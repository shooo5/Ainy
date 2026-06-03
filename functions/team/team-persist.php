<?php
/**
 * チーム（team CPT）の postmeta 保存本体（normalize 経由）
 *
 * @see docs/spec/team.md
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param array<string, mixed> $raw
 * @return array<string, mixed>
 */
function aidunite_team_normalize_input(array $raw) {
    if (function_exists('aidunite_normalize_team_payload')) {
        return aidunite_normalize_team_payload($raw);
    }

    return $raw;
}

/**
 * team_status を正規化して保存
 *
 * @param int    $team_id
 * @param string $status_raw
 * @return string
 */
function aidunite_team_write_status_meta($team_id, $status_raw) {
    $team_id = (int) $team_id;
    if ($team_id <= 0 || get_post_type($team_id) !== 'team') {
        return '';
    }
    $normalized = aidunite_team_normalize_input(['team_status' => $status_raw]);
    $status = (string) ($normalized['team_status'] ?? '');
    if ($status === '') {
        return '';
    }
    update_post_meta($team_id, 'team_status', $status);

    return $status;
}

/**
 * 性別・組織種別など canonical フィールドを保存
 *
 * @param int                  $team_id
 * @param array<string, mixed> $meta
 * @return void
 */
function aidunite_team_write_canonical_meta($team_id, array $meta) {
    $team_id = (int) $team_id;
    if ($team_id <= 0 || get_post_type($team_id) !== 'team') {
        return;
    }

    $normalized = aidunite_team_normalize_input($meta);

    if (isset($normalized['team_gender_option']) || isset($normalized['gender'])) {
        $g = (string) ($normalized['team_gender_option'] ?? $normalized['gender'] ?? '');
        if ($g !== '' && function_exists('aidunite_normalize_team_gender_option')) {
            $g = aidunite_normalize_team_gender_option($g);
        }
        $ban_both = apply_filters('aidunite_mvp_ban_new_team_gender_both', true);
        if ($g !== '' && !($ban_both && in_array($g, ['both', 'mixed'], true))) {
            update_post_meta($team_id, 'team_gender_option', $g);
        }
    }

    if (isset($normalized['team_type']) || isset($normalized['org_type'])) {
        if (function_exists('aidunite_update_team_type_meta')) {
            aidunite_update_team_type_meta($team_id, (string) ($normalized['team_type'] ?? $normalized['org_type'] ?? ''));
        } else {
            $org = function_exists('aidunite_normalize_team_org_type_value')
                ? aidunite_normalize_team_org_type_value($normalized['team_type'] ?? $normalized['org_type'] ?? '')
                : '';
            if ($org !== '') {
                update_post_meta($team_id, 'team_type', $org);
            }
        }
    }

    if (isset($normalized['team_status'])) {
        aidunite_team_write_status_meta($team_id, (string) $normalized['team_status']);
    }

    if (isset($normalized['payment_mode'])) {
        if (function_exists('aidunite_team_write_payment_mode_meta')) {
            aidunite_team_write_payment_mode_meta($team_id, (string) $normalized['payment_mode']);
        } elseif (function_exists('aidunite_update_team_payment_mode_meta')) {
            aidunite_update_team_payment_mode_meta($team_id, (string) $normalized['payment_mode']);
        }
    }
}

/**
 * チーム登録時のメタ一括保存（aidunite_register_team から呼ぶ）
 *
 * @param int                  $team_id
 * @param array<string, mixed> $team_data
 * @return void
 */
function aidunite_team_persist_register_meta($team_id, array $team_data) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return;
    }

    $text_fields = [
        'team_name',
        'team_name_kana',
        'team_description',
        'team_achievements',
        'sport_type',
        'team_category',
        'region',
        'team_place',
        'team_logo',
        'registrant_name',
        'contact_mail',
        'contact_phone',
    ];

    foreach ($text_fields as $field) {
        if (!isset($team_data[$field])) {
            continue;
        }
        update_post_meta($team_id, $field, sanitize_text_field((string) $team_data[$field]));
    }

    aidunite_team_write_canonical_meta($team_id, $team_data);

    if (!isset($team_data['team_status']) || (string) $team_data['team_status'] === '') {
        aidunite_team_write_status_meta($team_id, 'pending');
    }
}

/**
 * 設定ダッシュボード保存用（テキスト + canonical）
 *
 * @param int                  $team_id
 * @param array<string, mixed> $fields
 * @return void
 */
function aidunite_team_persist_settings_meta($team_id, array $fields) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return;
    }

    $map = [
        'team_name',
        'team_name_kana',
        'team_description',
        'sport_type',
        'team_category',
        'team_place',
        'team_logo',
        'registrant_name',
        'contact_mail',
        'contact_phone',
    ];
    foreach ($map as $key) {
        if (!array_key_exists($key, $fields)) {
            continue;
        }
        update_post_meta($team_id, $key, sanitize_text_field((string) $fields[$key]));
    }

    aidunite_team_write_canonical_meta($team_id, $fields);
}

/**
 * @param int $team_id
 * @return array<string, mixed>
 */
function aidunite_team_get_canonical_meta($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0 || get_post_type($team_id) !== 'team') {
        return [];
    }

    $gender_raw = (string) get_post_meta($team_id, 'team_gender_option', true);
    $gender = function_exists('aidunite_normalize_team_gender_option')
        ? aidunite_normalize_team_gender_option($gender_raw)
        : $gender_raw;

    $type_raw = (string) get_post_meta($team_id, 'team_type', true);
    $type = function_exists('aidunite_normalize_team_org_type_value')
        ? aidunite_normalize_team_org_type_value($type_raw)
        : $type_raw;

    $status_raw = (string) get_post_meta($team_id, 'team_status', true);
    $status = $status_raw;
    if (function_exists('aidunite_team_management_normalize_team_status')) {
        $status = aidunite_team_management_normalize_team_status($status_raw);
    }

    return [
        'team_id' => $team_id,
        'post_status' => (string) get_post_status($team_id),
        'team_gender_option' => $gender,
        'team_gender_option_raw' => $gender_raw,
        'team_type' => $type,
        'team_type_raw' => $type_raw,
        'team_status' => $status,
        'team_status_raw' => $status_raw,
        'payment_mode' => (string) get_post_meta($team_id, 'payment_mode', true),
    ];
}
