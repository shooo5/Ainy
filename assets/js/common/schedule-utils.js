/**
 * AidUnite スケジュールユーティリティ
 *
 * プロジェクト全体で使用されるスケジュール関連の共通関数を提供します。
 * 重複していた関数を統一し、保守性を向上させます。
 *
 * @version 1.0.0
 * @created 2024-12
 */

class AidUniteScheduleUtils {

    /** 登録 Step1 と同じ目的ラベル */
    static SCHEDULE_INTENT_LABELS = {
        confirmed: '確定の予定',
        recruit: '試合を募集',
        tentative: '仮押さえ',
    };

    /**
     * intent を confirmed / recruit / tentative に正規化（API の intent のみ使用）
     *
     * @param {Object} schedule
     * @returns {'confirmed'|'recruit'|'tentative'}
     */
    static normalizeScheduleIntent(schedule) {
        const raw = String(schedule?.intent || '').toLowerCase().trim();
        if (raw === 'confirmed' || raw === 'recruit' || raw === 'tentative') {
            return raw;
        }
        const certainty = String(schedule?.certainty || '').toLowerCase().trim();
        if (certainty === 'tentative') {
            return 'tentative';
        }
        return 'confirmed';
    }

    /**
     * @param {Object} schedule
     * @returns {string}
     */
    static getScheduleIntentLabel(schedule) {
        const intent = this.normalizeScheduleIntent(schedule);
        return this.SCHEDULE_INTENT_LABELS[intent] || '—';
    }

    /**
     * カレンダーフィルタ（data-filter）に一致するか
     *
     * @param {Object} schedule
     * @param {Set<string>} filters 'all' | 'confirmed' | 'recruit' | 'tentative'
     * @returns {boolean}
     */
    static matchesScheduleFilter(schedule, filters) {
        if (!filters || filters.has('all')) {
            return true;
        }
        const intent = this.normalizeScheduleIntent(schedule);
        return filters.has(intent);
    }

    /**
     * スケジュールタイプに応じたアイコンを取得
     *
     * @param {string} type - スケジュールタイプ
     * @returns {string} 対応する絵文字アイコン
     *
     * @example
     * const icon = AidUniteScheduleUtils.getScheduleIcon('公式試合');
     * console.log(icon); // "🏆"
     */
    static getScheduleIcon(type) {
        if (typeof AidUniteThemeIcons !== 'undefined' && AidUniteThemeIcons.getScheduleIconBasename) {
            return AidUniteThemeIcons.getScheduleIconBasename(type);
        }
        if (!type) return 'calendar_month';

        const iconMap = {
            '公式試合': 'trophy',
            '練習試合': 'handshake',
            '練習試合（募集）': 'handshake',
            '練習試合（仮）': 'handshake',
            '練習': 'exercise',
            '練習（仮）': 'exercise',
            '合同練習': 'group',
            '合同練習（募集）': 'group',
            '合宿': 'camping',
            '合宿（仮）': 'camping',
            '遠征': 'flight_takeoff',
            '遠征（仮）': 'flight_takeoff',
            'イベント': 'celebration',
            'イベント（仮）': 'celebration',
            '休み': 'airline_seat_recline_extra',
            '休み（仮）': 'airline_seat_recline_extra',
            '会議': 'list_alt_add',
            '会議（仮）': 'list_alt_add',
            'ミーティング': 'list_alt_add',
            'ミーティング（仮）': 'list_alt_add',
            '未定': 'question_mark',
            '未定（仮）': 'question_mark',
            'その他': 'calendar_month'
        };

        if (iconMap[type]) {
            return iconMap[type];
        }

        for (const [key, icon] of Object.entries(iconMap)) {
            if (type.includes(key) || key.includes(type)) {
                return icon;
            }
        }

        return 'calendar_month';
    }

    /**
     * スケジュール種別のインライン SVG HTML
     *
     * @param {string} type
     * @param {number} size
     * @returns {string}
     */
    static getScheduleIconHtml(type, size = 20) {
        if (typeof AidUniteThemeIcons !== 'undefined' && AidUniteThemeIcons.scheduleIconHtml) {
            return AidUniteThemeIcons.scheduleIconHtml(type, size);
        }
        return '';
    }

    /**
     * スケジュールタイプの表示名を取得
     *
     * @param {string} type - スケジュールタイプ
     * @returns {string} 表示用のタイプ名
     *
     * @example
     * const displayName = AidUniteScheduleUtils.getScheduleTypeDisplayName('練習（仮）');
     * console.log(displayName); // "練習"
     */
    static getScheduleTypeDisplayName(type) {
        if (!type) return 'その他';

        // 仮の表記を除去
        const cleanType = type.replace(/（仮）$/, '').replace(/（募集）$/, '');

        return cleanType || 'その他';
    }

    /**
     * スケジュールの重要度を取得
     *
     * @param {string} type - スケジュールタイプ
     * @returns {number} 重要度（1-5、5が最重要）
     *
     * @example
     * const priority = AidUniteScheduleUtils.getSchedulePriority('公式試合');
     * console.log(priority); // 5
     */
    static getSchedulePriority(type) {
        if (!type) return 1;

        const priorityMap = {
            '公式試合': 5,
            '練習試合': 4,
            '練習試合（募集）': 4,
            '練習試合（仮）': 4,
            '合宿': 4,
            '合宿（仮）': 4,
            '遠征': 4,
            '遠征（仮）': 4,
            '合同練習': 3,
            '合同練習（募集）': 3,
            '練習': 3,
            '練習（仮）': 3,
            'イベント': 2,
            'イベント（仮）': 2,
            '会議': 2,
            '会議（仮）': 2,
            '休み': 1,
            '休み（仮）': 1
        };

        return priorityMap[type] || 1;
    }

    /**
     * スケジュールの色を取得
     *
     * @param {string} type - スケジュールタイプ
     * @returns {string} CSSクラス名または色コード
     *
     * @example
     * const colorClass = AidUniteScheduleUtils.getScheduleColor('公式試合');
     * console.log(colorClass); // "schedule-official"
     */
    static getScheduleColor(type) {
        if (!type) return 'schedule-default';

        const colorMap = {
            '公式試合': 'schedule-official',
            '練習試合': 'schedule-match',
            '練習試合（募集）': 'schedule-match',
            '練習試合（仮）': 'schedule-match',
            '合宿': 'schedule-camp',
            '合宿（仮）': 'schedule-camp',
            '遠征': 'schedule-trip',
            '遠征（仮）': 'schedule-trip',
            '合同練習': 'schedule-joint',
            '合同練習（募集）': 'schedule-joint',
            '練習': 'schedule-practice',
            '練習（仮）': 'schedule-practice',
            'イベント': 'schedule-event',
            'イベント（仮）': 'schedule-event',
            '会議': 'schedule-meeting',
            '会議（仮）': 'schedule-meeting',
            '休み': 'schedule-rest',
            '休み（仮）': 'schedule-rest'
        };

        return colorMap[type] || 'schedule-default';
    }

    /**
     * スケジュールの詳細情報を取得
     *
     * @param {string} type - スケジュールタイプ
     * @returns {Object} スケジュールの詳細情報
     *
     * @example
     * const info = AidUniteScheduleUtils.getScheduleInfo('公式試合');
     * console.log(info); // { icon: "🏆", displayName: "公式試合", priority: 5, color: "schedule-official" }
     */
    static getScheduleInfo(type) {
        return {
            icon: this.getScheduleIcon(type),
            displayName: this.getScheduleTypeDisplayName(type),
            priority: this.getSchedulePriority(type),
            color: this.getScheduleColor(type),
            originalType: type
        };
    }

    /**
     * スケジュールタイプの一覧を取得
     *
     * @returns {Array} スケジュールタイプの配列
     *
     * @example
     * const types = AidUniteScheduleUtils.getScheduleTypes();
     * console.log(types); // ["公式試合", "練習試合", "練習", ...]
     */
    static getScheduleTypes() {
        return [
            '公式試合',
            '練習試合',
            '練習試合（募集）',
            '練習試合（仮）',
            '合宿',
            '合宿（仮）',
            '遠征',
            '遠征（仮）',
            '合同練習',
            '合同練習（募集）',
            '練習',
            '練習（仮）',
            'イベント',
            'イベント（仮）',
            '会議',
            '会議（仮）',
            '休み',
            '休み（仮）',
            'その他'
        ];
    }

    /**
     * スケジュールタイプの選択肢を取得（フォーム用）
     *
     * @returns {Array} 選択肢の配列
     *
     * @example
     * const options = AidUniteScheduleUtils.getScheduleTypeOptions();
     * console.log(options); // [{ value: "公式試合", label: "公式試合", icon: "🏆" }, ...]
     */
    static getScheduleTypeOptions() {
        return this.getScheduleTypes().map(type => ({
            value: type,
            label: this.getScheduleTypeDisplayName(type),
            icon: this.getScheduleIcon(type),
            priority: this.getSchedulePriority(type)
        }));
    }

    /**
     * 性別を日本語に変換
     *
     * @param {string} gender - 性別の内部値（'male', 'female', 'both'）
     * @returns {string} 日本語ラベル
     *
     * @example
     * const label = AidUniteScheduleUtils.getGenderLabel('male');
     * console.log(label); // "男子"
     */
    /**
     * 募集の対戦相手性別（schedule_gender）。MVP は男子／女子のみ。
     * ※ 会場の「どちらでも可」は getVenueConditionLabel を使う（both は会場専用）。
     */
    static getGenderLabel(gender) {
        const g = String(gender || '').toLowerCase().trim();
        const labels = {
            male: '男子',
            female: '女子',
        };
        return labels[g] || '—';
    }

    /**
     * 会場条件（schedule_place / venue_condition）
     */
    static getVenueConditionLabel(venue) {
        const v = String(venue || '').toLowerCase().trim();
        const labels = {
            home: 'ホーム',
            away: 'アウェイ',
            either: 'どちらでも可',
            both: 'どちらでも可',
            undecided: '未定',
        };
        return labels[v] || (v !== '' ? v : '—');
    }

    /**
     * 会場条件を日本語に変換
     *
     * @param {string} venue - 会場条件の内部値（'home', 'away', 'both', 'either'）
     * @returns {string} 日本語ラベル
     *
     * @example
     * const label = AidUniteScheduleUtils.getVenueLabel('home');
     * console.log(label); // "ホーム"
     */
    static getVenueLabel(venue) {
        return AidUniteScheduleUtils.getVenueConditionLabel(venue);
    }
}

// 後方互換性のためのグローバル関数（段階的移行用）
// 注意: 将来的に削除予定
if (typeof window !== 'undefined') {
    // 既存の関数名での後方互換性
    window.getScheduleIcon = AidUniteScheduleUtils.getScheduleIcon.bind(AidUniteScheduleUtils);
    window.getGenderLabel = AidUniteScheduleUtils.getGenderLabel.bind(AidUniteScheduleUtils);
    window.getVenueLabel = AidUniteScheduleUtils.getVenueLabel.bind(AidUniteScheduleUtils);
    window.normalizeScheduleIntent = AidUniteScheduleUtils.normalizeScheduleIntent.bind(AidUniteScheduleUtils);
    window.matchesScheduleFilter = AidUniteScheduleUtils.matchesScheduleFilter.bind(AidUniteScheduleUtils);
}

// モジュールエクスポート（ES6モジュール対応）
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AidUniteScheduleUtils;
}

// AMD対応
if (typeof define === 'function' && define.amd) {
    define([], function() {
        return AidUniteScheduleUtils;
    });
}
