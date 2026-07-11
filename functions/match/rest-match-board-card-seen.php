<?php
/**
 * 試合一覧：カード閲覧済み REST
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', static function () {
    register_rest_route('aidunite/v1', '/match-board/card-seen', [
        'methods'             => 'POST',
        'callback'            => 'aidunite_rest_match_board_mark_card_seen',
        'permission_callback' => static function ($request) {
            $result = AidUniteAuthMiddleware::rest_require($request, []);
            return !is_wp_error($result);
        },
        'args'                => [
            'card_key' => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ],
    ]);
});

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function aidunite_rest_match_board_mark_card_seen($request) {
    $user_id = (int) get_current_user_id();
    if ($user_id <= 0) {
        return new WP_Error('unauthorized', 'ログインが必要です', ['status' => 401]);
    }

    $card_key = sanitize_text_field((string) $request->get_param('card_key'));
    if (!function_exists('aidunite_user_persist_market_board_card_seen')) {
        return new WP_Error('unavailable', '保存処理が利用できません', ['status' => 500]);
    }

    $ok = aidunite_user_persist_market_board_card_seen($user_id, $card_key);
    if (!$ok) {
        return new WP_Error('invalid_card_key', '無効なカードキーです', ['status' => 400]);
    }

    $seen_map = function_exists('aidunite_user_read_market_board_card_seen')
        ? aidunite_user_read_market_board_card_seen($user_id)
        : [];

    return rest_ensure_response([
        'ok'       => true,
        'card_key' => $card_key,
        'seen_at'  => (string) ($seen_map[$card_key] ?? ''),
    ]);
}
