<?php
/**
 * 未所属（general）マイページの状態判定・表示データ
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 未所属（general）向けマイページ画面か
 */
function aidunite_is_general_mypage_screen() {
    if (!is_user_logged_in()) {
        return false;
    }

    $is_mypage = is_page('mypage');
    if (!$is_mypage) {
        $tpl = function_exists('get_page_template_slug') ? (string) get_page_template_slug() : '';
        $is_mypage = ($tpl === 'page-mypage.php');
    }
    if (!$is_mypage) {
        return false;
    }

    if (!function_exists('aidunite_get_effective_user_role')) {
        return false;
    }

    list($role,) = aidunite_get_effective_user_role();

    return $role === 'general';
}

/**
 * general マイページの表示状態
 *
 * @param int|null $user_id
 * @return string before_apply|pending|needs_revision
 */
function aidunite_mypage_general_state($user_id = null) {
    $user_id = $user_id === null ? get_current_user_id() : (int) $user_id;
    if ($user_id <= 0) {
        return 'before_apply';
    }

    if (function_exists('aidunite_get_user_needs_revision_team_id')) {
        $revision_id = aidunite_get_user_needs_revision_team_id($user_id);
        if ($revision_id > 0) {
            return 'needs_revision';
        }
    }

    if (function_exists('aidunite_get_user_pending_application_team_ids')) {
        if (aidunite_get_user_pending_application_team_ids($user_id) !== []) {
            return 'pending';
        }
    } else {
        $pending = (int) get_user_meta($user_id, 'pending_team_id', true);
        if ($pending > 0) {
            $post = get_post($pending);
            if ($post && $post->post_type === 'team' && $post->post_status === 'pending') {
                return 'pending';
            }
        }
    }

    return 'before_apply';
}

/**
 * 承認待ちマイページ用コンテキスト
 *
 * @param int|null $user_id
 * @return array<string, mixed>
 */
function aidunite_mypage_general_pending_context($user_id = null) {
    if (function_exists('aidunite_mypage_pending_application_teams_context')) {
        $multi = aidunite_mypage_pending_application_teams_context($user_id);
        if (!empty($multi['teams'])) {
            $first = $multi['teams'][0];
            return array_merge($first, [
                'teams'        => $multi['teams'],
                'submitted_at' => $multi['submitted_at'],
                'user_email'   => $multi['user_email'],
            ]);
        }
    }

    if (function_exists('aidunite_get_team_registration_complete_context')) {
        $ctx = aidunite_get_team_registration_complete_context();
        if (!empty($ctx['team_id'])) {
            return $ctx;
        }
    }

    return [
        'team_id'       => 0,
        'team_name'     => '',
        'sport_type'    => '',
        'team_category' => '',
        'team_type'     => '',
        'region'        => '',
        'team_logo'     => '',
        'logo_crop'     => ['x' => 0, 'y' => 0, 'zoom' => 100],
        'submitted_at'  => '',
        'user_email'    => '',
        'teams'         => [],
    ];
}
