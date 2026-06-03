<?php
/**
 * wp_mail 共通ラッパー（失敗理由の取得・ローカル SMTP・ログ）。
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ローカル環境で Mailpit へ SMTP 送信（任意）。
 *
 * Local 標準は PHP mail() → Mailpit 捕捉のため、デフォルトでは何もしない。
 * 明示的に wp-config.php で AIDUNITE_LOCAL_SMTP_PORT を定義したときのみ SMTP を使う。
 * 例: define('AIDUNITE_LOCAL_SMTP_PORT', 10001); // local-site.json の mailpit.SMTP
 *
 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer
 */
function aidunite_phpmailer_configure_local($phpmailer) {
    if (defined('AIDUNITE_DISABLE_LOCAL_SMTP') && AIDUNITE_DISABLE_LOCAL_SMTP) {
        return;
    }
    if (!defined('AIDUNITE_LOCAL_SMTP_PORT')) {
        return;
    }
    if (function_exists('wp_get_environment_type') && wp_get_environment_type() !== 'local') {
        return;
    }
    $host = defined('AIDUNITE_LOCAL_SMTP_HOST') ? (string) AIDUNITE_LOCAL_SMTP_HOST : '127.0.0.1';
    $port = (int) AIDUNITE_LOCAL_SMTP_PORT;
    if ($port <= 0) {
        return;
    }
    $phpmailer->isSMTP();
    $phpmailer->Host = $host;
    $phpmailer->Port = $port;
    $phpmailer->SMTPAuth = false;
}

add_action('phpmailer_init', 'aidunite_phpmailer_configure_local', 5);

/**
 * 送信元メール・表示名（wp_mail 用ヘッダー補完）。
 *
 * @return array{email:string,name:string}
 */
function aidunite_get_mail_from_identity() {
    $email = sanitize_email((string) get_option('admin_email'));
    if (!is_email($email)) {
        $email = 'noreply@' . wp_parse_url(home_url(), PHP_URL_HOST);
    }
    return [
        'email' => $email,
        'name' => 'Ainy',
    ];
}

/**
 * wp_mail を実行し成否とエラー文言を返す。
 *
 * @param string       $to
 * @param string       $subject
 * @param string       $message
 * @param string[]     $headers
 * @param string       $context ログ用コンテキスト
 * @return array{ok:bool,error:string}
 */
function aidunite_send_mail($to, $subject, $message, array $headers = [], $context = 'mail') {
    $to = sanitize_email($to);
    if (!is_email($to)) {
        return ['ok' => false, 'error' => 'invalid_to'];
    }

    $from = aidunite_get_mail_from_identity();
    $has_from = false;
    foreach ($headers as $h) {
        if (stripos((string) $h, 'From:') === 0) {
            $has_from = true;
            break;
        }
    }
    if (!$has_from) {
        $headers[] = 'From: ' . $from['name'] . ' <' . $from['email'] . '>';
    }
    $has_charset = false;
    foreach ($headers as $h) {
        if (stripos((string) $h, 'Content-Type:') === 0) {
            $has_charset = true;
            break;
        }
    }
    if (!$has_charset) {
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    }

    $error_message = '';
    $fail_cb = static function ($wp_error) use (&$error_message) {
        if ($wp_error instanceof WP_Error) {
            $error_message = $wp_error->get_error_message();
        }
    };
    add_action('wp_mail_failed', $fail_cb, 10, 1);

    $ok = wp_mail($to, $subject, $message, $headers);

    remove_action('wp_mail_failed', $fail_cb, 10);

    if (!$ok && $error_message === '') {
        $error_message = 'wp_mail returned false';
    }

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log(
            '[Ainy mail][' . $context . '] '
            . ($ok ? 'OK' : 'FAIL')
            . ' to=' . $to
            . ($error_message !== '' ? ' err=' . $error_message : '')
        );
    }

    return [
        'ok' => (bool) $ok,
        'error' => $error_message,
    ];
}
