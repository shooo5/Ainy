/**
 * AidUnitePagination テスト
 * ページネーションコンポーネントの単体テスト
 */

// ソースファイルを直接requireしてカバレッジを計測可能にする
const AidUnitePagination = require('@archive/js/pagination.js');

describe('AidUnitePagination', () => {
    let container;
    
    beforeEach(() => {
        container = document.createElement('div');
        container.id = 'pagination-container';
        document.body.appendChild(container);
    });
    
    afterEach(() => {
        document.body.innerHTML = '';
    });
    
    describe('constructor', () => {
        test('should initialize with default values', () => {
            const pagination = new AidUnitePagination();
            expect(pagination.currentPage).toBe(1);
            expect(pagination.totalPages).toBe(1);
            expect(pagination.perPage).toBe(20);
            expect(pagination.containerId).toBe('pagination-container');
        });
        
        test('should initialize with custom values', () => {
            const onPageChange = jest.fn();
            const pagination = new AidUnitePagination({
                currentPage: 3,
                totalPages: 10,
                perPage: 15,
                onPageChange: onPageChange,
                containerId: 'custom-container',
                maxVisiblePages: 7
            });
            
            expect(pagination.currentPage).toBe(3);
            expect(pagination.totalPages).toBe(10);
            expect(pagination.perPage).toBe(15);
            expect(pagination.onPageChange).toBe(onPageChange);
            expect(pagination.containerId).toBe('custom-container');
            expect(pagination.maxVisiblePages).toBe(7);
        });
    });
    
    describe('render', () => {
        test('should render pagination for multiple pages', () => {
            const pagination = new AidUnitePagination({
                currentPage: 1,
                totalPages: 5,
                containerId: 'pagination-container'
            });
            
            const element = pagination.render();
            expect(element).toBeInstanceOf(HTMLElement);
            expect(element.querySelector('.pagination-wrapper')).toBeTruthy();
            expect(element.querySelectorAll('.pagination-page').length).toBeGreaterThan(0);
        });
        
        test('should not render pagination for single page', () => {
            const pagination = new AidUnitePagination({
                currentPage: 1,
                totalPages: 1,
                containerId: 'pagination-container'
            });
            
            const element = pagination.render();
            expect(element.innerHTML).toBe('');
        });
        
        test('should highlight current page', () => {
            const pagination = new AidUnitePagination({
                currentPage: 3,
                totalPages: 5,
                containerId: 'pagination-container'
            });
            
            const element = pagination.render();
            const activeButton = element.querySelector('.pagination-page.active');
            expect(activeButton).toBeTruthy();
            expect(activeButton.dataset.page).toBe('3');
        });
        
        test('should disable prev button on first page', () => {
            const pagination = new AidUnitePagination({
                currentPage: 1,
                totalPages: 5,
                containerId: 'pagination-container'
            });
            
            const element = pagination.render();
            const prevButton = element.querySelector('.pagination-btn.prev');
            expect(prevButton.disabled).toBe(true);
            expect(prevButton.classList.contains('disabled')).toBe(true);
        });
        
        test('should disable next button on last page', () => {
            const pagination = new AidUnitePagination({
                currentPage: 5,
                totalPages: 5,
                containerId: 'pagination-container'
            });
            
            const element = pagination.render();
            const nextButton = element.querySelector('.pagination-btn.next');
            expect(nextButton.disabled).toBe(true);
            expect(nextButton.classList.contains('disabled')).toBe(true);
        });
    });
    
    describe('goToPage', () => {
        test('should change to valid page', () => {
            const onPageChange = jest.fn();
            const pagination = new AidUnitePagination({
                currentPage: 1,
                totalPages: 5,
                onPageChange: onPageChange,
                containerId: 'pagination-container'
            });
            
            pagination.render();
            pagination.goToPage(3);
            
            expect(pagination.currentPage).toBe(3);
            expect(onPageChange).toHaveBeenCalledWith(3);
        });
        
        test('should not change to invalid page (too low)', () => {
            const onPageChange = jest.fn();
            const pagination = new AidUnitePagination({
                currentPage: 3,
                totalPages: 5,
                onPageChange: onPageChange,
                containerId: 'pagination-container'
            });
            
            pagination.goToPage(0);
            
            expect(pagination.currentPage).toBe(3);
            expect(onPageChange).not.toHaveBeenCalled();
        });
        
        test('should not change to invalid page (too high)', () => {
            const onPageChange = jest.fn();
            const pagination = new AidUnitePagination({
                currentPage: 3,
                totalPages: 5,
                onPageChange: onPageChange,
                containerId: 'pagination-container'
            });
            
            pagination.goToPage(10);
            
            expect(pagination.currentPage).toBe(3);
            expect(onPageChange).not.toHaveBeenCalled();
        });
    });
    
    describe('update', () => {
        test('should update current page', () => {
            const pagination = new AidUnitePagination({
                currentPage: 1,
                totalPages: 5,
                containerId: 'pagination-container'
            });
            
            pagination.update({ currentPage: 3 });
            
            expect(pagination.currentPage).toBe(3);
        });
        
        test('should update total pages', () => {
            const pagination = new AidUnitePagination({
                currentPage: 1,
                totalPages: 5,
                containerId: 'pagination-container'
            });
            
            pagination.update({ totalPages: 10 });
            
            expect(pagination.totalPages).toBe(10);
        });
        
        test('should update per page', () => {
            const pagination = new AidUnitePagination({
                currentPage: 1,
                totalPages: 5,
                perPage: 20,
                containerId: 'pagination-container'
            });
            
            pagination.update({ perPage: 15 });
            
            expect(pagination.perPage).toBe(15);
        });
    });
    
    describe('event listeners', () => {
        test('should call onPageChange when page button is clicked', () => {
            const onPageChange = jest.fn();
            const pagination = new AidUnitePagination({
                currentPage: 1,
                totalPages: 5,
                onPageChange: onPageChange,
                containerId: 'pagination-container'
            });
            
            const element = pagination.render();
            const pageButton = element.querySelector('.pagination-page[data-page="2"]');
            
            if (pageButton) {
                pageButton.click();
                expect(onPageChange).toHaveBeenCalledWith(2);
            }
        });
    });
});
