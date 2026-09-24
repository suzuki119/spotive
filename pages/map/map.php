<?php

/**
 * pages/map/map.php
 * 観戦マップ画面。Leaflet の地図から試合を探す
 *
 * 試合そのものは data/matches.json（仮データ）を JS が読む。
 * ここでは、主催者が掲載した大会を v_public_tournaments から取り、
 * 地図に合流させるために JSON で埋め込む。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/support.php';

// -------------------------------------------------------------------
// 地図に載せる大会の取得
// -------------------------------------------------------------------
$mapTournaments = [];

try {
  $stmt = db()->prepare(
    'SELECT id, title, sport, starts_at,
            venue_name, venue_prefecture, venue_lat, venue_lng, is_indoor,
            entry_fee_yen, organizer_name
     FROM v_public_tournaments
     WHERE starts_at >= NOW()
       AND venue_lat IS NOT NULL
       AND venue_lng IS NOT NULL
     ORDER BY starts_at ASC
     LIMIT 500'
  );
  $stmt->execute();

  // JS 側（js/pages/map.js の fromTournament）が読む形に整えておく
  foreach ($stmt->fetchAll() as $t) {
    $mapTournaments[] = [
      'id'        => (int) $t['id'],
      'title'     => (string) $t['title'],
      'sport'     => (string) $t['sport'],
      'startsAt'  => (string) $t['starts_at'],
      'venue'     => (string) $t['venue_name'],
      'pref'      => (string) $t['venue_prefecture'],
      'lat'       => (float) $t['venue_lat'],
      'lng'       => (float) $t['venue_lng'],
      'isIndoor'  => (int) $t['is_indoor'] === 1,
      'fee'       => $t['entry_fee_yen'] === null ? null : (int) $t['entry_fee_yen'],
      'organizer' => (string) $t['organizer_name'],
    ];
  }
} catch (PDOException $e) {
  // DB が未構築でも、仮データだけで地図は動くようにする
  error_log('[SPOTIVE] DB error: ' . $e->getMessage());
}

// <script> の中に置くので、タグやクォートは実体参照に逃がしておく
$mapTournamentsJson = json_encode(
  $mapTournaments,
  JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);

?>
<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>観戦マップ | SPOTIVE</title>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Jost:wght@400;600&family=Noto+Sans+JP:wght@400;500;700&display=swap"
    rel="stylesheet" />
  <link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <!-- 近くのピンをまとめる MarkerCluster。読めなくても地図は動く -->
  <link
    rel="stylesheet"
    href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
  <link
    rel="stylesheet"
    href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
  <link rel="stylesheet" href="<?= h(asset('css/style.css')) ?>" />
</head>

<body>
  <header class="site-header"></header>

  <main class="l-main">
    <div class="sports-map">
      <!-- スマホではこの中身がまるごと全画面で出る（絞り込み → 結果一覧の順）-->
      <aside class="sports-map__side" id="filter-panel">
        <a href="../../index.php" class="logo">SPOTIVE</a>
        <!-- 普段は隠れている。地図左上の「絞り込み」ボタンで開閉する -->
        <div class="sports-map__filters">
          <div class="sheet__head">
            <p class="sheet__title">絞り込み</p>
            <!-- スマホは一覧を出さないので、件数はここで知らせる -->
            <span class="sheet__count" id="filter-count"></span>
            <button class="sheet__close" type="button" id="filter-close">閉じる</button>
          </div>

          <form id="filters">
            <fieldset>
              <legend>競技</legend>
              <!-- 競技チップは js/pages/map.js が、実際にある試合から作る -->
              <div id="sport-chips" class="chips"></div>
            </fieldset>

            <fieldset>
              <legend>日付</legend>
              <div class="seg">
                <label><input type="radio" name="period" value="all" checked /><span>すべて</span></label>
                <label><input type="radio" name="period" value="today" /><span>今日</span></label>
                <label><input type="radio" name="period" value="tomorrow" /><span>明日</span></label>
                <label><input type="radio" name="period" value="week" /><span>今週</span></label>
                <label><input type="radio" name="period" value="month" /><span>今月</span></label>
              </div>
            </fieldset>

            <div class="grid2">
              <label>
                エリア
                <select name="area">
                  <option value="all">全国</option>
                  <option>北海道・東北</option>
                  <option>関東</option>
                  <option>中部</option>
                  <option>近畿</option>
                  <option>中国・四国</option>
                  <option>九州・沖縄</option>
                </select>
              </label>

              <label>
                開始時間
                <select name="time">
                  <option value="all">指定なし</option>
                  <option value="day">デーゲーム（〜17時）</option>
                  <option value="night">ナイター（17時〜）</option>
                </select>
              </label>

              <label>
                チケット価格（目安）
                <select name="price">
                  <option value="all">指定なし</option>
                  <option value="u2000">〜2,000円</option>
                  <option value="2to4">2,000〜4,000円</option>
                  <option value="4to6">4,000〜6,000円</option>
                  <option value="o6">6,000円以上</option>
                </select>
              </label>

              <label>
                屋内・屋外
                <select name="place">
                  <option value="all">指定なし</option>
                  <option value="indoor">屋内</option>
                  <option value="outdoor">屋外</option>
                </select>
              </label>
            </div>

            <button type="reset" class="link">条件をリセット</button>
          </form>
        </div>

        <div class="list-head">
          <strong id="result-count"></strong>
          <select id="sort" aria-label="並び順">
            <option value="date">日時順</option>
            <option value="distance">近い順</option>
          </select>
        </div>

        <ul id="list" class="list"></ul>
      </aside>

      <section class="sports-map__canvas">
        <div id="map"></div>

        <!-- スマホではこの 3 つが画面の左上に並ぶ -->
        <div class="map-tools">
          <button
            class="map-tools__filter"
            id="filter-toggle"
            type="button"
            aria-controls="filter-panel"
            aria-expanded="false"
          >
            <span aria-hidden="true">☰</span> 絞り込み
          </button>
          <button id="locate" type="button"><span aria-hidden="true">📍</span> 現在地</button>
          <button id="fit" type="button">結果全体</button>
        </div>

        <!-- ピンを押したときに、絞り込みと入れ替わりで下から出る詳細 -->
        <section class="match-sheet" id="match-sheet" aria-label="試合の詳細" aria-hidden="true">
          <div class="sheet__head">
            <p class="sheet__title">試合の詳細</p>
            <button class="sheet__close" type="button" id="match-sheet-close">閉じる</button>
          </div>
          <div class="match-sheet__body" id="match-sheet-body"></div>
        </section>

        <div id="status" class="status" hidden></div>
      </section>
    </div>
  </main>

  <footer class="site-footer"></footer>

  <!-- 掲載中の大会。js/pages/map.js が読んで仮データと合流させる -->
  <script type="application/json" id="map-tournaments">
    <?= $mapTournamentsJson ?>
  </script>

  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
  <script src="<?= h(asset('js/main.js')) ?>"></script>
  <script src="<?= h(asset('js/pages/map.js')) ?>"></script>
</body>

</html>
