<?php
/**
 * 統一リダイレクトユーティリティ
 * AidUnite Theme - Redirect Utils
 *
 * URL/リダイレクト/画面遷移を統一するためのユーティリティクラス
 */

if (!defined('ABSPATH')) {
    exit;
}

class AidUniteRedirect {
    /**
     * リダイレクトを実行
     *
     * @param string $url リダイレクト先URL
     * @param int $status_code HTTPステータスコード
     */
    public static function to($url, $status_code = 302) {
        wp_safe_redirect($url, $status_code);
        exit;
    }

    /**
     * ログインページにリダイレクト
     *
     * @param string $redirect_to ログイン後のリダイレクト先
     */
    public static function to_login($redirect_to = '') {
        $login_url = wp_login_url($redirect_to ?: home_url('/mypage'));
        self::to($login_url);
    }

    /**
     * マイページにリダイレクト
     *
     * @param array $query_args クエリ引数
     */
    public static function to_mypage($query_args = []) {
        $url = home_url('/mypage');
        if (!empty($query_args)) {
            $url = add_query_arg($query_args, $url);
        }
        self::to($url);
    }

    /**
     * ホームページにリダイレクト
     *
     * @param array $query_args クエリ引数
     */
    public static function to_home($query_args = []) {
        $url = home_url();
        if (!empty($query_args)) {
            $url = add_query_arg($query_args, $url);
        }
        self::to($url);
    }

    /**
     * エラーページにリダイレクト
     *
     * @param string $error_message エラーメッセージ
     * @param string $error_code エラーコード
     */
    public static function to_error($error_message = '', $error_code = '') {
        $url = home_url('/error');
        $query_args = [];

        if (!empty($error_message)) {
            $query_args['error'] = urlencode($error_message);
        }

        if (!empty($error_code)) {
            $query_args['code'] = urlencode($error_code);
        }

        if (!empty($query_args)) {
            $url = add_query_arg($query_args, $url);
        }

        self::to($url);
    }

    /**
     * 前のページにリダイレクト（HTTP_REFERER）
     *
     * @param string $fallback_url フォールバックURL
     */
    public static function back($fallback_url = '') {
        $referer = wp_get_referer();
        if ($referer) {
            self::to($referer);
        } elseif ($fallback_url) {
            self::to($fallback_url);
        } else {
            self::to_home();
        }
    }

    /**
     * ログイン後のリダイレクト先を取得
     *
     * @param int $user_id ユーザーID
     * @return string リダイレクト先URL
     */
    public static function get_login_redirect_url($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return home_url('/mypage');
        }

        $user_info = aidunite_get_user_info($user_id);
        $user_type = $user_info['user_type'] ?? 'general';

        switch ($user_type) {
            case 'team_leader':
                return home_url('/team-management');
            case 'parent':
                return home_url('/mypage');
            case 'player':
                return home_url('/mypage');
            case 'administrator':
                return admin_url();
            default:
                return home_url('/mypage');
        }
    }
}
