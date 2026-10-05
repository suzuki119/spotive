<?php

/**
 * pages/account/guest-login.php
 * 「ゲストとしてログイン」の受け口。ログイン画面のボタンから POST で来る。
 * ゲストのアカウントにログインさせて、ホームへ移す（lib/guest.php）。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/guest.php';

session_boot();

// 直接開かれたときや GET のときは、ログイン画面へ戻す（更新は POST だけ）
if (!is_post()) {
  redirect('signin.php');
}

try {
  csrf_verify();
  guest_login();
  redirect(url('pages/home/home.php'));
} catch (AppError $e) {
  flash($e->getMessage(), 'error');
} catch (PDOException $e) {
  error_log('[SPOTIVE] DB error: ' . $e->getMessage());
  flash('ただいまゲストとしてログインできません。時間をおいてお試しください。', 'error');
}

redirect('signin.php');
