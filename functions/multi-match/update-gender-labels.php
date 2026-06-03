<?php
/**
 * 性別ラベル移行スクリプト
 * 「混合」を canonical の both に更新（手動管理用）
 */

// 直接実行を防ぐ
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 性別ラベルを「混合」から canonical の **`both`** に更新（旧「男女」表記への移行用スクリプトの後継）
 */
function aidunite_update_gender_labels() {
    global $wpdb;

    // チーム投稿タイプの性別フィールドを更新
    $updated_teams = $wpdb->update(
        $wpdb->postmeta,
        ['meta_value' => 'both'],
        [
            'meta_key' => 'team_gender_option',
            'meta_value' => '混合'
        ]
    );

    // スケジュール投稿タイプの性別条件フィールドを更新
    $updated_schedules = $wpdb->update(
        $wpdb->postmeta,
        ['meta_value' => 'both'],
        [
            'meta_key' => 'matching_gender_condition',
            'meta_value' => '混合'
        ]
    );

    // 複数チームマッチ参加者の性別フィールドを更新
    $updated_participants = $wpdb->update(
        $wpdb->postmeta,
        ['meta_value' => 'both'],
        [
            'meta_key' => 'team_gender_option',
            'meta_value' => '混合'
        ]
    );

    return [
        'teams' => $updated_teams,
        'schedules' => $updated_schedules,
        'participants' => $updated_participants
    ];
}

/**
 * 移行実行（管理者のみ）
 */
function aidunite_run_gender_label_migration() {
    // 管理者権限チェック
    if (!current_user_can('manage_options')) {
        wp_die('権限がありません');
    }

    // 移行実行
    $results = aidunite_update_gender_labels();

    // 結果表示
    echo '<div class="notice notice-success">';
    echo '<h3>性別ラベル移行完了</h3>';
    echo '<p>以下の件数が「混合」から <code>both</code> に更新されました：</p>';
    echo '<ul>';
    echo '<li>チーム: ' . ($results['teams'] ?: 0) . '件</li>';
    echo '<li>スケジュール: ' . ($results['schedules'] ?: 0) . '件</li>';
    echo '<li>複数チームマッチ参加者: ' . ($results['participants'] ?: 0) . '件</li>';
    echo '</ul>';
    echo '</div>';
}

// 管理画面で移行を実行するためのアクションフック
add_action('admin_post_aidunite_migrate_gender_labels', 'aidunite_run_gender_label_migration');

/**
 * 管理画面に移行ボタンを追加
 */
function aidunite_add_gender_migration_button() {
    if (current_user_can('manage_options')) {
        echo '<div class="wrap">';
        echo '<h2>性別ラベル移行</h2>';
        echo '<p>既存の「混合」ラベルを「男女」に更新します。</p>';
        echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
        echo '<input type="hidden" name="action" value="aidunite_migrate_gender_labels">';
        echo '<input type="submit" class="button button-primary" value="移行を実行">';
        echo '</form>';
        echo '</div>';
    }
}

// 管理画面メニューに追加（必要に応じて）
// add_action('admin_menu', function() {
//     add_management_page(
//         '性別ラベル移行',
//         '性別ラベル移行',
//         'manage_options',
//         'aidunite-gender-migration',
//         'aidunite_add_gender_migration_button'
//     );
// });
?>
