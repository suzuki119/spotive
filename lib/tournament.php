<?php

/**
 * lib/tournament.php
 * Lv.4 大会確認済み。
 *   主催者（Lv.3）が登録した「その大会」を 1 件ずつ確認する。
 *   確認できたら tournaments.verification_status = 'verified' になり、
 *   一覧・地図に「確認済み」バッジが出る。
 */

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once __DIR__ . '/validator.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/ratelimit.php';
require_once __DIR__ . '/notifier.php';
require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/geocoder.php';

/** 審査チェックリスト。審査画面はこの配列をもとに表示する */
const TOURNAMENT_CHECK_ITEMS = [
  'title'             => '大会名',
  'schedule'          => '開催日時',
  'venue'             => '開催場所',
  'sport'             => '競技種目',
  'eligibility'       => '参加条件',
  'entry_fee'         => '参加費',
  'capacity'          => '定員',
  'entry_period'      => '募集期間',
  'rules'             => '大会ルール',
  'organizer'         => '主催者',
  'venue_permission'  => '会場の利用許可・予約確認',
  'permits'           => '必要な許可・届出の有無',
  'insurance'         => '保険加入の有無',
  'emergency_contact' => '緊急時の連絡先',
];

const TOURNAMENT_VENUE_PERMISSION = [
  'unknown'      => '未確認',
  'reserved'     => '予約済み',
  'permitted'    => '利用許可あり',
  'not_required' => '許可不要',
];

const TOURNAMENT_INSURANCE_STATUS = [
  'none'    => '未加入',
  'applied' => '申込済み',
  'insured' => '加入済み',
];

const TOURNAMENT_DOC_SLOTS = [
  'venue_permit'      => '施設利用許可書',
  'venue_reservation' => '会場予約確認書',
  'permit'            => '許可証・届出',
  'insurance'         => '保険証券',
  'rulebook'          => '大会要項',
  'other'             => 'その他',
];

/**
 * 大会の作成。必要レベルは config/app.php の verification.require_level.create_tournament。
 * @return int tournaments.id
 */
function tournament_create(array $user, array $in): int
{
  tournament_validate_input($in);

  $profile = db_one(
    'SELECT organization_id FROM organizer_profiles WHERE user_id = :u AND status = "active"',
    ['u' => $user['id']]
  );

  // 審査を挟まない設定のあいだは、作成した時点で公開する
  $reviewRequired = (bool) config('verification.tournament_review_required', true);

  $id = db_insert('tournaments', tournament_columns($in) + [
    'organizer_user_id'   => $user['id'],
    'organization_id'     => $profile['organization_id'] ?? null,
    'status'              => $reviewRequired ? 'draft' : 'published',
    'verification_status' => 'unverified',
  ]);

  audit_log((int) $user['id'], 'tournament.create', 'tournament', $id);
  return $id;
}

function tournament_update(array $user, int $id, array $in): void
{
  $t = tournament_owned($id, (int) $user['id']);
  if (in_array($t['verification_status'], ['submitted', 'in_review'], true)) {
    throw new AppError('確認申請中は内容を変更できません。');
  }
  if (in_array($t['status'], ['cancelled', 'finished'], true)) {
    throw new AppError('中止・終了した大会は変更できません。');
  }

  // 送られてきた項目だけを書き換える（フォームにない許可・保険などの項目を消さない）
  $sent = array_intersect_key($in, $t);
  unset(
    $sent['id'], $sent['organizer_user_id'], $sent['organization_id'], $sent['status'],
    $sent['verification_status'], $sent['verified_at'], $sent['verified_by'],
    $sent['created_at'], $sent['updated_at']
  );
  $merged = $sent + $t;

  // 住所を変えたのに緯度経度が来ていなければ、古い位置は捨てて住所から求め直す
  $moved = trim((string) $merged['venue_address']) !== $t['venue_address']
    || trim((string) $merged['venue_prefecture']) !== $t['venue_prefecture'];
  if ($moved && !isset($sent['venue_lat'], $sent['venue_lng'])) {
    $merged['venue_lat'] = $merged['venue_lng'] = '';
  }

  tournament_validate_input($merged, $t);
  db_update('tournaments', tournament_columns($merged), 'id = :id', ['id' => $id]);

  // 確認済みの大会を書き換えたらバッジは外す（内容と保証のズレを防ぐ）
  if ($t['verification_status'] === 'verified') {
    db_update('tournaments', [
      'verification_status' => 'unverified',
      'verified_at'         => null,
      'verified_by'         => null,
    ], 'id = :id', ['id' => $id]);
    audit_log((int) $user['id'], 'tournament.badge_revoked_by_edit', 'tournament', $id);
  }

  audit_log((int) $user['id'], 'tournament.update', 'tournament', $id);
}

/** 自分が登録した大会の一覧（下書き・中止も含む） */
function tournament_list_mine(int $userId): array
{
  return db_all(
    'SELECT id, title, sport, starts_at, ends_at, venue_name, venue_prefecture,
            status, verification_status, updated_at
       FROM tournaments
      WHERE organizer_user_id = :u
      ORDER BY starts_at DESC',
    ['u' => $userId]
  );
}

/** 編集フォーム用。本人の大会なので内部情報も含めて返す */
function tournament_owned_detail(int $userId, int $id): array
{
  $t = tournament_owned($id, $userId);
  $t['editable'] = !in_array($t['verification_status'], ['submitted', 'in_review'], true)
    && !in_array($t['status'], ['cancelled', 'finished'], true);
  return $t;
}

/** 会場予約確認書・施設利用許可書・保険証券などの添付 */
function tournament_upload_document(array $user, int $id, string $docType, array $file): int
{
  tournament_owned($id, (int) $user['id']);
  if (!array_key_exists($docType, TOURNAMENT_DOC_SLOTS)) {
    throw new AppError('書類種別が不正です。');
  }
  $saved = storage_save($file, 'tournament', $id, $docType, (int) $user['id']);
  audit_log((int) $user['id'], 'tournament.upload', 'tournament', $id, ['doc_type' => $docType]);
  return $saved['id'];
}

/** 大会確認の申請。申請時点の内容をスナップショットで固定する */
function tournament_submit_verification(array $user, int $id): int
{
  $t = tournament_owned($id, (int) $user['id']);
  if (in_array($t['verification_status'], ['submitted', 'in_review'], true)) {
    throw new AppError('既に確認申請中です。');
  }

  $missing = tournament_missing_for_verification($t);
  if ($missing !== []) {
    throw new AppError('確認申請に必要な情報が不足しています。', $missing);
  }

  rate_limit_hit('tournament_verify:user:' . $user['id'], 20, 86400);

  return db_transaction(static function () use ($t, $id, $user): int {
    $verificationId = db_insert('tournament_verifications', [
      'tournament_id' => $id,
      'submitted_by'  => $user['id'],
      'status'        => 'submitted',
      'snapshot'      => json_encode($t, JSON_UNESCAPED_UNICODE),
    ]);

    foreach (array_keys(TOURNAMENT_CHECK_ITEMS) as $key) {
      db_insert('tournament_verification_checks', [
        'verification_id' => $verificationId,
        'item_key'        => $key,
        'result'          => 'pending',
      ]);
    }

    db_update('tournaments', ['verification_status' => 'submitted'], 'id = :id', ['id' => $id]);
    audit_log((int) $user['id'], 'tournament.submit_verification', 'tournament', $id, [
      'verification_id' => $verificationId,
    ]);

    return $verificationId;
  });
}

/** 提出前に足りない項目を洗い出す。@return array<string,string> */
function tournament_missing_for_verification(array $t): array
{
  $missing = [];

  if ($t['venue_permission_status'] === 'unknown') {
    $missing['venue_permission_status'] = '会場の利用許可・予約状況を選択してください。';
  }
  if (in_array($t['venue_permission_status'], ['reserved', 'permitted'], true)) {
    $doc = db_one(
      'SELECT id FROM attachments
        WHERE owner_type = "tournament" AND owner_id = :id AND deleted_at IS NULL
          AND doc_type IN ("venue_permit","venue_reservation") LIMIT 1',
      ['id' => $t['id']]
    );
    if ($doc === null) {
      $missing['venue_permit'] = '会場予約確認書または施設利用許可書を添付してください。';
    }
  }
  if ((int) $t['permits_required'] === 1 && trim((string) $t['permits_note']) === '') {
    $missing['permits_note'] = '必要な許可・届出の内容を記入してください。';
  }
  if ($t['insurance_status'] === 'insured' && trim((string) $t['insurance_provider']) === '') {
    $missing['insurance_provider'] = '保険会社名を入力してください。';
  }
  if ($t['entry_opens_at'] === null || $t['entry_closes_at'] === null) {
    $missing['entry_period'] = '募集期間（開始・終了）を入力してください。';
  }

  return $missing;
}

/** 審査担当が 1 項目ずつ結果を記録する */
function tournament_check(int $reviewerId, int $verificationId, string $itemKey, string $result, ?string $note): void
{
  if (!array_key_exists($itemKey, TOURNAMENT_CHECK_ITEMS)) {
    throw new AppError('チェック項目が不正です。');
  }
  if (!in_array($result, ['pending', 'ok', 'ng', 'na'], true)) {
    throw new AppError('チェック結果が不正です。');
  }

  $ver = db_one('SELECT * FROM tournament_verifications WHERE id = :id', ['id' => $verificationId]);
  if ($ver === null) {
    throw new AppError('確認申請が見つかりません。');
  }

  db_run(
    'INSERT INTO tournament_verification_checks (verification_id, item_key, result, note, checked_by, checked_at)
     VALUES (:v, :k, :r, :n, :u, NOW())
     ON DUPLICATE KEY UPDATE result = VALUES(result), note = VALUES(note),
                             checked_by = VALUES(checked_by), checked_at = VALUES(checked_at)',
    ['v' => $verificationId, 'k' => $itemKey, 'r' => $result, 'n' => $note, 'u' => $reviewerId]
  );

  // 最初のチェックが入った時点で「審査中」に進める
  if ($ver['status'] === 'submitted') {
    db_update('tournament_verifications', ['status' => 'in_review'], 'id = :id', ['id' => $verificationId]);
    db_update('tournaments', ['verification_status' => 'in_review'], 'id = :id', ['id' => $ver['tournament_id']]);
  }
}

/** 承認。必須項目がすべて ok / na でなければ通さない */
function tournament_approve(int $reviewerId, int $verificationId, ?string $note = null): void
{
  db_transaction(static function () use ($reviewerId, $verificationId, $note): void {
    $ver = db_one('SELECT * FROM tournament_verifications WHERE id = :id FOR UPDATE', ['id' => $verificationId]);
    if ($ver === null) {
      throw new AppError('確認申請が見つかりません。');
    }
    if ($ver['status'] === 'approved') {
      return;
    }

    $checks = db_all(
      'SELECT item_key, result FROM tournament_verification_checks WHERE verification_id = :v',
      ['v' => $verificationId]
    );
    $byKey = array_column($checks, 'result', 'item_key');

    $unfinished = [];
    foreach (TOURNAMENT_CHECK_ITEMS as $key => $label) {
      if (!in_array($byKey[$key] ?? 'pending', ['ok', 'na'], true)) {
        $unfinished[$key] = $label . ' が未確認です。';
      }
    }
    if ($unfinished !== []) {
      throw new AppError('未確認のチェック項目があります。', $unfinished);
    }

    // 主催者が今も有効な Lv.3 かを承認時点で再確認する
    $t         = db_one('SELECT * FROM tournaments WHERE id = :id', ['id' => $ver['tournament_id']]);
    $organizer = db_one('SELECT trust_level FROM users WHERE id = :u', ['u' => $t['organizer_user_id']]);
    if ((int) $organizer['trust_level'] < LEVEL_ORGANIZER) {
      throw new AppError('主催者の認証が有効ではありません。');
    }

    db_update('tournament_verifications', [
      'status'      => 'approved',
      'review_note' => $note,
      'reviewed_by' => $reviewerId,
      'reviewed_at' => date('Y-m-d H:i:s'),
    ], 'id = :id', ['id' => $verificationId]);

    db_update('tournaments', [
      'verification_status' => 'verified',
      'verified_at'         => date('Y-m-d H:i:s'),
      'verified_by'         => $reviewerId,
      'status'              => $t['status'] === 'draft' ? 'published' : $t['status'],
    ], 'id = :id', ['id' => $t['id']]);

    audit_log($reviewerId, 'tournament.verify', 'tournament', (int) $t['id'], [
      'verification_id' => $verificationId,
    ]);

    $u = db_one('SELECT email FROM users WHERE id = :id', ['id' => $t['organizer_user_id']]);
    notify_mail(
      (string) $u['email'],
      '【SPOTIVE】大会が確認済みになりました',
      "「{$t['title']}」が 大会確認済み になりました。\n"
    );
  });
}

function tournament_reject(int $reviewerId, int $verificationId, string $note, bool $moreInfo = false): void
{
  db_transaction(static function () use ($reviewerId, $verificationId, $note, $moreInfo): void {
    $ver = db_one('SELECT * FROM tournament_verifications WHERE id = :id FOR UPDATE', ['id' => $verificationId]);
    if ($ver === null) {
      throw new AppError('確認申請が見つかりません。');
    }

    $status = $moreInfo ? 'more_info_required' : 'rejected';
    db_update('tournament_verifications', [
      'status'      => $status,
      'review_note' => mb_substr($note, 0, 1000),
      'reviewed_by' => $reviewerId,
      'reviewed_at' => date('Y-m-d H:i:s'),
    ], 'id = :id', ['id' => $verificationId]);

    db_update('tournaments', ['verification_status' => $status], 'id = :id', ['id' => $ver['tournament_id']]);
    audit_log($reviewerId, 'tournament.' . $status, 'tournament', (int) $ver['tournament_id'], ['note' => $note]);

    $t = db_one(
      'SELECT t.title, u.email FROM tournaments t JOIN users u ON u.id = t.organizer_user_id WHERE t.id = :id',
      ['id' => $ver['tournament_id']]
    );
    notify_mail(
      (string) $t['email'],
      '【SPOTIVE】大会確認の結果',
      "「{$t['title']}」の確認結果をお知らせします。\n内容: {$note}\n"
    );
  });
}

/** 審査画面用。申請内容・チェックリスト・添付をまとめて返す */
function tournament_verification_detail(int $verificationId): array
{
  $ver = db_one('SELECT * FROM tournament_verifications WHERE id = :id', ['id' => $verificationId]);
  if ($ver === null) {
    throw new AppError('確認申請が見つかりません。');
  }

  $byKey = [];
  foreach (db_all(
    'SELECT item_key, result, note, checked_at FROM tournament_verification_checks WHERE verification_id = :v',
    ['v' => $verificationId]
  ) as $check) {
    $byKey[$check['item_key']] = $check;
  }

  $items = [];
  foreach (TOURNAMENT_CHECK_ITEMS as $key => $label) {
    $items[] = [
      'key'    => $key,
      'label'  => $label,
      'result' => $byKey[$key]['result'] ?? 'pending',
      'note'   => $byKey[$key]['note'] ?? null,
    ];
  }

  $ver['snapshot']  = json_decode((string) $ver['snapshot'], true);
  $ver['checks']    = $items;
  $ver['documents'] = storage_list('tournament', (int) $ver['tournament_id']);
  return $ver;
}

/** 公開用の 1 件取得（バッジ付き）。運営向けの内部情報は落とす */
function tournament_public_detail(int $id): array
{
  $t = db_one(
    'SELECT t.*, COALESCE(op.display_name, u.nickname) AS organizer_name,
            u.trust_level AS organizer_trust_level
       FROM tournaments t
       JOIN users u ON u.id = t.organizer_user_id
  LEFT JOIN organizer_profiles op ON op.user_id = t.organizer_user_id AND op.status = "active"
      WHERE t.id = :id AND t.status = "published"',
    ['id' => $id]
  );
  if ($t === null) {
    throw new AppError('大会が見つかりません。');
  }

  foreach (['insurance_policy_no', 'venue_permission_ref', 'verified_by'] as $key) {
    unset($t[$key]);
  }
  $t['is_verified'] = $t['verification_status'] === 'verified';
  return $t;
}

/** @param array|null $current 編集時は変更前の行。開催日時を変えていなければ過去日でも通す */
function tournament_validate_input(array $in, ?array $current = null): void
{
  $v = (new Validator($in))
    ->required('title', '大会名')->length('title', '大会名', 1, 150)
    ->required('sport', '競技種目')->length('sport', '競技種目', 1, 50)
    ->required('starts_at', '開催日時')->datetime('starts_at', '開催日時')
    ->required('ends_at', '終了日時')->datetime('ends_at', '終了日時')
    ->datetime('entry_opens_at', '募集開始日時')
    ->datetime('entry_closes_at', '募集終了日時')
    ->required('venue_name', '会場名')->length('venue_name', '会場名', 1, 150)
    ->required('venue_prefecture', '都道府県')->length('venue_prefecture', '都道府県', 1, 20)
    ->required('venue_address', '会場住所')->length('venue_address', '会場住所', 1, 255)
    ->required('eligibility', '参加条件')
    ->intRange('entry_fee_yen', '参加費', 0, 1000000)
    ->required('capacity', '定員')->intRange('capacity', '定員', 1, 10000)
    ->required('rules', '大会ルール')
    ->required('emergency_contact_name', '緊急時の連絡先（氏名）')
    ->required('emergency_contact_phone', '緊急時の連絡先（電話）')->tel('emergency_contact_phone')
    ->in('venue_permission_status', '会場利用許可', array_keys(TOURNAMENT_VENUE_PERMISSION))
    ->in('insurance_status', '保険加入状況', array_keys(TOURNAMENT_INSURANCE_STATUS));

  $zip = trim((string) ($in['venue_postal_code'] ?? ''));
  if ($zip !== '' && Validator::normalizePostalCode($zip) === null) {
    $v->add('venue_postal_code', '郵便番号は7桁の数字で入力してください。');
  }

  $startsAt = strtotime((string) ($in['starts_at'] ?? ''));
  $endsAt   = strtotime((string) ($in['ends_at'] ?? ''));
  if ($startsAt !== false && $endsAt !== false && $endsAt < $startsAt) {
    $v->add('ends_at', '終了日時は開催日時より後にしてください。');
  }

  // 編集時、開催日時を動かしていなければ過去日のままでも通す
  $startsChanged = $current === null || $startsAt !== strtotime((string) $current['starts_at']);
  if ($startsChanged && $startsAt !== false && $startsAt < time()) {
    $v->add('starts_at', '開催日時には未来の日時を指定してください。');
  }

  $opensAt  = ($in['entry_opens_at'] ?? '') === '' ? null : strtotime((string) $in['entry_opens_at']);
  $closesAt = ($in['entry_closes_at'] ?? '') === '' ? null : strtotime((string) $in['entry_closes_at']);
  if ($opensAt !== null && $closesAt !== null && $closesAt < $opensAt) {
    $v->add('entry_closes_at', '募集終了は募集開始より後にしてください。');
  }
  if ($closesAt !== null && $startsAt !== false && $closesAt > $startsAt) {
    $v->add('entry_closes_at', '募集終了は開催日時より前にしてください。');
  }

  $v->validate();
}

/** フォームの入力を tournaments の列にそろえる @return array<string,mixed> */
function tournament_columns(array $in): array
{
  $dt = static fn(?string $v): ?string => ($v === null || $v === '') ? null : date('Y-m-d H:i:s', (int) strtotime($v));

  return tournament_fill_coordinates([
    'title'             => trim((string) $in['title']),
    'sport'             => trim((string) $in['sport']),
    'description'       => ($in['description'] ?? '') === '' ? null : (string) $in['description'],
    'starts_at'         => $dt((string) $in['starts_at']),
    'ends_at'           => $dt((string) $in['ends_at']),
    'entry_opens_at'    => $dt($in['entry_opens_at'] ?? null),
    'entry_closes_at'   => $dt($in['entry_closes_at'] ?? null),
    'venue_name'        => trim((string) $in['venue_name']),
    'venue_postal_code' => Validator::normalizePostalCode((string) ($in['venue_postal_code'] ?? '')),
    'venue_prefecture'  => trim((string) $in['venue_prefecture']),
    'venue_address'     => trim((string) $in['venue_address']),
    'venue_lat'         => ($in['venue_lat'] ?? '') === '' ? null : (float) $in['venue_lat'],
    'venue_lng'         => ($in['venue_lng'] ?? '') === '' ? null : (float) $in['venue_lng'],
    'is_indoor'         => empty($in['is_indoor']) ? 0 : 1,
    'eligibility'       => (string) $in['eligibility'],
    'entry_fee_yen'     => (int) ($in['entry_fee_yen'] ?? 0),
    'capacity'          => (int) $in['capacity'],
    'rules'             => (string) $in['rules'],
    'venue_permission_status' => (string) ($in['venue_permission_status'] ?? 'unknown'),
    'venue_permission_ref'    => optional_text($in, 'venue_permission_ref'),
    'permits_required'  => empty($in['permits_required']) ? 0 : 1,
    'permits_note'      => optional_text($in, 'permits_note'),
    'insurance_status'  => (string) ($in['insurance_status'] ?? 'none'),
    'insurance_provider'  => optional_text($in, 'insurance_provider'),
    'insurance_policy_no' => optional_text($in, 'insurance_policy_no'),
    'emergency_contact_name'  => trim((string) $in['emergency_contact_name']),
    'emergency_contact_phone' => Validator::normalizeTel((string) $in['emergency_contact_phone'])
      ?? trim((string) $in['emergency_contact_phone']),
  ]);
}

/** 緯度・経度が未入力なら住所から求める。求められなければ空のまま（地図には出ない） */
function tournament_fill_coordinates(array $cols): array
{
  if ($cols['venue_lat'] !== null && $cols['venue_lng'] !== null) {
    return $cols;
  }
  $pos = geocode((string) $cols['venue_prefecture'], (string) $cols['venue_address']);
  if ($pos !== null) {
    $cols['venue_lat'] = $pos['lat'];
    $cols['venue_lng'] = $pos['lng'];
  }
  return $cols;
}

function tournament_owned(int $id, int $userId): array
{
  $t = db_one(
    'SELECT * FROM tournaments WHERE id = :id AND organizer_user_id = :u',
    ['id' => $id, 'u' => $userId]
  );
  if ($t === null) {
    throw new AppError('大会が見つかりません。');
  }
  return $t;
}
