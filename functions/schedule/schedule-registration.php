<?php
/**
 * 統一スケジュール登録システム
 * AidUnite Theme - Schedule Registration
 */

if (!defined('ABSPATH')) {
    exit;
}

// エラーハンドラーを読み込み
require_once get_stylesheet_directory() . '/functions/common/error-handler.php';
require_once get_stylesheet_directory() . '/functions/common/auth-middleware.php';

class AidUniteScheduleRegistration {

    /**
     * 統一されたスケジュール登録処理
     */
    public static function registerSchedule($request) {
        try {
            // 1. 認証チェック
            $auth_result = self::checkAuthentication();
            if (!$auth_result['valid']) {
                return AidUniteApiResponse::authError($auth_result['message']);
            }

            // 2. 権限チェック
            $permission_result = self::checkPermission();
            if (!$permission_result['valid']) {
                return AidUniteApiResponse::permissionError($permission_result['message']);
            }

            // 3. データ取得・正規化
            $data = self::normalizeScheduleData($request);

            // 4. バリデーション
            $validation_result = self::validateScheduleData($data);
            if (!$validation_result['valid']) {
                return AidUniteApiResponse::validationError($validation_result['errors']);
            }

            // 5. トランザクション処理
            global $wpdb;
            $wpdb->query('START TRANSACTION');

            try {
                // スケジュール作成
                $schedule_id = self::createSchedule($data);

                // マッチボード作成（必要に応じて）
                if ($data['intent'] === 'recruit' && function_exists('aidunite_schedule_finalize_new_recruit')) {
                    $team_id_hook = (int) get_post_meta((int) $schedule_id, 'team_id', true);
                    $board_id = aidunite_schedule_finalize_new_recruit(
                        (int) $schedule_id,
                        (int) $data['user_id'],
                        $team_id_hook
                    );
                }

                $wpdb->query('COMMIT');

                AidUniteErrorHandler::info('スケジュール登録成功', [
                    'schedule_id' => $schedule_id,
                    'user_id' => get_current_user_id(),
                    'intent' => $data['intent']
                ]);

                $team_id_for_hook = (int) get_post_meta((int) $schedule_id, 'team_id', true);
                if (function_exists('aidunite_fire_schedule_registered_hooks')) {
                    aidunite_fire_schedule_registered_hooks((int) $schedule_id, array_merge($data, [
                        'team_id' => $team_id_for_hook,
                    ]));
                } else {
                    do_action('aidunite_schedule_registered', (int) $schedule_id, array_merge($data, [
                        'team_id' => $team_id_for_hook,
                    ]));
                }

                return AidUniteApiResponse::success([
                    'schedule_id' => $schedule_id,
                    'board_id' => $board_id ?? null
                ], 'スケジュールを登録しました');

            } catch (Exception $e) {
                $wpdb->query('ROLLBACK');
                throw $e;
            }

        } catch (Exception $e) {
            AidUniteErrorHandler::handleException($e, [
                'action' => 'register_schedule',
                'user_id' => get_current_user_id()
            ]);

            return AidUniteApiResponse::error(
                'スケジュールの登録に失敗しました',
                'schedule_registration_failed',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * POST /delete-schedule-v2 — 正ルート。処理本体は legacy の aidunite_delete_schedule（依存ガード込み）
     */
    public static function deleteSchedule($request) {
        if (!function_exists('aidunite_delete_schedule')) {
            return AidUniteApiResponse::error(
                'スケジュール削除APIが利用できません',
                'not_available',
                500
            );
        }
        return aidunite_delete_schedule($request);
    }

    /**
     * POST /update-schedule-v2 — 正ルート。処理本体は legacy の aidunite_update_schedule（aidunite_can_update_schedule 込み）
     */
    public static function updateSchedule($request) {
        if (!function_exists('aidunite_update_schedule')) {
            return AidUniteApiResponse::error(
                'スケジュール更新APIが利用できません',
                'not_available',
                500
            );
        }
        return aidunite_update_schedule($request);
    }

    /**
     * 認証チェック（統一ミドルウェア使用）
     */
    private static function checkAuthentication() {
        $auth_result = AidUniteAuthMiddleware::require_auth();
        if (!$auth_result->is_valid()) {
            return ['valid' => false, 'message' => $auth_result->error ?: 'ログインが必要です'];
        }

        return ['valid' => true];
    }

    /**
     * 権限チェック（統一ミドルウェア使用）
     */
    private static function checkPermission() {
        $auth_result = AidUniteAuthMiddleware::require_role(['team_leader', 'administrator']);
        if (!$auth_result->is_valid()) {
            return [
                'valid' => false,
                'message' => $auth_result->error ?: 'チーム代表者または管理者のみがスケジュールを登録できます'
            ];
        }

        return ['valid' => true];
    }

    /**
     * データ正規化
     */
    private static function normalizeScheduleData($request) {
        $params = $request->get_json_params();

        // 日付の正規化
        $date = '';
        if (isset($params['start_date']) && !empty($params['start_date'])) {
            $date = sanitize_text_field($params['start_date']);
        } elseif (isset($params['date']) && !empty($params['date'])) {
            $date = sanitize_text_field($params['date']);
        } else {
            $date = date('Y-m-d');
        }

        // 時間の正規化
        $start_time = '';
        $end_time = '';

        if (isset($params['start_hour']) && isset($params['start_minute'])) {
            $start_time = sprintf('%02d:%02d',
                intval($params['start_hour']),
                intval($params['start_minute'])
            );
        } elseif (isset($params['start_time'])) {
            $start_time = sanitize_text_field($params['start_time']);
        }

        if (isset($params['end_hour']) && isset($params['end_minute'])) {
            $end_time = sprintf('%02d:%02d',
                intval($params['end_hour']),
                intval($params['end_minute'])
            );
        } elseif (isset($params['end_time'])) {
            $end_time = sanitize_text_field($params['end_time']);
        }

        if (!function_exists('aidunite_schedule_normalize_form_input')) {
            require_once get_template_directory() . '/functions/schedule/schedule-persist.php';
        }

        $raw = [
            'date' => $date,
            'start_date' => $date,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'schedule_type' => sanitize_text_field($params['schedule_type'] ?? $params['type'] ?? ''),
            'intent' => sanitize_text_field($params['intent'] ?? 'confirmed'),
            'certainty' => sanitize_text_field($params['certainty'] ?? ''),
            'venue_condition' => sanitize_text_field($params['venue_condition'] ?? ''),
            'venue_name' => sanitize_text_field($params['venue_name'] ?? ''),
            'gender_condition' => sanitize_text_field($params['gender_condition'] ?? ''),
            'male_teams' => (int) ($params['male_teams'] ?? 1),
            'female_teams' => (int) ($params['female_teams'] ?? 1),
            'male_slots' => isset($params['male_slots']) ? (int) $params['male_slots'] : null,
            'female_slots' => isset($params['female_slots']) ? (int) $params['female_slots'] : null,
            'note' => sanitize_textarea_field($params['note'] ?? ''),
            'user_id' => get_current_user_id(),
        ];
        if ($raw['male_slots'] === null) {
            unset($raw['male_slots']);
        }
        if ($raw['female_slots'] === null) {
            unset($raw['female_slots']);
        }

        $normalized = aidunite_schedule_normalize_form_input($raw);

        return [
            'date' => $normalized['date'],
            'start_time' => $normalized['start_time'],
            'end_time' => $normalized['end_time'],
            'type' => $normalized['schedule_type'],
            'intent' => $normalized['intent'],
            'venue_condition' => $normalized['venue_condition'],
            'venue_name' => $normalized['venue_name'],
            'gender_condition' => $normalized['gender_condition'],
            'male_teams' => $normalized['male_slots'],
            'female_teams' => $normalized['female_slots'],
            'note' => $normalized['schedule_quick_memo'],
            'user_id' => $normalized['user_id'],
        ];
    }

    /**
     * バリデーション
     */
    private static function validateScheduleData($data) {
        if (function_exists('aidunite_schedule_validate_registration_data')) {
            return aidunite_schedule_validate_registration_data($data);
        }

        return [
            'valid' => false,
            'errors' => ['バリデーションが利用できません'],
        ];
    }

    /**
     * スケジュール作成
     */
    private static function createSchedule($data) {
        if (!function_exists('aidunite_schedule_create_published_post')) {
            require_once get_template_directory() . '/functions/schedule/schedule-persist.php';
        }

        $user_id = (int) $data['user_id'];

        $team_id = function_exists('aidunite_resolve_user_team_id_for_schedule_ops')
            ? (int) aidunite_resolve_user_team_id_for_schedule_ops($user_id)
            : (int) get_user_meta($user_id, 'team_id', true);
        if (!$team_id) {
            $user_teams = get_posts([
                'post_type' => 'team',
                'meta_query' => [
                    [
                        'key' => 'team_members',
                        'value' => '"' . $user_id . '"',
                        'compare' => 'LIKE',
                    ],
                ],
                'posts_per_page' => 1,
            ]);
            if (!empty($user_teams)) {
                $team_id = (int) $user_teams[0]->ID;
            }
        }

        if (($data['intent'] ?? '') === 'recruit') {
            $gender_check = aidunite_schedule_validate_recruit_gender_for_save(0, (string) ($data['gender_condition'] ?? ''));
            if (is_wp_error($gender_check)) {
                throw new Exception($gender_check->get_error_message());
            }
        }

        $persist = aidunite_schedule_normalize_form_input([
            'date' => $data['date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'schedule_type' => $data['type'],
            'intent' => $data['intent'],
            'venue_condition' => $data['venue_condition'],
            'venue_name' => $data['venue_name'],
            'gender_condition' => $data['gender_condition'],
            'male_slots' => (int) ($data['male_teams'] ?? 0),
            'female_slots' => (int) ($data['female_teams'] ?? 0),
            'team_id' => $team_id,
            'user_id' => $user_id,
            'schedule_quick_memo' => $data['note'] ?? '',
        ]);

        $post_id = aidunite_schedule_create_published_post($persist);
        if (is_wp_error($post_id)) {
            throw new Exception($post_id->get_error_message());
        }

        return (int) $post_id;
    }

}

/**
 * REST APIエンドポイント登録
 */
add_action('rest_api_init', function() {
    require_once get_template_directory() . '/functions/common/auth-middleware.php';

    // スケジュール登録
    register_rest_route('aidunite/v1', '/register-schedule-v2', [
        'methods' => 'POST',
        'callback' => ['AidUniteScheduleRegistration', 'registerSchedule'],
        'permission_callback' => function($request) {
            $result = AidUniteAuthMiddleware::rest_require($request, []);
            return !is_wp_error($result);
        }
    ]);

    // スケジュール更新
    register_rest_route('aidunite/v1', '/update-schedule-v2', [
        'methods' => 'POST',
        'callback' => ['AidUniteScheduleRegistration', 'updateSchedule'],
        'permission_callback' => function($request) {
            $result = AidUniteAuthMiddleware::rest_require($request, []);
            return !is_wp_error($result);
        }
    ]);

    // スケジュール削除
    register_rest_route('aidunite/v1', '/delete-schedule-v2', [
        'methods' => 'POST',
        'callback' => ['AidUniteScheduleRegistration', 'deleteSchedule'],
        'permission_callback' => function($request) {
            $result = AidUniteAuthMiddleware::rest_require($request, []);
            return !is_wp_error($result);
        }
    ]);
});
