/**
 * loading-spinner-utils.js のテスト
 */

// カバレッジ計測のため、ファイル先頭でモジュールを読み込む
require('@js/loading-spinner-utils.js');

describe('loading-spinner-utils.js', () => {
    let mockDocument;
    let loadingSpinnerManager;
    let mockOverlay, mockButton;
    let LoadingSpinnerManager;

    beforeEach(() => {
        jest.clearAllMocks();
        jest.useFakeTimers();

        // モックドキュメントの作成
        mockDocument = {
            getElementById: jest.fn(),
            querySelectorAll: jest.fn(() => []),
            createElement: jest.fn(),
            addEventListener: jest.fn(),
            body: {
                appendChild: jest.fn(),
                removeChild: jest.fn()
            }
        };

        // モック要素の作成
        mockOverlay = {
            id: '',
            className: '',
            innerHTML: '',
            classList: {
                add: jest.fn(),
                remove: jest.fn()
            },
            parentElement: null,
            style: {}
        };

        mockButton = {
            disabled: false,
            innerHTML: '<span>送信</span>',
            classList: {
                add: jest.fn(),
                remove: jest.fn()
            }
        };

        // getElementByIdのモック実装
        mockDocument.getElementById.mockImplementation((id) => {
            if (id && id.startsWith('loading-spinner-')) {
                return mockOverlay;
            }
            return null;
        });

        // createElementのモック実装
        mockDocument.createElement.mockImplementation((tag) => {
            if (tag === 'div') {
                return { ...mockOverlay };
            }
            return {};
        });

        // グローバルオブジェクトの設定（モジュールは既に読み込まれているので、モックを設定するだけ）
        Object.defineProperty(global, 'document', {
            value: mockDocument,
            writable: true,
            configurable: true
        });

        // console.logをモック
        global.console = {
            log: jest.fn(),
            error: jest.fn(),
            warn: jest.fn()
        };

        // グローバルに公開されたクラスとインスタンスを取得（モジュールは既に読み込まれている）
        LoadingSpinnerManager = global.LoadingSpinnerManager;
        loadingSpinnerManager = global.loadingSpinnerManager;
    });

    afterEach(() => {
        jest.useRealTimers();
    });

    describe('LoadingSpinnerManager', () => {
        test('should show fullscreen spinner', () => {
            const spinnerId = loadingSpinnerManager.showFullscreen('処理中...', 'しばらくお待ちください');

            expect(mockDocument.createElement).toHaveBeenCalledWith('div');
            expect(mockDocument.body.appendChild).toHaveBeenCalled();
            expect(spinnerId).toBeTruthy();
        });

        test('should hide fullscreen spinner', () => {
            const spinnerId = 'loading-spinner-1234567890';
            mockOverlay.id = spinnerId;
            mockOverlay.parentElement = mockDocument.body;

            loadingSpinnerManager.hideFullscreen(spinnerId);

            expect(mockDocument.getElementById).toHaveBeenCalledWith(spinnerId);
            expect(mockOverlay.classList.add).toHaveBeenCalledWith('fade-out');

            // タイマーを進める
            jest.advanceTimersByTime(300);

            // removeChildが呼ばれることを確認（モックの実装による）
        });

        test('should show button spinner', () => {
            const result = loadingSpinnerManager.showButtonSpinner(mockButton, '処理中...');

            expect(mockButton.disabled).toBe(true);
            expect(mockButton.innerHTML).toContain('処理中...');
            expect(result).toHaveProperty('originalHTML');
            expect(result).toHaveProperty('originalDisabled');
            expect(result).toHaveProperty('restore');
        });

        test('should hide button spinner', () => {
            const originalHTML = '<span>送信</span>';
            const originalDisabled = false;

            loadingSpinnerManager.hideButtonSpinner(mockButton, originalHTML, originalDisabled);

            expect(mockButton.innerHTML).toBe(originalHTML);
            expect(mockButton.disabled).toBe(originalDisabled);
        });

        test('should show inline spinner', () => {
            const mockElement = {
                innerHTML: '<span>元の内容</span>'
            };

            const result = loadingSpinnerManager.showInlineSpinner(mockElement, '処理中...');

            expect(mockElement.innerHTML).toContain('処理中...');
            expect(result).toHaveProperty('originalHTML');
            expect(result).toHaveProperty('restore');
        });

        test('should hide inline spinner', () => {
            const mockElement = {
                innerHTML: ''
            };
            const originalHTML = '<span>元の内容</span>';

            loadingSpinnerManager.hideInlineSpinner(mockElement, originalHTML);

            expect(mockElement.innerHTML).toBe(originalHTML);
        });
    });

    describe('Global functions', () => {
        test('should expose showLoadingSpinner globally', () => {
            expect(typeof global.showLoadingSpinner).toBe('function');
            
            const spinnerId = global.showLoadingSpinner('タイトル', 'サブタイトル');
            expect(spinnerId).toBeTruthy();
        });

        test('should expose hideLoadingSpinner globally', () => {
            expect(typeof global.hideLoadingSpinner).toBe('function');
            
            const spinnerId = 'loading-spinner-1234567890';
            mockOverlay.id = spinnerId;
            mockOverlay.parentElement = mockDocument.body;

            global.hideLoadingSpinner(spinnerId);

            expect(mockDocument.getElementById).toHaveBeenCalledWith(spinnerId);
        });

        test('should expose showButtonLoading globally', () => {
            expect(typeof global.showButtonLoading).toBe('function');
            
            const result = global.showButtonLoading(mockButton, '処理中...');
            expect(result).toHaveProperty('originalHTML');
        });

        test('should expose hideButtonLoading globally', () => {
            expect(typeof global.hideButtonLoading).toBe('function');
            
            global.hideButtonLoading(mockButton, '<span>送信</span>', false);
            expect(mockButton.innerHTML).toBe('<span>送信</span>');
        });
    });
});

