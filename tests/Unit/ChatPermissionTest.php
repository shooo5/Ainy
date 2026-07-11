<?php
/**
 * E-1: チャットルーム権限の PHPUnit
 * aidunite_check_chat_permission(room_id, user_id) について、
 * 参加者でないルーム → false、存在しない room_id → false を検証する。
 */

use PHPUnit\Framework\TestCase;

class ChatPermissionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!function_exists('get_current_user_id')) {
            require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
            require_once dirname(__DIR__, 2) . '/tests/bootstrap-simple.php';
        }
        if (!function_exists('aidunite_check_chat_permission')) {
            require_once PROJECT_ROOT . '/functions/messaging/chat-functions.php';
        }
        $GLOBALS['test_current_user_can'] = false;
    }

    /**
     * 存在しない room_id（0）の場合は false
     */
    public function testNonExistentRoomIdZeroReturnsFalse(): void
    {
        $user_id = 1;
        update_user_meta($user_id, 'team_id', 1);
        $this->assertFalse(
            aidunite_check_chat_permission(0, $user_id),
            'room_id 0 の場合は false'
        );
    }

    /**
     * 存在しない room_id（未登録の ID）の場合は false
     */
    public function testNonExistentRoomIdReturnsFalse(): void
    {
        $user_id = 1;
        update_user_meta($user_id, 'team_id', 1);
        $GLOBALS['test_chat_rooms'] = [];
        $this->assertFalse(
            aidunite_check_chat_permission(99999, $user_id),
            '存在しない room_id の場合は false'
        );
    }

    /**
     * user_id 0 の場合は false
     */
    public function testZeroUserIdReturnsFalse(): void
    {
        $GLOBALS['test_chat_rooms'][1] = (object)[
            'id' => 1,
            'room_type' => 'team',
            'team_id' => 1,
        ];
        $this->assertFalse(
            aidunite_check_chat_permission(1, 0),
            'user_id 0 の場合は false'
        );
        unset($GLOBALS['test_chat_rooms'][1]);
    }

    /**
     * team ルーム: 他チームのユーザーは false（参加者でない）
     */
    public function testTeamRoomOtherTeamReturnsFalse(): void
    {
        $user_id = wp_create_user('chat_user_other', 'pass', 'chat_other@example.com');
        update_user_meta($user_id, 'team_id', 2);

        $GLOBALS['test_chat_rooms'][1] = (object)[
            'id' => 1,
            'room_type' => 'team',
            'team_id' => 1,
        ];

        $this->assertFalse(
            aidunite_check_chat_permission(1, $user_id),
            '他チームのユーザーは team ルームにアクセスできない'
        );

        unset($GLOBALS['test_chat_rooms'][1]);
        wp_delete_user($user_id);
    }

    /**
     * team ルーム: 同じチームのユーザーは true（参加者）
     */
    public function testTeamRoomSameTeamReturnsTrue(): void
    {
        $user_id = wp_create_user('chat_user_same', 'pass', 'chat_same@example.com');
        update_user_meta($user_id, 'team_id', 1);

        $GLOBALS['test_chat_rooms'][1] = (object)[
            'id' => 1,
            'room_type' => 'team',
            'team_id' => 1,
        ];

        $this->assertTrue(
            aidunite_check_chat_permission(1, $user_id),
            '同じチームのユーザーは team ルームにアクセスできる'
        );

        unset($GLOBALS['test_chat_rooms'][1]);
        wp_delete_user($user_id);
    }
}
