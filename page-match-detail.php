<?php
require_once get_stylesheet_directory() . '/functions/team/team-display-template.php';
require_once get_stylesheet_directory() . '/functions/common/label-functions.php';
require_once get_stylesheet_directory() . '/functions.php';
require_once get_stylesheet_directory() . '/functions/match/match-request-functions.php';
require_once get_stylesheet_directory() . '/functions/match/match-invite-functions.php';
require_once get_stylesheet_directory() . '/functions/match/match-detail-opponent-helpers.php';

get_header();
aidunite_team_display_styles();

$current_user_id = get_current_user_id();
require_once get_stylesheet_directory() . '/functions/match/match-board-page-helpers.php';
$my_team_id = function_exists('aidunite_match_board_resolve_viewer_team_id')
    ? aidunite_match_board_resolve_viewer_team_id($current_user_id)
    : 0;
$other_schedule_id = isset($_GET['schedule_id']) ? intval($_GET['schedule_id']) : 0;
$my_schedule_id = isset($_GET['my_schedule_id']) ? intval($_GET['my_schedule_id']) : 0;
$is_guest_invite = false;
$request_id_param = isset($_GET['id']) ? absint($_GET['id']) : 0;
$is_other_only_mode = false;

// 通知などから ?id=request_id で開いた場合（招待承認のマッチ詳細）
if ($request_id_param > 0) {
    $req_post = get_post($request_id_param);
    if (!$req_post || $req_post->post_type !== 'match_request') {
        echo '<div class="alert alert-warning">' . aidunite_render_theme_icon('brightness_alert', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline') . ' 指定された申請が見つかりません。</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        get_footer();
        return;
    }
    $mr_my_schedule_id = (int) get_post_meta($req_post->ID, 'my_schedule_id', true);
    $to_schedule_id_meta = (int) get_post_meta($req_post->ID, 'to_schedule_id', true);
    $from_team_id_url = (int) get_post_meta($req_post->ID, 'from_team_id', true);
    if ($from_team_id_url === $my_team_id) {
        $my_schedule_id = $mr_my_schedule_id;
    } elseif (function_exists('aidunite_resolve_my_schedule_id_for_match_application')) {
        $my_schedule_id = aidunite_resolve_my_schedule_id_for_match_application(
            $my_team_id,
            $mr_my_schedule_id,
            $to_schedule_id_meta,
            (int) $req_post->ID
        );
    } else {
        $my_schedule_id = $mr_my_schedule_id;
    }
    $approver_type = get_post_meta($req_post->ID, 'approver_type', true);
    $is_guest_invite = ($to_schedule_id_meta === 9999 || $approver_type === 'guest_invite');

    if (!$my_team_id) {
        echo '<div class="alert alert-warning">' . aidunite_render_theme_icon('brightness_alert', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline') . ' ログイン中のチーム情報がありません。</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        get_footer();
        return;
    }
    $my_schedule = get_post($my_schedule_id);
    if (!$my_schedule || $my_schedule->post_type !== 'schedule') {
        echo '<div class="alert alert-warning">' . aidunite_render_theme_icon('brightness_alert', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline') . ' スケジュールが見つかりません。</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        get_footer();
        return;
    }
    $latest_request = $req_post;
    $my_team = get_post($my_team_id);

    if ($is_guest_invite) {
        // 招待承認時: 相手スケジュールなし、相手は「招待（ゲスト承認）」＋学校名・氏名
        $other_schedule_id = 0;
        $other_schedule = null;
        $other_team_id = get_post_meta($req_post->ID, 'to_team_id', true);
        $my_team_data = [
            'name' => get_post_meta($my_team_id, 'team_name', true) ?: 'チーム名未設定',
            'sport' => get_post_meta($my_team_id, 'team_sport', true) ?: 'スポーツ種目未設定',
            'category' => get_post_meta($my_team_id, 'team_category', true) ?: 'カテゴリ未設定',
            'region' => function_exists('aidunite_team_activity_display_label') ? aidunite_team_activity_display_label($my_team_id) : (get_post_meta($my_team_id, 'region', true) ?: '地域未設定'),
            'logo' => get_post_meta($my_team_id, 'team_logo', true) ?: get_template_directory_uri() . '/images/default-team-logo.png'
        ];
        $other_team_data = [
            'name' => '招待（ゲスト承認）',
            'approver_school_name' => get_post_meta($req_post->ID, 'approver_school_name', true) ?: '',
            'approver_name' => get_post_meta($req_post->ID, 'approver_name', true) ?: '',
            'sport' => '', 'category' => '', 'region' => '', 'logo' => '', 'description' => '', 'achievements' => ''
        ];
        $my_place = get_post_meta($my_schedule_id, 'schedule_place', true) ?: get_post_meta($my_schedule_id, 'schedule_place_option', true);
        $my_gender = get_post_meta($my_schedule_id, 'schedule_gender', true) ?: get_post_meta($my_schedule_id, 'matching_gender_condition', true);
        $my_schedule_data = [
            'date' => get_post_meta($my_schedule_id, 'schedule_date', true) ?: '',
            'start' => get_post_meta($my_schedule_id, 'schedule_start_time', true) ?: '',
            'end' => get_post_meta($my_schedule_id, 'schedule_end_time', true) ?: '',
            'place' => $my_place ?: 'either',
            'gender' => $my_gender ?: 'both'
        ];
        $other_schedule_data = $my_schedule_data;
        $other_place = $my_place;
        $other_gender = $my_gender;
        // 相手側の会場表示：自分がホーム→相手はアウェイ、自分がアウェイ→相手はホーム
        $venue_name = get_post_meta($my_schedule_id, 'venue_name', true);
        $other_place_disp_guest = function_exists('aidunite_match_invite_opponent_venue_label')
            ? aidunite_match_invite_opponent_venue_label($my_place, $venue_name)
            : (function_exists('jp_place') ? jp_place($other_place) : '');
        $request_status = get_post_meta($req_post->ID, 'status', true);
        $from_team_id_req = get_post_meta($req_post->ID, 'from_team_id', true);
        $is_applicant = ($from_team_id_req == $my_team_id);
        if ($request_status === 'established' || $request_status === '試合確定') {
            $application_status = 'established';
        } else {
            $application_status = 'accepted';
        }
        $is_received_request = false;
        $received_request_id = null;
    } else {
        // id で開いたが通常のマッチ申請: to_schedule_id から相手を読み、以降は通常フローに任せる
        $other_schedule_id = $to_schedule_id_meta;
        $other_schedule = $other_schedule_id ? get_post($other_schedule_id) : null;
        if (!$other_schedule || $other_schedule->post_type !== 'schedule') {
            echo '<div class="alert alert-warning">' . aidunite_render_theme_icon('brightness_alert', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline') . ' 相手スケジュールが見つかりません。</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            get_footer();
            return;
        }
        $other_team_id = get_post_meta($other_schedule_id, 'team_id', true);
        $my_team_data = [
            'name' => get_post_meta($my_team_id, 'team_name', true) ?: 'チーム名未設定',
            'sport' => get_post_meta($my_team_id, 'team_sport', true) ?: 'スポーツ種目未設定',
            'category' => get_post_meta($my_team_id, 'team_category', true) ?: 'カテゴリ未設定',
            'region' => function_exists('aidunite_team_activity_display_label') ? aidunite_team_activity_display_label($my_team_id) : (get_post_meta($my_team_id, 'region', true) ?: '地域未設定'),
            'logo' => get_post_meta($my_team_id, 'team_logo', true) ?: get_template_directory_uri() . '/images/default-team-logo.png'
        ];
        $other_team_data = [
            'name' => get_post_meta($other_team_id, 'team_name', true) ?: 'チーム名未設定',
            'sport' => get_post_meta($other_team_id, 'team_sport', true) ?: 'スポーツ種目未設定',
            'category' => get_post_meta($other_team_id, 'team_category', true) ?: 'カテゴリ未設定',
            'region' => function_exists('aidunite_team_activity_display_label') ? aidunite_team_activity_display_label($other_team_id) : (get_post_meta($other_team_id, 'region', true) ?: '地域未設定'),
            'logo' => get_post_meta($other_team_id, 'team_logo', true) ?: get_template_directory_uri() . '/images/default-team-logo.png',
            'description' => get_post_meta($other_team_id, 'team_description', true) ?: '',
            'achievements' => get_post_meta($other_team_id, 'team_achievements', true) ?: '',
            'approver_school_name' => '',
            'approver_name' => ''
        ];
        // 以降の Phase 2 と get_latest_match_request_bidirectional で上書きされるのでここでは不要
    }
} elseif ($other_schedule_id > 0 && $my_schedule_id == 0) {
    // それ以外：相手のみ表示モード（掲示板から schedule_id のみで開いた場合）
    $is_other_only_mode = true;
    if (!$my_team_id) {
        echo '<div class="alert alert-warning">' . aidunite_render_theme_icon('brightness_alert', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline') . ' ログイン中のチーム情報がありません。</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        get_footer();
        return;
    }
    $other_schedule = get_post($other_schedule_id);
    if (!$other_schedule || $other_schedule->post_type !== 'schedule') {
        echo '<div class="alert alert-warning">' . aidunite_render_theme_icon('brightness_alert', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline') . ' 指定された募集が見つかりません。</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        get_footer();
        return;
    }
    $other_team_id = get_post_meta($other_schedule_id, 'team_id', true);
    $other_team_data = [
        'name' => $other_team_id ? (function_exists('aidunite_get_team_name') ? aidunite_get_team_name($other_team_id) : (get_the_title($other_team_id) ?: 'チーム名未設定')) : 'チーム名未設定',
        'sport' => $other_team_id ? (get_post_meta($other_team_id, 'team_sport', true) ?: '') : '',
        'category' => $other_team_id ? (get_post_meta($other_team_id, 'team_category', true) ?: '') : '',
        'region' => $other_team_id && function_exists('aidunite_team_activity_display_label') ? aidunite_team_activity_display_label($other_team_id) : ($other_team_id ? (get_post_meta($other_team_id, 'region', true) ?: '') : ''),
        'logo' => $other_team_id ? (get_post_meta($other_team_id, 'team_logo', true) ?: get_template_directory_uri() . '/images/default-team-logo.png') : '',
        'description' => $other_team_id ? (get_post_meta($other_team_id, 'team_description', true) ?: '') : '',
        'achievements' => $other_team_id ? (get_post_meta($other_team_id, 'team_achievements', true) ?: '') : '',
        'approver_school_name' => '',
        'approver_name' => ''
    ];
    $other_place = get_post_meta($other_schedule_id, 'schedule_place', true) ?: get_post_meta($other_schedule_id, 'schedule_place_option', true);
    $other_gender = get_post_meta($other_schedule_id, 'schedule_gender', true) ?: get_post_meta($other_schedule_id, 'matching_gender_condition', true);
    $other_schedule_data = [
        'date' => get_post_meta($other_schedule_id, 'schedule_date', true) ?: '',
        'start' => get_post_meta($other_schedule_id, 'schedule_start_time', true) ?: '',
        'end' => get_post_meta($other_schedule_id, 'schedule_end_time', true) ?: '',
        'place' => $other_place ?: 'either',
        'gender' => $other_gender ?: 'both',
        'venue_name' => get_post_meta($other_schedule_id, 'venue_name', true) ?: ''
    ];
    $my_team = get_post($my_team_id);
    $my_schedule = null;
    $my_schedule_data = ['date' => '', 'start' => '', 'end' => '', 'place' => 'either', 'gender' => 'both'];
    $my_team_data = [
        'name' => get_post_meta($my_team_id, 'team_name', true) ?: 'チーム名未設定',
        'sport' => get_post_meta($my_team_id, 'team_sport', true) ?: '',
        'category' => get_post_meta($my_team_id, 'team_category', true) ?: '',
        'region' => function_exists('aidunite_team_activity_display_label') ? aidunite_team_activity_display_label($my_team_id) : (get_post_meta($my_team_id, 'region', true) ?: ''),
        'logo' => get_post_meta($my_team_id, 'team_logo', true) ?: get_template_directory_uri() . '/images/default-team-logo.png',
    ];
} else {
    // 従来: my_schedule_id, schedule_id (other_schedule_id), my_team_id 必須
    $is_other_only_mode = false;
    if (!$my_team_id || !$other_schedule_id || !$my_schedule_id) {
        echo '<div class="alert alert-warning">' . aidunite_render_theme_icon('brightness_alert', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline') . ' 必要なパラメータが不足しています。</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        get_footer();
        return;
    }

    $my_team = get_post($my_team_id);
    $other_schedule = get_post($other_schedule_id);
    $my_schedule = get_post($my_schedule_id);

    if (!$my_team || !$other_schedule || !$my_schedule) {
        echo '<div class="alert alert-warning">' . aidunite_render_theme_icon('brightness_alert', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline') . ' 指定されたデータが見つかりません。</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        get_footer();
        return;
    }

    $my_team_data = [
        'name' => get_post_meta($my_team_id, 'team_name', true) ?: 'チーム名未設定',
        'sport' => get_post_meta($my_team_id, 'team_sport', true) ?: 'スポーツ種目未設定',
        'category' => get_post_meta($my_team_id, 'team_category', true) ?: 'カテゴリ未設定',
        'region' => function_exists('aidunite_team_activity_display_label') ? aidunite_team_activity_display_label($my_team_id) : (get_post_meta($my_team_id, 'region', true) ?: '地域未設定'),
        'logo' => get_post_meta($my_team_id, 'team_logo', true) ?: get_template_directory_uri() . '/images/default-team-logo.png'
    ];

    $other_team_id = get_post_meta($other_schedule_id, 'team_id', true);
    $other_team_data = [
        'name' => get_post_meta($other_team_id, 'team_name', true) ?: 'チーム名未設定',
        'sport' => get_post_meta($other_team_id, 'team_sport', true) ?: 'スポーツ種目未設定',
        'category' => get_post_meta($other_team_id, 'team_category', true) ?: 'カテゴリ未設定',
        'region' => function_exists('aidunite_team_activity_display_label') ? aidunite_team_activity_display_label($other_team_id) : (get_post_meta($other_team_id, 'region', true) ?: '地域未設定'),
        'logo' => get_post_meta($other_team_id, 'team_logo', true) ?: get_template_directory_uri() . '/images/default-team-logo.png',
        'description' => get_post_meta($other_team_id, 'team_description', true) ?: '',
        'achievements' => get_post_meta($other_team_id, 'team_achievements', true) ?: '',
        'approver_school_name' => '',
        'approver_name' => ''
    ];
}

$current_user_team_id = function_exists('aidunite_match_board_resolve_viewer_team_id')
    ? aidunite_match_board_resolve_viewer_team_id(get_current_user_id())
    : 0;

// 相手のみモード：自チーム→相手スケジュールの申請を1件取得（my_schedule_id は問わない）
if (!empty($is_other_only_mode)) {
    $application_status = '';
    $latest_request = null;
    $is_received_request = false;
    $received_request_id = null;
    $reqs = get_posts([
        'post_type' => 'match_request',
        'post_status' => 'any',
        'posts_per_page' => 1,
        'orderby' => 'date',
        'order' => 'DESC',
        'meta_query' => [
            ['key' => 'from_team_id', 'value' => $current_user_team_id],
            ['key' => 'to_schedule_id', 'value' => $other_schedule_id]
        ]
    ]);
    if (!empty($reqs)) {
        $latest_request = $reqs[0];
        $request_status = get_post_meta($latest_request->ID, 'status', true);
        if (in_array($request_status, ['established', '試合確定'])) {
            $application_status = 'established';
        } elseif (in_array($request_status, ['accepted', '承認済み'])) {
            $application_status = 'accepted';
        } elseif (in_array($request_status, ['publish', 'pending', '申請中'], true)) {
            $application_status = 'applying';
        } elseif (in_array($request_status, ['rejected', '拒否'])) {
            $application_status = 'rejected';
        } elseif (in_array($request_status, ['canceled', 'キャンセル'])) {
            $application_status = 'canceled';
        } else {
            $application_status = '';
        }
    }
    // 相手のみモードは from_team_id で申請を絞っており、常に申請者視点
    $is_applicant = true;
}

// 招待でない場合のみ Phase 2 と双方向取得（招待の場合は上で変数を設定済み）。相手のみモードではスキップ。
if (!$is_guest_invite && !$is_other_only_mode) {
// Phase 2: 統一メタキーを優先、後方互換性のために旧キーもフォールバック
$resolve_registered_snapshot = static function ($sid) {
    $sid = (int) $sid;
    $saved = ((string) get_post_meta($sid, 'pre_established_saved', true) === '1');
    $start = get_post_meta($sid, $saved ? 'pre_established_start_time' : 'schedule_start_time', true);
    $end = get_post_meta($sid, $saved ? 'pre_established_end_time' : 'schedule_end_time', true);
    $place = get_post_meta($sid, $saved ? 'pre_established_place' : 'schedule_place', true);
    if (!$place) {
        $place = get_post_meta($sid, $saved ? 'pre_established_place_option' : 'schedule_place_option', true);
    }
    $gender = get_post_meta($sid, $saved ? 'pre_established_gender' : 'schedule_gender', true);
    if (!$gender) {
        $gender = get_post_meta($sid, 'matching_gender_condition', true);
    }
    return [
        'start' => $start ?: '',
        'end' => $end ?: '',
        'place' => $place ?: '',
        'gender' => $gender ?: '',
    ];
};
$my_registered = $resolve_registered_snapshot($my_schedule_id);
$other_registered = $resolve_registered_snapshot($other_schedule_id);

$my_gender = $my_registered['gender'];
$my_place = $my_registered['place'];
$other_gender = $other_registered['gender'];
$other_place = $other_registered['place'];

$my_schedule_data = [
    'date' => get_post_meta($my_schedule_id, 'schedule_date', true) ?: '',
    'start' => $my_registered['start'],
    'end' => $my_registered['end'],
    'place' => $my_place ?: 'either',
    'gender' => $my_gender ?: 'both'
];

$other_schedule_data = [
    'date' => get_post_meta($other_schedule_id, 'schedule_date', true) ?: '',
    'start' => $other_registered['start'],
    'end' => $other_registered['end'],
    'place' => $other_place ?: 'either',
    'gender' => $other_gender ?: 'both',
    'venue_name' => get_post_meta($other_schedule_id, 'venue_name', true) ?: ''
];

// 申請状態の確認（match_requestテーブルから最新の状態を確認）
$application_status = '';
$is_received_request = false;
$received_request_id = null;
$latest_request = null;
$requires_reconfirm = false;
$proposal_pending_accept = false;
$reconfirm_reason = '';
$reconfirm_diff = ['messages' => [], 'rows' => []];

// 双方向で最新の申請を取得（掲示板などから match_request_id 指定時はその MR を優先し、相互申請で逆方向を拾わない）
$other_team_id = get_post_meta($other_schedule_id, 'team_id', true);
$match_request_id_param = isset($_GET['match_request_id']) ? absint($_GET['match_request_id']) : 0;
if ($match_request_id_param > 0) {
    $mr_pick = get_post($match_request_id_param);
    if ($mr_pick && $mr_pick->post_type === 'match_request') {
        $mr_my = (int) get_post_meta($mr_pick->ID, 'my_schedule_id', true);
        if ($mr_my <= 0) {
            $mr_my = (int) get_post_meta($mr_pick->ID, 'from_schedule_id', true);
        }
        $mr_to = (int) get_post_meta($mr_pick->ID, 'to_schedule_id', true);
        $pair_ok = ($mr_my > 0 && $mr_to > 0
            && (
                ($mr_my === (int) $my_schedule_id && $mr_to === (int) $other_schedule_id)
                || ($mr_my === (int) $other_schedule_id && $mr_to === (int) $my_schedule_id)
            ));
        $from_tid = (int) get_post_meta($mr_pick->ID, 'from_team_id', true);
        $host_team = $mr_to > 0 ? (int) get_post_meta($mr_to, 'team_id', true) : 0;
        $viewer_team = (int) $current_user_team_id;
        $viewer_ok = ($from_tid === $viewer_team) || ($host_team === $viewer_team);
        if ($viewer_ok && ($pair_ok || $match_request_id_param > 0)) {
            $latest_request = $mr_pick;
            if (!$pair_ok && $mr_to > 0 && (int) get_post_meta($mr_to, 'team_id', true) === $viewer_team) {
                $my_schedule_id = $mr_to;
                $other_schedule_id = $mr_my > 0 ? $mr_my : $other_schedule_id;
            } elseif (!$pair_ok && $mr_my > 0 && (int) get_post_meta($mr_my, 'team_id', true) === $viewer_team) {
                $my_schedule_id = $mr_my;
                $other_schedule_id = $mr_to > 0 ? $mr_to : $other_schedule_id;
            }
        }
    }
}
if (!$latest_request) {
    $latest_request = get_latest_match_request_bidirectional($current_user_team_id, $my_schedule_id, $other_team_id, $other_schedule_id);
}

if ($latest_request) {
    $request_status = get_post_meta($latest_request->ID, 'status', true);
    $requires_reconfirm = ((int) get_post_meta($latest_request->ID, 'requires_reconfirm', true) === 1);
    $proposal_pending_accept = ((int) get_post_meta($latest_request->ID, 'proposal_pending_accept', true) === 1);
    $reconfirm_reason = (string) get_post_meta($latest_request->ID, 'reconfirm_reason', true);
    $from_team_id = get_post_meta($latest_request->ID, 'from_team_id', true);
    $to_team_id = get_post_meta($latest_request->ID, 'to_team_id', true);
    if ($to_team_id === '' || $to_team_id === null) {
        $to_team_id = get_post_meta($latest_request->ID, 'other_team_id', true);
    }
    $my_schedule_id_meta = get_post_meta($latest_request->ID, 'my_schedule_id', true);
    $to_schedule_id_meta = get_post_meta($latest_request->ID, 'to_schedule_id', true);


    // 申請者側か受信者側かを判定（より正確な判定）
    $is_applicant = false;

    // まず、from_team_idとto_team_idで判定を試行
    if ($from_team_id == $current_user_team_id) {
        $is_applicant = true;
    } elseif ($to_team_id == $current_user_team_id) {
        $is_applicant = false;
    } else {
        // フォールバック：スケジュールIDで判定
        if ($my_schedule_id_meta == $my_schedule_id) {
            // my_schedule_idが一致する場合、このユーザーが申請者
            $is_applicant = true;
        } elseif ($to_schedule_id_meta == $my_schedule_id) {
            // to_schedule_idが一致する場合、このユーザーが受信者
            $is_applicant = false;
        } else {
            // さらにフォールバック：ページのスケジュールIDと比較
            if ((int) $my_schedule_id_meta === (int) $my_schedule_id) {
                // ページのmy_schedule_idが一致する場合、このユーザーが申請者
                $is_applicant = true;
            } elseif ((int) $to_schedule_id_meta === (int) $my_schedule_id) {
                // ページのother_schedule_idが一致する場合、このユーザーが受信者
                $is_applicant = false;
            } else {
                $is_applicant = false;
            }
        }
    }

    if ($requires_reconfirm && function_exists('aidunite_get_reconfirm_diff')) {
        $reconfirm_diff = aidunite_get_reconfirm_diff((int) $latest_request->ID, 0, [
            'for_applicant' => !empty($is_applicant),
        ]);
    }

    if ($proposal_pending_accept) {
        $application_status = 'proposal_pending_accept';
        $is_received_request = !$is_applicant;
        $received_request_id = $latest_request->ID;
    } elseif ($requires_reconfirm) {
        $application_status = 'reconfirm_required';
        $is_received_request = !$is_applicant;
        $received_request_id = $latest_request->ID;
    } elseif ($is_applicant) {
        // 申請者側
        switch ($request_status) {
            case 'draft':
                $application_status = 'applying';
                break;
            case 'publish':
            case 'pending':
            case '申請中':
                $application_status = 'applying';
                break;
            case 'accepted':
            case '承認済み':
                $application_status = 'accepted';
                break;
            case 'established':
            case '試合確定':
                $application_status = 'established';
                break;
            case 'rejected':
            case '拒否':
                $application_status = 'rejected';
                break;
            case 'canceled':
            case 'キャンセル':
                $application_status = 'canceled';
                break;
            default:
                $application_status = 'unknown';
        }
    } else {
        // 受信者側
        switch ($request_status) {
            case 'draft':
                $application_status = 'received';
                $is_received_request = true;
                $received_request_id = $latest_request->ID;
                break;
            case 'publish':
            case 'pending':
            case '申請中':
                $application_status = 'received';
                $is_received_request = true;
                $received_request_id = $latest_request->ID;
                break;
            case 'accepted':
            case '承認済み':
                $application_status = 'accepted';
                break;
            case 'established':
            case '試合確定':
                $application_status = 'established';
                break;
            case 'rejected':
            case '拒否':
                $application_status = 'rejected';
                break;
            case 'canceled':
            case 'キャンセル':
                $application_status = 'canceled';
                break;
            default:
                $application_status = 'unknown';
        }
    }

    if (function_exists('aidunite_match_flow_debug_log')) {
        aidunite_match_flow_debug_log('match_detail_page', [
            'request_id'          => (int) $latest_request->ID,
            'raw_status'          => (string) $request_status,
            'from_team_id'        => $from_team_id,
            'to_team_id'          => $to_team_id,
            'current_user_team'   => $current_user_team_id,
            'my_schedule_id'      => (int) $my_schedule_id,
            'other_schedule_id'   => (int) $other_schedule_id,
            'my_schedule_id_meta' => $my_schedule_id_meta,
            'to_schedule_id_meta' => $to_schedule_id_meta,
            'is_applicant'        => $is_applicant,
            'application_status'  => $application_status,
        ]);
    }
} else {
    // まだ申請がない閲覧＝申請側として扱う
    $is_applicant = true;
}
} // endif !$is_guest_invite

// 申請で確定した内容を取得（承認済み・試合確定の場合）
$confirmed_data = null;
$applied_data = null;
if (!$is_other_only_mode) {
if (($application_status === 'accepted' || $application_status === 'established') && $latest_request) {
    $confirmed_data = [
        'start_time' => get_post_meta($latest_request->ID, 'selected_start_time', true) ?: $my_schedule_data['start'],
        'end_time' => get_post_meta($latest_request->ID, 'selected_end_time', true) ?: $my_schedule_data['end'],
        'place' => get_post_meta($latest_request->ID, 'selected_place', true) ?: $my_schedule_data['place'],
        'gender' => get_post_meta($latest_request->ID, 'selected_gender', true) ?: $my_schedule_data['gender']
    ];
}

// 申請中の内容を取得（申請受けた側で表示用）
if (($application_status === 'received' || $application_status === 'applying') && $latest_request) {
    $applied_data = [
        'start_time' => get_post_meta($latest_request->ID, 'selected_start_time', true) ?: $my_schedule_data['start'],
        'end_time' => get_post_meta($latest_request->ID, 'selected_end_time', true) ?: $my_schedule_data['end'],
        'place' => get_post_meta($latest_request->ID, 'selected_place', true) ?: $my_schedule_data['place'],
        'gender' => get_post_meta($latest_request->ID, 'selected_gender', true) ?: $my_schedule_data['gender']
    ];
}
if ($application_status === 'reconfirm_required' && $latest_request) {
    $applied_data = [
        'start_time' => get_post_meta($latest_request->ID, 'selected_start_time', true) ?: $my_schedule_data['start'],
        'end_time' => get_post_meta($latest_request->ID, 'selected_end_time', true) ?: $my_schedule_data['end'],
        'place' => get_post_meta($latest_request->ID, 'selected_place', true) ?: $my_schedule_data['place'],
        'gender' => get_post_meta($latest_request->ID, 'selected_gender', true) ?: $my_schedule_data['gender']
    ];
}
}
$established_chat_url = '';
if (in_array((string) $application_status, ['accepted', 'established'], true) && $latest_request) {
    $chat_room_obj = function_exists('aidunite_get_game_chat_room_for_match_request')
        ? aidunite_get_game_chat_room_for_match_request((int) $latest_request->ID)
        : null;
    if ($chat_room_obj && !empty($chat_room_obj->id) && (string) ($chat_room_obj->status ?? '') === 'active') {
        $established_chat_url = home_url('/chat?room_id=' . (int) $chat_room_obj->id);
    } elseif (function_exists('aidunite_match_request_should_fork_new_chat_room')
        && aidunite_match_request_should_fork_new_chat_room((int) $latest_request->ID)) {
        // active が無い再承認サイクルは match_id 経由で新規ルーム作成へ
        $established_chat_url = home_url('/match-chat/?match_id=' . (int) $latest_request->ID);
    }
}

$reapply_cta = ['variant' => 'none', 'label' => '再申請する', 'note' => ''];
if (
    !$is_guest_invite
    && !empty($is_applicant)
    && $is_applicant
    && in_array($application_status, ['rejected', 'canceled', 'reconfirm_required'], true)
    && $other_schedule_id
    && function_exists('aidunite_match_detail_reapply_cta')
) {
    $my_sid_for_cta = (int) $my_schedule_id;
    if (!empty($is_other_only_mode) && $my_sid_for_cta <= 0) {
        $oo_date = get_post_meta($other_schedule_id, 'schedule_date', true);
        if ($oo_date && $current_user_team_id) {
            $same_day_scheds = get_posts([
                'post_type' => 'schedule',
                'post_status' => 'publish',
                'posts_per_page' => 1,
                'orderby' => 'ID',
                'order' => 'ASC',
                'meta_query' => [
                    'relation' => 'AND',
                    ['key' => 'team_id', 'value' => (int) $current_user_team_id, 'compare' => '='],
                    ['key' => 'schedule_date', 'value' => $oo_date, 'compare' => '='],
                ],
            ]);
            if (!empty($same_day_scheds[0])) {
                $my_sid_for_cta = (int) $same_day_scheds[0]->ID;
            }
        }
    }
    if ($my_sid_for_cta > 0) {
        $reapply_cta = aidunite_match_detail_reapply_cta((int) $other_schedule_id, $latest_request ? (int) $latest_request->ID : 0, $my_sid_for_cta);
    }
}

$match_apply_evaluation = null;
if (
    !$is_other_only_mode
    && !empty($my_schedule_id)
    && !empty($other_schedule_id)
    && function_exists('aidunite_evaluate_match_apply_context')
    && (empty($application_status) || !in_array($application_status, ['accepted', 'established'], true))
) {
    $match_apply_evaluation = aidunite_evaluate_match_apply_context(
        (int) $other_schedule_id,
        (int) $my_schedule_id,
        ['mode' => 'preview']
    );
}

if ($is_other_only_mode) {
    $match_score_label = '募集中';
} elseif ($is_guest_invite) {
    $match_score_label = '⭐ ベストマッチ';
} elseif (is_array($match_apply_evaluation) && function_exists('aidunite_match_board_tier_label_from_evaluation')) {
    $match_score_label = aidunite_match_board_tier_label_from_evaluation($match_apply_evaluation);
} else {
    $match_score_label = '条件不一致';
}

function get_match_level_color($label) {
    if (strpos($label, 'ベスト') !== false || strpos($label, '⭐') !== false) {
        return 'best-match';
    }
    if (strpos($label, '成立可能') !== false || strpos($label, '🟢') !== false) {
        return 'high-match';
    }
    if (strpos($label, '条件調整') !== false || strpos($label, '🟡') !== false) {
        return 'medium-match';
    }
    if (strpos($label, '希望未登録') !== false || strpos($label, '🔵') !== false) {
        return 'no-match';
    }

    return 'no-match';
}

$needs_time_adjustment = false;
$needs_place_adjustment = false;
$needs_gender_adjustment = false;
$time_result_for_detail = null;
$place_options = [];
$gender_options = [];
$has_adjustments = false;
$my_place = 'either';
$other_place = $other_schedule_data['place'] ?? 'either';
$other_gender = $other_schedule_data['gender'] ?? 'both';
$resolved_place_value = 'either';
$resolved_gender_value = $other_gender ?: 'both';

if (!$is_other_only_mode) {
// 時間判定：マッチカードと同じ aidunite_get_time_level を使用して整合性を取る
if ($my_schedule_data['start'] && $my_schedule_data['end'] && $other_schedule_data['start'] && $other_schedule_data['end']
    && function_exists('aidunite_get_time_level')) {
    $time_result_for_detail = aidunite_get_time_level(
        $my_schedule_data['start'],
        $my_schedule_data['end'],
        $other_schedule_data['start'],
        $other_schedule_data['end']
    );
}
$needs_time_adjustment = ($time_result_for_detail === null || (isset($time_result_for_detail['level']) && $time_result_for_detail['level'] !== 'TIME_STRONG'));

// 時間調整が必要な場合の選択可能範囲（重複部分）を計算（ワイヤー：🟡では表示のみで申請に使用）
$time_range_start_str = '';
$time_range_end_str = '';
// 重なり時間の算出は date の有無に依存しないように、時刻のみでもフォールバック計算する
$compute_overlap_time = function($start_a, $end_a, $start_b, $end_b) {
    if (empty($start_a) || empty($end_a) || empty($start_b) || empty($end_b)) {
        return ['', ''];
    }
    $a_start = strtotime('1970-01-01 ' . $start_a);
    $a_end = strtotime('1970-01-01 ' . $end_a);
    $b_start = strtotime('1970-01-01 ' . $start_b);
    $b_end = strtotime('1970-01-01 ' . $end_b);
    if (!$a_start || !$a_end || !$b_start || !$b_end) {
        return ['', ''];
    }
    $start = max($a_start, $b_start);
    $end = min($a_end, $b_end);
    if ($start >= $end) {
        return ['', ''];
    }
    return [date('H:i', $start), date('H:i', $end)];
};
if ($needs_time_adjustment && $my_schedule_data['start'] && $my_schedule_data['end'] && $other_schedule_data['start'] && $other_schedule_data['end']) {
    $my_start_ts = strtotime($my_schedule_data['date'] . ' ' . $my_schedule_data['start']);
    $my_end_ts = strtotime($my_schedule_data['date'] . ' ' . $my_schedule_data['end']);
    $other_start_ts = strtotime($other_schedule_data['date'] . ' ' . $other_schedule_data['start']);
    $other_end_ts = strtotime($other_schedule_data['date'] . ' ' . $other_schedule_data['end']);
    if ($my_start_ts && $my_end_ts && $other_start_ts && $other_end_ts) {
        $overlap = min($my_end_ts, $other_end_ts) - max($my_start_ts, $other_start_ts);
        if ($overlap > 0) {
            $time_range_start = max($my_start_ts, $other_start_ts);
            $time_range_end = min($my_end_ts, $other_end_ts);
            $time_range_start_str = date('H:i', $time_range_start);
            $time_range_end_str = date('H:i', $time_range_end);
        }
    }
}
if ($time_range_start_str === '' || $time_range_end_str === '') {
    list($fallback_overlap_start, $fallback_overlap_end) = $compute_overlap_time(
        $my_schedule_data['start'] ?? '',
        $my_schedule_data['end'] ?? '',
        $other_schedule_data['start'] ?? '',
        $other_schedule_data['end'] ?? ''
    );
    if (!empty($fallback_overlap_start) && !empty($fallback_overlap_end)) {
        $time_range_start_str = $fallback_overlap_start;
        $time_range_end_str = $fallback_overlap_end;
    }
}
if ($time_range_start_str === '' && !empty($my_schedule_data['start'])) {
    $time_range_start_str = $my_schedule_data['start'];
    $time_range_end_str = $my_schedule_data['end'] ?? '';
}

$my_place = $my_schedule_data['place'];
$other_place = $other_schedule_data['place'];
// 会場の内部値は either/both を同義として扱う
if ($my_place === 'both') {
    $my_place = 'either';
}
if ($other_place === 'both') {
    $other_place = 'either';
}

// 会場確定ロジック（9パターン仕様）：双方 either のときのみ選択UI
$place_options = [];
if ($my_place === 'either' && $other_place === 'either') {
    $needs_place_adjustment = true;
    $place_options = ['home', 'away'];
} else {
    $needs_place_adjustment = false;
}

$my_gender = $my_schedule_data['gender'];
$other_gender = $other_schedule_data['gender'];

if ($my_gender === 'both' && $other_gender === 'both') {
    $needs_gender_adjustment = true;
    $gender_options = ['male', 'female', 'both'];
} elseif ($my_gender === $other_gender) {
    $needs_gender_adjustment = false;
} elseif (($my_gender === 'male' && $other_gender === 'female') || ($my_gender === 'female' && $other_gender === 'male')) {
    $needs_gender_adjustment = false;
} else {
    $needs_gender_adjustment = true;
}

if ($needs_gender_adjustment) {
    if ($my_gender === 'male' && $other_gender === 'both') {
        $gender_options = ['male', 'both'];
    } elseif ($my_gender === 'female' && $other_gender === 'both') {
        $gender_options = ['female', 'both'];
    } elseif ($my_gender === 'both' && $other_gender === 'male') {
        $gender_options = ['male', 'both'];
    } elseif ($my_gender === 'both' && $other_gender === 'female') {
        $gender_options = ['female', 'both'];
    }
    if (empty($gender_options)) {
        $gender_options = [$my_gender];
        $needs_gender_adjustment = false;
    }
} else {
    $gender_options = [$my_gender];
}

if ($needs_gender_adjustment && isset($gender_options) && count($gender_options) === 1) {
    $needs_gender_adjustment = false;
}

// 申請時に送る性別（自チーム側の確定値）
$resolved_gender_value = $my_gender;
if ($needs_gender_adjustment && !empty($gender_options)) {
    if ($my_gender === 'both' && $other_gender === 'both') {
        $resolved_gender_value = 'both';
    } elseif (in_array($my_gender, ['male', 'female'], true) && $other_gender === 'both') {
        $resolved_gender_value = $my_gender;
    } elseif ($my_gender === 'both' && $other_gender === 'male') {
        $resolved_gender_value = 'male';
    } elseif ($my_gender === 'both' && $other_gender === 'female') {
        $resolved_gender_value = 'female';
    } else {
        $resolved_gender_value = $gender_options[0];
    }
} elseif (!$needs_gender_adjustment && !empty($gender_options)) {
    $resolved_gender_value = $gender_options[0];
}

// 会場確定（9パターン仕様）
$resolved_place_value = $my_place;
if ($needs_place_adjustment) {
    // either/either はピルで選択。初期は home
    $resolved_place_value = !empty($place_options[0]) ? $place_options[0] : 'home';
} elseif ($my_place === 'home' && $other_place === 'home') {
    $resolved_place_value = 'away';
} elseif ($my_place === 'away' && $other_place === 'away') {
    $resolved_place_value = 'home';
} elseif ($my_place === 'either' && $other_place === 'home') {
    $resolved_place_value = 'away';
} elseif ($my_place === 'either' && $other_place === 'away') {
    $resolved_place_value = 'home';
} elseif (in_array($my_place, ['home', 'away'], true) && $other_place === 'either') {
    $resolved_place_value = $my_place;
} elseif (in_array($my_place, ['home', 'away'], true) && in_array($other_place, ['home', 'away'], true) && $my_place !== $other_place) {
    // home/away or away/home は調整不要
    $resolved_place_value = $my_place;
}

// 双方 either の会場ピル選択は 🟢 表示のまま（時間・性別の調整のみ黄へ）
$needs_place_selection_ui = ($my_place === 'either' && $other_place === 'either');
$has_adjustments = $needs_time_adjustment || $needs_gender_adjustment;

// 表示モード: green / yellow / no_preference / established（mismatch は想定不要）
$match_detail_display_mode = 'green';
if (!empty($application_status) && in_array($application_status, ['accepted', 'established'])) {
    $match_detail_display_mode = 'established';
} elseif (!$is_other_only_mode) {
    if ($time_result_for_detail === null && !empty($my_schedule_data['start']) && !empty($other_schedule_data['start'])) {
        $match_detail_display_mode = 'no_preference';
    } elseif ($has_adjustments) {
        $match_detail_display_mode = 'yellow';
    }
}

if (is_array($match_apply_evaluation)) {
    $board_tier = (string) ($match_apply_evaluation['board_tier'] ?? '');
    if ($board_tier === 'best' || $board_tier === 'green') {
        $match_detail_display_mode = 'green';
    } elseif ($board_tier === 'no_preference'
        || ($match_apply_evaluation['list_row'] ?? '') === 'no_preference'
        || ($match_apply_evaluation['reason_code'] ?? '') === 'time_no_overlap') {
        $match_detail_display_mode = 'no_preference';
    } elseif ($board_tier === 'yellow'
        || ($match_apply_evaluation['variant'] ?? '') === 'proposal'
        || ($match_apply_evaluation['list_row'] ?? '') === 'yellow') {
        $match_detail_display_mode = 'yellow';
    } elseif (($match_apply_evaluation['list_row'] ?? '') === 'green' && !empty($match_apply_evaluation['default_selected_place'])) {
        $def_pl = (string) $match_apply_evaluation['default_selected_place'];
        if (in_array($def_pl, ['home', 'away'], true)) {
            $resolved_place_value = $def_pl;
        }
    }
}

}

function jp_place($place) {
    switch ($place) {
        case 'home': return 'ホーム';
        case 'away': return 'アウェイ';
        case 'either': return 'どちらでも可';
        case 'both': return 'どちらでも可';
        default: return $place ?: '-';
    }
}

function jp_gender($gender) {
    switch ($gender) {
        case 'male': return '男子';
        case 'female': return '女子';
        case 'both': return '男子・女子可';
        default: return $gender ?: '-';
    }
}

$display_date = '';
$display_time = '';
if ($is_other_only_mode) {
    if (!empty($other_schedule_data['date'])) {
        $date_obj = DateTime::createFromFormat('Y-m-d', $other_schedule_data['date']);
        if ($date_obj) {
            $weekdays = ['日', '月', '火', '水', '木', '金', '土'];
            $display_date = $date_obj->format('n/j') . '（' . $weekdays[$date_obj->format('w')] . '）';
        }
    }
    $display_time = (!empty($other_schedule_data['start']) && !empty($other_schedule_data['end'])) ? ($other_schedule_data['start'] . '〜' . $other_schedule_data['end']) : '';
} else {
    if ($my_schedule_data['date']) {
        $date_obj = DateTime::createFromFormat('Y-m-d', $my_schedule_data['date']);
        if ($date_obj) {
            $weekdays = ['日', '月', '火', '水', '木', '金', '土'];
            $display_date = $date_obj->format('n/j') . '（' . $weekdays[$date_obj->format('w')] . '）';
        }
    }
    if ($my_schedule_data['start'] && $my_schedule_data['end']) {
        $display_time = $my_schedule_data['start'] . '〜' . $my_schedule_data['end'];
    }
}

?>

<div id="fullScreenLoader" class="fullscreen-loader" aria-hidden="true" style="display: none;">
  <div class="spinner-container">
    <div class="spinner"></div>
    <div class="spinner-text" id="loaderText">申請中...</div>
  </div>
</div>

<!-- 完了メッセージ -->
<div id="completion-message" class="completion-message" style="display: none;">
  <div class="completion-card">
    <div class="completion-icon"><?php echo aidunite_render_theme_icon('check_circle', ['width' => '48', 'height' => '48'], 'aidunite-icon--success'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
    <div class="completion-title">申請が完了しました</div>
  </div>
</div>
<div id="chat-redirect-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:10010; align-items:center; justify-content:center; padding:16px;" aria-hidden="true">
  <div role="dialog" aria-modal="true" aria-labelledby="chat-redirect-modal-title" style="width:min(520px,100%); background:var(--bg-primary); border:1px solid var(--border-light); border-radius:var(--radius-large); box-shadow:var(--shadow-xl); padding:var(--spacing-lg); text-align:center;">
    <div style="margin-bottom:var(--spacing-sm);"><?php echo aidunite_render_theme_icon('check_circle', ['width' => '36', 'height' => '36'], 'aidunite-icon--success'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
    <h3 id="chat-redirect-modal-title" style="margin:0 0 var(--spacing-sm) 0; color:var(--text-primary);">試合成立おめでとうございます！</h3>
    <p style="margin:0 0 var(--spacing-lg) 0; color:var(--text-secondary);">専用のチャットページが作成されました。今すぐ移動しますか？</p>
    <div style="display:flex; gap:var(--spacing-sm); justify-content:center;">
      <button type="button" class="btn btn-ghost" id="chat-redirect-stay-btn">あとで</button>
      <button type="button" class="btn btn-primary" id="chat-redirect-go-btn">チャットへ移動</button>
    </div>
  </div>
</div>

<?php
$match_detail_page_title = 'マッチ詳細';
$match_detail_subtitle = '';
$match_detail_shell_opened = false;
if (function_exists('aidunite_web_app_page_shell_open')) {
    aidunite_web_app_page_shell_open([
        'page_class' => 'page-match-detail',
        'title' => $match_detail_page_title,
        'subtitle' => $match_detail_subtitle,
        'back_url' => home_url('/match-board-own'),
    ]);
    $match_detail_shell_opened = true;
} else {
    echo '<div class="team-dashboard-container page-match-detail">';
}
?>
    <!-- マッチ情報表示（dashboard-section は使用しない） -->
    <div class="match-detail-page">
    <main class="match-detail match-detail-layout">
        <?php if (!empty($is_other_only_mode)): ?>
        <div class="match-detail-main">
            <?php
            $other_date = $other_schedule_data['date'] ?? '';
            $my_schedules_same_day = [];
            if ($other_date && $current_user_team_id) {
                $team_id_int = (int) $current_user_team_id;
                $my_schedules_same_day = get_posts([
                    'post_type' => 'schedule',
                    'post_status' => 'publish',
                    'posts_per_page' => -1,
                    'meta_query' => [
                        'relation' => 'AND',
                        ['key' => 'team_id', 'value' => $team_id_int, 'compare' => '='],
                        ['key' => 'schedule_date', 'value' => $other_date, 'compare' => '=']
                    ]
                ]);
            }
            $can_apply = empty($application_status) || in_array($application_status, ['rejected', 'canceled']);
            $show_apply_form = $can_apply && !empty($my_schedules_same_day);
            $show_no_schedule_message = $can_apply && empty($my_schedules_same_day);
            // スケジュール登録は「どちらでも可」を both で保存。either と同義で選択UIを出す（仕様: match-request.md 会場の未確定時）
            $other_place_for_ui = $other_schedule_data['place'] ?? '';
            if ($other_place_for_ui === 'both') {
                $other_place_for_ui = 'either';
            }
            $need_place_choice = ($other_place_for_ui === 'either');
            $need_gender_choice = (($other_schedule_data['gender'] ?? '') === 'both');
            $default_place = $need_place_choice ? 'home' : ($other_schedule_data['place'] ?? 'either');
            // 相手が男女可のときは男子・女子・男女（both）の3択。既定は both（比較モードのピルと揃える）
            $default_gender = $need_gender_choice ? 'both' : ($other_schedule_data['gender'] ?? 'both');
            $other_apply_place_display = function_exists('jp_place') ? jp_place($default_place) : ($default_place ?: '-');
            $other_apply_gender_display = function_exists('jp_gender') ? jp_gender($default_gender) : ($default_gender ?: '-');
            ?>
            <div class="match-other-only-recruit">
                <div class="match-detail-card match-other-only-recruit-card">
                    <div class="match-detail-card-header">募集内容</div>
                    <div class="match-detail-card-body">
                        <div class="match-detail-card-row">
                            <div class="match-detail-card-label">日程</div>
                            <div class="match-detail-card-value"><?php echo esc_html($display_date !== '' ? $display_date : '—'); ?></div>
                        </div>
                        <div class="match-detail-card-row">
                            <div class="match-detail-card-label">相手チーム名</div>
                            <div class="match-detail-card-value"><?php echo esc_html($other_team_data['name'] ?? '—'); ?></div>
                        </div>
                        <div class="match-detail-card-row">
                            <div class="match-detail-card-label">時間</div>
                            <div class="match-detail-card-value"><?php echo esc_html($display_time !== '' ? $display_time : '—'); ?></div>
                        </div>
                        <div class="match-detail-card-row">
                            <div class="match-detail-card-label">会場</div>
                            <div class="match-detail-card-value"><?php echo esc_html(function_exists('jp_place') ? jp_place($other_place) : ($other_place ?: '—')); ?></div>
                        </div>
                        <div class="match-detail-card-row">
                            <div class="match-detail-card-label">性別</div>
                            <div class="match-detail-card-value"><?php echo esc_html(function_exists('jp_gender') ? jp_gender($other_gender) : ($other_gender ?: '—')); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- 申請エリア：比較モードと同じ match-apply-section / match-action-area で統一（独自HTML干渉防止） -->
            <div class="match-apply-section match-other-only-apply-block" style="margin-top: var(--spacing-lg); padding-top: var(--spacing-lg); border-top: 1px solid var(--border-light);">
                <?php if (in_array($application_status, ['accepted', 'established'])): ?>
                <div class="match-action-area">
                    <div class="badge match-card-status-badge badge-status status-established"><?php echo $application_status === 'established' ? '試合確定' : '承認済み'; ?></div>
                    <div><p class="match-action-area-message">この申請は承認されました</p></div>
                </div>
                <?php elseif (in_array($application_status, ['applying'])): ?>
                <div class="match-action-area">
                    <div class="badge match-card-status-badge badge-status status-pending">申請中</div>
                    <div></div>
                </div>
                <?php elseif (in_array($application_status, ['rejected', 'canceled'])): ?>
                    <?php if ($show_apply_form): ?>
                        <form id="applyFormOtherOnly" class="match-other-only-apply-form">
                            <?php if (count($my_schedules_same_day) > 1): ?>
                            <div class="match-select-ui">
                                <div class="match-select-row">
                                    <span class="match-select-label">この日の自チームの予定</span>
                                    <div class="match-select-pills" role="group" aria-label="この日の自チームの予定を選択">
                                        <?php
                                        $msi = 0;
                                        foreach ($my_schedules_same_day as $ms) {
                                            $ms_place = get_post_meta($ms->ID, 'schedule_place', true) ?: get_post_meta($ms->ID, 'schedule_place_option', true);
                                            $ms_place = ($ms_place === 'both') ? 'either' : $ms_place;
                                            $ms_label = get_post_meta($ms->ID, 'schedule_start_time', true) . '～' . get_post_meta($ms->ID, 'schedule_end_time', true);
                                            ?>
                                        <button type="button" class="match-pill match-pill-schedule<?php echo ($msi === 0) ? ' selected' : ''; ?>" data-type="my_schedule" data-value="<?php echo esc_attr($ms->ID); ?>" data-place="<?php echo esc_attr($ms_place ?: 'either'); ?>"><?php echo esc_html($ms_label); ?></button>
                                            <?php
                                            $msi++;
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?>
                            <?php $single_place = get_post_meta($my_schedules_same_day[0]->ID, 'schedule_place', true) ?: get_post_meta($my_schedules_same_day[0]->ID, 'schedule_place_option', true); $single_place = ($single_place === 'both') ? 'either' : $single_place; ?>
                            <input type="hidden" name="my_schedule_id" value="<?php echo esc_attr($my_schedules_same_day[0]->ID); ?>" data-my-place="<?php echo esc_attr($single_place ?: 'either'); ?>">
                            <?php endif; ?>
                            <?php if ($need_place_choice): ?>
                            <div class="match-select-ui">
                                <div class="match-select-row">
                                    <span class="match-select-label">会場を選択</span>
                                    <div class="match-select-pills" role="group" aria-label="会場を選択">
                                        <button type="button" class="match-pill match-pill-place<?php echo ($default_place === 'home') ? ' selected' : ''; ?>" data-type="place" data-value="home"><?php echo esc_html(jp_place('home')); ?></button>
                                        <button type="button" class="match-pill match-pill-place<?php echo ($default_place === 'away') ? ' selected' : ''; ?>" data-type="place" data-value="away"><?php echo esc_html(jp_place('away')); ?></button>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?><input type="hidden" name="selected_place" value="<?php echo esc_attr($default_place); ?>"><?php endif; ?>
                            <?php if ($need_gender_choice): ?>
                            <div class="match-select-ui">
                                <div class="match-select-row">
                                    <span class="match-select-label">性別を選択</span>
                                    <div class="match-select-pills" role="group" aria-label="性別を選択">
                                        <button type="button" class="match-pill match-pill-gender<?php echo ($default_gender === 'male') ? ' selected' : ''; ?>" data-type="gender" data-value="male"><?php echo esc_html(jp_gender('male')); ?></button>
                                        <button type="button" class="match-pill match-pill-gender<?php echo ($default_gender === 'female') ? ' selected' : ''; ?>" data-type="gender" data-value="female"><?php echo esc_html(jp_gender('female')); ?></button>
                                        <button type="button" class="match-pill match-pill-gender<?php echo ($default_gender === 'both') ? ' selected' : ''; ?>" data-type="gender" data-value="both"><?php echo esc_html(jp_gender('both')); ?></button>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?><input type="hidden" name="selected_gender" value="<?php echo esc_attr($default_gender); ?>"><?php endif; ?>
                        </form>
                        <p class="match-detail-apply-content-heading">申請内容</p>
                        <div class="match-apply-summary">
                            <div><?php echo esc_html(($display_date !== '' ? $display_date : '—') . '｜' . ($display_time !== '' ? $display_time : '—')); ?></div>
                            <div><span id="apply-summary-place-other"><?php echo esc_html($other_apply_place_display); ?></span>｜<span id="apply-summary-gender-other"><?php echo esc_html($other_apply_gender_display); ?></span></div>
                        </div>
                        <div class="match-action-area">
                            <div class="badge match-card-status-badge badge-status status-rejected"><?php echo $application_status === 'rejected' ? '拒否済み' : 'キャンセル済み'; ?></div>
                            <div>
                                <button type="button" class="apply-button btn btn-primary" id="applyButtonOtherOnly" data-other-schedule-id="<?php echo esc_attr($other_schedule_id); ?>" data-other-team-id="<?php echo esc_attr($other_team_id); ?>">再申請する</button>
                            </div>
                        </div>
                    <?php else: ?>
                <div class="match-action-area">
                    <div class="badge match-card-status-badge badge-status status-rejected"><?php echo $application_status === 'rejected' ? '拒否済み' : 'キャンセル済み'; ?></div>
                    <div></div>
                </div>
                    <?php endif; ?>
                <?php elseif ($show_apply_form): ?>
                        <form id="applyFormOtherOnly" class="match-other-only-apply-form">
                            <?php if (count($my_schedules_same_day) > 1): ?>
                            <div class="match-select-ui">
                                <div class="match-select-row">
                                    <span class="match-select-label">この日の自チームの予定</span>
                                    <div class="match-select-pills" role="group" aria-label="この日の自チームの予定を選択">
                                        <?php
                                        $msi = 0;
                                        foreach ($my_schedules_same_day as $ms) {
                                            $ms_place = get_post_meta($ms->ID, 'schedule_place', true) ?: get_post_meta($ms->ID, 'schedule_place_option', true);
                                            $ms_place = ($ms_place === 'both') ? 'either' : $ms_place;
                                            $ms_label = get_post_meta($ms->ID, 'schedule_start_time', true) . '～' . get_post_meta($ms->ID, 'schedule_end_time', true);
                                            ?>
                                        <button type="button" class="match-pill match-pill-schedule<?php echo ($msi === 0) ? ' selected' : ''; ?>" data-type="my_schedule" data-value="<?php echo esc_attr($ms->ID); ?>" data-place="<?php echo esc_attr($ms_place ?: 'either'); ?>"><?php echo esc_html($ms_label); ?></button>
                                            <?php
                                            $msi++;
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?>
                            <?php $single_place = get_post_meta($my_schedules_same_day[0]->ID, 'schedule_place', true) ?: get_post_meta($my_schedules_same_day[0]->ID, 'schedule_place_option', true); $single_place = ($single_place === 'both') ? 'either' : $single_place; ?>
                            <input type="hidden" name="my_schedule_id" value="<?php echo esc_attr($my_schedules_same_day[0]->ID); ?>" data-my-place="<?php echo esc_attr($single_place ?: 'either'); ?>">
                            <?php endif; ?>
                            <?php if ($need_place_choice): ?>
                            <div class="match-select-ui">
                                <div class="match-select-row">
                                    <span class="match-select-label">会場を選択</span>
                                    <div class="match-select-pills" role="group" aria-label="会場を選択">
                                        <button type="button" class="match-pill match-pill-place<?php echo ($default_place === 'home') ? ' selected' : ''; ?>" data-type="place" data-value="home"><?php echo esc_html(jp_place('home')); ?></button>
                                        <button type="button" class="match-pill match-pill-place<?php echo ($default_place === 'away') ? ' selected' : ''; ?>" data-type="place" data-value="away"><?php echo esc_html(jp_place('away')); ?></button>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?><input type="hidden" name="selected_place" value="<?php echo esc_attr($default_place); ?>"><?php endif; ?>
                            <?php if ($need_gender_choice): ?>
                            <div class="match-select-ui">
                                <div class="match-select-row">
                                    <span class="match-select-label">性別を選択</span>
                                    <div class="match-select-pills" role="group" aria-label="性別を選択">
                                        <button type="button" class="match-pill match-pill-gender<?php echo ($default_gender === 'male') ? ' selected' : ''; ?>" data-type="gender" data-value="male"><?php echo esc_html(jp_gender('male')); ?></button>
                                        <button type="button" class="match-pill match-pill-gender<?php echo ($default_gender === 'female') ? ' selected' : ''; ?>" data-type="gender" data-value="female"><?php echo esc_html(jp_gender('female')); ?></button>
                                        <button type="button" class="match-pill match-pill-gender<?php echo ($default_gender === 'both') ? ' selected' : ''; ?>" data-type="gender" data-value="both"><?php echo esc_html(jp_gender('both')); ?></button>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?><input type="hidden" name="selected_gender" value="<?php echo esc_attr($default_gender); ?>"><?php endif; ?>
                        </form>
                <p class="match-detail-apply-content-heading">申請内容</p>
                <div class="match-apply-summary">
                    <div><?php echo esc_html(($display_date !== '' ? $display_date : '—') . '｜' . ($display_time !== '' ? $display_time : '—')); ?></div>
                    <div><span id="apply-summary-place-other"><?php echo esc_html($other_apply_place_display); ?></span>｜<span id="apply-summary-gender-other"><?php echo esc_html($other_apply_gender_display); ?></span></div>
                </div>
                <div class="match-action-area">
                    <div class="badge match-card-status-badge badge-unapplied"><?php echo esc_html(function_exists('get_match_status_label') ? get_match_status_label('not_applied', 'text') : '未申請'); ?></div>
                    <div>
                        <button type="button" class="apply-button btn btn-primary" id="applyButtonOtherOnly" data-testid="match-apply-button" data-other-schedule-id="<?php echo esc_attr($other_schedule_id); ?>" data-other-team-id="<?php echo esc_attr($other_team_id); ?>">申請する</button>
                    </div>
                </div>
                <?php elseif ($show_no_schedule_message): ?>
                <div class="match-other-only-no-schedule-message">
                    <p>この日（<?php echo esc_html($display_date); ?>）の自チームの予定がありません。申請するとこの日の仮スケジュール（練習試合（仮））が自動作成され、承認後に確定します。</p>
                </div>
                        <form id="applyFormOtherOnly" class="match-other-only-apply-form">
                            <input type="hidden" name="my_schedule_id" value="0">
                            <?php if ($need_place_choice): ?>
                            <div class="match-select-ui">
                                <div class="match-select-row">
                                    <span class="match-select-label">会場を選択</span>
                                    <div class="match-select-pills" role="group" aria-label="会場を選択">
                                        <button type="button" class="match-pill match-pill-place<?php echo ($default_place === 'home') ? ' selected' : ''; ?>" data-type="place" data-value="home"><?php echo esc_html(jp_place('home')); ?></button>
                                        <button type="button" class="match-pill match-pill-place<?php echo ($default_place === 'away') ? ' selected' : ''; ?>" data-type="place" data-value="away"><?php echo esc_html(jp_place('away')); ?></button>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?><input type="hidden" name="selected_place" value="<?php echo esc_attr($default_place); ?>"><?php endif; ?>
                            <?php if ($need_gender_choice): ?>
                            <div class="match-select-ui">
                                <div class="match-select-row">
                                    <span class="match-select-label">性別を選択</span>
                                    <div class="match-select-pills" role="group" aria-label="性別を選択">
                                        <button type="button" class="match-pill match-pill-gender<?php echo ($default_gender === 'male') ? ' selected' : ''; ?>" data-type="gender" data-value="male"><?php echo esc_html(jp_gender('male')); ?></button>
                                        <button type="button" class="match-pill match-pill-gender<?php echo ($default_gender === 'female') ? ' selected' : ''; ?>" data-type="gender" data-value="female"><?php echo esc_html(jp_gender('female')); ?></button>
                                        <button type="button" class="match-pill match-pill-gender<?php echo ($default_gender === 'both') ? ' selected' : ''; ?>" data-type="gender" data-value="both"><?php echo esc_html(jp_gender('both')); ?></button>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?><input type="hidden" name="selected_gender" value="<?php echo esc_attr($default_gender); ?>"><?php endif; ?>
                        </form>
                <p class="match-detail-apply-content-heading">申請内容</p>
                <div class="match-apply-summary">
                    <div><?php echo esc_html(($display_date !== '' ? $display_date : '—') . '｜' . ($display_time !== '' ? $display_time : '—')); ?></div>
                    <div><span id="apply-summary-place-other"><?php echo esc_html($other_apply_place_display); ?></span>｜<span id="apply-summary-gender-other"><?php echo esc_html($other_apply_gender_display); ?></span></div>
                </div>
                <div class="match-action-area">
                    <div class="badge match-card-status-badge badge-unapplied"><?php echo esc_html(function_exists('get_match_status_label') ? get_match_status_label('not_applied', 'text') : '未申請'); ?></div>
                    <div>
                        <button type="button" class="apply-button btn btn-primary" id="applyButtonOtherOnly" data-testid="match-apply-button" data-other-schedule-id="<?php echo esc_attr($other_schedule_id); ?>" data-other-team-id="<?php echo esc_attr($other_team_id); ?>">申請する</button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        $match_detail_venue_label = function_exists('jp_place') ? jp_place($other_place ?? '') : '';
        $match_detail_common_context = [
            'my_schedule_data'    => [],
            'other_schedule_data' => isset($other_schedule_data) && is_array($other_schedule_data) ? $other_schedule_data : [],
        ];
        ?>
        <aside class="match-detail-sidebar" aria-label="<?php echo esc_attr('相手チーム情報'); ?>">
            <?php include get_stylesheet_directory() . '/template-parts/match-detail-opponent-sidebar.php'; ?>
        </aside>
        <div class="match-detail-flow-wrap">
            <?php include get_stylesheet_directory() . '/template-parts/match-detail-flow.php'; ?>
        </div>
            <?php else: ?>
            <div class="match-detail-main">
            <?php
            // 比較モード専用（ここは !$is_other_only_mode の else 内のため、$time_result_for_detail 等は既に設定済み）
            if (!empty($is_guest_invite)) {
                $breakdown = ['date' => 2, 'time' => 2, 'gender' => 2, 'place' => 2, 'total' => 8];
            } else {
                $breakdown = (is_array($match_apply_evaluation) && function_exists('aidunite_match_board_scores_breakdown_from_evaluation'))
                    ? aidunite_match_board_scores_breakdown_from_evaluation($match_apply_evaluation)
                    : ['date' => 2, 'time' => 2, 'gender' => 2, 'place' => 2, 'total' => 8];
            }
            if (!is_array($breakdown)) {
                $breakdown = ['date' => 2, 'time' => 2, 'gender' => 2, 'place' => 2, 'total' => 8];
            }
            $time_level = isset($time_result_for_detail['level']) ? $time_result_for_detail['level'] : ($needs_time_adjustment ? 'TIME_WEAK' : 'TIME_STRONG');

            $place_level = 'PLACE_EASY';
            if (function_exists('aidunite_get_place_level')) {
                $place_result = aidunite_get_place_level($my_place, $other_place);
                if (is_array($place_result) && !empty($place_result['level'])) {
                    $place_level = $place_result['level'];
                }
            } elseif ($needs_place_adjustment) {
                if ($breakdown['place'] >= 2) {
                    $place_level = 'PLACE_EASY';
                } elseif ($breakdown['place'] >= 1) {
                    $place_level = 'PLACE_MEDIUM';
                } else {
                    $place_level = 'PLACE_HEAVY';
                }
            }

            $gender_mixed = $needs_gender_adjustment;

            // 調整が必要な項目を判定
            $adjustment_items = [];
            if ($time_level && $time_level !== 'TIME_STRONG') {
                $adjustment_items[] = '時間';
            }
            if ($place_level && $place_level !== 'PLACE_EASY') {
                $adjustment_items[] = '会場';
            }
            if ($gender_mixed) {
                $adjustment_items[] = '性別';
            }

            $status = ($match_detail_display_mode === 'white') ? 'no_preference' : $match_detail_display_mode;
            $other_display_date = '';
            if (!empty($other_schedule_data['date'])) {
                $od = DateTime::createFromFormat('Y-m-d', $other_schedule_data['date']);
                if ($od) {
                    $weekdays = ['日', '月', '火', '水', '木', '金', '土'];
                    $other_display_date = $od->format('n/j') . '（' . $weekdays[(int)$od->format('w')] . '）';
                }
            }
            if ($other_display_date === '' && $display_date !== '') {
                $other_display_date = $display_date;
            }
            if ($display_date === '' && $other_display_date !== '') {
                $display_date = $other_display_date;
            }
            $compare_date_my = $display_date !== '' ? $display_date : '—';
            $fmt_time = function($start, $end) {
                if (empty($start) || empty($end)) return '-';
                return str_replace(':', '：', $start) . '～' . str_replace(':', '：', $end);
            };
            $other_time_range = $fmt_time($other_schedule_data['start'] ?? '', $other_schedule_data['end'] ?? '');
            $my_time_range = $fmt_time($my_schedule_data['start'] ?? '', $my_schedule_data['end'] ?? '');
            $apply_time_display = ($match_detail_display_mode === 'established' && $confirmed_data)
                ? $fmt_time($confirmed_data['start_time'] ?? '', $confirmed_data['end_time'] ?? '')
                : ((!empty($time_range_start_str) && !empty($time_range_end_str))
                    ? $fmt_time($time_range_start_str, $time_range_end_str)
                    : $my_time_range);
            // 予定の条件は常に「各スケジュールの登録値」を表示する（申請/確定値で上書きしない）
            $compare_other_place = (!empty($is_guest_invite) && isset($other_place_disp_guest)) ? $other_place_disp_guest : jp_place($other_place);
            $compare_my_place = jp_place($my_place);
            $compare_other_gender = jp_gender($other_gender);
            $compare_my_gender = jp_gender($my_gender);
            if ($application_status === 'received' && !empty($applied_data) && $latest_request) {
                $applied_place_for_compare = (string) ($applied_data['place'] ?? '');
                if ($applied_place_for_compare === 'both') {
                    $applied_place_for_compare = 'either';
                }
                if (in_array($applied_place_for_compare, ['home', 'away', 'either'], true) && function_exists('aidunite_resolve_place_for_viewer')) {
                    $from_team_req = (int) get_post_meta($latest_request->ID, 'from_team_id', true);
                    $to_team_req = (int) get_post_meta($latest_request->ID, 'to_team_id', true);
                    if ($to_team_req <= 0) {
                        $to_team_req = (int) get_post_meta($latest_request->ID, 'other_team_id', true);
                    }
                    $my_place_resolved = aidunite_resolve_place_for_viewer($applied_place_for_compare, $from_team_req, $to_team_req, (int) $current_user_team_id);
                    $other_viewer_team_id = ((int) $current_user_team_id === $from_team_req) ? $to_team_req : $from_team_req;
                    $other_place_resolved = aidunite_resolve_place_for_viewer($applied_place_for_compare, $from_team_req, $to_team_req, (int) $other_viewer_team_id);
                    $compare_my_place = jp_place($my_place_resolved);
                    $compare_other_place = jp_place($other_place_resolved);
                }

                $applied_gender_for_compare = (string) ($applied_data['gender'] ?? '');
                if (in_array($applied_gender_for_compare, ['male', 'female', 'both'], true)) {
                    // 受信者画面では「相手」= 申請者の選択、「自分」= 自チーム募集条件で表示する
                    $compare_other_gender = jp_gender($applied_gender_for_compare);
                    $compare_my_gender = jp_gender($my_gender);
                }
            }
            // 選択UI: 会場＝双方未定 or 同固定衝突／性別＝複数候補が残るとき（match-request.md 第10節）
            $show_place_choice = !empty($needs_place_adjustment) && $status !== 'established';
            $show_gender_choice = !empty($needs_gender_adjustment) && isset($gender_options) && count($gender_options) > 1 && $status !== 'established';
            $has_undecided_ui = $show_place_choice || $show_gender_choice;
            // 申請中・受付中は従来どおり固定。再確認待ちかつ黄（条件調整・提案プレビュー）もピルは表示のみ固定（再申請は data-default-* とサーバ正規化に従う）
            $lock_pill_selection = in_array($application_status, ['applying', 'received', 'proposal_pending_accept'], true)
                || (
                    $application_status === 'reconfirm_required'
                    && isset($match_detail_display_mode)
                    && $match_detail_display_mode === 'yellow'
                );
            $my_team_id = isset($my_team_id) ? $my_team_id : (isset($current_user_team_id) ? $current_user_team_id : 0);
            $my_schedule_id = isset($my_schedule_id) ? $my_schedule_id : 0;
            $other_team_id = isset($other_team_id) ? $other_team_id : (isset($other_team_data['team_id']) ? $other_team_data['team_id'] : 0);
            $other_schedule_id = isset($other_schedule_id) ? $other_schedule_id : 0;
            $application_status = isset($application_status) ? $application_status : '';
            if (in_array($application_status, ['applying', 'received'], true) && !empty($applied_data)) {
                $applied_place = $applied_data['place'] ?? '';
                if ($applied_place === 'both') {
                    $applied_place = 'either';
                }
                if (in_array($applied_place, ['home', 'away', 'either'], true)) {
                    $resolved_place_value = $applied_place;
                }
                $applied_gender = $applied_data['gender'] ?? '';
                if (in_array($applied_gender, ['male', 'female', 'both'], true)) {
                    $resolved_gender_value = $applied_gender;
                }
            }
            // 再確認・申請者: 募集変更後の「再申請で送る会場」を申請者視点で募集＋place_lock と整合（MR の古い selected_place に引っ張られない）
            if ($application_status === 'reconfirm_required' && !empty($is_applicant) && !empty($latest_request)) {
                $to_sid_place = (int) get_post_meta($latest_request->ID, 'to_schedule_id', true);
                $my_sid_place = (int) $my_schedule_id;
                if ($to_sid_place > 0 && $my_sid_place > 0 && function_exists('_aidunite_resolve_selected_place_for_established')) {
                    $canon_pl = (string) _aidunite_resolve_selected_place_for_established('', $to_sid_place, $my_sid_place);
                    if (in_array($canon_pl, ['home', 'away', 'either'], true)) {
                        $resolved_place_value = $canon_pl;
                    }
                }
            }
            $is_received_request = isset($is_received_request) ? $is_received_request : false;
            $is_received_highlight = ($application_status === 'received' && $is_received_request);
            $received_request_id = isset($received_request_id) ? $received_request_id : 0;
            $latest_request = isset($latest_request) ? $latest_request : null;
            // 申請内容の初期表示は 9パターンで解決済みの会場（resolved_place_value）を採用
            $apply_place_display = jp_place($resolved_place_value);
            $apply_gender_display = jp_gender($resolved_gender_value);
            if (in_array($application_status, ['applying', 'received'], true) && !empty($applied_data)) {
                $apply_place_display = jp_place($resolved_place_value);
                $apply_gender_display = jp_gender($resolved_gender_value);
            }
            if ($match_detail_display_mode === 'established' && $confirmed_data) {
                $confirmed_place = (string) ($confirmed_data['place'] ?? '');
                if ($confirmed_place === 'both') {
                    $confirmed_place = 'either';
                }
                $from_team_req = $latest_request ? (int) get_post_meta($latest_request->ID, 'from_team_id', true) : 0;
                $to_team_req = $latest_request ? (int) get_post_meta($latest_request->ID, 'to_team_id', true) : 0;
                if ($to_team_req <= 0 && $latest_request) {
                    $to_team_req = (int) get_post_meta($latest_request->ID, 'other_team_id', true);
                }
                if (in_array($confirmed_place, ['home', 'away', 'either'], true) && function_exists('aidunite_resolve_place_for_viewer') && $from_team_req > 0 && $to_team_req > 0) {
                    $confirmed_place_for_me = (string) aidunite_resolve_place_for_viewer($confirmed_place, $from_team_req, $to_team_req, (int) $current_user_team_id);
                    $apply_place_display = jp_place($confirmed_place_for_me);
                } else {
                    $apply_place_display = jp_place($confirmed_place);
                }
                $apply_gender_display = jp_gender($confirmed_data['gender']);
            }
            $established_time_display = ($match_detail_display_mode === 'established' && $confirmed_data) ? $fmt_time($confirmed_data['start_time'] ?? '', $confirmed_data['end_time'] ?? '') : '';
            $table_other_time = $other_time_range;
            $table_my_time = $my_time_range;
            $received_request_message = '';
            $received_apply_place_resolved = (string) $resolved_place_value;
            if ($application_status === 'received' && $is_received_request && !empty($applied_data)) {
                $apply_time_display = $fmt_time($applied_data['start_time'] ?? '', $applied_data['end_time'] ?? '');
                $applied_place_for_card = (string) ($applied_data['place'] ?? '');
                if ($applied_place_for_card === 'both') {
                    $applied_place_for_card = 'either';
                }
                if (in_array($applied_place_for_card, ['home', 'away', 'either'], true) && !empty($latest_request) && function_exists('aidunite_resolve_place_for_viewer')) {
                    $from_team_req = (int) get_post_meta($latest_request->ID, 'from_team_id', true);
                    $to_team_req = (int) get_post_meta($latest_request->ID, 'to_team_id', true);
                    if ($to_team_req <= 0) {
                        $to_team_req = (int) get_post_meta($latest_request->ID, 'other_team_id', true);
                    }
                    $received_apply_place_resolved = (string) aidunite_resolve_place_for_viewer(
                        $applied_place_for_card,
                        $from_team_req,
                        $to_team_req,
                        (int) $current_user_team_id
                    );
                }
                if ($received_apply_place_resolved === 'home') {
                    $received_request_message = '相手チームは、あなたのホームでの試合を希望しています。';
                } elseif ($received_apply_place_resolved === 'away') {
                    $received_request_message = '相手チームは、相手のホームでの試合を希望しています。';
                } else {
                    $received_request_message = '相手チームは、会場をどちらでも可としています。';
                }
            }
            $match_detail_venue_hint_theme = function_exists('aidunite_get_team_ui_theme_key')
                ? aidunite_get_team_ui_theme_key(isset($current_user_team_id) ? (int) $current_user_team_id : null)
                : 'boys';
            $received_apply_place_display = jp_place($received_apply_place_resolved);
            $received_datetime_display = ($compare_date_my !== '—' ? $compare_date_my : '')
                . str_replace(['：', '～'], [':', '〜'], (string) $apply_time_display);
            $match_detail_icon_base = aidunite_get_theme_icons_uri();
            ?>
            <!-- ① 結論バナー（state × role） -->
            <?php
            $is_conclusion_received_side = ($application_status === 'received' && $is_received_request);
            $conclusion_show = true;
            $conclusion_icon_file = '';
            $conclusion_title = '';
            $conclusion_desc = '';

            switch ($status) {
                case 'green':
                    $conclusion_icon_file = 'check_circle.svg';
                    if ($is_conclusion_received_side) {
                        $conclusion_title = 'この条件で承認できます';
                        $conclusion_desc = '申請内容に問題がなければ、承認すると試合が成立します。';
                    } else {
                        if ($application_status === 'applying') {
                            $conclusion_title = 'この条件で申請中です。';
                            $conclusion_desc = "以下の内容で申請しています。\n相手チームの承認をお待ちください。";
                        } else {
                            $conclusion_title = 'この条件で申請できます。';
                            $conclusion_desc = '条件が一致しているため、この内容で申請できます。';
                        }
                    }
                    break;
                case 'no_preference':
                    if ($is_conclusion_received_side) {
                        $conclusion_show = false;
                    } else {
                        $conclusion_icon_file = 'calendar_month.svg';
                        $conclusion_title = 'この日は試合調整が可能です';
                        $conclusion_desc = 'この募集へ申請できます。申請後、仮の日程として登録されます。';
                    }
                    break;
                case 'yellow':
                    $conclusion_icon_file = 'sliders.svg';
                    if ($is_conclusion_received_side) {
                        $conclusion_title = '申請内容の確認が必要です';
                        $conclusion_desc = '相手から届いた条件を確認してから承認してください。';
                    } else {
                        $conclusion_title = '条件調整が必要です';
                        $conclusion_desc = '一部条件を確認してから申請してください。';
                    }
                    break;
                case 'established':
                    $conclusion_icon_file = 'heart_check.svg';
                    $conclusion_title = '試合が成立しました';
                    $conclusion_desc = '確定した条件で、試合の詳細調整へ進めます。';
                    break;
                default:
                    $conclusion_icon_file = 'check_circle.svg';
                    if ($application_status === 'received' && !empty($is_received_request)) {
                        $conclusion_title = 'この条件で承認できます';
                        $conclusion_desc = '申請内容に問題がなければ、承認すると試合が成立します。';
                    } else {
                        $conclusion_title = 'この条件で申請できます';
                        $conclusion_desc = '条件が一致しているため、この内容で申請できます。';
                    }
                    break;
            }
            ?>
            <?php if ($conclusion_show): ?>
            <section class="match-detail-conclusion" role="status">
                <div class="match-result match-result--<?php echo esc_attr($status); ?> match-result--banner">
                    <?php if ($conclusion_icon_file !== ''): ?>
                    <span class="match-result__icon" aria-hidden="true">
                        <?php
                        $conclusion_icon_slug = str_replace('-', '_', pathinfo($conclusion_icon_file, PATHINFO_FILENAME));
                        $conclusion_preserve_fill = in_array($conclusion_icon_slug, ['sliders', 'heart_check'], true);
                        echo aidunite_get_theme_icon_svg($conclusion_icon_slug, ['width' => '24', 'height' => '24'], !$conclusion_preserve_fill); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        ?>
                    </span>
                    <?php endif; ?>
                    <div class="match-result__content">
                        <p class="match-result__title"><?php echo esc_html($conclusion_title); ?></p>
                        <?php if ($conclusion_desc !== ''): ?>
                        <p class="match-result__desc"><?php echo esc_html($conclusion_desc); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
            <?php endif; ?>
            <?php
            $show_proposal_hint = !empty($match_apply_evaluation)
                && ($match_apply_evaluation['variant'] ?? '') === 'proposal'
                && !in_array($application_status, ['applying', 'received', 'accepted', 'established', 'reconfirm_required'], true);
            ?>
            <?php if ($show_proposal_hint) : ?>
            <div class="match-detail-proposal-hint" role="status">
                <p class="match-detail-proposal-hint__title"><?php echo esc_html('会場希望のすり合わせ'); ?></p>
                <p class="match-detail-proposal-hint__body"><?php echo esc_html($match_apply_evaluation['message'] ?? ''); ?></p>
                <?php
                $prop_p = $match_apply_evaluation['proposal']['selected_place'] ?? '';
                if ($prop_p !== '' && function_exists('jp_place')) :
                    ?>
                <p class="match-detail-proposal-hint__foot"><?php echo esc_html('今回の申請では '); ?><strong><?php echo esc_html(jp_place($prop_p)); ?></strong><?php echo esc_html(' として送信してください（登録中の自チーム希望は変更されません）。'); ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if ($is_received_highlight && $status !== 'established'): ?>
            <!-- ② 受信側：相手からの申請内容（比較表の前） -->
            <section class="match-detail-section match-detail-section--received-apply" aria-labelledby="match-detail-received-apply-title">
                <header class="match-detail-section__intro">
                    <div class="match-detail-section-header">
                        <div class="match-detail-section-header__title-wrap">
                            <span class="match-detail-section-header__icon" aria-hidden="true">
                                <img src="<?php echo esc_url($match_detail_icon_base . 'person_check.svg'); ?>" alt="" width="20" height="20" decoding="async">
                            </span>
                            <h2 id="match-detail-received-apply-title" class="match-detail-section-header__title">相手からの申請内容</h2>
                        </div>
                        <p class="match-detail-section-header__lead">内容を確認し、問題なければ承認してください。</p>
                    </div>
                </header>
                <div class="match-detail-section__boundary" aria-hidden="true"></div>
                <div class="card match-detail-received-apply-card match-detail-received-apply-card--<?php echo esc_attr($match_detail_venue_hint_theme); ?>">
                    <div class="card-body">
                        <ul class="match-detail-received-apply-items">
                            <li class="match-detail-received-apply-item">
                                <span class="match-detail-received-apply-item__head">
                                    <span class="match-detail-received-apply-item__icon" aria-hidden="true">
                                        <img src="<?php echo esc_url($match_detail_icon_base . 'schedule.svg'); ?>" alt="" width="20" height="20" decoding="async">
                                    </span>
                                    <span class="match-detail-received-apply-item__label">日時</span>
                                </span>
                                <span class="match-detail-received-apply-item__value"><?php echo esc_html($received_datetime_display); ?></span>
                            </li>
                            <li class="match-detail-received-apply-item">
                                <span class="match-detail-received-apply-item__head">
                                    <span class="match-detail-received-apply-item__icon" aria-hidden="true">
                                        <img src="<?php echo esc_url($match_detail_icon_base . 'home.svg'); ?>" alt="" width="20" height="20" decoding="async">
                                    </span>
                                    <span class="match-detail-received-apply-item__label">会場</span>
                                </span>
                                <span class="match-detail-received-apply-item__value" id="apply-summary-place"><?php echo esc_html($received_apply_place_display); ?></span>
                            </li>
                            <li class="match-detail-received-apply-item">
                                <span class="match-detail-received-apply-item__head">
                                    <span class="match-detail-received-apply-item__icon" aria-hidden="true">
                                        <img src="<?php echo esc_url($match_detail_icon_base . 'group.svg'); ?>" alt="" width="20" height="20" decoding="async">
                                    </span>
                                    <span class="match-detail-received-apply-item__label">性別</span>
                                </span>
                                <span class="match-detail-received-apply-item__value" id="apply-summary-gender"><?php echo esc_html($apply_gender_display); ?></span>
                            </li>
                        </ul>
                    </div>
                </div>
                <?php if ($received_request_message !== ''): ?>
                <div class="match-detail-venue-hint match-detail-venue-hint--<?php echo esc_attr($match_detail_venue_hint_theme); ?>">
                    <span class="match-detail-venue-hint__icon" aria-hidden="true">
                        <?php echo aidunite_get_theme_icon_svg('info', ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </span>
                    <p class="match-detail-venue-hint__text"><?php echo esc_html($received_request_message); ?></p>
                </div>
                <?php endif; ?>
                <?php if ($application_status === 'proposal_pending_accept'): ?>
                <p class="match-detail-status-note" role="status">
                    相手チームの承諾待ちです。承諾完了後に承認操作が可能になります。
                </p>
                <?php endif; ?>
            </section>
            <?php endif; ?>
            <?php
            $apply_heading_text = ($application_status === 'reconfirm_required')
                ? '申請内容（現在）'
                : (($status === 'established') ? '確定内容' : '申請内容');
            $applicant_request_message = '';
            if (!$is_received_highlight && $status !== 'established') {
                if ($resolved_place_value === 'home') {
                    $applicant_request_message = 'あなたは、相手チームのホームでの試合を希望しています。';
                } elseif ($resolved_place_value === 'away') {
                    $applicant_request_message = 'あなたは、あなたのホームでの試合を希望しています。';
                } else {
                    $applicant_request_message = 'あなたは、会場をどちらでも可として申請しています。';
                }
            }
            ?>
            <?php if (!$is_received_highlight): ?>
            <!-- ② 申請側：申請内容（承認側と同じ位置） -->
            <section class="match-detail-section match-detail-section--apply" aria-labelledby="match-detail-apply-title">
                <header class="match-detail-section__intro">
                    <div class="match-detail-section-header">
                        <div class="match-detail-section-header__title-wrap">
                            <h2 id="match-detail-apply-title" class="match-detail-section-header__title match-detail-apply-content-heading"><?php echo esc_html($apply_heading_text); ?></h2>
                        </div>
                    </div>
                </header>
                <div class="match-detail-section__boundary" aria-hidden="true"></div>
                <div class="card match-detail-apply-card match-detail-apply-card--<?php echo esc_attr($match_detail_venue_hint_theme); ?>">
                <div class="card-body">
                    <div class="match-apply-summary">
                        <ul class="match-detail-received-apply-items match-detail-apply-items">
                            <li class="match-detail-received-apply-item">
                                <span class="match-detail-received-apply-item__head">
                                    <span class="match-detail-received-apply-item__icon" aria-hidden="true">
                                        <img src="<?php echo esc_url($match_detail_icon_base . 'schedule.svg'); ?>" alt="" width="20" height="20" decoding="async">
                                    </span>
                                    <span class="match-detail-received-apply-item__label">日時</span>
                                </span>
                                <span class="match-detail-received-apply-item__value"><?php echo esc_html($compare_date_my . ' ' . $apply_time_display); ?></span>
                            </li>
                            <li class="match-detail-received-apply-item">
                                <span class="match-detail-received-apply-item__head">
                                    <span class="match-detail-received-apply-item__icon" aria-hidden="true">
                                        <img src="<?php echo esc_url($match_detail_icon_base . 'home.svg'); ?>" alt="" width="20" height="20" decoding="async">
                                    </span>
                                    <span class="match-detail-received-apply-item__label">会場</span>
                                </span>
                                <span class="match-detail-received-apply-item__value" id="apply-summary-place"><?php echo esc_html($apply_place_display); ?></span>
                            </li>
                            <li class="match-detail-received-apply-item">
                                <span class="match-detail-received-apply-item__head">
                                    <span class="match-detail-received-apply-item__icon" aria-hidden="true">
                                        <img src="<?php echo esc_url($match_detail_icon_base . 'group.svg'); ?>" alt="" width="20" height="20" decoding="async">
                                    </span>
                                    <span class="match-detail-received-apply-item__label">性別</span>
                                </span>
                                <span class="match-detail-received-apply-item__value">
                                    <?php if ($show_gender_choice): ?>
                                    <span id="apply-summary-gender"><?php echo esc_html($apply_gender_display); ?></span>
                                    <?php else: ?>
                                    <?php echo esc_html($apply_gender_display); ?>
                                    <?php endif; ?>
                                </span>
                            </li>
                        </ul>
                    </div>
                    <?php if ($application_status === 'proposal_pending_accept'): ?>
                    <p class="match-detail-status-note">
                        相手チームの承諾待ちです。承諾完了後に承認操作が可能になります。
                    </p>
                    <?php endif; ?>
                </div>
                </div>
                <?php if ($applicant_request_message !== ''): ?>
                <div class="match-detail-venue-hint match-detail-venue-hint--<?php echo esc_attr($match_detail_venue_hint_theme); ?>">
                    <span class="match-detail-venue-hint__icon" aria-hidden="true">
                        <?php echo aidunite_get_theme_icon_svg('info', ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </span>
                    <p class="match-detail-venue-hint__text"><?php echo esc_html($applicant_request_message); ?></p>
                </div>
                <?php endif; ?>
            </section>
            <?php endif; ?>
            <!-- 予定の条件 -->
            <section class="match-detail-section match-detail-section--conditions" aria-labelledby="match-detail-conditions-title">
                <header class="match-detail-section__intro">
                    <div class="match-detail-section-header match-detail-conditions-card__header">
                        <div class="match-detail-section-header__row">
                            <div class="match-detail-section-header__title-wrap">
                                <span class="match-detail-section-header__icon" aria-hidden="true">
                                    <img src="<?php echo esc_url($match_detail_icon_base . 'schedule.svg'); ?>" alt="" width="20" height="20" decoding="async">
                                </span>
                                <h2 id="match-detail-conditions-title" class="match-detail-section-header__title">予定の条件</h2>
                            </div>
                            <?php if (!empty($compare_date_my)) : ?>
                            <span class="match-detail-conditions-card__date"><?php echo esc_html($compare_date_my); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </header>
                <div class="match-detail-section__boundary" aria-hidden="true"></div>
                <div class="card match-detail-conditions-card">
                <div class="card-body">
                    <div class="match-detail-conditions-compare" role="group" aria-label="<?php echo esc_attr('予定の条件の比較'); ?>">
                        <table class="match-detail-conditions-table" role="table">
                            <thead>
                                <tr>
                                    <th scope="col" class="match-detail-conditions-table__label-col"><span class="visually-hidden">項目</span></th>
                                    <th scope="col" class="match-detail-conditions-table__col match-detail-conditions-table__col--other">相手チームの条件</th>
                                    <th scope="col" class="match-detail-conditions-table__col match-detail-conditions-table__col--mine">自分の試合の条件</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <th scope="row">
                                        <span class="match-detail-conditions-table__row-label">
                                            <span class="match-detail-conditions-table__row-icon" aria-hidden="true">
                                                <img src="<?php echo esc_url($match_detail_icon_base . 'schedule.svg'); ?>" alt="" width="18" height="18" decoding="async">
                                            </span>
                                            <span class="match-detail-conditions-table__row-text">時間</span>
                                        </span>
                                    </th>
                                    <td class="match-detail-conditions-table__col match-detail-conditions-table__col--other"><?php echo esc_html($table_other_time); ?></td>
                                    <td class="match-detail-conditions-table__col match-detail-conditions-table__col--mine"><?php echo esc_html($table_my_time); ?></td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <span class="match-detail-conditions-table__row-label">
                                            <span class="match-detail-conditions-table__row-icon" aria-hidden="true">
                                                <img src="<?php echo esc_url($match_detail_icon_base . 'home.svg'); ?>" alt="" width="18" height="18" decoding="async">
                                            </span>
                                            <span class="match-detail-conditions-table__row-text">会場</span>
                                        </span>
                                    </th>
                                    <td class="match-detail-conditions-table__col match-detail-conditions-table__col--other"><?php echo esc_html($compare_other_place); ?></td>
                                    <td class="match-detail-conditions-table__col match-detail-conditions-table__col--mine"><?php echo esc_html($compare_my_place); ?></td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <span class="match-detail-conditions-table__row-label">
                                            <span class="match-detail-conditions-table__row-icon" aria-hidden="true">
                                                <img src="<?php echo esc_url($match_detail_icon_base . 'group.svg'); ?>" alt="" width="18" height="18" decoding="async">
                                            </span>
                                            <span class="match-detail-conditions-table__row-text">性別</span>
                                        </span>
                                    </th>
                                    <td class="match-detail-conditions-table__col match-detail-conditions-table__col--other"><?php echo esc_html($compare_other_gender); ?></td>
                                    <td class="match-detail-conditions-table__col match-detail-conditions-table__col--mine"><?php echo esc_html($compare_my_gender); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                </div>
            </section>

            <?php if ($show_place_choice || $show_gender_choice): ?>
            <!-- ④ 未確定条件のみ選択UI（ピルのみ・CTA上のプルダウンは出さない） -->
            <div class="match-detail-select-card">
            <div class="match-select-ui">
                <?php if ($show_place_choice): ?>
                <div class="match-select-row">
                    <span class="match-select-label">会場を選択</span>
                    <div class="match-select-pills">
                        <button type="button" class="match-pill match-pill-place <?php echo $resolved_place_value === 'home' ? 'selected' : ''; ?>" data-type="place" data-value="home" <?php echo $lock_pill_selection ? 'disabled aria-disabled="true"' : ''; ?>>ホーム</button>
                        <button type="button" class="match-pill match-pill-place <?php echo $resolved_place_value === 'away' ? 'selected' : ''; ?>" data-type="place" data-value="away" <?php echo $lock_pill_selection ? 'disabled aria-disabled="true"' : ''; ?>>アウェイ</button>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($show_gender_choice): ?>
                <div class="match-select-row">
                    <span class="match-select-label">性別を選択</span>
                    <div class="match-select-pills">
                        <?php foreach ($gender_options as $go): ?>
                        <button type="button" class="match-pill match-pill-gender <?php echo ($resolved_gender_value === $go) ? 'selected' : ''; ?>" data-type="gender" data-value="<?php echo esc_attr($go); ?>" <?php echo $lock_pill_selection ? 'disabled aria-disabled="true"' : ''; ?>><?php echo esc_html(jp_gender($go)); ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            </div>
            <?php endif; ?>
            <?php if ($status !== 'established' && !empty($time_range_start_str) && !empty($time_range_end_str)): ?>
            <div class="match-detail-time-display-only visually-hidden" data-overlap-start="<?php echo esc_attr($time_range_start_str); ?>" data-overlap-end="<?php echo esc_attr($time_range_end_str); ?>"></div>
            <?php endif; ?>

            <?php if ($status !== 'established'): ?>
            <?php if ($application_status === 'reconfirm_required'): ?>
            <?php
            $reconfirm_rows = (!empty($reconfirm_diff['rows']) && is_array($reconfirm_diff['rows'])) ? $reconfirm_diff['rows'] : [];
            $reconfirm_lead = !empty($is_applicant)
                ? 'あなたが申請したあとに、募集スケジュールの条件が次のように更新されました。以下は募集側の条件の変化です（申請内容そのものが自動で書き換わったわけではありません）。'
                : '募集を変更した結果、保留中の申請に再確認が必要です。以下は募集側の条件の変化です。';
            ?>
            <section class="match-detail-reconfirm-recruit match-detail-reconfirm-recruit--panel" aria-labelledby="match-detail-reconfirm-recruit-title">
                <h2 id="match-detail-reconfirm-recruit-title" class="match-detail-reconfirm-recruit__title"><?php echo esc_html('募集側の条件の変更'); ?></h2>
                <p class="match-detail-reconfirm-recruit__lead"><?php echo esc_html($reconfirm_lead); ?></p>
                <p class="match-detail-reconfirm-recruit__note">
                    <?php echo esc_html('募集条件が変更されたため、申請内容の再確認が必要です。現在の条件で再申請をご検討ください。'); ?>
                </p>
                <?php if (!empty($reconfirm_rows)): ?>
                <div class="match-detail-reconfirm-diff is-received-request" role="region" aria-label="<?php echo esc_attr('募集条件の変更内容'); ?>">
                    <?php foreach ($reconfirm_rows as $rd): ?>
                        <?php
                        if (!is_array($rd) || empty($rd['label'])) {
                            continue;
                        }
                        $rd_before = isset($rd['before']) ? (string) $rd['before'] : '';
                        $rd_after = isset($rd['after']) ? (string) $rd['after'] : '';
                        ?>
                    <div class="match-detail-reconfirm-diff__row">
                        <div class="match-detail-reconfirm-diff__label"><?php echo esc_html((string) $rd['label']); ?></div>
                        <div class="match-detail-reconfirm-diff__compare">
                            <div class="match-detail-reconfirm-diff__cell match-detail-reconfirm-diff__cell--before">
                                <span class="match-detail-reconfirm-diff__meta"><?php echo esc_html('変更前'); ?></span>
                                <span class="match-detail-reconfirm-diff__value"><?php echo esc_html($rd_before); ?></span>
                            </div>
                            <span class="match-detail-reconfirm-diff__arrow" aria-hidden="true"><?php echo aidunite_render_theme_icon('arrow_forward', ['width' => '18', 'height' => '18']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                            <div class="match-detail-reconfirm-diff__cell match-detail-reconfirm-diff__cell--after">
                                <span class="match-detail-reconfirm-diff__meta"><?php echo esc_html('変更後'); ?></span>
                                <span class="match-detail-reconfirm-diff__value"><?php echo esc_html($rd_after); ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php elseif (!empty($reconfirm_diff['messages']) && is_array($reconfirm_diff['messages'])): ?>
                <div class="match-detail-reconfirm-diff match-detail-reconfirm-diff--plain is-received-request" role="region" aria-label="<?php echo esc_attr('募集条件の変更内容'); ?>">
                    <?php foreach ($reconfirm_diff['messages'] as $diff_message): ?>
                    <div class="match-detail-reconfirm-diff__plain-line"><?php echo esc_html((string) $diff_message); ?></div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </section>
            <?php endif; ?>
            <?php endif; ?>

            <?php if ($is_received_highlight && $status !== 'established'): ?>
            <!-- 受信側：承認 / 拒否（カードなし） -->
            <section class="match-actions match-detail-received-actions" aria-label="<?php echo esc_attr('申請への操作'); ?>">
                <?php if ($application_status === 'received' && $is_received_request): ?>
                <button type="button" class="apply-button approve-button btn btn-primary" id="approveButton" data-testid="match-approve-button" data-request-id="<?php echo $received_request_id; ?>">承認</button>
                <button type="button" class="apply-button reject-button btn btn-danger" id="rejectButton" data-testid="match-reject-button" data-request-id="<?php echo $received_request_id; ?>">拒否</button>
                <?php endif; ?>
            </section>
            <?php else: ?>
            <!-- 申請側：CTA（既存ボタン・ID維持） -->
            <section class="match-actions match-detail-apply-cta" aria-label="<?php echo esc_attr('申請への操作'); ?>">
                <?php if ($application_status === 'reconfirm_required'): ?>
                    <?php if (!empty($is_applicant)): ?>
                        <button type="button" class="apply-button btn btn-primary" id="reapplyButton" data-testid="match-reapply-button" data-default-place="<?php echo esc_attr($resolved_place_value ?? 'home'); ?>" data-default-gender="<?php echo esc_attr($resolved_gender_value ?? 'both'); ?>">新しい条件で再申請する</button>
                    <?php else: ?>
                        <button type="button" class="apply-button btn btn-primary" id="proposalButton" data-request-id="<?php echo $latest_request ? (int) $latest_request->ID : 0; ?>">最新条件を提案する</button>
                        <button type="button" class="apply-button approve-button btn btn-primary" disabled aria-disabled="true">再申請待ち</button>
                    <?php endif; ?>
                <?php elseif ($application_status === 'proposal_pending_accept'): ?>
                    <?php if (!empty($is_applicant)): ?>
                        <button type="button" class="apply-button btn btn-primary" id="acceptProposalButton" data-request-id="<?php echo $latest_request ? (int) $latest_request->ID : 0; ?>">提案を承諾する</button>
                    <?php else: ?>
                        <button type="button" class="apply-button approve-button btn btn-primary" disabled aria-disabled="true">相手チームの承諾待ちです</button>
                    <?php endif; ?>
                <?php elseif ($application_status === 'applying'): ?>
                    <button type="button" class="apply-button cancel-button btn btn-danger" id="cancelButton" data-testid="match-cancel-button" data-request-id="<?php echo $latest_request ? $latest_request->ID : ''; ?>">キャンセル</button>
                <?php elseif ($match_detail_display_mode === 'established'): ?>
                    <?php
                    $hide_established_chat = !empty($latest_request)
                        && function_exists('aidunite_onboarding_bot_should_suppress_chat_cta')
                        && aidunite_onboarding_bot_should_suppress_chat_cta((int) $latest_request->ID);
                    ?>
                    <?php if ($established_chat_url !== '' && !$hide_established_chat): ?>
                    <a href="<?php echo esc_url($established_chat_url); ?>" class="apply-button btn btn-primary" id="openChatButton" data-testid="match-chat-button">チャット</a>
                    <?php endif; ?>
                    <button type="button" class="apply-button cancel-button btn btn-danger" id="cancelButton" data-testid="match-cancel-button" data-request-id="<?php echo $latest_request ? $latest_request->ID : ''; ?>">キャンセル</button>
                <?php elseif (in_array($application_status, ['rejected', 'canceled'])): ?>
                    <?php if (($reapply_cta['variant'] ?? '') === 'ineligible'): ?>
                        <p class="match-reapply-ineligible-note" role="status"><?php echo esc_html($reapply_cta['note'] ?? ''); ?></p>
                    <?php else: ?>
                        <?php if (($reapply_cta['variant'] ?? '') === 'new' && !empty($reapply_cta['note'])): ?>
                            <p class="match-reapply-condition-note"><?php echo esc_html($reapply_cta['note']); ?></p>
                        <?php endif; ?>
                        <button type="button" class="apply-button btn btn-primary" id="reapplyButton" data-testid="match-reapply-button" data-default-place="<?php echo esc_attr($resolved_place_value ?? 'home'); ?>" data-default-gender="<?php echo esc_attr($resolved_gender_value ?? 'both'); ?>"><?php echo esc_html($reapply_cta['label'] ?? '再申請する'); ?></button>
                    <?php endif; ?>
                <?php elseif ($has_undecided_ui): ?>
                    <button type="button" class="apply-button btn btn-primary" id="applyButton">調整して申請</button>
                <?php elseif ($status === 'no_preference'): ?>
                    <button type="button" class="apply-button btn btn-primary" id="applyButton">この募集に申請（仮スケ作成）</button>
                <?php else: ?>
                    <button type="button" class="apply-button btn btn-primary" id="applyButton">この条件で申請</button>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            </div>
            <?php
            $match_detail_venue_label = isset($compare_other_place) ? (string) $compare_other_place : '';
            $match_detail_common_context = [
                'my_schedule_data'    => isset($my_schedule_data) && is_array($my_schedule_data) ? $my_schedule_data : [],
                'other_schedule_data' => isset($other_schedule_data) && is_array($other_schedule_data) ? $other_schedule_data : [],
            ];
            ?>
            <aside class="match-detail-sidebar" aria-label="<?php echo esc_attr('相手チーム情報'); ?>">
                <?php include get_stylesheet_directory() . '/template-parts/match-detail-opponent-sidebar.php'; ?>
            </aside>
            <div class="match-detail-flow-wrap">
                <?php include get_stylesheet_directory() . '/template-parts/match-detail-flow.php'; ?>
            </div>

            <?php if (($application_status === 'accepted' || $application_status === 'established') && (empty($match_detail_display_mode) || $match_detail_display_mode !== 'established')): ?>
             <div class="status-message" style="color: var(--success-color); font-weight: bold; padding: 10px; text-align: center; margin-bottom: var(--spacing-base);">
                 この申請は承認されました
             </div>
             <?php if ($confirmed_data): ?>
             <div class="confirmed-details" style="background: var(--bg-secondary); border: 1px solid var(--border-light); border-radius: var(--radius-base); padding: var(--spacing-base); margin: var(--spacing-sm) 0;">
                 <h4 style="margin: 0 0 var(--spacing-sm) 0; color: var(--text-primary);">確定した試合条件</h4>
                 <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 14px;">
                     <div><strong>時間:</strong> <?php echo esc_html($confirmed_data['start_time'] . '～' . $confirmed_data['end_time']); ?>（確定）</div>
                     <div><strong>会場:</strong> <?php echo jp_place($confirmed_data['place']); ?>（確定）</div>
                     <div><strong>性別:</strong> <?php echo jp_gender($confirmed_data['gender']); ?>（確定）</div>
                 </div>
             </div>
             <?php endif; ?>

             <!-- 試合当日までのチェックリスト -->
             <div class="match-checklist" style="background: rgba(255, 193, 7, 0.1); border: 1px solid var(--warning-color); border-radius: var(--radius-base); padding: var(--spacing-base); margin: var(--spacing-base) 0;">
                 <h4 style="margin: 0 0 var(--spacing-base) 0; color: var(--warning-color);">試合当日までのチェックリスト</h4>
                 <div id="matchChecklist" style="color: var(--warning-color);">
                     <?php
                     $checklist_key = 'match_checklist_' . ($latest_request ? $latest_request->ID : $my_schedule_id . '_' . $other_schedule_id);
                     $checklist_data = get_user_meta(get_current_user_id(), $checklist_key, true);
                     $checklist_items = [
                         ['id' => 'match_confirmed', 'label' => 'マッチ成立', 'checked' => true],
                         ['id' => 'venue_info', 'label' => '会場情報の確認', 'checked' => isset($checklist_data['venue_info']) ? $checklist_data['venue_info'] : false],
                         ['id' => 'meeting_time', 'label' => '集合時間の確認', 'checked' => isset($checklist_data['meeting_time']) ? $checklist_data['meeting_time'] : false],
                         ['id' => 'equipment', 'label' => '持ち物の確認', 'checked' => isset($checklist_data['equipment']) ? $checklist_data['equipment'] : false],
                         ['id' => 'final_check', 'label' => '試合前日の最終確認', 'checked' => isset($checklist_data['final_check']) ? $checklist_data['final_check'] : false],
                     ];
                     foreach ($checklist_items as $item):
                     ?>
                     <label style="display: flex; align-items: center; margin-bottom: 10px; cursor: pointer; font-size: 14px;">
                         <input type="checkbox"
                                class="checklist-item"
                                data-item-id="<?php echo esc_attr($item['id']); ?>"
                                data-checklist-key="<?php echo esc_attr($checklist_key); ?>"
                                <?php echo $item['checked'] ? 'checked' : ''; ?>
                                <?php echo $item['id'] === 'match_confirmed' ? 'disabled' : ''; ?>
                                style="margin-right: 8px; width: 18px; height: 18px; cursor: pointer;">
                         <span><?php echo esc_html($item['label']); ?></span>
                     </label>
                     <?php endforeach; ?>
                 </div>
             </div>

             <!-- 申請完了後は調整部分を固定表示 -->
             <div class="fixed-conditions" style="background: linear-gradient(135deg, rgba(40, 167, 69, 0.1) 0%, rgba(40, 167, 69, 0.15) 100%); border: 3px solid var(--success-color); border-radius: var(--radius-base); padding: 20px; margin: 15px 0; box-shadow: 0 4px 12px rgba(40, 167, 69, 0.2);">
                 <h4 style="margin: 0 0 var(--spacing-lg) 0; color: var(--success-color); text-align: center; font-size: 1.3rem; font-weight: bold;">確定した試合条件</h4>

                 <!-- 時間調整表示 -->
                 <div class="adjustment-card time-card" style="background: var(--bg-primary); border: 2px solid var(--success-color); border-radius: var(--radius-small); padding: 15px; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(40, 167, 69, 0.15);">
                     <div class="card-header" style="margin-bottom: 10px;">
                         <div class="card-title" style="font-weight: bold; color: var(--success-color);">時間（確定）</div>
                     </div>
                     <div class="time-comparison" style="display: flex; gap: var(--spacing-base); margin-bottom: var(--spacing-sm);">
                         <div class="team-time-card my-team" style="flex: 1; text-align: center; padding: var(--spacing-xs); background: var(--bg-secondary); border-radius: var(--radius-small); opacity: 0.6;">
                             <div class="team-label" style="font-size: 11px; color: var(--text-secondary); margin-bottom: var(--spacing-xs);">自チーム</div>
                             <div class="time-range" style="font-size: 0.9rem;"><?php echo esc_html($my_schedule_data['start'] . '～' . $my_schedule_data['end']); ?></div>
                         </div>
                         <div class="team-time-card other-team" style="flex: 1; text-align: center; padding: var(--spacing-sm); background: linear-gradient(135deg, rgba(40, 167, 69, 0.1) 0%, rgba(40, 167, 69, 0.15) 100%); border: 2px solid var(--success-color); border-radius: var(--radius-small);">
                             <div class="team-label" style="font-size: 12px; color: var(--success-color); margin-bottom: var(--spacing-xs); font-weight: 600;">相手チーム</div>
                             <div class="time-range" style="font-weight: bold; font-size: 1.1rem; color: var(--success-color);"><?php echo esc_html($other_schedule_data['start'] . '～' . $other_schedule_data['end']); ?></div>
                         </div>
                     </div>
                     <div style="background: linear-gradient(135deg, rgba(40, 167, 69, 0.1) 0%, rgba(40, 167, 69, 0.15) 100%); border: 2px solid var(--success-color); border-radius: var(--radius-small); padding: var(--spacing-sm);">
                         <div style="font-weight: bold; color: var(--success-color); margin-bottom: var(--spacing-xs); font-size: 1rem;">
                             確定: <?php echo esc_html($confirmed_data['start_time'] . '～' . $confirmed_data['end_time']); ?>
                         </div>
                     </div>
                 </div>

                 <!-- 会場調整表示 -->
                 <div class="adjustment-card venue-card" style="background: var(--bg-primary); border: 2px solid var(--success-color); border-radius: var(--radius-small); padding: 15px; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(40, 167, 69, 0.15);">
                     <div class="card-header" style="margin-bottom: 10px;">
                         <div class="card-title" style="font-weight: bold; color: var(--success-color);">会場（確定）</div>
                     </div>
                     <div class="venue-comparison" style="display: flex; gap: var(--spacing-base); margin-bottom: var(--spacing-sm);">
                         <div class="team-venue-card my-team" style="flex: 1; text-align: center; padding: var(--spacing-xs); background: var(--bg-secondary); border-radius: var(--radius-small); opacity: 0.6;">
                             <div class="team-label" style="font-size: 11px; color: var(--text-secondary); margin-bottom: var(--spacing-xs);">自チーム</div>
                             <div class="venue-value" style="font-size: 0.9rem;"><?php echo jp_place($my_place); ?></div>
                         </div>
                         <div class="team-venue-card other-team" style="flex: 1; text-align: center; padding: var(--spacing-sm); background: linear-gradient(135deg, rgba(40, 167, 69, 0.1) 0%, rgba(40, 167, 69, 0.15) 100%); border: 2px solid var(--success-color); border-radius: var(--radius-small);">
                             <div class="team-label" style="font-size: 12px; color: var(--success-color); margin-bottom: var(--spacing-xs); font-weight: 600;">相手チーム</div>
                             <div class="venue-value" style="font-weight: bold; font-size: 1.1rem; color: var(--success-color);"><?php echo !empty($is_guest_invite) && isset($other_place_disp_guest) ? esc_html($other_place_disp_guest) : esc_html(jp_place($other_place)); ?></div>
                         </div>
                     </div>
                     <div style="background: linear-gradient(135deg, rgba(40, 167, 69, 0.1) 0%, rgba(40, 167, 69, 0.15) 100%); border: 2px solid var(--success-color); border-radius: var(--radius-small); padding: var(--spacing-sm);">
                         <div style="font-weight: bold; color: var(--success-color); margin-bottom: var(--spacing-xs); font-size: 1rem;">
                             確定: <?php echo jp_place($confirmed_data['place']); ?>
                         </div>
                     </div>
                 </div>

                 <!-- 性別調整表示 -->
                 <div class="adjustment-card gender-card" style="background: var(--bg-primary); border: 2px solid var(--success-color); border-radius: var(--radius-small); padding: 15px; box-shadow: 0 2px 8px rgba(40, 167, 69, 0.15);">
                     <div class="card-header" style="margin-bottom: 10px;">
                         <div class="card-title" style="font-weight: bold; color: var(--success-color);">性別（確定）</div>
                     </div>
                     <div class="gender-comparison" style="display: flex; gap: var(--spacing-base); margin-bottom: var(--spacing-sm);">
                         <div class="team-gender-card my-team" style="flex: 1; text-align: center; padding: var(--spacing-xs); background: var(--bg-secondary); border-radius: var(--radius-small); opacity: 0.6;">
                             <div class="team-label" style="font-size: 11px; color: var(--text-secondary); margin-bottom: var(--spacing-xs);">自チーム</div>
                             <div class="gender-value" style="font-size: 0.9rem;"><?php echo jp_gender($my_gender); ?></div>
                         </div>
                         <div class="team-gender-card other-team" style="flex: 1; text-align: center; padding: var(--spacing-sm); background: linear-gradient(135deg, rgba(40, 167, 69, 0.1) 0%, rgba(40, 167, 69, 0.15) 100%); border: 2px solid var(--success-color); border-radius: var(--radius-small);">
                             <div class="team-label" style="font-size: 12px; color: var(--success-color); margin-bottom: var(--spacing-xs); font-weight: 600;">相手チーム</div>
                             <div class="gender-value" style="font-weight: bold; font-size: 1.1rem; color: var(--success-color);"><?php echo jp_gender($other_gender); ?></div>
                         </div>
                     </div>
                     <div style="background: linear-gradient(135deg, rgba(40, 167, 69, 0.1) 0%, rgba(40, 167, 69, 0.15) 100%); border: 2px solid var(--success-color); border-radius: var(--radius-small); padding: var(--spacing-sm);">
                         <div style="font-weight: bold; color: var(--success-color); margin-bottom: var(--spacing-xs); font-size: 1rem;">
                             確定: <?php echo jp_gender($confirmed_data['gender']); ?>
                         </div>
                     </div>
                 </div>
             </div>
            <?php endif; ?>
            <?php endif; // end !$is_other_only_mode ?>
    </main>
    </div>

<?php
if ($match_detail_shell_opened && function_exists('aidunite_web_app_page_shell_close')) {
    aidunite_web_app_page_shell_close();
} else {
    echo '</div>';
}
?>

<script>
// ※ 旧仕様の折りたたみ（teamInfoContent/teamInfoToggle, adjustmentExplanation 等）はHTMLに存在しないため削除済み。

document.addEventListener('DOMContentLoaded', function() {
    // 確認: 時間選択UIは start-time-select-detail / end-time-select-detail の1系統のみ（旧IDはHTMLに存在しないため廃止済み）
    const startTimeSelectDetail = document.getElementById('start-time-select-detail');
    const endTimeSelectDetail = document.getElementById('end-time-select-detail');
    const selectedTimeRangeDetail = document.getElementById('selected-time-range-detail');

    // 時間選択UIの処理
    if (startTimeSelectDetail && endTimeSelectDetail) {
        const myStart = '<?php echo $my_schedule_data['start']; ?>';
        const myEnd = '<?php echo $my_schedule_data['end']; ?>';
        const otherStart = '<?php echo $other_schedule_data['start']; ?>';
        const otherEnd = '<?php echo $other_schedule_data['end']; ?>';

        // 時間を分に変換
        function timeToMinutes(timeStr) {
            if (!timeStr) return NaN;
            const parts = String(timeStr).split(':').map(Number);
            const hours = isFinite(parts[0]) ? parts[0] : NaN;
            const minutes = isFinite(parts[1]) ? parts[1] : 0;
            return hours * 60 + minutes;
        }

        function minutesToTime(minutes) {
            const hours = Math.floor(minutes / 60);
            const mins = minutes % 60;
            return String(hours).padStart(2, '0') + ':' + String(mins).padStart(2, '0');
        }

        const myStartMin = timeToMinutes(myStart);
        const myEndMin = timeToMinutes(myEnd);
        const otherStartMin = timeToMinutes(otherStart);
        const otherEndMin = timeToMinutes(otherEnd);

        // 利用可能な時間範囲を計算（双方の重複範囲：積集合＝最大開始〜最小終了）
        const overlapStart = Math.max(myStartMin, otherStartMin);
        const overlapEnd = Math.min(myEndMin, otherEndMin);

        // 30分単位で時間オプションを生成
        function generateTimeOptions(startMin, endMin) {
            const options = [];
            if (isFinite(startMin) && isFinite(endMin) && startMin < endMin) {
                for (let minutes = startMin; minutes <= endMin; minutes += 30) {
                    const timeStr = minutesToTime(minutes);
                    options.push(`<option value="${timeStr}">${timeStr}</option>`);
                }
            }
            return options.join('');
        }

        // 開始・終了セレクトを生成（重複範囲内のみ選択可能）
        const startOpts = generateTimeOptions(overlapStart, overlapEnd - 30);
        const endOpts = generateTimeOptions(overlapStart + 30, overlapEnd);
        if (startOpts && endOpts) {
            startTimeSelectDetail.innerHTML = '<option value="">選択してください</option>' + startOpts;
            endTimeSelectDetail.innerHTML = '<option value="">選択してください</option>' + endOpts;
            // デフォルトは重複範囲の開始〜終了
            const defStart = minutesToTime(overlapStart);
            const defEnd = minutesToTime(overlapEnd);
            if ([...startTimeSelectDetail.options].some(o => o.value === defStart)) startTimeSelectDetail.value = defStart;
            if ([...endTimeSelectDetail.options].some(o => o.value === defEnd)) endTimeSelectDetail.value = defEnd;
            if (selectedTimeRangeDetail) selectedTimeRangeDetail.textContent = `${defStart} ～ ${defEnd}`;
        } else {
            if (startTimeSelectDetail) startTimeSelectDetail.closest('div')?.classList.add('hidden');
            if (endTimeSelectDetail) endTimeSelectDetail.closest('div')?.classList.add('hidden');
            if (selectedTimeRangeDetail) selectedTimeRangeDetail.textContent = '選択可能時間なし（時間が重なっていません）';
        }

        // 時間選択の更新処理
        function updateTimeRangeDetail() {
            const startTime = startTimeSelectDetail.value;
            const endTime = endTimeSelectDetail.value;

            if (startTime && endTime) {
                const startMin = timeToMinutes(startTime);
                const endMin = timeToMinutes(endTime);

                if (startMin < endMin) {
                    if (selectedTimeRangeDetail) selectedTimeRangeDetail.textContent = `${startTime} ～ ${endTime}`;
                } else {
                    if (selectedTimeRangeDetail) selectedTimeRangeDetail.textContent = '終了時間は開始時間より後にしてください';
                }
            } else {
                if (selectedTimeRangeDetail) selectedTimeRangeDetail.textContent = '時間を選択してください';
            }
        }

        // 開始時間変更時の処理（重複範囲内に制限）
        startTimeSelectDetail.addEventListener('change', function() {
            const startTime = this.value;
            const prevEnd = endTimeSelectDetail.value;
            if (startTime) {
                const startMin = timeToMinutes(startTime);
                endTimeSelectDetail.innerHTML = '<option value="">選択してください</option>' +
                    generateTimeOptions(startMin + 30, overlapEnd);
                const prevEndMin = timeToMinutes(prevEnd);
                const minAllowedEnd = startMin + 30;
                if (isFinite(prevEndMin) && prevEndMin >= minAllowedEnd && prevEndMin <= overlapEnd) {
                    if ([...endTimeSelectDetail.options].some(o => o.value === prevEnd)) endTimeSelectDetail.value = prevEnd;
                } else {
                    const adjustedEnd = minutesToTime(Math.min(Math.max(minAllowedEnd, overlapStart + 30), overlapEnd));
                    if ([...endTimeSelectDetail.options].some(o => o.value === adjustedEnd)) endTimeSelectDetail.value = adjustedEnd;
                }
            }
            updateTimeRangeDetail();
        });

        // 終了時間変更時の処理（重複範囲内に制限）
        endTimeSelectDetail.addEventListener('change', function() {
            const endTime = this.value;
            const prevStart = startTimeSelectDetail.value;
            if (endTime) {
                const endMin = timeToMinutes(endTime);
                startTimeSelectDetail.innerHTML = '<option value="">選択してください</option>' +
                    generateTimeOptions(overlapStart, endMin - 30);
                const prevStartMin = timeToMinutes(prevStart);
                const maxAllowedStart = endMin - 30;
                if (isFinite(prevStartMin) && prevStartMin >= overlapStart && prevStartMin <= maxAllowedStart) {
                    if ([...startTimeSelectDetail.options].some(o => o.value === prevStart)) startTimeSelectDetail.value = prevStart;
                } else {
                    const adjustedStart = minutesToTime(Math.min(Math.max(overlapStart, maxAllowedStart), maxAllowedStart));
                    if ([...startTimeSelectDetail.options].some(o => o.value === adjustedStart)) startTimeSelectDetail.value = adjustedStart;
                }
            }
            updateTimeRangeDetail();
        });
    }

    const applyButton = document.getElementById('applyButton');
    const cancelButton = document.getElementById('cancelButton');
    const fullLoader = document.getElementById('fullScreenLoader');
    const loaderText = document.getElementById('loaderText');
    const aiduniteWpRestNonce = '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>';
    const aiduniteMatchRequestUrl = '<?php echo esc_url(rest_url('aidunite/v1/match-request')); ?>';

    // エラー表示関数（アラート廃止）
    function showErrorAnimation(message) {
        console.error('Error:', message);
        const cleanMessage = String(message).replace(/^❌\s*/, '');
        if (typeof showToastNotification !== 'undefined') {
            showToastNotification(cleanMessage, 'error');
        }
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    }

    function updateApplyButton() {
        const needsPlaceAdjustment = <?php echo !empty($needs_place_adjustment) ? 'true' : 'false'; ?>;
        const needsGenderAdjustment = <?php echo (!empty($needs_gender_adjustment) && isset($gender_options) && count($gender_options) > 1) ? 'true' : 'false'; ?>;

        const placeSelected = !needsPlaceAdjustment || document.querySelector('[data-type="place"].selected') !== null;
        const genderSelect = document.querySelector('[name="gender_choice"]');
        const genderSelected = !needsGenderAdjustment || document.querySelector('[data-type="gender"].selected') !== null || (genderSelect && genderSelect.value);
        const canApply = placeSelected && genderSelected;

        if (applyButton) {
            applyButton.disabled = !canApply;
            applyButton.style.opacity = canApply ? '1' : '0.6';
        }
    }

    const genderLabels = { male: '男子', female: '女子', both: '男子・女子可' };
    const placeLabels = { home: 'ホーム', away: 'アウェイ', either: 'どちらでも可', both: 'どちらでも可' };
    document.querySelectorAll('.match-pill').forEach(pill => {
        pill.addEventListener('click', function() {
            if (this.disabled) return;
            const type = this.dataset.type;
            const value = this.dataset.value;
            document.querySelectorAll(`.match-pill[data-type="${type}"]`).forEach(p => p.classList.remove('selected'));
            this.classList.add('selected');
            if (type === 'gender') {
                const summaryEl = document.getElementById('apply-summary-gender');
                if (summaryEl && genderLabels[value]) summaryEl.textContent = genderLabels[value];
                const summaryElOther = document.getElementById('apply-summary-gender-other');
                if (summaryElOther && genderLabels[value]) summaryElOther.textContent = genderLabels[value];
            }
            if (type === 'place') {
                const summaryPlace = document.getElementById('apply-summary-place');
                if (summaryPlace && placeLabels[value]) summaryPlace.textContent = placeLabels[value];
                const summaryPlaceOther = document.getElementById('apply-summary-place-other');
                if (summaryPlaceOther && placeLabels[value]) summaryPlaceOther.textContent = placeLabels[value];
            }
            if (typeof updateApplyButton === 'function') updateApplyButton();
        });
    });

    // 申請ボタンクリック → ローディングスピナー表示 → 申請送信
    if (applyButton) {
        applyButton.addEventListener('click', function() {
            if (this.disabled) return;

            const placeSel = document.querySelector('[data-type="place"].selected');
            const genderSel = document.querySelector('[data-type="gender"].selected');
            const genderChoiceSelect = document.querySelector('[name="gender_choice"]');
            const genderValue = (genderChoiceSelect && genderChoiceSelect.value) ? genderChoiceSelect.value : (genderSel ? genderSel.dataset.value : '<?php echo esc_js($resolved_gender_value ?? 'both'); ?>');
            let placeValue = placeSel ? placeSel.dataset.value : '';
            if (!placeValue) {
                placeValue = '<?php echo esc_js($resolved_place_value ?? "home"); ?>';
            }
            if (placeValue === 'both') {
                placeValue = 'either';
            }
            const activeStartSelect = document.getElementById('start-time-select-detail');
            const activeEndSelect = document.getElementById('end-time-select-detail');

            // 時間選択の検証（時間調整が必要な場合のみ。ワイヤー：🟡では重なり時間使用のためselectなし）
            if (activeStartSelect && activeEndSelect) {
                const startTime = activeStartSelect.value;
                const endTime = activeEndSelect.value;

                if (!startTime || !endTime) {
                    showErrorAnimation('開始時間と終了時間を選択してください。');
                    return;
                }

                if (startTime >= endTime) {
                    showErrorAnimation('終了時間は開始時間より後にしてください。');
                    return;
                }
            }

            // 時間データの取得（ワイヤー：🟡では重なり時間を表示のみ→ここで重なり時間を使用）
            let selectedStartTime = '';
            let selectedEndTime = '';

            const timeDisplayOnly = document.querySelector('.match-detail-time-display-only');
            if (timeDisplayOnly && timeDisplayOnly.dataset.overlapStart && timeDisplayOnly.dataset.overlapEnd) {
                selectedStartTime = timeDisplayOnly.dataset.overlapStart;
                selectedEndTime = timeDisplayOnly.dataset.overlapEnd;
            } else if (activeStartSelect && activeEndSelect && activeStartSelect.value && activeEndSelect.value) {
                selectedStartTime = activeStartSelect.value;
                selectedEndTime = activeEndSelect.value;
            } else {
                const myScheduleStart = '<?php echo esc_js($my_schedule_data['start'] ?? ''); ?>';
                const myScheduleEnd = '<?php echo esc_js($my_schedule_data['end'] ?? ''); ?>';
                const otherScheduleStart = '<?php echo esc_js($other_schedule_data['start'] ?? ''); ?>';
                const otherScheduleEnd = '<?php echo esc_js($other_schedule_data['end'] ?? ''); ?>';
                if (myScheduleStart && myScheduleEnd && otherScheduleStart && otherScheduleEnd) {
                    const overlapStart = myScheduleStart > otherScheduleStart ? myScheduleStart : otherScheduleStart;
                    const overlapEnd = myScheduleEnd < otherScheduleEnd ? myScheduleEnd : otherScheduleEnd;
                    if (overlapStart < overlapEnd) {
                        selectedStartTime = overlapStart;
                        selectedEndTime = overlapEnd;
                    }
                }
                if (!selectedStartTime || !selectedEndTime) {
                    selectedStartTime = myScheduleStart || otherScheduleStart || '';
                    selectedEndTime = myScheduleEnd || otherScheduleEnd || '';
                }

            }

            // 時間データの検証
            if (!selectedStartTime || !selectedEndTime) {
                showErrorAnimation('時間データが取得できませんでした。ページを再読み込みしてください。');
                this.disabled = false;
                this.innerHTML = this.originalHTML || '申請する';
                if (fullLoader) fullLoader.style.display = 'none';
                return;
            }

            const matchRequestBody = {
                my_schedule_id: parseInt('<?php echo esc_js($my_schedule_id); ?>', 10) || 0,
                other_schedule_id: parseInt('<?php echo esc_js($other_schedule_id); ?>', 10) || 0,
                selected_start_time: selectedStartTime,
                selected_end_time: selectedEndTime,
                selected_place: placeValue,
                selected_gender: genderValue
            };

            // ボタンを無効化してローディングスピナーを表示（統一されたローディングスピナーを使用）
            this.disabled = true;
            const originalHTML = this.innerHTML;
            this.innerHTML = '<span class="button-loading-spinner"></span><span>申請中...</span>';
            this.originalHTML = originalHTML;

            // フルスクリーンローダーを表示（スケジュール登録と同じ仕様）
            if (fullLoader && loaderText) {
                loaderText.textContent = '申請中...';
                fullLoader.style.display = 'flex';
            }

            fetch(aiduniteMatchRequestUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': aiduniteWpRestNonce
                },
                body: JSON.stringify(matchRequestBody)
            })
              .then(async r=>{
                  let data = null; let text = '';
                  try { text = await r.text(); data = JSON.parse(text); } catch(e) { /* not json */ }

                  if (r.ok && data && data.success) {
                      // 下書き削除/自動保存停止は、実装が存在する場合のみ実行
                      if (typeof deleteDraft === 'function') {
                          deleteDraft();
                      }
                      if (typeof stopAutoSave === 'function') {
                          stopAutoSave();
                      }

                      // 1秒後にローディングスピナーを非表示（スケジュール登録と同じ仕様）
                      setTimeout(() => {
                          if (fullLoader) {
                              fullLoader.style.display = 'none';
                          }

                          // 完了メッセージを表示
                          const completionMessage = document.getElementById('completion-message');
                          if (completionMessage) {
                              completionMessage.style.display = 'flex';
                          }

                          // 2.5秒後にリダイレクト（データベース反映を待つため1.5秒待機）
                          setTimeout(() => {
                              const url = '<?php echo home_url('/match-board-own/'); ?>#progress-view';
                              const timestamp = Date.now();
                              window.location.replace(`${url}?refresh=${timestamp}`);
                          }, 1500);
                      }, 1000);
                  } else {
                      console.error('match-request REST failed:', r.status, text || data);
                      // エラーメッセージを表示
                      const errorMessage = (data && data.data && data.data.message) || (data && data.message) || '申請に失敗しました';
                      showErrorAnimation(errorMessage);
                      // ローディングスピナーを非表示
                      if (fullLoader) {
                          fullLoader.style.display = 'none';
                      }
                      // ボタンを再有効化
                      this.disabled = false;
                      this.innerHTML = this.originalHTML || '申請する';
                      this.style.background = '';
                  }
              })
              .catch(()=>{
                  // ローディングスピナーを非表示
                  if (fullLoader) {
                      fullLoader.style.display = 'none';
                  }

                  if (typeof showToastNotification !== 'undefined') {
                      showToastNotification('申請に失敗しました。時間をおいてお試しください', 'error');
                  } else {
                      alert('申請に失敗しました。時間をおいてお試しください');
                  }
                  // ボタンを再有効化
                  this.disabled = false;
                  this.innerHTML = this.originalHTML || '申請する';
                  this.style.background = '';
              });
        });
    }

    // 相手のみモード：申請するボタン
    const applyButtonOtherOnly = document.getElementById('applyButtonOtherOnly');
    if (applyButtonOtherOnly) {
        applyButtonOtherOnly.addEventListener('click', function() {
            if (this.disabled) return;
            const form = document.getElementById('applyFormOtherOnly');
            const schedPill = form ? form.querySelector('.match-pill[data-type="my_schedule"].selected') : null;
            const scheduleHidden = form ? form.querySelector('input[name="my_schedule_id"]') : null;
            const myScheduleId = schedPill ? String(schedPill.dataset.value || '') : (scheduleHidden ? scheduleHidden.value : '');
            const otherScheduleId = this.dataset.otherScheduleId || '';
            const otherTeamId = this.dataset.otherTeamId || '';
            if (myScheduleId === '' || !otherScheduleId || !otherTeamId) {
                if (typeof showToastNotification !== 'undefined') showToastNotification('申請に必要な情報がありません。', 'error');
                else alert('申請に必要な情報がありません。');
                return;
            }
            this.disabled = true;
            const originalHTML = this.innerHTML;
            this.innerHTML = '<span class="button-loading-spinner"></span>申請中...';
            this.originalHTML = originalHTML;
            const fullLoader = document.getElementById('fullScreenLoader');
            const loaderText = document.getElementById('loaderText');
            if (fullLoader && loaderText) { loaderText.textContent = '申請中...'; fullLoader.style.display = 'flex'; }

            const placePill = form ? form.querySelector('.match-pill[data-type="place"].selected') : null;
            const placeHidden = form ? form.querySelector('input[name="selected_place"]') : null;
            var selectedPlace = placePill ? placePill.dataset.value : (placeHidden ? placeHidden.value : '<?php echo esc_js($other_schedule_data["place"] ?? "either"); ?>');
            var myPlace = 'either';
            if (schedPill) {
                myPlace = schedPill.getAttribute('data-place') || schedPill.dataset.place || 'either';
            } else if (scheduleHidden) {
                myPlace = scheduleHidden.getAttribute('data-my-place') || 'either';
            }
            // both/either を正規化し、会場確定を「自分優先（自分未定なら相手）」で統一
            selectedPlace = (selectedPlace === 'both') ? 'either' : selectedPlace;
            myPlace = (myPlace === 'both') ? 'either' : myPlace;
            if (myPlace === 'home' || myPlace === 'away') {
                selectedPlace = myPlace;
            } else if (!selectedPlace || selectedPlace === 'either') {
                selectedPlace = 'home';
            }
            if (!selectedPlace || selectedPlace === 'both') {
                selectedPlace = 'home';
            }
            const genderPill = form ? form.querySelector('.match-pill[data-type="gender"].selected') : null;
            const genderHidden = form ? form.querySelector('input[name="selected_gender"]') : null;
            var selectedGender = genderPill ? genderPill.dataset.value : (genderHidden ? genderHidden.value : '<?php echo esc_js($other_schedule_data["gender"] ?? "both"); ?>');
            const matchRequestBodyOther = {
                my_schedule_id: parseInt(myScheduleId, 10) || 0,
                other_schedule_id: parseInt(otherScheduleId, 10) || 0,
                other_team_id: parseInt(otherTeamId, 10) || 0,
                selected_start_time: '<?php echo esc_js($other_schedule_data["start"] ?? ""); ?>',
                selected_end_time: '<?php echo esc_js($other_schedule_data["end"] ?? ""); ?>',
                selected_place: selectedPlace,
                selected_gender: selectedGender
            };

            fetch(aiduniteMatchRequestUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': aiduniteWpRestNonce
                },
                body: JSON.stringify(matchRequestBodyOther)
            })
                .then(function(r) { return r.text().then(function(t) { try { return { ok: r.ok, data: JSON.parse(t) }; } catch(e) { return { ok: r.ok, data: null, raw: t }; } }); })
                .then(function(res) {
                    if (res.ok && res.data && res.data.success) {
                        // 比較モードと同じ：1秒後にローダー非表示 → 完了メッセージ表示 → 1.5秒後にリダイレクト
                        setTimeout(function() {
                            if (fullLoader) fullLoader.style.display = 'none';
                            var completionMessage = document.getElementById('completion-message');
                            if (completionMessage) completionMessage.style.display = 'flex';
                            setTimeout(function() {
                                var url = '<?php echo esc_url(home_url('/match-board-own/')); ?>#progress-view';
                                window.location.replace(url + '?refresh=' + Date.now());
                            }, 1500);
                        }, 1000);
                    } else {
                        if (fullLoader) fullLoader.style.display = 'none';
                        var msg = (res.data && res.data.data && res.data.data.message) || (res.data && res.data.message) || '申請に失敗しました';
                        if (typeof showToastNotification !== 'undefined') showToastNotification(msg, 'error');
                        else alert(msg);
                        applyButtonOtherOnly.disabled = false;
                        applyButtonOtherOnly.innerHTML = applyButtonOtherOnly.originalHTML || originalHTML;
                    }
                })
                .catch(function() {
                    if (fullLoader) fullLoader.style.display = 'none';
                    if (typeof showToastNotification !== 'undefined') showToastNotification('申請に失敗しました。時間をおいてお試しください', 'error');
                    else alert('申請に失敗しました。時間をおいてお試しください');
                    applyButtonOtherOnly.disabled = false;
                    applyButtonOtherOnly.innerHTML = applyButtonOtherOnly.originalHTML || originalHTML;
                });
        });
    }

    // キャンセルボタンの処理
    if (cancelButton) {
        cancelButton.addEventListener('click', function() {
            if (this.disabled) return;

            // ボタンを無効化してローディング表示
            this.disabled = true;
            const originalHTML = this.innerHTML;
            this.innerHTML = '<span class="button-loading-spinner"></span>キャンセル中...';

            // フルスクリーンローダーを表示
            if (fullLoader && loaderText) {
                loaderText.textContent = 'キャンセル中';
                fullLoader.style.display = 'flex';
            }

            // わくわく感を演出するテキスト変更
            const loadingTexts = ['キャンセル中...', '処理中...', '送信中...', '完了間近...'];
            let textIndex = 0;
            const textInterval = setInterval(() => {
                textIndex = (textIndex + 1) % loadingTexts.length;
                this.innerHTML = '<span class="button-loading-spinner"></span>' + loadingTexts[textIndex];
                if (loaderText) {
                    loaderText.textContent = loadingTexts[textIndex];
                }
            }, 800);

            // テキスト変更を停止するためのタイマーIDと元のHTMLを保存
            this.textInterval = textInterval;
            this.originalHTML = originalHTML;

            // ステータス更新APIに統一（旧キャンセル専用APIは使わない）
            const requestId = this.getAttribute('data-request-id');
            if (!requestId) {
                if (this.textInterval) {
                    clearInterval(this.textInterval);
                }
                if (fullLoader) fullLoader.style.display = 'none';
                showErrorAnimation('キャンセル対象の申請IDを取得できませんでした。ページを再読み込みしてください。');
                this.disabled = false;
                this.innerHTML = this.originalHTML || 'キャンセル';
                this.style.background = '';
                return;
            }
            const payload = new FormData();
            payload.append('action','au_update_match_request_status');
            payload.append('security','<?php echo wp_create_nonce('au_match_nonce'); ?>');
            payload.append('request_id', String(requestId));
            payload.append('status', 'canceled');

            fetch('<?php echo esc_url( admin_url('admin-ajax.php') ); ?>', { method:'POST', body: payload })
              .then(async r=>{
                  let data = null; let text = '';
                  try { text = await r.text(); data = JSON.parse(text); } catch(e) { /* not json */ }

                  if (r.ok && data && data.success) {
                      // テキスト変更を停止
                      if (this.textInterval) {
                          clearInterval(this.textInterval);
                      }

                      // 成功時：わくわく感を演出するため少し待ってから完了メッセージを表示
                      setTimeout(() => {
                          this.innerHTML = (typeof AidUniteThemeIcons !== 'undefined' ? AidUniteThemeIcons.html('check_circle', 18) + ' ' : '') + 'キャンセル完了しました！';
                          this.style.background = 'var(--success-color)'; if (loaderText) loaderText.textContent = '完了！';

                          setTimeout(() => {
                              if (fullLoader) fullLoader.style.display = 'none'; window.location.reload();
                          }, 2000);
                      }, 2000);
                  } else {
                      // テキスト変更を停止
                      if (this.textInterval) {
                          clearInterval(this.textInterval);
                      }

                      console.error('Cancel failed:', r.status, text || data);
                      showErrorAnimation('キャンセルに失敗しました。（' + ((data&&data.data&&data.data.message) || (data&&data.message) || r.status) + '）');
                      // ボタンを再有効化
                      this.disabled = false;
                      this.innerHTML = this.originalHTML || 'キャンセル';
                      this.style.background = '';
                  }
              })
              .catch(()=>{
                  // テキスト変更を停止
                  if (this.textInterval) {
                      clearInterval(this.textInterval);
                  }

                  showErrorAnimation('キャンセルに失敗しました。時間をおいてお試しください');
                  // ボタンを再有効化
                  this.disabled = false;
                  this.innerHTML = this.originalHTML || 'キャンセル';
                  this.style.background = '';
              });
        });
    }



    // 承認ボタンの処理
    const approveButton = document.getElementById('approveButton');
    if (approveButton) {
        approveButton.addEventListener('click', function() {
            if (this.disabled) return;
            const requestId = (this.getAttribute('data-request-id') || '').trim();
            if (!requestId) {
                showErrorAnimation('承認対象の申請IDを取得できませんでした。ページを再読み込みしてください。');
                return;
            }

            // ボタンを無効化してローディング表示
            this.disabled = true;
            this.dataset.originalHtml = this.innerHTML;
            this.innerHTML = '<span style="display:inline-block; width:18px; height:18px; border:3px solid rgba(255,255,255,0.3); border-top:3px solid #fff; border-radius:50%; animation:spin 0.8s linear infinite; margin-right:10px;"></span>承認中...';

            // フルスクリーンローダーを表示
            if (fullLoader && loaderText) {
                loaderText.textContent = '承認中';
                fullLoader.style.display = 'flex';
            }

            // テキスト変更
            const loadingTexts = ['承認中...', '処理中...', '送信中...', '完了間近...'];
            let textIndex = 0;
            const textInterval = setInterval(() => {
                textIndex = (textIndex + 1) % loadingTexts.length;
                this.innerHTML = '<span style="display:inline-block; width:18px; height:18px; border:3px solid rgba(255,255,255,0.3); border-top:3px solid #fff; border-radius:50%; animation:spin 0.8s linear infinite; margin-right:10px;"></span>' + loadingTexts[textIndex];
                if (loaderText) {
                    loaderText.textContent = loadingTexts[textIndex];
                }
            }, 800);

            // テキスト変更を停止するためのタイマーIDを保存
            this.textInterval = textInterval;

                updateMatchRequestStatus(requestId, 'accepted', this);
        });
    }

    // 拒否ボタンの処理
    const rejectButton = document.getElementById('rejectButton');
    if (rejectButton) {
        rejectButton.addEventListener('click', function() {
            if (this.disabled) return;
            const requestId = (this.getAttribute('data-request-id') || '').trim();
            if (!requestId) {
                showErrorAnimation('拒否対象の申請IDを取得できませんでした。ページを再読み込みしてください。');
                return;
            }

            // ボタンを無効化してローディング表示
            this.disabled = true;
            this.dataset.originalHtml = this.innerHTML;
            this.innerHTML = '<span style="display:inline-block; width:18px; height:18px; border:3px solid rgba(255,255,255,0.3); border-top:3px solid #fff; border-radius:50%; animation:spin 0.8s linear infinite; margin-right:10px;"></span>拒否中...';

            // フルスクリーンローダーを表示
            if (fullLoader && loaderText) {
                loaderText.textContent = '拒否中';
                fullLoader.style.display = 'flex';
            }

            // テキスト変更
            const loadingTexts = ['拒否中...', '処理中...', '送信中...', '完了間近...'];
            let textIndex = 0;
            const textInterval = setInterval(() => {
                textIndex = (textIndex + 1) % loadingTexts.length;
                this.innerHTML = '<span style="display:inline-block; width:18px; height:18px; border:3px solid rgba(255,255,255,0.3); border-top:3px solid #fff; border-radius:50%; animation:spin 0.8s linear infinite; margin-right:10px;"></span>' + loadingTexts[textIndex];
                if (loaderText) {
                    loaderText.textContent = loadingTexts[textIndex];
                }
            }, 800);

            // テキスト変更を停止するためのタイマーIDを保存
            this.textInterval = textInterval;

                updateMatchRequestStatus(requestId, 'rejected', this);
        });
    }

    // 再申請ボタンの処理
    const reapplyButton = document.getElementById('reapplyButton');
    if (reapplyButton) {
        reapplyButton.addEventListener('click', function() {
            if (this.disabled) return;

            // ボタンを無効化してローディング表示（統一されたクラスを使用）
            this.disabled = true;
            const originalHTML = this.innerHTML;
            this.innerHTML = '<span class="button-loading-spinner"></span><span>再申請中...</span>';
            this.originalHTML = originalHTML;

            // フルスクリーンローダーを表示（スケジュール登録と同じ仕様）
            if (fullLoader && loaderText) {
                loaderText.textContent = '再申請中...';
                fullLoader.style.display = 'flex';
            }

            // 再申請: 重なり時間・会場・性別をフォーム／既定値から取得
            const timeDisplayOnly = document.querySelector('.match-detail-time-display-only');
            const activeStartSelect = document.getElementById('start-time-select-detail');
            const activeEndSelect = document.getElementById('end-time-select-detail');
            const placeSel = document.querySelector('[data-type="place"].selected');
            const genderSel = document.querySelector('[data-type="gender"].selected');
            const genderChoiceSelect = document.querySelector('[name="gender_choice"]');
            const reapplyBtnEl = document.getElementById('reapplyButton');
            const defaultPlaceFromBtn = (reapplyBtnEl && reapplyBtnEl.dataset.defaultPlace) ? reapplyBtnEl.dataset.defaultPlace : '';
            const defaultGenderFromBtn = (reapplyBtnEl && reapplyBtnEl.dataset.defaultGender) ? reapplyBtnEl.dataset.defaultGender : '';
            const reapplyGenderValue = (genderChoiceSelect && genderChoiceSelect.value) ? genderChoiceSelect.value : (genderSel ? genderSel.dataset.value : (defaultGenderFromBtn || '<?php echo esc_js($resolved_gender_value ?? 'both'); ?>'));

            let selectedStartTime = '';
            let selectedEndTime = '';
            if (timeDisplayOnly && timeDisplayOnly.dataset.overlapStart && timeDisplayOnly.dataset.overlapEnd) {
                selectedStartTime = timeDisplayOnly.dataset.overlapStart;
                selectedEndTime = timeDisplayOnly.dataset.overlapEnd;
            } else if (activeStartSelect && activeEndSelect) {
                selectedStartTime = activeStartSelect.value;
                selectedEndTime = activeEndSelect.value;
            }
            let placeValue = placeSel ? placeSel.dataset.value : '';
            if (!placeValue) {
                placeValue = defaultPlaceFromBtn || '<?php echo esc_js($resolved_place_value ?? 'home'); ?>';
            }

            if (activeStartSelect && activeEndSelect) {
                const startTime = activeStartSelect.value;
                const endTime = activeEndSelect.value;
                if (startTime && endTime) {
                    if (startTime >= endTime) {
                        showErrorAnimation('終了時間は開始時間より後にしてください。');
                        this.disabled = false;
                        this.innerHTML = this.originalHTML || '再申請する';
                        this.style.background = '';
                        return;
                    }
                }
            }
            // 申請ボタンと同じフォールバック（再申請でも重なり時間または既存時間を採用）
            if (!selectedStartTime || !selectedEndTime) {
                const myScheduleStart = '<?php echo esc_js($my_schedule_data['start'] ?? ''); ?>';
                const myScheduleEnd = '<?php echo esc_js($my_schedule_data['end'] ?? ''); ?>';
                const otherScheduleStart = '<?php echo esc_js($other_schedule_data['start'] ?? ''); ?>';
                const otherScheduleEnd = '<?php echo esc_js($other_schedule_data['end'] ?? ''); ?>';
                if (myScheduleStart && myScheduleEnd && otherScheduleStart && otherScheduleEnd) {
                    const overlapStart = myScheduleStart > otherScheduleStart ? myScheduleStart : otherScheduleStart;
                    const overlapEnd = myScheduleEnd < otherScheduleEnd ? myScheduleEnd : otherScheduleEnd;
                    if (overlapStart < overlapEnd) {
                        selectedStartTime = overlapStart;
                        selectedEndTime = overlapEnd;
                    }
                }
                if (!selectedStartTime || !selectedEndTime) {
                    selectedStartTime = myScheduleStart || otherScheduleStart || '';
                    selectedEndTime = myScheduleEnd || otherScheduleEnd || '';
                }
            }
            if (!selectedStartTime || !selectedEndTime) {
                showErrorAnimation('時間データが取得できませんでした。');
                this.disabled = false;
                this.innerHTML = this.originalHTML || '再申請する';
                this.style.background = '';
                return;
            }

            const matchRequestBodyReapply = {
                my_schedule_id: parseInt('<?php echo esc_js($my_schedule_id); ?>', 10) || 0,
                other_schedule_id: parseInt('<?php echo esc_js($other_schedule_id); ?>', 10) || 0,
                match_request_id: parseInt('<?php echo $latest_request ? (int) $latest_request->ID : 0; ?>', 10) || 0,
                selected_start_time: selectedStartTime,
                selected_end_time: selectedEndTime,
                selected_place: placeValue,
                selected_gender: reapplyGenderValue
            };

            fetch(aiduniteMatchRequestUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': aiduniteWpRestNonce
                },
                body: JSON.stringify(matchRequestBodyReapply)
            })
            .then(async response => {
                let data = null;
                let text = '';
                try {
                    text = await response.text();
                    data = JSON.parse(text);
                } catch(e) {
                    console.error('JSON parse error:', e);
                }

                if (response.ok && data && data.success) {
                    // 1秒後にローディングスピナーを非表示（スケジュール登録と同じ仕様）
                    setTimeout(() => {
                        if (fullLoader) {
                            fullLoader.style.display = 'none';
                        }

                        // 完了メッセージを表示
                        const completionMessage = document.getElementById('completion-message');
                        if (completionMessage) {
                            completionMessage.style.display = 'flex';
                        }

                        // 2.5秒後にリダイレクト（データベース反映を待つため1.5秒待機）
                        setTimeout(() => {
                            const url = '<?php echo home_url('/match-board-own/'); ?>#progress-view';
                            const timestamp = Date.now();
                            window.location.replace(`${url}?refresh=${timestamp}`);
                        }, 1500);
                    }, 1000);
                } else {
                    console.error('Reapply failed:', response.status, text || data);
                    // エラーメッセージを表示
                    const errorMessage = (data && data.data && data.data.message) || (data && data.message) || '再申請に失敗しました';
                    showErrorAnimation(errorMessage);
                    // ローディングスピナーを非表示
                    if (fullLoader) {
                        fullLoader.style.display = 'none';
                    }
                    // ボタンを再有効化
                    this.disabled = false;
                    this.innerHTML = this.originalHTML || '再申請する';
                    this.style.background = '';
                }
            })
            .catch(error => {
                console.error('Reapply error:', error);
                // ローディングスピナーを非表示
                if (fullLoader) {
                    fullLoader.style.display = 'none';
                }

                showErrorAnimation('再申請に失敗しました。時間をおいてお試しください');
                // ボタンを再有効化
                this.disabled = false;
                this.innerHTML = this.originalHTML || '再申請する';
                this.style.background = '';
            });
        });
    }

    function callMatchAjaxAction(actionName, requestId, actionButton, loadingText) {
        if (!requestId) {
            showErrorAnimation('申請IDを取得できませんでした。ページを再読み込みしてください。');
            return;
        }
        actionButton.disabled = true;
        actionButton.dataset.originalHtml = actionButton.innerHTML;
        actionButton.innerHTML = '<span class="button-loading-spinner"></span><span>' + loadingText + '</span>';
        if (fullLoader && loaderText) {
            loaderText.textContent = loadingText;
            fullLoader.style.display = 'flex';
        }
        const payload = new FormData();
        payload.append('action', actionName);
        payload.append('security', '<?php echo wp_create_nonce('au_match_nonce'); ?>');
        payload.append('request_id', String(requestId));
        fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
            method: 'POST',
            body: payload
        })
        .then(async (response) => {
            let json = null;
            try {
                json = await response.json();
            } catch (e) {}
            if (json && json.success) {
                setTimeout(function() { location.reload(); }, 500);
                return;
            }
            const err = (json && json.data && (json.data.message || json.data)) || (json && json.message) || '処理に失敗しました';
            showErrorAnimation(String(err));
            if (fullLoader) fullLoader.style.display = 'none';
            actionButton.disabled = false;
            actionButton.innerHTML = actionButton.dataset.originalHtml || '実行';
        })
        .catch(() => {
            showErrorAnimation('処理に失敗しました');
            if (fullLoader) fullLoader.style.display = 'none';
            actionButton.disabled = false;
            actionButton.innerHTML = actionButton.dataset.originalHtml || '実行';
        });
    }

    const proposalButton = document.getElementById('proposalButton');
    if (proposalButton) {
        proposalButton.addEventListener('click', function() {
            const requestId = (this.getAttribute('data-request-id') || '').trim();
            callMatchAjaxAction('au_propose_reconfirm_conditions', requestId, this, '提案中...');
        });
    }

    const acceptProposalButton = document.getElementById('acceptProposalButton');
    if (acceptProposalButton) {
        acceptProposalButton.addEventListener('click', function() {
            const requestId = (this.getAttribute('data-request-id') || '').trim();
            callMatchAjaxAction('au_accept_reconfirm_proposal', requestId, this, '承諾中...');
        });
    }

    function showChatRedirectModal() {
        return new Promise(function(resolve) {
            const modal = document.getElementById('chat-redirect-modal');
            const goBtn = document.getElementById('chat-redirect-go-btn');
            const stayBtn = document.getElementById('chat-redirect-stay-btn');

            if (!modal || !goBtn || !stayBtn) {
                resolve(false);
                return;
            }

            const close = function(goToChat) {
                modal.style.display = 'none';
                modal.setAttribute('aria-hidden', 'true');
                goBtn.removeEventListener('click', onGo);
                stayBtn.removeEventListener('click', onStay);
                modal.removeEventListener('click', onBackdrop);
                resolve(goToChat);
            };
            const onGo = function() { close(true); };
            const onStay = function() { close(false); };
            const onBackdrop = function(e) {
                if (e.target === modal) {
                    close(false);
                }
            };

            goBtn.addEventListener('click', onGo);
            stayBtn.addEventListener('click', onStay);
            modal.addEventListener('click', onBackdrop);
            modal.style.display = 'flex';
            modal.setAttribute('aria-hidden', 'false');
        });
    }

    function updateMatchRequestStatus(requestId, status, actionButton = null) {
        const requestKey = String(requestId) + ':' + String(status);
        window.__aiduniteMatchStatusInFlight = window.__aiduniteMatchStatusInFlight || {};
        if (window.__aiduniteMatchStatusInFlight[requestKey]) {
            return;
        }
        window.__aiduniteMatchStatusInFlight[requestKey] = true;

        const restoreActionButton = () => {
            if (!actionButton) return;
            if (actionButton.textInterval) {
                clearInterval(actionButton.textInterval);
                actionButton.textInterval = null;
            }
            actionButton.disabled = false;
            if (actionButton.dataset && actionButton.dataset.originalHtml) {
                actionButton.innerHTML = actionButton.dataset.originalHtml;
            }
        };

        // 実際の処理実行
        const payload = new FormData();
        payload.append('action', 'au_update_match_request_status');
        payload.append('security', '<?php echo wp_create_nonce('au_match_nonce'); ?>');
        payload.append('request_id', requestId);
        payload.append('status', status);

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: payload
        })
        .then(r => r.json())
        .then(async json => {
            if (json && json.success) {
                const data = json.data || {};
                const isAccepted = status === 'accepted';

                if (isAccepted && data.show_onboarding_bot_chat_modal) {
                    if (typeof showToastNotification !== 'undefined') {
                        showToastNotification('試合が成立しました。', 'success');
                    }
                    if (typeof window.aiduniteShowOnboardingBotChatModal === 'function') {
                        window.aiduniteShowOnboardingBotChatModal(data.chat_url || '');
                        return;
                    }
                }

                const chatRedirectUrl = (typeof data.redirect_url === 'string') ? data.redirect_url : '';

                if (isAccepted && chatRedirectUrl) {
                    if (typeof showToastNotification !== 'undefined' && !window.__aiduniteApprovalToastShown) {
                        window.__aiduniteApprovalToastShown = true;
                        showToastNotification('試合成立おめでとうございます。専用チャットへ移動できます。', 'success');
                    }
                    const goToChat = await showChatRedirectModal();
                    if (goToChat) {
                        window.location.href = chatRedirectUrl;
                        return;
                    }
                }

                setTimeout(function() { location.reload(); }, 800);
            } else {
                window.__aiduniteMatchStatusInFlight[requestKey] = false;
                if (fullLoader) fullLoader.style.display = 'none';
                restoreActionButton();
                const err = (json && json.data && (json.data.message || json.data)) || (json && json.message) || '更新に失敗しました';
                showErrorAnimation(String(err));
            }
        })
        .catch(() => {
            window.__aiduniteMatchStatusInFlight[requestKey] = false;
            if (fullLoader) fullLoader.style.display = 'none';
            restoreActionButton();
            showErrorAnimation('更新に失敗しました');
        });
    }

    updateApplyButton();

    // チェックリストの保存機能
    const checklistItems = document.querySelectorAll('.checklist-item');
    checklistItems.forEach(item => {
        item.addEventListener('change', function() {
            const itemId = this.getAttribute('data-item-id');
            const checklistKey = this.getAttribute('data-checklist-key');
            const isChecked = this.checked;

            // LocalStorageに保存
            let checklistData = JSON.parse(localStorage.getItem(checklistKey) || '{}');
            checklistData[itemId] = isChecked;
            localStorage.setItem(checklistKey, JSON.stringify(checklistData));

            // サーバーにも保存（オプション）
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'aidunite_save_checklist',
                    checklist_key: checklistKey,
                    item_id: itemId,
                    checked: isChecked ? '1' : '0',
                    security: '<?php echo wp_create_nonce('aidunite_checklist_nonce'); ?>'
                })
            }).catch(error => {
                console.error('チェックリストの保存に失敗しました:', error);
            });
        });
    });
});
</script>


<?php
if ($my_team_id > 0 && function_exists('aidunite_enqueue_onboarding_bot_chat_modal_assets')) {
    $detail_bot_chat_url = '';
    if (defined('AIDUNITE_TEAM_META_ONBOARDING_BOT_MR')) {
        $detail_bot_mr = (int) get_post_meta((int) $my_team_id, AIDUNITE_TEAM_META_ONBOARDING_BOT_MR, true);
        if ($detail_bot_mr > 0 && function_exists('aidunite_onboarding_bot_get_chat_url_for_request')) {
            $detail_bot_chat_url = aidunite_onboarding_bot_get_chat_url_for_request($detail_bot_mr);
        }
    }
    $detail_show_bot_modal = function_exists('aidunite_onboarding_bot_should_show_chat_modal_on_mypage')
        && aidunite_onboarding_bot_should_show_chat_modal_on_mypage((int) $my_team_id);
    aidunite_enqueue_onboarding_bot_chat_modal_assets([
        'show_modal' => $detail_show_bot_modal,
        'chat_url'   => $detail_bot_chat_url,
    ]);
    get_template_part('template-parts/onboarding-bot-chat-modal');
}
get_footer();
?>
