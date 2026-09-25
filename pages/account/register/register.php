<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // account-type.phpからメールアドレスが送られてきた場合
  if (isset($_POST['email'])) {

    $_SESSION['register']['email'] = $_POST['email'];
  }

  // このregister.phpで名前・電話番号が送られてきた場合
  if (isset($_POST['name'])) {

    $_SESSION['register']['name'] = $_POST['name'];
    $_SESSION['register']['tel'] = $_POST['tel'] ?? '';

    header('Location: ../pasword.php');
    exit;
  }
}

?>
<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>新規登録 | SPOTIVE</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/the-new-css-reset/css/reset.min.css">
  <link rel="stylesheet" href="../../../css/style.css">
</head>

<body>
  <header class="site-header"> </header>
  <main>
    <div class="inner">
      <div class="register">
        <h1 class="register-title">アカウント登録</h1>

        <form action="" method="POST">
          <article class="register-form">
            <p>氏名</p>
            <input
              type="text"
              id="name"
              name="name"
              placeholder="田中　太郎"
              required
              class="register-form-name input">
          </article>
          <article class="register-form">
            <p>電話番号</p>
            <input
              type="tel"
              id="tel"
              name="tel"
              placeholder="09012345678"
              required
              class="register-form-tel input">
          </article>
          <button type="button" class="verification btn">本人確認</button>
          <button type="submit" class="next btn">次へ</button>
          <button type="button" class="back btn">戻る</button>
        </form>
      </div>
    </div>
  </main>
  <footer class="site-footer">

  </footer>
  <script src="../../../js/main.js"></script>
</body>

</html>
