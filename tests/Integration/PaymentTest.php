<?php
/**
 * 決済・会費機能の統合テスト
 */

use PHPUnit\Framework\TestCase;

class PaymentTest extends TestCase
{
    protected $test_user;
    protected $test_payment_id;
    protected $test_team_id;
    protected $test_payment_ids = [];
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // WordPressのテスト環境を初期化
        if (!function_exists('wp_insert_user')) {
            require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
            require_once dirname(__DIR__, 2) . '/tests/bootstrap-simple.php';
        }

        // テスト用ユーザーとチームを作成
        $user_id = wp_create_user('test_leader', 'password123', 'leader@example.com');
        // wp_create_user()がintを返す場合とオブジェクトを返す場合があるため、対応
        if (is_int($user_id)) {
            $this->test_user = get_user_by('id', $user_id);
        } else {
            $this->test_user = $user_id;
        }
        update_user_meta($this->test_user->ID, 'registration_status', 'accepted');
        update_user_meta($this->test_user->ID, 'test_user', 'true');

        // テスト用チームを作成
        $team_data = [
            'team_name' => 'テストチーム',
            'sport_type' => 'soccer',
            'region' => 'tokyo'
        ];
        $this->test_team_id = aidunite_register_team($this->test_user->ID, $team_data);
        if (function_exists('aidunite_approve_team')) {
            aidunite_approve_team($this->test_team_id);
        }
        
        // ログインユーザーとして設定
        wp_set_current_user($this->test_user->ID);

        // Stripeのテスト環境設定
        if (class_exists('Stripe\Stripe')) {
            \Stripe\Stripe::setApiKey('sk_test_51H1234567890abcdefghijklmnopqrstuvwxyz');
        }
    }
    
    protected function tearDown(): void
    {
        // テストで作成したデータをクリーンアップ
        foreach ($this->test_payment_ids as $payment_id) {
            wp_delete_post($payment_id, true);
        }
        
        if ($this->test_payment_id) {
            wp_delete_post($this->test_payment_id, true);
        }
        
        if ($this->test_team_id) {
            wp_delete_post($this->test_team_id, true);
        }
        
        if ($this->test_user) {
            wp_delete_user($this->test_user->ID ?? $this->test_user);
        }
        
        parent::tearDown();
    }
    
    /**
     * Stripe決済処理 - 正常パターン
     */
    public function testStripePaymentSuccess()
    {
        $payment_data = [
            'amount' => 1000,
            'currency' => 'jpy',
            'description' => 'Test payment',
            'payment_method' => 'stripe',
            'user_id' => $this->test_user->ID
        ];
        
        // Stripe決済処理（テストモード）
        $result = process_stripe_payment($payment_data);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('payment_intent_id', $result);
        
        // 決済記録の確認
        $this->test_payment_id = $result['payment_id'];
        $payment_record = get_payment_record($this->test_payment_id);
        $this->assertEquals('completed', $payment_record['status']);
        $this->assertEquals(1000, $payment_record['amount']);
    }
    
    /**
     * Stripe決済処理 - 異常パターン（無効な金額）
     */
    public function testStripePaymentInvalidAmount()
    {
        $payment_data = [
            'amount' => -100, // 負の金額
            'currency' => 'jpy',
            'description' => 'Test payment',
            'payment_method' => 'stripe',
            'user_id' => $this->test_user->ID
        ];
        
        $result = process_stripe_payment($payment_data);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }
    
    /**
     * Stripe決済処理 - 異常パターン（無効な通貨）
     */
    public function testStripePaymentInvalidCurrency()
    {
        $payment_data = [
            'amount' => 1000,
            'currency' => 'invalid_currency',
            'description' => 'Test payment',
            'payment_method' => 'stripe',
            'user_id' => $this->test_user->ID
        ];
        
        $result = process_stripe_payment($payment_data);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    /**
     * Critical#5: 他チームの支払い操作は拒否されること（bootstrap の process_stripe_payment に team_id チェックを追加済み）
     */
    public function testStripePaymentOtherTeamRejected()
    {
        wp_set_current_user($this->test_user->ID);
        $current_team_id = (int) get_user_meta($this->test_user->ID, 'team_id', true);
        $this->assertGreaterThan(0, $current_team_id, '前提: 現ユーザーにチームが紐づいていること');
        $other_team_id = $current_team_id + 99999;

        $payment_data = [
            'amount' => 1000,
            'currency' => 'jpy',
            'description' => 'Test',
            'payment_method' => 'stripe',
            'user_id' => $this->test_user->ID,
            'team_id' => $other_team_id
        ];
        $result = process_stripe_payment($payment_data);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertFalse($result['success'], '他チームの team_id を指定した場合は success が false であること');
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('他チーム', $result['error']);
    }

    /**
     * G-2: 同一条件で 2 回支払い作成したときの挙動を検証する（現状は両方 success、別 payment_id が返ること）
     */
    public function testDoublePaymentSameConditions(): void
    {
        wp_set_current_user($this->test_user->ID);
        $payment_data = [
            'amount' => 1000,
            'currency' => 'jpy',
            'description' => 'G-2 duplicate test',
            'payment_method' => 'stripe',
            'user_id' => $this->test_user->ID
        ];
        $first = process_stripe_payment($payment_data);
        $this->assertIsArray($first);
        $this->assertTrue($first['success'] ?? false);
        $this->assertArrayHasKey('payment_id', $first);
        $first_id = $first['payment_id'];

        $second = process_stripe_payment($payment_data);
        $this->assertIsArray($second);
        $this->assertTrue($second['success'] ?? false);
        $this->assertArrayHasKey('payment_id', $second);
        $second_id = $second['payment_id'];

        $this->assertNotSame($first_id, $second_id, '同一条件で 2 回呼ぶと別の payment_id が返ること（二重実行の挙動を検証）');
        $this->test_payment_ids[] = $first_id;
        $this->test_payment_ids[] = $second_id;
    }
    
    /**
     * 会費計算 - 基本料金
     */
    public function testMembershipFeeCalculation()
    {
        $base_amount = 5000;
        $fee_rate = 0.036; // 3.6%
        
        $total_amount = calculate_membership_fee($base_amount, $fee_rate);
        $expected_amount = $base_amount + ($base_amount * $fee_rate);
        
        $this->assertEquals($expected_amount, $total_amount);
    }
    
    /**
     * 会費計算 - 手数料なし
     */
    public function testMembershipFeeCalculationNoFee()
    {
        $base_amount = 5000;
        $fee_rate = 0;
        
        $total_amount = calculate_membership_fee($base_amount, $fee_rate);
        
        $this->assertEquals($base_amount, $total_amount);
    }
    
    /**
     * 会費計算 - 無効な値
     */
    public function testMembershipFeeCalculationInvalidValues()
    {
        $base_amount = -1000; // 負の金額
        $fee_rate = 0.036;
        
        $total_amount = calculate_membership_fee($base_amount, $fee_rate);
        
        $this->assertEquals(0, $total_amount);
    }
    
    /**
     * 支払いステータス更新 - 正常パターン
     */
    public function testPaymentStatusUpdate()
    {
        // 支払い記録を作成
        $payment_data = [
            'user_id' => $this->test_user->ID,
            'amount' => 1000,
            'status' => 'pending',
            'payment_method' => 'stripe'
        ];
        
        $this->test_payment_id = create_payment_record($payment_data);
        
        // ステータスを完了に更新
        $result = update_payment_status($this->test_payment_id, 'completed');
        $this->assertTrue($result);
        
        // 更新確認
        $payment_record = get_payment_record($this->test_payment_id);
        $this->assertEquals('completed', $payment_record['status']);
    }
    
    /**
     * 支払いステータス更新 - 異常パターン（存在しない支払い）
     */
    public function testPaymentStatusUpdateNonexistentPayment()
    {
        $result = update_payment_status(99999, 'completed');
        $this->assertFalse($result);
    }
    
    /**
     * 支払い履歴取得 - 正常パターン
     */
    public function testGetPaymentHistory()
    {
        // 複数の支払い記録を作成
        $payment1_data = [
            'user_id' => $this->test_user->ID,
            'amount' => 1000,
            'status' => 'completed',
            'payment_method' => 'stripe'
        ];
        
        $payment2_data = [
            'user_id' => $this->test_user->ID,
            'amount' => 2000,
            'status' => 'pending',
            'payment_method' => 'stripe'
        ];
        
        $payment1_id = create_payment_record($payment1_data);
        $payment2_id = create_payment_record($payment2_data);
        
        // 支払い履歴取得
        $payment_history = get_payment_history($this->test_user->ID);
        
        $this->assertIsArray($payment_history);
        $this->assertCount(2, $payment_history);
        
        // 日付順でソートされているか確認
        $this->assertGreaterThanOrEqual($payment_history[1]['created_at'], $payment_history[0]['created_at']);
        
        // クリーンアップ
        wp_delete_post($payment1_id, true);
        wp_delete_post($payment2_id, true);
    }
    
    /**
     * 支払い履歴取得 - 空の場合
     */
    public function testGetPaymentHistoryEmpty()
    {
        $new_user = create_test_user('subscriber');
        $payment_history = get_payment_history($new_user->ID);
        
        $this->assertIsArray($payment_history);
        $this->assertEmpty($payment_history);
        
        // クリーンアップ
        wp_delete_user($new_user->ID);
    }
    
    /**
     * 支払い統計取得 - 正常パターン
     */
    public function testGetPaymentStatistics()
    {
        // 複数の支払い記録を作成
        $payment1_data = [
            'user_id' => $this->test_user->ID,
            'amount' => 1000,
            'status' => 'completed',
            'payment_method' => 'stripe'
        ];
        
        $payment2_data = [
            'user_id' => $this->test_user->ID,
            'amount' => 2000,
            'status' => 'completed',
            'payment_method' => 'stripe'
        ];
        
        $payment3_data = [
            'user_id' => $this->test_user->ID,
            'amount' => 1500,
            'status' => 'pending',
            'payment_method' => 'stripe'
        ];
        
        $payment1_id = create_payment_record($payment1_data);
        $payment2_id = create_payment_record($payment2_data);
        $payment3_id = create_payment_record($payment3_data);
        
        // 支払い統計取得
        $statistics = get_payment_statistics($this->test_user->ID);
        
        $this->assertIsArray($statistics);
        $this->assertArrayHasKey('total_amount', $statistics);
        $this->assertArrayHasKey('completed_amount', $statistics);
        $this->assertArrayHasKey('pending_amount', $statistics);
        $this->assertArrayHasKey('total_count', $statistics);
        $this->assertArrayHasKey('completed_count', $statistics);
        $this->assertArrayHasKey('pending_count', $statistics);
        
        $this->assertEquals(4500, $statistics['total_amount']);
        $this->assertEquals(3000, $statistics['completed_amount']);
        $this->assertEquals(1500, $statistics['pending_amount']);
        $this->assertEquals(3, $statistics['total_count']);
        $this->assertEquals(2, $statistics['completed_count']);
        $this->assertEquals(1, $statistics['pending_count']);
        
        // クリーンアップ
        wp_delete_post($payment1_id, true);
        wp_delete_post($payment2_id, true);
        wp_delete_post($payment3_id, true);
    }
    
    /**
     * 支払い方法バリデーション - 有効な方法
     */
    public function testValidatePaymentMethod()
    {
        $valid_methods = ['stripe', 'bank_transfer', 'convenience_store'];
        
        foreach ($valid_methods as $method) {
            $is_valid = validate_payment_method($method);
            $this->assertTrue($is_valid, "Payment method '$method' should be valid");
        }
    }
    
    /**
     * 支払い方法バリデーション - 無効な方法
     */
    public function testValidatePaymentMethodInvalid()
    {
        $invalid_methods = ['invalid_method', '', null];
        
        foreach ($invalid_methods as $method) {
            $is_valid = validate_payment_method($method);
            $this->assertFalse($is_valid, "Payment method '$method' should be invalid");
        }
    }
    
    /**
     * 支払い通知送信 - 正常パターン
     */
    public function testSendPaymentNotification()
    {
        $payment_data = [
            'user_id' => $this->test_user->ID,
            'amount' => 1000,
            'status' => 'completed',
            'payment_method' => 'stripe'
        ];
        
        $this->test_payment_id = create_payment_record($payment_data);
        
        // 支払い完了通知送信
        $result = send_payment_notification($this->test_payment_id, 'completed');
        $this->assertTrue($result);
        
        // 通知記録の確認
        $notifications = get_user_notifications($this->test_user->ID);
        $this->assertNotEmpty($notifications);
        
        $payment_notification = array_filter($notifications, function($notification) {
            return strpos($notification['title'], '支払い完了') !== false;
        });
        
        $this->assertNotEmpty($payment_notification);
    }



    /**
     * Stripe決済インテント作成 - 正常パターン
     */
    public function testStripePaymentIntentCreation()
    {
        $amount = 15000; // 15,000円
        $currency = 'jpy';
        $description = 'テストチーム会費';

        // Stripe決済インテント作成
        $payment_intent = create_stripe_payment_intent($amount, $currency, $description);
        
        $this->assertNotNull($payment_intent);
        $this->assertEquals($amount, $payment_intent->amount);
        $this->assertEquals($currency, $payment_intent->currency);
        $this->assertEquals($description, $payment_intent->description);
        $this->assertEquals('requires_payment_method', $payment_intent->status);
    }

    /**
     * Stripe決済インテント作成 - 異常パターン（無効な金額）
     */
    public function testStripePaymentIntentCreationInvalidAmount()
    {
        $amount = -1000; // 負の金額
        $currency = 'jpy';
        $description = 'テストチーム会費';

        // Stripe決済インテント作成
        $payment_intent = create_stripe_payment_intent($amount, $currency, $description);
        
        $this->assertNull($payment_intent);
    }

    /**
     * 決済記録作成 - 正常パターン
     */
    public function testPaymentRecordCreation()
    {
        $payment_data = [
            'user_id' => $this->test_user->ID ?? $this->test_user,
            'team_id' => $this->test_team_id,
            'amount' => 15000,
            'currency' => 'jpy',
            'payment_method' => 'stripe',
            'status' => 'pending',
            'description' => 'テストチーム会費'
        ];

        $payment_id = create_payment_record($payment_data);
        
        $this->assertNotFalse($payment_id);
        $this->assertIsInt($payment_id);
        
        // 決済記録の確認
        $payment_post = get_post($payment_id);
        $this->assertEquals('payment', $payment_post->post_type);
        $this->assertEquals('publish', $payment_post->post_status);
        $this->assertEquals($this->test_user->ID ?? $this->test_user, $payment_post->post_author);
        
        // メタデータの確認
        $this->assertEquals($this->test_team_id, get_post_meta($payment_id, 'team_id', true));
        $this->assertEquals(15000, get_post_meta($payment_id, 'amount', true));
        $this->assertEquals('jpy', get_post_meta($payment_id, 'currency', true));
        $this->assertEquals('stripe', get_post_meta($payment_id, 'payment_method', true));
        $this->assertEquals('pending', get_post_meta($payment_id, 'payment_status', true));
        
        $this->test_payment_ids[] = $payment_id;
    }

    /**
     * 決済記録作成 - 異常パターン（必須項目不足）
     */
    public function testPaymentRecordCreationMissingFields()
    {
        // team_idが設定されていないユーザーを作成
        $user_without_team = wp_create_user('test_user_no_team', 'password123', 'noteam@example.com');
        if (is_int($user_without_team)) {
            $user_without_team_obj = get_user_by('id', $user_without_team);
        } else {
            $user_without_team_obj = $user_without_team;
        }
        
        // team_idを明示的に削除（存在しないことを確認）
        delete_user_meta($user_without_team_obj->ID, 'team_id');
        
        $payment_data = [
            'user_id' => $user_without_team_obj->ID,
            'amount' => 15000
            // team_idが不足
        ];

        $payment_id = create_payment_record($payment_data);
        
        $this->assertFalse($payment_id);
        
        // クリーンアップ
        wp_delete_user($user_without_team_obj->ID);
    }



    /**
     * 決済履歴取得 - 正常パターン
     */
    public function testPaymentHistoryRetrieval()
    {
        // 複数の決済記録を作成
        $payment_data1 = [
            'user_id' => $this->test_user->ID ?? $this->test_user,
            'team_id' => $this->test_team_id,
            'amount' => 15000,
            'currency' => 'jpy',
            'payment_method' => 'stripe',
            'status' => 'succeeded',
            'description' => '1月会費'
        ];

        $payment_data2 = [
            'user_id' => $this->test_user->ID ?? $this->test_user,
            'team_id' => $this->test_team_id,
            'amount' => 15000,
            'currency' => 'jpy',
            'payment_method' => 'stripe',
            'status' => 'pending',
            'description' => '2月会費'
        ];

        $payment1_id = create_payment_record($payment_data1);
        $payment2_id = create_payment_record($payment_data2);
        
        $this->test_payment_ids[] = $payment1_id;
        $this->test_payment_ids[] = $payment2_id;

        // 決済履歴取得
        $payment_history = get_payment_history($this->test_user->ID ?? $this->test_user);
        
        $this->assertIsArray($payment_history);
        $this->assertGreaterThanOrEqual(2, count($payment_history));
        
        // 決済内容の確認
        $found_payment1 = false;
        $found_payment2 = false;
        
        foreach ($payment_history as $payment) {
            if ($payment['id'] == $payment1_id) {
                $this->assertEquals(15000, $payment['amount']);
                $this->assertEquals('succeeded', $payment['status']);
                $this->assertEquals('1月会費', $payment['description']);
                $found_payment1 = true;
            }
            if ($payment['id'] == $payment2_id) {
                $this->assertEquals(15000, $payment['amount']);
                $this->assertEquals('pending', $payment['status']);
                $this->assertEquals('2月会費', $payment['description']);
                $found_payment2 = true;
            }
        }
        
        $this->assertTrue($found_payment1);
        $this->assertTrue($found_payment2);
    }

    /**
     * 決済統計情報取得 - 正常パターン
     */
    public function testPaymentStatisticsRetrieval()
    {
        // 異なるステータスの決済記録を作成
        $payment_statuses = ['succeeded', 'pending', 'failed', 'succeeded', 'pending'];
        $amounts = [15000, 15000, 15000, 20000, 20000];
        
        for ($i = 0; $i < count($payment_statuses); $i++) {
            $payment_data = [
                'user_id' => $this->test_user->ID ?? $this->test_user,
                'team_id' => $this->test_team_id,
                'amount' => $amounts[$i],
                'currency' => 'jpy',
                'payment_method' => 'stripe',
                'status' => $payment_statuses[$i],
                'description' => "テスト決済{$i}"
            ];
            
            $payment_id = create_payment_record($payment_data);
            $this->test_payment_ids[] = $payment_id;
        }

        // 統計情報取得
        $stats = get_payment_statistics($this->test_user->ID ?? $this->test_user);
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_count', $stats);
        $this->assertArrayHasKey('total_amount', $stats);
        $this->assertArrayHasKey('succeeded_count', $stats);
        $this->assertArrayHasKey('succeeded_amount', $stats);
        $this->assertArrayHasKey('pending_count', $stats);
        $this->assertArrayHasKey('pending_amount', $stats);
        $this->assertArrayHasKey('failed_count', $stats);
        $this->assertArrayHasKey('failed_amount', $stats);
        
        // このテストで作成した決済を含むことを確認（他のテストの決済が混入する可能性があるため）
        $this->assertGreaterThanOrEqual(5, $stats['total_count']);
        $this->assertGreaterThanOrEqual(85000, $stats['total_amount']); // 15000*3 + 20000*2
        $this->assertGreaterThanOrEqual(2, $stats['succeeded_count']);
        $this->assertGreaterThanOrEqual(35000, $stats['succeeded_amount']); // 15000 + 20000
        $this->assertGreaterThanOrEqual(2, $stats['pending_count']);
        $this->assertGreaterThanOrEqual(35000, $stats['pending_amount']); // 15000 + 20000
        $this->assertGreaterThanOrEqual(1, $stats['failed_count']);
        $this->assertGreaterThanOrEqual(15000, $stats['failed_amount']);
    }

    /**
     * 決済通知送信 - 正常パターン
     */
    public function testPaymentNotificationSending()
    {
        // 決済記録作成
        $payment_data = [
            'user_id' => $this->test_user->ID ?? $this->test_user,
            'team_id' => $this->test_team_id,
            'amount' => 15000,
            'currency' => 'jpy',
            'payment_method' => 'stripe',
            'status' => 'succeeded',
            'description' => 'テストチーム会費'
        ];

        $payment_id = create_payment_record($payment_data);
        $this->test_payment_ids[] = $payment_id;

        // 決済通知送信
        $result = send_payment_notification($payment_id, 'success');
        
        $this->assertTrue($result);
    }

    /**
     * 決済通知送信 - 異常パターン（存在しない決済）
     */
    public function testPaymentNotificationSendingNonexistentPayment()
    {
        $result = send_payment_notification(99999, 'success');
        
        $this->assertFalse($result);
    }

    /**
     * 決済キャンセル - 正常パターン
     */
    public function testPaymentCancellation()
    {
        // 決済記録作成
        $payment_data = [
            'user_id' => $this->test_user->ID ?? $this->test_user,
            'team_id' => $this->test_team_id,
            'amount' => 15000,
            'currency' => 'jpy',
            'payment_method' => 'stripe',
            'status' => 'pending',
            'description' => 'テストチーム会費'
        ];

        $payment_id = create_payment_record($payment_data);
        $this->test_payment_ids[] = $payment_id;

        // 決済キャンセル
        $result = cancel_payment($payment_id);
        
        $this->assertTrue($result);
        $this->assertEquals('canceled', get_post_meta($payment_id, 'payment_status', true));
    }

    /**
     * 決済キャンセル - 異常パターン（既に完了した決済）
     */
    public function testPaymentCancellationCompletedPayment()
    {
        // 決済記録作成（完了済み）
        $payment_data = [
            'user_id' => $this->test_user->ID ?? $this->test_user,
            'team_id' => $this->test_team_id,
            'amount' => 15000,
            'currency' => 'jpy',
            'payment_method' => 'stripe',
            'status' => 'succeeded',
            'description' => 'テストチーム会費'
        ];

        $payment_id = create_payment_record($payment_data);
        $this->test_payment_ids[] = $payment_id;

        // 決済キャンセル
        $result = cancel_payment($payment_id);
        
        $this->assertFalse($result);
        $this->assertEquals('succeeded', get_post_meta($payment_id, 'payment_status', true));
    }

    /**
     * 決済返金 - 正常パターン
     */
    public function testPaymentRefund()
    {
        // 決済記録作成（完了済み）
        $payment_data = [
            'user_id' => $this->test_user->ID ?? $this->test_user,
            'team_id' => $this->test_team_id,
            'amount' => 15000,
            'currency' => 'jpy',
            'payment_method' => 'stripe',
            'status' => 'succeeded',
            'description' => 'テストチーム会費'
        ];

        $payment_id = create_payment_record($payment_data);
        $this->test_payment_ids[] = $payment_id;

        // 決済返金
        $result = refund_payment($payment_id, 15000, 'テスト返金');
        
        $this->assertTrue($result);
        $this->assertEquals('refunded', get_post_meta($payment_id, 'payment_status', true));
    }

    /**
     * 決済返金 - 異常パターン（未完了の決済）
     */
    public function testPaymentRefundIncompletePayment()
    {
        // 決済記録作成（未完了）
        $payment_data = [
            'user_id' => $this->test_user->ID ?? $this->test_user,
            'team_id' => $this->test_team_id,
            'amount' => 15000,
            'currency' => 'jpy',
            'payment_method' => 'stripe',
            'status' => 'pending',
            'description' => 'テストチーム会費'
        ];

        $payment_id = create_payment_record($payment_data);
        $this->test_payment_ids[] = $payment_id;

        // 決済返金
        $result = refund_payment($payment_id, 15000, 'テスト返金');
        
        $this->assertFalse($result);
        $this->assertEquals('pending', get_post_meta($payment_id, 'payment_status', true));
    }

    /**
     * 決済検証 - 正常パターン
     */
    public function testPaymentVerification()
    {
        // 決済記録作成
        $payment_data = [
            'user_id' => $this->test_user->ID ?? $this->test_user,
            'team_id' => $this->test_team_id,
            'amount' => 15000,
            'currency' => 'jpy',
            'payment_method' => 'stripe',
            'status' => 'pending',
            'description' => 'テストチーム会費'
        ];

        $payment_id = create_payment_record($payment_data);
        $this->test_payment_ids[] = $payment_id;

        // 決済検証
        $result = verify_payment($payment_id, 'pi_test123');
        
        $this->assertTrue($result);
    }

    /**
     * 決済検証 - 異常パターン（無効な決済ID）
     */
    public function testPaymentVerificationInvalidPaymentId()
    {
        $result = verify_payment(99999, 'pi_test123');
        
        $this->assertFalse($result);
    }

    /**
     * 決済レポート生成 - 正常パターン
     */
    public function testPaymentReportGeneration()
    {
        // 複数の決済記録を作成
        $payment_data1 = [
            'user_id' => $this->test_user->ID ?? $this->test_user,
            'team_id' => $this->test_team_id,
            'amount' => 15000,
            'currency' => 'jpy',
            'payment_method' => 'stripe',
            'status' => 'succeeded',
            'description' => '1月会費'
        ];

        $payment_data2 = [
            'user_id' => $this->test_user->ID ?? $this->test_user,
            'team_id' => $this->test_team_id,
            'amount' => 15000,
            'currency' => 'jpy',
            'payment_method' => 'stripe',
            'status' => 'succeeded',
            'description' => '2月会費'
        ];

        $payment1_id = create_payment_record($payment_data1);
        $payment2_id = create_payment_record($payment_data2);
        
        $this->test_payment_ids[] = $payment1_id;
        $this->test_payment_ids[] = $payment2_id;

        // 決済レポート生成
        $report = generate_payment_report($this->test_team_id, '2024-01-01', '2024-12-31');
        
        $this->assertIsArray($report);
        $this->assertArrayHasKey('total_amount', $report);
        $this->assertArrayHasKey('payment_count', $report);
        $this->assertArrayHasKey('payments', $report);
        $this->assertEquals(30000, $report['total_amount']);
        $this->assertEquals(2, $report['payment_count']);
        $this->assertCount(2, $report['payments']);
    }
} 