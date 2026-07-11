<?php
/**
 * 決済関連メタの保存本体（normalize 経由）
 *
 * @see docs/spec/payment.md
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param mixed $raw
 * @return float 0〜100、小数第2位まで
 */
function aidunite_payment_normalize_tuition_fee_percent_value($raw) {
    if (!is_numeric($raw)) {
        return 0.0;
    }

    $value = round((float) $raw, 2);
    if ($value < 0) {
        return 0.0;
    }
    if ($value > 100) {
        return 100.0;
    }

    return $value;
}

/**
 * @param array<string, mixed> $raw
 * @return array<string, mixed>
 */
function aidunite_payment_normalize_input(array $raw) {
    if (function_exists('aidunite_normalize_payment_payload')) {
        return aidunite_normalize_payment_payload($raw);
    }

    return $raw;
}

/**
 * @param mixed $raw
 * @return string Y-m-d または空
 */
function aidunite_payment_normalize_trial_start_date_value($raw) {
    $s = trim((string) $raw);
    if ($s === '') {
        return '';
    }
    $ts = strtotime($s);
    if ($ts === false) {
        return '';
    }

    return date('Y-m-d', $ts);
}

/**
 * @param mixed $raw
 * @return string
 */
function aidunite_payment_normalize_payment_status_value($raw) {
    $s = strtolower(trim((string) $raw));
    $allowed = ['trial', 'paid', 'unpaid', 'cancelling', 'cancelled'];
    if (!in_array($s, $allowed, true)) {
        return '';
    }

    return $s;
}

/**
 * team post_meta: payment_mode
 *
 * @param int    $team_id
 * @param string $mode_raw
 * @return string 保存値（失敗時は空）
 */
function aidunite_team_write_payment_mode_meta($team_id, $mode_raw) {
    $team_id = (int) $team_id;
    if ($team_id <= 0 || get_post_type($team_id) !== 'team') {
        return '';
    }
    $normalized = aidunite_payment_normalize_input(['payment_mode' => $mode_raw]);
    $mode = (string) ($normalized['payment_mode'] ?? '');
    if ($mode === '' && function_exists('aidunite_normalize_payment_mode_value')) {
        $mode = aidunite_normalize_payment_mode_value($mode_raw);
    }
    if ($mode === '') {
        return '';
    }
    update_post_meta($team_id, 'payment_mode', $mode);

    return $mode;
}

/**
 * @param int    $team_id
 * @param string $plan_id_raw
 * @return string
 */
function aidunite_team_write_selected_plan_meta($team_id, $plan_id_raw) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return '';
    }
    $plan_id = sanitize_text_field((string) $plan_id_raw);
    if ($plan_id === '') {
        return '';
    }
    update_post_meta($team_id, 'selected_plan_id', $plan_id);

    return $plan_id;
}

/**
 * @param int $team_id
 * @return bool
 */
function aidunite_team_delete_selected_plan_meta($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return false;
    }

    delete_post_meta($team_id, 'selected_plan_id');

    return get_post_meta($team_id, 'selected_plan_id', true) === '';
}

/**
 * @param int         $team_id
 * @param string|null $date_raw null のとき今日（サイトタイムゾーン）
 * @return string
 */
function aidunite_team_write_trial_start_date_meta($team_id, $date_raw = null) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return '';
    }
    if ($date_raw === null) {
        $date_raw = current_time('Y-m-d');
    }
    $date = aidunite_payment_normalize_trial_start_date_value($date_raw);
    if ($date === '') {
        $date = aidunite_payment_normalize_trial_start_date_value(current_time('mysql'));
    }
    if ($date === '') {
        return '';
    }
    update_post_meta($team_id, 'trial_start_date', $date);

    return $date;
}

/**
 * user_meta: payment_status + payment_status_updated
 *
 * @param int    $user_id
 * @param string $status_raw
 * @return string
 */
function aidunite_user_write_payment_status_meta($user_id, $status_raw) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return '';
    }
    $status = aidunite_payment_normalize_payment_status_value($status_raw);
    if ($status === '') {
        return '';
    }
    update_user_meta($user_id, 'payment_status', $status);
    update_user_meta($user_id, 'payment_status_updated', current_time('mysql'));

    return $status;
}

/**
 * @param int    $user_id
 * @param string $customer_id
 * @return string
 */
function aidunite_user_write_stripe_customer_meta($user_id, $customer_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return '';
    }
    $customer_id = sanitize_text_field((string) $customer_id);
    if ($customer_id === '') {
        return '';
    }
    update_user_meta($user_id, 'stripe_customer_id', $customer_id);

    return $customer_id;
}

/**
 * @param int    $user_id
 * @param string $subscription_id
 * @return string
 */
function aidunite_user_write_stripe_subscription_meta($user_id, $subscription_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return '';
    }
    $subscription_id = sanitize_text_field((string) $subscription_id);
    if ($subscription_id === '') {
        return '';
    }
    update_user_meta($user_id, 'stripe_subscription_id', $subscription_id);

    return $subscription_id;
}

/**
 * team post_meta: product_plan
 *
 * @param int    $team_id
 * @param string $plan_raw
 * @return string
 */
function aidunite_team_write_product_plan_meta($team_id, $plan_raw) {
    $team_id = (int) $team_id;
    if ($team_id <= 0 || get_post_type($team_id) !== 'team') {
        return '';
    }
    $normalized = aidunite_payment_normalize_input(['product_plan' => $plan_raw]);
    $plan = (string) ($normalized['product_plan'] ?? '');
    if ($plan === '' && function_exists('aidunite_payment_normalize_product_plan_value')) {
        $plan = aidunite_payment_normalize_product_plan_value($plan_raw);
    }
    if ($plan === '') {
        $plan = 'match';
    }
    update_post_meta($team_id, 'product_plan', $plan);

    return $plan;
}

/**
 * @param int  $team_id
 * @param bool $is_founding
 * @param int  $founding_year
 * @return bool
 */
function aidunite_team_write_founding_team_meta($team_id, $is_founding, $founding_year = 0) {
    $team_id = (int) $team_id;
    if ($team_id <= 0 || get_post_type($team_id) !== 'team') {
        return false;
    }
    if (!$is_founding) {
        delete_post_meta($team_id, 'founding_team');
        delete_post_meta($team_id, 'founding_year');

        return true;
    }
    $year = (int) $founding_year;
    if ($year <= 0) {
        $year = (int) current_time('Y');
    }
    update_post_meta($team_id, 'founding_team', '1');
    update_post_meta($team_id, 'founding_year', $year);

    return true;
}

/**
 * @param int $team_id
 * @return bool
 */
function aidunite_payment_persist_assign_founding_team($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return false;
    }
    if (function_exists('aidunite_payment_read_founding_slots_remaining')
        && aidunite_payment_read_founding_slots_remaining() <= 0) {
        return false;
    }
    $canonical = function_exists('aidunite_payment_read_canonical_team_meta')
        ? aidunite_payment_read_canonical_team_meta($team_id)
        : [];
    if (!empty($canonical['founding_team'])) {
        return true;
    }

    return aidunite_team_write_founding_team_meta($team_id, true, (int) current_time('Y'));
}

/**
 * @param int $team_id
 * @return bool
 */
function aidunite_payment_persist_upgrade_to_club($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return false;
    }
    aidunite_team_write_product_plan_meta($team_id, 'club');
    $config = function_exists('aidunite_get_payment_config') ? aidunite_get_payment_config() : [];
    $club_plans = $config['club']['plans'] ?? [];
    $plan_id = 'plan_club';
    foreach ($club_plans as $plan) {
        if (!empty($plan['id'])) {
            $plan_id = (string) $plan['id'];
            break;
        }
    }
    aidunite_team_write_selected_plan_meta($team_id, $plan_id);

    return true;
}

/**
 * @param int                  $team_id
 * @param int                  $user_id
 * @param array<string, mixed> $fields
 * @return array<string, mixed>
 */
function aidunite_payment_persist_plan_selection($team_id, $user_id, array $fields) {
    $team_id = (int) $team_id;
    $user_id = (int) $user_id;
    if ($team_id <= 0) {
        return [];
    }

    $normalized = aidunite_payment_normalize_input($fields);

    if (!empty($normalized['product_plan'])) {
        aidunite_team_write_product_plan_meta($team_id, (string) $normalized['product_plan']);
    } else {
        $canonical_team = aidunite_payment_read_canonical_team_meta($team_id);
        if ((string) ($canonical_team['product_plan_raw'] ?? '') === '') {
            aidunite_team_write_product_plan_meta($team_id, 'match');
        }
    }

    if (!empty($normalized['selected_plan_id'])) {
        aidunite_team_write_selected_plan_meta($team_id, (string) $normalized['selected_plan_id']);
        if (array_key_exists('trial_start_date', $normalized)) {
            if ($normalized['trial_start_date'] !== null && $normalized['trial_start_date'] !== '') {
                aidunite_team_write_trial_start_date_meta(
                    $team_id,
                    (string) $normalized['trial_start_date']
                );
            }
        } else {
            $canonical_team = isset($canonical_team)
                ? $canonical_team
                : aidunite_payment_read_canonical_team_meta($team_id);
            $trial_start = (string) ($canonical_team['trial_start_date'] ?? '');
            if ($trial_start === '') {
                aidunite_team_write_trial_start_date_meta($team_id, null);
            }
        }
    }

    if (!empty($normalized['payment_mode'])) {
        aidunite_team_write_payment_mode_meta($team_id, (string) $normalized['payment_mode']);
    }

    if (!empty($normalized['selected_payment_method'])) {
        aidunite_team_write_selected_payment_method_meta(
            $team_id,
            (string) $normalized['selected_payment_method']
        );
    }

    if ($user_id > 0 && !empty($normalized['assign_founding'])) {
        aidunite_payment_persist_assign_founding_team($team_id);
    }

    if ($user_id > 0 && !empty($normalized['selected_plan_id'])) {
        $team_status = function_exists('aidunite_get_team_payment_status')
            ? aidunite_get_team_payment_status($team_id, $user_id)
            : null;
        if ($team_status === '' || $team_status === null) {
            if (function_exists('aidunite_set_team_payment_status')) {
                aidunite_set_team_payment_status($team_id, 'trial', $user_id);
            }
        }
    }

    return function_exists('aidunite_payment_read_team_payload')
        ? aidunite_payment_read_team_payload($team_id, $user_id)
        : [];
}

/**
 * チーム登録時など plan + trial + mode を一括
 *
 * @param int                  $team_id
 * @param array<string, mixed> $fields
 * @return void
 */
function aidunite_payment_persist_team_registration_meta($team_id, array $fields) {
    if (!isset($fields['product_plan'])) {
        $fields['product_plan'] = 'match';
    }
    aidunite_payment_persist_plan_selection((int) $team_id, 0, $fields);
}

/**
 * チーム月謝設定の保存（page-team-payment-management 用）
 *
 * @param int  $team_id
 * @param bool $tuition_enabled
 * @param int  $monthly_fee_yen 0 以下は未設定（delete）
 * @return bool
 */
function aidunite_payment_write_team_tuition_settings($team_id, $tuition_enabled, $monthly_fee_yen) {
    $team_id = (int) $team_id;
    if ($team_id <= 0 || get_post_type($team_id) !== 'team') {
        return false;
    }
    if (function_exists('aidunite_payment_require_club_plan')
        && !(function_exists('aidunite_payment_plan_gate_user_is_exempt')
            && aidunite_payment_plan_gate_user_is_exempt(get_current_user_id()))) {
        $club_check = aidunite_payment_require_club_plan($team_id);
        if (is_wp_error($club_check)) {
            return false;
        }
    }

    update_post_meta($team_id, 'team_tuition_enabled', $tuition_enabled ? '1' : '0');

    $amount = (int) $monthly_fee_yen;
    if ($amount > 0) {
        update_post_meta($team_id, 'team_monthly_fee', $amount);
    } else {
        delete_post_meta($team_id, 'team_monthly_fee');
    }

    return true;
}

/**
 * team post_meta: team_payment_status
 *
 * @param int    $team_id
 * @param string $status_raw
 * @return string
 */
function aidunite_team_write_payment_status_meta($team_id, $status_raw) {
    $team_id = (int) $team_id;
    if ($team_id <= 0 || get_post_type($team_id) !== 'team') {
        return '';
    }
    $status = aidunite_payment_normalize_payment_status_value($status_raw);
    if ($status === '') {
        return '';
    }
    update_post_meta($team_id, 'team_payment_status', $status);
    update_post_meta($team_id, 'team_payment_status_updated', current_time('mysql'));

    return $status;
}

/**
 * team post_meta: team_payment_status / team_payment_status_updated 削除
 *
 * @param int $team_id
 * @return bool
 */
function aidunite_team_clear_payment_status_meta($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return false;
    }
    delete_post_meta($team_id, 'team_payment_status');
    delete_post_meta($team_id, 'team_payment_status_updated');

    return true;
}

/**
 * @param int $user_id
 * @return int
 */
function aidunite_payment_resolve_user_billing_team_id($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return 0;
    }
    if (function_exists('aidunite_get_current_team_id')) {
        $team_id = (int) aidunite_get_current_team_id($user_id);
        if ($team_id > 0) {
            return $team_id;
        }
    }
    if (function_exists('aidunite_user_read_primary_team_id')) {
        return (int) aidunite_user_read_primary_team_id($user_id);
    }

    return 0;
}

/**
 * @param int    $team_id
 * @param string $subscription_id
 * @return string
 */
function aidunite_team_write_stripe_subscription_meta($team_id, $subscription_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return '';
    }
    $subscription_id = sanitize_text_field((string) $subscription_id);
    if ($subscription_id === '') {
        return '';
    }
    update_post_meta($team_id, 'team_stripe_subscription_id', $subscription_id);

    return $subscription_id;
}

/**
 * team post_meta: team_stripe_subscription_id 削除
 *
 * @param int $team_id
 * @return bool
 */
function aidunite_team_delete_stripe_subscription_meta($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return false;
    }
    delete_post_meta($team_id, 'team_stripe_subscription_id');

    return trim((string) get_post_meta($team_id, 'team_stripe_subscription_id', true)) === '';
}

/**
 * @param int    $team_id
 * @param string $available_until Y-m-d
 * @return string
 */
function aidunite_team_write_selected_payment_method_meta($team_id, $method_raw) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return '';
    }
    $method = sanitize_text_field((string) $method_raw);
    if (!in_array($method, ['stripe', 'invoice'], true)) {
        return '';
    }
    update_post_meta($team_id, 'selected_payment_method', $method);

    return $method;
}

/**
 * @param int    $team_id
 * @param string $available_until Y-m-d
 * @return string
 */
function aidunite_team_write_payment_available_until_meta($team_id, $available_until) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return '';
    }
    $date = aidunite_payment_normalize_trial_start_date_value($available_until);
    if ($date === '') {
        return '';
    }
    update_post_meta($team_id, 'team_payment_available_until', $date);

    return $date;
}

/**
 * @param int    $team_id
 * @param string $price_id
 * @return string
 */
function aidunite_team_write_stripe_price_id_meta($team_id, $price_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return '';
    }
    $price_id = sanitize_text_field((string) $price_id);
    if ($price_id === '') {
        return '';
    }
    update_post_meta($team_id, 'stripe_price_id', $price_id);

    return $price_id;
}

/**
 * @param int    $team_id
 * @param string $account_id
 * @return string
 */
function aidunite_team_write_stripe_connect_account_id_meta($team_id, $account_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return '';
    }
    $account_id = sanitize_text_field((string) $account_id);
    if ($account_id === '') {
        return '';
    }
    update_post_meta($team_id, 'stripe_connect_account_id', $account_id);

    return $account_id;
}

/**
 * @param int    $team_id
 * @param string $product_id
 * @return string
 */
function aidunite_team_write_stripe_connect_product_id_meta($team_id, $product_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0 || get_post_type($team_id) !== 'team') {
        return '';
    }
    $product_id = sanitize_text_field((string) $product_id);
    if ($product_id === '') {
        return '';
    }
    update_post_meta($team_id, 'stripe_connect_product_id', $product_id);

    return $product_id;
}

/**
 * @param int    $team_id
 * @param string $price_id
 * @return string
 */
function aidunite_team_write_stripe_connect_price_id_meta($team_id, $price_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0 || get_post_type($team_id) !== 'team') {
        return '';
    }
    $price_id = sanitize_text_field((string) $price_id);
    if ($price_id === '') {
        return '';
    }
    update_post_meta($team_id, 'stripe_connect_price_id', $price_id);

    return $price_id;
}

/**
 * 保護者の Connect Customer ID（team 単位）
 *
 * @param int    $parent_user_id
 * @param int    $team_id
 * @param string $customer_id
 * @return string
 */
function aidunite_user_write_connect_customer_meta($parent_user_id, $team_id, $customer_id) {
    $parent_user_id = (int) $parent_user_id;
    $team_id = (int) $team_id;
    if ($parent_user_id <= 0 || $team_id <= 0) {
        return '';
    }
    $customer_id = sanitize_text_field((string) $customer_id);
    if ($customer_id === '') {
        return '';
    }
    update_user_meta($parent_user_id, 'stripe_connect_customer_' . $team_id, $customer_id);

    return $customer_id;
}

/**
 * 保護者の月謝サブスクリプション ID（team 単位）
 *
 * @param int    $parent_user_id
 * @param int    $team_id
 * @param string $subscription_id
 * @return string
 */
function aidunite_user_write_tuition_subscription_meta($parent_user_id, $team_id, $subscription_id) {
    $parent_user_id = (int) $parent_user_id;
    $team_id = (int) $team_id;
    if ($parent_user_id <= 0 || $team_id <= 0) {
        return '';
    }
    $subscription_id = sanitize_text_field((string) $subscription_id);
    if ($subscription_id === '') {
        return '';
    }
    update_user_meta($parent_user_id, 'stripe_tuition_subscription_' . $team_id, $subscription_id);

    return $subscription_id;
}

/**
 * 保護者の月謝サブスクリプション ID を削除（team 単位）
 *
 * @param int $parent_user_id
 * @param int $team_id
 * @return bool
 */
function aidunite_user_delete_tuition_subscription_meta($parent_user_id, $team_id) {
    $parent_user_id = (int) $parent_user_id;
    $team_id = (int) $team_id;
    if ($parent_user_id <= 0 || $team_id <= 0) {
        return false;
    }

    delete_user_meta($parent_user_id, 'stripe_tuition_subscription_' . $team_id);

    return aidunite_user_read_tuition_subscription_id($parent_user_id, $team_id) === '';
}

/**
 * user meta: subscription_cancelled_date
 *
 * @param int         $user_id
 * @param string|null $date mysql datetime
 * @return string
 */
function aidunite_user_write_subscription_cancelled_date_meta($user_id, $date = null) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return '';
    }
    $date = $date !== null ? (string) $date : current_time('mysql');
    update_user_meta($user_id, 'subscription_cancelled_date', $date);

    return $date;
}

/**
 * user meta: stripe_subscription_id 削除
 *
 * @param int $user_id
 * @return bool
 */
function aidunite_user_delete_stripe_subscription_meta($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return false;
    }
    delete_user_meta($user_id, 'stripe_subscription_id');

    return true;
}

/**
 * user meta: stripe_customer_id 削除
 *
 * @param int $user_id
 * @return bool
 */
function aidunite_user_delete_stripe_customer_meta($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return false;
    }
    delete_user_meta($user_id, 'stripe_customer_id');

    return true;
}

/**
 * 月謝支払い履歴を DB に保存
 *
 * @param array<string, mixed> $args
 * @return bool
 */
function aidunite_payment_persist_tuition_payment_row(array $args) {
    global $wpdb;

    $defaults = [
        'parent_user_id' => 0,
        'child_id' => null,
        'team_id' => 0,
        'stripe_subscription_id' => '',
        'stripe_invoice_id' => '',
        'amount' => 0,
        'currency' => 'jpy',
        'status' => 'paid',
        'payment_date' => current_time('mysql'),
        'member_status' => null,
        'raw_payload' => null,
    ];

    $data = wp_parse_args($args, $defaults);
    $parent_user_id = (int) $data['parent_user_id'];
    $team_id = (int) $data['team_id'];
    if ($parent_user_id <= 0 || $team_id <= 0) {
        return false;
    }

    $table = $wpdb->prefix . 'aidunite_tuition_payments';
    $now = current_time('mysql');

    $inserted = $wpdb->insert(
        $table,
        [
            'parent_user_id' => $parent_user_id,
            'child_id' => $data['child_id'] ? (int) $data['child_id'] : null,
            'team_id' => $team_id,
            'stripe_subscription_id' => sanitize_text_field((string) $data['stripe_subscription_id']),
            'stripe_invoice_id' => sanitize_text_field((string) $data['stripe_invoice_id']),
            'amount' => (int) $data['amount'],
            'currency' => sanitize_text_field((string) $data['currency']),
            'status' => sanitize_text_field((string) $data['status']),
            'payment_date' => (string) $data['payment_date'],
            'member_status' => $data['member_status'],
            'raw_payload' => $data['raw_payload'],
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            '%d', '%d', '%d',
            '%s', '%s',
            '%d', '%s', '%s',
            '%s', '%s', '%s',
            '%s', '%s',
        ]
    );

    return (bool) $inserted;
}
