<?php
/**
 * 会員登録機能
 * TUNAGERU 新規会員登録処理
 */

/*--------------------------------------------------------------
  会員登録処理
--------------------------------------------------------------*/

/**
 * 登録データから姓・名・表示名を正規化（レガシー user_name 1項目にも対応）。
 *
 * @param array<string, mixed> $data
 * @return array{last_name:string,first_name:string,display_name:string}
 */
function aidunite_registration_name_from_data(array $data) {
    $last_name = sanitize_text_field($data['last_name'] ?? '');
    $first_name = sanitize_text_field($data['first_name'] ?? '');

    if ($last_name === '' && $first_name === '' && !empty($data['user_name'])) {
        $full = sanitize_text_field((string) $data['user_name']);
        return [
            'last_name' => '',
            'first_name' => $full,
            'display_name' => $full,
        ];
    }

    $display_name = trim($last_name . ' ' . $first_name);
    if ($display_name === '' && ($last_name !== '' || $first_name !== '')) {
        $display_name = $last_name . $first_name;
    }

    return [
        'last_name' => $last_name,
        'first_name' => $first_name,
        'display_name' => $display_name,
    ];
}

/**
 * 仮登録未完了（pending）ユーザーの再送信・パスワード更新。
 *
 * @param int                  $user_id
 * @param array<string, mixed> $registration_data
 * @return array<string, mixed>
 */
function aidunite_refresh_pending_registration($user_id, array $registration_data) {
    $user_id = (int) $user_id;
    $user = get_userdata($user_id);
    if (!$user) {
        return ['success' => false, 'message' => 'ユーザーが見つかりません。'];
    }

    $names = aidunite_registration_name_from_data($registration_data);
    wp_update_user([
        'ID' => $user_id,
        'user_pass' => $registration_data['password'] ?? '',
        'last_name' => $names['last_name'] !== '' ? $names['last_name'] : $user->last_name,
        'first_name' => $names['first_name'] !== '' ? $names['first_name'] : $user->first_name,
        'display_name' => $names['display_name'] !== '' ? $names['display_name'] : $user->display_name,
    ]);

    $token = function_exists('aidunite_user_persist_provisional_registration_token')
        ? aidunite_user_persist_provisional_registration_token($user_id)
        : bin2hex(random_bytes(16));
    aidunite_update_user_registration_status_meta($user_id, 'pending');

    $mail_result = aidunite_send_activation_email($user_id, $registration_data, $token);
    $redirect_url = home_url('/member-registration?pending=1');
    if (empty($mail_result['ok'])) {
        $redirect_url = add_query_arg('mail_failed', '1', $redirect_url);
    }

    return [
        'success' => true,
        'user_id' => $user_id,
        'resent' => true,
        'mail_sent' => !empty($mail_result['ok']),
        'mail_error' => $mail_result['error'] ?? '',
        'redirect_url' => $redirect_url,
    ];
}

function aidunite_register_member($registration_data) {
    $normalized_email = function_exists('aidunite_normalize_email')
        ? aidunite_normalize_email($registration_data['user_email'])
        : sanitize_email($registration_data['user_email']);

    // 仮登録未完了の同一メールは再送信（email_exists でブロックしない）
    $existing_user_id = $normalized_email !== '' ? email_exists($normalized_email) : false;
    if ($existing_user_id) {
        $existing_user_id = (int) $existing_user_id;
        $pending_status = (string) get_user_meta($existing_user_id, 'registration_status', true);
        if ($pending_status === 'pending') {
            $reshow_errors = [];
            if (empty($registration_data['password']) || strlen($registration_data['password']) < 8) {
                $reshow_errors[] = 'パスワードは8文字以上で入力してください';
            }
            if (
                !empty($registration_data['password'])
                && !empty($registration_data['password_confirm'])
                && $registration_data['password'] !== $registration_data['password_confirm']
            ) {
                $reshow_errors[] = 'パスワードが一致しません';
            }
            if (empty($registration_data['agree_terms'])) {
                $reshow_errors[] = '利用規約に同意してください';
            }
            if ($reshow_errors !== []) {
                return ['valid' => false, 'errors' => $reshow_errors];
            }
            return aidunite_refresh_pending_registration($existing_user_id, $registration_data);
        }
    }

    // バリデーション
    $validation_result = aidunite_validate_registration_data($registration_data);
    if (!$validation_result['valid']) {
        return $validation_result;
    }

    $names = aidunite_registration_name_from_data($registration_data);

    // ユーザー作成（メールは正規化: 全角→半角・小文字）
    $user_data = [
        'user_login' => $normalized_email, // メールアドレスをuser_loginにセット
        'user_email' => $normalized_email,
        'user_pass' => $registration_data['password'],
        'last_name' => $names['last_name'],
        'first_name' => $names['first_name'],
        'display_name' => $names['display_name'],
        'role' => 'subscriber'
    ];

    $user_id = wp_insert_user($user_data);

    if (is_wp_error($user_id)) {
        return [
            'success' => false,
            'message' => 'ユーザー登録に失敗しました: ' . $user_id->get_error_message()
        ];
    }

    // ユーザーメタデータを保存
    $meta_fields = [
        'registration_date' => current_time('mysql'),
        'registration_status' => 'pending'
    ];

    foreach ($meta_fields as $key => $value) {
        if ($key === 'registration_status') {
            aidunite_update_user_registration_status_meta($user_id, $value);
            continue;
        }
        update_user_meta($user_id, $key, $value);
    }

    $token = function_exists('aidunite_user_persist_provisional_registration_token')
        ? aidunite_user_persist_provisional_registration_token($user_id)
        : bin2hex(random_bytes(16));

    // 本登録メール送信
    $mail_result = aidunite_send_activation_email($user_id, $registration_data, $token);
    $redirect_url = home_url('/member-registration?pending=1');
    if (empty($mail_result['ok'])) {
        $redirect_url = add_query_arg('mail_failed', '1', $redirect_url);
    }

    return [
        'success' => true,
        'user_id' => $user_id,
        'mail_sent' => !empty($mail_result['ok']),
        'mail_error' => $mail_result['error'] ?? '',
        'redirect_url' => $redirect_url,
    ];
}

/*--------------------------------------------------------------
  登録データバリデーション
--------------------------------------------------------------*/
function aidunite_validate_registration_data($data) {
    $errors = [];

    // 必須フィールドチェック
    $required_fields = ['last_name', 'first_name', 'user_email', 'password', 'agree_terms'];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            $errors[] = '必須項目が入力されていません: ' . aidunite_get_field_label($field);
        }
    }

    // メールアドレス形式チェック
    if (!empty($data['user_email']) && !is_email($data['user_email'])) {
        $errors[] = '有効なメールアドレスを入力してください';
    }

    // メールアドレスの重複チェック（照合時も正規化）
    if (!empty($data['user_email'])) {
        $email_for_check = function_exists('aidunite_normalize_email') ? aidunite_normalize_email($data['user_email']) : $data['user_email'];
        if (email_exists($email_for_check)) {
            $errors[] = 'このメールアドレスは既に登録されています';
        }
    }

    // パスワード強度チェック
    if (!empty($data['password']) && strlen($data['password']) < 8) {
        $errors[] = 'パスワードは8文字以上で入力してください';
    }

    // パスワード確認チェック
    if (!empty($data['password']) && !empty($data['password_confirm']) &&
        $data['password'] !== $data['password_confirm']) {
        $errors[] = 'パスワードが一致しません';
    }

    // 同意チェック
    if (empty($data['agree_terms'])) {
        $errors[] = '利用規約に同意してください';
    }

    if (!empty($errors)) {
        return [
            'valid' => false,
            'errors' => $errors
        ];
    }

    return ['valid' => true];
}

/*--------------------------------------------------------------
  フィールドラベル取得
--------------------------------------------------------------*/
function aidunite_get_field_label($field_name) {
    $labels = [
        'last_name' => '姓',
        'first_name' => '名',
        'user_name' => 'お名前',
        'user_email' => 'メールアドレス',
        'password' => 'パスワード',
        'password_confirm' => 'パスワード（確認）',
        'agree_terms' => '利用規約同意'
    ];

    return $labels[$field_name] ?? $field_name;
}

/*--------------------------------------------------------------
  初回登録完了メール送信
--------------------------------------------------------------*/
function aidunite_send_welcome_email($user_id, $registration_data) {
    $user = get_userdata($user_id);
    if (!$user) {
        return ['ok' => false, 'error' => 'user_not_found'];
    }

    $names = aidunite_registration_name_from_data($registration_data);
    $greeting_name = $names['display_name'] !== '' ? $names['display_name'] : ($user->display_name ?: $user->user_login);
    $redirect_url = home_url('/mypage/');

    $subject = '【Ainy】ご登録ありがとうございます';

    $message = "{$greeting_name}さん\n\n";
    $message .= "Ainyへのご登録ありがとうございます！\n";
    $message .= "今後はこちらのマイページからご利用いただけます。\n\n";
    $message .= "▶︎ マイページ：{$redirect_url}\n\n";
    $message .= "ご不明点があればお気軽にお問い合わせください。\n\n";
    $message .= "Ainy運営チーム";

    if (!function_exists('aidunite_send_mail')) {
        return ['ok' => false, 'error' => 'mail_utils_missing'];
    }

    return aidunite_send_mail($user->user_email, $subject, $message, [], 'welcome');
}

/**
 * 本登録リンクのトークン検証・メタ更新・ウェルカムメール。
 *
 * @param int    $user_id
 * @param string $token
 * @return string success|invalid|expired|missing|already_completed
 */
function aidunite_complete_registration_with_token($user_id, $token) {
    $user_id = (int) $user_id;
    $token = (string) $token;

    if ($user_id <= 0 || $token === '') {
        return 'missing';
    }

    $user = get_userdata($user_id);
    if (!$user) {
        return 'invalid';
    }

    $status = (string) get_user_meta($user_id, 'registration_status', true);
    if ($status === 'accepted') {
        return 'already_completed';
    }

    $saved_token = (string) get_user_meta($user_id, 'registration_token', true);
    $token_time = (int) get_user_meta($user_id, 'registration_token_time', true);

    if ($saved_token === '' || !hash_equals($saved_token, $token)) {
        return 'invalid';
    }

    if ($token_time > 0 && (time() - $token_time) > 86400) {
        return 'expired';
    }

    if (function_exists('aidunite_user_persist_clear_provisional_registration_token')) {
        aidunite_user_persist_clear_provisional_registration_token($user_id);
    } else {
        delete_user_meta($user_id, 'registration_token');
        delete_user_meta($user_id, 'registration_token_time');
    }
    aidunite_update_user_registration_status_meta($user_id, 'accepted');
    update_user_meta($user_id, 'user_status', 0);

    // Ainy 表示ロール（aidunite_role）: /member-register 経由の本登録は general（一般）
    // team_leader / parent / player 等は各専用フロー（チーム申請・招待）で別途付与
    $existing_aidunite_role = (string) get_user_meta($user_id, 'aidunite_role', true);
    if ($existing_aidunite_role === '' && function_exists('aidunite_set_user_type')) {
        $team_name_for_role = (string) get_user_meta($user_id, 'team_name', true);
        $default_role = $team_name_for_role !== '' ? 'team_leader' : 'general';
        aidunite_set_user_type($user_id, $default_role);
    }

    $registration_source = (string) get_user_meta($user_id, 'registration_source', true);
    $is_guardian_signup = strpos($registration_source, 'guardian_invite') === 0;

    if (!$is_guardian_signup && function_exists('aidunite_send_welcome_email')) {
        aidunite_send_welcome_email($user_id, [
            'last_name' => $user->last_name,
            'first_name' => $user->first_name,
            'user_email' => $user->user_email,
        ]);
    }

    if (function_exists('do_action')) {
        do_action('aidunite_registration_accepted', $user_id);
    }

    $team_name = (string) get_user_meta($user_id, 'team_name', true);
    if ($team_name !== '' && function_exists('create_team_page_for_user')) {
        wp_update_user([
            'ID' => $user_id,
            'role' => 'author',
        ]);
        create_team_page_for_user($user_id, $team_name);
    }

    return 'success';
}

/**
 * @return string success|invalid|expired|missing|already_completed
 */
function aidunite_resolve_approve_registration_view_state() {
    if (isset($_GET['approved']) && (string) $_GET['approved'] === '1') {
        return 'success';
    }

    $user_id = isset($_GET['user_id']) ? absint($_GET['user_id']) : 0;
    $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';

    if ($user_id <= 0 || $token === '') {
        return 'missing';
    }

    $result = aidunite_complete_registration_with_token($user_id, $token);
    if ($result === 'success') {
        $redirect_args = ['approved' => '1'];
        $registration_source = (string) get_user_meta($user_id, 'registration_source', true);
        if (strpos($registration_source, 'guardian_invite') === 0) {
            $redirect_args['flow'] = 'guardian';
            $invite_type = (string) get_user_meta($user_id, 'guardian_invite_type', true);
            if ($invite_type === 'qr') {
                $redirect_args['team_pending'] = '1';
            }
        }
        wp_safe_redirect(add_query_arg($redirect_args, home_url('/approve-registration/')));
        exit;
    }

    return $result;
}

function aidunite_send_activation_email($user_id, $registration_data, $token) {
    $user = get_userdata($user_id);
    if (!$user) {
        return ['ok' => false, 'error' => 'user_not_found'];
    }

    $names = aidunite_registration_name_from_data($registration_data);
    $greeting_name = $names['display_name'] !== '' ? $names['display_name'] : ($user->display_name ?: $user->user_login);
    $confirm_url = add_query_arg([
        'user_id' => $user_id,
        'token'   => $token,
    ], home_url('/approve-registration'));

    $subject = '【Ainy】本登録のご案内';

    $message = "{$greeting_name}さん\n\n";
    $message .= "Ainyへの仮登録が完了しました。\n";
    $message .= "下記リンクから、本登録を完了してください。\n\n";
    $message .= "▼本登録はこちら\n{$confirm_url}\n\n";
    $message .= "※このリンクは24時間以内に有効です。\n";
    $message .= "期限を過ぎた場合は、再度登録をお願いいたします。\n\n";
    $message .= "Ainy運営チーム";

    if (!function_exists('aidunite_send_mail')) {
        return ['ok' => false, 'error' => 'mail_utils_missing'];
    }

    $result = aidunite_send_mail($user->user_email, $subject, $message, [], 'activation');

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[Ainy] 本登録URL（診断） user_id=' . (int) $user_id . ' ' . $confirm_url);
    }

    return $result;
}
