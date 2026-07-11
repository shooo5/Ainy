<?php
/**
 * Match / Club プランカタログ（支払い設定・料金プラン LP 共有）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @return array<string, mixed>
 */
function aidunite_payment_read_pricing_amounts() {
    $config = function_exists('aidunite_get_payment_config') ? aidunite_get_payment_config() : [];

    return [
        'match_amount' => (int) ($config['match']['monthly_amount'] ?? 2000),
        'club_per_player' => (int) ($config['club']['per_player_amount'] ?? 500),
        'club_minimum' => (int) ($config['club']['minimum_addon'] ?? 6000),
        'club_example_players' => 20,
    ];
}

/**
 * Match / Club の比較カード用カタログ
 *
 * @return array<string, array<string, mixed>>
 */
function aidunite_payment_get_product_plan_catalog() {
    $amounts = aidunite_payment_read_pricing_amounts();
    $match_amount = $amounts['match_amount'];
    $club_per_player = $amounts['club_per_player'];
    $club_minimum = $amounts['club_minimum'];
    $example_players = $amounts['club_example_players'];
    $example_fee = $match_amount + ($club_per_player * $example_players);
    $trial_copy = function_exists('aidunite_payment_get_trial_copy') ? aidunite_payment_get_trial_copy() : [
        'label' => '2ヶ月無料でお試しいただけます',
        'cta' => '2ヶ月無料で始める',
    ];

    return [
        'match' => [
            'product_plan' => 'match',
            'plan_id' => 'plan_match',
            'name' => 'Matchプラン',
            'tagline' => '試合成立を加速するエントリープラン',
            'description' => '試合マッチングに必要な機能をすべて利用できます',
            'price_label' => sprintf('月額 ¥%s（税込）', number_format($match_amount)),
            'price_amount' => $match_amount,
            'trial_label' => (string) ($trial_copy['label'] ?? '2ヶ月無料でお試しいただけます'),
            'summary' => '試合成立を加速する機能がすべて利用できます',
            'cta_label' => (string) ($trial_copy['cta'] ?? '2ヶ月無料で始める'),
            'is_recommended' => false,
            'features' => [
                ['icon' => 'handshake', 'title' => '試合募集・申請', 'text' => '対戦相手を探して試合を設定'],
                ['icon' => 'calendar_month', 'title' => 'スケジュール管理', 'text' => '練習・試合スケジュールを一元管理'],
                ['icon' => 'chat', 'title' => 'チャット', 'text' => 'チーム内のコミュニケーションを円滑に'],
                ['icon' => 'search', 'title' => '対戦相手検索', 'text' => '条件に合う対戦相手を簡単検索'],
            ],
        ],
        'club' => [
            'product_plan' => 'club',
            'plan_id' => 'plan_club',
            'name' => 'Clubプラン',
            'tagline' => 'Matchの機能すべて＋チーム運営をまるごとサポート',
            'description' => '試合マッチングに加え、日々のチーム運営を効率化',
            'price_label' => sprintf('月額 ¥%s ＋ ¥%s/人（税込）', number_format($match_amount), number_format($club_per_player)),
            'price_base' => $match_amount,
            'price_per_player' => $club_per_player,
            'trial_label' => (string) ($trial_copy['label'] ?? '2ヶ月無料でお試しいただけます'),
            'summary' => 'Matchのすべての機能に加え、チーム運営機能も利用できます',
            'minimum_note' => sprintf('最低月額 ¥%s/月（税込）', number_format($club_minimum)),
            'example_players' => $example_players,
            'example_fee' => max($club_minimum, $example_fee),
            'example_label' => sprintf('例えば%d名の場合', $example_players),
            'cta_label' => (string) ($trial_copy['cta'] ?? '2ヶ月無料で始める'),
            'is_recommended' => true,
            'features' => [
                ['icon' => 'check_circle', 'title' => 'Matchプランの機能すべて', 'text' => ''],
                ['icon' => 'person_check', 'title' => '出欠管理', 'text' => '練習・試合の出欠をかんたん管理'],
                ['icon' => 'family_group', 'title' => '保護者連絡', 'text' => '保護者への一斉連絡・通知'],
                ['icon' => 'payments', 'title' => '月謝管理（Stripe連携）', 'text' => '月謝の徴収・管理を効率化'],
                ['icon' => 'group', 'title' => 'メンバー管理', 'text' => '選手・スタッフ情報を一元管理'],
                ['icon' => 'bar_chart_4_bars', 'title' => 'データ管理・成長分析', 'text' => '活動データの蓄積と可視化'],
            ],
        ],
    ];
}

/**
 * 料金プラン LP（/plan-info）用ビューモデル
 *
 * @return array<string, mixed>
 */
function aidunite_payment_read_plan_info_page_model() {
    $amounts = aidunite_payment_read_pricing_amounts();
    $catalog = aidunite_payment_get_product_plan_catalog();
    $is_logged_in = is_user_logged_in();
    $user_id = $is_logged_in ? (int) get_current_user_id() : 0;
    $team_id = 0;

    if ($user_id > 0 && function_exists('aidunite_user_read_primary_team_id')) {
        $team_id = (int) aidunite_user_read_primary_team_id($user_id);
    }

    if ($is_logged_in && $team_id > 0) {
        $cta_url = home_url('/payment-setup');
        $cta_label = '支払い設定へ';
    } elseif ($is_logged_in) {
        $cta_url = home_url('/team-registration');
        $cta_label = 'チーム登録を始める';
    } else {
        $cta_url = home_url('/team-registration');
        $cta_label = 'チーム登録を始める';
    }

    return [
        'amounts' => $amounts,
        'plans' => array_values($catalog),
        'match_plan' => $catalog['match'],
        'club_plan' => $catalog['club'],
        'cta_url' => $cta_url,
        'cta_label' => $cta_label,
        'payment_setup_url' => home_url('/payment-setup'),
        'is_logged_in' => $is_logged_in,
        'has_team' => $team_id > 0,
    ];
}
