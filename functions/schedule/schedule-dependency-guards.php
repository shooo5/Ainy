<?php
/**
 * schedule 編集・削除時の match_request / match_board / chat 依存確認（第1段階）
 * 破壊的処理の前段ガード。成立済み・申請中のスケジュールの物理削除は行わない。
 */
if (!defined('ABSPATH')) {
    exit;
}

/** 申請中: 先に申請取り下げ必須（メタ保存値は英語 canonical の pending。旧 publish は正規化で吸収） */
if (!defined('AIDUNITE_MR_STATUS_PENDING')) {
    define('AIDUNITE_MR_STATUS_PENDING', 'pending');
}
/** 承認後〜成立（schedule 即時物理削除禁止ゾーン） */
if (!defined('AIDUNITE_MR_STATUS_IN_PLAY')) {
    define('AIDUNITE_MR_STATUS_IN_PLAY', ['accepted', 'established']);
}

/**
 * match_request の状態値を正規化する
 * - 既存データ互換: 日本語ラベル（申請中/承認済/拒否済/キャンセル済）を英語コードへ寄せる
 * - 空の場合は post_status をフォールバック（WP の publish 投稿状態は申請データが無ければ pending 扱いに寄せないこと）
 *
 * @param string $status_raw
 * @param string $post_status
 * @return string
 */
function aidunite_normalize_match_request_status($status_raw, $post_status = '') {
    $raw = trim((string) $status_raw);
    if ($raw === '') {
        $ps = trim((string) $post_status);
        if ($ps !== '') {
            $raw = $ps;
        }
    }
    if ($raw === '') {
        return '';
    }
    $map = [
        'not_applied' => 'not_applied',
        '未申請' => 'not_applied',
        'publish' => 'pending',
        '申請中' => 'pending',
        'pending' => 'pending',
        'accepted' => 'accepted',
        '承認済' => 'accepted',
        '承認済み' => 'accepted',
        'established' => 'established',
        '試合確定' => 'established',
        'rejected' => 'rejected',
        '拒否' => 'rejected',
        '拒否済' => 'rejected',
        '拒否済み' => 'rejected',
        'canceled' => 'canceled',
        'cancelled' => 'canceled',
        'キャンセル' => 'canceled',
        'キャンセル済' => 'canceled',
        'キャンセル済み' => 'canceled',
    ];
    if (isset($map[$raw])) {
        return $map[$raw];
    }
    $raw_lc = strtolower($raw);
    if (isset($map[$raw_lc])) {
        return $map[$raw_lc];
    }
    return $raw_lc;
}

/**
 * 依存サマリをログ向けの短い配列にする
 *
 * @param array $dep
 * @return array
 */
function aidunite_summarize_schedule_dependencies_for_log($dep) {
    $statuses = array_values(array_filter(array_map('strval', (array) ($dep['match_request_statuses'] ?? []))));
    $status_count = [];
    foreach ($statuses as $st) {
        if (!isset($status_count[$st])) {
            $status_count[$st] = 0;
        }
        $status_count[$st]++;
    }
    return [
        'schedule_id' => (int) ($dep['schedule_id'] ?? 0),
        'match_request_count' => count((array) ($dep['match_request_ids'] ?? [])),
        'match_board_count' => count((array) ($dep['match_board_ids'] ?? [])),
        'chat_room_count' => (int) ($dep['chat_room_count'] ?? 0),
        'has_pending' => !empty($dep['has_pending']),
        'has_in_play' => !empty($dep['has_in_play']),
        'status_count' => $status_count,
    ];
}

/**
 * マッチ系で「ある schedule へ紐づく」match_request を全取得（to / from / my）
 *
 * @param int $schedule_id
 * @return WP_Post[]
 */
function aidunite_get_schedule_match_requests($schedule_id) {
    $schedule_id = (int) $schedule_id;
    if ($schedule_id <= 0) {
        return [];
    }
    $sid_str = (string) $schedule_id;
    $q         = new WP_Query([
        'post_type'      => 'match_request',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'all',
        'no_found_rows'  => true,
        'meta_query'     => [
            'relation' => 'OR',
            [
                'key'   => 'to_schedule_id',
                'value' => $sid_str,
            ],
            [
                'key'   => 'from_schedule_id',
                'value' => $sid_str,
            ],
            [
                'key'   => 'my_schedule_id',
                'value' => $sid_str,
            ],
        ],
    ]);
    $posts = is_array($q->posts) ? $q->posts : [];
    wp_reset_postdata();
    return $posts;
}

/**
 * match_game_id（anchor schedule）に属する match_request を取得
 *
 * @param int $match_game_id anchor schedule 投稿 ID（= match_game_id）
 * @return WP_Post[]
 */
function aidunite_get_game_match_requests($match_game_id) {
    $match_game_id = (int) $match_game_id;
    if ($match_game_id <= 0) {
        return [];
    }
    $gid_str = (string) $match_game_id;
    $by_id   = [];

    $q_game = new WP_Query([
        'post_type'      => 'match_request',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'all',
        'no_found_rows'  => true,
        'meta_query'     => [
            [
                'key'   => 'match_game_id',
                'value' => $gid_str,
            ],
        ],
    ]);
    foreach (is_array($q_game->posts) ? $q_game->posts : [] as $p) {
        $by_id[(int) $p->ID] = $p;
    }
    wp_reset_postdata();

    // メタ未付与データのレガシー互換（移行中の to/my 紐づけ）
    foreach (['to_schedule_id', 'my_schedule_id'] as $legacy_key) {
        $q_legacy = new WP_Query([
            'post_type'      => 'match_request',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'all',
            'no_found_rows'  => true,
            'meta_query'     => [
                [
                    'key'   => $legacy_key,
                    'value' => $gid_str,
                ],
            ],
        ]);
        foreach (is_array($q_legacy->posts) ? $q_legacy->posts : [] as $p) {
            $rid = (int) $p->ID;
            if (isset($by_id[$rid])) {
                continue;
            }
            if ((int) get_post_meta($rid, 'match_game_id', true) > 0) {
                continue;
            }
            $by_id[$rid] = $p;
        }
        wp_reset_postdata();
    }

    return array_values($by_id);
}

/**
 * 募集ゲーム上で、指定 MR を除く established 件数（正規化ステータス基準）
 *
 * @param int $recruit_schedule_id
 * @param int $exclude_request_id  除外する match_request ID（0 で除外なし）
 * @return int
 */
function aidunite_count_game_established_match_requests($recruit_schedule_id, $exclude_request_id = 0) {
    $recruit_schedule_id = (int) $recruit_schedule_id;
    $exclude_request_id  = (int) $exclude_request_id;
    if ($recruit_schedule_id <= 0) {
        return 0;
    }
    $n = 0;
    foreach (aidunite_get_game_match_requests($recruit_schedule_id) as $p) {
        $rid = (int) $p->ID;
        if ($exclude_request_id > 0 && $rid === $exclude_request_id) {
            continue;
        }
        $meta = (string) get_post_meta($rid, 'status', true);
        $st   = aidunite_normalize_match_request_status($meta, isset($p->post_status) ? (string) $p->post_status : '');
        if ($st === 'established') {
            $n++;
        }
    }
    return $n;
}

/**
 * 募集 schedule の participants を、ゲーム内 established MR から再構築（補助メタ・単独では正としない）
 *
 * @param int $recruit_schedule_id
 * @return string 保存した participants CSV（空ならメタ削除）
 */
function aidunite_rebuild_recruit_schedule_participants_from_game_match_requests($recruit_schedule_id, array $options = []) {
    $recruit_schedule_id = (int) $recruit_schedule_id;
    $sync_chat           = !isset($options['sync_chat']) || $options['sync_chat'];
    if ($recruit_schedule_id <= 0) {
        return '';
    }
    $team_ids = [];
    foreach (aidunite_get_game_match_requests($recruit_schedule_id) as $p) {
        $rid = (int) $p->ID;
        $meta = (string) get_post_meta($rid, 'status', true);
        $st   = aidunite_normalize_match_request_status($meta, isset($p->post_status) ? (string) $p->post_status : '');
        if ($st !== 'established') {
            continue;
        }
        $from = (int) get_post_meta($rid, 'from_team_id', true);
        $to   = (int) get_post_meta($rid, 'to_team_id', true);
        if ($from > 0) {
            $team_ids[$from] = true;
        }
        if ($to > 0) {
            $team_ids[$to] = true;
        }
    }
    $ids = array_keys($team_ids);
    sort($ids, SORT_NUMERIC);
    if ($ids === []) {
        delete_post_meta($recruit_schedule_id, 'participants');
        if ($sync_chat && function_exists('aidunite_sync_schedule_chat_room_members')) {
            aidunite_sync_schedule_chat_room_members($recruit_schedule_id, $options);
        }
        return '';
    }
    $csv = implode(',', $ids);
    update_post_meta($recruit_schedule_id, 'participants', $csv);
    if ($sync_chat && function_exists('aidunite_sync_schedule_chat_room_members')) {
        aidunite_sync_schedule_chat_room_members($recruit_schedule_id, $options);
    }
    return $csv;
}

/**
 * 掲示板 match_board_status を、募集ゲームの MR 集合から決定（単一 match_request_id に非依存）
 * 優先度: established > pending > open（accepted は established 互換として吸収）
 *
 * @param int $recruit_schedule_id 募集側 schedule ID（board の post_parent）
 * @return string 設定した board ステータス（board 無しは open 扱いで空文字返却）
 */
/**
 * 募集ゲームの MR 集合から掲示板ステータスを算出（DB 更新なし）
 *
 * @param int $recruit_schedule_id
 * @return string established|pending|open|''（board 無しは open 相当で open）
 */
function aidunite_compute_match_board_status_from_game($recruit_schedule_id) {
    $recruit_schedule_id = (int) $recruit_schedule_id;
    if ($recruit_schedule_id <= 0) {
        return '';
    }

    $has_established = false;
    $has_accepted    = false;
    $has_pending     = false;

    if (!function_exists('aidunite_get_game_match_requests')) {
        return 'open';
    }

    foreach (aidunite_get_game_match_requests($recruit_schedule_id) as $p) {
        $meta = (string) get_post_meta($p->ID, 'status', true);
        $st = function_exists('aidunite_normalize_match_request_status')
            ? aidunite_normalize_match_request_status($meta, isset($p->post_status) ? (string) $p->post_status : '')
            : strtolower(trim($meta));
        if ($st === 'established') {
            $has_established = true;
        } elseif ($st === 'accepted') {
            $has_accepted = true;
        } elseif ($st === 'pending') {
            $has_pending = true;
        }
    }

    if ($has_established || $has_accepted) {
        return 'established';
    }
    if ($has_pending) {
        return 'pending';
    }
    return 'open';
}

function aidunite_sync_match_board_status_from_game($recruit_schedule_id) {
    $recruit_schedule_id = (int) $recruit_schedule_id;
    if ($recruit_schedule_id <= 0) {
        return '';
    }
    $board_id = wp_get_post_parent_id($recruit_schedule_id);
    if (!$board_id) {
        $boards = aidunite_get_schedule_match_boards($recruit_schedule_id);
        $board_id = !empty($boards[0]) ? (int) $boards[0]->ID : 0;
    }
    if (!$board_id) {
        return '';
    }

    if (function_exists('aidunite_match_board_sync_status_from_game')) {
        return (string) aidunite_match_board_sync_status_from_game($recruit_schedule_id);
    }

    $board_status = aidunite_compute_match_board_status_from_game($recruit_schedule_id);
    if (function_exists('aidunite_match_board_write_status_meta')) {
        return (string) aidunite_match_board_write_status_meta($board_id, $board_status);
    }
    update_post_meta($board_id, 'match_board_status', $board_status);

    return $board_status;
}

/**
 * 紐づく match_board（post_parent 方式）
 *
 * @param int $schedule_id
 * @return WP_Post[]
 */
function aidunite_get_schedule_match_boards($schedule_id) {
    $schedule_id = (int) $schedule_id;
    if ($schedule_id <= 0) {
        return [];
    }
    $boards = get_posts([
        'post_type'      => 'match_board',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'post_parent'    => $schedule_id,
    ]);
    return is_array($boards) ? $boards : [];
}

/**
 * chat_rooms: schedule_id または match_request ID に紐づく行（SELECT のみ・DELETE しない）
 *
 * @param int   $schedule_id
 * @param int[] $match_request_ids
 * @return object[] rows: id, match_id, schedule_id（カラム無し時はプロパティなし）, status, room_type
 */
function aidunite_get_schedule_chat_room_rows($schedule_id, $match_request_ids) {
    global $wpdb;
    $schedule_id = (int) $schedule_id;
    if ($schedule_id <= 0) {
        return [];
    }
    $table = $wpdb->prefix . 'chat_rooms';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[aidunite_schedule] chat_rooms テーブル不在: ' . $table);
        }
        return [];
    }
    $columns = $wpdb->get_col("SHOW COLUMNS FROM $table");
    $columns = is_array($columns) ? $columns : [];
    $has_schedule_col = in_array('schedule_id', $columns, true);
    $has_match_col    = in_array('match_id', $columns, true);
    if (!$has_schedule_col && !$has_match_col) {
        return [];
    }
    $select_parts = ['id'];
    if ($has_match_col) {
        $select_parts[] = 'match_id';
    }
    if ($has_schedule_col) {
        $select_parts[] = 'schedule_id';
    }
    $select_parts[] = 'status';
    $select_parts[] = 'room_type';
    $select_sql = implode(', ', $select_parts);

    $by_id = [];
    $rt_in = "room_type IN ('match','group')";
    if ($has_schedule_col) {
        $r1 = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT $select_sql FROM $table WHERE schedule_id = %d AND $rt_in",
                $schedule_id
            ),
            OBJECT
        );
        foreach (is_array($r1) ? $r1 : [] as $row) {
            $by_id[ (int) $row->id ] = $row;
        }
    }
    $match_request_ids = array_map('intval', (array) $match_request_ids);
    $match_request_ids = array_values(array_filter(array_unique($match_request_ids)));
    if ($has_match_col && !empty($match_request_ids)) {
        $placeholders = implode(',', array_fill(0, count($match_request_ids), '%d'));
        $sql = "SELECT $select_sql FROM $table WHERE $rt_in AND match_id IN ($placeholders)";
        $r2  = $wpdb->get_results($wpdb->prepare($sql, $match_request_ids), OBJECT);
        foreach (is_array($r2) ? $r2 : [] as $row) {
            $by_id[ (int) $row->id ] = $row;
        }
    }
    if ($wpdb->last_error && (defined('WP_DEBUG') && WP_DEBUG)) {
        error_log('[aidunite_schedule] chat_rooms 取得失敗: ' . $wpdb->last_error);
    }
    return array_values($by_id);
}

/**
 * 依存のまとめ（破壊的前にログ・API用）
 *
 * @param int $schedule_id
 * @return array{
 *   schedule_id: int,
 *   match_request_ids: int[],
 *   match_requests_by_id: int[],
 *   match_request_statuses: string[],
 *   has_pending: bool,
 *   has_in_play: bool,
 *   match_board_ids: int[],
 *   chat_room_count: int,
 *   chat_room_ids: int[]
 * }
 */
function aidunite_get_schedule_dependencies($schedule_id) {
    $schedule_id = (int) $schedule_id;
    $reqs = aidunite_get_schedule_match_requests($schedule_id);
    $mids  = [];
    $statuses = [];
    $has_pending = false;
    $has_in_play = false;
    foreach ($reqs as $p) {
        $mids[] = (int) $p->ID;
        $meta_status = (string) get_post_meta($p->ID, 'status', true);
        $post_status = isset($p->post_status) ? (string) $p->post_status : '';
        $st = aidunite_normalize_match_request_status($meta_status, $post_status);
        $statuses[] = $st;
        if ($st === AIDUNITE_MR_STATUS_PENDING) {
            $has_pending = true;
        }
        if (in_array($st, (array) AIDUNITE_MR_STATUS_IN_PLAY, true)) {
            $has_in_play = true;
        }
    }
    $boards  = aidunite_get_schedule_match_boards($schedule_id);
    $bids     = array_map(
        function ($b) {
            return (int) $b->ID; },
        $boards
    );
    $ch_rows  = aidunite_get_schedule_chat_room_rows($schedule_id, $mids);
    $cids     = array_map(
        function ($r) {
            return (int) $r->id; },
        $ch_rows
    );
    return [
        'schedule_id'             => $schedule_id,
        'match_request_ids'        => $mids,
        'match_requests_by_id'     => $mids,
        'match_request_statuses'  => $statuses,
        'has_pending'             => $has_pending,
        'has_in_play'              => $has_in_play,
        'match_board_ids'          => $bids,
        'chat_room_count'          => count($ch_rows),
        'chat_room_ids'            => $cids,
    ];
}

/**
 * 更新リクエストで「試合条件」に当たるパラメータキー（aidunite_update_schedule の $params 名）
 */
function aidunite_get_schedule_sensitive_param_keys() {
    return [
        'date', 'start_time', 'end_time', 'type',
        'place', 'schedule_place', 'schedule_place_option', 'venue_condition',
        'schedule_gender', 'matching_gender_condition', 'gender', 'gender_condition',
        'intent', 'certainty', 'matching', 'venue_name', 'post_id', // post_id 自体は比較しないが除外用
    ];
}

/**
 * 枠・チーム等（任意拡張）
 */
function aidunite_get_schedule_extra_sensitive_param_keys() {
    return [
        'male_slots', 'female_slots', 'capacity', 'team_id', // 将来 schedule REST が受け取るなら
    ];
}

/**
 * 実際に値が「変化する」敏感パラメータ名のリストを求める
 *
 * @param int   $post_id
 * @param array $params
 * @return string[]
 */
function aidunite_get_schedule_update_changed_sensitive_params($post_id, $params) {
    if (!is_array($params) || (int) $post_id <= 0) {
        return [];
    }
    $sensitive  = array_merge(
        aidunite_get_schedule_sensitive_param_keys(),
        aidunite_get_schedule_extra_sensitive_param_keys()
    );
    $sensitive  = array_diff(array_unique($sensitive), ['post_id']);
    $post_id   = (int) $post_id;
    $changed   = [];
    $bundle = function_exists('aidunite_schedule_get_display_bundle')
        ? aidunite_schedule_get_display_bundle($post_id)
        : [];
    $cur_date = (string) ($bundle['date'] ?? (function_exists('aidunite_schedule_read_normalized_date')
        ? aidunite_schedule_read_normalized_date($post_id)
        : ''));
    $cur_start = (string) ($bundle['start_time'] ?? '');
    $cur_end = (string) ($bundle['end_time'] ?? '');
    $cur_type = (string) ($bundle['schedule_type'] ?? '');
    $cur_place = function_exists('aidunite_schedule_read_place_raw')
        ? (string) aidunite_schedule_read_place_raw($post_id)
        : (string) ($bundle['place'] ?? '');
    $cur_gender = function_exists('aidunite_schedule_read_gender_raw')
        ? (string) aidunite_schedule_read_gender_raw($post_id)
        : (string) ($bundle['gender'] ?? '');
    $cur_intent = function_exists('aidunite_schedule_read_intent')
        ? (string) aidunite_schedule_read_intent($post_id)
        : (string) ($bundle['intent'] ?? '');
    $cur_match = (int) ($bundle['matching'] ?? 0);
    if (array_key_exists('date', $params) && (string) $params['date'] !== $cur_date) {
        $changed[] = 'date';
    }
    if (array_key_exists('start_time', $params) && (string) $params['start_time'] !== $cur_start) {
        $changed[] = 'start_time';
    }
    if (array_key_exists('end_time', $params) && (string) $params['end_time'] !== $cur_end) {
        $changed[] = 'end_time';
    }
    if (array_key_exists('type', $params) && (string) $params['type'] !== $cur_type) {
        $changed[] = 'type';
    }
    if (array_key_exists('place', $params) && (string) $params['place'] !== $cur_place) {
        $changed[] = 'place';
    }
    if (array_key_exists('schedule_gender', $params) && (string) $params['schedule_gender'] !== $cur_gender) {
        $changed[] = 'schedule_gender';
    }
    if (array_key_exists('gender', $params) && (string) $params['gender'] !== $cur_gender) {
        $changed[] = 'gender';
    }
    if (array_key_exists('schedule_place', $params)) {
        $nv = (string) $params['schedule_place'];
        if ($nv !== $cur_place) {
            $changed[] = 'schedule_place';
        }
    }
    if (array_key_exists('intent', $params) && (string) $params['intent'] !== $cur_intent) {
        $changed[] = 'intent';
    }
    if (array_key_exists('matching', $params)) {
        $nm = !empty($params['matching']) ? 1 : 0;
        if ($nm !== $cur_match) {
            $changed[] = 'matching';
        }
    }
    return array_values(array_unique($changed));
}

/**
 * スケジュール即時物理削除が許可されるか（不許可のとき WP_Error。成立・申請中は禁止）
 *
 * @param int $schedule_id
 * @return true|WP_Error
 */
function aidunite_can_delete_schedule($schedule_id) {
    $gate = aidunite_get_schedule_delete_gate_array($schedule_id);
    if (!empty($gate['allowed'])) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $summary = aidunite_summarize_schedule_dependencies_for_log((array) ($gate['dependencies'] ?? []));
            error_log('[aidunite_schedule] delete 許可 schedule_id=' . (int) $schedule_id . ' summary=' . wp_json_encode($summary));
        }
        return true;
    }
    $code    = (string) ($gate['code'] ?? 'delete_not_allowed');
    $message = (string) ($gate['message'] ?? 'このスケジュールは現状では削除できません。');
    $status  = (int) ($gate['http_status'] ?? 409);
    if (defined('WP_DEBUG') && WP_DEBUG) {
        $summary = aidunite_summarize_schedule_dependencies_for_log((array) ($gate['dependencies'] ?? []));
        error_log('[aidunite_schedule] delete 抑止: code=' . $code . ' schedule_id=' . (int) $schedule_id . ' summary=' . wp_json_encode($summary));
    }
    return new WP_Error(
        $code,
        $message,
        array_merge(
            [ 'status' => $status ],
            isset($gate['dependencies']) ? [ 'dependencies' => $gate['dependencies'] ] : []
        )
    );
}

/**
 * 配列形ゲート（REST 以外で再利用）
 */
function aidunite_get_schedule_delete_gate_array($schedule_id) {
    $schedule_id = (int) $schedule_id;
    if ($schedule_id <= 0 || get_post_type($schedule_id) !== 'schedule') {
        return [
            'allowed' => false,
            'code'    => 'invalid_schedule',
            'message' => '無効なスケジュールIDです。',
            'http_status' => 400,
        ];
    }
    $dep = aidunite_get_schedule_dependencies($schedule_id);
    if (!empty($dep['has_in_play'])) {
        return [
            'allowed'       => false,
            'code'          => 'match_committed',
            'message'       => '承認済み・成立済みのマッチ申請が存在するため、スケジュールを即時削除できません。試合のキャンセルや取り下げ手続きの後、または運営にご相談ください。',
            'http_status'   => 409,
            'summary'        => [ 'has_in_play' => true, 'has_pending' => (bool) $dep['has_pending'] ],
            'dependencies'  => $dep,
        ];
    }
    if (!empty($dep['has_pending'])) {
        return [
            'allowed'       => false,
            'code'          => 'match_pending',
            'message'       => '申請中のマッチ申請が存在します。先に申請のキャンセルに進んでから、スケジュールの削除を行ってください。',
            'http_status'   => 409,
            'summary'       => [ 'has_pending' => true ],
            'dependencies'  => $dep,
        ];
    }
    return [
        'allowed'      => true,
        'code'         => '',
        'message'      => '',
        'dependencies' => $dep,
        'summary'     => $dep,
    ];
}

/**
 * 編集: 申請中・承認/成立 があると敏感な項目の更新は不可（WP_Error）
 *
 * @param int   $post_id
 * @param array $params aidunite_update_schedule に渡る配列
 * @return true|WP_Error
 */
function aidunite_can_update_schedule($post_id, $params) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return new WP_Error('invalid_id', '無効なIDです。', [ 'status' => 400 ]);
    }
    $dep = aidunite_get_schedule_dependencies($post_id);
    $need_block = $dep['has_pending'] || $dep['has_in_play'];
    if (!$need_block) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[aidunite_schedule] update 許可（依存有効ブロックなし） schedule_id=' . $post_id);
        }
        return true;
    }
    $changed = aidunite_get_schedule_update_changed_sensitive_params($post_id, is_array($params) ? $params : []);
    if (empty($changed)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[aidunite_schedule] update 敏感項目なし、メモ等のみ schedule_id=' . $post_id);
        }
        return true;
    }
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[aidunite_schedule] update 抑止 schedule_id=' . $post_id . ' changed=' . wp_json_encode($changed) . ' has_pending=' . ( $dep['has_pending'] ? '1' : '0' ) . ' has_in_play=' . ( $dep['has_in_play'] ? '1' : '0' ));
    }
    return new WP_Error(
        'schedule_edit_blocked',
        '申請中または成立に関するマッチがあるため、日時・会場・目的などの重要項目を変更できません。先に申請の扱いを解消するか、メモのみ更新してください。',
        [ 'status' => 409, 'changed' => $changed, 'dependencies' => $dep ]
    );
}

/**
 * chat_rooms.status を completed に（DELETE は使わない）
 *
 * @param int   $schedule_id
 * @param int[] $match_request_ids
 * @return int 更新件数
 */
function aidunite_close_match_chat_rooms_for_schedule($schedule_id, $match_request_ids) {
    global $wpdb;
    $schedule_id = (int) $schedule_id;
    if ($schedule_id <= 0) {
        return 0;
    }
    $table = $wpdb->prefix . 'chat_rooms';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
        return 0;
    }
    $match_request_ids = array_filter(array_unique(array_map('intval', (array) $match_request_ids)));
    $ids_to_close     = aidunite_get_schedule_chat_room_rows($schedule_id, $match_request_ids);
    if (empty($ids_to_close)) {
        return 0;
    }
    $n = 0;
    foreach ($ids_to_close as $row) {
        $room_id = (int) $row->id;
        if ($room_id <= 0) {
            continue;
        }
        $st = (string) ($row->status ?? '');
        if ($st === 'completed' || $st === 'archived') {
            continue;
        }
        $mid = (int) ($row->match_id ?? 0);
        if ($mid > 0 && function_exists('aidunite_mark_match_chat_completed')) {
            if (aidunite_mark_match_chat_completed($mid, 'schedule_deletion')) {
                $n++;
            }
            continue;
        }
        $u = $wpdb->update(
            $table,
            [ 'status' => 'completed' ],
            [ 'id' => $room_id ],
            [ '%s' ],
            [ '%d' ]
        );
        if ($u !== false && (int) $u > 0) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[aidunite_schedule] chat_rooms 完了化 room_id=' . $room_id . ' schedule_id=' . $schedule_id);
            }
            $n += (int) $u;
        }
    }
    return $n;
}

/**
 * ガード通過時のみ: 子(match_request, match_board)除去後、schedule 自体を物理削除。
 * 成立/申請中は can_delete より呼ばれないこと。
 *
 * @param int $schedule_id
 * @return true|WP_Error|array{ deleted_match_requests: int, deleted_match_boards: int, closed_chat_rooms: int, schedule_id: int }
 */
function aidunite_perform_safe_schedule_deletion($schedule_id) {
    $schedule_id = (int) $schedule_id;
    $g           = aidunite_get_schedule_delete_gate_array($schedule_id);
    if (empty($g['allowed'])) {
        if (is_wp_error($may = aidunite_can_delete_schedule($schedule_id))) {
            return $may;
        }
    }
    $pre = $g['dependencies'] ?? aidunite_get_schedule_dependencies($schedule_id);
    if (!empty($pre['has_in_play']) || !empty($pre['has_pending'])) {
        error_log('[aidunite_schedule] CRITICAL: perform_safe 呼出し方が不整合 schedule_id=' . $schedule_id);
        return new WP_Error('internal_guard', '内部チェックの不整合です。', [ 'status' => 500 ]);
    }
    if (defined('WP_DEBUG') && WP_DEBUG) {
        $summary = aidunite_summarize_schedule_dependencies_for_log((array) $pre);
        error_log('[aidunite_schedule] 確認: 安全スケジュール物理削除 begin schedule_id=' . $schedule_id . ' summary=' . wp_json_encode($summary));
    }
    $reqs  = aidunite_get_schedule_match_requests($schedule_id);
    $mrids = array_map(
        function ($p) {
            return (int) $p->ID; },
        $reqs
    );
    $closed = aidunite_close_match_chat_rooms_for_schedule($schedule_id, $mrids);
    $del_mr = 0;
    foreach ($reqs as $p) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[aidunite_schedule] 確認: match_request 削除前 request_id=' . (int) $p->ID . ' schedule_id=' . $schedule_id);
        }
        if (wp_delete_post((int) $p->ID, true)) {
            $del_mr++;
        }
    }
    $boards     = aidunite_get_schedule_match_boards($schedule_id);
    $del_boards = 0;
    foreach ($boards as $b) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[aidunite_schedule] 確認: match_board 削除前 board_id=' . (int) $b->ID);
        }
        if (wp_delete_post((int) $b->ID, true)) {
            $del_boards++;
        }
    }
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[aidunite_schedule] 確認: schedule 削除直前 schedule_id=' . $schedule_id);
    }
    if (!wp_delete_post($schedule_id, true)) {
        return new WP_Error('delete_failed', 'スケジュールの削除に失敗しました。', [ 'status' => 500 ]);
    }
    $result = [
        'deleted_match_requests' => $del_mr,
        'deleted_match_boards'   => $del_boards,
        'closed_chat_rooms'      => $closed,
        'schedule_id'            => $schedule_id,
    ];
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[aidunite_schedule] delete 完了 schedule_id=' . $schedule_id . ' result=' . wp_json_encode($result));
    }
    return $result;
}
