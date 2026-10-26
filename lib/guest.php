<?php

/**
 * lib/guest.php
 * ゲスト（お試し用の共通アカウント）。
 *
 * ログインせずに使っている人がマイページ・主催者ページを開いたときは、guest_auto_login() で
 * 自動でゲストにログインさせる（ログインの画面は出さない）。パスワードでは入らない。
 *
 * ゲストは全員で 1 つのアカウントを使うので、退会・プロフィール変更・主催者登録・遠征プランの削除などはさせない
 * （guest_forbid() を各画面の先頭で呼ぶ）。遠征プランはゲスト全員で共有になる。
 * お気に入りとお知らせの既読は、ほかの人と混ざらないよう、未ログインと同じくブラウザに保存する
 * （current_member()。lib/favorite.php・pages/notification/）。
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
 * ログイン中のユーザー。ゲストなら null（未ログインと同じ扱い）。
 * お気に入りやお知らせの既読のように、ゲスト全員で混ざると困るものは、これで判定してブラウザに保存させる。
 */
function current_member(): ?array
{
  $user = current_user();
  return is_guest($user) ? null : $user;
}

/** IP ごとのゲストのログイン回数の上限（1 時間あたり）。教室など同じ IP から大勢が開いても足りるようにしておく */
const GUEST_LOGIN_LIMIT = 300;

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

  try {
    rate_limit_hit('guest_login:ip:' . rate_limit_ip(), GUEST_LOGIN_LIMIT, 3600);
  } catch (AppError $e) {
    throw new AppError('ただいまゲストでのご利用が混み合っています。時間をおいてお試しいただくか、アカウントを作成してください。');
  }

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
 * ゲストを受け付けていないときは何もしない（そのあとの require_login() がログイン画面へ送る）。
 * 失敗したとき（混み合っているときなど）は、理由をお知らせに残してからログイン画面へ送らせる。
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
    flash($e->getMessage(), 'error');
    return;
  } catch (PDOException $e) {
    error_log('[SPOTIVE] DB error: ' . $e->getMessage());
    flash('ただいまゲストとしてご利用いただけません。時間をおいてお試しください。', 'error');
    return;
  }
  // current_user() は 1 回目の結果を覚えているので、開き直してログイン後の状態で表示する
  redirect(current_url());
}

/**
 * ゲストには使わせない画面の先頭で呼ぶ。ゲストなら案内を出して $backPath へ戻す。
 * $backPath はアプリのルートからのパス（例：'pages/setting/mypage.php'）。
 * $allowLocal が true の画面は、guest_can_register() のとき（localhost で試すとき）だけゲストにも使わせる。
 */
function guest_forbid(array $user, string $message, string $backPath = 'pages/setting/mypage.php', bool $allowLocal = false): void
{
  if (!is_guest($user) || ($allowLocal && guest_can_register())) {
    return;
  }
  flash($message . 'アカウントを作成するとご利用いただけます。', 'error');
  redirect(url($backPath));
}
