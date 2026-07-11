<?php
/**
 * マッチ関連ロジックの単体テスト
 */

use PHPUnit\Framework\TestCase;

class MatchLogicTest extends TestCase
{
    protected $test_user_id;
    protected $test_team_id;
    protected $test_schedule_ids = [];
    protected $test_match_request_ids = [];

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
    }

    protected function tearDown(): void
    {
        // テストで作成したデータをクリーンアップ
        foreach ($this->test_match_request_ids as $request_id) {
            wp_delete_post($request_id, true);
        }
        
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
     * スケジュール重複チェック - 重複する場合
     */
    public function testScheduleOverlaps()
    {
        $schedule1 = [
            'start_date' => '2024-02-15',
            'start_time' => '14:00',
            'end_time' => '16:00'
        ];
        
        $schedule2 = [
            'start_date' => '2024-02-15',
            'start_time' => '15:00',
            'end_time' => '17:00'
        ];
        
        $overlaps = overlaps($schedule1, $schedule2);
        $this->assertTrue($overlaps);
    }
    
    /**
     * スケジュール重複チェック - 重複しない場合（時間が異なる）
     */
    public function testScheduleNoOverlapDifferentTime()
    {
        $schedule1 = [
            'start_date' => '2024-02-15',
            'start_time' => '14:00',
            'end_time' => '16:00'
        ];
        
        $schedule2 = [
            'start_date' => '2024-02-15',
            'start_time' => '17:00',
            'end_time' => '19:00'
        ];
        
        $overlaps = overlaps($schedule1, $schedule2);
        $this->assertFalse($overlaps);
    }
    
    /**
     * スケジュール重複チェック - 重複しない場合（日付が異なる）
     */
    public function testScheduleNoOverlapDifferentDate()
    {
        $schedule1 = [
            'start_date' => '2024-02-15',
            'start_time' => '14:00',
            'end_time' => '16:00'
        ];
        
        $schedule2 = [
            'start_date' => '2024-02-16',
            'start_time' => '14:00',
            'end_time' => '16:00'
        ];
        
        $overlaps = overlaps($schedule1, $schedule2);
        $this->assertFalse($overlaps);
    }
    
    /**
     * スケジュール重複チェック - 境界値テスト
     */
    public function testScheduleOverlapBoundary()
    {
        $schedule1 = [
            'start_date' => '2024-02-15',
            'start_time' => '14:00',
            'end_time' => '16:00'
        ];
        
        $schedule2 = [
            'start_date' => '2024-02-15',
            'start_time' => '16:00', // ちょうど終了時刻
            'end_time' => '18:00'
        ];
        
        $overlaps = overlaps($schedule1, $schedule2);
        $this->assertFalse($overlaps); // 重複しない
    }
    
    /**
     * 自動マッチ候補検索 - 正常パターン
     */
    public function testFindMatchCandidates()
    {
        $team_id = 1;
        $search_criteria = [
            'sport_type' => 'soccer',
            'team_level' => 'beginner',
            'region' => 'tokyo',
            'date_from' => '2024-02-01',
            'date_to' => '2024-02-28'
        ];
        
        $candidates = find_match_candidates($team_id, $search_criteria);
        
        $this->assertIsArray($candidates);
        
        // 候補が存在する場合の検証
        if (!empty($candidates)) {
            foreach ($candidates as $candidate) {
                $this->assertArrayHasKey('team_id', $candidate);
                $this->assertArrayHasKey('match_rank', $candidate);
                $this->assertArrayHasKey('schedule_info', $candidate);
                $this->assertGreaterThanOrEqual(0, $candidate['match_rank']);
                $this->assertLessThanOrEqual(100, $candidate['match_rank']);
            }
        }
    }
    
    /**
     * 自動マッチ候補検索 - 条件に合わない場合
     */
    public function testFindMatchCandidatesNoMatches()
    {
        $team_id = 1;
        $search_criteria = [
            'sport_type' => 'nonexistent_sport',
            'team_level' => 'expert',
            'region' => 'nonexistent_region',
            'date_from' => '2024-12-01',
            'date_to' => '2024-12-31'
        ];
        
        $candidates = find_match_candidates($team_id, $search_criteria);
        
        $this->assertIsArray($candidates);
        $this->assertEmpty($candidates);
    }
    
    /**
     * マッチ申請の重複チェック - 重複する場合
     */
    public function testCheckDuplicateApplication()
    {
        $user_id = 1;
        $match_id = 1;
        
        // 既存の申請を作成
        $application_data = [
            'post_type' => 'match_application',
            'post_status' => 'pending',
            'post_author' => $user_id,
            'meta_input' => [
                'match_id' => $match_id,
                'user_id' => $user_id
            ]
        ];
        
        $application_id = wp_insert_post($application_data);
        
        // 重複チェック
        $is_duplicate = check_duplicate_application($user_id, $match_id);
        $this->assertTrue($is_duplicate);
        
        // クリーンアップ
        wp_delete_post($application_id, true);
    }
    
    /**
     * マッチ申請の重複チェック - 重複しない場合
     */
    public function testCheckDuplicateApplicationNoDuplicate()
    {
        $user_id = 1;
        $match_id = 999; // 存在しないマッチID
        
        $is_duplicate = check_duplicate_application($user_id, $match_id);
        $this->assertFalse($is_duplicate);
    }
    
    /**
     * マッチ申請の有効性チェック - 有効な申請
     */
    public function testValidateMatchApplication()
    {
        $application_data = [
            'match_id' => 1,
            'user_id' => 1,
            'message' => 'Test application message',
            'team_level' => 'beginner',
            'sport_type' => 'soccer'
        ];
        
        $validation_result = validate_match_application($application_data);
        $this->assertTrue($validation_result['valid']);
    }
    
    /**
     * マッチ申請の有効性チェック - 無効な申請（必須項目不足）
     */
    public function testValidateMatchApplicationInvalid()
    {
        $application_data = [
            'match_id' => 1,
            'user_id' => 1,
            'message' => '', // 空のメッセージ
            'team_level' => 'beginner',
            'sport_type' => 'soccer'
        ];
        
        $validation_result = validate_match_application($application_data);
        $this->assertFalse($validation_result['valid']);
        $this->assertArrayHasKey('errors', $validation_result);
    }
    
    /**
     * マッチ申請の有効性チェック - 無効な申請（存在しないマッチ）
     */
    public function testValidateMatchApplicationNonexistentMatch()
    {
        $application_data = [
            'match_id' => 99999, // 存在しないマッチID
            'user_id' => 1,
            'message' => 'Test application message',
            'team_level' => 'beginner',
            'sport_type' => 'soccer'
        ];
        
        $validation_result = validate_match_application($application_data);
        $this->assertFalse($validation_result['valid']);
        $this->assertArrayHasKey('errors', $validation_result);
    }

    /**
     * 自動マッチ候補判定 - 正常パターン（条件一致）
     */
    public function testAutoMatchCandidateMatchingConditions()
    {
        // 自チームのスケジュール作成
        $my_schedule_data = [
            'post_type' => 'schedule',
            'post_title' => '2024-01-15 練習',
            'post_status' => 'publish',
            'post_author' => $this->test_user_id,
            'meta_input' => [
                'schedule_date' => '2024-01-15',
                'schedule_start_time' => '10:00',
                'schedule_end_time' => '12:00',
                'schedule_place' => 'テストグラウンド',
                'schedule_type' => 'practice',
                'is_match_requested' => 1,
                'matching_gender_condition' => 'mixed',
                'schedule_place_option' => 'any'
            ]
        ];
        $my_schedule_id = wp_insert_post($my_schedule_data);
        $this->test_schedule_ids[] = $my_schedule_id;

        // 他チームのスケジュール作成
        $other_user_id = wp_create_user('other_leader', 'password123', 'other@example.com');
        update_user_meta($other_user_id, 'test_user', 'true');
        
        $other_team_data = [
            'team_name' => '他チーム',
            'sport_type' => 'soccer',
            'region' => 'tokyo'
        ];
        $other_team_id = aidunite_register_team($other_user_id, $other_team_data);
        if (function_exists('aidunite_approve_team')) {
            aidunite_approve_team($other_team_id);
        }

        $other_schedule_data = [
            'post_type' => 'schedule',
            'post_title' => '2024-01-15 練習',
            'post_status' => 'publish',
            'post_author' => $other_user_id,
            'meta_input' => [
                'schedule_date' => '2024-01-15',
                'schedule_start_time' => '10:00',
                'schedule_end_time' => '12:00',
                'schedule_place' => 'テストグラウンド',
                'schedule_type' => 'practice',
                'is_match_requested' => 1,
                'matching_gender_condition' => 'mixed',
                'schedule_place_option' => 'any'
            ]
        ];
        $other_schedule_id = wp_insert_post($other_schedule_data);
        $this->test_schedule_ids[] = $other_schedule_id;

        // 自動マッチ条件チェック
        $result = aidunite_check_auto_match_conditions($my_schedule_id, $other_schedule_id);
        
        $this->assertTrue($result);
        
        // クリーンアップ
        wp_delete_post($other_team_id, true);
        wp_delete_user($other_user_id);
    }

    /**
     * 自動マッチ候補判定 - 異常パターン（日時不一致）
     */
    public function testAutoMatchCandidateTimeMismatch()
    {
        // 自チームのスケジュール作成
        $my_schedule_data = [
            'post_type' => 'schedule',
            'post_title' => '2024-01-15 練習',
            'post_status' => 'publish',
            'post_author' => $this->test_user_id,
            'meta_input' => [
                'schedule_date' => '2024-01-15',
                'schedule_start_time' => '10:00',
                'schedule_end_time' => '12:00',
                'schedule_place' => 'テストグラウンド',
                'schedule_type' => 'practice',
                'is_match_requested' => 1
            ]
        ];
        $my_schedule_id = wp_insert_post($my_schedule_data);
        $this->test_schedule_ids[] = $my_schedule_id;

        // 他チームのスケジュール作成（異なる日時）
        $other_user_id = wp_create_user('other_leader', 'password123', 'other@example.com');
        update_user_meta($other_user_id, 'test_user', 'true');
        
        $other_team_data = [
            'team_name' => '他チーム',
            'sport_type' => 'soccer',
            'region' => 'tokyo'
        ];
        $other_team_id = aidunite_register_team($other_user_id, $other_team_data);
        if (function_exists('aidunite_approve_team')) {
            aidunite_approve_team($other_team_id);
        }

        $other_schedule_data = [
            'post_type' => 'schedule',
            'post_title' => '2024-01-16 練習',
            'post_status' => 'publish',
            'post_author' => $other_user_id,
            'meta_input' => [
                'schedule_date' => '2024-01-16', // 異なる日
                'schedule_start_time' => '10:00',
                'schedule_end_time' => '12:00',
                'schedule_place' => 'テストグラウンド',
                'schedule_type' => 'practice',
                'is_match_requested' => 1
            ]
        ];
        $other_schedule_id = wp_insert_post($other_schedule_data);
        $this->test_schedule_ids[] = $other_schedule_id;

        // 自動マッチ条件チェック
        $result = aidunite_check_auto_match_conditions($my_schedule_id, $other_schedule_id);
        
        $this->assertFalse($result);
        
        // クリーンアップ
        wp_delete_post($other_team_id, true);
        wp_delete_user($other_user_id);
    }

    /**
     * マッチ申請作成 - 正常パターン
     */
    public function testMatchRequestCreationSuccess()
    {
        // 自チームのスケジュール作成
        $my_schedule_data = [
            'post_type' => 'schedule',
            'post_title' => '2024-01-15 練習',
            'post_status' => 'publish',
            'post_author' => $this->test_user_id,
            'meta_input' => [
                'schedule_date' => '2024-01-15',
                'schedule_start_time' => '10:00',
                'schedule_end_time' => '12:00',
                'schedule_place' => 'テストグラウンド',
                'schedule_type' => 'practice',
                'is_match_requested' => 1
            ]
        ];
        $my_schedule_id = wp_insert_post($my_schedule_data);
        $this->test_schedule_ids[] = $my_schedule_id;

        // 他チームのスケジュール作成
        $other_user_id = wp_create_user('other_leader', 'password123', 'other@example.com');
        update_user_meta($other_user_id, 'test_user', 'true');
        
        $other_team_data = [
            'team_name' => '他チーム',
            'sport_type' => 'soccer',
            'region' => 'tokyo'
        ];
        $other_team_id = aidunite_register_team($other_user_id, $other_team_data);
        if (function_exists('aidunite_approve_team')) {
            aidunite_approve_team($other_team_id);
        }

        $other_schedule_data = [
            'post_type' => 'schedule',
            'post_title' => '2024-01-15 練習',
            'post_status' => 'publish',
            'post_author' => $other_user_id,
            'meta_input' => [
                'schedule_date' => '2024-01-15',
                'schedule_start_time' => '10:00',
                'schedule_end_time' => '12:00',
                'schedule_place' => 'テストグラウンド',
                'schedule_type' => 'practice',
                'is_match_requested' => 1
            ]
        ];
        $other_schedule_id = wp_insert_post($other_schedule_data);
        $this->test_schedule_ids[] = $other_schedule_id;

        // マッチリクエスト作成
        $match_request = aidunite_create_or_get_auto_match_request(
            $this->test_team_id, 
            $my_schedule_id, 
            $other_team_id, 
            $other_schedule_id
        );
        
        $this->assertNotFalse($match_request);
        $this->assertEquals('match_request', $match_request->post_type);
        $this->assertEquals($this->test_team_id, get_post_meta($match_request->ID, 'from_team_id', true));
        $this->assertEquals($other_team_id, get_post_meta($match_request->ID, 'to_team_id', true));
        $this->assertEquals($my_schedule_id, get_post_meta($match_request->ID, 'from_schedule_id', true));
        $this->assertEquals($other_schedule_id, get_post_meta($match_request->ID, 'to_schedule_id', true));
        
        $this->test_match_request_ids[] = $match_request->ID;
        
        // クリーンアップ
        wp_delete_post($other_team_id, true);
        wp_delete_user($other_user_id);
    }

    /**
     * マッチ申請承認 - 正常パターン
     */
    public function testMatchRequestApprovalSuccess()
    {
        // マッチリクエストを作成
        $match_request_data = [
            'post_type' => 'match_request',
            'post_title' => 'マッチ申請',
            'post_status' => 'publish',
            'post_author' => $this->test_user_id,
            'meta_input' => [
                'from_team_id' => $this->test_team_id,
                'to_team_id' => $this->test_team_id, // テスト用に同じチーム
                'from_schedule_id' => 1,
                'to_schedule_id' => 2,
                'status' => 'pending'
            ]
        ];
        $match_request_id = wp_insert_post($match_request_data);
        $this->test_match_request_ids[] = $match_request_id;

        // マッチ申請承認
        $result = aidunite_approve_match_request($match_request_id);
        
        $this->assertTrue($result);
        $this->assertEquals('accepted', get_post_meta($match_request_id, 'status', true));
    }

    /**
     * マッチ申請拒否 - 正常パターン
     */
    public function testMatchRequestRejectionSuccess()
    {
        // マッチリクエストを作成
        $match_request_data = [
            'post_type' => 'match_request',
            'post_title' => 'マッチ申請',
            'post_status' => 'publish',
            'post_author' => $this->test_user_id,
            'meta_input' => [
                'from_team_id' => $this->test_team_id,
                'to_team_id' => $this->test_team_id,
                'from_schedule_id' => 1,
                'to_schedule_id' => 2,
                'status' => 'pending'
            ]
        ];
        $match_request_id = wp_insert_post($match_request_data);
        $this->test_match_request_ids[] = $match_request_id;

        // マッチ申請拒否
        $result = aidunite_reject_match_request($match_request_id);
        
        $this->assertTrue($result);
        $this->assertEquals('rejected', get_post_meta($match_request_id, 'status', true));
    }

    /**
     * マッチ申請キャンセル - 正常パターン
     */
    public function testMatchRequestCancellationSuccess()
    {
        // マッチリクエストを作成
        $match_request_data = [
            'post_type' => 'match_request',
            'post_title' => 'マッチ申請',
            'post_status' => 'publish',
            'post_author' => $this->test_user_id,
            'meta_input' => [
                'from_team_id' => $this->test_team_id,
                'to_team_id' => $this->test_team_id,
                'from_schedule_id' => 1,
                'to_schedule_id' => 2,
                'status' => 'pending'
            ]
        ];
        $match_request_id = wp_insert_post($match_request_data);
        $this->test_match_request_ids[] = $match_request_id;

        // マッチ申請キャンセル
        $result = aidunite_cancel_match_request($match_request_id);
        
        $this->assertTrue($result);
        $this->assertEquals('canceled', get_post_meta($match_request_id, 'status', true));
    }

    /**
     * マッチ掲示板ステータス更新 - 正常パターン
     */
    public function testMatchBoardStatusUpdate()
    {
        // 掲示板作成
        $board_data = [
            'post_type' => 'match_board',
            'post_title' => 'マッチ掲示板',
            'post_status' => 'publish',
            'post_author' => $this->test_user_id,
            'meta_input' => [
                'match_board_status' => 'open'
            ]
        ];
        $board_id = wp_insert_post($board_data);

        // スケジュール作成（掲示板の子投稿）
        $schedule_data = [
            'post_type' => 'schedule',
            'post_title' => '2024-01-15 練習',
            'post_status' => 'publish',
            'post_author' => $this->test_user_id,
            'post_parent' => $board_id,
            'meta_input' => [
                'schedule_date' => '2024-01-15',
                'schedule_start_time' => '10:00',
                'schedule_end_time' => '12:00'
            ]
        ];
        $schedule_id = wp_insert_post($schedule_data);
        $this->test_schedule_ids[] = $schedule_id;

        // 掲示板ステータス更新（申請時）
        update_board_status_on_apply($schedule_id);
        
        $this->assertEquals('pending', get_post_meta($board_id, 'match_board_status', true));

        // 掲示板ステータス更新（承認時）
        update_board_status_on_approved($schedule_id);
        
        $this->assertEquals('accepted', get_post_meta($board_id, 'match_board_status', true));

        // クリーンアップ
        wp_delete_post($board_id, true);
    }

    /**
     * マッチ申請一覧取得 - 正常パターン
     */
    public function testMatchRequestListRetrieval()
    {
        // 複数のマッチリクエストを作成
        $request1_data = [
            'post_type' => 'match_request',
            'post_title' => 'マッチ申請1',
            'post_status' => 'publish',
            'post_author' => $this->test_user_id,
            'meta_input' => [
                'from_team_id' => $this->test_team_id,
                'to_team_id' => $this->test_team_id,
                'status' => 'pending'
            ]
        ];
        $request1_id = wp_insert_post($request1_data);
        $this->test_match_request_ids[] = $request1_id;

        $request2_data = [
            'post_type' => 'match_request',
            'post_title' => 'マッチ申請2',
            'post_status' => 'publish',
            'post_author' => $this->test_user_id,
            'meta_input' => [
                'from_team_id' => $this->test_team_id,
                'to_team_id' => $this->test_team_id,
                'status' => 'accepted'
            ]
        ];
        $request2_id = wp_insert_post($request2_data);
        $this->test_match_request_ids[] = $request2_id;

        // マッチ申請一覧取得
        $pending_requests = get_match_requests_by_status('pending');
        $accepted_requests = get_match_requests_by_status('accepted');
        
        $this->assertIsArray($pending_requests);
        $this->assertIsArray($accepted_requests);
        $this->assertGreaterThan(0, count($pending_requests));
        $this->assertGreaterThan(0, count($accepted_requests));
    }

    /**
     * マッチ統計情報取得 - 正常パターン
     */
    public function testMatchStatisticsRetrieval()
    {
        // 複数のマッチリクエストを作成（異なるステータス）
        $statuses = ['pending', 'accepted', 'rejected', 'canceled'];
        foreach ($statuses as $status) {
            $request_data = [
                'post_type' => 'match_request',
                'post_title' => "マッチ申請_{$status}",
                'post_status' => 'publish',
                'post_author' => $this->test_user_id,
                'meta_input' => [
                    'from_team_id' => $this->test_team_id,
                    'to_team_id' => $this->test_team_id,
                    'status' => $status
                ]
            ];
            $request_id = wp_insert_post($request_data);
            $this->test_match_request_ids[] = $request_id;
        }

        // 統計情報取得
        $stats = get_match_statistics($this->test_team_id);
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('pending', $stats);
        $this->assertArrayHasKey('accepted', $stats);
        $this->assertArrayHasKey('rejected', $stats);
        $this->assertArrayHasKey('canceled', $stats);
        $this->assertEquals(4, $stats['total']);
    }
} 