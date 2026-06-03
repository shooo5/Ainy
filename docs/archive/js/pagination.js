/**
 * AidUnite ページネーションコンポーネント
 *
 * プロジェクト全体で使用されるページネーション機能を提供します。
 *
 * @version 1.0.0
 * @created 2024-12
 */

class AidUnitePagination {
    /**
     * コンストラクタ
     *
     * @param {Object} options - オプション
     * @param {number} options.currentPage - 現在のページ番号
     * @param {number} options.totalPages - 総ページ数
     * @param {number} options.perPage - 1ページあたりのアイテム数
     * @param {Function} options.onPageChange - ページ変更時のコールバック
     * @param {string} options.containerId - コンテナ要素のID
     */
    constructor(options = {}) {
        this.currentPage = options.currentPage || 1;
        this.totalPages = options.totalPages || 1;
        this.perPage = options.perPage || 20;
        this.onPageChange = options.onPageChange || (() => {});
        this.containerId = options.containerId || 'pagination-container';
        this.maxVisiblePages = options.maxVisiblePages || 5;
    }

    /**
     * ページネーションUIをレンダリング
     *
     * @returns {HTMLElement} ページネーション要素
     */
    render() {
        const container = document.getElementById(this.containerId) || document.createElement('div');
        container.id = this.containerId;
        container.className = 'aidunite-pagination';

        if (this.totalPages <= 1) {
            container.innerHTML = '';
            return container;
        }

        let html = '<div class="pagination-wrapper">';

        // 前へボタン
        if (this.currentPage > 1) {
            html += `<button class="pagination-btn prev" data-page="${this.currentPage - 1}">‹ 前へ</button>`;
        } else {
            html += '<button class="pagination-btn prev disabled" disabled>‹ 前へ</button>';
        }

        // ページ番号
        html += '<div class="pagination-pages">';

        const startPage = Math.max(1, this.currentPage - Math.floor(this.maxVisiblePages / 2));
        const endPage = Math.min(this.totalPages, startPage + this.maxVisiblePages - 1);

        // 最初のページ
        if (startPage > 1) {
            html += `<button class="pagination-page" data-page="1">1</button>`;
            if (startPage > 2) {
                html += '<span class="pagination-ellipsis">...</span>';
            }
        }

        // ページ番号ボタン
        for (let i = startPage; i <= endPage; i++) {
            if (i === this.currentPage) {
                html += `<button class="pagination-page active" data-page="${i}">${i}</button>`;
            } else {
                html += `<button class="pagination-page" data-page="${i}">${i}</button>`;
            }
        }

        // 最後のページ
        if (endPage < this.totalPages) {
            if (endPage < this.totalPages - 1) {
                html += '<span class="pagination-ellipsis">...</span>';
            }
            html += `<button class="pagination-page" data-page="${this.totalPages}">${this.totalPages}</button>`;
        }

        html += '</div>';

        // 次へボタン
        if (this.currentPage < this.totalPages) {
            html += `<button class="pagination-btn next" data-page="${this.currentPage + 1}">次へ ›</button>`;
        } else {
            html += '<button class="pagination-btn next disabled" disabled>次へ ›</button>';
        }

        html += '</div>';

        container.innerHTML = html;

        // イベントリスナーを設定
        this.attachEventListeners(container);

        return container;
    }

    /**
     * イベントリスナーを設定
     *
     * @param {HTMLElement} container - コンテナ要素
     */
    attachEventListeners(container) {
        const buttons = container.querySelectorAll('.pagination-btn, .pagination-page');
        buttons.forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                const page = parseInt(button.dataset.page);
                if (page && page !== this.currentPage) {
                    this.goToPage(page);
                }
            });
        });
    }

    /**
     * 指定されたページに移動
     *
     * @param {number} page - ページ番号
     */
    goToPage(page) {
        if (page >= 1 && page <= this.totalPages) {
            this.currentPage = page;
            this.onPageChange(page);
            this.render();
        }
    }

    /**
     * ページ情報を更新
     *
     * @param {Object} options - 更新オプション
     */
    update(options = {}) {
        if (options.currentPage !== undefined) {
            this.currentPage = options.currentPage;
        }
        if (options.totalPages !== undefined) {
            this.totalPages = options.totalPages;
        }
        if (options.perPage !== undefined) {
            this.perPage = options.perPage;
        }
        this.render();
    }
}

// グローバルに公開
if (typeof window !== 'undefined') {
    window.AidUnitePagination = AidUnitePagination;
}

// モジュールエクスポート
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AidUnitePagination;
}
