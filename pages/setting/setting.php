<?php

/**
 * pages/setting/setting.php
 * 設定画面。アカウント・通知・観戦・遠征・表示・プライバシー・サポートの各設定へのリンクを並べる。
 * マイページ（pages/setting/mypage.php）の「設定」と、画面下のメニューの「設定」から来る。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/auth.php';   // csrf_field()（ログアウトの POST 用）
require_once __DIR__ . '/../../lib/guest.php';

session_boot();

// パスワードの変更は、ログインしている本人だけ。ゲストはパスワードでログインしないので開けない
// （開いても password-setting.php が断る）
$settingUser       = current_user();
$canChangePassword = $settingUser !== null && !is_guest($settingUser);
$passwordNote      = is_guest($settingUser) ? 'ゲストは変更できません' : 'ログインが必要です';

?>
<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>設定 | SPOTIVE</title>

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
      <section class="header header--setting">
        <span class="header-account">
          <!-- 戻る：来た画面があればそこへ（js/main.js）、URL を直接開いたときはマイページへ -->
          <a href="mypage.php" class="setting-back">
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
            <!-- プロフィールの変更とアカウント情報・退会は、同じ画面（account-setting.php）にまとめている -->
            <a href="account-setting.php" class="setting-account-content">
              <span>
                <div class="setting-account-img"><img src="../../images/icons/setting-account.svg" alt=""></div>
                <p>プロフィール・アカウント情報</p>
              </span>
              <img src="../../images/icons/setting-next.svg" alt="" class="setting-account-next">
            </a>
            <?php if ($canChangePassword) : ?>
              <a href="password-setting.php" class="setting-account-content">
                <span>
                  <div class="setting-account-img"><img src="../../images/icons/setting-key.svg" alt=""></div>
                  <p>パスワード</p>
                </span>
                <img src="../../images/icons/setting-next.svg" alt="" class="setting-account-next">
              </a>
            <?php else : ?>
              <!-- ゲスト・未ログインでは開けないので、押せない表示にする -->
              <span class="setting-account-content is-disabled" role="link" aria-disabled="true">
                <span>
                  <div class="setting-account-img"><img src="../../images/icons/setting-key.svg" alt=""></div>
                  <p>パスワード</p>
                </span>
                <span class="setting-soon"><?= h($passwordNote) ?></span>
              </span>
            <?php endif; ?>
          </article>
        </section>

        <!-- __________________________________________________________________ -->

        <section class="setting-notification">
          <h2>通知設定</h2>

          <article>
            <!-- 準備中（ページがまだ無い）。押せない表示にする -->
            <span class="setting-notification-content is-disabled" role="link" aria-disabled="true">
              <span>
                <div class="setting-notification-img">
                  <img src="../../images/icons/setting-game.svg" alt="">
                </div>
                <p>試合開始前の通知</p>
              </span>
              <span class="setting-soon">準備中</span>
            </span>

            <!-- 準備中（ページがまだ無い）。押せない表示にする -->
            <span class="setting-notification-content is-disabled" role="link" aria-disabled="true">
              <span>
                <div class="setting-notification-img">
                  <img src="../../images/icons/setting-heart.svg" alt="">
                </div>
                <p>お気に入りチーム</p>
              </span>
              <span class="setting-soon">準備中</span>
            </span>

            <!-- 準備中（設定を保存する仕組みがまだ無い）。押せない表示にする -->
            <span class="setting-notification-content is-disabled" role="link" aria-disabled="true">
              <span>
                <div class="setting-notification-img">
                  <img src="../../images/icons/notice.svg" alt="">
                </div>
                <p>アプリからのお知らせ</p>
              </span>
              <span class="setting-soon">準備中</span>
            </span>
          </article>
        </section>
        <!-- __________________________________________________________________ -->
        <section class="setting-game">
          <h2>観戦</h2>

          <article>
            <!-- 準備中（設定を保存する仕組みがまだ無い）。押せない表示にする -->
            <span class="setting-game-content is-disabled" role="link" aria-disabled="true">
              <span>
                <div class="setting-game-img">
                  <img src="../../images/icons/setting-heart.svg" alt="">
                </div>
                <p>お気に入りチーム</p>
              </span>
              <span class="setting-soon">準備中</span>
            </span>

            <!-- 準備中（ページがまだ無い）。押せない表示にする -->
            <span class="setting-game-content is-disabled" role="link" aria-disabled="true">
              <span>
                <div class="setting-game-img">
                  <img src="../../images/icons/setting-place.svg" alt="">
                </div>
                <p>よく行く会場</p>
              </span>
              <span class="setting-soon">準備中</span>
            </span>
          </article>
        </section>

        <!-- __________________________________________________________________ -->

        <section class="setting-expedition">
          <h2>遠征サポート設定</h2>

          <article>
            <!-- 準備中（設定を保存する仕組みがまだ無い）。押せない表示にする -->
            <span class="setting-expedition-content is-disabled" role="link" aria-disabled="true">
              <span>
                <div class="setting-expedition-img">
                  <img src="../../images/icons/setting-departure.svg" alt="">
                </div>
                <p>出発地</p>
              </span>
              <span class="setting-soon">準備中</span>
            </span>

            <!-- 準備中（設定を保存する仕組みがまだ無い）。押せない表示にする -->
            <span class="setting-expedition-content is-disabled" role="link" aria-disabled="true">
              <span>
                <div class="setting-expedition-img">
                  <img src="../../images/icons/setting-train.svg" alt="">
                </div>
                <p>交通手段</p>
              </span>
              <span class="setting-soon">準備中</span>
            </span>

            <!-- 準備中（設定を保存する仕組みがまだ無い）。押せない表示にする -->
            <span class="setting-expedition-content is-disabled" role="link" aria-disabled="true">
              <span>
                <div class="setting-expedition-img">
                  <img src="../../images/icons/setting-hotel.svg" alt="">
                </div>
                <p>宿泊条件</p>
              </span>
              <span class="setting-soon">準備中</span>
            </span>

            <!-- 準備中（設定を保存する仕組みがまだ無い）。押せない表示にする -->
            <span class="setting-expedition-content is-disabled" role="link" aria-disabled="true">
              <span>
                <div class="setting-expedition-img">
                  <img src="../../images/icons/setting-money.svg" alt="">
                </div>
                <p>予算</p>
              </span>
              <span class="setting-soon">準備中</span>
            </span>
          </article>
        </section>


        <!-- __________________________________________________________________ -->


        <section class="setting-display">
          <h2>表示設定</h2>

          <article>
            <!-- 準備中（ページがまだ無い）。押せない表示にする -->
            <span class="setting-display-content is-disabled" role="link" aria-disabled="true">
              <span>
                <div class="setting-display-img">
                  <img src="../../images/icons/setting-display.svg" alt="">
                </div>
                <p>テーマ</p>
              </span>
              <span class="setting-soon">準備中</span>
            </span>
          </article>
        </section>
        <!-- __________________________________________________________________ -->

        <section class="setting-privacy">
          <h2>プライバシー・安全</h2>

          <article>
            <!-- 準備中（ページがまだ無い）。押せない表示にする -->
            <span class="setting-privacy-content is-disabled" role="link" aria-disabled="true">
              <span>
                <div class="setting-privacy-img">
                  <img src="../../images/icons/map.svg" alt="">
                </div>
                <p>位置情報</p>
              </span>
              <span class="setting-soon">準備中</span>
            </span>

            <a href="<?= h(url('pages/legal/privacy.php')) ?>" class="setting-privacy-content">
              <span>
                <div class="setting-privacy-img">
                  <img src="../../images/icons/setting-privacy.svg" alt="">
                </div>
                <p>プライバシーポリシー</p>
              </span>
              <img src="../../images/icons/setting-link.svg" alt="" class="setting-privacy-next">
            </a>

            <a href="<?= h(url('pages/legal/terms.php')) ?>" class="setting-privacy-content">
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
            <a href="<?= h(url('pages/support/faq.php')) ?>" class="setting-support-content">
              <span>
                <div class="setting-support-img">
                  <img src="../../images/icons/setting-question.svg" alt="">
                </div>
                <p>よくある質問</p>
              </span>
              <img src="../../images/icons/setting-link.svg" alt="" class="setting-support-next">
            </a>

            <a href="<?= h(url('pages/support/contact.php')) ?>" class="setting-support-content">
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
            <!-- logout.php は POST しか受けないので、リンクではなくフォームで送る -->
            <form action="<?= h(url('pages/account/logout.php')) ?>" method="post">
              <?= csrf_field() ?>
              <button type="submit" class="setting-logout-content">
                <span>
                  <p>ログアウト</p>
                </span>
                <img src="../../images/icons/setting-logout.svg" alt="" class="setting-logout-next">
              </button>
            </form>
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
