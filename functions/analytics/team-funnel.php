<?php
/**
 * チーム単位の成立ファネル集計
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/analytics-config.php';

/**
 * ファネル各段階の到達チーム数（累計型: その段階以上に到達したユニーク team 数）
 *
 * @return array{
 *   steps: array<int, array{
 *     id: string,
 *     label: string,
 *     order: int,
 *     count: int,
 *     prev_count: int|null,
 *     step_rate: float|null,
 *     leave_rate: float|null
 *   }>,
 *   reference: array{user_accounts: int}
 * }
 */
function aidunite_analytics_get_team_funnel_summary() {
    global $wpdb;

    $counts = [
        'team_created' => aidunite_analytics_funnel_count_teams_created(),
        'team_active' => aidunite_analytics_funnel_count_teams_active(),
        'schedule_registered' => aidunite_analytics_funnel_count_distinct_schedule_teams(null),
        'recruit_published' => aidunite_analytics_funnel_count_distinct_schedule_teams('recruit'),
        'application' => aidunite_analytics_funnel_count_distinct_mr_teams('application'),
        'approval' => aidunite_analytics_funnel_count_distinct_mr_teams('approval'),
        'established' => aidunite_analytics_funnel_count_distinct_mr_teams('established'),
        'reuse' => aidunite_analytics_funnel_count_teams_reuse_established(),
    ];

    $steps = [];
    $groups_out = [];
    $group_defs = aidunite_analytics_get_team_funnel_groups();
    $prev_count = null;
    $current_group = null;

    foreach (aidunite_analytics_get_team_funnel_steps() as $def) {
        $id = $def['id'];
        $group = (string) ($def['group'] ?? 'onboarding');
        if ($current_group !== $group) {
            $prev_count = null;
            $current_group = $group;
        }

        $count = (int) ($counts[$id] ?? 0);
        $step_rate = null;
        $leave_rate = null;
        if ($prev_count !== null && $prev_count > 0) {
            $step_rate = round(($count / $prev_count) * 100, 1);
            $leave_rate = round((1 - ($count / $prev_count)) * 100, 1);
        }

        $step_row = [
            'id' => $id,
            'label' => $def['label'],
            'order' => (int) $def['order'],
            'group' => $group,
            'count' => $count,
            'prev_count' => $prev_count,
            'step_rate' => $step_rate,
            'leave_rate' => $leave_rate,
            'rate_note' => ($prev_count !== null && (int) $prev_count === 0) ? '前段階0件' : '',
        ];
        $steps[] = $step_row;

        if (!isset($groups_out[$group])) {
            $meta = $group_defs[$group] ?? ['title' => $group, 'description' => '', 'step_ids' => []];
            $groups_out[$group] = [
                'id' => $group,
                'title' => $meta['title'],
                'description' => $meta['description'],
                'steps' => [],
            ];
        }
        $groups_out[$group]['steps'][] = $step_row;
        $prev_count = $count;
    }

    $user_accounts = function_exists('aidunite_dashboard_count_users')
        ? aidunite_dashboard_count_users(null, null)
        : (int) count_users()['total_users'];

    return [
        'steps' => $steps,
        'groups' => array_values($groups_out),
        'reference' => [
            'user_accounts' => $user_accounts,
            'teams_active' => (int) ($counts['team_active'] ?? 0),
            'teams_established' => (int) ($counts['established'] ?? 0),
            'teams_high_value' => aidunite_analytics_count_high_value_teams(),
        ],
    ];
}

/**
 * 募集・申請・成立のいずれかがあるチーム数（経営KPI）
 */
function aidunite_analytics_count_high_value_teams() {
    global $wpdb;
    $recruit = aidunite_analytics_funnel_count_distinct_schedule_teams('recruit');
    $apply = aidunite_analytics_funnel_count_distinct_mr_teams('application');
    $est = aidunite_analytics_funnel_count_distinct_mr_teams('established');
    return max($recruit, $apply, $est);
}

/**
 * チーム作成: team 投稿が存在（publish / pending）
 */
function aidunite_analytics_funnel_count_teams_created() {
    global $wpdb;
    return (int) $wpdb->get_var(
        "SELECT COUNT(DISTINCT ID) FROM {$wpdb->posts}
         WHERE post_type = 'team' AND post_status IN ('publish', 'pending')"
    );
}

/**
 * チーム承認済み: publish かつ team_status が active または未設定
 */
function aidunite_analytics_funnel_count_teams_active() {
    global $wpdb;
    return (int) $wpdb->get_var(
        "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
         LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'team_status'
         WHERE p.post_type = 'team' AND p.post_status = 'publish'
         AND (pm.meta_id IS NULL OR pm.meta_value IN ('', 'active'))"
    );
}

/**
 * schedule の team_id ユニーク数（intent 指定時は recruit のみ）
 *
 * @param string|null $intent confirmed|recruit|tentative|null
 */
function aidunite_analytics_funnel_count_distinct_schedule_teams($intent = null) {
    return count(aidunite_analytics_funnel_collect_schedule_team_ids($intent));
}

/**
 * schedule に紐づく team_id（meta または投稿者の team_id）
 *
 * @param string|null $intent
 * @return int[]
 */
function aidunite_analytics_funnel_collect_schedule_team_ids($intent = null) {
    $args = [
        'post_type' => 'schedule',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
    ];
    if ($intent !== null) {
        $args['meta_query'] = [
            [
                'key' => 'intent',
                'value' => $intent,
            ],
        ];
    }

    $ids = get_posts($args);
    $team_ids = [];
    foreach ($ids as $schedule_id) {
        $tid = (int) get_post_meta($schedule_id, 'team_id', true);
        if ($tid <= 0) {
            $author = (int) get_post_field('post_author', $schedule_id);
            if ($author > 0) {
                $tid = (int) get_user_meta($author, 'team_id', true);
            }
        }
        if ($tid > 0) {
            $team_ids[$tid] = true;
        }
    }
    return array_map('intval', array_keys($team_ids));
}

/**
 * match_request の from_team_id ユニーク数
 *
 * @param string $stage application|approval|established
 */
function aidunite_analytics_funnel_count_distinct_mr_teams($stage) {
    global $wpdb;

    $bot_exclude = function_exists('aidunite_analytics_sql_exclude_onboarding_bot_mr')
        ? aidunite_analytics_sql_exclude_onboarding_bot_mr()
        : '';

    if ($stage === 'application') {
        $excluded = aidunite_analytics_funnel_mr_excluded_status_meta_values();
        $placeholders = implode(',', array_fill(0, count($excluded), '%s'));
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT pm_team.meta_value) FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm_st ON p.ID = pm_st.post_id AND pm_st.meta_key = 'status'
                 INNER JOIN {$wpdb->postmeta} pm_team ON p.ID = pm_team.post_id AND pm_team.meta_key = 'from_team_id'
                 WHERE p.post_type = 'match_request' AND p.post_status IN ('publish', 'draft')
                 AND pm_st.meta_value NOT IN ({$placeholders})
                 AND pm_team.meta_value != '' AND pm_team.meta_value != '0'{$bot_exclude}",
                ...$excluded
            )
        );
    }
    if ($stage === 'approval') {
        $statuses = aidunite_analytics_funnel_mr_approval_status_meta_values();
    } elseif ($stage === 'established') {
        $statuses = aidunite_analytics_funnel_mr_established_status_meta_values();
    } else {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
    $bot_exclude = function_exists('aidunite_analytics_sql_exclude_onboarding_bot_mr')
        ? aidunite_analytics_sql_exclude_onboarding_bot_mr()
        : '';

    return (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(DISTINCT pm_team.meta_value) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_st ON p.ID = pm_st.post_id AND pm_st.meta_key = 'status'
             INNER JOIN {$wpdb->postmeta} pm_team ON p.ID = pm_team.post_id AND pm_team.meta_key = 'from_team_id'
             WHERE p.post_type = 'match_request' AND p.post_status IN ('publish', 'draft')
             AND pm_st.meta_value IN ({$placeholders})
             AND pm_team.meta_value != '' AND pm_team.meta_value != '0'{$bot_exclude}",
            ...$statuses
        )
    );
}

/**
 * established が2件以上ある from_team_id のユニーク数
 */
function aidunite_analytics_funnel_count_teams_reuse_established() {
    global $wpdb;
    $statuses = aidunite_analytics_funnel_mr_established_status_meta_values();
    $placeholders = implode(',', array_fill(0, count($statuses), '%s'));

    return (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM (
                SELECT pm_team.meta_value AS team_id
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm_st ON p.ID = pm_st.post_id AND pm_st.meta_key = 'status'
                INNER JOIN {$wpdb->postmeta} pm_team ON p.ID = pm_team.post_id AND pm_team.meta_key = 'from_team_id'
                WHERE p.post_type = 'match_request' AND p.post_status IN ('publish', 'draft')
                AND pm_st.meta_value IN ({$placeholders})
                AND pm_team.meta_value != '' AND pm_team.meta_value != '0'
                GROUP BY pm_team.meta_value
                HAVING COUNT(DISTINCT p.ID) >= 2
            ) AS reuse_teams",
            ...$statuses
        )
    );
}

/**
 * 指定段階に到達しているチーム ID 一覧（ドリルダウン用・上限付き）
 *
 * @param string $step_id
 * @param int $limit
 * @return int[]
 */
function aidunite_analytics_get_team_ids_for_funnel_step($step_id, $limit = 500) {
    global $wpdb;
    $limit = max(1, min(2000, (int) $limit));
    $step_id = sanitize_key($step_id);

    if ($step_id === 'reuse') {
        $statuses = aidunite_analytics_funnel_mr_established_status_meta_values();
        $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
        $rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT pm_team.meta_value AS team_id
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm_st ON p.ID = pm_st.post_id AND pm_st.meta_key = 'status'
                INNER JOIN {$wpdb->postmeta} pm_team ON p.ID = pm_team.post_id AND pm_team.meta_key = 'from_team_id'
                WHERE p.post_type = 'match_request' AND p.post_status IN ('publish', 'draft')
                AND pm_st.meta_value IN ({$placeholders})
                AND pm_team.meta_value != '' AND pm_team.meta_value != '0'
                GROUP BY pm_team.meta_value
                HAVING COUNT(DISTINCT p.ID) >= 2
                LIMIT %d",
                ...array_merge($statuses, [$limit])
            )
        );
        return array_map('intval', $rows ?: []);
    }

    if (in_array($step_id, ['application', 'approval', 'established'], true)) {
        $stage = $step_id;
        if ($stage === 'application') {
            $excluded = aidunite_analytics_funnel_mr_excluded_status_meta_values();
            $placeholders = implode(',', array_fill(0, count($excluded), '%s'));
            $rows = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT pm_team.meta_value FROM {$wpdb->posts} p
                     INNER JOIN {$wpdb->postmeta} pm_st ON p.ID = pm_st.post_id AND pm_st.meta_key = 'status'
                     INNER JOIN {$wpdb->postmeta} pm_team ON p.ID = pm_team.post_id AND pm_team.meta_key = 'from_team_id'
                     WHERE p.post_type = 'match_request' AND p.post_status IN ('publish', 'draft')
                     AND pm_st.meta_value NOT IN ({$placeholders})
                     AND pm_team.meta_value != '' AND pm_team.meta_value != '0'
                     LIMIT %d",
                    ...array_merge($excluded, [$limit])
                )
            );
            return array_map('intval', $rows ?: []);
        }
        $statuses = $stage === 'approval'
            ? aidunite_analytics_funnel_mr_approval_status_meta_values()
            : aidunite_analytics_funnel_mr_established_status_meta_values();
        $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
        $rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT pm_team.meta_value FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm_st ON p.ID = pm_st.post_id AND pm_st.meta_key = 'status'
                 INNER JOIN {$wpdb->postmeta} pm_team ON p.ID = pm_team.post_id AND pm_team.meta_key = 'from_team_id'
                 WHERE p.post_type = 'match_request' AND p.post_status IN ('publish', 'draft')
                 AND pm_st.meta_value IN ({$placeholders})
                 AND pm_team.meta_value != '' AND pm_team.meta_value != '0'
                 LIMIT %d",
                ...array_merge($statuses, [$limit])
            )
        );
        return array_map('intval', $rows ?: []);
    }

    if ($step_id === 'recruit_published') {
        return array_slice(aidunite_analytics_funnel_collect_schedule_team_ids('recruit'), 0, $limit);
    }

    if ($step_id === 'schedule_registered') {
        return array_slice(aidunite_analytics_funnel_collect_schedule_team_ids(null), 0, $limit);
    }

    if ($step_id === 'team_active') {
        $rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT p.ID FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'team_status'
                 WHERE p.post_type = 'team' AND p.post_status = 'publish'
                 AND (pm.meta_id IS NULL OR pm.meta_value IN ('', 'active'))
                 LIMIT %d",
                $limit
            )
        );
        return array_map('intval', $rows ?: []);
    }

    if ($step_id === 'team_created') {
        $rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT ID FROM {$wpdb->posts}
                 WHERE post_type = 'team' AND post_status IN ('publish', 'pending')
                 LIMIT %d",
                $limit
            )
        );
        return array_map('intval', $rows ?: []);
    }

    return [];
}
