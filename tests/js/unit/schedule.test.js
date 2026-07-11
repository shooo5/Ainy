/**
 * schedule.js tests
 */

global.AidUniteDateUtils = {
    formatDateLocal: jest.fn((date) => {
        if (!date || !(date instanceof Date)) return '';
        const y = date.getFullYear();
        const m = (date.getMonth() + 1).toString().padStart(2, '0');
        const d = date.getDate().toString().padStart(2, '0');
        return `${y}-${m}-${d}`;
    })
};

global.window = global.window || {};
require('@js/schedule/schedule.js');

describe('schedule.js', () => {
    beforeEach(() => {
        document.body.innerHTML = '';
        window.currentDate = new Date(2024, 0, 15);
        window.schedules = {};
        console.warn = jest.fn();
        console.log = jest.fn();
        console.error = jest.fn();
    });

    afterEach(() => {
        jest.clearAllMocks();
    });

    describe('isTodayDate', () => {
        test('should return true for today', () => {
            const today = new Date();
            const todayString = global.AidUniteDateUtils.formatDateLocal(today);
            expect(window.isTodayDate(todayString)).toBe(true);
        });

        test('should return false for past date', () => {
            expect(window.isTodayDate('2024-01-01')).toBe(false);
        });
    });

    describe('isJapaneseHoliday', () => {
        test('should return true for New Year', () => {
            const newYear = new Date(2024, 0, 1);
            expect(window.isJapaneseHoliday(newYear)).toBe(true);
        });

        test('should return true for Constitution Day', () => {
            const constitutionDay = new Date(2024, 4, 3);
            expect(window.isJapaneseHoliday(constitutionDay)).toBe(true);
        });

        test('should return false for regular day', () => {
            const regularDay = new Date(2024, 0, 15);
            expect(window.isJapaneseHoliday(regularDay)).toBe(false);
        });
    });

    describe('getJapaneseHolidayOrEventLabel', () => {
        test('should return non-empty label for New Year', () => {
            const newYear = new Date(2024, 0, 1);
            expect(window.getJapaneseHolidayOrEventLabel(newYear)).toBeTruthy();
        });

        test('should return non-empty label for Constitution Day', () => {
            const constitutionDay = new Date(2024, 4, 3);
            expect(window.getJapaneseHolidayOrEventLabel(constitutionDay)).toBeTruthy();
        });

        test('should return empty string for regular day', () => {
            const regularDay = new Date(2024, 0, 15);
            expect(window.getJapaneseHolidayOrEventLabel(regularDay)).toBe('');
        });
    });

    describe('shortenScheduleTitle', () => {
        test('should return strings for known types', () => {
            expect(typeof window.shortenScheduleTitle('practice_match')).toBe('string');
            expect(typeof window.shortenScheduleTitle('official_match')).toBe('string');
        });

        test('should return original title for unknown types', () => {
            expect(window.shortenScheduleTitle('Unknown Type')).toBe('Unknown Type');
        });
    });

    describe('generateScheduleCard', () => {
        test('should generate schedule card HTML', () => {
            const schedule = {
                id: 1,
                type: 'practice',
                gender: 'male',
                place: 'Test Place'
            };

            const cardHTML = window.generateScheduleCard(schedule);
            expect(cardHTML).toContain('schedule-card');
        });

        test('should generate card with practice class for practice type', () => {
            const schedule = { id: 1, type: 'practice', gender: 'male' };
            const cardHTML = window.generateScheduleCard(schedule);
            expect(cardHTML).toContain('practice');
        });

        test('should generate card with match class for match type', () => {
            const schedule = { id: 1, type: 'official_match', gender: 'male' };
            const cardHTML = window.generateScheduleCard(schedule);
            expect(cardHTML).toContain('match');
        });

        test('should generate simple mode card', () => {
            const schedule = { id: 1, type: 'practice', gender: 'male' };
            const cardHTML = window.generateScheduleCard(schedule, { simpleMode: true });
            expect(cardHTML).toContain('schedule-line-1');
        });
    });

    describe('getScheduleHTML', () => {
        test('should return empty string when no schedules', () => {
            window.schedules = {};
            expect(window.getScheduleHTML('2024-01-15')).toBe('');
        });

        test('should return HTML for schedules', () => {
            window.schedules = {
                '2024-01-15': [
                    { id: 1, type: 'practice' },
                    { id: 2, type: 'practice_match' }
                ]
            };

            const html = window.getScheduleHTML('2024-01-15');
            expect(html).toBeTruthy();
        });

        test('should show more count when schedules exceed max', () => {
            window.schedules = {
                '2024-01-15': [
                    { id: 1, type: 'practice' },
                    { id: 2, type: 'practice' },
                    { id: 3, type: 'practice' },
                    { id: 4, type: 'practice' }
                ]
            };

            const html = window.getScheduleHTML('2024-01-15');
            expect(html).toContain('+');
        });
    });

    describe('renderCalendar', () => {
        test('should not throw error when calendar elements do not exist', () => {
            expect(() => {
                window.renderCalendar();
            }).not.toThrow();
        });

        test('should render calendar when elements exist', () => {
            const calendarGrid = document.createElement('div');
            calendarGrid.id = 'calendar-grid';
            document.body.appendChild(calendarGrid);

            const calendarTitle = document.createElement('div');
            calendarTitle.id = 'calendar-title';
            document.body.appendChild(calendarTitle);

            window.renderCalendar();

            expect(calendarTitle.textContent).toContain('2024');
            expect(calendarTitle.textContent).toContain('1');
        });
    });
});
