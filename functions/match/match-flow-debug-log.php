<?php
/**
 * マッチ申請〜成立〜キャンセルまでの手動テスト用デバッグログ。
 *
 * wp-config.php で次のいずれかを有効にすると error_log に JSON 行が出ます。
 *   define( 'AIDUNITE_MATCH_FLOW_DEBUG', true );
 * または WP_DEBUG と WP_DEBUG_LOG がともに true（他ログも増える点に注意）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('aidunite_match_flow_debug_enabled')) {
    /**
     * @return bool
     */
    function aidunite_match_flow_debug_enabled() {
        if (defined('AIDUNITE_MATCH_FLOW_DEBUG') && AIDUNITE_MATCH_FLOW_DEBUG) {
            return true;
        }
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            return true;
        }
        return false;
    }
}

if (!function_exists('aidunite_match_flow_debug_log')) {
    /**
     * @param string              $event   識別子（例: ajax_update_status, rest_approve）
     * @param array<string,mixed> $context 追跡用の連想配列
     */
    function aidunite_match_flow_debug_log($event, array $context = []) {
        if (!aidunite_match_flow_debug_enabled()) {
            return;
        }
        $line = [
            'event' => (string) $event,
            'ts'    => function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s'),
            'user'  => function_exists('get_current_user_id') ? (int) get_current_user_id() : 0,
            'ctx'   => $context,
        ];
        if (function_exists('wp_json_encode')) {
            error_log('[aidunite_match_flow] ' . wp_json_encode($line, JSON_UNESCAPED_UNICODE));
        } else {
            error_log('[aidunite_match_flow] ' . $event);
        }
    }
}
