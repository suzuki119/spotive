<?php

/**
 * pages/administrators/administrators.php
 * 主催者ページ。大会を登録した人・チームの管理者が、掲載中のイベントとアクセス数を見る。
 * デザインは images/Administrator/ のモック（以前の pages/organizer/dashboard.php はここへ転送する）。
 *
 * スマホ … 左上のボタンでメニューを開く（js/pages/administrators.js）。
 *          開くと画面が右にずれ、左にメニューが出る
 * PC   … メニューを左に出したままにする
 *
 * 開けるのは大会を登録できるレベルの人（config/app.php の verification.require_level.create_tournament）。
 * 未ログインなら、ゲストにログインさせてから開く（マイページと同じ）。
 * 管理するチーム（team_admins）があれば、そのチームの数字も出す。データの作り方は lib/team-admin.php。
 * 大会の確認（Lv.4）の審査を挟む設定のときは、大会ごとに「確認を申請」を出す（POST で受ける）。
 *
 *   ?team=team-001 … 管理するチームが複数あるときに、どのチームを見るか（省略時は最初のチーム）
 *   ?q=…           … 掲載中のイベント・リーグを名前で絞り込む
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/guest.php';
require_once __DIR__ . '/../../lib/tournament.php';
require_once __DIR__ . '/../../lib/layout.php';
require_once __DIR__ . '/../../lib/team-admin.php';
require_once __DIR__ . '/../../lib/icon.php';

// ログインせずに使っている人は、ゲストにログインさせてから開く
guest_auto_login();
$user = require_action_level('create_tournament', LEVEL_USER);

// ゲスト（お試し用の共通アカウント）は、公開サーバーでは見るだけ（大会の掲載・編集は tournament-new.php が断る）。
// 押しても断られるボタンは出さず、アカウントを作ると掲載できることを伝える
$guestReadOnly = is_guest($user) && !guest_can_register();

// 審査を挟まない（デモ）あいだは、確認状態と確認申請を出さない
$reviewRequired = (bool) config('verification.tournament_review_required', true);

if (is_post()) {
  try {
    csrf_verify();
    if ($reviewRequired && (string) ($_POST['action'] ?? '') === 'submit_verification') {
      tournament_submit_verification($user, (int) ($_POST['tournament_id'] ?? 0));
      flash('大会確認を申請しました。審査の結果はメールでお知らせします。', 'success');
    }
  } catch (AppError $e) {
    flash($e->getMessage(), 'error');
  } catch (PDOException $e) {
    error_log('[SPOTIVE] DB error: ' . $e->getMessage());
    flash('ただいま受け付けられません。時間をおいてお試しください。', 'error');
  }
  redirect(url('pages/administrators/administrators.php'));
}

$flashes = take_flash();

/** メニュー。href はこのページの見出しへのアンカーか、既にある画面 */
$adminMenu = [
  ['href' => '#dashboard', 'icon' => 'menu',     'label' => 'ダッシュボード', 'current' => true],
  ['href' => '#events',    'icon' => 'calendar', 'label' => 'イベント管理'],
  ['href' => '#notice',    'icon' => 'notice',   'label' => 'お知らせ配信'],
  ['href' => '#access',    'icon' => 'price',    'label' => 'アクセス解析'],
];
// 観戦者としての画面（お気に入り・遠征・設定）はマイページにあるので、そこへ戻る道を 1 つだけ置く
$adminSubMenu = [
  ['href' => 'pages/setting/mypage.php', 'icon' => 'account', 'label' => 'マイページに戻る'],
];

$teamParam = input_string($_GET, 'team');
$query     = trim(input_string($_GET, 'q'));
$now       = new DateTimeImmutable('now', new DateTimeZone((string) config('app.timezone', 'Asia/Tokyo')));
$asOf      = $now->format('Y/m/d');

$teams     = [];
$team      = null;
$dashboard = null;
$dbError   = false;

try {
  $teams = team_admin_teams((int) $user['id']);
  foreach ($teams as $candidate) {
    if ($candidate['team_id'] === $teamParam) {
      $team = $candidate;
    }
  }
  $team ??= $teams[0] ?? null;
  $dashboard = team_admin_dashboard((int) $user['id'], $team, $now);
} catch (PDOException $e) {
  error_log('[SPOTIVE] DB error: ' . $e->getMessage());
  $dbError = true;
}

$access = $dashboard['access'] ?? ['page_view' => 0, 'ticket_click' => 0, 'favorite' => 0];
$events = $dashboard['events'] ?? ['leagues' => 0, 'upcoming_matches' => 0, 'upcoming_tournaments' => 0, 'drafts' => 0];

$accessStats = [
  ['value' => $access['page_view'],    'label' => '閲覧数'],
  ['value' => $access['favorite'],     'label' => 'お気に入り登録数'],
  ['value' => $access['ticket_click'], 'label' => 'チケットページ遷移'],
];

// 「未登録」は、まだ公開していない（下書きの）大会の数
$eventStats = [
  ['value' => $events['leagues'],              'label' => '登録リーグ'],
  ['value' => $events['upcoming_matches'],     'label' => '今後の試合'],
  ['value' => $events['upcoming_tournaments'], 'label' => '今後の大会'],
  ['value' => $events['drafts'],               'label' => '未登録'],
];

$publishedEvents   = $dashboard['published'] ?? [];
$unpublishedEvents = $dashboard['unpublished'] ?? [];
if ($query !== '') {
  $matchesQuery      = static fn(array $event): bool => mb_stripos($event['title'], $query) !== false;
  $publishedEvents   = array_values(array_filter($publishedEvents, $matchesQuery));
  $unpublishedEvents = array_values(array_filter($unpublishedEvents, $matchesQuery));
}

// 一覧の見出しごとのまとまり。公開していない大会は、1 件も無ければ見出しごと出さない
$eventSections = [
  [
    'id'    => 'administrators-published-title',
    'title' => '掲載中のイベント・リーグ',
    'items' => $publishedEvents,
    'empty' => $query !== '' ? '「' . $query . '」に当てはまるイベント・リーグはありません。' : '掲載中のイベント・リーグはありません。',
  ],
];
if ($unpublishedEvents !== []) {
  $eventSections[] = [
    'id'    => 'administrators-unpublished-title',
    'title' => '公開していない大会',
    'items' => $unpublishedEvents,
    'empty' => '',
  ];
}

/** 確認を申請できる状態（審査を挟む設定のときだけ使う） */
const ADMIN_VERIFICATION_REQUESTABLE = ['unverified', 'rejected', 'more_info_required'];

?>
<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>主催者ページ | SPOTIVE</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;600&family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/the-new-css-reset/css/reset.min.css">
  <link rel="stylesheet" href="<?= h(asset('css/style.css')) ?>">
  <script src="<?= h(asset('js/pages/administrators.js')) ?>" defer></script>
</head>

<body class="administrators">
  <!-- メニュー。スマホでは左上のボタンで開き、PC では出したままにする -->
  <nav class="administrators__nav" id="administrators-nav" aria-label="主催者メニュー">
    <p class="administrators__brand" lang="en">SPOTIVE</p>
    <p class="administrators__brand-sub" lang="en">ADMINISTRATORS</p>
    <ul class="administrators__menu">
      <?php foreach ($adminMenu as $item) : ?>
        <li>
          <a class="administrators__menu-link<?= !empty($item['current']) ? ' is-current' : '' ?>" href="<?= h($item['href']) ?>"<?= !empty($item['current']) ? ' aria-current="page"' : '' ?>>
            <?= icon_html($item['icon']) ?>
            <?= h($item['label']) ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
    <ul class="administrators__menu administrators__menu--sub">
      <?php foreach ($adminSubMenu as $item) : ?>
        <li>
          <a class="administrators__menu-link" href="<?= h(url($item['href'])) ?>">
            <?= icon_html($item['icon']) ?>
            <?= h($item['label']) ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </nav>

  <div class="administrators__page">
    <header class="administrators__header" id="dashboard">
      <button class="administrators__menu-btn" type="button" aria-controls="administrators-nav" aria-expanded="false" aria-label="メニューを開く" data-label-open="メニューを開く" data-label-close="メニューを閉じる">
        <?= icon_html('hamburger', 'administrators__menu-icon') ?>
      </button>
      <div class="administrators__heading">
        <h1 class="administrators__title" lang="en">ADMINISTRATORS</h1>
        <p class="administrators__lead">主催者ページ</p>
      </div>
    </header>

    <main class="administrators__main">
      <?php foreach ($flashes as $message) : ?>
        <p class="administrators__notice administrators__notice--<?= h($message['type']) ?>" role="status"><?= h($message['message']) ?></p>
      <?php endforeach; ?>

      <?php if ($guestReadOnly) : ?>
        <p class="administrators__notice">
          ゲストでは閲覧のみです。<a href="<?= h(url('pages/account/account-type.php')) ?>">アカウントを作成</a>すると、イベントを掲載できます。
        </p>
      <?php endif; ?>

      <?php if ($dbError) : ?>
        <p class="administrators__notice administrators__notice--error" role="alert">データを読み込めませんでした。時間をおいて開き直してください。</p>
      <?php elseif ($team === null) : ?>
        <p class="administrators__notice">管理するチームがまだ登録されていません。チームの数字を見るには、運営にチームの追加を依頼してください。自分が登録した大会の数字は表示しています。</p>
      <?php else : ?>
        <div class="administrators__team">
          <p class="administrators__team-name"><?= h($team['display_name']) ?></p>
          <?php if (count($teams) > 1) : ?>
            <ul class="administrators__team-switch" aria-label="管理するチーム">
              <?php foreach ($teams as $candidate) : ?>
                <li>
                  <a class="administrators__team-link<?= $candidate['team_id'] === $team['team_id'] ? ' is-current' : '' ?>" href="?team=<?= urlencode($candidate['team_id']) ?>"<?= $candidate['team_id'] === $team['team_id'] ? ' aria-current="page"' : '' ?>><?= h($candidate['team_name']) ?></a>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else : ?>
            <p class="administrators__team-sub"><?= h($team['team_name']) ?></p>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <form class="administrators__search" role="search" action="" method="get">
        <?php if ($team !== null) : ?>
          <input type="hidden" name="team" value="<?= h($team['team_id']) ?>">
        <?php endif; ?>
        <label class="visually-hidden" for="administrators-search">掲載中のイベント・リーグを検索</label>
        <input class="administrators__search-input" id="administrators-search" type="search" name="q" value="<?= h($query) ?>" placeholder="検索">
        <button class="administrators__search-btn" type="submit" aria-label="検索">
          <?= icon_html('search') ?>
        </button>
      </form>

      <div class="administrators__boards">
        <section class="administrators__card" id="access" aria-labelledby="administrators-access-title">
          <div class="administrators__card-head">
            <h2 class="administrators__card-title" id="administrators-access-title">アクセスボード</h2>
            <p class="administrators__card-date"><?= h($asOf) ?> 現在</p>
          </div>
          <dl class="administrators__stats">
            <?php foreach ($accessStats as $stat) : ?>
              <div class="administrators__stat">
                <dt class="administrators__stat-label"><?= h($stat['label']) ?></dt>
                <dd class="administrators__stat-value"><?= h(number_format((int) $stat['value'])) ?></dd>
              </div>
            <?php endforeach; ?>
          </dl>
        </section>

        <section class="administrators__card" id="events" aria-labelledby="administrators-events-title">
          <div class="administrators__card-head">
            <h2 class="administrators__card-title" id="administrators-events-title">イベント管理</h2>
            <p class="administrators__card-date"><?= h($asOf) ?> 現在</p>
          </div>
          <dl class="administrators__stats">
            <?php foreach ($eventStats as $stat) : ?>
              <div class="administrators__stat">
                <dt class="administrators__stat-label"><?= h($stat['label']) ?></dt>
                <dd class="administrators__stat-value"><?= h(number_format((int) $stat['value'])) ?></dd>
              </div>
            <?php endforeach; ?>
          </dl>
        </section>
      </div>

      <?php if ($guestReadOnly) : ?>
        <span class="administrators__add administrators__add--disabled" aria-disabled="true">
          イベントを追加
          <?= icon_html('eventsadd') ?>
        </span>
      <?php else : ?>
        <a class="administrators__add" href="<?= h(url('pages/organizer/tournament-new.php')) ?>">
          イベントを追加
          <?= icon_html('eventsadd') ?>
        </a>
      <?php endif; ?>

      <section class="administrators__card" id="notice" aria-labelledby="administrators-notice-title">
        <h2 class="administrators__card-title" id="administrators-notice-title">お知らせ配信</h2>
        <p class="administrators__card-text">
          チームをお気に入りに登録しているユーザー向けに、<br>お知らせを配信できます。
        </p>
      </section>

      <?php foreach ($eventSections as $section) : ?>
        <section class="administrators__published" aria-labelledby="<?= h($section['id']) ?>">
          <h2 class="administrators__section-title" id="<?= h($section['id']) ?>"><?= h($section['title']) ?></h2>
          <?php if ($section['items'] === []) : ?>
            <p class="administrators__empty"><?= h($section['empty']) ?></p>
          <?php else : ?>
            <ul class="administrators__event-list">
              <?php foreach ($section['items'] as $event) : ?>
                <li class="administrators__card administrators__event">
                  <h3 class="administrators__event-title"><?= h($event['title']) ?></h3>
                  <p class="administrators__event-term">
                    <?= icon_html('date') ?>
                    <?= h($event['term']) ?>
                  </p>
                  <p class="administrators__event-arena"><?= h($event['place_label']) ?>：<?= h($event['place'] !== '' ? $event['place'] : '未登録') ?></p>
                  <div class="administrators__event-foot">
                    <?php if (isset($event['status'])) : ?>
                      <p class="administrators__status administrators__status--muted"><?= h(status_label($event['status'])) ?></p>
                    <?php else : ?>
                      <p class="administrators__status">
                        掲載中
                        <?= icon_html('check') ?>
                      </p>
                    <?php endif; ?>
                    <?php if ($reviewRequired && $event['tournament_id'] !== null) : ?>
                      <p class="administrators__verification"><?= h(status_label($event['verification_status'])) ?></p>
                    <?php endif; ?>
                    <?php if ($event['tournament_id'] !== null && !$guestReadOnly) : ?>
                      <a class="administrators__edit" href="<?= h(url('pages/organizer/tournament-new.php')) ?>?id=<?= (int) $event['tournament_id'] ?>">編集する</a>
                    <?php endif; ?>
                  </div>
                  <?php if ($reviewRequired && $event['tournament_id'] !== null && in_array($event['verification_status'], ADMIN_VERIFICATION_REQUESTABLE, true)) : ?>
                    <form class="administrators__verify" action="" method="post">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="submit_verification">
                      <input type="hidden" name="tournament_id" value="<?= (int) $event['tournament_id'] ?>">
                      <button class="administrators__verify-btn" type="submit">確認を申請</button>
                    </form>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </section>
      <?php endforeach; ?>
    </main>
  </div>
</body>

</html>
