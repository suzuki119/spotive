<?php

/**
 * pages/organizer/dashboard.php
 * 主催者ダッシュボード。掲載中の試合・イベントの管理
 * 自分が登録した大会の一覧と、確認申請（Lv.4）の状態を表示する。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/tournament.php';
require_once __DIR__ . '/../../lib/organizer.php';
require_once __DIR__ . '/../../lib/layout.php';

$user   = require_login();
$errors = [];
$error  = null;

if (is_post()) {
  try {
    csrf_verify();
    if ((string) ($_POST['action'] ?? '') === 'submit_verification') {
      tournament_submit_verification($user, (int) $_POST['tournament_id']);
      flash('大会確認を申請しました。審査の結果はメールでお知らせします。', 'success');
    }
    redirect('dashboard.php');
  } catch (AppError $e) {
    $error  = $e->getMessage();
    $errors = $e->fields();
  } catch (PDOException $e) {
    error_log('[SPOTIVE] DB error: ' . $e->getMessage());
    $error = 'ただいま受け付けられません。時間をおいてお試しください。';
  }
}

$status  = organizer_status((int) $user['id']);
$profile = $status['profile'];
$events  = tournament_list_mine((int) $user['id']);
$base    = base_path();

page_header('主催者ページ', '主催者ページ');
?>

<?php if ($error !== null) : ?>
  <p class="notice notice--error"><?= h($error) ?></p>
  <?php foreach ($errors as $key => $message) : ?>
    <p class="field-error"><?= h($message) ?></p>
  <?php endforeach; ?>
<?php endif; ?>

<?php if ($profile === null || $profile['status'] !== 'active') : ?>
  <p class="notice notice--info">
    主催者認証（Lv.3）が有効ではありません。
    <a href="register.php">主催者認証の申請へ</a>
  </p>
<?php else : ?>
  <p class="form-page__lead">
    <strong><?= h((string) $profile['display_name']) ?></strong> として掲載できます
    （有効期限 <?= h(format_datetime((string) $profile['verified_until'])) ?>）。
  </p>
  <p class="form-page__note">
    <a class="button button--primary" href="tournament-new.php">大会を登録する</a>
  </p>
<?php endif; ?>

<h2 class="form-page__subtitle">登録した大会（<?= count($events) ?>件）</h2>

<?php if ($events === []) : ?>
  <p class="form-page__note">まだ大会を登録していません。</p>
<?php else : ?>
  <table class="table">
    <thead>
      <tr>
        <th>大会名</th>
        <th>開催日時</th>
        <th>会場</th>
        <th>公開状態</th>
        <th>確認状態</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($events as $event) : ?>
        <tr>
          <td>
            <a href="tournament-new.php?id=<?= (int) $event['id'] ?>">
              <?= h((string) $event['title']) ?>
            </a>
          </td>
          <td><?= h(format_datetime((string) $event['starts_at'])) ?></td>
          <td><?= h((string) $event['venue_prefecture']) ?> <?= h((string) $event['venue_name']) ?></td>
          <td><?= h(status_label((string) $event['status'])) ?></td>
          <td><?= h(status_label((string) $event['verification_status'])) ?></td>
          <td>
            <?php if (in_array((string) $event['verification_status'], ['unverified', 'rejected', 'more_info_required'], true)) : ?>
              <form action="dashboard.php" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="submit_verification" />
                <input type="hidden" name="tournament_id" value="<?= (int) $event['id'] ?>" />
                <button class="button button--small" type="submit">確認を申請</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<p class="form-page__note">
  <a href="<?= h($base) ?>/pages/setting/setting.php">マイページへ戻る</a>
</p>

<?php page_footer(); ?>
