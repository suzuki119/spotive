<?php

/**
 * pages/setting/setting.php
 * マイページ（設定メニューのトップも兼ねる）。ヘッダーのアカウントのアイコンから来る。
 *
 *   プロフィール   … ニックネームと信頼レベル。アイコンは仮の画像（images/sample/sample-icon.png）
 *   Favorite       … お気に入りのチーム（favorite_teams テーブル）
 *   Away Travel    … 遠征プラン（travel_plans）。まだ終わっていないプランのうち一番近いものの試合を出す
 *   Records        … 観戦記録。記録のテーブルはまだ無いので、仮データ（data/records.json）
 *   SPOTIVE PLUS+  … サブスクリプション（未設計）の案内。まだ押せない
 * 主催者は「自分の大会」、全員に各設定へのリンクも出す。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/tournament.php';
require_once __DIR__ . '/../../lib/favorite.php';
require_once __DIR__ . '/../../lib/match-search.php';
require_once __DIR__ . '/../../lib/travel.php';
require_once __DIR__ . '/../../lib/layout.php';   // status_label() / format_datetime()

/** 仮のプロフィール画像。プロフィール画像の設定ができたら差し替える */
const MYPAGE_DEFAULT_ICON = 'images/sample/sample-icon.png';

/** 競技 => 見出しに出すリーグ名（lib/match-search.php の MATCH_SEARCH_TABS。競技のタブは競技名で出すので、ここだけリーグ名） */
function mypage_league(string $sport): string
{
  $key = match_sport_key($sport);
  return MATCH_SEARCH_TABS[$key] ?? match_sport_label($sport);
}

$user = require_login();

// -------------------------------------------------------------------
// お気に入りのチーム
// -------------------------------------------------------------------
$teams       = load_teams();
$favoriteIds = [];
try {
  $favoriteIds = favorite_team_ids((int) $user['id']);
} catch (PDOException $e) {
  error_log('[SPOTIVE] DB error: ' . $e->getMessage());
}
$favoriteTeams = [];
foreach ($favoriteIds as $id) {
  if (isset($teams[$id])) {
    $favoriteTeams[] = $teams[$id] + ['logoPath' => match_team_logo($id, 0)];
  }
}

// -------------------------------------------------------------------
// 遠征プラン：まだ終わっていないプランのうち一番近いもの。カードはその試合ごとに出す
// -------------------------------------------------------------------
$travelPlan   = null;
$travelCount  = 0;
$travelGames  = [];
$travelError  = false;
try {
  ['plan' => $travelPlan, 'count' => $travelCount] = travel_upcoming_plan((int) $user['id']);
} catch (PDOException $e) {
  error_log('[SPOTIVE] DB error: ' . $e->getMessage());
  $travelError = true;
}
if ($travelPlan !== null) {
  $travelGames = array_values(array_filter(
    $travelPlan['items'],
    static fn(array $item): bool => $item['item_type'] === 'match'
  ));
}

// -------------------------------------------------------------------
// 観戦記録（仮データ）。競技ごとのタブで切り替える
// -------------------------------------------------------------------
$records = load_data_json('records.json');
usort($records, static fn(array $a, array $b): int => strcmp((string) $b['date'], (string) $a['date']));
$recordSports = [];
foreach ($records as $record) {
  $recordSports[match_sport_key((string) $record['sport'])] = mypage_league((string) $record['sport']);
}

// -------------------------------------------------------------------
// 主催者の大会
// -------------------------------------------------------------------
$myEvents = [];
if ((int) $user['trust_level'] >= (int) config('verification.require_level.create_tournament', LEVEL_ORGANIZER)) {
  try {
    $myEvents = tournament_list_mine((int) $user['id']);
  } catch (PDOException $e) {
    error_log('[SPOTIVE] DB error: ' . $e->getMessage());
  }
}

$fmtMd = static fn(string $date): string => $date === '' ? '' : date('m/d', (int) strtotime($date));

?>
<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>マイページ | SPOTIVE</title>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Jost:wght@400;600&family=Noto+Sans+JP:wght@400;500;700&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="<?= h(asset('css/style.css')) ?>" />
</head>

<body>
  <?php
  $appHeader = ['title' => 'MY PAGE', 'noticeIcon' => 'mail.svg', 'noticeLabel' => 'Notice', 'back' => 'pages/home/home.php'];
  require __DIR__ . '/../app-header.php';
  ?>

  <main class="mypage">
    <!-- ほかの画面から戻ってきたときのお知らせ（ゲストでは使えない画面など） -->
    <?php foreach (take_flash() as $message) : ?>
      <p class="notice notice--<?= h($message['type']) ?>"><?= h($message['message']) ?></p>
    <?php endforeach; ?>

    <!-- プロフィール -->
    <section class="mypage-profile" aria-label="プロフィール">
      <img class="mypage-profile__icon" src="<?= h(url(MYPAGE_DEFAULT_ICON)) ?>" alt="" width="90" height="90" />
      <div class="mypage-profile__body">
        <p class="mypage-profile__name"><?= h((string) $user['nickname']) ?></p>
        <p class="mypage-profile__level"><?= h(level_label((int) $user['trust_level'])) ?></p>
      </div>
      <a class="mypage-profile__setting" href="account-setting.php">アカウント設定</a>
    </section>

    <!-- Favorite -->
    <section class="mypage__section">
      <h2 class="mypage__heading">Favorite<span class="mypage__heading-sub">お気に入りチーム</span></h2>
      <?php if ($favoriteTeams === []) : ?>
        <p class="mypage__empty">
          お気に入りのチームはまだありません。<br />
          <a href="<?= h(url('pages/favorite/favorite.php')) ?>">チームを登録する</a>
        </p>
      <?php else : ?>
        <ul class="favorite-teams">
          <?php foreach ($favoriteTeams as $team) : ?>
            <li class="favorite-teams__item favorite-teams__item--<?= h(match_sport_key((string) $team['sport'])) ?>">
              <span class="favorite-teams__logo">
                <?php if ($team['logoPath'] !== '') : ?>
                  <img src="<?= h(url($team['logoPath'])) ?>" alt="" width="64" height="64" />
                <?php endif; ?>
              </span>
              <span class="favorite-teams__body">
                <span class="favorite-teams__sport"><?= h(match_sport_label((string) $team['sport'])) ?></span>
                <span class="favorite-teams__name"><?= h((string) $team['name']) ?></span>
                <span class="favorite-teams__league"><?= h(mypage_league((string) $team['sport'])) ?></span>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
        <a class="mypage__more" href="<?= h(url('pages/favorite/favorite.php')) ?>">お気に入りを編集</a>
      <?php endif; ?>
    </section>

    <!-- Away Travel（遠征プラン。一番近いプランの試合ごとに、チケットを買ったかを出す） -->
    <section class="mypage__section">
      <h2 class="mypage__heading">Away Travel</h2>
      <?php if ($travelError) : ?>
        <p class="mypage__empty">遠征プランを読み込めませんでした。</p>
      <?php elseif ($travelPlan === null) : ?>
        <p class="mypage__empty">
          これからの遠征プランはありません。
          <a href="<?= h(url('pages/travel/travel.php')) ?>">遠征プランを作る</a>
        </p>
      <?php else : ?>
        <div class="mypage-travel__head">
          <p class="mypage-travel__dates">
            <?= h($fmtMd((string) $travelPlan['start_date'])) ?>
            <?php if ($travelPlan['end_date'] !== $travelPlan['start_date']) : ?>
              〜<?= h($fmtMd((string) $travelPlan['end_date'])) ?>
            <?php endif; ?>
          </p>
          <a class="mypage-travel__check" href="<?= h(url('pages/travel/travel-plan.php?id=' . (int) $travelPlan['id'])) ?>">プランを確認</a>
        </div>
        <ul class="mypage-travel">
          <?php foreach ($travelGames as $item) : ?>
            <?php
            $vs     = match_teams(travel_item_team_ids($item));   // 主催者の大会は空
            $bought = $item['booking_status'] === 'booked';
            $detail = travel_item_detail_path($item);
            $start  = (string) $item['starts_at'];
            ?>
            <li class="mypage-travel__item <?= $bought ? 'is-done' : 'is-todo' ?>">
              <a class="mypage-travel__link" href="<?= h(url($detail !== '' ? $detail : 'pages/travel/travel-plan.php?id=' . (int) $travelPlan['id'])) ?>">
                <span class="mypage-travel__logos">
                  <?php foreach ($vs as $j => $team) : ?>
                    <?php if ($j === 1) : ?><span class="mypage-travel__vs">vs</span><?php endif; ?>
                    <?php if ($team['logo'] !== '') : ?>
                      <img src="<?= h(url($team['logo'])) ?>" alt="<?= h($team['name']) ?>" width="48" height="48" />
                    <?php endif; ?>
                  <?php endforeach; ?>
                </span>
                <span class="mypage-travel__body">
                  <span class="mypage-travel__meta"><?= h($fmtMd($start)) ?> <?= h(date('H:i', (int) strtotime($start))) ?>〜 ・ <?= h((string) $item['from_label']) ?></span>
                  <span class="mypage-travel__title"><?= h((string) $item['title']) ?></span>
                  <span class="mypage-travel__status"><?= $bought ? 'チケット購入済み ✓' : 'チケット未購入' ?></span>
                </span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php if ($travelCount > 1) : ?>
          <p class="mypage-travel__more">
            <a href="<?= h(url('pages/travel/travel-plan.php')) ?>">ほかの遠征プラン（<?= (int) $travelCount - 1 ?>件）</a>
          </p>
        <?php endif; ?>
      <?php endif; ?>
    </section>

    <!-- Records（観戦記録。仮データ） -->
    <section class="mypage__section">
      <h2 class="mypage__heading">Records</h2>
      <p class="mypage__mock">観戦記録は画面確認用の仮データです。</p>
      <?php if ($records === []) : ?>
        <p class="mypage__empty">観戦記録はまだありません。</p>
      <?php else : ?>
        <div class="mypage-tabs" role="tablist" aria-label="競技">
          <?php $first = true; ?>
          <?php foreach ($recordSports as $key => $label) : ?>
            <button
              class="mypage-tabs__tab<?= $first ? ' is-active' : '' ?>"
              type="button"
              role="tab"
              aria-selected="<?= $first ? 'true' : 'false' ?>"
              data-record-tab="<?= h($key) ?>"><?= h($label) ?></button>
            <?php $first = false; ?>
          <?php endforeach; ?>
        </div>

        <ul class="records">
          <?php $firstSport = array_key_first($recordSports); ?>
          <?php foreach ($records as $record) : ?>
            <?php
            $vs     = match_teams([(string) $record['homeTeamId'], (string) $record['awayTeamId']]);
            $home   = (int) $record['homeScore'];
            $away   = (int) $record['awayScore'];
            $sport  = match_sport_key((string) $record['sport']);
            ?>
            <li class="records__item" data-record-sport="<?= h($sport) ?>" <?= $sport === $firstSport ? '' : 'hidden' ?>>
              <div class="records__main">
                <?php if ($vs !== []) : ?>
                  <img class="records__logo" src="<?= h(url($vs[0]['logo'])) ?>" alt="<?= h($vs[0]['name']) ?>" width="72" height="72" />
                <?php endif; ?>
                <div class="records__center">
                  <p class="records__round"><?= h(mypage_league((string) $record['sport'])) ?> <?= h((string) $record['round']) ?></p>
                  <p class="records__score">
                    <span class="records__point<?= $home > $away ? ' is-win' : '' ?>"><?= $home ?></span>
                    <span class="records__dash">-</span>
                    <span class="records__point<?= $away > $home ? ' is-win' : '' ?>"><?= $away ?></span>
                  </p>
                </div>
                <?php if ($vs !== []) : ?>
                  <img class="records__logo" src="<?= h(url($vs[1]['logo'])) ?>" alt="<?= h($vs[1]['name']) ?>" width="72" height="72" />
                <?php endif; ?>
              </div>
              <div class="records__foot">
                <span class="records__venue">
                  <img src="<?= h(url('images/icons/point.svg')) ?>" alt="" width="11" height="14" />
                  <?= h((string) $record['venue']) ?>
                </span>
                <time class="records__date" datetime="<?= h((string) $record['date']) ?>"><?= h(date('Y/m/d', (int) strtotime((string) $record['date']))) ?></time>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <?php if ($myEvents !== []) : ?>
      <!-- 主催者の大会 -->
      <section class="mypage__section">
        <h2 class="mypage__heading">My Events<span class="mypage__heading-sub">自分の大会</span></h2>
        <ul class="mypage-events">
          <?php foreach (array_slice($myEvents, 0, 5) as $event) : ?>
            <li class="mypage-events__item">
              <a href="<?= h(url('pages/organizer/tournament-new.php?id=' . (int) $event['id'])) ?>"><?= h((string) $event['title']) ?></a>
              <span class="mypage-events__meta">
                <?= h(format_datetime((string) $event['starts_at'])) ?>
                ・<?= h(status_label((string) $event['status'])) ?>
                ・<?= h(status_label((string) $event['verification_status'])) ?>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
        <a class="mypage__more" href="<?= h(url('pages/organizer/dashboard.php')) ?>">主催者ページで管理する</a>
      </section>
    <?php endif; ?>

    <!-- SPOTIVE PLUS+（サブスクリプション。未設計なのでまだ押せない） -->
    <a href="../plus/plus.php">
      <section class="mypage-plus" aria-label="SPOTIVE PLUS+">
        <p class="mypage-plus__title">
          <img src="<?= h(url('images/icons/crown.svg')) ?>" alt="" width="20" height="20" />
          SPOTIVE PLUS+
        </p>
        <p class="mypage-plus__text">プレミアムプランにアップグレードして、スポーツ観戦をもっと便利に。</p>
      </section>
    </a>

    <!-- 設定 -->
    <nav class="mypage-settings" aria-label="設定">
      <h2 class="mypage__heading">Settings<span class="mypage__heading-sub">設定</span></h2>
      <ul class="mypage-settings__list">
        <li><a class="mypage-settings__link" href="profile.php">プロフィール設定</a></li>
        <li><a class="mypage-settings__link" href="notification-setting.php">通知設定</a></li>
        <li><a class="mypage-settings__link" href="account-setting.php">アカウント設定</a></li>
      </ul>
    </nav>
  </main>

  <script src="<?= h(asset('js/main.js')) ?>"></script>
  <script src="<?= h(asset('js/pages/setting.js')) ?>"></script>
  <?php require __DIR__ . '/../menu-bar.php'; ?>
</body>

</html>
