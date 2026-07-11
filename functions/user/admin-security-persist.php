<?php
/**
 * 管理者ログインセキュリティ（usermeta 正本）
 *
 * Stripe セキュリティ申告 セクション1 向け:
 * - ログイン失敗カウント / ロックアウト
 * - 二段階認証 OTP チャレンジ（transient）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/** ロックまでの最大失敗回数（チェックリスト: 10回以下） */
if (!defined('AIDUNITE_ADMIN_LOGIN_MAX_FAILURES')) {
    define('AIDUNITE_ADMIN_LOGIN_MAX_FAILURES', 10);
}

/** ロック継続秒数（30分） */
if (!defined('AIDUNITE_ADMIN_LOGIN_LOCK_SECONDS')) {
    define('AIDUNITE_ADMIN_LOGIN_LOCK_SECONDS', 30 * MINUTE_IN_SECONDS);
}

/** OTP 有効秒数（10分） */
if (!defined('AIDUNITE_ADMIN_OTP_TTL_SECONDS')) {
    define('AIDUNITE_ADMIN_OTP_TTL_SECONDS', 10 * MINUTE_IN_SECONDS);
}

/**
 * @param mixed $user WP_User|int|null
 * @return bool
 */
function aidunite_admin_security_is_privileged_user($user) {
    if ($user instanceof WP_User) {
        return user_can($user, 'manage_options');
    }
    $user_id = (int) $user;
    if ($user_id <= 0) {
        return false;
    }

    return user_can($user_id, 'manage_options');
}

/**
 * @param mixed $code
 * @return string
 */
function aidunite_admin_security_normalize_otp_code($code) {
    return preg_replace('/\D+/', '', (string) $code);
}

/**
 * @param int $user_id
 * @return array{fail_count:int,locked_until:int,is_locked:bool}
 */
function aidunite_admin_security_read_login_guard($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return [
            'fail_count' => 0,
            'locked_until' => 0,
            'is_locked' => false,
        ];
    }

    $fail_count = max(0, (int) get_user_meta($user_id, 'aidunite_admin_login_fail_count', true));
    $locked_until = max(0, (int) get_user_meta($user_id, 'aidunite_admin_login_locked_until', true));
    $now = time();

    if ($locked_until > 0 && $locked_until <= $now) {
        $fail_count = 0;
        $locked_until = 0;
        delete_user_meta($user_id, 'aidunite_admin_login_fail_count');
        delete_user_meta($user_id, 'aidunite_admin_login_locked_until');
    }

    return [
        'fail_count' => $fail_count,
        'locked_until' => $locked_until,
        'is_locked' => $locked_until > $now,
    ];
}

/**
 * @param int $user_id
 * @return string ロック中メッセージ（ロックなしは空）
 */
function aidunite_admin_security_read_login_lock_message($user_id) {
    $guard = aidunite_admin_security_read_login_guard($user_id);
    if (empty($guard['is_locked'])) {
        return '';
    }

    $remaining = max(1, (int) ceil(($guard['locked_until'] - time()) / 60));

    return sprintf(
        'ログイン試行回数が上限に達したため、アカウントを一時的にロックしました。%d 分後に再度お試しください。',
        $remaining
    );
}

/**
 * @param int $user_id
 * @return void
 */
function aidunite_admin_security_record_login_failure($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0 || !aidunite_admin_security_is_privileged_user($user_id)) {
        return;
    }

    $guard = aidunite_admin_security_read_login_guard($user_id);
    if (!empty($guard['is_locked'])) {
        return;
    }

    $fail_count = (int) $guard['fail_count'] + 1;
    update_user_meta($user_id, 'aidunite_admin_login_fail_count', $fail_count);

    if ($fail_count >= AIDUNITE_ADMIN_LOGIN_MAX_FAILURES) {
        $locked_until = time() + (int) AIDUNITE_ADMIN_LOGIN_LOCK_SECONDS;
        update_user_meta($user_id, 'aidunite_admin_login_locked_until', $locked_until);
    }
}

/**
 * @param int $user_id
 * @return void
 */
function aidunite_admin_security_reset_login_guard($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return;
    }

    delete_user_meta($user_id, 'aidunite_admin_login_fail_count');
    delete_user_meta($user_id, 'aidunite_admin_login_locked_until');
}

/**
 * @param int $user_id
 * @return array{token:string,expires:int}|WP_Error
 */
function aidunite_admin_security_issue_otp_challenge($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0 || !aidunite_admin_security_is_privileged_user($user_id)) {
        return new WP_Error('invalid_user', '二段階認証の対象ユーザーが不正です。');
    }

    $user = get_userdata($user_id);
    if (!$user || !is_email($user->user_email)) {
        return new WP_Error('invalid_email', '管理者メールアドレスが未設定のため、確認コードを送信できません。');
    }

    $code = (string) wp_rand(100000, 999999);
    $token = wp_generate_password(32, false, false);
    $expires = time() + (int) AIDUNITE_ADMIN_OTP_TTL_SECONDS;

    $payload = [
        'user_id' => $user_id,
        'otp_hash' => wp_hash_password($code),
        'expires' => $expires,
        'attempts' => 0,
    ];

    set_transient('aidunite_admin_2fa_' . $token, $payload, AIDUNITE_ADMIN_OTP_TTL_SECONDS);

    $subject = '【Ainy】管理者ログイン確認コード';
    $message = "管理者ログインの確認コードです。\n\n"
        . "確認コード: {$code}\n"
        . '有効期限: ' . wp_date('Y-m-d H:i', $expires) . "\n\n"
        . "心当たりがない場合は、このメールを破棄してください。\n";

    $mail = function_exists('aidunite_send_mail')
        ? aidunite_send_mail($user->user_email, $subject, $message, [], 'admin_2fa_otp')
        : ['ok' => (bool) wp_mail($user->user_email, $subject, $message)];

    if (empty($mail['ok'])) {
        delete_transient('aidunite_admin_2fa_' . $token);

        return new WP_Error('otp_mail_failed', '確認コードの送信に失敗しました。時間をおいて再度お試しください。');
    }

    return [
        'token' => $token,
        'expires' => $expires,
    ];
}

/**
 * @param string $token
 * @param string $code
 * @return int|WP_Error user_id
 */
function aidunite_admin_security_verify_otp_challenge($token, $code) {
    $token = sanitize_text_field((string) $token);
    $code = aidunite_admin_security_normalize_otp_code($code);

    if ($token === '' || strlen($code) !== 6) {
        return new WP_Error('invalid_otp', '確認コードが正しくありません。');
    }

    $key = 'aidunite_admin_2fa_' . $token;
    $payload = get_transient($key);
    if (!is_array($payload) || empty($payload['user_id'])) {
        return new WP_Error('otp_expired', '確認コードの有効期限が切れました。最初からログインし直してください。');
    }

    $expires = (int) ($payload['expires'] ?? 0);
    if ($expires > 0 && $expires < time()) {
        delete_transient($key);

        return new WP_Error('otp_expired', '確認コードの有効期限が切れました。最初からログインし直してください。');
    }

    $attempts = (int) ($payload['attempts'] ?? 0) + 1;
    $payload['attempts'] = $attempts;
    set_transient($key, $payload, max(60, $expires - time()));

    if ($attempts > 5) {
        delete_transient($key);

        return new WP_Error('otp_locked', '確認コードの入力回数が上限に達しました。最初からログインし直してください。');
    }

    $hash = (string) ($payload['otp_hash'] ?? '');
    if ($hash === '' || !wp_check_password($code, $hash)) {
        return new WP_Error('invalid_otp', '確認コードが正しくありません。');
    }

    $user_id = (int) $payload['user_id'];
    delete_transient($key);

    if ($user_id <= 0 || !aidunite_admin_security_is_privileged_user($user_id)) {
        return new WP_Error('invalid_user', '二段階認証の対象ユーザーが不正です。');
    }

    return $user_id;
}
