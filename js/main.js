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
