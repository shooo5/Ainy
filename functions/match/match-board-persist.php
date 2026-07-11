<?php
/**
 * 試合掲示板（match_board）の状態保存本体（normalize 経由 → postmeta 一本化）
 *
 * 正本: match_board_status は game MR 集合から算出し、本モジュール経由でのみ書き込む。
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * board_status 生値を canonical へ（open / pending / established）
 *
 * @param mixed $raw
 * @return string
 */
function aidunite_match_board_normalize_status($raw) {
    if (function_exists('aidunite_normalize_match_board_status_value')) {
        return (string) aidunite_normalize_match_board_status_value($raw);
    }
    $s = strtolower(trim((string) $raw));
    if ($s === 'accepted') {
        return 'established';
    }
    return $s;
}

/**
 * match_board_status を正規化して保存する（唯一の書き込み口）
 *
 * @param int    $board_id
 * @param string $status_raw
 * @return string 保存した canonical（失敗時は空文字）
 */
function aidunite_match_board_write_status_meta($board_id, $status_raw) {
    $board_id = (int) $board_id;
    if ($board_id < 1 || get_post_type($board_id) !== 'match_board') {
        return '';
    }

    $normalized = aidunite_match_board_normalize_status($status_raw);
    if (!in_array($normalized, ['open', 'pending', 'established'], true)) {
        return '';
    }

    update_post_meta($board_id, 'match_board_status', $normalized);

    return $normalized;
}

/**
 * 表示・診断用: match_board の canonical メタ
 *
 * @param int $board_id
 * @return array<string, mixed>
 */
/**
 * match_board に紐づく募集 schedule ID（meta schedule_id 優先、post_parent フォールバック）
 *
 * @param int $board_id
 * @return int
 */
function aidunite_match_board_resolve_recruit_schedule_id($board_id) {
    $board_id = (int) $board_id;
    if ($board_id < 1) {
        return 0;
    }
    $from_meta = (int) get_post_meta($board_id, 'schedule_id', true);
    if ($from_meta > 0 && get_post_type($from_meta) === 'schedule') {
        return $from_meta;
    }
    $parent = (int) wp_get_post_parent_id($board_id);
    if ($parent > 0 && get_post_type($parent) === 'schedule') {
        return $parent;
    }

    return 0;
}

function aidunite_match_board_get_canonical_meta($board_id) {
    $board_id = (int) $board_id;
    if ($board_id < 1 || get_post_type($board_id) !== 'match_board') {
        return [];
    }

    $schedule_id = aidunite_match_board_resolve_recruit_schedule_id($board_id);

    $raw_status = (string) get_post_meta($board_id, 'match_board_status', true);
    $board_status = aidunite_match_board_normalize_status($raw_status);

    $computed = '';
    if ($schedule_id > 0 && function_exists('aidunite_compute_match_board_status_from_game')) {
        $computed = (string) aidunite_compute_match_board_status_from_game($schedule_id);
    }

    return [
        'match_board_id' => $board_id,
        'post_status' => (string) get_post_status($board_id),
        'schedule_id' => $schedule_id,
        'team_id' => (int) get_post_meta($board_id, 'team_id', true),
        'board_status' => $board_status,
        'board_status_computed_from_game' => $computed,
        'board_status_in_sync' => ($computed === '' || $board_status === $computed),
    ];
}

/**
 * 募集 schedule（anchor）の MR 集合から掲示板ステータスを再同期する。
 *
 * @param int $recruit_schedule_id
 * @return string 設定した board_status（board 無しは空文字）
 */
function aidunite_match_board_find_id_for_recruit_schedule($recruit_schedule_id) {
    $recruit_schedule_id = (int) $recruit_schedule_id;
    if ($recruit_schedule_id <= 0) {
        return 0;
    }
    if (function_exists('aidunite_schedule_find_match_board_id_for_schedule')) {
        $by_meta = (int) aidunite_schedule_find_match_board_id_for_schedule($recruit_schedule_id);
        if ($by_meta > 0) {
            return $by_meta;
        }
    }
    $board_id = (int) wp_get_post_parent_id($recruit_schedule_id);
    if ($board_id > 0 && get_post_type($board_id) === 'match_board') {
        return $board_id;
    }
    if (function_exists('aidunite_get_schedule_match_boards')) {
        $boards = aidunite_get_schedule_match_boards($recruit_schedule_id);
        if (!empty($boards[0])) {
            return (int) $boards[0]->ID;
        }
    }

    return 0;
}

function aidunite_match_board_sync_status_from_game($recruit_schedule_id) {
    $recruit_schedule_id = (int) $recruit_schedule_id;
    if ($recruit_schedule_id <= 0) {
        return '';
    }

    $board_id = aidunite_match_board_find_id_for_recruit_schedule($recruit_schedule_id);
    if ($board_id < 1) {
        return '';
    }

    if (!function_exists('aidunite_compute_match_board_status_from_game')) {
        return aidunite_match_board_write_status_meta($board_id, 'open');
    }

    $board_status = (string) aidunite_compute_match_board_status_from_game($recruit_schedule_id);

    return aidunite_match_board_write_status_meta($board_id, $board_status);
}

/**
 * 新規 board 作成直後の初期状態（open）
 *
 * @param int $board_id
 * @return string
 */
function aidunite_match_board_bootstrap_open_status($board_id) {
    return aidunite_match_board_write_status_meta($board_id, 'open');
}

/**
 * 全 match_board の match_board_status を game MR から再同期（drift / accepted 残存の修復）
 *
 * @param bool $dry_run true のとき書き込みなしで件数のみ
 * @return array{fixed_count:int,skipped_count:int,total:int,dry_run:bool}
 */
function aidunite_match_board_repair_all_statuses($dry_run = false) {
    $boards = get_posts([
        'post_type'      => 'match_board',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);
    $fixed = 0;
    $skipped = 0;
    foreach ($boards ?: [] as $board_id) {
        $board_id = (int) $board_id;
        $recruit_id = aidunite_match_board_resolve_recruit_schedule_id($board_id);
        if ($recruit_id <= 0) {
            $skipped++;
            continue;
        }
        if (!function_exists('aidunite_compute_match_board_status_from_game')) {
            $skipped++;
            continue;
        }
        $before = aidunite_match_board_normalize_status(get_post_meta($board_id, 'match_board_status', true));
        $expected = (string) aidunite_compute_match_board_status_from_game($recruit_id);
        if ($expected === '') {
            $skipped++;
            continue;
        }
        if ($before === $expected) {
            continue;
        }
        if (!$dry_run) {
            aidunite_match_board_write_status_meta($board_id, $expected);
        }
        $fixed++;
    }

    return [
        'fixed_count'    => $fixed,
        'skipped_count'  => $skipped,
        'total'          => count($boards ?: []),
        'dry_run'        => (bool) $dry_run,
    ];
}
