<?php
/**
 * マッチ申請の統一判定（提案型・一覧/詳細/保存共通）
 * docs/spec/match-apply-evaluation.md
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('aidunite_market_build_schedule_array')) {
    $axis = dirname(__FILE__) . '/match-board-market-axis.php';
    if (is_readable($axis)) {
        require_once $axis;
    }
}

/** @return array<string,string> aidunite_market_build_schedule_array と同形（同ファイル未読込時のみ） */
if (!function_exists('_aidunite_match_apply_build_schedule_array')) {
    function _aidunite_match_apply_build_schedule_array($schedule_id) {
        if (function_exists('aidunite_market_build_schedule_array')) {
            return aidunite_market_build_schedule_array((int) $schedule_id);
        }
        $schedule_id = (int) $schedule_id;
        $bundle = function_exists('aidunite_schedule_get_display_bundle')
            ? aidunite_schedule_get_display_bundle($schedule_id)
            : [];
        $api = function_exists('aidunite_schedule_get_api_display_fields')
            ? aidunite_schedule_get_api_display_fields($schedule_id)
            : [];
        $gender = function_exists('aidunite_schedule_read_gender_raw')
            ? aidunite_schedule_read_gender_raw($schedule_id)
            : (string) ($api['gender'] ?? '');
        $place = function_exists('aidunite_schedule_read_place_raw')
            ? aidunite_schedule_read_place_raw($schedule_id)
            : (string) ($api['place'] ?? '');
        $date = function_exists('aidunite_schedule_read_normalized_date')
            ? aidunite_schedule_read_normalized_date($schedule_id)
            : (string) ($api['date'] ?? '');
        return [
            'schedule_date' => $date,
            'start'         => (string) ($bundle['start_time'] ?? $api['start_time'] ?? ''),
            'end'           => (string) ($bundle['end_time'] ?? $api['end_time'] ?? ''),
            'gender'        => function_exists('aidunite_normalize_gender_for_match_score')
                ? aidunite_normalize_gender_for_match_score($gender)
                : (string) $gender,
            'place'         => $place,
        ];
    }
}

if (!function_exists('aidunite_compute_schedule_pair_overlap_times')) {
    /**
     * 2つの schedule の重なり時間帯（H:i）。申請時の selected_* の正とする。
     *
     * @param int $schedule_a_id
     * @param int $schedule_b_id
     * @return array{0:string,1:string} [start, end] 空文字=重なりなし
     */
    function aidunite_compute_schedule_pair_overlap_times($schedule_a_id, $schedule_b_id) {
        $a = _aidunite_match_apply_build_schedule_array((int) $schedule_a_id);
        $b = _aidunite_match_apply_build_schedule_array((int) $schedule_b_id);
        $a_start = (string) ($a['start'] ?? '');
        $a_end   = (string) ($a['end'] ?? '');
        $b_start = (string) ($b['start'] ?? '');
        $b_end   = (string) ($b['end'] ?? '');
        if ($a_start === '' || $a_end === '' || $b_start === '' || $b_end === '') {
            return ['', ''];
        }
        $a_s = strtotime('1970-01-01 ' . $a_start);
        $a_e = strtotime('1970-01-01 ' . $a_end);
        $b_s = strtotime('1970-01-01 ' . $b_start);
        $b_e = strtotime('1970-01-01 ' . $b_end);
        if (!$a_s || !$a_e || !$b_s || !$b_e) {
            return ['', ''];
        }
        $start = max($a_s, $b_s);
        $end   = min($a_e, $b_e);
        if ($start >= $end) {
            return ['', ''];
        }

        return [date('H:i', $start), date('H:i', $end)];
    }
}

if (!function_exists('_aidunite_match_apply_recruit_gender_for_evaluation')) {
    /**
     * 募集側 schedule のマッチ判定用性別（MVP: 男子 / 女子ラベルのみ。both は空扱い）。
     */
    function _aidunite_match_apply_recruit_gender_for_evaluation($recruit_schedule_id) {
        $recruit_schedule_id = (int) $recruit_schedule_id;
        $fallback = function_exists('aidunite_schedule_read_gender_raw')
            ? aidunite_schedule_read_gender_raw($recruit_schedule_id)
            : '';
        if (function_exists('aidunite_mvp_gender_raw_is_both_legacy') && aidunite_mvp_gender_raw_is_both_legacy($fallback)) {
            if (function_exists('aidunite_mvp_log_both_gender_excluded')) {
                aidunite_mvp_log_both_gender_excluded('match_apply_recruit', $recruit_schedule_id);
            }
            return '';
        }

        return ($fallback !== '' && $fallback !== null && function_exists('aidunite_normalize_gender_for_match_score'))
            ? aidunite_normalize_gender_for_match_score($fallback)
            : trim((string) $fallback);
    }
}

if (!function_exists('_aidunite_match_apply_normalize_schedule_date')) {
    /**
     * schedule_date の表記ゆれ（2026-5-4 vs 2026-05-04 等）を揃える
     */
    function _aidunite_match_apply_normalize_schedule_date($d) {
        if ($d === '' || $d === null) {
            return '';
        }
        $t = strtotime((string) $d);
        return ($t && $t > 0) ? date('Y-m-d', $t) : '';
    }
}

if (!function_exists('_aidunite_match_apply_empty_result')) {
    /**
     * @return array<string,mixed>
     */
    function _aidunite_match_apply_empty_result() {
        return [
            'can_apply'     => false,
            'variant'       => 'ineligible',
            'reason_code'   => '',
            'message'       => '',
            'list_row'      => 'ineligible',
            'proposal'      => null,
            'proposals'     => [],
            'overlap_minutes' => 0,
            'time_level'    => '',
            'place_level'   => '',
            'blocking_codes' => [],
            'requires_place_selection' => false,
            'default_selected_place'   => '',
            'board_tier'               => '',
            'scores'                   => ['T' => 0, 'V' => 0, 'A' => 0],
        ];
    }
}

if (!function_exists('_aidunite_match_apply_finish_preview')) {
    /**
     * preview 戻り値に board_tier / scores を付与
     *
     * @param array $out
     * @param int   $recruit_schedule_id
     * @param int   $applicant_schedule_id
     * @param int   $viewer_team_id
     * @return array
     */
    function _aidunite_match_apply_finish_preview(array $out, $recruit_schedule_id, $applicant_schedule_id, $viewer_team_id) {
        if (!function_exists('aidunite_match_board_tier_from_evaluation')) {
            return $out;
        }
        $pack = aidunite_match_board_tier_from_evaluation(
            $out,
            (int) $recruit_schedule_id,
            (int) $applicant_schedule_id,
            (int) $viewer_team_id
        );
        $out['board_tier'] = (string) ($pack['board_tier'] ?? '');
        $out['scores'] = is_array($pack['scores'] ?? null) ? $pack['scores'] : ['T' => 0, 'V' => 0, 'A' => 0];
        if (($out['board_tier'] ?? '') === 'no_preference') {
            $out['list_row'] = 'no_preference';
        } elseif (in_array((string) ($out['board_tier'] ?? ''), ['best', 'green'], true)) {
            $out['list_row'] = 'green';
        } elseif (($out['board_tier'] ?? '') === 'yellow') {
            $out['list_row'] = 'yellow';
        }

        return $out;
    }
}

if (!function_exists('_aidunite_match_apply_places_complementary')) {
    /**
     * 実効会場（正規化済み）が対戦として両立するか（either を含む）
     */
    function _aidunite_match_apply_places_complementary($applicant_place, $recruit_place) {
        if (!function_exists('aidunite_normalize_place_for_lock')) {
            return false;
        }
        $a = aidunite_normalize_place_for_lock((string) $applicant_place);
        $b = aidunite_normalize_place_for_lock((string) $recruit_place);
        if ($a === 'either' || $b === 'either') {
            return true;
        }
        return ($a === 'home' && $b === 'away') || ($a === 'away' && $b === 'home');
    }
}

if (!function_exists('_aidunite_match_apply_resolve_default_selected_place')) {
    /**
     * 申請者視点の selected_place デフォルト（either×either は空＝ユーザー選択必須）
     *
     * @param string $applicant_place_norm 正規化済み
     * @param string $recruit_place_norm   正規化済み
     * @return string home|away|'' 
     */
    function _aidunite_match_apply_resolve_default_selected_place($applicant_place_norm, $recruit_place_norm) {
        if (!function_exists('aidunite_normalize_place_for_lock')) {
            return '';
        }
        $a = aidunite_normalize_place_for_lock((string) $applicant_place_norm);
        $b = aidunite_normalize_place_for_lock((string) $recruit_place_norm);
        if ($a === 'either' && $b === 'either') {
            return '';
        }
        if ($a === 'home' && $b === 'home') {
            return 'away';
        }
        if ($a === 'away' && $b === 'away') {
            return 'home';
        }
        if ($a === 'either' && $b === 'home') {
            return 'away';
        }
        if ($a === 'either' && $b === 'away') {
            return 'home';
        }
        if (in_array($a, ['home', 'away'], true) && $b === 'either') {
            return $a;
        }
        if (($a === 'home' && $b === 'away') || ($a === 'away' && $b === 'home')) {
            return $a;
        }
        return '';
    }
}

if (!function_exists('_aidunite_match_apply_suggest_applicant_place')) {
    /**
     * スケジュール上の組み合わせが対戦にならないとき、申請側が選ぶべき selected_place を1案返す（無ければ null）
     */
    function _aidunite_match_apply_suggest_applicant_place($applicant_place_norm, $recruit_place_norm, $recruit_place_lock) {
        if (_aidunite_match_apply_places_complementary($applicant_place_norm, $recruit_place_norm)) {
            return null;
        }
        $ap = aidunite_normalize_place_for_lock((string) $applicant_place_norm);
        $rp = aidunite_normalize_place_for_lock((string) $recruit_place_norm);

        $candidates = [];
        if ($ap === 'home' && $rp === 'home') {
            // away を試し、募集側 place_lock で弾かれたときは either でワンショット申請が通るケースがある
            $candidates[] = 'away';
            $candidates[] = 'either';
        } elseif ($ap === 'away' && $rp === 'away') {
            $candidates[] = 'home';
            $candidates[] = 'either';
        }

        foreach ($candidates as $try) {
            if ($recruit_place_lock === '') {
                return $try;
            }
            if ($recruit_place_lock !== 'home' && $recruit_place_lock !== 'away') {
                return null;
            }
            if (!function_exists('aidunite_place_lock_matches_candidate')) {
                return $try;
            }
            if (aidunite_place_lock_matches_candidate($recruit_place_lock, $try)) {
                return $try;
            }
        }

        return null;
    }
}

if (!function_exists('_aidunite_match_apply_slots_ok')) {
    /**
     * 募集側の男女枠に対する申請可否（申請チームの性別 consumption）
     *
     * @param int    $recruit_schedule_id
     * @param string $applicant_gender male|female|both|''（canonical）
     */
    function _aidunite_match_apply_slots_ok($recruit_schedule_id, $applicant_gender) {
        $recruit_schedule_id = (int) $recruit_schedule_id;
        $to_male = function_exists('aidunite_get_schedule_male_slots') ? aidunite_get_schedule_male_slots($recruit_schedule_id) : (int) get_post_meta($recruit_schedule_id, 'male_slots', true);
        $to_female = function_exists('aidunite_get_schedule_female_slots') ? aidunite_get_schedule_female_slots($recruit_schedule_id) : (int) get_post_meta($recruit_schedule_id, 'female_slots', true);
        $g = strtolower(trim((string) $applicant_gender));
        if ($g === 'male' && $to_male < 1) {
            return false;
        }
        if ($g === 'female' && $to_female < 1) {
            return false;
        }
        if (($g === 'both' || $g === '') && $to_male < 1 && $to_female < 1) {
            return false;
        }

        return true;
    }
}

if (!function_exists('_aidunite_match_apply_slot_blocking_code')) {
    /**
     * validate 用: 枠不足を性別枠のみか総枠かに分類する（match-request.md 第6A節）。
     *
     * @return string '' | 'gender_slot_exhausted' | 'recruitment_slots_exhausted'
     */
    function _aidunite_match_apply_slot_blocking_code($recruit_schedule_id, $gender_for_slot) {
        $recruit_schedule_id = (int) $recruit_schedule_id;
        $to_male = function_exists('aidunite_get_schedule_male_slots') ? aidunite_get_schedule_male_slots($recruit_schedule_id) : (int) get_post_meta($recruit_schedule_id, 'male_slots', true);
        $to_female = function_exists('aidunite_get_schedule_female_slots') ? aidunite_get_schedule_female_slots($recruit_schedule_id) : (int) get_post_meta($recruit_schedule_id, 'female_slots', true);
        $g = strtolower(trim((string) $gender_for_slot));
        if (_aidunite_match_apply_slots_ok($recruit_schedule_id, $gender_for_slot)) {
            return '';
        }
        if (($g === 'both' || $g === '') && $to_male < 1 && $to_female < 1) {
            return 'recruitment_slots_exhausted';
        }
        if (($g === 'male' && $to_male < 1) || ($g === 'female' && $to_female < 1)) {
            return 'gender_slot_exhausted';
        }
        return 'recruitment_slots_exhausted';
    }
}

if (!function_exists('aidunite_match_apply_collect_validate_blocking_codes')) {
    /**
     * mode=validate と同一の入力で、失敗理由を機械可読コードの配列で返す（成立後アウトカム用）。
     *
     * @return array{eligible:bool,blocking_codes:string[],primary_reason:string,primary_message:string}
     */
    function aidunite_match_apply_collect_validate_blocking_codes($recruit_schedule_id, $applicant_schedule_id, $args = []) {
        $recruit_schedule_id = (int) $recruit_schedule_id;
        $applicant_schedule_id = (int) $applicant_schedule_id;
        $args = wp_parse_args($args, [
            'selected_place'      => '',
            'selected_gender'     => '',
            'selected_start_time' => '',
            'selected_end_time'   => '',
            'skip_time_check'     => false,
        ]);

        $empty_ok = [
            'eligible'            => true,
            'blocking_codes'      => [],
            'primary_reason'      => 'ok',
            'legacy_reason_code'  => 'ok',
            'primary_message'     => '',
        ];

        if ($recruit_schedule_id <= 0 || $applicant_schedule_id <= 0) {
            return [
                'eligible'        => false,
                'blocking_codes'  => ['invalid_input'],
                'primary_reason'  => 'invalid_input',
                'primary_message' => 'スケジュール情報が不足しています。',
            ];
        }

        $recruit_post = get_post($recruit_schedule_id);
        $app_post = get_post($applicant_schedule_id);
        if (!$recruit_post || $recruit_post->post_type !== 'schedule' || !$app_post || $app_post->post_type !== 'schedule') {
            return [
                'eligible'        => false,
                'blocking_codes'  => ['schedule_not_found'],
                'primary_reason'  => 'schedule_not_found',
                'primary_message' => 'スケジュールが見つかりません。',
            ];
        }

        $recruit_team = function_exists('aidunite_schedule_read_team_id')
            ? aidunite_schedule_read_team_id($recruit_schedule_id)
            : (int) get_post_meta($recruit_schedule_id, 'team_id', true);
        $app_team = function_exists('aidunite_schedule_read_team_id')
            ? aidunite_schedule_read_team_id($applicant_schedule_id)
            : (int) get_post_meta($applicant_schedule_id, 'team_id', true);
        if ($recruit_team > 0 && $app_team > 0 && $recruit_team === $app_team) {
            return [
                'eligible'        => false,
                'blocking_codes'  => ['same_team'],
                'primary_reason'  => 'same_team',
                'primary_message' => '同一チームへの申請はできません。',
            ];
        }

        if (function_exists('aidunite_schedule_guest_remaining') && aidunite_schedule_guest_remaining($applicant_schedule_id) < 1) {
            return [
                'eligible'            => false,
                'blocking_codes'      => ['applicant_schedule_committed'],
                'primary_reason'      => 'applicant_schedule_committed',
                'legacy_reason_code'  => 'applicant_schedule_committed',
                'primary_message'     => 'この対戦希望はすでに試合が確定しているため、新しい申請はできません。',
            ];
        }

        if (!function_exists('aidunite_recruit_accepts_match_applications')
            || !aidunite_recruit_accepts_match_applications($recruit_schedule_id)) {
            return [
                'eligible'        => false,
                'blocking_codes'  => ['recruit_closed'],
                'primary_reason'  => 'recruit_closed',
                'primary_message' => 'この募集は対戦マッチングが無効です。',
            ];
        }

        $recruit_arr = _aidunite_match_apply_build_schedule_array($recruit_schedule_id);
        $applicant_arr = _aidunite_match_apply_build_schedule_array($applicant_schedule_id);
        $recruit_arr['gender'] = _aidunite_match_apply_recruit_gender_for_evaluation($recruit_schedule_id);

        $sp = sanitize_text_field((string) $args['selected_place']);
        if ($sp !== '') {
            if ($sp === 'both') {
                $sp = 'either';
            }
            $applicant_arr['place'] = $sp;
        }
        $sg = sanitize_text_field((string) $args['selected_gender']);
        if ($sg !== '') {
            $applicant_arr['gender'] = function_exists('aidunite_normalize_gender_for_match_score')
                ? aidunite_normalize_gender_for_match_score($sg)
                : $sg;
        }
        $sst = sanitize_text_field((string) $args['selected_start_time']);
        $sen = sanitize_text_field((string) $args['selected_end_time']);
        if ($sst !== '' && $sen !== '') {
            $applicant_arr['start'] = $sst;
            $applicant_arr['end'] = $sen;
        }

        $blocking = [];

        $rd = _aidunite_match_apply_normalize_schedule_date($recruit_arr['schedule_date'] ?? '');
        $ad = _aidunite_match_apply_normalize_schedule_date($applicant_arr['schedule_date'] ?? '');
        if ($rd === '' || $rd !== $ad) {
            $blocking[] = 'date_mismatch';
        }

        if (function_exists('aidunite_check_gender_compatibility')) {
            $gc = aidunite_check_gender_compatibility($applicant_arr['gender'], $recruit_arr['gender']);
            if (empty($gc['is_compatible'])) {
                $blocking[] = 'gender_conflict';
            }
        }

        if (!$args['skip_time_check'] && function_exists('aidunite_get_time_level')) {
            $time_level_row = aidunite_get_time_level(
                $applicant_arr['start'],
                $applicant_arr['end'],
                $recruit_arr['start'],
                $recruit_arr['end']
            );
            if (!$time_level_row) {
                $blocking[] = 'time_no_overlap';
            }
        }

        $mn = function_exists('aidunite_get_match_label_new') ? aidunite_get_match_label_new($applicant_arr, $recruit_arr) : null;
        if (!$mn) {
            $blocking[] = 'match_label_failed';
        }

        $recruit_lock = function_exists('aidunite_get_schedule_place_lock') ? aidunite_get_schedule_place_lock($recruit_schedule_id) : '';
        $app_place_meta = function_exists('aidunite_schedule_read_place_raw')
            ? aidunite_schedule_read_place_raw($applicant_schedule_id)
            : '';
        $rec_place_meta = function_exists('aidunite_schedule_read_place_raw')
            ? aidunite_schedule_read_place_raw($recruit_schedule_id)
            : '';
        $app_place_norm = function_exists('aidunite_normalize_place_for_lock')
            ? aidunite_normalize_place_for_lock($app_place_meta)
            : strtolower(trim((string) $app_place_meta));
        $rec_place_norm = function_exists('aidunite_normalize_place_for_lock')
            ? aidunite_normalize_place_for_lock($rec_place_meta)
            : strtolower(trim((string) $rec_place_meta));
        $effective_applicant_place = $app_place_norm;
        if ($args['selected_place'] !== '') {
            $effective_applicant_place = aidunite_normalize_place_for_lock($args['selected_place']);
        }
        $applicant_place_lock = function_exists('aidunite_get_schedule_place_lock') ? aidunite_get_schedule_place_lock($applicant_schedule_id) : '';
        $applicant_lock_ok = true;
        if ($applicant_place_lock !== '' && function_exists('aidunite_place_lock_matches_candidate')) {
            $applicant_lock_ok = aidunite_place_lock_matches_candidate($applicant_place_lock, $rec_place_meta);
        }
        $recruit_lock_ok = true;
        if ($recruit_lock !== '' && function_exists('aidunite_place_lock_matches_candidate')) {
            $recruit_lock_ok = aidunite_place_lock_matches_candidate($recruit_lock, $effective_applicant_place);
        }

        $gender_for_slot = function_exists('aidunite_get_schedule_gender') ? aidunite_get_schedule_gender($applicant_schedule_id) : '';
        if ($args['selected_gender'] !== '') {
            $gender_for_slot = sanitize_text_field($args['selected_gender']);
        }
        $gender_for_slot = strtolower(trim((string) $gender_for_slot));

        $remaining = function_exists('aidunite_get_remaining_gender_slots')
            ? aidunite_get_remaining_gender_slots($recruit_schedule_id)
            : ['male' => 1, 'female' => 1];
        $slot_ok = true;
        $recruit_gender_raw = function_exists('aidunite_get_schedule_gender') ? aidunite_get_schedule_gender($recruit_schedule_id) : '';
        if ($recruit_gender_raw === 'male') {
            $slot_ok = ($remaining['male'] > 0);
        } elseif ($recruit_gender_raw === 'female') {
            $slot_ok = ($remaining['female'] > 0);
        } elseif ($recruit_gender_raw === 'both' || $recruit_gender_raw === '') {
            $slot_ok = ($remaining['male'] > 0 || $remaining['female'] > 0);
        }

        $slot_code = _aidunite_match_apply_slot_blocking_code($recruit_schedule_id, $gender_for_slot);
        if ($slot_code !== '') {
            $blocking[] = $slot_code;
        } elseif (!$slot_ok) {
            $blocking[] = 'recruitment_slots_exhausted';
        }

        if (!$applicant_lock_ok) {
            $blocking[] = 'venue_self_lock';
        }
        if (!$recruit_lock_ok) {
            $blocking[] = 'venue_recruit_lock';
        }
        if (! _aidunite_match_apply_places_complementary($effective_applicant_place, $rec_place_norm)) {
            $blocking[] = 'venue_not_complementary';
        }

        // 双方 either：selected_place は home/away の明示が必須（スケジュール either のまま不可）
        if ($app_place_norm === 'either' && $rec_place_norm === 'either') {
            $chosen = $effective_applicant_place;
            if ($chosen === 'either' || !in_array($chosen, ['home', 'away'], true)) {
                $blocking[] = 'place_selection_required';
            }
        }

        $messages = [
            'applicant_schedule_committed'   => 'この対戦希望はすでに試合が確定しているため、新しい申請はできません。',
            'date_mismatch'                  => '同日の対戦希望同士のみ申請できます。',
            'gender_conflict'                => '性別条件が一致しないため申請できません。',
            'time_no_overlap'                => '時間帯が重ならないため申請できません。',
            'match_label_failed'             => '条件が一致しないため申請できません。',
            'gender_slot_exhausted'          => '該当性別枠が満了しています。',
            'recruitment_slots_exhausted'    => '募集枠が満了しています。',
            'venue_self_lock'                => '自チームの会場ロックと募集条件が一致しません。',
            'venue_recruit_lock'             => '現在の募集の会場条件と申請内容が一致しません。',
            'venue_not_complementary'        => '会場の組み合わせが対戦として成立しません。',
            'place_selection_required'       => '双方が「どちらでも可」のため、ホームまたはアウェイを選んで申請してください。',
        ];

        if ($blocking === []) {
            return $empty_ok;
        }

        $prio = ['applicant_schedule_committed', 'gender_conflict', 'recruitment_slots_exhausted', 'gender_slot_exhausted', 'place_selection_required', 'venue_self_lock', 'venue_not_complementary', 'venue_recruit_lock', 'time_no_overlap', 'date_mismatch', 'match_label_failed'];
        $blocking = array_values(array_unique($blocking));
        usort($blocking, static function ($a, $b) use ($prio) {
            $ia = array_search($a, $prio, true);
            $ib = array_search($b, $prio, true);
            $ia = ($ia === false) ? 999 : $ia;
            $ib = ($ib === false) ? 999 : $ib;
            return $ia <=> $ib;
        });
        $primary = $blocking[0];
        $legacy_map = [
            'applicant_schedule_committed' => 'applicant_schedule_committed',
            'gender_conflict'             => 'gender_conflict',
            'gender_slot_exhausted'       => 'gender_slots_full',
            'recruitment_slots_exhausted' => 'slots_full',
            'venue_self_lock'             => 'venue_conflict',
            'venue_recruit_lock'          => 'venue_conflict',
            'venue_not_complementary'     => 'venue_conflict',
            'time_no_overlap'             => 'time_no_overlap',
            'date_mismatch'               => 'date_mismatch',
            'match_label_failed'          => 'match_label_failed',
            'place_selection_required'    => 'place_selection_required',
        ];
        $legacy_reason = isset($legacy_map[$primary]) ? $legacy_map[$primary] : 'invalid_input';

        return [
            'eligible'            => false,
            'blocking_codes'      => $blocking,
            'primary_reason'      => $primary,
            'legacy_reason_code'  => $legacy_reason,
            'primary_message'     => (string) ($messages[$primary] ?? '申請できません。'),
        ];
    }
}

if (!function_exists('aidunite_match_request_project_outcome_from_blocking_codes')) {
    /**
     * match-request.md 第6A.4 に準じた畳み込み（blocking_codes は優先度順に並んでいる前提）。
     *
     * @param string[] $blocking_codes
     * @return string ユーザー向け outcome コード
     */
    function aidunite_match_request_project_outcome_from_blocking_codes(array $blocking_codes) {
        $codes = array_values(array_unique($blocking_codes));
        if ($codes === []) {
            return 'keep_pending';
        }
        $has = static function ($c) use ($codes) {
            return in_array($c, $codes, true);
        };
        if ($has('applicant_schedule_committed')) {
            return 'invalid_closed';
        }
        if ($has('gender_conflict')) {
            return 'gender_conflict';
        }
        if ($has('recruitment_slots_exhausted')) {
            return 'slots_full';
        }
        if ($has('gender_slot_exhausted')) {
            return 'gender_slots_full';
        }
        if ($has('venue_self_lock')) {
            return 'invalid_closed';
        }
        if ($has('venue_not_complementary') || $has('venue_recruit_lock')) {
            return 'proposal_possible';
        }
        if ($has('time_no_overlap') || $has('date_mismatch') || $has('match_label_failed')) {
            return 'invalid_closed';
        }
        return 'reconfirm_required';
    }
}

if (!function_exists('aidunite_evaluate_match_apply_context')) {
    /**
     * @param int   $recruit_schedule_id  申請先（募集）schedule ID
     * @param int   $applicant_schedule_id 申請者側 schedule ID
     * @param array $args mode, selected_*
     * @return array<string,mixed>
     */
    function aidunite_evaluate_match_apply_context($recruit_schedule_id, $applicant_schedule_id, $args = []) {
        $out = _aidunite_match_apply_empty_result();

        $recruit_schedule_id = (int) $recruit_schedule_id;
        $applicant_schedule_id = (int) $applicant_schedule_id;
        $args = wp_parse_args($args, [
            'mode'                 => 'preview',
            'selected_place'       => '',
            'selected_gender'      => '',
            'selected_start_time'  => '',
            'selected_end_time'    => '',
            'skip_time_check'      => false,
        ]);

        if ($recruit_schedule_id <= 0 || $applicant_schedule_id <= 0) {
            $out['reason_code'] = 'invalid_input';
            $out['message'] = 'スケジュール情報が不足しています。';
            return $out;
        }

        $recruit_post = get_post($recruit_schedule_id);
        $app_post = get_post($applicant_schedule_id);
        if (!$recruit_post || $recruit_post->post_type !== 'schedule' || !$app_post || $app_post->post_type !== 'schedule') {
            $out['reason_code'] = 'schedule_not_found';
            $out['message'] = 'スケジュールが見つかりません。';
            return $out;
        }

        $recruit_team = function_exists('aidunite_schedule_read_team_id')
            ? aidunite_schedule_read_team_id($recruit_schedule_id)
            : (int) get_post_meta($recruit_schedule_id, 'team_id', true);
        $app_team = function_exists('aidunite_schedule_read_team_id')
            ? aidunite_schedule_read_team_id($applicant_schedule_id)
            : (int) get_post_meta($applicant_schedule_id, 'team_id', true);
        if ($recruit_team > 0 && $app_team > 0 && $recruit_team === $app_team) {
            $out['reason_code'] = 'same_team';
            $out['message'] = '同一チームへの申請はできません。';
            return $out;
        }

        if (!function_exists('aidunite_recruit_accepts_match_applications')
            || !aidunite_recruit_accepts_match_applications($recruit_schedule_id)) {
            $out['reason_code'] = 'recruit_closed';
            $out['message'] = 'この募集は対戦マッチングが無効です。';
            $out['variant'] = 'ineligible';
            return $out;
        }

        if (function_exists('aidunite_schedule_guest_remaining') && aidunite_schedule_guest_remaining($applicant_schedule_id) < 1) {
            $out['can_apply']     = false;
            $out['variant']       = 'ineligible';
            $out['list_row']      = 'ineligible';
            $out['reason_code']   = 'applicant_schedule_committed';
            $out['message']       = 'この対戦希望はすでに試合が確定しているため、新しい申請はできません。';
            $out['blocking_codes'] = ['applicant_schedule_committed'];
            return $out;
        }

        $recruit_arr = _aidunite_match_apply_build_schedule_array($recruit_schedule_id);
        $applicant_arr = _aidunite_match_apply_build_schedule_array($applicant_schedule_id);
        $recruit_arr['gender'] = _aidunite_match_apply_recruit_gender_for_evaluation($recruit_schedule_id);

        if ($args['mode'] === 'validate') {
            $collected = aidunite_match_apply_collect_validate_blocking_codes($recruit_schedule_id, $applicant_schedule_id, $args);
            $out['blocking_codes'] = $collected['blocking_codes'];
            if (!$collected['eligible']) {
                $out['can_apply'] = false;
                $out['variant'] = 'ineligible';
                $out['list_row'] = 'ineligible';
                $out['reason_code'] = isset($collected['legacy_reason_code']) ? $collected['legacy_reason_code'] : 'invalid_input';
                $out['message'] = $collected['primary_message'];
                return $out;
            }
            $out['can_apply'] = true;
            $out['variant'] = 'direct';
            $out['reason_code'] = 'ok';
            $out['message'] = '';
            $out['list_row'] = 'green';
            $out['blocking_codes'] = [];
            return $out;
        }

        // 日付（表記ゆれを正規化して比較）
        $rd = _aidunite_match_apply_normalize_schedule_date($recruit_arr['schedule_date'] ?? '');
        $ad = _aidunite_match_apply_normalize_schedule_date($applicant_arr['schedule_date'] ?? '');
        if ($rd === '' || $rd !== $ad) {
            $out['reason_code'] = 'date_mismatch';
            $out['message'] = '同日の対戦希望同士のみ申請できます。';
            $out['list_row'] = 'ineligible';
            return $out;
        }

        // 性別ハード
        if (function_exists('aidunite_check_gender_compatibility')) {
            $gc = aidunite_check_gender_compatibility($applicant_arr['gender'], $recruit_arr['gender']);
            if (empty($gc['is_compatible'])) {
                $out['reason_code'] = 'gender_conflict';
                $out['message'] = '性別条件が一致しないため申請できません。';
                $out['list_row'] = 'ineligible';
                return $out;
            }
        }

        // 時間（プレビューではこの結果をラベル欠落時のフォールバックにも使う）
        $time_level_row = null;
        if (!$args['skip_time_check'] && function_exists('aidunite_get_time_level')) {
            $time_level_row = aidunite_get_time_level(
                $applicant_arr['start'],
                $applicant_arr['end'],
                $recruit_arr['start'],
                $recruit_arr['end']
            );
            if (!$time_level_row) {
                $out['reason_code'] = 'time_no_overlap';
                $out['message'] = '時間帯が重ならないため申請できません。';
                if (($args['mode'] ?? '') === 'preview') {
                    $out['list_row'] = 'no_preference';
                    $out['variant'] = 'direct';
                    $out['can_apply'] = false;

                    return _aidunite_match_apply_finish_preview($out, $recruit_schedule_id, $applicant_schedule_id, $app_team);
                }
                $out['list_row'] = 'ineligible';

                return $out;
            }
        }

        $mn = function_exists('aidunite_get_match_label_new') ? aidunite_get_match_label_new($applicant_arr, $recruit_arr) : null;
        if (
            !$mn
            && ($args['mode'] ?? '') === 'preview'
            && !empty($time_level_row)
        ) {
            // 会場表記の不一致等で aidunite_get_match_label_new が null でも、時間重複があれば提案型の会場判定へ進める
            $fallback_place_level = 'PLACE_MEDIUM';
            if (function_exists('aidunite_get_place_level')) {
                $pl_fb = aidunite_get_place_level($applicant_arr['place'] ?? '', $recruit_arr['place'] ?? '');
                if (is_array($pl_fb) && !empty($pl_fb['level'])) {
                    $fallback_place_level = (string) $pl_fb['level'];
                }
            }
            $mn = [
                'label'           => 'マッチ',
                'time_level'      => (string) ($time_level_row['level'] ?? 'TIME_OK'),
                'place_level'     => $fallback_place_level,
                'overlap_minutes' => (int) ($time_level_row['overlap_minutes'] ?? 0),
                'gender_mixed'    => false,
            ];
        }
        if (!$mn) {
            $out['reason_code'] = 'match_label_failed';
            $out['message'] = '条件が一致しないため申請できません。';
            $out['list_row'] = 'ineligible';
            return $out;
        }

        $out['overlap_minutes'] = isset($mn['overlap_minutes']) ? (int) $mn['overlap_minutes'] : 0;
        $out['time_level'] = (string) ($mn['time_level'] ?? '');
        $out['place_level'] = (string) ($mn['place_level'] ?? '');

        $recruit_lock = function_exists('aidunite_get_schedule_place_lock') ? aidunite_get_schedule_place_lock($recruit_schedule_id) : '';

        $app_place_meta = function_exists('aidunite_schedule_read_place_raw')
            ? aidunite_schedule_read_place_raw($applicant_schedule_id)
            : '';
        $rec_place_meta = function_exists('aidunite_schedule_read_place_raw')
            ? aidunite_schedule_read_place_raw($recruit_schedule_id)
            : '';

        $app_place_norm = function_exists('aidunite_normalize_place_for_lock')
            ? aidunite_normalize_place_for_lock($app_place_meta)
            : strtolower(trim((string) $app_place_meta));
        $rec_place_norm = function_exists('aidunite_normalize_place_for_lock')
            ? aidunite_normalize_place_for_lock($rec_place_meta)
            : strtolower(trim((string) $rec_place_meta));
        // schedule_place と place_option の食い違い（表示は either・DB が home）を補正
        if ($app_place_norm === 'home' && function_exists('aidunite_normalize_place_for_lock')) {
            $place_opt = function_exists('aidunite_schedule_read_place_raw')
                ? aidunite_schedule_read_place_raw($applicant_schedule_id)
                : '';
            if (aidunite_normalize_place_for_lock($place_opt) === 'either') {
                $app_place_norm = 'either';
            }
        }

        // either は申請時に home/away へ解決されるため、ロック・ペア判定は解決後 place を優先
        $app_place_for_pair = $app_place_norm;
        if ($app_place_norm === 'either') {
            $resolved_place = _aidunite_match_apply_resolve_default_selected_place($app_place_norm, $rec_place_norm);
            if ($resolved_place !== '') {
                $app_place_for_pair = $resolved_place;
            }
        }
        $effective_applicant_place = $app_place_for_pair;

        $applicant_place_lock = function_exists('aidunite_get_schedule_place_lock') ? aidunite_get_schedule_place_lock($applicant_schedule_id) : '';

        // 掲示板と同じ: 自スケの place_lock と募集側の会場（正規化済み）
        $applicant_lock_ok = true;
        if ($applicant_place_lock !== '' && function_exists('aidunite_place_lock_matches_candidate')) {
            $applicant_lock_ok = aidunite_place_lock_matches_candidate($applicant_place_lock, $rec_place_norm);
        }

        // 募集側ロックと「申請側が提示する開催形式」
        $recruit_lock_ok = true;
        if ($recruit_lock !== '' && function_exists('aidunite_place_lock_matches_candidate')) {
            $recruit_lock_ok = aidunite_place_lock_matches_candidate($recruit_lock, $app_place_for_pair);
        }

        // 枠: 募集 schedule の残り（設計どおり recruit 側メタ）
        $gender_for_slot = function_exists('aidunite_get_schedule_gender') ? aidunite_get_schedule_gender($applicant_schedule_id) : '';
        $gender_for_slot = strtolower(trim((string) $gender_for_slot));

        $remaining = function_exists('aidunite_get_remaining_gender_slots')
            ? aidunite_get_remaining_gender_slots($recruit_schedule_id)
            : ['male' => 1, 'female' => 1];
        $slot_ok = true;
        $recruit_gender_raw = function_exists('aidunite_get_schedule_gender') ? aidunite_get_schedule_gender($recruit_schedule_id) : '';
        if ($recruit_gender_raw === 'male') {
            $slot_ok = ($remaining['male'] > 0);
        } elseif ($recruit_gender_raw === 'female') {
            $slot_ok = ($remaining['female'] > 0);
        } elseif ($recruit_gender_raw === 'both' || $recruit_gender_raw === '') {
            $slot_ok = ($remaining['male'] > 0 || $remaining['female'] > 0);
        }

        if (!_aidunite_match_apply_slots_ok($recruit_schedule_id, $gender_for_slot)) {
            $slot_ok = false;
        }

        $time_strong = ($out['time_level'] === 'TIME_STRONG');
        $time_ok = ($out['time_level'] === 'TIME_OK');
        $place_easy = ($out['place_level'] === 'PLACE_EASY');
        // either×ホーム（主催募集の残枠）は会場 EASY 扱い（ラベル取得が home×home で HEAVY になる取りこぼし防止）
        if (!$place_easy && $app_place_norm === 'either' && $rec_place_norm === 'home') {
            $place_easy = true;
            $out['place_level'] = 'PLACE_EASY';
        }

        // --- preview ---
        $complementary = _aidunite_match_apply_places_complementary($app_place_for_pair, $rec_place_norm);
        $pair_ok = $complementary && $recruit_lock_ok;
        $host_recruit_with_slots = ($rec_place_norm === 'home' && $slot_ok);
        $applicant_to_host_slot = ($app_place_norm === 'either' || $app_place_for_pair === 'away');

        $proposal_place = _aidunite_match_apply_suggest_applicant_place($app_place_norm, $rec_place_norm, $recruit_lock);

        // 主催ホーム×申請 either → 提案黄に落とさず緑（B→A の 5691×5702）
        if ($host_recruit_with_slots && $applicant_to_host_slot && $place_easy && ($time_strong || $time_ok)
            && $applicant_lock_ok) {
            $out['list_row'] = 'green';
            $out['variant'] = 'direct';
            $out['can_apply'] = true;
            $out['reason_code'] = 'ok';
            $out['message'] = '';
            $def_place = _aidunite_match_apply_resolve_default_selected_place($app_place_norm, $rec_place_norm);
            if ($def_place !== '') {
                $out['default_selected_place'] = $def_place;
            }

            return _aidunite_match_apply_finish_preview($out, $recruit_schedule_id, $applicant_schedule_id, $app_team);
        }

        if ($proposal_place === null && $complementary && ! $recruit_lock_ok && $recruit_lock !== '' && function_exists('aidunite_place_lock_matches_candidate')) {
            foreach (['away', 'home', 'either'] as $try) {
                if (aidunite_place_lock_matches_candidate($recruit_lock, $try)
                    && _aidunite_match_apply_places_complementary($try, $rec_place_norm)) {
                    $proposal_place = $try;
                    break;
                }
            }
        }

        // preview ではスケジュール上の申請者住所とロックの整合（送信前）
        $effective_preview_ok = $pair_ok && $applicant_lock_ok && $slot_ok;

        // 掲示板 🟢: TIME_STRONG 必須。either 含み PLACE_EASY かつ時間重複十分(TIME_OK)も緑（2026-05-20 会場 either 対応）
        $venue_has_either = ($app_place_norm === 'either' || $rec_place_norm === 'either');
        $time_for_list_green = $time_strong || ($venue_has_either && $time_ok && $place_easy);

        if ($effective_preview_ok && $time_for_list_green && $place_easy) {
            $out['list_row'] = 'green';
            $out['variant'] = 'direct';
            $out['can_apply'] = true;
            $out['reason_code'] = 'ok';
            $out['message'] = '';
            if ($app_place_norm === 'either' && $rec_place_norm === 'either') {
                $out['requires_place_selection'] = true;
            } else {
                $def_place = _aidunite_match_apply_resolve_default_selected_place($app_place_norm, $rec_place_norm);
                if ($def_place !== '') {
                    $out['default_selected_place'] = $def_place;
                }
            }

            return _aidunite_match_apply_finish_preview($out, $recruit_schedule_id, $applicant_schedule_id, $app_team);
        }

        if ($proposal_place && !$pair_ok) {
            $label = ($proposal_place === 'away') ? 'アウェイに変更して申請する' : (($proposal_place === 'home') ? 'ホームで申請する' : '条件を選んで申請する');
            $desc = '会場希望が重複しています。今回の申請だけ別の開催形式で送信できます（スケジュール本体は変わりません）。';
            if ($app_place_norm === 'away' && $rec_place_norm === 'away') {
                $desc = '双方ともアウェイ希望です。今回の申請ではホーム（またはどちらでも可）を選べば申請できます。募集側の条件によっては募集の編集が必要です。';
            }
            $out['variant'] = 'proposal';
            $out['reason_code'] = ($app_place_norm === 'away' && $rec_place_norm === 'away') ? 'venue_both_away' : 'venue_conflict';
            $out['proposal'] = [
                'selected_place' => $proposal_place,
                'selected_gender' => '',
                'label'          => $label,
                'description'    => $desc,
            ];
            $out['list_row'] = 'yellow';
            $out['can_apply'] = false;
            $out['message'] = $desc;

            return _aidunite_match_apply_finish_preview($out, $recruit_schedule_id, $applicant_schedule_id, $app_team);
        }

        if (!$proposal_place && !$pair_ok) {
            $out['variant'] = 'ineligible';
            $out['reason_code'] = 'venue_unfixable_without_recruit_edit';
            $out['message'] = '会場条件をそろえるには、募集側のスケジュール編集が必要です。';
            $out['list_row'] = 'ineligible';
            return $out;
        }

        // pair_ok だが簡易マッチ以外（時間・会場調整）
        if (!$slot_ok) {
            $out['reason_code'] = 'slots_full';
            $out['message'] = 'この日程の募集枠が満了です。';
            $out['variant'] = 'ineligible';
            $out['list_row'] = 'ineligible';
            return $out;
        }

        // プレビュー: スケジュール上は両立するが place_lock と募集カードの表示だけが食い違うとき、
        // 今回の申請だけ別の開催形式（selected_place）なら成立しうる → 黄・提案で一覧に残す
        if (!$applicant_lock_ok && ($args['mode'] ?? '') === 'preview') {
            foreach (['away', 'home', 'either'] as $try_sp) {
                if (!_aidunite_match_apply_places_complementary($try_sp, $rec_place_norm)) {
                    continue;
                }
                if ($recruit_lock !== '' && function_exists('aidunite_place_lock_matches_candidate')
                    && !aidunite_place_lock_matches_candidate($recruit_lock, $try_sp)) {
                    continue;
                }
                $label = ($try_sp === 'away') ? 'アウェイに変更して申請する' : (($try_sp === 'home') ? 'ホームで申請する' : '条件を選んで申請する');
                $out['variant'] = 'proposal';
                $out['reason_code'] = 'venue_conflict';
                $out['proposal'] = [
                    'selected_place'   => $try_sp,
                    'selected_gender'  => '',
                    'label'            => $label,
                    'description'      => '自チームの会場ロックと募集の表示が一致しないため、この申請だけ別の開催形式で送信できます（スケジュール本体は変わりません）。',
                ];
                $out['list_row'] = 'yellow';
                $out['can_apply'] = false;
                $out['message'] = $out['proposal']['description'];

                return _aidunite_match_apply_finish_preview($out, $recruit_schedule_id, $applicant_schedule_id, $app_team);
            }
        }

        if (!$applicant_lock_ok) {
            $out['variant'] = 'ineligible';
            $out['reason_code'] = 'venue_conflict';
            $out['message'] = '自チームの会場ロックと募集が一致しません。';
            $out['list_row'] = 'ineligible';
            return $out;
        }

        // pair_ok・枠あり・会場 EASY で時間が OK 以上 → 一覧は緑（従来ここで黄固定になっていた）
        if ($effective_preview_ok && $place_easy && ($time_strong || $time_ok)) {
            $out['variant'] = 'direct';
            $out['list_row'] = 'green';
            $out['can_apply'] = true;
            $out['reason_code'] = 'ok';
            $out['message'] = '';
            if ($app_place_norm === 'either' && $rec_place_norm === 'either') {
                $out['requires_place_selection'] = true;
            } else {
                $def_place = _aidunite_match_apply_resolve_default_selected_place($app_place_norm, $rec_place_norm);
                if ($def_place !== '') {
                    $out['default_selected_place'] = $def_place;
                }
            }

            return _aidunite_match_apply_finish_preview($out, $recruit_schedule_id, $applicant_schedule_id, $app_team);
        }

        $out['variant'] = 'direct';
        $out['list_row'] = 'yellow';
        $out['can_apply'] = true;
        $out['reason_code'] = 'ok';
        $out['message'] = '';

        return _aidunite_match_apply_finish_preview($out, $recruit_schedule_id, $applicant_schedule_id, $app_team);
    }
}
