<?php
/**
 * match_feedback CPT 保存正本
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param array<string, mixed> $raw
 * @return array<string, mixed>
 */
function aidunite_match_feedback_normalize_row(array $raw) {
    $satisfaction_reasons = $raw['satisfaction_reasons'] ?? [];
    $opponent_reasons = $raw['opponent_reasons'] ?? [];

    return [
        'match_id' => (int) ($raw['match_id'] ?? 0),
        'team_id' => (int) ($raw['team_id'] ?? 0),
        'user_id' => (int) ($raw['user_id'] ?? 0),
        'satisfaction' => (int) ($raw['satisfaction'] ?? 0),
        'satisfaction_reasons' => is_array($satisfaction_reasons) ? $satisfaction_reasons : [],
        'opponent_rating' => (int) ($raw['opponent_rating'] ?? 0),
        'opponent_reasons' => is_array($opponent_reasons) ? $opponent_reasons : [],
        'venue_rating' => (int) ($raw['venue_rating'] ?? 0),
        'venue_improvement' => sanitize_textarea_field((string) ($raw['venue_improvement'] ?? '')),
        'rematch_interest' => sanitize_text_field((string) ($raw['rematch_interest'] ?? '')),
        'rematch_reason' => sanitize_textarea_field((string) ($raw['rematch_reason'] ?? '')),
        'comment' => sanitize_textarea_field((string) ($raw['comment'] ?? '')),
        'created_at' => (string) ($raw['created_at'] ?? current_time('mysql')),
    ];
}

/**
 * @param array<string, mixed> $raw
 * @param int                  $author_user_id
 * @return array{success:bool, feedback_id:int, message?:string}
 */
function aidunite_match_feedback_persist_save(array $raw, $author_user_id) {
    $author_user_id = (int) $author_user_id;
    $row = aidunite_match_feedback_normalize_row($raw);

    if ($row['match_id'] <= 0 || $row['team_id'] <= 0 || $author_user_id <= 0) {
        return ['success' => false, 'feedback_id' => 0, 'message' => 'invalid_params'];
    }

    $feedback_post_id = wp_insert_post([
        'post_type' => 'match_feedback',
        'post_status' => 'publish',
        'post_title' => 'マッチアンケート - マッチID: ' . $row['match_id'] . ', チームID: ' . $row['team_id'],
        'post_author' => $author_user_id,
    ], true);

    if (is_wp_error($feedback_post_id)) {
        return [
            'success' => false,
            'feedback_id' => 0,
            'message' => $feedback_post_id->get_error_message(),
        ];
    }

    $feedback_post_id = (int) $feedback_post_id;
    foreach ($row as $key => $value) {
        update_post_meta($feedback_post_id, $key, $value);
    }

    return ['success' => true, 'feedback_id' => $feedback_post_id];
}
