<?php

/**
 * pages/match/match-search.php
 * 検索画面（企画書の機能 3「条件検索・フィルター」）。メニューバーの「検索」から来る。
 *
 *   タブ         … ALL / 野球 / サッカー / バスケットボール / バレーボール …（競技での絞り込み）
 *   キーワード   … チーム名・会場名など
 *   条件検索     … 日程（開始日〜終了日）、エリア（現在地から○km以内 or 都道府県）、価格の上限
 *   周辺検索     … 地図へ移り、会場を選ぶと周辺の施設を出す（企画書の機能 6 のデモ）
 * 結果は下に日付ごとに並べる。
 *
 * 対象は data/matches.json（仮データ）と v_public_tournaments（掲載大会）の両方。
 * 絞り込みの処理は lib/match-search.php にまとめてある。
 * 「現在地から○km以内」だけは、現在地をサーバーに送らないよう js/pages/match-search.js が絞り込む。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/match-search.php';
require_once __DIR__ . '/../../lib/favorite.php';

/** 価格のスライダーの目盛り（円）。安い価格帯を細かく選べるよう、等間隔にしない */
const SEARCH_PRICE_STEPS = [
  0, 500, 1000, 1500, 2000, 2500, 3000, 4000, 5000, 6000, 8000,
  10000, 15000, 20000, 30000, 50000, MATCH_SEARCH_PRICE_MAX,
];

// -------------------------------------------------------------------
// 検索条件と試合の取得
// -------------------------------------------------------------------
$cond     = match_search_conditions($_GET);
$upcoming = load_upcoming_matches();
$options  = match_search_options($upcoming);
$results  = match_search($upcoming, $cond);
$days     = group_matches_by_date($results);
$isActive = match_search_is_active($cond);

// お気に入りのチームの試合に印を付けるため（ログイン中なら DB、未ログインならブラウザ）
$favoriteState = favorite_client_state();

// 競技のタブ（試合一覧と共通）
$tabs = match_search_tabs($upcoming, $cond['sport']);

// 条件の行に出す、いま選んでいる内容
$fmtDate   = static fn(string $d, string $format): string => date($format, (int) strtotime($d));
$dateLabel = match (true) {
  $cond['from'] !== '' && $cond['to'] !== '' => $fmtDate($cond['from'], 'Y/m/d') . '〜' . $fmtDate($cond['to'], 'm/d'),
  $cond['from'] !== ''                       => $fmtDate($cond['from'], 'Y/m/d') . '〜',
  $cond['to'] !== ''                         => '〜' . $fmtDate($cond['to'], 'Y/m/d'),
  default                                    => '指定しない',
};
$areaLabel = match (true) {
  $cond['near'] !== null => '現在地から' . $cond['near'] . 'km以内',
  $cond['pref'] !== ''   => $cond['pref'],
  default                => '全国',
};

// スライダーの位置（目盛りの番号）。上限なしなら右端
$priceIndex = count(SEARCH_PRICE_STEPS) - 1;
if ($cond['maxPrice'] !== null) {
  foreach (SEARCH_PRICE_STEPS as $i => $yen) {
    if ($yen >= $cond['maxPrice']) {
      $priceIndex = $i;
      break;
    }
  }
}
$priceValue = SEARCH_PRICE_STEPS[$priceIndex];

?>
<!DOCTYPE html>
<html lang="ja">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>検索 | SPOTIVE</title>

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Jost:wght@400;600&family=Noto+Sans+JP:wght@400;500;700&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="<?= h(asset('css/style.css')) ?>" />
  </head>

  <body>
    <?php
    $appHeader = ['title' => 'SEARCH', 'noticeIcon' => 'mail.svg', 'noticeLabel' => 'Notice'];
    require __DIR__ . '/../app-header.php';
    ?>

    <main class="match-search">
      <form class="search-form" id="search-form" action="match-search.php#results" method="get">
        <!-- 競技のタブ。選ぶとすぐに検索し直す（JS が無いときは下のボタンで） -->
        <div class="search-tabs" role="radiogroup" aria-label="競技">
          <?php foreach ($tabs as $value => $label) : ?>
            <label class="search-tabs__tab">
              <input
                class="search-tabs__input"
                type="radio"
                name="sport"
                value="<?= h((string) $value) ?>"
                <?= $cond['sport'] === (string) $value ? 'checked' : '' ?>
              />
              <span class="search-tabs__label"><?= h($label) ?></span>
            </label>
          <?php endforeach; ?>
        </div>

        <input
          class="search-form__keyword"
          type="search"
          name="keyword"
          value="<?= h($cond['keyword']) ?>"
          placeholder="キーワード検索"
          maxlength="50"
          aria-label="キーワード検索（チーム名・会場名など）"
        />

        <h2 class="search-form__heading">条件検索</h2>

        <!-- 日程 -->
        <details class="search-cond" <?= $cond['from'] !== '' || $cond['to'] !== '' ? 'open' : '' ?>>
          <summary class="search-cond__head">
            <img class="search-cond__icon" src="<?= h(url('images/icons/date.svg')) ?>" alt="" width="16" height="16" />
            <span class="search-cond__name">日程</span>
            <span class="search-cond__value" data-date-label><?= h($dateLabel) ?></span>
          </summary>
          <div class="search-cond__body search-cond__body--dates">
            <input class="search-cond__input" type="date" name="from" value="<?= h($cond['from']) ?>"
              min="<?= h(date('Y-m-d')) ?>" aria-label="開始日" />
            <span aria-hidden="true">〜</span>
            <input class="search-cond__input" type="date" name="to" value="<?= h($cond['to']) ?>"
              min="<?= h(date('Y-m-d')) ?>" aria-label="終了日" />
          </div>
        </details>

        <!-- エリア -->
        <details class="search-cond" <?= $cond['near'] !== null || $cond['pref'] !== '' ? 'open' : '' ?>>
          <summary class="search-cond__head">
            <img class="search-cond__icon" src="<?= h(url('images/icons/point.svg')) ?>" alt="" width="16" height="16" />
            <span class="search-cond__name">エリア</span>
            <span class="search-cond__value" data-area-label><?= h($areaLabel) ?></span>
          </summary>
          <div class="search-cond__body">
            <label class="search-cond__field">
              <span class="search-cond__caption">現在地から</span>
              <select class="search-cond__input" name="near" data-near>
                <option value="">指定しない</option>
                <?php foreach (MATCH_SEARCH_NEAR_KM as $km) : ?>
                  <option value="<?= (int) $km ?>" <?= $cond['near'] === $km ? 'selected' : '' ?>><?= (int) $km ?>km以内</option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="search-cond__field">
              <span class="search-cond__caption">都道府県</span>
              <select class="search-cond__input" name="pref" data-pref>
                <option value="">全国</option>
                <?php foreach ($options['prefs'] as $pref) : ?>
                  <option value="<?= h($pref) ?>" <?= $cond['pref'] === $pref ? 'selected' : '' ?>><?= h($pref) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <p class="search-cond__note">現在地はこの端末の中だけで使い、送信しません。</p>
          </div>
        </details>

        <!-- 価格（上限） -->
        <details class="search-cond search-cond--box" open>
          <summary class="search-cond__head">
            <img class="search-cond__icon" src="<?= h(url('images/icons/price.svg')) ?>" alt="" width="16" height="16" />
            <span class="search-cond__name">価格</span>
            <span class="search-cond__value search-cond__value--price" data-price-label>
              <?= $priceValue === MATCH_SEARCH_PRICE_MAX ? '上限なし' : h('¥' . number_format($priceValue) . 'まで') ?>
            </span>
          </summary>
          <div class="search-cond__body search-cond__body--price">
            <span class="search-cond__end">0円</span>
            <input
              class="search-cond__range"
              type="range"
              min="0"
              max="<?= count(SEARCH_PRICE_STEPS) - 1 ?>"
              step="1"
              value="<?= (int) $priceIndex ?>"
              aria-label="価格の上限"
              data-price-range
              data-steps="<?= h(implode(',', SEARCH_PRICE_STEPS)) ?>"
            />
            <span class="search-cond__end">10万円</span>
            <!-- 送る値はこちら。右端（上限なし）のときは送らない -->
            <input type="hidden" name="max_price" value="<?= $priceValue === MATCH_SEARCH_PRICE_MAX ? '' : (int) $priceValue ?>" data-price-value />
          </div>
        </details>

        <h2 class="search-form__heading">周辺検索</h2>
        <ul class="nearby-links">
          <li>
            <a class="nearby-links__item" href="<?= h(url('pages/map/map.php?nearby=food')) ?>">
              <span class="nearby-links__image nearby-links__image--food" aria-hidden="true">🍜</span>
              <span class="nearby-links__label">アリーナグルメ</span>
            </a>
          </li>
          <li>
            <a class="nearby-links__item" href="<?= h(url('pages/map/map.php?nearby=hotel')) ?>">
              <span class="nearby-links__image nearby-links__image--hotel" aria-hidden="true">🏨</span>
              <span class="nearby-links__label">ホテル検索</span>
            </a>
          </li>
          <li>
            <span class="nearby-links__item nearby-links__item--disabled" aria-disabled="true">
              <span class="nearby-links__image nearby-links__image--transit" aria-hidden="true">🚃</span>
              <span class="nearby-links__label">交通情報（準備中）</span>
            </span>
          </li>
        </ul>

        <button class="search-form__submit" type="submit">
          この条件で検索
          <img src="<?= h(url('images/icons/search.svg')) ?>" alt="" width="24" height="24" />
        </button>
        <?php if ($isActive) : ?>
          <a class="search-form__clear" href="match-search.php">条件をクリア</a>
        <?php endif; ?>
      </form>

      <section class="match-search__results" id="results" aria-labelledby="result-heading">
        <h2 class="match-search__count" id="result-heading">
          <?= $isActive ? '条件に合う試合' : 'これからの試合' ?>
          <span class="match-search__number" data-result-count><?= count($results) ?></span>件
        </h2>
        <p class="match-search__status" data-near-status hidden></p>

        <p class="match-search__empty" data-empty <?= $results === [] ? '' : 'hidden' ?>>
          条件に合う試合が見つかりませんでした。<br />
          日程や価格の条件をゆるめて、もう一度探してみてください。
        </p>

        <?php foreach ($days as $date => $dayMatches) : ?>
          <section class="match-search__day" data-day>
            <h3 class="match-search__date"><?= h(match_date_label($date)) ?></h3>
            <ul class="match-search__items">
              <?php foreach ($dayMatches as $match) : ?>
                <li
                  class="match-search__item"
                  data-match
                  <?php if ($match['lat'] !== null && $match['lng'] !== null) : ?>
                    data-lat="<?= (float) $match['lat'] ?>"
                    data-lng="<?= (float) $match['lng'] ?>"
                  <?php endif; ?>
                >
                  <?php require __DIR__ . '/match-card.php'; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          </section>
        <?php endforeach; ?>
      </section>
    </main>

    <footer class="site-footer"></footer>

    <script src="<?= h(asset('js/main.js')) ?>"></script>
    <script type="application/json" id="favorite-state"><?= favorite_state_json($favoriteState) ?></script>
    <script src="<?= h(asset('js/common/favorite-store.js')) ?>"></script>
    <script src="<?= h(asset('js/pages/match-search.js')) ?>"></script>
    <?php require __DIR__ . '/../menu-bar.php'; ?>
  </body>
</html>
