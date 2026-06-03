/**
 * Ainyメッセージ機能 管理画面用JavaScript
 * Phase 1-3: 全機能実装
 */

class AidUniteMessagingAdmin {
    constructor() {
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadStats();
    }

    bindEvents() {
        // データベーステーブル作成
        $(document).on('click', '.create-tables-btn', (e) => {
            e.preventDefault();
            this.createTables();
        });

        // SSE接続テスト
        $(document).on('click', '.test-sse-btn', (e) => {
            e.preventDefault();
            this.testSSEConnection();
        });

        // 統計更新
        $(document).on('click', '.refresh-stats-btn', (e) => {
            e.preventDefault();
            this.loadStats();
        });

        // ゲーミフィケーション統計更新
        $(document).on('click', '.refresh-gamification-btn', (e) => {
            e.preventDefault();
            this.loadGamificationStats();
        });

        // 分析レポート生成
        $(document).on('click', '.generate-report-btn', (e) => {
            e.preventDefault();
            this.generateReport();
        });

        // マッチング最適化実行
        $(document).on('click', '.optimize-matching-btn', (e) => {
            e.preventDefault();
            this.optimizeMatching();
        });
    }

    // データベーステーブル作成
    createTables() {
        const button = $('.create-tables-btn');
        const originalText = button.text();

        button.prop('disabled', true).text('作成中...');

        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'aidunite_create_messaging_tables',
                nonce: $('#_wpnonce').val()
            },
            success: (response) => {
                if (response.success) {
                    this.showMessage('✅ データベーステーブルが正常に作成されました！', 'success');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    this.showMessage('❌ テーブル作成に失敗しました: ' + response.data, 'error');
                }
            },
            error: (xhr) => {
                this.showMessage('❌ エラーが発生しました: ' + xhr.responseText, 'error');
            },
            complete: () => {
                button.prop('disabled', false).text(originalText);
            }
        });
    }

    // SSE接続テスト
    testSSEConnection() {
        const resultDiv = $('#sse-test-result');
        resultDiv.html('接続中...');

        const eventSource = new EventSource('/wp-json/aidunite/v1/chat/1/stream');

        eventSource.onopen = () => {
            resultDiv.html('✅ SSE接続成功！');
            eventSource.close();
        };

        eventSource.onerror = () => {
            resultDiv.html('❌ SSE接続失敗');
            eventSource.close();
        };

        setTimeout(() => {
            eventSource.close();
            if (resultDiv.html() === '接続中...') {
                resultDiv.html('⏰ 接続タイムアウト');
            }
        }, 5000);
    }

    // 統計読み込み
    loadStats() {
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'aidunite_get_messaging_stats',
                nonce: $('#_wpnonce').val()
            },
            success: (response) => {
                if (response.success) {
                    this.updateStatsDisplay(response.data);
                }
            },
            error: (xhr) => {
                console.error('統計の読み込みに失敗しました:', xhr);
            }
        });
    }

    // 統計表示更新
    updateStatsDisplay(stats) {
        $('.total-chats').text(stats.total_chats || 0);
        $('.total-messages').text(stats.total_messages || 0);
        $('.active-connections').text(stats.active_connections || 0);
        $('.total-ratings').text(stats.total_ratings || 0);
        $('.rematch-suggestions').text(stats.rematch_suggestions || 0);
    }

    // ゲーミフィケーション統計読み込み
    loadGamificationStats() {
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'aidunite_get_gamification_stats',
                nonce: $('#_wpnonce').val()
            },
            success: (response) => {
                if (response.success) {
                    this.updateGamificationDisplay(response.data);
                }
            },
            error: (xhr) => {
                console.error('ゲーミフィケーション統計の読み込みに失敗しました:', xhr);
            }
        });
    }

    // ゲーミフィケーション表示更新
    updateGamificationDisplay(stats) {
        $('.total-points').text(stats.total_points || 0);
        $('.total-badges').text(stats.total_badges || 0);
        $('.active-teams').text(stats.active_teams || 0);
        $('.leaderboard').html(this.generateLeaderboardHTML(stats.leaderboard || []));
    }

    // リーダーボードHTML生成
    generateLeaderboardHTML(leaderboard) {
        let html = '<table class="wp-list-table widefat fixed striped"><thead><tr><th>順位</th><th>チーム名</th><th>ポイント</th><th>バッジ</th></tr></thead><tbody>';

        leaderboard.forEach((team, index) => {
            const badges = team.badges ? Object.values(team.badges).map(b => b.icon).join(' ') : '';
            html += `
                <tr>
                    <td>${index + 1}</td>
                    <td>${team.team_name}</td>
                    <td>${team.points.toLocaleString()}</td>
                    <td>${badges}</td>
                </tr>
            `;
        });

        html += '</tbody></table>';
        return html;
    }

    // 分析レポート生成
    generateReport() {
        const teamId = $('#team-select').val();
        const period = $('#period-select').val();

        if (!teamId) {
            this.showMessage('チームを選択してください', 'error');
            return;
        }

        const button = $('.generate-report-btn');
        const originalText = button.text();

        button.prop('disabled', true).text('生成中...');

        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'aidunite_generate_analytics_report',
                team_id: teamId,
                period: period,
                nonce: $('#_wpnonce').val()
            },
            success: (response) => {
                if (response.success) {
                    this.showMessage('✅ 分析レポートが生成されました！', 'success');
                    this.displayReport(response.data);
                } else {
                    this.showMessage('❌ レポート生成に失敗しました: ' + response.data, 'error');
                }
            },
            error: (xhr) => {
                this.showMessage('❌ エラーが発生しました: ' + xhr.responseText, 'error');
            },
            complete: () => {
                button.prop('disabled', false).text(originalText);
            }
        });
    }

    // レポート表示
    displayReport(report) {
        const reportDiv = $('#analytics-report');
        reportDiv.html(`
            <div class="analytics-report">
                <h3>📊 ${report.team_name} - ${report.period}レポート</h3>
                <div class="report-overview">
                    <div class="stat-card">
                        <div class="stat-value">${report.overview.total_matches}</div>
                        <div class="stat-label">試合数</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">${report.overview.total_messages}</div>
                        <div class="stat-label">メッセージ数</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">${report.overview.average_rating}</div>
                        <div class="stat-label">平均評価</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">${report.overview.rematch_rate}%</div>
                        <div class="stat-label">再マッチ率</div>
                    </div>
                </div>
                <div class="report-recommendations">
                    <h4>推奨事項</h4>
                    <ul>
                        ${report.recommendations.map(rec => `<li class="recommendation ${rec.priority}">${rec.title}: ${rec.description}</li>`).join('')}
                    </ul>
                </div>
            </div>
        `);
    }

    // マッチング最適化実行
    optimizeMatching() {
        const teamId = $('#matching-team-select').val();

        if (!teamId) {
            this.showMessage('チームを選択してください', 'error');
            return;
        }

        const button = $('.optimize-matching-btn');
        const originalText = button.text();

        button.prop('disabled', true).text('最適化中...');

        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'aidunite_optimize_matching',
                team_id: teamId,
                nonce: $('#_wpnonce').val()
            },
            success: (response) => {
                if (response.success) {
                    this.showMessage('✅ マッチング最適化が完了しました！', 'success');
                    this.displayMatchingResults(response.data);
                } else {
                    this.showMessage('❌ 最適化に失敗しました: ' + response.data, 'error');
                }
            },
            error: (xhr) => {
                this.showMessage('❌ エラーが発生しました: ' + xhr.responseText, 'error');
            },
            complete: () => {
                button.prop('disabled', false).text(originalText);
            }
        });
    }

    // マッチング結果表示
    displayMatchingResults(results) {
        const resultsDiv = $('#matching-results');
        resultsDiv.html(`
            <div class="matching-results">
                <h3>🎯 最適なマッチング候補</h3>
                <div class="matching-suggestions">
                    ${results.map(match => `
                        <div class="suggestion-item">
                            <div class="suggestion-header">
                                <h4>${match.team_name}</h4>
                                <div class="score-badge" style="background-color: ${match.recommendation.color}">
                                    ${match.percentage}%
                                </div>
                            </div>
                            <div class="suggestion-reasons">
                                <ul>
                                    ${match.reasons.map(reason => `<li>${reason}</li>`).join('')}
                                </ul>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `);
    }

    // メッセージ表示
    showMessage(message, type = 'info') {
        const messageDiv = $(`
            <div class="notice notice-${type} is-dismissible">
                <p>${message}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">この通知を非表示にする</span>
                </button>
            </div>
        `);

        $('.wrap h1').after(messageDiv);

        // 自動非表示
        setTimeout(() => {
            messageDiv.fadeOut();
        }, 5000);
    }
}

// 初期化
$(document).ready(function() {
    new AidUniteMessagingAdmin();
});

// AJAXアクション
$(document).on('click', '.notice-dismiss', function() {
    $(this).closest('.notice').fadeOut();
});
