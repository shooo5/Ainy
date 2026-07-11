<?php
/**
 * Match state synchronization helpers.
 * - Apply established request values to schedule meta
 * - Roll back schedule meta on cancellation
 * - Shared viewer-place conversion
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('aidunite_get_request_schedule_ids')) {
    function aidunite_get_request_schedule_ids($request_id) {
        $request_id = (int) $request_id;
        $mr = function_exists('aidunite_match_request_get_canonical_meta')
            ? aidunite_match_request_get_canonical_meta($request_id)
            : [];
        $to_schedule_id = (int) ($mr['to_schedule_id'] ?? 0);
        $my_schedule_id = (int) ($mr['my_schedule_id'] ?? 0);
        if (!$my_schedule_id) {
            $my_schedule_id = (int) ($mr['from_schedule_id'] ?? 0);
        }

        return [$to_schedule_id, $my_schedule_id];
    }
}

if (!function_exists('aidunite_resolve_place_for_viewer')) {
    function aidunite_resolve_place_for_viewer($selected_place, $from_team_id, $to_team_id, $viewer_team_id = 0) {
        $place = (string) $selected_place;
        if ($place === 'both') {
            $place = 'either';
        }
        if (!in_array($place, ['home', 'away', 'either'], true)) {
            return $place;
        }

        $from_team_id = (int) $from_team_id;
        $to_team_id = (int) $to_team_id;
        $viewer_team_id = (int) $viewer_team_id;
        if (!$viewer_team_id) {
            return $place;
        }

        // selected_place is stored from requester(from_team/my_schedule) perspective.
        if ($to_team_id && $viewer_team_id === $to_team_id) {
            if ($place === 'home') {
                return 'away';
            }
            if ($place === 'away') {
                return 'home';
            }
        }

        return $place;
    }
}

if (!function_exists('_aidunite_resolve_selected_place_for_established')) {
    /**
     * 承認時に selected_place が空/either でも、主催（to_schedule）を home 固定にできるよう
     * 申請者視点の開催形式（home|away）へ確定する。
     */
    function _aidunite_resolve_selected_place_for_established($selected_place, $to_schedule_id, $my_schedule_id) {
        $sp = (string) $selected_place;
        if ($sp === 'both') {
            $sp = 'either';
        }
        if (in_array($sp, ['home', 'away'], true)) {
            return $sp;
        }

        $to_lock = function_exists('aidunite_get_schedule_place_lock') ? aidunite_get_schedule_place_lock((int) $to_schedule_id) : '';
        if ($to_lock === 'home') {
            return 'away';
        }
        if ($to_lock === 'away') {
            return 'home';
        }

        $to_place_raw = function_exists('aidunite_schedule_read_place_raw')
            ? aidunite_schedule_read_place_raw((int) $to_schedule_id)
            : '';
        $my_place_raw = function_exists('aidunite_schedule_read_place_raw')
            ? aidunite_schedule_read_place_raw((int) $my_schedule_id)
            : '';
        $to_place = function_exists('aidunite_normalize_place_for_lock')
            ? aidunite_normalize_place_for_lock($to_place_raw)
            : (string) $to_place_raw;
        $my_place = function_exists('aidunite_normalize_place_for_lock')
            ? aidunite_normalize_place_for_lock($my_place_raw)
            : (string) $my_place_raw;

        if ($to_place === 'home') {
            return 'away';
        }
        if ($to_place === 'away') {
            return 'home';
        }
        if ($my_place === 'home' && $to_place === 'home') {
            return 'away';
        }
        if ($my_place === 'away' && $to_place === 'away') {
            return 'home';
        }
        // both/either 同士など曖昧なときは、主催 to_schedule を home に固定する。
        return 'away';
    }
}

if (!function_exists('aidunite_apply_established_to_schedules')) {
    function aidunite_apply_established_to_schedules($request_id) {
        $request_id = (int) $request_id;
        if ($request_id <= 0) {
            return;
        }

        [$to_schedule_id, $my_schedule_id] = aidunite_get_request_schedule_ids($request_id);
        // ゲスト招待のプレースホルダ（9999）は実スケジュールではないため、成立処理の対象から外す
        if ($to_schedule_id === 9999) {
            $to_schedule_id = 0;
        }

        aidunite_update_match_request_status_meta($request_id, 'established');

        if (function_exists('aidunite_match_flow_debug_log')) {
            aidunite_match_flow_debug_log('apply_established', [
                'request_id'       => $request_id,
                'to_schedule_id'   => $to_schedule_id,
                'my_schedule_id'   => $my_schedule_id,
            ]);
        }

        if ($my_schedule_id && function_exists('aidunite_schedule_finalize_applicant_on_established')) {
            aidunite_schedule_finalize_applicant_on_established($my_schedule_id);
        } elseif ($my_schedule_id && function_exists('aidunite_schedule_persist_intent_flags')) {
            aidunite_schedule_persist_intent_flags($my_schedule_id, 'confirmed', false);
            $my_bundle_est = function_exists('aidunite_schedule_get_display_bundle')
                ? aidunite_schedule_get_display_bundle((int) $my_schedule_id)
                : [];
            $my_date = (string) ($my_bundle_est['date'] ?? (function_exists('aidunite_schedule_read_normalized_date')
                ? aidunite_schedule_read_normalized_date((int) $my_schedule_id)
                : ''));
            wp_update_post([
                'ID'         => $my_schedule_id,
                'post_title' => $my_date ? ($my_date . ' 練習試合') : '練習試合',
            ]);
        }

        $mr_est = function_exists('aidunite_match_request_get_canonical_meta')
            ? aidunite_match_request_get_canonical_meta($request_id)
            : [];
        $selected_start = (string) ($mr_est['selected_start_time'] ?? '');
        $selected_end = (string) ($mr_est['selected_end_time'] ?? '');
        $selected_gender = (string) ($mr_est['selected_gender'] ?? '');
        $selected_place = (string) ($mr_est['selected_place'] ?? '');
        if ($selected_place === 'both') {
            $selected_place = 'either';
        }

        // selected_* が欠落していても承認時は確定値へ寄せる（主催固定）。
        if (($selected_start === '' || $selected_end === '') && $to_schedule_id && $my_schedule_id) {
            $to_bundle_est = function_exists('aidunite_schedule_get_display_bundle')
                ? aidunite_schedule_get_display_bundle((int) $to_schedule_id)
                : [];
            $my_bundle_time = function_exists('aidunite_schedule_get_display_bundle')
                ? aidunite_schedule_get_display_bundle((int) $my_schedule_id)
                : [];
            $to_api = function_exists('aidunite_schedule_get_api_display_fields')
                ? aidunite_schedule_get_api_display_fields((int) $to_schedule_id)
                : [];
            $my_api = function_exists('aidunite_schedule_get_api_display_fields')
                ? aidunite_schedule_get_api_display_fields((int) $my_schedule_id)
                : [];
            $to_start = (string) ($to_bundle_est['start_time'] ?? $to_api['start_time'] ?? '');
            $to_end = (string) ($to_bundle_est['end_time'] ?? $to_api['end_time'] ?? '');
            $my_start = (string) ($my_bundle_time['start_time'] ?? $my_api['start_time'] ?? '');
            $my_end = (string) ($my_bundle_time['end_time'] ?? $my_api['end_time'] ?? '');
            if ($to_start !== '' && $to_end !== '' && $my_start !== '' && $my_end !== '') {
                $start = max($to_start, $my_start);
                $end = min($to_end, $my_end);
                if ($start < $end) {
                    if ($selected_start === '') {
                        $selected_start = $start;
                    }
                    if ($selected_end === '') {
                        $selected_end = $end;
                    }
                    if (function_exists('aidunite_match_request_persist_established_selection')) {
                        aidunite_match_request_persist_established_selection($request_id, [
                            'selected_start_time' => $selected_start,
                            'selected_end_time' => $selected_end,
                        ]);
                    }
                }
            }
        }
        if ($to_schedule_id && $my_schedule_id) {
            $selected_place = _aidunite_resolve_selected_place_for_established($selected_place, $to_schedule_id, $my_schedule_id);
            if (function_exists('aidunite_match_request_persist_established_selection')) {
                aidunite_match_request_persist_established_selection($request_id, [
                    'selected_place' => $selected_place,
                ]);
            }
        }

        $from_team_id = (int) get_post_meta($request_id, 'from_team_id', true);
        $to_team_id = (int) get_post_meta($request_id, 'to_team_id', true);

        foreach ([$to_schedule_id, $my_schedule_id] as $sid) {
            if (!$sid) {
                continue;
            }

            if (function_exists('aidunite_schedule_backup_pre_established_once')) {
                aidunite_schedule_backup_pre_established_once($sid);
            }

            $place_for_schedule = '';
            if (in_array($selected_place, ['home', 'away', 'either'], true)) {
                $place_for_schedule = aidunite_resolve_place_for_viewer(
                    $selected_place,
                    $from_team_id,
                    $to_team_id,
                    (function_exists('aidunite_schedule_read_team_id')
                        ? aidunite_schedule_read_team_id((int) $sid)
                        : (int) get_post_meta($sid, 'team_id', true))
                );
            }

            if (function_exists('aidunite_schedule_apply_established_selection')) {
                aidunite_schedule_apply_established_selection($sid, [
                    'selected_start' => $selected_start,
                    'selected_end' => $selected_end,
                    'selected_place' => $selected_place,
                    'selected_gender' => $selected_gender,
                    'apply_gender' => true,
                    'my_schedule_id' => $my_schedule_id,
                    'place_for_schedule' => $place_for_schedule,
                ]);
            }
        }

        if ($to_schedule_id && $my_schedule_id) {
            $opponent_gender = function_exists('aidunite_get_schedule_gender') ? aidunite_get_schedule_gender($my_schedule_id) : '';
            $to_male_now = function_exists('aidunite_get_schedule_male_slots') ? aidunite_get_schedule_male_slots($to_schedule_id) : 0;
            $decrement_male = ($opponent_gender === 'male') || ($opponent_gender === 'both' && $to_male_now >= 1);
            if (function_exists('aidunite_match_request_persist_established_selection')) {
                aidunite_match_request_persist_established_selection($request_id, [
                    'established_gender_slot' => $decrement_male ? 'male' : 'female',
                ]);
            }

            $my_place = function_exists('aidunite_schedule_read_place_raw')
                ? aidunite_schedule_read_place_raw((int) $my_schedule_id)
                : '';
            $to_place = function_exists('aidunite_schedule_read_place_raw')
                ? aidunite_schedule_read_place_raw((int) $to_schedule_id)
                : '';

            foreach ([$to_schedule_id, $my_schedule_id] as $sid) {
                if (!$sid) {
                    continue;
                }
                $lock = function_exists('aidunite_get_schedule_place_lock') ? aidunite_get_schedule_place_lock($sid) : '';
                if ($lock === '' && function_exists('aidunite_resolve_place_lock')) {
                    $self_place = ($sid === (int) $my_schedule_id) ? $my_place : $to_place;
                    $other_place = ($sid === (int) $my_schedule_id) ? $to_place : $my_place;
                    update_post_meta($sid, 'place_lock', aidunite_resolve_place_lock($self_place, $other_place));
                }
            }
        }

        $match_game_id = 0;
        if (function_exists('aidunite_assign_match_game_id_on_established')) {
            $match_game_id = (int) aidunite_assign_match_game_id_on_established($request_id);
        }

        // 性別枠の消費は anchor schedule（match_game_id）のみ
        if ($match_game_id > 0 && $to_schedule_id && $my_schedule_id) {
            if ($decrement_male && function_exists('aidunite_get_schedule_male_slots')) {
                $cur = aidunite_get_schedule_male_slots($match_game_id);
                update_post_meta($match_game_id, 'male_slots', max(0, $cur - 1));
            } else {
                $cur = function_exists('aidunite_get_schedule_female_slots')
                    ? aidunite_get_schedule_female_slots($match_game_id)
                    : 0;
                update_post_meta($match_game_id, 'female_slots', max(0, $cur - 1));
            }
        }

        // anchor: 性別枠が残っている間は recruit のまま
        $anchor_sid = $match_game_id > 0 ? $match_game_id : (int) $to_schedule_id;
        if ($anchor_sid > 0) {
            $host_slots_empty = true;
            if (function_exists('aidunite_get_remaining_gender_slots')) {
                $rem = aidunite_get_remaining_gender_slots($anchor_sid);
                $canon = function_exists('aidunite_market_recruitment_gender_canonical')
                    ? aidunite_market_recruitment_gender_canonical($anchor_sid)
                    : '';
                if ($canon === 'male') {
                    $host_slots_empty = ((int) ($rem['male'] ?? 0)) < 1;
                } elseif ($canon === 'female') {
                    $host_slots_empty = ((int) ($rem['female'] ?? 0)) < 1;
                } else {
                    $host_slots_empty = ((int) ($rem['male'] ?? 0)) < 1 && ((int) ($rem['female'] ?? 0)) < 1;
                }
            }
            if (function_exists('aidunite_schedule_persist_intent_flags')) {
                if ($host_slots_empty) {
                    aidunite_schedule_persist_intent_flags($anchor_sid, 'confirmed', false);
                } else {
                    aidunite_schedule_persist_intent_flags($anchor_sid, 'recruit', true);
                }
            } elseif ($host_slots_empty) {
                update_post_meta($anchor_sid, 'intent', 'confirmed');
                update_post_meta($anchor_sid, 'is_match_requested', '0');
                update_post_meta($anchor_sid, 'matching', '0');
            } else {
                update_post_meta($anchor_sid, 'intent', 'recruit');
                update_post_meta($anchor_sid, 'matching', '1');
                update_post_meta($anchor_sid, 'is_match_requested', '1');
            }
        }

        // 申請先の募集 schedule（anchor と別 ID の away 募集など）: 成立後は募集を閉じる
        if ($to_schedule_id > 0 && (int) $to_schedule_id !== (int) $anchor_sid
            && function_exists('aidunite_schedule_read_intent')
            && aidunite_schedule_read_intent($to_schedule_id) === 'recruit'
            && function_exists('aidunite_count_established_for_schedule')
            && aidunite_count_established_for_schedule($to_schedule_id) > 0) {
            $to_canon = function_exists('aidunite_market_recruitment_gender_canonical')
                ? aidunite_market_recruitment_gender_canonical($to_schedule_id)
                : '';
            if (in_array($to_canon, ['male', 'female'], true)) {
                if (function_exists('aidunite_schedule_persist_intent_flags')) {
                    aidunite_schedule_persist_intent_flags($to_schedule_id, 'confirmed', false);
                } else {
                    update_post_meta($to_schedule_id, 'intent', 'confirmed');
                    update_post_meta($to_schedule_id, 'matching', '0');
                    update_post_meta($to_schedule_id, 'is_match_requested', '0');
                }
            }
        }

        do_action('aidunite_match_established', $request_id);
    }
}

if (!function_exists('aidunite_cancel_superseded_pending_for_same_applicant_recruit')) {
    /**
     * 同一チームが同一募集（to_schedule_id）へ複数の match_request を出しているとき、
     * いずれか1件が成立（established）したら残りの pending/accepted（未確定）を自動キャンセルする。
     * 枠・ロールバックは触らない（未成立のため）。チャット完了化もしない。
     *
     * @param int $winner_request_id いま成立処理が完了した MR の投稿ID
     * @return int[] キャンセルした MR の投稿ID一覧
     */
    function aidunite_cancel_superseded_pending_for_same_applicant_recruit($winner_request_id) {
        $winner_request_id = (int) $winner_request_id;
        if ($winner_request_id <= 0) {
            return [];
        }
        $to_id = (int) get_post_meta($winner_request_id, 'to_schedule_id', true);
        $from_tid = (int) get_post_meta($winner_request_id, 'from_team_id', true);
        if ($to_id <= 0 || $to_id === 9999 || $from_tid <= 0) {
            return [];
        }

        $dupes = get_posts([
            'post_type'      => 'match_request',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'post__not_in'   => [$winner_request_id],
            'meta_query'     => [
                'relation' => 'AND',
                ['key' => 'to_schedule_id', 'value' => (string) $to_id, 'compare' => '='],
                ['key' => 'from_team_id', 'value' => (string) $from_tid, 'compare' => '='],
            ],
        ]);

        $canceled_ids = [];
        foreach ($dupes as $p) {
            $raw = (string) get_post_meta($p->ID, 'status', true);
            $norm = function_exists('aidunite_normalize_match_request_status')
                ? aidunite_normalize_match_request_status($raw, isset($p->post_status) ? (string) $p->post_status : '')
                : strtolower($raw);
            if (in_array($norm, ['established', 'rejected', 'canceled'], true)) {
                continue;
            }
            if ($norm === 'not_applied') {
                continue;
            }

            aidunite_update_match_request_status_meta((int) $p->ID, 'canceled');
            if (function_exists('aidunite_match_request_persist_superseded_cancel')) {
                aidunite_match_request_persist_superseded_cancel(
                    (int) $p->ID,
                    $winner_request_id,
                    'superseded_established',
                    '別の申請が試合確定したため、この申請は自動キャンセルされました。'
                );
            } elseif (function_exists('aidunite_update_match_request_cancel_reason_meta')) {
                update_post_meta($p->ID, 'canceled_at', current_time('mysql'));
                delete_post_meta($p->ID, 'canceled_by_team_id');
                aidunite_update_match_request_cancel_reason_meta(
                    (int) $p->ID,
                    'superseded_established',
                    '別の申請が試合確定したため、この申請は自動キャンセルされました。'
                );
                update_post_meta($p->ID, 'aidunite_superseded_by_request_id', $winner_request_id);
            }

            if (function_exists('aidunite_match_flow_debug_log')) {
                aidunite_match_flow_debug_log('supersede_duplicate_mr', [
                    'canceled_request_id' => (int) $p->ID,
                    'winner_request_id'   => $winner_request_id,
                    'to_schedule_id'      => $to_id,
                    'from_team_id'        => $from_tid,
                ]);
            }
            $canceled_ids[] = (int) $p->ID;
        }

        return $canceled_ids;
    }
}

if (!function_exists('aidunite_is_strict_mirror_match_request')) {
    /**
     * 相互申請の「完全逆方向」MR かどうか（日程・時間の推論はしない）。
     */
    function aidunite_is_strict_mirror_match_request($winner_request_id, $candidate_request_id) {
        $winner_request_id = (int) $winner_request_id;
        $candidate_request_id = (int) $candidate_request_id;
        if ($winner_request_id <= 0 || $candidate_request_id <= 0 || $winner_request_id === $candidate_request_id) {
            return false;
        }

        $w_to = (int) get_post_meta($winner_request_id, 'to_schedule_id', true);
        $w_my = (int) get_post_meta($winner_request_id, 'my_schedule_id', true);
        if ($w_my <= 0) {
            $w_my = (int) get_post_meta($winner_request_id, 'from_schedule_id', true);
        }
        $c_to = (int) get_post_meta($candidate_request_id, 'to_schedule_id', true);
        $c_my = (int) get_post_meta($candidate_request_id, 'my_schedule_id', true);
        if ($c_my <= 0) {
            $c_my = (int) get_post_meta($candidate_request_id, 'from_schedule_id', true);
        }

        if ($w_to === 9999 || $c_to === 9999) {
            return false;
        }

        return ($w_to > 0 && $w_my > 0 && $c_to > 0 && $c_my > 0 && $c_to === $w_my && $c_my === $w_to);
    }
}

if (!function_exists('aidunite_cancel_mirror_pending_for_established')) {
    /**
     * 相互申請で片方が established になった直後、逆方向の pending/accepted を自動キャンセルする。
     * 成立処理の補助整理であり、失敗しても成立処理自体はロールバックしない。
     *
     * @param int $winner_request_id
     * @return int[] キャンセルした MR の投稿ID一覧
     */
    function aidunite_cancel_mirror_pending_for_established($winner_request_id) {
        $winner_request_id = (int) $winner_request_id;
        if ($winner_request_id <= 0) {
            return [];
        }

        $winner_from_team_id = (int) get_post_meta($winner_request_id, 'from_team_id', true);
        $winner_to_schedule_id = (int) get_post_meta($winner_request_id, 'to_schedule_id', true);
        if ($winner_to_schedule_id === 9999) {
            return [];
        }
        $winner_to_team_id = $winner_to_schedule_id > 0
            ? (function_exists('aidunite_schedule_read_team_id')
                ? aidunite_schedule_read_team_id($winner_to_schedule_id)
                : (int) get_post_meta($winner_to_schedule_id, 'team_id', true))
            : 0;
        if ($winner_to_team_id <= 0) {
            $winner_to_team_id = (int) get_post_meta($winner_request_id, 'to_team_id', true);
        }
        if ($winner_from_team_id <= 0 || $winner_to_team_id <= 0) {
            return [];
        }

        $candidates = get_posts([
            'post_type'      => 'match_request',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'post__not_in'   => [$winner_request_id],
            'meta_query'     => [
                'relation' => 'AND',
                ['key' => 'from_team_id', 'value' => (string) $winner_to_team_id, 'compare' => '='],
            ],
        ]);

        $canceled_ids = [];
        foreach ($candidates as $p) {
            try {
                $rid = (int) $p->ID;
                $raw = (string) get_post_meta($rid, 'status', true);
                $norm = function_exists('aidunite_normalize_match_request_status')
                    ? aidunite_normalize_match_request_status($raw, isset($p->post_status) ? (string) $p->post_status : '')
                    : strtolower($raw);
                if (!in_array($norm, ['pending', 'accepted'], true)) {
                    continue;
                }

                $candidate_to_team_id = 0;
                $candidate_to_schedule_id = (int) get_post_meta($rid, 'to_schedule_id', true);
                if ($candidate_to_schedule_id > 0) {
                    $candidate_to_team_id = function_exists('aidunite_schedule_read_team_id')
                        ? aidunite_schedule_read_team_id($candidate_to_schedule_id)
                        : (int) get_post_meta($candidate_to_schedule_id, 'team_id', true);
                }
                if ($candidate_to_team_id <= 0) {
                    $candidate_to_team_id = (int) get_post_meta($rid, 'to_team_id', true);
                }
                if ($candidate_to_team_id !== $winner_from_team_id) {
                    continue;
                }

                if (!aidunite_is_strict_mirror_match_request($winner_request_id, $rid)) {
                    continue;
                }

                aidunite_update_match_request_status_meta($rid, 'canceled');
                update_post_meta($rid, 'canceled_at', current_time('mysql'));
                delete_post_meta($rid, 'canceled_by_team_id');
                update_post_meta($rid, 'canceled_reason', 'mirror_established');
                update_post_meta($rid, 'canceled_by_system', '1');
                update_post_meta($rid, 'superseded_by_request_id', $winner_request_id);
                if (function_exists('aidunite_update_match_request_cancel_reason_meta')) {
                    aidunite_update_match_request_cancel_reason_meta(
                        $rid,
                        'mirror_established',
                        '別方向の申請が成立したため、この申請は自動キャンセルされました。'
                    );
                } else {
                    update_post_meta($rid, 'aidunite_cancel_reason', 'mirror_established');
                    update_post_meta($rid, 'cancel_reason', '別方向の申請が成立したため、この申請は自動キャンセルされました。');
                }

                error_log('🪞 mirror MR auto-canceled: request_id=' . $rid . ' winner=' . $winner_request_id . ' reason=mirror_established');
                if (function_exists('aidunite_match_flow_debug_log')) {
                    aidunite_match_flow_debug_log('mirror_mr_auto_canceled', [
                        'canceled_request_id' => $rid,
                        'winner_request_id'   => $winner_request_id,
                        'reason'              => 'mirror_established',
                    ]);
                }
                $canceled_ids[] = $rid;
            } catch (Throwable $e) {
                error_log('❌ mirror MR auto-cancel error: winner=' . $winner_request_id . ' candidate=' . (int) $p->ID . ' msg=' . $e->getMessage());
            }
        }

        return $canceled_ids;
    }
}

if (!function_exists('aidunite_clear_match_request_reconfirm_meta')) {
    function aidunite_clear_match_request_reconfirm_meta($request_id) {
        $request_id = (int) $request_id;
        if ($request_id <= 0) return;
        $keys = [
            'requires_reconfirm',
            'reconfirm_reason',
            'reconfirm_detected_at',
            'superseded_by_request_id',
            'reconfirm_before_schedule_place',
            'reconfirm_before_schedule_gender',
            'reconfirm_before_male_slots',
            'reconfirm_before_female_slots',
            'reconfirm_before_place_lock',
            'proposal_pending_accept',
            'proposal_by_team_id',
            'proposal_created_at',
            'proposal_accepted_at',
        ];
        foreach ($keys as $k) {
            delete_post_meta($request_id, $k);
        }
    }
}

if (!function_exists('aidunite_match_request_sync_outcome_after_established_context')) {
    /**
     * 成立直後の同一募集・残存MRについて評価し、mr_outcome_code / requires_reconfirm を同期する（match-request.md 第6A節）。
     *
     * @param int $request_id       残存 match_request ID
     * @param int $winner_request_id 成立したMR ID（再確認メタの superseded 用）
     */
    function aidunite_match_request_sync_outcome_after_established_context($request_id, $winner_request_id) {
        $request_id = (int) $request_id;
        $winner_request_id = (int) $winner_request_id;
        if ($request_id <= 0) {
            return;
        }
        if (!function_exists('aidunite_match_apply_collect_validate_blocking_codes')) {
            $eval_file = get_template_directory() . '/functions/match/match-apply-evaluation.php';
            if (is_readable($eval_file)) {
                require_once $eval_file;
            }
        }
        if (!function_exists('aidunite_match_apply_collect_validate_blocking_codes')
            || !function_exists('aidunite_match_request_project_outcome_from_blocking_codes')) {
            return;
        }

        $to_schedule_id = (int) get_post_meta($request_id, 'to_schedule_id', true);
        $my_schedule_id = (int) get_post_meta($request_id, 'my_schedule_id', true);
        if ($my_schedule_id <= 0) {
            $my_schedule_id = (int) get_post_meta($request_id, 'from_schedule_id', true);
        }
        if ($to_schedule_id <= 0 || $my_schedule_id <= 0) {
            return;
        }

        $selected_place = (string) get_post_meta($request_id, 'selected_place', true);
        if ($selected_place === 'both') {
            $selected_place = 'either';
        }

        $collected = aidunite_match_apply_collect_validate_blocking_codes($to_schedule_id, $my_schedule_id, [
            'mode'                 => 'validate',
            'selected_place'       => $selected_place,
            'selected_gender'      => (string) get_post_meta($request_id, 'selected_gender', true),
            'selected_start_time'  => (string) get_post_meta($request_id, 'selected_start_time', true),
            'selected_end_time'    => (string) get_post_meta($request_id, 'selected_end_time', true),
        ]);

        $outcome = aidunite_match_request_project_outcome_from_blocking_codes($collected['blocking_codes']);
        if (function_exists('aidunite_match_request_persist_outcome_code')) {
            aidunite_match_request_persist_outcome_code($request_id, $outcome);
        }

        if ($outcome === 'keep_pending') {
            if (function_exists('aidunite_clear_match_request_reconfirm_meta')) {
                aidunite_clear_match_request_reconfirm_meta($request_id);
            }
            if (function_exists('aidunite_match_request_persist_outcome_code')) {
                aidunite_match_request_persist_outcome_code($request_id, '');
            }
            return;
        }

        $needs_reconfirm = in_array($outcome, ['reconfirm_required', 'proposal_possible'], true);
        if ($needs_reconfirm) {
            if (function_exists('aidunite_mark_match_request_reconfirm_required')) {
                aidunite_mark_match_request_reconfirm_required($request_id, $winner_request_id, 'schedule_condition_changed');
            }
            if (function_exists('send_match_request_reconfirm_required_notification')) {
                send_match_request_reconfirm_required_notification($request_id, $winner_request_id, 'schedule_condition_changed');
            }
        } elseif (function_exists('aidunite_clear_match_request_reconfirm_meta')) {
            aidunite_clear_match_request_reconfirm_meta($request_id);
        }
    }
}

if (!function_exists('aidunite_mark_match_request_reconfirm_required')) {
    function aidunite_mark_match_request_reconfirm_required($request_id, $winner_request_id, $reason = 'schedule_condition_changed') {
        $request_id = (int) $request_id;
        $winner_request_id = (int) $winner_request_id;
        if ($request_id <= 0 || $winner_request_id <= 0) {
            return;
        }
        $mr = function_exists('aidunite_match_request_get_canonical_meta')
            ? aidunite_match_request_get_canonical_meta($request_id)
            : [];
        $to_schedule_id = (int) ($mr['to_schedule_id'] ?? 0);
        $before_place = '';
        $before_gender = '';
        if ($to_schedule_id > 0) {
            $before_place = function_exists('aidunite_schedule_read_place_raw')
                ? aidunite_schedule_read_place_raw($to_schedule_id)
                : '';
            $before_gender = function_exists('aidunite_schedule_read_gender_raw')
                ? aidunite_schedule_read_gender_raw($to_schedule_id)
                : '';
        }
        $before = [
            'before_place' => $before_place,
            'before_gender' => $before_gender,
        ];
        if ($to_schedule_id > 0) {
            $before['male'] = function_exists('aidunite_get_schedule_male_slots')
                ? (int) aidunite_get_schedule_male_slots($to_schedule_id)
                : 0;
            $before['female'] = function_exists('aidunite_get_schedule_female_slots')
                ? (int) aidunite_get_schedule_female_slots($to_schedule_id)
                : 0;
            $before['place_lock'] = function_exists('aidunite_get_schedule_place_lock')
                ? (string) aidunite_get_schedule_place_lock($to_schedule_id)
                : '';
        }
        if (function_exists('aidunite_match_request_persist_reconfirm_required')) {
            aidunite_match_request_persist_reconfirm_required($request_id, $winner_request_id, $reason, $before);
        }
    }
}

if (!function_exists('aidunite_mark_reconfirm_required_requests_for_recruit_after_established')) {
    /**
     * 同一募集に残る pending/accepted を再評価し、条件不一致を requires_reconfirm にする。
     */
    function aidunite_mark_reconfirm_required_requests_for_recruit_after_established($winner_request_id) {
        $winner_request_id = (int) $winner_request_id;
        if ($winner_request_id <= 0) return;
        $to_schedule_id = (int) get_post_meta($winner_request_id, 'to_schedule_id', true);
        if ($to_schedule_id <= 0 || $to_schedule_id === 9999) return;

        $candidates = get_posts([
            'post_type'      => 'match_request',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'post__not_in'   => [$winner_request_id],
            'meta_query'     => [
                'relation' => 'AND',
                ['key' => 'to_schedule_id', 'value' => (string) $to_schedule_id, 'compare' => '='],
                ['key' => 'status', 'value' => ['pending', 'accepted', 'publish', '申請中', '承認済み'], 'compare' => 'IN'],
            ],
        ]);

        foreach ($candidates as $p) {
            $rid = (int) $p->ID;
            if (function_exists('aidunite_match_request_sync_outcome_after_established_context')) {
                aidunite_match_request_sync_outcome_after_established_context($rid, $winner_request_id);
            }
        }
    }
}

if (!function_exists('aidunite_after_match_established')) {
    /**
     * 試合確定（established）後の副作用をこの関数に集約する（通常承認・招待確定など全経路から呼ぶ）。
     * 成立処理本体のロールバックはしない（補助処理の失敗はログに留める）。
     *
     * @param int   $request_id
     * @param array $options guest_invite: ゲスト招待（to_schedule_id=9999 系）。participants 再構築はゲームMR集合に載らないためスキップ。
     * @return void
     */
    function aidunite_after_match_established($request_id, array $options = []) {
        $request_id = (int) $request_id;
        if ($request_id <= 0) {
            return;
        }

        $guest_invite = !empty($options['guest_invite']);
        $send_approval_notification = array_key_exists('send_approval_notification', $options)
            ? (bool) $options['send_approval_notification']
            : true;
        $increment_match_counts = array_key_exists('increment_match_counts', $options)
            ? (bool) $options['increment_match_counts']
            : true;

        try {
            $post = get_post($request_id);
            if (!$post || $post->post_type !== 'match_request') {
                error_log('❌ aidunite_after_match_established: invalid request post id=' . $request_id);
                return;
            }

            $raw_st = (string) get_post_meta($request_id, 'status', true);
            $norm_st = function_exists('aidunite_normalize_match_request_status')
                ? aidunite_normalize_match_request_status($raw_st, (string) $post->post_status)
                : strtolower($raw_st);
            if ($norm_st !== 'established') {
                error_log('❌ aidunite_after_match_established: skip, status is not established id=' . $request_id . ' norm=' . $norm_st);
                return;
            }

            if (function_exists('aidunite_match_request_ensure_link_team_meta')) {
                aidunite_match_request_ensure_link_team_meta($request_id);
            }

            $superseded_ids = [];
            if (function_exists('aidunite_cancel_superseded_pending_for_same_applicant_recruit')) {
                $superseded_ids = aidunite_cancel_superseded_pending_for_same_applicant_recruit($request_id);
                if (!is_array($superseded_ids)) {
                    $superseded_ids = [];
                }
            }

            $mirror_canceled_ids = [];
            if (function_exists('aidunite_cancel_mirror_pending_for_established')) {
                $mirror_canceled_ids = aidunite_cancel_mirror_pending_for_established($request_id);
            }

            if (function_exists('aidunite_mark_reconfirm_required_requests_for_recruit_after_established')) {
                aidunite_mark_reconfirm_required_requests_for_recruit_after_established($request_id);
            }

            [$w_to, $w_my] = aidunite_get_request_schedule_ids($request_id);
            if ($w_to === 9999) {
                $w_to = 0;
            }
            if ($w_my <= 0) {
                $w_my = (int) get_post_meta($request_id, 'from_schedule_id', true);
            }

            $match_game_id = function_exists('aidunite_resolve_match_game_id_for_match_request')
                ? (int) aidunite_resolve_match_game_id_for_match_request($request_id)
                : 0;

            if (!$guest_invite && $match_game_id > 0) {
                if (function_exists('aidunite_rebuild_recruit_schedule_participants_from_game_match_requests')) {
                    aidunite_rebuild_recruit_schedule_participants_from_game_match_requests($match_game_id, ['sync_chat' => false]);
                }
                if (function_exists('aidunite_sync_match_board_status_from_game')) {
                    aidunite_sync_match_board_status_from_game($match_game_id);
                }
                $room_id = (int) get_post_meta($request_id, 'chat_room_id', true);
                if (
                    $room_id <= 0
                    && function_exists('aidunite_get_active_chat_room_for_match_game')
                    && !(function_exists('aidunite_match_request_should_fork_new_chat_room')
                        && aidunite_match_request_should_fork_new_chat_room($request_id))
                ) {
                    $active_room = aidunite_get_active_chat_room_for_match_game($match_game_id);
                    if (
                        $active_room
                        && !empty($active_room->id)
                        && (string) ($active_room->status ?? '') === 'active'
                        && function_exists('aidunite_bind_match_game_chat_room')
                    ) {
                        aidunite_bind_match_game_chat_room($request_id, $match_game_id, (int) $active_room->id);
                    }
                }
                // established 集合に合わせて参加者を再同期（2本目以降の MR で add_team 失敗時も補完）
                if (function_exists('aidunite_sync_schedule_chat_room_members')) {
                    aidunite_sync_schedule_chat_room_members($match_game_id);
                }
            } elseif ($guest_invite && $w_my > 0 && function_exists('update_board_status_on_established')) {
                update_board_status_on_established($w_my);
            }

            if ($increment_match_counts && function_exists('increment_team_match_count')) {
                $from_tid = (int) get_post_meta($request_id, 'from_team_id', true);
                $to_tid = (int) get_post_meta($request_id, 'to_team_id', true);
                if ($from_tid > 0) {
                    increment_team_match_count($from_tid);
                }
                if ($to_tid > 0) {
                    increment_team_match_count($to_tid);
                }
            }

            foreach ($mirror_canceled_ids as $mid) {
                $mid = (int) $mid;
                if ($mid <= 0 || $mid === $request_id) {
                    continue;
                }
                if (function_exists('aidunite_mark_match_chat_completed')) {
                    aidunite_mark_match_chat_completed($mid, 'canceled');
                }
            }

            foreach ($superseded_ids as $sid) {
                $sid = (int) $sid;
                if ($sid <= 0 || $sid === $request_id) {
                    continue;
                }
                if (function_exists('send_match_request_superseded_notifications')) {
                    send_match_request_superseded_notifications($sid, $request_id);
                }
            }

            if ($send_approval_notification && function_exists('send_match_approval_notification')) {
                send_match_approval_notification($request_id);
            }

            foreach ($mirror_canceled_ids as $mid) {
                $mid = (int) $mid;
                if ($mid <= 0) {
                    continue;
                }
                if (function_exists('send_match_request_mirror_established_notification')) {
                    send_match_request_mirror_established_notification($mid, $request_id);
                }
            }

            do_action('aidunite_after_match_established', $request_id, $options);

            error_log('✅ aidunite_after_match_established done request_id=' . $request_id . ' superseded=' . count($superseded_ids) . ' mirror=' . count($mirror_canceled_ids));
        } catch (Throwable $e) {
            error_log('❌ aidunite_after_match_established error request_id=' . $request_id . ' msg=' . $e->getMessage());
        }
    }
}

if (!function_exists('aidunite_restore_schedule_from_pre_established_backup')) {
    /**
     * schedule の pre_established_* からフィールドを復元（§17.5.1 / §17.6 共通）
     *
     * @param int   $schedule_id
     * @param array $options set_recruit_matching: 参加側 my_schedule を recruit+matching に戻す
     * @return bool バックアップありで復元したら true
     */
    function aidunite_restore_schedule_from_pre_established_backup($schedule_id, array $options = []) {
        if (function_exists('aidunite_schedule_restore_from_pre_established_backup')) {
            return aidunite_schedule_restore_from_pre_established_backup($schedule_id, $options);
        }

        return false;
    }
}

if (!function_exists('aidunite_restore_anchor_schedule_on_game_dissolve')) {
    /**
     * §17.5: anchor schedule（match_game_id）のみ募集初期状態へ戻す
     *
     * @param int $match_game_id
     * @return void
     */
    function aidunite_restore_anchor_schedule_on_game_dissolve($match_game_id) {
        $match_game_id = (int) $match_game_id;
        if ($match_game_id <= 0) {
            return;
        }

        if (function_exists('aidunite_schedule_rollback_intent_after_established_cancel')) {
            aidunite_schedule_rollback_intent_after_established_cancel($match_game_id);
        }

        $had_backup = function_exists('aidunite_restore_schedule_from_pre_established_backup')
            ? aidunite_restore_schedule_from_pre_established_backup($match_game_id, ['set_recruit_matching' => false])
            : false;

        $anchor_bundle = function_exists('aidunite_schedule_get_display_bundle')
            ? aidunite_schedule_get_display_bundle($match_game_id)
            : [];
        $schedule_type = (string) ($anchor_bundle['schedule_type'] ?? '');
        $is_practice     = in_array($schedule_type, ['practice_match', 'joint_practice', '練習試合', '合同練習'], true)
            || (function_exists('mb_strpos') && mb_strpos($schedule_type, '練習試合') !== false)
            || (function_exists('mb_strpos') && mb_strpos($schedule_type, '合同練習') !== false);
        if ($is_practice) {
            update_post_meta($match_game_id, 'match_status', 'planned');
        }

        $anchor_intent = function_exists('aidunite_schedule_read_intent')
            ? aidunite_schedule_read_intent($match_game_id)
            : (string) get_post_meta($match_game_id, 'intent', true);
        if ($anchor_intent === 'recruit') {
            $slots_remain = true;
            if (function_exists('aidunite_get_remaining_gender_slots')) {
                $rem   = aidunite_get_remaining_gender_slots($match_game_id);
                $canon = function_exists('aidunite_market_recruitment_gender_canonical')
                    ? aidunite_market_recruitment_gender_canonical($match_game_id)
                    : '';
                if ($canon === 'male') {
                    $slots_remain = ((int) ($rem['male'] ?? 0)) > 0;
                } elseif ($canon === 'female') {
                    $slots_remain = ((int) ($rem['female'] ?? 0)) > 0;
                } else {
                    $slots_remain = ((int) ($rem['male'] ?? 0)) > 0 || ((int) ($rem['female'] ?? 0)) > 0;
                }
            }
            if ($slots_remain) {
                update_post_meta($match_game_id, 'matching', '1');
                update_post_meta($match_game_id, 'is_match_requested', '1');
            }
        }

        if (function_exists('aidunite_match_flow_debug_log')) {
            aidunite_match_flow_debug_log('game_dissolve_anchor_restore', [
                'match_game_id' => $match_game_id,
                'had_backup'    => $had_backup,
            ]);
        }
    }
}

if (!function_exists('aidunite_restore_all_participant_schedules_on_game_dissolve')) {
    /**
     * §17.5 手順7: 当該 game に established していた各参加チームの my_schedule を復元
     *
     * @param int       $match_game_id
     * @param WP_Post[] $established_posts established_at ありの MR（キャンセル前に収集）
     * @return void
     */
    function aidunite_restore_all_participant_schedules_on_game_dissolve($match_game_id, array $established_posts) {
        $match_game_id = (int) $match_game_id;
        if ($match_game_id <= 0 || $established_posts === []) {
            return;
        }

        $restored_schedule_ids = [];

        foreach ($established_posts as $p) {
            if (!$p instanceof WP_Post) {
                continue;
            }
            $rid = (int) $p->ID;
            if (get_post_meta($rid, 'established_at', true) === '') {
                continue;
            }

            [, $my_sid] = aidunite_get_request_schedule_ids($rid);
            $my_sid = (int) $my_sid;
            if ($my_sid <= 0 || $my_sid === $match_game_id) {
                continue;
            }
            if (isset($restored_schedule_ids[$my_sid])) {
                continue;
            }
            $restored_schedule_ids[$my_sid] = true;

            if (function_exists('aidunite_schedule_delete_match_apply_tentative_on_established_cancel')
                && aidunite_schedule_delete_match_apply_tentative_on_established_cancel($my_sid, $my_sid)) {
                if (function_exists('aidunite_match_flow_debug_log')) {
                    aidunite_match_flow_debug_log('game_dissolve_participant_delete_tentative', [
                        'match_game_id' => $match_game_id,
                        'request_id'    => $rid,
                        'schedule_id'   => $my_sid,
                    ]);
                }
                continue;
            }

            if (!function_exists('aidunite_restore_schedule_from_pre_established_backup')) {
                continue;
            }
            aidunite_restore_schedule_from_pre_established_backup($my_sid, ['set_recruit_matching' => true]);

            if (function_exists('aidunite_match_flow_debug_log')) {
                aidunite_match_flow_debug_log('game_dissolve_participant_restore', [
                    'match_game_id' => $match_game_id,
                    'request_id'    => $rid,
                    'schedule_id'   => $my_sid,
                ]);
            }
        }
    }
}

if (!function_exists('aidunite_rollback_match_game_on_host_dissolve')) {
    /**
     * §17.5 ゲーム全体リセットのスケジュール復元（D4: anchor + 全参加者 my_schedule）
     *
     * @param int       $match_game_id
     * @param WP_Post[] $established_posts
     * @return void
     */
    function aidunite_rollback_match_game_on_host_dissolve($match_game_id, array $established_posts) {
        $match_game_id = (int) $match_game_id;
        if ($match_game_id <= 0) {
            return;
        }

        if (function_exists('aidunite_restore_anchor_schedule_on_game_dissolve')) {
            aidunite_restore_anchor_schedule_on_game_dissolve($match_game_id);
        }
        if (function_exists('aidunite_restore_all_participant_schedules_on_game_dissolve')) {
            aidunite_restore_all_participant_schedules_on_game_dissolve($match_game_id, $established_posts);
        }

        if (function_exists('aidunite_match_flow_debug_log')) {
            aidunite_match_flow_debug_log('rollback_match_game_dissolve_done', [
                'match_game_id'      => $match_game_id,
                'established_mr_ids' => array_map(
                    static function ($post) {
                        return $post instanceof WP_Post ? (int) $post->ID : 0;
                    },
                    $established_posts
                ),
            ]);
        }
    }
}

if (!function_exists('aidunite_rollback_established_from_schedules')) {
    function aidunite_rollback_established_from_schedules($request_id) {
        $request_id = (int) $request_id;
        if ($request_id <= 0) {
            return;
        }

        [$to_schedule_id, $my_schedule_id] = aidunite_get_request_schedule_ids($request_id);
        $was_established = (get_post_meta($request_id, 'established_at', true) !== '');

        if (function_exists('aidunite_match_flow_debug_log')) {
            aidunite_match_flow_debug_log('rollback_established_start', [
                'request_id'        => $request_id,
                'was_established'   => $was_established,
                'to_schedule_id'    => $to_schedule_id,
                'my_schedule_id'    => $my_schedule_id,
            ]);
        }

        $had_backup_by_schedule = [];
        $deleted_apply_tentative = [];
        foreach ([$to_schedule_id, $my_schedule_id] as $sid) {
            if (!$sid) {
                continue;
            }

            if (function_exists('aidunite_schedule_delete_match_apply_tentative_on_established_cancel')
                && aidunite_schedule_delete_match_apply_tentative_on_established_cancel($sid, $my_schedule_id)) {
                $deleted_apply_tentative[(int) $sid] = true;
                continue;
            }

            $had_backup = (get_post_meta($sid, 'pre_established_saved', true) === '1');
            $had_backup_by_schedule[(int) $sid] = $had_backup;

            if (function_exists('aidunite_schedule_rollback_intent_after_established_cancel')) {
                aidunite_schedule_rollback_intent_after_established_cancel($sid);
            }

            $sid_bundle = function_exists('aidunite_schedule_get_display_bundle')
                ? aidunite_schedule_get_display_bundle((int) $sid)
                : [];
            $schedule_type = (string) ($sid_bundle['schedule_type'] ?? '');
            $is_practice_schedule_type = in_array($schedule_type, ['practice_match', 'joint_practice', '練習試合', '合同練習'], true)
                || (function_exists('mb_strpos') && mb_strpos($schedule_type, '練習試合') !== false)
                || (function_exists('mb_strpos') && mb_strpos($schedule_type, '合同練習') !== false);
            if ($is_practice_schedule_type) {
                // カレンダー表示を確実に募集状態へ戻す（確定バッジ残留を防止）
                update_post_meta($sid, 'match_status', 'planned');
            }

            if ($had_backup && function_exists('aidunite_schedule_restore_pre_established_fields')) {
                aidunite_schedule_restore_pre_established_fields($sid);
            } else {
                // 旧データ互換: バックアップがない成立キャンセルでも、確定表示メタを除去して募集表示へ戻す
                update_post_meta($sid, 'match_status', 'planned');
                delete_post_meta($sid, 'match_opponent_team_id');
                delete_post_meta($sid, 'match_opponent_name');
            }
        }

        if (!$was_established) {
            return;
        }

        $slot_used = get_post_meta($request_id, 'established_gender_slot', true);
        if ($slot_used !== 'male' && $slot_used !== 'female') {
            $opponent_gender = function_exists('aidunite_get_schedule_gender') ? aidunite_get_schedule_gender($my_schedule_id) : '';
            $slot_used = ($opponent_gender === 'female') ? 'female' : 'male';
        }
        $inc_male = ($slot_used === 'male');

        foreach ([$to_schedule_id, $my_schedule_id] as $sid) {
            if (!$sid || !empty($deleted_apply_tentative[(int) $sid])) {
                continue;
            }
            // If no backup exists (legacy data), keep old incremental fallback behavior.
            if (empty($had_backup_by_schedule[(int) $sid])) {
                if ($inc_male && function_exists('aidunite_get_schedule_male_slots')) {
                    $cur = aidunite_get_schedule_male_slots($sid);
                    update_post_meta($sid, 'male_slots', $cur + 1);
                } else {
                    $cur = function_exists('aidunite_get_schedule_female_slots') ? aidunite_get_schedule_female_slots($sid) : 0;
                    update_post_meta($sid, 'female_slots', $cur + 1);
                }
                if (function_exists('aidunite_count_established_for_schedule') && aidunite_count_established_for_schedule($sid) === 0) {
                    delete_post_meta($sid, 'place_lock');
                }
            }
        }
    }
}

if (!function_exists('aidunite_restore_established_slot_only')) {
    /**
     * 共有ゲームで他 established が残るため full rollback をスキップする場合に、
     * 当該 request が消費した性別枠のみを戻す。
     *
     * @param int $request_id
     * @return void
     */
    function aidunite_restore_established_slot_only($request_id) {
        $request_id = (int) $request_id;
        if ($request_id <= 0) {
            return;
        }

        [$to_schedule_id, $my_schedule_id] = aidunite_get_request_schedule_ids($request_id);
        $was_established = (get_post_meta($request_id, 'established_at', true) !== '');
        if (!$was_established) {
            return;
        }

        $slot_used = (string) get_post_meta($request_id, 'established_gender_slot', true);
        if ($slot_used !== 'male' && $slot_used !== 'female') {
            $opp_gender = function_exists('aidunite_get_schedule_gender') ? aidunite_get_schedule_gender((int) $my_schedule_id) : '';
            $slot_used = ($opp_gender === 'female') ? 'female' : 'male';
        }
        $inc_male = ($slot_used === 'male');

        $anchor_sid = function_exists('aidunite_resolve_match_game_id_for_match_request')
            ? (int) aidunite_resolve_match_game_id_for_match_request($request_id)
            : (int) $to_schedule_id;
        if ($anchor_sid > 0) {
            if ($inc_male && function_exists('aidunite_get_schedule_male_slots')) {
                $cur = (int) aidunite_get_schedule_male_slots($anchor_sid);
                update_post_meta($anchor_sid, 'male_slots', max(0, $cur + 1));
            } else {
                $cur = function_exists('aidunite_get_schedule_female_slots')
                    ? (int) aidunite_get_schedule_female_slots($anchor_sid)
                    : (int) get_post_meta($anchor_sid, 'female_slots', true);
                update_post_meta($anchor_sid, 'female_slots', max(0, $cur + 1));
            }
        }

        if (function_exists('aidunite_restore_participant_my_schedule_on_shared_cancel')) {
            aidunite_restore_participant_my_schedule_on_shared_cancel($request_id);
        }

        if (function_exists('aidunite_match_flow_debug_log')) {
            aidunite_match_flow_debug_log('rollback_slot_only', [
                'request_id'     => $request_id,
                'to_schedule_id' => (int) $to_schedule_id,
                'my_schedule_id' => (int) $my_schedule_id,
                'slot_used'      => $slot_used,
            ]);
        }
    }
}

if (!function_exists('aidunite_restore_participant_my_schedule_on_shared_cancel')) {
    /**
     * 共有ゲーム継続時の参加側キャンセル: 申請者 my_schedule のみ pre_established から復元する。
     * 募集 schedule（主催）は第17.6 に従い変更しない。
     *
     * @param int $request_id
     * @return void
     */
    function aidunite_restore_participant_my_schedule_on_shared_cancel($request_id) {
        $request_id = (int) $request_id;
        if ($request_id <= 0) {
            return;
        }

        [$to_schedule_id, $my_schedule_id] = aidunite_get_request_schedule_ids($request_id);
        $my_schedule_id = (int) $my_schedule_id;
        if ($my_schedule_id <= 0) {
            return;
        }

        $from_team_id = (int) get_post_meta($request_id, 'from_team_id', true);
        $my_team_id   = function_exists('aidunite_schedule_read_team_id')
            ? aidunite_schedule_read_team_id($my_schedule_id)
            : (int) get_post_meta($my_schedule_id, 'team_id', true);
        if ($from_team_id > 0 && $my_team_id > 0 && $from_team_id !== $my_team_id) {
            return;
        }

        if (function_exists('aidunite_schedule_delete_match_apply_tentative_on_established_cancel')
            && aidunite_schedule_delete_match_apply_tentative_on_established_cancel($my_schedule_id, $my_schedule_id)) {
            if (function_exists('aidunite_match_flow_debug_log')) {
                aidunite_match_flow_debug_log('restore_participant_my_schedule_deleted_tentative', [
                    'request_id'     => $request_id,
                    'my_schedule_id' => $my_schedule_id,
                ]);
            }
            return;
        }

        $had_backup = function_exists('aidunite_restore_schedule_from_pre_established_backup')
            ? aidunite_restore_schedule_from_pre_established_backup($my_schedule_id, ['set_recruit_matching' => true])
            : false;

        if (function_exists('aidunite_match_flow_debug_log')) {
            aidunite_match_flow_debug_log('restore_participant_my_schedule', [
                'request_id'     => $request_id,
                'my_schedule_id' => $my_schedule_id,
                'to_schedule_id' => (int) $to_schedule_id,
                'had_backup'     => $had_backup,
            ]);
        }
    }
}

if (!function_exists('aidunite_recover_specific_schedule_for_reapply_once')) {
    /**
     * 実機確認用: 特定 schedule を募集状態に戻す（バージョン管理で再実行可能）
     * - post_id=5047
     * - post_id=5059
     */
    function aidunite_recover_specific_schedule_for_reapply_once() {
        $target_schedule_ids = [5047, 5059];
        $target_version = 2;
        $done_option = 'aidunite_recover_schedules_for_reapply_version';
        $done_version = (int) get_option($done_option, 0);
        if ($done_version >= $target_version) {
            return;
        }

        foreach ($target_schedule_ids as $target_schedule_id) {
            $target_schedule_id = (int) $target_schedule_id;
            if ($target_schedule_id <= 0) {
                continue;
            }
            $post = get_post($target_schedule_id);
            if (!$post || $post->post_type !== 'schedule') {
                continue;
            }

            update_post_meta($target_schedule_id, 'intent', 'recruit');
            update_post_meta($target_schedule_id, 'match_status', 'planned');
            delete_post_meta($target_schedule_id, 'match_opponent_team_id');
            delete_post_meta($target_schedule_id, 'match_opponent_name');
            delete_post_meta($target_schedule_id, 'place_lock');

            // 念のため、成立時バックアップ系もクリア（再ロールバック干渉防止）
            delete_post_meta($target_schedule_id, 'pre_established_start_time');
            delete_post_meta($target_schedule_id, 'pre_established_end_time');
            delete_post_meta($target_schedule_id, 'pre_established_place');
            delete_post_meta($target_schedule_id, 'pre_established_place_option');
            delete_post_meta($target_schedule_id, 'pre_established_gender');
            delete_post_meta($target_schedule_id, 'pre_established_male_slots');
            delete_post_meta($target_schedule_id, 'pre_established_female_slots');
            delete_post_meta($target_schedule_id, 'pre_established_place_lock');
            delete_post_meta($target_schedule_id, 'pre_established_match_status');
            delete_post_meta($target_schedule_id, 'pre_established_match_opponent_team_id');
            delete_post_meta($target_schedule_id, 'pre_established_match_opponent_name');
            delete_post_meta($target_schedule_id, 'pre_established_saved');
        }

        update_option($done_option, $target_version, false);
    }
}
add_action('init', 'aidunite_recover_specific_schedule_for_reapply_once', 30);
