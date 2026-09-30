<?php

/**
 * pages/setting/account-setting.php
 * アカウント情報・退会
 *
 * 表示しているのは users の中身と、いま有効なセッションの数。
 * 退会（アカウントの削除）は account-delete.php で行う。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/account.php';
require_once __DIR__ . '/../../lib/layout.php';

$user = require_login();

$sessions = (int) db_one(
  'SELECT COUNT(*) AS c FROM auth_sessions
    WHERE user_id = :u AND revoked_at IS NULL AND expires_at > NOW()',
  ['u' => $user['id']]
)['c'];

page_header('アカウント設定', 'アカウント設定');
?>

<dl class="detail">
  <dt class="detail__label">ニックネーム</dt>
  <dd class="detail__value"><?= h((string) $user['nickname']) ?></dd>

  <dt class="detail__label">メールアドレス</dt>
  <dd class="detail__value">
    <?= h((string) $user['email']) ?>
    <?= $user['email_verified_at'] !== null ? '（確認済み）' : '（未確認）' ?>
  </dd>

  <dt class="detail__label">電話番号</dt>
  <dd class="detail__value">
    <?= h((string) $user['phone_e164']) ?>
    <?= $user['phone_verified_at'] !== null ? '（確認済み）' : '（未確認）' ?>
  </dd>

  <dt class="detail__label">信頼レベル</dt>
  <dd class="detail__value"><?= h(level_label((int) $user['trust_level'])) ?></dd>

  <dt class="detail__label">最終ログイン</dt>
  <dd class="detail__value"><?= h(format_datetime((string) $user['last_login_at'])) ?></dd>

  <dt class="detail__label">ログイン中の端末</dt>
  <dd class="detail__value"><?= $sessions ?>件</dd>
</dl>

<p class="form-page__note">
  メールアドレスの変更はまだ作っていません。<br />
  <a href="setting.php">マイページへ戻る</a>
</p>

<h2 class="form-page__subtitle">アカウントの削除</h2>
<p class="form-page__note">
  アカウントを削除すると、登録内容は元に戻せません。<br />
  <a href="account-delete.php">アカウントを削除する</a>
</p>

<?php page_footer();
