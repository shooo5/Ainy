<?php
/**
 * 出口・解約関連 team meta の保存（正本）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param int    $team_id
 * @param int    $user_id
 * @param string $exit_type cancel|withdraw|dissolve
 * @param string $complete_at Y-m-d
 * @return bool
 */
function aidunite_payment_exit_persist_write_pending($team_id, $user_id, $exit_type, $complete_at) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return false;
    }
    $payload = [
        'type' => (string) $exit_type,
        'user_id' => (int) $user_id,
        'requested_at' => current_time('mysql'),
        'complete_at' => (string) $complete_at,
    ];
    update_post_meta($team_id, AIDUNITE_TEAM_PAYMENT_EXIT_PENDING_META, wp_json_encode($payload));
    if ($exit_type === 'dissolve') {
        aidunite_payment_exit_persist_write_dissolution_date($team_id, $complete_at);
    }

    return true;
}

/**
 * @param int    $team_id
 * @param string $complete_at Y-m-d
 */
function aidunite_payment_exit_persist_write_dissolution_date($team_id, $complete_at) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return;
    }
    $date = function_exists('aidunite_payment_normalize_trial_start_date_value')
        ? aidunite_payment_normalize_trial_start_date_value($complete_at)
        : trim((string) $complete_at);
    if ($date === '') {
        return;
    }
    update_post_meta($team_id, AIDUNITE_TEAM_DISSOLUTION_DATE_META, $date);
}

/**
 * @param int                  $team_id
 * @param array<string, mixed> $pending
 */
function aidunite_payment_exit_persist_update_pending($team_id, array $pending) {
    $team_id = (int) $team_id;
    if ($team_id <= 0 || $pending === []) {
        return;
    }
    update_post_meta($team_id, AIDUNITE_TEAM_PAYMENT_EXIT_PENDING_META, wp_json_encode($pending));
    if ((string) ($pending['type'] ?? '') === 'dissolve' && !empty($pending['complete_at'])) {
        aidunite_payment_exit_persist_write_dissolution_date($team_id, (string) $pending['complete_at']);
    }
}

/**
 * @param int $team_id
 */
function aidunite_payment_exit_persist_clear_pending($team_id) {
    delete_post_meta((int) $team_id, AIDUNITE_TEAM_PAYMENT_EXIT_PENDING_META);
    delete_post_meta((int) $team_id, AIDUNITE_TEAM_DISSOLUTION_DATE_META);
}

/**
 * @param int $team_id
 */
function aidunite_payment_exit_persist_mark_first_match_modal_shown($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return;
    }
    update_post_meta($team_id, AIDUNITE_TEAM_BILLING_FIRST_MATCH_META, '1');
    delete_post_meta($team_id, AIDUNITE_TEAM_BILLING_FIRST_MATCH_PENDING_META);
}

/**
 * 初回実試合成立後にモーダル表示待ちを立てる（ボット誤表示の dismiss をリセット）
 *
 * @param int $team_id
 */
function aidunite_payment_exit_persist_set_first_match_modal_pending($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return;
    }
    update_post_meta($team_id, AIDUNITE_TEAM_BILLING_FIRST_MATCH_PENDING_META, '1');
    delete_post_meta($team_id, AIDUNITE_TEAM_BILLING_FIRST_MATCH_META);
}

/**
 * 1回表示したら pending を下ろす（マイページ再訪のたびに出さない）
 *
 * @param int $team_id
 */
function aidunite_payment_exit_persist_clear_first_match_modal_pending($team_id) {
    delete_post_meta((int) $team_id, AIDUNITE_TEAM_BILLING_FIRST_MATCH_PENDING_META);
}

/**
 * お支払い設定へ遷移・訪問後しばらくモーダルを出さない
 *
 * @param int $team_id
 */
function aidunite_payment_exit_persist_snooze_first_match_modal_payment_setup($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return;
    }
    $hours = 48;
    if (function_exists('aidunite_payment_exit_get_first_match_payment_setup_snooze_hours')) {
        $hours = (int) aidunite_payment_exit_get_first_match_payment_setup_snooze_hours();
    }
    $hours = max(1, $hours);
    $until = wp_date('Y-m-d H:i:s', time() + ($hours * HOUR_IN_SECONDS));
    update_post_meta($team_id, AIDUNITE_TEAM_BILLING_FIRST_MATCH_SNOOZE_META, $until);
    delete_post_meta($team_id, AIDUNITE_TEAM_BILLING_FIRST_MATCH_PENDING_META);
}

/**
 * 解約完了時の team subscription メタ削除
 *
 * @param int $team_id
 */
function aidunite_payment_exit_persist_clear_team_subscription_meta($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return;
    }
    if (function_exists('aidunite_team_delete_stripe_subscription_meta')) {
        aidunite_team_delete_stripe_subscription_meta($team_id);
    }
    delete_post_meta($team_id, 'team_payment_available_until');
}
