<?php
/**
 * ユーザー usermeta 保存本体（normalize 経由）
 *
 * @see docs/spec/user.md
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/** @var string[] */
function aidunite_user_canonical_meta_keys() {
    return [
        'aidunite_role',
        'registration_status',
        'user_status',
        'user_gender',
        'registration_source',
    ];
}

/**
 * @param array<string, mixed> $raw
 * @return array<string, mixed>
 */
function aidunite_user_normalize_input(array $raw) {
    if (function_exists('aidunite_normalize_user_payload')) {
        return aidunite_normalize_user_payload($raw);
    }

    return $raw;
}

/**
 * canonical usermeta を正規化して保存（唯一の書き込み口）
 *
 * @param int                  $user_id
 * @param array<string, mixed> $meta role, registration_status, user_status, user_gender, registration_source
 * @return void
 */
function aidunite_user_write_canonical_meta($user_id, array $meta) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return;
    }

    $normalized = aidunite_user_normalize_input($meta);

    if (isset($normalized['role'])) {
        aidunite_user_write_role_meta($user_id, (string) $normalized['role']);
    }

    if (isset($normalized['registration_status'])) {
        $status = (string) $normalized['registration_status'];
        if ($status !== '') {
            update_user_meta($user_id, 'registration_status', $status);
        }
    }

    if (isset($normalized['user_status'])) {
        $us = (string) $normalized['user_status'];
        if ($us !== '') {
            update_user_meta($user_id, 'user_status', $us);
        }
    }

    if (isset($normalized['user_gender']) || isset($normalized['profile_gender'])) {
        $g = (string) ($normalized['user_gender'] ?? $normalized['profile_gender'] ?? '');
        if ($g !== '') {
            update_user_meta($user_id, 'user_gender', $g);
        }
    }

    if (isset($normalized['registration_source'])) {
        $src = (string) $normalized['registration_source'];
        if ($src !== '') {
            update_user_meta($user_id, 'registration_source', $src);
        }
    }
}

/**
 * aidunite_role（＋互換 user_type）を保存
 *
 * @param int    $user_id
 * @param string $role_raw
 * @return string 保存した role（失敗時は空）
 */
function aidunite_user_write_role_meta($user_id, $role_raw) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return '';
    }

    $normalized = aidunite_user_normalize_input(['role' => $role_raw]);
    $role = (string) ($normalized['role'] ?? '');
    if ($role === '') {
        $role = strtolower(trim((string) $role_raw));
    }

    $allowed = ['team_leader', 'parent', 'player', 'supporter', 'match', 'public', 'general', 'administrator'];
    if (!in_array($role, $allowed, true)) {
        return '';
    }

    update_user_meta($user_id, 'aidunite_role', $role);
    update_user_meta($user_id, 'user_type', $role);

    return $role;
}

/**
 * registration_status を正規化して保存
 *
 * @param int    $user_id
 * @param string $status_raw
 * @return string
 */
function aidunite_user_write_registration_status_meta($user_id, $status_raw) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return '';
    }
    $normalized = aidunite_user_normalize_input(['registration_status' => $status_raw]);
    $status = (string) ($normalized['registration_status'] ?? '');
    if ($status === '') {
        return '';
    }
    update_user_meta($user_id, 'registration_status', $status);

    return $status;
}

/**
 * 本登録（メール確認）済みか
 *
 * @param int $user_id
 * @return bool
 */
function aidunite_user_read_registration_is_accepted($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return false;
    }

    $canonical = aidunite_user_get_canonical_meta($user_id);
    $status = (string) ($canonical['registration_status'] ?? '');

    return $status === 'accepted' || $status === 'active';
}

/**
 * 仮登録・本登録・保護者招待経路の read payload
 *
 * @param int $user_id
 * @return array<string, mixed>
 */
function aidunite_user_read_registration_context($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return [];
    }

    $canonical = aidunite_user_get_canonical_meta($user_id);
    $registration_source = (string) ($canonical['registration_source'] ?? '');

    return array_merge($canonical, [
        'guardian_invite_type' => (string) get_user_meta($user_id, 'guardian_invite_type', true),
        'registration_token' => (string) get_user_meta($user_id, 'registration_token', true),
        'registration_token_time' => (int) get_user_meta($user_id, 'registration_token_time', true),
        'registration_is_accepted' => aidunite_user_read_registration_is_accepted($user_id),
        'is_guardian_invite' => strpos($registration_source, 'guardian_invite') === 0,
    ]);
}

/**
 * 仮登録用トークンを発行して保存
 *
 * @param int         $user_id
 * @param string|null $token 省略時は新規生成
 * @return string
 */
function aidunite_user_persist_provisional_registration_token($user_id, $token = null) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return '';
    }

    $token = $token !== null ? (string) $token : bin2hex(random_bytes(16));
    update_user_meta($user_id, 'registration_token', $token);
    update_user_meta($user_id, 'registration_token_time', time());

    return $token;
}

/**
 * 仮登録トークンを削除
 *
 * @param int $user_id
 */
function aidunite_user_persist_clear_provisional_registration_token($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return;
    }

    delete_user_meta($user_id, 'registration_token');
    delete_user_meta($user_id, 'registration_token_time');
}

/**
 * @param int $user_id
 * @return array<string, mixed>
 */
function aidunite_user_get_canonical_meta($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return [];
    }

    $role_raw = (string) (get_user_meta($user_id, 'aidunite_role', true) ?: get_user_meta($user_id, 'user_type', true));
    $reg_raw = (string) get_user_meta($user_id, 'registration_status', true);
    $reg = $reg_raw;
    if (function_exists('aidunite_normalize_user_payload')) {
        $reg = (string) (aidunite_normalize_user_payload(['registration_status' => $reg_raw])['registration_status'] ?? $reg_raw);
    }

    $src_raw = (string) get_user_meta($user_id, 'registration_source', true);
    $src = $src_raw;
    if (function_exists('aidunite_normalize_registration_source_value')) {
        $src = aidunite_normalize_registration_source_value($src_raw);
    }

    return [
        'user_id' => $user_id,
        'aidunite_role' => $role_raw,
        'registration_status' => $reg,
        'registration_status_raw' => $reg_raw,
        'user_status' => (string) get_user_meta($user_id, 'user_status', true),
        'user_gender' => (string) get_user_meta($user_id, 'user_gender', true),
        'registration_source' => $src,
        'registration_source_raw' => $src_raw,
        'team_memberships' => aidunite_user_read_team_memberships($user_id),
    ];
}

/**
 * @param int $user_id
 * @return array<int|string, mixed>
 */
function aidunite_user_read_team_memberships($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return [];
    }

    $raw = get_user_meta($user_id, 'team_memberships', true);

    return is_array($raw) ? $raw : [];
}

/**
 * 画面表示用の主所属 team_id（current → legacy team_id）
 *
 * @param int $user_id
 * @return int
 */
/**
 * page 表示用 role（canonical）
 *
 * @param int $user_id
 * @return string
 */
function aidunite_user_read_aidunite_role($user_id) {
    $canonical = aidunite_user_get_canonical_meta((int) $user_id);

    return (string) ($canonical['aidunite_role'] ?? '');
}

/**
 * 代表電話（legacy phone 互換）
 *
 * @param int $user_id
 * @return string
 */
function aidunite_user_read_contact_phone($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return '';
    }
    $phone = (string) get_user_meta($user_id, 'user_phone', true);
    if ($phone !== '') {
        return $phone;
    }

    return (string) get_user_meta($user_id, 'phone', true);
}

/**
 * legacy team_id のみ（移行比較・管理操作用）
 *
 * @param int $user_id
 * @return int
 */
function aidunite_user_read_legacy_team_id($user_id) {
    return (int) get_user_meta((int) $user_id, 'team_id', true);
}

function aidunite_user_read_primary_team_id($user_id) {
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

    return (int) get_user_meta($user_id, 'team_id', true);
}

/**
 * /profile-edit フォーム初期値
 *
 * @param int $user_id
 * @return array<string, mixed>
 */
function aidunite_user_get_profile_display($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return [];
    }

    $avatar_type = (string) get_user_meta($user_id, 'user_avatar_type', true);

    return [
        'user_id' => $user_id,
        'user_phone' => (string) get_user_meta($user_id, 'user_phone', true),
        'user_birth_date' => (string) get_user_meta($user_id, 'user_birth_date', true),
        'user_gender' => (string) get_user_meta($user_id, 'user_gender', true),
        'user_address' => (string) get_user_meta($user_id, 'user_address', true),
        'user_bio' => (string) get_user_meta($user_id, 'user_bio', true),
        'user_avatar_type' => $avatar_type !== '' ? $avatar_type : 'emoji',
        'user_avatar_emoji' => (string) (get_user_meta($user_id, 'user_avatar_emoji', true) ?: '👤'),
    ];
}

/**
 * 管理用ユーザー一覧・CSV 行（page-admin-user-list）
 *
 * @param int $user_id
 * @return array<string, mixed>
 */
function aidunite_user_get_admin_list_display($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return [];
    }

    $user = get_userdata($user_id);
    $canonical = aidunite_user_get_canonical_meta($user_id);

    $managed_raw = function_exists('aidunite_read_user_managed_team_ids_meta')
        ? aidunite_read_user_managed_team_ids_meta($user_id)
        : get_user_meta($user_id, 'managed_team_ids', true);
    if (!is_array($managed_raw)) {
        $managed_raw = $managed_raw !== '' && $managed_raw !== false ? [$managed_raw] : [];
    }

    $reg_token = (string) get_user_meta($user_id, 'registration_token', true);

    return array_merge($canonical, [
        'user_login' => $user ? (string) $user->user_login : '',
        'user_email' => $user ? (string) $user->user_email : '',
        'display_name' => $user ? (string) $user->display_name : '',
        'user_registered' => $user ? (string) $user->user_registered : '',
        'team_id' => aidunite_user_read_primary_team_id($user_id),
        'team_id_legacy' => (int) get_user_meta($user_id, 'team_id', true),
        'managed_team_ids_raw' => $managed_raw,
        'current_operating_team_id' => (int) get_user_meta($user_id, 'current_operating_team_id', true),
        'pending_team_id' => (string) get_user_meta($user_id, 'pending_team_id', true),
        'last_name' => (string) get_user_meta($user_id, 'last_name', true),
        'first_name' => (string) get_user_meta($user_id, 'first_name', true),
        'family_id' => (string) get_user_meta($user_id, 'family_id', true),
        'registration_date' => (string) get_user_meta($user_id, 'registration_date', true),
        'registration_token' => $reg_token,
        'has_registration_token' => $reg_token !== '',
        'registration_token_time' => (string) get_user_meta($user_id, 'registration_token_time', true),
        'parent_name' => (string) get_user_meta($user_id, 'parent_name', true),
        'player_name' => (string) get_user_meta($user_id, 'player_name', true),
        'payment_status' => (string) get_user_meta($user_id, 'payment_status', true),
    ]);
}

/**
 * team_memberships 配列を保存（唯一の書き込み口）
 *
 * @param int                  $user_id
 * @param array<int|string, mixed> $memberships
 */
function aidunite_user_persist_team_memberships($user_id, array $memberships) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return;
    }
    update_user_meta($user_id, 'team_memberships', $memberships);
    clean_user_cache($user_id);
}

/**
 * プライマリ team_id + legacy team_id を保存
 *
 * @param int  $user_id
 * @param int  $team_id
 * @param bool $only_if_empty 既存 primary が無いときだけ primary を設定
 */
function aidunite_user_persist_primary_team_ids($user_id, $team_id, $only_if_empty = false) {
    $user_id = (int) $user_id;
    $team_id = (int) $team_id;
    if ($user_id <= 0 || $team_id <= 0) {
        return;
    }

    if (!$only_if_empty || (int) get_user_meta($user_id, 'primary_team_id', true) <= 0) {
        update_user_meta($user_id, 'primary_team_id', $team_id);
    }
    if (!$only_if_empty || (int) get_user_meta($user_id, 'team_id', true) <= 0) {
        update_user_meta($user_id, 'team_id', $team_id);
    }
    clean_user_cache($user_id);
}

/**
 * 複数チーム所属に1チーム追加
 *
 * @param int    $user_id
 * @param int    $team_id
 * @param string $role_in_team
 * @param string $membership_status active|pending|rejected
 * @return bool
 */
function aidunite_user_persist_add_team_membership($user_id, $team_id, $role_in_team = 'player', $membership_status = 'active') {
    $user_id = (int) $user_id;
    $team_id = (int) $team_id;
    if ($user_id <= 0 || $team_id <= 0) {
        return false;
    }

    $status = function_exists('aidunite_normalize_parent_membership_status')
        ? aidunite_normalize_parent_membership_status($membership_status)
        : (string) $membership_status;
    if ($status === '') {
        $status = 'active';
    }

    $memberships = aidunite_user_read_team_memberships($user_id);
    $memberships[$team_id] = [
        'team_id' => $team_id,
        'role' => (string) $role_in_team,
        'joined_date' => current_time('mysql'),
        'status' => $status,
    ];

    aidunite_user_persist_team_memberships($user_id, $memberships);
    if ($status === 'active') {
        aidunite_user_persist_primary_team_ids($user_id, $team_id, true);
    }

    return true;
}

/**
 * team_memberships の status を更新
 *
 * @param int    $user_id
 * @param int    $team_id
 * @param string $membership_status
 * @return bool
 */
function aidunite_user_persist_update_team_membership_status($user_id, $team_id, $membership_status) {
    $user_id = (int) $user_id;
    $team_id = (int) $team_id;
    if ($user_id <= 0 || $team_id <= 0) {
        return false;
    }

    $status = function_exists('aidunite_normalize_parent_membership_status')
        ? aidunite_normalize_parent_membership_status($membership_status)
        : (string) $membership_status;
    if ($status === '') {
        return false;
    }

    $memberships = aidunite_user_read_team_memberships($user_id);
    if (!isset($memberships[$team_id])) {
        return false;
    }

    $memberships[$team_id]['status'] = $status;
    aidunite_user_persist_team_memberships($user_id, $memberships);

    if ($status === 'active') {
        aidunite_user_persist_primary_team_ids($user_id, $team_id, true);
    }

    return true;
}

/**
 * team_memberships から1チーム分を削除
 *
 * @param int $user_id
 * @param int $team_id
 * @return bool
 */
function aidunite_user_persist_remove_team_membership($user_id, $team_id) {
    $user_id = (int) $user_id;
    $team_id = (int) $team_id;
    if ($user_id <= 0 || $team_id <= 0) {
        return false;
    }

    $memberships = aidunite_user_read_team_memberships($user_id);
    if (!isset($memberships[$team_id])) {
        return false;
    }

    unset($memberships[$team_id]);
    aidunite_user_persist_team_memberships($user_id, $memberships);

    return true;
}

/**
 * プライマリチームを切替（所属済みのみ）
 *
 * @param int $user_id
 * @param int $team_id
 * @return bool
 */
function aidunite_user_persist_set_primary_team($user_id, $team_id) {
    $user_id = (int) $user_id;
    $team_id = (int) $team_id;
    if ($user_id <= 0 || $team_id <= 0) {
        return false;
    }

    $memberships = aidunite_user_read_team_memberships($user_id);
    if (!isset($memberships[$team_id])) {
        return false;
    }

    aidunite_user_persist_primary_team_ids($user_id, $team_id, false);

    return true;
}

/**
 * legacy registration_status=active を accepted へ移行
 *
 * @param bool $dry_run
 * @return array{migrated_count:int,skipped_count:int,total:int,dry_run:bool}
 */
function aidunite_user_migrate_legacy_registration_status_meta($dry_run = false) {
    global $wpdb;
    $rows = $wpdb->get_results(
        "SELECT user_id, meta_value FROM {$wpdb->usermeta}
         WHERE meta_key = 'registration_status' AND meta_value = 'active'"
    );
    $migrated = 0;
    $skipped = 0;
    foreach ($rows ?: [] as $row) {
        $uid = (int) $row->user_id;
        if ($uid <= 0) {
            $skipped++;
            continue;
        }
        if (!$dry_run) {
            aidunite_user_write_registration_status_meta($uid, 'accepted');
        }
        $migrated++;
    }

    return [
        'migrated_count' => $migrated,
        'skipped_count'  => $skipped,
        'total'          => count($rows ?: []),
        'dry_run'        => (bool) $dry_run,
    ];
}

/**
 * 試合一覧：カード単位の最終確認時刻マップ
 *
 * @param int $user_id
 * @return array<string, string>
 */
function aidunite_user_read_market_board_card_seen($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return [];
    }

    $raw = get_user_meta($user_id, 'aidunite_market_board_card_seen', true);
    if (!is_array($raw)) {
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        } else {
            $raw = [];
        }
    }

    if (function_exists('aidunite_normalize_market_board_card_seen_payload')) {
        return aidunite_normalize_market_board_card_seen_payload($raw);
    }

    return $raw;
}

/**
 * @param int    $user_id
 * @param string $card_key mr:123 / recruit:456
 * @param string|null $seen_at ISO8601（省略時は現在）
 * @return bool
 */
function aidunite_user_persist_market_board_card_seen($user_id, $card_key, $seen_at = null) {
    $user_id = (int) $user_id;
    $card_key = trim((string) $card_key);
    if ($user_id <= 0 || $card_key === '') {
        return false;
    }
    if (!preg_match('/^(mr|recruit):\d+$/', $card_key)) {
        return false;
    }

    $seen_at = $seen_at !== null ? trim((string) $seen_at) : gmdate('c');
    if ($seen_at === '') {
        $seen_at = gmdate('c');
    }

    $map = aidunite_user_read_market_board_card_seen($user_id);
    $map[$card_key] = $seen_at;

    if (count($map) > 500) {
        arsort($map);
        $map = array_slice($map, 0, 500, true);
    }

    update_user_meta($user_id, 'aidunite_market_board_card_seen', $map);

    return true;
}
