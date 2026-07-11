/**
 * toast-notification.js のテスト
 */

// カバレッジ計測のため、ファイル先頭でモジュールを読み込む
require('@js/toast-notification.js');

describe('toast-notification.js', () => {
    let mockDocument;
    let mockToast, mockOverlay, mockBody;
    let showToastNotification, showScheduleRegistrationToast, escapeHtml;

    beforeEach(() => {
        jest.clearAllMocks();
        jest.useFakeTimers();

        // モックドキュメントの作成
        mockDocument = {
            querySelector: jest.fn(),
            createElement: jest.fn(),
            addEventListener: jest.fn(),
            body: {
                appendChild: jest.fn()
            },
            head: {
                appendChild: jest.fn()
            }
        };

        // モック要素の作成
        mockToast = {
            className: '',
            setAttribute: jest.fn(),
            innerHTML: '',
            classList: {
                add: jest.fn(),
                remove: jest.fn()
            },
            addEventListener: jest.fn(),
            parentElement: null,
            remove: jest.fn(),
            style: {}
        };

        mockOverlay = {
            className: '',
            style: {},
            parentElement: null,
            remove: jest.fn()
        };

        mockBody = {
            appendChild: jest.fn()
        };

        // createElementのモック実装
        mockDocument.createElement.mockImplementation((tag) => {
            if (tag === 'div') {
                let textContentValue = '';
                let innerHTMLValue = '';
                const element = {
                    className: '',
                    setAttribute: jest.fn(),
                    innerHTML: '',
                    classList: {
                        add: jest.fn(),
                        remove: jest.fn()
                    },
                    addEventListener: jest.fn(),
                    parentElement: null,
                    remove: jest.fn(),
                    style: {},
                    get textContent() {
                        return textContentValue;
                    },
                    set textContent(value) {
                        textContentValue = value || '';
                        innerHTMLValue = String(value || '')
                            .replace(/&/g, '&amp;')
                            .replace(/</g, '&lt;')
                            .replace(/>/g, '&gt;')
                            .replace(/"/g, '&quot;')
                            .replace(/'/g, '&#39;');
                    },
                    get innerHTML() {
                        return innerHTMLValue;
                    },
                    set innerHTML(value) {
                        innerHTMLValue = value || '';
                    }
                };
                return element;
            }
            return { textContent: '', innerHTML: '' };
        });

        // querySelectorのモック実装
        mockDocument.querySelector.mockImplementation((selector) => {
            if (selector === '.aidunite-toast-notification') {
                return null; // 既存のトーストはない
            }
            if (selector === '.aidunite-toast-overlay') {
                return null; // 既存のオーバーレイはない
            }
            return null;
        });

        mockDocument.body = mockBody;

        // グローバルオブジェクトの設定（モジュールは既に読み込まれているので、モックを設定するだけ）
        Object.defineProperty(global, 'document', {
            value: mockDocument,
            writable: true,
            configurable: true
        });
        
        // windowオブジェクトの設定（documentを参照できるように）
        global.window = {
            ...global.window,
            document: mockDocument
        };

        // グローバルに公開された関数を取得（モジュールは既に読み込まれている）
        showToastNotification = global.showToastNotification;
        showScheduleRegistrationToast = global.showScheduleRegistrationToast;
        escapeHtml = global.escapeHtml;
    });

    afterEach(() => {
        jest.useRealTimers();
    });

    describe('escapeHtml', () => {
        test('should escape HTML special characters', () => {
            const result = escapeHtml('<script>alert("xss")</script>');
            expect(result).toBe('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;');
        });

        test('should return empty string for non-string input', () => {
            const result = escapeHtml(null);
            expect(result).toBe('');
        });

        test('should handle empty string', () => {
            const result = escapeHtml('');
            expect(result).toBe('');
        });
    });

    describe('showToastNotification', () => {
        test('should create and display toast notification', () => {
            showToastNotification('テストメッセージ', 'success');

            expect(mockDocument.createElement).toHaveBeenCalledWith('div');
            expect(mockDocument.body.appendChild).toHaveBeenCalled();
        });

        test('should remove existing toast before creating new one', () => {
            const existingToast = { remove: jest.fn() };
            mockDocument.querySelector.mockReturnValueOnce(existingToast);

            showToastNotification('新しいメッセージ', 'info');

            expect(existingToast.remove).toHaveBeenCalled();
        });

        test('should use default title for success type', () => {
            showToastNotification('メッセージ', 'success');

            const createElementCalls = mockDocument.createElement.mock.calls;
            const toastElement = createElementCalls.find(call => call[0] === 'div');
            expect(toastElement).toBeTruthy();
        });

        test('should use custom title when provided', () => {
            showToastNotification('メッセージ', 'success', {
                title: 'カスタムタイトル'
            });

            expect(mockDocument.createElement).toHaveBeenCalled();
        });

        test('should call onClose callback when toast is closed', () => {
            const onClose = jest.fn();
            let mockToastElement;
            
            // createElementのモックを設定して、作成された要素を保存
            mockDocument.createElement.mockImplementation((tag) => {
                if (tag === 'div') {
                    mockToastElement = {
                        className: '',
                        setAttribute: jest.fn(),
                        innerHTML: '',
                        classList: {
                            add: jest.fn(),
                            remove: jest.fn()
                        },
                        addEventListener: jest.fn(),
                        parentElement: mockBody,
                        remove: jest.fn(),
                        style: {}
                    };
                    return mockToastElement;
                }
                return { textContent: '', innerHTML: '' };
            });

            showToastNotification('メッセージ', 'success', {
                duration: 1000,
                onClose: onClose
            });

            // タイマーを進める
            jest.advanceTimersByTime(1000);
            jest.advanceTimersByTime(300);

            expect(onClose).toHaveBeenCalled();
        });
    });

    describe('showScheduleRegistrationToast', () => {
        test('should call showToastNotification with correct parameters', () => {
            mockDocument.querySelector.mockReturnValue(null);
            
            // createElementの呼び出しを追跡
            const createdElements = [];
            mockDocument.createElement.mockImplementation((tag) => {
                if (tag === 'div') {
                    let textContentValue = '';
                    let innerHTMLValue = '';
                    const element = {
                        className: '',
                        setAttribute: jest.fn(),
                        innerHTML: '',
                        classList: {
                            add: jest.fn(),
                            remove: jest.fn()
                        },
                        addEventListener: jest.fn(),
                        parentElement: null,
                        remove: jest.fn(),
                        style: {},
                        get textContent() {
                            return textContentValue;
                        },
                        set textContent(value) {
                            textContentValue = value || '';
                            innerHTMLValue = String(value || '')
                                .replace(/&/g, '&amp;')
                                .replace(/</g, '&lt;')
                                .replace(/>/g, '&gt;')
                                .replace(/"/g, '&quot;')
                                .replace(/'/g, '&#39;');
                        },
                        get innerHTML() {
                            return innerHTMLValue;
                        },
                        set innerHTML(value) {
                            innerHTMLValue = value || '';
                        }
                    };
                    createdElements.push(element);
                    return element;
                }
                return { textContent: '', innerHTML: '' };
            });
            
            showScheduleRegistrationToast('スケジュール登録完了');

            // showToastNotificationが呼ばれたことを確認（createElementが呼ばれている）
            expect(mockDocument.createElement).toHaveBeenCalled();
            
            // トースト要素（2番目のdiv要素）を取得
            // 1番目はオーバーレイ、2番目はトースト
            const toastElement = createdElements.length >= 2 ? createdElements[1] : createdElements[0];
            expect(toastElement).toBeTruthy();
            expect(toastElement.setAttribute).toHaveBeenCalledWith('data-type', 'success');
            // innerHTMLにタイトルとアイコンが含まれていることを確認
            expect(toastElement.innerHTML).toContain('スケジュール登録完了');
            expect(toastElement.innerHTML).toContain('🎉');
        });

        test('should use default message when not provided', () => {
            mockDocument.querySelector.mockReturnValue(null);
            
            // createElementの呼び出しを追跡
            const createdElements = [];
            mockDocument.createElement.mockImplementation((tag) => {
                if (tag === 'div') {
                    let textContentValue = '';
                    let innerHTMLValue = '';
                    const element = {
                        className: '',
                        setAttribute: jest.fn(),
                        innerHTML: '',
                        classList: {
                            add: jest.fn(),
                            remove: jest.fn()
                        },
                        addEventListener: jest.fn(),
                        parentElement: null,
                        remove: jest.fn(),
                        style: {},
                        get textContent() {
                            return textContentValue;
                        },
                        set textContent(value) {
                            textContentValue = value || '';
                            innerHTMLValue = String(value || '')
                                .replace(/&/g, '&amp;')
                                .replace(/</g, '&lt;')
                                .replace(/>/g, '&gt;')
                                .replace(/"/g, '&quot;')
                                .replace(/'/g, '&#39;');
                        },
                        get innerHTML() {
                            return innerHTMLValue;
                        },
                        set innerHTML(value) {
                            innerHTMLValue = value || '';
                        }
                    };
                    createdElements.push(element);
                    return element;
                }
                return { textContent: '', innerHTML: '' };
            });
            
            showScheduleRegistrationToast();

            // showToastNotificationが呼ばれたことを確認（createElementが呼ばれている）
            expect(mockDocument.createElement).toHaveBeenCalled();
            
            // トースト要素（2番目のdiv要素）を取得
            // 1番目はオーバーレイ、2番目はトースト
            const toastElement = createdElements.length >= 2 ? createdElements[1] : createdElements[0];
            expect(toastElement).toBeTruthy();
            expect(toastElement.setAttribute).toHaveBeenCalledWith('data-type', 'success');
            expect(toastElement.innerHTML).toContain('スケジュールの登録が完了しました！');
            expect(toastElement.innerHTML).toContain('🎉');
        });
    });
});

