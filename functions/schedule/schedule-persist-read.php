<?php
/**
 * スケジュールメタの読取正規化（表示・REST・管理画面用）
 *
 * 書き込み正本は schedule-persist.php（Phase 4: 旧キーは読取フォールバックのみ）。
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * schedule 投稿の表示用メタを1本化して返す（旧キーはフォールバックのみ）
 *
 * @param int $schedule_id
 * @return array<string, mixed>
 */
function aidunite_schedule_get_canonical_meta($schedule_id) {
    $schedule_id = (int) $schedule_id;
    if ($schedule_id < 1 || get_post_type($schedule_id) !== 'schedule') {
        return [];
    }

    $intent = (string) get_post_meta($schedule_id, 'intent', true);
    if ($intent === '' && function_exists('aidunite_normalize_schedule_intent')) {
        $matching = get_post_meta($schedule_id, 'matching', true);
        $certainty = (string) get_post_meta($schedule_id, 'certainty', true);
        $intent = aidunite_normalize_schedule_intent(
            in_array($matching, [1, '1', true], true) ? 'recruit' : '',
            $certainty
        );
    }

    $place = (string) (get_post_meta($schedule_id, 'schedule_place', true)
        ?: get_post_meta($schedule_id, 'schedule_place_option', true));
    $gender = (string) (get_post_meta($schedule_id, 'schedule_gender', true)
        ?: get_post_meta($schedule_id, 'matching_gender_condition', true)
        ?: get_post_meta($schedule_id, 'gender_condition', true));
    if ($gender !== '' && function_exists('aidunite_normalize_gender_canonical')) {
        $canonical = aidunite_normalize_gender_canonical($gender);
        if ($canonical !== '') {
            $gender = $canonical;
        }
    }

    $memo = function_exists('aidunite_schedule_read_quick_memo_meta')
        ? aidunite_schedule_read_quick_memo_meta($schedule_id)
        : (string) (get_post_meta($schedule_id, 'schedule_quick_memo', true)
            ?: get_post_meta($schedule_id, 'schedule_note', true)
            ?: get_post_meta($schedule_id, 'schedule_memo', true));

    $male_slots = (int) get_post_meta($schedule_id, 'male_slots', true);
    if ($male_slots < 1) {
        $male_slots = (int) (get_post_meta($schedule_id, 'male_teams', true)
            ?: get_post_meta($schedule_id, 'male_capacity', true));
    }
    $female_slots = (int) get_post_meta($schedule_id, 'female_slots', true);
    if ($female_slots < 1) {
        $female_slots = (int) (get_post_meta($schedule_id, 'female_teams', true)
            ?: get_post_meta($schedule_id, 'female_capacity', true));
    }

    $board_id = function_exists('aidunite_schedule_find_match_board_id_for_schedule')
        ? aidunite_schedule_find_match_board_id_for_schedule($schedule_id)
        : 0;

    $date = (string) get_post_meta($schedule_id, 'schedule_date', true);
    $end_date = function_exists('aidunite_schedule_read_end_date_meta')
        ? aidunite_schedule_read_end_date_meta($schedule_id)
        : (string) (get_post_meta($schedule_id, 'schedule_end_date', true) ?: $date);

    return [
        'schedule_id' => $schedule_id,
        'team_id' => (int) get_post_meta($schedule_id, 'team_id', true),
        'date' => $date,
        'end_date' => $end_date,
        'start_time' => (string) get_post_meta($schedule_id, 'schedule_start_time', true),
        'end_time' => (string) get_post_meta($schedule_id, 'schedule_end_time', true),
        'schedule_type' => (string) get_post_meta($schedule_id, 'schedule_type', true),
        'intent' => $intent,
        'certainty' => (string) get_post_meta($schedule_id, 'certainty', true),
        'venue_condition' => $place,
        'schedule_place' => $place,
        'venue_name' => (string) get_post_meta($schedule_id, 'venue_name', true),
        'gender_condition' => $gender,
        'schedule_gender' => $gender,
        'male_slots' => $male_slots,
        'female_slots' => $female_slots,
        'matching' => in_array(get_post_meta($schedule_id, 'matching', true), [1, '1', true], true) ? '1' : '0',
        'is_match_requested' => in_array(get_post_meta($schedule_id, 'is_match_requested', true), [1, '1', true], true) ? '1' : '0',
        'schedule_quick_memo' => $memo,
        'match_board_id' => $board_id,
        'match_board_status' => $board_id > 0 && function_exists('aidunite_match_board_get_canonical_meta')
            ? (string) (aidunite_match_board_get_canonical_meta($board_id)['board_status'] ?? '')
            : ($board_id > 0 ? (string) get_post_meta($board_id, 'match_board_status', true) : ''),
        'is_personal' => get_post_meta($schedule_id, 'is_personal', true) === '1' ? '1' : '0',
        'attendance_required' => get_post_meta($schedule_id, 'attendance_required', true) === '1' ? '1' : '0',
        'schedule_origin' => (string) get_post_meta($schedule_id, 'aidunite_schedule_origin', true),
        'competition_event_id' => (int) get_post_meta($schedule_id, 'competition_event_id', true),
        'competition_entry_id' => (int) get_post_meta($schedule_id, 'competition_entry_id', true),
        'is_competition' => (
            (string) get_post_meta($schedule_id, 'aidunite_schedule_origin', true) === 'competition_entry'
            || (int) get_post_meta($schedule_id, 'competition_event_id', true) > 0
        ),
    ];
}

/**
 * 正本メタと併存している旧キー一覧（移行・診断用）
 *
 * @return string[]
 */
function aidunite_schedule_get_redundant_legacy_meta_keys($schedule_id) {
    $schedule_id = (int) $schedule_id;
    if ($schedule_id < 1) {
        return [];
    }

    $redundant = [];
    if ((string) get_post_meta($schedule_id, 'schedule_gender', true) !== ''
        && (string) get_post_meta($schedule_id, 'matching_gender_condition', true) !== '') {
        $redundant[] = 'matching_gender_condition';
    }
    if ((string) get_post_meta($schedule_id, 'schedule_place', true) !== ''
        && (string) get_post_meta($schedule_id, 'schedule_place_option', true) !== '') {
        $redundant[] = 'schedule_place_option';
    }
    if ((string) get_post_meta($schedule_id, 'male_slots', true) !== ''
        && (string) get_post_meta($schedule_id, 'male_teams', true) !== '') {
        $redundant[] = 'male_teams';
    }
    if ((string) get_post_meta($schedule_id, 'female_slots', true) !== ''
        && (string) get_post_meta($schedule_id, 'female_teams', true) !== '') {
        $redundant[] = 'female_teams';
    }
    if ((string) get_post_meta($schedule_id, 'schedule_quick_memo', true) !== ''
        && (string) get_post_meta($schedule_id, 'schedule_note', true) !== '') {
        $redundant[] = 'schedule_note';
    }
    if ((string) get_post_meta($schedule_id, 'schedule_quick_memo', true) !== ''
        && (string) get_post_meta($schedule_id, 'schedule_memo', true) !== '') {
        $redundant[] = 'schedule_memo';
    }

    return $redundant;
}

/**
 * 画面テンプレート向けの表示用フィールド（canonical 1本化）
 *
 * @param int $schedule_id
 * @return array<string, mixed>
 */
/**
 * スケジュール種別ラベルの短縮（カレンダーカード表示用）
 *
 * @param string $schedule_type
 * @return string
 */
function aidunite_schedule_shorten_type_label($schedule_type) {
    $title = trim((string) $schedule_type);
    if ($title === '') {
        return '';
    }
    $short = [
        '練習' => '練習',
        '練習（仮）' => '練習（仮）',
        '公式試合' => '公式試合',
        '練習試合' => '練習試合',
        '練習試合（募集）' => '練習試合（募）',
        '練習試合（募）' => '練習試合（募）',
        '練習試合（仮）' => '練習試合（仮）',
        '合同練習' => '合同練習',
        '合同練習（募集）' => '合同練習（募）',
        '合同練習（募）' => '合同練習（募）',
        '合同練習（仮）' => '合同練習（仮）',
        '合宿' => '合宿',
        '合宿（仮）' => '合宿（仮）',
        '遠征' => '遠征',
        '遠征（仮）' => '遠征（仮）',
        '休み' => '休み',
        '休み（仮）' => '休み（仮）',
        'イベント' => 'イベント',
        'イベント（仮）' => 'イベント（仮）',
    ];

    return $short[$title] ?? $title;
}

/**
 * 種別名から「（仮）」を除く
 *
 * @param string $schedule_type
 * @return string
 */
function aidunite_schedule_strip_tentative_type_suffix($schedule_type) {
    return trim(preg_replace('/（仮）|\(仮\)/u', '', (string) $schedule_type));
}

/**
 * 時間範囲の表示文字列
 *
 * @param string $start_time
 * @param string $end_time
 * @param string $style calendar|list
 * @return string
 */
function aidunite_schedule_format_time_range_display($start_time, $end_time, $style = 'calendar') {
    $start = trim((string) $start_time);
    $end = trim((string) $end_time);
    $sep = $style === 'list' ? ' - ' : '〜';
    if ($start !== '' && $end !== '') {
        return $start . $sep . $end;
    }
    if ($start !== '' || $end !== '') {
        return $start !== '' ? $start : $end;
    }

    return '';
}

/**
 * カレンダーカード用の種別フラグ
 *
 * @param string $schedule_type
 * @param string $ui_kind
 * @return array{is_practice:bool,is_match:bool,is_meeting:bool,is_event:bool,is_off:bool}
 */
function aidunite_schedule_get_type_flags_for_card($schedule_type, $ui_kind = '') {
    $t = trim((string) $schedule_type);
    $is_joint = strpos($t, '合同練習') !== false;

    return [
        'is_practice' => strpos($t, '練習') !== false
            && strpos($t, '練習試合') === false
            && strpos($t, '公式試合') === false
            && !$is_joint,
        'is_match' => strpos($t, '公式試合') !== false
            || strpos($t, '練習試合') !== false
            || strpos($t, '公式戦') !== false,
        'is_meeting' => strpos($t, 'ミーティング') !== false || strpos($t, '会議') !== false,
        'is_event' => strpos($t, '合宿') !== false
            || strpos($t, '遠征') !== false
            || strpos($t, 'イベント') !== false
            || $is_joint,
        'is_off' => $ui_kind === 'rest' || strpos($t, '休み') !== false,
    ];
}

/**
 * カレンダーカードのステータスラベル（一覧バッジ用）
 *
 * @param string $intent
 * @param string $schedule_type
 * @param array{is_practice:bool,is_match:bool,is_meeting:bool,is_event:bool,is_off:bool} $flags
 * @return string
 */
function aidunite_schedule_resolve_card_status_label($intent, $schedule_type, array $flags) {
    if ($intent === 'tentative') {
        $base = aidunite_schedule_strip_tentative_type_suffix($schedule_type);
        $label = aidunite_schedule_shorten_type_label($base) ?: ($base ?: '予定');

        return '仮　' . $label;
    }
    if (!empty($flags['is_off'])) {
        return '休み';
    }
    if (!empty($flags['is_meeting'])) {
        return 'ミーティング';
    }
    if (!empty($flags['is_event'])) {
        return aidunite_schedule_shorten_type_label($schedule_type) ?: 'イベント';
    }
    if ($intent === 'recruit') {
        return '未確定';
    }
    if ($intent === 'confirmed' && !empty($flags['is_match'])) {
        if (strpos($schedule_type, '練習試合') !== false) {
            return '練習試合';
        }

        return '試合確定';
    }
    if (!empty($flags['is_practice'])) {
        return '通常練習';
    }

    return aidunite_schedule_shorten_type_label($schedule_type) ?: '予定';
}

/**
 * カレンダーカードの modifier（CSS 用）
 *
 * @param string $intent
 * @param array{is_practice:bool,is_match:bool,is_meeting:bool,is_event:bool,is_off:bool} $flags
 * @return string
 */
function aidunite_schedule_resolve_card_modifier($intent, array $flags) {
    if ($intent === 'tentative' || !empty($flags['is_off'])) {
        return 'off';
    }
    if (!empty($flags['is_meeting'])) {
        return 'meeting';
    }
    if (!empty($flags['is_event'])) {
        return 'event';
    }
    if ($intent === 'recruit') {
        return 'match-recruit';
    }
    if ($intent === 'confirmed' && !empty($flags['is_match'])) {
        return 'match-confirmed';
    }

    return 'practice';
}

/**
 * カレンダー・リスト共通の2行表示（persist-read 正本）
 *
 * @param int   $schedule_id
 * @param array $context team_name,start_time,end_time,opponent_display,ui_schedule_kind,multi_team,bundle
 * @return array{card_line1:string,card_line2:string,status_label:string,list_time_label:string,card_modifier:string,card_class:string}
 */
function aidunite_schedule_get_calendar_card_display($schedule_id, array $context = []) {
    $schedule_id = (int) $schedule_id;
    if ($schedule_id < 1 || get_post_type($schedule_id) !== 'schedule') {
        return [];
    }

    $bundle = isset($context['bundle']) && is_array($context['bundle'])
        ? $context['bundle']
        : aidunite_schedule_get_display_bundle($schedule_id);
    if ($bundle === []) {
        return [];
    }

    $intent = function_exists('aidunite_normalize_schedule_intent')
        ? aidunite_normalize_schedule_intent((string) ($bundle['intent'] ?? ''), (string) ($bundle['certainty'] ?? ''))
        : (string) ($bundle['intent'] ?? 'confirmed');
    $schedule_type = (string) ($bundle['schedule_type'] ?? '');
    $start_time = (string) ($context['start_time'] ?? $bundle['start_time'] ?? '');
    $end_time = (string) ($context['end_time'] ?? $bundle['end_time'] ?? '');
    $team_name = trim((string) ($context['team_name'] ?? ''));
    if ($team_name === '') {
        $team_id = (int) ($bundle['team_id'] ?? 0);
        if ($team_id > 0) {
            $team_post = get_post($team_id);
            if ($team_post && $team_post->post_type === 'team') {
                $team_name = (string) $team_post->post_title;
            }
        }
    }

    $ui_kind = (string) ($context['ui_schedule_kind'] ?? '');
    if ($ui_kind === '' && function_exists('aidunite_schedule_infer_ui_kind')) {
        $ui_kind = aidunite_schedule_infer_ui_kind($intent, $schedule_type);
    }

    $time_calendar = aidunite_schedule_format_time_range_display($start_time, $end_time, 'calendar');
    $time_list = aidunite_schedule_format_time_range_display($start_time, $end_time, 'list');
    $flags = aidunite_schedule_get_type_flags_for_card($schedule_type, $ui_kind);
    $modifier = aidunite_schedule_resolve_card_modifier($intent, $flags);
    $card_class = 'schedule-card ' . $modifier;

    if ($intent === 'recruit') {
        $line1 = $team_name !== '' ? '未確定　' . $team_name : '未確定';
        $line2 = $time_calendar !== '' ? '試合の募集　' . $time_calendar : '試合の募集';
        $list_time = $time_list !== '' ? '試合の募集　' . $time_list : '試合の募集';

        return [
            'card_line1' => $line1,
            'card_line2' => $line2,
            'status_label' => '未確定',
            'list_time_label' => $list_time,
            'card_modifier' => $modifier,
            'card_class' => $card_class,
        ];
    }

    if ($intent === 'tentative') {
        $base = aidunite_schedule_strip_tentative_type_suffix($schedule_type);
        $label = aidunite_schedule_shorten_type_label($base) ?: ($base ?: '予定');
        $line1 = '仮　';
        if ($team_name !== '') {
            $line1 .= $team_name . '　';
        }
        $line1 .= $label;

        return [
            'card_line1' => $line1,
            'card_line2' => $time_calendar,
            'status_label' => '仮　' . $label,
            'list_time_label' => $time_list,
            'card_modifier' => $modifier,
            'card_class' => $card_class,
        ];
    }

    $status_label = aidunite_schedule_resolve_card_status_label($intent, $schedule_type, $flags);
    $show_team = !empty($context['multi_team']);
    $line1 = ($show_team && $team_name !== '') ? $team_name . '　' . $status_label : $status_label;
    $line2 = $time_calendar;

    $opponent = trim((string) ($context['opponent_display'] ?? ''));
    if ($opponent === '' && function_exists('aidunite_schedule_opponent_display_for_ui')) {
        $opponent = aidunite_schedule_opponent_display_for_ui($schedule_id);
    }
    if ($intent === 'confirmed' && !empty($flags['is_match']) && $opponent !== '') {
        $line2 = $time_calendar !== '' ? $time_calendar . '　' . $opponent : $opponent;
    }

    return [
        'card_line1' => $line1,
        'card_line2' => $line2,
        'status_label' => $status_label,
        'list_time_label' => $time_list,
        'card_modifier' => $modifier,
        'card_class' => $card_class,
    ];
}

/**
 * REST・match-common 向けの表示用フィールド（canonical 1本）
 *
 * @param int $schedule_id
 * @return array<string, mixed>
 */
function aidunite_schedule_get_api_display_fields($schedule_id) {
    $list = function_exists('aidunite_schedule_get_market_list_display')
        ? aidunite_schedule_get_market_list_display((int) $schedule_id)
        : [];
    $base = [];
    if ($list !== []) {
        $base = [
            'team_id'     => (int) ($list['team_id'] ?? 0),
            'date'        => (string) ($list['date'] ?? ''),
            'start_time'  => (string) ($list['start_time'] ?? ''),
            'end_time'    => (string) ($list['end_time'] ?? ''),
            'place'       => (string) ($list['place'] ?? ''),
            'gender'      => (string) ($list['gender'] ?? ''),
            'venue_name'  => (string) ($list['venue_name'] ?? ''),
            'matching'    => (string) ($list['matching'] ?? '0'),
            'intent'      => function_exists('aidunite_schedule_read_intent')
                ? aidunite_schedule_read_intent((int) $schedule_id)
                : (string) get_post_meta((int) $schedule_id, 'intent', true),
        ];
    } else {
        $bundle = aidunite_schedule_get_display_bundle((int) $schedule_id);
        $base = [
            'team_id'     => (int) ($bundle['team_id'] ?? (function_exists('aidunite_schedule_read_team_id')
                ? aidunite_schedule_read_team_id((int) $schedule_id)
                : 0)),
            'date'        => function_exists('aidunite_schedule_read_normalized_date')
                ? aidunite_schedule_read_normalized_date((int) $schedule_id)
                : (string) ($bundle['date'] ?? ''),
            'start_time'  => (string) ($bundle['start_time'] ?? ''),
            'end_time'    => (string) ($bundle['end_time'] ?? ''),
            'place'       => function_exists('aidunite_schedule_read_place_raw')
                ? aidunite_schedule_read_place_raw((int) $schedule_id)
                : (string) ($bundle['place'] ?? ''),
            'gender'      => function_exists('aidunite_schedule_read_gender_raw')
                ? aidunite_schedule_read_gender_raw((int) $schedule_id)
                : (string) ($bundle['gender'] ?? ''),
            'venue_name'  => (string) ($bundle['venue_name'] ?? get_post_meta((int) $schedule_id, 'venue_name', true)),
            'matching'    => (string) ($bundle['matching'] ?? '0'),
            'intent'      => function_exists('aidunite_schedule_read_intent')
                ? aidunite_schedule_read_intent((int) $schedule_id)
                : (string) ($bundle['intent'] ?? ''),
        ];
    }

    $card = aidunite_schedule_get_calendar_card_display((int) $schedule_id, [
        'start_time' => (string) ($base['start_time'] ?? ''),
        'end_time' => (string) ($base['end_time'] ?? ''),
    ]);

    return array_merge($base, $card);
}

function aidunite_schedule_get_market_list_display($schedule_id) {
    $bundle = aidunite_schedule_get_display_bundle((int) $schedule_id);
    if ($bundle === []) {
        return [];
    }

    return [
        'team_id' => (int) ($bundle['team_id'] ?? 0),
        'date' => (string) ($bundle['date'] ?? ''),
        'start_time' => (string) ($bundle['start_time'] ?? ''),
        'end_time' => (string) ($bundle['end_time'] ?? ''),
        'place' => (string) ($bundle['place'] ?? ''),
        'gender' => (string) ($bundle['gender'] ?? ''),
        'venue_name' => (string) ($bundle['venue_name'] ?? ''),
        'matching' => (string) ($bundle['matching'] ?? '0'),
    ];
}

function aidunite_schedule_get_display_bundle($schedule_id) {
    $canonical = aidunite_schedule_get_canonical_meta($schedule_id);
    if ($canonical === []) {
        return [];
    }

    $legacy_time = (string) get_post_meta((int) $schedule_id, 'schedule_time', true);

    return [
        'team_id' => (int) ($canonical['team_id'] ?? 0),
        'date' => (string) ($canonical['date'] ?? ''),
        'end_date' => (string) ($canonical['end_date'] ?? ''),
        'start_time' => (string) ($canonical['start_time'] ?? ''),
        'end_time' => (string) ($canonical['end_time'] ?? ''),
        'legacy_time' => $legacy_time,
        'schedule_type' => (string) ($canonical['schedule_type'] ?? ''),
        'intent' => (string) ($canonical['intent'] ?? ''),
        'certainty' => (string) ($canonical['certainty'] ?? ''),
        'place' => (string) ($canonical['schedule_place'] ?? ''),
        'venue_name' => (string) ($canonical['venue_name'] ?? ''),
        'gender' => (string) ($canonical['schedule_gender'] ?? ''),
        'matching' => (string) ($canonical['matching'] ?? '0'),
        'memo' => (string) ($canonical['schedule_quick_memo'] ?? ''),
        'is_personal' => (string) ($canonical['is_personal'] ?? '0'),
        'attendance_required' => (string) ($canonical['attendance_required'] ?? '0'),
        'schedule_origin' => (string) ($canonical['schedule_origin'] ?? ''),
    ];
}

/**
 * 成立前スナップショット優先の時間・会場・性別（マッチ詳細等）
 *
 * @param int $schedule_id
 * @return array{start:string, end:string, place:string, gender:string}
 */
/**
 * マッチ照合・掲示板用の正規化日付（Y-m-d）
 *
 * @param int $schedule_id
 * @return string
 */
function aidunite_schedule_read_normalized_date($schedule_id) {
    $schedule_id = (int) $schedule_id;
    $raw = '';
    if (function_exists('aidunite_schedule_get_display_bundle')) {
        $bundle = aidunite_schedule_get_display_bundle($schedule_id);
        $raw = (string) ($bundle['date'] ?? '');
    }
    if ($raw === '') {
        $raw = (string) get_post_meta($schedule_id, 'schedule_date', true);
    }

    return function_exists('_aidunite_match_apply_normalize_schedule_date')
        ? (string) _aidunite_match_apply_normalize_schedule_date($raw)
        : $raw;
}

/**
 * @param int $schedule_id
 * @return string
 */
function aidunite_schedule_read_intent($schedule_id) {
    $schedule_id = (int) $schedule_id;
    if (function_exists('aidunite_schedule_get_display_bundle')) {
        $bundle = aidunite_schedule_get_display_bundle($schedule_id);
        $intent = (string) ($bundle['intent'] ?? '');
        if ($intent !== '') {
            return $intent;
        }
    }

    return (string) get_post_meta($schedule_id, 'intent', true);
}

/**
 * @param int $schedule_id
 * @return string schedule_place 相当（未正規化）
 */
function aidunite_schedule_read_place_raw($schedule_id) {
    $schedule_id = (int) $schedule_id;
    if (function_exists('aidunite_schedule_get_display_bundle')) {
        $bundle = aidunite_schedule_get_display_bundle($schedule_id);
        $place = (string) ($bundle['place'] ?? '');
        if ($place !== '') {
            return $place;
        }
    }

    return (string) (get_post_meta($schedule_id, 'schedule_place', true)
        ?: get_post_meta($schedule_id, 'schedule_place_option', true));
}

/**
 * @param int $schedule_id
 * @return string
 */
/**
 * @param int $schedule_id
 * @return int
 */
function aidunite_schedule_read_team_id($schedule_id) {
    $schedule_id = (int) $schedule_id;
    if (function_exists('aidunite_schedule_get_display_bundle')) {
        $bundle = aidunite_schedule_get_display_bundle($schedule_id);
        $team_id = (int) ($bundle['team_id'] ?? 0);
        if ($team_id > 0) {
            return $team_id;
        }
    }

    return (int) get_post_meta($schedule_id, 'team_id', true);
}

function aidunite_schedule_read_gender_raw($schedule_id) {
    $schedule_id = (int) $schedule_id;
    if (function_exists('aidunite_schedule_get_display_bundle')) {
        $bundle = aidunite_schedule_get_display_bundle($schedule_id);
        $gender = (string) ($bundle['gender'] ?? '');
        if ($gender !== '') {
            return $gender;
        }
    }

    return (string) (get_post_meta($schedule_id, 'schedule_gender', true)
        ?: get_post_meta($schedule_id, 'matching_gender_condition', true));
}

function aidunite_schedule_get_registered_snapshot_fields($schedule_id) {
    $schedule_id = (int) $schedule_id;
    if ($schedule_id < 1 || get_post_type($schedule_id) !== 'schedule') {
        return ['start' => '', 'end' => '', 'place' => '', 'gender' => ''];
    }

    $saved = ((string) get_post_meta($schedule_id, 'pre_established_saved', true) === '1');
    if ($saved) {
        $start = (string) get_post_meta($schedule_id, 'pre_established_start_time', true);
        $end = (string) get_post_meta($schedule_id, 'pre_established_end_time', true);
        $place = (string) get_post_meta($schedule_id, 'pre_established_place', true);
        if ($place === '') {
            $place = (string) get_post_meta($schedule_id, 'pre_established_place_option', true);
        }
        $gender = (string) get_post_meta($schedule_id, 'pre_established_gender', true);
    } else {
        $bundle = aidunite_schedule_get_display_bundle($schedule_id);
        $start = (string) ($bundle['start_time'] ?? '');
        $end = (string) ($bundle['end_time'] ?? '');
        $place = (string) ($bundle['place'] ?? '');
        $gender = (string) ($bundle['gender'] ?? '');
    }

    if ($gender === '') {
        $gender = (string) get_post_meta($schedule_id, 'matching_gender_condition', true);
    }

    return [
        'start' => $start,
        'end' => $end,
        'place' => $place,
        'gender' => $gender,
    ];
}

/**
 * 募集 schedule に紐づく match_request ID 一覧（to_schedule_id 基準）
 *
 * @return int[]
 */
function aidunite_schedule_get_linked_match_request_ids($schedule_id) {
    $schedule_id = (int) $schedule_id;
    if ($schedule_id < 1) {
        return [];
    }

    $ids = get_posts([
        'post_type'      => 'match_request',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'     => 'to_schedule_id',
                'value'   => (string) $schedule_id,
                'compare' => '=',
            ],
        ],
    ]);

    return array_map('intval', is_array($ids) ? $ids : []);
}

/**
 * WP REST schedule の meta に canonical エイリアスを付与（編集 JS・旧キー互換を一本化）
 *
 * @param WP_REST_Response $response
 * @param WP_Post          $post
 * @return WP_REST_Response
 */
function aidunite_schedule_rest_merge_canonical_meta_aliases($response, $post) {
    if (!$post instanceof WP_Post || $post->post_type !== 'schedule') {
        return $response;
    }
    if (!function_exists('aidunite_schedule_get_display_bundle')) {
        return $response;
    }
    $bundle = aidunite_schedule_get_display_bundle((int) $post->ID);
    if ($bundle === []) {
        return $response;
    }

    $data = $response->get_data();
    if (!isset($data['meta']) || !is_array($data['meta'])) {
        $data['meta'] = [];
    }

    $date = (string) ($bundle['date'] ?? '');
    $start = (string) ($bundle['start_time'] ?? '');
    $end = (string) ($bundle['end_time'] ?? '');
    $place = (string) ($bundle['place'] ?? '');
    $gender = (string) ($bundle['gender'] ?? '');
    $type = (string) ($bundle['schedule_type'] ?? '');
    $memo = (string) ($bundle['memo'] ?? '');

    $data['meta'] = array_merge($data['meta'], [
        'date' => $date,
        'start_time' => $start,
        'end_time' => $end,
        'place' => $place,
        'venue_condition' => $place,
        'gender' => $gender,
        'gender_condition' => $gender,
        'memo' => $memo,
        'schedule_date' => $date,
        'schedule_start_time' => $start,
        'schedule_end_time' => $end,
        'schedule_place' => $place,
        'schedule_gender' => $gender,
        'schedule_type' => $type,
        'schedule_quick_memo' => $memo,
    ]);
    $response->set_data($data);

    return $response;
}

/**
 * スケジュール一覧行の追加表示（workflow / analytics / participants）
 *
 * @param int $schedule_id
 * @return array<string, mixed>
 */
function aidunite_schedule_get_list_row_display($schedule_id) {
    $schedule_id = (int) $schedule_id;
    if ($schedule_id <= 0 || get_post_type($schedule_id) !== 'schedule') {
        return [];
    }

    $participants_display = aidunite_schedule_get_participants_display($schedule_id);
    $analytics = aidunite_schedule_get_analytics_meta($schedule_id);

    $card = aidunite_schedule_get_calendar_card_display($schedule_id);

    return array_merge($participants_display, $analytics, $card, [
        'capacity' => (string) get_post_meta($schedule_id, 'capacity', true),
        'participation_fee' => (string) get_post_meta($schedule_id, 'participation_fee', true),
        'cancellation_reason' => (string) get_post_meta($schedule_id, 'cancellation_reason', true),
        'schedule_status' => aidunite_schedule_read_schedule_status($schedule_id),
    ]);
}

/**
 * ワークフロー用 schedule_status（分析・workflow メタ）
 *
 * @param int $schedule_id
 * @return string
 */
function aidunite_schedule_read_schedule_status($schedule_id) {
    return (string) get_post_meta((int) $schedule_id, 'schedule_status', true);
}

/**
 * PV 系 analytics メタ（一覧集計用）
 *
 * @param int $schedule_id
 * @return array{view_count:int, unique_visitors:int}
 */
function aidunite_schedule_get_analytics_meta($schedule_id) {
    $schedule_id = (int) $schedule_id;

    return [
        'view_count' => (int) (get_post_meta($schedule_id, 'view_count', true) ?: 0),
        'unique_visitors' => (int) (get_post_meta($schedule_id, 'unique_visitors', true) ?: 0),
    ];
}

/**
 * 参加チーム participants 表示用
 *
 * @param int $schedule_id
 * @return array{participants:string, participant_count:int, participants_list:string}
 */
function aidunite_schedule_get_participants_display($schedule_id) {
    $schedule_id = (int) $schedule_id;
    $participants_raw = (string) get_post_meta($schedule_id, 'participants', true);
    $participant_count = 0;
    $participants_list = '-';

    if ($participants_raw !== '') {
        $participant_ids = array_filter(array_map('trim', explode(',', $participants_raw)));
        $participant_count = count($participant_ids);
        if ($participant_count > 0) {
            $participant_names = [];
            foreach ($participant_ids as $pid) {
                $team_post = get_post((int) $pid);
                if ($team_post) {
                    $participant_names[] = $team_post->post_title;
                }
            }
            $participants_list = implode(', ', $participant_names);
        }
    }

    return [
        'participants' => $participants_raw,
        'participant_count' => $participant_count,
        'participants_list' => $participants_list,
    ];
}

/**
 * UI 5区分 → intent / schedule_type（保存正本）
 *
 * @param string $ui_kind recruit|practice|match|rest|tentative
 * @return array{intent:string,schedule_type:string,certainty?:string}
 */
function aidunite_schedule_map_ui_kind_to_meta($ui_kind) {
    $ui_kind = sanitize_key((string) $ui_kind);
    $map = [
        'recruit' => [
            'intent' => 'recruit',
            'schedule_type' => '練習試合（募集）',
        ],
        'practice' => [
            'intent' => 'confirmed',
            'schedule_type' => '練習',
        ],
        'meeting' => [
            'intent' => 'confirmed',
            'schedule_type' => 'ミーティング',
        ],
        'rest' => [
            'intent' => 'confirmed',
            'schedule_type' => '休み',
        ],
        'official_match' => [
            'intent' => 'confirmed',
            'schedule_type' => '公式試合',
        ],
        'practice_match' => [
            'intent' => 'confirmed',
            'schedule_type' => '練習試合',
        ],
        'joint_practice' => [
            'intent' => 'confirmed',
            'schedule_type' => '合同練習',
        ],
        'camp' => [
            'intent' => 'confirmed',
            'schedule_type' => '合宿',
        ],
        'expedition' => [
            'intent' => 'confirmed',
            'schedule_type' => '遠征',
        ],
        'tentative' => [
            'intent' => 'tentative',
            'schedule_type' => '練習（仮）',
            'certainty' => 'tentative',
        ],
        // 後方互換
        'match' => [
            'intent' => 'confirmed',
            'schedule_type' => '練習試合',
        ],
    ];

    return $map[$ui_kind] ?? [];
}

/**
 * 仮予定の内訳種別 → schedule_type（（仮）付き）
 *
 * @param string $base_kind practice|rest|meeting|...
 * @param string $explicit_type クライアントから渡された schedule_type
 * @return string
 */
function aidunite_schedule_resolve_tentative_schedule_type($base_kind, $explicit_type = '') {
    $explicit_type = trim((string) $explicit_type);
    if ($explicit_type !== '') {
        return sanitize_text_field($explicit_type);
    }

    $base_kind = sanitize_key((string) $base_kind);
    if ($base_kind !== '' && function_exists('aidunite_schedule_map_ui_kind_to_meta')) {
        $base_meta = aidunite_schedule_map_ui_kind_to_meta($base_kind);
        if (!empty($base_meta['schedule_type'])) {
            $type = (string) $base_meta['schedule_type'];
            if (mb_strpos($type, '（仮）') === false && strpos($type, '(仮)') === false) {
                $type .= '（仮）';
            }
            return $type;
        }
    }

    return '練習（仮）';
}

/**
 * 終日（時間なし）を許可する UI 種別
 *
 * @param string $ui_kind
 * @return bool
 */
function aidunite_schedule_ui_kind_allows_all_day($ui_kind) {
    $ui_kind = sanitize_key((string) $ui_kind);
    return in_array($ui_kind, ['rest', 'tentative', 'camp', 'expedition'], true);
}

/**
 * 保存データが終日（時間なし）を許可する種別か
 *
 * @param array<string, mixed> $data
 * @return bool
 */
function aidunite_schedule_data_allows_all_day(array $data) {
    if (!empty($data['ui_schedule_kind']) && aidunite_schedule_ui_kind_allows_all_day((string) $data['ui_schedule_kind'])) {
        return true;
    }
    $intent = function_exists('aidunite_normalize_schedule_intent')
        ? aidunite_normalize_schedule_intent((string) ($data['intent'] ?? ''))
        : strtolower(trim((string) ($data['intent'] ?? '')));
    $type = trim((string) ($data['schedule_type'] ?? ($data['type'] ?? '')));
    if ($intent === 'tentative' || preg_match('/（仮）|\(仮\)/u', $type)) {
        return true;
    }
    if (preg_match('/(休み|合宿|遠征)/u', $type)) {
        return true;
    }
    return false;
}

/**
 * 保存済みメタから UI 5区分を推定
 *
 * @param string $intent
 * @param string $schedule_type
 * @return string
 */
function aidunite_schedule_infer_ui_kind($intent, $schedule_type) {
    $intent = function_exists('aidunite_normalize_schedule_intent')
        ? aidunite_normalize_schedule_intent($intent)
        : strtolower(trim((string) $intent));
    $type = trim((string) $schedule_type);

    if ($intent === 'recruit' || preg_match('/（募集）|\(募集\)|（募）|\(募\)/u', $type)) {
        return 'recruit';
    }
    if ($intent === 'tentative' || preg_match('/（仮）|\(仮\)/u', $type)) {
        return 'tentative';
    }
    if ($type === '休み' || strpos($type, '休み') === 0) {
        return 'rest';
    }
    if (preg_match('/公式試合/u', $type) && !preg_match('/募集/u', $type)) {
        return 'official_match';
    }
    if (preg_match('/練習試合/u', $type) && !preg_match('/募集/u', $type)) {
        return 'practice_match';
    }
    if (preg_match('/合同練習/u', $type) && !preg_match('/募集/u', $type)) {
        return 'joint_practice';
    }
    if (preg_match('/合宿/u', $type)) {
        return 'camp';
    }
    if (preg_match('/遠征/u', $type)) {
        return 'expedition';
    }
    if (preg_match('/ミーティング|会議/u', $type)) {
        return 'meeting';
    }

    return 'practice';
}

/**
 * register-schedule-v2 成功時の軽量 payload（詳細リンク解決を避ける）
 *
 * @param int                  $schedule_id
 * @param array<string, mixed> $data registration normalize 後
 * @return array<string, mixed>
 */
function aidunite_schedule_get_rest_register_minimal_payload($schedule_id, array $data = []) {
    $schedule_id = (int) $schedule_id;
    $intent = (string) ($data['intent'] ?? '');
    $type = (string) ($data['type'] ?? '');
    if ($type === '' && $schedule_id > 0) {
        $type = (string) get_post_meta($schedule_id, 'schedule_type', true);
    }
    $ui_kind = 'practice';
    if ($intent === 'recruit') {
        $ui_kind = 'recruit';
    } elseif (function_exists('aidunite_schedule_infer_ui_kind')) {
        $ui_kind = aidunite_schedule_infer_ui_kind($intent, $type);
    }

    return [
        'id' => $schedule_id,
        'schedule_id' => $schedule_id,
        'post_id' => $schedule_id,
        'date' => (string) ($data['date'] ?? get_post_meta($schedule_id, 'schedule_date', true)),
        'start_time' => (string) ($data['start_time'] ?? get_post_meta($schedule_id, 'schedule_start_time', true)),
        'end_time' => (string) ($data['end_time'] ?? get_post_meta($schedule_id, 'schedule_end_time', true)),
        'type' => $type,
        'schedule_type' => $type,
        'intent' => $intent,
        'ui_schedule_kind' => $ui_kind,
        'venue_condition' => (string) ($data['venue_condition'] ?? ''),
        'gender_condition' => (string) ($data['gender_condition'] ?? ''),
        'can_show_match_detail' => false,
        'match_detail_url' => '',
        'schedule_visibility' => ($intent === 'recruit') ? 'team' : 'team',
    ];
}

/**
 * カレンダー・REST 返却用: format_schedule_for_ui + 詳細リンク + ui_kind
 *
 * @param int $schedule_id
 * @param int $user_id
 * @return array<string, mixed>
 */
function aidunite_schedule_get_rest_calendar_payload($schedule_id, $user_id = 0) {
    $schedule_id = (int) $schedule_id;
    $user_id = (int) ($user_id > 0 ? $user_id : get_current_user_id());
    $post = get_post($schedule_id);
    if (!$post || $post->post_type !== 'schedule') {
        return [];
    }

    if (!function_exists('aidunite_format_schedule_for_ui')) {
        require_once get_template_directory() . '/functions/schedule/schedule-functions.php';
    }

    $ui = aidunite_format_schedule_for_ui($post);
    if ($ui === null || $ui === []) {
        return [];
    }

    $detail = [];
    try {
        $detail = aidunite_schedule_resolve_match_detail_link($schedule_id, $user_id);
    } catch (Throwable $e) {
        if (class_exists('AidUniteErrorHandler')) {
            AidUniteErrorHandler::warning('match_detail_link_failed', ['schedule_id' => $schedule_id, 'error' => $e->getMessage()]);
        }
    }
    $bundle = function_exists('aidunite_schedule_get_display_bundle')
        ? aidunite_schedule_get_display_bundle($schedule_id)
        : [];

    $dep = function_exists('aidunite_get_schedule_dependencies')
        ? aidunite_get_schedule_dependencies($schedule_id)
        : ['has_pending' => false, 'has_in_play' => false];
    $can_edit_full = empty($dep['has_pending']) && empty($dep['has_in_play']);

    $participants_display = function_exists('aidunite_schedule_get_participants_display')
        ? aidunite_schedule_get_participants_display($schedule_id)
        : [];

    return array_merge($ui, $detail, $participants_display, [
        'schedule_id' => $schedule_id,
        'post_id' => $schedule_id,
        'ui_schedule_kind' => aidunite_schedule_infer_ui_kind(
            (string) ($ui['intent'] ?? ''),
            (string) ($ui['schedule_type'] ?? $ui['type'] ?? '')
        ),
        'schedule_visibility' => ((string) ($bundle['is_personal'] ?? '0') === '1') ? 'personal' : 'team',
        'attendance_required' => (string) ($bundle['attendance_required'] ?? '0'),
        'male_slots' => (int) ($bundle['male_slots'] ?? 0),
        'female_slots' => (int) ($bundle['female_slots'] ?? 0),
        'can_edit_full_fields' => $can_edit_full,
        'match_board_id' => function_exists('aidunite_schedule_find_match_board_id_for_schedule')
            ? (int) aidunite_schedule_find_match_board_id_for_schedule($schedule_id)
            : 0,
    ]);
}

/**
 * 登録済みカード「詳細」: match_request 連携から遷移 URL を解決
 *
 * @param int $schedule_id
 * @param int $user_id
 * @return array{can_show_match_detail:bool,match_detail_url:string,match_request_id:int}
 */
function aidunite_schedule_resolve_match_detail_link($schedule_id, $user_id = 0) {
    $schedule_id = (int) $schedule_id;
    $empty = [
        'can_show_match_detail' => false,
        'match_detail_url' => '',
        'match_request_id' => 0,
    ];
    if ($schedule_id < 1) {
        return $empty;
    }

    if (!function_exists('aidunite_get_schedule_match_requests')) {
        $guards = get_template_directory() . '/functions/schedule/schedule-dependency-guards.php';
        if (is_readable($guards)) {
            require_once $guards;
        }
    }
    if (!function_exists('aidunite_get_schedule_match_requests')) {
        return $empty;
    }

    $viewer_team = 0;
    if (function_exists('aidunite_get_current_team_id')) {
        $viewer_team = (int) aidunite_get_current_team_id((int) ($user_id > 0 ? $user_id : get_current_user_id()));
    }
    if ($viewer_team < 1 && $user_id > 0) {
        $viewer_team = (int) get_user_meta($user_id, 'team_id', true);
    }

    $schedule_team = function_exists('aidunite_schedule_read_team_id')
        ? (int) aidunite_schedule_read_team_id($schedule_id)
        : (int) get_post_meta($schedule_id, 'team_id', true);

    $requests = aidunite_get_schedule_match_requests($schedule_id);
    if ($requests === []) {
        return $empty;
    }

    $status_rank = [
        'established' => 0,
        'accepted' => 1,
        'pending' => 2,
    ];

    usort($requests, static function ($a, $b) use ($status_rank) {
        $sa = function_exists('aidunite_normalize_match_request_status')
            ? aidunite_normalize_match_request_status((string) get_post_meta($a->ID, 'status', true), $a->post_status)
            : strtolower((string) get_post_meta($a->ID, 'status', true));
        $sb = function_exists('aidunite_normalize_match_request_status')
            ? aidunite_normalize_match_request_status((string) get_post_meta($b->ID, 'status', true), $b->post_status)
            : strtolower((string) get_post_meta($b->ID, 'status', true));
        $ra = $status_rank[$sa] ?? 99;
        $rb = $status_rank[$sb] ?? 99;
        if ($ra !== $rb) {
            return $ra <=> $rb;
        }

        return (int) $b->ID <=> (int) $a->ID;
    });

    foreach ($requests as $req) {
        $req_id = (int) $req->ID;
        $status = function_exists('aidunite_normalize_match_request_status')
            ? aidunite_normalize_match_request_status((string) get_post_meta($req_id, 'status', true), $req->post_status)
            : strtolower(trim((string) get_post_meta($req_id, 'status', true)));

        if (!in_array($status, ['established', 'accepted', 'pending'], true)) {
            continue;
        }

        $url = '';
        if (function_exists('aidunite_match_request_get_link_schedule_teams')) {
            [$my_sid, $to_sid, $my_team, $to_team, $from_team] = aidunite_match_request_get_link_schedule_teams($req_id);
            $my_sid = (int) $my_sid;
            $to_sid = (int) $to_sid;

            if ($viewer_team > 0 && $to_sid === $schedule_id && $viewer_team === (int) $to_team) {
                $args = ['my_schedule_id' => $to_sid, 'match_request_id' => $req_id];
                if ($my_sid > 0) {
                    $args['schedule_id'] = $my_sid;
                }
                $url = add_query_arg($args, home_url('/match-detail/'));
            } elseif ($viewer_team > 0 && $my_sid === $schedule_id && $viewer_team === (int) $from_team) {
                $args = ['my_schedule_id' => $my_sid, 'match_request_id' => $req_id];
                if ($to_sid > 0 && $to_sid !== 9999) {
                    $args['schedule_id'] = $to_sid;
                }
                $url = add_query_arg($args, home_url('/match-detail/'));
            } elseif ($schedule_team > 0 && $viewer_team === $schedule_team && $to_sid === $schedule_id) {
                $url = add_query_arg([
                    'my_schedule_id' => $to_sid,
                    'match_request_id' => $req_id,
                ], home_url('/match-detail/'));
            }
        }

        if ($url === '') {
            $url = add_query_arg(['id' => $req_id], home_url('/match-detail/'));
        }

        if ($status === 'established' || $url !== '') {
            return [
                'can_show_match_detail' => true,
                'match_detail_url' => $url,
                'match_request_id' => $req_id,
            ];
        }
    }

    return $empty;
}

add_filter('rest_prepare_schedule', 'aidunite_schedule_rest_merge_canonical_meta_aliases', 10, 2);
