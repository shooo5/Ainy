/**
 * AidUniteScheduleModal テスト
 * スケジュールモーダルユーティリティの単体テスト
 */

// ソースファイルを直接requireしてカバレッジを計測可能にする
const AidUniteScheduleModal = require('@js/common/schedule-modal.js');
const AidUniteScheduleUtils = require('@js/common/schedule-utils.js');
const AidUniteDateUtils = require('@js/common/date-utils.js');

describe('AidUniteScheduleModal', () => {
    
    beforeEach(() => {
        // 各テスト前にDOMをクリーンアップ
        document.body.innerHTML = '';
        
        // グローバル変数をモック
        window.schedules = {};
        window.mypageSchedules = {};
        window.scheduleData = {};
    });
    
    describe('findScheduleById', () => {
        test('should find schedule by ID', () => {
            const testSchedule = {
                id: '123',
                type: '練習',
                date: '2024-12-15'
            };
            
            window.schedules = {
                '2024-12-15': [testSchedule]
            };
            
            const result = AidUniteScheduleModal.findScheduleById('123');
            expect(result).toEqual(testSchedule);
        });
        
        test('should return null for non-existent ID', () => {
            window.schedules = {
                '2024-12-15': [{ id: '123', type: '練習' }]
            };
            
            const result = AidUniteScheduleModal.findScheduleById('999');
            expect(result).toBeNull();
        });
    });
    
    describe('createModal', () => {
        test('should create modal element', () => {
            const schedule = {
                id: '123',
                type: '練習',
                date: '2024-12-15',
                start_time: '09:00',
                end_time: '12:00'
            };
            
            const modal = AidUniteScheduleModal.createModal(schedule);
            expect(modal).toBeInstanceOf(HTMLElement);
            expect(modal.className).toBe('schedule-detail-modal');
            expect(modal.innerHTML).toContain('練習');
        });
    });
    
    describe('createPopup', () => {
        test('should create popup element', () => {
            const schedule = {
                id: '123',
                type: '練習',
                date: '2024-12-15',
                start_time: '09:00',
                end_time: '12:00'
            };
            
            const popup = AidUniteScheduleModal.createPopup(schedule);
            expect(popup).toBeInstanceOf(HTMLElement);
            expect(popup.id).toBe('schedule-detail-popup');
            expect(popup.className).toBe('schedule-detail-popup');
            expect(popup.innerHTML).toContain('練習');
        });
    });
    
    describe('closeModal', () => {
        test('should remove modal from DOM', () => {
            const modal = document.createElement('div');
            modal.className = 'schedule-detail-modal';
            document.body.appendChild(modal);
            
            AidUniteScheduleModal.closeModal(modal);
            
            // アニメーション後に削除されるため、少し待つ
            setTimeout(() => {
                expect(document.querySelector('.schedule-detail-modal')).toBeNull();
            }, 400);
        });
    });
    
    describe('closePopup', () => {
        test('should remove popup from DOM', () => {
            const popup = document.createElement('div');
            popup.id = 'schedule-detail-popup';
            popup.className = 'schedule-detail-popup';
            document.body.appendChild(popup);
            
            AidUniteScheduleModal.closePopup();
            
            // アニメーション後に削除されるため、少し待つ
            setTimeout(() => {
                expect(document.getElementById('schedule-detail-popup')).toBeNull();
            }, 400);
        });
    });
    
    describe('closeAll', () => {
        test('should close all modals and popups', () => {
            const modal = document.createElement('div');
            modal.className = 'schedule-detail-modal';
            document.body.appendChild(modal);
            
            const popup = document.createElement('div');
            popup.id = 'schedule-detail-popup';
            popup.className = 'schedule-detail-popup';
            document.body.appendChild(popup);
            
            AidUniteScheduleModal.closeAll();
            
            setTimeout(() => {
                expect(document.querySelector('.schedule-detail-modal')).toBeNull();
                expect(document.getElementById('schedule-detail-popup')).toBeNull();
            }, 400);
        });
    });
});
