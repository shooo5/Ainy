<?php
/**
 * 統一認証・権限チェックミドルウェア
 * AidUnite Theme - Authentication Middleware
 *
 * テーマ内の認証・権限チェックを統一するためのミドルウェアです。
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once get_stylesheet_directory() . '/functions/common/error-handler.php';

/**
 * WordPress システム管理者または Ainy 管理者（aidunite_role / user_type）か
 *
 * @param int|null $user_id
 * @return bool
 */
function aidunite_user_is_privileged_admin($user_id = null) {
    $user_id = $user_id === null ? get_current_user_id() : (int) $user_id;
    if ($user_id <= 0) {
        return false;
    }

    if (user_can($user_id, 'administrator') || user_can($user_id, 'manage_options')) {
        return true;
    }

    $aidunite_role = (string) get_user_meta($user_id, 'aidunite_role', true);
    if ($aidunite_role === 'administrator') {
        return true;
    }

    $user_type = (string) get_user_meta($user_id, 'user_type', true);

    return $user_type === 'administrator';
}

/**
 * 管理者閲覧用のチーム ID（クエリ → 先頭チーム）
 *
 * @param int|null             $user_id
 * @param array<string, mixed> $sources team_id 等
 * @return int
 */
function aidunite_user_resolve_admin_team_id($user_id = null, array $sources = []) {
    $user_id = $user_id === null ? get_current_user_id() : (int) $user_id;
    if (!aidunite_user_is_privileged_admin($user_id)) {
        return 0;
    }

    foreach (['team_id', 'post_team_id'] as $key) {
        if (!empty($sources[$key])) {
            $team_id = (int) $sources[$key];
            if ($team_id > 0) {
                return $team_id;
            }
        }
    }

    $teams = get_posts([
        'post_type' => 'team',
        'post_status' => ['publish', 'pending', 'draft'],
        'posts_per_page' => 1,
        'orderby' => 'date',
        'order' => 'DESC',
        'fields' => 'ids',
    ]);

    return !empty($teams) ? (int) $teams[0] : 0;
}

/**
 * 認証・権限チェック結果クラス
 */
class AidUniteAuthResult {
    public $is_authenticated = false;
    public $is_authorized = false;
    public $user_id = 0;
    public $team_id = 0;
    public $user_role = '';
    public $error = null;
    public $error_code = '';
    public $redirect_url = '';

    public function __construct($authenticated = false, $authorized = false) {
        $this->is_authenticated = $authenticated;
        $this->is_authorized = $authorized;
    }

    public function is_valid() {
        // 認証が成功していて、エラーがない場合は有効
        // 管理者の場合はis_authorizedもチェック
        if ($this->is_authenticated && !empty($this->is_authorized)) {
            return empty($this->error);
        }
        return $this->is_authenticated && empty($this->error);
    }

    public function set_error($message, $code = 'auth_error', $redirect_url = '') {
        $this->error = $message;
        $this->error_code = $code;
        $this->redirect_url = $redirect_url;
        return $this;
    }
}

/**
 * 統一認証・権限チェックミドルウェアクラス
 */
class AidUniteAuthMiddleware {

    /**
     * 認証チェック（ログイン必須）
     *
     * @param bool $redirect 未ログイン時にリダイレクトするか
     * @param string $redirect_url リダイレクト先URL（空の場合はログインページ）
     * @return AidUniteAuthResult
     */
    public static function require_auth($redirect = false, $redirect_url = '') {
        $result = new AidUniteAuthResult();

        if (!is_user_logged_in()) {
            $result->set_error(
                'ログインが必要です',
                'authentication_required',
                $redirect_url ?: home_url('/login')
            );

            if ($redirect) {
                wp_redirect($result->redirect_url);
                exit;
            }

            return $result;
        }

        $result->is_authenticated = true;
        $result->user_id = get_current_user_id();
        $result->user_role = aidunite_get_user_role($result->user_id);
        if (aidunite_user_is_privileged_admin($result->user_id)) {
            $result->is_authorized = true;
        }

        return $result;
    }

    /**
     * チーム所属チェック
     *
     * @param int|null $required_team_id 特定のチームIDが必要な場合（nullの場合は任意のチーム所属）
     * @param bool $redirect 未所属時にリダイレクトするか
     * @return AidUniteAuthResult
     */
    public static function require_team_membership($required_team_id = null, $redirect = false) {
        $result = self::require_auth($redirect);

        if (!$result->is_authenticated) {
            return $result;
        }

        $uid = (int) $result->user_id;
        $managed = function_exists('aidunite_get_managed_team_ids') ? aidunite_get_managed_team_ids($uid) : [];
        $legacy = (int) get_user_meta($uid, 'team_id', true);
        $has_any_team = !empty($managed) || $legacy > 0;
        if (!$has_any_team && function_exists('aidunite_parent_user_has_any_team_affiliation')) {
            $has_any_team = aidunite_parent_user_has_any_team_affiliation($uid);
        }

        // システム管理者・Ainy 管理者はチーム未所属でも閲覧可
        if (aidunite_user_is_privileged_admin($uid)) {
            $result->is_authorized = true;
            $resolved_team = function_exists('aidunite_get_current_team_id')
                ? (int) aidunite_get_current_team_id($uid)
                : 0;
            if ($resolved_team <= 0) {
                $resolved_team = $legacy > 0 ? $legacy : 0;
            }
            if ($resolved_team <= 0) {
                $resolved_team = aidunite_user_resolve_admin_team_id($uid, $_GET);
            }
            $result->team_id = $resolved_team;
            return $result;
        }

        if (!$has_any_team) {
            $result->set_error(
                'チームに所属していません',
                'team_membership_required',
                home_url('/team-registration')
            );

            if ($redirect) {
                wp_redirect($result->redirect_url);
                exit;
            }

            return $result;
        }

        // 特定のチームIDが必要な場合（managed またはレガシー team_id と一致すること）
        if ($required_team_id !== null) {
            $req = (int) $required_team_id;
            $can = function_exists('aidunite_user_has_managed_team_access')
                ? aidunite_user_has_managed_team_access($uid, $req)
                : ($req > 0 && (int) $legacy === $req);

            if ($req <= 0 || !$can) {
                $result->set_error(
                    'このチームのメンバーではありません',
                    'team_access_denied',
                    home_url('/mypage')
                );

                if ($redirect) {
                    wp_redirect($result->redirect_url);
                    exit;
                }

                return $result;
            }
        }

        $result->is_authorized = true;
        $result->team_id = function_exists('aidunite_get_current_team_id')
            ? (int) aidunite_get_current_team_id($uid)
            : ($legacy > 0 ? $legacy : 0);

        // current が未設定で 0 のときはレガシー team_id にフォールバック（互換）
        if ($result->team_id <= 0 && $legacy > 0) {
            $result->team_id = $legacy;
        }

        return $result;
    }

    /**
     * ロールチェック
     *
     * @param string|array $required_roles 必要なロール（文字列または配列）
     * @param bool $redirect 権限不足時にリダイレクトするか
     * @return AidUniteAuthResult
     */
    public static function require_role($required_roles, $redirect = false) {
        $result = self::require_auth($redirect);

        if (!$result->is_authenticated) {
            return $result;
        }

        // システム管理者・Ainy 管理者は常に許可
        if (aidunite_user_is_privileged_admin($result->user_id)) {
            $result->is_authorized = true;
            return $result;
        }

        // ロールを配列に統一
        if (!is_array($required_roles)) {
            $required_roles = [$required_roles];
        }

        // ユーザーロールを取得
        $user_role = $result->user_role;

        if (!in_array($user_role, $required_roles, true)) {
            $result->set_error(
                'この操作を行う権限がありません',
                'insufficient_permissions',
                home_url('/mypage')
            );

            if ($redirect) {
                wp_redirect($result->redirect_url);
                exit;
            }

            return $result;
        }

        $result->is_authorized = true;

        return $result;
    }

    /**
     * チーム代表者チェック
     *
     * @param int|null $team_id チェックするチームID（nullの場合はユーザーの所属チーム）
     * @param bool $redirect 権限不足時にリダイレクトするか
     * @return AidUniteAuthResult
     */
    public static function require_team_leader($team_id = null, $redirect = false) {
        $result = self::require_team_membership($team_id, $redirect);

        if (!$result->is_authenticated || !$result->is_authorized) {
            return $result;
        }

        // システム管理者・Ainy 管理者は常に許可
        if (aidunite_user_is_privileged_admin($result->user_id)) {
            $result->is_authorized = true;
            return $result;
        }

        $check_team_id = (int) ($team_id ?: $result->team_id);

        $is_team_leader = false;
        if ($check_team_id > 0 && function_exists('aidunite_team_settings_user_is_leader_of_team')) {
            $is_team_leader = aidunite_team_settings_user_is_leader_of_team((int) $result->user_id, $check_team_id);
        } elseif ($check_team_id > 0 && function_exists('aidunite_team_resolve_leader_user_id')) {
            $resolved = aidunite_team_resolve_leader_user_id($check_team_id);
            $is_team_leader = $resolved > 0 && $resolved === (int) $result->user_id;
        }

        if (!$is_team_leader) {
            $result->set_error(
                'チーム代表者のみがこの操作を実行できます',
                'team_leader_required',
                home_url('/mypage')
            );

            if ($redirect) {
                wp_redirect($result->redirect_url);
                exit;
            }

            return $result;
        }

        $result->is_authorized = true;

        return $result;
    }

    /**
     * 複合チェック（認証 + チーム所属 + ロール）
     *
     * @param array $options オプション配列
     *   - 'team_id': 特定のチームIDが必要な場合
     *   - 'roles': 必要なロール（配列）
     *   - 'team_leader': チーム代表者が必要な場合（true）
     *   - 'redirect': リダイレクトするか（bool）
     * @return AidUniteAuthResult
     */
    public static function require($options = []) {
        $defaults = [
            'team_id' => null,
            'roles' => null,
            'team_leader' => false,
            'redirect' => false,
        ];

        $options = wp_parse_args($options, $defaults);

        // まず認証チェック
        $result = self::require_auth($options['redirect']);
        if (!$result->is_authenticated) {
            return $result;
        }

        // チーム所属チェック
        if ($options['team_id'] !== null || $options['team_leader']) {
            $result = self::require_team_membership($options['team_id'], $options['redirect']);
            if (!$result->is_authorized) {
                return $result;
            }
        }

        // チーム代表者チェック
        if ($options['team_leader']) {
            $result = self::require_team_leader($options['team_id'], $options['redirect']);
            if (!$result->is_authorized) {
                return $result;
            }
        }

        // ロールチェック
        if ($options['roles'] !== null) {
            $result = self::require_role($options['roles'], $options['redirect']);
            if (!$result->is_authorized) {
                return $result;
            }
        }

        return $result;
    }

    /**
     * REST API用の認証チェック
     *
     * @param WP_REST_Request $request
     * @param array $options オプション配列（require()と同じ）
     * @return AidUniteAuthResult|WP_Error
     */
    public static function rest_require($request, $options = []) {
        $result = self::require($options);

        if (!$result->is_valid()) {
            $status_code = 401;
            if ($result->error_code === 'team_membership_required' ||
                $result->error_code === 'team_access_denied' ||
                $result->error_code === 'insufficient_permissions' ||
                $result->error_code === 'team_leader_required') {
                $status_code = 403;
            }

            return new WP_Error(
                $result->error_code,
                $result->error,
                ['status' => $status_code]
            );
        }

        return $result;
    }

    /**
     * CSRF対策（nonce検証）
     *
     * @param string $nonce_name nonce名
     * @param string $action nonceアクション
     * @param string $method リクエストメソッド（POST/GET）
     * @return bool|WP_Error
     */
    public static function verify_nonce($nonce_name, $action, $method = 'POST') {
        $nonce_value = '';

        if ($method === 'POST') {
            $nonce_value = isset($_POST[$nonce_name]) ? $_POST[$nonce_name] : '';
        } elseif ($method === 'GET') {
            $nonce_value = isset($_GET[$nonce_name]) ? $_GET[$nonce_name] : '';
        } else {
            $nonce_value = isset($_REQUEST[$nonce_name]) ? $_REQUEST[$nonce_name] : '';
        }

        if (empty($nonce_value)) {
            return new WP_Error(
                'nonce_missing',
                'セキュリティトークンが送信されていません',
                ['status' => 403]
            );
        }

        if (!wp_verify_nonce($nonce_value, $action)) {
            return new WP_Error(
                'nonce_invalid',
                'セキュリティチェックに失敗しました',
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * データサニタイズ（統一処理）
     *
     * @param mixed $data サニタイズするデータ
     * @param string $type サニタイズタイプ（text, email, url, int, float, array）
     * @return mixed サニタイズされたデータ
     */
    public static function sanitize($data, $type = 'text') {
        if (is_array($data)) {
            return array_map(function($item) use ($type) {
                return self::sanitize($item, $type);
            }, $data);
        }

        switch ($type) {
            case 'email':
                return sanitize_email($data);
            case 'url':
                return esc_url_raw($data);
            case 'int':
                return intval($data);
            case 'float':
                return floatval($data);
            case 'textarea':
                return sanitize_textarea_field($data);
            case 'text':
            default:
                return sanitize_text_field($data);
        }
    }

    /**
     * チャット権限チェック
     *
     * @param int $room_id チャットルームID
     * @param int|null $user_id ユーザーID（nullの場合は現在のユーザー）
     * @param bool $redirect 権限不足時にリダイレクトするか
     * @return AidUniteAuthResult|bool
     */
    public static function require_chat_permission($room_id, $user_id = null, $redirect = false) {
        // まず認証チェック
        $result = self::require_auth($redirect);
        if (!$result->is_authenticated) {
            return $result;
        }

        $check_user_id = $user_id ?: $result->user_id;

        // 既存のチャット権限チェック関数を使用（後方互換性のため）
        if (function_exists('aidunite_check_chat_permission')) {
            $has_permission = aidunite_check_chat_permission($room_id, $check_user_id);
        } else {
            // フォールバック: 基本的な権限チェック
            global $wpdb;
            $room = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}chat_rooms WHERE id = %d",
                intval($room_id)
            ));

            if (!$room) {
                $result->set_error(
                    'チャットルームが見つかりません',
                    'chat_room_not_found',
                    home_url('/mypage')
                );
                return $result;
            }

            // ルームの team_id が managed（またはレガシー所属）に含まれるか
            $room_team = isset($room->team_id) ? (int) $room->team_id : 0;
            if ($room_team > 0 && function_exists('aidunite_user_has_managed_team_access')) {
                $has_permission = aidunite_user_has_managed_team_access($check_user_id, $room_team);
            } else {
                $user_team_id = function_exists('aidunite_get_current_team_id')
                    ? (int) aidunite_get_current_team_id((int) $check_user_id)
                    : (int) get_user_meta($check_user_id, 'team_id', true);
                $has_permission = ($user_team_id > 0 && $user_team_id === $room_team);
            }
        }

        if (!$has_permission) {
            $result->set_error(
                'このチャットルームへのアクセス権限がありません',
                'chat_permission_denied',
                home_url('/mypage')
            );

            if ($redirect) {
                wp_redirect($result->redirect_url);
                exit;
            }

            return $result;
        }

        $result->is_authorized = true;
        return $result;
    }

    /**
     * 決済権限チェック
     *
     * @param int|null $team_id チームID（nullの場合はユーザーの所属チーム）
     * @param bool $require_team_leader チーム代表者が必要か
     * @param bool $redirect 権限不足時にリダイレクトするか
     * @return AidUniteAuthResult
     */
    public static function require_payment_permission($team_id = null, $require_team_leader = false, $redirect = false) {
        // まず認証チェック
        $result = self::require_auth($redirect);
        if (!$result->is_authenticated) {
            return $result;
        }

        // システム管理者・Ainy 管理者は常に許可
        if (aidunite_user_is_privileged_admin($result->user_id)) {
            $result->is_authorized = true;
            return $result;
        }

        // チーム所属チェック
        $result = self::require_team_membership($team_id, $redirect);
        if (!$result->is_authorized) {
            return $result;
        }

        // チーム代表者が必要な場合
        if ($require_team_leader) {
            $result = self::require_team_leader($team_id, $redirect);
            if (!$result->is_authorized) {
                return $result;
            }
        }

        // 支払い制限チェック（未払いの場合は制限。導線無効時はスキップ）
        if (
            function_exists('aidunite_payment_user_flows_enabled')
            && aidunite_payment_user_flows_enabled()
            && function_exists('aidunite_is_payment_required')
        ) {
            $payment_required = aidunite_is_payment_required($result->user_id);
            if ($payment_required) {
                $status = function_exists('aidunite_get_payment_status')
                    ? aidunite_get_payment_status($result->user_id)
                    : 'unpaid';

                if ($status === 'unpaid') {
                    $result->set_error(
                        '支払いが必要です。決済設定を完了してください。',
                        'payment_required',
                        home_url('/payment-required')
                    );

                    if ($redirect) {
                        wp_redirect($result->redirect_url);
                        exit;
                    }

                    return $result;
                }
            }
        }

        $result->is_authorized = true;
        return $result;
    }

    /**
     * 管理者権限チェック
     *
     * @param bool $redirect 権限不足時にリダイレクトするか
     * @return AidUniteAuthResult
     */
    public static function require_admin($redirect = false) {
        // まず認証チェック
        $result = self::require_auth($redirect);
        if (!$result->is_authenticated) {
            return $result;
        }

        if (!aidunite_user_is_privileged_admin($result->user_id)) {
            $result->set_error(
                '管理者権限が必要です',
                'admin_required',
                home_url('/mypage')
            );

            if ($redirect) {
                wp_redirect($result->redirect_url);
                exit;
            }

            return $result;
        }

        $result->is_authorized = true;
        return $result;
    }
}

/**
 * ユーザーロール取得ヘルパー関数
 *
 * @param int $user_id ユーザーID
 * @return string ユーザーロール
 */
function aidunite_get_user_role($user_id = null) {
    if ($user_id === null) {
        $user_id = get_current_user_id();
    }

    if (!$user_id) {
        return 'public';
    }

    // 管理者チェック
    $user = get_userdata($user_id);
    if ($user && in_array('administrator', $user->roles)) {
        return 'administrator';
    }

    // aidunite_roleを取得
    $aidunite_role = get_user_meta($user_id, 'aidunite_role', true);
    $allowed_roles = ['team_leader', 'parent', 'player', 'supporter', 'match', 'public', 'general', 'administrator'];

    if ($aidunite_role === 'administrator') {
        return 'administrator';
    }

    if (in_array($aidunite_role, $allowed_roles, true)) {
        return $aidunite_role;
    }

    $user_type = (string) get_user_meta($user_id, 'user_type', true);
    if ($user_type === 'administrator') {
        return 'administrator';
    }

    return 'general';
}
