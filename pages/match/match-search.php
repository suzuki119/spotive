<?php

/**
 * pages/match/match-search.php
 * 試合を探す画面（企画書の機能 3「条件検索・フィルター」）。
 * キーワード・競技・都道府県・日付・料金の上限で絞り込み、日付ごとに並べる。
 *
 * 対象は data/matches.json（仮データ）と v_public_tournaments（掲載大会）の両方。
 * 絞り込みの処理は lib/match-search.php にまとめてある。
 * トップページ（index.php）の「条件から探す」もこの画面に送る。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/match-search.php';

// -------------------------------------------------------------------
// 検索条件と試合の取得
// -------------------------------------------------------------------
$cond     = match_search_conditions($_GET);
$upcoming = load_upcoming_matches();
$options  = match_search_options($upcoming);
$results  = match_search($upcoming, $cond);
$days     = group_matches_by_date($results);
$isActive = match_search_is_active($cond);

?>
<!DOCTYPE html>
<html lang="ja">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>試合を探す | SPOTIVE</title>

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Jost:wght@400;600&family=Noto+Sans+JP:wght@400;500;700&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="<?= h(asset('css/style.css')) ?>" />
  </head>

  <body>
    <header class="site-header"></header>

    <main class="l-main">
      <div class="match-search">
        <h1 class="match-search__title">試合を探す</h1>

        <form class="match-search__form" action="match-search.php" method="get">
          <div class="match-search__field match-search__field--wide">
            <label class="match-search__label" for="keyword">キーワード</label>
            <input
              class="match-search__input"
              type="search"
              id="keyword"
              name="keyword"
              value="<?= h($cond['keyword']) ?>"
              placeholder="チーム名・会場名など"
              maxlength="50"
            />
          </div>

          <div class="match-search__field">
            <label class="match-search__label" for="sport">競技</label>
            <select class="match-search__input" id="sport" name="sport">
              <option value="">すべての競技</option>
              <?php foreach ($options['sports'] as $value => $label) : ?>
                <option value="<?= h($value) ?>" <?= $cond['sport'] === $value ? 'selected' : '' ?>>
                  <?= h($label) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="match-search__field">
            <label class="match-search__label" for="pref">都道府県</label>
            <select class="match-search__input" id="pref" name="pref">
              <option value="">全国</option>
              <?php foreach ($options['prefs'] as $value) : ?>
                <option value="<?= h($value) ?>" <?= $cond['pref'] === $value ? 'selected' : '' ?>>
                  <?= h($value) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="match-search__field">
            <label class="match-search__label" for="when">いつ</label>
            <select class="match-search__input" id="when" name="when">
              <?php foreach (MATCH_SEARCH_WHEN as $value => $label) : ?>
                <option value="<?= h($value) ?>" <?= $cond['when'] === $value ? 'selected' : '' ?>>
                  <?= h($label) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="match-search__field">
            <label class="match-search__label" for="date">日付を指定</label>
            <input
              class="match-search__input"
              type="date"
              id="date"
              name="date"
              value="<?= h($cond['date']) ?>"
              min="<?= h(date('Y-m-d')) ?>"
            />
          </div>

          <div class="match-search__field">
            <label class="match-search__label" for="max-price">料金の上限</label>
            <select class="match-search__input" id="max-price" name="max_price">
              <option value="">指定しない</option>
              <?php foreach (MATCH_SEARCH_PRICES as $value) : ?>
                <option value="<?= (int) $value ?>" <?= $cond['maxPrice'] === $value ? 'selected' : '' ?>>
                  <?= $value === 0 ? '無料のみ' : h('¥' . number_format($value) . 'まで') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <p class="match-search__hint">
            料金は、試合はチケットの最安席の目安、大会は参加費です。日付を指定すると「いつ」より優先します。
          </p>

          <div class="match-search__actions">
            <button class="match-search__submit" type="submit">この条件で探す</button>
            <?php if ($isActive) : ?>
              <a class="match-search__clear" href="match-search.php">条件をクリア</a>
            <?php endif; ?>
          </div>
        </form>

        <section class="match-search__results" aria-labelledby="result-heading">
          <h2 class="match-search__count" id="result-heading">
            <?= $isActive ? '条件に合う試合' : 'これからの試合' ?>
            <span class="match-search__number"><?= count($results) ?></span>件
          </h2>

          <?php if ($results === []) : ?>
            <p class="match-search__empty">
              条件に合う試合が見つかりませんでした。<br />
              日付や料金の条件をゆるめて、もう一度探してみてください。
            </p>
          <?php else : ?>
            <?php foreach ($days as $date => $dayMatches) : ?>
              <section class="match-search__day">
                <h3 class="match-search__date"><?= h(match_date_label($date)) ?></h3>
                <ul class="match-search__items">
                  <?php foreach ($dayMatches as $match) : ?>
                    <li class="match-search__item">
                      <?php require __DIR__ . '/match-card.php'; ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </section>
            <?php endforeach; ?>
          <?php endif; ?>
        </section>
      </div>
    </main>

    <footer class="site-footer"></footer>

    <script src="<?= h(asset('js/main.js')) ?>"></script>
    <?php require __DIR__ . '/../menu-bar.php'; ?>
  </body>
</html>
