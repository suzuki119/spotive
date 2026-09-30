<?php

/**
 * pages/travel/travel-plan.php
 * 観戦プラン（主要機能 5「遠征サポート」）。設計は docs/away-travel.md。
 *
 *   GET id なし … 自分のプランの一覧
 *   GET id あり … 予定表（日付ごと・時刻順）。他人のプランは開けない
 *   POST action=toggle … 「購入済み」「予約済み」の印を切り替える（item_id）
 *   POST action=delete … プランを削除する（plan_id）
 *
 * 「購入済み」はユーザーが自分で付ける印。SPOTIVE はチケットや切符を売らないので、本当に買ったかは分からない。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/travel.php';

$user   = require_login();
$userId = (int) $user['id'];
$planId = (int) ($_GET['id'] ?? 0);
$error  = null;

// -------------------------------------------------------------------
// 印の切り替え・削除（POST）
// -------------------------------------------------------------------
if (is_post()) {
  try {
    csrf_verify();
    $planId = (int) ($_POST['plan_id'] ?? 0);

    switch ((string) ($_POST['action'] ?? '')) {
      case 'toggle':
        travel_toggle_booking($userId, (int) ($_POST['item_id'] ?? 0));
        redirect('travel-plan.php?id=' . $planId);
        // no break

      case 'delete':
        travel_delete_plan($userId, $planId);
        flash('観戦プランを削除しました。', 'success');
        redirect('travel-plan.php');
        // no break

      default:
        throw new AppError('操作が不正です。');
    }
  } catch (AppError $e) {
    $error = $e->getMessage();
  } catch (PDOException $e) {
    error_log('[SPOTIVE] DB error: ' . $e->getMessage());
    $error = 'ただいま保存できません。時間をおいてお試しください。';
  }
}

// -------------------------------------------------------------------
// プランの取得
// -------------------------------------------------------------------
$plan  = null;
$plans = [];
$days  = [];

try {
  if ($planId > 0) {
    $plan = travel_plan_owned($planId, $userId);
    $days = travel_timeline($plan['items']);
  } else {
    $plans = travel_plans_mine($userId);
  }
} catch (AppError $e) {
  http_response_code(404);
  $error = $e->getMessage();
} catch (PDOException $e) {
  error_log('[SPOTIVE] DB error: ' . $e->getMessage());
  $error = '観戦プランを読み込めませんでした。';
}

$teams    = load_teams();
$messages = take_flash();

?>
<!DOCTYPE html>
<html lang="ja">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= h($plan === null ? '観戦プラン' : (string) $plan['title']) ?> | SPOTIVE</title>

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
    $appHeader = ['title' => 'AWAY TRAVEL', 'back' => $plan === null ? 'pages/travel/travel.php' : 'pages/travel/travel-plan.php'];
    require __DIR__ . '/../app-header.php';
    ?>

    <main class="travel">
      <?php foreach ($messages as $message) : ?>
        <p class="travel__notice"><?= h($message['message']) ?></p>
      <?php endforeach; ?>
      <?php if ($error !== null) : ?>
        <p class="travel__error" role="alert"><?= h($error) ?></p>
      <?php endif; ?>

      <?php if ($plan !== null) : ?>
        <h1 class="travel-plan__title"><?= h((string) $plan['title']) ?></h1>

        <?php foreach ($days as $date => $rows) : ?>
          <section class="travel-plan__day">
            <h2 class="travel-plan__date"><?= h(travel_date_label($date, true)) ?></h2>

            <ol class="travel-timeline">
              <?php foreach ($rows as $row) : ?>
                <?php $item = $row['item']; ?>
                <li class="travel-timeline__row">
                  <?php if ($row['text'] !== null) : ?>
                    <p class="travel-timeline__label">
                      <span class="travel-timeline__time"><?= h((string) $row['time']) ?></span>
                      <?= h($row['text']) ?>
                    </p>
                  <?php endif; ?>

                  <?php if ($item !== null) : ?>
                    <?php
                    $booked = $item['booking_status'] === 'booked';
                    $labels = TRAVEL_BOOKING_LABELS[$item['item_type']];
                    $detail = travel_item_detail_path($item);
                    $start  = (int) strtotime((string) $item['starts_at']);
                    $end    = (int) strtotime((string) $item['ends_at']);
                    ?>
                    <article class="travel-item travel-item--<?= h((string) $item['item_type']) ?> <?= $booked ? 'is-booked' : '' ?>">
                      <?php if ($item['item_type'] === 'transport') : ?>
                        <p class="travel-item__sub"><?= h((string) $item['title']) ?></p>
                        <p class="travel-item__route">
                          <?= h((string) $item['from_label']) ?><small>（<?= h(date('H:i', $start)) ?>発）</small>
                          →
                          <?= h((string) $item['to_label']) ?><small>（<?= h(date('H:i', $end)) ?>着）</small>
                        </p>
                      <?php elseif ($item['item_type'] === 'match') : ?>
                        <p class="travel-item__sub"><?= h((string) $item['from_label']) ?></p>
                        <p class="travel-item__name"><?= h((string) $item['title']) ?></p>
                        <p class="travel-item__route"><?= h(date('m/d', $start)) ?>｜<?= h(date('H:i', $start)) ?>〜</p>
                      <?php else : ?>
                        <p class="travel-item__name"><?= h((string) $item['title']) ?></p>
                        <p class="travel-item__route">
                          <?= h(date('m/d', $start)) ?><small>（<?= h(date('H:i', $start)) ?>〜）</small>
                          →
                          <?= h(date('m/d', $end)) ?><small>（〜<?= h(date('H:i', $end)) ?>）</small>
                        </p>
                      <?php endif; ?>

                      <?php if ($item['price_yen'] !== null) : ?>
                        <p class="travel-item__price">
                          <?= h(travel_yen((int) $item['price_yen'])) ?><?= $item['item_type'] === 'hotel' ? '/1泊' : '' ?><?= $item['item_type'] === 'match' ? '〜' : '' ?>
                        </p>
                      <?php endif; ?>
                    </article>

                    <div class="travel-item__actions">
                      <?php if ($item['url'] !== null) : ?>
                        <a class="travel-item__link" href="<?= h((string) $item['url']) ?>" target="_blank" rel="noopener">
                          <?= $item['item_type'] === 'match' ? 'チケット販売サイトへ' : '予約サイトへ' ?>
                        </a>
                      <?php elseif ($detail !== '') : ?>
                        <a class="travel-item__link travel-item__link--sub" href="<?= h(url($detail)) ?>">
                          <?= $item['item_type'] === 'hotel' ? 'ホテルの詳細' : '試合の詳細' ?>
                        </a>
                      <?php endif; ?>
                      <form action="travel-plan.php" method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="toggle" />
                        <input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>" />
                        <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>" />
                        <button class="travel-item__toggle <?= $booked ? 'is-booked' : '' ?>" type="submit" aria-pressed="<?= $booked ? 'true' : 'false' ?>">
                          <?= h($booked ? $labels['done'] . ' ✓' : $labels['todo']) ?>
                        </button>
                      </form>
                    </div>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ol>
          </section>
        <?php endforeach; ?>

        <section class="travel-cost">
          <p class="travel-cost__caption">1人あたりの目安（作成した時点）</p>
          <p class="travel-cost__total"><?= h(travel_yen((int) $plan['estimated_total_yen'])) ?>〜</p>
          <p class="travel-cost__note">交通は距離からの計算、ホテルは仮のデータによる目安です。「購入済み」はご自身の記録用の印です。</p>
        </section>

        <form class="travel-plan__delete" action="travel-plan.php" method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete" />
          <input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>" />
          <button class="travel-plan__delete-button" type="submit">このプランを削除する</button>
        </form>

      <?php elseif ($planId === 0) : ?>
        <h1 class="travel-plan__title">観戦プラン</h1>

        <?php if ($plans === []) : ?>
          <p class="travel__empty">まだ観戦プランがありません。</p>
        <?php else : ?>
          <ul class="travel-plans">
            <?php foreach ($plans as $row) : ?>
              <li>
                <a class="travel-plans__link" href="travel-plan.php?id=<?= (int) $row['id'] ?>">
                  <span class="travel-plans__name"><?= h((string) $row['title']) ?></span>
                  <span class="travel-plans__meta">
                    <?= h(travel_date_label((string) $row['start_date'])) ?>
                    <?= $row['end_date'] !== $row['start_date'] ? '〜' . h(travel_date_label((string) $row['end_date'], true)) : '' ?>
                    ・<?= h(travel_yen((int) $row['estimated_total_yen'])) ?>〜
                  </span>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      <?php endif; ?>

      <p class="travel__more"><a href="travel.php">遠征の試合を探す</a></p>
    </main>

    <script src="<?= h(asset('js/main.js')) ?>"></script>
    <?php require __DIR__ . '/../menu-bar.php'; ?>
  </body>
</html>
