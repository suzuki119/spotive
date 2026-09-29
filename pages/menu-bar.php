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


/**
 * pages/menu-bar.php
 * 画面下に固定するメニューバー。
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/support.php';

/**
 * 下段のメニュー
 */
$menuItems = [
  ['href' => 'pages/home/home.php',              'icon' => 'home.svg',     'label' => 'ホーム'],
  ['href' => 'pages/match/match-list.php',       'icon' => 'calendar.svg', 'label' => '日程'],
  ['href' => 'pages/map/map.php',                'icon' => 'map.svg',      'label' => '地図'],
  ['icon' => 'menu.svg',                         'label' => 'メニュー',    'type' => 'toggle'],
  ['href' => 'pages/match/match-search.php',     'icon' => 'search.svg',   'label' => '探す'],
];

/**
 * 上段の追加メニュー
 */
$subMenuItems = [
  ['href' => 'pages/setting/notification-setting.php',         'icon' => 'setting.svg',      'label' => '設定'],
  ['href' => 'pages/plus/plus.php',                'icon' => 'crown.svg',           'label' => 'プラス'],
  ['href' => 'pages/notification/notification.php', 'icon' => 'mail.svg',  'label' => 'お知らせ'],
  ['href' => 'pages/travel/travel.php',            'icon' => 'travel.svg',        'label' => '遠征'],
  ['href' => 'pages/account/account.php',          'icon' => 'account.svg',       'label' => 'アカウント'],
];

?>

<!-- メニュー -->
<section class="menu-bar">

  <!-- 追加メニュー -->
  <div class="menu-bar__sub">
    <ul class="menu-bar__sub-list">

      <?php foreach ($subMenuItems as $item) : ?>

        <li class="menu-bar__item">
          <a
            class="menu-bar__link"
            href="<?= h(url($item['href'])) ?>">

            <img
              class="menu-bar__icon"
              src="<?= h(url('images/icons/' . $item['icon'])) ?>"
              alt=""
              width="24"
              height="24" />

            <span class="menu-bar__label">
              <?= h($item['label']) ?>
            </span>

          </a>
        </li>

      <?php endforeach; ?>

    </ul>
  </div>


  <!-- 下段の通常メニュー -->
  <nav class="menu-bar__nav">

    <ul class="menu-bar__list">

      <?php foreach ($menuItems as $item) : ?>

        <li class="menu-bar__item">

          <?php if (($item['type'] ?? '') === 'toggle') : ?>

            <!-- メニュー開閉ボタン -->
            <button
              type="button"
              class="menu-bar__link menu-bar__toggle"
              aria-expanded="false">

              <img
                class="menu-bar__icon"
                src="<?= h(url('images/icons/' . $item['icon'])) ?>"
                alt=""
                width="24"
                height="24" />

              <span class="menu-bar__label">
                <?= h($item['label']) ?>
              </span>

            </button>

          <?php else : ?>

            <!-- 通常のリンク -->
            <a
              class="menu-bar__link"
              href="<?= h(url($item['href'])) ?>">

              <img
                class="menu-bar__icon"
                src="<?= h(url('images/icons/' . $item['icon'])) ?>"
                alt=""
                width="24"
                height="24" />

              <span class="menu-bar__label">
                <?= h($item['label']) ?>
              </span>

            </a>

          <?php endif; ?>

        </li>

      <?php endforeach; ?>

    </ul>

  </nav>

</section>


<script>
  const menuToggle = document.querySelector('.menu-bar__toggle');
  const subMenu = document.querySelector('.menu-bar__sub');
  const menuIcon = menuToggle.querySelector('.menu-bar__icon');

  menuToggle.addEventListener('click', () => {

    const isOpen = subMenu.classList.toggle('is-open');

    menuToggle.setAttribute('aria-expanded', isOpen);

    // SVGを一度消す
    menuToggle.classList.add('is-changing');

    // 消えてからSVGを変更
    setTimeout(() => {

      if (isOpen) {
        menuIcon.src = "<?= h(url('images/icons/close.svg')) ?>";
      } else {
        menuIcon.src = "<?= h(url('images/icons/menu.svg')) ?>";
      }

      // 新しいSVGを表示
      menuToggle.classList.remove('is-changing');

    }, 200);

  });
</script>
