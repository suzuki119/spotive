<?php

/**
 * pages/admin/attachment.php
 * 審査担当が添付書類の中身を見る。復号してそのまま返す。
 * 誰がどのファイルを見たかは必ず監査ログに残る。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/review.php';

$reviewer = require_role('reviewer', 'admin');

try {
  review_stream_attachment($reviewer, (int) ($_GET['id'] ?? 0));
} catch (AppError $e) {
  http_response_code(404);
  header('Content-Type: text/plain; charset=utf-8');
  echo $e->getMessage();
}
