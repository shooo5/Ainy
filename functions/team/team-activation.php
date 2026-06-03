<?php
/**
 * 代表者アクティベーション（初回試合募集オンボーディング）
 *
 * @package AidUnite
 * @see docs/spec/account-onboarding.md §5A
 */

if (!defined('ABSPATH')) {
    exit;
}

/** team post_meta: activation stage */
if (!defined('AIDUNITE_TEAM_META_ACTIVATION_STAGE')) {
    define('AIDUNITE_TEAM_META_ACTIVATION_STAGE', 'aidunite_activation_stage');
}

if (!defined('AIDUNITE_TEAM_META_FIRST_RECRUIT_SCHEDULE_ID')) {
    define('AIDUNITE_TEAM_META_FIRST_RECRUIT_SCHEDULE_ID', 'aidunite_first_recruit_schedule_id');
}

if (!defined('AIDUNITE_TEAM_META_ACTIVATION_WIZARD_STEP')) {
    define('AIDUNITE_TEAM_META_ACTIVATION_WIZARD_STEP', 'aidunite_activation_wizard_step');
}

/**
 * アクティベーション段階 ID 一覧（昇順）
 *
 * @return string[]
 */
function aidunite_activation_stage_ids() {
    return [
        'recruit_pending',
        'recruit_published',
        'first_application',
        'first_established',
    ];
}

/**
 * 初回試合募集（トライアル絞り込み）のスケジュール登録 URL
 *
 * @return string
 */
function aidunite_get_activation_recruit_edit_url() {
    return home_url('/schedule-edit/');
}

/**
 * @deprecated 代わりに aidunite_get_activation_recruit_edit_url() を使用
 * @return string
 */
function aidunite_get_first_match_url() {
    return aidunite_get_activation_recruit_edit_url();
}

/**
 * 無料期間（トライアル）中か
 *
 * @param int|null $user_id
 * @return bool
 */
function aidunite_user_is_on_free_trial($user_id = null) {
    $user_id = $user_id === null ? get_current_user_id() : (int) $user_id;
    if ($user_id <= 0) {
        return false;
    }

    if (function_exists('aidunite_get_payment_status') && aidunite_get_payment_status($user_id) === 'trial') {
        return true;
    }

    $team_id = function_exists('aidunite_get_current_team_id')
        ? (int) aidunite_get_current_team_id($user_id)
        : (int) get_user_meta($user_id, 'team_id', true);

    return $team_id > 0 && function_exists('aidunite_is_trial_period') && aidunite_is_trial_period($team_id);
}

/**
 * スケジュール登録を初回ミッション向けに絞るか（代表者・first_established 未満）
 *
 * 無料期間中でも初回成立済み（first_established 以降）の team は通常 UI。
 *
 * @param int|null $user_id
 * @return bool
 */
function aidunite_schedule_edit_is_trial_simplified($user_id = null) {
    $user_id = $user_id === null ? get_current_user_id() : (int) $user_id;
    if ($user_id <= 0) {
        return false;
    }

    if (!function_exists('aidunite_get_effective_user_role')) {
        return false;
    }

    list($role,) = aidunite_get_effective_user_role($user_id);
    if ($role !== 'team_leader') {
        return false;
    }

    $team_id = function_exists('aidunite_get_current_team_id')
        ? (int) aidunite_get_current_team_id($user_id)
        : (int) get_user_meta($user_id, 'team_id', true);

    return $team_id > 0
        && function_exists('aidunite_activation_is_mission_ui')
        && aidunite_activation_is_mission_ui($team_id);
}

/**
 * トライアル recruit 登録成功後にマイページへ戻すか
 *
 * @param int    $team_id
 * @param string $intent
 * @return bool
 */
function aidunite_schedule_edit_should_redirect_to_mypage($team_id, $intent = 'recruit') {
    $team_id = (int) $team_id;
    if ($team_id <= 0 || (string) $intent !== 'recruit') {
        return false;
    }

    return function_exists('aidunite_activation_is_mission_ui') && aidunite_activation_is_mission_ui($team_id);
}

/**
 * 段階の序数（比較用）
 *
 * @param string $stage
 * @return int
 */
function aidunite_activation_stage_rank($stage) {
    $stages = aidunite_activation_stage_ids();
    $idx    = array_search((string) $stage, $stages, true);

    return $idx === false ? -1 : (int) $idx;
}

/**
 * team の recruit schedule（intent=recruit）が1件以上あるか
 *
 * @param int $team_id
 * @return bool
 */
function aidunite_team_has_recruit_schedule($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return false;
    }

    $posts = get_posts([
        'post_type'      => 'schedule',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'   => 'team_id',
                'value' => (string) $team_id,
            ],
            [
                'key'   => 'intent',
                'value' => 'recruit',
            ],
        ],
    ]);

    return !empty($posts);
}

/**
 * team が match_request を1件以上持つか（送受信・終端以外）
 *
 * @param int $team_id
 * @return bool
 */
function aidunite_team_has_match_application($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return false;
    }

    $excluded = function_exists('aidunite_analytics_funnel_mr_excluded_status_meta_values')
        ? aidunite_analytics_funnel_mr_excluded_status_meta_values()
        : ['canceled', 'rejected', 'キャンセル', '却下'];

    $posts = get_posts([
        'post_type'      => 'match_request',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => [
            'relation' => 'OR',
            [
                'key'   => 'from_team_id',
                'value' => (string) $team_id,
            ],
            [
                'key'   => 'to_team_id',
                'value' => (string) $team_id,
            ],
            [
                'key'   => 'other_team_id',
                'value' => (string) $team_id,
            ],
        ],
    ]);

    foreach ($posts as $post_id) {
        $status = (string) get_post_meta((int) $post_id, 'status', true);
        if ($status === '') {
            $status = (string) get_post_meta((int) $post_id, 'request_status', true);
        }
        if (in_array($status, $excluded, true)) {
            continue;
        }
        return true;
    }

    return false;
}

/**
 * team の初回試合成立があるか
 *
 * @param int $team_id
 * @return bool
 */
function aidunite_team_has_established_match($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return false;
    }

    $established = function_exists('aidunite_analytics_funnel_mr_established_status_meta_values')
        ? aidunite_analytics_funnel_mr_established_status_meta_values()
        : ['established', '試合確定'];

    $posts = get_posts([
        'post_type'      => 'match_request',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => [
            'relation' => 'AND',
            [
                'relation' => 'OR',
                [
                    'key'   => 'from_team_id',
                    'value' => (string) $team_id,
                ],
                [
                    'key'   => 'to_team_id',
                    'value' => (string) $team_id,
                ],
            ],
            [
                'key'     => 'status',
                'value'   => $established,
                'compare' => 'IN',
            ],
        ],
    ]);

    return !empty($posts);
}

/**
 * DB 状態から段階を再計算（巻き戻しなし）
 *
 * @param int $team_id
 * @return string
 */
function aidunite_team_activation_resolve_stage($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return 'recruit_pending';
    }

    $stored = (string) get_post_meta($team_id, AIDUNITE_TEAM_META_ACTIVATION_STAGE, true);
    if ($stored === 'first_established') {
        return 'first_established';
    }

    $stage = 'recruit_pending';

    if (aidunite_team_has_recruit_schedule($team_id)) {
        $stage = 'recruit_published';
    }
    if (aidunite_team_has_match_application($team_id)) {
        $stage = 'first_application';
    }
    if (aidunite_team_has_established_match($team_id)) {
        $stage = 'first_established';
    }

    return $stage;
}

/**
 * team の activation stage を取得（必要なら sync）
 *
 * @param int  $team_id
 * @param bool $sync
 * @return string
 */
function aidunite_get_team_activation_stage($team_id, $sync = true) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return 'recruit_pending';
    }

    if ($sync) {
        return aidunite_team_activation_sync($team_id);
    }

    $stored = (string) get_post_meta($team_id, AIDUNITE_TEAM_META_ACTIVATION_STAGE, true);
    if ($stored !== '' && in_array($stored, aidunite_activation_stage_ids(), true)) {
        return $stored;
    }

    return 'recruit_pending';
}

/**
 * 段階を保存
 *
 * first_established からの巻き戻しのみ禁止。
 * sync 再計算では recruit 未公開・MR なし等の場合、recruit_published 以下へ降格可。
 *
 * @param int    $team_id
 * @param string $stage
 * @return string 保存後の段階
 */
function aidunite_set_team_activation_stage($team_id, $stage) {
    $team_id = (int) $team_id;
    $stage   = (string) $stage;

    if ($team_id <= 0 || !in_array($stage, aidunite_activation_stage_ids(), true)) {
        return aidunite_get_team_activation_stage($team_id, false);
    }

    $current = (string) get_post_meta($team_id, AIDUNITE_TEAM_META_ACTIVATION_STAGE, true);
    if (
        $current === 'first_established'
        && aidunite_activation_stage_rank($stage) < aidunite_activation_stage_rank($current)
    ) {
        return $current;
    }

    if ($stage !== $current) {
        update_post_meta($team_id, AIDUNITE_TEAM_META_ACTIVATION_STAGE, $stage);
    }

    return $stage;
}

/**
 * DB から段階を sync して保存
 *
 * @param int $team_id
 * @return string
 */
function aidunite_team_activation_sync($team_id) {
    $resolved = aidunite_team_activation_resolve_stage($team_id);
    $synced_stage = aidunite_set_team_activation_stage($team_id, $resolved);

    return $synced_stage;
}

/**
 * チーム承認直後の初期化
 *
 * @param int $team_id
 */
function aidunite_team_activation_init_on_approval($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return;
    }

    update_post_meta($team_id, AIDUNITE_TEAM_META_ACTIVATION_STAGE, 'recruit_pending');
    update_post_meta($team_id, AIDUNITE_TEAM_META_ACTIVATION_WIZARD_STEP, '1');
    delete_post_meta($team_id, AIDUNITE_TEAM_META_FIRST_RECRUIT_SCHEDULE_ID);
}

/**
 * 初回 recruit 公開時
 *
 * @param int $schedule_id
 * @param int $team_id
 */
function aidunite_team_activation_on_recruit_published($schedule_id, $team_id) {
    $team_id     = (int) $team_id;
    $schedule_id = (int) $schedule_id;
    if ($team_id <= 0) {
        return;
    }

    $first = (int) get_post_meta($team_id, AIDUNITE_TEAM_META_FIRST_RECRUIT_SCHEDULE_ID, true);
    if ($first <= 0 && $schedule_id > 0) {
        update_post_meta($team_id, AIDUNITE_TEAM_META_FIRST_RECRUIT_SCHEDULE_ID, $schedule_id);
    }

    aidunite_set_team_activation_stage($team_id, 'recruit_published');
    update_post_meta($team_id, AIDUNITE_TEAM_META_ACTIVATION_WIZARD_STEP, '3');
}

/**
 * ミッション UI 表示中か（first_established 未満）
 *
 * @param int $team_id
 * @return bool
 */
function aidunite_activation_is_mission_ui($team_id) {
    $stage = aidunite_get_team_activation_stage($team_id);

    return aidunite_activation_stage_rank($stage) < aidunite_activation_stage_rank('first_established');
}

/**
 * チャット解禁済みか
 *
 * @param int $team_id
 * @return bool
 */
function aidunite_activation_is_chat_unlocked($team_id) {
    $stage = aidunite_get_team_activation_stage($team_id);

    return aidunite_activation_stage_rank($stage) >= aidunite_activation_stage_rank('first_established');
}

/**
 * ロック対象スラッグ一覧
 *
 * @return string[]
 */
function aidunite_activation_locked_page_slugs() {
    return [
        'attendance-management',
        'team-members',
        'player-add',
        'invite-guardian',
        'match-analytics',
        'mypage-favorite-teams',
    ];
}

/**
 * ページスラッグがアクティベーションでロックされるか
 *
 * @param string $slug
 * @param int    $team_id
 * @return bool
 */
function aidunite_activation_is_page_locked($slug, $team_id) {
    $slug = (string) $slug;
    if (!in_array($slug, aidunite_activation_locked_page_slugs(), true)) {
        return false;
    }

    if (apply_filters('aidunite_activation_sub_features_unlocked', false, (int) $team_id)) {
        return false;
    }

    return true;
}

/**
 * ロック理由文案
 *
 * @param int $team_id
 * @return string
 */
function aidunite_activation_lock_message($team_id) {
    $stage = aidunite_get_team_activation_stage($team_id);

    if (aidunite_activation_stage_rank($stage) < aidunite_activation_stage_rank('first_established')) {
        return '初回の試合が成立すると利用できます';
    }

    return '試合が落ち着いてから使える機能です';
}

/**
 * ログイン後リダイレクト URL（マイページ固定。F1 ミッションで次アクションを案内）
 *
 * @param WP_User $user
 * @return string
 */
function aidunite_get_post_login_url_for_user($user) {
    unset($user);

    return home_url('/mypage/');
}

/**
 * ウィザード STEP 保存
 *
 * @param int $team_id
 * @param int $step 1-3
 */
function aidunite_team_activation_set_wizard_step($team_id, $step) {
    $step = max(1, min(3, (int) $step));
    update_post_meta((int) $team_id, AIDUNITE_TEAM_META_ACTIVATION_WIZARD_STEP, (string) $step);
}

/**
 * ウィザード STEP 取得
 *
 * @param int $team_id
 * @return int
 */
function aidunite_team_activation_get_wizard_step($team_id) {
    return max(1, min(3, (int) get_post_meta((int) $team_id, AIDUNITE_TEAM_META_ACTIVATION_WIZARD_STEP, true)));
}

/**
 * 初回ウィザード用：チームの募集性別（canonical）
 *
 * @param int $team_id
 * @return string male|female
 */
function aidunite_activation_recruit_gender_for_team($team_id) {
    $option = (string) get_post_meta((int) $team_id, 'team_gender_option', true);
    if (function_exists('aidunite_normalize_team_gender_option')) {
        $option = aidunite_normalize_team_gender_option($option);
    }

    if ($option === 'female' || $option === 'girls') {
        return 'female';
    }

    return 'male';
}

/**
 * スケジュール新規登録後の共通フック（REST / page-schedule-edit 両方から呼ぶ）
 *
 * @param int   $schedule_id
 * @param array $data intent, team_id 等
 */
function aidunite_fire_schedule_registered_hooks($schedule_id, array $data = []) {
    $schedule_id = (int) $schedule_id;
    if ($schedule_id <= 0) {
        return;
    }

    $team_id = (int) ($data['team_id'] ?? 0);
    if ($team_id <= 0) {
        $team_id = (int) get_post_meta($schedule_id, 'team_id', true);
    }

    $intent = isset($data['intent']) ? (string) $data['intent'] : (string) get_post_meta($schedule_id, 'intent', true);

    do_action('aidunite_schedule_registered', $schedule_id, array_merge($data, [
        'team_id' => $team_id,
        'intent'  => $intent,
    ]));
}

/**
 * recruit 登録成功フック
 *
 * @param int   $schedule_id
 * @param array $data
 */
function aidunite_team_activation_hook_schedule_registered($schedule_id, $data) {
    if (($data['intent'] ?? '') !== 'recruit') {
        return;
    }

    $team_id = (int) ($data['team_id'] ?? 0);
    if ($team_id <= 0 && $schedule_id > 0) {
        $team_id = (int) get_post_meta($schedule_id, 'team_id', true);
    }

    if ($team_id > 0) {
        aidunite_team_activation_on_recruit_published($schedule_id, $team_id);
    }
}

add_action('aidunite_schedule_registered', 'aidunite_team_activation_hook_schedule_registered', 10, 2);

/**
 * 試合成立後 sync
 *
 * @param int $request_id
 */
function aidunite_team_activation_hook_match_established($request_id) {
    $request_id = (int) $request_id;
    if ($request_id <= 0) {
        return;
    }

    foreach (['from_team_id', 'to_team_id', 'other_team_id'] as $key) {
        $tid = (int) get_post_meta($request_id, $key, true);
        if ($tid > 0) {
            aidunite_team_activation_sync($tid);
        }
    }
}

add_action('aidunite_after_match_established', 'aidunite_team_activation_hook_match_established', 5, 1);

/**
 * match_request 保存後 sync（初回申請）
 *
 * @param int $request_id
 */
function aidunite_team_activation_hook_match_request_saved($request_id) {
    aidunite_team_activation_hook_match_established($request_id);
}

add_action('aidunite_match_request_saved', 'aidunite_team_activation_hook_match_request_saved', 10, 1);

/**
 * メニューにロック情報を付与
 *
 * @param array  $menu
 * @param string $user_type
 * @return array
 */
function aidunite_activation_filter_mypage_menu($menu, $user_type) {
    if ($user_type !== 'team_leader') {
        return $menu;
    }

    $team_id = function_exists('aidunite_get_current_team_id')
        ? (int) aidunite_get_current_team_id()
        : 0;

    if ($team_id <= 0) {
        return $menu;
    }

    $lock_slugs = array_flip(aidunite_activation_locked_page_slugs());
    $message    = aidunite_activation_lock_message($team_id);

    foreach ($menu as &$item) {
        $url  = isset($item['url']) ? (string) $item['url'] : '';
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $slug = $path !== '' ? basename($path) : '';

        if ($slug === 'communication' && !aidunite_activation_is_chat_unlocked($team_id)) {
            $item['activation_locked']  = true;
            $item['activation_message']   = $message;
            $item['url']                  = '#';
            continue;
        }

        if (isset($lock_slugs[$slug]) && aidunite_activation_is_page_locked($slug, $team_id)) {
            $item['activation_locked'] = true;
            $item['activation_message']  = $message;
            $item['url']                 = '#';
        }
    }
    unset($item);

    return $menu;
}

add_filter('aidunite_mypage_menu_v2_items', 'aidunite_activation_filter_mypage_menu', 20, 2);
