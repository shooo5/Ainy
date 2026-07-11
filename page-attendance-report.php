<?php
/**
 * Template Name: 出欠連絡
 *
 * 保護者・選手向け出欠報告ページ
 */

// 統一認証・権限チェック（保護者・選手のみ）
$auth_result = AidUniteAuthMiddleware::require([
    'roles' => ['parent', 'player'],
    'redirect' => true,
]);
if (!$auth_result->is_valid()) {
    // リダイレクトは自動で実行される
    return;
}

$current_user_id = $auth_result->user_id;
$user_info = aidunite_get_user_info($current_user_id);
$user_type = $user_info['user_type'] ?? 'general';

// チームID取得
$team_id = $user_info['team_id'] ?? 0;
if (!$team_id) {
    $user_canonical = function_exists('aidunite_user_get_canonical_meta')
        ? aidunite_user_get_canonical_meta((int) $current_user_id)
        : [];
    $team_memberships = is_array($user_canonical['team_memberships'] ?? null)
        ? $user_canonical['team_memberships']
        : [];
    if (!empty($team_memberships)) {
        $team_ids = array_keys($team_memberships);
        $team_id = $team_ids[0];
    }
}

$respondent_user_id = function_exists('aidunite_attendance_resolve_respondent_user_id')
    ? aidunite_attendance_resolve_respondent_user_id((int) $current_user_id, (int) $team_id)
    : (int) $current_user_id;

// 出欠報告の送信処理
$submit_success = false;
$submit_error = '';
$transient_key = 'attendance_submitted_' . $current_user_id;

// 旧URL (?attendance_submitted=success) → クリーンURLへ（POST再送信確認ダイアログ防止）
if (isset($_GET['attendance_submitted']) && $_GET['attendance_submitted'] === 'success') {
    set_transient($transient_key, 1, 60);
    $page = get_page_by_path('attendance-report');
    $clean_url = $page ? get_permalink($page->ID) : home_url('/attendance-report/');
    wp_safe_redirect($clean_url, 303);
    exit;
}

$transient_value = get_transient($transient_key);
if ($transient_value) {
    $submit_success = true;
    delete_transient($transient_key);
}

// POST処理（成功表示中はスキップ — 二重送信防止）
if (!$submit_success && $_POST && isset($_POST['submit_attendance'], $_POST['attendance_nonce'])) {
    $schedule_id = (int) ($_POST['schedule_id'] ?? 0);
    $status_field = 'attendance_status_' . $schedule_id;
    $attendance_status = sanitize_text_field(
        (string) ($_POST[$status_field] ?? $_POST['attendance_status'] ?? '')
    );
    $comment_field = 'comment_' . $schedule_id;
    $comment = sanitize_textarea_field(
        (string) ($_POST[$comment_field] ?? $_POST['comment'] ?? '')
    );

    if (!wp_verify_nonce($_POST['attendance_nonce'], 'submit_attendance')) {
        if ($schedule_id && in_array($attendance_status, ['attending', 'not_attending', 'maybe'], true)) {
            $attendance_data = function_exists('aidunite_attendance_read_data_map')
                ? aidunite_attendance_read_data_map($schedule_id)
                : [];
            $saved_row = is_array($attendance_data) && $respondent_user_id > 0
                ? ($attendance_data[$respondent_user_id] ?? null)
                : null;
            $saved_status = is_array($saved_row) ? ($saved_row['status'] ?? '') : '';
            $expected = function_exists('aidunite_normalize_attendance_status_value')
                ? aidunite_normalize_attendance_status_value($attendance_status)
                : $attendance_status;
            if ($saved_status === $expected || get_transient($transient_key)) {
                $submit_success = true;
                delete_transient($transient_key);
            } else {
                $submit_error = 'セキュリティチェックに失敗しました。';
            }
        } elseif (get_transient($transient_key)) {
            $submit_success = true;
            delete_transient($transient_key);
        } else {
            $submit_error = 'セキュリティチェックに失敗しました。';
        }
    } elseif ($schedule_id && in_array($attendance_status, ['attending', 'not_attending', 'maybe'], true)) {
        $sch_att = function_exists('aidunite_schedule_get_canonical_meta')
            ? aidunite_schedule_get_canonical_meta($schedule_id)
            : [];
        if (($sch_att['attendance_required'] ?? '0') === '1') {
            $schedule_date = (string) ($sch_att['schedule_date'] ?? $sch_att['date'] ?? '');
            if ($schedule_date === '' && function_exists('aidunite_schedule_read_normalized_date')) {
                $schedule_date = (string) aidunite_schedule_read_normalized_date($schedule_id);
            }
            $deadline_at = function_exists('aidunite_attendance_policy_get_response_deadline')
                ? aidunite_attendance_policy_get_response_deadline($schedule_date)
                : '';
            if (function_exists('aidunite_attendance_is_response_open')
                && !aidunite_attendance_is_response_open($schedule_date, $deadline_at)) {
                $submit_error = function_exists('aidunite_attendance_response_closed_message')
                    ? aidunite_attendance_response_closed_message(
                        aidunite_attendance_response_closed_reason($schedule_date, $deadline_at)
                    )
                    : '回答期限を過ぎているため、送信できません。';
            } elseif ($respondent_user_id <= 0) {
                $submit_error = '出欠回答対象の選手が見つかりません。';
            } elseif (aidunite_save_attendance($schedule_id, $respondent_user_id, $attendance_status, $comment)) {
                set_transient($transient_key, 1, 60);
                $page = get_page_by_path('attendance-report');
                $redirect_url = $page ? get_permalink($page->ID) : home_url('/attendance-report/');
                wp_safe_redirect($redirect_url, 303);
                exit;
            }
            $submit_error = '出欠連絡の送信に失敗しました。もう一度お試しください。';
        } else {
            $submit_error = 'このスケジュールは出欠確認が不要です。';
        }
    } else {
        $submit_error = '入力内容に誤りがあります。';
    }
}

// 出欠確認が必要なスケジュールを取得
$schedules = [];
if ($team_id) {
    $schedules = aidunite_get_attendance_required_schedules($team_id);
}

// view model 構築
$schedule_cards = [];
foreach ($schedules as $schedule) {
    if (!function_exists('aidunite_attendance_build_schedule_view_model')) {
        continue;
    }
    $schedule_cards[] = aidunite_attendance_build_schedule_view_model($schedule, (int) $team_id, (int) $respondent_user_id);
}

$highlight_schedule_id = isset($_GET['schedule_id']) ? (int) $_GET['schedule_id'] : 0;
$theme_key = function_exists('aidunite_get_team_ui_theme_key')
    ? aidunite_get_team_ui_theme_key((int) $team_id)
    : 'boys';
if (!in_array($theme_key, ['boys', 'girls'], true)) {
    $theme_key = 'boys';
}

$page_title = '出欠連絡';
$page_description = '出欠確認が必要なスケジュールの出欠連絡を行います。';

get_header();

if (function_exists('aidunite_web_app_page_shell_open')) {
    aidunite_web_app_page_shell_open([
        'page_class' => 'page-attendance-report',
        'title' => $page_title,
        'subtitle' => $page_description,
    ]);
} else {
    echo '<div class="team-dashboard-container page-attendance-report"><div class="dashboard-header"><h1>' . esc_html($page_title) . '</h1><p>' . esc_html($page_description) . '</p></div>';
}
?>

<div class="attendance-page" data-team-theme="<?php echo esc_attr($theme_key); ?>"<?php echo $highlight_schedule_id > 0 ? ' data-highlight-schedule-id="' . (int) $highlight_schedule_id . '"' : ''; ?>>

    <?php if ($submit_success) : ?>
        <div class="alert alert-success">
            <?php echo aidunite_render_theme_icon('check_circle', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            出欠連絡を送信しました。
        </div>
    <?php endif; ?>

    <?php if ($submit_error) : ?>
        <div class="alert alert-error">
            <?php echo aidunite_render_theme_icon('close', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php echo esc_html($submit_error); ?>
        </div>
    <?php endif; ?>

    <div class="attendance-report-section">
        <?php if (empty($schedule_cards)) : ?>
            <div class="attendance-empty">
                <div class="attendance-empty__icon"><?php echo aidunite_render_theme_icon('calendar_month', ['width' => '48', 'height' => '48']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                <h3>出欠確認が必要なスケジュールがありません</h3>
                <p>現在、出欠確認が必要なスケジュールはありません。</p>
                <p>チーム代表者が「出欠確認が必要」を ON にしたスケジュールのみ表示されます。</p>
                <a href="<?php echo esc_url(home_url('/schedule-management')); ?>" class="btn btn-primary">スケジュール確認へ</a>
            </div>
        <?php else : ?>
            <div class="attendance-board">
                <?php foreach ($schedule_cards as $card) : ?>
                    <?php
                    get_template_part('template-parts/attendance/schedule-card', null, [
                        'schedule' => $card,
                        'mode' => 'report',
                        'highlight' => $highlight_schedule_id > 0 && (int) ($card['schedule_id'] ?? 0) === $highlight_schedule_id,
                    ]);
                    ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php
if (function_exists('aidunite_web_app_page_shell_close')) {
    aidunite_web_app_page_shell_close();
} else {
    echo '</div>';
}
get_footer();
