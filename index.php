<?php

/**
 * index.php
 * 入口。ログインしているかどうかだけを見て、行き先を振り分ける。画面は出さない。
 *
 *   ログインしている   … ホーム（pages/home/home.php）
 *   ログインしていない … 新規登録（pages/account/account-type.php）
 *
 * 以前のトップページの中身は project/index-before-home.txt に控えてある
 * （home.php へ移し終えたら削除する）。
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';

$loggedIn = false;
try {
  $loggedIn = is_logged_in();
} catch (PDOException $e) {
  // DB に繋がらないときは、白画面にせず未ログインとして扱う
  error_log('[SPOTIVE] DB error: ' . $e->getMessage());
}

redirect($loggedIn ? 'pages/home/home.php' : 'pages/account/account-type.php');
