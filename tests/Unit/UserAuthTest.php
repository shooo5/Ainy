<?php

use PHPUnit\Framework\TestCase;

/**
 * ユーザー認証機能の単体テスト
 */
class UserAuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // PROJECT_ROOTが定義されていない場合は定義
        if (!defined('PROJECT_ROOT')) {
            define('PROJECT_ROOT', dirname(__DIR__, 2));
        }
        
        // グローバル変数を強制的にリセット（他のテストの影響を排除）
        $GLOBALS['test_users'] = [];
        $GLOBALS['test_user_id'] = 1;
        $GLOBALS['test_meta_data'] = [];
        
        // WordPress関数のモック化
        if (!function_exists('wp_create_user')) {
            function wp_create_user($username, $password, $email) {
                return 1;
            }
        }

            // wp_insert_userはbootstrap-simple.phpで定義されているため、ここでは再定義しない

        if (!function_exists('update_user_meta')) {
            function update_user_meta($user_id, $meta_key, $meta_value) {
                // グローバル変数にメタデータを保存
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
                // グローバル変数を優先チェック（他のテストクラスで設定された値を尊重）
                // 注意: isset()の前に、配列が存在するか確認
                // グローバル変数が存在し、配列であり、ユーザーIDが存在し、その配列であり、メタキーが存在する場合
                // デバッグ: グローバル変数の状態を確認
                $has_globals = isset($GLOBALS['test_meta_data']);
                $is_array = $has_globals && is_array($GLOBALS['test_meta_data']);
                $has_user_id = $is_array && isset($GLOBALS['test_meta_data'][$user_id]);
                $is_user_array = $has_user_id && is_array($GLOBALS['test_meta_data'][$user_id]);
                $has_key = $is_user_array && array_key_exists($meta_key, $GLOBALS['test_meta_data'][$user_id]);
                
                if ($has_key) {
                    // グローバル変数から値を取得
                    $value = $GLOBALS['test_meta_data'][$user_id][$meta_key];
                    // 空文字列やnullの場合は、デフォルト値を使用しない（グローバル変数の値を優先）
                    return $value;
                }
                // デフォルト値（グローバル変数が設定されていない場合のみ）
                $mock_data = [
                    'registration_status' => 'accepted',
                    'team_id' => 123,
                    'aidunite_role' => 'team_leader'
                ];
                return $mock_data[$meta_key] ?? 'test_value';
            }
        }

        if (!function_exists('get_user_by')) {
            function get_user_by($field, $value) {
                // グローバル変数からユーザーを検索
                if (isset($GLOBALS['test_users']) && is_array($GLOBALS['test_users'])) {
                    foreach ($GLOBALS['test_users'] as $user_id => $user) {
                        if ($field === 'email' && isset($user->user_email) && $user->user_email === $value) {
                            return $user;
                        }
                        if ($field === 'login' && isset($user->user_login) && $user->user_login === $value) {
                            return $user;
                        }
                        if ($field === 'id' && isset($user->ID) && $user->ID == $value) {
                            return $user;
                        }
                    }
                }
                // デフォルト値（見つからない場合のみ）
                return (object)[
                    'ID' => 1,
                    'user_login' => 'testuser',
                    'user_email' => 'test@example.com',
                    'display_name' => 'テストユーザー'
                ];
            }
        }
        
        if (!function_exists('get_userdata')) {
            function get_userdata($user_id) {
                // グローバル変数からユーザーを検索
                if (isset($GLOBALS['test_users']) && isset($GLOBALS['test_users'][$user_id])) {
                    return $GLOBALS['test_users'][$user_id];
                }
                // デフォルト値
                return (object)[
                    'ID' => $user_id,
                    'user_login' => 'testuser',
                    'user_email' => 'test@example.com',
                    'display_name' => 'テストユーザー'
                ];
            }
        }

        if (!function_exists('wp_delete_user')) {
            function wp_delete_user($user_id) {
                return true;
            }
        }

        if (!function_exists('get_users')) {
            function get_users($args) {
                return [];
            }
        }
        

        // その他の必要な関数のモック（実際の関数を読み込む前に定義）
        if (!function_exists('is_email')) {
            function is_email($email) {
                return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
            }
        }
        
        if (!function_exists('email_exists')) {
            function email_exists($email) {
                return false; // テストでは重複なしと仮定
            }
        }
        
        if (!function_exists('sanitize_email')) {
            function sanitize_email($email) {
                return filter_var($email, FILTER_SANITIZE_EMAIL);
            }
        }
        
        if (!function_exists('sanitize_text_field')) {
            function sanitize_text_field($text) {
                return trim(strip_tags($text));
            }
        }
        
        if (!function_exists('current_time')) {
            function current_time($type = 'mysql') {
                return date('Y-m-d H:i:s');
            }
        }
        
        if (!function_exists('home_url')) {
            function home_url($path = '') {
                return 'http://localhost' . $path;
            }
        }
        
        if (!function_exists('add_query_arg')) {
            function add_query_arg($key, $value = null, $url = null) {
                if ($url === null) {
                    $url = home_url();
                }
                if (is_array($key)) {
                    $query_string = http_build_query($key);
                } else {
                    $query_string = $key . '=' . urlencode($value);
                }
                $separator = (strpos($url, '?') !== false) ? '&' : '?';
                return $url . $separator . $query_string;
            }
        }
        
        if (!function_exists('get_option')) {
            function get_option($option, $default = false) {
                // テスト用のデフォルト値を返す
                $test_options = [
                    'admin_email' => 'admin@example.com',
                    'blogname' => 'Test Site',
                    'siteurl' => 'http://localhost'
                ];
                return $test_options[$option] ?? $default;
            }
        }
        
        if (!function_exists('update_option')) {
            function update_option($option, $value) {
                return true;
            }
        }
        
        // 実際の関数を読み込む（functions/member/register-functions.php）
        // 注意: bootstrap-simple.phpでモック関数が定義されている場合は、実際の関数は読み込まない
        // モック関数が既に定義されている場合は、実際の関数ファイルを読み込まない
        if (!function_exists('aidunite_register_member') && file_exists(PROJECT_ROOT . '/functions/member/register-functions.php')) {
            require_once PROJECT_ROOT . '/functions/member/register-functions.php';
        }
        
        // 実際の関数が存在しない場合のみモック関数を定義
        if (!function_exists('aidunite_send_activation_email')) {
            function aidunite_send_activation_email($user_id, $registration_data, $token) {
                return true; // テストではメール送信をスキップ
            }
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * ユーザー登録 - 正常パターン
     */
    public function testUserRegistrationSuccess()
    {
        $registration_data = [
            'user_email' => 'test@example.com',
            'password' => 'testpassword123',
            'user_name' => 'テストユーザー',
            'first_name' => 'テスト',
            'last_name' => 'ユーザー',
            'agree_terms' => '1' // 必須フィールド
        ];

        $result = aidunite_register_member($registration_data);
        
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('user_id', $result);
        
        // ユーザーが正しく作成されているか確認
        $user = get_user_by('email', 'test@example.com');
        $this->assertNotNull($user);
        $this->assertEquals('ユーザー テスト', $user->display_name);
        
        // グローバル変数に値が保存されているか確認
        // 注意: $result['user_id']を使用して、実際に作成されたユーザーIDを確認
        $created_user_id = $result['user_id'];
        $this->assertArrayHasKey('test_meta_data', $GLOBALS);
        $this->assertArrayHasKey($created_user_id, $GLOBALS['test_meta_data'], 
            "test_meta_data should have key {$created_user_id}. Available keys: " . 
            implode(', ', array_keys($GLOBALS['test_meta_data'] ?? [])));
        $this->assertArrayHasKey('registration_status', $GLOBALS['test_meta_data'][$created_user_id]);
        $this->assertEquals('pending', $GLOBALS['test_meta_data'][$created_user_id]['registration_status']);
        
        // get_user_meta()が正しい値を返すか確認
        $registration_status = get_user_meta($created_user_id, 'registration_status', true);
        $this->assertEquals('pending', $registration_status, 
            "Expected 'pending' but got '{$registration_status}'. " .
            "GLOBALS value: " . var_export($GLOBALS['test_meta_data'][$created_user_id]['registration_status'] ?? 'NOT SET', true));
    }

    /**
     * ユーザー登録 - 異常パターン（無効なメールアドレス）
     */
    public function testUserRegistrationInvalidEmail()
    {
        $registration_data = [
            'user_email' => 'invalid-email',
            'password' => 'testpassword123',
            'user_name' => 'テストユーザー',
            'agree_terms' => '1'
        ];

        $result = aidunite_register_member($registration_data);
        
        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('errors', $result);
        $this->assertNotEmpty($result['errors']);
        // エラーメッセージに「メールアドレス」が含まれているか確認
        $has_email_error = false;
        foreach ($result['errors'] as $error) {
            if (strpos($error, 'メールアドレス') !== false) {
                $has_email_error = true;
                break;
            }
        }
        $this->assertTrue($has_email_error, 'エラーメッセージに「メールアドレス」が含まれていること');
    }

    /**
     * ユーザー登録 - 異常パターン（短いパスワード）
     */
    public function testUserRegistrationShortPassword()
    {
        $registration_data = [
            'user_email' => 'test@example.com',
            'password' => '123',
            'user_name' => 'テストユーザー',
            'agree_terms' => '1'
        ];

        $result = aidunite_register_member($registration_data);
        
        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('errors', $result);
        $this->assertNotEmpty($result['errors']);
        // エラーメッセージに「パスワード」が含まれているか確認
        $has_password_error = false;
        foreach ($result['errors'] as $error) {
            if (strpos($error, 'パスワード') !== false) {
                $has_password_error = true;
                break;
            }
        }
        $this->assertTrue($has_password_error, 'エラーメッセージに「パスワード」が含まれていること');
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
        // テスト用の値を明示的に設定
        update_user_meta($user_id, 'registration_status', 'accepted');
        $meta_value = get_user_meta($user_id, 'registration_status', true);
        
        $this->assertEquals('accepted', $meta_value);
    }
} 