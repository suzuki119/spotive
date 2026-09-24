/**
 * main.js
 * 全ページで読み込む共通のエントリーポイント。
 * ページ固有の処理は js/pages/ 側に書く。
 */

document.addEventListener("DOMContentLoaded", () => {
  // 共通処理の初期化をここにまとめる

  // 　　back（戻る）をクリックすると前のページに戻る
  const backButton = document.querySelector(".back");
  backButton.addEventListener("click", () => {
    history.back();
  });
});
