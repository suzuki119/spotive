<?php

/**
 * pages/account/register.php
 * 一般ユーザーの新規登録（1 段階目）。
 * メールアドレスだけ受け取り、確認リンクを送る。
 * 残りの入力は verify-email.php で行う。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/account.php';
require_once __DIR__ . '/../../lib/layout.php';

$errors = [];
$error  = null;
$sent   = false;

if (is_post()) {
  try {
    csrf_verify();
    account_start_signup($_POST);
    $sent = true;
  } catch (AppError $e) {
    $error  = $e->getMessage();
    $errors = $e->fields();
  } catch (PDOException $e) {
    error_log('[SPOTIVE] DB error: ' . $e->getMessage());
    $error = 'ただいま登録を受け付けられません。時間をおいてお試しください。';
  }
}

page_header('新規登録', '新規登録');
?>

<?php if ($sent) : ?>
  <p class="notice notice--success">
    確認メールを送信しました。メール内のリンクを開いて、登録を続けてください。
  </p>
  <p class="form-page__note">
    メールが届かない場合は、迷惑メールフォルダをご確認ください。<br />
    開発中は実際に送信せず、<code>spotive-storage/notify.log</code>（公開ディレクトリの外）に本文を書き出しています。
  </p>
<?php else : ?>
  <p class="form-page__lead">
    メールアドレスを入力してください。確認のリンクをお送りします。
  </p>

  <?php if ($error !== null) : ?>
    <p class="notice notice--error"><?= h($error) ?></p>
  <?php endif; ?>

  <form class="form" action="register.php" method="post">
    <?= csrf_field() ?>

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

    <button class="button button--primary" type="submit">確認メールを送る</button>
  </form>

  <p class="form-page__note">
    すでにアカウントをお持ちの方は
    <a href="login.php">ログイン</a> へ。<br />
    大会を主催する方は <a href="account-type.php">こちら</a>。
  </p>
<?php endif; ?>

<?php page_footer(); ?>
