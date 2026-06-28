<?php
/**
 * 大会 fixture slot_assign（コート・時刻割当）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param string $time HH:MM
 * @return int
 */
function aidunite_competition_generator_time_to_minutes($time) {
    $time = sanitize_text_field((string) $time);
    if (!preg_match('/^(\d{1,2}):(\d{2})$/', $time, $m)) {
        return 9 * 60;
    }

    return ((int) $m[1] * 60) + (int) $m[2];
}

/**
 * @param int $minutes
 * @return string HH:MM
 */
function aidunite_competition_generator_minutes_to_time($minutes) {
    $minutes = max(0, (int) $minutes);
    $h = (int) floor($minutes / 60);
    $m = $minutes % 60;

    return sprintf('%02d:%02d', $h, $m);
}

/**
 * @param string $date Y-m-d
 * @param int    $minutes_from_midnight
 * @return string Y-m-d H:i:s
 */
function aidunite_competition_generator_datetime_from_day_minutes($date, $minutes_from_midnight) {
    $date = sanitize_text_field((string) $date);
    $time = aidunite_competition_generator_minutes_to_time($minutes_from_midnight);

    return $date . ' ' . $time . ':00';
}

/**
 * @param int                  $event_id
 * @param array<string, mixed> $config
 * @param array<string, mixed> $template
 * @return array{date: string, start_time: string, end_time: string, courts: string[]}
 */
function aidunite_competition_generator_resolve_slot_context($event_id, array $config, array $template) {
    $blocks = function_exists('aidunite_competition_read_blocks_for_event')
        ? aidunite_competition_read_blocks_for_event($event_id)
        : [];
    $courts = is_array($config['courts'] ?? null) ? $config['courts'] : [];
    if ($courts === []) {
        $courts = is_array($template['courts'] ?? null) ? $template['courts'] : ['A'];
    }

    if ($blocks !== []) {
        $block = $blocks[0];

        return [
            'date' => (string) ($block['date'] ?? ''),
            'start_time' => (string) ($block['start_time'] ?: '09:00'),
            'end_time' => (string) ($block['end_time'] ?: '17:00'),
            'courts' => is_array($block['courts'] ?? null) && $block['courts'] !== []
                ? $block['courts']
                : $courts,
        ];
    }

    $event_raw = aidunite_competition_read_event_meta_raw($event_id);

    return [
        'date' => (string) ($event_raw['date_start'] ?? ''),
        'start_time' => '09:00',
        'end_time' => '17:00',
        'courts' => $courts,
    ];
}

/**
 * @param array<int, array<string, mixed>> $draft_fixtures
 * @param array<string, mixed>           $context
 * @param array<string, mixed>           $config
 * @param string[]                         $warnings
 */
function aidunite_competition_generator_assign_slots(array &$draft_fixtures, array $context, array $config, array &$warnings) {
    $date = (string) ($context['date'] ?? '');
    if ($date === '') {
        $warnings[] = 'slot_assign: 開催日が未設定のため時刻を割当できません';

        return;
    }

    $courts = array_values(array_filter(array_map('strval', (array) ($context['courts'] ?? ['A']))));
    if ($courts === []) {
        $courts = ['A'];
    }

    $court_count = count($courts);
    $match_min = max(5, (int) ($config['match_duration_min'] ?? 25));
    $break_min = max(0, (int) ($config['break_min'] ?? 5));
    $slot_min = $match_min + $break_min;
    $constraints = is_array($config['constraints'] ?? null) ? $config['constraints'] : [];
    $avoid_b2b = !empty($constraints['avoid_back_to_back']);

    $day_start = aidunite_competition_generator_time_to_minutes((string) ($context['start_time'] ?? '09:00'));
    $day_end = aidunite_competition_generator_time_to_minutes((string) ($context['end_time'] ?? '17:00'));
    $team_last_end = [];
    $court_next = array_fill(0, $court_count, $day_start);
    $global_cursor = $day_start;

    $by_round = [];
    foreach ($draft_fixtures as $idx => $fixture) {
        $by_round[(int) ($fixture['round'] ?? 0)][] = $idx;
    }
    ksort($by_round);

    foreach ($by_round as $round => $indexes) {
        $pending = $indexes;
        while ($pending !== []) {
            $progress = false;
            for ($c = 0; $c < $court_count; $c++) {
                if ($pending === []) {
                    break;
                }

                $pick_idx = null;
                foreach ($pending as $pos => $fixture_idx) {
                    $fixture = $draft_fixtures[$fixture_idx];
                    $team_a = (int) ($fixture['team_a_id'] ?? 0);
                    $team_b = (int) ($fixture['team_b_id'] ?? 0);
                    $teams = array_filter([$team_a, $team_b]);
                    $earliest = max($global_cursor, $court_next[$c]);
                    if ($avoid_b2b) {
                        foreach ($teams as $tid) {
                            if (isset($team_last_end[$tid])) {
                                $earliest = max($earliest, $team_last_end[$tid] + $slot_min);
                            }
                        }
                    }
                    if ($earliest + $match_min > $day_end) {
                        continue;
                    }
                    $pick_idx = $pos;
                    break;
                }

                if ($pick_idx === null) {
                    continue;
                }

                $fixture_idx = $pending[$pick_idx];
                unset($pending[$pick_idx]);
                $pending = array_values($pending);

                $fixture = $draft_fixtures[$fixture_idx];
                $team_a = (int) ($fixture['team_a_id'] ?? 0);
                $team_b = (int) ($fixture['team_b_id'] ?? 0);
                $teams = array_filter([$team_a, $team_b]);
                $start_min = max($global_cursor, $court_next[$c]);
                if ($avoid_b2b) {
                    foreach ($teams as $tid) {
                        if (isset($team_last_end[$tid])) {
                            $start_min = max($start_min, $team_last_end[$tid] + $slot_min);
                        }
                    }
                }

                if ($start_min + $match_min > $day_end) {
                    $warnings[] = 'slot_assign: 1日の終了時刻内に収まりません（' . ($fixture['title'] ?? '試合') . '）';
                    continue;
                }

                $end_min = $start_min + $match_min;
                $draft_fixtures[$fixture_idx]['scheduled_at'] = aidunite_competition_generator_datetime_from_day_minutes($date, $start_min);
                $draft_fixtures[$fixture_idx]['court_label'] = $courts[$c];
                $court_next[$c] = $end_min + $break_min;
                $global_cursor = max($global_cursor, $start_min);
                foreach ($teams as $tid) {
                    $team_last_end[$tid] = $end_min;
                }
                $progress = true;
            }

            if (!$progress) {
                if ($pending !== []) {
                    $warnings[] = 'slot_assign: 残り ' . count($pending) . ' 試合を当日枠内に割当できません';
                }
                break;
            }
        }
    }
}

/**
 * @param string $template_id
 * @return bool
 */
function aidunite_competition_generator_template_supports_slot_assign($template_id) {
    foreach (aidunite_competition_get_generator_templates() as $row) {
        if (($row['template_id'] ?? '') !== $template_id) {
            continue;
        }
        $modes = is_array($row['generator_modes'] ?? null) ? $row['generator_modes'] : [];

        return in_array('slot_assign', $modes, true);
    }

    return false;
}
