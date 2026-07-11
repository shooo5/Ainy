/**
 * AidUniteAjaxUtils テスト
 */

const AidUniteAjaxUtils = require('@js/common/ajax-utils.js');

describe('AidUniteAjaxUtils', () => {
    beforeEach(() => {
        // DOMをクリーンアップ
        document.body.innerHTML = '';
        // グローバル変数をリセット
        window.ajaxurl = '/wp-admin/admin-ajax.php';
        window.ajax_nonce = 'test_nonce';
        window.wpApiSettings = { nonce: 'test_nonce' };
        
        // fetchをモック
        global.fetch = jest.fn();
    });

    afterEach(() => {
        jest.clearAllMocks();
    });

    describe('getAjaxUrl', () => {
        test('should return ajaxurl from window', () => {
            window.ajaxurl = '/custom-ajax.php';
            expect(AidUniteAjaxUtils.getAjaxUrl()).toBe('/custom-ajax.php');
        });

        test('should return default URL when ajaxurl is not set', () => {
            delete window.ajaxurl;
            expect(AidUniteAjaxUtils.getAjaxUrl()).toBe('/wp-admin/admin-ajax.php');
        });
    });

    describe('getNonce', () => {
        test('should return nonce from window.ajax_nonce', () => {
            window.ajax_nonce = 'test_nonce_123';
            expect(AidUniteAjaxUtils.getNonce('test_action')).toBe('test_nonce_123');
        });

        test('should return nonce from form field', () => {
            delete window.ajax_nonce;
            const form = document.createElement('form');
            const nonceField = document.createElement('input');
            nonceField.name = '_wpnonce';
            nonceField.value = 'form_nonce_456';
            form.appendChild(nonceField);
            document.body.appendChild(form);

            expect(AidUniteAjaxUtils.getNonce('test_action')).toBe('form_nonce_456');
        });

        test('should return dev_nonce when no nonce found', () => {
            delete window.ajax_nonce;
            document.body.innerHTML = '';
            const consoleWarnSpy = jest.spyOn(console, 'warn').mockImplementation();
            
            expect(AidUniteAjaxUtils.getNonce('test_action')).toBe('dev_nonce');
            expect(consoleWarnSpy).toHaveBeenCalled();
            
            consoleWarnSpy.mockRestore();
        });
    });

    describe('showLoading', () => {
        test('should create loading element', () => {
            AidUniteAjaxUtils.showLoading();
            const loading = document.getElementById('aidunite-loading');
            expect(loading).toBeTruthy();
            expect(loading.className).toBe('aidunite-loading');
        });

        test('should remove existing loading before creating new one', () => {
            AidUniteAjaxUtils.showLoading();
            const firstLoading = document.getElementById('aidunite-loading');
            AidUniteAjaxUtils.showLoading();
            const secondLoading = document.getElementById('aidunite-loading');
            
            expect(firstLoading).not.toBe(secondLoading);
            expect(document.querySelectorAll('#aidunite-loading').length).toBe(1);
        });
    });

    describe('hideLoading', () => {
        test('should remove loading element', () => {
            AidUniteAjaxUtils.showLoading();
            expect(document.getElementById('aidunite-loading')).toBeTruthy();
            
            AidUniteAjaxUtils.hideLoading();
            expect(document.getElementById('aidunite-loading')).toBeFalsy();
        });

        test('should not throw error when loading element does not exist', () => {
            expect(() => AidUniteAjaxUtils.hideLoading()).not.toThrow();
        });
    });

    describe('showMessage', () => {
        test('should create error message element', () => {
            AidUniteAjaxUtils.showMessage('Test error', 'error');
            const message = document.getElementById('aidunite-message');
            expect(message).toBeTruthy();
            expect(message.textContent).toBe('Test error');
            expect(message.className).toContain('aidunite-message-error');
        });

        test('should create success message element', () => {
            AidUniteAjaxUtils.showMessage('Test success', 'success');
            const message = document.getElementById('aidunite-message');
            expect(message).toBeTruthy();
            expect(message.className).toContain('aidunite-message-success');
        });

        test('should auto-hide message after 3 seconds', () => {
            jest.useFakeTimers();
            AidUniteAjaxUtils.showMessage('Test message');
            expect(document.getElementById('aidunite-message')).toBeTruthy();
            
            jest.advanceTimersByTime(3000);
            
            expect(document.getElementById('aidunite-message')).toBeFalsy();
            jest.useRealTimers();
        });
    });

    describe('hideMessage', () => {
        test('should remove message element', () => {
            AidUniteAjaxUtils.showMessage('Test message');
            expect(document.getElementById('aidunite-message')).toBeTruthy();
            
            AidUniteAjaxUtils.hideMessage();
            expect(document.getElementById('aidunite-message')).toBeFalsy();
        });
    });

    describe('showSuccess', () => {
        test('should call showMessage with success type', () => {
            const showMessageSpy = jest.spyOn(AidUniteAjaxUtils, 'showMessage');
            AidUniteAjaxUtils.showSuccess('Success message');
            expect(showMessageSpy).toHaveBeenCalledWith('Success message', 'success');
            showMessageSpy.mockRestore();
        });
    });

    describe('showError', () => {
        test('should call showMessage with error type', () => {
            const showMessageSpy = jest.spyOn(AidUniteAjaxUtils, 'showMessage');
            AidUniteAjaxUtils.showError('Error message');
            expect(showMessageSpy).toHaveBeenCalledWith('Error message', 'error');
            showMessageSpy.mockRestore();
        });
    });

    describe('request', () => {
        test('should call onError when action is not provided', () => {
            const onError = jest.fn();
            AidUniteAjaxUtils.request({ onError });
            expect(onError).toHaveBeenCalledWith('アクションが指定されていません');
        });

        test('should make POST request with correct data', async () => {
            const mockResponse = { success: true, data: { id: 1 } };
            global.fetch.mockResolvedValueOnce({
                ok: true,
                json: async () => mockResponse
            });

            const onSuccess = jest.fn();
            AidUniteAjaxUtils.request({
                action: 'test_action',
                data: { test: 'value' },
                onSuccess
            });

            // Promiseが解決するまで待つ
            await new Promise(resolve => {
                setTimeout(() => {
                    resolve();
                }, 0);
            });
            // さらに少し待つ
            await new Promise(resolve => setTimeout(resolve, 10));

            expect(global.fetch).toHaveBeenCalled();
            const callArgs = global.fetch.mock.calls[0];
            expect(callArgs[0]).toBe('/wp-admin/admin-ajax.php');
            expect(callArgs[1].method).toBe('POST');
            expect(onSuccess).toHaveBeenCalledWith(mockResponse);
        });

        test('should call onError when request fails', async () => {
            global.fetch.mockRejectedValueOnce(new Error('Network error'));

            const onError = jest.fn();
            AidUniteAjaxUtils.request({
                action: 'test_action',
                onError
            });

            // Promiseが解決するまで待つ
            await new Promise(resolve => {
                setTimeout(() => {
                    resolve();
                }, 0);
            });
            // さらに少し待つ
            await new Promise(resolve => setTimeout(resolve, 10));

            expect(onError).toHaveBeenCalledWith('Network error');
        });

        test('should call onError when response.success is false', async () => {
            const mockResponse = { success: false, message: 'Error message' };
            global.fetch.mockResolvedValueOnce({
                ok: true,
                json: async () => mockResponse
            });

            const onError = jest.fn();
            AidUniteAjaxUtils.request({
                action: 'test_action',
                onError
            });

            // Promiseが解決するまで待つ
            await new Promise(resolve => {
                setTimeout(() => {
                    resolve();
                }, 0);
            });
            // さらに少し待つ
            await new Promise(resolve => setTimeout(resolve, 10));

            expect(onError).toHaveBeenCalledWith('Error message');
        });
    });

    describe('get', () => {
        test('should call request with GET method', () => {
            // fetchをモックして、requestが正常に動作するようにする
            global.fetch.mockResolvedValueOnce({
                ok: true,
                json: async () => ({ success: true })
            });
            
            const requestSpy = jest.spyOn(AidUniteAjaxUtils, 'request');
            AidUniteAjaxUtils.get({ action: 'test_action' });
            expect(requestSpy).toHaveBeenCalledWith({
                action: 'test_action',
                method: 'GET'
            });
            requestSpy.mockRestore();
        });
    });

    describe('post', () => {
        test('should call request with POST method', () => {
            // fetchをモックして、requestが正常に動作するようにする
            global.fetch.mockResolvedValueOnce({
                ok: true,
                json: async () => ({ success: true })
            });
            
            const requestSpy = jest.spyOn(AidUniteAjaxUtils, 'request');
            AidUniteAjaxUtils.post({ action: 'test_action' });
            expect(requestSpy).toHaveBeenCalledWith({
                action: 'test_action',
                method: 'POST'
            });
            requestSpy.mockRestore();
        });
    });

    describe('getSchedules', () => {
        test('should call request with get_schedules action', () => {
            // fetchをモックして、requestが正常に動作するようにする
            global.fetch.mockResolvedValueOnce({
                ok: true,
                json: async () => ({ success: true })
            });
            
            const requestSpy = jest.spyOn(AidUniteAjaxUtils, 'request');
            AidUniteAjaxUtils.getSchedules({
                start_date: '2024-01-01',
                end_date: '2024-01-31',
                view: 'calendar'
            });
            expect(requestSpy).toHaveBeenCalledWith({
                action: 'get_schedules',
                data: {
                    start_date: '2024-01-01',
                    end_date: '2024-01-31',
                    view: 'calendar'
                },
                onSuccess: null,
                onError: null
            });
            requestSpy.mockRestore();
        });
    });

    describe('saveSchedule', () => {
        test('should call request with save_schedule action', () => {
            // fetchをモックして、requestが正常に動作するようにする
            global.fetch.mockResolvedValueOnce({
                ok: true,
                json: async () => ({ success: true })
            });
            
            const requestSpy = jest.spyOn(AidUniteAjaxUtils, 'request');
            const scheduleData = { date: '2024-01-01', type: 'practice' };
            AidUniteAjaxUtils.saveSchedule({ schedule_data: scheduleData });
            expect(requestSpy).toHaveBeenCalledWith({
                action: 'save_schedule',
                data: scheduleData,
                onSuccess: null,
                onError: null
            });
            requestSpy.mockRestore();
        });
    });

    describe('deleteSchedule', () => {
        test('should call request with delete_schedule action', () => {
            // fetchをモックして、requestが正常に動作するようにする
            global.fetch.mockResolvedValueOnce({
                ok: true,
                json: async () => ({ success: true })
            });
            
            const requestSpy = jest.spyOn(AidUniteAjaxUtils, 'request');
            AidUniteAjaxUtils.deleteSchedule({ schedule_id: 123 });
            expect(requestSpy).toHaveBeenCalledWith({
                action: 'delete_schedule',
                data: { schedule_id: 123 },
                onSuccess: null,
                onError: null
            });
            requestSpy.mockRestore();
        });
    });

    describe('getUserInfo', () => {
        test('should call request with get_user_info action', () => {
            // fetchをモックして、requestが正常に動作するようにする
            global.fetch.mockResolvedValueOnce({
                ok: true,
                json: async () => ({ success: true })
            });
            
            const requestSpy = jest.spyOn(AidUniteAjaxUtils, 'request');
            AidUniteAjaxUtils.getUserInfo({ user_id: 456 });
            expect(requestSpy).toHaveBeenCalledWith({
                action: 'get_user_info',
                data: { user_id: 456 },
                onSuccess: null,
                onError: null
            });
            requestSpy.mockRestore();
        });
    });

    describe('getTeamInfo', () => {
        test('should call request with get_team_info action', () => {
            // fetchをモックして、requestが正常に動作するようにする
            global.fetch.mockResolvedValueOnce({
                ok: true,
                json: async () => ({ success: true })
            });
            
            const requestSpy = jest.spyOn(AidUniteAjaxUtils, 'request');
            AidUniteAjaxUtils.getTeamInfo({ team_id: 789 });
            expect(requestSpy).toHaveBeenCalledWith({
                action: 'get_team_info',
                data: { team_id: 789 },
                onSuccess: null,
                onError: null
            });
            requestSpy.mockRestore();
        });
    });

    describe('sendNotification', () => {
        test('should call request with send_notification action', () => {
            // fetchをモックして、requestが正常に動作するようにする
            global.fetch.mockResolvedValueOnce({
                ok: true,
                json: async () => ({ success: true })
            });
            
            const requestSpy = jest.spyOn(AidUniteAjaxUtils, 'request');
            const notificationData = { message: 'Test notification' };
            AidUniteAjaxUtils.sendNotification({
                user_id: 1,
                type: 'match_request',
                data: notificationData
            });
            expect(requestSpy).toHaveBeenCalledWith({
                action: 'send_notification',
                data: {
                    user_id: 1,
                    type: 'match_request',
                    data: JSON.stringify(notificationData)
                },
                onSuccess: null,
                onError: null
            });
            requestSpy.mockRestore();
        });
    });
});
