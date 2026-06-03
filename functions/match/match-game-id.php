<?php
/**
 * match_game_id（試合の箱 = 最初にホーム確定した anchor schedule ID）
 *
 * @package Aidunite
 */

if (!function_exists('aidunite_resolve_home_anchor_schedule_id_for_match_request')) {
    /**
     * 成立後 MR からホーム（会場）側 schedule ID を取得
     *
     * @param int $match_request_id
     * @return int 0=未解決
     */
    function aidunite_resolve_home_anchor_schedule_id_for_match_request($match_request_id) {
        $match_request_id = (int) $match_request_id;
        if ($match_request_id <= 0) {
            return 0;
        }

        $to = (int) get_post_meta($match_request_id, 'to_schedule_id', true);
        $my = (int) get_post_meta($match_request_id, 'my_schedule_id', true);
        if ($my <= 0) {
            $my = (int) get_post_meta($match_request_id, 'from_schedule_id', true);
        }
        if ($to === 9999) {
            $to = 0;
        }

        foreach ([$to, $my] as $sid) {
            if ($sid <= 0) {
                continue;
            }
            $lock = (string) get_post_meta($sid, 'place_lock', true);
            if ($lock === 'home') {
                return $sid;
            }
            $place = (string) get_post_meta($sid, 'schedule_place', true);
            if ($place === '') {
                $place = (string) get_post_meta($sid, 'schedule_place_option', true);
            }
            if ($place === 'home') {
                return $sid;
            }
        }

        return 0;
    }
}

if (!function_exists('aidunite_get_anchor_team_id_for_match_game')) {
    /**
     * anchor schedule（match_game_id）の会場側 team_id
     *
     * @param int $match_game_id
     * @return int
     */
    function aidunite_get_anchor_team_id_for_match_game($match_game_id) {
        $match_game_id = (int) $match_game_id;
        if ($match_game_id <= 0) {
            return 0;
        }
        if (function_exists('aidunite_resolve_schedule_owner_team_id')) {
            $tid = (int) aidunite_resolve_schedule_owner_team_id($match_game_id);
            if ($tid > 0) {
                return $tid;
            }
        }
        $tid = (int) get_post_meta($match_game_id, 'team_id', true);
        if ($tid > 0) {
            return $tid;
        }
        $author_id = (int) get_post_field('post_author', $match_game_id);
        if ($author_id > 0) {
            return function_exists('aidunite_get_current_team_id')
                ? (int) aidunite_get_current_team_id($author_id)
                : (int) get_user_meta($author_id, 'team_id', true);
        }

        return 0;
    }
}

if (!function_exists('aidunite_infer_anchor_schedule_id_for_match_request')) {
    /**
     * match_game_id メタ未設定時の anchor 推定（解散・board 同期のレガシー互換）
     *
     * @param int $match_request_id
     * @return int
     */
    function aidunite_infer_anchor_schedule_id_for_match_request($match_request_id) {
        $match_request_id = (int) $match_request_id;
        if ($match_request_id <= 0) {
            return 0;
        }

        $home = aidunite_resolve_home_anchor_schedule_id_for_match_request($match_request_id);
        if ($home > 0) {
            return $home;
        }

        $to = (int) get_post_meta($match_request_id, 'to_schedule_id', true);
        $my = (int) get_post_meta($match_request_id, 'my_schedule_id', true);
        if ($my <= 0) {
            $my = (int) get_post_meta($match_request_id, 'from_schedule_id', true);
        }
        if ($to === 9999) {
            $to = 0;
        }

        if ($to > 0 && (string) get_post_meta($to, 'intent', true) === 'recruit') {
            return $to;
        }
        if ($my > 0 && (string) get_post_meta($my, 'intent', true) === 'recruit') {
            return $my;
        }
        if ($to > 0) {
            return $to;
        }

        return $my > 0 ? $my : 0;
    }
}

if (!function_exists('aidunite_resolve_match_game_id_for_match_request')) {
    /**
     * MR の match_game_id（試合の箱）を解決
     *
     * @param int $match_request_id
     * @return int 0=未解決
     */
    function aidunite_resolve_match_game_id_for_match_request($match_request_id) {
        $match_request_id = (int) $match_request_id;
        if ($match_request_id <= 0) {
            return 0;
        }

        $stored = (int) get_post_meta($match_request_id, 'match_game_id', true);
        if ($stored > 0) {
            return $stored;
        }

        $to = (int) get_post_meta($match_request_id, 'to_schedule_id', true);
        if ($to === 9999) {
            $to = 0;
        }
        if ($to > 0) {
            $sibling_game = aidunite_find_existing_match_game_id_for_anchor($to, $match_request_id);
            if ($sibling_game > 0) {
                return $sibling_game;
            }
        }

        $home = aidunite_resolve_home_anchor_schedule_id_for_match_request($match_request_id);
        if ($home > 0) {
            return $home;
        }

        return aidunite_infer_anchor_schedule_id_for_match_request($match_request_id);
    }
}

if (!function_exists('aidunite_find_existing_match_game_id_for_anchor')) {
    /**
     * 同一 anchor に既に付与された match_game_id を探す
     *
     * @param int $anchor_schedule_id
     * @param int $exclude_request_id
     * @return int
     */
    function aidunite_find_existing_match_game_id_for_anchor($anchor_schedule_id, $exclude_request_id = 0) {
        $anchor_schedule_id = (int) $anchor_schedule_id;
        $exclude_request_id = (int) $exclude_request_id;
        if ($anchor_schedule_id <= 0) {
            return 0;
        }

        if (!function_exists('aidunite_get_game_match_requests')) {
            return 0;
        }

        foreach (aidunite_get_game_match_requests($anchor_schedule_id) as $p) {
            $rid = (int) $p->ID;
            if ($exclude_request_id > 0 && $rid === $exclude_request_id) {
                continue;
            }
            $gid = (int) get_post_meta($rid, 'match_game_id', true);
            if ($gid > 0) {
                return $gid;
            }
            $meta = (string) get_post_meta($rid, 'status', true);
            $st   = function_exists('aidunite_normalize_match_request_status')
                ? aidunite_normalize_match_request_status($meta, isset($p->post_status) ? (string) $p->post_status : '')
                : strtolower($meta);
            if ($st === 'established') {
                return $anchor_schedule_id;
            }
        }

        return 0;
    }
}

if (!function_exists('aidunite_backfill_match_game_id_on_anchor')) {
    /**
     * 同一 game の established MR に match_game_id を揃える
     *
     * @param int $match_game_id
     * @param int $exclude_request_id
     * @return void
     */
    function aidunite_backfill_match_game_id_on_anchor($match_game_id, $exclude_request_id = 0) {
        $match_game_id = (int) $match_game_id;
        if ($match_game_id <= 0 || !function_exists('aidunite_get_game_match_requests')) {
            return;
        }
        foreach (aidunite_get_game_match_requests($match_game_id) as $p) {
            $rid = (int) $p->ID;
            if ($exclude_request_id > 0 && $rid === $exclude_request_id) {
                continue;
            }
            $meta = (string) get_post_meta($rid, 'status', true);
            $st   = function_exists('aidunite_normalize_match_request_status')
                ? aidunite_normalize_match_request_status($meta, isset($p->post_status) ? (string) $p->post_status : '')
                : strtolower($meta);
            if ($st !== 'established') {
                continue;
            }
            if ((int) get_post_meta($rid, 'match_game_id', true) !== $match_game_id) {
                update_post_meta($rid, 'match_game_id', $match_game_id);
            }
        }
    }
}

if (!function_exists('aidunite_assign_match_game_id_on_established')) {
    /**
     * 初回 established で home schedule を match_game_id に。2件目以降は継承。
     *
     * @param int $request_id
     * @return int 付与した match_game_id
     */
    function aidunite_assign_match_game_id_on_established($request_id) {
        $request_id = (int) $request_id;
        if ($request_id <= 0) {
            return 0;
        }

        $existing = (int) get_post_meta($request_id, 'match_game_id', true);
        if ($existing > 0) {
            aidunite_backfill_match_game_id_on_anchor($existing, $request_id);
            return $existing;
        }

        $home_sid = aidunite_resolve_home_anchor_schedule_id_for_match_request($request_id);
        if ($home_sid <= 0) {
            return 0;
        }

        $game_id = aidunite_find_existing_match_game_id_for_anchor($home_sid, $request_id);
        if ($game_id <= 0) {
            $game_id = $home_sid;
        }

        update_post_meta($request_id, 'match_game_id', $game_id);
        aidunite_backfill_match_game_id_on_anchor($game_id, 0);

        return $game_id;
    }
}

if (!function_exists('aidunite_actor_is_anchor_host_for_match_request')) {
    /**
     * 会場側（anchor team）がゲーム解散を実行できるか
     *
     * @param int $match_request_id
     * @param int $actor_team_id
     * @return bool
     */
    function aidunite_actor_is_anchor_host_for_match_request($match_request_id, $actor_team_id) {
        $actor_team_id = (int) $actor_team_id;
        if ($actor_team_id <= 0) {
            return false;
        }
        $game_id = aidunite_resolve_match_game_id_for_match_request((int) $match_request_id);
        if ($game_id <= 0) {
            return false;
        }
        $anchor_team = aidunite_get_anchor_team_id_for_match_game($game_id);

        return $anchor_team > 0 && $anchor_team === $actor_team_id;
    }
}

if (!function_exists('aidunite_get_active_chat_room_for_match_game')) {
    /**
     * §3A.1: 当該 match_game の active チャットを1件だけ返す（拡張先の参照用）。
     * 副ルーム統合・他 MR の chat_room_id 収集は行わない。
     *
     * @param int $match_game_id anchor schedule ID
     * @return object|null
     */
    function aidunite_get_active_chat_room_for_match_game($match_game_id) {
        $match_game_id = (int) $match_game_id;
        if ($match_game_id <= 0) {
            return null;
        }

        $room_id = (int) get_post_meta($match_game_id, 'active_match_chat_room_id', true);
        if ($room_id > 0 && function_exists('aidunite_get_chat_room')) {
            $room = aidunite_get_chat_room($room_id);
            if ($room && !empty($room->id)) {
                $status = (string) ($room->status ?? '');
                if ($status === 'active') {
                    return $room;
                }
                // completed は自動で active に戻さない（キャンセル→再承認で旧ルームが復活するのを防ぐ）
            }
        }

        return null;
    }
}

if (!function_exists('aidunite_bind_match_game_chat_room')) {
    /**
     * MR と anchor schedule の active チャットポインタを揃える（unify 代替）
     *
     * @param int $match_request_id
     * @param int $match_game_id
     * @param int $room_id
     * @return int
     */
    function aidunite_bind_match_game_chat_room($match_request_id, $match_game_id, $room_id) {
        $match_request_id = (int) $match_request_id;
        $match_game_id    = (int) $match_game_id;
        $room_id          = (int) $room_id;
        if ($room_id <= 0) {
            return 0;
        }
        if ($match_request_id > 0) {
            if (function_exists('aidunite_bind_match_chat_room_pointer')) {
                aidunite_bind_match_chat_room_pointer($match_request_id, $match_game_id, $room_id);
            } else {
                update_post_meta($match_request_id, 'chat_room_id', $room_id);
            }
        }
        if ($match_game_id > 0) {
            update_post_meta($match_game_id, 'active_match_chat_room_id', $room_id);
        }

        return $room_id;
    }
}

if (!function_exists('aidunite_parse_cancel_scope_from_request')) {
    /**
     * キャンセル操作の範囲（§17.5 dissolve / §17.6 pair）
     *
     * @return string dissolve|pair（未指定・不正値は pair）
     */
    function aidunite_parse_cancel_scope_from_request() {
        $scope = '';
        if (class_exists('WP_REST_Request') && function_exists('rest_get_server')) {
            $server = rest_get_server();
            $current = null;
            // 環境差分: get_current_request() を持たない WP_REST_Server でも致命にならないようにする。
            if ($server && method_exists($server, 'get_current_request')) {
                $current = $server->get_current_request();
            }
            if ($current instanceof WP_REST_Request) {
                $scope = (string) $current->get_param('cancel_scope');
                if ($scope === '') {
                    $params = $current->get_json_params();
                    if (is_array($params) && isset($params['cancel_scope'])) {
                        $scope = (string) $params['cancel_scope'];
                    }
                }
            }
        }
        if ($scope === '') {
            $scope = isset($_POST['cancel_scope']) ? (string) wp_unslash($_POST['cancel_scope']) : '';
        }
        if ($scope === '' && isset($_REQUEST['cancel_scope'])) {
            $scope = (string) wp_unslash($_REQUEST['cancel_scope']);
        }
        $scope = strtolower(sanitize_text_field($scope));

        return $scope === 'dissolve' ? 'dissolve' : 'pair';
    }
}

if (!function_exists('aidunite_resolve_withdrawn_team_id_for_canceled_match_request')) {
    /**
     * 共有ゲーム継続キャンセルで除外される team ID（主催操作時は申請元 team）
     *
     * @param int $match_request_id
     * @param int $actor_team_id 操作 team
     * @return int
     */
    function aidunite_resolve_withdrawn_team_id_for_canceled_match_request($match_request_id, $actor_team_id) {
        $match_request_id = (int) $match_request_id;
        $actor_team_id    = (int) $actor_team_id;
        $from_team_id     = (int) get_post_meta($match_request_id, 'from_team_id', true);

        $match_game_id = function_exists('aidunite_resolve_match_game_id_for_match_request')
            ? (int) aidunite_resolve_match_game_id_for_match_request($match_request_id)
            : 0;
        if ($match_game_id > 0 && function_exists('aidunite_get_anchor_team_id_for_match_game')) {
            $anchor_team = (int) aidunite_get_anchor_team_id_for_match_game($match_game_id);
            if ($anchor_team > 0 && $actor_team_id === $anchor_team && $from_team_id > 0) {
                return $from_team_id;
            }
        }

        if ($actor_team_id > 0) {
            return $actor_team_id;
        }

        return $from_team_id;
    }
}
