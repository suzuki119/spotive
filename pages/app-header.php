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
 *   back        … 左をアカウントではなく「戻る」にするときの戻り先（例：'pages/home/home.php'）。
 *                 同じサイトから来たときは、js/common/header.js が来た画面に戻す
 *   close       … 右をお知らせではなく ✕ にするときの戻り先（例：'pages/home/home.php'）。
 *                 back と同じく、同じサイトから来たときは来た画面に戻す（お知らせ画面で使う）
 *
 * 相対パスは読み込んだページの URL を基準にするので、必ず url() を通すこと（menu-bar.php と同じ）。
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';

/** @var array{title:string,noticeIcon?:string,noticeLabel?:string,back?:string,close?:string} $appHeader */
$appHeader += ['title' => 'SPOTIVE', 'noticeIcon' => 'notice.svg', 'noticeLabel' => '', 'back' => '', 'close' => ''];

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
  <?php if ($appHeader['back'] !== '') : ?>
    <a class="app-header__icon" href="<?= h(url($appHeader['back'])) ?>" data-header-back>
      <img src="<?= h(url('images/icons/back.svg')) ?>" alt="戻る" width="32" height="32" />
    </a>
  <?php else : ?>
    <a class="app-header__icon" href="<?= h(url($appHeaderAccount)) ?>">
      <img src="<?= h(url('images/icons/account.svg')) ?>" alt="アカウント" width="32" height="32" />
    </a>
  <?php endif; ?>
  <h1 class="app-header__title"><?= h($appHeader['title']) ?></h1>
  <?php if ($appHeader['close'] !== '') : ?>
    <a class="app-header__icon" href="<?= h(url($appHeader['close'])) ?>" data-header-back>
      <img src="<?= h(url('images/icons/close.svg')) ?>" alt="閉じる" width="32" height="32" />
    </a>
  <?php else : ?>
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
  <?php endif; ?>
</header>
<script src="<?= h(asset('js/common/header.js')) ?>"></script>
