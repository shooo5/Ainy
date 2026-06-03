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
$current_user_team_id = get_user_meta($current_user_id, 'team_id', true);

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

    <!-- マッチ統計セクション -->
    <section class="dashboard-section">
        <div class="main-content-area">
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
        </div>
    </section>

<?php
if (function_exists('aidunite_web_app_page_shell_close')) {
    aidunite_web_app_page_shell_close();
} else {
    echo '</div>';
}
?>

<style>

.page-match-analytics .analytics-section {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 2rem;
    margin-bottom: 2rem;
}

.page-match-analytics .analytics-section h2 {
    margin-top: 0;
    margin-bottom: 1.5rem;
    font-size: 1.5rem;
    color: var(--text-primary);
}

.page-match-analytics .stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
}

.page-match-analytics .stat-card {
    background: var(--bg-secondary);
    border-radius: 8px;
    padding: 1.5rem;
    text-align: center;
}

.page-match-analytics .stat-card .stat-label {
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin-bottom: 0.5rem;
}

.page-match-analytics .stat-card .stat-value {
    font-size: 2rem;
    font-weight: bold;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}

.page-match-analytics .stat-card .stat-subvalue {
    font-size: 0.85rem;
    color: var(--text-muted);
}

.page-match-analytics .stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.page-match-analytics .rate-item {
    background: var(--bg-secondary);
    border-radius: 8px;
    padding: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.page-match-analytics .rate-item .rate-label {
    font-weight: 600;
    color: var(--text-primary);
}

.page-match-analytics .rate-item .rate-value {
    font-size: 1.2rem;
    font-weight: bold;
    color: var(--primary-color);
}

.page-match-analytics .trend-container {
    min-height: 300px;
}

.page-match-analytics .loading {
    text-align: center;
    padding: 2rem;
    color: var(--text-secondary);
}

@media (max-width: 768px) {
    .page-match-analytics {
        padding: 1rem;
    }

    .page-match-analytics .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

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

<script>
jQuery(document).ready(function($) {
    const boardTierOrder = <?php echo wp_json_encode($match_analytics_board_tier_order); ?>;
    const boardTierLabels = <?php echo wp_json_encode($match_analytics_board_tier_labels); ?>;

    function loadMatchAnalytics() {
        $.ajax({
            url: '<?php echo esc_url(rest_url('aidunite/v1/match-analytics')); ?>',
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce("wp_rest"); ?>');
            },
            success: function(response) {
                console.log('[MATCH_ANALYTICS] Success:', response);
                if (response.success && response.data) {
                    displayBasicStats(response.data.basic_stats);
                    displayBoardTierStats(response.data.match_score_rates);
                    displayTimeSlotStats(response.data.time_slot_rates);
                    displayDayOfWeekStats(response.data.day_of_week_rates);
                    displayMonthlyTrend(response.data.monthly_data);
                } else {
                    $('.loading').text('データの取得に失敗しました。');
                }
            },
            error: function(xhr) {
                console.error('[MATCH_ANALYTICS] Error:', xhr);
                $('.loading').text('エラーが発生しました。ページを再読み込みしてください。');
            }
        });
    }

    function displayBasicStats(stats) {
        let html = '';
        html += '<div class="stat-card">';
        html += '<div class="stat-label">申請数</div>';
        html += '<div class="stat-value">' + stats.total_requests + '</div>';
        html += '<div class="stat-subvalue">件</div>';
        html += '</div>';

        html += '<div class="stat-card">';
        html += '<div class="stat-label">成立数</div>';
        html += '<div class="stat-value">' + stats.established_count + '</div>';
        html += '<div class="stat-subvalue">件</div>';
        html += '</div>';

        html += '<div class="stat-card">';
        html += '<div class="stat-label">成立率</div>';
        html += '<div class="stat-value">' + stats.establishment_rate + '%</div>';
        html += '<div class="stat-subvalue">' + stats.established_count + '/' + stats.total_requests + '</div>';
        html += '</div>';

        html += '<div class="stat-card">';
        html += '<div class="stat-label">平均成立までの日数</div>';
        html += '<div class="stat-value">' + stats.average_days_to_establish + '</div>';
        html += '<div class="stat-subvalue">日</div>';
        html += '</div>';

        $('#basic-stats').html(html);
    }

    function displayBoardTierStats(stats) {
        let html = '';
        boardTierOrder.forEach(function(key) {
            if (stats[key] && stats[key].total > 0) {
                html += '<div class="rate-item">';
                var tierLabel = boardTierLabels[key] || key;
                if (key === 'best' && typeof AidUniteThemeIcons !== 'undefined') {
                    tierLabel = AidUniteThemeIcons.html('star', 16) + ' ' + tierLabel;
                }
                html += '<div class="rate-label">' + tierLabel + '</div>';
                html += '<div class="rate-value">' + stats[key].rate + '%</div>';
                html += '<div style="font-size: 0.85rem; color: var(--text-secondary); margin-left: 10px;">(' + stats[key].established + '/' + stats[key].total + ')</div>';
                html += '</div>';
            }
        });

        if (html === '') {
            html = '<p style="color: var(--text-secondary);">データがありません。</p>';
        }

        $('#match-score-stats').html(html);
    }

    function displayTimeSlotStats(stats) {
        let html = '';
        const slots = ['16:00-18:00', '18:00-20:00', '20:00-22:00'];

        slots.forEach(function(slot) {
            if (stats[slot] && stats[slot].total > 0) {
                html += '<div class="rate-item">';
                html += '<div class="rate-label">' + slot + '</div>';
                html += '<div class="rate-value">' + stats[slot].rate + '%</div>';
                html += '<div style="font-size: 0.85rem; color: var(--text-secondary); margin-left: 10px;">(' + stats[slot].established + '/' + stats[slot].total + ')</div>';
                html += '</div>';
            }
        });

        if (html === '') {
            html = '<p style="color: var(--text-secondary);">データがありません。</p>';
        }

        $('#time-slot-stats').html(html);
    }

    function displayDayOfWeekStats(stats) {
        let html = '';
        const days = ['日', '月', '火', '水', '木', '金', '土'];

        days.forEach(function(day) {
            if (stats[day] && stats[day].total > 0) {
                html += '<div class="rate-item">';
                html += '<div class="rate-label">' + day + '曜日</div>';
                html += '<div class="rate-value">' + stats[day].rate + '%</div>';
                html += '<div style="font-size: 0.85rem; color: var(--text-secondary); margin-left: 10px;">(' + stats[day].established + '/' + stats[day].total + ')</div>';
                html += '</div>';
            }
        });

        if (html === '') {
            html = '<p style="color: var(--text-secondary);">データがありません。</p>';
        }

        $('#day-of-week-stats').html(html);
    }

    function displayMonthlyTrend(data) {
        let html = '<div style="overflow-x: auto;"><table style="width: 100%; border-collapse: collapse;">';
        html += '<thead><tr><th style="padding: 0.75rem; text-align: left; border-bottom: 2px solid var(--border-color);">月</th><th style="padding: 0.75rem; text-align: right; border-bottom: 2px solid var(--border-color);">申請数</th><th style="padding: 0.75rem; text-align: right; border-bottom: 2px solid var(--border-color);">成立数</th><th style="padding: 0.75rem; text-align: right; border-bottom: 2px solid var(--border-color);">成立率</th></tr></thead>';
        html += '<tbody>';

        if (Object.keys(data).length === 0) {
            html += '<tr><td colspan="4" style="padding: 1rem; text-align: center; color: var(--text-secondary);">データがありません。</td></tr>';
        } else {
            Object.keys(data).forEach(function(month) {
                const rate = data[month].total > 0 ? ((data[month].established / data[month].total) * 100).toFixed(1) : 0;
                html += '<tr>';
                html += '<td style="padding: 0.75rem; border-bottom: 1px solid var(--border-light);">' + month + '</td>';
                html += '<td style="padding: 0.75rem; text-align: right; border-bottom: 1px solid var(--border-light);">' + data[month].total + '</td>';
                html += '<td style="padding: 0.75rem; text-align: right; border-bottom: 1px solid var(--border-light);">' + data[month].established + '</td>';
                html += '<td style="padding: 0.75rem; text-align: right; border-bottom: 1px solid var(--border-light);">' + rate + '%</td>';
                html += '</tr>';
            });
        }

        html += '</tbody></table></div>';
        $('#monthly-trend').html(html);
    }

    // 初期読み込み
    loadMatchAnalytics();
});
</script>

<?php get_footer(); ?>
