<?php

/**
 * pages/organizer/login.php
 * 主催者アカウントのログイン画面。
 *
 * SPOTIVE のアカウントは 1 種類なので、ログインの仕組みは一般ユーザーと同じ。
 * この画面は「ログイン後に主催者ページへ戻る」入口として分けてある。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/account.php';
require_once __DIR__ . '/../../lib/layout.php';

$next   = url('pages/organizer/dashboard.php');
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

page_header('主催者ログイン', '主催者ログイン');
?>

<?php if ($error !== null) : ?>
  <p class="notice notice--error"><?= h($error) ?></p>
<?php endif; ?>

<form class="form" action="login.php" method="post">
  <?= csrf_field() ?>

  <div class="form__field">
    <label class="form__label" for="email">メールアドレス</label>
    <input class="form__input" type="email" id="email" name="email"
           value="<?= h(old($_POST, 'email')) ?>" autocomplete="email" required />
    <?php field_error($errors, 'email'); ?>
  </div>

  <div class="form__field">
    <label class="form__label" for="password">パスワード</label>
    <input class="form__input" type="password" id="password" name="password"
           autocomplete="current-password" required />
    <?php field_error($errors, 'password'); ?>
  </div>

  <button class="button button--primary" type="submit">ログイン</button>
</form>

<p class="form-page__note">
  まだ主催者認証を受けていない方は <a href="register.php">主催者登録</a> へ。
</p>

<?php page_footer(); ?>
