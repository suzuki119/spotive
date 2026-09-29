<?php

/**
 * pages/notification/notification.php
 * お知らせ画面。運営からのお知らせと、お気に入りチームのお知らせを新しい順に並べる。
 *
 * 取り方は lib/notification.php にまとめてある（v_public_notifications から取る）。
 * カードを開くと本文が出て、既読になる（js/pages/notification.js）。
 *   ログイン中 … 既読は DB（notification-api.php 経由）
 *   未ログイン … チーム向けのお知らせは、ブラウザに保存したお気に入りのチームの分だけ出す。
 *                既読もブラウザ（localStorage）に持つ
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/notification.php';
require_once __DIR__ . '/../../lib/favorite.php';

// -------------------------------------------------------------------
// お知らせの取得
// -------------------------------------------------------------------
$user          = null;
$notifications = [];
$dbError       = false;

try {
  $user          = current_user();
  $notifications = notification_list($user === null ? null : (int) $user['id']);
} catch (PDOException $e) {
  // テーブルがまだ無い環境でも、画面は開けるようにする
  error_log('[SPOTIVE] DB error: ' . $e->getMessage());
  $dbError = true;
}

$loggedIn = $user !== null;

// js/pages/notification.js が最初に読む状態
$noticeState = [
  'loggedIn' => $loggedIn,
  'endpoint' => url('pages/notification/notification-api.php'),
  'csrf'     => $loggedIn ? csrf_token() : '',
];

// 未ログインでチーム向けのお知らせを出すかは、お気に入りを見て JS が決める
// （DB に繋がらないときは、favorite_client_state() が未ログイン扱いで返す）
$favoriteState = favorite_client_state();

?>
<!DOCTYPE html>
<html lang="ja">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>お知らせ | SPOTIVE</title>

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
    $appHeader = ['title' => 'NOTICE', 'close' => 'pages/home/home.php'];
    require __DIR__ . '/../app-header.php';
    ?>

    <main class="notice-page">
      <?php if ($dbError) : ?>
        <p class="notice-page__empty">お知らせを読み込めませんでした。時間をおいてお試しください。</p>
      <?php else : ?>
        <p class="notice-page__empty" data-notice-empty <?= $notifications === [] ? '' : 'hidden' ?>>
          お知らせはまだありません。<br />
          お気に入りのチームを登録すると、そのチームのお知らせも届きます。
        </p>

        <?php if (!$loggedIn) : ?>
          <p class="notice-page__note">
            <a href="<?= h(url('pages/account/signin.php') . '?next=' . urlencode(current_url())) ?>">ログイン</a>すると、
            ほかの端末でも既読がそろいます。
          </p>
        <?php endif; ?>

        <ul class="notice-page__list">
          <?php foreach ($notifications as $n) : ?>
            <li
              class="notice-page__item"
              data-notice
              data-notice-id="<?= (int) $n['id'] ?>"
              <?php if ($n['audience'] === 'team') : ?>
                data-team-id="<?= h($n['teamId']) ?>"
                <?= $loggedIn ? '' : 'hidden' ?>
              <?php endif; ?>
            >
              <details class="notice-card<?= $n['isRead'] === false ? ' is-unread' : '' ?> notice-card--<?= h($n['category']) ?>">
                <summary class="notice-card__summary">
                  <span class="notice-card__meta">
                    <span class="notice-card__unread" aria-hidden="true"></span>
                    <time class="notice-card__date" datetime="<?= h(date('c', (int) strtotime($n['publishedAt']))) ?>">
                      <?= h(notification_date($n['publishedAt'])) ?>
                    </time>
                    <?php if (NOTIFICATION_CATEGORY_LABELS[$n['category']] !== '') : ?>
                      <span class="notice-card__category"><?= h(NOTIFICATION_CATEGORY_LABELS[$n['category']]) ?></span>
                    <?php endif; ?>
                    <span class="notice-card__status" data-notice-status><?= $n['isRead'] === false ? '未読' : '' ?></span>
                  </span>
                  <span class="notice-card__title"><?= h($n['title']) ?></span>
                  <span class="notice-card__sender">
                    <img class="notice-card__sender-icon" src="<?= h(url('images/icons/mail.svg')) ?>" alt="" width="20" height="16" />
                    <?= h($n['sender']) ?>
                  </span>
                </summary>

                <div class="notice-card__detail">
                  <?php if ($n['body'] !== '') : ?>
                    <p class="notice-card__body"><?= nl2br(h($n['body'])) ?></p>
                  <?php endif; ?>
                  <?php if ($n['detailPath'] !== '') : ?>
                    <a class="notice-card__link" href="<?= h(url($n['detailPath'])) ?>">試合の詳細を見る</a>
                  <?php endif; ?>
                </div>
              </details>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </main>

    <footer class="site-footer"></footer>

    <script src="<?= h(asset('js/main.js')) ?>"></script>
    <script type="application/json" id="notice-state"><?= favorite_state_json($noticeState) ?></script>
    <script type="application/json" id="favorite-state"><?= favorite_state_json($favoriteState) ?></script>
    <script src="<?= h(asset('js/common/favorite-store.js')) ?>"></script>
    <script src="<?= h(asset('js/pages/notification.js')) ?>"></script>
    <?php require __DIR__ . '/../menu-bar.php'; ?>
  </body>
</html>
