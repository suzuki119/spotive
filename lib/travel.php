<?php

/**
 * lib/travel.php
 * 遠征サポート（主要機能 5）。設計は docs/away-travel.md。
 *
 *   応援チームのアウェー戦を「遠征」にまとめ、交通・ホテル・費用の目安を出す。
 *   旅程の前後に会場の近くで観られる試合も探す。プランは travel_plans / travel_plan_items に保存する。
 *
 * 交通は時刻表・運賃のデータが無いので、出発地から会場までの直線距離から計算した「目安」。
 * 画面では必ず「目安」と表示すること。数字を変えるときは、下の定数だけを直す。
 */

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/match.php';
require_once __DIR__ . '/hotel.php';

const TRAVEL_ARRIVE_BEFORE_MIN = 120;     // 最初の試合の何分前に着くか
const TRAVEL_RETURN_AFTER_MIN  = 30;      // 試合の終了予定から何分後に帰りの便に乗るか
const TRAVEL_EARLIEST_DEPART   = '06:00'; // これより早く出る必要があれば前泊する
const TRAVEL_PRE_NIGHT_DEPART  = '16:00'; // 前泊するときに前日に出る時刻
const TRAVEL_LATEST_ARRIVE     = '23:30'; // これより遅く着くなら後泊する
const TRAVEL_POST_NIGHT_DEPART = '10:00'; // 後泊したときに翌日に出る時刻
const TRAVEL_DEFAULT_START     = '13:00'; // 開始時刻が決まっていない試合
const TRAVEL_VENUE_LEAD_MIN    = 60;      // 「会場へ」を出す、試合の何分前か
const TRAVEL_MEAL_YEN_PER_DAY  = 2000;    // 食事ほか（1 日あたり）
const TRAVEL_HOTEL_FALLBACK_YEN = 9000;   // 会場の近くにホテルの仮データが無いときの 1 泊の目安
const TRAVEL_NEARBY_KM         = 50.0;    // 「この遠征中に観られる試合」を探す範囲
const TRAVEL_BETWEEN_GAMES_MIN = 60;     // 試合と試合の間に必要な移動の時間（これより近いと重なる扱い）
const TRAVEL_EXTRA_LIMIT       = 5;       // プランに追加できる試合の数
const TRAVEL_NEARBY_LIMIT      = 6;       // 候補として出す試合の数

/** 競技ごとの試合時間（分）。載っていない競技は TRAVEL_GAME_MINUTES_DEFAULT */
const TRAVEL_GAME_MINUTES = [
  'baseball'   => 210,
  'soccer'     => 120,
  'basketball' => 120,
  'volleyball' => 150,
];
const TRAVEL_GAME_MINUTES_DEFAULT = 150;

/**
 * 交通手段の目安。直線距離（km）が under 未満なら、その手段にする。
 *   minutes = base_min ＋ 移動距離 ÷ 時速
 *   fare    = base_yen ＋ 移動距離 × yen_per_km
 *   移動距離 = 直線距離 × detour（線路・道路の回り道の分）
 */
const TRAVEL_MODES = [
  'local'   => ['under' => 40,  'label' => '在来線・バス',  'detour' => 1.0, 'base_min' => 20,  'kmh' => 40,  'base_yen' => 200,   'yen_per_km' => 20],
  'express' => ['under' => 800, 'label' => '新幹線・特急',  'detour' => 1.2, 'base_min' => 30,  'kmh' => 200, 'base_yen' => 1500,  'yen_per_km' => 28],
  'plane'   => ['under' => INF, 'label' => '飛行機',        'detour' => 1.0, 'base_min' => 150, 'kmh' => 700, 'base_yen' => 15000, 'yen_per_km' => 12],
];

const TRAVEL_BOOKING_LABELS = [
  'transport' => ['todo' => '購入済みにする', 'done' => '購入済み'],
  'match'     => ['todo' => '購入済みにする', 'done' => '購入済み'],
  'hotel'     => ['todo' => '予約済みにする', 'done' => '予約済み'],
];

// ---------------------------------------------------------------------
// データの読み込み
// ---------------------------------------------------------------------

/** @return array<string,array<string,mixed>> エリア ID => エリア（出発地の候補） */
function travel_areas(): array
{
  $areas = [];
  foreach (load_data_json('areas.json') as $area) {
    if (isset($area['id'], $area['lat'], $area['lng'])) {
      $areas[(string) $area['id']] = $area;
    }
  }
  return $areas;
}

/** 試合（lib/match.php の match_row の形）を、遠征の計算で使う形にする */
function travel_game(array $row): array
{
  static $ticketUrls = null;
  static $teams      = null;
  $ticketUrls ??= array_column(load_data_json('matches.json'), 'ticketUrl', 'id');
  $teams      ??= load_teams();

  $ref   = str_starts_with($row['key'], 'json-') ? substr($row['key'], 5) : $row['key'];
  $names = array_map(static fn(string $id): string => (string) ($teams[$id]['name'] ?? ''), $row['teamIds']);
  $url   = (string) ($ticketUrls[$ref] ?? '');

  return [
    'ref'     => $ref,
    'row'     => $row,
    'sport'   => $row['sport'],
    'title'   => count($names) === 2 && !in_array('', $names, true) ? $names[0] . ' vs ' . $names[1] : $row['title'],
    'home'    => $row['teamIds'][0] ?? '',
    'away'    => $row['teamIds'][1] ?? '',
    'date'    => $row['date'],
    'time'    => $row['time'] === '' ? TRAVEL_DEFAULT_START : $row['time'],
    'venue'   => $row['venue'],
    'pref'    => $row['pref'],
    'lat'     => (float) $row['lat'],
    'lng'     => (float) $row['lng'],
    'price'   => $row['price'],
    'url'     => preg_match('#\Ahttps?://#i', $url) === 1 ? $url : '',
  ];
}

/** 開始時刻（UNIX 時刻） */
function travel_game_start(array $game): int
{
  return (int) strtotime($game['date'] . ' ' . $game['time']);
}

/** 終了予定（UNIX 時刻） */
function travel_game_end(array $game): int
{
  $minutes = TRAVEL_GAME_MINUTES[match_sport_key($game['sport'])] ?? TRAVEL_GAME_MINUTES_DEFAULT;
  return travel_game_start($game) + $minutes * 60;
}

/** @return list<array<string,mixed>> 位置の分かる、今日以降の試合（遠征の計算で使う形） */
function travel_upcoming_games(): array
{
  static $games = null;
  $games ??= array_map(
    'travel_game',
    array_values(array_filter(load_upcoming_matches(), static fn(array $m): bool => $m['lat'] !== null && $m['lng'] !== null))
  );
  return $games;
}

/**
 * チームのアウェー戦を「遠征」にまとめる。同じ会場で日付が続く試合（連戦）は 1 回の遠征にする。
 *
 * @return list<array{games:list<array<string,mixed>>,venue:string,start:string,end:string}>
 */
function travel_trips(string $teamId): array
{
  $trips = [];
  foreach (travel_upcoming_games() as $game) {
    if ($game['away'] !== $teamId) {
      continue;
    }
    $last = array_key_last($trips);
    $isSeries = $last !== null
      && $trips[$last]['venue'] === $game['venue']
      && strtotime($game['date']) - strtotime($trips[$last]['end']) <= 86400;

    if ($isSeries) {
      $trips[$last]['games'][] = $game;
      $trips[$last]['end']     = $game['date'];
    } else {
      $trips[] = ['games' => [$game], 'venue' => $game['venue'], 'start' => $game['date'], 'end' => $game['date']];
    }
  }
  return $trips;
}

/** 試合 ID（match-001）から、その試合を含む遠征を探す。無ければ null */
function travel_find_trip(string $teamId, string $gameRef): ?array
{
  foreach (travel_trips($teamId) as $trip) {
    foreach ($trip['games'] as $game) {
      if ($game['ref'] === $gameRef) {
        return $trip;
      }
    }
  }
  return null;
}

/** 試合 ID（match-001）から試合を探す。無ければ null */
function travel_find_game(string $gameRef): ?array
{
  foreach (travel_upcoming_games() as $game) {
    if ($game['ref'] === $gameRef) {
      return $game;
    }
  }
  return null;
}

// ---------------------------------------------------------------------
// 計算
// ---------------------------------------------------------------------

/**
 * 交通の目安。直線距離から手段・所要時間・片道運賃を出す。
 *
 * @return array{mode:string,label:string,minutes:int,fare:int,km:float}
 */
function travel_transport(float $fromLat, float $fromLng, float $toLat, float $toLng): array
{
  $km = hotel_distance_km($fromLat, $fromLng, $toLat, $toLng);

  foreach (TRAVEL_MODES as $mode => $m) {
    if ($km < $m['under']) {
      $moveKm  = $km * $m['detour'];
      $minutes = $m['base_min'] + $moveKm / $m['kmh'] * 60;
      $fare    = $m['base_yen'] + $moveKm * $m['yen_per_km'];
      return [
        'mode'    => $mode,
        'label'   => $m['label'],
        'minutes' => (int) (ceil($minutes / 10) * 10),
        'fare'    => (int) (ceil($fare / 100) * 100),
        'km'      => $km,
      ];
    }
  }
  throw new LogicException('TRAVEL_MODES の最後は under => INF にすること');
}

/** 1h30m / 40m */
function travel_duration_label(int $minutes): string
{
  $h = intdiv($minutes, 60);
  $m = $minutes % 60;
  return ($h > 0 ? $h . 'h' : '') . ($m > 0 || $h === 0 ? $m . 'm' : '');
}

/** 10 分単位に切り捨て・切り上げ */
function travel_round_10(int $time, bool $up): int
{
  $step = 600;
  return $up ? (int) (ceil($time / $step) * $step) : intdiv($time, $step) * $step;
}

/** @return list<array<string,mixed>> 会場の近くのホテル（近い順）。画面とプランで使う形 */
function travel_hotel_options(float $lat, float $lng): array
{
  $list = [];
  foreach (hotels_near($lat, $lng) as $hotel) {
    $url    = (string) ($hotel['reserveUrl'] ?? '');
    $list[] = [
      'id'       => (string) $hotel['id'],
      'name'     => (string) $hotel['name'],
      'price'    => (int) ($hotel['priceMin'] ?? TRAVEL_HOTEL_FALLBACK_YEN),
      'walk'     => hotel_walk_minutes((float) $hotel['distance']),
      'checkIn'  => (string) ($hotel['checkIn'] ?? '15:00'),
      'checkOut' => (string) ($hotel['checkOut'] ?? '11:00'),
      'url'      => preg_match('#\Ahttps?://#i', $url) === 1 ? $url : '',
    ];
  }
  return $list;
}

/** 2 試合が、移動の時間も含めて重なるか（両方は観に行けないか） */
function travel_games_conflict(array $a, array $b): bool
{
  $gap = TRAVEL_BETWEEN_GAMES_MIN * 60;
  return travel_game_start($a) < travel_game_end($b) + $gap
    && travel_game_start($b) < travel_game_end($a) + $gap;
}

/**
 * 遠征の見積もり。画面の目安とプランの保存の両方で使う（計算をここ 1 か所にするため）。
 *
 * 行きは最初の試合の会場へ、帰りは最後の試合の会場から。ホテルは遠征の本来の会場（$main）の近くで選ぶ
 * （前後に追加した試合の会場を基準にすると、画面で選んだホテルが候補から外れてしまうため）。
 *
 * @param list<array<string,mixed>> $games   観る試合（遠征の試合 ＋ 追加した試合）。1 件以上
 * @param array<string,mixed>       $area    出発地（travel_areas() の 1 件）
 * @param string                    $hotelId 選んだホテル。空なら会場に一番近いホテル
 * @param array<string,mixed>|null  $main    遠征の本来の試合。省略時は最初の試合
 */
function travel_estimate(array $games, array $area, string $hotelId = '', ?array $main = null): array
{
  usort($games, static fn(array $a, array $b): int => travel_game_start($a) <=> travel_game_start($b));
  $first = $games[0];
  $last  = $games[0];
  foreach ($games as $game) {
    $last = travel_game_end($game) >= travel_game_end($last) ? $game : $last;
  }
  $main ??= $first;

  $transport = travel_transport((float) $area['lat'], (float) $area['lng'], $first['lat'], $first['lng']);
  $return    = travel_transport($last['lat'], $last['lng'], (float) $area['lat'], (float) $area['lng']);

  // 行き：最初の試合の少し前に着く。早すぎる出発になるなら前日に出て前泊する
  $goSec    = $transport['minutes'] * 60;
  $goDepart = travel_round_10(travel_game_start($first) - TRAVEL_ARRIVE_BEFORE_MIN * 60 - $goSec, false);
  if ($goDepart < strtotime($first['date'] . ' ' . TRAVEL_EARLIEST_DEPART)) {
    $goDepart = (int) strtotime($first['date'] . ' ' . TRAVEL_PRE_NIGHT_DEPART . ' -1 day');
  }
  $goArrive = $goDepart + $goSec;

  // 帰り：最後の試合の終了予定のあと。遅くなりすぎるなら後泊して翌朝に帰る
  $backSec    = $return['minutes'] * 60;
  $lastEnd    = travel_game_end($last);
  $lastDate   = date('Y-m-d', $lastEnd);
  $backDepart = travel_round_10($lastEnd + TRAVEL_RETURN_AFTER_MIN * 60, true);
  if ($backDepart + $backSec > strtotime($lastDate . ' ' . TRAVEL_LATEST_ARRIVE)) {
    $backDepart = (int) strtotime($lastDate . ' ' . TRAVEL_POST_NIGHT_DEPART . ' +1 day');
  }
  $backArrive = $backDepart + $backSec;

  // 宿泊：行きの到着日から帰りの出発日まで
  $nights  = (int) round((strtotime(date('Y-m-d', $backDepart)) - strtotime(date('Y-m-d', $goArrive))) / 86400);
  $options = travel_hotel_options($main['lat'], $main['lng']);
  $hotel   = null;
  if ($nights > 0) {
    $hotel = $options[0] ?? [
      'id' => '', 'name' => '会場周辺のホテル（目安）', 'price' => TRAVEL_HOTEL_FALLBACK_YEN,
      'walk' => null, 'checkIn' => '15:00', 'checkOut' => '11:00', 'url' => '',
    ];
    foreach ($options as $option) {
      if ($option['id'] === $hotelId) {
        $hotel = $option;
      }
    }
    // チェックインは到着より前にしない。チェックアウトは帰りの便に間に合う時刻にする
    $hotel['checkinAt']  = max((int) strtotime(date('Y-m-d', $goArrive) . ' ' . $hotel['checkIn']), $goArrive);
    $hotel['checkoutAt'] = min((int) strtotime(date('Y-m-d', $backDepart) . ' ' . $hotel['checkOut']), $backDepart - 1800);
    $hotel['nights']     = $nights;
  }

  $start = date('Y-m-d', $goDepart);
  $end   = date('Y-m-d', $backArrive);
  $days  = (int) round((strtotime($end) - strtotime($start)) / 86400) + 1;

  $ticket  = array_sum(array_map(static fn(array $g): int => (int) ($g['price'] ?? 0), $games));
  $cost = [
    'transport'      => $transport['fare'] + $return['fare'],
    'hotel'          => $hotel === null ? 0 : $hotel['price'] * $nights,
    'ticket'         => $ticket,
    'meal'           => TRAVEL_MEAL_YEN_PER_DAY * $days,
    'ticket_unknown' => in_array(null, array_column($games, 'price'), true),
  ];
  $cost['total'] = $cost['transport'] + $cost['hotel'] + $cost['ticket'] + $cost['meal'];

  return [
    'games'     => $games,
    'first'     => $first,
    'last'      => $last,
    'area'      => $area,
    'transport' => $transport,   // 行き
    'return'    => $return,      // 帰り
    'go'        => ['depart' => $goDepart, 'arrive' => $goArrive],
    'back'      => ['depart' => $backDepart, 'arrive' => $backArrive],
    'hotel'     => $hotel,
    'hotels'    => $options,
    'start'     => $start,
    'end'       => $end,
    'days'      => $days,
    'cost'      => $cost,
  ];
}

/**
 * この遠征中に観られる試合。旅程の前日〜翌日に、会場から近くで行われるもの。
 * 遠征の試合と時間が重なるもの（観に行けない）は除く。
 *
 * @param array<string,mixed> $trip travel_trips() の 1 件
 * @return list<array<string,mixed>>
 */
function travel_nearby_games(array $trip): array
{
  $base  = $trip['games'][0];
  $from  = date('Y-m-d', (int) strtotime($trip['start'] . ' -1 day'));
  $to    = date('Y-m-d', (int) strtotime($trip['end'] . ' +1 day'));
  $refs  = array_column($trip['games'], 'ref');

  $list = [];
  foreach (travel_upcoming_games() as $game) {
    if ($game['date'] < $from || $game['date'] > $to || in_array($game['ref'], $refs, true)) {
      continue;
    }
    $km = hotel_distance_km($base['lat'], $base['lng'], $game['lat'], $game['lng']);
    if ($km > TRAVEL_NEARBY_KM) {
      continue;
    }
    foreach ($trip['games'] as $tripGame) {
      if (travel_games_conflict($game, $tripGame)) {
        continue 2;
      }
    }
    $list[] = $game + ['km' => $km];
    if (count($list) >= TRAVEL_NEARBY_LIMIT) {
      break;
    }
  }
  return $list;
}

// ---------------------------------------------------------------------
// プランの保存・取得
// ---------------------------------------------------------------------

/**
 * プランを作る。金額などは受け取らず、ここで計算し直してから保存する。
 *
 * @param array<string,mixed> $in team / game / from / hotel / extras[]
 * @return int travel_plans.id
 */
function travel_create_plan(array $user, array $in): int
{
  $teamId  = (string) ($in['team'] ?? '');
  $teams   = load_teams();
  $areas   = travel_areas();
  $trip    = isset($teams[$teamId]) ? travel_find_trip($teamId, (string) ($in['game'] ?? '')) : null;
  $area    = $areas[(string) ($in['from'] ?? '')] ?? null;

  if ($trip === null) {
    throw new AppError('遠征する試合が見つかりません。もう一度選び直してください。');
  }
  if ($area === null) {
    throw new AppError('出発地を選んでください。');
  }

  // 追加する試合は、候補として出したものだけを受け付ける
  $picked = array_map('strval', array_filter((array) ($in['extras'] ?? []), 'is_string'));
  // 追加した試合どうしが重なるときは、先に並んでいる方だけを残す
  $extras = [];
  foreach (travel_nearby_games($trip) as $game) {
    if (!in_array($game['ref'], $picked, true) || count($extras) >= TRAVEL_EXTRA_LIMIT) {
      continue;
    }
    foreach ($extras as $kept) {
      if (travel_games_conflict($game, $kept)) {
        continue 2;
      }
    }
    $extras[] = $game;
  }

  $plan     = travel_estimate([...$trip['games'], ...$extras], $area, (string) ($in['hotel'] ?? ''), $trip['games'][0]);
  $opponent = (string) ($teams[$trip['games'][0]['home']]['name'] ?? $trip['venue']);
  $title    = mb_substr((string) $teams[$teamId]['name'] . 'vs' . $opponent . '観戦プラン', 0, 150);

  return db_transaction(static function () use ($user, $teamId, $title, $area, $plan): int {
    $planId = db_insert('travel_plans', [
      'user_id'             => $user['id'],
      'team_id'             => $teamId,
      'title'               => $title,
      'depart_area_id'      => (string) $area['id'],
      'start_date'          => $plan['start'],
      'end_date'            => $plan['end'],
      'estimated_total_yen' => $plan['cost']['total'],
    ]);

    foreach (travel_plan_rows($plan) as $row) {
      db_insert('travel_plan_items', $row + ['plan_id' => $planId]);
    }

    audit_log((int) $user['id'], 'travel.create', 'travel_plan', $planId);
    return $planId;
  });
}

/** 見積もりを travel_plan_items の行にする（plan_id 以外） */
function travel_plan_rows(array $plan): array
{
  $dt   = static fn(int $time): string => date('Y-m-d H:i:s', $time);
  $home = mb_substr((string) $plan['area']['name'], 0, 50);

  $rows = [
    ['item_type' => 'transport', 'ref_id' => null, 'title' => $plan['transport']['label'] . '（目安）',
     'from_label' => $home, 'to_label' => travel_place_label($plan['first']),
     'starts_at' => $dt($plan['go']['depart']), 'ends_at' => $dt($plan['go']['arrive']),
     'price_yen' => $plan['transport']['fare'], 'url' => null],
    ['item_type' => 'transport', 'ref_id' => null, 'title' => $plan['return']['label'] . '（目安）',
     'from_label' => travel_place_label($plan['last']), 'to_label' => $home,
     'starts_at' => $dt($plan['back']['depart']), 'ends_at' => $dt($plan['back']['arrive']),
     'price_yen' => $plan['return']['fare'], 'url' => null],
  ];

  foreach ($plan['games'] as $game) {
    $rows[] = [
      'item_type'  => 'match',
      'ref_id'     => mb_substr($game['ref'], 0, 32),
      'title'      => mb_substr($game['title'], 0, 150),
      'from_label' => mb_substr($game['venue'], 0, 50),
      'to_label'   => null,
      'starts_at'  => $dt(travel_game_start($game)),
      'ends_at'    => $dt(travel_game_end($game)),
      'price_yen'  => $game['price'],
      'url'        => $game['url'] === '' ? null : $game['url'],
    ];
  }

  if ($plan['hotel'] !== null) {
    $rows[] = [
      'item_type'  => 'hotel',
      'ref_id'     => $plan['hotel']['id'] === '' ? null : $plan['hotel']['id'],
      'title'      => mb_substr($plan['hotel']['name'], 0, 150),
      'from_label' => null,
      'to_label'   => null,
      'starts_at'  => $dt($plan['hotel']['checkinAt']),
      'ends_at'    => $dt($plan['hotel']['checkoutAt']),
      'price_yen'  => $plan['hotel']['price'],
      'url'        => $plan['hotel']['url'] === '' ? null : $plan['hotel']['url'],
    ];
  }
  return $rows;
}

/** @return list<array<string,mixed>> 自分のプラン（出発の近い順） */
function travel_plans_mine(int $userId): array
{
  return db_all(
    'SELECT id, team_id, title, start_date, end_date, estimated_total_yen
       FROM travel_plans
      WHERE user_id = :u
      ORDER BY start_date ASC, id ASC',
    ['u' => $userId]
  );
}

/** 自分のプランと、その予定（時刻順）。他人のプランは見つからない扱いにする */
function travel_plan_owned(int $planId, int $userId): array
{
  $plan = db_one(
    'SELECT * FROM travel_plans WHERE id = :id AND user_id = :u',
    ['id' => $planId, 'u' => $userId]
  );
  if ($plan === null) {
    throw new AppError('プランが見つかりません。');
  }
  $plan['items'] = db_all(
    'SELECT * FROM travel_plan_items WHERE plan_id = :p ORDER BY starts_at ASC, id ASC',
    ['p' => $planId]
  );
  return $plan;
}

/**
 * マイページ用：まだ終わっていないプランのうち、一番近いもの（予定つき）と、その件数。
 *
 * @return array{plan:array<string,mixed>|null,count:int}
 */
function travel_upcoming_plan(int $userId): array
{
  $rows = db_all(
    'SELECT id FROM travel_plans
      WHERE user_id = :u AND end_date >= CURDATE()
      ORDER BY start_date ASC, id ASC',
    ['u' => $userId]
  );
  return [
    'plan'  => $rows === [] ? null : travel_plan_owned((int) $rows[0]['id'], $userId),
    'count' => count($rows),
  ];
}

/**
 * 試合の予定の、対戦する 2 チームの ID（ホーム、アウェーの順）。
 * 仮データ（data/matches.json）の試合だけ。主催者の大会や見つからない試合は空配列
 *
 * @return list<string>
 */
function travel_item_team_ids(array $item): array
{
  static $byId = null;
  $byId ??= array_column(load_data_json('matches.json'), null, 'id');

  $match = $byId[(string) ($item['ref_id'] ?? '')] ?? null;
  if ($match === null) {
    return [];
  }
  return array_values(array_filter([(string) ($match['homeTeamId'] ?? ''), (string) ($match['awayTeamId'] ?? '')]));
}

/** 「購入済み」「予約済み」の印を切り替える（自分のプランの予定だけ） */
function travel_toggle_booking(int $userId, int $itemId): void
{
  $updated = db_run(
    'UPDATE travel_plan_items i
       JOIN travel_plans p ON p.id = i.plan_id
        SET i.booking_status = IF(i.booking_status = "booked", "none", "booked")
      WHERE i.id = :id AND p.user_id = :u',
    ['id' => $itemId, 'u' => $userId]
  )->rowCount();

  if ($updated === 0) {
    throw new AppError('予定が見つかりません。');
  }
}

function travel_delete_plan(int $userId, int $planId): void
{
  $deleted = db_run(
    'DELETE FROM travel_plans WHERE id = :id AND user_id = :u',
    ['id' => $planId, 'u' => $userId]
  )->rowCount();

  if ($deleted === 0) {
    throw new AppError('プランが見つかりません。');
  }
  audit_log($userId, 'travel.delete', 'travel_plan', $planId);
}

// ---------------------------------------------------------------------
// 予定表の表示
// ---------------------------------------------------------------------

/**
 * 予定表の行を日付ごとにまとめる。「到着」「会場へ」「チェックアウト」は、ここで items から作る。
 * 1 行 = ['at' => UNIX 時刻, 'time' => '09:10' か null, 'text' => 文字か null, 'item' => 予定か null]
 *
 * @return array<string,list<array<string,mixed>>> Y-m-d => その日の行
 */
function travel_timeline(array $items): array
{
  $rows  = [];
  $order = 0;
  $add   = static function (int $at, ?string $text, ?array $item, bool $showTime = true) use (&$rows, &$order): void {
    $rows[] = ['at' => $at, 'order' => $order++, 'time' => $showTime ? date('H:i', $at) : null, 'text' => $text, 'item' => $item];
  };

  foreach ($items as $item) {
    $start = (int) strtotime((string) $item['starts_at']);
    $end   = $item['ends_at'] === null ? $start : (int) strtotime((string) $item['ends_at']);

    switch ($item['item_type']) {
      case 'transport':
        $add($start, $item['from_label'] . 'を出発', $item);
        $add($end, $item['to_label'] . 'に到着', null);
        break;

      case 'match':
        $add($start - TRAVEL_VENUE_LEAD_MIN * 60, '会場へ', null);
        $add($start, null, $item, false);
        break;

      case 'hotel':
        $add($start, 'ホテルにチェックイン', $item);
        $add($end, 'チェックアウト', null);
        break;
    }
  }

  usort($rows, static fn(array $a, array $b): int => [$a['at'], $a['order']] <=> [$b['at'], $b['order']]);

  $days = [];
  foreach ($rows as $row) {
    $days[date('Y-m-d', $row['at'])][] = $row;
  }
  return $days;
}

/** 試合の予定から、試合詳細へのパス（アプリのルートから）。出すときは url() を通すこと */
function travel_item_detail_path(array $item): string
{
  $ref = (string) ($item['ref_id'] ?? '');
  if ($item['item_type'] === 'hotel') {
    return $ref === '' ? '' : hotel_detail_path($ref);
  }
  if (str_starts_with($ref, 'tournament-')) {
    return match_detail_path('tournament', substr($ref, strlen('tournament-')));
  }
  return $ref === '' ? '' : match_detail_path('match', $ref);
}

/** 交通の行き先・出発地の表示（会場の都道府県。無ければ会場名） */
function travel_place_label(array $game): string
{
  return mb_substr($game['pref'] !== '' ? $game['pref'] : $game['venue'], 0, 50);
}

/** 2026-09-26 => 2026/09/26。$short なら 09/26 */
function travel_date_label(string $date, bool $short = false): string
{
  $time = strtotime($date);
  return $time === false ? $date : date($short ? 'm/d' : 'Y/m/d', $time);
}

/** ¥22,000 */
function travel_yen(int $yen): string
{
  return '¥' . number_format($yen);
}
