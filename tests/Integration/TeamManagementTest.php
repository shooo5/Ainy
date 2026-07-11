<?php
/**
 * チーム管理機能の統合テスト
 */

use PHPUnit\Framework\TestCase;

/**
 * チーム管理機能の統合テスト
 */
class TeamManagementTest extends TestCase
{
    protected $test_user_id;
    protected $test_team_id;
    protected $test_member_ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        
        // WordPressのテスト環境を初期化
        if (!function_exists('wp_insert_user')) {
            require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
            require_once dirname(__DIR__, 2) . '/tests/bootstrap-simple.php';
        }

        // テスト用ユーザーを作成
        $this->test_user_id = wp_create_user('test_leader', 'password123', 'leader@example.com');
        update_user_meta($this->test_user_id, 'registration_status', 'accepted');
        update_user_meta($this->test_user_id, 'test_user', 'true');
    }

    protected function tearDown(): void
    {
        // テストで作成したデータをクリーンアップ
        if ($this->test_team_id) {
            wp_delete_post($this->test_team_id, true);
        }
        
        foreach ($this->test_member_ids as $member_id) {
            wp_delete_user($member_id);
        }
        
        if ($this->test_user_id) {
            wp_delete_user($this->test_user_id);
        }
        
        parent::tearDown();
    }

    /**
     * チーム作成 - 正常パターン
     */
    public function testTeamCreationSuccess()
    {
        $team_data = [
            'team_name' => 'テストチーム',
            'team_description' => 'テスト用のチームです',
            'sport_type' => 'soccer',
            'team_category' => 'amateur',
            'team_type' => 'club',
            'team_gender_option' => 'mixed',
            'region' => 'tokyo',
            'registrant_name' => 'テスト代表者',
            'contact_mail' => 'contact@test.com',
            'contact_phone' => '090-1234-5678'
        ];

        $team_id = aidunite_register_team($this->test_user_id, $team_data);
        
        $this->assertNotFalse($team_id);
        $this->assertIsInt($team_id);
        
        // チーム情報の確認
        $team_post = get_post($team_id);
        $this->assertEquals('team', $team_post->post_type);
        $this->assertEquals('pending', $team_post->post_status);
        $this->assertEquals($this->test_user_id, $team_post->post_author);
        
        // メタデータの確認
        $this->assertEquals('テストチーム', get_post_meta($team_id, 'team_name', true));
        $this->assertEquals('soccer', get_post_meta($team_id, 'sport_type', true));
        $this->assertEquals('tokyo', get_post_meta($team_id, 'region', true));
        
        // 招待コードの確認
        $invite_code = get_post_meta($team_id, 'invite_code', true);
        $this->assertNotEmpty($invite_code);
        
        // ユーザーの役割確認（承認前は所属メタを付けない）
        $this->assertEquals('', (string) get_user_meta($this->test_user_id, 'aidunite_role', true));
        $this->assertEquals(0, (int) get_user_meta($this->test_user_id, 'team_id', true));
        $this->assertEquals($team_id, (int) get_user_meta($this->test_user_id, 'pending_team_id', true));
        
        $this->test_team_id = $team_id;
    }

    /**
     * チーム作成 - 異常パターン（必須項目不足）
     */
    public function testTeamCreationMissingRequiredFields()
    {
        $team_data = [
            'team_description' => 'テスト用のチームです',
            'sport_type' => 'soccer'
            // team_nameが不足
        ];

        $team_id = aidunite_register_team($this->test_user_id, $team_data);
        
        $this->assertFalse($team_id);
    }

    /**
     * チーム作成 - 異常パターン（無効なユーザーID）
     */
    public function testTeamCreationInvalidUserId()
    {
        $team_data = [
            'team_name' => 'テストチーム',
            'sport_type' => 'soccer'
        ];

        $team_id = aidunite_register_team(99999, $team_data);
        
        $this->assertFalse($team_id);
    }

    /**
     * チーム承認 - 正常パターン
     */
    public function testTeamApprovalSuccess()
    {
        // チーム作成
        $team_data = [
            'team_name' => '承認テストチーム',
            'sport_type' => 'soccer',
            'region' => 'tokyo'
        ];
        
        $team_id = aidunite_register_team($this->test_user_id, $team_data);
        $this->test_team_id = $team_id;

        // チーム承認
        $result = aidunite_approve_team($team_id);
        
        $this->assertTrue($result);
        
        // 承認後の状態確認
        $team_post = get_post($team_id);
        $this->assertEquals('publish', $team_post->post_status);
        $this->assertEquals('active', get_post_meta($team_id, 'team_status', true));
        $this->assertEquals('team_leader', get_user_meta($this->test_user_id, 'aidunite_role', true));
        $this->assertEquals($team_id, (int) get_user_meta($this->test_user_id, 'team_id', true));
    }

    /**
     * チーム承認 - 異常パターン（存在しないチーム）
     */
    public function testTeamApprovalNonexistentTeam()
    {
        $result = aidunite_approve_team(99999);
        
        $this->assertFalse($result);
    }

    /**
     * チーム編集 - 正常パターン
     */
    public function testTeamEditSuccess()
    {
        // チーム作成
        $team_data = [
            'team_name' => '編集前チーム名',
            'sport_type' => 'soccer',
            'region' => 'tokyo',
            'team_description' => '編集前の説明'
        ];
        
        $team_id = aidunite_register_team($this->test_user_id, $team_data);
        $this->test_team_id = $team_id;

        aidunite_approve_team($team_id);

        // 現在のユーザーを設定
        wp_set_current_user($this->test_user_id);

        // チーム編集
        $updated_data = [
            'team_id' => $team_id,
            'team_name' => '編集後チーム名',
            'team_description' => '編集後の説明',
            'region' => 'osaka'
        ];

        $result = aidunite_update_team($updated_data);
        
        $this->assertTrue($result);
        
        // 更新内容の確認
        $this->assertEquals('編集後チーム名', get_post_meta($team_id, 'team_name', true));
        $this->assertEquals('編集後の説明', get_post_meta($team_id, 'team_description', true));
        $this->assertEquals('osaka', get_post_meta($team_id, 'region', true));
    }

    /**
     * チーム編集 - 異常パターン（権限なし＝他チーム代表者で編集試行）
     */
    public function testTeamEditNoPermission()
    {
        $team_data = [
            'team_name' => '権限テストチーム',
            'sport_type' => 'soccer'
        ];
        $team_id = aidunite_register_team($this->test_user_id, $team_data);
        $this->test_team_id = $team_id;
        aidunite_approve_team($team_id);

        $other_user_id = wp_create_user('other_user', 'password123', 'other@example.com');
        update_user_meta($other_user_id, 'test_user', 'true');

        $updated_data = [
            'team_id' => $team_id,
            'team_name' => '権限なし編集'
        ];
        // 第二引数で「別ユーザーとして実行」→ 権限なしで false になること
        $result = aidunite_update_team($updated_data, $other_user_id);

        $this->assertFalse($result, '他チーム代表者では編集が拒否されること');

        wp_delete_user($other_user_id);
    }

    /**
     * チーム編集 - 異常パターン（team_id 欠落）
     */
    public function testTeamEditMissingTeamId()
    {
        wp_set_current_user($this->test_user_id);
        $result = aidunite_update_team(['team_name' => '無効']);
        $this->assertFalse($result, 'team_id が無い場合は false');
    }

    /**
     * チーム編集 - 異常パターン（存在しない team_id）
     */
    public function testTeamEditNonexistentTeam()
    {
        wp_set_current_user($this->test_user_id);
        $result = aidunite_update_team(['team_id' => 999999, 'team_name' => '無効']);
        $this->assertFalse($result, '存在しない team_id の場合は false');
    }

    /**
     * チーム削除 - 正常パターン
     */
    public function testTeamDeletionSuccess()
    {
        // チーム作成
        $team_data = [
            'team_name' => '削除テストチーム',
            'sport_type' => 'soccer'
        ];
        
        $team_id = aidunite_register_team($this->test_user_id, $team_data);
        $this->test_team_id = $team_id;
        aidunite_approve_team($team_id);

        // チームメンバーを追加
        $member_id = wp_create_user('test_member', 'password123', 'member@example.com');
        update_user_meta($member_id, 'team_id', $team_id);
        update_user_meta($member_id, 'aidunite_role', 'member');
        update_user_meta($member_id, 'test_user', 'true');
        $this->test_member_ids[] = $member_id;

        // 現在のユーザーを設定
        wp_set_current_user($this->test_user_id);

        // チーム削除
        $result = aidunite_delete_team($team_id);
        
        $this->assertTrue($result);
        
        // 削除後の確認
        $team_post = get_post($team_id);
        $this->assertNull($team_post);
        
        // メンバーの状態確認
        $this->assertEmpty(get_user_meta($member_id, 'team_id', true));
        $this->assertEquals('general', get_user_meta($member_id, 'aidunite_role', true));
        
        $this->test_team_id = null; // 削除済みなのでnullに
    }

    /**
     * チーム削除 - 異常パターン（権限なし＝他ユーザーで削除試行）
     */
    public function testTeamDeletionNoPermission()
    {
        $team_data = [
            'team_name' => '削除権限テストチーム',
            'sport_type' => 'soccer'
        ];
        $team_id = aidunite_register_team($this->test_user_id, $team_data);
        $this->test_team_id = $team_id;
        aidunite_approve_team($team_id);

        $other_user_id = wp_create_user('other_user', 'password123', 'other@example.com');
        update_user_meta($other_user_id, 'test_user', 'true');

        $result = aidunite_delete_team($team_id, $other_user_id);

        $this->assertFalse($result, '他ユーザーでは削除が拒否されること');

        wp_delete_user($other_user_id);
    }

    /**
     * チームメンバー追加 - 正常パターン
     */
    public function testTeamMemberAdditionSuccess()
    {
        // チーム作成
        $team_data = [
            'team_name' => 'メンバー追加テストチーム',
            'sport_type' => 'soccer'
        ];
        
        $team_id = aidunite_register_team($this->test_user_id, $team_data);
        $this->test_team_id = $team_id;
        aidunite_approve_team($team_id);

        // メンバー追加
        $member_id = wp_create_user('new_member', 'password123', 'newmember@example.com');
        update_user_meta($member_id, 'test_user', 'true');
        $this->test_member_ids[] = $member_id;

        $result = aidunite_add_team_member($team_id, $member_id);
        
        $this->assertTrue($result);
        
        // メンバー状態の確認
        $this->assertEquals($team_id, get_user_meta($member_id, 'team_id', true));
        $this->assertEquals('member', get_user_meta($member_id, 'aidunite_role', true));
    }

    /**
     * チームメンバー削除 - 正常パターン
     */
    public function testTeamMemberRemovalSuccess()
    {
        // チーム作成
        $team_data = [
            'team_name' => 'メンバー削除テストチーム',
            'sport_type' => 'soccer'
        ];
        
        $team_id = aidunite_register_team($this->test_user_id, $team_data);
        $this->test_team_id = $team_id;
        aidunite_approve_team($team_id);

        // メンバー追加
        $member_id = wp_create_user('remove_member', 'password123', 'removemember@example.com');
        update_user_meta($member_id, 'team_id', $team_id);
        update_user_meta($member_id, 'aidunite_role', 'member');
        update_user_meta($member_id, 'test_user', 'true');
        $this->test_member_ids[] = $member_id;

        // メンバー削除
        $result = aidunite_remove_team_member($team_id, $member_id);
        
        $this->assertTrue($result);
        
        // 削除後の状態確認
        $this->assertEmpty(get_user_meta($member_id, 'team_id', true));
        $this->assertEquals('general', get_user_meta($member_id, 'aidunite_role', true));
    }

    /**
     * チームメンバー一覧取得 - 正常パターン
     */
    public function testTeamMembersListSuccess()
    {
        // チーム作成
        $team_data = [
            'team_name' => 'メンバー一覧テストチーム',
            'sport_type' => 'soccer'
        ];
        
        $team_id = aidunite_register_team($this->test_user_id, $team_data);
        $this->test_team_id = $team_id;
        aidunite_approve_team($team_id);

        // メンバー追加
        $member1_id = wp_create_user('member1', 'password123', 'member1@example.com');
        $member2_id = wp_create_user('member2', 'password123', 'member2@example.com');
        
        update_user_meta($member1_id, 'team_id', $team_id);
        update_user_meta($member1_id, 'aidunite_role', 'member');
        update_user_meta($member2_id, 'team_id', $team_id);
        update_user_meta($member2_id, 'aidunite_role', 'member');
        
        update_user_meta($member1_id, 'test_user', 'true');
        update_user_meta($member2_id, 'test_user', 'true');
        
        $this->test_member_ids[] = $member1_id;
        $this->test_member_ids[] = $member2_id;

        // メンバー一覧取得
        $members = aidunite_get_team_members($team_id);
        
        $this->assertIsArray($members);
        $this->assertCount(3, $members); // 代表者 + メンバー2名
        
        // 代表者の確認
        $leader_found = false;
        foreach ($members as $member) {
            if ($member->ID == $this->test_user_id) {
                $this->assertEquals('team_leader', get_user_meta($member->ID, 'aidunite_role', true));
                $leader_found = true;
            }
        }
        $this->assertTrue($leader_found);
    }

    /**
     * チーム招待コード生成 - 正常パターン
     */
    public function testTeamInviteCodeGeneration()
    {
        // チーム作成
        $team_data = [
            'team_name' => '招待コードテストチーム',
            'sport_type' => 'soccer'
        ];
        
        $team_id = aidunite_register_team($this->test_user_id, $team_data);
        $this->test_team_id = $team_id;

        // 招待コード生成
        $invite_code = aidunite_generate_team_invite_code($team_id);
        
        $this->assertNotEmpty($invite_code);
        $this->assertIsString($invite_code);
        $this->assertEquals(8, strlen($invite_code)); // 8文字のコード
        
        // 保存されたコードとの一致確認
        $saved_code = get_post_meta($team_id, 'invite_code', true);
        $this->assertEquals($invite_code, $saved_code);
        
        // 再度生成しても同じコードが返されることを確認
        $invite_code2 = aidunite_generate_team_invite_code($team_id);
        $this->assertEquals($invite_code, $invite_code2);
    }

    /**
     * チーム招待処理 - 正常パターン
     */
    public function testTeamInvitationSuccess()
    {
        // チーム作成
        $team_data = [
            'team_name' => '招待テストチーム',
            'sport_type' => 'soccer'
        ];
        
        $team_id = aidunite_register_team($this->test_user_id, $team_data);
        $this->test_team_id = $team_id;
        aidunite_approve_team($team_id);

        $invite_code = get_post_meta($team_id, 'invite_code', true);

        // 招待処理
        $invited_user_id = wp_create_user('invited_user', 'password123', 'invited@example.com');
        update_user_meta($invited_user_id, 'test_user', 'true');
        $this->test_member_ids[] = $invited_user_id;

        $result = aidunite_process_team_invitation($invite_code, $invited_user_id);
        
        $this->assertTrue($result['success']);
        
        // 招待後の状態確認
        $this->assertEquals($team_id, get_user_meta($invited_user_id, 'team_id', true));
        $this->assertEquals('member', get_user_meta($invited_user_id, 'aidunite_role', true));
    }

    /**
     * チーム招待処理 - 異常パターン（無効なコード）
     */
    public function testTeamInvitationInvalidCode()
    {
        $invited_user_id = wp_create_user('invited_user', 'password123', 'invited@example.com');
        update_user_meta($invited_user_id, 'test_user', 'true');
        $this->test_member_ids[] = $invited_user_id;

        $result = aidunite_process_team_invitation('INVALID', $invited_user_id);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('無効な招待コード', $result['message']);
    }

    /**
     * チーム情報取得 - 正常パターン
     */
    public function testTeamInfoRetrieval()
    {
        // チーム作成
        $team_data = [
            'team_name' => '情報取得テストチーム',
            'team_description' => 'テスト用のチームです',
            'sport_type' => 'soccer',
            'region' => 'tokyo'
        ];
        
        $team_id = aidunite_register_team($this->test_user_id, $team_data);
        $this->test_team_id = $team_id;

        // チーム情報取得
        $team_info = aidunite_get_team_info($team_id);
        
        $this->assertIsArray($team_info);
        $this->assertEquals('情報取得テストチーム', $team_info['team_name']);
        $this->assertEquals('テスト用のチームです', $team_info['team_description']);
        $this->assertEquals('soccer', $team_info['sport_type']);
        $this->assertEquals('tokyo', $team_info['region']);
        $this->assertEquals($this->test_user_id, $team_info['leader_id']);
    }

    /**
     * チーム検索 - 正常パターン
     */
    public function testTeamSearch()
    {
        // 複数のチームを作成
        $team_data1 = [
            'team_name' => '検索テストチームA',
            'sport_type' => 'soccer',
            'region' => 'tokyo'
        ];
        
        $team_data2 = [
            'team_name' => '検索テストチームB',
            'sport_type' => 'basketball',
            'region' => 'osaka'
        ];
        
        $team1_id = aidunite_register_team($this->test_user_id, $team_data1);
        aidunite_approve_team($team1_id);

        $team2_id = aidunite_register_team($this->test_user_id, $team_data2);

        $this->test_team_id = $team2_id;

        // スポーツ種別で検索
        $soccer_teams = aidunite_search_teams(['sport_type' => 'soccer']);
        $this->assertIsArray($soccer_teams);
        $this->assertGreaterThan(0, count($soccer_teams));
        
        // 地域で検索
        $tokyo_teams = aidunite_search_teams(['region' => 'tokyo']);
        $this->assertIsArray($tokyo_teams);
        $this->assertGreaterThan(0, count($tokyo_teams));
        
        // 複数条件で検索
        $filtered_teams = aidunite_search_teams([
            'sport_type' => 'soccer',
            'region' => 'tokyo'
        ]);
        $this->assertIsArray($filtered_teams);
        $this->assertGreaterThan(0, count($filtered_teams));

        wp_delete_post($team1_id, true);
        wp_delete_post($team2_id, true);
        $this->test_team_id = null;
    }
} 