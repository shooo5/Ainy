<?php
/**
 * 解約・退会・解散の出口ゲート（§12B）
 *
 * @package AidUnite
 * @see docs/spec/payment.md §12B
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @return array<string, mixed>
 */
function aidunite_payment_exit_get_holiday_ranges() {
    $config = function_exists('aidunite_get_payment_config') ? aidunite_get_payment_config() : [];
    $ranges = $config['exit_gates']['major_holiday_ranges'] ?? [
        ['start' => '12-29', 'end' => '01-03', 'cross_year' => true],
        ['start' => '04-29', 'end' => '05-05', 'cross_year' => false],
        ['start' => '08-13', 'end' => '08-16', 'cross_year' => false],
    ];

    return is_array($ranges) ? $ranges : [];
}

/**
 * @param string $date_ymd Y-m-d
 * @return bool
 */
function aidunite_payment_exit_date_in_major_holiday($date_ymd) {
    $date_ymd = trim($date_ymd);
    if ($date_ymd === '') {
        return false;
    }
    $ts = strtotime($date_ymd);
    if ($ts === false) {
        return false;
    }
    $md = date('m-d', $ts);
    $year = (int) date('Y', $ts);

    foreach (aidunite_payment_exit_get_holiday_ranges() as $range) {
        if (!is_array($range)) {
            continue;
        }
        $start = (string) ($range['start'] ?? '');
        $end = (string) ($range['end'] ?? '');
        $cross = !empty($range['cross_year']);
        if ($start === '' || $end === '') {
            continue;
        }
        if ($cross) {
            if ($md >= $start || $md <= $end) {
                return true;
            }
            continue;
        }
        if ($md >= $start && $md <= $end) {
            return true;
        }
    }

    return false;
}

/**
 * @param int $year
 * @param int $weekday 0=Sun .. 6=Sat
 * @return string Y-m-d
 */
function aidunite_payment_exit_first_weekday_of_month($year, $weekday) {
    $year = (int) $year;
    $weekday = (int) $weekday;
    $cursor = strtotime(sprintf('%04d-%02d-01', $year, 1));
    while ((int) date('w', $cursor) !== $weekday) {
        $cursor = strtotime('+1 day', $cursor);
    }

    return date('Y-m-d', $cursor);
}

/**
 * @param string $date_ymd
 * @return bool
 */
function aidunite_payment_exit_is_first_weekend_or_holiday_of_month($date_ymd) {
    $ts = strtotime($date_ymd);
    if ($ts === false) {
        return false;
    }
    $year = (int) date('Y', $ts);
    $first_sat = aidunite_payment_exit_first_weekday_of_month($year, 6);
    $first_sun = aidunite_payment_exit_first_weekday_of_month($year, 0);
    if ($date_ymd === $first_sat || $date_ymd === $first_sun) {
        return true;
    }
    if (aidunite_payment_exit_date_in_major_holiday($date_ymd)) {
        return true;
    }

    return false;
}

/**
 * B-1: 手続き日が当月末7日以内 かつ 試合日が翌月1〜7日
 *
 * @param string $procedure_date Y-m-d
 * @param string $match_date Y-m-d
 */
function aidunite_payment_exit_is_gate_b1($procedure_date, $match_date) {
    $pts = strtotime($procedure_date);
    $mts = strtotime($match_date);
    if ($pts === false || $mts === false) {
        return false;
    }
    $proc_month = date('Y-m', $pts);
    $match_month = date('Y-m', $mts);
    $next_month = date('Y-m', strtotime('first day of next month', $pts));
    if ($match_month !== $next_month) {
        return false;
    }
    $month_end = strtotime('last day of this month', $pts);
    $days_to_end = (int) floor(($month_end - $pts) / DAY_IN_SECONDS);
    if ($days_to_end > 6) {
        return false;
    }
    $match_day = (int) date('j', $mts);

    return $match_day >= 1 && $match_day <= 7;
}

/**
 * B-2: 翌月の第一土日祝・大型連休
 *
 * @param string $procedure_date
 * @param string $match_date
 */
function aidunite_payment_exit_is_gate_b2($procedure_date, $match_date) {
    $pts = strtotime($procedure_date);
    $mts = strtotime($match_date);
    if ($pts === false || $mts === false) {
        return false;
    }
    $next_month = date('Y-m', strtotime('first day of next month', $pts));
    if (date('Y-m', $mts) !== $next_month) {
        return false;
    }

    return aidunite_payment_exit_is_first_weekend_or_holiday_of_month($match_date);
}

/**
 * @param string $procedure_date
 * @param string $match_date
 */
function aidunite_payment_exit_match_is_gate_b($procedure_date, $match_date) {
    return aidunite_payment_exit_is_gate_b1($procedure_date, $match_date)
        || aidunite_payment_exit_is_gate_b2($procedure_date, $match_date);
}

/**
 * @param int $request_id
 * @param int $team_id
 * @return string Y-m-d
 */
function aidunite_payment_exit_match_request_primary_date($request_id, $team_id) {
    $request_id = (int) $request_id;
    $team_id = (int) $team_id;
    if ($request_id <= 0) {
        return '';
    }
    $schedule_ids = [];
    $canonical = function_exists('aidunite_match_request_read_canonical_meta')
        ? aidunite_match_request_read_canonical_meta($request_id)
        : [];
    foreach (['my_schedule_id', 'to_schedule_id', 'from_schedule_id'] as $key) {
        $sid = (int) ($canonical[$key] ?? get_post_meta($request_id, $key, true));
        if ($key === 'to_schedule_id' && $sid === 9999) {
            $sid = 0;
        }
        if ($sid > 0) {
            $schedule_ids[$sid] = true;
        }
    }
    if (function_exists('aidunite_resolve_match_game_id_for_match_request')) {
        $gid = (int) aidunite_resolve_match_game_id_for_match_request($request_id);
        if ($gid > 0) {
            $schedule_ids[$gid] = true;
        }
    }
    $dates = [];
    foreach (array_keys($schedule_ids) as $sid) {
        $d = function_exists('aidunite_schedule_read_normalized_date')
            ? aidunite_schedule_read_normalized_date((int) $sid)
            : '';
        if ($d !== '') {
            $dates[] = $d;
        }
    }
    if ($dates === []) {
        return '';
    }
    sort($dates);

    return $dates[0];
}

/**
 * @param int   $team_id
 * @param array $statuses
 * @return array<int, array{request_id:int,status:string,match_date:string}>
 */
function aidunite_payment_exit_collect_team_match_rows($team_id, array $statuses = ['accepted', 'established']) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return [];
    }
    $requests = get_posts([
        'post_type' => 'match_request',
        'post_status' => 'any',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => [
            'relation' => 'AND',
            [
                'relation' => 'OR',
                ['key' => 'from_team_id', 'value' => (string) $team_id, 'compare' => '='],
                ['key' => 'to_team_id', 'value' => (string) $team_id, 'compare' => '='],
            ],
            ['key' => 'status', 'value' => $statuses, 'compare' => 'IN'],
        ],
    ]);
    $rows = [];
    foreach ($requests ?: [] as $rid) {
        $rid = (int) $rid;
        $status = function_exists('aidunite_match_request_read_canonical_meta')
            ? (string) (aidunite_match_request_read_canonical_meta($rid)['status'] ?? '')
            : (string) get_post_meta($rid, 'status', true);
        if (function_exists('aidunite_match_request_read_status_normalized')) {
            $status = (string) aidunite_match_request_read_status_normalized($rid);
        }
        $date = aidunite_payment_exit_match_request_primary_date($rid, $team_id);
        if ($date === '') {
            continue;
        }
        $rows[] = [
            'request_id' => $rid,
            'status' => $status,
            'match_date' => $date,
        ];
    }

    return $rows;
}

/**
 * @param string|null $procedure_date Y-m-d
 */
function aidunite_payment_exit_first_day_next_month($procedure_date = null) {
    $ts = $procedure_date ? strtotime($procedure_date) : time();
    if ($ts === false) {
        $ts = time();
    }

    return date('Y-m-01', strtotime('first day of next month', $ts));
}

/**
 * @param string $date_ymd
 * @param string|null $procedure_date
 */
function aidunite_payment_exit_is_on_or_after_next_month($date_ymd, $procedure_date = null) {
    $date_ymd = trim($date_ymd);
    if ($date_ymd === '') {
        return false;
    }

    return $date_ymd >= aidunite_payment_exit_first_day_next_month($procedure_date);
}

/**
 * @param int         $team_id
 * @param string|null $procedure_date Y-m-d
 * @return array{
 *   can_start:bool,
 *   gate_a_blocked:bool,
 *   gate_b_matches:array,
 *   gate_a_matches:array,
 *   messages:array
 * }
 */
function aidunite_payment_exit_evaluate_gates($team_id, $procedure_date = null) {
    $team_id = (int) $team_id;
    $procedure_date = $procedure_date ?: current_time('Y-m-d');
    $result = [
        'can_start' => true,
        'gate_a_blocked' => false,
        'gate_b_matches' => [],
        'gate_a_matches' => [],
        'messages' => [],
    ];
    $rows = aidunite_payment_exit_collect_team_match_rows($team_id);
    foreach ($rows as $row) {
        $match_date = (string) $row['match_date'];
        if (!aidunite_payment_exit_is_on_or_after_next_month($match_date, $procedure_date)) {
            continue;
        }
        if (aidunite_payment_exit_match_is_gate_b($procedure_date, $match_date)) {
            $result['gate_b_matches'][] = $row;
            continue;
        }
        $result['gate_a_matches'][] = $row;
        $result['gate_a_blocked'] = true;
        $result['can_start'] = false;
    }
    if ($result['gate_a_blocked']) {
        $result['messages'][] = '翌月以降の試合が残っています。先に試合をキャンセルしてください。';
    }

    return $result;
}

/**
 * @param int    $team_id
 * @param string $exit_type cancel|withdraw|dissolve
 * @param string|null $procedure_date
 */
function aidunite_payment_exit_compute_completion_date($team_id, $exit_type, $procedure_date = null) {
    $team_id = (int) $team_id;
    $procedure_date = $procedure_date ?: current_time('Y-m-d');
    $pts = strtotime($procedure_date);
    if ($pts === false) {
        $pts = time();
    }
    $candidates = [date('Y-m-t', $pts)];
    if ($exit_type === 'dissolve') {
        $delay = defined('AIDUNITE_WITHDRAWAL_REP_DELAY_SECONDS') ? (int) AIDUNITE_WITHDRAWAL_REP_DELAY_SECONDS : 30 * DAY_IN_SECONDS;
        $candidates[] = date('Y-m-d', $pts + $delay);
    }
    $gates = aidunite_payment_exit_evaluate_gates($team_id, $procedure_date);
    foreach ($gates['gate_b_matches'] as $row) {
        $mts = strtotime((string) $row['match_date']);
        if ($mts !== false) {
            $candidates[] = date('Y-m-d', strtotime('+1 day', $mts));
        }
    }
    rsort($candidates);

    return $candidates[0];
}

/**
 * @param int    $team_id
 * @param int    $user_id
 * @param string $exit_type cancel|withdraw|dissolve
 * @param string $complete_at Y-m-d
 * @return bool
 */
function aidunite_payment_exit_write_pending($team_id, $user_id, $exit_type, $complete_at) {
    return function_exists('aidunite_payment_exit_persist_write_pending')
        ? aidunite_payment_exit_persist_write_pending($team_id, $user_id, $exit_type, $complete_at)
        : false;
}

/**
 * @param int $team_id
 */
function aidunite_payment_exit_clear_pending($team_id) {
    if (function_exists('aidunite_payment_exit_persist_clear_pending')) {
        aidunite_payment_exit_persist_clear_pending($team_id);
    }
}

/**
 * @param int $team_id
 */
function aidunite_payment_exit_team_in_grace($team_id) {
    return aidunite_payment_exit_read_pending((int) $team_id) !== null;
}

/**
 * @param int         $team_id
 * @param string|null $date_ymd
 */
function aidunite_payment_exit_grace_blocks_date($team_id, $date_ymd) {
    if (!aidunite_payment_exit_team_in_grace($team_id)) {
        return false;
    }
    $date_ymd = trim((string) $date_ymd);
    if ($date_ymd === '') {
        return false;
    }

    return aidunite_payment_exit_is_on_or_after_next_month($date_ymd);
}

/**
 * @param int $team_id
 * @return WP_Error|true
 */
function aidunite_payment_exit_assert_grace_allows_date($team_id, $date_ymd) {
    if (aidunite_payment_exit_grace_blocks_date($team_id, $date_ymd)) {
        return new WP_Error(
            'exit_grace_next_month_blocked',
            '解約・退会・解散の手続き中は、翌月以降の試合募集・申請・成立はできません。',
            ['status' => 403]
        );
    }

    return true;
}

/**
 * @param int $team_id
 */
function aidunite_payment_exit_purge_next_month_items($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return ['recruits' => 0, 'pending' => 0];
    }
    $next = aidunite_payment_exit_first_day_next_month();
    $counts = ['recruits' => 0, 'pending' => 0];

    $schedules = get_posts([
        'post_type' => 'schedule',
        'post_status' => 'any',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => [
            ['key' => 'team_id', 'value' => (string) $team_id, 'compare' => '='],
        ],
    ]);
    foreach ($schedules ?: [] as $sid) {
        $sid = (int) $sid;
        $d = function_exists('aidunite_schedule_read_normalized_date')
            ? aidunite_schedule_read_normalized_date($sid)
            : '';
        if ($d === '' || $d < $next) {
            continue;
        }
        $intent = function_exists('aidunite_schedule_read_intent')
            ? aidunite_schedule_read_intent($sid)
            : '';
        if ($intent === 'recruit') {
            wp_delete_post($sid, true);
            $counts['recruits']++;
        }
    }

    $pending = aidunite_payment_exit_collect_team_match_rows($team_id, ['pending', 'accepted']);
    foreach ($pending as $row) {
        if ((string) $row['match_date'] < $next) {
            continue;
        }
        $rid = (int) $row['request_id'];
        if (function_exists('aidunite_match_request_submit_update_status')) {
            aidunite_match_request_submit_update_status($rid, 'cancel', get_current_user_id());
        } elseif (function_exists('aidunite_update_match_request_status_meta')) {
            aidunite_update_match_request_status_meta($rid, 'canceled');
        }
        $counts['pending']++;
    }

    return $counts;
}

/**
 * ユーザーがまだ代表者であるチーム ID（解散手続き中を除く未整理）
 *
 * @param int $user_id
 * @return int[]
 */
function aidunite_payment_exit_representative_unresolved_team_ids($user_id) {
    $user_id = (int) $user_id;
    if (!function_exists('aidunite_get_representative_team_ids')) {
        return [];
    }
    $unresolved = [];
    foreach (aidunite_get_representative_team_ids($user_id) as $team_id) {
        $team_id = (int) $team_id;
        if ($team_id <= 0) {
            continue;
        }
        if (function_exists('aidunite_team_settings_user_is_leader_of_team')
            && !aidunite_team_settings_user_is_leader_of_team($user_id, $team_id)) {
            continue;
        }
        $pending = aidunite_payment_exit_read_pending($team_id);
        if (is_array($pending) && (string) ($pending['type'] ?? '') === 'dissolve') {
            continue;
        }
        $unresolved[] = $team_id;
    }

    return $unresolved;
}

/**
 * @param int $user_id
 * @return true|WP_Error
 */
function aidunite_payment_exit_representative_may_withdraw($user_id) {
    $user_id = (int) $user_id;
    $unresolved = aidunite_payment_exit_representative_unresolved_team_ids($user_id);
    if ($unresolved !== []) {
        $names = array_map(static function ($team_id) {
            $team = get_post((int) $team_id);

            return $team ? $team->post_title : ('ID:' . $team_id);
        }, $unresolved);

        return new WP_Error(
            'rep_teams_unresolved',
            sprintf(
                '代表者として登録されているチーム（%s）があります。各チームで代表者を譲渡するか、解散手続きを完了してから退会してください。',
                implode('・', $names)
            ),
            ['status' => 400, 'team_ids' => $unresolved]
        );
    }

    if (!function_exists('aidunite_get_representative_team_ids')) {
        return true;
    }
    foreach (aidunite_get_representative_team_ids($user_id) as $team_id) {
        $pending = aidunite_payment_exit_read_pending((int) $team_id);
        if ($pending === null || (string) ($pending['type'] ?? '') !== 'dissolve') {
            continue;
        }
        $gates = aidunite_payment_exit_evaluate_gates((int) $team_id);
        if (!$gates['can_start']) {
            $team = get_post((int) $team_id);
            $name = $team ? $team->post_title : ('ID:' . $team_id);

            return new WP_Error(
                'gate_a_blocked',
                sprintf('チーム「%s」に翌月以降の試合が残っています。先に試合をキャンセルしてから解散退会してください。', $name),
                ['status' => 400]
            );
        }
    }

    return true;
}

/**
 * カレンダー表示用の解散マーカー
 *
 * @param int    $user_id
 * @param string $start_date Y-m-d
 * @param string $end_date Y-m-d
 * @param array  $fetch_args
 * @return array<int, array{date:string,team_id:int,team_name:string,label:string}>
 */
function aidunite_payment_exit_collect_dissolution_markers_for_user($user_id, $start_date, $end_date, array $fetch_args = []) {
    $user_id = (int) $user_id;
    $start_date = trim((string) $start_date);
    $end_date = trim((string) $end_date);
    if ($user_id <= 0 || $start_date === '' || $end_date === '') {
        return [];
    }
    $team_ids = [];
    if (!empty($fetch_args['team_id'])) {
        $team_ids[] = (int) $fetch_args['team_id'];
    } elseif (($fetch_args['scope'] ?? '') === 'managed' && function_exists('aidunite_get_managed_teams_for_schedule_ui')) {
        foreach (aidunite_get_managed_teams_for_schedule_ui($user_id) as $row) {
            if (!empty($row['id'])) {
                $team_ids[] = (int) $row['id'];
            }
        }
    } elseif (function_exists('aidunite_get_member_team_ids_for_schedule_view')) {
        $team_ids = aidunite_get_member_team_ids_for_schedule_view($user_id);
    }
    if ($team_ids === [] && function_exists('aidunite_user_read_primary_team_id')) {
        $tid = (int) aidunite_user_read_primary_team_id($user_id);
        if ($tid > 0) {
            $team_ids[] = $tid;
        }
    }
    $team_ids = array_values(array_unique(array_filter(array_map('intval', $team_ids))));
    $markers = [];
    foreach ($team_ids as $team_id) {
        $date = function_exists('aidunite_payment_exit_read_dissolution_date')
            ? aidunite_payment_exit_read_dissolution_date($team_id)
            : '';
        if ($date === '' || $date < $start_date || $date > $end_date) {
            continue;
        }
        $pending = aidunite_payment_exit_read_pending($team_id);
        if ($pending === null || (string) ($pending['type'] ?? '') !== 'dissolve') {
            continue;
        }
        $team = get_post($team_id);
        $markers[] = [
            'date' => $date,
            'team_id' => $team_id,
            'team_name' => $team ? $team->post_title : '',
            'label' => 'チーム解散予定日',
        ];
    }
    usort($markers, static function ($a, $b) {
        return strcmp((string) $a['date'], (string) $b['date']);
    });

    return $markers;
}

/**
 * 完了日到達分を処理（cron）
 */
function aidunite_payment_exit_process_scheduled_completions() {
    $teams = get_posts([
        'post_type' => 'team',
        'post_status' => 'any',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => [
            ['key' => AIDUNITE_TEAM_PAYMENT_EXIT_PENDING_META, 'compare' => 'EXISTS'],
        ],
    ]);
    $today = current_time('Y-m-d');
    foreach ($teams ?: [] as $team_id) {
        $team_id = (int) $team_id;
        $pending = aidunite_payment_exit_read_pending($team_id);
        if ($pending === null) {
            continue;
        }
        $complete_at = (string) ($pending['complete_at'] ?? '');
        if ($complete_at === '' || $complete_at > $today) {
            continue;
        }
        $type = (string) ($pending['type'] ?? '');
        $user_id = (int) ($pending['user_id'] ?? 0);
        if ($type === 'cancel') {
            if (function_exists('aidunite_payment_exit_finalize_team_cancellation')) {
                aidunite_payment_exit_finalize_team_cancellation($team_id);
            }
            aidunite_payment_exit_clear_pending($team_id);
            continue;
        }
        if ($type === 'dissolve' && $user_id > 0) {
            if (function_exists('aidunite_payment_cancel_team_stripe_subscription_immediate')) {
                aidunite_payment_cancel_team_stripe_subscription_immediate($team_id, $user_id);
            }
            if (function_exists('aidunite_cancel_tuition_subscription')) {
                $members = get_users(['meta_key' => 'team_id', 'meta_value' => $team_id, 'fields' => 'ID']);
                foreach ((array) $members as $member_id) {
                    aidunite_cancel_tuition_subscription((int) $member_id, $team_id);
                }
            }
            if (function_exists('aidunite_detach_team_members_before_delete')) {
                aidunite_detach_team_members_before_delete($team_id, $user_id);
            }
            wp_delete_post($team_id, true);
            aidunite_payment_exit_clear_pending($team_id);
            $still_rep = function_exists('aidunite_get_representative_team_ids')
                ? aidunite_get_representative_team_ids($user_id)
                : [];
            if ($still_rep === [] && function_exists('aidunite_execute_withdrawal')) {
                aidunite_execute_withdrawal($user_id);
            }
        }
    }
}

add_action('aidunite_payment_exit_process_scheduled', 'aidunite_payment_exit_process_scheduled_completions');
add_action('init', static function () {
    if (get_transient('aidunite_payment_exit_cron_registered')) {
        return;
    }
    if (!wp_next_scheduled('aidunite_payment_exit_process_scheduled')) {
        wp_schedule_event(time(), 'hourly', 'aidunite_payment_exit_process_scheduled');
    }
    set_transient('aidunite_payment_exit_cron_registered', 1, DAY_IN_SECONDS);
}, 99);

/**
 * 初試合成立後モーダル: お支払い設定訪問後の再表示抑制（時間）
 */
function aidunite_payment_exit_get_first_match_payment_setup_snooze_hours() {
    $config = function_exists('aidunite_get_payment_config') ? aidunite_get_payment_config() : [];
    $hours = (int) ($config['first_match_billing_prompt']['payment_setup_snooze_hours'] ?? 48);

    return max(1, $hours);
}

/**
 * 初試合成立モーダル上部イラスト URL
 */
function aidunite_payment_exit_get_first_match_hero_image_url() {
    $hero_image_path = get_stylesheet_directory() . '/assets/images/初回試合成立.png';
    if (!is_readable($hero_image_path)) {
        return '';
    }

    return add_query_arg(
        'ver',
        (string) filemtime($hero_image_path),
        get_stylesheet_directory_uri() . '/assets/images/初回試合成立.png'
    );
}

/**
 * デザインリファレンス用：初試合成立・課金案内モーダルのサンプルペイロード
 *
 * @return array<string, mixed>
 */
function aidunite_payment_exit_get_first_match_billing_prompt_demo_payload() {
    $demo_days = 30;
    $demo_end = function_exists('aidunite_payment_resolve_trial_end_at_month_end')
        ? aidunite_payment_resolve_trial_end_at_month_end(
            current_time('mysql'),
            function_exists('aidunite_payment_get_default_trial_months') ? aidunite_payment_get_default_trial_months() : 2
        )
        : null;
    $trial_end_label = $demo_end instanceof DateTimeImmutable && function_exists('aidunite_payment_format_date_display')
        ? aidunite_payment_format_date_display($demo_end->format('Y-m-d H:i:s'))
        : '';
    if ($demo_end instanceof DateTimeImmutable) {
        $demo_days = max(1, (int) ceil(($demo_end->getTimestamp() - time()) / DAY_IN_SECONDS));
    }
    $highlight_text = function_exists('aidunite_payment_get_trial_copy')
        ? aidunite_payment_get_trial_copy()['highlight']
        : 'お申し込み月と翌月は無料です。';
    if ($trial_end_label !== '') {
        $highlight_text .= '（' . $trial_end_label . 'まで）';
    }

    $gift_icon = '';
    if (function_exists('aidunite_render_theme_icon')) {
        $gift_icon = aidunite_render_theme_icon('redeem', [
            'width' => '22',
            'height' => '22',
        ], 'payment-first-match-modal__gift-icon-svg aidunite-icon--inline');
    }

    return [
        'payment_setup_url' => home_url('/payment-setup'),
        'later_label' => 'あとで',
        'days_remaining' => $demo_days,
        'body_text' => "Ainyでは地域の試合機会を維持するための、\n月額2,000円（税込）でご利用いただけます。",
        'highlight_text' => $highlight_text,
        'hero_image_url' => aidunite_payment_exit_get_first_match_hero_image_url(),
        'gift_icon_html' => $gift_icon,
    ];
}

/**
 * 初試合成立直後に pending を立てるべきか（ボット除外・件数1）
 *
 * @param int $team_id
 * @param int $trigger_request_id 成立処理中の MR ID（同一リクエスト内の件数未反映を補正）
 */
function aidunite_payment_exit_team_qualifies_for_first_match_modal_pending($team_id, $trigger_request_id = 0) {
    $team_id = (int) $team_id;
    $trigger_request_id = (int) $trigger_request_id;
    if ($team_id <= 0) {
        return false;
    }
    if ($trigger_request_id > 0 && function_exists('aidunite_match_request_is_onboarding_bot')
        && aidunite_match_request_is_onboarding_bot($trigger_request_id)) {
        return false;
    }
    if (!function_exists('aidunite_team_count_real_established_matches')) {
        return false;
    }

    $count = aidunite_team_count_real_established_matches($team_id);
    if ($count === 1) {
        return true;
    }

    // 成立直後は get_posts が同一リクエスト内で 0 件になることがある
    if ($count === 0 && $trigger_request_id > 0) {
        $canonical = function_exists('aidunite_match_request_read_canonical_meta')
            ? aidunite_match_request_read_canonical_meta($trigger_request_id)
            : [];
        $from = (int) ($canonical['from_team_id'] ?? get_post_meta($trigger_request_id, 'from_team_id', true));
        $to = (int) ($canonical['to_team_id'] ?? get_post_meta($trigger_request_id, 'to_team_id', true));
        if (!in_array($team_id, [$from, $to], true)) {
            return false;
        }
        $status = strtolower((string) ($canonical['status'] ?? get_post_meta($trigger_request_id, 'status', true)));

        return in_array($status, ['established', '試合確定'], true);
    }

    return false;
}

/**
 * 実試合あり・未表示・未スヌーズのチームに pending を補完（機能導入前の成立チーム向け）
 *
 * @param int $team_id
 */
function aidunite_payment_exit_sync_first_match_modal_pending($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return;
    }
    if (function_exists('aidunite_payment_exit_read_first_match_modal_pending')
        && aidunite_payment_exit_read_first_match_modal_pending($team_id)) {
        return;
    }
    if (function_exists('aidunite_payment_exit_read_first_match_modal_shown')
        && aidunite_payment_exit_read_first_match_modal_shown($team_id)) {
        return;
    }
    if (function_exists('aidunite_payment_exit_read_first_match_modal_snooze_active')
        && aidunite_payment_exit_read_first_match_modal_snooze_active($team_id)) {
        return;
    }
    if (function_exists('aidunite_payment_exit_read_subscription_state')) {
        $sub = aidunite_payment_exit_read_subscription_state($team_id);
        if (!empty($sub['has_subscription'])) {
            return;
        }
    } elseif (function_exists('aidunite_get_team_stripe_subscription_id')
        && aidunite_get_team_stripe_subscription_id($team_id) !== '') {
        return;
    }
    if (!function_exists('aidunite_team_has_real_established_match')
        || !aidunite_team_has_real_established_match($team_id)) {
        return;
    }
    if (function_exists('aidunite_payment_exit_persist_set_first_match_modal_pending')) {
        aidunite_payment_exit_persist_set_first_match_modal_pending($team_id);
    }
}

/**
 * 初試合成立時ペイロード（§12A）
 *
 * @param int $team_id
 * @return array<string, mixed>|null
 */
function aidunite_payment_exit_should_show_first_match_billing_prompt($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return false;
    }
    if (!function_exists('aidunite_team_has_real_established_match')
        || !aidunite_team_has_real_established_match($team_id)) {
        return false;
    }
    if (function_exists('aidunite_payment_exit_read_first_match_modal_shown')
        && aidunite_payment_exit_read_first_match_modal_shown($team_id)) {
        return false;
    }
    if (function_exists('aidunite_payment_exit_read_first_match_modal_snooze_active')
        && aidunite_payment_exit_read_first_match_modal_snooze_active($team_id)) {
        return false;
    }
    if (function_exists('aidunite_payment_exit_sync_first_match_modal_pending')) {
        aidunite_payment_exit_sync_first_match_modal_pending($team_id);
    }
    if (function_exists('aidunite_payment_exit_read_first_match_modal_pending')
        && !aidunite_payment_exit_read_first_match_modal_pending($team_id)) {
        return false;
    }
    if (function_exists('aidunite_payment_exit_read_subscription_state')) {
        $sub = aidunite_payment_exit_read_subscription_state($team_id);
        if (!empty($sub['has_subscription'])) {
            return false;
        }
    } elseif (function_exists('aidunite_get_team_stripe_subscription_id')
        && aidunite_get_team_stripe_subscription_id($team_id) !== '') {
        return false;
    }

    return true;
}

function aidunite_payment_exit_first_match_billing_prompt($team_id) {
    $team_id = (int) $team_id;
    if (!aidunite_payment_exit_should_show_first_match_billing_prompt($team_id)) {
        return null;
    }
    $days_remaining = function_exists('aidunite_payment_get_trial_days_remaining')
        ? aidunite_payment_get_trial_days_remaining($team_id)
        : null;
    $trial_end_label = function_exists('aidunite_payment_format_trial_end_label')
        ? aidunite_payment_format_trial_end_label($team_id)
        : '';

    $later_label = 'あとで';

    $highlight_text = function_exists('aidunite_payment_get_trial_copy')
        ? aidunite_payment_get_trial_copy()['highlight']
        : 'お申し込み月と翌月は無料です。';
    if ($trial_end_label !== '') {
        $highlight_text .= '（' . $trial_end_label . 'まで）';
    }

    $hero_image_url = function_exists('aidunite_payment_exit_get_first_match_hero_image_url')
        ? aidunite_payment_exit_get_first_match_hero_image_url()
        : '';

    $gift_icon = '';
    if (function_exists('aidunite_render_theme_icon')) {
        $gift_icon = aidunite_render_theme_icon('redeem', [
            'width' => '22',
            'height' => '22',
        ], 'payment-first-match-modal__gift-icon-svg aidunite-icon--inline');
    }

    return [
        'payment_setup_url' => home_url('/payment-setup'),
        'later_label' => $later_label,
        'days_remaining' => $days_remaining,
        'trial_end_label' => $trial_end_label,
        'body_text' => "Ainyでは地域の試合機会を維持するための、\n月額2,000円（税込）でご利用いただけます。",
        'highlight_text' => $highlight_text,
        'hero_image_url' => $hero_image_url,
        'gift_icon_html' => $gift_icon,
    ];
}

/**
 * @param int $team_id
 */
function aidunite_payment_exit_mark_first_match_prompt_shown($team_id) {
    if (function_exists('aidunite_payment_exit_persist_mark_first_match_modal_shown')) {
        aidunite_payment_exit_persist_mark_first_match_modal_shown($team_id);
    }
}

/**
 * @param int $team_id
 */
function aidunite_payment_exit_mark_first_match_prompt_impression($team_id) {
    if (function_exists('aidunite_payment_exit_persist_clear_first_match_modal_pending')) {
        aidunite_payment_exit_persist_clear_first_match_modal_pending($team_id);
    }
}

/**
 * @param int $team_id
 */
function aidunite_payment_exit_snooze_first_match_prompt_payment_setup($team_id) {
    if (function_exists('aidunite_payment_exit_persist_snooze_first_match_modal_payment_setup')) {
        aidunite_payment_exit_persist_snooze_first_match_modal_payment_setup($team_id);
    }
}

/**
 * @param int $request_id
 */
function aidunite_payment_exit_on_match_established($request_id) {
    $request_id = (int) $request_id;
    if ($request_id <= 0) {
        return;
    }

    $is_bot = function_exists('aidunite_match_request_is_onboarding_bot')
        && aidunite_match_request_is_onboarding_bot($request_id);

    $canonical = function_exists('aidunite_match_request_read_canonical_meta')
        ? aidunite_match_request_read_canonical_meta($request_id)
        : [];
    $from = (int) ($canonical['from_team_id'] ?? get_post_meta($request_id, 'from_team_id', true));
    $to = (int) ($canonical['to_team_id'] ?? get_post_meta($request_id, 'to_team_id', true));
    foreach (array_unique(array_filter([$from, $to])) as $team_id) {
        do_action('aidunite_payment_first_match_established', $team_id, $request_id);

        if ($is_bot) {
            continue;
        }
        if (!function_exists('aidunite_payment_exit_team_qualifies_for_first_match_modal_pending')
            || !aidunite_payment_exit_team_qualifies_for_first_match_modal_pending($team_id, $request_id)) {
            continue;
        }
        if (function_exists('aidunite_payment_exit_persist_set_first_match_modal_pending')) {
            aidunite_payment_exit_persist_set_first_match_modal_pending($team_id);
        }
    }
}

add_action('aidunite_match_established', 'aidunite_payment_exit_on_match_established', 20, 1);
add_action('aidunite_after_match_established', 'aidunite_payment_exit_on_match_established', 25, 1);

/**
 * 試合キャンセル等で完了日を再計算
 */
function aidunite_payment_exit_maybe_recompute_on_match_status($meta_id, $post_id, $meta_key, $meta_value) {
    if ($meta_key !== 'status' || get_post_type((int) $post_id) !== 'match_request') {
        return;
    }
    $norm = strtolower((string) $meta_value);
    if (!in_array($norm, ['canceled', 'cancelled', 'rejected'], true)) {
        return;
    }
    $request_id = (int) $post_id;
    $canonical = function_exists('aidunite_match_request_read_canonical_meta')
        ? aidunite_match_request_read_canonical_meta($request_id)
        : [];
    $team_ids = array_unique(array_filter([
        (int) ($canonical['from_team_id'] ?? get_post_meta($request_id, 'from_team_id', true)),
        (int) ($canonical['to_team_id'] ?? get_post_meta($request_id, 'to_team_id', true)),
    ]));
    foreach ($team_ids as $team_id) {
        $pending = aidunite_payment_exit_read_pending($team_id);
        if ($pending === null) {
            continue;
        }
        $exit_type = (string) ($pending['type'] ?? 'cancel');
        $complete_at = aidunite_payment_exit_compute_completion_date($team_id, $exit_type);
        $pending['complete_at'] = $complete_at;
        if (function_exists('aidunite_payment_exit_persist_update_pending')) {
            aidunite_payment_exit_persist_update_pending($team_id, $pending);
        }
        if (function_exists('aidunite_team_write_payment_available_until_meta')) {
            aidunite_team_write_payment_available_until_meta($team_id, $complete_at);
        }
    }
}

add_action('updated_post_meta', 'aidunite_payment_exit_maybe_recompute_on_match_status', 20, 4);
