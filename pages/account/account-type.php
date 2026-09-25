<?php

/**
 * pages/account/account-type.php
 * アカウント種別の選択画面。一般ユーザー / 主催者アカウントを選ぶ
 */

require_once __DIR__ . '/../../config/db.php';

// ここでデータを取得する（HTML は書かない）

?>
<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>アカウント種別の選択 | SPOTIVE</title>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/the-new-css-reset/css/reset.min.css">
  <link rel="stylesheet" href="../../css/style.css">
</head>

<body>
  <header class="site-header"> <?php
                                // header.phpを読み込む
                                include_once '../header.php';
                                ?></header>

  <main class="l-main">

    <div class="inner">

      <div class="account-type">
        <h1>ようこそ！</h1>
        <p>
          メールアドレスを入力し
          <br>
          アカウント登録を開始しましょう
        </p>

        <form action="./register/register.php" method="POST">
          <label for="email"></label>

          <input
            type="email"
            id="email"
            name="email"
            placeholder="email"
            required
            class="account-type-email input">

          <button type="submit" class="account-type-btn btn">登録</button>
        </form>

        <article>
          <p>大会・イベントを主催する方</p>
          <span class="account-type-organizer btn">登録</span>
        </article>
      </div>

      <hr>

      <div class="account-type-signin">
        <p>すでにアカウントをお持ちですか？</p>
        <button class="account-type-signin-btn">Sign in</button>
      </div>
    </div>
  </main>

  <footer class="site-footer">
    <?php
    // menu-bar.phpを読み込む
    include_once __DIR__ . '/../menu-bar.php';
    ?>
  </footer>

  <script src="../../js/main.js"></script>
</body>

</html>
