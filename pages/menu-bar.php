<?php

/**
 * pages/menu-bar.php
 * 画面下に固定するメニューバー。各ページから require して使う。
 *
 * 注意：読み込まれた側のパスではなく、「読み込んだページ」の URL を基準に
 * 相対パスが解決される。ここに ../ を直接書くと、pages/map/ から読んだときと
 * index.php から読んだときで指す先がずれるので、必ず url() を通すこと。
 *
 * 項目を増やす・並べ替えるときは、下の $menuItems を直せばよい。
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/support.php';

/** icon はファイル名、label はアイコンの下に出す文字 */
$menuItems = [
  ['href' => 'index.php',               'icon' => 'home.svg',     'label' => 'ホーム'],
  ['href' => 'pages/map/map.php',       'icon' => 'menu.svg',     'label' => 'メニュー'],
  ['href' => 'pages/map/map.php',       'icon' => 'map.svg',      'label' => '地図'],
  ['href' => 'pages/list/list.php',     'icon' => 'calendar.svg', 'label' => '一覧'],
  ['href' => 'pages/about/about.php',   'icon' => 'search.svg',   'label' => '探す'],
];

?>
<!-- メニュー -->
<section class="menu-bar">
  <nav class="menu-bar__nav">
    <ul class="menu-bar__list">
      <?php foreach ($menuItems as $item) : ?>
        <li class="menu-bar__item">
          <a class="menu-bar__link" href="<?= h(url($item['href'])) ?>">
            <!-- 下に文字を出すので、画像は飾り扱い（alt は空）にする -->
            <img
              class="menu-bar__icon"
              src="<?= h(url('images/icons/' . $item['icon'])) ?>"
              alt=""
              width="24"
              height="24"
            />
            <span class="menu-bar__label"><?= h($item['label']) ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </nav>
</section>
