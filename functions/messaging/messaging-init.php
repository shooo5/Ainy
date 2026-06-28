<?php
/**
 * メッセージ機能初期化
 * MESSAGE-MVP-DESIGN.md に基づく実装
 */

// 必要なファイルを読み込み
require_once __DIR__ . '/database-schema.php';
require_once __DIR__ . '/rest-api.php';
require_once __DIR__ . '/sse-functions.php';
require_once __DIR__ . '/message-functions.php';
require_once __DIR__ . '/chat-persist.php';
require_once __DIR__ . '/chat-functions.php';
require_once __DIR__ . '/notification-functions.php';
require_once __DIR__ . '/match-chat-router.php';
$file_upload_path = __DIR__ . '/file-upload-functions.php';
if (file_exists($file_upload_path)) {
    require_once $file_upload_path;
} else {
    // テンプレートディレクトリからのパスも試す（Windows/Unixのパス差異対策）
    $theme_dir = trailingslashit(get_template_directory());
    $file_upload_path_alt = $theme_dir . 'functions/messaging/file-upload-functions.php';
    if (file_exists($file_upload_path_alt)) {
        require_once $file_upload_path_alt;
    } else {
        static $file_upload_warning_logged = false;
        if (!$file_upload_warning_logged) {
            $file_upload_warning_logged = true;
            error_log('⚠️ file-upload-functions.php が見つかりません（機能はスキップされます）');
            error_log('   試行したパス1: ' . $file_upload_path);
            error_log('   試行したパス2: ' . $file_upload_path_alt);
        }
    }
}

// メッセージ機能を有効化
add_action('init', function() {
    // データベーステーブル作成
    if (get_option('aidunite_messaging_db_version') !== '2.0.0') {
        aidunite_create_messaging_tables();
    } else {
        // 既存テーブルのマイグレーション（カラム追加など）
        aidunite_migrate_chat_rooms_table();
    }

    // 定期クリーンアップ
    if (!wp_next_scheduled('aidunite_cleanup_old_messages')) {
        wp_schedule_event(time(), 'daily', 'aidunite_cleanup_old_messages');
    }

    // 既存データ補正（バージョン管理）: 重複 active の match/group ルームを completed へ整理
    $cleanup_version = (int) get_option('aidunite_migrated_duplicate_active_match_rooms_version', 0);
    if ($cleanup_version < 2 && function_exists('aidunite_cleanup_duplicate_active_match_rooms')) {
        $migrated = (int) aidunite_cleanup_duplicate_active_match_rooms();
        update_option('aidunite_migrated_duplicate_active_match_rooms_version', 2);
        update_option('aidunite_migrated_duplicate_active_match_rooms', true); // 旧オプション互換
        if ($migrated > 0) {
            error_log('aidunite_cleanup_duplicate_active_match_rooms: ' . $migrated . ' 件を completed に補正しました');
        }
    }

});

// 試合成立時にチャット自動作成
add_action('aidunite_match_confirmed', function($match_id, $team_a_id, $team_b_id, $match_date) {
    aidunite_create_match_chat($match_id, $team_a_id, $team_b_id, $match_date);
}, 10, 4);

// チーム登録時のチームチャット自動作成は Club プラン時のみ（Match は対戦チャットのみ）
add_action('aidunite_team_registered', function ($team_id) {
    if (function_exists('aidunite_payment_team_has_club_plan') && !aidunite_payment_team_has_club_plan($team_id)) {
        return;
    }
    aidunite_create_team_chat($team_id);
});

// メッセージ送信時にSSE配信（チャット用）
add_action('aidunite_message_sent', function($room_id, $message_data) {
    aidunite_broadcast_message($room_id, $message_data);
}, 10, 2);

// 掲示板メッセージ作成（SSEチャットとは切り離し）
add_action('aidunite_team_message_created', function($message_id, $message_data) {
    // 必要に応じて掲示板用の通知やログのみ行う（チャットSSEは行わない）
    aidunite_log_success("掲示板メッセージが作成されました (Message ID: {$message_id}, Team ID: {$message_data['team_id']})");
}, 10, 2);

// メッセージ送信時にWeb Push通知（チャット）
add_action('aidunite_message_sent', function($room_id, $message_data) {
    // 既存のSSE配信
    aidunite_broadcast_message($room_id, $message_data);

    // Web Push通知を追加
    if (isset($message_data['sender_id'])) {
        $team_members = aidunite_get_team_members_from_room($room_id);

        if ($team_members) {
            foreach ($team_members as $member) {
                if ($member->user_id != $message_data['sender_id']) {
                    $title = "新しいメッセージ";
                    $message = substr($message_data['content'], 0, 100) . '...';

                    aidunite_send_web_push($member->user_id, $title, $message, [
                        'room_id' => $room_id,
                        'message_id' => $message_data['id'] ?? null,
                        'url' => '/chat?chat_id=' . $room_id
                    ]);
                }
            }
        }
    }
}, 10, 2);

// 既読状態更新時にSSE配信
add_action('aidunite_read_status_updated', function($room_id, $user_id, $message_id) {
    aidunite_broadcast_read_status($room_id, $user_id, $message_id);
}, 10, 3);

// 評価投稿時に再マッチング提案
add_action('aidunite_rating_posted', function($match_id, $rater_team_id, $rated_team_id, $rating) {
    if ($rating >= 4) {
        aidunite_suggest_rematch($match_id, $rater_team_id, $rated_team_id);
    }
}, 10, 4);

// スケジュール関連の自動メッセージ生成とチャットルーム作成
add_action('aidunite_schedule_created', function($schedule_id, $team_id, $schedule_data) {
    aidunite_create_auto_message($team_id, 'schedule', 'created', $schedule_data);
    // システムチャットルームを作成
    aidunite_create_system_chat('schedule_created', $schedule_id, $team_id);
}, 10, 3);

add_action('aidunite_schedule_updated', function($schedule_id, $team_id, $schedule_data) {
    aidunite_create_auto_message($team_id, 'schedule', 'updated', $schedule_data);
    // システムチャットルームを作成
    aidunite_create_system_chat('schedule_updated', $schedule_id, $team_id);
}, 10, 3);

add_action('aidunite_schedule_deleted', function($schedule_id, $team_id, $schedule_data) {
    aidunite_create_auto_message($team_id, 'schedule', 'deleted', $schedule_data);
    // システムチャットルームを作成
    aidunite_create_system_chat('schedule_deleted', $schedule_id, $team_id);
}, 10, 3);

add_action('aidunite_schedule_confirmed', function($schedule_id, $team_id, $schedule_data) {
    aidunite_create_auto_message($team_id, 'schedule', 'confirmed', $schedule_data);
    // システムチャットルームを作成
    aidunite_create_system_chat('schedule_confirmed', $schedule_id, $team_id);
}, 10, 3);

// マッチ関連の自動メッセージ生成とチャットルーム作成
add_action('aidunite_match_created', function($match_id, $team_a_id, $team_b_id, $match_data) {
    aidunite_create_auto_message($team_a_id, 'match', 'created', $match_data);
    aidunite_create_auto_message($team_b_id, 'match', 'created', $match_data);
    // システムチャットルームを作成（マッチ成立前）
    aidunite_create_system_chat('match_created', $match_id, $team_a_id);
    aidunite_create_system_chat('match_created', $match_id, $team_b_id);
}, 10, 4);

// 管理画面にメッセージ機能メニューを追加
add_action('admin_menu', function() {
    add_menu_page(
        'メッセージ機能',
        'メッセージ',
        'manage_options',
        'aidunite-messaging',
        'aidunite_messaging_admin_page',
        'dashicons-format-chat',
        30
    );
});

// フロントエンド用のJavaScript/CSS読み込み（コミュニケーション・チャット画面のみ）
add_action('wp_enqueue_scripts', function() {
    $load_messaging_assets = is_page(['communication-main', 'communication', 'team-chat', 'chat'])
        || is_page_template(['page-communication-main.php', 'page-chat.php']);
    if (!$load_messaging_assets) {
        return;
    }

    $messaging_css = get_stylesheet_directory() . '/assets/css/pages/messaging.css';
    wp_enqueue_style(
        'aidunite-messaging',
        get_stylesheet_directory_uri() . '/assets/css/pages/messaging.css',
        ['aidunite-style', 'aidunite-web-app-integrated-ui', 'button-style', 'form-style', 'card-style'],
        is_readable($messaging_css) ? (string) filemtime($messaging_css) : '2.0.1'
    );
    $messaging_buttons_css = get_stylesheet_directory() . '/assets/css/components/messaging-buttons.css';
    if (is_readable($messaging_buttons_css)) {
        wp_enqueue_style(
            'aidunite-messaging-buttons',
            get_stylesheet_directory_uri() . '/assets/css/components/messaging-buttons.css',
            ['button-style', 'aidunite-messaging'],
            (string) filemtime($messaging_buttons_css)
        );
    }

    // aidunite_messaging は page-communication-main.php / page-chat.php で使用
    wp_register_script('aidunite-messaging-config', false, ['jquery'], '2.0.0', true);
    wp_enqueue_script('aidunite-messaging-config');
    if (is_page('chat') || is_page_template('page-chat.php')) {
        $chat_critical_css = get_stylesheet_directory() . '/assets/css/pages/chat-critical.css';
        if (is_readable($chat_critical_css)) {
            wp_enqueue_style(
                'aidunite-chat-critical',
                get_stylesheet_directory_uri() . '/assets/css/pages/chat-critical.css',
                ['aidunite-style'],
                (string) filemtime($chat_critical_css)
            );
        }
        $chat_page_css = get_stylesheet_directory() . '/assets/css/pages/chat-page.css';
        if (is_readable($chat_page_css)) {
            wp_enqueue_style(
                'aidunite-chat-page',
                get_stylesheet_directory_uri() . '/assets/css/pages/chat-page.css',
                ['aidunite-chat-critical', 'aidunite-messaging'],
                (string) filemtime($chat_page_css)
            );
        }
        $chat_page_js = get_stylesheet_directory() . '/assets/js/pages/chat-page.js';
        if (is_readable($chat_page_js)) {
            wp_enqueue_script(
                'aidunite-chat-page',
                get_stylesheet_directory_uri() . '/assets/js/pages/chat-page.js',
                ['aidunite-messaging-config', 'aidunite-theme-icons'],
                (string) filemtime($chat_page_js),
                true
            );
        }
    }
    wp_localize_script('aidunite-messaging-config', 'aidunite_messaging', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'rest_url' => rest_url('aidunite/v1/'),
        'nonce' => wp_create_nonce('wp_rest'),
        'user_id' => get_current_user_id(),
        'team_id' => function_exists('aidunite_get_current_team_id')
            ? (int) aidunite_get_current_team_id(get_current_user_id())
            : (int) get_user_meta(get_current_user_id(), 'team_id', true),
        'is_admin' => current_user_can('administrator')
    ]);
});

// 管理画面用のJavaScript/CSS読み込み
add_action('admin_enqueue_scripts', function($hook) {
    if (strpos($hook, 'aidunite-messaging') !== false) {
        wp_enqueue_script('aidunite-messaging-admin', get_template_directory_uri() . '/assets/js/admin/messaging-admin.js', ['jquery'], '2.0.0', true);
        wp_enqueue_style(
            'aidunite-messaging-admin',
            get_stylesheet_directory_uri() . '/assets/css/pages/messaging-admin.css',
            ['aidunite-style', 'button-style'],
            '2.0.0'
        );
    }
});

// ショートコード: コミュニケーションメイン画面表示
add_shortcode('aidunite_communication_main', function($atts) {
    // 統一認証・権限チェック
    $auth_result = AidUniteAuthMiddleware::require_team_membership();
    if (!$auth_result->is_valid()) {
        return '<p>' . esc_html($auth_result->error ?: 'ログインが必要です') . '</p>';
    }

    $user_id = $auth_result->user_id;
    $team_id = $auth_result->team_id;

    ob_start();
    include get_template_directory() . '/page-communication-main.php';
    return ob_get_clean();
});

// ショートコード: チャット画面表示（統合版）
add_shortcode('aidunite_chat', function($atts) {
    // 統一認証・権限チェック
    $auth_result = AidUniteAuthMiddleware::require_auth();
    if (!$auth_result->is_valid()) {
        return '<p>' . esc_html($auth_result->error ?: 'ログインが必要です') . '</p>';
    }

    $atts = shortcode_atts([
        'chat_id' => 0,
        'match_id' => 0
    ], $atts);

    ob_start();
    include get_template_directory() . '/page-chat.php';
    return ob_get_clean();
});

// 後方互換: チームチャット画面表示（内部でpage-chat.phpにリダイレクト）
add_shortcode('aidunite_team_chat', function($atts) {
    // 統一認証・権限チェック
    $auth_result = AidUniteAuthMiddleware::require_team_membership();
    if (!$auth_result->is_valid()) {
        return '<p>' . esc_html($auth_result->error ?: 'ログインが必要です') . '</p>';
    }

    $user_id = $auth_result->user_id;
    $team_id = $auth_result->team_id;

    // team_idからチャットルームを取得
    $chat_room = aidunite_get_team_chat_room($team_id);
    if (!$chat_room) {
        $chat_room_id = aidunite_create_team_chat($team_id);
        if (is_wp_error($chat_room_id)) {
            return '<p>チャットルームの作成に失敗しました。</p>';
        }
        $chat_room = aidunite_get_chat_room(intval($chat_room_id));
    }

    if ($chat_room) {
        // page-chat.phpにリダイレクト
        wp_redirect(home_url('/chat?chat_id=' . $chat_room->id));
        exit;
    }

    return '<p>チャットルームの取得に失敗しました。</p>';
});

// 後方互換: 対戦チャット画面表示（内部でpage-chat.phpにリダイレクト）
add_shortcode('aidunite_match_chat', function($atts) {
    $atts = shortcode_atts([
        'match_id' => 0
    ], $atts);

    if (!$atts['match_id']) {
        return '<p>マッチIDが指定されていません。</p>';
    }

    // 統一認証・権限チェック
    $auth_result = AidUniteAuthMiddleware::require_auth();
    if (!$auth_result->is_valid()) {
        return '<p>' . esc_html($auth_result->error ?: 'ログインが必要です') . '</p>';
    }

    // match_idからチャットルームを取得
    $chat_room = aidunite_get_match_chat_room($atts['match_id']);
    if ($chat_room) {
        // page-chat.phpにリダイレクト
        wp_redirect(home_url('/chat?chat_id=' . $chat_room->id));
        exit;
    }

    return '<p>対戦チャットルームが見つかりません。</p>';
});

// デバッグ用: 接続統計表示
add_action('wp_ajax_aidunite_sse_stats', function() {
    if (!current_user_can('manage_options')) {
        wp_die('権限がありません');
    }

    $stats = aidunite_get_sse_stats();
    wp_send_json_success($stats);
});

// クリーンアップ: プラグイン無効化時
register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('aidunite_cleanup_old_messages');
});

// ログ出力用ヘルパー関数
function aidunite_log($message, $level = 'info') {
    $log_message = sprintf('[%s] %s: %s', current_time('Y-m-d H:i:s'), strtoupper($level), $message);
    error_log($log_message);
}

// エラーハンドリング
function aidunite_handle_error($error, $context = '') {
    $message = $context ? "{$context}: {$error}" : $error;
    aidunite_log($message, 'error');

    if (WP_DEBUG) {
        wp_die($message);
    }
}

// 成功ログ
function aidunite_log_success($message, $context = '') {
    $log_message = $context ? "{$context}: {$message}" : $message;
    aidunite_log($log_message, 'success');
}
