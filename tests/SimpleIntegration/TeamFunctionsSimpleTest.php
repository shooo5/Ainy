<?php
/**
 * チーム機能の簡易版統合テスト
 * WordPressテスト環境なしで動作
 */

use PHPUnit\Framework\TestCase;

class TeamFunctionsSimpleTest extends TestCase
{
    protected $test_user;
    protected $test_team_id;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // テスト用のユーザーを作成
        $this->test_user = create_test_user('subscriber');
        
        // テスト用のチームを作成
        $this->test_team_id = create_test_team($this->test_user->ID);
    }
    
    protected function tearDown(): void
    {
        // テストデータのクリーンアップ
        if ($this->test_team_id) {
            wp_delete_post($this->test_team_id, true);
        }
        
        if ($this->test_user) {
            wp_delete_user($this->test_user->ID);
        }
        
        parent::tearDown();
    }
    
    /**
     * チーム情報取得関数のテスト（モック版・aidunite_get_my_team_info に統一）
     */
    public function testAiduniteGetMyTeamInfo()
    {
        $this->assertTrue(function_exists('aidunite_get_my_team_info'));
        wp_set_current_user($this->test_user->ID);
        $team_info = aidunite_get_my_team_info($this->test_user->ID);
        $this->assertIsArray($team_info);
        $this->assertArrayHasKey('team_id', $team_info);
    }
    
    /**
     * チーム申請機能のテスト（モック版）
     */
    public function testAiduniteHasAlreadyApplied()
    {
        // 関数が存在するかチェック
        $this->assertTrue(function_exists('aidunite_has_already_applied'));
        
        // まだ申請していない状態をテスト
        $has_applied = aidunite_has_already_applied($this->test_user->ID, $this->test_team_id);
        $this->assertFalse($has_applied);
    }
    
    /**
     * チーム投稿タイプの登録テスト
     */
    public function testTeamPostTypeRegistration()
    {
        // 投稿タイプが登録されているかチェック
        $post_types = get_post_types();
        $this->assertContains('team', $post_types);
        
        // チーム投稿タイプの詳細を取得
        $team_post_type = get_post_type_object('team');
        $this->assertNotNull($team_post_type);
        $this->assertEquals('チーム', $team_post_type->labels->name);
    }
    
    /**
     * 基本的なWordPress関数の動作確認
     */
    public function testWordPressFunctions()
    {
        // wp_insert_userのテスト
        $user_id = wp_insert_user([
            'user_login' => 'test_user_' . uniqid(),
            'user_email' => 'test_' . uniqid() . '@example.com',
            'user_pass' => 'password123'
        ]);
        $this->assertIsInt($user_id);
        
        // wp_insert_postのテスト
        $post_id = wp_insert_post([
            'post_title' => 'Test Post',
            'post_content' => 'Test Content',
            'post_status' => 'publish'
        ]);
        $this->assertIsInt($post_id);
        
        // update_post_metaのテスト
        $result = update_post_meta($post_id, 'test_key', 'test_value');
        $this->assertTrue($result);
    }
    
    /**
     * チーム関連関数の存在確認
     */
    public function testTeamFunctionsExist()
    {
        $this->assertTrue(function_exists('aidunite_get_my_team_info'));
        $this->assertTrue(function_exists('aidunite_has_already_applied'));
        $this->assertTrue(function_exists('aidunite_get_team_info'));
        $this->assertTrue(function_exists('aidunite_get_team_members'));
    }
} 