/**
 * AidUniteDateUtils テスト
 * 日付・時間ユーティリティの単体テスト
 */

// ソースファイルを直接requireしてカバレッジを計測可能にする
const AidUniteDateUtils = require('@js/common/date-utils.js');

describe('AidUniteDateUtils', () => {
    
    describe('formatDateLocal', () => {
        test('should format Date object to YYYY-MM-DD format', () => {
            const date = new Date('2024-12-15T10:30:00');
            const result = AidUniteDateUtils.formatDateLocal(date);
            expect(result).toBe('2024-12-15');
        });
        
        test('should return empty string for invalid date', () => {
            const result = AidUniteDateUtils.formatDateLocal(null);
            expect(result).toBe('');
        });
        
        test('should handle single digit month and day', () => {
            const date = new Date('2024-01-05T10:30:00');
            const result = AidUniteDateUtils.formatDateLocal(date);
            expect(result).toBe('2024-01-05');
        });
    });
    
    describe('formatTime', () => {
        test('should format time range correctly', () => {
            const result = AidUniteDateUtils.formatTime('09:00', '12:00');
            expect(result).toBe('09:00～12:00');
        });
        
        test('should return default text when time is missing', () => {
            const result = AidUniteDateUtils.formatTime(null, '12:00');
            expect(result).toBe('時間未設定');
        });
        
        test('should use custom default text', () => {
            const result = AidUniteDateUtils.formatTime(null, null, '未設定');
            expect(result).toBe('未設定');
        });
    });
    
    describe('formatDateForDisplay', () => {
        test('should format date string with default format', () => {
            const result = AidUniteDateUtils.formatDateForDisplay('2024-12-15');
            expect(result).toBe('2024/12/15');
        });
        
        test('should format date string with custom format', () => {
            const result = AidUniteDateUtils.formatDateForDisplay('2024-12-15', 'MM/DD');
            expect(result).toBe('12/15');
        });
        
        test('should return empty string for invalid input', () => {
            const result = AidUniteDateUtils.formatDateForDisplay('');
            expect(result).toBe('');
        });
    });
    
    describe('isToday', () => {
        test('should return true for today', () => {
            const today = new Date();
            const todayString = AidUniteDateUtils.formatDateLocal(today);
            const result = AidUniteDateUtils.isToday(todayString);
            expect(result).toBe(true);
        });
        
        test('should return false for past date', () => {
            const result = AidUniteDateUtils.isToday('2024-01-01');
            expect(result).toBe(false);
        });
        
        test('should return false for invalid input', () => {
            const result = AidUniteDateUtils.isToday(null);
            expect(result).toBe(false);
        });
    });
    
    describe('isValidDate', () => {
        test('should return true for valid date string', () => {
            const result = AidUniteDateUtils.isValidDate('2024-12-15');
            expect(result).toBe(true);
        });
        
        test('should return false for invalid date string', () => {
            const result = AidUniteDateUtils.isValidDate('invalid-date');
            expect(result).toBe(false);
        });
        
        test('should return false for null input', () => {
            const result = AidUniteDateUtils.isValidDate(null);
            expect(result).toBe(false);
        });
    });
    
    describe('formatTimeFromTimestamp', () => {
        test('should format timestamp to HH:MM format', () => {
            const timestamp = new Date('2024-12-15T14:30:00').getTime();
            const result = AidUniteDateUtils.formatTimeFromTimestamp(timestamp);
            expect(result).toMatch(/\d{2}:\d{2}/);
        });
        
        test('should return empty string for invalid timestamp', () => {
            const result = AidUniteDateUtils.formatTimeFromTimestamp(null);
            expect(result).toBe('');
        });
    });
    
    describe('formatRelativeTime', () => {
        test('should return "今" for very recent time', () => {
            const recentTime = new Date(Date.now() - 30000); // 30 seconds ago
            const result = AidUniteDateUtils.formatRelativeTime(recentTime);
            expect(result).toBe('今');
        });
        
        test('should return minutes ago for time within an hour', () => {
            const time = new Date(Date.now() - 300000); // 5 minutes ago
            const result = AidUniteDateUtils.formatRelativeTime(time);
            expect(result).toContain('分前');
        });
        
        test('should return empty string for invalid input', () => {
            const result = AidUniteDateUtils.formatRelativeTime(null);
            expect(result).toBe('');
        });
    });
    
    describe('formatDateJapanese', () => {
        test('should format date in Japanese format', () => {
            const result = AidUniteDateUtils.formatDateJapanese('2024-12-15');
            expect(result).toBeTruthy();
            expect(typeof result).toBe('string');
        });
        
        test('should return empty string for invalid input', () => {
            const result = AidUniteDateUtils.formatDateJapanese(null);
            expect(result).toBe('');
        });
    });
});
