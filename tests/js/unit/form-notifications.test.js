/**
 * form-notifications.js のテスト
 */

// カバレッジ計測のため、ファイル先頭でモジュールを読み込む
require('@js/form-notifications.js');

describe('form-notifications.js', () => {
    let mockDocument;
    let formNotifications;
    let mockToastContainer, mockForm, mockSubmitButton, mockLoadingElement, mockErrorElement, mockSuccessElement;
    let FormNotifications;

    beforeEach(() => {
        jest.clearAllMocks();
        jest.useFakeTimers();

        // Jest環境のJSDOMのdocumentを保存（FormData用）
        const originalJSDOMDocument = global.document;

        // FormDataをモック
        global.FormData = jest.fn().mockImplementation((form) => {
            const formData = {
                get: jest.fn(),
                set: jest.fn(),
                has: jest.fn(),
                delete: jest.fn(),
                append: jest.fn(),
                entries: jest.fn(() => []),
                keys: jest.fn(() => []),
                values: jest.fn(() => []),
                forEach: jest.fn()
            };
            return formData;
        });

        // モックドキュメントの作成
        mockDocument = {
            getElementById: jest.fn(),
            createElement: jest.fn(),
            addEventListener: jest.fn(),
            body: {
                appendChild: jest.fn()
            }
        };
        
        // 元のJSDOMのdocumentを保存（後で使用するため）
        mockDocument._originalJSDOMDocument = originalJSDOMDocument;

        // モック要素の作成
        mockToastContainer = {
            id: 'toast-container',
            appendChild: jest.fn(),
            style: {}
        };

        mockSubmitButton = {
            disabled: false,
            textContent: '送信',
            classList: {
                contains: jest.fn()
            }
        };

        mockLoadingElement = {
            style: {
                display: 'none'
            }
        };

        mockErrorElement = {
            style: {
                display: 'none'
            },
            textContent: ''
        };

        mockSuccessElement = {
            style: {
                display: 'none'
            },
            textContent: ''
        };

        // getElementByIdのモック実装
        mockDocument.getElementById.mockImplementation((id) => {
            if (id === 'toast-container') {
                return mockToastContainer;
            }
            return null;
        });

        // createElementのモック実装
        mockDocument.createElement.mockImplementation((tag) => {
            if (tag === 'div') {
                return {
                    id: '',
                    className: '',
                    style: {},
                    textContent: '',
                    parentNode: null,
                    remove: jest.fn()
                };
            }
            if (tag === 'form') {
                const formElement = {
                    classList: {
                        contains: jest.fn(() => true)
                    },
                    querySelector: jest.fn((selector) => {
                        if (selector === '.submit-button') return mockSubmitButton;
                        if (selector === '.loading') return mockLoadingElement;
                        if (selector === '.error-message') return mockErrorElement;
                        if (selector === '.success-message') return mockSuccessElement;
                        return null;
                    }),
                    appendChild: jest.fn(),
                    remove: jest.fn()
                };
                return formElement;
            }
            return {};
        });

        // HTMLFormElementとしてモックを作成
        mockForm = mockDocument.createElement('form');

        // グローバルオブジェクトの設定（モジュールは既に読み込まれているので、モックを設定するだけ）
        Object.defineProperty(global, 'document', {
            value: mockDocument,
            writable: true,
            configurable: true
        });

        // windowオブジェクトの設定
        global.window = {
            disableFormNotifications: false
        };

        // WordPressのグローバル変数を設定
        global.ajaxurl = '/wp-admin/admin-ajax.php';
        global.showToastNotification = jest.fn();

        // グローバルに公開されたクラスを取得（モジュールは既に読み込まれている）
        FormNotifications = global.FormNotifications;

        // FormNotificationsクラスを直接インスタンス化
        formNotifications = new FormNotifications();
    });

    afterEach(() => {
        jest.useRealTimers();
    });

    describe('FormNotifications', () => {
        test('should bind submit listener on init', () => {
            expect(mockDocument.addEventListener).toHaveBeenCalledWith(
                'submit',
                expect.any(Function)
            );
        });

        test('should show toast via showToastNotification', () => {
            formNotifications.showToast('テストメッセージ', 'success');

            expect(global.showToastNotification).toHaveBeenCalledWith(
                'テストメッセージ',
                'success',
                { duration: 3000 }
            );
        });

        test('should warn when showToastNotification is not loaded', () => {
            const warnSpy = jest.spyOn(console, 'warn').mockImplementation(() => {});
            delete global.showToastNotification;

            formNotifications.showToast('テスト', 'error');

            expect(warnSpy).toHaveBeenCalled();
            warnSpy.mockRestore();
            global.showToastNotification = jest.fn();
        });

        test('should handle form submit event', async () => {
            // イベントリスナーが設定されていることを確認
            expect(mockDocument.addEventListener).toHaveBeenCalledWith(
                'submit',
                expect.any(Function)
            );

            // イベントリスナーを取得
            const submitHandler = mockDocument.addEventListener.mock.calls.find(
                call => call[0] === 'submit'
            )[1];

            // モックフォーム要素を作成
            const formElement = {
                className: 'ajax-form',
                classList: {
                    contains: jest.fn((className) => className === 'ajax-form')
                },
                querySelector: jest.fn((selector) => {
                    if (selector === '.submit-button') return mockSubmitButton;
                    if (selector === '.loading') return mockLoadingElement;
                    if (selector === '.error-message') return mockErrorElement;
                    if (selector === '.success-message') return mockSuccessElement;
                    return null;
                })
            };

            const mockEvent = {
                target: formElement,
                preventDefault: jest.fn()
            };

            // fetchをモック
            const fetchPromise = Promise.resolve({
                ok: true,
                json: () => Promise.resolve({
                    success: true,
                    data: {
                        message: '成功しました'
                    }
                })
            });
            global.fetch = jest.fn(() => fetchPromise);

            // window.loadingSpinnerManagerをモック
            global.window = {
                ...global.window,
                loadingSpinnerManager: undefined
            };

            // イベントハンドラーを呼び出し（非同期処理が開始される）
            submitHandler(mockEvent);

            // fetchのPromiseチェーンが完了するまで待つ
            await fetchPromise;
            
            // すべてのタイマーを実行
            jest.runAllTimers();

            expect(mockEvent.preventDefault).toHaveBeenCalled();
            expect(global.FormData).toHaveBeenCalledWith(formElement);
            expect(global.fetch).toHaveBeenCalled();
        });

        test('should handle form submit with error', async () => {
            const submitHandler = mockDocument.addEventListener.mock.calls.find(
                call => call[0] === 'submit'
            )[1];

            // モックフォーム要素を作成
            const formElement = {
                className: 'ajax-form',
                classList: {
                    contains: jest.fn((className) => className === 'ajax-form')
                },
                querySelector: jest.fn((selector) => {
                    if (selector === '.submit-button') return mockSubmitButton;
                    if (selector === '.loading') return mockLoadingElement;
                    if (selector === '.error-message') return mockErrorElement;
                    if (selector === '.success-message') return mockSuccessElement;
                    return null;
                })
            };

            const mockEvent = {
                target: formElement,
                preventDefault: jest.fn()
            };

            // fetchをモック（エラー）
            const fetchPromise = Promise.resolve({
                ok: true,
                json: () => Promise.resolve({
                    success: false,
                    data: {
                        message: 'エラーが発生しました'
                    }
                })
            });
            global.fetch = jest.fn(() => fetchPromise);

            // window.loadingSpinnerManagerをモック
            global.window = {
                ...global.window,
                loadingSpinnerManager: undefined
            };

            // イベントハンドラーを呼び出し（非同期処理が開始される）
            submitHandler(mockEvent);

            // fetchのPromiseチェーンが完了するまで待つ
            await fetchPromise;
            
            // すべてのタイマーを実行
            jest.runAllTimers();

            expect(mockEvent.preventDefault).toHaveBeenCalled();
            expect(global.FormData).toHaveBeenCalledWith(formElement);
            expect(global.fetch).toHaveBeenCalled();
        });
    });
});

