<?php
/**
 * 選手登録 POST オーケストレーション
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * チーム代表者・保護者による選手登録
 *
 * @param array<string, mixed> $raw
 * @param int                  $actor_user_id
 * @return array<string, mixed>
 */
function aidunite_player_submit_registration(array $raw, $actor_user_id, array $files = []) {
    $actor_user_id = (int) $actor_user_id;
    if ($actor_user_id <= 0) {
        return [
            'ok' => false,
            'success' => false,
            'player_id' => 0,
            'redirect' => wp_login_url(home_url('/player-add')),
            'errors' => [],
            'flash' => ['status' => 'auth_required', 'message' => ''],
            'form_defaults' => [],
        ];
    }

    $nonce = (string) ($raw['player_nonce'] ?? '');
    if ($nonce === '' || !wp_verify_nonce($nonce, 'player_registration')) {
        return [
            'ok' => false,
            'success' => false,
            'player_id' => 0,
            'redirect' => '',
            'errors' => ['セキュリティチェックに失敗しました。ページを再読み込みして再度お試しください。'],
            'flash' => ['status' => 'validation', 'message' => ''],
            'form_defaults' => function_exists('aidunite_player_read_registration_form_defaults')
                ? aidunite_player_read_registration_form_defaults($raw)
                : [],
        ];
    }

    $team_id = function_exists('aidunite_player_resolve_registration_team_id')
        ? (int) aidunite_player_resolve_registration_team_id($actor_user_id, $raw)
        : 0;

    $registration_actor = function_exists('aidunite_player_resolve_registration_actor')
        ? aidunite_player_resolve_registration_actor($actor_user_id, $team_id)
        : '';

    $is_admin = function_exists('aidunite_user_is_privileged_admin')
        && aidunite_user_is_privileged_admin($actor_user_id);

    if ($team_id <= 0) {
        return [
            'ok' => false,
            'success' => false,
            'player_id' => 0,
            'redirect' => $is_admin ? '' : home_url('/mypage'),
            'errors' => $is_admin
                ? ['閲覧対象のチームが見つかりません。URL に ?team_id= を指定するか、チームを作成してください。']
                : [],
            'flash' => ['status' => 'no_team', 'message' => ''],
            'form_defaults' => function_exists('aidunite_player_read_registration_form_defaults')
                ? aidunite_player_read_registration_form_defaults($raw)
                : [],
        ];
    }

    if ($registration_actor === '' && !$is_admin) {
        return [
            'ok' => false,
            'success' => false,
            'player_id' => 0,
            'redirect' => home_url('/mypage'),
            'errors' => ['この操作を行う権限がありません。'],
            'flash' => ['status' => 'forbidden', 'message' => ''],
            'form_defaults' => function_exists('aidunite_player_read_registration_form_defaults')
                ? aidunite_player_read_registration_form_defaults($raw)
                : [],
        ];
    }

    $registered_by_parent = ($registration_actor === 'parent');

    $team_display = function_exists('aidunite_team_get_display_bundle')
        ? aidunite_team_get_display_bundle($team_id)
        : [];
    $team_category = (string) ($team_display['team_category'] ?? '');
    $is_minor_team = function_exists('aidunite_player_is_minor_team_category')
        ? aidunite_player_is_minor_team_category($team_category)
        : false;

    if ($registered_by_parent && !$is_minor_team) {
        return [
            'ok' => false,
            'success' => false,
            'player_id' => 0,
            'redirect' => home_url('/mypage'),
            'errors' => ['お子様の登録は小学生・中学生・高校生チームでのみ利用できます。'],
            'flash' => ['status' => 'forbidden', 'message' => ''],
            'form_defaults' => function_exists('aidunite_player_read_registration_form_defaults')
                ? aidunite_player_read_registration_form_defaults($raw)
                : [],
        ];
    }

    $normalized = aidunite_player_normalize_registration_input_from_post($raw, [
        'is_minor_team' => $is_minor_team,
        'team_leader_id' => $actor_user_id,
        'registered_by_parent' => $registered_by_parent,
    ]);

    if ($registered_by_parent && function_exists('aidunite_parent_build_self_contact_data')) {
        $normalized['parent'] = aidunite_parent_build_self_contact_data($actor_user_id);
    }

    $validation_opts = ['registered_by_parent' => $registered_by_parent];
    $errors = aidunite_player_validate_registration_input($normalized, $is_minor_team, $validation_opts);
    $form_defaults = function_exists('aidunite_player_read_registration_form_defaults')
        ? aidunite_player_read_registration_form_defaults($raw)
        : [];

    if (!empty($errors)) {
        return [
            'ok' => false,
            'success' => false,
            'player_id' => 0,
            'redirect' => '',
            'errors' => $errors,
            'flash' => ['status' => 'validation', 'message' => ''],
            'form_defaults' => $form_defaults,
        ];
    }

    $player_data = is_array($normalized['player'] ?? null) ? $normalized['player'] : [];

    if ($registered_by_parent) {
        $result = aidunite_parent_register_child($actor_user_id, $team_id, $player_data);
        $success_message = (string) ($result['message'] ?? 'お子様の登録が完了しました。');
    } elseif ($is_minor_team) {
        $parent_data = is_array($normalized['parent'] ?? null) ? $normalized['parent'] : [];
        $result = aidunite_team_leader_register_player_with_parent($actor_user_id, $player_data, $parent_data);
        $success_message = (string) ($result['message'] ?? '');
        if ($success_message === '') {
            $success_message = !empty($result['parent_invite_sent'])
                ? '選手の登録が完了しました。保護者への招待メールを送信しました。'
                : (!empty($result['parent_linked_existing'])
                    ? '選手の登録が完了しました。登録済みの保護者アカウントと紐づけました。'
                    : '選手の登録が完了しました。');
        }
    } else {
        $emergency_data = is_array($normalized['emergency'] ?? null) ? $normalized['emergency'] : [];
        $result = aidunite_team_leader_register_adult_player($actor_user_id, $player_data, $emergency_data);
        $success_message = '選手の登録が完了しました。';
    }

    if (!empty($result['success'])) {
        $photo_errors = [];
        if (!empty($result['player_id'])) {
            $photo_errors = aidunite_player_persist_photo_from_request((int) $result['player_id'], $raw, $files);
        }

        wp_cache_flush();
        if (!empty($result['player_id'])) {
            clean_user_cache((int) $result['player_id']);
        }
        if (!empty($result['parent_id'])) {
            clean_user_cache((int) $result['parent_id']);
        }

        if (!empty($photo_errors)) {
            $success_message .= '（写真: ' . implode(' ', $photo_errors) . '）';
        }

        $success_redirect_base = $registered_by_parent
            ? home_url('/team-members')
            : home_url('/player-add');

        return [
            'ok' => true,
            'success' => true,
            'player_id' => (int) ($result['player_id'] ?? 0),
            'redirect' => add_query_arg([
                'toast' => 'success',
                'message' => rawurlencode($success_message),
            ], $success_redirect_base),
            'errors' => [],
            'flash' => ['status' => 'success', 'message' => $success_message],
            'form_defaults' => [],
        ];
    }

    $message = (string) ($result['message'] ?? '登録に失敗しました。');

    return [
        'ok' => false,
        'success' => false,
        'player_id' => 0,
        'redirect' => add_query_arg([
            'toast' => 'error',
            'message' => rawurlencode($message),
        ], home_url('/player-add')),
        'errors' => [$message],
        'flash' => ['status' => 'error', 'message' => $message],
        'form_defaults' => $form_defaults,
    ];
}

/**
 * チーム代表者による未成年選手登録（保護者情報付き）
 *
 * @param int                  $team_leader_id
 * @param array<string, mixed> $player_data
 * @param array<string, mixed> $parent_data
 * @return array<string, mixed>
 */
function aidunite_team_leader_register_player_with_parent($team_leader_id, $player_data, $parent_data) {
    if (!aidunite_is_team_leader($team_leader_id)) {
        return ['success' => false, 'message' => 'チーム代表者権限が必要です'];
    }

    $team_id = function_exists('aidunite_get_current_team_id')
        ? (int) aidunite_get_current_team_id((int) $team_leader_id)
        : (int) get_user_meta($team_leader_id, 'team_id', true);
    if (!$team_id) {
        return ['success' => false, 'message' => 'チーム情報が見つかりません'];
    }

    $existing_user = null;
    if (!empty($player_data['player_email'])) {
        $email_for_check = function_exists('aidunite_normalize_email')
            ? aidunite_normalize_email($player_data['player_email'])
            : sanitize_email($player_data['player_email']);
        $existing_user = get_user_by('email', $email_for_check);
    }

    if ($existing_user) {
        $player_user_id = $existing_user->ID;

        aidunite_set_user_type($player_user_id, 'player');
        aidunite_set_minor_status($player_user_id, true);

        $existing_teams = aidunite_get_user_teams($player_user_id);

        if (empty($existing_teams)) {
            $managed_ids = function_exists('aidunite_get_managed_team_ids')
                ? aidunite_get_managed_team_ids((int) $player_user_id)
                : [];
            $existing_team_id = !empty($managed_ids) ? (int) $managed_ids[0] : (int) get_user_meta($player_user_id, 'team_id', true);
            if ($existing_team_id) {
                $existing_teams = [
                    $existing_team_id => [
                        'team_id' => $existing_team_id,
                        'role' => 'player',
                        'joined_date' => current_time('mysql'),
                        'status' => 'active',
                    ],
                ];
                aidunite_user_persist_team_memberships($player_user_id, $existing_teams);
                clean_user_cache($player_user_id);
            }
        }

        aidunite_add_user_to_multiple_teams($player_user_id, $team_id, 'player');

        if (!empty($player_data['nickname']) || !empty($player_data['player_name'])) {
            wp_update_user([
                'ID' => $player_user_id,
                'display_name' => $player_data['nickname'] ?? $player_data['player_name'] ?? get_userdata($player_user_id)->display_name,
                'first_name' => $player_data['player_name'] ?? get_userdata($player_user_id)->first_name,
                'nickname' => $player_data['nickname'] ?? $player_data['player_name'] ?? get_userdata($player_user_id)->nickname,
            ]);
        }

        aidunite_player_persist_registration_meta($player_user_id, $player_data, [
            'team_leader_id' => $team_leader_id,
            'skip_empty' => true,
        ]);

        $password = null;
        $username = $existing_user->user_login;

        clean_user_cache($player_user_id);
        wp_cache_flush();
    } else {
        $username = aidunite_generate_child_username($player_data['player_name'], $team_id);
        $temp_email = 'player_' . time() . '_' . wp_generate_password(6, false) . '@temp.aidunite.local';

        if (!empty($player_data['password_option']) && $player_data['password_option'] === 'manual' && !empty($player_data['custom_password'])) {
            $password = $player_data['custom_password'];
        } else {
            $password = wp_generate_password(12, false);
        }

        $player_user_id = wp_create_user($username, $password, $temp_email);

        if (is_wp_error($player_user_id)) {
            return ['success' => false, 'message' => 'アカウント作成に失敗しました: ' . $player_user_id->get_error_message()];
        }

        wp_update_user([
            'ID' => $player_user_id,
            'display_name' => $player_data['nickname'] ?? $player_data['player_name'],
            'first_name' => $player_data['player_name'],
            'nickname' => $player_data['nickname'] ?? $player_data['player_name'],
        ]);

        aidunite_player_persist_registration_meta($player_user_id, $player_data, [
            'team_leader_id' => $team_leader_id,
            'skip_empty' => false,
        ]);

        aidunite_set_user_type($player_user_id, 'player');
        aidunite_set_minor_status($player_user_id, true);
        aidunite_set_user_team($player_user_id, $team_id);
    }

    if (!empty($parent_data)) {
        aidunite_link_parent_to_child($player_user_id, $parent_data);
    } else {
        aidunite_player_persist_needs_parent_link_flag($player_user_id, true);
    }

    $parent_followup = [
        'invite_sent' => false,
        'linked_existing' => false,
        'message' => '',
    ];

    if (!empty($parent_data['parent_email']) && function_exists('aidunite_parent_submit_after_minor_player_registered')) {
        $parent_followup = aidunite_parent_submit_after_minor_player_registered(
            $team_id,
            $team_leader_id,
            $player_user_id,
            $player_data,
            $parent_data
        );
    }

    return [
        'success' => true,
        'player_id' => $player_user_id,
        'username' => $username,
        'password' => $password,
        'parent_id' => (int) ($parent_followup['parent_user_id'] ?? 0),
        'parent_invite_sent' => !empty($parent_followup['invite_sent']),
        'parent_linked_existing' => !empty($parent_followup['linked_existing']),
        'message' => (string) ($parent_followup['message'] ?? ''),
    ];
}

/**
 * 選手編集権限
 *
 * @param int $actor_user_id
 * @param int $player_id
 * @param int $team_id
 * @return bool
 */
function aidunite_player_submit_actor_can_edit_player($actor_user_id, $player_id, $team_id) {
    $actor_user_id = (int) $actor_user_id;
    $player_id = (int) $player_id;
    $team_id = (int) $team_id;
    if ($actor_user_id <= 0 || $player_id <= 0 || $team_id <= 0) {
        return false;
    }

    $canonical = aidunite_player_get_canonical_meta($player_id);
    if ((string) ($canonical['aidunite_role'] ?? '') !== 'player') {
        return false;
    }

    if ((int) ($canonical['team_id'] ?? 0) !== $team_id) {
        return false;
    }

    if (function_exists('aidunite_user_is_privileged_admin')
        && aidunite_user_is_privileged_admin($actor_user_id)) {
        return true;
    }

    $actor = function_exists('aidunite_team_members_resolve_actor')
        ? (string) aidunite_team_members_resolve_actor($actor_user_id, $team_id)
        : '';

    if ($actor === 'parent') {
        return function_exists('aidunite_team_members_parent_can_manage_player')
            && aidunite_team_members_parent_can_manage_player($actor_user_id, $player_id, $team_id);
    }

    return $actor === 'team_leader';
}

/**
 * 選手編集 POST
 *
 * @param int                  $player_id
 * @param int                  $actor_user_id
 * @param int                  $team_id
 * @param array<string, mixed> $files
 * @return array<string, mixed>
 */
function aidunite_player_submit_edit($player_id, $actor_user_id, $team_id, array $files = []) {
    $player_id = (int) $player_id;
    $actor_user_id = (int) $actor_user_id;
    $team_id = (int) $team_id;

    if ($player_id <= 0 || $actor_user_id <= 0 || $team_id <= 0) {
        return [
            'ok' => false,
            'redirect' => '',
            'errors' => ['無効なリクエストです。'],
        ];
    }

    if (!isset($_POST['update_player'])) {
        return [
            'ok' => false,
            'redirect' => '',
            'errors' => [],
        ];
    }

    $nonce_result = AidUniteAuthMiddleware::verify_nonce('player_edit_nonce', 'update_player_info');
    if (is_wp_error($nonce_result)) {
        return [
            'ok' => false,
            'redirect' => '',
            'errors' => ['セキュリティチェックに失敗しました。'],
            'http_status' => 403,
        ];
    }

    if (!aidunite_player_submit_actor_can_edit_player($actor_user_id, $player_id, $team_id)) {
        return [
            'ok' => false,
            'redirect' => '',
            'errors' => ['この選手を編集する権限がありません。'],
            'http_status' => 403,
        ];
    }

    $team_display = function_exists('aidunite_team_get_display_bundle')
        ? aidunite_team_get_display_bundle($team_id)
        : [];
    $team_category = (string) ($team_display['team_category'] ?? '');
    $is_minor_team = function_exists('aidunite_player_is_minor_team_category')
        ? aidunite_player_is_minor_team_category($team_category)
        : in_array($team_category, ['小学生', '中学生', '高校生'], true);

    $normalized_input = aidunite_player_normalize_edit_input_from_post(wp_unslash($_POST));
    aidunite_player_persist_edit_meta($player_id, $normalized_input, $is_minor_team);
    $photo_errors = aidunite_player_persist_photo_from_request($player_id, wp_unslash($_POST), $files);

    $redirect_args = [
        'updated' => '1',
        'player_id' => $player_id,
    ];
    if ($photo_errors !== []) {
        $redirect_args['photo_error'] = rawurlencode(implode(' ', $photo_errors));
    }

    return [
        'ok' => true,
        'redirect' => add_query_arg($redirect_args, home_url('/team-members')),
        'errors' => [],
        'photo_errors' => $photo_errors,
    ];
}

/**
 * 代表者が選手ユーザーを削除できるか
 *
 * @param int $actor_user_id
 * @param int $player_id
 * @return bool
 */
function aidunite_player_submit_actor_can_delete_player($actor_user_id, $player_id) {
    $actor_user_id = (int) $actor_user_id;
    $player_id = (int) $player_id;
    if ($actor_user_id <= 0 || $player_id <= 0) {
        return false;
    }

    if (function_exists('aidunite_user_is_privileged_admin')
        && aidunite_user_is_privileged_admin($actor_user_id)) {
        return aidunite_player_read_team_id($player_id) > 0;
    }

    $user_info = aidunite_get_user_info($actor_user_id);
    if (($user_info['user_type'] ?? '') !== 'team_leader') {
        return false;
    }

    if (function_exists('aidunite_users_share_managed_team')) {
        return aidunite_users_share_managed_team($actor_user_id, $player_id);
    }

    return (int) ($user_info['team_id'] ?? 0) === aidunite_player_read_team_id($player_id);
}

/**
 * 選手削除（代表者）
 *
 * @param int    $player_id
 * @param int    $actor_user_id
 * @param string $nonce
 * @return array{ok:bool,message:string}
 */
function aidunite_player_submit_delete($player_id, $actor_user_id, $nonce) {
    $player_id = (int) $player_id;
    $actor_user_id = (int) $actor_user_id;
    $nonce = (string) $nonce;

    if ($player_id <= 0 || $nonce === '' || !wp_verify_nonce($nonce, 'delete_player_' . $player_id)) {
        return [
            'ok' => false,
            'message' => '無効なパラメータです',
        ];
    }

    if (!aidunite_player_submit_actor_can_delete_player($actor_user_id, $player_id)) {
        return [
            'ok' => false,
            'message' => '権限がありません',
        ];
    }

    require_once ABSPATH . 'wp-admin/includes/user.php';
    $deleted = wp_delete_user($player_id);

    return [
        'ok' => (bool) $deleted,
        'message' => $deleted ? '選手を削除しました' : '削除に失敗しました',
    ];
}

/**
 * 選手削除 AJAX
 */
function aidunite_player_ajax_delete() {
    $player_id = (int) ($_POST['player_id'] ?? 0);
    $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));
    $result = aidunite_player_submit_delete($player_id, get_current_user_id(), $nonce);

    if (!empty($result['ok'])) {
        wp_send_json_success(['message' => (string) ($result['message'] ?? '')]);
        return;
    }

    wp_send_json_error(['message' => (string) ($result['message'] ?? '削除に失敗しました')]);
}

add_action('wp_ajax_delete_player_ajax', 'aidunite_player_ajax_delete');
