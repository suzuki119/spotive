<?php

/**
 * pages/account/complete.php
 * 登録の実行と完了画面。
 *
 * 登録そのものは lib/account.php の account_register() が行う。
 * 重複の確認、メールアドレスの正規化、電話番号の E.164 変換、
 * パスワードのハッシュ化、規約同意の記録、監査ログはそちらにまとめてある。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/account.php';

session_boot();

// 直接開かれたときは確認画面へ戻す
if (!is_post()) {
  redirect('confirm.php');
}

$register    = $_SESSION['register'] ?? [];
$nickname    = (string) ($register['nickname'] ?? '');
$isOrganizer = ($register['intent'] ?? '') === 'organizer';

// ログイン後に主催者登録へ戻すための next=。
// safe_next() は「/ から始まるパス」しか通さないので、サイトのルートからのパスで作る
$appRoot     = rtrim(str_replace('\\', '/', dirname((string) $_SERVER['SCRIPT_NAME'], 3)), '/');
$signinUrl   = $isOrganizer
  ? 'signin.php?next=' . urlencode($appRoot . '/pages/organizer/register.php')
  : 'signin.php';

try {
  csrf_verify();

  account_register([
    'email'         => $register['email'] ?? '',
    'nickname'      => $nickname,
    'phone'         => $register['phone'] ?? '',
    'birthdate'     => $register['birthdate'] ?? '',
    'password'      => $register['password'] ?? '',
    'agree_terms'   => $_POST['agree_terms'] ?? '',
    'agree_privacy' => $_POST['agree_privacy'] ?? '',
  ]);

  // 入力の控えは残さない（パスワードを持ち続けないため）
  unset($_SESSION['register']);
} catch (AppError $e) {
  flash($e->getMessage(), 'error');
  foreach ($e->fields() as $message) {
    flash($message, 'error');
  }
  redirect('confirm.php');
} catch (PDOException $e) {
  error_log('[SPOTIVE] DB error: ' . $e->getMessage());
  flash('ただいま登録を受け付けられません。時間をおいてお試しください。', 'error');
  redirect('confirm.php');
}

?>
<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>登録完了 | SPOTIVE</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/the-new-css-reset/css/reset.min.css">
  <link rel="stylesheet" href="<?= h(asset('css/style.css')) ?>">
</head>

<body>
  <main>
    <div class="inner">
      <div class="complete">

        <h1 class="complete-title">登録が完了しました</h1>

        <p class="complete-lead">
          <?= h($nickname) ?> さん、ようこそ SPOTIVE へ。
        </p>

        <p class="complete-note">
          ご登録のメールアドレスに確認メールをお送りしました。<br />
          メール内のリンクを開くと、すべての機能が使えるようになります。
        </p>

        <?php if ($isOrganizer) : ?>
          <p class="complete-note">
            主催者として大会を掲載するには、続けて次の手順が必要です。
          </p>
          <ol class="complete-steps">
            <li>確認メールのリンクを開く</li>
            <li>本人確認をする</li>
            <li>主催者登録（団体・学校・企業などの情報）を申請する</li>
            <li>運営の審査で承認されると、主催者として大会を掲載できます</li>
          </ol>
        <?php endif; ?>

        <p class="complete-note">
          <a href="<?= h($signinUrl) ?>">ログインする</a>
        </p>

      </div>
    </div>
  </main>

  <script src="<?= h(asset('js/main.js')) ?>"></script>
</body>

</html>
