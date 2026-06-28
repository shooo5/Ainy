/**
 * AidUnite スケジュールモーダルユーティリティ
 *
 * プロジェクト全体で使用されるスケジュール詳細表示・モーダル関連の共通関数を提供します。
 * 重複していた関数を統一し、保守性を向上させます。
 *
 * @version 1.0.0
 * @created 2024-12
 */

class AidUniteScheduleModal {
    static iconHtml(basename, size) {
        if (typeof AidUniteThemeIcons !== 'undefined') {
            return AidUniteThemeIcons.html(basename, size || 18);
        }
        return '';
    }

    /**
     * intentに応じて表示用typeを正規化（確定時は募集表記を除去）
     * @param {Object} schedule
     * @returns {string}
     */
    static getDisplayType(schedule = {}) {
        const rawType = String(schedule.type || '').trim();
        if (!rawType) return 'スケジュール';
        const intent = String(schedule.intent || '').toLowerCase();
        if (intent === 'confirmed') {
            return rawType
                .replace(/（募集）/g, '')
                .replace(/\(募集\)/g, '')
                .replace(/（募）/g, '')
                .replace(/\(募\)/g, '')
                .trim();
        }
        return rawType;
    }

    /**
     * 削除ゲート（GET schedule-dependencies の delete_gate.code）向けの案内文
     * @param {string} code
     * @returns {string}
     */
    static messageForDeleteGateCode(code) {
        switch (String(code || '')) {
            case 'match_pending':
                return '申請中のマッチ申請が紐づいているため、このままでは削除できません。先に申請を取り下げてから削除してください。';
            case 'match_committed':
                return '承認済み・成立済みのマッチが紐づいているため、このスケジュールを即時削除できません。試合のキャンセルや取り下げの後に再度お試しください。';
            default:
                return 'このスケジュールは現状では削除できません。';
        }
    }

    /**
     * REST 409 等の code に応じたユーザー向け文言（サーバー message をフォールバックに使用）
     * @param {string} code
     * @param {string} serverMessage
     * @returns {string}
     */
    static messageForScheduleErrorCode(code, serverMessage) {
        const fallback = serverMessage || '操作を完了できませんでした。';
        switch (String(code || '')) {
            case 'match_pending':
                return '申請中のマッチがあるため削除できません。先に申請を取り下げてください。';
            case 'match_committed':
                return '承認済み・成立済みのマッチがあるため削除できません。試合の取り下げ・キャンセル後に再度お試しください。';
            case 'schedule_edit_blocked':
                return '申請中または成立に関するマッチがあるため、日時・会場・目的などの重要項目を変更できません。先に申請の扱いを解消するか、メモのみ更新してください。';
            default:
                return fallback;
        }
    }

    /** @param {string} message @param {string} [type='info'] */
    static notify(message, type = 'info') {
        if (typeof showToastNotification === 'function') {
            showToastNotification(message, type);
        } else {
            console.warn('[AidUnite]', type, message);
        }
    }

    /**
     * OS confirm() の代替（削除・重要操作）
     * @param {{ title?: string, message: string, confirmLabel?: string, confirmVariant?: 'danger'|'primary', onConfirm?: Function }} opts
     */
    static confirmAction(opts) {
        const message = opts && opts.message ? String(opts.message) : '';
        if (typeof showConfirmModal !== 'function') {
            console.warn('[AidUnite] showConfirmModal unavailable:', message);
            return;
        }
        showConfirmModal({
            title: (opts && opts.title) || '削除確認',
            message,
            confirmLabel: (opts && opts.confirmLabel) || '削除する',
            cancelLabel: 'キャンセル',
            confirmVariant: (opts && opts.confirmVariant) || 'danger',
            onConfirm: (opts && typeof opts.onConfirm === 'function') ? opts.onConfirm : null
        });
    }

    /**
     * テキストを要素内・textarea 初期表示用にエスケープ
     * @param {string} str
     * @returns {string}
     */
    static escapeHtmlText(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /**
     * スケジュールIDを統一（id / post_id / schedule_id）
     * @param {Object} schedule
     * @returns {string}
     */
    static resolveScheduleId(schedule) {
        if (!schedule) {
            return '';
        }
        const raw = schedule.id ?? schedule.post_id ?? schedule.schedule_id;
        if (raw === undefined || raw === null || raw === '') {
            return '';
        }
        return String(raw);
    }

    /**
     * onclick 属性用に ID をエスケープ
     * @param {string|number} scheduleId
     * @returns {string}
     */
    static escapeScheduleIdForJs(scheduleId) {
        return JSON.stringify(String(scheduleId ?? ''));
    }

    /**
     * 仮スケジュールか（詳細の「確定」ボタン表示・APIの type 表記ゆれ対応）
     * @param {Object} schedule
     * @returns {boolean}
     */
    static isScheduleTentative(schedule) {
        if (!schedule) return false;
        if (typeof AidUniteScheduleUtils !== 'undefined' && AidUniteScheduleUtils.normalizeScheduleIntent) {
            return AidUniteScheduleUtils.normalizeScheduleIntent(schedule) === 'tentative';
        }
        return String(schedule.intent || '').toLowerCase() === 'tentative';
    }

    /**
     * スケジュール詳細を表示
     *
     * @param {string|number} scheduleId - スケジュールID
     * @param {Object} options - 表示オプション
     * @param {string} options.mode - 表示モード ('modal' | 'popup')
     * @param {Function} options.onClose - 閉じる時のコールバック
     * @param {Function} options.onEdit - 編集時のコールバック
     * @param {Function} options.onDelete - 削除時のコールバック
     *
     * @example
     * AidUniteScheduleModal.showDetail('123', { mode: 'modal' });
     */
    static showDetail(scheduleId, options = {}) {
        const {
            mode = 'modal',
            onClose = null,
            onEdit = null,
            onDelete = null
        } = options;

        console.log('スケジュール詳細表示:', scheduleId);

        // スケジュールを検索
        const schedule = this.findScheduleById(scheduleId);
        if (!schedule) {
            console.error('スケジュールが見つかりません:', scheduleId);
            return;
        }

        // 表示モードに応じて処理
        if (mode === 'popup') {
            this.showPopup(schedule, { onClose, onEdit, onDelete });
        } else {
            this.showModal(schedule, { onClose, onEdit, onDelete });
        }
    }

    /**
     * モーダル形式でスケジュール詳細を表示
     *
     * @param {Object} schedule - スケジュールオブジェクト
     * @param {Object} options - オプション
     */
    static showModal(schedule, options = {}) {
        const open = (matchRestricted) => {
            const modal = this.createModal(schedule, { ...options, matchRestricted });
            document.body.appendChild(modal);
            modal.style.display = 'flex';
            modal.classList.add('show');
        };
        const depScheduleIdModal = AidUniteScheduleModal.resolveScheduleId(schedule);
        if (typeof AidUniteAjaxUtils !== 'undefined' && typeof AidUniteAjaxUtils.getScheduleDependencies === 'function' && depScheduleIdModal) {
            AidUniteAjaxUtils.getScheduleDependencies({
                schedule_id: depScheduleIdModal,
                onSuccess: (data) => {
                    const dep = (data && data.dependencies) || {};
                    open(!!(dep.has_pending || dep.has_in_play));
                },
                onError: () => open(false)
            });
            return;
        }
        open(false);
    }

    /**
     * ポップアップ形式でスケジュール詳細を表示
     *
     * @param {Object} schedule - スケジュールオブジェクト
     * @param {Object} options - オプション
     */
    static showPopup(schedule, options = {}) {
        // 既存のポップアップを完全に削除
        this.closeAll();

        // 既存のポップアップ要素を強制削除
        const existingPopups = document.querySelectorAll('.schedule-detail-popup');
        existingPopups.forEach(popup => popup.remove());

        const open = (matchRestricted) => {
            const popup = this.createPopup(schedule, { ...options, matchRestricted });
            document.body.appendChild(popup);
            popup.style.display = 'flex';
            popup.classList.add('show');
        };
        const depScheduleId = AidUniteScheduleModal.resolveScheduleId(schedule);
        if (typeof AidUniteAjaxUtils !== 'undefined' && typeof AidUniteAjaxUtils.getScheduleDependencies === 'function' && depScheduleId) {
            AidUniteAjaxUtils.getScheduleDependencies({
                schedule_id: depScheduleId,
                onSuccess: (data) => {
                    const dep = (data && data.dependencies) || {};
                    open(!!(dep.has_pending || dep.has_in_play));
                },
                onError: () => open(false)
            });
            return;
        }
        open(false);
    }

    /**
     * モーダルを作成
     *
     * @param {Object} schedule - スケジュールオブジェクト
     * @param {Object} options - オプション
     * @returns {HTMLElement} モーダル要素
     */
    static createModal(schedule, options = {}) {
        const modal = document.createElement('div');
        modal.className = 'schedule-detail-modal';
        const scheduleId = AidUniteScheduleModal.resolveScheduleId(schedule);
        if (scheduleId) {
            modal.setAttribute('data-schedule-id', scheduleId);
        }
        modal.innerHTML = this.getModalHTML(schedule, options);

        this.attachModalEvents(modal, options);

        if (options.matchRestricted === undefined) {
            this.applyMatchRestrictedEditUi(modal, schedule);
        }

        return modal;
    }

    /**
     * ポップアップを作成
     *
     * @param {Object} schedule - スケジュールオブジェクト
     * @param {Object} options - オプション
     * @returns {HTMLElement} ポップアップ要素
     */
    static createPopup(schedule, options = {}) {
        const popup = document.createElement('div');
        popup.id = 'schedule-detail-popup';
        popup.className = 'schedule-detail-popup';
        const scheduleId = AidUniteScheduleModal.resolveScheduleId(schedule);
        if (scheduleId) {
            popup.setAttribute('data-schedule-id', scheduleId);
        }
        popup.innerHTML = this.getPopupHTML(schedule, options);

        this.attachPopupEvents(popup, options);

        if (options.matchRestricted === undefined) {
            this.applyMatchRestrictedEditUi(popup, schedule);
        }

        return popup;
    }

    /**
     * モーダルのHTMLを生成
     *
     * @param {Object} schedule - スケジュールオブジェクト
     * @param {Object} options - オプション
     * @returns {string} HTML文字列
     */
    static getModalHTML(schedule, options = {}) {
        const scheduleId = AidUniteScheduleModal.resolveScheduleId(schedule);
        const sidJs = AidUniteScheduleModal.escapeScheduleIdForJs(scheduleId);
        const displayType = AidUniteScheduleModal.getDisplayType(schedule);
        const iconHtml = (typeof AidUniteScheduleUtils !== 'undefined' && AidUniteScheduleUtils.getScheduleIconHtml)
            ? AidUniteScheduleUtils.getScheduleIconHtml(displayType, 20)
            : (typeof AidUniteThemeIcons !== 'undefined' ? AidUniteThemeIcons.scheduleIconHtml(displayType, 20) : '');

        const timeRange = typeof formatTime === 'function' ?
            formatTime(schedule.start_time, schedule.end_time) :
            AidUniteDateUtils?.formatTime(schedule.start_time, schedule.end_time) || '時間未設定';

        const dateDisplay = schedule.date || '';
        // 性別を日本語に変換（共通ユーティリティを使用）
        const genderDisplay = (typeof AidUniteScheduleUtils !== 'undefined' && AidUniteScheduleUtils.getGenderLabel)
            ? AidUniteScheduleUtils.getGenderLabel(schedule.gender) || ''
            : (typeof getGenderLabel === 'function' ? getGenderLabel(schedule.gender) || '' : schedule.gender || '');
        // 会場の表記ロジック
        const venueRaw = (typeof AidUniteScheduleUtils !== 'undefined' && AidUniteScheduleUtils.resolveVenueConditionRaw)
            ? AidUniteScheduleUtils.resolveVenueConditionRaw(schedule)
            : (schedule.schedule_place || schedule.venue_condition || schedule.place || '');
        // 会場条件を日本語に変換（共通ユーティリティを使用）
        const venueLabel = (typeof AidUniteScheduleUtils !== 'undefined' && AidUniteScheduleUtils.getVenueLabel)
            ? AidUniteScheduleUtils.getVenueLabel(venueRaw) || ''
            : (typeof getVenueLabel === 'function' ? getVenueLabel(venueRaw) || '' : (function mapVenue(v){
                if (!v) return '';
                const m = {
                    'home': 'ホーム',
                    'away': 'アウェイ',
                    'both': 'どちらでも',
                    'either': 'どちらでも'
                };
                return m[v] || v;
            })(venueRaw));
        // 会場条件（ホーム/アウェイ/どちらでも）と会場名を分離して取得
        // 会場条件はvenueLabelから取得、会場名はvenue_nameから取得
        const venueCondition = venueLabel ? `${venueLabel}` : '';
        const venueNameValue = schedule.venue_name || '';
        // venue_nameがキーワード（home/away/both/either）でない場合のみ会場名として使用
        const actualVenueName = (venueNameValue && !isKeyword(venueNameValue)) ? venueNameValue : '';
        // 会場条件と会場名を結合（両方ある場合は「会場条件／会場名」の形式）
        const venueDisplay = (() => {
            if (venueCondition && actualVenueName) return `${venueCondition}／${actualVenueName}`;
            if (venueCondition) return venueCondition;
            if (actualVenueName) return actualVenueName;
            return '未設定';
        })();
        const venueMemo = schedule.venue_memo || schedule.place_memo || '';
        const memoDisplay = schedule.memo || schedule.quick_memo || schedule.schedule_quick_memo || schedule.schedule_memo || '';
        const matchRestricted = options.matchRestricted === true;
        const memoEscaped = AidUniteScheduleModal.escapeHtmlText(memoDisplay);

        const memoBlock = matchRestricted ? `
                    <div class="detail-item detail-item-full">
                        <label>メモ：</label>
                        <div class="aidunite-modal-memo-edit">
                            <p class="aidunite-modal-memo-hint" style="font-size:0.85em;color:#666;margin:0 0 8px;line-height:1.4;">申請中または成立に関するマッチがあるため、日時・会場・目的などは変更できません。メモのみ編集できます。</p>
                            <textarea class="aidunite-modal-memo-textarea" rows="4" style="width:100%;box-sizing:border-box;font:inherit;">${memoEscaped}</textarea>
                            <button type="button" class="btn btn-primary aidunite-modal-memo-save" data-aidunite-action="save-schedule-memo" style="margin-top:8px">メモを保存</button>
                        </div>
                    </div>` : `
                    <div class="detail-item detail-item-full">
                        <label>メモ：</label>
                        <span>${memoDisplay || '-'}</span>
                    </div>`;

        const footerInner = matchRestricted ? `
                    ${(schedule.matching === '1' || schedule.matching === 1 || schedule.matching === true) ? `
                        <button type="button" class="btn btn-invite" onclick="event.stopPropagation(); AidUniteScheduleModal.generateInviteUrl(${sidJs})">
                            ${AidUniteScheduleModal.iconHtml('send', 18)} 招待
                        </button>
                    ` : ''}
                    <button type="button" class="btn btn-danger" data-aidunite-action="delete-schedule" onclick="event.stopPropagation(); AidUniteScheduleModal.deleteSchedule(${sidJs})">
                        削除
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="AidUniteScheduleModal.closeModal(this)">
                        閉じる
                    </button>` : `
                    ${(schedule.matching === '1' || schedule.matching === 1 || schedule.matching === true) ? `
                        <button type="button" class="btn btn-invite" onclick="event.stopPropagation(); AidUniteScheduleModal.generateInviteUrl(${sidJs})">
                            ${AidUniteScheduleModal.iconHtml('send', 18)} 招待
                        </button>
                    ` : ''}
                    <button type="button" class="btn btn-primary" data-aidunite-action="edit-schedule" onclick="event.stopPropagation(); AidUniteScheduleModal.editSchedule(${sidJs})">
                        編集
                    </button>
                    <button type="button" class="btn btn-danger" data-aidunite-action="delete-schedule" onclick="event.stopPropagation(); AidUniteScheduleModal.deleteSchedule(${sidJs})">
                        削除
                    </button>
                    ${AidUniteScheduleModal.isScheduleTentative(schedule) ? `
                        <button type="button" class="btn btn-success" data-aidunite-action="confirm-tentative" onclick="event.stopPropagation(); AidUniteScheduleModal.confirmSchedule(${sidJs})">
                            確定
                        </button>
                    ` : ''}
                    <button type="button" class="btn btn-secondary" onclick="AidUniteScheduleModal.closeModal(this)">
                        閉じる
                    </button>`;

        return `
            <div class="modal-content">
                <div class="modal-header">
                    <h3>${iconHtml} ${displayType}</h3>
                    <button class="modal-close" onclick="AidUniteScheduleModal.closeModal(this)">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="detail-grid">
                        <div class="detail-item">
                            <label>日程：</label>
                            <span>${dateDisplay || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <label>時間：</label>
                            <span>${timeRange}</span>
                        </div>
                        <div class="detail-item detail-item-venue">
                            <label>会場：</label>
                            <div class="venue-content">
                                <div class="venue-name">${venueDisplay}</div>
                                ${venueMemo ? `<div class="venue-memo">${venueMemo}</div>` : ''}
                            </div>
                        </div>
                        <div class="detail-item">
                            <label>性別：</label>
                            <span>${genderDisplay}</span>
                        </div>
                    </div>${memoBlock}
                </div>
                <div class="modal-footer">${footerInner}
                </div>
            </div>
        `;
    }

    /**
     * ポップアップのHTMLを生成
     *
     * @param {Object} schedule - スケジュールオブジェクト
     * @param {Object} options - オプション
     * @returns {string} HTML文字列
     */
    static getPopupHTML(schedule, options = {}) {
        const scheduleId = AidUniteScheduleModal.resolveScheduleId(schedule);
        const sidJs = AidUniteScheduleModal.escapeScheduleIdForJs(scheduleId);
        const displayType = AidUniteScheduleModal.getDisplayType(schedule);
        const iconHtml = (typeof AidUniteScheduleUtils !== 'undefined' && AidUniteScheduleUtils.getScheduleIconHtml)
            ? AidUniteScheduleUtils.getScheduleIconHtml(displayType, 20)
            : (typeof AidUniteThemeIcons !== 'undefined' ? AidUniteThemeIcons.scheduleIconHtml(displayType, 20) : '');

        const timeRange = typeof formatTime === 'function' ?
            formatTime(schedule.start_time, schedule.end_time) :
            AidUniteDateUtils?.formatTime(schedule.start_time, schedule.end_time) || '時間未設定';

        const dateDisplay = schedule.date || '';
        // 性別を日本語に変換（共通ユーティリティを使用）
        const genderDisplay = (typeof AidUniteScheduleUtils !== 'undefined' && AidUniteScheduleUtils.getGenderLabel)
            ? AidUniteScheduleUtils.getGenderLabel(schedule.gender) || ''
            : (typeof getGenderLabel === 'function' ? getGenderLabel(schedule.gender) || '' : schedule.gender || '');
        const venueRaw = (typeof AidUniteScheduleUtils !== 'undefined' && AidUniteScheduleUtils.resolveVenueConditionRaw)
            ? AidUniteScheduleUtils.resolveVenueConditionRaw(schedule)
            : (schedule.schedule_place || schedule.venue_condition || schedule.place || '');
        // 会場条件を日本語に変換（共通ユーティリティを使用）
        const venueLabel = (typeof AidUniteScheduleUtils !== 'undefined' && AidUniteScheduleUtils.getVenueLabel)
            ? AidUniteScheduleUtils.getVenueLabel(venueRaw) || ''
            : (typeof getVenueLabel === 'function' ? getVenueLabel(venueRaw) || '' : (function mapVenue(v){
                if (!v) return '';
                const m = {
                    'home': 'ホーム',
                    'away': 'アウェイ',
                    'both': 'どちらでも',
                    'either': 'どちらでも'
                };
                return m[v] || v;
            })(venueRaw));
        // 会場条件（ホーム/アウェイ/どちらでも）と会場名を分離して取得
        // 会場条件はvenueLabelから取得、会場名はvenue_nameから取得
        const venueCondition = venueLabel ? `${venueLabel}` : '';
        const venueNameValue = schedule.venue_name || '';
        // venue_nameがキーワード（home/away/both/either）でない場合のみ会場名として使用
        const actualVenueName = (venueNameValue && !isKeyword(venueNameValue)) ? venueNameValue : '';
        // 会場条件と会場名を結合（両方ある場合は「会場条件／会場名」の形式）
        const venueDisplay = (() => {
            if (venueCondition && actualVenueName) return `${venueCondition}／${actualVenueName}`;
            if (venueCondition) return venueCondition;
            if (actualVenueName) return actualVenueName;
            return '未設定';
        })();
        const venueMemo = schedule.venue_memo || schedule.place_memo || '';
        const memoDisplay = schedule.memo || schedule.quick_memo || schedule.schedule_quick_memo || schedule.schedule_memo || '';
        const matchRestricted = options.matchRestricted === true;
        const memoEscaped = AidUniteScheduleModal.escapeHtmlText(memoDisplay);

        const memoBlock = matchRestricted ? `
                    <div class="detail-item detail-item-full">
                        <label>メモ：</label>
                        <div class="aidunite-modal-memo-edit">
                            <p class="aidunite-modal-memo-hint" style="font-size:0.85em;color:#666;margin:0 0 8px;line-height:1.4;">申請中または成立に関するマッチがあるため、日時・会場・目的などは変更できません。メモのみ編集できます。</p>
                            <textarea class="aidunite-modal-memo-textarea" rows="4" style="width:100%;box-sizing:border-box;font:inherit;">${memoEscaped}</textarea>
                            <button type="button" class="btn btn-primary aidunite-modal-memo-save" data-aidunite-action="save-schedule-memo" style="margin-top:8px">メモを保存</button>
                        </div>
                    </div>` : `
                    <div class="detail-item detail-item-full">
                        <label>メモ：</label>
                        <span>${memoDisplay || '-'}</span>
                    </div>`;

        const footerInner = matchRestricted ? `
                    ${(schedule.matching === '1' || schedule.matching === 1 || schedule.matching === true) ? `
                        <button type="button" class="btn btn-invite" onclick="event.stopPropagation(); AidUniteScheduleModal.generateInviteUrl(${sidJs})">
                            ${AidUniteScheduleModal.iconHtml('send', 18)} 招待
                        </button>
                    ` : ''}
                    <button type="button" class="btn btn-danger" data-aidunite-action="delete-schedule" onclick="event.stopPropagation(); AidUniteScheduleModal.deleteSchedule(${sidJs})">
                        削除
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="AidUniteScheduleModal.closePopup()">
                        閉じる
                    </button>` : `
                    ${(schedule.matching === '1' || schedule.matching === 1 || schedule.matching === true) ? `
                        <button type="button" class="btn btn-invite" onclick="event.stopPropagation(); AidUniteScheduleModal.generateInviteUrl(${sidJs})">
                            ${AidUniteScheduleModal.iconHtml('send', 18)} 招待
                        </button>
                    ` : ''}
                    <button type="button" class="btn btn-primary" data-aidunite-action="edit-schedule" onclick="event.stopPropagation(); AidUniteScheduleModal.editSchedule(${sidJs})">
                        編集
                    </button>
                    <button type="button" class="btn btn-danger" data-aidunite-action="delete-schedule" onclick="event.stopPropagation(); AidUniteScheduleModal.deleteSchedule(${sidJs})">
                        削除
                    </button>
                    ${AidUniteScheduleModal.isScheduleTentative(schedule) ? `
                        <button type="button" class="btn btn-success" data-aidunite-action="confirm-tentative" onclick="event.stopPropagation(); AidUniteScheduleModal.confirmSchedule(${sidJs})">
                            確定
                        </button>
                    ` : ''}
                    <button type="button" class="btn btn-secondary" onclick="AidUniteScheduleModal.closePopup()">
                        閉じる
                    </button>`;

        return `
            <div class="popup-content">
                <div class="popup-header">
                    <h3>${iconHtml} ${displayType}</h3>
                    <button class="popup-close" onclick="AidUniteScheduleModal.closePopup()">&times;</button>
                </div>
                <div class="popup-body">
                    <div class="detail-grid">
                        <div class="detail-item">
                            <label>日程：</label>
                            <span>${dateDisplay || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <label>時間：</label>
                            <span>${timeRange}</span>
                        </div>
                        <div class="detail-item detail-item-venue">
                            <label>会場：</label>
                            <div class="venue-content">
                                <div class="venue-name">${venueDisplay}</div>
                                ${venueMemo ? `<div class="venue-memo">${venueMemo}</div>` : ''}
                            </div>
                        </div>
                        <div class="detail-item">
                            <label>性別：</label>
                            <span>${genderDisplay}</span>
                        </div>
                    </div>${memoBlock}
                </div>
                <div class="modal-footer">${footerInner}
                </div>
            </div>
        `;
    }

    /**
     * モーダルのイベントリスナーを追加
     *
     * @param {HTMLElement} modal - モーダル要素
     * @param {Object} options - オプション
     */
    static attachModalEvents(modal, options = {}) {
        // 背景クリックで閉じる
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                this.closeModal(modal);
            }
        });

        // ESCキーで閉じる
        const handleKeyDown = (e) => {
            if (e.key === 'Escape') {
                this.closeModal(modal);
                document.removeEventListener('keydown', handleKeyDown);
            }
        };
        document.addEventListener('keydown', handleKeyDown);
    }

    /**
     * ポップアップのイベントリスナーを追加
     *
     * @param {HTMLElement} popup - ポップアップ要素
     * @param {Object} options - オプション
     */
    static attachPopupEvents(popup, options = {}) {
        // ESCキーで閉じる
        const handleKeyDown = (e) => {
            if (e.key === 'Escape') {
                this.closePopup();
                document.removeEventListener('keydown', handleKeyDown);
            }
        };
        document.addEventListener('keydown', handleKeyDown);
    }

    /**
     * モーダルを閉じる
     *
     * @param {HTMLElement|string} element - モーダル要素またはボタン要素
     */
    static closeModal(element) {
        let modal;
        if (typeof element === 'string') {
            modal = document.querySelector(element);
        } else if (element.classList.contains('modal-close') || element.classList.contains('btn')) {
            modal = element.closest('.schedule-detail-modal');
        } else {
            modal = element;
        }

        if (modal) {
            modal.classList.remove('show');
            setTimeout(() => {
                modal.remove();
            }, 300);
        }
    }

    /**
     * ポップアップを閉じる
     */
    static closePopup() {
        const popup = document.getElementById('schedule-detail-popup');
        if (popup) {
            popup.classList.remove('show');
            popup.style.display = 'none';
            setTimeout(() => {
                popup.remove();
            }, 300);
        }
    }

    /**
     * 全てのモーダル・ポップアップを閉じる
     */
    static closeAll() {
        this.closePopup();
        const modals = document.querySelectorAll('.schedule-detail-modal');
        modals.forEach(modal => this.closeModal(modal));
    }

    /**
     * スケジュールをIDで検索
     *
     * @param {string|number} scheduleId - スケジュールID
     * @returns {Object|null} スケジュールオブジェクト
     */
    /**
     * 申請中・成立系のマッチが付いているとき: フル編集ボタンを隠し、モーダル内でメモのみ編集可能にする
     *
     * @param {HTMLElement} root .schedule-detail-modal または .schedule-detail-popup
     * @param {Object} schedule スケジュールオブジェクト（id・メモ初期値）
     */
    static applyMatchRestrictedEditUi(root, schedule) {
        const sid = AidUniteScheduleModal.resolveScheduleId(schedule);
        if (!root || !schedule || !sid) {
            return;
        }
        if (typeof AidUniteAjaxUtils === 'undefined' || typeof AidUniteAjaxUtils.getScheduleDependencies !== 'function') {
            return;
        }
        AidUniteAjaxUtils.getScheduleDependencies({
            schedule_id: sid,
            onSuccess: (data) => {
                const dep = (data && data.dependencies) || {};
                if (!dep.has_pending && !dep.has_in_play) {
                    return;
                }
                const editBtn = root.querySelector('[data-aidunite-action="edit-schedule"]');
                if (editBtn) {
                    editBtn.style.display = 'none';
                }
                root.querySelectorAll('[data-aidunite-action="confirm-tentative"]').forEach((b) => {
                    b.style.display = 'none';
                });
                const memoContainer = root.querySelector('.modal-body .detail-item-full')
                    || root.querySelector('.popup-body .detail-item-full');
                if (!memoContainer) {
                    return;
                }
                while (memoContainer.firstChild) {
                    memoContainer.removeChild(memoContainer.firstChild);
                }
                const lab = document.createElement('label');
                lab.textContent = 'メモ：';
                memoContainer.appendChild(lab);
                const wrap = document.createElement('div');
                wrap.className = 'aidunite-modal-memo-edit';
                const hint = document.createElement('p');
                hint.className = 'aidunite-modal-memo-hint';
                hint.style.cssText = 'font-size:0.85em;color:#666;margin:0 0 8px;line-height:1.4;';
                hint.textContent = '申請中または成立に関するマッチがあるため、日時・会場・目的などは変更できません。メモのみ編集できます。';
                const ta = document.createElement('textarea');
                ta.className = 'aidunite-modal-memo-textarea';
                ta.setAttribute('rows', '4');
                ta.style.cssText = 'width:100%;box-sizing:border-box;font:inherit;';
                const memoText = schedule.memo || schedule.quick_memo || schedule.schedule_quick_memo || schedule.schedule_memo || '';
                ta.value = memoText;
                const saveBtn = document.createElement('button');
                saveBtn.type = 'button';
                saveBtn.className = 'btn btn-primary aidunite-modal-memo-save';
                saveBtn.style.marginTop = '8px';
                saveBtn.textContent = 'メモを保存';
                saveBtn.addEventListener('click', () => {
                    AidUniteScheduleModal.saveScheduleMemoOnly(sid, ta.value, schedule);
                });
                wrap.appendChild(hint);
                wrap.appendChild(ta);
                wrap.appendChild(saveBtn);
                memoContainer.appendChild(wrap);
            },
            onError: () => {}
        });
    }

    /**
     * REST: メタ schedule_quick_memo のみ更新
     *
     * @param {string|number} scheduleId
     * @param {string} memoValue
     * @param {Object|null} schedule メモリ上のオブジェクトを更新する場合
     */
    static saveScheduleMemoOnly(scheduleId, memoValue, schedule) {
        if (typeof AidUniteAjaxUtils === 'undefined' || typeof AidUniteAjaxUtils.updateScheduleMemoOnly !== 'function') {
            AidUniteScheduleModal.notify('メモ保存が利用できません。ページを再読み込みしてください。', 'error');
            return;
        }
        AidUniteAjaxUtils.updateScheduleMemoOnly({
            schedule_id: scheduleId,
            memo: memoValue != null ? memoValue : '',
            onSuccess: () => {
                if (schedule) {
                    const v = memoValue != null ? memoValue : '';
                    schedule.memo = v;
                    schedule.quick_memo = v;
                    schedule.schedule_quick_memo = v;
                }
                AidUniteScheduleModal.notify('メモを保存しました', 'success');
            },
            onError: (err) => {
                const code = (err && err.code) ? err.code : '';
                const raw = (err && err.message) ? err.message : String(err || '');
                const msg = code ? AidUniteScheduleModal.messageForScheduleErrorCode(code, raw) : raw;
                AidUniteScheduleModal.notify('メモの保存に失敗しました: ' + msg, 'error');
            }
        });
    }

    static findScheduleById(scheduleId) {
        // グローバル変数から検索（見つけた日付キーを date に補完）
        const searchInSchedules = (schedules) => {
            if (!schedules) return null;

            for (const dateKey in schedules) {
                const daySchedules = schedules[dateKey];
                if (Array.isArray(daySchedules)) {
                    const found = daySchedules.find(s => {
                        const sid = AidUniteScheduleModal.resolveScheduleId(s);
                        return sid && sid === String(scheduleId);
                    });
                    if (found) {
                        if (!found.id && (found.post_id || found.schedule_id)) {
                            try { found.id = found.post_id || found.schedule_id; } catch (_) {}
                        }
                        if (!found.date) {
                            try { found.date = dateKey; } catch(_) {}
                        }
                        return found;
                    }
                }
            }
            return null;
        };

        // 複数のグローバル変数を検索
        const searchTargets = [
            window.schedules,
            window.mypageSchedules,
            window.scheduleData
        ];

        for (const schedules of searchTargets) {
            const found = searchInSchedules(schedules);
            if (found) return found;
        }

        return null;
    }

    /**
     * スケジュール編集
     *
     * @param {string|number} scheduleId - スケジュールID
     */
    static editSchedule(scheduleId) {
        const id = scheduleId != null && scheduleId !== '' ? String(scheduleId) : '';
        console.log('スケジュール編集:', id);
        if (!id) {
            console.error('スケジュール編集: ID が取得できません');
            if (typeof showToastNotification !== 'undefined') {
                showToastNotification('スケジュールIDが取得できません。ページを再読み込みしてください。', 'error');
            }
            return;
        }
        this.closeAll();

        if (typeof AidUniteScheduleQuickModal !== 'undefined') {
            const found = AidUniteScheduleQuickModal.findScheduleById(id);
            if (found) {
                AidUniteScheduleQuickModal.openEdit(found);
                return;
            }
            if (typeof AidUniteScheduleQuickModal.openEditById === 'function') {
                AidUniteScheduleQuickModal.openEditById(id);
                return;
            }
        }

        // 各ページで個別に実装されている場合はそれを優先
        if (typeof window.editScheduleFromPopup === 'function') {
            window.editScheduleFromPopup(id);
            return;
        } else if (typeof window.editSchedule === 'function') {
            window.editSchedule(id);
            return;
        }

        window.location.href = '/schedule-management/?edit_schedule=' + encodeURIComponent(id);
    }

    /**
     * スケジュール削除
     *
     * @param {string|number} scheduleId - スケジュールID
     */
    static deleteSchedule(scheduleId) {
        const id = scheduleId != null && scheduleId !== '' ? String(scheduleId) : '';
        console.log('スケジュール削除:', id);
        if (!id) {
            console.error('スケジュール削除: ID が取得できません');
            if (typeof showToastNotification !== 'undefined') {
                showToastNotification('スケジュールIDが取得できません。ページを再読み込みしてください。', 'error');
            }
            return;
        }

        // 各ページで個別に実装されている場合はそれを優先（確認後に閉じる・削除はページ側で実施）
        if (typeof window.deleteScheduleFromPopup === 'function') {
            window.deleteScheduleFromPopup(id);
            return;
        }

        const self = this;
        const notifyErr = function(text) {
            AidUniteScheduleModal.notify(text, 'error');
        };
        const runDelete = function(apiFn) {
            apiFn({
                schedule_id: id,
                onSuccess: (response) => {
                    console.log('スケジュール削除成功:', response);
                    self.closeAll();
                    if (typeof showToastNotification !== 'undefined') {
                        showToastNotification('スケジュールを削除しました', 'success');
                        setTimeout(function() {
                            window.location.reload();
                        }, 700);
                    } else {
                        window.location.reload();
                    }
                },
                onError: (error) => {
                    const code = (error && typeof error === 'object' && error.code) ? error.code : '';
                    const raw = (error && typeof error === 'object' && error.message) ? error.message : String(error || '');
                    const msg = code ? self.messageForScheduleErrorCode(code, raw) : raw;
                    console.error('スケジュール削除エラー:', error);
                    notifyErr('スケジュールの削除に失敗しました: ' + msg);
                }
            });
        };

        const executeScheduleDelete = function() {
            if (typeof AidUniteAjaxUtils !== 'undefined' && typeof AidUniteAjaxUtils.deleteSchedule === 'function') {
                runDelete(AidUniteAjaxUtils.deleteSchedule.bind(AidUniteAjaxUtils));
                return;
            }
            if (typeof window.deleteSchedule === 'function') {
                runDelete(window.deleteSchedule.bind(window));
                return;
            }
            const nonce = typeof wpApiSettings !== 'undefined' ? wpApiSettings.nonce : '';
            const root = (typeof wpApiSettings !== 'undefined' && wpApiSettings.root) ? String(wpApiSettings.root).replace(/\/?$/, '/') : '/wp-json/';
            fetch(root + 'aidunite/v1/delete-schedule-v2', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce
                },
                body: JSON.stringify({
                    post_id: id,
                    nonce: nonce
                })
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then((j) => {
                        const code = (j && (j.code || j.data?.code)) || '';
                        const m = (j && (j.message || j.data?.message)) || ('HTTP ' + response.status);
                        throw { message: m, code: code };
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    console.log('スケジュール削除成功:', data);
                    self.closeAll();
                    if (typeof showToastNotification !== 'undefined') {
                        showToastNotification('スケジュールを削除しました', 'success');
                        setTimeout(function() {
                            window.location.reload();
                        }, 700);
                    } else {
                        window.location.reload();
                    }
                } else {
                    const code = (data && (data.code || data.data?.code)) || '';
                    const m = (data && data.message) || '削除に失敗しました';
                    throw code ? { message: m, code: code } : new Error(m);
                }
            })
            .catch(error => {
                console.error('スケジュール削除エラー:', error);
                const code = (error && error.code) ? error.code : '';
                const raw = (error && error.message) ? error.message : String(error || '');
                const msg = code ? self.messageForScheduleErrorCode(code, raw) : raw;
                notifyErr('スケジュールの削除に失敗しました: ' + msg);
            });
        };

        const afterDepsOk = function() {
            const dep = (window.__aiduniteLastScheduleDeps && window.__aiduniteLastScheduleDeps.dependencies) || {};
            const boards = (dep.match_board_ids && dep.match_board_ids.length) ? dep.match_board_ids.length : 0;
            const mrs = (dep.match_request_ids && dep.match_request_ids.length) ? dep.match_request_ids.length : 0;
            let confirmText = 'このスケジュールを削除してもよろしいですか？';
            if (boards > 0 || mrs > 0) {
                confirmText = '関連する掲示板・マッチ申請データも含めて削除される場合があります。' + confirmText;
            }
            AidUniteScheduleModal.confirmAction({
                message: confirmText,
                onConfirm: executeScheduleDelete
            });
        };

        if (typeof AidUniteAjaxUtils !== 'undefined' && typeof AidUniteAjaxUtils.getScheduleDependencies === 'function') {
            AidUniteAjaxUtils.getScheduleDependencies({
                schedule_id: id,
                onSuccess: (data) => {
                    window.__aiduniteLastScheduleDeps = data;
                    const gate = data && data.delete_gate;
                    if (gate && gate.allowed === false) {
                        notifyErr(self.messageForDeleteGateCode(gate.code));
                        return;
                    }
                    afterDepsOk();
                },
                onError: () => {
                    AidUniteScheduleModal.confirmAction({
                        title: '確認',
                        message: '依存状況を取得できませんでした。このまま削除を試みますか？',
                        confirmLabel: '続行する',
                        onConfirm: () => {
                            window.__aiduniteLastScheduleDeps = null;
                            AidUniteScheduleModal.confirmAction({
                                message: 'このスケジュールを削除してもよろしいですか？',
                                onConfirm: executeScheduleDelete
                            });
                        }
                    });
                }
            });
            return;
        }

        AidUniteScheduleModal.confirmAction({
            message: 'このスケジュールを削除してもよろしいですか？',
            onConfirm: () => {
                window.__aiduniteLastScheduleDeps = null;
                executeScheduleDelete();
            }
        });
    }

    /**
     * 招待URL発行
     *
     * @param {string|number} scheduleId - スケジュールID
     */
    static generateInviteUrl(scheduleId) {
        console.log('招待URL発行:', scheduleId);

        const nonce = typeof wpApiSettings !== 'undefined' ? wpApiSettings.nonce : '';
        const root = typeof wpApiSettings !== 'undefined' ? wpApiSettings.root : '/wp-json/';

        fetch(root + 'aidunite/v1/generate-invite-url', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce
            },
            body: JSON.stringify({
                schedule_id: parseInt(scheduleId)
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const scheduleInfo = {
                    schedule_date: data.schedule_date || '',
                    schedule_start: data.schedule_start || '',
                    schedule_end: data.schedule_end || '',
                    team_name: data.team_name || '',
                    venue_name: data.venue_name || ''
                };
                this.showInviteUrlModal(data.invite_url, data.expires_at, scheduleInfo);
            } else {
                throw new Error(data.message || '招待URLの生成に失敗しました');
            }
        })
        .catch(error => {
            console.error('招待URL発行エラー:', error);
            AidUniteScheduleModal.notify(
                '招待URLの生成に失敗しました: ' + (error.message || '不明なエラー'),
                'error'
            );
        });
    }

    /**
     * 招待URL表示モーダル（LINE用メッセージ付き）
     *
     * @param {string} inviteUrl - 招待URL
     * @param {string} expiresAt - 有効期限
     * @param {Object} scheduleInfo - スケジュール情報（LINE用メッセージ生成用）
     */
    static showInviteUrlModal(inviteUrl, expiresAt, scheduleInfo = {}) {
        this.closeAll();

        const d = scheduleInfo.schedule_date || '';
        const start = scheduleInfo.schedule_start || '';
        const end = scheduleInfo.schedule_end || '';
        const venueName = scheduleInfo.venue_name || '';
        const timeStr = (start && end) ? `${start}〜${end}` : '';
        const dateStr = d ? d.replace(/-/g, '/') : '';
        const when = [dateStr, timeStr].filter(Boolean).join(' ');
        const venueLine = venueName ? `アウェイ @${venueName}` : '';

        const lineMessage = `お疲れ様です。\n${when ? when + 'の' : ''}試合の招待です。\n${venueLine ? venueLine + '\n' : ''}下のリンクから内容を確認し、承認または拒否をお願いします。\n\n${inviteUrl}`;

        const modal = document.createElement('div');
        modal.className = 'invite-url-modal';
        modal.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 10000;';

        modal.innerHTML = `
            <div style="background: white; padding: 2rem; border-radius: 8px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
                <h3 style="margin-top: 0;">招待のみを発行しました</h3>
                <p style="color: #666; font-size: 0.9rem; margin-bottom: 1rem;">LINEで送る場合は「LINE用メッセージをコピー」すると、文脈付きで送れます</p>
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: bold;">LINE用メッセージ（文脈付き）</label>
                    <textarea id="invite-line-message" readonly rows="5" style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; font-size: 0.9rem; resize: vertical;"></textarea>
                    <button type="button" id="copy-line-message-btn" class="btn btn-primary" style="margin-top: 0.5rem; width: 100%;">LINE用メッセージをコピー</button>
                </div>
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: bold;">招待のみ</label>
                    <input type="text" id="invite-url-input" value="${String(inviteUrl).replace(/&/g, '&amp;').replace(/"/g, '&quot;')}" readonly style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; font-size: 0.9rem;">
                    <button type="button" id="copy-invite-url-btn" class="btn btn-secondary" style="margin-top: 0.5rem; width: 100%;">URLのみコピー</button>
                </div>
                <p style="color: #666; font-size: 0.85rem; margin-bottom: 1rem;">有効期限: ${expiresAt}</p>
                <button type="button" class="btn btn-secondary" onclick="AidUniteScheduleModal.closeInviteUrlModal()" style="width: 100%;">閉じる</button>
            </div>
        `;

        document.body.appendChild(modal);

        const urlInput = modal.querySelector('#invite-url-input');
        const lineTextarea = modal.querySelector('#invite-line-message');
        if (lineTextarea) lineTextarea.value = lineMessage;

        modal.querySelector('#copy-invite-url-btn').addEventListener('click', () => {
            urlInput.select();
            document.execCommand('copy');
            const btn = modal.querySelector('#copy-invite-url-btn');
            btn.textContent = 'コピーしました！';
            setTimeout(() => { btn.textContent = 'URLのみコピー'; }, 2000);
        });

        const copyLineBtn = modal.querySelector('#copy-line-message-btn');
        if (copyLineBtn && lineTextarea) {
            copyLineBtn.addEventListener('click', () => {
                lineTextarea.select();
                document.execCommand('copy');
                copyLineBtn.textContent = 'コピーしました！';
                setTimeout(() => { copyLineBtn.textContent = 'LINE用メッセージをコピー'; }, 2000);
            });
        }
    }

    /**
     * 招待URLモーダルを閉じる
     */
    static closeInviteUrlModal() {
        const modal = document.querySelector('.invite-url-modal');
        if (modal) {
            modal.remove();
        }
    }

    /**
     * スケジュール確定（仮→確定）
     *
     * @param {string|number} scheduleId - スケジュールID
     */
    static confirmSchedule(scheduleId) {
        console.log('スケジュール確定:', scheduleId);
        // 各ページで個別に実装（仮→確定用のトーストモーダルを優先）
        if (typeof window.confirmScheduleFromPopup === 'function') {
            window.confirmScheduleFromPopup(scheduleId);
            return;
        }
        if (typeof window.confirmSchedule === 'function') {
            window.confirmSchedule(scheduleId);
            return;
        }
        if (typeof window.showTentativeConfirmToast === 'function') {
            // 登録画面ダッシュボード用：中央トーストで確認・編集してから確定
            window.showTentativeConfirmToast(scheduleId);
            return;
        }
        AidUniteScheduleModal.confirmScheduleBuiltin(scheduleId);
    }

    /**
     * ページ側に confirm / showTentativeConfirmToast が無いときのフォールバック（REST で仮→確定）
     * @param {string|number} scheduleId
     */
    static confirmScheduleBuiltin(scheduleId) {
        const schedule = AidUniteScheduleModal.findScheduleById(scheduleId);
        if (!schedule) {
            AidUniteScheduleModal.notify('スケジュール情報が取得できませんでした。', 'error');
            return;
        }
        AidUniteScheduleModal.confirmAction({
            title: '確定確認',
            message: 'この仮の予定を確定しますか？',
            confirmLabel: '確定する',
            confirmVariant: 'primary',
            onConfirm: () => AidUniteScheduleModal.runConfirmScheduleBuiltin(scheduleId, schedule)
        });
    }

    static runConfirmScheduleBuiltin(scheduleId, schedule) {
        const nonce = (typeof wpApiSettings !== 'undefined' && wpApiSettings.nonce) ? wpApiSettings.nonce : '';
        const url = (typeof wpApiSettings !== 'undefined' && wpApiSettings.root)
            ? (wpApiSettings.root + 'aidunite/v1/update-schedule-v2')
            : '/wp-json/aidunite/v1/update-schedule-v2';
        const typeRaw = String(schedule.type || '');
        const typeStripped = typeRaw.replace(/（仮）/g, '').replace(/\(仮\)/g, '').trim();
        const venueCond = (typeof AidUniteScheduleUtils !== 'undefined' && AidUniteScheduleUtils.resolveVenueConditionRaw)
            ? AidUniteScheduleUtils.resolveVenueConditionRaw(schedule)
            : (schedule.schedule_place || schedule.venue_condition || '');
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce
            },
            body: JSON.stringify({
                post_id: scheduleId,
                nonce: nonce,
                intent: 'confirmed',
                certainty: 'firm',
                date: schedule.date || '',
                start_time: schedule.start_time || '',
                end_time: schedule.end_time || '',
                type: typeStripped || typeRaw,
                gender: schedule.gender || '',
                venue_condition: venueCond,
                venue_name: schedule.venue_name || '',
                memo: schedule.quick_memo || schedule.memo || schedule.schedule_quick_memo || ''
            })
        })
            .then((res) => res.json())
            .then((data) => {
                if (data && data.success) {
                    AidUniteScheduleModal.closeAll();
                    AidUniteScheduleModal.notify('スケジュールを確定しました', 'success');
                    if (typeof window.loadSchedules === 'function') {
                        window.loadSchedules();
                    } else {
                        window.location.reload();
                    }
                } else {
                    throw new Error((data && data.message) ? data.message : '確定に失敗しました');
                }
            })
            .catch((err) => {
                console.error('confirmScheduleBuiltin:', err);
                AidUniteScheduleModal.notify(err.message || 'スケジュールの確定に失敗しました', 'error');
            });
    }

    /**
     * 詳細ポップアップ／モーダル内ボタン（data-aidunite-action）のフォールバック委譲
     */
    static handleDetailActionClick(e) {
        const btn = e.target.closest('[data-aidunite-action]');
        if (!btn) {
            return;
        }
        const root = btn.closest('.schedule-detail-popup, .schedule-detail-modal');
        if (!root) {
            return;
        }
        const action = btn.getAttribute('data-aidunite-action') || '';
        const scheduleId = btn.getAttribute('data-schedule-id')
            || root.getAttribute('data-schedule-id')
            || '';
        if (!scheduleId && action !== 'save-schedule-memo') {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        if (action === 'edit-schedule') {
            AidUniteScheduleModal.editSchedule(scheduleId);
        } else if (action === 'delete-schedule') {
            AidUniteScheduleModal.deleteSchedule(scheduleId);
        } else if (action === 'confirm-tentative') {
            AidUniteScheduleModal.confirmSchedule(scheduleId);
        } else if (action === 'save-schedule-memo') {
            const scope = btn.closest('.modal-content, .popup-content');
            const ta = scope ? scope.querySelector('.aidunite-modal-memo-textarea') : null;
            const schedule = scheduleId ? AidUniteScheduleModal.findScheduleById(scheduleId) : null;
            AidUniteScheduleModal.saveScheduleMemoOnly(scheduleId, ta ? ta.value : '', schedule);
        }
    }
}

// 詳細ポップアップ内の編集・削除（onclick 未発火時のフォールバック）
if (typeof document !== 'undefined' && !window.__aiduniteScheduleDetailActionBound) {
    window.__aiduniteScheduleDetailActionBound = true;
    document.addEventListener('click', function(e) {
        AidUniteScheduleModal.handleDetailActionClick(e);
    }, true);
}

// 後方互換性のためのグローバル関数（段階的移行用）
// 注意: 将来的に削除予定
if (typeof window !== 'undefined') {
    // 既存の関数名での後方互換性
    window.showScheduleDetail = AidUniteScheduleModal.showDetail.bind(AidUniteScheduleModal);
    window.createScheduleDetailModal = AidUniteScheduleModal.createModal.bind(AidUniteScheduleModal);
        window.showScheduleDetail = AidUniteScheduleModal.showDetail.bind(AidUniteScheduleModal);
        window.showScheduleDetailPopup = AidUniteScheduleModal.showPopup.bind(AidUniteScheduleModal);
    window.closeScheduleDetail = AidUniteScheduleModal.closeModal.bind(AidUniteScheduleModal);
    window.closeScheduleDetailPopup = AidUniteScheduleModal.closePopup.bind(AidUniteScheduleModal);
}

// モジュールエクスポート（ES6モジュール対応）
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AidUniteScheduleModal;
}

// AMD対応
if (typeof define === 'function' && define.amd) {
    define([], function() {
        return AidUniteScheduleModal;
    });
}
