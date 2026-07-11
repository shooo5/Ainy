<?php
/**
 * 決済共通関数
 * 金額計算、登録選手数カウント、トライアル期間計算など
 */

/**
 * チームタイプを取得
 */
function aidunite_get_team_type($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return '';
    }
    if (function_exists('aidunite_team_read_canonical_meta')) {
        $canonical = aidunite_team_read_canonical_meta($team_id);
        $raw = (string) ($canonical['team_type'] ?? '');
    } elseif (function_exists('aidunite_team_get_canonical_meta')) {
        $canonical = aidunite_team_get_canonical_meta($team_id);
        $raw = (string) ($canonical['team_type'] ?? '');
    } else {
        $raw = '';
    }
    if (function_exists('aidunite_team_type_to_canonical')) {
        return aidunite_team_type_to_canonical($raw);
    }

    return (string) $raw;
}

/**
 * 登録選手数を取得（保護者は除外）
 */
function aidunite_get_registered_player_count($team_id) {
    global $wpdb;

    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return 0;
    }

    $like_members = '%' . $wpdb->esc_like('i:' . $team_id . ';') . '%';
    $managed_key = defined('AIDUNITE_USER_META_MANAGED_TEAM_IDS') ? AIDUNITE_USER_META_MANAGED_TEAM_IDS : 'managed_team_ids';
    $tid_str = (string) $team_id;

    $json_sql = '';
    $json_args = [];
    if (function_exists('aidunite_db_supports_json_contains_user_meta') && aidunite_db_supports_json_contains_user_meta()) {
        $json_sql = ' OR (JSON_VALID(umg.meta_value) AND JSON_CONTAINS(umg.meta_value, CAST(%d AS JSON), \'$\'))';
        $json_args[] = $team_id;
    }

    $sql = "SELECT COUNT(DISTINCT u.ID)
         FROM {$wpdb->users} u
         INNER JOIN {$wpdb->usermeta} umr ON u.ID = umr.user_id AND umr.meta_key = 'aidunite_role' AND umr.meta_value = 'player'
         LEFT JOIN {$wpdb->usermeta} umt ON u.ID = umt.user_id AND umt.meta_key = 'team_id'
         LEFT JOIN {$wpdb->usermeta} umm ON u.ID = umm.user_id AND umm.meta_key = 'team_memberships'
         LEFT JOIN {$wpdb->usermeta} umg ON u.ID = umg.user_id AND umg.meta_key = %s
         WHERE (umt.meta_value IS NOT NULL AND CAST(umt.meta_value AS UNSIGNED) = %d)
            OR (umm.meta_value IS NOT NULL AND umm.meta_value LIKE %s)
            OR (umg.meta_value IS NOT NULL AND (umg.meta_value = %s OR CAST(umg.meta_value AS UNSIGNED) = %d" . $json_sql . '))';

    $args = array_merge(
        [$managed_key, $team_id, $like_members, $tid_str, $team_id],
        $json_args
    );

    $count = (int) $wpdb->get_var($wpdb->prepare($sql, $args));

    return $count;
}

/**
 * チームの月額料金を計算
 */
function aidunite_calculate_monthly_fee($team_id, $user_id = 0) {
    $team_id = (int) $team_id;
    $user_id = (int) $user_id;
    if ($team_id <= 0) {
        return 0;
    }
    if ($user_id <= 0 && is_user_logged_in()) {
        $user_id = (int) get_current_user_id();
    }
    if (function_exists('aidunite_payment_read_pricing_payload')) {
        $pricing = aidunite_payment_read_pricing_payload($team_id, $user_id);

        return (int) ($pricing['monthly_fee'] ?? 0);
    }

    return 0;
}

/**
 * プラン情報を取得
 */
function aidunite_get_plan_info($team_id, $plan_id = null) {
    $team_type = aidunite_get_team_type($team_id);
    $config = aidunite_get_payment_config();

    if ($plan_id === null) {
        $plan_id = aidunite_get_selected_plan_id($team_id);
    }

    $product_plan = function_exists('aidunite_get_team_product_plan')
        ? aidunite_get_team_product_plan($team_id)
        : 'match';
    if (function_exists('aidunite_payment_normalize_selected_plan_id')) {
        $plan_id = aidunite_payment_normalize_selected_plan_id((string) $plan_id, $product_plan);
    }

    $config_key = $product_plan === 'club' ? 'club' : 'match';

    if (empty($plan_id)) {
        $plans = $config[$config_key]['plans'] ?? $config['match']['plans'] ?? [];
        foreach ($plans as $plan) {
            if (!empty($plan['is_default'])) {
                return $plan;
            }
        }
        return !empty($plans) ? $plans[0] : null;
    }

    $plans = $config[$config_key]['plans'] ?? $config['match']['plans'] ?? [];
    foreach ($plans as $plan) {
        if ($plan['id'] === $plan_id) {
            return $plan;
        }
    }

    $fallback_plans = $config[$config_key]['plans'] ?? $config['match']['plans'] ?? [];
    foreach ($fallback_plans as $plan) {
        if (!empty($plan['is_default'])) {
            return $plan;
        }
    }

    return !empty($fallback_plans) ? $fallback_plans[0] : null;
}

/**
 * 無料期間の終了日時（当月末 23:59:59）を算出
 *
 * 請求は毎月1日のため、開始日+Nヶ月の途中日ではなく
 * 無料対象月の月末で締め、日割りと月額請求の齟齬を避ける。
 *
 * @param string|DateTimeInterface $trial_start_date
 * @param int                      $free_months 無料対象の暦月数（2暦月無料=2）
 * @return DateTimeImmutable|null
 */
function aidunite_payment_resolve_trial_end_at_month_end($trial_start_date, $free_months = 1) {
    $free_months = max(1, (int) $free_months);
    $tz = wp_timezone();

    if ($trial_start_date instanceof DateTimeInterface) {
        $start = DateTimeImmutable::createFromInterface($trial_start_date)->setTimezone($tz);
    } else {
        $start = new DateTimeImmutable((string) $trial_start_date, $tz);
    }

    $start = $start->setTime(0, 0, 0);
    if ($free_months <= 1) {
        $month_anchor = $start->modify('first day of this month');
    } else {
        $month_anchor = $start->modify('first day of this month')
            ->modify('+' . ($free_months - 1) . ' months');
    }

    return $month_anchor->modify('last day of this month')->setTime(23, 59, 59);
}

/**
 * トライアル終了日を計算
 */
function aidunite_calculate_trial_end_date($team_id) {
    $trial_start_date = aidunite_get_trial_start_date($team_id);

    if (empty($trial_start_date)) {
        return null;
    }

    $plan = aidunite_get_plan_info($team_id);

    if (empty($plan)) {
        return null;
    }

    if ($plan['trial_type'] === 'first_month_free' || $plan['trial_type'] === 'free_months') {
        $free_months = max(1, (int) ($plan['trial_value'] ?? 1));
        $end = aidunite_payment_resolve_trial_end_at_month_end($trial_start_date, $free_months);
        return $end instanceof DateTimeImmutable ? $end->format('Y-m-d H:i:s') : null;
    }

    $start_timestamp = strtotime($trial_start_date);

    if ($plan['trial_type'] === 'days') {
        $trial_days = (int) $plan['trial_value'];
        return date('Y-m-d H:i:s', strtotime("+{$trial_days} days", $start_timestamp));
    }

    return null;
}

/**
 * 次回請求日（毎月1日）を算出
 *
 * @param string|null $after_date_ymd この日より後の最初の月1日（Y-m-d）。null のときは「今日より後」
 * @return string Y-m-d
 */
function aidunite_payment_resolve_next_billing_date_1st($after_date_ymd = null) {
    $tz = wp_timezone();

    if ($after_date_ymd === null || $after_date_ymd === '') {
        $after = new DateTimeImmutable('now', $tz);
    } else {
        $after = DateTimeImmutable::createFromFormat('Y-m-d', substr($after_date_ymd, 0, 10), $tz);
        if ($after === false) {
            $after = new DateTimeImmutable($after_date_ymd, $tz);
        }
    }

    $after = $after->setTime(0, 0, 0);
    $candidate = $after->modify('first day of this month');

    if ($candidate <= $after) {
        $candidate = $candidate->modify('first day of next month');
    }

    return $candidate->format('Y-m-d');
}

/**
 * 決済画面の日付表示（Ainy UI 統一: yy/mm/dd（曜））
 *
 * @param string|int|null $date Y-m-d / mysql datetime / Unix timestamp
 * @return string
 */
function aidunite_payment_format_date_display($date) {
    if ($date === null || $date === '') {
        return '';
    }

    if (!class_exists('AidUniteDateUtils')) {
        require_once get_template_directory() . '/functions/common/date-utils.php';
    }

    return AidUniteDateUtils::formatDateForDisplay($date);
}

/**
 * 請求日の表示用フォーマット（毎月1日・画面表示統一）
 *
 * @param string $billing_date_ymd Y-m-d
 * @return string
 */
function aidunite_payment_format_billing_date_display($billing_date_ymd) {
    return aidunite_payment_format_date_display($billing_date_ymd);
}

/**
 * Stripe 等の Unix 時刻から次回請求日（毎月1日）表示文字列へ
 *
 * @param int $timestamp
 * @return string yy/mm/dd（曜）
 */
function aidunite_payment_format_next_billing_from_timestamp($timestamp) {
    $timestamp = (int) $timestamp;
    if ($timestamp <= 0) {
        return '';
    }

    $day = (int) wp_date('j', $timestamp);
    if ($day === 1) {
        return aidunite_payment_format_date_display($timestamp);
    }

    $ymd = wp_date('Y-m-d', $timestamp);
    $billing_ymd = aidunite_payment_resolve_next_billing_date_1st($ymd);

    return aidunite_payment_format_billing_date_display($billing_ymd);
}

/**
 * トライアル期間中かチェック
 */
function aidunite_is_trial_period($team_id) {
    $trial_end_date = aidunite_calculate_trial_end_date($team_id);

    if (empty($trial_end_date)) {
        return false;
    }

    $current_time = current_time('mysql');
    return strtotime($current_time) < strtotime($trial_end_date);
}

/**
 * 支払いが必要かチェック
 */
function aidunite_is_payment_required($user_id) {
    $status = aidunite_get_payment_status($user_id);

    if ($status === 'paid') {
        return false;
    }

    // チームID（操作中 team を優先）
    $team_id = function_exists('aidunite_get_current_team_id')
        ? (int) aidunite_get_current_team_id((int) $user_id)
        : (int) get_user_meta($user_id, 'team_id', true);

    if ($team_id <= 0) {
        return false; // チーム未所属の場合は支払い不要
    }

    // トライアル期間中かチェック
    if (aidunite_is_trial_period($team_id)) {
        return false;
    }

    // 未払いまたはトライアル終了
    return true;
}

/**
 * 契約パターンを判定
 */
function aidunite_get_contract_pattern($team_id) {
    $payment_mode = aidunite_get_team_payment_mode($team_id);
    $product_plan = function_exists('aidunite_get_team_product_plan')
        ? aidunite_get_team_product_plan($team_id)
        : 'match';

    if ($payment_mode === 'corporate') {
        return $product_plan === 'club' ? 'corporate_club' : 'corporate_match';
    }

    return $product_plan === 'club' ? 'personal_club' : 'personal_match';
}

/**
 * トライアル終了日時を取得
 *
 * @param int $team_id
 * @return string|null Y-m-d H:i:s
 */
function aidunite_get_trial_end_date($team_id) {
    $end = aidunite_calculate_trial_end_date((int) $team_id);

    return $end !== null && $end !== '' ? (string) $end : null;
}

/**
 * トライアル残日数（当日含む、0 以上）
 *
 * @param int $team_id
 * @return int|null trial 未開始時は null
 */
function aidunite_payment_get_trial_days_remaining($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0 || aidunite_get_trial_start_date($team_id) === '') {
        return null;
    }

    $end = aidunite_get_trial_end_date($team_id);
    if ($end === null) {
        return null;
    }

    $remaining = (int) ceil((strtotime($end) - strtotime(current_time('mysql'))) / DAY_IN_SECONDS);

    return max(0, $remaining);
}

/**
 * トライアル終了日の表示ラベル（yy/mm/dd（曜））
 *
 * @param int $team_id
 * @return string
 */
function aidunite_payment_format_trial_end_label($team_id) {
    $end = aidunite_get_trial_end_date((int) $team_id);
    if ($end === null) {
        return '';
    }

    return aidunite_payment_format_date_display($end);
}

/**
 * 未課金チームの無料期間開始日を保証（表示用 trial_end 算出の前提）
 *
 * @param int $team_id
 * @param int $user_id
 * @return bool trial_start_date が利用可能になったか
 */
function aidunite_payment_ensure_trial_started($team_id, $user_id = 0) {
    $team_id = (int) $team_id;
    $user_id = (int) $user_id;
    if ($team_id <= 0) {
        return false;
    }

    if (aidunite_get_trial_start_date($team_id) !== '') {
        return true;
    }

    if (function_exists('aidunite_get_team_stripe_subscription_id')
        && aidunite_get_team_stripe_subscription_id($team_id, $user_id) !== '') {
        return false;
    }

    $team_status = function_exists('aidunite_get_team_payment_status')
        ? (string) aidunite_get_team_payment_status($team_id, $user_id)
        : '';
    if ($team_status === 'paid') {
        return false;
    }

    if (function_exists('aidunite_payment_start_trial_on_team_approval')) {
        aidunite_payment_start_trial_on_team_approval($team_id, $user_id);
    } else {
        aidunite_set_trial_start_date($team_id);
    }

    return aidunite_get_trial_start_date($team_id) !== '';
}

/**
 * 画面表示用の無料期間終了日時（Y-m-d H:i:s）を解決
 *
 * @param int $team_id
 * @param int $user_id
 * @return string
 */
function aidunite_payment_resolve_trial_end_date($team_id, $user_id = 0) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return '';
    }

    aidunite_payment_ensure_trial_started($team_id, $user_id);

    $end = aidunite_calculate_trial_end_date($team_id);
    if ($end) {
        return (string) $end;
    }

    return '';
}

/**
 * チーム承認（publish）時に Match 2ヶ月無料トライアルを開始
 *
 * @param int $team_id
 * @param int $leader_user_id
 */
function aidunite_payment_start_trial_on_team_approval($team_id, $leader_user_id = 0) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return;
    }

    if (aidunite_get_trial_start_date($team_id) !== '') {
        return;
    }

    if (function_exists('aidunite_get_team_stripe_subscription_id')
        && aidunite_get_team_stripe_subscription_id($team_id) !== '') {
        return;
    }

    if ($leader_user_id <= 0 && function_exists('aidunite_team_resolve_leader_user_id')) {
        $leader_user_id = (int) aidunite_team_resolve_leader_user_id($team_id);
    }

    $plan_id = function_exists('aidunite_get_selected_plan_id')
        ? (string) aidunite_get_selected_plan_id($team_id)
        : '';
    if ($plan_id === '' && function_exists('aidunite_payment_persist_plan_selection')) {
        aidunite_payment_persist_plan_selection($team_id, $leader_user_id, [
            'selected_plan_id' => 'plan_match',
            'product_plan' => 'match',
        ]);
    }

    aidunite_set_trial_start_date($team_id);

    if ($leader_user_id > 0 && function_exists('aidunite_set_team_payment_status')) {
        $current = function_exists('aidunite_get_payment_status')
            ? (string) aidunite_get_payment_status($leader_user_id)
            : '';
        if ($current === '' || $current === 'unpaid') {
            aidunite_set_team_payment_status($team_id, 'trial', $leader_user_id);
        }
    }
}
