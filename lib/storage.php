<?php

/**
 * lib/storage.php
 * 本人確認書類・会場予約確認書などの保存。
 * - 公開ディレクトリの外にランダムな名前で置く（URL から推測できない）
 * - encrypt_key があれば AES-256-GCM で暗号化して保存する
 * - purge_after を過ぎたものは storage_purge() で物理削除する
 */

declare(strict_types=1);

require_once __DIR__ . '/support.php';

/**
 * アップロードされたファイルを保存し、attachments に 1 行足す。
 *
 * @param array $file $_FILES['xxx'] の 1 要素
 * @return array{id:int,sha256:string}
 */
function storage_save(
  array $file,
  string $ownerType,
  int $ownerId,
  string $docType,
  int $uploadedBy,
  ?string $purgeAfter = null
): array {
  if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    throw new AppError('ファイルのアップロードに失敗しました。');
  }

  $max = (int) config('storage.max_bytes', 8388608);
  if ((int) $file['size'] > $max) {
    throw new AppError('ファイルサイズが大きすぎます（上限 ' . (int) ($max / 1048576) . 'MB）。');
  }

  $tmp = (string) $file['tmp_name'];
  if (!is_uploaded_file($tmp)) {
    throw new AppError('不正なアップロードです。');
  }

  // 拡張子ではなく中身で判定する
  $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '';
  if (!in_array($mime, (array) config('storage.allowed_mime', []), true)) {
    throw new AppError('対応していないファイル形式です（JPEG / PNG / PDF）。');
  }

  $bytes  = (string) file_get_contents($tmp);
  $sha    = hash('sha256', $bytes);
  $key    = storage_key();
  $stored = $key === null ? $bytes : storage_encrypt($bytes, $key);

  $relative = sprintf('%s/%s.bin', date('Y/m'), bin2hex(random_bytes(16)));
  $absolute = storage_root() . '/' . $relative;
  if (!is_dir(dirname($absolute)) && !@mkdir(dirname($absolute), 0770, true)) {
    throw new AppError('保存先を作成できませんでした。');
  }
  if (file_put_contents($absolute, $stored, LOCK_EX) === false) {
    throw new AppError('ファイルを保存できませんでした。');
  }
  @chmod($absolute, 0600);

  $id = db_insert('attachments', [
    'owner_type'    => $ownerType,
    'owner_id'      => $ownerId,
    'doc_type'      => $docType,
    'original_name' => mb_substr((string) $file['name'], 0, 255),
    'storage_path'  => $relative,
    'mime_type'     => $mime,
    'byte_size'     => (int) $file['size'],
    'sha256'        => $sha,
    'is_encrypted'  => $key === null ? 0 : 1,
    'uploaded_by'   => $uploadedBy,
    'purge_after'   => $purgeAfter,
  ]);

  return ['id' => $id, 'sha256' => $sha];
}

/** 審査担当者だけが呼ぶ。復号した中身を返す */
function storage_read(array $attachment): string
{
  $raw = @file_get_contents(storage_root() . '/' . $attachment['storage_path']);
  if ($raw === false) {
    throw new AppError('ファイルが見つかりません。');
  }
  if ((int) $attachment['is_encrypted'] === 0) {
    return $raw;
  }

  $key = storage_key();
  if ($key === null) {
    throw new AppError('復号キーが設定されていません。');
  }
  return storage_decrypt($raw, $key);
}

function storage_purge(int $attachmentId): void
{
  $a = db_one('SELECT * FROM attachments WHERE id = :id', ['id' => $attachmentId]);
  if ($a === null || $a['deleted_at'] !== null) {
    return;
  }
  @unlink(storage_root() . '/' . $a['storage_path']);
  db_run('UPDATE attachments SET deleted_at = NOW() WHERE id = :id', ['id' => $attachmentId]);
}

/** @return list<array<string,mixed>> 添付の一覧（中身は含まない） */
function storage_list(string $ownerType, int $ownerId): array
{
  return db_all(
    'SELECT id, doc_type, original_name, mime_type, byte_size, created_at
       FROM attachments
      WHERE owner_type = :t AND owner_id = :i AND deleted_at IS NULL
      ORDER BY id',
    ['t' => $ownerType, 'i' => $ownerId]
  );
}

function storage_root(): string
{
  $path = (string) config('storage.path');
  if (!is_dir($path) && !@mkdir($path, 0770, true)) {
    throw new AppError('storage.path を作成できません。config/app.php を確認してください。');
  }
  return rtrim($path, '/');
}

function storage_key(): ?string
{
  $k = (string) config('storage.encrypt_key', '');
  if ($k === '') {
    return null;
  }
  $bin = base64_decode($k, true);
  return ($bin === false || strlen($bin) !== 32) ? null : $bin;
}

function storage_encrypt(string $plain, string $key): string
{
  $iv  = random_bytes(12);
  $tag = '';
  $ct  = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
  if ($ct === false) {
    throw new AppError('暗号化に失敗しました。');
  }
  return 'GCM1' . $iv . $tag . $ct;
}

function storage_decrypt(string $blob, string $key): string
{
  if (!str_starts_with($blob, 'GCM1')) {
    throw new AppError('保存形式が不正です。');
  }
  $out = openssl_decrypt(substr($blob, 32), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($blob, 4, 12), substr($blob, 16, 16));
  if ($out === false) {
    throw new AppError('復号に失敗しました。');
  }
  return $out;
}
