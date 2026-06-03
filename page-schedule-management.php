<?php
/**
 * Template Name: スケジュール管理
 *
 * スケジュールの管理・表示を行うページ
 * PC版：2カラムレイアウト、スマホ版：1カラムレイアウト
 */

// 統一認証・権限チェック
$auth_result = AidUniteAuthMiddleware::require_auth(true);
if (!$auth_result->is_valid()) {
    // リダイレクトは自動で実行される
    return;
}

// 権限チェック - チーム管理者のみ編集可能
$can_edit_schedule = false;
$user_id = $auth_result->user_id;

// 統一認証・権限チェック（管理者の場合）
require_once get_template_directory() . '/functions/common/auth-middleware.php';
$admin_result = AidUniteAuthMiddleware::require_admin(false);
if ($admin_result->is_valid()) {
    $can_edit_schedule = true;
} else {
    // カスタムロールのチェック
    $user_role = aidunite_get_user_role($user_id);
    if ($user_role === 'team_leader') {
        $can_edit_schedule = true;
    }
}

get_header(); ?>

<?php
// スケジュール編集ページのURLを取得
$schedule_edit_url = esc_url(home_url('/schedule-edit'));

// 共通カレンダーテンプレートを読み込み
require_once get_template_directory() . '/functions/schedule/schedule-calendar-common.php';

?>

<script>
// スケジュール編集ページのURL
const scheduleEditUrl = '<?php echo $schedule_edit_url; ?>';

// スケジュール編集ページを開く（ページ読み込み時に即座に定義）
function openScheduleEdit(dateString = null) {
    try {
        let url = scheduleEditUrl;

        if (dateString) {
            const date = new Date(dateString);
            const formattedDate = date.toISOString().split('T')[0];
            url += `?date=${formattedDate}`;
        }

        window.location.href = url;
    } catch (error) {
        console.error('openScheduleEdit エラー:', error);
        if (typeof showToastNotification !== 'undefined') {
            showToastNotification('ページ遷移でエラーが発生しました: ' + error.message, 'error');
        } else {
            alert('ページ遷移でエラーが発生しました: ' + error.message);
        }
    }
}

// グローバルスコープでも利用可能にする
window.openScheduleEdit = openScheduleEdit;
</script>

<?php
if (function_exists('aidunite_web_app_page_shell_open')) {
    aidunite_web_app_page_shell_open([
        'page_class' => 'page-schedule-management',
        'title' => 'スケジュール管理',
        'subtitle' => 'チームのスケジュールを管理し、予定を確認できます。',
        'content_class' => 'ainy-webapp-content--schedule-management',
    ]);
} else {
    echo '<div class="team-dashboard-container page-schedule-management"><div class="dashboard-header"><h1>スケジュール管理</h1><p>チームのスケジュールを管理し、予定を確認できます。</p></div>';
}
?>

    <section class="dashboard-section dashboard-section--flush">
        <!-- スケジュール表示エリア（ヘッダー直下・カード・main-content-area なし） -->
        <div class="schedule-display-area">
            <!-- 絞り込みフィルター（1段目: チップ＋右端に新規登録、2段目: 説明文） -->
            <div class="schedule-filter-bar">
                <div class="schedule-filter-row">
                    <div class="filter-chips" id="schedule-filter-chips">
                        <button type="button" class="filter-chip active" data-filter="all">すべて</button>
                        <button type="button" class="filter-chip" data-filter="confirmed">確定の予定</button>
                        <button type="button" class="filter-chip" data-filter="recruit">試合を募集</button>
                        <button type="button" class="filter-chip" data-filter="tentative">仮押さえ</button>
                    </div>
                    <?php if ($can_edit_schedule): ?>
                    <a href="<?php echo esc_url($schedule_edit_url); ?>" id="schedule-add-btn" class="ainy-header-icon-link" aria-label="新規登録" data-testid="schedule-new-create">
                        <svg xmlns="http://www.w3.org/2000/svg" height="24" width="24" viewBox="0 -960 960 960" fill="#0000F5" aria-hidden="true"><path d="M440-440H200v-80h240v-240h80v240h240v80H520v240h-80v-240Z"/></svg>
                    </a>
                    <?php endif; ?>
                </div>
                <?php
                if (function_exists('output_schedule_view_and_team_filters')) {
                    output_schedule_view_and_team_filters();
                }
                ?>
                <p class="schedule-filter-legend">登録時の目的と同じ区分です（確定の予定／試合を募集／仮押さえ）</p>
            </div>

            <!-- カレンダー表示 -->
            <?php output_schedule_calendar_html(); ?>
        </div>
    </section>

<?php
if (function_exists('aidunite_web_app_page_shell_close')) {
    aidunite_web_app_page_shell_close();
} else {
    echo '</div>';
}
?>

<!-- 削除確認モーダル（リストビューからの削除用） -->
<div id="delete-confirm-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>削除確認</h3>
            <span class="close" onclick="closeDeleteConfirmModal()">&times;</span>
        </div>
        <div class="modal-body">
            <p>このスケジュールを削除してもよろしいですか？</p>
            <p class="schedule-title" id="delete-schedule-title"></p>
        </div>
        <div class="modal-footer">
            <button class="dashboard-btn btn-danger" onclick="confirmDeleteSchedule()">削除</button>
            <button class="dashboard-btn btn-secondary" onclick="closeDeleteConfirmModal()">キャンセル</button>
        </div>
    </div>
</div>

<?php
// 統一カレンダー（インラインスクリプトより前に読み込み・fetch 完了後に1回だけ描画）
output_schedule_calendar_js();
?>
<script>
// スケジュール管理JavaScript
window.currentDate = window.currentDate || new Date();

// ローカル（JST）で YYYY-MM-DD を作る
// formatDateLocal関数は js/common/date-utils.js の AidUniteDateUtils.formatDateLocal() を使用
// 後方互換性のため、グローバル関数として利用可能
let selectedDate = null;
let currentView = 'calendar';

// bfcache（戻る/進む）で復元された場合は必ず再取得して最新を表示（キャッシュなし仕様のため再取得のみ）
window.addEventListener('pageshow', function(event) {
    if (event.persisted && typeof loadSchedules === 'function') {
        loadSchedules();
    }
});

// ページ読み込み時の初期化
document.addEventListener('DOMContentLoaded', function() {
    try {
        // URLパラメータにrefreshがある場合、キャッシュをクリアして強制的に再取得
        const urlParams = new URLSearchParams(window.location.search);
        const hasRefresh = urlParams.has('refresh');

        if (hasRefresh) {
            console.log('🔄 編集完了後のリフレッシュ: 再取得します');
        }

        // loadTodaySchedule(); // 今日の予定セクションは削除済み
        // setupViewToggle(); // 表示切り替え機能は削除済み
        loadSchedules(); // カレンダーのスケジュールを読み込み（キャッシュなし・常にAPIで取得）

        // refreshパラメータがある場合、読み込み後にURLから削除（履歴に残さない）
        if (hasRefresh) {
            setTimeout(() => {
                const newUrl = window.location.pathname;
                window.history.replaceState({}, document.title, newUrl);
            }, 100);
        }
        // グローバル公開（共通ユーティリティをそのまま利用）
        window.showScheduleDetail = function(scheduleId){
            if (typeof AidUniteScheduleModal !== 'undefined') {
                AidUniteScheduleModal.showDetail(scheduleId, { mode: 'popup' });
            } else if (typeof window.showScheduleDetailPopup === 'function') {
                // 後方互換

                const schedule = (window.schedules && Object.values(window.schedules).flat().find(s=>String(s.id)===String(scheduleId))) || null;
                if (schedule) window.showScheduleDetailPopup(schedule);
            } else {
                console.error('AidUniteScheduleModal が利用できません');
            }
        };
        console.log('✅ スケジュール管理ページ初期化完了');
    } catch (error) {
        console.error('初期化エラー:', error);
    }
});

// カレンダー表示は共通テンプレートから読み込み済み

// スケジュール詳細表示（共通テンプレから呼ばれる）
// showScheduleDetail関数は js/common/schedule-modal.js の AidUniteScheduleModal.showDetail() を使用
// 後方互換性のため、グローバル関数として利用可能

// createScheduleDetailModal関数は js/common/schedule-modal.js の AidUniteScheduleModal.createModal() を使用
// 後方互換性のため、グローバル関数として利用可能

// 全スケジュール表示は廃止（共通ポップアップに統一）
function showAllSchedules(dateString) {
    const scheduleList = schedules[dateString];
    if (!scheduleList || scheduleList.length === 0) return;
    // 一覧モーダルは使わず、最初のスケジュールを直接開く
    const first = scheduleList[0];
    if (first && typeof AidUniteScheduleModal !== 'undefined') {
        AidUniteScheduleModal.showDetail(first.id, { mode: 'popup' });
    }
}

// 一覧モーダル関連の生成コードは廃止（共通ポップアップに統一）

// モーダルを閉じる
// closeScheduleDetail関数は js/common/schedule-modal.js の AidUniteScheduleModal.closeModal() を使用
// 後方互換性のため、グローバル関数として利用可能

// editSchedule / deleteSchedule は本ファイル後半で定義（モーダル経由の ID 引数に対応した版）

// スケジュール確定（仮押さえから確定に変更）: 中央トーストモーダルを表示
function confirmSchedule(scheduleId) {
    showTentativeConfirmToast(scheduleId);
}

// 仮→確定用トーストモーダル（中央）を表示
function showTentativeConfirmToast(scheduleId) {
    try {
        const schedule = findScheduleByIdInGlobal(scheduleId);
        if (!schedule) {
            if (typeof showToastNotification !== 'undefined') {
                showToastNotification('スケジュール情報が取得できませんでした。', 'error');
            } else {
                alert('スケジュール情報が取得できませんでした。');
            }
            return;
        }

        // 既存のトーストを削除
        const existing = document.getElementById('tentative-confirm-toast');
        if (existing) existing.remove();

        const overlay = document.createElement('div');
        overlay.id = 'tentative-confirm-toast';
        overlay.className = 'tentative-confirm-toast-overlay';
        overlay.innerHTML = `
            <div class="tentative-confirm-toast">
                <div class="toast-header">
                    <h3>仮スケジュールを確定</h3>
                    <button type="button" class="toast-close" aria-label="閉じる">&times;</button>
                </div>
                <div class="toast-body">
                    <div class="toast-row">
                        <label>日付</label>
                        <div>${schedule.date || '-'}</div>
                    </div>
                    <div class="toast-row">
                        <label>時間</label>
                        <div class="toast-time-inputs">
                            <input type="time" id="toast-start-time" value="${schedule.start_time || ''}">
                            <span>〜</span>
                            <input type="time" id="toast-end-time" value="${schedule.end_time || ''}">
                        </div>
                    </div>
                    <div class="toast-row">
                        <label>種別</label>
                        <select id="toast-type">
                            <option value="練習">練習</option>
                            <option value="練習試合">練習試合</option>
                            <option value="公式試合">公式試合</option>
                            <option value="合同練習">合同練習</option>
                            <option value="合宿">合宿</option>
                            <option value="遠征">遠征</option>
                            <option value="休み">休み</option>
                            <option value="イベント">イベント</option>
                        </select>
                    </div>
                    <div class="toast-row">
                        <label>性別</label>
                        <select id="toast-gender">
                            <option value="">指定なし</option>
                            <option value="male">男子</option>
                            <option value="female">女子</option>
                        </select>
                    </div>
                    <div class="toast-row">
                        <label>会場条件</label>
                        <select id="toast-venue-condition">
                            <option value="">未定</option>
                            <option value="home">ホーム</option>
                            <option value="away">アウェイ</option>
                            <option value="either">どちらでも</option>
                        </select>
                    </div>
                    <div class="toast-row">
                        <label>会場名</label>
                        <input type="text" id="toast-venue-name" value="${schedule.venue_name || ''}">
                    </div>
                    <div class="toast-row">
                        <label>メモ</label>
                        <textarea id="toast-memo" rows="2">${schedule.quick_memo || schedule.memo || ''}</textarea>
                    </div>
                </div>
                <div class="toast-footer">
                    <button type="button" class="dashboard-btn btn-secondary" id="toast-cancel-btn">キャンセル</button>
                    <button type="button" class="dashboard-btn btn-primary" id="toast-confirm-btn">この内容で確定する</button>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);

        // 初期値のセット（種別・性別・会場条件）
        const typeSelect = document.getElementById('toast-type');
        if (typeSelect && schedule.type) {
            const baseType = String(schedule.type).replace('（仮）', '');
            const opt = Array.from(typeSelect.options).find(o => o.value === baseType);
            if (opt) typeSelect.value = baseType;
        }
        const genderSelect = document.getElementById('toast-gender');
        if (genderSelect && schedule.gender) {
            genderSelect.value = schedule.gender;
        }
        const venueSelect = document.getElementById('toast-venue-condition');
        if (venueSelect && schedule.venue_condition) {
            venueSelect.value = schedule.venue_condition;
        }

        const closeToast = () => {
            overlay.classList.remove('show');
            setTimeout(() => overlay.remove(), 200);
        };

        overlay.querySelector('.toast-close').addEventListener('click', closeToast);
        document.getElementById('toast-cancel-btn').addEventListener('click', closeToast);

        document.getElementById('toast-confirm-btn').addEventListener('click', function () {
            confirmTentativeSchedule(scheduleId, {
                date: schedule.date,
                start_time: document.getElementById('toast-start-time').value,
                end_time: document.getElementById('toast-end-time').value,
                type: document.getElementById('toast-type').value,
                gender: document.getElementById('toast-gender').value,
                venue_condition: document.getElementById('toast-venue-condition').value,
                venue_name: document.getElementById('toast-venue-name').value,
                memo: document.getElementById('toast-memo').value
            }, closeToast);
        });

        // アニメーション表示
        setTimeout(() => overlay.classList.add('show'), 10);
    } catch (e) {
        console.error('showTentativeConfirmToast error:', e);
        if (typeof showToastNotification !== 'undefined') {
            showToastNotification('確定用のポップアップ表示中にエラーが発生しました。', 'error');
        } else {
            alert('確定用のポップアップ表示中にエラーが発生しました。');
        }
    }
}

// グローバルスケジュールオブジェクトからIDで検索
function findScheduleByIdInGlobal(scheduleId) {
    if (!window.schedules) return null;
    for (const dateKey in window.schedules) {
        const dayList = window.schedules[dateKey];
        if (!Array.isArray(dayList)) continue;
        const found = dayList.find(s => String(s.id) === String(scheduleId));
        if (found) {
            if (!found.date) found.date = dateKey;
            return found;
        }
    }
    return null;
}

// 仮スケジュールをREST API経由で確定
function confirmTentativeSchedule(scheduleId, payload, onDone) {
    const nonce = (typeof wpApiSettings !== 'undefined') ? wpApiSettings.nonce : '';
    const body = Object.assign({}, payload, {
        post_id: scheduleId,
        intent: 'confirmed',
        certainty: 'firm'
    });

    fetch('<?php echo esc_url(rest_url('aidunite/v1/update-schedule-v2')); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': nonce
        },
        body: JSON.stringify(body)
    })
    .then(res => res.json())
    .then(data => {
        if (data && data.success) {
            if (typeof onDone === 'function') onDone();
            showCenterToast('スケジュールを確定しました');
            // 再読み込み
            if (typeof loadSchedules === 'function') {
                loadSchedules();
            } else {
                window.location.reload();
            }
        } else {
            console.error('confirmTentativeSchedule error:', data);
            if (typeof showToastNotification !== 'undefined') {
                showToastNotification('スケジュールの確定に失敗しました。', 'error');
            } else {
                alert('スケジュールの確定に失敗しました。');
            }
        }
    })
    .catch(err => {
        console.error('confirmTentativeSchedule fetch error:', err);
        if (typeof showToastNotification !== 'undefined') {
            showToastNotification('スケジュールの確定中にエラーが発生しました。', 'error');
        } else {
            alert('スケジュールの確定中にエラーが発生しました。');
        }
    });
}

// 中央トースト通知
function showCenterToast(message) {
    const existing = document.getElementById('center-toast');
    if (existing) existing.remove();
    const el = document.createElement('div');
    el.id = 'center-toast';
    el.className = 'center-toast';
    el.textContent = message;
    document.body.appendChild(el);
    setTimeout(() => el.classList.add('show'), 10);
    setTimeout(() => {
        el.classList.remove('show');
        setTimeout(() => el.remove(), 200);
    }, 2000);
}

// 前月
function previousMonth() {
    currentDate.setMonth(currentDate.getMonth() - 1);
    loadSchedules();
}

// 次月
function nextMonth() {
    currentDate.setMonth(currentDate.getMonth() + 1);
    loadSchedules();
}

// スケジュール読み込み（最適化版）
// モバイルでも確実に表示するため「先に42マスを描画してからAPI取得」＋タイムアウトで読み込み中で止まらない
function loadSchedules() {
    console.log('📅 スケジュール読み込み開始');

    const year = currentDate.getFullYear();
    const monthIdx = currentDate.getMonth();
    const firstDayOfMonth = new Date(year, monthIdx, 1);
    const calendarStart = new Date(firstDayOfMonth);
    calendarStart.setDate(firstDayOfMonth.getDate() - firstDayOfMonth.getDay());
    const calendarEnd = new Date(calendarStart);
    calendarEnd.setDate(calendarStart.getDate() + 41);
    const start = (typeof AidUniteDateUtils !== 'undefined' && AidUniteDateUtils.formatDateLocal)
        ? AidUniteDateUtils.formatDateLocal(calendarStart)
        : (calendarStart.getFullYear() + '-' + String(calendarStart.getMonth() + 1).padStart(2, '0') + '-' + String(calendarStart.getDate()).padStart(2, '0'));
    const end = (typeof AidUniteDateUtils !== 'undefined' && AidUniteDateUtils.formatDateLocal)
        ? AidUniteDateUtils.formatDateLocal(calendarEnd)
        : (calendarEnd.getFullYear() + '-' + String(calendarEnd.getMonth() + 1).padStart(2, '0') + '-' + String(calendarEnd.getDate()).padStart(2, '0'));

    console.log('📅 取得期間（カレンダー表示42日分）:', { start, end });

    // 先に空グリッドを1回描画（モバイルで即日付表示）、fetch 後にデータ付きで再描画
    window.schedules = window.schedules || {};
    if (typeof renderCalendar === 'function') renderCalendar();
    showCalendarLoadingOverlay();

    const FETCH_TIMEOUT_MS = 12000;
    const timeoutPromise = new Promise(function(_, reject) {
        setTimeout(function() { reject(new Error('スケジュール取得がタイムアウトしました')); }, FETCH_TIMEOUT_MS);
    });

    function finishLoad() {
        hideCalendarLoadingOverlay();
        if (typeof renderCalendar === 'function') {
            renderCalendar();
            if (typeof applyScheduleFilter === 'function') applyScheduleFilter();
        }
        if (typeof AidUniteScheduleMonthView !== 'undefined' && AidUniteScheduleMonthView.afterDataLoaded) {
            AidUniteScheduleMonthView.afterDataLoaded();
        }
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('refresh')) {
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    }

    Promise.race([
        fetchMonthSchedules(start, end),
        timeoutPromise
    ])
        .then(function() {
            console.log('📅 データ取得完了、カレンダー描画');
            finishLoad();
        })
        .catch(function(error) {
            console.warn('⚠️ 取得失敗またはタイムアウト、グリッドは表示のまま:', error);
            window.schedules = window.schedules || {};
            finishLoad();
            // 月単位失敗時は日別フォールバックをバックグラウンドで試行（結果は次回操作で反映）
            fetchMonthByDaily(start, end).then(function() {
                if (typeof renderCalendar === 'function') {
                    renderCalendar();
                    if (typeof applyScheduleFilter === 'function') applyScheduleFilter();
                }
            }).catch(function() {});
        });
}

// フィルタ状態
let activeScheduleFilters = new Set(['all']);

// カードにフィルタを適用
function applyScheduleFilter() {
    if (!window.schedules) return;
    const filters = activeScheduleFilters.has('all') ? new Set(['all']) : new Set(activeScheduleFilters);

    Object.keys(window.schedules).forEach(dateKey => {
        const list = window.schedules[dateKey];
        if (!Array.isArray(list)) return;
        const dayEl = document.querySelector(`.se-day[data-date="${dateKey}"]`);
        if (!dayEl) return;
        const cards = dayEl.querySelectorAll('.schedule-card');
        cards.forEach((card, idx) => {
            const sch = list[idx];
            if (!sch) return;
            let visible = true;
            if (typeof AidUniteScheduleUtils !== 'undefined' && typeof AidUniteScheduleUtils.matchesScheduleFilter === 'function') {
                visible = AidUniteScheduleUtils.matchesScheduleFilter(sch, filters);
            } else if (typeof matchesScheduleFilter === 'function') {
                visible = matchesScheduleFilter(sch, filters);
            } else if (!filters.has('all')) {
                const intent = String(sch.intent || '').toLowerCase();
                visible = intent !== '' && filters.has(intent);
            }
            const cfg = window.AIDUNITE_SCHEDULE_VIEW || {};
            const tf = cfg.activeTeamFilter;
            if (visible && tf && tf !== 'all' && sch.team_id) {
                visible = String(sch.team_id) === String(tf);
            }
            card.style.display = visible ? '' : 'none';
        });
    });
}

// フィルタチップの初期化
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('schedule-filter-chips');
    if (!container) return;
    container.addEventListener('click', function(e) {
        const btn = e.target.closest('.filter-chip');
        if (!btn) return;
        const value = btn.getAttribute('data-filter');
        if (!value) return;

        if (value === 'all') {
            activeScheduleFilters = new Set(['all']);
            Array.from(container.querySelectorAll('.filter-chip')).forEach(el => {
                el.classList.toggle('active', el.getAttribute('data-filter') === 'all');
            });
        } else {
            if (activeScheduleFilters.has('all')) {
                activeScheduleFilters = new Set();
            }
            if (activeScheduleFilters.has(value)) {
                activeScheduleFilters.delete(value);
            } else {
                activeScheduleFilters.add(value);
            }
            if (activeScheduleFilters.size === 0) {
                activeScheduleFilters.add('all');
            }
            Array.from(container.querySelectorAll('.filter-chip')).forEach(el => {
                const key = el.getAttribute('data-filter');
                el.classList.toggle('active', activeScheduleFilters.has(key));
            });
        }
        applyScheduleFilter();
        if (typeof AidUniteScheduleMonthView !== 'undefined' && AidUniteScheduleMonthView.afterDataLoaded) {
            AidUniteScheduleMonthView.afterDataLoaded();
        }
    });
});

// カレンダーローディング表示（グリッドを置き換えず、オーバーレイで表示）
function showCalendarLoadingOverlay() {
    hideCalendarLoadingOverlay();
    const header = document.querySelector('.calendar-header');
    if (header) {
        const el = document.createElement('div');
        el.id = 'calendar-loading-overlay';
        el.className = 'calendar-loading-overlay';
        el.setAttribute('aria-live', 'polite');
        el.innerHTML = '<span class="calendar-loading-spinner" aria-hidden="true"></span><span>読み込み中...</span>';
        header.appendChild(el);
    }
}

function hideCalendarLoadingOverlay() {
    const el = document.getElementById('calendar-loading-overlay');
    if (el && el.parentNode) el.parentNode.removeChild(el);
}

// 後方互換（旧 showCalendarLoading はグリッドを潰さないオーバーレイに変更済みのため未使用）
function showCalendarLoading() {
    showCalendarLoadingOverlay();
}
function hideCalendarLoading() {
    hideCalendarLoadingOverlay();
}

// 月単位での一括取得（キャッシュなし・共通ロジック使用。常に最新を取得）
function fetchMonthSchedules(start, end) {
    console.log('📅 月単位一括取得開始:', { start, end });
    if (typeof AidUniteScheduleLoader === 'undefined') {
        return Promise.reject(new Error('AidUniteScheduleLoader が読み込まれていません'));
    }
    const fetchOpts = (typeof AidUniteScheduleMonthView !== 'undefined' && AidUniteScheduleMonthView.getFetchOptions)
        ? AidUniteScheduleMonthView.getFetchOptions()
        : (window.AIDUNITE_SCHEDULE_VIEW ? { scope: window.AIDUNITE_SCHEDULE_VIEW.fetchScope || 'operating' } : {});
    return AidUniteScheduleLoader.fetchMonthSchedulesForCalendar(start, end, fetchOpts)
        .then(() => {
            console.log('✅ 月単位取得完了:', Object.keys(window.schedules).length, '日分のデータ');
        })
        .catch(error => {
            console.error('❌ 月単位取得エラー:', error);
            throw error;
        });
}

// 最終フォールバック: 当月の全日について日別APIで取得し集約
function fetchMonthByDaily(start, end) {
    // 'YYYY-MM-DD' をローカル日付として安全に生成
    const parseYmd = (s) => {
        const [y, m, d] = s.split('-').map(n => parseInt(n, 10));
        return new Date(y, m - 1, d);
    };
    const dates = [];
    let d = parseYmd(start);
    const last = parseYmd(end);
    while (d <= last) {
        dates.push((typeof AidUniteDateUtils !== 'undefined' && AidUniteDateUtils.formatDateLocal)
            ? AidUniteDateUtils.formatDateLocal(d)
            : formatDateLocal(d));
        d.setDate(d.getDate() + 1);
    }
    window.schedules = {};
    return Promise.all(dates.map(ds => {
        return fetch('/wp-json/aidunite/v1/get-schedules-by-date', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': (typeof wpApiSettings !== 'undefined' ? wpApiSettings.nonce : '')
            },
            body: JSON.stringify({ date: ds })
        })
        .then(res => res.json())
        .then(payload => {
            const arr = Array.isArray(payload) ? payload : (payload && payload.data ? payload.data : []);
            if (Array.isArray(arr) && arr.length) {
                window.schedules[ds] = arr;
            }
        })
        .catch(() => {});
    }));
}

// スケジュールモーダルを閉じる（旧モーダル削除後は no-op。後方互換のため残す）
function closeScheduleModal() {
    const modal = document.getElementById('schedule-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// スケジュールの編集（削除確認モーダル等からの編集用）
function editSchedule(scheduleId) {
    const id = (scheduleId != null && scheduleId !== '') ? String(scheduleId) : '';
    // ポップアップからID指定で呼ばれた場合は直接遷移
    if (id) {
        window.location.href = `<?php echo get_permalink(get_page_by_path('schedule-edit')); ?>?post_id=${encodeURIComponent(id)}`;
        return;
    }

    if (selectedDate) {
        const formattedDate = selectedDate.toISOString().split('T')[0];

        // 指定日のスケジュールを取得して編集ページにリダイレクト
        fetch(`/wp-json/aidunite/v1/get-schedules-by-date`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': wpApiSettings.nonce
            },
            body: JSON.stringify({ date: formattedDate })
        })
        .then(response => response.json())
        .then(schedules => {
            if (Array.isArray(schedules) && schedules.length > 0) {
                // 最初のスケジュールを編集
                const schedule = schedules[0];
                window.location.href = `<?php echo get_permalink(get_page_by_path('schedule-edit')); ?>?post_id=${schedule.id}`;
            } else {
                // スケジュールがない場合は新規作成
                openScheduleEdit(selectedDate.toISOString());
            }
        })
        .catch(error => {
            console.error('スケジュール取得エラー:', error);
            // エラーの場合は新規作成
            openScheduleEdit(selectedDate.toISOString());
        });
    }
    closeScheduleModal();
}

// スケジュールの削除
function deleteSchedule(scheduleId) {
    const id = (scheduleId != null && scheduleId !== '') ? String(scheduleId) : '';
    // ポップアップからID指定で呼ばれた場合は即時削除
    if (id) {
        if (confirm('このスケジュールを削除しますか？')) {
            deleteScheduleById(id);
        }
        return;
    }

    if (selectedDate) {
        showDeleteConfirmModal();
    }
}

function deleteScheduleById(scheduleId) {
    const nonce = (typeof wpApiSettings !== 'undefined') ? wpApiSettings.nonce : '';
    fetch('<?php echo esc_url(rest_url('aidunite/v1/delete-schedule-v2')); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': nonce
        },
        body: JSON.stringify({
            post_id: scheduleId,
            nonce: nonce
        })
    })
    .then(res => res.json())
    .then(data => {
        if (!data || !data.success) {
            throw new Error((data && data.message) ? data.message : '削除に失敗しました');
        }
        if (typeof AidUniteScheduleModal !== 'undefined') {
            AidUniteScheduleModal.closeAll();
        }
        if (typeof showToastNotification !== 'undefined') {
            showToastNotification('スケジュールを削除しました', 'success');
        } else {
            alert('スケジュールを削除しました');
        }
        if (typeof loadSchedules === 'function') {
            loadSchedules();
        } else {
            window.location.reload();
        }
    })
    .catch(error => {
        console.error('スケジュール削除エラー:', error);
        if (typeof showToastNotification !== 'undefined') {
            showToastNotification('スケジュールの削除に失敗しました', 'error');
        } else {
            alert('スケジュールの削除に失敗しました');
        }
    });
}

// 共通モーダル（AidUniteScheduleModal）からの呼び出し口
function editScheduleFromPopup(scheduleId) {
    editSchedule(scheduleId);
}

function deleteScheduleFromPopup(scheduleId) {
    deleteSchedule(scheduleId);
}

// 共通モーダルから仮→確定（トースト編集UIを開く）
function confirmScheduleFromPopup(scheduleId) {
    showTentativeConfirmToast(scheduleId);
}

// 削除確認モーダルを表示
function showDeleteConfirmModal() {
    const modal = document.getElementById('delete-confirm-modal');
    const scheduleTitle = document.getElementById('delete-schedule-title');

    if (!modal || !scheduleTitle) return;

    scheduleTitle.textContent = `${selectedDate.getMonth() + 1}月${selectedDate.getDate()}日のスケジュール`;
    modal.style.display = 'block';
}

// 削除確認モーダルを閉じる
function closeDeleteConfirmModal() {
    const modal = document.getElementById('delete-confirm-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// スケジュールの削除を確認
function confirmDeleteSchedule() {
    if (!selectedDate) return;

    const formattedDate = selectedDate.toISOString().split('T')[0];

    // 指定日のスケジュールを取得
    fetch(`/wp-json/aidunite/v1/get-schedules-by-date`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': wpApiSettings.nonce
        },
        body: JSON.stringify({ date: formattedDate })
    })
    .then(response => response.json())
    .then(schedules => {
        if (Array.isArray(schedules) && schedules.length > 0) {
            // 各スケジュールを削除
            const deletePromises = schedules.map(schedule => {
                return fetch(`/wp-json/wp/v2/schedule/${schedule.id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-WP-Nonce': wpApiSettings.nonce
                    }
                });
            });

            return Promise.all(deletePromises);
        }
        throw new Error('削除するスケジュールが見つかりません');
    })
    .then(() => {
        console.log('スケジュールを削除しました:', selectedDate);
        if (typeof showToastNotification !== 'undefined') {
            showToastNotification('スケジュールを削除しました', 'success');
        } else {
            alert('スケジュールを削除しました');
        }

        closeDeleteConfirmModal();
        closeScheduleModal();
        renderCalendar(); // カレンダーを再描画
        // loadTodaySchedule(); // 今日の予定セクションは削除済み
    })
    .catch(error => {
        console.error('スケジュール削除エラー:', error);
        if (typeof showToastNotification !== 'undefined') {
            showToastNotification('スケジュールの削除に失敗しました', 'error');
        } else {
            alert('スケジュールの削除に失敗しました');
        }
    });
}

// モーダルからスケジュールを削除
function deleteScheduleFromModal() {
    showDeleteConfirmModal();
}

// 前月・次月の関数はテンプレートファイルに移行

// 今日のスケジュールを読み込み
function loadTodaySchedule() {
    const content = document.getElementById('today-schedule-content');
    if (!content) {
        console.warn('⚠️ 今日のスケジュール表示要素が見つかりません');
        return;
    }

    const today = new Date();
    const formattedDate = today.toISOString().split('T')[0];

    console.log('🔍 今日のスケジュール読み込み開始:', { today: today.toISOString(), formattedDate });

    // REST APIから今日のスケジュールを取得
    fetch(`/wp-json/aidunite/v1/get-schedules-by-date`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': wpApiSettings.nonce
        },
        body: JSON.stringify({ date: formattedDate })
    })
    .then(response => {
        console.log('📡 今日のスケジュールAPI レスポンス:', response);
        return response.json();
    })
    .then(schedules => {
        console.log('📋 今日のスケジュール:', schedules);

        if (Array.isArray(schedules) && schedules.length > 0) {
            let scheduleHTML = '';
            schedules.forEach(schedule => {
                scheduleHTML += `
                    <div class="today-schedule-item">
                        <div class="schedule-item-time">
                            <span class="time-icon">🕐</span>
                            <span class="time-text">${schedule.start_time} - ${schedule.end_time}</span>
                        </div>
                        <div class="schedule-item-content">
                            <div class="schedule-item-title">${schedule.type}</div>
                            <div class="schedule-item-venue">${schedule.place}</div>
                            <span class="schedule-item-type">${schedule.type}</span>
                        </div>
                    </div>
                `;
            });
            content.innerHTML = scheduleHTML;
            console.log('✅ 今日のスケジュール表示完了:', scheduleHTML);
        } else {
            content.innerHTML = `
                <div class="no-schedule">
                    <p>今日の予定はありません</p>
                </div>
            `;
            console.log('📝 今日の予定なし');
        }
    })
    .catch(error => {
        console.error('❌ 今日のスケジュール取得エラー:', error);
        content.innerHTML = `
            <div class="no-schedule">
                <p>今日の予定はありません</p>
            </div>
        `;
    });
}

// ビューの切り替え設定
function setupViewToggle() {
    const toggleButtons = document.querySelectorAll('.toggle-buttons .dashboard-btn');
    const calendarContainer = document.querySelector('.schedule-calendar-container');
    const listView = document.getElementById('list-view');
    const detailedListView = document.getElementById('detailed-list-view');

    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const view = this.dataset.view;

            // ボタンのアクティブ状態を更新
            toggleButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');

            // ビューを切り替え
            if (view === 'calendar') {
                calendarContainer.style.display = 'block';

                listView.classList.remove('active');
                detailedListView.classList.remove('active');
                currentView = 'calendar';
            } else if (view === 'list') {
                listView.classList.add('active');
                calendarContainer.style.display = 'none';
                detailedListView.classList.remove('active');
                currentView = 'list';
                loadScheduleList();
            } else if (view === 'detailed-list') {
                detailedListView.classList.add('active');
                calendarContainer.style.display = 'none';
                listView.classList.remove('active');
                currentView = 'detailed-list';
                loadDetailedScheduleList();
            }
        });
    });
}

// スケジュールリストを読み込み（カード表示）
function loadScheduleList() {
    const listContainer = document.getElementById('schedule-list');
    if (!listContainer) {
        console.warn('⚠️ スケジュールリスト表示要素が見つかりません');
        return;
    }

    console.log('🔍 スケジュールリスト読み込み開始');

    // REST APIからスケジュールリストを取得
    fetch('/wp-json/aidunite/v1/get-user-schedules', {
        method: 'GET',
        headers: {
            'X-WP-Nonce': wpApiSettings.nonce
        }
    })
    .then(response => response.json())
    .then(data => {
        console.log('📋 取得したスケジュールリスト:', data);

        if (data.success && data.data && data.data.length > 0) {
            let listHTML = '<div class="schedule-cards-grid">';
            data.data.forEach(schedule => {
                const date = new Date(schedule.date);
                const formattedDate = date.toLocaleDateString('ja-JP', {
                    month: 'long',
                    day: 'numeric',
                    weekday: 'long'
                });

                listHTML += `
                    <div class="schedule-card">
                        <div class="card-header">
                            <div class="card-date">${formattedDate}</div>
                            <div class="card-actions">
                                <a href="<?php echo esc_url(home_url('/schedule-edit')); ?>?date=${schedule.date}" class="btn btn-sm btn-primary">編集</a>
                                <button class="btn btn-sm btn-danger" onclick="deleteScheduleFromList('${schedule.date}')">削除</button>
                            </div>
                        </div>
                        <div class="card-body">
                            <h4 class="card-title">${schedule.type}</h4>
                            <div class="card-details">
                                <div class="detail-item">
                                    <span class="detail-label">⏰ 時間:</span>
                                    <span class="detail-value">${schedule.start_time} - ${schedule.end_time}</span>
                                </div>
                                ${schedule.place ? `
                                <div class="detail-item">
                                    <span class="detail-label">📍 会場:</span>
                                    <span class="detail-value">${schedule.place}</span>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `;
            });
            listHTML += '</div>';
            listContainer.innerHTML = listHTML;
            console.log('✅ スケジュールリスト表示完了');
        } else {
            listContainer.innerHTML = '<div class="no-schedule"><p>スケジュールが登録されていません</p></div>';
            console.log('📝 スケジュールなし');
        }
    })
    .catch(error => {
        console.error('❌ スケジュールリスト取得エラー:', error);
        listContainer.innerHTML = '<div class="no-schedule"><p>スケジュールの取得に失敗しました</p></div>';
    });
}

// 詳細スケジュールリストを読み込み（テーブル表示）
function loadDetailedScheduleList() {
    const listContainer = document.getElementById('schedule-detailed-list');
    if (!listContainer) {
        console.warn('⚠️ 詳細スケジュールリスト表示要素が見つかりません');
        return;
    }

    console.log('🔍 詳細スケジュールリスト読み込み開始');

    // REST APIから全期間のスケジュールを取得
    fetch('/wp-json/aidunite/v1/get-user-schedules', {
        method: 'GET',
        headers: {
            'X-WP-Nonce': wpApiSettings.nonce
        }
    })
    .then(response => response.json())
    .then(data => {
        console.log('📋 取得した詳細スケジュールリスト:', data);

        if (data.success && data.data && data.data.length > 0) {
            let listHTML = `
                <div class="schedule-table-container">
                    <table class="schedule-table">
                        <thead>
                            <tr>
                                <th>日付</th>
                                <th>時間</th>
                                <th>種別</th>
                                <th>会場</th>
                                <th>ステータス</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            data.data.forEach(schedule => {
                const date = new Date(schedule.date);
                const formattedDate = date.toLocaleDateString('ja-JP', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    weekday: 'long'
                });

                listHTML += `
                    <tr>
                        <td class="schedule-date">${formattedDate}</td>
                        <td class="schedule-time">${schedule.start_time} - ${schedule.end_time}</td>
                        <td class="schedule-type">${schedule.type}</td>
                        <td class="schedule-place">${schedule.place || '-'}</td>
                        <td class="schedule-status">
                            <span class="status-badge status-${schedule.match_status || 'planned'}">
                                ${getStatusText(schedule.match_status)}
                            </span>
                        </td>
                        <td class="schedule-actions">
                            <a href="<?php echo esc_url(home_url('/schedule-edit')); ?>?date=${schedule.date}" class="btn btn-sm btn-primary">編集</a>
                            <button class="btn btn-sm btn-danger" onclick="deleteScheduleFromList('${schedule.date}')">削除</button>
                        </td>
                    </tr>
                `;
            });

            listHTML += `
                        </tbody>
                    </table>
                </div>
            `;

            listContainer.innerHTML = listHTML;
            console.log('✅ 詳細スケジュールリスト表示完了');
        } else {
            listContainer.innerHTML = '<div class="no-schedule"><p>スケジュールが登録されていません</p></div>';
            console.log('📝 スケジュールなし');
        }
    })
    .catch(error => {
        console.error('❌ 詳細スケジュールリスト取得エラー:', error);
        listContainer.innerHTML = '<div class="no-schedule"><p>スケジュールの取得に失敗しました</p></div>';
    });
}

// ステータステキストを取得
function getStatusText(status) {
    const statusMap = {
        'planned': '予定',
        'matched': '成立',
        'confirmed': '確定',
        'cancelled': 'キャンセル'
    };
    return statusMap[status] || '不明';
}

// リストからスケジュールを編集
function editScheduleFromList(dateString) {
    const date = new Date(dateString);
    const formattedDate = date.toISOString().split('T')[0];

    // 指定日のスケジュールを取得して編集ページにリダイレクト
    fetch(`/wp-json/aidunite/v1/get-schedules-by-date`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': wpApiSettings.nonce
        },
        body: JSON.stringify({ date: formattedDate })
    })
    .then(response => response.json())
    .then(schedules => {
        if (Array.isArray(schedules) && schedules.length > 0) {
            // 最初のスケジュールを編集
            const schedule = schedules[0];
            window.location.href = `<?php echo get_permalink(get_page_by_path('schedule-edit')); ?>?post_id=${schedule.id}`;
        } else {
            // スケジュールがない場合は新規作成
            openScheduleEdit(dateString);
        }
    })
    .catch(error => {
        console.error('スケジュール取得エラー:', error);
        // エラーの場合は新規作成
        openScheduleEdit(dateString);
    });
}

// リストからスケジュールを削除
function deleteScheduleFromList(dateString) {
    selectedDate = new Date(dateString);
    showDeleteConfirmModal();
}

// 日付の比較（年月日のみ）
function isSameDate(date1, date2) {
    return date1.getFullYear() === date2.getFullYear() &&
           date1.getMonth() === date2.getMonth() &&
           date1.getDate() === date2.getDate();
}

// 今日の日付を取得（日本時間）
function getTodayDate() {
    const now = new Date();

    // 方法1: ローカルタイムゾーンで今日の日付を取得
    const localToday = now.toLocaleDateString('ja-JP', {
        timeZone: 'Asia/Tokyo',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
    }).split('/').join('-');

    // 方法2: UTC+9で計算（フォールバック）
    const jstDate = new Date(now.getTime() + (9 * 60 * 60 * 1000));
    const jstToday = jstDate.toISOString().split('T')[0];

    // 方法3: 現在のローカル日付（デバッグ用）
    const localDate = now.toLocaleDateString('en-CA'); // YYYY-MM-DD形式

    console.log('🔍 今日の日付判定:', {
        now: now.toISOString(),
        localToday: localToday,
        jstToday: jstToday,
        localDate: localDate,
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone
    });

    // ローカルタイムゾーンが利用可能な場合はそれを使用、そうでなければJST計算を使用
    return localToday || jstToday;
}

// 今日の日付かどうかを判定
function isToday(dateString) {
    const today = getTodayDate();
    const isTodayResult = dateString === today;

    console.log('🔍 今日判定:', {
        dateString: dateString,
        today: today,
        isToday: isTodayResult
    });

    return isTodayResult;
}

// 削除確認モーダルの外側クリックで閉じる
window.onclick = function(event) {
    const deleteModal = document.getElementById('delete-confirm-modal');
    if (deleteModal && event.target === deleteModal) {
        closeDeleteConfirmModal();
    }
}
</script>


<?php get_footer(); ?>
