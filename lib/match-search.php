<?php

/**
 * lib/match-search.php
 * 条件検索（企画書の機能 3）。キーワード・競技・都道府県・日付・料金で試合を絞り込む。
 *
 * 対象は lib/match.php の load_upcoming_matches() と同じ
 * （data/matches.json の仮データ ＋ v_public_tournaments の掲載大会）。
 * 件数が少ない仮データの間は PHP 側で絞り込む。観戦用のテーブルができたら SQL に置き換える。
 */

declare(strict_types=1);

require_once __DIR__ . '/match.php';
require_once __DIR__ . '/validator.php';

/** 「いつ」の選択肢。キーは GET の when の値 */
const MATCH_SEARCH_WHEN = [
  ''         => '指定しない',
  'today'    => '今日',
  'tomorrow' => '明日',
  'weekend'  => '今週末（土日）',
  'week'     => '7日以内',
];

/** 料金の上限の選択肢（円）。0 は「無料のみ」 */
const MATCH_SEARCH_PRICES = [0, 1000, 2000, 3000, 5000, 10000];

/** 価格のスライダー。右端（10 万円）は「上限なし」として扱う */
const MATCH_SEARCH_PRICE_MAX  = 100000;
const MATCH_SEARCH_PRICE_STEP = 500;

/** 「現在地から○km以内」の選択肢 */
const MATCH_SEARCH_NEAR_KM = [1, 3, 5, 10, 30];

/**
 * 検索画面の上のタブ。キーは競技コード（空文字は ALL）。
 * リーグ名で見せているが、絞り込みは競技で行う（リーグのデータはまだ無い）
 */
const MATCH_SEARCH_TABS = [
  ''           => 'ALL',
  'baseball'   => 'プロ野球',
  'soccer'     => 'Jリーグ',
  'basketball' => 'B.LEAGUE',
  'volleyball' => 'SV LEAGUE',
];

/** 都道府県の並び順（JIS の番号順）。選択肢をこの順に出す */
const MATCH_SEARCH_PREF_ORDER = [
  '北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県',
  '茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県',
  '新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県', '岐阜県', '静岡県', '愛知県',
  '三重県', '滋賀県', '京都府', '大阪府', '兵庫県', '奈良県', '和歌山県',
  '鳥取県', '島根県', '岡山県', '広島県', '山口県',
  '徳島県', '香川県', '愛媛県', '高知県',
  '福岡県', '佐賀県', '長崎県', '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県',
];

/**
 * 競技名を競技コードに寄せる。tournaments.sport は自由入力なので「サッカー」とも書かれる。
 * 寄せ方は js/pages/map.js の SPORT_KEYS と揃える。どれにも当たらなければ other。
 */
function match_search_sport_key(string $sport): string
{
  if (array_key_exists($sport, MATCH_SPORT_LABELS)) {
    return $sport;
  }
  $patterns = [
    '/野球|ベースボール|baseball/iu'   => 'baseball',
    '/フットサル|futsal/iu'            => 'futsal',
    '/サッカー|フット|soccer|football/iu' => 'soccer',
    '/バスケ|basketball/iu'            => 'basketball',
    '/バレー|volleyball/iu'            => 'volleyball',
    '/ラグビー|rugby/iu'               => 'rugby',
    '/卓球|table.?tennis/iu'           => 'tabletennis',
    '/バドミントン|badminton/iu'       => 'badminton',
    '/テニス|tennis/iu'                => 'tennis',
  ];
  foreach ($patterns as $pattern => $key) {
    if (preg_match($pattern, $sport) === 1) {
      return $key;
    }
  }
  return 'other';
}

/**
 * GET の値を、型の決まった検索条件にする。知らない値は「指定なし」に倒す。
 *
 *   from / to … 日程の範囲（Y-m-d）。片方だけでもよい
 *   near      … 現在地から○km以内。現在地はサーバーに送らないので、絞り込みはブラウザ側で行う
 *   max_price … 料金の上限。0〜10 万円。10 万円（スライダーの右端）は「上限なし」
 *   when / date は以前の画面（トップの「条件から探す」）からのリンク用に残している
 *
 * @return array{keyword:string, sport:string, pref:string, near:?int, from:string, to:string,
 *               when:string, date:string, maxPrice:?int}
 */
function match_search_conditions(array $get): array
{
  $keyword = input_string($get, 'keyword');
  $sport   = input_string($get, 'sport');
  $pref    = input_string($get, 'pref');
  $near    = filter_var($get['near'] ?? null, FILTER_VALIDATE_INT);
  $from    = input_string($get, 'from');
  $to      = input_string($get, 'to');
  $when    = input_string($get, 'when');
  $date    = input_string($get, 'date');
  $price   = filter_var($get['max_price'] ?? null, FILTER_VALIDATE_INT);

  $from = Validator::isDate($from) ? $from : '';
  $to   = Validator::isDate($to) ? $to : '';
  if ($from !== '' && $to !== '' && $to < $from) {
    [$from, $to] = [$to, $from];   // 逆に選ばれたら入れ替える
  }

  $near = is_int($near) && in_array($near, MATCH_SEARCH_NEAR_KM, true) ? $near : null;

  return [
    'keyword'  => mb_substr($keyword, 0, 50),
    'sport'    => $sport === 'other' || array_key_exists($sport, MATCH_SPORT_LABELS) ? $sport : '',
    // 現在地から探すときは、都道府県は使わない
    'pref'     => $near === null && in_array($pref, MATCH_SEARCH_PREF_ORDER, true) ? $pref : '',
    'near'     => $near,
    'from'     => $from,
    'to'       => $to,
    'when'     => array_key_exists($when, MATCH_SEARCH_WHEN) ? $when : '',
    'date'     => Validator::isDate($date) ? $date : '',
    'maxPrice' => is_int($price) && $price >= 0 && $price < MATCH_SEARCH_PRICE_MAX
      ? intdiv($price, MATCH_SEARCH_PRICE_STEP) * MATCH_SEARCH_PRICE_STEP
      : null,
  ];
}

/** 何か 1 つでも条件が指定されているか */
function match_search_is_active(array $cond): bool
{
  return $cond['keyword'] !== '' || $cond['sport'] !== '' || $cond['pref'] !== '' || $cond['near'] !== null
    || $cond['from'] !== '' || $cond['to'] !== ''
    || $cond['when'] !== '' || $cond['date'] !== '' || $cond['maxPrice'] !== null;
}

/**
 * 「いつ」を日付の範囲にする。日付を直接指定していればそちらを優先する。
 *
 * @return array{0:string,1:string}|null [開始日, 終了日]（Y-m-d）。指定なしは null
 */
function match_search_date_range(string $when, string $date): ?array
{
  if ($date !== '') {
    return [$date, $date];
  }

  $today = strtotime('today');
  $day   = static fn(int $offset): string => date('Y-m-d', strtotime("+{$offset} day", $today));

  switch ($when) {
    case 'today':
      return [$day(0), $day(0)];
    case 'tomorrow':
      return [$day(1), $day(1)];
    case 'week':
      return [$day(0), $day(6)];
    case 'weekend':
      // 日曜なら今日だけ。それ以外は次の土曜〜日曜（土曜なら今日〜明日）
      $w = (int) date('w', $today);
      if ($w === 0) {
        return [$day(0), $day(0)];
      }
      return [$day(6 - $w), $day(7 - $w)];
    default:
      return null;
  }
}

/**
 * 試合を条件で絞り込む。並び順は受け取ったまま（load_upcoming_matches() の日時順）。
 *
 * @param list<array<string,mixed>> $matches
 * @return list<array<string,mixed>>
 */
function match_search(array $matches, array $cond): array
{
  // 日程の範囲を選んでいればそれを、無ければ以前の「いつ」「日付」を使う
  $range = $cond['from'] !== '' || $cond['to'] !== ''
    ? [$cond['from'] !== '' ? $cond['from'] : date('Y-m-d'), $cond['to'] !== '' ? $cond['to'] : '9999-12-31']
    : match_search_date_range($cond['when'], $cond['date']);

  return array_values(array_filter($matches, static function (array $m) use ($cond, $range): bool {
    if ($cond['sport'] !== '' && match_search_sport_key((string) $m['sport']) !== $cond['sport']) {
      return false;
    }
    if ($cond['pref'] !== '' && $m['pref'] !== $cond['pref']) {
      return false;
    }
    if ($range !== null && ($m['date'] < $range[0] || $m['date'] > $range[1])) {
      return false;
    }
    // 料金が分からない試合は、上限を指定したときには出さない
    if ($cond['maxPrice'] !== null && ($m['price'] === null || $m['price'] > $cond['maxPrice'])) {
      return false;
    }
    if ($cond['keyword'] !== '') {
      $haystack = implode(' ', [$m['title'], $m['venue'], $m['pref'], $m['organizer']]);
      if (mb_stripos($haystack, $cond['keyword']) === false) {
        return false;
      }
    }
    return true;
  }));
}

/**
 * 選択肢は「実際にある試合」から作る（選んでも 0 件、を減らすため）。
 *
 * @param list<array<string,mixed>> $matches
 * @return array{sports:array<string,string>, prefs:list<string>}
 */
function match_search_options(array $matches): array
{
  $sports = [];
  $prefs  = [];
  foreach ($matches as $m) {
    $key = match_search_sport_key((string) $m['sport']);
    $sports[$key] = MATCH_SPORT_LABELS[$key] ?? 'その他';
    if ($m['pref'] !== '') {
      $prefs[$m['pref']] = true;
    }
  }

  // 競技は MATCH_SPORT_LABELS の順、「その他」は最後
  $order = array_flip([...array_keys(MATCH_SPORT_LABELS), 'other']);
  uksort($sports, static fn(string $a, string $b): int => $order[$a] <=> $order[$b]);

  return [
    'sports' => $sports,
    'prefs'  => array_values(array_filter(MATCH_SEARCH_PREF_ORDER, static fn(string $p): bool => isset($prefs[$p]))),
  ];
}
