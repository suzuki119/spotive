<?php

/**
 * lib/match.php
 * 観戦者向けページ（試合一覧・お気に入り）で使う試合データの読み込み。
 *
 * 観戦用の試合テーブルはまだ schema.sql に無いので（AGENTS.md「データベースの現状」）、
 * 試合は data/matches.json（仮データ）から読む。主催者が掲載した大会は
 * v_public_tournaments から取り、同じ形にそろえて合流させる。
 * 観戦用のテーブルができたら、load_json_matches() を DB からの取得に置き換える。
 */

declare(strict_types=1);

require_once __DIR__ . '/support.php';

/** 競技コード => 表示名。js/pages/map.js の SPORTS と揃える */
const MATCH_SPORT_LABELS = [
  'soccer'      => 'サッカー',
  'baseball'    => '野球',
  'basketball'  => 'バスケットボール',
  'volleyball'  => 'バレーボール',
  'futsal'      => 'フットサル',
  'tennis'      => 'テニス',
  'badminton'   => 'バドミントン',
  'rugby'       => 'ラグビー',
  'tabletennis' => '卓球',
];

/** 競技コードを、CSS の modifier に使えるキーにする。知らない競技は other */
function match_sport_key(string $sport): string
{
  return array_key_exists($sport, MATCH_SPORT_LABELS) ? $sport : 'other';
}

/** 競技の表示名。tournaments.sport は自由入力なので、知らない値はそのまま出す */
function match_sport_label(string $sport): string
{
  return MATCH_SPORT_LABELS[$sport] ?? $sport;
}

/** 料金の表示。null は未登録、0 円は無料 */
function match_price_label(?int $yen): string
{
  if ($yen === null) {
    return '料金未登録';
  }
  return $yen === 0 ? '無料' : '¥' . number_format($yen) . '〜';
}

/** 2026-10-03 => 10月3日(土)。今日・明日はそれも添える */
function match_date_label(string $date): string
{
  $time = strtotime($date);
  if ($time === false) {
    return $date;
  }
  $youbi = ['日', '月', '火', '水', '木', '金', '土'][(int) date('w', $time)];
  $label = date('n月j日', $time) . '(' . $youbi . ')';

  if ($date === date('Y-m-d')) {
    return '今日 ' . $label;
  }
  if ($date === date('Y-m-d', strtotime('+1 day'))) {
    return '明日 ' . $label;
  }
  return $label;
}

/**
 * data/ の JSON を配列で読む。読めないときは空配列（ページは白画面にしない）
 *
 * @return list<array<string,mixed>>
 */
function load_data_json(string $name): array
{
  $file = dirname(__DIR__) . '/data/' . $name;
  $json = is_file($file) ? file_get_contents($file) : false;
  $data = $json === false ? null : json_decode($json, true);

  if (!is_array($data)) {
    error_log('[SPOTIVE] data file could not be read: ' . $name);
    return [];
  }
  return array_values(array_filter($data, 'is_array'));
}

/** @return array<string,array<string,mixed>> チーム ID => チーム */
function load_teams(): array
{
  $teams = [];
  foreach (load_data_json('teams.json') as $team) {
    $teams[(string) ($team['id'] ?? '')] = $team;
  }
  unset($teams['']);
  return $teams;
}

/**
 * 画面で使う 1 試合の形。
 *
 * @return array{
 *   key:string, sport:string, title:string, date:string, time:string,
 *   venue:string, pref:string, price:?int, teamIds:list<string>, organizer:string,
 *   detailPath:string
 * }
 */
function match_row(
  string $key,
  string $sport,
  string $title,
  string $date,
  string $time,
  string $venue,
  string $pref,
  ?int $price,
  array $teamIds = [],
  string $organizer = '',
  string $detailPath = ''
): array {
  return compact(
    'key', 'sport', 'title', 'date', 'time', 'venue', 'pref', 'price', 'teamIds', 'organizer', 'detailPath'
  );
}

/**
 * 試合詳細（pages/match/match-detail.php）へのパス。アプリのルートからの形で返すので、
 * 出すときは url() を通すこと。
 *   type=match      … data/matches.json の仮データ（id は match-001 の形）
 *   type=tournament … v_public_tournaments の大会（id は数字）
 */
function match_detail_path(string $type, string $id): string
{
  if ($id === '') {
    return '';
  }
  return 'pages/match/match-detail.php?' . http_build_query(['type' => $type, 'id' => $id]);
}

/** @return list<array<string,mixed>> data/matches.json の試合（仮データ） */
function load_json_matches(): array
{
  $prefByArea = [];
  foreach (load_data_json('areas.json') as $area) {
    $prefByArea[(string) ($area['id'] ?? '')] = (string) ($area['name'] ?? '');
  }

  $rows = [];
  foreach (load_data_json('matches.json') as $m) {
    $date = (string) ($m['date'] ?? '');
    if (!preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $date)) {
      continue;
    }
    $rows[] = match_row(
      'json-' . (string) ($m['id'] ?? ''),
      (string) ($m['sport'] ?? ''),
      (string) ($m['title'] ?? ''),
      $date,
      (string) ($m['startTime'] ?? ''),
      (string) ($m['venue'] ?? ''),
      $prefByArea[(string) ($m['areaId'] ?? '')] ?? '',
      isset($m['priceMin']) ? (int) $m['priceMin'] : null,
      array_values(array_filter([(string) ($m['homeTeamId'] ?? ''), (string) ($m['awayTeamId'] ?? '')])),
      '',
      match_detail_path('match', (string) ($m['id'] ?? ''))
    );
  }
  return $rows;
}

/** @return list<array<string,mixed>> 主催者が掲載した大会。DB に繋がらなければ空 */
function load_tournament_matches(): array
{
  try {
    $list = db_all(
      'SELECT id, title, sport, starts_at, venue_name, venue_prefecture,
              entry_fee_yen, organizer_name
       FROM v_public_tournaments
       WHERE starts_at >= CURDATE()
       ORDER BY starts_at ASC
       LIMIT 500'
    );
  } catch (PDOException $e) {
    error_log('[SPOTIVE] DB error: ' . $e->getMessage());
    return [];
  }

  $rows = [];
  foreach ($list as $t) {
    $time = strtotime((string) $t['starts_at']);
    if ($time === false) {
      continue;
    }
    $rows[] = match_row(
      'tournament-' . (int) $t['id'],
      (string) $t['sport'],
      (string) $t['title'],
      date('Y-m-d', $time),
      date('H:i', $time),
      (string) $t['venue_name'],
      (string) $t['venue_prefecture'],
      $t['entry_fee_yen'] === null ? null : (int) $t['entry_fee_yen'],
      [],
      (string) $t['organizer_name'],
      match_detail_path('tournament', (string) (int) $t['id'])
    );
  }
  return $rows;
}

/**
 * 今日以降の試合を、日付・時刻の順に並べて返す。
 *
 * @return list<array<string,mixed>>
 */
function load_upcoming_matches(): array
{
  $today   = date('Y-m-d');
  $matches = array_filter(
    [...load_json_matches(), ...load_tournament_matches()],
    static fn(array $m): bool => $m['date'] >= $today
  );

  // 時刻未定（空文字）はその日の最後に回す
  usort($matches, static fn(array $a, array $b): int =>
    [$a['date'], $a['time'] === '' ? '99:99' : $a['time']]
      <=> [$b['date'], $b['time'] === '' ? '99:99' : $b['time']]);

  return array_values($matches);
}

/**
 * 日付ごとにまとめる。
 *
 * @param list<array<string,mixed>> $matches load_upcoming_matches() の結果
 * @return array<string,list<array<string,mixed>>> Y-m-d => その日の試合
 */
function group_matches_by_date(array $matches): array
{
  $days = [];
  foreach ($matches as $m) {
    $days[$m['date']][] = $m;
  }
  return $days;
}
