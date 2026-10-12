<?php

/**
 * pages/setting/team-notification.php
 * お気に入りチームのお知らせの受け取り設定。まだ作っていないので「準備中」とだけ出す。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/layout.php';

page_header('お気に入りチームの通知', 'お気に入りチームの通知');
?>

<p class="form-page__lead">このページは準備中です。</p>

<p class="form-page__note">
  <a href="<?= h(url('pages/setting/notification-setting.php')) ?>">設定へ戻る</a>
</p>

<?php page_footer();
