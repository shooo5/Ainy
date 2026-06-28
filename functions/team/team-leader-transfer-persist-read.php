<?php
/**
 * 代表者譲渡 pending meta の読み取り（正本）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('AIDUNITE_TEAM_LEADER_TRANSFER_PENDING_META')) {
    define('AIDUNITE_TEAM_LEADER_TRANSFER_PENDING_META', 'team_leader_transfer_pending');
}

/**
 * @param mixed $raw
 * @return array<string, mixed>|null
 */
function aidunite_team_normalize_leader_transfer_pending_payload($raw) {
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $raw = $decoded;
        }
    }
    if (!is_array($raw)) {
        return null;
    }
    $to_user_id = (int) ($raw['to_user_id'] ?? 0);
    if ($to_user_id <= 0) {
        return null;
    }

    return [
        'from_user_id' => (int) ($raw['from_user_id'] ?? 0),
        'to_user_id' => $to_user_id,
        'started_at' => (string) ($raw['started_at'] ?? ''),
        'checkout_deadline' => (string) ($raw['checkout_deadline'] ?? ''),
        'mode' => (string) ($raw['mode'] ?? ''),
    ];
}

/**
 * @param int $team_id
 * @return array<string, mixed>|null
 */
function aidunite_team_read_leader_transfer_pending($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return null;
    }

    return aidunite_team_normalize_leader_transfer_pending_payload(
        get_post_meta($team_id, AIDUNITE_TEAM_LEADER_TRANSFER_PENDING_META, true)
    );
}
