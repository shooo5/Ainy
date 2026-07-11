<?php
/**
 * match_feedback CPT 読取正本（REST 用）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param int $feedback_id
 * @return array<string, mixed>
 */
function aidunite_match_feedback_get_display_meta($feedback_id) {
    $feedback_id = (int) $feedback_id;
    if ($feedback_id <= 0 || get_post_type($feedback_id) !== 'match_feedback') {
        return [];
    }

    return [
        'feedback_id' => $feedback_id,
        'match_id' => (int) get_post_meta($feedback_id, 'match_id', true),
        'team_id' => (int) get_post_meta($feedback_id, 'team_id', true),
        'satisfaction' => (string) get_post_meta($feedback_id, 'satisfaction', true),
        'opponent_rating' => (string) get_post_meta($feedback_id, 'opponent_rating', true),
        'venue_rating' => (string) get_post_meta($feedback_id, 'venue_rating', true),
        'rematch_interest' => (string) get_post_meta($feedback_id, 'rematch_interest', true),
        'created_at' => (string) get_post_meta($feedback_id, 'created_at', true),
    ];
}

/**
 * チーム×マッチの回答済みか
 *
 * @param int $match_id
 * @param int $team_id
 * @return bool
 */
function aidunite_match_feedback_read_exists($match_id, $team_id) {
    $match_id = (int) $match_id;
    $team_id = (int) $team_id;
    if ($match_id <= 0 || $team_id <= 0) {
        return false;
    }

    $found = get_posts([
        'post_type' => 'match_feedback',
        'post_status' => 'any',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'meta_query' => [
            'relation' => 'AND',
            [
                'key' => 'match_id',
                'value' => $match_id,
                'compare' => '=',
            ],
            [
                'key' => 'team_id',
                'value' => $team_id,
                'compare' => '=',
            ],
        ],
    ]);

    return !empty($found);
}

/**
 * チームスコープの feedback 投稿を取得
 *
 * @param int[] $team_ids
 * @param int   $match_id 0 なら全マッチ
 * @return WP_Post[]
 */
function aidunite_match_feedback_read_posts_for_teams(array $team_ids, $match_id = 0) {
    $team_ids = array_values(array_filter(array_map('intval', $team_ids)));
    if ($team_ids === []) {
        return [];
    }

    $team_meta = count($team_ids) === 1
        ? [
            'key' => 'team_id',
            'value' => $team_ids[0],
            'compare' => '=',
        ]
        : [
            'key' => 'team_id',
            'value' => $team_ids,
            'compare' => 'IN',
        ];

    $meta_query = [
        'relation' => 'AND',
        $team_meta,
    ];

    $match_id = (int) $match_id;
    if ($match_id > 0) {
        $meta_query[] = [
            'key' => 'match_id',
            'value' => $match_id,
            'compare' => '=',
        ];
    }

    return get_posts([
        'post_type' => 'match_feedback',
        'post_status' => 'any',
        'posts_per_page' => -1,
        'meta_query' => $meta_query,
    ]);
}

/**
 * REST GET レスポンス1行
 *
 * @param int $feedback_id
 * @return array<string, mixed>
 */
function aidunite_match_feedback_format_rest_row($feedback_id) {
    $meta = aidunite_match_feedback_get_display_meta($feedback_id);
    if ($meta === []) {
        return [];
    }

    return [
        'id' => (int) ($meta['feedback_id'] ?? 0),
        'match_id' => (int) ($meta['match_id'] ?? 0),
        'team_id' => (int) ($meta['team_id'] ?? 0),
        'satisfaction' => (string) ($meta['satisfaction'] ?? ''),
        'opponent_rating' => (string) ($meta['opponent_rating'] ?? ''),
        'venue_rating' => (string) ($meta['venue_rating'] ?? ''),
        'rematch_interest' => (string) ($meta['rematch_interest'] ?? ''),
        'created_at' => (string) ($meta['created_at'] ?? ''),
    ];
}
