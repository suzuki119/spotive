<?php

/**
 * pages/map/nearby.php
 * 会場の周辺施設を JSON で返す。js/pages/map.js の「周辺施設」ボタンから呼ぶ。
 *
 *   GET nearby.php?lat=35.4568&lng=139.6317&kind=cafe
 *   => { "ok": true, "places": [ { name, kind, lat, lng, distance }, ... ] }
 *
 * kind は lib/geoapify.php の NEARBY_KINDS のキー（food / cafe / convenience / hotel / parking）。
 * 利用者が選んだ種類だけを検索する。
 *
 * API キーをブラウザに見せないため、Geoapify へはここ（サーバー）から問い合わせる。
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/geoapify.php';

header('Content-Type: application/json; charset=UTF-8');

$lat = filter_var($_GET['lat'] ?? null, FILTER_VALIDATE_FLOAT);
$lng = filter_var($_GET['lng'] ?? null, FILTER_VALIDATE_FLOAT);
$kind = trim((string) ($_GET['kind'] ?? ''));

// 会場は日本国内だけ。それ以外の座標でクレジットを使われないようにする
$inJapan = is_float($lat) && is_float($lng)
  && $lat >= 20 && $lat <= 46 && $lng >= 122 && $lng <= 154;

if (!$inJapan || !isset(NEARBY_KINDS[$kind])) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'message' => '場所の指定が正しくありません。'], JSON_UNESCAPED_UNICODE);
  exit;
}

if (geoapify_key() === '') {
  echo json_encode(
    ['ok' => false, 'message' => '周辺施設の検索は準備中です（API キーが設定されていません）。'],
    JSON_UNESCAPED_UNICODE
  );
  exit;
}

try {
  $places = geoapify_nearby($lat, $lng, $kind);
  echo json_encode(['ok' => true, 'places' => $places], JSON_UNESCAPED_UNICODE);
} catch (RuntimeException $e) {
  http_response_code(502);
  echo json_encode(['ok' => false, 'message' => '周辺施設を読み込めませんでした。'], JSON_UNESCAPED_UNICODE);
}
