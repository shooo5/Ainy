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
    // 複数チーム所属対応
    $team_memberships = get_user_meta($current_user_id, 'team_memberships', true);
    if (is_array($team_memberships) && !empty($team_memberships)) {
        $team_ids = array_keys($team_memberships);
        $team_id = $team_ids[0]; // 最初のチームIDを使用
    }
}

// 出欠報告の送信処理
$submit_success = false;
$submit_error = '';

// クエリパラメータから成功メッセージを取得
if (isset($_GET['attendance_submitted']) && $_GET['attendance_submitted'] === 'success') {
    $submit_success = true;
} else {
    // transientからも確認（PhpBrowserでクエリパラメータが失われる場合のフォールバック）
    $transient_key = 'attendance_submitted_' . $current_user_id;
    $transient_value = get_transient($transient_key);
    if ($transient_value) {
        $submit_success = true;
        delete_transient($transient_key); // 一度表示したら削除
    }
}

// POST処理は、成功メッセージが表示されていない場合のみ実行
// リダイレクト後のページ読み込み時にPOSTデータが残っている場合の重複処理を防ぐ
if (!$submit_success && $_POST && isset($_POST['submit_attendance']) && isset($_POST['attendance_nonce'])) {
    error_log("🔍 [attendance-report] POST処理開始");
    error_log("🔍 [attendance-report] POSTデータ: " . json_encode($_POST));

    if (wp_verify_nonce($_POST['attendance_nonce'], 'submit_attendance')) {
        error_log("✅ [attendance-report] nonce検証成功");
        $schedule_id = intval($_POST['schedule_id'] ?? 0);
        $attendance_status = sanitize_text_field($_POST['attendance_status'] ?? '');
        $comment = sanitize_textarea_field($_POST['comment'] ?? '');

        error_log("🔍 [attendance-report] schedule_id={$schedule_id}, status={$attendance_status}, user_id={$current_user_id}");

        if ($schedule_id && in_array($attendance_status, ['attending', 'not_attending'])) {
            // 出欠確認が必要かチェック
            $attendance_required = get_post_meta($schedule_id, 'attendance_required', true);
            error_log("🔍 [attendance-report] attendance_required={$attendance_required}");
            if ($attendance_required === '1') {
                // 出欠状況を保存
                error_log("🔍 [attendance-report] aidunite_save_attendance呼び出し前");
                $result = aidunite_save_attendance($schedule_id, $current_user_id, $attendance_status, $comment);
                error_log("🔍 [attendance-report] aidunite_save_attendance結果: " . var_export($result, true));
                if ($result) {
                    // 成功メッセージをtransientで保存（PhpBrowser対応）
                    $transient_key = 'attendance_submitted_' . $current_user_id;
                    set_transient($transient_key, true, 30); // 30秒間有効
                    error_log("✅ [attendance-report] 保存成功、transient設定");

                    // リダイレクト（クエリパラメータも追加）
                    $page = get_page_by_path('attendance-report');
                    if ($page) {
                        $redirect_url = add_query_arg('attendance_submitted', 'success', get_permalink($page->ID));
                    } else {
                        $redirect_url = add_query_arg('attendance_submitted', 'success', home_url('/attendance-report'));
                    }
                    error_log("🔍 [attendance-report] リダイレクト: {$redirect_url}");
                    wp_safe_redirect($redirect_url);
                    exit;
                } else {
                    error_log("❌ [attendance-report] 保存失敗");
                    $submit_error = '出欠連絡の送信に失敗しました。もう一度お試しください。';
                }
            } else {
                error_log("❌ [attendance-report] 出欠確認が不要");
                $submit_error = 'このスケジュールは出欠確認が不要です。';
            }
        } else {
            error_log("❌ [attendance-report] 入力内容エラー");
            $submit_error = '入力内容に誤りがあります。';
        }
    } else {
        error_log("❌ [attendance-report] nonce検証失敗");
        // nonce検証失敗（リダイレクト後のページ読み込み時にPOSTデータが残っている場合）
        // この場合は、既にデータが保存されているかチェックして成功メッセージを表示する
        $schedule_id = intval($_POST['schedule_id'] ?? 0);
        $attendance_status = sanitize_text_field($_POST['attendance_status'] ?? '');

        error_log("🔍 [attendance-report] nonce検証失敗後の処理 - schedule_id={$schedule_id}, status={$attendance_status}");

        if ($schedule_id && in_array($attendance_status, ['attending', 'not_attending'])) {
            // 既にデータが保存されているかチェック
            $attendance_data = get_post_meta($schedule_id, 'attendance_data', true);
            error_log("🔍 [attendance-report] 既存データ確認: " . json_encode($attendance_data));
            if (is_array($attendance_data) && isset($attendance_data[$current_user_id])) {
                $saved_status = $attendance_data[$current_user_id]['status'] ?? '';
                if ($saved_status === $attendance_status) {
                    // 既に同じステータスで保存されている場合は成功メッセージを表示
                    error_log("✅ [attendance-report] 既に保存済み、成功メッセージ表示");
                    $submit_success = true;
                } else {
                    // transientをチェック
                    $transient_key = 'attendance_submitted_' . $current_user_id;
                    if (get_transient($transient_key)) {
                        error_log("✅ [attendance-report] transient確認成功");
                        $submit_success = true;
                        delete_transient($transient_key);
                    } else {
                        error_log("❌ [attendance-report] transient確認失敗");
                        $submit_error = 'セキュリティチェックに失敗しました。';
                    }
                }
            } else {
                // transientをチェック
                $transient_key = 'attendance_submitted_' . $current_user_id;
                if (get_transient($transient_key)) {
                    error_log("✅ [attendance-report] transient確認成功（データなし）");
                    $submit_success = true;
                    delete_transient($transient_key);
                } else {
                    error_log("❌ [attendance-report] transient確認失敗（データなし）");
                    $submit_error = 'セキュリティチェックに失敗しました。';
                }
            }
        } else {
            // transientをチェック
            $transient_key = 'attendance_submitted_' . $current_user_id;
            if (get_transient($transient_key)) {
                error_log("✅ [attendance-report] transient確認成功（入力エラー）");
                $submit_success = true;
                delete_transient($transient_key);
            } else {
                error_log("❌ [attendance-report] transient確認失敗（入力エラー）");
                $submit_error = 'セキュリティチェックに失敗しました。';
            }
        }
    }
}

// 出欠確認が必要なスケジュールを取得
$schedules = [];
if ($team_id) {
    $schedules = aidunite_get_attendance_required_schedules($team_id);
}

// 各スケジュールの出欠状況を取得
$schedules_with_attendance = [];
foreach ($schedules as $schedule) {
    $attendance_data = get_post_meta($schedule['id'], 'attendance_data', true);
    $user_attendance = null;

    if (is_array($attendance_data) && isset($attendance_data[$current_user_id])) {
        $user_attendance = $attendance_data[$current_user_id];
    }

    $schedules_with_attendance[] = [
        'id' => $schedule['id'],
        'title' => $schedule['title'],
        'date' => $schedule['date'],
        'start_time' => $schedule['start_time'],
        'end_time' => $schedule['end_time'],
        'place' => $schedule['place'],
        'type' => $schedule['type'],
        'status' => $user_attendance['status'] ?? 'pending',
        'note' => $user_attendance['note'] ?? '',
        'response_date' => $user_attendance['response_date'] ?? ''
    ];
}

// ページ設定変数（統一ガイドラインに準拠）
$page_title = '出欠連絡';
$page_description = '出欠確認が必要なスケジュールの出欠連絡を行います。';
$page_icon = 'check_circle';

get_header();
?>

<div class="team-dashboard-container page-attendance-report">
    <!-- 統一ページヘッダー -->
    <div class="dashboard-header">
        <h1><?php echo esc_html($page_title); ?></h1>
        <p><?php echo esc_html($page_description); ?></p>
    </div>

    <?php if ($submit_success): ?>
        <div class="alert alert-success">
            <?php echo aidunite_render_theme_icon('check_circle', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            出欠連絡を送信しました。
        </div>
    <?php endif; ?>

    <?php if ($submit_error): ?>
        <div class="alert alert-error">
            <?php echo aidunite_render_theme_icon('close', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php echo esc_html($submit_error); ?>
        </div>
    <?php endif; ?>

    <div class="attendance-report-section">
        <h2 class="section-title"><?php echo aidunite_render_theme_icon('list_alt_add', ['width' => '24', 'height' => '24'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 出欠連絡一覧</h2>

        <?php if (empty($schedules_with_attendance)): ?>
            <div class="empty-state">
                <div class="empty-icon"><?php echo aidunite_render_theme_icon('calendar_month', ['width' => '48', 'height' => '48']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                <h3>出欠確認が必要なスケジュールがありません</h3>
                <p>
                    現在、出欠確認が必要なスケジュールはありません。
                </p>
                <p>
                    <?php echo aidunite_render_theme_icon('info', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    出欠確認が必要なスケジュールは、<br>
                    チーム代表者がスケジュール登録時に<br>
                    「出欠確認が必要」を選択したもののみ表示されます。
                </p>
                <a href="<?php echo esc_url(home_url('/schedule-list')); ?>" class="btn btn-primary">
                    <?php echo aidunite_render_theme_icon('calendar_month', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    スケジュール確認へ
                </a>
            </div>
        <?php else: ?>
            <div class="attendance-list">
                <?php foreach ($schedules_with_attendance as $schedule): ?>
                    <div class="attendance-item card">
                        <div class="attendance-header">
                            <h3 class="attendance-title">
                                <?php echo aidunite_render_theme_icon('calendar_month', ['width' => '20', 'height' => '20'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                <?php echo esc_html($schedule['title']); ?>
                            </h3>
                            <div class="attendance-meta">
                                <span class="attendance-date">
                                    <?php
                                    if ($schedule['date']) {
                                        echo esc_html(date('Y年m月d日', strtotime($schedule['date'])));
                                    }
                                    ?>
                                </span>
                                <?php if ($schedule['start_time'] && $schedule['end_time']): ?>
                                    <span class="attendance-time">
                                        <?php echo esc_html($schedule['start_time']); ?>〜<?php echo esc_html($schedule['end_time']); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($schedule['place']): ?>
                                    <span class="attendance-place">
                                        <?php echo aidunite_render_theme_icon('stadium', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                        <?php echo esc_html($schedule['place']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($schedule['status'] === 'pending'): ?>
                            <form method="post" action="" class="attendance-form">
                                <?php wp_nonce_field('submit_attendance', 'attendance_nonce'); ?>
                                <input type="hidden" name="schedule_id" value="<?php echo esc_attr($schedule['id']); ?>">

                                <div class="form-group">
                                    <label class="form-label">出欠状況 *</label>
                                    <div class="radio-group">
                                        <label class="radio-option">
                                            <input type="radio" name="attendance_status" value="attending" required>
                                            <span><?php echo aidunite_render_theme_icon('check_circle', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 参加</span>
                                        </label>
                                        <label class="radio-option">
                                            <input type="radio" name="attendance_status" value="not_attending" required>
                                            <span><?php echo aidunite_render_theme_icon('close', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 不参加</span>
                                        </label>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="comment_<?php echo esc_attr($schedule['id']); ?>" class="form-label">コメント</label>
                                    <textarea id="comment_<?php echo esc_attr($schedule['id']); ?>"
                                              name="comment"
                                              class="form-control"
                                              rows="3"
                                              placeholder="参加できない理由やその他の連絡事項があれば記載してください"><?php echo esc_textarea($schedule['note']); ?></textarea>
                                </div>

                                <div class="form-actions">
                                    <button type="submit" name="submit_attendance" class="btn btn-primary">
                                        <?php echo aidunite_render_theme_icon('send', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                        出欠連絡を送信
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="attendance-submitted">
                                <div class="attendance-status-badge <?php echo esc_attr($schedule['status']); ?>">
                                    <?php
                                    if ($schedule['status'] === 'attending') {
                                        echo aidunite_render_theme_icon('check_circle', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline') . ' 参加'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                    } elseif ($schedule['status'] === 'not_attending') {
                                        echo aidunite_render_theme_icon('close', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline') . ' 不参加'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                    }
                                    ?>
                                </div>
                                <?php if ($schedule['note']): ?>
                                    <div class="attendance-note">
                                        <strong>コメント:</strong>
                                        <p><?php echo esc_html($schedule['note']); ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if ($schedule['response_date']): ?>
                                    <div class="attendance-response-date">
                                        回答日時: <?php echo esc_html($schedule['response_date']); ?>
                                    </div>
                                <?php endif; ?>

                                <form method="post" action="" class="attendance-form">
                                    <?php wp_nonce_field('submit_attendance', 'attendance_nonce'); ?>
                                    <input type="hidden" name="schedule_id" value="<?php echo esc_attr($schedule['id']); ?>">

                                    <div class="form-group">
                                        <label class="form-label">出欠状況を変更</label>
                                        <div class="radio-group">
                                            <label class="radio-option">
                                                <input type="radio" name="attendance_status" value="attending" <?php checked($schedule['status'], 'attending'); ?> required>
                                                <span><?php echo aidunite_render_theme_icon('check_circle', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 参加</span>
                                            </label>
                                            <label class="radio-option">
                                                <input type="radio" name="attendance_status" value="not_attending" <?php checked($schedule['status'], 'not_attending'); ?> required>
                                                <span><?php echo aidunite_render_theme_icon('close', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 不参加</span>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="comment_edit_<?php echo esc_attr($schedule['id']); ?>" class="form-label">コメント</label>
                                        <textarea id="comment_edit_<?php echo esc_attr($schedule['id']); ?>"
                                                  name="comment"
                                                  class="form-control"
                                                  rows="3"
                                                  placeholder="参加できない理由やその他の連絡事項があれば記載してください"><?php echo esc_textarea($schedule['note']); ?></textarea>
                                    </div>

                                    <div class="form-actions">
                                        <button type="submit" name="submit_attendance" class="btn btn-secondary">
                                            <?php echo aidunite_render_theme_icon('stylus', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                            出欠連絡を更新
                                        </button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.attendance-report-section {
    margin-top: 2rem;
}

.attendance-list {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.attendance-item {
    padding: 1.5rem;
    border: 1px solid var(--border-light);
    border-radius: 8px;
}

.attendance-header {
    margin-bottom: 1rem;
}

.attendance-title {
    font-size: 1.25rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
}

.attendance-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    color: #666;
    font-size: 0.9rem;
}

.attendance-form {
    margin-top: 1rem;
}

.radio-group {
    display: flex;
    gap: 1rem;
    margin-top: 0.5rem;
}

.radio-option {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
}

.radio-option input[type="radio"] {
    margin: 0;
}

.attendance-submitted {
    margin-top: 1rem;
}

.attendance-status-badge {
    display: inline-block;
    padding: 0.5rem 1rem;
    border-radius: 4px;
    font-weight: bold;
    margin-bottom: 1rem;
}

.attendance-status-badge.attending {
    background-color: rgba(40, 167, 69, 0.1);
    color: var(--success-color);
}

.attendance-status-badge.not_attending {
    background-color: #f8d7da;
    color: #721c24;
}

.attendance-note {
    margin-top: 1rem;
    padding: 1rem;
    background-color: var(--bg-secondary);
    border-radius: 4px;
}

.attendance-response-date {
    margin-top: 0.5rem;
    font-size: 0.9rem;
    color: #666;
}

.empty-state {
    text-align: center;
    padding: 3rem 1rem;
}

.empty-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
}

.empty-state h3 {
    margin-bottom: 1rem;
}

.empty-state p {
    margin-bottom: 1rem;
    color: #666;
}
</style>

<?php
get_footer();
