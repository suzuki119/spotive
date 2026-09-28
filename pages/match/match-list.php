<?php

/**
 * pages/match/match-list.php
 * スケジュール一覧（企画書の機能 2）。今日以降の試合を日付ごとに並べ、
 * 開始時間・会場・チケット価格を出す。
 *
 * 試合は data/matches.json（仮データ）と v_public_tournaments（掲載大会）から取る。
 * 取り方は lib/match.php にまとめてある。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/match.php';
require_once __DIR__ . '/../../lib/favorite.php';

// -------------------------------------------------------------------
// 試合の取得
// -------------------------------------------------------------------
$matches = load_upcoming_matches();
$days    = group_matches_by_date($matches);

// お気に入りの保存先（ログイン中なら DB）と、今の登録内容
$favoriteState = favorite_client_state();

?>
<!DOCTYPE html>
<html lang="ja">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>試合一覧 | SPOTIVE</title>

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
      <div class="schedule-list">
        <div class="schedule-list__head">
          <h1 class="schedule-list__title">試合一覧</h1>
          <p class="schedule-list__count"><?= count($matches) ?>件の試合</p>
        </div>

        <?php if ($matches !== []) : ?>
          <label class="schedule-list__filter">
            <input class="schedule-list__filter-input" type="checkbox" id="favorite-only" />
            お気に入りのチームの試合だけ見る
          </label>
          <p class="schedule-list__empty is-hidden" id="favorite-empty">
            お気に入りのチームの試合はありません。<br />
            <a href="<?= h(url('pages/favorite/favorite.php')) ?>">お気に入りのチームを登録する</a>
          </p>
        <?php endif; ?>

        <?php if ($matches === []) : ?>
          <p class="schedule-list__empty">これから行われる試合はまだありません。</p>
        <?php else : ?>
          <?php foreach ($days as $date => $dayMatches) : ?>
            <section class="schedule-list__day" data-day>
              <h2 class="schedule-list__date"><?= h(match_date_label($date)) ?></h2>
              <ul class="schedule-list__items">
                <?php foreach ($dayMatches as $match) : ?>
                  <li class="schedule-list__item" data-match>
                    <?php require __DIR__ . '/match-card.php'; ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            </section>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </main>

    <footer class="site-footer"></footer>

    <script src="<?= h(asset('js/main.js')) ?>"></script>
    <script type="application/json" id="favorite-state"><?= favorite_state_json($favoriteState) ?></script>
    <script src="<?= h(asset('js/common/favorite-store.js')) ?>"></script>
    <script src="<?= h(asset('js/pages/match-list.js')) ?>"></script>
    <?php require __DIR__ . '/../menu-bar.php'; ?>
  </body>
</html>
