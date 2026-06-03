<?php
/**
 * 通知システム API（仕様: docs/spec/notification.md）
 * 送信・未読件数・一覧の唯一の実装
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('aidunite_notification_delivery_log_write')) {
    require_once __DIR__ . '/notification-delivery-log.php';
}

/**
 * テスト通知で `wp_mail` の To に上書きしてよいメールアドレス（小文字・重複なし）
 *
 * 既定では **サイトの admin_email** のみ（チーム申請通知などと同じ受信箱）。追加は
 * `add_filter( 'aidunite_notification_test_mail_allowlist', function( $emails ) { $emails[] = 'ops@example.com'; return $emails; } );`
 *
 * @return string[]
 */
function aidunite_notification_get_test_mail_allowlist() {
    $emails = [];
    $admin = sanitize_email((string) get_option('admin_email'));
    if (is_email($admin)) {
        $emails[] = strtolower($admin);
    }
    /**
     * テスト通知の宛先上書きに許可するメール（小文字で比較）
     *
     * @param string[] $emails
     */
    $filtered = apply_filters('aidunite_notification_test_mail_allowlist', $emails);
    $out = [];
    foreach ((array) $filtered as $one) {
        $e = strtolower(sanitize_email((string) $one));
        if ($e !== '' && is_email($e) && ! in_array($e, $out, true)) {
            $out[] = $e;
        }
    }
    return $out;
}

/**
 * 通知を送信する（唯一の入口）
 *
 * @param int    $user_id 宛先ユーザーID
 * @param string $type    通知タイプ（match_request / match_established / ... / general。旧名は normalize で吸収）
 * @param array  $data    タイプに応じたデータ（title, message は必須。他はタイプ別）。**任意**: `idempotency_key`（string）…配信ログ親行に保存し、将来の重複抑止設計に利用。`related_id`・`link_url` 等は従来どおり。**任意（内部）**: `test_mail_recipient`（string）…`aidunite_notification_get_test_mail_allowlist()` に含まれる場合のみ `wp_mail` の To を上書き。空でないのに許可外のときは **送信せず** `success` false を返す。メール通知 OFF 時も許可宛先があれば送信する（REST テスト用）。
 * @return array { success: bool, message?: string, notification_id?: int, mail?: array{ attempted: bool, ok: ?bool, to: ?string, error?: string } }
 */
function aidunite_notification_send($user_id, $type, $data) {
    $user_id = (int) $user_id;
    if (function_exists('aidunite_normalize_notification_type_value')) {
        $type = aidunite_normalize_notification_type_value($type);
    }
    $type = sanitize_text_field($type);
    if (!$user_id || !$type) {
        return ['success' => false, 'message' => 'user_id と type は必須です'];
    }

    $user = get_userdata($user_id);
    if (!$user) {
        return ['success' => false, 'message' => 'ユーザーが見つかりません'];
    }

    $title = isset($data['title']) ? sanitize_text_field($data['title']) : '';
    $message = isset($data['message']) ? sanitize_textarea_field($data['message']) : '';
    if ($title === '' && $message === '') {
        return ['success' => false, 'message' => 'title または message が必要です'];
    }
    if ($title === '') {
        $title = wp_trim_words($message, 10);
    }

    $correlation_id = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : (string) wp_generate_password(32, false, false);
    $idempotency_key = !empty($data['idempotency_key']) ? sanitize_text_field((string) $data['idempotency_key']) : null;
    $related_id_for_log = isset($data['related_id']) && $data['related_id'] !== '' && $data['related_id'] !== null
        ? (int) $data['related_id']
        : null;

    // ユーザー通知設定（メールは設定に従う。お知らせCPTは常に作成＝設定未登録でも一覧・バッジには表示）
    $settings = aidunite_notification_get_settings($user_id);
    $send_email = !empty($settings['email_notifications']);
    $raw_settings = get_user_meta($user_id, 'notification_settings', true);
    $user_wants_line_channel = is_array($raw_settings) && !empty($raw_settings['line_notifications']);

    // 管理者向け REST テスト等: 許可リストに含まれる test_mail_recipient のみ To を上書き（本文にメールアドレスは含めない）
    $raw_test = isset($data['test_mail_recipient']) ? trim((string) $data['test_mail_recipient']) : '';
    $test_mail_recipient = '';
    if ($raw_test !== '') {
        $candidate = sanitize_email($raw_test);
        $allow = aidunite_notification_get_test_mail_allowlist();
        if (! is_email($candidate) || ! in_array(strtolower($candidate), $allow, true)) {
            return [
                'success' => false,
                'message' => 'テストメールの宛先が許可リストにありません。既定はサイトの管理者メール（設定 → 一般）です。別アドレスへ送る場合は `aidunite_notification_test_mail_allowlist` フィルターで追加してください。',
            ];
        }
        $test_mail_recipient = $candidate;
    }
    $wp_mail_to = $test_mail_recipient !== '' ? $test_mail_recipient : $user->user_email;
    $should_send_email = $send_email || ($test_mail_recipient !== '');

    // 通知CPT作成（post_author = 宛先。仕様統一）
    $notification_id = wp_insert_post([
        'post_type'   => 'notification',
        'post_status' => 'publish',
        'post_title'  => $title,
        'post_content' => $message,
        'post_author' => $user_id,
    ]);

    if (!$notification_id || is_wp_error($notification_id)) {
        $fail_detail = is_wp_error($notification_id) ? $notification_id->get_error_message() : 'wp_insert_post failed';
        aidunite_notification_delivery_log_write([
            'correlation_id' => $correlation_id,
            'idempotency_key' => $idempotency_key,
            'user_id' => $user_id,
            'notification_type' => $type,
            'related_id' => $related_id_for_log,
            'notification_post_id' => null,
            'channels' => [
                [
                    'channel' => 'app',
                    'result' => 'failed',
                    'detail' => function_exists('mb_substr') ? mb_substr($fail_detail, 0, 200) : substr($fail_detail, 0, 200),
                ],
            ],
        ]);
        return [
            'success' => false,
            'message' => is_wp_error($notification_id) ? $notification_id->get_error_message() : '通知の保存に失敗しました',
        ];
    }

    update_post_meta($notification_id, 'type', $type);
    update_post_meta($notification_id, 'is_read', false);
    if (isset($data['related_id'])) {
        update_post_meta($notification_id, 'related_id', (int) $data['related_id']);
    }
    if (isset($data['link_url'])) {
        update_post_meta($notification_id, 'link_url', esc_url_raw($data['link_url']));
    }

    $headers = [];
    if (function_exists('aidunite_get_effective_user_role')) {
        list(, $preview_mode) = aidunite_get_effective_user_role();
        if ($preview_mode) {
            $title = '[PREVIEW] ' . $title;
            $message = "[PREVIEWモードで送信されました]\n" . $message;
            $headers[] = 'Cc: ' . get_option('admin_email');
            $log_path = WP_CONTENT_DIR . '/uploads/preview_log.txt';
            @file_put_contents($log_path, date('Y-m-d H:i:s') . "\tuser_id={$user_id}\ttype={$type}\ttitle={$title}\n", FILE_APPEND | LOCK_EX);
        }
    }

    $channels_log = [
        ['channel' => 'app', 'result' => 'success'],
    ];

    $mail_ok = null;
    $mail_error_message = '';
    $mail_bcc_to = '';
    if ($should_send_email) {
        if (!is_email($wp_mail_to)) {
            $channels_log[] = [
                'channel' => 'email',
                'result' => 'failed',
                'detail' => 'invalid_wp_mail_to',
            ];
            $mail_ok = false;
            $mail_error_message = 'メール宛先が無効です。';
        } else {
            $mail_fail_cb = function ($wp_error) use (&$mail_error_message) {
                if ($wp_error instanceof WP_Error) {
                    $mail_error_message = $wp_error->get_error_message();
                }
            };
            add_action('wp_mail_failed', $mail_fail_cb, 10, 1);

            // テスト宛先指定時はチーム申請通知（aidunite_notify_admin_team_registration）と同様に HTML 形式にする。
            // さらに診断用: サイトの管理者メール（届いている経路）へ Bcc を付与（To と同一のときは付けない）。
            $headers_for_mail = array_merge(['Content-Type: text/plain; charset=UTF-8'], $headers);
            $body_for_mail = $message;
            if ($test_mail_recipient !== '') {
                $headers_for_mail = array_merge(['Content-Type: text/html; charset=UTF-8'], $headers);
                $body_for_mail = '<!DOCTYPE html><html><body><p style="font-family:sans-serif;white-space:pre-wrap;">'
                    . esc_html($message) . '</p></body></html>';
                $bcc_admin = sanitize_email((string) get_option('admin_email'));
                if (is_email($bcc_admin) && strtolower($bcc_admin) !== strtolower($wp_mail_to)) {
                    $headers_for_mail[] = 'Bcc: ' . $bcc_admin;
                    $mail_bcc_to = $bcc_admin;
                }
            }

            $mail_ok = wp_mail($wp_mail_to, $title, $body_for_mail, $headers_for_mail);

            remove_action('wp_mail_failed', $mail_fail_cb, 10);

            if ($mail_ok) {
                $channels_log[] = ['channel' => 'email', 'result' => 'success'];
            } else {
                $detail = $mail_error_message !== '' ? $mail_error_message : 'wp_mail returned false';
                $channels_log[] = [
                    'channel' => 'email',
                    'result' => 'failed',
                    'detail' => function_exists('mb_substr') ? mb_substr($detail, 0, 300) : substr($detail, 0, 300),
                ];
            }
        }
    } else {
        $channels_log[] = ['channel' => 'email', 'result' => 'skipped', 'skip_reason' => 'user_email_off'];
    }

    // LINE Notify API は 2025-03-31 にサービス終了済み。notify-api.line.me への HTTP は行わない。
    // 後続の LINE Messaging API 等は `aidunite_notification_send_line_channel` で接続する（第20節）。
    if ($user_wants_line_channel) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[AidUnite] LINE channel skipped: LINE Notify ended 2025-03-31. user_id=%d notification_id=%d type=%s',
                $user_id,
                (int) $notification_id,
                $type
            ));
        }
        /**
         * LINE 系の外部送信（Messaging API 実装時に利用）
         *
         * @param int     $user_id
         * @param WP_User $user
         * @param string  $title
         * @param string  $message
         * @param string  $type
         * @param int     $notification_id
         * @param array   $settings `aidunite_notification_get_settings` の結果（`line_notifications` は常に false）
         * @param bool    $user_wants_line_channel user_meta 上、従来の LINE チャネルを希望していたか
         */
        do_action('aidunite_notification_send_line_channel', $user_id, $user, $title, $message, $type, (int) $notification_id, $settings, $user_wants_line_channel);
        $channels_log[] = ['channel' => 'line', 'result' => 'skipped', 'skip_reason' => 'line_messaging_not_configured'];
    } else {
        $channels_log[] = ['channel' => 'line', 'result' => 'skipped', 'skip_reason' => 'user_line_off'];
    }

    // Web Push 等は本関数外（notification.md 第17・18節）。テンプレートに push があってもここでは送らない。
    $channels_log[] = ['channel' => 'push', 'result' => 'skipped', 'skip_reason' => 'separate_path_not_in_core_send'];

    $channels_log = apply_filters(
        'aidunite_notification_delivery_channels_log',
        $channels_log,
        [
            'user_id' => $user_id,
            'notification_type' => $type,
            'notification_id' => (int) $notification_id,
            'data' => $data,
        ]
    );

    aidunite_notification_delivery_log_write([
        'correlation_id' => $correlation_id,
        'idempotency_key' => $idempotency_key,
        'user_id' => $user_id,
        'notification_type' => $type,
        'related_id' => $related_id_for_log,
        'notification_post_id' => (int) $notification_id,
        'channels' => $channels_log,
    ]);

    $out = [
        'success' => true,
        'notification_id' => (int) $notification_id,
        'mail' => [
            'attempted' => (bool) $should_send_email,
            'ok' => $should_send_email ? (bool) $mail_ok : null,
            'to' => $should_send_email ? $wp_mail_to : null,
            'bcc' => ($should_send_email && $mail_bcc_to !== '') ? $mail_bcc_to : null,
            'error' => ($should_send_email && $mail_ok !== true)
                ? ($mail_error_message !== '' ? $mail_error_message : 'wp_mail returned false')
                : null,
        ],
    ];

    return $out;
}

/**
 * ユーザー通知設定を取得（送信時のON/OFF判定用）
 * 設定前に登録したユーザー（notification_settings 未保存）もデフォルト値で扱い、通知は届く。
 *
 * @param int $user_id
 * @return array
 */
function aidunite_notification_get_settings($user_id) {
    $defaults = [
        'notifications_enabled' => true,
        'email_notifications'  => true,
        'line_notifications'   => false,
        'match_notifications'  => true,
        'team_notifications'   => true,
        'schedule_notifications' => true,
        'general_notifications'  => true,
    ];
    $saved = get_user_meta($user_id, 'notification_settings', true);
    if (!is_array($saved)) {
        return $defaults;
    }
    $merged = wp_parse_args($saved, $defaults);
    // LINE Notify は 2025-03-31 終了済み。返却値の line_notifications は送信用に常に false（生の user_meta は未変更）。
    $merged['line_notifications'] = false;
    return $merged;
}

/**
 * 未読通知件数を返す（バッジ用）
 *
 * @param int|null $user_id 省略時は get_current_user_id()
 * @return int
 */
function aidunite_notification_unread_count($user_id = null) {
    if ($user_id === null) {
        $user_id = get_current_user_id();
    }
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return 0;
    }

    $q = new WP_Query([
        'post_type'      => 'notification',
        'post_status'    => 'publish',
        'author'         => $user_id,
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'meta_query'     => [
            'relation' => 'OR',
            ['key' => 'is_read', 'compare' => 'NOT EXISTS'],
            ['key' => 'is_read', 'value' => '1', 'compare' => '!='],
        ],
    ]);
    return $q->post_count;
}

/**
 * ユーザー宛の通知一覧を取得（お知らせ一覧ページ用）
 *
 * @param int   $user_id
 * @param array $args get_posts に渡す追加引数
 * @return WP_Post[]
 */
function aidunite_notification_list($user_id, $args = []) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return [];
    }

    $defaults = [
        'post_type'      => 'notification',
        'post_status'    => 'publish',
        'author'         => $user_id,
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ];
    return get_posts(wp_parse_args($args, $defaults));
}
