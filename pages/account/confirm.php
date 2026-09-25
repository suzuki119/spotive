<?php

declare(strict_types=1);

session_start();

$register = $_SESSION['register'] ?? [];

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

        <?php if (!empty($register['name'])): ?>
          <article class="confirm-item">
            <p class="confirm-item-label">氏名</p>
            <p class="confirm-item-value">
              <?= htmlspecialchars($register['name'], ENT_QUOTES, 'UTF-8') ?>
            </p>
          </article>
        <?php endif; ?>


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


        <?php if (!empty($register['tel'])): ?>
          <article class="confirm-item">
            <p class="confirm-item-label">電話番号</p>
            <p class="confirm-item-value">
              <?= htmlspecialchars($register['tel'], ENT_QUOTES, 'UTF-8') ?>
            </p>
          </article>
        <?php endif; ?>


        <?php if (!empty($register['email'])): ?>
          <article class="confirm-item">
            <p class="confirm-item-label">メールアドレス</p>
            <p class="confirm-item-value">
              <?= htmlspecialchars($register['email'], ENT_QUOTES, 'UTF-8') ?>
            </p>
          </article>
        <?php endif; ?>


        <?php if (!empty($register['address'])): ?>
          <article class="confirm-item">
            <p class="confirm-item-label">住所</p>
            <p class="confirm-item-value">
              <?= htmlspecialchars($register['address'], ENT_QUOTES, 'UTF-8') ?>
            </p>
          </article>
        <?php endif; ?>


        <?php if (!empty($register['password'])): ?>
          <article class="confirm-item">
            <p class="confirm-item-label">パスワード</p>
            <p>••••••••</p>
          </article>
        <?php endif; ?>



        <form action="complete.php" method="POST">

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
