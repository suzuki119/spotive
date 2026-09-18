<?php

/**
 * config/db.php
 * データベース接続（PDO）を返す。
 *
 * ローカル環境ごとにユーザー名・パスワードが違う場合は、
 * このファイルをコピーして各自の値に直してください。
 * 認証情報を書き換えたものは Git にコミットしないこと。
 */

declare(strict_types=1);

const DB_HOST = '127.0.0.1';
const DB_PORT = 3306;
const DB_NAME = 'sportmap';
const DB_USER = 'root';
const DB_PASS = '';

/**
 * PDO インスタンスを返す。接続できない場合は PDOException を投げる。
 */
function db(): PDO
{
  static $pdo = null;

  if ($pdo instanceof PDO) {
    return $pdo;
  }

  $dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
    DB_HOST,
    DB_PORT,
    DB_NAME
  );

  $pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
  ]);

  // schema.sql と同じタイムゾーンに揃える
  $pdo->exec("SET time_zone = '+09:00'");

  return $pdo;
}
