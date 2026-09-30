<?php

/**
 * pages/notification/notification-api.php
 * お知らせの既読を受け付ける（js/pages/notification.js から fetch で呼ぶ）。
 * ログイン中のユーザーだけが使える。未ログインの既読はブラウザ（localStorage）に持つ。
 *
 *   action=read  ids[]=1&ids[]=2 … 既読にする
 *
 * 返り値: {"ok": true}
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/notification.php';

/** JSON を返して終わる */
function notification_api_respond(int $status, array $body): never
{
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');
  echo json_encode($body, JSON_UNESCAPED_UNICODE);
  exit;
}

if (!is_post()) {
  notification_api_respond(405, ['ok' => false, 'message' => 'この操作は受け付けていません。']);
}

try {
  csrf_verify();

  $user = current_user();
  if ($user === null) {
    notification_api_respond(401, ['ok' => false, 'message' => 'ログインが必要です。']);
  }

  if ((string) ($_POST['action'] ?? '') !== 'read') {
    throw new AppError('操作が不正です。');
  }

  $ids = $_POST['ids'] ?? [];
  notification_mark_read((int) $user['id'], is_array($ids) ? $ids : []);

  notification_api_respond(200, ['ok' => true]);
} catch (AppError $e) {
  notification_api_respond(400, ['ok' => false, 'message' => $e->getMessage()]);
} catch (PDOException $e) {
  error_log('[SPOTIVE] DB error: ' . $e->getMessage());
  notification_api_respond(500, ['ok' => false, 'message' => '保存できませんでした。時間をおいてお試しください。']);
}
