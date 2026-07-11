<?php
/**
 * 試合掲示板・マッチ詳細・REST マッチ候補で共有するヘルパー。
 * 以前は page-match-board-own.php / page-match-detail.php に重複定義されていた。
 * aidunite_get_auto_match_candidates は rest-match-candidates.php から参照されるためテーマ読込時に必ず定義する。
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 試合掲示板・マッチ詳細の「閲覧者チーム」= 操作中 team（current_operating_team_id）。
 *
 * @param int|null $user_id
 * @return int
 */
if (!function_exists('aidunite_match_board_resolve_viewer_team_id')) {
    function aidunite_match_board_resolve_viewer_team_id($user_id = null) {
        $uid = $user_id !== null ? (int) $user_id : get_current_user_id();
        if ($uid <= 0) {
            return 0;
        }
        if (function_exists('aidunite_get_current_team_id')) {
            $tid = (int) aidunite_get_current_team_id($uid);
            if ($tid > 0) {
                return $tid;
            }
        }
        if (function_exists('aidunite_resolve_user_team_id_for_schedule_ops')) {
            $tid = (int) aidunite_resolve_user_team_id_for_schedule_ops($uid);
            if ($tid > 0) {
                return $tid;
            }
        }

        return (int) get_user_meta($uid, 'team_id', true);
    }
}

if (!function_exists('aidunite_get_auto_match_candidates')) {
    function aidunite_get_auto_match_candidates($schedule_id) {
        $current_user      = wp_get_current_user();
        $current_user_team_id = function_exists('aidunite_resolve_schedule_owner_team_id')
            ? aidunite_resolve_schedule_owner_team_id((int) $schedule_id)
            : 0;
        if (!$current_user_team_id && $current_user && $current_user->ID) {
            $current_user_team_id = function_exists('aidunite_match_board_resolve_viewer_team_id')
                ? aidunite_match_board_resolve_viewer_team_id((int) $current_user->ID)
                : 0;
        }
        if (!$current_user_team_id) {
            return [];
        }
        $sch_base = function_exists('aidunite_schedule_get_display_bundle')
            ? aidunite_schedule_get_display_bundle((int) $schedule_id)
            : [];
        $base_date = (string) ($sch_base['date'] ?? '');
        if ($base_date === '' && function_exists('aidunite_schedule_read_normalized_date')) {
            $base_date = aidunite_schedule_read_normalized_date((int) $schedule_id);
        }
        $today             = date('Y-m-d');
        if ($base_date && $base_date < $today) {
            return [];
        }

        $args  = [
            'post_type'      => 'schedule',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'relation' => 'OR',
                    [
                        'key'     => 'matching',
                        'value'   => '1',
                        'compare' => '=',
                    ],
                    [
                        'key'     => 'is_match_requested',
                        'value'   => '1',
                        'compare' => '=',
                    ],
                ],
                [
                    'key'     => 'team_id',
                    'value'   => $current_user_team_id,
                    'compare' => '!=',
                ],
                [
                    'key'     => 'schedule_date',
                    'value'   => $base_date,
                    'compare' => '=',
                ],
            ],
        ];
        $query = new WP_Query($args);

        return $query->posts;
    }
}

if (!function_exists('aidunite_local_match_score')) {
    function aidunite_local_match_score($base_schedule_id, $candidate_post) {
        if (function_exists('aidunite_get_match_label_new')) {
            $normalize_gender = function ($g) {
                if ($g === 'male') {
                    return '男子';
                }
                if ($g === 'female') {
                    return '女子';
                }
                if ($g === 'both') {
                    return '男子・女子可';
                }

                return $g;
            };

            if (function_exists('aidunite_market_build_schedule_array')) {
                $my_schedule = aidunite_market_build_schedule_array((int) $base_schedule_id);
                $other_schedule = aidunite_market_build_schedule_array((int) $candidate_post->ID);
                $my_schedule['gender'] = $normalize_gender($my_schedule['gender'] ?? '');
                $other_schedule['gender'] = $normalize_gender($other_schedule['gender'] ?? '');
            } else {
                $my_bundle = function_exists('aidunite_schedule_get_display_bundle')
                    ? aidunite_schedule_get_display_bundle((int) $base_schedule_id)
                    : [];
                $other_bundle = function_exists('aidunite_schedule_get_display_bundle')
                    ? aidunite_schedule_get_display_bundle((int) $candidate_post->ID)
                    : [];
                $my_gender = (string) ($my_bundle['gender'] ?? '');
                $my_place = (string) ($my_bundle['place'] ?? '');
                $other_gender = (string) ($other_bundle['gender'] ?? '');
                $other_place = (string) ($other_bundle['place'] ?? '');
                $my_schedule = [
                    'schedule_date' => (string) ($my_bundle['date'] ?? ''),
                    'start'         => (string) ($my_bundle['start_time'] ?? ''),
                    'end'           => (string) ($my_bundle['end_time'] ?? ''),
                    'gender'        => $normalize_gender($my_gender),
                    'place'         => $my_place,
                ];
                $other_schedule = [
                    'schedule_date' => (string) ($other_bundle['date'] ?? ''),
                    'start'         => (string) ($other_bundle['start_time'] ?? ''),
                    'end'           => (string) ($other_bundle['end_time'] ?? ''),
                    'gender'        => $normalize_gender($other_gender),
                    'place'         => $other_place,
                ];
            }

            $result = aidunite_get_match_label_new($my_schedule, $other_schedule);
            if ($result) {
                return $result['label'];
            }

            return '条件不一致';
        }

        $score = aidunite_calculate_match_score($base_schedule_id, $candidate_post->ID);
        if ($score >= 10) {
            return 'ベストマッチ';
        }
        if ($score >= 8) {
            return '高マッチ';
        }
        if ($score >= 6) {
            return '中マッチ';
        }
        if ($score >= 4) {
            return '低マッチ';
        }

        return '条件不一致';
    }
}

if (!function_exists('aidunite_get_match_score_breakdown')) {
    function aidunite_get_match_score_breakdown($base_schedule_id, $candidate_schedule_id) {
        $read_score_fields = static function ($schedule_id) {
            $schedule_id = (int) $schedule_id;
            if ($schedule_id <= 0) {
                return ['date' => '', 'start' => '', 'end' => '', 'gender' => '', 'place' => ''];
            }
            if (function_exists('aidunite_schedule_get_api_display_fields')) {
                $api = aidunite_schedule_get_api_display_fields($schedule_id);
                return [
                    'date'   => (string) ($api['date'] ?? ''),
                    'start'  => (string) ($api['start_time'] ?? ''),
                    'end'    => (string) ($api['end_time'] ?? ''),
                    'gender' => (string) ($api['gender'] ?? ''),
                    'place'  => (string) ($api['place'] ?? ''),
                ];
            }

            return [
                'date'   => function_exists('aidunite_schedule_read_normalized_date')
                    ? aidunite_schedule_read_normalized_date($schedule_id)
                    : '',
                'start'  => '',
                'end'    => '',
                'gender' => function_exists('aidunite_schedule_read_gender_raw')
                    ? aidunite_schedule_read_gender_raw($schedule_id)
                    : '',
                'place'  => function_exists('aidunite_schedule_read_place_raw')
                    ? aidunite_schedule_read_place_raw($schedule_id)
                    : '',
            ];
        };

        $my = $read_score_fields($base_schedule_id);
        $other = $read_score_fields($candidate_schedule_id);
        $my_date = $my['date'];
        $my_start = $my['start'];
        $my_end = $my['end'];
        $my_gender = $my['gender'];
        $my_place = $my['place'];
        $other_date = $other['date'];
        $other_start = $other['start'];
        $other_end = $other['end'];
        $other_gender = $other['gender'];
        $other_place = $other['place'];

        $normalize_gender = function ($g) {
            if ($g === 'male') {
                return '男子';
            }
            if ($g === 'female') {
                return '女子';
            }
            if ($g === 'both') {
                return '男子・女子可';
            }

            return $g;
        };

        $my = [
            'schedule_date' => $my_date,
            'start'         => $my_start,
            'end'           => $my_end,
            'gender'        => $normalize_gender($my_gender),
            'place'         => $my_place,
        ];
        $other = [
            'schedule_date' => $other_date,
            'start'         => $other_start,
            'end'           => $other_end,
            'gender'        => $normalize_gender($other_gender),
            'place'         => $other_place,
        ];

        $breakdown = [
            'date'   => 0,
            'time'   => 0,
            'gender' => 0,
            'place'  => 0,
            'total'  => 0,
        ];

        if ($my['schedule_date'] === $other['schedule_date']) {
            $breakdown['date'] = 2;
        }

        if ($my['start'] === $other['start'] && $my['end'] === $other['end']) {
            $breakdown['time'] = 2;
        } elseif (function_exists('overlaps') && overlaps($my, $other)) {
            $breakdown['time'] = 1;
        }

        if (function_exists('gender_score')) {
            $breakdown['gender'] = gender_score($my['gender'], $other['gender']);
            if ($breakdown['gender'] < 0) {
                $breakdown['gender'] = 0;
            }
        } else {
            if ($my['gender'] === $other['gender']) {
                $breakdown['gender'] = 2;
            } elseif ($my['gender'] === '男子・女子可' || $other['gender'] === '男子・女子可') {
                $breakdown['gender'] = 1;
            }
        }

        if (function_exists('place_score_updated')) {
            $breakdown['place'] = place_score_updated($my['place'], $other['place']);
            if ($breakdown['place'] < 0) {
                $breakdown['place'] = 0;
            }
        } else {
            if ($my['place'] === 'either' || $other['place'] === 'either') {
                $breakdown['place'] = 1;
            } elseif (($my['place'] === 'home' && $other['place'] === 'away') || ($my['place'] === 'away' && $other['place'] === 'home')) {
                $breakdown['place'] = 2;
            } elseif ($my['place'] === $other['place']) {
                $breakdown['place'] = 2;
            }
        }

        $breakdown['total'] = $breakdown['date'] + $breakdown['time'] + $breakdown['gender'] + $breakdown['place'];

        return $breakdown;
    }
}


if (!function_exists('aidunite_market_viewer_hides_established_recruit_row')) {
    /**
     * 募集中タブ: 閲覧 team が当該募集と既に試合確定している行は出さない（申請状況タブで確認）。
     *
     * @param int $viewer_team_id
     * @param int $recruit_schedule_id
     * @return bool
     */
    function aidunite_market_viewer_hides_established_recruit_row($viewer_team_id, $recruit_schedule_id) {
        if (!function_exists('aidunite_get_established_context_for_recruit_row')) {
            return false;
        }
        $ctx = aidunite_get_established_context_for_recruit_row((int) $viewer_team_id, (int) $recruit_schedule_id);

        return is_array($ctx) && !empty($ctx['request_id']);
    }
}

if (!function_exists('aidunite_match_request_established_meta_query_status_values')) {
    /**
     * 募集中行の成立 MR 検索用 status 値（meta 保存値・レガシー含む）
     *
     * @return string[]
     */
    function aidunite_match_request_established_meta_query_status_values() {
        return ['established', '試合確定', 'accepted', '承認済み'];
    }
}

if (!function_exists('aidunite_match_request_is_viewer_recruit_establishment')) {
    /**
     * 閲覧 team と募集 team の間で、試合確定として扱う MR か
     *
     * @param WP_Post|int $request
     * @param int         $viewer_team_id
     * @param int         $recruit_team_id
     */
    function aidunite_match_request_is_viewer_recruit_establishment($request, $viewer_team_id, $recruit_team_id) {
        $request = $request instanceof WP_Post ? $request : get_post((int) $request);
        if (!$request || $request->post_type !== 'match_request') {
            return false;
        }
        $viewer_team_id = (int) $viewer_team_id;
        $recruit_team_id = (int) $recruit_team_id;
        if ($viewer_team_id <= 0 || $recruit_team_id <= 0) {
            return false;
        }
        if (function_exists('aidunite_match_request_involves_team_pair')
            && !aidunite_match_request_involves_team_pair($request, $viewer_team_id, $recruit_team_id)) {
            return false;
        }
        $rid = (int) $request->ID;
        if (function_exists('aidunite_match_request_counts_as_guest_commitment')
            && aidunite_match_request_counts_as_guest_commitment($rid)) {
            return true;
        }
        $raw = (string) get_post_meta($rid, 'status', true);
        $norm = function_exists('aidunite_normalize_match_request_status')
            ? aidunite_normalize_match_request_status($raw, (string) $request->post_status)
            : strtolower($raw);
        if (in_array($norm, ['canceled', 'rejected'], true)) {
            return false;
        }
        if (get_post_meta($rid, 'established_at', true) !== '') {
            return true;
        }

        return in_array($norm, ['established', 'accepted'], true);
    }
}

if (!function_exists('aidunite_match_request_touches_normalized_date')) {
    /**
     * MR に紐づく schedule（game 含む）のいずれかが指定日と一致するか
     *
     * @param int    $request_id
     * @param string $date_normalized Y-m-d
     */
    function aidunite_match_request_touches_normalized_date($request_id, $date_normalized) {
        $request_id = (int) $request_id;
        $date_normalized = trim((string) $date_normalized);
        if ($request_id <= 0 || $date_normalized === '') {
            return false;
        }
        $schedule_ids = [];
        foreach (['my_schedule_id', 'to_schedule_id', 'from_schedule_id'] as $meta_key) {
            $sid = (int) get_post_meta($request_id, $meta_key, true);
            if ($meta_key === 'to_schedule_id' && $sid === 9999) {
                $sid = 0;
            }
            if ($sid > 0) {
                $schedule_ids[$sid] = true;
            }
        }
        if (function_exists('aidunite_resolve_match_game_id_for_match_request')) {
            $game_id = (int) aidunite_resolve_match_game_id_for_match_request($request_id);
            if ($game_id > 0) {
                $schedule_ids[$game_id] = true;
            }
        }
        foreach (array_keys($schedule_ids) as $sid) {
            $d = function_exists('aidunite_schedule_read_normalized_date')
                ? aidunite_schedule_read_normalized_date((int) $sid)
                : '';
            if ($d === $date_normalized) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('aidunite_match_request_established_row_context_from_post')) {
    /**
     * @param WP_Post $req
     * @param int     $viewer_team_id
     * @return array{request_id:int,my_schedule_id:int,to_schedule_id:int}|null
     */
    function aidunite_match_request_established_row_context_from_post(WP_Post $req, $viewer_team_id) {
        $rid = (int) $req->ID;
        if (function_exists('aidunite_match_request_get_link_schedule_teams')) {
            [$my_sid, $to_sid] = array_slice(aidunite_match_request_get_link_schedule_teams($rid), 0, 2);
        } else {
            $my_sid = (int) get_post_meta($rid, 'my_schedule_id', true);
            if ($my_sid <= 0) {
                $my_sid = (int) get_post_meta($rid, 'from_schedule_id', true);
            }
            $to_sid = (int) get_post_meta($rid, 'to_schedule_id', true);
            if ($to_sid === 9999) {
                $to_sid = 0;
            }
        }
        if ($my_sid <= 0 || $to_sid <= 0) {
            return null;
        }
        if (function_exists('aidunite_match_request_get_link_schedule_teams')) {
            [, , $my_team, $to_team] = array_slice(aidunite_match_request_get_link_schedule_teams($rid), 0, 4);
        } else {
            $my_team = (int) get_post_meta($my_sid, 'team_id', true);
            $to_team = (int) get_post_meta($to_sid, 'team_id', true);
        }
        if ($my_team !== (int) $viewer_team_id && $to_team !== (int) $viewer_team_id) {
            return null;
        }

        return [
            'request_id'     => $rid,
            'my_schedule_id' => (int) $my_sid,
            'to_schedule_id' => (int) $to_sid,
        ];
    }
}

if (!function_exists('aidunite_get_established_context_for_recruit_row')) {
    /**
     * 募集行に対して、閲覧チームが関係する established MR を1件返す。
     *
     * Pass1: 募集 schedule ID が MR の my/to/from に直結している場合。
     * Pass2: ホスト承認などで MR が anchor（別 schedule）に付く場合 — 閲覧×募集 team＋同日で解決。
     *
     * @param int $viewer_team_id
     * @param int $candidate_schedule_id
     * @return array{request_id:int,my_schedule_id:int,to_schedule_id:int}|null
     */
    function aidunite_get_established_context_for_recruit_row($viewer_team_id, $candidate_schedule_id) {
        $viewer_team_id = (int) $viewer_team_id;
        $candidate_schedule_id = (int) $candidate_schedule_id;
        if ($viewer_team_id <= 0 || $candidate_schedule_id <= 0) {
            return null;
        }

        $status_values = function_exists('aidunite_match_request_established_meta_query_status_values')
            ? aidunite_match_request_established_meta_query_status_values()
            : ['established', '試合確定'];

        $posts = get_posts([
            'post_type'      => 'match_request',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [
                'relation' => 'AND',
                ['key' => 'status', 'value' => $status_values, 'compare' => 'IN'],
                [
                    'relation' => 'OR',
                    ['key' => 'to_schedule_id', 'value' => (string) $candidate_schedule_id, 'compare' => '='],
                    ['key' => 'my_schedule_id', 'value' => (string) $candidate_schedule_id, 'compare' => '='],
                    ['key' => 'from_schedule_id', 'value' => (string) $candidate_schedule_id, 'compare' => '='],
                ],
            ],
        ]);

        foreach ($posts as $req) {
            $recruit_team_id = (int) get_post_meta($candidate_schedule_id, 'team_id', true);
            if (!function_exists('aidunite_match_request_is_viewer_recruit_establishment')
                || !aidunite_match_request_is_viewer_recruit_establishment($req, $viewer_team_id, $recruit_team_id)) {
                continue;
            }
            $ctx = function_exists('aidunite_match_request_established_row_context_from_post')
                ? aidunite_match_request_established_row_context_from_post($req, $viewer_team_id)
                : null;
            if ($ctx === null) {
                continue;
            }
            $rid = (int) $req->ID;
            $my_sid = (int) $ctx['my_schedule_id'];
            $to_sid = (int) $ctx['to_schedule_id'];
            if ($my_sid === $candidate_schedule_id || $to_sid === $candidate_schedule_id) {
                return $ctx;
            }
        }

        $recruit_bundle = function_exists('aidunite_schedule_get_display_bundle')
            ? aidunite_schedule_get_display_bundle($candidate_schedule_id)
            : [];
        $recruit_team_id = (int) ($recruit_bundle['team_id'] ?? (function_exists('aidunite_schedule_read_team_id')
            ? aidunite_schedule_read_team_id($candidate_schedule_id)
            : 0));
        $recruit_date = function_exists('aidunite_schedule_read_normalized_date')
            ? aidunite_schedule_read_normalized_date($candidate_schedule_id)
            : '';
        if ($recruit_team_id <= 0 || $recruit_team_id === $viewer_team_id || $recruit_date === '') {
            return null;
        }

        $pair_posts = get_posts([
            'post_type'      => 'match_request',
            'post_status'    => 'any',
            'posts_per_page' => 30,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [
                'relation' => 'OR',
                ['key' => 'from_team_id', 'value' => (string) $viewer_team_id, 'compare' => '='],
                ['key' => 'from_team_id', 'value' => (string) $recruit_team_id, 'compare' => '='],
            ],
        ]);

        foreach ($pair_posts as $req) {
            if (!function_exists('aidunite_match_request_is_viewer_recruit_establishment')
                || !aidunite_match_request_is_viewer_recruit_establishment($req, $viewer_team_id, $recruit_team_id)) {
                continue;
            }
            if (!function_exists('aidunite_match_request_touches_normalized_date')
                || !aidunite_match_request_touches_normalized_date((int) $req->ID, $recruit_date)) {
                continue;
            }
            $ctx = function_exists('aidunite_match_request_established_row_context_from_post')
                ? aidunite_match_request_established_row_context_from_post($req, $viewer_team_id)
                : null;
            if ($ctx !== null) {
                return $ctx;
            }
        }

        return null;
    }
}

if (!function_exists('aidunite_get_application_status')) {
    /**
     * 募集中タブ1行の申請ステータス（MR 解決・表示 code）。
     *
     * @param int $current_user_team_id
     * @param int $my_schedule_id
     * @param int $candidate_team_id
     * @param int $candidate_id 相手募集 schedule ID
     * @return array{text:string,class:string,request_id:int,is_requester:bool,code:string}
     */
    function aidunite_get_application_status($current_user_team_id, $my_schedule_id, $candidate_team_id, $candidate_id) {
        $unapplied_label = function_exists('aidunite_get_match_status_label')
            ? aidunite_get_match_status_label('not_applied', 'text')
            : '未申請';
        $status_text  = $unapplied_label;
        $status_class = '';
        $request_id   = 0;
        $is_requester = false;
        $code         = 'not_applied';

        $established_ctx = function_exists('aidunite_get_established_context_for_recruit_row')
            ? aidunite_get_established_context_for_recruit_row((int) $current_user_team_id, (int) $candidate_id)
            : null;
        if (is_array($established_ctx) && !empty($established_ctx['request_id'])) {
            $request_id = (int) $established_ctx['request_id'];
            $from_team_id = (int) get_post_meta($request_id, 'from_team_id', true);
            $is_requester = ($from_team_id > 0 && $from_team_id === (int) $current_user_team_id);

            return [
                'text'         => '試合確定',
                'class'        => 'badge-established',
                'request_id'   => $request_id,
                'is_requester' => $is_requester,
                'code'         => 'established',
            ];
        }

        $latest_request = get_latest_match_request_bidirectional(
            $current_user_team_id,
            $my_schedule_id,
            $candidate_team_id,
            $candidate_id,
            [
                'involve_schedule_id' => (int) $candidate_id,
            ]
        );

        if ($latest_request) {
            $request_id   = (int) $latest_request->ID;
            $from_team_id = (int) get_post_meta($latest_request->ID, 'from_team_id', true);
            $is_requester = ($from_team_id > 0 && $from_team_id === (int) $current_user_team_id);

            if (function_exists('aidunite_resolve_match_request_view_state')) {
                $view = aidunite_resolve_match_request_view_state($latest_request, (int) $current_user_team_id);
                $code = (string) ($view['display_code'] ?? 'not_applied');
                $status_text = (string) ($view['display_label'] ?? $unapplied_label);
                $status_class = (string) ($view['badge_class'] ?? '');

                if ($code === 'pending' || $code === 'publish') {
                    $status_text = $is_requester ? '申請中' : '申請受付中';
                    $status_class = $status_class !== '' ? $status_class : 'badge-status';
                    $code = 'pending';
                } elseif ($code === 'accepted') {
                    $status_text = '承認済み';
                    $status_class = $status_class !== '' ? $status_class : 'badge-approved';
                } elseif ($code === 'established') {
                    $status_text = '試合確定';
                    $status_class = $status_class !== '' ? $status_class : 'badge-established';
                } elseif ($code === 'rejected') {
                    $status_text = '拒否済み';
                    $status_class = $status_class !== '' ? $status_class : 'badge-rejected';
                } elseif ($code === 'canceled' || $code === 'canceled_opponent') {
                    if ($code === 'canceled_opponent') {
                        $status_text = '相手キャンセル';
                        $status_class = 'badge-rejected';
                    } else {
                        $status_text = 'キャンセル済み';
                        $status_class = 'badge-canceled';
                    }
                } elseif ($code === 'not_applied') {
                    $status_text = $unapplied_label;
                    $status_class = '';
                } elseif ($status_class === '') {
                    $status_class = 'badge-canceled';
                }
            } else {
                $request_status = get_post_meta($latest_request->ID, 'status', true);
                $norm = function_exists('aidunite_normalize_match_request_status')
                    ? aidunite_normalize_match_request_status((string) $request_status, $latest_request->post_status)
                    : (string) $request_status;
                if ($norm === 'draft') {
                    $norm = 'not_applied';
                }

                switch ($norm) {
                    case 'pending':
                    case 'publish':
                        $status_text  = $is_requester ? '申請中' : '申請受付中';
                        $status_class = 'badge-status';
                        $code         = 'pending';
                        break;
                    case 'accepted':
                        $status_text  = '承認済み';
                        $status_class = 'badge-approved';
                        $code         = 'accepted';
                        break;
                    case 'established':
                        $status_text  = '試合確定';
                        $status_class = 'badge-established';
                        $code         = 'established';
                        break;
                    case 'rejected':
                        $status_text  = '拒否済み';
                        $status_class = 'badge-rejected';
                        $code         = 'rejected';
                        break;
                    case 'canceled':
                        $canceled_by = get_post_meta($latest_request->ID, 'canceled_by_team_id', true);
                        if (!empty($canceled_by) && (int) $canceled_by !== (int) $current_user_team_id) {
                            $status_text  = '相手キャンセル';
                            $status_class = 'badge-rejected';
                            $code         = 'canceled_opponent';
                        } else {
                            $status_text  = 'キャンセル済み';
                            $status_class = 'badge-canceled';
                            $code         = 'canceled';
                        }
                        break;
                    default:
                        $status_text  = $unapplied_label;
                        $status_class = '';
                        $code         = 'not_applied';
                        break;
                }
            }
        }

        return [
            'text'         => $status_text,
            'class'        => $status_class,
            'request_id'   => $request_id,
            'is_requester' => $is_requester,
            'code'         => $code,
        ];
    }
}

if (!function_exists('aidunite_market_board_my_has_incoming_application_rows')) {
    /**
     * 申請状況タブ: 自チーム募集に対する申請・成立行が1件でもあるか
     *
     * @param int   $team_id
     * @param array $schedule_posts schedule 投稿の配列
     * @return bool
     */
    function aidunite_market_board_my_has_incoming_application_rows($team_id, $schedule_posts) {
        $team_id = (int) $team_id;
        if ($team_id <= 0 || !is_array($schedule_posts) || $schedule_posts === []) {
            return false;
        }
        if (!function_exists('aidunite_get_established_requests_for_schedule')) {
            return false;
        }

        foreach ($schedule_posts as $post) {
            $sid = is_object($post) ? (int) $post->ID : (int) $post;
            if ($sid <= 0) {
                continue;
            }
            $list = aidunite_get_established_requests_for_schedule($sid, $team_id, ['exclude_terminal' => true]);
            foreach ($list as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $cta = (string) ($row['cta_type'] ?? '');
                if (in_array($cta, ['approve_reject', 'established_chat', 'chat', 'cancel_established', 'reapply', 'detail'], true)) {
                    return true;
                }
                $st = function_exists('aidunite_normalize_match_request_status')
                    ? aidunite_normalize_match_request_status((string) ($row['status'] ?? ''), '')
                    : strtolower((string) ($row['status'] ?? ''));
                if (in_array($st, ['pending', 'established', 'accepted', 'publish'], true)) {
                    return true;
                }
            }
        }

        return false;
    }
}

if (!function_exists('aidunite_market_board_should_show_my_tab_nudge')) {
    /**
     * オンボーディング中・申請状況タブで「次に何をするか」案内を出すか
     *
     * @param int   $team_id
     * @param array $schedule_posts
     * @return bool
     */
    function aidunite_market_board_should_show_my_tab_nudge($team_id, $schedule_posts) {
        $team_id = (int) $team_id;
        if ($team_id <= 0 || !is_array($schedule_posts) || $schedule_posts === []) {
            return false;
        }
        if (!function_exists('aidunite_activation_is_mission_ui') || !aidunite_activation_is_mission_ui($team_id)) {
            return false;
        }
        $stage = function_exists('aidunite_get_team_activation_stage')
            ? (string) aidunite_get_team_activation_stage($team_id)
            : '';
        if (!in_array($stage, ['recruit_published', 'first_application'], true)) {
            return false;
        }

        if (function_exists('aidunite_onboarding_bot_ensure_for_team')) {
            aidunite_onboarding_bot_ensure_for_team($team_id);
        }

        return true;
    }
}

if (!function_exists('aidunite_market_board_should_show_my_tab_bot_approve_nudge')) {
    /**
     * 申請状況タブ: 練習相手チームの承認待ち案内（申請待ちカードの代替）
     *
     * @param int $team_id
     * @return bool
     */
    function aidunite_market_board_should_show_my_tab_bot_approve_nudge($team_id) {
        $team_id = (int) $team_id;
        if ($team_id <= 0) {
            return false;
        }
        if (!function_exists('aidunite_activation_is_mission_ui') || !aidunite_activation_is_mission_ui($team_id)) {
            return false;
        }
        $stage = function_exists('aidunite_get_team_activation_stage')
            ? (string) aidunite_get_team_activation_stage($team_id)
            : '';
        if (!in_array($stage, ['recruit_published', 'first_application'], true)) {
            return false;
        }
        if (!defined('AIDUNITE_TEAM_META_ONBOARDING_BOT_MR')) {
            return false;
        }
        $mr_id = (int) get_post_meta($team_id, AIDUNITE_TEAM_META_ONBOARDING_BOT_MR, true);
        if ($mr_id <= 0 || !function_exists('aidunite_match_request_is_onboarding_bot')
            || !aidunite_match_request_is_onboarding_bot($mr_id)) {
            return false;
        }
        $st = function_exists('aidunite_normalize_match_request_status')
            ? aidunite_normalize_match_request_status((string) get_post_meta($mr_id, 'status', true), '')
            : strtolower((string) get_post_meta($mr_id, 'status', true));

        return $st === 'pending';
    }
}

if (!function_exists('aidunite_market_board_render_my_tab_nudge_html')) {
    /**
     * 申請状況タブ先頭のオンボーディング案内カード HTML
     *
     * @param int        $team_id
     * @param array|null $schedule_posts
     * @return string
     */
    function aidunite_market_board_render_my_tab_nudge_html($team_id, $schedule_posts) {
        $team_id = (int) $team_id;
        $schedule_posts = is_array($schedule_posts) ? $schedule_posts : [];
        $show_base = function_exists('aidunite_market_board_should_show_my_tab_nudge')
            && aidunite_market_board_should_show_my_tab_nudge($team_id, $schedule_posts);
        $show_bot = function_exists('aidunite_market_board_should_show_my_tab_bot_approve_nudge')
            && aidunite_market_board_should_show_my_tab_bot_approve_nudge($team_id);

        if (!$show_base && !$show_bot) {
            return '';
        }

        $html = '';
        $icon_html = function_exists('aidunite_render_theme_icon')
            ? aidunite_render_theme_icon('info', ['width' => '20', 'height' => '20'], 'aidunite-icon--inline')
            : '';

        if ($show_bot) {
            $html .= '<div class="market-axis-my-nudge market-axis-my-nudge--bot-approve" role="status">';
            $html .= '<div class="market-axis-my-nudge__card">';
            $html .= '<span class="market-axis-my-nudge__icon" aria-hidden="true">' . $icon_html . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            $html .= '<div class="market-axis-my-nudge__body">';
            $html .= '<p class="market-axis-my-nudge__title">練習相手から申請が届いています</p>';
            $html .= '<p class="market-axis-my-nudge__text"><strong>ボット練習はメール・プッシュ通知は届きません。</strong>下の日程から「詳細を確認」を押して承認してください。</p>';
            $html .= '</div></div></div>';
        }

        if ($show_base && !$show_bot) {
            $html .= '<div class="market-axis-my-nudge" role="status">';
            $html .= '<div class="market-axis-my-nudge__card">';
            $html .= '<span class="market-axis-my-nudge__icon" aria-hidden="true">' . $icon_html . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            $html .= '<div class="market-axis-my-nudge__body">';
            $html .= '<p class="market-axis-my-nudge__title">申請を待っています</p>';
            $html .= '<p class="market-axis-my-nudge__text">初回の練習相手から申請が届くと、このタブに表示されます。届いたら<strong>詳細を確認</strong>から承認してください（通知は届きません）。</p>';
            $html .= '<ul class="market-axis-my-nudge__list">';
            $html .= '<li>実チームからの申請は、お知らせ・メールでも通知されます</li>';
            $html .= '<li>他チームへ申請する場合は「募集中の試合」タブをご利用ください</li>';
            $html .= '</ul>';
            $html .= '</div></div></div>';
        }

        return $html;
    }
}

if (!function_exists('aidunite_market_board_render_debug_panel')) {
    /**
     * 掲示板デバッグパネル（?au_market_debug=1、任意で &debug_schedule_id=）
     *
     * @param array<string, mixed> $ctx
     */
    function aidunite_market_board_render_debug_panel(array $ctx) {
        $viewer_team_id = (int) ($ctx['viewer_team_id'] ?? 0);
        $user_id = (int) ($ctx['user_id'] ?? 0);
        $date_from = (string) ($ctx['date_from'] ?? '');
        $date_to = (string) ($ctx['date_to'] ?? '');
        $filter_gender = (string) ($ctx['filter_gender'] ?? '');
        $all_recruitments = $ctx['all_recruitments'] ?? [];
        $rows = $ctx['rows'] ?? [];
        $row_debug = $ctx['row_debug'] ?? [];
        $my_matching_schedules = $ctx['my_matching_schedules'] ?? [];

        if (!is_array($all_recruitments)) {
            $all_recruitments = [];
        }
        ?>
        <div class="alert alert-warning market-axis-debug-panel" role="status" style="margin:var(--spacing-base) 0;font-size:var(--font-size-sm);">
            <p><strong>掲示板デバッグ</strong>（<code>au_market_debug=1</code>、任意 <code>debug_schedule_id=</code>）</p>
            <ul style="margin:0;padding-left:1.2em;">
                <li>viewer_team_id: <?php echo $viewer_team_id; ?>（<?php echo esc_html(get_the_title($viewer_team_id) ?: '—'); ?>）</li>
                <?php if ($user_id > 0 && function_exists('aidunite_get_all_stored_operating_team_ids')) : ?>
                <li>stored operating: <?php echo esc_html(implode(',', aidunite_get_all_stored_operating_team_ids($user_id))); ?></li>
                <?php endif; ?>
                <li>date_filter: <?php echo esc_html($date_from); ?> ～ <?php echo esc_html($date_to); ?></li>
                <li>filter_gender: <?php echo $filter_gender !== '' ? esc_html($filter_gender) : 'auto（閲覧 team）'; ?></li>
                <li>all_recruitments: <?php echo count($all_recruitments); ?>
                    <?php
                    if ($all_recruitments !== []) {
                        $ids = array_map(static function ($p) {
                            return (int) (is_object($p) ? $p->ID : $p);
                        }, $all_recruitments);
                        echo ' IDs=' . esc_html(implode(',', $ids));
                    }
                    ?>
                </li>
                <li>表示 rows: <?php echo is_array($rows) ? count($rows) : 0; ?>
                    <?php
                    if (!empty($row_debug)) {
                        echo ' ' . esc_html(wp_json_encode($row_debug, JSON_UNESCAPED_UNICODE));
                    }
                    ?>
                </li>
                <?php if (!empty($GLOBALS['aidunite_market_board_debug']) && is_array($GLOBALS['aidunite_market_board_debug'])) : ?>
                <li>pipeline: <?php echo esc_html(wp_json_encode($GLOBALS['aidunite_market_board_debug'], JSON_UNESCAPED_UNICODE)); ?></li>
                <?php endif; ?>
                <?php
                $dbg_sid = isset($_GET['debug_schedule_id']) ? (int) $_GET['debug_schedule_id'] : 0;
                if ($dbg_sid > 0) :
                    $dbg_date = function_exists('aidunite_schedule_read_normalized_date')
                        ? aidunite_schedule_read_normalized_date($dbg_sid)
                        : '';
                    $dbg_in_date = ($dbg_date !== '' && $dbg_date >= $date_from && $dbg_date <= $date_to);
                    $dbg_gender = function_exists('aidunite_market_recruitment_gender_canonical')
                        ? aidunite_market_recruitment_gender_canonical($dbg_sid)
                        : '';
                    $dbg_passes = function_exists('aidunite_market_recruitment_passes_board_filters')
                        ? aidunite_market_recruitment_passes_board_filters($dbg_sid)
                        : null;
                    $dbg_hidden = function_exists('aidunite_market_recruit_hidden_for_viewer_team')
                        ? aidunite_market_recruit_hidden_for_viewer_team($dbg_sid, $viewer_team_id)
                        : null;
                    $dbg_post = get_post($dbg_sid);
                    $dbg_row = ($dbg_post instanceof WP_Post && function_exists('aidunite_market_get_row_state'))
                        ? aidunite_market_get_row_state($dbg_post, $my_matching_schedules, $viewer_team_id)
                        : null;
                    $rdbg = function_exists('aidunite_recruit_open_for_market_board_reason')
                        ? aidunite_recruit_open_for_market_board_reason($dbg_sid)
                        : ['open' => false, 'reason' => ''];
                    $acc = function_exists('aidunite_recruit_accepts_match_applications')
                        && aidunite_recruit_accepts_match_applications($dbg_sid);
                    ?>
                <li><strong>schedule #<?php echo $dbg_sid; ?></strong>
                    date=<?php echo esc_html($dbg_date ?: '—'); ?>
                    in_date_range=<?php echo $dbg_in_date ? 'yes' : 'NO'; ?>
                    gender=<?php echo esc_html($dbg_gender ?: '—'); ?>
                    passes_filters=<?php echo $dbg_passes === null ? '—' : ($dbg_passes ? 'yes' : 'NO'); ?>
                    hidden_for_viewer=<?php echo $dbg_hidden === null ? '—' : ($dbg_hidden ? 'YES' : 'no'); ?>
                    accepts=<?php echo $acc ? 'true' : 'false'; ?>
                    open=<?php echo !empty($rdbg['open']) ? 'true' : 'false'; ?>
                    (<?php echo esc_html((string) ($rdbg['reason'] ?? '')); ?>)
                    <?php if (is_array($dbg_row)) {
                        echo ' row_state=' . esc_html((string) ($dbg_row['state'] ?? ''));
                    } ?>
                </li>
                <?php endif; ?>
            </ul>
        </div>
        <?php
    }
}

/**
 * 申請状況タブ：ステータス表示用バッジ variant（色分け CSS 用）
 *
 * @param string $badge_label
 * @param string $status
 * @return string
 */
if (!function_exists('aidunite_market_my_slot_badge_variant')) {
    function aidunite_market_my_slot_badge_variant($badge_label, $status = '') {
        $label = (string) $badge_label;
        $norm = function_exists('aidunite_normalize_match_request_status')
            ? aidunite_normalize_match_request_status((string) $status, '')
            : strtolower((string) $status);

        if ($norm === 'established' || in_array($label, ['試合確定', '成立'], true)) {
            return 'established';
        }
        if (in_array($norm, ['pending', 'publish'], true) || in_array($label, ['申請中', '承認待ち', '申請受付中', '相手の承認待ち'], true)) {
            return 'pending';
        }
        if ($norm === 'rejected' || in_array($label, ['拒否済み', '相手キャンセル'], true)) {
            return 'rejected';
        }
        if ($norm === 'canceled' || $label === 'キャンセル済み') {
            return 'canceled';
        }
        if (strpos($label, '条件') !== false || strpos($label, '調整') !== false || $norm === 'proposal_possible') {
            return 'adjust';
        }
        if (strpos($label, '期限') !== false || $norm === 'invalid_closed') {
            return 'expired';
        }
        if (strpos($label, '下書') !== false || strpos($label, '本申請前') !== false) {
            return 'draft';
        }

        return 'default';
    }
}

/**
 * 申請状況スロット行：共通点バッジ
 *
 * @param int   $viewer_team_id
 * @param int   $my_schedule_id
 * @param int   $other_schedule_id
 * @param int   $other_team_id
 * @return array<int, string>
 */
if (!function_exists('aidunite_market_my_slot_common_badges')) {
    function aidunite_market_my_slot_common_badges($viewer_team_id, $my_schedule_id, $other_schedule_id, $other_team_id) {
        $viewer_team_id = (int) $viewer_team_id;
        $my_schedule_id = (int) $my_schedule_id;
        $other_schedule_id = (int) $other_schedule_id;
        $other_team_id = (int) $other_team_id;
        $common_badges = [];

        if ($viewer_team_id <= 0 || $other_team_id <= 0 || !function_exists('aidunite_match_detail_common_points')) {
            return $common_badges;
        }

        $viewer_bundle = function_exists('aidunite_team_get_display_bundle') ? aidunite_team_get_display_bundle($viewer_team_id) : [];
        $other_team_bundle = function_exists('aidunite_team_get_display_bundle') ? aidunite_team_get_display_bundle($other_team_id) : [];
        $my_sched = $my_schedule_id > 0 && function_exists('aidunite_schedule_get_display_bundle')
            ? aidunite_schedule_get_display_bundle($my_schedule_id)
            : [];
        $other_sched = $other_schedule_id > 0 && function_exists('aidunite_schedule_get_display_bundle')
            ? aidunite_schedule_get_display_bundle($other_schedule_id)
            : [];

        $common_badges = aidunite_match_detail_common_points(
            $viewer_team_id,
            $other_team_id,
            is_array($viewer_bundle) ? $viewer_bundle : [],
            is_array($other_team_bundle) ? $other_team_bundle : [],
            [
                'my_schedule_data' => is_array($my_sched) ? $my_sched : [],
                'other_schedule_data' => is_array($other_sched) ? $other_sched : [],
            ]
        );

        if ($my_schedule_id > 0 && $other_schedule_id > 0 && function_exists('aidunite_market_row_state_from_schedules')) {
            $score_pack = aidunite_market_row_state_from_schedules($other_schedule_id, [$my_schedule_id], $viewer_team_id);
            $scores = is_array($score_pack['scores'] ?? null) ? $score_pack['scores'] : [];
            $t_score = (int) ($scores['T'] ?? 0);
            $v_score = (int) ($scores['V'] ?? 0);
            $a_score = (int) ($scores['A'] ?? 0);
            if ($t_score >= 80 && !in_array('時間帯一致', $common_badges, true)) {
                $common_badges[] = '時間帯一致';
            } elseif ($t_score >= 55 && !in_array('活動時間帯が近い', $common_badges, true)) {
                $common_badges[] = '活動時間帯が近い';
            }
            if ($v_score >= 30 && !in_array('会場条件OK', $common_badges, true)) {
                $common_badges[] = '会場条件OK';
            }
            if ($a_score >= 50 && !in_array('同じ活動エリア', $common_badges, true)) {
                $common_badges[] = '同じ活動エリア';
            } elseif ($a_score >= 25 && !in_array('活動エリア近い', $common_badges, true)) {
                $common_badges[] = '活動エリア近い';
            }
        }

        return array_slice(array_values(array_unique($common_badges)), 0, 4);
    }
}

/**
 * 掲示板：新着バッジ（lightbulb SVG のみ・黄色・きらめき）
 *
 * @param string $label
 * @return string HTML
 */
if (!function_exists('aidunite_match_board_render_new_badge')) {
    function aidunite_match_board_render_new_badge($label = '新着') {
        if (!function_exists('aidunite_render_theme_icon')) {
            return '';
        }
        $icon = aidunite_render_theme_icon(
            'lightbulb',
            ['width' => '16', 'height' => '16'],
            'aidunite-icon--inline market-axis-badge--new-icon'
        );

        return sprintf(
            '<span class="market-axis-badge market-axis-badge--new" data-badge-type="new" aria-label="%s"><span class="market-axis-badge--new-icon-wrap" aria-hidden="true">%s</span></span>',
            esc_attr((string) $label),
            $icon // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        );
    }
}
