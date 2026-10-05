<?php

/**
 * pages/setting/notification-setting.php
 * 通知設定画面。通知の受け取り設定
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/support.php';   // h() / url() と、下のメニューバーのため

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
  <header class="headerr">
    <div class="inner">
      <section class="header">
        <span class="header-account">
          <!-- 戻る：来た画面があればそこへ（js/main.js）、URL を直接開いたときはマイページへ -->
          <a href="setting.php" class="setting-back">
            <img src="<?= h(url('images/icons/setting-back.svg')) ?>" alt="戻る">
          </a>
        </span>
        <h1 class="header-title">SPOTIVE</h1>
        <span class="header-notice">
          <img src="<?= h(url('images/icons/notice.svg')) ?>" alt="">
        </span>
      </section>
    </div>
  </header>

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
          <h2>通知設定</h2>

          <article>
            <a href="" class="setting-notification-content">
              <span>
                <div class="setting-notification-img">
                  <img src="../../images/icons/setting-game.svg" alt="">
                </div>
                <p>試合開始前の通知</p>
              </span>
              <img src="../../images/icons/setting-next.svg" alt="" class="setting-notification-next">
            </a>

            <a href="" class="setting-notification-content">
              <span>
                <div class="setting-notification-img">
                  <img src="../../images/icons/setting-heart.svg" alt="">
                </div>
                <p>お気に入りチーム</p>
              </span>
              <img src="../../images/icons/setting-next.svg" alt="" class="setting-notification-next">
            </a>

            <a href="" class="setting-notification-content">
              <span>
                <div class="setting-notification-img">
                  <img src="../../images/icons/notice.svg" alt="">
                </div>
                <p>アプリからのお知らせ</p>
              </span>
              <img src="../../images/icons/setting-next.svg" alt="" class="setting-notification-next">
            </a>
          </article>
        </section>
        <!-- __________________________________________________________________ -->
        <section class="setting-game">
          <h2>観戦</h2>

          <article>
            <a href="" class="setting-game-content">
              <span>
                <div class="setting-game-img">
                  <img src="../../images/icons/setting-heart.svg" alt="">
                </div>
                <p>お気に入りチーム</p>
              </span>
              <img src="../../images/icons/setting-next.svg" alt="" class="setting-game-next">
            </a>

            <a href="" class="setting-game-content">
              <span>
                <div class="setting-game-img">
                  <img src="../../images/icons/setting-place.svg" alt="">
                </div>
                <p>よく行く会場</p>
              </span>
              <img src="../../images/icons/setting-next.svg" alt="" class="setting-game-next">
            </a>
          </article>
        </section>

        <!-- __________________________________________________________________ -->

        <section class="setting-expedition">
          <h2>遠征サポート設定</h2>

          <article>
            <a href="" class="setting-expedition-content">
              <span>
                <div class="setting-expedition-img">
                  <img src="../../images/icons/setting-departure.svg" alt="">
                </div>
                <p>出発地</p>
              </span>
              <img src="../../images/icons/setting-next.svg" alt="" class="setting-expedition-next">
            </a>

            <a href="" class="setting-expedition-content">
              <span>
                <div class="setting-expedition-img">
                  <img src="../../images/icons/setting-train.svg" alt="">
                </div>
                <p>交通手段</p>
              </span>
              <img src="../../images/icons/setting-next.svg" alt="" class="setting-expedition-next">
            </a>

            <a href="" class="setting-expedition-content">
              <span>
                <div class="setting-expedition-img">
                  <img src="../../images/icons/setting-hotel.svg" alt="">
                </div>
                <p>宿泊条件</p>
              </span>
              <img src="../../images/icons/setting-next.svg" alt="" class="setting-expedition-next">
            </a>

            <a href="" class="setting-expedition-content">
              <span>
                <div class="setting-expedition-img">
                  <img src="../../images/icons/setting-money.svg" alt="">
                </div>
                <p>予算</p>
              </span>
              <img src="../../images/icons/setting-next.svg" alt="" class="setting-expedition-next">
            </a>
          </article>
        </section>


        <!-- __________________________________________________________________ -->


        <section class="setting-display">
          <h2>表示設定</h2>

          <article>
            <a href="" class="setting-display-content">
              <span>
                <div class="setting-display-img">
                  <img src="../../images/icons/setting-display.svg" alt="">
                </div>
                <p>テーマ</p>
              </span>
              <img src="../../images/icons/setting-next.svg" alt="" class="setting-display-next">
            </a>
          </article>
        </section>
        <!-- __________________________________________________________________ -->

        <section class="setting-privacy">
          <h2>プライバシー・安全</h2>

          <article>
            <a href="" class="setting-privacy-content">
              <span>
                <div class="setting-privacy-img">
                  <img src="../../images/icons/map.svg" alt="">
                </div>
                <p>位置情報</p>
              </span>
              <img src="../../images/icons/setting-link.svg" alt="" class="setting-privacy-next">
            </a>

            <a href="" class="setting-privacy-content">
              <span>
                <div class="setting-privacy-img">
                  <img src="../../images/icons/setting-privacy.svg" alt="">
                </div>
                <p>プライバシーポリシー</p>
              </span>
              <img src="../../images/icons/setting-link.svg" alt="" class="setting-privacy-next">
            </a>

            <a href="" class="setting-privacy-content">
              <span>
                <div class="setting-privacy-img">
                  <img src="../../images/icons/setting-term.svg" alt="">
                </div>
                <p>利用規約</p>
              </span>
              <img src="../../images/icons/setting-link.svg" alt="" class="setting-privacy-next">
            </a>
          </article>
        </section>

        <!-- __________________________________________________________________ -->

        <section class="setting-support">
          <h2>サポート</h2>

          <article>
            <a href="" class="setting-support-content">
              <span>
                <div class="setting-support-img">
                  <img src="../../images/icons/setting-question.svg" alt="">
                </div>
                <p>よくある質問</p>
              </span>
              <img src="../../images/icons/setting-link.svg" alt="" class="setting-support-next">
            </a>

            <a href="" class="setting-support-content">
              <span>
                <div class="setting-support-img">
                  <img src="../../images/icons/mail.svg" alt="">
                </div>
                <p>お問い合わせ</p>
              </span>
              <img src="../../images/icons/setting-link.svg" alt="" class="setting-support-next">
            </a>
          </article>
        </section>

        <section class="setting-logout">
          <article>
            <a href="" class="setting-logout-content">
              <span>
                <p>ログアウト</p>
              </span>
              <img src="../../images/icons/setting-logout.svg" alt="" class="setting-logout-next">
            </a>
          </article>
        </section>
      </div> <!--  setting -->

    </div>
  </main>

  <footer class="site-footer"></footer>

  <script src="../../js/main.js"></script>
  <?php require __DIR__ . '/../menu-bar.php'; ?>
</body>

</html>
