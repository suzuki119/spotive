<?php

/**
 * pages/account/confirm.php
 * 入力内容の確認。ここで規約に同意して complete.php へ送る。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/support.php';

session_boot();

$register = $_SESSION['register'] ?? [];

// 入力が足りないまま直接開かれたら、最初の画面へ戻す
foreach (['email', 'nickname', 'phone', 'birthdate', 'password'] as $key) {
  if (($register[$key] ?? '') === '') {
    redirect('account-type.php');
  }
}

?>

<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>登録内容の確認 | SPOTIVE</title>

  <link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/the-new-css-reset/css/reset.min.css">

  <link rel="stylesheet" href="../../css/style.css">
</head>

<body>

  <main>

    <div class="inner">

      <div class="confirm">

        <h1 class="confirm-title">
          登録内容の確認
        </h1>

        <article class="confirm-item">
          <p class="confirm-item-label">お名前</p>
          <p class="confirm-item-value"><?= h($register['nickname']) ?></p>
        </article>


        <?php if (!empty($register['representative_name'])): ?>
          <article class="confirm-item">
            <p class="confirm-item-label">代表者名</p>
            <p class="confirm-item-value">
              <?= htmlspecialchars($register['representative_name'], ENT_QUOTES, 'UTF-8') ?>
            </p>
          </article>
        <?php endif; ?>


        <?php if (!empty($register['company_name'])): ?>
          <article class="confirm-item">
            <p class="confirm-item-label">会社・団体名</p>
            <p class="confirm-item-value">
              <?= htmlspecialchars($register['company_name'], ENT_QUOTES, 'UTF-8') ?>
            </p>
          </article>
        <?php endif; ?>


        <article class="confirm-item">
          <p class="confirm-item-label">電話番号</p>
          <p class="confirm-item-value"><?= h($register['phone']) ?></p>
        </article>

        <article class="confirm-item">
          <p class="confirm-item-label">生年月日</p>
          <p class="confirm-item-value"><?= h($register['birthdate']) ?></p>
        </article>


        <article class="confirm-item">
          <p class="confirm-item-label">メールアドレス</p>
          <p class="confirm-item-value"><?= h($register['email']) ?></p>
        </article>


        <?php if (!empty($register['address'])): ?>
          <article class="confirm-item">
            <p class="confirm-item-label">住所</p>
            <p class="confirm-item-value">
              <?= htmlspecialchars($register['address'], ENT_QUOTES, 'UTF-8') ?>
            </p>
          </article>
        <?php endif; ?>


        <article class="confirm-item">
          <p class="confirm-item-label">パスワード</p>
          <p>••••••••</p>
        </article>



        <?php foreach (take_flash() as $message) : ?>
          <p class="notice notice--<?= h($message['type']) ?>"><?= h($message['message']) ?></p>
        <?php endforeach; ?>

        <form action="complete.php" method="POST">
          <?= csrf_field() ?>

          <div class="confirm-agree">
            <label class="form__check">
              <input type="checkbox" name="agree_terms" value="1" required />
              <span>利用規約に同意します</span>
            </label>
            <label class="form__check">
              <input type="checkbox" name="agree_privacy" value="1" required />
              <span>プライバシーポリシーに同意します</span>
            </label>
          </div>

          <button type="submit" class="next btn confirm-btn ">
            この内容で登録する
          </button>

        </form>


        <button
          type="button"
          class="back btn"
          onclick="history.back()">
          戻る
        </button>

      </div>

    </div>

  </main>

</body>

</html>
