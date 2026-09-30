<?php

/**
 * pages/setting/account-delete.php
 * アカウントの削除（退会）。確認は 2 回。
 *
 *   1 回目 … 消えるものを読んで「元に戻せない」にチェックし、パスワードを入れる（action=check）
 *   2 回目 … 最後の確認画面で「削除する」を押す（?step=final → action=delete）
 *   完了   … ?done=1（ログアウト済みなので、ログインなしで開ける）
 *
 * 1 回目が通ったら、セッションに 10 分だけ使える合言葉を置く。2 回目はそれが無いと進めない
 * （パスワードを入れずに最後の画面を直接開いても、削除できないようにするため）。
 * 削除の中身は lib/account-delete.php にまとめてある。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/account-delete.php';
require_once __DIR__ . '/../../lib/layout.php';

// -------------------------------------------------------------------
// 完了（ログアウト済み）
// -------------------------------------------------------------------
if (($_GET['done'] ?? '') === '1') {
  page_header('アカウントを削除しました', 'アカウントを削除しました');
  ?>
  <p class="form-page__lead">
    ご利用ありがとうございました。アカウントと登録内容を削除しました。<br />
    同じメールアドレスで、もう一度登録することもできます。
  </p>
  <p class="form-page__note"><a href="<?= h(url('index.php')) ?>">トップへ戻る</a></p>
  <?php
  page_footer();
  exit;
}

$user   = require_login();
$errors = [];
$error  = null;

/** 2 回目の確認に進んでよいか（1 回目が 10 分以内に通っているか） */
function account_delete_confirmed(int $userId, string $token = ''): bool
{
  $saved = $_SESSION['account_delete'] ?? null;
  if (!is_array($saved) || (int) ($saved['user_id'] ?? 0) !== $userId || (int) ($saved['expires'] ?? 0) < time()) {
    return false;
  }
  return $token === '' || hash_equals((string) $saved['token'], $token);
}

// -------------------------------------------------------------------
// 1 回目（パスワード）・2 回目（削除する）
// -------------------------------------------------------------------
if (is_post()) {
  try {
    csrf_verify();

    $blockers = account_delete_blockers($user);
    if ($blockers !== []) {
      throw new AppError('今はアカウントを削除できません。');
    }

    switch ((string) ($_POST['action'] ?? '')) {
      case 'check':
        if (($_POST['understand'] ?? '') !== '1') {
          throw new AppError('入力内容を確認してください。', ['understand' => '内容を確認して、チェックを入れてください。']);
        }
        account_delete_check_password($user, (string) ($_POST['password'] ?? ''));

        $_SESSION['account_delete'] = [
          'user_id' => (int) $user['id'],
          'token'   => bin2hex(random_bytes(16)),
          'expires' => time() + ACCOUNT_DELETE_CONFIRM_TTL,
        ];
        redirect('account-delete.php?step=final');

      case 'delete':
        if (!account_delete_confirmed((int) $user['id'], (string) ($_POST['token'] ?? ''))) {
          unset($_SESSION['account_delete']);
          flash('確認の時間が切れました。もう一度パスワードを入力してください。', 'error');
          redirect('account-delete.php');
        }
        account_delete($user);
        logout_user();   // セッションも消す（DB 側のセッションは account_delete() で無効化済み）
        redirect('account-delete.php?done=1');

      default:
        throw new AppError('操作が不正です。');
    }
  } catch (AppError $e) {
    $error  = $e->getMessage();
    $errors = $e->fields();
  } catch (PDOException $e) {
    error_log('[SPOTIVE] DB error: ' . $e->getMessage());
    $error = 'ただいま削除できません。時間をおいてお試しください。';
  }
}

$blockers = account_delete_blockers($user);
$isFinal  = ($_GET['step'] ?? '') === 'final' && $blockers === [] && account_delete_confirmed((int) $user['id']);

page_header('アカウントの削除', $isFinal ? '本当に削除しますか？' : 'アカウントの削除');
?>

<?php if ($error !== null) : ?>
  <p class="notice notice--error"><?= h($error) ?></p>
<?php endif; ?>

<?php if ($blockers !== []) : ?>

  <?php foreach ($blockers as $message) : ?>
    <p class="notice notice--error"><?= h($message) ?></p>
  <?php endforeach; ?>
  <p class="form-page__note"><a href="account-setting.php">アカウント設定へ戻る</a></p>

<?php elseif ($isFinal) : ?>

  <p class="notice notice--error">
    この操作は取り消せません。「削除する」を押すと、すぐにアカウントが削除されます。
  </p>

  <dl class="detail">
    <dt class="detail__label">削除するアカウント</dt>
    <dd class="detail__value">
      <?= h((string) $user['nickname']) ?>（<?= h((string) $user['email']) ?>）
    </dd>
  </dl>

  <form class="form" action="account-delete.php" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete" />
    <input type="hidden" name="token" value="<?= h((string) $_SESSION['account_delete']['token']) ?>" />
    <div class="form__actions">
      <button class="button button--danger" type="submit">削除する</button>
      <a class="button" href="account-setting.php">やめる</a>
    </div>
  </form>

<?php else : ?>

  <p class="form-page__lead">アカウントを削除すると、次のものが消え、元に戻せません。</p>
  <ul class="doc-list">
    <li class="doc-list__item">ログイン情報（メールアドレス・電話番号・パスワード）とニックネーム</li>
    <li class="doc-list__item">お気に入りのチーム、お知らせの既読、遠征プラン</li>
    <li class="doc-list__item">本人確認で登録した氏名・生年月日と、提出した書類</li>
    <li class="doc-list__item">主催者の資格（主催した過去の大会は「<?= h(ACCOUNT_DELETED_NICKNAME) ?>」の名前で残ります）</li>
  </ul>

  <form class="form" action="account-delete.php" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="check" />

    <div class="form__field form__field--check">
      <label class="form__check">
        <input type="checkbox" name="understand" value="1" required />
        <span>削除すると元に戻せないことを理解しました</span>
      </label>
      <?php field_error($errors, 'understand'); ?>
    </div>

    <div class="form__field">
      <label class="form__label" for="password">パスワード</label>
      <input class="form__input" type="password" id="password" name="password"
             autocomplete="current-password" required />
      <p class="form__hint">本人の操作か確かめるため、今のパスワードを入力してください。</p>
      <?php field_error($errors, 'password'); ?>
    </div>

    <div class="form__actions">
      <button class="button button--danger" type="submit">次へ（最後の確認）</button>
      <a class="button" href="account-setting.php">やめる</a>
    </div>
  </form>

<?php endif; ?>

<?php page_footer(); ?>
