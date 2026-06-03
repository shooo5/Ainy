<?php
/**
 * マッチ度スコア・ランク・会場正規化（functions.php から移設）
 * overlaps, gender_score, place_score_updated, normalize_place_value, get_place_label
 */

if (!defined('ABSPATH')) {
  exit;
}

/*--------------------------------------------------------------
  No.38 共通関数_会場条件の正規化＆表示変換（重複定義削除済）
--------------------------------------------------------------*/
if (!function_exists('normalize_place_value')) {
  function normalize_place_value($val) {
    if (is_array($val)) $val = $val[0] ?? '';
    $val = trim($val);
    switch ($val) {
      case 'ホーム':
      case 'ホーム開催':
        return 'home';
      case 'アウェイ':
      case 'アウェイ開催':
        return 'away';
      case 'どちらでもOK':
      case 'どちらもOK':
      case 'どちらでも可':
      case 'どちらでも':
      case 'both':
      case 'either':
        return 'either';
      case 'home':
      case 'away':
        return $val;
      default:
        return '';
    }
  }
}

if (!function_exists('get_place_label')) {
  function get_place_label($v) {
    switch ($v) {
      case 'home':
      case 'ホーム開催':
        return 'ホーム開催';
      case 'away':
      case 'アウェイ開催':
        return 'アウェイ開催';
      case 'either':
      case 'どちらでもOK':
      case 'どちらもOK':
        return 'どちらもOK';
      default:
        return '未設定';
    }
  }
}

// 時間帯の重なりチェック（重複していれば true）
function overlaps($a, $b) {
  return ($a['start'] < $b['end'] && $b['start'] < $a['end']);
}

// 性別条件スコア（MVP 練習試合）: 男子×男子 / 女子×女子 のみ 2点。それ以外は -1
function gender_score($g1, $g2) {
  if (function_exists('aidunite_normalize_gender_canonical')) {
    $c1 = aidunite_normalize_gender_canonical((string) $g1);
    $c2 = aidunite_normalize_gender_canonical((string) $g2);
    if (!function_exists('aidunite_mvp_gender_is_valid') || !aidunite_mvp_gender_is_valid($c1) || !aidunite_mvp_gender_is_valid($c2)) {
      return -1;
    }
    if ($c1 === $c2) {
      return 2;
    }

    return -1;
  }
  if ($g1 === $g2) {
    return 2;
  }

  return -1;
}

// 会場条件スコア：home/away/either 対応
function place_score_updated($p1, $p2) {
  $p1 = normalize_place_value($p1);
  $p2 = normalize_place_value($p2);

  if (($p1 === 'home' && $p2 === 'away') || ($p1 === 'away' && $p2 === 'home')) {
    return 2;
  }
  if ($p1 === 'either' || $p2 === 'either') {
    return 1;
  }
  if ($p1 === $p2) {
    return 0;
  }
  return 0;
}

