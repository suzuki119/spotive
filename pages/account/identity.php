<?php

/**
 * pages/account/identity.php
 * Lv.2 本人確認の申し込みと書類提出。
 *
 * manual モードでは、申し込み → 書類アップロード → 提出 の 3 手順。
 * external_ekyc モードでは、申し込んだ時点で外部サービスへ引き継ぐ。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/identity.php';
require_once __DIR__ . '/../../lib/layout.php';

$user = require_login();

$errors = [];
$error  = null;

if (is_post()) {
  try {
    csrf_verify();

    switch ((string) ($_POST['action'] ?? '')) {
      case 'start':
        identity_start($user, $_POST);
        flash('本人確認の申し込みを受け付けました。', 'success');
        break;

      case 'upload':
        identity_upload_document($user, (int) $_POST['identity_id'], (string) $_POST['doc_type'], $_FILES['document'] ?? []);
        flash('書類をアップロードしました。', 'success');
        break;

      case 'submit':
        identity_submit($user, (int) $_POST['identity_id']);
        flash('本人確認を提出しました。審査の結果はメールでお知らせします。', 'success');
        break;
    }
    redirect('identity.php');
  } catch (AppError $e) {
    $error  = $e->getMessage();
    $errors = $e->fields();
  } catch (PDOException $e) {
    error_log('[SPOTIVE] DB error: ' . $e->getMessage());
    $error = 'ただいま受け付けられません。時間をおいてお試しください。';
  }
}

$identity  = identity_status((int) $user['id']);
$documents = $identity === null ? [] : storage_list('identity', (int) $identity['id']);
$canEdit   = $identity !== null
  && $identity['provider'] === 'manual'
  && in_array((string) $identity['status'], ['draft', 'more_info_required'], true);

page_header('本人確認', '本人確認（Lv.2）');
?>

<?php if ($error !== null) : ?>
  <p class="notice notice--error"><?= h($error) ?></p>
<?php endif; ?>

<?php if ($identity === null) : ?>

  <p class="form-page__lead">
    大会を主催するには、本人確認が必要です。
    お名前と生年月日を入力し、本人確認書類を提出してください。
  </p>

  <form class="form" action="identity.php" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="start" />

    <div class="form__field">
      <label class="form__label" for="legal_name">氏名（本名）</label>
      <input class="form__input" type="text" id="legal_name" name="legal_name"
             value="<?= h(old($_POST, 'legal_name')) ?>" maxlength="100" required />
      <?php field_error($errors, 'legal_name'); ?>
    </div>

    <div class="form__field">
      <label class="form__label" for="legal_name_kana">氏名（カナ）</label>
      <input class="form__input" type="text" id="legal_name_kana" name="legal_name_kana"
             value="<?= h(old($_POST, 'legal_name_kana')) ?>" maxlength="100" />
      <?php field_error($errors, 'legal_name_kana'); ?>
    </div>

    <div class="form__field">
      <label class="form__label" for="birthdate">生年月日</label>
      <input class="form__input" type="date" id="birthdate" name="birthdate"
             value="<?= h(old($_POST, 'birthdate')) ?>" required />
      <?php field_error($errors, 'birthdate'); ?>
    </div>

    <div class="form__field">
      <label class="form__label" for="document_type">本人確認書類</label>
      <select class="form__select" id="document_type" name="document_type" required>
        <option value="">選択してください</option>
        <?php foreach (IDENTITY_DOCUMENT_TYPES as $value => $label) : ?>
          <option value="<?= h($value) ?>" <?= old($_POST, 'document_type') === $value ? 'selected' : '' ?>>
            <?= h($label) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <?php field_error($errors, 'document_type'); ?>
    </div>

    <button class="button button--primary" type="submit">本人確認をはじめる</button>
  </form>

<?php else : ?>

  <dl class="detail">
    <dt class="detail__label">状態</dt>
    <dd class="detail__value"><?= h(status_label((string) $identity['status'])) ?></dd>

    <dt class="detail__label">氏名</dt>
    <dd class="detail__value"><?= h((string) $identity['legal_name']) ?></dd>

    <dt class="detail__label">書類</dt>
    <dd class="detail__value">
      <?= h(IDENTITY_DOCUMENT_TYPES[(string) $identity['document_type']] ?? '') ?>
    </dd>

    <?php if ($identity['expires_at'] !== null) : ?>
      <dt class="detail__label">有効期限</dt>
      <dd class="detail__value"><?= h(format_datetime((string) $identity['expires_at'])) ?></dd>
    <?php endif; ?>

    <?php if ($identity['reject_reason'] !== null) : ?>
      <dt class="detail__label">運営からの連絡</dt>
      <dd class="detail__value"><?= h((string) $identity['reject_reason']) ?></dd>
    <?php endif; ?>
  </dl>

  <?php if ($identity['status'] === 'approved') : ?>
    <p class="notice notice--success">
      本人確認が完了しています。
      <a href="<?= h(base_path()) ?>/pages/organizer/register.php">主催者認証（Lv.3）へ進む</a>
    </p>
  <?php endif; ?>

  <?php if ($identity['provider'] === 'external_ekyc' && $identity['status'] === 'submitted') : ?>
    <p class="notice notice--info">
      本人確認サービスの画面で、書類の撮影を完了してください。
      結果が届き次第、こちらの状態が変わります。
    </p>
  <?php endif; ?>

  <?php if ($canEdit) : ?>
    <h2 class="form-page__subtitle">書類のアップロード</h2>

    <ul class="doc-list">
      <?php foreach ($documents as $doc) : ?>
        <li class="doc-list__item">
          <?= h(IDENTITY_DOC_SLOTS[(string) $doc['doc_type']] ?? (string) $doc['doc_type']) ?>
          ：<?= h((string) $doc['original_name']) ?>
          （<?= h(number_format((int) $doc['byte_size'] / 1024, 0)) ?>KB）
        </li>
      <?php endforeach; ?>
      <?php if ($documents === []) : ?>
        <li class="doc-list__item doc-list__item--empty">まだアップロードされていません。</li>
      <?php endif; ?>
    </ul>

    <form class="form" action="identity.php" method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="upload" />
      <input type="hidden" name="identity_id" value="<?= (int) $identity['id'] ?>" />

      <div class="form__field">
        <label class="form__label" for="doc_type">書類の種類</label>
        <select class="form__select" id="doc_type" name="doc_type" required>
          <?php foreach (IDENTITY_DOC_SLOTS as $value => $label) : ?>
            <option value="<?= h($value) ?>"><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form__field">
        <label class="form__label" for="document">ファイル</label>
        <input class="form__input" type="file" id="document" name="document"
               accept="image/jpeg,image/png,application/pdf" required />
        <p class="form__hint">JPEG / PNG / PDF、8MB まで。</p>
      </div>

      <button class="button" type="submit">アップロード</button>
    </form>

    <form class="form form--inline" action="identity.php" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="submit" />
      <input type="hidden" name="identity_id" value="<?= (int) $identity['id'] ?>" />
      <button class="button button--primary" type="submit">審査に提出する</button>
    </form>
  <?php endif; ?>

<?php endif; ?>

<p class="form-page__note">
  <a href="<?= h(base_path()) ?>/pages/setting/setting.php">マイページへ戻る</a>
</p>

<?php page_footer(); ?>
