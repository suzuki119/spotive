<?php

/**
 * pages/setting/setting.php
 * 設定メニューのトップ＝マイページ。
 * 信頼レベルと「次に何をすればよいか」、各設定への導線をまとめる。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/account.php';
require_once __DIR__ . '/../../lib/tournament.php';
require_once __DIR__ . '/../../lib/layout.php';

$user     = require_login();
$overview = account_overview((int) $user['id']);
$myEvents = (int) $user['trust_level'] >= (int) config('verification.require_level.create_tournament', LEVEL_ORGANIZER)
  ? tournament_list_mine((int) $user['id'])
  : [];

$base = base_path();

page_header('マイページ', 'マイページ');
?>

<section class="level-card">
  <p class="level-card__name"><?= h((string) $user['nickname']) ?> さん</p>
</section>

<?php if ($myEvents !== []) : ?>
  <section class="status-list">
    <h2 class="form-page__subtitle">自分の大会</h2>
    <ul class="event-list">
      <?php foreach (array_slice($myEvents, 0, 5) as $event) : ?>
        <li class="event-list__item">
          <a href="<?= h($base) ?>/pages/organizer/tournament-new.php?id=<?= (int) $event['id'] ?>">
            <?= h((string) $event['title']) ?>
          </a>
          <span class="event-list__meta">
            <?= h(format_datetime((string) $event['starts_at'])) ?>
            ・<?= h(status_label((string) $event['status'])) ?>
            ・<?= h(status_label((string) $event['verification_status'])) ?>
          </span>
        </li>
      <?php endforeach; ?>
    </ul>
    <p class="form-page__note">
      <a href="<?= h($base) ?>/pages/organizer/dashboard.php">主催者ページで管理する</a>
    </p>
  </section>
<?php endif; ?>

<nav class="setting-menu">
  <h2 class="form-page__subtitle">設定</h2>
  <ul class="setting-menu__list">
    <li><a href="profile.php">プロフィール設定</a></li>
    <li><a href="notification-setting.php">通知設定</a></li>
    <li><a href="account-setting.php">アカウント設定</a></li>
  </ul>
</nav>

<?php page_footer();
