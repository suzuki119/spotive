<?php

/**
 * pages/hotel/hotel-detail.php
 * ホテル詳細画面（企画書の機能 6「周辺施設表示」のうちホテル）。試合詳細の「周辺のホテル」から来る。
 *
 *   GET id    … ホテル（data/hotels.json の id。hotel-001 の形）
 *   GET match … どの試合から来たか（data/matches.json の id）。「試合情報に戻る」と会場までの距離に使う
 *
 * いまは画面確認用の仮データ（lib/hotel.php）。名前・住所・料金などはすべて架空。
 * 「予約サイトへ」はまだどこにもつながない（楽天トラベル API の利用規約を確認中）。
 * 「遠征プランに追加」は、来た試合で遠征サポート（pages/travel/travel.php）を開き、このホテルを選んだ状態にする。
 * 試合から来ていないときは、どの遠征か決められないので押せない表示にしている。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/hotel.php';

// -------------------------------------------------------------------
// ホテルと、どの試合（会場）から来たかの取得
// -------------------------------------------------------------------
$hotelId = input_string($_GET, 'id');
$matchId = input_string($_GET, 'match');
$hotel   = find_hotel($hotelId);

if ($hotel === null) {
  http_response_code(404);
}

// 会場：来た試合の会場。無ければホテルの近くの会場（仮データの venue）
$venue = null;
foreach (load_data_json('matches.json') as $m) {
  $isFrom  = $matchId !== '' && ($m['id'] ?? '') === $matchId;
  $isNear  = $hotel !== null && $matchId === '' && ($m['venue'] ?? '') === ($hotel['venue'] ?? '');
  if (($isFrom || $isNear) && isset($m['lat'], $m['lng'])) {
    $venue = ['name' => (string) $m['venue'], 'lat' => (float) $m['lat'], 'lng' => (float) $m['lng']];
    break;
  }
}
// 来た試合が見つからなければ、戻り先は地図にする
$backPath  = $matchId !== '' && $venue !== null ? match_detail_path('match', $matchId) : 'pages/map/map.php';
$backLabel = $matchId !== '' && $venue !== null ? '試合情報に戻る' : '地図に戻る';

// -------------------------------------------------------------------
// 表示用に整える
// -------------------------------------------------------------------
$km = $routeUrl = null;
if ($hotel !== null && $venue !== null) {
  $km = hotel_distance_km((float) $hotel['lat'], (float) $hotel['lng'], $venue['lat'], $venue['lng']);
  // 経路は Google マップに任せる（キー不要のリンク）。
  // 楽天の API に切り替えたら、楽天のデータを出す部分に楽天以外のリンクは置けない（規約 第8条4）ので見直すこと
  $routeUrl = 'https://www.google.com/maps/dir/?' . http_build_query([
    'api'         => 1,
    'origin'      => $hotel['lat'] . ',' . $hotel['lng'],
    'destination' => $venue['lat'] . ',' . $venue['lng'],
    'travelmode'  => 'transit',
  ]);
}

$amenities = [];
foreach ((array) ($hotel['amenities'] ?? []) as $key) {
  if (isset(HOTEL_AMENITIES[$key])) {
    $amenities[] = HOTEL_AMENITIES[$key];
  }
}

$pageTitle = $hotel === null ? 'ホテルが見つかりません' : (string) $hotel['name'];

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
      rel="stylesheet"
    />
    <link rel="stylesheet" href="<?= h(asset('css/style.css')) ?>" />
  </head>

  <body>
    <main class="hotel-detail">
      <a class="hotel-detail__back" href="<?= h(url($backPath)) ?>">← <?= h($backLabel) ?></a>

      <?php if ($hotel === null) : ?>
        <p class="hotel-detail__empty">ホテルが見つかりませんでした。</p>
      <?php else : ?>
        <p class="hotel-detail__mock">画面確認用の仮データです。名前・住所・料金などは実在しません。</p>

        <!-- 写真。仮データには写真が無いので、代わりの絵を出す -->
        <?php if ($hotel['image'] !== '') : ?>
          <img class="hotel-detail__photo" src="<?= h(url((string) $hotel['image'])) ?>" alt="<?= h((string) $hotel['name']) ?>" width="740" height="400" />
        <?php else : ?>
          <div class="hotel-detail__photo hotel-detail__photo--none" role="img" aria-label="写真はまだありません">🏨</div>
        <?php endif; ?>

        <h1 class="hotel-detail__name"><?= h((string) $hotel['name']) ?></h1>
        <p class="hotel-detail__address">
          <img src="<?= h(url('images/icons/point.svg')) ?>" alt="" width="11" height="14" />
          <?= h((string) $hotel['address']) ?>
        </p>

        <section class="hotel-detail__info" aria-label="設備とチェックイン">
          <?php if ($amenities !== []) : ?>
            <ul class="hotel-amenities">
              <?php foreach ($amenities as $amenity) : ?>
                <li class="hotel-amenities__item">
                  <img class="hotel-amenities__icon" src="<?= h(url('images/icons/' . $amenity['icon'])) ?>" alt="" width="24" height="24" />
                  <span class="hotel-amenities__label"><?= h($amenity['label']) ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>

          <dl class="hotel-detail__times">
            <div class="hotel-detail__time">
              <dt><img src="<?= h(url('images/icons/clock.svg')) ?>" alt="" width="16" height="16" />チェックイン</dt>
              <dd><?= h((string) $hotel['checkIn']) ?>〜</dd>
            </div>
            <div class="hotel-detail__time">
              <dt><img src="<?= h(url('images/icons/clock.svg')) ?>" alt="" width="16" height="16" />チェックアウト</dt>
              <dd>〜<?= h((string) $hotel['checkOut']) ?></dd>
            </div>
          </dl>
        </section>

        <?php if ($venue !== null) : ?>
          <section class="hotel-card">
            <h2 class="hotel-card__title">会場へのアクセス情報</h2>
            <p class="hotel-card__sub"><?= h($venue['name']) ?>まで</p>
            <ul class="hotel-access">
              <li class="hotel-access__item">
                <img src="<?= h(url('images/icons/walk.svg')) ?>" alt="" width="28" height="28" />
                <span class="hotel-access__label">徒歩</span>
                <span class="hotel-access__value">約<?= hotel_walk_minutes($km) ?>分</span>
              </li>
              <li class="hotel-access__item">
                <img src="<?= h(url('images/icons/train.svg')) ?>" alt="" width="28" height="28" />
                <span class="hotel-access__label">電車</span>
                <span class="hotel-access__value">
                  <?php // 仮データで電車の時間が無いのは、会場が歩ける距離のホテル ?>
                  <?= $hotel['trainMinutes'] === null ? '徒歩圏' : (int) $hotel['trainMinutes'] . '分' ?>
                </span>
              </li>
              <li class="hotel-access__item">
                <img src="<?= h(url('images/icons/point.svg')) ?>" alt="" width="22" height="28" />
                <span class="hotel-access__label">会場まで</span>
                <span class="hotel-access__value">約<?= h(hotel_distance_label($km)) ?></span>
              </li>
            </ul>
            <p class="hotel-card__note">徒歩と距離は直線距離からの目安です。</p>
            <a class="hotel-card__button" href="<?= h($routeUrl) ?>" target="_blank" rel="noopener">
              <img src="<?= h(url('images/icons/route.svg')) ?>" alt="" width="20" height="20" />
              アクセスルートを検索
            </a>
          </section>
        <?php endif; ?>

        <h2 class="hotel-detail__heading">予約</h2>
        <section class="hotel-card hotel-card--reserve" aria-label="予約">
          <div class="hotel-card__row">
            <p class="hotel-card__price">
              <span class="hotel-card__price-label">1泊あたり（目安）</span>
              ¥<?= h(number_format((int) $hotel['priceMin'])) ?>〜
            </p>
            <?php if (preg_match('#\Ahttps?://#i', (string) $hotel['reserveUrl']) === 1) : ?>
              <a class="hotel-card__reserve" href="<?= h((string) $hotel['reserveUrl']) ?>" target="_blank" rel="noopener">予約サイトへ</a>
            <?php else : ?>
              <span class="hotel-card__reserve is-disabled" aria-disabled="true">予約サイト（準備中）</span>
            <?php endif; ?>
          </div>
          <?php if ($matchId !== '' && $venue !== null) : ?>
            <a class="hotel-card__plan" href="<?= h(url('pages/travel/travel.php?' . http_build_query(['game' => $matchId, 'hotel' => $hotelId]))) ?>">
              <img src="<?= h(url('images/icons/add.svg')) ?>" alt="" width="20" height="20" />
              遠征プランに追加
            </a>
          <?php else : ?>
            <!-- 試合から来ていないと、どの遠征か決められない -->
            <span class="hotel-card__plan is-disabled" aria-disabled="true">
              <img src="<?= h(url('images/icons/add.svg')) ?>" alt="" width="20" height="20" />
              遠征プランに追加（試合から開いたときに使えます）
            </span>
          <?php endif; ?>
        </section>
      <?php endif; ?>
    </main>

    <script src="<?= h(asset('js/main.js')) ?>"></script>
    <?php require __DIR__ . '/../menu-bar.php'; ?>
  </body>
</html>
