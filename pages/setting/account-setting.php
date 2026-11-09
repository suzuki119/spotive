<?php

/**
 * pages/setting/account-setting.php
 * アカウント。プロフィールの変更・アカウント情報・ログイン中の端末・退会を 1 画面にまとめている
 * （以前の profile.php もここに入れた）。設定画面（setting.php）とマイページから来る。
 *
 * 変えられるのはニックネームと電話番号だけ。
 * メールアドレスの変更は本人のものか確かめ直す手順が必要なので、まだ作っていない。
 * パスワードの変更は password-setting.php で行う。
 * ゲスト（お試し用の共通アカウント）は見るだけで、変更はできない（localhost で試すときは除く）。
 * 退会（アカウントの削除）は account-delete.php で行う。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/account.php';
require_once __DIR__ . '/../../lib/guest.php';
require_once __DIR__ . '/../../lib/layout.php';

// ログインせずに使っている人は、ゲストにログインさせてから開く
guest_auto_login();
$user = require_login();

// guest_forbid() と同じ条件。ゲストにはフォームを出さず、送られてきても受け付けない
$canEdit = !is_guest($user) || guest_can_register();

$errors = [];
$error  = null;

if (is_post()) {
  try {
    csrf_verify();
    if (!$canEdit) {
      throw new AppError('ゲストのプロフィールは変更できません。アカウントを作成するとご利用いただけます。');
    }

    $input = [
      'nickname' => trim((string) ($_POST['nickname'] ?? '')),
      'phone'    => trim((string) ($_POST['phone'] ?? '')),
    ];

    (new Validator($input))
      ->required('nickname', 'ニックネーム')->length('nickname', 'ニックネーム', 1, 50)
      ->required('phone', '電話番号')->phone('phone')
      ->validate();

    $phone = Validator::normalizePhone($input['phone']);

    // 電話番号は 1 人 1 つ。ほかの人が使っていないか確かめる
    $taken = db_one(
      'SELECT id FROM users WHERE phone_e164 = :p AND id <> :id AND deleted_at IS NULL',
      ['p' => $phone, 'id' => $user['id']]
    );
    if ($taken !== null) {
      throw new AppError('この電話番号は既に使われています。', ['phone' => 'この電話番号は既に使われています。']);
    }

    // 電話番号を変えたら、確認済みの印は外す（別の番号になったため）
    $changedPhone = $phone !== $user['phone_e164'];

    db_update('users', [
      'nickname'          => $input['nickname'],
      'phone_e164'        => $phone,
      'phone_verified_at' => $changedPhone ? null : $user['phone_verified_at'],
    ], 'id = :id', ['id' => $user['id']]);

    audit_log((int) $user['id'], 'user.profile_updated', 'user', (int) $user['id']);

    flash('プロフィールを更新しました。', 'success');
    redirect('account-setting.php');
  } catch (AppError $e) {
    $error  = $e->getMessage();
    $errors = $e->fields();
  } catch (PDOException $e) {
    error_log('[SPOTIVE] DB error: ' . $e->getMessage());
    $error = 'ただいま更新できません。時間をおいてお試しください。';
  }
}

// 入力し直しのときは送られてきた値、それ以外は今の登録内容を出す
$form = is_post() ? $_POST : [
  'nickname' => $user['nickname'],
  'phone'    => $user['phone_e164'],
];

$sessions = 0;
try {
  $sessions = (int) db_one(
    'SELECT COUNT(*) AS c FROM auth_sessions
      WHERE user_id = :u AND revoked_at IS NULL AND expires_at > NOW()',
    ['u' => $user['id']]
  )['c'];
} catch (PDOException $e) {
  error_log('[SPOTIVE] DB error: ' . $e->getMessage());
}

setting_page_header('アカウント');
?>

        <?php if ($error !== null) : ?>
          <p class="notice notice--error"><?= h($error) ?></p>
        <?php endif; ?>

        <section>
          <h2>プロフィール</h2>

          <?php if ($canEdit) : ?>
            <form class="setting-form" action="account-setting.php" method="post">
              <?= csrf_field() ?>

              <div class="setting-form__field">
                <label class="setting-form__label" for="nickname">ニックネーム</label>
                <input
                  class="form__input"
                  type="text"
                  id="nickname"
                  name="nickname"
                  value="<?= h(old($form, 'nickname')) ?>"
                  maxlength="50"
                  required
                />
                <p class="setting-form__hint">画面に表示される名前です。</p>
                <?php field_error($errors, 'nickname'); ?>
              </div>

              <div class="setting-form__field">
                <label class="setting-form__label" for="phone">電話番号</label>
                <input
                  class="form__input"
                  type="tel"
                  id="phone"
                  name="phone"
                  value="<?= h(old($form, 'phone')) ?>"
                  placeholder="09012345678"
                  required
                />
                <?php if ($user['phone_e164'] !== null) : ?>
                  <p class="setting-form__hint"><?= $user['phone_verified_at'] !== null ? '確認済みです。' : 'まだ確認していません。' ?>変えると、もう一度確認が必要になります。</p>
                <?php endif; ?>
                <?php field_error($errors, 'phone'); ?>
              </div>

              <button class="setting-form__submit" type="submit">保存する</button>
            </form>
          <?php else : ?>
            <dl class="detail detail--striped">
              <div class="detail__row">
                <dt class="detail__label">ニックネーム</dt>
                <dd class="detail__value"><?= h((string) $user['nickname']) ?></dd>
              </div>
            </dl>
            <p class="setting-form__hint">ゲストのプロフィールは変更できません。アカウントを作成するとご利用いただけます。</p>
          <?php endif; ?>
        </section>

        <section>
          <h2>アカウント情報</h2>

          <dl class="detail detail--striped">
            <div class="detail__row">
              <dt class="detail__label">メールアドレス</dt>
              <dd class="detail__value">
                <?= h((string) $user['email']) ?>
                <?= $user['email_verified_at'] !== null ? '（確認済み）' : '（未確認）' ?>
              </dd>
            </div>

            <div class="detail__row">
              <dt class="detail__label">生年月日</dt>
              <dd class="detail__value"><?= h((string) $user['birthdate']) ?></dd>
            </div>

            <div class="detail__row">
              <dt class="detail__label">信頼レベル</dt>
              <dd class="detail__value"><?= h(level_label((int) $user['trust_level'])) ?></dd>
            </div>

            <div class="detail__row">
              <dt class="detail__label">最終ログイン</dt>
              <dd class="detail__value"><?= h(format_datetime((string) $user['last_login_at'])) ?></dd>
            </div>

            <div class="detail__row">
              <dt class="detail__label">ログイン中の端末</dt>
              <dd class="detail__value"><?= $sessions ?>件</dd>
            </div>
          </dl>
          <p class="setting-form__hint">メールアドレス・生年月日の変更はまだ作っていません。</p>
        </section>

        <!-- 退会。設定画面のログアウトと同じく、赤い枠と文字で区別する。ゲストは退会できないので出さない -->
        <?php if (!is_guest($user)) : ?>
          <section class="setting-danger">
            <h2>アカウントの削除</h2>
            <a class="setting-danger__link" href="account-delete.php">
              <span>アカウントを削除する</span>
              <img src="<?= h(url('images/icons/setting-next.svg')) ?>" alt="" width="11" height="18" />
            </a>
            <p class="setting-form__hint">削除すると、登録内容は元に戻せません。</p>
          </section>
        <?php endif; ?>

<?php setting_page_footer();
