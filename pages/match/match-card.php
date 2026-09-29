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

?>
<article
  class="match-card"
  data-team-ids="<?= h(implode(' ', $match['teamIds'])) ?>"
>
  <p class="match-card__time"><?= $match['time'] === '' ? '未定' : h($match['time']) ?></p>

  <div class="match-card__body">
    <p class="match-card__tags">
      <span class="match-card__sport match-card__sport--<?= h(match_sport_key($match['sport'])) ?>">
        <?= h(match_sport_label($match['sport'])) ?>
      </span>
      <span class="match-card__favorite is-hidden">お気に入り</span>
    </p>
    <h3 class="match-card__title"><?= h($match['title']) ?></h3>
    <p class="match-card__venue"><?= h($place) ?></p>
    <?php if ($match['organizer'] !== '') : ?>
      <p class="match-card__organizer">主催：<?= h($match['organizer']) ?></p>
    <?php endif; ?>
  </div>

  <p class="match-card__price"><?= h(match_price_label($match['price'])) ?></p>
</article>
