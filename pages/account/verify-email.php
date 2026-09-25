<?php

/**
 * pages/account/verify-email.php
 * 登録後に送る確認メールのリンク先。
 * トークンを消化してメールアドレスを確認済みにし、信頼レベルを Lv.1 に上げる。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/account.php';

session_boot();

$token   = trim((string) ($_GET['token'] ?? ''));
$done    = false;
$message = '';

try {
  $done = account_verify_email($token) !== null;
  $message = $done
    ? 'メールアドレスの確認が完了しました。'
    : 'リンクが無効か、有効期限が切れています。ログインしてから確認メールを送り直してください。';
} catch (PDOException $e) {
  error_log('[SPOTIVE] DB error: ' . $e->getMessage());
  $message = 'ただいま確認できません。時間をおいてお試しください。';
}

?>
<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>メールアドレスの確認 | SPOTIVE</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/the-new-css-reset/css/reset.min.css">
  <link rel="stylesheet" href="<?= h(asset('css/style.css')) ?>">
</head>

<body>
  <main>
    <div class="inner">
      <div class="complete">

        <h1 class="complete-title">メールアドレスの確認</h1>

        <p class="notice notice--<?= $done ? 'success' : 'error' ?>">
          <?= h($message) ?>
        </p>

        <p class="complete-note">
          <a href="signin.php">ログインへ進む</a>
        </p>

      </div>
    </div>
  </main>

  <script src="<?= h(asset('js/main.js')) ?>"></script>
</body>

</html>
