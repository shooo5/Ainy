<?php
/*
Template Name: スケジュール一覧
*/

// セッション開始
if (!session_id()) {
    session_start();
}

// 統一認証・権限チェック
$auth_result = AidUniteAuthMiddleware::require_auth(true);
if (!$auth_result->is_valid()) {
    // リダイレクトは自動で実行される
    return;
}

try {
get_header();
} catch (Exception $e) {
    echo "ヘッダーの読み込みでエラーが発生しました: " . $e->getMessage();
    return;
}

// ユーザー情報取得
$current_user_id = $auth_result->user_id;
$user_info = aidunite_get_user_info($current_user_id);
list($effective_role, $preview_mode) = aidunite_get_effective_user_role();

// 管理者権限チェック（管理者のみ全チーム表示）
$is_admin = current_user_can('administrator') || current_user_can('manage_options');

// 一括削除処理
if (isset($_POST['bulk_delete_schedules'])) {
    if (check_admin_referer('bulk_delete_schedules_action', 'bulk_delete_schedules_nonce')) {
        $selected_schedules = isset($_POST['selected_schedules']) ? $_POST['selected_schedules'] : [];

        if (empty($selected_schedules)) {
            $delete_message = '<div class="alert-message alert-warning">削除する項目が選択されていません。</div>';
        } else {
            $deleted_count = 0;
            $skipped = 0;
            foreach ($selected_schedules as $schedule_id) {
                if (get_post_type($schedule_id) === 'schedule') {
                    if (function_exists('aidunite_get_schedule_delete_gate_array') && function_exists('aidunite_perform_safe_schedule_deletion')) {
                        $gate = aidunite_get_schedule_delete_gate_array((int) $schedule_id);
                        if (empty($gate['allowed'])) {
                            if (defined('WP_DEBUG') && WP_DEBUG) {
                                error_log('[aidunite_schedule] 一括削除抑止 page-schedule-list schedule_id=' . (int) $schedule_id . ' code=' . ($gate['code'] ?? ''));
                            }
                            $skipped++;
                            continue;
                        }
                        $r = aidunite_perform_safe_schedule_deletion((int) $schedule_id);
                        if (is_wp_error($r)) {
                            $skipped++;
                            if (defined('WP_DEBUG') && WP_DEBUG) {
                                error_log('[aidunite_schedule] 一括削除失敗 schedule_id=' . (int) $schedule_id . ' ' . $r->get_error_message());
                            }
                            continue;
                        }
                        $deleted_count++;
                    } else {
                        $skipped++;
                    }
                }
            }
            $msg = '削除: ' . $deleted_count . ' 件。';
            if ($skipped > 0) {
                $msg .= ' スキップ: ' . $skipped . ' 件（申請中・承認/成立中の枠、または保護中）。';
            }
            $delete_message = '<div class="alert-message alert-success">' . esc_html($msg) . '</div>';
        }
    }
}

// 削除済みスケジュールの完全削除処理
if (isset($_POST['cleanup_trashed_schedules'])) {
    if (check_admin_referer('cleanup_trashed_schedules_action', 'cleanup_trashed_schedules_nonce')) {
        $trashed_schedules = get_posts([
            'post_type' => 'schedule',
            'post_status' => 'trash',
            'posts_per_page' => -1
        ]);

        $deleted_count = 0;
        $skipped = 0;

        foreach ($trashed_schedules as $schedule) {
            if (function_exists('aidunite_get_schedule_delete_gate_array') && function_exists('aidunite_perform_safe_schedule_deletion')) {
                $gate = aidunite_get_schedule_delete_gate_array((int) $schedule->ID);
                if (empty($gate['allowed'])) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[aidunite_schedule] ゴミ箱掃除抑止 schedule_id=' . (int) $schedule->ID);
                    }
                    $skipped++;
                    continue;
                }
                $r = aidunite_perform_safe_schedule_deletion((int) $schedule->ID);
                if (is_wp_error($r)) {
                    $skipped++;
                    continue;
                }
                $deleted_count++;
            } else {
                $skipped++;
            }
        }

        $msg = '完全削除: ' . $deleted_count . ' 件。';
        if ($skipped > 0) {
            $msg .= ' スキップ: ' . $skipped . ' 件（保護中の枠）。';
        }
        $delete_message = '<div class="alert-message alert-success">🗑️ ' . esc_html($msg) . '</div>';
    }
}

// 全スケジュールの完全削除処理（危険な操作）
if (isset($_POST['delete_all_schedules'])) {
    if (check_admin_referer('delete_all_schedules_action', 'delete_all_schedules_nonce')) {
        $all_schedules = get_posts([
            'post_type' => 'schedule',
            'post_status' => ['publish', 'draft', 'private', 'pending', 'future', 'trash'],
            'posts_per_page' => -1
        ]);

        $deleted_count = 0;
        $skipped = 0;

        foreach ($all_schedules as $schedule) {
            if (get_post_type($schedule->ID) === 'schedule' && function_exists('aidunite_get_schedule_delete_gate_array') && function_exists('aidunite_perform_safe_schedule_deletion')) {
                $gate = aidunite_get_schedule_delete_gate_array((int) $schedule->ID);
                if (empty($gate['allowed'])) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[aidunite_schedule] 全件削除抑止（危険操作） schedule_id=' . (int) $schedule->ID);
                    }
                    $skipped++;
                    continue;
                }
                $r = aidunite_perform_safe_schedule_deletion((int) $schedule->ID);
                if (is_wp_error($r)) {
                    $skipped++;
                    continue;
                }
                $deleted_count++;
            } else {
                $skipped++;
            }
        }

        $msg = '削除: ' . $deleted_count . ' 件。';
        if ($skipped > 0) {
            $msg .= ' スキップ: ' . $skipped . ' 件（保護中、または非対応）。';
        }
        $delete_message = '<div class="alert-message alert-danger">⚠️ 全スケジュール一括: ' . esc_html($msg) . '</div>';
    }
}

// フィルター用のパラメータを取得
$month_filter = isset($_GET['month']) ? intval($_GET['month']) : 0; // 0=全月
$year_filter = isset($_GET['year']) ? intval($_GET['year']) : (int)date('Y');
$type_filter = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
$intent_filter = isset($_GET['intent']) ? sanitize_text_field($_GET['intent']) : '';
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';

// スケジュールを取得
$args = [
  'post_type'      => 'schedule',
  'posts_per_page' => -1,
  'post_status'    => ['publish','future','pending','draft','private'],
  'orderby'        => 'meta_value',
  'meta_key'       => 'schedule_date',
  'order'          => 'ASC',
  'meta_query'     => ['relation' => 'AND'],
];

// 一般ユーザー: 操作中チームのスケジュールのみ（ヘッダー切替と一致）
if (!$is_admin) {
    $team_clause = function_exists('aidunite_schedule_team_meta_query_for_operating_team')
        ? aidunite_schedule_team_meta_query_for_operating_team((int) $current_user_id)
        : null;
    if ($team_clause === null) {
        $schedules = [];
    } else {
        $args['meta_query'][] = $team_clause;
    }
}

// 月フィルター
if ($month_filter > 0) {
    $start_date = sprintf('%04d-%02d-01', $year_filter, $month_filter);
    $end_date = sprintf('%04d-%02d-%02d', $year_filter, $month_filter, date('t', strtotime($start_date)));
    $args['meta_query'][] = [
        'key' => 'schedule_date',
        'value' => [$start_date, $end_date],
        'compare' => 'BETWEEN',
        'type' => 'DATE'
    ];
}

// 種別フィルターを適用
if (!empty($type_filter)) {
    $args['meta_query'][] = [
        'key' => 'schedule_type',
        'value' => $type_filter,
        'compare' => '='
    ];
}

// 目的フィルターを適用
if (!empty($intent_filter)) {
    $args['meta_query'][] = [
        'key' => 'intent',
        'value' => $intent_filter,
        'compare' => '='
    ];
}

// 期間フィルターを適用
if ($status_filter === 'future') {
    $args['meta_query'][] = [
        'key' => 'schedule_date',
        'value' => date('Y-m-d'), // 今日以降のスケジュール
        'compare' => '>=',
        'type' => 'DATE'
    ];
} elseif ($status_filter === 'past') {
    $args['meta_query'][] = [
        'key' => 'schedule_date',
        'value' => date('Y-m-d', strtotime('-1 day')),
        'compare' => '<=',
        'type' => 'DATE'
    ];
} elseif ($status_filter === 'today') {
    $args['meta_query'][] = [
        'key' => 'schedule_date',
        'value' => date('Y-m-d'),
        'compare' => '=',
        'type' => 'DATE'
    ];
}

try {
    if (!isset($schedules)) {
    $schedules = get_posts($args);
    }
} catch (Exception $e) {
    $schedules = [];
}

// マッチ統計情報を取得
$total_schedules = count($schedules);
$total_match_requests = 0;
$auto_requests = 0;
$manual_requests = 0;
$accepted_requests = 0;
$pending_requests = 0;
$rejected_requests = 0;
$canceled_requests = 0;

foreach ($schedules as $schedule) {
    $match_requests = get_posts([
        'post_type' => 'match_request',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'to_schedule_id',
                'value' => $schedule->ID,
            ]
        ]
    ]);

    $total_match_requests += count($match_requests);

    foreach ($match_requests as $request) {
        $is_auto = get_post_meta($request->ID, 'is_auto', true);
        $status = get_post_meta($request->ID, 'status', true);

        if ($is_auto) {
            $auto_requests++;
        } else {
            $manual_requests++;
        }

        switch ($status) {
            case 'accepted':
                $accepted_requests++;
                break;
            case 'pending':
                $pending_requests++;
                break;
            case 'rejected':
                $rejected_requests++;
                break;
            case 'canceled':
                $canceled_requests++;
                break;
        }
    }
}
?>

<style>
.stats-section {
    margin: 30px 0;
    padding: 25px;
    background: var(--bg-secondary);
    border-radius: 12px;
    border: 1px solid var(--border-light);
    box-shadow: var(--shadow-md);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 20px;
    margin-top: 15px;
}

.stat-item {
    text-align: center;
    padding: 20px 15px;
    background: white;
    border-radius: 8px;
    box-shadow: var(--shadow-sm);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stat-item:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.stat-number {
    font-size: 2.5rem;
    font-weight: bold;
    color: var(--primary-color);
    margin-bottom: 8px;
    line-height: 1;
}

.stat-label {
    font-size: 0.9rem;
    color: var(--text-secondary);
    font-weight: 500;
    text-align: center;
}


.past-schedule-actions {
    margin: 1rem 0;
    padding: 1rem;
    background: rgba(255, 193, 7, 0.1);
    border: 1px solid var(--warning-color);
    border-radius: 8px;
}

.match-status-actions {
    margin: 1rem 0;
    padding: 1rem;
    background: rgba(23, 162, 184, 0.1);
    border: 1px solid var(--info-color);
    border-radius: var(--radius-base);
}

.direct-delete-actions {
    margin: 1rem 0;
    padding: 1rem;
    background: rgba(220, 53, 69, 0.1);
    border: 1px solid var(--danger-color);
    border-radius: 8px;
}

.direct-delete-form {
    margin-top: 1rem;
}

.input-group {
    display: flex;
    gap: 10px;
    align-items: center;
    margin-bottom: 0.5rem;
}

.delete-info {
    margin: 1rem 0;
    padding: 1rem;
    background: rgba(23, 162, 184, 0.1);
    border: 1px solid var(--info-color);
    border-radius: 8px;
}

.alert-message {
    margin: 1em 0;
    padding: 10px;
    border-radius: 4px;
}

.alert-warning {
    background-color: rgba(255, 193, 7, 0.1);
    color: var(--warning-color);
    border: 1px solid var(--warning-color);
}

.alert-success {
    background-color: rgba(40, 167, 69, 0.1);
    color: var(--success-color);
    border: 1px solid var(--success-color);
}

.text-success { color: var(--success-color); }
.text-danger { color: var(--danger-color); }
.text-muted { color: var(--text-secondary); }

.page-header {
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 2px solid var(--border-light);
}

.page-header h1 {
    margin-bottom: 10px;
    color: var(--text-primary);
    font-size: 2rem;
    font-weight: 600;
}

.page-header p {
    color: var(--text-secondary);
    font-size: 1.1rem;
    margin: 0;
}

/* 理由バッジのスタイル */
.reason-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 500;
    color: white;
}

.reason-cancelled { background-color: var(--danger-color); }
.reason-no_show { background-color: var(--warning-color); }
.reason-expired { background-color: var(--text-secondary); }
.reason-insufficient_teams { background-color: var(--warning-color); color: var(--text-primary); }
.reason-venue_issue { background-color: var(--info-color); }
.reason-weather { background-color: var(--info-color); }
.reason-other { background-color: var(--secondary-color); }

.reason-none {
    color: var(--text-secondary);
    font-style: italic;
}

/* モーダルのスタイル */
.modal {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
}

.modal-content {
    background-color: var(--bg-primary);
    margin: 5% auto;
    padding: 0;
    border: none;
    border-radius: 8px;
    width: 90%;
    max-width: 500px;
    box-shadow: var(--shadow-xl);
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid var(--border-light);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    color: var(--text-primary);
}

.close {
    color: var(--text-muted);
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover {
    color: var(--text-primary);
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    padding: 20px;
    border-top: 1px solid var(--border-light);
    text-align: right;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: var(--text-primary);
}

.form-group select,
.form-group textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 14px;
}

.form-group textarea {
    resize: vertical;
    min-height: 80px;
}

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    margin-left: 10px;
}

.btn-secondary {
    background-color: var(--text-secondary);
    color: white;
}

.btn-primary {
    background-color: var(--primary-color);
    color: white;
}

.btn:hover {
    opacity: 0.9;
}

/* マッチリクエスト一覧のスタイル */
.match-requests-section {
    margin: 20px 0;
    padding: 20px;
    background: var(--bg-secondary);
    border-radius: 8px;
    border: 1px solid var(--border-color);
}

.match-requests-controls {
    margin-bottom: 15px;
}

.match-requests-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: var(--shadow-sm);
}

.match-requests-table th,
.match-requests-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid var(--border-light);
}

.match-requests-table th {
    background: var(--bg-light);
    font-weight: bold;
    color: var(--text-primary);
}

.match-requests-table tr:hover {
    background: var(--bg-secondary);
}

.status-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
}

.status-申請中 {
    background: rgba(23, 162, 184, 0.1);
    color: var(--info-color);
}

.status-承認済み {
    background: rgba(40, 167, 69, 0.1);
    color: var(--success-color);
}

.status-拒否済み {
    background: rgba(220, 53, 69, 0.1);
    color: var(--danger-color);
}

.status-キャンセル済み {
    background: rgba(177, 108, 234, 0.1);
    color: var(--secondary-color);
}

.btn-delete {
    background: var(--danger-color);
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
}

.btn-delete:hover {
    background: var(--danger-color);
}

.loading, .error, .no-data {
    padding: 20px;
    text-align: center;
    font-size: 16px;
}

.loading {
    color: var(--text-secondary);
}

.error {
    color: var(--danger-color);
    background: rgba(220, 53, 69, 0.1);
    border-radius: 4px;
}

.no-data {
    color: var(--text-secondary);
    background: var(--bg-light);
    border-radius: 4px;
}

/* 強化された削除機能のスタイル */
.enhanced-delete-actions {
    background: rgba(255, 193, 7, 0.1);
    border: 1px solid var(--warning-color);
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.enhanced-delete-actions h4 {
    color: var(--warning-color);
    margin-bottom: 1rem;
}

.enhanced-delete-actions .action-buttons {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}

.cleanup-btn {
    background: var(--info-color);
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 4px;
    cursor: pointer;
    font-weight: bold;
    transition: background-color 0.3s;
}

.cleanup-btn:hover {
    background: var(--info-color);
}

.danger-btn {
    background: var(--danger-color);
    color: var(--text-light);
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 4px;
    cursor: pointer;
    font-weight: bold;
    transition: background-color 0.3s;
}

.danger-btn:hover {
    background: var(--danger-color);
}

.enhanced-delete-actions .action-info {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: 4px;
    padding: 1rem;
    font-size: 0.9rem;
}

.enhanced-delete-actions .action-info ul {
    margin: 0.5rem 0 0 1.5rem;
    padding: 0;
}

.enhanced-delete-actions .action-info li {
    margin-bottom: 0.25rem;
}
</style>

<?php
$schedule_use_integrated_ui = function_exists('aidunite_should_use_web_app_integrated_ui')
    && aidunite_should_use_web_app_integrated_ui();
$schedule_subtitle = $is_admin
    ? 'システムに登録されている全チームのスケジュールを管理できます。'
    : 'あなたのチームのスケジュールを確認できます。';
$schedule_hero_actions = [];
if ($effective_role === 'team_leader' || $is_admin) {
    $schedule_create_url = home_url('/schedule-management');
    $schedule_hero_actions[] = [
        'type'  => 'link',
        'href'  => $schedule_create_url,
        'icon'  => '＋',
        'aria'  => 'スケジュールを登録',
    ];
}
?>
<div class="wrap<?php echo $schedule_use_integrated_ui ? ' ainy-webapp-page' : ''; ?>">
    <?php if (isset($delete_message)) echo $delete_message; ?>

    <?php
    if ($schedule_use_integrated_ui && function_exists('aidunite_render_web_app_page_hero')) {
        aidunite_render_web_app_page_hero([
            'size'       => 'sm',
            'title'      => 'スケジュール一覧',
            'subtitle'   => $schedule_subtitle,
            'back'       => true,
            'active_nav' => 'schedule',
            'actions'    => $schedule_hero_actions,
        ]);
        aidunite_render_web_app_content_open('ainy-webapp-content--schedule-list');
    } else {
        ?>
    <div class="page-header">
        <h1>スケジュール一覧</h1>
        <p><?php echo esc_html($schedule_subtitle); ?></p>
    </div>
        <?php
    }
    ?>

        <?php if ($is_admin): ?>
        <!-- 過去スケジュール処理ボタン -->
        <div class="past-schedule-actions">
            <h4>🕐 過去スケジュール管理</h4>
            <p>日程が過ぎたスケジュールを自動的に「完了」ステータスに更新します。</p>
            <div class="action-buttons">
                <a href="?page_id=<?= get_the_ID() ?>&process_past_schedules=1" class="process-btn" onclick="return confirm('過去のスケジュールを完了ステータスに更新しますか？')">
                    🔄 過去スケジュールを完了処理
                </a>
                <span class="action-info">※ 管理者のみ実行可能</span>
            </div>
        </div>

        <!-- 申請ステータス管理 -->
        <div class="match-status-actions">
            <h4>📋 申請ステータス管理</h4>
            <p>マッチリクエストの申請を一括で削除できます。</p>
            <div class="action-buttons">
                <button type="button" class="process-btn" onclick="resetAllMatchStatuses()">
                    🗑️ 全申請を削除
                </button>
                <button type="button" class="process-btn" onclick="resetPendingStatuses()">
                    ⏳ 申請中の申請のみ削除
                </button>
                <button type="button" class="process-btn" onclick="showMatchRequestsList()">
                    📋 申請一覧を表示
                </button>
                <span class="action-info">※ 管理者のみ実行可能</span>
            </div>
        </div>
        <?php endif; ?>

        <!-- マッチリクエスト一覧表示エリア -->
        <div id="match-requests-list" class="match-requests-section" style="display: none;">
            <h4>📋 マッチリクエスト一覧</h4>
            <div class="match-requests-controls">
                <button type="button" class="process-btn" onclick="refreshMatchRequestsList()">
                    🔄 一覧を更新
                </button>
                <button type="button" class="process-btn" onclick="deleteAllMatchRequests()">
                    🗑️ 全申請を削除
                </button>
            </div>
            <div id="match-requests-table-container">
                <!-- マッチリクエスト一覧がここに表示されます -->
            </div>
        </div>

        <!-- スケジュールID直接削除 -->
        <div class="direct-delete-actions">
            <h4>🗑️ スケジュールID直接削除</h4>
            <p>スケジュールIDを直接指定して削除できます（一覧に表示されていないスケジュールも削除可能）。</p>
            <div class="direct-delete-form">
                <div class="input-group">
                    <input type="number" id="directDeleteId" placeholder="スケジュールIDを入力" min="1" style="padding: var(--spacing-xs); border: 1px solid var(--border-color); border-radius: var(--radius-small); width: 200px;">
                    <button type="button" class="process-btn" onclick="deleteScheduleById()">
                        🗑️ 指定IDで削除
                    </button>
                </div>
                <span class="action-info">※ 関連するマッチリクエストも一緒に削除されます</span>
            </div>
        </div>

        <!-- 削除機能情報 -->
        <div class="delete-info">
            <h4>🗑️ 削除機能</h4>
            <p>個別削除：各行の「削除」ボタンで個別にスケジュールを削除できます。</p>
            <p>一括削除：チェックボックスで複数選択し、「選択したスケジュールを削除」ボタンで一括削除できます。</p>
            <div class="delete-permissions">
                <strong>現在の権限:</strong>
                <?php if (current_user_can('delete_posts')): ?>
                    <span class="text-success">✅ 削除権限あり</span>
                <?php else: ?>
                    <span class="text-danger">❌ 削除権限なし</span>
                <?php endif; ?>
                |
                <?php if (current_user_can('administrator')): ?>
                    <span class="text-success">✅ 管理者権限あり</span>
                <?php else: ?>
                    <span class="text-danger">❌ 管理者権限なし</span>
                <?php endif; ?>
                |
                <span class="text-muted">ユーザーID: <?= get_current_user_id() ?></span>
            </div>
        </div>

        <!-- 強化された削除機能 -->
        <div class="enhanced-delete-actions">
            <h4>🗑️ 強化された削除機能</h4>
            <p>より高度な削除操作を実行できます。</p>
            <div class="action-buttons">
                <!-- 削除済みスケジュールの完全削除 -->
                <form method="post" style="display: inline;">
                    <?php wp_nonce_field('cleanup_trashed_schedules_action', 'cleanup_trashed_schedules_nonce'); ?>
                    <button type="submit" name="cleanup_trashed_schedules" class="process-btn cleanup-btn"
                            onclick="return confirm('削除済み（ゴミ箱）のスケジュールを完全削除しますか？\n\n※関連するマッチリクエストも一緒に削除されます。\nこの操作は取り消すことができません。')">
                        🗑️ 削除済みスケジュールを完全削除
                    </button>
                </form>

                <!-- 全スケジュールの完全削除（危険な操作） -->
                <form method="post" style="display: inline;">
                    <?php wp_nonce_field('delete_all_schedules_action', 'delete_all_schedules_nonce'); ?>
                    <button type="submit" name="delete_all_schedules" class="process-btn danger-btn"
                            onclick="return confirm('⚠️ 警告：全スケジュールを完全削除しますか？\n\n※公開中・下書き・削除済みを含む全てのスケジュールが削除されます。\n※関連するマッチリクエストも一緒に削除されます。\nこの操作は取り消すことができません。\n\n本当に実行しますか？')">
                        ⚠️ 全スケジュールを完全削除
                    </button>
                </form>
            </div>
            <div class="action-info">
                <strong>注意事項:</strong>
                <ul>
                    <li>「削除済みスケジュールを完全削除」：ゴミ箱にあるスケジュールのみを削除</li>
                    <li>「全スケジュールを完全削除」：全てのスケジュール（公開中・下書き・削除済み）を削除</li>
                    <li>どちらの操作も関連するマッチリクエストも一緒に削除されます</li>
                    <li>これらの操作は取り消すことができません</li>
                </ul>
            </div>
        </div>
    </div>

    <?php if (is_wp_error($schedules)): ?>
        <div class="error-message">
            <h3>❌ エラーが発生しました</h3>
            <p><?= esc_html($schedules->get_error_message()) ?></p>
        </div>
    <?php else: ?>
        <!-- フィルター -->
        <div class="filter-section">
            <form method="get" class="filter-form">
                <div class="filter-row">
                    <div class="filter-item">
                        <label for="month">月:</label>
                        <select name="month" id="month">
                            <option value="0">全月</option>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= $month_filter == $m ? 'selected' : '' ?>>
                                    <?= $m ?>月
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="filter-item">
                        <label for="year">年:</label>
                        <select name="year" id="year">
                            <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
                                <option value="<?= $y ?>" <?= $year_filter == $y ? 'selected' : '' ?>>
                                    <?= $y ?>年
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="filter-item">
                        <label for="type">種別:</label>
                        <select name="type" id="type">
                            <option value="">全種別</option>
                            <option value="practice" <?= $type_filter === 'practice' ? 'selected' : '' ?>>練習</option>
                            <option value="official_match" <?= $type_filter === 'official_match' ? 'selected' : '' ?>>公式試合</option>
                            <option value="practice_match" <?= $type_filter === 'practice_match' ? 'selected' : '' ?>>練習試合</option>
                            <option value="joint_practice" <?= $type_filter === 'joint_practice' ? 'selected' : '' ?>>合同練習</option>
                            <option value="rest" <?= $type_filter === 'rest' ? 'selected' : '' ?>>休み</option>
                            <option value="event" <?= $type_filter === 'event' ? 'selected' : '' ?>>イベント</option>
                            <option value="training_camp" <?= $type_filter === 'training_camp' ? 'selected' : '' ?>>合宿</option>
                            <option value="away_game" <?= $type_filter === 'away_game' ? 'selected' : '' ?>>遠征</option>
                        </select>
                    </div>

                    <div class="filter-item">
                        <label for="intent">目的:</label>
                        <select name="intent" id="intent">
                            <option value="">全目的</option>
                            <option value="confirmed" <?= (isset($_GET['intent']) && $_GET['intent'] === 'confirmed') ? 'selected' : '' ?>>確定の予定</option>
                            <option value="recruit" <?= (isset($_GET['intent']) && $_GET['intent'] === 'recruit') ? 'selected' : '' ?>>マッチ希望</option>
                            <option value="tentative" <?= (isset($_GET['intent']) && $_GET['intent'] === 'tentative') ? 'selected' : '' ?>>仮押さえ</option>
                        </select>
                    </div>

                    <div class="filter-item">
                        <label for="status">期間:</label>
                        <select name="status" id="status">
                            <option value="all" <?= (!isset($_GET['status']) || $_GET['status'] === 'all') ? 'selected' : '' ?>>全て</option>
                            <option value="future" <?= (isset($_GET['status']) && $_GET['status'] === 'future') ? 'selected' : '' ?>>今後のみ</option>
                            <option value="past" <?= (isset($_GET['status']) && $_GET['status'] === 'past') ? 'selected' : '' ?>>過去のみ</option>
                            <option value="today" <?= (isset($_GET['status']) && $_GET['status'] === 'today') ? 'selected' : '' ?>>今日のみ</option>
                        </select>
                    </div>

                    <div class="filter-item">
                        <button type="submit" class="filter-btn">フィルター適用</button>
                        <a href="?page_id=<?= get_the_ID() ?>" class="filter-reset">リセット</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- マッチ統計情報 -->
        <div class="stats-section">
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $total_schedules; ?></div>
                    <div class="stat-label">総スケジュール数</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $total_match_requests; ?></div>
                    <div class="stat-label">マッチ希望</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $accepted_requests; ?></div>
                    <div class="stat-label">確定の予定</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $pending_requests; ?></div>
                    <div class="stat-label">仮押さえ</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $auto_requests + $manual_requests; ?></div>
                    <div class="stat-label">対象チーム数</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php
                        $total_visitors = 0;
                        $total_views = 0;
                        foreach ($schedules as $schedule) {
                            $total_visitors += get_post_meta($schedule->ID, 'unique_visitors', true) ?: 0;
                            $total_views += get_post_meta($schedule->ID, 'view_count', true) ?: 0;
                        }
                        echo $total_visitors;
                    ?></div>
                    <div class="stat-label">訪問数合計</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $total_views; ?></div>
                    <div class="stat-label">閲覧合計</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $total_match_requests; ?></div>
                    <div class="stat-label">マッチ件数</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $accepted_requests; ?></div>
                    <div class="stat-label">成立数</div>
                </div>
            </div>
        </div>

        <!-- スケジュール一覧表示 -->
        <div class="schedule-display-container">
            <div class="display-header">
                <h3>📋 スケジュール一覧</h3>
                <div class="display-controls">
                    <div class="view-toggle">
                        <button class="btn btn-sm btn-outline active" data-view="cards" onclick="toggleScheduleDisplay('cards')">
                            📋 カード表示
                        </button>
                        <button class="btn btn-sm btn-outline" data-view="table" onclick="toggleScheduleDisplay('table')">
                            📊 テーブル表示
                        </button>
                    </div>
                    <?php if ($is_admin): ?>
                    <div class="bulk-actions">
                        <button type="button" class="btn btn-sm btn-danger" onclick="bulkDeleteSchedules()">
                            🗑️ 選択した項目を削除
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- カード表示 -->
            <div id="schedule-cards-view" class="schedule-view active">
                <div class="schedule-cards-grid">
                    <?php if (empty($schedules)): ?>
                        <?php
                        // 統一空の状態コンポーネントを使用
                        echo aidunite_empty_state([
                            'title' => 'スケジュールが登録されていません',
                            'message' => '表示するスケジュールがありません。',
                            'description' => 'スケジュールを登録すると、ここに表示されます。',
                            'action' => [
                                'text' => 'スケジュールを登録',
                                'url' => home_url('/schedule-edit')
                            ],
                            'type' => 'default'
                        ]);
                        ?>
                    <?php else: ?>
                        <?php foreach ($schedules as $schedule): ?>
                            <?php
                            $schedule_id = $schedule->ID;
                            $date = get_post_meta($schedule_id, 'schedule_date', true);
                            $start_time = get_post_meta($schedule_id, 'schedule_start_time', true);
                            $end_time = get_post_meta($schedule_id, 'schedule_end_time', true);
                            $type = get_post_meta($schedule_id, 'schedule_type', true);
                            $place = get_post_meta($schedule_id, 'schedule_place', true);
                            $note = get_post_meta($schedule_id, 'schedule_note', true);
                            $matching = get_post_meta($schedule_id, 'matching', true);

                            // チーム名取得
                            $team_id = get_post_meta($schedule_id, 'team_id', true);
                            $team_name = '';
                            if ($team_id) {
                                $team_post = get_post($team_id);
                                $team_name = $team_post ? $team_post->post_title : '不明なチーム';
                            }

                            // 日付フォーマット
                            $formatted_date = $date ? date('Y年n月j日', strtotime($date)) : '日付未設定';
                            $time_display = ($start_time && $end_time) ? "{$start_time} - {$end_time}" : '時間未設定';
                            ?>
                            <div class="schedule-card">
                                <div class="card-header">
                                    <div class="card-date"><?= esc_html($formatted_date) ?></div>
                                    <div class="card-actions">
                                        <?php if ($is_admin): ?>
                                            <a href="<?= get_edit_post_link($schedule_id) ?>" class="btn btn-sm btn-primary">編集</a>
                                        <?php else: ?>
                                            <!-- 一般ユーザー向け出欠機能（準備中） -->
                                            <div class="attendance-actions">
                                                <button class="btn btn-sm btn-success" onclick="markAttendance(<?= $schedule_id ?>, 'attending')" title="出席">
                                                    ✅ 出席
                                                </button>
                                                <button class="btn btn-sm btn-warning" onclick="markAttendance(<?= $schedule_id ?>, 'not_attending')" title="欠席">
                                                    ❌ 欠席
                                                </button>
                                                <button class="btn btn-sm btn-info" onclick="markAttendance(<?= $schedule_id ?>, 'pending')" title="未定">
                                                    ⏳ 未定
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <h4 class="card-title"><?= esc_html($type ?: 'スケジュール') ?></h4>
                                    <div class="card-details">
                                        <div class="detail-item">
                                            <span class="detail-label">⏰ 時間:</span>
                                            <span class="detail-value"><?= esc_html($time_display) ?></span>
                                        </div>
                                        <?php if ($place): ?>
                                        <div class="detail-item">
                                            <span class="detail-label">📍 場所:</span>
                                            <span class="detail-value"><?= esc_html($place) ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($team_name && $is_admin): ?>
                                        <div class="detail-item">
                                            <span class="detail-label">🏀 チーム:</span>
                                            <span class="detail-value"><?= esc_html($team_name) ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($matching): ?>
                                        <div class="detail-item">
                                            <span class="detail-label">🤝 マッチ希望:</span>
                                            <span class="detail-value">✅</span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($note): ?>
                                    <div class="card-note">
                                        <span class="note-label">📝 備考:</span>
                                        <span class="note-text"><?= esc_html($note) ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- テーブル表示 -->
            <div id="schedule-table-view" class="schedule-view">
                <div class="schedule-table-container">
                    <?php if (empty($schedules)): ?>
            <div class="no-schedules">
                <p>指定された条件に該当するスケジュールはありません。</p>
            </div>
                    <?php else: ?>
            <!-- 一括操作セクション -->
            <div class="bulk-actions-section">
                <div class="bulk-actions-header">
                    <div class="bulk-actions-info">
                        <span id="selected-count">0</span>件選択中
                    </div>
                    <div class="bulk-actions-buttons">
                        <button type="button" id="select-all-btn" class="bulk-btn">全選択</button>
                        <button type="button" id="deselect-all-btn" class="bulk-btn">選択解除</button>
                        <button type="button" id="bulk-delete-btn" class="bulk-btn bulk-delete-btn" disabled>選択したスケジュールを削除</button>
                    </div>
                </div>
            </div>

            <div class="schedule-table-container">
                <table class="schedule-table">
      <thead>
                        <tr>
                            <th class="checkbox-header">
                                <input type="checkbox" id="select-all-checkbox">
                            </th>
                            <th>登録日</th>
                            <th>主催チーム</th>
                            <th>主催ID</th>
                            <th>スケジュールID</th>
                            <th>目的</th>
                            <th>種別</th>
                            <th>練習or試合日</th>
                            <th>参加数</th>
                            <th>参加一覧</th>
                            <th>定員充足率</th>
                            <th>開始時間</th>
                            <th>会場名</th>
                            <th>会場条件</th>
                            <th>性別条件</th>
                            <th>男子 成立数/定員</th>
                            <th>女子 成立数/定員</th>
                            <th>閲覧数</th>
                            <th>訪問数</th>
                            <th>地域</th>
                            <th>種目</th>
                            <th>レベル</th>
                            <th>参加費</th>
                            <th>作成者</th>
                            <th>最終更新</th>
                            <th>理由</th>
                            <th>操作</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($schedules as $schedule):
                            try {
                                $schedule_id = $schedule->ID;
          $author_id = $schedule->post_author;
                                $team_id = get_post_meta($schedule_id, 'team_id', true);
                                $team_name = '';

                                if ($team_id) {
                                    $team_post = get_post($team_id);
                                    $team_name = $team_post ? $team_post->post_title : 'チームID: ' . $team_id;
                                } else {
                                    $user_teams = get_posts([
                                        'post_type' => 'team',
                                        'meta_query' => [
                                            [
                                                'key' => 'team_members',
                                                'value' => '"' . $author_id . '"',
                                                'compare' => 'LIKE'
                                            ]
                                        ],
                                        'posts_per_page' => 1
                                    ]);

                                    if (!empty($user_teams)) {
                                        $team_name = $user_teams[0]->post_title;
                                    } else {
                                        $team_name = get_the_author_meta('display_name', $author_id);
                                    }
                                }

                                $date = get_post_meta($schedule_id, 'schedule_date', true);
                                $start_time = get_post_meta($schedule_id, 'schedule_start_time', true);
                                $end_time = get_post_meta($schedule_id, 'schedule_end_time', true);
                                $type = get_post_meta($schedule_id, 'schedule_type', true);
                                $intent = get_post_meta($schedule_id, 'intent', true);
                                $certainty = get_post_meta($schedule_id, 'certainty', true);
                                $place = get_post_meta($schedule_id, 'schedule_place', true);
                                $note = get_post_meta($schedule_id, 'schedule_note', true);
                                $matching = get_post_meta($schedule_id, 'matching', true);
                                // Phase 2: 統一メタキーを優先、後方互換性のために旧キーもフォールバック
                                $gender_condition = get_post_meta($schedule_id, 'schedule_gender', true);
                                if (!$gender_condition) {
                                    $gender_condition = get_post_meta($schedule_id, 'matching_gender_condition', true);
                                }
                                $capacity = get_post_meta($schedule_id, 'capacity', true);

                                // 追加データの取得
                                $created_date = $schedule->post_date;
                                $modified_date = $schedule->post_modified;
                                $venue_name = get_post_meta($schedule_id, 'venue_name', true);
                                $place_condition = get_post_meta($schedule_id, 'schedule_place', true);
                                if (!$place_condition) {
                                    $place_condition = get_post_meta($schedule_id, 'schedule_place_option', true);
                                }
                                $male_capacity = get_post_meta($schedule_id, 'male_capacity', true);
                                $female_capacity = get_post_meta($schedule_id, 'female_capacity', true);
                                $team_area = get_post_meta($team_id, 'team_area', true);
                                $sport_type = get_post_meta($team_id, 'sport_type', true);
                                $team_level = get_post_meta($team_id, 'team_level', true);
                                $participation_fee = get_post_meta($schedule_id, 'participation_fee', true);
                                $author_name = get_the_author_meta('display_name', $author_id);

                                // 参加チーム数と一覧の取得
                                $participants = get_post_meta($schedule_id, 'participants', true);
                                $participant_count = 0;
                                $participants_list = '-';
                                if ($participants) {
                                    $participant_ids = explode(',', $participants);
                                    $participant_count = count(array_filter($participant_ids));
                                    if ($participant_count > 0) {
                                        $participant_names = [];
                                        foreach ($participant_ids as $pid) {
                                            $team_post = get_post($pid);
                                            if ($team_post) {
                                                $participant_names[] = $team_post->post_title;
                                            }
                                        }
                                        $participants_list = implode(', ', $participant_names);
                                    }
                                }

                                // 定員充足率の計算
                                $total_capacity = ($male_capacity ?: 0) + ($female_capacity ?: 0);
                                $capacity_rate = $total_capacity > 0 ? round(($participant_count / $total_capacity) * 100, 1) : 0;

                                // 閲覧数・訪問数の取得（仮の値）
                                $view_count = get_post_meta($schedule_id, 'view_count', true) ?: 0;
                                $unique_visitors = get_post_meta($schedule_id, 'unique_visitors', true) ?: 0;

                                // 理由の取得
                                $cancellation_reason = get_post_meta($schedule_id, 'cancellation_reason', true);
                                $reason_display = '';
                                if ($cancellation_reason) {
                                    $reason_options = [
                                        'cancelled' => 'キャンセル',
                                        'no_show' => '無断',
                                        'expired' => '期限過ぎた',
                                        'insufficient_teams' => 'チーム数不足',
                                        'venue_issue' => '会場問題',
                                        'weather' => '天候不良',
                                        'other' => 'その他'
                                    ];
                                    $reason_display = $reason_options[$cancellation_reason] ?? $cancellation_reason;
                                }

                                if ($date) {
                                    $date_obj = DateTime::createFromFormat('Y-m-d', $date);
                                    if ($date_obj) {
                                        $date = $date_obj->format('Y-m-d');
                                    } else {
                                        $date = '日付不明';
                                    }
                                }

                                // ステータスを判定
                                $status = get_post_meta($schedule_id, 'schedule_status', true);
                                $today = date('Y-m-d');

                                if (empty($status)) {
                                    if ($date < $today) {
                                        $status = 'past';
                                        $status_display = '<span class="status-past">過去</span>';
                                    } elseif ($date == $today) {
                                        $status = 'today';
                                        $status_display = '<span class="status-today">今日</span>';
                                    } else {
                                        $status = 'future';
                                        $status_display = '<span class="status-future">今後</span>';
                                    }
                                } else {
                                    switch ($status) {
                                        case 'completed':
                                            $status_display = '<span class="status-completed">完了</span>';
                                            break;
                                        case 'cancelled':
                                            $status_display = '<span class="status-cancelled">キャンセル</span>';
                                            break;
                                        default:
                                            $status_display = '<span class="status-unknown">' . esc_html($status) . '</span>';
                                    }
                                }

                                // 時間表示の整形
                                $time_display = '';
                                if ($start_time && $end_time) {
                                    $time_display = $start_time . ' - ' . $end_time;
                                } elseif ($start_time) {
                                    $time_display = $start_time;
                                }

                                // 種別の日本語表示
                                $type_display = [
                                    'practice' => '練習',
                                    'official_match' => '公式試合',
                                    'practice_match' => '練習試合',
                                    'joint_practice' => '合同練習',
                                    'rest' => '休み',
                                    'event' => 'イベント',
                                    'training_camp' => '合宿',
                                    'away_game' => '遠征'
                                ][$type] ?? $type;

                                // 目的の日本語表示
                                $intent_display = [
                                    'confirmed' => '確定の予定',
                                    'recruit' => 'マッチ希望',
                                    'tentative' => '仮押さえ'
                                ][$intent] ?? $intent;

                                // 確定度の日本語表示
                                $certainty_display = [
                                    'firm' => '確定',
                                    'tentative' => '仮'
                                ][$certainty] ?? $certainty;

                                // 会場の日本語表示
                                $place_display = [
                                    'home' => 'ホーム開催',
                                    'away' => 'アウェイ開催',
                                    'either' => 'どちらでも'
                                ][$place] ?? $place;

                                $team_count_display = $capacity ? $capacity . 'チーム' : '-';
                            ?>
                            <tr class="schedule-row status-<?= $status ?>" data-schedule-id="<?= $schedule_id ?>">
                                <td class="checkbox-cell">
                                    <input type="checkbox" name="selected_schedules[]" class="schedule-checkbox" value="<?= $schedule_id ?>">
                                </td>
                                <td class="created-date"><?= esc_html(date('Y-m-d H:i', strtotime($created_date))) ?></td>
                                <td class="team-name"><?= esc_html($team_name) ?></td>
                                <td class="team-id"><?= esc_html($team_id) ?></td>
                                <td class="schedule-id"><?= esc_html($schedule_id) ?></td>
                                <td class="schedule-intent"><?= esc_html($intent_display) ?></td>
                                <td class="schedule-type"><?= esc_html($type_display) ?></td>
                                <td class="schedule-date"><?= esc_html($date) ?></td>
                                <td class="participant-count"><?= esc_html($participant_count) ?></td>
                                <td class="participants-list"><?= esc_html($participants_list) ?></td>
                                <td class="capacity-rate"><?= esc_html($capacity_rate) ?>%</td>
                                <td class="start-time"><?= esc_html($time_display) ?></td>
                                <td class="venue-name"><?= esc_html($venue_name ?: '-') ?></td>
                                <td class="place-condition">
                                            <?php
                                    $place_condition_display = [
                                        'home' => 'ホーム',
                                        'away' => 'アウェイ',
                                        'either' => 'どちらでも'
                                    ][$place_condition] ?? ($place_condition ?: '-');
                                    echo esc_html($place_condition_display);
                                    ?>
                                </td>
                                <td class="gender-condition">
                                    <?php
                                    $gender_condition_display = [
                                                'male' => '男子のみ',
                                                'female' => '女子のみ',
                                                'both' => '男子・女子可'
                                    ][$gender_condition] ?? ($gender_condition ?: '-');
                                    echo esc_html($gender_condition_display);
                                    ?>
                                </td>
                                <td class="male-capacity"><?= esc_html($participant_count) ?>/<?= esc_html($male_capacity ?: 0) ?></td>
                                <td class="female-capacity"><?= esc_html($participant_count) ?>/<?= esc_html($female_capacity ?: 0) ?></td>
                                <td class="view-count"><?= esc_html($view_count) ?></td>
                                <td class="unique-visitors"><?= esc_html($unique_visitors) ?></td>
                                <td class="team-area"><?= esc_html($team_area ?: '-') ?></td>
                                <td class="sport-type"><?= esc_html($sport_type ?: '-') ?></td>
                                <td class="team-level"><?= esc_html($team_level ?: '-') ?></td>
                                <td class="participation-fee"><?= esc_html($participation_fee ?: '-') ?></td>
                                <td class="author-name"><?= esc_html($author_name) ?></td>
                                <td class="modified-date"><?= esc_html(date('Y-m-d H:i', strtotime($modified_date))) ?></td>
                                <td class="cancellation-reason">
                                    <?php if ($reason_display): ?>
                                        <span class="reason-badge reason-<?= esc_attr($cancellation_reason) ?>"><?= esc_html($reason_display) ?></span>
                                    <?php else: ?>
                                        <span class="reason-none">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="schedule-actions">
                                    <?php if ($is_admin): ?>
                                    <a href="<?= get_edit_post_link($schedule_id) ?>" class="edit-link">編集</a>
                                    <a href="<?= get_permalink($schedule_id) ?>" class="view-link">表示</a>
                                    <button type="button" class="reason-link" onclick="openReasonModal(<?= $schedule_id ?>, '<?= esc_js($team_name) ?>', '<?= esc_js($cancellation_reason) ?>')">理由</button>
                                    <button type="button" class="delete-link" onclick="deleteSchedule(<?= $schedule_id ?>, '<?= esc_js($team_name) ?>', '<?= esc_js($date) ?>')">削除</button>
                                    <?php else: ?>
                                        <!-- 一般ユーザー向け出欠機能（準備中） -->
                                        <div class="attendance-actions">
                                            <button class="btn btn-sm btn-success" onclick="markAttendance(<?= $schedule_id ?>, 'attending')" title="出席">
                                                ✅ 出席
                                            </button>
                                            <button class="btn btn-sm btn-warning" onclick="markAttendance(<?= $schedule_id ?>, 'not_attending')" title="欠席">
                                                ❌ 欠席
                                            </button>
                                            <button class="btn btn-sm btn-info" onclick="markAttendance(<?= $schedule_id ?>, 'pending')" title="未定">
                                                ⏳ 未定
                                            </button>
                                        </div>
                                    <?php endif; ?>
          </td>
        </tr>
                            <?php } catch (Exception $e) {
                                echo '<tr><td colspan="12" class="error">スケジュールの表示でエラーが発生しました</td></tr>';
                            } ?>
        <?php endforeach; ?>
      </tbody>
    </table>
            </div>
        <?php endif; ?>
  <?php endif; ?>

<?php
if ($schedule_use_integrated_ui && function_exists('aidunite_render_web_app_content_close')) {
    aidunite_render_web_app_content_close();
}
?>
</div>

<style>
.page-header {
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--primary-color);
}

.page-header h1 {
    color: var(--primary-color);
    margin-bottom: 0.5rem;
}

.filter-section {
    background: var(--bg-secondary);
    padding: 1.5rem;
    border-radius: 8px;
    margin-bottom: 2rem;
}

.filter-form {
    margin: 0;
}

.filter-row {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    align-items: end;
}

.filter-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-item label {
    font-weight: bold;
    color: var(--text-primary);
}

.filter-item select {
    padding: 0.5rem;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    min-width: 120px;
}

.filter-btn {
    background: var(--primary-color);
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 4px;
    cursor: pointer;
    font-weight: bold;
}

.filter-btn:hover {
    background: var(--primary-dark);
}

.filter-reset {
    background: var(--text-secondary);
    color: white;
    text-decoration: none;
    padding: 0.5rem 1rem;
    border-radius: 4px;
    font-weight: bold;
}

.filter-reset:hover {
    background: var(--text-secondary);
    color: white;
}

.stats-section {
    margin-bottom: 2rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.stat-item {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    text-align: center;
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border-light);
}

.stat-number {
    font-size: 2rem;
    font-weight: bold;
    color: var(--primary-color);
    margin-bottom: 0.5rem;
}

.stat-label {
    color: var(--text-secondary);
    font-weight: bold;
}

.schedule-table-container {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: var(--shadow-sm);
}

.schedule-table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

.schedule-table th {
    background: var(--bg-secondary);
    padding: 1rem;
    text-align: left;
    font-weight: bold;
    color: var(--text-primary);
    border-bottom: 2px solid var(--border-color);
}

.schedule-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--border-light);
    vertical-align: top;
}

.schedule-table tr:hover {
    background: var(--bg-secondary);
}

.checkbox-header {
    width: 50px; /* チェックボックスの幅 */
    text-align: center;
}

.checkbox-cell {
    width: 50px; /* チェックボックスの幅 */
    text-align: center;
}

.checkbox-header input[type="checkbox"],
.checkbox-cell input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.schedule-id {
    font-family: monospace;
    font-weight: bold;
    color: var(--text-secondary);
}

.team-name {
    font-weight: bold;
    color: var(--primary-color);
}

.schedule-date {
    font-weight: bold;
}

.schedule-time {
    font-family: monospace;
}

.schedule-type {
    background: rgba(23, 162, 184, 0.1);
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.9rem;
    font-weight: bold;
}

.schedule-intent {
    background: var(--border-light);
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.9rem;
    font-weight: bold;
}

.schedule-certainty {
    background: var(--bg-secondary);
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.9rem;
    font-weight: bold;
}

.schedule-place {
    color: var(--text-secondary);
}

.matching-yes {
    color: var(--success-color);
    font-weight: bold;
}

.matching-no {
    color: var(--danger-color);
}

.schedule-capacity {
    font-weight: bold;
    color: var(--primary-color);
}

.schedule-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.edit-link, .view-link, .delete-link {
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: bold;
    border: none;
    cursor: pointer;
    transition: background-color 0.3s ease;
    white-space: nowrap;
}

.edit-link {
    background: var(--primary-color);
    color: white;
}

.edit-link:hover {
    background: var(--primary-dark);
}

.view-link {
    background: var(--success-color);
    color: white;
}

.view-link:hover {
    background: var(--success-color);
}

.delete-link {
    background: var(--danger-color);
    color: white;
}

.delete-link:hover {
    background: var(--danger-color);
}

.no-schedules {
    text-align: center;
    padding: 3rem;
    background: white;
    border-radius: 8px;
    color: var(--text-secondary);
}

.error-message {
    background-color: rgba(220, 53, 69, 0.1);
    color: var(--danger-color);
    padding: var(--spacing-base);
    border-radius: var(--radius-base);
    margin-bottom: 2rem;
    border: 1px solid var(--danger-color);
}

.error-message h3 {
    margin-top: 0;
    margin-bottom: 0.5rem;
    color: var(--danger-color);
}

.error-message p {
    margin-bottom: 0;
    font-size: 0.9rem;
}

/* ステータススタイル */
.status-past {
    color: var(--text-secondary);
    font-weight: bold;
}

.status-today {
    color: var(--info-color);
    font-weight: bold;
}

.status-future {
    color: var(--primary-color);
    font-weight: bold;
}

.status-completed {
    color: var(--success-color);
    font-weight: bold;
}

.status-cancelled {
    color: var(--danger-color);
    font-weight: bold;
}

.status-unknown {
    color: var(--text-secondary);
    font-weight: bold;
}

/* 過去スケジュール処理ボタンスタイル */
.past-schedule-actions {
    margin: 1rem 0;
    padding: 1rem;
    background: rgba(255, 193, 7, 0.1);
    border: 1px solid var(--warning-color);
    border-radius: 8px;
}

.past-schedule-actions h4 {
    margin-top: 0;
    margin-bottom: 0.5rem;
    color: var(--warning-color);
}

.past-schedule-actions p {
    margin-bottom: 1rem;
    color: var(--warning-color);
}

.action-buttons {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.process-btn {
    background: var(--warning-color);
    color: var(--text-primary);
    text-decoration: none;
    padding: 0.5rem 1rem;
    border-radius: 4px;
    font-weight: bold;
    transition: background-color 0.3s ease;
}

.process-btn:hover {
    background: var(--warning-color);
    color: var(--text-primary);
}

.action-info {
    color: var(--warning-color);
    font-size: 0.9rem;
    font-style: italic;
}

.delete-info {
    margin: 1rem 0;
    padding: 1rem;
    background: rgba(23, 162, 184, 0.1);
    border: 1px solid var(--info-color);
    border-radius: 8px;
}

.delete-info h4 {
    margin-top: 0;
    margin-bottom: 0.5rem;
    color: var(--info-color);
}

.delete-info p {
    margin-bottom: 1rem;
    color: var(--info-color);
}

.delete-permissions {
    font-size: 0.9rem;
    color: var(--text-primary);
}

.delete-permissions strong {
    font-weight: bold;
}

.bulk-actions-section {
    background: var(--bg-secondary);
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 2rem;
    border: 1px solid var(--border-color);
}

.bulk-actions-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.bulk-actions-info {
    font-weight: bold;
    color: var(--text-primary);
    font-size: 1.1rem;
}

.bulk-actions-buttons {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.bulk-btn {
    background: var(--primary-color);
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 4px;
    cursor: pointer;
    font-weight: bold;
    transition: background-color 0.3s ease;
    font-size: 0.9rem;
}

.bulk-btn:hover {
    background: var(--primary-dark);
}

.bulk-btn:disabled {
    background: var(--text-secondary);
    cursor: not-allowed;
    opacity: 0.6;
}

.bulk-delete-btn {
    background: var(--danger-color);
}

.bulk-delete-btn:hover:not(:disabled) {
    background: var(--danger-color);
}

@media (max-width: 768px) {
    .filter-row {
        flex-direction: column;
        align-items: stretch;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }

    .schedule-table {
        font-size: 0.9rem;
    }

    .schedule-table th,
    .schedule-table td {
        padding: 0.5rem;
    }

    .schedule-actions {
        flex-direction: column;
        gap: 0.25rem;
    }

    .bulk-actions-header {
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
    }

    .bulk-actions-buttons {
        justify-content: center;
    }

    .checkbox-header,
    .checkbox-cell {
        width: 40px;
    }

    .checkbox-header input[type="checkbox"],
    .checkbox-cell input[type="checkbox"] {
        width: 16px;
        height: 16px;
    }
}
</style>

<?php
try {
    get_footer();
} catch (Exception $e) {
    echo "<p>フッターの読み込みでエラーが発生しました: " . $e->getMessage() . "</p>";
}
?>

<script>
// 削除機能と一括削除機能
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('select-all-checkbox');
    const scheduleCheckboxes = document.querySelectorAll('.schedule-checkbox');
    const selectAllBtn = document.getElementById('select-all-btn');
    const deselectAllBtn = document.getElementById('deselect-all-btn');
    const bulkDeleteBtn = document.getElementById('bulk-delete-btn');
    const selectedCountSpan = document.getElementById('selected-count');

    // 全選択チェックボックスの処理
    selectAllCheckbox.addEventListener('change', function() {
        const isChecked = this.checked;
        scheduleCheckboxes.forEach(checkbox => {
            checkbox.checked = isChecked;
        });
        updateSelectedCount();
        updateBulkDeleteButton();
    });


    // 個別チェックボックスの処理
    scheduleCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateSelectAllCheckbox();
            updateSelectedCount();
            updateBulkDeleteButton();
        });
    });

    // 全選択ボタン
    selectAllBtn.addEventListener('click', function() {
        selectAllCheckbox.checked = true;
        scheduleCheckboxes.forEach(checkbox => {
            checkbox.checked = true;
        });
        updateSelectedCount();
        updateBulkDeleteButton();
    });

    // 選択解除ボタン
    deselectAllBtn.addEventListener('click', function() {
        selectAllCheckbox.checked = false;
        scheduleCheckboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        updateSelectedCount();
        updateBulkDeleteButton();
    });

    // 一括削除ボタン
    bulkDeleteBtn.addEventListener('click', function() {
        const selectedIds = getSelectedScheduleIds();
        if (selectedIds.length === 0) {
            if (typeof showToastNotification !== 'undefined') {
                showToastNotification('削除するスケジュールが選択されていません。', 'warning');
            } else {
                alert('削除するスケジュールが選択されていません。');
            }
            return;
        }

        const confirmMessage = `選択された${selectedIds.length}件のスケジュールを削除しますか？\n\nこの操作は取り消すことができません。`;
        if (confirm(confirmMessage)) {
            bulkDeleteSchedules(selectedIds);
        }
    });

    // 全選択チェックボックスの状態を更新
    function updateSelectAllCheckbox() {
        const checkedCount = document.querySelectorAll('.schedule-checkbox:checked').length;
        const totalCount = scheduleCheckboxes.length;

        if (checkedCount === 0) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
        } else if (checkedCount === totalCount) {
            selectAllCheckbox.checked = true;
            selectAllCheckbox.indeterminate = false;
        } else {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = true;
        }
    }

    // 選択された件数を更新
    function updateSelectedCount() {
        const selectedCount = document.querySelectorAll('.schedule-checkbox:checked').length;
        selectedCountSpan.textContent = selectedCount;
    }

    // 一括削除ボタンの有効/無効を更新
    function updateBulkDeleteButton() {
        const selectedCount = document.querySelectorAll('.schedule-checkbox:checked').length;
        bulkDeleteBtn.disabled = selectedCount === 0;
    }

    // 選択されたスケジュールIDを取得
    function getSelectedScheduleIds() {
        const selectedCheckboxes = document.querySelectorAll('.schedule-checkbox:checked');
        return Array.from(selectedCheckboxes).map(checkbox => checkbox.value);
    }

    // 初期化
    updateSelectedCount();
    updateBulkDeleteButton();
});

// 個別スケジュール削除
function deleteSchedule(scheduleId, teamName, date) {
    const confirmMessage = `以下のスケジュールを削除しますか？\n\nチーム: ${teamName}\n日付: ${date}\n\n※関連するマッチリクエストも一緒に削除されます。\nこの操作は取り消すことができません。`;

    if (confirm(confirmMessage)) {
        // カスタムエンドポイントを使用してスケジュールと関連データを削除
        fetch('/wp-json/aidunite/v1/delete-schedule-v2', {
            method: 'POST',
            headers: {
                'X-WP-Nonce': '<?= wp_create_nonce('wp_rest') ?>',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                schedule_id: scheduleId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // 削除成功
                if (typeof showToastNotification !== 'undefined') {
                    showToastNotification(`スケジュールと${data.deleted_match_requests}件のマッチリクエストが削除されました。`, 'success');
                } else {
                    alert(`スケジュールと${data.deleted_match_requests}件のマッチリクエストが削除されました。`);
                }
                // 該当行を削除
                const row = document.querySelector(`tr[data-schedule-id="${scheduleId}"]`);
                if (row) {
                    row.remove();
                    // 選択状態を更新
                    updateSelectAllCheckbox();
                    updateSelectedCount();
                    updateBulkDeleteButton();
                }
                // ページを再読み込み（確実性のため）
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                // 削除失敗
                throw new Error(data.message || '削除に失敗しました');
            }
        })
        .catch(error => {
            console.error('削除エラー:', error);
            if (typeof showToastNotification !== 'undefined') {
                showToastNotification('スケジュールの削除に失敗しました。エラー: ' + error.message, 'error');
            } else {
                alert('スケジュールの削除に失敗しました。\nエラー: ' + error.message);
            }
        });
    }
}

// 一括削除
function bulkDeleteSchedules(scheduleIds) {
    const deletePromises = scheduleIds.map(scheduleId => {
        return fetch(`/wp-json/wp/v2/schedule/${scheduleId}`, {
            method: 'DELETE',
            headers: {
                'X-WP-Nonce': '<?= wp_create_nonce('wp_rest') ?>',
                'Content-Type': 'application/json'
            }
        });
    });

    Promise.all(deletePromises)
        .then(responses => {
            const successCount = responses.filter(response => response.ok).length;
            const failCount = scheduleIds.length - successCount;

            if (failCount === 0) {
                if (typeof showToastNotification !== 'undefined') {
                    showToastNotification(`${successCount}件のスケジュールが正常に削除されました。`, 'success');
                } else {
                    alert(`${successCount}件のスケジュールが正常に削除されました。`);
                }
            } else {
                if (typeof showToastNotification !== 'undefined') {
                    showToastNotification(`${successCount}件のスケジュールが削除されました。${failCount}件の削除に失敗しました。`, 'warning');
                } else {
                    alert(`${successCount}件のスケジュールが削除されました。\n${failCount}件の削除に失敗しました。`);
                }
            }

            // ページを再読み込み
            location.reload();
        })
        .catch(error => {
            console.error('一括削除エラー:', error);
            if (typeof showToastNotification !== 'undefined') {
                showToastNotification('一括削除中にエラーが発生しました。エラー: ' + error.message, 'error');
            } else {
                alert('一括削除中にエラーが発生しました。\nエラー: ' + error.message);
            }
        });
}

// 理由設定モーダル
function openReasonModal(scheduleId, teamName, currentReason) {
    document.getElementById('modalScheduleId').value = scheduleId;
    document.getElementById('modalScheduleInfo').textContent = `チーム: ${teamName} (ID: ${scheduleId})`;
    document.getElementById('reasonSelect').value = currentReason || '';
    document.getElementById('reasonModal').style.display = 'block';
}

function closeReasonModal() {
    document.getElementById('reasonModal').style.display = 'none';
}

function saveReason() {
    const scheduleId = document.getElementById('modalScheduleId').value;
    const reason = document.getElementById('reasonSelect').value;
    const note = document.getElementById('reasonNote').value;

    // WordPress REST APIを使用して理由を保存
    const data = {
        'cancellation_reason': reason,
        'cancellation_note': note
    };

    fetch(`/wp-json/wp/v2/schedule/${scheduleId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': '<?= wp_create_nonce('wp_rest') ?>'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.id) {
            if (typeof showToastNotification !== 'undefined') {
                showToastNotification('理由を保存しました。', 'success');
            } else {
                alert('理由を保存しました。');
            }
            closeReasonModal();
            location.reload();
        } else {
            if (typeof showToastNotification !== 'undefined') {
                showToastNotification('理由の保存に失敗しました。', 'error');
            } else {
                alert('理由の保存に失敗しました。');
            }
        }
    })
    .catch(error => {
        console.error('理由保存エラー:', error);
        if (typeof showToastNotification !== 'undefined') {
            showToastNotification('理由の保存中にエラーが発生しました。', 'error');
        } else {
            alert('理由の保存中にエラーが発生しました。');
        }
    });
}

// モーダル外クリックで閉じる
window.onclick = function(event) {
    const modal = document.getElementById('reasonModal');
    if (event.target === modal) {
        closeReasonModal();
    }
}

// マッチリクエスト一覧表示機能
function showMatchRequestsList() {
    const listSection = document.getElementById('match-requests-list');
    if (listSection.style.display === 'none') {
        listSection.style.display = 'block';
        loadMatchRequestsList();
    } else {
        listSection.style.display = 'none';
    }
}

function loadMatchRequestsList() {
    const container = document.getElementById('match-requests-table-container');
    container.innerHTML = '<div class="loading">📋 マッチリクエスト一覧を読み込み中...</div>';

    fetch('/wp-json/aidunite/v1/match-requests', {
        method: 'GET',
        headers: {
            'X-WP-Nonce': '<?= wp_create_nonce('wp_rest') ?>',
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayMatchRequestsTable(data.match_requests);
        } else {
            container.innerHTML = '<div class="error">❌ マッチリクエストの取得に失敗しました: ' + data.message + '</div>';
        }
    })
    .catch(error => {
        console.error('マッチリクエスト取得エラー:', error);
        container.innerHTML = '<div class="error">❌ マッチリクエストの取得中にエラーが発生しました。</div>';
    });
}

function displayMatchRequestsTable(matchRequests) {
    const container = document.getElementById('match-requests-table-container');

    if (matchRequests.length === 0) {
        container.innerHTML = '<div class="no-data">📋 マッチリクエストはありません。</div>';
        return;
    }

    let html = `
        <table class="match-requests-table">
            <thead>
                <tr>
                    <th>申請ID</th>
                    <th>申請チーム</th>
                    <th>対象スケジュール</th>
                    <th>ステータス</th>
                    <th>申請日時</th>
                    <th>選択条件</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
    `;

    matchRequests.forEach(request => {
        const selectedConditions = [];
        if (request.selected_start_time && request.selected_end_time) {
            selectedConditions.push(`時間: ${request.selected_start_time}～${request.selected_end_time}`);
        }
        if (request.selected_place) {
            const placeMap = {'home': 'ホーム', 'away': 'アウェイ', 'either': 'どちらでも'};
            selectedConditions.push(`会場: ${placeMap[request.selected_place] || request.selected_place}`);
        }
        if (request.selected_gender) {
            const genderMap = {'male': '男子', 'female': '女子', 'both': '男子・女子可'};
            selectedConditions.push(`性別: ${genderMap[request.selected_gender] || request.selected_gender}`);
        }

        html += `
            <tr data-request-id="${request.ID}">
                <td>${request.ID}</td>
                <td>${request.from_team_name || '不明'}</td>
                <td>${request.to_schedule_id}</td>
                <td><span class="status-badge status-${request.status}">${request.status}</span></td>
                <td>${request.post_date}</td>
                <td>${selectedConditions.join('<br>') || '-'}</td>
                <td>
                    <button class="btn-delete" onclick="deleteMatchRequest(${request.ID})" title="削除">
                        🗑️
                    </button>
                </td>
            </tr>
        `;
    });

    html += `
            </tbody>
        </table>
    `;

    container.innerHTML = html;
}

function refreshMatchRequestsList() {
    loadMatchRequestsList();
}

function deleteMatchRequest(requestId) {
    const confirmMessage = `申請ID ${requestId} を削除しますか？\n\nこの操作は取り消すことができません。`;

    if (confirm(confirmMessage)) {
        fetch('/wp-json/aidunite/v1/delete-match-request', {
            method: 'POST',
            headers: {
                'X-WP-Nonce': '<?= wp_create_nonce('wp_rest') ?>',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                request_id: requestId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (typeof showToastNotification !== 'undefined') {
                    showToastNotification(`申請ID ${requestId} を削除しました。`, 'success');
                } else {
                    alert(`申請ID ${requestId} を削除しました。`);
                }
                // 該当行を削除
                const row = document.querySelector(`tr[data-request-id="${requestId}"]`);
                if (row) {
                    row.remove();
                }
                // 一覧を更新
                refreshMatchRequestsList();
            } else {
                if (typeof showToastNotification !== 'undefined') {
                    showToastNotification('削除に失敗しました: ' + data.message, 'error');
                } else {
                    alert('削除に失敗しました: ' + data.message);
                }
            }
        })
        .catch(error => {
            console.error('削除エラー:', error);
            if (typeof showToastNotification !== 'undefined') {
                showToastNotification('削除中にエラーが発生しました。', 'error');
            } else {
                alert('削除中にエラーが発生しました。');
            }
        });
    }
}

function deleteAllMatchRequests() {
    const confirmMessage = `全てのマッチリクエストを削除しますか？\n\n※承認済み、申請中、拒否、キャンセルなど、すべての申請が完全に削除されます。\nこの操作は取り消すことができません。`;

    if (confirm(confirmMessage)) {
        fetch('/wp-json/aidunite/v1/delete-all-match-requests', {
            method: 'POST',
            headers: {
                'X-WP-Nonce': '<?= wp_create_nonce('wp_rest') ?>',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (typeof showToastNotification !== 'undefined') {
                    showToastNotification(`${data.deleted_count}件のマッチリクエストを削除しました。`, 'success');
                } else {
                    alert(`${data.deleted_count}件のマッチリクエストを削除しました。`);
                }
                refreshMatchRequestsList();
            } else {
                if (typeof showToastNotification !== 'undefined') {
                    showToastNotification('削除に失敗しました: ' + data.message, 'error');
                } else {
                    alert('削除に失敗しました: ' + data.message);
                }
            }
        })
        .catch(error => {
            console.error('一括削除エラー:', error);
            if (typeof showToastNotification !== 'undefined') {
                showToastNotification('一括削除中にエラーが発生しました。', 'error');
            } else {
                alert('一括削除中にエラーが発生しました。');
            }
        });
    }
}

// 申請ステータス削除機能
function resetAllMatchStatuses() {
    const confirmMessage = `全てのマッチリクエストの申請を削除しますか？\n\n※承認済み、申請中、拒否、キャンセルなど、すべての申請が完全に削除されます。\nこの操作は取り消すことができません。`;

    if (confirm(confirmMessage)) {
        fetch('/wp-json/aidunite/v1/reset-match-statuses', {
            method: 'POST',
            headers: {
                'X-WP-Nonce': '<?= wp_create_nonce('wp_rest') ?>',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                reset_type: 'all'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (typeof showToastNotification !== 'undefined') {
                    showToastNotification(data.message, 'success');
                } else {
                    alert(data.message);
                }
                location.reload();
            } else {
                if (typeof showToastNotification !== 'undefined') {
                    showToastNotification('ステータスのリセットに失敗しました。エラー: ' + (data.message || '不明なエラー'), 'error');
                } else {
                    alert('ステータスのリセットに失敗しました。\nエラー: ' + (data.message || '不明なエラー'));
                }
            }
        })
        .catch(error => {
            console.error('ステータスリセットエラー:', error);
            if (typeof showToastNotification !== 'undefined') {
                showToastNotification('ステータスのリセット中にエラーが発生しました。', 'error');
            } else {
                alert('ステータスのリセット中にエラーが発生しました。');
            }
        });
    }
}

function resetPendingStatuses() {
    const confirmMessage = `申請中ステータスのマッチリクエストのみを削除しますか？\n\n※申請中ステータスのみが完全に削除され、承認済みや拒否済みは残ります。\nこの操作は取り消すことができません。`;

    if (confirm(confirmMessage)) {
        fetch('/wp-json/aidunite/v1/reset-match-statuses', {
            method: 'POST',
            headers: {
                'X-WP-Nonce': '<?= wp_create_nonce('wp_rest') ?>',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                reset_type: 'pending'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (typeof showToastNotification !== 'undefined') {
                    showToastNotification(data.message, 'success');
                } else {
                    alert(data.message);
                }
                location.reload();
            } else {
                if (typeof showToastNotification !== 'undefined') {
                    showToastNotification('ステータスのリセットに失敗しました。エラー: ' + (data.message || '不明なエラー'), 'error');
                } else {
                    alert('ステータスのリセットに失敗しました。\nエラー: ' + (data.message || '不明なエラー'));
                }
            }
        })
        .catch(error => {
            console.error('ステータスリセットエラー:', error);
            if (typeof showToastNotification !== 'undefined') {
                showToastNotification('ステータスのリセット中にエラーが発生しました。', 'error');
            } else {
                alert('ステータスのリセット中にエラーが発生しました。');
            }
        });
    }
}

// スケジュールID直接削除機能
function deleteScheduleById() {
    const scheduleId = document.getElementById('directDeleteId').value;

    if (!scheduleId || scheduleId < 1) {
        if (typeof showToastNotification !== 'undefined') {
            showToastNotification('有効なスケジュールIDを入力してください。', 'warning');
        } else {
            alert('有効なスケジュールIDを入力してください。');
        }
        return;
    }

    const confirmMessage = `スケジュールID: ${scheduleId} を削除しますか？\n\n※関連するマッチリクエストも一緒に削除されます。\nこの操作は取り消すことができません。`;

    if (confirm(confirmMessage)) {
        fetch('/wp-json/aidunite/v1/delete-schedule-v2', {
            method: 'POST',
            headers: {
                'X-WP-Nonce': '<?= wp_create_nonce('wp_rest') ?>',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                schedule_id: parseInt(scheduleId)
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (typeof showToastNotification !== 'undefined') {
                    showToastNotification(`スケジュールID: ${scheduleId} と${data.deleted_match_requests}件のマッチリクエストが削除されました。`, 'success');
                } else {
                    alert(`スケジュールID: ${scheduleId} と${data.deleted_match_requests}件のマッチリクエストが削除されました。`);
                }
                document.getElementById('directDeleteId').value = '';
                // テーブルから該当行を削除（存在する場合）
                const row = document.querySelector(`tr[data-schedule-id="${scheduleId}"]`);
                if (row) {
                    row.remove();
                    updateSelectAllCheckbox();
                    updateSelectedCount();
                    updateBulkDeleteButton();
                }
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                if (typeof showToastNotification !== 'undefined') {
                    showToastNotification('削除に失敗しました。エラー: ' + (data.message || '不明なエラー'), 'error');
                } else {
                    alert('削除に失敗しました。\nエラー: ' + (data.message || '不明なエラー'));
                }
            }
        })
        .catch(error => {
            console.error('削除エラー:', error);
            if (typeof showToastNotification !== 'undefined') {
                showToastNotification('スケジュールの削除に失敗しました。エラー: ' + error.message, 'error');
            } else {
                alert('スケジュールの削除に失敗しました。\nエラー: ' + error.message);
            }
        });
    }
}
</script>

<!-- 理由設定モーダル -->
<div id="reasonModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>理由設定</h3>
            <span class="close" onclick="closeReasonModal()">&times;</span>
        </div>
        <div class="modal-body">
            <p id="modalScheduleInfo"></p>
            <form id="reasonForm">
                <input type="hidden" id="modalScheduleId" value="">
                <div class="form-group">
                    <label for="reasonSelect">理由を選択してください:</label>
                    <select id="reasonSelect" name="reason">
                        <option value="">理由なし</option>
                        <option value="cancelled">キャンセル</option>
                        <option value="no_show">無断</option>
                        <option value="expired">期限過ぎた</option>
                        <option value="insufficient_teams">チーム数不足</option>
                        <option value="venue_issue">会場問題</option>
                        <option value="weather">天候不良</option>
                        <option value="other">その他</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="reasonNote">詳細メモ（任意）:</label>
                    <textarea id="reasonNote" name="note" rows="3" placeholder="詳細な理由や状況を記入してください"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeReasonModal()">キャンセル</button>
            <button type="button" class="btn btn-primary" onclick="saveReason()">保存</button>
        </div>
    </div>
</div>

<script>
// スケジュール表示の切り替え
function toggleScheduleDisplay(view) {
    const cardsView = document.getElementById('schedule-cards-view');
    const tableView = document.getElementById('schedule-table-view');
    const buttons = document.querySelectorAll('.view-toggle button');

    // ボタンのアクティブ状態を更新
    buttons.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');

    // ビューを切り替え
    if (view === 'cards') {
        cardsView.classList.add('active');
        tableView.classList.remove('active');
    } else {
        tableView.classList.add('active');
        cardsView.classList.remove('active');
    }
}

// 出欠機能（準備中）
function markAttendance(scheduleId, status) {
    console.log('出欠登録:', { scheduleId, status });

    // 確認ダイアログ
    const statusText = {
        'attending': '出席',
        'not_attending': '欠席',
        'pending': '未定'
    }[status] || status;

    if (!confirm(`このスケジュールを「${statusText}」として登録しますか？`)) {
        return;
    }

    // ここで実際の出欠登録処理を行う
    // 現在は準備中のため、アラートで終了
    if (typeof showToastNotification !== 'undefined') {
        showToastNotification(`出欠機能は準備中です。スケジュールID: ${scheduleId} ステータス: ${statusText}`, 'info');
    } else {
        alert(`出欠機能は準備中です。\nスケジュールID: ${scheduleId}\nステータス: ${statusText}`);
    }

    // 将来的には以下のような処理を実装
    /*
    fetch('/wp-json/aidunite/v1/mark-attendance', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': wpApiSettings.nonce
        },
        body: JSON.stringify({
            schedule_id: scheduleId,
            status: status
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (typeof showToastNotification !== 'undefined') {
                showToastNotification('出欠を登録しました', 'success');
            } else {
                alert('出欠を登録しました');
            }
            // ページを再読み込みまたは表示を更新
            location.reload();
        } else {
            if (typeof showToastNotification !== 'undefined') {
                showToastNotification('出欠の登録に失敗しました: ' + (data.message || '不明なエラー'), 'error');
            } else {
                alert('出欠の登録に失敗しました: ' + (data.message || '不明なエラー'));
            }
        }
    })
    .catch(error => {
        console.error('出欠登録エラー:', error);
        if (typeof showToastNotification !== 'undefined') {
            showToastNotification('出欠の登録に失敗しました: ' + error.message, 'error');
        } else {
            alert('出欠の登録に失敗しました: ' + error.message);
        }
    });
    */
}
</script>

<style>
/* スケジュール表示切り替え用スタイル */
.schedule-display-container {
    margin-top: 2rem;
}

.display-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.display-controls {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.view-toggle {
    display: flex;
    gap: 0.5rem;
}

.view-toggle button {
    padding: 0.5rem 1rem;
    border: 1px solid var(--border-color);
    background: white;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.view-toggle button.active {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

.schedule-view {
    display: none;
}

.schedule-view.active {
    display: block;
}

/* カード表示用スタイル */
.schedule-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
    margin-top: 1rem;
}

.schedule-card {
    background: white;
    border: 1px solid var(--border-light);
    border-radius: 12px;
    box-shadow: var(--shadow-md);
    transition: all 0.3s ease;
    overflow: hidden;
}

.schedule-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary-color);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--border-light) 100%);
    border-bottom: 1px solid var(--border-color);
}

.card-date {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 1.1rem;
}

.card-actions {
    display: flex;
    gap: 0.5rem;
}

.card-body {
    padding: 1.5rem;
}

.card-title {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 1rem;
}

.card-details {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.detail-label {
    font-weight: 500;
    color: var(--text-secondary);
    min-width: 80px;
    font-size: 0.9rem;
}

.detail-value {
    color: var(--text-primary);
    font-size: 0.9rem;
}

.card-note {
    padding: 0.75rem;
    background: var(--bg-secondary);
    border-radius: 6px;
    border-left: 3px solid var(--primary-color);
}

.note-label {
    font-weight: 500;
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.note-text {
    color: var(--text-primary);
    font-size: 0.9rem;
    line-height: 1.4;
}

.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--text-secondary);
}

.empty-state p {
    font-size: 1.1rem;
    margin: 0;
}

/* レスポンシブ対応 */
@media (max-width: 768px) {
    .display-header {
        flex-direction: column;
        align-items: stretch;
    }

    .display-controls {
        justify-content: center;
    }

    .schedule-cards-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }

    .card-header {
        padding: 1rem;
    }

    .card-body {
        padding: 1rem;
    }

    .card-details {
        gap: 0.5rem;
    }

    .detail-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }

    .detail-label {
        min-width: auto;
    }
}

/* 出欠機能用スタイル */
.attendance-actions {
    display: flex;
    gap: 0.25rem;
    flex-wrap: wrap;
}

.attendance-actions .btn {
    font-size: 0.8rem;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    border: 1px solid transparent;
    cursor: pointer;
    transition: all 0.2s ease;
}

.attendance-actions .btn:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow-sm);
}

.attendance-actions .btn-success {
    background: rgba(40, 167, 69, 0.1);
    color: var(--success-color);
    border-color: var(--success-color);
}

.attendance-actions .btn-success:hover {
    background: rgba(40, 167, 69, 0.15);
}

.attendance-actions .btn-warning {
    background: rgba(255, 193, 7, 0.1);
    color: var(--warning-color);
    border-color: var(--warning-color);
}

.attendance-actions .btn-warning:hover {
    background: rgba(255, 193, 7, 0.2);
}

.attendance-actions .btn-info {
    background: rgba(23, 162, 184, 0.1);
    color: var(--info-color);
    border-color: var(--info-color);
}

.attendance-actions .btn-info:hover {
    background: rgba(23, 162, 184, 0.2);
}

/* 出欠機能のレスポンシブ対応 */
@media (max-width: 768px) {
    .attendance-actions {
        flex-direction: column;
        gap: 0.25rem;
    }

    .attendance-actions .btn {
        font-size: 0.75rem;
        padding: 0.2rem 0.4rem;
    }
}
</style>
