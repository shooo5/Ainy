/**
 * AidUniteScheduleUtils テスト
 * スケジュールユーティリティの単体テスト
 */

// ソースファイルを直接requireしてカバレッジを計測可能にする
const AidUniteScheduleUtils = require('@js/common/schedule-utils.js');

describe('AidUniteScheduleUtils', () => {
    
    describe('getScheduleIcon', () => {
        test('should return correct icon for official match', () => {
            const result = AidUniteScheduleUtils.getScheduleIcon('公式試合');
            expect(result).toBe('🏆');
        });
        
        test('should return correct icon for practice match', () => {
            const result = AidUniteScheduleUtils.getScheduleIcon('練習試合');
            expect(result).toBe('🤝');
        });
        
        test('should return correct icon for practice', () => {
            const result = AidUniteScheduleUtils.getScheduleIcon('練習');
            expect(result).toBe('🏃');
        });
        
        test('should return default icon for unknown type', () => {
            const result = AidUniteScheduleUtils.getScheduleIcon('不明なタイプ');
            expect(result).toBe('📅');
        });
        
        test('should return default icon for null input', () => {
            const result = AidUniteScheduleUtils.getScheduleIcon(null);
            expect(result).toBe('📅');
        });
    });
    
    describe('getScheduleTypeDisplayName', () => {
        test('should remove tentative marker from type', () => {
            const result = AidUniteScheduleUtils.getScheduleTypeDisplayName('練習（仮）');
            expect(result).toBe('練習');
        });
        
        test('should remove recruitment marker from type', () => {
            const result = AidUniteScheduleUtils.getScheduleTypeDisplayName('練習試合（募集）');
            expect(result).toBe('練習試合');
        });
        
        test('should return type as is when no markers', () => {
            const result = AidUniteScheduleUtils.getScheduleTypeDisplayName('公式試合');
            expect(result).toBe('公式試合');
        });
        
        test('should return default for null input', () => {
            const result = AidUniteScheduleUtils.getScheduleTypeDisplayName(null);
            expect(result).toBe('その他');
        });
    });
    
    describe('getSchedulePriority', () => {
        test('should return highest priority for official match', () => {
            const result = AidUniteScheduleUtils.getSchedulePriority('公式試合');
            expect(result).toBe(5);
        });
        
        test('should return priority 4 for practice match', () => {
            const result = AidUniteScheduleUtils.getSchedulePriority('練習試合');
            expect(result).toBe(4);
        });
        
        test('should return priority 3 for practice', () => {
            const result = AidUniteScheduleUtils.getSchedulePriority('練習');
            expect(result).toBe(3);
        });
        
        test('should return lowest priority for unknown type', () => {
            const result = AidUniteScheduleUtils.getSchedulePriority('不明なタイプ');
            expect(result).toBe(1);
        });
    });
    
    describe('getScheduleColor', () => {
        test('should return correct color class for official match', () => {
            const result = AidUniteScheduleUtils.getScheduleColor('公式試合');
            expect(result).toBe('schedule-official');
        });
        
        test('should return correct color class for practice', () => {
            const result = AidUniteScheduleUtils.getScheduleColor('練習');
            expect(result).toBe('schedule-practice');
        });
        
        test('should return default color for unknown type', () => {
            const result = AidUniteScheduleUtils.getScheduleColor('不明なタイプ');
            expect(result).toBe('schedule-default');
        });
    });
    
    describe('getScheduleInfo', () => {
        test('should return complete schedule info object', () => {
            const result = AidUniteScheduleUtils.getScheduleInfo('公式試合');
            expect(result).toHaveProperty('icon');
            expect(result).toHaveProperty('displayName');
            expect(result).toHaveProperty('priority');
            expect(result).toHaveProperty('color');
            expect(result).toHaveProperty('originalType');
            expect(result.icon).toBe('🏆');
            expect(result.displayName).toBe('公式試合');
            expect(result.priority).toBe(5);
            expect(result.color).toBe('schedule-official');
        });
    });
    
    describe('getScheduleTypes', () => {
        test('should return array of schedule types', () => {
            const result = AidUniteScheduleUtils.getScheduleTypes();
            expect(Array.isArray(result)).toBe(true);
            expect(result.length).toBeGreaterThan(0);
            expect(result).toContain('公式試合');
            expect(result).toContain('練習');
        });
    });
    
    describe('getScheduleTypeOptions', () => {
        test('should return array of option objects', () => {
            const result = AidUniteScheduleUtils.getScheduleTypeOptions();
            expect(Array.isArray(result)).toBe(true);
            expect(result.length).toBeGreaterThan(0);
            expect(result[0]).toHaveProperty('value');
            expect(result[0]).toHaveProperty('label');
            expect(result[0]).toHaveProperty('icon');
            expect(result[0]).toHaveProperty('priority');
        });
    });
    
    describe('getGenderLabel', () => {
        test('should return Japanese label for male', () => {
            const result = AidUniteScheduleUtils.getGenderLabel('male');
            expect(result).toBe('男子');
        });
        
        test('should return Japanese label for female', () => {
            const result = AidUniteScheduleUtils.getGenderLabel('female');
            expect(result).toBe('女子');
        });
        
        test('should return Japanese label for both', () => {
            const result = AidUniteScheduleUtils.getGenderLabel('both');
            expect(result).toBe('男女とも');
        });
        
        test('should return original value for unknown gender', () => {
            const result = AidUniteScheduleUtils.getGenderLabel('unknown');
            expect(result).toBe('unknown');
        });
    });
    
    describe('getVenueLabel', () => {
        test('should return Japanese label for home', () => {
            const result = AidUniteScheduleUtils.getVenueLabel('home');
            expect(result).toBe('ホーム');
        });
        
        test('should return Japanese label for away', () => {
            const result = AidUniteScheduleUtils.getVenueLabel('away');
            expect(result).toBe('アウェイ');
        });
        
        test('should return Japanese label for both', () => {
            const result = AidUniteScheduleUtils.getVenueLabel('both');
            expect(result).toBe('どちらでも');
        });
        
        test('should return original value for unknown venue', () => {
            const result = AidUniteScheduleUtils.getVenueLabel('unknown');
            expect(result).toBe('unknown');
        });
    });
});
