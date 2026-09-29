<?php

/**
 * lib/hotel.php
 * 会場周辺のホテル（企画書の機能 6「周辺施設表示」のうちホテル）。
 *
 * いまは画面を確認するための仮データ（data/hotels.json）だけを使う。名前・住所・料金などはすべて架空。
 * 楽天トラベル API（利用規約の確認中）などにつなぐときは、load_hotels() の中身を差し替える。
 * 機能 6 は AGENTS.md で「後回し」なので、DB のテーブルは作らない。
 */

declare(strict_types=1);

require_once __DIR__ . '/match.php';

/** 設備のキー => 表示名とアイコン（images/icons/）。hotels.json の amenities に入れるキー */
const HOTEL_AMENITIES = [
  'wifi'      => ['label' => 'Wi-Fi',        'icon' => 'wifi.svg'],
  'breakfast' => ['label' => '朝食',         'icon' => 'breakfast.svg'],
  'laundry'   => ['label' => 'コインランドリー', 'icon' => 'laundry.svg'],
  'parking'   => ['label' => '駐車場',       'icon' => 'parking.svg'],
];

/** 「会場の近く」とみなす距離（km） */
const HOTEL_NEAR_KM = 3.0;

/** 徒歩の速さ。不動産広告の表示ルールと同じ「80m で 1 分」 */
const HOTEL_WALK_M_PER_MIN = 80;

/** @return list<array<string,mixed>> 仮データのホテル */
function load_hotels(): array
{
  return load_data_json('hotels.json');
}

/** @return array<string,mixed>|null */
function find_hotel(string $id): ?array
{
  foreach (load_hotels() as $hotel) {
    if (($hotel['id'] ?? '') === $id) {
      return $hotel;
    }
  }
  return null;
}

/** 2 点間の距離（km）。地球を球とみなした近似 */
function hotel_distance_km(float $lat1, float $lng1, float $lat2, float $lng2): float
{
  $rad = M_PI / 180;
  $a   = sin(($lat2 - $lat1) * $rad / 2) ** 2
    + cos($lat1 * $rad) * cos($lat2 * $rad) * sin(($lng2 - $lng1) * $rad / 2) ** 2;
  return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

/**
 * 徒歩の目安（分）。直線距離から出すので、実際の道のりより短めになる
 */
function hotel_walk_minutes(float $km): int
{
  return max(1, (int) ceil($km * 1000 / HOTEL_WALK_M_PER_MIN));
}

/** 2.4km / 850m */
function hotel_distance_label(float $km): string
{
  return $km < 1 ? (string) (int) round($km * 1000, -1) . 'm' : number_format($km, 1) . 'km';
}

/**
 * 会場の近くのホテルを、近い順に返す。距離（km）を distance に入れて返す。
 *
 * @return list<array<string,mixed>>
 */
function hotels_near(float $lat, float $lng, float $withinKm = HOTEL_NEAR_KM): array
{
  $list = [];
  foreach (load_hotels() as $hotel) {
    if (!isset($hotel['lat'], $hotel['lng'])) {
      continue;
    }
    $km = hotel_distance_km($lat, $lng, (float) $hotel['lat'], (float) $hotel['lng']);
    if ($km <= $withinKm) {
      $list[] = $hotel + ['distance' => $km];
    }
  }
  usort($list, static fn(array $a, array $b): int => $a['distance'] <=> $b['distance']);
  return $list;
}

/**
 * ホテル詳細へのパス（アプリのルートから）。出すときは url() を通すこと。
 * どの試合から来たかを渡すと、「試合情報に戻る」と会場までの距離に使う。
 */
function hotel_detail_path(string $hotelId, string $matchId = ''): string
{
  $query = ['id' => $hotelId];
  if ($matchId !== '') {
    $query['match'] = $matchId;
  }
  return 'pages/hotel/hotel-detail.php?' . http_build_query($query);
}
