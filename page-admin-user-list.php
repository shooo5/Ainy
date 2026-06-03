<?php
/*
Template Name: 管理用ユーザー一覧
*/
// 統一認証・権限チェック（管理者のみ）
require_once get_template_directory() . '/functions/common/auth-middleware.php';
$auth_result = AidUniteAuthMiddleware::require_admin(true);
if (!$auth_result->is_valid()) {
    // リダイレクトは自動で実行される
    return;
}
$current_user_id = $auth_result->user_id;

/**
 * 管理用ユーザー一覧: ユーザーアカウント削除（退会ポリシー準拠）
 *
 * @param int $user_id
 * @param int $acting_user_id
 * @return bool
 */
function aidunite_admin_user_list_delete_user_account($user_id, $acting_user_id) {
    $user_id = (int) $user_id;
    $acting_user_id = (int) $acting_user_id;
    if ($user_id <= 0 || $user_id === $acting_user_id) {
        return false;
    }

    $user = get_user_by('ID', $user_id);
    if (!$user) {
        return false;
    }

    $delete_post_types = function_exists('aidunite_get_withdrawal_delete_post_types')
        ? aidunite_get_withdrawal_delete_post_types()
        : ['team', 'schedule', 'notification', 'match_log'];
    $keep_post_types = function_exists('aidunite_get_withdrawal_keep_post_types')
        ? aidunite_get_withdrawal_keep_post_types()
        : ['message', 'payment_log', 'audit_log'];

    foreach ($delete_post_types as $post_type) {
        $posts = get_posts([
            'author' => $user_id,
            'post_type' => $post_type,
            'numberposts' => -1,
            'post_status' => 'any',
        ]);
        foreach ($posts as $post) {
            if ($post_type === 'team' && function_exists('aidunite_detach_team_members_before_delete')) {
                aidunite_detach_team_members_before_delete($post->ID, $user_id);
            }
            wp_delete_post($post->ID, true);
        }
    }

    foreach ($keep_post_types as $post_type) {
        $posts = get_posts([
            'author' => $user_id,
            'post_type' => $post_type,
            'numberposts' => -1,
            'post_status' => 'any',
        ]);
        foreach ($posts as $post) {
            wp_update_post([
                'ID' => $post->ID,
                'post_author' => 1,
                'post_status' => 'private',
            ]);
        }
    }

    $user_meta = get_user_meta($user_id);
    foreach ($user_meta as $meta_key => $meta_values) {
        delete_user_meta($user_id, $meta_key);
    }

    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user($user_id);

    return true;
}

// ユーザー削除処理
if (isset($_POST['delete_user_id'])) {
    // CSRF対策（統一版）
    $delete_user_id = intval($_POST['delete_user_id']);
    $nonce_result = AidUniteAuthMiddleware::verify_nonce('_wpnonce', 'delete_user_' . $delete_user_id);
    if (is_wp_error($nonce_result)) {
        wp_die($nonce_result->get_error_message(), 'エラー', ['response' => 403]);
    }

    if ($delete_user_id && $delete_user_id !== $current_user_id) {
        if (aidunite_admin_user_list_delete_user_account($delete_user_id, $current_user_id)) {
            echo '<div class="notice notice-success">ユーザーID ' . esc_html($delete_user_id) . ' を削除しました。<br>';
            echo '※重要な履歴（メッセージ、支払い履歴など）は管理者に移管して保持しています。</div>';
        } else {
            echo '<div class="notice notice-error">指定されたユーザーが見つかりません。</div>';
        }
    } else {
        echo '<div class="notice notice-error">自分自身を削除することはできません。</div>';
    }
}

// ロール編集処理
if (isset($_POST['edit_role_user_id'], $_POST['new_aidunite_role']) && check_admin_referer('edit_role_' . $_POST['edit_role_user_id'])) {
    $edit_user_id = intval($_POST['edit_role_user_id']);
    $new_role = sanitize_text_field($_POST['new_aidunite_role']);
    update_user_meta($edit_user_id, 'aidunite_role', $new_role);
    echo '<div class="notice notice-success">ユーザーID ' . esc_html($edit_user_id) . ' のロールを ' . esc_html($new_role) . ' に変更しました。</div>';
}

// 登録状態編集処理
if (isset($_POST['edit_status_user_id'], $_POST['new_registration_status'])) {
    // CSRF対策（統一版）
    $edit_user_id = intval($_POST['edit_status_user_id']);
    $nonce_result = AidUniteAuthMiddleware::verify_nonce('_wpnonce', 'edit_status_' . $edit_user_id);
    if (is_wp_error($nonce_result)) {
        wp_die($nonce_result->get_error_message(), 'エラー', ['response' => 403]);
    }
    $new_status = sanitize_text_field($_POST['new_registration_status']);

    if ($new_status === '') {
        delete_user_meta($edit_user_id, 'registration_status');
        echo '<div class="notice notice-success">ユーザーID ' . esc_html($edit_user_id) . ' の登録状態を削除しました。</div>';
    } else {
        $saved = aidunite_update_user_registration_status_meta($edit_user_id, $new_status);
        $display_status = $saved !== '' ? $saved : $new_status;
        echo '<div class="notice notice-success">ユーザーID ' . esc_html($edit_user_id) . ' の登録状態を ' . esc_html($display_status) . ' に変更しました。</div>';
    }
}

// マルチチーム managed_team_ids 修復（代表者の publish team を再収集）
if (isset($_POST['reconcile_managed_teams_user_id'])) {
    $reconcile_user_id = (int) $_POST['reconcile_managed_teams_user_id'];
    $nonce_result = AidUniteAuthMiddleware::verify_nonce('_wpnonce', 'reconcile_managed_' . $reconcile_user_id);
    if (is_wp_error($nonce_result)) {
        wp_die($nonce_result->get_error_message(), 'エラー', ['response' => 403]);
    }
    if ($reconcile_user_id > 0 && function_exists('aidunite_reconcile_user_managed_team_ids')) {
        $fixed = aidunite_reconcile_user_managed_team_ids($reconcile_user_id);
        $raw_after = function_exists('aidunite_read_user_managed_team_ids_meta')
            ? aidunite_read_user_managed_team_ids_meta($reconcile_user_id)
            : [];
        echo '<div class="notice notice-success">ユーザーID '
            . esc_html((string) $reconcile_user_id)
            . ' の managed_team_ids を修復しました（publish 件数: '
            . esc_html((string) count($fixed))
            . '）。保存値: <code>'
            . esc_html((string) get_user_meta($reconcile_user_id, 'managed_team_ids', true))
            . '</code></div>';
    }
}

// メタ情報編集処理
if (isset($_POST['edit_meta_user_id'], $_POST['meta_key'], $_POST['meta_value'])) {
    // CSRF対策（統一版）
    $edit_user_id = intval($_POST['edit_meta_user_id']);
    $nonce_result = AidUniteAuthMiddleware::verify_nonce('_wpnonce', 'edit_meta_' . $edit_user_id);
    if (is_wp_error($nonce_result)) {
        wp_die($nonce_result->get_error_message(), 'エラー', ['response' => 403]);
    }
    $meta_key = sanitize_text_field($_POST['meta_key']);
    $meta_value = sanitize_text_field($_POST['meta_value']);

    if ($meta_value === '') {
        delete_user_meta($edit_user_id, $meta_key);
        echo '<div class="notice notice-success">ユーザーID ' . esc_html($edit_user_id) . ' のメタ情報 ' . esc_html($meta_key) . ' を削除しました。</div>';
    } else {
        update_user_meta($edit_user_id, $meta_key, $meta_value);
        echo '<div class="notice notice-success">ユーザーID ' . esc_html($edit_user_id) . ' のメタ情報 ' . esc_html($meta_key) . ' を ' . esc_html($meta_value) . ' に更新しました。</div>';
    }
}

// プラン設定編集処理
if (isset($_POST['edit_plan_user_id'], $_POST['edit_plan_team_id'], $_POST['selected_plan_id']) && check_admin_referer('edit_plan_' . $_POST['edit_plan_user_id'])) {
    $edit_user_id = intval($_POST['edit_plan_user_id']);
    $edit_team_id = intval($_POST['edit_plan_team_id']);
    $selected_plan_id = sanitize_text_field($_POST['selected_plan_id']);

    if ($edit_team_id > 0) {
        // プラン設定関数を使用
        require_once get_template_directory() . '/functions/payment/payment-config.php';
        require_once get_template_directory() . '/functions/payment/payment-functions.php';

        if (!empty($selected_plan_id)) {
            aidunite_set_selected_plan_id($edit_team_id, $selected_plan_id);

            // トライアル開始日を設定（まだ設定されていない場合）
            $trial_start = aidunite_get_trial_start_date($edit_team_id);
            if (empty($trial_start)) {
                aidunite_set_trial_start_date($edit_team_id);
            }

            // 支払いステータスをトライアルに設定（まだ設定されていない場合）
            $current_status = aidunite_get_payment_status($edit_user_id);
            if (empty($current_status)) {
                aidunite_set_payment_status($edit_user_id, 'trial');
            }

            echo '<div class="notice notice-success">ユーザーID ' . esc_html($edit_user_id) . ' のチームID ' . esc_html($edit_team_id) . ' のプランを ' . esc_html($selected_plan_id) . ' に設定しました。</div>';
        } else {
            // プランIDが空の場合は削除
            delete_post_meta($edit_team_id, 'selected_plan_id');
            echo '<div class="notice notice-success">ユーザーID ' . esc_html($edit_user_id) . ' のチームID ' . esc_html($edit_team_id) . ' のプラン設定を削除しました。</div>';
        }
    } else {
        echo '<div class="notice notice-error">チームIDが設定されていません。</div>';
    }
}

// 支払いステータス編集処理
if (isset($_POST['edit_payment_status_user_id'], $_POST['payment_status'])) {
    // CSRF対策（統一版）
    $edit_user_id = intval($_POST['edit_payment_status_user_id']);
    $nonce_result = AidUniteAuthMiddleware::verify_nonce('_wpnonce', 'edit_payment_status_' . $edit_user_id);
    if (is_wp_error($nonce_result)) {
        wp_die($nonce_result->get_error_message(), 'エラー', ['response' => 403]);
    }
    $payment_status = sanitize_text_field($_POST['payment_status']);

    require_once get_template_directory() . '/functions/payment/payment-config.php';
    require_once get_template_directory() . '/functions/payment/payment-functions.php';

    if (!empty($payment_status)) {
        aidunite_set_payment_status($edit_user_id, $payment_status);
        echo '<div class="notice notice-success">ユーザーID ' . esc_html($edit_user_id) . ' の支払いステータスを ' . esc_html($payment_status) . ' に変更しました。</div>';
    } else {
        delete_user_meta($edit_user_id, 'payment_status');
        delete_user_meta($edit_user_id, 'payment_status_updated');
        echo '<div class="notice notice-success">ユーザーID ' . esc_html($edit_user_id) . ' の支払いステータスを削除しました。</div>';
    }
}

// team_id編集処理
if (isset($_POST['edit_team_id_user_id'], $_POST['team_id'])) {
    $edit_user_id = intval($_POST['edit_team_id_user_id']);

    // CSRF対策（統一版）
    $nonce_result = AidUniteAuthMiddleware::verify_nonce('_wpnonce', 'edit_team_id_' . $edit_user_id);
    if (is_wp_error($nonce_result)) {
        error_log('Nonce validation failed for user_id: ' . $edit_user_id);
        echo '<div class="notice notice-error">セキュリティチェックに失敗しました。ページを再読み込みして再度お試しください。</div>';
    } else {
        // デバッグ情報
        error_log('Nonce verification - action: edit_team_id_' . $edit_user_id . ', valid: true');
        $team_id = sanitize_text_field($_POST['team_id']);

        if (!empty($team_id) && is_numeric($team_id)) {
            $team_id = intval($team_id);
            // チームが存在するか確認
            $team_post = get_post($team_id);

            // デバッグ情報を追加
            error_log('=== Team ID Validation ===');
            error_log('Checking team_id: ' . $team_id);
            error_log('get_post result: ' . ($team_post ? 'exists' : 'null'));
            if ($team_post) {
                error_log('post_type: ' . $team_post->post_type);
                error_log('post_status: ' . $team_post->post_status);
                error_log('post_title: ' . $team_post->post_title);
                error_log('post_ID: ' . $team_post->ID);
            } else {
                error_log('Team post with ID ' . $team_id . ' does not exist');
                // すべての投稿タイプを確認
                $all_posts = get_posts(['post_type' => 'any', 'numberposts' => -1]);
                error_log('Total posts in database: ' . count($all_posts));
                $team_posts = get_posts(['post_type' => 'team', 'numberposts' => -1]);
                error_log('Total team posts: ' . count($team_posts));
                if (!empty($team_posts)) {
                    $team_post_ids = array_map(function($p) { return $p->ID; }, $team_posts);
                    error_log('Team post IDs: ' . implode(', ', $team_post_ids));
                }
            }

            if ($team_post && $team_post->post_type === 'team') {
                // 保存前の値を確認
                $before_team_id = get_user_meta($edit_user_id, 'team_id', true);
                error_log('Before update - user_id: ' . $edit_user_id . ', team_id: ' . var_export($before_team_id, true));

                // チームIDを保存
                $update_result = update_user_meta($edit_user_id, 'team_id', $team_id);
                error_log('update_user_meta result: ' . var_export($update_result, true));

                // 保存後の値を確認
                $after_team_id = get_user_meta($edit_user_id, 'team_id', true);
                error_log('After update - user_id: ' . $edit_user_id . ', team_id: ' . var_export($after_team_id, true));
                error_log('Expected team_id: ' . $team_id);

                // 保存が成功したか確認
                if ($after_team_id == $team_id) {
                    if (function_exists('aidunite_reconcile_user_managed_team_ids')) {
                        aidunite_reconcile_user_managed_team_ids($edit_user_id);
                    }
                    echo '<div class="notice notice-success">ユーザーID ' . esc_html($edit_user_id) . ' のチームIDを ' . esc_html($team_id) . ' に設定しました（managed_team_ids も再同期しました）。</div>';
                } else {
                    echo '<div class="notice notice-error">チームIDの保存に失敗しました。保存値: ' . esc_html($after_team_id) . ', 期待値: ' . esc_html($team_id) . '</div>';
                    error_log('ERROR: Team ID save failed - saved: ' . var_export($after_team_id, true) . ', expected: ' . $team_id);
                }
            } else {
                error_log('Team validation failed - post exists: ' . ($team_post ? 'yes' : 'no') . ', post_type: ' . ($team_post ? $team_post->post_type : 'N/A'));
                echo '<div class="notice notice-error">チームID ' . esc_html($team_id) . ' は存在しません。チーム投稿タイプを確認してください。</div>';
            }
        } else {
            // team_idが空の場合は削除
            delete_user_meta($edit_user_id, 'team_id');
            echo '<div class="notice notice-success">ユーザーID ' . esc_html($edit_user_id) . ' のチームIDを削除しました。</div>';
        }
    }
}

// 一括ロール変更処理
if (isset($_POST['bulk_action'], $_POST['user_ids'], $_POST['bulk_role'])) {
    // CSRF対策（統一版）
    $nonce_result = AidUniteAuthMiddleware::verify_nonce('_wpnonce', 'bulk_role_change');
    if (is_wp_error($nonce_result)) {
        wp_die($nonce_result->get_error_message(), 'エラー', ['response' => 403]);
    }

    $user_ids = array_map('intval', $_POST['user_ids']);
    $bulk_role = sanitize_text_field($_POST['bulk_role']);

    $success_count = 0;
    foreach ($user_ids as $user_id) {
        if ($user_id !== $current_user_id) {
            if (function_exists('aidunite_user_write_role_meta')) {
                if (aidunite_user_write_role_meta($user_id, $bulk_role) !== '') {
                    $success_count++;
                }
            } elseif (update_user_meta($user_id, 'aidunite_role', $bulk_role)) {
                $success_count++;
            }
        }
    }

    echo '<div class="notice notice-success">' . $success_count . '件のユーザーロールを ' . esc_html($bulk_role) . ' に変更しました。</div>';
}

// 一括削除処理
if (isset($_POST['bulk_delete_users'])) {
    $nonce_result = AidUniteAuthMiddleware::verify_nonce('_wpnonce', 'bulk_delete_users');
    if (is_wp_error($nonce_result)) {
        wp_die($nonce_result->get_error_message(), 'エラー', ['response' => 403]);
    }

    $user_ids = isset($_POST['user_ids']) ? array_map('intval', (array) $_POST['user_ids']) : [];
    $user_ids = array_values(array_unique(array_filter($user_ids)));

    $success_count = 0;
    foreach ($user_ids as $user_id) {
        if (aidunite_admin_user_list_delete_user_account($user_id, $current_user_id)) {
            $success_count++;
        }
    }

    if ($success_count > 0) {
        echo '<div class="notice notice-success">' . (int) $success_count . '件のユーザーを削除しました。</div>';
    } elseif (empty($user_ids)) {
        echo '<div class="notice notice-warning">削除するユーザーが選択されていません。</div>';
    } else {
        echo '<div class="notice notice-error">ユーザーを削除できませんでした（自分自身は削除できません）。</div>';
    }
}

// 検索・フィルター機能
$search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
$role_filter = isset($_GET['role_filter']) ? sanitize_text_field($_GET['role_filter']) : '';
$status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : '';

// ページネーション
$users_per_page = defined('AIDUNITE_ADMIN_USER_LIST_PER_PAGE') ? (int) AIDUNITE_ADMIN_USER_LIST_PER_PAGE : 20;
$users_per_page = max(1, min(100, $users_per_page));
$current_page = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;

// ユーザー取得（検索・フィルター適用）
$user_args = ['orderby' => 'registered', 'order' => 'DESC', 'number' => 99999];

if ($search) {
    $user_args['search'] = '*' . $search . '*';
    $user_args['search_columns'] = ['user_login', 'user_email', 'display_name'];
}

$users_all = get_users($user_args);

// フィルター適用
if ($role_filter || $status_filter) {
    $filtered_users = [];
    foreach ($users_all as $user) {
        $user_meta = get_user_meta($user->ID);
        $user_role = $user_meta['aidunite_role'][0] ?? '';
        $reg_status = $user_meta['registration_status'][0] ?? '';
        $role_match = !$role_filter || $user_role === $role_filter;
        if (!$status_filter) {
            $status_match = true;
        } elseif ($status_filter === 'active') {
            $status_match = in_array($reg_status, ['accepted', 'active'], true);
        } else {
            $status_match = $reg_status === $status_filter;
        }
        if ($role_match && $status_match) {
            $filtered_users[] = $user;
        }
    }
    $users_all = $filtered_users;
}

$total_users_count = count($users_all);
$total_pages = $total_users_count > 0 ? (int) ceil($total_users_count / $users_per_page) : 1;
$current_page = min($current_page, max(1, $total_pages));
$offset = ($current_page - 1) * $users_per_page;
$users = array_slice($users_all, $offset, $users_per_page);

// CSV出力
if (isset($_GET['csv']) && $_GET['csv'] === '1') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="users-' . date('Y-m-d-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, "\xEF\xBB\xBF"); // BOM for Excel UTF-8
    fputcsv($out, ['ID', '姓', '名', '氏名', 'メール', 'チームID', '世帯ID', '年齢', '性別', '登録日', '継続月', '登録状態', 'ロール']);
    foreach ($users_all as $u) {
        $meta = get_user_meta($u->ID);
        $reg_status = $meta['registration_status'][0] ?? '';
        $role = $meta['aidunite_role'][0] ?? '';
        $team_id_csv = get_user_meta($u->ID, 'team_id', true);
        $family_id_csv = $meta['family_id'][0] ?? '';
        $reg_date = $meta['registration_date'][0] ?? $u->user_registered;
        $last_name = $meta['last_name'][0] ?? '';
        $first_name = $meta['first_name'][0] ?? '';
        $display_name_common = trim($last_name . ' ' . $first_name) ?: $u->display_name;
        $birth = $meta['user_birth_date'][0] ?? '';
        $age = $birth && function_exists('calculate_user_age') ? calculate_user_age($birth) : '';
        $gender_key = $meta['user_gender'][0] ?? '';
        $gender_label = $gender_key && function_exists('get_gender_display_name') ? get_gender_display_name($gender_key) : $gender_key;
        $base_date_csv = $reg_date ?: $u->user_registered;
        $months_csv = '';
        if ($base_date_csv) {
            try {
                $from = new DateTime($base_date_csv);
                $to = new DateTime('now');
                $months_csv = (int) $from->diff($to)->format('%y') * 12 + (int) $from->diff($to)->format('%m');
            } catch (Exception $e) {}
        }
        fputcsv($out, [$u->ID, $last_name, $first_name, $display_name_common, $u->user_email, $team_id_csv, $family_id_csv, $age, $gender_label, $reg_date, $months_csv, $reg_status, $role]);
    }
    fclose($out);
    exit;
}

$aidunite_roles = ['team_leader', 'parent', 'player', 'supporter', 'general', 'administrator', 'public', 'match'];

// デバッグ: 実際に読み込まれているファイルパスを確認
if (isset($_GET['debug_file_path'])) {
    echo '<div style="background:var(--bg-primary); padding:var(--spacing-lg); margin:var(--spacing-lg); border:2px solid var(--text-primary); position:fixed; top:0; left:0; z-index:99999; max-width:600px;">';
    echo '<h2>デバッグ情報</h2>';
    echo '<p><strong>get_template_directory():</strong> ' . esc_html(get_template_directory()) . '</p>';
    echo '<p><strong>get_stylesheet_directory():</strong> ' . esc_html(get_stylesheet_directory()) . '</p>';
    echo '<p><strong>__FILE__:</strong> ' . esc_html(__FILE__) . '</p>';
    echo '<p><strong>realpath(__FILE__):</strong> ' . esc_html(realpath(__FILE__)) . '</p>';
    echo '<p><strong>is_link:</strong> ' . (is_link(__FILE__) ? 'true' : 'false') . '</p>';
    if (is_link(__FILE__)) {
        echo '<p><strong>readlink(__FILE__):</strong> ' . esc_html(readlink(__FILE__)) . '</p>';
    }
    echo '<p><strong>現在のファイルの736行目付近:</strong></p>';
    $lines = file(__FILE__);
    if (isset($lines[735])) {
        echo '<pre style="background:var(--bg-secondary); padding:var(--spacing-sm);">' . esc_html($lines[735]) . '</pre>';
    }
    if (isset($lines[736])) {
        echo '<pre style="background:var(--bg-secondary); padding:var(--spacing-sm);">' . esc_html($lines[736]) . '</pre>';
    }
    if (isset($lines[737])) {
        echo '<pre style="background:var(--bg-secondary); padding:var(--spacing-sm);">' . esc_html($lines[737]) . '</pre>';
    }
    echo '</div>';
}

get_header();
?>
<style>
.meta-info-section {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: 4px;
    padding: 15px;
    margin: 10px 0;
}
.meta-info-row {
    display: grid;
    grid-template-columns: 150px 1fr;
    gap: 10px;
    padding: 5px 0;
    border-bottom: 1px solid var(--border-light);
}
.meta-info-row:last-child {
    border-bottom: none;
}
.meta-key {
    font-weight: bold;
    color: var(--text-primary);
}
.meta-value {
    color: var(--text-primary);
}
.meta-value.empty {
    color: var(--text-muted);
    font-style: italic;
}
.edit-meta-form {
    display: inline-flex;
    gap: 5px;
    margin-top: 5px;
}
.edit-meta-form input[type="text"] {
    padding: 4px 8px;
    border: 1px solid var(--border-color);
    border-radius: 3px;
}
.edit-meta-form button {
    padding: 4px 12px;
    font-size: 12px;
}
.status-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: bold;
}
.status-pending {
    background: rgba(255, 193, 7, 0.1);
    color: var(--warning-color);
}
.status-accepted {
    background: rgba(40, 167, 69, 0.1);
    color: var(--success-color);
}
.status-active {
    background: rgba(23, 162, 184, 0.1);
    color: var(--info-color);
}
.status-invalid {
    background: rgba(220, 53, 69, 0.1);
    color: var(--danger-color);
}
.details-toggle {
    cursor: pointer;
    color: var(--primary-color);
    text-decoration: underline;
}
.details-toggle:hover {
    color: var(--primary-dark);
}
.user-details {
    display: none;
}
.user-details.active {
    display: table-row;
}
.user-details.active td {
    display: table-cell;
}
.user-details td {
    vertical-align: top;
}
.button-danger {
    background: rgba(220, 53, 69, 0.1);
    color: var(--danger-color, #dc3545);
    border: 1px solid var(--danger-color, #dc3545);
}
.button-danger:hover {
    background: var(--danger-color, #dc3545);
    color: #fff;
}
/* 表スタイルは admin-list-table.css */
</style>
<div class="wrap page-admin-user-list-wrap" style="width:100%;max-width:100%;margin:auto;">
  <h1>ユーザー管理（Ainy用）</h1>

  <!-- 検索・フィルター機能 -->
  <div class="search-filter-section" style="background:var(--bg-secondary); padding:var(--spacing-lg); margin:var(--spacing-lg) 0; border-radius:var(--radius-base);">
    <h3>🔍 検索・フィルター</h3>
    <form method="get" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:15px; align-items:end;">
      <div>
        <label for="search">検索:</label>
        <input type="text" id="search" name="search" value="<?php echo esc_attr($search); ?>" placeholder="名前・メール・ID" style="width:100%; padding:8px;">
      </div>
      <div>
        <label for="role_filter">ロール:</label>
        <select id="role_filter" name="role_filter" style="width:100%; padding:8px;">
          <option value="">すべて</option>
          <?php foreach ($aidunite_roles as $role): ?>
            <option value="<?php echo esc_attr($role); ?>" <?php selected($role_filter, $role); ?>><?php echo esc_html($role); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="status_filter">登録状態:</label>
        <select id="status_filter" name="status_filter" style="width:100%; padding:8px;">
          <option value="">すべて</option>
          <option value="pending" <?php selected($status_filter, 'pending'); ?>>pending</option>
          <option value="accepted" <?php selected($status_filter, 'accepted'); ?>>accepted</option>
          <option value="active" <?php selected($status_filter, 'active'); ?>>active</option>
        </select>
      </div>
      <div>
        <button type="submit" class="button button-primary">検索</button>
        <a href="<?php echo remove_query_arg(['search', 'role_filter', 'status_filter']); ?>" class="button">リセット</a>
      </div>
    </form>

    <?php if ($search || $role_filter || $status_filter): ?>
    <div style="margin-top:var(--spacing-base); padding:var(--spacing-sm); background:rgba(23, 162, 184, 0.1); border-radius:var(--radius-small);">
      <strong>検索結果:</strong> <?php echo $total_users_count; ?>件
      <?php if ($search): ?> | 検索: "<?php echo esc_html($search); ?>"<?php endif; ?>
      <?php if ($role_filter): ?> | ロール: <?php echo esc_html($role_filter); ?><?php endif; ?>
      <?php if ($status_filter): ?> | 状態: <?php echo esc_html($status_filter); ?><?php endif; ?>
    </div>
    <?php endif; ?>

    <div style="margin-top:var(--spacing-base); display:flex; flex-wrap:wrap; align-items:center; gap:var(--spacing-base);">
      <p style="margin:0;">全 <?php echo $total_users_count; ?> 件<?php if ($total_pages > 1): ?>（<?php echo $offset + 1; ?> - <?php echo min($offset + $users_per_page, $total_users_count); ?> 件目）<?php endif; ?></p>
      <a href="<?php echo esc_url(add_query_arg('csv', '1')); ?>" class="button">CSVで出力</a>
    </div>
  </div>

  <div class="role-description">
    <h3>ロール説明</h3>
    <ul>
      <li><strong>team_leader</strong>: チーム代表者（チーム管理・メンバー管理権限）</li>
      <li><strong>parent</strong>: 保護者（子供の出欠管理・スケジュール確認）</li>
      <li><strong>player</strong>: 選手（練習・試合参加者）</li>
      <li><strong>supporter</strong>: サポーター（チーム支援者）</li>
      <li><strong>administrator</strong>: 管理者（システム全体管理）</li>
      <li><strong>general</strong>: 一般会員（/member-register 本登録後の既定。チーム未所属）</li>
      <li><strong>public</strong>: 閲覧のみ</li>
      <li><strong>match</strong>: マッチング担当者</li>
    </ul>
  </div>

  <!-- 一括操作機能 -->
  <div class="bulk-actions-section" style="background:rgba(255, 193, 7, 0.1); padding:var(--spacing-base); margin:var(--spacing-lg) 0; border-radius:var(--radius-base); border:1px solid var(--warning-color);">
    <h3>⚡ 一括操作</h3>
    <form method="post" id="bulk-actions-form">
      <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:15px; align-items:end;">
        <div>
          <label for="bulk_role">一括ロール変更:</label>
          <select id="bulk_role" name="bulk_role" style="width:100%; padding:8px;">
            <option value="">ロールを選択</option>
            <?php foreach ($aidunite_roles as $role): ?>
              <option value="<?php echo esc_attr($role); ?>"><?php echo esc_html($role); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <button type="submit" name="bulk_action" value="change_role" class="button button-primary" onclick="return confirmBulkAction('ロール変更')">一括ロール変更</button>
        </div>
      </div>
      <?php wp_nonce_field('bulk_role_change'); ?>
    </form>
  </div>

  <?php if (!empty($users)) : ?>
  <div class="user-bulk-delete-section" style="background:rgba(220, 53, 69, 0.08); padding:var(--spacing-base); margin:var(--spacing-lg) 0; border-radius:var(--radius-base); border:1px solid var(--danger-color);">
    <h3 style="margin:0 0 var(--spacing-sm) 0;">🗑️ ユーザー一括削除</h3>
    <p style="margin:0 0 var(--spacing-base) 0; font-size:var(--font-size-sm, 0.875rem); color:var(--text-secondary);">
      一覧で選択したユーザーを一括削除できます（表示中のページのみ）。重要な履歴は管理者に移管して保持します。
    </p>
    <form method="post" id="bulk-delete-users-form">
      <div style="display:flex; flex-wrap:wrap; align-items:center; gap:var(--spacing-base);">
        <span id="user-bulk-selected-count" style="color:var(--text-secondary); font-size:var(--font-size-sm, 0.875rem);" aria-live="polite">0件選択</span>
        <button type="submit" name="bulk_delete_users" value="1" class="button button-danger" id="user-bulk-delete-btn" disabled onclick="return confirmBulkAction('削除');">選択したユーザーを一括削除</button>
      </div>
      <?php wp_nonce_field('bulk_delete_users'); ?>
    </form>
  </div>
  <?php endif; ?>

  <?php if ($total_pages > 1): ?>
  <nav class="admin-user-pagination" style="margin-bottom:var(--spacing-base);" aria-label="ページ送り">
    <?php
    $base = add_query_arg(array_filter(['search' => $search, 'role_filter' => $role_filter, 'status_filter' => $status_filter]), get_permalink());
    echo paginate_links([
        'base' => $base . '%_%',
        'format' => '?paged=%#%',
        'current' => $current_page,
        'total' => $total_pages,
        'prev_text' => '&laquo; 前へ',
        'next_text' => '次へ &raquo;',
    ]);
    ?>
  </nav>
  <?php endif; ?>

  <table class="admin-user-table wp-list-table widefat fixed striped users">
    <thead>
      <tr>
        <th class="col-checkbox">
          <label class="aidunite-admin-checkbox" title="すべて選択">
            <input type="checkbox" id="select-all-users" class="aidunite-admin-checkbox__input" aria-label="すべて選択">
            <span class="aidunite-admin-checkbox__box" aria-hidden="true"></span>
          </label>
        </th>
        <th class="col-no">No</th>
        <th>ID</th>
        <th>氏名</th>
        <th>メール</th>
        <th>チームID</th>
        <th>世帯ID</th>
        <th>登録日</th>
        <th>継続月</th>
        <th>登録状態</th>
        <th>ロール</th>
        <th>年齢</th>
        <th>性別</th>
        <th>詳細</th>
        <th>削除</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $idx => $user):
        $row_no = $offset + $idx + 1;
        $user_id = $user->ID;
        $meta = get_user_meta($user_id);
        $role = function_exists('aidunite_get_user_role')
            ? aidunite_get_user_role((int) $user_id)
            : ($meta['aidunite_role'][0] ?? '');
        $team_id = get_user_meta($user_id, 'team_id', true);
        if (
            function_exists('aidunite_reconcile_user_managed_team_ids')
            && ($role === 'team_leader' || !empty($team_id))
        ) {
            aidunite_reconcile_user_managed_team_ids((int) $user_id);
        }
        $managed_team_ids_raw = get_user_meta($user_id, 'managed_team_ids', true);
        $managed_team_ids = function_exists('aidunite_get_managed_team_ids')
            ? aidunite_get_managed_team_ids((int) $user_id)
            : [];
        $current_operating_team_id = get_user_meta($user_id, 'current_operating_team_id', true);
        $leader_teams_publish = function_exists('aidunite_discover_publish_team_ids_for_leader')
            ? aidunite_discover_publish_team_ids_for_leader((int) $user_id)
            : [];
        $leader_teams_all = function_exists('aidunite_discover_team_ids_for_leader')
            ? aidunite_discover_team_ids_for_leader((int) $user_id, ['publish', 'pending', 'draft', 'trash'])
            : [];
        $multi_team_needs_attention = count($leader_teams_publish) > 1 && count($managed_team_ids) < 2;
        $pending_team_id = $meta['pending_team_id'][0] ?? '';
        $team_id_display = $team_id ? (string) $team_id : '—';
        if (count($managed_team_ids) > 1) {
            $team_id_display .= ' (managed:' . count($managed_team_ids) . ')';
        } elseif (count($leader_teams_publish) > 1 && count($managed_team_ids) < 2) {
            $team_id_display .= ' (要修復:' . count($leader_teams_publish) . 'チーム)';
        }
        $parent_name = $meta['parent_name'][0] ?? '';
        $player_name = $meta['player_name'][0] ?? '';
        $reg_date = $meta['registration_date'][0] ?? '';
        $reg_status = $meta['registration_status'][0] ?? '';
        $has_token = !empty($meta['registration_token'][0]);
        $token_time = $meta['registration_token_time'][0] ?? '';
        // 共通メタ（姓・名・年齢・性別）
        $last_name = $meta['last_name'][0] ?? '';
        $first_name = $meta['first_name'][0] ?? '';
        $display_name_common = trim($last_name . ' ' . $first_name) ?: $user->display_name;
        $user_birth_date = $meta['user_birth_date'][0] ?? '';
        $age_display = $user_birth_date && function_exists('calculate_user_age') ? calculate_user_age($user_birth_date) . '歳' : '—';
        $user_gender_key = $meta['user_gender'][0] ?? '';
        $gender_display = $user_gender_key && function_exists('get_gender_display_name') ? get_gender_display_name($user_gender_key) : ($user_gender_key ?: '—');
        $family_id = $meta['family_id'][0] ?? '';
        // 継続月（登録日から今日までの経過月数）
        $base_date = $reg_date ?: $user->user_registered;
        $months_display = '—';
        if ($base_date) {
            try {
                $from = new DateTime($base_date);
                $to = new DateTime('now');
                $months = (int) $from->diff($to)->format('%y') * 12 + (int) $from->diff($to)->format('%m');
                $months_display = $months . 'ヶ月';
            } catch (Exception $e) {
                $months_display = '—';
            }
        }

        // プラン情報を取得
        $selected_plan_id = '';
        $payment_status = $meta['payment_status'][0] ?? '';
        $available_plans = [];

        if (!empty($team_id)) {
            require_once get_template_directory() . '/functions/payment/payment-config.php';
            require_once get_template_directory() . '/functions/payment/payment-functions.php';

            $selected_plan_id = aidunite_get_selected_plan_id($team_id);
            $team_type = aidunite_get_team_type($team_id);

            // 利用可能なプランを取得
            $config = aidunite_get_payment_config();
            $config_key = function_exists('aidunite_team_type_payment_config_key')
                ? aidunite_team_type_payment_config_key($team_id)
                : ($team_type === 'club' ? 'club' : 'school');
            if (in_array($config_key, ['school', 'club'], true)) {
                $available_plans = $config[$config_key]['plans'] ?? [];
            } else {
                // チームタイプが未設定の場合は両方のプランを表示
                $school_plans = $config['school']['plans'] ?? [];
                $club_plans = $config['club']['plans'] ?? [];
                $available_plans = array_merge($school_plans, $club_plans);
            }
        }

        // 登録状態のバッジ表示
        $status_class = 'status-invalid';
        $status_text = $reg_status ?: '(未設定)';
        if ($reg_status === 'pending') {
            $status_class = 'status-pending';
        } elseif ($reg_status === 'accepted' || $reg_status === 'active') {
            $status_class = 'status-accepted';
        }

      ?>
      <tr>
        <td class="col-checkbox">
          <label class="aidunite-admin-checkbox">
            <input type="checkbox" name="user_ids[]" value="<?php echo esc_attr($user_id); ?>" class="user-checkbox aidunite-admin-checkbox__input" aria-label="<?php echo esc_attr('ユーザーID ' . $user_id . ' を選択'); ?>">
            <span class="aidunite-admin-checkbox__box" aria-hidden="true"></span>
          </label>
        </td>
        <td class="col-no"><?php echo (int) $row_no; ?></td>
        <td><?php echo esc_html($user_id); ?></td>
        <td><?php echo esc_html($display_name_common); ?></td>
        <td><?php echo esc_html($user->user_email); ?></td>
        <td><?php echo esc_html($team_id_display); ?></td>
        <td><?php echo esc_html($family_id ?: '—'); ?></td>
        <td><?php echo esc_html($reg_date ?: $user->user_registered); ?></td>
        <td><?php echo esc_html($months_display); ?></td>
        <td><span class="status-badge <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_text); ?></span></td>
        <td><?php echo esc_html($role ?: '—'); ?></td>
        <td><?php echo esc_html($age_display); ?></td>
        <td><?php echo esc_html($gender_display); ?></td>
        <td>
          <a href="#" class="details-toggle" onclick="toggleDetails(<?php echo $user_id; ?>); return false;">
            <span id="toggle-text-<?php echo $user_id; ?>">詳細表示</span>
          </a>
        </td>
        <td>
          <?php if ((int) $user_id !== (int) $current_user_id) : ?>
          <form method="post" class="delete-user-form" style="display:inline;" onsubmit="return confirm('ユーザーID <?php echo esc_js($user_id); ?>（<?php echo esc_js($display_name_common); ?>）を削除しますか？\nWordPressのユーザーも削除され、取り消せません。');">
            <?php wp_nonce_field('delete_user_' . $user_id); ?>
            <input type="hidden" name="delete_user_id" value="<?php echo esc_attr($user_id); ?>">
            <button type="submit" class="button button-small button-danger">削除</button>
          </form>
          <?php else : ?>
          <span class="text-muted" style="font-size:var(--font-size-xs);">—</span>
          <?php endif; ?>
        </td>
      </tr>
      <tr id="details-<?php echo $user_id; ?>" class="user-details">
        <td colspan="14">
          <div class="meta-info-section">
            <div class="meta-info-section-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:var(--spacing-sm); margin-bottom:var(--spacing-base);">
              <h4 style="margin:0;">📋 詳細（ユーザーID: <?php echo esc_html($user_id); ?>）</h4>
              <button type="button" class="button button-small" onclick="toggleDetails(<?php echo $user_id; ?>); return false;" aria-label="詳細を閉じる">閉じる</button>
            </div>

            <!-- 共通プロフィール（姓・名・年齢・性別）・チームID・世帯ID -->
            <div class="meta-info-row" style="background:rgba(40, 167, 69, 0.08); padding:var(--spacing-base); border-radius:var(--radius-small); margin:var(--spacing-base) 0; border:1px solid rgba(40, 167, 69, 0.2);">
              <div class="meta-key">👤 共通プロフィール・識別:</div>
              <div class="meta-value">
                <span><strong>姓</strong>: <?php echo esc_html($last_name ?: '—'); ?></span>
                <span style="margin-left:var(--spacing-base);"><strong>名</strong>: <?php echo esc_html($first_name ?: '—'); ?></span>
                <span style="margin-left:var(--spacing-base);"><strong>年齢</strong>: <?php echo esc_html($age_display); ?></span>
                <span style="margin-left:var(--spacing-base);"><strong>性別</strong>: <?php echo esc_html($gender_display); ?></span>
                <span style="margin-left:var(--spacing-base);"><strong>チームID</strong>: <?php echo esc_html($team_id ?: '—'); ?></span>
                <span style="margin-left:var(--spacing-base);"><strong>世帯ID</strong>: <?php echo esc_html($family_id ?: '—'); ?></span>
                <span style="margin-left:var(--spacing-base);"><strong>継続月</strong>: <?php echo esc_html($months_display); ?></span>
              </div>
            </div>

            <!-- 登録状態変更（詳細内） -->
            <div class="meta-info-row" style="background:rgba(23, 162, 184, 0.08); padding:var(--spacing-base); border-radius:var(--radius-small); margin:var(--spacing-base) 0;">
              <div class="meta-key">登録状態:</div>
              <div class="meta-value">
                <span class="status-badge <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_text); ?></span>
                <form method="post" class="edit-meta-form" style="display:inline-flex; gap:var(--spacing-sm); align-items:center; margin-left:var(--spacing-sm);">
                  <?php wp_nonce_field('edit_status_' . $user_id); ?>
                  <input type="hidden" name="edit_status_user_id" value="<?php echo esc_attr($user_id); ?>">
                  <select name="new_registration_status" style="padding:var(--spacing-xs) var(--spacing-sm); border:1px solid var(--border-color); border-radius:var(--radius-small);">
                    <option value="">(未設定)</option>
                    <option value="pending" <?php selected($reg_status, 'pending'); ?>>pending</option>
                    <option value="accepted" <?php selected($reg_status, 'accepted'); ?>>accepted</option>
                  </select>
                  <button type="submit" class="button button-small">登録状態を変更</button>
                </form>
              </div>
            </div>

            <!-- ロール変更（詳細内） -->
            <div class="meta-info-row" style="background:rgba(23, 162, 184, 0.08); padding:var(--spacing-base); border-radius:var(--radius-small); margin:var(--spacing-base) 0;">
              <div class="meta-key">ロール:</div>
              <div class="meta-value">
                <?php echo esc_html($role ?: '(未設定)'); ?>
                <form method="post" class="edit-meta-form" style="display:inline-flex; gap:var(--spacing-sm); align-items:center; margin-left:var(--spacing-sm);">
                  <?php wp_nonce_field('edit_role_' . $user_id); ?>
                  <input type="hidden" name="edit_role_user_id" value="<?php echo esc_attr($user_id); ?>">
                  <select name="new_aidunite_role" style="padding:var(--spacing-xs) var(--spacing-sm); border:1px solid var(--border-color); border-radius:var(--radius-small);">
                    <?php foreach ($aidunite_roles as $r): ?>
                      <option value="<?php echo esc_attr($r); ?>" <?php selected($role, $r); ?>><?php echo esc_html($r); ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" class="button button-small">ロールを変更</button>
                </form>
              </div>
            </div>

            <div class="meta-info-row">
              <div class="meta-key">registration_token:</div>
              <div class="meta-value <?php echo $has_token ? '' : 'empty'; ?>">
                <?php echo $has_token ? 'あり（' . esc_html(substr($meta['registration_token'][0], 0, 20)) . '...）' : '(なし)'; ?>
                <?php if ($has_token): ?>
                <form method="post" class="edit-meta-form">
                  <?php wp_nonce_field('edit_meta_' . $user_id); ?>
                  <input type="hidden" name="edit_meta_user_id" value="<?php echo esc_attr($user_id); ?>">
                  <input type="hidden" name="meta_key" value="registration_token">
                  <input type="hidden" name="meta_value" value="">
                  <button type="submit" class="button button-small" onclick="return confirm('トークンを削除しますか？');">削除</button>
                </form>
                <?php endif; ?>
              </div>
            </div>

            <div class="meta-info-row">
              <div class="meta-key">registration_token_time:</div>
              <div class="meta-value <?php echo $token_time ? '' : 'empty'; ?>">
                <?php
                if ($token_time) {
                    $token_date = date('Y-m-d H:i:s', $token_time);
                    $expired = (time() - $token_time > 86400);
                    echo esc_html($token_date) . ($expired ? ' <span style="color:red;">(期限切れ)</span>' : ' <span style="color:green;">(有効)</span>');
                } else {
                    echo '(なし)';
                }
                ?>
                <?php if ($token_time): ?>
                <form method="post" class="edit-meta-form">
                  <?php wp_nonce_field('edit_meta_' . $user_id); ?>
                  <input type="hidden" name="edit_meta_user_id" value="<?php echo esc_attr($user_id); ?>">
                  <input type="hidden" name="meta_key" value="registration_token_time">
                  <input type="hidden" name="meta_value" value="">
                  <button type="submit" class="button button-small" onclick="return confirm('トークン時間を削除しますか？');">削除</button>
                </form>
                <?php endif; ?>
              </div>
            </div>

            <div class="meta-info-row">
              <div class="meta-key">registration_date:</div>
              <div class="meta-value"><?php echo esc_html($reg_date ?: '(未設定)'); ?></div>
            </div>

            <div class="meta-info-row">
              <div class="meta-key">aidunite_role:</div>
              <div class="meta-value"><?php echo esc_html($role ?: '(未設定)'); ?></div>
            </div>

            <?php require get_template_directory() . '/template-parts/admin-user-multi-team-panel.php'; ?>

            <div class="meta-info-row" style="background:rgba(23, 162, 184, 0.1); padding:var(--spacing-base); border-radius:var(--radius-small); margin:var(--spacing-base) 0; border:2px solid rgba(23, 162, 184, 0.2);">
              <div class="meta-key">🏀 チームID設定（レガシー・単一）:</div>
              <div class="meta-value">
                <div style="margin-bottom:10px;">
                  <strong>現在のチームID:</strong>
                  <?php
                  if (!empty($team_id)) {
                      $team_post = get_post($team_id);
                      if ($team_post && $team_post->post_type === 'team') {
                          echo '<span style="padding:var(--spacing-xs) var(--spacing-sm); background:rgba(40, 167, 69, 0.1); color:var(--success-color); border-radius:var(--radius-small); font-weight:bold;">' . esc_html($team_id) . ' - ' . esc_html($team_post->post_title) . '</span>';
                      } else {
                          echo '<span style="padding:var(--spacing-xs) var(--spacing-sm); background:rgba(220, 53, 69, 0.1); color:var(--danger-color); border-radius:var(--radius-small); font-weight:bold;">' . esc_html($team_id) . ' (チームが見つかりません)</span>';
                      }
                  } else {
                      echo '<span style="color:var(--text-muted);">(未設定)</span>';
                  }
                  ?>
                </div>
                <form method="post" class="edit-meta-form" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                  <?php wp_nonce_field('edit_team_id_' . $user_id); ?>
                  <input type="hidden" name="edit_team_id_user_id" value="<?php echo esc_attr($user_id); ?>">
                  <label for="team_id_<?php echo $user_id; ?>" style="font-weight:bold;">チームID:</label>
                  <input type="number" name="team_id" id="team_id_<?php echo $user_id; ?>" value="<?php echo esc_attr($team_id); ?>" placeholder="チームIDを入力" style="padding:var(--spacing-sm) var(--spacing-base); border:1px solid var(--border-color); border-radius:var(--radius-small); width:150px;">
                  <button type="submit" class="button button-primary">設定</button>
                </form>
                <div style="font-size:var(--font-size-xs); color:var(--text-secondary); margin-top:var(--spacing-xs);">
                  ※ チームIDを設定すると、プラン設定が可能になります。チームIDを削除する場合は空欄にして設定ボタンをクリックしてください。
                </div>
              </div>
            </div>

            <!-- プラン設定セクション -->
            <?php if (!empty($team_id)): ?>
            <div class="meta-info-row" style="background:rgba(23, 162, 184, 0.1); padding:var(--spacing-base); border-radius:var(--radius-small); margin:var(--spacing-base) 0; border:2px solid rgba(23, 162, 184, 0.2);">
              <div class="meta-key">💰 プラン設定:</div>
              <div class="meta-value">
                <div style="margin-bottom:10px;">
                  <strong>現在のプラン:</strong>
                  <?php
                  if (!empty($selected_plan_id)) {
                      $plan_info = null;
                      foreach ($available_plans as $plan) {
                          if ($plan['id'] === $selected_plan_id) {
                              $plan_info = $plan;
                              break;
                          }
                      }
                      if ($plan_info) {
                          echo esc_html($plan_info['name'] ?? $selected_plan_id) . ' (' . esc_html($selected_plan_id) . ')';
                      } else {
                          echo esc_html($selected_plan_id);
                      }
                  } else {
                      echo '<span style="color:var(--text-muted);">(未設定)</span>';
                  }
                  ?>
                </div>
                <form method="post" class="edit-meta-form" style="display:flex; flex-direction:column; gap:var(--spacing-sm);">
                  <?php wp_nonce_field('edit_plan_' . $user_id); ?>
                  <input type="hidden" name="edit_plan_user_id" value="<?php echo esc_attr($user_id); ?>">
                  <input type="hidden" name="edit_plan_team_id" value="<?php echo esc_attr($team_id); ?>">
                  <div style="display:flex; gap:var(--spacing-sm); align-items:center; flex-wrap:wrap;">
                    <label for="selected_plan_id_<?php echo $user_id; ?>" style="font-weight:bold;">プラン選択:</label>
                    <select name="selected_plan_id" id="selected_plan_id_<?php echo $user_id; ?>" style="padding:var(--spacing-sm) var(--spacing-base); border:1px solid var(--border-color); border-radius:var(--radius-small); min-width:200px;">
                      <option value="">(プランを削除)</option>
                      <?php foreach ($available_plans as $plan): ?>
                        <option value="<?php echo esc_attr($plan['id']); ?>" <?php selected($selected_plan_id, $plan['id']); ?>>
                          <?php echo esc_html($plan['name'] ?? $plan['id']); ?> (<?php echo esc_html($plan['id']); ?>)
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <button type="submit" class="button button-primary">プランを設定</button>
                  </div>
                  <div style="font-size:var(--font-size-xs); color:var(--text-secondary);">
                    ※ プランを設定すると、トライアル開始日と支払いステータスも自動的に設定されます。
                  </div>
                </form>
              </div>
            </div>

            <!-- 支払いステータスセクション -->
            <div class="meta-info-row" style="background:rgba(255, 193, 7, 0.1); padding:var(--spacing-base); border-radius:var(--radius-small); margin:var(--spacing-base) 0; border:2px solid var(--warning-color);">
              <div class="meta-key">💳 支払いステータス:</div>
              <div class="meta-value">
                <div style="margin-bottom:10px;">
                  <strong>現在のステータス:</strong>
                  <?php
                  if (!empty($payment_status)) {
                      $status_labels = [
                          'trial' => 'トライアル',
                          'paid' => '支払い済み',
                          'unpaid' => '未払い'
                      ];
                      $status_label = $status_labels[$payment_status] ?? $payment_status;
                      echo '<span style="padding:var(--spacing-xs) var(--spacing-sm); background:rgba(40, 167, 69, 0.1); color:var(--success-color); border-radius:var(--radius-small); font-weight:bold;">' . esc_html($status_label) . ' (' . esc_html($payment_status) . ')</span>';
                  } else {
                      echo '<span style="color:var(--text-muted);">(未設定)</span>';
                  }
                  ?>
                </div>
                <form method="post" class="edit-meta-form" style="display:flex; gap:var(--spacing-sm); align-items:center; flex-wrap:wrap;">
                  <?php wp_nonce_field('edit_payment_status_' . $user_id); ?>
                  <input type="hidden" name="edit_payment_status_user_id" value="<?php echo esc_attr($user_id); ?>">
                  <label for="payment_status_<?php echo $user_id; ?>" style="font-weight:bold;">ステータス:</label>
                  <select name="payment_status" id="payment_status_<?php echo $user_id; ?>" style="padding:var(--spacing-sm) var(--spacing-base); border:1px solid var(--border-color); border-radius:var(--radius-small);">
                    <option value="">(ステータスを削除)</option>
                    <option value="trial" <?php selected($payment_status, 'trial'); ?>>トライアル (trial)</option>
                    <option value="paid" <?php selected($payment_status, 'paid'); ?>>支払い済み (paid)</option>
                    <option value="unpaid" <?php selected($payment_status, 'unpaid'); ?>>未払い (unpaid)</option>
                  </select>
                  <button type="submit" class="button button-primary">ステータスを更新</button>
                </form>
              </div>
            </div>
            <?php else: ?>
            <div class="meta-info-row" style="background:rgba(220, 53, 69, 0.1); padding:var(--spacing-base); border-radius:var(--radius-small); margin:var(--spacing-base) 0; border:2px solid rgba(220, 53, 69, 0.2);">
              <div class="meta-key">💰 プラン設定:</div>
              <div class="meta-value" style="color:var(--danger-color);">
                <strong>チームIDが設定されていないため、プラン設定はできません。</strong>
              </div>
            </div>
            <?php endif; ?>

            <div class="meta-info-row">
              <div class="meta-key">pending_team_id:</div>
              <div class="meta-value"><?php echo esc_html($pending_team_id ?: '(未設定)'); ?></div>
            </div>

            <div class="meta-info-row">
              <div class="meta-key">parent_name:</div>
              <div class="meta-value"><?php echo esc_html($parent_name ?: '(未設定)'); ?></div>
            </div>

            <div class="meta-info-row">
              <div class="meta-key">player_name:</div>
              <div class="meta-value"><?php echo esc_html($player_name ?: '(未設定)'); ?></div>
            </div>

            <div class="meta-info-row">
              <div class="meta-key">WordPressロール:</div>
              <div class="meta-value"><?php echo esc_html(implode(', ', $user->roles)); ?></div>
            </div>

            <div class="meta-info-row">
              <div class="meta-key">user_login:</div>
              <div class="meta-value"><?php echo esc_html($user->user_login); ?></div>
            </div>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php if ($total_pages > 1): ?>
  <nav class="admin-user-pagination" style="margin-top:var(--spacing-base);" aria-label="ページ送り">
    <?php
    $base_bottom = add_query_arg(array_filter(['search' => $search, 'role_filter' => $role_filter, 'status_filter' => $status_filter]), get_permalink());
    echo paginate_links([
        'base' => $base_bottom . '%_%',
        'format' => '?paged=%#%',
        'current' => $current_page,
        'total' => $total_pages,
        'prev_text' => '&laquo; 前へ',
        'next_text' => '次へ &raquo;',
    ]);
    ?>
  </nav>
  <?php endif; ?>
</div>

<script>
(function() {
    const selectAll = document.getElementById('select-all-users');
    const bulkDeleteBtn = document.getElementById('user-bulk-delete-btn');
    const bulkCountEl = document.getElementById('user-bulk-selected-count');

    function getUserCheckboxes() {
        return document.querySelectorAll('.user-checkbox');
    }

    function updateUserBulkUi() {
        const boxes = getUserCheckboxes();
        const checked = document.querySelectorAll('.user-checkbox:checked');
        if (bulkCountEl) {
            bulkCountEl.textContent = checked.length + '件選択';
        }
        if (bulkDeleteBtn) {
            bulkDeleteBtn.disabled = checked.length === 0;
        }
        if (selectAll && boxes.length > 0) {
            selectAll.checked = boxes.length === checked.length;
            selectAll.indeterminate = checked.length > 0 && checked.length < boxes.length;
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            getUserCheckboxes().forEach(function(box) {
                box.checked = selectAll.checked;
            });
            updateUserBulkUi();
        });
    }

    getUserCheckboxes().forEach(function(box) {
        box.addEventListener('change', updateUserBulkUi);
    });

    updateUserBulkUi();

    function injectUserIdsToForm(form) {
        if (!form) {
            return;
        }
        form.querySelectorAll('input.js-user-bulk-hidden-id').forEach(function(el) {
            el.remove();
        });
        document.querySelectorAll('.user-checkbox:checked').forEach(function(box) {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'user_ids[]';
            hidden.value = box.value;
            hidden.className = 'js-user-bulk-hidden-id';
            form.appendChild(hidden);
        });
    }

    const bulkRoleForm = document.getElementById('bulk-actions-form');
    if (bulkRoleForm) {
        bulkRoleForm.addEventListener('submit', function() {
            injectUserIdsToForm(bulkRoleForm);
        });
    }

    const bulkDeleteForm = document.getElementById('bulk-delete-users-form');
    if (bulkDeleteForm) {
        bulkDeleteForm.addEventListener('submit', function() {
            injectUserIdsToForm(bulkDeleteForm);
        });
    }
})();

// 一括操作の確認
function confirmBulkAction(action) {
    const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');
    if (checkedBoxes.length === 0) {
        if (typeof showToastNotification !== 'undefined') {
            showToastNotification('操作対象のユーザーを選択してください。', 'warning');
        } else {
            alert('操作対象のユーザーを選択してください。');
        }
        return false;
    }

    if (action === '削除') {
        return confirm('選択された ' + checkedBoxes.length + ' 件のユーザーを削除しますか？\nこの操作は取り消せません。');
    } else if (action === 'ロール変更') {
        const bulkRole = document.getElementById('bulk_role').value;
        if (!bulkRole) {
            if (typeof showToastNotification !== 'undefined') {
                showToastNotification('ロールを選択してください。', 'warning');
            } else {
                alert('ロールを選択してください。');
            }
            return false;
        }
        return confirm('選択された ' + checkedBoxes.length + ' 件のユーザーのロールを ' + bulkRole + ' に変更しますか？');
    }

    return true;
}

// 詳細表示のトグル
function toggleDetails(userId) {
    const details = document.getElementById('details-' + userId);
    const toggleText = document.getElementById('toggle-text-' + userId);

    if (details.classList.contains('active')) {
        details.classList.remove('active');
        toggleText.textContent = '詳細表示';
    } else {
        details.classList.add('active');
        toggleText.textContent = '閉じる';
    }
}
</script>

<?php get_footer(); ?>
