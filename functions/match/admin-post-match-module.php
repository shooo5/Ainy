<?php
/**
 * 試合後モジュール — Ainy サイト内管理者 UI（WP 管理画面は使わない）
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/post-match-module.php';

/**
 * @return string
 */
function aidunite_admin_post_match_module_base_url() {
    return home_url('/admin-post-match-modules');
}

/**
 * POST 処理（保存・削除）
 */
function aidunite_admin_post_match_module_handle_post() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $action = isset($_POST['aidunite_pmm_action']) ? sanitize_key(wp_unslash($_POST['aidunite_pmm_action'])) : '';

    if ($action === 'save') {
        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'aidunite_admin_pmm_save')) {
            wp_die('不正なリクエストです。', 'エラー', ['response' => 403]);
        }

        $module_id = isset($_POST['module_id']) ? (int) $_POST['module_id'] : 0;
        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        if ($title === '') {
            $GLOBALS['aidunite_admin_pmm_notice'] = ['type' => 'error', 'message' => 'タイトルは必須です。'];
            return;
        }

        if ($module_id > 0) {
            $post = get_post($module_id);
            if (!$post || $post->post_type !== 'post_match_module') {
                $GLOBALS['aidunite_admin_pmm_notice'] = ['type' => 'error', 'message' => 'モジュールが見つかりません。'];
                return;
            }
            wp_update_post([
                'ID'         => $module_id,
                'post_title' => $title,
            ]);
        } else {
            $module_id = wp_insert_post([
                'post_type'   => 'post_match_module',
                'post_status' => 'publish',
                'post_title'  => $title,
            ]);
            if (is_wp_error($module_id) || !$module_id) {
                $GLOBALS['aidunite_admin_pmm_notice'] = ['type' => 'error', 'message' => '作成に失敗しました。'];
                return;
            }
        }

        $no_period = !empty($_POST['no_period']);
        $data = [
            'module_type'     => isset($_POST['module_type']) ? wp_unslash($_POST['module_type']) : 'notice',
            'slot_key'        => isset($_POST['slot_key']) ? wp_unslash($_POST['slot_key']) : 'after_match_feedback',
            'is_active'       => !empty($_POST['is_active']),
            'priority'        => isset($_POST['priority']) ? (int) $_POST['priority'] : 10,
            'start_at'        => $no_period ? '' : (isset($_POST['start_at']) ? wp_unslash($_POST['start_at']) : ''),
            'end_at'          => $no_period ? '' : (isset($_POST['end_at']) ? wp_unslash($_POST['end_at']) : ''),
            'description'     => isset($_POST['description']) ? wp_unslash($_POST['description']) : '',
            'banner_image_id'     => isset($_POST['banner_image_id']) ? (int) $_POST['banner_image_id'] : 0,
            'banner_external_url' => isset($_POST['banner_external_url']) ? wp_unslash($_POST['banner_external_url']) : '',
            'cta_label'       => isset($_POST['cta_label']) ? wp_unslash($_POST['cta_label']) : '',
            'cta_url'         => isset($_POST['cta_url']) ? wp_unslash($_POST['cta_url']) : '',
            'target_sport'    => isset($_POST['target_sport']) ? wp_unslash($_POST['target_sport']) : '',
            'target_region'   => isset($_POST['target_region']) ? wp_unslash($_POST['target_region']) : '',
        ];
        aidunite_post_match_module_apply_meta($module_id, $data);

        wp_safe_redirect(add_query_arg('updated', '1', aidunite_admin_post_match_module_base_url()));
        exit;
    }

    if ($action === 'delete') {
        $module_id = isset($_POST['module_id']) ? (int) $_POST['module_id'] : 0;
        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'aidunite_admin_pmm_delete_' . $module_id)) {
            wp_die('不正なリクエストです。', 'エラー', ['response' => 403]);
        }

        $post = get_post($module_id);
        if ($post && $post->post_type === 'post_match_module') {
            wp_trash_post($module_id);
            $GLOBALS['aidunite_admin_pmm_notice'] = ['type' => 'success', 'message' => 'モジュールを削除しました。'];
        } else {
            $GLOBALS['aidunite_admin_pmm_notice'] = ['type' => 'error', 'message' => 'モジュールが見つかりません。'];
        }
    }
}

/**
 * @return array<int, array{impression:int, cta_click:int}>
 */
function aidunite_admin_post_match_module_log_counts_by_module() {
    global $wpdb;
    $table = aidunite_post_match_module_log_table_name();
    $rows = $wpdb->get_results(
        "SELECT module_id, action, COUNT(*) AS cnt FROM {$table} GROUP BY module_id, action",
        ARRAY_A
    );
    $out = [];
    if (!is_array($rows)) {
        return $out;
    }
    foreach ($rows as $row) {
        $mid = (int) ($row['module_id'] ?? 0);
        $action = (string) ($row['action'] ?? '');
        if ($mid <= 0) {
            continue;
        }
        if (!isset($out[$mid])) {
            $out[$mid] = ['impression' => 0, 'cta_click' => 0];
        }
        if ($action === 'impression') {
            $out[$mid]['impression'] = (int) $row['cnt'];
        } elseif ($action === 'cta_click') {
            $out[$mid]['cta_click'] = (int) $row['cnt'];
        }
    }
    return $out;
}

/**
 * 一覧・編集画面の描画
 */
function aidunite_admin_post_match_module_render_page() {
    $base_url = aidunite_admin_post_match_module_base_url();
    $action = isset($_GET['pmm_action']) ? sanitize_key($_GET['pmm_action']) : 'list';
    $module_id = isset($_GET['module_id']) ? (int) $_GET['module_id'] : 0;

    if (isset($_GET['updated']) && $_GET['updated'] === '1') {
        $GLOBALS['aidunite_admin_pmm_notice'] = ['type' => 'success', 'message' => 'モジュールを保存しました。'];
    }

    echo '<div class="admin-pmm-wrap team-dashboard-container">';
    echo '<header class="admin-pmm-header">';
    echo '<h1 class="admin-pmm-title">試合後アンケートの表示設定</h1>';
    echo '<p class="admin-pmm-lead">試合後振り返り画面（<code>/match-feedback</code>）下部に差し込む大会・お知らせ・スポンサー情報を、期間と表示条件付きで管理します。一般ユーザーにはこのページは表示されません。</p>';
    echo '<p class="admin-pmm-lead"><a href="' . esc_url(home_url('/ainy-dashboard')) . '" class="btn btn-secondary">← 管理者ダッシュボード</a></p>';
    echo '</header>';

    if (!empty($GLOBALS['aidunite_admin_pmm_notice'])) {
        $n = $GLOBALS['aidunite_admin_pmm_notice'];
        $class = 'admin-pmm-notice--' . esc_attr($n['type']);
        echo '<div class="admin-pmm-notice ' . $class . '" role="status">' . esc_html($n['message']) . '</div>';
        unset($GLOBALS['aidunite_admin_pmm_notice']);
    }

    if ($action === 'new' || ($action === 'edit' && $module_id > 0)) {
        aidunite_admin_post_match_module_render_form($base_url, $action, $module_id);
    } else {
        aidunite_admin_post_match_module_render_list($base_url);
        aidunite_admin_post_match_module_render_preview_modal();
    }

    echo '</div>';
}

/**
 * 一覧用プレビューモーダル
 */
function aidunite_admin_post_match_module_render_preview_modal() {
    echo '<div id="admin-pmm-modal" class="admin-pmm-modal" hidden aria-hidden="true">';
    echo '<div class="admin-pmm-modal__backdrop" data-admin-pmm-modal-close tabindex="-1"></div>';
    echo '<div class="admin-pmm-modal__panel" role="dialog" aria-modal="true" aria-labelledby="admin-pmm-modal-title">';
    echo '<header class="admin-pmm-modal__header">';
    echo '<h2 id="admin-pmm-modal-title" class="admin-pmm-modal__title">プレビュー</h2>';
    echo '<button type="button" class="admin-pmm-modal__close" data-admin-pmm-modal-close aria-label="閉じる">×</button>';
    echo '</header>';
    echo '<div id="admin-pmm-modal-body" class="admin-pmm-modal__body" data-testid="post-match-module-preview-modal-body">';
    echo '<p class="admin-pmm-preview-loading">読み込み中…</p>';
    echo '</div></div></div>';
}

/**
 * 同一スロットの他モジュール（プレビュー用）
 *
 * @param string $slot_key
 * @param int    $exclude_id
 * @return array<int, array<string, mixed>>
 */
function aidunite_post_match_modules_for_preview_slot($slot_key, $exclude_id = 0) {
    $context = aidunite_build_post_match_module_context(get_current_user_id(), 0, 0);
    $modules = aidunite_get_post_match_modules_for_slot($slot_key, $context);
    $out = [];
    foreach ($modules as $row) {
        if ((int) ($row['id'] ?? 0) === (int) $exclude_id) {
            continue;
        }
        $out[] = $row;
    }
    return $out;
}

/**
 * @param array<string, mixed> $body
 * @return array<string, mixed>
 */
function aidunite_build_match_feedback_flow_preview_args(array $body) {
    $module = aidunite_post_match_module_build_preview_data($body);
    $schedule_body = aidunite_post_match_module_normalize_schedule_body($body);
    $schedule_note = aidunite_post_match_module_preview_schedule_note($schedule_body, $module);
    $slot_key = isset($body['slot_key']) ? sanitize_key($body['slot_key']) : 'after_match_feedback';
    $exclude_id = isset($body['module_id']) ? (int) $body['module_id'] : (int) ($module['id'] ?? 0);
    $is_active = true;
    if (array_key_exists('is_active', $body)) {
        $is_active = filter_var($body['is_active'], FILTER_VALIDATE_BOOLEAN);
    }
    $module_visible = aidunite_post_match_module_in_schedule([
        'is_active' => $is_active,
        'start_at'  => $schedule_body['start_at'] ?? '',
        'end_at'    => $schedule_body['end_at'] ?? '',
    ], (int) current_time('timestamp'));

    return [
        'current_module'   => $module,
        'current_visible'  => $module_visible,
        'current_note'     => $schedule_note,
        'other_modules'    => aidunite_post_match_modules_for_preview_slot($slot_key, $exclude_id),
        'slot_key'         => $slot_key,
        'slot_label'       => aidunite_post_match_module_slot_label($slot_key),
    ];
}

/**
 * @param int $module_id
 * @return array<string, mixed>|null
 */
function aidunite_build_match_feedback_flow_preview_args_from_id($module_id) {
    $module_id = (int) $module_id;
    $post = get_post($module_id);
    if (!$post || $post->post_type !== 'post_match_module') {
        return null;
    }
    $row = aidunite_post_match_module_get_meta($module_id);
    return aidunite_build_match_feedback_flow_preview_args([
        'module_id'       => $module_id,
        'title'           => $post->post_title,
        'module_type'     => $row['type'],
        'slot_key'        => $row['slot_key'],
        'is_active'       => $row['is_active'],
        'start_at'        => $row['start_at'],
        'end_at'          => $row['end_at'],
        'description'     => $row['description'],
        'banner_image_id'     => (int) $row['banner_image_id'],
        'banner_external_url' => (string) ($row['banner_external_url'] ?? ''),
        'cta_label'           => $row['cta_label'],
        'cta_url'             => $row['cta_url'],
    ]);
}

/**
 * マッチアンケート画面全体のプレビュー（①〜⑤）
 *
 * @param array<string, mixed> $args
 * @return string
 */
function aidunite_render_match_feedback_flow_preview(array $args) {
    $current = $args['current_module'] ?? [];
    $current_visible = !empty($args['current_visible']);
    $current_note = (string) ($args['current_note'] ?? '');
    $others = is_array($args['other_modules'] ?? null) ? $args['other_modules'] : [];
    $slot_label = (string) ($args['slot_label'] ?? '');

    $all_module_toggles = [];
    if ($current_visible || !empty($current['title']) || !empty($current['banner_url'])) {
        $all_module_toggles[] = [
            'id'      => 'editing-current',
            'title'   => $current['title'] !== '' ? $current['title'] : '（編集中・無題）',
            'checked' => true,
            'editing' => true,
        ];
    }
    foreach ($others as $other) {
        $all_module_toggles[] = [
            'id'      => (int) ($other['id'] ?? 0),
            'title'   => (string) ($other['title'] ?? ''),
            'checked' => true,
            'editing' => false,
        ];
    }

    ob_start();
    echo '<div class="match-feedback-flow-preview" data-testid="match-feedback-flow-preview">';

    echo '<p class="match-feedback-flow-preview__lead">試合後振り返り（<code>/match-feedback</code>）の表示順プレビュー。<strong>①〜③は本番と同じカードUI</strong>、④は運営情報モジュールです（会場の評価は表示しません）。</p>';

    echo '<div class="match-feedback-flow-preview__toggles" role="group" aria-label="ブロック表示切替">';
    echo '<span class="match-feedback-flow-preview__toggles-label">表示するブロック:</span>';
    foreach ([
        '1' => '1 試合評価',
        '2' => '2 お気に入り',
        '3' => '3 再戦希望',
        '4' => '4 運営情報',
    ] as $num => $label) {
        echo '<label class="match-feedback-flow-preview__toggle">';
        echo '<input type="checkbox" data-preview-section-toggle="' . esc_attr($num) . '" checked>';
        echo '<span>' . esc_html($label) . '</span>';
        echo '</label>';
    }
    echo '</div>';

    if ($all_module_toggles !== []) {
        echo '<div class="match-feedback-flow-preview__module-toggles" role="group" aria-label="運営モジュール表示切替">';
        echo '<span class="match-feedback-flow-preview__toggles-label">④ 内のモジュール:</span>';
        foreach ($all_module_toggles as $toggle) {
            $tid = (int) $toggle['id'];
            $label = $toggle['title'];
            if (!empty($toggle['editing'])) {
                $label .= '（編集中）';
            }
            echo '<label class="match-feedback-flow-preview__toggle">';
            echo '<input type="checkbox" data-module-card-toggle="' . esc_attr((string) $tid) . '"';
            echo $toggle['checked'] ? ' checked' : '';
            echo '>';
            echo '<span>' . esc_html($label) . '</span>';
            echo '</label>';
        }
        echo '</div>';
    }

    echo '<div class="match-feedback-flow-preview__screen">';

    // ① 試合評価（必須）
    echo '<section class="match-feedback-flow-preview__section" data-flow-section="1" data-testid="flow-preview-section-1">';
    echo '<header class="match-feedback-flow-preview__section-head">';
    echo '<span class="match-feedback-flow-preview__step">1</span>';
    echo '<h3 class="match-feedback-flow-preview__section-title">試合評価</h3>';
    echo '<span class="match-feedback-flow-preview__badge match-feedback-flow-preview__badge--required">必須</span>';
    echo '</header>';
    if (function_exists('aidunite_render_match_feedback_preview_section')) {
        echo aidunite_render_match_feedback_preview_section('evaluation', [
            'opponent_team_name' => '（相手チーム名）',
        ]);
    }
    echo '</section>';

    // ② お気に入り（任意）
    echo '<section class="match-feedback-flow-preview__section" data-flow-section="2" data-testid="flow-preview-section-2">';
    echo '<header class="match-feedback-flow-preview__section-head">';
    echo '<span class="match-feedback-flow-preview__step">2</span>';
    echo '<h3 class="match-feedback-flow-preview__section-title">お気に入り</h3>';
    echo '<span class="match-feedback-flow-preview__badge match-feedback-flow-preview__badge--optional">任意</span>';
    echo '</header>';
    if (function_exists('aidunite_render_match_feedback_preview_section')) {
        echo aidunite_render_match_feedback_preview_section('favorite', [
            'opponent_team_name' => '（相手チーム名）',
        ]);
    }
    echo '</section>';

    // ③ 再試合希望（必須）
    echo '<section class="match-feedback-flow-preview__section" data-flow-section="3" data-testid="flow-preview-section-3">';
    echo '<header class="match-feedback-flow-preview__section-head">';
    echo '<span class="match-feedback-flow-preview__step">3</span>';
    echo '<h3 class="match-feedback-flow-preview__section-title">再試合希望</h3>';
    echo '<span class="match-feedback-flow-preview__badge match-feedback-flow-preview__badge--required">必須</span>';
    echo '</header>';
    if (function_exists('aidunite_render_match_feedback_preview_section')) {
        echo aidunite_render_match_feedback_preview_section('rematch', []);
    }
    echo '</section>';

    echo '<section class="match-feedback-flow-preview__section match-feedback-flow-preview__section--modules" data-flow-section="4" data-testid="flow-preview-section-4">';
    echo '<header class="match-feedback-flow-preview__section-head">';
    echo '<span class="match-feedback-flow-preview__step">4</span>';
    echo '<h3 class="match-feedback-flow-preview__section-title">チーム活動を応援する情報</h3>';
    echo '<span class="match-feedback-flow-preview__badge match-feedback-flow-preview__badge--multi">0〜N</span>';
    echo '</header>';
    if ($slot_label !== '') {
        echo '<p class="match-feedback-flow-preview__slot-note">表示枠: ' . esc_html($slot_label) . '</p>';
    }
    if ($current_note !== '') {
        echo '<p class="match-feedback-flow-preview__module-note">' . esc_html($current_note) . '</p>';
    }

    echo '<div class="match-feedback-flow-preview__modules post-match-modules">';
    $has_cards = false;

    $current_id = (int) ($current['id'] ?? 0);
    $current_html = aidunite_render_post_match_module_card($current, ['preview' => true]);
    if ($current_html !== '') {
        $has_cards = true;
        $hidden_class = $current_visible ? '' : ' is-module-hidden';
        $editing_class = ' post-match-module--editing';
        echo '<div class="match-feedback-flow-preview__module-wrap' . esc_attr($hidden_class) . '" data-module-wrap-id="editing-current" data-module-wrap-editing="1">';
        echo str_replace(
            'class="post-match-module ',
            'class="post-match-module' . $editing_class . ' ',
            $current_html
        );
        if (!$current_visible) {
            echo '<p class="match-feedback-flow-preview__hidden-label">非表示設定（ユーザーには出ません）</p>';
        }
        echo '</div>';
    }

    foreach ($others as $other) {
        $oid = (int) ($other['id'] ?? 0);
        $card = aidunite_render_post_match_module_card($other, ['preview' => true]);
        if ($card === '') {
            continue;
        }
        $has_cards = true;
        echo '<div class="match-feedback-flow-preview__module-wrap" data-module-wrap-id="' . esc_attr((string) $oid) . '">';
        echo $card;
        echo '</div>';
    }

    if (!$has_cards) {
        echo '<p class="match-feedback-flow-preview__empty-modules">公開中のモジュールがありません（⑤ は非表示になります）</p>';
    }
    echo '</div></section>';

    echo '<div class="match-feedback-flow-preview__submit-mock">';
    echo '<span class="btn btn-primary" tabindex="-1" aria-hidden="true">送信</span>';
    echo '</div>';

    echo '</div></div>';
    return (string) ob_get_clean();
}

/**
 * プレビュー HTML（サーバー初期描画）
 *
 * @param array<string, mixed> $fields
 */
function aidunite_admin_post_match_module_render_preview_html(array $fields) {
    $args = aidunite_build_match_feedback_flow_preview_args($fields);
    echo aidunite_render_match_feedback_flow_preview($args);
}

/**
 * @param string $base_url
 */
function aidunite_admin_post_match_module_render_list($base_url) {
    $log_counts = aidunite_admin_post_match_module_log_counts_by_module();
    $posts = get_posts([
        'post_type'      => 'post_match_module',
        'post_status'    => ['publish', 'draft', 'pending'],
        'posts_per_page' => 100,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);

    echo '<div class="admin-pmm-toolbar">';
    echo '<a href="' . esc_url(add_query_arg('pmm_action', 'new', $base_url)) . '" class="btn btn-primary">＋ 新規作成</a>';
    echo '<span class="admin-pmm-toolbar-meta">全 ' . count($posts) . ' 件</span>';
    echo '</div>';

    if ($posts === []) {
        echo '<p class="admin-pmm-empty">まだモジュールがありません。「新規作成」から追加してください。</p>';
        return;
    }

    echo '<div class="admin-pmm-table-wrap">';
    echo '<table class="admin-pmm-table">';
    echo '<thead><tr>';
    echo '<th>ID</th><th>タイトル</th><th>種別</th><th>表示枠</th><th>表示</th><th>ユーザー画面</th><th>優先度</th><th>期間</th><th>表示回数</th><th>CTA</th><th>操作</th>';
    echo '</tr></thead><tbody>';

    foreach ($posts as $post) {
        $meta = aidunite_post_match_module_get_meta($post->ID);
        $active = !empty($meta['is_active']);
        $counts = $log_counts[$post->ID] ?? ['impression' => 0, 'cta_click' => 0];
        $period = '—';
        if (!empty($meta['start_at']) || !empty($meta['end_at'])) {
            $period = esc_html(trim(($meta['start_at'] ?: '—') . ' ～ ' . ($meta['end_at'] ?: '—')));
        }
        $edit_url = add_query_arg(['pmm_action' => 'edit', 'module_id' => $post->ID], $base_url);

        echo '<tr>';
        echo '<td>' . (int) $post->ID . '</td>';
        echo '<td><strong>' . esc_html($meta['title']) . '</strong></td>';
        echo '<td>' . esc_html(aidunite_post_match_module_type_label($meta['type'])) . '</td>';
        echo '<td><code>' . esc_html($meta['slot_key']) . '</code><br><small>' . esc_html(aidunite_post_match_module_slot_label($meta['slot_key'])) . '</small></td>';
        echo '<td><span class="admin-pmm-badge admin-pmm-badge--' . ($active ? 'on' : 'off') . '">' . ($active ? 'ON' : 'OFF') . '</span></td>';
        $warnings = function_exists('aidunite_post_match_module_visibility_warnings')
            ? aidunite_post_match_module_visibility_warnings($meta)
            : [];
        if ($warnings !== []) {
            echo '<td><span class="admin-pmm-badge admin-pmm-badge--warn">' . esc_html(implode(' / ', $warnings)) . '</span></td>';
        } else {
            echo '<td><span class="admin-pmm-badge admin-pmm-badge--on">表示可</span></td>';
        }
        echo '<td>' . (int) $meta['priority'] . '</td>';
        echo '<td class="admin-pmm-period">' . $period . '</td>';
        echo '<td>' . (int) $counts['impression'] . '</td>';
        echo '<td>' . (int) $counts['cta_click'] . '</td>';
        echo '<td class="admin-pmm-actions">';
        echo '<button type="button" class="btn btn-secondary btn-sm admin-pmm-preview-btn" data-module-id="' . (int) $post->ID . '" data-testid="post-match-module-preview-open">プレビュー</button> ';
        echo '<a href="' . esc_url($edit_url) . '" class="btn btn-secondary btn-sm">編集</a> ';
        echo '<form method="post" class="admin-pmm-inline-form" onsubmit="return confirm(\'このモジュールを削除しますか？\');">';
        echo '<input type="hidden" name="aidunite_pmm_action" value="delete">';
        echo '<input type="hidden" name="module_id" value="' . (int) $post->ID . '">';
        wp_nonce_field('aidunite_admin_pmm_delete_' . $post->ID);
        echo '<button type="submit" class="btn btn-secondary btn-sm admin-pmm-btn-danger">削除</button>';
        echo '</form>';
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

/**
 * @param string $base_url
 * @param string $action
 * @param int    $module_id
 */
function aidunite_admin_post_match_module_render_form($base_url, $action, $module_id) {
    $is_new = $action === 'new';
    $title = '';
    $meta = [
        'type'            => 'notice',
        'slot_key'        => 'after_match_feedback',
        'is_active'       => true,
        'priority'        => 10,
        'description'     => '',
        'banner_image_id'     => 0,
        'banner_external_url' => '',
        'cta_label'       => '',
        'cta_url'         => '',
        'target_sport'    => '',
        'target_region'   => '',
    ];

    if (!$is_new && $module_id > 0) {
        $post = get_post($module_id);
        if (!$post || $post->post_type !== 'post_match_module') {
            echo '<p class="admin-pmm-notice admin-pmm-notice--error">モジュールが見つかりません。</p>';
            return;
        }
        $title = $post->post_title;
        $loaded = aidunite_post_match_module_get_meta($module_id);
        $meta = array_merge($meta, $loaded);
    }

    $start_local = $module_id > 0 ? aidunite_post_match_module_datetime_local($module_id, 'start_at') : '';
    $end_local = $module_id > 0 ? aidunite_post_match_module_datetime_local($module_id, 'end_at') : '';

    echo '<p><a href="' . esc_url($base_url) . '" class="btn btn-secondary">← 一覧に戻る</a></p>';
    echo '<h2 class="admin-pmm-subtitle">' . ($is_new ? '新規作成' : '編集（ID ' . (int) $module_id . '）') . '</h2>';

    $no_period_checked = ($start_local === '' && $end_local === '');
    $preview_fields = [
        'module_id'       => $module_id,
        'title'           => $title,
        'module_type'     => $meta['type'],
        'is_active'       => !empty($meta['is_active']),
        'start_at'        => $start_local,
        'end_at'          => $end_local,
        'description'     => $meta['description'],
        'banner_image_id'     => (int) $meta['banner_image_id'],
        'banner_external_url' => (string) ($meta['banner_external_url'] ?? ''),
        'cta_label'       => $meta['cta_label'],
        'cta_url'         => $meta['cta_url'],
        'target_sport'    => $meta['target_sport'],
        'target_region'   => $meta['target_region'],
    ];

    $sport_options = ['' => '指定なし（全競技）', 'basketball' => 'バスケットボール'];
    $region_options = ['' => '指定なし（全地域）'];
    if (function_exists('aidunite_team_activity_prefecture_filter_options')) {
        foreach (aidunite_team_activity_prefecture_filter_options() as $code => $label) {
            $region_options[$code] = $label;
        }
    }

    $banner_preview_url = function_exists('aidunite_post_match_module_resolve_banner_url')
        ? aidunite_post_match_module_resolve_banner_url(
            (int) $meta['banner_image_id'],
            (string) ($meta['banner_external_url'] ?? '')
        )
        : '';

    echo '<div class="admin-pmm-layout">';
    echo '<div class="admin-pmm-layout__main admin-pmm-setting-panel">';
    echo '<form method="post" class="admin-pmm-form" id="admin-pmm-form" data-admin-pmm-preview-form>';
    echo '<input type="hidden" name="aidunite_pmm_action" value="save">';
    echo '<input type="hidden" name="module_id" value="' . (int) $module_id . '">';
    wp_nonce_field('aidunite_admin_pmm_save');

    echo '<div class="admin-pmm-panel-header">';
    echo '<h2 class="admin-pmm-panel-title">試合後アンケートの表示設定</h2>';
    echo '<button type="submit" class="admin-pmm-btn-save">保存</button>';
    echo '</div>';
    echo '<a href="#admin-pmm-preview-root" class="admin-pmm-preview-jump">👁 プレビュー</a>';

    echo '<section class="admin-pmm-setting-card">';
    echo '<h3 class="admin-pmm-setting-card__title"><span class="admin-pmm-setting-card__num">1</span> 基本設定</h3>';
    echo '<div class="admin-pmm-field">';
    echo '<label class="admin-pmm-label admin-pmm-label--switch"><input type="checkbox" name="is_active" value="1" ' . checked(!empty($meta['is_active']), true, false) . '> 表示する（ON）</label>';
    echo '<p class="admin-pmm-help">OFF のときはユーザー画面に表示されません。</p>';
    echo '</div>';
    echo '<div class="admin-pmm-field">';
    echo '<label for="pmm_title" class="admin-pmm-label">タイトル <span class="required">*</span></label>';
    echo '<input type="text" id="pmm_title" name="title" class="admin-pmm-input" required value="' . esc_attr($title) . '">';
    echo '</div>';
    echo '<div class="admin-pmm-field-row">';
    echo '<div class="admin-pmm-field"><label for="module_type" class="admin-pmm-label">種別</label><select id="module_type" name="module_type" class="admin-pmm-input">';
    foreach (aidunite_post_match_module_types() as $t) {
        echo '<option value="' . esc_attr($t) . '" ' . selected($meta['type'], $t, false) . '>' . esc_html(aidunite_post_match_module_type_label($t)) . '</option>';
    }
    echo '</select></div>';
    echo '<div class="admin-pmm-field"><label for="slot_key" class="admin-pmm-label">表示枠</label><select id="slot_key" name="slot_key" class="admin-pmm-input">';
    foreach (aidunite_post_match_module_slot_keys() as $s) {
        echo '<option value="' . esc_attr($s) . '" ' . selected($meta['slot_key'], $s, false) . '>' . esc_html(aidunite_post_match_module_slot_label($s)) . '</option>';
    }
    echo '</select>';
    echo '<p class="admin-pmm-help">Phase A では未回答・回答済みのどちらの枠でも同じモジュールが表示されます（おすすめ: 未回答フォーム下）。</p>';
    echo '</div></div>';
    echo '<div class="admin-pmm-field">';
    echo '<label for="priority" class="admin-pmm-label">表示優先度</label>';
    echo '<input type="number" id="priority" name="priority" class="admin-pmm-input" min="0" step="1" value="' . esc_attr((string) (int) $meta['priority']) . '">';
    echo '<p class="admin-pmm-help">小さい数字ほど上に表示されます。</p>';
    echo '</div></section>';

    echo '<section class="admin-pmm-setting-card">';
    echo '<h3 class="admin-pmm-setting-card__title"><span class="admin-pmm-setting-card__num">2</span> 表示期間</h3>';
    echo '<div class="admin-pmm-field"><label class="admin-pmm-label"><input type="checkbox" name="no_period" value="1" id="pmm_no_period" ' . checked($no_period_checked, true, false) . '> 期間指定なし（常時表示）</label></div>';
    echo '<div class="admin-pmm-field-row" id="pmm_period_fields">';
    echo '<div class="admin-pmm-field"><label for="start_at" class="admin-pmm-label">表示開始</label><input type="datetime-local" id="start_at" name="start_at" class="admin-pmm-input" value="' . esc_attr($start_local) . '"></div>';
    echo '<div class="admin-pmm-field"><label for="end_at" class="admin-pmm-label">表示終了</label><input type="datetime-local" id="end_at" name="end_at" class="admin-pmm-input" value="' . esc_attr($end_local) . '"></div>';
    echo '</div>';
    echo '<p class="admin-pmm-help">表示終了を過ぎるとユーザー画面には出ません。</p></section>';

    echo '<section class="admin-pmm-setting-card">';
    echo '<h3 class="admin-pmm-setting-card__title"><span class="admin-pmm-setting-card__num">3</span> 表示条件</h3>';
    echo '<p class="admin-pmm-help">未指定の項目は全チームに表示されます。</p>';
    echo '<div class="admin-pmm-field-row">';
    echo '<div class="admin-pmm-field"><label for="target_sport" class="admin-pmm-label">競技</label><select id="target_sport" name="target_sport" class="admin-pmm-input">';
    foreach ($sport_options as $val => $label) {
        echo '<option value="' . esc_attr($val) . '" ' . selected($meta['target_sport'], $val, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select></div>';
    echo '<div class="admin-pmm-field"><label for="target_region" class="admin-pmm-label">地域</label><select id="target_region" name="target_region" class="admin-pmm-input">';
    foreach ($region_options as $val => $label) {
        echo '<option value="' . esc_attr($val) . '" ' . selected($meta['target_region'], $val, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select></div></div>';
    echo '<div class="admin-pmm-field"><label for="target_age_future" class="admin-pmm-label">年代</label>';
    echo '<select id="target_age_future" class="admin-pmm-input" disabled><option>（今後追加）</option></select></div>';
    echo '<div class="admin-pmm-field"><label for="target_gender_future" class="admin-pmm-label">性別</label>';
    echo '<select id="target_gender_future" class="admin-pmm-input" disabled><option>（今後追加）</option></select></div>';
    echo '</section>';

    echo '<section class="admin-pmm-setting-card">';
    echo '<h3 class="admin-pmm-setting-card__title"><span class="admin-pmm-setting-card__num">4</span> バナー・リンク</h3>';
    $banner_id = (int) $meta['banner_image_id'];
    echo '<div class="admin-pmm-field admin-pmm-banner-field">';
    echo '<span class="admin-pmm-label">バナー画像</span>';
    echo '<div class="admin-pmm-banner-preview-wrap">';
    echo '<img class="admin-pmm-banner-preview" src="' . esc_url($banner_preview_url) . '" alt="" id="admin-pmm-banner-preview-img"' . ($banner_preview_url === '' ? ' hidden' : '') . '>';
    echo '<div class="admin-pmm-banner-preview admin-pmm-banner-preview--empty" id="admin-pmm-banner-preview-empty"' . ($banner_preview_url !== '' ? ' hidden' : '') . '>バナー未設定</div>';
    echo '</div>';
    echo '<input type="hidden" id="banner_image_id" name="banner_image_id" value="' . esc_attr((string) $banner_id) . '">';
    echo '<div class="admin-pmm-banner-actions">';
    echo '<button type="button" class="button button-secondary" id="admin-pmm-banner-select" data-testid="admin-pmm-banner-select">画像を選ぶ</button>';
    echo '<button type="button" class="button button-secondary" id="admin-pmm-banner-remove" data-testid="admin-pmm-banner-remove"' . ($banner_id <= 0 ? ' disabled' : '') . '>画像を外す</button>';
    echo '</div>';
    echo '<p class="admin-pmm-help">この画面から画像をアップロード・選択できます（WordPress 管理画面を開く必要はありません）。</p>';
    if (function_exists('aidunite_post_match_module_banner_admin_help_html')) {
        echo aidunite_post_match_module_banner_admin_help_html();
    }
    echo '</div>';
    $external_banner = (string) ($meta['banner_external_url'] ?? '');
    echo '<div class="admin-pmm-field admin-pmm-banner-external">';
    echo '<label for="banner_external_url" class="admin-pmm-label">バナー画像URL（外部・他社ホスト用）</label>';
    echo '<p class="admin-pmm-help admin-pmm-banner-external__lead">スポンサー等が自社サーバーで配信している画像をそのまま表示できます（アップロード不要）。<strong>http / https</strong> の画像URL（プレビューは https 推奨）。</p>';
    echo '<input type="url" id="banner_external_url" name="banner_external_url" class="admin-pmm-input" value="' . esc_attr($external_banner) . '" placeholder="http:// または https://example.com/banner.jpg" inputmode="url" autocomplete="url">';
    echo '<p class="admin-pmm-help">「画像を選ぶ」でメディアを設定している場合は、そちらが優先されます。外部URLだけ使う場合はメディアを「画像を外す」にしてください。</p>';
    echo '</div>';
    echo '<div class="admin-pmm-field"><label for="description" class="admin-pmm-label">詳細文</label>';
    echo '<textarea id="description" name="description" class="admin-pmm-input admin-pmm-textarea" rows="5">' . esc_textarea((string) $meta['description']) . '</textarea></div>';
    echo '<div class="admin-pmm-field-row">';
    echo '<div class="admin-pmm-field"><label for="cta_label" class="admin-pmm-label">CTAボタン文言</label><input type="text" id="cta_label" name="cta_label" class="admin-pmm-input" value="' . esc_attr((string) $meta['cta_label']) . '" placeholder="詳しく見る"></div>';
    echo '<div class="admin-pmm-field"><label for="cta_url" class="admin-pmm-label">CTA URL</label><input type="url" id="cta_url" name="cta_url" class="admin-pmm-input" value="' . esc_attr((string) $meta['cta_url']) . '"></div>';
    echo '</div></section>';

    echo '<section class="admin-pmm-setting-card">';
    echo '<h3 class="admin-pmm-setting-card__title"><span class="admin-pmm-setting-card__num">5</span> 表示オプション</h3>';
    echo '<label class="admin-pmm-checkbox-label"><input type="checkbox" checked disabled> 表示する（ON）— 上記の表示ON/OFFで制御</label>';
    echo '<label class="admin-pmm-checkbox-label admin-pmm-checkbox-label--muted"><input type="checkbox" disabled> ランダム表示にする（MVP未使用）</label>';
    echo '<p class="admin-pmm-help">表示優先度は「基本設定」で指定。ランダム表示・表示回数制限は今後追加予定です。</p>';
    echo '</section>';

    echo '<section class="admin-pmm-setting-card admin-pmm-setting-card--preview-note">';
    echo '<h3 class="admin-pmm-setting-card__title"><span class="admin-pmm-setting-card__num">6</span> プレビュー</h3>';
    echo '<p class="admin-pmm-help">右側（狭い画面では下）に、ユーザー画面での表示順を確認できます。</p>';
    echo '</section>';

    echo '<div class="admin-pmm-form-actions">';
    echo '<a href="' . esc_url($base_url) . '" class="btn btn-secondary">キャンセル</a>';
    echo '</div>';
    echo '</form>';
    echo '</div>';

    echo '<aside class="admin-pmm-layout__preview admin-pmm-preview-panel" aria-label="プレビュー" id="admin-pmm-preview-panel">';
    echo '<h3 class="admin-pmm-preview-heading">ユーザー画面プレビュー</h3>';
    echo '<p class="admin-pmm-preview-hint">①試合評価 → ②お気に入り → ③再戦希望 → ④運営モジュールの順（会場の評価はなし）。右のプレビューはユーザー画面に近い表示です。</p>';
    echo '<div id="admin-pmm-preview-status" class="admin-pmm-preview-status" role="status" aria-live="polite" hidden></div>';
    echo '<button type="button" class="btn btn-secondary btn-sm admin-pmm-preview-retry-btn" hidden>プレビューを再取得</button>';
    echo '<div id="admin-pmm-preview-root" class="admin-pmm-preview-root" data-testid="post-match-module-preview-root">';
    aidunite_admin_post_match_module_render_preview_html($preview_fields);
    echo '</div>';
    echo '</aside>';
    echo '</div>';
}

/**
 * 管理ページ用 CSS
 */
function aidunite_admin_post_match_module_enqueue_assets() {
    if (!is_page_template('page-admin-post-match-modules.php')) {
        return;
    }
    $theme_uri = get_stylesheet_directory_uri();
    $ver = wp_get_theme()->get('Version') ?: '1.0.0';
    wp_enqueue_style('aidunite-post-match-modules', $theme_uri . '/assets/css/components/post-match-module.css', [], $ver);
    if (function_exists('aidunite_match_feedback_form_enqueue_assets')) {
        aidunite_match_feedback_form_enqueue_assets();
    }
    wp_enqueue_style('aidunite-admin-pmm', $theme_uri . '/assets/css/pages/admin-post-match-modules.css', ['aidunite-post-match-modules', 'aidunite-match-feedback-form'], $ver);
    wp_enqueue_media();
    $pmm_js = get_stylesheet_directory() . '/assets/js/admin/admin-post-match-modules.js';
    wp_enqueue_script(
        'aidunite-admin-pmm',
        $theme_uri . '/assets/js/admin/admin-post-match-modules.js',
        ['jquery', 'media-editor'],
        is_readable($pmm_js) ? (string) filemtime($pmm_js) : $ver,
        true
    );
    wp_localize_script('aidunite-admin-pmm', 'aiduniteAdminPmmConfig', [
        'restBase'          => esc_url_raw(rest_url('aidunite/v1')),
        'mediaRestBase'     => esc_url_raw(rest_url('wp/v2/media')),
        'nonce'             => wp_create_nonce('wp_rest'),
        'mediaFrameTitle'   => 'バナー画像を選択',
        'mediaFrameButton'  => 'この画像を使う',
        'mediaUnavailable'  => '画像選択を利用できません。管理者でログインしているか確認してください。',
    ]);
}
add_action('wp_enqueue_scripts', 'aidunite_admin_post_match_module_enqueue_assets');
