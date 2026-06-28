/**
 * 申請履歴（page-match-history.php）
 */
(function ($) {
  'use strict';
  var cfg = typeof aiduniteMatchHistoryPage !== 'undefined' ? aiduniteMatchHistoryPage : {};

  $(function () {
    let currentPage = 1;
    let currentFilters = {
        status: 'all',
        date_from: cfg.defaultDateFrom || '',
        date_to: cfg.defaultDateTo || '',
        team_name: '',
        search: '',
        per_page: 20,
        type: 'all'
    };

    // localStorageから設定を復元
    const savedFilters = localStorage.getItem('match_history_filters');
    if (savedFilters) {
        try {
            const parsed = JSON.parse(savedFilters);
            currentFilters = { ...currentFilters, ...parsed };

            // UIに反映
            $('#status-filter').val(currentFilters.status);
            $('#date-from').val(currentFilters.date_from);
            $('#date-to').val(currentFilters.date_to);
            $('#team-name-filter').val(currentFilters.team_name);
            $('#per-page-select').val(currentFilters.per_page);
        } catch (e) {
            console.error('Failed to parse saved filters:', e);
        }
    }

    // 申請履歴を読み込む
    function loadMatchHistory(page = 1) {
        currentPage = page;
        const params = {
            ...currentFilters,
            page: page
        };

        $('#match-history-list').html('<div class="loading">読み込み中...</div>');

        $.ajax({
            url: cfg.historyUrl || '',
            method: 'GET',
            data: params,
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', cfg.restNonce || '');
            },
            success: function(response) {
                if (response.success && response.data) {
                    displayMatchHistory(response.data.items);
                    displayPagination(response.data.pagination);
                } else {
                    $('#match-history-list').html('<div class="empty-state">データの取得に失敗しました。</div>');
                }
            },
            error: function(xhr) {
                console.error('Error loading match history:', xhr);
                $('#match-history-list').html('<div class="empty-state">エラーが発生しました。ページを再読み込みしてください。</div>');
            }
        });
    }

    // 申請履歴を表示
    function displayMatchHistory(items) {
        if (items.length === 0) {
            $('#match-history-list').html('<div class="empty-state">該当する申請履歴がありません。</div>');
            return;
        }

        let html = '';
        items.forEach(function(item) {
            const sentIcon = (typeof AidUniteThemeIcons !== 'undefined') ? AidUniteThemeIcons.html('send', 16) : '';
            const receivedIcon = (typeof AidUniteThemeIcons !== 'undefined') ? AidUniteThemeIcons.html('forward_to_inbox', 16) : '';
            const typeLabel = item.type === 'sent' ? sentIcon + ' 申請した案件' : receivedIcon + ' 受信した申請';
            const typeClass = item.type === 'sent' ? 'sent' : 'received';
            const statusClass = 'status-' + item.status.toLowerCase().replace('済み', '').replace('中', 'pending');

            html += '<div class="history-item">';
            html += '<div class="history-item-header">';
            html += '<span class="history-item-type ' + typeClass + '">' + typeLabel + '</span>';
            html += '<span class="status-badge ' + statusClass + '">' + item.status + '</span>';
            html += '</div>';
            html += '<div class="history-item-details">';
            html += '<div class="history-item-detail"><label>相手チーム</label><span>' + escapeHtml(item.opponent_team_name) + '</span></div>';
            html += '<div class="history-item-detail"><label>試合日</label><span>' + (item.schedule_date || '-') + '</span></div>';
            html += '<div class="history-item-detail"><label>時間</label><span>' + (item.schedule_start && item.schedule_end ? item.schedule_start + '～' + item.schedule_end : '-') + '</span></div>';
            html += '<div class="history-item-detail"><label>会場</label><span>' + (item.venue || '-') + '</span></div>';
            html += '<div class="history-item-detail"><label>申請日時</label><span>' + item.created_at + '</span></div>';
            html += '</div>';
            if (item.request_message) {
                html += '<div class="history-item-message"><strong>メッセージ:</strong> ' + escapeHtml(item.request_message) + '</div>';
            }
            html += '</div>';
        });

        $('#match-history-list').html(html);
    }

    // ページネーションを表示
    function displayPagination(pagination) {
        const { page, total_pages, total } = pagination;

        $('#pagination-info').text(`全 ${total}件中 ${((page - 1) * currentFilters.per_page + 1)}-${Math.min(page * currentFilters.per_page, total)}件を表示`);

        let html = '';

        // 前へボタン（デザインリファレンスに合わせて「« 前へ」形式）
        html += '<button class="pagination-btn" data-page="' + (page - 1) + '" ' + (page <= 1 ? 'disabled' : '') + '>« 前へ</button>';

        // ページ番号
        const maxPages = 10;
        let startPage = Math.max(1, page - Math.floor(maxPages / 2));
        let endPage = Math.min(total_pages, startPage + maxPages - 1);

        if (endPage - startPage < maxPages - 1) {
            startPage = Math.max(1, endPage - maxPages + 1);
        }

        if (startPage > 1) {
            html += '<button class="pagination-btn" data-page="1">1</button>';
            if (startPage > 2) {
                html += '<span class="pagination-ellipsis">...</span>';
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            html += '<button class="pagination-btn' + (i === page ? ' active' : '') + '" data-page="' + i + '">' + i + '</button>';
        }

        if (endPage < total_pages) {
            if (endPage < total_pages - 1) {
                html += '<span class="pagination-ellipsis">...</span>';
            }
            html += '<button class="pagination-btn" data-page="' + total_pages + '">' + total_pages + '</button>';
        }

        // 次へボタン（デザインリファレンスに合わせて「次へ »」形式）
        html += '<button class="pagination-btn" data-page="' + (page + 1) + '" ' + (page >= total_pages ? 'disabled' : '') + '>次へ »</button>';

        $('#pagination').html(html);
    }

    // HTMLエスケープ
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text ? text.replace(/[&<>"']/g, m => map[m]) : '';
    }

    // フィルター適用
    $('#apply-filter').on('click', function() {
        currentFilters.status = $('#status-filter').val();
        currentFilters.date_from = $('#date-from').val();
        currentFilters.date_to = $('#date-to').val();
        currentFilters.team_name = $('#team-name-filter').val();

        // localStorageに保存
        localStorage.setItem('match_history_filters', JSON.stringify(currentFilters));

        loadMatchHistory(1);
    });

    // フィルターリセット
    $('#reset-filter').on('click', function() {
        currentFilters = {
            status: 'all',
            date_from: cfg.defaultDateFrom || '',
            date_to: cfg.defaultDateTo || '',
            team_name: '',
            search: '',
            per_page: 20,
            type: 'all'
        };

        $('#status-filter').val('all');
        $('#date-from').val(currentFilters.date_from);
        $('#date-to').val(currentFilters.date_to);
        $('#team-name-filter').val('');
        $('#search-input').val('');
        $('#per-page-select').val(20);

        localStorage.removeItem('match_history_filters');

        loadMatchHistory(1);
    });

    // 検索
    $('#search-btn').on('click', function() {
        currentFilters.search = $('#search-input').val();
        loadMatchHistory(1);
    });

    // Enterキーで検索
    $('#search-input').on('keypress', function(e) {
        if (e.which === 13) {
            $('#search-btn').click();
        }
    });

    // 表示件数変更
    $('#per-page-select').on('change', function() {
        currentFilters.per_page = parseInt($(this).val());
        localStorage.setItem('match_history_filters', JSON.stringify(currentFilters));
        loadMatchHistory(1);
    });

    // ページネーション
    $(document).on('click', '.pagination-btn', function() {
        const page = parseInt($(this).data('page'));
        if (page && !$(this).prop('disabled')) {
            loadMatchHistory(page);
        }
    });

    // 初期読み込み
    loadMatchHistory(1);
  });
})(jQuery);
