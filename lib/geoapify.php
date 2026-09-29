<?php

/**
 * lib/geoapify.php
 * Geoapify Places API で、会場の周辺施設（飲食店・カフェ・ホテル・駐車場・コンビニ）を探す。
 * 企画書の機能 6「周辺施設表示」のデモ。DB 設計を含めた本実装は、チームで決めてから行う。
 *
 * API キーはブラウザに見せないため、必ずこの PHP から呼ぶ（JS から直接呼ばない）。
 * キーは config/geoapify.local.php に置く（config/geoapify.example.php を参照）。
 *
 * 検索は、利用者が選んだ種類だけを行う（1 回 1 クレジット）。無料プランは 1 日 3,000 クレジット。
 * 同じ会場・同じ種類を何度も開いても使い切らないよう、結果はしばらく一時ファイルに取っておく。
 */

declare(strict_types=1);

/** 施設の種類 => Geoapify のカテゴリ。キーは js/pages/map.js の NEARBY と揃える */
const NEARBY_KINDS = [
  'food'        => ['catering.restaurant', 'catering.fast_food'],
  'cafe'        => ['catering.cafe'],
  'convenience' => ['commercial.convenience'],
  'hotel'       => ['accommodation.hotel', 'accommodation.guest_house', 'accommodation.hostel'],
  'parking'     => ['parking.cars'],
];

const NEARBY_RADIUS_M  = 800;      // 会場から歩いて 10 分ほど
const NEARBY_LIMIT     = 20;       // 20 件までは 1 回 1 クレジット
const NEARBY_CACHE_TTL = 60 * 60;  // 1 時間

/** API キー。置かれていなければ空文字 */
function geoapify_key(): string
{
  $file = __DIR__ . '/../config/geoapify.local.php';
  $conf = is_file($file) ? (array) require $file : [];
  return trim((string) ($conf['api_key'] ?? ''));
}

/**
 * 会場のまわりにある、指定した種類の施設を近い順に返す。
 *
 * @param string $kind NEARBY_KINDS のキー。呼ぶ側で確かめておくこと
 * @return list<array{name:string,kind:string,lat:float,lng:float,distance:int}>
 * @throws RuntimeException API を呼べなかったとき（詳細は error_log に出す）
 */
function geoapify_nearby(float $lat, float $lng, string $kind): array
{
  if (!isset(NEARBY_KINDS[$kind])) {
    throw new InvalidArgumentException('Unknown nearby kind');
  }

  // 座標を丸めて、ほぼ同じ場所なら同じ結果を使い回す（約 10m 単位）
  $lat = round($lat, 4);
  $lng = round($lng, 4);

  $cacheDir  = sys_get_temp_dir() . '/spotive-geoapify';
  $cacheFile = $cacheDir . '/' . sha1($lat . ',' . $lng . ',' . $kind) . '.json';
  if (is_file($cacheFile) && time() - (int) filemtime($cacheFile) < NEARBY_CACHE_TTL) {
    $cached = json_decode((string) file_get_contents($cacheFile), true);
    if (is_array($cached)) {
      return $cached;
    }
  }

  $key = geoapify_key();
  if ($key === '') {
    throw new RuntimeException('Geoapify API key is not set');
  }

  $ch = curl_init('https://api.geoapify.com/v2/places?' . http_build_query([
    'categories' => implode(',', NEARBY_KINDS[$kind]),
    'filter'     => sprintf('circle:%F,%F,%d', $lng, $lat, NEARBY_RADIUS_M),
    'bias'       => sprintf('proximity:%F,%F', $lng, $lat),
    'limit'      => NEARBY_LIMIT,
    'lang'       => 'ja',
    'apiKey'     => $key,
  ]));
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 8,
    CURLOPT_CONNECTTIMEOUT => 4,
  ]);
  $body = curl_exec($ch);
  $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  // curl_close() は PHP 8 から何もせず、8.5 では非推奨の警告が JSON に混ざるので呼ばない

  $data = is_string($body) ? json_decode($body, true) : null;
  if ($code !== 200 || !is_array($data)) {
    // URL にはキーが入っているので、ログには出さない
    error_log(sprintf('[SPOTIVE] Geoapify error: kind=%s status=%d', $kind, $code));
    throw new RuntimeException('Geoapify request failed');
  }

  $places = [];
  foreach ($data['features'] ?? [] as $feature) {
    $p    = $feature['properties'] ?? [];
    $name = trim((string) ($p['name'] ?? ''));
    if ($name === '') {
      continue;   // 名前の無い駐車場などは、地図に出しても何か分からない
    }
    $places[] = [
      'name'     => $name,
      'kind'     => $kind,
      'lat'      => (float) ($p['lat'] ?? 0),
      'lng'      => (float) ($p['lon'] ?? 0),
      'distance' => (int) ($p['distance'] ?? 0),
    ];
  }
  usort($places, static fn(array $a, array $b): int => $a['distance'] <=> $b['distance']);

  if (is_dir($cacheDir) || @mkdir($cacheDir, 0700, true)) {
    @file_put_contents($cacheFile, json_encode($places, JSON_UNESCAPED_UNICODE));
  }

  return $places;
}
