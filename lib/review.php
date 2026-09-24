<?php

/**
 * lib/review.php
 * 運営（reviewer / admin）向けの審査キュー。
 * 本人確認・主催者申請・大会確認の 3 つを一覧し、詳細を開く。
 */

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/storage.php';

/**
 * 審査待ちの一覧。
 * @param string $type identity | organizer | tournament
 */
function review_queue(string $type, int $limit = 50): array
{
  // LIMIT はプレースホルダにできないので、int に丸めてから埋め込む
  $limit = max(1, min($limit, 200));

  return match ($type) {
    'identity' => db_all(
      'SELECT iv.id, iv.user_id, u.nickname, iv.provider, iv.document_type,
              iv.status, iv.submitted_at
         FROM identity_verifications iv
         JOIN users u ON u.id = iv.user_id
        WHERE iv.status IN ("submitted","in_review")
        ORDER BY iv.submitted_at ASC
        LIMIT ' . $limit
    ),
    'organizer' => db_all(
      'SELECT a.id, a.user_id, u.nickname, a.applicant_type, o.name AS organization_name,
              a.planned_title, a.planned_date, a.status, a.submitted_at
         FROM organizer_applications a
         JOIN users u ON u.id = a.user_id
    LEFT JOIN organizations o ON o.id = a.organization_id
        WHERE a.status IN ("submitted","in_review")
        ORDER BY a.submitted_at ASC
        LIMIT ' . $limit
    ),
    'tournament' => db_all(
      'SELECT v.id AS verification_id, v.tournament_id, t.title, t.sport, t.starts_at,
              t.venue_name, v.status, v.submitted_at,
              COALESCE(op.display_name, u.nickname) AS organizer_name
         FROM tournament_verifications v
         JOIN tournaments t ON t.id = v.tournament_id
         JOIN users u ON u.id = t.organizer_user_id
    LEFT JOIN organizer_profiles op ON op.user_id = t.organizer_user_id AND op.status = "active"
        WHERE v.status IN ("submitted","in_review")
        ORDER BY v.submitted_at ASC
        LIMIT ' . $limit
    ),
    default => throw new AppError('種別が不正です。'),
  };
}

/** 本人確認の詳細。申告どおりか照合できるよう、本人申告の生年月日も並べて返す */
function review_identity_detail(int $identityId): array
{
  $iv = db_one(
    'SELECT iv.*, u.nickname, u.email, u.trust_level, u.birthdate AS self_reported_birthdate
       FROM identity_verifications iv
       JOIN users u ON u.id = iv.user_id
      WHERE iv.id = :id',
    ['id' => $identityId]
  );
  if ($iv === null) {
    throw new AppError('申請が見つかりません。');
  }

  $iv['documents'] = storage_list('identity', $identityId);
  return $iv;
}

/** 主催者申請の詳細（団体情報・実績・添付をまとめて返す） */
function review_organizer_detail(int $appId): array
{
  $app = db_one(
    'SELECT a.*, u.nickname, u.email, u.trust_level,
            iv.legal_name, iv.birthdate AS verified_birthdate, iv.status AS identity_status
       FROM organizer_applications a
       JOIN users u ON u.id = a.user_id
  LEFT JOIN identity_verifications iv
         ON iv.user_id = a.user_id AND iv.status = "approved"
      WHERE a.id = :id',
    ['id' => $appId]
  );
  if ($app === null) {
    throw new AppError('申請が見つかりません。');
  }

  $app['organization'] = $app['organization_id'] === null
    ? null
    : db_one('SELECT * FROM organizations WHERE id = :id', ['id' => $app['organization_id']]);
  $app['past_events'] = db_all(
    'SELECT * FROM organizer_past_events WHERE application_id = :a ORDER BY held_on DESC',
    ['a' => $appId]
  );
  $app['documents'] = storage_list('organizer_application', $appId);

  return $app;
}

/** 審査担当が添付書類の中身を見る。復号してそのまま出力し、閲覧を監査ログに残す */
function review_stream_attachment(array $reviewer, int $attachmentId): never
{
  $a = db_one('SELECT * FROM attachments WHERE id = :id AND deleted_at IS NULL', ['id' => $attachmentId]);
  if ($a === null) {
    throw new AppError('ファイルが見つかりません。');
  }

  $bytes = storage_read($a);
  audit_log((int) $reviewer['id'], 'attachment.view', (string) $a['owner_type'], (int) $a['owner_id'], [
    'attachment_id' => $attachmentId,
  ]);

  header('Content-Type: ' . $a['mime_type']);
  header('Content-Length: ' . strlen($bytes));
  header('Content-Disposition: inline; filename="' . rawurlencode((string) $a['original_name']) . '"');
  header('Cache-Control: no-store, private');
  header('X-Content-Type-Options: nosniff');
  echo $bytes;
  exit;
}

/** 審査待ちの件数（審査画面のタブに出す） */
function review_counts(): array
{
  return [
    'identity' => (int) db_one(
      'SELECT COUNT(*) AS c FROM identity_verifications WHERE status IN ("submitted","in_review")'
    )['c'],
    'organizer' => (int) db_one(
      'SELECT COUNT(*) AS c FROM organizer_applications WHERE status IN ("submitted","in_review")'
    )['c'],
    'tournament' => (int) db_one(
      'SELECT COUNT(*) AS c FROM tournament_verifications WHERE status IN ("submitted","in_review")'
    )['c'],
  ];
}
