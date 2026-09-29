/**
 * header.js
 * 共通ヘッダー（pages/app-header.php）の処理。app-header.php が自分で読み込む。
 *   ・左の「戻る」と右の ✕ は、同じサイトの画面から来たときはその画面に戻す。
 *     URL を直接開いたときなどは、リンク先（ホームなど）へそのまま移る
 */

document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll("[data-header-back]").forEach((back) => back.addEventListener("click", (e) => {
    let fromSameSite = false;
    try {
      fromSameSite = document.referrer !== "" && new URL(document.referrer).origin === location.origin;
    } catch {
      fromSameSite = false;
    }
    if (fromSameSite && history.length > 1) {
      e.preventDefault();
      history.back();
    }
  }));
});
