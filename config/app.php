<?php

/**
 * config/app.php
 * アプリ全体の設定。db() の接続情報は config/db.php にある。
 *
 * 認証情報（SMTP のパスワードなど）を書き換えたものはコミットしないこと。
 */

declare(strict_types=1);

return [
  'app' => [
    // メールに載せるリンクの土台。MAMP の URL に合わせて書き換える
    'base_url' => 'http://localhost:8888/spotive',
    'timezone' => 'Asia/Tokyo',
  ],

  'auth' => [
    'email_token_ttl'    => 60 * 60 * 24, // メール確認リンクの有効期間（24時間）
    'sms_code_ttl'       => 60 * 10,      // SMS 認証コードの有効期間（10分）
    'max_login_failures' => 5,            // 連続失敗でロックする回数
    'lock_minutes'       => 15,           // ロックする時間
  ],

  // 添付書類とメール送信ログの保存先。
  // 必ず公開ディレクトリ（MAMP の htdocs）の外に置くこと。
  // 既定は htdocs のひとつ上＝ ~/Documents/spotive-storage。
  // プロジェクトを別の場所に置いている人は、ここを絶対パスで書き換える。
  'storage' => [
    'path'         => dirname(__DIR__, 3) . '/spotive-storage',
    'encrypt_key'  => '',                 // base64 の 32 バイト。空なら暗号化しない（開発時のみ）
    'max_bytes'    => 8 * 1024 * 1024,
    'allowed_mime' => ['image/jpeg', 'image/png', 'image/heic', 'application/pdf'],
    // 本人確認書類の原本を持っておく日数
    'identity_retention_days' => 30,
  ],

  'verification' => [
    // Lv.2 の確認方法。'external_ekyc'（画像を持たない） / 'manual'（自前で目視審査）
    'identity_provider'    => 'manual',
    'identity_valid_days'  => 730,        // 本人確認の有効期間
    'organizer_valid_days' => 365,        // 主催者認証の有効期間

    // 機能ごとの必要レベル。運用しながら上げ下げするための入口
    'require_level' => [
      'organizer_apply'   => 2,           // 主催者申請には本人確認（Lv.2）が必要
      'create_tournament' => 3,           // 大会登録には主催者認証（Lv.3）が必要
    ],

    // true にすると、承認されるまで大会は公開されない
    'tournament_review_required' => true,
  ],

  'notify' => [
    // log  … 送信せず storage/notify.log に書き出す（既定。開発用）
    // smtp … 下の smtp 設定で実送信する
    // mail … PHP の mail() を使う（ローカルからはほぼ届かない）
    'mail_driver'    => 'log',
    'mail_from'      => 'no-reply@example.com',
    'mail_from_name' => 'SPOTIVE',
    'smtp' => [
      'host'       => 'smtp.gmail.com',
      'port'       => 587,
      'username'   => '',
      'password'   => '',
      'encryption' => 'tls',
    ],

    // SMS 認証は後回し。有効にするときは notifier.php の sms() と
    // account.php の recalc_trust_level() の Lv.1 条件をあわせて戻すこと
    'sms_driver' => 'log',
  ],
];
