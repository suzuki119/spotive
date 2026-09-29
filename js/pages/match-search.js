/**
 * match-search.js
 * 検索画面（pages/match/match-search.php）の処理。
 *   ・タブを選んだら、すぐに検索し直す
 *   ・日程・エリア・価格の行に、いま選んでいる内容を出す
 *   ・価格のスライダー（目盛りは等間隔ではないので、送る値は隠し項目に入れる）
 *   ・「現在地から○km以内」の絞り込み（現在地をサーバーに送らないよう、ここで行う）
 *   ・お気に入りのチームの試合に「お気に入り」の印を付ける（js/common/favorite-store.js）
 */

document.addEventListener("DOMContentLoaded", () => {
  // -----------------------------------------------------------------
  // お気に入りの印（登録はお気に入り画面と試合詳細で行う）
  // -----------------------------------------------------------------
  function markFavorites() {
    if (typeof SpotiveFavorites === "undefined") return;
    const favorites = SpotiveFavorites.teamIds();
    document.querySelectorAll("[data-match] .match-card").forEach((card) => {
      const badge = card.querySelector(".match-card__favorite");
      if (badge) badge.classList.toggle("is-hidden", !SpotiveFavorites.isFavoriteMatch(card, favorites));
    });
  }
  markFavorites();
  // ブラウザの登録をアカウントに移し終えたときなどに付け直す
  if (typeof SpotiveFavorites !== "undefined") SpotiveFavorites.onChange(markFavorites);

  const form = document.getElementById("search-form");
  if (!form) return;

  // -----------------------------------------------------------------
  // タブ
  // -----------------------------------------------------------------
  form.querySelectorAll(".search-tabs__input").forEach((tab) => {
    tab.addEventListener("change", () => form.requestSubmit());
  });

  // -----------------------------------------------------------------
  // 日程
  // -----------------------------------------------------------------
  const fromInput = form.elements.from;
  const toInput = form.elements.to;
  const dateLabel = form.querySelector("[data-date-label]");
  const slash = (value, withYear) => {
    const [y, m, d] = value.split("-");
    return withYear ? `${y}/${m}/${d}` : `${m}/${d}`;
  };

  function renderDateLabel() {
    const from = fromInput.value;
    const to = toInput.value;
    if (from && to) dateLabel.textContent = `${slash(from, true)}〜${slash(to, false)}`;
    else if (from) dateLabel.textContent = `${slash(from, true)}〜`;
    else if (to) dateLabel.textContent = `〜${slash(to, true)}`;
    else dateLabel.textContent = "指定しない";
  }

  fromInput.addEventListener("change", () => {
    // 終了日が開始日より前にならないようにする
    toInput.min = fromInput.value || toInput.getAttribute("min");
    if (toInput.value && fromInput.value && toInput.value < fromInput.value) toInput.value = fromInput.value;
    renderDateLabel();
  });
  toInput.addEventListener("change", renderDateLabel);

  // -----------------------------------------------------------------
  // エリア（現在地から／都道府県は、どちらか一方だけ）
  // -----------------------------------------------------------------
  const nearSelect = form.querySelector("[data-near]");
  const prefSelect = form.querySelector("[data-pref]");
  const areaLabel = form.querySelector("[data-area-label]");

  function renderAreaLabel() {
    if (nearSelect.value) areaLabel.textContent = `現在地から${nearSelect.value}km以内`;
    else if (prefSelect.value) areaLabel.textContent = prefSelect.value;
    else areaLabel.textContent = "全国";
  }

  nearSelect.addEventListener("change", () => {
    if (nearSelect.value) prefSelect.value = "";
    renderAreaLabel();
  });
  prefSelect.addEventListener("change", () => {
    if (prefSelect.value) nearSelect.value = "";
    renderAreaLabel();
  });

  // -----------------------------------------------------------------
  // 価格
  // -----------------------------------------------------------------
  const range = form.querySelector("[data-price-range]");
  const priceValue = form.querySelector("[data-price-value]");
  const priceLabel = form.querySelector("[data-price-label]");
  const steps = range.dataset.steps.split(",").map(Number);

  function renderPrice() {
    const index = Number(range.value);
    const yen = steps[index];
    const unlimited = index === steps.length - 1;
    // 右端は「上限なし」。値を送らない
    priceValue.value = unlimited ? "" : String(yen);
    priceLabel.textContent = unlimited ? "上限なし" : `¥${yen.toLocaleString("ja-JP")}まで`;
    range.style.setProperty("--fill", `${(index / (steps.length - 1)) * 100}%`);
  }

  range.addEventListener("input", renderPrice);
  renderPrice();

  // 空の条件は URL に載せない（見やすさのため）
  form.addEventListener("submit", () => {
    const allTab = form.querySelector(".search-tabs__input:checked");
    [fromInput, toInput, nearSelect, prefSelect, priceValue, form.elements.keyword, allTab].forEach((el) => {
      if (el && !el.value) el.disabled = true;
    });
  });
  // 「戻る」でこの画面に戻ったとき、送信時に止めた欄が止まったままにならないようにする
  window.addEventListener("pageshow", () => {
    form.querySelectorAll(":disabled").forEach((el) => { el.disabled = false; });
  });

  // -----------------------------------------------------------------
  // 現在地から○km以内
  // -----------------------------------------------------------------
  const nearKm = Number(nearSelect.value);
  if (!nearKm) return;

  const status = document.querySelector("[data-near-status]");
  const count = document.querySelector("[data-result-count]");
  const empty = document.querySelector("[data-empty]");
  const items = document.querySelectorAll("[data-match]");

  function showStatus(text) {
    status.textContent = text;
    status.hidden = false;
  }

  const toRad = (deg) => (deg * Math.PI) / 180;
  /** 2 点間の距離（km）。地球を球とみなした近似 */
  function distanceKm(lat1, lng1, lat2, lng2) {
    const a = Math.sin(toRad(lat2 - lat1) / 2) ** 2
      + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(toRad(lng2 - lng1) / 2) ** 2;
    return 6371 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  }

  function filterByDistance(lat, lng) {
    let shown = 0;
    items.forEach((item) => {
      // 会場の場所が分からない試合は、距離で選べないので出さない
      const ok = item.dataset.lat != null
        && distanceKm(lat, lng, Number(item.dataset.lat), Number(item.dataset.lng)) <= nearKm;
      item.hidden = !ok;
      if (ok) shown += 1;
    });
    document.querySelectorAll("[data-day]").forEach((day) => {
      day.hidden = !day.querySelector("[data-match]:not([hidden])");
    });
    count.textContent = String(shown);
    empty.hidden = shown > 0;
    showStatus(`現在地から${nearKm}km以内の試合を表示しています。`);
  }

  if (!navigator.geolocation) {
    showStatus("この端末では現在地を使えないため、距離で絞り込めませんでした。");
    return;
  }

  showStatus("現在地を確認しています…");
  navigator.geolocation.getCurrentPosition(
    (pos) => filterByDistance(pos.coords.latitude, pos.coords.longitude),
    () => showStatus("現在地を取得できなかったため、距離で絞り込めませんでした（位置情報の許可を確認してください）。"),
    { timeout: 10000, maximumAge: 5 * 60 * 1000 }
  );
});
