<?php

/**
 * lib/auth.php
 * ログイン状態と、信頼レベルによるアクセス制御。
 *
 * 前身プロジェクトは Bearer トークンを localStorage に持たせていたが、
 * SPOTIVE は PHP がページを組み立てるので PHP のセッションを使う。
 * auth_sessions テーブルには「どの端末からログインしているか」を残し、
 * ログアウト時に失効させる（セッション ID の平文は保存しない）。
 */

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/ratelimit.php';

const LEVEL_PROVISIONAL = 0;  // 仮登録（メール未確認）
const LEVEL_USER        = 1;  // Lv.1 一般ユーザー
const LEVEL_IDENTIFIED  = 2;  // Lv.2 本人確認済み
const LEVEL_ORGANIZER   = 3;  // Lv.3 主催者認証済み

/** ログイン中のユーザー行。未ログインなら null */
function current_user(): ?array
{
  static $user = null;
  static $resolved = false;

  if ($resolved) {
    return $user;
  }
  $resolved = true;

  session_boot();
  $userId = $_SESSION['user_id'] ?? null;
  $hash   = $_SESSION['session_hash'] ?? null;
  if (!is_int($userId) || !is_string($hash)) {
    return null;
  }

  // DB 側で失効させられたセッションは、その場でログアウトにする
  $row = db_one(
    'SELECT u.* FROM auth_sessions s
       JOIN users u ON u.id = s.user_id
      WHERE s.token_hash = :h
        AND s.user_id = :u
        AND s.revoked_at IS NULL
        AND s.expires_at > NOW()
        AND u.status = "active"
        AND u.deleted_at IS NULL',
    ['h' => $hash, 'u' => $userId]
  );
  if ($row === null) {
    session_forget();
    return null;
  }

  db_run('UPDATE auth_sessions SET last_used_at = NOW() WHERE token_hash = :h', ['h' => $hash]);

  $user = $row;
  return $user;
}

function is_logged_in(): bool
{
  return current_user() !== null;
}

/**
 * ログインが必要なページの先頭で呼ぶ。未ログインならログイン画面へ送る。
 * 戻ってこられるよう、元のページを next= で渡す。
 */
function require_login(): array
{
  $user = current_user();
  if ($user !== null) {
    return $user;
  }
  flash('ログインが必要です。', 'error');
  redirect(url('pages/account/login.php') . '?next=' . urlencode(current_url()));
}

/** 信頼レベルが足りなければ、次にやることの案内へ送る */
function require_level(int $level): array
{
  $user = require_login();
  if ((int) $user['trust_level'] >= $level) {
    return $user;
  }

  flash(level_message($level), 'error');
  redirect(match ($level) {
    LEVEL_IDENTIFIED => url('pages/account/identity.php'),
    LEVEL_ORGANIZER  => url('pages/organizer/register.php'),
    default          => url('pages/setting/setting.php'),
  });
}

/**
 * 機能ごとの必要レベル（config/app.php の verification.require_level）でゲートする。
 * 運用しながらレベルを上げ下げするための入口。
 */
function require_action_level(string $action, int $default): array
{
  $level = config('verification.require_level.' . $action);
  return require_level($level === null ? $default : (int) $level);
}

/** 運営だけが開けるページで使う */
function require_role(string ...$roles): array
{
  $user = require_login();
  if (!in_array((string) $user['role'], $roles, true)) {
    flash('このページを開く権限がありません。', 'error');
    redirect(url('index.php'));
  }
  return $user;
}

function level_label(int $level): string
{
  return match ($level) {
    LEVEL_USER       => 'Lv.1 一般ユーザー',
    LEVEL_IDENTIFIED => 'Lv.2 本人確認済み',
    LEVEL_ORGANIZER  => 'Lv.3 主催者認証済み',
    default          => 'Lv.0 仮登録',
  };
}

function level_message(int $level): string
{
  return match ($level) {
    LEVEL_USER       => 'メールアドレスの確認を完了してください。',
    LEVEL_IDENTIFIED => 'この操作には本人確認（Lv.2）が必要です。',
    LEVEL_ORGANIZER  => 'この操作には主催者認証（Lv.3）が必要です。',
    default          => 'この操作を行う権限がありません。',
  };
}

function hash_password(string $plain): string
{
  return password_hash($plain, PASSWORD_DEFAULT, ['cost' => 12]);
}

// ---------------------------------------------------------------------
// ログイン・ログアウト
// ---------------------------------------------------------------------

/**
 * ログインさせる。セッション ID を作り直してから（固定化攻撃を防ぐ）、
 * auth_sessions に「この端末のセッション」を 1 行残す。
 */
function login_user(int $userId): void
{
  session_boot();
  session_regenerate_id(true);

  $hash = hash('sha256', session_id());
  db_insert('auth_sessions', [
    'token_hash' => $hash,
    'user_id'    => $userId,
    'ip'         => request_ip_binary(),
    'user_agent' => request_user_agent(),
    'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
  ]);

  $_SESSION['user_id']      = $userId;
  $_SESSION['session_hash'] = $hash;
}

function logout_user(): void
{
  session_boot();
  $hash = $_SESSION['session_hash'] ?? null;
  if (is_string($hash)) {
    db_run(
      'UPDATE auth_sessions SET revoked_at = NOW() WHERE token_hash = :h AND revoked_at IS NULL',
      ['h' => $hash]
    );
  }
  session_forget();
}

/** セッションの中身と cookie を消す */
function session_forget(): void
{
  session_boot();
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
  }
  session_destroy();
}

/** いま開いているページの URL（クエリ込み）。ログイン後に戻るために使う */
function current_url(): string
{
  return (string) ($_SERVER['REQUEST_URI'] ?? '/');
}

/**
 * next= で渡された戻り先。外部サイトへ飛ばされないよう、
 * 同じサイト内の相対パスだけを通す。
 */
function safe_next(?string $next, string $fallback): string
{
  if ($next === null || $next === '' || !str_starts_with($next, '/') || str_starts_with($next, '//')) {
    return $fallback;
  }
  return $next;
}
