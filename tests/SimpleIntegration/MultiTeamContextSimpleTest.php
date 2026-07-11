<?php
/**
 * マルチチーム文脈の SimpleIntegration スタブ（P1-04）
 * 本番 team-context.php を読み込み、フィクスチャ生成のみ検証。
 */

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/_support/MultiTeamTestFixture.php';

class MultiTeamContextSimpleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!function_exists('get_current_user_id')) {
            require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
            require_once dirname(__DIR__, 2) . '/tests/bootstrap-simple.php';
        }
        $GLOBALS['test_users'] = [];
        $GLOBALS['test_user_id'] = 1;
        $GLOBALS['test_meta_data'] = [];
        $GLOBALS['test_posts'] = [];
        $GLOBALS['test_post_meta'] = [];
    }

    public function testDualTeamFixtureProducesTwoTeams(): void
    {
        $seed = MultiTeamTestFixture::seed_dual_team_leader();
        $this->assertGreaterThan(0, $seed['leader_id']);
        $this->assertGreaterThan(0, $seed['team_a']);
        $this->assertGreaterThan(0, $seed['team_b']);
        $this->assertNotSame($seed['team_a'], $seed['team_b']);

        $managed_raw = get_user_meta($seed['leader_id'], 'managed_team_ids', true);
        $managed = json_decode((string) $managed_raw, true);
        $this->assertIsArray($managed);
        $this->assertCount(2, $managed);
    }

    public function testOperatingTeamSwitchUpdatesCurrentTeam(): void
    {
        $seed = MultiTeamTestFixture::seed_dual_team_leader();
        wp_set_current_user($seed['leader_id']);

        $this->assertSame($seed['team_a'], (int) aidunite_get_current_team_id($seed['leader_id']));

        $switched = aidunite_set_current_operating_team_id($seed['leader_id'], $seed['team_b']);
        $this->assertTrue($switched);
        $this->assertSame($seed['team_b'], (int) aidunite_get_current_team_id($seed['leader_id']));
    }

    public function testManagedTeamIdsRejectUnknownTeamSwitch(): void
    {
        $seed = MultiTeamTestFixture::seed_dual_team_leader();
        $unknown_team_id = max($seed['team_a'], $seed['team_b']) + 1000;

        $switched = aidunite_set_current_operating_team_id($seed['leader_id'], $unknown_team_id);
        $this->assertFalse($switched);
        $this->assertSame($seed['team_a'], (int) get_user_meta($seed['leader_id'], 'current_operating_team_id', true));
    }

    public function testManualChecklistIsDocumented(): void
    {
        $this->assertNotEmpty(MultiTeamTestFixture::manual_checklist());
    }
}
