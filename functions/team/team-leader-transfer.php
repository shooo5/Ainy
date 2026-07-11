<?php
/**
 * 代表者譲渡（§12B: 新代表 Checkout 期限 or 同時引き継ぎ）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @return int
 */
function aidunite_payment_get_leader_transfer_checkout_days() {
    $config = function_exists('aidunite_get_payment_config') ? aidunite_get_payment_config() : [];
    $days = (int) ($config['leader_transfer']['checkout_deadline_days'] ?? 14);

    return max(1, min(60, $days));
}

/**
 * @param int $team_id
 */
function aidunite_team_clear_leader_transfer_pending($team_id) {
    if (function_exists('aidunite_team_persist_clear_leader_transfer_pending')) {
        aidunite_team_persist_clear_leader_transfer_pending($team_id);
    }
}

/**
 * @param int $team_id
 * @param int $from_user_id
 * @return array<int, array{user_id:int,display_name:string,role:string}>
 */
function aidunite_team_get_leader_transfer_candidates($team_id, $from_user_id) {
    $team_id = (int) $team_id;
    $from_user_id = (int) $from_user_id;
    if ($team_id <= 0) {
        return [];
    }
    $member_ids = function_exists('aidunite_get_team_affiliated_user_ids')
        ? aidunite_get_team_affiliated_user_ids($team_id)
        : [];
    $candidates = [];
    foreach ($member_ids as $uid) {
        $uid = (int) $uid;
        if ($uid <= 0 || $uid === $from_user_id) {
            continue;
        }
        $user = get_userdata($uid);
        if (!$user) {
            continue;
        }
        $role = function_exists('aidunite_user_read_aidunite_role')
            ? (string) aidunite_user_read_aidunite_role($uid)
            : (string) get_user_meta($uid, 'aidunite_role', true);
        if (!in_array($role, ['parent', 'player', 'team_leader'], true)) {
            continue;
        }
        $candidates[] = [
            'user_id' => $uid,
            'display_name' => $user->display_name,
            'role' => $role,
        ];
    }

    return $candidates;
}

/**
 * @param int $team_id
 * @param int $user_id
 */
function aidunite_team_user_is_affiliated($team_id, $user_id) {
    $team_id = (int) $team_id;
    $user_id = (int) $user_id;
    if ($team_id <= 0 || $user_id <= 0 || !function_exists('aidunite_get_team_affiliated_user_ids')) {
        return false;
    }
    $ids = array_map('intval', aidunite_get_team_affiliated_user_ids($team_id));

    return in_array($user_id, $ids, true);
}

/**
 * @param int    $team_id
 * @param int    $from_user_id
 * @param int    $to_user_id
 * @param string $mode simultaneous|checkout_required
 */
function aidunite_team_apply_leader_role_swap($team_id, $from_user_id, $to_user_id) {
    $team_id = (int) $team_id;
    $from_user_id = (int) $from_user_id;
    $to_user_id = (int) $to_user_id;

    if (function_exists('aidunite_team_sync_post_leader_linkage')) {
        aidunite_team_sync_post_leader_linkage($team_id, $to_user_id);
    } else {
        update_post_meta($team_id, 'team_leader_id', $to_user_id);
    }

    if (function_exists('aidunite_user_write_role_meta')) {
        aidunite_user_write_role_meta($to_user_id, 'team_leader');
    } else {
        update_user_meta($to_user_id, 'aidunite_role', 'team_leader');
        update_user_meta($to_user_id, 'user_type', 'team_leader');
    }

    if (function_exists('aidunite_get_team_affiliated_user_ids')
        && in_array($from_user_id, aidunite_get_team_affiliated_user_ids($team_id), true)) {
        $demote = 'parent';
        if (function_exists('aidunite_user_write_role_meta')) {
            aidunite_user_write_role_meta($from_user_id, $demote);
        } else {
            update_user_meta($from_user_id, 'aidunite_role', $demote);
        }
    }

    do_action('aidunite_team_leader_transferred', $team_id, $from_user_id, $to_user_id);
}

/**
 * Stripe 顧客を新代表へ同期（team 単位 subscription 維持）
 *
 * @param int $team_id
 * @param int $to_user_id
 */
function aidunite_team_handoff_stripe_billing_to_leader($team_id, $to_user_id) {
    $team_id = (int) $team_id;
    $to_user_id = (int) $to_user_id;
    if ($team_id <= 0 || $to_user_id <= 0 || !function_exists('aidunite_get_team_stripe_subscription_id')) {
        return;
    }
    $sub_id = aidunite_get_team_stripe_subscription_id($team_id, $to_user_id);
    if ($sub_id === '') {
        return;
    }
    $customer_id = function_exists('aidunite_get_stripe_customer_id')
        ? (string) aidunite_get_stripe_customer_id($to_user_id)
        : '';
    if ($customer_id === '' || !class_exists('\Stripe\Stripe') || !aidunite_init_stripe()) {
        return;
    }
    try {
        \Stripe\Subscription::update($sub_id, ['customer' => $customer_id]);
    } catch (\Exception $e) {
        error_log('代表者譲渡: Stripe customer 更新スキップ (team=' . $team_id . '): ' . $e->getMessage());
    }
    if (function_exists('aidunite_set_team_stripe_subscription_id')) {
        aidunite_set_team_stripe_subscription_id($team_id, $sub_id, $to_user_id);
    }
}

/**
 * @param int  $team_id
 * @param int  $from_user_id
 * @param int  $to_user_id
 * @param bool $simultaneous_handoff
 * @return array|WP_Error
 */
function aidunite_team_start_leader_transfer($team_id, $from_user_id, $to_user_id, $simultaneous_handoff = false) {
    $team_id = (int) $team_id;
    $from_user_id = (int) $from_user_id;
    $to_user_id = (int) $to_user_id;

    if ($team_id <= 0 || $from_user_id <= 0 || $to_user_id <= 0) {
        return new WP_Error('invalid_args', '譲渡先を指定してください。');
    }
    if ($from_user_id === $to_user_id) {
        return new WP_Error('same_user', '自分自身には譲渡できません。');
    }
    if (!function_exists('aidunite_team_settings_user_is_leader_of_team')
        || !aidunite_team_settings_user_is_leader_of_team($from_user_id, $team_id)) {
        return new WP_Error('forbidden', '代表者のみ譲渡できます。');
    }
    if (!aidunite_team_user_is_affiliated($team_id, $to_user_id)) {
        return new WP_Error('not_member', '譲渡先はチーム所属メンバーである必要があります。');
    }
    if (function_exists('aidunite_payment_exit_read_pending') && aidunite_payment_exit_read_pending($team_id) !== null) {
        return new WP_Error('exit_pending', '解約・解散の手続き中は代表者を譲渡できません。');
    }
    if (aidunite_team_read_leader_transfer_pending($team_id) !== null) {
        return new WP_Error('transfer_pending', '譲渡手続きが進行中です。');
    }

    $subscription_id = function_exists('aidunite_get_team_stripe_subscription_id')
        ? aidunite_get_team_stripe_subscription_id($team_id, $from_user_id)
        : '';
    $new_leader_customer = function_exists('aidunite_get_stripe_customer_id')
        ? (string) aidunite_get_stripe_customer_id($to_user_id)
        : '';
    $needs_checkout = $subscription_id !== '' && !$simultaneous_handoff && $new_leader_customer === '';

    if ($subscription_id !== '' && $simultaneous_handoff && $new_leader_customer === '') {
        return new WP_Error(
            'handoff_requires_payment',
            '同時引き継ぎには、譲渡先の Stripe 顧客登録が必要です。先に譲渡先に支払い設定を案内するか、Checkout 期限付き譲渡を選んでください。'
        );
    }

    aidunite_team_apply_leader_role_swap($team_id, $from_user_id, $to_user_id);

    if ($subscription_id !== '' && ($simultaneous_handoff || $new_leader_customer !== '')) {
        aidunite_team_handoff_stripe_billing_to_leader($team_id, $to_user_id);
        aidunite_team_clear_leader_transfer_pending($team_id);
        if (function_exists('aidunite_notify_user')) {
            $team = get_post($team_id);
            $team_name = $team ? $team->post_title : 'チーム';
            aidunite_notify_user(
                $to_user_id,
                '代表者への譲渡が完了しました',
                "{$team_name} の代表者になりました。引き続きチーム運営をお願いします。",
                'leader_transfer',
                $team_id
            );
        }

        return [
            'success' => true,
            'mode' => 'simultaneous',
            'team_id' => $team_id,
            'new_leader_id' => $to_user_id,
        ];
    }

    if ($needs_checkout) {
        $days = aidunite_payment_get_leader_transfer_checkout_days();
        $deadline = date('Y-m-d', strtotime('+' . $days . ' days'));
        $payload = [
            'from_user_id' => $from_user_id,
            'to_user_id' => $to_user_id,
            'started_at' => current_time('mysql'),
            'checkout_deadline' => $deadline,
            'mode' => 'checkout_required',
        ];
        if (function_exists('aidunite_team_persist_write_leader_transfer_pending')) {
            aidunite_team_persist_write_leader_transfer_pending($team_id, $payload);
        }
        if (function_exists('aidunite_notify_user')) {
            $team = get_post($team_id);
            $team_name = $team ? $team->post_title : 'チーム';
            $payment_url = home_url('/payment-setup');
            aidunite_notify_user(
                $to_user_id,
                '代表者への譲渡とお支払い設定のお願い',
                "{$team_name} の代表者に任命されました。{$deadline} までにお支払い設定（Checkout）を完了してください。\n{$payment_url}",
                'leader_transfer_checkout',
                $team_id
            );
            aidunite_notify_user(
                $from_user_id,
                '代表者譲渡を受け付けました',
                "{$team_name} の代表者を譲渡しました。新代表が {$deadline} までに Checkout を完了する必要があります。",
                'leader_transfer',
                $team_id
            );
        }

        return [
            'success' => true,
            'mode' => 'checkout_required',
            'checkout_deadline' => $deadline,
            'team_id' => $team_id,
            'new_leader_id' => $to_user_id,
            'payment_setup_url' => home_url('/payment-setup'),
        ];
    }

    aidunite_team_clear_leader_transfer_pending($team_id);

    return [
        'success' => true,
        'mode' => 'no_subscription',
        'team_id' => $team_id,
        'new_leader_id' => $to_user_id,
    ];
}

/**
 * Checkout 完了時に譲渡 pending を解消
 *
 * @param int $team_id
 * @param int $user_id
 */
function aidunite_team_complete_leader_transfer_checkout($team_id, $user_id) {
    $pending = aidunite_team_read_leader_transfer_pending((int) $team_id);
    if ($pending === null) {
        return;
    }
    if ((int) ($pending['to_user_id'] ?? 0) !== (int) $user_id) {
        return;
    }
    aidunite_team_handoff_stripe_billing_to_leader((int) $team_id, (int) $user_id);
    aidunite_team_clear_leader_transfer_pending((int) $team_id);
}

/**
 * 期限切れ譲渡 pending の処理（cron）
 */
function aidunite_team_process_expired_leader_transfers() {
    $teams = get_posts([
        'post_type' => 'team',
        'post_status' => 'any',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => [
            ['key' => AIDUNITE_TEAM_LEADER_TRANSFER_PENDING_META, 'compare' => 'EXISTS'],
        ],
    ]);
    $today = current_time('Y-m-d');
    foreach ($teams ?: [] as $team_id) {
        $team_id = (int) $team_id;
        $pending = aidunite_team_read_leader_transfer_pending($team_id);
        if ($pending === null) {
            continue;
        }
        $deadline = (string) ($pending['checkout_deadline'] ?? '');
        if ($deadline === '' || $deadline >= $today) {
            continue;
        }
        $from = (int) ($pending['from_user_id'] ?? 0);
        $to = (int) ($pending['to_user_id'] ?? 0);
        if ($from > 0 && $to > 0 && function_exists('aidunite_team_settings_user_is_leader_of_team')
            && aidunite_team_settings_user_is_leader_of_team($to, $team_id)) {
            aidunite_team_apply_leader_role_swap($team_id, $to, $from);
        }
        aidunite_team_clear_leader_transfer_pending($team_id);
        if (function_exists('aidunite_notify_user')) {
            $team = get_post($team_id);
            $team_name = $team ? $team->post_title : 'チーム';
            foreach (array_filter([$from, $to]) as $uid) {
                aidunite_notify_user(
                    (int) $uid,
                    '代表者譲渡が期限切れになりました',
                    "{$team_name} の代表者譲渡は、お支払い設定が期限内に完了しなかったため取り消されました。",
                    'leader_transfer_expired',
                    $team_id
                );
            }
        }
    }
}

add_action('aidunite_team_leader_transfer_process_expired', 'aidunite_team_process_expired_leader_transfers');
add_action('init', static function () {
    if (get_transient('aidunite_leader_transfer_cron_registered')) {
        return;
    }
    if (!wp_next_scheduled('aidunite_team_leader_transfer_process_expired')) {
        wp_schedule_event(time(), 'daily', 'aidunite_team_leader_transfer_process_expired');
    }
    set_transient('aidunite_leader_transfer_cron_registered', 1, DAY_IN_SECONDS);
}, 99);

add_action('rest_api_init', static function () {
    register_rest_route('aidunite/v1', '/team-leader/transfer', [
        'methods' => 'POST',
        'callback' => 'aidunite_rest_team_leader_transfer',
        'permission_callback' => static function ($request) {
            $result = AidUniteAuthMiddleware::rest_require($request, ['team_id' => null]);
            return !is_wp_error($result);
        },
    ]);

    register_rest_route('aidunite/v1', '/team-leader/transfer-candidates', [
        'methods' => 'GET',
        'callback' => 'aidunite_rest_team_leader_transfer_candidates',
        'permission_callback' => static function ($request) {
            $result = AidUniteAuthMiddleware::rest_require($request, ['team_id' => null]);
            return !is_wp_error($result);
        },
        'args' => [
            'team_id' => ['required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint'],
        ],
    ]);
});

/**
 * @param WP_REST_Request $request
 */
function aidunite_rest_team_leader_transfer($request) {
    $user_id = get_current_user_id();
    $params = $request->get_json_params();
    if (!is_array($params)) {
        $params = [];
    }
    $team_id = (int) ($params['team_id'] ?? 0);
    $to_user_id = (int) ($params['new_leader_user_id'] ?? $params['to_user_id'] ?? 0);
    $simultaneous = !empty($params['simultaneous_handoff']);
    $result = aidunite_team_start_leader_transfer($team_id, $user_id, $to_user_id, $simultaneous);
    if (is_wp_error($result)) {
        return $result;
    }

    return new WP_REST_Response(['success' => true, 'data' => $result], 200);
}

/**
 * @param WP_REST_Request $request
 */
function aidunite_rest_team_leader_transfer_candidates($request) {
    $user_id = get_current_user_id();
    $team_id = (int) $request->get_param('team_id');
    if (!function_exists('aidunite_team_settings_user_is_leader_of_team')
        || !aidunite_team_settings_user_is_leader_of_team($user_id, $team_id)) {
        return new WP_Error('forbidden', '代表者のみ利用できます。', ['status' => 403]);
    }

    return new WP_REST_Response([
        'success' => true,
        'candidates' => aidunite_team_get_leader_transfer_candidates($team_id, $user_id),
        'checkout_deadline_days' => aidunite_payment_get_leader_transfer_checkout_days(),
        'pending' => aidunite_team_read_leader_transfer_pending($team_id),
    ], 200);
}
