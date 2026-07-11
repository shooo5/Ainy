<?php
/**
 * 選手登録・編集 UI 用 read payload
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param string $team_category
 * @return bool
 */
function aidunite_player_is_minor_team_category($team_category) {
    $minor_categories = ['小学生', '中学生', '高校生'];

    return in_array((string) $team_category, $minor_categories, true);
}

/**
 * 代表者の操作中チーム ID
 *
 * @param int $user_id
 * @return int
 */
function aidunite_player_read_leader_team_id($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return 0;
    }

    if (function_exists('aidunite_get_current_team_id')) {
        $current = (int) aidunite_get_current_team_id($user_id);
        if ($current > 0) {
            return $current;
        }
    }

    if (function_exists('aidunite_user_read_primary_team_id')) {
        $from_primary = (int) aidunite_user_read_primary_team_id($user_id);
        if ($from_primary > 0) {
            return $from_primary;
        }
    }

    return (int) get_user_meta($user_id, 'team_id', true);
}

/**
 * @param int $user_id
 * @return int
 */
function aidunite_player_read_team_id($user_id) {
    return aidunite_player_read_leader_team_id($user_id);
}

/**
 * 選手に紐づく保護者ユーザー ID
 *
 * @param int $player_id
 * @return int
 */
function aidunite_player_read_linked_parent_id($player_id) {
    $player_id = (int) $player_id;
    if ($player_id <= 0) {
        return 0;
    }

    return (int) get_user_meta($player_id, 'linked_parent_id', true);
}

/** @return string */
function aidunite_player_read_photo_meta_key() {
    return 'player_photo';
}

/**
 * @return int bytes
 */
function aidunite_player_read_photo_max_upload_bytes() {
    return function_exists('aidunite_get_image_upload_max_bytes')
        ? aidunite_get_image_upload_max_bytes()
        : 10 * 1024 * 1024;
}

/** @return string */
function aidunite_player_read_photo_max_upload_label() {
    return function_exists('aidunite_get_image_upload_max_label')
        ? aidunite_get_image_upload_max_label()
        : '10MB';
}

/**
 * @param int $user_id
 * @return string
 */
function aidunite_player_read_photo_url($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return '';
    }

    $url = (string) get_user_meta($user_id, aidunite_player_read_photo_meta_key(), true);

    return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
}

/**
 * 一覧・アバター表示用 URL
 *
 * @param int $user_id
 * @param int $size
 * @return string
 */
function aidunite_player_read_photo_display_url($user_id, $size = 80) {
    $user_id = (int) $user_id;
    $custom = aidunite_player_read_photo_url($user_id);
    if ($custom !== '') {
        return $custom;
    }

    $default_logo = get_template_directory_uri() . '/images/default-team-logo.png';

    return (string) get_avatar_url($user_id, ['size' => $size, 'default' => $default_logo]);
}

/**
 * @param int $user_id
 * @return array<string, mixed>
 */
function aidunite_player_get_canonical_meta($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return [];
    }

    $role_raw = (string) (get_user_meta($user_id, 'aidunite_role', true) ?: get_user_meta($user_id, 'user_type', true));

    return [
        'user_id' => $user_id,
        'aidunite_role' => $role_raw,
        'team_id' => aidunite_player_read_team_id($user_id),
    ];
}

/**
 * @param string $full_name
 * @return array{0:string,1:string}
 */
function aidunite_player_split_legacy_full_name($full_name) {
    $full_name = trim((string) $full_name);
    if ($full_name === '') {
        return ['', ''];
    }
    $parts = explode(' ', $full_name, 2);

    return [$parts[0] ?? '', $parts[1] ?? ''];
}

/**
 * @param string $birth_date Y-m-d
 * @param string $grade_raw
 * @return string
 */
function aidunite_player_resolve_display_grade($birth_date, $grade_raw = '') {
    $birth_date = trim((string) $birth_date);
    if ($birth_date !== '' && function_exists('aidunite_calculate_grade_from_birthdate')) {
        $calculated = aidunite_calculate_grade_from_birthdate($birth_date);
        if ($calculated !== '') {
            return $calculated;
        }
    }

    return trim((string) $grade_raw);
}

/**
 * 学年ラベルが中学以下か（未就学・小学・中学。高校・卒業は false）
 *
 * @param string $grade_label
 * @return bool
 */
function aidunite_grade_label_is_junior_high_or_below($grade_label) {
    $grade_label = trim((string) $grade_label);
    if ($grade_label === '' || $grade_label === '卒業') {
        return false;
    }

    return $grade_label === '未就学'
        || preg_match('/^(小学|中学)/u', $grade_label) === 1;
}

/**
 * チームカテゴリが中学以下か（小学生・中学生）
 *
 * @param mixed $team_category
 * @return bool
 */
function aidunite_player_is_junior_high_team_category($team_category) {
    return in_array((string) $team_category, ['小学生', '中学生'], true);
}

/**
 * 選手ユーザーが中学以下か（出欠通知は保護者宛て）
 *
 * @param int $user_id
 * @return bool
 */
function aidunite_user_is_junior_high_grade_or_below($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return false;
    }

    $birth_date = (string) get_user_meta($user_id, 'player_birth_date', true);
    if ($birth_date === '') {
        $birth_date = (string) get_user_meta($user_id, 'user_birth_date', true);
    }

    if ($birth_date !== '' && function_exists('aidunite_calculate_grade_from_birthdate')) {
        return aidunite_grade_label_is_junior_high_or_below(
            aidunite_calculate_grade_from_birthdate($birth_date)
        );
    }

    $grade_stored = (string) get_user_meta($user_id, 'player_grade', true);
    if ($grade_stored !== '') {
        return aidunite_grade_label_is_junior_high_or_below($grade_stored);
    }

    $team_id = function_exists('aidunite_get_current_team_id')
        ? (int) aidunite_get_current_team_id($user_id)
        : (int) get_user_meta($user_id, 'team_id', true);
    if ($team_id > 0) {
        $team_category = (string) get_post_meta($team_id, 'team_category', true);
        return aidunite_player_is_junior_high_team_category($team_category);
    }

    return false;
}

/**
 * 学年ラベルが高校生以下か（未就学・小中高。卒業＝18歳以上は false）
 *
 * @param string $grade_label
 */
function aidunite_grade_label_is_high_school_or_below($grade_label) {
    $grade_label = trim((string) $grade_label);
    if ($grade_label === '' || $grade_label === '卒業') {
        return false;
    }

    return $grade_label === '未就学'
        || preg_match('/^(小学|中学|高校)/u', $grade_label) === 1;
}

/**
 * 選手ユーザーが高校生以下か（会費表示制御用）
 *
 * @param int $user_id
 */
function aidunite_user_is_high_school_grade_or_below($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return false;
    }

    $birth_date = (string) get_user_meta($user_id, 'player_birth_date', true);
    if ($birth_date === '') {
        $birth_date = (string) get_user_meta($user_id, 'user_birth_date', true);
    }

    if ($birth_date !== '' && function_exists('aidunite_calculate_grade_from_birthdate')) {
        return aidunite_grade_label_is_high_school_or_below(
            aidunite_calculate_grade_from_birthdate($birth_date)
        );
    }

    $grade_stored = (string) get_user_meta($user_id, 'player_grade', true);
    if ($grade_stored !== '') {
        return aidunite_grade_label_is_high_school_or_below($grade_stored);
    }

    $team_id = function_exists('aidunite_get_current_team_id')
        ? (int) aidunite_get_current_team_id($user_id)
        : (int) get_user_meta($user_id, 'team_id', true);
    if ($team_id > 0) {
        $team_category = (string) get_post_meta($team_id, 'team_category', true);
        return aidunite_player_is_minor_team_category($team_category);
    }

    return false;
}

/**
 * /edit-player フォーム初期値
 *
 * @param int $user_id
 * @return array<string, mixed>
 */
function aidunite_player_get_edit_display($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return [];
    }

    $player_name_sei = (string) get_user_meta($user_id, 'player_name_sei', true);
    $player_name_mei = (string) get_user_meta($user_id, 'player_name_mei', true);
    $player_name = (string) get_user_meta($user_id, 'player_name', true);
    if ($player_name_sei === '' && $player_name_mei === '' && $player_name !== '') {
        [$player_name_sei, $player_name_mei] = aidunite_player_split_legacy_full_name($player_name);
    }

    $player_kana_sei = (string) get_user_meta($user_id, 'player_kana_sei', true);
    $player_kana_mei = (string) get_user_meta($user_id, 'player_kana_mei', true);
    $player_name_kana = (string) get_user_meta($user_id, 'player_name_kana', true);
    if ($player_kana_sei === '' && $player_kana_mei === '' && $player_name_kana !== '') {
        [$player_kana_sei, $player_kana_mei] = aidunite_player_split_legacy_full_name($player_name_kana);
    }

    $player_height = (string) get_user_meta($user_id, 'player_height', true);
    if ($player_height === '') {
        $player_height = (string) get_user_meta($user_id, 'height', true);
    }

    $player_weight = (string) get_user_meta($user_id, 'player_weight', true);
    if ($player_weight === '') {
        $player_weight = (string) get_user_meta($user_id, 'weight', true);
    }

    $parent_name_sei = (string) get_user_meta($user_id, 'parent_name_sei', true);
    $parent_name_mei = (string) get_user_meta($user_id, 'parent_name_mei', true);
    $parent_name = (string) get_user_meta($user_id, 'parent_name', true);
    if ($parent_name_sei === '' && $parent_name_mei === '' && $parent_name !== '') {
        [$parent_name_sei, $parent_name_mei] = aidunite_player_split_legacy_full_name($parent_name);
    }

    $player_birth_date = (string) get_user_meta($user_id, 'player_birth_date', true);
    $player_grade_stored = (string) get_user_meta($user_id, 'player_grade', true);

    return [
        'user_id' => $user_id,
        'player_name_sei' => $player_name_sei,
        'player_name_mei' => $player_name_mei,
        'player_name' => $player_name,
        'player_kana_sei' => $player_kana_sei,
        'player_kana_mei' => $player_kana_mei,
        'player_name_kana' => $player_name_kana,
        'player_nickname' => (string) get_user_meta($user_id, 'player_nickname', true),
        'player_birth_date' => $player_birth_date,
        'player_grade' => aidunite_player_resolve_display_grade($player_birth_date, $player_grade_stored),
        'player_position' => (string) get_user_meta($user_id, 'player_position', true),
        'player_club_team' => (string) get_user_meta($user_id, 'player_club_team', true),
        'player_email' => (string) get_user_meta($user_id, 'player_email', true),
        'player_height' => $player_height,
        'player_weight' => $player_weight,
        'parent_name_sei' => $parent_name_sei,
        'parent_name_mei' => $parent_name_mei,
        'parent_name' => $parent_name,
        'parent_email' => (string) get_user_meta($user_id, 'parent_email', true),
        'parent_emergency_contact' => (string) get_user_meta($user_id, 'parent_emergency_contact', true),
        'player_photo' => aidunite_player_read_photo_url($user_id),
    ];
}

/**
 * /team-members 一覧行
 *
 * @param int $user_id
 * @return array<string, mixed>
 */
function aidunite_player_get_list_row_display($user_id) {
    $edit = aidunite_player_get_edit_display($user_id);
    if ($edit === []) {
        return [];
    }

    $player_name = trim(($edit['player_name_sei'] ?? '') . ' ' . ($edit['player_name_mei'] ?? ''));
    if ($player_name === '') {
        $player_name = (string) ($edit['player_name'] ?? '');
    }
    if ($player_name === '') {
        $player_name = '未設定';
    }

    $parent_name_sei = (string) ($edit['parent_name_sei'] ?? '');
    $parent_name_mei = (string) ($edit['parent_name_mei'] ?? '');
    $parent_name = trim($parent_name_sei . ' ' . $parent_name_mei);
    if ($parent_name === '') {
        $parent_name = (string) ($edit['parent_name'] ?? '');
    }

    if ($parent_name === '' && function_exists('aidunite_get_player_parent')) {
        $parent_user = aidunite_get_player_parent($user_id);
        if ($parent_user instanceof WP_User) {
            if (function_exists('aidunite_parent_build_self_contact_data')) {
                $parent_contact = aidunite_parent_build_self_contact_data((int) $parent_user->ID);
                $parent_name_sei = (string) ($parent_contact['parent_name_sei'] ?? '');
                $parent_name_mei = (string) ($parent_contact['parent_name_mei'] ?? '');
                $parent_name = (string) ($parent_contact['parent_name'] ?? '');
            }
            if ($parent_name === '') {
                $parent_name = (string) $parent_user->display_name;
            }
        }
    }

    $player_name_sei = (string) ($edit['player_name_sei'] ?? '');
    $player_name_mei = (string) ($edit['player_name_mei'] ?? '');
    if ($player_name_sei === '' && $player_name_mei === '' && $player_name !== '' && $player_name !== '未設定') {
        if (function_exists('aidunite_player_split_legacy_full_name')) {
            [$player_name_sei, $player_name_mei] = aidunite_player_split_legacy_full_name($player_name);
        }
    }

    $birth = (string) ($edit['player_birth_date'] ?? '');
    $grade = aidunite_player_resolve_display_grade($birth, (string) ($edit['player_grade'] ?? ''));

    return [
        'user_id' => $user_id,
        'player_name' => $player_name,
        'player_name_sei' => $player_name_sei,
        'player_name_mei' => $player_name_mei,
        'player_nickname' => (string) ($edit['player_nickname'] ?? ''),
        'player_birth_date' => $birth,
        'player_grade' => $grade,
        'player_position' => (string) ($edit['player_position'] ?? ''),
        'player_height' => (string) ($edit['player_height'] ?? ''),
        'parent_name' => $parent_name,
        'parent_name_sei' => $parent_name_sei,
        'parent_name_mei' => $parent_name_mei,
    ];
}

/**
 * 登録フォーム再表示用の POST デフォルト
 *
 * @param array<string, mixed> $post_defaults
 * @return array<string, string>
 */
function aidunite_player_read_registration_form_defaults(array $post_defaults) {
    $keys = [
        'player_nickname',
        'player_position',
        'player_height',
        'player_club_team',
        'player_name_sei',
        'player_name_mei',
        'player_kana_sei',
        'player_kana_mei',
        'player_birth_date',
        'player_grade',
        'player_email',
        'parent_name_sei',
        'parent_name_mei',
        'parent_email',
        'parent_emergency_contact',
        'emergency_contact_name',
        'emergency_contact_relationship',
        'emergency_contact_phone',
    ];

    $out = [];
    foreach ($keys as $key) {
        if (!array_key_exists($key, $post_defaults)) {
            continue;
        }
        $out[$key] = is_scalar($post_defaults[$key]) ? (string) $post_defaults[$key] : '';
    }

    if (!empty($out['player_birth_date'])) {
        $out['player_grade'] = aidunite_player_resolve_display_grade($out['player_birth_date'], '');
    }

    return $out;
}

/**
 * 選手登録画面用のチーム ID（管理者は team_id クエリ / 先頭チームで閲覧可）
 *
 * @param int                  $user_id
 * @param array<string, mixed> $sources
 * @return int
 */
function aidunite_player_resolve_registration_team_id($user_id, array $sources = []) {
    $user_id = (int) $user_id;
    $team_id = aidunite_player_read_leader_team_id($user_id);
    if ($team_id > 0) {
        return $team_id;
    }

    if (function_exists('aidunite_user_resolve_admin_team_id')) {
        return (int) aidunite_user_resolve_admin_team_id($user_id, $sources);
    }

    return 0;
}

/**
 * 選手登録ページの操作者ロール（team_leader|parent|administrator|''）
 *
 * @param int $user_id
 * @param int $team_id
 * @return string
 */
function aidunite_player_resolve_registration_actor($user_id, $team_id) {
    $user_id = (int) $user_id;
    $team_id = (int) $team_id;
    if ($user_id <= 0) {
        return '';
    }

    if (function_exists('aidunite_user_is_privileged_admin') && aidunite_user_is_privileged_admin($user_id)) {
        return 'administrator';
    }

    $role = function_exists('aidunite_get_user_role') ? aidunite_get_user_role($user_id) : '';

    if ($role === 'parent' && $team_id > 0) {
        if (
            function_exists('aidunite_parent_read_user_membership_status')
            && aidunite_parent_read_user_membership_status($user_id, $team_id) === 'active'
        ) {
            return 'parent';
        }

        return '';
    }

    if (
        $role === 'team_leader'
        || (
            $team_id > 0
            && function_exists('aidunite_team_settings_user_is_leader_of_team')
            && aidunite_team_settings_user_is_leader_of_team($user_id, $team_id)
        )
    ) {
        return 'team_leader';
    }

    return '';
}

/**
 * page-player-add 表示コンテキスト
 *
 * @param int                  $user_id
 * @param array<string, mixed> $opts query, post_defaults, submit_result
 * @return array<string, mixed>
 */
function aidunite_player_get_registration_page_context($user_id, array $opts = []) {
    $user_id = (int) $user_id;
    $query = is_array($opts['query'] ?? null) ? $opts['query'] : [];
    $post_defaults = is_array($opts['post_defaults'] ?? null) ? $opts['post_defaults'] : [];
    $submit_result = is_array($opts['submit_result'] ?? null) ? $opts['submit_result'] : [];

    $is_privileged_admin = function_exists('aidunite_user_is_privileged_admin')
        && aidunite_user_is_privileged_admin($user_id);
    $team_id = aidunite_player_resolve_registration_team_id($user_id, array_merge($query, $post_defaults));
    $registration_actor = aidunite_player_resolve_registration_actor($user_id, $team_id);
    $registered_by_parent = ($registration_actor === 'parent');

    if ($team_id > 0 && $registration_actor === '' && !$is_privileged_admin) {
        return [
            'ok' => false,
            'redirect' => home_url('/mypage'),
            'team_id' => $team_id,
            'team' => [],
            'is_minor_team' => false,
            'is_privileged_admin' => false,
            'registered_by_parent' => false,
            'registration_actor' => '',
            'flash' => ['status' => 'forbidden', 'message' => ''],
            'errors' => ['このページを利用する権限がありません。'],
            'form_defaults' => [],
            'back_url' => home_url('/mypage'),
        ];
    }

    if ($team_id <= 0 && !$is_privileged_admin) {
        $parent_role = function_exists('aidunite_get_user_role') ? aidunite_get_user_role($user_id) : '';
        $fallback_redirect = ($registration_actor === 'parent' || $parent_role === 'parent')
            ? home_url('/mypage')
            : home_url('/team-registration');

        return [
            'ok' => false,
            'redirect' => $fallback_redirect,
            'team_id' => 0,
            'team' => [],
            'is_minor_team' => false,
            'is_privileged_admin' => false,
            'registered_by_parent' => false,
            'registration_actor' => $registration_actor,
            'flash' => ['status' => '', 'message' => ''],
            'errors' => [],
            'form_defaults' => [],
            'back_url' => $registered_by_parent ? home_url('/team-members') : home_url('/team-members'),
        ];
    }

    if ($team_id <= 0 && $is_privileged_admin) {
        $errors = is_array($submit_result['errors'] ?? null) ? $submit_result['errors'] : [];

        return [
            'ok' => true,
            'redirect' => '',
            'team_id' => 0,
            'team' => [],
            'theme_key' => 'boys',
            'is_minor_team' => false,
            'is_privileged_admin' => true,
            'registered_by_parent' => false,
            'registration_actor' => 'administrator',
            'flash' => ['status' => '', 'message' => ''],
            'errors' => $errors,
            'form_defaults' => aidunite_player_read_registration_form_defaults($post_defaults),
            'back_url' => home_url('/team-members'),
        ];
    }

    if ($registered_by_parent) {
        $team_display_pre = function_exists('aidunite_team_get_display_bundle')
            ? aidunite_team_get_display_bundle($team_id)
            : [];
        $team_category_pre = (string) ($team_display_pre['team_category'] ?? '');
        if (
            !function_exists('aidunite_player_is_minor_team_category')
            || !aidunite_player_is_minor_team_category($team_category_pre)
        ) {
            return [
                'ok' => false,
                'redirect' => home_url('/mypage'),
                'team_id' => $team_id,
                'team' => [],
                'is_minor_team' => false,
                'is_privileged_admin' => false,
                'registered_by_parent' => true,
                'registration_actor' => 'parent',
                'flash' => ['status' => '', 'message' => ''],
                'errors' => ['お子様の登録は小学生・中学生・高校生チームでのみ利用できます。'],
                'form_defaults' => [],
                'back_url' => home_url('/mypage'),
            ];
        }
    }

    $team_display = function_exists('aidunite_team_get_display_bundle')
        ? aidunite_team_get_display_bundle($team_id)
        : [];
    $team_category = (string) ($team_display['team_category'] ?? '');
    $is_minor_team = aidunite_player_is_minor_team_category($team_category);

    $flash = ['status' => '', 'message' => ''];
    if (isset($query['toast'], $query['message'])) {
        $flash = [
            'status' => sanitize_key((string) $query['toast']),
            'message' => rawurldecode((string) $query['message']),
        ];
    }

    $errors = is_array($submit_result['errors'] ?? null) ? $submit_result['errors'] : [];
    $form_defaults = !empty($submit_result['form_defaults']) && is_array($submit_result['form_defaults'])
        ? $submit_result['form_defaults']
        : aidunite_player_read_registration_form_defaults($post_defaults);

    $theme_key = function_exists('aidunite_get_team_ui_theme_key')
        ? (string) aidunite_get_team_ui_theme_key($team_id)
        : 'boys';

    return [
        'ok' => true,
        'redirect' => '',
        'team_id' => $team_id,
        'team' => [
            'id' => $team_id,
            'team_name' => (string) ($team_display['team_name'] ?? ''),
            'team_category' => $team_category,
            'theme_key' => $theme_key,
        ],
        'theme_key' => $theme_key,
        'is_minor_team' => $is_minor_team,
        'is_privileged_admin' => $is_privileged_admin,
        'registered_by_parent' => $registered_by_parent,
        'registration_actor' => $registration_actor,
        'flash' => $flash,
        'errors' => $errors,
        'form_defaults' => $form_defaults,
        'back_url' => home_url('/team-members'),
    ];
}
