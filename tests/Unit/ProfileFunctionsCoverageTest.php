<?php
/**
 * Critical カバレッジ: profile-functions.php 網羅
 */

use PHPUnit\Framework\TestCase;

class ProfileFunctionsCoverageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!function_exists('get_current_user_id')) {
            require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
            require_once dirname(__DIR__, 2) . '/tests/bootstrap-simple.php';
        }
        if (!function_exists('get_user_avatar')) {
            require_once PROJECT_ROOT . '/functions/user/profile-functions.php';
        }
    }

    public function testGetUserAvatarWithUserId(): void
    {
        wp_set_current_user(1);
        $GLOBALS['test_users'][1] = (object)['ID' => 1, 'user_login' => 'u', 'user_email' => 'u@u.c', 'roles' => ['subscriber']];
        update_user_meta(1, 'user_avatar_emoji', '😀');
        $html = get_user_avatar(1, 80);
        $this->assertStringContainsString('😀', $html);
        $this->assertStringContainsString('80', $html);
    }

    public function testGetUserAvatarNoUserIdUsesCurrent(): void
    {
        wp_set_current_user(2);
        $GLOBALS['test_users'][2] = (object)['ID' => 2, 'user_login' => 'u2', 'user_email' => 'u2@u.c', 'roles' => ['subscriber']];
        update_user_meta(2, 'user_avatar_emoji', '👤');
        $html = get_user_avatar(null, 60);
        $this->assertStringContainsString('60', $html);
    }

    public function testGetUserAvatarZeroReturnsDefault(): void
    {
        wp_set_current_user(0);
        $html = get_user_avatar(0, 100);
        $this->assertStringContainsString('👤', $html);
    }

    public function testGetDefaultAvatar(): void
    {
        $html = get_default_avatar(120);
        $this->assertStringContainsString('120', $html);
        $this->assertStringContainsString('👤', $html);
    }

    public function testGetUserDisplayName(): void
    {
        $GLOBALS['test_users'][1] = (object)['ID' => 1, 'user_login' => 'u', 'user_email' => 'u@u.c', 'display_name' => '表示名', 'roles' => ['subscriber']];
        $this->assertSame('表示名', get_user_display_name(1));
    }

    public function testGetUserDisplayNameEmptyUsesLogin(): void
    {
        $GLOBALS['test_users'][1] = (object)['ID' => 1, 'user_login' => 'login1', 'user_email' => 'u@u.c', 'display_name' => '', 'roles' => ['subscriber']];
        $this->assertSame('login1', get_user_display_name(1));
    }

    public function testGetUserDisplayNameNoUser(): void
    {
        wp_set_current_user(0);
        $this->assertSame('ゲスト', get_user_display_name(null));
    }

    public function testGetUserFullName(): void
    {
        $GLOBALS['test_users'][1] = (object)['ID' => 1, 'user_login' => 'u', 'user_email' => 'u@u.c', 'first_name' => '名', 'last_name' => '姓', 'display_name' => 'd', 'roles' => ['subscriber']];
        $this->assertSame('姓 名', get_user_full_name(1));
    }

    public function testGetUserFullNameEmptyUsesDisplay(): void
    {
        $GLOBALS['test_users'][1] = (object)['ID' => 1, 'user_login' => 'u', 'user_email' => 'u@u.c', 'first_name' => '', 'last_name' => '', 'display_name' => 'Display', 'roles' => ['subscriber']];
        $this->assertSame('Display', get_user_full_name(1));
    }

    public function testGetUserProfileData(): void
    {
        wp_set_current_user(1);
        $GLOBALS['test_users'][1] = (object)['ID' => 1, 'user_login' => 'u', 'user_email' => 'u@u.c', 'first_name' => 'F', 'last_name' => 'L', 'display_name' => 'D', 'user_registered' => '2020-01-01', 'roles' => ['subscriber']];
        update_user_meta(1, 'user_phone', '090-0000-0000');
        $data = get_user_profile_data(1);
        $this->assertIsArray($data);
        $this->assertSame(1, $data['user_id']);
        $this->assertSame('u', $data['user_login']);
        $this->assertSame('090-0000-0000', $data['phone']);
    }

    public function testGetUserProfileDataNoUser(): void
    {
        $this->assertSame([], get_user_profile_data(99999));
    }

    public function testUpdateUserProfileRejectsOtherUserId(): void
    {
        wp_set_current_user(1);
        $this->assertFalse(update_user_profile(2, ['first_name' => 'X']));
    }

    public function testUpdateUserProfileSuccess(): void
    {
        wp_set_current_user(1);
        $GLOBALS['test_users'][1] = (object)['ID' => 1, 'user_login' => 'u', 'user_email' => 'u@u.c', 'roles' => ['subscriber']];
        $result = update_user_profile(1, ['first_name' => 'New', 'last_name' => 'Name']);
        $this->assertTrue($result);
    }

    public function testUpdateUserProfileInvalidUserId(): void
    {
        $this->assertFalse(update_user_profile(0, ['first_name' => 'X']));
    }

    public function testUpdateUserProfileNotArray(): void
    {
        $this->assertFalse(update_user_profile(1, null));
    }

    public function testCheckPasswordStrengthWeak(): void
    {
        $this->assertSame('weak', check_password_strength('ab'));
    }

    public function testCheckPasswordStrengthMedium(): void
    {
        $this->assertSame('medium', check_password_strength('abcdefgh'));
    }

    public function testCheckPasswordStrengthStrong(): void
    {
        $this->assertSame('strong', check_password_strength('Abcdefg1!'));
    }

    public function testIsEmailAvailableNoUser(): void
    {
        $this->assertTrue(is_email_available('new@example.com'));
    }

    public function testIsEmailAvailableExcludeUserId(): void
    {
        $GLOBALS['test_users'][1] = (object)['ID' => 1, 'user_login' => 'u', 'user_email' => 'same@example.com', 'roles' => ['subscriber']];
        $this->assertTrue(is_email_available('same@example.com', 1));
    }

    public function testIsEmailAvailableTaken(): void
    {
        $GLOBALS['test_users'][1] = (object)['ID' => 1, 'user_login' => 'u', 'user_email' => 'taken@example.com', 'roles' => ['subscriber']];
        $this->assertFalse(is_email_available('taken@example.com', 2));
    }

    public function testCalculateUserAge(): void
    {
        $birth = (new DateTime())->modify('-25 years')->format('Y-m-d');
        $this->assertSame(25, calculate_user_age($birth));
    }

    public function testCalculateUserAgeEmpty(): void
    {
        $this->assertNull(calculate_user_age(''));
    }

    public function testGetGenderDisplayName(): void
    {
        $this->assertSame('男性', get_gender_display_name('male'));
        $this->assertSame('女性', get_gender_display_name('female'));
        $this->assertSame('その他', get_gender_display_name('other'));
        $this->assertSame('回答しない', get_gender_display_name('prefer_not_to_say'));
        $this->assertSame('', get_gender_display_name('unknown'));
    }

    public function testGetProfileEditUrl(): void
    {
        $url = get_profile_edit_url();
        $this->assertStringContainsString('profile-edit', $url);
    }

    public function testGetProfileEditLink(): void
    {
        $html = get_profile_edit_link('編集', 'btn');
        $this->assertStringContainsString('編集', $html);
        $this->assertStringContainsString('profile-edit', $html);
    }

    public function testCalculateProfileCompletion(): void
    {
        wp_set_current_user(1);
        $GLOBALS['test_users'][1] = (object)['ID' => 1, 'user_login' => 'u', 'user_email' => 'u@u.c', 'first_name' => 'F', 'last_name' => 'L', 'display_name' => 'D', 'user_registered' => '2020-01-01', 'roles' => ['subscriber']];
        $pct = calculate_profile_completion(1);
        $this->assertGreaterThanOrEqual(0, $pct);
        $this->assertLessThanOrEqual(100, $pct);
    }

    public function testCalculateProfileCompletionZeroUser(): void
    {
        wp_set_current_user(0);
        $this->assertSame(0, calculate_profile_completion(null));
    }

    public function testGetProfileCompletionHtml(): void
    {
        wp_set_current_user(1);
        $GLOBALS['test_users'][1] = (object)['ID' => 1, 'user_login' => 'u', 'user_email' => 'u@u.c', 'first_name' => 'F', 'last_name' => 'L', 'display_name' => 'D', 'user_registered' => '2020-01-01', 'roles' => ['subscriber']];
        $html = get_profile_completion_html(1);
        $this->assertStringContainsString('profile-completion', $html);
        $this->assertStringContainsString('progress', $html);
    }

    public function testGetProfileCompletionHtmlSuccessClass(): void
    {
        wp_set_current_user(1);
        $GLOBALS['test_users'][1] = (object)['ID' => 1, 'user_login' => 'u', 'user_email' => 'u@u.c', 'first_name' => 'F', 'last_name' => 'L', 'display_name' => 'D', 'user_registered' => '2020-01-01', 'roles' => ['subscriber']];
        update_user_meta(1, 'user_phone', '090');
        update_user_meta(1, 'user_birth_date', '1990-01-01');
        update_user_meta(1, 'user_gender', 'male');
        update_user_meta(1, 'user_address', 'addr');
        update_user_meta(1, 'user_bio', 'bio');
        $html = get_profile_completion_html(1);
        $this->assertStringContainsString('bg-', $html);
    }
}
