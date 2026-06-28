<?php
/**
 * 試合一覧（match-board-own）読取正本
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('aidunite_match_board_card_key')) {
    /**
     * @param string $type mr|recruit
     * @param int    $id
     * @return string
     */
    function aidunite_match_board_card_key($type, $id) {
        $type = strtolower(trim((string) $type));
        $id = (int) $id;
        if ($id <= 0 || !in_array($type, ['mr', 'recruit'], true)) {
            return '';
        }

        return $type . ':' . $id;
    }
}

if (!function_exists('aidunite_match_board_read_post_match_survey_payload')) {
    /**
     * @param int $match_request_id
     * @param int $viewer_team_id
     * @return array{state:string,url?:string,label?:string}
     */
    function aidunite_match_board_read_post_match_survey_payload($match_request_id, $viewer_team_id) {
        $match_request_id = (int) $match_request_id;
        $viewer_team_id = (int) $viewer_team_id;
        if ($match_request_id <= 0 || $viewer_team_id <= 0) {
            return ['state' => 'hidden'];
        }

        $status = '';
        if (function_exists('aidunite_match_request_get_canonical_meta')) {
            $canonical = aidunite_match_request_get_canonical_meta($match_request_id);
            $status = function_exists('aidunite_normalize_match_request_status')
                ? aidunite_normalize_match_request_status((string) ($canonical['status'] ?? ''), '')
                : strtolower((string) ($canonical['status'] ?? ''));
        }
        if ($status !== 'established') {
            return ['state' => 'hidden'];
        }
        if (!function_exists('aidunite_is_match_ended') || !aidunite_is_match_ended($match_request_id)) {
            return ['state' => 'not_ended'];
        }
        if (function_exists('aidunite_team_has_submitted_match_feedback')
            && aidunite_team_has_submitted_match_feedback($match_request_id, $viewer_team_id)) {
            return ['state' => 'answered', 'label' => 'アンケート回答済み'];
        }

        $url = function_exists('aidunite_get_match_feedback_survey_url')
            ? aidunite_get_match_feedback_survey_url($match_request_id)
            : home_url('/match-feedback/?match_id=' . $match_request_id);

        return [
            'state' => 'pending',
            'url'   => $url,
            'label' => '試合後アンケート',
        ];
    }
}

if (!function_exists('aidunite_match_board_est_row_is_completed')) {
    /**
     * 試合完了タブへ出す行: established かつ試合終了時刻を過ぎている
     *
     * @param array<string, mixed> $est_row
     */
    function aidunite_match_board_est_row_is_completed(array $est_row) {
        $st = function_exists('aidunite_normalize_match_request_status')
            ? aidunite_normalize_match_request_status((string) ($est_row['status'] ?? ''), '')
            : strtolower((string) ($est_row['status'] ?? ''));
        if ($st !== 'established'
            && !in_array($est_row['status'] ?? '', ['established', '試合確定'], true)) {
            return false;
        }
        $request_id = (int) ($est_row['request_id'] ?? 0);
        if ($request_id <= 0 || !function_exists('aidunite_is_match_ended')) {
            return false;
        }

        return aidunite_is_match_ended($request_id);
    }
}

if (!function_exists('aidunite_match_board_read_mr_updated_at')) {
    /**
     * @param int $match_request_id
     * @return string ISO8601
     */
    function aidunite_match_board_read_mr_updated_at($match_request_id) {
        $match_request_id = (int) $match_request_id;
        if ($match_request_id <= 0) {
            return '';
        }
        $ts = (int) get_post_modified_time('U', true, $match_request_id);
        if ($ts <= 0) {
            return '';
        }

        return gmdate('c', $ts);
    }
}

if (!function_exists('aidunite_match_board_read_recruit_updated_at')) {
    /**
     * @param int $recruit_schedule_id
     * @param int $request_id
     * @return string ISO8601
     */
    function aidunite_match_board_read_recruit_updated_at($recruit_schedule_id, $request_id = 0) {
        $recruit_schedule_id = (int) $recruit_schedule_id;
        $request_id = (int) $request_id;
        $times = [];
        if ($recruit_schedule_id > 0) {
            $sched_ts = (int) get_post_modified_time('U', true, $recruit_schedule_id);
            if ($sched_ts > 0) {
                $times[] = $sched_ts;
            }
        }
        if ($request_id > 0) {
            $mr_ts = (int) get_post_modified_time('U', true, $request_id);
            if ($mr_ts > 0) {
                $times[] = $mr_ts;
            }
        }
        if ($times === []) {
            return '';
        }

        return gmdate('c', max($times));
    }
}

if (!function_exists('aidunite_match_board_is_card_new')) {
    /**
     * @param array<string, string>|null $seen_map
     * @param string                     $card_key
     * @param string                     $updated_at ISO8601
     */
    function aidunite_match_board_is_card_new($seen_map, $card_key, $updated_at) {
        $card_key = trim((string) $card_key);
        $updated_at = trim((string) $updated_at);
        if ($card_key === '' || $updated_at === '') {
            return false;
        }
        if (!is_array($seen_map)) {
            $seen_map = [];
        }
        $seen_at = trim((string) ($seen_map[$card_key] ?? ''));
        $updated_ts = strtotime($updated_at);
        if ($updated_ts === false) {
            return false;
        }
        if ($seen_at === '') {
            return true;
        }
        $seen_ts = strtotime($seen_at);

        return ($seen_ts === false) ? true : ($updated_ts > $seen_ts);
    }
}

if (!function_exists('aidunite_match_board_enrich_est_row')) {
    /**
     * @param array<string, mixed>       $est
     * @param int                        $viewer_team_id
     * @param int                        $user_id
     * @param array<string, string>|null $seen_map
     * @return array<string, mixed>
     */
    function aidunite_match_board_enrich_est_row(array $est, $viewer_team_id, $user_id, $seen_map = null) {
        $viewer_team_id = (int) $viewer_team_id;
        $user_id = (int) $user_id;
        $request_id = (int) ($est['request_id'] ?? 0);
        if ($seen_map === null && $user_id > 0 && function_exists('aidunite_user_read_market_board_card_seen')) {
            $seen_map = aidunite_user_read_market_board_card_seen($user_id);
        }
        if (!is_array($seen_map)) {
            $seen_map = [];
        }

        if ($request_id > 0) {
            $est['post_match_survey'] = aidunite_match_board_read_post_match_survey_payload($request_id, $viewer_team_id);
            $est['board_card_key'] = aidunite_match_board_card_key('mr', $request_id);
            $est['board_updated_at'] = aidunite_match_board_read_mr_updated_at($request_id);
            $est['board_is_new'] = aidunite_match_board_is_card_new(
                $seen_map,
                (string) ($est['board_card_key'] ?? ''),
                (string) ($est['board_updated_at'] ?? '')
            );
        }

        return $est;
    }
}

if (!function_exists('aidunite_match_board_enrich_recruit_row')) {
    /**
     * @param array<string, mixed>       $row
     * @param int                        $viewer_team_id
     * @param int                        $user_id
     * @param int                        $request_id
     * @param array<string, string>|null $seen_map
     * @return array<string, mixed>
     */
    function aidunite_match_board_enrich_recruit_row(array $row, $viewer_team_id, $user_id, $request_id = 0, $seen_map = null) {
        $viewer_team_id = (int) $viewer_team_id;
        $user_id = (int) $user_id;
        $request_id = (int) $request_id;
        $other = $row['other'] ?? null;
        if (!$other instanceof WP_Post) {
            return $row;
        }
        if ($seen_map === null && $user_id > 0 && function_exists('aidunite_user_read_market_board_card_seen')) {
            $seen_map = aidunite_user_read_market_board_card_seen($user_id);
        }
        if (!is_array($seen_map)) {
            $seen_map = [];
        }

        $other_id = (int) $other->ID;
        $row['board_card_key'] = aidunite_match_board_card_key('recruit', $other_id);
        $row['board_updated_at'] = aidunite_match_board_read_recruit_updated_at($other_id, $request_id);
        $row['board_is_new'] = aidunite_match_board_is_card_new(
            $seen_map,
            (string) ($row['board_card_key'] ?? ''),
            (string) ($row['board_updated_at'] ?? '')
        );

        return $row;
    }
}

if (!function_exists('aidunite_match_board_read_schedule_end_timestamp')) {
    /**
     * スケジュール箱の終了時刻（Unix timestamp）
     *
     * @param int $schedule_id
     * @return int|null
     */
    function aidunite_match_board_read_schedule_end_timestamp($schedule_id) {
        $schedule_id = (int) $schedule_id;
        if ($schedule_id <= 0) {
            return null;
        }

        $disp = function_exists('aidunite_schedule_get_market_list_display')
            ? aidunite_schedule_get_market_list_display($schedule_id)
            : [];
        $date = (string) ($disp['date'] ?? '');
        $end_time = (string) ($disp['end_time'] ?? '');
        if ($date === '' && function_exists('aidunite_schedule_read_normalized_date')) {
            $date = (string) aidunite_schedule_read_normalized_date($schedule_id);
        }
        if ($date === '') {
            return null;
        }

        if ($end_time !== '') {
            $ts = strtotime($date . ' ' . $end_time);
            return ($ts !== false) ? (int) $ts : null;
        }

        $ts = strtotime($date . ' 23:59:59');
        return ($ts !== false) ? (int) $ts : null;
    }
}

if (!function_exists('aidunite_match_board_schedule_block_is_past')) {
    /**
     * スケジュール箱ごとの「過去」判定（終了時刻ベース）
     *
     * @param int $schedule_id
     */
    function aidunite_match_board_schedule_block_is_past($schedule_id) {
        $end_ts = aidunite_match_board_read_schedule_end_timestamp($schedule_id);
        if ($end_ts === null) {
            return false;
        }

        return current_time('timestamp') >= $end_ts;
    }
}

if (!function_exists('aidunite_match_board_collect_own_team_schedule_posts')) {
    /**
     * 自チーム所有の schedule 箱（日付問わず）
     *
     * @param int $viewer_team_id
     * @return WP_Post[]
     */
    function aidunite_match_board_collect_own_team_schedule_posts($viewer_team_id) {
        $viewer_team_id = (int) $viewer_team_id;
        if ($viewer_team_id <= 0) {
            return [];
        }

        $by_id = [];
        if (function_exists('aidunite_market_get_board_my_schedules')) {
            foreach (aidunite_market_get_board_my_schedules($viewer_team_id) as $post) {
                $by_id[(int) $post->ID] = $post;
            }
        }
        if (function_exists('aidunite_market_get_schedule_ids_with_team_match_activity')) {
            foreach (aidunite_market_get_schedule_ids_with_team_match_activity($viewer_team_id) as $sid) {
                $sid = (int) $sid;
                if (isset($by_id[$sid])) {
                    continue;
                }
                $post = get_post($sid);
                if ($post && $post->post_type === 'schedule' && $post->post_status === 'publish') {
                    $by_id[$sid] = $post;
                }
            }
        }

        $team_schedules = get_posts([
            'post_type'      => 'schedule',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => 'team_id',
                    'value'   => (string) $viewer_team_id,
                    'compare' => '=',
                ],
            ],
            'orderby'        => 'meta_value',
            'meta_key'       => 'schedule_date',
            'order'          => 'DESC',
        ]);
        foreach ($team_schedules as $post) {
            $sid = (int) $post->ID;
            if (!isset($by_id[$sid])) {
                $by_id[$sid] = $post;
            }
        }

        $merged = array_values(array_filter($by_id, static function ($post) use ($viewer_team_id) {
            $owner = function_exists('aidunite_resolve_schedule_owner_team_id')
                ? (int) aidunite_resolve_schedule_owner_team_id((int) $post->ID)
                : (function_exists('aidunite_schedule_read_team_id')
                    ? (int) aidunite_schedule_read_team_id((int) $post->ID)
                    : 0);

            return $owner === $viewer_team_id;
        }));

        usort($merged, static function ($a, $b) {
            $da = function_exists('aidunite_schedule_read_normalized_date')
                ? aidunite_schedule_read_normalized_date((int) $a->ID)
                : '';
            $db = function_exists('aidunite_schedule_read_normalized_date')
                ? aidunite_schedule_read_normalized_date((int) $b->ID)
                : '';
            if ($da === $db) {
                return $b->ID <=> $a->ID;
            }

            return strcmp($db, $da);
        });

        return $merged;
    }
}

if (!function_exists('aidunite_match_board_analyze_my_schedule_block')) {
    /**
     * 申請状況タブ：日程ブロックの注目度メタ（ソート・クイックフィルタ用）
     *
     * @param int $schedule_id
     * @param int $viewer_team_id
     * @return array{tier:int,has_established:bool,needs_action:bool,has_mr:bool,date:string,start_time:string}
     */
    function aidunite_match_board_analyze_my_schedule_block($schedule_id, $viewer_team_id) {
        $schedule_id = (int) $schedule_id;
        $viewer_team_id = (int) $viewer_team_id;
        $date = function_exists('aidunite_schedule_read_normalized_date')
            ? (string) aidunite_schedule_read_normalized_date($schedule_id)
            : '';
        $m_disp = function_exists('aidunite_schedule_get_market_list_display')
            ? aidunite_schedule_get_market_list_display($schedule_id)
            : [];
        $start_time = (string) ($m_disp['start_time'] ?? '');

        $has_established = false;
        $needs_action = false;
        $has_mr = false;
        $has_pending_apply = false;
        $has_other_mr = false;
        $tier = 4;

        if ($schedule_id > 0 && $viewer_team_id > 0 && function_exists('aidunite_get_established_requests_for_schedule')) {
            $list = aidunite_get_established_requests_for_schedule($schedule_id, $viewer_team_id, ['exclude_terminal' => true]);
            foreach ($list as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $has_mr = true;
                $cta = (string) ($row['cta_type'] ?? '');
                $st = function_exists('aidunite_normalize_match_request_status')
                    ? aidunite_normalize_match_request_status((string) ($row['status'] ?? ''), '')
                    : strtolower((string) ($row['status'] ?? ''));
                $status_raw = (string) ($row['status'] ?? '');

                if ($cta === 'cancel'
                    || in_array($st, ['established', 'accepted'], true)
                    || in_array($status_raw, ['established', '試合確定', 'accepted', '承認済み'], true)) {
                    $has_established = true;
                }
                if ($cta === 'approve_reject') {
                    $needs_action = true;
                }
                $request_id = (int) ($row['request_id'] ?? 0);
                if ($request_id > 0 && function_exists('aidunite_resolve_match_request_view_state')) {
                    $req_post = get_post($request_id);
                    if ($req_post) {
                        $view = aidunite_resolve_match_request_view_state($req_post, $viewer_team_id);
                        if (!empty($view['proposal_pending_accept'])) {
                            $proposal_by = (int) ($view['proposal_by_team_id'] ?? 0);
                            if ($proposal_by > 0 && $proposal_by !== $viewer_team_id) {
                                $needs_action = true;
                            }
                        }
                    }
                }
                if ($cta === 'cancel_apply' || in_array($st, ['pending', 'publish'], true)) {
                    $has_pending_apply = true;
                } elseif ($cta === 'reapply') {
                    $has_other_mr = true;
                }
            }
        }

        if ($has_established) {
            $tier = 0;
        } elseif ($needs_action) {
            $tier = 1;
        } elseif ($has_pending_apply) {
            $tier = 2;
        } elseif ($has_other_mr || $has_mr) {
            $tier = 3;
        }

        return [
            'tier'            => $tier,
            'has_established' => $has_established,
            'needs_action'    => $needs_action,
            'has_mr'          => $has_mr,
            'date'            => $date,
            'start_time'      => $start_time,
        ];
    }
}

if (!function_exists('aidunite_match_board_sort_my_tab_schedule_posts')) {
    /**
     * 申請状況タブ：確定優先 → 日付昇順
     *
     * @param WP_Post[] $posts
     * @param int       $viewer_team_id
     * @return WP_Post[]
     */
    function aidunite_match_board_sort_my_tab_schedule_posts(array $posts, $viewer_team_id) {
        $viewer_team_id = (int) $viewer_team_id;
        usort($posts, static function ($a, $b) use ($viewer_team_id) {
            $ma = aidunite_match_board_analyze_my_schedule_block((int) $a->ID, $viewer_team_id);
            $mb = aidunite_match_board_analyze_my_schedule_block((int) $b->ID, $viewer_team_id);
            if ($ma['tier'] !== $mb['tier']) {
                return $ma['tier'] <=> $mb['tier'];
            }
            $date_cmp = strcmp((string) $ma['date'], (string) $mb['date']);
            if ($date_cmp !== 0) {
                return $date_cmp;
            }
            $time_cmp = strcmp((string) $ma['start_time'], (string) $mb['start_time']);
            if ($time_cmp !== 0) {
                return $time_cmp;
            }

            return (int) $a->ID <=> (int) $b->ID;
        });

        return $posts;
    }
}

if (!function_exists('aidunite_match_board_filter_my_tab_schedule_posts')) {
    /**
     * 申請状況タブ：クイックフィルタ（要対応 / 申請あり / すべて）
     *
     * @param WP_Post[] $posts
     * @param int       $viewer_team_id
     * @param string    $my_quick all|action|has_mr
     * @return WP_Post[]
     */
    function aidunite_match_board_filter_my_tab_schedule_posts(array $posts, $viewer_team_id, $my_quick) {
        $viewer_team_id = (int) $viewer_team_id;
        $my_quick = sanitize_key((string) $my_quick);
        if ($my_quick === '' || $my_quick === 'all') {
            return $posts;
        }

        return array_values(array_filter($posts, static function ($post) use ($viewer_team_id, $my_quick) {
            $meta = aidunite_match_board_analyze_my_schedule_block((int) $post->ID, $viewer_team_id);
            if ($my_quick === 'action') {
                return !empty($meta['needs_action']);
            }
            if ($my_quick === 'has_mr') {
                return !empty($meta['has_mr']);
            }

            return true;
        }));
    }
}

if (!function_exists('aidunite_match_board_get_my_tab_schedule_posts')) {
    /**
     * 申請状況タブ：終了前の schedule 箱のみ（確定優先ソート済み）
     *
     * @param int $viewer_team_id
     * @return WP_Post[]
     */
    function aidunite_match_board_get_my_tab_schedule_posts($viewer_team_id) {
        $viewer_team_id = (int) $viewer_team_id;
        if ($viewer_team_id <= 0) {
            return [];
        }

        $posts = array_values(array_filter(
            aidunite_match_board_collect_own_team_schedule_posts($viewer_team_id),
            static function ($post) {
                return !aidunite_match_board_schedule_block_is_past((int) $post->ID);
            }
        ));

        return aidunite_match_board_sort_my_tab_schedule_posts($posts, $viewer_team_id);
    }
}

if (!function_exists('aidunite_match_board_get_completed_tab_schedule_posts')) {
    /**
     * 試合完了タブ：終了済み schedule 箱のみ
     *
     * @param int $viewer_team_id
     * @return WP_Post[]
     */
    function aidunite_match_board_get_completed_tab_schedule_posts($viewer_team_id) {
        $viewer_team_id = (int) $viewer_team_id;
        if ($viewer_team_id <= 0) {
            return [];
        }

        return array_values(array_filter(
            aidunite_match_board_collect_own_team_schedule_posts($viewer_team_id),
            static function ($post) {
                return aidunite_match_board_schedule_block_is_past((int) $post->ID);
            }
        ));
    }
}

if (!function_exists('aidunite_match_board_schedule_qualifies_for_completed_block')) {
    /**
     * 試合完了タブに箱ごと出すか（MR あり／募集 ON／確定 intent）
     *
     * @param int $schedule_id
     * @param int $viewer_team_id
     */
    function aidunite_match_board_schedule_qualifies_for_completed_block($schedule_id, $viewer_team_id) {
        $schedule_id = (int) $schedule_id;
        $viewer_team_id = (int) $viewer_team_id;
        if ($schedule_id <= 0 || $viewer_team_id <= 0) {
            return false;
        }
        if (!aidunite_match_board_schedule_block_is_past($schedule_id)) {
            return false;
        }

        if (function_exists('aidunite_get_established_requests_for_schedule')) {
            $rows = aidunite_get_established_requests_for_schedule($schedule_id, $viewer_team_id, ['exclude_terminal' => false]);
            if (!empty($rows)) {
                return true;
            }
        }

        if (function_exists('aidunite_schedule_matching_meta_on') && aidunite_schedule_matching_meta_on($schedule_id)) {
            return true;
        }

        $intent = function_exists('aidunite_schedule_read_intent')
            ? (string) aidunite_schedule_read_intent($schedule_id)
            : '';
        if ($intent === 'confirmed') {
            return true;
        }

        return false;
    }
}

if (!function_exists('aidunite_match_board_completed_row_display_badge')) {
    /**
     * @param array<string, mixed> $est_row
     * @return array{label:string,variant:string}
     */
    function aidunite_match_board_completed_row_display_badge(array $est_row) {
        if (aidunite_match_board_est_row_is_completed($est_row)) {
            return ['label' => '試合完了', 'variant' => 'completed'];
        }

        $badge_label = (string) ($est_row['badge_label'] ?? ($est_row['status'] ?? '—'));
        $variant = function_exists('aidunite_market_my_slot_badge_variant')
            ? aidunite_market_my_slot_badge_variant($badge_label, (string) ($est_row['status'] ?? ''))
            : 'default';

        return ['label' => $badge_label, 'variant' => $variant];
    }
}

if (!function_exists('aidunite_match_board_get_completed_blocks_payload')) {
    /**
     * @param int $viewer_team_id
     * @param int $user_id
     * @return array<int, array<string, mixed>>
     */
    function aidunite_match_board_get_completed_blocks_payload($viewer_team_id, $user_id) {
        $viewer_team_id = (int) $viewer_team_id;
        $user_id = (int) $user_id;
        if ($viewer_team_id <= 0) {
            return [];
        }

        $seen_map = ($user_id > 0 && function_exists('aidunite_user_read_market_board_card_seen'))
            ? aidunite_user_read_market_board_card_seen($user_id)
            : [];
        $blocks = [];

        foreach (aidunite_match_board_get_completed_tab_schedule_posts($viewer_team_id) as $my_post) {
            $sid = (int) $my_post->ID;
            if (!aidunite_match_board_schedule_qualifies_for_completed_block($sid, $viewer_team_id)) {
                continue;
            }
            if (!function_exists('aidunite_get_established_requests_for_schedule')) {
                continue;
            }
            $established_list = aidunite_get_established_requests_for_schedule($sid, $viewer_team_id, ['exclude_terminal' => false]);
            $slot_rows = [];
            foreach ($established_list as $est_row) {
                if (!aidunite_match_board_est_row_is_completed($est_row)) {
                    continue;
                }
                $enriched = aidunite_match_board_enrich_est_row($est_row, $viewer_team_id, $user_id, $seen_map);
                $survey = is_array($enriched['post_match_survey'] ?? null) ? $enriched['post_match_survey'] : [];
                $survey_state = (string) ($survey['state'] ?? '');
                $slot_rows[] = [
                    'label'    => '',
                    'est'      => $enriched,
                    'is_tail'  => false,
                    'is_muted' => $survey_state === 'answered',
                ];
            }
            if ($slot_rows === []) {
                continue;
            }

            $m_disp = function_exists('aidunite_schedule_get_market_list_display')
                ? aidunite_schedule_get_market_list_display($sid)
                : [];
            $m_date = (string) ($m_disp['date'] ?? '');
            $m_start = (string) ($m_disp['start_time'] ?? '');
            $m_end = (string) ($m_disp['end_time'] ?? '');
            $m_place = (string) ($m_disp['place'] ?? '');
            $date_short = $m_date
                ? date('n/j', strtotime($m_date)) . '(' . ['日', '月', '火', '水', '木', '金', '土'][date('w', strtotime($m_date))] . ')'
                : '';
            $time_short = trim(($m_start ?: '') . '–' . ($m_end ?: ''));
            $place_disp = function_exists('aidunite_jp_place') ? aidunite_jp_place($m_place) : $m_place;
            $m_place_lc = strtolower(trim((string) $m_place));
            $is_schedule_away = ($m_place_lc === 'away' || $m_place === 'アウェイ');

            $blocks[] = [
                'schedule_id'      => $sid,
                'date_short'       => $date_short,
                'time_short'       => $time_short,
                'place_disp'       => $place_disp,
                'is_schedule_away' => $is_schedule_away,
                'slot_rows'        => $slot_rows,
            ];
        }

        return $blocks;
    }
}

if (!function_exists('aidunite_match_board_read_tab_badges_payload')) {
    /**
     * @param int $viewer_team_id
     * @param int $user_id
     * @return array{completed_survey_pending:int}
     */
    function aidunite_match_board_read_tab_badges_payload($viewer_team_id, $user_id) {
        $viewer_team_id = (int) $viewer_team_id;
        $pending = 0;
        foreach (aidunite_match_board_get_completed_blocks_payload($viewer_team_id, $user_id) as $block) {
            foreach (($block['slot_rows'] ?? []) as $slot) {
                $est = is_array($slot['est'] ?? null) ? $slot['est'] : [];
                $survey = is_array($est['post_match_survey'] ?? null) ? $est['post_match_survey'] : [];
                if (($survey['state'] ?? '') === 'pending') {
                    $pending++;
                }
            }
        }

        return [
            'completed_survey_pending' => $pending,
        ];
    }
}
