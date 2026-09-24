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
$myEvents = (int) $user['trust_level'] >= LEVEL_ORGANIZER
  ? tournament_list_mine((int) $user['id'])
  : [];

$base = base_path();

page_header('マイページ', 'マイページ');
?>

<section class="level-card">
  <p class="level-card__name"><?= h((string) $user['nickname']) ?> さん</p>
  <p class="level-card__level"><?= h($overview['level_label']) ?></p>

  <ol class="level-steps">
    <?php
    $steps = [
      ['level' => LEVEL_USER,       'label' => 'アカウント登録', 'link' => null],
      ['level' => LEVEL_IDENTIFIED, 'label' => '本人確認',       'link' => $base . '/pages/account/identity.php'],
      ['level' => LEVEL_ORGANIZER,  'label' => '主催者認証',     'link' => $base . '/pages/organizer/register.php'],
    ];
    ?>
    <?php foreach ($steps as $step) : ?>
      <li class="level-steps__item <?= $overview['level'] >= $step['level'] ? 'is-done' : '' ?>">
        <?php if ($overview['level'] >= $step['level'] || $step['link'] === null) : ?>
          <?= h($step['label']) ?>
        <?php else : ?>
          <a href="<?= h($step['link']) ?>"><?= h($step['label']) ?></a>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ol>

  <?php if ($overview['next_step'] === 'verify_email') : ?>
    <p class="notice notice--info">メールアドレスの確認が済んでいません。</p>
  <?php elseif ($overview['next_step'] === 'identity') : ?>
    <p class="notice notice--info">
      次は本人確認です。
      <a href="<?= h($base) ?>/pages/account/identity.php">本人確認へ進む</a>
    </p>
  <?php elseif ($overview['next_step'] === 'organizer') : ?>
    <p class="notice notice--info">
      次は主催者認証です。
      <a href="<?= h($base) ?>/pages/organizer/register.php">主催者認証を申請する</a>
    </p>
  <?php else : ?>
    <p class="notice notice--success">主催者認証まで完了しています。</p>
  <?php endif; ?>
</section>

<section class="status-list">
  <h2 class="form-page__subtitle">申請の状況</h2>

  <dl class="detail">
    <dt class="detail__label">本人確認</dt>
    <dd class="detail__value">
      <?php if ($overview['identity'] === null) : ?>
        未申請
      <?php else : ?>
        <?= h(status_label((string) $overview['identity']['status'])) ?>
        <?php if ($overview['identity']['reject_reason'] !== null) : ?>
          <span class="detail__note"><?= h((string) $overview['identity']['reject_reason']) ?></span>
        <?php endif; ?>
      <?php endif; ?>
    </dd>

    <dt class="detail__label">主催者認証</dt>
    <dd class="detail__value">
      <?php if ($overview['application'] === null) : ?>
        未申請
      <?php else : ?>
        <?= h(status_label((string) $overview['application']['status'])) ?>
        <?php if ($overview['application']['review_note'] !== null) : ?>
          <span class="detail__note"><?= h((string) $overview['application']['review_note']) ?></span>
        <?php endif; ?>
      <?php endif; ?>
    </dd>

    <?php if ($overview['profile'] !== null) : ?>
      <dt class="detail__label">主催者名</dt>
      <dd class="detail__value">
        <?= h((string) $overview['profile']['display_name']) ?>
        （有効期限 <?= h(format_datetime((string) $overview['profile']['verified_until'])) ?>）
      </dd>
    <?php endif; ?>
  </dl>
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

<?php page_footer(); ?>
