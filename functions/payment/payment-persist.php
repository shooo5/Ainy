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
    $allowed = ['trial', 'paid', 'unpaid', 'cancelled'];
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
 * チーム登録時など plan + trial + mode を一括
 *
 * @param int                  $team_id
 * @param array<string, mixed> $fields payment_mode, selected_plan_id, trial_start_date
 * @return void
 */
function aidunite_payment_persist_team_registration_meta($team_id, array $fields) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return;
    }
    if (!empty($fields['payment_mode'])) {
        aidunite_team_write_payment_mode_meta($team_id, (string) $fields['payment_mode']);
    }
    if (!empty($fields['selected_plan_id'])) {
        aidunite_team_write_selected_plan_meta($team_id, (string) $fields['selected_plan_id']);
    }
    if (!empty($fields['selected_plan_id']) && array_key_exists('trial_start_date', $fields)) {
        aidunite_team_write_trial_start_date_meta($team_id, $fields['trial_start_date'] !== null ? (string) $fields['trial_start_date'] : null);
    } elseif (!empty($fields['selected_plan_id'])) {
        aidunite_team_write_trial_start_date_meta($team_id, null);
    }
}

/**
 * @param int $team_id
 * @return array<string, mixed>
 */
function aidunite_payment_get_canonical_team_meta($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0 || get_post_type($team_id) !== 'team') {
        return [];
    }

    $mode_raw = (string) get_post_meta($team_id, 'payment_mode', true);
    $mode = $mode_raw;
    if (function_exists('aidunite_normalize_payment_mode_value')) {
        $mode = aidunite_normalize_payment_mode_value($mode_raw);
    }

    return [
        'team_id' => $team_id,
        'payment_mode' => $mode,
        'payment_mode_raw' => $mode_raw,
        'selected_plan_id' => (string) get_post_meta($team_id, 'selected_plan_id', true),
        'trial_start_date' => (string) get_post_meta($team_id, 'trial_start_date', true),
        'selected_payment_method' => (string) get_post_meta($team_id, 'selected_payment_method', true),
    ];
}

/**
 * @param int $user_id
 * @return array<string, mixed>
 */
function aidunite_payment_get_canonical_user_meta($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return [];
    }

    $status_raw = (string) get_user_meta($user_id, 'payment_status', true);

    return [
        'user_id' => $user_id,
        'payment_status' => aidunite_payment_normalize_payment_status_value($status_raw) ?: $status_raw,
        'payment_status_raw' => $status_raw,
        'payment_status_updated' => (string) get_user_meta($user_id, 'payment_status_updated', true),
        'stripe_customer_id' => (string) get_user_meta($user_id, 'stripe_customer_id', true),
        'stripe_subscription_id' => (string) get_user_meta($user_id, 'stripe_subscription_id', true),
    ];
}
