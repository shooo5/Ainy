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
 *
 * @param array<string, mixed> $extra canEdit, showAttendanceSummary 等
 */
function aidunite_build_schedule_view_config(array $extra = []) {
    $user_id = get_current_user_id();
    $teams = function_exists('aidunite_get_managed_teams_for_schedule_ui')
        ? aidunite_get_managed_teams_for_schedule_ui($user_id)
        : [];
    $multi_team = count($teams) > 1;
    $fetch_scope = $multi_team ? 'managed' : 'operating';

    if (!empty($GLOBALS['aidunite_schedule_view_config']) && is_array($GLOBALS['aidunite_schedule_view_config'])) {
        $extra = array_merge($GLOBALS['aidunite_schedule_view_config'], $extra);
    }

    $can_edit = !empty($extra['canEdit']);
    $show_attendance = !empty($extra['showAttendanceSummary']);
    $member_team_ids = function_exists('aidunite_get_member_team_ids_for_schedule_view')
        ? aidunite_get_member_team_ids_for_schedule_view($user_id)
        : [];

    return [
        'multiTeam' => $multi_team,
        'fetchScope' => $fetch_scope,
        'teams' => $teams,
        'memberTeamIds' => array_values(array_map('intval', $member_team_ids)),
        'activeTeamFilter' => 'all',
        'viewModeStorageKey' => 'ainy_schedule_view_mode',
        'listBreakpointPx' => 768,
        'canEdit' => $can_edit,
        'showAttendanceSummary' => $show_attendance,
    ];
}

function output_schedule_month_view_config(array $extra = []) {
    aidunite_enqueue_schedule_calendar_scripts();
    wp_localize_script(
        'aidunite-schedule-month-view',
        'AIDUNITE_SCHEDULE_VIEW',
        aidunite_build_schedule_view_config($extra)
    );
}

function aidunite_enqueue_schedule_calendar_scripts() {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $theme_dir = get_stylesheet_directory();
    $theme_uri = get_stylesheet_directory_uri();

    $schedule_js = $theme_dir . '/assets/js/schedule/schedule.js';
    if (is_readable($schedule_js)) {
        wp_enqueue_script(
            'aidunite-schedule',
            $theme_uri . '/assets/js/schedule/schedule.js',
            ['aidunite-date-utils', 'aidunite-schedule-utils'],
            (string) filemtime($schedule_js),
            true
        );
    }

    $month_view_js = $theme_dir . '/assets/js/schedule/schedule-month-view.js';
    if (is_readable($month_view_js)) {
        wp_enqueue_script(
            'aidunite-schedule-month-view',
            $theme_uri . '/assets/js/schedule/schedule-month-view.js',
            ['aidunite-date-utils', 'aidunite-schedule-utils', 'aidunite-schedule'],
            (string) filemtime($month_view_js),
            true
        );
    }

    $bootstrap_js = $theme_dir . '/assets/js/schedule/schedule-calendar-bootstrap.js';
    if (is_readable($bootstrap_js)) {
        wp_enqueue_script(
            'aidunite-schedule-calendar-bootstrap',
            $theme_uri . '/assets/js/schedule/schedule-calendar-bootstrap.js',
            ['aidunite-date-utils', 'aidunite-schedule-utils', 'aidunite-schedule', 'aidunite-schedule-modal'],
            (string) filemtime($bootstrap_js),
            true
        );
        wp_localize_script('aidunite-schedule-calendar-bootstrap', 'aiduniteScheduleCalendarBootstrap', [
            'scheduleEditUrl' => function_exists('aidunite_get_schedule_edit_url')
                ? aidunite_get_schedule_edit_url()
                : home_url('/schedule-management/'),
        ]);
    }
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

        <div class="schedule-day-panel" id="schedule-day-panel" hidden>
            <header class="schedule-day-panel__head">
                <h4 class="schedule-day-panel__title" id="schedule-day-panel-title"></h4>
                <span class="schedule-day-panel__count" id="schedule-day-panel-count"></span>
            </header>
            <div class="schedule-day-panel__list" id="schedule-day-panel-list" aria-live="polite"></div>
            <p class="schedule-day-panel__empty" id="schedule-day-panel-empty" hidden>この日の予定はありません</p>
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
    output_schedule_month_view_config();
}
