<?php

/**
 * pages/match/match-detail.php
 * 試合詳細画面。日時・会場・料金・対戦カード・チケット・会場へのルート。
 *
 * 表示するものは type= で 2 種類に分かれる（地図の js/pages/map.js がリンクを作る）。
 *   type=match       … data/matches.json の仮データ（id は match-001 の形）
 *   type=tournament  … 主催者が掲載した大会。v_public_tournaments から取る（id は数字）
 *
 * 周辺施設・天気は主要機能 5・6（後回し）なので、まだ出さない。
 * ホテルだけは、画面確認用の仮データ（lib/hotel.php）で「周辺のホテル」を出している。
 *
 * デザインにあってデータに無いもの（リーグ名・節・戦績・最寄り駅・試合の写真）は、
 * 競技名や所在地、競技の色の絵で代わりにしている。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/match.php';
require_once __DIR__ . '/../../lib/hotel.php';
require_once __DIR__ . '/../../lib/favorite.php';

/** 試合の写真が無いときに上に出す写真 */
const DETAIL_DEFAULT_IMAGE = 'images/sample/sample-match.png';

/** 競技コード => アイコン。js/pages/map.js の SPORTS と揃える（表示名は lib/match.php） */
const DETAIL_SPORT_ICONS = [
  'soccer'      => '⚽',
  'baseball'    => '⚾',
  'basketball'  => '🏀',
  'volleyball'  => '🏐',
  'futsal'      => '⚽',
  'tennis'      => '🎾',
  'badminton'   => '🏸',
  'rugby'       => '🏉',
  'tabletennis' => '🏓',
];

/** 仮データの試合 1 件を、画面で使う形にする。見つからなければ null */
function detail_from_match(string $id): ?array
{
  $match = null;
  foreach (load_data_json('matches.json') as $m) {
    if (($m['id'] ?? '') === $id) {
      $match = $m;
      break;
    }
  }
  if ($match === null) {
    return null;
  }

  $teams = array_column(load_data_json('teams.json'), 'name', 'id');
  $areas = array_column(load_data_json('areas.json'), 'name', 'id');
  $time  = (string) ($match['startTime'] ?? '');

  return [
    'kind'        => 'match',
    'sport'       => (string) ($match['sport'] ?? ''),
    'title'       => (string) ($match['title'] ?? ''),
    'home'        => $teams[$match['homeTeamId'] ?? ''] ?? null,
    'away'        => $teams[$match['awayTeamId'] ?? ''] ?? null,
    'team_ids'    => array_values(array_filter([(string) ($match['homeTeamId'] ?? ''), (string) ($match['awayTeamId'] ?? '')])),
    'starts_at'   => (string) ($match['date'] ?? '') . ' ' . ($time === '' ? '00:00' : $time),
    'time_tbd'    => $time === '',
    'venue'       => (string) ($match['venue'] ?? ''),
    'address'     => (string) ($match['address'] ?? ''),
    'prefecture'  => (string) ($areas[$match['areaId'] ?? ''] ?? ''),
    'lat'         => isset($match['lat']) ? (float) $match['lat'] : null,
    'lng'         => isset($match['lng']) ? (float) $match['lng'] : null,
    'is_indoor'   => isset($match['isIndoor']) ? (bool) $match['isIndoor'] : null,
    'price_label' => 'チケット価格（目安）',
    'price_min'   => isset($match['priceMin']) ? (int) $match['priceMin'] : null,
    'price_max'   => isset($match['priceMax']) ? (int) $match['priceMax'] : null,
    'ticket_url'  => (string) ($match['ticketUrl'] ?? ''),
    'image'       => (string) ($match['image'] ?? ''),
    // 「B.PREMIER 2026-27 第1節 GAME2」の各部分（仮データ）
    'league'      => (string) ($match['league'] ?? ''),
    'season'      => (string) ($match['season'] ?? ''),
    'round'       => (string) ($match['round'] ?? ''),
    'game'        => (string) ($match['game'] ?? ''),
    'organizer'   => null,
    'is_verified' => false,
  ];
}

/** 主催者が掲載した大会 1 件。公開中のものだけ（ビューが絞る）。見つからなければ null */
function detail_from_tournament(int $id): ?array
{
  $stmt = db()->prepare(
    'SELECT id, title, sport, starts_at, venue_name, venue_prefecture,
            venue_lat, venue_lng, is_indoor, entry_fee_yen, is_verified, organizer_name
       FROM v_public_tournaments
      WHERE id = :id'
  );
  $stmt->execute([':id' => $id]);
  $t = $stmt->fetch();
  if ($t === false) {
    return null;
  }

  return [
    'kind'        => 'tournament',
    'sport'       => (string) $t['sport'],
    'title'       => (string) $t['title'],
    'home'        => null,
    'away'        => null,
    'team_ids'    => [],
    'starts_at'   => (string) $t['starts_at'],
    'time_tbd'    => false,
    'venue'       => (string) $t['venue_name'],
    'address'     => '',
    'prefecture'  => (string) $t['venue_prefecture'],
    'lat'         => $t['venue_lat'] === null ? null : (float) $t['venue_lat'],
    'lng'         => $t['venue_lng'] === null ? null : (float) $t['venue_lng'],
    'is_indoor'   => (bool) $t['is_indoor'],
    'price_label' => '参加費',
    'price_min'   => (int) $t['entry_fee_yen'],
    'price_max'   => null,
    'ticket_url'  => '',
    'image'       => '',
    'league'      => '',
    'season'      => '',
    'round'       => '',
    'game'        => '',
    'organizer'   => (string) $t['organizer_name'],
    'is_verified' => (bool) $t['is_verified'],
  ];
}

/** ¥2,500〜¥8,000 の形。価格が無ければ「未定」 */
function detail_price(?int $min, ?int $max): string
{
  if ($min === null) {
    return '未定';
  }
  if ($min === 0 && $max === null) {
    return '無料';
  }
  $text = '¥' . number_format($min);
  if ($max !== null && $max > $min) {
    $text .= '〜¥' . number_format($max);
  }
  return $text;
}

// -------------------------------------------------------------------
// 受け取った値
// -------------------------------------------------------------------
$type = input_string($_GET, 'type', 'match');
$rawId = input_string($_GET, 'id');

$game    = null;
$dbError = false;

try {
  if ($type === 'tournament') {
    $tournamentId = filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($tournamentId !== false) {
      $game = detail_from_tournament($tournamentId);
    }
  } elseif (preg_match('/\Amatch-\d+\z/', $rawId) === 1) {
    $game = detail_from_match($rawId);
  }
} catch (PDOException $e) {
  error_log('[SPOTIVE] DB error: ' . $e->getMessage());
  $dbError = true;
}

if ($game === null) {
  http_response_code($dbError ? 500 : 404);
}

// -------------------------------------------------------------------
// 表示用に整える
// -------------------------------------------------------------------
$tz        = new DateTimeZone((string) config('app.timezone', 'Asia/Tokyo'));
$sportKey  = '';
$sportInfo = null;
$startsAt  = null;
$dateText  = '';
$isOver    = false;
$ticketUrl = '';
$routeUrl  = '';

if ($game !== null) {
  $sportKey  = match_sport_key($game['sport']);
  $sportInfo = [
    'label' => $game['sport'] !== '' ? match_sport_label($game['sport']) : 'その他',
    'icon'  => DETAIL_SPORT_ICONS[$sportKey] ?? '🏅',
  ];

  try {
    $startsAt = new DateTimeImmutable($game['starts_at'], $tz);
  } catch (Exception $e) {
    $startsAt = null;
  }

  if ($startsAt !== null) {
    $youbi    = ['日', '月', '火', '水', '木', '金', '土'][(int) $startsAt->format('w')];
    $dateText = $startsAt->format('Y年n月j日') . '(' . $youbi . ') '
      . ($game['time_tbd'] ? '時刻未定' : $startsAt->format('H:i') . ' 開始');
    // 開始日の翌日になったら「終了」とみなす（終了時刻は仮データに無いため）
    $isOver = $startsAt->setTime(0, 0) < new DateTimeImmutable('today', $tz);
  }

  // チケットの URL は http / https だけ通す（javascript: などをリンクにしない）
  if (preg_match('#\Ahttps?://#i', $game['ticket_url']) === 1) {
    $ticketUrl = $game['ticket_url'];
  }

  if ($game['lat'] !== null && $game['lng'] !== null) {
    $routeUrl = 'https://www.google.com/maps/dir/?api=1&destination='
      . rawurlencode($game['lat'] . ',' . $game['lng']);
  }
}

// 会場の近くのホテル（仮データ）。仮データの試合から来たときだけ、戻り先として試合を渡す
$nearHotels = [];
if ($game !== null && $game['lat'] !== null && $game['lng'] !== null) {
  $nearHotels = hotels_near((float) $game['lat'], (float) $game['lng']);
}
$fromMatchId = $type === 'match' ? $rawId : '';

// デザインの「10/14 ｜ 13:00〜」と、対戦チームのロゴ
$shortDate = $startsAt === null ? '日程未定' : $startsAt->format('n/j');
$timeText  = $startsAt === null || $game['time_tbd'] ? '時刻未定' : $startsAt->format('H:i') . '〜';
$teamList  = $game === null ? [] : match_teams($game['team_ids']);

// 対戦チームの ☆（お気に入り）。ログイン中なら DB、未ログインならブラウザに保存する
$favoriteState = favorite_client_state();

// 上の写真。試合の写真（matches.json の image）が置かれていればそれ、無ければサンプルの写真
$heroImage = '';
foreach ([$game['image'] ?? '', DETAIL_DEFAULT_IMAGE] as $candidate) {
  if ($candidate !== '' && is_file(dirname(__DIR__, 2) . '/' . $candidate)) {
    $heroImage = $candidate;
    break;
  }
}

$pageTitle = $game === null ? '試合が見つかりません' : $game['title'];

// 地図の詳細シート（pages/map/map.php）の中に出すとき。リンクはシートの中ではなく画面全体で開く
$embed = ($_GET['embed'] ?? '') === '1';

?>
<!DOCTYPE html>
<html lang="ja">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= h($pageTitle) ?> | SPOTIVE</title>
    <?php if ($embed) : ?>
      <base target="_top" />
    <?php endif; ?>

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Jost:wght@400;600&family=Noto+Sans+JP:wght@400;500;700&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/the-new-css-reset/css/reset.min.css" />
    <link rel="stylesheet" href="<?= h(asset('css/style.css')) ?>" />
  </head>

  <body>
    <main class="match-detail match-detail--<?= h($sportKey !== '' ? $sportKey : 'other') ?>">
      <!-- 上の写真。飾りなので読み上げない。写真が 1 枚も無いときは、競技の色とアイコンで代わりにする -->
      <div class="match-detail__hero" aria-hidden="true">
        <?php if ($heroImage !== '') : ?>
          <img class="match-detail__hero-image" src="<?= h(url($heroImage)) ?>" alt="" width="402" height="205" />
        <?php else : ?>
          <span class="match-detail__hero-icon"><?= h($sportInfo['icon'] ?? '🏟️') ?></span>
        <?php endif; ?>
      </div>

      <div class="match-detail__sheet">
        <div class="match-detail__head">
          <p class="match-detail__league">
            <?php if ($game === null) : ?>
              SPOTIVE
            <?php else : ?>
              <?php if ($game['league'] !== '') : ?>
                <?= h(match_league_label($game, true)) ?><?= $game['season'] !== '' ? ' Season' : '' ?>
              <?php else : ?>
                <?= h($game['organizer'] !== null ? '主催：' . $game['organizer'] : $sportInfo['label']) ?>
              <?php endif; ?>
            <?php endif; ?>
          </p>
          <!-- 閉じる：来た画面に戻る（js/pages/match-detail.js）。JS が無ければ地図へ -->
          <a class="match-detail__close" href="<?= h(url('pages/map/map.php')) ?>" data-back aria-label="閉じる">
            <img src="<?= h(url('images/icons/close.svg')) ?>" alt="" width="24" height="24" />
          </a>
        </div>

        <?php if ($game === null) : ?>
          <h1 class="match-detail__title">
            <?= $dbError ? '読み込めませんでした' : '試合が見つかりませんでした' ?>
          </h1>
          <p class="match-detail__text">
            <?php if ($dbError) : ?>
              ただいま試合の情報を読み込めません。時間をおいてお試しください。
            <?php else : ?>
              掲載が終わったか、URL が間違っている可能性があります。地図から試合を探してください。
            <?php endif; ?>
          </p>
        <?php else : ?>
          <!-- 対戦チーム。チームの無い大会は大会名を出す -->
          <?php if ($teamList !== []) : ?>
            <h1 class="match-detail__teams">
              <?php foreach ($teamList as $team) : ?>
                <span class="match-detail__team">
                  <?php if ($team['logo'] !== '') : ?>
                    <img class="match-detail__logo" src="<?= h(url($team['logo'])) ?>" alt="" width="40" height="40" />
                  <?php endif; ?>
                  <span class="match-detail__team-name"><?= h($team['name']) ?></span>
                </span>
              <?php endforeach; ?>
            </h1>

            <!-- 対戦チームをお気に入りに登録・解除する（js/pages/match-detail.js）。見出しの外に置く -->
            <div class="match-detail__favorites">
              <?php foreach ($teamList as $i => $team) : ?>
                <button
                  class="match-detail__favorite"
                  type="button"
                  data-favorite-team="<?= h((string) ($game['team_ids'][$i] ?? '')) ?>"
                  aria-pressed="false"
                >
                  <span class="match-detail__favorite-icon" aria-hidden="true">☆</span>
                  <span class="match-detail__favorite-name"><?= h($team['name']) ?></span>
                </button>
              <?php endforeach; ?>
            </div>
          <?php else : ?>
            <h1 class="match-detail__title"><?= h($game['title']) ?></h1>
          <?php endif; ?>

          <?php if ($game['is_verified']) : ?>
            <p class="match-detail__badge">確認済みの大会</p>
          <?php endif; ?>

          <?php if (match_round_label($game) !== '') : ?>
            <!-- 第1節 GAME2（見出しはリーグとシーズン） -->
            <p class="match-detail__round"><?= h(match_round_label($game)) ?></p>
          <?php elseif ($game['organizer'] !== null) : ?>
            <!-- 大会は見出しが主催者名なので、競技名はここに出す -->
            <p class="match-detail__round"><?= h($sportInfo['label']) ?></p>
          <?php endif; ?>

          <p class="match-detail__when">
            <?php if ($startsAt !== null) : ?>
              <time datetime="<?= h($startsAt->format($game['time_tbd'] ? 'Y-m-d' : 'Y-m-d\TH:i')) ?>">
                <span class="match-detail__date"><?= h($shortDate) ?></span><span class="match-detail__time"><?= h($timeText) ?></span>
              </time>
            <?php else : ?>
              <span class="match-detail__date"><?= h($shortDate) ?></span>
            <?php endif; ?>
            <span class="match-detail__venue">
              <img src="<?= h(url('images/icons/point.svg')) ?>" alt="" width="11" height="14" />
              <?= h($game['venue']) ?>
            </span>
          </p>

          <?php if ($isOver) : ?>
            <p class="match-detail__over">この試合は終了しました。</p>
          <?php endif; ?>

          <!-- チケット情報 -->
          <section class="match-detail__section">
            <h2 class="match-detail__heading"><?= $game['kind'] === 'match' ? 'チケット情報' : '参加費' ?></h2>
            <div class="match-detail__ticket">
              <p class="match-detail__price">
                <?= h(detail_price($game['price_min'], null)) ?><?= $game['price_min'] !== null && $game['price_min'] > 0 ? '〜' : '' ?>
              </p>
              <?php if ($game['kind'] === 'match') : ?>
                <?php if ($ticketUrl !== '' && !$isOver) : ?>
                  <a class="match-detail__cta" href="<?= h($ticketUrl) ?>" target="_blank" rel="noopener">チケット販売サイトへ</a>
                <?php else : ?>
                  <span class="match-detail__cta is-disabled" aria-disabled="true">販売情報は準備中</span>
                <?php endif; ?>
              <?php endif; ?>
            </div>
            <?php if ($game['kind'] === 'match' && $game['price_min'] !== null) : ?>
              <p class="match-detail__note">
                <?= h($game['price_label']) ?>：<?= h(detail_price($game['price_min'], $game['price_max'])) ?>。席の種類や購入時期によって変わります。
              </p>
            <?php endif; ?>
          </section>

          <!-- アクセス情報 -->
          <section class="match-detail__section">
            <h2 class="match-detail__heading">アクセス情報</h2>
            <dl class="match-detail__access">
              <div class="match-detail__access-item">
                <dt><img src="<?= h(url('images/icons/point.svg')) ?>" alt="" width="11" height="14" />現在地から</dt>
                <dd
                  data-distance
                  <?php if ($game['lat'] !== null && $game['lng'] !== null) : ?>
                    data-lat="<?= (float) $game['lat'] ?>"
                    data-lng="<?= (float) $game['lng'] ?>"
                  <?php endif; ?>
                >—</dd>
              </div>
              <div class="match-detail__access-item">
                <dt><img src="<?= h(url('images/icons/point.svg')) ?>" alt="" width="11" height="14" />所在地</dt>
                <dd><?= h($game['address'] !== '' ? $game['address'] : $game['prefecture']) ?></dd>
              </div>
            </dl>
            <?php if ($routeUrl !== '') : ?>
              <a class="match-detail__route" href="<?= h($routeUrl) ?>" target="_blank" rel="noopener">ルートを調べる（Google マップ）</a>
            <?php else : ?>
              <p class="match-detail__note">会場の位置情報がまだ登録されていません。</p>
            <?php endif; ?>
          </section>

          <!-- 周辺のホテル（仮データ） -->
          <section class="match-detail__section">
            <h2 class="match-detail__heading">周辺のホテル</h2>
            <?php if ($nearHotels !== []) : ?>
              <p class="match-detail__note">画面確認用の仮データです（実在しません）。</p>
              <ul class="near-hotels">
                <?php foreach ($nearHotels as $nearHotel) : ?>
                  <li>
                    <a class="near-hotels__item" href="<?= h(url(hotel_detail_path((string) $nearHotel['id'], $fromMatchId))) ?>">
                      <span class="near-hotels__photo" aria-hidden="true">🏨</span>
                      <span class="near-hotels__body">
                        <span class="near-hotels__name"><?= h((string) $nearHotel['name']) ?></span>
                        <span class="near-hotels__meta">会場から約<?= h(hotel_distance_label((float) $nearHotel['distance'])) ?></span>
                        <span class="near-hotels__price">¥<?= h(number_format((int) $nearHotel['priceMin'])) ?>〜<small>/1泊</small></span>
                      </span>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else : ?>
              <p class="match-detail__note">会場の近くのホテルはまだ登録されていません。</p>
            <?php endif; ?>
            <a class="match-detail__hotel-search" href="<?= h(url('pages/map/map.php?nearby=hotel')) ?>">
              <span class="match-detail__hotel-icon" aria-hidden="true">🛏️</span>
              会場周辺のホテルを探す
            </a>
          </section>

          <!-- 会場の特徴 -->
          <?php if ($game['is_indoor'] !== null) : ?>
            <ul class="match-detail__features">
              <li class="match-detail__feature"><?= $game['is_indoor'] ? '屋内' : '屋外' ?></li>
            </ul>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </main>

    <script src="<?= h(asset('js/main.js')) ?>"></script>
    <script type="application/json" id="favorite-state"><?= favorite_state_json($favoriteState) ?></script>
    <script src="<?= h(asset('js/common/favorite-store.js')) ?>"></script>
    <script src="<?= h(asset('js/pages/match-detail.js')) ?>"></script>
    <?php if (!$embed) : ?>
      <?php require __DIR__ . '/../menu-bar.php'; ?>
    <?php endif; ?>
  </body>
</html>
