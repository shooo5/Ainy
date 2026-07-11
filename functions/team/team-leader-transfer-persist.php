<?php
/**
 * 代表者譲渡 pending meta の保存（正本）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param int                  $team_id
 * @param array<string, mixed> $payload
 */
function aidunite_team_persist_write_leader_transfer_pending($team_id, array $payload) {
    $team_id = (int) $team_id;
    if ($team_id <= 0 || $payload === []) {
        return;
    }
    update_post_meta($team_id, AIDUNITE_TEAM_LEADER_TRANSFER_PENDING_META, wp_json_encode($payload));
}

/**
 * @param int $team_id
 */
function aidunite_team_persist_clear_leader_transfer_pending($team_id) {
    delete_post_meta((int) $team_id, AIDUNITE_TEAM_LEADER_TRANSFER_PENDING_META);
}
