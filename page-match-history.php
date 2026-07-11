<?php
/**
 * Template Name: 申請履歴
 * マッチ申請履歴の管理ページ（フィルタリング、検索、ページネーション機能付き）
 */

// ページ設定変数（ガイドに沿って定義）
$page_title = '申請履歴';
$page_description = 'マッチ申請履歴を確認し、フィルタリングや検索ができます';
$page_icon = 'list_alt_add';

get_header();

if (!is_user_logged_in()) {
    echo '<div class="container">';
    echo '<p>このページを表示するにはログインが必要です。</p>';
    echo '<p><a href="' . esc_url(home_url('/login')) . '" class="button">ログインページへ</a></p>';
    echo '</div>';
    get_footer();
    exit;
}

$current_user_id = get_current_user_id();
$current_user_team_id = aidunite_user_read_primary_team_id((int) $current_user_id);

if (!$current_user_team_id) {
    echo '<div class="container">';
    echo '<p>チームIDが設定されていません。チーム登録を完了してください。</p>';
    echo '<p><a href="' . esc_url(home_url('/mypage')) . '" class="button">マイページへ</a></p>';
    echo '</div>';
    get_footer();
    exit;
}
?>

<?php
if (function_exists('aidunite_web_app_page_shell_open')) {
    aidunite_web_app_page_shell_open([
        'page_class' => 'page-match-history',
        'title' => $page_title,
        'subtitle' => $page_description,
    ]);
} else {
    echo '<div class="team-dashboard-container page-match-history"><div class="dashboard-header"><h1>' . esc_html($page_title) . '</h1><p>' . esc_html($page_description) . '</p></div>';
}
?>

    <!-- 申請履歴 -->
        <!-- フィルターセクション -->
        <div class="filter-section">
            <h3>フィルター</h3>
            <div class="filter-controls">
                <div class="filter-group">
                    <label>ステータス:</label>
                    <select id="status-filter" class="filter-select">
                        <option value="all">すべて</option>
                        <option value="申請中">申請中</option>
                        <option value="承認済み">承認済み</option>
                        <option value="拒否済み">拒否済み</option>
                        <option value="キャンセル済み">キャンセル済み</option>
                        <option value="試合確定">試合確定</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>日付範囲:</label>
                    <input type="date" id="date-from" class="filter-input" value="<?php echo date('Y-m-d', strtotime('-3 months')); ?>">
                    <span>～</span>
                    <input type="date" id="date-to" class="filter-input" value="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="filter-group">
                    <label>相手チーム:</label>
                    <input type="text" id="team-name-filter" class="filter-input" placeholder="チーム名で検索">
                </div>

                <div class="filter-group">
                    <button id="apply-filter" class="btn btn-primary">フィルター適用</button>
                    <button id="reset-filter" class="btn btn-secondary">リセット</button>
                </div>
            </div>
        </div>

        <!-- 検索セクション -->
        <div class="search-section">
            <h3>検索</h3>
            <div class="search-controls">
                <input type="text" id="search-input" class="search-input" placeholder="キーワード検索（チーム名、試合日、会場、メッセージ）">
                <button id="search-btn" class="btn btn-primary">検索</button>
            </div>
        </div>

        <!-- 表示件数とページネーション -->
        <div class="pagination-controls">
            <div class="per-page-control">
                <label>表示件数:</label>
                <select id="per-page-select" class="per-page-select">
                    <option value="10">10件</option>
                    <option value="20" selected>20件</option>
                    <option value="50">50件</option>
                    <option value="100">100件</option>
                </select>
            </div>
            <div id="pagination-info" class="pagination-info"></div>
        </div>

        <!-- 申請履歴一覧 -->
        <div id="match-history-list" class="match-history-list">
            <div class="loading">読み込み中...</div>
        </div>

        <!-- ページネーション -->
        <div id="pagination" class="pagination"></div>

<?php
if (function_exists('aidunite_web_app_page_shell_close')) {
    aidunite_web_app_page_shell_close();
} else {
    echo '</div>';
}
?>



<?php
wp_localize_script('aidunite-match-history', 'aiduniteMatchHistoryPage', [
    'defaultDateFrom' => date('Y-m-d', strtotime('-3 months')),
    'defaultDateTo' => date('Y-m-d'),
    'historyUrl' => rest_url('aidunite/v1/match-history'),
    'restNonce' => wp_create_nonce('wp_rest'),
]);
get_footer();
?>
