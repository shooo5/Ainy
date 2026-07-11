<?php
/**
 * 市場軸掲示板：他チーム募集一覧の取得・照合・状態判定・最良結果選定
 * docs/match-board-market-axis-decisions.md に準拠
 */

if (!defined('ABSPATH')) {
    exit;
}

if (function_exists('aidunite_match_board_ensure_dependencies')) {
    aidunite_match_board_ensure_dependencies();
}

if (!function_exists('aidunite_market_recruitment_passes_board_filters')) {
    /**
     * 募集中母集団フィルタ（性別 canonical・募集オープン）。ヘルパー未読込時は取りこぼし防止の簡易判定。
     *
     * @param int $schedule_id
     */
    function aidunite_market_recruitment_passes_board_filters($schedule_id) {
        $pid = (int) $schedule_id;
        if ($pid <= 0) {
            return false;
        }
        $canon = function_exists('aidunite_market_recruitment_gender_canonical')
            ? aidunite_market_recruitment_gender_canonical($pid)
            : '';
        if ($canon === '' && function_exists('aidunite_mvp_log_both_gender_excluded')) {
            $raw = function_exists('aidunite_schedule_read_gender_raw')
                ? aidunite_schedule_read_gender_raw($pid)
                : '';
            if (function_exists('aidunite_mvp_gender_raw_is_both_legacy') && aidunite_mvp_gender_raw_is_both_legacy($raw)) {
                aidunite_mvp_log_both_gender_excluded('match_board_recruitment_query', $pid);
            }
        }
        if (!function_exists('aidunite_mvp_gender_is_valid') || !aidunite_mvp_gender_is_valid($canon)) {
            return false;
        }
        if (function_exists('aidunite_market_schedule_unavailable_as_opponent_recruitment')
            && aidunite_market_schedule_unavailable_as_opponent_recruitment($pid)) {
            return false;
        }
        if (function_exists('aidunite_recruit_accepts_match_applications')) {
            return aidunite_recruit_accepts_match_applications($pid);
        }
        if (function_exists('aidunite_recruit_open_for_market_board')) {
            return aidunite_recruit_open_for_market_board($pid);
        }
        $intent = function_exists('aidunite_schedule_read_intent')
            ? aidunite_schedule_read_intent($pid)
            : (string) get_post_meta($pid, 'intent', true);
        if ($intent === 'confirmed') {
            return false;
        }

        return function_exists('aidunite_schedule_matching_meta_on')
            && aidunite_schedule_matching_meta_on($pid);
    }
}

if (!function_exists('aidunite_market_build_schedule_array')) {
    /**
     * スケジュールから配列（aidunite_get_match_label_new 用）を組み立てる
     */
    function aidunite_market_build_schedule_array($schedule_id) {
        $schedule_id = (int) $schedule_id;
        $bundle = function_exists('aidunite_schedule_get_display_bundle')
            ? aidunite_schedule_get_display_bundle($schedule_id)
            : [];
        $api = function_exists('aidunite_schedule_get_api_display_fields')
            ? aidunite_schedule_get_api_display_fields($schedule_id)
            : [];
        $gender = (string) ($bundle['gender'] ?? $api['gender'] ?? '');
        if ($gender === '' && function_exists('aidunite_schedule_read_gender_raw')) {
            $gender = aidunite_schedule_read_gender_raw($schedule_id);
        }
        $place = (string) ($bundle['place'] ?? $api['place'] ?? '');
        if ($place === '' && function_exists('aidunite_schedule_read_place_raw')) {
            $place = aidunite_schedule_read_place_raw($schedule_id);
        }
        if (function_exists('aidunite_normalize_place_for_lock')) {
            $place = aidunite_normalize_place_for_lock($place);
        } elseif ((string) $place === 'both') {
            $place = 'either';
        }

        return [
            'schedule_date' => (string) ($bundle['date'] ?? $api['date'] ?? (function_exists('aidunite_schedule_read_normalized_date')
                ? aidunite_schedule_read_normalized_date($schedule_id)
                : '')),
            'start'         => (string) ($bundle['start_time'] ?? $api['start_time'] ?? ''),
            'end'           => (string) ($bundle['end_time'] ?? $api['end_time'] ?? ''),
            'gender'        => aidunite_normalize_gender_for_match_score($gender),
            'place'         => $place,
        ];
    }
}

/**
 * 最良結果（代表 my_schedule_id）の選定
 * 優先1: 開始時刻差が最小 → 2: 終了時刻差が最小 → 3: overlap_minutes が最大 → 4: 自スケ開始が早い
 */
function aidunite_market_pick_best_my_schedule($candidates, $other_start, $other_end) {
    if (empty($candidates)) {
        return null;
    }
    $other_start_min = function_exists('aidunite_time_to_minutes') ? aidunite_time_to_minutes($other_start) : 0;
    $other_end_min   = function_exists('aidunite_time_to_minutes') ? aidunite_time_to_minutes($other_end) : 0;

    usort($candidates, function ($a, $b) use ($other_start_min, $other_end_min) {
        $my_start_a = function_exists('aidunite_time_to_minutes') ? aidunite_time_to_minutes($a['start']) : 0;
        $my_end_a   = function_exists('aidunite_time_to_minutes') ? aidunite_time_to_minutes($a['end']) : 0;
        $my_start_b = function_exists('aidunite_time_to_minutes') ? aidunite_time_to_minutes($b['start']) : 0;
        $my_end_b   = function_exists('aidunite_time_to_minutes') ? aidunite_time_to_minutes($b['end']) : 0;

        $start_diff_a = abs($my_start_a - $other_start_min);
        $start_diff_b = abs($my_start_b - $other_start_min);
        if ($start_diff_a !== $start_diff_b) {
            return $start_diff_a <=> $start_diff_b;
        }
        $end_diff_a = abs($my_end_a - $other_end_min);
        $end_diff_b = abs($my_end_b - $other_end_min);
        if ($end_diff_a !== $end_diff_b) {
            return $end_diff_a <=> $end_diff_b;
        }
        $overlap_a = isset($a['overlap_minutes']) ? (int) $a['overlap_minutes'] : 0;
        $overlap_b = isset($b['overlap_minutes']) ? (int) $b['overlap_minutes'] : 0;
        if ($overlap_a !== $overlap_b) {
            return $overlap_b <=> $overlap_a; // 大きい方が先
        }
        return $my_start_a <=> $my_start_b;
    });

    return $candidates[0];
}

/**
 * 相手募集と同日の自チーム schedule を行照合用にマージ（matching=0 の主催確定 5691 型を含む）。
 *
 * @param array $my_schedules WP_Post[]
 * @param int   $my_team_id
 * @param int   $other_schedule_id
 * @return array
 */
function aidunite_market_merge_same_day_schedules_for_row_compare($my_schedules, $my_team_id, $other_schedule_id) {
    $by_id = [];
    foreach ($my_schedules as $my_post) {
        if ($my_post instanceof WP_Post) {
            $by_id[(int) $my_post->ID] = $my_post;
        }
    }
    $my_team_id = (int) $my_team_id;
    $other_schedule_id = (int) $other_schedule_id;
    if ($my_team_id <= 0 || $other_schedule_id <= 0) {
        return array_values($by_id);
    }
    $other_dn = function_exists('aidunite_schedule_read_normalized_date')
        ? aidunite_schedule_read_normalized_date($other_schedule_id)
        : '';
    if ($other_dn === '') {
        return array_values($by_id);
    }
    $same_day = get_posts([
        'post_type'      => 'schedule',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => [
            'relation' => 'AND',
            ['key' => 'team_id', 'value' => (string) $my_team_id, 'compare' => '='],
            ['key' => 'schedule_date', 'value' => $other_dn, 'compare' => '='],
        ],
    ]);
    foreach ($same_day as $post) {
        if ($post instanceof WP_Post) {
            $by_id[(int) $post->ID] = $post;
        }
    }

    return array_values($by_id);
}

/**
 * 募集中行の照合に使う schedule を絞る（申請希望として使わないものは除外）。
 * 主催ホーム確定（5691 型）は同日の時間照合には残す。参加側アウェイ確定・ホーム募集中は除外。
 *
 * @param array $my_schedules WP_Post[]
 * @return array
 */
function aidunite_market_filter_my_schedules_for_recruit_row($my_schedules) {
    return array_values(array_filter($my_schedules, static function ($my_post) {
        $sid = (int) $my_post->ID;
        if ($sid <= 0) {
            return false;
        }
        $intent_filter = function_exists('aidunite_schedule_read_intent')
            ? aidunite_schedule_read_intent($sid)
            : (string) get_post_meta($sid, 'intent', true);
        if ($intent_filter === 'confirmed') {
            if (function_exists('aidunite_schedule_is_guest_slot_schedule')
                && aidunite_schedule_is_guest_slot_schedule($sid)) {
                return false;
            }

            return true;
        }
        return true;
    }));
}

/**
 * 1件の「他チーム募集」に対する状態（🟢/🟡/🔵/⚪）と代表 my_schedule を判定
 *
 * @param WP_Post $other_schedule 相手の schedule 投稿
 * @param array   $my_schedules   自チームの対戦希望 schedule の配列（WP_Post）
 * @param int     $my_team_id     自チームID
 * @return array ['state' => 'green'|'yellow'|'no_preference'|'mismatch', 'best_my_schedule_id' => int, 'best_result' => array|null, 'best_my_display' => string]
 */
function aidunite_market_get_row_state($other_schedule, $my_schedules, $my_team_id) {
    $other_id = $other_schedule->ID;
    $mismatch_row = [
        'state'                => 'mismatch',
        'best_my_schedule_id'  => 0,
        'best_result'          => null,
        'best_my_display'      => '',
    ];
    if (function_exists('aidunite_market_schedule_unavailable_as_opponent_recruitment')
        && aidunite_market_schedule_unavailable_as_opponent_recruitment((int) $other_id)) {
        return $mismatch_row;
    }
    $my_schedules = aidunite_market_merge_same_day_schedules_for_row_compare($my_schedules, $my_team_id, $other_id);
    $my_schedules = aidunite_market_filter_my_schedules_for_recruit_row($my_schedules);
    if (empty($my_schedules)) {
        $other_dn_empty = function_exists('aidunite_schedule_read_normalized_date')
            ? aidunite_schedule_read_normalized_date($other_id)
            : '';
        if ($my_team_id > 0 && $other_dn_empty !== ''
            && function_exists('aidunite_team_has_guest_committed_schedule_on_date')
            && aidunite_team_has_guest_committed_schedule_on_date($my_team_id, $other_dn_empty)) {
            return $mismatch_row;
        }

        return [
            'state'                => 'no_preference',
            'best_my_schedule_id'  => 0,
            'best_result'          => null,
            'best_my_display'      => '',
        ];
    }

    // 申請に載せる候補: 参加側残枠あり。主催ホーム確定は時間照合のみ（guest_remaining は見ない）
    $eligible_my_schedules = array_values(array_filter($my_schedules, static function ($my_post) {
        $sid = (int) $my_post->ID;
        if ($sid <= 0) {
            return false;
        }
        $intent = function_exists('aidunite_schedule_read_intent')
            ? aidunite_schedule_read_intent($sid)
            : (string) get_post_meta($sid, 'intent', true);
        $place_raw = function_exists('aidunite_schedule_read_place_raw')
            ? aidunite_schedule_read_place_raw($sid)
            : '';
        $place_lc = function_exists('aidunite_normalize_place_for_lock')
            ? aidunite_normalize_place_for_lock($place_raw)
            : strtolower(trim((string) $place_raw));

        // 主催募集（ホーム／either）: 他募集行との時間照合のみ。🔵希望未登録に落とさない（P4）
        if ($intent === 'recruit' && in_array($place_lc, ['home', 'either'], true)) {
            if (function_exists('aidunite_schedule_matching_meta_on')
                && aidunite_schedule_matching_meta_on($sid)) {
                return true;
            }
        }

        if ($intent === 'confirmed') {
            $place_lock = function_exists('aidunite_get_schedule_place_lock')
                ? aidunite_get_schedule_place_lock($sid)
                : '';

            return $place_lock === 'home' || $place_lc === 'home';
        }

        return function_exists('aidunite_schedule_guest_remaining')
            && aidunite_schedule_guest_remaining($sid) > 0;
    }));
    $my_schedules = $eligible_my_schedules;
    if ($my_schedules === []) {
        $other_dn_only = function_exists('aidunite_schedule_read_normalized_date')
            ? aidunite_schedule_read_normalized_date($other_id)
            : '';
        if ($other_dn_only !== '' && function_exists('aidunite_team_has_guest_committed_schedule_on_date')) {
            $other_team_only = function_exists('aidunite_schedule_read_team_id')
                ? aidunite_schedule_read_team_id($other_id)
                : 0;
            if ($my_team_id > 0 && aidunite_team_has_guest_committed_schedule_on_date($my_team_id, $other_dn_only)) {
                return $mismatch_row;
            }
            if ($other_team_only > 0 && aidunite_team_has_guest_committed_schedule_on_date($other_team_only, $other_dn_only)) {
                return $mismatch_row;
            }
        }

        return [
            'state'                => 'no_preference',
            'best_my_schedule_id'  => 0,
            'best_result'          => null,
            'best_my_display'      => '',
        ];
    }
    $other_gender_raw_pre = function_exists('aidunite_schedule_read_gender_raw')
        ? aidunite_schedule_read_gender_raw($other_id)
        : '';
    if (function_exists('aidunite_mvp_gender_raw_is_both_legacy') && aidunite_mvp_gender_raw_is_both_legacy($other_gender_raw_pre)) {
        if (function_exists('aidunite_mvp_log_both_gender_excluded')) {
            aidunite_mvp_log_both_gender_excluded('match_board_recruitment', $other_id);
        }
        return $mismatch_row;
    }
    $other_canon_pre = function_exists('aidunite_normalize_gender_canonical')
        ? aidunite_normalize_gender_canonical((string) $other_gender_raw_pre)
        : '';
    if (!function_exists('aidunite_mvp_gender_is_valid') || !aidunite_mvp_gender_is_valid($other_canon_pre)) {
        return $mismatch_row;
    }
    if (function_exists('aidunite_market_recruit_hidden_for_viewer_team')
        && aidunite_market_recruit_hidden_for_viewer_team((int) $other_id, (int) $my_team_id)) {
        return $mismatch_row;
    }

    $my_ids = [];
    foreach ($my_schedules as $my_post) {
        if ($my_post instanceof WP_Post) {
            $my_ids[] = (int) $my_post->ID;
        }
    }

    if (!function_exists('aidunite_market_row_state_from_schedules')) {
        return [
            'state'                => 'no_preference',
            'board_tier'           => 'no_preference',
            'scores'               => ['T' => 0, 'V' => 0, 'A' => 0],
            'best_my_schedule_id'  => 0,
            'best_result'          => null,
            'best_my_display'      => '',
            'best_apply_eval'      => null,
        ];
    }

    $resolved = aidunite_market_row_state_from_schedules((int) $other_id, $my_ids, (int) $my_team_id);
    $state = (string) ($resolved['state'] ?? 'no_preference');
    if ($state === 'hidden') {
        return $mismatch_row;
    }

    $best_my_schedule_id = (int) ($resolved['best_my_schedule_id'] ?? 0);
    $best_apply_eval = is_array($resolved['best_apply_eval'] ?? null) ? $resolved['best_apply_eval'] : null;
    $best_my_display = '';
    if ($best_my_schedule_id > 0) {
        $best_api = function_exists('aidunite_schedule_get_api_display_fields')
            ? aidunite_schedule_get_api_display_fields($best_my_schedule_id)
            : [];
        $best_my_display = trim(
            (string) ($best_api['start_time'] ?? '')
            . '–'
            . (string) ($best_api['end_time'] ?? '')
        );
    }

    $other_arr = aidunite_market_build_schedule_array($other_id);
    $other_dn_final = function_exists('_aidunite_match_apply_normalize_schedule_date')
        ? _aidunite_match_apply_normalize_schedule_date($other_arr['schedule_date'] ?? '')
        : (string) ($other_arr['schedule_date'] ?? '');
    if ($other_dn_final !== '' && function_exists('aidunite_team_has_guest_committed_schedule_on_date')) {
        if ($my_team_id > 0
            && aidunite_team_has_guest_committed_schedule_on_date($my_team_id, $other_dn_final)) {
            return $mismatch_row;
        }
        $other_team_id = function_exists('aidunite_schedule_read_team_id')
            ? aidunite_schedule_read_team_id($other_id)
            : (int) get_post_meta($other_id, 'team_id', true);
        if ($other_team_id > 0
            && aidunite_team_has_guest_committed_schedule_on_date($other_team_id, $other_dn_final)) {
            return $mismatch_row;
        }
    }
    if ($best_my_schedule_id > 0 && function_exists('aidunite_schedule_guest_remaining')
        && aidunite_schedule_guest_remaining($best_my_schedule_id) < 1) {
        return $mismatch_row;
    }

    return [
        'state'                => $state,
        'board_tier'           => (string) ($resolved['board_tier'] ?? $state),
        'scores'               => is_array($resolved['scores'] ?? null) ? $resolved['scores'] : ['T' => 0, 'V' => 0, 'A' => 0],
        'best_my_schedule_id'  => $best_my_schedule_id,
        'best_result'          => null,
        'best_my_display'      => $best_my_display,
        'best_apply_eval'      => $best_apply_eval,
    ];
}

/**
 * 募集中タブのデフォルト日付範囲（GET 未指定時）
 * from は今日+1日（当日分は現場対応不可のため一覧から除外する運用）
 *
 * @return array{from: string, to: string} Y-m-d
 */
function aidunite_market_board_default_date_range() {
    return [
        'from' => date('Y-m-d', strtotime('+1 day')),
        'to'   => date('Y-m-d', strtotime('+120 days')),
    ];
}

/**
 * 掲示板フィルタ日付を Y-m-d に正規化（type=date の GET / スラッシュ表記の保険）。
 *
 * @param string $raw
 * @return string
 */
function aidunite_market_normalize_filter_date($raw) {
    $raw = trim((string) $raw);
    if ($raw === '') {
        return '';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        return $raw;
    }
    $normalized = str_replace('/', '-', $raw);
    $ts = strtotime($normalized);

    return ($ts !== false) ? date('Y-m-d', $ts) : $raw;
}

/**
 * 募集中母集団の第1段: matching ON または recruit_open（枠残の主催募集・成立後 matching=0 も含む）
 *
 * @param int $schedule_id
 * @return bool
 */
function aidunite_market_schedule_in_recruitment_mother_set($schedule_id) {
    $schedule_id = (int) $schedule_id;
    if ($schedule_id <= 0 || get_post_status($schedule_id) !== 'publish') {
        return false;
    }

    $in_set = function_exists('aidunite_recruit_accepts_match_applications')
        && aidunite_recruit_accepts_match_applications($schedule_id);

    return (bool) apply_filters('aidunite_market_schedule_in_recruitment_mother_set', $in_set, $schedule_id);
}

/**
 * 市場軸：他チームの対戦希望スケジュールを一覧取得（日付範囲・自チーム除外）
 *
 * @param string $date_from Y-m-d
 * @param string $date_to   Y-m-d
 * @param int    $my_team_id
 * @param array  $filters   ['gender' => 'male'|'female'|'', 'team_area' => '']. 会場は統一判定（提案型）側で扱うためクエリでは絞らない。gender 空時は操作中 team の性別と一致する募集のみ（both は除外）。
 * @return WP_Post[]
 */
function aidunite_market_get_all_recruitments($date_from, $date_to, $my_team_id, $filters = []) {
    if (function_exists('aidunite_match_board_ensure_dependencies')) {
        aidunite_match_board_ensure_dependencies();
    }
    $my_team_id = (int) $my_team_id;
    $GLOBALS['aidunite_market_recruit_viewer_team_id'] = $my_team_id;
    $date_from = aidunite_market_normalize_filter_date($date_from);
    $date_to   = aidunite_market_normalize_filter_date($date_to);
    if ($date_from !== '' && $date_to !== '' && $date_from > $date_to) {
        $tmp = $date_from;
        $date_from = $date_to;
        $date_to = $tmp;
    }

    $gender_filter = '';
    if (!empty($filters['gender']) && in_array($filters['gender'], ['male', 'female'], true)) {
        $gender_filter = $filters['gender'];
    } elseif ($my_team_id > 0 && function_exists('aidunite_normalize_team_gender_option')) {
        $team_raw = (string) get_post_meta($my_team_id, 'team_gender_option', true);
        $team_g   = aidunite_normalize_team_gender_option($team_raw);
        if (function_exists('aidunite_mvp_gender_is_valid') && aidunite_mvp_gender_is_valid($team_g)) {
            $gender_filter = $team_g;
        }
    }

    // meta_query（matching / BETWEEN）は環境によって0件になるため、publish schedule を PHP で絞る。
    $schedule_ids = get_posts([
        'post_type'      => 'schedule',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'orderby'        => 'meta_value',
        'meta_key'       => 'schedule_date',
        'order'          => 'ASC',
    ]);
    $schedule_ids = is_array($schedule_ids) ? array_map('intval', $schedule_ids) : [];

    $posts = [];
    foreach ($schedule_ids as $sid) {
        if (!aidunite_market_schedule_in_recruitment_mother_set((int) $sid)) {
            continue;
        }
        $post = get_post($sid);
        if ($post instanceof WP_Post) {
            $posts[] = $post;
        }
    }

    if (!empty($GLOBALS['aidunite_market_board_debug_enabled'])) {
        $GLOBALS['aidunite_market_board_debug'] = [
            'theme'              => get_stylesheet(),
            'publish_schedules'  => count($schedule_ids),
            'after_matching_on'  => count($posts),
            'recruit_open_fn'    => function_exists('aidunite_recruit_open_for_market_board') ? 1 : 0,
            'date_from'          => $date_from,
            'date_to'            => $date_to,
            'my_team_id'          => $my_team_id,
            'gender_filter'      => $gender_filter,
        ];
    }

    if ($my_team_id > 0) {
        $posts = array_values(array_filter($posts, static function ($p) use ($my_team_id) {
            $owner = function_exists('aidunite_schedule_read_team_id')
                ? aidunite_schedule_read_team_id((int) $p->ID)
                : (int) get_post_meta((int) $p->ID, 'team_id', true);

            return $owner !== $my_team_id;
        }));
    }
    if ($date_from !== '' && $date_to !== '') {
        $posts = array_values(array_filter($posts, static function ($p) use ($date_from, $date_to) {
            $dn = function_exists('aidunite_schedule_read_normalized_date')
                ? aidunite_schedule_read_normalized_date((int) $p->ID)
                : '';

            return $dn !== '' && $dn >= $date_from && $dn <= $date_to;
        }));
    }
    if ($gender_filter !== '') {
        $posts = array_values(array_filter($posts, static function ($p) use ($gender_filter) {
            $pid = (int) $p->ID;
            $canon = function_exists('aidunite_market_recruitment_gender_canonical')
                ? aidunite_market_recruitment_gender_canonical($pid)
                : '';
            if ($canon === '') {
                $raw = function_exists('aidunite_schedule_read_gender_raw')
                    ? aidunite_schedule_read_gender_raw($pid)
                    : '';
                $canon = function_exists('aidunite_normalize_gender_canonical')
                    ? aidunite_normalize_gender_canonical((string) $raw)
                    : '';
            }

            return $canon === $gender_filter;
        }));
    }

    $before_open = count($posts);
    $posts = array_values(array_filter($posts, static function ($p) {
        return aidunite_market_recruitment_passes_board_filters((int) $p->ID);
    }));

    if (!empty($GLOBALS['aidunite_market_board_debug_enabled'])) {
        $GLOBALS['aidunite_market_board_debug']['after_team_date_gender'] = $before_open;
        $GLOBALS['aidunite_market_board_debug']['after_recruit_open'] = count($posts);
        $GLOBALS['aidunite_market_board_debug']['result_ids'] = array_map(static function ($p) {
            return (int) $p->ID;
        }, $posts);
    }

    if (!empty($filters['team_area'])) {
        $area = $filters['team_area'];
        $posts = array_filter($posts, function ($p) use ($area) {
            $team_id = function_exists('aidunite_schedule_read_team_id')
                ? aidunite_schedule_read_team_id((int) $p->ID)
                : (int) get_post_meta($p->ID, 'team_id', true);
            if (!$team_id) return false;
            $team_area = get_post_meta($team_id, 'team_area', true);
            return $team_area === $area;
        });
        $posts = array_values($posts);
    }

    // 閲覧 team と既に試合確定している募集行は母集団から除外（ページ側の continue と二重化）
    if ($my_team_id > 0 && function_exists('aidunite_market_viewer_hides_established_recruit_row')) {
        $posts = array_values(array_filter($posts, static function ($p) use ($my_team_id) {
            return !aidunite_market_viewer_hides_established_recruit_row((int) $my_team_id, (int) $p->ID);
        }));
    }

    $posts = apply_filters('aidunite_market_get_all_recruitments_posts', $posts);
    unset($GLOBALS['aidunite_market_recruit_viewer_team_id']);

    return $posts;
}

/**
 * 自チームの対戦希望スケジュール一覧（matching=1 or is_match_requested=1、今日以降）
 */
function aidunite_market_get_my_matching_schedules($my_team_id) {
    $today = date('Y-m-d');
    $args = [
        'post_type'      => 'schedule',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => [
            'relation' => 'AND',
            ['key' => 'team_id', 'value' => $my_team_id, 'compare' => '='],
            ['key' => 'schedule_date', 'value' => $today, 'compare' => '>='],
            [
                'relation' => 'OR',
                ['key' => 'matching', 'value' => '1', 'compare' => '='],
                ['key' => 'is_match_requested', 'value' => '1', 'compare' => '='],
            ],
        ],
        'orderby' => 'meta_value',
        'meta_key' => 'schedule_date',
        'order' => 'ASC',
    ];
    $query = new WP_Query($args);
    $posts = $query->posts;

    return array_values(array_filter($posts, static function ($p) {
        return (string) get_post_meta((int) $p->ID, 'intent', true) !== 'confirmed';
    }));
}

/**
 * 自チームが参加側として試合確定済みの schedule が指定日にあるか（パターンA・掲示板行判定用）。
 *
 * @param int    $team_id
 * @param string $date_normalized Y-m-d
 */
if (!function_exists('aidunite_team_has_guest_committed_schedule_on_date')) {
    function aidunite_team_has_guest_committed_schedule_on_date($team_id, $date_normalized) {
        $team_id = (int) $team_id;
        $date_normalized = trim((string) $date_normalized);
        if ($team_id <= 0 || $date_normalized === '') {
            return false;
        }
        $posts = get_posts([
            'post_type'      => 'schedule',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'AND',
                ['key' => 'team_id', 'value' => (string) $team_id, 'compare' => '='],
                ['key' => 'schedule_date', 'value' => $date_normalized, 'compare' => '='],
            ],
        ]);
        foreach ($posts as $pid) {
            $sid = is_object($pid) ? (int) $pid->ID : (int) $pid;
            if (!function_exists('aidunite_schedule_is_guest_slot_schedule')
                || !aidunite_schedule_is_guest_slot_schedule($sid)) {
                continue;
            }
            // 主催ホーム確定（5691 型）は参加側コミットではない → 他募集の非表示対象にしない
            $intent_guest = function_exists('aidunite_schedule_read_intent')
                ? aidunite_schedule_read_intent($sid)
                : (string) get_post_meta($sid, 'intent', true);
            if ($intent_guest === 'confirmed') {
                $place_lock = function_exists('aidunite_get_schedule_place_lock')
                    ? aidunite_get_schedule_place_lock($sid)
                    : '';
                $place_raw = function_exists('aidunite_schedule_read_place_raw')
                    ? aidunite_schedule_read_place_raw($sid)
                    : '';
                $place_lc = function_exists('aidunite_normalize_place_for_lock')
                    ? aidunite_normalize_place_for_lock($place_raw)
                    : strtolower(trim((string) $place_raw));
                if ($place_lock === 'home' || $place_lc === 'home') {
                    continue;
                }
            }
            if (function_exists('aidunite_schedule_guest_remaining')
                && aidunite_schedule_guest_remaining($sid) < 1) {
                return true;
            }
        }

        // matching=0 でも my_schedule 側にコミット MR があれば参加側確定済み
        $applicant_requests = get_posts([
            'post_type'      => 'match_request',
            'post_status'    => 'any',
            'posts_per_page' => 10,
            'meta_query'     => [
                ['key' => 'from_team_id', 'value' => (string) $team_id, 'compare' => '='],
            ],
        ]);
        foreach ($applicant_requests as $req) {
            if (!function_exists('aidunite_match_request_counts_as_guest_commitment')
                || !aidunite_match_request_counts_as_guest_commitment((int) $req->ID)) {
                continue;
            }
            $mr_req = function_exists('aidunite_match_request_get_canonical_meta')
                ? aidunite_match_request_get_canonical_meta((int) $req->ID)
                : [];
            $my_sid = (int) ($mr_req['my_schedule_id'] ?? get_post_meta($req->ID, 'my_schedule_id', true));
            if ($my_sid <= 0) {
                $my_sid = (int) ($mr_req['from_schedule_id'] ?? get_post_meta($req->ID, 'from_schedule_id', true));
            }
            if ($my_sid <= 0) {
                continue;
            }
            // 主催 schedule（5691）を my_schedule にした MR は参加側コミットではない
            if (!function_exists('aidunite_schedule_is_guest_slot_schedule')
                || !aidunite_schedule_is_guest_slot_schedule($my_sid)) {
                continue;
            }
            $sched_team = function_exists('aidunite_schedule_read_team_id')
                ? aidunite_schedule_read_team_id($my_sid)
                : 0;
            if ($sched_team > 0 && $sched_team !== $team_id) {
                continue;
            }
            $d = function_exists('aidunite_schedule_read_normalized_date')
                ? aidunite_schedule_read_normalized_date($my_sid)
                : '';
            if ($d === $date_normalized) {
                return true;
            }
        }

        return false;
    }
}

/**
 * チームに紐づく match_request から schedule ID を収集（過去試合の申請状況表示用）
 *
 * @param int $my_team_id
 * @return int[]
 */
if (!function_exists('aidunite_market_get_schedule_ids_with_team_match_activity')) {
    function aidunite_market_get_schedule_ids_with_team_match_activity($my_team_id) {
        $my_team_id = (int) $my_team_id;
        if ($my_team_id <= 0) {
            return [];
        }
        $request_ids = get_posts([
            'post_type'      => 'match_request',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'OR',
                ['key' => 'from_team_id', 'value' => (string) $my_team_id, 'compare' => '='],
                ['key' => 'to_team_id', 'value' => (string) $my_team_id, 'compare' => '='],
            ],
        ]);
        $schedule_ids = [];
        foreach ($request_ids as $request_id) {
            foreach (['my_schedule_id', 'to_schedule_id', 'from_schedule_id'] as $meta_key) {
                $sid = (int) get_post_meta((int) $request_id, $meta_key, true);
                if ($sid > 0) {
                    $schedule_ids[$sid] = true;
                }
            }
        }

        return array_map('intval', array_keys($schedule_ids));
    }
}

/**
 * 申請状況タブ用: 対戦マッチング ON の schedule に加え、成立・申請中 MR が紐づく schedule（主催確定含む）を含める。
 *
 * @param int $my_team_id
 * @return WP_Post[]
 */
if (!function_exists('aidunite_market_get_board_my_schedules')) {
    function aidunite_market_get_board_my_schedules($my_team_id) {
        $my_team_id = (int) $my_team_id;
        if ($my_team_id <= 0) {
            return [];
        }
        $by_id = [];
        foreach (aidunite_market_get_my_matching_schedules($my_team_id) as $post) {
            $by_id[(int) $post->ID] = $post;
        }

        foreach (aidunite_market_get_schedule_ids_with_team_match_activity($my_team_id) as $sid) {
            if (isset($by_id[$sid])) {
                continue;
            }
            $post = get_post($sid);
            if ($post && $post->post_type === 'schedule' && $post->post_status === 'publish') {
                $by_id[$sid] = $post;
            }
        }

        $today = date('Y-m-d');
        $team_schedules = get_posts([
            'post_type'      => 'schedule',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                'relation' => 'AND',
                ['key' => 'team_id', 'value' => (string) $my_team_id, 'compare' => '='],
                ['key' => 'schedule_date', 'value' => $today, 'compare' => '>='],
            ],
            'orderby'        => 'meta_value',
            'meta_key'       => 'schedule_date',
            'order'          => 'ASC',
        ]);

        foreach ($team_schedules as $post) {
            $sid = (int) $post->ID;
            if (isset($by_id[$sid])) {
                continue;
            }
            $matching_on = function_exists('aidunite_schedule_matching_meta_on')
                && aidunite_schedule_matching_meta_on($sid);
            $intent = function_exists('aidunite_schedule_read_intent')
                ? aidunite_schedule_read_intent($sid)
                : (string) get_post_meta($sid, 'intent', true);
            $has_activity = false;
            if (function_exists('aidunite_get_established_requests_for_schedule')) {
                $rows = aidunite_get_established_requests_for_schedule($sid, $my_team_id, ['exclude_terminal' => false]);
                $has_activity = !empty($rows);
            }
            if ($matching_on || $intent === 'confirmed' || $has_activity) {
                $by_id[$sid] = $post;
            }
        }

        $merged = array_values($by_id);
        // 申請状況は「自チームの schedule ブロック」のみ。MR 経由で相手 anchor（match_game_id）が
        // 混ざると、承認側に相手のホーム枠＋自アウェイの二重表示になるため除外する。
        $merged = array_values(array_filter($merged, static function ($post) use ($my_team_id) {
            $owner = function_exists('aidunite_resolve_schedule_owner_team_id')
                ? (int) aidunite_resolve_schedule_owner_team_id((int) $post->ID)
                : (function_exists('aidunite_schedule_read_team_id')
                    ? aidunite_schedule_read_team_id((int) $post->ID)
                    : (int) get_post_meta((int) $post->ID, 'team_id', true));

            return $owner === $my_team_id;
        }));
        usort($merged, static function ($a, $b) {
            $da = function_exists('aidunite_schedule_read_normalized_date')
                ? aidunite_schedule_read_normalized_date((int) $a->ID)
                : '';
            $db = function_exists('aidunite_schedule_read_normalized_date')
                ? aidunite_schedule_read_normalized_date((int) $b->ID)
                : '';
            if ($da === $db) {
                return $a->ID <=> $b->ID;
            }

            return strcmp($da, $db);
        });

        return $merged;
    }
}

/**
 * 募集 anchor 上の MR 1件が消費する性別枠（男子/女子）を解決する。
 *
 * @param int $request_id
 * @param int $recruit_schedule_id 募集側 schedule（anchor）
 * @return string male|female
 */
if (!function_exists('aidunite_resolve_mr_gender_slot_for_recruit_anchor')) {
    function aidunite_resolve_mr_gender_slot_for_recruit_anchor($request_id, $recruit_schedule_id) {
        $request_id = (int) $request_id;
        $recruit_schedule_id = (int) $recruit_schedule_id;
        if ($request_id <= 0) {
            return 'male';
        }

        $established_slot_meta = (string) get_post_meta($request_id, 'established_gender_slot', true);
        if (in_array($established_slot_meta, ['male', 'female'], true)) {
            return $established_slot_meta;
        }

        $mr_slot = function_exists('aidunite_match_request_get_canonical_meta')
            ? aidunite_match_request_get_canonical_meta($request_id)
            : [];
        $selected_gender = (string) ($mr_slot['selected_gender'] ?? get_post_meta($request_id, 'selected_gender', true));
        if ($selected_gender === 'male') {
            return 'male';
        }
        if ($selected_gender === 'female') {
            return 'female';
        }

        $my_id = (int) ($mr_slot['my_schedule_id'] ?? get_post_meta($request_id, 'my_schedule_id', true));
        if ($my_id <= 0) {
            $my_id = (int) ($mr_slot['from_schedule_id'] ?? get_post_meta($request_id, 'from_schedule_id', true));
        }
        $to_id = (int) ($mr_slot['to_schedule_id'] ?? get_post_meta($request_id, 'to_schedule_id', true));
        $other_schedule_id = 0;
        if ($to_id === $recruit_schedule_id) {
            $other_schedule_id = $my_id;
        } elseif ($my_id === $recruit_schedule_id) {
            $other_schedule_id = $to_id;
        }
        if ($other_schedule_id > 0 && function_exists('aidunite_get_schedule_gender')) {
            $resolved = aidunite_get_schedule_gender($other_schedule_id);
            if ($resolved === 'female') {
                return 'female';
            }
            if ($resolved === 'male') {
                return 'male';
            }
        }

        return 'male';
    }
}

/**
 * 募集 anchor の性別枠ごとに、成立（承認済み含む）・承認待ち件数を集計する（掲示板 anchor と同じ母集団）。
 *
 * @param int $recruit_schedule_id
 * @return array{male_established:int,female_established:int,male_pending:int,female_pending:int}
 */
if (!function_exists('aidunite_summarize_recruit_anchor_gender_slots')) {
    function aidunite_summarize_recruit_anchor_gender_slots($recruit_schedule_id) {
        $recruit_schedule_id = (int) $recruit_schedule_id;
        $male_established = 0;
        $female_established = 0;
        $male_pending = 0;
        $female_pending = 0;
        if ($recruit_schedule_id <= 0) {
            return [
                'male_established'   => 0,
                'female_established' => 0,
                'male_pending'       => 0,
                'female_pending'     => 0,
            ];
        }

        $posts = [];
        if (function_exists('aidunite_get_game_match_requests')) {
            $posts = aidunite_get_game_match_requests($recruit_schedule_id);
        }
        if ($posts === []) {
            $posts = get_posts([
                'post_type'      => 'match_request',
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'meta_query'     => [
                    'relation' => 'OR',
                    ['key' => 'to_schedule_id', 'value' => (string) $recruit_schedule_id, 'compare' => '='],
                    ['key' => 'my_schedule_id', 'value' => (string) $recruit_schedule_id, 'compare' => '='],
                    ['key' => 'from_schedule_id', 'value' => (string) $recruit_schedule_id, 'compare' => '='],
                ],
            ]);
        }

        $recruit_gender = function_exists('aidunite_market_recruitment_gender_canonical')
            ? aidunite_market_recruitment_gender_canonical($recruit_schedule_id)
            : '';
        $recruit_male_only = ($recruit_gender === 'male');
        $recruit_female_only = ($recruit_gender === 'female');

        foreach ($posts as $p) {
            $rid = (int) $p->ID;
            $raw = (string) get_post_meta($rid, 'status', true);
            $norm = function_exists('aidunite_normalize_match_request_status')
                ? aidunite_normalize_match_request_status($raw, isset($p->post_status) ? (string) $p->post_status : '')
                : strtolower($raw);
            if (in_array($norm, ['canceled', 'rejected'], true)) {
                continue;
            }

            $slot = aidunite_resolve_mr_gender_slot_for_recruit_anchor($rid, $recruit_schedule_id);
            $is_male_bucket = ($slot === 'male');
            if ($recruit_male_only && $slot === 'female') {
                $is_male_bucket = true;
            } elseif ($recruit_female_only && $slot === 'male') {
                $is_male_bucket = false;
            } elseif ($slot === 'female') {
                $is_male_bucket = false;
            }

            $is_pending = ($norm === 'pending')
                || in_array($raw, ['publish', 'pending', '申請中'], true);
            if ($is_pending) {
                if ($is_male_bucket) {
                    $male_pending++;
                } else {
                    $female_pending++;
                }
                continue;
            }

            $is_filled = in_array($norm, ['established', 'accepted'], true)
                || (function_exists('aidunite_match_request_counts_as_guest_commitment')
                    && aidunite_match_request_counts_as_guest_commitment($rid));
            if (!$is_filled) {
                continue;
            }
            if ($is_male_bucket) {
                $male_established++;
            } else {
                $female_established++;
            }
        }

        return [
            'male_established'   => $male_established,
            'female_established' => $female_established,
            'male_pending'       => $male_pending,
            'female_pending'     => $female_pending,
        ];
    }
}

/**
 * マッチ申請一覧・管理者向け表示用: 募集 schedule の男子/女子「充足数/定員」。
 * 掲示板 anchor の `aidunite_market_board_anchor_slot_row_plan` と同じ定員・成立数を用いる（participants / male_capacity は使わない）。
 *
 * @param int $schedule_id 募集側 schedule ID
 * @return array{ male_current: int, female_current: int, male_cap: int, female_cap: int }
 */
if (!function_exists('aidunite_get_schedule_recruitment_counts')) {
    function aidunite_get_schedule_recruitment_counts($schedule_id) {
        $schedule_id = (int) $schedule_id;
        if ($schedule_id <= 0) {
            return [
                'male_current'   => 0,
                'female_current' => 0,
                'male_cap'       => 0,
                'female_cap'     => 0,
            ];
        }

        $summary = aidunite_summarize_recruit_anchor_gender_slots($schedule_id);
        $male_plan = function_exists('aidunite_market_board_anchor_slot_row_plan')
            ? aidunite_market_board_anchor_slot_row_plan(
                $schedule_id,
                'male',
                (int) $summary['male_established'],
                (int) $summary['male_pending']
            )
            : ['capacity' => 0];
        $female_plan = function_exists('aidunite_market_board_anchor_slot_row_plan')
            ? aidunite_market_board_anchor_slot_row_plan(
                $schedule_id,
                'female',
                (int) $summary['female_established'],
                (int) $summary['female_pending']
            )
            : ['capacity' => 0];

        return [
            'male_current'   => (int) $summary['male_established'],
            'female_current' => (int) $summary['female_established'],
            'male_cap'       => (int) ($male_plan['capacity'] ?? 0),
            'female_cap'     => (int) ($female_plan['capacity'] ?? 0),
        ];
    }
}

/**
 * 申請状況・anchor 主催ブロックの残枠と枠行数（ヘッダー「男子 残 n」と枠行を一致させる）
 *
 * 残数 = 登録枠 − 成立数のみ（承認待ちは残枠を消費しない。主催が選択承認するため申請は残ありなら無制限に来てよい）。
 * 枠行 = 成立各行 ＋ 承認待ち各行 ＋ 空き枠（残数に対し、承認待ちが埋めていない分の「まだ申請はございません。」）
 *
 * @param int    $schedule_id
 * @param string $gender      male|female
 * @param int    $established_count 当該性別の成立件数
 * @param int    $pending_count     当該性別の承認待ち件数（表示件数。残枠上限ではない）
 * @return array{capacity:int,remaining:int,empty_slots:int,total_rows:int}
 */
if (!function_exists('aidunite_market_board_anchor_slot_row_plan')) {
    function aidunite_market_board_anchor_slot_row_plan($schedule_id, $gender, $established_count, $pending_count) {
        $schedule_id = (int) $schedule_id;
        $gender = ($gender === 'female') ? 'female' : 'male';
        $established_count = max(0, (int) $established_count);
        $pending_count = max(0, (int) $pending_count);

        $meta_rem = $gender === 'female'
            ? (int) (function_exists('aidunite_get_schedule_female_slots') ? aidunite_get_schedule_female_slots($schedule_id) : 0)
            : (int) (function_exists('aidunite_get_schedule_male_slots') ? aidunite_get_schedule_male_slots($schedule_id) : 0);

        $pre_key = $gender === 'female' ? 'pre_established_female_slots' : 'pre_established_male_slots';
        $pre_cap = (int) get_post_meta($schedule_id, $pre_key, true);
        if ($pre_cap > 0 && (string) get_post_meta($schedule_id, 'pre_established_saved', true) === '1') {
            $capacity = $pre_cap;
            $remaining = max(0, $pre_cap - $established_count);
        } else {
            $capacity = max($established_count + $meta_rem, $established_count);
            $remaining = max(0, $capacity - $established_count);
            if (function_exists('aidunite_get_remaining_gender_slots')) {
                $rem = aidunite_get_remaining_gender_slots($schedule_id);
                $remaining = max(0, (int) ($rem[$gender] ?? $remaining));
            }
        }

        // 残あり: 承認待ちは件数分すべて表示。空きプレースホルダは未申請分のみ。
        $empty_slots = $remaining > 0 ? max(0, $remaining - $pending_count) : 0;

        return [
            'capacity'    => $capacity,
            'remaining'   => $remaining,
            'empty_slots' => $empty_slots,
            'total_rows'  => $established_count + $pending_count + $empty_slots,
        ];
    }
}
