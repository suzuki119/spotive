/**
 * travel.js
 * AWAY TRAVEL（pages/travel/travel.php）の処理。
 * チームや出発地を選び直したら、ボタンを押さなくても表示を切り替える。
 * JS が動かないときは「表示」「変更」ボタンで送れるので、ボタンは JS が動いたときだけ隠す。
 */

document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll("[data-auto-submit]").forEach((form) => {
    const button = form.querySelector("[data-auto-submit-button]");
    if (button) button.classList.add("is-hidden");

    form.querySelectorAll("select").forEach((select) => {
      select.addEventListener("change", () => form.submit());
    });
  });
});
