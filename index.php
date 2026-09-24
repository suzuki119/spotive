<?php

/**
 * index.php
 * SPOTIVE トップページ。
 * db/schema.sql の公開用ビュー v_public_tournaments から、
 * 公開中（status = 'published'）の大会を取得して一覧表示する。
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';

/** 競技コード => 表示名。schema.sql の tournaments.sport は自由入力の VARCHAR */
const SPORT_LABELS = [
  'soccer'     => 'サッカー',
  'baseball'   => '野球',
  'basketball' => 'バスケットボール',
  'volleyball' => 'バレーボール',
  'futsal'     => 'フットサル',
  'tennis'     => 'テニス',
  'badminton'  => 'バドミントン',
];

/** HTML エスケープ。出力時は必ずこれを通す */
function h(?string $value): string
{
  return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** 競技コードを表示名に変換する */
function sport_label(string $sport): string
{
  return SPORT_LABELS[$sport] ?? $sport;
}

/** 2026-10-03 18:00:00 => 10月3日(土) 18:00 */
function format_date(?string $datetime): string
{
  if ($datetime === null || $datetime === '') {
    return '';
  }

  $time = strtotime($datetime);
  if ($time === false) {
    return '';
  }

  $youbi = ['日', '月', '火', '水', '木', '金', '土'][(int) date('w', $time)];

  return date('n月j日', $time) . '(' . $youbi . ') ' . date('H:i', $time);
}

/** 参加費の表示。0 円は「無料」 */
function format_fee(int $yen): string
{
  return $yen === 0 ? '無料' : '¥' . number_format($yen);
}

// -------------------------------------------------------------------
// 検索条件（GET）
// -------------------------------------------------------------------
$sport     = trim((string) ($_GET['sport'] ?? ''));
$pref      = trim((string) ($_GET['pref'] ?? ''));
$maxFeeRaw = filter_var($_GET['max_fee'] ?? null, FILTER_VALIDATE_INT);
$maxFee    = is_int($maxFeeRaw) && $maxFeeRaw >= 0 ? $maxFeeRaw : null;
$verifiedOnly = ($_GET['verified'] ?? '') === '1';

// -------------------------------------------------------------------
// 大会の取得
// -------------------------------------------------------------------
$tournaments = [];
$prefectures = [];
$sports      = [];
$dbError     = null;

try {
  $pdo = db();

  // 絞り込み用の選択肢は、実際に公開されている大会から作る
  $prefectures = $pdo
    ->query('SELECT DISTINCT venue_prefecture FROM v_public_tournaments ORDER BY venue_prefecture')
    ->fetchAll(PDO::FETCH_COLUMN);

  $sports = $pdo
    ->query('SELECT DISTINCT sport FROM v_public_tournaments ORDER BY sport')
    ->fetchAll(PDO::FETCH_COLUMN);

  $where  = ['starts_at >= NOW()'];
  $params = [];

  if ($sport !== '') {
    $where[] = 'sport = :sport';
    $params[':sport'] = $sport;
  }

  if ($pref !== '') {
    $where[] = 'venue_prefecture = :pref';
    $params[':pref'] = $pref;
  }

  if ($maxFee !== null) {
    $where[] = 'entry_fee_yen <= :max_fee';
    $params[':max_fee'] = $maxFee;
  }

  if ($verifiedOnly) {
    $where[] = 'is_verified = 1';
  }

  $sql = 'SELECT
            id, title, sport, starts_at, ends_at,
            venue_name, venue_prefecture, venue_lat, venue_lng, is_indoor,
            entry_fee_yen, capacity, entry_closes_at,
            is_verified, organizer_name, organizer_trust_level
          FROM v_public_tournaments
          WHERE ' . implode(' AND ', $where) . '
          ORDER BY starts_at ASC
          LIMIT 30';

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $tournaments = $stmt->fetchAll();
} catch (PDOException $e) {
  // DB が未構築でもページ自体は開けるようにする（表示崩れの確認ができるように）
  $dbError = $e->getMessage();
  error_log('[SPOTIVE] DB error: ' . $dbError);
}

?>
<!DOCTYPE html>
<html lang="ja">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SPOTIVE｜スポーツを、もっと身近な日常に</title>
    <meta
      name="description"
      content="全国のスポーツ観戦情報を地図から探せる観戦マップ。地域・日時・競技・料金から、自分に合った観戦が見つかります。"
    />

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Jost:wght@400;600&family=Noto+Sans+JP:wght@400;500;700&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="css/style.css" />
  </head>

  <body>
    <header class="site-header">
      <a class="site-header__logo" href="index.php">SPOTIVE</a>

      <nav class="site-header__nav">
        <a class="site-header__link" href="pages/map/map.php">マップ</a>
        <a class="site-header__link" href="pages/match/match-list.php">試合一覧</a>
        <a class="site-header__link" href="pages/favorite/favorite.php">お気に入り</a>
        <a class="site-header__link" href="pages/account/login.php">ログイン</a>
      </nav>
    </header>

    <main class="l-main">
      <!-- ファーストビュー -->
      <section class="hero">
        <h1 class="hero__title">スポーツを、もっと身近な日常に。</h1>
        <p class="hero__lead">
          全国のスポーツ観戦情報を、地図から簡単に探せます。
        </p>
        <a class="button button--accent" href="pages/map/map.php">
          地図から探す
        </a>
      </section>

      <!-- 絞り込み -->
      <section class="search-filter">
        <h2 class="search-filter__title">条件から探す</h2>

        <form class="search-filter__form" action="index.php" method="get">
          <div class="search-filter__field">
            <label class="search-filter__label" for="sport">競技</label>
            <select class="search-filter__select" id="sport" name="sport">
              <option value="">すべての競技</option>
              <?php foreach ($sports as $option) : ?>
                <option
                  value="<?= h($option) ?>"
                  <?= $option === $sport ? 'selected' : '' ?>
                >
                  <?= h(sport_label($option)) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="search-filter__field">
            <label class="search-filter__label" for="pref">開催地域</label>
            <select class="search-filter__select" id="pref" name="pref">
              <option value="">すべての地域</option>
              <?php foreach ($prefectures as $option) : ?>
                <option
                  value="<?= h($option) ?>"
                  <?= $option === $pref ? 'selected' : '' ?>
                >
                  <?= h($option) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="search-filter__field">
            <label class="search-filter__label" for="max-fee">参加費の上限</label>
            <select class="search-filter__select" id="max-fee" name="max_fee">
              <option value="">指定しない</option>
              <?php foreach ([0, 1000, 3000, 5000, 10000] as $option) : ?>
                <option
                  value="<?= $option ?>"
                  <?= $maxFee === $option ? 'selected' : '' ?>
                >
                  <?= $option === 0 ? '無料のみ' : '¥' . number_format($option) . 'まで' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="search-filter__field search-filter__field--check">
            <input
              class="search-filter__checkbox"
              type="checkbox"
              id="verified"
              name="verified"
              value="1"
              <?= $verifiedOnly ? 'checked' : '' ?>
            />
            <label class="search-filter__label" for="verified">
              確認済みの大会のみ
            </label>
          </div>

          <button class="button button--primary" type="submit">検索する</button>
        </form>
      </section>

      <!-- 大会一覧 -->
      <section class="match-list">
        <h2 class="match-list__title">
          これから開催される大会
          <span class="match-list__count">
            <?= count($tournaments) ?>件
          </span>
        </h2>

        <?php if ($dbError !== null) : ?>
          <p class="match-list__empty">
            大会情報を読み込めませんでした。データベースの接続設定を確認してください。
          </p>
        <?php elseif ($tournaments === []) : ?>
          <p class="match-list__empty">
            条件に合う大会が見つかりませんでした。条件を変えて探してみてください。
          </p>
        <?php else : ?>
          <ul class="match-list__items">
            <?php foreach ($tournaments as $t) : ?>
              <li class="match-list__item">
                <article class="match-card">
                  <div class="match-card__body">
                    <p class="match-card__tag">
                      <?= h(sport_label($t['sport'])) ?>
                    </p>

                    <h3 class="match-card__title">
                      <a
                        class="match-card__link"
                        href="pages/match/match-detail.php?id=<?= (int) $t['id'] ?>"
                      >
                        <?= h($t['title']) ?>
                      </a>
                    </h3>

                    <?php if ((int) $t['is_verified'] === 1) : ?>
                      <p class="match-card__badge">確認済み</p>
                    <?php endif; ?>

                    <dl class="match-card__meta">
                      <dt class="match-card__meta-label">開催日時</dt>
                      <dd class="match-card__date">
                        <?= h(format_date($t['starts_at'])) ?>
                      </dd>

                      <dt class="match-card__meta-label">会場</dt>
                      <dd class="match-card__venue">
                        <?= h($t['venue_prefecture']) ?>
                        <?= h($t['venue_name']) ?>
                        <?= (int) $t['is_indoor'] === 1 ? '（屋内）' : '' ?>
                      </dd>

                      <dt class="match-card__meta-label">参加費</dt>
                      <dd class="match-card__fee">
                        <?= h(format_fee((int) $t['entry_fee_yen'])) ?>
                      </dd>

                      <dt class="match-card__meta-label">定員</dt>
                      <dd class="match-card__capacity">
                        <?= (int) $t['capacity'] ?>名
                      </dd>

                      <dt class="match-card__meta-label">主催</dt>
                      <dd class="match-card__organizer">
                        <?= h($t['organizer_name']) ?>
                      </dd>
                    </dl>

                    <?php if ($t['entry_closes_at'] !== null) : ?>
                      <p class="match-card__entry">
                        募集終了：<?= h(format_date($t['entry_closes_at'])) ?>
                      </p>
                    <?php endif; ?>
                  </div>
                </article>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>
    </main>

    <footer class="site-footer">
      <p class="site-footer__copyright">&copy; SPOTIVE</p>
    </footer>

    <script src="js/main.js"></script>
  </body>
</html>
