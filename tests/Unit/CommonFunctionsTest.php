<?php
/**
 * 共通関数の単体テスト
 */

use PHPUnit\Framework\TestCase;

class CommonFunctionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // 基本的なWordPress関数のモック
        if (!function_exists('wp_create_user')) {
            function wp_create_user($username, $password, $email) {
                static $user_id_counter = 1;
                return $user_id_counter++;
            }
        }
        
        if (!function_exists('update_user_meta')) {
            function update_user_meta($user_id, $meta_key, $meta_value) {
                if (!isset($GLOBALS['test_meta_data'])) {
                    $GLOBALS['test_meta_data'] = [];
                }
                if (!isset($GLOBALS['test_meta_data'][$user_id])) {
                    $GLOBALS['test_meta_data'][$user_id] = [];
                }
                $GLOBALS['test_meta_data'][$user_id][$meta_key] = $meta_value;
                return true;
            }
        }
        
        if (!function_exists('get_user_meta')) {
            function get_user_meta($user_id, $meta_key, $single = true) {
                // グローバル変数を優先チェック
                if (isset($GLOBALS['test_meta_data'][$user_id][$meta_key])) {
                    return $GLOBALS['test_meta_data'][$user_id][$meta_key];
                }
                // デフォルト値（グローバル変数が設定されていない場合のみ）
                $mock_data = [
                    'registration_status' => 'accepted',
                    'team_id' => 123,
                    'aidunite_role' => 'team_leader'
                ];
                return $mock_data[$meta_key] ?? '';
            }
        }
        
        if (!function_exists('wp_insert_post')) {
            function wp_insert_post($postarr) {
                static $post_id_counter = 456;
                return $post_id_counter++;
            }
        }
        
        if (!function_exists('get_post')) {
            function get_post($post_id) {
                return (object)[
                    'ID' => $post_id,
                    'post_type' => 'team',
                    'post_status' => 'publish',
                    'post_author' => 1
                ];
            }
        }
        
        if (!function_exists('wp_delete_post')) {
            function wp_delete_post($post_id, $force_delete = false) {
                return true;
            }
        }
        
        if (!function_exists('wp_delete_user')) {
            function wp_delete_user($user_id) {
                return true;
            }
        }
    }
    
    protected function tearDown(): void
    {
        parent::tearDown();
    }
    
    /**
     * ユーザー作成テスト
     */
    public function testUserCreation()
    {
        $user_id = wp_create_user('testuser', 'password123', 'test@example.com');
        $this->assertIsInt($user_id);
    }
    
    /**
     * ユーザーメタ保存テスト
     */
    public function testUserMetaSave()
    {
        $user_id = 1;
        $result = update_user_meta($user_id, 'test_key', 'test_value');
        $this->assertTrue($result);
    }
    
    /**
     * ユーザーメタ取得テスト
     */
    public function testUserMetaRetrieval()
    {
        $user_id = 1;
        // まず値を設定
        update_user_meta($user_id, 'registration_status', 'accepted');
        // その後取得
        $meta_value = get_user_meta($user_id, 'registration_status', true);
        $this->assertEquals('accepted', $meta_value);
    }
    
    /**
     * 投稿作成テスト
     */
    public function testPostCreation()
    {
        $post_id = wp_insert_post([
            'post_type' => 'team',
            'post_title' => 'テストチーム'
        ]);
        $this->assertIsInt($post_id);
    }
    
    /**
     * 投稿取得テスト
     */
    public function testPostRetrieval()
    {
        // まず投稿を作成
        $post_id = wp_insert_post([
            'post_type' => 'team',
            'post_title' => 'テストチーム'
        ]);
        
        // 作成した投稿を取得
        $post = get_post($post_id);
        $this->assertNotNull($post);
        $this->assertEquals('team', $post->post_type);
    }
}
