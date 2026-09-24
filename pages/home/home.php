<?php

/**
 * pages/home/home.php
 * ホーム画面。ログイン後のおすすめ・近くの試合を表示
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';

// ここでデータを取得する（HTML は書かない）

?>
<!DOCTYPE html>
<html lang="ja">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>ホーム | SPOTIVE</title>

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Jost:wght@400;600&family=Noto+Sans+JP:wght@400;500;700&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="../../css/style.css" />
  </head>

  <body>
    <header class="site-header"></header>

    <main class="l-main"></main>

    <footer class="site-footer"></footer>

    <script src="../../js/main.js"></script>
    <script src="../../js/pages/home.js"></script>
  </body>
</html>
