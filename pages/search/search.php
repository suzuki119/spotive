<?php

/**
 * pages/search/search.php
 * 検索画面（作成中）。いまはどこからもリンクされていない。
 * メニューバーの「検索」は pages/match/match-search.php を開く。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/support.php';   // h() / asset() のため

?>
<!DOCTYPE html>
 <html lang="ja">

 <head>
   <meta charset="UTF-8" />
   <meta name="viewport" content="width=device-width, initial-scale=1.0" />
   <title>検索 | SPOTIVE</title>

   <link rel="preconnect" href="https://fonts.googleapis.com" />
   <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
   <link
     href="https://fonts.googleapis.com/css2?family=Jost:wght@400;600&family=Noto+Sans+JP:wght@400;500;700&display=swap"
     rel="stylesheet" />
   <link rel="stylesheet" href="<?= h(asset('css/style.css')) ?>" />
 </head>

 <body>
   <?php
    $appHeader = ['title' => 'SEARCH'];
    require __DIR__ . '/../app-header.php';
    ?>
   <?php require __DIR__ . '/../menu-bar.php'; ?>
 </body>

 </html>
