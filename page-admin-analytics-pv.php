<?php
/*
Template Name: 管理用PVデータ一覧
 */
require_once get_template_directory() . '/functions/common/auth-middleware.php';
$auth_result = AidUniteAuthMiddleware::require_admin(true);
if (!$auth_result->is_valid()) {
    return;
}

$granularity = isset($_GET['granularity']) ? sanitize_key(wp_unslash($_GET['granularity'])) : 'month';
if (!in_array($granularity, ['day', 'week', 'month'], true)) {
    $granularity = 'month';
}
$limit = $granularity === 'day' ? 30 : ($granularity === 'week' ? 12 : 12);
$pv_report = aidunite_analytics_get_pv_period_report($granularity, $limit);

$to = current_time('Y-m-d');
$from = date('Y-m-d', strtotime('-29 days', strtotime($to . ' 00:00:00')));
$page_rows = aidunite_analytics_get_pv_by_page_report($from, $to);
$board_cv = aidunite_analytics_get_match_board_cv_summary($from, $to);

$base_url = get_permalink();

get_header();
?>
<div class="admin-analytics-wrap">
    <?php aidunite_analytics_render_admin_nav('pv'); ?>
    <?php aidunite_analytics_render_page_header(
        'PVデータ一覧',
        '補助指標です。日・週・月で期間集計。行動到達率（ユニークチーム・CTA）はイベント蓄積後に拡張します。'
    ); ?>

    <section class="admin-analytics-section">
        <h2 class="admin-analytics-section__title">心臓指標（直近30日・イベント）</h2>
        <div class="admin-analytics-kpi-grid">
            <?php aidunite_analytics_render_kpi_card('board閲覧チーム', number_format((int) ($board_cv['board_view_teams'] ?? 0))); ?>
            <?php aidunite_analytics_render_kpi_card('申請アクション', number_format((int) ($board_cv['apply_teams'] ?? 0))); ?>
            <?php
            $cv_label = $board_cv['cv_rate'] !== null ? number_format((float) $board_cv['cv_rate'], 1) . '%' : '—';
            aidunite_analytics_render_kpi_card('閲覧→申請CV', $cv_label);
            ?>
        </div>
        <p class="admin-analytics-note">申請ボタンに <code>data-analytics-target="match_apply"</code> を付与すると計測精度が上がります。</p>
    </section>

    <section class="admin-analytics-section">
        <h2 class="admin-analytics-section__title">期間別 PV</h2>
        <form method="get" class="admin-analytics-filters">
            <div>
                <label for="granularity">集計単位</label>
                <select name="granularity" id="granularity">
                    <option value="month" <?php selected($granularity, 'month'); ?>>月次</option>
                    <option value="week" <?php selected($granularity, 'week'); ?>>週次</option>
                    <option value="day" <?php selected($granularity, 'day'); ?>>日次</option>
                </select>
            </div>
            <button type="submit" class="button button-primary">表示</button>
        </form>
        <div style="overflow-x:auto;">
            <table class="admin-analytics-table">
                <thead>
                    <tr>
                        <th class="col-no">No</th>
                        <th>期間</th>
                        <th class="col-num">合計PV</th>
                        <th class="col-num">平均滞在</th>
                        <th class="col-num">前月比</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pv_report['rows'])) : ?>
                    <tr><td colspan="5">データなし</td></tr>
                    <?php else : ?>
                    <?php foreach ($pv_report['rows'] as $i => $row) : ?>
                    <tr>
                        <td class="col-no"><?php echo (int) ($i + 1); ?></td>
                        <td><?php echo esc_html($row['period_label']); ?></td>
                        <td class="col-num"><?php echo esc_html(number_format((int) $row['views'])); ?></td>
                        <td class="col-num"><?php echo esc_html((int) $row['avg_sec']); ?>秒</td>
                        <td class="col-num"><?php echo $row['mom_pct'] !== null ? esc_html($row['mom_pct'] . '%') : '—'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="admin-analytics-section">
        <h2 class="admin-analytics-section__title">ページ別（直近30日）</h2>
        <div style="overflow-x:auto;">
            <table class="admin-analytics-table">
                <thead>
                    <tr>
                        <th class="col-no">No</th>
                        <th>ページ</th>
                        <th>page_key</th>
                        <th class="col-num">PV</th>
                        <th class="col-num">平均滞在</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($page_rows)) : ?>
                    <tr><td colspan="5">データなし</td></tr>
                    <?php else : ?>
                    <?php foreach ($page_rows as $i => $row) : ?>
                    <tr>
                        <td class="col-no"><?php echo (int) ($i + 1); ?></td>
                        <td><?php echo esc_html($row['label']); ?></td>
                        <td><code><?php echo esc_html($row['page_key']); ?></code></td>
                        <td class="col-num"><?php echo esc_html(number_format((int) $row['views'])); ?></td>
                        <td class="col-num"><?php echo esc_html((int) $row['avg_sec']); ?>秒</td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php get_footer(); ?>
