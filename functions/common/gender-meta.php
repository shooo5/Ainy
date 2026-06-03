<?php
/**
 * 性別メタの canonical 化（MVP: male / female のみ）と募集時のチーム整合バリデーション。
 *
 * ## MVP（練習試合マッチ）
 * - 使用値: male, female
 * - both（男子・女子可・混合募集）は廃止。DB 上に残っていても読取除外・新規保存拒否。
 * - 大会・multi-match 等は本ファイルの正規化を使わない経路があり得る（スコープ外）。
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * MVP で both 系データを読み取り除外したときの管理者向けログ（同一リクエスト内は context+post で1回）。
 *
 * @param string $context 例: match_board_recruitment, normalize
 * @param int    $post_id schedule / team の投稿 ID
 */
function aidunite_mvp_log_both_gender_excluded($context, $post_id = 0) {
    if (!apply_filters('aidunite_mvp_log_both_exclusion', true)) {
        return;
    }
    static $logged = [];
    $key = sanitize_key((string) $context) . ':' . (int) $post_id;
    if (isset($logged[$key])) {
        return;
    }
    $logged[$key] = true;
    $msg = sprintf(
        '[Ainy MVP] both 性別データは練習試合対象外: context=%s post_id=%d',
        $context,
        (int) $post_id
    );
    if (function_exists('current_user_can') && current_user_can('manage_options')) {
        error_log($msg);
    } elseif (defined('WP_DEBUG') && WP_DEBUG) {
        error_log($msg);
    }
}

/**
 * 正規化結果が MVP の練習試合で有効な性別か。
 *
 * @param string $canonical aidunite_normalize_gender_canonical の戻り
 */
function aidunite_mvp_gender_is_valid($canonical) {
    return in_array((string) $canonical, ['male', 'female'], true);
}

/**
 * チーム属性（team_gender_option）の表示ラベル。MVP は男子／女子のみ。
 *
 * @param string $canonical male|female|''
 * @return string
 */
function aidunite_team_gender_label($canonical) {
    $g = function_exists('aidunite_normalize_team_gender_option')
        ? aidunite_normalize_team_gender_option($canonical)
        : aidunite_normalize_gender_canonical($canonical);
    $labels = [
        'male'   => '男子',
        'female' => '女子',
    ];
    return $labels[$g] ?? '—';
}

/**
 * 募集条件（schedule_gender / gender_condition）の表示ラベル。MVP は男子／女子のみ。
 *
 * @param string $canonical male|female|''
 * @return string
 */
function aidunite_schedule_recruit_gender_label($canonical) {
    $g = aidunite_normalize_gender_canonical($canonical);
    $labels = [
        'male'   => '男子',
        'female' => '女子',
    ];
    return $labels[$g] ?? '—';
}

/**
 * 会場条件（schedule_place）の表示ラベル。※ gender の both とは別概念。
 *
 * @param string $venue home|away|either|both|undecided|''
 * @return string
 */
function aidunite_venue_condition_label($venue) {
    $v = strtolower(trim((string) $venue));
    $labels = [
        'home'      => 'ホーム',
        'away'      => 'アウェイ',
        'either'    => 'どちらでも可',
        'both'      => 'どちらでも可',
        'undecided' => '未定',
    ];
    return $labels[$v] ?? ($v !== '' ? $v : '—');
}

/**
 * raw が both 系か（正規化前の検出・ログ用）。
 *
 * @param mixed $raw
 */
function aidunite_mvp_gender_raw_is_both_legacy($raw) {
    $g = is_string($raw) ? strtolower(trim($raw)) : '';
    if ($g === 'both') {
        return true;
    }
    static $jp = ['男女', '混合', 'その他', '男女とも', '男子・女子可'];
    return in_array(is_string($raw) ? trim($raw) : '', $jp, true);
}

/**
 * 二つのスケジュール募集/希望性別が対戦として両立するか（掲示板・候補・スコア用）。
 * MVP: 男子×男子 / 女子×女子 のみ true。
 *
 * @param string $gender_a
 * @param string $gender_b
 * @return bool
 */
function aidunite_schedule_recruit_genders_compatible($gender_a, $gender_b) {
    if (!function_exists('aidunite_normalize_gender_canonical')) {
        return (string) $gender_a === (string) $gender_b;
    }
    $a = aidunite_normalize_gender_canonical((string) $gender_a);
    $b = aidunite_normalize_gender_canonical((string) $gender_b);
    if (!aidunite_mvp_gender_is_valid($a) || !aidunite_mvp_gender_is_valid($b)) {
        return false;
    }

    return $a === $b;
}

/**
 * 任意の性別系メタ値を canonical（male|female）へ正規化。both 系は空文字（読取除外）。
 *
 * @param mixed $raw
 * @return string '', 'male', 'female'
 */
function aidunite_normalize_gender_canonical($raw) {
    $g = is_string($raw) ? trim($raw) : '';
    if ($g === '') {
        return '';
    }
    $lower = strtolower($g);
    if ($lower === 'male') {
        return 'male';
    }
    if ($lower === 'female') {
        return 'female';
    }
    if ($lower === 'both' || aidunite_mvp_gender_raw_is_both_legacy($g)) {
        return '';
    }
    static $map = null;
    if ($map === null) {
        $map = [
            '男子' => 'male',
            '女子' => 'female',
        ];
    }

    return $map[$g] ?? '';
}

/**
 * team_gender_option 用（意味はチーム属性。MVP は male|female のみ有効）。
 *
 * @param mixed $raw
 * @return string '', 'male', 'female'
 */
function aidunite_normalize_team_gender_option($raw) {
    $g = is_string($raw) ? trim($raw) : '';
    if ($g === '' || strtolower($g) === 'both' || aidunite_mvp_gender_raw_is_both_legacy($g)) {
        if (aidunite_mvp_gender_raw_is_both_legacy($raw) || strtolower((string) $g) === 'both') {
            aidunite_mvp_log_both_gender_excluded('team_gender_option', 0);
        }
        return '';
    }

    return aidunite_normalize_gender_canonical($raw);
}

/**
 * 募集（intent=recruit）の schedule_gender がチーム属性に許容されるか検証する。
 *
 * @param int    $team_id WP post ID（team）
 * @param string $schedule_gender male|female（未正規化可）
 * @return true|\WP_Error
 */
function aidunite_validate_recruit_gender_for_team($team_id, $schedule_gender) {
    $team_id = (int) $team_id;
    if ($team_id < 1) {
        return new WP_Error('no_team', 'チーム情報が取得できません。');
    }
    if (aidunite_mvp_gender_raw_is_both_legacy($schedule_gender) || strtolower(trim((string) $schedule_gender)) === 'both') {
        return new WP_Error('invalid_gender', '男子・女子可（混合）の募集は利用できません。');
    }
    $g = aidunite_normalize_gender_canonical($schedule_gender);
    if (!aidunite_mvp_gender_is_valid($g)) {
        return new WP_Error('invalid_gender', '性別条件は男子または女子を選択してください。');
    }
    $team_raw = (string) get_post_meta($team_id, 'team_gender_option', true);
    $team_g   = aidunite_normalize_team_gender_option($team_raw);
    if ($team_g === '') {
        return true;
    }
    if ($team_g !== $g) {
        return new WP_Error(
            'recruit_gender_team_mismatch',
            $team_g === 'male'
                ? '男子チームの募集は「男子」のみ選択できます。'
                : '女子チームの募集は「女子」のみ選択できます。'
        );
    }

    return true;
}

/**
 * DB 上の旧性別表記を canonical に一括置換（冪等）。MVP: both への置換は行わない。
 *
 * @return void
 */
function aidunite_run_gender_canonical_db_migration() {
    global $wpdb;

    $pairs = [
        ['男子', 'male'],
        ['女子', 'female'],
    ];

    foreach ($pairs as $pair) {
        list($from, $to) = $pair;
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE meta_key = %s AND meta_value = %s",
                $to,
                'team_gender_option',
                $from
            )
        );

        $sched_keys = ['schedule_gender', 'matching_gender_condition', 'gender_condition', 'pre_established_gender'];
        foreach ($sched_keys as $sk) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->postmeta} pm
                    INNER JOIN {$wpdb->posts} po ON po.ID = pm.post_id AND po.post_type = 'schedule'
                    SET pm.meta_value = %s WHERE pm.meta_key = %s AND pm.meta_value = %s",
                    $to,
                    $sk,
                    $from
                )
            );
        }

        $mr_keys = ['selected_gender', 'preferred_gender', 'reconfirm_before_schedule_gender'];
        foreach ($mr_keys as $mk) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->postmeta} pm
                    INNER JOIN {$wpdb->posts} po ON po.ID = pm.post_id AND po.post_type = 'match_request'
                    SET pm.meta_value = %s WHERE pm.meta_key = %s AND pm.meta_value = %s",
                    $to,
                    $mk,
                    $from
                )
            );
        }
    }
}

/**
 * 初回のみ性別 canonical 移行を実行する。
 */
function aidunite_maybe_run_gender_canonical_migration() {
    if (get_option('aidunite_gender_canonical_v1')) {
        return;
    }
    if (!function_exists('aidunite_run_gender_canonical_db_migration')) {
        return;
    }
    aidunite_run_gender_canonical_db_migration();
    update_option('aidunite_gender_canonical_v1', 1, false);
}

add_action('init', 'aidunite_maybe_run_gender_canonical_migration', 3);
