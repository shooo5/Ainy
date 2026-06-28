<?php
/**
 * 保護者招待・登録 UI 用 read payload
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param int $team_id
 * @return array<string, mixed>
 */
function aidunite_parent_read_team_payload($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return [];
    }

    $bundle = function_exists('aidunite_team_get_display_bundle')
        ? aidunite_team_get_display_bundle($team_id)
        : [];

    if ($bundle === []) {
        return [
            'id' => $team_id,
            'team_name' => '',
            'sport_type' => '',
            'region' => '',
            'activity_prefecture' => '',
            'activity_area' => '',
            'team_category' => '',
            'category_label' => '',
            'gender_label' => '',
            'team_type_label' => '',
            'logo_url' => '',
            'theme_key' => 'boys',
        ];
    }

    $sport_type = (string) ($bundle['sport_type'] ?? $bundle['team_sport'] ?? '');
    $team_category = (string) ($bundle['team_category'] ?? '');
    $gender_raw = (string) ($bundle['team_gender_option'] ?? '');
    $gender_label = function_exists('aidunite_team_gender_label')
        ? (string) aidunite_team_gender_label($gender_raw)
        : '';
    if ($gender_label === '—') {
        $gender_label = '';
    }
    $category_label = $team_category;
    if ($category_label !== '' && $gender_label !== '') {
        $category_label .= $gender_label;
    } elseif ($category_label === '' && $gender_label !== '') {
        $category_label = $gender_label;
    }

    $team_type_raw = (string) ($bundle['team_type'] ?? '');
    $team_type_label = function_exists('aidunite_team_type_label')
        ? (string) aidunite_team_type_label($team_type_raw)
        : $team_type_raw;
    if ($team_type_label === '未設定') {
        $team_type_label = '';
    }

    $activity = function_exists('aidunite_get_team_activity_profile')
        ? aidunite_get_team_activity_profile($team_id)
        : [];
    $activity_prefecture = (string) ($activity['activity_prefecture'] ?? '');
    $activity_area = (string) ($activity['activity_area'] ?? '');

    $logo_url = (string) ($bundle['team_logo'] ?? '');
    if ($logo_url !== '' && function_exists('aidunite_team_logo_is_displayable') && !aidunite_team_logo_is_displayable($logo_url)) {
        $logo_url = '';
    }

    return [
        'id' => $team_id,
        'team_name' => (string) ($bundle['team_name'] ?? ''),
        'sport_type' => $sport_type,
        'region' => (string) ($bundle['region'] ?? ''),
        'activity_prefecture' => $activity_prefecture,
        'activity_area' => $activity_area,
        'team_category' => $team_category,
        'category_label' => $category_label,
        'gender_label' => $gender_label,
        'team_type_label' => $team_type_label,
        'logo_url' => $logo_url,
        'theme_key' => function_exists('aidunite_get_team_ui_theme_key')
            ? (string) aidunite_get_team_ui_theme_key($team_id)
            : 'boys',
    ];
}

/**
 * 招待トークン read payload（UI向け）
 *
 * @param array<string, mixed>|false $stored
 * @return array<string, mixed>
 */
function aidunite_parent_read_invite_token_payload($stored) {
    if (!is_array($stored) || empty($stored['token'])) {
        return [
            'valid' => false,
            'expires_at' => '',
            'invite_type' => 'email',
            'approval_required' => false,
            'email_locked' => false,
            'invite_email' => '',
        ];
    }

    $invite_type = function_exists('aidunite_parent_normalize_invite_type')
        ? aidunite_parent_normalize_invite_type($stored['invite_type'] ?? 'email')
        : 'email';
    $invite_email = (string) ($stored['invite_email'] ?? '');
    $expires_at = (int) ($stored['expires_at'] ?? 0);

    return [
        'valid' => true,
        'expires_at' => $expires_at > 0 ? gmdate('c', $expires_at) : '',
        'invite_type' => $invite_type,
        'approval_required' => $invite_type === 'qr',
        'email_locked' => $invite_type === 'email' && $invite_email !== '',
        'invite_email' => $invite_email,
    ];
}

/**
 * @param int                  $user_id
 * @param array<string, mixed> $opts query, post_defaults
 * @return array<string, mixed>
 */
function aidunite_parent_get_invite_page_context($user_id, array $opts = []) {
    $user_id = (int) $user_id;
    $query = is_array($opts['query'] ?? null) ? $opts['query'] : [];
    $post_defaults = is_array($opts['post_defaults'] ?? null) ? $opts['post_defaults'] : [];

    $team_id = function_exists('aidunite_get_current_team_id')
        ? (int) aidunite_get_current_team_id($user_id)
        : 0;

    $can_invite = $team_id > 0
        && function_exists('aidunite_parent_submit_leader_can_manage_team')
        && aidunite_parent_submit_leader_can_manage_team($user_id, $team_id);

    $flash = ['status' => '', 'message' => ''];
    if (isset($query['sent']) && (string) $query['sent'] === '1') {
        $flash = [
            'status' => 'sent',
            'message' => '保護者に招待メールを送信しました。',
        ];
    } elseif (isset($query['error'])) {
        $error = sanitize_key((string) $query['error']);
        if ($error === 'no_team') {
            $flash = [
                'status' => 'no_team',
                'message' => 'チームに所属していません。先にチームを作成または参加してください。',
            ];
        } elseif ($error === 'send_failed') {
            $flash = [
                'status' => 'send_failed',
                'message' => '招待メールの送信に失敗しました。しばらく経ってから再度お試しください。',
            ];
        }
    }

    $locked = false;
    $lock_message = '';
    $admin_bypass = function_exists('aidunite_user_is_privileged_admin') && aidunite_user_is_privileged_admin($user_id);
    if ($team_id > 0 && function_exists('aidunite_activation_is_page_locked') && !$admin_bypass) {
        $locked = aidunite_activation_is_page_locked('invite-guardian', $team_id);
        if ($locked && function_exists('aidunite_activation_lock_message')) {
            $lock_message = (string) aidunite_activation_lock_message($team_id);
        }
    }

    $invite_email = (string) ($post_defaults['invite_email'] ?? $post_defaults['guardian_email'] ?? '');

    $qr = [
        'enabled' => false,
        'signup_url' => '',
    ];
    $parent_counts = ['active' => 0, 'pending' => 0];

    if ($team_id > 0 && !$locked) {
        $parent_counts = aidunite_parent_read_parent_counts($team_id);
        $qr_token = aidunite_parent_persist_qr_invite_token($team_id, $user_id);
        if (is_array($qr_token) && !empty($qr_token['token'])) {
            $qr = [
                'enabled' => true,
                'signup_url' => home_url(
                    '/guardian-signup/?token=' . rawurlencode((string) $qr_token['token'])
                    . '&team_id=' . $team_id
                ),
            ];
        }
    }

    return [
        'ok' => $team_id > 0 && $can_invite,
        'team_id' => $team_id,
        'team' => $team_id > 0 ? aidunite_parent_read_team_payload($team_id) : [],
        'can_invite' => $can_invite,
        'locked' => $locked,
        'lock_message' => $lock_message,
        'flash' => $flash,
        'form_defaults' => [
            'invite_email' => $invite_email,
        ],
        'qr' => $qr,
        'parent_counts' => $parent_counts,
    ];
}

/**
 * 管理者・システム開発者が招待トークンなしで画面プレビューできるか
 *
 * @param int|null $viewer_user_id
 * @return bool
 */
function aidunite_parent_signup_viewer_can_admin_preview($viewer_user_id = null) {
    $viewer_user_id = $viewer_user_id === null ? get_current_user_id() : (int) $viewer_user_id;
    if ($viewer_user_id <= 0) {
        return false;
    }

    if (user_can($viewer_user_id, 'manage_options')) {
        return true;
    }

    if (!class_exists('AidUniteAuthMiddleware')) {
        require_once get_template_directory() . '/functions/common/auth-middleware.php';
    }

    $admin_result = AidUniteAuthMiddleware::require_admin(false);
    return $admin_result->is_valid() && (int) $admin_result->user_id === $viewer_user_id;
}

/**
 * 管理者プレビュー用の team_id を解決
 *
 * @param int $team_id
 * @param int $viewer_user_id
 * @return int
 */
function aidunite_parent_resolve_signup_preview_team_id($team_id, $viewer_user_id) {
    $team_id = (int) $team_id;
    if ($team_id > 0) {
        $team = aidunite_parent_read_team_payload($team_id);
        if ((string) ($team['team_name'] ?? '') !== '') {
            return $team_id;
        }
    }

    $viewer_user_id = (int) $viewer_user_id;
    if ($viewer_user_id > 0 && function_exists('aidunite_get_current_team_id')) {
        $operating_team_id = (int) aidunite_get_current_team_id($viewer_user_id);
        if ($operating_team_id > 0) {
            return $operating_team_id;
        }
    }

    if ($viewer_user_id > 0 && function_exists('aidunite_get_managed_team_ids')) {
        foreach (aidunite_get_managed_team_ids($viewer_user_id) as $managed_team_id) {
            $managed_team_id = (int) $managed_team_id;
            if ($managed_team_id > 0) {
                return $managed_team_id;
            }
        }
    }

    $teams = get_posts([
        'post_type' => 'team',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'orderby' => 'ID',
        'order' => 'DESC',
        'fields' => 'ids',
    ]);

    return !empty($teams) ? (int) $teams[0] : 0;
}

/**
 * 管理者向けサインアップ画面プレビュー payload
 *
 * @param int                  $team_id
 * @param array<string, mixed> $opts
 * @return array<string, mixed>
 */
function aidunite_parent_get_signup_admin_preview_context($team_id, array $opts = []) {
    $viewer_user_id = (int) ($opts['viewer_user_id'] ?? get_current_user_id());
    $repersist = is_array($opts['form_repersist'] ?? null) ? $opts['form_repersist'] : [];
    $query = is_array($opts['query'] ?? null) ? $opts['query'] : [];

    $invite_preview = sanitize_key((string) ($query['invite_preview'] ?? 'email'));
    if (!in_array($invite_preview, ['email', 'qr'], true)) {
        $invite_preview = 'email';
    }

    $resolved_team_id = aidunite_parent_resolve_signup_preview_team_id($team_id, $viewer_user_id);
    $team = $resolved_team_id > 0 ? aidunite_parent_read_team_payload($resolved_team_id) : [
        'id' => 0,
        'team_name' => '（プレビュー用サンプルチーム）',
        'sport_type' => 'バスケットボール',
        'region' => '東京都',
    ];

    $token_payload = [
        'valid' => true,
        'expires_at' => '',
        'invite_type' => $invite_preview,
        'approval_required' => $invite_preview === 'qr',
        'email_locked' => $invite_preview === 'email',
        'invite_email' => $invite_preview === 'email' ? 'preview@example.com' : '',
    ];

    $preview_switch_base = home_url('/guardian-signup/');
    if ($resolved_team_id > 0) {
        $preview_switch_base = add_query_arg('team_id', $resolved_team_id, $preview_switch_base);
    }

    return [
        'ok' => true,
        'error_code' => '',
        'message' => '',
        'admin_preview' => true,
        'team' => $team,
        'token' => $token_payload,
        'token_raw' => '',
        'team_id' => $resolved_team_id,
        'preview_links' => [
            'email' => add_query_arg('invite_preview', 'email', $preview_switch_base),
            'qr' => add_query_arg('invite_preview', 'qr', $preview_switch_base),
        ],
        'form_defaults' => [
            'parent_name_sei' => (string) ($repersist['parent_name_sei'] ?? '山田'),
            'parent_name_mei' => (string) ($repersist['parent_name_mei'] ?? '太郎'),
            'parent_kana_sei' => (string) ($repersist['parent_kana_sei'] ?? 'ヤマダ'),
            'parent_kana_mei' => (string) ($repersist['parent_kana_mei'] ?? 'タロウ'),
            'parent_email' => (string) ($repersist['parent_email'] ?? ($invite_preview === 'email' ? 'preview@example.com' : '')),
            'parent_phone' => (string) ($repersist['parent_phone'] ?? '090-0000-0000'),
        ],
        'form' => [
            'password_required' => true,
            'terms_required' => true,
            'kana_required' => true,
            'submit_enabled' => false,
        ],
    ];
}

/**
 * @param string               $token
 * @param int                  $team_id
 * @param array<string, mixed> $opts form_repersist, viewer_user_id, query
 * @return array<string, mixed>
 */
function aidunite_parent_get_signup_context($token, $team_id, array $opts = []) {
    $token = sanitize_text_field((string) $token);
    $team_id = (int) $team_id;
    $repersist = is_array($opts['form_repersist'] ?? null) ? $opts['form_repersist'] : [];
    $viewer_user_id = (int) ($opts['viewer_user_id'] ?? get_current_user_id());
    $can_admin_preview = aidunite_parent_signup_viewer_can_admin_preview($viewer_user_id);

    $stored = aidunite_parent_read_invite_token($token, $team_id > 0 ? $team_id : null);
    $token_payload = aidunite_parent_read_invite_token_payload($stored);

    if (!$token_payload['valid']) {
        if ($can_admin_preview) {
            return aidunite_parent_get_signup_admin_preview_context($team_id, $opts);
        }

        return [
            'ok' => false,
            'error_code' => 'invalid_token',
            'message' => 'この招待リンクは無効または期限切れです。',
            'team' => [],
            'form_defaults' => [],
            'form' => [
                'password_required' => true,
                'terms_required' => false,
                'kana_required' => true,
            ],
            'token' => $token_payload,
        ];
    }

    $resolved_team_id = (int) ($stored['team_id'] ?? $team_id);
    $team = aidunite_parent_read_team_payload($resolved_team_id);
    if ($team === [] || (string) ($team['team_name'] ?? '') === '') {
        if ($can_admin_preview) {
            return aidunite_parent_get_signup_admin_preview_context($team_id, $opts);
        }

        return [
            'ok' => false,
            'error_code' => 'team_not_found',
            'message' => 'チーム情報が見つかりません。',
            'team' => [],
            'form_defaults' => [],
            'form' => [
                'password_required' => true,
                'terms_required' => false,
                'kana_required' => true,
            ],
            'token' => $token_payload,
        ];
    }

    $default_email = (string) ($repersist['parent_email'] ?? $token_payload['invite_email'] ?? '');
    $password_required = true;
    if ($default_email !== '' && email_exists($default_email)) {
        $password_required = false;
    }

    $invite_type = (string) ($token_payload['invite_type'] ?? 'email');

    return [
        'ok' => true,
        'error_code' => '',
        'message' => '',
        'team' => $team,
        'token' => $token_payload,
        'token_raw' => (string) ($stored['token'] ?? $token),
        'team_id' => $resolved_team_id,
        'form_defaults' => [
            'parent_name_sei' => (string) ($repersist['parent_name_sei'] ?? ''),
            'parent_name_mei' => (string) ($repersist['parent_name_mei'] ?? ''),
            'parent_kana_sei' => (string) ($repersist['parent_kana_sei'] ?? ''),
            'parent_kana_mei' => (string) ($repersist['parent_kana_mei'] ?? ''),
            'parent_email' => $default_email,
            'parent_phone' => (string) ($repersist['parent_phone'] ?? ''),
        ],
        'form' => [
            'password_required' => $password_required,
            'terms_required' => $password_required,
            'kana_required' => true,
            'submit_enabled' => true,
        ],
    ];
}

/**
 * 代表者向け承認待ち一覧 payload
 *
 * @param int $team_id
 * @param int $leader_user_id
 * @return array<string, mixed>
 */
function aidunite_parent_get_pending_approval_context($team_id, $leader_user_id) {
    $team_id = (int) $team_id;
    $leader_user_id = (int) $leader_user_id;

    if ($team_id <= 0 || $leader_user_id <= 0) {
        return [
            'ok' => false,
            'team_id' => $team_id,
            'pending_parents' => [],
            'message' => 'チーム情報が見つかりません。',
        ];
    }

    if (!aidunite_parent_submit_leader_can_manage_team($leader_user_id, $team_id)) {
        return [
            'ok' => false,
            'team_id' => $team_id,
            'pending_parents' => [],
            'message' => 'この操作を行う権限がありません。',
        ];
    }

    $pending = aidunite_parent_read_team_parent_rows($team_id, 'pending');

    return [
        'ok' => true,
        'team_id' => $team_id,
        'team' => aidunite_parent_read_team_payload($team_id),
        'pending_parents' => $pending,
        'pending_count' => count($pending),
        'message' => '',
    ];
}

/**
 * 保護者の pending 待機画面用 payload
 *
 * @param int $user_id
 * @return array<string, mixed>
 */
function aidunite_parent_get_pending_mypage_context($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return ['has_pending' => false, 'teams' => []];
    }

    $teams = [];
    if (function_exists('aidunite_user_read_team_memberships')) {
        foreach (aidunite_user_read_team_memberships($user_id) as $tid => $membership) {
            $status = is_array($membership) ? (string) ($membership['status'] ?? 'active') : 'active';
            if ($status !== 'pending') {
                continue;
            }
            $teams[] = [
                'team_id' => (int) $tid,
                'team' => aidunite_parent_read_team_payload((int) $tid),
            ];
        }
    }

    $has_active_parent_team = false;
    if (function_exists('aidunite_user_read_team_memberships')) {
        foreach (aidunite_user_read_team_memberships($user_id) as $membership) {
            if (!is_array($membership) || (string) ($membership['role'] ?? '') !== 'parent') {
                continue;
            }
            $status = (string) ($membership['status'] ?? 'active');
            if ($status === '' || $status === 'active') {
                $has_active_parent_team = true;
                break;
            }
        }
    }

    return [
        'has_pending' => $teams !== [],
        'teams' => $teams,
        'pending_only_mypage' => $teams !== [] && !$has_active_parent_team,
        'has_active_parent_team' => $has_active_parent_team,
    ];
}

/**
 * 保護者プロフィール usermeta（承認一覧・連絡先 read 用）
 *
 * @param int $user_id
 * @return array<string, string>
 */
function aidunite_parent_read_guardian_profile_fields($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return [];
    }

    $kana_sei = (string) get_user_meta($user_id, 'parent_kana_sei', true);
    $kana_mei = (string) get_user_meta($user_id, 'parent_kana_mei', true);
    $kana = trim($kana_sei . ' ' . $kana_mei);
    if ($kana === '') {
        $kana = (string) get_user_meta($user_id, 'parent_name_kana', true);
    }

    return [
        'parent_kana_sei' => $kana_sei,
        'parent_kana_mei' => $kana_mei,
        'parent_kana' => $kana,
        'parent_phone' => (string) get_user_meta($user_id, 'parent_phone', true),
        'parent_emergency_contact' => (string) get_user_meta($user_id, 'parent_emergency_contact', true),
        'parent_name_sei' => (string) get_user_meta($user_id, 'parent_name_sei', true),
        'parent_name_mei' => (string) get_user_meta($user_id, 'parent_name_mei', true),
        'guardian_invite_type' => (string) get_user_meta($user_id, 'guardian_invite_type', true),
        'registration_date' => (string) get_user_meta($user_id, 'registration_date', true),
    ];
}

/**
 * ログイン中保護者の連絡先 payload（子供登録紐付け用）
 *
 * @param int $parent_user_id
 * @return array<string, string>
 */
function aidunite_parent_read_self_contact_payload($parent_user_id) {
    $parent_user_id = (int) $parent_user_id;
    $user = get_userdata($parent_user_id);
    if (!$user) {
        return [];
    }

    $fields = aidunite_parent_read_guardian_profile_fields($parent_user_id);
    $parent_name_sei = $fields['parent_name_sei'] !== '' ? $fields['parent_name_sei'] : (string) $user->last_name;
    $parent_name_mei = $fields['parent_name_mei'] !== '' ? $fields['parent_name_mei'] : (string) $user->first_name;

    $email = function_exists('aidunite_normalize_email')
        ? aidunite_normalize_email($user->user_email)
        : sanitize_email($user->user_email);

    $phone = $fields['parent_phone'];
    if ($phone === '') {
        $phone = $fields['parent_emergency_contact'];
    }

    return [
        'parent_name' => trim($parent_name_sei . ' ' . $parent_name_mei),
        'parent_name_sei' => $parent_name_sei,
        'parent_name_mei' => $parent_name_mei,
        'parent_email' => $email,
        'parent_emergency_contact' => $phone,
    ];
}

/**
 * parent の pending membership は通常機能（スケジュール等）から除外するか
 *
 * @param int                       $user_id
 * @param int                       $team_id
 * @param array<string, mixed>|null $membership
 * @return bool
 */
function aidunite_parent_membership_excludes_operations($user_id, $team_id, $membership = null) {
    $user_id = (int) $user_id;
    $team_id = (int) $team_id;
    if ($user_id <= 0 || $team_id <= 0) {
        return false;
    }

    if ($membership === null && function_exists('aidunite_user_read_team_memberships')) {
        $memberships = aidunite_user_read_team_memberships($user_id);
        $membership = is_array($memberships[$team_id] ?? null) ? $memberships[$team_id] : null;
    }

    if (!is_array($membership)) {
        return false;
    }

    $role = (string) ($membership['role'] ?? '');
    if ($role !== 'parent') {
        return false;
    }

    $status = (string) ($membership['status'] ?? 'active');
    if ($status === '') {
        $status = 'active';
    }

    return $status === 'pending';
}

/**
 * @param int $user_id
 * @return bool
 */
function aidunite_parent_is_pending_only_mypage($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0 || !function_exists('aidunite_user_read_team_memberships')) {
        return false;
    }

    $has_pending_parent = false;
    $has_active_parent = false;

    foreach (aidunite_user_read_team_memberships($user_id) as $membership) {
        if (!is_array($membership) || (string) ($membership['role'] ?? '') !== 'parent') {
            continue;
        }
        $status = (string) ($membership['status'] ?? 'active');
        if ($status === '') {
            $status = 'active';
        }
        if ($status === 'pending') {
            $has_pending_parent = true;
        } else {
            $has_active_parent = true;
        }
    }

    return $has_pending_parent && !$has_active_parent;
}

/**
 * @param int $user_id
 * @return bool
 */
function aidunite_parent_user_has_any_team_affiliation($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return false;
    }

    if (function_exists('aidunite_get_managed_team_ids') && !empty(aidunite_get_managed_team_ids($user_id))) {
        return true;
    }

    if (function_exists('aidunite_user_read_team_memberships')) {
        foreach (aidunite_user_read_team_memberships($user_id) as $membership) {
            if (is_array($membership) && (string) ($membership['role'] ?? '') === 'parent') {
                return true;
            }
        }
    }

    if (function_exists('aidunite_user_read_legacy_team_id')) {
        return aidunite_user_read_legacy_team_id($user_id) > 0;
    }

    return false;
}

/**
 * @param int $user_id
 * @param int $team_id
 * @return string active|pending|rejected|''
 */
function aidunite_parent_read_user_membership_status($user_id, $team_id) {
    $user_id = (int) $user_id;
    $team_id = (int) $team_id;
    if ($user_id <= 0 || $team_id <= 0 || !function_exists('aidunite_user_read_team_memberships')) {
        return '';
    }

    $memberships = aidunite_user_read_team_memberships($user_id);
    if (!isset($memberships[$team_id]) || !is_array($memberships[$team_id])) {
        return '';
    }

    $status = (string) ($memberships[$team_id]['status'] ?? 'active');
    if ($status === '') {
        return 'active';
    }

    return $status;
}

/**
 * Ainy 本登録（メール確認）済みか（user-persist 委譲）
 *
 * @param int $user_id
 * @return bool
 */
function aidunite_parent_user_registration_is_accepted($user_id) {
    return function_exists('aidunite_user_read_registration_is_accepted')
        ? aidunite_user_read_registration_is_accepted($user_id)
        : false;
}

/**
 * @param int         $team_id
 * @param string|null $status active|pending|null=全件
 * @return array<int, array<string, mixed>>
 */
function aidunite_parent_read_team_parent_rows($team_id, $status = null) {
    $team_id = (int) $team_id;
    if ($team_id <= 0 || !function_exists('aidunite_get_team_affiliated_user_ids')) {
        return [];
    }

    $rows = [];
    foreach (aidunite_get_team_affiliated_user_ids($team_id) as $user_id) {
        $user_id = (int) $user_id;
        $user = get_userdata($user_id);
        if (!$user) {
            continue;
        }

        $user_type = function_exists('aidunite_get_user_info')
            ? (string) (aidunite_get_user_info($user_id)['user_type'] ?? '')
            : (string) get_user_meta($user_id, 'user_type', true);
        if ($user_type !== 'parent') {
            continue;
        }

        $row_status = aidunite_parent_read_user_membership_status($user_id, $team_id);
        if ($row_status === '') {
            $row_status = 'active';
        }
        if ($status !== null && $row_status !== $status) {
            continue;
        }

        if ($row_status === 'pending' && !aidunite_parent_user_registration_is_accepted($user_id)) {
            continue;
        }

        $profile = aidunite_parent_read_guardian_profile_fields($user_id);

        $rows[] = [
            'user_id' => $user_id,
            'display_name' => (string) $user->display_name,
            'parent_email' => (string) $user->user_email,
            'parent_phone' => $profile['parent_phone'],
            'parent_kana' => $profile['parent_kana'],
            'status' => $row_status,
            'requested_at' => $profile['registration_date'],
            'invite_type' => $profile['guardian_invite_type'],
        ];
    }

    return $rows;
}

/**
 * @param int $team_id
 * @return int
 */
function aidunite_parent_read_pending_approval_count($team_id) {
    return count(aidunite_parent_read_team_parent_rows((int) $team_id, 'pending'));
}

/**
 * @param int $team_id
 * @return array{active:int,pending:int}
 */
function aidunite_parent_read_parent_counts($team_id) {
    $team_id = (int) $team_id;
    $active = 0;
    $pending = 0;
    foreach (aidunite_parent_read_team_parent_rows($team_id, null) as $row) {
        if (($row['status'] ?? '') === 'pending') {
            $pending++;
        } else {
            $active++;
        }
    }

    return ['active' => $active, 'pending' => $pending];
}
