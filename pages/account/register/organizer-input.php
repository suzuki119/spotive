<?php

declare(strict_types=1);

session_start();

$organizerType = $_SESSION['register']['organizer_type'] ?? '';

if ($organizerType === '') {
  header('Location: organizer-register.php');
  exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // 個人
  if ($organizerType === 'individual') {

    $_SESSION['register']['name'] = $_POST['name'] ?? '';
    $_SESSION['register']['tel'] = $_POST['tel'] ?? '';
    $_SESSION['register']['email'] = $_POST['email'] ?? '';

    // 団体・チーム
  } elseif ($organizerType === 'team') {

    $_SESSION['register']['team_name'] = $_POST['team_name'] ?? '';
    $_SESSION['register']['representative_name'] = $_POST['representative_name'] ?? '';
    $_SESSION['register']['tel'] = $_POST['tel'] ?? '';
    $_SESSION['register']['email'] = $_POST['email'] ?? '';
    $_SESSION['register']['address'] = $_POST['address'] ?? '';

    // 学校
  } elseif ($organizerType === 'school') {

    $_SESSION['register']['school_name'] = $_POST['school_name'] ?? '';
    $_SESSION['register']['contact_name'] = $_POST['contact_name'] ?? '';
    $_SESSION['register']['tel'] = $_POST['tel'] ?? '';
    $_SESSION['register']['email'] = $_POST['email'] ?? '';
    $_SESSION['register']['address'] = $_POST['address'] ?? '';

    // 企業
  } elseif ($organizerType === 'company') {

    $_SESSION['register']['company_name'] = $_POST['company_name'] ?? '';
    $_SESSION['register']['contact_name'] = $_POST['contact_name'] ?? '';
    $_SESSION['register']['tel'] = $_POST['tel'] ?? '';
    $_SESSION['register']['email'] = $_POST['email'] ?? '';
    $_SESSION['register']['corporate_number'] = $_POST['corporate_number'] ?? '';
    $_SESSION['register']['address'] = $_POST['address'] ?? '';

    // スポーツ協会・連盟
  } elseif ($organizerType === 'association') {

    $_SESSION['register']['association_name'] = $_POST['association_name'] ?? '';
    $_SESSION['register']['representative_name'] = $_POST['representative_name'] ?? '';
    $_SESSION['register']['tel'] = $_POST['tel'] ?? '';
    $_SESSION['register']['email'] = $_POST['email'] ?? '';
    $_SESSION['register']['area'] = $_POST['area'] ?? '';
  }

  header('Location: ../pasword.php');
  exit;
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/the-new-css-reset/css/reset.min.css">
  <link rel="stylesheet" href="../../../css/style.css">

  <title>主催者アカウント登録</title>
</head>

<body>

  <main class="l-main">

    <div class="inner">
      <div class="organizer-input">

        <h1 class=organizer-input-title>主催者アカウント登録</h1>

        <?php if ($organizerType === 'individual'): ?>


          <form action="" method="POST">

            <article class="organizer-register-form">
              <p>氏名</p>
              <input
                type="text"
                name="name"
                class="input"
                placeholder="田中 太郎"
                required>
            </article>

            <article class="organizer-register-form">
              <p>電話番号</p>
              <input
                type="tel"
                name="tel"
                class="input"
                placeholder="09012345678"
                required>
            </article>

            <article class="organizer-register-form">
              <p>メールアドレス</p>
              <input
                type="email"
                name="email"
                class="input"
                placeholder="example@example.com"
                required>
            </article>

            <button type="submit" class="next btn">
              次へ
            </button>
            <button
              type="button"
              class="back btn"
              onclick="history.back()">
              戻る
            </button>
          </form>


        <?php elseif ($organizerType === 'team'): ?>

          <form action="" method="POST">

            <article class="organizer-register-form">
              <p>団体・チーム名</p>
              <input
                type="text"
                name="team_name"
                class="input"
                placeholder="○○スポーツクラブ"
                required>
            </article>

            <article class="organizer-register-form">
              <p>代表者名</p>
              <input
                type="text"
                name="representative_name"
                class="input"
                placeholder="田中 太郎"
                required>
            </article>

            <article class="organizer-register-form">
              <p>団体電話番号</p>
              <input
                type="tel"
                name="tel"
                class="input"
                placeholder="09012345678"
                required>
            </article>

            <article class="organizer-register-form">
              <p>メールアドレス</p>
              <input
                type="email"
                name="email"
                class="input"
                placeholder="example@example.com"
                required>
            </article>

            <article class="organizer-register-form">
              <p>活動地域・所在地</p>
              <input
                type="text"
                name="address"
                class="input"
                placeholder="愛知県名古屋市"
                required>
            </article>

            <button type="submit" class="next btn">
              次へ
            </button>
            <button
              type="button"
              class="back btn"
              onclick="history.back()">
              戻る
            </button>
          </form>


        <?php elseif ($organizerType === 'school'): ?>


          <form action="" method="POST">

            <article class="organizer-register-form">
              <p>学校名</p>
              <input
                type="text"
                name="school_name"
                class="input"
                placeholder="○○高等学校"
                required>
            </article>

            <article class="organizer-register-form">
              <p>担当者名</p>
              <input
                type="text"
                name="contact_name"
                class="input"
                placeholder="田中 太郎"
                required>
            </article>

            <article class="organizer-register-form">
              <p>電話番号</p>
              <input
                type="tel"
                name="tel"
                class="input"
                placeholder="09012345678"
                required>
            </article>

            <article class="organizer-register-form">
              <p>メールアドレス</p>
              <input
                type="email"
                name="email"
                class="input"
                placeholder="example@example.com"
                required>
            </article>

            <article class="organizer-register-form">
              <p>所在地</p>
              <input
                type="text"
                name="address"
                class="input"
                placeholder="愛知県名古屋市"
                required>
            </article>

            <button type="submit" class="next btn">
              次へ
            </button>
            <button
              type="button"
              class="back btn"
              onclick="history.back()">
              戻る
            </button>
          </form>


        <?php elseif ($organizerType === 'company'): ?>


          <form action="" method="POST">

            <article class="organizer-register-form">
              <p>企業名</p>
              <input
                type="text"
                name="company_name"
                class="input"
                placeholder="株式会社○○"
                required>
            </article>

            <article class="organizer-register-form">
              <p>担当者名</p>
              <input
                type="text"
                name="contact_name"
                class="input"
                placeholder="田中 太郎"
                required>
            </article>

            <article class="organizer-register-form">
              <p>電話番号</p>
              <input
                type="tel"
                name="tel"
                class="input"
                placeholder="09012345678"
                required>
            </article>

            <article class="organizer-register-form">
              <p>メールアドレス</p>
              <input
                type="email"
                name="email"
                class="input"
                placeholder="example@example.com"
                required>
            </article>

            <article class="organizer-register-form">
              <p>法人番号</p>
              <input
                type="text"
                name="corporate_number"
                class="input"
                placeholder="1234567890123"
                maxlength="13"
                required>
            </article>

            <article class="organizer-register-form">
              <p>所在地</p>
              <input
                type="text"
                name="address"
                class="input"
                placeholder="愛知県名古屋市"
                required>
            </article>

            <button type="submit" class="next btn">
              次へ
            </button>
            <button
              type="button"
              class="back btn"
              onclick="history.back()">
              戻る
            </button>
          </form>


        <?php elseif ($organizerType === 'association'): ?>


          <form action="" method="POST">

            <article class="organizer-register-form">
              <p>協会・連盟名</p>
              <input
                type="text"
                name="association_name"
                class="input"
                placeholder="○○県サッカー協会"
                required>
            </article>

            <article class="organizer-register-form">
              <p>代表者名</p>
              <input
                type="text"
                name="representative_name"
                class="input"
                placeholder="田中 太郎"
                required>
            </article>

            <article class="organizer-register-form">
              <p>電話番号</p>
              <input
                type="tel"
                name="tel"
                class="input"
                placeholder="09012345678"
                required>
            </article>

            <article class="organizer-register-form">
              <p>メールアドレス</p>
              <input
                type="email"
                name="email"
                class="input"
                placeholder="example@example.com"
                required>
            </article>

            <article class="organizer-register-form">
              <p>管轄地域</p>
              <input
                type="text"
                name="area"
                class="input"
                placeholder="愛知県"
                required>
            </article>

            <button type="submit" class="next btn">
              次へ
            </button>
            <button
              type="button"
              class="back btn"
              onclick="history.back()">
              戻る
            </button>
          </form>

        <?php endif; ?>
      </div>

    </div>

  </main>

</body>

</html>
