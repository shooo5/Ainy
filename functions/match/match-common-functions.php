<?php


/*--------------------------------------------------------------
  MR が2チームの組み合わせに属するか（from/other メタ、なければ schedule の team_id）
--------------------------------------------------------------*/
if (!function_exists('aidunite_match_request_involves_team_pair')) {
  function aidunite_match_request_involves_team_pair($request, $team_a, $team_b) {
    $ta = (int) $team_a;
    $tb = (int) $team_b;
    if ($ta <= 0 || $tb <= 0) {
      return false;
    }
    $ft = (int) get_post_meta($request->ID, 'from_team_id', true);
    $ot = (int) get_post_meta($request->ID, 'other_team_id', true);
    if ($ft > 0 && $ot > 0) {
      return ($ft === $ta && $ot === $tb) || ($ft === $tb && $ot === $ta);
    }
    $mys = (int) get_post_meta($request->ID, 'my_schedule_id', true);
    if ($mys <= 0) {
      $mys = (int) get_post_meta($request->ID, 'from_schedule_id', true);
    }
    $tos = (int) get_post_meta($request->ID, 'to_schedule_id', true);
    $teams = [];
    if ($mys > 0) {
      $teams[] = (int) get_post_meta($mys, 'team_id', true);
    }
    if ($tos > 0) {
      $teams[] = (int) get_post_meta($tos, 'team_id', true);
    }
    $teams = array_filter(array_unique($teams));
    return in_array($ta, $teams, true) && in_array($tb, $teams, true);
  }
}

/*--------------------------------------------------------------
  MR のリンク用チームメタ（other_team_id / to_team_id）を schedule から補完
--------------------------------------------------------------*/
if (!function_exists('aidunite_match_request_get_link_schedule_teams')) {
  /**
   * @return array{0:int,1:int,2:int,3:int,4:int} my_sid, to_sid, my_team, to_team, from_team
   */
  function aidunite_match_request_get_link_schedule_teams($request_id) {
    $request_id = (int) $request_id;
    if ($request_id <= 0) {
      return [0, 0, 0, 0, 0];
    }
    $my_sid = (int) get_post_meta($request_id, 'my_schedule_id', true);
    if ($my_sid <= 0) {
      $my_sid = (int) get_post_meta($request_id, 'from_schedule_id', true);
    }
    $to_sid = (int) get_post_meta($request_id, 'to_schedule_id', true);
    if ($to_sid === 9999) {
      $to_sid = 0;
    }
    $my_team = ($my_sid > 0) ? (int) get_post_meta($my_sid, 'team_id', true) : 0;
    $to_team = ($to_sid > 0) ? (int) get_post_meta($to_sid, 'team_id', true) : 0;
    $from_team = (int) get_post_meta($request_id, 'from_team_id', true);
    return [$my_sid, $to_sid, $my_team, $to_team, $from_team];
  }
}

if (!function_exists('aidunite_match_request_resolve_opponent_team_id')) {
  /**
   * `from_team_id` が my または to の schedule.team_id のどちらかと一致するとき、もう一方の team_id を返す。
   */
  function aidunite_match_request_resolve_opponent_team_id($request_id) {
    [, , $my_team, $to_team, $from_team] = aidunite_match_request_get_link_schedule_teams($request_id);
    if ($my_team <= 0 || $to_team <= 0 || $from_team <= 0) {
      return 0;
    }
    if ($from_team === $my_team) {
      return $to_team;
    }
    if ($from_team === $to_team) {
      return $my_team;
    }
    return 0;
  }
}

if (!function_exists('aidunite_match_request_ensure_link_team_meta')) {
  /**
   * 空の `other_team_id` / `to_team_id` を my・to の schedule.team_id と from_team_id から補完する。
   *
   * @return bool メタを1つ以上更新したら true
   */
  function aidunite_match_request_ensure_link_team_meta($request_id) {
    $request_id = (int) $request_id;
    if ($request_id <= 0) {
      return false;
    }
    $opp = aidunite_match_request_resolve_opponent_team_id($request_id);
    if ($opp <= 0) {
      return false;
    }
    $changed = false;
    $cur_other = (int) get_post_meta($request_id, 'other_team_id', true);
    $cur_to = (int) get_post_meta($request_id, 'to_team_id', true);
    if ($cur_other <= 0) {
      update_post_meta($request_id, 'other_team_id', $opp);
      $changed = true;
    }
    if ($cur_to <= 0) {
      update_post_meta($request_id, 'to_team_id', $opp);
      $changed = true;
    }
    return $changed;
  }
}

if (!function_exists('aidunite_match_request_admin_backfill_link_team_meta')) {
  /**
   * 管理画面読み込み時に、リンク用チームメタが欠けた MR を少しずつ補完する（管理者のみ・完了後は no-op）。
   */
  function aidunite_match_request_admin_backfill_link_team_meta() {
    if (!is_admin() || !current_user_can('manage_options')) {
      return;
    }
    if (get_option('aidunite_mr_link_team_meta_backfill_complete')) {
      return;
    }
    if (!function_exists('aidunite_match_request_ensure_link_team_meta')) {
      return;
    }
    if (get_transient('aidunite_mr_link_team_meta_backfill_lock')) {
      return;
    }
    set_transient('aidunite_mr_link_team_meta_backfill_lock', 1, 30);
    try {
      $meta_query = [
        'relation' => 'AND',
        [
          'relation' => 'OR',
          [
            'relation' => 'OR',
            ['key' => 'other_team_id', 'compare' => 'NOT EXISTS'],
            ['key' => 'other_team_id', 'value' => '', 'compare' => '='],
            ['key' => 'other_team_id', 'value' => '0', 'compare' => '='],
          ],
          [
            'relation' => 'OR',
            ['key' => 'to_team_id', 'compare' => 'NOT EXISTS'],
            ['key' => 'to_team_id', 'value' => '', 'compare' => '='],
            ['key' => 'to_team_id', 'value' => '0', 'compare' => '='],
          ],
        ],
        [
          'key' => 'to_schedule_id',
          'value' => [0, 9999],
          'compare' => 'NOT IN',
          'type' => 'NUMERIC',
        ],
        [
          'relation' => 'OR',
          ['key' => 'my_schedule_id', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC'],
          ['key' => 'from_schedule_id', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC'],
        ],
      ];

      $q = new WP_Query([
        'post_type' => 'match_request',
        'post_status' => 'any',
        'posts_per_page' => 30,
        'fields' => 'ids',
        'no_found_rows' => true,
        'orderby' => 'ID',
        'order' => 'ASC',
        'meta_query' => $meta_query,
      ]);

      $ids = is_array($q->posts) ? $q->posts : [];
      if ($ids === []) {
        update_option('aidunite_mr_link_team_meta_backfill_complete', time());
        return;
      }
      foreach ($ids as $rid) {
        aidunite_match_request_ensure_link_team_meta((int) $rid);
      }
    } finally {
      delete_transient('aidunite_mr_link_team_meta_backfill_lock');
    }
  }
}

add_action('admin_init', 'aidunite_match_request_admin_backfill_link_team_meta', 20);

if (!function_exists('aidunite_get_reconfirm_diff')) {
  /**
   * 再確認待ち表示用の差分（申請時スナップショット vs 現在募集条件）を返す。
   *
   * @param array<string,mixed> $opts `for_applicant`（bool）: 申請者向けUIでは募集枠行を省略。性別は表示ラベルが同一なら行を出さない（内部比較は `aidunite_normalize_gender_for_match_score`）。
   * @return array{changed_fields:array,before:array,after:array,messages:array,rows:array<int,array{key:string,label:string,before:string,after:string}>}
   */
  function aidunite_get_reconfirm_diff($request_id, $recruit_schedule_id = 0, array $opts = []) {
    $request_id = (int) $request_id;
    $recruit_schedule_id = (int) $recruit_schedule_id;
    $for_applicant = !empty($opts['for_applicant']);
    if ($request_id <= 0) {
      return ['changed_fields' => [], 'before' => [], 'after' => [], 'messages' => [], 'rows' => []];
    }
    if ($recruit_schedule_id <= 0) {
      $recruit_schedule_id = (int) get_post_meta($request_id, 'to_schedule_id', true);
    }
    if ($recruit_schedule_id <= 0 || $recruit_schedule_id === 9999) {
      return ['changed_fields' => [], 'before' => [], 'after' => [], 'messages' => [], 'rows' => []];
    }

    $before_place = (string) get_post_meta($request_id, 'reconfirm_before_schedule_place', true);
    $before_gender = (string) get_post_meta($request_id, 'reconfirm_before_schedule_gender', true);
    $before_male = (int) get_post_meta($request_id, 'reconfirm_before_male_slots', true);
    $before_female = (int) get_post_meta($request_id, 'reconfirm_before_female_slots', true);
    $before_lock = (string) get_post_meta($request_id, 'reconfirm_before_place_lock', true);

    $after_place = (string) (get_post_meta($recruit_schedule_id, 'schedule_place', true) ?: get_post_meta($recruit_schedule_id, 'schedule_place_option', true));
    $after_gender = (string) (get_post_meta($recruit_schedule_id, 'schedule_gender', true) ?: get_post_meta($recruit_schedule_id, 'matching_gender_condition', true));
    $after_male = function_exists('aidunite_get_schedule_male_slots') ? (int) aidunite_get_schedule_male_slots($recruit_schedule_id) : (int) get_post_meta($recruit_schedule_id, 'male_slots', true);
    $after_female = function_exists('aidunite_get_schedule_female_slots') ? (int) aidunite_get_schedule_female_slots($recruit_schedule_id) : (int) get_post_meta($recruit_schedule_id, 'female_slots', true);
    $after_lock = function_exists('aidunite_get_schedule_place_lock') ? (string) aidunite_get_schedule_place_lock($recruit_schedule_id) : (string) get_post_meta($recruit_schedule_id, 'place_lock', true);

    $changed_fields = [];
    $messages = [];
    $rows = [];

    $jp_place = static function ($p) {
      return function_exists('aidunite_jp_place') ? aidunite_jp_place((string) $p) : (string) $p;
    };
    $jp_gender = static function ($g) {
      return function_exists('aidunite_jp_gender') ? aidunite_jp_gender((string) $g) : (string) $g;
    };

    if ($before_place !== '' && $after_place !== '' && $before_place !== $after_place) {
      $changed_fields[] = 'place';
      $b = $jp_place($before_place);
      $a = $jp_place($after_place);
      $messages[] = '会場条件（募集）: ' . $b . ' -> ' . $a;
      $rows[] = ['key' => 'place', 'label' => '会場条件（募集）', 'before' => $b, 'after' => $a];
    }
    if ($before_lock !== '' && $after_lock !== '' && $before_lock !== $after_lock) {
      $changed_fields[] = 'place_lock';
      $b = $jp_place($before_lock);
      $a = $jp_place($after_lock);
      $messages[] = '開催形式ロック（募集）: ' . $b . ' -> ' . $a;
      $rows[] = ['key' => 'place_lock', 'label' => '開催形式ロック（募集）', 'before' => $b, 'after' => $a];
    }
    if ($before_gender !== '' && $after_gender !== '') {
      $nb = function_exists('aidunite_normalize_gender_for_match_score')
        ? (string) aidunite_normalize_gender_for_match_score($before_gender)
        : $jp_gender($before_gender);
      $na = function_exists('aidunite_normalize_gender_for_match_score')
        ? (string) aidunite_normalize_gender_for_match_score($after_gender)
        : $jp_gender($after_gender);
      if ($nb !== $na) {
        $changed_fields[] = 'gender';
        $messages[] = '性別条件（募集）: ' . $nb . ' -> ' . $na;
        $rows[] = ['key' => 'gender', 'label' => '性別条件（募集）', 'before' => $nb, 'after' => $na];
      }
    }
    if (
      !$for_applicant
      && ($before_male !== 0 || $before_female !== 0)
      && ($before_male !== $after_male || $before_female !== $after_female)
    ) {
      $changed_fields[] = 'slots';
      $b = '男 ' . $before_male . ' / 女 ' . $before_female;
      $a = '男 ' . $after_male . ' / 女 ' . $after_female;
      $messages[] = '募集枠: 男 ' . $before_male . '/女 ' . $before_female . ' -> 男 ' . $after_male . '/女 ' . $after_female;
      $rows[] = ['key' => 'slots', 'label' => '募集枠（募集）', 'before' => $b, 'after' => $a];
    }

    return [
      'changed_fields' => array_values(array_unique($changed_fields)),
      'before' => [
        'place' => $before_place,
        'gender' => $before_gender,
        'male_slots' => $before_male,
        'female_slots' => $before_female,
        'place_lock' => $before_lock,
      ],
      'after' => [
        'place' => $after_place,
        'gender' => $after_gender,
        'male_slots' => $after_male,
        'female_slots' => $after_female,
        'place_lock' => $after_lock,
      ],
      'messages' => $messages,
      'rows'       => $rows,
    ];
  }
}

if (!function_exists('aidunite_match_request_outcome_label_jp')) {
    /**
     * match-request.md 第6A節のユーザー向けアウトカムコードに対応する短いラベル（一覧・詳細用）。
     */
    function aidunite_match_request_outcome_label_jp($outcome_code) {
        $code = (string) $outcome_code;
        $map = [
            'keep_pending'         => '承認可能',
            'reconfirm_required'     => '再確認待ち',
            'proposal_possible'      => '条件調整で成立可能',
            'slots_full'             => '募集枠満了',
            'gender_slots_full'      => '性別枠満了',
            'gender_conflict'        => '性別不一致',
            'venue_conflict'         => '会場条件不一致',
            'invalid_closed'         => '成立不可',
        ];
        return (string) ($map[$code] ?? '');
    }
}

if (!function_exists('aidunite_match_request_guard_before_recipient_accept')) {
    /**
     * 募集側が申請を承認（確定処理）する直前の検証。REST / Ajax で同一ロジックを使う。
     *
     * @param int $request_id match_request 投稿ID
     * @return null|array{message:string,code:string,http_status:int} null=通過
     */
    function aidunite_match_request_guard_before_recipient_accept($request_id) {
        $request_id = (int) $request_id;
        if ($request_id <= 0) {
            return null;
        }

        if ((int) get_post_meta($request_id, 'proposal_pending_accept', true) === 1) {
            return [
                'message'     => '相手チームの承諾待ちです。承諾後に承認してください。',
                'code'        => 'proposal_pending_accept',
                'http_status' => 409,
            ];
        }
        if ((int) get_post_meta($request_id, 'requires_reconfirm', true) === 1) {
            return [
                'message'     => '募集条件が変更されたため、この申請は再確認待ちです。最新条件で再申請してください。',
                'code'        => 'reconfirm_required',
                'http_status' => 409,
            ];
        }

        $my_schedule_id = (int) get_post_meta($request_id, 'my_schedule_id', true);
        $to_schedule_id = (int) get_post_meta($request_id, 'to_schedule_id', true);
        if ($to_schedule_id <= 0 || $my_schedule_id <= 0) {
            return null;
        }

        $from_team_id = (string) get_post_meta($request_id, 'from_team_id', true);

        $existing_established = get_posts([
            'post_type'      => 'match_request',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'exclude'        => [$request_id],
            'meta_query'     => [
                'relation' => 'AND',
                ['key' => 'status', 'value' => 'established', 'compare' => '='],
                ['key' => 'to_schedule_id', 'value' => (string) $to_schedule_id, 'compare' => '='],
                ['key' => 'from_team_id', 'value' => $from_team_id, 'compare' => '='],
            ],
        ]);
        if (!empty($existing_established)) {
            return [
                'message'     => 'この相手チームとは既にこの日程で成立しています。',
                'code'        => 'duplicate_established',
                'http_status' => 409,
            ];
        }

        $to_place_lock = function_exists('aidunite_get_schedule_place_lock') ? aidunite_get_schedule_place_lock($to_schedule_id) : '';
        if ($to_place_lock !== '') {
            $applicant_place = (string) get_post_meta($request_id, 'selected_place', true);
            if ($applicant_place === '' || $applicant_place === 'both') {
                $applicant_place = get_post_meta($my_schedule_id, 'schedule_place', true) ?: get_post_meta($my_schedule_id, 'schedule_place_option', true);
            }
            if ($applicant_place === 'both') {
                $applicant_place = 'either';
            }
            if (!function_exists('aidunite_place_lock_matches_candidate') || !aidunite_place_lock_matches_candidate($to_place_lock, $applicant_place)) {
                return [
                    'message'     => 'この日程は開催形式が確定しています。条件が一致しないため承認できません。',
                    'code'        => 'place_lock_mismatch',
                    'http_status' => 409,
                ];
            }
        }

        $to_male = function_exists('aidunite_get_schedule_male_slots') ? aidunite_get_schedule_male_slots($to_schedule_id) : 1;
        $to_female = function_exists('aidunite_get_schedule_female_slots') ? aidunite_get_schedule_female_slots($to_schedule_id) : 1;
        $selected_gender_req = (string) get_post_meta($request_id, 'selected_gender', true);
        $opponent_gender = $selected_gender_req !== '' ? $selected_gender_req : (function_exists('aidunite_get_schedule_gender') ? aidunite_get_schedule_gender($my_schedule_id) : '');
        if (function_exists('aidunite_normalize_gender_canonical')) {
            $opponent_gender = aidunite_normalize_gender_canonical((string) $opponent_gender);
        }

        if ($opponent_gender === 'male' && (int) $to_male < 1) {
            return [
                'message'     => '該当性別枠が満了しています。',
                'code'        => 'gender_slots_full',
                'http_status' => 409,
            ];
        }
        if ($opponent_gender === 'female' && (int) $to_female < 1) {
            return [
                'message'     => '該当性別枠が満了しています。',
                'code'        => 'gender_slots_full',
                'http_status' => 409,
            ];
        }
        if (($opponent_gender === 'both' || $opponent_gender === '') && (int) $to_male < 1 && (int) $to_female < 1) {
            return [
                'message'     => '該当性別枠が満了しています。',
                'code'        => 'gender_slots_full',
                'http_status' => 409,
            ];
        }

        $remaining_total = (int) $to_male + (int) $to_female;
        if ($remaining_total < 1) {
            return [
                'message'     => '募集枠が満了しています。',
                'code'        => 'slots_full',
                'http_status' => 409,
            ];
        }

        $post = get_post($request_id);
        $raw_status = (string) get_post_meta($request_id, 'status', true);
        $norm = function_exists('aidunite_normalize_match_request_status')
            ? aidunite_normalize_match_request_status($raw_status, $post && isset($post->post_status) ? (string) $post->post_status : '')
            : strtolower($raw_status);
        if (in_array($norm, ['pending', 'publish', 'accepted'], true)) {
            $mr_oc = (string) get_post_meta($request_id, 'mr_outcome_code', true);
            if ($mr_oc !== '' && $mr_oc !== 'keep_pending') {
                $race_msg = '他チームの承認により枠が埋まりました。画面を更新してください。';
                if (in_array($mr_oc, ['slots_full', 'gender_slots_full'], true)) {
                    return [
                        'message'     => $race_msg,
                        'code'        => $mr_oc,
                        'http_status' => 409,
                    ];
                }
                $label = function_exists('aidunite_match_request_outcome_label_jp') ? aidunite_match_request_outcome_label_jp($mr_oc) : '';
                $head = ($label !== '') ? $label : $mr_oc;
                return [
                    'message'     => $head . '。この状態では承認できません。画面を更新するか、申請側の条件見直しをお願いください。',
                    'code'        => $mr_oc,
                    'http_status' => 409,
                ];
            }
        }

        return null;
    }
}

if (!function_exists('aidunite_resolve_match_request_view_state')) {
  /**
   * MRの表示状態を統一判定する（proposal_pending_accept > requires_reconfirm > pending+mr_outcome > accepted+mr_outcome > status）。
   *
   * @return array{display_code:string,display_label:string,badge_class:string,requires_reconfirm:bool,proposal_pending_accept:bool,can_approve:bool,can_reapply:bool,reason_code:string,mr_outcome_code:string}
   */
  function aidunite_resolve_match_request_view_state($request, $viewer_team_id = 0) {
    if (!$request) {
      return [
        'display_code' => 'not_applied',
        'display_label' => '未申請',
        'badge_class' => '',
        'requires_reconfirm' => false,
        'proposal_pending_accept' => false,
        'can_approve' => false,
        'can_reapply' => false,
        'reason_code' => '',
        'mr_outcome_code' => '',
      ];
    }
    $request_id = is_object($request) ? (int) $request->ID : (int) $request;
    $viewer_team_id = (int) $viewer_team_id;
    $proposal_pending_accept = ((int) get_post_meta($request_id, 'proposal_pending_accept', true) === 1);
    if ($proposal_pending_accept) {
      return [
        'display_code' => 'proposal_pending_accept',
        'display_label' => '相手承諾待ち',
        'badge_class' => 'badge-status',
        'requires_reconfirm' => false,
        'proposal_pending_accept' => true,
        'can_approve' => false,
        'can_reapply' => false,
        'reason_code' => 'proposal_pending_accept',
        'mr_outcome_code' => '',
      ];
    }
    $requires_reconfirm = ((int) get_post_meta($request_id, 'requires_reconfirm', true) === 1);
    $reason_code = (string) get_post_meta($request_id, 'reconfirm_reason', true);
    if ($requires_reconfirm) {
      $mr_oc = (string) get_post_meta($request_id, 'mr_outcome_code', true);
      $label = '再確認待ち';
      if ($mr_oc === 'proposal_possible' && function_exists('aidunite_match_request_outcome_label_jp')) {
        $alt = aidunite_match_request_outcome_label_jp('proposal_possible');
        if ($alt !== '') {
          $label = $alt;
        }
      }
      return [
        'display_code' => 'reconfirm_required',
        'display_label' => $label,
        'badge_class' => 'badge-canceled',
        'requires_reconfirm' => true,
        'proposal_pending_accept' => false,
        'can_approve' => false,
        'can_reapply' => true,
        'reason_code' => ($reason_code !== '' ? $reason_code : 'schedule_condition_changed'),
        'mr_outcome_code' => $mr_oc,
      ];
    }

    $raw_status = (string) get_post_meta($request_id, 'status', true);
    $norm = function_exists('aidunite_normalize_match_request_status')
      ? aidunite_normalize_match_request_status($raw_status, is_object($request) && isset($request->post_status) ? (string) $request->post_status : '')
      : strtolower($raw_status);
    if ($norm === 'draft') {
      $norm = 'not_applied';
    }

    $from_team_id = (int) get_post_meta($request_id, 'from_team_id', true);
    $is_requester = ($viewer_team_id > 0 && $from_team_id > 0 && $viewer_team_id === $from_team_id);

    if ($norm === 'pending' || $norm === 'publish') {
      $mr_oc = (string) get_post_meta($request_id, 'mr_outcome_code', true);
      if ($mr_oc !== '' && $mr_oc !== 'keep_pending') {
        $ol = function_exists('aidunite_match_request_outcome_label_jp') ? aidunite_match_request_outcome_label_jp($mr_oc) : $mr_oc;
        $can_reapply = !in_array($mr_oc, ['invalid_closed', 'gender_conflict', 'slots_full', 'gender_slots_full'], true);
        return [
          'display_code' => $mr_oc,
          'display_label' => ($ol !== '' ? $ol : $mr_oc),
          'badge_class' => 'badge-canceled',
          'requires_reconfirm' => false,
          'proposal_pending_accept' => false,
          'can_approve' => false,
          'can_reapply' => $can_reapply,
          'reason_code' => $mr_oc,
          'mr_outcome_code' => $mr_oc,
        ];
      }
      return [
        'display_code' => 'pending',
        'display_label' => $is_requester ? '申請中' : '申請受付中',
        'badge_class' => 'badge-status',
        'requires_reconfirm' => false,
        'proposal_pending_accept' => false,
        'can_approve' => !$is_requester,
        'can_reapply' => false,
        'reason_code' => '',
        'mr_outcome_code' => '',
      ];
    }
    if ($norm === 'accepted') {
      $mr_oc = (string) get_post_meta($request_id, 'mr_outcome_code', true);
      if ($mr_oc !== '' && $mr_oc !== 'keep_pending') {
        $ol = function_exists('aidunite_match_request_outcome_label_jp') ? aidunite_match_request_outcome_label_jp($mr_oc) : $mr_oc;
        $can_reapply = !in_array($mr_oc, ['invalid_closed', 'gender_conflict', 'slots_full', 'gender_slots_full'], true);
        $suffix = ($ol !== '' ? $ol : $mr_oc);
        return [
          'display_code' => $mr_oc,
          'display_label' => '承認済み・' . $suffix,
          'badge_class' => 'badge-canceled',
          'requires_reconfirm' => false,
          'proposal_pending_accept' => false,
          'can_approve' => false,
          'can_reapply' => $can_reapply,
          'reason_code' => $mr_oc,
          'mr_outcome_code' => $mr_oc,
        ];
      }
      return [
        'display_code' => 'accepted',
        'display_label' => '承認済み',
        'badge_class' => 'badge-approved',
        'requires_reconfirm' => false,
        'proposal_pending_accept' => false,
        'can_approve' => false,
        'can_reapply' => false,
        'reason_code' => '',
        'mr_outcome_code' => '',
      ];
    }
    if ($norm === 'established') {
      return [
        'display_code' => 'established',
        'display_label' => '試合確定',
        'badge_class' => 'badge-established',
        'requires_reconfirm' => false,
        'proposal_pending_accept' => false,
        'can_approve' => false,
        'can_reapply' => false,
        'reason_code' => '',
        'mr_outcome_code' => '',
      ];
    }
    if ($norm === 'rejected') {
      return [
        'display_code' => 'rejected',
        'display_label' => '拒否済み',
        'badge_class' => 'badge-rejected',
        'requires_reconfirm' => false,
        'proposal_pending_accept' => false,
        'can_approve' => false,
        'can_reapply' => true,
        'reason_code' => '',
        'mr_outcome_code' => '',
      ];
    }
    if ($norm === 'canceled') {
      $canceled_by = (int) get_post_meta($request_id, 'canceled_by_team_id', true);
      $label = ($viewer_team_id > 0 && $canceled_by > 0 && $canceled_by !== $viewer_team_id) ? '相手キャンセル' : 'キャンセル済み';
      $code = ($label === '相手キャンセル') ? 'canceled_opponent' : 'canceled';
      return [
        'display_code' => $code,
        'display_label' => $label,
        'badge_class' => 'badge-canceled',
        'requires_reconfirm' => false,
        'proposal_pending_accept' => false,
        'can_approve' => false,
        'can_reapply' => true,
        'reason_code' => '',
        'mr_outcome_code' => '',
      ];
    }

    return [
      'display_code' => 'not_applied',
      'display_label' => '未申請',
      'badge_class' => '',
      'requires_reconfirm' => false,
      'proposal_pending_accept' => false,
      'can_approve' => false,
      'can_reapply' => false,
      'reason_code' => '',
      'mr_outcome_code' => '',
    ];
  }
}

/*--------------------------------------------------------------
  No.101 get_latest_match_request_bidirectional
  双方向で最新の match_request を取得（共通利用）
  @param array $opts 任意。
   - restrict_to_to_schedule_id: この募集 schedule ID に対する申請（to_schedule_id が一致）のみ残す。
     get_or_create や、相互申請の逆方向を混ぜない重複判定向け。
   - involve_schedule_id: 「この行の募集」schedule ID が MR の to または my（申請側枠）のどちらかに触れるものに限定。
     相手が自チームの募集を承認して成立した MR は to が相手側のため、掲示板「相手のこの募集」行では to だけの restrict だと拾えない。
     指定時は restrict_to_to_schedule_id より優先する。
--------------------------------------------------------------*/
function get_latest_match_request_bidirectional($team_id1, $schedule_id1, $team_id2, $schedule_id2, $opts = []) {

  // より包括的な検索パターンを使用
  $search_patterns = [
    // パターン1: team_id1 → schedule_id2
    [
      'meta_query' => [
        'relation' => 'AND',
        ['key' => 'from_team_id', 'value' => $team_id1],
        ['key' => 'to_schedule_id', 'value' => $schedule_id2],
      ]
    ],
    // パターン2: team_id2 → schedule_id1
    [
      'meta_query' => [
        'relation' => 'AND',
        ['key' => 'from_team_id', 'value' => $team_id2],
        ['key' => 'to_schedule_id', 'value' => $schedule_id1],
      ]
    ],
    // パターン3: my_schedule_id と to_schedule_id の組み合わせ
    [
      'meta_query' => [
        'relation' => 'AND',
        ['key' => 'my_schedule_id', 'value' => $schedule_id1],
        ['key' => 'to_schedule_id', 'value' => $schedule_id2],
      ]
    ],
    [
      'meta_query' => [
        'relation' => 'AND',
        ['key' => 'my_schedule_id', 'value' => $schedule_id2],
        ['key' => 'to_schedule_id', 'value' => $schedule_id1],
      ]
    ]
  ];

  $candidates_by_id = [];

  foreach ($search_patterns as $pattern) {
    $args = [
      'post_type' => 'match_request',
      'post_status' => 'any',
      'posts_per_page' => -1,
      'meta_query' => $pattern['meta_query'],
      'orderby' => 'date',
      'order' => 'DESC',
    ];

    $requests = get_posts($args);

    if (!empty($requests)) {
      foreach ($requests as $request) {
        $candidates_by_id[(int) $request->ID] = $request;
      }
    }
  }

  $involve_sid = !empty($opts['involve_schedule_id']) ? (int) $opts['involve_schedule_id'] : 0;
  if ($involve_sid > 0 && $team_id1 && $team_id2) {
    $args_extra = [
      'post_type'      => 'match_request',
      'post_status'    => 'any',
      'posts_per_page' => -1,
      'meta_query'     => [
        'relation' => 'OR',
        [ 'key' => 'to_schedule_id', 'value' => (string) $involve_sid ],
        [ 'key' => 'my_schedule_id', 'value' => (string) $involve_sid ],
        [ 'key' => 'from_schedule_id', 'value' => (string) $involve_sid ],
      ],
    ];
    $extra_posts = get_posts($args_extra);
    if (!empty($extra_posts)) {
      foreach ($extra_posts as $request) {
        if (!aidunite_match_request_involves_team_pair($request, $team_id1, $team_id2)) {
          continue;
        }
        $candidates_by_id[(int) $request->ID] = $request;
      }
    }
  }

  $restrict_to = ($involve_sid > 0) ? 0 : (!empty($opts['restrict_to_to_schedule_id']) ? (int) $opts['restrict_to_to_schedule_id'] : 0);

  if ($involve_sid > 0 && !empty($candidates_by_id)) {
    foreach ($candidates_by_id as $rid => $request) {
      $to_s  = (int) get_post_meta($request->ID, 'to_schedule_id', true);
      $mys_s = (int) get_post_meta($request->ID, 'my_schedule_id', true);
      if ($mys_s <= 0) {
        $mys_s = (int) get_post_meta($request->ID, 'from_schedule_id', true);
      }
      if ($to_s !== $involve_sid && $mys_s !== $involve_sid) {
        unset($candidates_by_id[$rid]);
      }
    }
  } elseif ($restrict_to > 0 && !empty($candidates_by_id)) {
    foreach ($candidates_by_id as $rid => $request) {
      if ((int) get_post_meta($request->ID, 'to_schedule_id', true) !== $restrict_to) {
        unset($candidates_by_id[$rid]);
      }
    }
  }

  if (empty($candidates_by_id)) {
    return null;
  }

  // 同一申請ライン（from_team + 募集 to_schedule + 申請元 my/from_schedule）の重複 MR は
  // post_modified が新しい1件に畳む。キャンセル後も古い pending が pick_best で勝つ掲示板表示を防ぐ。
  $collapsed = [];
  $standalone = [];
  foreach ($candidates_by_id as $rid => $request) {
    $fid = (int) get_post_meta($request->ID, 'from_team_id', true);
    $to = (int) get_post_meta($request->ID, 'to_schedule_id', true);
    $my = (int) get_post_meta($request->ID, 'my_schedule_id', true);
    if ($my <= 0) {
      $my = (int) get_post_meta($request->ID, 'from_schedule_id', true);
    }
    if ($fid <= 0 || $to <= 0 || $my <= 0) {
      $standalone[(int) $request->ID] = $request;
      continue;
    }
    $k = $fid . ':' . $to . ':' . $my;
    $ts = (int) get_post_modified_time('U', true, $request);
    $pid = (int) $request->ID;
    if (!isset($collapsed[$k])) {
      $collapsed[$k] = ['ts' => $ts, 'id' => $pid, 'post' => $request];
    } elseif ($ts > $collapsed[$k]['ts'] || ($ts === $collapsed[$k]['ts'] && $pid > $collapsed[$k]['id'])) {
      $collapsed[$k] = ['ts' => $ts, 'id' => $pid, 'post' => $request];
    }
  }
  $candidates_by_id = $standalone;
  foreach ($collapsed as $row) {
    $candidates_by_id[(int) $row['post']->ID] = $row['post'];
  }

  if (empty($candidates_by_id)) {
    return null;
  }

  // 同一募集に複数 MR があるとき、優先度テーブルで選び、同優先度は post_modified でタイブレークする。
  $pick_best = static function (array $pool) {
    $best = null;
    $best_tuple = null;
    foreach ($pool as $request) {
      $raw_st = (string) get_post_meta($request->ID, 'status', true);
      $norm = function_exists('aidunite_normalize_match_request_status')
        ? aidunite_normalize_match_request_status($raw_st, isset($request->post_status) ? (string) $request->post_status : '')
        : strtolower($raw_st);
      if ($norm === 'draft') {
        $norm = 'not_applied';
      }
      $prio = [
        'established' => 100,
        'accepted' => 80,
        'pending' => 60,
        'publish' => 60,
        'not_applied' => 40,
        'rejected' => 20,
        'canceled' => 10,
      ];
      $p = isset($prio[$norm]) ? (int) $prio[$norm] : 5;
      $ts = (int) get_post_modified_time('U', true, $request);
      $tuple = [$p, $ts, (int) $request->ID];
      if ($best === null || $tuple > $best_tuple) {
        $best = $request;
        $best_tuple = $tuple;
      }
    }
    return $best;
  };

  $sid1 = (int) $schedule_id1;
  $sid2 = (int) $schedule_id2;
  // 相互申請で MR が2件あるとき、URL の2スケジュールと端点が一致する MR だけを優先（日付が新しい逆方向 MR に負けない）
  if ($sid1 > 0 && $sid2 > 0) {
    $u = min($sid1, $sid2);
    $v = max($sid1, $sid2);
    $exact_pool = [];
    foreach ($candidates_by_id as $request) {
      $my = (int) get_post_meta($request->ID, 'my_schedule_id', true);
      if ($my <= 0) {
        $my = (int) get_post_meta($request->ID, 'from_schedule_id', true);
      }
      $to = (int) get_post_meta($request->ID, 'to_schedule_id', true);
      if ($my <= 0 || $to <= 0) {
        continue;
      }
      $a = min($my, $to);
      $b = max($my, $to);
      if ($a === $u && $b === $v) {
        $exact_pool[(int) $request->ID] = $request;
      }
    }
    if (!empty($exact_pool)) {
      return $pick_best($exact_pool);
    }
  }

  return $pick_best($candidates_by_id);
}


/*--------------------------------------------------------------
  No.102 get_or_create_match_request
  未申請状態の match_request がなければ新規作成（表示用）
--------------------------------------------------------------*/
function get_or_create_match_request($my_team_id, $my_schedule_id, $other_team_id, $other_schedule_id) {
  if (!$my_team_id || !$my_schedule_id || !$other_team_id || !$other_schedule_id) {
    error_log("[match_request] ❌ 入力不正: my_team_id={$my_team_id}, my_schedule_id={$my_schedule_id}, other_team_id={$other_team_id}, other_schedule_id={$other_schedule_id}");
    return null;
  }

  $existing = get_latest_match_request_bidirectional($my_team_id, $my_schedule_id, $other_team_id, $other_schedule_id, [
    'restrict_to_to_schedule_id' => (int) $other_schedule_id,
  ]);

  $existing_status_raw = $existing ? (string) get_post_meta($existing->ID, 'status', true) : '';
  $existing_norm       = function_exists('aidunite_normalize_match_request_status')
    ? aidunite_normalize_match_request_status($existing_status_raw, $existing ? $existing->post_status : '')
    : $existing_status_raw;

  if ($existing && $existing_norm === 'not_applied') {
    error_log("[match_request] ✅ 既存の未申請リクエストを再利用: ID={$existing->ID}");
    return $existing;
  } elseif ($existing) {
    error_log("[match_request] ⚠️ 既存リクエストあり（未申請ではない）: ID={$existing->ID}, status=" . get_post_meta($existing->ID, 'status', true));
    return $existing; // 既存は返すが状態は未申請ではない
  }

  if (!function_exists('aidunite_match_request_create_placeholder_post')) {
    error_log('[match_request] ❌ persist 未読込');
    return null;
  }

  $new_id = aidunite_match_request_create_placeholder_post([
    'post_author' => get_current_user_id(),
    'post_title' => 'マッチ申請（自動生成） ' . current_time('mysql'),
    'from_team_id' => $my_team_id,
    'my_schedule_id' => $my_schedule_id,
    'to_schedule_id' => $other_schedule_id,
    'other_team_id' => $other_team_id,
    'to_team_id' => $other_team_id,
    'request_team_id' => $my_team_id,
    'status' => 'not_applied',
    'post_status' => 'draft',
  ]);

  if (is_wp_error($new_id)) {
    error_log('[match_request] ❌ 作成失敗: ' . $new_id->get_error_message());
    return null;
  }

  error_log("[match_request] 🆕 新規作成: ID={$new_id}, from_team_id={$my_team_id}, to_schedule_id={$other_schedule_id}");
  return get_post($new_id);
}


/*--------------------------------------------------------------
  No.105 get_match_status_label
  ステータスラベルを返す（自動マッチ用）＋バッジ表示向け調整
--------------------------------------------------------------*/
function get_match_status_label($status, $format = 'text') {
  $norm = function_exists('aidunite_normalize_match_request_status')
    ? aidunite_normalize_match_request_status((string) $status, '')
    : (string) $status;

  $labels = [
    'not_applied' => '未申請',
    '未申請'      => '未申請',
    'publish'     => '申請中',
    'pending'     => '承認待ち',
    'accepted'    => '承認済み',
    'established' => '試合確定',
    'rejected'    => '拒否済み',
    'canceled'    => 'キャンセル済み',
    'resend'      => '再申請可能',
    'approved'    => '承認済み',
    '試合確定'   => '試合確定',
    '申請中'      => '申請中',
    '承認済み'    => '承認済み',
    'キャンセル済み' => 'キャンセル済み',
    '拒否済み'    => '拒否済み',
    '相手キャンセル' => '相手キャンセル',
  ];

  $classes = [
    'not_applied' => 'badge badge-secondary',
    '未申請'      => 'badge badge-secondary',
    'publish'     => 'badge badge-info',
    'pending'     => 'badge badge-warning',
    'accepted'    => 'badge badge-success',
    'established' => 'badge badge-success',
    'rejected'    => 'badge badge-danger',
    'canceled'    => 'badge badge-dark',
    'resend'      => 'badge badge-primary',
    'approved'    => 'badge badge-success',
    '試合確定'   => 'badge badge-success',
    '申請中'      => 'badge badge-info',
    '承認済み'    => 'badge badge-success',
    'キャンセル済み' => 'badge badge-dark',
    '拒否済み'    => 'badge badge-danger',
    '相手キャンセル' => 'badge badge-warning',
  ];

  $label = $labels[$norm] ?? ($labels[(string) $status] ?? $status);


  if ($format === 'html') {
    $class = $classes[$norm] ?? ($classes[(string) $status] ?? 'badge badge-secondary');
    return '<span class="' . esc_attr($class) . '">' . esc_html($label) . '</span>';
  }

  return $label;
}


/*--------------------------------------------------------------
  No.106 get_board_status_label
  掲示板用ステータスラベル（viewer_role に応じて切り替え）
--------------------------------------------------------------*/
function get_board_status_label($status, $viewer_role = 'other') {
  $norm = function_exists('aidunite_normalize_match_request_status')
    ? aidunite_normalize_match_request_status((string) $status, '')
    : (string) $status;

  $labels = [
    'open'         => '募集中',
    'not_applied'  => $viewer_role === 'self' ? 'マッチ度が高い試合です' : '募集中',
    '未申請'       => $viewer_role === 'self' ? 'マッチ度が高い試合です' : '募集中',
    'publish'      => $viewer_role === 'self' ? '申請中' : ($viewer_role === 'receiver' ? '承認待ち' : '募集中'),
    'pending'      => $viewer_role === 'self' ? '申請中' : ($viewer_role === 'receiver' ? '承認待ち' : '募集中'),
    'accepted'     => $viewer_role === 'self' ? '承認済み' : ($viewer_role === 'receiver' ? '募集中（承認済み）' : '募集中'),
    'established'  => $viewer_role === 'self' ? '試合確定' : ($viewer_role === 'receiver' ? '募集中（試合確定）' : '募集中'),
    'rejected'     => '募集中（再申請可能）',
    'canceled'     => '募集中（再申請可能）',
    'resend'       => '再申請可能',
    'closed'       => '募集終了',
  ];
  return $labels[$norm] ?? ($labels[(string) $status] ?? '未設定');
}


/*--------------------------------------------------------------
  No.107 get_board_id_from_schedule
  スケジュールIDから掲示板IDを取得（post_parent 経由）
--------------------------------------------------------------*/
function get_board_id_from_schedule($schedule_id) {
  $args = [
    'post_type'      => 'match_board',
    'post_parent'    => $schedule_id,
    'posts_per_page' => 1,
    'post_status'    => 'any', // ✅ draft状態でも取得できるように変更
    'fields'         => 'ids',
  ];

  $query = new WP_Query($args);
  $board_id = !empty($query->posts) ? $query->posts[0] : 0;

  error_log("🔍 [No.107] get_board_id_from_schedule: schedule_id={$schedule_id}, found={$board_id}");
  return $board_id;
}


/*--------------------------------------------------------------
  No.108 sync_board_and_request_status
  自動マッチ⇄掲示板 ステータス同期処理（共通関数）
--------------------------------------------------------------*/
/**
 * match_request 保存時など：募集ゲーム（to_schedule_id）単位で掲示板ステータスを同期する。
 * 旧実装は from_schedule_id 側の板へ単一 MR の status をコピーしていたが、複数 MR ゲームと整合しないため廃止。
 *
 * @param int $match_request_id
 * @return bool 募集 schedule が取れて同期できれば true
 */
function sync_board_and_request_status($match_request_id) {
    $match_request_id = (int) $match_request_id;
    if ($match_request_id <= 0) {
        return false;
    }
    $to_schedule_id = (int) get_post_meta($match_request_id, 'to_schedule_id', true);
    if ($to_schedule_id <= 0 || $to_schedule_id === 9999) {
        return false;
    }
    if (!function_exists('aidunite_sync_match_board_status_from_game')) {
        return false;
    }
    aidunite_sync_match_board_status_from_game($to_schedule_id);
    return true;
}


/*--------------------------------------------------------------
  No.109 tunageru_get_own_match_boards()
  自チームの match_board 一覧を取得（post_type=match_board, post_parent=schedule）
--------------------------------------------------------------*/
function aidunite_get_own_match_boards($team_id) {
  $schedules = get_posts([
    'post_type'   => 'schedule',
    'post_status' => 'publish',
    'numberposts' => -1,
    'meta_query'  => [
      ['key' => 'team_id', 'value' => $team_id],
    ],
  ]);

  if (empty($schedules)) return [];

  $schedule_ids = wp_list_pluck($schedules, 'ID');

  $match_boards = get_posts([
    'post_type'   => 'match_board',
    'post_status' => 'publish',
    'numberposts' => -1,
    'post_parent__in' => $schedule_ids
  ]);

  return $match_boards;
}
/*--------------------------------------------------------------
  No.111 tunageru_get_existing_match_request()
  指定された2チーム間＆スケジュール間で既存の match_request を1件取得
--------------------------------------------------------------*/
function aidunite_get_existing_match_request($team_id, $other_team_id, $schedule_id_1, $schedule_id_2) {
  // 複数のパターンでマッチリクエストを検索
  $search_patterns = [
    // パターン1: from_schedule_id と to_schedule_id の組み合わせ
    [
      'meta_query' => [
        'relation' => 'AND',
        [
          'relation' => 'OR',
          ['key' => 'from_team_id', 'value' => $team_id],
          ['key' => 'from_team_id', 'value' => $other_team_id],
        ],
        [
          'relation' => 'AND',
          ['key' => 'from_schedule_id', 'value' => $schedule_id_1],
          ['key' => 'to_schedule_id', 'value' => $schedule_id_2],
        ]
      ],
      'description' => 'from_schedule_id + to_schedule_id (1→2)'
    ],
    // パターン2: 逆方向の組み合わせ
    [
      'meta_query' => [
        'relation' => 'AND',
        [
          'relation' => 'OR',
          ['key' => 'from_team_id', 'value' => $team_id],
          ['key' => 'from_team_id', 'value' => $other_team_id],
        ],
        [
          'relation' => 'AND',
          ['key' => 'from_schedule_id', 'value' => $schedule_id_2],
          ['key' => 'to_schedule_id', 'value' => $schedule_id_1],
        ]
      ],
      'description' => 'from_schedule_id + to_schedule_id (2→1)'
    ],
    // パターン3: 旧形式の my_schedule_id と to_schedule_id
    [
      'meta_query' => [
        'relation' => 'AND',
        [
          'relation' => 'OR',
          ['key' => 'from_team_id', 'value' => $team_id],
          ['key' => 'from_team_id', 'value' => $other_team_id],
        ],
        [
          'relation' => 'AND',
          ['key' => 'my_schedule_id', 'value' => $schedule_id_1],
          ['key' => 'to_schedule_id', 'value' => $schedule_id_2],
        ]
      ],
      'description' => 'my_schedule_id + to_schedule_id (1→2)'
    ],
    // パターン4: 旧形式の逆方向
    [
      'meta_query' => [
        'relation' => 'AND',
        [
          'relation' => 'OR',
          ['key' => 'from_team_id', 'value' => $team_id],
          ['key' => 'from_team_id', 'value' => $other_team_id],
        ],
        [
          'relation' => 'AND',
          ['key' => 'my_schedule_id', 'value' => $schedule_id_2],
          ['key' => 'to_schedule_id', 'value' => $schedule_id_1],
        ]
      ],
      'description' => 'my_schedule_id + to_schedule_id (2→1)'
    ]
  ];

  $latest_request = null;
  $latest_date = 0;
  $found_pattern = '';

  foreach ($search_patterns as $pattern) {
    $args = [
      'post_type' => 'match_request',
      'post_status' => 'any', // publish以外も含める
      'posts_per_page' => 1,
      'meta_query' => $pattern['meta_query'],
      'orderby' => 'date',
      'order' => 'DESC',
    ];

    $requests = get_posts($args);

    if (!empty($requests)) {
      foreach ($requests as $request) {
        $request_date = strtotime($request->post_date);
        if ($request_date > $latest_date) {
          $latest_date = $request_date;
          $latest_request = $request;
          $found_pattern = $pattern['description'];
        }
      }
    }
  }

  if ($latest_request) {
    error_log("[🔍 EXISTING_MATCH] Found: request_id={$latest_request->ID}, pattern={$found_pattern}, team_id={$team_id}, other_team_id={$other_team_id}");
  } else {
    error_log("[🔍 EXISTING_MATCH] Not found: team_id={$team_id}, other_team_id={$other_team_id}, schedule_id_1={$schedule_id_1}, schedule_id_2={$schedule_id_2}");
  }

  return $latest_request;
}

/*--------------------------------------------------------------
  No.113 get_safe_match_status
  match_request の status を安全に取得（空・旧日本語は正規化。未設定は not_applied）
--------------------------------------------------------------*/
function get_safe_match_status($match_request_id) {
  $post = get_post((int) $match_request_id);
  $status = get_post_meta($match_request_id, 'status', true);
  $norm   = function_exists('aidunite_normalize_match_request_status')
    ? aidunite_normalize_match_request_status((string) $status, $post ? $post->post_status : '')
    : (string) $status;
  if ($norm === 'draft') {
    $norm = 'not_applied';
  }
  return $norm !== '' ? $norm : 'not_applied';
}

/*--------------------------------------------------------------
  No.118 tunageru_get_team_profile_info()
  チームの基本情報（名前・紹介・実績など）を取得
--------------------------------------------------------------*/
function aidunite_get_schedule_and_team_info_by_schedule_id($schedule_id) {
  if (!$schedule_id) return [];

  $team_user_id = get_post_field('post_author', $schedule_id);
  $team_id = function_exists('aidunite_resolve_schedule_owner_team_id')
      ? aidunite_resolve_schedule_owner_team_id((int) $schedule_id)
      : (int) get_user_meta($team_user_id, 'team_id', true);

  // マッチステータス情報を取得
  $match_status = '未設定';
  $match_request_id = null;

  // from_schedule_id で検索
  $args_from = [
    'post_type' => 'match_request',
    'post_status' => 'any',
    'posts_per_page' => 1,
    'meta_query' => [
      ['key' => 'from_schedule_id', 'value' => $schedule_id],
    ],
    'orderby' => 'date',
    'order' => 'DESC',
  ];
  $requests_from = get_posts($args_from);

  // to_schedule_id で検索
  $args_to = [
    'post_type' => 'match_request',
    'post_status' => 'any',
    'posts_per_page' => 1,
    'meta_query' => [
      ['key' => 'to_schedule_id', 'value' => $schedule_id],
    ],
    'orderby' => 'date',
    'order' => 'DESC',
  ];
  $requests_to = get_posts($args_to);

  // my_schedule_id で検索（旧形式対応）
  $args_my = [
    'post_type' => 'match_request',
    'post_status' => 'any',
    'posts_per_page' => 1,
    'meta_query' => [
      ['key' => 'my_schedule_id', 'value' => $schedule_id],
    ],
    'orderby' => 'date',
    'order' => 'DESC',
  ];
  $requests_my = get_posts($args_my);

  // 最新のリクエストを取得
  $latest_request = null;
  $latest_date = 0;

  foreach ([$requests_from, $requests_to, $requests_my] as $requests) {
    if (!empty($requests)) {
      foreach ($requests as $request) {
        $request_date = strtotime($request->post_date);
        if ($request_date > $latest_date) {
          $latest_date = $request_date;
          $latest_request = $request;
        }
      }
    }
  }

  if ($latest_request) {
    $match_request_id = $latest_request->ID;
    $status = get_post_meta($latest_request->ID, 'status', true);
    $match_status = get_match_status_label($status);

    error_log("[🔍 MATCH_STATUS] schedule_id={$schedule_id}, request_id={$match_request_id}, status={$status}, label={$match_status}");
  } else {
    error_log("[🔍 MATCH_STATUS] schedule_id={$schedule_id}, マッチリクエストが見つかりません");
  }

  return [
    'schedule_id'   => $schedule_id,
    'team_id'       => $team_id,
    'team_name'     => get_the_title($team_id),
    'team_area'     => get_post_meta($team_id, 'team_area', true),
    'team_intro'    => get_post_meta($team_id, 'team_description', true),
    'match_status'  => $match_status,
    'match_request_id' => $match_request_id,
  ];
}

/*--------------------------------------------------------------
  No.119 指定した from_schedule_id に紐づく match_request を取得（ログ付き）
--------------------------------------------------------------*/
function get_match_requests_by_from_schedule($schedule_id) {
    if (!$schedule_id) {
        error_log('[❌ from_schedule] 無効な schedule_id が指定されました');
        return [];
    }

    $args = [
        'post_type'      => 'match_request',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'meta_query'     => [[
            'key'     => 'from_schedule_id',
            'value'   => $schedule_id,
            'compare' => '='
        ]]
    ];

    $query = new WP_Query($args);
    error_log("[🔍 from_schedule] schedule_id={$schedule_id}, 件数=" . count($query->posts));

    // 各 request ID をログ出力（任意）
    foreach ($query->posts as $post) {
        error_log("[🧾 match_request] ID=" . $post->ID . ", status=" . get_post_meta($post->ID, 'status', true));
    }

    return $query->have_posts() ? $query->posts : [];
}

/*--------------------------------------------------------------
  No.120 スケジュールID・チームIDをもとに、自動マッチ条件を満たすスケジュール（マッチ度は問わない）を返す
--------------------------------------------------------------*/
function aidunite_get_all_matchable_schedules($from_schedule_id, $from_team_id) {
  $from_date = get_post_meta($from_schedule_id, 'schedule_date', true);
  if (!$from_date) {
    error_log("[🔍 ALL_MATCHABLE] 日付が取得できません: from_schedule_id={$from_schedule_id}");
    return [];
  }

  error_log("[🔍 ALL_MATCHABLE] 検索開始: from_schedule_id={$from_schedule_id}, from_team_id={$from_team_id}, date={$from_date}");

  $args = [
    'post_type'      => 'schedule',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'meta_query'     => [
      ['key' => 'schedule_date', 'value' => $from_date],
      ['key' => 'matching', 'value' => '1'], // 単一の値で検索
    ]
  ];

  $query = new WP_Query($args);
  error_log("[🔍 ALL_MATCHABLE] 条件一致スケジュール数: " . count($query->posts));

  $results = [];

  foreach ($query->posts as $post) {
    $to_team_id = function_exists('aidunite_resolve_schedule_owner_team_id')
        ? aidunite_resolve_schedule_owner_team_id((int) $post->ID)
        : (int) get_user_meta($post->post_author, 'team_id', true);
    $matching = get_post_meta($post->ID, 'matching', true);
    error_log("[🔍 ALL_MATCHABLE] チェック: schedule_id={$post->ID}, to_team_id={$to_team_id}, matching={$matching}");

    if ($to_team_id && $to_team_id != $from_team_id) {
      $results[] = $post;
      error_log("[🔍 ALL_MATCHABLE] ✅ 候補追加: schedule_id={$post->ID}");
    }
  }

  error_log("[🔍 ALL_MATCHABLE] 最終候補数: " . count($results));
  return $results;
}

/**
 * 指定したスケジュール同士で既存のmatch_requestがなければ、draft状態で新規作成。
 * 生成したmatch_request_idをmatch_boardのmetaに保存。
 *
 * @param int $from_schedule_id
 * @param int $to_schedule_id
 * @return int|false 生成または既存のmatch_requestのID、失敗時はfalse
 */
function aidunite_generate_match_request_if_not_exists($from_schedule_id, $to_schedule_id) {
    if (!$from_schedule_id || !$to_schedule_id) {
        error_log("[🔍 GENERATE] Invalid parameters: from_schedule_id={$from_schedule_id}, to_schedule_id={$to_schedule_id}");
        return false;
    }

    error_log("[🔍 GENERATE] 開始: {$from_schedule_id} → {$to_schedule_id}");

    // 既存のmatch_requestを検索
    $args = [
        'post_type'      => 'match_request',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'meta_query'     => [
            [ 'key' => 'from_schedule_id', 'value' => $from_schedule_id, 'compare' => '=' ],
            [ 'key' => 'to_schedule_id',   'value' => $to_schedule_id,   'compare' => '=' ],
        ]
    ];
    $query = new WP_Query($args);
    if ($query->have_posts()) {
        // 既存があればそのIDを返す
        $existing_id = $query->posts[0]->ID;
        error_log("[🔍 GENERATE] 既存のリクエストを発見: ID={$existing_id}");
        return $existing_id;
    }

    error_log("[🔍 GENERATE] 新規作成を開始");

    $from_team_id = (int) get_post_meta($from_schedule_id, 'team_id', true);
    $to_team_id = (int) get_post_meta($to_schedule_id, 'team_id', true);

    if (!function_exists('aidunite_match_request_create_placeholder_post')) {
        error_log('[🔍 GENERATE] persist 未読込');
        return false;
    }

    $new_req_id = aidunite_match_request_create_placeholder_post([
        'post_title' => '自動生成: ' . $from_schedule_id . '→' . $to_schedule_id,
        'post_status' => 'draft',
        'status' => 'draft',
        'from_team_id' => $from_team_id,
        'to_team_id' => $to_team_id,
        'other_team_id' => $to_team_id,
        'request_team_id' => $from_team_id,
        'my_schedule_id' => (int) $from_schedule_id,
        'to_schedule_id' => (int) $to_schedule_id,
        'from_schedule_id' => (int) $from_schedule_id,
        'type' => 'auto',
        'is_auto_match' => '1',
    ]);

    if (!$new_req_id || is_wp_error($new_req_id)) {
        $error_msg = is_wp_error($new_req_id) ? $new_req_id->get_error_message() : 'Unknown error';
        error_log("[🔍 GENERATE] 作成失敗: {$error_msg}");
        return false;
    }

    error_log("[🔍 GENERATE] 投稿作成成功: ID={$new_req_id}");
    error_log("[🔍 GENERATE] メタデータ保存完了: from_team_id={$from_team_id}, to_team_id={$to_team_id}");

    // match_boardのmetaにIDを保存（from_schedule_id側のboardに紐付ける想定）
    if (function_exists('get_board_id_from_schedule')) {
        $board_id = get_board_id_from_schedule($from_schedule_id);
        if ($board_id) {
            $ids = get_post_meta($board_id, 'match_request_ids', true);
            if (!is_array($ids)) $ids = [];
            if (!in_array($new_req_id, $ids)) {
                $ids[] = $new_req_id;
                update_post_meta($board_id, 'match_request_ids', $ids);
                error_log("[🔍 GENERATE] ボードにリクエストIDを保存: board_id={$board_id}");
            }
        } else {
            error_log("[🔍 GENERATE] ボードが見つかりません: from_schedule_id={$from_schedule_id}");
        }
    }

    error_log("[🔍 GENERATE] 完了: 新規リクエストID={$new_req_id}");
    return $new_req_id;
}

// match_request保存時にステータス同期
add_action('save_post', function($post_id) {
    // 投稿タイプ確認
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'match_request') return;
    // auto-draftは除外
    if ($post->post_status === 'auto-draft') return;
    // ステータス同期
    sync_board_and_request_status($post_id);
}, 20);

/*--------------------------------------------------------------
  自動マッチ一覧用：条件一致する他チームのスケジュールを取得
--------------------------------------------------------------*/
function aidunite_get_auto_match_candidates_for_archive($my_schedule_id, $my_team_id) {
  if (!$my_schedule_id || !$my_team_id) {
    error_log("[🔍 AUTO_CANDIDATES] Invalid parameters: my_schedule_id={$my_schedule_id}, my_team_id={$my_team_id}");
    return [];
  }

  // 自スケジュールの条件を取得
  // Phase 2: 統一メタキーを優先、後方互換性のために旧キーもフォールバック
  $my_date = get_post_meta($my_schedule_id, 'schedule_date', true);
  $my_gender = get_post_meta($my_schedule_id, 'schedule_gender', true);
  if (!$my_gender) {
    $my_gender = get_post_meta($my_schedule_id, 'matching_gender_condition', true);
  }
  $my_gender_canon = function_exists('aidunite_normalize_gender_canonical')
    ? aidunite_normalize_gender_canonical((string) $my_gender)
    : '';

  $my_place = get_post_meta($my_schedule_id, 'schedule_place', true);
  if (!$my_place) {
    $my_place = get_post_meta($my_schedule_id, 'schedule_place_option', true);
  }

  $my_start = get_post_meta($my_schedule_id, 'schedule_start_time', true);
  $my_end = get_post_meta($my_schedule_id, 'schedule_end_time', true);

  if (!$my_date || !$my_gender || !$my_place || !$my_start || !$my_end) {
    error_log("[🔍 AUTO_CANDIDATES] 自スケジュールの条件が不完全: date={$my_date}, gender={$my_gender}, place={$my_place}, start={$my_start}, end={$my_end}");
    return [];
  }
  if ($my_gender_canon === '') {
    error_log("[🔍 AUTO_CANDIDATES] 自スケジュールの性別が canonical 化できません: raw={$my_gender}");
    return [];
  }

  // 条件一致する他チームのスケジュールを取得
  // 性別は DB 上 male/female/both と日本語旧値が混在するため、クエリでは絞り込まず
  // ループ内で aidunite_schedule_recruit_genders_compatible（募集同士の両立）で判定する
  // （例: 自 male と相手 both を取りこぼさない）
  $args = [
    'post_type' => 'schedule',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'meta_query' => [
      'relation' => 'AND',
      ['key' => 'matching', 'value' => '1'],
      ['key' => 'schedule_date', 'value' => $my_date],
    ],
    'orderby' => 'meta_value',
    'meta_key' => 'schedule_start_time',
    'order' => 'ASC',
  ];

  $other_schedules = get_posts($args);
  $candidates = [];

  foreach ($other_schedules as $other_schedule) {
    $other_schedule_id = $other_schedule->ID;
    $other_author_id = $other_schedule->post_author;
    $other_team_id = function_exists('aidunite_resolve_schedule_owner_team_id')
        ? aidunite_resolve_schedule_owner_team_id((int) $other_schedule_id)
        : (int) get_user_meta($other_author_id, 'team_id', true);

    // 自チームは除外
    if ($other_team_id == $my_team_id) {
      continue;
    }

    $other_gender_raw = get_post_meta($other_schedule_id, 'schedule_gender', true);
    if ($other_gender_raw === '' || $other_gender_raw === null) {
      $other_gender_raw = get_post_meta($other_schedule_id, 'matching_gender_condition', true);
    }
    $other_canon = function_exists('aidunite_normalize_gender_canonical')
      ? aidunite_normalize_gender_canonical((string) $other_gender_raw)
      : '';
    if (!function_exists('aidunite_schedule_recruit_genders_compatible')
        || !aidunite_schedule_recruit_genders_compatible($my_gender_canon, $other_canon)) {
      continue;
    }

    // 会場条件をチェック（ホーム vs アウェイ）
    $other_place = get_post_meta($other_schedule_id, 'schedule_place_option', true);
    $valid_place_combinations = [
      ['home', 'away'],
      ['away', 'home'],
    ];
    if (!in_array([$my_place, $other_place], $valid_place_combinations)) {
      continue;
    }

    // 時間重複をチェック
    $other_start = get_post_meta($other_schedule_id, 'schedule_start_time', true);
    $other_end = get_post_meta($other_schedule_id, 'schedule_end_time', true);

    if ($my_end <= $other_start || $other_end <= $my_start) {
      continue;
    }

    // マッチリクエストの状態を確認
    $match_request = tunageru_get_existing_match_request($my_team_id, $other_team_id, $my_schedule_id, $other_schedule_id);
    $status = $match_request ? get_post_meta($match_request->ID, 'status', true) : 'draft';
    $match_request_id = $match_request ? $match_request->ID : null;

    // キャンセル済みは除外
    if ($status === 'canceled') {
      continue;
    }

    $candidates[] = [
      'schedule_id' => $other_schedule_id,
      'team_id' => $other_team_id,
      'match_request_id' => $match_request_id,
      'status' => $status,
    ];
  }

  error_log("[🔍 AUTO_CANDIDATES] Found " . count($candidates) . " candidates for schedule_id={$my_schedule_id}");
  return $candidates;
}
