/**
 * main.js
 * 全ページで読み込む共通のエントリーポイント。
 * ページ固有の処理は js/pages/ 側に書く。
 *
 * ここは全ページで動くので、ある画面にしか無い要素を前提にしないこと。
 * 要素が無いページで落ちると、以降の共通処理がすべて止まる。
 */

document.addEventListener("DOMContentLoaded", () => {
  // 共通処理の初期化をここにまとめる

  // back（戻る）を押すと前のページに戻る。
  // ボタンが無いページ・複数あるページのどちらでも動くようにしておく
  document.querySelectorAll(".back").forEach((backButton) => {
    backButton.addEventListener("click", () => {
      history.back();
    });
  });
});

// 設定画面の「戻る」。ほかのページには無いので、あるときだけ動かす（無いと全ページでエラーになる）。
// 同じサイトの画面から来たときはその画面に戻り、URL を直接開いたときなどはリンク先（マイページ）へ移る
const settingBack = document.querySelector('.setting-back');

if (settingBack) {
  settingBack.addEventListener('click', (e) => {
    let fromSameSite = false;
    try {
      fromSameSite = document.referrer !== '' && new URL(document.referrer).origin === location.origin;
    } catch {
      fromSameSite = false;
    }
    if (fromSameSite && history.length > 1) {
      e.preventDefault();
      history.back();
    }
  });
}
