<?php
/*
Template Name: 管理用AI月次分析レポート
 */
require_once get_template_directory() . '/functions/common/auth-middleware.php';
$auth_result = AidUniteAuthMiddleware::require_admin(true);
if (!$auth_result->is_valid()) {
    return;
}

$year = isset($_GET['year']) ? (int) $_GET['year'] : (int) current_time('Y');
$month = isset($_GET['month']) ? (int) $_GET['month'] : (int) current_time('n');
if ($month < 1 || $month > 12) {
    $month = (int) current_time('n');
}

if (isset($_POST['aidunite_generate_report']) && check_admin_referer('aidunite_ai_report_generate')) {
    $report = aidunite_analytics_build_monthly_report($year, $month);
    aidunite_analytics_save_monthly_report($report, $year, $month);
    wp_safe_redirect(add_query_arg(['year' => $year, 'month' => $month, 'generated' => '1'], get_permalink()));
    exit;
}

$saved = aidunite_analytics_get_saved_monthly_report($year, $month);
if (!$saved) {
    $saved = aidunite_analytics_build_monthly_report($year, $month);
}

$insights = $saved['insights'] ?? [];
$metrics = $saved['metrics'] ?? [];
$generated_at = $saved['generated_at'] ?? '';

get_header();
?>
<div class="admin-analytics-wrap">
    <?php aidunite_analytics_render_admin_nav('ai'); ?>
    <?php aidunite_analytics_render_page_header(
        'AI月次分析レポート',
        '数値はシステム集計の固定値です。以下はルールベースの示唆（外部AI APIは未接続）。'
    ); ?>

    <section class="admin-analytics-section">
        <h2 class="admin-analytics-section__title">対象月</h2>
        <form method="get" class="admin-analytics-filters">
            <div>
                <label for="year">年</label>
                <input type="number" name="year" id="year" value="<?php echo (int) $year; ?>" min="2024" max="2100">
            </div>
            <div>
                <label for="month">月</label>
                <input type="number" name="month" id="month" value="<?php echo (int) $month; ?>" min="1" max="12">
            </div>
            <button type="submit" class="button">表示</button>
        </form>
        <form method="post" style="margin-top:var(--spacing-base);">
            <?php wp_nonce_field('aidunite_ai_report_generate'); ?>
            <input type="hidden" name="aidunite_generate_report" value="1">
            <button type="submit" class="button button-primary">この月のレポートを再生成</button>
        </form>
        <?php if ($generated_at !== '') : ?>
        <p class="admin-analytics-section__desc">生成: <?php echo esc_html($generated_at); ?></p>
        <?php endif; ?>
        <?php if (isset($_GET['generated'])) : ?>
        <p class="admin-analytics-note">レポートを保存しました。</p>
        <?php endif; ?>
    </section>

    <section class="admin-analytics-section">
        <h2 class="admin-analytics-section__title">数値サマリ</h2>
        <div class="admin-analytics-kpi-grid">
            <?php aidunite_analytics_render_kpi_card('公開チーム', number_format((int) ($metrics['teams_publish'] ?? 0))); ?>
            <?php aidunite_analytics_render_kpi_card('高価値チーム', number_format((int) ($metrics['high_value_teams'] ?? 0))); ?>
            <?php aidunite_analytics_render_kpi_card('成立チーム', number_format((int) ($metrics['established_teams'] ?? 0))); ?>
        </div>
    </section>

    <section class="admin-analytics-section">
        <h2 class="admin-analytics-section__title">示唆（要約）</h2>
        <ul class="admin-analytics-insights">
            <?php foreach ($insights as $line) : ?>
            <li><?php echo esc_html($line); ?></li>
            <?php endforeach; ?>
        </ul>
    </section>
</div>
<?php get_footer(); ?>
