<?php
/**
 * 大会・イベント オーケストレーション（create / invite / respond / schedule / notify）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param int $user_id
 * @return bool
 */
function aidunite_competition_user_can_operate($user_id = 0) {
    $user_id = (int) ($user_id ?: get_current_user_id());
    if ($user_id < 1) {
        return false;
    }

    if (user_can($user_id, 'manage_options')) {
        return true;
    }

    return function_exists('aidunite_user_is_privileged_admin')
        && aidunite_user_is_privileged_admin($user_id);
}

/**
 * @param int $user_id
 * @param int $event_id
 * @return bool
 */
function aidunite_competition_user_can_view_event($user_id, $event_id) {
    $user_id = (int) $user_id;
    $event_id = (int) $event_id;
    if ($event_id < 1) {
        return false;
    }
    if (aidunite_competition_user_can_operate($user_id)) {
        return true;
    }

    $team_ids = function_exists('aidunite_get_managed_team_ids')
        ? array_map('intval', (array) aidunite_get_managed_team_ids($user_id))
        : [];
    if (empty($team_ids)) {
        return false;
    }

    foreach ($team_ids as $team_id) {
        if (aidunite_competition_persist_find_entry_id($event_id, $team_id) > 0) {
            return true;
        }
    }

    return false;
}

/**
 * @param int $user_id
 * @param int $entry_id
 * @return bool
 */
function aidunite_competition_user_can_manage_entry($user_id, $entry_id) {
    $user_id = (int) $user_id;
    $entry_id = (int) $entry_id;
    if ($entry_id < 1) {
        return false;
    }
    if (aidunite_competition_user_can_operate($user_id)) {
        return true;
    }

    $raw = aidunite_competition_read_entry_meta_raw($entry_id);
    if (empty($raw)) {
        return false;
    }

    $team_id = (int) ($raw['team_id'] ?? 0);

    return $team_id > 0
        && function_exists('aidunite_user_has_managed_team_access')
        && aidunite_user_has_managed_team_access($user_id, $team_id);
}

/**
 * @param array<string, mixed> $input
 * @param int                  $operator_id
 * @return array{ok: bool, event_id?: int, payload?: array<string, mixed>, error?: string, code?: string}
 */
function aidunite_competition_submit_create_event(array $input, $operator_id = 0) {
    $operator_id = (int) ($operator_id ?: get_current_user_id());
    if (!aidunite_competition_user_can_operate($operator_id)) {
        return ['ok' => false, 'code' => 'forbidden', 'error' => '権限がありません'];
    }

    $data = aidunite_competition_normalize_event_input($input);
    if ($data['title'] === '') {
        return ['ok' => false, 'code' => 'invalid_params', 'error' => 'イベント名が必要です'];
    }
    if ($data['status'] === 'draft' && empty($input['status'])) {
        $data['status'] = 'draft';
    }

    $saved = aidunite_competition_persist_save_event(0, $data);
    if (is_wp_error($saved)) {
        return ['ok' => false, 'code' => 'save_failed', 'error' => $saved->get_error_message()];
    }

    return [
        'ok' => true,
        'event_id' => (int) $saved,
        'payload' => aidunite_competition_read_event_payload((int) $saved, ['viewer_user_id' => $operator_id]),
    ];
}

/**
 * @param int                  $event_id
 * @param array<string, mixed> $input
 * @param int                  $operator_id
 * @return array{ok: bool, payload?: array<string, mixed>, error?: string, code?: string}
 */
function aidunite_competition_submit_update_event($event_id, array $input, $operator_id = 0) {
    $event_id = (int) $event_id;
    $operator_id = (int) ($operator_id ?: get_current_user_id());
    if (!aidunite_competition_user_can_operate($operator_id)) {
        return ['ok' => false, 'code' => 'forbidden', 'error' => '権限がありません'];
    }
    if ($event_id < 1 || get_post_type($event_id) !== 'competition_event') {
        return ['ok' => false, 'code' => 'not_found', 'error' => 'イベントが見つかりません'];
    }

    $current = aidunite_competition_read_event_meta_raw($event_id);
    $merged = array_merge($current, $input);
    $data = aidunite_competition_normalize_event_input($merged);
    if ($data['title'] === '') {
        $data['title'] = (string) $current['title'];
    }

    $saved = aidunite_competition_persist_save_event($event_id, $data);
    if (is_wp_error($saved)) {
        return ['ok' => false, 'code' => 'save_failed', 'error' => $saved->get_error_message()];
    }

    return [
        'ok' => true,
        'payload' => aidunite_competition_read_event_payload($event_id, ['viewer_user_id' => $operator_id]),
    ];
}

/**
 * @param int $event_id
 * @param int $operator_id
 * @return array{ok: bool, payload?: array<string, mixed>, error?: string, code?: string}
 */
function aidunite_competition_submit_publish_event($event_id, $operator_id = 0) {
    $event_id = (int) $event_id;
    $operator_id = (int) ($operator_id ?: get_current_user_id());
    if (!aidunite_competition_user_can_operate($operator_id)) {
        return ['ok' => false, 'code' => 'forbidden', 'error' => '権限がありません'];
    }

    $raw = aidunite_competition_read_event_meta_raw($event_id);
    if (empty($raw)) {
        return ['ok' => false, 'code' => 'not_found', 'error' => 'イベントが見つかりません'];
    }

    $status = aidunite_competition_normalize_event_status($raw['status'] ?? 'draft');
    if ($status !== 'draft') {
        return ['ok' => false, 'code' => 'invalid_transition', 'error' => '下書き状態のみ公開できます'];
    }

    $data = aidunite_competition_normalize_event_input(array_merge($raw, ['status' => 'inviting']));
    $saved = aidunite_competition_persist_save_event($event_id, $data);
    if (is_wp_error($saved)) {
        return ['ok' => false, 'code' => 'save_failed', 'error' => $saved->get_error_message()];
    }

    return [
        'ok' => true,
        'payload' => aidunite_competition_read_event_payload($event_id, ['viewer_user_id' => $operator_id]),
    ];
}

/**
 * @param int   $event_id
 * @param int[] $team_ids
 * @param int   $operator_id
 * @return array{ok: bool, entries?: array<int, array<string, mixed>>, error?: string, code?: string}
 */
function aidunite_competition_submit_invite_teams($event_id, array $team_ids, $operator_id = 0) {
    $event_id = (int) $event_id;
    $operator_id = (int) ($operator_id ?: get_current_user_id());
    if (!aidunite_competition_user_can_operate($operator_id)) {
        return ['ok' => false, 'code' => 'forbidden', 'error' => '権限がありません'];
    }

    $raw = aidunite_competition_read_event_meta_raw($event_id);
    if (empty($raw)) {
        return ['ok' => false, 'code' => 'not_found', 'error' => 'イベントが見つかりません'];
    }

    if (aidunite_competition_is_recruitment_closed($event_id)) {
        return ['ok' => false, 'code' => 'recruitment_closed', 'error' => '募集は締め切られています'];
    }

    $created = [];
    foreach (array_unique(array_map('intval', $team_ids)) as $team_id) {
        if ($team_id < 1 || get_post_type($team_id) !== 'team') {
            continue;
        }

        $existing = aidunite_competition_persist_find_entry_id($event_id, $team_id);
        if ($existing > 0) {
            continue;
        }

        $entry_data = aidunite_competition_normalize_entry_input([
            'event_id' => $event_id,
            'team_id' => $team_id,
            'status' => 'invited',
            'payment_status' => 'unpaid',
        ]);

        $saved = aidunite_competition_persist_save_entry(0, $entry_data);
        if (is_wp_error($saved)) {
            continue;
        }

        aidunite_competition_notify_invited((int) $saved);
        $created[] = aidunite_competition_read_entry_payload((int) $saved, ['viewer_user_id' => $operator_id]);
    }

    return ['ok' => true, 'entries' => $created];
}

/**
 * @param int    $entry_id
 * @param string $response confirm|decline
 * @param int    $user_id
 * @return array{ok: bool, payload?: array<string, mixed>, error?: string, code?: string}
 */
function aidunite_competition_submit_respond_entry($entry_id, $response, $user_id = 0) {
    $entry_id = (int) $entry_id;
    $user_id = (int) ($user_id ?: get_current_user_id());
    $response = sanitize_key((string) $response);

    if (!in_array($response, ['confirm', 'decline'], true)) {
        return ['ok' => false, 'code' => 'invalid_params', 'error' => 'response は confirm または decline が必要です'];
    }
    if (!aidunite_competition_user_can_manage_entry($user_id, $entry_id)) {
        return ['ok' => false, 'code' => 'forbidden', 'error' => '権限がありません'];
    }

    $raw = aidunite_competition_read_entry_meta_raw($entry_id);
    if (empty($raw)) {
        return ['ok' => false, 'code' => 'not_found', 'error' => '参加エントリが見つかりません'];
    }

    $status = aidunite_competition_normalize_entry_status($raw['status'] ?? '');
    if ($status !== 'invited') {
        return ['ok' => false, 'code' => 'invalid_transition', 'error' => '招待中のエントリのみ回答できます'];
    }

    $event_id = (int) ($raw['event_id'] ?? 0);
    if (aidunite_competition_is_recruitment_closed($event_id)) {
        return ['ok' => false, 'code' => 'recruitment_closed', 'error' => '募集は締め切られています'];
    }

    $now = current_time('mysql');
    if ($response === 'decline') {
        $data = aidunite_competition_normalize_entry_input(array_merge($raw, [
            'status' => 'declined',
            'responded_at' => $now,
        ]));
        aidunite_competition_persist_write_entry_meta($entry_id, array_merge($data, ['responded_at' => $now]));

        return [
            'ok' => true,
            'payload' => aidunite_competition_read_entry_payload($entry_id, ['viewer_user_id' => $user_id]),
        ];
    }

    $event_raw = aidunite_competition_read_event_meta_raw($event_id);
    $capacity = (int) ($event_raw['capacity_teams'] ?? 0);
    $confirmed_count = aidunite_competition_count_entries_by_status($event_id, 'confirmed');
    if ($capacity > 0 && $confirmed_count >= $capacity) {
        return ['ok' => false, 'code' => 'recruitment_closed', 'error' => '定員に達しています'];
    }

    $approval_mode = aidunite_competition_normalize_approval_mode($event_raw['approval_mode'] ?? 'auto');
    $next_status = $approval_mode === 'manual' ? 'applied' : 'confirmed';

    $data = aidunite_competition_normalize_entry_input(array_merge($raw, [
        'status' => $next_status,
        'responded_at' => $now,
    ]));
    aidunite_competition_persist_write_entry_meta($entry_id, array_merge($data, ['responded_at' => $now]));

    if ($next_status === 'confirmed') {
        aidunite_competition_persist_write_entry_meta($entry_id, ['confirmed_at' => $now]);
        aidunite_competition_persist_ensure_entry_schedule($entry_id);
        aidunite_competition_notify_entry_confirmed($entry_id);
    }

    return [
        'ok' => true,
        'payload' => aidunite_competition_read_entry_payload($entry_id, ['viewer_user_id' => $user_id]),
    ];
}

/**
 * @param int    $entry_id
 * @param string $payment_status
 * @param int    $operator_id
 * @return array{ok: bool, payload?: array<string, mixed>, error?: string, code?: string}
 */
function aidunite_competition_submit_update_payment($entry_id, $payment_status, $operator_id = 0) {
    $entry_id = (int) $entry_id;
    $operator_id = (int) ($operator_id ?: get_current_user_id());
    if (!aidunite_competition_user_can_operate($operator_id)) {
        return ['ok' => false, 'code' => 'forbidden', 'error' => '権限がありません'];
    }

    $raw = aidunite_competition_read_entry_meta_raw($entry_id);
    if (empty($raw)) {
        return ['ok' => false, 'code' => 'not_found', 'error' => '参加エントリが見つかりません'];
    }

    $data = aidunite_competition_normalize_entry_input(array_merge($raw, [
        'payment_status' => $payment_status,
    ]));
    aidunite_competition_persist_write_entry_meta($entry_id, $data);

    return [
        'ok' => true,
        'payload' => aidunite_competition_read_entry_payload($entry_id, ['viewer_user_id' => $operator_id]),
    ];
}

/**
 * @param int $entry_id
 * @param int $operator_id
 * @return array{ok: bool, payload?: array<string, mixed>, error?: string, code?: string}
 */
function aidunite_competition_submit_approve_entry($entry_id, $operator_id = 0) {
    $entry_id = (int) $entry_id;
    $operator_id = (int) ($operator_id ?: get_current_user_id());
    if (!aidunite_competition_user_can_operate($operator_id)) {
        return ['ok' => false, 'code' => 'forbidden', 'error' => '権限がありません'];
    }

    $raw = aidunite_competition_read_entry_meta_raw($entry_id);
    if (empty($raw)) {
        return ['ok' => false, 'code' => 'not_found', 'error' => '参加エントリが見つかりません'];
    }

    $status = aidunite_competition_normalize_entry_status($raw['status'] ?? '');
    if ($status !== 'applied') {
        return ['ok' => false, 'code' => 'invalid_transition', 'error' => '承認待ちのエントリのみ承認できます'];
    }

    $event_id = (int) ($raw['event_id'] ?? 0);
    $event_raw = aidunite_competition_read_event_meta_raw($event_id);
    $capacity = (int) ($event_raw['capacity_teams'] ?? 0);
    $confirmed_count = aidunite_competition_count_entries_by_status($event_id, 'confirmed');
    if ($capacity > 0 && $confirmed_count >= $capacity) {
        return ['ok' => false, 'code' => 'recruitment_closed', 'error' => '定員に達しています'];
    }

    $now = current_time('mysql');
    $data = aidunite_competition_normalize_entry_input(array_merge($raw, [
        'status' => 'confirmed',
    ]));
    aidunite_competition_persist_write_entry_meta($entry_id, array_merge($data, [
        'confirmed_at' => $now,
    ]));

    aidunite_competition_persist_ensure_entry_schedule($entry_id);
    aidunite_competition_notify_entry_confirmed($entry_id);

    return [
        'ok' => true,
        'payload' => aidunite_competition_read_entry_payload($entry_id, ['viewer_user_id' => $operator_id]),
    ];
}

/**
 * @param int                  $event_id
 * @param array<string, mixed> $input
 * @param int                  $operator_id
 * @return array{ok: bool, payload?: array<string, mixed>, error?: string, code?: string}
 */
function aidunite_competition_submit_create_block($event_id, array $input, $operator_id = 0) {
    $event_id = (int) $event_id;
    $operator_id = (int) ($operator_id ?: get_current_user_id());
    if (!aidunite_competition_user_can_operate($operator_id)) {
        return ['ok' => false, 'code' => 'forbidden', 'error' => '権限がありません'];
    }
    if (get_post_type($event_id) !== 'competition_event') {
        return ['ok' => false, 'code' => 'not_found', 'error' => 'イベントが見つかりません'];
    }

    $data = aidunite_competition_normalize_block_input(array_merge($input, ['event_id' => $event_id]));
    if ($data['block_date'] === '') {
        return ['ok' => false, 'code' => 'invalid_params', 'error' => 'block_date が必要です'];
    }

    $saved = aidunite_competition_persist_save_block(0, $data);
    if (is_wp_error($saved)) {
        return ['ok' => false, 'code' => 'save_failed', 'error' => $saved->get_error_message()];
    }

    return [
        'ok' => true,
        'payload' => aidunite_competition_read_block_payload((int) $saved),
    ];
}

/**
 * entry 確定時に schedule 1 本を生成（Phase 1: event_wide 暫定 09:00–17:00）
 *
 * @param int $entry_id
 * @return int schedule_id（失敗時 0）
 */
function aidunite_competition_persist_ensure_entry_schedule($entry_id) {
    $entry_id = (int) $entry_id;
    $raw = aidunite_competition_read_entry_meta_raw($entry_id);
    if (empty($raw)) {
        return 0;
    }

    $existing_schedule = (int) ($raw['calendar_schedule_id'] ?? 0);
    if ($existing_schedule > 0 && get_post_type($existing_schedule) === 'schedule') {
        return $existing_schedule;
    }

    $event_id = (int) ($raw['event_id'] ?? 0);
    $team_id = (int) ($raw['team_id'] ?? 0);
    $event_raw = aidunite_competition_read_event_meta_raw($event_id);
    if (empty($event_raw) || $team_id < 1) {
        return 0;
    }

    if (!function_exists('aidunite_schedule_normalize_form_input')
        || !function_exists('aidunite_schedule_create_published_post')) {
        return 0;
    }

    $event_kind = aidunite_competition_normalize_event_kind($event_raw['event_kind'] ?? 'tournament');
    $schedule_type = in_array($event_kind, ['clinic', 'practice', 'session'], true) ? 'イベント' : '大会';
    $date_start = (string) ($event_raw['date_start'] ?? '');
    if ($date_start === '') {
        return 0;
    }

    $blocks = aidunite_competition_read_blocks_for_event($event_id);
    $start_time = '09:00';
    $end_time = '17:00';
    $schedule_date = $date_start;
    if (!empty($blocks)) {
        $first = $blocks[0];
        $schedule_date = (string) ($first['date'] ?? $date_start);
        $start_time = (string) ($first['start_time'] ?? $start_time);
        $end_time = (string) ($first['end_time'] ?? $end_time);
    }

    $leader_id = function_exists('aidunite_team_resolve_leader_user_id')
        ? (int) aidunite_team_resolve_leader_user_id($team_id)
        : 0;
    if ($leader_id < 1) {
        $leader_id = (int) get_current_user_id();
    }

    $schedule_input = aidunite_schedule_normalize_form_input([
        'intent' => 'confirmed',
        'schedule_type' => $schedule_type,
        'date' => $schedule_date,
        'end_date' => (string) ($event_raw['date_end'] ?: $date_start),
        'start_time' => $start_time,
        'end_time' => $end_time,
        'venue_name' => (string) ($event_raw['venue_name'] ?? ''),
        'team_id' => $team_id,
        'user_id' => $leader_id,
        'attendance_required' => '1',
        'schedule_origin' => 'competition_entry',
        'competition_event_id' => $event_id,
        'competition_entry_id' => $entry_id,
        'schedule_quick_memo' => '大会・イベント参加（' . (string) $event_raw['title'] . '）',
    ]);

    $schedule_id = aidunite_schedule_create_published_post($schedule_input);
    if (is_wp_error($schedule_id) || !$schedule_id) {
        return 0;
    }

    $schedule_id = (int) $schedule_id;
    aidunite_competition_persist_write_entry_meta($entry_id, [
        'calendar_schedule_id' => $schedule_id,
    ]);

    return $schedule_id;
}

/**
 * @param int $entry_id
 */
function aidunite_competition_notify_invited($entry_id) {
    $entry_id = (int) $entry_id;
    if ($entry_id < 1 || !function_exists('aidunite_notification_send')) {
        return;
    }

    $raw = aidunite_competition_read_entry_meta_raw($entry_id);
    if (empty($raw)) {
        return;
    }

    $team_id = (int) ($raw['team_id'] ?? 0);
    $event_id = (int) ($raw['event_id'] ?? 0);
    $leader_id = function_exists('aidunite_team_resolve_leader_user_id')
        ? (int) aidunite_team_resolve_leader_user_id($team_id)
        : 0;
    if ($leader_id < 1) {
        return;
    }

    $event_title = get_the_title($event_id);
    $link_url = home_url('/mypage/?competition_entry=' . $entry_id);

    aidunite_notification_send($leader_id, 'competition_invited', [
        'title' => '大会・イベントへの招待',
        'message' => '「' . $event_title . '」への参加招待が届きました。マイページのやることから回答してください。',
        'related_id' => $entry_id,
        'link_url' => $link_url,
        'event_id' => $event_id,
        'entry_id' => $entry_id,
        'team_id' => $team_id,
    ]);
}

/**
 * @param int $entry_id
 */
function aidunite_competition_notify_entry_confirmed($entry_id) {
    $entry_id = (int) $entry_id;
    if ($entry_id < 1 || !function_exists('aidunite_notification_send')) {
        return;
    }

    $raw = aidunite_competition_read_entry_meta_raw($entry_id);
    if (empty($raw)) {
        return;
    }

    $team_id = (int) ($raw['team_id'] ?? 0);
    $event_id = (int) ($raw['event_id'] ?? 0);
    $leader_id = function_exists('aidunite_team_resolve_leader_user_id')
        ? (int) aidunite_team_resolve_leader_user_id($team_id)
        : 0;
    if ($leader_id < 1) {
        return;
    }

    $event_title = get_the_title($event_id);
    $schedule_id = (int) ($raw['calendar_schedule_id'] ?? 0);
    $link_url = $schedule_id > 0 && function_exists('aidunite_attendance_report_url')
        ? aidunite_attendance_report_url($schedule_id)
        : home_url('/mypage/?competition_entry=' . $entry_id);

    aidunite_notification_send($leader_id, 'competition_entry_confirmed', [
        'title' => '大会・イベント参加が確定しました',
        'message' => '「' . $event_title . '」への参加が確定しました。スケジュールと出欠をご確認ください。',
        'related_id' => $entry_id,
        'link_url' => $link_url,
        'event_id' => $event_id,
        'entry_id' => $entry_id,
        'schedule_id' => $schedule_id,
        'team_id' => $team_id,
    ]);
}

/**
 * @param int $event_id
 */
function aidunite_competition_notify_fixtures_published($event_id) {
    $event_id = (int) $event_id;
    if ($event_id < 1 || !function_exists('aidunite_notification_send')) {
        return;
    }

    $event_title = get_the_title($event_id);
    $link_url = home_url('/mypage/?competition_event=' . $event_id);

    foreach (aidunite_competition_read_entries_for_event($event_id) as $entry) {
        if (($entry['status'] ?? '') !== 'confirmed') {
            continue;
        }
        $team_id = (int) ($entry['team']['id'] ?? $entry['team']['team_id'] ?? 0);
        $leader_id = function_exists('aidunite_team_resolve_leader_user_id')
            ? (int) aidunite_team_resolve_leader_user_id($team_id)
            : 0;
        if ($leader_id < 1) {
            continue;
        }

        aidunite_notification_send($leader_id, 'competition_fixture_published', [
            'title' => '試合表が公開されました',
            'message' => '「' . $event_title . '」の試合表が確定しました。マイページからご確認ください。',
            'related_id' => $event_id,
            'link_url' => $link_url,
            'event_id' => $event_id,
            'team_id' => $team_id,
        ]);
    }
}

/**
 * @param int                  $event_id
 * @param array<string, mixed> $input
 * @param int                  $operator_id
 * @return array{ok: bool, fixtures?: array<int, array<string, mixed>>, warnings?: string[], preview?: bool, error?: string, code?: string}
 */
function aidunite_competition_submit_generate_fixtures($event_id, array $input, $operator_id = 0) {
    $event_id = (int) $event_id;
    $operator_id = (int) ($operator_id ?: get_current_user_id());
    if (!aidunite_competition_user_can_operate($operator_id)) {
        return ['ok' => false, 'code' => 'forbidden', 'error' => '権限がありません'];
    }

    $options = [
        'generator_mode' => (string) ($input['generator_mode'] ?? 'skeleton'),
        'preview' => !empty($input['preview']),
        'replace_existing' => !isset($input['replace_existing']) || !empty($input['replace_existing']),
    ];

    $result = aidunite_competition_persist_generate_fixtures($event_id, $options);
    if (empty($result['ok'])) {
        return [
            'ok' => false,
            'code' => (string) ($result['code'] ?? 'save_failed'),
            'error' => (string) ($result['error'] ?? '生成に失敗しました'),
            'warnings' => is_array($result['warnings'] ?? null) ? $result['warnings'] : [],
        ];
    }

    if (empty($options['preview']) && !empty($input['notify'])) {
        aidunite_competition_notify_fixtures_published($event_id);
    }

    return [
        'ok' => true,
        'fixtures' => $result['fixtures'] ?? [],
        'warnings' => $result['warnings'] ?? [],
        'preview' => !empty($result['preview']),
    ];
}

/**
 * @param int                  $fixture_id
 * @param array<string, mixed> $input
 * @param int                  $operator_id
 * @return array{ok: bool, payload?: array<string, mixed>, error?: string, code?: string}
 */
function aidunite_competition_submit_update_fixture($fixture_id, array $input, $operator_id = 0) {
    $fixture_id = (int) $fixture_id;
    $operator_id = (int) ($operator_id ?: get_current_user_id());
    if (!aidunite_competition_user_can_operate($operator_id)) {
        return ['ok' => false, 'code' => 'forbidden', 'error' => '権限がありません'];
    }

    $raw = aidunite_competition_read_fixture_meta_raw($fixture_id);
    if (empty($raw)) {
        return ['ok' => false, 'code' => 'not_found', 'error' => '試合が見つかりません'];
    }

    $before_at = (string) ($raw['scheduled_at'] ?? '');
    $before_court = (string) ($raw['court_label'] ?? '');

    $merged = array_merge($raw, $input);
    $data = aidunite_competition_normalize_fixture_input($merged);
    aidunite_competition_persist_write_fixture_meta($fixture_id, $data);

    $after_at = (string) ($data['scheduled_at'] ?? '');
    $after_court = (string) ($data['court_label'] ?? '');
    if (($before_at !== '' || $before_court !== '') && ($before_at !== $after_at || $before_court !== $after_court)) {
        aidunite_competition_notify_fixture_changed($fixture_id);
    }

    return [
        'ok' => true,
        'payload' => aidunite_competition_read_fixture_payload($fixture_id),
    ];
}

/**
 * @param int                  $fixture_id
 * @param array<string, mixed> $input
 * @param int                  $operator_id
 * @return array{ok: bool, payload?: array<string, mixed>, error?: string, code?: string}
 */
function aidunite_competition_submit_fixture_result($fixture_id, array $input, $operator_id = 0) {
    $fixture_id = (int) $fixture_id;
    $operator_id = (int) ($operator_id ?: get_current_user_id());
    if (!aidunite_competition_user_can_operate($operator_id)) {
        return ['ok' => false, 'code' => 'forbidden', 'error' => '権限がありません'];
    }

    $raw = aidunite_competition_read_fixture_meta_raw($fixture_id);
    if (empty($raw)) {
        return ['ok' => false, 'code' => 'not_found', 'error' => '試合が見つかりません'];
    }

    $score_a = isset($input['score_a']) ? (int) $input['score_a'] : (int) ($raw['score_a'] ?? 0);
    $score_b = isset($input['score_b']) ? (int) $input['score_b'] : (int) ($raw['score_b'] ?? 0);
    if ($score_a === $score_b) {
        return ['ok' => false, 'code' => 'invalid_params', 'error' => '同点は入力できません'];
    }

    $winner_team_id = $score_a > $score_b
        ? (int) ($raw['team_a_id'] ?? 0)
        : (int) ($raw['team_b_id'] ?? 0);

    $data = aidunite_competition_normalize_fixture_input(array_merge($raw, [
        'score_a' => $score_a,
        'score_b' => $score_b,
        'status' => 'finished',
        'result_source' => 'admin',
    ]));
    aidunite_competition_persist_write_fixture_meta($fixture_id, $data);

    $advances_to = (int) ($raw['winner_advances_to'] ?? 0);
    $winner_slot = (string) ($raw['winner_slot'] ?? '');
    if ($advances_to > 0 && $winner_team_id > 0 && in_array($winner_slot, ['a', 'b'], true)) {
        $next_raw = aidunite_competition_read_fixture_meta_raw($advances_to);
        if (!empty($next_raw)) {
            $patch = $next_raw;
            if ($winner_slot === 'a') {
                $patch['team_a_id'] = $winner_team_id;
            } else {
                $patch['team_b_id'] = $winner_team_id;
            }
            aidunite_competition_persist_write_fixture_meta($advances_to, aidunite_competition_normalize_fixture_input($patch));
        }
    }

    return [
        'ok' => true,
        'payload' => aidunite_competition_read_fixture_payload($fixture_id),
    ];
}

/**
 * @param int $fixture_id
 */
function aidunite_competition_notify_fixture_changed($fixture_id) {
    $fixture_id = (int) $fixture_id;
    if ($fixture_id < 1 || !function_exists('aidunite_notification_send')) {
        return;
    }

    $raw = aidunite_competition_read_fixture_meta_raw($fixture_id);
    if (empty($raw)) {
        return;
    }

    $event_id = (int) ($raw['event_id'] ?? 0);
    $event_title = get_the_title($event_id);
    $team_ids = array_filter([(int) ($raw['team_a_id'] ?? 0), (int) ($raw['team_b_id'] ?? 0)]);

    foreach ($team_ids as $team_id) {
        $leader_id = function_exists('aidunite_team_resolve_leader_user_id')
            ? (int) aidunite_team_resolve_leader_user_id($team_id)
            : 0;
        if ($leader_id < 1) {
            continue;
        }

        aidunite_notification_send($leader_id, 'competition_fixture_changed', [
            'title' => '試合日程・コートが変更されました',
            'message' => '「' . $event_title . '」の試合日程またはコートが更新されました。マイページからご確認ください。',
            'related_id' => $fixture_id,
            'link_url' => home_url('/mypage/?competition_event=' . $event_id),
            'event_id' => $event_id,
            'fixture_id' => $fixture_id,
            'team_id' => $team_id,
        ]);
    }
}
