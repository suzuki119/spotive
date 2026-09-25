<?php

/**
 * pages/setting/profile.php
 * ユーザー情報の編集。
 *
 * ここで変えられるのは表示名と電話番号だけ。
 * メールアドレスの変更は本人のものか確かめ直す手順が必要なので、
 * パスワードの変更は今のパスワードの確認が必要なので、まだ作っていない。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/account.php';
require_once __DIR__ . '/../../lib/layout.php';

$user = require_login();

$errors = [];
$error  = null;

if (is_post()) {
  try {
    csrf_verify();

    $input = [
      'nickname' => trim((string) ($_POST['nickname'] ?? '')),
      'phone'    => trim((string) ($_POST['phone'] ?? '')),
    ];

    (new Validator($input))
      ->required('nickname', 'お名前')->length('nickname', 'お名前', 1, 50)
      ->required('phone', '電話番号')->phone('phone')
      ->validate();

    $phone = Validator::normalizePhone($input['phone']);

    // 電話番号は 1 人 1 つ。ほかの人が使っていないか確かめる
    $taken = db_one(
      'SELECT id FROM users WHERE phone_e164 = :p AND id <> :id AND deleted_at IS NULL',
      ['p' => $phone, 'id' => $user['id']]
    );
    if ($taken !== null) {
      throw new AppError('この電話番号は既に使われています。', ['phone' => 'この電話番号は既に使われています。']);
    }

    // 電話番号を変えたら、確認済みの印は外す（別の番号になったため）
    $changedPhone = $phone !== $user['phone_e164'];

    db_update('users', [
      'nickname'          => $input['nickname'],
      'phone_e164'        => $phone,
      'phone_verified_at' => $changedPhone ? null : $user['phone_verified_at'],
    ], 'id = :id', ['id' => $user['id']]);

    audit_log((int) $user['id'], 'user.profile_updated', 'user', (int) $user['id']);

    flash('ユーザー情報を更新しました。', 'success');
    redirect('profile.php');
  } catch (AppError $e) {
    $error  = $e->getMessage();
    $errors = $e->fields();
  } catch (PDOException $e) {
    error_log('[SPOTIVE] DB error: ' . $e->getMessage());
    $error = 'ただいま更新できません。時間をおいてお試しください。';
  }
}

// 入力し直しのときは送られてきた値、それ以外は今の登録内容を出す
$form = is_post() ? $_POST : [
  'nickname' => $user['nickname'],
  'phone'    => $user['phone_e164'],
];

page_header('ユーザー情報の編集', 'ユーザー情報の編集');
?>

<?php if ($error !== null) : ?>
  <p class="notice notice--error"><?= h($error) ?></p>
<?php endif; ?>

<form class="form" action="profile.php" method="post">
  <?= csrf_field() ?>

  <div class="form__field">
    <label class="form__label" for="nickname">お名前</label>
    <input
      class="form__input"
      type="text"
      id="nickname"
      name="nickname"
      value="<?= h(old($form, 'nickname')) ?>"
      maxlength="50"
      required
    />
    <p class="form__hint">画面に表示される名前です。</p>
    <?php field_error($errors, 'nickname'); ?>
  </div>

  <div class="form__field">
    <label class="form__label" for="phone">電話番号</label>
    <input
      class="form__input"
      type="tel"
      id="phone"
      name="phone"
      value="<?= h(old($form, 'phone')) ?>"
      placeholder="09012345678"
      required
    />
    <?php field_error($errors, 'phone'); ?>
  </div>

  <button class="button button--primary" type="submit">保存する</button>
</form>

<h2 class="form-page__subtitle">変更できない項目</h2>

<dl class="detail">
  <dt class="detail__label">メールアドレス</dt>
  <dd class="detail__value">
    <?= h((string) $user['email']) ?>
    <?= $user['email_verified_at'] !== null ? '（確認済み）' : '（未確認）' ?>
  </dd>

  <dt class="detail__label">生年月日</dt>
  <dd class="detail__value"><?= h((string) $user['birthdate']) ?></dd>

  <dt class="detail__label">信頼レベル</dt>
  <dd class="detail__value"><?= h(level_label((int) $user['trust_level'])) ?></dd>
</dl>

<p class="form-page__note">
  メールアドレスとパスワードの変更、退会はまだ作っていません。<br />
  <a href="setting.php">マイページへ戻る</a>
</p>

<?php page_footer(); ?>
