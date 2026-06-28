<?php
/**
 * Template Name: マッチ統計
 * マッチ成立率の分析ページ
 */

// ページ設定変数（ガイドに沿って定義）
$page_title = 'あなたのマッチ統計';
$page_description = 'マッチ成立率の分析と統計データを確認できます';
$page_icon = 'bar_chart_4_bars';

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
        'page_class' => 'page-match-analytics',
        'title' => $page_title,
        'subtitle' => $page_description,
    ]);
} else {
    echo '<div class="team-dashboard-container page-match-analytics"><div class="dashboard-header"><h1>' . esc_html($page_title) . '</h1><p>' . esc_html($page_description) . '</p></div>';
}
?>

    <!-- マッチ統計 -->
        <!-- 基本統計 -->
        <div class="analytics-section">
            <h2>基本統計</h2>
            <div id="basic-stats" class="stats-grid">
                <div class="loading">読み込み中...</div>
            </div>
        </div>

        <!-- マッチ度別の成立率 -->
        <div class="analytics-section">
            <h2>マッチ度別の成立率</h2>
            <div id="match-score-stats" class="stats-container">
                <div class="loading">読み込み中...</div>
            </div>
        </div>

        <!-- 時間帯別の成立率 -->
        <div class="analytics-section">
            <h2>時間帯別の成立率</h2>
            <div id="time-slot-stats" class="stats-container">
                <div class="loading">読み込み中...</div>
            </div>
        </div>

        <!-- 曜日別の成立率 -->
        <div class="analytics-section">
            <h2>曜日別の成立率</h2>
            <div id="day-of-week-stats" class="stats-container">
                <div class="loading">読み込み中...</div>
            </div>
        </div>

        <!-- 月別のトレンド -->
        <div class="analytics-section">
            <h2>月別のトレンド</h2>
            <div id="monthly-trend" class="trend-container">
                <div class="loading">読み込み中...</div>
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
$match_analytics_board_tier_order = ['best', 'green', 'yellow', 'no_preference'];
$match_analytics_board_tier_labels = [];
$tier_label_defs = function_exists('aidunite_match_board_tier_labels')
    ? aidunite_match_board_tier_labels()
    : [];
foreach ($match_analytics_board_tier_order as $tier_key) {
    if (isset($tier_label_defs[$tier_key]['label']) && $tier_label_defs[$tier_key]['label'] !== '') {
        $match_analytics_board_tier_labels[$tier_key] = (string) $tier_label_defs[$tier_key]['label'];
        continue;
    }
    $match_analytics_board_tier_labels[$tier_key] = [
        'best' => 'ベストマッチ',
        'green' => '成立可能',
        'yellow' => '条件調整',
        'no_preference' => '希望未登録',
    ][$tier_key] ?? $tier_key;
}
?>

<?php
aidunite_page_asset_localize('match-analytics', [
    'analyticsUrl' => esc_url(rest_url('aidunite/v1/match-analytics')),
    'restNonce' => wp_create_nonce('wp_rest'),
    'boardTierOrder' => $match_analytics_board_tier_order,
    'boardTierLabels' => $match_analytics_board_tier_labels,
]);
get_footer();
?>
