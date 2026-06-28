/**
 * スケジュール管理: 日付タップ即登録 / 試合募集確認 / カード操作モーダル
 */
class AidUniteScheduleQuickModal {
    /** @type {ReturnType<typeof setTimeout>|null} */
    static _closeTimer = null;

    /** クイックモーダル DOM をすべて取得（重複 id 対策） */
    static getModalRoots() {
        return Array.from(document.querySelectorAll('#aidunite-schedule-quick-modal'));
    }

    /** 開き直し前など、アニメなしで即座に除去 */
    static destroyImmediately() {
        if (AidUniteScheduleQuickModal._closeTimer) {
            clearTimeout(AidUniteScheduleQuickModal._closeTimer);
            AidUniteScheduleQuickModal._closeTimer = null;
        }
        AidUniteScheduleQuickModal.getModalRoots().forEach((el) => el.remove());
    }

    /** クイック登録・カード操作モーダルを閉じる */
    static close() {
        const modals = AidUniteScheduleQuickModal.getModalRoots();
        if (!modals.length) return;
        if (AidUniteScheduleQuickModal._closeTimer) {
            clearTimeout(AidUniteScheduleQuickModal._closeTimer);
            AidUniteScheduleQuickModal._closeTimer = null;
        }
        modals.forEach((el) => el.classList.remove('show'));
        AidUniteScheduleQuickModal._closeTimer = setTimeout(() => {
            AidUniteScheduleQuickModal.getModalRoots().forEach((el) => el.remove());
            AidUniteScheduleQuickModal._closeTimer = null;
        }, 200);
    }

    /** 挿入直後のモーダル root（重複 id があっても最後の要素） */
    static getActiveModalRoot() {
        const modals = AidUniteScheduleQuickModal.getModalRoots();
        return modals.length ? modals[modals.length - 1] : null;
    }

    /** 試合募集: 募集チーム数の上限 */
    static maxRecruitTeamCount() {
        return 5;
    }

    static getConfig() {
        return window.AIDUNITE_SCHEDULE_QUICK || {};
    }

    /**
     * 初回オンボーディング: 募集公開後のマイページ誘導（トーストの代わりにモーダル／遷移）
     *
     * @param {boolean} isRecruit
     * @param {boolean} isEdit
     * @returns {boolean} 後続トーストを省略する場合 true
     */
    static handleActivationRecruitPublishedFollowUp(isRecruit, isEdit) {
        if (isEdit || !isRecruit) {
            return false;
        }

        const joyCfg = window.aiduniteMypageJoy || {};
        const quickCfg = AidUniteScheduleQuickModal.getConfig();
        const isMission = joyCfg.activationMissionUi === '1'
            || joyCfg.activationMissionUi === 1
            || joyCfg.activationMissionUi === true
            || quickCfg.activationMissionUi === '1'
            || quickCfg.activationMissionUi === 1;
        const stage = String(joyCfg.activationStage || quickCfg.activationStage || '');
        if (!isMission || stage !== 'recruit_pending') {
            return false;
        }

        if (window.aiduniteMypageJoy && typeof window.dispatchEvent === 'function') {
            window.dispatchEvent(new CustomEvent('aidunite:activation-recruit-published'));
            return true;
        }

        const mypageUrl = String(quickCfg.mypageUrl || '/mypage/').trim() || '/mypage/';
        const joiner = mypageUrl.indexOf('?') >= 0 ? '&' : '?';
        window.location.href = mypageUrl + joiner + 'activation_recruit_published=1';
        return true;
    }

    static canEdit() {
        const cfg = AidUniteScheduleQuickModal.getConfig();
        if (cfg.canEdit === true || cfg.canEdit === '1' || cfg.canEdit === 1) {
            return true;
        }
        const viewCfg = window.AIDUNITE_SCHEDULE_VIEW || {};
        return viewCfg.canEdit === true || viewCfg.canEdit === '1' || viewCfg.canEdit === 1;
    }

    /** PC カレンダー表示（768px 超） */
    static isPcLayout() {
        return typeof window !== 'undefined'
            && window.matchMedia
            && window.matchMedia('(min-width: 769px)').matches;
    }

    static closeIconSvg(size = 20) {
        const s = Number(size) || 20;
        return `<svg xmlns="http://www.w3.org/2000/svg" width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg>`;
    }

    static closeButtonHtml() {
        return `<button type="button" class="sqm-close" data-sqm-close="1" aria-label="閉じる">${AidUniteScheduleQuickModal.closeIconSvg()}</button>`;
    }

    /**
     * カード操作モード（payload のみで判定）
     * @returns {'normal'|'recruit'|'match_confirmed'}
     */
    static resolveCardActionMode(schedule) {
        if (!schedule || typeof schedule !== 'object') {
            return 'normal';
        }
        const intent = String(schedule.intent || '').toLowerCase();
        const uiKind = String(schedule.ui_schedule_kind || '').toLowerCase();
        if (intent === 'recruit' || uiKind === 'recruit') {
            return 'recruit';
        }
        const canDetail = schedule.can_show_match_detail === true
            || schedule.can_show_match_detail === '1'
            || schedule.can_show_match_detail === 1;
        if (canDetail && uiKind === 'match') {
            return 'match_confirmed';
        }
        return 'normal';
    }

    static resolveKindBadge(schedule) {
        const badge = String(schedule.status_badge || schedule.intent_label || '').trim();
        if (badge) {
            return badge;
        }
        const mode = AidUniteScheduleQuickModal.resolveCardActionMode(schedule);
        if (mode === 'recruit') {
            return '試合募集';
        }
        if (mode === 'match_confirmed') {
            return '試合確定';
        }
        const uiKind = String(schedule.ui_schedule_kind || '');
        const map = {
            recruit: '試合の募集',
            practice: '練習',
            meeting: 'ミーティング',
            rest: '休み',
            official_match: '公式試合',
            practice_match: '練習試合',
            joint_practice: '合同練習',
            camp: '合宿',
            expedition: '遠征',
            tentative: '仮予定',
            match: '試合',
        };
        return map[uiKind] || String(schedule.display_type || schedule.type || '予定');
    }

    static kindBadgeClass(schedule) {
        const mode = AidUniteScheduleQuickModal.resolveCardActionMode(schedule);
        if (mode === 'recruit') {
            return 'sqm-badge--recruit';
        }
        if (mode === 'match_confirmed') {
            return 'sqm-badge--match';
        }
        const intent = String(schedule?.intent || '').toLowerCase();
        if (intent === 'confirmed') {
            return 'sqm-badge--confirmed';
        }
        if (intent === 'tentative') {
            return 'sqm-badge--tentative';
        }
        const uiKind = String(schedule.ui_schedule_kind || 'practice');
        return `sqm-badge--${uiKind}`;
    }

    static resolveKindBadgeIcon(schedule) {
        const mode = AidUniteScheduleQuickModal.resolveCardActionMode(schedule);
        if (mode === 'recruit') {
            return 'vs';
        }
        if (mode === 'match_confirmed') {
            return 'trophy';
        }
        const intent = String(schedule?.intent || '').toLowerCase();
        if (intent === 'confirmed') {
            return 'check_circle';
        }
        if (intent === 'recruit') {
            return 'vs';
        }
        if (intent === 'tentative') {
            return 'hourglass_empty';
        }
        const uiKind = String(schedule?.ui_schedule_kind || '');
        const kindIcons = {
            practice: 'exercise',
            meeting: 'group',
            rest: 'airline_seat_recline_extra',
            official_match: 'trophy',
            practice_match: 'vs',
            joint_practice: 'group',
            camp: 'camping',
            expedition: 'flight_takeoff',
            tentative: 'question_mark',
        };
        if (kindIcons[uiKind]) {
            return kindIcons[uiKind];
        }
        if (typeof AidUniteThemeIcons !== 'undefined' && AidUniteThemeIcons.getScheduleIconBasename) {
            const type = String(schedule?.display_type || schedule?.type || '');
            return AidUniteThemeIcons.getScheduleIconBasename(type);
        }
        return 'schedule';
    }

    static buildBadgeHtml(schedule) {
        const badgeClass = AidUniteScheduleQuickModal.kindBadgeClass(schedule);
        const label = AidUniteScheduleQuickModal.resolveKindBadge(schedule);
        const icon = AidUniteScheduleQuickModal.confirmDetailIconHtml(
            AidUniteScheduleQuickModal.resolveKindBadgeIcon(schedule),
            14
        );
        return `<span class="sqm-badge ${badgeClass}">`
            + (icon ? `<span class="sqm-badge__icon" aria-hidden="true">${icon}</span>` : '')
            + `<span class="sqm-badge__text">${AidUniteScheduleQuickModal.escapeHtml(label)}</span>`
            + `</span>`;
    }

    static buildConfirmTitleHtml(schedule) {
        const team = String(schedule.team_name || '').trim();
        const typeLabel = (typeof AidUniteScheduleModal !== 'undefined' && AidUniteScheduleModal.getDisplayType)
            ? AidUniteScheduleModal.getDisplayType(schedule)
            : String(schedule.display_type || schedule.type || '予定');
        const typeEsc = AidUniteScheduleQuickModal.escapeHtml(typeLabel);
        if (team) {
            return `<span class="sqm-view-title__inner">`
                + `<span class="sqm-view-title__team">${AidUniteScheduleQuickModal.escapeHtml(team)}</span>`
                + `<span class="sqm-view-title__sep" aria-hidden="true"></span>`
                + `<span class="sqm-view-title__kind">${typeEsc}</span>`
                + `</span>`;
        }
        return `<span class="sqm-view-title__kind">${typeEsc}</span>`;
    }

    static confirmDetailIconHtml(basename, size = 20) {
        if (typeof AidUniteScheduleModal !== 'undefined' && AidUniteScheduleModal.iconHtml) {
            return AidUniteScheduleModal.iconHtml(basename, size);
        }
        if (typeof AidUniteThemeIcons !== 'undefined' && AidUniteThemeIcons.html) {
            return AidUniteThemeIcons.html(basename, size);
        }
        return '';
    }

    static resolveGenderForSchedule(schedule) {
        const g = String(
            (schedule && (schedule.gender_condition || schedule.gender))
            || AidUniteScheduleQuickModal.defaultGender()
            || ''
        ).toLowerCase();
        return (g === 'male' || g === 'female') ? g : '';
    }

    static applyGenderThemeForSchedule(root, schedule) {
        if (!root) {
            return;
        }
        root.classList.remove('sqm--theme-male', 'sqm--theme-female');
        const g = AidUniteScheduleQuickModal.resolveGenderForSchedule(schedule);
        if (g === 'male') {
            root.classList.add('sqm--theme-male');
        } else if (g === 'female') {
            root.classList.add('sqm--theme-female');
        }
    }

    static viewActionButtonHtml(options) {
        const opts = options || {};
        const idAttr = opts.id ? ` id="${opts.id}"` : '';
        const variant = opts.variant || 'primary';
        const label = AidUniteScheduleQuickModal.escapeHtml(opts.label || '');
        const iconLeft = opts.iconLeft
            ? AidUniteScheduleQuickModal.confirmDetailIconHtml(opts.iconLeft, opts.iconSize || 18)
            : '';
        const iconRight = opts.iconRight
            ? AidUniteScheduleQuickModal.confirmDetailIconHtml(opts.iconRight, opts.iconSize || 18)
            : '';
        const extraClass = opts.extraClass ? ` ${opts.extraClass}` : '';
        const closeAttr = opts.close ? ' data-sqm-close="1"' : '';
        if (variant === 'link') {
            return `<button type="button" class="sqm-view-close-link${extraClass}"${idAttr}${closeAttr}>`
                + `${iconLeft}<span>${label}</span></button>`;
        }
        const btnClass = variant === 'danger'
            ? 'btn btn-danger'
            : (variant === 'secondary' ? 'btn btn-secondary' : 'btn btn-primary');
        const detailClass = opts.layout === 'split' ? ' sqm-action-btn--split' : '';
        return `<button type="button" class="${btnClass} sqm-full sqm-action-btn${detailClass}${extraClass}"${idAttr}${closeAttr}>`
            + `<span class="sqm-action-btn__inner">`
            + (iconLeft ? `<span class="sqm-action-btn__leading">${iconLeft}<span>${label}</span></span>` : `<span>${label}</span>`)
            + (iconRight ? `<span class="sqm-action-btn__trailing">${iconRight}</span>` : '')
            + `</span></button>`;
    }

    static buildViewInfoHtml(schedule, actionMode) {
        let message = '';
        if (actionMode === 'recruit') {
            message = 'この予定で試合相手の募集を開始します。';
        } else if (actionMode === 'match_confirmed') {
            message = '試合が成立しています。詳細画面でやり取りを確認できます。';
        }
        if (!message) {
            return '';
        }
        const icon = AidUniteScheduleQuickModal.confirmDetailIconHtml('info', 18);
        return `<div class="sqm-view-info" role="note">${icon}<p>${AidUniteScheduleQuickModal.escapeHtml(message)}</p></div>`;
    }

    static buildDetailRowHtml(iconBasename, ariaLabel, value) {
        const v = String(value ?? '').trim();
        if (!v) {
            return '';
        }
        const icon = AidUniteScheduleQuickModal.confirmDetailIconHtml(iconBasename, 18);
        const label = AidUniteScheduleQuickModal.escapeHtml(ariaLabel);
        return `<div class="sqm-view-detail-row">`
            + `<div class="sqm-view-detail-row__meta">`
            + `<span class="sqm-view-detail-row__icon" aria-hidden="true">${icon}</span>`
            + `<span class="sqm-view-detail-row__label">${label}</span>`
            + `</div>`
            + `<span class="sqm-view-detail-row__sep" aria-hidden="true"></span>`
            + `<span class="sqm-view-detail-row__value">${AidUniteScheduleQuickModal.escapeHtml(v)}</span>`
            + `</div>`;
    }

    static buildDetailListHtml(rowsHtml) {
        const rows = String(rowsHtml || '').trim();
        if (!rows) {
            return '<p class="sqm-detail-empty">—</p>';
        }
        return `<div class="sqm-view-detail-list">${rows}</div>`;
    }

    static buildRegisterConfirmDetailHtml(draft) {
        const rows = [];
        const push = (iconBasename, ariaLabel, value) => {
            const html = AidUniteScheduleQuickModal.buildDetailRowHtml(iconBasename, ariaLabel, value);
            if (html) {
                rows.push(html);
            }
        };
        const kindLabel = (() => {
            if (draft.ui_schedule_kind === 'tentative') {
                if (draft.schedule_type) {
                    return draft.schedule_type;
                }
                const base = AidUniteScheduleQuickModal.tentativeKindOptions()
                    .find((o) => o.value === draft.tentative_base_kind)?.label;
                return base ? `${base}（仮）` : '仮予定';
            }
            return AidUniteScheduleQuickModal.kindOptions()
                .find((o) => o.value === draft.ui_schedule_kind)?.label || '';
        })();
        const placeLabel = AidUniteScheduleQuickModal.placeOptions()
            .find((o) => o.value === draft.venue_condition)?.label || '';
        const genderLbl = draft.gender_condition === 'male'
            ? '男子'
            : (draft.gender_condition === 'female' ? '女子' : '—');
        const kindIcon = AidUniteScheduleQuickModal.resolveKindBadgeIcon({
            ui_schedule_kind: draft.ui_schedule_kind,
            intent: draft.intent,
        });
        const genderIcon = draft.gender_condition === 'female' ? 'person_woman' : 'person_man';

        push('today', '日付', AidUniteScheduleQuickModal.formatDateLabel(draft.date));
        push('schedule', '時間', AidUniteScheduleQuickModal.formatScheduleTimeDisplay(draft) || '—');
        push(kindIcon, '予定', kindLabel);
        push(genderIcon, '性別', genderLbl);
        push('stadium', '会場条件', placeLabel);
        push('group', '募集数', AidUniteScheduleQuickModal.recruitTeamCountLabel(draft));
        push('stadium', '会場名', draft.venue_name || '—');
        push('stylus', 'メモ', draft.note || '—');

        return AidUniteScheduleQuickModal.buildDetailListHtml(rows.join(''));
    }

    static buildConfirmDetailHtml(schedule) {
        const rows = [];
        const push = (iconBasename, ariaLabel, value) => {
            const html = AidUniteScheduleQuickModal.buildDetailRowHtml(iconBasename, ariaLabel, value);
            if (html) {
                rows.push(html);
            }
        };

        const dateLabel = schedule.date
            ? AidUniteScheduleQuickModal.formatDateLabel(schedule.date)
            : '';
        push('today', '日付', dateLabel);
        const timeLabel = AidUniteScheduleQuickModal.formatScheduleTimeDisplay(schedule);
        if (timeLabel) {
            push('schedule', '時間', timeLabel);
        }
        const venueLabel = (typeof AidUniteScheduleUtils !== 'undefined' && AidUniteScheduleUtils.resolveScheduleVenueLabel)
            ? AidUniteScheduleUtils.resolveScheduleVenueLabel(schedule)
            : '';
        push('stadium', '会場', venueLabel || 'ー');
        const mode = AidUniteScheduleQuickModal.resolveCardActionMode(schedule);
        if (mode === 'recruit') {
            const g = String(schedule.gender_condition || schedule.gender || '').toLowerCase();
            if (g === 'male') {
                push('person_man', '性別', '男子');
            } else if (g === 'female') {
                push('person_woman', '性別', '女子');
            }
        }
        if (mode === 'match_confirmed') {
            const opp = String(schedule.opponent_display || schedule.opponent?.name || '').trim();
            if (opp) {
                push('vs', '対戦相手', opp);
            }
        }
        const memo = String(schedule.memo || schedule.quick_memo || schedule.note || '').trim();
        push('stylus', 'メモ', memo || '—');

        return AidUniteScheduleQuickModal.buildDetailListHtml(rows.join(''));
    }

    static escapeHtml(str) {
        if (typeof AidUniteScheduleModal !== 'undefined' && AidUniteScheduleModal.escapeHtmlText) {
            return AidUniteScheduleModal.escapeHtmlText(str);
        }
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    static formatDateLabel(dateString) {
        if (typeof AidUniteDateUtils !== 'undefined' && AidUniteDateUtils.formatDateDisplayUnified) {
            return AidUniteDateUtils.formatDateDisplayUnified(dateString);
        }
        if (!dateString) {
            return '';
        }
        try {
            const d = new Date(String(dateString) + 'T00:00:00');
            if (isNaN(d.getTime())) {
                return String(dateString);
            }
            const y = String(d.getFullYear()).slice(-2);
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            const weekday = ['日', '月', '火', '水', '木', '金', '土'][d.getDay()];
            return `${y}/${m}/${day}（${weekday}）`;
        } catch (e) {
            return String(dateString);
        }
    }

    static defaultGender() {
        const g = String(AidUniteScheduleQuickModal.getConfig().teamGender || '').toLowerCase();
        return (g === 'male' || g === 'female') ? g : '';
    }

    static kindOptions() {
        return [
            { value: 'recruit', label: '試合の募集', main: true },
            { value: 'practice', label: '練習', main: true },
            { value: 'rest', label: '休み', main: true },
            { value: 'meeting', label: 'ミーティング', main: false },
            { value: 'official_match', label: '公式試合', main: false },
            { value: 'practice_match', label: '練習試合', main: false },
            { value: 'joint_practice', label: '合同練習', main: false },
            { value: 'camp', label: '合宿', main: false },
            { value: 'expedition', label: '遠征', main: false },
            { value: 'tentative', label: '仮予定', main: false },
        ];
    }

    static kindMainOptions() {
        return AidUniteScheduleQuickModal.kindOptions().filter((o) => o.main);
    }

    static kindMoreOptions() {
        return AidUniteScheduleQuickModal.kindOptions().filter((o) => !o.main);
    }

    /** 仮予定選択時の内訳（試合の募集・仮予定以外） */
    static tentativeKindOptions() {
        return AidUniteScheduleQuickModal.kindOptions()
            .filter((o) => o.value !== 'recruit' && o.value !== 'tentative');
    }

    static tentativeBaseKindToScheduleType(baseKind) {
        const map = {
            practice: '練習',
            meeting: 'ミーティング',
            rest: '休み',
            official_match: '公式試合',
            practice_match: '練習試合',
            joint_practice: '合同練習',
            camp: '合宿',
            expedition: '遠征',
        };
        const label = map[String(baseKind || '').trim()] || '練習';
        return label.includes('（仮）') ? label : `${label}（仮）`;
    }

    static scheduleTypeToTentativeBaseKind(scheduleType) {
        const base = String(scheduleType || '').replace(/（仮）|\(仮\)/g, '').trim();
        if (!base) return '';
        const hit = AidUniteScheduleQuickModal.tentativeKindOptions()
            .find((o) => o.label === base);
        if (hit) return hit.value;
        const fallback = {
            練習: 'practice',
            休み: 'rest',
            ミーティング: 'meeting',
            公式試合: 'official_match',
            練習試合: 'practice_match',
            合同練習: 'joint_practice',
            合宿: 'camp',
            遠征: 'expedition',
        };
        return fallback[base] || 'practice';
    }

    static inferEditKindState(schedule) {
        const intent = String(schedule?.intent || '').toLowerCase();
        const type = String(schedule?.schedule_type || schedule?.type || '');
        const uiKind = String(schedule?.ui_schedule_kind || '');
        const isTentative = intent === 'tentative'
            || uiKind === 'tentative'
            || /（仮）|\(仮\)/.test(type);
        if (isTentative) {
            return {
                kind: 'tentative',
                tentative_base_kind: AidUniteScheduleQuickModal.scheduleTypeToTentativeBaseKind(type),
            };
        }
        return {
            kind: uiKind || 'practice',
            tentative_base_kind: '',
        };
    }

    static isTentativeSchedule(schedule) {
        if (!schedule) return false;
        const intent = String(schedule.intent || '').toLowerCase();
        const uiKind = String(schedule.ui_schedule_kind || '');
        const type = String(schedule.schedule_type || schedule.type || '');
        return intent === 'tentative'
            || uiKind === 'tentative'
            || /（仮）|\(仮\)/.test(type);
    }

    static isKindInMore(value) {
        return AidUniteScheduleQuickModal.kindMoreOptions().some((o) => o.value === value);
    }

    static buildTentativeKindPickerHtml(selectedValue = '') {
        return `<div class="sqm-tentative-kind-grid">${AidUniteScheduleQuickModal.tentativeKindOptions()
            .map((o) => {
                const checked = o.value === selectedValue ? ' checked' : '';
                const sel = o.value === selectedValue ? ' sqm-kind-pill--selected' : '';
                return `<label class="sqm-kind-pill sqm-kind-pill--sub${sel}"><input type="radio" name="sqm_tentative_kind" value="${o.value}" class="sqm-kind-pill__input"${checked}><span>${AidUniteScheduleQuickModal.escapeHtml(o.label)}</span></label>`;
            })
            .join('')}</div>`;
    }

    static syncTentativeKindPillSelected(root) {
        const kind = root.querySelector('input[name="sqm_tentative_kind"]:checked')?.value || '';
        root.querySelectorAll('#sqm-tentative-kind-picker .sqm-kind-pill').forEach((pill) => {
            const input = pill.querySelector('input[name="sqm_tentative_kind"]');
            pill.classList.toggle('sqm-kind-pill--selected', !!(input && input.value === kind));
        });
    }

    static validateTentativeDraft(draft) {
        if (!draft || draft.ui_schedule_kind !== 'tentative') {
            return '';
        }
        if (!draft.tentative_base_kind) {
            return '仮予定の内容（練習・休みなど）を選択してください';
        }
        return '';
    }

    /** 会場名入力を表示する種別（遠征・試合・合宿） */
    static kindsWithVenueNameInput() {
        return ['official_match', 'practice_match', 'expedition', 'camp'];
    }

    static kindNeedsVenueNameInput(kind, tentativeBaseKind = '') {
        const effective = kind === 'tentative'
            ? String(tentativeBaseKind || '').trim()
            : String(kind || '').trim();
        return AidUniteScheduleQuickModal.kindsWithVenueNameInput().includes(effective);
    }

    static readVenueNameKindState(root) {
        if (!root) {
            return { kind: 'practice', tentativeBaseKind: '' };
        }
        const kind = root.querySelector('input[name="sqm_kind"]:checked')?.value || 'practice';
        const tentativeBaseKind = kind === 'tentative'
            ? (root.querySelector('input[name="sqm_tentative_kind"]:checked')?.value || '')
            : '';
        return { kind, tentativeBaseKind };
    }

    static buildChoiceRowHtml(name, options, selectedValue) {
        return `<div class="sqm-radios">${options
            .map((o) => {
                const val = String(o.value);
                const checked = val === String(selectedValue) ? ' checked' : '';
                const label = o.label != null ? String(o.label) : val;
                return `<label class="sqm-radio"><input type="radio" name="${AidUniteScheduleQuickModal.escapeHtml(name)}" value="${AidUniteScheduleQuickModal.escapeHtml(val)}"${checked}> ${AidUniteScheduleQuickModal.escapeHtml(label)}</label>`;
            })
            .join('')}</div>`;
    }

    static buildKindPickerHtml(selectedValue = 'practice') {
        const main = AidUniteScheduleQuickModal.kindMainOptions()
            .map((o) => {
                const checked = o.value === selectedValue ? ' checked' : '';
                const sel = o.value === selectedValue ? ' sqm-kind-pill--selected' : '';
                return `<label class="sqm-kind-pill${sel}"><input type="radio" name="sqm_kind" value="${o.value}" class="sqm-kind-pill__input"${checked}><span>${AidUniteScheduleQuickModal.escapeHtml(o.label)}</span></label>`;
            })
            .join('');
        const more = AidUniteScheduleQuickModal.kindMoreOptions()
            .map((o) => {
                const checked = o.value === selectedValue ? ' checked' : '';
                const sel = o.value === selectedValue ? ' sqm-kind-pill--selected' : '';
                return `<label class="sqm-kind-pill sqm-kind-pill--sub${sel}"><input type="radio" name="sqm_kind" value="${o.value}" class="sqm-kind-pill__input"${checked}><span>${AidUniteScheduleQuickModal.escapeHtml(o.label)}</span></label>`;
            })
            .join('');
        const expanded = AidUniteScheduleQuickModal.isKindInMore(selectedValue);
        return `
            <div class="sqm-kind-main">${main}</div>
            <button type="button" class="sqm-kind-more-toggle" id="sqm-kind-more-toggle" aria-expanded="${expanded ? 'true' : 'false'}">その他 <span class="sqm-kind-more-caret">${expanded ? '▲' : '▼'}</span></button>
            <div class="sqm-kind-more${expanded ? '' : ' sqm-is-hidden'}" id="sqm-kind-more">${more}</div>`;
    }

    static syncKindPillSelected(root) {
        const kind = root.querySelector('input[name="sqm_kind"]:checked')?.value || 'practice';
        root.querySelectorAll('.sqm-kind-pill').forEach((pill) => {
            const input = pill.querySelector('input[name="sqm_kind"]');
            pill.classList.toggle('sqm-kind-pill--selected', !!(input && input.value === kind));
        });
    }

    static repeatOptions(dateString) {
        const d = new Date(`${dateString}T12:00:00`);
        if (Number.isNaN(d.getTime())) {
            return [{ value: 'none', label: '繰り返さない' }];
        }
        const weekdays = ['日', '月', '火', '水', '木', '金', '土'];
        const w = weekdays[d.getDay()];
        const month = d.getMonth() + 1;
        const day = d.getDate();
        const weekOfMonth = Math.ceil(day / 7);
        return [
            { value: 'none', label: '繰り返さない' },
            { value: 'daily', label: '毎日' },
            { value: 'weekly', label: `毎週 ${w}曜日` },
            { value: 'monthly_weekday', label: `毎月 第${weekOfMonth} ${w}曜日` },
            { value: 'yearly', label: `毎年 ${month}月${day}日` },
            { value: 'weekdays', label: '毎週平日（月〜金）' },
            { value: 'custom', label: 'カスタム...' },
        ];
    }

    static buildRepeatSelectHtml(dateString, selectedValue = 'none') {
        const opts = AidUniteScheduleQuickModal.repeatOptions(dateString)
            .map((o) => {
                const sel = o.value === selectedValue ? ' selected' : '';
                return `<option value="${o.value}"${sel}>${AidUniteScheduleQuickModal.escapeHtml(o.label)}</option>`;
            })
            .join('');
        return `<div class="sqm-select-wrap">`
            + `<select id="sqm-repeat" class="sqm-input sqm-input--select" aria-label="繰り返し">${opts}</select>`
            + `<span class="sqm-select-chevron" aria-hidden="true">▼</span>`
            + `</div>`;
    }

    static applyGenderTheme(root) {
        if (!root) return;
        const g = AidUniteScheduleQuickModal.defaultGender();
        root.classList.remove('sqm--theme-male', 'sqm--theme-female');
        if (g === 'male') {
            root.classList.add('sqm--theme-male');
        } else if (g === 'female') {
            root.classList.add('sqm--theme-female');
        }
    }

    static buildCustomRepeatLabel(custom) {
        if (!custom || typeof custom !== 'object') {
            return 'カスタム';
        }
        const interval = Math.max(1, parseInt(String(custom.interval || 1), 10) || 1);
        const unitMap = { day: '日', week: '週', month: '月', year: '年' };
        const unit = unitMap[String(custom.unit || 'week')] || '週';
        let end = '';
        if (custom.end === 'date' && custom.endDate) {
            end = `、${custom.endDate}まで`;
        } else if (custom.end === 'count') {
            const c = Math.max(1, parseInt(String(custom.endCount || 1), 10) || 1);
            end = `、${c}回`;
        }
        return `${interval}${unit}ごと${end}`;
    }

    static buildCustomRepeatPanelHtml(custom = null) {
        const c = custom && typeof custom === 'object' ? custom : {};
        const interval = Math.max(1, parseInt(String(c.interval || 1), 10) || 1);
        const unit = ['day', 'week', 'month', 'year'].includes(String(c.unit)) ? String(c.unit) : 'week';
        const end = ['never', 'date', 'count'].includes(String(c.end)) ? String(c.end) : 'never';
        const endDate = String(c.endDate || '');
        const endCount = Math.max(1, Math.min(99, parseInt(String(c.endCount || 10), 10) || 10));
        return `
            <div id="sqm-repeat-custom" class="sqm-repeat-custom sqm-is-hidden">
                <div class="sqm-row-2">
                    <div>
                        <label class="sqm-label" for="sqm-custom-interval">間隔</label>
                        <input type="number" id="sqm-custom-interval" class="sqm-input" min="1" max="99" value="${interval}">
                    </div>
                    <div>
                        <label class="sqm-label" for="sqm-custom-unit">単位</label>
                        <select id="sqm-custom-unit" class="sqm-input">
                            <option value="day"${unit === 'day' ? ' selected' : ''}>日</option>
                            <option value="week"${unit === 'week' ? ' selected' : ''}>週</option>
                            <option value="month"${unit === 'month' ? ' selected' : ''}>月</option>
                            <option value="year"${unit === 'year' ? ' selected' : ''}>年</option>
                        </select>
                    </div>
                </div>
                <span class="sqm-label">終了</span>
                <div class="sqm-radios sqm-radios--stack">
                    <label class="sqm-radio"><input type="radio" name="sqm_custom_end" value="never"${end === 'never' ? ' checked' : ''}> なし</label>
                    <label class="sqm-radio sqm-radio--wrap"><input type="radio" name="sqm_custom_end" value="date"${end === 'date' ? ' checked' : ''}> 日付 <input type="date" id="sqm-custom-end-date" class="sqm-input sqm-input--inline" value="${AidUniteScheduleQuickModal.escapeHtml(endDate)}"></label>
                    <label class="sqm-radio sqm-radio--wrap"><input type="radio" name="sqm_custom_end" value="count"${end === 'count' ? ' checked' : ''}> <input type="number" id="sqm-custom-end-count" class="sqm-input sqm-input--inline sqm-input--narrow" min="1" max="99" value="${endCount}"> 回</label>
                </div>
            </div>`;
    }

    static readCustomRepeatFromDom() {
        return {
            interval: document.getElementById('sqm-custom-interval')?.value || '1',
            unit: document.getElementById('sqm-custom-unit')?.value || 'week',
            end: document.querySelector('input[name="sqm_custom_end"]:checked')?.value || 'never',
            endDate: document.getElementById('sqm-custom-end-date')?.value || '',
            endCount: document.getElementById('sqm-custom-end-count')?.value || '10',
        };
    }

    static parseNoteRepeat(note) {
        const raw = String(note || '');
        const m = raw.match(/^\[繰り返し:\s*([^\]]+)\]\s*/);
        if (!m) {
            return { repeat: 'none', custom: null, memo: raw };
        }
        const body = m[1].trim();
        const memo = raw.slice(m[0].length);
        if (body.startsWith('{')) {
            try {
                const custom = JSON.parse(body);
                return { repeat: 'custom', custom, memo };
            } catch (e) {
                return { repeat: 'custom', custom: null, memo };
            }
        }
        const presets = ['none', 'daily', 'weekly', 'monthly_weekday', 'yearly', 'weekdays'];
        const opts = AidUniteScheduleQuickModal.repeatOptions(new Date().toISOString().slice(0, 10));
        const hit = opts.find((o) => o.label === body);
        if (hit) {
            return { repeat: hit.value, custom: null, memo };
        }
        if (body.indexOf('カスタム') === 0 || body.indexOf('ごと') >= 0) {
            return { repeat: 'custom', custom: null, memo };
        }
        return { repeat: 'none', custom: null, memo: raw };
    }

    static appendRepeatToNote(repeat, dateString, userNote, customRepeat) {
        if (!repeat || repeat === 'none') {
            return userNote || '';
        }
        let label = '';
        if (repeat === 'custom') {
            label = JSON.stringify(AidUniteScheduleQuickModal.readCustomRepeatFromDom());
        } else {
            label = AidUniteScheduleQuickModal.repeatOptions(dateString)
                .find((o) => o.value === repeat)?.label || repeat;
        }
        const prefix = `[繰り返し: ${label}]`;
        const note = String(userNote || '').trim();
        return note ? `${prefix} ${note}` : prefix;
    }

    static syncRepeatCustomPanel() {
        const repeat = document.getElementById('sqm-repeat')?.value || 'none';
        const panel = document.getElementById('sqm-repeat-custom');
        if (panel) {
            panel.classList.toggle('sqm-is-hidden', repeat !== 'custom');
        }
    }

    static placeOptions() {
        return [
            { value: 'either', label: 'どちらでも可' },
            { value: 'home', label: 'ホーム' },
            { value: 'away', label: 'アウェイ' },
        ];
    }

    static visibilityOptions() {
        return [
            { value: 'team', label: 'チーム' },
            { value: 'personal', label: '非公開' },
        ];
    }

    static hourOptions() {
        const hours = [];
        for (let h = 6; h <= 22; h += 1) {
            hours.push(String(h).padStart(2, '0'));
        }
        return hours;
    }

    static minuteOptions() {
        return ['00', '30'];
    }

    static parseTimeValue(value, fallbackHour, fallbackMinute) {
        const m = String(value || '').match(/^(\d{1,2}):(\d{2})$/);
        if (!m) {
            return { hour: fallbackHour, minute: fallbackMinute };
        }
        const hour = String(parseInt(m[1], 10)).padStart(2, '0');
        let minute = m[2];
        if (!AidUniteScheduleQuickModal.minuteOptions().includes(minute)) {
            minute = parseInt(minute, 10) < 30 ? '00' : '30';
        }
        return { hour, minute };
    }

    static buildTimeFieldHtml(prefix, defaultValue) {
        const parsed = AidUniteScheduleQuickModal.parseTimeValue(defaultValue, '13', '00');
        const hourOpts = AidUniteScheduleQuickModal.hourOptions()
            .map((h) => `<option value="${h}"${h === parsed.hour ? ' selected' : ''}>${h}</option>`)
            .join('');
        const minuteOpts = AidUniteScheduleQuickModal.minuteOptions()
            .map((m) => `<option value="${m}"${m === parsed.minute ? ' selected' : ''}>${m}</option>`)
            .join('');
        return `
            <div class="sqm-time-pickers">
                <select id="${prefix}-hour" class="sqm-input sqm-time-select" aria-label="時">${hourOpts}</select>
                <span class="sqm-time-colon">:</span>
                <select id="${prefix}-minute" class="sqm-input sqm-time-select" aria-label="分">${minuteOpts}</select>
            </div>`;
    }

    static readTimeField(prefix) {
        const hour = document.getElementById(`${prefix}-hour`)?.value || '00';
        const minute = document.getElementById(`${prefix}-minute`)?.value || '00';
        return `${hour}:${minute}`;
    }

    /** 終日チェックを表示する UI 種別 */
    static kindsWithAllDayOption() {
        return ['rest', 'camp', 'expedition'];
    }

    static allowsAllDayKind(kind, tentativeBaseKind = '') {
        const k = String(kind || '').trim();
        if (k === 'tentative') {
            const base = String(tentativeBaseKind || '').trim();
            if (base) {
                return AidUniteScheduleQuickModal.kindsWithAllDayOption().includes(base);
            }
            return true;
        }
        return AidUniteScheduleQuickModal.kindsWithAllDayOption().includes(k);
    }

    static allowsAllDayForDraft(draft) {
        if (!draft) return false;
        return AidUniteScheduleQuickModal.allowsAllDayKind(
            draft.ui_schedule_kind || 'practice',
            draft.tentative_base_kind || ''
        );
    }

    static readAllDayKindState(root) {
        if (!root) {
            return { kind: 'practice', tentativeBaseKind: '' };
        }
        const kind = root.querySelector('input[name="sqm_kind"]:checked')?.value || 'practice';
        const tentativeBaseKind = kind === 'tentative'
            ? (root.querySelector('input[name="sqm_tentative_kind"]:checked')?.value || '')
            : '';
        return { kind, tentativeBaseKind };
    }

    static isAllDayFromSchedule(schedule) {
        const start = String(schedule?.start_time || '').trim();
        const end = String(schedule?.end_time || '').trim();
        if (start || end) {
            return false;
        }
        if (typeof AidUniteScheduleUtils !== 'undefined' && AidUniteScheduleUtils.scheduleAllowsAllDayDisplay(schedule)) {
            return true;
        }
        const kind = String(schedule?.ui_schedule_kind || '');
        return AidUniteScheduleQuickModal.allowsAllDayKind(kind);
    }

    static formatScheduleTimeDisplay(item) {
        const kind = String(item?.ui_schedule_kind || '');
        const start = String(item?.start_time || '').trim();
        const end = String(item?.end_time || '').trim();
        if (start && end) {
            return `${start} 〜 ${end}`;
        }
        if (start || end) {
            return start || end;
        }
        if (typeof AidUniteScheduleUtils !== 'undefined' && AidUniteScheduleUtils.scheduleAllowsAllDayDisplay(item)) {
            return '終日';
        }
        const baseKind = String(item?.tentative_base_kind || '').trim();
        if (AidUniteScheduleQuickModal.allowsAllDayKind(kind, baseKind)) {
            return '終日';
        }
        return '';
    }

    static syncAllDayUi(root) {
        if (!root) {
            return;
        }
        const { kind, tentativeBaseKind } = AidUniteScheduleQuickModal.readAllDayKindState(root);
        const allows = AidUniteScheduleQuickModal.allowsAllDayKind(kind, tentativeBaseKind);
        const field = document.getElementById('sqm-all-day-field');
        const timeRow = root.querySelector('.sqm-row-time');
        if (field) {
            field.hidden = !allows;
            field.classList.toggle('sqm-is-hidden', !allows);
        }
        if (!allows) {
            const cb = document.getElementById('sqm-all-day');
            if (cb) {
                cb.checked = false;
            }
        }
        const allDay = allows && !!document.getElementById('sqm-all-day')?.checked;
        if (timeRow) {
            timeRow.hidden = allDay;
            timeRow.classList.toggle('sqm-is-hidden', allDay);
            timeRow.querySelectorAll('select').forEach((el) => {
                el.disabled = allDay;
            });
        }
    }

    static validateDraftTimes(draft) {
        if (!AidUniteScheduleQuickModal.allowsAllDayForDraft(draft)) {
            return '';
        }
        const start = String(draft?.start_time || '').trim();
        const end = String(draft?.end_time || '').trim();
        if (!start && !end) {
            return '';
        }
        if (!start || !end) {
            return '開始・終了時間を入力するか、終日を選択してください';
        }
        const toMin = (t) => {
            const m = String(t).match(/^(\d{1,2}):(\d{2})$/);
            if (!m) return -1;
            return (parseInt(m[1], 10) * 60) + parseInt(m[2], 10);
        };
        if (toMin(start) < 0 || toMin(end) < 0 || toMin(start) >= toMin(end)) {
            return '終了時間は開始時間より後にしてください';
        }
        return '';
    }

    static buildRecruitTeamCountHtml(defaultCount) {
        const max = AidUniteScheduleQuickModal.maxRecruitTeamCount();
        const n = Math.max(1, Math.min(max, parseInt(String(defaultCount || 1), 10) || 1));
        const opts = [];
        for (let i = 1; i <= max; i += 1) {
            opts.push({ value: String(i), label: String(i) });
        }
        return AidUniteScheduleQuickModal.buildChoiceRowHtml('sqm_recruit_teams', opts, String(n));
    }

    static recruitTeamCountLabel(draft) {
        const n = draft.gender_condition === 'female'
            ? (parseInt(String(draft.female_teams || 0), 10) || 0)
            : (parseInt(String(draft.male_teams || 0), 10) || 0);
        if (n < 1) {
            return '—';
        }
        const g = draft.gender_condition === 'female' ? '女子' : '男子';
        return `${g}${n}チーム`;
    }

    static applyRecruitSlotsToDraft(draft) {
        const place = draft.venue_condition || 'either';
        const gender = draft.gender_condition || '';
        let count = 1;
        if (place !== 'away') {
            const el = document.querySelector('input[name="sqm_recruit_teams"]:checked');
            count = Math.max(1, Math.min(AidUniteScheduleQuickModal.maxRecruitTeamCount(), parseInt(el?.value || '1', 10) || 1));
        }
        if (gender === 'female') {
            draft.male_teams = 0;
            draft.female_teams = count;
        } else {
            draft.male_teams = count;
            draft.female_teams = 0;
        }
        draft.venue_name = document.getElementById('sqm-venue-name')?.value?.trim() || '';
        return draft;
    }

    static sanitizeErrorMessage(raw) {
        const text = String(raw || '').trim();
        if (!text) {
            return '処理に失敗しました。時間をおいて再度お試しください。';
        }
        if (/<\/?[a-z][\s\S]*>/i.test(text)) {
            return 'サーバーでエラーが発生しました。時間をおいて再度お試しください。';
        }
        return text;
    }

    static setRegisterStep(root, step, mode = 'create') {
        const isConfirm = step === 'confirm';
        root.querySelector('#sqm-body-register')?.classList.toggle('sqm-is-hidden', isConfirm);
        root.querySelector('#sqm-body-confirm')?.classList.toggle('sqm-is-hidden', !isConfirm);
        root.querySelector('#sqm-footer-input')?.classList.toggle('sqm-is-hidden', isConfirm);
        root.querySelector('#sqm-footer-confirm')?.classList.toggle('sqm-is-hidden', !isConfirm);
        const title = root.querySelector('#sqm-title');
        if (title) {
            const recruitOnly = root?.dataset?.sqmRecruitOnly === '1';
            title.textContent = isConfirm
                ? '内容の確認'
                : (recruitOnly && mode !== 'edit'
                    ? '試合の募集を登録する'
                    : (mode === 'edit' ? '予定を編集' : '予定を登録'));
        }
    }

    static findScheduleById(scheduleId) {
        const sid = String(scheduleId || '').trim();
        if (!sid) return null;
        if (typeof AidUniteScheduleModal !== 'undefined' && typeof AidUniteScheduleModal.findScheduleById === 'function') {
            const fromModal = AidUniteScheduleModal.findScheduleById(sid);
            if (fromModal) return fromModal;
        }
        const pool = window.schedules;
        if (!pool || typeof pool !== 'object') return null;
        for (const key of Object.keys(pool)) {
            const arr = pool[key];
            if (!Array.isArray(arr)) continue;
            const hit = arr.find((s) => String(s.id || s.schedule_id) === sid);
            if (hit) return hit;
        }
        return null;
    }

    static openEdit(schedule) {
        if (!schedule || !AidUniteScheduleQuickModal.canEdit()) return;
        const parsed = AidUniteScheduleQuickModal.parseNoteRepeat(
            schedule.note || schedule.memo || schedule.quick_memo || ''
        );
        const gender = String(schedule.gender_condition || schedule.gender || '').toLowerCase()
            || AidUniteScheduleQuickModal.defaultGender();
        const maleSlots = parseInt(String(schedule.male_slots ?? schedule.male_teams ?? 0), 10) || 0;
        const femaleSlots = parseInt(String(schedule.female_slots ?? schedule.female_teams ?? 0), 10) || 0;
        let teamCount = gender === 'female' ? femaleSlots : maleSlots;
        if (teamCount < 1) teamCount = 1;
        const canFull = schedule.can_edit_full_fields !== false
            && schedule.can_edit_full_fields !== '0'
            && schedule.can_edit_full_fields !== 0;
        const kindState = AidUniteScheduleQuickModal.inferEditKindState(schedule);
        AidUniteScheduleQuickModal.openFormModal({
            mode: 'edit',
            postId: parseInt(String(schedule.id || schedule.schedule_id || 0), 10) || 0,
            dateString: String(schedule.date || ''),
            memoOnly: !canFull,
            initial: {
                kind: kindState.kind,
                tentative_base_kind: kindState.tentative_base_kind,
                start_time: schedule.start_time || '13:00',
                end_time: schedule.end_time || '14:00',
                all_day: AidUniteScheduleQuickModal.isAllDayFromSchedule(schedule),
                venue_condition: schedule.venue_condition || schedule.schedule_place_option || 'either',
                venue_name: schedule.venue_name || '',
                gender_condition: gender,
                visibility: schedule.schedule_visibility || (schedule.is_personal ? 'personal' : 'team'),
                repeat: parsed.repeat,
                customRepeat: parsed.custom,
                memo: parsed.memo,
                attendance_required: schedule.attendance_required === '1'
                    || schedule.attendance_required === true
                    || schedule.attendance_required === 1,
                teamCount,
            },
        });
    }

    static openEditById(scheduleId) {
        const schedule = AidUniteScheduleQuickModal.findScheduleById(scheduleId);
        if (schedule) {
            AidUniteScheduleQuickModal.openEdit(schedule);
            return;
        }
        AidUniteScheduleQuickModal.toast('スケジュールを読み込めません。ページを再読み込みしてください。', 'warning');
    }

    static buildRegisterInitial(kind = 'recruit') {
        return {
            kind,
            start_time: AidUniteScheduleQuickModal.getConfig().defaultStartTime || '13:00',
            end_time: AidUniteScheduleQuickModal.getConfig().defaultEndTime || '14:00',
            all_day: false,
            venue_condition: 'either',
            venue_name: '',
            gender_condition: AidUniteScheduleQuickModal.defaultGender(),
            visibility: 'team',
            repeat: 'none',
            customRepeat: null,
            memo: '',
            attendance_required: false,
            teamCount: parseInt(String(AidUniteScheduleQuickModal.getConfig().defaultRecruitTeams || 1), 10) || 1,
        };
    }

    static openRegister(dateString) {
        AidUniteScheduleQuickModal.openFormModal({
            mode: 'create',
            postId: 0,
            dateString,
            memoOnly: false,
            initial: AidUniteScheduleQuickModal.buildRegisterInitial('recruit'),
        });
    }

    static openRegisterRecruit(dateString) {
        AidUniteScheduleQuickModal.openFormModal({
            mode: 'create',
            postId: 0,
            dateString,
            memoOnly: false,
            recruitOnly: true,
            initial: AidUniteScheduleQuickModal.buildRegisterInitial('recruit'),
        });
    }

    static openFormModal(formState) {
        if (!AidUniteScheduleQuickModal.canEdit()) {
            return;
        }
        AidUniteScheduleQuickModal.destroyImmediately();
        const cfg = AidUniteScheduleQuickModal.getConfig();
        const init = formState.initial || {};
        const mode = formState.mode === 'edit' ? 'edit' : 'create';
        const isEdit = mode === 'edit';
        const memoOnly = !!formState.memoOnly;
        const recruitOnly = !!formState.recruitOnly;
        let dateString = String(formState.dateString || '').trim();
        if (!dateString) {
            if (typeof AidUniteDateUtils !== 'undefined' && AidUniteDateUtils.formatDateLocal) {
                dateString = AidUniteDateUtils.formatDateLocal(new Date());
            } else {
                dateString = new Date().toISOString().split('T')[0];
            }
        }
        const gender = String(init.gender_condition || AidUniteScheduleQuickModal.defaultGender());
        const genderLabel = gender === 'male' ? '男子' : (gender === 'female' ? '女子' : 'チーム登録の性別');
        const kindPicker = recruitOnly
            ? ''
            : AidUniteScheduleQuickModal.buildKindPickerHtml(init.kind || 'practice');
        const placeRow = AidUniteScheduleQuickModal.buildChoiceRowHtml(
            'sqm_place',
            AidUniteScheduleQuickModal.placeOptions(),
            init.venue_condition || 'either'
        );
        const visRow = AidUniteScheduleQuickModal.buildChoiceRowHtml(
            'sqm_visibility',
            AidUniteScheduleQuickModal.visibilityOptions(),
            init.visibility || 'team'
        );
        const teamCountRow = AidUniteScheduleQuickModal.buildRecruitTeamCountHtml(init.teamCount || 1);
        const repeatVal = init.repeat || 'none';
        const dateFieldHtml = memoOnly
            ? `<p class="sqm-date">${AidUniteScheduleQuickModal.escapeHtml(AidUniteScheduleQuickModal.formatDateLabel(dateString))}</p>`
            : `<label class="sqm-label" for="sqm-date-input">日付</label><input type="date" id="sqm-date-input" class="sqm-input" value="${AidUniteScheduleQuickModal.escapeHtml(dateString)}">`;
        const titleText = recruitOnly && !isEdit
            ? '試合の募集を登録する'
            : (isEdit ? '予定を編集' : '予定を登録');
        const saveLabel = isEdit ? '保存する' : '登録する';
        const memoOnlyBanner = memoOnly
            ? '<p class="sqm-memo-only-hint">申請中または成立に関するマッチがあるため、メモと出欠確認のみ変更できます。</p>'
            : '';

        const html = `
            <div id="aidunite-schedule-quick-modal" class="aidunite-schedule-quick-modal" role="dialog" aria-modal="true" aria-labelledby="sqm-title">
                <div class="sqm-overlay" data-sqm-close="1"></div>
                <div class="sqm-panel">
                    <header class="sqm-header">
                        <h3 id="sqm-title">${titleText}</h3>
                        ${AidUniteScheduleQuickModal.closeButtonHtml()}
                    </header>
                    <div class="sqm-body" id="sqm-body-register">
                        ${memoOnlyBanner}
                        <div class="sqm-field sqm-field--date sqm-form-head">${dateFieldHtml}</div>
                        ${recruitOnly ? '<input type="hidden" id="sqm-kind-recruit-lock" value="recruit">' : `<div class="sqm-field sqm-field--kind" data-sqm-lockable="1">
                            <div id="sqm-kind-picker">${kindPicker}</div>
                        </div>`}
                        <div class="sqm-field sqm-tentative-only sqm-is-hidden" id="sqm-tentative-kind-field" hidden data-sqm-lockable="1">
                            <div id="sqm-tentative-kind-picker">${AidUniteScheduleQuickModal.buildTentativeKindPickerHtml(init.tentative_base_kind || '')}</div>
                        </div>
                        <div class="sqm-field sqm-field--section sqm-field--time" data-sqm-lockable="1">
                            <div class="sqm-all-day-field sqm-is-hidden" id="sqm-all-day-field" hidden>
                                <label class="sqm-checkbox">
                                    <input type="checkbox" id="sqm-all-day" value="1"${init.all_day ? ' checked' : ''}>
                                    <span>終日</span>
                                </label>
                            </div>
                            <div class="sqm-row-time sqm-row-time--inline">
                                ${AidUniteScheduleQuickModal.buildTimeFieldHtml('sqm-start', init.start_time || '13:00')}
                                <span class="sqm-time-range-sep" aria-hidden="true">～</span>
                                ${AidUniteScheduleQuickModal.buildTimeFieldHtml('sqm-end', init.end_time || '14:00')}
                            </div>
                        </div>
                        <div class="sqm-field sqm-field--section sqm-normal-only" data-sqm-lockable="1">
                            <label class="sqm-label" for="sqm-repeat">繰り返し</label>
                            ${AidUniteScheduleQuickModal.buildRepeatSelectHtml(dateString, repeatVal)}
                            ${AidUniteScheduleQuickModal.buildCustomRepeatPanelHtml(init.customRepeat)}
                        </div>
                        <div class="sqm-field sqm-recruit-only" hidden data-sqm-lockable="1">
                            <span class="sqm-label">会場条件</span>
                            ${placeRow}
                        </div>
                        <div class="sqm-field sqm-recruit-only" hidden data-sqm-lockable="1">
                            <span class="sqm-label">性別</span>
                            <input type="text" id="sqm-gender-display" class="sqm-input sqm-input--readonly" readonly value="${AidUniteScheduleQuickModal.escapeHtml(genderLabel)}">
                            <input type="hidden" id="sqm-gender" value="${AidUniteScheduleQuickModal.escapeHtml(gender)}">
                        </div>
                        <div class="sqm-field sqm-recruit-only sqm-recruit-team-count sqm-is-hidden" hidden data-sqm-lockable="1">
                            <span class="sqm-label">募集数</span>
                            ${teamCountRow}
                        </div>
                        <div class="sqm-field sqm-field--section sqm-venue-name-field sqm-is-hidden" hidden id="sqm-venue-name-field" data-sqm-lockable="1">
                            <label class="sqm-label" for="sqm-venue-name">会場名（任意）</label>
                            <input type="text" id="sqm-venue-name" class="sqm-input" placeholder="例: ○○体育館" value="${AidUniteScheduleQuickModal.escapeHtml(init.venue_name || '')}">
                        </div>
                        <div class="sqm-field sqm-field--section sqm-normal-only" data-sqm-lockable="1">
                            <span class="sqm-label">公開範囲</span>
                            ${visRow}
                        </div>
                        <div class="sqm-field sqm-field--section sqm-field--attendance sqm-normal-only">
                            <label class="sqm-checkbox">
                                <input type="checkbox" id="sqm-attendance-required" value="1"${init.attendance_required ? ' checked' : ''}>
                                <span>出欠確認が必要</span>
                            </label>
                            <p class="sqm-hint">チェックすると、保護者・選手に出欠確認通知を送信します。</p>
                        </div>
                        <div class="sqm-field sqm-field--section sqm-field--memo">
                            <label class="sqm-label" for="sqm-memo">メモ</label>
                            <textarea id="sqm-memo" class="sqm-textarea" rows="2" placeholder="補足があれば入力">${AidUniteScheduleQuickModal.escapeHtml(init.memo || '')}</textarea>
                        </div>
                    </div>
                    <div class="sqm-body sqm-body--confirm sqm-is-hidden" id="sqm-body-confirm"></div>
                    <footer class="sqm-footer" id="sqm-footer-input">
                        <button type="button" class="btn btn-secondary" data-sqm-close="1">キャンセル</button>
                        <button type="button" class="btn btn-primary sqm-cta-normal" id="sqm-submit-normal">${saveLabel}</button>
                        <button type="button" class="btn btn-primary sqm-cta-recruit sqm-is-hidden" id="sqm-submit-recruit-next">${isEdit ? saveLabel : '確認へ進む'}</button>
                    </footer>
                    <footer class="sqm-footer sqm-footer--confirm-only sqm-is-hidden" id="sqm-footer-confirm">
                        <button type="button" class="btn btn-secondary sqm-full" id="sqm-back-confirm">戻る</button>
                        <button type="button" class="btn btn-primary sqm-full" id="sqm-submit-recruit-publish">${isEdit ? '保存' : '登録'}</button>
                    </footer>
                </div>
            </div>`;

        document.body.insertAdjacentHTML('beforeend', html);
        const root = AidUniteScheduleQuickModal.getActiveModalRoot();
        if (!root) return;
        if (recruitOnly) {
            root.dataset.sqmRecruitOnly = '1';
        }
        const state = {
            mode,
            postId: parseInt(String(formState.postId || 0), 10) || 0,
            date: dateString,
            draft: null,
            submitting: false,
            memoOnly,
            recruitOnly,
        };
        AidUniteScheduleQuickModal.applyGenderTheme(root);
        AidUniteScheduleQuickModal.setRegisterStep(root, 'input', mode);
        if (isEdit) {
            root.querySelector('#sqm-footer-confirm')?.classList.add('sqm-is-hidden');
        }

        const applyMemoOnlyLock = () => {
            root.querySelectorAll('[data-sqm-lockable="1"]').forEach((el) => {
                el.querySelectorAll('input, select, textarea, button').forEach((input) => {
                    if (input.id === 'sqm-memo' || input.id === 'sqm-attendance-required') return;
                    input.disabled = memoOnly;
                });
            });
            const picker = document.getElementById('sqm-kind-more-toggle');
            if (picker) picker.disabled = memoOnly;
        };

        const resolveKind = () => {
            if (state.recruitOnly) {
                return 'recruit';
            }
            return root.querySelector('input[name="sqm_kind"]:checked')?.value || 'practice';
        };

        const syncRecruitVenueUi = () => {
            const kind = resolveKind();
            const teamCountField = root.querySelector('.sqm-recruit-team-count');
            if (kind !== 'recruit') {
                if (teamCountField) {
                    teamCountField.hidden = true;
                    teamCountField.classList.add('sqm-is-hidden');
                }
                return;
            }
            const place = root.querySelector('input[name="sqm_place"]:checked')?.value || 'either';
            const showCount = place === 'home' || place === 'either';
            if (teamCountField) {
                teamCountField.hidden = !showCount;
                teamCountField.classList.toggle('sqm-is-hidden', !showCount);
            }
        };

        const syncKindUi = () => {
            const kind = resolveKind();
            const isRecruit = kind === 'recruit';
            const isTentative = kind === 'tentative';
            root.querySelectorAll('.sqm-recruit-only').forEach((el) => {
                el.hidden = !isRecruit;
                el.classList.toggle('sqm-is-hidden', !isRecruit);
            });
            root.querySelectorAll('.sqm-normal-only').forEach((el) => {
                el.hidden = isRecruit;
                el.classList.toggle('sqm-is-hidden', isRecruit);
            });
            const tentativeField = document.getElementById('sqm-tentative-kind-field');
            if (tentativeField) {
                tentativeField.hidden = !isTentative;
                tentativeField.classList.toggle('sqm-is-hidden', !isTentative);
            }
            const { tentativeBaseKind } = AidUniteScheduleQuickModal.readVenueNameKindState(root);
            const showVenueName = isRecruit
                || AidUniteScheduleQuickModal.kindNeedsVenueNameInput(kind, tentativeBaseKind);
            const venueNameField = document.getElementById('sqm-venue-name-field');
            if (venueNameField) {
                venueNameField.hidden = !showVenueName;
                venueNameField.classList.toggle('sqm-is-hidden', !showVenueName);
            }
            const showRecruitNextCta = isRecruit && mode === 'create';
            document.getElementById('sqm-submit-normal')?.classList.toggle('sqm-is-hidden', showRecruitNextCta);
            document.getElementById('sqm-submit-recruit-next')?.classList.toggle('sqm-is-hidden', !showRecruitNextCta);
            syncRecruitVenueUi();
            AidUniteScheduleQuickModal.syncAllDayUi(root);
        };

        document.getElementById('sqm-all-day')?.addEventListener('change', () => {
            AidUniteScheduleQuickModal.syncAllDayUi(root);
        });
        document.getElementById('sqm-kind-more-toggle')?.addEventListener('click', () => {
            const more = document.getElementById('sqm-kind-more');
            const btn = document.getElementById('sqm-kind-more-toggle');
            if (!more || !btn) return;
            const isHidden = more.classList.toggle('sqm-is-hidden');
            btn.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
            const caret = btn.querySelector('.sqm-kind-more-caret');
            if (caret) caret.textContent = isHidden ? '▼' : '▲';
        });
        root.querySelectorAll('input[name="sqm_kind"]').forEach((el) => {
            el.addEventListener('change', () => {
                AidUniteScheduleQuickModal.syncKindPillSelected(root);
                syncKindUi();
            });
        });
        root.querySelectorAll('input[name="sqm_tentative_kind"]').forEach((el) => {
            el.addEventListener('change', () => {
                AidUniteScheduleQuickModal.syncTentativeKindPillSelected(root);
                AidUniteScheduleQuickModal.syncAllDayUi(root);
                syncKindUi();
            });
        });
        root.querySelectorAll('input[name="sqm_place"]').forEach((el) => {
            el.addEventListener('change', syncRecruitVenueUi);
        });
        document.getElementById('sqm-repeat')?.addEventListener('change', () => {
            AidUniteScheduleQuickModal.syncRepeatCustomPanel();
        });
        AidUniteScheduleQuickModal.syncKindPillSelected(root);
        AidUniteScheduleQuickModal.syncTentativeKindPillSelected(root);
        AidUniteScheduleQuickModal.syncRepeatCustomPanel();
        if (repeatVal === 'custom' && init.customRepeat) {
            const panel = document.getElementById('sqm-repeat-custom');
            panel?.classList.remove('sqm-is-hidden');
        }
        syncKindUi();
        applyMemoOnlyLock();

        root.querySelectorAll('[data-sqm-close]').forEach((el) => {
            el.addEventListener('click', () => AidUniteScheduleQuickModal.close());
        });
        root.classList.toggle('sqm--pc', AidUniteScheduleQuickModal.isPcLayout());

        const getFormDate = () => document.getElementById('sqm-date-input')?.value || state.date;

        const collectDraft = () => {
            const memoRaw = document.getElementById('sqm-memo')?.value || '';
            const attendanceOn = !!document.getElementById('sqm-attendance-required')?.checked;
            if (memoOnly && state.postId > 0) {
                return {
                    post_id: state.postId,
                    note: memoRaw,
                    attendance_required: attendanceOn ? '1' : '0',
                };
            }
            const kind = resolveKind();
            const tentativeBaseKind = kind === 'tentative'
                ? (root.querySelector('input[name="sqm_tentative_kind"]:checked')?.value || '')
                : '';
            const allDay = AidUniteScheduleQuickModal.allowsAllDayKind(kind, tentativeBaseKind)
                && !!document.getElementById('sqm-all-day')?.checked;
            const place = root.querySelector('input[name="sqm_place"]:checked')?.value || '';
            const visibility = root.querySelector('input[name="sqm_visibility"]:checked')?.value || 'team';
            let genderVal = document.getElementById('sqm-gender')?.value || '';
            if (!genderVal) genderVal = AidUniteScheduleQuickModal.defaultGender();
            const repeat = kind === 'recruit' ? 'none' : (document.getElementById('sqm-repeat')?.value || 'none');
            const dateVal = getFormDate();
            const draft = {
                ui_schedule_kind: kind,
                tentative_base_kind: tentativeBaseKind,
                intent: kind === 'recruit' ? 'recruit' : (kind === 'tentative' ? 'tentative' : 'confirmed'),
                certainty: kind === 'tentative' ? 'tentative' : '',
                date: dateVal,
                start_date: dateVal,
                start_time: allDay ? '' : AidUniteScheduleQuickModal.readTimeField('sqm-start'),
                end_time: allDay ? '' : AidUniteScheduleQuickModal.readTimeField('sqm-end'),
                all_day: allDay ? '1' : '0',
                venue_condition: kind === 'recruit' ? (place || 'either') : place,
                venue_name: document.getElementById('sqm-venue-name')?.value?.trim() || '',
                gender_condition: genderVal,
                schedule_visibility: kind === 'recruit' ? 'team' : visibility,
                attendance_required: (kind === 'recruit' || !attendanceOn) ? '0' : '1',
                repeat,
                note: kind === 'recruit'
                    ? memoRaw
                    : AidUniteScheduleQuickModal.appendRepeatToNote(repeat, dateVal, memoRaw),
            };
            if (state.postId > 0) draft.post_id = state.postId;
            if (kind === 'recruit') AidUniteScheduleQuickModal.applyRecruitSlotsToDraft(draft);
            if (kind === 'tentative' && tentativeBaseKind) {
                draft.schedule_type = AidUniteScheduleQuickModal.tentativeBaseKindToScheduleType(tentativeBaseKind);
            }
            return draft;
        };

        const showConfirm = () => {
            const draft = collectDraft();
            const tentativeError = AidUniteScheduleQuickModal.validateTentativeDraft(draft);
            if (tentativeError) {
                AidUniteScheduleQuickModal.toast(tentativeError, 'warning');
                return;
            }
            const timeError = AidUniteScheduleQuickModal.validateDraftTimes(draft);
            if (timeError) {
                AidUniteScheduleQuickModal.toast(timeError, 'warning');
                return;
            }
            if (!draft.gender_condition) {
                AidUniteScheduleQuickModal.toast('チームの性別が未設定です。チーム設定から性別を登録してください。', 'warning');
                return;
            }
            if (draft.ui_schedule_kind === 'recruit') {
                if (!draft.venue_condition) {
                    AidUniteScheduleQuickModal.toast('会場条件を選択してください。', 'warning');
                    return;
                }
                AidUniteScheduleQuickModal.applyRecruitSlotsToDraft(draft);
                const total = (parseInt(String(draft.male_teams || 0), 10) || 0)
                    + (parseInt(String(draft.female_teams || 0), 10) || 0);
                if (total < 1) {
                    AidUniteScheduleQuickModal.toast('募集数を指定してください。', 'warning');
                    return;
                }
            }
            state.draft = draft;
            AidUniteScheduleQuickModal.setRegisterStep(root, 'confirm', mode);
            const infoHtml = AidUniteScheduleQuickModal.buildViewInfoHtml(
                { ui_schedule_kind: 'recruit', intent: 'recruit' },
                'recruit'
            );
            document.getElementById('sqm-body-confirm').innerHTML = `
                <div class="sqm-confirm-head">
                    <p class="sqm-confirm-lead">以下の内容で登録します。</p>
                </div>
                ${AidUniteScheduleQuickModal.buildRegisterConfirmDetailHtml(draft)}
                ${infoHtml}`;
        };

        document.getElementById('sqm-back-confirm')?.addEventListener('click', () => {
            AidUniteScheduleQuickModal.setRegisterStep(root, 'input', mode);
        });

        document.getElementById('sqm-submit-recruit-next')?.addEventListener('click', () => {
            if (mode === 'create') {
                showConfirm();
            } else {
                submit(collectDraft());
            }
        });

        const submit = (payload) => {
            if (state.submitting) return;
            state.submitting = true;
            const publishBtn = document.getElementById('sqm-submit-recruit-publish');
            const normalBtn = document.getElementById('sqm-submit-normal');
            const recruitBtn = document.getElementById('sqm-submit-recruit-next');
            [publishBtn, normalBtn, recruitBtn].forEach((b) => { if (b) b.disabled = true; });

            const api = isEdit
                ? AidUniteScheduleQuickModal.update(payload)
                : AidUniteScheduleQuickModal.register(payload);

            api.then((schedule) => {
                AidUniteScheduleQuickModal.close();
                if (schedule) AidUniteScheduleQuickModal.mergeScheduleIntoCalendar(schedule);
                if (typeof loadSchedules === 'function') loadSchedules();
                const isRecruit = payload && payload.ui_schedule_kind === 'recruit';
                if (isEdit) {
                    AidUniteScheduleQuickModal.toast('保存しました', 'success');
                } else if (AidUniteScheduleQuickModal.handleActivationRecruitPublishedFollowUp(isRecruit, false)) {
                    /* オンボーディングモーダルへ委譲 */
                } else {
                    AidUniteScheduleQuickModal.toast(isRecruit ? '試合募集を公開しました' : '登録しました', 'success');
                }
            }).catch((err) => {
                AidUniteScheduleQuickModal.toast(
                    AidUniteScheduleQuickModal.sanitizeErrorMessage(err && err.message),
                    'error'
                );
            }).finally(() => {
                state.submitting = false;
                [publishBtn, normalBtn, recruitBtn].forEach((b) => { if (b) b.disabled = false; });
            });
        };

        document.getElementById('sqm-submit-normal')?.addEventListener('click', () => {
            const draft = collectDraft();
            const tentativeError = AidUniteScheduleQuickModal.validateTentativeDraft(draft);
            if (tentativeError) {
                AidUniteScheduleQuickModal.toast(tentativeError, 'warning');
                return;
            }
            const timeError = AidUniteScheduleQuickModal.validateDraftTimes(draft);
            if (timeError) {
                AidUniteScheduleQuickModal.toast(timeError, 'warning');
                return;
            }
            submit(draft);
        });
        document.getElementById('sqm-submit-recruit-publish')?.addEventListener('click', () => {
            if (state.draft) submit(state.draft);
        });

        setTimeout(() => root.classList.add('show'), 10);
    }

    static openCardActions(schedule) {
        if (!schedule) return;
        AidUniteScheduleQuickModal.destroyImmediately();
        const sid = String(schedule.id || schedule.schedule_id || '');
        const actionMode = AidUniteScheduleQuickModal.resolveCardActionMode(schedule);
        const canDetail = schedule.can_show_match_detail === true
            || schedule.can_show_match_detail === '1'
            || schedule.can_show_match_detail === 1;
        const detailUrl = String(schedule.match_detail_url || '');
        const showDetailBtn = canDetail && detailUrl !== '';
        const showDeleteBtn = AidUniteScheduleQuickModal.canEdit() && actionMode !== 'match_confirmed';
        const showConfirmBtn = AidUniteScheduleQuickModal.canEdit()
            && AidUniteScheduleQuickModal.isTentativeSchedule(schedule);
        const badgeHtml = AidUniteScheduleQuickModal.buildBadgeHtml(schedule);
        const titleHtml = AidUniteScheduleQuickModal.buildConfirmTitleHtml(schedule);
        const detailHtml = AidUniteScheduleQuickModal.buildConfirmDetailHtml(schedule);
        const infoHtml = AidUniteScheduleQuickModal.buildViewInfoHtml(schedule, actionMode);
        const genderThemeClass = (() => {
            const g = AidUniteScheduleQuickModal.resolveGenderForSchedule(schedule);
            if (g === 'male') return ' sqm--theme-male';
            if (g === 'female') return ' sqm--theme-female';
            return '';
        })();

        const html = `
            <div id="aidunite-schedule-quick-modal" class="aidunite-schedule-quick-modal aidunite-schedule-quick-modal--actions${genderThemeClass}${AidUniteScheduleQuickModal.isPcLayout() ? ' sqm--pc' : ''}" role="dialog" aria-modal="true">
                <div class="sqm-overlay" data-sqm-close="1"></div>
                <div class="sqm-panel sqm-panel--confirm-view">
                    <header class="sqm-header sqm-header--view">
                        ${badgeHtml}
                        ${AidUniteScheduleQuickModal.closeButtonHtml()}
                    </header>
                    <div class="sqm-body sqm-body--view">
                        <div class="sqm-view-title-wrap">
                            <h4 class="sqm-view-title">${titleHtml}</h4>
                        </div>
                        ${detailHtml}
                        ${infoHtml}
                    </div>
                    <footer class="sqm-footer sqm-footer--stack sqm-footer--view">
                        ${showDetailBtn ? AidUniteScheduleQuickModal.viewActionButtonHtml({
                            id: 'sqm-action-detail',
                            variant: 'primary',
                            iconLeft: 'id_card',
                            iconRight: 'chevron_right',
                            label: '詳細を見る',
                            layout: 'split',
                        }) : ''}
                        ${showConfirmBtn ? AidUniteScheduleQuickModal.viewActionButtonHtml({
                            id: 'sqm-action-confirm',
                            variant: 'primary',
                            iconLeft: 'check_circle',
                            label: '確定する',
                        }) : ''}
                        ${AidUniteScheduleQuickModal.canEdit() ? AidUniteScheduleQuickModal.viewActionButtonHtml({
                            id: 'sqm-action-edit',
                            variant: 'secondary',
                            iconLeft: 'stylus',
                            label: '編集する',
                        }) : ''}
                        ${showDeleteBtn ? AidUniteScheduleQuickModal.viewActionButtonHtml({
                            id: 'sqm-action-delete',
                            variant: 'danger',
                            iconLeft: 'delete_forever',
                            label: '削除する',
                        }) : ''}
                        ${AidUniteScheduleQuickModal.viewActionButtonHtml({
                            variant: 'link',
                            iconLeft: 'close',
                            label: '閉じる',
                            close: true,
                        })}
                    </footer>
                </div>
            </div>`;

        document.body.insertAdjacentHTML('beforeend', html);
        const root = AidUniteScheduleQuickModal.getActiveModalRoot();
        if (!root) return;
        root.querySelectorAll('[data-sqm-close]').forEach((el) => {
            el.addEventListener('click', () => AidUniteScheduleQuickModal.close());
        });
        document.getElementById('sqm-action-detail')?.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const url = detailUrl;
            AidUniteScheduleQuickModal.close();
            if (url) {
                window.location.assign(url);
            }
        });
        document.getElementById('sqm-action-confirm')?.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            AidUniteScheduleQuickModal.close();
            if (typeof window.confirmSchedule === 'function') {
                window.confirmSchedule(sid);
            } else if (typeof window.confirmScheduleFromPopup === 'function') {
                window.confirmScheduleFromPopup(sid);
            }
        });
        document.getElementById('sqm-action-edit')?.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            AidUniteScheduleQuickModal.openEdit(schedule);
        });
        document.getElementById('sqm-action-delete')?.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const runDelete = () => {
                AidUniteScheduleQuickModal.close();
                AidUniteScheduleQuickModal.deleteScheduleById(sid);
            };
            if (typeof AidUniteScheduleModal !== 'undefined' && typeof AidUniteScheduleModal.confirmAction === 'function') {
                AidUniteScheduleModal.confirmAction({
                    message: 'このスケジュールを削除してもよろしいですか？',
                    onConfirm: runDelete
                });
            } else if (typeof showConfirmModal === 'function') {
                showConfirmModal({
                    title: '削除確認',
                    message: 'このスケジュールを削除してもよろしいですか？',
                    confirmLabel: '削除する',
                    cancelLabel: 'キャンセル',
                    confirmVariant: 'danger',
                    onConfirm: runDelete
                });
            } else {
                console.warn('[AidUnite] showConfirmModal unavailable');
            }
        });
        setTimeout(() => root.classList.add('show'), 10);
    }

    static parseRegisterApiBody(text) {
        const raw = String(text || '').trim();
        if (!raw) {
            return null;
        }
        try {
            return JSON.parse(raw);
        } catch (e) {
            const start = raw.indexOf('{');
            const end = raw.lastIndexOf('}');
            if (start >= 0 && end > start) {
                try {
                    return JSON.parse(raw.slice(start, end + 1));
                } catch (e2) {
                    return null;
                }
            }
            return null;
        }
    }

    static extractRegisterSuccess(data, payload) {
        if (!data || typeof data !== 'object') {
            return null;
        }
        const inner = data.data && typeof data.data === 'object' ? data.data : data;
        const scheduleId = parseInt(
            String(inner.schedule_id || inner.id || data.schedule_id || 0),
            10
        );
        const explicitSuccess = data.success === true || data.success === 1 || data.success === '1';
        if (!explicitSuccess && scheduleId < 1) {
            return null;
        }
        const schedule = inner.schedule && typeof inner.schedule === 'object'
            ? inner.schedule
            : null;
        if (schedule) {
            return schedule;
        }
        return {
            id: scheduleId,
            schedule_id: scheduleId,
            date: (payload && payload.date) ? payload.date : (inner.date || ''),
        };
    }

    static throwApiError(res, data, text, fallbackMsg) {
        const apiErrors = (data && data.data && data.data.errors)
            ? data.data.errors
            : ((data && data.errors) ? data.errors : null);
        let msg = (data && data.message) ? data.message : fallbackMsg;
        if (Array.isArray(apiErrors) && apiErrors.length) {
            msg = String(apiErrors[0]);
        } else if (apiErrors && typeof apiErrors === 'object') {
            const first = Object.values(apiErrors)[0];
            if (first) msg = String(first);
        }
        if (!res.ok && !data) {
            throw new Error(text);
        }
        throw new Error(msg);
    }

    static extractApiSchedule(data, payload) {
        if (!data || typeof data !== 'object') {
            return null;
        }
        if (data.schedule && typeof data.schedule === 'object') {
            return data.schedule;
        }
        return AidUniteScheduleQuickModal.extractRegisterSuccess(data, payload);
    }

    static async register(payload) {
        const cfg = AidUniteScheduleQuickModal.getConfig();
        const url = cfg.registerUrl || '/wp-json/aidunite/v1/register-schedule-v2';
        const nonce = cfg.nonce || (typeof wpApiSettings !== 'undefined' ? wpApiSettings.nonce : '');
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce,
            },
            body: JSON.stringify(payload),
        });
        const text = await res.text();
        const data = AidUniteScheduleQuickModal.parseRegisterApiBody(text);
        const schedule = AidUniteScheduleQuickModal.extractApiSchedule(data, payload);
        if (schedule) {
            return schedule;
        }
        AidUniteScheduleQuickModal.throwApiError(res, data, text, '登録に失敗しました');
    }

    static async update(payload) {
        const cfg = AidUniteScheduleQuickModal.getConfig();
        const url = cfg.updateUrl || '/wp-json/aidunite/v1/update-schedule-v2';
        const nonce = cfg.nonce || (typeof wpApiSettings !== 'undefined' ? wpApiSettings.nonce : '');
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce,
            },
            body: JSON.stringify(payload),
        });
        const text = await res.text();
        const data = AidUniteScheduleQuickModal.parseRegisterApiBody(text);
        const schedule = AidUniteScheduleQuickModal.extractApiSchedule(data, payload);
        if (schedule) {
            return schedule;
        }
        AidUniteScheduleQuickModal.throwApiError(res, data, text, '保存に失敗しました');
    }

    static deleteScheduleById(scheduleId) {
        const id = String(scheduleId || '').trim();
        if (!id) return;
        if (typeof window.deleteScheduleById === 'function') {
            window.deleteScheduleById(id);
            return;
        }
        const cfg = AidUniteScheduleQuickModal.getConfig();
        const nonce = cfg.nonce || (typeof wpApiSettings !== 'undefined' ? wpApiSettings.nonce : '');
        const deleteUrl = cfg.deleteUrl || '/wp-json/aidunite/v1/delete-schedule-v2';
        fetch(deleteUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce,
            },
            body: JSON.stringify({ post_id: id, nonce }),
        })
            .then((res) => res.json())
            .then((data) => {
                if (!data || !data.success) {
                    throw new Error((data && data.message) ? data.message : '削除に失敗しました');
                }
                AidUniteScheduleQuickModal.toast('削除しました', 'success');
                if (typeof loadSchedules === 'function') {
                    loadSchedules();
                } else {
                    window.location.reload();
                }
            })
            .catch((err) => {
                AidUniteScheduleQuickModal.toast(
                    AidUniteScheduleQuickModal.sanitizeErrorMessage(err && err.message),
                    'error'
                );
            });
    }

    static mergeScheduleIntoCalendar(schedule) {
        if (!schedule || !schedule.date) return;
        window.schedules = window.schedules || {};
        const key = String(schedule.date);
        if (!Array.isArray(window.schedules[key])) {
            window.schedules[key] = [];
        }
        const id = String(schedule.id || schedule.schedule_id || '');
        const idx = window.schedules[key].findIndex((s) => String(s.id || s.schedule_id) === id);
        if (idx >= 0) {
            window.schedules[key][idx] = schedule;
        } else {
            window.schedules[key].push(schedule);
        }
        if (typeof renderCalendar === 'function') {
            renderCalendar();
        }
        if (typeof applyScheduleFilter === 'function') {
            applyScheduleFilter();
        }
        if (typeof AidUniteScheduleMonthView !== 'undefined' && AidUniteScheduleMonthView.afterDataLoaded) {
            AidUniteScheduleMonthView.afterDataLoaded();
        }
    }

    static toast(message, type) {
        if (typeof showToastNotification === 'function') {
            showToastNotification(message, type || 'info');
            return;
        }
        console.warn('[AidUnite]', type || 'info', message);
    }
}

window.AidUniteScheduleQuickModal = AidUniteScheduleQuickModal;

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-aidunite-recruit-modal]').forEach(function (trigger) {
        trigger.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            if (typeof AidUniteScheduleQuickModal === 'undefined'
                || typeof AidUniteScheduleQuickModal.openRegisterRecruit !== 'function') {
                return;
            }
            if (!AidUniteScheduleQuickModal.canEdit()) {
                return;
            }
            AidUniteScheduleQuickModal.openRegisterRecruit();
        });
    });
});
