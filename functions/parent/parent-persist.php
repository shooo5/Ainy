<?php
/**
 * 保護者・家族 usermeta 書込正本
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param int                  $user_id
 * @param array<string, mixed> $fields
 * @param bool                 $skip_empty
 */
function aidunite_parent_write_meta_map($user_id, array $fields, $skip_empty = false) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return;
    }
    foreach ($fields as $key => $value) {
        if ($skip_empty && (is_scalar($value) ? (string) $value : '') === '') {
            continue;
        }
        update_user_meta($user_id, (string) $key, is_scalar($value) ? sanitize_text_field((string) $value) : $value);
    }
}

/**
 * 代表者登録フロー等の保護者メタ保存（招待サインアップは guardian_signup_profile を使用）
 *
 * @param int                  $user_id
 * @param array<string, mixed> $parent_data
 */
function aidunite_parent_persist_register_meta($user_id, array $parent_data) {
    $user_id = (int) $user_id;
    if ($user_id <= 0 || empty($parent_data)) {
        return false;
    }

    $keys = [
        'parent_name',
        'parent_name_kana',
        'parent_phone',
        'parent_email',
        'parent_address',
        'parent_emergency_contact',
        'parent_relationship',
        'parent_children_count',
    ];
    $fields = [];
    foreach ($keys as $key) {
        if (isset($parent_data[$key])) {
            $fields[$key] = $parent_data[$key];
        }
    }
    aidunite_parent_write_meta_map($user_id, $fields, false);

    if (!empty($parent_data['team_id'])) {
        aidunite_set_user_team($user_id, (int) $parent_data['team_id']);
    }

    return true;
}

/**
 * 親子紐付け（family_id / linked_parent_id）
 *
 * @param int $parent_id
 * @param int $child_user_id
 */
function aidunite_parent_persist_family_link($parent_id, $child_user_id) {
    $parent_id = (int) $parent_id;
    $child_user_id = (int) $child_user_id;
    if ($parent_id <= 0 || $child_user_id <= 0) {
        return;
    }

    $family_id = (string) $parent_id;
    update_user_meta($parent_id, 'family_id', $family_id);
    update_user_meta($child_user_id, 'family_id', $family_id);
    update_user_meta($child_user_id, 'linked_parent_id', $parent_id);
}

/**
 * 選手ユーザに保護者連絡先を保存（link_parent_to_child）
 *
 * @param int                  $child_id
 * @param array<string, mixed> $parent_data
 */
function aidunite_parent_persist_contact_on_player($child_id, array $parent_data) {
    $child_id = (int) $child_id;
    if ($child_id <= 0 || empty($parent_data)) {
        return false;
    }

    $email = function_exists('aidunite_normalize_email')
        ? aidunite_normalize_email((string) ($parent_data['parent_email'] ?? ''))
        : sanitize_email((string) ($parent_data['parent_email'] ?? ''));

    aidunite_parent_write_meta_map($child_id, [
        'parent_name' => (string) ($parent_data['parent_name'] ?? ''),
        'parent_name_sei' => (string) ($parent_data['parent_name_sei'] ?? ''),
        'parent_name_mei' => (string) ($parent_data['parent_name_mei'] ?? ''),
        'parent_name_kana' => (string) ($parent_data['parent_name_kana'] ?? ''),
        'parent_phone' => (string) ($parent_data['parent_phone'] ?? ''),
        'parent_email' => $email,
        'parent_relationship' => (string) ($parent_data['parent_relationship'] ?? '父'),
        'parent_emergency_contact' => (string) ($parent_data['parent_emergency_contact'] ?? ''),
        'parent_linked_date' => current_time('mysql'),
    ], false);

    update_user_meta($child_id, 'parent_info_linked', true);
    delete_user_meta($child_id, 'needs_parent_link');

    return true;
}

/**
 * ユーザーが指定チームに所属しているか
 *
 * @param int $user_id
 * @param int $team_id
 * @return bool
 */
function aidunite_parent_user_belongs_to_team($user_id, $team_id) {
    $user_id = (int) $user_id;
    $team_id = (int) $team_id;
    if ($user_id <= 0 || $team_id <= 0) {
        return false;
    }

    if (function_exists('aidunite_get_user_teams')) {
        $teams = aidunite_get_user_teams($user_id);
        if (is_array($teams) && isset($teams[$team_id])) {
            return true;
        }
    }

    return (int) get_user_meta($user_id, 'team_id', true) === $team_id;
}

/**
 * チーム内で保護者連絡先メールが一致する選手ユーザー ID を取得
 *
 * @param int    $team_id
 * @param string $parent_email
 * @return int[]
 */
function aidunite_parent_read_player_user_ids_by_contact_email($team_id, $parent_email) {
    $team_id = (int) $team_id;
    $parent_email = function_exists('aidunite_normalize_email')
        ? aidunite_normalize_email((string) $parent_email)
        : sanitize_email((string) $parent_email);

    if ($team_id <= 0 || $parent_email === '') {
        return [];
    }

    $candidates = get_users([
        'meta_key' => 'parent_email',
        'meta_value' => $parent_email,
        'fields' => 'ID',
        'number' => 200,
    ]);

    $player_ids = [];
    foreach ($candidates as $user_id) {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            continue;
        }

        $role = function_exists('aidunite_get_user_type')
            ? (string) aidunite_get_user_type($user_id)
            : (string) get_user_meta($user_id, 'aidunite_role', true);

        if ($role !== 'player') {
            continue;
        }

        if (!aidunite_parent_user_belongs_to_team($user_id, $team_id)) {
            continue;
        }

        $player_ids[] = $user_id;
    }

    return array_values(array_unique($player_ids));
}

/**
 * 保護者サインアップ後など：連絡先メールが一致する選手と正式紐付け
 *
 * @param int    $parent_user_id
 * @param int    $team_id
 * @param string $parent_email
 * @return array{linked_count:int,player_ids:int[]}
 */
function aidunite_parent_link_guardian_to_team_players($parent_user_id, $team_id, $parent_email) {
    $parent_user_id = (int) $parent_user_id;
    $team_id = (int) $team_id;

    if ($parent_user_id <= 0 || $team_id <= 0) {
        return ['linked_count' => 0, 'player_ids' => []];
    }

    $player_ids = aidunite_parent_read_player_user_ids_by_contact_email($team_id, $parent_email);
    $linked = [];

    foreach ($player_ids as $player_user_id) {
        $player_user_id = (int) $player_user_id;
        if ($player_user_id <= 0) {
            continue;
        }

        $existing_parent = function_exists('aidunite_player_read_linked_parent_id')
            ? aidunite_player_read_linked_parent_id($player_user_id)
            : (int) get_user_meta($player_user_id, 'linked_parent_id', true);
        if ($existing_parent > 0 && $existing_parent !== $parent_user_id) {
            continue;
        }

        if (function_exists('aidunite_link_parent_and_player')) {
            aidunite_link_parent_and_player($parent_user_id, $player_user_id);
        } else {
            aidunite_parent_persist_family_link($parent_user_id, $player_user_id);
        }

        $linked[] = $player_user_id;
    }

    if ($linked !== []) {
        clean_user_cache($parent_user_id);
        foreach ($linked as $pid) {
            clean_user_cache((int) $pid);
        }
    }

    return [
        'linked_count' => count($linked),
        'player_ids' => $linked,
    ];
}

/**
 * linked_parent_id から保護者の子ユーザー ID 一覧
 *
 * @param int $parent_id
 * @return int[]
 */
function aidunite_parent_read_linked_child_user_ids($parent_id) {
    $parent_id = (int) $parent_id;
    if ($parent_id <= 0) {
        return [];
    }

    $users = get_users([
        'meta_key' => 'linked_parent_id',
        'meta_value' => (string) $parent_id,
        'fields' => 'ID',
        'number' => 100,
    ]);

    return array_values(array_unique(array_map('intval', $users)));
}

/** @return string */
function aidunite_parent_invite_token_option_key($token) {
    return 'aidunite_invite_token_' . md5((string) $token);
}

/** @return string */
function aidunite_parent_qr_invite_option_key($team_id) {
    return 'aidunite_parent_qr_invite_' . (int) $team_id;
}

/**
 * @param mixed $raw
 * @return string email|qr
 */
function aidunite_parent_normalize_invite_type($raw) {
    $v = strtolower(trim((string) $raw));
    if ($v === 'qr') {
        return 'qr';
    }
    return 'email';
}

/**
 * 保護者一覧「経路」列の表示ラベル
 *
 * @param mixed $invite_type email|qr
 * @return string
 */
function aidunite_parent_read_invite_type_label($invite_type) {
    $normalized = aidunite_parent_normalize_invite_type($invite_type);
    if ($normalized === 'qr') {
        return 'QR';
    }
    if ($normalized === 'email') {
        return '招待';
    }

    return '—';
}

/**
 * トークン配列を正規形に揃える
 *
 * @param array<string, mixed> $stored
 * @return array<string, mixed>
 */
function aidunite_parent_normalize_invite_token_record(array $stored) {
    $invite_type = aidunite_parent_normalize_invite_type($stored['invite_type'] ?? 'email');

    return [
        'token' => (string) ($stored['token'] ?? ''),
        'team_id' => (int) ($stored['team_id'] ?? 0),
        'invite_type' => $invite_type,
        'approval_required' => $invite_type === 'qr',
        'invite_email' => (string) ($stored['invite_email'] ?? ''),
        'inviter_user_id' => (int) ($stored['inviter_user_id'] ?? 0),
        'max_uses' => $invite_type === 'email' ? 1 : null,
        'used_count' => (int) ($stored['used_count'] ?? 0),
        'expires_at' => (int) ($stored['expires_at'] ?? 0),
        'created_at' => (int) ($stored['created_at'] ?? 0),
    ];
}

/**
 * @param array<string, mixed> $stored
 * @return bool
 */
function aidunite_parent_invite_token_is_expired(array $stored) {
    $expires_at = (int) ($stored['expires_at'] ?? 0);
    return $expires_at > 0 && $expires_at < time();
}

/**
 * メール招待トークンを wp_options に保存
 *
 * @param array<string, mixed> $payload team_id, invite_email, inviter_user_id
 * @return array<string, mixed>|false
 */
function aidunite_parent_persist_invite_token(array $payload) {
    $team_id = (int) ($payload['team_id'] ?? 0);
    if ($team_id <= 0) {
        return false;
    }

    $hours_valid = (int) ($payload['hours_valid'] ?? 72);
    if ($hours_valid < 1) {
        $hours_valid = 72;
    }

    $token = bin2hex(random_bytes(32));
    $created_at = time();
    $expires_at = $created_at + ($hours_valid * 3600);

    $stored = aidunite_parent_normalize_invite_token_record([
        'token' => $token,
        'team_id' => $team_id,
        'invite_type' => 'email',
        'invite_email' => (string) ($payload['invite_email'] ?? ''),
        'inviter_user_id' => (int) ($payload['inviter_user_id'] ?? 0),
        'used_count' => 0,
        'expires_at' => $expires_at,
        'created_at' => $created_at,
    ]);

    update_option(aidunite_parent_invite_token_option_key($token), $stored, false);

    return $stored;
}

/**
 * QR 招待トークンを取得または新規生成（チーム単位）
 *
 * @param int $team_id
 * @param int $inviter_user_id
 * @return array<string, mixed>|false
 */
function aidunite_parent_persist_qr_invite_token($team_id, $inviter_user_id = 0) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return false;
    }

    $key = aidunite_parent_qr_invite_option_key($team_id);
    $existing = get_option($key);
    if (is_array($existing) && !empty($existing['token'])) {
        $normalized = aidunite_parent_normalize_invite_token_record($existing);
        if (!aidunite_parent_invite_token_is_expired($normalized)) {
            return $normalized;
        }
    }

    $token = bin2hex(random_bytes(24));
    $stored = aidunite_parent_normalize_invite_token_record([
        'token' => $token,
        'team_id' => $team_id,
        'invite_type' => 'qr',
        'invite_email' => '',
        'inviter_user_id' => (int) $inviter_user_id,
        'used_count' => 0,
        'expires_at' => 0,
        'created_at' => time(),
    ]);

    update_option($key, $stored, false);

    return $stored;
}

/**
 * @param string   $token
 * @param int|null $team_id
 * @return array<string, mixed>|false
 */
function aidunite_parent_read_invite_token($token, $team_id = null) {
    $token = sanitize_text_field((string) $token);
    if ($token === '') {
        return false;
    }

    $email_stored = get_option(aidunite_parent_invite_token_option_key($token));
    if (is_array($email_stored) && !empty($email_stored['token']) && (string) $email_stored['token'] === $token) {
        $normalized = aidunite_parent_normalize_invite_token_record($email_stored);
        if (aidunite_parent_invite_token_is_expired($normalized)) {
            delete_option(aidunite_parent_invite_token_option_key($token));
            return false;
        }
        if ($team_id !== null && (int) ($normalized['team_id'] ?? 0) !== (int) $team_id) {
            return false;
        }
        return $normalized;
    }

    $resolved_team_id = (int) $team_id;
    if ($resolved_team_id <= 0) {
        return false;
    }

    $qr_stored = get_option(aidunite_parent_qr_invite_option_key($resolved_team_id));
    if (!is_array($qr_stored) || empty($qr_stored['token']) || (string) $qr_stored['token'] !== $token) {
        return false;
    }

    $normalized = aidunite_parent_normalize_invite_token_record($qr_stored);
    if (aidunite_parent_invite_token_is_expired($normalized)) {
        return false;
    }

    return $normalized;
}

/**
 * QR トークンの利用回数を加算
 *
 * @param string $token
 * @param int    $team_id
 * @return bool
 */
function aidunite_parent_increment_invite_token_use($token, $team_id) {
    $stored = aidunite_parent_read_invite_token($token, (int) $team_id);
    if (!is_array($stored) || aidunite_parent_normalize_invite_type($stored['invite_type'] ?? '') !== 'qr') {
        return false;
    }

    $stored['used_count'] = (int) ($stored['used_count'] ?? 0) + 1;
    update_option(aidunite_parent_qr_invite_option_key((int) $team_id), $stored, false);

    return true;
}

/**
 * メール招待トークンのみ削除
 *
 * @param string $token
 * @return bool
 */
function aidunite_parent_consume_invite_token($token) {
    $token = sanitize_text_field((string) $token);
    if ($token === '') {
        return false;
    }

    $stored = get_option(aidunite_parent_invite_token_option_key($token));
    if (!is_array($stored) || empty($stored['token'])) {
        return false;
    }

    return delete_option(aidunite_parent_invite_token_option_key($token));
}

/**
 * 承認ログを team postmeta に追記
 *
 * @param int                  $team_id
 * @param array<string, mixed> $entry
 */
function aidunite_parent_persist_approval_log_entry($team_id, array $entry) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return;
    }

    $log = get_post_meta($team_id, 'aidunite_parent_approval_log', true);
    if (!is_array($log)) {
        $log = [];
    }

    $log[] = array_merge([
        'logged_at' => current_time('mysql'),
    ], $entry);

    update_post_meta($team_id, 'aidunite_parent_approval_log', $log);
}

/**
 * 保護者招待サインアップの usermeta 保存（正規化済み payload 想定）
 *
 * @param int                  $user_id
 * @param array<string, mixed> $payload
 */
function aidunite_parent_persist_guardian_signup_profile($user_id, array $payload, array $opts = []) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return false;
    }

    $registration_status = (string) ($opts['registration_status'] ?? 'accepted');
    if (!in_array($registration_status, ['pending', 'accepted'], true)) {
        $registration_status = 'accepted';
    }

    $normalized = function_exists('aidunite_normalize_parent_signup_payload')
        ? aidunite_normalize_parent_signup_payload($payload)
        : $payload;

    $parent_name = trim(
        (string) ($normalized['parent_name_sei'] ?? '')
        . ' '
        . (string) ($normalized['parent_name_mei'] ?? '')
    );

    $kana_sei = (string) ($normalized['parent_kana_sei'] ?? '');
    $kana_mei = (string) ($normalized['parent_kana_mei'] ?? '');
    $parent_kana = trim($kana_sei . ' ' . $kana_mei);

    $fields = [
        'parent_name_sei' => (string) ($normalized['parent_name_sei'] ?? ''),
        'parent_name_mei' => (string) ($normalized['parent_name_mei'] ?? ''),
        'parent_name' => $parent_name,
        'parent_kana_sei' => $kana_sei,
        'parent_kana_mei' => $kana_mei,
        'parent_name_kana' => $parent_kana,
        'parent_email' => (string) ($normalized['parent_email'] ?? ''),
        'family_id' => (string) $user_id,
    ];
    if (!empty($normalized['parent_phone'])) {
        $fields['parent_phone'] = (string) $normalized['parent_phone'];
    }
    if (!empty($normalized['invite_type'])) {
        $fields['guardian_invite_type'] = aidunite_parent_normalize_invite_type($normalized['invite_type']);
    }

    aidunite_parent_write_meta_map($user_id, $fields, false);
    update_user_meta($user_id, 'user_status', 0);
    update_user_meta($user_id, 'registration_date', current_time('mysql'));

    if (function_exists('aidunite_user_write_canonical_meta')) {
        aidunite_user_write_canonical_meta($user_id, [
            'role' => 'parent',
            'registration_status' => $registration_status,
            'registration_source' => (string) ($normalized['registration_source'] ?? 'guardian_invite'),
        ]);
    } else {
        if (function_exists('aidunite_user_write_registration_status_meta')) {
            aidunite_user_write_registration_status_meta($user_id, $registration_status);
        }
        update_user_meta(
            $user_id,
            'registration_source',
            (string) ($normalized['registration_source'] ?? 'guardian_invite')
        );
    }

    return true;
}

/**
 * 保護者による子供登録のレガシー usermeta（player-persist と併用）
 *
 * @param int                  $child_user_id
 * @param array<string, mixed> $child_data
 */
function aidunite_parent_persist_child_legacy_meta($child_user_id, array $child_data) {
    $child_user_id = (int) $child_user_id;
    if ($child_user_id <= 0) {
        return false;
    }

    $player_shape = [
        'player_name' => (string) ($child_data['child_name'] ?? $child_data['player_name'] ?? ''),
        'birth_date' => (string) ($child_data['birth_date'] ?? ''),
        'grade' => (string) ($child_data['grade'] ?? ''),
        'position' => (string) ($child_data['position'] ?? ''),
        'height' => (string) ($child_data['height'] ?? ''),
        'weight' => (string) ($child_data['weight'] ?? ''),
        'nickname' => (string) ($child_data['child_name'] ?? ''),
    ];

    if (function_exists('aidunite_player_persist_registration_meta')) {
        aidunite_player_persist_registration_meta($child_user_id, $player_shape, ['skip_empty' => false]);
    }

    $extra = [];
    foreach (['player_name_kana', 'gender', 'jersey_number', 'player_description', 'medical_info', 'emergency_contact'] as $key) {
        if (isset($child_data[$key])) {
            $extra[$key] = $child_data[$key];
        }
    }
    if (!empty($child_data['child_name']) && empty($extra['player_name'])) {
        $extra['player_name'] = $child_data['child_name'];
    }

    aidunite_parent_write_meta_map($child_user_id, $extra, false);

    if (!empty($child_data['birth_date']) && function_exists('aidunite_player_persist_age_and_grade_from_birth')) {
        aidunite_player_persist_age_and_grade_from_birth(
            $child_user_id,
            (string) $child_data['birth_date'],
            (string) ($child_data['grade'] ?? '')
        );
    }

    return true;
}

/**
 * チーム長登録フローでの保護者アカウント作成メタ
 *
 * @param int                  $parent_user_id
 * @param array<string, mixed> $parent_data
 */
function aidunite_parent_persist_team_leader_account_meta($parent_user_id, array $parent_data) {
    $parent_user_id = (int) $parent_user_id;
    if ($parent_user_id <= 0) {
        return;
    }

    $fields = [
        'parent_name' => (string) ($parent_data['parent_name'] ?? ''),
        'parent_name_kana' => (string) ($parent_data['parent_name_kana'] ?? ''),
        'parent_phone' => (string) ($parent_data['parent_phone'] ?? ''),
        'parent_email' => (string) ($parent_data['parent_email'] ?? ''),
        'parent_relationship' => (string) ($parent_data['parent_relationship'] ?? ''),
        'parent_emergency_contact' => (string) ($parent_data['parent_emergency_contact'] ?? ''),
        'registration_date' => current_time('mysql'),
        'family_id' => (string) $parent_user_id,
    ];

    aidunite_parent_write_meta_map($parent_user_id, $fields, false);
}
