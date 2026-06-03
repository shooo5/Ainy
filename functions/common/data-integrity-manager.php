<?php
/**
 * データ整合性管理システム
 * AidUnite Theme - Data Integrity Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

// エラーハンドラーを読み込み
require_once get_stylesheet_directory() . '/functions/common/error-handler.php';

class AidUniteDataIntegrityManager {

    /**
     * データ整合性チェック（包括的）
     */
    public static function checkDataIntegrity() {
        $results = [
            'schedules' => self::checkScheduleIntegrity(),
            'teams' => self::checkTeamIntegrity(),
            'users' => self::checkUserIntegrity(),
            'match_requests' => self::checkMatchRequestIntegrity(),
            'match_lifecycle' => self::checkMatchLifecycleIntegrity(),
            'notifications' => self::checkNotificationIntegrity(),
        ];

        $total_issues = array_sum(array_column($results, 'issue_count'));

        AidUniteErrorHandler::info('データ整合性チェック完了', [
            'total_issues' => $total_issues,
            'results' => $results
        ]);

        return [
            'total_issues' => $total_issues,
            'results' => $results,
            'timestamp' => current_time('mysql')
        ];
    }

    /**
     * スケジュール整合性チェック
     */
    private static function checkScheduleIntegrity() {
        global $wpdb;
        $issues = [];

        // 1. 孤立したスケジュール（チームIDが無効）
        $query = "
            SELECT p.ID, p.post_title, pm.meta_value as team_id
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'schedule'
            AND pm.meta_key = 'team_id'
            AND pm.meta_value NOT IN (
                SELECT ID FROM {$wpdb->posts} WHERE post_type = 'team' AND post_status = 'publish'
            )
        ";

        $orphaned_schedules = $wpdb->get_results($query);
        foreach ($orphaned_schedules as $schedule) {
            $issues[] = [
                'type' => 'orphaned_schedule',
                'id' => $schedule->ID,
                'title' => $schedule->post_title,
                'team_id' => $schedule->team_id,
                'message' => '無効なチームIDを持つスケジュール'
            ];
        }

        // 2. 日付形式が無効なスケジュール
        $query = "
            SELECT p.ID, p.post_title, pm.meta_value as schedule_date
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'schedule'
            AND pm.meta_key = 'schedule_date'
            AND pm.meta_value NOT REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'
        ";

        $invalid_date_schedules = $wpdb->get_results($query);
        foreach ($invalid_date_schedules as $schedule) {
            $issues[] = [
                'type' => 'invalid_date_format',
                'id' => $schedule->ID,
                'title' => $schedule->post_title,
                'date' => $schedule->schedule_date,
                'message' => '無効な日付形式のスケジュール'
            ];
        }

        // 3. 時間形式が無効なスケジュール
        $query = "
            SELECT p.ID, p.post_title,
                   pm_start.meta_value as start_time,
                   pm_end.meta_value as end_time
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_start ON p.ID = pm_start.post_id
            INNER JOIN {$wpdb->postmeta} pm_end ON p.ID = pm_end.post_id
            WHERE p.post_type = 'schedule'
            AND pm_start.meta_key = 'schedule_start_time'
            AND pm_end.meta_key = 'schedule_end_time'
            AND (pm_start.meta_value NOT REGEXP '^[0-9]{2}:[0-9]{2}$'
                 OR pm_end.meta_value NOT REGEXP '^[0-9]{2}:[0-9]{2}$')
        ";

        $invalid_time_schedules = $wpdb->get_results($query);
        foreach ($invalid_time_schedules as $schedule) {
            $issues[] = [
                'type' => 'invalid_time_format',
                'id' => $schedule->ID,
                'title' => $schedule->post_title,
                'start_time' => $schedule->start_time,
                'end_time' => $schedule->end_time,
                'message' => '無効な時間形式のスケジュール'
            ];
        }

        return [
            'issue_count' => count($issues),
            'issues' => $issues
        ];
    }

    /**
     * チーム整合性チェック
     */
    private static function checkTeamIntegrity() {
        global $wpdb;
        $issues = [];

        // 1. 無効なユーザーIDを持つチームメンバー
        $query = "
            SELECT p.ID, p.post_title, pm.meta_value as team_members
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'team'
            AND pm.meta_key = 'team_members'
        ";

        $teams = $wpdb->get_results($query);
        foreach ($teams as $team) {
            $members = maybe_unserialize($team->team_members);
            if (is_array($members)) {
                foreach ($members as $member_id) {
                    if (!get_userdata($member_id)) {
                        $issues[] = [
                            'type' => 'invalid_member',
                            'team_id' => $team->ID,
                            'team_title' => $team->post_title,
                            'member_id' => $member_id,
                            'message' => '無効なユーザーIDを持つチームメンバー'
                        ];
                    }
                }
            }
        }

        // 2. 代表者が設定されていないチーム
        $query = "
            SELECT p.ID, p.post_title
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'team_leader'
            WHERE p.post_type = 'team'
            AND p.post_status = 'publish'
            AND (pm.meta_value IS NULL OR pm.meta_value = '')
        ";

        $teams_without_leader = $wpdb->get_results($query);
        foreach ($teams_without_leader as $team) {
            $issues[] = [
                'type' => 'no_leader',
                'team_id' => $team->ID,
                'team_title' => $team->post_title,
                'message' => '代表者が設定されていないチーム'
            ];
        }

        return [
            'issue_count' => count($issues),
            'issues' => $issues
        ];
    }

    /**
     * ユーザー整合性チェック
     */
    private static function checkUserIntegrity() {
        global $wpdb;
        $issues = [];

        // 1. 無効なチームIDを持つユーザー
        $query = "
            SELECT u.ID, u.user_login, um.meta_value as team_id
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
            WHERE um.meta_key = 'team_id'
            AND um.meta_value NOT IN (
                SELECT ID FROM {$wpdb->posts} WHERE post_type = 'team' AND post_status = 'publish'
            )
            AND um.meta_value != ''
        ";

        $users_with_invalid_team = $wpdb->get_results($query);
        foreach ($users_with_invalid_team as $user) {
            $issues[] = [
                'type' => 'invalid_team_id',
                'user_id' => $user->ID,
                'user_login' => $user->user_login,
                'team_id' => $user->team_id,
                'message' => '無効なチームIDを持つユーザー'
            ];
        }

        // 2. 役割が設定されていないユーザー
        $query = "
            SELECT u.ID, u.user_login
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'aidunite_role'
            WHERE (um.meta_value IS NULL OR um.meta_value = '')
        ";

        $users_without_role = $wpdb->get_results($query);
        foreach ($users_without_role as $user) {
            $issues[] = [
                'type' => 'no_role',
                'user_id' => $user->ID,
                'user_login' => $user->user_login,
                'message' => '役割が設定されていないユーザー'
            ];
        }

        return [
            'issue_count' => count($issues),
            'issues' => $issues
        ];
    }

    /**
     * マッチ申請整合性チェック
     */
    private static function checkMatchRequestIntegrity() {
        global $wpdb;
        $issues = [];

        // 1. 無効なチームIDを持つマッチ申請
        $query = "
            SELECT p.ID, p.post_title,
                   pm_from.meta_value as from_team_id,
                   pm_to.meta_value as to_team_id
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_from ON p.ID = pm_from.post_id
            INNER JOIN {$wpdb->postmeta} pm_to ON p.ID = pm_to.post_id
            WHERE p.post_type = 'match_request'
            AND pm_from.meta_key = 'from_team_id'
            AND pm_to.meta_key = 'to_team_id'
            AND (pm_from.meta_value NOT IN (
                SELECT ID FROM {$wpdb->posts} WHERE post_type = 'team' AND post_status = 'publish'
            ) OR pm_to.meta_value NOT IN (
                SELECT ID FROM {$wpdb->posts} WHERE post_type = 'team' AND post_status = 'publish'
            ))
        ";

        $invalid_match_requests = $wpdb->get_results($query);
        foreach ($invalid_match_requests as $request) {
            $issues[] = [
                'type' => 'invalid_team_id',
                'id' => $request->ID,
                'title' => $request->post_title,
                'from_team_id' => $request->from_team_id,
                'to_team_id' => $request->to_team_id,
                'message' => '無効なチームIDを持つマッチ申請'
            ];
        }

        // 2. 無効なスケジュールIDを持つマッチ申請
        $query = "
            SELECT p.ID, p.post_title,
                   pm_from.meta_value as from_schedule_id,
                   pm_to.meta_value as to_schedule_id
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_from ON p.ID = pm_from.post_id
            INNER JOIN {$wpdb->postmeta} pm_to ON p.ID = pm_to.post_id
            WHERE p.post_type = 'match_request'
            AND pm_from.meta_key = 'from_schedule_id'
            AND pm_to.meta_key = 'to_schedule_id'
            AND (pm_from.meta_value NOT IN (
                SELECT ID FROM {$wpdb->posts} WHERE post_type = 'schedule' AND post_status = 'publish'
            ) OR pm_to.meta_value NOT IN (
                SELECT ID FROM {$wpdb->posts} WHERE post_type = 'schedule' AND post_status = 'publish'
            ))
        ";

        $invalid_schedule_requests = $wpdb->get_results($query);
        foreach ($invalid_schedule_requests as $request) {
            $issues[] = [
                'type' => 'invalid_schedule_id',
                'id' => $request->ID,
                'title' => $request->post_title,
                'from_schedule_id' => $request->from_schedule_id,
                'to_schedule_id' => $request->to_schedule_id,
                'message' => '無効なスケジュールIDを持つマッチ申請'
            ];
        }

        return [
            'issue_count' => count($issues),
            'issues' => $issues
        ];
    }

    /**
     * schedule × match_request × match_board × chat の横断整合性（第1段階ガード補完）
     */
    private static function checkMatchLifecycleIntegrity() {
        global $wpdb;
        $issues = [];

        $schedule_ids = $wpdb->get_col(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'schedule'"
        );
        $schedule_set = array_fill_keys(array_map('intval', $schedule_ids ?: []), true);

        $mr_schedule_keys = ['to_schedule_id', 'my_schedule_id', 'from_schedule_id'];
        foreach ($mr_schedule_keys as $meta_key) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT p.ID AS mr_id, pm.meta_value AS schedule_id
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 WHERE p.post_type = 'match_request'
                 AND pm.meta_key = %s
                 AND pm.meta_value REGEXP '^[0-9]+$'
                 AND CAST(pm.meta_value AS UNSIGNED) > 0",
                $meta_key
            ));
            foreach ($rows ?: [] as $row) {
                $sid = (int) $row->schedule_id;
                if ($sid > 0 && !isset($schedule_set[$sid])) {
                    $issues[] = [
                        'type' => 'mr_orphan_schedule_ref',
                        'match_request_id' => (int) $row->mr_id,
                        'meta_key' => $meta_key,
                        'schedule_id' => $sid,
                        'message' => 'マッチ申請が存在しないスケジュールを参照している',
                    ];
                }
            }
        }

        $boards = $wpdb->get_results(
            "SELECT p.ID AS board_id, p.post_parent AS recruit_schedule_id,
                    pm.meta_value AS match_board_status
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'match_board_status'
             WHERE p.post_type = 'match_board'"
        );
        foreach ($boards ?: [] as $board) {
            $recruit_id = (int) $board->recruit_schedule_id;
            if ($recruit_id <= 0) {
                $issues[] = [
                    'type' => 'board_missing_parent',
                    'board_id' => (int) $board->board_id,
                    'message' => 'match_board の post_parent（募集 schedule）が未設定',
                ];
                continue;
            }
            if (!isset($schedule_set[$recruit_id])) {
                $issues[] = [
                    'type' => 'board_orphan_parent',
                    'board_id' => (int) $board->board_id,
                    'recruit_schedule_id' => $recruit_id,
                    'message' => 'match_board の親スケジュールが存在しない',
                ];
                continue;
            }
            if (!function_exists('aidunite_compute_match_board_status_from_game')) {
                continue;
            }
            $expected = aidunite_compute_match_board_status_from_game($recruit_id);
            $actual   = (string) ($board->match_board_status ?? '');
            if ($expected !== '' && $actual !== $expected) {
                $issues[] = [
                    'type' => 'board_status_drift',
                    'board_id' => (int) $board->board_id,
                    'recruit_schedule_id' => $recruit_id,
                    'expected' => $expected,
                    'actual' => $actual,
                    'message' => '掲示板ステータスがゲーム上の MR 状態と不一致',
                ];
            }
        }

        $chat_table = $wpdb->prefix . 'chat_rooms';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $chat_table)) === $chat_table) {
            $columns = $wpdb->get_col("SHOW COLUMNS FROM {$chat_table}");
            $columns = is_array($columns) ? $columns : [];
            if (in_array('match_id', $columns, true)) {
                $mr_ids = $wpdb->get_col(
                    "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'match_request'"
                );
                $mr_set = array_fill_keys(array_map('intval', $mr_ids ?: []), true);
                $rooms = $wpdb->get_results(
                    "SELECT id, match_id, schedule_id, status, room_type
                     FROM {$chat_table}
                     WHERE match_id IS NOT NULL AND match_id > 0"
                );
                foreach ($rooms ?: [] as $room) {
                    $mid = (int) $room->match_id;
                    if ($mid > 0 && !isset($mr_set[$mid])) {
                        $issues[] = [
                            'type' => 'chat_orphan_match_id',
                            'chat_room_id' => (int) $room->id,
                            'match_id' => $mid,
                            'message' => 'チャットルームが存在しない match_request を参照している',
                        ];
                    }
                }
            }
        }

        return [
            'issue_count' => count($issues),
            'issues' => $issues,
        ];
    }

    /**
     * 通知整合性チェック
     */
    private static function checkNotificationIntegrity() {
        global $wpdb;
        $issues = [];

        // 1. 無効なユーザーIDを持つ通知
        $query = "
            SELECT p.ID, p.post_title, p.post_author
            FROM {$wpdb->posts} p
            WHERE p.post_type = 'notification'
            AND p.post_author NOT IN (
                SELECT ID FROM {$wpdb->users}
            )
        ";

        $invalid_notifications = $wpdb->get_results($query);
        foreach ($invalid_notifications as $notification) {
            $issues[] = [
                'type' => 'invalid_author',
                'id' => $notification->ID,
                'title' => $notification->post_title,
                'author_id' => $notification->post_author,
                'message' => '無効なユーザーIDを持つ通知'
            ];
        }

        return [
            'issue_count' => count($issues),
            'issues' => $issues
        ];
    }

    /**
     * 自動修復処理
     */
    public static function autoRepair($repair_options = []) {
        $default_options = [
            'fix_orphaned_schedules' => true,
            'fix_invalid_dates' => true,
            'fix_invalid_times' => true,
            'fix_users_without_role' => true,
            'cleanup_invalid_notifications' => true,
            'sync_match_board_status' => true,
        ];

        $options = wp_parse_args($repair_options, $default_options);
        $repair_results = [];

        if ($options['fix_orphaned_schedules']) {
            $repair_results['orphaned_schedules'] = self::fixOrphanedSchedules();
        }

        if ($options['fix_invalid_dates']) {
            $repair_results['invalid_dates'] = self::fixInvalidDates();
        }

        if ($options['fix_invalid_times']) {
            $repair_results['invalid_times'] = self::fixInvalidTimes();
        }

        if ($options['fix_users_without_role']) {
            $repair_results['users_without_role'] = self::fixUsersWithoutRole();
        }

        if ($options['cleanup_invalid_notifications']) {
            $repair_results['invalid_notifications'] = self::cleanupInvalidNotifications();
        }

        if ($options['sync_match_board_status']) {
            $repair_results['match_board_status'] = self::repairMatchBoardStatusDrift();
        }

        AidUniteErrorHandler::info('データ自動修復完了', [
            'options' => $options,
            'results' => $repair_results
        ]);

        return $repair_results;
    }

    /**
     * 孤立したスケジュールの修復
     */
    private static function fixOrphanedSchedules() {
        global $wpdb;

        $query = "
            SELECT p.ID, p.post_author
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'schedule'
            AND pm.meta_key = 'team_id'
            AND pm.meta_value NOT IN (
                SELECT ID FROM {$wpdb->posts} WHERE post_type = 'team' AND post_status = 'publish'
            )
        ";

        $orphaned_schedules = $wpdb->get_results($query);
        $fixed_count = 0;

        foreach ($orphaned_schedules as $schedule) {
            // ユーザーのチームIDを取得
            $user_team_id = function_exists('aidunite_resolve_schedule_owner_team_id')
                ? (int) aidunite_resolve_schedule_owner_team_id((int) $schedule->ID)
                : 0;
            if ($user_team_id <= 0) {
                $aid = (int) $schedule->post_author;
                if ($aid > 0) {
                    $user_team_id = function_exists('aidunite_get_current_team_id')
                        ? (int) aidunite_get_current_team_id($aid)
                        : (int) get_user_meta($aid, 'team_id', true);
                }
            }
            if ($user_team_id && get_post($user_team_id)) {
                update_post_meta($schedule->ID, 'team_id', $user_team_id);
                $fixed_count++;
            }
        }

        return ['fixed_count' => $fixed_count];
    }

    /**
     * 無効な日付の修復
     */
    private static function fixInvalidDates() {
        global $wpdb;

        $query = "
            SELECT p.ID, pm.meta_value as schedule_date
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'schedule'
            AND pm.meta_key = 'schedule_date'
            AND pm.meta_value NOT REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'
        ";

        $invalid_dates = $wpdb->get_results($query);
        $fixed_count = 0;

        foreach ($invalid_dates as $schedule) {
            // 日付を正規化
            $normalized_date = date('Y-m-d', strtotime($schedule->schedule_date));
            if ($normalized_date !== '1970-01-01') {
                update_post_meta($schedule->ID, 'schedule_date', $normalized_date);
                $fixed_count++;
            }
        }

        return ['fixed_count' => $fixed_count];
    }

    /**
     * 無効な時間の修復
     */
    private static function fixInvalidTimes() {
        global $wpdb;

        $query = "
            SELECT p.ID,
                   pm_start.meta_value as start_time,
                   pm_end.meta_value as end_time
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_start ON p.ID = pm_start.post_id
            INNER JOIN {$wpdb->postmeta} pm_end ON p.ID = pm_end.post_id
            WHERE p.post_type = 'schedule'
            AND pm_start.meta_key = 'schedule_start_time'
            AND pm_end.meta_key = 'schedule_end_time'
            AND (pm_start.meta_value NOT REGEXP '^[0-9]{2}:[0-9]{2}$'
                 OR pm_end.meta_value NOT REGEXP '^[0-9]{2}:[0-9]{2}$')
        ";

        $invalid_times = $wpdb->get_results($query);
        $fixed_count = 0;

        foreach ($invalid_times as $schedule) {
            // 時間を正規化
            $start_time = date('H:i', strtotime($schedule->start_time));
            $end_time = date('H:i', strtotime($schedule->end_time));

            if ($start_time !== '00:00' && $end_time !== '00:00') {
                update_post_meta($schedule->ID, 'schedule_start_time', $start_time);
                update_post_meta($schedule->ID, 'schedule_end_time', $end_time);
                $fixed_count++;
            }
        }

        return ['fixed_count' => $fixed_count];
    }

    /**
     * 役割なしユーザーの修復
     */
    private static function fixUsersWithoutRole() {
        global $wpdb;

        $query = "
            SELECT u.ID
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'aidunite_role'
            WHERE (um.meta_value IS NULL OR um.meta_value = '')
        ";

        $users_without_role = $wpdb->get_col($query);
        $fixed_count = 0;

        foreach ($users_without_role as $user_id) {
            update_user_meta($user_id, 'aidunite_role', 'player');
            $fixed_count++;
        }

        return ['fixed_count' => $fixed_count];
    }

    /**
     * 無効な通知のクリーンアップ
     */
    private static function cleanupInvalidNotifications() {
        global $wpdb;

        $query = "
            SELECT p.ID
            FROM {$wpdb->posts} p
            WHERE p.post_type = 'notification'
            AND p.post_author NOT IN (
                SELECT ID FROM {$wpdb->users}
            )
        ";

        $invalid_notifications = $wpdb->get_col($query);
        $deleted_count = 0;

        foreach ($invalid_notifications as $notification_id) {
            if (wp_delete_post($notification_id, true)) {
                $deleted_count++;
            }
        }

        return ['deleted_count' => $deleted_count];
    }

    /**
     * 掲示板 match_board_status をゲーム MR から再同期
     */
    private static function repairMatchBoardStatusDrift() {
        if (!function_exists('aidunite_sync_match_board_status_from_game')) {
            return ['fixed_count' => 0, 'skipped' => 'sync_unavailable'];
        }
        $boards = get_posts([
            'post_type' => 'match_board',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);
        $fixed = 0;
        foreach ($boards ?: [] as $board_id) {
            $recruit_id = (int) wp_get_post_parent_id((int) $board_id);
            if ($recruit_id <= 0 || get_post_type($recruit_id) !== 'schedule') {
                continue;
            }
            $before = (string) get_post_meta((int) $board_id, 'match_board_status', true);
            $after  = (string) aidunite_sync_match_board_status_from_game($recruit_id);
            if ($after !== '' && $before !== $after) {
                $fixed++;
            }
        }
        return ['fixed_count' => $fixed];
    }
}

/**
 * データ整合性チェック用REST API
 */
add_action('rest_api_init', function() {
    // データ整合性チェック
    register_rest_route('aidunite/v1', '/data-integrity-check-v2', [
        'methods' => 'GET',
        'callback' => function($request) {
            if (!current_user_can('administrator')) {
                return AidUniteApiResponse::permissionError('管理者のみアクセス可能です');
            }

            $results = AidUniteDataIntegrityManager::checkDataIntegrity();
            return AidUniteApiResponse::success($results);
        },
        'permission_callback' => function() {
            return current_user_can('administrator');
        }
    ]);

    // 自動修復
    register_rest_route('aidunite/v1', '/data-integrity-repair-v2', [
        'methods' => 'POST',
        'callback' => function($request) {
            if (!current_user_can('administrator')) {
                return AidUniteApiResponse::permissionError('管理者のみアクセス可能です');
            }

            $params = $request->get_json_params();
            $results = AidUniteDataIntegrityManager::autoRepair($params);

            return AidUniteApiResponse::success($results, 'データ修復を実行しました');
        },
        'permission_callback' => function() {
            return current_user_can('administrator');
        }
    ]);
});
