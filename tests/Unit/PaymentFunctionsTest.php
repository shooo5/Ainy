<?php
/**
 * 支払い機能の単体テスト
 */

use PHPUnit\Framework\TestCase;

class PaymentFunctionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // 基本的なWordPress関数のモック
        if (!function_exists('get_stylesheet_directory')) {
            function get_stylesheet_directory() {
                return dirname(__DIR__, 2);
            }
        }
        
        // 支払い関連関数のモック
        if (!function_exists('calculate_payment_amount')) {
            function calculate_payment_amount($base_amount, $fee_percentage = 0) {
                return (int)($base_amount * (1 + $fee_percentage / 100));
            }
        }
        
        if (!function_exists('check_payment_status')) {
            function check_payment_status($status) {
                return $status === 'completed';
            }
        }
        
        if (!function_exists('validate_payment_method')) {
            function validate_payment_method($method) {
                $valid_methods = ['credit_card', 'bank_transfer', 'paypal'];
                return in_array($method, $valid_methods);
            }
        }
    }
    
    protected function tearDown(): void
    {
        parent::tearDown();
    }
    
    /**
     * 支払い金額計算関数のテスト
     */
    public function testCalculatePaymentAmount()
    {
        // 基本料金のテスト
        $amount = calculate_payment_amount(1000, 0);
        $this->assertEquals(1000, $amount);
        
        // 手数料ありのテスト
        $amount = calculate_payment_amount(1000, 3.6);
        $this->assertEquals(1036, $amount);
    }
    
    /**
     * 支払いステータス確認関数のテスト
     */
    public function testCheckPaymentStatus()
    {
        // 支払い完了のテスト
        $status = check_payment_status('completed');
        $this->assertTrue($status);
        
        // 支払い未完了のテスト
        $status = check_payment_status('pending');
        $this->assertFalse($status);
    }
    
    /**
     * 支払い方法バリデーションのテスト
     */
    public function testValidatePaymentMethod()
    {
        // 有効な支払い方法のテスト
        $valid = validate_payment_method('credit_card');
        $this->assertTrue($valid);
        
        // 無効な支払い方法のテスト
        $valid = validate_payment_method('invalid_method');
        $this->assertFalse($valid);
    }
} 