<?php
/**
 * 管理画面メニュー設定
 */

// 管理画面メニューに登録完了ページプレビューを追加
function add_registration_preview_menu() {
    add_menu_page(
        '登録完了ページプレビュー', // ページタイトル
        '登録プレビュー', // メニュータイトル
        'manage_options', // 必要な権限
        'registration-preview', // メニュースラッグ
        'registration_preview_page', // コールバック関数
        'dashicons-visibility', // アイコン
        30 // 位置
    );
}
add_action('admin_menu', 'add_registration_preview_menu');

// 登録完了ページプレビューページの表示
function registration_preview_page() {
    ?>
    <div class="wrap">
        <h1>登録完了ページプレビュー</h1>
        <p>以下のリンクから各登録完了ページのプレビューを確認できます。</p>

        <div class="registration-preview-links">
            <div class="preview-link-card">
                <h3>📧 仮登録完了ページ</h3>
                <p>ユーザーが仮登録を完了した際に表示されるページ</p>
                <a href="<?php echo home_url('/registration-preview/?preview=pending'); ?>"
                   class="button button-primary" target="_blank">
                    <span class="dashicons dashicons-external"></span>
                    プレビューを開く
                </a>
            </div>

            <div class="preview-link-card">
                <h3>🎊 本登録完了ページ</h3>
                <p>ユーザーが本登録を完了した際に表示されるページ</p>
                <a href="<?php echo home_url('/registration-preview/?preview=success'); ?>"
                   class="button button-primary" target="_blank">
                    <span class="dashicons dashicons-external"></span>
                    プレビューを開く
                </a>
            </div>

            <div class="preview-link-card">
                <h3>⚠️ エラーページ</h3>
                <p>リンクが無効または期限切れの場合に表示されるページ</p>
                <a href="<?php echo home_url('/registration-preview/?preview=error'); ?>"
                   class="button button-primary" target="_blank">
                    <span class="dashicons dashicons-external"></span>
                    プレビューを開く
                </a>
            </div>

            <div class="preview-link-card">
                <h3>🔐 ログインページ</h3>
                <p>ユーザーがログインする際に表示されるページ</p>
                <a href="<?php echo home_url('/login/'); ?>"
                   class="button button-primary" target="_blank">
                    <span class="dashicons dashicons-external"></span>
                    プレビューを開く
                </a>
            </div>

            <div class="preview-link-card">
                <h3>🎨 全ページプレビュー</h3>
                <p>全てのページを切り替えて確認できるプレビューページ</p>
                <a href="<?php echo home_url('/registration-preview/'); ?>"
                   class="button button-secondary" target="_blank">
                    <span class="dashicons dashicons-admin-appearance"></span>
                    プレビューページを開く
                </a>
            </div>
        </div>

        <div class="preview-info">
            <h3>📋 プレビューページについて</h3>
            <ul>
                <li>これらのページは管理者のみアクセス可能です</li>
                <li>実際のユーザー登録は行われません</li>
                <li>デザインとアニメーションの確認ができます</li>
                <li>レスポンシブデザインも確認できます</li>
            </ul>
        </div>
    </div>
    <?php
}

/**
 * 管理者向けチャット管理ページ
 */
function aidunite_add_chat_management_menu() {
    add_menu_page(
        'チャット管理',
        'チャット管理',
        'manage_options',
        'aidunite-chat-management',
        'aidunite_render_chat_management_page',
        'dashicons-format-chat',
        31
    );
}
add_action('admin_menu', 'aidunite_add_chat_management_menu');

/**
 * チャット管理画面（一覧・閲覧・重複削除）
 */
function aidunite_render_chat_management_page() {
    if (!current_user_can('manage_options')) {
        wp_die('権限がありません');
    }

    $current_user = wp_get_current_user();
    $unlock_meta_key = 'aidunite_chat_mgmt_unlocked_until';
    $unlocked_until = (int) get_user_meta($current_user->ID, $unlock_meta_key, true);
    $is_unlocked = ($unlocked_until > time());

    global $wpdb;
    $rooms_table = $wpdb->prefix . 'chat_rooms';
    $messages_table = $wpdb->prefix . 'chat_messages';
    $room_columns = $wpdb->get_col("SHOW COLUMNS FROM {$rooms_table}", 0);
    $has_schedule_id_col = is_array($room_columns) && in_array('schedule_id', $room_columns, true);
    $has_team_a_id_col = is_array($room_columns) && in_array('team_a_id', $room_columns, true);
    $has_team_b_id_col = is_array($room_columns) && in_array('team_b_id', $room_columns, true);

    $notice = '';
    $error = '';

    // 管理者の再認証（15分有効）
    if (isset($_POST['aidunite_chat_unlock'])) {
        check_admin_referer('aidunite_chat_unlock_action', '_aidunite_chat_unlock_nonce');
        $password = isset($_POST['admin_password']) ? (string) wp_unslash($_POST['admin_password']) : '';
        if ($password !== '' && wp_check_password($password, $current_user->user_pass, $current_user->ID)) {
            $unlocked_until = time() + (15 * 60);
            update_user_meta($current_user->ID, $unlock_meta_key, $unlocked_until);
            $is_unlocked = true;
            $notice = '再認証に成功しました。15分間、チャット一覧を閲覧できます。';
        } else {
            $error = 'パスワードが正しくありません。';
        }
    }

    if (isset($_POST['aidunite_chat_lock'])) {
        check_admin_referer('aidunite_chat_unlock_action', '_aidunite_chat_unlock_nonce');
        delete_user_meta($current_user->ID, $unlock_meta_key);
        $unlocked_until = 0;
        $is_unlocked = false;
        $notice = 'チャット管理をロックしました。';
    }

    if (!$is_unlocked) {
        ?>
        <div class="wrap">
            <h1>チャット管理（管理者）</h1>
            <p>プライバシー保護のため、表示前に管理者パスワードを再入力してください。</p>
            <?php if ($notice): ?>
                <div class="notice notice-success"><p><?php echo esc_html($notice); ?></p></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>
            <div class="card" style="max-width:520px; padding:16px;">
                <form method="post">
                    <?php wp_nonce_field('aidunite_chat_unlock_action', '_aidunite_chat_unlock_nonce'); ?>
                    <p>
                        <label for="admin_password"><strong>管理者パスワード</strong></label><br>
                        <input id="admin_password" type="password" name="admin_password" required style="width:100%; max-width:360px;">
                    </p>
                    <p>
                        <button type="submit" name="aidunite_chat_unlock" class="button button-primary">チャット一覧を表示</button>
                    </p>
                </form>
            </div>
        </div>
        <?php
        return;
    }

    // 重複一括削除（物理削除）
    if (isset($_POST['aidunite_dedupe_rooms'])) {
        check_admin_referer('aidunite_chat_mgmt_action', '_aidunite_chat_nonce');
        $keep_room_id = isset($_POST['keep_room_id']) ? intval($_POST['keep_room_id']) : 0;
        $force_ids_raw = isset($_POST['force_delete_room_ids']) ? sanitize_text_field(wp_unslash($_POST['force_delete_room_ids'])) : '';
        $force_delete_ids = [];
        if ($force_ids_raw !== '') {
            $parts = preg_split('/[\s,]+/', $force_ids_raw);
            foreach ($parts as $part) {
                $id = intval($part);
                if ($id > 0) {
                    $force_delete_ids[] = $id;
                }
            }
            $force_delete_ids = array_values(array_unique($force_delete_ids));
        }

        if (function_exists('aidunite_delete_duplicate_match_rooms_permanently')) {
            $report = aidunite_delete_duplicate_match_rooms_permanently($keep_room_id);
            if (!empty($force_delete_ids) && function_exists('aidunite_delete_chat_room_permanently')) {
                foreach ($force_delete_ids as $room_id) {
                    if ($keep_room_id > 0 && $room_id === $keep_room_id) {
                        continue;
                    }
                    $deleted = aidunite_delete_chat_room_permanently($room_id);
                    if (is_wp_error($deleted)) {
                        $report['errors'][] = 'room_id=' . $room_id . ': ' . $deleted->get_error_message();
                    } elseif ($deleted) {
                        $report['deleted_rooms'][] = $room_id;
                    }
                }
                $report['deleted_rooms'] = array_values(array_unique(array_map('intval', $report['deleted_rooms'])));
            }

            $deleted_count = isset($report['deleted_rooms']) ? count($report['deleted_rooms']) : 0;
            $notice = '重複削除を実行しました。削除件数: ' . $deleted_count;
            if (!empty($report['errors'])) {
                $error = '一部削除でエラー: ' . implode(' / ', array_map('esc_html', $report['errors']));
            }
        } else {
            $error = '重複削除機能が利用できません。';
        }
    }

    // 個別完全削除
    if (isset($_POST['aidunite_delete_single_room'])) {
        check_admin_referer('aidunite_chat_mgmt_action', '_aidunite_chat_nonce');
        $delete_room_id = isset($_POST['delete_room_id']) ? intval($_POST['delete_room_id']) : 0;
        if ($delete_room_id > 0 && function_exists('aidunite_delete_chat_room_permanently')) {
            $deleted = aidunite_delete_chat_room_permanently($delete_room_id);
            if (is_wp_error($deleted)) {
                $error = 'room_id=' . $delete_room_id . ' の削除に失敗: ' . $deleted->get_error_message();
            } elseif ($deleted) {
                $notice = 'room_id=' . $delete_room_id . ' を完全削除しました。';
            } else {
                $error = 'room_id=' . $delete_room_id . ' が見つかりません。';
            }
        }
    }

    // 選択行の一括完全削除
    if (isset($_POST['aidunite_delete_selected_rooms'])) {
        check_admin_referer('aidunite_chat_mgmt_action', '_aidunite_chat_nonce');
        $selected_room_ids = isset($_POST['selected_room_ids']) && is_array($_POST['selected_room_ids'])
            ? array_values(array_unique(array_filter(array_map('intval', wp_unslash($_POST['selected_room_ids'])))))
            : [];
        if (empty($selected_room_ids)) {
            $error = '削除対象が選択されていません。';
        } elseif (!function_exists('aidunite_delete_chat_room_permanently')) {
            $error = '削除機能が利用できません。';
        } else {
            $deleted_count = 0;
            $delete_errors = [];
            foreach ($selected_room_ids as $room_id) {
                $deleted = aidunite_delete_chat_room_permanently($room_id);
                if (is_wp_error($deleted)) {
                    $delete_errors[] = 'room_id=' . $room_id . ': ' . $deleted->get_error_message();
                } elseif ($deleted) {
                    $deleted_count++;
                }
            }
            $notice = $deleted_count . '件のチャットを完全削除しました。';
            if (!empty($delete_errors)) {
                $error = '一部削除でエラー: ' . implode(' / ', array_map('esc_html', $delete_errors));
            }
        }
    }

    // ルーム再表示（ユーザー非表示メタから指定 room_id を除外）
    if (isset($_POST['aidunite_unhide_room'])) {
        check_admin_referer('aidunite_chat_mgmt_action', '_aidunite_chat_nonce');
        $unhide_room_id = isset($_POST['unhide_room_id']) ? intval($_POST['unhide_room_id']) : 0;
        if ($unhide_room_id <= 0) {
            $error = '再表示する room_id を入力してください。';
        } else {
            $users = get_users([
                'meta_key' => 'aidunite_hidden_chat_rooms',
                'fields' => 'ID',
                'number' => -1,
            ]);
            $updated_users = 0;
            foreach ($users as $uid) {
                $hidden = get_user_meta($uid, 'aidunite_hidden_chat_rooms', true);
                if (!is_array($hidden)) {
                    if (is_string($hidden) && $hidden !== '') {
                        $hidden = explode(',', $hidden);
                    } else {
                        $hidden = [];
                    }
                }
                $hidden = array_values(array_unique(array_filter(array_map('intval', $hidden))));
                $new_hidden = array_values(array_filter($hidden, function ($id) use ($unhide_room_id) {
                    return (int) $id !== $unhide_room_id;
                }));
                if ($new_hidden !== $hidden) {
                    update_user_meta($uid, 'aidunite_hidden_chat_rooms', $new_hidden);
                    $updated_users++;
                }
            }
            $notice = 'room_id=' . $unhide_room_id . ' を再表示しました（更新ユーザー数: ' . $updated_users . '）。';
        }
    }

    // 検索条件
    $status_filter = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
    $type_filter = isset($_GET['room_type']) ? sanitize_text_field(wp_unslash($_GET['room_type'])) : '';
    $keyword = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
    $view_room_id = isset($_GET['view_room']) ? intval($_GET['view_room']) : 0;
    $valid_statuses = ['active', 'completed', 'archived'];
    $valid_room_types = ['match', 'group', 'team', 'direct', 'message_thread', 'system'];
    if (!in_array($status_filter, $valid_statuses, true)) {
        $status_filter = '';
    }
    if (!in_array($type_filter, $valid_room_types, true)) {
        $type_filter = '';
    }

    $where = ['1=1'];
    $params = [];
    if ($status_filter !== '') {
        $where[] = 'r.status = %s';
        $params[] = $status_filter;
    }
    if ($type_filter !== '') {
        $where[] = 'r.room_type = %s';
        $params[] = $type_filter;
    }
    if ($keyword !== '') {
        $where[] = '(r.name LIKE %s OR CAST(r.id AS CHAR) = %s OR CAST(r.match_id AS CHAR) = %s)';
        $like = '%' . $wpdb->esc_like($keyword) . '%';
        $params[] = $like;
        $params[] = $keyword;
        $params[] = $keyword;
    }

    $page_no = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 50;
    if (!in_array($per_page, [20, 50, 100, 200], true)) {
        $per_page = 50;
    }
    $offset = ($page_no - 1) * $per_page;

    $base_where = implode(' AND ', $where);
    $count_sql = "SELECT COUNT(*) FROM {$rooms_table} r WHERE {$base_where}";
    $total_count = (int) (empty($params) ? $wpdb->get_var($count_sql) : $wpdb->get_var($wpdb->prepare($count_sql, ...$params)));
    $total_pages = max(1, (int) ceil($total_count / $per_page));
    if ($page_no > $total_pages) {
        $page_no = $total_pages;
        $offset = ($page_no - 1) * $per_page;
    }

    $sql = "SELECT
                r.id,
                r.room_type,
                r.name,
                r.status,
                r.match_id,
                " . ($has_schedule_id_col ? 'r.schedule_id' : 'NULL AS schedule_id') . ",
                " . ($has_team_a_id_col ? 'r.team_a_id' : 'NULL AS team_a_id') . ",
                " . ($has_team_b_id_col ? 'r.team_b_id' : 'NULL AS team_b_id') . ",
                r.created_at,
                (SELECT COUNT(*) FROM {$messages_table} m WHERE m.room_id = r.id) AS message_count,
                (SELECT m2.content FROM {$messages_table} m2 WHERE m2.room_id = r.id ORDER BY m2.id DESC LIMIT 1) AS latest_message
            FROM {$rooms_table} r
            WHERE {$base_where}
            ORDER BY r.id DESC
            LIMIT %d OFFSET %d";
    $rooms_query_params = array_merge($params, [$per_page, $offset]);
    $rooms = $wpdb->get_results($wpdb->prepare($sql, ...$rooms_query_params), ARRAY_A);

    $messages = [];
    if ($view_room_id > 0) {
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT id, sender_id, message_type, content, created_at
             FROM {$messages_table}
             WHERE room_id = %d
             ORDER BY id DESC
             LIMIT 200",
            $view_room_id
        ), ARRAY_A);
    }
    ?>
    <div class="wrap">
        <h1>チャット管理（管理者）</h1>
        <p>管理者はチャット履歴を閲覧・削除できます。個人情報を含む可能性があるため、運用ポリシーに基づいて最小限で実行してください。</p>
        <form method="post" style="margin:8px 0 16px;">
            <?php wp_nonce_field('aidunite_chat_unlock_action', '_aidunite_chat_unlock_nonce'); ?>
            <button type="submit" name="aidunite_chat_lock" class="button">ロックする</button>
            <span style="margin-left:8px; color:#666;">解錠期限: <?php echo esc_html(date_i18n('Y-m-d H:i:s', $unlocked_until)); ?></span>
        </form>

        <?php if ($notice): ?>
            <div class="notice notice-success"><p><?php echo esc_html($notice); ?></p></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
        <?php endif; ?>

        <h2 style="margin:16px 0 8px;">重複一括削除（完全削除）</h2>
        <div style="background:#fff; border:1px solid #dcdcde; padding:12px; margin-bottom:16px;">
            <form method="post" data-aidunite-confirm="重複ルームを完全削除します。実行しますか？" data-aidunite-confirm-label="実行する">
                <?php wp_nonce_field('aidunite_chat_mgmt_action', '_aidunite_chat_nonce'); ?>
                <p>
                    <label>残す room_id（任意）</label><br>
                    <input type="number" name="keep_room_id" min="0" placeholder="例: 354" style="width:180px;">
                </p>
                <p>
                    <label>強制削除 room_id（カンマ/スペース区切り）</label><br>
                    <input type="text" name="force_delete_room_ids" placeholder="例: 338,339" style="width:360px;">
                </p>
                <p>
                    <button type="submit" name="aidunite_dedupe_rooms" class="button button-primary">重複を削除実行</button>
                </p>
            </form>
        </div>

        <h2 style="margin:16px 0 8px;">非表示ルームの再表示</h2>
        <div style="background:#fff; border:1px solid #dcdcde; padding:12px; margin-bottom:16px;">
            <form method="post">
                <?php wp_nonce_field('aidunite_chat_mgmt_action', '_aidunite_chat_nonce'); ?>
                <p>
                    <label>再表示する room_id</label><br>
                    <input type="number" name="unhide_room_id" min="1" placeholder="例: 356" style="width:180px;">
                    <button type="submit" name="aidunite_unhide_room" class="button">再表示実行</button>
                </p>
                <p style="margin:0; color:#666;">各ユーザーの非表示リストから指定 room_id を除外します（データは削除しません）。</p>
            </form>
        </div>

        <h2 style="margin:16px 0 8px;">検索</h2>
        <div style="background:#fff; border:1px solid #dcdcde; padding:12px; margin-bottom:16px;">
            <form method="get">
                <input type="hidden" name="page" value="aidunite-chat-management">
                <label>ステータス</label>
                <select name="status">
                    <option value="">すべて</option>
                    <?php foreach ($valid_statuses as $status): ?>
                        <option value="<?php echo esc_attr($status); ?>" <?php selected($status_filter, $status); ?>><?php echo esc_html($status); ?></option>
                    <?php endforeach; ?>
                </select>
                <label>ルーム種別</label>
                <select name="room_type">
                    <option value="">すべて</option>
                    <?php foreach ($valid_room_types as $type): ?>
                        <option value="<?php echo esc_attr($type); ?>" <?php selected($type_filter, $type); ?>><?php echo esc_html($type); ?></option>
                    <?php endforeach; ?>
                </select>
                <label>キーワード/ID</label>
                <input type="text" name="s" value="<?php echo esc_attr($keyword); ?>" placeholder="room_id / match_id / 名称">
                <label>件数</label>
                <select name="per_page">
                    <?php foreach ([20, 50, 100, 200] as $pp): ?>
                        <option value="<?php echo (int) $pp; ?>" <?php selected($per_page, $pp); ?>><?php echo (int) $pp; ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="button">絞り込む</button>
                <a class="button" href="<?php echo esc_url(add_query_arg(['page' => 'aidunite-chat-management', 'status' => '', 'room_type' => '', 's' => '', 'view_room' => '', 'paged' => 1, 'per_page' => 50], admin_url('admin.php'))); ?>">クリア</a>
            </form>
        </div>

        <h2 style="margin:12px 0 8px;">チャット一覧（表形式）</h2>
        <p style="margin:0 0 8px; color:#50575e;">全 <?php echo (int) $total_count; ?> 件 / <?php echo (int) $total_pages; ?> ページ（現在 <?php echo (int) $page_no; ?> ページ）</p>
        <form method="post" data-aidunite-confirm="選択したチャットを完全削除します。よろしいですか？" data-aidunite-confirm-label="削除する">
        <?php wp_nonce_field('aidunite_chat_mgmt_action', '_aidunite_chat_nonce'); ?>
        <div style="margin:8px 0;">
            <button type="submit" name="aidunite_delete_selected_rooms" class="button button-secondary">選択したチャットを完全削除</button>
        </div>
        <table class="wp-list-table widefat fixed striped table-view-list aidunite-chat-admin-table" style="table-layout:auto;">
            <thead>
                <tr>
                    <th class="col-check">✓<br><input type="checkbox" id="select_all_rooms" aria-label="全選択"></th>
                    <th style="width:60px;">No</th>
                    <th>ID</th>
                    <th>種別</th>
                    <th>状態</th>
                    <th>名称</th>
                    <th>match_id</th>
                    <th>schedule_id</th>
                    <th>team_a/b</th>
                    <th>件数</th>
                    <th>最新メッセージ</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rooms)): ?>
                    <?php
                    $total_rooms = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$rooms_table}");
                    $type_breakdown = $wpdb->get_results("SELECT room_type, status, COUNT(*) AS c FROM {$rooms_table} GROUP BY room_type, status ORDER BY room_type, status", ARRAY_A);
                    ?>
                    <tr><td colspan="12">該当チャットなし（現在フィルタ: status=<?php echo esc_html($status_filter ?: 'ALL'); ?>, room_type=<?php echo esc_html($type_filter ?: 'ALL'); ?>, keyword=<?php echo esc_html($keyword ?: ''); ?>）</td></tr>
                    <tr><td colspan="12">chat_rooms 総件数: <?php echo (int) $total_rooms; ?></td></tr>
                    <?php if (!empty($type_breakdown)): ?>
                        <tr>
                            <td colspan="12">
                                <?php foreach ($type_breakdown as $row): ?>
                                    <span style="display:inline-block; margin-right:12px;">
                                        <?php echo esc_html($row['room_type']); ?>/<?php echo esc_html($row['status']); ?>: <?php echo (int) $row['c']; ?>
                                    </span>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php else: ?>
                    <?php $row_no = $offset + 1; ?>
                    <?php foreach ($rooms as $room): ?>
                        <tr>
                            <td class="col-check"><input type="checkbox" name="selected_room_ids[]" value="<?php echo (int) $room['id']; ?>" class="room-select-checkbox" aria-label="room_id <?php echo (int) $room['id']; ?>"></td>
                            <td><?php echo (int) $row_no; ?></td>
                            <td><?php echo (int) $room['id']; ?></td>
                            <td><?php echo esc_html($room['room_type']); ?></td>
                            <td><?php echo esc_html($room['status']); ?></td>
                            <td><?php echo esc_html((string) $room['name']); ?></td>
                            <td><?php echo (int) $room['match_id']; ?></td>
                            <td><?php echo (int) $room['schedule_id']; ?></td>
                            <td><?php echo (int) $room['team_a_id']; ?> / <?php echo (int) $room['team_b_id']; ?></td>
                            <td><?php echo (int) $room['message_count']; ?></td>
                            <td><?php echo esc_html(mb_strimwidth((string) ($room['latest_message'] ?? ''), 0, 80, '...')); ?></td>
                            <td>
                                <a class="button button-small" href="<?php echo esc_url(add_query_arg(['page' => 'aidunite-chat-management', 'view_room' => (int) $room['id'], 'status' => $status_filter, 'room_type' => $type_filter, 's' => $keyword, 'paged' => $page_no, 'per_page' => $per_page], admin_url('admin.php'))); ?>">閲覧</a>
                                <form method="post" style="display:inline;" data-aidunite-confirm="room_id=<?php echo (int) $room['id']; ?> を完全削除します。よろしいですか？" data-aidunite-confirm-label="削除する">
                                    <?php wp_nonce_field('aidunite_chat_mgmt_action', '_aidunite_chat_nonce'); ?>
                                    <input type="hidden" name="delete_room_id" value="<?php echo (int) $room['id']; ?>">
                                    <button type="submit" name="aidunite_delete_single_room" class="button button-small">完全削除</button>
                                </form>
                            </td>
                        </tr>
                        <?php $row_no++; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        </form>
        <?php if ($total_pages > 1): ?>
            <div class="tablenav" style="margin-top:8px;">
                <div class="tablenav-pages">
                    <?php
                    $base_args = [
                        'page' => 'aidunite-chat-management',
                        'status' => $status_filter,
                        'room_type' => $type_filter,
                        's' => $keyword,
                        'per_page' => $per_page,
                    ];
                    $first_url = esc_url(add_query_arg(array_merge($base_args, ['paged' => 1]), admin_url('admin.php')));
                    $prev_url = esc_url(add_query_arg(array_merge($base_args, ['paged' => max(1, $page_no - 1)]), admin_url('admin.php')));
                    $next_url = esc_url(add_query_arg(array_merge($base_args, ['paged' => min($total_pages, $page_no + 1)]), admin_url('admin.php')));
                    $last_url = esc_url(add_query_arg(array_merge($base_args, ['paged' => $total_pages]), admin_url('admin.php')));
                    ?>
                    <span class="pagination-links">
                        <a class="first-page button <?php echo $page_no <= 1 ? 'disabled' : ''; ?>" href="<?php echo $first_url; ?>">«</a>
                        <a class="prev-page button <?php echo $page_no <= 1 ? 'disabled' : ''; ?>" href="<?php echo $prev_url; ?>">‹</a>
                        <span class="paging-input"><?php echo (int) $page_no; ?> / <span class="total-pages"><?php echo (int) $total_pages; ?></span></span>
                        <a class="next-page button <?php echo $page_no >= $total_pages ? 'disabled' : ''; ?>" href="<?php echo $next_url; ?>">›</a>
                        <a class="last-page button <?php echo $page_no >= $total_pages ? 'disabled' : ''; ?>" href="<?php echo $last_url; ?>">»</a>
                    </span>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($view_room_id > 0): ?>
            <div class="card" style="max-width:none; padding:16px; margin-top:16px;">
                <h2>チャット詳細（room_id=<?php echo (int) $view_room_id; ?>）</h2>
                <p>最新200件を表示します。</p>
                <table class="widefat striped">
                    <thead><tr><th>msg_id</th><th>sender_id</th><th>type</th><th>created_at</th><th>content</th></tr></thead>
                    <tbody>
                        <?php if (empty($messages)): ?>
                            <tr><td colspan="5">メッセージなし</td></tr>
                        <?php else: ?>
                            <?php foreach ($messages as $msg): ?>
                                <tr>
                                    <td><?php echo (int) $msg['id']; ?></td>
                                    <td><?php echo (int) $msg['sender_id']; ?></td>
                                    <td><?php echo esc_html($msg['message_type']); ?></td>
                                    <td><?php echo esc_html($msg['created_at']); ?></td>
                                    <td style="white-space:pre-wrap;"><?php echo esc_html((string) $msg['content']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * 管理画面のインライン資産を外部ファイルへ
 */
function aidunite_enqueue_admin_screen_assets($hook) {
    $theme_dir = get_stylesheet_directory();
    $theme_uri = get_stylesheet_directory_uri();

    if ($hook === 'toplevel_page_registration-preview') {
        $css = $theme_dir . '/assets/css/admin/admin-preview-links.css';
        if (is_readable($css)) {
            wp_enqueue_style(
                'aidunite-admin-preview-links',
                $theme_uri . '/assets/css/admin/admin-preview-links.css',
                [],
                (string) filemtime($css)
            );
        }
    }

    if ($hook === 'toplevel_page_aidunite-chat-management') {
        $css = $theme_dir . '/assets/css/admin/admin-chat-management.css';
        if (is_readable($css)) {
            wp_enqueue_style(
                'aidunite-admin-chat-management',
                $theme_uri . '/assets/css/admin/admin-chat-management.css',
                [],
                (string) filemtime($css)
            );
        }
        $js = $theme_dir . '/assets/js/admin/admin-chat-management.js';
        if (is_readable($js)) {
            wp_enqueue_script(
                'aidunite-admin-chat-management',
                $theme_uri . '/assets/js/admin/admin-chat-management.js',
                [],
                (string) filemtime($js),
                true
            );
        }
    }
}
add_action('admin_enqueue_scripts', 'aidunite_enqueue_admin_screen_assets');
