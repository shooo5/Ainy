<?php
/**
 * チームドメイン read payload（canonical）
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
function aidunite_team_read_canonical_meta($team_id) {
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
        'team_leader_id' => (int) get_post_meta($team_id, 'team_leader_id', true),
        'team_gender_option' => $gender,
        'team_gender_option_raw' => $gender_raw,
        'team_type' => $type,
        'team_type_raw' => $type_raw,
        'team_status' => $status,
        'team_status_raw' => $status_raw,
        'payment_mode' => (string) get_post_meta($team_id, 'payment_mode', true),
    ];
}

/**
 * @param int $team_id
 * @return int
 */
function aidunite_team_read_leader_id($team_id) {
    $meta = aidunite_team_read_canonical_meta($team_id);

    return (int) ($meta['team_leader_id'] ?? 0);
}

/**
 * @param int $team_id
 * @return int
 */
function aidunite_team_read_applicant_promoted_uid($team_id) {
    return (int) get_post_meta((int) $team_id, 'aidunite_team_applicant_promoted_uid', true);
}

/**
 * 通知・承認メール用ラベル
 *
 * @param int $team_id
 * @return array{team_name:string,registrant_name:string}
 */
function aidunite_team_read_notification_labels($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return ['team_name' => '', 'registrant_name' => ''];
    }

    $team_name = (string) get_post_meta($team_id, 'team_name', true);
    if ($team_name === '') {
        $post = get_post($team_id);
        if ($post) {
            $team_name = (string) $post->post_title;
        }
    }

    return [
        'team_name' => $team_name,
        'registrant_name' => (string) get_post_meta($team_id, 'registrant_name', true),
    ];
}

/**
 * @param int $team_id
 * @return array<string, mixed>
 */
function aidunite_team_get_canonical_meta($team_id) {
    return aidunite_team_read_canonical_meta($team_id);
}
