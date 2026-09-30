<?php

/**
 * pages/admin/notification.php
 * 運営のお知らせ管理。開けるのは users.role が admin の人だけ。
 *
 *   （パラメータなし） … 一覧
 *   ?id=new             … 新しく作る
 *   ?id=12              … 編集
 *
 * 保存・取り消し・削除は POST（CSRF 対策あり）で受け、終わったらリダイレクトする。
 * 処理そのものは lib/notification-admin.php にまとめてある。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/notification-admin.php';
require_once __DIR__ . '/../../lib/layout.php';

$admin = require_role('admin');

$rawId  = (string) ($_GET['id'] ?? $_POST['id'] ?? '');
$isNew  = $rawId === 'new';
$id     = $isNew || $rawId === '' ? null : (int) $rawId;   // null ＝ 一覧 または 新規
$errors = [];
$error  = null;

// -------------------------------------------------------------------
// 保存・取り消し・削除
// -------------------------------------------------------------------
if (is_post()) {
  try {
    csrf_verify();

    switch ((string) ($_POST['action'] ?? '')) {
      case 'save':
        $savedId = notification_admin_save($admin, $id, $_POST);
        flash(($_POST['publish'] ?? '') === '1' ? 'お知らせを公開しました。' : 'お知らせを保存しました。', 'success');
        redirect('notification.php?id=' . $savedId);

      case 'withdraw':
        notification_admin_withdraw($admin, (int) $id);
        flash('お知らせの公開を取り消しました。', 'success');
        redirect('notification.php?id=' . (int) $id);

      case 'delete':
        notification_admin_delete($admin, (int) $id);
        flash('下書きを削除しました。', 'success');
        redirect('notification.php');

      default:
        throw new AppError('操作が不正です。');
    }
  } catch (AppError $e) {
    $error  = $e->getMessage();
    $errors = $e->fields();
  } catch (PDOException $e) {
    error_log('[SPOTIVE] DB error: ' . $e->getMessage());
    $error = 'ただいま保存できません。時間をおいてお試しください。';
  }
}

// -------------------------------------------------------------------
// 表示するデータ
// -------------------------------------------------------------------
$list    = [];
$current = null;
$teams   = load_teams();

try {
  if ($id === null && !$isNew) {
    $list = notification_admin_list();
  } elseif ($id !== null) {
    $current = notification_admin_find($id);
    if ($current === null) {
      flash('お知らせが見つかりません。', 'error');
      redirect('notification.php');
    }
  }
} catch (PDOException $e) {
  error_log('[SPOTIVE] DB error: ' . $e->getMessage());
  $error = 'お知らせを読み込めませんでした。テーブル（db/schema.sql の notifications）があるか確認してください。';
}

$isForm = $isNew || $current !== null;

// 入力し直しのときは送られてきた値、編集なら今の内容、新規なら既定値
if ($isForm) {
  $form = is_post() ? $_POST : ($current === null
    ? ['audience' => 'all', 'category' => 'info', 'sender_name' => NOTIFICATION_DEFAULT_SENDER]
    : [
      'audience'     => $current['audience'],
      'team_id'      => (string) $current['team_id'],
      'category'     => $current['category'],
      'sender_name'  => $current['sender_name'],
      'title'        => $current['title'],
      'body'         => (string) $current['body'],
      'match_ref'    => (string) $current['match_ref'],
      'published_at' => datetime_local($current['published_at']),
      'expires_at'   => datetime_local($current['expires_at']),
    ]);
  $matchOptions = notification_admin_match_options();
  // 編集中のお知らせに付いている試合が終わっていても、選択肢から消さない
  if (($form['match_ref'] ?? '') !== '' && !isset($matchOptions[$form['match_ref']])) {
    $matchOptions[(string) $form['match_ref']] = (string) $form['match_ref'] . '（終了した試合）';
  }
  $state = $current === null ? 'draft' : notification_admin_state($current);
}

page_header('お知らせ管理', $isNew ? 'お知らせを作る' : ($current !== null ? 'お知らせを編集' : 'お知らせ管理'));
?>

<?php if ($error !== null) : ?>
  <p class="notice notice--error"><?= h($error) ?></p>
<?php endif; ?>

<?php if (!$isForm) : ?>

  <p class="form-page__note">
    <a class="button button--primary" href="notification.php?id=new">お知らせを作る</a>
  </p>

  <?php if ($list === []) : ?>
    <p class="form-page__note">お知らせはまだありません。</p>
  <?php else : ?>
    <table class="table">
      <thead>
        <tr>
          <th>状態</th><th>公開日時</th><th>届け先</th><th>種類</th><th>タイトル</th><th>既読</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($list as $row) : ?>
          <tr>
            <td><?= h(NOTIFICATION_STATE_LABELS[$row['state']]) ?></td>
            <td><?= $row['published_at'] === null ? '—' : h(format_datetime((string) $row['published_at'])) ?></td>
            <td>
              <?php if ($row['audience'] === 'team') : ?>
                <?= h((string) ($teams[$row['team_id']]['name'] ?? $row['team_id'])) ?>
              <?php else : ?>
                全員
              <?php endif; ?>
            </td>
            <td><?= h(NOTIFICATION_CATEGORIES[$row['category']] ?? '') ?></td>
            <td><?= h((string) $row['title']) ?></td>
            <td><?= (int) $row['read_count'] ?></td>
            <td><a href="notification.php?id=<?= (int) $row['id'] ?>">編集</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

<?php else : ?>

  <p class="form-page__note"><a href="notification.php">← 一覧へ戻る</a></p>

  <?php if ($current !== null) : ?>
    <dl class="detail">
      <dt class="detail__label">状態</dt>
      <dd class="detail__value"><?= h(NOTIFICATION_STATE_LABELS[$state]) ?></dd>
    </dl>
  <?php endif; ?>

  <form class="form" action="notification.php?id=<?= h($isNew ? 'new' : (string) (int) $id) ?>" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save" />

    <div class="form__field">
      <label class="form__label" for="audience">届け先</label>
      <select class="form__select" id="audience" name="audience" required>
        <?php foreach (NOTIFICATION_AUDIENCES as $value => $label) : ?>
          <option value="<?= h($value) ?>" <?= old($form, 'audience') === $value ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
      <?php field_error($errors, 'audience'); ?>
    </div>

    <div class="form__field">
      <label class="form__label" for="team_id">チーム（届け先が「チームのファン」のとき）</label>
      <select class="form__select" id="team_id" name="team_id">
        <option value="">選ばない</option>
        <?php foreach ($teams as $teamId => $team) : ?>
          <option value="<?= h((string) $teamId) ?>" <?= old($form, 'team_id') === (string) $teamId ? 'selected' : '' ?>>
            <?= h((string) ($team['name'] ?? $teamId)) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <p class="form__hint">そのチームをお気に入りに登録している人にだけ届きます。</p>
      <?php field_error($errors, 'team_id'); ?>
    </div>

    <div class="form__field">
      <label class="form__label" for="category">種類</label>
      <select class="form__select" id="category" name="category" required>
        <?php foreach (NOTIFICATION_CATEGORIES as $value => $label) : ?>
          <option value="<?= h($value) ?>" <?= old($form, 'category') === $value ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
      <?php field_error($errors, 'category'); ?>
    </div>

    <div class="form__field">
      <label class="form__label" for="sender_name">送り主</label>
      <input class="form__input" type="text" id="sender_name" name="sender_name"
             value="<?= h(old($form, 'sender_name')) ?>" maxlength="120" required />
      <p class="form__hint">一覧に出す名前です（例：SPOTIVE 運営、チーム名）。</p>
      <?php field_error($errors, 'sender_name'); ?>
    </div>

    <div class="form__field">
      <label class="form__label" for="title">タイトル</label>
      <input class="form__input" type="text" id="title" name="title"
             value="<?= h(old($form, 'title')) ?>" maxlength="150" required />
      <p class="form__hint">一覧に 1 行で出ます。</p>
      <?php field_error($errors, 'title'); ?>
    </div>

    <div class="form__field">
      <label class="form__label" for="body">本文（任意）</label>
      <textarea class="form__textarea" id="body" name="body" rows="6"
                maxlength="<?= NOTIFICATION_BODY_MAX ?>"><?= h(old($form, 'body')) ?></textarea>
      <p class="form__hint">お知らせを開いたときに出ます。</p>
      <?php field_error($errors, 'body'); ?>
    </div>

    <div class="form__field">
      <label class="form__label" for="match_ref">関係する試合（任意）</label>
      <select class="form__select" id="match_ref" name="match_ref">
        <option value="">なし</option>
        <?php foreach ($matchOptions as $value => $label) : ?>
          <option value="<?= h((string) $value) ?>" <?= old($form, 'match_ref') === (string) $value ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
      <p class="form__hint">選ぶと、お知らせに「試合の詳細を見る」のリンクが付きます。</p>
      <?php field_error($errors, 'match_ref'); ?>
    </div>

    <div class="form__field">
      <label class="form__label" for="published_at">公開日時（任意）</label>
      <input class="form__input" type="datetime-local" id="published_at" name="published_at"
             value="<?= h(old($form, 'published_at')) ?>" />
      <p class="form__hint">空のまま「公開する」を押すと、すぐに公開します。未来の日時にすると、その時刻まで予約になります。</p>
      <?php field_error($errors, 'published_at'); ?>
    </div>

    <div class="form__field">
      <label class="form__label" for="expires_at">掲載終了日時（任意）</label>
      <input class="form__input" type="datetime-local" id="expires_at" name="expires_at"
             value="<?= h(old($form, 'expires_at')) ?>" />
      <p class="form__hint">空なら出し続けます。</p>
      <?php field_error($errors, 'expires_at'); ?>
    </div>

    <div class="form__actions">
      <button class="button button--primary" type="submit" name="publish" value="1">
        <?= in_array($state, ['published', 'scheduled'], true) ? 'この内容で更新する' : '公開する' ?>
      </button>
      <?php if ($state === 'draft') : ?>
        <button class="button" type="submit" name="publish" value="0">下書きで保存</button>
      <?php endif; ?>
    </div>
  </form>

  <?php if ($current !== null && in_array($state, ['published', 'scheduled', 'expired'], true)) : ?>
    <form class="form form--inline" action="notification.php?id=<?= (int) $id ?>" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="withdraw" />
      <button class="button button--danger" type="submit">公開を取り消す</button>
      <p class="form__hint">見る側から消えます。記録は残ります。</p>
    </form>
  <?php endif; ?>

  <?php if ($current !== null && $state === 'draft') : ?>
    <form class="form form--inline" action="notification.php?id=<?= (int) $id ?>" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete" />
      <button class="button button--danger" type="submit">下書きを削除する</button>
    </form>
  <?php endif; ?>

<?php endif; ?>

<?php page_footer(); ?>
