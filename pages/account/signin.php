<?php

/**
 * pages/account/login.php
 * 一般ユーザーのログイン。
 * next= が付いていれば、ログイン後にそのページへ戻す。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/account.php';
require_once __DIR__ . '/../../lib/layout.php';

$next   = safe_next($_GET['next'] ?? $_POST['next'] ?? null, url('pages/setting/setting.php'));
$error  = null;
$errors = [];

if (is_logged_in()) {
  redirect($next);
}

if (is_post()) {
  try {
    csrf_verify();

    (new Validator($_POST))
      ->required('email', 'メールアドレス')->email('email')
      ->required('password', 'パスワード')
      ->validate();

    $user = account_login((string) $_POST['email'], (string) $_POST['password']);
    login_user((int) $user['id']);

    flash('ログインしました。', 'success');
    redirect($next);
  } catch (AppError $e) {
    $error  = $e->getMessage();
    $errors = $e->fields();
  } catch (PDOException $e) {
    error_log('[SPOTIVE] DB error: ' . $e->getMessage());
    $error = 'ただいまログインできません。時間をおいてお試しください。';
  }
}

page_header('ログイン', 'ログイン');
?>

<?php if ($error !== null) : ?>
  <p class="notice notice--error"><?= h($error) ?></p>
<?php endif; ?>

<form class="form" action="login.php" method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="next" value="<?= h($next) ?>" />

  <div class="form__field">
    <label class="form__label" for="email">メールアドレス</label>
    <input
      class="form__input"
      type="email"
      id="email"
      name="email"
      value="<?= h(old($_POST, 'email')) ?>"
      autocomplete="email"
      required
    />
    <?php field_error($errors, 'email'); ?>
  </div>

  <div class="form__field">
    <label class="form__label" for="password">パスワード</label>
    <input
      class="form__input"
      type="password"
      id="password"
      name="password"
      autocomplete="current-password"
      required
    />
    <?php field_error($errors, 'password'); ?>
  </div>

  <button class="button button--primary" type="submit">ログイン</button>
</form>

<p class="form-page__note">
  アカウントをお持ちでない方は <a href="register.php">新規登録</a> へ。
</p>

<?php page_footer(); ?>
