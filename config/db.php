<?php

/**
 * config/db.php
 * データベース接続（PDO）を返す。
 *
 * ローカル環境ごとに接続先が違う場合は、このファイルを直さず
 * config/db.local.php を置いて、違う値だけを上書きしてください。
 * （db.local.php は .gitignore に入れてあり、コミットされません）
 *
 *   <?php
 *   return ['port' => 8889, 'pass' => 'root'];   // 例: MAMP
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

  // 環境ごとの違いは config/db.local.php で上書きする
  $local = is_file(__DIR__ . '/db.local.php') ? (array) require __DIR__ . '/db.local.php' : [];
  $conf  = $local + [
    'host' => DB_HOST,
    'port' => DB_PORT,
    'name' => DB_NAME,
    'user' => DB_USER,
    'pass' => DB_PASS,
  ];

  $dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
    $conf['host'],
    $conf['port'],
    $conf['name']
  );

  $pdo = new PDO($dsn, $conf['user'], $conf['pass'], [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
  ]);

  // schema.sql と同じタイムゾーンに揃える
  $pdo->exec("SET time_zone = '+09:00'");

  return $pdo;
}
