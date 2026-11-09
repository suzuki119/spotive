<?php

/**
 * pages/plus/plus.php
 * SPOTIVE PLUS＋（サブスク）の紹介。メニューバーの「プラス」とマイページから来る。
 *
 * まだ課金はできないので、プランは選べるが「PLUS＋にアップグレード」は押せない（準備中）。
 * プランの選択は radio にして、選んでいる見た目は CSS（:has(:checked)）だけで切り替える。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/support.php';
require_once __DIR__ . '/../../lib/icon.php';

/** プラン。料金は税込の円 */
$plans = [
  ['id' => 'monthly', 'name' => 'PLUS+ Monthly', 'price' => 550,  'unit' => '月'],
  ['id' => 'yearly',  'name' => 'PLUS+ Yearly',  'price' => 5000, 'unit' => '年'],
];

/** 登録するとできること */
$benefits = ['遠征サポート機能', '高度な検索フィルター', '推しチーム登録上限解除', '広告完全非表示'];

?>
<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SPOTIVE PLUS＋ | SPOTIVE</title>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Jost:wght@400;600&family=Noto+Sans+JP:wght@400;500;700&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/the-new-css-reset/css/reset.min.css">
  <link rel="stylesheet" href="<?= h(asset('css/style.css')) ?>">
</head>

<body>
  <main class="inner">
    <div class="plus">
      <!-- 閉じる：来た画面があればそこへ（js/main.js の .setting-back）、URL を直接開いたときはホームへ -->
      <a class="plus__close setting-back" href="<?= h(url('pages/home/home.php')) ?>" aria-label="閉じる">
        <?= icon_html('close') ?>
      </a>

      <h1 class="plus__title">SPOTIVE PLUS+</h1>
      <p class="plus__lead">
        月額<em class="plus__em plus__em--number">550</em>円で、<em class="plus__em">スポーツ観戦</em>を<br>
        もっと<em class="plus__em">便利</em>に、もっと<em class="plus__em">身近</em>に！
      </p>

      <section class="plus__benefits">
        <h2 class="plus__benefits-title">PLUS＋に登録すると、<br>すべての機能が使い放題！</h2>
        <ul class="plus__benefit-list">
          <?php foreach ($benefits as $benefit) : ?>
            <li class="plus__benefit"><?= icon_html('check') ?><?= h($benefit) ?></li>
          <?php endforeach; ?>
        </ul>
      </section>

      <fieldset class="plus__plans">
        <legend class="plus__plans-title">プランを選択</legend>
        <?php foreach ($plans as $i => $plan) : ?>
          <label class="plus__plan">
            <input class="plus__plan-input visually-hidden" type="radio" name="plan" value="<?= h($plan['id']) ?>" <?= $i === 0 ? 'checked' : '' ?> />
            <span class="plus__plan-name"><?= h($plan['name']) ?></span>
            <span class="plus__plan-check"><?= icon_html('check') ?></span>
            <span class="plus__plan-price">
              ￥<?= h(number_format($plan['price'])) ?><span class="plus__plan-unit">/<?= h($plan['unit']) ?></span>
            </span>
          </label>
        <?php endforeach; ?>
      </fieldset>

      <!-- まだ課金できないので押せない -->
      <button class="plus__upgrade" type="button" disabled>
        PLUS+にアップグレード
        <span class="plus__soon">準備中</span>
      </button>
    </div>
  </main>

  <script src="<?= h(asset('js/main.js')) ?>"></script>
  <?php require __DIR__ . '/../menu-bar.php'; ?>
</body>

</html>
