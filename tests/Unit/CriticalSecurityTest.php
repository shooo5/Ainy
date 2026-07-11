<?php
/**
 * Critical 領域のセキュリティ・権限の単体テスト
 * MVP「事故らない」のための正常系・異常系を検証する。
 */

use PHPUnit\Framework\TestCase;

class CriticalSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!function_exists('get_current_user_id')) {
            require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
            require_once dirname(__DIR__, 2) . '/tests/bootstrap-simple.php';
        }
    }

    /**
     * 未ログイン時（current_user_id = 0）は is_user_logged_in() が false を返すこと
     */
    public function testUnauthenticatedUserIsNotLoggedIn(): void
    {
        wp_set_current_user(0);
        $this->assertFalse(is_user_logged_in(), '未ログイン時は is_user_logged_in が false');
        $this->assertEquals(0, get_current_user_id());
    }

    /**
     * ログイン時は is_user_logged_in() が true を返すこと
     */
    public function testAuthenticatedUserIsLoggedIn(): void
    {
        wp_set_current_user(1);
        $this->assertTrue(is_user_logged_in());
        $this->assertEquals(1, get_current_user_id());
    }

    /**
     * aidunite_update_team は team_id が空の場合に false を返すこと（異常系）
     */
    public function testUpdateTeamRejectsEmptyTeamId(): void
    {
        wp_set_current_user(1);
        $result = aidunite_update_team(['team_name' => '無効']);
        $this->assertFalse($result, 'team_id が無い場合は false');
    }

    /**
     * aidunite_update_team は存在しない team_id の場合に false を返すこと（異常系）
     */
    public function testUpdateTeamRejectsNonexistentTeamId(): void
    {
        wp_set_current_user(1);
        $result = aidunite_update_team(['team_id' => 999999, 'team_name' => '無効']);
        $this->assertFalse($result, '存在しない team_id の場合は false');
    }

    /**
     * 正常系: チーム作成後に自分で編集すると true
     */
    public function testUpdateTeamSuccessWhenOwner(): void
    {
        $user_id = wp_create_user('owner_sec', 'pass123', 'owner_sec@example.com');
        $team_id = aidunite_register_team($user_id, ['team_name' => '自チーム', 'sport_type' => 'soccer']);
        $this->assertIsInt($team_id);
        if (function_exists('aidunite_approve_team')) {
            aidunite_approve_team($team_id);
        }

        wp_set_current_user($user_id);
        $result = aidunite_update_team(['team_id' => $team_id, 'team_name' => '更新後']);
        $this->assertTrue($result, 'オーナーは編集できること');

        wp_delete_post($team_id, true);
        wp_delete_user($user_id);
    }

    /**
     * 異常系: 他ユーザーで編集試行すると false
     */
    public function testUpdateTeamRejectsOtherUser(): void
    {
        $owner_id = wp_create_user('owner_perm', 'pass123', 'owner_perm@example.com');
        $team_id = aidunite_register_team($owner_id, ['team_name' => 'チームA', 'sport_type' => 'soccer']);
        if (function_exists('aidunite_approve_team')) {
            aidunite_approve_team($team_id);
        }
        $other_id = wp_create_user('other_perm', 'pass123', 'other_perm@example.com');

        $result = aidunite_update_team(
            ['team_id' => $team_id, 'team_name' => '乗っ取り'],
            $other_id
        );
        $this->assertFalse($result, '他ユーザーでは編集が拒否されること');

        wp_delete_post($team_id, true);
        wp_delete_user($owner_id);
        wp_delete_user($other_id);
    }

    /**
     * Critical#6: 他ユーザーのプロフィール更新は拒否されること
     */
    public function testUpdateProfileRejectsOtherUserId(): void
    {
        if (!function_exists('update_user_profile')) {
            require_once PROJECT_ROOT . '/functions/user/profile-functions.php';
        }
        $user_a = wp_create_user('profile_a', 'pass123', 'profile_a@example.com');
        $user_b = wp_create_user('profile_b', 'pass123', 'profile_b@example.com');
        wp_set_current_user($user_a);
        $result = update_user_profile($user_b, ['first_name' => '乗っ取り']);
        $this->assertFalse($result, '他 user_id ではプロフィール更新が拒否されること');
        wp_delete_user($user_a);
        wp_delete_user($user_b);
    }

    /**
     * Critical#6: 本人のプロフィール更新は成功すること（正常系）
     */
    public function testUpdateProfileSuccessWhenSelf(): void
    {
        if (!function_exists('update_user_profile')) {
            require_once PROJECT_ROOT . '/functions/user/profile-functions.php';
        }
        $user_id = wp_create_user('profile_self', 'pass123', 'profile_self@example.com');
        wp_set_current_user($user_id);
        $result = update_user_profile($user_id, ['first_name' => '正しい']);
        $this->assertTrue($result, '本人はプロフィール更新できること');
        wp_delete_user($user_id);
    }

    /**
     * D-1: 他 user_id のプロフィール読み取りは拒否されること（空配列が返ること）
     */
    public function testGetUserProfileDataRejectsOtherUserId(): void
    {
        if (!function_exists('get_user_profile_data')) {
            require_once PROJECT_ROOT . '/functions/user/profile-functions.php';
        }
        $user_a = wp_create_user('profile_read_a', 'pass123', 'profile_read_a@example.com');
        $user_b = wp_create_user('profile_read_b', 'pass123', 'profile_read_b@example.com');
        wp_set_current_user($user_a);
        $data = get_user_profile_data($user_b);
        $this->assertIsArray($data);
        $this->assertEmpty($data, '他 user_id を指定した場合は空配列が返ること');
        wp_delete_user($user_a);
        wp_delete_user($user_b);
    }

    /**
     * D-1 正常系: 本人の user_id で get_user_profile_data を呼ぶとデータが返ること
     */
    public function testGetUserProfileDataSuccessWhenSelf(): void
    {
        if (!function_exists('get_user_profile_data')) {
            require_once PROJECT_ROOT . '/functions/user/profile-functions.php';
        }
        $user_id = wp_create_user('profile_read_self', 'pass123', 'profile_read_self@example.com');
        wp_set_current_user($user_id);
        $data = get_user_profile_data($user_id);
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('user_id', $data);
        $this->assertSame((int) $user_id, (int) $data['user_id']);
        wp_delete_user($user_id);
    }
}
