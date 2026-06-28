<?php

/**

 * 管理者用決済一覧（タブ: チームシステム料 / 保護者月謝 / Stripe連携）

 */



if (!defined('ABSPATH')) {

    exit;

}

require_once get_template_directory() . '/functions/payment/admin-payment-metrics.php';



/**

 * @return string[]

 */

function aidunite_admin_payment_list_allowed_tabs() {

    return ['teams', 'tuition', 'stripe'];

}



/**

 * @param string $tab

 * @return string

 */

function aidunite_admin_payment_list_normalize_tab($tab) {

    $tab = sanitize_key((string) $tab);



    return in_array($tab, aidunite_admin_payment_list_allowed_tabs(), true) ? $tab : 'teams';

}



/**

 * Stripe ダッシュボードがテストモードか

 *

 * @return bool

 */

function aidunite_admin_payment_list_stripe_is_test_mode() {

    $secret = trim((string) get_option('aidunite_stripe_secret_key', ''));



    return strpos($secret, 'sk_test_') === 0;

}



/**

 * @param string $path

 * @return string

 */

function aidunite_admin_payment_list_stripe_dashboard_url($path) {

    $path = ltrim((string) $path, '/');

    $base = aidunite_admin_payment_list_stripe_is_test_mode()

        ? 'https://dashboard.stripe.com/test/'

        : 'https://dashboard.stripe.com/';



    return $base . $path;

}



/**

 * @param string $stripe_id

 * @return string

 */

function aidunite_admin_payment_list_short_stripe_id($stripe_id) {

    $stripe_id = trim((string) $stripe_id);

    if ($stripe_id === '') {

        return '—';

    }

    if (strlen($stripe_id) <= 14) {

        return $stripe_id;

    }



    return substr($stripe_id, 0, 10) . '…';

}



/**

 * チーム行（システム料タブ）

 *

 * @param int $team_id

 * @return array<string, mixed>

 */

function aidunite_admin_payment_list_team_row_data($team_id) {

    $team_id = (int) $team_id;

    $team_post = get_post($team_id);

    $team_name = $team_post ? (string) $team_post->post_title : ('ID:' . $team_id);



    $leader_user_id = function_exists('aidunite_team_resolve_leader_user_id')
        ? (int) aidunite_team_resolve_leader_user_id($team_id)
        : (function_exists('aidunite_team_read_leader_id') ? (int) aidunite_team_read_leader_id($team_id) : 0);



    $leader_name = '—';

    $leader_email = '';

    if ($leader_user_id > 0) {

        $leader = get_userdata($leader_user_id);

        if ($leader) {

            $leader_name = $leader->display_name ?: $leader->user_login;

            $leader_email = (string) $leader->user_email;

        }

    }



    $payload = function_exists('aidunite_payment_read_team_payload')

        ? aidunite_payment_read_team_payload($team_id, $leader_user_id)

        : [];

    $tuition_display = function_exists('aidunite_payment_read_tuition_display')

        ? aidunite_payment_read_tuition_display($team_id)

        : [];



    $subscription_state = function_exists('aidunite_payment_exit_read_subscription_state')

        ? aidunite_payment_exit_read_subscription_state($team_id, $leader_user_id)

        : [];

    $subscription_id = (string) ($subscription_state['stripe_subscription_id'] ?? '');



    $leader_meta = ($leader_user_id > 0 && function_exists('aidunite_payment_read_canonical_user_meta'))

        ? aidunite_payment_read_canonical_user_meta($leader_user_id)

        : [];

    $customer_id = (string) ($leader_meta['stripe_customer_id'] ?? '');



    $connect_account_id = function_exists('aidunite_payment_read_stripe_connect_account_id')

        ? trim((string) aidunite_payment_read_stripe_connect_account_id($team_id))

        : trim((string) ($tuition_display['stripe_connect_account_id'] ?? ''));

    $connect_ready = $connect_account_id !== '' && strpos($connect_account_id, 'acct_') === 0;



    $contract = aidunite_admin_payment_list_team_contract_display($team_id, $leader_user_id);

    $payment_mode = (string) ($payload['payment_mode'] ?? '');

    $plan_label = (string) ($payload['product_plan_label'] ?? 'Matchプラン');



    $monthly_fee = ($leader_user_id > 0 && function_exists('aidunite_calculate_monthly_fee'))

        ? (int) aidunite_calculate_monthly_fee($team_id, $leader_user_id)

        : 0;

    if ($monthly_fee <= 0 && isset($payload['pricing']['monthly_fee'])) {

        $monthly_fee = (int) $payload['pricing']['monthly_fee'];

    }



    $next_billing_label = '—';

    if (function_exists('aidunite_payment_resolve_trial_end_date')) {

        $trial_end = aidunite_payment_resolve_trial_end_date($team_id, $leader_user_id);

        if ($trial_end !== '') {

            $next_billing_label = function_exists('aidunite_payment_format_date_display')

                ? aidunite_payment_format_date_display($trial_end) . '（トライアル終了）'

                : substr($trial_end, 0, 10) . '（トライアル終了）';

        }

    }

    if ($next_billing_label === '—' && !empty($subscription_state['has_subscription'])) {

        $next_billing_label = 'サブスク連携済';

    }



    $founding_payload = is_array($payload['founding'] ?? null) ? $payload['founding'] : [];

    $is_founding_team = !empty($founding_payload['is_founding_team']);

    $slots_remaining = function_exists('aidunite_payment_read_founding_slots_remaining')

        ? (int) aidunite_payment_read_founding_slots_remaining()

        : 0;



    $status_updated = function_exists('aidunite_payment_read_team_payment_status_updated')
        ? aidunite_payment_read_team_payment_status_updated($team_id)
        : '';



    return [

        'team_id' => $team_id,

        'team_name' => $team_name,

        'leader_user_id' => $leader_user_id > 0 ? $leader_user_id : '—',

        'leader_name' => $leader_name,

        'leader_email' => $leader_email,

        'plan_label' => $plan_label,

        'contract_mode_label' => aidunite_admin_payment_list_contract_mode_label($payment_mode),

        'contract_label' => (string) $contract['label'],

        'contract_modifier' => (string) $contract['modifier'],

        'contract_filter_key' => (string) $contract['filter_key'],

        'monthly_fee' => $monthly_fee > 0 ? number_format($monthly_fee) . '円' : '—',

        'stripe_subscription_id' => $subscription_id,

        'stripe_subscription_short' => aidunite_admin_payment_list_short_stripe_id($subscription_id),

        'stripe_customer_id' => $customer_id,

        'stripe_customer_short' => aidunite_admin_payment_list_short_stripe_id($customer_id),

        'stripe_subscription_linked' => $subscription_id !== '',

        'stripe_customer_linked' => $customer_id !== '',

        'connect_account_id' => $connect_account_id,

        'connect_account_short' => aidunite_admin_payment_list_short_stripe_id($connect_account_id),

        'connect_linked' => $connect_ready,

        'tuition_enabled' => !empty($tuition_display['team_tuition_enabled']),

        'tuition_open' => function_exists('aidunite_payment_team_tuition_open_for_parents')

            && aidunite_payment_team_tuition_open_for_parents($team_id),

        'next_billing_label' => $next_billing_label !== '' ? $next_billing_label : '—',

        'status_updated' => $status_updated !== '' ? $status_updated : '—',

        'founding_label' => $is_founding_team ? 'Founding' : '—',

        'can_assign_founding' => !$is_founding_team && $slots_remaining > 0,

        'team_settings_url' => add_query_arg('team_id', $team_id, home_url('/team-settings')),

        'payment_setup_url' => home_url('/payment-setup'),

        'team_payment_url' => home_url('/team-payment-management'),

        'stripe_subscription_url' => $subscription_id !== ''

            ? aidunite_admin_payment_list_stripe_dashboard_url('subscriptions/' . rawurlencode($subscription_id))

            : '',

        'stripe_customer_url' => $customer_id !== ''

            ? aidunite_admin_payment_list_stripe_dashboard_url('customers/' . rawurlencode($customer_id))

            : '',

        'stripe_connect_url' => $connect_ready

            ? aidunite_admin_payment_list_stripe_dashboard_url('connect/accounts/' . rawurlencode($connect_account_id))

            : '',

    ];

}



/**

 * Stripe 連携サマリー

 *

 * @return array<string, mixed>

 */

function aidunite_admin_payment_list_stripe_summary() {

    $teams = aidunite_admin_payment_list_collect_team_ids();



    $summary = [

        'team_total' => count($teams),

        'system_subscription_count' => 0,

        'system_customer_count' => 0,

        'system_paid_count' => 0,

        'system_trial_count' => 0,

        'system_unpaid_count' => 0,

        'connect_linked_count' => 0,

        'tuition_open_count' => 0,

        'tuition_parent_registered_count' => 0,

        'is_test_mode' => aidunite_admin_payment_list_stripe_is_test_mode(),

    ];



    $attention = [

        'club_no_connect' => [],

        'tuition_enabled_no_connect' => [],

        'subscription_no_customer' => [],

    ];



    $tuition_parent_ids = [];



    foreach ($teams as $team_id) {

        $row = aidunite_admin_payment_list_team_row_data($team_id);



        if (!empty($row['stripe_subscription_linked'])) {

            $summary['system_subscription_count']++;

        }

        if (!empty($row['stripe_customer_linked'])) {

            $summary['system_customer_count']++;

        }



        $filter_key = (string) ($row['contract_filter_key'] ?? '');

        if ($filter_key === 'paid') {

            $summary['system_paid_count']++;

        } elseif ($filter_key === 'trial') {

            $summary['system_trial_count']++;

        } elseif ($filter_key === 'unpaid') {

            $summary['system_unpaid_count']++;

        }



        if (!empty($row['connect_linked'])) {

            $summary['connect_linked_count']++;

        }

        if (!empty($row['tuition_open'])) {

            $summary['tuition_open_count']++;

        }



        $plan_label = (string) ($row['plan_label'] ?? '');

        if ($plan_label === 'Clubプラン' && empty($row['connect_linked'])) {

            $attention['club_no_connect'][] = $row;

        }

        if (!empty($row['tuition_enabled']) && empty($row['connect_linked'])) {

            $attention['tuition_enabled_no_connect'][] = $row;

        }

        if (!empty($row['stripe_subscription_linked']) && empty($row['stripe_customer_linked'])) {

            $attention['subscription_no_customer'][] = $row;

        }



        if (function_exists('aidunite_payment_read_team_tuition_collections_page_model')) {

            $model = aidunite_payment_read_team_tuition_collections_page_model($team_id);

            foreach ((array) ($model['parents'] ?? []) as $parent_row) {

                if (empty($parent_row['has_subscription'])) {

                    continue;

                }

                $parent_id = (int) ($parent_row['user_id'] ?? 0);

                if ($parent_id <= 0) {

                    continue;

                }

                $tuition_parent_ids[$parent_id . ':' . $team_id] = true;

            }

        }

    }



    $summary['tuition_parent_registered_count'] = count($tuition_parent_ids);



    return [

        'summary' => $summary,

        'attention' => $attention,

    ];

}



/**

 * タブ URL

 *

 * @param string $tab

 * @param array<string, mixed> $extra

 * @return string

 */

function aidunite_admin_payment_list_tab_url($tab, array $extra = []) {

    $base = remove_query_arg(

        ['apl_paged', 'paged', 'contract_filter', 'tuition_status_filter', 'team_q'],

        get_permalink()

    );

    $args = array_merge(['apl_tab' => aidunite_admin_payment_list_normalize_tab($tab)], $extra);

    $args = array_filter($args, static function ($value) {

        return $value !== null && $value !== '';

    });



    return add_query_arg($args, $base);

}

