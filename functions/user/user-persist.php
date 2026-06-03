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
    ];
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
