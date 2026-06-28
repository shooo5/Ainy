<?php
/*
Template Name: 管理用スケジュール一覧
 *
 * 使い方: 固定ページを新規作成し、スラッグを「admin-schedule-list」に、
 * テンプレートで「管理用スケジュール一覧」を選択してください。
 */
require_once get_template_directory() . '/functions/common/auth-middleware.php';
$auth_result = AidUniteAuthMiddleware::require_admin(true);
if (!$auth_result->is_valid()) {
    return;
}

require_once get_template_directory() . '/functions/schedule/admin-schedule-list.php';
require_once get_template_directory() . '/functions/team/team-display-template.php';

// 単体削除
if (isset($_POST['delete_schedule_id'])) {
    $delete_schedule_id = (int) $_POST['delete_schedule_id'];
    $nonce_result = AidUniteAuthMiddleware::verify_nonce('_wpnonce', 'delete_schedule_' . $delete_schedule_id);
    if (is_wp_error($nonce_result)) {
        wp_die($nonce_result->get_error_message(), 'エラー', ['response' => 403]);
    }
    $delete_result = aidunite_admin_schedule_list_delete_schedule($delete_schedule_id);
    if (is_wp_error($delete_result)) {
        $GLOBALS['aidunite_admin_schedule_list_notice'] = [
            'type' => 'error',
            'message' => $delete_result->get_error_message(),
        ];
    } else {
        $GLOBALS['aidunite_admin_schedule_list_notice'] = [
            'type' => 'success',
            'message' => 'スケジュール「' . $delete_result['title'] . '」（ID ' . $delete_result['schedule_id'] . '）を削除しました。',
        ];
    }
}

// 一括削除
if (isset($_POST['bulk_delete_schedules'])) {
    $nonce_result = AidUniteAuthMiddleware::verify_nonce('_wpnonce', 'bulk_delete_schedules');
    if (is_wp_error($nonce_result)) {
        wp_die($nonce_result->get_error_message(), 'エラー', ['response' => 403]);
    }

    $schedule_ids = isset($_POST['schedule_ids']) ? array_map('intval', (array) $_POST['schedule_ids']) : [];
    $schedule_ids = array_values(array_unique(array_filter($schedule_ids)));
    $deleted_count = 0;
    $failed = [];

    foreach ($schedule_ids as $sid) {
        $result = aidunite_admin_schedule_list_delete_schedule($sid);
        if (is_wp_error($result)) {
            $failed[] = $sid . ': ' . $result->get_error_message();
            continue;
        }
        $deleted_count++;
    }

    if ($deleted_count > 0 && empty($failed)) {
        $GLOBALS['aidunite_admin_schedule_list_notice'] = [
            'type' => 'success',
            'message' => (int) $deleted_count . ' 件のスケジュールを削除しました。',
        ];
    } elseif ($deleted_count > 0) {
        $GLOBALS['aidunite_admin_schedule_list_notice'] = [
            'type' => 'warning',
            'message' => (int) $deleted_count . ' 件を削除しました。削除できなかった件: ' . implode(' / ', $failed),
        ];
    } elseif (!empty($failed)) {
        $GLOBALS['aidunite_admin_schedule_list_notice'] = [
            'type' => 'error',
            'message' => '削除できませんでした: ' . implode(' / ', $failed),
        ];
    } else {
        $GLOBALS['aidunite_admin_schedule_list_notice'] = [
            'type' => 'warning',
            'message' => '削除するスケジュールが選択されていません。',
        ];
    }
}

$schedule_types_for_filter = aidunite_admin_schedule_list_collect_schedule_types();
$team_filter_options = aidunite_admin_schedule_list_get_team_filter_options();
$filters = aidunite_admin_schedule_list_get_filters_from_request($schedule_types_for_filter);
$filter_groups = aidunite_admin_schedule_list_get_filter_groups($schedule_types_for_filter);
$has_active_filters = aidunite_admin_schedule_list_has_active_filters($filters);
$active_filter_labels = aidunite_admin_schedule_list_get_active_filter_labels($filters);

$list_page_var = 'asl_paged';
$per_page = defined('AIDUNITE_ADMIN_SCHEDULE_LIST_PER_PAGE') ? (int) AIDUNITE_ADMIN_SCHEDULE_LIST_PER_PAGE : 25;
$per_page = max(1, min(100, $per_page));

$all_schedules = get_posts([
    'post_type' => 'schedule',
    'post_status' => ['publish', 'pending', 'draft', 'private', 'trash'],
    'numberposts' => -1,
    'orderby' => 'date',
    'order' => 'DESC',
]);

if ($has_active_filters) {
    $filtered = [];
    foreach ($all_schedules as $post) {
        if (aidunite_admin_schedule_list_matches_filters($post, $filters)) {
            $filtered[] = $post;
        }
    }
    $all_schedules = $filtered;
}

$total_count = count($all_schedules);
$total_pages = $total_count > 0 ? (int) ceil($total_count / $per_page) : 1;
$requested_page = 0;
if (isset($_GET[$list_page_var])) {
    $requested_page = (int) $_GET[$list_page_var];
} elseif (isset($_GET['paged'])) {
    $requested_page = (int) $_GET['paged'];
}
$current_page = $requested_page > 0 ? max(1, min($requested_page, $total_pages)) : 1;
$offset = ($current_page - 1) * $per_page;
$schedules_page = array_slice($all_schedules, $offset, $per_page);

$filter_query_args = aidunite_admin_schedule_list_filters_to_query_args($filters);
$pagination_base = add_query_arg($filter_query_args, get_permalink());
$reset_url = remove_query_arg(
    array_merge(array_keys($filter_groups), ['team_filter', 'team_id_filter', 'search', 'date_from', 'date_to', $list_page_var, 'paged']),
    get_permalink()
);

// CSV 出力
if (isset($_GET['csv']) && $_GET['csv'] === '1') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="schedules-' . date('Y-m-d-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, "\xEF\xBB\xBF");
    fputcsv($out, [
        'ID', '日付', '時間', '種別', '目的', '性別', '会場', '会場名',
        'チーム名', 'チームID', '作成者', '作成者ID', '作成日時',
    ]);
    foreach ($all_schedules as $post) {
        $row = aidunite_admin_schedule_list_row_data($post->ID);
        if (empty($row)) {
            continue;
        }
        fputcsv($out, [
            $row['id'],
            $row['schedule_date'],
            $row['time_display'],
            $row['schedule_type'],
            $row['intent_label'],
            $row['gender_label'],
            $row['place_label'],
            $row['venue_name'],
            $row['team_name'],
            $row['team_id'],
            $row['author_name'],
            $row['author_id'],
            $row['created'],
        ]);
    }
    fclose($out);
    exit;
}

get_header();
?>

<div class="wrap page-admin-schedule-list-wrap">
    <h1>スケジュール一覧（管理者用）</h1>
    <p style="color:var(--text-secondary); margin:0 0 var(--spacing-lg);">
        登録済みのスケジュールをデータ表形式で確認できます。<strong>会場</strong>は登録時の会場条件（ホーム／アウェイ等）、<strong>会場名</strong>は登録時の施設名です（成立後の home/away 変更では会場条件列は変わりません）。詳細行では投稿メタをすべて表示します。
    </p>

    <?php
    if (!empty($GLOBALS['aidunite_admin_schedule_list_notice'])) {
        $notice = $GLOBALS['aidunite_admin_schedule_list_notice'];
        $notice_class = 'notice-' . ($notice['type'] === 'success' ? 'success' : ($notice['type'] === 'warning' ? 'warning' : 'error'));
        echo '<div class="notice ' . esc_attr($notice_class) . '" style="margin:var(--spacing-base) 0;">' . esc_html($notice['message']) . '</div>';
        unset($GLOBALS['aidunite_admin_schedule_list_notice']);
    }
    ?>

    <div class="search-filter-section admin-schedule-filters" style="background:var(--bg-secondary); padding:var(--spacing-lg); margin:var(--spacing-lg) 0; border-radius:var(--radius-base);">
        <h3>🔍 フィルター</h3>
        <form method="get" class="admin-schedule-filters__form">
            <div class="admin-schedule-filters__field">
                <label for="team_filter">チーム名</label>
                <select id="team_filter" name="team_filter" class="admin-schedule-filters__input">
                    <?php foreach ($team_filter_options as $option_value => $option_label) : ?>
                    <option value="<?php echo esc_attr($option_value); ?>" <?php selected($filters['team_filter'] ?? '', $option_value); ?>><?php echo esc_html($option_label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="admin-schedule-filters__field">
                <label for="date_from">日付（から）</label>
                <input type="date" id="date_from" name="date_from" class="admin-schedule-filters__input" value="<?php echo esc_attr($filters['date_from']); ?>">
            </div>
            <div class="admin-schedule-filters__field">
                <label for="date_to">日付（まで）</label>
                <input type="date" id="date_to" name="date_to" class="admin-schedule-filters__input" value="<?php echo esc_attr($filters['date_to']); ?>">
            </div>
            <?php foreach ($filter_groups as $filter_key => $filter_group) : ?>
            <div class="admin-schedule-filters__field">
                <label for="<?php echo esc_attr($filter_key); ?>"><?php echo esc_html($filter_group['label']); ?></label>
                <select id="<?php echo esc_attr($filter_key); ?>" name="<?php echo esc_attr($filter_key); ?>" class="admin-schedule-filters__input">
                    <?php foreach ($filter_group['options'] as $option_value => $option_label) : ?>
                    <option value="<?php echo esc_attr($option_value); ?>" <?php selected($filters[$filter_key] ?? '', $option_value); ?>><?php echo esc_html($option_label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endforeach; ?>
            <div class="admin-schedule-filters__actions">
                <button type="submit" class="button button-primary">絞り込む</button>
                <a href="<?php echo esc_url($reset_url); ?>" class="button">リセット</a>
            </div>
        </form>

        <?php if ($has_active_filters) : ?>
        <div style="margin-top:var(--spacing-base); padding:var(--spacing-sm); background:rgba(23, 162, 184, 0.1); border-radius:var(--radius-small);">
            <strong>絞り込み結果:</strong> <?php echo (int) $total_count; ?>件
            <?php foreach ($active_filter_labels as $label_name => $label_value) : ?>
                | <?php echo esc_html($label_name); ?>: <?php echo esc_html($label_value); ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div style="margin-top:var(--spacing-base); display:flex; flex-wrap:wrap; align-items:center; gap:var(--spacing-base);">
            <p style="margin:0;">
                全 <?php echo (int) $total_count; ?> 件
                <?php if ($total_pages > 1) : ?>
                    （<?php echo (int) ($offset + 1); ?> - <?php echo (int) min($offset + $per_page, $total_count); ?> 件目）
                <?php endif; ?>
            </p>
            <a href="<?php echo esc_url(add_query_arg(array_merge($filter_query_args, ['csv' => '1']), get_permalink())); ?>" class="button">CSVで出力</a>
            <a href="<?php echo esc_url(home_url('/schedule-management')); ?>" class="button button-secondary">カレンダー管理へ</a>
        </div>
    </div>

    <?php if ($total_pages > 1) : ?>
    <nav style="margin-bottom:var(--spacing-base);" aria-label="ページ送り">
        <?php
        echo paginate_links([
            'base' => $pagination_base . '%_%',
            'format' => '?' . $list_page_var . '=%#%',
            'current' => $current_page,
            'total' => $total_pages,
            'prev_text' => '&laquo; 前へ',
            'next_text' => '次へ &raquo;',
            'mid_size' => 2,
            'end_size' => 1,
        ]);
        ?>
    </nav>
    <?php endif; ?>

    <?php if (!empty($schedules_page)) : ?>
    <div class="schedule-bulk-delete-section" style="background:rgba(220, 53, 69, 0.08); padding:var(--spacing-base); margin:var(--spacing-lg) 0; border-radius:var(--radius-base); border:1px solid var(--danger-color);">
        <h3 style="margin:0 0 var(--spacing-sm) 0;">🗑️ スケジュール一括削除</h3>
        <p style="margin:0 0 var(--spacing-base) 0; font-size:var(--font-size-sm, 0.875rem); color:var(--text-secondary);">
            一覧で選択したスケジュールを一括削除できます（表示中のページのみ）。申請中・成立済みマッチがある場合は削除できません。
        </p>
        <form method="post" id="schedule-bulk-delete-form">
            <div style="display:flex; flex-wrap:wrap; align-items:center; gap:var(--spacing-base);">
                <span id="schedule-bulk-selected-count" style="color:var(--text-secondary); font-size:var(--font-size-sm, 0.875rem);" aria-live="polite">0件選択</span>
                <button type="submit" name="bulk_delete_schedules" value="1" class="button button-danger" id="schedule-bulk-delete-btn" disabled>選択したスケジュールを一括削除</button>
            </div>
            <?php wp_nonce_field('bulk_delete_schedules'); ?>
        </form>
    </div>
    <?php endif; ?>

    <div style="overflow-x:auto;">
        <table class="admin-schedule-table wp-list-table widefat striped">
            <thead>
                <tr>
                    <th class="col-checkbox">
                        <label class="aidunite-admin-checkbox" title="すべて選択">
                            <input type="checkbox" id="select-all-schedules" class="aidunite-admin-checkbox__input" aria-label="すべて選択"<?php echo empty($schedules_page) ? ' disabled' : ''; ?>>
                            <span class="aidunite-admin-checkbox__box" aria-hidden="true"></span>
                        </label>
                    </th>
                    <th class="col-no">No</th>
                    <th class="admin-cell admin-cell--id">schedule ID</th>
                    <th class="admin-cell admin-cell--date">日付</th>
                    <th class="admin-cell admin-cell--time">時間</th>
                    <th class="admin-cell admin-cell--text">種別</th>
                    <th class="admin-cell admin-cell--text">目的</th>
                    <th class="admin-cell admin-cell--gender">性別</th>
                    <th class="admin-cell admin-cell--venue">会場</th>
                    <th class="admin-cell admin-cell--text">会場名</th>
                    <th>チーム名</th>
                    <th>チームID</th>
                    <th>作成者</th>
                    <th>作成者ID</th>
                    <th>作成日</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($schedules_page)) : ?>
                <tr>
                    <td colspan="16" style="text-align:center; padding:var(--spacing-xl);">スケジュールが見つかりません。</td>
                </tr>
                <?php endif; ?>
                <?php foreach ($schedules_page as $idx => $post) :
                    $row = aidunite_admin_schedule_list_row_data($post->ID);
                    if (empty($row)) {
                        continue;
                    }
                    $row_no = $offset + $idx + 1;
                    $sid = (int) $row['id'];
                    $intent_class = 'status-intent-' . ($row['intent'] !== '' ? sanitize_html_class($row['intent']) : 'none');
                ?>
                <tr>
                    <td class="col-checkbox">
                        <label class="aidunite-admin-checkbox">
                            <input type="checkbox" class="schedule-row-checkbox aidunite-admin-checkbox__input" value="<?php echo (int) $sid; ?>" data-schedule-id="<?php echo (int) $sid; ?>" form="schedule-bulk-delete-form" aria-label="<?php echo esc_attr('スケジュールID ' . $sid . ' を選択'); ?>">
                            <span class="aidunite-admin-checkbox__box" aria-hidden="true"></span>
                        </label>
                    </td>
                    <td class="col-no"><?php echo (int) $row_no; ?></td>
                    <?php aidunite_admin_list_echo_cell('id', (string) $sid); ?>
                    <?php aidunite_admin_list_echo_cell('date', $row['schedule_date']); ?>
                    <?php aidunite_admin_list_echo_cell('time', $row['time_display']); ?>
                    <?php aidunite_admin_list_echo_cell('text', $row['schedule_type']); ?>
                    <td class="admin-cell admin-cell--text">
                        <span class="status-badge <?php echo esc_attr($intent_class); ?>"><?php echo esc_html($row['intent_label']); ?></span>
                    </td>
                    <?php aidunite_admin_list_echo_cell('gender', $row['gender_label']); ?>
                    <?php aidunite_admin_list_echo_cell('venue', $row['place_label']); ?>
                    <?php aidunite_admin_list_echo_cell('text', $row['venue_name']); ?>
                    <?php
                    if ($row['team_id'] !== '—') {
                        $team_link = '<a href="' . esc_url(add_query_arg('team_id', $row['team_id'], home_url('/team-settings'))) . '">'
                            . esc_html($row['team_name']) . '</a>';
                        aidunite_admin_list_echo_cell_html('text', $team_link);
                    } else {
                        aidunite_admin_list_echo_cell('text', '—');
                    }
                    ?>
                    <?php aidunite_admin_list_echo_cell('id', $row['team_id'] !== '—' ? (string) $row['team_id'] : '—'); ?>
                    <?php aidunite_admin_list_echo_cell('text', $row['author_name']); ?>
                    <?php aidunite_admin_list_echo_cell('id', $row['author_id'] !== '—' ? (string) $row['author_id'] : '—'); ?>
                    <?php aidunite_admin_list_echo_cell('datetime', $row['created']); ?>
                    <td>
                        <a href="#" class="details-toggle" data-schedule-id="<?php echo (int) $sid; ?>">詳細</a>
                        <?php if (!empty($row['edit_url'])) : ?>
                        | <a href="<?php echo esc_url($row['edit_url']); ?>">編集</a>
                        <?php endif; ?>
                        | <form method="post" style="display:inline;" data-aidunite-confirm="スケジュール ID <?php echo (int) $sid; ?> を削除しますか？&#10;申請中・成立済みマッチがある場合は削除できません。" data-aidunite-confirm-label="削除する">
                            <?php wp_nonce_field('delete_schedule_' . $sid); ?>
                            <input type="hidden" name="delete_schedule_id" value="<?php echo (int) $sid; ?>">
                            <button type="submit" class="button button-small button-danger">削除</button>
                        </form>
                    </td>
                </tr>
                <tr id="schedule-details-<?php echo (int) $sid; ?>" class="schedule-details">
                    <td colspan="16">
                        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:var(--spacing-sm);">
                            <h4 style="margin:0;">メタデータ（ID: <?php echo (int) $sid; ?>）</h4>
                            <button type="button" class="button button-small schedule-details-close" data-schedule-id="<?php echo (int) $sid; ?>">閉じる</button>
                        </div>
                        <p style="margin:var(--spacing-sm) 0; font-size:var(--font-size-sm); color:var(--text-secondary);">
                            募集中: <?php echo esc_html($row['matching']); ?>
                            <?php if (!empty($row['wp_edit_url'])) : ?>
                            | <a href="<?php echo esc_url($row['wp_edit_url']); ?>">WP管理画面で開く</a>
                            <?php endif; ?>
                        </p>
                        <div class="schedule-meta-grid">
                            <?php
                            $all_meta = is_array($row['all_meta']) ? $row['all_meta'] : [];
                            ksort($all_meta);
                            foreach ($all_meta as $meta_key => $meta_values) :
                                if (!is_array($meta_values)) {
                                    $meta_values = [$meta_values];
                                }
                                $display_val = implode(', ', array_map(static function ($v) {
                                    if (is_array($v) || is_object($v)) {
                                        return wp_json_encode($v);
                                    }
                                    return (string) $v;
                                }, $meta_values));
                                if ($display_val === '') {
                                    continue;
                                }
                            ?>
                            <div class="schedule-meta-item">
                                <code><?php echo esc_html($meta_key); ?></code>
                                <?php echo esc_html($display_val); ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1) : ?>
    <nav style="margin-top:var(--spacing-xl); display:flex; justify-content:center;" aria-label="ページ送り（下）">
        <?php
        echo paginate_links([
            'base' => $pagination_base . '%_%',
            'format' => '?' . $list_page_var . '=%#%',
            'current' => $current_page,
            'total' => $total_pages,
            'prev_text' => '&laquo; 前へ',
            'next_text' => '次へ &raquo;',
            'mid_size' => 2,
            'end_size' => 1,
        ]);
        ?>
    </nav>
    <?php endif; ?>
</div>

<?php get_footer(); ?>
