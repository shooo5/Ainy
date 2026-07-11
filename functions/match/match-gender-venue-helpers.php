<?php
/**
 * 性別・会場（枠・place_lock）仕様用ヘルパー
 * docs/match-gender-venue-and-flow-spec.md に準拠
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * スケジュールの男子枠残数を取得（後方互換: male_slots が無ければ male_teams を返す）
 */
if (!function_exists('aidunite_get_schedule_male_slots')) {
    function aidunite_get_schedule_male_slots($schedule_id) {
        $v = get_post_meta($schedule_id, 'male_slots', true);
        if ($v !== '' && $v !== null) {
            return (int) $v;
        }
        $v = get_post_meta($schedule_id, 'male_teams', true);
        return $v !== '' && $v !== null ? (int) $v : 0;
    }
}

/**
 * スケジュールの女子枠残数を取得（後方互換: female_slots が無ければ female_teams を返す）
 */
if (!function_exists('aidunite_get_schedule_female_slots')) {
    function aidunite_get_schedule_female_slots($schedule_id) {
        $v = get_post_meta($schedule_id, 'female_slots', true);
        if ($v !== '' && $v !== null) {
            return (int) $v;
        }
        $v = get_post_meta($schedule_id, 'female_teams', true);
        return $v !== '' && $v !== null ? (int) $v : 0;
    }
}

/**
 * スケジュールの place_lock を取得（無ければ空文字）
 */
if (!function_exists('aidunite_get_schedule_place_lock')) {
    function aidunite_get_schedule_place_lock($schedule_id) {
        $v = get_post_meta($schedule_id, 'place_lock', true);
        return ($v === 'home' || $v === 'away') ? $v : '';
    }
}

/**
 * 指定スケジュールに関連する established 件数を返す（自スケジュール視点: my_schedule_id または to_schedule_id が一致する申請）
 */
if (!function_exists('aidunite_count_established_for_schedule')) {
    function aidunite_count_established_for_schedule($schedule_id) {
        $posts = get_posts([
            'post_type'      => 'match_request',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'meta_query'     => [
                'relation' => 'AND',
                ['key' => 'status', 'value' => ['established', '試合確定', 'accepted', '承認済み'], 'compare' => 'IN'],
                [
                    'relation' => 'OR',
                    ['key' => 'my_schedule_id', 'value' => (string) $schedule_id, 'compare' => '='],
                    ['key' => 'to_schedule_id', 'value' => (string) $schedule_id, 'compare' => '='],
                ],
            ],
        ]);
        $count = 0;
        foreach ($posts as $p) {
            if (function_exists('aidunite_match_request_counts_as_guest_commitment')
                && aidunite_match_request_counts_as_guest_commitment((int) $p->ID)) {
                $count++;
            }
        }

        return $count;
    }
}

/**
 * パターンA・枠消費で「コミット済み」とみなす match_request.status 値（meta 保存値）。
 *
 * @return string[]
 */
if (!function_exists('aidunite_match_request_committed_status_meta_values')) {
    function aidunite_match_request_committed_status_meta_values() {
        return ['established', '試合確定', 'accepted', '承認済み'];
    }
}

/**
 * 当該 MR が参加側スケジュールをコミット済みか（承認直後・メタ未同期の accepted も含む）。
 *
 * @param int $request_id
 */
if (!function_exists('aidunite_match_request_counts_as_guest_commitment')) {
    function aidunite_match_request_counts_as_guest_commitment($request_id) {
        $request_id = (int) $request_id;
        if ($request_id <= 0) {
            return false;
        }
        $raw = (string) get_post_meta($request_id, 'status', true);
        $post = get_post($request_id);
        $norm = function_exists('aidunite_normalize_match_request_status')
            ? aidunite_normalize_match_request_status($raw, $post ? (string) $post->post_status : '')
            : strtolower($raw);
        if (in_array($norm, ['canceled', 'rejected'], true)) {
            return false;
        }
        if (in_array($raw, ['established', '試合確定'], true)) {
            return true;
        }
        if (get_post_meta($request_id, 'established_at', true) !== '') {
            return true;
        }

        return $norm === 'established' || $norm === 'accepted';
    }
}

/**
 * 募集中一覧用: 募集 schedule の性別 canonical（男子/女子メタ・レガシー both 誤保存の救済）。
 *
 * @param int $schedule_id
 * @return string '', 'male', 'female'
 */
if (!function_exists('aidunite_market_recruitment_gender_canonical')) {
    function aidunite_market_recruitment_gender_canonical($schedule_id) {
        $schedule_id = (int) $schedule_id;
        if ($schedule_id <= 0) {
            return '';
        }
        $sources = [];
        if (function_exists('aidunite_schedule_get_display_bundle')) {
            $bundle = aidunite_schedule_get_display_bundle($schedule_id);
            if (($bundle['gender'] ?? '') !== '') {
                $sources[] = (string) $bundle['gender'];
            }
        }
        if (function_exists('aidunite_schedule_read_gender_raw')) {
            $sources[] = aidunite_schedule_read_gender_raw($schedule_id);
        }
        foreach ($sources as $raw) {
            if (function_exists('aidunite_mvp_gender_raw_is_both_legacy') && aidunite_mvp_gender_raw_is_both_legacy($raw)) {
                continue;
            }
            $canon = function_exists('aidunite_normalize_gender_canonical')
                ? aidunite_normalize_gender_canonical((string) $raw)
                : '';
            if (function_exists('aidunite_mvp_gender_is_valid') && aidunite_mvp_gender_is_valid($canon)) {
                return $canon;
            }
        }

        return '';
    }
}

/**
 * パターンAの「1 schedule＝1試合」対象か（参加側の対戦希望。主催募集の残枠あり schedule は対象外）。
 *
 * @param int $schedule_id
 */
if (!function_exists('aidunite_schedule_is_guest_slot_schedule')) {
    function aidunite_schedule_is_guest_slot_schedule($schedule_id) {
        $schedule_id = (int) $schedule_id;
        if ($schedule_id <= 0) {
            return false;
        }
        $place_lock = function_exists('aidunite_get_schedule_place_lock')
            ? aidunite_get_schedule_place_lock($schedule_id)
            : '';
        if ($place_lock === 'away') {
            return true;
        }
        $intent = function_exists('aidunite_schedule_read_intent')
            ? aidunite_schedule_read_intent($schedule_id)
            : (string) get_post_meta($schedule_id, 'intent', true);
        if ($intent === 'tentative') {
            return true;
        }
        $place_raw = function_exists('aidunite_schedule_read_place_raw')
            ? aidunite_schedule_read_place_raw($schedule_id)
            : '';
        $bundle_match = function_exists('aidunite_schedule_get_display_bundle')
            ? aidunite_schedule_get_display_bundle($schedule_id)
            : [];
        $place_lc = function_exists('aidunite_normalize_place_for_lock')
            ? aidunite_normalize_place_for_lock($place_raw)
            : strtolower(trim((string) $place_raw));
        $matching_on = function_exists('aidunite_schedule_matching_meta_on')
            ? aidunite_schedule_matching_meta_on($schedule_id)
            : (($bundle_match['matching'] ?? '') === '1'
                || get_post_meta($schedule_id, 'matching', true) === '1'
                || get_post_meta($schedule_id, 'is_match_requested', true) === '1');
        // 参加側のマッチ希望（アウェイ／どちらでも可系）
        if ($matching_on && in_array($place_lc, ['away', 'either'], true)) {
            return true;
        }
        // 主催ホーム募集（残枠あり）: 5691 のような schedule はパターンAの「参加枠」ではない
        if ($intent === 'recruit' && $matching_on && in_array($place_lc, ['home', 'either'], true)) {
            $male_rem = function_exists('aidunite_get_schedule_male_slots') ? (int) aidunite_get_schedule_male_slots($schedule_id) : 0;
            $female_rem = function_exists('aidunite_get_schedule_female_slots') ? (int) aidunite_get_schedule_female_slots($schedule_id) : 0;
            if ($male_rem > 0 || $female_rem > 0) {
                return false;
            }
        }

        // 成立後: 参加側（アウェイ確定）のみ。place_lock 未設定の主催ホームを !== 'home' で拾うと teamA 募集中が全消しになる
        if ($intent === 'confirmed') {
            if ($place_lock === 'away') {
                return true;
            }
            if ($place_lock === 'home' || $place_lc === 'home') {
                return false;
            }

            return in_array($place_lc, ['away', 'either'], true);
        }

        return false;
    }
}

/**
 * 参加側残枠（パターンA）: 当該 schedule を my_schedule_id にした established が1件でもあれば 0、なければ 1。
 * 時間帯は見ない（1 schedule 投稿あたり同時に載せられる試合は1本まで）。
 *
 * @param int $schedule_id 申請者側の schedule 投稿 ID
 * @return int 0|1
 */
if (!function_exists('aidunite_schedule_guest_remaining')) {
    function aidunite_schedule_guest_remaining($schedule_id) {
        $schedule_id = (int) $schedule_id;
        if ($schedule_id <= 0) {
            return 0;
        }
        if (!aidunite_schedule_is_guest_slot_schedule($schedule_id)) {
            return 1;
        }
        if (function_exists('aidunite_schedule_read_intent')
            ? aidunite_schedule_read_intent($schedule_id) === 'confirmed'
            : (string) get_post_meta($schedule_id, 'intent', true) === 'confirmed') {
            return 0;
        }
        // status だけで絞ると pending のまま established_at がある漏れが出るため、my_schedule_id のみで取得して counts_as で判定
        $candidates = get_posts([
            'post_type'      => 'match_request',
            'post_status'    => 'any',
            'posts_per_page' => 10,
            'meta_query'     => [
                ['key' => 'my_schedule_id', 'value' => (string) $schedule_id, 'compare' => '='],
            ],
        ]);
        foreach ($candidates as $req) {
            if (aidunite_match_request_counts_as_guest_commitment((int) $req->ID)) {
                return 0;
            }
        }

        return 1;
    }
}

if (!function_exists('aidunite_market_schedule_unavailable_as_opponent_recruitment')) {
    /**
     * 他チーム募集行として出さない／申請不可（§6B: 参加側 schedule 消費済み）。
     *
     * @param int $schedule_id 掲示板に載せる相手 schedule ID
     * @return bool true=載せない
     */
    function aidunite_market_schedule_unavailable_as_opponent_recruitment($schedule_id) {
        $schedule_id = (int) $schedule_id;
        if ($schedule_id <= 0) {
            return true;
        }

        if (function_exists('aidunite_schedule_is_guest_slot_schedule')
            && aidunite_schedule_is_guest_slot_schedule($schedule_id)
            && function_exists('aidunite_schedule_guest_remaining')
            && aidunite_schedule_guest_remaining($schedule_id) < 1) {
            return true;
        }

        if (function_exists('aidunite_schedule_read_intent')
            ? aidunite_schedule_read_intent($schedule_id) === 'confirmed'
            : (string) get_post_meta($schedule_id, 'intent', true) === 'confirmed') {
            $place_lock = function_exists('aidunite_get_schedule_place_lock')
                ? aidunite_get_schedule_place_lock($schedule_id)
                : '';
            if ($place_lock === 'away') {
                return true;
            }
        }

        $unavail_bundle = function_exists('aidunite_schedule_get_display_bundle')
            ? aidunite_schedule_get_display_bundle($schedule_id)
            : [];
        $team_id = (int) ($unavail_bundle['team_id'] ?? (function_exists('aidunite_schedule_read_team_id')
            ? aidunite_schedule_read_team_id($schedule_id)
            : 0));
        $date_norm = function_exists('aidunite_schedule_read_normalized_date')
            ? aidunite_schedule_read_normalized_date($schedule_id)
            : '';
        if ($team_id > 0 && $date_norm !== ''
            && function_exists('aidunite_team_has_guest_committed_schedule_on_date')
            && aidunite_team_has_guest_committed_schedule_on_date($team_id, $date_norm)) {
            return true;
        }

        return false;
    }
}

/**
 * 募集中タブの母集団: 主催募集がまだ受付可能か（確定済み除外・性別枠残あり）。
 *
 * @param int $recruit_schedule_id 他チームの募集 schedule ID
 */
if (!function_exists('aidunite_normalize_schedule_place_meta_value')) {
    /**
     * 会場メタの both を either に正規化（既存データの読取時修復を含む）。
     *
     * @param int $schedule_id
     * @return string
     */
    function aidunite_normalize_schedule_place_meta_value($schedule_id) {
        $schedule_id = (int) $schedule_id;
        if ($schedule_id <= 0) {
            return '';
        }
        $place = function_exists('aidunite_schedule_read_place_raw')
            ? aidunite_schedule_read_place_raw($schedule_id)
            : '';
        if (function_exists('aidunite_normalize_place_for_lock')) {
            $norm = aidunite_normalize_place_for_lock($place);
        } else {
            $norm = (string) $place === 'both' ? 'either' : (string) $place;
        }
        if ($norm === 'both') {
            $norm = 'either';
        }
        if (in_array($norm, ['home', 'away', 'either'], true)) {
            if (function_exists('aidunite_schedule_write_place_meta')) {
                aidunite_schedule_write_place_meta($schedule_id, $norm);
            } else {
                update_post_meta($schedule_id, 'schedule_place', $norm);
            }
        }

        return $norm;
    }
}

if (!function_exists('aidunite_schedule_matching_meta_on')) {
    /**
     * schedule がマッチ募集・対戦希望として有効なメタか（matching=1 または is_match_requested=1）。
     * 低レイヤ判定。掲示板の「枠残で matching=0」は aidunite_recruit_accepts_match_applications を使う。
     *
     * @param int $schedule_id
     * @return bool
     */
    function aidunite_schedule_matching_meta_on($schedule_id) {
        $pid = (int) $schedule_id;
        if ($pid <= 0) {
            return false;
        }
        $matching = get_post_meta($pid, 'matching', true);
        $is_mr = get_post_meta($pid, 'is_match_requested', true);

        return $matching === '1' || $matching === 1 || $matching === true
            || $is_mr === '1' || $is_mr === 1 || $is_mr === true;
    }
}

if (!function_exists('aidunite_recruit_accepts_match_applications')) {
    /**
     * 募集 schedule が申請・掲示板プレビューの対象か（matching ON または枠残 recruit_open）。
     * 母集団・統一判定・validate で同じ条件を使う。
     *
     * @param int $recruit_schedule_id
     * @return bool
     */
    function aidunite_recruit_accepts_match_applications($recruit_schedule_id) {
        $pid = (int) $recruit_schedule_id;
        if ($pid <= 0) {
            return false;
        }
        if (function_exists('aidunite_market_schedule_unavailable_as_opponent_recruitment')
            && aidunite_market_schedule_unavailable_as_opponent_recruitment($pid)) {
            return false;
        }
        // 当該募集 schedule に成立 MR が付いていれば新規申請・掲示板母集団から外す（6889 型）
        if (function_exists('aidunite_count_established_for_schedule')
            && aidunite_count_established_for_schedule($pid) > 0) {
            return false;
        }
        if (aidunite_schedule_matching_meta_on($pid)) {
            return true;
        }

        return function_exists('aidunite_recruit_open_for_market_board')
            && aidunite_recruit_open_for_market_board($pid);
    }
}

if (!function_exists('aidunite_recruit_open_for_market_board')) {
    function aidunite_recruit_open_for_market_board($recruit_schedule_id) {
        $reason = function_exists('aidunite_recruit_open_for_market_board_reason')
            ? aidunite_recruit_open_for_market_board_reason($recruit_schedule_id)
            : ['open' => false, 'reason' => 'reason_fn_missing'];

        return !empty($reason['open']);
    }
}

if (!function_exists('aidunite_recruit_open_for_market_board_reason')) {
    /**
     * @param int $recruit_schedule_id
     * @return array{open:bool,reason:string}
     */
    function aidunite_recruit_open_for_market_board_reason($recruit_schedule_id) {
        $pid = (int) $recruit_schedule_id;
        if ($pid <= 0) {
            return ['open' => false, 'reason' => 'invalid_id'];
        }
        if (get_post_status($pid) !== 'publish') {
            return ['open' => false, 'reason' => 'not_publish'];
        }
        if (function_exists('aidunite_normalize_schedule_place_meta_value')) {
            aidunite_normalize_schedule_place_meta_value($pid);
        }
        if (!function_exists('aidunite_get_remaining_gender_slots')) {
            return ['open' => true, 'reason' => 'slots_fn_missing'];
        }
        $remaining = aidunite_get_remaining_gender_slots($pid);
        $canon = function_exists('aidunite_market_recruitment_gender_canonical')
            ? aidunite_market_recruitment_gender_canonical($pid)
            : '';
        if ($canon === 'male') {
            $ok = (int) ($remaining['male'] ?? 0) > 0;
            if ($ok) {
                return ['open' => true, 'reason' => 'male_slot_ok'];
            }
        } elseif ($canon === 'female') {
            $ok = (int) ($remaining['female'] ?? 0) > 0;
            if ($ok) {
                return ['open' => true, 'reason' => 'female_slot_ok'];
            }
        } else {
            $ok = ((int) ($remaining['male'] ?? 0) > 0) || ((int) ($remaining['female'] ?? 0) > 0);
            if ($ok) {
                return ['open' => true, 'reason' => 'any_slot_ok'];
            }
        }
        // 枠残0: 複数枠主催で1件だけ成立済み（intent=confirmed でも）ここで閉じる
        if (function_exists('aidunite_schedule_read_intent')
            ? aidunite_schedule_read_intent($pid) === 'confirmed'
            : (string) get_post_meta($pid, 'intent', true) === 'confirmed') {
            return ['open' => false, 'reason' => 'intent_confirmed_slots_full'];
        }
        if (!function_exists('aidunite_schedule_matching_meta_on')
            || !aidunite_schedule_matching_meta_on($pid)) {
            return ['open' => false, 'reason' => 'matching_off'];
        }

        return ['open' => false, 'reason' => $canon === 'male' ? 'male_slots_full' : ($canon === 'female' ? 'female_slots_full' : 'slots_full')];
    }
}

/**
 * place 値を正規化（both を either に。functions.php の normalize_place_value と併用）
 */
if (!function_exists('aidunite_normalize_place_for_lock')) {
    function aidunite_normalize_place_for_lock($place) {
        $place = trim((string) $place);
        if ($place === 'both' || $place === 'どちらでも可' || $place === 'どちらでも') {
            return 'either';
        }
        if ($place === 'home' || $place === 'away' || $place === 'either') {
            return $place;
        }
        if (function_exists('normalize_place_value')) {
            $n = normalize_place_value($place);
            return $n === 'both' ? 'either' : $n;
        }
        return $place;
    }
}

/**
 * 成立時の place_lock を決定（自チームスケジュール視点・仕様 2.6）
 * @param string $my_place 自スケジュールの place（home/away/either）
 * @param string $other_place 相手スケジュールの place
 * @return string 'home' | 'away'
 */
if (!function_exists('aidunite_resolve_place_lock')) {
    function aidunite_resolve_place_lock($my_place, $other_place) {
        $my_place = aidunite_normalize_place_for_lock($my_place);
        $other_place = aidunite_normalize_place_for_lock($other_place);
        if ($my_place === 'home') {
            return 'home';
        }
        if ($my_place === 'away') {
            return 'away';
        }
        if ($my_place === 'either') {
            if ($other_place === 'home') {
                return 'away';
            }
            if ($other_place === 'away') {
                return 'home';
            }
            return 'home';
        }
        return 'home';
    }
}

/**
 * place_lock と相手の place が整合するか（自スケジュールに place_lock がある場合、相手がその開催形式で成立可能か）
 * 自 place_lock=home → 相手は away または either でOK。自 place_lock=away → 相手は home または either でOK。
 */
if (!function_exists('aidunite_place_lock_matches_candidate')) {
    function aidunite_place_lock_matches_candidate($my_place_lock, $candidate_place) {
        if ($my_place_lock !== 'home' && $my_place_lock !== 'away') {
            return true;
        }
        $other = aidunite_normalize_place_for_lock($candidate_place);
        if ($my_place_lock === 'home') {
            return $other === 'away' || $other === 'either';
        }
        return $other === 'home' || $other === 'either';
    }
}

/**
 * 相手スケジュールの性別を取得（male/female/both）
 */
if (!function_exists('aidunite_get_schedule_gender')) {
    function aidunite_get_schedule_gender($schedule_id) {
        $schedule_id = (int) $schedule_id;
        if (function_exists('aidunite_schedule_read_gender_raw')) {
            return (string) aidunite_schedule_read_gender_raw($schedule_id);
        }

        return '';
    }
}

/**
 * スケジュールの性別別「残り枠」を返す（成立済みを性別別に集計して減算）
 * docs/match-gender-venue-and-flow-spec.md 準拠。🟢判定で使用。
 *
 * @param int $schedule_id 自チームのスケジュールID
 * @return array ['male' => int, 'female' => int] 残り枠（0以上）
 */
if (!function_exists('aidunite_get_remaining_gender_slots')) {
    function aidunite_get_remaining_gender_slots($schedule_id) {
        $schedule_id = (int) $schedule_id;
        $male_total = function_exists('aidunite_get_schedule_male_slots') ? aidunite_get_schedule_male_slots($schedule_id) : (int) get_post_meta($schedule_id, 'male_slots', true);
        $female_total = function_exists('aidunite_get_schedule_female_slots') ? aidunite_get_schedule_female_slots($schedule_id) : (int) get_post_meta($schedule_id, 'female_slots', true);

        // 成立時に male_slots/female_slots を直接減算済み（申請状況の「残」と同じ）。MR 再減算で0になるのを防ぐ。
        if (get_post_meta($schedule_id, 'pre_established_saved', true) === '1') {
            return [
                'male'   => max(0, $male_total),
                'female' => max(0, $female_total),
            ];
        }

        $male_consumed = 0;
        $female_consumed = 0;

        $requests = get_posts([
            'post_type'      => 'match_request',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'meta_query'     => [
                'relation' => 'AND',
                ['key' => 'status', 'value' => aidunite_match_request_committed_status_meta_values(), 'compare' => 'IN'],
                [
                    'relation' => 'OR',
                    ['key' => 'my_schedule_id', 'value' => (string) $schedule_id, 'compare' => '='],
                    ['key' => 'to_schedule_id', 'value' => (string) $schedule_id, 'compare' => '='],
                ],
            ],
        ]);

        foreach ($requests as $req) {
            if (!aidunite_match_request_counts_as_guest_commitment((int) $req->ID)) {
                continue;
            }
            $slot = get_post_meta($req->ID, 'established_gender_slot', true);
            if ($slot === 'male') {
                $male_consumed++;
                continue;
            }
            if ($slot === 'female') {
                $female_consumed++;
                continue;
            }
            $mr_slots = function_exists('aidunite_match_request_get_canonical_meta')
                ? aidunite_match_request_get_canonical_meta((int) $req->ID)
                : [];
            $my_id = (int) ($mr_slots['my_schedule_id'] ?? get_post_meta($req->ID, 'my_schedule_id', true));
            $to_id = (int) ($mr_slots['to_schedule_id'] ?? get_post_meta($req->ID, 'to_schedule_id', true));
            $other_schedule_id = ($my_id === (int) $schedule_id) ? $to_id : $my_id;
            if ($other_schedule_id <= 0) {
                continue;
            }
            $other_gender = function_exists('aidunite_get_schedule_gender') ? aidunite_get_schedule_gender($other_schedule_id) : '';
            if ($other_gender === 'male') {
                $male_consumed++;
            } elseif ($other_gender === 'female') {
                $female_consumed++;
            } elseif ($other_gender === 'both') {
                $male_consumed++;
                $female_consumed++;
            }
        }

        return [
            'male'   => max(0, $male_total - $male_consumed),
            'female' => max(0, $female_total - $female_consumed),
        ];
    }
}

/**
 * 指定スケジュール（自分スケ）に紐づく申請一覧を取得（申請状況タブ用。申請中 publish 含む）
 *
 * @param int $schedule_id 自チームのスケジュールID
 * @param int $current_user_team_id 現在ユーザーのチームID
 * @return array 各要素: request_id, team_name, gender_display, gender_slot, time_display, place_display,
 *                status, cta_type ('cancel'|'cancel_apply'|'approve_reject'|'reapply'|'badge'),
 *                other_schedule_id, is_requester, badge_label, sort_order
 */
if (!function_exists('aidunite_recruit_schedule_fingerprint')) {
    /**
     * 募集 schedule の主要メタから条件スナップショット（申請時・再申請判定用）
     *
     * @param int $schedule_id
     * @return string md5 hex
     */
    function aidunite_recruit_schedule_fingerprint($schedule_id) {
        $schedule_id = (int) $schedule_id;
        if ($schedule_id <= 0) {
            return '';
        }
        $keys = [
            'intent',
            'place_lock',
            'male_slots',
            'female_slots',
            'schedule_gender',
            'matching_gender_condition',
            'schedule_place',
            'schedule_place_option',
            'match_status',
        ];
        $parts = [];
        foreach ($keys as $k) {
            $parts[] = $k . '=' . (string) get_post_meta($schedule_id, $k, true);
        }
        return md5(implode('|', $parts));
    }
}

if (!function_exists('aidunite_applicant_can_apply_to_recruit_now')) {
    /**
     * 申請者スケジュールから、現在の募集に申請可能か（統一判定・スケジュール希望ベース）
     *
     * @return array{ok:bool, reason:string}
     */
    function aidunite_applicant_can_apply_to_recruit_now($recruit_schedule_id, $my_schedule_id) {
        $recruit_schedule_id = (int) $recruit_schedule_id;
        $my_schedule_id = (int) $my_schedule_id;
        if ($recruit_schedule_id <= 0 || $my_schedule_id <= 0) {
            return ['ok' => false, 'reason' => '日程情報が不足しています。'];
        }
        if (function_exists('aidunite_evaluate_match_apply_context')) {
            $g = function_exists('aidunite_get_schedule_gender') ? aidunite_get_schedule_gender($my_schedule_id) : '';
            $g = is_string($g) ? $g : '';
            $my_apply_bundle = function_exists('aidunite_schedule_get_display_bundle')
                ? aidunite_schedule_get_display_bundle($my_schedule_id)
                : [];
            $my_apply_api = function_exists('aidunite_schedule_get_api_display_fields')
                ? aidunite_schedule_get_api_display_fields($my_schedule_id)
                : [];
            $st = (string) ($my_apply_bundle['start_time'] ?? $my_apply_api['start_time'] ?? '');
            $en = (string) ($my_apply_bundle['end_time'] ?? $my_apply_api['end_time'] ?? '');
            $evp = aidunite_evaluate_match_apply_context($recruit_schedule_id, $my_schedule_id, ['mode' => 'preview']);
            if (!empty($evp['can_apply'])) {
                return ['ok' => true, 'reason' => ''];
            }
            if (($evp['variant'] ?? '') === 'proposal' && !empty($evp['proposal']['selected_place'])) {
                $ev2 = aidunite_evaluate_match_apply_context($recruit_schedule_id, $my_schedule_id, [
                    'mode'                => 'validate',
                    'selected_place'      => (string) $evp['proposal']['selected_place'],
                    'selected_gender'     => $g,
                    'selected_start_time' => $st,
                    'selected_end_time'   => $en,
                ]);
                return [
                    'ok'     => !empty($ev2['can_apply']),
                    'reason' => !empty($ev2['can_apply']) ? '' : (string) ($ev2['message'] ?: ''),
                ];
            }
            return [
                'ok'     => false,
                'reason' => (string) ($evp['message'] ?: ''),
            ];
        }
        $to_place_lock = function_exists('aidunite_get_schedule_place_lock') ? aidunite_get_schedule_place_lock($recruit_schedule_id) : '';
        $applicant_place = function_exists('aidunite_schedule_read_place_raw')
            ? aidunite_schedule_read_place_raw($my_schedule_id)
            : '';
        if ($to_place_lock !== '') {
            if (!function_exists('aidunite_place_lock_matches_candidate') || !aidunite_place_lock_matches_candidate($to_place_lock, $applicant_place)) {
                return ['ok' => false, 'reason' => '現在の募集の会場条件と自チームの開催形式が一致しません。'];
            }
        }
        $to_male = function_exists('aidunite_get_schedule_male_slots') ? aidunite_get_schedule_male_slots($recruit_schedule_id) : (int) get_post_meta($recruit_schedule_id, 'male_slots', true);
        $to_female = function_exists('aidunite_get_schedule_female_slots') ? aidunite_get_schedule_female_slots($recruit_schedule_id) : (int) get_post_meta($recruit_schedule_id, 'female_slots', true);
        $opponent_gender = function_exists('aidunite_get_schedule_gender') ? aidunite_get_schedule_gender($my_schedule_id) : '';
        if ($opponent_gender === 'male' && $to_male < 1) {
            return ['ok' => false, 'reason' => 'この日程の募集枠（男子）が満了です。'];
        }
        if ($opponent_gender === 'female' && $to_female < 1) {
            return ['ok' => false, 'reason' => 'この日程の募集枠（女子）が満了です。'];
        }
        if (($opponent_gender === 'both' || $opponent_gender === '') && $to_male < 1 && $to_female < 1) {
            return ['ok' => false, 'reason' => 'この日程の募集枠が満了です。'];
        }
        return ['ok' => true, 'reason' => ''];
    }
}

if (!function_exists('aidunite_match_detail_reapply_cta')) {
    /**
     * マッチ詳細・掲示板の再申請ボタン文言と可否
     *
     * @return array{variant:string, label:string, note:string}
     */
    function aidunite_match_detail_reapply_cta($recruit_schedule_id, $latest_request_id, $my_schedule_id) {
        $can = aidunite_applicant_can_apply_to_recruit_now($recruit_schedule_id, $my_schedule_id);
        if (!$can['ok']) {
            return ['variant' => 'ineligible', 'label' => '', 'note' => $can['reason']];
        }
        $fp_now  = aidunite_recruit_schedule_fingerprint($recruit_schedule_id);
        $fp_then = $latest_request_id ? (string) get_post_meta($latest_request_id, 'recruit_condition_fp', true) : '';
        if ($fp_then !== '' && $fp_then === $fp_now) {
            return ['variant' => 'same', 'label' => '再申請する', 'note' => ''];
        }
        return [
            'variant' => 'new',
            'label'   => '新しい条件で申請する',
            'note'    => '募集側の条件が申請時から変更されています。',
        ];
    }
}

if (!function_exists('aidunite_get_established_requests_for_schedule')) {
    function aidunite_get_established_requests_for_schedule($schedule_id, $current_user_team_id, $args = []) {
        $schedule_id = (int) $schedule_id;
        $current_user_team_id = (int) $current_user_team_id;
        if ($schedule_id <= 0) {
            return [];
        }
        $exclude_terminal = !empty($args['exclude_terminal']);

        $requests = get_posts([
            'post_type'      => 'match_request',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'meta_query'     => [
                'relation' => 'AND',
                ['key' => 'status', 'value' => ['established', '試合確定', 'accepted', '承認済み', 'canceled', 'キャンセル済み', 'rejected', '拒否済み', 'publish', 'pending', '申請中'], 'compare' => 'IN'],
                [
                    'relation' => 'OR',
                    ['key' => 'my_schedule_id', 'value' => (string) $schedule_id, 'compare' => '='],
                    ['key' => 'to_schedule_id', 'value' => (string) $schedule_id, 'compare' => '='],
                    // レガシー：my_schedule_id 未設定で from_schedule_id のみの MR（募集側から見ても拾う）
                    ['key' => 'from_schedule_id', 'value' => (string) $schedule_id, 'compare' => '='],
                ],
            ],
        ]);

        $out = [];
        foreach ($requests as $req) {
            $mr_row = function_exists('aidunite_match_request_get_canonical_meta')
                ? aidunite_match_request_get_canonical_meta((int) $req->ID)
                : [];
            $status = (string) ($mr_row['status'] ?? get_post_meta($req->ID, 'status', true));
            if ($exclude_terminal && function_exists('aidunite_normalize_match_request_status')) {
                $norm_early = aidunite_normalize_match_request_status((string) $status, isset($req->post_status) ? (string) $req->post_status : '');
                if (in_array($norm_early, ['canceled', 'rejected'], true)) {
                    continue;
                }
            }
            $my_id = (int) ($mr_row['my_schedule_id'] ?? get_post_meta($req->ID, 'my_schedule_id', true));
            if ($my_id <= 0) {
                $my_id = (int) ($mr_row['from_schedule_id'] ?? get_post_meta($req->ID, 'from_schedule_id', true));
            }
            $to_id = (int) ($mr_row['to_schedule_id'] ?? get_post_meta($req->ID, 'to_schedule_id', true));
            $from_team_id = (int) ($mr_row['from_team_id'] ?? get_post_meta($req->ID, 'from_team_id', true));
            $to_team_id = (int) ($mr_row['to_team_id'] ?? get_post_meta($req->ID, 'to_team_id', true));

            // 申請者／受信者は my_schedule_id と一覧アンカーの一致に依存させない（メタずれで申請中↔承認待ちが逆転するのを防ぐ）
            $i_am_applicant = ($from_team_id > 0 && (int) $from_team_id === (int) $current_user_team_id);
            if (!$from_team_id) {
                $i_am_applicant = ($my_id === $schedule_id);
            }
            $is_requester = $i_am_applicant;

            if ($i_am_applicant) {
                $other_schedule_id = $to_id;
                $other_team_id = $to_team_id;
                if ($to_id > 0) {
                    $tid = (int) get_post_meta($to_id, 'team_id', true);
                    if ($tid > 0) {
                        $other_team_id = $tid;
                    }
                }
            } else {
                $other_schedule_id = $my_id;
                if ($from_team_id > 0) {
                    $other_team_id = $from_team_id;
                } elseif ($my_id > 0) {
                    $other_team_id = (int) get_post_meta($my_id, 'team_id', true);
                } else {
                    $other_team_id = $to_team_id;
                }
            }
            $team_name = $other_team_id ? get_the_title($other_team_id) : '—';

            $selected_gender = (string) ($mr_row['selected_gender'] ?? get_post_meta($req->ID, 'selected_gender', true));
            $established_slot_meta = get_post_meta($req->ID, 'established_gender_slot', true);
            // 試合確定後は実際に消費した枠（male/female）を優先（selected_gender='both' だと UI が女子枠だけに偏るのを防ぐ）
            if (in_array($status, ['established', '試合確定'], true) && in_array($established_slot_meta, ['male', 'female'], true)) {
                $gender_slot = $established_slot_meta;
                $gender_display = function_exists('aidunite_jp_gender') ? aidunite_jp_gender($established_slot_meta) : $established_slot_meta;
            } elseif ($selected_gender === 'both') {
                $slot_from_meta = in_array($established_slot_meta, ['male', 'female'], true)
                    ? $established_slot_meta
                    : '';
                if ($slot_from_meta !== '') {
                    $gender_slot = $slot_from_meta;
                    $gender_display = function_exists('aidunite_jp_gender') ? aidunite_jp_gender($slot_from_meta) : $slot_from_meta;
                } else {
                    $gender_slot = 'male';
                    $gender_display = function_exists('aidunite_jp_gender') ? aidunite_jp_gender('male') : '男子';
                }
            } elseif ($selected_gender === 'male') {
                $gender_slot = 'male';
                $gender_display = function_exists('aidunite_jp_gender') ? aidunite_jp_gender('male') : '男子';
            } elseif ($selected_gender === 'female') {
                $gender_slot = 'female';
                $gender_display = function_exists('aidunite_jp_gender') ? aidunite_jp_gender('female') : '女子';
            } else {
                $gender_slot = get_post_meta($req->ID, 'established_gender_slot', true);
                if ($gender_slot === 'male') {
                    $gender_display = function_exists('aidunite_jp_gender') ? aidunite_jp_gender('male') : '男子';
                } elseif ($gender_slot === 'female') {
                    $gender_display = function_exists('aidunite_jp_gender') ? aidunite_jp_gender('female') : '女子';
                } else {
                    $other_sid = $other_schedule_id > 0 ? $other_schedule_id : (($my_id === $schedule_id) ? $to_id : $my_id);
                    $gender_display = $other_sid && function_exists('aidunite_get_schedule_gender') ? (function_exists('aidunite_jp_gender') ? aidunite_jp_gender(aidunite_get_schedule_gender($other_sid)) : '') : '—';
                    if ($other_sid && function_exists('aidunite_get_schedule_gender')) {
                        $resolved_gender = aidunite_get_schedule_gender($other_sid);
                        if ($resolved_gender === 'both') {
                            $gender_slot = 'both';
                        } else {
                            $gender_slot = $resolved_gender === 'female' ? 'female' : 'male';
                        }
                    }
                }
            }

            $place_display = '—';
            $selected_place = (string) ($mr_row['selected_place'] ?? get_post_meta($req->ID, 'selected_place', true));
            if ($selected_place === 'both') {
                $selected_place = 'either';
            }
            $is_pending_like = in_array($status, ['publish', 'pending', '申請中'], true);
            $is_established_like = in_array($status, ['established', '試合確定'], true);
            // line2 は相手チーム名＋会場。ステータスに関わらず「相手チーム名」に対する会場を出すため、
            // selected_place は相手チーム視点で解決する（teamA｜アウェイ のような逆転表示を防ぐ）。
            if ($selected_place && in_array($selected_place, ['home', 'away', 'either'], true)) {
                $place_viewer_team_id = (int) $current_user_team_id;
                if ((int) $other_team_id > 0) {
                    $place_viewer_team_id = (int) $other_team_id;
                }
                $place_for_schedule = function_exists('aidunite_resolve_place_for_viewer')
                    ? aidunite_resolve_place_for_viewer($selected_place, $from_team_id, $to_team_id, $place_viewer_team_id)
                    : $selected_place;
                $place_display = function_exists('aidunite_jp_place') ? aidunite_jp_place($place_for_schedule) : $place_for_schedule;
            } else {
                $place_source_sid = 0;
                if ($is_pending_like && $my_id > 0) {
                    $place_source_sid = $my_id;
                } else {
                    $place_source_sid = (int) $other_schedule_id;
                }
                $place_src_bundle = ($place_source_sid > 0 && function_exists('aidunite_schedule_get_display_bundle'))
                    ? aidunite_schedule_get_display_bundle($place_source_sid)
                    : [];
                $other_place = $place_source_sid
                    ? (function_exists('aidunite_schedule_read_place_raw')
                        ? aidunite_schedule_read_place_raw($place_source_sid)
                        : '')
                    : '';
                $venue_name = $place_source_sid ? (string) ($place_src_bundle['venue_name'] ?? get_post_meta($place_source_sid, 'venue_name', true)) : '';
                if (in_array($other_place, ['home', 'ホーム'], true) && !empty(trim((string) $venue_name))) {
                    $place_display = $venue_name;
                } elseif ($other_place && function_exists('aidunite_jp_place')) {
                    $place_display = aidunite_jp_place($other_place);
                } elseif ($other_place) {
                    $place_display = $other_place;
                }
            }

            $time_display = '—';
            $selected_start = (string) ($mr_row['selected_start_time'] ?? get_post_meta($req->ID, 'selected_start_time', true));
            $selected_end = (string) ($mr_row['selected_end_time'] ?? get_post_meta($req->ID, 'selected_end_time', true));
            if (!empty($selected_start) && !empty($selected_end)) {
                $s = $selected_start;
                $e = $selected_end;
                $time_display = trim(($s ?: '') . '–' . ($e ?: ''));
            } elseif ($other_schedule_id) {
                $other_time_bundle = function_exists('aidunite_schedule_get_display_bundle')
                    ? aidunite_schedule_get_display_bundle((int) $other_schedule_id)
                    : [];
                $other_time_api = function_exists('aidunite_schedule_get_api_display_fields')
                    ? aidunite_schedule_get_api_display_fields((int) $other_schedule_id)
                    : [];
                $s = (string) ($other_time_bundle['start_time'] ?? $other_time_api['start_time'] ?? '');
                $e = (string) ($other_time_bundle['end_time'] ?? $other_time_api['end_time'] ?? '');
                $time_display = trim(($s ?: '') . '–' . ($e ?: ''));
            }
            if ($time_display === '–' || $time_display === '') {
                $anchor_time_bundle = function_exists('aidunite_schedule_get_display_bundle')
                    ? aidunite_schedule_get_display_bundle($schedule_id)
                    : [];
                $anchor_time_api = function_exists('aidunite_schedule_get_api_display_fields')
                    ? aidunite_schedule_get_api_display_fields((int) $schedule_id)
                    : [];
                $s = (string) ($anchor_time_bundle['start_time'] ?? $anchor_time_api['start_time'] ?? '');
                $e = (string) ($anchor_time_bundle['end_time'] ?? $anchor_time_api['end_time'] ?? '');
                $time_display = trim(($s ?: '') . '–' . ($e ?: ''));
            }
            if ($time_display === '' || $time_display === '–') {
                $time_display = '—';
            }

            $view = function_exists('aidunite_resolve_match_request_view_state')
                ? aidunite_resolve_match_request_view_state($req, (int) $current_user_team_id)
                : null;
            $view_code = (string) ($view['display_code'] ?? '');
            $view_label = (string) ($view['display_label'] ?? '');
            $requires_reconfirm = !empty($view['requires_reconfirm']);
            $proposal_pending_accept = !empty($view['proposal_pending_accept']);

            $cta_type = 'badge';
            if ($proposal_pending_accept) {
                $cta_type = 'badge';
            } elseif ($requires_reconfirm) {
                $cta_type = $is_requester ? 'reapply' : 'badge';
            } elseif (in_array($view_code, ['slots_full', 'gender_slots_full'], true)) {
                // 満杯後の pending は再申請不可（同一募集）。取り下げのみ。
                $cta_type = $is_requester ? 'cancel_apply' : 'badge';
            } elseif (in_array($view_code, ['gender_conflict', 'invalid_closed', 'venue_conflict'], true)) {
                $cta_type = $is_requester ? 'badge' : 'badge';
            } elseif ($view_code === 'proposal_possible') {
                $cta_type = $is_requester ? 'reapply' : 'badge';
            } elseif (in_array($view_code, ['established', 'accepted'], true)) {
                $cta_type = 'cancel';
            } elseif ($view_code === 'pending') {
                $cta_type = $is_requester ? 'cancel_apply' : 'approve_reject';
            } elseif (in_array($view_code, ['canceled', 'canceled_opponent', 'rejected'], true)) {
                $cta_type = 'reapply';
            }

            if (function_exists('aidunite_match_request_counts_as_guest_commitment')
                && aidunite_match_request_counts_as_guest_commitment((int) $req->ID)) {
                $badge_label = '試合確定';
            } elseif ($view_code === 'pending') {
                $badge_label = $is_requester ? '申請中' : '承認待ち';
            } elseif ($view_label !== '') {
                $badge_label = $view_label;
            } else {
                $badge_label = function_exists('get_match_status_label') ? get_match_status_label($status, 'text') : $status;
            }

            $sort_order = 4;
            if (in_array($status, ['established', '試合確定'], true)) {
                $sort_order = 1;
            } elseif (in_array($status, ['accepted', '承認済み'], true)) {
                $sort_order = 2;
            } elseif (in_array($status, ['publish', 'pending', '申請中'], true)) {
                $sort_order = 3;
            }

            $post_match_survey = null;
            if (in_array($status, ['established', '試合確定'], true) && function_exists('aidunite_match_board_read_post_match_survey_payload')) {
                $post_match_survey = aidunite_match_board_read_post_match_survey_payload((int) $req->ID, $current_user_team_id);
            } elseif (in_array($status, ['established', '試合確定'], true) && function_exists('aidunite_resolve_post_match_survey_cta')) {
                $post_match_survey = aidunite_resolve_post_match_survey_cta((int) $req->ID, $current_user_team_id);
            }

            $out[] = [
                'request_id'           => (int) $req->ID,
                'team_name'            => $team_name,
                'gender_display'       => $gender_display,
                'gender_slot'          => $gender_slot,
                'time_display'         => $time_display,
                'place_display'        => $place_display,
                'status'               => $status,
                'cta_type'             => $cta_type,
                'other_schedule_id'    => (int) $other_schedule_id,
                'is_requester'         => $is_requester,
                'badge_label'          => $badge_label,
                'sort_order'           => $sort_order,
                'post_match_survey'    => $post_match_survey,
            ];
        }

        usort($out, function ($a, $b) {
            if ($a['sort_order'] !== $b['sort_order']) {
                return $a['sort_order'] - $b['sort_order'];
            }
            return 0;
        });

        return apply_filters(
            'aidunite_established_requests_for_schedule',
            $out,
            $schedule_id,
            $current_user_team_id,
            $args
        );
    }
}
