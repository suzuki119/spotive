<?php

/**
 * pages/organizer/register.php
 * 主催者アカウントの新規登録＝主催者認証（Lv.3）の申請。
 * 申請区分（個人・団体・学校・法人・協会）で入力欄が変わる。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/organizer.php';
require_once __DIR__ . '/../../lib/layout.php';

$user   = require_login();
$errors = [];
$error  = null;

if (is_post()) {
  try {
    csrf_verify();

    switch ((string) ($_POST['action'] ?? 'apply')) {
      case 'apply':
        $appId = organizer_apply($user, $_POST);
        flash('申請の下書きを保存しました。書類を添付して提出してください。', 'success');
        break;

      case 'upload':
        organizer_upload_document($user, (int) $_POST['application_id'], (string) $_POST['doc_type'], $_FILES['document'] ?? []);
        flash('書類をアップロードしました。', 'success');
        break;

      case 'submit':
        organizer_submit($user, (int) $_POST['application_id']);
        flash('主催者認証を申請しました。審査の結果はメールでお知らせします。', 'success');
        break;
    }
    redirect('register.php');
  } catch (AppError $e) {
    $error  = $e->getMessage();
    $errors = $e->fields();
  } catch (PDOException $e) {
    error_log('[SPOTIVE] DB error: ' . $e->getMessage());
    $error = 'ただいま受け付けられません。時間をおいてお試しください。';
  }
}

$status      = organizer_status((int) $user['id']);
$application = $status['application'];
$profile     = $status['profile'];
$documents   = $application === null ? [] : storage_list('organizer_application', (int) $application['id']);
$canEdit     = $application !== null
  && in_array((string) $application['status'], ['draft', 'more_info_required'], true);
$needNew     = $application === null
  || in_array((string) $application['status'], ['rejected', 'withdrawn'], true);

$base = base_path();

page_header('主催者登録', '主催者認証（Lv.3）');
?>

<?php if ($error !== null) : ?>
  <p class="notice notice--error"><?= h($error) ?></p>
<?php endif; ?>

<?php if ($profile !== null && $profile['status'] === 'active') : ?>
  <p class="notice notice--success">
    主催者認証が完了しています（<?= h((string) $profile['display_name']) ?>）。
    <a href="dashboard.php">主催者ページへ</a>
  </p>
<?php endif; ?>

<?php if ((int) $user['trust_level'] < LEVEL_IDENTIFIED) : ?>

  <p class="notice notice--info">
    主催者認証には本人確認（Lv.2）の完了が必要です。
  </p>
  <p class="form-page__note">
    <a class="button button--primary" href="<?= h($base) ?>/pages/account/identity.php">本人確認へ進む</a>
  </p>

<?php elseif ($application !== null && !$needNew) : ?>

  <dl class="detail">
    <dt class="detail__label">申請区分</dt>
    <dd class="detail__value">
      <?= h(ORGANIZER_APPLICANT_TYPES[(string) $application['applicant_type']] ?? '') ?>
    </dd>

    <dt class="detail__label">状態</dt>
    <dd class="detail__value"><?= h(status_label((string) $application['status'])) ?></dd>

    <dt class="detail__label">連絡先</dt>
    <dd class="detail__value">
      <?= h((string) $application['contact_name']) ?>
      ／ <?= h((string) $application['contact_email']) ?>
    </dd>

    <?php if ($application['review_note'] !== null) : ?>
      <dt class="detail__label">運営からの連絡</dt>
      <dd class="detail__value"><?= h((string) $application['review_note']) ?></dd>
    <?php endif; ?>
  </dl>

  <?php if ($canEdit) : ?>
    <h2 class="form-page__subtitle">書類の添付</h2>

    <ul class="doc-list">
      <?php foreach ($documents as $doc) : ?>
        <li class="doc-list__item">
          <?= h(ORGANIZER_DOC_SLOTS[(string) $doc['doc_type']] ?? (string) $doc['doc_type']) ?>
          ：<?= h((string) $doc['original_name']) ?>
        </li>
      <?php endforeach; ?>
      <?php if ($documents === []) : ?>
        <li class="doc-list__item doc-list__item--empty">まだ添付されていません（任意）。</li>
      <?php endif; ?>
    </ul>

    <form class="form" action="register.php" method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="upload" />
      <input type="hidden" name="application_id" value="<?= (int) $application['id'] ?>" />

      <div class="form__field">
        <label class="form__label" for="doc_type">書類の種類</label>
        <select class="form__select" id="doc_type" name="doc_type" required>
          <?php foreach (ORGANIZER_DOC_SLOTS as $value => $label) : ?>
            <option value="<?= h($value) ?>"><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form__field">
        <label class="form__label" for="document">ファイル</label>
        <input class="form__input" type="file" id="document" name="document"
               accept="image/jpeg,image/png,application/pdf" required />
      </div>

      <button class="button" type="submit">アップロード</button>
    </form>

    <form class="form form--inline" action="register.php" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="submit" />
      <input type="hidden" name="application_id" value="<?= (int) $application['id'] ?>" />
      <button class="button button--primary" type="submit">審査に提出する</button>
    </form>
  <?php endif; ?>

<?php else : ?>

  <p class="form-page__lead">
    大会を掲載するための申請です。個人でも申請できます。<br />
    団体・学校・法人・協会として申請する場合は、団体の情報もあわせて入力してください。
  </p>

  <form class="form" action="register.php" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="apply" />

    <div class="form__field">
      <label class="form__label" for="applicant_type">申請区分</label>
      <select class="form__select" id="applicant_type" name="applicant_type" required>
        <?php foreach (ORGANIZER_APPLICANT_TYPES as $value => $label) : ?>
          <option value="<?= h($value) ?>" <?= old($_POST, 'applicant_type') === $value ? 'selected' : '' ?>>
            <?= h($label) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <p class="form__hint">「個人」以外を選ぶと、下の「団体の情報」が必須になります。</p>
      <?php field_error($errors, 'applicant_type'); ?>
    </div>

    <h2 class="form-page__subtitle">申請者の連絡先</h2>

    <div class="form__field">
      <label class="form__label" for="contact_name">氏名</label>
      <input class="form__input" type="text" id="contact_name" name="contact_name"
             value="<?= h(old($_POST, 'contact_name')) ?>" maxlength="100" required />
      <?php field_error($errors, 'contact_name'); ?>
    </div>

    <div class="form__field">
      <label class="form__label" for="contact_phone">電話番号</label>
      <input class="form__input" type="tel" id="contact_phone" name="contact_phone"
             value="<?= h(old($_POST, 'contact_phone')) ?>" required />
      <?php field_error($errors, 'contact_phone'); ?>
    </div>

    <div class="form__field">
      <label class="form__label" for="contact_email">メールアドレス</label>
      <input class="form__input" type="email" id="contact_email" name="contact_email"
             value="<?= h(old($_POST, 'contact_email', (string) $user['email'])) ?>" required />
      <?php field_error($errors, 'contact_email'); ?>
    </div>

    <h2 class="form-page__subtitle">団体の情報（個人の場合は不要）</h2>

    <div class="form__field">
      <label class="form__label" for="org_name">団体名・法人名</label>
      <input class="form__input" type="text" id="org_name" name="org_name"
             value="<?= h(old($_POST, 'org_name')) ?>" maxlength="120" />
      <?php field_error($errors, 'org_name'); ?>
    </div>

    <div class="form__field">
      <label class="form__label" for="org_representative_name">団体の代表者名</label>
      <input class="form__input" type="text" id="org_representative_name" name="org_representative_name"
             value="<?= h(old($_POST, 'org_representative_name')) ?>" maxlength="100" />
      <?php field_error($errors, 'org_representative_name'); ?>
    </div>

    <div class="form__field">
      <label class="form__label" for="org_corporate_number">法人番号（法人のみ・13桁）</label>
      <input class="form__input" type="text" id="org_corporate_number" name="org_corporate_number"
             value="<?= h(old($_POST, 'org_corporate_number')) ?>" maxlength="13" inputmode="numeric" />
      <?php field_error($errors, 'org_corporate_number'); ?>
    </div>

    <div class="form__field">
      <label class="form__label" for="org_prefecture">都道府県</label>
      <input class="form__input" type="text" id="org_prefecture" name="org_prefecture"
             value="<?= h(old($_POST, 'org_prefecture')) ?>" maxlength="20" />
      <?php field_error($errors, 'org_prefecture'); ?>
    </div>

    <div class="form__field">
      <label class="form__label" for="org_address_line">所在地</label>
      <input class="form__input" type="text" id="org_address_line" name="org_address_line"
             value="<?= h(old($_POST, 'org_address_line')) ?>" maxlength="255" />
      <?php field_error($errors, 'org_address_line'); ?>
    </div>

    <div class="form__field">
      <label class="form__label" for="org_contact_phone">団体連絡先（電話）</label>
      <input class="form__input" type="tel" id="org_contact_phone" name="org_contact_phone"
             value="<?= h(old($_POST, 'org_contact_phone')) ?>" />
      <?php field_error($errors, 'org_contact_phone'); ?>
    </div>

    <div class="form__field">
      <label class="form__label" for="org_contact_email">団体連絡先（メール）</label>
      <input class="form__input" type="email" id="org_contact_email" name="org_contact_email"
             value="<?= h(old($_POST, 'org_contact_email')) ?>" />
      <?php field_error($errors, 'org_contact_email'); ?>
    </div>

    <div class="form__field">
      <label class="form__label" for="org_website_url">Webサイト</label>
      <input class="form__input" type="url" id="org_website_url" name="org_website_url"
             value="<?= h(old($_POST, 'org_website_url')) ?>" placeholder="https://" />
      <?php field_error($errors, 'org_website_url'); ?>
    </div>

    <div class="form__field">
      <label class="form__label" for="org_activity_description">活動内容</label>
      <textarea class="form__textarea" id="org_activity_description" name="org_activity_description"
                rows="4"><?= h(old($_POST, 'org_activity_description')) ?></textarea>
      <?php field_error($errors, 'org_activity_description'); ?>
    </div>

    <h2 class="form-page__subtitle">主催予定の大会（任意）</h2>

    <div class="form__field">
      <label class="form__label" for="planned_title">大会名</label>
      <input class="form__input" type="text" id="planned_title" name="planned_title"
             value="<?= h(old($_POST, 'planned_title')) ?>" maxlength="150" />
      <?php field_error($errors, 'planned_title'); ?>
    </div>

    <div class="form__field">
      <label class="form__label" for="planned_sport">競技種目</label>
      <input class="form__input" type="text" id="planned_sport" name="planned_sport"
             value="<?= h(old($_POST, 'planned_sport')) ?>" maxlength="50" />
      <?php field_error($errors, 'planned_sport'); ?>
    </div>

    <div class="form__field">
      <label class="form__label" for="planned_date">開催予定日</label>
      <input class="form__input" type="date" id="planned_date" name="planned_date"
             value="<?= h(old($_POST, 'planned_date')) ?>" />
      <?php field_error($errors, 'planned_date'); ?>
    </div>

    <div class="form__field">
      <label class="form__label" for="planned_summary">大会の内容</label>
      <textarea class="form__textarea" id="planned_summary" name="planned_summary"
                rows="4"><?= h(old($_POST, 'planned_summary')) ?></textarea>
      <?php field_error($errors, 'planned_summary'); ?>
    </div>

    <h2 class="form-page__subtitle">過去の開催実績（任意）</h2>
    <p class="form__hint">実績があるほど審査は通りやすくなります。1 件だけ入力できます。</p>

    <div class="form__field">
      <label class="form__label" for="past_title">大会名</label>
      <input class="form__input" type="text" id="past_title" name="past_events[0][title]"
             value="<?= h(old($_POST['past_events'][0] ?? [], 'title')) ?>" maxlength="150" />
    </div>

    <div class="form__field">
      <label class="form__label" for="past_held_on">開催日</label>
      <input class="form__input" type="date" id="past_held_on" name="past_events[0][held_on]"
             value="<?= h(old($_POST['past_events'][0] ?? [], 'held_on')) ?>" />
      <?php field_error($errors, 'past_events'); ?>
    </div>

    <div class="form__field">
      <label class="form__label" for="past_venue">会場</label>
      <input class="form__input" type="text" id="past_venue" name="past_events[0][venue]"
             value="<?= h(old($_POST['past_events'][0] ?? [], 'venue')) ?>" maxlength="255" />
    </div>

    <button class="button button--primary" type="submit">申請の下書きを保存する</button>
  </form>

<?php endif; ?>

<p class="form-page__note">
  <a href="<?= h($base) ?>/pages/setting/setting.php">マイページへ戻る</a>
</p>

<?php page_footer(); ?>
