<?php

/**
 * lib/organizer.php
 * Lv.3 主催者認証。
 *   前提: Lv.2 本人確認済み（個人申請でも団体申請でも、申請者本人の本人確認は必須）。
 *   個人 → 連絡先 ＋ 主催予定の大会内容（任意）＋ 実績（任意）
 *   団体 → 上記 ＋ 団体情報（代表者・所在地・連絡先・Web/SNS・活動内容）
 *   法人 → 上記 ＋ 法人番号（チェックディジット検証あり）
 */

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once __DIR__ . '/validator.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/account.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/ratelimit.php';
require_once __DIR__ . '/notifier.php';
require_once __DIR__ . '/storage.php';

const ORGANIZER_APPLICANT_TYPES = [
  'individual'  => '個人',
  'group'       => '団体・サークル',
  'school'      => '学校',
  'corporation' => '法人',
  'association' => '協会・連盟',
];

/** 申請区分 => organizations.org_type。individual は団体を作らない */
const ORGANIZER_ORG_TYPE = [
  'group'       => 'group',
  'school'      => 'school',
  'corporation' => 'corporation',
  'association' => 'association',
];

const ORGANIZER_DOC_SLOTS = [
  'org_rule'         => '団体規約・会則',
  'activity_report'  => '活動報告',
  'registry'         => '登記事項証明書（法人）',
  'past_event_proof' => '過去の大会の資料',
  'other'            => 'その他',
];

/** 申請の作成（下書き）。@return int organizer_applications.id */
function organizer_apply(array $user, array $in): int
{
  // 主催者になるには本人確認が先。ここが Lv.2 → Lv.3 のゲート
  $needed = (int) (config('verification.require_level.organizer_apply') ?? LEVEL_IDENTIFIED);
  if ((int) $user['trust_level'] < $needed) {
    throw new AppError('主催者認証には本人確認（Lv.2）の完了が必要です。');
  }

  $open = db_one(
    'SELECT id FROM organizer_applications
      WHERE user_id = :u AND status IN ("submitted","in_review")
      ORDER BY id DESC LIMIT 1',
    ['u' => $user['id']]
  );
  if ($open !== null) {
    throw new AppError('審査中の申請があります。');
  }

  $type = (string) ($in['applicant_type'] ?? '');

  $v = (new Validator($in))
    ->required('applicant_type', '申請区分')
    ->in('applicant_type', '申請区分', array_keys(ORGANIZER_APPLICANT_TYPES))
    ->required('contact_name', '氏名')->length('contact_name', '氏名', 1, 100)
    ->required('contact_phone', '電話番号')->tel('contact_phone')
    ->required('contact_email', 'メールアドレス')->email('contact_email')
    // 大会の情報は主催者登録では必須にしない。入っていれば保存する
    ->length('planned_title', '大会名', 1, 150)
    ->length('planned_sport', '競技種目', 1, 50)
    ->length('planned_summary', '大会の内容', 1, 4000)
    ->length('planned_venue', '開催予定場所', 1, 255)
    ->date('planned_date', '開催予定日')
    ->intRange('planned_participants', '想定参加人数', 1, 10000)
    ->intRange('planned_fee_yen', '参加費', 0, 1000000);

  $plannedDate = (string) ($in['planned_date'] ?? '');
  if ($plannedDate !== '' && Validator::isDate($plannedDate) && strtotime($plannedDate) < strtotime('today')) {
    $v->add('planned_date', '開催予定日には未来の日付を入力してください。');
  }
  $v->validate();

  return db_transaction(static function () use ($user, $in, $type): int {
    $orgId = $type === 'individual' ? null : organizer_upsert_organization((int) $user['id'], $type, $in);

    $appId = db_insert('organizer_applications', [
      'user_id'              => $user['id'],
      'organization_id'      => $orgId,
      'applicant_type'       => $type,
      'contact_name'         => trim((string) $in['contact_name']),
      'contact_phone'        => Validator::normalizeTel((string) $in['contact_phone']),
      'contact_email'        => trim((string) $in['contact_email']),
      'planned_title'        => optional_text($in, 'planned_title'),
      'planned_sport'        => optional_text($in, 'planned_sport'),
      'planned_summary'      => optional_text($in, 'planned_summary'),
      'planned_venue'        => optional_text($in, 'planned_venue'),
      'planned_date'         => optional_text($in, 'planned_date'),
      'planned_fee_yen'      => (int) ($in['planned_fee_yen'] ?? 0),
      'planned_participants' => ($in['planned_participants'] ?? '') === '' ? null : (int) $in['planned_participants'],
      'tournament_rules'     => optional_text($in, 'tournament_rules'),
      'cancellation_policy'  => optional_text($in, 'cancellation_policy'),
      'refund_policy'        => optional_text($in, 'refund_policy'),
      'status'               => 'draft',
    ]);

    // 過去の大会開催実績（任意。あるほど審査は通りやすい）
    foreach ((array) ($in['past_events'] ?? []) as $event) {
      if (!is_array($event) || trim((string) ($event['title'] ?? '')) === '') {
        continue;
      }
      if (!Validator::isDate((string) ($event['held_on'] ?? ''))) {
        throw new AppError('入力内容を確認してください。', [
          'past_events' => '実績の開催日は YYYY-MM-DD 形式で入力してください。',
        ]);
      }
      db_insert('organizer_past_events', [
        'application_id'  => $appId,
        'organization_id' => $orgId,
        'title'           => mb_substr((string) $event['title'], 0, 150),
        'held_on'         => (string) $event['held_on'],
        'venue'           => optional_text($event, 'venue'),
        'participants'    => ($event['participants'] ?? '') === '' ? null : (int) $event['participants'],
        'reference_url'   => optional_text($event, 'reference_url'),
        'note'            => optional_text($event, 'note'),
      ]);
    }

    audit_log((int) $user['id'], 'organizer.apply', 'organizer_application', $appId, ['type' => $type]);
    return $appId;
  });
}

/** 団体・法人情報の作成／更新 */
function organizer_upsert_organization(int $userId, string $type, array $in): int
{
  $v = (new Validator($in))
    ->required('org_name', '団体名')->length('org_name', '団体名', 1, 120)
    ->required('org_representative_name', '団体の代表者名')->length('org_representative_name', '代表者名', 1, 100)
    ->required('org_prefecture', '都道府県')->length('org_prefecture', '都道府県', 1, 20)
    ->required('org_address_line', '所在地')->length('org_address_line', '所在地', 1, 255)
    ->required('org_contact_phone', '団体連絡先（電話）')->tel('org_contact_phone')
    ->required('org_contact_email', '団体連絡先（メール）')->email('org_contact_email')
    ->length('org_activity_description', '活動内容', 1, 4000)
    ->url('org_website_url', 'Webサイト')
    ->url('org_sns_url', 'SNS');

  if ($type === 'corporation') {
    $v->required('org_corporate_number', '法人番号')->corporateNumber('org_corporate_number');
  }
  $v->validate();

  $data = [
    'owner_user_id'        => $userId,
    'org_type'             => ORGANIZER_ORG_TYPE[$type] ?? 'group',
    'name'                 => trim((string) $in['org_name']),
    'name_kana'            => optional_text($in, 'org_name_kana'),
    'representative_name'  => trim((string) $in['org_representative_name']),
    'corporate_number'     => $type === 'corporation'
      ? preg_replace('/\D/', '', (string) $in['org_corporate_number'])
      : null,
    'postal_code'          => optional_text($in, 'org_postal_code'),
    'prefecture'           => trim((string) $in['org_prefecture']),
    'address_line'         => trim((string) $in['org_address_line']),
    'contact_phone'        => Validator::normalizeTel((string) $in['org_contact_phone']),
    'contact_email'        => trim((string) $in['org_contact_email']),
    'website_url'          => optional_text($in, 'org_website_url'),
    'sns_url'              => optional_text($in, 'org_sns_url'),
    'activity_description' => optional_text($in, 'org_activity_description'),
  ];

  $existingId = (int) ($in['organization_id'] ?? 0);
  if ($existingId > 0) {
    $org = db_one(
      'SELECT id FROM organizations WHERE id = :id AND owner_user_id = :u',
      ['id' => $existingId, 'u' => $userId]
    );
    if ($org === null) {
      throw new AppError('団体情報が見つかりません。');
    }
    unset($data['owner_user_id']);   // 持ち主は変えない
    db_update('organizations', $data, 'id = :id', ['id' => $existingId]);
    return $existingId;
  }

  return db_insert('organizations', $data);
}

/** 添付書類（団体規約・活動報告・登記事項証明書など） */
function organizer_upload_document(array $user, int $appId, string $docType, array $file): int
{
  $app = organizer_owned_app($appId, (int) $user['id']);
  if (!in_array($app['status'], ['draft', 'more_info_required'], true)) {
    throw new AppError('この申請は編集できません。');
  }
  if (!array_key_exists($docType, ORGANIZER_DOC_SLOTS)) {
    throw new AppError('書類種別が不正です。');
  }

  $saved = storage_save($file, 'organizer_application', $appId, $docType, (int) $user['id']);
  audit_log((int) $user['id'], 'organizer.upload', 'organizer_application', $appId, ['doc_type' => $docType]);
  return $saved['id'];
}

function organizer_submit(array $user, int $appId): void
{
  $app = organizer_owned_app($appId, (int) $user['id']);
  if (!in_array($app['status'], ['draft', 'more_info_required'], true)) {
    throw new AppError('この申請は提出できません。');
  }
  if ((int) $user['trust_level'] < LEVEL_IDENTIFIED) {
    throw new AppError('本人確認（Lv.2）が完了していません。');
  }

  rate_limit_hit('organizer_submit:user:' . $user['id'], 5, 86400);

  db_update('organizer_applications', [
    'status'       => 'submitted',
    'submitted_at' => date('Y-m-d H:i:s'),
  ], 'id = :id', ['id' => $appId]);

  audit_log((int) $user['id'], 'organizer.submit', 'organizer_application', $appId);
}

/** 承認。organizer_profiles を作り、Lv.3 を与える */
function organizer_approve(int $appId, int $reviewerId, ?string $note = null): void
{
  db_transaction(static function () use ($appId, $reviewerId, $note): void {
    $app = db_one('SELECT * FROM organizer_applications WHERE id = :id FOR UPDATE', ['id' => $appId]);
    if ($app === null) {
      throw new AppError('申請が見つかりません。');
    }
    if ($app['status'] === 'approved') {
      return;
    }
    if (!in_array($app['status'], ['submitted', 'in_review', 'more_info_required'], true)) {
      throw new AppError('この申請は承認できません。');
    }

    // 申請中に失効していることがあるので、承認時点でも本人確認を見直す
    $identity = db_one(
      'SELECT id FROM identity_verifications
        WHERE user_id = :u AND status = "approved"
          AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1',
      ['u' => $app['user_id']]
    );
    if ($identity === null) {
      throw new AppError('申請者の本人確認が有効ではありません。');
    }

    db_update('organizer_applications', [
      'status'      => 'approved',
      'review_note' => $note,
      'reviewed_by' => $reviewerId,
      'reviewed_at' => date('Y-m-d H:i:s'),
    ], 'id = :id', ['id' => $appId]);

    $validDays = (int) config('verification.organizer_valid_days', 365);
    $until     = date('Y-m-d H:i:s', strtotime("+{$validDays} days"));

    $displayName = $app['organization_id'] !== null
      ? (string) db_one('SELECT name FROM organizations WHERE id = :id', ['id' => $app['organization_id']])['name']
      : (string) $app['contact_name'];

    $existing = db_one('SELECT id FROM organizer_profiles WHERE user_id = :u', ['u' => $app['user_id']]);
    $profile  = [
      'organization_id' => $app['organization_id'],
      'application_id'  => $appId,
      'display_name'    => $displayName,
      'verified_at'     => date('Y-m-d H:i:s'),
      'verified_until'  => $until,
      'status'          => 'active',
    ];

    if ($existing !== null) {
      db_update('organizer_profiles', $profile + ['suspend_reason' => null], 'id = :id', ['id' => $existing['id']]);
    } else {
      db_insert('organizer_profiles', $profile + ['user_id' => $app['user_id']]);
    }

    if ($app['organization_id'] !== null) {
      db_update('organizations', ['status' => 'verified'], 'id = :id', ['id' => $app['organization_id']]);
    }

    recalc_trust_level((int) $app['user_id']);
    audit_log($reviewerId, 'organizer.approve', 'organizer_application', $appId, [
      'user_id'     => (int) $app['user_id'],
      'valid_until' => $until,
    ]);

    notify_mail(
      (string) $app['contact_email'],
      '【SPOTIVE】主催者認証が完了しました',
      "主催者認証が完了しました。大会の登録ができるようになりました。\n有効期限: {$until}\n"
    );
  });
}

function organizer_reject(int $appId, int $reviewerId, string $note, bool $moreInfo = false): void
{
  db_transaction(static function () use ($appId, $reviewerId, $note, $moreInfo): void {
    $app = db_one('SELECT * FROM organizer_applications WHERE id = :id FOR UPDATE', ['id' => $appId]);
    if ($app === null) {
      throw new AppError('申請が見つかりません。');
    }

    $status = $moreInfo ? 'more_info_required' : 'rejected';
    db_update('organizer_applications', [
      'status'      => $status,
      'review_note' => mb_substr($note, 0, 1000),
      'reviewed_by' => $reviewerId,
      'reviewed_at' => date('Y-m-d H:i:s'),
    ], 'id = :id', ['id' => $appId]);

    audit_log($reviewerId, 'organizer.' . $status, 'organizer_application', $appId, ['note' => $note]);

    notify_mail(
      (string) $app['contact_email'],
      '【SPOTIVE】主催者認証の審査結果',
      ($moreInfo ? "追加の確認が必要です。\n" : "認証を見送らせていただきました。\n") . "内容: {$note}\n"
    );
  });
}

/** 資格の停止・取消（違反時）。停止すると新しい大会を作れなくなる */
function organizer_suspend(int $userId, int $actorId, string $reason, bool $revoke = false): void
{
  db_update(
    'organizer_profiles',
    ['status' => $revoke ? 'revoked' : 'suspended', 'suspend_reason' => mb_substr($reason, 0, 500)],
    'user_id = :u',
    ['u' => $userId]
  );
  recalc_trust_level($userId);
  audit_log($actorId, $revoke ? 'organizer.revoke' : 'organizer.suspend', 'user', $userId, ['reason' => $reason]);
}

/** @return array{application:?array,profile:?array} */
function organizer_status(int $userId): array
{
  return [
    'application' => db_one(
      'SELECT * FROM organizer_applications WHERE user_id = :u ORDER BY id DESC LIMIT 1',
      ['u' => $userId]
    ),
    'profile' => db_one(
      'SELECT * FROM organizer_profiles WHERE user_id = :u',
      ['u' => $userId]
    ),
  ];
}

function organizer_owned_app(int $appId, int $userId): array
{
  $app = db_one(
    'SELECT * FROM organizer_applications WHERE id = :id AND user_id = :u',
    ['id' => $appId, 'u' => $userId]
  );
  if ($app === null) {
    throw new AppError('申請が見つかりません。');
  }
  return $app;
}
