<?php
/**
 * スケジュール機能の統合テスト
 */

use PHPUnit\Framework\TestCase;

/**
 * スケジュール機能の統合テスト
 */
class ScheduleTest extends TestCase
{
    protected $test_user_id;
    protected $test_team_id;
    protected $test_schedule_ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        
        // WordPressのテスト環境を初期化
        if (!function_exists('wp_insert_user')) {
            require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
            require_once dirname(__DIR__, 2) . '/tests/bootstrap-simple.php';
        }

        // テスト用ユーザーとチームを作成
        $this->test_user_id = wp_create_user('test_leader', 'password123', 'leader@example.com');
        update_user_meta($this->test_user_id, 'registration_status', 'accepted');
        update_user_meta($this->test_user_id, 'test_user', 'true');

        // テスト用チームを作成
        $team_data = [
            'team_name' => 'テストチーム',
            'sport_type' => 'soccer',
            'region' => 'tokyo'
        ];
        $this->test_team_id = aidunite_register_team($this->test_user_id, $team_data);
        if (function_exists('aidunite_approve_team')) {
            aidunite_approve_team($this->test_team_id);
        }
        
        // 現在のユーザーを設定
        wp_set_current_user($this->test_user_id);
    }

    protected function tearDown(): void
    {
        // テストで作成したデータをクリーンアップ
        foreach ($this->test_schedule_ids as $schedule_id) {
            wp_delete_post($schedule_id, true);
        }
        
        if ($this->test_team_id) {
            wp_delete_post($this->test_team_id, true);
        }
        
        if ($this->test_user_id) {
            wp_delete_user($this->test_user_id);
        }
        
        parent::tearDown();
    }

    /**
     * スケジュール登録 - 正常パターン
     */
    public function testScheduleRegistrationSuccess()
    {
        $schedule_data = [
            'date' => '2024-01-15',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'place' => 'テストグラウンド',
            'note' => 'テスト用の練習です',
            'type' => 'practice',
            'matching' => 1,
            'gender_condition' => 'mixed',
            'place_condition' => 'any'
        ];

        // 現在のユーザーを設定
        wp_set_current_user($this->test_user_id);
        
        $schedule_id = register_schedules_callback(['body' => json_encode([$schedule_data])]);
        
        $this->assertNotFalse($schedule_id);
        $this->assertIsInt($schedule_id);
        
        // スケジュール情報の確認
        $schedule_post = get_post($schedule_id);
        $this->assertEquals('schedule', $schedule_post->post_type);
        $this->assertEquals('publish', $schedule_post->post_status);
        $this->assertEquals($this->test_user_id, $schedule_post->post_author);
        
        // メタデータの確認
        $this->assertEquals('2024-01-15', get_post_meta($schedule_id, 'schedule_date', true));
        $this->assertEquals('10:00', get_post_meta($schedule_id, 'schedule_start_time', true));
        $this->assertEquals('12:00', get_post_meta($schedule_id, 'schedule_end_time', true));
        $this->assertEquals('テストグラウンド', get_post_meta($schedule_id, 'schedule_place', true));
        $this->assertEquals('テスト用の練習です', get_post_meta($schedule_id, 'schedule_note', true));
        $this->assertEquals('practice', get_post_meta($schedule_id, 'schedule_type', true));
        $this->assertEquals(1, get_post_meta($schedule_id, 'is_match_requested', true));
        $this->assertEquals('mixed', get_post_meta($schedule_id, 'matching_gender_condition', true));
        $this->assertEquals('any', get_post_meta($schedule_id, 'schedule_place_option', true));
        
        $this->test_schedule_ids[] = $schedule_id;
    }

    /**
     * スケジュール登録 - 異常パターン（必須項目不足）
     */
    public function testScheduleRegistrationMissingRequiredFields()
    {
        $schedule_data = [
            'start_time' => '10:00',
            'end_time' => '12:00',
            'place' => 'テストグラウンド',
            'type' => 'practice'
            // dateが不足
        ];

        $schedule_id = register_schedules_callback(['body' => json_encode([$schedule_data])]);
        
        $this->assertFalse($schedule_id);
    }

    /**
     * スケジュール登録 - 異常パターン（無効な日付）
     */
    public function testScheduleRegistrationInvalidDate()
    {
        $schedule_data = [
            'date' => 'invalid-date',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'place' => 'テストグラウンド',
            'type' => 'practice'
        ];

        $schedule_id = register_schedules_callback(['body' => json_encode([$schedule_data])]);
        
        $this->assertFalse($schedule_id);
    }

    /**
     * スケジュール登録 - 異常パターン（終了時刻が開始時刻より前）
     */
    public function testScheduleRegistrationInvalidTimeRange()
    {
        $schedule_data = [
            'date' => '2024-01-15',
            'start_time' => '12:00',
            'end_time' => '10:00', // 開始時刻より前
            'place' => 'テストグラウンド',
            'type' => 'practice'
        ];

        $schedule_id = register_schedules_callback(['body' => json_encode([$schedule_data])]);
        
        $this->assertFalse($schedule_id);
    }

    /**
     * スケジュール編集 - 正常パターン
     */
    public function testScheduleEditSuccess()
    {
        // スケジュール作成
        $schedule_data = [
            'date' => '2024-01-15',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'place' => '編集前グラウンド',
            'note' => '編集前のメモ',
            'type' => 'practice'
        ];

        $schedule_id = register_schedules_callback(['body' => json_encode([$schedule_data])]);
        $this->test_schedule_ids[] = $schedule_id;

        // スケジュール編集
        $updated_data = [
            'post_id' => $schedule_id,
            'date' => '2024-01-16',
            'start_time' => '14:00',
            'end_time' => '16:00',
            'place' => '編集後グラウンド',
            'note' => '編集後のメモ',
            'type' => 'match'
        ];

        $result = aidunite_update_schedule(['body' => json_encode($updated_data)]);
        
        $this->assertTrue($result);
        
        // 更新内容の確認
        $this->assertEquals('2024-01-16', get_post_meta($schedule_id, 'schedule_date', true));
        $this->assertEquals('14:00', get_post_meta($schedule_id, 'schedule_start_time', true));
        $this->assertEquals('16:00', get_post_meta($schedule_id, 'schedule_end_time', true));
        $this->assertEquals('編集後グラウンド', get_post_meta($schedule_id, 'schedule_place', true));
        $this->assertEquals('編集後のメモ', get_post_meta($schedule_id, 'schedule_note', true));
        $this->assertEquals('match', get_post_meta($schedule_id, 'schedule_type', true));
    }

    /**
     * スケジュール編集 - 異常パターン（存在しないスケジュール）
     */
    public function testScheduleEditNonexistentSchedule()
    {
        $updated_data = [
            'post_id' => 99999,
            'date' => '2024-01-16',
            'start_time' => '14:00',
            'end_time' => '16:00',
            'place' => 'テストグラウンド',
            'type' => 'practice'
        ];

        $result = aidunite_update_schedule(['body' => json_encode($updated_data)]);
        
        $this->assertFalse($result);
    }

    /**
     * スケジュール削除 - 正常パターン
     */
    public function testScheduleDeletionSuccess()
    {
        // スケジュール作成
        $schedule_data = [
            'date' => '2024-01-15',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'place' => '削除テストグラウンド',
            'type' => 'practice'
        ];

        $schedule_id = register_schedules_callback(['body' => json_encode([$schedule_data])]);

        // スケジュール削除
        $result = aidunite_delete_schedule(['body' => json_encode(['post_id' => $schedule_id])]);
        
        $this->assertTrue($result);
        
        // 削除後の確認
        $schedule_post = get_post($schedule_id);
        $this->assertNull($schedule_post);
    }

    /**
     * スケジュール削除 - 異常パターン（存在しないスケジュール）
     */
    public function testScheduleDeletionNonexistentSchedule()
    {
        $result = aidunite_delete_schedule(['body' => json_encode(['post_id' => 99999])]);
        
        $this->assertFalse($result);
    }

    /**
     * ユーザースケジュール取得 - 正常パターン
     */
    public function testUserSchedulesRetrieval()
    {
        // 複数のスケジュールを作成
        $schedule_data1 = [
            'date' => '2024-01-15',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'place' => 'グラウンドA',
            'type' => 'practice'
        ];

        $schedule_data2 = [
            'date' => '2024-01-16',
            'start_time' => '14:00',
            'end_time' => '16:00',
            'place' => 'グラウンドB',
            'type' => 'match'
        ];

        // 現在のユーザーを設定
        wp_set_current_user($this->test_user_id);
        
        $schedule1_id = register_schedules_callback(['body' => json_encode([$schedule_data1])]);
        $schedule2_id = register_schedules_callback(['body' => json_encode([$schedule_data2])]);
        
        $this->test_schedule_ids[] = $schedule1_id;
        $this->test_schedule_ids[] = $schedule2_id;

        // ユーザースケジュール取得
        $schedules = aidunite_get_user_schedules(['user_id' => $this->test_user_id]);
        
        $this->assertIsArray($schedules);
        $this->assertGreaterThanOrEqual(2, count($schedules));
        
        // スケジュール内容の確認
        $found_schedule1 = false;
        $found_schedule2 = false;
        
        foreach ($schedules as $schedule) {
            if ($schedule['id'] == $schedule1_id) {
                $this->assertEquals('2024-01-15', $schedule['date']);
                $this->assertEquals('practice', $schedule['type']);
                $found_schedule1 = true;
            }
            if ($schedule['id'] == $schedule2_id) {
                $this->assertEquals('2024-01-16', $schedule['date']);
                $this->assertEquals('match', $schedule['type']);
                $found_schedule2 = true;
            }
        }
        
        $this->assertTrue($found_schedule1);
        $this->assertTrue($found_schedule2);
    }

    /**
     * 日付別スケジュール取得 - 正常パターン
     */
    public function testSchedulesByDateRetrieval()
    {
        // 同じ日付の複数スケジュールを作成
        $schedule_data1 = [
            'date' => '2024-01-15',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'place' => 'グラウンドA',
            'type' => 'practice'
        ];

        $schedule_data2 = [
            'date' => '2024-01-15',
            'start_time' => '14:00',
            'end_time' => '16:00',
            'place' => 'グラウンドB',
            'type' => 'match'
        ];

        // 現在のユーザーを設定
        wp_set_current_user($this->test_user_id);
        
        $schedule1_id = register_schedules_callback(['body' => json_encode([$schedule_data1])]);
        $schedule2_id = register_schedules_callback(['body' => json_encode([$schedule_data2])]);
        
        $this->test_schedule_ids[] = $schedule1_id;
        $this->test_schedule_ids[] = $schedule2_id;

        // 日付別スケジュール取得
        $schedules = aidunite_get_schedules_by_date(['body' => json_encode(['date' => '2024-01-15'])]);
        
        $this->assertIsArray($schedules);
        $this->assertCount(2, $schedules);
        
        // 時間順にソートされているか確認
        $this->assertEquals('10:00', $schedules[0]['start_time']);
        $this->assertEquals('14:00', $schedules[1]['start_time']);
    }

    /**
     * 日付別スケジュール取得 - 異常パターン（スケジュールなし）
     */
    public function testSchedulesByDateNoSchedules()
    {
        $schedules = aidunite_get_schedules_by_date(['body' => json_encode(['date' => '2024-12-31'])]);
        
        $this->assertIsArray($schedules);
        $this->assertCount(0, $schedules);
    }

    /**
     * スケジュール重複チェック - 正常パターン
     */
    public function testScheduleOverlapCheck()
    {
        // 既存のスケジュールを作成
        $existing_schedule_data = [
            'date' => '2024-01-15',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'place' => 'テストグラウンド',
            'type' => 'practice'
        ];

        // 現在のユーザーを設定
        wp_set_current_user($this->test_user_id);
        
        $existing_schedule_id = register_schedules_callback(['body' => json_encode([$existing_schedule_data])]);
        $this->test_schedule_ids[] = $existing_schedule_id;

        // 重複するスケジュールデータ
        $overlapping_schedule_data = [
            'date' => '2024-01-15',
            'start_time' => '11:00',
            'end_time' => '13:00',
            'place' => 'テストグラウンド',
            'type' => 'practice'
        ];

        // 重複チェック
        $has_overlap = check_schedule_overlap($overlapping_schedule_data, $this->test_user_id);
        
        $this->assertTrue($has_overlap);
    }

    /**
     * スケジュール重複チェック - 異常パターン（重複なし）
     */
    public function testScheduleNoOverlapCheck()
    {
        // 既存のスケジュールを作成
        $existing_schedule_data = [
            'date' => '2024-01-15',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'place' => 'テストグラウンド',
            'type' => 'practice'
        ];

        $existing_schedule_id = register_schedules_callback(['body' => json_encode([$existing_schedule_data])]);
        $this->test_schedule_ids[] = $existing_schedule_id;

        // 重複しないスケジュールデータ
        $non_overlapping_schedule_data = [
            'date' => '2024-01-15',
            'start_time' => '13:00',
            'end_time' => '15:00',
            'place' => 'テストグラウンド',
            'type' => 'practice'
        ];

        // 重複チェック
        $has_overlap = check_schedule_overlap($non_overlapping_schedule_data, $this->test_user_id);
        
        $this->assertFalse($has_overlap);
    }

    /**
     * スケジュールフィルタリング - 正常パターン
     */
    public function testScheduleFiltering()
    {
        // 異なるタイプのスケジュールを作成
        $practice_schedule_data = [
            'date' => '2024-01-15',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'place' => 'グラウンドA',
            'type' => 'practice'
        ];

        $match_schedule_data = [
            'date' => '2024-01-16',
            'start_time' => '14:00',
            'end_time' => '16:00',
            'place' => 'グラウンドB',
            'type' => 'match'
        ];

        // 現在のユーザーを設定
        wp_set_current_user($this->test_user_id);
        
        $practice_id = register_schedules_callback(['body' => json_encode([$practice_schedule_data])]);
        $match_id = register_schedules_callback(['body' => json_encode([$match_schedule_data])]);
        
        $this->test_schedule_ids[] = $practice_id;
        $this->test_schedule_ids[] = $match_id;

        // 練習スケジュールのみフィルタリング
        $practice_schedules = filter_schedules_by_type('practice', $this->test_user_id);
        
        $this->assertIsArray($practice_schedules);
        $this->assertCount(1, $practice_schedules);
        $this->assertEquals('practice', $practice_schedules[0]['type']);

        // マッチスケジュールのみフィルタリング
        $match_schedules = filter_schedules_by_type('match', $this->test_user_id);
        
        $this->assertIsArray($match_schedules);
        $this->assertCount(1, $match_schedules);
        $this->assertEquals('match', $match_schedules[0]['type']);
    }

    /**
     * スケジュール統計情報取得 - 正常パターン
     */
    public function testScheduleStatisticsRetrieval()
    {
        // 異なるタイプのスケジュールを作成
        $schedule_types = ['practice', 'match', 'practice', 'match', 'practice'];
        $dates = ['2024-01-15', '2024-01-16', '2024-01-17', '2024-01-18', '2024-01-19'];
        
        for ($i = 0; $i < count($schedule_types); $i++) {
            $schedule_data = [
                'date' => $dates[$i],
                'start_time' => '10:00',
                'end_time' => '12:00',
                'place' => "グラウンド{$i}",
                'type' => $schedule_types[$i]
            ];
            
            $schedule_id = register_schedules_callback(['body' => json_encode([$schedule_data])]);
            $this->test_schedule_ids[] = $schedule_id;
        }

        // 現在のユーザーを設定
        wp_set_current_user($this->test_user_id);

        // 統計情報取得
        $stats = get_schedule_statistics($this->test_user_id);
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('practice', $stats);
        $this->assertArrayHasKey('match', $stats);
        $this->assertEquals(5, $stats['total']);
        $this->assertEquals(3, $stats['practice']);
        $this->assertEquals(2, $stats['match']);
    }

    /**
     * スケジュールカレンダー表示 - 正常パターン
     */
    public function testScheduleCalendarDisplay()
    {
        // 複数のスケジュールを作成
        $schedule_data1 = [
            'date' => '2024-01-15',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'place' => 'グラウンドA',
            'type' => 'practice'
        ];

        $schedule_data2 = [
            'date' => '2024-01-15',
            'start_time' => '14:00',
            'end_time' => '16:00',
            'place' => 'グラウンドB',
            'type' => 'match'
        ];

        // 現在のユーザーを設定
        wp_set_current_user($this->test_user_id);
        
        $schedule1_id = register_schedules_callback(['body' => json_encode([$schedule_data1])]);
        $schedule2_id = register_schedules_callback(['body' => json_encode([$schedule_data2])]);
        
        $this->test_schedule_ids[] = $schedule1_id;
        $this->test_schedule_ids[] = $schedule2_id;

        // カレンダー表示用データ取得
        $calendar_data = get_calendar_schedule_data('2024-01', $this->test_user_id);
        
        $this->assertIsArray($calendar_data);
        $this->assertArrayHasKey('2024-01-15', $calendar_data);
        $this->assertCount(2, $calendar_data['2024-01-15']);
        
        // イベントデータの確認
        $events = $calendar_data['2024-01-15'];
        $this->assertEquals('practice', $events[0]['type']);
        $this->assertEquals('match', $events[1]['type']);
    }

    /**
     * スケジュール通知設定 - 正常パターン
     */
    public function testScheduleNotificationSettings()
    {
        // スケジュール作成
        $schedule_data = [
            'date' => '2024-01-15',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'place' => 'テストグラウンド',
            'type' => 'practice'
        ];

        $schedule_id = register_schedules_callback(['body' => json_encode([$schedule_data])]);
        $this->test_schedule_ids[] = $schedule_id;

        // 通知設定
        $notification_settings = [
            'reminder_time' => 60, // 60分前
            'notification_type' => 'email',
            'enabled' => true
        ];

        $result = set_schedule_notification_settings($schedule_id, $notification_settings);
        
        $this->assertTrue($result);
        
        // 設定の確認
        $saved_settings = get_schedule_notification_settings($schedule_id);
        $this->assertEquals(60, $saved_settings['reminder_time']);
        $this->assertEquals('email', $saved_settings['notification_type']);
        $this->assertTrue($saved_settings['enabled']);
    }
} 