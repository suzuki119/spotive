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
require_once __DIR__ . '/../lib/guest.php';

/** @var array{title:string,noticeIcon?:string,noticeLabel?:string,back?:string,close?:string} $appHeader */
$appHeader += ['title' => 'SPOTIVE', 'noticeIcon' => 'notice.svg', 'noticeLabel' => '', 'back' => '', 'close' => ''];

// ログイン中はマイページ、未ログインならログイン画面へ。
// ゲストを受け付けているときは、未ログインでもマイページへ（開くとゲストに自動ログインする。lib/guest.php）
$appHeaderUser = null;
try {
  $appHeaderUser = current_user();
} catch (PDOException $e) {
  error_log('[SPOTIVE] DB error: ' . $e->getMessage());
}
$appHeaderAccount = $appHeaderUser === null && !guest_enabled() ? 'pages/account/signin.php' : 'pages/setting/setting.php';

// 見出しが英語（SCHEDULE・SEARCH など）のときは lang="en" を付け、読み上げが日本語読みにならないようにする
$appHeaderTitleLang = preg_match('/\A[\x20-\x7E]+\z/', $appHeader['title']) === 1 ? 'en' : '';

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
  <h1 class="app-header__title"<?= $appHeaderTitleLang === '' ? '' : ' lang="' . h($appHeaderTitleLang) . '"' ?>><?= h($appHeader['title']) ?></h1>
  <?php if ($appHeader['close'] !== '') : ?>
    <a class="app-header__icon" href="<?= h(url($appHeader['close'])) ?>" data-header-back>
      <img src="<?= h(url('images/icons/close.svg')) ?>" alt="閉じる" width="32" height="32" />
    </a>
  <?php else : ?>
  <!-- 添える文字は英語（Notice）のこともあるので、読み上げでは「お知らせ」と伝える -->
  <a class="app-header__icon" href="<?= h(url('pages/notification/notification.php')) ?>" aria-label="お知らせ">
    <img
      src="<?= h(url('images/icons/' . $appHeader['noticeIcon'])) ?>"
      alt=""
      width="32"
      height="32"
    />
    <?php if ($appHeader['noticeLabel'] !== '') : ?>
      <span class="app-header__label" aria-hidden="true"><?= h($appHeader['noticeLabel']) ?></span>
    <?php endif; ?>
  </a>
  <?php endif; ?>
</header>
<?php if (is_guest($appHeaderUser)) : ?>
  <?php require __DIR__ . '/guest-bar.php'; ?>
<?php endif; ?>
<script src="<?= h(asset('js/common/header.js')) ?>"></script>
