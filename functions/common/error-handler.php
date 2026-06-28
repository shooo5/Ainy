<?php
/**
 * 統一エラーハンドリングシステム
 * AidUnite Theme - Error Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class AidUniteErrorHandler {
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_CRITICAL = 'critical';

    private static $log_file = null;

    /**
     * ログを記録
     */
    public static function log($message, $level = self::LEVEL_INFO, $context = []) {
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'user_id' => get_current_user_id(),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
        ];

        // ログファイルに記録
        self::writeToLogFile($log_entry);

        // 重要なエラーは管理者に通知
        if (in_array($level, [self::LEVEL_ERROR, self::LEVEL_CRITICAL])) {
            self::notifyAdmin($log_entry);
        }
    }

    /**
     * 例外をハンドリング
     */
    public static function handleException($exception, $context = []) {
        self::log(
            $exception->getMessage(),
            self::LEVEL_ERROR,
            array_merge($context, [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ])
        );
    }

    /**
     * ログファイルに書き込み（テキスト形式）
     */
    private static function writeToLogFile($log_entry) {
        if (!self::$log_file) {
            $upload_dir = wp_upload_dir();
            $log_dir = $upload_dir['basedir'] . '/aidunite-logs';

            if (!file_exists($log_dir)) {
                wp_mkdir_p($log_dir);
            }

            self::$log_file = $log_dir . '/error-' . date('Y-m-d') . '.log';
        }

        $log_line = sprintf(
            "[%s] %s: %s %s\n",
            $log_entry['timestamp'],
            strtoupper($log_entry['level']),
            $log_entry['message'],
            !empty($log_entry['context']) ? json_encode($log_entry['context'], JSON_UNESCAPED_UNICODE) : ''
        );

        file_put_contents(self::$log_file, $log_line, FILE_APPEND | LOCK_EX);
    }

    /**
     * JSON形式でログファイルに書き込み
     */
    private static function writeToLogFileJson($log_entry) {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/aidunite-logs';

        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        $json_log_file = $log_dir . '/error-json-' . date('Y-m-d') . '.log';

        $log_line = json_encode($log_entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

        file_put_contents($json_log_file, $log_line, FILE_APPEND | LOCK_EX);
    }

    /**
     * 管理者に通知
     */
    private static function notifyAdmin($log_entry) {
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return;
        }
        $admin_email = get_option('admin_email');
        if (!$admin_email) return;

        $subject = '[AidUnite] システムエラー通知';
        $message = sprintf(
            "システムエラーが発生しました。\n\n" .
            "レベル: %s\n" .
            "メッセージ: %s\n" .
            "ユーザーID: %s\n" .
            "リクエストURI: %s\n" .
            "時刻: %s\n\n" .
            "詳細: %s",
            $log_entry['level'],
            $log_entry['message'],
            $log_entry['user_id'],
            $log_entry['request_uri'],
            $log_entry['timestamp'],
            json_encode($log_entry['context'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * デバッグログ
     */
    public static function debug($message, $context = []) {
        if (WP_DEBUG) {
            self::log($message, self::LEVEL_DEBUG, $context);
        }
    }

    /**
     * 情報ログ
     */
    public static function info($message, $context = []) {
        self::log($message, self::LEVEL_INFO, $context);
    }

    /**
     * 警告ログ
     */
    public static function warning($message, $context = []) {
        self::log($message, self::LEVEL_WARNING, $context);
    }

    /**
     * エラーログ
     */
    public static function error($message, $context = []) {
        self::log($message, self::LEVEL_ERROR, $context);
    }

    /**
     * 致命的エラーログ
     */
    public static function critical($message, $context = []) {
        self::log($message, self::LEVEL_CRITICAL, $context);
    }
}

/**
 * 統一されたAPIレスポンスクラス
 */
class AidUniteApiResponse {
    /**
     * 成功レスポンス（JSON送信）
     *
     * @param mixed $data レスポンスデータ
     * @param string $message 成功メッセージ
     */
    public static function send_success($data = null, $message = '処理が完了しました') {
        wp_send_json_success([
            'message' => $message,
            'data' => $data
        ]);
    }

    /**
     * エラーレスポンス（JSON送信）
     *
     * @param string $message エラーメッセージ
     * @param array $errors フォームエラー（オプション）['field_name' => 'エラーメッセージ']
     * @param string $urgency 緊急度（'critical', 'urgent', 'normal'）
     * @param string $code エラーコード
     */
    public static function send_error($message, $errors = null, $urgency = 'normal', $code = 'error') {
        // ログに記録
        AidUniteErrorHandler::error($message, [
            'code' => $code,
            'errors' => $errors,
            'urgency' => $urgency
        ]);

        $response = [
            'message' => $message,
            'urgency' => $urgency
        ];

        // フォームエラーがある場合は追加
        if ($errors && is_array($errors) && !empty($errors)) {
            $response['errors'] = $errors;
        }

        wp_send_json_error($response);
    }

    /**
     * バリデーションエラー（JSON送信）
     *
     * @param array $errors フォームエラー ['field_name' => 'エラーメッセージ']
     * @param string $message 全体メッセージ
     */
    public static function send_validation_error($errors, $message = '入力内容を確認してください') {
        AidUniteErrorHandler::warning($message, ['errors' => $errors]);

        wp_send_json_error([
            'message' => $message,
            'errors' => $errors,
            'urgency' => 'normal'
        ]);
    }

    /**
     * 緊急エラー（JSON送信）
     *
     * @param string $message エラーメッセージ
     * @param string $code エラーコード
     */
    public static function send_critical_error($message, $code = 'critical_error') {
        AidUniteErrorHandler::critical($message, ['code' => $code]);

        wp_send_json_error([
            'message' => $message,
            'urgency' => 'critical',
            'code' => $code
        ]);
    }

    /**
     * 成功レスポンス（配列返却）
     *
     * @param mixed $data レスポンスデータ
     * @param string $message 成功メッセージ
     * @return array
     */
    public static function success($data = null, $message = 'Success') {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => current_time('mysql')
        ];
    }

    /**
     * エラーレスポンス（WP_Error返却）
     *
     * @param string $message エラーメッセージ
     * @param string $code エラーコード
     * @param int $status_code HTTPステータスコード
     * @param mixed $details 詳細情報
     * @return WP_Error
     */
    public static function error($message, $code = 'error', $status_code = 400, $details = null) {
        AidUniteErrorHandler::error($message, [
            'code' => $code,
            'status_code' => $status_code,
            'details' => $details
        ]);

        return new WP_Error($code, $message, [
            'status' => $status_code,
            'details' => $details
        ]);
    }

    /**
     * バリデーションエラー（WP_Error返却）
     *
     * @param array $errors エラー配列
     * @param string $message メッセージ
     * @return WP_Error
     */
    public static function validationError($errors, $message = 'Validation failed') {
        AidUniteErrorHandler::warning($message, ['errors' => $errors]);

        return new WP_Error('validation_failed', $message, [
            'status' => 400,
            'errors' => $errors
        ]);
    }

    /**
     * 権限エラー
     */
    public static function permissionError($message = 'Permission denied') {
        AidUniteErrorHandler::warning($message, [
            'user_id' => get_current_user_id(),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? ''
        ]);

        return new WP_Error('permission_denied', $message, [
            'status' => 403
        ]);
    }

    /**
     * 認証エラー
     */
    public static function authError($message = 'Authentication required') {
        AidUniteErrorHandler::warning($message, [
            'user_id' => get_current_user_id(),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? ''
        ]);

        return new WP_Error('authentication_required', $message, [
            'status' => 401
        ]);
    }

    /**
     * 後方互換性レイヤー: WordPress標準形式との互換レスポンス
     *
     * @param mixed $data レスポンスデータ
     * @param string $message メッセージ
     * @param bool $success 成功かどうか
     * @return array WordPress標準形式のレスポンス
     */
    public static function send_compatible_response($data = null, $message = '', $success = true) {
        if ($success) {
            // WordPress標準の成功形式: { success: true, data: {...} }
            wp_send_json_success([
                'message' => $message,
                'data' => $data
            ]);
        } else {
            // WordPress標準のエラー形式: { success: false, data: {...} }
            wp_send_json_error([
                'message' => $message,
                'data' => $data
            ]);
        }
    }

    /**
     * WordPress標準形式への変換
     *
     * @param array $response 統一形式のレスポンス
     * @return array WordPress標準形式のレスポンス
     */
    public static function convert_to_wp_format($response) {
        // 統一形式: { success: true/false, message: '', data: {}, urgency: '', errors: {} }
        // WordPress形式: { success: true/false, data: {...} }

        $wp_response = [
            'success' => $response['success'] ?? false,
            'data' => []
        ];

        // メッセージをdataに含める
        if (!empty($response['message'])) {
            $wp_response['data']['message'] = $response['message'];
        }

        // データをdataに含める
        if (isset($response['data'])) {
            if (is_array($response['data'])) {
                $wp_response['data'] = array_merge($wp_response['data'], $response['data']);
            } else {
                $wp_response['data']['data'] = $response['data'];
            }
        }

        // エラー情報をdataに含める
        if (!empty($response['errors'])) {
            $wp_response['data']['errors'] = $response['errors'];
        }

        // 緊急度をdataに含める
        if (!empty($response['urgency'])) {
            $wp_response['data']['urgency'] = $response['urgency'];
        }

        return $wp_response;
    }

    /**
     * 統一形式への変換（WordPress標準形式から）
     *
     * @param array $wp_response WordPress標準形式のレスポンス
     * @return array 統一形式のレスポンス
     */
    public static function convert_from_wp_format($wp_response) {
        // WordPress形式: { success: true/false, data: {...} }
        // 統一形式: { success: true/false, message: '', data: {}, urgency: '', errors: {} }

        $response = [
            'success' => $wp_response['success'] ?? false
        ];

        $data = $wp_response['data'] ?? [];

        // メッセージを抽出
        if (isset($data['message'])) {
            $response['message'] = $data['message'];
            unset($data['message']);
        }

        // エラー情報を抽出
        if (isset($data['errors'])) {
            $response['errors'] = $data['errors'];
            unset($data['errors']);
        }

        // 緊急度を抽出
        if (isset($data['urgency'])) {
            $response['urgency'] = $data['urgency'];
            unset($data['urgency']);
        }

        // 残りのデータ
        $response['data'] = $data;

        return $response;
    }
}

/**
 * バリデーション関数
 */
class AidUniteValidator {
    /**
     * 必須項目チェック
     */
    public static function required($data, $fields) {
        $errors = [];
        foreach ($fields as $field) {
            if (empty($data[$field])) {
                $errors[] = "{$field}は必須です";
            }
        }
        return $errors;
    }

    /**
     * 日付妥当性チェック
     */
    public static function validateDate($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    /**
     * 時間妥当性チェック
     */
    public static function validateTime($time) {
        return preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time);
    }

    /**
     * 時間順序チェック
     */
    public static function validateTimeOrder($start_time, $end_time) {
        return strtotime($start_time) < strtotime($end_time);
    }

    /**
     * 数値範囲チェック
     */
    public static function validateRange($value, $min, $max) {
        $num = intval($value);
        return $num >= $min && $num <= $max;
    }

    /**
     * メールアドレス妥当性チェック
     */
    public static function validateEmail($email) {
        return is_email($email);
    }

    /**
     * ユーザーID存在チェック
     */
    public static function validateUserExists($user_id) {
        return get_userdata($user_id) !== false;
    }

    /**
     * 投稿ID存在チェック
     */
    public static function validatePostExists($post_id, $post_type = null) {
        $post = get_post($post_id);
        if (!$post) return false;

        if ($post_type && $post->post_type !== $post_type) {
            return false;
        }

        return true;
    }

    /**
     * スケジュールバリデーション
     *
     * @param array $data スケジュールデータ
     * @return array エラー配列
     */
    public static function validateSchedule($data) {
        $errors = [];

        // 必須項目チェック
        $required_fields = ['date', 'start_time', 'end_time', 'schedule_type'];
        $required_errors = self::required($data, $required_fields);
        $errors = array_merge($errors, $required_errors);

        // 日付妥当性チェック
        if (!empty($data['date']) && !self::validateDate($data['date'])) {
            $errors[] = '無効な日付形式です';
        }

        // 時間妥当性チェック
        if (!empty($data['start_time']) && !self::validateTime($data['start_time'])) {
            $errors[] = '無効な開始時間です';
        }

        if (!empty($data['end_time']) && !self::validateTime($data['end_time'])) {
            $errors[] = '無効な終了時間です';
        }

        // 時間順序チェック
        if (!empty($data['start_time']) && !empty($data['end_time'])) {
            if (!self::validateTimeOrder($data['start_time'], $data['end_time'])) {
                $errors[] = '終了時間は開始時間より後にしてください';
            }
        }

        return $errors;
    }

    /**
     * チームバリデーション
     *
     * @param array $data チームデータ
     * @return array エラー配列
     */
    public static function validateTeam($data) {
        $errors = [];

        // 必須項目チェック
        $required_fields = ['team_name', 'sport_type', 'team_category', 'region'];
        $required_errors = self::required($data, $required_fields);
        $errors = array_merge($errors, $required_errors);

        // メールアドレス妥当性チェック
        if (!empty($data['contact_mail']) && !self::validateEmail($data['contact_mail'])) {
            $errors[] = '無効なメールアドレスです';
        }

        return $errors;
    }

    /**
     * ユーザーバリデーション
     *
     * @param array $data ユーザーデータ
     * @return array エラー配列
     */
    public static function validateUser($data) {
        $errors = [];

        // 必須項目チェック
        $required_fields = ['user_email', 'display_name'];
        $required_errors = self::required($data, $required_fields);
        $errors = array_merge($errors, $required_errors);

        // メールアドレス妥当性チェック
        if (!empty($data['user_email']) && !self::validateEmail($data['user_email'])) {
            $errors[] = '無効なメールアドレスです';
        }

        // パスワードチェック（設定されている場合）
        if (!empty($data['user_pass'])) {
            if (strlen($data['user_pass']) < 8) {
                $errors[] = 'パスワードは8文字以上で入力してください';
            }
        }

        return $errors;
    }
}

// グローバル例外ハンドラーを設定
set_exception_handler(function($exception) {
    AidUniteErrorHandler::handleException($exception);
});

// エラーハンドラーを設定
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    $exception = new ErrorException($message, 0, $severity, $file, $line);
    AidUniteErrorHandler::handleException($exception);

    return true;
});
