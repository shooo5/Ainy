<?php
/**
 * 掲示板4段表示: T / V / A スコアと決定木（board_tier）
 * docs/spec/match-apply-evaluation.md, match-board.md
 */

if (!defined('ABSPATH')) {
    exit;
}

if (function_exists('aidunite_match_board_ensure_dependencies')) {
    aidunite_match_board_ensure_dependencies();
}

if (!function_exists('aidunite_match_board_tier_labels')) {
    /**
     * @return array<string,array{label:string,class:string}>
     */
    function aidunite_match_board_tier_labels() {
        return [
            'best'           => ['label' => 'ベストマッチ', 'class' => 'best'],
            'green'          => ['label' => '成立可能', 'class' => 'green'],
            'yellow'         => ['label' => '条件調整', 'class' => 'yellow'],
            'no_preference'  => ['label' => '希望未登録', 'class' => 'no_preference'],
            'hidden'         => ['label' => '', 'class' => 'hidden'],
        ];
    }
}

if (!function_exists('aidunite_match_board_tier_label_from_evaluation')) {
    /**
     * evaluate 結果から表示用ラベル（⭐/🟢/🟡/🔵）
     */
    function aidunite_match_board_tier_label_from_evaluation(array $eval) {
        $tier = (string) ($eval['board_tier'] ?? '');
        if ($tier === '') {
            $lr = (string) ($eval['list_row'] ?? '');
            if ($lr === 'no_preference') {
                $tier = 'no_preference';
            } elseif ($lr === 'yellow') {
                $tier = 'yellow';
            } elseif ($lr === 'green') {
                $tier = 'green';
            }
        }
        $labels = aidunite_match_board_tier_labels();
        if ($tier !== '' && isset($labels[$tier]['label']) && $labels[$tier]['label'] !== '') {
            return (string) $labels[$tier]['label'];
        }

        return '条件不一致';
    }
}

if (!function_exists('aidunite_match_board_tier_sort_priority')) {
    function aidunite_match_board_tier_sort_priority($tier) {
        $map = ['best' => 0, 'green' => 1, 'yellow' => 2, 'no_preference' => 3, 'hidden' => 9];

        return $map[(string) $tier] ?? 5;
    }
}

if (!function_exists('aidunite_match_board_scores_breakdown_from_evaluation')) {
    /**
     * T/V/A スコアを比較 UI 用の breakdown 形に変換（詳細ページの place フォールバック等）
     *
     * @param array|null $eval
     * @return array{date:int,time:int,gender:int,place:int,total:int,T:int,V:int,A:int}
     */
    function aidunite_match_board_scores_breakdown_from_evaluation($eval) {
        $scores = (is_array($eval) && is_array($eval['scores'] ?? null))
            ? $eval['scores']
            : ['T' => 0, 'V' => 0, 'A' => 0];
        $t = (int) ($scores['T'] ?? 0);
        $v = (int) ($scores['V'] ?? 0);
        $a = (int) ($scores['A'] ?? 0);
        $time_pts = $t >= 80 ? 2 : ($t >= 55 ? 1 : 0);
        $place_pts = $v >= 30 ? 2 : ($v >= 10 ? 1 : 0);

        return [
            'date'   => $a > 0 ? 2 : 0,
            'time'   => $time_pts,
            'gender' => 2,
            'place'  => $place_pts,
            'total'  => $t + $v + $a,
            'T'      => $t,
            'V'      => $v,
            'A'      => $a,
        ];
    }
}

if (!function_exists('aidunite_match_board_score_time')) {
    /**
     * @param array $applicant_arr
     * @param array $recruit_arr
     * @return array{score:int,level:string,overlap_minutes:int}
     */
    function aidunite_match_board_score_time(array $applicant_arr, array $recruit_arr) {
        $as = trim((string) ($applicant_arr['start'] ?? ''));
        $ae = trim((string) ($applicant_arr['end'] ?? ''));
        $rs = trim((string) ($recruit_arr['start'] ?? ''));
        $re = trim((string) ($recruit_arr['end'] ?? ''));
        if ($as !== '' && $ae !== '' && $as === $rs && $ae === $re) {
            return ['score' => 100, 'level' => 'TIME_EXACT', 'overlap_minutes' => 0];
        }
        if (!function_exists('aidunite_get_time_level')) {
            return ['score' => 0, 'level' => '', 'overlap_minutes' => 0];
        }
        $tl = aidunite_get_time_level($as, $ae, $rs, $re);
        if (!$tl) {
            return ['score' => 0, 'level' => '', 'overlap_minutes' => 0];
        }
        $level = (string) ($tl['level'] ?? '');
        $overlap = (int) ($tl['overlap_minutes'] ?? 0);
        $map = [
            'TIME_STRONG' => 80,
            'TIME_OK'     => 55,
            'TIME_WEAK'   => 35,
        ];

        return [
            'score'            => $map[$level] ?? 0,
            'level'            => $level,
            'overlap_minutes'  => $overlap,
        ];
    }
}

if (!function_exists('aidunite_match_board_score_venue')) {
    /**
     * @param string $place_level PLACE_EASY / PLACE_HEAVY / PLACE_MEDIUM
     * @param string $variant     direct|proposal|ineligible
     */
    function aidunite_match_board_score_venue($place_level, $variant = 'direct') {
        if ($variant === 'proposal') {
            return 10;
        }
        $place_level = (string) $place_level;
        if ($place_level === 'PLACE_EASY') {
            return 30;
        }

        return 10;
    }
}

if (!function_exists('aidunite_match_board_score_activity')) {
    /**
     * @param int $viewer_team_id
     * @param int $recruit_team_id
     */
    function aidunite_match_board_score_activity($viewer_team_id, $recruit_team_id) {
        if (!function_exists('aidunite_team_activity_compare')) {
            return 0;
        }
        $cmp = aidunite_team_activity_compare((int) $viewer_team_id, (int) $recruit_team_id);
        if ($cmp === 'exact') {
            return 50;
        }
        if ($cmp === 'prefecture') {
            return 25;
        }
        if ($cmp === 'out') {
            return 0;
        }

        return 25;
    }
}

if (!function_exists('aidunite_market_recruit_hidden_for_viewer_team')) {
    /**
     * 決定木 (0): 性別・競技・年代不一致 → 非表示
     *
     * @param int $recruit_schedule_id
     * @param int $viewer_team_id
     */
    function aidunite_market_recruit_hidden_for_viewer_team($recruit_schedule_id, $viewer_team_id) {
        $recruit_schedule_id = (int) $recruit_schedule_id;
        $viewer_team_id = (int) $viewer_team_id;
        if ($recruit_schedule_id <= 0 || $viewer_team_id <= 0) {
            return false;
        }
        $recruit_team_id = (int) get_post_meta($recruit_schedule_id, 'team_id', true);
        if ($recruit_team_id <= 0 || $recruit_team_id === $viewer_team_id) {
            return true;
        }

        $viewer_sport = strtolower(trim((string) get_post_meta($viewer_team_id, 'sport_type', true)));
        $recruit_sport = strtolower(trim((string) get_post_meta($recruit_team_id, 'sport_type', true)));
        if ($viewer_sport !== '' && $recruit_sport !== '' && $viewer_sport !== $recruit_sport) {
            return true;
        }

        $viewer_cat = strtolower(trim((string) get_post_meta($viewer_team_id, 'team_category', true)));
        $recruit_cat = strtolower(trim((string) get_post_meta($recruit_team_id, 'team_category', true)));
        if ($viewer_cat !== '' && $recruit_cat !== '' && $viewer_cat !== $recruit_cat) {
            return true;
        }

        if (function_exists('aidunite_normalize_team_gender_option')) {
            $team_g = aidunite_normalize_team_gender_option((string) get_post_meta($viewer_team_id, 'team_gender_option', true));
            $canon = function_exists('aidunite_market_recruitment_gender_canonical')
                ? aidunite_market_recruitment_gender_canonical($recruit_schedule_id)
                : '';
            if ($team_g === 'male' && $canon === 'female') {
                return true;
            }
            if ($team_g === 'female' && $canon === 'male') {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('aidunite_resolve_match_board_tier')) {
    /**
     * 決定木で board_tier を返す
     *
     * @param array $ctx scores(T,V,A), has_own_schedule, time_overlap_zero, recruit_closed, variant
     * @return string best|green|yellow|no_preference|hidden
     */
    function aidunite_resolve_match_board_tier(array $ctx) {
        if (!empty($ctx['hidden_zero'])) {
            return 'hidden';
        }
        if (!empty($ctx['recruit_closed'])) {
            return 'hidden';
        }
        if (empty($ctx['has_own_schedule']) || !empty($ctx['time_overlap_zero'])) {
            return 'no_preference';
        }

        $t = (int) ($ctx['T'] ?? 0);
        $v = (int) ($ctx['V'] ?? 0);
        $a = (int) ($ctx['A'] ?? 0);

        if ($a === 0) {
            return 'yellow';
        }
        if ($v === 10) {
            return 'yellow';
        }
        if ($a === 50 && $t >= 80 && $v === 30) {
            return 'best';
        }
        if ($a >= 25 && $t >= 55 && $v === 30) {
            return 'green';
        }

        return 'yellow';
    }
}

if (!function_exists('aidunite_match_board_tier_from_evaluation')) {
    /**
     * evaluate 結果 + チームID から board_tier / scores を付与
     *
     * @param array $eval aidunite_evaluate_match_apply_context の戻り
     * @param int   $recruit_schedule_id
     * @param int   $applicant_schedule_id
     * @param int   $viewer_team_id 活動スコア用（申請側チーム）
     */
    function aidunite_match_board_tier_from_evaluation(array $eval, $recruit_schedule_id, $applicant_schedule_id, $viewer_team_id) {
        $recruit_schedule_id = (int) $recruit_schedule_id;
        $applicant_schedule_id = (int) $applicant_schedule_id;
        $viewer_team_id = (int) $viewer_team_id;

        $existing_tier = (string) ($eval['board_tier'] ?? '');
        if ($existing_tier !== '' && is_array($eval['scores'] ?? null)) {
            return [
                'board_tier'      => $existing_tier,
                'list_row'        => (string) ($eval['list_row'] ?? $existing_tier),
                'scores'          => $eval['scores'],
                'time_level'      => (string) ($eval['time_level'] ?? ''),
                'overlap_minutes' => (int) ($eval['overlap_minutes'] ?? 0),
            ];
        }

        $recruit_team_id = (int) get_post_meta($recruit_schedule_id, 'team_id', true);

        $applicant_arr = function_exists('_aidunite_match_apply_build_schedule_array')
            ? _aidunite_match_apply_build_schedule_array($applicant_schedule_id)
            : (function_exists('aidunite_market_build_schedule_array') ? aidunite_market_build_schedule_array($applicant_schedule_id) : []);
        $recruit_arr = function_exists('_aidunite_match_apply_build_schedule_array')
            ? _aidunite_match_apply_build_schedule_array($recruit_schedule_id)
            : (function_exists('aidunite_market_build_schedule_array') ? aidunite_market_build_schedule_array($recruit_schedule_id) : []);
        if (function_exists('_aidunite_match_apply_recruit_gender_for_evaluation')) {
            $recruit_arr['gender'] = _aidunite_match_apply_recruit_gender_for_evaluation($recruit_schedule_id);
        }

        $time = aidunite_match_board_score_time($applicant_arr, $recruit_arr);
        $variant = (string) ($eval['variant'] ?? 'direct');
        $place_level = (string) ($eval['place_level'] ?? '');
        $v = aidunite_match_board_score_venue($place_level, $variant);
        $a = aidunite_match_board_score_activity($viewer_team_id, $recruit_team_id);

        $has_own = ($applicant_schedule_id > 0);
        $time_zero = ((int) ($time['score'] ?? 0) === 0);

        $tier = aidunite_resolve_match_board_tier([
            'has_own_schedule'   => $has_own,
            'time_overlap_zero'  => $time_zero,
            'recruit_closed'     => (($eval['reason_code'] ?? '') === 'recruit_closed'),
            'T'                  => (int) $time['score'],
            'V'                  => $v,
            'A'                  => $a,
        ]);

        $list_row = $tier;
        if ($tier === 'best') {
            $list_row = 'green';
        } elseif ($tier === 'hidden') {
            $list_row = 'ineligible';
        }

        return [
            'board_tier'       => $tier,
            'list_row'         => $list_row,
            'scores'           => [
                'T' => (int) $time['score'],
                'V' => $v,
                'A' => $a,
            ],
            'time_level'       => (string) ($time['level'] ?? ($eval['time_level'] ?? '')),
            'overlap_minutes'  => (int) ($time['overlap_minutes'] ?? ($eval['overlap_minutes'] ?? 0)),
        ];
    }
}

if (!function_exists('aidunite_market_row_state_from_schedules')) {
    /**
     * 募集1件 × 自スケ複数 → 最良 board_tier と代表 my_schedule_id
     *
     * @param int   $recruit_schedule_id
     * @param int[] $my_schedule_ids
     * @param int   $viewer_team_id
     * @return array{state:string,best_my_schedule_id:int,board_tier:string,scores:array}
     */
    function aidunite_market_row_state_from_schedules($recruit_schedule_id, array $my_schedule_ids, $viewer_team_id) {
        $recruit_schedule_id = (int) $recruit_schedule_id;
        $viewer_team_id = (int) $viewer_team_id;
        $empty = [
            'state'               => 'hidden',
            'best_my_schedule_id' => 0,
            'board_tier'          => 'hidden',
            'scores'              => ['T' => 0, 'V' => 0, 'A' => 0],
        ];

        if (aidunite_market_recruit_hidden_for_viewer_team($recruit_schedule_id, $viewer_team_id)) {
            return $empty;
        }

        $other_date = get_post_meta($recruit_schedule_id, 'schedule_date', true);
        $tier_priority = ['best' => 0, 'green' => 1, 'yellow' => 2, 'no_preference' => 3, 'hidden' => 9];
        $best = null;

        if (empty($my_schedule_ids)) {
            $a = function_exists('aidunite_match_board_score_activity')
                ? aidunite_match_board_score_activity($viewer_team_id, (int) get_post_meta($recruit_schedule_id, 'team_id', true))
                : 25;
            $tier = aidunite_resolve_match_board_tier([
                'has_own_schedule'  => false,
                'time_overlap_zero' => true,
                'T'                 => 0,
                'V'                 => 30,
                'A'                 => $a,
            ]);

            return [
                'state'               => $tier,
                'best_my_schedule_id' => 0,
                'board_tier'          => $tier,
                'scores'              => ['T' => 0, 'V' => 30, 'A' => $a],
            ];
        }

        foreach ($my_schedule_ids as $my_id) {
            $my_id = (int) $my_id;
            if ($my_id <= 0) {
                continue;
            }
            $my_date = get_post_meta($my_id, 'schedule_date', true);
            if ($other_date && $my_date && $other_date !== $my_date) {
                continue;
            }
            if (!function_exists('aidunite_evaluate_match_apply_context')) {
                continue;
            }
            $ev = aidunite_evaluate_match_apply_context($recruit_schedule_id, $my_id, ['mode' => 'preview']);
            if (in_array((string) ($ev['reason_code'] ?? ''), ['applicant_schedule_committed', 'recruit_closed', 'date_mismatch', 'gender_conflict'], true)) {
                continue;
            }
            $tier = (string) ($ev['board_tier'] ?? '');
            $tier_pack = ($tier !== '' && is_array($ev['scores'] ?? null))
                ? [
                    'board_tier' => $tier,
                    'scores'     => $ev['scores'],
                ]
                : aidunite_match_board_tier_from_evaluation($ev, $recruit_schedule_id, $my_id, $viewer_team_id);
            $tier = (string) ($tier_pack['board_tier'] ?? 'yellow');
            if ($tier === 'hidden' || $tier === 'ineligible') {
                continue;
            }
            $prio = $tier_priority[$tier] ?? 5;
            if ($best === null || $prio < ($tier_priority[$best['board_tier']] ?? 5)) {
                $best = [
                    'state'               => $tier,
                    'best_my_schedule_id' => $my_id,
                    'board_tier'          => $tier,
                    'scores'              => $tier_pack['scores'] ?? ['T' => 0, 'V' => 0, 'A' => 0],
                    'eval'                => $ev,
                ];
            } elseif ($best !== null && $prio === ($tier_priority[$best['board_tier']] ?? 5)) {
                $t_new = (int) (($tier_pack['scores']['T'] ?? 0));
                $t_old = (int) (($best['scores']['T'] ?? 0));
                if ($t_new > $t_old) {
                    $best = [
                        'state'               => $tier,
                        'best_my_schedule_id' => $my_id,
                        'board_tier'          => $tier,
                        'scores'              => $tier_pack['scores'],
                        'eval'                => $ev,
                    ];
                }
            }
        }

        if ($best === null) {
            return [
                'state'               => 'no_preference',
                'best_my_schedule_id' => 0,
                'board_tier'          => 'no_preference',
                'scores'              => ['T' => 0, 'V' => 30, 'A' => 25],
                'best_apply_eval'     => null,
            ];
        }

        $eval_out = is_array($best['eval'] ?? null) ? $best['eval'] : null;
        unset($best['eval']);

        $best['best_apply_eval'] = $eval_out;

        return $best;
    }
}
