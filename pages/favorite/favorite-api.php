<?php

/**
 * pages/favorite/favorite-api.php
 * お気に入りの登録・解除を受け付ける（js/common/favorite-store.js から fetch で呼ぶ）。
 * ログイン中のユーザーだけが使える。返すのは JSON で、画面は持たない。
 *
 *   action=toggle  team_id=team-001  on=1|0  … 1 件の登録・解除
 *   action=merge   team_ids[]=...            … ブラウザに保存していた分をアカウントに移す
 *
 * 返り値: {"ok": true, "teamIds": [...]}（保存後のお気に入り一覧）
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/favorite.php';

/** JSON を返して終わる */
function favorite_api_respond(int $status, array $body): never
{
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');
  echo json_encode($body, JSON_UNESCAPED_UNICODE);
  exit;
}

if (!is_post()) {
  favorite_api_respond(405, ['ok' => false, 'message' => 'この操作は受け付けていません。']);
}

try {
  csrf_verify();

  $user = current_user();
  if ($user === null) {
    favorite_api_respond(401, ['ok' => false, 'message' => 'ログインが必要です。']);
  }
  $userId = (int) $user['id'];

  switch ((string) ($_POST['action'] ?? '')) {
    case 'toggle':
      favorite_set($userId, trim((string) ($_POST['team_id'] ?? '')), ($_POST['on'] ?? '') === '1');
      break;

    case 'merge':
      $ids = $_POST['team_ids'] ?? [];
      favorite_merge($userId, is_array($ids) ? $ids : []);
      break;

    default:
      throw new AppError('操作が不正です。');
  }

  favorite_api_respond(200, ['ok' => true, 'teamIds' => favorite_team_ids($userId)]);
} catch (AppError $e) {
  favorite_api_respond(400, ['ok' => false, 'message' => $e->getMessage()]);
} catch (PDOException $e) {
  error_log('[SPOTIVE] DB error: ' . $e->getMessage());
  favorite_api_respond(500, ['ok' => false, 'message' => '保存できませんでした。時間をおいてお試しください。']);
}
