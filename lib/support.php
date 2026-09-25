<?php

/**
 * lib/support.php
 * どのページでも使う土台。設定の読み込み、出力エスケープ、
 * DB のよく使う問い合わせ、画面間の受け渡し（フラッシュ）、CSRF 対策。
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

/**
 * 業務エラー。画面はこれを捕まえて、メッセージと項目別エラーを表示する。
 * $fields のキーは入力欄の name と対応させる。
 */
class AppError extends RuntimeException
{
  /** @param array<string,string> $fields */
  public function __construct(string $message, private array $fields = [])
  {
    parent::__construct($message);
  }

  /** @return array<string,string> */
  public function fields(): array
  {
    return $this->fields;
  }
}

// ---------------------------------------------------------------------
// 設定
// ---------------------------------------------------------------------

/**
 * config/app.php の値を取り出す。'auth.lock_minutes' のようにドットでたどる。
 */
function config(string $path, mixed $default = null): mixed
{
  static $config = null;
  $config ??= require __DIR__ . '/../config/app.php';

  $value = $config;
  foreach (explode('.', $path) as $key) {
    if (!is_array($value) || !array_key_exists($key, $value)) {
      return $default;
    }
    $value = $value[$key];
  }
  return $value;
}

// ---------------------------------------------------------------------
// 出力
// ---------------------------------------------------------------------

/** HTML エスケープ。出力時は必ずこれを通す */
function h(?string $value): string
{
  return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** 任意入力。空文字は NULL として保存する */
function optional_text(array $in, string $key): ?string
{
  $v = trim((string) ($in[$key] ?? ''));
  return $v === '' ? null : $v;
}

/** 直前の入力値。入力し直しのときに欄を空にしないために使う */
function old(array $input, string $key, string $default = ''): string
{
  $v = $input[$key] ?? $default;
  return is_string($v) ? $v : $default;
}

/** ページの土台からの相対 URL。pages/ の中からは url('pages/map/map.php') と書く */
function url(string $path = ''): string
{
  return base_path() . '/' . ltrim($path, '/');
}

/** このアプリのルートへの相対パス（pages/xxx/yyy.php からなら ../..） */
function base_path(): string
{
  $root    = str_replace('\\', '/', dirname(__DIR__));
  $pageDir = str_replace('\\', '/', dirname((string) realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''))));

  // ルートから何階層下のページかを数え、その数だけ ../ で戻る
  $relative = trim(substr($pageDir, strlen($root)), '/');
  $depth    = $relative === '' ? 0 : substr_count($relative, '/') + 1;

  return $depth === 0 ? '.' : rtrim(str_repeat('../', $depth), '/');
}

// ブラウザが古い HTML を使い回すと、?v= の付いた新しい CSS / JS にたどり着けない。
// 毎回サーバーに確認させる（画像や CSS 自体は ?v= があるのでキャッシュされてよい）
if (!headers_sent()) {
  header('Cache-Control: no-cache, must-revalidate');
}

/**
 * CSS や JS の URL。ファイルを更新すると ?v= の値が変わるので、
 * ブラウザが古いキャッシュを使い続けることがなくなる
 * （AGENTS.md のチェックリスト「キャッシュで古い CSS が残ります」への対策）。
 */
function asset(string $path): string
{
  $file    = dirname(__DIR__) . '/' . ltrim($path, '/');
  $version = is_file($file) ? (string) filemtime($file) : '0';
  return url($path) . '?v=' . $version;
}

/** 指定の URL へ移動して終了する。POST の後は必ずこれでリダイレクトする */
function redirect(string $path): never
{
  header('Location: ' . $path);
  exit;
}

// ---------------------------------------------------------------------
// セッションとフラッシュメッセージ
// ---------------------------------------------------------------------

/** セッションを開始する。cookie は JavaScript から読めないようにする */
function session_boot(): void
{
  if (session_status() === PHP_SESSION_ACTIVE) {
    return;
  }
  session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',   // 他サイトからの POST では cookie を送らない
  ]);
  session_start();
}

/** 次に開くページへ 1 回だけ渡すメッセージ */
function flash(string $message, string $type = 'info'): void
{
  session_boot();
  $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** @return list<array{type:string,message:string}> 取り出したら消える */
function take_flash(): array
{
  session_boot();
  $list = $_SESSION['flash'] ?? [];
  unset($_SESSION['flash']);
  return $list;
}

// ---------------------------------------------------------------------
// CSRF 対策
// ---------------------------------------------------------------------

/**
 * フォームに埋める合言葉。他サイトに置かれた偽フォームからの POST を弾くため、
 * 中身を変える POST には必ず csrf_field() を入れ、受け取り側で csrf_verify() する。
 */
function csrf_token(): string
{
  session_boot();
  $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
  return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
  return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '" />';
}

function csrf_verify(): void
{
  session_boot();
  $sent  = (string) ($_POST['csrf_token'] ?? '');
  $known = (string) ($_SESSION['csrf_token'] ?? '');
  if ($known === '' || !hash_equals($known, $sent)) {
    throw new AppError('ページの有効期限が切れました。お手数ですが、もう一度お試しください。');
  }
}

/** POST で開かれたか */
function is_post(): bool
{
  return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

// ---------------------------------------------------------------------
// DB のよく使う問い合わせ
// 接続は config/db.php の db() だけを使う。変数は必ずプレースホルダで渡す
// ---------------------------------------------------------------------

/** @param array<string,mixed> $params */
function db_run(string $sql, array $params = []): PDOStatement
{
  $stmt = db()->prepare($sql);
  $stmt->execute($params);
  return $stmt;
}

/** @return array<string,mixed>|null 1 行。無ければ null */
function db_one(string $sql, array $params = []): ?array
{
  $row = db_run($sql, $params)->fetch();
  return $row === false ? null : $row;
}

/** @return list<array<string,mixed>> */
function db_all(string $sql, array $params = []): array
{
  return db_run($sql, $params)->fetchAll();
}

/** @param array<string,mixed> $data 列名 => 値。列名はコード内の固定値だけを渡すこと */
function db_insert(string $table, array $data): int
{
  $cols = array_keys($data);
  $sql  = sprintf(
    'INSERT INTO %s (%s) VALUES (%s)',
    $table,
    implode(', ', $cols),
    implode(', ', array_map(static fn(string $c): string => ':' . $c, $cols))
  );
  db_run($sql, $data);
  return (int) db()->lastInsertId();
}

/** @param array<string,mixed> $data */
function db_update(string $table, array $data, string $where, array $whereParams): int
{
  $sets   = implode(', ', array_map(static fn(string $c): string => "{$c} = :set_{$c}", array_keys($data)));
  $params = $whereParams;
  foreach ($data as $key => $value) {
    $params['set_' . $key] = $value;
  }
  return db_run("UPDATE {$table} SET {$sets} WHERE {$where}", $params)->rowCount();
}

/**
 * トランザクション。既に開始済みなら入れ子にせずそのまま実行する
 * （rate_limit_hit() のように単体でも張る処理から呼ばれるため）。
 */
function db_transaction(callable $fn): mixed
{
  $pdo = db();
  if ($pdo->inTransaction()) {
    return $fn($pdo);
  }

  $pdo->beginTransaction();
  try {
    $result = $fn($pdo);
    $pdo->commit();
    return $result;
  } catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
  }
}

// ---------------------------------------------------------------------
// リクエストの情報（監査ログ用）
// ---------------------------------------------------------------------

/** ip カラムは VARBINARY(16) なので、文字列ではなくバイト列で入れる */
function request_ip_binary(): ?string
{
  $bin = @inet_pton((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
  return ($bin === false || $bin === '') ? null : $bin;
}

function request_user_agent(): ?string
{
  $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
  return $ua === '' ? null : mb_substr($ua, 0, 255);
}
