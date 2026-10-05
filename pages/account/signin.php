<?php

/**
 * pages/account/signin.php
 * ログイン。
 *
 * 照合そのものは lib/account.php の account_login() が行う。
 * パスワードの照合、失敗回数の記録、連続失敗時のロック、
 * 利用停止アカウントの拒否はそちらにまとめてある。
 *
 * next= が付いていれば、ログイン後にそのページへ戻す。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/account.php';
require_once __DIR__ . '/../../lib/guest.php';

session_boot();

// ログイン後の戻り先。フォームの hidden には、GET / POST で渡された「/ から始まるパス」だけを入れる。
// 既定の戻り先（url() が返す ../../pages/map/map.php）を hidden に入れて送ると、
// ロリポップの WAF が ../ をディレクトリトラバーサルと判定して、ログインが 403 になるため
$requestedNext = safe_next(input_string($_GET, 'next') ?: input_string($_POST, 'next'), '');
$next  = $requestedNext !== '' ? $requestedNext : url('pages/map/map.php');
$error = null;

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

    redirect($next);
  } catch (AppError $e) {
    $error = $e->getMessage();
  } catch (PDOException $e) {
    error_log('[SPOTIVE] DB error: ' . $e->getMessage());
    $error = 'ただいまログインできません。時間をおいてお試しください。';
  }
}

?>
<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <title>ログイン | SPOTIVE</title>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Jost:wght@400;600&family=Noto+Sans+JP:wght@400;500;700&display=swap"
    rel="stylesheet"
  />

  <link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/the-new-css-reset/css/reset.min.css">

  <link rel="stylesheet" href="<?= h(asset('css/style.css')) ?>">
</head>

<body>

  <header class="site-header"></header>

  <main class="l-main">

    <div class="inner">

      <?php if ($error !== null) : ?>
        <p class="error"><?= h($error) ?></p>
      <?php endif; ?>

      <!-- ほかの画面から戻ってきたときのお知らせ（ゲストとしてログインできなかったときなど） -->
      <?php foreach (take_flash() as $message) : ?>
        <p class="error"><?= h($message['message']) ?></p>
      <?php endforeach; ?>

      <div class="signin">
        <h1 class="signin-title">ログイン</h1>

        <form action="signin.php" method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="next" value="<?= h($requestedNext) ?>">

          <article class="signin-form">
            <p>メールアドレス</p>
            <input
              type="email"
              id="email"
              name="email"
              value="<?= h(old($_POST, 'email')) ?>"
              placeholder="email"
              autocomplete="email"
              required
              class="signin-form-email input">
          </article>

          <article class="signin-form">
            <p>パスワード</p>
            <input
              type="password"
              id="password"
              name="password"
              placeholder="パスワードを入力"
              autocomplete="current-password"
              required
              class="signin-form-password input">
          </article>

          <button type="submit" class="next btn">ログイン</button>
        </form>

        <?php if (guest_enabled()) : ?>
          <!-- ゲスト（お試し用の共通アカウント）。パスワードは使わず、サーバー側でログインさせる -->
          <form class="signin-guest" action="guest-login.php" method="post">
            <?= csrf_field() ?>
            <p class="signin-guest-text">アカウントを作らずに試したい方</p>
            <button type="submit" class="btn signin-guest-btn">ゲストとしてログイン</button>
          </form>
        <?php endif; ?>

        <div class="signin-register">
          <p>アカウントをお持ちでない方</p>
          <a href="account-type.php" class="btn">新規登録</a>
        </div>
      </div>

    </div>

  </main>

  <footer class="site-footer"></footer>

  <script src="<?= h(asset('js/main.js')) ?>"></script>

</body>

</html>
