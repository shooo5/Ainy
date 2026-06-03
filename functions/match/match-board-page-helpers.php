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
        $base_date         = get_post_meta($schedule_id, 'schedule_date', true);
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

            $my_gender = get_post_meta($base_schedule_id, 'schedule_gender', true);
            if (!$my_gender) {
                $my_gender = get_post_meta($base_schedule_id, 'matching_gender_condition', true);
            }
            $my_place = get_post_meta($base_schedule_id, 'schedule_place', true);
            if (!$my_place) {
                $my_place = get_post_meta($base_schedule_id, 'schedule_place_option', true);
            }

            $other_gender = get_post_meta($candidate_post->ID, 'schedule_gender', true);
            if (!$other_gender) {
                $other_gender = get_post_meta($candidate_post->ID, 'matching_gender_condition', true);
            }
            $other_place = get_post_meta($candidate_post->ID, 'schedule_place', true);
            if (!$other_place) {
                $other_place = get_post_meta($candidate_post->ID, 'schedule_place_option', true);
            }

            $my_schedule = [
                'schedule_date' => get_post_meta($base_schedule_id, 'schedule_date', true),
                'start'         => get_post_meta($base_schedule_id, 'schedule_start_time', true),
                'end'           => get_post_meta($base_schedule_id, 'schedule_end_time', true),
                'gender'        => $normalize_gender($my_gender),
                'place'         => $my_place,
            ];

            $other_schedule = [
                'schedule_date' => get_post_meta($candidate_post->ID, 'schedule_date', true),
                'start'         => get_post_meta($candidate_post->ID, 'schedule_start_time', true),
                'end'           => get_post_meta($candidate_post->ID, 'schedule_end_time', true),
                'gender'        => $normalize_gender($other_gender),
                'place'         => $other_place,
            ];

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
        $my_date = get_post_meta($base_schedule_id, 'schedule_date', true);
        $my_start = get_post_meta($base_schedule_id, 'schedule_start_time', true);
        $my_end = get_post_meta($base_schedule_id, 'schedule_end_time', true);
        $my_gender = get_post_meta($base_schedule_id, 'schedule_gender', true);
        if (!$my_gender) {
            $my_gender = get_post_meta($base_schedule_id, 'matching_gender_condition', true);
        }
        $my_place = get_post_meta($base_schedule_id, 'schedule_place', true);
        if (!$my_place) {
            $my_place = get_post_meta($base_schedule_id, 'schedule_place_option', true);
        }

        $other_date = get_post_meta($candidate_schedule_id, 'schedule_date', true);
        $other_start = get_post_meta($candidate_schedule_id, 'schedule_start_time', true);
        $other_end = get_post_meta($candidate_schedule_id, 'schedule_end_time', true);
        $other_gender = get_post_meta($candidate_schedule_id, 'schedule_gender', true);
        if (!$other_gender) {
            $other_gender = get_post_meta($candidate_schedule_id, 'matching_gender_condition', true);
        }
        $other_place = get_post_meta($candidate_schedule_id, 'schedule_place', true);
        if (!$other_place) {
            $other_place = get_post_meta($candidate_schedule_id, 'schedule_place_option', true);
        }

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

if (!function_exists('aidunite_get_established_context_for_recruit_row')) {
    /**
     * 募集行に対して、閲覧チームが関係する established MR を1件返す。
     * other_team_id が空でも、my/to schedule の team_id から関係性を解決する。
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

        $posts = get_posts([
            'post_type'      => 'match_request',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [
                'relation' => 'AND',
                ['key' => 'status', 'value' => ['established', '試合確定'], 'compare' => 'IN'],
                [
                    'relation' => 'OR',
                    ['key' => 'to_schedule_id', 'value' => (string) $candidate_schedule_id, 'compare' => '='],
                    ['key' => 'my_schedule_id', 'value' => (string) $candidate_schedule_id, 'compare' => '='],
                    ['key' => 'from_schedule_id', 'value' => (string) $candidate_schedule_id, 'compare' => '='],
                ],
            ],
        ]);

        foreach ($posts as $req) {
            $rid = (int) $req->ID;
            if (function_exists('aidunite_normalize_match_request_status')) {
                $norm = aidunite_normalize_match_request_status(
                    (string) get_post_meta($rid, 'status', true),
                    (string) $req->post_status
                );
                if ($norm !== 'established') {
                    continue;
                }
            }

            if (function_exists('aidunite_match_request_get_link_schedule_teams')) {
                [$my_sid, $to_sid, $my_team, $to_team] = array_slice(
                    aidunite_match_request_get_link_schedule_teams($rid),
                    0,
                    4
                );
            } else {
                $my_sid = (int) get_post_meta($rid, 'my_schedule_id', true);
                if ($my_sid <= 0) {
                    $my_sid = (int) get_post_meta($rid, 'from_schedule_id', true);
                }
                $to_sid = (int) get_post_meta($rid, 'to_schedule_id', true);
                if ($to_sid === 9999) {
                    $to_sid = 0;
                }
                $my_team = ($my_sid > 0) ? (int) get_post_meta($my_sid, 'team_id', true) : 0;
                $to_team = ($to_sid > 0) ? (int) get_post_meta($to_sid, 'team_id', true) : 0;
            }

            if ($my_sid <= 0 || $to_sid <= 0 || $my_team <= 0 || $to_team <= 0) {
                continue;
            }
            if ($my_team !== $viewer_team_id && $to_team !== $viewer_team_id) {
                continue;
            }
            if ($my_sid !== $candidate_schedule_id && $to_sid !== $candidate_schedule_id) {
                continue;
            }

            return [
                'request_id'     => $rid,
                'my_schedule_id' => $my_sid,
                'to_schedule_id' => $to_sid,
            ];
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
                    $dbg_date = function_exists('_aidunite_match_apply_normalize_schedule_date')
                        ? _aidunite_match_apply_normalize_schedule_date(get_post_meta($dbg_sid, 'schedule_date', true))
                        : (string) get_post_meta($dbg_sid, 'schedule_date', true);
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
