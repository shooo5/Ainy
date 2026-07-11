<?php
/**
 * Normalize service の単体テスト（辞書・normalize-service.php 準拠）
 *
 * @group normalize
 */
class NormalizeServiceTest extends PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!function_exists('aidunite_normalize_team_org_type_value')) {
            self::markTestSkipped('normalize-service.php が bootstrap で読み込まれていません');
        }
    }

    public function test_normalize_team_org_type_value(): void
    {
        foreach (NormalizeTestHelper::teamOrgTypeSamples() as $label => $sample) {
            $actual = aidunite_normalize_team_org_type_value($sample['raw']);
            NormalizeTestHelper::assertCanonicalTeamOrgType($sample['canonical'], $actual, $label);
        }
    }

    public function test_normalize_match_request_status_read(): void
    {
        foreach (NormalizeTestHelper::matchRequestStatusReadSamples() as $label => $sample) {
            $actual = aidunite_normalize_match_request_status($sample['raw'], '');
            NormalizeTestHelper::assertCanonicalMrStatusRead($sample['read'], $actual, $label);
        }
    }

    public function test_normalize_match_request_status_save(): void
    {
        foreach (NormalizeTestHelper::matchRequestStatusSaveSamples() as $label => $sample) {
            $actual = aidunite_normalize_match_request_status_value($sample['raw'], '', true);
            $this->assertSame($sample['saved'], $actual, $label);
        }
    }

    public function test_normalize_notification_type_legacy_alias(): void
    {
        foreach (NormalizeTestHelper::notificationTypeAliasSamples() as $label => $sample) {
            $actual = aidunite_normalize_notification_type_value($sample['raw']);
            $this->assertSame($sample['canonical'], $actual, $label);
        }
    }

    public function test_normalize_user_registration_status_active(): void
    {
        $out = aidunite_normalize_user_payload(['registration_status' => 'active']);
        $this->assertSame('accepted', $out['registration_status']);
    }

    public function test_normalize_schedule_payload_place_both_to_either(): void
    {
        $out = aidunite_normalize_schedule_payload([
            'intent' => 'recruit',
            'place_type' => 'both',
        ]);
        $this->assertSame('either', $out['place_type']);
        $this->assertSame('either', $out['venue_condition']);
        $this->assertSame('either', $out['schedule_place']);
        $this->assertSame(1, $out['is_match_requested']);
    }
}
