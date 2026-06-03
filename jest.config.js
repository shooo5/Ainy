/**
 * Jest設定ファイル
 * AidUniteプロジェクト用のJest設定
 */

module.exports = {
    // テスト環境
    testEnvironment: 'jsdom',
    
    // テストファイルのパターン
    testMatch: [
        '**/tests/js/**/*.test.js',
        '**/tests/js/**/*.spec.js'
    ],
    
    // モジュールのパス解決
    moduleNameMapper: {
        '^@/(.*)$': '<rootDir>/$1',
        '^@js/(.*)$': '<rootDir>/js/$1',
        '^@tests/(.*)$': '<rootDir>/tests/$1',
        '^@archive/js/(.*)$': '<rootDir>/docs/archive/js/$1'
    },
    
    // カバレッジ設定（実際にテストされているファイルのみを含める）
    collectCoverageFrom: [
        // テストされているファイルのみを明示的に指定
        'js/common/ajax-utils.js',
        'js/common/date-utils.js',
        'js/common/dom-utils.js',
        'js/common/form-utils.js',
        'js/common/schedule-loader.js',
        'js/common/schedule-modal.js',
        'js/common/schedule-utils.js',
        'js/form-notifications.js',
        'js/loading-spinner-utils.js',
        'js/match/match-actions.js',
        'js/match/match-board-button-control.js',
        'js/pages/profile-edit.js',
        'js/schedule/schedule-edit.js',
        'js/schedule/schedule-management.js',
        'js/schedule/schedule.js',
        'js/team/team-registration-wizard.js',
        'js/toast-notification.js',
        // 除外パターン（念のため）
        '!js/**/*.min.js',
        '!js/vendor/**',
        '!js/tests/**',
        '!js/**/*.test.js',
        '!js/**/*.spec.js',
        '!js/node_modules/**',
        '!node_modules/**'
    ],
    
    // カバレッジを強制的に収集しない（--coverageフラグ使用時のみ収集）
    collectCoverage: false,
    
    // カバレッジ収集時の設定
    coveragePathIgnorePatterns: [
        '/node_modules/',
        '/tests/',
        '/vendor/',
        '\\.min\\.js$',
        'simple-test\\.js$',
        'ui-test-automation\\.js$',
        'animation-examples\\.js$'
    ],
    
    // カバレッジレポートの出力先
    coverageDirectory: 'tests/coverage',
    
    // カバレッジレポート形式
    // textのみを使用することで、個別ファイルのカバレッジが表示される（以前と同じ表示）
    coverageReporters: ['text', 'lcov', 'html'],
    
    // カバレッジの閾値
    coverageThreshold: {
        global: {
            branches: 70,
            functions: 70,
            lines: 70,
            statements: 70
        }
    },
    
    // セットアップファイル
    setupFilesAfterEnv: ['<rootDir>/tests/js/setup.js'],
    
    // モックファイルのパス
    moduleFileExtensions: ['js', 'json'],
    
    // タイムアウト設定（ミリ秒）
    testTimeout: 10000,
    
    // 詳細なエラー出力
    verbose: true
};
