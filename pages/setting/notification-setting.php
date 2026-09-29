<?php

/**
 * pages/setting/notification-setting.php
 * 通知設定画面。通知の受け取り設定
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';

// ここでデータを取得する（HTML は書かない）

?>
<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>通知設定 | SPOTIVE</title>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Jost:wght@400;600&family=Noto+Sans+JP:wght@400;500;700&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/the-new-css-reset/css/reset.min.css">
  <link rel="stylesheet" href="../../css/style.css" />
</head>

<body>
  <header class="site-header"></header>

  <main class="l-main">
    <div class="inner">
      <div class="setting">
        <section class="setting-account">
          <h2>アカウント</h2>
          <article>
            <a href="" class="setting-account-content">
              <span>
                <div class="setting-account-img"><img src="../../images/icons/account.svg" alt=""></div>
                <p>プロフィール</p>
              </span>
              <img src="../../images/icons/setting-next.svg" alt="" class="setting-account-next">
            </a>
            <a href="" class="setting-account-content">
              <span>
                <div class="setting-account-img"><img src="../../images/icons/setting-account.svg" alt=""></div>
                <p>アカウント情報</p>
              </span>
              <img src="../../images/icons/setting-next.svg" alt="" class="setting-account-next">
            </a>
            <a href="" class="setting-account-content">
              <span>
                <div class="setting-account-img"><img src="../../images/icons/setting-key.svg" alt=""></div>
                <p>アカウント</p>
              </span>
              <img src="../../images/icons/setting-next.svg" alt="" class="setting-account-next">
            </a>
          </article>
        </section>

        <!-- __________________________________________________________________ -->

        <section class="setting-notification">
          <h2>通知</h2>

          <article>
            <a href="" class="setting-notification-content">
              <span>
                <div class="setting-notification-img">
                  <img src="../../images/icons/notification.svg" alt="">
                </div>
                <p>通知設定</p>
              </span>
              <img src="../../images/icons/setting-next.svg" alt="" class="setting-notification-next">
            </a>

            <a href="" class="setting-notification-content">
              <span>
                <div class="setting-notification-img">
                  <img src="../../images/icons/setting-mail.svg" alt="">
                </div>
                <p>メール通知</p>
              </span>
              <img src="../../images/icons/setting-next.svg" alt="" class="setting-notification-next">
            </a>

            <a href="" class="setting-notification-content">
              <span>
                <div class="setting-notification-img">
                  <img src="../../images/icons/setting-event.svg" alt="">
                </div>
                <p>イベント通知</p>
              </span>
              <img src="../../images/icons/setting-next.svg" alt="" class="setting-notification-next">
            </a>
          </article>
        </section>
      </div> <!--  setting -->

    </div>
  </main>

  <footer class="site-footer"></footer>

  <script src="../../js/main.js"></script>
</body>

</html>
