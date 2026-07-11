<?php
/**
 * Critical#2: 主要 REST API の未認証時契約テスト
 * 認証なし → 403/401 または固定エラー形式であることを検証する。
 */

use PHPUnit\Framework\TestCase;

class RestApiContractTest extends TestCase
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
    }

    /**
     * 未認証時、rest_require は WP_Error を返すこと（契約）
     */
    public function testUnauthenticatedRestRequireReturnsWpError(): void
    {
        wp_set_current_user(0);
        $request = $this->createMockRequest();
        $result = AidUniteAuthMiddleware::rest_require($request, []);
        $this->assertTrue(is_wp_error($result), '未認証時は WP_Error を返すこと');
        $this->assertSame('authentication_required', $result->get_error_code());
        $data = $result->get_error_data('authentication_required');
        $this->assertIsArray($data);
        $this->assertArrayHasKey('status', $data);
        $this->assertContains((int) $data['status'], [401, 403], 'ステータスは 401 または 403');
    }

    private function createMockRequest()
    {
        $request = new stdClass();
        return $request;
    }
}
