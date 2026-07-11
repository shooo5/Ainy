<?php
/**
 * Normalize service テスト用ヘルパー（期待値サンプルと共通アサーション）
 */

class NormalizeTestHelper
{
    /**
     * 辞書準拠の team_type サンプル
     *
     * @return array<string, array{raw: string, canonical: string}>
     */
    public static function teamOrgTypeSamples(): array
    {
        return [
            'school_ja' => ['raw' => '学校', 'canonical' => 'school'],
            'club_ja' => ['raw' => 'クラブ', 'canonical' => 'club'],
            'school_en' => ['raw' => 'school', 'canonical' => 'school'],
        ];
    }

    /**
     * MR status 読取正規化サンプル（保存前）
     *
     * @return array<string, array{raw: string, read: string}>
     */
    public static function matchRequestStatusReadSamples(): array
    {
        return [
            'legacy_publish' => ['raw' => 'publish', 'read' => 'pending'],
            'ja_pending' => ['raw' => '申請中', 'read' => 'pending'],
            'en_established' => ['raw' => 'established', 'read' => 'established'],
        ];
    }

    /**
     * MR status 保存正規化サンプル（for_save=true）
     *
     * @return array<string, array{raw: string, saved: string}>
     */
    public static function matchRequestStatusSaveSamples(): array
    {
        return [
            'accepted_to_established' => ['raw' => 'accepted', 'saved' => 'established'],
            'pending_unchanged' => ['raw' => 'pending', 'saved' => 'pending'],
        ];
    }

    /**
     * 通知 type レガシーエイリアス
     *
     * @return array<string, array{raw: string, canonical: string}>
     */
    public static function notificationTypeAliasSamples(): array
    {
        return [
            'match_accepted' => ['raw' => 'match_accepted', 'canonical' => 'match_established'],
            'team_approved' => ['raw' => 'team_approved', 'canonical' => 'team_approval_completed'],
        ];
    }

    public static function assertCanonicalTeamOrgType(string $expected, string $actual, string $label = ''): void
    {
        PHPUnit\Framework\Assert::assertSame($expected, $actual, $label);
    }

    public static function assertCanonicalMrStatusRead(string $expected, string $actual, string $label = ''): void
    {
        PHPUnit\Framework\Assert::assertSame($expected, $actual, $label);
    }
}
