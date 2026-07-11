<?php
/**
 * 通知・外部連携機能の統合テスト
 */

use PHPUnit\Framework\TestCase;

class NotificationTest extends TestCase
{
    protected $test_user;
    protected $test_notification_id;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // WordPressのテスト環境を初期化
        if (!function_exists('wp_set_current_user')) {
            require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
            require_once dirname(__DIR__, 2) . '/tests/bootstrap-simple.php';
        }
        
        // テスト用のユーザーを作成
        $this->test_user = create_test_user('subscriber');
        
        // ログインユーザーとして設定
        wp_set_current_user($this->test_user->ID);
    }
    
    protected function tearDown(): void
    {
        // テストデータのクリーンアップ
        if ($this->test_notification_id) {
            wp_delete_post($this->test_notification_id, true);
        }
        
        if ($this->test_user) {
            wp_delete_user($this->test_user->ID);
        }
        
        parent::tearDown();
    }
    
    /**
     * メール通知送信 - 正常パターン
     */
    public function testEmailNotificationSuccess()
    {
        $notification_data = [
            'user_id' => $this->test_user->ID,
            'type' => 'match_application',
            'title' => 'マッチ申請が承認されました',
            'message' => 'あなたのマッチ申請が承認されました。',
            'email_enabled' => true
        ];
        
        $this->test_notification_id = create_notification($notification_data);
        
        // メール通知送信
        $result = send_email_notification($this->test_notification_id);
        $this->assertTrue($result);
        
        // 通知記録の確認
        $notification = get_notification($this->test_notification_id);
        $this->assertEquals('sent', $notification['email_status']);
    }
    
    /**
     * メール通知送信 - 無効なメールアドレス
     */
    public function testEmailNotificationInvalidEmail()
    {
        // 無効なメールアドレスを持つユーザーを作成
        $invalid_user = create_test_user('subscriber');
        update_user_meta($invalid_user->ID, 'user_email', 'invalid-email');
        
        $notification_data = [
            'user_id' => $invalid_user->ID,
            'type' => 'match_application',
            'title' => 'テスト通知',
            'message' => 'テストメッセージ',
            'email_enabled' => true,
            'recipient_email' => 'invalid-email' // 無効なメールアドレスを明示的に設定
        ];
        
        $notification_id = create_notification($notification_data);
        
        // メール通知送信
        $result = send_email_notification($notification_id);
        $this->assertFalse($result);
        
        // クリーンアップ
        wp_delete_post($notification_id, true);
        wp_delete_user($invalid_user->ID);
    }
    
    /**
     * 通知作成 - 正常パターン
     */
    public function testCreateNotification()
    {
        $notification_data = [
            'user_id' => $this->test_user->ID,
            'type' => 'team_invitation',
            'title' => 'チーム招待',
            'message' => '新しいチームに招待されました。',
            'related_post_id' => 123,
            'link_url' => '/team/123'
        ];
        
        $this->test_notification_id = create_notification($notification_data);
        
        $this->assertIsInt($this->test_notification_id);
        $this->assertGreaterThan(0, $this->test_notification_id);
        
        // 通知内容の確認
        $notification = get_notification($this->test_notification_id);
        $this->assertEquals($notification_data['title'], $notification['title']);
        $this->assertEquals($notification_data['message'], $notification['message']);
        $this->assertEquals($notification_data['type'], $notification['type']);
    }
    
    /**
     * 通知作成 - 異常パターン（必須項目不足）
     */
    public function testCreateNotificationMissingRequiredFields()
    {
        $notification_data = [
            'user_id' => $this->test_user->ID,
            'type' => 'team_invitation',
            'title' => '', // 空のタイトル
            'message' => 'テストメッセージ'
        ];
        
        $notification_id = create_notification($notification_data);
        $this->assertFalse($notification_id);
    }
    
    /**
     * 通知一覧取得 - 正常パターン
     */
    public function testGetUserNotifications()
    {
        // 複数の通知を作成
        $notification1_data = [
            'user_id' => $this->test_user->ID,
            'type' => 'match_application',
            'title' => '通知1',
            'message' => 'メッセージ1'
        ];
        
        $notification2_data = [
            'user_id' => $this->test_user->ID,
            'type' => 'team_invitation',
            'title' => '通知2',
            'message' => 'メッセージ2'
        ];
        
        $notification1_id = create_notification($notification1_data);
        $notification2_id = create_notification($notification2_data);
        
        // 通知一覧取得
        $notifications = get_user_notifications($this->test_user->ID);
        
        $this->assertIsArray($notifications);
        // このテストで作成した通知のみを確認（他のテストの通知が混入する可能性があるため、最低2つ以上であることを確認）
        $this->assertGreaterThanOrEqual(2, count($notifications));
        
        // 作成した通知が含まれていることを確認
        $notification_ids = array_column($notifications, 'id');
        $this->assertContains($notification1_id, $notification_ids);
        $this->assertContains($notification2_id, $notification_ids);
        
        // 日付順でソートされているか確認
        if (count($notifications) >= 2) {
            $this->assertGreaterThanOrEqual($notifications[1]['created_at'], $notifications[0]['created_at']);
        }
        
        // クリーンアップ
        delete_notification($notification1_id);
        delete_notification($notification2_id);
        
        // クリーンアップ
        wp_delete_post($notification1_id, true);
        wp_delete_post($notification2_id, true);
    }
    
    /**
     * 通知一覧取得 - フィルター付き
     */
    public function testGetUserNotificationsWithFilter()
    {
        // 複数の通知を作成
        $notification1_data = [
            'user_id' => $this->test_user->ID,
            'type' => 'match_application',
            'title' => 'マッチ申請通知',
            'message' => 'メッセージ1'
        ];
        
        $notification2_data = [
            'user_id' => $this->test_user->ID,
            'type' => 'team_invitation',
            'title' => 'チーム招待通知',
            'message' => 'メッセージ2'
        ];
        
        $notification1_id = create_notification($notification1_data);
        $notification2_id = create_notification($notification2_data);
        
        // マッチ申請通知のみ取得
        $match_notifications = get_user_notifications($this->test_user->ID, ['type' => 'match_application']);
        // このテストで作成した通知を含むことを確認（他のテストの通知が混入する可能性があるため）
        $this->assertGreaterThanOrEqual(1, count($match_notifications));
        
        // 作成した通知が含まれていることを確認
        $notification_ids = array_column($match_notifications, 'id');
        $this->assertContains($notification1_id, $notification_ids);
        $this->assertEquals('マッチ申請通知', $match_notifications[array_search($notification1_id, $notification_ids)]['title']);
        
        // クリーンアップ
        delete_notification($notification1_id);
        delete_notification($notification2_id);
    }
    
    /**
     * 通知既読処理 - 正常パターン
     */
    public function testMarkNotificationAsRead()
    {
        $notification_data = [
            'user_id' => $this->test_user->ID,
            'type' => 'match_application',
            'title' => 'テスト通知',
            'message' => 'テストメッセージ'
        ];
        
        $this->test_notification_id = create_notification($notification_data);
        
        // 既読処理
        $result = mark_notification_as_read($this->test_notification_id);
        $this->assertTrue($result);
        
        // 既読状態の確認
        $notification = get_notification($this->test_notification_id);
        $this->assertEquals('read', $notification['status']);
    }
    
    /**
     * 通知削除 - 正常パターン
     */
    public function testDeleteNotification()
    {
        $notification_data = [
            'user_id' => $this->test_user->ID,
            'type' => 'match_application',
            'title' => '削除対象通知',
            'message' => '削除される通知'
        ];
        
        $notification_id = create_notification($notification_data);
        
        // 通知削除
        $result = delete_notification($notification_id);
        $this->assertTrue($result);
        
        // 削除確認
        $notification = get_notification($notification_id);
        $this->assertNull($notification);
    }
    
    /**
     * 通知統計取得 - 正常パターン
     */
    public function testGetNotificationStatistics()
    {
        // 複数の通知を作成
        $notification1_data = [
            'user_id' => $this->test_user->ID,
            'type' => 'match_application',
            'title' => '未読通知1',
            'message' => 'メッセージ1'
        ];
        
        $notification2_data = [
            'user_id' => $this->test_user->ID,
            'type' => 'team_invitation',
            'title' => '未読通知2',
            'message' => 'メッセージ2'
        ];
        
        $notification3_data = [
            'user_id' => $this->test_user->ID,
            'type' => 'payment',
            'title' => '既読通知',
            'message' => 'メッセージ3'
        ];
        
        $notification1_id = create_notification($notification1_data);
        $notification2_id = create_notification($notification2_data);
        $notification3_id = create_notification($notification3_data);
        
        // 既読処理
        mark_notification_as_read($notification3_id);
        
        // 通知統計取得
        $statistics = get_notification_statistics($this->test_user->ID);
        
        $this->assertIsArray($statistics);
        $this->assertArrayHasKey('total_count', $statistics);
        $this->assertArrayHasKey('unread_count', $statistics);
        $this->assertArrayHasKey('read_count', $statistics);
        $this->assertArrayHasKey('by_type', $statistics);
        
        // このテストで作成した通知を含むことを確認（他のテストの通知が混入する可能性があるため）
        $this->assertGreaterThanOrEqual(3, $statistics['total_count']);
        $this->assertGreaterThanOrEqual(2, $statistics['unread_count']);
        $this->assertGreaterThanOrEqual(1, $statistics['read_count']);
        
        // クリーンアップ
        delete_notification($notification1_id);
        delete_notification($notification2_id);
        delete_notification($notification3_id);
    }
    
    /**
     * 管理者通知送信 - 正常パターン
     */
    public function testSendAdminNotification()
    {
        $admin_notification_data = [
            'type' => 'system_alert',
            'title' => 'システムアラート',
            'message' => '重要なシステム通知です。',
            'priority' => 'high'
        ];
        
        $result = send_admin_notification($admin_notification_data);
        $this->assertTrue($result);
        
        // 管理者通知の確認
        $admin_notifications = get_admin_notifications();
        $this->assertNotEmpty($admin_notifications);
        
        $system_alert = array_filter($admin_notifications, function($notification) {
            return $notification['type'] === 'system_alert';
        });
        
        $this->assertNotEmpty($system_alert);
    }
    
    /**
     * リマインダー通知送信 - 正常パターン
     */
    public function testSendReminderNotification()
    {
        $reminder_data = [
            'user_id' => $this->test_user->ID,
            'type' => 'payment_reminder',
            'title' => '支払いリマインダー',
            'message' => '支払い期限が近づいています。',
            'reminder_date' => date('Y-m-d', strtotime('+3 days'))
        ];
        
        $result = schedule_reminder_notification($reminder_data);
        $this->assertTrue($result);
        
        // リマインダーの確認
        $reminders = get_scheduled_reminders($this->test_user->ID);
        $this->assertNotEmpty($reminders);
        
        $payment_reminder = array_filter($reminders, function($reminder) {
            return $reminder['type'] === 'payment_reminder';
        });
        
        $this->assertNotEmpty($payment_reminder);
    }
    
    /**
     * 通知テンプレート取得 - 正常パターン
     */
    public function testGetNotificationTemplate()
    {
        $template = get_notification_template('match_application_approved');
        
        $this->assertIsArray($template);
        $this->assertArrayHasKey('title', $template);
        $this->assertArrayHasKey('message', $template);
        $this->assertArrayHasKey('email_subject', $template);
        $this->assertArrayHasKey('email_body', $template);
    }
    
    /**
     * 通知テンプレート取得 - 存在しないテンプレート
     */
    public function testGetNotificationTemplateNonexistent()
    {
        $template = get_notification_template('nonexistent_template');
        
        $this->assertNull($template);
    }
    
    /**
     * 通知設定取得 - 正常パターン
     */
    public function testGetNotificationSettings()
    {
        $settings = get_user_notification_settings($this->test_user->ID);
        
        $this->assertIsArray($settings);
        $this->assertArrayHasKey('email_enabled', $settings);
        $this->assertArrayHasKey('push_enabled', $settings);
        $this->assertArrayHasKey('notification_types', $settings);
    }
    
    /**
     * 通知設定更新 - 正常パターン
     */
    public function testUpdateNotificationSettings()
    {
        $new_settings = [
            'email_enabled' => false,
            'push_enabled' => true,
            'notification_types' => ['match_application', 'team_invitation']
        ];
        
        $result = update_user_notification_settings($this->test_user->ID, $new_settings);
        $this->assertTrue($result);
        
        // 設定更新の確認
        $updated_settings = get_user_notification_settings($this->test_user->ID);
        $this->assertEquals($new_settings['email_enabled'], $updated_settings['email_enabled']);
        $this->assertEquals($new_settings['push_enabled'], $updated_settings['push_enabled']);
    }
} 