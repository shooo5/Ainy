<?php
/**
 * 権限マトリクス自動テスト（P1-02）
 */

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/_support/PermissionMatrixCatalog.php';
require_once dirname(__DIR__) . '/_support/PermissionMatrixEvaluator.php';

class PermissionMatrixTest extends TestCase
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
    if (!function_exists('aidunite_e2e_api_enabled')) {
      require_once PROJECT_ROOT . '/functions/e2e/test-data-api.php';
    }
    permission_matrix_reset_globals();
    $GLOBALS['test_user_id'] = 1;
  }

  /**
   * @dataProvider matrixRowProvider
   */
  public function testPermissionMatrixRow(array $row): void
  {
    $expected = (string) ($row['expected'] ?? '');
    $actual = PermissionMatrixEvaluator::evaluate($row);

    $this->assertSame(
      $expected,
      $actual,
      ($row['id'] ?? '') . ': expected ' . $expected . ', got ' . $actual
        . (isset($row['notes']) ? ' — ' . $row['notes'] : '')
    );
  }

  /**
   * @return array<string, array{array}>
   */
  public function matrixRowProvider(): array
  {
    $cases = [];
    foreach (PermissionMatrixCatalog::rows() as $row) {
      $cases[$row['id']] = [$row];
    }
    return $cases;
  }
}
