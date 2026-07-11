/**
 * Jestセットアップファイル
 * テスト実行前の初期化処理
 * 
 * 注意: モジュールの読み込みは各テストファイルでrequireを使用します。
 * これにより、Jestがカバレッジを正しく計測できるようになります。
 */

// グローバル変数のモック
const bodyElement = {
    appendChild: jest.fn(),
    removeChild: jest.fn(),
    innerHTML: '',
    querySelector: jest.fn(),
    querySelectorAll: jest.fn(() => [])
};

global.window = {
    location: {
        href: 'http://localhost',
        pathname: '/',
        search: '',
        hash: ''
    },
    localStorage: {
        getItem: jest.fn(),
        setItem: jest.fn(),
        removeItem: jest.fn(),
        clear: jest.fn()
    },
    sessionStorage: {
        getItem: jest.fn(),
        setItem: jest.fn(),
        removeItem: jest.fn(),
        clear: jest.fn()
    },
    console: {
        log: jest.fn(),
        warn: jest.fn(),
        error: jest.fn()
    },
    schedules: {},
    mypageSchedules: {},
    scheduleData: {}
};

global.document = {
    createElement: jest.fn(() => ({
        style: {},
        setAttribute: jest.fn(),
        getAttribute: jest.fn(),
        addEventListener: jest.fn(),
        removeEventListener: jest.fn(),
        appendChild: jest.fn(),
        removeChild: jest.fn(),
        classList: {
            add: jest.fn(),
            remove: jest.fn(),
            contains: jest.fn(() => false)
        },
        closest: jest.fn(),
        innerHTML: '',
        id: '',
        className: '',
        dataset: {},
        click: jest.fn()
    })),
    getElementById: jest.fn((id) => {
        if (id === 'schedule-detail-popup') {
            return {
                classList: {
                    add: jest.fn(),
                    remove: jest.fn(),
                    contains: jest.fn(() => false)
                },
                style: { display: 'none' },
                innerHTML: '',
                remove: jest.fn()
            };
        }
        return null;
    }),
    querySelector: jest.fn(),
    querySelectorAll: jest.fn(() => []),
    body: {
        appendChild: jest.fn(),
        removeChild: jest.fn(),
        innerHTML: '',
        querySelector: jest.fn(),
        querySelectorAll: jest.fn(() => [])
    },
    addEventListener: jest.fn(),
    removeEventListener: jest.fn()
};

// windowオブジェクトにもdocumentを追加
global.window.document = global.document;

// windowとdocumentをグローバルに公開（テストファイルから直接アクセス可能にする）
global.window = global.window;
global.document = global.document;

// 後方互換性のため、グローバル関数をモックとして提供
// 注意: 各テストファイルでrequireを使用するため、これらのグローバル変数は
// 主に後方互換性のために残されています
global.getScheduleIcon = jest.fn();
global.getGenderLabel = jest.fn();
global.getVenueLabel = jest.fn();
global.formatTime = jest.fn();

// Date.now()のモック（必要に応じて）
// jest.spyOn(Date, 'now').mockReturnValue(1234567890000);
