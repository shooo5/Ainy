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
        'page_class' => 'page-match-history',
        'title' => $page_title,
        'subtitle' => $page_description,
    ]);
} else {
    echo '<div class="team-dashboard-container page-match-history"><div class="dashboard-header"><h1>' . esc_html($page_title) . '</h1><p>' . esc_html($page_description) . '</p></div>';
}
?>

    <!-- 申請履歴セクション -->
    <section class="dashboard-section">
        <div class="main-content-area">
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
/* 申請履歴ページ専用スタイル */

.page-match-history .filter-section,
.page-match-history .search-section {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
}

.page-match-history .filter-controls {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.page-match-history .filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.page-match-history .filter-group label {
    font-weight: 600;
    font-size: 0.9rem;
}

.page-match-history .filter-select,
.page-match-history .filter-input {
    padding: 0.5rem;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 0.9rem;
}

.page-match-history .search-controls {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
}

.page-match-history .search-input {
    flex: 1;
    padding: 0.5rem;
    border: 1px solid var(--border-color);
    border-radius: 4px;
}

.page-match-history .pagination-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.page-match-history .match-history-list {
    min-height: 200px;
}

.page-match-history .history-item {
    background: #fff;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    transition: box-shadow 0.3s;
}

.page-match-history .history-item:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.page-match-history .history-item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.page-match-history .history-item-type {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 4px;
    font-size: 0.85rem;
    font-weight: 600;
}

.page-match-history .history-item-type.sent {
    background: rgba(23, 162, 184, 0.1);
    color: var(--info-color);
}

.page-match-history .history-item-type.received {
    background: rgba(177, 108, 234, 0.1);
    color: var(--secondary-color);
}

.page-match-history .status-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 4px;
    font-size: 0.85rem;
    font-weight: 600;
}

.page-match-history .status-badge.status-pending {
    background: #fff3cd;
    color: #856404;
}

.page-match-history .status-badge.status-approved {
    background: rgba(40, 167, 69, 0.1);
    color: var(--success-color);
}

.page-match-history .status-badge.status-rejected {
    background: #f8d7da;
    color: #721c24;
}

.page-match-history .status-badge.status-cancelled {
    background: var(--border-light);
    color: var(--text-secondary);
}

.page-match-history .status-badge.status-established {
    background: rgba(23, 162, 184, 0.1);
    color: var(--info-color);
}

.page-match-history .history-item-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}

.page-match-history .history-item-detail {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.page-match-history .history-item-detail label {
    font-size: 0.85rem;
    color: var(--text-secondary);
    font-weight: 600;
}

.page-match-history .history-item-detail span {
    font-size: 0.95rem;
    color: var(--text-primary);
}

.page-match-history .history-item-message {
    margin-top: 1rem;
    padding: 0.75rem;
    background: #f8f9fa;
    border-radius: 4px;
    font-size: 0.9rem;
}

/* ページネーション（デザインリファレンスに合わせたスタイル） */
.page-match-history .pagination {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    justify-content: center;
    margin-top: 2rem;
}

.page-match-history .pagination-btn {
    padding: 0.5rem 1rem;
    border: 1px solid var(--border-light);
    background: #fff;
    color: var(--text-primary);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.9rem;
    font-weight: 500;
}

.page-match-history .pagination-btn:hover:not(:disabled) {
    background: #f8f9fa;
    border-color: var(--secondary-color);
}

.page-match-history .pagination-btn.active {
    background: #9b8ffa;
    color: white;
    border-color: var(--secondary-color);
    font-weight: 600;
}

.page-match-history .pagination-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.page-match-history .pagination-ellipsis {
    padding: 0.5rem 0.25rem;
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.page-match-history .empty-state {
    text-align: center;
    padding: 3rem;
    color: var(--text-secondary);
}

.page-match-history .loading {
    text-align: center;
    padding: 2rem;
    color: var(--text-secondary);
}

@media (max-width: 768px) {
    .page-match-history {
        padding: 1rem;
    }

    .page-match-history .filter-controls {
        grid-template-columns: 1fr;
    }

    .page-match-history .history-item-details {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    let currentPage = 1;
    let currentFilters = {
        status: 'all',
        date_from: '<?php echo date('Y-m-d', strtotime('-3 months')); ?>',
        date_to: '<?php echo date('Y-m-d'); ?>',
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
            url: '<?php echo esc_url(rest_url('aidunite/v1/match-history')); ?>',
            method: 'GET',
            data: params,
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce("wp_rest"); ?>');
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
            date_from: '<?php echo date('Y-m-d', strtotime('-3 months')); ?>',
            date_to: '<?php echo date('Y-m-d'); ?>',
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
</script>

<?php get_footer(); ?>
