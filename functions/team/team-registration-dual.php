<?php
/**
 * 男女同時チーム申請（1ウィザード・2 team 投稿・管理者承認は各1回）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('AIDUNITE_TEAM_META_PAIRED_TEAM_ID')) {
    define('AIDUNITE_TEAM_META_PAIRED_TEAM_ID', 'aidunite_paired_team_id');
}

if (!defined('AIDUNITE_TEAM_META_APPLICATION_BATCH_ID')) {
    define('AIDUNITE_TEAM_META_APPLICATION_BATCH_ID', 'aidunite_application_batch_id');
}

/**
 * @param int $user_id
 * @return int[]
 */
function aidunite_get_user_pending_application_team_ids($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return [];
    }

    $ids = [];
    $anchor = (int) get_user_meta($user_id, 'pending_team_id', true);
    if ($anchor > 0) {
        $post = get_post($anchor);
        if ($post
            && $post->post_type === 'team'
            && $post->post_status === 'pending'
            && (int) $post->post_author === $user_id
        ) {
            $ids[] = $anchor;
            $paired = (int) get_post_meta($anchor, AIDUNITE_TEAM_META_PAIRED_TEAM_ID, true);
            if ($paired > 0 && $paired !== $anchor) {
                $ids[] = $paired;
            }
        }
    }

    $author_pending = get_posts([
        'post_type'      => 'team',
        'post_status'    => 'pending',
        'author'         => $user_id,
        'posts_per_page' => 10,
        'fields'         => 'ids',
        'orderby'        => 'date',
        'order'          => 'ASC',
    ]);
    foreach ($author_pending as $tid) {
        $tid = (int) $tid;
        if ($tid > 0 && !in_array($tid, $ids, true)) {
            $ids[] = $tid;
        }
    }

    $ids = array_values(array_unique(array_filter($ids)));
    sort($ids);

    return $ids;
}

/**
 * @param int $user_id
 * @return bool
 */
function aidunite_user_has_pending_team_application($user_id) {
    return aidunite_get_user_pending_application_team_ids((int) $user_id) !== [];
}

/**
 * @param int    $team_id_a
 * @param int    $team_id_b
 * @param string $batch_id
 */
function aidunite_link_paired_team_applications($team_id_a, $team_id_b, $batch_id) {
    $team_id_a = (int) $team_id_a;
    $team_id_b = (int) $team_id_b;
    $batch_id  = sanitize_text_field((string) $batch_id);
    if ($team_id_a <= 0 || $team_id_b <= 0 || $batch_id === '') {
        return;
    }
    update_post_meta($team_id_a, AIDUNITE_TEAM_META_PAIRED_TEAM_ID, $team_id_b);
    update_post_meta($team_id_b, AIDUNITE_TEAM_META_PAIRED_TEAM_ID, $team_id_a);
    update_post_meta($team_id_a, AIDUNITE_TEAM_META_APPLICATION_BATCH_ID, $batch_id);
    update_post_meta($team_id_b, AIDUNITE_TEAM_META_APPLICATION_BATCH_ID, $batch_id);
}

/**
 * 申請 POST から単一 team 用データ配列を組み立てる。
 *
 * @param array<string, mixed> $post $_POST 相当
 * @param string               $gender male|female
 * @return array<string, mixed>
 */
function aidunite_build_team_registration_data_from_post(array $post, $gender) {
    $gender = function_exists('aidunite_normalize_team_gender_option')
        ? aidunite_normalize_team_gender_option($gender)
        : sanitize_key($gender);

    $base_name = sanitize_text_field($post['team_name_base'] ?? $post['team_name'] ?? '');
    $explicit  = sanitize_text_field($post['team_' . $gender . '_name'] ?? '');
    $team_name = $explicit !== '' ? $explicit : $base_name;

    $kana_base = sanitize_text_field($post['team_name_kana'] ?? '');
    $kana_explicit = sanitize_text_field($post['team_' . $gender . '_name_kana'] ?? '');
    $team_name_kana = $kana_explicit !== '' ? $kana_explicit : $kana_base;

    $logo_key = 'team_' . $gender . '_logo';
    $logo     = function_exists('aidunite_team_logo_normalize_storage_url')
        ? aidunite_team_logo_normalize_storage_url(wp_unslash($post[$logo_key] ?? $post['team_logo'] ?? ''))
        : esc_url_raw(wp_unslash($post[$logo_key] ?? $post['team_logo'] ?? ''));

    $current_user = wp_get_current_user();
    $rep_name     = sanitize_text_field(wp_unslash($post['representative_name'] ?? ''));
    $rep_email    = sanitize_email(wp_unslash($post['representative_email'] ?? ''));
    $rep_phone    = sanitize_text_field(wp_unslash($post['representative_phone'] ?? ''));
    if ($current_user && $current_user->ID > 0) {
        if ($rep_name === '') {
            $rep_name = $current_user->display_name;
        }
        if ($rep_email === '') {
            $rep_email = $current_user->user_email;
        }
    }

    $desc_key = 'team_' . $gender . '_description';

    return [
        'team_name'          => $team_name,
        'team_name_kana'     => $team_name_kana,
        'team_description'   => sanitize_textarea_field(wp_unslash($post[$desc_key] ?? $post['team_description'] ?? '')),
        'sport_type'         => sanitize_text_field($post['sport_type'] ?? ''),
        'team_category'      => sanitize_text_field($post['team_category'] ?? ''),
        'team_type'          => sanitize_text_field($post['team_type'] ?? ''),
        'team_gender_option' => $gender,
        'region'             => sanitize_text_field($post['activity_prefecture'] ?? ''),
        'team_place'         => sanitize_text_field($post['team_place'] ?? ''),
        'team_logo'          => $logo,
        'registrant_name'    => $rep_name,
        'contact_mail'       => $rep_email,
        'contact_phone'      => $rep_phone,
    ];
}

/**
 * team 投稿に活動地域・URL・ロゴ crop を保存（申請 Ajax 共通）。
 *
 * @param int                  $team_id
 * @param array<string, mixed> $post
 * @param string               $gender_prefix male|female|''（単一は空）
 */
function aidunite_save_team_registration_post_meta_from_request($team_id, array $post, $gender_prefix = '') {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return;
    }

    if (function_exists('aidunite_team_activity_save_meta')) {
        aidunite_team_activity_save_meta($team_id, [
            'activity_prefecture' => wp_unslash($post['activity_prefecture'] ?? ''),
            'activity_area_type'  => wp_unslash($post['activity_area_type'] ?? ''),
            'activity_area_ward'  => wp_unslash($post['activity_area_ward'] ?? ''),
            'activity_area_city'  => wp_unslash($post['activity_area_city'] ?? ''),
            'activity_area_sync'  => wp_unslash($post['activity_area_sync'] ?? ''),
            'activity_area'       => wp_unslash($post['activity_area'] ?? ''),
        ]);
    }

    $website = esc_url_raw(wp_unslash($post['team_website'] ?? ''));
    $sns     = esc_url_raw(wp_unslash($post['team_sns_url'] ?? ''));
    if ($website !== '') {
        update_post_meta($team_id, 'team_website', $website);
    }
    if ($sns !== '') {
        update_post_meta($team_id, 'team_sns_url', $sns);
    }

    $prefix = $gender_prefix !== '' ? 'team_' . $gender_prefix . '_' : 'team_';
    $logo   = function_exists('aidunite_team_logo_normalize_storage_url')
        ? aidunite_team_logo_normalize_storage_url(wp_unslash($post[$prefix . 'logo'] ?? $post['team_logo'] ?? ''))
        : esc_url_raw(wp_unslash($post[$prefix . 'logo'] ?? $post['team_logo'] ?? ''));
    if ($logo !== '') {
        update_post_meta($team_id, 'team_logo', $logo);
        if (function_exists('aidunite_save_team_logo_crop_meta')) {
            aidunite_save_team_logo_crop_meta(
                $team_id,
                wp_unslash($post[$prefix . 'logo_offset_x'] ?? $post['team_logo_offset_x'] ?? 0),
                wp_unslash($post[$prefix . 'logo_offset_y'] ?? $post['team_logo_offset_y'] ?? 0),
                wp_unslash($post[$prefix . 'logo_zoom'] ?? $post['team_logo_zoom'] ?? 100)
            );
        }
    }

    $team_type = sanitize_text_field($post['team_type'] ?? '');
    if ($team_type !== '' && function_exists('aidunite_set_team_type')) {
        aidunite_set_team_type($team_id, $team_type);
    }
}

/**
 * 男女同時申請: team を2件作成。失敗時はロールバック。
 *
 * @param int                  $user_id
 * @param array<string, mixed> $post
 * @return array{male_id:int,female_id:int}|\WP_Error
 */
function aidunite_register_dual_gender_teams($user_id, array $post) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return new WP_Error('invalid_user', 'ログインが必要です。');
    }

    $male_data   = aidunite_build_team_registration_data_from_post($post, 'male');
    $female_data = aidunite_build_team_registration_data_from_post($post, 'female');

    foreach (['male' => $male_data, 'female' => $female_data] as $label => $data) {
        $g = (string) ($data['team_gender_option'] ?? '');
        if (!in_array($g, ['male', 'female'], true)) {
            return new WP_Error('invalid_gender', '性別の指定が不正です。');
        }
        if (trim((string) ($data['team_name'] ?? '')) === '') {
            return new WP_Error('missing_name', $label === 'male' ? '男子チーム名を入力してください。' : '女子チーム名を入力してください。');
        }
    }

    $batch_id = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('batch_', true);

    $male_id = aidunite_register_team($user_id, $male_data, [
        'skip_pending_limit'   => false,
        'set_pending_team_id'  => true,
    ]);
    if (!$male_id) {
        $detail = function_exists('aidunite_register_team_take_last_error')
            ? aidunite_register_team_take_last_error()
            : '';
        return new WP_Error('register_failed', $detail !== '' ? $detail : '男子チームの申請に失敗しました。');
    }

    aidunite_save_team_registration_post_meta_from_request($male_id, $post, 'male');

    $female_id = aidunite_register_team($user_id, $female_data, [
        'skip_pending_limit'   => true,
        'set_pending_team_id'  => false,
    ]);
    if (!$female_id) {
        wp_delete_post((int) $male_id, true);
        $pend = (int) get_user_meta($user_id, 'pending_team_id', true);
        if ($pend === (int) $male_id) {
            delete_user_meta($user_id, 'pending_team_id');
        }
        $detail = function_exists('aidunite_register_team_take_last_error')
            ? aidunite_register_team_take_last_error()
            : '';
        return new WP_Error('register_failed', $detail !== '' ? $detail : '女子チームの申請に失敗しました。');
    }

    aidunite_save_team_registration_post_meta_from_request($female_id, $post, 'female');
    aidunite_link_paired_team_applications($male_id, $female_id, $batch_id);

    return [
        'male_id'   => (int) $male_id,
        'female_id' => (int) $female_id,
    ];
}

/**
 * 承認待ちマイページ・完了画面用: 複数 team の review context。
 *
 * @param int|null $user_id
 * @return array{teams:array<int,array<string,mixed>>,submitted_at:string,user_email:string}
 */
function aidunite_mypage_pending_application_teams_context($user_id = null) {
    $user_id = $user_id === null ? get_current_user_id() : (int) $user_id;
    $out     = [
        'teams'        => [],
        'submitted_at' => '',
        'user_email'   => '',
    ];

    if ($user_id <= 0) {
        return $out;
    }

    $user = wp_get_current_user();
    if ($user && $user->ID === $user_id) {
        $out['user_email'] = (string) $user->user_email;
    }

    if (!function_exists('aidunite_get_team_application_review_context')) {
        return $out;
    }

    foreach (aidunite_get_user_pending_application_team_ids($user_id) as $team_id) {
        $ctx = aidunite_get_team_application_review_context((int) $team_id);
        if ($ctx === []) {
            continue;
        }
        $out['teams'][] = $ctx;
        if ($out['submitted_at'] === '' && !empty($ctx['submitted_at'])) {
            $out['submitted_at'] = (string) $ctx['submitted_at'];
        }
    }

    return $out;
}

/**
 * team_leader 向け: 承認済みがあり、別 team がまだ pending の一覧。
 *
 * @param int $user_id
 * @return array<int, array<string, mixed>>
 */
function aidunite_get_pending_sibling_teams_for_leader($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0 || !function_exists('aidunite_get_team_application_review_context')) {
        return [];
    }

    $managed = function_exists('aidunite_get_managed_team_ids')
        ? aidunite_get_managed_team_ids($user_id)
        : [];
    if ($managed === []) {
        return [];
    }

    $pending_ids = aidunite_get_user_pending_application_team_ids($user_id);
    $items       = [];
    foreach ($pending_ids as $team_id) {
        $team_id = (int) $team_id;
        if ($team_id <= 0 || in_array($team_id, $managed, true)) {
            continue;
        }
        $ctx = aidunite_get_team_application_review_context($team_id);
        if ($ctx !== []) {
            $items[] = $ctx;
        }
    }

    return $items;
}
