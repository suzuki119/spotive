<?php

/**
 * index.php
 * 入口。来た人に合わせて、行き先を振り分ける。
 *
 *   ログインしている                         … ホーム（pages/home/home.php）
 *   ログインしていないが、この端末で一度
 *   ログインしたことがある（lib/auth.php の印）… ログイン画面（pages/account/signin.php）
 *   「ログインせずに使う」を選んだことがある … ホーム（pages/account/browse.php の印）
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

if (is_browsing_visitor()) {
  redirect('pages/home/home.php');
}

?>
<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;600&family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/the-new-css-reset/css/reset.min.css">
  <link rel="stylesheet" href="<?= h(asset('css/style.css')) ?>">
  <title>SPOTIVE</title>
</head>

<body class="start">
  <main class="start__inner">
    <div class="start__text">
      <h1 class="start__title">SPOTIVEへようこそ。</h1>
      <p class="start__lead">スポーツ観戦をもっと身近に。</p>
    </div>
    <div class="start__actions">
      <a class="start__btn" href="./pages/account/account-type.php">はじめる</a>
      <!-- 別の端末で登録した人など、印が無くてもアカウントを持っている人のために -->
      <p class="start__sub">
        アカウントをお持ちの方は<a href="./pages/account/signin.php">ログイン</a>
      </p>
      <p class="start__sub">
        <a href="./pages/account/browse.php">ログインせずに使う</a>
      </p>
      <!-- 実在のリーグ・チームの情報と誤解されないように -->
      <p class="start__note">掲載している試合・チーム・会場は、すべて架空のデモデータです。</p>
    </div>
  </main>
</body>

</html>
