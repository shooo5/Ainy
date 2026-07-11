<?php
/**
 * 月謝 Connect ブロック文言（P1-05）
 */

use PHPUnit\Framework\TestCase;

class PaymentTuitionBlockMessageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!function_exists('aidunite_payment_read_tuition_block_message')) {
            require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
            require_once dirname(__DIR__, 2) . '/tests/bootstrap-simple.php';
            require_once PROJECT_ROOT . '/functions/payment/payment-persist-read.php';
        }
    }

    public function testParentConnectIncompleteMessageMentionsTeamLeader(): void
    {
        $message = aidunite_payment_read_tuition_block_message('connect_incomplete', 'parent');
        $this->assertStringContainsString('チーム代表者', $message);
        $this->assertStringNotContainsString('Stripe Connect', $message);
    }

    public function testLeaderConnectIncompleteMessageMentionsStripeConnect(): void
    {
        $message = aidunite_payment_read_tuition_block_message('connect_incomplete', 'leader');
        $this->assertStringContainsString('Stripe Connect', $message);
    }

    public function testUnknownReasonUsesAudienceFallback(): void
    {
        $parent = aidunite_payment_read_tuition_block_message('unknown_reason', 'parent');
        $leader = aidunite_payment_read_tuition_block_message('unknown_reason', 'leader');
        $this->assertNotSame($parent, $leader);
    }
}
