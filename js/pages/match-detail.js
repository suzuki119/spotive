/**
 * match-detail.js
 * 試合詳細画面（pages/match/match-detail.php）の処理。
 * 会場の位置を小さな地図に出す。周辺施設・ホテル・天気は後回し（主要機能 5・6）。
 */

document.addEventListener("DOMContentLoaded", () => {
  const mapEl = document.getElementById("detail-map");
  // 位置情報の無い大会では地図の枠が出ない。Leaflet が読めなかったときも何もしない
  if (!mapEl || typeof L === "undefined") return;

  const lat = Number(mapEl.dataset.lat);
  const lng = Number(mapEl.dataset.lng);
  if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

  const map = L.map(mapEl, {
    center: [lat, lng],
    zoom: 15,
    // ページのスクロール中に地図が勝手に拡大縮小しないようにする
    scrollWheelZoom: false,
  });

  // 観戦マップと同じ地理院タイル。クレジット表記はライセンス上の義務
  L.tileLayer("https://cyberjapandata.gsi.go.jp/xyz/pale/{z}/{x}/{y}.png", {
    maxZoom: 18,
    attribution: '<a href="https://maps.gsi.go.jp/development/ichiran.html" target="_blank" rel="noopener">地理院タイル</a>',
  }).addTo(map);

  // 会場名は data-name から textContent で入れる（HTML として解釈させない）
  const label = document.createElement("span");
  label.textContent = mapEl.dataset.name || "会場";
  L.marker([lat, lng]).addTo(map).bindPopup(label).openPopup();
});
