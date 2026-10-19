<?php

/**
 * pages/guest-bar.php
 * ゲストでログインしている間、ヘッダーの下に出す帯（lib/guest.php）。
 * pages/app-header.php と lib/layout.php の page_header() が、ゲストのときだけ読み込む。
 *
 * ゲストは全員で 1 つのアカウントを使うので、お気に入りや遠征プランがほかの人と混ざることを伝え、
 * 自分のアカウントを作る画面へ案内する。
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/support.php';

?>
<aside class="guest-bar" aria-label="ゲストでの利用について">
  <p class="guest-bar__text">
    ゲストで利用中です。お気に入りや遠征プランは、ゲスト全員で共有されます。
  </p>
  <a class="guest-bar__link" href="<?= h(url('pages/account/account-type.php')) ?>">アカウントを作成</a>
</aside>
