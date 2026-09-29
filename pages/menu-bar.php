<?php

/**
 * pages/menu-bar.php
 * 画面下に固定するメニューバー。各ページの </body> の直前で require して使う。
 *
 *   下の段 … いつも出ている項目（ホーム・日程・マップ・メニュー・検索）
 *   上の段 … 「メニュー」を押すと開く項目（設定・プラス・お知らせ・遠征・アカウント）
 * 開閉は js/common/navigation.js が行う（このファイルが読み込む）。
 *
 * 注意：読み込まれた側のパスではなく、「読み込んだページ」の URL を基準に
 * 相対パスが解決される。ここに ../ を直接書くと、pages/map/ から読んだときと
 * index.php から読んだときで指す先がずれるので、必ず url() を通すこと。
 *
 * 項目を増やす・並べ替えるときは、下の $menuMain / $menuSub を直せばよい。
 * href が null の項目は「準備中」として押せない表示にする（href="#" は置かない）。
 */


/**
 * pages/menu-bar.php
 * 画面下に固定するメニューバー。
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/support.php';

<<<<<<< HEAD
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
  ['href' => 'pages/settings/settings.php',         'icon' => 'setting.svg',      'label' => '設定'],
  ['href' => 'pages/plus/plus.php',                'icon' => 'crown.svg',           'label' => 'プラス'],
  ['href' => 'pages/notification/notification.php', 'icon' => 'mail.svg',  'label' => 'お知らせ'],
  ['href' => 'pages/travel/travel.php',            'icon' => 'travel.svg',        'label' => '遠征'],
  ['href' => 'pages/account/account.php',          'icon' => 'account.svg',       'label' => 'アカウント'],
=======
/** icon はファイル名、label はアイコンの下に出す文字 */
$menuMain = [
  ['href' => 'pages/home/home.php',          'icon' => 'home.svg',     'label' => 'ホーム'],
  ['href' => 'pages/match/match-list.php',   'icon' => 'calendar.svg', 'label' => '日程'],
  ['href' => 'pages/map/map.php',            'icon' => 'map.svg',      'label' => 'マップ'],
  // 3 つめと 4 つめのあいだに「メニュー」の開閉ボタンが入る
  ['href' => 'pages/match/match-search.php', 'icon' => 'search.svg',   'label' => '検索'],
>>>>>>> 182d0296eb1d8489b700edfaa7b118a3a3674e48
];

$menuSub = [
  ['href' => 'pages/setting/setting.php',           'icon' => 'setting.svg', 'label' => '設定'],
  ['href' => null,                                  'icon' => 'plus.svg',    'label' => 'プラス'],   // サブスクリプション（未設計）
  ['href' => 'pages/notification/notification.php', 'icon' => 'mail.svg',    'label' => 'お知らせ'],
  ['href' => null,                                  'icon' => 'travel.svg',  'label' => '遠征'],     // 主要機能 5（後回し）
  ['href' => 'pages/setting/profile.php',           'icon' => 'account.svg', 'label' => 'アカウント'],
];

// いま開いているページの項目を強調する。URL ではなくファイルの実体で比べる
$menuCurrent = str_replace('\\', '/', (string) realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')));
$menuIsCurrent = static function (?string $href) use ($menuCurrent): bool {
  if ($href === null) {
    return false;
  }
  $file = str_replace('\\', '/', (string) realpath(dirname(__DIR__) . '/' . $href));
  return $file !== '' && $file === $menuCurrent;
};

/** 1 項目ぶんの中身（アイコンと文字） */
$menuItem = static function (array $item): void {
  ?>
  <!-- 下に文字を出すので、画像は飾り扱い（alt は空）にする -->
  <img
    class="menu-bar__icon"
    src="<?= h(url('images/icons/' . $item['icon'])) ?>"
    alt=""
    width="24"
    height="24"
  />
  <span class="menu-bar__label"><?= h($item['label']) ?></span>
  <?php
};

?>

<!-- メニュー -->
<<<<<<< HEAD
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

=======
<nav class="menu-bar" aria-label="メインメニュー">
  <ul class="menu-bar__list menu-bar__list--sub" id="menu-bar-sub" hidden>
    <?php foreach ($menuSub as $item) : ?>
      <li class="menu-bar__item">
        <?php if ($item['href'] === null) : ?>
          <span class="menu-bar__link menu-bar__link--disabled" aria-disabled="true" title="準備中">
            <?php $menuItem($item); ?>
          </span>
        <?php else : ?>
          <a
            class="menu-bar__link<?= $menuIsCurrent($item['href']) ? ' is-active' : '' ?>"
            href="<?= h(url($item['href'])) ?>"
            <?= $menuIsCurrent($item['href']) ? 'aria-current="page"' : '' ?>
          >
            <?php $menuItem($item); ?>
>>>>>>> 182d0296eb1d8489b700edfaa7b118a3a3674e48
          </a>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>

  <ul class="menu-bar__list">
    <?php foreach ($menuMain as $i => $item) : ?>
      <?php if ($i === 3) : ?>
        <li class="menu-bar__item">
          <button
            class="menu-bar__link menu-bar__toggle"
            type="button"
            aria-expanded="false"
            aria-controls="menu-bar-sub"
            data-icon-open="<?= h(url('images/icons/menu.svg')) ?>"
            data-icon-close="<?= h(url('images/icons/close.svg')) ?>"
          >
            <?php $menuItem(['icon' => 'menu.svg', 'label' => 'メニュー']); ?>
          </button>
        </li>
<<<<<<< HEAD

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
=======
      <?php endif; ?>
      <li class="menu-bar__item">
        <a
          class="menu-bar__link<?= $menuIsCurrent($item['href']) ? ' is-active' : '' ?>"
          href="<?= h(url($item['href'])) ?>"
          <?= $menuIsCurrent($item['href']) ? 'aria-current="page"' : '' ?>
        >
          <?php $menuItem($item); ?>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
</nav>
<script src="<?= h(asset('js/common/navigation.js')) ?>"></script>
>>>>>>> 182d0296eb1d8489b700edfaa7b118a3a3674e48
