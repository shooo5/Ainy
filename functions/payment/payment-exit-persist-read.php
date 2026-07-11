<?php
/**
 * 出口・解約関連 team meta の読み取り（正本）
 *
 * @package AidUnite
 * @see docs/spec/payment.md §12B
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('AIDUNITE_TEAM_PAYMENT_EXIT_PENDING_META')) {
    define('AIDUNITE_TEAM_PAYMENT_EXIT_PENDING_META', 'team_payment_exit_pending');
}
if (!defined('AIDUNITE_TEAM_DISSOLUTION_DATE_META')) {
    define('AIDUNITE_TEAM_DISSOLUTION_DATE_META', 'team_dissolution_date');
}
if (!defined('AIDUNITE_TEAM_BILLING_FIRST_MATCH_META')) {
    define('AIDUNITE_TEAM_BILLING_FIRST_MATCH_META', 'billing_first_match_modal_shown');
}
if (!defined('AIDUNITE_TEAM_BILLING_FIRST_MATCH_PENDING_META')) {
    define('AIDUNITE_TEAM_BILLING_FIRST_MATCH_PENDING_META', 'billing_first_match_modal_pending');
}
if (!defined('AIDUNITE_TEAM_BILLING_FIRST_MATCH_SNOOZE_META')) {
    define('AIDUNITE_TEAM_BILLING_FIRST_MATCH_SNOOZE_META', 'billing_first_match_modal_payment_setup_snooze_until');
}

/**
 * @param mixed $raw
 * @return array<string, mixed>|null
 */
function aidunite_payment_exit_normalize_pending_payload($raw) {
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $raw = $decoded;
        }
    }
    if (!is_array($raw)) {
        return null;
    }
    $type = (string) ($raw['type'] ?? '');
    if ($type === '') {
        return null;
    }

    return [
        'type' => $type,
        'user_id' => (int) ($raw['user_id'] ?? 0),
        'requested_at' => (string) ($raw['requested_at'] ?? ''),
        'complete_at' => (string) ($raw['complete_at'] ?? ''),
    ];
}

/**
 * @param int $team_id
 * @return array<string, mixed>|null
 */
function aidunite_payment_exit_read_pending($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return null;
    }

    return aidunite_payment_exit_normalize_pending_payload(
        get_post_meta($team_id, AIDUNITE_TEAM_PAYMENT_EXIT_PENDING_META, true)
    );
}

/**
 * @param int $team_id
 * @return string Y-m-d
 */
function aidunite_payment_exit_read_dissolution_date($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return '';
    }

    return trim((string) get_post_meta($team_id, AIDUNITE_TEAM_DISSOLUTION_DATE_META, true));
}

/**
 * @param int $team_id
 */
function aidunite_payment_exit_read_first_match_modal_shown($team_id) {
    return (bool) get_post_meta((int) $team_id, AIDUNITE_TEAM_BILLING_FIRST_MATCH_META, true);
}

/**
 * @param int $team_id
 */
function aidunite_payment_exit_read_first_match_modal_pending($team_id) {
    return get_post_meta((int) $team_id, AIDUNITE_TEAM_BILLING_FIRST_MATCH_PENDING_META, true) === '1';
}

/**
 * お支払い設定ページ訪問後の再表示抑制が有効か
 *
 * @param int $team_id
 */
function aidunite_payment_exit_read_first_match_modal_snooze_active($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return false;
    }
    $until = trim((string) get_post_meta($team_id, AIDUNITE_TEAM_BILLING_FIRST_MATCH_SNOOZE_META, true));
    if ($until === '') {
        return false;
    }
    $ts = strtotime($until);

    return $ts !== false && $ts > time();
}

/**
 * @param int $team_id
 * @param int $user_id
 * @return int
 */
function aidunite_payment_exit_resolve_leader_user_id($team_id, $user_id = 0) {
    if ($user_id > 0) {
        return (int) $user_id;
    }
    if (function_exists('aidunite_team_resolve_leader_user_id')) {
        return (int) aidunite_team_resolve_leader_user_id((int) $team_id);
    }

    if (function_exists('aidunite_team_read_leader_id')) {
        return (int) aidunite_team_read_leader_id((int) $team_id);
    }

    return 0;
}

/**
 * @param int $team_id
 * @param int $user_id
 * @return array<string, mixed>
 */
function aidunite_payment_exit_read_subscription_state($team_id, $user_id = 0) {
    return aidunite_payment_read_team_subscription_state((int) $team_id, (int) $user_id);
}

/**
 * UI / REST 用 exit state payload
 *
 * @param int $team_id
 * @param int $user_id
 * @return array<string, mixed>
 */
function aidunite_payment_exit_read_exit_state_payload($team_id, $user_id = 0) {
    $team_id = (int) $team_id;
    $user_id = (int) $user_id;
    if ($team_id <= 0) {
        return [];
    }
    $pending = aidunite_payment_exit_read_pending($team_id);
    $subscription = aidunite_payment_exit_read_subscription_state($team_id, $user_id);
    $in_grace = $pending !== null;

    return [
        'team_id' => $team_id,
        'pending' => $pending,
        'in_grace' => $in_grace,
        'dissolution_date' => aidunite_payment_exit_read_dissolution_date($team_id),
        'complete_at' => is_array($pending) ? (string) ($pending['complete_at'] ?? '') : '',
        'exit_type' => is_array($pending) ? (string) ($pending['type'] ?? '') : '',
        'can_show_cancel_ui' => !$in_grace
            && !empty($subscription['has_subscription'])
            && empty($subscription['is_cancelled']),
    ];
}
