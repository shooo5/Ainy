<?php
/**
 * マッチ詳細：相手チームサイドバー用データ
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * チームの成立試合数（任意期間）
 *
 * @param int         $team_id
 * @param string|null $since_mysql established_at の下限（Y-m-d H:i:s）
 * @return int
 */
function aidunite_match_detail_count_established_for_team($team_id, $since_mysql = null) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return 0;
    }

    $meta_query = [
        'relation' => 'AND',
        [
            'key'     => 'status',
            'value'   => ['established', '試合確定'],
            'compare' => 'IN',
        ],
        [
            'relation' => 'OR',
            ['key' => 'from_team_id', 'value' => $team_id, 'compare' => '='],
            ['key' => 'to_team_id', 'value' => $team_id, 'compare' => '='],
        ],
    ];

    if ($since_mysql !== null && $since_mysql !== '') {
        $meta_query[] = [
            'key'     => 'established_at',
            'value'   => $since_mysql,
            'compare' => '>=',
            'type'    => 'DATETIME',
        ];
    }

    $query = new WP_Query([
        'post_type'              => 'match_request',
        'post_status'            => 'any',
        'posts_per_page'         => -1,
        'fields'                 => 'ids',
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'meta_query'             => $meta_query,
    ]);

    return is_array($query->posts) ? count($query->posts) : 0;
}

/**
 * @param int $team_id
 * @return array{total:int,recent:int}
 */
function aidunite_match_detail_opponent_match_stats($team_id) {
    $team_id = (int) $team_id;
    $since = gmdate('Y-m-d H:i:s', strtotime('-30 days', current_time('timestamp')));

    return [
        'total'  => aidunite_match_detail_count_established_for_team($team_id, null),
        'recent' => aidunite_match_detail_count_established_for_team($team_id, $since),
    ];
}

/**
 * @param int   $team_id
 * @param array $team_data
 * @return string[]
 */
function aidunite_match_detail_opponent_activity_lines($team_id, array $team_data, $venue_label = '') {
    $lines = [];

    if (!empty($team_data['region'])) {
        $lines[] = '活動地域: ' . (string) $team_data['region'];
    }

    if (!empty($team_data['sport']) && $team_data['sport'] !== 'スポーツ種目未設定') {
        $lines[] = 'スポーツ: ' . (string) $team_data['sport'];
    }

    if ($venue_label !== '') {
        $lines[] = '会場傾向: ' . $venue_label;
    }

    if (function_exists('aidunite_get_team_activity_profile')) {
        $profile = aidunite_get_team_activity_profile((int) $team_id);
        $area = trim((string) ($profile['activity_area'] ?? ''));
        if ($area !== '') {
            $lines[] = 'エリア: ' . $area;
        }
    }

    return $lines;
}

/**
 * @param string $time_str HH:MM
 * @return int|null
 */
function aidunite_match_detail_time_to_minutes($time_str) {
    $time_str = trim((string) $time_str);
    if ($time_str === '') {
        return null;
    }
    $parts = array_map('intval', explode(':', $time_str));
    if (!isset($parts[0]) || $parts[0] < 0 || $parts[0] > 23) {
        return null;
    }
    $minutes = isset($parts[1]) ? max(0, min(59, $parts[1])) : 0;

    return $parts[0] * 60 + $minutes;
}

/**
 * @param array{start?:string,end?:string} $a
 * @param array{start?:string,end?:string} $b
 */
function aidunite_match_detail_schedules_have_near_time_band(array $a, array $b) {
    $a_start = aidunite_match_detail_time_to_minutes($a['start'] ?? '');
    $a_end = aidunite_match_detail_time_to_minutes($a['end'] ?? '');
    $b_start = aidunite_match_detail_time_to_minutes($b['start'] ?? '');
    $b_end = aidunite_match_detail_time_to_minutes($b['end'] ?? '');

    if ($a_start === null || $a_end === null || $b_start === null || $b_end === null) {
        return false;
    }

    $overlap_start = max($a_start, $b_start);
    $overlap_end = min($a_end, $b_end);
    if ($overlap_end > $overlap_start) {
        return true;
    }

    return abs($a_start - $b_start) <= 120;
}

/**
 * 直近の schedule から最も多い曜日（0=日…6=土）を返す
 *
 * @param int $team_id
 * @param int $lookback_days
 * @return int|null
 */
function aidunite_match_detail_team_dominant_weekday($team_id, $lookback_days = 180) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return null;
    }

    $since = gmdate('Y-m-d', strtotime('-' . max(1, (int) $lookback_days) . ' days', current_time('timestamp')));
    $schedules = get_posts([
        'post_type'      => 'schedule',
        'post_status'    => 'publish',
        'posts_per_page' => 80,
        'fields'         => 'ids',
        'meta_query'     => [
            'relation' => 'AND',
            ['key' => 'team_id', 'value' => $team_id, 'compare' => '='],
            ['key' => 'schedule_date', 'value' => $since, 'compare' => '>=', 'type' => 'DATE'],
        ],
    ]);

    if (!is_array($schedules) || $schedules === []) {
        return null;
    }

    $counts = array_fill(0, 7, 0);
    foreach ($schedules as $sid) {
        $date = function_exists('aidunite_schedule_read_normalized_date')
            ? aidunite_schedule_read_normalized_date((int) $sid)
            : (string) get_post_meta((int) $sid, 'schedule_date', true);
        if ($date === '') {
            continue;
        }
        $ts = strtotime($date);
        if ($ts === false) {
            continue;
        }
        $counts[(int) gmdate('w', $ts)]++;
    }

    $max = max($counts);
    if ($max <= 0) {
        return null;
    }

    $day = array_search($max, $counts, true);

    return $day === false ? null : (int) $day;
}

/**
 * @param int $team_a
 * @param int $team_b
 */
function aidunite_match_detail_teams_have_past_established_match($team_a, $team_b) {
    $team_a = (int) $team_a;
    $team_b = (int) $team_b;
    if ($team_a <= 0 || $team_b <= 0 || $team_a === $team_b) {
        return false;
    }

    $query = new WP_Query([
        'post_type'      => 'match_request',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => [
            'relation' => 'AND',
            [
                'key'     => 'status',
                'value'   => ['established', '試合確定'],
                'compare' => 'IN',
            ],
            [
                'relation' => 'OR',
                [
                    'relation' => 'AND',
                    ['key' => 'from_team_id', 'value' => $team_a, 'compare' => '='],
                    ['key' => 'to_team_id', 'value' => $team_b, 'compare' => '='],
                ],
                [
                    'relation' => 'AND',
                    ['key' => 'from_team_id', 'value' => $team_b, 'compare' => '='],
                    ['key' => 'to_team_id', 'value' => $team_a, 'compare' => '='],
                ],
            ],
        ],
    ]);

    return is_array($query->posts) && $query->posts !== [];
}

/**
 * 共通点カード MVP（最大4項目）
 *
 * @param array<string, mixed> $context my_schedule_data, other_schedule_data
 * @return string[]
 */
function aidunite_match_detail_common_points($my_team_id, $other_team_id, array $my_team_data, array $other_team_data, array $context = []) {
    $points = [];
    $my_team_id = (int) $my_team_id;
    $other_team_id = (int) $other_team_id;
    $my_sched = isset($context['my_schedule_data']) && is_array($context['my_schedule_data']) ? $context['my_schedule_data'] : [];
    $other_sched = isset($context['other_schedule_data']) && is_array($context['other_schedule_data']) ? $context['other_schedule_data'] : [];

    if ($my_team_id > 0 && $other_team_id > 0 && function_exists('aidunite_team_activity_compare')) {
        if (aidunite_team_activity_compare($my_team_id, $other_team_id) === 'exact') {
            $points[] = '同じ活動エリア';
        }
    }

    if (
        $my_sched !== []
        && $other_sched !== []
        && aidunite_match_detail_schedules_have_near_time_band($my_sched, $other_sched)
    ) {
        $points[] = '活動時間帯が近い';
    }

    if ($my_team_id > 0 && $other_team_id > 0) {
        $my_day = aidunite_match_detail_team_dominant_weekday($my_team_id);
        $other_day = aidunite_match_detail_team_dominant_weekday($other_team_id);
        if ($my_day !== null && $other_day !== null && $my_day === $other_day) {
            $points[] = '同じ曜日に活動が多い';
        }
    }

    if ($my_team_id > 0 && $other_team_id > 0 && aidunite_match_detail_teams_have_past_established_match($my_team_id, $other_team_id)) {
        $points[] = '過去に対戦あり';
    }

    return $points;
}

/**
 * @param string $team_name
 * @return string
 */
function aidunite_match_detail_team_avatar_initial($team_name) {
    $team_name = trim((string) $team_name);
    if ($team_name === '') {
        return '?';
    }
    if (function_exists('mb_substr')) {
        return mb_substr($team_name, 0, 1, 'UTF-8');
    }

    return substr($team_name, 0, 1);
}

/**
 * 申請ステータス表示ラベル（相手チームカード用）
 *
 * @param string $application_status
 * @return string
 */
function aidunite_match_detail_application_status_label($application_status) {
    $map = [
        'received'              => '申請中',
        'applying'              => '申請中',
        'proposal_pending_accept' => '提案確認中',
        'reconfirm_required'    => '再確認待ち',
        'accepted'              => '承認済み',
        'established'           => '試合確定',
        'rejected'              => '拒否済み',
        'canceled'              => 'キャンセル済み',
    ];

    $status = (string) $application_status;
    if (isset($map[$status])) {
        return $map[$status];
    }

    return function_exists('aidunite_get_match_status_label')
        ? (string) aidunite_get_match_status_label('not_applied', 'text')
        : '未申請';
}

/**
 * 条件の一致状況行（マッチ詳細 UI）
 *
 * @param array<string, mixed> $args
 * @return array<int, array{key:string,icon:string,status:string,status_label:string,value:string,subtext:string}>
 */
function aidunite_match_detail_condition_status_rows(array $args) {
    $time_level = (string) ($args['time_level'] ?? '');
    $place_level = (string) ($args['place_level'] ?? '');
    $gender_mixed = !empty($args['gender_mixed']);
    $compare_my_place = (string) ($args['compare_my_place'] ?? '');
    $compare_other_place = (string) ($args['compare_other_place'] ?? '');
    $compare_my_gender = (string) ($args['compare_my_gender'] ?? '');
    $compare_other_gender = (string) ($args['compare_other_gender'] ?? '');
    $time_display = (string) ($args['time_display'] ?? '');
    $gender_display = (string) ($args['gender_display'] ?? $compare_other_gender);

    if (in_array($time_level, ['TIME_STRONG', 'TIME_OK'], true)) {
        $time_status = 'match';
        $time_label = '一致しています';
    } elseif ($time_level === 'TIME_WEAK') {
        $time_status = 'partial';
        $time_label = '一部異なります';
    } else {
        $time_status = 'mismatch';
        $time_label = '異なります';
    }

    if ($place_level === 'PLACE_EASY' && $compare_my_place === $compare_other_place) {
        $place_status = 'match';
        $place_label = '一致しています';
        $place_subtext = '';
    } elseif (in_array($place_level, ['PLACE_EASY', 'PLACE_MEDIUM'], true)) {
        $place_status = 'partial';
        $place_label = '一部異なります';
        $place_subtext = 'あなた: ' . $compare_my_place . ' / 相手: ' . $compare_other_place;
    } else {
        $place_status = 'mismatch';
        $place_label = '異なります';
        $place_subtext = 'あなた: ' . $compare_my_place . ' / 相手: ' . $compare_other_place;
    }

    if (!$gender_mixed && $compare_my_gender === $compare_other_gender) {
        $gender_status = 'match';
        $gender_label = '一致しています';
        $gender_subtext = '';
    } elseif (!$gender_mixed) {
        $gender_status = 'partial';
        $gender_label = '一部異なります';
        $gender_subtext = 'あなた: ' . $compare_my_gender . ' / 相手: ' . $compare_other_gender;
    } else {
        $gender_status = 'partial';
        $gender_label = '確認が必要です';
        $gender_subtext = 'あなた: ' . $compare_my_gender . ' / 相手: ' . $compare_other_gender;
    }

    return [
        [
            'key'          => 'time',
            'icon'         => 'schedule',
            'status'       => $time_status,
            'status_label' => $time_label,
            'value'        => $time_display,
            'subtext'      => '',
        ],
        [
            'key'          => 'place',
            'icon'         => 'home',
            'status'       => $place_status,
            'status_label' => $place_label,
            'value'        => $compare_my_place,
            'subtext'      => $place_subtext,
        ],
        [
            'key'          => 'gender',
            'icon'         => 'group',
            'status'       => $gender_status,
            'status_label' => $gender_label,
            'value'        => $gender_display,
            'subtext'      => $gender_subtext,
        ],
    ];
}
