<?php
/**
 * お気に入りチーム機能
 * ユーザー単位で team_id をお気に入り登録し、マイページで一覧表示する。
 */

if (!defined('ABSPATH')) {
    exit;
}

/** ユーザーメタキー */
const AIDUNITE_FAVORITE_TEAM_IDS_META_KEY = 'favorite_team_ids';

/**
 * お気に入りチームID一覧を取得
 *
 * @param int $user_id ユーザーID（0の場合は現在のユーザー）
 * @return int[]
 */
function aidunite_get_favorite_team_ids($user_id = 0) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    if (!$user_id) {
        return [];
    }
    $raw = get_user_meta($user_id, AIDUNITE_FAVORITE_TEAM_IDS_META_KEY, true);
    if (is_array($raw)) {
        return array_map('intval', array_filter($raw));
    }
    if (is_string($raw) && $raw !== '') {
        $arr = array_map('intval', array_filter(explode(',', $raw)));
        return array_values(array_unique($arr));
    }
    return [];
}

/**
 * お気に入りに追加（重複・自チームは除外）
 *
 * @param int $user_id ユーザーID
 * @param int $team_id チームID（post type = team）
 * @return true|WP_Error
 */
function aidunite_add_favorite_team($user_id, $team_id) {
    $team_id = (int) $team_id;
    $own_ids = function_exists('aidunite_get_managed_team_ids') ? aidunite_get_managed_team_ids($user_id) : [];
    if (empty($own_ids)) {
        $legacy = (int) get_user_meta($user_id, 'team_id', true);
        if ($legacy > 0) {
            $own_ids = [$legacy];
        }
    }
    if ($team_id > 0 && in_array($team_id, array_map('intval', $own_ids), true)) {
        return new WP_Error('own_team', '自分のチームはお気に入りに追加できません', ['status' => 400]);
    }

    $post = get_post($team_id);
    if (!$post || $post->post_type !== 'team' || $post->post_status !== 'publish') {
        return new WP_Error('invalid_team', 'チームが見つかりません', ['status' => 404]);
    }

    $ids = aidunite_get_favorite_team_ids($user_id);
    if (in_array($team_id, $ids, true)) {
        return true; // 既に登録済み
    }
    $ids[] = $team_id;
    update_user_meta($user_id, AIDUNITE_FAVORITE_TEAM_IDS_META_KEY, $ids);
    return true;
}

/**
 * お気に入りから削除
 *
 * @param int $user_id ユーザーID
 * @param int $team_id チームID
 * @return true|WP_Error
 */
function aidunite_remove_favorite_team($user_id, $team_id) {
    $team_id = (int) $team_id;
    $ids = aidunite_get_favorite_team_ids($user_id);
    $ids = array_values(array_filter($ids, function ($id) use ($team_id) {
        return (int) $id !== $team_id;
    }));
    update_user_meta($user_id, AIDUNITE_FAVORITE_TEAM_IDS_META_KEY, $ids);
    return true;
}

/**
 * トグル（登録済みなら解除、未登録なら追加）
 *
 * @param int $user_id ユーザーID
 * @param int $team_id チームID
 * @return array{ added: bool }|WP_Error
 */
function aidunite_toggle_favorite_team($user_id, $team_id) {
    $team_id = (int) $team_id;
    $own_ids = function_exists('aidunite_get_managed_team_ids') ? aidunite_get_managed_team_ids($user_id) : [];
    if (empty($own_ids)) {
        $legacy = (int) get_user_meta($user_id, 'team_id', true);
        if ($legacy > 0) {
            $own_ids = [$legacy];
        }
    }
    if ($team_id > 0 && in_array($team_id, array_map('intval', $own_ids), true)) {
        return new WP_Error('own_team', '自分のチームはお気に入りに追加できません', ['status' => 400]);
    }

    $post = get_post($team_id);
    if (!$post || $post->post_type !== 'team' || $post->post_status !== 'publish') {
        return new WP_Error('invalid_team', 'チームが見つかりません', ['status' => 404]);
    }

    $ids = aidunite_get_favorite_team_ids($user_id);
    $is_fav = in_array($team_id, $ids, true);
    if ($is_fav) {
        aidunite_remove_favorite_team($user_id, $team_id);
        return ['added' => false];
    }
    aidunite_add_favorite_team($user_id, $team_id);
    return ['added' => true];
}

/**
 * 指定チームがお気に入りかどうか
 *
 * @param int $user_id ユーザーID（0の場合は現在のユーザー）
 * @param int $team_id チームID
 * @return bool
 */
function aidunite_is_favorite_team($user_id, $team_id) {
    $ids = aidunite_get_favorite_team_ids($user_id);
    return in_array((int) $team_id, $ids, true);
}

/**
 * お気に入りチーム一覧（ID + 名前等）を取得
 *
 * @param int $user_id ユーザーID（0の場合は現在のユーザー）
 * @return array{ id: int, name: string, profile_url: string|null }[]
 */
function aidunite_get_favorite_teams_with_names($user_id = 0) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    $ids = aidunite_get_favorite_team_ids($user_id);
    $out = [];
    foreach ($ids as $tid) {
        $post = get_post($tid);
        if (!$post || $post->post_type !== 'team') {
            continue;
        }
        $name = function_exists('aidunite_get_team_name') ? aidunite_get_team_name($tid) : get_the_title($tid);
        $profile_url = null;
        if (
            function_exists('aidunite_team_public_profile_can_view')
            && aidunite_team_public_profile_can_view($tid, (int) $user_id)
            && function_exists('aidunite_get_team_public_profile_url')
        ) {
            $profile_url = aidunite_get_team_public_profile_url($tid);
        }
        $out[] = [
            'id'          => (int) $tid,
            'name'        => $name ?: 'チーム名なし',
            'profile_url' => $profile_url,
        ];
    }
    return $out;
}

// --- REST API ---

add_action('rest_api_init', function () {
    $ns = 'aidunite/v1';

    register_rest_route($ns, '/favorite-teams', [
        'methods'             => 'GET',
        'callback'            => 'aidunite_rest_get_favorite_teams',
        'permission_callback' => function ($request) {
            $r = AidUniteAuthMiddleware::rest_require($request, []);
            return !is_wp_error($r);
        },
    ]);

    register_rest_route($ns, '/favorite-teams', [
        'methods'             => 'POST',
        'callback'            => 'aidunite_rest_add_favorite_team',
        'permission_callback' => function ($request) {
            $r = AidUniteAuthMiddleware::rest_require($request, []);
            return !is_wp_error($r);
        },
        'args' => [
            'team_id' => [
                'required'          => true,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ],
        ],
    ]);

    register_rest_route($ns, '/favorite-teams/toggle', [
        'methods'             => 'POST',
        'callback'            => 'aidunite_rest_toggle_favorite_team',
        'permission_callback' => function ($request) {
            $r = AidUniteAuthMiddleware::rest_require($request, []);
            return !is_wp_error($r);
        },
        'args' => [
            'team_id' => [
                'required'          => true,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ],
        ],
    ]);

    register_rest_route($ns, '/favorite-teams/(?P<team_id>\d+)', [
        'methods'             => 'DELETE',
        'callback'            => 'aidunite_rest_remove_favorite_team',
        'permission_callback' => function ($request) {
            $r = AidUniteAuthMiddleware::rest_require($request, []);
            return !is_wp_error($r);
        },
        'args' => [
            'team_id' => [
                'required' => true,
                'type'     => 'integer',
            ],
        ],
    ]);
});

function aidunite_rest_get_favorite_teams($request) {
    $user_id = get_current_user_id();
    $list = aidunite_get_favorite_teams_with_names($user_id);
    return new WP_REST_Response([
        'success' => true,
        'data'    => $list,
    ], 200);
}

function aidunite_rest_add_favorite_team($request) {
    $user_id = get_current_user_id();
    $team_id = (int) $request->get_param('team_id');
    $result = aidunite_add_favorite_team($user_id, $team_id);
    if (is_wp_error($result)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => $result->get_error_message(),
        ], $result->get_error_data()['status'] ?? 400);
    }
    return new WP_REST_Response([
        'success' => true,
        'message' => 'お気に入りに追加しました',
        'data'    => ['team_id' => $team_id],
    ], 200);
}

function aidunite_rest_toggle_favorite_team($request) {
    $user_id = get_current_user_id();
    $team_id = (int) $request->get_param('team_id');
    $result = aidunite_toggle_favorite_team($user_id, $team_id);
    if (is_wp_error($result)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => $result->get_error_message(),
        ], $result->get_error_data()['status'] ?? 400);
    }
    return new WP_REST_Response([
        'success' => true,
        'added'   => $result['added'],
        'data'    => ['team_id' => $team_id],
    ], 200);
}

function aidunite_rest_remove_favorite_team($request) {
    $user_id = get_current_user_id();
    $team_id = (int) $request->get_param('team_id');
    aidunite_remove_favorite_team($user_id, $team_id);
    return new WP_REST_Response([
        'success' => true,
        'message' => 'お気に入りから削除しました',
    ], 200);
}
