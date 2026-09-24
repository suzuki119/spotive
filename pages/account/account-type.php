<?php

/**
 * pages/account/account-type.php
 * アカウント種別の選択画面。一般ユーザー / 主催者アカウントを選ぶ
 *
 * SPOTIVE のアカウントは 1 種類で、主催者は「一般アカウント＋主催者認証（Lv.3）」。
 * ここでは、その流れの入口を示す。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/layout.php';

$user = current_user();

page_header('アカウント種別の選択', 'どちらで始めますか？');
?>

<div class="choice">
  <section class="choice__item">
    <h2 class="choice__title">観戦する</h2>
    <p class="choice__lead">
      試合を探して、お気に入りに登録できます。メールアドレスだけで始められます。
    </p>
    <a class="button button--primary" href="register.php">新規登録へ</a>
  </section>

  <section class="choice__item">
    <h2 class="choice__title">大会を主催する</h2>
    <p class="choice__lead">
      大会を掲載するには、次の順に進みます。
    </p>
    <ol class="choice__steps">
      <li>アカウント登録（Lv.1）</li>
      <li>本人確認（Lv.2）</li>
      <li>主催者認証（Lv.3）</li>
      <li>大会の登録・確認（Lv.4）</li>
    </ol>
    <?php if ($user === null) : ?>
      <a class="button button--primary" href="register.php">まず登録する</a>
    <?php else : ?>
      <a class="button button--primary" href="<?= h(base_path()) ?>/pages/organizer/register.php">
        主催者認証を申請する
      </a>
    <?php endif; ?>
  </section>
</div>

<?php page_footer(); ?>
