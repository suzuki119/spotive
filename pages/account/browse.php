<?php

/**
 * pages/account/browse.php
 * 「ログインせずに使う」の受け口。スタート画面・アカウント種別・ログイン画面のリンクから来る。
 *
 * アカウントは作らない。地図・一覧・検索・試合の詳細はログインなしで見られ、
 * お気に入りはこの端末のブラウザに保存される（js/common/favorite-store.js）。
 * あとでアカウントを作ると、ブラウザのお気に入りはアカウントに移る（pages/favorite/favorite-api.php の merge）。
 *
 * ここでは「選んだ」印の Cookie を残してホームへ移すだけ。DB は触らないので GET で受ける。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';

mark_browsing_visitor();
redirect(url('pages/home/home.php'));
