<?php
/**
 * Template Name: 出欠管理
 *
 * チーム代表者向け出欠管理ページ
 */

// 統一認証・権限チェック（チーム代表者のみ）
$auth_result = AidUniteAuthMiddleware::require([
    'roles' => ['team_leader', 'administrator'],
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
    // 統一認証・権限チェック（チーム代表者または管理者の場合）
    require_once get_template_directory() . '/functions/common/auth-middleware.php';
    $admin_result = AidUniteAuthMiddleware::require_admin(false);
    $team_leader_result = AidUniteAuthMiddleware::require_team_leader(null, false);
    if ($user_type === 'team_leader' || $admin_result->is_valid() || $team_leader_result->is_valid()) {
        // 作成したチームを取得
        $user_teams = get_posts([
            'post_type' => 'team',
            'author' => $current_user_id,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids'
        ]);
        if (!empty($user_teams)) {
            $team_id = $user_teams[0];
        }
    }

    // まだ取得できていない場合、team_membershipsから取得
    if (!$team_id) {
        $team_memberships = get_user_meta($current_user_id, 'team_memberships', true);
        if (is_array($team_memberships) && !empty($team_memberships)) {
            $team_ids = array_keys($team_memberships);
            $team_id = $team_ids[0];
        }
    }
}
if (!$team_id) {
    wp_die('チーム情報が見つかりません。');
}

// フィルター処理
$filter_type = sanitize_text_field($_GET['type'] ?? '');
$filter_status = sanitize_text_field($_GET['status'] ?? '');
$filter_date_from = sanitize_text_field($_GET['date_from'] ?? '');
$filter_date_to = sanitize_text_field($_GET['date_to'] ?? '');

// 出欠確認が必要なスケジュールを取得
$schedules = aidunite_get_attendance_required_schedules($team_id, $filter_date_from, $filter_date_to);

// フィルター適用（イベント種別）
if ($filter_type) {
    $schedules = array_filter($schedules, function($schedule) use ($filter_type) {
        return $schedule['type'] === $filter_type;
    });
}

// チームメンバーを取得（保護者と選手のみ）
$team_members = aidunite_get_team_members_list($team_id);
$target_members = [];
foreach ($team_members as $member) {
    if (in_array($member['user_type'], ['parent', 'player'])) {
        $target_members[] = $member;
    }
}

// 各スケジュールの出欠状況を取得
$schedules_with_attendance = [];
foreach ($schedules as $schedule) {
    $attendance_data = get_post_meta($schedule['id'], 'attendance_data', true);
    if (!is_array($attendance_data)) {
        $attendance_data = [];
    }

    // 集計を取得
    $member_ids = array_column($target_members, 'user_id');
    $summary = aidunite_get_attendance_summary($schedule['id'], $member_ids);

    // メンバーごとの出欠状況
    $members_attendance = [];
    foreach ($target_members as $member) {
        $user_id = $member['user_id'];
        $member_attendance = $attendance_data[$user_id] ?? null;

        $members_attendance[] = [
            'user_id' => $user_id,
            'user_name' => $member['display_name'],
            'user_type' => $member['user_type'],
            'status' => $member_attendance['status'] ?? 'pending',
            'note' => $member_attendance['note'] ?? '',
            'response_date' => $member_attendance['response_date'] ?? ''
        ];
    }

    // フィルター適用（出欠状況）
    if ($filter_status && $filter_status !== 'all') {
        $filtered_members = array_filter($members_attendance, function($member) use ($filter_status) {
            return $member['status'] === $filter_status;
        });
        if (empty($filtered_members)) {
            continue; // 該当するメンバーがいない場合はスケジュールをスキップ
        }
    }

    $schedules_with_attendance[] = [
        'id' => $schedule['id'],
        'title' => $schedule['title'],
        'date' => $schedule['date'],
        'start_time' => $schedule['start_time'],
        'end_time' => $schedule['end_time'],
        'place' => $schedule['place'],
        'type' => $schedule['type'],
        'summary' => $summary,
        'members' => $members_attendance
    ];
}

// イベント種別の一覧を取得（フィルター用）
$schedule_types = [];
foreach ($schedules as $schedule) {
    if ($schedule['type'] && !in_array($schedule['type'], $schedule_types)) {
        $schedule_types[] = $schedule['type'];
    }
}
sort($schedule_types);

// ページ設定変数（統一ガイドラインに準拠）
$page_title = '出欠管理';
$page_description = '出欠確認が必要なスケジュールの出欠状況を管理します。';
$page_icon = 'bar_chart_4_bars';

get_header();

if (function_exists('aidunite_web_app_page_shell_open')) {
    aidunite_web_app_page_shell_open([
        'page_class' => 'page-attendance-management',
        'title' => $page_title,
        'subtitle' => $page_description,
    ]);
} else {
    echo '<div class="team-dashboard-container page-attendance-management"><div class="dashboard-header"><h1>' . esc_html($page_title) . '</h1><p>' . esc_html($page_description) . '</p></div>';
}
?>

    <!-- フィルター -->
    <div class="filter-section card">
        <h2 class="section-title"><?php echo aidunite_render_theme_icon('search', ['width' => '24', 'height' => '24'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> フィルター</h2>
        <form method="get" action="" class="filter-form">
            <div class="filter-row">
                <div class="filter-item">
                    <label for="filter_type" class="filter-label">イベント種別</label>
                    <select name="type" id="filter_type" class="form-control">
                        <option value="">全て</option>
                        <?php foreach ($schedule_types as $type): ?>
                            <option value="<?php echo esc_attr($type); ?>" <?php selected($filter_type, $type); ?>>
                                <?php echo esc_html($type); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-item">
                    <label for="filter_status" class="filter-label">出欠状況</label>
                    <select name="status" id="filter_status" class="form-control">
                        <option value="all">全て</option>
                        <option value="attending" <?php selected($filter_status, 'attending'); ?>>参加</option>
                        <option value="not_attending" <?php selected($filter_status, 'not_attending'); ?>>不参加</option>
                        <option value="pending" <?php selected($filter_status, 'pending'); ?>>未回答</option>
                    </select>
                </div>

                <div class="filter-item">
                    <label for="filter_date_from" class="filter-label">開始日</label>
                    <input type="date" name="date_from" id="filter_date_from" class="form-control" value="<?php echo esc_attr($filter_date_from); ?>">
                </div>

                <div class="filter-item">
                    <label for="filter_date_to" class="filter-label">終了日</label>
                    <input type="date" name="date_to" id="filter_date_to" class="form-control" value="<?php echo esc_attr($filter_date_to); ?>">
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary"><?php echo aidunite_render_theme_icon('search', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> フィルター適用</button>
                    <a href="<?php echo esc_url(remove_query_arg(['type', 'status', 'date_from', 'date_to'])); ?>" class="btn btn-secondary">リセット</a>
                </div>
            </div>
        </form>
    </div>

    <!-- 出欠状況一覧 -->
    <div class="attendance-management-section">
        <h2 class="section-title"><?php echo aidunite_render_theme_icon('bar_chart_4_bars', ['width' => '24', 'height' => '24'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 出欠状況一覧</h2>

        <?php if (empty($schedules_with_attendance)): ?>
            <?php
            // 統一空の状態コンポーネントを使用
            echo aidunite_empty_state([
                'title' => '出欠確認が必要なスケジュールがありません',
                'message' => '現在、出欠確認が必要なスケジュールはありません。',
                'description' => 'スケジュールを登録すると、出欠確認が可能になります。',
                'action' => [
                    'text' => 'スケジュールを登録',
                    'url' => home_url('/schedule-edit')
                ],
                'type' => 'default'
            ]);
            ?>
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

                        <!-- 集計表示 -->
                        <div class="attendance-stats">
                            <div class="stat-item">
                                <span class="stat-number"><?php echo esc_html($schedule['summary']['total']); ?></span>
                                <span class="stat-label">総数</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number" style="color: var(--success-color);"><?php echo esc_html($schedule['summary']['attending']); ?></span>
                                <span class="stat-label">参加</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number" style="color: var(--danger-color);"><?php echo esc_html($schedule['summary']['not_attending']); ?></span>
                                <span class="stat-label">不参加</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number" style="color: #ffc107;"><?php echo esc_html($schedule['summary']['pending']); ?></span>
                                <span class="stat-label">未回答</span>
                            </div>
                        </div>

                        <!-- メンバー一覧 -->
                        <div class="members-attendance">
                            <h4 class="members-title">メンバー別出欠状況</h4>
                            <div class="members-list">
                                <?php foreach ($schedule['members'] as $member): ?>
                                    <div class="member-item">
                                        <div class="member-info">
                                            <span class="member-name">
                                                <?php echo aidunite_render_theme_icon('person_man', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                                <?php echo esc_html($member['user_name']); ?>
                                            </span>
                                            <span class="member-type">
                                                (<?php
                                                if ($member['user_type'] === 'parent') {
                                                    echo '保護者';
                                                } elseif ($member['user_type'] === 'player') {
                                                    echo '選手';
                                                }
                                                ?>)
                                            </span>
                                        </div>
                                        <div class="member-status">
                                            <?php if ($member['status'] === 'attending'): ?>
                                                <span class="status-badge attending"><?php echo aidunite_render_theme_icon('check_circle', ['width' => '14', 'height' => '14'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 参加</span>
                                            <?php elseif ($member['status'] === 'not_attending'): ?>
                                                <span class="status-badge not-attending"><?php echo aidunite_render_theme_icon('close', ['width' => '14', 'height' => '14'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 不参加</span>
                                            <?php else: ?>
                                                <span class="status-badge pending"><?php echo aidunite_render_theme_icon('hourglass_empty', ['width' => '14', 'height' => '14'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 未回答</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($member['note']): ?>
                                            <div class="member-note">
                                                <strong>コメント:</strong> <?php echo esc_html($member['note']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($member['response_date']): ?>
                                            <div class="member-response-date">
                                                回答日時: <?php echo esc_html($member['response_date']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

<?php
if (function_exists('aidunite_web_app_page_shell_close')) {
    aidunite_web_app_page_shell_close();
} else {
    echo '</div>';
}
?>

<style>
.filter-section {
    margin-top: 2rem;
    padding: 1.5rem;
}

.filter-form {
    margin-top: 1rem;
}

.filter-row {
  display: flex;
    flex-wrap: wrap;
  gap: 1rem;
    align-items: flex-end;
}

.filter-item {
    flex: 1;
    min-width: 150px;
}

.filter-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: bold;
}

.filter-actions {
  display: flex;
  gap: 0.5rem;
}

.attendance-management-section {
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
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.attendance-stats {
  display: flex;
    gap: 2rem;
    margin: 1.5rem 0;
    padding: 1rem;
    background-color: var(--bg-secondary);
    border-radius: 4px;
}

.stat-item {
  display: flex;
    flex-direction: column;
  align-items: center;
}

.stat-number {
    font-size: 2rem;
    font-weight: bold;
}

.stat-label {
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin-top: 0.25rem;
}

.members-attendance {
    margin-top: 1.5rem;
}

.members-title {
    font-size: 1.1rem;
    font-weight: bold;
  margin-bottom: 1rem;
}

.members-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }

.member-item {
    padding: 1rem;
    background-color: var(--bg-secondary);
    border-radius: 4px;
    border-left: 4px solid #007bff;
}

.member-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

.member-name {
    font-weight: bold;
}

.member-type {
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.member-status {
    margin-bottom: 0.5rem;
}

.status-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 4px;
    font-weight: bold;
    font-size: 0.9rem;
}

.status-badge.attending {
    background-color: rgba(40, 167, 69, 0.1);
    color: var(--success-color);
}

.status-badge.not-attending {
    background-color: #f8d7da;
    color: #721c24;
}

.status-badge.pending {
    background-color: rgba(255, 193, 7, 0.1);
    color: #856404;
}

.member-note {
    margin-top: 0.5rem;
    padding: 0.5rem;
    background-color: #ffffff;
    border-radius: 4px;
    font-size: 0.9rem;
}

.member-response-date {
    margin-top: 0.5rem;
    font-size: 0.85rem;
    color: var(--text-secondary);
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
    color: var(--text-secondary);
}
</style>

<?php
get_footer();
