<?php
/**
 * 決済レガシー（スタンダードプラン / school 課金）の正規化
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 旧プラン ID → 現行 ID
 *
 * @return array<string, string>
 */
function aidunite_payment_legacy_plan_id_map() {
    return [
        'plan_standard' => 'plan_match',
        'plan_premium' => 'plan_club',
        'plan_school' => 'plan_match',
        'standard' => 'plan_match',
        'premium' => 'plan_club',
        'school' => 'plan_match',
    ];
}

/**
 * @param string $plan_id
 * @param string $product_plan match|club
 * @return string
 */
function aidunite_payment_normalize_selected_plan_id($plan_id, $product_plan = 'match') {
    $plan_id = trim((string) $plan_id);
    $map = aidunite_payment_legacy_plan_id_map();

    if (isset($map[$plan_id])) {
        return $map[$plan_id];
    }
    if ($plan_id === 'plan_match' || $plan_id === 'plan_club') {
        return $plan_id;
    }
    if ($plan_id !== '' && strpos($plan_id, 'club') !== false) {
        return 'plan_club';
    }
    if ($plan_id !== '') {
        return 'plan_match';
    }

    return $product_plan === 'club' ? 'plan_club' : 'plan_match';
}

/**
 * @param array<string, mixed> $plan
 */
function aidunite_payment_is_legacy_plan_definition(array $plan) {
    $id = (string) ($plan['id'] ?? '');
    $name = (string) ($plan['name'] ?? '');
    $map = aidunite_payment_legacy_plan_id_map();

    if (isset($map[$id])) {
        return true;
    }
    if (strpos($name, 'スタンダード') !== false || strpos($name, 'プレミアム') !== false) {
        return true;
    }
    if (($plan['trial_type'] ?? '') === 'days') {
        return true;
    }

    return false;
}

/**
 * @param array<string, mixed> $config
 * @param array<string, mixed> $default_config
 * @return array{0: array<string, mixed>, 1: bool}
 */
function aidunite_payment_migrate_config_legacy(array $config, array $default_config) {
    $needs_save = false;

    if (!isset($config['match']['monthly_amount']) && isset($config['school']['personal_amount'])) {
        $config['match']['monthly_amount'] = (int) $config['school']['personal_amount'];
        $needs_save = true;
    }

    foreach (['match', 'club'] as $key) {
        if (empty($config[$key]['plans']) || !is_array($config[$key]['plans'])) {
            $config[$key]['plans'] = $default_config[$key]['plans'] ?? $default_config['match']['plans'];
            $needs_save = true;
            continue;
        }
        foreach ($config[$key]['plans'] as $plan) {
            if (!is_array($plan) || !aidunite_payment_is_legacy_plan_definition($plan)) {
                continue;
            }
            $config[$key]['plans'] = $default_config[$key]['plans'] ?? $default_config['match']['plans'];
            $needs_save = true;
            break;
        }
    }

    if (isset($config['school'])) {
        $school_amount = (int) ($config['school']['personal_amount'] ?? $config['school']['amount'] ?? 0);
        $match_amount = (int) ($config['match']['monthly_amount'] ?? 0);
        if ($match_amount <= 0 || ($school_amount > 0 && $match_amount === $school_amount)) {
            $config['match']['monthly_amount'] = (int) ($default_config['match']['monthly_amount'] ?? 2000);
            $needs_save = true;
        }
        unset($config['school']);
        $needs_save = true;
    }

    if (!isset($config['match']['monthly_amount'])) {
        $config['match']['monthly_amount'] = (int) ($default_config['match']['monthly_amount'] ?? 2000);
        $needs_save = true;
    }

    $target_months = aidunite_payment_get_default_trial_months();
    $target_description = aidunite_payment_get_trial_copy()['plan_description'];
    foreach (['match', 'club'] as $plan_key) {
        if (empty($config[$plan_key]['plans']) || !is_array($config[$plan_key]['plans'])) {
            continue;
        }
        foreach ($config[$plan_key]['plans'] as $index => $plan) {
            if (!is_array($plan)) {
                continue;
            }
            $trial_type = (string) ($plan['trial_type'] ?? '');
            if (!in_array($trial_type, ['first_month_free', 'free_months'], true)) {
                continue;
            }
            $current_value = (int) ($plan['trial_value'] ?? 0);
            $current_description = (string) ($plan['description'] ?? '');
            $needs_trial_update = $current_value < $target_months
                || $trial_type === 'first_month_free'
                || strpos($current_description, '初月無料') !== false
                || strpos($current_description, '30日') !== false;
            if (!$needs_trial_update) {
                continue;
            }
            $config[$plan_key]['plans'][$index]['trial_type'] = 'free_months';
            $config[$plan_key]['plans'][$index]['trial_value'] = $target_months;
            if ($plan_key === 'club' && (string) ($plan['id'] ?? '') === 'plan_club') {
                $config[$plan_key]['plans'][$index]['description'] = aidunite_payment_get_trial_copy()['club_plan_description'];
            } elseif ($current_description === '' || strpos($current_description, '初月無料') !== false || strpos($current_description, '30日') !== false) {
                $config[$plan_key]['plans'][$index]['description'] = $target_description;
            }
            $needs_save = true;
        }
    }

    return [$config, $needs_save];
}

/**
 * チームの旧決済メタを現行形式へ（DB 更新あり）
 *
 * @param int $team_id
 * @return bool 変更があったか
 */
function aidunite_payment_migrate_team_legacy_meta($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0 || get_post_type($team_id) !== 'team') {
        return false;
    }

    $changed = false;
    $product_plan_raw = (string) get_post_meta($team_id, 'product_plan', true);
    $product_plan = function_exists('aidunite_payment_normalize_product_plan_value')
        ? aidunite_payment_normalize_product_plan_value($product_plan_raw !== '' ? $product_plan_raw : 'match')
        : 'match';

    $raw_plan_id = (string) get_post_meta($team_id, 'selected_plan_id', true);
    $normalized_plan_id = aidunite_payment_normalize_selected_plan_id($raw_plan_id, $product_plan);
    if ($raw_plan_id !== $normalized_plan_id) {
        aidunite_team_write_selected_plan_meta($team_id, $normalized_plan_id);
        $changed = true;
    }

    if (strpos($normalized_plan_id, 'club') !== false && $product_plan !== 'club') {
        aidunite_team_write_product_plan_meta($team_id, 'club');
        $changed = true;
    }

    $payment_method = (string) (aidunite_payment_read_canonical_team_meta($team_id)['selected_payment_method'] ?? '');
    $payment_mode_raw = (string) (aidunite_payment_read_canonical_team_meta($team_id)['payment_mode_raw'] ?? '');
    $has_subscription = function_exists('aidunite_get_team_stripe_subscription_id')
        && aidunite_get_team_stripe_subscription_id($team_id) !== '';

    if (
        !$has_subscription
        && ($payment_method === 'invoice' || in_array($payment_mode_raw, ['school', 'business', 'board'], true))
    ) {
        aidunite_team_write_selected_payment_method_meta($team_id, 'stripe');
        aidunite_team_write_payment_mode_meta($team_id, 'personal');
        $changed = true;
    }

    return $changed;
}

/**
 * 全チームの旧決済メタを一括正規化（1回のみ）
 */
function aidunite_payment_migrate_all_teams_legacy_meta() {
    $team_ids = get_posts([
        'post_type' => 'team',
        'post_status' => 'any',
        'posts_per_page' => -1,
        'fields' => 'ids',
    ]);

    foreach ($team_ids as $team_id) {
        aidunite_payment_migrate_team_legacy_meta((int) $team_id);
    }
}

/**
 * プラン説明＋トライアル表記（重複を避ける）
 *
 * @param array<string, mixed> $plan
 * @return string
 */
function aidunite_payment_format_plan_subtitle(array $plan) {
    $description = trim((string) ($plan['description'] ?? ''));
    $trial_type = (string) ($plan['trial_type'] ?? '');
    $trial_value = (int) ($plan['trial_value'] ?? 0);

    $suffix = '';
    if ($trial_type === 'first_month_free' || $trial_type === 'free_months') {
        $months = max(1, $trial_value > 0 ? $trial_value : (function_exists('aidunite_payment_get_default_trial_months') ? aidunite_payment_get_default_trial_months() : 2));
        $suffix = $months >= 2 ? '2ヶ月無料' : '初月無料';
    } elseif ($trial_type === 'days' && $trial_value > 0) {
        $suffix = 'トライアル: ' . $trial_value . '日間';
    }

    if ($suffix === '') {
        return $description;
    }
    if (
        $description === ''
        || $description === $suffix
        || strpos($description, 'トライアル') !== false
        || strpos($description, '初月無料') !== false
        || strpos($description, '2ヶ月無料') !== false
        || strpos($description, '30日') !== false
    ) {
        return $suffix;
    }

    return $description . ' | ' . $suffix;
}

/**
 * 決済フラッシュメッセージ（Stripe 戻り先 query）
 *
 * @return array{type: string, message: string}|null
 */
function aidunite_payment_read_flash_from_query() {
    if (!isset($_GET['payment'])) {
        return null;
    }

    $state = sanitize_key((string) wp_unslash($_GET['payment']));
    if ($state === 'success') {
        return [
            'type' => 'success',
            'message' => 'お支払い手続きが完了しました。',
        ];
    }
    if ($state === 'cancelled') {
        return [
            'type' => 'info',
            'message' => 'お支払い手続きはキャンセルされました。必要なときに再度お試しください。',
        ];
    }

    return null;
}

add_action('init', static function () {
    if (get_option('aidunite_payment_legacy_migrated_v2')) {
        return;
    }
    if (function_exists('aidunite_get_payment_config')) {
        aidunite_get_payment_config();
    }
    aidunite_payment_migrate_all_teams_legacy_meta();
    update_option('aidunite_payment_legacy_migrated_v2', 1);
    delete_option('aidunite_payment_legacy_migrated_v1');
}, 25);
