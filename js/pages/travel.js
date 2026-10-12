/**
 * travel.js
 * AWAY TRAVEL（pages/travel/travel.php）の処理。
 * チームや出発地を選び直したら、ボタンを押さなくても表示を切り替える。
 * JS が動かないときは「表示」「変更」ボタンで送れるので、ボタンは JS が動いたときだけ隠す。
 *
 * 「この遠征中に観られる試合」のチェックを変えたら、同じ条件を GET で読み直し、
 * 交通・ホテル・費用の目安（data-travel-live の部分）だけを差し替える。
 * 計算はサーバー（lib/travel.php）だけで行い、JS では金額を計算しない。
 */

document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll("[data-auto-submit]").forEach((form) => {
    const button = form.querySelector("[data-auto-submit-button]");
    if (button) button.classList.add("is-hidden");

    form.querySelectorAll("select").forEach((select) => {
      select.addEventListener("change", () => form.submit());
    });
  });

  const createForm = document.querySelector(".travel__create");
  if (createForm) {
    let request = 0;

    createForm.addEventListener("change", async (event) => {
      if (!event.target.matches("[data-travel-extra]")) return;

      // プラン作成のフォームの中身を GET の条件にする（CSRF のトークンは URL に載せない）
      const params = new URLSearchParams(new FormData(createForm));
      params.delete("csrf_token");
      const url = `travel.php?${params}`;
      const current = ++request;

      createForm.classList.add("is-loading");
      try {
        const res = await fetch(url, { headers: { Accept: "text/html" } });
        if (!res.ok) throw new Error(`status ${res.status}`);
        const doc = new DOMParser().parseFromString(await res.text(), "text/html");
        if (current !== request) return; // 続けてチェックしたときは、最後の結果だけを使う

        document.querySelectorAll("[data-travel-live]").forEach((el) => {
          const next = doc.querySelector(`[data-travel-live="${el.dataset.travelLive}"]`);
          if (next) el.replaceWith(next);
        });
        // 重なって追加できなかった試合は、チェックを外す
        createForm.querySelectorAll("[data-travel-extra]").forEach((input) => {
          const next = doc.querySelector(`[data-travel-extra][value="${CSS.escape(input.value)}"]`);
          if (next) input.checked = next.checked;
        });
        history.replaceState(null, "", url);
      } catch (error) {
        // 読み直せなければ、画面ごと開き直す
        window.location.href = url;
      } finally {
        if (current === request) createForm.classList.remove("is-loading");
      }
    });
  }
});
