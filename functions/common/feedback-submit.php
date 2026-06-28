<?php
/**
 * フィードバックフォーム送信（REST → admin メール）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', static function () {
    register_rest_route('aidunite/v1', '/feedback', [
        'methods'             => 'POST',
        'callback'            => 'aidunite_rest_submit_feedback',
        'permission_callback' => static function () {
            return is_user_logged_in();
        },
        'args'                => [
            'feedbackType' => ['type' => 'string', 'required' => true],
            'priority'     => ['type' => 'string', 'required' => false],
            'title'        => ['type' => 'string', 'required' => true],
            'description'  => ['type' => 'string', 'required' => true],
            'environment'  => ['type' => 'array', 'required' => false],
            'browser'      => ['type' => 'string', 'required' => false],
            'email'        => ['type' => 'string', 'required' => false],
        ],
    ]);
});

add_action('wp_enqueue_scripts', static function () {
    if (!function_exists('aidunite_page_asset_matches') || !aidunite_page_asset_matches('feedback')) {
        return;
    }
    if (!function_exists('aidunite_page_asset_localize')) {
        return;
    }
    aidunite_page_asset_localize('feedback', [
        'restUrl' => esc_url_raw(rest_url('aidunite/v1/')),
        'nonce'   => wp_create_nonce('wp_rest'),
    ]);
}, 25);

/**
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function aidunite_rest_submit_feedback(WP_REST_Request $request) {
    $feedback_type = sanitize_text_field((string) $request->get_param('feedbackType'));
    $priority = sanitize_text_field((string) $request->get_param('priority'));
    $title = sanitize_text_field((string) $request->get_param('title'));
    $description = sanitize_textarea_field((string) $request->get_param('description'));
    $environment = $request->get_param('environment');
    $browser = sanitize_text_field((string) $request->get_param('browser'));
    $email = sanitize_email((string) $request->get_param('email'));

    if ($feedback_type === '' || $title === '' || $description === '') {
        return new WP_Error('invalid_params', '入力内容を確認してください。', ['status' => 400]);
    }

    if (strlen($description) > 8000) {
        return new WP_Error('message_too_long', '内容が長すぎます。', ['status' => 400]);
    }

    if ($email !== '' && !is_email($email)) {
        return new WP_Error('invalid_email', 'メールアドレスの形式が正しくありません。', ['status' => 400]);
    }

    $to = (string) get_option('admin_email');
    if ($to === '') {
        return new WP_Error('mail_unavailable', '送信先が設定されていません。', ['status' => 500]);
    }

    $env_list = [];
    if (is_array($environment)) {
        foreach ($environment as $item) {
            $env_list[] = sanitize_text_field((string) $item);
        }
    }
    $env_label = $env_list !== [] ? implode(', ', $env_list) : '未指定';
    $priority_label = $priority !== '' ? $priority : 'medium';

    $user_id = get_current_user_id();
    $user = get_userdata($user_id);
    $display_name = $user ? (string) $user->display_name : '';

    $mail_subject = sprintf('[Ainyフィードバック][%s] %s', $feedback_type, $title);
    $body = "種類: {$feedback_type}\n";
    $body .= "優先度: {$priority_label}\n";
    $body .= "ユーザーID: {$user_id}\n";
    if ($display_name !== '') {
        $body .= "表示名: {$display_name}\n";
    }
    if ($email !== '') {
        $body .= "連絡先: {$email}\n";
    }
    $body .= "環境: {$env_label}\n";
    if ($browser !== '') {
        $body .= "ブラウザ: {$browser}\n";
    }
    $body .= "\n---\n{$description}\n";

    $headers = ['Content-Type: text/plain; charset=UTF-8'];
    if ($email !== '') {
        $headers[] = 'Reply-To: ' . $email;
    }

    $sent = wp_mail($to, $mail_subject, $body, $headers);
    if (!$sent) {
        return new WP_Error('mail_failed', '送信に失敗しました。時間をおいて再度お試しください。', ['status' => 500]);
    }

    return rest_ensure_response([
        'success' => true,
        'message' => 'フィードバックを受け付けました。',
    ]);
}
