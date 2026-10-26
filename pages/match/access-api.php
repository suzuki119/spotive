<?php

/**
 * pages/match/access-api.php
 * 試合詳細の閲覧と、チケット販売サイトへのリンクを押したことを数える（js/pages/match-detail.js から送る）。
 * 数えた結果は管理者画面のアクセスボードに出る（lib/access.php）。返すのは JSON で、画面は持たない。
 *
 *   type=match|tournament  id=match-001|5  metric=page_view|ticket_click
 *
 * 同じ人が開き直すたびに増えないよう、閲覧は 1 日 1 回（セッションごと）だけ数える。
 * チケットのリンクは押すたびに数える。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/access.php';

/** JSON を返して終わる */
function access_api_respond(int $status, array $body): never
{
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');
  echo json_encode($body, JSON_UNESCAPED_UNICODE);
  exit;
}

if (!is_post()) {
  access_api_respond(405, ['ok' => false]);
}

try {
  csrf_verify();

  $type   = input_string($_POST, 'type');
  $id     = input_string($_POST, 'id');
  $metric = input_string($_POST, 'metric');

  if ($metric === 'page_view') {
    $today = date('Y-m-d');
    $key   = $type . ':' . $id;
    // 日が変わったら、数えた印を消す（セッションが大きくなりすぎないように）
    if (($_SESSION['access_seen']['date'] ?? '') !== $today) {
      $_SESSION['access_seen'] = ['date' => $today, 'keys' => []];
    }
    if (isset($_SESSION['access_seen']['keys'][$key])) {
      access_api_respond(200, ['ok' => true]);
    }
    $_SESSION['access_seen']['keys'][$key] = true;
  }

  access_count($type, $id, $metric);
  access_api_respond(200, ['ok' => true]);
} catch (AppError $e) {
  access_api_respond(400, ['ok' => false]);
} catch (PDOException $e) {
  error_log('[SPOTIVE] DB error: ' . $e->getMessage());
  access_api_respond(500, ['ok' => false]);
}
