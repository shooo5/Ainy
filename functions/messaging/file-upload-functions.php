<?php
/**
 * ファイルアップロード機能
 * Phase 2 実装
 * MESSAGE-MVP-DESIGN.md に基づく実装
 */

require_once get_template_directory() . '/functions/common/error-handler.php';

// AJAXアクション登録
add_action('wp_ajax_aidunite_upload_file', 'aidunite_handle_file_upload');
add_action('wp_ajax_aidunite_upload_image', 'aidunite_handle_image_upload');

/**
 * ファイルアップロード処理
 */
function aidunite_handle_file_upload() {
    // 統一認証・権限チェック
    $auth_result = AidUniteAuthMiddleware::require_auth();
    if (!$auth_result->is_valid()) {
        // 認証エラーはcritical
        AidUniteApiResponse::send_critical_error(
            $auth_result->error ?: 'ログインが必要です',
            'authentication_required'
        );
        return;
    }

    // CSRF対策
    $nonce_result = AidUniteAuthMiddleware::verify_nonce('nonce', 'aidunite_messaging_nonce');
    if (is_wp_error($nonce_result)) {
        // CSRFエラーはcritical
        AidUniteApiResponse::send_critical_error(
            'セキュリティチェックに失敗しました。ページを再読み込みして再度お試しください。',
            'csrf_verification_failed'
        );
        return;
    }

    // ファイルアップロード処理
    if (!function_exists('wp_handle_upload')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }

    $uploadedfile = $_FILES['file'];
    $upload_overrides = [
        'test_form' => false,
        'mimes' => aidunite_get_allowed_file_types()
    ];

    $movefile = wp_handle_upload($uploadedfile, $upload_overrides);

    if ($movefile && !isset($movefile['error'])) {
        // データベースに記録
        $file_id = aidunite_save_uploaded_file($movefile['url'], $uploadedfile['name'], $uploadedfile['type']);

        AidUniteApiResponse::send_success([
            'url' => $movefile['url'],
            'name' => $uploadedfile['name'],
            'type' => $uploadedfile['type'],
            'id' => $file_id
        ], 'ファイルをアップロードしました');
    } else {
        // ファイルアップロードエラーはnormal
        AidUniteApiResponse::send_error(
            $movefile['error'] ?? 'ファイルのアップロードに失敗しました。しばらく時間をおいて再度お試しください。',
            null,
            'normal',
            'file_upload_failed'
        );
    }
}

/**
 * 画像アップロード処理
 */
function aidunite_handle_image_upload() {
    // CSRF対策（統一版）
    require_once get_template_directory() . '/functions/common/auth-middleware.php';
    require_once get_template_directory() . '/functions/common/error-handler.php';
    $nonce_result = AidUniteAuthMiddleware::verify_nonce('nonce', 'aidunite_messaging_nonce');
    if (is_wp_error($nonce_result)) {
        // CSRFエラーはcritical
        AidUniteApiResponse::send_critical_error(
            $nonce_result->get_error_message(),
            'csrf_verification_failed'
        );
        return;
    }

    // 統一認証・権限チェック
    $auth_result = AidUniteAuthMiddleware::require_auth();
    if (!$auth_result->is_valid()) {
        // 認証エラーはcritical
        AidUniteApiResponse::send_critical_error(
            $auth_result->error ?: 'ログインが必要です',
            'authentication_required'
        );
        return;
    }

    // 画像リサイズ処理
    $uploadedfile = $_FILES['file'];
    $upload_overrides = [
        'test_form' => false,
        'mimes' => ['jpg|jpeg|jpe' => 'image/jpeg', 'gif' => 'image/gif', 'png' => 'image/png', 'webp' => 'image/webp']
    ];

    if (!function_exists('wp_handle_upload')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }

    $movefile = wp_handle_upload($uploadedfile, $upload_overrides);

    if ($movefile && !isset($movefile['error'])) {
        // 画像リサイズ
        $resized = aidunite_resize_image($movefile['file'], 800, 600);

        if ($resized) {
            $movefile['url'] = str_replace($movefile['file'], $resized, $movefile['url']);
        }

        // データベースに記録
        $file_id = aidunite_save_uploaded_file($movefile['url'], $uploadedfile['name'], $uploadedfile['type']);

        AidUniteApiResponse::send_success([
            'url' => $movefile['url'],
            'name' => $uploadedfile['name'],
            'type' => $uploadedfile['type'],
            'id' => $file_id
        ], '画像をアップロードしました');
    } else {
        // ファイルアップロードエラーはnormal
        AidUniteApiResponse::send_error(
            $movefile['error'] ?? '画像のアップロードに失敗しました。しばらく時間をおいて再度お試しください。',
            null,
            'normal',
            'image_upload_failed'
        );
    }
}

/**
 * 許可されたファイルタイプを取得
 */
function aidunite_get_allowed_file_types() {
    return [
        'jpg|jpeg|jpe' => 'image/jpeg',
        'gif' => 'image/gif',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'txt' => 'text/plain',
        'zip' => 'application/zip'
    ];
}

/**
 * アップロードされたファイルをデータベースに保存
 */
function aidunite_save_uploaded_file($url, $name, $type) {
    global $wpdb;

    $table = $wpdb->prefix . 'uploaded_files';

    // ファイルサイズを取得
    $file_path = str_replace(home_url(), ABSPATH, $url);
    $file_size = file_exists($file_path) ? filesize($file_path) : 0;

    $result = $wpdb->insert($table, [
        'user_id' => get_current_user_id(),
        'file_url' => $url,
        'file_name' => $name,
        'file_type' => $type,
        'file_size' => $file_size,
        'created_at' => current_time('mysql')
    ], [
        '%d', '%s', '%s', '%s', '%d', '%s'
    ]);

    if ($result === false) {
        return false;
    }

    return $wpdb->insert_id;
}

/**
 * 画像リサイズ
 */
function aidunite_resize_image($file_path, $max_width, $max_height) {
    if (!function_exists('wp_get_image_editor')) {
        return false;
    }

    $editor = wp_get_image_editor($file_path);

    if (is_wp_error($editor)) {
        return false;
    }

    $editor->resize($max_width, $max_height, true);
    $resized = $editor->save();

    if (is_wp_error($resized)) {
        return false;
    }

    return $resized['path'];
}

/**
 * ファイルアップロード用データベーステーブル作成
 */
function aidunite_create_uploaded_files_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'uploaded_files';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        file_url text NOT NULL,
        file_name varchar(255) NOT NULL,
        file_type varchar(100) NOT NULL,
        file_size bigint(20) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY file_type (file_type)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * ファイル削除
 */
function aidunite_delete_uploaded_file($file_id) {
    global $wpdb;

    $table = $wpdb->prefix . 'uploaded_files';

    // ファイル情報を取得
    $file = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM $table WHERE id = %d
    ", $file_id));

    if (!$file) {
        return false;
    }

    // 物理ファイルを削除
    $file_path = str_replace(home_url(), ABSPATH, $file->file_url);
    if (file_exists($file_path)) {
        unlink($file_path);
    }

    // データベースから削除
    $result = $wpdb->delete($table, ['id' => $file_id], ['%d']);

    return $result !== false;
}

/**
 * ユーザーのアップロードファイル一覧を取得
 */
function aidunite_get_user_uploaded_files($user_id, $limit = 20, $offset = 0) {
    global $wpdb;

    $table = $wpdb->prefix . 'uploaded_files';

    $files = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM $table
        WHERE user_id = %d
        ORDER BY created_at DESC
        LIMIT %d OFFSET %d
    ", $user_id, $limit, $offset));

    return $files;
}

/**
 * ファイルアップロード統計を取得
 */
function aidunite_get_upload_stats($user_id = null) {
    global $wpdb;

    $table = $wpdb->prefix . 'uploaded_files';

    $where_clause = '';
    $where_values = [];

    if ($user_id) {
        $where_clause = 'WHERE user_id = %d';
        $where_values[] = $user_id;
    }

    $sql = $wpdb->prepare("
        SELECT
            COUNT(*) as total_files,
            SUM(file_size) as total_size,
            SUM(CASE WHEN file_type LIKE 'image/%' THEN 1 ELSE 0 END) as image_count,
            SUM(CASE WHEN file_type LIKE 'application/%' THEN 1 ELSE 0 END) as document_count
        FROM $table
        $where_clause
    ", $where_values);

    return $wpdb->get_row($sql);
}
