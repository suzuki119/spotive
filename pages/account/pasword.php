<?php

/**
 * pages/account/password.php
 * パスワード設定画面
 */

declare(strict_types=1);

session_start();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $password = $_POST['password'] ?? '';
  $passwordConfirm = $_POST['password-confirm'] ?? '';

  if ($password === '' || $passwordConfirm === '') {

    $error = 'パスワードを入力してください。';
  } elseif ($password !== $passwordConfirm) {

    $error = 'パスワードが一致していません。';
  } else {

    // パスワードをセッションに保存
    $_SESSION['register']['password'] = $password;

    // 確認ページへ移動
    header('Location: confirm.php');
    exit;
  }
}

?>
<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <title>パスワード設定 | SPOTIVE</title>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />

  <link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/the-new-css-reset/css/reset.min.css">

  <link rel="stylesheet" href="../../css/style.css">
</head>

<body>

  <header class="site-header">
    <?php
    // header.phpを読み込む
    include_once '../header.php';
    ?>
  </header>

  <main class="l-main">

    <div class="inner">

      <?php if ($error !== ''): ?>
        <p class="error">
          <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </p>
      <?php endif; ?>
      <div class="pasword">
        <h1 class="pasword-title">アカウント登録</h1>
        <form action="" method="post">

          <article class="pasword-form">
            <p>パスワード</p>

            <input
              type="password"
              id="password"
              name="password"
              placeholder="パスワードを入力"
              required
              class="pasword-form-first input">
          </article>

          <article class="pasword-form">
            <p>パスワード（確認）</p>

            <input
              type="password"
              id="password-confirm"
              name="password-confirm"
              placeholder="もう一度入力"
              required
              class="pasword-form-second input">
          </article>
      </div>

      <button class="next btn" type="submit">次へ</button>
      <button type="button" class="back btn">戻る</button>

      </form>

    </div>

  </main>

  <footer class="site-footer">
  </footer>

  <script src="../../js/main.js"></script>

</body>

</html>
