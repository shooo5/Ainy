<?php
/**
 * match_request / REST テスト用フィクスチャ（canonical status: pending / established / …）
 *
 * テストケース本体はユーザーが実装。本クラスは test_posts / test_post_meta のセットアップのみ。
 */

if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__DIR__, 2));
}

class MatchRequestTestFixture
{
    /**
     * @param array<string, mixed> $params
     */
    public static function createRestRequest(array $params): WP_REST_Request
    {
        $request = new WP_REST_Request();
        $request->set_json_params($params);
        return $request;
    }

    /**
     * @param int                  $post_id
     * @param array<string, mixed> $meta from_team_id, to_schedule_id, status 等
     * @param int|null             $post_author
     */
    public static function setupMatchRequestPost($post_id, array $meta, $post_author = null): void
    {
        if ($post_author === null) {
            $post_author = 1;
        }

        $status_raw = array_key_exists('status', $meta)
            ? (string) $meta['status']
            : 'pending';

        if (function_exists('aidunite_normalize_match_request_status')) {
            $status_meta = aidunite_normalize_match_request_status($status_raw, '');
        } else {
            $status_meta = strtolower(trim($status_raw));
        }

        $meta['status'] = $status_meta !== '' ? $status_meta : 'pending';

        $GLOBALS['test_posts'][$post_id] = (object) [
            'ID' => $post_id,
            'post_type' => 'match_request',
            'post_status' => 'publish',
            'post_title' => 'マッチ申請 ' . $post_id,
            'post_author' => $post_author,
            'post_parent' => 0,
        ];
        $GLOBALS['test_post_meta'][$post_id] = array_merge([
            'from_team_id' => null,
            'to_schedule_id' => null,
            'other_team_id' => null,
            'my_schedule_id' => null,
            'status' => 'pending',
        ], $meta);
    }

    /**
     * @param int                  $post_id
     * @param int                  $post_author
     * @param array<string, mixed> $meta
     */
    public static function setupSchedulePost($post_id, $post_author, array $meta = []): void
    {
        $GLOBALS['test_posts'][$post_id] = (object) [
            'ID' => $post_id,
            'post_type' => 'schedule',
            'post_status' => 'publish',
            'post_title' => 'スケジュール ' . $post_id,
            'post_author' => $post_author,
            'post_parent' => 0,
        ];
        $GLOBALS['test_post_meta'][$post_id] = array_merge([
            'schedule_date' => date('Y-m-d'),
            'schedule_start_time' => '09:00',
            'schedule_end_time' => '11:00',
            'team_id' => null,
        ], $meta);
    }

    /**
     * @param int    $room_id
     * @param string $room_type
     * @param array<string, mixed> $attrs
     */
    public static function setupChatRoom($room_id, $room_type, array $attrs = []): void
    {
        if (!isset($GLOBALS['test_chat_rooms'])) {
            $GLOBALS['test_chat_rooms'] = [];
        }
        $GLOBALS['test_chat_rooms'][$room_id] = (object) array_merge(
            ['id' => $room_id, 'room_type' => $room_type],
            $attrs
        );
    }

    public static function tearDownMatchRequestPost($post_id): void
    {
        unset($GLOBALS['test_posts'][$post_id], $GLOBALS['test_post_meta'][$post_id]);
    }

    public static function tearDownSchedulePost($post_id): void
    {
        unset($GLOBALS['test_posts'][$post_id], $GLOBALS['test_post_meta'][$post_id]);
    }

    public static function tearDownChatRoom($room_id): void
    {
        unset($GLOBALS['test_chat_rooms'][$room_id]);
    }
}
