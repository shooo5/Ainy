<?php
/**
 * 大会・イベント メタ書込正本（normalize → update_post_meta）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param mixed $raw
 * @return string
 */
function aidunite_competition_normalize_event_kind($raw) {
    $kind = sanitize_key((string) $raw);
    $allowed = ['tournament', 'cup', 'clinic', 'practice', 'league', 'friendly', 'session'];

    return in_array($kind, $allowed, true) ? $kind : 'tournament';
}

/**
 * @param mixed $raw
 * @return string
 */
function aidunite_competition_normalize_event_status($raw) {
    $status = sanitize_key((string) $raw);
    $allowed = ['draft', 'inviting', 'locked', 'in_progress', 'finished'];

    return in_array($status, $allowed, true) ? $status : 'draft';
}

/**
 * @param mixed $raw
 * @return string
 */
function aidunite_competition_normalize_entry_status($raw) {
    $status = sanitize_key((string) $raw);
    $allowed = ['invited', 'applied', 'confirmed', 'declined', 'withdrawn', 'waitlisted'];

    return in_array($status, $allowed, true) ? $status : 'invited';
}

/**
 * @param mixed $raw
 * @return string
 */
function aidunite_competition_normalize_payment_status($raw) {
    $status = sanitize_key((string) $raw);
    $allowed = ['unpaid', 'pending', 'paid', 'waived', 'refunded'];

    return in_array($status, $allowed, true) ? $status : 'unpaid';
}

/**
 * @param mixed $raw
 * @return string
 */
function aidunite_competition_normalize_approval_mode($raw) {
    $mode = sanitize_key((string) $raw);

    return in_array($mode, ['auto', 'manual'], true) ? $mode : 'auto';
}

/**
 * @param mixed $raw
 * @return string
 */
function aidunite_competition_normalize_visibility($raw) {
    $visibility = sanitize_key((string) $raw);

    return in_array($visibility, ['admin_only', 'public'], true) ? $visibility : 'admin_only';
}

/**
 * @param array<string, mixed> $marketing
 * @return array<string, mixed>
 */
function aidunite_competition_normalize_marketing_input(array $marketing) {
    $tags = $marketing['tags'] ?? [];
    if (!is_array($tags)) {
        $tags = [];
    }
    $tags = array_values(array_filter(array_map(static function ($tag) {
        return sanitize_text_field((string) $tag);
    }, $tags)));

    return [
        'slug' => sanitize_title((string) ($marketing['slug'] ?? '')),
        'cover_image' => esc_url_raw((string) ($marketing['cover_image'] ?? '')),
        'summary' => sanitize_textarea_field((string) ($marketing['summary'] ?? '')),
        'tags' => $tags,
    ];
}

/**
 * @param array<string, mixed> $raw
 * @return array<string, mixed>
 */
function aidunite_competition_normalize_refund_policy_input(array $raw) {
    return [
        'full_refund_days_before_start' => max(0, (int) ($raw['full_refund_days_before_start'] ?? 7)),
        'partial_refund_days_before_start' => max(0, (int) ($raw['partial_refund_days_before_start'] ?? 3)),
        'partial_refund_percent' => min(100, max(0, (int) ($raw['partial_refund_percent'] ?? 50))),
        'no_refund_after_deadline' => !isset($raw['no_refund_after_deadline']) || !empty($raw['no_refund_after_deadline']),
        'notes' => sanitize_textarea_field((string) ($raw['notes'] ?? '')),
    ];
}

/**
 * @param int    $event_id
 * @param string $slug
 * @param string $visibility
 */
function aidunite_competition_persist_sync_public_slug($event_id, $slug, $visibility) {
    $event_id = (int) $event_id;
    $slug = sanitize_title((string) $slug);
    $visibility = aidunite_competition_normalize_visibility($visibility);

    if ($event_id < 1 || $visibility !== 'public' || $slug === '') {
        if ($event_id > 0) {
            delete_post_meta($event_id, 'competition_public_slug');
        }

        return;
    }

    update_post_meta($event_id, 'competition_public_slug', $slug);
}

/**
 * @param array<string, mixed> $raw
 * @return array<string, mixed>
 */
function aidunite_competition_normalize_event_input(array $raw) {
    $fee_amount = isset($raw['entry_fee_amount']) ? (int) $raw['entry_fee_amount'] : null;
    $capacity = isset($raw['capacity_teams']) ? max(0, (int) $raw['capacity_teams']) : null;

    $organizer = $raw['organizer'] ?? null;
    if (!is_array($organizer)) {
        $organizer = [
            'type' => 'platform',
            'admin_user_id' => (int) get_current_user_id(),
        ];
    }

    $eligibility = $raw['eligibility'] ?? [];
    if (!is_array($eligibility)) {
        $eligibility = [];
    }

    $generator_config = $raw['generator_config'] ?? [];
    if (!is_array($generator_config)) {
        $generator_config = [];
    }

    $capabilities_override = $raw['capabilities_override'] ?? [];
    if (!is_array($capabilities_override)) {
        $capabilities_override = [];
    }

    $awards = $raw['awards'] ?? [];
    if (!is_array($awards)) {
        $awards = [];
    }

    $marketing = $raw['marketing'] ?? [];
    if (!is_array($marketing)) {
        $marketing = [];
    }
    $marketing = aidunite_competition_normalize_marketing_input($marketing);

    $refund_policy = $raw['refund_policy'] ?? [];
    if (!is_array($refund_policy)) {
        $refund_policy = [];
    }
    $refund_policy = aidunite_competition_normalize_refund_policy_input($refund_policy);

    $out = [
        'title' => sanitize_text_field((string) ($raw['title'] ?? $raw['name'] ?? '')),
        'event_kind' => aidunite_competition_normalize_event_kind($raw['event_kind'] ?? 'tournament'),
        'status' => aidunite_competition_normalize_event_status($raw['status'] ?? 'draft'),
        'date_start' => sanitize_text_field((string) ($raw['date_start'] ?? '')),
        'date_end' => sanitize_text_field((string) ($raw['date_end'] ?? $raw['date_start'] ?? '')),
        'venue_name' => sanitize_text_field((string) ($raw['venue_name'] ?? '')),
        'venue_address' => sanitize_text_field((string) ($raw['venue_address'] ?? '')),
        'application_deadline' => sanitize_text_field((string) ($raw['application_deadline'] ?? '')),
        'approval_mode' => aidunite_competition_normalize_approval_mode($raw['approval_mode'] ?? 'auto'),
        'visibility' => aidunite_competition_normalize_visibility($raw['visibility'] ?? 'admin_only'),
        'entry_fee_currency' => sanitize_text_field((string) ($raw['entry_fee_currency'] ?? 'JPY')),
        'entry_fee_required' => !empty($raw['entry_fee_required']) ? '1' : '0',
        'organizer_json' => wp_json_encode($organizer),
        'eligibility_json' => wp_json_encode($eligibility),
        'generator_config_json' => wp_json_encode($generator_config),
        'capabilities_override_json' => wp_json_encode($capabilities_override),
        'awards_json' => wp_json_encode(array_values($awards)),
        'marketing_json' => wp_json_encode($marketing),
        'refund_policy_json' => wp_json_encode($refund_policy),
    ];

    if ($fee_amount !== null) {
        $out['entry_fee_amount'] = max(0, $fee_amount);
    }
    if ($capacity !== null) {
        $out['capacity_teams'] = $capacity;
    }

    return $out;
}

/**
 * @param array<string, mixed> $raw
 * @return array<string, mixed>
 */
function aidunite_competition_normalize_entry_input(array $raw) {
    return [
        'event_id' => max(0, (int) ($raw['event_id'] ?? $raw['competition_event_id'] ?? 0)),
        'team_id' => max(0, (int) ($raw['team_id'] ?? $raw['competition_team_id'] ?? 0)),
        'status' => aidunite_competition_normalize_entry_status($raw['status'] ?? 'invited'),
        'payment_status' => aidunite_competition_normalize_payment_status($raw['payment_status'] ?? 'unpaid'),
        'calendar_schedule_id' => max(0, (int) ($raw['calendar_schedule_id'] ?? 0)),
        'group_id' => max(0, (int) ($raw['group_id'] ?? 0)),
        'seed' => max(0, (int) ($raw['seed'] ?? 0)),
        'waitlist_order' => max(0, (int) ($raw['waitlist_order'] ?? 0)),
        'stripe_checkout_session_id' => sanitize_text_field((string) ($raw['stripe_checkout_session_id'] ?? '')),
        'stripe_payment_intent_id' => sanitize_text_field((string) ($raw['stripe_payment_intent_id'] ?? '')),
    ];
}

/**
 * @param array<string, mixed> $raw
 * @return array<string, mixed>
 */
function aidunite_competition_normalize_block_input(array $raw) {
    $courts = $raw['courts'] ?? [];
    if (!is_array($courts)) {
        $courts = [];
    }
    $courts = array_values(array_filter(array_map(static function ($court) {
        return sanitize_text_field((string) $court);
    }, $courts)));

    return [
        'event_id' => max(0, (int) ($raw['event_id'] ?? $raw['competition_event_id'] ?? 0)),
        'block_date' => sanitize_text_field((string) ($raw['block_date'] ?? $raw['date'] ?? '')),
        'venue_name' => sanitize_text_field((string) ($raw['venue_name'] ?? '')),
        'courts_json' => wp_json_encode($courts),
        'start_time' => sanitize_text_field((string) ($raw['start_time'] ?? '09:00')),
        'end_time' => sanitize_text_field((string) ($raw['end_time'] ?? '17:00')),
        'notes' => sanitize_textarea_field((string) ($raw['notes'] ?? '')),
    ];
}

/**
 * @param int                  $post_id
 * @param array<string, mixed> $data
 */
function aidunite_competition_persist_write_event_meta($post_id, array $data) {
    $post_id = (int) $post_id;
    if ($post_id < 1) {
        return;
    }

    $map = [
        'competition_status' => 'status',
        'competition_event_kind' => 'event_kind',
        'competition_date_start' => 'date_start',
        'competition_date_end' => 'date_end',
        'competition_venue_name' => 'venue_name',
        'competition_venue_address' => 'venue_address',
        'competition_capacity_teams' => 'capacity_teams',
        'competition_application_deadline' => 'application_deadline',
        'competition_entry_fee_amount' => 'entry_fee_amount',
        'competition_entry_fee_currency' => 'entry_fee_currency',
        'competition_entry_fee_required' => 'entry_fee_required',
        'competition_approval_mode' => 'approval_mode',
        'competition_eligibility_json' => 'eligibility_json',
        'competition_organizer_json' => 'organizer_json',
        'competition_visibility' => 'visibility',
        'competition_generator_config_json' => 'generator_config_json',
        'competition_capabilities_override_json' => 'capabilities_override_json',
        'competition_awards_json' => 'awards_json',
        'competition_marketing_json' => 'marketing_json',
        'competition_refund_policy_json' => 'refund_policy_json',
    ];

    foreach ($map as $meta_key => $data_key) {
        if (!array_key_exists($data_key, $data)) {
            continue;
        }
        $value = $data[$data_key];
        if (is_scalar($value)) {
            update_post_meta($post_id, $meta_key, (string) $value);
        }
    }
}

/**
 * @param int                  $post_id 0 = 新規
 * @param array<string, mixed> $data
 * @return int|\WP_Error
 */
function aidunite_competition_persist_save_event($post_id, array $data) {
    $post_id = (int) $post_id;
    $title = (string) ($data['title'] ?? '');
    if ($title === '') {
        return new WP_Error('invalid_params', 'イベント名が必要です');
    }

    $post_args = [
        'post_type' => 'competition_event',
        'post_title' => $title,
        'post_status' => 'publish',
    ];

    if ($post_id > 0) {
        $post_args['ID'] = $post_id;
        $saved = wp_update_post($post_args, true);
    } else {
        $saved = wp_insert_post($post_args, true);
    }

    if (is_wp_error($saved) || !$saved) {
        return is_wp_error($saved)
            ? $saved
            : new WP_Error('save_failed', 'イベントの保存に失敗しました');
    }

    $saved = (int) $saved;
    aidunite_competition_persist_write_event_meta($saved, $data);

    $raw = aidunite_competition_read_event_meta_raw($saved);
    $marketing = is_array($raw['marketing'] ?? null) ? $raw['marketing'] : [];
    $slug = (string) ($marketing['slug'] ?? '');
    aidunite_competition_persist_sync_public_slug(
        $saved,
        $slug,
        (string) ($raw['visibility'] ?? 'admin_only')
    );

    return $saved;
}

/**
 * @param int                  $post_id
 * @param array<string, mixed> $data
 */
function aidunite_competition_persist_write_entry_meta($post_id, array $data) {
    $post_id = (int) $post_id;
    if ($post_id < 1) {
        return;
    }

    $map = [
        'competition_event_id' => 'event_id',
        'competition_team_id' => 'team_id',
        'competition_entry_status' => 'status',
        'competition_payment_status' => 'payment_status',
        'competition_calendar_schedule_id' => 'calendar_schedule_id',
        'competition_group_id' => 'group_id',
        'competition_seed' => 'seed',
        'competition_waitlist_order' => 'waitlist_order',
        'competition_stripe_checkout_session_id' => 'stripe_checkout_session_id',
        'competition_stripe_payment_intent_id' => 'stripe_payment_intent_id',
        'competition_refunded_at' => 'refunded_at',
        'competition_refund_reason' => 'refund_reason',
        'competition_stripe_refund_id' => 'stripe_refund_id',
    ];

    foreach ($map as $meta_key => $data_key) {
        if (!array_key_exists($data_key, $data)) {
            continue;
        }
        update_post_meta($post_id, $meta_key, (string) $data[$data_key]);
    }

    if (!empty($data['responded_at'])) {
        update_post_meta($post_id, 'competition_responded_at', (string) $data['responded_at']);
    }
    if (!empty($data['confirmed_at'])) {
        update_post_meta($post_id, 'competition_confirmed_at', (string) $data['confirmed_at']);
    }
}

/**
 * @param int                  $post_id 0 = 新規
 * @param array<string, mixed> $data
 * @return int|\WP_Error
 */
function aidunite_competition_persist_save_entry($post_id, array $data) {
    $post_id = (int) $post_id;
    $event_id = (int) ($data['event_id'] ?? 0);
    $team_id = (int) ($data['team_id'] ?? 0);
    if ($event_id < 1 || $team_id < 1) {
        return new WP_Error('invalid_params', 'event_id と team_id が必要です');
    }

    $title = (string) ($data['title'] ?? '');
    if ($title === '') {
        $event_title = get_the_title($event_id);
        $team_name = function_exists('aidunite_get_team_name')
            ? (string) aidunite_get_team_name($team_id)
            : (string) get_the_title($team_id);
        $title = trim($event_title . ' — ' . $team_name);
    }

    $post_args = [
        'post_type' => 'competition_entry',
        'post_title' => $title,
        'post_status' => 'publish',
    ];

    if ($post_id > 0) {
        $post_args['ID'] = $post_id;
        $saved = wp_update_post($post_args, true);
    } else {
        $saved = wp_insert_post($post_args, true);
    }

    if (is_wp_error($saved) || !$saved) {
        return is_wp_error($saved)
            ? $saved
            : new WP_Error('save_failed', '参加エントリの保存に失敗しました');
    }

    $saved = (int) $saved;
    aidunite_competition_persist_write_entry_meta($saved, $data);

    return $saved;
}

/**
 * @param int                  $post_id
 * @param array<string, mixed> $data
 */
function aidunite_competition_persist_write_block_meta($post_id, array $data) {
    $post_id = (int) $post_id;
    if ($post_id < 1) {
        return;
    }

    $map = [
        'competition_event_id' => 'event_id',
        'competition_block_date' => 'block_date',
        'competition_block_venue_name' => 'venue_name',
        'competition_block_courts_json' => 'courts_json',
        'competition_block_start_time' => 'start_time',
        'competition_block_end_time' => 'end_time',
        'competition_block_notes' => 'notes',
    ];

    foreach ($map as $meta_key => $data_key) {
        if (!array_key_exists($data_key, $data)) {
            continue;
        }
        update_post_meta($post_id, $meta_key, (string) $data[$data_key]);
    }
}

/**
 * @param int                  $post_id 0 = 新規
 * @param array<string, mixed> $data
 * @return int|\WP_Error
 */
function aidunite_competition_persist_save_block($post_id, array $data) {
    $post_id = (int) $post_id;
    $event_id = (int) ($data['event_id'] ?? 0);
    $block_date = (string) ($data['block_date'] ?? '');
    if ($event_id < 1 || $block_date === '') {
        return new WP_Error('invalid_params', 'event_id と block_date が必要です');
    }

    $title = (string) ($data['title'] ?? '');
    if ($title === '') {
        $title = get_the_title($event_id) . ' — ' . $block_date;
    }

    $post_args = [
        'post_type' => 'competition_block',
        'post_title' => $title,
        'post_status' => 'publish',
    ];

    if ($post_id > 0) {
        $post_args['ID'] = $post_id;
        $saved = wp_update_post($post_args, true);
    } else {
        $saved = wp_insert_post($post_args, true);
    }

    if (is_wp_error($saved) || !$saved) {
        return is_wp_error($saved)
            ? $saved
            : new WP_Error('save_failed', 'スケジュールブロックの保存に失敗しました');
    }

    $saved = (int) $saved;
    aidunite_competition_persist_write_block_meta($saved, $data);

    return $saved;
}

/**
 * @param int $event_id
 * @param int $team_id
 * @return int
 */
function aidunite_competition_persist_find_entry_id($event_id, $team_id) {
    $event_id = (int) $event_id;
    $team_id = (int) $team_id;
    if ($event_id < 1 || $team_id < 1) {
        return 0;
    }

    $posts = get_posts([
        'post_type' => 'competition_entry',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'meta_query' => [
            'relation' => 'AND',
            [
                'key' => 'competition_event_id',
                'value' => (string) $event_id,
                'compare' => '=',
            ],
            [
                'key' => 'competition_team_id',
                'value' => (string) $team_id,
                'compare' => '=',
            ],
        ],
    ]);

    return !empty($posts[0]) ? (int) $posts[0] : 0;
}

/**
 * @param mixed $raw
 * @return string
 */
function aidunite_competition_normalize_fixture_status($raw) {
    $status = sanitize_key((string) $raw);
    $allowed = ['scheduled', 'finished', 'cancelled', 'walkover'];

    return in_array($status, $allowed, true) ? $status : 'scheduled';
}

/**
 * @param mixed $raw
 * @return string
 */
function aidunite_competition_normalize_result_source($raw) {
    $source = sanitize_key((string) $raw);
    $allowed = ['admin', 'referee', 'team'];

    return in_array($source, $allowed, true) ? $source : 'admin';
}

/**
 * @param array<string, mixed> $raw
 * @return array<string, mixed>
 */
function aidunite_competition_normalize_fixture_input(array $raw) {
    return [
        'event_id' => max(0, (int) ($raw['event_id'] ?? $raw['competition_event_id'] ?? 0)),
        'stage_id' => max(0, (int) ($raw['stage_id'] ?? 0)),
        'group_id' => max(0, (int) ($raw['group_id'] ?? 0)),
        'team_a_id' => max(0, (int) ($raw['team_a_id'] ?? 0)),
        'team_b_id' => max(0, (int) ($raw['team_b_id'] ?? 0)),
        'scheduled_at' => sanitize_text_field((string) ($raw['scheduled_at'] ?? '')),
        'court_label' => sanitize_text_field((string) ($raw['court_label'] ?? '')),
        'status' => aidunite_competition_normalize_fixture_status($raw['status'] ?? 'scheduled'),
        'score_a' => isset($raw['score_a']) ? (int) $raw['score_a'] : null,
        'score_b' => isset($raw['score_b']) ? (int) $raw['score_b'] : null,
        'result_source' => aidunite_competition_normalize_result_source($raw['result_source'] ?? 'admin'),
        'round' => max(0, (int) ($raw['round'] ?? 0)),
        'match_index' => max(0, (int) ($raw['match_index'] ?? 0)),
        'winner_advances_to' => max(0, (int) ($raw['winner_advances_to'] ?? 0)),
        'winner_slot' => sanitize_key((string) ($raw['winner_slot'] ?? '')),
    ];
}

/**
 * @param int                  $post_id
 * @param array<string, mixed> $data
 */
function aidunite_competition_persist_write_fixture_meta($post_id, array $data) {
    $post_id = (int) $post_id;
    if ($post_id < 1) {
        return;
    }

    $map = [
        'competition_event_id' => 'event_id',
        'competition_stage_id' => 'stage_id',
        'competition_group_id' => 'group_id',
        'competition_team_a_id' => 'team_a_id',
        'competition_team_b_id' => 'team_b_id',
        'competition_fixture_scheduled_at' => 'scheduled_at',
        'competition_court_label' => 'court_label',
        'competition_fixture_status' => 'status',
        'competition_result_source' => 'result_source',
        'competition_fixture_round' => 'round',
        'competition_fixture_match_index' => 'match_index',
        'competition_fixture_winner_advances_to' => 'winner_advances_to',
        'competition_fixture_winner_slot' => 'winner_slot',
    ];

    foreach ($map as $meta_key => $data_key) {
        if (!array_key_exists($data_key, $data)) {
            continue;
        }
        $value = $data[$data_key];
        if ($value === null) {
            continue;
        }
        update_post_meta($post_id, $meta_key, is_scalar($value) ? (string) $value : '');
    }

    if (array_key_exists('score_a', $data) && $data['score_a'] !== null) {
        update_post_meta($post_id, 'competition_score_a', (string) (int) $data['score_a']);
    }
    if (array_key_exists('score_b', $data) && $data['score_b'] !== null) {
        update_post_meta($post_id, 'competition_score_b', (string) (int) $data['score_b']);
    }
}

/**
 * @param int                  $post_id 0 = 新規
 * @param array<string, mixed> $data
 * @return int|\WP_Error
 */
function aidunite_competition_persist_save_fixture($post_id, array $data) {
    $post_id = (int) $post_id;
    $event_id = (int) ($data['event_id'] ?? 0);
    if ($event_id < 1) {
        return new WP_Error('invalid_params', 'event_id が必要です');
    }

    $title = (string) ($data['title'] ?? '');
    if ($title === '') {
        $round = (int) ($data['round'] ?? 0);
        $match_index = (int) ($data['match_index'] ?? 0);
        $title = get_the_title($event_id) . ' R' . $round . '-' . $match_index;
    }

    $post_args = [
        'post_type' => 'competition_fixture',
        'post_title' => $title,
        'post_status' => 'publish',
    ];

    if ($post_id > 0) {
        $post_args['ID'] = $post_id;
        $saved = wp_update_post($post_args, true);
    } else {
        $saved = wp_insert_post($post_args, true);
    }

    if (is_wp_error($saved) || !$saved) {
        return is_wp_error($saved)
            ? $saved
            : new WP_Error('save_failed', '試合の保存に失敗しました');
    }

    $saved = (int) $saved;
    aidunite_competition_persist_write_fixture_meta($saved, $data);

    return $saved;
}

/**
 * @param int $event_id
 * @return int 削除件数
 */
function aidunite_competition_persist_delete_fixtures_for_event($event_id) {
    $event_id = (int) $event_id;
    if ($event_id < 1) {
        return 0;
    }

    $posts = get_posts([
        'post_type' => 'competition_fixture',
        'post_status' => 'any',
        'posts_per_page' => 500,
        'fields' => 'ids',
        'meta_query' => [
            [
                'key' => 'competition_event_id',
                'value' => (string) $event_id,
                'compare' => '=',
            ],
        ],
    ]);

    $deleted = 0;
    foreach ($posts as $fixture_id) {
        if (wp_delete_post((int) $fixture_id, true)) {
            $deleted++;
        }
    }

    return $deleted;
}
