<?php

/**
 * index.php
 * 入口。
 *
 *   ログインしている   … ホーム（pages/home/home.php）
 *   ログインしていない … index.php に留まる
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
} ?>
<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/the-new-css-reset/css/reset.min.css">
  <link rel="stylesheet" href="css/style.css">
  <title>Document</title>
</head>

<body class="start">
  <div class="start-text">
    <h1 class="start-title">SPOTIVEへようこそ。</h1>
    <p class="start-letter">スポーツ観戦をもっと身近に。</p>
  </div>
  <section class="start-gradation">
    <a href="./pages/account/account-type.php">はじめる</a>
  </section>

</body>

</html>
