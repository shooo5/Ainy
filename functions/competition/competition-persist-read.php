<?php
/**
 * 大会・イベント メタ読取正本（payload + capabilities derive）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @return array<string, string>
 */
function aidunite_competition_get_event_kind_labels() {
    return [
        'tournament' => 'トーナメント',
        'cup' => 'カップ',
        'clinic' => 'クリニック',
        'practice' => '練習会',
        'league' => 'リーグ',
        'friendly' => '交流戦',
        'session' => 'セッション',
    ];
}

/**
 * @return array<string, string>
 */
function aidunite_competition_get_event_status_labels() {
    return [
        'draft' => '下書き',
        'inviting' => '募集中',
        'locked' => '締切',
        'in_progress' => '開催中',
        'finished' => '終了',
    ];
}

/**
 * @return array<string, string>
 */
function aidunite_competition_get_entry_status_labels() {
    return [
        'invited' => '招待中',
        'applied' => '承認待ち',
        'confirmed' => '参加確定',
        'declined' => '辞退',
        'withdrawn' => '取消',
        'waitlisted' => 'キャンセル待ち',
    ];
}

/**
 * @param string               $event_kind
 * @param array<string, mixed> $overrides
 * @param array<string, mixed> $event_data
 * @return array<string, bool>
 */
function aidunite_competition_read_event_capabilities($event_kind, array $overrides = [], array $event_data = []) {
    $kind = aidunite_competition_normalize_event_kind($event_kind);
    $is_match_kind = in_array($kind, ['tournament', 'cup', 'league', 'friendly'], true);
    $fee_required = !empty($event_data['entry_fee_required']);

    $base = [
        'has_fixture' => $is_match_kind,
        'has_ranking' => $is_match_kind,
        'has_team_result' => $is_match_kind,
        'has_player_stats' => false,
        'has_mvp' => false,
        'has_attendance' => true,
        'has_entry_fee' => $fee_required,
        'has_chat' => false,
        'has_public_bracket' => false,
        'has_referee_input' => false,
        'has_digital_badge' => false,
        'has_roster' => false,
    ];

    if (in_array($kind, ['clinic', 'practice', 'session'], true)) {
        $base['has_fixture'] = false;
        $base['has_ranking'] = false;
        $base['has_team_result'] = false;
    }

    foreach ($overrides as $key => $value) {
        if (array_key_exists($key, $base)) {
            $base[$key] = (bool) $value;
        }
    }

    return $base;
}

/**
 * @param int                  $event_id
 * @param array<string, mixed> $capabilities
 * @return string event_wide|per_block|per_fixture
 */
function aidunite_competition_derive_schedule_mode($event_id, array $capabilities = []) {
    $event_id = (int) $event_id;
    if ($event_id > 0) {
        $blocks = aidunite_competition_read_blocks_for_event($event_id);
        if (!empty($blocks)) {
            return 'per_block';
        }
    }

    if (!empty($capabilities['has_fixture'])) {
        return 'event_wide';
    }

    return 'event_wide';
}

/**
 * @param int $event_id
 * @return array<string, mixed>
 */
function aidunite_competition_read_event_meta_raw($event_id) {
    $event_id = (int) $event_id;
    if ($event_id < 1 || get_post_type($event_id) !== 'competition_event') {
        return [];
    }

    $decode = static function ($json) {
        if ($json === '' || $json === null) {
            return [];
        }
        $decoded = json_decode((string) $json, true);

        return is_array($decoded) ? $decoded : [];
    };

    return [
        'id' => $event_id,
        'title' => get_the_title($event_id),
        'status' => (string) get_post_meta($event_id, 'competition_status', true),
        'event_kind' => (string) get_post_meta($event_id, 'competition_event_kind', true),
        'date_start' => (string) get_post_meta($event_id, 'competition_date_start', true),
        'date_end' => (string) get_post_meta($event_id, 'competition_date_end', true),
        'venue_name' => (string) get_post_meta($event_id, 'competition_venue_name', true),
        'venue_address' => (string) get_post_meta($event_id, 'competition_venue_address', true),
        'capacity_teams' => (int) get_post_meta($event_id, 'competition_capacity_teams', true),
        'application_deadline' => (string) get_post_meta($event_id, 'competition_application_deadline', true),
        'entry_fee_amount' => (int) get_post_meta($event_id, 'competition_entry_fee_amount', true),
        'entry_fee_currency' => (string) get_post_meta($event_id, 'competition_entry_fee_currency', true),
        'entry_fee_required' => get_post_meta($event_id, 'competition_entry_fee_required', true) === '1',
        'approval_mode' => (string) get_post_meta($event_id, 'competition_approval_mode', true),
        'visibility' => (string) get_post_meta($event_id, 'competition_visibility', true),
        'eligibility' => $decode(get_post_meta($event_id, 'competition_eligibility_json', true)),
        'organizer' => $decode(get_post_meta($event_id, 'competition_organizer_json', true)),
        'generator_config' => $decode(get_post_meta($event_id, 'competition_generator_config_json', true)),
        'capabilities_override' => $decode(get_post_meta($event_id, 'competition_capabilities_override_json', true)),
        'awards' => $decode(get_post_meta($event_id, 'competition_awards_json', true)),
        'marketing' => $decode(get_post_meta($event_id, 'competition_marketing_json', true)),
        'refund_policy' => $decode(get_post_meta($event_id, 'competition_refund_policy_json', true)),
        'public_slug' => (string) get_post_meta($event_id, 'competition_public_slug', true),
    ];
}

/**
 * @param int $entry_id
 * @return array<string, mixed>
 */
function aidunite_competition_read_entry_meta_raw($entry_id) {
    $entry_id = (int) $entry_id;
    if ($entry_id < 1 || get_post_type($entry_id) !== 'competition_entry') {
        return [];
    }

    return [
        'id' => $entry_id,
        'event_id' => (int) get_post_meta($entry_id, 'competition_event_id', true),
        'team_id' => (int) get_post_meta($entry_id, 'competition_team_id', true),
        'status' => (string) get_post_meta($entry_id, 'competition_entry_status', true),
        'payment_status' => (string) get_post_meta($entry_id, 'competition_payment_status', true),
        'calendar_schedule_id' => (int) get_post_meta($entry_id, 'competition_calendar_schedule_id', true),
        'responded_at' => (string) get_post_meta($entry_id, 'competition_responded_at', true),
        'confirmed_at' => (string) get_post_meta($entry_id, 'competition_confirmed_at', true),
        'group_id' => (int) get_post_meta($entry_id, 'competition_group_id', true),
        'seed' => (int) get_post_meta($entry_id, 'competition_seed', true),
        'waitlist_order' => (int) get_post_meta($entry_id, 'competition_waitlist_order', true),
        'stripe_checkout_session_id' => (string) get_post_meta($entry_id, 'competition_stripe_checkout_session_id', true),
        'stripe_payment_intent_id' => (string) get_post_meta($entry_id, 'competition_stripe_payment_intent_id', true),
        'refunded_at' => (string) get_post_meta($entry_id, 'competition_refunded_at', true),
        'refund_reason' => (string) get_post_meta($entry_id, 'competition_refund_reason', true),
        'stripe_refund_id' => (string) get_post_meta($entry_id, 'competition_stripe_refund_id', true),
    ];
}

/**
 * @param string $slug
 * @return int
 */
function aidunite_competition_persist_find_event_id_by_slug($slug) {
    $slug = sanitize_title((string) $slug);
    if ($slug === '') {
        return 0;
    }

    $posts = get_posts([
        'post_type' => 'competition_event',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'meta_query' => [
            [
                'key' => 'competition_public_slug',
                'value' => $slug,
                'compare' => '=',
            ],
        ],
    ]);

    return !empty($posts[0]) ? (int) $posts[0] : 0;
}

/**
 * @param string $payment_intent_id
 * @return int
 */
function aidunite_competition_persist_find_entry_id_by_payment_intent($payment_intent_id) {
    $payment_intent_id = sanitize_text_field((string) $payment_intent_id);
    if ($payment_intent_id === '') {
        return 0;
    }

    $posts = get_posts([
        'post_type' => 'competition_entry',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'meta_query' => [
            [
                'key' => 'competition_stripe_payment_intent_id',
                'value' => $payment_intent_id,
                'compare' => '=',
            ],
        ],
    ]);

    return !empty($posts[0]) ? (int) $posts[0] : 0;
}

/**
 * @param int $event_id
 * @return array<string, mixed>
 */
function aidunite_competition_read_refund_policy_raw($event_id) {
    $event_id = (int) $event_id;
    if ($event_id < 1) {
        return aidunite_competition_normalize_refund_policy_input([]);
    }

    $raw = aidunite_competition_read_event_meta_raw($event_id);
    $policy = is_array($raw['refund_policy'] ?? null) ? $raw['refund_policy'] : [];

    return aidunite_competition_normalize_refund_policy_input($policy);
}

/**
 * @param int $event_id
 * @return array<string, mixed>
 */
function aidunite_competition_read_refund_policy_payload($event_id) {
    $policy = aidunite_competition_read_refund_policy_raw($event_id);
    $lines = [];
    if ($policy['full_refund_days_before_start'] > 0) {
        $lines[] = '開催 ' . (int) $policy['full_refund_days_before_start'] . ' 日前まで: 全額返金';
    }
    if ($policy['partial_refund_days_before_start'] > 0 && $policy['partial_refund_percent'] > 0) {
        $lines[] = '開催 ' . (int) $policy['partial_refund_days_before_start'] . ' 日前まで: '
            . (int) $policy['partial_refund_percent'] . '% 返金';
    }
    if (!empty($policy['no_refund_after_deadline'])) {
        $lines[] = '申込締切後は返金不可';
    }

    return [
        'full_refund_days_before_start' => (int) $policy['full_refund_days_before_start'],
        'partial_refund_days_before_start' => (int) $policy['partial_refund_days_before_start'],
        'partial_refund_percent' => (int) $policy['partial_refund_percent'],
        'no_refund_after_deadline' => !empty($policy['no_refund_after_deadline']),
        'summary_lines' => $lines,
        'notes' => (string) ($policy['notes'] ?? ''),
    ];
}

/**
 * @param array<string, mixed> $filters status, status_not_in, posts_per_page, orderby, order
 * @return int[]
 */
function aidunite_competition_read_event_post_ids(array $filters = []) {
    $args = [
        'post_type' => 'competition_event',
        'post_status' => 'publish',
        'posts_per_page' => max(1, min(500, (int) ($filters['posts_per_page'] ?? 100))),
        'orderby' => sanitize_key((string) ($filters['orderby'] ?? 'ID')),
        'order' => strtoupper((string) ($filters['order'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC',
        'fields' => 'ids',
    ];

    $meta_query = [];
    if (!empty($filters['status'])) {
        $meta_query[] = [
            'key' => 'competition_status',
            'value' => aidunite_competition_normalize_event_status($filters['status']),
            'compare' => '=',
        ];
    }
    if (!empty($filters['status_not_in']) && is_array($filters['status_not_in'])) {
        $meta_query[] = [
            'key' => 'competition_status',
            'value' => array_values(array_map('sanitize_key', $filters['status_not_in'])),
            'compare' => 'NOT IN',
        ];
    }
    if ($meta_query !== []) {
        $args['meta_query'] = $meta_query;
    }

    return array_map('intval', (array) get_posts($args));
}

/**
 * @param array<string, mixed> $context viewer_user_id 等
 * @return array<int, array<string, mixed>>
 */
function aidunite_competition_read_events_list(array $context = []) {
    $ids = aidunite_competition_read_event_post_ids([
        'posts_per_page' => (int) ($context['posts_per_page'] ?? 100),
        'orderby' => (string) ($context['orderby'] ?? 'ID'),
        'order' => (string) ($context['order'] ?? 'DESC'),
    ]);

    $items = [];
    foreach ($ids as $event_id) {
        $payload = aidunite_competition_read_event_payload($event_id, $context);
        if (!empty($payload)) {
            $items[] = $payload;
        }
    }

    return $items;
}

/**
 * @param int    $event_id
 * @param string $entry_status 空 = 全件
 * @return int[]
 */
function aidunite_competition_read_entry_post_ids_for_event($event_id, $entry_status = '') {
    $event_id = (int) $event_id;
    if ($event_id < 1) {
        return [];
    }

    $meta_query = [
        'relation' => 'AND',
        [
            'key' => 'competition_event_id',
            'value' => (string) $event_id,
            'compare' => '=',
        ],
    ];
    if ($entry_status !== '') {
        $meta_query[] = [
            'key' => 'competition_entry_status',
            'value' => aidunite_competition_normalize_entry_status($entry_status),
            'compare' => '=',
        ];
    }

    $posts = get_posts([
        'post_type' => 'competition_entry',
        'post_status' => 'publish',
        'posts_per_page' => 200,
        'orderby' => 'ID',
        'order' => 'ASC',
        'fields' => 'ids',
        'meta_query' => $meta_query,
    ]);

    return array_map('intval', (array) $posts);
}

/**
 * @param int                  $team_id
 * @return array<string, mixed>
 */
function aidunite_competition_read_team_summary($team_id) {
    $team_id = (int) $team_id;
    if ($team_id < 1) {
        return [];
    }

    if (function_exists('aidunite_parent_read_team_payload')) {
        return aidunite_parent_read_team_payload($team_id);
    }

    return [
        'team_id' => $team_id,
        'team_name' => function_exists('aidunite_get_team_name')
            ? (string) aidunite_get_team_name($team_id)
            : (string) get_the_title($team_id),
    ];
}

/**
 * @param int                  $event_id
 * @param array<string, mixed> $context
 * @return array<string, mixed>
 */
function aidunite_competition_read_event_payload($event_id, array $context = []) {
    $raw = aidunite_competition_read_event_meta_raw($event_id);
    if (empty($raw)) {
        return [];
    }

    $kind_labels = aidunite_competition_get_event_kind_labels();
    $status_labels = aidunite_competition_get_event_status_labels();
    $event_kind = aidunite_competition_normalize_event_kind($raw['event_kind'] ?? 'tournament');
    $status = aidunite_competition_normalize_event_status($raw['status'] ?? 'draft');
    $capabilities = aidunite_competition_read_event_capabilities(
        $event_kind,
        is_array($raw['capabilities_override'] ?? null) ? $raw['capabilities_override'] : [],
        $raw
    );

    $confirmed_count = aidunite_competition_count_entries_by_status($event_id, 'confirmed');
    $invited_count = aidunite_competition_count_entries_by_status($event_id, 'invited');
    $applied_count = aidunite_competition_count_entries_by_status($event_id, 'applied');

    $viewer_id = (int) ($context['viewer_user_id'] ?? get_current_user_id());
    $can_operate = function_exists('aidunite_competition_user_can_operate')
        ? aidunite_competition_user_can_operate($viewer_id)
        : current_user_can('manage_options');

    return [
        'id' => (int) $raw['id'],
        'title' => (string) $raw['title'],
        'event_kind' => $event_kind,
        'event_kind_label' => $kind_labels[$event_kind] ?? $event_kind,
        'status' => $status,
        'status_label' => $status_labels[$status] ?? $status,
        'date_start' => (string) $raw['date_start'],
        'date_end' => (string) ($raw['date_end'] ?: $raw['date_start']),
        'venue' => [
            'name' => (string) $raw['venue_name'],
            'address' => (string) $raw['venue_address'],
        ],
        'capacity_teams' => (int) $raw['capacity_teams'],
        'application_deadline' => (string) $raw['application_deadline'],
        'entry_fee' => [
            'required' => !empty($raw['entry_fee_required']),
            'amount' => (int) $raw['entry_fee_amount'],
            'currency' => (string) ($raw['entry_fee_currency'] ?: 'JPY'),
        ],
        'approval_mode' => aidunite_competition_normalize_approval_mode($raw['approval_mode'] ?? 'auto'),
        'visibility' => aidunite_competition_normalize_visibility($raw['visibility'] ?? 'admin_only'),
        'eligibility' => is_array($raw['eligibility']) ? $raw['eligibility'] : [],
        'organizer' => is_array($raw['organizer']) ? $raw['organizer'] : [],
        'generator_config' => is_array($raw['generator_config']) ? $raw['generator_config'] : [],
        'capabilities' => $capabilities,
        'schedule_mode' => aidunite_competition_derive_schedule_mode($event_id, $capabilities),
        'counts' => [
            'confirmed' => $confirmed_count,
            'invited' => $invited_count,
            'applied' => $applied_count,
        ],
        'permissions' => [
            'can_operate' => $can_operate,
            'can_invite' => $can_operate && in_array($status, ['inviting'], true),
            'can_edit' => $can_operate,
        ],
        'recruitment_closed' => aidunite_competition_is_recruitment_closed($event_id),
        'marketing' => aidunite_competition_normalize_marketing_input(
            is_array($raw['marketing'] ?? null) ? $raw['marketing'] : []
        ),
        'refund_policy' => aidunite_competition_read_refund_policy_payload($event_id),
        'public_url' => !empty($raw['public_slug']) && function_exists('aidunite_competition_public_lp_url')
            ? aidunite_competition_public_lp_url((string) $raw['public_slug'])
            : '',
    ];
}

/**
 * @param int                  $entry_id
 * @param array<string, mixed> $context
 * @return array<string, mixed>
 */
function aidunite_competition_read_entry_payload($entry_id, array $context = []) {
    $raw = aidunite_competition_read_entry_meta_raw($entry_id);
    if (empty($raw)) {
        return [];
    }

    $status_labels = aidunite_competition_get_entry_status_labels();
    $status = aidunite_competition_normalize_entry_status($raw['status'] ?? 'invited');
    $payment_status = aidunite_competition_normalize_payment_status($raw['payment_status'] ?? 'unpaid');
    $team_id = (int) $raw['team_id'];
    $event_id = (int) $raw['event_id'];

    $viewer_id = (int) ($context['viewer_user_id'] ?? get_current_user_id());
    $can_operate = function_exists('aidunite_competition_user_can_operate')
        ? aidunite_competition_user_can_operate($viewer_id)
        : current_user_can('manage_options');
    $can_respond = false;
    if ($status === 'invited' && $team_id > 0 && function_exists('aidunite_user_has_managed_team_access')) {
        $can_respond = aidunite_user_has_managed_team_access($viewer_id, $team_id);
    }

    $event_payload = $event_id > 0 ? aidunite_competition_read_event_payload($event_id, $context) : [];
    $entry_fee = is_array($event_payload['entry_fee'] ?? null) ? $event_payload['entry_fee'] : [];
    $pay_eligibility = function_exists('aidunite_competition_entry_payment_eligibility')
        ? aidunite_competition_entry_payment_eligibility($entry_id)
        : ['eligible' => false];
    $refund_eligibility = function_exists('aidunite_competition_evaluate_refund_eligibility')
        ? aidunite_competition_evaluate_refund_eligibility($entry_id, false)
        : ['eligible' => false];

    return [
        'id' => (int) $raw['id'],
        'event' => !empty($event_payload) ? [
            'id' => (int) $event_payload['id'],
            'title' => (string) $event_payload['title'],
            'event_kind' => (string) $event_payload['event_kind'],
            'event_kind_label' => (string) $event_payload['event_kind_label'],
            'date_start' => (string) $event_payload['date_start'],
            'date_end' => (string) $event_payload['date_end'],
            'venue' => $event_payload['venue'] ?? [],
            'application_deadline' => (string) ($event_payload['application_deadline'] ?? ''),
        ] : [],
        'team' => aidunite_competition_read_team_summary($team_id),
        'status' => $status,
        'status_label' => $status_labels[$status] ?? $status,
        'payment_status' => $payment_status,
        'calendar_schedule_id' => (int) $raw['calendar_schedule_id'],
        'responded_at' => (string) $raw['responded_at'],
        'confirmed_at' => (string) $raw['confirmed_at'],
        'actions' => [
            'can_respond' => $can_respond && !$can_operate,
            'can_approve' => $can_operate && $status === 'applied',
            'can_update_payment' => $can_operate,
            'can_checkout' => !empty($pay_eligibility['eligible']),
            'can_refund' => $can_operate && !empty($refund_eligibility['eligible']),
            'respond_options' => $can_respond ? ['confirm', 'decline'] : [],
        ],
        'payment' => [
            'status' => $payment_status,
            'amount' => (int) ($entry_fee['amount'] ?? 0),
            'currency' => (string) ($entry_fee['currency'] ?? 'JPY'),
            'required' => !empty($entry_fee['required']),
            'checkout_available' => !empty($pay_eligibility['eligible']),
            'refunded_at' => (string) ($raw['refunded_at'] ?? ''),
            'refund_eligibility' => $can_operate ? $refund_eligibility : ['eligible' => false],
        ],
    ];
}

/**
 * @param int $event_id
 * @return array<int, array<string, mixed>>
 */
function aidunite_competition_read_entries_for_event($event_id) {
    $event_id = (int) $event_id;
    if ($event_id < 1) {
        return [];
    }

    $items = [];
    foreach (aidunite_competition_read_entry_post_ids_for_event($event_id) as $entry_id) {
        $payload = aidunite_competition_read_entry_payload($entry_id);
        if (!empty($payload)) {
            $items[] = $payload;
        }
    }

    return $items;
}

/**
 * @param int $user_id
 * @return array<int, array<string, mixed>>
 */
function aidunite_competition_read_team_entries_for_user($user_id) {
    $user_id = (int) $user_id;
    if ($user_id < 1) {
        return [];
    }

    $team_ids = function_exists('aidunite_get_managed_team_ids')
        ? array_map('intval', (array) aidunite_get_managed_team_ids($user_id))
        : [];
    if (empty($team_ids)) {
        $legacy = (int) get_user_meta($user_id, 'team_id', true);
        if ($legacy > 0) {
            $team_ids = [$legacy];
        }
    }
    if (empty($team_ids)) {
        return [];
    }

    $items = [];
    foreach ($team_ids as $team_id) {
        $posts = get_posts([
            'post_type' => 'competition_entry',
            'post_status' => 'publish',
            'posts_per_page' => 50,
            'meta_query' => [
                [
                    'key' => 'competition_team_id',
                    'value' => (string) $team_id,
                    'compare' => '=',
                ],
            ],
        ]);
        foreach ($posts as $post) {
            $payload = aidunite_competition_read_entry_payload((int) $post->ID, ['viewer_user_id' => $user_id]);
            if (!empty($payload)) {
                $items[] = $payload;
            }
        }
    }

    return $items;
}

/**
 * @param int    $event_id
 * @param string $status
 * @return int
 */
function aidunite_competition_count_entries_by_status($event_id, $status) {
    $event_id = (int) $event_id;
    $status = aidunite_competition_normalize_entry_status($status);
    if ($event_id < 1) {
        return 0;
    }

    $query = new WP_Query([
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
                'key' => 'competition_entry_status',
                'value' => $status,
                'compare' => '=',
            ],
        ],
    ]);

    return (int) $query->found_posts;
}

/**
 * @param int $event_id
 * @return bool
 */
function aidunite_competition_is_recruitment_closed($event_id) {
    $raw = aidunite_competition_read_event_meta_raw($event_id);
    if (empty($raw)) {
        return true;
    }

    $status = aidunite_competition_normalize_event_status($raw['status'] ?? 'draft');
    if (!in_array($status, ['inviting'], true)) {
        return true;
    }

    $deadline = (string) ($raw['application_deadline'] ?? '');
    if ($deadline !== '' && strtotime($deadline . ' 23:59:59') < time()) {
        return true;
    }

    return false;
}

/**
 * @param int $block_id
 * @return array<string, mixed>
 */
function aidunite_competition_read_block_payload($block_id) {
    $block_id = (int) $block_id;
    if ($block_id < 1 || get_post_type($block_id) !== 'competition_block') {
        return [];
    }

    $courts_json = (string) get_post_meta($block_id, 'competition_block_courts_json', true);
    $courts = json_decode($courts_json, true);
    if (!is_array($courts)) {
        $courts = [];
    }

    return [
        'id' => $block_id,
        'event_id' => (int) get_post_meta($block_id, 'competition_event_id', true),
        'date' => (string) get_post_meta($block_id, 'competition_block_date', true),
        'venue_name' => (string) get_post_meta($block_id, 'competition_block_venue_name', true),
        'courts' => $courts,
        'start_time' => (string) get_post_meta($block_id, 'competition_block_start_time', true),
        'end_time' => (string) get_post_meta($block_id, 'competition_block_end_time', true),
        'notes' => (string) get_post_meta($block_id, 'competition_block_notes', true),
    ];
}

/**
 * @param int $event_id
 * @return array<int, array<string, mixed>>
 */
function aidunite_competition_read_blocks_for_event($event_id) {
    $event_id = (int) $event_id;
    if ($event_id < 1) {
        return [];
    }

    $posts = get_posts([
        'post_type' => 'competition_block',
        'post_status' => 'publish',
        'posts_per_page' => 30,
        'orderby' => 'meta_value',
        'meta_key' => 'competition_block_date',
        'order' => 'ASC',
        'meta_query' => [
            [
                'key' => 'competition_event_id',
                'value' => (string) $event_id,
                'compare' => '=',
            ],
        ],
    ]);

    $items = [];
    foreach ($posts as $post) {
        $payload = aidunite_competition_read_block_payload((int) $post->ID);
        if (!empty($payload)) {
            $items[] = $payload;
        }
    }

    return $items;
}

/**
 * @return array<string, mixed>
 */
function aidunite_competition_read_ops_summary() {
    $events = aidunite_competition_read_event_post_ids([
        'status_not_in' => ['draft', 'finished'],
    ]);

    $summary = [
        'active_events' => count($events),
        'unanswered_entries' => 0,
        'unpaid_confirmed' => 0,
        'missing_schedule' => 0,
    ];

    foreach ($events as $event_id) {
        $summary['unanswered_entries'] += aidunite_competition_count_entries_by_status((int) $event_id, 'invited');
        $entries = aidunite_competition_read_entries_for_event((int) $event_id);
        foreach ($entries as $entry) {
            if (($entry['status'] ?? '') === 'confirmed') {
                $event_raw = aidunite_competition_read_event_meta_raw((int) $event_id);
                if (!empty($event_raw['entry_fee_required']) && ($entry['payment_status'] ?? '') === 'unpaid') {
                    $summary['unpaid_confirmed']++;
                }
                if ((int) ($entry['calendar_schedule_id'] ?? 0) < 1) {
                    $summary['missing_schedule']++;
                }
            }
        }
    }

    return $summary;
}

/**
 * @param array<string, mixed> $filters
 * @return array<int, array<string, mixed>>
 */
function aidunite_competition_read_ops_alerts(array $filters = []) {
    $event_filter = (int) ($filters['event_id'] ?? 0);
    $alerts = [];

    $event_ids = [];
    if ($event_filter > 0) {
        $event_ids = [$event_filter];
    } else {
        $event_ids = aidunite_competition_read_event_post_ids([
            'status_not_in' => ['draft', 'finished'],
        ]);
    }

    foreach ($event_ids as $event_id) {
        $event_id = (int) $event_id;
        $event_title = get_the_title($event_id);
        $raw = aidunite_competition_read_event_meta_raw($event_id);
        $unanswered = aidunite_competition_count_entries_by_status($event_id, 'invited');
        if ($unanswered > 0 && !aidunite_competition_is_recruitment_closed($event_id)) {
            $alerts[] = [
                'id' => 'unanswered_entries',
                'severity' => 'warning',
                'count' => $unanswered,
                'event_id' => $event_id,
                'event_title' => $event_title,
                'label' => '未回答チーム ' . $unanswered . '件',
                'actions' => [
                    ['type' => 'resend_invite', 'enabled' => true],
                ],
            ];
        }

        $confirmed = aidunite_competition_count_entries_by_status($event_id, 'confirmed');
        $capacity = (int) ($raw['capacity_teams'] ?? 0);
        if ($capacity > 0 && $confirmed > $capacity) {
            $alerts[] = [
                'id' => 'over_capacity',
                'severity' => 'error',
                'count' => $confirmed - $capacity,
                'event_id' => $event_id,
                'event_title' => $event_title,
                'label' => '定員超過（確定 ' . $confirmed . ' / 定員 ' . $capacity . '）',
                'actions' => [],
            ];
        }

        $entries = aidunite_competition_read_entries_for_event($event_id);
        $unpaid = 0;
        $missing_schedule = 0;
        foreach ($entries as $entry) {
            if (($entry['status'] ?? '') !== 'confirmed') {
                continue;
            }
            if (!empty($raw['entry_fee_required']) && ($entry['payment_status'] ?? '') === 'unpaid') {
                $unpaid++;
            }
            if ((int) ($entry['calendar_schedule_id'] ?? 0) < 1) {
                $missing_schedule++;
            }
        }

        if ($unpaid > 0) {
            $alerts[] = [
                'id' => 'unpaid_confirmed',
                'severity' => 'warning',
                'count' => $unpaid,
                'event_id' => $event_id,
                'event_title' => $event_title,
                'label' => '参加確定・未入金 ' . $unpaid . '件',
                'actions' => [],
            ];
        }
        if ($missing_schedule > 0) {
            $alerts[] = [
                'id' => 'missing_schedule',
                'severity' => 'error',
                'count' => $missing_schedule,
                'event_id' => $event_id,
                'event_title' => $event_title,
                'label' => 'スケジュール未生成 ' . $missing_schedule . '件',
                'actions' => [],
            ];
        }

        $today = current_time('Y-m-d');
        $fixtures_today = 0;
        $results_pending = 0;
        foreach (aidunite_competition_read_fixtures_for_event($event_id) as $fixture) {
            $scheduled_at = (string) ($fixture['scheduled_at'] ?? '');
            if ($scheduled_at !== '' && strpos($scheduled_at, $today) === 0) {
                $fixtures_today++;
            }
            $team_a = is_array($fixture['team_a'] ?? null) ? $fixture['team_a'] : [];
            $team_b = is_array($fixture['team_b'] ?? null) ? $fixture['team_b'] : [];
            if (($fixture['status'] ?? '') === 'scheduled'
                && (int) ($team_a['id'] ?? $team_a['team_id'] ?? 0) > 0
                && (int) ($team_b['id'] ?? $team_b['team_id'] ?? 0) > 0) {
                $results_pending++;
            }
        }
        if ($fixtures_today > 0) {
            $alerts[] = [
                'id' => 'fixtures_today',
                'severity' => 'info',
                'count' => $fixtures_today,
                'event_id' => $event_id,
                'event_title' => $event_title,
                'label' => '本日の試合 ' . $fixtures_today . '件',
                'actions' => [],
            ];
        }
        if ($results_pending > 0 && in_array($raw['status'] ?? '', ['in_progress', 'locked'], true)) {
            $alerts[] = [
                'id' => 'results_pending',
                'severity' => 'warning',
                'count' => $results_pending,
                'event_id' => $event_id,
                'event_title' => $event_title,
                'label' => '結果未入力 ' . $results_pending . '件',
                'actions' => [],
            ];
        }
    }

    return $alerts;
}

/**
 * @param int $event_id
 * @return array<string, mixed>
 */
function aidunite_competition_read_awards_payload($event_id) {
    $event_id = (int) $event_id;
    if ($event_id < 1) {
        return ['items' => [], 'status' => 'coming_soon'];
    }

    $raw = aidunite_competition_read_event_meta_raw($event_id);
    $items = is_array($raw['awards'] ?? null) ? $raw['awards'] : [];

    return [
        'items' => $items,
        'status' => empty($items) ? 'coming_soon' : 'published',
    ];
}

/**
 * @param int $fixture_id
 * @return array<string, mixed>
 */
function aidunite_competition_read_fixture_meta_raw($fixture_id) {
    $fixture_id = (int) $fixture_id;
    if ($fixture_id < 1 || get_post_type($fixture_id) !== 'competition_fixture') {
        return [];
    }

    return [
        'id' => $fixture_id,
        'event_id' => (int) get_post_meta($fixture_id, 'competition_event_id', true),
        'stage_id' => (int) get_post_meta($fixture_id, 'competition_stage_id', true),
        'group_id' => (int) get_post_meta($fixture_id, 'competition_group_id', true),
        'team_a_id' => (int) get_post_meta($fixture_id, 'competition_team_a_id', true),
        'team_b_id' => (int) get_post_meta($fixture_id, 'competition_team_b_id', true),
        'scheduled_at' => (string) get_post_meta($fixture_id, 'competition_fixture_scheduled_at', true),
        'court_label' => (string) get_post_meta($fixture_id, 'competition_court_label', true),
        'status' => (string) get_post_meta($fixture_id, 'competition_fixture_status', true),
        'score_a' => get_post_meta($fixture_id, 'competition_score_a', true),
        'score_b' => get_post_meta($fixture_id, 'competition_score_b', true),
        'result_source' => (string) get_post_meta($fixture_id, 'competition_result_source', true),
        'round' => (int) get_post_meta($fixture_id, 'competition_fixture_round', true),
        'match_index' => (int) get_post_meta($fixture_id, 'competition_fixture_match_index', true),
        'winner_advances_to' => (int) get_post_meta($fixture_id, 'competition_fixture_winner_advances_to', true),
        'winner_slot' => (string) get_post_meta($fixture_id, 'competition_fixture_winner_slot', true),
    ];
}

/**
 * @param array<string, mixed> $raw
 * @return array<string, mixed>
 */
function aidunite_competition_read_fixture_payload_from_array(array $raw) {
    $team_a_id = (int) ($raw['team_a_id'] ?? 0);
    $team_b_id = (int) ($raw['team_b_id'] ?? 0);

    return [
        'id' => (int) ($raw['id'] ?? 0),
        'event_id' => (int) ($raw['event_id'] ?? 0),
        'round' => (int) ($raw['round'] ?? 0),
        'match_index' => (int) ($raw['match_index'] ?? 0),
        'team_a' => $team_a_id > 0 ? aidunite_competition_read_team_summary($team_a_id) : null,
        'team_b' => $team_b_id > 0 ? aidunite_competition_read_team_summary($team_b_id) : null,
        'scheduled_at' => (string) ($raw['scheduled_at'] ?? ''),
        'court_label' => (string) ($raw['court_label'] ?? ''),
        'status' => aidunite_competition_normalize_fixture_status($raw['status'] ?? 'scheduled'),
        'score' => [
            'a' => isset($raw['score_a']) && $raw['score_a'] !== '' ? (int) $raw['score_a'] : null,
            'b' => isset($raw['score_b']) && $raw['score_b'] !== '' ? (int) $raw['score_b'] : null,
        ],
        'result_source' => aidunite_competition_normalize_result_source($raw['result_source'] ?? 'admin'),
    ];
}

/**
 * @param int $fixture_id
 * @return array<string, mixed>
 */
function aidunite_competition_read_fixture_payload($fixture_id) {
    $raw = aidunite_competition_read_fixture_meta_raw($fixture_id);
    if (empty($raw)) {
        return [];
    }

    $payload = aidunite_competition_read_fixture_payload_from_array($raw);
    $payload['id'] = (int) $raw['id'];
    $payload['winner_advances_to'] = (int) ($raw['winner_advances_to'] ?? 0);

    return $payload;
}

/**
 * @param int $event_id
 * @return array<int, array<string, mixed>>
 */
function aidunite_competition_read_fixtures_for_event($event_id) {
    $event_id = (int) $event_id;
    if ($event_id < 1) {
        return [];
    }

    $posts = get_posts([
        'post_type' => 'competition_fixture',
        'post_status' => 'publish',
        'posts_per_page' => 500,
        'orderby' => 'meta_value_num',
        'meta_key' => 'competition_fixture_round',
        'order' => 'ASC',
        'meta_query' => [
            [
                'key' => 'competition_event_id',
                'value' => (string) $event_id,
                'compare' => '=',
            ],
        ],
    ]);

    usort($posts, static function ($a, $b) {
        $ra = aidunite_competition_read_fixture_meta_raw((int) $a->ID);
        $rb = aidunite_competition_read_fixture_meta_raw((int) $b->ID);
        $round_a = (int) ($ra['round'] ?? 0);
        $round_b = (int) ($rb['round'] ?? 0);
        if ($round_a !== $round_b) {
            return $round_a <=> $round_b;
        }

        return (int) ($ra['match_index'] ?? 0) <=> (int) ($rb['match_index'] ?? 0);
    });

    $items = [];
    foreach ($posts as $post) {
        $payload = aidunite_competition_read_fixture_payload((int) $post->ID);
        if (!empty($payload)) {
            $items[] = $payload;
        }
    }

    return $items;
}

/**
 * @param int $event_id
 * @return array<string, mixed>
 */
function aidunite_competition_read_standings_payload($event_id) {
    $event_id = (int) $event_id;
    $fixtures = aidunite_competition_read_fixtures_for_event($event_id);
    $standings = [];

    if ($fixtures === []) {
        return ['items' => [], 'status' => 'empty'];
    }

    $max_round = 0;
    $final = null;
    foreach ($fixtures as $fixture) {
        $round = (int) ($fixture['round'] ?? 0);
        if ($round >= $max_round) {
            $max_round = $round;
            $final = $fixture;
        }
    }

    if ($final && ($final['status'] ?? '') === 'finished') {
        $score = is_array($final['score'] ?? null) ? $final['score'] : [];
        $winner = null;
        $runner_up = null;
        if ((int) ($score['a'] ?? -1) > (int) ($score['b'] ?? -1)) {
            $winner = $final['team_a'] ?? null;
            $runner_up = $final['team_b'] ?? null;
        } elseif ((int) ($score['b'] ?? -1) > (int) ($score['a'] ?? -1)) {
            $winner = $final['team_b'] ?? null;
            $runner_up = $final['team_a'] ?? null;
        }
        if ($winner) {
            $standings[] = ['rank' => 1, 'team' => $winner];
        }
        if ($runner_up) {
            $standings[] = ['rank' => 2, 'team' => $runner_up];
        }
    }

    return [
        'items' => $standings,
        'status' => $standings === [] ? 'pending' : 'partial',
    ];
}

/**
 * @param int $event_id
 * @return array<string, mixed>
 */
function aidunite_competition_read_public_bracket_payload($event_id) {
    $event_id = (int) $event_id;
    $event_raw = aidunite_competition_read_event_meta_raw($event_id);
    if (empty($event_raw)) {
        return [];
    }

    $visibility = aidunite_competition_normalize_visibility($event_raw['visibility'] ?? 'admin_only');
    if ($visibility !== 'public' && !aidunite_competition_user_can_operate(get_current_user_id())) {
        return ['error' => 'forbidden'];
    }

    $fixtures = aidunite_competition_read_fixtures_for_event($event_id);
    $rounds = [];
    foreach ($fixtures as $fixture) {
        $round = (int) ($fixture['round'] ?? 0);
        if (!isset($rounds[$round])) {
            $rounds[$round] = [];
        }
        $rounds[$round][] = $fixture;
    }
    ksort($rounds);

    return [
        'event' => [
            'id' => $event_id,
            'title' => (string) ($event_raw['title'] ?? get_the_title($event_id)),
            'date_start' => (string) ($event_raw['date_start'] ?? ''),
        ],
        'rounds' => array_values(array_map(static function ($items) {
            return ['fixtures' => $items];
        }, $rounds)),
    ];
}

/**
 * マイページ「やること」用 competition 招待
 *
 * @param int      $user_id
 * @param int[]    $team_scope
 * @param string   $effective_role
 * @return array<int, array<string, mixed>>
 */
function aidunite_competition_read_action_required_items($user_id, array $team_scope, $effective_role) {
    $user_id = (int) $user_id;
    if ($user_id < 1 || $effective_role !== 'team_leader' || empty($team_scope)) {
        return [];
    }

    $items = [];
    $entries = aidunite_competition_read_team_entries_for_user($user_id);
    foreach ($entries as $entry) {
        if (($entry['status'] ?? '') !== 'invited') {
            continue;
        }
        $team_id = (int) ($entry['team']['team_id'] ?? $entry['team']['id'] ?? 0);
        if ($team_id > 0 && !in_array($team_id, array_map('intval', $team_scope), true)) {
            continue;
        }

        $event = is_array($entry['event'] ?? null) ? $entry['event'] : [];
        $deadline = (string) ($event['application_deadline'] ?? '');
        $deadline_ts = $deadline !== '' ? $deadline . ' 23:59:59' : null;

        $items[] = [
            'type' => 'competition_invite',
            'id' => (int) $entry['id'],
            'title' => '大会・イベントへの参加回答',
            'description' => (string) ($event['title'] ?? '大会招待'),
            'date' => (string) ($event['date_start'] ?? ''),
            'deadline' => $deadline_ts,
            'link_url' => home_url('/mypage/?competition_entry=' . (int) $entry['id']),
            'actions' => [
                ['label' => '参加する', 'action' => 'competition_confirm', 'type' => 'primary'],
                ['label' => '辞退する', 'action' => 'competition_decline', 'type' => 'secondary'],
            ],
        ];
    }

    foreach ($entries as $entry) {
        if (($entry['status'] ?? '') !== 'confirmed') {
            continue;
        }
        if (empty($entry['payment']['checkout_available'])) {
            continue;
        }
        $team_id = (int) ($entry['team']['team_id'] ?? $entry['team']['id'] ?? 0);
        if ($team_id > 0 && !in_array($team_id, array_map('intval', $team_scope), true)) {
            continue;
        }
        $event = is_array($entry['event'] ?? null) ? $entry['event'] : [];
        $amount = (int) ($entry['payment']['amount'] ?? 0);
        $items[] = [
            'type' => 'competition_payment',
            'id' => (int) $entry['id'],
            'title' => '大会・イベント参加費のお支払い',
            'description' => (string) ($event['title'] ?? '大会') . ($amount > 0 ? ' — ¥' . number_format($amount) : ''),
            'date' => (string) ($event['date_start'] ?? ''),
            'deadline' => null,
            'link_url' => home_url('/mypage/?competition_entry=' . (int) $entry['id']),
            'actions' => [
                ['label' => '支払う', 'action' => 'competition_pay', 'type' => 'primary'],
            ],
        ];
    }

    return $items;
}
