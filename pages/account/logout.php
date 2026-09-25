<?php

/**
 * pages/account/logout.php
 * ログアウト。他サイトから勝手に叩かれないよう POST だけを受ける。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';

session_boot();

if (!is_post()) {
  redirect(url('index.php'));
}

try {
  csrf_verify();
  logout_user();
} catch (AppError $e) {
  error_log('[SPOTIVE] logout: ' . $e->getMessage());
}

redirect(url('index.php'));
