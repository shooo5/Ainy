<?php
/**
 * マッチ申請 POST / ステータス更新オーケストレーション
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST /update-match-status の本体
 *
 * @param int    $match_id
 * @param string $action
 * @param int    $user_id
 * @return array{ok:bool,http_status:int,error?:array<string,mixed>,body?:array<string,mixed>}
 */
function aidunite_match_request_submit_update_status($match_id, $action, $user_id) {
    $match_id = (int) $match_id;
    $action = sanitize_text_field((string) $action);
    $user_id = (int) $user_id;

    $post = get_post($match_id);
    if (!$post || $post->post_type !== 'match_request') {
        return [
            'ok' => false,
            'http_status' => 400,
            'error' => [
                'type' => 'wp_error',
                'code' => 'invalid_match',
                'message' => '無効なマッチ申請です',
            ],
        ];
    }

    $access = aidunite_match_request_read_actor_access($match_id, $user_id);
    $canonical = is_array($access['canonical'] ?? null) ? $access['canonical'] : [];
    if ($canonical === []) {
        return [
            'ok' => false,
            'http_status' => 400,
            'error' => [
                'type' => 'wp_error',
                'code' => 'invalid_match',
                'message' => '無効なマッチ申請です',
            ],
        ];
    }

    $from_team_int = (int) ($access['from_team_id'] ?? 0);
    $to_team_int = (int) ($access['to_team_id'] ?? 0);
    $has_from = !empty($access['has_from']);
    $has_to = !empty($access['has_to']);
    $post_status = (string) ($access['post_status'] ?? (string) $post->post_status);

    if (!$has_from && !$has_to) {
        return [
            'ok' => false,
            'http_status' => 403,
            'error' => [
                'type' => 'wp_error',
                'code' => 'no_permission',
                'message' => 'このマッチ申請に対する操作権限がありません。',
            ],
        ];
    }

    if (in_array($action, ['approve', 'reject'], true) && !$has_to) {
        return [
            'ok' => false,
            'http_status' => 403,
            'error' => [
                'type' => 'wp_error',
                'code' => 'no_permission',
                'message' => '承認・拒否は申請先チームの代表者のみが行えます。',
            ],
        ];
    }

    $new_status = '';
    $board_status = '';
    $to_schedule_id = (int) ($canonical['to_schedule_id'] ?? 0);
    $board_id = wp_get_post_parent_id($to_schedule_id);
    $dissolved_by_host = false;
    $actor_team_cancel = 0;
    $chat_room_id = null;
    $target_schedule_id = 0;

    if ($action === 'approve') {
        if (function_exists('aidunite_match_request_guard_before_recipient_accept')) {
            $guard = aidunite_match_request_guard_before_recipient_accept($match_id);
            if ($guard !== null) {
                return [
                    'ok' => false,
                    'http_status' => (int) ($guard['http_status'] ?? 400),
                    'error' => [
                        'type' => 'response',
                        'code' => (string) ($guard['code'] ?? ''),
                        'message' => (string) ($guard['message'] ?? ''),
                    ],
                ];
            }
        }
        aidunite_update_match_request_status_meta($match_id, 'accepted', $post_status);
        $new_status = '承認済';
        $board_status = 'accepted';
    } elseif ($action === 'reject') {
        aidunite_update_match_request_status_meta($match_id, 'rejected', $post_status);
        $new_status = '拒否済';
        $board_status = 'open';
    } elseif ($action === 'cancel') {
        $cancel_scope = function_exists('aidunite_parse_cancel_scope_from_request')
            ? aidunite_parse_cancel_scope_from_request()
            : 'pair';
        $match_game_cancel_rest = function_exists('aidunite_resolve_match_game_id_for_match_request')
            ? (int) aidunite_resolve_match_game_id_for_match_request($match_id)
            : $to_schedule_id;
        $actor_team_cancel = $has_from ? $from_team_int : ($has_to ? $to_team_int : 0);
        $is_anchor_host_rest = (
            $match_game_cancel_rest > 0
            && $match_game_cancel_rest !== 9999
            && $actor_team_cancel > 0
            && function_exists('aidunite_actor_is_anchor_host_for_match_request')
            && aidunite_actor_is_anchor_host_for_match_request($match_id, $actor_team_cancel)
        );
        if (
            $is_anchor_host_rest
            && $cancel_scope === 'dissolve'
            && function_exists('aidunite_host_dissolve_game_match_requests')
        ) {
            $dissolved_by_host = aidunite_host_dissolve_game_match_requests($match_id, $actor_team_cancel);
        }
        if (!$dissolved_by_host) {
            $was_est_rest = (string) ($canonical['established_at'] ?? '') !== '';
            $other_est_rest = ($match_game_cancel_rest > 0 && function_exists('aidunite_count_game_established_match_requests'))
                ? aidunite_count_game_established_match_requests($match_game_cancel_rest, $match_id)
                : 0;
            $skip_rb_rest = false;
            if ($was_est_rest && $other_est_rest > 0 && $cancel_scope !== 'dissolve') {
                if ((int) $actor_team_cancel === (int) $from_team_int || $is_anchor_host_rest) {
                    $skip_rb_rest = true;
                }
            }

            aidunite_update_match_request_status_meta($match_id, 'canceled', $post_status);
            if ($actor_team_cancel > 0) {
                aidunite_match_request_persist_canceled_by_team_id($match_id, $actor_team_cancel);
            }
            if ($was_est_rest && !$skip_rb_rest && function_exists('aidunite_rollback_established_from_schedules')) {
                aidunite_rollback_established_from_schedules($match_id);
            } elseif ($was_est_rest && $skip_rb_rest && function_exists('aidunite_restore_established_slot_only')) {
                aidunite_restore_established_slot_only($match_id);
            }
            if ($skip_rb_rest && function_exists('aidunite_handle_chat_on_match_request_canceled')) {
                aidunite_handle_chat_on_match_request_canceled($match_id);
            } elseif (!$skip_rb_rest && function_exists('aidunite_mark_match_chat_completed')) {
                aidunite_mark_match_chat_completed($match_id, 'canceled');
            }
        }
        $new_status = 'キャンセル済';
        $board_status = 'open';
    } elseif ($action === 'submit') {
        if (!$has_from) {
            return [
                'ok' => false,
                'http_status' => 403,
                'error' => [
                    'type' => 'response',
                    'message' => 'この操作は申請者のみが行えます。',
                ],
            ];
        }
        $cur_norm = (string) ($canonical['status'] ?? '');
        if (!in_array($cur_norm, ['accepted', 'established'], true)) {
            if (function_exists('aidunite_reset_match_request_lifecycle_meta_for_reapply')) {
                aidunite_reset_match_request_lifecycle_meta_for_reapply($match_id);
            }
            aidunite_update_match_request_status_meta($match_id, 'pending', $post_status);
        }
        $new_status = '申請中';
        $board_status = 'pending';
    } elseif ($action === 'apply') {
        if (function_exists('aidunite_reset_match_request_lifecycle_meta_for_reapply')) {
            aidunite_reset_match_request_lifecycle_meta_for_reapply($match_id);
        }
        aidunite_update_match_request_status_meta($match_id, 'pending', $post_status);
        $new_status = '申請中';
        $board_status = 'pending';
    } else {
        return [
            'ok' => false,
            'http_status' => 400,
            'error' => [
                'type' => 'response',
                'message' => '❌ 無効なアクションです。',
            ],
        ];
    }

    if ($action === 'approve') {
        $from_team_id = $from_team_int;
        $to_team_id = $to_team_int;
        $to_schedule_id_for_chat = $to_schedule_id;
        $my_schedule_id = (int) ($canonical['my_schedule_id'] ?? 0);

        if (function_exists('aidunite_create_or_extend_match_chat') && $from_team_id && $to_team_id) {
            $target_schedule_id = function_exists('aidunite_resolve_match_game_id_for_match_request')
                ? (int) aidunite_resolve_match_game_id_for_match_request($match_id)
                : 0;
            if ($target_schedule_id <= 0) {
                $target_schedule_id = $to_schedule_id_for_chat ?: $my_schedule_id;
            }

            $match_date = null;
            if ($target_schedule_id && function_exists('aidunite_schedule_read_normalized_date')) {
                $match_date = aidunite_schedule_read_normalized_date((int) $target_schedule_id);
            }
            if (!$match_date && $my_schedule_id && function_exists('aidunite_schedule_read_normalized_date')) {
                $match_date = aidunite_schedule_read_normalized_date($my_schedule_id);
            }

            $chat_room_id = aidunite_create_or_extend_match_chat(
                $match_id,
                $from_team_id,
                $to_team_id,
                $target_schedule_id,
                $match_date
            );

            if (!is_wp_error($chat_room_id)) {
                $chat_room_id = (int) $chat_room_id;
                aidunite_match_request_persist_chat_room_link($match_id, $chat_room_id, (int) $target_schedule_id);
                if (class_exists('AidUniteErrorHandler')) {
                    AidUniteErrorHandler::info('マッチチャットルームを作成/拡張（REST API経由）', [
                        'room_id' => $chat_room_id,
                        'schedule_id' => $target_schedule_id,
                        'match_request_id' => $match_id,
                    ]);
                } else {
                    error_log('✅ マッチチャットルームを作成/拡張しました（REST API経由）: Room ID = ' . $chat_room_id);
                }
            } elseif (class_exists('AidUniteErrorHandler')) {
                AidUniteErrorHandler::error('マッチチャットルームの作成/拡張に失敗（REST API経由）', [
                    'match_request_id' => $match_id,
                    'schedule_id' => $target_schedule_id,
                    'error' => $chat_room_id->get_error_message(),
                ]);
            } else {
                error_log('❌ マッチチャットルームの作成/拡張に失敗（REST API経由）: ' . $chat_room_id->get_error_message());
            }
        }

        if (function_exists('aidunite_apply_established_to_schedules')) {
            aidunite_apply_established_to_schedules($match_id);
        }
        if (function_exists('aidunite_after_match_established')) {
            aidunite_after_match_established($match_id);
        }
        if (function_exists('aidunite_get_game_chat_room_for_match_request')) {
            $canonical_room = aidunite_get_game_chat_room_for_match_request($match_id);
            if ($canonical_room && !empty($canonical_room->id)) {
                $canonical_id = (int) $canonical_room->id;
                $chat_room_id = $canonical_id;
                aidunite_match_request_persist_chat_room_link($match_id, $canonical_id, (int) $target_schedule_id);
            }
        }
        $new_status = '試合確定';
    }

    if ($action !== 'approve') {
        $game_sync_id = function_exists('aidunite_resolve_match_game_id_for_match_request')
            ? (int) aidunite_resolve_match_game_id_for_match_request($match_id)
            : $to_schedule_id;
        if ($game_sync_id > 0) {
            if (function_exists('aidunite_rebuild_recruit_schedule_participants_from_game_match_requests')) {
                $rebuild_opts = ($action === 'cancel') ? ['sync_chat' => false] : [];
                aidunite_rebuild_recruit_schedule_participants_from_game_match_requests($game_sync_id, $rebuild_opts);
            }
            if (function_exists('aidunite_sync_match_board_status_from_game')) {
                aidunite_sync_match_board_status_from_game($game_sync_id);
            }
        }
    }

    if ($action === 'cancel' && empty($dissolved_by_host) && function_exists('aidunite_notify_match_request_canceled')) {
        $actor_team_id = (int) $actor_team_cancel;
        if ($actor_team_id <= 0 && function_exists('aidunite_resolve_user_team_id_for_schedule_ops')) {
            $actor_team_id = (int) aidunite_resolve_user_team_id_for_schedule_ops($user_id, $to_schedule_id);
        }
        aidunite_notify_match_request_canceled($match_id, $actor_team_id);
    }

    do_action('aidunite_notify_match_status_change', $match_id, $action, $user_id, $new_status, '');

    if (function_exists('aidunite_match_flow_debug_log')) {
        $chat_dbg = (isset($chat_room_id) && $chat_room_id && !is_wp_error($chat_room_id)) ? (int) $chat_room_id : 0;
        aidunite_match_flow_debug_log('rest_update_match_status', [
            'match_id' => $match_id,
            'action' => $action,
            'new_status' => $new_status,
            'board_id' => (int) $board_id,
            'to_schedule' => $to_schedule_id,
            'chat_room_id' => $chat_dbg,
        ]);
    }

    $show_bot_chat_modal = (
        $action === 'approve'
        && function_exists('aidunite_onboarding_bot_should_show_chat_modal_after_approve')
        && aidunite_onboarding_bot_should_show_chat_modal_after_approve($match_id, $user_id)
    );

    if ($action === 'approve' && !$show_bot_chat_modal && isset($chat_room_id) && $chat_room_id && !is_wp_error($chat_room_id)) {
        return [
            'ok' => true,
            'http_status' => 200,
            'body' => [
                'success' => true,
                'new_status' => $new_status,
                'message' => $new_status . ' に更新しました',
                'chat_room_id' => (int) $chat_room_id,
                'redirect_url' => home_url('/chat?room_id=' . (int) $chat_room_id),
                'fallback_url' => home_url('/communication'),
            ],
        ];
    }

    if ($action === 'approve') {
        $approve_payload = [
            'success' => true,
            'new_status' => $new_status,
            'message' => $new_status . ' に更新しました',
        ];
        if ($show_bot_chat_modal) {
            $approve_payload['show_onboarding_bot_chat_modal'] = true;
            if (function_exists('aidunite_onboarding_bot_get_chat_url_for_request')) {
                $bot_chat = aidunite_onboarding_bot_get_chat_url_for_request($match_id);
                if ($bot_chat !== '') {
                    $approve_payload['chat_url'] = $bot_chat;
                }
            }
        } else {
            $approve_payload['fallback_url'] = home_url('/communication');
        }
        return [
            'ok' => true,
            'http_status' => 200,
            'body' => $approve_payload,
        ];
    }

    return [
        'ok' => true,
        'http_status' => 200,
        'body' => [
            'success' => true,
            'new_status' => $new_status,
            'message' => $new_status . ' に更新しました',
        ],
    ];
}
