<?php
/**
 * 管理者ログインセキュリティ（wp-login.php）
 *
 * - 10回以下の失敗でロック
 * - manage_options ユーザー向けメール OTP 二段階認証
 *
 * IP 制限 / ベーシック認証はサーバー側（docs/server/wp-admin-basic-auth.example.md）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once get_stylesheet_directory() . '/functions/user/admin-security-persist.php';

/**
 * wp_login_failed で失敗カウントをスキップするフラグ
 *
 * @param bool $skip
 * @return void
 */
function aidunite_admin_security_set_skip_failure_increment($skip = true) {
    $GLOBALS['aidunite_admin_security_skip_failure_increment'] = (bool) $skip;
}

/**
 * @return bool
 */
function aidunite_admin_security_should_skip_failure_increment() {
    return !empty($GLOBALS['aidunite_admin_security_skip_failure_increment']);
}

/**
 * @param string $username
 * @return WP_User|null
 */
function aidunite_admin_security_resolve_user_by_login($username) {
    $username = trim((string) $username);
    if ($username === '') {
        return null;
    }

    $user = get_user_by('login', $username);
    if ($user instanceof WP_User) {
        return $user;
    }

    if (is_email($username)) {
        $user = get_user_by('email', $username);
        if ($user instanceof WP_User) {
            return $user;
        }
    }

    return null;
}

/**
 * @param WP_User|WP_Error|null $user
 * @param string                $username
 * @param string                $password
 * @return WP_User|WP_Error|null
 */
function aidunite_admin_security_authenticate_lock_check($user, $username, $password = '') {
    unset($password);
    if ($user instanceof WP_Error) {
        return $user;
    }

    $candidate = null;
    if ($user instanceof WP_User) {
        $candidate = $user;
    } else {
        $candidate = aidunite_admin_security_resolve_user_by_login($username);
    }

    if (!$candidate instanceof WP_User || !aidunite_admin_security_is_privileged_user($candidate)) {
        return $user;
    }

    $message = aidunite_admin_security_read_login_lock_message($candidate->ID);
    if ($message !== '') {
        return new WP_Error('aidunite_admin_locked', $message);
    }

    return $user;
}

/**
 * @param string $username
 * @return void
 */
function aidunite_admin_security_on_login_failed($username) {
    if (aidunite_admin_security_should_skip_failure_increment()) {
        aidunite_admin_security_set_skip_failure_increment(false);

        return;
    }

    $user = aidunite_admin_security_resolve_user_by_login($username);
    if (!$user instanceof WP_User) {
        return;
    }

    aidunite_admin_security_record_login_failure($user->ID);
}

/**
 * @param WP_User|WP_Error $user
 * @param string           $password
 * @return WP_User|WP_Error
 */
function aidunite_admin_security_require_2fa($user, $password) {
    unset($password);

    if (is_wp_error($user)) {
        return $user;
    }

    if (!aidunite_admin_security_is_privileged_user($user)) {
        return $user;
    }

    if (!empty($_POST['aidunite_admin_2fa_verify'])) {
        return $user;
    }

    $challenge = aidunite_admin_security_issue_otp_challenge($user->ID);
    if (is_wp_error($challenge)) {
        return $challenge;
    }

    aidunite_admin_security_set_skip_failure_increment(true);

    $verify_url = add_query_arg(
        [
            'aidunite_2fa_token' => rawurlencode((string) $challenge['token']),
            'aidunite_2fa_user' => rawurlencode((string) $user->user_login),
        ],
        wp_login_url()
    );

    return new WP_Error(
        'aidunite_admin_2fa_required',
        sprintf(
            'セキュリティのため、確認コードをメールで送信しました。<a href="%s">確認コード入力画面</a>へ進んでください。',
            esc_url($verify_url)
        )
    );
}

/**
 * OTP 入力フォーム（wp-login.php）
 *
 * @return void
 */
function aidunite_admin_security_render_2fa_login_form() {
    $token = sanitize_text_field(wp_unslash($_GET['aidunite_2fa_token'] ?? ''));
    if ($token === '') {
        return;
    }

    $user_login = sanitize_text_field(wp_unslash($_GET['aidunite_2fa_user'] ?? ''));
    ?>
    <input type="hidden" name="aidunite_admin_2fa_verify" value="1" />
    <input type="hidden" name="aidunite_admin_2fa_token" value="<?php echo esc_attr($token); ?>" />
    <?php if ($user_login !== '') : ?>
        <input type="hidden" name="log" value="<?php echo esc_attr($user_login); ?>" />
    <?php endif; ?>
    <p>
        <label for="aidunite_admin_2fa_code"><?php esc_html_e('メールで受け取った6桁の確認コード', 'aidunite'); ?></label>
        <input type="text"
               name="aidunite_admin_2fa_code"
               id="aidunite_admin_2fa_code"
               class="input"
               value=""
               size="20"
               autocomplete="one-time-code"
               inputmode="numeric"
               maxlength="6"
               required />
    </p>
    <?php
}

/**
 * @param string $message
 * @return string
 */
function aidunite_admin_security_login_message($message) {
    if (!empty($_GET['aidunite_2fa_error'])) {
        $message .= '<div id="login_error" class="notice notice-error"><p>確認コードの認証に失敗しました。もう一度お試しください。</p></div>';
    }

    $token = sanitize_text_field(wp_unslash($_GET['aidunite_2fa_token'] ?? ''));
    if ($token !== '') {
        $message .= '<p class="message">メールに届いた6桁の確認コードを入力してください。</p>';
    }

    return $message;
}

/**
 * OTP 検証 POST（パスワード再入力なし）
 *
 * @return void
 */
function aidunite_admin_security_handle_2fa_verify_post() {
    if (empty($_POST['aidunite_admin_2fa_verify'])) {
        return;
    }

    if (!isset($_POST['aidunite_admin_2fa_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['aidunite_admin_2fa_nonce'])), 'aidunite_admin_2fa')) {
        wp_safe_redirect(add_query_arg('aidunite_2fa_error', '1', wp_login_url()));
        exit;
    }

    $token = sanitize_text_field(wp_unslash($_POST['aidunite_admin_2fa_token'] ?? ''));
    $code = aidunite_admin_security_normalize_otp_code(wp_unslash($_POST['aidunite_admin_2fa_code'] ?? ''));

    $user_id = aidunite_admin_security_verify_otp_challenge($token, $code);
    if (is_wp_error($user_id)) {
        $redirect = add_query_arg(
            [
                'aidunite_2fa_error' => '1',
                'aidunite_2fa_token' => rawurlencode($token),
                'aidunite_2fa_user' => rawurlencode(sanitize_text_field(wp_unslash($_POST['log'] ?? ''))),
            ],
            wp_login_url()
        );
        wp_safe_redirect($redirect);
        exit;
    }

    $user = get_userdata((int) $user_id);
    if (!$user instanceof WP_User) {
        wp_safe_redirect(add_query_arg('aidunite_2fa_error', '1', wp_login_url()));
        exit;
    }

    aidunite_admin_security_reset_login_guard((int) $user_id);

    wp_clear_auth_cookie();
    wp_set_current_user((int) $user_id);
    wp_set_auth_cookie((int) $user_id, !empty($_POST['rememberme']));
    do_action('wp_login', $user->user_login, $user);

    $redirect_to = isset($_REQUEST['redirect_to']) ? wp_validate_redirect(wp_unslash($_REQUEST['redirect_to']), admin_url()) : admin_url();
    wp_safe_redirect($redirect_to);
    exit;
}

/**
 * フロント /login から管理者ログインを wp-login へ誘導
 *
 * @param WP_User $user_obj
 * @return WP_Error|null
 */
function aidunite_admin_security_block_frontend_admin_login($user_obj) {
    if (!$user_obj instanceof WP_User || !aidunite_admin_security_is_privileged_user($user_obj)) {
        return null;
    }

    $lock_message = aidunite_admin_security_read_login_lock_message($user_obj->ID);
    if ($lock_message !== '') {
        return new WP_Error('aidunite_admin_locked', $lock_message);
    }

    return new WP_Error(
        'aidunite_admin_wp_login_required',
        '管理者アカウントはセキュリティのため、WordPress 管理画面のログイン（/wp-admin）からログインしてください。'
    );
}

add_filter('authenticate', 'aidunite_admin_security_authenticate_lock_check', 25, 3);
add_action('wp_login_failed', 'aidunite_admin_security_on_login_failed');
add_filter('wp_authenticate_user', 'aidunite_admin_security_require_2fa', 30, 2);
add_action('wp_login', function ($user_login, $user) {
    unset($user_login);
    if ($user instanceof WP_User && aidunite_admin_security_is_privileged_user($user)) {
        aidunite_admin_security_reset_login_guard($user->ID);
    }
}, 10, 2);
add_action('login_init', 'aidunite_admin_security_handle_2fa_verify_post', 1);
add_action('login_form', 'aidunite_admin_security_render_2fa_login_form');
add_filter('login_message', 'aidunite_admin_security_login_message');

add_action('login_form', function () {
    if (!empty($_GET['aidunite_2fa_token'])) {
        wp_nonce_field('aidunite_admin_2fa', 'aidunite_admin_2fa_nonce');
    }
}, 5);

add_action('login_enqueue_scripts', function () {
    if (empty($_GET['aidunite_2fa_token'])) {
        return;
    }
    wp_add_inline_style('login', '#loginform .user-pass-wrap, #loginform .forgetmenot { display: none !important; }');
});
