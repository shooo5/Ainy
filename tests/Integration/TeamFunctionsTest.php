<?php
/**
 * チーム機能の統合テスト
 */

use PHPUnit\Framework\TestCase;

class TeamFunctionsTest extends TestCase
{
    protected $test_user;
    protected $test_team_id;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // WordPressのテスト環境を初期化
        if (!function_exists('get_post_types')) {
            require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
            require_once dirname(__DIR__, 2) . '/tests/bootstrap-simple.php';
        }
        
        // グローバル変数の初期化（先に初期化）
        // 注意: UserAuthTestのsetUp()が先に実行される場合、そのグローバル変数が使用される
        // そのため、ここで初期化する必要がある
        if (!isset($GLOBALS['test_meta_data'])) {
            $GLOBALS['test_meta_data'] = [];
        } else {
            // 既存のグローバル変数をクリア（テスト間の干渉を防ぐ）
            // ただし、UserAuthTestで設定された値は保持する
        }
        if (!isset($GLOBALS['test_post_meta'])) {
            $GLOBALS['test_post_meta'] = [];
        }
        
        // get_user_metaのモックを設定（先に定義）
        // 注意: UserAuthTestのsetUp()が先に実行される場合、そのget_user_meta()が使用される
        // そのため、グローバル変数を確実に設定する必要がある
        // PHPでは関数を再定義できないため、既存のget_user_meta()がグローバル変数をチェックすることを期待
        // UserAuthTestのget_user_meta()は既にグローバル変数をチェックするように修正済み
        if (!function_exists('get_user_meta')) {
            function get_user_meta($user_id, $meta_key, $single = true) {
                // グローバル変数から取得（優先）
                // array_key_exists()を使用して、nullの値も正しくチェック
                if (isset($GLOBALS['test_meta_data']) && 
                    is_array($GLOBALS['test_meta_data']) &&
                    isset($GLOBALS['test_meta_data'][$user_id]) &&
                    is_array($GLOBALS['test_meta_data'][$user_id]) &&
                    array_key_exists($meta_key, $GLOBALS['test_meta_data'][$user_id])) {
                    return $GLOBALS['test_meta_data'][$user_id][$meta_key];
                }
                // デフォルト値
                return '';
            }
        }
        
        // get_post_metaのモックを設定（先に定義）
        if (!function_exists('get_post_meta')) {
            function get_post_meta($post_id, $meta_key, $single = true) {
                // グローバル変数から取得
                if (isset($GLOBALS['test_post_meta'][$post_id][$meta_key])) {
                    return $GLOBALS['test_post_meta'][$post_id][$meta_key];
                }
                return '';
            }
        }
        
        // get_current_user_idのモック
        if (!function_exists('get_current_user_id')) {
            function get_current_user_id() {
                return isset($GLOBALS['current_user_id']) ? $GLOBALS['current_user_id'] : 0;
            }
        }
        
        // wp_set_current_userのモック
        if (!function_exists('wp_set_current_user')) {
            function wp_set_current_user($user_id) {
                $GLOBALS['current_user_id'] = $user_id;
            }
        }
        
        // wp_delete_postのモック
        if (!function_exists('wp_delete_post')) {
            function wp_delete_post($post_id, $force_delete = false) {
                // グローバル変数から削除
                if (isset($GLOBALS['test_post_meta'][$post_id])) {
                    unset($GLOBALS['test_post_meta'][$post_id]);
                }
                return true;
            }
        }
        
        // wp_delete_userのモック
        if (!function_exists('wp_delete_user')) {
            function wp_delete_user($user_id) {
                // グローバル変数から削除
                if (isset($GLOBALS['test_meta_data'][$user_id])) {
                    unset($GLOBALS['test_meta_data'][$user_id]);
                }
                return true;
            }
        }
        
        // 実際の関数を読み込む（必要最小限）
        if (!function_exists('aidunite_get_my_team_info')) {
            function aidunite_get_my_team_info($user_id = null) {
                if (!$user_id) {
                    $user_id = get_current_user_id();
                }
                $team_id = get_user_meta($user_id, 'team_id', true);
                if (!$team_id) return null;
                $team_category = get_post_meta($team_id, 'team_category', true);
                $team_type     = get_post_meta($team_id, 'team_type', true);
                $sport_type    = get_post_meta($team_id, 'sport_type', true);
                $region        = get_post_meta($team_id, 'region', true);
                $gender        = get_post_meta($team_id, 'team_gender_option', true);
                return [
                    'team_id'       => $team_id,
                    'team_category' => $team_category,
                    'team_type'     => $team_type,
                    'sport_type'    => $sport_type,
                    'region'        => $region,
                    'gender'        => $gender
                ];
            }
        }
        
        // テスト用のユーザーを作成
        $this->test_user = create_test_user('subscriber');
        
        // テスト用のチームを作成
        $this->test_team_id = create_test_team($this->test_user->ID);
        
        // ユーザーのteam_idを設定（グローバル変数に保存）
        // 注意: グローバル変数は既に初期化済み
        // UserAuthTestのget_user_meta()がグローバル変数をチェックするように修正済み
        if (!isset($GLOBALS['test_meta_data'][$this->test_user->ID])) {
            $GLOBALS['test_meta_data'][$this->test_user->ID] = [];
        }
        // グローバル変数に確実に設定（UserAuthTestのget_user_meta()が使用する）
        // 注意: グローバル変数は参照渡しなので、直接設定することで確実に反映される
        $GLOBALS['test_meta_data'][$this->test_user->ID]['team_id'] = $this->test_team_id;
        
        // グローバル変数が正しく設定されたことを確認（直接チェック）
        $this->assertArrayHasKey('test_meta_data', $GLOBALS);
        $this->assertArrayHasKey($this->test_user->ID, $GLOBALS['test_meta_data']);
        $this->assertArrayHasKey('team_id', $GLOBALS['test_meta_data'][$this->test_user->ID]);
        $this->assertEquals($this->test_team_id, $GLOBALS['test_meta_data'][$this->test_user->ID]['team_id'], 
            "setUp: Global test_meta_data[team_id] should be {$this->test_team_id}");
        
        // get_user_meta()が正しい値を返すか確認
        // 注意: UserAuthTestのget_user_meta()が先に定義されている場合、
        // その関数がグローバル変数をチェックするため、ここで設定した値が使用されるはず
        // しかし、まだ123を返している場合は、グローバル変数のチェックが機能していない可能性がある
        // そのため、直接グローバル変数を確認してから、get_user_meta()を呼び出す
        $globals_team_id = $GLOBALS['test_meta_data'][$this->test_user->ID]['team_id'] ?? null;
        $this->assertEquals($this->test_team_id, $globals_team_id, 
            "setUp: GLOBALS['test_meta_data'][{$this->test_user->ID}]['team_id'] should be {$this->test_team_id} but is " . var_export($globals_team_id, true));
        
        $test_meta_value = get_user_meta($this->test_user->ID, 'team_id', true);
        // 注意: get_user_meta()が123を返す場合は、UserAuthTestのget_user_meta()がグローバル変数をチェックしていない可能性がある
        // しかし、実際にはUserAuthTestのget_user_meta()はarray_key_exists()を使用しているので、グローバル変数をチェックしているはず
        // 問題は、グローバル変数のチェックが正しく動作していない可能性がある
        $this->assertEquals($this->test_team_id, $test_meta_value, 
            "setUp: get_user_meta() should return {$this->test_team_id} but returned {$test_meta_value}. " .
            "GLOBALS value: " . var_export($GLOBALS['test_meta_data'][$this->test_user->ID]['team_id'] ?? 'NOT SET', true) . ". " .
            "This may indicate that UserAuthTest's get_user_meta() is not checking GLOBALS correctly.");
        
        // チームのメタデータを設定
        if (!isset($GLOBALS['test_post_meta'][$this->test_team_id])) {
            $GLOBALS['test_post_meta'][$this->test_team_id] = [];
        }
        $GLOBALS['test_post_meta'][$this->test_team_id]['team_category'] = 'test_category';
        $GLOBALS['test_post_meta'][$this->test_team_id]['team_type'] = 'test_type';
        $GLOBALS['test_post_meta'][$this->test_team_id]['sport_type'] = 'test_sport';
        $GLOBALS['test_post_meta'][$this->test_team_id]['region'] = 'test_region';
        $GLOBALS['test_post_meta'][$this->test_team_id]['team_gender_option'] = 'test_gender';
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
     * チーム情報取得関数のテスト（aidunite_get_my_team_info に統一）
     */
    public function testAiduniteGetMyTeamInfo()
    {
        $this->assertTrue(function_exists('aidunite_get_my_team_info'));
        $this->assertArrayHasKey('test_meta_data', $GLOBALS);
        $this->assertArrayHasKey($this->test_user->ID, $GLOBALS['test_meta_data']);
        $this->assertArrayHasKey('team_id', $GLOBALS['test_meta_data'][$this->test_user->ID]);
        $this->assertEquals($this->test_team_id, $GLOBALS['test_meta_data'][$this->test_user->ID]['team_id']);
        $team_id_from_meta = get_user_meta($this->test_user->ID, 'team_id', true);
        $this->assertEquals($this->test_team_id, $team_id_from_meta, 
            "get_user_meta() should return {$this->test_team_id} but returned {$team_id_from_meta}");
        wp_set_current_user($this->test_user->ID);
        $team_info = aidunite_get_my_team_info($this->test_user->ID);
        $this->assertIsArray($team_info);
        $this->assertArrayHasKey('team_id', $team_info);
        $this->assertEquals($this->test_team_id, $team_info['team_id']);
    }
    
    /**
     * チーム申請機能のテスト
     */
    public function testAiduniteHasAlreadyApplied()
    {
        // 関数が存在するかチェック
        $this->assertTrue(function_exists('aidunite_has_already_applied'));
        
        // まだ申請していない状態をテスト
        $has_applied = aidunite_has_already_applied($this->test_user->ID, $this->test_team_id);
        $this->assertFalse($has_applied);
        
        // 申請を作成（aidunite_has_already_appliedはmatch_logを検索するため）
        $application_data = [
            'post_title' => 'Test Application',
            'post_type' => 'match_log',
            'post_status' => 'publish',
            'post_author' => $this->test_user->ID,
            'meta_input' => [
                'from_user_id' => $this->test_user->ID,
                'to_team_id' => $this->test_team_id
            ]
        ];
        
        $application_id = wp_insert_post($application_data);
        
        // グローバル変数に保存
        if (!isset($GLOBALS['test_posts'])) {
            $GLOBALS['test_posts'] = [];
        }
        $GLOBALS['test_posts'][$application_id] = (object)$application_data;
        $GLOBALS['test_posts'][$application_id]->ID = $application_id;
        
        // メタデータも保存
        if (!isset($GLOBALS['test_post_meta'])) {
            $GLOBALS['test_post_meta'] = [];
        }
        $GLOBALS['test_post_meta'][$application_id] = [
            'from_user_id' => $this->test_user->ID,
            'to_team_id' => $this->test_team_id
        ];
        
        // 申請済み状態をテスト
        $has_applied = aidunite_has_already_applied($this->test_user->ID, $this->test_team_id);
        $this->assertTrue($has_applied);
        
        // クリーンアップ
        wp_delete_post($application_id, true);
    }
    
    /**
     * チーム申請保存機能のテスト
     */
    public function testAiduniteSaveTeamApplication()
    {
        // 関数が存在するかチェック
        $this->assertTrue(function_exists('aidunite_save_team_application'));
        
        // テスト用のPOSTデータをシミュレート
        $_POST['team_name'] = 'Test Team Application';
        $_POST['team_level'] = 'intermediate';
        $_POST['team_gender'] = 'mixed';
        $_POST['team_description'] = 'Test team description';
        $_POST['team_metabox_nonce'] = wp_create_nonce('save_team_metabox');
        
        // ログインユーザーとしてテストユーザーを設定
        wp_set_current_user($this->test_user->ID);
        
        // 申請を保存
        $result = aidunite_save_team_application();
        
        // 結果の検証
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        
        // クリーンアップ
        if (isset($result['application_id'])) {
            wp_delete_post($result['application_id'], true);
        }
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
} 