<?php
/**
 * 基本的なユーザー関数のテスト
 */
class UserFunctionsTest extends PHPUnit\Framework\TestCase
{
    /**
     * テストの前処理
     */
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * テストの後処理
     */
    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * 基本的なテスト：PHPUnitが動作しているか確認
     */
    public function testPhpunitIsWorking()
    {
        $this->assertTrue(true, 'PHPUnit is working correctly');
    }

    /**
     * 文字列のテスト
     */
    public function testStringComparison()
    {
        $expected = 'AidUnite';
        $actual = 'AidUnite';
        $this->assertEquals($expected, $actual);
    }

    /**
     * 配列のテスト
     */
    public function testArrayHasKey()
    {
        $array = ['user_id' => 1, 'user_name' => 'Test User'];
        $this->assertArrayHasKey('user_id', $array);
        $this->assertArrayHasKey('user_name', $array);
    }
}

