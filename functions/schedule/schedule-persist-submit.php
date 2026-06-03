<?php
/**
 * スケジュール登録ウィザード POST の解析・バリデーション・recruit 完了処理（persist 正本の補助）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * schedule_id に紐づく match_board 投稿 ID（draft 含む）
 *
 * @return int 0=なし
 */
function aidunite_schedule_find_match_board_id_for_schedule($schedule_id) {
    $schedule_id = (int) $schedule_id;
    if ($schedule_id < 1) {
        return 0;
    }

    $ids = get_posts([
        'post_type'      => 'match_board',
        'post_status'    => ['draft', 'publish', 'private', 'pending'],
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'     => 'schedule_id',
                'value'   => (string) $schedule_id,
                'compare' => '=',
            ],
        ],
    ]);

    return !empty($ids) ? (int) $ids[0] : 0;
}

/**
 * 新規 recruit 公開後の共通後処理（match_board 作成・高マッチ通知）。既に board があれば再作成しない。
 *
 * @return int match_board post_id（0=作成なし／失敗）
 */
function aidunite_schedule_finalize_new_recruit($schedule_id, $user_id, $team_id = 0) {
    $schedule_id = (int) $schedule_id;
    $user_id = (int) $user_id;
    $team_id = (int) $team_id;

    if ($schedule_id < 1) {
        return 0;
    }

    $intent = (string) get_post_meta($schedule_id, 'intent', true);
    if ($intent !== 'recruit') {
        return 0;
    }

    if ($team_id < 1) {
        $team_id = (int) get_post_meta($schedule_id, 'team_id', true);
    }

    $board_id = aidunite_schedule_find_match_board_id_for_schedule($schedule_id);
    if ($board_id < 1 && function_exists('aidunite_schedule_create_match_board_for_recruit')) {
        $board_id = aidunite_schedule_create_match_board_for_recruit($schedule_id, $user_id);
    }

    if ($team_id > 0 && function_exists('send_high_match_notifications')) {
        send_high_match_notifications($schedule_id, $team_id);
    }

    return (int) $board_id;
}

/**
 * page-schedule-edit ウィザード POST を解析（sanitize のみ。normalize は persist 側）
 *
 * @param array<string, mixed> $post
 * @param array<string, mixed> $context user_id, team_id, trial_simplified, is_team_leader
 * @return array<string, mixed>
 */
function aidunite_schedule_parse_wizard_post(array $post, array $context) {
    $user_id = (int) ($context['user_id'] ?? get_current_user_id());
    $team_id = (int) ($context['team_id'] ?? 0);
    $trial_simplified = !empty($context['trial_simplified']);
    $is_team_leader = !empty($context['is_team_leader']);

    $intent = sanitize_text_field((string) ($post['intent'] ?? ''));
    $schedule_type = sanitize_text_field((string) ($post['schedule_type'] ?? ''));

    if ($intent === 'tentative' && mb_strpos($schedule_type, '（仮）') === false) {
        $schedule_type .= '（仮）';
    } elseif ($intent === 'confirmed') {
        $schedule_type = str_replace('（仮）', '', $schedule_type);
    }

    $start_date = sanitize_text_field((string) ($post['start_date'] ?? ''));
    $end_date = sanitize_text_field((string) ($post['end_date'] ?? ''));
    $start_hour = sanitize_text_field((string) ($post['start_hour'] ?? '13'));
    $start_minute = sanitize_text_field((string) ($post['start_minute'] ?? '00'));
    $end_hour = sanitize_text_field((string) ($post['end_hour'] ?? '14'));
    $end_minute = sanitize_text_field((string) ($post['end_minute'] ?? '00'));
    $venue_condition = sanitize_text_field((string) ($post['venue_condition'] ?? ''));
    $venue_name = sanitize_text_field((string) ($post['venue_name'] ?? ''));
    $gender_condition = sanitize_text_field((string) ($post['gender_condition'] ?? ''));
    $male_teams = (int) ($post['male_teams'] ?? 1);
    $female_teams = (int) ($post['female_teams'] ?? 1);
    $all_confirmed_dates = sanitize_text_field((string) ($post['all_confirmed_dates'] ?? ''));
    $schedule_gender = sanitize_text_field((string) ($post['schedule_gender'] ?? ''));
    $schedule_quick_memo = sanitize_text_field((string) ($post['schedule_quick_memo'] ?? ''));
    $attendance_required = isset($post['attendance_required']) ? '1' : '0';

    $schedule_visibility = sanitize_text_field((string) ($post['schedule_visibility'] ?? 'team'));
    $is_personal = ($schedule_visibility === 'personal') ? '1' : '0';
    if (!$is_team_leader) {
        $is_personal = '1';
    }

    if ($trial_simplified && $is_team_leader) {
        $intent = 'recruit';
        $schedule_visibility = 'team';
        $is_personal = '0';
        $attendance_required = '0';
        if ($gender_condition === '' && function_exists('aidunite_activation_recruit_gender_for_team')) {
            $gender_condition = aidunite_activation_recruit_gender_for_team($team_id);
        }
        if ($gender_condition === 'male') {
            $male_teams = max(1, $male_teams);
            $female_teams = 0;
        } elseif ($gender_condition === 'female') {
            $female_teams = max(1, $female_teams);
            $male_teams = 0;
        }
    }

    if (function_exists('aidunite_schedule_normalize_recruit_fields')) {
        $recruit_fields = aidunite_schedule_normalize_recruit_fields([
            'intent' => $intent,
            'certainty' => ($intent === 'tentative') ? 'tentative' : 'firm',
            'venue_condition' => $venue_condition,
            'gender_condition' => $gender_condition,
        ]);
        $intent = $recruit_fields['intent'];
        $venue_condition = $recruit_fields['venue_condition'];
        $gender_condition = $recruit_fields['gender_condition'];
    }

    $start_time = $start_hour . ':' . $start_minute;
    $end_time = $end_hour . ':' . $end_minute;

    $post_id_from_form = isset($post['post_id']) ? (int) $post['post_id'] : 0;
    $dates_to_process = [];
    if ($post_id_from_form > 0) {
        $dates_to_process = [$start_date];
    } elseif ($all_confirmed_dates !== '') {
        $dates_to_process = array_values(array_filter(array_map('trim', explode(',', $all_confirmed_dates))));
    } elseif ($start_date !== '') {
        $dates_to_process = [$start_date];
    }

    return [
        'user_id' => $user_id,
        'team_id' => $team_id,
        'post_id' => $post_id_from_form,
        'intent' => $intent,
        'schedule_type' => $schedule_type,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'start_time' => $start_time,
        'end_time' => $end_time,
        'start_hour' => $start_hour,
        'start_minute' => $start_minute,
        'end_hour' => $end_hour,
        'end_minute' => $end_minute,
        'venue_condition' => $venue_condition,
        'venue_name' => $venue_name,
        'gender_condition' => $gender_condition,
        'male_teams' => $male_teams,
        'female_teams' => $female_teams,
        'schedule_gender' => $schedule_gender,
        'schedule_quick_memo' => $schedule_quick_memo,
        'attendance_required' => $attendance_required,
        'is_personal' => $is_personal,
        'dates_to_process' => $dates_to_process,
        'is_team_leader' => $is_team_leader,
    ];
}

/**
 * ウィザード送信のサーバーバリデーション（page-schedule-edit / REST v2 共通）
 *
 * @param array<string, mixed> $parsed aidunite_schedule_parse_wizard_post の戻り値、または registration 相当の配列
 * @param int                  $team_id
 * @param array<string, mixed> $opts edit_post_id, skip_duplicate_check
 * @return string[] エラーメッセージ
 */
function aidunite_schedule_validate_wizard_submission(array $parsed, $team_id, array $opts = []) {
    $team_id = (int) $team_id;
    $edit_post_id = (int) ($opts['edit_post_id'] ?? ($parsed['post_id'] ?? 0));
    $skip_duplicate = !empty($opts['skip_duplicate_check']);

    $intent = (string) ($parsed['intent'] ?? '');
    $schedule_type = (string) ($parsed['schedule_type'] ?? ($parsed['type'] ?? ''));
    $start_date = (string) ($parsed['start_date'] ?? ($parsed['date'] ?? ''));
    $start_time = (string) ($parsed['start_time'] ?? '');
    $end_time = (string) ($parsed['end_time'] ?? '');
    $venue_condition = (string) ($parsed['venue_condition'] ?? '');
    $gender_condition = (string) ($parsed['gender_condition'] ?? '');
    $male_teams = (int) ($parsed['male_teams'] ?? ($parsed['male_slots'] ?? 0));
    $female_teams = (int) ($parsed['female_teams'] ?? ($parsed['female_slots'] ?? 0));

    $errors = [];

    if ($intent === '') {
        $errors[] = '目的を選択してください';
    }
    if ($schedule_type === '') {
        $errors[] = '種別を選択してください';
    }
    if ($start_date === '' && empty($parsed['dates_to_process'])) {
        $errors[] = '開始日を入力してください';
    }

    if ($start_time !== '' && $end_time !== '') {
        $sh = (int) ($parsed['start_hour'] ?? 0);
        $sm = (int) ($parsed['start_minute'] ?? 0);
        $eh = (int) ($parsed['end_hour'] ?? 0);
        $em = (int) ($parsed['end_minute'] ?? 0);
        if (!isset($parsed['start_hour']) && preg_match('/^(\d{1,2}):(\d{2})$/', $start_time, $m)) {
            $sh = (int) $m[1];
            $sm = (int) $m[2];
        }
        if (!isset($parsed['end_hour']) && preg_match('/^(\d{1,2}):(\d{2})$/', $end_time, $m2)) {
            $eh = (int) $m2[1];
            $em = (int) $m2[2];
        }
        $start_cmp = $sh * 60 + $sm;
        $end_cmp = $eh * 60 + $em;
        if (!($start_cmp < $end_cmp)) {
            $errors[] = '終了時間は開始時間より後にしてください';
        }
    }

    if ($intent === 'recruit') {
        if ($venue_condition === '') {
            $errors[] = '会場条件は必須です';
        }
        if ($gender_condition === '') {
            $errors[] = '性別条件は必須です';
        }

        if ($gender_condition !== '' && $team_id > 0 && function_exists('aidunite_validate_recruit_gender_for_team')) {
            $gender_team_err = aidunite_validate_recruit_gender_for_team($team_id, $gender_condition);
            if (is_wp_error($gender_team_err)) {
                $errors[] = $gender_team_err->get_error_message();
            }
        }

        if ($venue_condition === 'away') {
            if ($gender_condition === 'male' && $male_teams < 1) {
                $male_teams = 1;
            } elseif ($gender_condition === 'female' && $female_teams < 1) {
                $female_teams = 1;
            }
        }

        $total_recruit = 0;
        if ($gender_condition === 'male') {
            $total_recruit = $male_teams;
        } elseif ($gender_condition === 'female') {
            $total_recruit = $female_teams;
        }
        if ($total_recruit < 1) {
            $errors[] = 'チーム数は必須です';
        }

        if ($edit_post_id > 0 && function_exists('aidunite_schedule_validate_recruit_gender_for_save')) {
            $gender_save = aidunite_schedule_validate_recruit_gender_for_save($edit_post_id, $gender_condition);
            if (is_wp_error($gender_save)) {
                $errors[] = $gender_save->get_error_message();
            }
        }
    }

    if (!$skip_duplicate && $team_id > 0 && $start_date !== '' && $start_time !== '' && $end_time !== '' && $edit_post_id < 1) {
        $existing = get_posts([
            'post_type'      => 'schedule',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'meta_query'     => [
                'relation' => 'AND',
                ['key' => 'team_id', 'value' => $team_id],
                ['key' => 'schedule_date', 'value' => $start_date],
                ['key' => 'schedule_start_time', 'value' => $start_time],
                ['key' => 'schedule_end_time', 'value' => $end_time],
            ],
        ]);
        if (!empty($existing)) {
            $errors[] = '同じ日付・時間帯に既にスケジュールが登録されています。重複を避けるため、時間を変更してください。';
        }
    }

    return $errors;
}

/**
 * 解析済みウィザードデータから persist 用 normalize 入力を1日分生成
 *
 * @return array<string, mixed>
 */
function aidunite_schedule_persist_payload_from_wizard(array $parsed, $date, $user_id, $team_id) {
    $intent = (string) ($parsed['intent'] ?? '');

    return aidunite_schedule_normalize_form_input([
        'intent' => $intent,
        'certainty' => ($intent === 'tentative') ? 'tentative' : 'firm',
        'schedule_type' => (string) ($parsed['schedule_type'] ?? ''),
        'date' => (string) $date,
        'start_time' => (string) ($parsed['start_time'] ?? ''),
        'end_time' => (string) ($parsed['end_time'] ?? ''),
        'venue_condition' => (string) ($parsed['venue_condition'] ?? ''),
        'venue_name' => (string) ($parsed['venue_name'] ?? ''),
        'gender_condition' => (string) ($parsed['gender_condition'] ?? ''),
        'male_teams' => (int) ($parsed['male_teams'] ?? 0),
        'female_teams' => (int) ($parsed['female_teams'] ?? 0),
        'team_id' => (int) $team_id,
        'user_id' => (int) $user_id,
        'is_personal' => (string) ($parsed['is_personal'] ?? '0'),
        'attendance_required' => (string) ($parsed['attendance_required'] ?? '0'),
        'schedule_quick_memo' => (string) ($parsed['schedule_quick_memo'] ?? ''),
        'schedule_gender' => (string) ($parsed['schedule_gender'] ?? ''),
    ]);
}

/**
 * REST register-schedule-v2 用: normalizeScheduleData 後の配列を検証
 *
 * @param array<string, mixed> $data registration normalize 後
 * @return array{valid: bool, errors: string[]}
 */
function aidunite_schedule_validate_registration_data(array $data) {
    $parsed = [
        'intent' => $data['intent'] ?? '',
        'type' => $data['type'] ?? '',
        'schedule_type' => $data['type'] ?? '',
        'date' => $data['date'] ?? '',
        'start_date' => $data['date'] ?? '',
        'start_time' => $data['start_time'] ?? '',
        'end_time' => $data['end_time'] ?? '',
        'venue_condition' => $data['venue_condition'] ?? '',
        'gender_condition' => $data['gender_condition'] ?? '',
        'male_teams' => (int) ($data['male_teams'] ?? 0),
        'female_teams' => (int) ($data['female_teams'] ?? 0),
        'user_id' => (int) ($data['user_id'] ?? 0),
    ];

    if (($parsed['venue_condition'] ?? '') === 'away') {
        if ($parsed['gender_condition'] === 'male' && $parsed['male_teams'] < 1) {
            $parsed['male_teams'] = 1;
        } elseif ($parsed['gender_condition'] === 'female' && $parsed['female_teams'] < 1) {
            $parsed['female_teams'] = 1;
        }
    }

    $team_id = function_exists('aidunite_resolve_user_team_id_for_schedule_ops')
        ? (int) aidunite_resolve_user_team_id_for_schedule_ops((int) $parsed['user_id'])
        : (int) get_user_meta((int) $parsed['user_id'], 'team_id', true);

    $errors = aidunite_schedule_validate_wizard_submission($parsed, $team_id, [
        'skip_duplicate_check' => true,
    ]);

    if (class_exists('AidUniteValidator')) {
        if (!AidUniteValidator::validateDate($data['date'] ?? '')) {
            $errors[] = '無効な日付形式です';
        }
        if (!AidUniteValidator::validateTime($data['start_time'] ?? '')) {
            $errors[] = '無効な開始時間です';
        }
        if (!AidUniteValidator::validateTime($data['end_time'] ?? '')) {
            $errors[] = '無効な終了時間です';
        }
        if (!AidUniteValidator::validateTimeOrder($data['start_time'] ?? '', $data['end_time'] ?? '')) {
            $errors[] = '終了時間は開始時間より後にしてください';
        }
    }
    if ($team_id < 1) {
        $errors[] = 'チームに所属していません';
    }

    $errors = array_values(array_unique($errors));

    return [
        'valid' => empty($errors),
        'errors' => $errors,
    ];
}
