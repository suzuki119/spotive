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
require_once __DIR__ . '/../../lib/match.php';

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

// 一覧のカードとピンの詳細に出すチームのロゴ。
// チーム ID => [ホーム側で出すとき, アウェイ側で出すとき, 相手と同じロゴになったときの代わり]
// （まだロゴが無いチームは仮のロゴ。仮のロゴは両チームで同じになることがあるので、代わりも渡す）
$mapTeamLogos = [];
foreach (array_keys(load_teams()) as $teamId) {
  $away = match_team_logo($teamId, 1);
  $mapTeamLogos[$teamId] = array_map(
    static fn(string $logo): string => $logo === '' ? '' : url($logo),
    [match_team_logo($teamId, 0), $away, match_next_placeholder($away)]
  );
}
$mapTeamLogosJson = json_encode(
  $mapTeamLogos,
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

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/the-new-css-reset/css/reset.min.css">
  <link rel="stylesheet" href="<?= h(asset('css/style.css')) ?>" />
</head>

<body>

  <main class="l-main">
    <div class="sports-map">
      <!--
        スマホ：普段は、メニューバーの上に「試合一覧を開く」の帯だけが見える。押すと一覧が開く。
                 絞り込みボタンを押すと、全画面になって絞り込み → 一覧の順に出る
        PC    ：左の固定サイドバー
      -->
      <aside class="sports-map__side" id="filter-panel">
        <!-- スマホ：普段は、メニューバーの上にこの帯だけが見える。押すと一覧が開く -->
        <button class="sheet-handle" type="button" id="list-handle" aria-controls="filter-panel" aria-expanded="false">
          <span class="sheet-handle__bar" aria-hidden="true"></span>
          <span class="sheet-handle__row">
            <span class="sheet-handle__label">▲ 試合一覧を開く</span>
            <span class="sheet-handle__count" id="handle-count"></span>
          </span>
        </button>
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

        <!-- 地図の上：キーワード検索と絞り込み。その下に現在地・結果全体の小さなボタン -->
        <div class="map-tools">
          <label class="map-search">
            <span class="map-search__label">キーワード検索</span>
            <!-- 絞り込みのフォーム（#filters）の一部として扱う。リセットで一緒に消える -->
            <input
              class="map-search__input"
              type="search"
              name="keyword"
              id="keyword"
              form="filters"
              placeholder="キーワード検索"
              maxlength="50"
              autocomplete="off" />
            <img class="map-search__icon" src="<?= h(url('images/icons/search.svg')) ?>" alt="" width="20" height="20" />
          </label>
          <button
            class="map-tools__round map-tools__filter"
            id="filter-toggle"
            type="button"
            aria-controls="filter-panel"
            aria-expanded="false"
            aria-label="絞り込み">
            <span class="map-tools__lines" aria-hidden="true"></span>
          </button>
          <div class="map-tools__sub">
            <button class="map-tools__round" id="locate" type="button" aria-label="現在地"><span aria-hidden="true">📍</span></button>
            <button class="map-tools__round" id="fit" type="button" aria-label="結果全体を表示"><span aria-hidden="true">⤢</span></button>
          </div>
        </div>

        <!-- ピンを押したときに、絞り込みと入れ替わりで下から出る詳細 -->
        <section class="match-sheet" id="match-sheet" aria-label="試合の詳細" aria-hidden="true">
          <div class="sheet__head">
            <p class="sheet__title">試合の詳細</p>
            <button class="sheet__close" type="button" id="match-sheet-close">閉じる</button>
          </div>
          <div class="match-sheet__body" id="match-sheet-body"></div>
        </section>

        <!-- 試合を押したときに下から出る詳細ページ（match-detail.php を中に表示する） -->
        <section class="detail-sheet" id="detail-sheet" aria-label="試合の詳細ページ" aria-hidden="true">
          <iframe class="detail-sheet__frame" id="detail-sheet-frame" title="試合の詳細ページ"></iframe>
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

  <!-- チームのロゴ。js/pages/map.js が一覧のカードの背景に使う -->
  <script type="application/json" id="map-team-logos">
    <?= $mapTeamLogosJson ?>
  </script>

  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
  <script src="<?= h(asset('js/main.js')) ?>"></script>
  <script src="<?= h(asset('js/pages/map.js')) ?>"></script>
  <?php require __DIR__ . '/../menu-bar.php'; ?>
</body>

</html>
