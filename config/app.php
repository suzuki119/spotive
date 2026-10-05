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

  // ゲスト（お試し用の共通アカウント）。ログイン画面の「ゲストとしてログイン」で入れる。
  // パスワードでは入らない（lib/guest.php がサーバー側でログインさせる）。
  // 無ければ最初に押されたときに作る。電話番号は持たない（db/migrate-phone-nullable.sql が必要）。
  // 本番で使わないときは enabled を false にする。
  'guest' => [
    'enabled'  => true,
    'email'    => 'guest@gmal.com',
    'nickname' => 'ゲスト',
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
  ],

  'verification' => [
    // 本人確認（Lv.2）はいったん外している。主催者申請はメール確認（Lv.1）だけで出せる
    'organizer_valid_days' => 365,        // 主催者認証の有効期間

    // 機能ごとの必要レベル。運用しながら上げ下げするための入口
    'require_level' => [
      'organizer_apply'   => 1,           // 主催者申請にはメール確認（Lv.1）が必要
      'create_tournament' => 1,           // 大会登録・編集はメール確認（Lv.1）で可。主催者認証の必須化は後で
    ],

    // true にすると、承認されるまで大会は公開されない。
    // デモ中は false：登録した時点で公開し、安全・許認可の入力欄、書類の添付、
    // 大会確認（Lv.4）の申請を画面から隠す（AGENTS.md「デモ版の扱い」参照）
    'tournament_review_required' => false,
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
