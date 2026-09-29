<?php

/**
 * pages/app-header.php
 * 画面上のヘッダー（左にアカウント、中央に見出し、右にお知らせ）。ホーム・検索などで共通。
 * 各ページの <body> の直後で require して使う。
 *
 * 読み込む前に $appHeader を用意しておく。
 *   title       … 中央の見出し（例：'SPOTIVE'、'SEARCH'）
 *   noticeIcon  … お知らせのアイコンのファイル名（省略時 notice.svg）
 *   noticeLabel … アイコンの下に添える文字（省略時は出さない。例：'Notice'）
 *
 * 相対パスは読み込んだページの URL を基準にするので、必ず url() を通すこと（menu-bar.php と同じ）。
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';

/** @var array{title:string,noticeIcon?:string,noticeLabel?:string} $appHeader */
$appHeader += ['title' => 'SPOTIVE', 'noticeIcon' => 'notice.svg', 'noticeLabel' => ''];

// ログイン中はマイページ、未ログインならログイン画面へ
$appHeaderUser = null;
try {
  $appHeaderUser = current_user();
} catch (PDOException $e) {
  error_log('[SPOTIVE] DB error: ' . $e->getMessage());
}
$appHeaderAccount = $appHeaderUser === null ? 'pages/account/signin.php' : 'pages/setting/setting.php';

?>
<header class="app-header">
  <a class="app-header__icon" href="<?= h(url($appHeaderAccount)) ?>">
    <img src="<?= h(url('images/icons/account.svg')) ?>" alt="アカウント" width="32" height="32" />
  </a>
  <h1 class="app-header__title"><?= h($appHeader['title']) ?></h1>
  <a class="app-header__icon" href="<?= h(url('pages/notification/notification.php')) ?>">
    <img
      src="<?= h(url('images/icons/' . $appHeader['noticeIcon'])) ?>"
      alt="<?= $appHeader['noticeLabel'] === '' ? 'お知らせ' : '' ?>"
      width="32"
      height="32"
    />
    <?php if ($appHeader['noticeLabel'] !== '') : ?>
      <span class="app-header__label"><?= h($appHeader['noticeLabel']) ?></span>
    <?php endif; ?>
  </a>
</header>
