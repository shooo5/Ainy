<?php
/**
 * Template Name: Ainy 管理者ダッシュボード
 * 管理者専用。KPIカード・やること・ショートカット。
 *
 * 使い方: 固定ページを新規作成し、スラッグを「ainy-dashboard」に、
 * テンプレートで「Ainy 管理者ダッシュボード」を選択してください。
 */

require_once get_template_directory() . '/functions/common/auth-middleware.php';
$auth_result = AidUniteAuthMiddleware::require_admin(true);
if (!$auth_result->is_valid()) {
    return;
}

// aidunite_dashboard_get_period_dates 等は ainy-dashboard-kpi.php で定義。このページでは定義しないこと（二重定義エラー防止）
require_once get_stylesheet_directory() . '/functions/dashboard/ainy-dashboard-kpi.php';

$period = isset($_GET['period']) ? sanitize_text_field($_GET['period']) : '7d';
if (!in_array($period, ['today', '7d', '30d', 'month'], true)) {
    $period = '7d';
}

// 当日メトリックを DB に反映（グラフ・KPI の鮮度）
if (function_exists('aidunite_metrics_collect_daily')) {
    aidunite_metrics_collect_daily(current_time('Y-m-d'));
}
if (function_exists('aidunite_analytics_flush_page_buffer')) {
    aidunite_analytics_flush_page_buffer(current_time('Y-m-d'));
}

$kpi = aidunite_dashboard_get_kpi_data($period);
$todo_items = aidunite_dashboard_get_todo_items();
$data_management_shortcuts = aidunite_dashboard_get_data_management_shortcuts();
$admin_shortcuts = aidunite_dashboard_get_admin_shortcuts();

$period_dates = aidunite_dashboard_get_period_dates($period);
$page_usage_payload = function_exists('aidunite_dashboard_get_page_usage_charts_payload')
    ? aidunite_dashboard_get_page_usage_charts_payload($period)
    : ['pages' => [], 'period_label' => $period_dates['label']];
$page_usage_pages = $page_usage_payload['pages'] ?? [];
$match_rate = $kpi['match_establishment_rate'] ?? ['label' => '—', 'formula' => '', 'beta' => true];
$chart_export_base_url = add_query_arg(
    [
        'ainy_export' => 'csv',
        'period' => $period,
    ],
    get_permalink()
);
$page_usage_csv_url = function_exists('aidunite_dashboard_get_page_usage_csv_url')
    ? aidunite_dashboard_get_page_usage_csv_url($period)
    : add_query_arg(['ainy_export' => 'csv', 'export_type' => 'page_usage', 'period' => $period], get_permalink());

// システム情報（ダッシュボード用）
$sys_wp_version = get_bloginfo('version');
$sys_php_version = phpversion();
$sys_theme_name = wp_get_theme()->get('Name');
$sys_plugins = get_option('active_plugins');
$sys_total_users = count_users()['total_users'];
$sys_total_posts = wp_count_posts()->publish;
$sys_total_pages = wp_count_posts('page')->publish;

get_header();
?>

<div class="ainy-dashboard-wrap team-dashboard-container page-ainy-dashboard">
    <header class="ainy-dashboard-header dashboard-header">
        <h1><?php echo aidunite_render_theme_icon('bar_chart_4_bars', ['width' => '28', 'height' => '28'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> Ainy 管理者ダッシュボード</h1>
        <p>現状把握・異常検知・次の打ち手の判断に使います。</p>
    </header>

    <section class="ainy-dashboard-todo" aria-label="やること">
        <h2 class="ainy-dashboard-section-title">やること</h2>
        <?php if (!empty($todo_items)) : ?>
        <ul class="ainy-dashboard-todo-list">
            <?php foreach ($todo_items as $item) : ?>
            <li>
                <a href="<?php echo esc_url($item['url']); ?>" class="ainy-dashboard-todo-link">
                    <span class="ainy-dashboard-todo-label"><?php echo esc_html($item['label']); ?></span>
                    <span class="ainy-dashboard-todo-badge" aria-label="<?php echo esc_attr($item['count']); ?>件"><?php echo (int) $item['count']; ?>件</span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php else : ?>
        <p class="ainy-dashboard-todo-empty">やることはありません</p>
        <?php endif; ?>
    </section>

    <section class="ainy-dashboard-period">
        <p class="ainy-dashboard-period-label">表示期間</p>
        <nav class="ainy-dashboard-period-nav" aria-label="期間切り替え">
            <a href="<?php echo esc_url(add_query_arg('period', 'today')); ?>" class="ainy-dashboard-period-btn <?php echo $period === 'today' ? 'is-active' : ''; ?>">今日</a>
            <a href="<?php echo esc_url(add_query_arg('period', '7d')); ?>" class="ainy-dashboard-period-btn <?php echo $period === '7d' ? 'is-active' : ''; ?>">直近7日</a>
            <a href="<?php echo esc_url(add_query_arg('period', '30d')); ?>" class="ainy-dashboard-period-btn <?php echo $period === '30d' ? 'is-active' : ''; ?>">直近30日</a>
            <a href="<?php echo esc_url(add_query_arg('period', 'month')); ?>" class="ainy-dashboard-period-btn <?php echo $period === 'month' ? 'is-active' : ''; ?>">今月</a>
        </nav>
    </section>

    <section class="ainy-dashboard-kpi" aria-label="KPI">
        <div class="ainy-dashboard-kpi-grid">
            <button type="button" class="ainy-dashboard-kpi-card ainy-dashboard-kpi-card--clickable is-chart-active" data-chart-metric="users_total" aria-pressed="true">
                <span class="ainy-dashboard-kpi-name">登録ユーザー</span>
                <span class="ainy-dashboard-kpi-value"><?php echo esc_html(number_format($kpi['users'])); ?></span>
                <?php if ($kpi['users_diff']['dir'] !== null) : ?>
                <span class="ainy-dashboard-kpi-diff ainy-dashboard-kpi-diff--<?php echo esc_attr($kpi['users_diff']['dir']); ?>">
                    <?php echo $kpi['users_diff']['dir'] === 'up' ? aidunite_render_theme_icon('arrow_upward', ['width' => '14', 'height' => '14']) : aidunite_render_theme_icon('arrow_downward', ['width' => '14', 'height' => '14']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo $kpi['users_diff']['raw'] >= 0 ? '+' . $kpi['users_diff']['raw'] : $kpi['users_diff']['raw']; ?> 前日比
                </span>
                <?php endif; ?>
                <span class="ainy-dashboard-kpi-meta">累計 · クリックで推移</span>
            </button>
            <button type="button" class="ainy-dashboard-kpi-card ainy-dashboard-kpi-card--clickable" data-chart-metric="teams_total" aria-pressed="false">
                <span class="ainy-dashboard-kpi-name">登録チーム</span>
                <span class="ainy-dashboard-kpi-value"><?php echo esc_html(number_format($kpi['teams'])); ?></span>
                <?php if ($kpi['teams_diff']['dir'] !== null) : ?>
                <span class="ainy-dashboard-kpi-diff ainy-dashboard-kpi-diff--<?php echo esc_attr($kpi['teams_diff']['dir']); ?>">
                    <?php echo $kpi['teams_diff']['dir'] === 'up' ? aidunite_render_theme_icon('arrow_upward', ['width' => '14', 'height' => '14']) : aidunite_render_theme_icon('arrow_downward', ['width' => '14', 'height' => '14']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo $kpi['teams_diff']['raw'] >= 0 ? '+' . $kpi['teams_diff']['raw'] : $kpi['teams_diff']['raw']; ?> 前日比
                </span>
                <?php endif; ?>
                <span class="ainy-dashboard-kpi-meta">累計 · クリックで推移</span>
            </button>
            <div class="ainy-dashboard-kpi-card">
                <span class="ainy-dashboard-kpi-name">承認待ちチーム</span>
                <span class="ainy-dashboard-kpi-value"><?php echo esc_html(number_format($kpi['pending_teams'])); ?></span>
                <span class="ainy-dashboard-kpi-meta">現在</span>
            </div>
            <div class="ainy-dashboard-kpi-card">
                <span class="ainy-dashboard-kpi-name">アクティブチーム</span>
                <span class="ainy-dashboard-kpi-value"><?php echo esc_html(number_format($kpi['active_teams'])); ?></span>
                <span class="ainy-dashboard-kpi-meta">直近30日</span>
            </div>
            <button type="button" class="ainy-dashboard-kpi-card ainy-dashboard-kpi-card--clickable" data-chart-metric="matches_established" aria-pressed="false">
                <span class="ainy-dashboard-kpi-name">成立マッチ</span>
                <span class="ainy-dashboard-kpi-value"><?php echo esc_html(number_format($kpi['established_matches'])); ?></span>
                <span class="ainy-dashboard-kpi-meta"><?php echo esc_html($kpi['label']); ?> · クリックで推移</span>
            </button>
            <button type="button" class="ainy-dashboard-kpi-card ainy-dashboard-kpi-card--clickable" data-chart-metric="pending_matches" aria-pressed="false">
                <span class="ainy-dashboard-kpi-name">申請中マッチ</span>
                <span class="ainy-dashboard-kpi-value"><?php echo esc_html(number_format($kpi['pending_matches'])); ?></span>
                <span class="ainy-dashboard-kpi-meta">現在 · クリックで推移</span>
            </button>
            <div class="ainy-dashboard-kpi-card ainy-dashboard-kpi-card--beta">
                <span class="ainy-dashboard-kpi-name">マッチ成立率 <span class="ainy-dashboard-beta-tag">β</span></span>
                <span class="ainy-dashboard-kpi-value"><?php echo esc_html($match_rate['label']); ?></span>
                <span class="ainy-dashboard-kpi-meta" title="暫定: 期間内成立数 ÷ 期間内申請数"><?php echo esc_html($match_rate['formula']); ?>（<?php echo esc_html($kpi['label']); ?>）</span>
            </div>
            <div class="ainy-dashboard-kpi-card">
                <span class="ainy-dashboard-kpi-name">エラー件数</span>
                <span class="ainy-dashboard-kpi-value"><?php echo esc_html(number_format($kpi['errors_24h'])); ?></span>
                <span class="ainy-dashboard-kpi-meta">直近24時間</span>
            </div>
        </div>
    </section>

    <section class="ainy-dashboard-section ainy-dashboard-trends" aria-label="推移グラフ">
        <div class="ainy-dashboard-section-head">
            <h2 id="ainy-dashboard-chart-title" class="ainy-dashboard-section-title">推移（登録ユーザー）</h2>
            <a id="ainy-dashboard-csv-link" href="<?php echo esc_url(add_query_arg('metric', 'users_total', $chart_export_base_url)); ?>" class="ainy-dashboard-csv-link" data-base-url="<?php echo esc_url($chart_export_base_url); ?>">CSV ダウンロード</a>
        </div>
        <p id="ainy-dashboard-chart-note" class="ainy-dashboard-chart-note">集計開始日以降の日次データを表示します。KPIカードをクリックすると指標を切り替えられます。</p>
        <div class="ainy-dashboard-chart-wrap">
            <canvas id="ainy-dashboard-trend-chart" role="img" aria-label="登録ユーザー数の推移グラフ"></canvas>
        </div>
    </section>

    <section class="ainy-dashboard-section ainy-dashboard-page-usage" aria-label="ページ利用状況">
        <div class="ainy-dashboard-section-head">
            <h2 class="ainy-dashboard-section-title">ページ利用状況</h2>
            <a href="<?php echo esc_url($page_usage_csv_url); ?>" class="ainy-dashboard-csv-link">CSV ダウンロード</a>
        </div>
        <p class="ainy-dashboard-chart-note">
            <?php echo esc_html($period_dates['label']); ?>の集計。
            トップは <code>home</code>（未ログインも計測）。その他は固定ページのスラッグ（例: <code>mypage</code>）など。管理者・ダッシュボードは計測除外。
        </p>
        <?php if (!empty($page_usage_pages)) : ?>
        <div class="ainy-dashboard-kpi-grid ainy-dashboard-page-cards">
            <?php foreach ($page_usage_pages as $i => $page_row) : ?>
            <button
                type="button"
                class="ainy-dashboard-kpi-card ainy-dashboard-kpi-card--clickable ainy-dashboard-page-card <?php echo $i === 0 ? 'is-chart-active' : ''; ?>"
                data-page-key="<?php echo esc_attr($page_row['page_key']); ?>"
                aria-pressed="<?php echo $i === 0 ? 'true' : 'false'; ?>"
            >
                <span class="ainy-dashboard-kpi-name"><?php echo esc_html($page_row['label']); ?></span>
                <span class="ainy-dashboard-kpi-value"><?php echo esc_html(number_format((int) $page_row['views'])); ?> <small>PV</small></span>
                <span class="ainy-dashboard-kpi-meta"><?php echo esc_html((int) $page_row['avg_sec']); ?>秒 平均滞在</span>
                <span class="ainy-dashboard-page-key-meta"><code><?php echo esc_html($page_row['page_key']); ?></code></span>
            </button>
            <?php endforeach; ?>
        </div>
        <div class="ainy-dashboard-page-chart-toolbar">
            <span class="ainy-dashboard-page-chart-toolbar-label">グラフ指標:</span>
            <button type="button" class="ainy-dashboard-period-btn is-active" data-page-metric="views">PV</button>
            <button type="button" class="ainy-dashboard-period-btn" data-page-metric="avg_sec">平均滞在</button>
        </div>
        <h3 id="ainy-dashboard-page-chart-title" class="ainy-dashboard-subsection-title">ページ推移</h3>
        <div class="ainy-dashboard-chart-wrap">
            <canvas id="ainy-dashboard-page-chart" role="img" aria-label="ページ別推移グラフ"></canvas>
        </div>
        <?php else : ?>
        <p class="ainy-dashboard-empty-metrics">まだページ分析データがありません。ログインユーザー（管理者以外）の閲覧が蓄積されるとカードとグラフが表示されます。</p>
        <?php endif; ?>
    </section>

    <section class="ainy-dashboard-shortcuts" aria-label="データ管理">
        <h2 class="ainy-dashboard-section-title">データ管理</h2>
        <div class="ainy-dashboard-shortcuts-grid">
            <?php foreach ($data_management_shortcuts as $s) : ?>
            <a href="<?php echo esc_url($s['url']); ?>" class="ainy-dashboard-shortcut-card">
                <span class="ainy-dashboard-shortcut-icon" aria-hidden="true"><?php echo aidunite_render_theme_icon($s['icon'], ['width' => '22', 'height' => '22']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                <span class="ainy-dashboard-shortcut-label"><?php echo esc_html($s['label']); ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="ainy-dashboard-shortcuts" aria-label="ショートカット">
        <h2 class="ainy-dashboard-section-title">ショートカット</h2>
        <div class="ainy-dashboard-shortcuts-grid">
            <?php foreach ($admin_shortcuts as $s) : ?>
            <a href="<?php echo esc_url($s['url']); ?>" class="ainy-dashboard-shortcut-card">
                <span class="ainy-dashboard-shortcut-icon" aria-hidden="true"><?php echo aidunite_render_theme_icon($s['icon'], ['width' => '22', 'height' => '22']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                <span class="ainy-dashboard-shortcut-label"><?php echo esc_html($s['label']); ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- システム情報（一番下部） -->
    <section class="ainy-dashboard-section ainy-dashboard-system-info" aria-label="システム情報">
        <h2 class="ainy-dashboard-section-title">システム情報</h2>
        <div class="ainy-dashboard-kpi-grid">
            <div class="ainy-dashboard-kpi-card">
                <span class="ainy-dashboard-kpi-name">WordPress</span>
                <span class="ainy-dashboard-kpi-value" style="font-size:var(--font-size-base);"><?php echo esc_html($sys_wp_version); ?></span>
            </div>
            <div class="ainy-dashboard-kpi-card">
                <span class="ainy-dashboard-kpi-name">PHP</span>
                <span class="ainy-dashboard-kpi-value" style="font-size:var(--font-size-base);"><?php echo esc_html($sys_php_version); ?></span>
            </div>
            <div class="ainy-dashboard-kpi-card">
                <span class="ainy-dashboard-kpi-name">テーマ</span>
                <span class="ainy-dashboard-kpi-value" style="font-size:var(--font-size-base);"><?php echo esc_html($sys_theme_name); ?></span>
            </div>
            <div class="ainy-dashboard-kpi-card">
                <span class="ainy-dashboard-kpi-name">プラグイン</span>
                <span class="ainy-dashboard-kpi-value"><?php echo count($sys_plugins); ?></span>
                <span class="ainy-dashboard-kpi-meta">有効</span>
            </div>
            <div class="ainy-dashboard-kpi-card">
                <span class="ainy-dashboard-kpi-name">ユーザー</span>
                <span class="ainy-dashboard-kpi-value"><?php echo number_format($sys_total_users); ?></span>
            </div>
            <div class="ainy-dashboard-kpi-card">
                <span class="ainy-dashboard-kpi-name">投稿</span>
                <span class="ainy-dashboard-kpi-value"><?php echo number_format($sys_total_posts); ?></span>
            </div>
            <div class="ainy-dashboard-kpi-card">
                <span class="ainy-dashboard-kpi-name">固定ページ</span>
                <span class="ainy-dashboard-kpi-value"><?php echo number_format($sys_total_pages); ?></span>
            </div>
        </div>
    </section>
</div>

<?php get_footer(); ?>
