<?php

/**
 * pages/organizer/tournament-new.php
 * 大会の登録・編集。?id= を付けると編集になる。
 *
 * 緯度経度は入力しなくてよい。空なら住所から国土地理院の API で求める。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/tournament.php';
require_once __DIR__ . '/../../lib/layout.php';

$user = require_action_level('create_tournament', LEVEL_ORGANIZER);

$id     = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$errors = [];
$error  = null;

if (is_post()) {
  try {
    csrf_verify();

    switch ((string) ($_POST['action'] ?? 'save')) {
      case 'save':
        if ($id > 0) {
          tournament_update($user, $id, $_POST);
          flash('大会の内容を更新しました。', 'success');
        } else {
          $id = tournament_create($user, $_POST);
          flash('大会を登録しました。確認申請に必要な書類を添付してください。', 'success');
        }
        redirect('tournament-new.php?id=' . $id);
        // no break

      case 'upload':
        tournament_upload_document($user, $id, (string) $_POST['doc_type'], $_FILES['document'] ?? []);
        flash('書類を添付しました。', 'success');
        redirect('tournament-new.php?id=' . $id);
        // no break

      case 'submit_verification':
        tournament_submit_verification($user, $id);
        flash('大会確認を申請しました。', 'success');
        redirect('dashboard.php');
    }
  } catch (AppError $e) {
    $error  = $e->getMessage();
    $errors = $e->fields();
  } catch (PDOException $e) {
    error_log('[SPOTIVE] DB error: ' . $e->getMessage());
    $error = 'ただいま受け付けられません。時間をおいてお試しください。';
  }
}

// 表示に使う値：POST されていればその内容、無ければ DB の内容
$t = [];
if ($id > 0) {
  try {
    $t = tournament_owned_detail((int) $user['id'], $id);
  } catch (AppError $e) {
    flash($e->getMessage(), 'error');
    redirect('dashboard.php');
  }
}
$form      = is_post() ? $_POST : $t;
$documents = $id > 0 ? storage_list('tournament', $id) : [];
$editable  = $id === 0 || ($t['editable'] ?? true);

/** 値の取り出し。datetime 列は <input> の形に直す */
$val = static function (string $key) use ($form): string {
  return old($form, $key);
};
$dtVal = static function (string $key) use ($form, $t): string {
  $raw = (string) ($form[$key] ?? '');
  // DB から来た値は「2026-10-03 18:00:00」なので変換する
  return str_contains($raw, ' ') ? datetime_local($raw) : $raw;
};

page_header($id > 0 ? '大会の編集' : '大会の登録', $id > 0 ? '大会の編集' : '大会の登録');
?>

<?php if ($error !== null) : ?>
  <p class="notice notice--error"><?= h($error) ?></p>
<?php endif; ?>

<?php if (!$editable) : ?>
  <p class="notice notice--info">
    確認申請中、または中止・終了した大会のため、内容は変更できません。
  </p>
<?php endif; ?>

<form class="form" action="tournament-new.php" method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save" />
  <input type="hidden" name="id" value="<?= $id ?>" />

  <h2 class="form-page__subtitle">大会の基本情報</h2>

  <div class="form__field">
    <label class="form__label" for="title">大会名</label>
    <input class="form__input" type="text" id="title" name="title"
           value="<?= h($val('title')) ?>" maxlength="150" required />
    <?php field_error($errors, 'title'); ?>
  </div>

  <div class="form__field">
    <label class="form__label" for="sport">競技種目</label>
    <input class="form__input" type="text" id="sport" name="sport"
           value="<?= h($val('sport')) ?>" maxlength="50" placeholder="サッカー" required />
    <?php field_error($errors, 'sport'); ?>
  </div>

  <div class="form__field">
    <label class="form__label" for="description">大会の説明</label>
    <textarea class="form__textarea" id="description" name="description" rows="4"><?= h($val('description')) ?></textarea>
  </div>

  <div class="form__field">
    <label class="form__label" for="starts_at">開催日時</label>
    <input class="form__input" type="datetime-local" id="starts_at" name="starts_at"
           value="<?= h($dtVal('starts_at')) ?>" required />
    <?php field_error($errors, 'starts_at'); ?>
  </div>

  <div class="form__field">
    <label class="form__label" for="ends_at">終了日時</label>
    <input class="form__input" type="datetime-local" id="ends_at" name="ends_at"
           value="<?= h($dtVal('ends_at')) ?>" required />
    <?php field_error($errors, 'ends_at'); ?>
  </div>

  <div class="form__field">
    <label class="form__label" for="entry_opens_at">募集開始日時</label>
    <input class="form__input" type="datetime-local" id="entry_opens_at" name="entry_opens_at"
           value="<?= h($dtVal('entry_opens_at')) ?>" />
    <?php field_error($errors, 'entry_opens_at'); ?>
  </div>

  <div class="form__field">
    <label class="form__label" for="entry_closes_at">募集終了日時</label>
    <input class="form__input" type="datetime-local" id="entry_closes_at" name="entry_closes_at"
           value="<?= h($dtVal('entry_closes_at')) ?>" />
    <p class="form__hint">確認申請（Lv.4）には募集期間の入力が必要です。</p>
    <?php field_error($errors, 'entry_closes_at'); ?>
    <?php field_error($errors, 'entry_period'); ?>
  </div>

  <h2 class="form-page__subtitle">開催場所</h2>

  <div class="form__field">
    <label class="form__label" for="venue_name">会場名</label>
    <input class="form__input" type="text" id="venue_name" name="venue_name"
           value="<?= h($val('venue_name')) ?>" maxlength="150" required />
    <?php field_error($errors, 'venue_name'); ?>
  </div>

  <div class="form__field">
    <label class="form__label" for="venue_postal_code">郵便番号</label>
    <input class="form__input" type="text" id="venue_postal_code" name="venue_postal_code"
           value="<?= h($val('venue_postal_code')) ?>" maxlength="8" placeholder="733-0036" />
    <?php field_error($errors, 'venue_postal_code'); ?>
  </div>

  <div class="form__field">
    <label class="form__label" for="venue_prefecture">都道府県</label>
    <input class="form__input" type="text" id="venue_prefecture" name="venue_prefecture"
           value="<?= h($val('venue_prefecture')) ?>" maxlength="20" required />
    <?php field_error($errors, 'venue_prefecture'); ?>
  </div>

  <div class="form__field">
    <label class="form__label" for="venue_address">会場住所</label>
    <input class="form__input" type="text" id="venue_address" name="venue_address"
           value="<?= h($val('venue_address')) ?>" maxlength="255" required />
    <p class="form__hint">緯度経度は住所から自動で求めます。うまく出ない場合だけ下に入力してください。</p>
    <?php field_error($errors, 'venue_address'); ?>
  </div>

  <div class="form__field">
    <label class="form__label" for="venue_lat">緯度・経度（任意）</label>
    <input class="form__input form__input--half" type="text" id="venue_lat" name="venue_lat"
           value="<?= h($val('venue_lat')) ?>" placeholder="35.6895" />
    <input class="form__input form__input--half" type="text" name="venue_lng"
           value="<?= h($val('venue_lng')) ?>" placeholder="139.6917" />
  </div>

  <div class="form__field form__field--check">
    <label class="form__check">
      <input type="checkbox" name="is_indoor" value="1" <?= !empty($form['is_indoor']) ? 'checked' : '' ?> />
      <span>屋内の会場</span>
    </label>
  </div>

  <h2 class="form-page__subtitle">参加条件</h2>

  <div class="form__field">
    <label class="form__label" for="eligibility">参加条件</label>
    <textarea class="form__textarea" id="eligibility" name="eligibility" rows="4" required><?= h($val('eligibility')) ?></textarea>
    <p class="form__hint">年齢・性別・レベルなど。</p>
    <?php field_error($errors, 'eligibility'); ?>
  </div>

  <div class="form__field">
    <label class="form__label" for="entry_fee_yen">参加費（円）</label>
    <input class="form__input" type="number" id="entry_fee_yen" name="entry_fee_yen"
           value="<?= h($val('entry_fee_yen') === '' ? '0' : $val('entry_fee_yen')) ?>" min="0" max="1000000" />
    <?php field_error($errors, 'entry_fee_yen'); ?>
  </div>

  <div class="form__field">
    <label class="form__label" for="capacity">定員</label>
    <input class="form__input" type="number" id="capacity" name="capacity"
           value="<?= h($val('capacity')) ?>" min="1" max="10000" required />
    <?php field_error($errors, 'capacity'); ?>
  </div>

  <div class="form__field">
    <label class="form__label" for="rules">大会ルール</label>
    <textarea class="form__textarea" id="rules" name="rules" rows="6" required><?= h($val('rules')) ?></textarea>
    <?php field_error($errors, 'rules'); ?>
  </div>

  <h2 class="form-page__subtitle">安全・許認可（Lv.4 の確認対象）</h2>

  <div class="form__field">
    <label class="form__label" for="venue_permission_status">会場の利用許可・予約</label>
    <select class="form__select" id="venue_permission_status" name="venue_permission_status">
      <?php foreach (TOURNAMENT_VENUE_PERMISSION as $value => $label) : ?>
        <option value="<?= h($value) ?>" <?= $val('venue_permission_status') === $value ? 'selected' : '' ?>>
          <?= h($label) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <?php field_error($errors, 'venue_permission_status'); ?>
    <?php field_error($errors, 'venue_permit'); ?>
  </div>

  <div class="form__field">
    <label class="form__label" for="venue_permission_ref">予約番号・許可証番号</label>
    <input class="form__input" type="text" id="venue_permission_ref" name="venue_permission_ref"
           value="<?= h($val('venue_permission_ref')) ?>" maxlength="191" />
  </div>

  <div class="form__field form__field--check">
    <label class="form__check">
      <input type="checkbox" name="permits_required" value="1" <?= !empty($form['permits_required']) ? 'checked' : '' ?> />
      <span>道路使用許可・消防届出などが必要</span>
    </label>
  </div>

  <div class="form__field">
    <label class="form__label" for="permits_note">必要な許可・届出の内容</label>
    <textarea class="form__textarea" id="permits_note" name="permits_note" rows="3"><?= h($val('permits_note')) ?></textarea>
    <?php field_error($errors, 'permits_note'); ?>
  </div>

  <div class="form__field">
    <label class="form__label" for="insurance_status">保険の加入状況</label>
    <select class="form__select" id="insurance_status" name="insurance_status">
      <?php foreach (TOURNAMENT_INSURANCE_STATUS as $value => $label) : ?>
        <option value="<?= h($value) ?>" <?= $val('insurance_status') === $value ? 'selected' : '' ?>>
          <?= h($label) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <?php field_error($errors, 'insurance_status'); ?>
  </div>

  <div class="form__field">
    <label class="form__label" for="insurance_provider">保険会社名</label>
    <input class="form__input" type="text" id="insurance_provider" name="insurance_provider"
           value="<?= h($val('insurance_provider')) ?>" maxlength="120" />
    <?php field_error($errors, 'insurance_provider'); ?>
  </div>

  <div class="form__field">
    <label class="form__label" for="emergency_contact_name">緊急時の連絡先（氏名）</label>
    <input class="form__input" type="text" id="emergency_contact_name" name="emergency_contact_name"
           value="<?= h($val('emergency_contact_name')) ?>" maxlength="100" required />
    <?php field_error($errors, 'emergency_contact_name'); ?>
  </div>

  <div class="form__field">
    <label class="form__label" for="emergency_contact_phone">緊急時の連絡先（電話）</label>
    <input class="form__input" type="tel" id="emergency_contact_phone" name="emergency_contact_phone"
           value="<?= h($val('emergency_contact_phone')) ?>" required />
    <?php field_error($errors, 'emergency_contact_phone'); ?>
  </div>

  <?php if ($editable) : ?>
    <button class="button button--primary" type="submit">
      <?= $id > 0 ? '内容を更新する' : '大会を登録する' ?>
    </button>
  <?php endif; ?>
</form>

<?php if ($id > 0) : ?>
  <h2 class="form-page__subtitle">書類の添付</h2>

  <ul class="doc-list">
    <?php foreach ($documents as $doc) : ?>
      <li class="doc-list__item">
        <?= h(TOURNAMENT_DOC_SLOTS[(string) $doc['doc_type']] ?? (string) $doc['doc_type']) ?>
        ：<?= h((string) $doc['original_name']) ?>
      </li>
    <?php endforeach; ?>
    <?php if ($documents === []) : ?>
      <li class="doc-list__item doc-list__item--empty">まだ添付されていません。</li>
    <?php endif; ?>
  </ul>

  <form class="form" action="tournament-new.php" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="upload" />
    <input type="hidden" name="id" value="<?= $id ?>" />

    <div class="form__field">
      <label class="form__label" for="doc_type">書類の種類</label>
      <select class="form__select" id="doc_type" name="doc_type" required>
        <?php foreach (TOURNAMENT_DOC_SLOTS as $value => $label) : ?>
          <option value="<?= h($value) ?>"><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form__field">
      <label class="form__label" for="document">ファイル</label>
      <input class="form__input" type="file" id="document" name="document"
             accept="image/jpeg,image/png,application/pdf" required />
    </div>

    <button class="button" type="submit">添付する</button>
  </form>

  <form class="form form--inline" action="tournament-new.php" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="submit_verification" />
    <input type="hidden" name="id" value="<?= $id ?>" />
    <button class="button button--primary" type="submit">大会確認（Lv.4）を申請する</button>
  </form>
<?php endif; ?>

<p class="form-page__note">
  <a href="dashboard.php">主催者ページへ戻る</a>
</p>

<?php page_footer(); ?>
