<?php

/**
 * pages/home/home.php
 * ホーム画面。ログインした人は index.php からここへ来る。
 *
 *   お気に入り            … お気に入りのチームのこれからの試合（横にスクロール）
 *   今から観戦できる試合  … まだ始まっていない試合を、始まる順に
 *
 * お気に入りは、ログイン中なら DB、未ログインならブラウザに保存されている
 * （lib/favorite.php・js/common/favorite-store.js）。どちらでも同じように出せるよう、
 * チームの付いた試合をすべて埋めておき、お気に入りかどうかの切り替えは JS が行う。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/favorite.php';

const HOME_NOW_LIMIT = 10;   // 「今から観戦できる試合」に出す数

/** 本日 13:00〜 / 明日 13:00〜 / 10/3(土) 13:00〜 */
function home_when(array $match): string
{
  $time = $match['time'] === '' ? '時間未定' : $match['time'] . '〜';
  if ($match['date'] === date('Y-m-d')) {
    return '本日 ' . $time;
  }
  if ($match['date'] === date('Y-m-d', strtotime('+1 day'))) {
    return '明日 ' . $time;
  }
  $ts    = (int) strtotime($match['date']);
  $youbi = ['日', '月', '火', '水', '木', '金', '土'][(int) date('w', $ts)];
  return date('n/j', $ts) . '(' . $youbi . ') ' . $time;
}

// -------------------------------------------------------------------
// データの取得
// -------------------------------------------------------------------
$favoriteState = favorite_client_state();
$teams         = load_teams();
$upcoming      = load_upcoming_matches();

// 距離を出すための会場の座標（仮データの試合だけ持っている）
$coords = [];
foreach (load_data_json('matches.json') as $m) {
  if (isset($m['id'], $m['lat'], $m['lng'])) {
    $coords['json-' . $m['id']] = ['lat' => (float) $m['lat'], 'lng' => (float) $m['lng']];
  }
}

/** 試合に、画面で使うチーム（ロゴ・略称）と座標を足す */
$decorate = static function (array $match) use ($teams, $coords): array {
  $match['teams'] = [];
  foreach ($match['teamIds'] as $id) {
    if (!isset($teams[$id])) {
      continue;
    }
    $name = (string) ($teams[$id]['name'] ?? '');
    $match['teams'][] = [
      'name'    => $name,
      // ロゴ画像がまだ無いチームは仮のロゴ（lib/match.php）。それも無ければ頭文字の丸
      'logo'    => match_team_logo($id, count($match['teams'])),
      'initial' => mb_substr($name, 0, 2),
    ];
  }
  $match['coords'] = $coords[$match['key']] ?? null;
  return $match;
};

// お気に入り候補：チームの付いた試合すべて（どれを出すかは JS が決める）
$favoriteCandidates = array_map(
  $decorate,
  array_values(array_filter($upcoming, static fn(array $m): bool => $m['teamIds'] !== []))
);

// 今から観戦できる試合：今日のうち、もう始まった試合は外す
$now        = date('H:i');
$today      = date('Y-m-d');
$nowMatches = array_map($decorate, array_slice(array_values(array_filter(
  $upcoming,
  static fn(array $m): bool => $m['date'] > $today || $m['time'] === '' || $m['time'] >= $now
)), 0, HOME_NOW_LIMIT));

$user     = null;
try {
  $user = current_user();
} catch (PDOException $e) {
  error_log('[SPOTIVE] DB error: ' . $e->getMessage());
}
$accountHref = $user === null ? 'pages/account/signin.php' : 'pages/setting/setting.php';

/**
 * 対戦カードのロゴ部分（チーム vs チーム）。チームが無い大会は競技名を出す。
 * 表示だけの小さな部品なので、ここに置いている。
 */
$teamsVs = static function (array $match, string $block): void {
  if (count($match['teams']) < 2) {
    ?>
    <span class="<?= h($block) ?>__solo team-logo team-logo--<?= h(match_sport_key($match['sport'])) ?>">
      <?= h(match_sport_label($match['sport'])) ?>
    </span>
    <?php
    return;
  }
  foreach ($match['teams'] as $i => $team) :
    if ($i === 1) :
      ?><span class="<?= h($block) ?>__vs">vs</span><?php
    endif;
    if ($team['logo'] !== '') :
      ?><img class="team-logo" src="<?= h(url($team['logo'])) ?>" alt="<?= h($team['name']) ?>" width="48" height="48" /><?php
    else :
      ?><span class="team-logo team-logo--<?= h(match_sport_key($match['sport'])) ?>" role="img" aria-label="<?= h($team['name']) ?>"><?= h($team['initial']) ?></span><?php
    endif;
  endforeach;
};

?>
<!DOCTYPE html>
<html lang="ja">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>ホーム | SPOTIVE</title>

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Jost:wght@400;600&family=Noto+Sans+JP:wght@400;500;700&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="<?= h(asset('css/style.css')) ?>" />
  </head>

  <body>
    <header class="home-header">
      <a class="home-header__icon" href="<?= h(url($accountHref)) ?>">
        <img src="<?= h(url('images/icons/account.svg')) ?>" alt="アカウント" width="32" height="32" />
      </a>
      <h1 class="home-header__title">SPOTIVE</h1>
      <a class="home-header__icon" href="<?= h(url('pages/notification/notification.php')) ?>">
        <img src="<?= h(url('images/icons/notice.svg')) ?>" alt="お知らせ" width="32" height="32" />
      </a>
    </header>

    <main class="home">
      <section class="home__section">
        <div class="home__head">
          <h2 class="home__heading">お気に入り</h2>
          <a class="home__more" href="<?= h(url('pages/favorite/favorite.php')) ?>">編集</a>
        </div>

        <p class="home__empty" id="home-favorite-empty">
          お気に入りのチームを登録すると、試合がここに並びます。<br />
          <a href="<?= h(url('pages/favorite/favorite.php')) ?>">チームを登録する</a>
        </p>

        <ul class="home__slider">
          <?php foreach ($favoriteCandidates as $match) : ?>
            <li class="home__slide is-hidden" data-favorite-slide>
              <a
                class="fav-match"
                href="<?= h($match['detailPath'] === '' ? url('pages/favorite/favorite.php') : url($match['detailPath'])) ?>"
                data-team-ids="<?= h(implode(' ', $match['teamIds'])) ?>"
              >
                <span class="fav-match__round"><?= h(match_sport_label($match['sport'])) ?></span>
                <span class="fav-match__teams"><?php $teamsVs($match, 'fav-match'); ?></span>
                <span class="fav-match__when"><?= h(home_when($match)) ?></span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>

      <section class="home__section">
        <h2 class="home__heading">今から観戦できる試合</h2>

        <?php if ($nowMatches === []) : ?>
          <p class="home__empty">これから行われる試合はまだありません。</p>
        <?php else : ?>
          <ul class="home__list">
            <?php foreach ($nowMatches as $match) : ?>
              <li>
                <a
                  class="now-match"
                  href="<?= h($match['detailPath'] === '' ? url('pages/match/match-list.php') : url($match['detailPath'])) ?>"
                  <?php if ($match['coords'] !== null) : ?>
                    data-lat="<?= (float) $match['coords']['lat'] ?>"
                    data-lng="<?= (float) $match['coords']['lng'] ?>"
                  <?php endif; ?>
                >
                  <span class="now-match__main">
                    <span class="now-match__teams"><?php $teamsVs($match, 'now-match'); ?></span>
                    <span class="now-match__info">
                      <span class="now-match__league">
                        <?= h(match_sport_label($match['sport'])) ?>
                        <?= $match['organizer'] !== '' ? '・' . h($match['organizer']) : '' ?>
                      </span>
                      <span class="now-match__title"><?= h($match['title']) ?></span>
                      <span class="now-match__meta">
                        <span class="now-match__time"><?= h(home_when($match)) ?></span>
                        <span class="now-match__distance is-hidden" data-distance>
                          <img src="<?= h(url('images/icons/point.svg')) ?>" alt="" width="11" height="14" />
                          <span data-distance-text></span>
                        </span>
                      </span>
                    </span>
                  </span>
                  <span class="now-match__ticket">
                    <span class="now-match__venue">
                      <img src="<?= h(url('images/icons/point.svg')) ?>" alt="" width="11" height="14" />
                      <?= h($match['venue']) ?>
                    </span>
                    <span class="now-match__price"><?= h(match_price_label($match['price'])) ?></span>
                  </span>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>
    </main>

    <script type="application/json" id="favorite-state"><?= favorite_state_json($favoriteState) ?></script>
    <script src="<?= h(asset('js/main.js')) ?>"></script>
    <script src="<?= h(asset('js/common/favorite-store.js')) ?>"></script>
    <script src="<?= h(asset('js/pages/home.js')) ?>"></script>
    <?php require __DIR__ . '/../menu-bar.php'; ?>
  </body>
</html>
