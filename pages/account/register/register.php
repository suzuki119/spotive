<?php

/**
 * pages/account/register/register.php
 * アカウント登録の入力（お名前・電話番号・生年月日）。
 * 入力はセッションに溜めていき、confirm.php で確認して complete.php で登録する。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../lib/account.php';

session_boot();

$errors = [];

if (is_post()) {
  // account-type.php からメールアドレスが送られてきた場合
  if (isset($_POST['email'])) {
    $_SESSION['register']['email'] = trim((string) $_POST['email']);
  }

  // この画面から お名前・電話番号・生年月日 が送られてきた場合
  if (isset($_POST['nickname'])) {
    $input = [
      'nickname'  => trim((string) $_POST['nickname']),
      'phone'     => trim((string) ($_POST['phone'] ?? '')),
      'birthdate' => (string) ($_POST['birthdate'] ?? ''),
    ];

    // ここで確かめておかないと、最後の登録で弾かれて入力し直しになる
    $v = (new Validator($input))
      ->required('nickname', 'お名前')->length('nickname', 'お名前', 1, 50)
      ->required('phone', '電話番号')->phone('phone')
      ->required('birthdate', '生年月日')->date('birthdate', '生年月日')->minAge('birthdate', 13);

    if ($v->fails()) {
      $errors = $v->errors();
    } else {
      $_SESSION['register'] += $input;
      redirect('../pasword.php');
    }
  }
}

$saved = $_SESSION['register'] ?? [];

?>
<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>新規登録 | SPOTIVE</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/the-new-css-reset/css/reset.min.css">
  <link rel="stylesheet" href="../../../css/style.css">
</head>

<body>
  <header class="site-header"> </header>
  <main>
    <div class="inner">
      <div class="register">
        <h1 class="register-title">アカウント登録</h1>

        <form action="" method="POST">
          <article class="register-form">
            <p>お名前</p>
            <input
              type="text"
              id="nickname"
              name="nickname"
              value="<?= h(old($saved, 'nickname')) ?>"
              placeholder="田中　太郎"
              maxlength="50"
              required
              class="register-form-name input">
            <?php if (isset($errors['nickname'])) : ?>
              <p class="field-error"><?= h($errors['nickname']) ?></p>
            <?php endif; ?>
          </article>

          <article class="register-form">
            <p>電話番号</p>
            <input
              type="tel"
              id="phone"
              name="phone"
              value="<?= h(old($saved, 'phone')) ?>"
              placeholder="09012345678"
              required
              class="register-form-tel input">
            <?php if (isset($errors['phone'])) : ?>
              <p class="field-error"><?= h($errors['phone']) ?></p>
            <?php endif; ?>
          </article>

          <article class="register-form">
            <p>生年月日</p>
            <input
              type="date"
              id="birthdate"
              name="birthdate"
              value="<?= h(old($saved, 'birthdate')) ?>"
              required
              class="register-form-birthdate input">
            <?php if (isset($errors['birthdate'])) : ?>
              <p class="field-error"><?= h($errors['birthdate']) ?></p>
            <?php endif; ?>
          </article>
          <button type="button" class="verification btn">本人確認</button>
          <button type="submit" class="next btn">次へ</button>
          <button type="button" class="back btn">戻る</button>
        </form>
      </div>
    </div>
  </main>
  <footer class="site-footer">

  </footer>
  <script src="../../../js/main.js"></script>
</body>

</html>
