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
        recruit: '試合の募集',
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

    /**
     * REST / 表示用オブジェクトから会場条件キーを1本化（旧キーはサーバー側エイリアスで補完済み想定）
     *
     * @param {Object} schedule
     * @returns {string}
     */
    static resolveVenueConditionRaw(schedule) {
        const s = schedule || {};
        const isKeyword = (v) => ['home', 'away', 'both', 'either'].includes(String(v || '').toLowerCase());
        return String(
            s.schedule_place
            || s.venue_condition
            || s.place_option
            || (isKeyword(s.place) ? s.place : '')
            || ''
        );
    }

    /**
     * 終日表示を許可する種別か（時間なし時に「終日」を出す）
     *
     * @param {Object} schedule
     * @returns {boolean}
     */
    static scheduleAllowsAllDayDisplay(schedule) {
        const s = schedule || {};
        const uiKind = String(s.ui_schedule_kind || '').toLowerCase();
        if (['rest', 'tentative', 'camp', 'expedition'].includes(uiKind)) {
            return true;
        }
        const intent = AidUniteScheduleUtils.normalizeScheduleIntent(s);
        const type = String(s.schedule_type || s.type || '').trim();
        if (intent === 'tentative' || /（仮）|\(仮\)/.test(type)) {
            return true;
        }
        return /(休み|合宿|遠征)/.test(type);
    }

    /**
     * 募集以外で会場未登録（either デフォルト等）か
     *
     * @param {boolean} isRecruit
     * @param {string} raw venue_condition キー
     * @param {string} venueName
     * @param {string} place PHP 変換後の place
     * @returns {boolean}
     */
    static isUnsetVenueForNonRecruit(isRecruit, raw, venueName, place) {
        if (isRecruit || String(venueName || '').trim() !== '') {
            return false;
        }
        const r = String(raw || '').toLowerCase().trim();
        const p = String(place || '').trim();
        const unsetRaw = r === '' || r === 'either' || r === 'both';
        const unsetPlace = p === 'どちらでも' || p === 'どちらでも可' || p === '未設定';
        return unsetRaw || unsetPlace;
    }

    /**
     * 会場表示用ラベル（リスト・詳細モーダル共通）
     *
     * @param {Object} schedule
     * @returns {string}
     */
    static resolveScheduleVenueLabel(schedule) {
        const s = schedule || {};
        let intent = AidUniteScheduleUtils.normalizeScheduleIntent(s);
        const matching = s.matching === 1 || s.matching === '1' || s.matching === true;
        if (intent === 'confirmed' && matching) {
            intent = 'recruit';
        }
        const isRecruit = intent === 'recruit';
        const raw = AidUniteScheduleUtils.resolveVenueConditionRaw(s).toLowerCase().trim();
        const venueName = String(s.venue_name || '').trim();
        const place = String(s.place || '').trim();

        if (AidUniteScheduleUtils.isUnsetVenueForNonRecruit(isRecruit, raw, venueName, place)) {
            return '';
        }

        let conditionLabel = '';
        if (raw) {
            const label = AidUniteScheduleUtils.getVenueConditionLabel(raw);
            conditionLabel = label && label !== '—' ? label : '';
        }

        if (conditionLabel && venueName) {
            return `${conditionLabel}/${venueName}`;
        }
        if (conditionLabel) {
            return conditionLabel;
        }

        const placeKeywords = ['home', 'away', 'either', 'both'];
        if (place && !placeKeywords.includes(place.toLowerCase())) {
            return place;
        }
        return venueName || '';
    }

    /**
     * 出欠 status: attending/not_attending/pending → present/absent/no_response（REST 送信・表示用）
     *
     * @param {string} raw
     * @returns {string}
     */
    static normalizeAttendanceStatus(raw) {
        const s = String(raw || '').toLowerCase().trim();
        const map = {
            attending: 'present',
            present: 'present',
            not_attending: 'absent',
            absent: 'absent',
            late: 'late',
            leave_early: 'leave_early',
            no_response: 'no_response',
            pending: 'no_response',
            maybe: 'no_response',
        };
        return map[s] || s;
    }

    /**
     * get-user-schedules 等の1件を正規化（id / date / place / gender / 出欠エイリアス）
     *
     * @param {Object} schedule
     * @returns {Object}
     */
    static normalizeScheduleRecord(schedule) {
        if (!schedule || typeof schedule !== 'object') {
            return schedule;
        }
        const s = Object.assign({}, schedule);
        if (!s.id && (s.post_id || s.schedule_id)) {
            s.id = s.post_id || s.schedule_id;
        }
        const rawDate = s.date || s.schedule_date || s.start_date;
        if (rawDate) {
            s.date = String(rawDate).trim().substring(0, 10);
        }
        const place = String(
            s.place_type || s.schedule_place || s.venue_condition || s.place || ''
        ).toLowerCase().trim();
        if (place) {
            s.place_type = place;
            s.schedule_place = place;
            s.venue_condition = place;
        }
        const gender = String(
            s.gender || s.schedule_gender || s.matching_gender_condition || s.gender_condition || ''
        ).toLowerCase().trim();
        if (gender) {
            s.gender = gender;
            s.schedule_gender = gender;
            s.matching_gender_condition = gender;
            s.gender_condition = gender;
        }
        if (s.attendance_status != null && s.attendance_status !== '') {
            s.attendance_status = AidUniteScheduleUtils.normalizeAttendanceStatus(s.attendance_status);
        } else if (s.status != null && s.status !== '' && s.type === 'attendance') {
            s.attendance_status = AidUniteScheduleUtils.normalizeAttendanceStatus(s.status);
        }
        return s;
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
    window.normalizeAttendanceStatus = AidUniteScheduleUtils.normalizeAttendanceStatus.bind(AidUniteScheduleUtils);
    window.normalizeScheduleRecord = AidUniteScheduleUtils.normalizeScheduleRecord.bind(AidUniteScheduleUtils);
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
