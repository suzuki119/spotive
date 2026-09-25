<?php

declare(strict_types=1);

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $organizerType = $_POST['organizer_type'] ?? '';

  if ($organizerType !== '') {

    // 選択した主催者タイプを保存
    $_SESSION['register']['organizer_type'] = $organizerType;

    // 入力画面へ
    header('Location: organizer-input.php');
    exit;
  }
}

?>
<!DOCTYPE html>
<html lang="ja">

<head>

  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>主催者登録 | SPOTIVE</title>

  <link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/the-new-css-reset/css/reset.min.css">

  <link rel="stylesheet" href="../../../css/style.css">

</head>

<body>

  <main class="l-main">

    <div class="inner">

      <div class="account-type">

        <h1 class="account-type-title">主催者アカウント登録</h1>

        <p>
          登録するアカウントの種類を
          <br>
          選択してください
        </p>

        <form action="" method="POST">

          <div class="organizer">

            <label>
              <input
                type="radio"
                name="organizer_type"
                value="individual"
                required>
              <span>個人</span>
            </label>

            <label>
              <input
                type="radio"
                name="organizer_type"
                value="team">
              <span>団体・チーム</span>
            </label>

            <label>
              <input
                type="radio"
                name="organizer_type"
                value="school">
              <span>学校</span>
            </label>

            <label>
              <input
                type="radio"
                name="organizer_type"
                value="company">
              <span>企業</span>
            </label>

            <label>
              <input
                type="radio"
                name="organizer_type"
                value="association">
              <span>スポーツ協会・連盟</span>
            </label>
          </div>
          <button
            type="submit"
            class="next btn">
            次へ
          </button>

          <button
            type="button"
            class="back btn"
            onclick="history.back()">
            戻る
          </button>

        </form>

      </div>

    </div>

  </main>

</body>

</html>
