<?php
/**
 * お問い合わせフォーム送信（REST → admin メール）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', static function () {
    register_rest_route('aidunite/v1', '/contact', [
        'methods'             => 'POST',
        'callback'            => 'aidunite_rest_submit_contact',
        'permission_callback' => static function () {
            return true;
        },
        'args'                => [
            'name' => ['type' => 'string', 'required' => true],
            'email' => ['type' => 'string', 'required' => true],
            'subject' => ['type' => 'string', 'required' => true],
            'category' => ['type' => 'string', 'required' => false],
            'message' => ['type' => 'string', 'required' => true],
        ],
    ]);
});

add_action('wp_enqueue_scripts', static function () {
    if (!function_exists('aidunite_page_asset_matches') || !aidunite_page_asset_matches('contact')) {
        return;
    }
    if (!function_exists('aidunite_page_asset_localize')) {
        return;
    }
    aidunite_page_asset_localize('contact', [
        'restUrl' => esc_url_raw(rest_url('aidunite/v1/')),
        'nonce'   => wp_create_nonce('wp_rest'),
    ]);
}, 25);

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function aidunite_rest_submit_contact(WP_REST_Request $request) {
    $name = sanitize_text_field((string) $request->get_param('name'));
    $email = sanitize_email((string) $request->get_param('email'));
    $subject = sanitize_text_field((string) $request->get_param('subject'));
    $category = sanitize_text_field((string) $request->get_param('category'));
    $message = sanitize_textarea_field((string) $request->get_param('message'));

    if ($name === '' || $email === '' || !is_email($email) || $subject === '' || $message === '') {
        return new WP_Error('invalid_params', '入力内容を確認してください。', ['status' => 400]);
    }

    if (strlen($message) > 8000) {
        return new WP_Error('message_too_long', 'お問い合わせ内容が長すぎます。', ['status' => 400]);
    }

    $to = (string) get_option('admin_email');
    if ($to === '') {
        return new WP_Error('mail_unavailable', '送信先が設定されていません。', ['status' => 500]);
    }

    $user_id = get_current_user_id();
    $category_label = $category !== '' ? $category : 'general';
    $mail_subject = sprintf('[Ainyお問い合わせ][%s] %s', $category_label, $subject);
    $body = "お名前: {$name}\n";
    $body .= "メール: {$email}\n";
    $body .= "種別: {$category_label}\n";
    if ($user_id > 0) {
        $body .= "ユーザーID: {$user_id}\n";
    }
    $body .= "\n---\n{$message}\n";

    $headers = [
        'Content-Type: text/plain; charset=UTF-8',
        'Reply-To: ' . $name . ' <' . $email . '>',
    ];

    $sent = wp_mail($to, $mail_subject, $body, $headers);
    if (!$sent) {
        return new WP_Error('mail_failed', '送信に失敗しました。時間をおいて再度お試しください。', ['status' => 500]);
    }

    return rest_ensure_response([
        'success' => true,
        'message' => 'お問い合わせを受け付けました。',
    ]);
}
