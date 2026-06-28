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

    /** @var bool */
    private static $register_postprocess_shutdown_registered = false;

    /**
     * 統一されたスケジュール登録処理
     */
    public static function registerSchedule($request) {
        $data = [];
        $success_message = 'スケジュールを登録しました';

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

            $schedule_id = (int) self::createSchedule($data);
            if ($schedule_id < 1) {
                throw new Exception('スケジュールIDの取得に失敗しました');
            }

            $success_message = ($data['intent'] ?? '') === 'recruit'
                ? '試合募集を公開しました'
                : 'スケジュールを登録しました';

            // board・フック等は JSON 返却後の shutdown で実行（500/タイムアウト防止）
            self::queueRegisterSchedulePostProcess($schedule_id, $data);

            return self::buildRegisterScheduleSuccessResponse($schedule_id, $data, $success_message, null);

        } catch (Throwable $e) {
            $saved_id = (int) ($GLOBALS['aidunite_schedule_last_persist_post_id'] ?? 0);
            if ($saved_id > 0) {
                if (!is_array($data)) {
                    $data = [];
                }
                if (($data['intent'] ?? '') === '') {
                    $data['intent'] = (string) get_post_meta($saved_id, 'intent', true);
                }
                if (($data['date'] ?? '') === '') {
                    $data['date'] = (string) get_post_meta($saved_id, 'schedule_date', true);
                }
                $success_message = ($data['intent'] ?? '') === 'recruit'
                    ? '試合募集を公開しました'
                    : 'スケジュールを登録しました';
                self::queueRegisterSchedulePostProcess($saved_id, $data);
                if (class_exists('AidUniteErrorHandler')) {
                    AidUniteErrorHandler::warning('schedule_register_postprocess_deferred_after_error', [
                        'schedule_id' => $saved_id,
                        'error' => $e->getMessage(),
                    ]);
                }
                return self::buildRegisterScheduleSuccessResponse($saved_id, $data, $success_message, null);
            }

            AidUniteErrorHandler::handleException($e, [
                'action' => 'register_schedule',
                'user_id' => get_current_user_id(),
            ]);

            $detail = $e->getMessage();
            $message = 'スケジュールの登録に失敗しました';
            if ($detail !== '') {
                $message .= '（' . $detail . '）';
            }

            return AidUniteApiResponse::error(
                $message,
                'schedule_registration_failed',
                500,
                $detail
            );
        }
    }

    /**
     * POST /delete-schedule-v2 — 正ルート（schedule-rest-handlers.php の aidunite_delete_schedule）
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
     * POST /update-schedule-v2 — 正ルート（schedule-rest-handlers.php の aidunite_update_schedule）
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
            require_once get_stylesheet_directory() . '/functions/schedule/schedule-persist.php';
        }

        $ui_kind = sanitize_key((string) ($params['ui_schedule_kind'] ?? ''));
        $tentative_base = sanitize_key((string) ($params['tentative_base_kind'] ?? ''));
        $kind_meta = ($ui_kind !== '' && function_exists('aidunite_schedule_map_ui_kind_to_meta'))
            ? aidunite_schedule_map_ui_kind_to_meta($ui_kind)
            : [];

        if ($ui_kind === 'tentative' && function_exists('aidunite_schedule_resolve_tentative_schedule_type')) {
            $intent_in = 'tentative';
            $type_in = aidunite_schedule_resolve_tentative_schedule_type(
                $tentative_base,
                (string) ($params['schedule_type'] ?? $params['type'] ?? '')
            );
            $certainty_in = 'tentative';
        } else {
            $intent_in = sanitize_text_field($params['intent'] ?? ($kind_meta['intent'] ?? 'confirmed'));
            $type_in = sanitize_text_field($params['schedule_type'] ?? $params['type'] ?? ($kind_meta['schedule_type'] ?? ''));
            $certainty_in = sanitize_text_field($params['certainty'] ?? ($kind_meta['certainty'] ?? ''));
        }

        $schedule_visibility = sanitize_text_field((string) ($params['schedule_visibility'] ?? 'team'));
        $is_personal = ($intent_in === 'recruit') ? '0' : (($schedule_visibility === 'personal') ? '1' : '0');

        $raw = [
            'date' => $date,
            'start_date' => $date,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'schedule_type' => $type_in,
            'intent' => $intent_in,
            'certainty' => $certainty_in,
            'venue_condition' => sanitize_text_field($params['venue_condition'] ?? ''),
            'venue_name' => sanitize_text_field($params['venue_name'] ?? ''),
            'gender_condition' => sanitize_text_field($params['gender_condition'] ?? ''),
            'male_teams' => (int) ($params['male_teams'] ?? 1),
            'female_teams' => (int) ($params['female_teams'] ?? 1),
            'male_slots' => isset($params['male_slots']) ? (int) $params['male_slots'] : null,
            'female_slots' => isset($params['female_slots']) ? (int) $params['female_slots'] : null,
            'note' => sanitize_textarea_field($params['note'] ?? $params['memo'] ?? $params['schedule_quick_memo'] ?? ''),
            'attendance_required' => !empty($params['attendance_required']) ? '1' : '0',
            'is_personal' => $is_personal,
            'user_id' => get_current_user_id(),
            'ui_schedule_kind' => $ui_kind,
            'all_day' => array_key_exists('all_day', $params)
                ? (!empty($params['all_day']) && (string) $params['all_day'] !== '0' ? '1' : '0')
                : '0',
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
            'schedule_type' => $normalized['schedule_type'],
            'intent' => $normalized['intent'],
            'certainty' => $normalized['certainty'] ?? '',
            'ui_schedule_kind' => $ui_kind,
            'venue_condition' => $normalized['venue_condition'],
            'venue_name' => $normalized['venue_name'],
            'gender_condition' => $normalized['gender_condition'],
            'male_teams' => $normalized['male_slots'],
            'female_teams' => $normalized['female_slots'],
            'note' => $normalized['schedule_quick_memo'],
            'attendance_required' => $normalized['attendance_required'] ?? '0',
            'is_personal' => $normalized['is_personal'] ?? '0',
            'user_id' => $normalized['user_id'],
            'ui_schedule_kind' => $ui_kind,
            'all_day' => $normalized['all_day'] ?? '0',
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
            require_once get_stylesheet_directory() . '/functions/schedule/schedule-persist.php';
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
            'certainty' => $data['certainty'] ?? '',
            'venue_condition' => $data['venue_condition'],
            'venue_name' => $data['venue_name'],
            'gender_condition' => $data['gender_condition'],
            'male_slots' => (int) ($data['male_teams'] ?? 0),
            'female_slots' => (int) ($data['female_teams'] ?? 0),
            'team_id' => $team_id,
            'user_id' => $user_id,
            'schedule_quick_memo' => $data['note'] ?? '',
            'attendance_required' => $data['attendance_required'] ?? '0',
            'is_personal' => $data['is_personal'] ?? '0',
            'ui_schedule_kind' => $data['ui_schedule_kind'] ?? '',
            'all_day' => $data['all_day'] ?? '0',
            'defer_registered_hooks' => true,
        ]);

        $post_id = aidunite_schedule_create_published_post($persist);
        if (is_wp_error($post_id)) {
            throw new Exception($post_id->get_error_message());
        }

        return (int) $post_id;
    }

    /**
     * 重い後処理を shutdown で実行（REST レスポンス送出後）
     *
     * @param int   $schedule_id
     * @param array $data
     */
    private static function queueRegisterSchedulePostProcess($schedule_id, array $data) {
        $schedule_id = (int) $schedule_id;
        if ($schedule_id < 1) {
            return;
        }

        $GLOBALS['aidunite_pending_register_postprocess'] = [
            'schedule_id' => $schedule_id,
            'data' => $data,
        ];

        if (!self::$register_postprocess_shutdown_registered) {
            self::$register_postprocess_shutdown_registered = true;
            add_action('shutdown', [__CLASS__, 'flushPendingRegisterPostProcess'], 0);
        }
    }

    /**
     * shutdown: クライアントへ先に応答を送ってから board・フックを実行
     */
    public static function flushPendingRegisterPostProcess() {
        if (empty($GLOBALS['aidunite_pending_register_postprocess'])) {
            return;
        }

        $pending = $GLOBALS['aidunite_pending_register_postprocess'];
        unset($GLOBALS['aidunite_pending_register_postprocess']);

        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }

        if (!is_array($pending) || (int) ($pending['schedule_id'] ?? 0) < 1) {
            return;
        }

        self::runRegisterSchedulePostProcess((int) $pending['schedule_id'], (array) ($pending['data'] ?? []));
    }

    /**
     * 保存成功後の後処理（board・フック・ログ）
     *
     * @param int   $schedule_id
     * @param array $data
     * @return int|null board_id
     */
    private static function runRegisterSchedulePostProcess($schedule_id, array $data) {
        try {
            return self::runRegisterSchedulePostProcessInner($schedule_id, $data);
        } catch (Throwable $e) {
            if (class_exists('AidUniteErrorHandler')) {
                AidUniteErrorHandler::warning('schedule_register_postprocess_failed', [
                    'schedule_id' => (int) $schedule_id,
                    'error' => $e->getMessage(),
                ]);
            }
            return null;
        }
    }

    /**
     * @param int   $schedule_id
     * @param array $data
     * @return int|null board_id
     */
    private static function runRegisterSchedulePostProcessInner($schedule_id, array $data) {
        $schedule_id = (int) $schedule_id;
        $board_id = null;

        if ($schedule_id > 0 && ($data['intent'] ?? '') === 'recruit' && function_exists('aidunite_schedule_finalize_new_recruit')) {
            $team_id_hook = (int) get_post_meta($schedule_id, 'team_id', true);
            try {
                $board_id = aidunite_schedule_finalize_new_recruit(
                    $schedule_id,
                    (int) ($data['user_id'] ?? get_current_user_id()),
                    $team_id_hook
                );
            } catch (Throwable $finalize_error) {
                if (class_exists('AidUniteErrorHandler')) {
                    AidUniteErrorHandler::warning('schedule_finalize_recruit_failed', [
                        'schedule_id' => $schedule_id,
                        'error' => $finalize_error->getMessage(),
                    ]);
                }
            }
        }

        try {
            if (class_exists('AidUniteErrorHandler')) {
                AidUniteErrorHandler::info('スケジュール登録成功', [
                    'schedule_id' => $schedule_id,
                    'user_id' => get_current_user_id(),
                    'intent' => $data['intent'] ?? '',
                ]);
            }
        } catch (Throwable $log_error) {
            // ignore
        }

        $team_id_for_hook = (int) get_post_meta($schedule_id, 'team_id', true);
        try {
            if (function_exists('aidunite_fire_schedule_registered_hooks')) {
                aidunite_fire_schedule_registered_hooks($schedule_id, array_merge($data, [
                    'team_id' => $team_id_for_hook,
                ]));
            } else {
                do_action('aidunite_schedule_registered', $schedule_id, array_merge($data, [
                    'team_id' => $team_id_for_hook,
                ]));
            }
        } catch (Throwable $hook_error) {
            if (class_exists('AidUniteErrorHandler')) {
                AidUniteErrorHandler::warning('schedule_registered_hook_failed', [
                    'schedule_id' => $schedule_id,
                    'error' => $hook_error->getMessage(),
                ]);
            }
        }

        return $board_id;
    }

    /**
     * @param int         $schedule_id
     * @param array       $data
     * @param string      $success_message
     * @param int|null    $board_id
     * @return array<string, mixed>
     */
    private static function buildRegisterScheduleSuccessResponse($schedule_id, array $data, $success_message, $board_id = null) {
        $schedule_id = (int) $schedule_id;
        try {
            $payload = function_exists('aidunite_schedule_get_rest_register_minimal_payload')
                ? aidunite_schedule_get_rest_register_minimal_payload($schedule_id, $data)
                : [
                    'id' => $schedule_id,
                    'schedule_id' => $schedule_id,
                    'date' => (string) ($data['date'] ?? ''),
                    'ui_schedule_kind' => ($data['intent'] ?? '') === 'recruit' ? 'recruit' : 'practice',
                ];
        } catch (Throwable $payload_error) {
            if (class_exists('AidUniteErrorHandler')) {
                AidUniteErrorHandler::warning('schedule_rest_payload_failed', [
                    'schedule_id' => $schedule_id,
                    'error' => $payload_error->getMessage(),
                ]);
            }
            $payload = [
                'id' => $schedule_id,
                'schedule_id' => $schedule_id,
                'date' => (string) ($data['date'] ?? ''),
                'ui_schedule_kind' => ($data['intent'] ?? '') === 'recruit' ? 'recruit' : 'practice',
            ];
        }

        return AidUniteApiResponse::success([
            'schedule_id' => $schedule_id,
            'board_id' => $board_id,
            'schedule' => $payload,
        ], $success_message);
    }

    /**
     * 保存成功後の後処理。失敗しても schedule_id があれば成功レスポンスを返す。
     *
     * @param int    $schedule_id
     * @param array  $data
     * @param string $success_message
     * @return array<string, mixed>
     */
    private static function completeRegisterScheduleSuccess($schedule_id, array $data, $success_message) {
        $board_id = self::runRegisterSchedulePostProcess((int) $schedule_id, $data);
        return self::buildRegisterScheduleSuccessResponse((int) $schedule_id, $data, $success_message, $board_id);
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
        'callback' => static function ($request) {
            $result = AidUniteScheduleRegistration::registerSchedule($request);
            if (is_wp_error($result)) {
                return $result;
            }
            if (is_array($result)) {
                return new WP_REST_Response($result, 200);
            }
            return rest_ensure_response($result);
        },
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
