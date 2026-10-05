<?php

/**
 * pages/match/match-list.php
 * スケジュール一覧（企画書の機能 2）。今日以降の試合を日付ごとに並べ、
 * 開始時間・会場・チケット価格を出す。
 *
 * 試合は data/matches.json（仮データ）と v_public_tournaments（掲載大会）から取る。
 * 取り方は lib/match.php にまとめてある。
 *
 * 競技のタブ（?sport=baseball など）で絞り込める。タブの並びと競技の寄せ方は
 * 試合を探す画面（match-search.php）と同じ（lib/match-search.php）。
 * 「お気に入りのチームの試合だけ見る」は JS で絞り込み、タブを切り替えても残るよう ?favorite=1 で持ち回る。
 *
 * 一度に見せるのは 1 日分。「‹ 今日 ›」で試合のある日へ移り、「カレンダー」で下から出る
 * カレンダーから日を選べる（js/pages/match-list.js）。選んだ日はタブを切り替えても残るよう ?date= で持ち回る。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/match.php';
require_once __DIR__ . '/../../lib/match-search.php';
require_once __DIR__ . '/../../lib/favorite.php';

// -------------------------------------------------------------------
// 試合の取得と、競技での絞り込み
// -------------------------------------------------------------------
$upcoming     = load_upcoming_matches();
$sport        = match_search_conditions(['sport' => input_string($_GET, 'sport')])['sport'];
$favoriteOnly = input_string($_GET, 'favorite') === '1';
// 選んでいる日（無ければ JS が今日にする）。形がおかしい値は使わない
$selectedDate = input_string($_GET, 'date');
$selectedDate = preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $selectedDate) === 1 && strtotime($selectedDate) !== false
  ? $selectedDate
  : '';

$matches = $sport === ''
  ? $upcoming
  : array_values(array_filter(
    $upcoming,
    static fn(array $m): bool => match_search_sport_key((string) $m['sport']) === $sport
  ));
$days = group_matches_by_date($matches);

// 競技のタブ（試合を探す画面と共通）
$tabs = ['' => 'すべて'];
$sportLabels = [
  'baseball' => '野球',
  'soccer' => 'サッカー',
  'basketball' => 'バスケットボール',
  'volleyball' => 'バレーボール',
  'tennis' => 'テニス',
];
foreach ($upcoming as $match) {
  $key = match_search_sport_key((string) $match['sport']);
  if ($key !== '') {
    $tabs[$key] = $sportLabels[$key] ?? $key;
  }
}

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
    rel="stylesheet" />
  <link rel="stylesheet" href="<?= h(asset('css/style.css')) ?>" />
</head>

<body>
  <?php
  $appHeader = ['title' => 'SCHEDULE', 'noticeIcon' => 'mail.svg', 'noticeLabel' => 'Notice'];
  require __DIR__ . '/../app-header.php';
  ?>

  <main class="l-main">
    <div class="schedule-list">
      <!-- ‹ 今日 › ［カレンダー］ -->
      <div class="schedule-days">
        <button class="schedule-days__step" type="button" data-day-step="-1" aria-label="前の試合の日">‹</button>
        <p class="schedule-days__label" data-day-label aria-live="polite">今日</p>
        <button class="schedule-days__step" type="button" data-day-step="1" aria-label="次の試合の日">›</button>
        <button
          class="schedule-days__calendar"
          type="button"
          data-calendar-open
          aria-haspopup="dialog"
          aria-controls="calendar-sheet"
        >カレンダー</button>
      </div>
      <p class="schedule-list__count"><span data-day-count><?= count($matches) ?></span>件の試合</p>

      <form class="schedule-list__form" id="schedule-form" action="match-list.php" method="get">
        <!-- 競技のタブ。選ぶとすぐに表示し直す（JS が無いときは下のボタンで） -->
        <div class="search-tabs" role="radiogroup" aria-label="競技">
          <?php foreach ($tabs as $value => $label) : ?>
            <label class="search-tabs__tab">
              <input
                class="search-tabs__input"
                type="radio"
                name="sport"
                value="<?= h((string) $value) ?>"
                <?= $sport === (string) $value ? 'checked' : '' ?> />
              <span class="search-tabs__label"><?= h($label) ?></span>
            </label>
          <?php endforeach; ?>
        </div>

        <?php if ($matches !== []) : ?>
          <label class="schedule-list__filter">
            <input
              class="schedule-list__filter-input"
              type="checkbox"
              id="favorite-only"
              name="favorite"
              value="1"
              <?= $favoriteOnly ? 'checked' : '' ?> />
            お気に入りのチームの試合だけ見る
          </label>
        <?php endif; ?>

        <!-- 選んでいる日。タブを切り替えても同じ日を見られるよう、一緒に送る -->
        <input type="hidden" name="date" value="<?= h($selectedDate) ?>" data-date-input />

        <noscript>
          <button class="schedule-list__submit" type="submit">この条件で表示する</button>
        </noscript>
      </form>

      <?php if ($matches !== []) : ?>
        <p class="schedule-list__empty is-hidden" id="favorite-empty">
          お気に入りのチームの試合はありません。<br />
          <a href="<?= h(url('pages/favorite/favorite.php')) ?>">お気に入りのチームを登録する</a>
        </p>
      <?php endif; ?>

      <?php if ($matches === []) : ?>
        <p class="schedule-list__empty">
          <?php if ($sport === '') : ?>
            これから行われる試合はまだありません。
          <?php else : ?>
            <?= h($tabs[$sport] ?? '') ?>のこれからの試合はありません。<br />
            <a href="match-list.php">すべての競技の試合を見る</a>
          <?php endif; ?>
        </p>
      <?php else : ?>
        <!-- 選んだ日に試合が無いとき -->
        <p class="schedule-list__empty schedule-list__none" data-day-none hidden>
          <span data-day-none-text>この日の試合はありません。</span>
          <button class="schedule-list__next" type="button" data-day-next hidden></button>
        </p>

        <?php foreach ($days as $date => $dayMatches) : ?>
          <section class="schedule-list__day" data-day data-date="<?= h($date) ?>">
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

    <!-- 下から出るカレンダー。中の日付は js/pages/match-list.js が作る -->
    <div class="calendar-sheet" id="calendar-sheet" data-calendar hidden>
      <div class="calendar-sheet__backdrop" data-calendar-close></div>
      <section class="calendar-sheet__panel" role="dialog" aria-modal="true" aria-labelledby="calendar-title">
        <span class="calendar-sheet__handle" aria-hidden="true"></span>
        <div class="calendar-sheet__head">
          <button class="calendar-sheet__step" type="button" data-month-step="-1" aria-label="前の月">‹</button>
          <h2 class="calendar-sheet__title" id="calendar-title" data-month-label>9月</h2>
          <button class="calendar-sheet__step" type="button" data-month-step="1" aria-label="次の月">›</button>
        </div>
        <ol class="calendar-sheet__week" aria-hidden="true">
          <li>日</li><li>月</li><li>火</li><li>水</li><li>木</li><li>金</li><li>土</li>
        </ol>
        <div class="calendar-sheet__grid" data-calendar-grid role="grid"></div>
        <p class="calendar-sheet__note">点のある日に試合があります。</p>
        <button class="calendar-sheet__close" type="button" data-calendar-close>閉じる</button>
      </section>
    </div>
  </main>

  <footer class="site-footer"></footer>

  <script src="<?= h(asset('js/main.js')) ?>"></script>
  <script type="application/json" id="favorite-state">
    <?= favorite_state_json($favoriteState) ?>
  </script>
  <script src="<?= h(asset('js/common/favorite-store.js')) ?>"></script>
  <script src="<?= h(asset('js/pages/match-list.js')) ?>"></script>
  <?php require __DIR__ . '/../menu-bar.php'; ?>
</body>

</html>
