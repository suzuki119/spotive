<?php

/**
 * lib/layout.php
 * ログインまわり・申請まわりのページで共通して使う枠。
 *
 * 観戦者向けのページ（home / match-list など）は各担当が作るので、
 * この枠は「アカウント・主催者・運営」の画面だけで使っている。
 */

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once __DIR__ . '/auth.php';

/** 申請・大会の状態 => 画面に出す日本語 */
const STATUS_LABELS = [
  'draft'              => '下書き',
  'submitted'          => '審査待ち',
  'in_review'          => '審査中',
  'more_info_required' => '追加情報の提出待ち',
  'approved'           => '承認済み',
  'rejected'           => '却下',
  'withdrawn'          => '取り下げ',
  'expired'            => '期限切れ',
  'unverified'         => '未確認',
  'verified'           => '確認済み',
  'published'          => '公開中',
  'cancelled'          => '中止',
  'finished'           => '終了',
  'active'             => '有効',
  'suspended'          => '停止中',
  'revoked'            => '取消',
];

function status_label(?string $status): string
{
  return STATUS_LABELS[(string) $status] ?? (string) $status;
}

/** 2026-10-03 18:00:00 => 10月3日(土) 18:00 */
function format_datetime(?string $datetime): string
{
  if ($datetime === null || $datetime === '') {
    return '';
  }
  $time = strtotime($datetime);
  if ($time === false) {
    return '';
  }
  $youbi = ['日', '月', '火', '水', '木', '金', '土'][(int) date('w', $time)];
  return date('n月j日', $time) . '(' . $youbi . ') ' . date('H:i', $time);
}

/** <input type="datetime-local"> に入れる形 */
function datetime_local(?string $datetime): string
{
  if ($datetime === null || $datetime === '') {
    return '';
  }
  $time = strtotime($datetime);
  return $time === false ? '' : date('Y-m-d\TH:i', $time);
}

/**
 * ページの上半分。データ取得が終わってから呼ぶこと
 * （ここで出力が始まるので、以降は redirect() できない）。
 */
function page_header(string $title, string $heading = ''): void
{
  $user = current_user();
  $base = base_path();
  ?>
<!DOCTYPE html>
<html lang="ja">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= h($title) ?> | SPOTIVE</title>

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Jost:wght@400;600&family=Noto+Sans+JP:wght@400;500;700&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="<?= h(asset('css/style.css')) ?>" />
  </head>

  <body>
    <header class="site-header">
      <a class="site-header__logo" href="<?= h($base) ?>/index.php">SPOTIVE</a>

      <nav class="site-header__nav">
        <a class="site-header__link" href="<?= h($base) ?>/pages/map/map.php">マップ</a>
        <a class="site-header__link" href="<?= h($base) ?>/pages/match/match-list.php">試合一覧</a>

        <?php if ($user === null) : ?>
          <a class="site-header__link" href="<?= h($base) ?>/pages/account/login.php">ログイン</a>
          <a class="site-header__link" href="<?= h($base) ?>/pages/account/register.php">新規登録</a>
        <?php else : ?>
          <a class="site-header__link" href="<?= h($base) ?>/pages/setting/setting.php">
            <?= h((string) $user['nickname']) ?> さん
          </a>
          <?php if ((int) $user['trust_level'] >= LEVEL_ORGANIZER) : ?>
            <a class="site-header__link" href="<?= h($base) ?>/pages/organizer/dashboard.php">主催者ページ</a>
          <?php endif; ?>
          <?php if (in_array((string) $user['role'], ['reviewer', 'admin'], true)) : ?>
            <a class="site-header__link" href="<?= h($base) ?>/pages/admin/review.php">審査</a>
          <?php endif; ?>
          <form class="site-header__logout" action="<?= h($base) ?>/pages/account/logout.php" method="post">
            <?= csrf_field() ?>
            <button class="site-header__link" type="submit">ログアウト</button>
          </form>
        <?php endif; ?>
      </nav>
    </header>

    <main class="l-main">
      <div class="form-page">
        <?php foreach (take_flash() as $message) : ?>
          <p class="notice notice--<?= h($message['type']) ?>"><?= h($message['message']) ?></p>
        <?php endforeach; ?>

        <?php if ($heading !== '') : ?>
          <h1 class="form-page__title"><?= h($heading) ?></h1>
        <?php endif; ?>
  <?php
}

function page_footer(): void
{
  $base = base_path();
  ?>
      </div>
    </main>

    <footer class="site-footer">
      <p class="site-footer__copyright">&copy; SPOTIVE</p>
    </footer>

    <script src="<?= h(asset('js/main.js')) ?>"></script>
  </body>
</html>
  <?php
}

/**
 * 入力欄の下に出すエラー。$errors は AppError::fields() の中身。
 */
function field_error(array $errors, string $key): void
{
  if (isset($errors[$key])) {
    echo '<p class="field-error">' . h($errors[$key]) . '</p>';
  }
}
