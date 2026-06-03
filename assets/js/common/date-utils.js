/**
 * AidUnite 日付・時間ユーティリティ
 *
 * プロジェクト全体で使用される日付・時間関連の共通関数を提供します。
 * 重複していた関数を統一し、保守性を向上させます。
 *
 * @version 1.0.0
 * @created 2024-12
 */

// 既に定義されている場合は再定義しない（重複読み込み対策）
if (typeof AidUniteDateUtils === 'undefined') {
class AidUniteDateUtils {

    /**
     * 日付をローカル形式（YYYY-MM-DD）にフォーマット
     *
     * @param {Date} date - フォーマットする日付オブジェクト
     * @returns {string} YYYY-MM-DD形式の文字列
     *
     * @example
     * const today = new Date();
     * const formatted = AidUniteDateUtils.formatDateLocal(today);
     * console.log(formatted); // "2024-12-15"
     */
    static formatDateLocal(date) {
        if (!date || !(date instanceof Date)) {
            console.warn('AidUniteDateUtils.formatDateLocal: 有効なDateオブジェクトが必要です');
            return '';
        }

        const y = date.getFullYear();
        const m = (date.getMonth() + 1).toString().padStart(2, '0');
        const d = date.getDate().toString().padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    /**
     * 時間範囲をフォーマット（開始時間～終了時間）
     *
     * @param {string} startTime - 開始時間（HH:MM形式）
     * @param {string} endTime - 終了時間（HH:MM形式）
     * @param {string} defaultText - 時間が未設定の場合のデフォルトテキスト
     * @returns {string} フォーマットされた時間範囲文字列
     *
     * @example
     * const timeRange = AidUniteDateUtils.formatTime('09:00', '12:00');
     * console.log(timeRange); // "09:00～12:00"
     */
    static formatTime(startTime, endTime, defaultText = '時間未設定') {
        if (!startTime || !endTime) {
            return defaultText;
        }
        return `${startTime}～${endTime}`;
    }

    /**
     * 日付文字列を表示用にフォーマット
     *
     * @param {string} dateString - 日付文字列（YYYY-MM-DD形式）
     * @param {string} format - フォーマット形式（'YYYY/MM/DD', 'MM/DD', 'MM月DD日'など）
     * @returns {string} フォーマットされた日付文字列
     *
     * @example
     * const formatted = AidUniteDateUtils.formatDateForDisplay('2024-12-15', 'YYYY/MM/DD');
     * console.log(formatted); // "2024/12/15"
     */
    static formatDateForDisplay(dateString, format = 'YYYY/MM/DD') {
        if (!dateString) return '';

        try {
            const date = new Date(dateString + 'T00:00:00');
            if (isNaN(date.getTime())) {
                console.warn('AidUniteDateUtils.formatDateForDisplay: 無効な日付文字列です', dateString);
                return dateString;
            }

            const year = date.getFullYear();
            const month = (date.getMonth() + 1).toString().padStart(2, '0');
            const day = date.getDate().toString().padStart(2, '0');

            return format
                .replace('YYYY', year)
                .replace('MM', month)
                .replace('DD', day);
        } catch (error) {
            console.error('AidUniteDateUtils.formatDateForDisplay: エラーが発生しました', error);
            return dateString;
        }
    }

    /**
     * 今日の日付かどうかをチェック
     *
     * @param {string} dateString - チェックする日付文字列（YYYY-MM-DD形式）
     * @returns {boolean} 今日の日付の場合true
     *
     * @example
     * const isToday = AidUniteDateUtils.isToday('2024-12-15');
     * console.log(isToday); // true or false
     */
    static isToday(dateString) {
        if (!dateString) return false;

        const today = new Date();
        const todayString = this.formatDateLocal(today);
        return dateString === todayString;
    }

    /**
     * 日付文字列が有効かどうかをチェック
     *
     * @param {string} dateString - チェックする日付文字列
     * @returns {boolean} 有効な日付の場合true
     *
     * @example
     * const isValid = AidUniteDateUtils.isValidDate('2024-12-15');
     * console.log(isValid); // true
     */
    static isValidDate(dateString) {
        if (!dateString) return false;

        const date = new Date(dateString + 'T00:00:00');
        return !isNaN(date.getTime()) && /^\d{4}-\d{2}-\d{2}$/.test(dateString);
    }

    /**
     * 現在の日時を取得（デバッグ用）
     *
     * @returns {string} 現在の日時文字列
     */
    static getCurrentDateTime() {
        const now = new Date();
        return now.toLocaleString('ja-JP');
    }

    /**
     * タイムスタンプを時間形式にフォーマット（チャット用）
     *
     * @param {number|string} timestamp - タイムスタンプ（ミリ秒）またはISO日時文字列
     * @returns {string} HH:MM形式の時間文字列
     *
     * @example
     * const time = AidUniteDateUtils.formatTimeFromTimestamp(Date.now());
     * console.log(time); // "14:30"
     */
    static formatTimeFromTimestamp(timestamp) {
        if (!timestamp) return '';

        const date = new Date(timestamp);
        if (isNaN(date.getTime())) {
            console.warn('AidUniteDateUtils.formatTimeFromTimestamp: 無効なタイムスタンプです', timestamp);
            return '';
        }

        return date.toLocaleTimeString('ja-JP', {
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    /**
     * 相対時間をフォーマット（チャット用：○分前、○時間前など）
     *
     * @param {number|string} dateString - 日時文字列またはタイムスタンプ
     * @returns {string} 相対時間文字列（例: "5分前"、"2時間前"）
     *
     * @example
     * const relative = AidUniteDateUtils.formatRelativeTime(new Date(Date.now() - 300000));
     * console.log(relative); // "5分前"
     */
    static formatRelativeTime(dateString) {
        if (!dateString) return '';

        const date = new Date(dateString);
        if (isNaN(date.getTime())) {
            console.warn('AidUniteDateUtils.formatRelativeTime: 無効な日時です', dateString);
            return '';
        }

        const now = new Date();
        const diff = now - date;

        if (diff < 60000) { // 1分未満
            return '今';
        } else if (diff < 3600000) { // 1時間未満
            return Math.floor(diff / 60000) + '分前';
        } else if (diff < 86400000) { // 1日未満
            return Math.floor(diff / 3600000) + '時間前';
        } else {
            return date.toLocaleDateString('ja-JP');
        }
    }

    /**
     * 日付を日本語形式でフォーマット（曜日付き）
     *
     * @param {string} dateString - 日付文字列（YYYY-MM-DD形式）
     * @returns {string} 日本語形式の日付文字列（例: "2024年12月15日（日曜日）"）
     *
     * @example
     * const formatted = AidUniteDateUtils.formatDateJapanese('2024-12-15');
     * console.log(formatted); // "2024年12月15日（日曜日）"
     */
    static formatDateJapanese(dateString) {
        if (!dateString) return '';

        try {
            const date = new Date(dateString + 'T00:00:00');
            if (isNaN(date.getTime())) {
                console.warn('AidUniteDateUtils.formatDateJapanese: 無効な日付文字列です', dateString);
                return dateString;
            }

            return date.toLocaleDateString('ja-JP', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                weekday: 'long'
            });
        } catch (error) {
            console.error('AidUniteDateUtils.formatDateJapanese: エラーが発生しました', error);
            return dateString;
        }
    }

    /**
     * 日付を「M/D（曜日）」形式でフォーマット（マイページ用）
     *
     * @param {string} dateString - 日付文字列（YYYY-MM-DD形式）
     * @returns {string} フォーマットされた日付文字列（例: "1/18（土）"）
     *
     * @example
     * const formatted = AidUniteDateUtils.formatDateWithWeekday('2024-01-18');
     * console.log(formatted); // "1/18（土）"
     */
    static formatDateWithWeekday(dateString) {
        if (!dateString) return '';

        try {
            const date = new Date(dateString + 'T00:00:00');
            if (isNaN(date.getTime())) {
                console.warn('AidUniteDateUtils.formatDateWithWeekday: 無効な日付文字列です', dateString);
                return dateString;
            }

            const month = date.getMonth() + 1;
            const day = date.getDate();
            const weekday = ['日', '月', '火', '水', '木', '金', '土'][date.getDay()];
            return `${month}/${day}（${weekday}）`;
        } catch (error) {
            console.error('AidUniteDateUtils.formatDateWithWeekday: エラーが発生しました', error);
            return dateString;
        }
    }
}

// 後方互換性のためのグローバル関数（段階的移行用）
// 注意: 将来的に削除予定
if (typeof window !== 'undefined') {
    // 既存の関数名での後方互換性
    window.formatDateLocal = AidUniteDateUtils.formatDateLocal.bind(AidUniteDateUtils);
    window.formatTime = AidUniteDateUtils.formatTime.bind(AidUniteDateUtils);
    window.formatDateForDisplay = AidUniteDateUtils.formatDateForDisplay.bind(AidUniteDateUtils);
    window.isToday = AidUniteDateUtils.isToday.bind(AidUniteDateUtils);

    // タイムスタンプ用のformatTime（チャットページ用）
    // 注意: 既存のformatTimeと競合する可能性があるため、使用時は注意
    window.formatTimeFromTimestamp = AidUniteDateUtils.formatTimeFromTimestamp.bind(AidUniteDateUtils);
    window.formatRelativeTime = AidUniteDateUtils.formatRelativeTime.bind(AidUniteDateUtils);
    window.formatDateJapanese = AidUniteDateUtils.formatDateJapanese.bind(AidUniteDateUtils);
    window.formatDateWithWeekday = AidUniteDateUtils.formatDateWithWeekday.bind(AidUniteDateUtils);
}

// モジュールエクスポート（ES6モジュール対応）
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AidUniteDateUtils;
}

// AMD対応
if (typeof define === 'function' && define.amd) {
    define([], function() {
        return AidUniteDateUtils;
    });
}

} // if (typeof AidUniteDateUtils === 'undefined') の終了
