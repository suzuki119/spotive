<?php

/**
 * pages/favorite/favorite.php
 * お気に入り画面（企画書の機能 4）。お気に入りのチームを登録し、その試合を見る。
 *
 * 登録先は、今はブラウザの localStorage（js/common/favorite-store.js）。
 * DB に保存する形は、観戦用のテーブル（チーム・試合）と一緒にチームで設計する。
 * そのため、この画面では全チーム・全試合を出しておき、表示の切り替えは JS が行う。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/match.php';

// -------------------------------------------------------------------
// チームと試合の取得
// -------------------------------------------------------------------
$teams = load_teams();

// チームは競技ごとにまとめて出す
$teamsBySport = [];
foreach ($teams as $team) {
  $teamsBySport[(string) ($team['sport'] ?? '')][] = $team;
}

// お気に入りの試合はチームで判定するので、チームの付いている試合だけでよい
$days = group_matches_by_date(array_values(array_filter(
  load_upcoming_matches(),
  static fn(array $m): bool => $m['teamIds'] !== []
)));

?>
<!DOCTYPE html>
<html lang="ja">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>お気に入り | SPOTIVE</title>

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
      <div class="favorite">
        <h1 class="favorite__title">お気に入り</h1>
        <p class="favorite__note">お気に入りはこの端末のブラウザに保存されます。</p>

        <section class="favorite__section">
          <h2 class="favorite__heading">お気に入りのチームの試合</h2>
          <p class="favorite__empty" id="favorite-empty">
            下の「チーム」から ☆ を押して登録すると、そのチームの試合がここに並びます。
          </p>

          <?php foreach ($days as $date => $dayMatches) : ?>
            <section class="match-list__day is-hidden" data-day>
              <h3 class="match-list__date"><?= h(match_date_label($date)) ?></h3>
              <ul class="match-list__items">
                <?php foreach ($dayMatches as $match) : ?>
                  <li class="match-list__item is-hidden" data-match>
                    <?php require __DIR__ . '/../match/match-card.php'; ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            </section>
          <?php endforeach; ?>
        </section>

        <section class="favorite__section">
          <h2 class="favorite__heading">チーム</h2>

          <?php if ($teams === []) : ?>
            <p class="favorite__empty">チームの情報を読み込めませんでした。</p>
          <?php else : ?>
            <?php foreach ($teamsBySport as $sport => $sportTeams) : ?>
              <h3 class="favorite__sport"><?= h(match_sport_label((string) $sport)) ?></h3>
              <ul class="favorite__teams">
                <?php foreach ($sportTeams as $team) : ?>
                  <li class="team-item">
                    <span class="team-item__name"><?= h((string) ($team['name'] ?? '')) ?></span>
                    <button
                      class="team-item__toggle"
                      type="button"
                      data-team-id="<?= h((string) ($team['id'] ?? '')) ?>"
                      aria-pressed="false"
                      aria-label="<?= h((string) ($team['name'] ?? '')) ?> をお気に入りに登録"
                    >☆</button>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endforeach; ?>
          <?php endif; ?>
        </section>
      </div>
    </main>

    <footer class="site-footer"></footer>

    <script src="<?= h(asset('js/main.js')) ?>"></script>
    <script src="<?= h(asset('js/common/favorite-store.js')) ?>"></script>
    <script src="<?= h(asset('js/pages/favorite.js')) ?>"></script>
    <?php require __DIR__ . '/../menu-bar.php'; ?>
  </body>
</html>
