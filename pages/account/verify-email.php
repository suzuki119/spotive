<?php

/**
 * pages/account/verify-email.php
 * 登録メールのリンクを開いた先（2 段階目）。
 * トークンを確かめてから、残りの情報を入力してもらいアカウントを作る。
 *
 * 既にアカウントがある人のメール確認リンクも、ここで消化する。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/account.php';
require_once __DIR__ . '/../../lib/layout.php';

$token   = trim((string) ($_POST['token'] ?? $_GET['token'] ?? ''));
$errors  = [];
$error   = null;
$email   = null;
$expired = false;

try {
  if (is_post()) {
    csrf_verify();
    $userId = account_complete_signup($token, $_POST);
    login_user($userId);
    flash('登録が完了しました。ようこそ SPOTIVE へ。', 'success');
    redirect(url('pages/setting/setting.php'));
  }

  // GET: 既存ユーザーのメール確認リンクなら、ここで消化して終わり
  $verifiedUserId = account_verify_email($token);
  if ($verifiedUserId !== null) {
    flash('メールアドレスを確認しました。', 'success');
    redirect(url('pages/account/login.php'));
  }

  // 登録待ちのトークンなら、続きの入力へ進む
  $email = (string) account_pending_signup($token)['target_email'];
} catch (AppError $e) {
  $error   = $e->getMessage();
  $errors  = $e->fields();
  $expired = $errors === [];   // 項目別エラーが無い＝リンクそのものが無効
  if (!$expired) {
    $email = (string) account_pending_signup($token)['target_email'];
  }
} catch (PDOException $e) {
  error_log('[SPOTIVE] DB error: ' . $e->getMessage());
  $error   = 'ただいま登録を受け付けられません。時間をおいてお試しください。';
  $expired = true;
}

page_header('登録を完了する', '登録を完了する');
?>

<?php if ($expired) : ?>
  <p class="notice notice--error"><?= h((string) $error) ?></p>
  <p class="form-page__note">
    <a href="register.php">もう一度メールアドレスを入力する</a>
  </p>
<?php else : ?>
  <?php if ($error !== null) : ?>
    <p class="notice notice--error"><?= h($error) ?></p>
  <?php endif; ?>

  <p class="form-page__lead">
    <strong><?= h((string) $email) ?></strong> の確認ができました。<br />
    残りの情報を入力すると登録が完了します。
  </p>

  <form class="form" action="verify-email.php" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= h($token) ?>" />

    <div class="form__field">
      <label class="form__label" for="nickname">ニックネーム</label>
      <input
        class="form__input"
        type="text"
        id="nickname"
        name="nickname"
        value="<?= h(old($_POST, 'nickname')) ?>"
        maxlength="50"
        required
      />
      <p class="form__hint">画面に表示される名前です。本名でなくてかまいません。</p>
      <?php field_error($errors, 'nickname'); ?>
    </div>

    <div class="form__field">
      <label class="form__label" for="phone">電話番号（携帯）</label>
      <input
        class="form__input"
        type="tel"
        id="phone"
        name="phone"
        value="<?= h(old($_POST, 'phone')) ?>"
        placeholder="09012345678"
        autocomplete="tel"
        required
      />
      <?php field_error($errors, 'phone'); ?>
    </div>

    <div class="form__field">
      <label class="form__label" for="birthdate">生年月日</label>
      <input
        class="form__input"
        type="date"
        id="birthdate"
        name="birthdate"
        value="<?= h(old($_POST, 'birthdate')) ?>"
        required
      />
      <p class="form__hint">13歳未満の方はご利用いただけません。</p>
      <?php field_error($errors, 'birthdate'); ?>
    </div>

    <div class="form__field">
      <label class="form__label" for="password">パスワード</label>
      <input
        class="form__input"
        type="password"
        id="password"
        name="password"
        autocomplete="new-password"
        required
      />
      <p class="form__hint">10文字以上で、英字と数字（または記号）を混ぜてください。</p>
      <?php field_error($errors, 'password'); ?>
    </div>

    <div class="form__field form__field--check">
      <label class="form__check">
        <input type="checkbox" name="agree_terms" value="1" <?= old($_POST, 'agree_terms') !== '' ? 'checked' : '' ?> />
        <span>利用規約に同意します</span>
      </label>
      <?php field_error($errors, 'agree_terms'); ?>

      <label class="form__check">
        <input type="checkbox" name="agree_privacy" value="1" <?= old($_POST, 'agree_privacy') !== '' ? 'checked' : '' ?> />
        <span>プライバシーポリシーに同意します</span>
      </label>
      <?php field_error($errors, 'agree_privacy'); ?>
    </div>

    <button class="button button--primary" type="submit">登録を完了する</button>
  </form>
<?php endif; ?>

<?php page_footer(); ?>
