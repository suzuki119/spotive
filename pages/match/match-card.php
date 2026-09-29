<?php

/**
 * pages/match/match-card.php
 * 試合カード 1 枚。試合一覧・お気に入りの両方から require して使う。
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

// 背景に薄く置く両チームのロゴ。2 チームそろっている試合だけ出す
$logos = [];
foreach (array_slice($match['teamIds'], 0, 2) as $side => $teamId) {
  $logo = match_team_logo($teamId, $side);
  if ($logo !== '') {
    $logos[] = $logo;
  }
}

?>
<article
  class="match-card"
  data-team-ids="<?= h(implode(' ', $match['teamIds'])) ?>"
>
  <?php if (count($logos) === 2) : ?>
    <!-- 飾りなので読み上げない。チーム名は試合名に書いてある -->
    <span class="match-card__logos" aria-hidden="true">
      <?php foreach ($logos as $logo) : ?>
        <img class="match-card__logo" src="<?= h(url($logo)) ?>" alt="" width="96" height="96" loading="lazy" />
      <?php endforeach; ?>
    </span>
  <?php endif; ?>

  <p class="match-card__time"><?= $match['time'] === '' ? '未定' : h($match['time']) ?></p>

  <div class="match-card__body">
    <p class="match-card__tags">
      <span class="match-card__sport match-card__sport--<?= h(match_sport_key($match['sport'])) ?>">
        <?= h(match_sport_label($match['sport'])) ?>
      </span>
      <span class="match-card__favorite is-hidden">お気に入り</span>
    </p>
    <h3 class="match-card__title">
      <?php if ($match['detailPath'] !== '') : ?>
        <a class="match-card__link" href="<?= h(url($match['detailPath'])) ?>"><?= h($match['title']) ?></a>
      <?php else : ?>
        <?= h($match['title']) ?>
      <?php endif; ?>
    </h3>
    <p class="match-card__venue"><?= h($place) ?></p>
    <?php if ($match['organizer'] !== '') : ?>
      <p class="match-card__organizer">主催：<?= h($match['organizer']) ?></p>
    <?php endif; ?>
  </div>

  <p class="match-card__price"><?= h(match_price_label($match['price'])) ?></p>
</article>
