<?php

/**
 * pages/home/home.php
 * ホーム画面。ログインした人は index.php からここへ来る。
 *
 *   お気に入り            … 「‹ 今日 ›」で選んだ日の、タブで選んだお気に入りのチームの試合
 *                           （複数あれば横にめくる。無ければ次の試合へ案内する）
 *   今から観戦できる試合  … まだ始まっていない試合を、始まる順に。「現在地から○km以内」で絞り込める
 * 日付・チーム・距離の切り替えは js/pages/home.js が行う（現在地はサーバーに送らない）。
 *
 * お気に入りは、ログイン中なら DB、未ログインならブラウザに保存されている
 * （lib/favorite.php・js/common/favorite-store.js）。どちらでも同じように出せるよう、
 * チームの付いた試合をすべて埋めておき、お気に入りかどうかの切り替えは JS が行う。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/favorite.php';

const HOME_NOW_LIMIT = 10;   // 「今から観戦できる試合」に出す数（距離で絞っても、この数まで）
const HOME_NOW_RADIUS_KM = [1, 3, 5, 10, 30];   // 「現在地から○km以内」の選択肢

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
$decorate = static function (array $match) use ($coords): array {
  // match_teams() は、仮のロゴが両チームで同じにならないようにしてくれる
  $match['teams'] = array_map(
    static fn(array $t): array => $t + ['initial' => mb_substr($t['name'], 0, 2)],
    match_teams($match['teamIds'])
  );
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
// 距離で絞り込むと上位が外れるので、ここでは数を切らずに全部出し、JS が HOME_NOW_LIMIT 件だけ見せる
$nowMatches = array_map($decorate, array_values(array_filter(
  $upcoming,
  static fn(array $m): bool => $m['date'] > $today || $m['time'] === '' || $m['time'] >= $now
)));

// お気に入りのタブに出すチーム名（どのチームがお気に入りかは JS が決める）
$teamNamesJson = (string) json_encode(
  array_map(static fn(array $t): string => (string) ($t['name'] ?? ''), $teams),
  JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);


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
    <?php
    $appHeader = ['title' => 'SPOTIVE'];
    require __DIR__ . '/../app-header.php';
    ?>

    <main class="home">
      <section class="home__section" data-fav>
        <div class="home__head">
          <h2 class="home__heading">お気に入り</h2>
          <a class="home__more" href="<?= h(url('pages/favorite/favorite.php')) ?>">編集</a>
        </div>

        <p class="home__empty" id="home-favorite-empty">
          お気に入りのチームを登録すると、試合がここに並びます。<br />
          <a href="<?= h(url('pages/favorite/favorite.php')) ?>">チームを登録する</a>
        </p>

        <div class="fav-browser" data-fav-browser hidden>
          <!-- ‹ 今日 › -->
          <div class="fav-browser__days">
            <button class="fav-browser__day-button" type="button" data-day-step="-1" aria-label="前の日">‹</button>
            <p class="fav-browser__day" data-day-label aria-live="polite">今日</p>
            <button class="fav-browser__day-button" type="button" data-day-step="1" aria-label="次の日">›</button>
          </div>

          <!-- お気に入りのチームのタブ（JS が作る） -->
          <div class="fav-browser__tabs" role="tablist" aria-label="お気に入りのチーム" data-team-tabs></div>

          <ul class="home__slider" data-fav-slider>
            <?php foreach ($favoriteCandidates as $match) : ?>
              <li class="home__slide" data-favorite-slide data-date="<?= h($match['date']) ?>" hidden>
                <a
                  class="fav-match"
                  href="<?= h($match['detailPath'] === '' ? url('pages/favorite/favorite.php') : url($match['detailPath'])) ?>"
                  data-team-ids="<?= h(implode(' ', $match['teamIds'])) ?>"
                >
                  <?php $homeTeam = $match['teams'][0] ?? null; $awayTeam = $match['teams'][1] ?? null; ?>
                  <span class="fav-match__side">
                    <?php if ($homeTeam !== null && $homeTeam['logo'] !== '') : ?>
                      <img class="fav-match__logo" src="<?= h(url($homeTeam['logo'])) ?>" alt="<?= h($homeTeam['name']) ?>" width="72" height="72" />
                    <?php endif; ?>
                  </span>
                  <span class="fav-match__center">
                    <span class="fav-match__round"><?= h(match_league_label($match)) ?></span>
                    <span class="fav-match__time"><?= $match['time'] === '' ? '時間未定' : h($match['time']) ?></span>
                    <span class="fav-match__venue">
                      <img src="<?= h(url('images/icons/point.svg')) ?>" alt="" width="11" height="14" />
                      <?= h($match['venue']) ?>
                    </span>
                  </span>
                  <span class="fav-match__side">
                    <?php if ($awayTeam !== null && $awayTeam['logo'] !== '') : ?>
                      <img class="fav-match__logo" src="<?= h(url($awayTeam['logo'])) ?>" alt="<?= h($awayTeam['name']) ?>" width="72" height="72" />
                    <?php endif; ?>
                  </span>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>

          <!-- その日に試合が無いとき -->
          <p class="fav-browser__none" data-fav-none hidden>
            <span data-fav-none-text>この日の試合はありません。</span>
            <button class="fav-browser__next" type="button" data-fav-next hidden></button>
          </p>

          <!-- 何枚目か（2 枚以上のとき） -->
          <div class="fav-browser__dots" data-fav-dots aria-hidden="true"></div>
        </div>
      </section>

      <section class="home__section">
        <h2 class="home__heading">今から観戦できる試合</h2>
        <label class="home__radius">
          <span class="home__radius-label">表示する範囲</span>
          <select class="home__radius-select" data-radius>
            <option value="">すべての試合</option>
            <?php foreach (HOME_NOW_RADIUS_KM as $km) : ?>
              <option value="<?= (int) $km ?>">現在地から<?= (int) $km ?>km以内</option>
            <?php endforeach; ?>
          </select>
        </label>
        <p class="home__status" data-radius-status hidden></p>
        <p class="home__empty" data-now-empty hidden>この範囲で、これから観戦できる試合はありません。</p>

        <?php if ($nowMatches === []) : ?>
          <p class="home__empty">これから行われる試合はまだありません。</p>
        <?php else : ?>
          <ul class="home__list" data-now-list data-limit="<?= HOME_NOW_LIMIT ?>">
            <?php foreach ($nowMatches as $i => $match) : ?>
              <li data-now-item <?= $i < HOME_NOW_LIMIT ? '' : 'hidden' ?>>
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
                        <?= h(match_league_label($match)) ?>
                        <?= $match['organizer'] !== '' ? '・' . h($match['organizer']) : '' ?>
                      </span>
                      <span class="now-match__title">
                        <?php if (count($match['teams']) === 2) : ?>
                          <?= h($match['teams'][0]['name']) ?><span class="now-match__title-vs">vs</span><?= h($match['teams'][1]['name']) ?>
                        <?php else : ?>
                          <?= h($match['title']) ?>
                        <?php endif; ?>
                      </span>
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
    <script type="application/json" id="team-names"><?= $teamNamesJson ?></script>
    <script src="<?= h(asset('js/main.js')) ?>"></script>
    <script src="<?= h(asset('js/common/favorite-store.js')) ?>"></script>
    <script src="<?= h(asset('js/pages/home.js')) ?>"></script>
    <?php require __DIR__ . '/../menu-bar.php'; ?>
  </body>
</html>
