<?php
/**
 * アクティブチーム分析（区分・明細）
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/analytics-config.php';

/**
 * チームの最終活動日時（schedule / MR）
 *
 * @param int $team_id
 * @return string|null Y-m-d H:i:s
 */
function aidunite_analytics_team_last_activity_at($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return null;
    }

    $latest = null;

    $schedules = get_posts([
        'post_type' => 'schedule',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'orderby' => 'date',
        'order' => 'DESC',
        'fields' => 'ids',
        'meta_query' => [
            [
                'key' => 'team_id',
                'value' => (string) $team_id,
            ],
        ],
    ]);
    if (!empty($schedules)) {
        $latest = get_post_field('post_date', $schedules[0]);
    }

    $requests = get_posts([
        'post_type' => 'match_request',
        'post_status' => ['publish', 'draft'],
        'posts_per_page' => 1,
        'orderby' => 'date',
        'order' => 'DESC',
        'fields' => 'ids',
        'meta_query' => [
            [
                'key' => 'from_team_id',
                'value' => (string) $team_id,
            ],
        ],
    ]);
    if (!empty($requests)) {
        $mr_date = get_post_field('post_date', $requests[0]);
        if ($latest === null || strtotime($mr_date) > strtotime($latest)) {
            $latest = $mr_date;
        }
    }

    return $latest;
}

/**
 * @param int $team_id
 * @return array{has_schedule:bool, has_recruit:bool, has_application:bool, has_established:bool, established_count:int}
 */
function aidunite_analytics_team_activity_flags($team_id) {
    $team_id = (int) $team_id;
    $has_schedule = !empty(get_posts([
        'post_type' => 'schedule',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'meta_query' => [['key' => 'team_id', 'value' => (string) $team_id]],
    ]));

    $has_recruit = !empty(get_posts([
        'post_type' => 'schedule',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'meta_query' => [
            ['key' => 'team_id', 'value' => (string) $team_id],
            ['key' => 'intent', 'value' => 'recruit'],
        ],
    ]));

    $excluded = aidunite_analytics_funnel_mr_excluded_status_meta_values();
    $has_application = false;
    $app_posts = get_posts([
        'post_type' => 'match_request',
        'post_status' => ['publish', 'draft'],
        'posts_per_page' => 20,
        'fields' => 'ids',
        'meta_query' => [['key' => 'from_team_id', 'value' => (string) $team_id]],
    ]);
    $established_count = 0;
    $est_values = aidunite_analytics_funnel_mr_established_status_meta_values();
    foreach ($app_posts as $rid) {
        $st = (string) get_post_meta($rid, 'status', true);
        if (!in_array($st, $excluded, true)) {
            $has_application = true;
        }
        if (in_array($st, $est_values, true)) {
            $established_count++;
        }
    }

    return [
        'has_schedule' => $has_schedule,
        'has_recruit' => $has_recruit,
        'has_application' => $has_application,
        'has_established' => $established_count > 0,
        'established_count' => $established_count,
    ];
}

/**
 * @param int $team_id
 * @param string|null $last_at
 * @param array<string, mixed> $flags
 * @return string new_active|continuing|dormant_return|dormant_risk|inactive|high_value
 */
function aidunite_analytics_classify_team_segment($team_id, $last_at, array $flags) {
    if (!empty($flags['has_established'])) {
        return 'high_value';
    }
    if (!empty($flags['has_recruit']) && !empty($flags['has_application'])) {
        return 'high_value';
    }

    $now = current_time('timestamp');
    if ($last_at === null) {
        return 'inactive';
    }

    $last_ts = strtotime($last_at);
    if ($last_ts === false) {
        return 'inactive';
    }

    $days_ago = ($now - $last_ts) / DAY_IN_SECONDS;

    if ($days_ago <= 30) {
        $had_prior = aidunite_analytics_team_had_activity_between($team_id, 90, 30);
        if (!$had_prior) {
            $older = aidunite_analytics_team_had_activity_before_days($team_id, 90);
            return $older ? 'dormant_return' : 'new_active';
        }
        return 'continuing';
    }

    if ($days_ago <= 90) {
        return 'dormant_risk';
    }

    return 'inactive';
}

/**
 * @param int $team_id
 * @param int $days_start ago start
 * @param int $days_end ago end (exclusive upper window)
 */
function aidunite_analytics_team_had_activity_between($team_id, $days_start, $days_end) {
    $from = date('Y-m-d H:i:s', strtotime('-' . (int) $days_start . ' days', current_time('timestamp')));
    $to = date('Y-m-d H:i:s', strtotime('-' . (int) $days_end . ' days', current_time('timestamp')));
    return aidunite_analytics_team_has_posts_between($team_id, $from, $to);
}

function aidunite_analytics_team_had_activity_before_days($team_id, $days) {
    $before = date('Y-m-d H:i:s', strtotime('-' . (int) $days . ' days', current_time('timestamp')));
    global $wpdb;
    $team_id = (int) $team_id;
    $c = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'team_id' AND pm.meta_value = %s
             WHERE p.post_type = 'schedule' AND p.post_status = 'publish' AND p.post_date < %s",
            (string) $team_id,
            $before
        )
    );
    if ($c > 0) {
        return true;
    }
    return (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'from_team_id' AND pm.meta_value = %s
             WHERE p.post_type = 'match_request' AND p.post_date < %s",
            (string) $team_id,
            $before
        )
    ) > 0;
}

function aidunite_analytics_team_has_posts_between($team_id, $from, $to) {
    global $wpdb;
    $team_id = (int) $team_id;
    $c = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'team_id' AND pm.meta_value = %s
             WHERE p.post_type = 'schedule' AND p.post_status = 'publish'
             AND p.post_date >= %s AND p.post_date < %s",
            (string) $team_id,
            $from,
            $to
        )
    );
    if ($c > 0) {
        return true;
    }
    return (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'from_team_id' AND pm.meta_value = %s
             WHERE p.post_type = 'match_request'
             AND p.post_date >= %s AND p.post_date < %s",
            (string) $team_id,
            $from,
            $to
        )
    ) > 0;
}

/**
 * @return array<string, string>
 */
function aidunite_analytics_team_segment_labels() {
    return [
        'high_value' => '高価値アクティブ',
        'new_active' => '新規アクティブ',
        'continuing' => '継続アクティブ',
        'dormant_return' => '休眠復帰',
        'dormant_risk' => '休眠予備軍',
        'inactive' => '非アクティブ',
    ];
}

/**
 * @param string $segment_filter
 * @param int $limit
 * @return array{summary: array<string, int>, teams: array<int, array<string, mixed>>}
 */
function aidunite_analytics_get_active_teams_report($segment_filter = '', $limit = 100) {
    $segment_filter = sanitize_key($segment_filter);
    $labels = aidunite_analytics_team_segment_labels();
    $summary = array_fill_keys(array_keys($labels), 0);

    $team_posts = get_posts([
        'post_type' => 'team',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
    ]);

    $teams = [];
    foreach ($team_posts as $team_id) {
        $team_id = (int) $team_id;
        $last_at = aidunite_analytics_team_last_activity_at($team_id);
        $flags = aidunite_analytics_team_activity_flags($team_id);
        $segment = aidunite_analytics_classify_team_segment($team_id, $last_at, $flags);
        if (isset($summary[$segment])) {
            $summary[$segment]++;
        }

        if ($segment_filter !== '' && $segment !== $segment_filter) {
            continue;
        }

        $team_name = get_post_meta($team_id, 'team_name', true);
        if ($team_name === '') {
            $p = get_post($team_id);
            $team_name = $p ? $p->post_title : ('チーム #' . $team_id);
        }

        $teams[] = [
            'team_id' => $team_id,
            'team_name' => $team_name,
            'segment' => $segment,
            'segment_label' => $labels[$segment] ?? $segment,
            'last_activity_at' => $last_at,
            'flags' => $flags,
        ];
    }

    usort($teams, static function ($a, $b) {
        $ta = $a['last_activity_at'] ? strtotime($a['last_activity_at']) : 0;
        $tb = $b['last_activity_at'] ? strtotime($b['last_activity_at']) : 0;
        return $tb <=> $ta;
    });

    $teams = array_slice($teams, 0, max(1, min(500, (int) $limit)));

    return [
        'summary' => $summary,
        'teams' => $teams,
        'total_publish_teams' => count($team_posts),
    ];
}
