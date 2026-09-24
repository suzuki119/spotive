<?php

/**
 * pages/match/match-detail.php
 * 試合詳細画面。会場アクセス・周辺施設・ホテル・天気
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
    <title>試合詳細 | SPOTIVE</title>

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
    <script src="../../js/pages/match-detail.js"></script>
  </body>
</html>
