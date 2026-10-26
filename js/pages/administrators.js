/**
 * administrators.js
 * 管理者ページ（pages/administrators/administrators.php）のメニュー。
 * スマホでは左上のボタンでメニューを開き、アイコンが × に変わる。
 * PC ではメニューを出したままにするので、ボタンは CSS で隠している。
 */

document.addEventListener("DOMContentLoaded", () => {
  const button = document.querySelector(".administrators__menu-btn");
  const nav = document.getElementById("administrators-nav");
  if (!button || !nav) return;

  const icon = button.querySelector(".administrators__menu-icon");
  const iconOf = (name) => icon.style.getPropertyValue("--icon").replace(/(hamburger|close)\.svg/, `${name}.svg`);

  function setOpen(open) {
    document.body.classList.toggle("is-menu-open", open);
    button.setAttribute("aria-expanded", open ? "true" : "false");
    button.setAttribute("aria-label", open ? button.dataset.labelClose : button.dataset.labelOpen);
    icon.style.setProperty("--icon", iconOf(open ? "close" : "hamburger"));
  }

  const isOpen = () => document.body.classList.contains("is-menu-open");

  button.addEventListener("click", () => setOpen(!isOpen()));

  // メニューの項目を選んだら閉じる（同じページの見出しへ移るため）
  nav.querySelectorAll("a").forEach((link) => {
    link.addEventListener("click", () => setOpen(false));
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && isOpen()) {
      setOpen(false);
      button.focus();
    }
  });
});
