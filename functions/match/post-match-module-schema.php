<?php
/**
 * 試合後モジュール反応ログ（RDB）
 * PostMatchModule 定義は post_match_module（永続化アダプタ）、ログは専用テーブル。
 */

if (!defined('ABSPATH')) {
    exit;
}

define('AIDUNITE_POST_MATCH_MODULE_LOG_DB_VERSION', '1.0.0');

/**
 * @return string
 */
function aidunite_post_match_module_log_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'aidunite_post_match_module_log';
}

/**
 * テーブル作成
 */
function aidunite_post_match_module_log_install_table() {
    global $wpdb;

    $table = aidunite_post_match_module_log_table_name();
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        module_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        team_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        match_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        action VARCHAR(32) NOT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY idx_module (module_id),
        KEY idx_user (user_id),
        KEY idx_match (match_id),
        KEY idx_action (action),
        KEY idx_created (created_at)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    update_option('aidunite_post_match_module_log_db_version', AIDUNITE_POST_MATCH_MODULE_LOG_DB_VERSION);
}

add_action('after_switch_theme', 'aidunite_post_match_module_log_install_table');
add_action('init', static function () {
    if (get_option('aidunite_post_match_module_log_db_version') !== AIDUNITE_POST_MATCH_MODULE_LOG_DB_VERSION) {
        aidunite_post_match_module_log_install_table();
    }
}, 5);

/**
 * @param int    $module_id
 * @param string $action impression|cta_click|dismiss
 * @param int    $user_id
 * @param int    $team_id
 * @param int    $match_id
 * @return int|false insert id
 */
function aidunite_post_match_module_log_insert($module_id, $action, $user_id, $team_id = 0, $match_id = 0) {
    global $wpdb;

    $module_id = (int) $module_id;
    $user_id = (int) $user_id;
    $team_id = (int) $team_id;
    $match_id = (int) $match_id;
    $action = sanitize_key($action);

    if ($module_id <= 0 || $user_id <= 0 || !in_array($action, ['impression', 'cta_click', 'dismiss'], true)) {
        return false;
    }

    $ok = $wpdb->insert(
        aidunite_post_match_module_log_table_name(),
        [
            'module_id'  => $module_id,
            'user_id'    => $user_id,
            'team_id'    => $team_id,
            'match_id'   => $match_id,
            'action'     => $action,
            'created_at' => current_time('mysql'),
        ],
        ['%d', '%d', '%d', '%d', '%s', '%s']
    );

    return $ok ? (int) $wpdb->insert_id : false;
}
