-- db/migrate-phone-nullable.sql
-- すでに作ってある DB の users.phone_e164 を、NULL でもよい形に変える（2026-09-30）。
-- テスト用のゲストアカウントは電話番号を持たないため。
-- 新しく DB を作る場合は db/schema.sql（schema-lolipop.sql）に反映済みなので、これは不要。
--
-- UNIQUE KEY uq_users_phone はそのまま残す（NULL は重複扱いにならない）。
-- 通常の新規登録では、アプリ側（lib/account.php）で今までどおり電話番号を必須にしている。

ALTER TABLE users
  MODIFY phone_e164 VARCHAR(20) NULL COMMENT '+819012345678 形式。ゲストアカウント（テスト用）は NULL';
