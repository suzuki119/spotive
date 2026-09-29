<?php

/**
 * config/rakuten.example.php
 * 楽天ウェブサービス（楽天トラベル API）のキーの置き方の見本。
 *
 * このファイルを config/rakuten.local.php という名前でコピーし、
 * 楽天ウェブサービスの「アプリ情報」に出ている値を書く。
 * rakuten.local.php は .gitignore 済みでコミットされない。
 */

return [
  'application_id' => '',   // アプリID
  'access_key'     => '',   // アクセスキー
  'affiliate_id'   => '',   // アフィリエイトID（無くても動く。付けると予約時に紹介料が入る）
];
