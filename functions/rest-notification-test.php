<?php
/**
 * REST API: 管理者用・通知テスト送信（ボタン1つで送信）
 * 仕様: docs/spec/notification.md
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {
    register_rest_route('aidunite/v1', '/notification-test', [
        'methods'             => 'POST',
        'callback'            => 'aidunite_rest_notification_test_send',
        'permission_callback' => function () {
            return current_user_can('manage_options');
        },
        'args' => [
            'user_id' => [
                'type'        => 'integer',
                'description' => '送信先ユーザーID。省略時は自分',
            ],
            'test_mail_recipient' => [
                'type'        => 'string',
                'description' => 'テストメールの To（省略時はサイトの admin_email）。許可リスト必須',
                'required'    => false,
            ],
        ],
    ]);
});

function aidunite_rest_notification_test_send(WP_REST_Request $request) {
    $user_id = $request->get_param('user_id');
    if ($user_id) {
        $user_id = (int) $user_id;
        if (!get_userdata($user_id)) {
            return new WP_Error('invalid_user', '指定されたユーザーが見つかりません', ['status' => 400]);
        }
    } else {
        $user_id = get_current_user_id();
    }

    if (!function_exists('aidunite_notification_send')) {
        return new WP_Error('not_available', '通知APIが読み込まれていません', ['status' => 500]);
    }

    // テストメールの To: リクエストで指定（JSON / クエリ）がなければ admin_email（チーム申請通知と同じ受信箱）
    $params = $request->get_json_params();
    $raw_override = null;
    if (is_array($params) && array_key_exists('test_mail_recipient', $params)) {
        $raw_override = $params['test_mail_recipient'];
    }
    if ($raw_override === null && $request->get_param('test_mail_recipient') !== null) {
        $raw_override = $request->get_param('test_mail_recipient');
    }

    $test_mail_recipient = '';
    if ($raw_override !== null && $raw_override !== '') {
        $test_mail_recipient = sanitize_email((string) $raw_override);
    } else {
        $admin = sanitize_email((string) get_option('admin_email'));
        $allow = aidunite_notification_get_test_mail_allowlist();
        if (is_email($admin) && in_array(strtolower($admin), $allow, true)) {
            $test_mail_recipient = $admin;
        }
    }

    $payload = [
        'title'   => '【テスト】通知のテスト送信',
        'message' => 'これは管理者によるテスト送信です。' . "\n" .
            '送信日時: ' . current_time('Y-m-d H:i:s') . "\n" .
            'お知らせ一覧に表示され、メールは許可された宛先へ送られます（既定はサイトの管理者メール。LINE は Notify 終了に伴い別経路未実装）。',
    ];
    if ($test_mail_recipient !== '') {
        $payload['test_mail_recipient'] = $test_mail_recipient;
    }

    $result = aidunite_notification_send($user_id, 'general', $payload);

    if (empty($result['success'])) {
        return new WP_REST_Response([
            'success' => false,
            'message' => $result['message'] ?? '送信に失敗しました',
        ], 400);
    }

    $mail = isset($result['mail']) && is_array($result['mail']) ? $result['mail'] : [];
    $mail_to = !empty($mail['to']) ? (string) $mail['to'] : '';
    $mail_ok = array_key_exists('ok', $mail) ? $mail['ok'] : null;
    $mail_err = !empty($mail['error']) ? (string) $mail['error'] : '';

    $message = 'テスト通知を送信しました。お知らせ一覧をご確認ください。';
    if (!empty($mail['attempted']) && $mail_ok === false) {
        $message = 'お知らせ一覧には通知を作成しましたが、メール送信に失敗しました。宛先: ' . $mail_to . '。理由: ' . ($mail_err !== '' ? $mail_err : 'wp_mail が false を返しました。ローカル環境では SMTP プラグイン（WP Mail SMTP 等）の設定が必要なことが多いです。');
    } elseif (!empty($mail['attempted']) && $mail_ok === true && $mail_to !== '') {
        $message .= ' メール送信処理は完了しました（宛先: ' . $mail_to . '）。';
        if (!empty($mail['bcc'])) {
            $message .= ' 診断用に管理者メール（' . $mail['bcc'] . '）へ Bcc しています。';
        }
        $message .= '届かない場合は迷惑メールフォルダやサーバーのメールログを確認してください。';
    }

    return rest_ensure_response([
        'success'          => true,
        'message'          => $message,
        'notification_id'  => $result['notification_id'] ?? null,
        'mail'             => $mail,
    ]);
}
