<?php
/**
 * 大会 fixture generator（skeleton / slot_assign）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param int $team_id
 * @return string
 */
function aidunite_competition_generator_team_region_key($team_id) {
    $team_id = (int) $team_id;
    if ($team_id < 1) {
        return '';
    }

    if (function_exists('aidunite_team_get_display_bundle')) {
        $bundle = aidunite_team_get_display_bundle($team_id);
        $pref = trim((string) ($bundle['activity_prefecture'] ?? ''));
        if ($pref !== '') {
            return $pref;
        }
        $region = trim((string) ($bundle['region'] ?? ''));
        if ($region !== '') {
            return $region;
        }
    }

    if (function_exists('aidunite_get_team_activity_profile')) {
        $profile = aidunite_get_team_activity_profile($team_id);
        $pref = trim((string) ($profile['activity_prefecture'] ?? ''));
        if ($pref !== '') {
            return $pref;
        }
        $region = trim((string) ($profile['region'] ?? ''));
        if ($region !== '') {
            return $region;
        }
    }

    return '';
}

/**
 * @param int[]  $team_ids
 * @param string $mode off|best_effort|strict
 * @return array{pairs: array<int, array{0:int,1:int}>, warnings: string[]}
 */
function aidunite_competition_generator_pair_round_one(array $team_ids, $mode = 'best_effort') {
    $team_ids = array_values(array_unique(array_map('intval', $team_ids)));
    $warnings = [];

    if (count($team_ids) < 2) {
        return ['pairs' => [], 'warnings' => ['参加確定チームが不足しています']];
    }

    if ($mode === 'off') {
        shuffle($team_ids);
        $pairs = [];
        for ($i = 0; $i < count($team_ids); $i += 2) {
            if (!isset($team_ids[$i + 1])) {
                break;
            }
            $pairs[] = [$team_ids[$i], $team_ids[$i + 1]];
        }

        return ['pairs' => $pairs, 'warnings' => $warnings];
    }

    $remaining = $team_ids;
    shuffle($remaining);
    $pairs = [];

    while (count($remaining) >= 2) {
        $first = array_shift($remaining);
        $first_region = aidunite_competition_generator_team_region_key($first);
        $partner_index = null;

        foreach ($remaining as $idx => $candidate) {
            $candidate_region = aidunite_competition_generator_team_region_key($candidate);
            if ($first_region === '' || $candidate_region === '' || $first_region !== $candidate_region) {
                $partner_index = $idx;
                break;
            }
        }

        if ($partner_index === null) {
            $warnings[] = '同地区回避: 1回戦の組み合わせで地区分散できないペアがあります';
            if ($mode === 'strict') {
                return ['pairs' => [], 'warnings' => $warnings];
            }
            $partner_index = 0;
        }

        $second = $remaining[$partner_index];
        unset($remaining[$partner_index]);
        $remaining = array_values($remaining);
        $pairs[] = [$first, $second];
    }

    return ['pairs' => $pairs, 'warnings' => $warnings];
}

/**
 * @param int $team_count
 * @return array<int, array{round:int, matches:int, label:string}>
 */
function aidunite_competition_generator_knockout_round_plan($team_count) {
    $team_count = (int) $team_count;
    if ($team_count < 2 || ($team_count & ($team_count - 1)) !== 0) {
        return [];
    }

    $rounds = [];
    $round = 1;
    $matches = (int) ($team_count / 2);
    $labels = [
        2 => '決勝',
        4 => '準決勝',
        8 => '準々決勝',
    ];

    while ($matches >= 1) {
        $rounds[] = [
            'round' => $round,
            'matches' => $matches,
            'label' => $labels[$matches] ?? ('第' . $round . '回戦'),
        ];
        $matches = (int) ($matches / 2);
        $round++;
    }

    return $rounds;
}

/**
 * @param int                  $event_id
 * @param int[]                $team_ids
 * @param array<string, mixed> $config
 * @param array<string, mixed> $template
 * @return array{draft: array<int, array<string, mixed>>, warnings: string[], code?: string, error?: string}
 */
function aidunite_competition_generator_build_knockout_draft($event_id, array $team_ids, array $config, array $template) {
    $event_id = (int) $event_id;
    $warnings = [];
    $expected_teams = (int) ($template['teams'] ?? 0);
    $team_ids = array_values(array_unique(array_map('intval', $team_ids)));

    if ($expected_teams > 0 && count($team_ids) !== $expected_teams) {
        $warnings[] = '参加確定 ' . count($team_ids) . ' チーム（テンプレ想定 ' . $expected_teams . '）';
    }

    $round_plan = aidunite_competition_generator_knockout_round_plan(count($team_ids));
    if ($round_plan === []) {
        return [
            'draft' => [],
            'warnings' => $warnings,
            'code' => 'generator_constraint_failed',
            'error' => '2の累乗チーム数が必要です（現在 ' . count($team_ids) . '）',
        ];
    }

    $constraints = is_array($config['constraints'] ?? null) ? $config['constraints'] : [];
    $region_mode = (string) ($constraints['avoid_same_region_round_1'] ?? 'best_effort');
    $pairing = aidunite_competition_generator_pair_round_one($team_ids, $region_mode);
    if ($pairing['pairs'] === [] && !empty($pairing['warnings'])) {
        return [
            'draft' => [],
            'warnings' => array_merge($warnings, $pairing['warnings']),
            'code' => 'generator_constraint_failed',
            'error' => '組み合わせを生成できませんでした',
        ];
    }
    $warnings = array_merge($warnings, $pairing['warnings']);

    $draft_fixtures = [];
    $match_index = 1;
    foreach ($pairing['pairs'] as $pair) {
        $draft_fixtures[] = [
            'event_id' => $event_id,
            'round' => 1,
            'match_index' => $match_index,
            'team_a_id' => (int) $pair[0],
            'team_b_id' => (int) $pair[1],
            'status' => 'scheduled',
            'result_source' => 'admin',
            'title' => get_the_title($event_id) . ' ' . ($round_plan[0]['label'] ?? '1回戦') . ' #' . $match_index,
        ];
        $match_index++;
    }

    foreach (array_slice($round_plan, 1) as $round_info) {
        $round = (int) $round_info['round'];
        for ($i = 1; $i <= (int) $round_info['matches']; $i++) {
            $draft_fixtures[] = [
                'event_id' => $event_id,
                'round' => $round,
                'match_index' => $i,
                'team_a_id' => 0,
                'team_b_id' => 0,
                'status' => 'scheduled',
                'result_source' => 'admin',
                'title' => get_the_title($event_id) . ' ' . (string) $round_info['label'] . ' #' . $i,
            ];
        }
    }

    $by_round = [];
    foreach ($draft_fixtures as $idx => $fixture) {
        $by_round[(int) $fixture['round']][] = $idx;
    }
    ksort($by_round);
    $round_keys = array_keys($by_round);
    for ($r = 0; $r < count($round_keys) - 1; $r++) {
        $current_indexes = $by_round[$round_keys[$r]];
        $next_indexes = $by_round[$round_keys[$r + 1]];
        foreach ($current_indexes as $pos => $draft_idx) {
            $target_pos = (int) floor($pos / 2);
            if (!isset($next_indexes[$target_pos])) {
                continue;
            }
            $draft_fixtures[$draft_idx]['winner_advances_to_key'] = $next_indexes[$target_pos];
            $draft_fixtures[$draft_idx]['winner_slot'] = ($pos % 2 === 0) ? 'a' : 'b';
        }
    }

    return ['draft' => $draft_fixtures, 'warnings' => $warnings];
}

/**
 * @param array<int, array<string, mixed>> $draft_fixtures
 * @return array<int, array<string, mixed>>
 */
function aidunite_competition_generator_persist_draft_fixtures(array $draft_fixtures) {
    $saved_id_map = [];
    $saved_payloads = [];

    foreach ($draft_fixtures as $idx => $fixture) {
        $data = aidunite_competition_normalize_fixture_input($fixture);
        $saved = aidunite_competition_persist_save_fixture(0, $data);
        if (is_wp_error($saved)) {
            return ['error' => $saved->get_error_message()];
        }
        $saved_id_map[$idx] = (int) $saved;
    }

    foreach ($draft_fixtures as $idx => $fixture) {
        if (!isset($fixture['winner_advances_to_key'], $saved_id_map[$idx])) {
            continue;
        }
        $target_idx = (int) $fixture['winner_advances_to_key'];
        if (!isset($saved_id_map[$target_idx])) {
            continue;
        }
        aidunite_competition_persist_write_fixture_meta($saved_id_map[$idx], [
            'winner_advances_to' => $saved_id_map[$target_idx],
            'winner_slot' => (string) ($fixture['winner_slot'] ?? ''),
        ]);
    }

    foreach ($saved_id_map as $fixture_id) {
        $payload = aidunite_competition_read_fixture_payload($fixture_id);
        if (!empty($payload)) {
            $saved_payloads[] = $payload;
        }
    }

    return ['fixtures' => $saved_payloads];
}

/**
 * @param int                  $event_id
 * @param array<string, mixed> $options
 * @return array{ok: bool, fixtures?: array<int, array<string, mixed>>, warnings?: string[], code?: string, error?: string, preview?: bool}
 */
function aidunite_competition_persist_generate_fixtures($event_id, array $options = []) {
    $event_id = (int) $event_id;
    if ($event_id < 1 || get_post_type($event_id) !== 'competition_event') {
        return ['ok' => false, 'code' => 'not_found', 'error' => 'イベントが見つかりません'];
    }

    $event_raw = aidunite_competition_read_event_meta_raw($event_id);
    $config = is_array($event_raw['generator_config'] ?? null) ? $event_raw['generator_config'] : [];
    $mode = sanitize_key((string) ($options['generator_mode'] ?? $config['generator_mode'] ?? 'skeleton'));
    $preview = !empty($options['preview']);
    $replace = !isset($options['replace_existing']) || !empty($options['replace_existing']);

    if ($mode === 'full_auto') {
        return ['ok' => false, 'code' => 'generator_not_supported', 'error' => 'full_auto は Phase 4 です'];
    }
    if ($mode === 'csv_import') {
        return ['ok' => false, 'code' => 'generator_not_supported', 'error' => 'csv_import は未実装です'];
    }
    if (!in_array($mode, ['skeleton', 'slot_assign'], true)) {
        return ['ok' => false, 'code' => 'invalid_params', 'error' => 'generator_mode が不正です'];
    }

    $template_id = (string) ($config['template_id'] ?? 'ainy_8_two_court_1day');
    if ($mode === 'slot_assign' && !aidunite_competition_generator_template_supports_slot_assign($template_id)) {
        return ['ok' => false, 'code' => 'generator_not_supported', 'error' => 'このテンプレは slot_assign 未対応です'];
    }

    $templates = aidunite_competition_get_generator_templates();
    $template = null;
    foreach ($templates as $row) {
        if (($row['template_id'] ?? '') === $template_id) {
            $template = $row;
            break;
        }
    }
    if ($template === null || empty($template['has_fixture'])) {
        return ['ok' => false, 'code' => 'generator_not_supported', 'error' => '試合表非対応テンプレです'];
    }

    $team_ids = [];
    foreach (aidunite_competition_read_entries_for_event($event_id) as $entry) {
        if (($entry['status'] ?? '') !== 'confirmed') {
            continue;
        }
        $tid = (int) ($entry['team']['id'] ?? $entry['team']['team_id'] ?? 0);
        if ($tid > 0) {
            $team_ids[] = $tid;
        }
    }

    $built = aidunite_competition_generator_build_knockout_draft($event_id, $team_ids, $config, $template);
    if (!empty($built['code'])) {
        return [
            'ok' => false,
            'code' => (string) $built['code'],
            'error' => (string) ($built['error'] ?? '生成に失敗しました'),
            'warnings' => is_array($built['warnings'] ?? null) ? $built['warnings'] : [],
        ];
    }

    $draft_fixtures = is_array($built['draft'] ?? null) ? $built['draft'] : [];
    $warnings = is_array($built['warnings'] ?? null) ? $built['warnings'] : [];

    if ($mode === 'slot_assign') {
        $slot_context = aidunite_competition_generator_resolve_slot_context($event_id, $config, $template);
        aidunite_competition_generator_assign_slots($draft_fixtures, $slot_context, $config, $warnings);
    }

    if ($preview) {
        $preview_payloads = [];
        foreach ($draft_fixtures as $fixture) {
            unset($fixture['winner_advances_to_key']);
            $preview_payloads[] = aidunite_competition_read_fixture_payload_from_array($fixture);
        }

        return [
            'ok' => true,
            'preview' => true,
            'fixtures' => $preview_payloads,
            'warnings' => $warnings,
        ];
    }

    if ($replace) {
        aidunite_competition_persist_delete_fixtures_for_event($event_id);
    }

    $persisted = aidunite_competition_generator_persist_draft_fixtures($draft_fixtures);
    if (isset($persisted['error'])) {
        return ['ok' => false, 'code' => 'save_failed', 'error' => (string) $persisted['error']];
    }

    return [
        'ok' => true,
        'fixtures' => $persisted['fixtures'] ?? [],
        'warnings' => $warnings,
    ];
}
