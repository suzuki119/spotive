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
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/match.php';
require_once __DIR__ . '/../../lib/hotel.php';

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
$type = (string) ($_GET['type'] ?? 'match');
$rawId = trim((string) ($_GET['id'] ?? ''));

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

$pageTitle = $game === null ? '試合が見つかりません' : $game['title'];

?>
<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= h($pageTitle) ?> | SPOTIVE</title>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Jost:wght@400;600&family=Noto+Sans+JP:wght@400;500;700&display=swap"
    rel="stylesheet" />
  <link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/the-new-css-reset/css/reset.min.css">
  <link rel="stylesheet" href="<?= h(asset('css/style.css')) ?>" />
</head>

<body>
  <main class="l-main">
    <div class="match-detail">
      <a class="match-detail__back" href="../map/map.php">← 地図に戻る</a>

      <?php if ($game === null) : ?>
        <section class="match-detail__card">
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
        </section>
      <?php else : ?>
        <article class="match-detail__card">
          <div class="match-detail__tags">
            <span class="match-detail__sport match-detail__sport--<?= h($sportKey) ?>">
              <span aria-hidden="true"><?= h($sportInfo['icon']) ?></span>
              <?= h($sportInfo['label']) ?>
            </span>
            <?php if ($game['is_verified']) : ?>
              <span class="match-detail__badge">確認済みの大会</span>
            <?php endif; ?>
          </div>

          <h1 class="match-detail__title"><?= h($game['title']) ?></h1>

          <?php if ($game['home'] !== null && $game['away'] !== null) : ?>
            <div class="match-detail__teams">
              <p class="match-detail__team">
                <span class="match-detail__side">HOME</span>
                <?= h($game['home']) ?>
              </p>
              <span class="match-detail__vs">VS</span>
              <p class="match-detail__team">
                <span class="match-detail__side">AWAY</span>
                <?= h($game['away']) ?>
              </p>
            </div>
          <?php endif; ?>

          <?php if ($isOver) : ?>
            <p class="match-detail__over">この試合は終了しました。</p>
          <?php endif; ?>

          <dl class="match-detail__info">
            <div class="match-detail__row">
              <dt class="match-detail__label">日時</dt>
              <dd class="match-detail__value match-detail__value--number">
                <?php if ($startsAt !== null) : ?>
                  <time datetime="<?= h($startsAt->format($game['time_tbd'] ? 'Y-m-d' : 'Y-m-d\TH:i')) ?>"><?= h($dateText) ?></time>
                <?php else : ?>
                  未定
                <?php endif; ?>
              </dd>
            </div>

            <div class="match-detail__row">
              <dt class="match-detail__label">会場</dt>
              <dd class="match-detail__value">
                <?= h($game['venue']) ?>
                <span class="match-detail__sub">
                  <?= h($game['address'] !== '' ? $game['address'] : $game['prefecture']) ?>
                  <?php if ($game['is_indoor'] !== null) : ?>
                    ・<?= $game['is_indoor'] ? '屋内' : '屋外' ?>
                  <?php endif; ?>
                </span>
              </dd>
            </div>

            <div class="match-detail__row">
              <dt class="match-detail__label"><?= h($game['price_label']) ?></dt>
              <dd class="match-detail__value match-detail__value--number">
                <?= h(detail_price($game['price_min'], $game['price_max'])) ?>
                <?php if ($game['kind'] === 'match' && $game['price_min'] !== null) : ?>
                  <span class="match-detail__sub">席の種類や購入時期によって変わります。</span>
                <?php endif; ?>
              </dd>
            </div>

            <?php if ($game['organizer'] !== null) : ?>
              <div class="match-detail__row">
                <dt class="match-detail__label">主催</dt>
                <dd class="match-detail__value"><?= h($game['organizer']) ?></dd>
              </div>
            <?php endif; ?>
          </dl>
        </article>

        <?php if ($game['kind'] === 'match') : ?>
          <section class="match-detail__section">
            <h2 class="match-detail__heading">チケット</h2>
            <?php if ($ticketUrl !== '' && !$isOver) : ?>
              <a class="match-detail__cta" href="<?= h($ticketUrl) ?>" target="_blank" rel="noopener">
                公式サイトでチケットを購入
              </a>
            <?php else : ?>
              <p class="match-detail__text">チケットの販売情報は準備中です。</p>
            <?php endif; ?>
          </section>
        <?php endif; ?>

        <section class="match-detail__section">
          <h2 class="match-detail__heading">会場へのアクセス</h2>
          <?php if ($routeUrl !== '') : ?>
            <div
              class="match-detail__map"
              id="detail-map"
              data-lat="<?= h((string) $game['lat']) ?>"
              data-lng="<?= h((string) $game['lng']) ?>"
              data-name="<?= h($game['venue']) ?>"></div>
            <a class="match-detail__route" href="<?= h($routeUrl) ?>" target="_blank" rel="noopener">
              ルートを調べる（Google マップ）
            </a>
          <?php else : ?>
            <p class="match-detail__text">会場の位置情報がまだ登録されていません。</p>
          <?php endif; ?>
        </section>

        <?php if ($nearHotels !== []) : ?>
          <section class="match-detail__section">
            <h2 class="match-detail__heading">周辺のホテル</h2>
            <p class="match-detail__text">画面確認用の仮データです（実在しません）。</p>
            <ul class="near-hotels">
              <?php foreach ($nearHotels as $nearHotel) : ?>
                <li>
                  <a class="near-hotels__item" href="<?= h(url(hotel_detail_path((string) $nearHotel['id'], $fromMatchId))) ?>">
                    <span class="near-hotels__name"><?= h((string) $nearHotel['name']) ?></span>
                    <span class="near-hotels__meta">
                      会場から約<?= h(hotel_distance_label((float) $nearHotel['distance'])) ?>
                      ・ ¥<?= h(number_format((int) $nearHotel['priceMin'])) ?>〜
                    </span>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          </section>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </main>

  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="<?= h(asset('js/main.js')) ?>"></script>
  <script src="<?= h(asset('js/pages/match-detail.js')) ?>"></script>
  <?php require __DIR__ . '/../menu-bar.php'; ?>
</body>

</html>
