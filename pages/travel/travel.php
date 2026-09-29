<?php

/**
 * pages/travel/travel.php
 * AWAY TRAVEL（主要機能 5「遠征サポート」）。設計は docs/away-travel.md。
 * 応援チームの次のアウェー戦と、交通・ホテル・費用の目安、遠征中に観られる他の試合を出し、
 * 「この条件でプランを作成」で観戦プラン（travel-plan.php）を作る。
 *
 *   GET team  … 応援チーム（data/teams.json の id）
 *   GET game  … 遠征の試合（data/matches.json の id）。team が無ければ、この試合のアウェー側のチームにする
 *   GET from  … 出発地（data/areas.json の id）。省略時はチームの本拠地
 *   GET hotel … ホテル（data/hotels.json の id）。省略時は会場に一番近いホテル
 *   POST      … プランの作成（ログインが必要）
 *
 * 画面は誰でも見られる。金額はすべて目安（交通は距離からの計算、ホテルは仮データ）。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/travel.php';
require_once __DIR__ . '/../../lib/favorite.php';

// -------------------------------------------------------------------
// プランの作成（POST）
// -------------------------------------------------------------------
$error = null;
$input = $_GET;

if (is_post()) {
  $user  = require_login();
  $input = $_POST;
  try {
    csrf_verify();
    $planId = travel_create_plan($user, $_POST);
    flash('観戦プランを作成しました。', 'success');
    redirect('travel-plan.php?id=' . $planId);
  } catch (AppError $e) {
    $error = $e->getMessage();
  } catch (PDOException $e) {
    error_log('[SPOTIVE] DB error: ' . $e->getMessage());
    $error = 'ただいまプランを作成できません。時間をおいてお試しください。';
  }
}

// -------------------------------------------------------------------
// 条件の受け取り
// -------------------------------------------------------------------
$teams   = load_teams();
$areas   = travel_areas();
$teamId  = input_string($input, 'team');
$gameRef = input_string($input, 'game');
$fromId  = input_string($input, 'from');
$hotelId = input_string($input, 'hotel');
$picked  = array_filter((array) ($input['extras'] ?? []), 'is_string');

$user        = null;
$favoriteIds = [];
try {
  $user = current_user();
  if ($user !== null) {
    $favoriteIds = array_values(array_filter(favorite_team_ids((int) $user['id']), static fn(string $id): bool => isset($teams[$id])));
  }
} catch (PDOException $e) {
  error_log('[SPOTIVE] DB error: ' . $e->getMessage());
}

// 試合だけ渡されたら（ホテル詳細の「遠征プランに追加」など）、そのアウェー側のチームにする
if (!isset($teams[$teamId]) && $gameRef !== '') {
  $teamId = (string) (travel_find_game($gameRef)['away'] ?? '');
}
// チームが決まらなければ、お気に入り → アウェー戦のあるチームの順で選ぶ
if (!isset($teams[$teamId])) {
  $teamId = '';
  foreach ([...$favoriteIds, ...array_keys($teams)] as $id) {
    if (travel_trips($id) !== []) {
      $teamId = $id;
      break;
    }
  }
  $teamId = $teamId === '' ? (string) array_key_first($teams) : $teamId;
}

$trips = $teamId === '' ? [] : travel_trips($teamId);
$trip  = ($gameRef === '' ? null : travel_find_trip($teamId, $gameRef)) ?? ($trips[0] ?? null);
$area  = $areas[$fromId] ?? $areas[(string) ($teams[$teamId]['areaId'] ?? '')] ?? reset($areas);

$estimate = $trip === null || $area === false ? null : travel_estimate($trip['games'], $area, $hotelId);
$nearby   = $trip === null ? [] : travel_nearby_games($trip);
$teamName = (string) ($teams[$teamId]['name'] ?? '');

/** 今の条件を引き継いだ、この画面へのリンク */
$link = static function (array $override) use ($teamId, $trip, $area, $estimate): string {
  $query = [
    'team'  => $teamId,
    'game'  => $trip['games'][0]['ref'] ?? '',
    'from'  => $area === false ? '' : (string) $area['id'],
    'hotel' => (string) ($estimate['hotel']['id'] ?? ''),
  ];
  return 'travel.php?' . http_build_query(array_filter(array_merge($query, $override), static fn($v): bool => $v !== ''));
};

// お気に入りのチームを先に並べる
$teamGroups = [
  'お気に入り'   => array_intersect_key($teams, array_flip($favoriteIds)),
  'すべてのチーム' => array_diff_key($teams, array_flip($favoriteIds)),
];

?>
<!DOCTYPE html>
<html lang="ja">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>遠征サポート | SPOTIVE</title>

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
    $appHeader = ['title' => 'AWAY TRAVEL', 'noticeIcon' => 'mail.svg', 'noticeLabel' => 'Notice'];
    require __DIR__ . '/../app-header.php';
    ?>

    <main class="travel">
      <!-- 応援チーム -->
      <form class="travel-team" action="travel.php" method="get" data-auto-submit>
        <label class="travel-team__label" for="travel-team">応援するチーム</label>
        <select class="travel-team__select" id="travel-team" name="team">
          <?php foreach ($teamGroups as $group => $groupTeams) : ?>
            <?php if ($groupTeams !== []) : ?>
              <optgroup label="<?= h($group) ?>">
                <?php foreach ($groupTeams as $id => $team) : ?>
                  <option value="<?= h((string) $id) ?>" <?= $id === $teamId ? 'selected' : '' ?>>
                    <?= h((string) ($team['name'] ?? '')) ?>
                  </option>
                <?php endforeach; ?>
              </optgroup>
            <?php endif; ?>
          <?php endforeach; ?>
        </select>
        <button class="travel-team__go" type="submit" data-auto-submit-button>表示</button>
      </form>

      <?php if ($error !== null) : ?>
        <p class="travel__error" role="alert"><?= h($error) ?></p>
      <?php endif; ?>

      <!-- 次のアウェー戦 -->
      <section class="travel__section">
        <h2 class="travel__heading">NEXT AWAY GAME</h2>

        <?php if ($trip === null || $estimate === null) : ?>
          <p class="travel__empty"><?= h($teamName) ?>の、これから先のアウェー戦はありません。</p>
        <?php else : ?>
          <div class="travel-dates">
            <p class="travel-dates__range">
              <?= h(travel_date_label($trip['start'])) ?>
              <?php if ($trip['end'] !== $trip['start']) : ?>
                〜<?= h(travel_date_label($trip['end'], true)) ?>
              <?php endif; ?>
            </p>
            <?php if (count($trips) > 1) : ?>
              <details class="travel-choice">
                <summary class="travel-choice__toggle">日程を変更</summary>
                <ul class="travel-choice__list">
                  <?php foreach ($trips as $other) : ?>
                    <li>
                      <a
                        class="travel-choice__link <?= $other['games'][0]['ref'] === $trip['games'][0]['ref'] ? 'is-active' : '' ?>"
                        href="<?= h($link(['game' => $other['games'][0]['ref'], 'hotel' => ''])) ?>"
                      >
                        <?= h(travel_date_label($other['start'], true)) ?>
                        <?= $other['end'] !== $other['start'] ? '〜' . h(travel_date_label($other['end'], true)) : '' ?>
                        ・<?= h($other['venue']) ?>
                      </a>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </details>
            <?php endif; ?>
          </div>

          <ul class="travel__games">
            <?php foreach ($trip['games'] as $game) : ?>
              <li class="travel__game">
                <?php $match = $game['row']; require __DIR__ . '/../match/match-card.php'; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>

      <?php if ($trip !== null && $estimate !== null) : ?>
        <!-- 交通とホテル -->
        <section class="travel__section">
          <div class="travel__heading-row">
            <h2 class="travel__heading">TRAVEL PLAN</h2>
            <form class="travel-from" action="travel.php" method="get" data-auto-submit>
              <input type="hidden" name="team" value="<?= h($teamId) ?>" />
              <input type="hidden" name="game" value="<?= h($trip['games'][0]['ref']) ?>" />
              <input type="hidden" name="hotel" value="<?= h($hotelId) ?>" />
              <label class="travel-from__label" for="travel-from">出発地</label>
              <select class="travel-from__select" id="travel-from" name="from">
                <?php foreach ($areas as $id => $option) : ?>
                  <option value="<?= h((string) $id) ?>" <?= $id === $area['id'] ? 'selected' : '' ?>>
                    <?= h((string) $option['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <button class="travel-from__go" type="submit" data-auto-submit-button>変更</button>
            </form>
          </div>

          <div class="travel-cards">
            <article class="travel-card">
              <p class="travel-card__head">
                <img class="travel-card__icon" src="<?= h(url('images/icons/train.svg')) ?>" alt="" width="20" height="20" />
                交通手段
                <span class="travel-card__meta"><?= h(travel_duration_label($estimate['transport']['minutes'])) ?></span>
              </p>
              <p class="travel-card__route">
                <?= h((string) $area['name']) ?>→<?= h(travel_place_label($estimate['first'])) ?>
              </p>
              <p class="travel-card__price">往復 <?= h(travel_yen($estimate['cost']['transport'])) ?>〜</p>
              <p class="travel-card__note"><?= h($estimate['transport']['label']) ?>・距離からの目安</p>
            </article>

            <article class="travel-card">
              <p class="travel-card__head">
                <img class="travel-card__icon" src="<?= h(url('images/icons/hotel.svg')) ?>" alt="" width="20" height="20" />
                ホテル
                <?php if (($estimate['hotel']['walk'] ?? null) !== null) : ?>
                  <span class="travel-card__meta">徒歩<?= (int) $estimate['hotel']['walk'] ?>分</span>
                <?php endif; ?>
              </p>
              <?php if ($estimate['hotel'] === null) : ?>
                <p class="travel-card__route">日帰り</p>
                <p class="travel-card__price">宿泊なし</p>
                <p class="travel-card__note">試合のあとに帰れる時間です</p>
              <?php else : ?>
                <p class="travel-card__route"><?= h($estimate['hotel']['name']) ?></p>
                <p class="travel-card__price">1泊 <?= h(travel_yen($estimate['hotel']['price'])) ?>〜</p>
                <p class="travel-card__note"><?= (int) $estimate['hotel']['nights'] ?>泊・<?= h(travel_date_label(date('Y-m-d', $estimate['hotel']['checkinAt']), true)) ?>から</p>
                <?php if (count($estimate['hotels']) > 1) : ?>
                  <details class="travel-choice travel-choice--card">
                    <summary class="travel-choice__toggle">ホテルを変える</summary>
                    <ul class="travel-choice__list">
                      <?php foreach ($estimate['hotels'] as $option) : ?>
                        <li>
                          <a
                            class="travel-choice__link <?= $option['id'] === $estimate['hotel']['id'] ? 'is-active' : '' ?>"
                            href="<?= h($link(['hotel' => $option['id']])) ?>"
                          >
                            <?= h($option['name']) ?>（徒歩<?= (int) $option['walk'] ?>分・<?= h(travel_yen($option['price'])) ?>〜）
                          </a>
                        </li>
                      <?php endforeach; ?>
                    </ul>
                  </details>
                <?php endif; ?>
              <?php endif; ?>
            </article>
          </div>
        </section>

        <form class="travel__create" action="travel.php" method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="team" value="<?= h($teamId) ?>" />
          <input type="hidden" name="game" value="<?= h($trip['games'][0]['ref']) ?>" />
          <input type="hidden" name="from" value="<?= h((string) $area['id']) ?>" />
          <input type="hidden" name="hotel" value="<?= h((string) ($estimate['hotel']['id'] ?? '')) ?>" />

          <!-- 遠征先でもう 1 試合（企画書の機能 5 の中心） -->
          <section class="travel__section">
            <h2 class="travel__heading">ONE MORE GAME</h2>
            <p class="travel__lead">この遠征の前後に、会場の近くで観られる試合です。チェックするとプランに追加します。</p>
            <?php if ($nearby === []) : ?>
              <p class="travel__empty">この遠征の前後に、近くで観られる試合はありません。</p>
            <?php else : ?>
              <ul class="travel__games">
                <?php foreach ($nearby as $game) : ?>
                  <li class="travel-extra">
                    <label class="travel-extra__check">
                      <input
                        class="travel-extra__input"
                        type="checkbox"
                        name="extras[]"
                        value="<?= h($game['ref']) ?>"
                        <?= in_array($game['ref'], $picked, true) ? 'checked' : '' ?>
                      />
                      <span>プランに追加（会場から約<?= h(hotel_distance_label($game['km'])) ?>）</span>
                    </label>
                    <?php $match = $game['row']; require __DIR__ . '/../match/match-card.php'; ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </section>

          <!-- 費用の目安 -->
          <section class="travel__section">
            <h2 class="travel__heading">ESTIMATED COST</h2>
            <div class="travel-cost">
              <p class="travel-cost__caption">1人あたりの目安</p>
              <p class="travel-cost__total"><?= h(travel_yen($estimate['cost']['total'])) ?>〜</p>
              <dl class="travel-cost__list">
                <div class="travel-cost__row">
                  <dt>交通（往復）</dt>
                  <dd><?= h(travel_yen($estimate['cost']['transport'])) ?></dd>
                </div>
                <div class="travel-cost__row">
                  <dt>宿泊</dt>
                  <dd><?= h(travel_yen($estimate['cost']['hotel'])) ?></dd>
                </div>
                <div class="travel-cost__row">
                  <dt>チケット</dt>
                  <dd><?= h(travel_yen($estimate['cost']['ticket'])) ?><?= $estimate['cost']['ticket_unknown'] ? '＋未定' : '' ?></dd>
                </div>
                <div class="travel-cost__row">
                  <dt>食事ほか</dt>
                  <dd><?= h(travel_yen($estimate['cost']['meal'])) ?></dd>
                </div>
              </dl>
              <p class="travel-cost__note">
                交通は距離からの計算、ホテルは仮のデータによる目安です。
                試合を追加したときのチケット代・宿泊は、プランを作るときに計算し直します。
              </p>
            </div>
          </section>

          <?php if ($user !== null) : ?>
            <button class="travel__cta" type="submit">
              この条件でプランを作成
              <img src="<?= h(url('images/icons/map.svg')) ?>" alt="" width="16" height="20" />
            </button>
          <?php else : ?>
            <a class="travel__cta" href="<?= h(url('pages/account/signin.php') . '?next=' . urlencode(current_url())) ?>">
              ログインしてプランを作成
            </a>
          <?php endif; ?>
        </form>
      <?php endif; ?>

      <?php if ($user !== null) : ?>
        <p class="travel__more"><a href="travel-plan.php">作成した観戦プランを見る</a></p>
      <?php endif; ?>
    </main>

    <script src="<?= h(asset('js/main.js')) ?>"></script>
    <script src="<?= h(asset('js/pages/travel.js')) ?>"></script>
    <?php require __DIR__ . '/../menu-bar.php'; ?>
  </body>
</html>
