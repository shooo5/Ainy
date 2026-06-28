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
        if ($field === 'team_logo') {
            update_post_meta($team_id, $field, function_exists('aidunite_team_logo_normalize_storage_url')
                ? aidunite_team_logo_normalize_storage_url((string) $team_data[$field])
                : esc_url_raw((string) $team_data[$field]));
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
 * 承認済み代表者 UID（二重昇格防止）
 *
 * @param int $team_id
 * @param int $user_id
 */
function aidunite_team_write_applicant_promoted_uid($team_id, $user_id) {
    $team_id = (int) $team_id;
    $user_id = (int) $user_id;
    if ($team_id < 1 || $user_id < 1) {
        return;
    }
    update_post_meta($team_id, 'aidunite_team_applicant_promoted_uid', (string) $user_id);
}

/**
 * 表示・設定画面用 read payload（postmeta 直読みは persist-read 経由）
 *
 * @param int $team_id
 * @return array<string, mixed>
 */
function aidunite_team_get_display_bundle($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0 || get_post_type($team_id) !== 'team') {
        return [];
    }

    $post = get_post($team_id);
    $canonical = aidunite_team_get_canonical_meta($team_id);

    $team_name = (string) get_post_meta($team_id, 'team_name', true);
    if ($team_name === '' && $post) {
        $team_name = (string) $post->post_title;
    }

    $sport_type = (string) get_post_meta($team_id, 'sport_type', true);
    $team_sport = (string) (get_post_meta($team_id, 'team_sport', true) ?: $sport_type);
    $team_category = (string) get_post_meta($team_id, 'team_category', true);
    $team_type = (string) ($canonical['team_type'] ?? get_post_meta($team_id, 'team_type', true));
    $team_gender_option = (string) ($canonical['team_gender_option'] ?? '');

    $region = function_exists('aidunite_team_activity_display_label')
        ? (string) aidunite_team_activity_display_label($team_id)
        : (string) get_post_meta($team_id, 'region', true);
    if ($region === '' || $region === '地域未設定') {
        $legacy = (string) get_post_meta($team_id, 'team_location', true);
        if ($legacy !== '') {
            $region = $legacy;
        }
    }

    $team_place = (string) get_post_meta($team_id, 'team_place', true);
    if ($team_place === '') {
        $team_place = (string) get_post_meta($team_id, 'team_location', true);
    }

    $contact_mail = (string) get_post_meta($team_id, 'contact_mail', true);
    if ($contact_mail === '') {
        $contact_mail = (string) get_post_meta($team_id, 'team_contact', true);
    }

    $venue_name_history = get_post_meta($team_id, 'venue_name_history', true);
    if (!is_array($venue_name_history)) {
        $venue_name_history = [];
    }

    $onboarding_bot_mr_id = 0;
    if (defined('AIDUNITE_TEAM_META_ONBOARDING_BOT_MR')) {
        $onboarding_bot_mr_id = (int) get_post_meta($team_id, AIDUNITE_TEAM_META_ONBOARDING_BOT_MR, true);
    }

    $team_description = (string) get_post_meta($team_id, 'team_description', true);
    if ($team_description === '' && $post) {
        $team_description = (string) $post->post_content;
    }

    $accepting_raw = (string) get_post_meta($team_id, 'accepting_applications', true);
    $legacy_description = (string) get_post_meta($team_id, 'description', true);

    return array_merge($canonical, [
        'team_name' => $team_name,
        'team_name_kana' => (string) get_post_meta($team_id, 'team_name_kana', true),
        'sport_type' => $sport_type,
        'team_sport' => $team_sport,
        'team_category' => $team_category,
        'team_type' => $team_type,
        'team_gender_option' => $team_gender_option,
        'region' => $region,
        'team_place' => $team_place,
        'team_location' => (string) get_post_meta($team_id, 'team_location', true),
        'team_area' => (string) get_post_meta($team_id, 'team_area', true),
        'team_level' => (string) get_post_meta($team_id, 'team_level', true),
        'team_logo' => (string) get_post_meta($team_id, 'team_logo', true),
        'team_description' => $team_description,
        'team_achievements' => (string) get_post_meta($team_id, 'team_achievements', true),
        'registrant_name' => (string) get_post_meta($team_id, 'registrant_name', true),
        'contact_mail' => $contact_mail,
        'contact_phone' => (string) get_post_meta($team_id, 'contact_phone', true),
        'home_venue_name' => (string) get_post_meta($team_id, 'home_venue_name', true),
        'venue_name_history' => $venue_name_history,
        'onboarding_bot_mr_id' => $onboarding_bot_mr_id,
        'accepting_applications' => $accepting_raw,
        'secret_code' => (string) get_post_meta($team_id, 'secret_code', true),
        'description_legacy' => $legacy_description,
    ]);
}

/**
 * マッチ詳細カード用（page-match-detail）
 *
 * @param int $team_id
 * @return array<string, string>
 */
function aidunite_team_get_match_card_profile($team_id) {
    $bundle = aidunite_team_get_display_bundle($team_id);
    if ($bundle === []) {
        return [];
    }

    $default_logo = get_template_directory_uri() . '/images/default-team-logo.png';

    return [
        'name' => ($bundle['team_name'] ?? '') !== '' ? (string) $bundle['team_name'] : 'チーム名未設定',
        'sport' => ($bundle['team_sport'] ?? '') !== '' ? (string) $bundle['team_sport'] : 'スポーツ種目未設定',
        'category' => ($bundle['team_category'] ?? '') !== '' ? (string) $bundle['team_category'] : 'カテゴリ未設定',
        'region' => ($bundle['region'] ?? '') !== '' ? (string) $bundle['region'] : '地域未設定',
        'logo' => ($bundle['team_logo'] ?? '') !== '' ? (string) $bundle['team_logo'] : $default_logo,
        'description' => (string) ($bundle['team_description'] ?? ''),
        'achievements' => (string) ($bundle['team_achievements'] ?? ''),
        'approver_school_name' => '',
        'approver_name' => '',
    ];
}

/**
 * /team-settings フォーム初期値
 *
 * @param int $team_id
 * @return array<string, mixed>
 */
function aidunite_team_get_settings_display($team_id) {
    return aidunite_team_get_display_bundle($team_id);
}
