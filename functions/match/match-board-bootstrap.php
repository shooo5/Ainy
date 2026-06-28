<?php
/**
 * 試合掲示板・マッチ申請の依存を子テーマ（stylesheet）から一括読込。
 * get_template_directory() は親テーマを指しうるため、ここでは常に stylesheet を使う。
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('aidunite_match_theme_file')) {
    /**
     * @param string $relative functions/match/... からの相対パス
     */
    function aidunite_match_theme_file($relative) {
        return get_stylesheet_directory() . '/' . ltrim(str_replace('\\', '/', $relative), '/');
    }
}

if (!function_exists('aidunite_match_board_ensure_dependencies')) {
    /**
     * 募集中一覧・行判定に必要なヘルパーを必ず読込（重複 require は安全）。
     */
    function aidunite_match_board_ensure_dependencies() {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $files = [
            'functions/common/error-handler.php',
            'functions/common/team-activity-options.php',
            'functions/match/match-gender-venue-helpers.php',
            'functions/match/match-apply-evaluation.php',
            'functions/match/match-board-tier.php',
            'functions/match/match-board-persist-read.php',
        ];
        foreach ($files as $rel) {
            $path = aidunite_match_theme_file($rel);
            if (is_readable($path)) {
                require_once $path;
            }
        }
        $loaded = true;
    }
}

aidunite_match_board_ensure_dependencies();
