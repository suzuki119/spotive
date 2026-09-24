<?php

/**
 * lib/audit.php
 * 監査ログ。誰がどの審査をどう動かしたかを必ず残す。
 * 承認・却下・書類の閲覧は、例外なくここを通すこと。
 */

declare(strict_types=1);

require_once __DIR__ . '/support.php';

/** @param array<string,mixed> $detail 補足情報。JSON で残る */
function audit_log(?int $actorUserId, string $action, string $targetType, ?int $targetId, array $detail = []): void
{
  db_insert('audit_logs', [
    'actor_user_id' => $actorUserId,
    'action'        => $action,
    'target_type'   => $targetType,
    'target_id'     => $targetId,
    'detail'        => $detail === [] ? null : json_encode($detail, JSON_UNESCAPED_UNICODE),
    'ip'            => request_ip_binary(),
    'user_agent'    => request_user_agent(),
  ]);
}
