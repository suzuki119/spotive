<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/the-new-css-reset/css/reset.min.css">
  <link rel="stylesheet" href="../../css/style.css">
  <title>Administrator</title>
</head>

<body>
  <header>
    <h1>ADMINISTRATOR</h1>
    <p>管理者専用ページ</p>
  </header>
  <main>
    <div class="inner">
      <div class="administrators">
        <article class="administrators_search">
          <input type="text" placeholder="検索">
          <button type="button"><img src="../../images/icons/search.svg" alt=""></button>
        </article>
        <section class="administrators_accessboard">
          <article class="administrators_accessboard_top">
            <h2 class="administrators_accessboard_title">アクセスボード</h2>
            <p class="administrators_accessboard_time">2026/09/28 現在</p>
          </article>
          <article class="administrators_accessboard_bottom">
            <span class="administrators_accessboard_bottom_view">
              <p>23,145</p>
              <p class="administrators_accessboard_bottom_text">閲覧数</p>
            </span>
            <span class="administrators_accessboard_bottom-favorite">
              <p>1,290</p>
              <p class="administrators_accessboard_bottom_text">お気に入り登録数</p>
            </span>
            <span class="administrators_accessboard_bottom-ticket">
              <p>2,346</p>
              <p class="administrators_accessboard_bottom_text">チケットページ遷移</p>
            </span>
          </article>
        </section>
        <section class="administrators_events">
          <article class="administrators_events_top">
            <h2 class="administrators_events_title">イベント情報</h2>
            <p class="administrators_events_time">2026/09/28 現在</p>
          </article>

          <article class="administrators_events_bottom">
            <span class="administrators_events_bottom-league">
              <p>0</p>
              <p class="administrators_events_bottom_text">登録リーグ</p>
            </span>

            <span class="administrators_events_bottom-match">
              <p>0</p>
              <p class="administrators_events_bottom_text">今後の試合</p>
            </span>

            <span class="administrators_events_bottom-tournament">
              <p>0</p>
              <p class="administrators_events_bottom_text">今後の大会</p>
            </span>

            <span class="administrators_events_bottom-unregistered">
              <p>0</p>
              <p class="administrators_events_bottom_text">未登録</p>
            </span>
          </article>
        </section>
        <article class="administrators_events_btn">
          <a href="">
            <p>イベントの追加</p>
            <img src="../../images/icons/eventsadd.svg" alt="">
          </a>
        </article>
        <section class="administrators_notice">
          <article class="administrators_notice_top">
            <h2 class="administrators_notice_title">お知らせ配信</h2>
          </article>
          <article class="administrators_notice_bottom">
            <p>
              チームをお気に入りに登録しているユーザー向けに、
              お知らせを配信できます。
            </p>
          </article>
        </section>
        <article class="administrators_notice_btn">
          <a href="">
            <p>お知らせを配信</p>
            <img src="../../images/icons/eventsadd.svg" alt="">
          </a>
        </article>
        <section class="administrators_publish">
          <h2 class="administrators_publish_title">掲載中のイベント・リーグ</h2>
          <article class="administrators_publish_group">
            <h3 class="administrators_publish_group_title">
              B.LEAGUE ONE 2026-27Season
            </h3>
            <span class="administrators_publish_group_time">
              <img src="../../images/icons/date.svg" alt="">
              <p>2026/09/27〜2027/05/06</p>
            </span>
            <p class="administrators_publish_group_place">
              ホームアリーナ登録：枇杷島アリーナ
            </p>
            <span class="administrators_publish_group_check">
              <p>掲載中</p>
            </span>
          </article>
        </section>
      </div>
    </div>
  </main>
  <footer>

  </footer>
</body>

</html>
