<?php

/**
 * pages/match/match-card.php
 * 試合カード 1 枚。試合一覧・お気に入りの両方から require して使う。
 *
 *   左 … 両チームのロゴ（vs）と、その下に競技名
 *   右 … リーグと節（B.PREMIER 第1節 GAME2）、対戦カード、日付｜開始時間、会場、料金
 *
 * 読み込む前に $match（lib/match.php の match_row() の形）を用意しておくこと。
 * data-team-ids は js/common/favorite-store.js がお気に入りの判定に使う。
 */

declare(strict_types=1);

/** @var array<string,mixed> $match */
if (!isset($match)) {
  return;   // URL で直接開かれたときは何も出さない
}

$place = $match['pref'] === '' ? $match['venue'] : $match['venue'] . '（' . $match['pref'] . '）';
$teams = match_teams($match['teamIds']);   // 2 チームそろわない大会は空
$sport = match_sport_key($match['sport']);

?>
<article
  class="match-card"
  data-team-ids="<?= h(implode(' ', $match['teamIds'])) ?>"
>
  <?php if ($teams !== [] && $teams[0]['logo'] !== '' && $teams[1]['logo'] !== '') : ?>
    <!-- 背景に薄く置くロゴ。飾りなので読み上げない -->
    <span class="match-card__logos" aria-hidden="true">
      <?php foreach ($teams as $team) : ?>
        <img class="match-card__logo" src="<?= h(url($team['logo'])) ?>" alt="" width="96" height="96" loading="lazy" />
      <?php endforeach; ?>
    </span>
  <?php endif; ?>

  <div class="match-card__side">
    <div class="match-card__emblems">
      <?php if ($teams === []) : ?>
        <span class="match-card__emblem match-card__emblem--<?= h($sport) ?>" aria-hidden="true">
          <?= h(mb_substr(match_sport_label($match['sport']), 0, 2)) ?>
        </span>
      <?php else : ?>
        <?php foreach ($teams as $i => $team) : ?>
          <?php if ($i === 1) : ?>
            <span class="match-card__emblem-vs" aria-hidden="true">vs</span>
          <?php endif; ?>
          <?php if ($team['logo'] !== '') : ?>
            <img class="match-card__emblem" src="<?= h(url($team['logo'])) ?>" alt="<?= h($team['name']) ?>" width="48" height="48" loading="lazy" />
          <?php else : ?>
            <span class="match-card__emblem match-card__emblem--<?= h($sport) ?>" role="img" aria-label="<?= h($team['name']) ?>">
              <?= h(mb_substr($team['name'], 0, 2)) ?>
            </span>
          <?php endif; ?>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <span class="match-card__sport match-card__sport--<?= h($sport) ?>"><?= h(match_sport_label($match['sport'])) ?></span>
  </div>

  <div class="match-card__body">
    <!-- 「B.PREMIER 第1節 GAME2」。リーグの情報が無い大会は主催者名 -->
    <p class="match-card__sub">
      <?php if ($match['league'] === '' && $match['organizer'] !== '') : ?>
        主催：<?= h($match['organizer']) ?>
      <?php else : ?>
        <?= h(match_league_label($match)) ?>
      <?php endif; ?>
    </p>

    <h3 class="match-card__title">
      <?php if ($match['detailPath'] !== '') : ?>
        <a class="match-card__link" href="<?= h(url($match['detailPath'])) ?>">
      <?php endif; ?>
      <?php if ($teams !== []) : ?>
        <?= h($teams[0]['name']) ?><span class="match-card__vs">vs</span><?= h($teams[1]['name']) ?>
      <?php else : ?>
        <?= h($match['title']) ?>
      <?php endif; ?>
      <?php if ($match['detailPath'] !== '') : ?>
        </a>
      <?php endif; ?>
    </h3>

    <p class="match-card__when">
      <span class="match-card__date"><?= h(match_short_date($match['date'])) ?></span>
      <span class="match-card__time"><?= $match['time'] === '' ? '時間未定' : h($match['time']) . '〜' ?></span>
    </p>

    <p class="match-card__place">
      <img src="<?= h(url('images/icons/point.svg')) ?>" alt="" width="11" height="14" />
      <?= h($place) ?>
    </p>

    <p class="match-card__foot">
      <span class="match-card__price"><?= h(match_price_label($match['price'])) ?></span>
      <span class="match-card__favorite is-hidden">お気に入り</span>
    </p>
  </div>
</article>
