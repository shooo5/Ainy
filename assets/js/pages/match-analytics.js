/**
 * page-match-analytics.php
 */
(function ($) {
  'use strict';
  var cfg = typeof aidunitePage_match_analytics !== 'undefined' ? aidunitePage_match_analytics : {};

$(function () {
    const boardTierOrder = cfg.boardTierOrder || [];
    const boardTierLabels = cfg.boardTierLabels || {};

    function loadMatchAnalytics() {
        $.ajax({
            url: (cfg.analyticsUrl || ''),
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', cfg.restNonce || '');
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
})(jQuery);
