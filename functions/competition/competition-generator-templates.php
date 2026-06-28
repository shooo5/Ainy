<?php
/**
 * 大会 generator テンプレート定義（§23）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @return array<int, array<string, mixed>>
 */
function aidunite_competition_get_generator_templates() {
    return [
        [
            'template_id' => 'ainy_session_single_block',
            'name' => '単日イベント（試合表なし）',
            'phase' => 1,
            'teams' => null,
            'courts' => [],
            'match_duration_min' => null,
            'break_min' => null,
            'has_fixture' => false,
            'generator_modes' => [],
            'notes' => 'クリニック・練習会・交流会向け',
        ],
        [
            'template_id' => 'ainy_8_single_court_1day',
            'name' => '8チーム単敗（1コート・1日）',
            'phase' => 2,
            'teams' => 8,
            'courts' => ['A'],
            'match_duration_min' => 25,
            'break_min' => 5,
            'has_fixture' => true,
            'generator_modes' => ['skeleton', 'slot_assign'],
            'notes' => '終了遅延しやすい',
        ],
        [
            'template_id' => 'ainy_8_two_court_1day',
            'name' => '8チーム単敗（2コート・1日）',
            'phase' => 2,
            'teams' => 8,
            'courts' => ['A', 'B'],
            'match_duration_min' => 25,
            'break_min' => 5,
            'has_fixture' => true,
            'recommended' => true,
            'generator_modes' => ['skeleton', 'slot_assign', 'full_auto'],
            'default_constraints' => [
                'avoid_back_to_back' => true,
                'avoid_same_region_round_1' => 'best_effort',
                'third_place_match' => false,
            ],
            'notes' => 'Ainy CUP 標準（初号テスト推奨）',
        ],
        [
            'template_id' => 'ainy_16_two_court_1day',
            'name' => '16チーム単敗（2コート・1日・タイト）',
            'phase' => 2,
            'teams' => 16,
            'courts' => ['A', 'B'],
            'match_duration_min' => 20,
            'break_min' => 5,
            'has_fixture' => true,
            'generator_modes' => ['skeleton'],
            'warnings' => ['1日収まりません → T5 提案'],
        ],
        [
            'template_id' => 'ainy_16_two_court_2day',
            'name' => '16チーム単敗（2コート・2日）',
            'phase' => 2,
            'teams' => 16,
            'courts' => ['A', 'B'],
            'match_duration_min' => 25,
            'break_min' => 5,
            'has_fixture' => true,
            'generator_modes' => ['skeleton', 'slot_assign'],
            'blocks_hint' => '1日目: 1回戦〜準々決勝 / 2日目: 準決勝・決勝',
        ],
        [
            'template_id' => 'ainy_4_round_robin_1court',
            'name' => '4チームリーグ（1コート・1日）',
            'phase' => 2,
            'teams' => 4,
            'courts' => ['A'],
            'match_duration_min' => 25,
            'break_min' => 5,
            'has_fixture' => true,
            'format' => 'round_robin',
            'generator_modes' => ['skeleton'],
        ],
        [
            'template_id' => 'ainy_12_group_to_ko',
            'name' => '12チーム（予選3組×4 → 本選）',
            'phase' => 4,
            'teams' => 12,
            'courts' => ['A', 'B'],
            'has_fixture' => true,
            'generator_modes' => ['csv_import'],
            'notes' => 'v0.1 は 501 + CSV 逃げ',
        ],
    ];
}
