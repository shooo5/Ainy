<?php
/**
 * メッセージ機能用データベーススキーマ
 * MESSAGE-MVP-DESIGN.md に基づく設計
 */

// データベーステーブル作成
function aidunite_create_messaging_tables() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    // 1. メッセージ掲示板テーブル
    $table_team_messages = $wpdb->prefix . 'team_messages';
    $sql_team_messages = "CREATE TABLE $table_team_messages (
        id BIGINT NOT NULL AUTO_INCREMENT,
        team_id BIGINT NOT NULL,
        match_id BIGINT NULL,
        user_id BIGINT NOT NULL,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        category VARCHAR(50) DEFAULT 'general',
        message_type ENUM('team', 'match') DEFAULT 'team',
        priority VARCHAR(20) DEFAULT 'normal',
        pinned BOOLEAN DEFAULT FALSE,
        status VARCHAR(20) DEFAULT 'published',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_team_created (team_id, created_at),
        INDEX idx_user_team (user_id, team_id),
        INDEX idx_pinned (pinned, created_at),
        INDEX idx_category (category),
        INDEX idx_priority (priority),
        INDEX idx_status (status),
        INDEX idx_match_id (match_id),
        INDEX idx_message_type (message_type)
    ) $charset_collate;";

    // 2. チャットルームテーブル（チームチャット・対戦チャット統合）
    $table_chat_rooms = $wpdb->prefix . 'chat_rooms';
    $sql_chat_rooms = "CREATE TABLE $table_chat_rooms (
        id BIGINT NOT NULL AUTO_INCREMENT,
        room_type ENUM('team', 'match', 'message_thread', 'system', 'group', 'direct') NOT NULL,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        team_id BIGINT NULL,
        match_id BIGINT NULL,
        schedule_id BIGINT NULL,
        team_a_id BIGINT NULL,
        team_b_id BIGINT NULL,
        match_date DATE NULL,
        match_time TIME NULL,
        venue VARCHAR(255) NULL,
        related_message_id BIGINT NULL,
        system_type VARCHAR(50) NULL,
        related_id BIGINT NULL,
        unique_key VARCHAR(255) NULL,
        status ENUM('active', 'completed', 'archived') DEFAULT 'active',
        merged_into_room_id BIGINT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_room_type (room_type),
        INDEX idx_team (team_id),
        INDEX idx_match (match_id),
        INDEX idx_schedule (schedule_id),
        INDEX idx_teams (team_a_id, team_b_id),
        INDEX idx_status (status),
        INDEX idx_related_message (related_message_id),
        INDEX idx_system (system_type, related_id),
        INDEX idx_unique_key (unique_key),
        UNIQUE KEY unique_message_thread (room_type, related_message_id),
        UNIQUE KEY unique_system (room_type, system_type, related_id),
        UNIQUE KEY unique_group_direct (room_type, unique_key)
    ) $charset_collate;";

    // 3. チャットメッセージテーブル（parent_message_id: 返信スレッド用・Google Chat風）
    $table_chat_messages = $wpdb->prefix . 'chat_messages';
    $sql_chat_messages = "CREATE TABLE $table_chat_messages (
        id BIGINT NOT NULL AUTO_INCREMENT,
        room_id BIGINT NOT NULL,
        sender_id BIGINT NOT NULL,
        parent_message_id BIGINT NULL,
        message_type ENUM('text', 'image', 'file', 'system', 'evaluation_request', 'evaluation_completion', 'rematch_suggestion') DEFAULT 'text',
        content TEXT NOT NULL,
        file_url VARCHAR(500),
        is_edited BOOLEAN DEFAULT FALSE,
        edited_at TIMESTAMP NULL,
        is_private BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_room_created (room_id, created_at),
        INDEX idx_sender (sender_id),
        INDEX idx_message_type (message_type),
        INDEX idx_parent (parent_message_id)
    ) $charset_collate;";

    // 3b. チャットメッセージリアクション（吹き出しに紐づく・新規メッセージにしない）
    $table_chat_reactions = $wpdb->prefix . 'chat_message_reactions';
    $sql_chat_reactions = "CREATE TABLE $table_chat_reactions (
        id BIGINT NOT NULL AUTO_INCREMENT,
        message_id BIGINT NOT NULL,
        user_id BIGINT NOT NULL,
        reaction_type VARCHAR(20) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_message_user_reaction (message_id, user_id, reaction_type),
        INDEX idx_message (message_id),
        INDEX idx_user (user_id)
    ) $charset_collate;";

    // 4. 既読管理テーブル
    $table_chat_read_status = $wpdb->prefix . 'chat_read_status';
    $sql_chat_read_status = "CREATE TABLE $table_chat_read_status (
        id BIGINT NOT NULL AUTO_INCREMENT,
        room_id BIGINT NOT NULL,
        user_id BIGINT NOT NULL,
        last_read_message_id BIGINT DEFAULT 0,
        last_read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_room_user (room_id, user_id),
        INDEX idx_user_room (user_id, room_id)
    ) $charset_collate;";

    // 5. チャット参加者テーブル
    $table_chat_participants = $wpdb->prefix . 'chat_participants';
    $sql_chat_participants = "CREATE TABLE $table_chat_participants (
        id BIGINT NOT NULL AUTO_INCREMENT,
        room_id BIGINT NOT NULL,
        user_id BIGINT NOT NULL,
        role ENUM('admin', 'member') DEFAULT 'member',
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_read_at TIMESTAMP NULL,
        PRIMARY KEY (id),
        UNIQUE KEY unique_room_user (room_id, user_id),
        INDEX idx_user (user_id),
        INDEX idx_room (room_id)
    ) $charset_collate;";

    // 6. 試合評価テーブル（非公開）
    $table_match_evaluations = $wpdb->prefix . 'match_evaluations';
    $sql_match_evaluations = "CREATE TABLE $table_match_evaluations (
        id BIGINT NOT NULL AUTO_INCREMENT,
        match_id BIGINT NOT NULL,
        rater_team_id BIGINT NOT NULL,
        rated_team_id BIGINT NOT NULL,
        overall_rating TINYINT NOT NULL,
        would_rematch BOOLEAN DEFAULT NULL,
        feedback TEXT,
        is_private BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_match_teams (match_id, rater_team_id, rated_team_id),
        INDEX idx_rater (rater_team_id),
        INDEX idx_rated (rated_team_id),
        INDEX idx_rating (overall_rating),
        INDEX idx_private (is_private)
    ) $charset_collate;";

    // 7. 再マッチング提案テーブル
    $table_rematch_suggestions = $wpdb->prefix . 'rematch_suggestions';
    $sql_rematch_suggestions = "CREATE TABLE $table_rematch_suggestions (
        id BIGINT NOT NULL AUTO_INCREMENT,
        team_a_id BIGINT NOT NULL,
        team_b_id BIGINT NOT NULL,
        original_match_id BIGINT NOT NULL,
        status ENUM('pending', 'accepted', 'rejected', 'expired') DEFAULT 'pending',
        suggested_date DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_teams (team_a_id, team_b_id),
        INDEX idx_status (status),
        INDEX idx_expires (expires_at)
    ) $charset_collate;";

    // テーブル作成実行
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    dbDelta($sql_team_messages);
    dbDelta($sql_chat_rooms);
    dbDelta($sql_chat_messages);
    dbDelta($sql_chat_reactions);
    dbDelta($sql_chat_read_status);
    dbDelta($sql_chat_participants);
    dbDelta($sql_match_evaluations);
    dbDelta($sql_rematch_suggestions);

    // ファイルアップロード用テーブル作成
    aidunite_create_uploaded_files_table();

    // バージョン情報を保存
    update_option('aidunite_messaging_db_version', '2.0.0');

    // データベースマイグレーション（既存テーブルにカラムを追加）
    aidunite_migrate_chat_rooms_table();
    aidunite_migrate_chat_messages_table();

    // サンプル投稿を追加
    aidunite_create_sample_messages();

    error_log('✅ メッセージ機能用データベーステーブルを作成しました（v2.0.0）');
}

/**
 * チャットルームテーブルのマイグレーション（既存テーブルにカラムを追加）
 */
function aidunite_migrate_chat_rooms_table() {
    global $wpdb;

    $table_chat_rooms = $wpdb->prefix . 'chat_rooms';

    // テーブルが存在するか確認
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_chat_rooms'") === $table_chat_rooms;

    if (!$table_exists) {
        return; // テーブルが存在しない場合は何もしない
    }

    $current_schema_version = get_option('aidunite_chat_rooms_schema_version');
    $target_schema_version  = '2026-05-21';

    // 既存のカラムを確認（バージョン済みでも schedule_id / merged_into 欠落時は続行して修復する）
    $columns = $wpdb->get_col("SHOW COLUMNS FROM $table_chat_rooms LIKE '%'");
    $columns = is_array($columns) ? $columns : [];
    $has_schedule_id_col = in_array('schedule_id', $columns, true);
    $has_merged_into_col = in_array('merged_into_room_id', $columns, true);
    if ($current_schema_version === $target_schema_version && $has_schedule_id_col && $has_merged_into_col) {
        return;
    }

    // 新しいカラムを追加（存在しない場合のみ）
    $new_columns = [
        'schedule_id' => "ALTER TABLE $table_chat_rooms ADD COLUMN schedule_id BIGINT NULL AFTER match_id",
        'merged_into_room_id' => "ALTER TABLE $table_chat_rooms ADD COLUMN merged_into_room_id BIGINT NULL AFTER status",
        'related_message_id' => "ALTER TABLE $table_chat_rooms ADD COLUMN related_message_id BIGINT NULL AFTER venue",
        'system_type' => "ALTER TABLE $table_chat_rooms ADD COLUMN system_type VARCHAR(50) NULL AFTER related_message_id",
        'related_id' => "ALTER TABLE $table_chat_rooms ADD COLUMN related_id BIGINT NULL AFTER system_type",
        'unique_key' => "ALTER TABLE $table_chat_rooms ADD COLUMN unique_key VARCHAR(255) NULL AFTER related_id"
    ];

    foreach ($new_columns as $column_name => $sql) {
        if (!in_array($column_name, $columns, true)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- DDL from fixed strings above.
            $wpdb->query($sql);
        }
    }

    // カラム追加後に再取得（idx_schedule 等の前提）
    $columns = $wpdb->get_col("SHOW COLUMNS FROM $table_chat_rooms LIKE '%'");
    $columns = is_array($columns) ? $columns : [];
    $has_schedule_id_col = in_array('schedule_id', $columns, true);

    // room_type ENUMを拡張（既存の値は保持）
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    $wpdb->query("ALTER TABLE $table_chat_rooms MODIFY COLUMN room_type ENUM('team', 'match', 'message_thread', 'system', 'group', 'direct') NOT NULL");

    // インデックスを追加（存在しない場合のみ）
    $indexes_result = $wpdb->get_results("SHOW INDEX FROM $table_chat_rooms", ARRAY_A);
    $indexes = [];
    if ($indexes_result) {
        foreach ($indexes_result as $index_row) {
            if (!empty($index_row['Key_name'])) {
                $indexes[] = $index_row['Key_name'];
            }
        }
    }
    $indexes = array_unique($indexes);

    $new_indexes = [
        'idx_schedule' => "CREATE INDEX idx_schedule ON $table_chat_rooms (schedule_id)",
        'idx_related_message' => "CREATE INDEX idx_related_message ON $table_chat_rooms (related_message_id)",
        'idx_system' => "CREATE INDEX idx_system ON $table_chat_rooms (system_type, related_id)",
        'idx_unique_key' => "CREATE INDEX idx_unique_key ON $table_chat_rooms (unique_key)"
    ];

    foreach ($new_indexes as $index_name => $sql) {
        if ($index_name === 'idx_schedule' && !$has_schedule_id_col) {
            continue;
        }
        if (!in_array($index_name, $indexes, true)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query($sql);
        }
    }

    // ユニーク制約を追加（存在しない場合のみ、エラーは無視）
    $constraints_result = $wpdb->get_results("
        SELECT CONSTRAINT_NAME
        FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = '$table_chat_rooms'
          AND CONSTRAINT_TYPE = 'UNIQUE'
    ", ARRAY_A);
    $constraints = [];
    if ($constraints_result) {
        foreach ($constraints_result as $constraint_row) {
            if (!empty($constraint_row['CONSTRAINT_NAME'])) {
                $constraints[] = $constraint_row['CONSTRAINT_NAME'];
            }
        }
    }

    $unique_constraints = [
        'unique_message_thread' => "ALTER TABLE $table_chat_rooms ADD UNIQUE KEY unique_message_thread (room_type, related_message_id)",
        'unique_system' => "ALTER TABLE $table_chat_rooms ADD UNIQUE KEY unique_system (room_type, system_type, related_id)",
        'unique_group_direct' => "ALTER TABLE $table_chat_rooms ADD UNIQUE KEY unique_group_direct (room_type, unique_key)"
    ];

    foreach ($unique_constraints as $constraint_name => $sql) {
        if (!in_array($constraint_name, $constraints, true)) {
            // 既存のデータで重複がある場合はスキップ
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query($sql);
        }
    }

    if ($has_schedule_id_col) {
        aidunite_backfill_chat_rooms_schedule_id_column();
    }

    update_option('aidunite_chat_rooms_schema_version', $target_schema_version);
}

/**
 * chat_rooms.schedule_id を match_request（match_id）から逆引きして埋める（NULL のままでも動作する前提の補完）
 */
function aidunite_backfill_chat_rooms_schedule_id_column() {
    global $wpdb;
    $table = $wpdb->prefix . 'chat_rooms';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
        return;
    }
    $cols = $wpdb->get_col("SHOW COLUMNS FROM $table LIKE '%'");
    if (!is_array($cols) || !in_array('schedule_id', $cols, true) || !in_array('match_id', $cols, true)) {
        return;
    }
    $rows = $wpdb->get_results(
        "SELECT id, match_id FROM $table WHERE room_type IN ('match','group') AND match_id IS NOT NULL AND match_id > 0 AND (schedule_id IS NULL OR schedule_id = 0) LIMIT 2000"
    );
    if (empty($rows)) {
        return;
    }
    foreach ($rows as $row) {
        $rid = (int) $row->id;
        $mid = (int) $row->match_id;
        if ($rid <= 0 || $mid <= 0) {
            continue;
        }
        $to_sid   = (int) get_post_meta($mid, 'to_schedule_id', true);
        $from_sid = (int) get_post_meta($mid, 'from_schedule_id', true);
        $my_sid   = (int) get_post_meta($mid, 'my_schedule_id', true);
        $sid      = $to_sid > 0 ? $to_sid : ($from_sid > 0 ? $from_sid : $my_sid);
        if ($sid > 0) {
            $wpdb->update($table, [ 'schedule_id' => $sid ], [ 'id' => $rid ], [ '%d' ], [ '%d' ]);
        }
    }
}

/**
 * チャットメッセージテーブルのマイグレーション（parent_message_id・リアクション用テーブル）
 */
function aidunite_migrate_chat_messages_table() {
    global $wpdb;
    $table_messages = $wpdb->prefix . 'chat_messages';
    $table_reactions = $wpdb->prefix . 'chat_message_reactions';
    $version_key = 'aidunite_chat_messages_schema_version';
    $target_version = '2025-02-reactions-reply';

    if (get_option($version_key) === $target_version) {
        return;
    }

    if ($wpdb->get_var("SHOW TABLES LIKE '$table_messages'") !== $table_messages) {
        return;
    }

    $columns = $wpdb->get_col("SHOW COLUMNS FROM $table_messages LIKE '%'");
    if (!in_array('parent_message_id', $columns)) {
        $wpdb->query("ALTER TABLE $table_messages ADD COLUMN parent_message_id BIGINT NULL AFTER sender_id");
        $wpdb->query("ALTER TABLE $table_messages ADD INDEX idx_parent (parent_message_id)");
    }

    if ($wpdb->get_var("SHOW TABLES LIKE '$table_reactions'") !== $table_reactions) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_reactions (
            id BIGINT NOT NULL AUTO_INCREMENT,
            message_id BIGINT NOT NULL,
            user_id BIGINT NOT NULL,
            reaction_type VARCHAR(20) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_message_user_reaction (message_id, user_id, reaction_type),
            INDEX idx_message (message_id),
            INDEX idx_user (user_id)
        ) $charset;";
        dbDelta($sql);
    }

    update_option($version_key, $target_version);
}

// 既存サイトでもマイグレーションが走るよう init で実行（parent_message_id とリアクション用テーブル）
add_action('init', 'aidunite_migrate_chat_messages_table', 20);

// プラグイン有効化時にテーブル作成
register_activation_hook(__FILE__, 'aidunite_create_messaging_tables');

// 管理画面でテーブル作成を実行
add_action('admin_init', function() {
    if (isset($_GET['create_messaging_tables'])) {
        // 統一認証・権限チェック（管理者のみ）
        require_once get_template_directory() . '/functions/common/auth-middleware.php';
        $auth_result = AidUniteAuthMiddleware::require_admin(false);
        if (!$auth_result->is_valid()) {
            wp_die($auth_result->error ?: '権限がありません');
        }

        // CSRF対策（統一版）
        $nonce_result = AidUniteAuthMiddleware::verify_nonce('create_tables_nonce', 'aidunite_create_messaging_tables');
        if (is_wp_error($nonce_result)) {
            wp_die($nonce_result->get_error_message(), 'エラー', ['response' => 403]);
        }

        aidunite_create_messaging_tables();
        wp_redirect(admin_url('admin.php?page=aidunite-messaging&tables_created=1'));
        exit;
    }
});

// 管理画面にテーブル作成ボタンを追加
add_action('admin_menu', function() {
    add_submenu_page(
        'aidunite-settings',
        'メッセージ機能設定',
        'メッセージ機能',
        'manage_options',
        'aidunite-messaging',
        'aidunite_messaging_admin_page'
    );
});

function aidunite_messaging_admin_page() {
    ?>
    <div class="wrap">
        <h1>メッセージ機能設定</h1>

        <?php if (isset($_GET['tables_created'])): ?>
            <div class="notice notice-success">
                <p>✅ データベーステーブルが正常に作成されました！</p>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2>データベース設定</h2>
            <p>メッセージ機能に必要なデータベーステーブルを作成します。</p>
            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=aidunite-messaging&create_messaging_tables=1'), 'aidunite_create_messaging_tables', 'create_tables_nonce'); ?>"
               class="button button-primary">
                📊 データベーステーブルを作成
            </a>
        </div>

        <div class="card">
            <h2>機能ステータス</h2>
            <table class="form-table">
                <tr>
                    <th>メッセージ掲示板</th>
                    <td><?php echo aidunite_check_table_exists('team_messages') ? '✅ 有効' : '❌ 未作成'; ?></td>
                </tr>
                <tr>
                    <th>チャットルーム</th>
                    <td><?php echo aidunite_check_table_exists('chat_rooms') ? '✅ 有効' : '❌ 未作成'; ?></td>
                </tr>
                <tr>
                    <th>チャットメッセージ</th>
                    <td><?php echo aidunite_check_table_exists('chat_messages') ? '✅ 有効' : '❌ 未作成'; ?></td>
                </tr>
                <tr>
                    <th>既読管理</th>
                    <td><?php echo aidunite_check_table_exists('chat_read_status') ? '✅ 有効' : '❌ 未作成'; ?></td>
                </tr>
                <tr>
                    <th>チャット参加者</th>
                    <td><?php echo aidunite_check_table_exists('chat_participants') ? '✅ 有効' : '❌ 未作成'; ?></td>
                </tr>
                <tr>
                    <th>試合評価</th>
                    <td><?php echo aidunite_check_table_exists('match_evaluations') ? '✅ 有効' : '❌ 未作成'; ?></td>
                </tr>
                <tr>
                    <th>再マッチング提案</th>
                    <td><?php echo aidunite_check_table_exists('rematch_suggestions') ? '✅ 有効' : '❌ 未作成'; ?></td>
                </tr>
            </table>
        </div>
    </div>
    <?php
}

function aidunite_check_table_exists($table_name) {
    global $wpdb;
    $table = $wpdb->prefix . $table_name;
    return $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;
}

/**
 * サンプルメッセージを作成
 */
function aidunite_create_sample_messages($team_id = null) {
    global $wpdb;

    $table = $wpdb->prefix . 'team_messages';

    // チームIDが指定されていない場合は1を使用
    if (!$team_id) {
        $team_id = 1;
    }

    // 既にサンプルメッセージが存在するかチェック
    $existing = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE user_id = 0 AND team_id = %d", $team_id));
    if ($existing > 0) {
        return; // 既にサンプルメッセージが存在する場合はスキップ
    }

    // 指定されたチームIDを使用
    $sample_team_id = $team_id;

    $sample_messages = [
        [
            'team_id' => $sample_team_id,
            'user_id' => 0, // システム
            'title' => '🎉 コミュニケーションルームへようこそ！',
            'content' => "チーム内での連絡やコミュニケーションを効率的に行うための新しい機能です。\n\n📋 メッセージ掲示板では：\n・お知らせや連絡事項の共有\n・スケジュール変更の通知\n・試合結果の報告\n\n💬 チャット機能では：\n・リアルタイムでのコミュニケーション\n・ファイルや画像の共有\n・対戦相手との調整\n\nぜひ活用してください！",
            'category' => 'announcement',
            'priority' => 'important',
            'pinned' => true,
            'status' => 'published'
        ],
    ];

    foreach ($sample_messages as $message) {
        $wpdb->insert($table, $message, [
            '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s'
        ]);
    }

    error_log('✅ サンプルメッセージを作成しました');
}

/**
 * 廃止したテスト用サンプルお知らせをDBから削除（サイトごとに1回のみ）
 */
function aidunite_remove_retired_sample_board_messages() {
    if (get_option('aidunite_retired_sample_board_removed_v1')) {
        return;
    }

    global $wpdb;

    if (!aidunite_check_table_exists('team_messages')) {
        return;
    }

    $table = $wpdb->prefix . 'team_messages';
    $title_patterns = [
        '%来週の練習スケジュールについて%',
        '%先日の試合結果報告%',
        '%練習場所変更%',
        '%今週の練習テーマ%',
    ];

    $message_ids = [];
    foreach ($title_patterns as $pattern) {
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM $table WHERE user_id = 0 AND title LIKE %s",
            $pattern
        ));
        if (!empty($ids)) {
            $message_ids = array_merge($message_ids, $ids);
        }
    }

    $message_ids = array_values(array_unique(array_map('intval', $message_ids)));
    foreach ($message_ids as $message_id) {
        if (function_exists('aidunite_get_message_thread_room') && function_exists('aidunite_delete_chat_room_permanently')) {
            $thread_room = aidunite_get_message_thread_room($message_id);
            if ($thread_room && !empty($thread_room->id)) {
                aidunite_delete_chat_room_permanently((int) $thread_room->id);
            }
        }
        $wpdb->delete($table, ['id' => $message_id], ['%d']);
    }

    update_option('aidunite_retired_sample_board_removed_v1', true);
    error_log('✅ 廃止したテスト用サンプルお知らせを削除しました');
}
add_action('init', 'aidunite_remove_retired_sample_board_messages', 20);
