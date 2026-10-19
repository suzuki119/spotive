<?php

/**
 * lib/guest.php
 * ゲスト（お試し用の共通アカウント）。
 *
 * ログイン画面の「ゲストとしてログイン」を押すと、pages/account/guest-login.php が
 * guest_login() を呼び、パスワード無しでゲストのアカウントにログインさせる。
 * パスワードを画面に埋めて自動入力する形にしないのは、ページのソースから誰でも読めてしまうため。
 *
 * ログインせずに使っている人がマイページ・主催者ページを開いたときは、guest_auto_login() で
 * 自動でゲストにログインさせる（ログインの画面は出さない）。
 *
 * ゲストは全員で 1 つのアカウントを使うので、退会・プロフィール変更・主催者登録などはさせない
 * （guest_forbid() を各画面の先頭で呼ぶ）。お気に入りなども、ゲスト全員で共有になる。
 * localhost で試すときは、config/app.local.php で guest.allow_register を true にすると、
 * 退会以外はゲストでもできるようになる。
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

/** ゲストにも登録・変更を許すか（localhost で試すとき。config/app.local.php で true にする） */
function guest_can_register(): bool
{
  return (bool) config('guest.allow_register', false);
}

/**
 * ログインが必要な画面の先頭（require_login() などより前）で呼ぶ。
 * ログインしていなければゲストにログインさせて、同じ画面を開き直す。
 * ゲストを受け付けていないときや失敗したときは何もしない（そのあとの require_login() がログイン画面へ送る）。
 */
function guest_auto_login(): void
{
  // 送信（POST）のときは勝手にログインさせない。開いたとき（GET）だけ
  if (is_post() || !guest_enabled() || current_user() !== null) {
    return;
  }

  try {
    guest_login();
  } catch (AppError $e) {
    return;
  } catch (PDOException $e) {
    error_log('[SPOTIVE] DB error: ' . $e->getMessage());
    return;
  }
  // current_user() は 1 回目の結果を覚えているので、開き直してログイン後の状態で表示する
  redirect(current_url());
}

/**
 * ゲストには使わせない画面の先頭で呼ぶ。ゲストなら案内を出して $backPath へ戻す。
 * $backPath はアプリのルートからのパス（例：'pages/setting/setting.php'）。
 * $allowLocal が true の画面は、guest_can_register() のとき（localhost で試すとき）だけゲストにも使わせる。
 */
function guest_forbid(array $user, string $message, string $backPath = 'pages/setting/setting.php', bool $allowLocal = false): void
{
  if (!is_guest($user) || ($allowLocal && guest_can_register())) {
    return;
  }
  flash($message . 'アカウントを作成するとご利用いただけます。', 'error');
  redirect(url($backPath));
}
