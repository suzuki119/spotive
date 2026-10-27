<?php

/**
 * pages/setting/password-setting.php
 * パスワードの変更。設定画面（setting.php）の「パスワード」から来る。
 *
 * 今のパスワードで本人か確かめてから変える。変えたら、ほかの端末のログインは切る。
 * 処理の中身は lib/account.php の account_change_password()。
 * ゲスト（お試し用の共通アカウント）はパスワードでログインしないので、使わせない。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/account.php';
require_once __DIR__ . '/../../lib/guest.php';
require_once __DIR__ . '/../../lib/layout.php';

// ログインせずに使っている人は、ゲストにログインさせてから開く（そのあと下で断る）
guest_auto_login();
$user = require_login();
guest_forbid($user, 'ゲストのパスワードは変更できません。', 'pages/setting/account-setting.php');

$errors = [];
$error  = null;

if (is_post()) {
  try {
    csrf_verify();
    account_change_password($user, [
      'current'          => (string) ($_POST['current'] ?? ''),
      'password'         => (string) ($_POST['password'] ?? ''),
      'password_confirm' => (string) ($_POST['password_confirm'] ?? ''),
    ]);

    // 設定画面（setting.php）はお知らせを出さないので、アカウントの画面に戻す
    flash('パスワードを変更しました。ほかの端末ではログインし直してください。', 'success');
    redirect('account-setting.php');
  } catch (AppError $e) {
    $error  = $e->getMessage();
    $errors = $e->fields();
  } catch (PDOException $e) {
    error_log('[SPOTIVE] DB error: ' . $e->getMessage());
    $error = 'ただいま変更できません。時間をおいてお試しください。';
  }
}

setting_page_header('パスワード');
?>
        <section>
          <h2>パスワード</h2>

          <?php if ($error !== null) : ?>
            <p class="notice notice--error"><?= h($error) ?></p>
          <?php endif; ?>

          <!-- パスワードは入力し直しのときも値を戻さない（画面に残さないため） -->
          <form class="setting-form" action="password-setting.php" method="post">
            <?= csrf_field() ?>

            <div class="setting-form__field">
              <label class="setting-form__label" for="current">今のパスワード</label>
              <input class="form__input" type="password" id="current" name="current" autocomplete="current-password" required />
              <?php field_error($errors, 'current'); ?>
            </div>

            <div class="setting-form__field">
              <label class="setting-form__label" for="password">新しいパスワード</label>
              <input class="form__input" type="password" id="password" name="password" autocomplete="new-password" minlength="10" required />
              <p class="setting-form__hint">10文字以上で、英字と数字（または記号）を混ぜてください。</p>
              <?php field_error($errors, 'password'); ?>
            </div>

            <div class="setting-form__field">
              <label class="setting-form__label" for="password_confirm">新しいパスワード（確認）</label>
              <input class="form__input" type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required />
              <?php field_error($errors, 'password_confirm'); ?>
            </div>

            <p class="setting-form__hint">変更すると、ほかの端末ではログアウトされます。</p>

            <button class="setting-form__submit" type="submit">変更する</button>
          </form>
        </section>

<?php setting_page_footer();
