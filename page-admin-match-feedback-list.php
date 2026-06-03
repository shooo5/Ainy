<?php
/**
 * Template Name: 管理用試合後アンケート一覧
 *
 * 使い方: 固定ページスラッグ「admin-match-feedback-list」、本テンプレートを選択。
 * Ainy ダッシュボード「データ管理」から遷移。
 */

require_once get_template_directory() . '/functions/common/auth-middleware.php';
$auth_result = AidUniteAuthMiddleware::require_admin(true);
if (!$auth_result->is_valid()) {
    return;
}

require_once get_template_directory() . '/functions/match/admin-match-feedback-list.php';

$filters = aidunite_admin_match_feedback_list_get_filters_from_request();
$has_active_filters = aidunite_admin_match_feedback_list_has_active_filters($filters);

$global_total = count(get_posts([
    'post_type'      => 'match_feedback',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
]));

$all_posts = get_posts([
    'post_type'      => 'match_feedback',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'date',
    'order'          => 'DESC',
]);

if ($has_active_filters) {
    $filtered = [];
    foreach ($all_posts as $post) {
        if (aidunite_admin_match_feedback_list_matches_filters($filters, $post)) {
            $filtered[] = $post;
        }
    }
    $all_posts = $filtered;
}

if (isset($_GET['csv']) && $_GET['csv'] === '1') {
    aidunite_admin_match_feedback_list_export_csv($all_posts);
}

$list_page_var = 'mfl_paged';
$per_page = defined('AIDUNITE_ADMIN_MATCH_FEEDBACK_LIST_PER_PAGE')
    ? (int) AIDUNITE_ADMIN_MATCH_FEEDBACK_LIST_PER_PAGE
    : 25;
$per_page = max(1, min(100, $per_page));

$total_count = count($all_posts);
$total_pages = $total_count > 0 ? (int) ceil($total_count / $per_page) : 1;
$requested_page = 0;
if (isset($_GET[$list_page_var])) {
    $requested_page = (int) $_GET[$list_page_var];
} elseif (isset($_GET['paged'])) {
    $requested_page = (int) $_GET['paged'];
}
$current_page = $requested_page > 0 ? max(1, min($requested_page, $total_pages)) : 1;
$offset = ($current_page - 1) * $per_page;
$posts_page = array_slice($all_posts, $offset, $per_page);

$filter_query_args = aidunite_admin_match_feedback_list_filters_to_query_args($filters);
$pagination_base = add_query_arg($filter_query_args, get_permalink());
$reset_url = remove_query_arg(
    array_merge(array_keys($filter_query_args), [$list_page_var, 'paged', 'csv']),
    get_permalink()
);
$csv_url = add_query_arg(array_merge($filter_query_args, ['csv' => '1']), get_permalink());
$dashboard_url = home_url('/ainy-dashboard');
$match_requests_url = home_url('/match-requests');

get_header();
?>

<div class="wrap page-admin-match-feedback-list-wrap">
    <header class="admin-mfl-header">
        <h1>試合後のアンケート一覧</h1>
        <p class="admin-mfl-lead">
            チーム代表者が送信した試合後の振り返り（<code>match_feedback</code>）を一覧表示します。相手チームの回答内容は代表者向け画面では閲覧できませんが、管理者は本画面で確認できます。
        </p>
        <nav class="admin-mfl-nav" aria-label="関連リンク">
            <a href="<?php echo esc_url($dashboard_url); ?>" class="button">← Ainy ダッシュボード</a>
            <a href="<?php echo esc_url($match_requests_url); ?>" class="button">マッチ申請一覧</a>
            <a href="<?php echo esc_url($csv_url); ?>" class="button button-primary">CSV 出力</a>
        </nav>
    </header>

    <div class="search-filter-section admin-mfl-filters">
        <h2 class="admin-mfl-filters__title">フィルター</h2>
        <form method="get" class="admin-mfl-filters__form">
            <div class="admin-mfl-filters__field">
                <label for="mfl_match_id">マッチ ID</label>
                <input type="number" id="mfl_match_id" name="match_id" class="admin-mfl-filters__input" min="0" value="<?php echo (int) ($filters['match_id'] ?? 0) > 0 ? (int) $filters['match_id'] : ''; ?>" placeholder="例: 1234">
            </div>
            <div class="admin-mfl-filters__field">
                <label for="mfl_team_id">回答チーム ID</label>
                <input type="number" id="mfl_team_id" name="team_id" class="admin-mfl-filters__input" min="0" value="<?php echo (int) ($filters['team_id'] ?? 0) > 0 ? (int) $filters['team_id'] : ''; ?>">
            </div>
            <div class="admin-mfl-filters__field">
                <label for="mfl_rematch">再戦希望</label>
                <select id="mfl_rematch" name="rematch_interest" class="admin-mfl-filters__input">
                    <option value="">すべて</option>
                    <option value="yes" <?php selected($filters['rematch_interest'] ?? '', 'yes'); ?>>はい</option>
                    <option value="no" <?php selected($filters['rematch_interest'] ?? '', 'no'); ?>>いいえ</option>
                </select>
            </div>
            <div class="admin-mfl-filters__field">
                <label for="mfl_date_from">回答日（から）</label>
                <input type="date" id="mfl_date_from" name="date_from" class="admin-mfl-filters__input" value="<?php echo esc_attr($filters['date_from'] ?? ''); ?>">
            </div>
            <div class="admin-mfl-filters__field">
                <label for="mfl_date_to">回答日（まで）</label>
                <input type="date" id="mfl_date_to" name="date_to" class="admin-mfl-filters__input" value="<?php echo esc_attr($filters['date_to'] ?? ''); ?>">
            </div>
            <div class="admin-mfl-filters__field admin-mfl-filters__field--wide">
                <label for="mfl_search">キーワード</label>
                <input type="search" id="mfl_search" name="search" class="admin-mfl-filters__input" value="<?php echo esc_attr($filters['search'] ?? ''); ?>" placeholder="チーム名・コメントなど">
            </div>
            <div class="admin-mfl-filters__actions">
                <button type="submit" class="button button-primary">絞り込む</button>
                <a href="<?php echo esc_url($reset_url); ?>" class="button">リセット</a>
            </div>
        </form>
        <?php if ($has_active_filters) : ?>
        <p class="admin-mfl-filters__active" role="status">フィルター適用中 — 該当 <?php echo (int) $total_count; ?> 件</p>
        <?php endif; ?>
    </div>

    <p class="admin-mfl-summary" role="status">
        登録 <?php echo (int) $global_total; ?> 件<?php echo $has_active_filters ? ' — 表示 ' . (int) $total_count . ' 件（絞り込み後）' : ''; ?>（<?php echo (int) $current_page; ?> / <?php echo (int) max(1, $total_pages); ?> ページ）
    </p>

    <?php if (empty($posts_page)) : ?>
    <div class="admin-mfl-empty" role="status">
        <p>該当するアンケート回答がありません。</p>
    </div>
    <?php else : ?>
    <div class="admin-mfl-table-wrap">
        <table class="admin-mfl-table wp-list-table widefat striped">
            <thead>
                <tr class="admin-mfl-group-row">
                    <th colspan="4" scope="colgroup">基本</th>
                    <th colspan="4" scope="colgroup" class="admin-mfl-col-group-start">チーム</th>
                    <th colspan="4" scope="colgroup" class="admin-mfl-col-group-start">アンケート</th>
                    <th colspan="3" scope="colgroup" class="admin-mfl-col-group-start">回答者・操作</th>
                </tr>
                <tr>
                    <th scope="col" class="admin-cell admin-cell--id">ID</th>
                    <th scope="col" class="admin-cell admin-cell--date">回答日</th>
                    <th scope="col" class="admin-cell admin-cell--time">回答時刻</th>
                    <th scope="col" class="admin-cell admin-cell--id">マッチID</th>
                    <th scope="col" class="admin-mfl-col-group-start">回答チーム名</th>
                    <th scope="col" class="admin-cell admin-cell--id">回答チームID</th>
                    <th scope="col">相手チーム名</th>
                    <th scope="col" class="admin-cell admin-cell--id">相手チームID</th>
                    <th scope="col" class="admin-mfl-col-group-start">満足度</th>
                    <th scope="col">良かった点</th>
                    <th scope="col">再戦希望</th>
                    <th scope="col">再戦理由（抜粋）</th>
                    <th scope="col" class="admin-mfl-col-group-start">回答者名</th>
                    <th scope="col" class="admin-cell admin-cell--id">回答者ID</th>
                    <th scope="col">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($posts_page as $post) :
                    $row = aidunite_admin_match_feedback_list_row_data($post->ID);
                    if (empty($row)) {
                        continue;
                    }
                    $fid = (int) $row['id'];
                    $dt = aidunite_admin_match_feedback_list_split_datetime($row['created_at']);
                    $rematch_excerpt = $row['rematch_reason'] !== ''
                        ? (mb_strlen($row['rematch_reason']) > 40
                            ? mb_substr($row['rematch_reason'], 0, 40, 'UTF-8') . '…'
                            : $row['rematch_reason'])
                        : '—';
                    ?>
                <tr class="admin-mfl-row-main">
                    <td class="admin-cell admin-cell--id"><?php echo (int) $fid; ?></td>
                    <td class="admin-cell admin-cell--date"><?php echo esc_html($dt['date']); ?></td>
                    <td class="admin-cell admin-cell--time"><?php echo esc_html($dt['time']); ?></td>
                    <td class="admin-cell admin-cell--id">
                        <a href="<?php echo esc_url(add_query_arg('match_id', (int) $row['match_id'], $match_requests_url)); ?>">
                            <?php echo (int) $row['match_id']; ?>
                        </a>
                    </td>
                    <td class="admin-mfl-col-group-start"><?php echo esc_html($row['team_name']); ?></td>
                    <td class="admin-cell admin-cell--id"><?php echo (int) $row['team_id']; ?></td>
                    <td><?php echo esc_html($row['opponent_team_name']); ?></td>
                    <td class="admin-cell admin-cell--id"><?php echo (int) $row['opponent_team_id']; ?></td>
                    <td class="admin-mfl-col-group-start"><?php echo esc_html(aidunite_admin_match_feedback_list_star_display((int) $row['satisfaction'])); ?></td>
                    <td class="admin-mfl-cell-reasons"><?php echo aidunite_admin_match_feedback_list_render_reason_chips($row['satisfaction_reasons']); ?></td>
                    <td>
                        <span class="admin-mfl-rematch admin-mfl-rematch--<?php echo esc_attr($row['rematch_interest'] ?: 'none'); ?>">
                            <?php echo esc_html($row['rematch_interest_label']); ?>
                        </span>
                    </td>
                    <td class="admin-mfl-cell-excerpt" title="<?php echo esc_attr($row['rematch_reason']); ?>"><?php echo esc_html($rematch_excerpt); ?></td>
                    <td class="admin-mfl-col-group-start"><?php echo esc_html($row['user_display']); ?></td>
                    <td class="admin-cell admin-cell--id"><?php echo (int) $row['user_id']; ?></td>
                    <td>
                        <button type="button" class="button button-small admin-mfl-details-toggle" data-feedback-id="<?php echo (int) $fid; ?>" aria-expanded="false" aria-controls="mfl-details-<?php echo (int) $fid; ?>">
                            全文
                        </button>
                    </td>
                </tr>
                <tr id="mfl-details-<?php echo (int) $fid; ?>" class="admin-mfl-details" hidden>
                    <td colspan="15">
                        <div class="admin-mfl-details__inner">
                            <button type="button" class="button button-small admin-mfl-details-close" data-feedback-id="<?php echo (int) $fid; ?>">閉じる</button>
                            <div class="admin-mfl-details__sections">
                                <section class="admin-mfl-details__section" aria-labelledby="mfl-sec-survey-<?php echo (int) $fid; ?>">
                                    <h3 class="admin-mfl-details__heading" id="mfl-sec-survey-<?php echo (int) $fid; ?>">アンケート（全文）</h3>
                                    <dl class="admin-mfl-details__grid">
                                        <div><dt>再戦理由</dt><dd><?php echo $row['rematch_reason'] !== '' ? esc_html($row['rematch_reason']) : '—'; ?></dd></div>
                                        <div class="admin-mfl-details__field--wide"><dt>自由記述</dt><dd><?php echo $row['comment'] !== '' ? esc_html($row['comment']) : '—'; ?></dd></div>
                                    </dl>
                                </section>
                                <section class="admin-mfl-details__section" aria-labelledby="mfl-sec-legacy-<?php echo (int) $fid; ?>">
                                    <h3 class="admin-mfl-details__heading" id="mfl-sec-legacy-<?php echo (int) $fid; ?>">旧項目（参考）</h3>
                                    <dl class="admin-mfl-details__grid">
                                        <div><dt>相手評価</dt><dd><?php echo (int) $row['opponent_rating'] > 0 ? esc_html((string) $row['opponent_rating']) : '—'; ?></dd></div>
                                        <div><dt>会場評価</dt><dd><?php echo (int) $row['venue_rating'] > 0 ? esc_html((string) $row['venue_rating']) : '—'; ?></dd></div>
                                        <?php if ($row['venue_improvement'] !== '') : ?>
                                        <div class="admin-mfl-details__field--wide"><dt>会場改善</dt><dd><?php echo esc_html($row['venue_improvement']); ?></dd></div>
                                        <?php endif; ?>
                                    </dl>
                                </section>
                                <section class="admin-mfl-details__section" aria-labelledby="mfl-sec-sys-<?php echo (int) $fid; ?>">
                                    <h3 class="admin-mfl-details__heading" id="mfl-sec-sys-<?php echo (int) $fid; ?>">システム</h3>
                                    <dl class="admin-mfl-details__grid">
                                        <div><dt>回答 ID</dt><dd><?php echo (int) $fid; ?></dd></div>
                                        <div><dt>投稿日（WP）</dt><dd><?php echo esc_html($row['post_date']); ?></dd></div>
                                        <div><dt>回答日時（raw）</dt><dd><?php echo esc_html($row['created_at']); ?></dd></div>
                                    </dl>
                                </section>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1) : ?>
    <nav class="admin-mfl-pagination" aria-label="ページ送り">
        <?php
        echo paginate_links([
            'base'      => add_query_arg($list_page_var, '%#%', $pagination_base),
            'format'    => '',
            'current'   => $current_page,
            'total'     => $total_pages,
            'prev_text' => '« 前へ',
            'next_text' => '次へ »',
        ]);
        ?>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
(function () {
    function toggleDetails(feedbackId, open) {
        var row = document.getElementById('mfl-details-' + feedbackId);
        var btn = document.querySelector('.admin-mfl-details-toggle[data-feedback-id="' + feedbackId + '"]');
        if (!row) return;
        var show = typeof open === 'boolean' ? open : row.hasAttribute('hidden');
        if (show) {
            row.removeAttribute('hidden');
            if (btn) btn.setAttribute('aria-expanded', 'true');
        } else {
            row.setAttribute('hidden', 'hidden');
            if (btn) btn.setAttribute('aria-expanded', 'false');
        }
    }
    document.querySelectorAll('.admin-mfl-details-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            toggleDetails(btn.getAttribute('data-feedback-id'));
        });
    });
    document.querySelectorAll('.admin-mfl-details-close').forEach(function (btn) {
        btn.addEventListener('click', function () {
            toggleDetails(btn.getAttribute('data-feedback-id'), false);
        });
    });
})();
</script>

<?php
get_footer();
