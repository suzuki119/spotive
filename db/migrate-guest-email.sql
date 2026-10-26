-- db/migrate-guest-email.sql
-- すでに作ってあるゲストのアカウントを、新しいメールアドレスに移す（2026-10-26）。
-- config/app.php の guest.email を guest@gmal.com から guest@example.com に変えたため。
-- これを流さないと、次にゲストでログインしたときに別のゲストのアカウントが作られ、古い方が残る。
-- まだゲストのアカウントが無い DB では、何も変わらない。
--
-- あわせて、ゲストのお気に入りとお知らせの既読を消す。
-- ゲストのお気に入り・既読は、ほかの人と混ざらないようブラウザに保存する形に変えたので、
-- DB に残っている分は使わない（残すと、主催者ページのお気に入り登録数に混ざる）。

UPDATE users
   SET email = 'guest@example.com',
       email_normalized = 'guest@example.com'
 WHERE email_normalized = 'guest@gmal.com';

DELETE FROM favorite_teams
 WHERE user_id IN (SELECT id FROM (SELECT id FROM users WHERE email_normalized = 'guest@example.com') AS g);

DELETE FROM notification_reads
 WHERE user_id IN (SELECT id FROM (SELECT id FROM users WHERE email_normalized = 'guest@example.com') AS g);
