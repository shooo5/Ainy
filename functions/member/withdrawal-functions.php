<?php
/**
 * 会員退会処理（プラグイン非依存）
 * データ取り決め: docs/withdrawal-data-policy.md 案B（管理者移管＋一部完全削除）
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('AIDUNITE_WITHDRAWAL_DELETE_POST_TYPES')) {
    /** 退会時に完全削除する投稿タイプ（docs/withdrawal-data-policy.md 案B・妥当性反映済み） */
    define('AIDUNITE_WITHDRAWAL_DELETE_POST_TYPES', 'team,schedule,notification,match_log,match_request,match_board,team_application,match_feedback,attendance,schedule_template,practice');
}
if (!defined('AIDUNITE_WITHDRAWAL_KEEP_POST_TYPES')) {
    /** 退会時に管理者へ移管して保持する投稿タイプ（案B） */
    define('AIDUNITE_WITHDRAWAL_KEEP_POST_TYPES', 'message,payment_log,audit_log');
}
if (!defined('AIDUNITE_WITHDRAWAL_ADMIN_USER_ID')) {
    /** 管理者ユーザーID（保持投稿の移管先） */
    define('AIDUNITE_WITHDRAWAL_ADMIN_USER_ID', 1);
}
if (!defined('AIDUNITE_WITHDRAWAL_TOKEN_EXPIRY_HOURS')) {
    /** 退会確認リンクの有効期限（時間）。docs/withdrawal-risks-and-gaps.md 5.5 */
    define('AIDUNITE_WITHDRAWAL_TOKEN_EXPIRY_HOURS', 24);
}
if (!defined('AIDUNITE_WITHDRAWAL_REP_DELAY_SECONDS')) {
    /**
     * 代表者退会時の「チーム削除＋代表者削除」を実行するまでの遅延（秒）。
     * 本番: 30日。テスト時は wp-config で 60 などに設定すると仮想で1分後に実行される。
     */
    define('AIDUNITE_WITHDRAWAL_REP_DELAY_SECONDS', 30 * 24 * 3600);
}

/**
 * 削除対象投稿タイプを配列で取得（管理画面・退会処理の共通化用）
 *
 * @return string[]
 */
function aidunite_get_withdrawal_delete_post_types() {
    return array_filter(array_map('trim', explode(',', AIDUNITE_WITHDRAWAL_DELETE_POST_TYPES)));
}

/**
 * 保持対象投稿タイプを配列で取得（管理画面・退会処理の共通化用）
 *
 * @return string[]
 */
function aidunite_get_withdrawal_keep_post_types() {
    return array_filter(array_map('trim', explode(',', AIDUNITE_WITHDRAWAL_KEEP_POST_TYPES)));
}

/**
 * 退会確認トークンを生成する（有効期限24時間＋署名）
 * docs/withdrawal-risks-and-gaps.md 5.5
 *
 * @param int $user_id 対象ユーザーID
 * @return string トークン文字列（URLにそのまま使える）
 */
function aidunite_create_withdrawal_token($user_id) {
    $user_id = (int) $user_id;
    $expiry = time() + (AIDUNITE_WITHDRAWAL_TOKEN_EXPIRY_HOURS * HOUR_IN_SECONDS);
    $payload = $user_id . ':' . $expiry;
    $secret = defined('AUTH_KEY') && AUTH_KEY ? AUTH_KEY : 'aidunite-withdrawal-fallback';
    $signature = hash_hmac('sha256', $payload, $secret);
    return base64_encode($payload . ':' . $signature);
}

/**
 * 退会確認トークンを検証する（有効期限・署名チェック）
 *
 * @param string $token URLから受け取ったトークン
 * @return int|false 有効なら user_id、無効・期限切れなら false
 */
function aidunite_verify_withdrawal_token($token) {
    if (!is_string($token) || $token === '') {
        return false;
    }
    $decoded = base64_decode($token, true);
    if ($decoded === false || strpos($decoded, ':') === false) {
        return false;
    }
    $parts = explode(':', $decoded);
    if (count($parts) < 3) {
        return false;
    }
    $signature = array_pop($parts);
    $payload = implode(':', $parts);
    $secret = defined('AUTH_KEY') && AUTH_KEY ? AUTH_KEY : 'aidunite-withdrawal-fallback';
    if (!hash_equals(hash_hmac('sha256', $payload, $secret), $signature)) {
        return false;
    }
    $payload_parts = explode(':', $payload);
    $user_id = (int) $payload_parts[0];
    $expiry = isset($payload_parts[1]) ? (int) $payload_parts[1] : 0;
    if ($expiry < time() || $user_id <= 0) {
        return false;
    }
    return $user_id;
}

/**
 * 退会確認メールを送信する（確認リンク付き・有効期限24時間）
 *
 * @param int $user_id 対象ユーザーID
 * @return bool 送信成功なら true
 */
function aidunite_send_withdrawal_confirmation_email($user_id) {
    $user = get_user_by('id', (int) $user_id);
    if (!$user || !$user->user_email) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("aidunite_send_withdrawal_confirmation_email: 無効なユーザーID: {$user_id}");
        }
        return false;
    }

    $token = aidunite_create_withdrawal_token($user_id);
    $confirm_url = add_query_arg([
        'withdraw' => '1',
        'token' => $token,
    ], home_url('/confirm-withdrawal'));

    $nickname = get_user_meta($user_id, 'nickname', true) ?: $user->display_name;
    $subject = '【AidUnite】退会確認のご案内';
    $hours = AIDUNITE_WITHDRAWAL_TOKEN_EXPIRY_HOURS;
    $message = $nickname . " 様\n\n"
        . "退会のご申請ありがとうございます。\n\n"
        . "以下のリンクをクリックして退会を完了してください。\n\n"
        . $confirm_url . "\n\n"
        . "※このリンクの有効期限は" . $hours . "時間です。期限を過ぎた場合は退会申請画面から再度申請してください。\n"
        . "※メールが届かない場合は迷惑メールフォルダをご確認いただくか、ドメインの受信許可設定をご確認ください。お問い合わせは運営までご連絡ください。\n\n"
        . "AidUnite 運営チーム";

    $sent = wp_mail($user->user_email, $subject, $message);
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log($sent
            ? "退会確認メール送信成功（user_id: {$user_id}）"
            : "退会確認メール送信失敗（user_id: {$user_id}）");
    }
    return $sent;
}

/**
 * チーム削除前に、そのチームに属する他メンバーの team_id とロールをクリアする。
 * 代表者退会・管理者によるユーザー削除時に、チーム削除とセットで呼ぶ（docs/withdrawal-data-policy.md）。
 *
 * @param int $team_id 削除するチームの投稿ID
 * @param int $exclude_user_id クリア対象から除外するユーザーID（退会者本人など）。0 の場合は除外なし。
 */
function aidunite_detach_team_members_before_delete($team_id, $exclude_user_id = 0) {
    $team_id = (int) $team_id;
    $exclude_user_id = (int) $exclude_user_id;
    if ($team_id <= 0) {
        return;
    }

    $member_ids = function_exists('aidunite_get_team_affiliated_user_ids')
        ? aidunite_get_team_affiliated_user_ids($team_id)
        : array_map('intval', (array) get_users([
            'meta_key' => 'team_id',
            'meta_value' => $team_id,
            'fields' => 'ID',
            'number' => -1,
        ]));

    foreach ($member_ids as $member_id) {
        if ($exclude_user_id > 0 && $member_id === $exclude_user_id) {
            continue;
        }
        if (function_exists('aidunite_user_remove_team_membership')) {
            aidunite_user_remove_team_membership($member_id, $team_id);
        } else {
            delete_user_meta($member_id, 'team_id');
            update_user_meta($member_id, 'aidunite_role', 'general');
        }
    }
}

/**
 * ユーザーが代表者であるチームの team_id 一覧を取得する
 * post_author または team_leader_id がそのユーザーである team 投稿を返す。
 *
 * @param int $user_id ユーザーID
 * @return int[]
 */
function aidunite_get_representative_team_ids($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return [];
    }
    if (function_exists('aidunite_discover_team_ids_for_leader')) {
        return aidunite_discover_team_ids_for_leader($user_id, ['publish', 'pending', 'draft', 'private']);
    }
    $by_author = get_posts([
        'post_type' => 'team',
        'author' => $user_id,
        'post_status' => 'any',
        'posts_per_page' => -1,
        'fields' => 'ids',
    ]);
    $by_leader = get_posts([
        'post_type' => 'team',
        'post_status' => 'any',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => [
            ['key' => 'team_leader_id', 'value' => (string) $user_id, 'compare' => '='],
        ],
    ]);
    $ids = array_unique(array_merge($by_author ?: [], $by_leader ?: []));
    return array_map('intval', $ids);
}

/**
 * ユーザーが代表者かどうか（1件以上チームの代表者であるか）
 *
 * @param int $user_id ユーザーID
 * @return bool
 */
function aidunite_is_representative($user_id) {
    return count(aidunite_get_representative_team_ids($user_id)) > 0;
}

/**
 * 代表者退会スケジュール登録時に、チームメンバーへ通知する
 *
 * @param int   $user_id   退会する代表者 user_id
 * @param int[] $team_ids  解散するチームの team_id 一覧
 */
function aidunite_notify_team_dissolution_scheduled($user_id, $team_ids) {
    if (!function_exists('aidunite_notify_user')) {
        return;
    }
    $execute_ts = time() + (defined('AIDUNITE_WITHDRAWAL_REP_DELAY_SECONDS') ? AIDUNITE_WITHDRAWAL_REP_DELAY_SECONDS : 30 * 24 * 3600);
    $date_str = date_i18n('Y年n月j日', $execute_ts);

    foreach ($team_ids as $team_id) {
        $team = get_post($team_id);
        $team_name = $team ? $team->post_title : 'チーム';
        $member_ids = function_exists('aidunite_get_team_affiliated_user_ids')
            ? aidunite_get_team_affiliated_user_ids($team_id)
            : array_map('intval', (array) get_users(['meta_key' => 'team_id', 'meta_value' => $team_id, 'fields' => 'ID']));
        foreach ($member_ids as $mid) {
            if ((int) $mid === (int) $user_id) {
                continue;
            }
            $m = get_userdata($mid);
            if (!$m) {
                continue;
            }
            aidunite_notify_user(
                $m->ID,
                'チーム解散のご案内',
                "{$team_name} の代表者による退会のため、{$date_str} をもってチームが解散します。\n月謝は解散日をもって解約されます。ご不明な点はお問い合わせください。",
                'team_dissolution',
                $team_id
            );
        }
    }
}

/**
 * 退会対象ユーザーが契約しているチーム月謝の team_id 一覧を取得する
 * user_meta の stripe_tuition_subscription_{team_id} が存在する team_id を返す。
 *
 * @param int $user_id ユーザーID（保護者）
 * @return int[]
 */
function aidunite_get_withdrawal_tuition_team_ids($user_id) {
    global $wpdb;
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return [];
    }
    $like = $wpdb->esc_like('stripe_tuition_subscription_') . '%';
    $rows = $wpdb->get_col($wpdb->prepare(
        "SELECT meta_key FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE %s",
        $user_id,
        $like
    ));
    $prefix = 'stripe_tuition_subscription_';
    $team_ids = [];
    foreach ($rows as $meta_key) {
        if (strpos($meta_key, $prefix) === 0) {
            $team_id = (int) substr($meta_key, strlen($prefix));
            if ($team_id > 0) {
                $team_ids[] = $team_id;
            }
        }
    }
    return $team_ids;
}

/**
 * 退会実行前にシステム利用料・チーム月謝を解約する
 * docs/team-withdrawal-flow.md 月謝まわりで実装・運用で揃えること
 *
 * @param int $user_id 退会するユーザーID
 */
function aidunite_cancel_all_subscriptions_before_withdrawal($user_id) {
    $user_id = (int) $user_id;

    $rep_team_ids = function_exists('aidunite_get_representative_team_ids')
        ? aidunite_get_representative_team_ids($user_id)
        : [];
    foreach ($rep_team_ids as $team_id) {
        $team_id = (int) $team_id;
        if ($team_id <= 0) {
            continue;
        }
        if (function_exists('aidunite_payment_cancel_team_stripe_subscription_immediate')) {
            aidunite_payment_cancel_team_stripe_subscription_immediate($team_id, $user_id);
        }
    }

    if (function_exists('aidunite_cancel_tuition_subscription')) {
        $team_ids = aidunite_get_withdrawal_tuition_team_ids($user_id);
        foreach ($team_ids as $team_id) {
            $result = aidunite_cancel_tuition_subscription($user_id, (int) $team_id);
            if (is_wp_error($result) && defined('WP_DEBUG') && WP_DEBUG) {
                error_log('退会前月謝解約 team_id=' . $team_id . ': ' . $result->get_error_message());
            }
        }
    }
}

/**
 * 退会を実行する（投稿の削除／管理者移管 → ユーザー削除）
 * docs/withdrawal-data-policy.md 案B に準拠。
 * ※ 呼び出し元で退会前に aidunite_cancel_all_subscriptions_before_withdrawal を呼ぶこと。
 *
 * @param int $user_id 退会するユーザーID
 * @return bool 成功した場合 true、ユーザーが存在しない等で失敗した場合 false
 */
function aidunite_execute_withdrawal($user_id) {
    $user_id = (int) $user_id;
    $user = get_user_by('id', $user_id);
    if (!$user) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("aidunite_execute_withdrawal: 無効なユーザーID: {$user_id}");
        }
        return false;
    }

    $delete_types = aidunite_get_withdrawal_delete_post_types();
    $keep_types = aidunite_get_withdrawal_keep_post_types();
    $admin_id = (int) AIDUNITE_WITHDRAWAL_ADMIN_USER_ID;

    // 削除対象の投稿を完全削除
    foreach ($delete_types as $post_type) {
        $post_type = trim($post_type);
        if ($post_type === '') {
            continue;
        }
        $posts = get_posts([
            'post_type' => $post_type,
            'author' => $user_id,
            'posts_per_page' => -1,
            'post_status' => 'any',
        ]);
        foreach ($posts as $post) {
            if ($post_type === 'team' && $post->ID) {
                aidunite_detach_team_members_before_delete($post->ID, $user_id);
            }
            wp_delete_post($post->ID, true);
        }
    }

    // 保持対象の投稿は管理者に移管し非公開で保持
    foreach ($keep_types as $post_type) {
        $post_type = trim($post_type);
        if ($post_type === '') {
            continue;
        }
        $posts = get_posts([
            'post_type' => $post_type,
            'author' => $user_id,
            'posts_per_page' => -1,
            'post_status' => 'any',
        ]);
        foreach ($posts as $post) {
            wp_update_post([
                'ID' => $post->ID,
                'post_author' => $admin_id,
                'post_status' => 'private',
            ]);
        }
    }

    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user($user_id);

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("退会処理完了（user_id: {$user_id}）");
    }
    return true;
}

/**
 * 会員退会申請 AJAX：確認メールを送信する（プラグイン非依存）
 */
function aidunite_ajax_member_withdrawal() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'ログインしてください。']);
        return;
    }

    if (!isset($_POST['member_withdrawal_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['member_withdrawal_nonce'])), 'save_member_withdrawal')) {
        wp_send_json_error(['message' => 'リクエストが無効です。ページを再読み込みしてやり直してください。']);
        return;
    }

    $request_user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
    $current_user_id = get_current_user_id();
    if ($request_user_id !== $current_user_id) {
        wp_send_json_error(['message' => '本人のみ退会申請できます。']);
        return;
    }

    if (function_exists('aidunite_is_representative') && aidunite_is_representative($current_user_id)) {
        $choice = isset($_POST['rep_withdrawal_choice']) ? sanitize_text_field(wp_unslash($_POST['rep_withdrawal_choice'])) : '';
        if ($choice === 'transfer') {
            wp_send_json_error([
                'message' => '代表者を譲る場合は、先にチーム設定で譲渡を完了してから退会申請してください（譲渡後は一般メンバーとして退会できます）。',
            ]);
            return;
        }
        if ($choice !== 'dissolve') {
            wp_send_json_error([
                'message' => '代表者退会するには「チームを解散して退会する」を選択するか、先にチーム設定で代表者を譲渡してください。',
            ]);
            return;
        }
        $team_ids = function_exists('aidunite_get_representative_team_ids')
            ? aidunite_get_representative_team_ids($current_user_id)
            : [];
        foreach ($team_ids as $tid) {
            if (!function_exists('aidunite_payment_exit_evaluate_gates')) {
                continue;
            }
            $gates = aidunite_payment_exit_evaluate_gates((int) $tid);
            if (!$gates['can_start']) {
                wp_send_json_error([
                    'message' => implode(' ', $gates['messages'] ?: ['翌月以降の試合を先にキャンセルしてください。']),
                ]);
                return;
            }
        }
    }

    $sent = aidunite_send_withdrawal_confirmation_email($current_user_id);
    if ($sent) {
        wp_send_json_success([
            'message' => '退会確認メールを送信しました。メールに記載のリンクをクリックすると退会が完了します。',
        ]);
    } else {
        wp_send_json_error(['message' => 'メールの送信に失敗しました。しばらく経ってから再度お試しください。']);
    }
}

add_action('wp_ajax_member_withdrawal', 'aidunite_ajax_member_withdrawal');

/**
 * 旧スケジュール退会 cron / option の後始末（exit pending が正本）
 */
add_action('init', static function () {
    if (get_transient('aidunite_legacy_scheduled_withdrawals_retired')) {
        return;
    }
    while ($ts = wp_next_scheduled('aidunite_process_scheduled_withdrawals')) {
        wp_unschedule_event($ts, 'aidunite_process_scheduled_withdrawals');
    }
    delete_option('aidunite_scheduled_withdrawals');
    set_transient('aidunite_legacy_scheduled_withdrawals_retired', 1, YEAR_IN_SECONDS);
}, 5);
