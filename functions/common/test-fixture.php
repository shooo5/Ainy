<?php
/**
 * テスト用フィクスチャ（PHPUnit / E2E）の識別・整理
 *
 * 安全方針: 一括削除は aidunite_test_fixture メタが付いた投稿のみ。
 * 名前・作者のヒューリスティックは「警告表示」のみ（誤削除防止）。
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @return string
 */
function aidunite_test_fixture_meta_key() {
    return 'aidunite_test_fixture';
}

/**
 * 投稿をテスト用フィクスチャとしてマーク
 *
 * @param int    $post_id
 * @param string $source phpunit|e2e|manual 等
 */
function aidunite_mark_post_as_test_fixture($post_id, $source = 'unknown') {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return;
    }
    update_post_meta($post_id, aidunite_test_fixture_meta_key(), sanitize_key((string) $source));
}

/**
 * @param int $post_id
 */
function aidunite_post_is_test_fixture($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return false;
    }
    if (get_post_meta($post_id, aidunite_test_fixture_meta_key(), true) !== '') {
        return true;
    }
    if (get_post_meta($post_id, 'e2e_test_data', true) === '1') {
        return true;
    }

    return false;
}

/**
 * ヒューリスティック: テスト残骸の可能性（削除対象にはしない）
 *
 * @param int $team_id
 */
function aidunite_team_looks_like_test_fixture($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return false;
    }

    if (aidunite_post_is_test_fixture($team_id)) {
        return true;
    }

    $team = get_post($team_id);
    if (!$team || $team->post_type !== 'team') {
        return false;
    }

    $title = trim((string) $team->post_title);
    $team_name_meta = trim((string) get_post_meta($team_id, 'team_name', true));
    if ($title === 'テストチーム' || $team_name_meta === 'テストチーム') {
        return true;
    }
    if (preg_match('/^テストチーム[0-9_]*$/u', $title) === 1 || preg_match('/^テストチーム[0-9_]*$/u', $team_name_meta) === 1) {
        return true;
    }
    if (preg_match('/^\[E2E\]/', $title) === 1) {
        return true;
    }

    $author_id = (int) $team->post_author;
    if ($author_id > 0 && get_user_meta($author_id, 'test_user', true) === 'true') {
        return true;
    }

    return false;
}

/**
 * 一括削除の対象（明示メタのみ）
 *
 * @return int[]
 */
function aidunite_discover_test_fixture_team_ids_for_deletion() {
    $teams = get_posts([
        'post_type' => 'team',
        'post_status' => ['publish', 'pending', 'draft', 'private'],
        'numberposts' => -1,
        'fields' => 'ids',
        'meta_key' => aidunite_test_fixture_meta_key(),
        'meta_compare' => 'EXISTS',
    ]);

    if (!is_array($teams)) {
        return [];
    }

    return array_values(array_map('intval', $teams));
}

/**
 * 警告バナー用（ヒューリスティック含む・削除はしない）
 *
 * @return int[]
 */
function aidunite_discover_test_fixture_team_ids() {
    $teams = get_posts([
        'post_type' => 'team',
        'post_status' => ['publish', 'pending', 'draft', 'private'],
        'numberposts' => -1,
        'fields' => 'ids',
    ]);

    if (!is_array($teams)) {
        return [];
    }

    $ids = [];
    foreach ($teams as $team_id) {
        if (aidunite_team_looks_like_test_fixture((int) $team_id)) {
            $ids[] = (int) $team_id;
        }
    }

    return array_values(array_unique($ids));
}

/**
 * テスト用フィクスチャチームを一括削除（明示メタ付きのみ）
 *
 * @return array{deleted:int,failed:string[],skipped_heuristic:int}
 */
function aidunite_delete_test_fixture_teams() {
    $deleted = 0;
    $failed = [];
    $to_delete = aidunite_discover_test_fixture_team_ids_for_deletion();

    foreach ($to_delete as $team_id) {
        if (!function_exists('aidunite_admin_delete_team')) {
            $failed[] = $team_id . ': aidunite_admin_delete_team が利用できません';
            continue;
        }
        $result = aidunite_admin_delete_team($team_id);
        if (is_wp_error($result)) {
            $failed[] = $team_id . ': ' . $result->get_error_message();
            continue;
        }
        $deleted++;
    }

    $heuristic_only = 0;
    foreach (aidunite_discover_test_fixture_team_ids() as $hid) {
        if (!in_array($hid, $to_delete, true)) {
            $heuristic_only++;
        }
    }

    return [
        'deleted' => $deleted,
        'failed' => $failed,
        'skipped_heuristic' => $heuristic_only,
    ];
}

/**
 * チーム登録時: テスト実行時・明示フラグ・test_user のみマーク（名前だけではマークしない）
 *
 * @param int   $team_id
 * @param int   $user_id
 * @param array $team_data
 */
function aidunite_maybe_mark_team_fixture_on_register($team_id, $user_id, $team_data) {
    $team_id = (int) $team_id;
    $user_id = (int) $user_id;
    if ($team_id <= 0) {
        return;
    }

    if (defined('AIDUNITE_TEST_MODE') && AIDUNITE_TEST_MODE) {
        aidunite_mark_post_as_test_fixture($team_id, 'phpunit');
        return;
    }

    if (!empty($team_data['aidunite_test_fixture'])) {
        aidunite_mark_post_as_test_fixture($team_id, sanitize_key((string) $team_data['aidunite_test_fixture']));
        return;
    }

    if ($user_id > 0 && get_user_meta($user_id, 'test_user', true) === 'true') {
        aidunite_mark_post_as_test_fixture($team_id, 'test_user');
    }
}
