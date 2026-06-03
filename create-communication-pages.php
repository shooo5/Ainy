<?php
/**
 * コミュニケーション関連の固定ページを作成・更新するスクリプト
 *
 * 使用方法:
 * 1. WordPressのルートディレクトリにこのファイルを配置
 * 2. ブラウザでアクセス: http://your-site.com/create-communication-pages.php
 * または
 * 3. WordPress管理画面の任意のページにアクセス（admin_initフックで実行される）
 */

// WordPressを読み込む
require_once(__DIR__ . '/wp-load.php');

// 管理者権限チェック
if (!current_user_can('administrator')) {
    wp_die('このスクリプトを実行するには管理者権限が必要です。');
}

echo '<h1>コミュニケーション関連ページの作成・更新</h1>';
echo '<pre>';

// コミュニケーションメイン画面
$communication_main_page = get_page_by_path('communication-main');
if (!$communication_main_page) {
    $page_id = wp_insert_post(array(
        'post_title'    => 'コミュニケーションメイン画面',
        'post_name'     => 'communication-main',
        'post_status'   => 'publish',
        'post_type'     => 'page',
        'post_content'  => '',
    ));
    if ($page_id && !is_wp_error($page_id)) {
        if (file_exists(get_template_directory() . '/page-communication-main.php')) {
            update_post_meta($page_id, '_wp_page_template', 'page-communication-main.php');
        }
        echo "✅ コミュニケーションメイン画面を作成しました。ページID: $page_id\n";
        echo "   URL: " . get_permalink($page_id) . "\n";
    } else {
        echo "❌ コミュニケーションメイン画面の作成に失敗しました。\n";
    }
} else {
    $current_template = get_post_meta($communication_main_page->ID, '_wp_page_template', true);
    if ($current_template !== 'page-communication-main.php') {
        update_post_meta($communication_main_page->ID, '_wp_page_template', 'page-communication-main.php');
        echo "✅ コミュニケーションメイン画面のテンプレートを更新しました。ページID: {$communication_main_page->ID}\n";
    } else {
        echo "ℹ️  コミュニケーションメイン画面は既に存在します。ページID: {$communication_main_page->ID}\n";
    }
    echo "   URL: " . get_permalink($communication_main_page->ID) . "\n";
}

// チャット画面（統合版）
$chat_page = get_page_by_path('chat');
if (!$chat_page) {
    $page_id = wp_insert_post(array(
        'post_title'    => 'チャット画面（統合版）',
        'post_name'     => 'chat',
        'post_status'   => 'publish',
        'post_type'     => 'page',
        'post_content'  => '',
    ));
    if ($page_id && !is_wp_error($page_id)) {
        if (file_exists(get_template_directory() . '/page-chat.php')) {
            update_post_meta($page_id, '_wp_page_template', 'page-chat.php');
        }
        echo "✅ チャット画面を作成しました。ページID: $page_id\n";
        echo "   URL: " . get_permalink($page_id) . "\n";
    } else {
        echo "❌ チャット画面の作成に失敗しました。\n";
    }
} else {
    $current_template = get_post_meta($chat_page->ID, '_wp_page_template', true);
    if ($current_template !== 'page-chat.php') {
        update_post_meta($chat_page->ID, '_wp_page_template', 'page-chat.php');
        echo "✅ チャット画面のテンプレートを更新しました。ページID: {$chat_page->ID}\n";
    } else {
        echo "ℹ️  チャット画面は既に存在します。ページID: {$chat_page->ID}\n";
    }
    echo "   URL: " . get_permalink($chat_page->ID) . "\n";
}

// 後方互換性: /communication も /communication-main にリダイレクト
$communication_page = get_page_by_path('communication');
if (!$communication_page) {
    $page_id = wp_insert_post(array(
        'post_title'    => 'コミュニケーション',
        'post_name'     => 'communication',
        'post_status'   => 'publish',
        'post_type'     => 'page',
        'post_content'  => '',
    ));
    if ($page_id && !is_wp_error($page_id)) {
        update_post_meta($page_id, '_wp_page_template', 'page-communication-main.php');
        echo "✅ コミュニケーションページ（リダイレクト用）を作成しました。ページID: $page_id\n";
        echo "   URL: " . get_permalink($page_id) . "\n";
    }
} else {
    echo "ℹ️  コミュニケーションページは既に存在します。ページID: {$communication_page->ID}\n";
    echo "   URL: " . get_permalink($communication_page->ID) . "\n";
}

echo "\n完了しました！\n";
echo '</pre>';
echo '<p><a href="' . admin_url() . '">管理画面に戻る</a></p>';
