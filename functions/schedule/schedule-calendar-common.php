<?php
/**
 * スケジュールカレンダー表示の共通テンプレート
 * マイページとスケジュール管理ページで共通使用
 */

/**
 * カレンダーのHTMLを出力
 */
/**
 * 月間表示用 JS 設定（managed チーム・デフォルト表示モード）
 */
function output_schedule_month_view_config() {
    $user_id = get_current_user_id();
    $teams = function_exists('aidunite_get_managed_teams_for_schedule_ui')
        ? aidunite_get_managed_teams_for_schedule_ui($user_id)
        : [];
    $multi_team = count($teams) > 1;
    $fetch_scope = $multi_team ? 'managed' : 'operating';
    ?>
    <script>
    window.AIDUNITE_SCHEDULE_VIEW = {
        multiTeam: <?php echo $multi_team ? 'true' : 'false'; ?>,
        fetchScope: <?php echo wp_json_encode($fetch_scope); ?>,
        teams: <?php echo wp_json_encode($teams); ?>,
        activeTeamFilter: 'all',
        viewModeStorageKey: 'ainy_schedule_view_mode',
        listBreakpointPx: 768
    };
    </script>
    <?php
}

/**
 * 表示切替・チームフィルタ（schedule-filter-bar 内で呼ぶ・トンマナ統一）
 */
function output_schedule_view_and_team_filters() {
    $user_id = get_current_user_id();
    $teams = function_exists('aidunite_get_managed_teams_for_schedule_ui')
        ? aidunite_get_managed_teams_for_schedule_ui($user_id)
        : [];
    $show_team_filter = count($teams) > 1;
    ?>
    <div class="schedule-filter-row schedule-filter-row--view">
        <div class="filter-chips schedule-view-toggle-chips" id="schedule-view-toggle" role="tablist" aria-label="表示形式">
            <button type="button" class="filter-chip active" data-view="calendar" role="tab" aria-selected="true">カレンダー</button>
            <button type="button" class="filter-chip" data-view="list" role="tab" aria-selected="false">リスト</button>
        </div>
    </div>
    <?php if ($show_team_filter) : ?>
    <div class="schedule-filter-row schedule-filter-row--team">
        <div class="filter-chips" id="schedule-team-filter-chips" aria-label="チームで絞り込み">
            <button type="button" class="filter-chip active" data-team-filter="all">すべて</button>
            <?php foreach ($teams as $team) : ?>
                <?php
                $gender = isset($team['gender_label']) ? (string) $team['gender_label'] : '';
                $name = isset($team['name']) ? (string) $team['name'] : '';
                $show_gender = $gender !== '' && ($name === '' || mb_strpos($name, $gender) === false);
                ?>
            <button type="button" class="filter-chip" data-team-filter="<?php echo esc_attr((string) $team['id']); ?>">
                <?php echo esc_html($name); ?>
                <?php if ($show_gender) : ?>
                <span class="schedule-team-filter__gender"><?php echo esc_html($gender); ?></span>
                <?php endif; ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php
}

function output_schedule_calendar_html() {
    ?>
    <div class="schedule-month-shell">
        <div class="calendar-header schedule-month-nav" id="schedule-month-nav" role="navigation" aria-label="表示月">
            <button type="button" class="calendar-nav-btn calendar-nav-btn--month" id="calendar-prev-btn" aria-label="前月">
                <?php echo aidunite_render_theme_icon('chevron_left', ['width' => '20', 'height' => '20'], 'calendar-nav-btn__chevron'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </button>
            <h3 class="calendar-title" id="calendar-title">2024年1月</h3>
            <button type="button" class="calendar-nav-btn calendar-nav-btn--month" id="calendar-next-btn" aria-label="次月">
                <?php echo aidunite_render_theme_icon('chevron_right', ['width' => '20', 'height' => '20'], 'calendar-nav-btn__chevron'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </button>
        </div>

        <div class="schedule-month-list-wrap" id="schedule-month-list-wrap" hidden>
            <div class="schedule-month-list" id="schedule-month-list" aria-live="polite"></div>
            <p class="schedule-month-list-empty" id="schedule-month-list-empty" hidden>この月の予定はありません</p>
        </div>

        <div class="calendar-container schedule-calendar-panel" id="schedule-calendar-panel">
        <div class="calendar-weekdays">
            <div class="weekday weekday-sunday">日</div>
            <div class="weekday weekday-monday">月</div>
            <div class="weekday weekday-tuesday">火</div>
            <div class="weekday weekday-wednesday">水</div>
            <div class="weekday weekday-thursday">木</div>
            <div class="weekday weekday-friday">金</div>
            <div class="weekday weekday-saturday">土</div>
        </div>

        <div class="calendar-grid" id="calendar-grid">
            <!-- カレンダーの日付がここに動的に生成される -->
        </div>
        </div>
    </div>
    <?php
}

/**
 * カレンダー表示用のCSS（廃止: schedule.css を enqueue.php で読み込み）
 */
function output_schedule_calendar_css() {
    // インライン CSS は schedule.css と干渉するため出力しない。
}

/**
 * カレンダー表示用のJavaScriptを出力
 */
function output_schedule_calendar_js() {
    // date-utils.jsが先に読み込まれていることを確認
    $date_utils_url = get_template_directory_uri() . '/assets/js/common/date-utils.js';
    $js_url = get_template_directory_uri() . '/assets/js/schedule/schedule.js';
    $month_view_url = get_template_directory_uri() . '/assets/js/schedule/schedule-month-view.js';
    $month_view_path = get_template_directory() . '/assets/js/schedule/schedule-month-view.js';
    $month_view_ver = is_readable($month_view_path) ? (string) filemtime($month_view_path) : '1.0.0';
    output_schedule_month_view_config();
    ?>
    <!-- date-utils.jsを先に読み込む（schedule.jsの依存関係） -->
    <script src="<?php echo esc_url($date_utils_url); ?>"></script>
    <script src="<?php echo esc_url($js_url); ?>"></script>
    <script src="<?php echo esc_url($month_view_url); ?>?ver=<?php echo esc_attr($month_view_ver); ?>"></script>
    <script>
    // カレンダー表示の共通変数
    window.currentDate = window.currentDate || new Date();
    window.schedules = window.schedules || {};

    // formatDateLocal関数は js/common/date-utils.js の AidUniteDateUtils.formatDateLocal() を使用
    // 後方互換性のため、グローバル関数として利用可能
    // js/schedule.jsのrenderCalendar()がformatDateLocal()を直接呼び出すため、グローバルに公開
    // formatDateLocalとformatTimeをグローバルに公開（js/schedule.jsのrenderCalendar()が使用するため）
    (function() {
        if (typeof formatDateLocal === 'undefined') {
            if (typeof AidUniteDateUtils !== 'undefined' && AidUniteDateUtils.formatDateLocal) {
                window.formatDateLocal = AidUniteDateUtils.formatDateLocal.bind(AidUniteDateUtils);
            } else {
                console.error('❌ formatDateLocal関数が見つかりません。js/common/date-utils.jsが読み込まれているか確認してください。');
            }
        }

        if (typeof formatTime === 'undefined') {
            if (typeof AidUniteDateUtils !== 'undefined' && AidUniteDateUtils.formatTime) {
                window.formatTime = AidUniteDateUtils.formatTime.bind(AidUniteDateUtils);
            }
        }
    })();

    // 今日の日付かどうかチェック
    function isTodayDate(dateString) {
        const formatDateLocalFn = typeof formatDateLocal === 'function'
            ? formatDateLocal
            : (typeof AidUniteDateUtils !== 'undefined' && AidUniteDateUtils.formatDateLocal
                ? AidUniteDateUtils.formatDateLocal.bind(AidUniteDateUtils)
                : null);

        if (!formatDateLocalFn) {
            return false;
        }

        const today = new Date();
        const jstToday = formatDateLocalFn(today);
        return dateString === jstToday;
    }

    // 日本の祝日・イベント名を取得（例: 元日 / 文化の日 / 大晦日 など）
    function getJapaneseHolidayOrEventLabel(date) {
        const year = date.getFullYear();
        const month = date.getMonth() + 1;
        const day = date.getDate();

        // 固定祝日・イベント
        const fixedHolidays = {
            '1-1':  '元日',
            '2-11': '建国記念の日',
            '4-29': '昭和の日',
            '5-3':  '憲法記念日',
            '5-4':  'みどりの日',
            '5-5':  'こどもの日',
            '8-11': '山の日',
            '11-3': '文化の日',
            '11-23': '勤労感謝の日',
            // 現行の天皇誕生日（簡易）
            '2-23': '天皇誕生日',
            // 世の中的なイベント
            '12-31': '大晦日',
            '12-25': 'クリスマス'
        };

        const key = `${month}-${day}`;
        if (fixedHolidays[key]) {
            return fixedHolidays[key];
        }

        // 春分の日（簡易計算）
        if (month === 3) {
            const springEquinox = year <= 1980 ? Math.floor(20.8357 + 0.242194 * (year - 1980) - Math.floor((year - 1980) / 4)) :
                year <= 2099 ? Math.floor(20.8431 + 0.242194 * (year - 1980) - Math.floor((year - 1980) / 4)) : 20;
            if (day === springEquinox) {
                return '春分の日';
            }
        }

        // 秋分の日（簡易計算）
        if (month === 9) {
            const autumnEquinox = year <= 1980 ? Math.floor(23.2588 + 0.242194 * (year - 1980) - Math.floor((year - 1980) / 4)) :
                year <= 2099 ? Math.floor(23.2488 + 0.242194 * (year - 1980) - Math.floor((year - 1980) / 4)) : 23;
            if (day === autumnEquinox) {
                return '秋分の日';
            }
        }

        // 海の日（7月23日、2020年以降）
        if (month === 7 && year >= 2020 && day === 23) {
            return '海の日';
        }

        // 敬老の日（9月の第3月曜日 - 簡易版）
        if (month === 9) {
            const firstDay = new Date(year, 8, 1);
            const firstMonday = (9 - firstDay.getDay()) % 7;
            const thirdMonday = firstMonday + 14;
            if (day === thirdMonday) {
                return '敬老の日';
            }
        }

        // スポーツの日（10月の第2月曜日 - 簡易版）
        if (month === 10) {
            const firstDay = new Date(year, 9, 1);
            const firstMonday = (9 - firstDay.getDay()) % 7;
            const secondMonday = firstMonday + 7;
            if (day === secondMonday) {
                return 'スポーツの日';
            }
        }

        return '';
    }

    // 日本の祝日判定（簡易版：登録ページと同じロジック）
    // 既存コードとの互換性のため、boolean を返すラッパーとして残す
    function isJapaneseHoliday(date) {
        return !!getJapaneseHolidayOrEventLabel(date);
    }

    // カレンダー描画はjs/schedule.jsのrenderCalendar()を使用
    // formatDateLocalとformatTimeは上記のIIFE内で既にグローバルに公開済み

    // スケジュールのHTMLを生成
    // js/schedule.jsのgetScheduleHTML()がwindow.generateScheduleCardを使用するため、
    // output_schedule_calendar_js()内で定義されたgenerateScheduleCardを公開する必要がある
    function getScheduleHTML(dateString) {
        const schedules = window.schedules || {};
        if (!schedules[dateString] || schedules[dateString].length === 0) {
            return '';
        }

        const scheduleList = schedules[dateString];
        const maxDisplay = 2;
        const showMore = scheduleList.length > maxDisplay;
        const displaySchedules = showMore ? scheduleList.slice(0, maxDisplay) : scheduleList;

        let html = '';

        // generateScheduleCardを安全に参照（window.generateScheduleCardを優先）
        const cardGenerator = typeof window.generateScheduleCard === 'function'
            ? window.generateScheduleCard
            : (typeof generateScheduleCard === 'function' ? generateScheduleCard : null);

        if (!cardGenerator) {
            console.warn('⚠️ generateScheduleCard関数が見つかりません');
            return '';
        }

        displaySchedules.forEach(schedule => {
            html += cardGenerator(schedule);
        });

        if (showMore) {
            const remainingCount = scheduleList.length - maxDisplay;
            const remainingSchedules = scheduleList.slice(maxDisplay);
            const scheduleIds = remainingSchedules.map(s => s.id).join(',');
            html += `<div class="schedule-more" onclick="if(window.showAllSchedulesForDate){window.showAllSchedulesForDate('${dateString}');}else if(window.showScheduleDetail){window.showAllSchedules('${dateString}');}">+ ${remainingCount}件</div>`;
        }

        return html;
    }

    // 性別を日本語に変換（共通ユーティリティを使用）
    function getGenderLabel(gender) {
        if (typeof AidUniteScheduleUtils !== 'undefined' && AidUniteScheduleUtils.getGenderLabel) {
            return AidUniteScheduleUtils.getGenderLabel(gender);
        }
        if (typeof window.getGenderLabel === 'function') {
            return window.getGenderLabel(gender);
        }
        // フォールバック
        if (!gender) return '';
        const labels = {
            'male': '男子',
            'female': '女子',
        };
        return labels[gender] || (gender ? gender : '—');
    }

    // 会場条件を日本語に変換（共通ユーティリティを使用）
    function getVenueLabel(venue) {
        if (typeof AidUniteScheduleUtils !== 'undefined' && AidUniteScheduleUtils.getVenueLabel) {
            return AidUniteScheduleUtils.getVenueLabel(venue);
        }
        if (typeof window.getVenueLabel === 'function') {
            return window.getVenueLabel(venue);
        }
        // フォールバック
        if (!venue) return '';
        const labels = {
            'home': 'ホーム',
            'away': 'アウェイ',
            'both': 'どちらでも',
            'either': 'どちらでも'
        };
        return labels[venue] || venue;
    }

    // スケジュールカードを生成（schedule.js の buildCalendarScheduleCard に委譲）
    function generateScheduleCard(schedule, options = {}) {
        if (typeof window.AidUniteBuildCalendarScheduleCard === 'function') {
            return window.AidUniteBuildCalendarScheduleCard(schedule, options);
        }
        const scheduleId = schedule.id || schedule.post_id || schedule.schedule_id || '';
        const title = (schedule.type || '予定').toString();
        return '<div class="schedule-card practice" data-schedule-id="' + scheduleId + '"><div class="schedule-line-1">' + title + '</div></div>';
    }

    // スケジュールタイトルを短縮
    function shortenScheduleTitle(title) {
        const shortTitles = {
            '練習': '練習',
            '練習（仮）': '練習（仮）',
            '公式試合': '公式試合',
            '練習試合': '練習試合',
            '練習試合（募集）': '練習試合（募）',
            '練習試合（募）': '練習試合（募）',
            '練習試合（仮）': '練習試合（仮）',
            '合同練習': '合同練習',
            '合同練習（募集）': '合同練習（募）',
            '合同練習（募）': '合同練習（募）',
            '合同練習（仮）': '合同練習（仮）',
            '合宿': '合宿',
            '合宿（仮）': '合宿（仮）',
            '遠征': '遠征',
            '遠征（仮）': '遠征（仮）',
            '休み': '休み',
            '休み（仮）': '休み（仮）',
            'イベント': 'イベント',
            'イベント（仮）': 'イベント（仮）'
        };
        return shortTitles[title] || title;
    }

    // スケジュールアイコンを取得
    // getScheduleIcon関数は js/common/schedule-utils.js の AidUniteScheduleUtils.getScheduleIcon() を使用
    // 後方互換性のため、グローバル関数として利用可能

    // 時間フォーマット
    // formatTime関数は js/common/date-utils.js の AidUniteDateUtils.formatTime() を使用
    // 後方互換性のため、グローバル関数として利用可能

    // 前月
    function previousMonth() {
        const currentDate = window.currentDate || new Date();
        currentDate.setMonth(currentDate.getMonth() - 1);
        window.currentDate = currentDate;
        loadSchedules();
    }

    // 次月
    function nextMonth() {
        const currentDate = window.currentDate || new Date();
        currentDate.setMonth(currentDate.getMonth() + 1);
        window.currentDate = currentDate;
        loadSchedules();
    }

    // スケジュール読み込み（各ページで実装）
    function loadSchedules() {
        // 各ページで個別に実装
        if (typeof window.loadSchedulesCallback === 'function') {
            window.loadSchedulesCallback();
        } else {
            // デフォルトの動作：カレンダーのみ再描画
            if (typeof renderCalendar === 'function') {
                renderCalendar();
            } else if (typeof window.renderCalendar === 'function') {
                window.renderCalendar();
            }
        }
    }

    // 日付選択
    function selectDate(dateString) {
        console.log('日付選択:', dateString);

        // 過去の日付は選択不可
        const selectedDate = new Date(dateString);
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        if (selectedDate < today) {
            console.log('過去の日付は選択できません:', dateString);
            return;
        }

        // スケジュールがあるかチェック
        const schedules = window.schedules || {};
        const hasSchedule = schedules[dateString] && schedules[dateString].length > 0;

        if (hasSchedule) {
            // スケジュールがある場合は既存の処理
            console.log('スケジュールが存在します:', dateString);
            // 各ページで個別に実装
        } else {
            // スケジュールがない場合は新規登録ポップアップを表示
            showNewSchedulePopup(dateString);
        }
    }

    // 新規登録ポップアップを表示
    function showNewSchedulePopup(dateString) {
        console.log('新規登録ポップアップ表示:', dateString);

        // 日付を日本語形式で表示
        const date = new Date(dateString);
        const formattedDate = date.toLocaleDateString('ja-JP', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            weekday: 'long'
        });

        // 既存のポップアップを削除
        const existingPopup = document.getElementById('new-schedule-popup');
        if (existingPopup) {
            existingPopup.remove();
        }

        // ポップアップHTMLを生成
        const popupHTML = `
            <div id="new-schedule-popup" class="new-schedule-popup">
                <div class="popup-overlay" onclick="closeNewSchedulePopup()"></div>
                <div class="popup-content">
                    <div class="popup-header">
                        <h3>新規スケジュール登録</h3>
                        <button class="popup-close" onclick="closeNewSchedulePopup()">&times;</button>
                    </div>
                    <div class="popup-body">
                        <p class="date-info">📅 <strong>${formattedDate}</strong></p>
                        <p class="message">この日には予定がありません。</p>
                        <p class="message">新規スケジュールを登録しますか？</p>
                    </div>
                    <div class="popup-footer">
                        <button class="btn btn-primary" onclick="goToScheduleEdit('${dateString}')">
                            新規登録
                        </button>
                        <button class="btn btn-secondary" onclick="closeNewSchedulePopup()">
                            キャンセル
                        </button>
                    </div>
                </div>
            </div>
        `;

        // ポップアップを表示
        document.body.insertAdjacentHTML('beforeend', popupHTML);

        // アニメーション効果
        setTimeout(() => {
            const popup = document.getElementById('new-schedule-popup');
            if (popup) {
                popup.classList.add('show');
            }
        }, 10);
    }

    // 新規登録ポップアップを閉じる
    function closeNewSchedulePopup() {
        const popup = document.getElementById('new-schedule-popup');
        if (popup) {
            popup.classList.remove('show');
            setTimeout(() => {
                popup.remove();
            }, 300);
        }
    }

    // スケジュール編集ページに遷移
    function goToScheduleEdit(dateString) {
        console.log('スケジュール編集ページに遷移:', dateString);
        const editUrl = '<?php echo home_url("/schedule-edit"); ?>?date=' + dateString;
        window.location.href = editUrl;
    }

    // 全スケジュール表示（複数スケジュールをすべて表示）
    function showAllSchedulesForDate(dateString) {
        const schedules = window.schedules || {};
        const scheduleList = schedules[dateString] || [];

        if (scheduleList.length === 0) {
            console.log('スケジュールがありません:', dateString);
            return;
        }

        // 複数スケジュールがある場合、すべてを表示
        if (scheduleList.length > 1) {
            // モーダルまたはポップアップで複数スケジュールを表示
            if (typeof AidUniteScheduleModal !== 'undefined') {
                // 最初のスケジュールを表示し、残りをリストとして表示
                const firstSchedule = scheduleList[0];
                const remainingSchedules = scheduleList.slice(1);

                // 複数スケジュール用のHTMLを生成
                let multiScheduleHTML = '<div class="multi-schedule-list">';
                multiScheduleHTML += '<h4>スケジュール一覧 (' + scheduleList.length + '件)</h4>';
                scheduleList.forEach((schedule, index) => {
                    multiScheduleHTML += '<div class="schedule-item" onclick="AidUniteScheduleModal.showDetail(' + schedule.id + ', {mode: \'popup\'})">';
                    multiScheduleHTML += '<div class="schedule-item-time">' + (schedule.start_time || '') + ' - ' + (schedule.end_time || '') + '</div>';
                    multiScheduleHTML += '<div class="schedule-item-type">' + (schedule.type || '') + '</div>';
                    multiScheduleHTML += '</div>';
                });
                multiScheduleHTML += '</div>';

                // ポップアップとして表示
                const popup = document.createElement('div');
                popup.className = 'schedule-detail-popup';
                popup.innerHTML = '<div class="popup-overlay"></div><div class="popup-content">' + multiScheduleHTML + '<button class="popup-close" onclick="this.closest(\'.schedule-detail-popup\').remove()">×</button></div>';
                document.body.appendChild(popup);
                setTimeout(() => popup.classList.add('show'), 10);
            } else {
                // フォールバック: 最初のスケジュールを表示
                if (typeof window.showScheduleDetail === 'function') {
                    window.showScheduleDetail(scheduleList[0].id);
                }
            }
        } else {
            // 単一スケジュールの場合は通常通り表示
            if (typeof AidUniteScheduleModal !== 'undefined') {
                AidUniteScheduleModal.showDetail(scheduleList[0].id, { mode: 'popup' });
            } else if (typeof window.showScheduleDetail === 'function') {
                window.showScheduleDetail(scheduleList[0].id);
            }
        }
    }

    // 全スケジュール表示（必要に応じて各ページで上書き可能）
    function showAllSchedules(dateString) {
        if (typeof window.showAllSchedulesForDate === 'function') {
            return window.showAllSchedulesForDate(dateString);
        }
        if (typeof window.showAllSchedules === 'function' && window.showAllSchedules !== showAllSchedules) {
            // ページ側で定義されていればそちらを優先
            return window.showAllSchedules(dateString);
        }
        console.log('全スケジュール表示（デフォルト）:', dateString);
    }

    // スケジュール詳細表示（共通：ポップアップに統一）
    function showScheduleDetail(scheduleId) {
        try {
            // 既存の一覧モーダルが開いていたら先に閉じる/除去（位置ズレ・干渉対策）
            const openListModals = document.querySelectorAll('.schedule-detail-modal');
            if (openListModals && openListModals.length) {
                openListModals.forEach(m => m.parentNode && m.parentNode.removeChild(m));
            }
            if (typeof AidUniteScheduleModal !== 'undefined') {
                AidUniteScheduleModal.showDetail(scheduleId, { mode: 'popup' });
                return;
            }
            // 後方互換: ページ側で関数があれば呼ぶ
            if (typeof window.showScheduleDetail === 'function' && window.showScheduleDetail !== showScheduleDetail) {
                return window.showScheduleDetail(scheduleId);
            }
            console.error('AidUniteScheduleModal が利用できず、ページ固有の showScheduleDetail も見つかりません');
        } catch (e) {
            console.error('showScheduleDetail 実行時エラー:', e);
        }
    }

    // ============================================
    // グローバルスコープに公開（すべての関数定義後に実行）
    // ============================================
    // 注意: 関数を定義してから公開することで、関数定義の順序エラーを防ぐ
    // renderCalendarはjs/schedule.jsから提供されるため、ここでは公開しない
    // ただし、output_schedule_calendar_js()内で定義されたgetScheduleHTMLとgenerateScheduleCardを公開
    // これにより、js/schedule.jsのrenderCalendar()がこれらの関数を使用できる
    window.getScheduleHTML = getScheduleHTML;
    window.generateScheduleCard = generateScheduleCard;
    window.previousMonth = previousMonth;
    window.nextMonth = nextMonth;

    // 前月・次月ボタン（id 指定。onclick 未設定ページ向けのフォールバック）
    function bindCalendarMonthNavButtons() {
        const prevBtn = document.getElementById('calendar-prev-btn');
        const nextBtn = document.getElementById('calendar-next-btn');
        if (prevBtn && !prevBtn.dataset.navBound) {
            prevBtn.dataset.navBound = '1';
            prevBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (typeof window.previousMonth === 'function') {
                    window.previousMonth();
                }
            });
        }
        if (nextBtn && !nextBtn.dataset.navBound) {
            nextBtn.dataset.navBound = '1';
            nextBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (typeof window.nextMonth === 'function') {
                    window.nextMonth();
                }
            });
        }
    }
    window.bindCalendarMonthNavButtons = bindCalendarMonthNavButtons;
    window.selectDate = selectDate;
    window.loadSchedules = loadSchedules;
    window.showNewSchedulePopup = showNewSchedulePopup;
    window.closeNewSchedulePopup = closeNewSchedulePopup;
    window.goToScheduleEdit = goToScheduleEdit;
    window.showAllSchedules = showAllSchedules;
    window.showAllSchedulesForDate = showAllSchedulesForDate;
    // showScheduleDetailは既にページ側で定義されている場合は上書きしない
    if (typeof window.showScheduleDetail === 'undefined') {
        window.showScheduleDetail = showScheduleDetail;
    }
    window.getGenderLabel = getGenderLabel;
    window.getVenueLabel = getVenueLabel;
    window.shortenScheduleTitle = shortenScheduleTitle;

    // スケジュールカードのクリックイベントを後から追加（より確実な方法）
    function attachScheduleCardClickEvents() {
        document.querySelectorAll('.schedule-card[data-schedule-id]').forEach(card => {
            const scheduleId = card.getAttribute('data-schedule-id');
            if (scheduleId && !card.hasAttribute('data-click-attached')) {
                card.setAttribute('data-click-attached', 'true');
                card.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const id = parseInt(scheduleId);
                    if (typeof window.showScheduleDetail === 'function') {
                        window.showScheduleDetail(id);
                    } else if (typeof AidUniteScheduleModal !== 'undefined') {
                        AidUniteScheduleModal.showDetail(id, { mode: 'popup' });
                    } else {
                        console.error('showScheduleDetail not available');
                    }
                });
            }
        });
    }

    // カレンダー描画後にイベントを追加
    // スケジュールカードのフォントサイズを動的に調整（幅に応じて全部表示）
    function adjustScheduleCardFontSize() {
        const cards = document.querySelectorAll('.schedule-card');
        cards.forEach(function(card) {
            const line1 = card.querySelector('.schedule-line-1');
            if (!line1) return;

            // 初期フォントサイズを取得（px単位）
            const computedStyle = window.getComputedStyle(card);
            const rootFontSize = parseFloat(getComputedStyle(document.documentElement).fontSize);
            let fontSizePx = parseFloat(computedStyle.fontSize);
            const minFontSizePx = 0.35 * rootFontSize; // 最小フォントサイズ（px）
            const maxFontSizePx = 0.65 * rootFontSize; // 最大フォントサイズ（px）

            // カードの幅を取得（パディングを考慮）
            const cardWidth = card.offsetWidth;
            const padding = parseFloat(computedStyle.paddingLeft) + parseFloat(computedStyle.paddingRight);
            const availableWidth = cardWidth - padding;

            // テキストの幅を測定
            const text = line1.textContent || line1.innerText;
            if (!text) return;

            // 一時的な要素でテキスト幅を測定
            const measureEl = document.createElement('span');
            measureEl.style.visibility = 'hidden';
            measureEl.style.position = 'absolute';
            measureEl.style.whiteSpace = 'nowrap';
            measureEl.style.fontSize = fontSizePx + 'px';
            measureEl.style.fontWeight = '600';
            measureEl.style.fontFamily = computedStyle.fontFamily;
            measureEl.textContent = text;
            document.body.appendChild(measureEl);

            let textWidth = measureEl.offsetWidth;
            document.body.removeChild(measureEl);

            // テキストがはみ出す場合はフォントサイズを調整
            if (textWidth > availableWidth && fontSizePx > minFontSizePx) {
                // バイナリサーチで最適なフォントサイズを探す
                let low = minFontSizePx;
                let high = fontSizePx;
                let bestSize = minFontSizePx;

                for (let i = 0; i < 10; i++) {
                    const testSize = (low + high) / 2;
                    measureEl.style.fontSize = testSize + 'px';
                    document.body.appendChild(measureEl);
                    textWidth = measureEl.offsetWidth;
                    document.body.removeChild(measureEl);

                    if (textWidth <= availableWidth) {
                        bestSize = testSize;
                        low = testSize;
                    } else {
                        high = testSize;
                    }
                }

                fontSizePx = Math.max(bestSize, minFontSizePx);
            }

            // フォントサイズを適用（px単位で設定）
            card.style.fontSize = fontSizePx + 'px';
        });
    }

    if (typeof window.renderCalendar === 'function') {
        const originalRenderCalendar = window.renderCalendar;
        window.renderCalendar = function() {
            const result = originalRenderCalendar.apply(this, arguments);
            setTimeout(function() {
                attachScheduleCardClickEvents();
                adjustScheduleCardFontSize();
            }, 100);
            return result;
        };
    }

    // DOMContentLoaded時にも実行
    function onCalendarDomReady() {
        bindCalendarMonthNavButtons();
        attachScheduleCardClickEvents();
        adjustScheduleCardFontSize();
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', onCalendarDomReady);
    } else {
        setTimeout(onCalendarDomReady, 100);
    }

    // ウィンドウリサイズ時にも再調整
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(adjustScheduleCardFontSize, 200);
    });
    </script>
    <?php
}
