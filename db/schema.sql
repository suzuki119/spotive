-- =====================================================================
-- 観戦マップ / 大会プラットフォーム  信頼レベル設計スキーマ
--   Lv.1 一般ユーザー      -> users.trust_level = 1
--   Lv.2 本人確認済み      -> users.trust_level = 2
--   Lv.3 主催者認証済み    -> users.trust_level = 3
--   Lv.4 大会確認済み      -> tournaments.verification_status = 'verified'
--        （Lv.4 はユーザーではなく「大会」に付くレベル）
-- MySQL 8.0 / MariaDB 10.5+ 想定
-- =====================================================================
SET NAMES utf8mb4;
SET time_zone = '+09:00';

CREATE DATABASE IF NOT EXISTS sportmap
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sportmap;

-- ---------------------------------------------------------------------
-- 規約・ポリシー（バージョン管理。「どの版に同意したか」を残すため）
-- ---------------------------------------------------------------------
CREATE TABLE policy_documents (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  kind          ENUM('terms','privacy') NOT NULL,
  version       VARCHAR(20)  NOT NULL,          -- 例: '1.0.0'
  title         VARCHAR(255) NOT NULL,
  body_url      VARCHAR(500) NOT NULL,
  body_sha256   CHAR(64)     NULL,              -- 本文の改ざん検知用
  published_at  DATETIME     NOT NULL,
  retired_at    DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_policy (kind, version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Lv.1 ユーザー本体
--   氏名・生年月日の「本名」は users には置かず identity_verifications 側に持つ
--   （users.birthdate は自己申告、identity_verifications.birthdate は確認済み）
-- ---------------------------------------------------------------------
CREATE TABLE users (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email              VARCHAR(255) NOT NULL,
  email_normalized   VARCHAR(255) NOT NULL,     -- 小文字化＋trim。重複判定用
  email_verified_at  DATETIME     NULL,
  phone_e164         VARCHAR(20)  NULL,         -- +819012345678 形式で保存。ゲストアカウント（テスト用）は NULL
  phone_verified_at  DATETIME     NULL,
  password_hash      VARCHAR(255) NOT NULL,     -- password_hash(PASSWORD_DEFAULT)
  nickname           VARCHAR(50)  NOT NULL,
  birthdate          DATE         NOT NULL,     -- 自己申告
  trust_level        TINYINT UNSIGNED NOT NULL DEFAULT 0,  -- 0=仮登録 1=一般 2=本人確認済 3=主催者
  status             ENUM('pending','active','suspended','deleted') NOT NULL DEFAULT 'pending',
  role               ENUM('user','reviewer','admin') NOT NULL DEFAULT 'user',
  failed_login_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until       DATETIME     NULL,
  last_login_at      DATETIME     NULL,
  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at         DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email_normalized),
  UNIQUE KEY uq_users_phone (phone_e164),        -- NULL は重複扱いにならないので、ゲストは何件あってもよい
  KEY idx_users_level (trust_level, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 同意履歴（いつ・どの版に・どのIPから同意したか）
CREATE TABLE user_agreements (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    BIGINT UNSIGNED NOT NULL,
  policy_id  INT UNSIGNED NOT NULL,
  agreed_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ip         VARBINARY(16) NULL,
  user_agent VARCHAR(255) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_policy (user_id, policy_id),
  KEY idx_agree_user (user_id),
  CONSTRAINT fk_agree_user   FOREIGN KEY (user_id)   REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_agree_policy FOREIGN KEY (policy_id) REFERENCES policy_documents(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- メール認証トークン（平文は保存せず SHA-256 のみ）
CREATE TABLE email_verifications (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      BIGINT UNSIGNED NULL,           -- メール先行登録の間はまだ users の行が無いので空
  purpose      ENUM('signup','email_change','password_reset') NOT NULL DEFAULT 'signup',
  target_email VARCHAR(255) NOT NULL,
  token_hash   CHAR(64) NOT NULL,
  expires_at   DATETIME NOT NULL,
  consumed_at  DATETIME NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_email_token (token_hash),
  KEY idx_email_user (user_id, purpose, consumed_at),
  CONSTRAINT fk_emailv_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SMS認証コード（6桁。ハッシュで保存し試行回数を制限）
CREATE TABLE phone_verifications (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id       BIGINT UNSIGNED NOT NULL,
  phone_e164    VARCHAR(20) NOT NULL,
  code_hash     CHAR(64) NOT NULL,
  attempt_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
  max_attempts  TINYINT UNSIGNED NOT NULL DEFAULT 5,
  expires_at    DATETIME NOT NULL,
  consumed_at   DATETIME NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_phone_user (user_id, consumed_at, expires_at),
  CONSTRAINT fk_phonev_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- APIセッション（Bearerトークン。平文は返すだけでDBにはハッシュ）
CREATE TABLE auth_sessions (
  token_hash   CHAR(64) NOT NULL,
  user_id      BIGINT UNSIGNED NOT NULL,
  ip           VARBINARY(16) NULL,
  user_agent   VARCHAR(255) NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at   DATETIME NOT NULL,
  revoked_at   DATETIME NULL,
  PRIMARY KEY (token_hash),
  KEY idx_sess_user (user_id, revoked_at),
  CONSTRAINT fk_sess_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 汎用レート制限（登録・SMS送信・ログイン試行など）
CREATE TABLE rate_limits (
  bucket        VARCHAR(191) NOT NULL,          -- 例: 'sms:user:12', 'login:ip:1.2.3.4'
  window_start  DATETIME NOT NULL,
  counter       INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (bucket)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 添付書類（本人確認／主催者申請／大会確認 で共用）
--   storage_path は必ず公開ディレクトリ外。原本は暗号化して保存する。
-- ---------------------------------------------------------------------
CREATE TABLE attachments (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  owner_type   ENUM('identity','organizer_application','tournament') NOT NULL,
  owner_id     BIGINT UNSIGNED NOT NULL,
  doc_type     VARCHAR(50) NOT NULL,            -- id_front / id_back / selfie / venue_permit / insurance / permit ...
  original_name VARCHAR(255) NOT NULL,
  storage_path VARCHAR(500) NOT NULL,           -- 例: storage/2026/09/ab12....bin
  mime_type    VARCHAR(100) NOT NULL,
  byte_size    INT UNSIGNED NOT NULL,
  sha256       CHAR(64) NOT NULL,
  is_encrypted TINYINT(1) NOT NULL DEFAULT 1,
  uploaded_by  BIGINT UNSIGNED NOT NULL,
  purge_after  DATETIME NULL,                   -- 保存期限（過ぎたらバッチで物理削除）
  deleted_at   DATETIME NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_att_owner (owner_type, owner_id, deleted_at),
  KEY idx_att_purge (purge_after, deleted_at),
  CONSTRAINT fk_att_user FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Lv.2 本人確認
--   provider='external_ekyc' のときはアプリ側に画像を持たず、
--   外部サービスの参照IDと結果だけを保持する（推奨）。
--   provider='manual' のとき attachments に暗号化保存＋purge_after で自動削除。
-- ---------------------------------------------------------------------
CREATE TABLE identity_verifications (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id            BIGINT UNSIGNED NOT NULL,
  provider           ENUM('external_ekyc','manual') NOT NULL DEFAULT 'external_ekyc',
  provider_reference VARCHAR(191) NULL,         -- 外部サービスの applicant_id / session_id
  legal_name         VARCHAR(100) NULL,         -- 確認済みの氏名
  legal_name_kana    VARCHAR(100) NULL,
  birthdate          DATE NULL,                 -- 確認済みの生年月日
  document_type      ENUM('drivers_license','my_number_card','residence_card','passport') NULL,
  liveness_checked   TINYINT(1) NOT NULL DEFAULT 0,  -- 顔写真（セルフィー）照合の有無
  status             ENUM('draft','submitted','in_review','more_info_required','approved','rejected','expired')
                     NOT NULL DEFAULT 'draft',
  result_payload     JSON NULL,                 -- プロバイダの生レスポンス（最小限）
  reject_reason      VARCHAR(500) NULL,
  submitted_at       DATETIME NULL,
  reviewed_at        DATETIME NULL,
  reviewed_by        BIGINT UNSIGNED NULL,
  expires_at         DATETIME NULL,             -- 再確認の期限（例: 2年）
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_idv_user (user_id, status),
  KEY idx_idv_queue (status, submitted_at),
  UNIQUE KEY uq_idv_provider_ref (provider, provider_reference),
  CONSTRAINT fk_idv_user     FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_idv_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Lv.3 主催者認証
-- ---------------------------------------------------------------------
-- 団体・法人
CREATE TABLE organizations (
  id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  owner_user_id        BIGINT UNSIGNED NOT NULL,   -- 代表者アカウント
  org_type             ENUM('group','school','corporation','association') NOT NULL,
  name                 VARCHAR(120) NOT NULL,      -- 団体名 / 法人名
  name_kana            VARCHAR(120) NULL,
  representative_name  VARCHAR(100) NOT NULL,      -- 団体の代表者名
  corporate_number     CHAR(13) NULL,              -- 法人番号（法人のみ）
  postal_code          VARCHAR(8)  NULL,
  prefecture           VARCHAR(20) NOT NULL,
  address_line         VARCHAR(255) NOT NULL,      -- 団体所在地
  contact_phone        VARCHAR(20) NOT NULL,
  contact_email        VARCHAR(255) NOT NULL,
  website_url          VARCHAR(500) NULL,
  sns_url              VARCHAR(500) NULL,
  activity_description TEXT NULL,              -- 活動内容
  status               ENUM('draft','verified','suspended') NOT NULL DEFAULT 'draft',
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_org_corpnum (corporate_number),
  KEY idx_org_owner (owner_user_id),
  CONSTRAINT fk_org_owner FOREIGN KEY (owner_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 主催者申請（個人 or 団体）
CREATE TABLE organizer_applications (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id             BIGINT UNSIGNED NOT NULL,
  organization_id     BIGINT UNSIGNED NULL,       -- 団体・法人として申請する場合
  applicant_type      ENUM('individual','group','school','corporation','association') NOT NULL,
  -- 連絡先（個人申請時の必須項目）
  contact_name        VARCHAR(100) NOT NULL,
  contact_phone       VARCHAR(20)  NOT NULL,
  contact_email       VARCHAR(255) NOT NULL,
  -- 主催予定の大会内容
  planned_title       VARCHAR(150) NULL,
  planned_sport       VARCHAR(50)  NULL,
  planned_summary     TEXT NULL,
  planned_venue       VARCHAR(255) NULL,          -- 開催予定場所
  planned_date        DATE NULL,                  -- 開催予定日
  planned_fee_yen     INT UNSIGNED NOT NULL DEFAULT 0,   -- 参加費
  planned_participants SMALLINT UNSIGNED NULL,          -- 想定参加人数
  tournament_rules    TEXT NULL,                  -- 大会規約
  cancellation_policy TEXT NULL,                  -- キャンセル条件
  refund_policy       TEXT NULL,                  -- 返金条件
  status              ENUM('draft','submitted','in_review','more_info_required','approved','rejected','withdrawn')
                      NOT NULL DEFAULT 'draft',
  review_note         VARCHAR(1000) NULL,
  reviewed_by         BIGINT UNSIGNED NULL,
  reviewed_at         DATETIME NULL,
  submitted_at        DATETIME NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_oapp_user (user_id, status),
  KEY idx_oapp_queue (status, submitted_at),
  CONSTRAINT fk_oapp_user     FOREIGN KEY (user_id)         REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_oapp_org      FOREIGN KEY (organization_id) REFERENCES organizations(id),
  CONSTRAINT fk_oapp_reviewer FOREIGN KEY (reviewed_by)     REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 過去の大会・活動実績（申請 or 団体に紐づく）
CREATE TABLE organizer_past_events (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  application_id  BIGINT UNSIGNED NULL,
  organization_id BIGINT UNSIGNED NULL,
  title           VARCHAR(150) NOT NULL,
  held_on         DATE NOT NULL,
  venue           VARCHAR(255) NULL,
  participants    SMALLINT UNSIGNED NULL,
  reference_url   VARCHAR(500) NULL,
  note            VARCHAR(500) NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_past_app (application_id),
  KEY idx_past_org (organization_id),
  CONSTRAINT fk_past_app FOREIGN KEY (application_id)  REFERENCES organizer_applications(id) ON DELETE CASCADE,
  CONSTRAINT fk_past_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 認証済み主催者（承認の結果できる「資格」。有効期限と停止を持つ）
CREATE TABLE organizer_profiles (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id         BIGINT UNSIGNED NOT NULL,
  organization_id BIGINT UNSIGNED NULL,
  application_id  BIGINT UNSIGNED NOT NULL,
  display_name    VARCHAR(120) NOT NULL,          -- 公開される主催者名
  verified_at     DATETIME NOT NULL,
  verified_until  DATETIME NULL,                  -- 例: 1年後。過ぎたら再審査
  status          ENUM('active','suspended','revoked') NOT NULL DEFAULT 'active',
  suspend_reason  VARCHAR(500) NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_oprof_user (user_id),
  CONSTRAINT fk_oprof_user FOREIGN KEY (user_id)        REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_oprof_org  FOREIGN KEY (organization_id) REFERENCES organizations(id),
  CONSTRAINT fk_oprof_app  FOREIGN KEY (application_id)  REFERENCES organizer_applications(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Lv.4 大会そのものの確認
-- ---------------------------------------------------------------------
CREATE TABLE tournaments (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  organizer_user_id  BIGINT UNSIGNED NOT NULL,
  organization_id    BIGINT UNSIGNED NULL,
  title              VARCHAR(150) NOT NULL,      -- 大会名
  sport              VARCHAR(50)  NOT NULL,      -- 競技種目
  description        TEXT NULL,
  starts_at          DATETIME NOT NULL,          -- 開催日時
  ends_at            DATETIME NOT NULL,
  entry_opens_at     DATETIME NULL,              -- 募集期間 開始
  entry_closes_at    DATETIME NULL,              -- 募集期間 終了
  -- 開催場所
  venue_name         VARCHAR(150) NOT NULL,
  venue_postal_code  VARCHAR(8) NULL,
  venue_prefecture   VARCHAR(20) NOT NULL,
  venue_address      VARCHAR(255) NOT NULL,
  venue_lat          DECIMAL(9,6) NULL,
  venue_lng          DECIMAL(9,6) NULL,
  is_indoor          TINYINT(1) NOT NULL DEFAULT 0,
  -- 参加条件
  eligibility        MEDIUMTEXT NOT NULL,        -- 参加条件（年齢・性別・レベル等）。文字数の上限なし
  entry_fee_yen      INT UNSIGNED NOT NULL DEFAULT 0,
  capacity           SMALLINT UNSIGNED NOT NULL, -- 定員
  rules              MEDIUMTEXT NOT NULL,        -- 大会ルール。文字数の上限なし
  -- 安全・許認可まわり（Lv.4の確認対象）
  venue_permission_status ENUM('unknown','reserved','permitted','not_required') NOT NULL DEFAULT 'unknown',
  venue_permission_ref    VARCHAR(191) NULL,     -- 予約番号・許可証番号
  permits_required   TINYINT(1) NOT NULL DEFAULT 0,  -- 道路使用許可・消防届出などの要否
  permits_note       VARCHAR(500) NULL,
  insurance_status   ENUM('none','applied','insured') NOT NULL DEFAULT 'none',
  insurance_provider VARCHAR(120) NULL,
  insurance_policy_no VARCHAR(100) NULL,
  emergency_contact_name  VARCHAR(100) NOT NULL, -- 緊急時の連絡先
  emergency_contact_phone VARCHAR(20)  NOT NULL,
  -- 状態
  status              ENUM('draft','submitted','published','cancelled','finished') NOT NULL DEFAULT 'draft',
  verification_status ENUM('unverified','submitted','in_review','more_info_required','verified','rejected')
                      NOT NULL DEFAULT 'unverified',
  verified_at        DATETIME NULL,
  verified_by        BIGINT UNSIGNED NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tour_organizer (organizer_user_id, status),
  KEY idx_tour_search (status, verification_status, starts_at),
  KEY idx_tour_geo (venue_lat, venue_lng),
  CONSTRAINT fk_tour_user FOREIGN KEY (organizer_user_id) REFERENCES users(id),
  CONSTRAINT fk_tour_org  FOREIGN KEY (organization_id)   REFERENCES organizations(id),
  CONSTRAINT fk_tour_verifier FOREIGN KEY (verified_by)   REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 大会確認の申請＝審査1件ぶんの履歴（再申請すると行が増える）
CREATE TABLE tournament_verifications (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tournament_id BIGINT UNSIGNED NOT NULL,
  submitted_by  BIGINT UNSIGNED NOT NULL,
  status        ENUM('submitted','in_review','more_info_required','approved','rejected') NOT NULL DEFAULT 'submitted',
  snapshot      JSON NOT NULL,                 -- 申請時点の大会内容を凍結して保存
  review_note   VARCHAR(1000) NULL,
  reviewed_by   BIGINT UNSIGNED NULL,
  reviewed_at   DATETIME NULL,
  submitted_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tv_tour (tournament_id, status),
  KEY idx_tv_queue (status, submitted_at),
  CONSTRAINT fk_tv_tour     FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
  CONSTRAINT fk_tv_user     FOREIGN KEY (submitted_by)  REFERENCES users(id),
  CONSTRAINT fk_tv_reviewer FOREIGN KEY (reviewed_by)   REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- チェックリスト（大会名・日時・場所…会場利用許可・保険・緊急連絡先 の1項目=1行）
CREATE TABLE tournament_verification_checks (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  verification_id BIGINT UNSIGNED NOT NULL,
  item_key        VARCHAR(40) NOT NULL,        -- PHP側 TournamentService::CHECK_ITEMS のキー
  result          ENUM('pending','ok','ng','na') NOT NULL DEFAULT 'pending',
  note            VARCHAR(500) NULL,
  checked_by      BIGINT UNSIGNED NULL,
  checked_at      DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_check_item (verification_id, item_key),
  CONSTRAINT fk_check_ver  FOREIGN KEY (verification_id) REFERENCES tournament_verifications(id) ON DELETE CASCADE,
  CONSTRAINT fk_check_user FOREIGN KEY (checked_by)      REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 監査ログ（誰がどの審査をどう動かしたか。承認まわりは必ず残す）
-- ---------------------------------------------------------------------
CREATE TABLE audit_logs (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_user_id  BIGINT UNSIGNED NULL,
  action         VARCHAR(60) NOT NULL,          -- identity.approve / organizer.reject / tournament.verify ...
  target_type    VARCHAR(40) NOT NULL,
  target_id      BIGINT UNSIGNED NULL,
  detail         JSON NULL,
  ip             VARBINARY(16) NULL,
  user_agent     VARCHAR(255) NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_target (target_type, target_id, created_at),
  KEY idx_audit_actor (actor_user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- お気に入りのチーム（企画書の機能 4）
-- ログイン中のユーザーだけがここに保存する。未ログインのあいだはブラウザ（localStorage）。
-- チームのテーブルがまだ無いので、team_id は data/teams.json の id（team-001）をそのまま持つ。
-- チームのテーブルができたら、外部キーに置き換える。
-- ---------------------------------------------------------------------
CREATE TABLE favorite_teams (
  user_id     BIGINT UNSIGNED NOT NULL,
  team_id     VARCHAR(32) NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, team_id),
  CONSTRAINT fk_fav_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- お知らせ（運営からのお知らせ ＋ お気に入りチームのお知らせ）
--   audience = 'all'  … 全員に出す（運営からのお知らせ）
--   audience = 'team' … team_id をお気に入りにしている人に出す
-- チームのテーブルがまだ無いので、team_id は favorite_teams と同じく
-- data/teams.json の id（team-001）をそのまま持つ。
-- 送り主の名前（「FE名古屋」「SPOTIVE 運営」）は、送った時点の表記を sender_name に残す。
-- 画面は notifications を直接見ず、下の v_public_notifications から取ること。
-- CHECK 制約は MySQL 8.0.16 以降 / MariaDB 10.2 以降で効く。
-- ---------------------------------------------------------------------
CREATE TABLE notifications (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  audience       ENUM('all','team') NOT NULL DEFAULT 'all',
  team_id        VARCHAR(32)  NULL,                 -- audience = 'team' のときだけ入れる
  category       ENUM('info','change','cancel') NOT NULL DEFAULT 'info',
                                                    -- info=お知らせ change=日時・会場の変更 cancel=中止
  sender_name    VARCHAR(120) NOT NULL,             -- 一覧に出す送り主
  title          VARCHAR(150) NOT NULL,             -- 一覧に出す 1 行
  body           TEXT NULL,                         -- 開いたときの本文
  match_ref      VARCHAR(32)  NULL,                 -- 関係する試合（data/matches.json の id。試合のテーブルができたら外部キーに）
  tournament_id  BIGINT UNSIGNED NULL,              -- 関係する大会
  status         ENUM('draft','published','withdrawn') NOT NULL DEFAULT 'draft',
  published_at   DATETIME NULL,                     -- 公開日時。未来の日時にすると予約になる
  expires_at     DATETIME NULL,                     -- 掲載終了。NULL なら出し続ける
  created_by     BIGINT UNSIGNED NOT NULL,          -- 作成した運営のユーザー
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notif_public (status, published_at),
  KEY idx_notif_team (audience, team_id, published_at),
  CONSTRAINT chk_notif_audience CHECK (
    (audience = 'all'  AND team_id IS NULL) OR
    (audience = 'team' AND team_id IS NOT NULL)
  ),
  CONSTRAINT chk_notif_period CHECK (expires_at IS NULL OR published_at IS NULL OR expires_at > published_at),
  CONSTRAINT fk_notif_tour FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE SET NULL,
  CONSTRAINT fk_notif_user FOREIGN KEY (created_by)    REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 既読（ログイン中のユーザーだけ。未ログインのあいだはブラウザの localStorage）
-- 行がある＝読んだ。未読の数は「見えるお知らせ − この表にある分」で出す。
-- 登録より前のお知らせも未読として出る（テスト段階なのでそのままにしている）。
CREATE TABLE notification_reads (
  user_id          BIGINT UNSIGNED NOT NULL,
  notification_id  BIGINT UNSIGNED NOT NULL,
  read_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, notification_id),
  KEY idx_nread_notif (notification_id),
  CONSTRAINT fk_nread_user  FOREIGN KEY (user_id)         REFERENCES users(id)         ON DELETE CASCADE,
  CONSTRAINT fk_nread_notif FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 遠征プラン（主要機能 5「遠征サポート」。設計は docs/away-travel.md）
-- 試合・チーム・ホテルはまだ DB に無いので、favorite_teams と同じく
-- data/*.json の id（team-001 / match-001 / hotel-001 / area-23）をそのまま持つ。
-- JSON が変わっても表示が変わらないよう、名前・時刻・金額は作成時点の値を items に写す。
-- 「到着」「会場へ」「チェックアウト」の行は保存せず、表示するときに items から作る。
-- ---------------------------------------------------------------------
CREATE TABLE travel_plans (
  id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id              BIGINT UNSIGNED NOT NULL,
  team_id              VARCHAR(32)  NOT NULL,           -- 応援するチーム
  title                VARCHAR(150) NOT NULL,
  depart_area_id       VARCHAR(32)  NOT NULL,           -- 出発地
  start_date           DATE         NOT NULL,
  end_date             DATE         NOT NULL,
  estimated_total_yen  INT UNSIGNED NOT NULL DEFAULT 0, -- 作成時点の費用の目安（1 人あたり）
  created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_travel_user (user_id, start_date),
  CONSTRAINT fk_travel_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE travel_plan_items (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  plan_id         BIGINT UNSIGNED NOT NULL,
  item_type       ENUM('transport','match','hotel') NOT NULL,
  ref_id          VARCHAR(32)  NULL,                    -- match-001 / tournament-5 / hotel-001。交通は NULL
  title           VARCHAR(150) NOT NULL,
  from_label      VARCHAR(50)  NULL,                    -- 交通の出発地。試合は会場名
  to_label        VARCHAR(50)  NULL,                    -- 交通の到着地
  starts_at       DATETIME     NOT NULL,                -- 交通は発、試合は開始、ホテルはチェックイン
  ends_at         DATETIME     NULL,                    -- 交通は着、試合は終了予定、ホテルはチェックアウト
  price_yen       INT UNSIGNED NULL,                    -- 交通は片道、試合はチケットの最低価格、ホテルは 1 泊
  booking_status  ENUM('none','booked') NOT NULL DEFAULT 'none',  -- ユーザーが自分で付ける「購入済み」の印
  url             VARCHAR(500) NULL,                    -- 予約・販売サイト
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_travel_item_plan (plan_id, starts_at),
  CONSTRAINT fk_travel_item_plan FOREIGN KEY (plan_id) REFERENCES travel_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 公開用ビュー：一覧・地図に出す大会（確認済みバッジ付き）
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW v_public_tournaments AS
SELECT
  t.id, t.title, t.sport, t.starts_at, t.ends_at,
  t.venue_name, t.venue_prefecture, t.venue_lat, t.venue_lng, t.is_indoor,
  t.entry_fee_yen, t.capacity, t.entry_opens_at, t.entry_closes_at,
  (t.verification_status = 'verified') AS is_verified,
  COALESCE(op.display_name, u.nickname) AS organizer_name,
  u.trust_level AS organizer_trust_level
FROM tournaments t
JOIN users u ON u.id = t.organizer_user_id
LEFT JOIN organizer_profiles op ON op.user_id = t.organizer_user_id AND op.status = 'active'
WHERE t.status = 'published';

-- ---------------------------------------------------------------------
-- 公開用ビュー：いま画面に出してよいお知らせ
--   下書き・取り消し・公開前（予約中）・掲載終了は出さない
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW v_public_notifications AS
SELECT
  n.id, n.audience, n.team_id, n.category, n.sender_name,
  n.title, n.body, n.match_ref, n.tournament_id, n.published_at
FROM notifications n
WHERE n.status = 'published'
  AND n.published_at IS NOT NULL
  AND n.published_at <= NOW()
  AND (n.expires_at IS NULL OR n.expires_at > NOW());
