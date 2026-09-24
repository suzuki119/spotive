<?php

/**
 * lib/geocoder.php
 * 住所 → 緯度経度。国土地理院の住所検索 API（キー不要）を使う。
 * 稼働保証のないサービスなので、失敗しても例外は投げず null を返す。
 */

declare(strict_types=1);

const GEOCODER_ENDPOINT    = 'https://msearch.gsi.go.jp/address-search/AddressSearch?q=';
const GEOCODER_TIMEOUT_SEC = 5;

/** @return array{lat:float,lng:float}|null */
function geocode(string $prefecture, string $address): ?array
{
  $query = geocode_query($prefecture, $address);
  if ($query === '') {
    return null;
  }

  $context = stream_context_create([
    'http' => [
      'timeout'       => GEOCODER_TIMEOUT_SEC,
      'ignore_errors' => true,
      'header'        => "Accept: application/json\r\n",
    ],
    'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
  ]);

  $raw = @file_get_contents(GEOCODER_ENDPOINT . rawurlencode($query), false, $context);
  if ($raw === false) {
    error_log('[SPOTIVE] geocoder: request failed for ' . $query);
    return null;
  }

  $json = json_decode($raw, true);
  $hit  = $json[0] ?? null;
  if (!is_array($hit) || !geocode_is_precise($query, (string) ($hit['properties']['title'] ?? ''))) {
    return null;
  }

  // 返り値の座標は [経度, 緯度] の順
  $coords = $hit['geometry']['coordinates'] ?? null;
  if (!is_array($coords) || count($coords) < 2 || !is_numeric($coords[0]) || !is_numeric($coords[1])) {
    return null;
  }

  $lat = round((float) $coords[1], 6);
  $lng = round((float) $coords[0], 6);

  // 日本の範囲外なら誤ヒットとみなす
  if ($lat < 20.0 || $lat > 46.5 || $lng < 122.0 || $lng > 154.0) {
    return null;
  }
  return ['lat' => $lat, 'lng' => $lng];
}

/**
 * API はあいまい検索なので、違う市や、都道府県・市区町村だけの粗い結果も返る。
 * 人が確認しないサーバー側では、市区町村が住所と一致し、かつ
 * 町名以下まで特定できた結果だけを使う。
 */
function geocode_is_precise(string $query, string $title): bool
{
  $rest = preg_replace('/^(東京都|北海道|(?:京都|大阪)府|.{2,3}県)/u', '', $title);

  // 郡名は住所で省かれやすいので飛ばし、最初の市区町村を取り出す
  if (!preg_match('/^(?:.+?郡)?(.+?[市区町村])(.*)$/u', (string) $rest, $m)) {
    return false;
  }
  // 「大阪市北区」のように政令市の区までしか分からない結果も粗いとみなす
  return str_contains($query, $m[1]) && preg_match('/^(?:[^市区町村]+区)?$/u', $m[2]) === 0;
}

/** 住所に都道府県が含まれていなければ先頭に付ける */
function geocode_query(string $prefecture, string $address): string
{
  $prefecture = trim($prefecture);
  $address    = trim($address);
  if ($address === '') {
    return '';
  }
  if ($prefecture !== '' && !str_starts_with($address, $prefecture)) {
    $address = $prefecture . $address;
  }
  return $address;
}
