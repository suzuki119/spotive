<?php

/**
 * index.php
 * 入口。来た人に合わせて、行き先を振り分ける。
 *
 *   ログインしている                         … ホーム（pages/home/home.php）
 *   ログインしていないが、この端末で一度
 *   ログインしたことがある（lib/auth.php の印）… ログイン画面（pages/account/signin.php）
 *   初めての人                               … このスタート画面。「はじめる」でアカウント作成へ
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';

$loggedIn = false;

try {
  $loggedIn = is_logged_in();
} catch (PDOException $e) {
  // DB に繋がらないときは未ログインとして扱う
  error_log('[SPOTIVE] DB error: ' . $e->getMessage());
}

if ($loggedIn) {
  redirect('pages/home/home.php');
}

if (is_returning_visitor()) {
  redirect('pages/account/signin.php');
}

?>
<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/the-new-css-reset/css/reset.min.css">
  <link rel="stylesheet" href="<?= h(asset('css/style.css')) ?>">
  <title>SPOTIVE</title>
</head>

<body class="start">
  <div class="start-text">
    <h1 class="start-title">SPOTIVEへようこそ。</h1>
    <p class="start-letter">スポーツ観戦をもっと身近に。</p>
  </div>
  <section class="start-gradation">
    <a href="./pages/account/account-type.php">はじめる</a>
    <!-- 別の端末で登録した人など、印が無くてもアカウントを持っている人のために -->
    <p class="start-signin">
      アカウントをお持ちの方は<a href="./pages/account/signin.php">ログイン</a>
    </p>
  </section>

</body>

</html>
