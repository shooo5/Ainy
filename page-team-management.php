<?php
/*
Template Name: チーム管理（管理者専用）
*/
// 統一認証・権限チェック（管理者のみ）
require_once get_template_directory() . '/functions/common/auth-middleware.php';
$auth_result = AidUniteAuthMiddleware::require_admin(true);
if (!$auth_result->is_valid()) {
    // リダイレクトは自動で実行される
    return;
}

require_once get_template_directory() . '/functions/team/team-display-template.php';
require_once get_template_directory() . '/functions/team/team-functions.php';

// チーム削除処理
if (isset($_POST['delete_team_id'])) {
    // CSRF対策（統一版）
    $delete_team_id = intval($_POST['delete_team_id']);
    $nonce_result = AidUniteAuthMiddleware::verify_nonce('_wpnonce', 'delete_team_' . $delete_team_id);
    if (is_wp_error($nonce_result)) {
        wp_die($nonce_result->get_error_message(), 'エラー', ['response' => 403]);
    }

    if ($delete_team_id > 0 && function_exists('aidunite_admin_delete_team')) {
        $result = aidunite_admin_delete_team($delete_team_id);
        if (is_wp_error($result)) {
            if (function_exists('aidunite_team_management_set_flash_notice')) {
                aidunite_team_management_set_flash_notice('error', $result->get_error_message());
            }
        } else {
            $msg = 'チーム「' . $result['team_name'] . '」（ID ' . $result['team_id'] . '）を削除しました。'
                . '（メンバー ' . (int) $result['members'] . ' 名の所属を解除、関連投稿 ' . (int) $result['related_posts'] . ' 件を削除）';
            if (function_exists('aidunite_team_management_set_flash_notice')) {
                aidunite_team_management_set_flash_notice('success', $msg);
            }
        }
        $redirect = function_exists('aidunite_team_management_get_redirect_url_after_action')
            ? aidunite_team_management_get_redirect_url_after_action()
            : get_permalink();
        wp_safe_redirect($redirect);
        exit;
    }
}

// チーム一括削除処理
if (isset($_POST['bulk_delete_teams'])) {
    $nonce_result = AidUniteAuthMiddleware::verify_nonce('_wpnonce', 'bulk_delete_teams');
    if (is_wp_error($nonce_result)) {
        wp_die($nonce_result->get_error_message(), 'エラー', ['response' => 403]);
    }

    $team_ids = isset($_POST['team_ids']) ? array_map('intval', (array) $_POST['team_ids']) : [];
    $team_ids = array_values(array_unique(array_filter($team_ids)));
    $deleted_count = 0;
    $failed = [];
    $total_members = 0;
    $total_related = 0;

    if (function_exists('aidunite_admin_delete_team')) {
        foreach ($team_ids as $tid) {
            $result = aidunite_admin_delete_team($tid);
            if (is_wp_error($result)) {
                $failed[] = $tid . ': ' . $result->get_error_message();
                continue;
            }
            $deleted_count++;
            $total_members += (int) $result['members'];
            $total_related += (int) $result['related_posts'];
        }
    }

    if ($deleted_count > 0 && empty($failed)) {
        $msg = (int) $deleted_count . ' 件のチームを削除しました。'
            . '（メンバー ' . (int) $total_members . ' 名の所属を解除、関連投稿 ' . (int) $total_related . ' 件を削除）';
        if (function_exists('aidunite_team_management_set_flash_notice')) {
            aidunite_team_management_set_flash_notice('success', $msg);
        }
    } elseif ($deleted_count > 0) {
        $msg = (int) $deleted_count . ' 件を削除しましたが、一部失敗しました: ' . implode(' / ', $failed);
        if (function_exists('aidunite_team_management_set_flash_notice')) {
            aidunite_team_management_set_flash_notice('warning', $msg);
        }
    } elseif (!empty($failed)) {
        if (function_exists('aidunite_team_management_set_flash_notice')) {
            aidunite_team_management_set_flash_notice('error', '削除できませんでした: ' . implode(' / ', $failed));
        }
    } else {
        if (function_exists('aidunite_team_management_set_flash_notice')) {
            aidunite_team_management_set_flash_notice('warning', '削除するチームが選択されていません。カードにチェックを入れてから一括削除してください。');
        }
    }

    $redirect = function_exists('aidunite_team_management_get_redirect_url_after_action')
        ? aidunite_team_management_get_redirect_url_after_action()
        : get_permalink();
    wp_safe_redirect($redirect);
    exit;
}

// メンバー一括移動処理
if (isset($_POST['move_members'], $_POST['source_team_id'], $_POST['target_team_id'])) {
    // CSRF対策（統一版）
    $nonce_result = AidUniteAuthMiddleware::verify_nonce('_wpnonce', 'move_members');
    if (is_wp_error($nonce_result)) {
        wp_die($nonce_result->get_error_message(), 'エラー', ['response' => 403]);
    }
    $source_team_id = intval($_POST['source_team_id']);
    $target_team_id = intval($_POST['target_team_id']);

    if ($source_team_id && $target_team_id && $source_team_id !== $target_team_id) {
        $member_ids = function_exists('aidunite_get_team_affiliated_user_ids')
            ? aidunite_get_team_affiliated_user_ids($source_team_id)
            : array_map('intval', (array) get_users([
                'meta_key' => 'team_id',
                'meta_value' => $source_team_id,
                'fields' => 'ID',
                'number' => -1,
            ]));
        $success_count = 0;

        foreach ($member_ids as $member_id) {
            // 代表者は team_id が primary の別チームのことがあるため、当該 team_id のユーザーのみ移動
            if ((int) get_user_meta($member_id, 'team_id', true) !== $source_team_id) {
                continue;
            }
            update_user_meta($member_id, 'team_id', $target_team_id);
            $success_count++;
        }

        echo '<div class="notice notice-success">' . $success_count . '名のメンバーをチームID ' . $source_team_id . ' から ' . $target_team_id . ' に移動しました。</div>';
    }
}

// テスト用チーム一括削除（PHPUnit / E2E 残骸）
if (isset($_POST['delete_test_fixture_teams'])) {
    $nonce_result = AidUniteAuthMiddleware::verify_nonce('_wpnonce', 'delete_test_fixture_teams');
    if (is_wp_error($nonce_result)) {
        wp_die($nonce_result->get_error_message(), 'エラー', ['response' => 403]);
    }

    if (function_exists('aidunite_delete_test_fixture_teams')) {
        $cleanup = aidunite_delete_test_fixture_teams();
        $deleted = (int) ($cleanup['deleted'] ?? 0);
        $failed = $cleanup['failed'] ?? [];

        $skipped = (int) ($cleanup['skipped_heuristic'] ?? 0);
        if ($deleted > 0 && empty($failed)) {
            $msg = 'テスト用マーク付きチーム ' . $deleted . ' 件を削除しました。';
            if ($skipped > 0) {
                $msg .= '（名前のみテスト疑いの ' . $skipped . ' 件は削除していません）';
            }
            if (function_exists('aidunite_team_management_set_flash_notice')) {
                aidunite_team_management_set_flash_notice('success', $msg);
            }
        } elseif ($deleted > 0) {
            $msg = 'テスト用マーク付きチーム ' . $deleted . ' 件を削除しましたが、一部失敗しました: ' . implode(' / ', $failed);
            if (function_exists('aidunite_team_management_set_flash_notice')) {
                aidunite_team_management_set_flash_notice('warning', $msg);
            }
        } elseif (!empty($failed)) {
            if (function_exists('aidunite_team_management_set_flash_notice')) {
                aidunite_team_management_set_flash_notice('error', '削除できませんでした: ' . implode(' / ', $failed));
            }
        } else {
            if (function_exists('aidunite_team_management_set_flash_notice')) {
                aidunite_team_management_set_flash_notice('success', '削除対象のテスト用チームはありませんでした。');
            }
        }
    }

    $redirect = function_exists('aidunite_team_management_get_redirect_url_after_action')
        ? aidunite_team_management_get_redirect_url_after_action()
        : get_permalink();
    wp_safe_redirect($redirect);
    exit;
}

// チームステータス変更処理
if (isset($_POST['update_team_status'], $_POST['team_id'], $_POST['team_status_new'])) {
    $nonce_result = AidUniteAuthMiddleware::verify_nonce('_wpnonce', 'update_team_status');
    if (is_wp_error($nonce_result)) {
        wp_die($nonce_result->get_error_message(), 'エラー', ['response' => 403]);
    }
    $team_id = intval($_POST['team_id']);
    $team_status_new = sanitize_text_field($_POST['team_status_new']);
    $allowed = ['active', 'pending', 'inactive'];
    if ($team_id && in_array($team_status_new, $allowed, true)) {
        $team_post = get_post($team_id);
        if ($team_post && $team_post->post_type === 'team') {
            if (function_exists('aidunite_team_write_status_meta')) {
                aidunite_team_write_status_meta($team_id, $team_status_new);
            } else {
                update_post_meta($team_id, 'team_status', $team_status_new);
            }
            $redirect = add_query_arg(['team_status_updated' => 1, 'team_id' => $team_id], wp_get_referer() ?: home_url('/team-management'));
            wp_safe_redirect($redirect);
            exit;
        } else {
            echo '<div class="notice notice-error">指定されたチームが見つかりません。</div>';
        }
    } else {
        echo '<div class="notice notice-error">不正なリクエストです。</div>';
    }
}

// 検索・フィルター
$team_filters = function_exists('aidunite_team_management_get_filters_from_request')
    ? aidunite_team_management_get_filters_from_request()
    : [
        'team_filter' => isset($_GET['team_filter']) ? sanitize_text_field(wp_unslash($_GET['team_filter'])) : '',
        'status_filter' => isset($_GET['status_filter']) ? sanitize_text_field(wp_unslash($_GET['status_filter'])) : '',
        'type_filter' => '',
        'sport_filter' => '',
        'category_filter' => '',
        'gender_filter' => '',
        'region_filter' => '',
    ];
$team_name_filter_options = function_exists('aidunite_team_management_get_team_name_filter_options')
    ? aidunite_team_management_get_team_name_filter_options()
    : ['' => 'すべて'];
$filter_option_groups = function_exists('aidunite_team_management_get_filter_option_groups')
    ? aidunite_team_management_get_filter_option_groups()
    : [];
$has_active_filters = function_exists('aidunite_team_management_has_active_filters')
    ? aidunite_team_management_has_active_filters($team_filters)
    : (($team_filters['team_filter'] ?? '') !== '' || ($team_filters['status_filter'] ?? '') !== '');
$active_filter_labels = function_exists('aidunite_team_management_get_active_filter_labels')
    ? aidunite_team_management_get_active_filter_labels($team_filters)
    : [];

// チーム取得（管理者は公開済み＋投稿承認待ちを一覧。新しい順で先頭ページに表示）
$team_args = [
    'post_type' => 'team',
    'post_status' => ['publish', 'pending', 'draft'],
    'numberposts' => -1,
    'orderby' => 'date',
    'order' => 'DESC',
];

$teams = get_posts($team_args);

// フィルター適用
if ($has_active_filters) {
    $filtered_teams = [];
    foreach ($teams as $team) {
        $matches = function_exists('aidunite_team_management_team_matches_filters')
            ? aidunite_team_management_team_matches_filters($team->ID, $team_filters)
            : true;
        if ($matches) {
            $filtered_teams[] = $team;
        }
    }
    $teams = $filtered_teams;
}

// 代表者（世帯）ごとに並べ替え → 同一代表のチームが隣接し、グループ見出しで判別しやすい
if (function_exists('aidunite_team_management_sort_teams_by_leader')) {
    $teams = aidunite_team_management_sort_teams_by_leader($teams);
}
$teams_by_leader_counts = function_exists('aidunite_team_management_count_teams_by_leader')
    ? aidunite_team_management_count_teams_by_leader($teams)
    : [];

// チーム名で絞り込み時、下書き等が一覧クエリに含まれない場合は直接取得
if (($team_filters['team_filter'] ?? '') !== '' && empty($teams)) {
    $forced_team = get_post((int) $team_filters['team_filter']);
    if ($forced_team && $forced_team->post_type === 'team' && $forced_team->post_status !== 'trash') {
        if (function_exists('aidunite_team_management_team_matches_filters')
            && aidunite_team_management_team_matches_filters($forced_team->ID, $team_filters)) {
            $teams = [$forced_team];
        }
    }
}

// ページネーション（1ページ 12 件＝横3×縦4）
// 固定ページでは WP が ?paged= を canonical リダイレクトで落とすため、専用クエリ変数を使う
$team_list_page_var = 'tml_paged';
$teams_per_page = defined('AIDUNITE_TEAM_MANAGEMENT_PER_PAGE') ? (int) AIDUNITE_TEAM_MANAGEMENT_PER_PAGE : 12;
$teams_per_page = max(1, min(100, $teams_per_page));
$total_teams = count($teams);
$total_pages = $total_teams > 0 ? (int) ceil($total_teams / $teams_per_page) : 1;
$requested_page = 0;
if (isset($_GET[$team_list_page_var])) {
    $requested_page = (int) $_GET[$team_list_page_var];
} elseif (isset($_GET['paged'])) {
    $requested_page = (int) $_GET['paged'];
}
$current_page = $requested_page > 0 ? max(1, min($requested_page, $total_pages)) : 1;
$offset = ($current_page - 1) * $teams_per_page;
$teams_page = array_slice($teams, $offset, $teams_per_page);

// 削除後などでページ番号だけが残り一覧が空になるのを防ぐ
if ($total_teams > 0 && $teams_page === [] && $current_page > 1) {
    $redirect_first = function_exists('aidunite_team_management_get_redirect_url_after_action')
        ? aidunite_team_management_get_redirect_url_after_action(
            function_exists('aidunite_team_management_filters_to_query_args')
                ? aidunite_team_management_filters_to_query_args($team_filters)
                : []
        )
        : remove_query_arg([$team_list_page_var, 'paged'], get_permalink());
    wp_safe_redirect($redirect_first);
    exit;
}

$teams_page_leader_groups = function_exists('aidunite_team_management_group_posts_by_leader')
    ? aidunite_team_management_group_posts_by_leader($teams_page)
    : [];
if ($teams_page !== [] && $teams_page_leader_groups === []) {
    $teams_page_leader_groups = [
        [
            'leader_id' => 0,
            'leader_key' => 0,
            'teams' => $teams_page,
        ],
    ];
}

$team_management_filter_args = function_exists('aidunite_team_management_filters_to_query_args')
    ? aidunite_team_management_filters_to_query_args($team_filters)
    : array_filter([
        'team_filter' => $team_filters['team_filter'] ?? null,
        'status_filter' => $team_filters['status_filter'] ?? null,
    ]);
$team_management_pagination_base = add_query_arg($team_management_filter_args, get_permalink());
$team_management_reset_url = remove_query_arg(
    array_merge(array_keys($filter_option_groups), ['team_filter', 'search', $team_list_page_var, 'paged']),
    get_permalink()
);

get_header();

$team_mgmt_shell_opened = false;
if (function_exists('aidunite_web_app_page_shell_open')) {
    aidunite_web_app_page_shell_open([
        'page_class' => 'page-team-management team-management-page',
        'title' => 'チーム管理',
        'subtitle' => '管理者専用',
    ]);
    $team_mgmt_shell_opened = true;
} else {
    echo '<div class="wrap team-management-page">';
}
?>

<?php if (!$team_mgmt_shell_opened) : ?>
    <h1>チーム管理（管理者専用）</h1>
<?php endif; ?>
    <?php
    if (isset($_GET['team_status_updated'], $_GET['team_id'])) {
        $tid = (int) $_GET['team_id'];
        $t = $tid ? get_post($tid) : null;
        if ($t && $t->post_type === 'team') {
            $new_status = get_post_meta($tid, 'team_status', true);
            $new_status_label = function_exists('aidunite_team_management_get_team_status_label')
                ? aidunite_team_management_get_team_status_label($new_status)
                : $new_status;
            echo '<div class="notice notice-success" style="margin:var(--spacing-base) 0;">チーム「' . esc_html($t->post_title) . '」のステータスを「' . esc_html($new_status_label) . '」に変更しました。</div>';
        }
    }
    $tml_flash = function_exists('aidunite_team_management_consume_flash_notice')
        ? aidunite_team_management_consume_flash_notice()
        : null;
    if ($tml_flash && !empty($tml_flash['message'])) {
        $tml_class = 'notice-' . ($tml_flash['type'] === 'success' ? 'success' : ($tml_flash['type'] === 'warning' ? 'warning' : 'error'));
        echo '<div class="notice ' . esc_attr($tml_class) . '" style="margin:var(--spacing-base) 0;">' . esc_html($tml_flash['message']) . '</div>';
    }
    if (function_exists('aidunite_team_management_render_test_fixture_notice')) {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 関数内で esc_html 済み
        echo aidunite_team_management_render_test_fixture_notice();
    }
    ?>
    <!-- フィルター（スケジュール一覧 admin-schedule-list と同レイアウト） -->
    <div class="search-filter-section admin-schedule-filters team-management-filters" style="background:var(--bg-secondary); padding:var(--spacing-lg); margin:var(--spacing-lg) 0; border-radius:var(--radius-base);">
        <h3>🔍 フィルター</h3>
        <form method="get" class="admin-schedule-filters__form team-management-filters__form">
            <div class="admin-schedule-filters__field team-management-filters__field">
                <label for="team_filter">チーム名</label>
                <select id="team_filter" name="team_filter" class="admin-schedule-filters__input team-management-filters__input">
                    <?php foreach ($team_name_filter_options as $option_value => $option_label) : ?>
                    <option value="<?php echo esc_attr($option_value); ?>" <?php selected($team_filters['team_filter'] ?? '', $option_value); ?>><?php echo esc_html($option_label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php foreach ($filter_option_groups as $filter_key => $filter_group) : ?>
            <div class="admin-schedule-filters__field team-management-filters__field">
                <label for="<?php echo esc_attr($filter_key); ?>"><?php echo esc_html($filter_group['label']); ?></label>
                <select id="<?php echo esc_attr($filter_key); ?>" name="<?php echo esc_attr($filter_key); ?>" class="admin-schedule-filters__input team-management-filters__input">
                    <?php foreach ($filter_group['options'] as $option_value => $option_label) : ?>
                    <option value="<?php echo esc_attr($option_value); ?>" <?php selected($team_filters[$filter_key] ?? '', $option_value); ?>><?php echo esc_html($option_label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endforeach; ?>
            <div class="admin-schedule-filters__actions team-management-filters__actions">
                <button type="submit" class="button button-primary">絞り込む</button>
                <a href="<?php echo esc_url($team_management_reset_url); ?>" class="button">リセット</a>
            </div>
        </form>

        <?php if ($has_active_filters) : ?>
        <div style="margin-top:var(--spacing-base); padding:var(--spacing-sm); background:rgba(23, 162, 184, 0.1); border-radius:var(--radius-small);">
            <strong>絞り込み結果:</strong> <?php echo (int) count($teams); ?>件
            <?php foreach ($active_filter_labels as $label_name => $label_value) : ?>
                | <?php echo esc_html($label_name); ?>: <?php echo esc_html($label_value); ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div style="margin-top:var(--spacing-base); display:flex; flex-wrap:wrap; align-items:center; gap:var(--spacing-base);">
            <p style="margin:0;">
                全 <?php echo (int) $total_teams; ?> 件
                <?php if ($total_pages > 1) : ?>
                    （<?php echo (int) ($offset + 1); ?> - <?php echo (int) min($offset + $teams_per_page, $total_teams); ?> 件目）
                <?php endif; ?>
            </p>
            <a href="<?php echo esc_url(home_url('/admin-user-list')); ?>" class="button">ユーザー一覧へ</a>
            <a href="<?php echo esc_url(home_url('/admin-schedule-list')); ?>" class="button button-secondary">スケジュール一覧へ</a>
        </div>
    </div>

    <?php if ($total_pages > 1) : ?>
    <nav style="margin-bottom:var(--spacing-base);" aria-label="チーム一覧のページ送り">
        <?php
        echo paginate_links([
            'base' => $team_management_pagination_base . '%_%',
            'format' => '?' . $team_list_page_var . '=%#%',
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

    <!-- メンバー移動機能 -->
    <div class="member-move-section" style="background:rgba(255, 193, 7, 0.1); padding:var(--spacing-base); margin:var(--spacing-lg) 0; border-radius:var(--radius-base); border:1px solid var(--warning-color);">
        <h3>🔄 メンバー一括移動</h3>
        <form method="post" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:15px; align-items:end;">
            <div>
                <label for="source_team_id">移動元チーム:</label>
                <select id="source_team_id" name="source_team_id" style="width:100%; padding:8px;" required>
                    <option value="">チームを選択</option>
                    <?php foreach ($teams as $team): ?>
                        <option value="<?php echo esc_attr($team->ID); ?>"><?php echo esc_html($team->post_title); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="target_team_id">移動先チーム:</label>
                <select id="target_team_id" name="target_team_id" style="width:100%; padding:8px;" required>
                    <option value="">チームを選択</option>
                    <?php foreach ($teams as $team): ?>
                        <option value="<?php echo esc_attr($team->ID); ?>"><?php echo esc_html($team->post_title); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <button type="submit" name="move_members" value="1" class="button button-warning" onclick="return confirm('選択されたチームの全メンバーを移動しますか？')">メンバー移動</button>
            </div>
            <?php wp_nonce_field('move_members'); ?>
        </form>
    </div>

    <!-- チーム一覧（現在ページ分のみ表示・新しい順） -->
    <?php if (empty($teams_page) && $total_teams > 0) : ?>
    <div class="notice notice-warning" style="margin:var(--spacing-lg) 0;">
        このページに表示できるチームがありません。
        <a href="<?php echo esc_url(remove_query_arg($team_list_page_var)); ?>">1ページ目（最新のチーム）へ</a>
    </div>
    <?php endif; ?>

    <?php if (!empty($teams_page)) : ?>
    <div class="team-bulk-actions-section" style="background:rgba(220, 53, 69, 0.08); padding:var(--spacing-base); margin:var(--spacing-lg) 0; border-radius:var(--radius-base); border:1px solid var(--danger-color);">
        <h3 style="margin:0 0 var(--spacing-sm) 0;">🗑️ チーム一括削除</h3>
        <p style="margin:0 0 var(--spacing-base) 0; font-size:var(--font-size-sm, 0.875rem); color:var(--text-secondary);">
            カードを選択して一括削除できます（表示中のページのみ）。メンバーの所属解除・関連スケジュール等も削除されます。
        </p>
        <form method="post" id="team-bulk-delete-form" class="team-bulk-delete-form">
            <div style="display:flex; flex-wrap:wrap; align-items:center; gap:var(--spacing-base);">
                <label class="aidunite-admin-checkbox aidunite-admin-checkbox--row">
                    <input type="checkbox" id="select-all-teams" class="aidunite-admin-checkbox__input" aria-label="このページのチームをすべて選択">
                    <span class="aidunite-admin-checkbox__box" aria-hidden="true"></span>
                    <span class="aidunite-admin-checkbox__label-text">このページのチームをすべて選択</span>
                </label>
                <span id="team-bulk-selected-count" style="color:var(--text-secondary); font-size:var(--font-size-sm, 0.875rem);" aria-live="polite">0件選択</span>
                <button type="submit" name="bulk_delete_teams" value="1" class="button button-danger" id="team-bulk-delete-btn" disabled onclick="return confirmTeamBulkDelete();">選択したチームを一括削除</button>
            </div>
            <?php wp_nonce_field('bulk_delete_teams'); ?>
        </form>
    </div>
    <?php endif; ?>

    <div class="teams-list-by-leader">
        <?php foreach ($teams_page_leader_groups as $leader_group) :
            $group_teams = $leader_group['teams'];
            if ($group_teams === []) {
                continue;
            }
            $leader_id = (int) $leader_group['leader_id'];
            $leader_group_key = (int) $leader_group['leader_key'];
            $group_count_on_page = count($group_teams);
            $leader_total = (int) ($teams_by_leader_counts[$leader_group_key] ?? $group_count_on_page);
            $first_team_id = (int) $group_teams[0]->ID;
            $leader_group_label = function_exists('aidunite_team_management_get_leader_display_label')
                ? aidunite_team_management_get_leader_display_label($leader_id, $first_team_id)
                : ($leader_id > 0 ? ('ユーザー #' . $leader_id) : '代表者未設定');
            $leader_group_user_url = $leader_id > 0
                ? add_query_arg(['user_id' => $leader_id], home_url('/admin-user-list'))
                : '';
            $show_group_banner = $group_count_on_page >= 2;
            $group_team_ids_on_page = array_map(static function ($t) {
                return (int) $t->ID;
            }, $group_teams);
            $group_team_ids_attr = implode(',', $group_team_ids_on_page);
            $primary_tid_for_leader = ($leader_id > 0 && function_exists('aidunite_team_management_resolve_leader_primary_team_id'))
                ? aidunite_team_management_resolve_leader_primary_team_id($leader_id)
                : 0;
            ?>
        <article class="team-leader-block" data-leader-group="<?php echo esc_attr((string) $leader_group_key); ?>">
            <?php if ($show_group_banner) : ?>
            <section class="team-management-leader-group" role="group" aria-label="<?php echo esc_attr('代表者グループ: ' . $leader_group_label); ?>">
                <div class="team-management-leader-group__inner">
                    <label class="aidunite-admin-checkbox team-management-leader-group__select" title="このページに表示中のチームを選択">
                        <input type="checkbox" class="team-bulk-group-checkbox aidunite-admin-checkbox__input" data-leader-group="<?php echo esc_attr((string) $leader_group_key); ?>" data-team-ids="<?php echo esc_attr($group_team_ids_attr); ?>" aria-label="<?php echo esc_attr($leader_group_label . ' の表示中 ' . $group_count_on_page . ' チームを選択'); ?>">
                        <span class="aidunite-admin-checkbox__box" aria-hidden="true"></span>
                    </label>
                    <div class="team-management-leader-group__heading">
                        <h2 class="team-management-leader-group__title">
                            <?php if ($leader_group_user_url !== '') : ?>
                            <a href="<?php echo esc_url($leader_group_user_url); ?>" class="team-management-leader-group__link"><?php echo esc_html($leader_group_label); ?></a>
                            <?php else : ?>
                            <?php echo esc_html($leader_group_label); ?>
                            <?php endif; ?>
                        </h2>
                        <p class="team-management-leader-group__meta">
                            <?php if ($leader_id > 0) : ?>
                            <span class="team-management-leader-group__badge">代表者ID <?php echo (int) $leader_id; ?></span>
                            <?php endif; ?>
                            <span class="team-management-leader-group__badge team-management-leader-group__badge--multi">⇆ マルチ <?php echo (int) $leader_total; ?>チーム</span>
                            <?php if ($leader_total > $group_count_on_page) : ?>
                            <span class="team-management-leader-group__badge">このページ <?php echo (int) $group_count_on_page; ?>件</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </section>
            <?php endif; ?>
            <div class="team-leader-block__cards">
            <?php foreach ($group_teams as $team) :
            $team_id = (int) $team->ID;
            $team_name = $team->post_title;
            $is_primary_team_card = $primary_tid_for_leader > 0 && $primary_tid_for_leader === $team_id;
            $display_labels = function_exists('aidunite_team_management_get_display_labels')
                ? aidunite_team_management_get_display_labels($team_id)
                : [];
            $team_status = $display_labels['team_status'] ?? get_post_meta($team_id, 'team_status', true);
            if ($team_status === '') {
                $team_status = 'active';
            }
            $team_status_label = $display_labels['team_status_label'] ?? $team_status;
            $post_status_label = $display_labels['post_status_label'] ?? '';

            // メンバー統計（team_id のみでは内訳と総数がずれることがあるため一括集計）
            $member_counts = function_exists('aidunite_team_management_count_members')
                ? aidunite_team_management_count_members($team_id)
                : ['total' => 0, 'team_leader' => 0, 'player' => 0, 'parent' => 0, 'other' => 0, 'other_roles' => []];

            $schedule_count = function_exists('aidunite_team_management_count_schedules')
                ? aidunite_team_management_count_schedules($team_id)
                : 0;
        ?>
        <div class="team-card" data-team-id="<?php echo esc_attr($team_id); ?>" data-leader-group="<?php echo esc_attr((string) $leader_group_key); ?>" style="background:var(--bg-primary); border:1px solid var(--border-color); border-radius:var(--radius-base); padding:var(--spacing-lg); box-shadow:var(--shadow-sm);">
            <?php if (!$show_group_banner) : ?>
            <div class="team-card__leader-strip" role="group" aria-label="<?php echo esc_attr('代表者: ' . $leader_group_label); ?>">
                <?php if ($leader_group_user_url !== '') : ?>
                <a href="<?php echo esc_url($leader_group_user_url); ?>" class="team-card__leader-strip-link"><?php echo esc_html($leader_group_label); ?></a>
                <?php else : ?>
                <span class="team-card__leader-strip-name"><?php echo esc_html($leader_group_label); ?></span>
                <?php endif; ?>
                <?php if ($leader_id > 0) : ?>
                <span class="team-card__leader-strip-id">ID <?php echo (int) $leader_id; ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <label class="aidunite-admin-checkbox team-card-select-label--corner">
                <input type="checkbox" value="<?php echo esc_attr($team_id); ?>" class="team-bulk-checkbox aidunite-admin-checkbox__input" data-team-id="<?php echo esc_attr($team_id); ?>" aria-label="<?php echo esc_attr('チーム「' . $team_name . '」を選択'); ?>">
                <span class="aidunite-admin-checkbox__box" aria-hidden="true"></span>
            </label>
            <div class="team-header" style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:var(--spacing-base); flex-wrap:wrap; gap:var(--spacing-sm); padding-right:48px;">
                <h3 style="margin:0; color:var(--text-primary); word-break:break-word; flex:1; min-width:0;">
                    <?php echo esc_html($team_name); ?>
                    <?php if ($is_primary_team_card) : ?>
                    <span class="team-management-card__primary-badge" title="代表者のメインチーム（primary_team_id）">メイン</span>
                    <?php endif; ?>
                </h3>
                <form method="post" class="team-status-form" style="display:flex; align-items:center; gap:var(--spacing-xs);">
                    <?php wp_nonce_field('update_team_status'); ?>
                    <input type="hidden" name="team_id" value="<?php echo esc_attr($team_id); ?>">
                    <label for="team_status_<?php echo $team_id; ?>" class="screen-reader-text">ステータス</label>
                    <?php
                    $bg = $team_status === 'active' ? 'rgba(40, 167, 69, 0.1)' : ($team_status === 'pending' ? 'rgba(255, 193, 7, 0.1)' : 'rgba(220, 53, 69, 0.1)');
                    $fg = $team_status === 'active' ? 'var(--success-color)' : ($team_status === 'pending' ? 'var(--warning-color)' : 'var(--danger-color)');
                    ?>
                    <select name="team_status_new" id="team_status_<?php echo $team_id; ?>" style="padding:var(--spacing-xs) var(--spacing-sm); border-radius:var(--radius-small); font-size:12px; font-weight:bold;
                        background:<?php echo $bg; ?>;
                        color:<?php echo $fg; ?>;
                        border:1px solid var(--border-color); min-height:32px;">
                        <option value="active" <?php selected($team_status, 'active'); ?>>有効</option>
                        <option value="pending" <?php selected($team_status, 'pending'); ?>>承認待ち</option>
                        <option value="inactive" <?php selected($team_status, 'inactive'); ?>>無効</option>
                    </select>
                    <button type="submit" name="update_team_status" value="1" class="button button-small" style="padding:2px 8px; font-size:12px;">変更</button>
                </form>
            </div>

            <div class="team-info" style="margin-bottom:15px;">
                <p style="margin:5px 0;"><strong>チームID:</strong> <span style="background:rgba(23, 162, 184, 0.1); padding:2px var(--spacing-xs); border-radius:var(--radius-small); font-weight:bold; font-family:monospace;"><?php echo esc_html($team_id); ?></span></p>
                <?php if ($post_status_label !== '' && $post_status_label !== '公開') : ?>
                <p style="margin:5px 0;"><strong>投稿状態:</strong> <?php echo esc_html($post_status_label); ?></p>
                <?php endif; ?>
                <p style="margin:5px 0;"><strong>チーム状態:</strong> <?php echo esc_html($team_status_label); ?></p>
                <p style="margin:5px 0;"><strong>チーム種別:</strong> <?php echo esc_html($display_labels['team_type'] ?? '未設定'); ?></p>
                <p style="margin:5px 0;"><strong>競技種目:</strong> <?php echo esc_html($display_labels['sport_type'] ?? '未設定'); ?></p>
                <p style="margin:5px 0;"><strong>年代カテゴリ:</strong> <?php echo esc_html($display_labels['team_category'] ?? '未設定'); ?></p>
                <p style="margin:5px 0;"><strong>性別:</strong> <?php echo esc_html($display_labels['gender_label'] ?? '未設定'); ?></p>
                <p style="margin:5px 0;"><strong>活動地域:</strong> <?php echo esc_html(($display_labels['region'] ?? '') !== '' && ($display_labels['region'] ?? '') !== '—' ? $display_labels['region'] : '未設定'); ?></p>
                <p style="margin:5px 0;"><strong>作成日:</strong> <?php echo esc_html($display_labels['created_date'] ?? '未設定'); ?></p>
            </div>

            <div class="team-stats" style="display:grid; grid-template-columns: repeat(2, 1fr); gap:10px; margin-bottom:15px;">
                <div style="text-align:center; padding:var(--spacing-sm); background:var(--bg-secondary); border-radius:var(--radius-small);">
                    <div style="font-size:24px; font-weight:bold; color:var(--primary-color);"><?php echo (int) $member_counts['total']; ?></div>
                    <div style="font-size:12px; color:var(--text-secondary);">総メンバー</div>
                </div>
                <div style="text-align:center; padding:var(--spacing-sm); background:var(--bg-secondary); border-radius:var(--radius-small);">
                    <div style="font-size:24px; font-weight:bold; color:var(--success-color);"><?php echo (int) $schedule_count; ?></div>
                    <div style="font-size:12px; color:var(--text-secondary);">スケジュール</div>
                </div>
            </div>

            <div class="member-breakdown" style="margin-bottom:15px;">
                <h4 style="margin:0 0 var(--spacing-sm) 0; font-size:14px; color:var(--text-secondary);">メンバー内訳</h4>
                <div style="display:flex; gap:var(--spacing-sm); font-size:12px;">
                    <span style="background:rgba(23, 162, 184, 0.1); padding:2px var(--spacing-xs); border-radius:var(--radius-small);">代表者: <?php echo (int) $member_counts['team_leader']; ?>名</span>
                    <span style="background:rgba(177, 108, 234, 0.1); padding:2px var(--spacing-xs); border-radius:var(--radius-small);">選手: <?php echo (int) $member_counts['player']; ?>名</span>
                    <span style="background:rgba(40, 167, 69, 0.1); padding:2px var(--spacing-xs); border-radius:var(--radius-small);">保護者: <?php echo (int) $member_counts['parent']; ?>名</span>
                    <?php if ((int) $member_counts['other'] > 0) : ?>
                    <?php
                    $other_role_hints = [];
                    foreach ($member_counts['other_roles'] as $role_label => $role_n) {
                        $other_role_hints[] = $role_label . '×' . (int) $role_n;
                    }
                    ?>
                    <span style="background:rgba(255, 193, 7, 0.15); padding:2px var(--spacing-xs); border-radius:var(--radius-small);" title="<?php echo esc_attr(implode(', ', $other_role_hints)); ?>">その他: <?php echo (int) $member_counts['other']; ?>名</span>
                    <?php endif; ?>
                </div>
                <?php if ((int) $member_counts['other'] > 0) : ?>
                <p style="margin:var(--spacing-xs) 0 0; font-size:11px; color:var(--text-secondary);">
                    「その他」は team_id のみ再設定され、代表者・選手・保護者以外のロール（general 等）のユーザーです。管理用ユーザー一覧でロールを修正してください。
                </p>
                <?php endif; ?>
            </div>

            <div class="team-actions" style="display:flex; gap:10px;">
                <?php
                $edit_url = function_exists('aidunite_get_team_settings_edit_url')
                    ? aidunite_get_team_settings_edit_url($team_id)
                    : add_query_arg('team_id', $team_id, home_url('/team-settings'));
                ?>
                <a href="<?php echo esc_url($edit_url); ?>" class="button button-primary" style="flex:1; text-align:center;">編集</a>
                <a href="<?php echo home_url('/team-members?team_id=' . $team_id); ?>" class="button button-secondary" style="flex:1; text-align:center;">メンバー</a>
                <form method="post" style="flex:1;" onsubmit="return confirm('チーム「<?php echo esc_js($team_name); ?>」を削除しますか？\nメンバー全員と関連データも削除されます。');">
                    <?php wp_nonce_field('delete_team_' . $team_id); ?>
                    <input type="hidden" name="delete_team_id" value="<?php echo esc_attr($team_id); ?>">
                    <button type="submit" class="button button-danger" style="width:100%;">削除</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
            </div>
        </article>
        <?php endforeach; ?>
    </div>

    <!-- 一覧下のページネーション -->
    <?php if ($total_teams > 0 && $total_pages > 1): ?>
    <div class="team-management-pagination-bottom" style="margin-top:var(--spacing-xl); display:flex; justify-content:center;">
        <?php
        echo paginate_links([
            'base' => $team_management_pagination_base . '%_%',
            'format' => '?' . $team_list_page_var . '=%#%',
            'current' => $current_page,
            'total' => $total_pages,
            'prev_text' => '&laquo; 前へ',
            'next_text' => '次へ &raquo;',
            'mid_size' => 2,
            'end_size' => 1,
        ]);
        ?>
    </div>
    <?php endif; ?>

    <?php if (empty($teams)): ?>
    <div style="text-align:center; padding:var(--spacing-xl); background:var(--bg-secondary); border-radius:var(--radius-base); margin:var(--spacing-lg) 0;">
        <h3>チームが見つかりません</h3>
        <p>検索条件を変更するか、新しいチームを作成してください。</p>
    </div>
    <?php endif; ?>

<?php
if ($team_mgmt_shell_opened && function_exists('aidunite_web_app_page_shell_close')) {
    aidunite_web_app_page_shell_close();
} else {
    echo '</div>';
}
?>

<script>
(function() {
    const selectAll = document.getElementById('select-all-teams');
    const bulkBtn = document.getElementById('team-bulk-delete-btn');
    const countEl = document.getElementById('team-bulk-selected-count');

    function parseTeamIds(raw) {
        if (!raw) {
            return [];
        }
        return String(raw).split(',').map(function(id) {
            return id.trim();
        }).filter(Boolean);
    }

    function getTeamCheckboxes() {
        return document.querySelectorAll('.team-bulk-checkbox');
    }

    function getGroupCheckboxes() {
        return document.querySelectorAll('.team-bulk-group-checkbox');
    }

    function getLeaderBlockFromNode(node) {
        return node ? node.closest('.team-leader-block') : null;
    }

    function getCheckboxesInLeaderSection(sectionOrGroupCb) {
        const block = getLeaderBlockFromNode(sectionOrGroupCb);
        return block ? Array.from(block.querySelectorAll('.team-bulk-checkbox')) : [];
    }

    function getSelectedTeamIdSet() {
        const ids = new Set();
        getGroupCheckboxes().forEach(function(groupCb) {
            if (!groupCb.checked) {
                return;
            }
            parseTeamIds(groupCb.getAttribute('data-team-ids')).forEach(function(id) {
                ids.add(id);
            });
        });
        getTeamCheckboxes().forEach(function(box) {
            if (!box.checked) {
                return;
            }
            const block = getLeaderBlockFromNode(box);
            const groupCb = block ? block.querySelector('.team-bulk-group-checkbox') : null;
            if (groupCb && groupCb.checked) {
                return;
            }
            ids.add(box.value || box.getAttribute('data-team-id'));
        });
        return ids;
    }

    function syncLeaderGroupCheckboxState(groupCb) {
        const section = groupCb.closest('.team-management-leader-group');
        const allIds = parseTeamIds(groupCb.getAttribute('data-team-ids'));
        const visible = getCheckboxesInLeaderSection(section);
        let checkedVisible = 0;
        visible.forEach(function(box) {
            if (box.checked) {
                checkedVisible++;
            }
        });
        const total = allIds.length;
        const selectedViaGroup = groupCb.checked;
        if (selectedViaGroup) {
            groupCb.checked = true;
            groupCb.indeterminate = false;
        } else if (checkedVisible === 0) {
            groupCb.checked = false;
            groupCb.indeterminate = false;
        } else if (checkedVisible === visible.length && visible.length === total) {
            groupCb.checked = true;
            groupCb.indeterminate = false;
        } else {
            groupCb.checked = false;
            groupCb.indeterminate = checkedVisible > 0;
        }
        if (section) {
            section.classList.toggle('is-group-selected', groupCb.checked || groupCb.indeterminate);
        }
    }

    function updateTeamBulkUi() {
        const boxes = getTeamCheckboxes();
        const selectedIds = getSelectedTeamIdSet();
        if (countEl) {
            countEl.textContent = selectedIds.size + '件選択';
        }
        if (bulkBtn) {
            bulkBtn.disabled = selectedIds.size === 0;
        }
        if (selectAll && boxes.length > 0) {
            const visibleChecked = document.querySelectorAll('.team-bulk-checkbox:checked').length;
            const anyGroupChecked = Array.from(getGroupCheckboxes()).some(function(gcb) {
                return gcb.checked;
            });
            selectAll.checked = !anyGroupChecked && visibleChecked === boxes.length;
            selectAll.indeterminate = !selectAll.checked && (visibleChecked > 0 || selectedIds.size > visibleChecked);
        }
        boxes.forEach(function(box) {
            const card = box.closest('.team-card');
            if (card) {
                const id = box.value || box.getAttribute('data-team-id');
                card.classList.toggle('is-selected', selectedIds.has(id));
            }
        });
        getGroupCheckboxes().forEach(syncLeaderGroupCheckboxState);
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            getGroupCheckboxes().forEach(function(groupCb) {
                groupCb.checked = false;
                groupCb.indeterminate = false;
            });
            getTeamCheckboxes().forEach(function(box) {
                box.checked = selectAll.checked;
            });
            updateTeamBulkUi();
        });
    }

    getGroupCheckboxes().forEach(function(groupCb) {
        groupCb.addEventListener('change', function() {
            const section = groupCb.closest('.team-management-leader-group');
            const checked = groupCb.checked;
            getCheckboxesInLeaderSection(section).forEach(function(box) {
                box.checked = checked;
            });
            updateTeamBulkUi();
        });
    });

    getTeamCheckboxes().forEach(function(box) {
        box.addEventListener('change', function() {
            const block = getLeaderBlockFromNode(box);
            const groupCb = block ? block.querySelector('.team-bulk-group-checkbox') : null;
            if (groupCb && !box.checked) {
                groupCb.checked = false;
            }
            updateTeamBulkUi();
        });
    });

    updateTeamBulkUi();

    window.confirmTeamBulkDelete = function() {
        const selectedIds = getSelectedTeamIdSet();
        if (selectedIds.size === 0) {
            alert('削除するチームを選択してください。');
            return false;
        }
        return confirm(
            '選択した ' + selectedIds.size + ' 件のチームを削除しますか？\n' +
            '各チームのメンバー所属・スケジュール・通知・試合ログも削除されます。\n' +
            'この操作は取り消せません。'
        );
    };

    const bulkForm = document.getElementById('team-bulk-delete-form');
    if (bulkForm) {
        bulkForm.addEventListener('submit', function(e) {
            bulkForm.querySelectorAll('input.js-team-bulk-hidden-id').forEach(function(el) {
                el.remove();
            });
            const selectedIds = getSelectedTeamIdSet();
            if (selectedIds.size === 0) {
                e.preventDefault();
                alert('削除するチームを選択してください。');
                return;
            }
            selectedIds.forEach(function(id) {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'team_ids[]';
                hidden.value = id;
                hidden.className = 'js-team-bulk-hidden-id';
                bulkForm.appendChild(hidden);
            });
        });
    }

    if (typeof window.loadingSpinnerManager !== 'undefined' && typeof window.loadingSpinnerManager.hideAllSpinners === 'function') {
        window.loadingSpinnerManager.hideAllSpinners();
    }
})();
</script>

<?php get_footer(); ?>
