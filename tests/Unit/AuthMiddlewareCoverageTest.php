<?php
/**
 * Critical カバレッジ: auth-middleware.php 網羅
 * AidUniteAuthResult / AidUniteAuthMiddleware の全メソッド・分岐を実行する。
 */

use PHPUnit\Framework\TestCase;

class AuthMiddlewareCoverageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!function_exists('get_current_user_id')) {
            require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
            require_once dirname(__DIR__, 2) . '/tests/bootstrap-simple.php';
        }
        if (!class_exists('AidUniteAuthMiddleware')) {
            require_once PROJECT_ROOT . '/functions/common/error-handler.php';
            require_once PROJECT_ROOT . '/functions/common/auth-middleware.php';
        }
        $GLOBALS['test_current_user_can'] = false;
        $GLOBALS['test_user_capabilities'] = [];
        $GLOBALS['test_users'] = [];
        $GLOBALS['test_meta_data'] = [];
        $GLOBALS['test_user_id'] = 1;
        wp_set_current_user(0);
    }

    // --- AidUniteAuthResult ---
    public function testAuthResultConstructAndIsValid(): void
    {
        $r = new AidUniteAuthResult(false, false);
        $this->assertFalse($r->is_valid());
        $r = new AidUniteAuthResult(true, false);
        $this->assertTrue($r->is_valid());
        $r = new AidUniteAuthResult(true, true);
        $this->assertTrue($r->is_valid());
        $r->set_error('err', 'code', '/url');
        $this->assertFalse($r->is_valid());
        $this->assertSame('err', $r->error);
        $this->assertSame('code', $r->error_code);
        $this->assertSame('/url', $r->redirect_url);
    }

    public function testAuthResultIsValidWithAuthorized(): void
    {
        $r = new AidUniteAuthResult(true, true);
        $r->set_error('x', 'y');
        $this->assertFalse($r->is_valid());
    }

    // --- require_auth ---
    public function testRequireAuthNotLoggedIn(): void
    {
        wp_set_current_user(0);
        $result = AidUniteAuthMiddleware::require_auth(false);
        $this->assertFalse($result->is_authenticated);
        $this->assertSame('authentication_required', $result->error_code);
    }

    public function testRequireAuthLoggedIn(): void
    {
        wp_set_current_user(1);
        update_user_meta(1, 'aidunite_role', 'team_leader');
        $result = AidUniteAuthMiddleware::require_auth(false);
        $this->assertTrue($result->is_authenticated);
        $this->assertSame(1, $result->user_id);
    }

    // --- require_team_membership ---
    public function testRequireTeamMembershipNotLoggedIn(): void
    {
        wp_set_current_user(0);
        $result = AidUniteAuthMiddleware::require_team_membership(null, false);
        $this->assertFalse($result->is_authenticated);
    }

    public function testRequireTeamMembershipNoTeam(): void
    {
        $uid = 1;
        wp_set_current_user($uid);
        if (!isset($GLOBALS['test_meta_data'][$uid])) {
            $GLOBALS['test_meta_data'][$uid] = [];
        }
        unset($GLOBALS['test_meta_data'][$uid]['team_id']);
        $result = AidUniteAuthMiddleware::require_team_membership(null, false);
        $this->assertTrue($result->is_authenticated);
        $this->assertFalse($result->is_authorized);
        $this->assertSame('team_membership_required', $result->error_code);
    }

    public function testRequireTeamMembershipWithTeam(): void
    {
        wp_set_current_user(1);
        update_user_meta(1, 'team_id', 5);
        update_user_meta(1, 'aidunite_role', 'team_leader');
        $result = AidUniteAuthMiddleware::require_team_membership(null, false);
        $this->assertTrue($result->is_authenticated);
        $this->assertTrue($result->is_authorized);
        $this->assertSame(5, $result->team_id);
    }

    public function testRequireTeamMembershipRequiredTeamMismatch(): void
    {
        wp_set_current_user(1);
        update_user_meta(1, 'team_id', 5);
        update_user_meta(1, 'aidunite_role', 'member');
        $result = AidUniteAuthMiddleware::require_team_membership(10, false);
        $this->assertTrue($result->is_authenticated);
        $this->assertFalse($result->is_authorized);
        $this->assertSame('team_access_denied', $result->error_code);
    }

    public function testRequireTeamMembershipAdministrator(): void
    {
        wp_set_current_user(1);
        $GLOBALS['test_users'][1] = (object)['ID' => 1, 'user_login' => 'admin', 'user_email' => 'a@b.c', 'roles' => ['administrator']];
        $GLOBALS['test_current_user_can'] = true;
        $result = AidUniteAuthMiddleware::require_team_membership(null, false);
        $this->assertTrue($result->is_authenticated);
        $this->assertTrue($result->is_authorized);
    }

    // --- require_role ---
    public function testRequireRoleNotLoggedIn(): void
    {
        wp_set_current_user(0);
        $result = AidUniteAuthMiddleware::require_role('team_leader', false);
        $this->assertFalse($result->is_authenticated);
    }

    public function testRequireRoleWrongRole(): void
    {
        wp_set_current_user(1);
        update_user_meta(1, 'aidunite_role', 'general');
        $result = AidUniteAuthMiddleware::require_role('team_leader', false);
        $this->assertTrue($result->is_authenticated);
        $this->assertFalse($result->is_authorized);
        $this->assertSame('insufficient_permissions', $result->error_code);
    }

    public function testRequireRoleCorrectRole(): void
    {
        wp_set_current_user(1);
        $GLOBALS['test_users'][1] = (object)['ID' => 1, 'user_login' => 'u', 'user_email' => 'u@u.c', 'roles' => ['subscriber']];
        update_user_meta(1, 'aidunite_role', 'team_leader');
        $result = AidUniteAuthMiddleware::require_role('team_leader', false);
        $this->assertTrue($result->is_authenticated);
        $this->assertTrue($result->is_authorized);
    }

    public function testRequireRoleArray(): void
    {
        wp_set_current_user(1);
        $GLOBALS['test_users'][1] = (object)['ID' => 1, 'user_login' => 'u', 'user_email' => 'u@u.c', 'roles' => ['subscriber']];
        update_user_meta(1, 'aidunite_role', 'player');
        $result = AidUniteAuthMiddleware::require_role(['team_leader', 'player'], false);
        $this->assertTrue($result->is_authenticated);
        $this->assertTrue($result->is_authorized);
    }

    public function testRequireRoleAdministrator(): void
    {
        wp_set_current_user(1);
        $GLOBALS['test_users'][1] = (object)['ID' => 1, 'user_login' => 'a', 'user_email' => 'a@b.c', 'roles' => ['administrator']];
        $GLOBALS['test_current_user_can'] = true;
        $result = AidUniteAuthMiddleware::require_role('team_leader', false);
        $this->assertTrue($result->is_authenticated);
        $this->assertTrue($result->is_authorized);
    }

    // --- require_team_leader ---
    public function testRequireTeamLeaderNotLeader(): void
    {
        wp_set_current_user(1);
        update_user_meta(1, 'team_id', 5);
        update_user_meta(1, 'aidunite_role', 'member');
        if (!isset($GLOBALS['test_post_meta'][5])) {
            $GLOBALS['test_post_meta'][5] = [];
        }
        $GLOBALS['test_post_meta'][5]['team_leader_id'] = 999; // 別ユーザーが代表
        $result = AidUniteAuthMiddleware::require_team_leader(5, false);
        $this->assertTrue($result->is_authenticated);
        $this->assertSame('team_leader_required', $result->error_code);
        $this->assertNotEmpty($result->error);
    }

    public function testRequireTeamLeaderIsLeader(): void
    {
        wp_set_current_user(1);
        update_user_meta(1, 'team_id', 5);
        update_user_meta(1, 'aidunite_role', 'team_leader');
        if (!isset($GLOBALS['test_post_meta'][5])) {
            $GLOBALS['test_post_meta'][5] = [];
        }
        $GLOBALS['test_post_meta'][5]['team_leader_id'] = 1;
        $result = AidUniteAuthMiddleware::require_team_leader(5, false);
        $this->assertTrue($result->is_authenticated);
        $this->assertTrue($result->is_authorized);
    }

    // --- require (combined) ---
    public function testRequireOptionsAuthOnly(): void
    {
        wp_set_current_user(1);
        update_user_meta(1, 'aidunite_role', 'general');
        $result = AidUniteAuthMiddleware::require(['redirect' => false]);
        $this->assertTrue($result->is_authenticated);
        // options に team_id/roles/team_leader がない場合は is_authorized は未設定のまま
        $this->assertEmpty($result->error);
    }

    public function testRequireWithTeamId(): void
    {
        wp_set_current_user(1);
        update_user_meta(1, 'team_id', 3);
        update_user_meta(1, 'aidunite_role', 'member');
        $result = AidUniteAuthMiddleware::require(['team_id' => 3, 'redirect' => false]);
        $this->assertTrue($result->is_authenticated);
        $this->assertTrue($result->is_authorized);
    }

    public function testRequireWithRoles(): void
    {
        wp_set_current_user(1);
        $GLOBALS['test_users'][1] = (object)['ID' => 1, 'user_login' => 'u', 'user_email' => 'u@u.c', 'roles' => ['subscriber']];
        update_user_meta(1, 'aidunite_role', 'team_leader');
        $result = AidUniteAuthMiddleware::require(['roles' => 'team_leader', 'redirect' => false]);
        $this->assertTrue($result->is_authenticated);
        $this->assertTrue($result->is_authorized);
    }

    // --- rest_require ---
    public function testRestRequireReturnsWpErrorWhenInvalid(): void
    {
        wp_set_current_user(0);
        $request = new stdClass();
        $result = AidUniteAuthMiddleware::rest_require($request, []);
        $this->assertTrue(is_wp_error($result));
        $this->assertSame(401, $result->get_error_data()['status']);
    }

    public function testRestRequireReturnsResultWhenValid(): void
    {
        wp_set_current_user(1);
        update_user_meta(1, 'aidunite_role', 'team_leader');
        $request = new stdClass();
        $result = AidUniteAuthMiddleware::rest_require($request, []);
        $this->assertFalse(is_wp_error($result));
        $this->assertInstanceOf(AidUniteAuthResult::class, $result);
    }

    public function testRestRequire403ForTeamAccessDenied(): void
    {
        wp_set_current_user(1);
        update_user_meta(1, 'team_id', 1);
        update_user_meta(1, 'aidunite_role', 'member');
        $request = new stdClass();
        $result = AidUniteAuthMiddleware::rest_require($request, ['team_id' => 2]);
        $this->assertTrue(is_wp_error($result));
        $this->assertSame(403, $result->get_error_data()['status']);
    }

    // --- verify_nonce ---
    public function testVerifyNonceMissing(): void
    {
        $_POST = [];
        $_GET = [];
        $result = AidUniteAuthMiddleware::verify_nonce('_wpnonce', 'action', 'POST');
        $this->assertTrue(is_wp_error($result));
        $this->assertSame('nonce_missing', $result->get_error_code());
    }

    public function testVerifyNonceValid(): void
    {
        $_POST['_wpnonce'] = 'abc';
        $result = AidUniteAuthMiddleware::verify_nonce('_wpnonce', 'action', 'POST');
        $this->assertTrue($result === true);
    }

    public function testVerifyNonceGet(): void
    {
        $_GET['_wpnonce'] = 'x';
        $result = AidUniteAuthMiddleware::verify_nonce('_wpnonce', 'a', 'GET');
        $this->assertTrue($result === true);
    }

    // --- sanitize ---
    public function testSanitizeText(): void
    {
        $this->assertSame('hello', AidUniteAuthMiddleware::sanitize('  hello  ', 'text'));
    }

    public function testSanitizeEmail(): void
    {
        $out = AidUniteAuthMiddleware::sanitize('  A@B.CO  ', 'email');
        $this->assertNotEmpty($out);
        $this->assertTrue(filter_var($out, FILTER_VALIDATE_EMAIL) !== false);
    }

    public function testSanitizeInt(): void
    {
        $this->assertSame(42, AidUniteAuthMiddleware::sanitize('42', 'int'));
    }

    public function testSanitizeFloat(): void
    {
        $this->assertSame(3.14, AidUniteAuthMiddleware::sanitize('3.14', 'float'));
    }

    public function testSanitizeTextarea(): void
    {
        $out = AidUniteAuthMiddleware::sanitize("  line1  \n  line2  ", 'textarea');
        $this->assertStringContainsString('line1', $out);
        $this->assertStringContainsString('line2', $out);
    }

    public function testSanitizeArray(): void
    {
        $out = AidUniteAuthMiddleware::sanitize([' a ', ' b '], 'text');
        $this->assertIsArray($out);
        $this->assertSame('a', $out[0]);
        $this->assertSame('b', $out[1]);
    }

    // --- require_chat_permission ---
    public function testRequireChatPermissionNotLoggedIn(): void
    {
        wp_set_current_user(0);
        $result = AidUniteAuthMiddleware::require_chat_permission(1, null, false);
        $this->assertFalse($result->is_authenticated);
    }

    public function testRequireChatPermissionWithAiduniteCheck(): void
    {
        if (!function_exists('aidunite_check_chat_permission')) {
            require_once PROJECT_ROOT . '/functions/messaging/chat-functions.php';
        }
        wp_set_current_user(1);
        update_user_meta(1, 'team_id', 1);
        $GLOBALS['test_chat_rooms'][1] = (object)['id' => 1, 'room_type' => 'team', 'team_id' => 1];
        $result = AidUniteAuthMiddleware::require_chat_permission(1, 1, false);
        $this->assertTrue($result->is_authenticated);
        $this->assertTrue($result->is_authorized);
        unset($GLOBALS['test_chat_rooms'][1]);
    }

    public function testRequireChatPermissionDenied(): void
    {
        if (!function_exists('aidunite_check_chat_permission')) {
            require_once PROJECT_ROOT . '/functions/messaging/chat-functions.php';
        }
        wp_set_current_user(1);
        update_user_meta(1, 'team_id', 2);
        $GLOBALS['test_chat_rooms'][1] = (object)['id' => 1, 'room_type' => 'team', 'team_id' => 1];
        $result = AidUniteAuthMiddleware::require_chat_permission(1, 1, false);
        $this->assertTrue($result->is_authenticated);
        $this->assertFalse($result->is_authorized);
        unset($GLOBALS['test_chat_rooms'][1]);
    }

    // --- require_payment_permission ---
    public function testRequirePaymentPermissionLoggedInWithTeam(): void
    {
        wp_set_current_user(1);
        update_user_meta(1, 'team_id', 1);
        update_user_meta(1, 'aidunite_role', 'team_leader');
        $result = AidUniteAuthMiddleware::require_payment_permission(null, false, false);
        $this->assertTrue($result->is_authenticated);
        $this->assertTrue($result->is_authorized);
    }

    public function testRequirePaymentPermissionAdministrator(): void
    {
        wp_set_current_user(1);
        $GLOBALS['test_users'][1] = (object)['ID' => 1, 'user_login' => 'a', 'user_email' => 'a@b.c', 'roles' => ['administrator']];
        $GLOBALS['test_current_user_can'] = true;
        $result = AidUniteAuthMiddleware::require_payment_permission(null, false, false);
        $this->assertTrue($result->is_authenticated);
        $this->assertTrue($result->is_authorized);
    }

    // --- require_admin ---
    public function testRequireAdminNotAdmin(): void
    {
        wp_set_current_user(1);
        $GLOBALS['test_users'][1] = (object)['ID' => 1, 'user_login' => 'u', 'user_email' => 'u@u.c', 'roles' => ['subscriber']];
        $GLOBALS['test_current_user_can'] = false;
        $result = AidUniteAuthMiddleware::require_admin(false);
        $this->assertTrue($result->is_authenticated);
        $this->assertFalse($result->is_authorized);
    }

    public function testRequireAdminIsAdmin(): void
    {
        wp_set_current_user(1);
        $GLOBALS['test_users'][1] = (object)['ID' => 1, 'user_login' => 'a', 'user_email' => 'a@b.c', 'roles' => ['administrator']];
        $GLOBALS['test_current_user_can'] = true;
        $result = AidUniteAuthMiddleware::require_admin(false);
        $this->assertTrue($result->is_authenticated);
        $this->assertTrue($result->is_authorized);
    }

    // --- aidunite_get_user_role ---
    public function testAiduniteGetUserRoleNullUserId(): void
    {
        wp_set_current_user(0);
        $this->assertSame('public', aidunite_get_user_role(null));
    }

    public function testAiduniteGetUserRoleZero(): void
    {
        $this->assertSame('public', aidunite_get_user_role(0));
    }

    public function testAiduniteGetUserRoleFromMeta(): void
    {
        wp_set_current_user(1);
        $GLOBALS['test_users'][1] = (object)['ID' => 1, 'user_login' => 'u', 'user_email' => 'u@u.c', 'roles' => ['subscriber']];
        update_user_meta(1, 'aidunite_role', 'player');
        $this->assertSame('player', aidunite_get_user_role(1));
    }

    public function testAiduniteGetUserRoleAdministrator(): void
    {
        $GLOBALS['test_users'][1] = (object)['ID' => 1, 'user_login' => 'a', 'user_email' => 'a@b.c', 'roles' => ['administrator']];
        $this->assertSame('administrator', aidunite_get_user_role(1));
    }

    public function testAiduniteGetUserRoleGeneralFallback(): void
    {
        wp_set_current_user(1);
        $GLOBALS['test_users'][1] = (object)['ID' => 1, 'user_login' => 'u', 'user_email' => 'u@u.c', 'roles' => ['subscriber']];
        update_user_meta(1, 'aidunite_role', 'unknown_role');
        $this->assertSame('general', aidunite_get_user_role(1));
    }
}
