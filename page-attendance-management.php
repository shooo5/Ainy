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

// 各スケジュールの view model を構築
$schedule_cards = [];
foreach ($schedules as $schedule) {
    if (!function_exists('aidunite_attendance_build_schedule_view_model')) {
        continue;
    }
    $card = aidunite_attendance_build_schedule_view_model($schedule, (int) $team_id, (int) $current_user_id);

    if ($filter_status && $filter_status !== 'all') {
        $matching = array_filter(
            $card['members'] ?? [],
            static function ($member) use ($filter_status) {
                $ui_key = (string) ($member['ui_key'] ?? 'unanswered');
                if ($filter_status === 'pending') {
                    return $ui_key === 'unanswered';
                }
                return $ui_key === $filter_status;
            }
        );
        if ($matching === []) {
            continue;
        }
    }

    $schedule_cards[] = $card;
}

$theme_key = function_exists('aidunite_get_team_ui_theme_key')
    ? aidunite_get_team_ui_theme_key((int) $team_id)
    : 'boys';
if (!in_array($theme_key, ['boys', 'girls'], true)) {
    $theme_key = 'boys';
}

// イベント種別の一覧を取得（フィルター用）
$schedule_types = [];
foreach ($schedules as $schedule) {
    if ($schedule['type'] && !in_array($schedule['type'], $schedule_types)) {
        $schedule_types[] = $schedule['type'];
    }
}
sort($schedule_types);

// フィルターが適用中なら初期表示で開く
$filter_active = ($filter_type !== ''
    || ($filter_status !== '' && $filter_status !== 'all')
    || $filter_date_from !== ''
    || $filter_date_to !== '');

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

<div class="attendance-page" data-team-theme="<?php echo esc_attr($theme_key); ?>">

    <!-- フィルター（デフォルト閉じ） -->
    <div class="filter-section card">
        <details class="attendance-filter"<?php echo $filter_active ? ' open' : ''; ?>>
            <summary class="attendance-filter__summary">
                <span class="attendance-filter__summary-title">
                    <?php echo aidunite_render_theme_icon('search', ['width' => '22', 'height' => '22'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    フィルター
                </span>
                <span class="attendance-filter__chevron" aria-hidden="true">
                    <?php echo aidunite_render_theme_icon('arrow_downward', ['width' => '22', 'height' => '22']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </span>
            </summary>
            <div class="attendance-filter__body">
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
                        <option value="maybe" <?php selected($filter_status, 'maybe'); ?>>未定</option>
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
        </details>
    </div>

    <!-- 出欠状況一覧 -->
    <div class="attendance-management-section">
        <h2 class="section-title"><?php echo aidunite_render_theme_icon('bar_chart_4_bars', ['width' => '24', 'height' => '24'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 出欠状況一覧</h2>

        <?php if (empty($schedule_cards)) : ?>
            <?php
            echo aidunite_empty_state([
                'title' => '出欠確認が必要なスケジュールがありません',
                'message' => '現在、出欠確認が必要なスケジュールはありません。',
                'description' => 'スケジュールを登録すると、出欠確認が可能になります。',
                'action' => [
                    'text' => 'スケジュールを登録',
                    'url' => function_exists('aidunite_get_schedule_edit_url')
                        ? aidunite_get_schedule_edit_url()
                        : home_url('/schedule-management/'),
                ],
                'type' => 'default',
            ]);
            ?>
        <?php else : ?>
            <div class="attendance-board">
                <?php foreach ($schedule_cards as $card) : ?>
                    <?php
                    get_template_part('template-parts/attendance/schedule-card', null, [
                        'schedule' => $card,
                        'mode' => 'manage',
                        'highlight' => false,
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
?>

<?php
get_footer();
