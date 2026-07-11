<?php
/**
 * Template Name: スケジュール管理
 *
 * スケジュールの管理・表示を行うページ
 * PC版：2カラムレイアウト、スマホ版：1カラムレイアウト
 */

// 統一認証・権限チェック
$auth_result = AidUniteAuthMiddleware::require_auth(true);
if (!$auth_result->is_valid()) {
    // リダイレクトは自動で実行される
    return;
}

// 権限チェック - チーム管理者のみ編集可能
$can_edit_schedule = false;
$user_id = $auth_result->user_id;

// 統一認証・権限チェック（管理者の場合）
require_once get_template_directory() . '/functions/common/auth-middleware.php';
$admin_result = AidUniteAuthMiddleware::require_admin(false);
if ($admin_result->is_valid()) {
    $can_edit_schedule = true;
} else {
    // カスタムロールのチェック
    $user_role = aidunite_get_user_role($user_id);
    if ($user_role === 'team_leader') {
        $can_edit_schedule = true;
    }
}

// 保護者・選手: 所属チームを判定し、操作中チームを active 所属に揃える
if (!$can_edit_schedule && function_exists('aidunite_schedule_sync_member_view_operating_team')) {
    $member_team_id = aidunite_schedule_sync_member_view_operating_team($user_id);
    if ($member_team_id <= 0) {
        wp_safe_redirect(home_url('/mypage/?schedule_access=none'));
        exit;
    }
}

get_header(); ?>

<?php
$GLOBALS['aidunite_schedule_view_config'] = [
    'canEdit' => $can_edit_schedule,
    'showAttendanceSummary' => $can_edit_schedule,
];

// スケジュール編集ページのURLを取得
$schedule_edit_url = esc_url(function_exists('aidunite_get_schedule_edit_url')
    ? aidunite_get_schedule_edit_url()
    : home_url('/schedule-management/'));

$schedule_quick_team_gender = '';
if ($can_edit_schedule && function_exists('aidunite_get_current_team_id')) {
    $quick_team_id = (int) aidunite_get_current_team_id($user_id);
    if ($quick_team_id > 0 && function_exists('aidunite_team_get_canonical_meta')) {
        $team_canonical = aidunite_team_get_canonical_meta($quick_team_id);
        $schedule_quick_team_gender = (string) ($team_canonical['team_gender_option'] ?? '');
    }
}

// 共通カレンダーテンプレートを読み込み
require_once get_template_directory() . '/functions/schedule/schedule-calendar-common.php';

?>

<?php
if (function_exists('aidunite_web_app_page_shell_open')) {
    aidunite_web_app_page_shell_open([
        'page_class' => 'page-schedule-management' . ($can_edit_schedule ? '' : ' schedule-management--readonly'),
        'title' => $can_edit_schedule ? 'スケジュール管理' : 'スケジュール',
        'subtitle' => $can_edit_schedule
            ? 'チームのスケジュールを管理し、予定を確認できます。'
            : 'チームのスケジュールを確認できます（閲覧専用）。',
        'content_class' => 'ainy-webapp-content--schedule-management',
    ]);
} else {
    echo '<div class="team-dashboard-container page-schedule-management' . ($can_edit_schedule ? '' : ' schedule-management--readonly') . '"><div class="dashboard-header"><h1>' . esc_html($can_edit_schedule ? 'スケジュール管理' : 'スケジュール') . '</h1><p>' . esc_html($can_edit_schedule ? 'チームのスケジュールを管理し、予定を確認できます。' : 'チームのスケジュールを確認できます（閲覧専用）。') . '</p></div>';
}
?>

        <?php if (!$can_edit_schedule) : ?>
        <div class="schedule-management-readonly-notice" role="status">
            閲覧専用モードです。予定の登録・編集はチーム代表者のみ可能です。
        </div>
        <?php endif; ?>

        <div class="schedule-display-area">
            <!-- 絞り込みフィルター（1段目: チップ＋右端に新規登録、2段目: 説明文） -->
            <div class="schedule-filter-bar">
                <div class="schedule-filter-row">
                    <div class="filter-chips" id="schedule-filter-chips">
                        <button type="button" class="filter-chip active" data-filter="all">すべて</button>
                        <button type="button" class="filter-chip" data-filter="confirmed">確定の予定</button>
                        <button type="button" class="filter-chip" data-filter="recruit">試合を募集</button>
                    </div>
                    <?php if ($can_edit_schedule): ?>
                    <button type="button" id="schedule-add-btn" class="ainy-header-icon-link" aria-label="新規登録" data-testid="schedule-new-create">
                        <svg xmlns="http://www.w3.org/2000/svg" height="24" width="24" viewBox="0 -960 960 960" fill="#0000F5" aria-hidden="true"><path d="M440-440H200v-80h240v-240h80v240h240v80H520v240h-80v-240Z"/></svg>
                    </button>
                    <?php endif; ?>
                </div>
                <?php
                if (function_exists('output_schedule_view_and_team_filters')) {
                    output_schedule_view_and_team_filters();
                }
                ?>
                <p class="schedule-filter-legend">登録時の目的と同じ区分です（確定の予定／試合を募集）</p>
            </div>

            <!-- カレンダー表示 -->
            <?php output_schedule_calendar_html(); ?>
        </div>

<?php
if (function_exists('aidunite_web_app_page_shell_close')) {
    aidunite_web_app_page_shell_close();
} else {
    echo '</div>';
}
?>

<!-- 削除確認モーダル（リストビューからの削除用・編集権限のみ） -->
<?php if ($can_edit_schedule) : ?>
<div id="delete-confirm-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>削除確認</h3>
            <span class="close" onclick="closeDeleteConfirmModal()">&times;</span>
        </div>
        <div class="modal-body">
            <p>このスケジュールを削除してもよろしいですか？</p>
            <p class="schedule-title" id="delete-schedule-title"></p>
        </div>
        <div class="modal-footer">
            <button class="dashboard-btn btn-danger" onclick="confirmDeleteSchedule()">削除</button>
            <button class="dashboard-btn btn-secondary" onclick="closeDeleteConfirmModal()">キャンセル</button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
if (wp_script_is('aidunite-schedule-quick-modal', 'registered')) {
    $schedule_quick_activation_stage = '';
    $schedule_quick_activation_mission = false;
    if (function_exists('aidunite_get_current_team_id')) {
        $schedule_quick_team_id = (int) aidunite_get_current_team_id($user_id);
        if ($schedule_quick_team_id > 0 && function_exists('aidunite_get_team_activation_stage')) {
            $schedule_quick_activation_stage = (string) aidunite_get_team_activation_stage($schedule_quick_team_id);
            $schedule_quick_activation_mission = function_exists('aidunite_activation_is_mission_ui')
                && aidunite_activation_is_mission_ui($schedule_quick_team_id);
        }
    }
    wp_localize_script('aidunite-schedule-quick-modal', 'AIDUNITE_SCHEDULE_QUICK', [
        'canEdit' => $can_edit_schedule,
        'scheduleEditUrl' => function_exists('aidunite_get_schedule_edit_url')
            ? aidunite_get_schedule_edit_url()
            : home_url('/schedule-management/'),
        'registerUrl' => rest_url('aidunite/v1/register-schedule-v2'),
        'deleteUrl' => rest_url('aidunite/v1/delete-schedule-v2'),
        'updateUrl' => rest_url('aidunite/v1/update-schedule-v2'),
        'nonce' => wp_create_nonce('wp_rest'),
        'teamGender' => $schedule_quick_team_gender,
        'defaultStartTime' => '13:00',
        'defaultEndTime' => '14:00',
        'defaultRecruitTeams' => 2,
        'activationStage' => $schedule_quick_activation_stage,
        'activationMissionUi' => $schedule_quick_activation_mission ? '1' : '0',
        'mypageUrl' => home_url('/mypage/'),
        'matchBoardMyUrl' => home_url('/match-board-own?market_tab=my'),
    ]);
}
// 統一カレンダー（インラインスクリプトより前に読み込み・fetch 完了後に1回だけ描画）
output_schedule_calendar_js();
?>
<?php
$schedule_mgmt_page_js = get_stylesheet_directory() . '/assets/js/pages/schedule-management-page.js';
if (is_readable($schedule_mgmt_page_js)) {
    wp_enqueue_script(
        'aidunite-schedule-management-page',
        get_stylesheet_directory_uri() . '/assets/js/pages/schedule-management-page.js',
        [
            'aidunite-schedule-loader',
            'aidunite-schedule-modal',
            'aidunite-schedule-quick-modal',
            'aidunite-toast-notification',
            'aidunite-confirm-modal',
        ],
        (string) filemtime($schedule_mgmt_page_js),
        true
    );
    wp_localize_script('aidunite-schedule-management-page', 'aiduniteScheduleManagementPage', [
        'scheduleEditUrl' => esc_url($schedule_edit_url),
        'canEdit' => (bool) $can_edit_schedule,
    ]);
}
?>

<?php get_footer(); ?>
