<?php

/**
 * pages/admin/review.php
 * 運営の審査画面。本人確認・主催者申請・大会確認の 3 つのキューを扱う。
 * 開けるのは users.role が reviewer / admin の人だけ。
 *
 *   ?queue=identity|organizer|tournament … 一覧
 *   ?queue=...&id=...                    … 1 件の詳細
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/review.php';
require_once __DIR__ . '/../../lib/identity.php';
require_once __DIR__ . '/../../lib/organizer.php';
require_once __DIR__ . '/../../lib/tournament.php';
require_once __DIR__ . '/../../lib/layout.php';

$reviewer = require_role('reviewer', 'admin');

$queues = ['identity' => '本人確認', 'organizer' => '主催者申請', 'tournament' => '大会確認'];
$queue  = (string) ($_GET['queue'] ?? $_POST['queue'] ?? 'identity');
if (!array_key_exists($queue, $queues)) {
  $queue = 'identity';
}
$id     = (int) ($_GET['id'] ?? 0);
$errors = [];
$error  = null;

if (is_post()) {
  try {
    csrf_verify();

    $targetId = (int) $_POST['target_id'];
    $note     = trim((string) ($_POST['note'] ?? ''));
    $action   = (string) ($_POST['action'] ?? '');

    // 却下・追加依頼のときは理由を必ず書かせる
    if (in_array($action, ['reject', 'more_info'], true) && $note === '') {
      throw new AppError('理由を入力してください。', ['note' => '理由を入力してください。']);
    }

    match ([$queue, $action]) {
      ['identity', 'approve']   => identity_approve($targetId, (int) $reviewer['id']),
      ['identity', 'reject']    => identity_reject($targetId, (int) $reviewer['id'], $note),
      ['identity', 'more_info'] => identity_reject($targetId, (int) $reviewer['id'], $note, true),

      ['organizer', 'approve']   => organizer_approve($targetId, (int) $reviewer['id'], $note ?: null),
      ['organizer', 'reject']    => organizer_reject($targetId, (int) $reviewer['id'], $note),
      ['organizer', 'more_info'] => organizer_reject($targetId, (int) $reviewer['id'], $note, true),

      ['tournament', 'check'] => tournament_check(
        (int) $reviewer['id'],
        $targetId,
        (string) $_POST['item_key'],
        (string) $_POST['result'],
        $note === '' ? null : $note
      ),
      ['tournament', 'approve']   => tournament_approve((int) $reviewer['id'], $targetId, $note ?: null),
      ['tournament', 'reject']    => tournament_reject((int) $reviewer['id'], $targetId, $note),
      ['tournament', 'more_info'] => tournament_reject((int) $reviewer['id'], $targetId, $note, true),

      default => throw new AppError('操作が不正です。'),
    };

    flash('審査の結果を記録しました。', 'success');

    // チェック中は同じ詳細に留まり、結果が出たら一覧へ戻る
    redirect($action === 'check'
      ? 'review.php?queue=' . $queue . '&id=' . $targetId
      : 'review.php?queue=' . $queue);
  } catch (AppError $e) {
    $error  = $e->getMessage();
    $errors = $e->fields();
  } catch (PDOException $e) {
    error_log('[SPOTIVE] DB error: ' . $e->getMessage());
    $error = 'ただいま処理できません。時間をおいてお試しください。';
  }
}

$counts = review_counts();
$detail = null;
$list   = [];

try {
  if ($id > 0) {
    $detail = match ($queue) {
      'identity'   => review_identity_detail($id),
      'organizer'  => review_organizer_detail($id),
      'tournament' => tournament_verification_detail($id),
    };
  } else {
    $list = review_queue($queue);
  }
} catch (AppError $e) {
  $error = $e->getMessage();
}

$base = base_path();

page_header('審査', '審査キュー');
?>

<?php if ($error !== null) : ?>
  <p class="notice notice--error"><?= h($error) ?></p>
  <?php foreach ($errors as $key => $message) : ?>
    <?php if ($key !== 'note') : ?>
      <p class="field-error"><?= h($message) ?></p>
    <?php endif; ?>
  <?php endforeach; ?>
<?php endif; ?>

<nav class="queue-tabs">
  <?php foreach ($queues as $key => $label) : ?>
    <a class="queue-tabs__item <?= $queue === $key ? 'is-current' : '' ?>"
       href="review.php?queue=<?= h($key) ?>">
      <?= h($label) ?><span class="queue-tabs__count"><?= $counts[$key] ?></span>
    </a>
  <?php endforeach; ?>
</nav>

<?php if ($detail === null) : ?>

  <?php if ($list === []) : ?>
    <p class="form-page__note">審査待ちはありません。</p>
  <?php else : ?>
    <table class="table">
      <thead>
        <tr>
          <?php if ($queue === 'identity') : ?>
            <th>申請者</th><th>方式</th><th>書類</th><th>状態</th><th>提出</th><th></th>
          <?php elseif ($queue === 'organizer') : ?>
            <th>申請者</th><th>区分</th><th>団体</th><th>状態</th><th>提出</th><th></th>
          <?php else : ?>
            <th>大会名</th><th>主催者</th><th>開催日時</th><th>状態</th><th>提出</th><th></th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($list as $row) : ?>
          <tr>
            <?php if ($queue === 'identity') : ?>
              <td><?= h((string) $row['nickname']) ?></td>
              <td><?= h((string) $row['provider']) ?></td>
              <td><?= h(IDENTITY_DOCUMENT_TYPES[(string) $row['document_type']] ?? '') ?></td>
              <td><?= h(status_label((string) $row['status'])) ?></td>
              <td><?= h(format_datetime((string) $row['submitted_at'])) ?></td>
              <td><a href="review.php?queue=identity&id=<?= (int) $row['id'] ?>">審査する</a></td>
            <?php elseif ($queue === 'organizer') : ?>
              <td><?= h((string) $row['nickname']) ?></td>
              <td><?= h(ORGANIZER_APPLICANT_TYPES[(string) $row['applicant_type']] ?? '') ?></td>
              <td><?= h((string) ($row['organization_name'] ?? '—')) ?></td>
              <td><?= h(status_label((string) $row['status'])) ?></td>
              <td><?= h(format_datetime((string) $row['submitted_at'])) ?></td>
              <td><a href="review.php?queue=organizer&id=<?= (int) $row['id'] ?>">審査する</a></td>
            <?php else : ?>
              <td><?= h((string) $row['title']) ?></td>
              <td><?= h((string) $row['organizer_name']) ?></td>
              <td><?= h(format_datetime((string) $row['starts_at'])) ?></td>
              <td><?= h(status_label((string) $row['status'])) ?></td>
              <td><?= h(format_datetime((string) $row['submitted_at'])) ?></td>
              <td><a href="review.php?queue=tournament&id=<?= (int) $row['verification_id'] ?>">審査する</a></td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

<?php else : ?>

  <p class="form-page__note"><a href="review.php?queue=<?= h($queue) ?>">← 一覧へ戻る</a></p>

  <?php if ($queue === 'identity') : ?>

    <h2 class="form-page__subtitle">本人確認 #<?= (int) $detail['id'] ?></h2>
    <dl class="detail">
      <dt class="detail__label">申請者</dt>
      <dd class="detail__value"><?= h((string) $detail['nickname']) ?>（<?= h((string) $detail['email']) ?>）</dd>

      <dt class="detail__label">氏名（申告）</dt>
      <dd class="detail__value"><?= h((string) $detail['legal_name']) ?> <?= h((string) $detail['legal_name_kana']) ?></dd>

      <dt class="detail__label">生年月日</dt>
      <dd class="detail__value">
        書類 <?= h((string) $detail['birthdate']) ?>
        ／ 登録時の自己申告 <?= h((string) $detail['self_reported_birthdate']) ?>
      </dd>

      <dt class="detail__label">書類</dt>
      <dd class="detail__value"><?= h(IDENTITY_DOCUMENT_TYPES[(string) $detail['document_type']] ?? '') ?></dd>

      <dt class="detail__label">提出書類</dt>
      <dd class="detail__value">
        <?php foreach ($detail['documents'] as $doc) : ?>
          <a href="attachment.php?id=<?= (int) $doc['id'] ?>" target="_blank" rel="noopener">
            <?= h(IDENTITY_DOC_SLOTS[(string) $doc['doc_type']] ?? (string) $doc['doc_type']) ?>
          </a>
        <?php endforeach; ?>
        <?php if ($detail['documents'] === []) : ?>—<?php endif; ?>
      </dd>
    </dl>

  <?php elseif ($queue === 'organizer') : ?>

    <h2 class="form-page__subtitle">主催者申請 #<?= (int) $detail['id'] ?></h2>
    <dl class="detail">
      <dt class="detail__label">申請者</dt>
      <dd class="detail__value"><?= h((string) $detail['nickname']) ?>（<?= h((string) $detail['email']) ?>）</dd>

      <dt class="detail__label">本人確認</dt>
      <dd class="detail__value">
        <?= h(status_label((string) $detail['identity_status'])) ?>
        <?php if ($detail['legal_name'] !== null) : ?>
          ／ <?= h((string) $detail['legal_name']) ?>
        <?php endif; ?>
      </dd>

      <dt class="detail__label">区分</dt>
      <dd class="detail__value"><?= h(ORGANIZER_APPLICANT_TYPES[(string) $detail['applicant_type']] ?? '') ?></dd>

      <dt class="detail__label">連絡先</dt>
      <dd class="detail__value">
        <?= h((string) $detail['contact_name']) ?>
        ／ <?= h((string) $detail['contact_phone']) ?>
        ／ <?= h((string) $detail['contact_email']) ?>
      </dd>

      <?php if ($detail['organization'] !== null) : ?>
        <dt class="detail__label">団体</dt>
        <dd class="detail__value">
          <?= h((string) $detail['organization']['name']) ?>
          （代表 <?= h((string) $detail['organization']['representative_name']) ?>）<br />
          <?= h((string) $detail['organization']['prefecture']) ?>
          <?= h((string) $detail['organization']['address_line']) ?><br />
          <?php if ($detail['organization']['corporate_number'] !== null) : ?>
            法人番号 <?= h((string) $detail['organization']['corporate_number']) ?><br />
          <?php endif; ?>
          <?= h((string) $detail['organization']['activity_description']) ?>
        </dd>
      <?php endif; ?>

      <dt class="detail__label">実績</dt>
      <dd class="detail__value">
        <?php foreach ($detail['past_events'] as $event) : ?>
          <?= h((string) $event['title']) ?>（<?= h((string) $event['held_on']) ?>）<br />
        <?php endforeach; ?>
        <?php if ($detail['past_events'] === []) : ?>—<?php endif; ?>
      </dd>

      <dt class="detail__label">添付</dt>
      <dd class="detail__value">
        <?php foreach ($detail['documents'] as $doc) : ?>
          <a href="attachment.php?id=<?= (int) $doc['id'] ?>" target="_blank" rel="noopener">
            <?= h(ORGANIZER_DOC_SLOTS[(string) $doc['doc_type']] ?? (string) $doc['doc_type']) ?>
          </a>
        <?php endforeach; ?>
        <?php if ($detail['documents'] === []) : ?>—<?php endif; ?>
      </dd>
    </dl>

  <?php else : ?>

    <?php $snapshot = (array) $detail['snapshot']; ?>
    <h2 class="form-page__subtitle">大会確認 #<?= (int) $detail['id'] ?></h2>

    <dl class="detail">
      <dt class="detail__label">大会名</dt>
      <dd class="detail__value"><?= h((string) ($snapshot['title'] ?? '')) ?></dd>

      <dt class="detail__label">開催日時</dt>
      <dd class="detail__value">
        <?= h(format_datetime((string) ($snapshot['starts_at'] ?? ''))) ?>
        〜 <?= h(format_datetime((string) ($snapshot['ends_at'] ?? ''))) ?>
      </dd>

      <dt class="detail__label">会場</dt>
      <dd class="detail__value">
        <?= h((string) ($snapshot['venue_prefecture'] ?? '')) ?>
        <?= h((string) ($snapshot['venue_name'] ?? '')) ?>
        （<?= h((string) ($snapshot['venue_address'] ?? '')) ?>）
      </dd>

      <dt class="detail__label">会場の利用許可</dt>
      <dd class="detail__value">
        <?= h(TOURNAMENT_VENUE_PERMISSION[(string) ($snapshot['venue_permission_status'] ?? '')] ?? '') ?>
        <?= h((string) ($snapshot['venue_permission_ref'] ?? '')) ?>
      </dd>

      <dt class="detail__label">保険</dt>
      <dd class="detail__value">
        <?= h(TOURNAMENT_INSURANCE_STATUS[(string) ($snapshot['insurance_status'] ?? '')] ?? '') ?>
        <?= h((string) ($snapshot['insurance_provider'] ?? '')) ?>
      </dd>

      <dt class="detail__label">緊急連絡先</dt>
      <dd class="detail__value">
        <?= h((string) ($snapshot['emergency_contact_name'] ?? '')) ?>
        ／ <?= h((string) ($snapshot['emergency_contact_phone'] ?? '')) ?>
      </dd>

      <dt class="detail__label">添付</dt>
      <dd class="detail__value">
        <?php foreach ($detail['documents'] as $doc) : ?>
          <a href="attachment.php?id=<?= (int) $doc['id'] ?>" target="_blank" rel="noopener">
            <?= h(TOURNAMENT_DOC_SLOTS[(string) $doc['doc_type']] ?? (string) $doc['doc_type']) ?>
          </a>
        <?php endforeach; ?>
        <?php if ($detail['documents'] === []) : ?>—<?php endif; ?>
      </dd>
    </dl>

    <h3 class="form-page__subtitle">チェックリスト</h3>
    <p class="form__hint">すべての項目を「OK」または「対象外」にしないと承認できません。</p>

    <table class="table">
      <tbody>
        <?php foreach ($detail['checks'] as $item) : ?>
          <tr>
            <td><?= h($item['label']) ?></td>
            <td><?= h(['pending' => '未確認', 'ok' => 'OK', 'ng' => 'NG', 'na' => '対象外'][$item['result']] ?? '') ?></td>
            <td><?= h((string) $item['note']) ?></td>
            <td>
              <form class="form form--inline" action="review.php" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="queue" value="tournament" />
                <input type="hidden" name="action" value="check" />
                <input type="hidden" name="target_id" value="<?= (int) $detail['id'] ?>" />
                <input type="hidden" name="item_key" value="<?= h($item['key']) ?>" />
                <select name="result">
                  <?php foreach (['ok' => 'OK', 'ng' => 'NG', 'na' => '対象外', 'pending' => '未確認'] as $value => $label) : ?>
                    <option value="<?= h($value) ?>" <?= $item['result'] === $value ? 'selected' : '' ?>>
                      <?= h($label) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <input type="text" name="note" value="<?= h((string) $item['note']) ?>" placeholder="メモ" />
                <button class="button button--small" type="submit">記録</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

  <?php endif; ?>

  <h3 class="form-page__subtitle">審査の結果</h3>

  <form class="form" action="review.php" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="queue" value="<?= h($queue) ?>" />
    <input type="hidden" name="target_id" value="<?= (int) $detail['id'] ?>" />

    <div class="form__field">
      <label class="form__label" for="note">申請者へのメモ・理由</label>
      <textarea class="form__textarea" id="note" name="note" rows="3"><?= h(old($_POST, 'note')) ?></textarea>
      <?php field_error($errors, 'note'); ?>
    </div>

    <div class="form__actions">
      <button class="button button--primary" type="submit" name="action" value="approve">承認する</button>
      <button class="button" type="submit" name="action" value="more_info">追加情報を求める</button>
      <button class="button button--danger" type="submit" name="action" value="reject">却下する</button>
    </div>
  </form>

<?php endif; ?>

<?php page_footer(); ?>
