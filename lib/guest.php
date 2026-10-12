<?php

/**
 * lib/guest.php
 * ゲスト（お試し用の共通アカウント）。
 *
 * ログイン画面の「ゲストとしてログイン」を押すと、pages/account/guest-login.php が
 * guest_login() を呼び、パスワード無しでゲストのアカウントにログインさせる。
 * パスワードを画面に埋めて自動入力する形にしないのは、ページのソースから誰でも読めてしまうため。
 *
 * ゲストは全員で 1 つのアカウントを使うので、退会・プロフィール変更・主催者登録などはさせない
 * （guest_forbid() を各画面の先頭で呼ぶ）。お気に入りなども、ゲスト全員で共有になる。
 * 設定は config/app.php の guest。
 */

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/ratelimit.php';

/** ゲストのログインを受け付けるか */
function guest_enabled(): bool
{
  return (bool) config('guest.enabled', false) && guest_email() !== '';
}

/** ゲストのメールアドレス（小文字・前後の空白なし） */
function guest_email(): string
{
  return strtolower(trim((string) config('guest.email', '')));
}

/** このユーザーがゲストか */
function is_guest(?array $user): bool
{
  return $user !== null
    && guest_email() !== ''
    && (string) ($user['email_normalized'] ?? '') === guest_email();
}

/**
 * ゲストとしてログインさせる。アカウントが無ければ作る。
 *
 * @throws AppError 受け付けていない・停止中・押しすぎのとき
 */
function guest_login(): void
{
  if (!guest_enabled()) {
    throw new AppError('いまはゲストとしてログインできません。');
  }

  rate_limit_hit('guest_login:ip:' . rate_limit_ip(), 30, 3600);

  $email = guest_email();
  $user  = db_one('SELECT id, status FROM users WHERE email_normalized = :e', ['e' => $email]);

  if ($user === null) {
    // 初めて押されたとき。パスワードは誰も知らない値にしておく（パスワードでは入らせない）
    $id = db_insert('users', [
      'email'             => $email,
      'email_normalized' => $email,
      'email_verified_at' => date('Y-m-d H:i:s'),
      'phone_e164'        => null,
      'password_hash'     => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
      'nickname'          => (string) config('guest.nickname', 'ゲスト'),
      'birthdate'         => '2000-01-01',   // 自己申告の欄。ゲストには意味が無いので固定
      'trust_level'       => LEVEL_USER,     // メール確認済みの一般ユーザー（Lv.1）と同じ
      'status'            => 'active',
    ]);
    audit_log($id, 'guest.created', 'user', $id);
    $user = ['id' => $id, 'status' => 'active'];
  }

  if ($user['status'] !== 'active') {
    throw new AppError('いまはゲストとしてログインできません。');
  }

  login_user((int) $user['id']);
  audit_log((int) $user['id'], 'guest.login', 'user', (int) $user['id']);
}

/**
 * ゲストには使わせない画面の先頭で呼ぶ。ゲストなら案内を出して $backPath へ戻す。
 * $backPath はアプリのルートからのパス（例：'pages/setting/setting.php'）。
 */
function guest_forbid(array $user, string $message, string $backPath = 'pages/setting/setting.php'): void
{
  if (!is_guest($user)) {
    return;
  }
  flash($message . 'アカウントを作成するとご利用いただけます。', 'error');
  redirect(url($backPath));
}
