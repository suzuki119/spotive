<?php

/**
 * pages/account/logout.php
 * ログアウト。他サイトから勝手に叩かれないよう POST だけを受ける。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';

if (!is_post()) {
  redirect(url('index.php'));
}

try {
  csrf_verify();
  logout_user();
  flash('ログアウトしました。', 'success');
} catch (AppError $e) {
  flash($e->getMessage(), 'error');
}

redirect(url('index.php'));
