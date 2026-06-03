<?php
/**
 * 決済共通関数
 * 金額計算、登録選手数カウント、トライアル期間計算など
 */

/**
 * チームタイプを取得
 */
function aidunite_get_team_type($team_id) {
    $raw = get_post_meta($team_id, 'team_type', true);
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
function aidunite_calculate_monthly_fee($team_id) {
    $team_type = aidunite_get_team_type($team_id);
    $payment_mode = aidunite_get_team_payment_mode($team_id);
    $config = aidunite_get_payment_config();

    if ($team_type === 'school') {
        // 学校チーム
        if ($payment_mode === 'board') {
            // 教育委員会契約
            return (int) ($config['school']['board_amount'] ?? 2000);
        } elseif ($payment_mode === 'school') {
            // 学校契約
            return (int) ($config['school']['school_amount'] ?? 2000);
        } elseif ($payment_mode === 'personal') {
            // 個人契約
            return (int) ($config['school']['personal_amount'] ?? $config['school']['amount'] ?? 2000);
        } else {
            // デフォルト（個人契約）
            return (int) ($config['school']['personal_amount'] ?? $config['school']['amount'] ?? 2000);
        }
    } elseif ($team_type === 'club') {
        // クラブチーム（登録選手数 × 1人あたり金額）
        $player_count = aidunite_get_registered_player_count($team_id);
        $base_amount = (int) $config['club']['base_amount'];
        return $player_count * $base_amount;
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

    if (empty($plan_id)) {
        // デフォルトプランを取得
        $config_key = function_exists('aidunite_team_type_payment_config_key')
            ? aidunite_team_type_payment_config_key($team_type)
            : ($team_type === 'club' ? 'club' : 'school');
        $plans = $config[$config_key]['plans'];
        foreach ($plans as $plan) {
            if (!empty($plan['is_default'])) {
                return $plan;
            }
        }
        // デフォルトプランがない場合は最初のプラン
        return !empty($plans) ? $plans[0] : null;
    }

    $config_key = function_exists('aidunite_team_type_payment_config_key')
        ? aidunite_team_type_payment_config_key($team_type)
        : ($team_type === 'club' ? 'club' : 'school');
    $plans = $config[$config_key]['plans'];
    foreach ($plans as $plan) {
        if ($plan['id'] === $plan_id) {
            return $plan;
        }
    }

    return null;
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

    $start_timestamp = strtotime($trial_start_date);

    if ($plan['trial_type'] === 'days') {
        // 日数指定
        $trial_days = (int) $plan['trial_value'];
        return date('Y-m-d H:i:s', strtotime("+{$trial_days} days", $start_timestamp));
    } elseif ($plan['trial_type'] === 'free_months') {
        // 無料月数指定
        $free_months = (int) $plan['trial_value'];
        return date('Y-m-d H:i:s', strtotime("+{$free_months} months", $start_timestamp));
    }

    return null;
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
    $team_type = aidunite_get_team_type($team_id);
    $payment_mode = aidunite_get_team_payment_mode($team_id);

    if ($team_type === 'school') {
        if ($payment_mode === 'board') {
            return 'education_board'; // 教育委員会契約
        } elseif ($payment_mode === 'school') {
            return 'school_invoice'; // 学校契約（請求書）
        } elseif ($payment_mode === 'personal') {
            return 'personal_stripe'; // 個人契約（Stripe）
        }
    } elseif ($team_type === 'club') {
        return 'club_stripe'; // クラブチーム（Stripe）
    }

    return 'unknown';
}

/**
 * 専用コードを生成
 */
function aidunite_generate_registration_code($length = 12) {
    $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // 0, O, I, 1を除外
    $code = '';

    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[random_int(0, strlen($characters) - 1)];
    }

    return $code;
}

/**
 * ユニークな専用コードを生成（重複チェック付き）
 */
function aidunite_generate_unique_registration_code($length = 12) {
    $max_attempts = 100;
    $attempt = 0;

    while ($attempt < $max_attempts) {
        $code = aidunite_generate_registration_code($length);

        if (!aidunite_is_registration_code_used($code)) {
            return $code;
        }

        $attempt++;
    }

    // 最大試行回数に達した場合は長さを増やす
    return aidunite_generate_registration_code($length + 2);
}

/**
 * 早期決済特典を計算（請求日統一対応・後払い方式）
 * @param int $team_id チームID
 * @return array|null ['bonus_months' => 2|1|0, 'unified_billing_date' => '2025-03-01', 'first_billing_date' => '2025-02-01', 'message' => '特典メッセージ']
 */
function aidunite_calculate_early_payment_bonus($team_id) {
    $trial_start = aidunite_get_trial_start_date($team_id);
    if (empty($trial_start)) {
        return null;
    }

    $days_elapsed = floor((time() - strtotime($trial_start)) / (24 * 60 * 60));

    // 元のトライアル終了日を計算（30日後）
    $original_trial_end = strtotime('+30 days', strtotime($trial_start));

    // 統一請求日（毎月1日・後払い方式）
    $unified_billing_day = 1;

    // 元のトライアル終了日以降の最初の統一請求日を計算（後払い方式）
    // トライアル終了日の月の次の月の1日が最初の請求日
    $year = date('Y', $original_trial_end);
    $month = date('m', $original_trial_end);

    // トライアル終了日の次の月の1日が最初の請求日（後払い方式）
    $first_billing_timestamp = strtotime("{$year}-{$month}-{$unified_billing_day} +1 month");
    $first_billing_date = date('Y-m-' . sprintf('%02d', $unified_billing_day), $first_billing_timestamp);

    // 早期決済特典を計算（後払い方式）
    if ($days_elapsed <= 20) {
        // 20日以内 → 2か月無料
        // 最初の請求日から2か月後の請求日まで無料
        $first_charge_date = date('Y-m-' . sprintf('%02d', $unified_billing_day), strtotime('+2 months', $first_billing_timestamp));

        return [
            'bonus_months' => 2,
            'unified_billing_date' => $first_charge_date,
            'first_billing_date' => $first_billing_date, // 最初の請求日（ただし無料）
            'message' => '🎉 早期決済特典: 2か月無料！',
            'days_elapsed' => $days_elapsed
        ];
    } elseif ($days_elapsed <= 30) {
        // 21-30日以内 → 1か月無料
        // 最初の請求日から1か月後の請求日まで無料
        $first_charge_date = date('Y-m-' . sprintf('%02d', $unified_billing_day), strtotime('+1 month', $first_billing_timestamp));

        return [
            'bonus_months' => 1,
            'unified_billing_date' => $first_charge_date,
            'first_billing_date' => $first_billing_date, // 最初の請求日（ただし無料）
            'message' => '🎁 早期決済特典: 1か月無料！',
            'days_elapsed' => $days_elapsed
        ];
    } else {
        // 30日を超えた場合（特典なしでも統一請求日に合わせる）
        return [
            'bonus_months' => 0,
            'unified_billing_date' => $first_billing_date,
            'first_billing_date' => $first_billing_date,
            'message' => '通常料金',
            'days_elapsed' => $days_elapsed
        ];
    }
}
