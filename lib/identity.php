<?php

/**
 * lib/identity.php
 * Lv.2 本人確認。
 *
 *   provider = 'manual'        … 自前で目視審査する。書類画像を attachments に
 *                                暗号化保存し、審査が終わったら purge_after で消す。
 *   provider = 'external_ekyc' … 外部サービスに書類を預け、こちらは結果だけ持つ（推奨）。
 *
 * どちらで動くかは config/app.php の verification.identity_provider で決まる。
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

const IDENTITY_DOCUMENT_TYPES = [
  'drivers_license' => '運転免許証',
  'my_number_card'  => 'マイナンバーカード',
  'residence_card'  => '在留カード',
  'passport'        => 'パスポート',
];

/** manual モードで提出してもらう書類 */
const IDENTITY_DOC_SLOTS = [
  'id_front' => '本人確認書類（表）',
  'id_back'  => '本人確認書類（裏）',
  'selfie'   => '本人の顔写真',
];

/** 申請の開始。@return int identity_verifications.id */
function identity_start(array $user, array $in): int
{
  if ((int) $user['trust_level'] < LEVEL_USER) {
    throw new AppError('先にメールアドレスの確認を完了してください。');
  }

  $existing = db_one(
    'SELECT id, status FROM identity_verifications
      WHERE user_id = :u AND status IN ("submitted","in_review","approved")
        AND (expires_at IS NULL OR expires_at > NOW())
      ORDER BY id DESC LIMIT 1',
    ['u' => $user['id']]
  );
  if ($existing !== null) {
    throw new AppError($existing['status'] === 'approved' ? '既に本人確認済みです。' : '審査中の申請があります。');
  }

  (new Validator($in))
    ->required('legal_name', '氏名')->length('legal_name', '氏名', 1, 100)
    ->length('legal_name_kana', '氏名（カナ）', 0, 100)
    ->required('birthdate', '生年月日')->date('birthdate', '生年月日')
    ->required('document_type', '本人確認書類')
    ->in('document_type', '本人確認書類', array_keys(IDENTITY_DOCUMENT_TYPES))
    ->validate();

  rate_limit_hit('identity:user:' . $user['id'], 5, 86400);

  $provider = (string) config('verification.identity_provider', 'manual');

  $id = db_insert('identity_verifications', [
    'user_id'         => $user['id'],
    'provider'        => $provider,
    'legal_name'      => trim((string) $in['legal_name']),
    'legal_name_kana' => trim((string) ($in['legal_name_kana'] ?? '')) ?: null,
    'birthdate'       => (string) $in['birthdate'],
    'document_type'   => (string) $in['document_type'],
    'status'          => 'draft',
  ]);

  audit_log((int) $user['id'], 'identity.start', 'identity_verification', $id, ['provider' => $provider]);

  if ($provider === 'external_ekyc') {
    // 実サービス（TRUSTDOCK / Liquid eKYC 等）の呼び出しはここに入れる。
    // 返ってきた applicant_id を provider_reference に保存し、結果は webhook で受け取る。
    db_update('identity_verifications', [
      'provider_reference' => 'ekyc_' . bin2hex(random_bytes(12)),
      'status'             => 'submitted',
      'submitted_at'       => date('Y-m-d H:i:s'),
    ], 'id = :id', ['id' => $id]);
  }

  return $id;
}

/** manual モード：書類画像をアップロードする */
function identity_upload_document(array $user, int $identityId, string $docType, array $file): int
{
  $iv = identity_owned_row($identityId, (int) $user['id']);
  if ($iv['provider'] !== 'manual') {
    throw new AppError('このお申し込みでは画像アップロードは不要です。');
  }
  if (!in_array($iv['status'], ['draft', 'more_info_required'], true)) {
    throw new AppError('この申請は編集できません。');
  }
  if (!array_key_exists($docType, IDENTITY_DOC_SLOTS)) {
    throw new AppError('書類種別が不正です。');
  }

  $days  = (int) config('storage.identity_retention_days', 30);
  $purge = date('Y-m-d H:i:s', strtotime("+{$days} days"));

  $saved = storage_save($file, 'identity', $identityId, $docType, (int) $user['id'], $purge);
  audit_log((int) $user['id'], 'identity.upload', 'identity_verification', $identityId, [
    'doc_type'      => $docType,
    'attachment_id' => $saved['id'],
  ]);

  return $saved['id'];
}

/** manual モード：審査へ提出する */
function identity_submit(array $user, int $identityId): void
{
  $iv = identity_owned_row($identityId, (int) $user['id']);
  if (!in_array($iv['status'], ['draft', 'more_info_required'], true)) {
    throw new AppError('この申請は提出できません。');
  }

  $count = (int) db_one(
    'SELECT COUNT(*) AS c FROM attachments
      WHERE owner_type = "identity" AND owner_id = :id AND deleted_at IS NULL',
    ['id' => $identityId]
  )['c'];
  if ($count === 0) {
    throw new AppError('本人確認書類の画像をアップロードしてください。');
  }

  db_update('identity_verifications', [
    'status'       => 'submitted',
    'submitted_at' => date('Y-m-d H:i:s'),
  ], 'id = :id', ['id' => $identityId]);

  audit_log((int) $user['id'], 'identity.submit', 'identity_verification', $identityId);
}

/** 承認。ここだけが Lv.2 を与える入口 */
function identity_approve(int $identityId, ?int $reviewerId, array $payload = []): void
{
  db_transaction(static function () use ($identityId, $reviewerId, $payload): void {
    $iv = db_one('SELECT * FROM identity_verifications WHERE id = :id FOR UPDATE', ['id' => $identityId]);
    if ($iv === null) {
      throw new AppError('申請が見つかりません。');
    }
    if ($iv['status'] === 'approved') {
      return;   // 二重処理しない
    }

    $validDays = (int) config('verification.identity_valid_days', 730);

    db_update('identity_verifications', [
      'status'           => 'approved',
      'reviewed_at'      => date('Y-m-d H:i:s'),
      'reviewed_by'      => $reviewerId,
      'expires_at'       => date('Y-m-d H:i:s', strtotime("+{$validDays} days")),
      'result_payload'   => $payload === [] ? null : json_encode($payload, JSON_UNESCAPED_UNICODE),
      'reject_reason'    => null,
      // 外部サービスから氏名・生年月日が返る場合は確認済みの値で上書きする
      'legal_name'       => $payload['legal_name'] ?? $iv['legal_name'],
      'birthdate'        => $payload['birthdate'] ?? $iv['birthdate'],
      'liveness_checked' => isset($payload['liveness']) ? (int) (bool) $payload['liveness'] : $iv['liveness_checked'],
    ], 'id = :id', ['id' => $identityId]);

    recalc_trust_level((int) $iv['user_id']);
    identity_schedule_purge($identityId);
    audit_log($reviewerId, 'identity.approve', 'identity_verification', $identityId, [
      'user_id' => (int) $iv['user_id'],
    ]);

    $u = db_one('SELECT email FROM users WHERE id = :id', ['id' => $iv['user_id']]);
    notify_mail(
      (string) $u['email'],
      '【SPOTIVE】本人確認が完了しました',
      "本人確認が完了しました。主催者認証（Lv.3）の申請ができるようになりました。\n"
    );
  });
}

function identity_reject(int $identityId, ?int $reviewerId, string $reason, bool $moreInfo = false): void
{
  db_transaction(static function () use ($identityId, $reviewerId, $reason, $moreInfo): void {
    $iv = db_one('SELECT * FROM identity_verifications WHERE id = :id FOR UPDATE', ['id' => $identityId]);
    if ($iv === null) {
      throw new AppError('申請が見つかりません。');
    }

    db_update('identity_verifications', [
      'status'        => $moreInfo ? 'more_info_required' : 'rejected',
      'reject_reason' => mb_substr($reason, 0, 500),
      'reviewed_at'   => date('Y-m-d H:i:s'),
      'reviewed_by'   => $reviewerId,
    ], 'id = :id', ['id' => $identityId]);

    recalc_trust_level((int) $iv['user_id']);
    if (!$moreInfo) {
      identity_schedule_purge($identityId);   // 追加提出を待つ間は消さない
    }
    audit_log($reviewerId, 'identity.reject', 'identity_verification', $identityId, ['reason' => $reason]);

    $u = db_one('SELECT email FROM users WHERE id = :id', ['id' => $iv['user_id']]);
    notify_mail(
      (string) $u['email'],
      '【SPOTIVE】本人確認の結果',
      "本人確認を完了できませんでした。\n理由: {$reason}\n"
    );
  });
}

/** 審査が終わったら画像は長く持たない。削除対象の期限を今にする */
function identity_schedule_purge(int $identityId): void
{
  db_run(
    'UPDATE attachments SET purge_after = LEAST(COALESCE(purge_after, NOW()), NOW())
      WHERE owner_type = "identity" AND owner_id = :id AND deleted_at IS NULL',
    ['id' => $identityId]
  );
}

/** 直近の申請。無ければ null */
function identity_status(int $userId): ?array
{
  return db_one(
    'SELECT * FROM identity_verifications WHERE user_id = :u ORDER BY id DESC LIMIT 1',
    ['u' => $userId]
  );
}

function identity_owned_row(int $identityId, int $userId): array
{
  $iv = db_one(
    'SELECT * FROM identity_verifications WHERE id = :id AND user_id = :u',
    ['id' => $identityId, 'u' => $userId]
  );
  if ($iv === null) {
    throw new AppError('申請が見つかりません。');
  }
  return $iv;
}
