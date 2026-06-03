<?php
/*
Template Name: 管理用離脱分析一覧
 */
require_once get_template_directory() . '/functions/common/auth-middleware.php';
$auth_result = AidUniteAuthMiddleware::require_admin(true);
if (!$auth_result->is_valid()) {
    return;
}

$report = aidunite_analytics_get_churn_report();

get_header();
?>
<div class="admin-analytics-wrap">
    <?php aidunite_analytics_render_admin_nav('churn'); ?>
    <?php aidunite_analytics_render_page_header(
        '離脱分析一覧',
        'ファネル離脱とページ別の離脱・CVです。イベント計測が増えるほど精度が上がります。'
    ); ?>

    <?php foreach ($report['funnel_groups'] as $group) : ?>
    <section class="admin-analytics-section">
        <h2 class="admin-analytics-section__title"><?php echo esc_html($group['title']); ?> — 離脱</h2>
        <p class="admin-analytics-section__desc"><?php echo esc_html($group['description']); ?></p>
        <div style="overflow-x:auto;">
            <table class="admin-analytics-table">
                <thead>
                    <tr>
                        <th>段階</th>
                        <th class="col-num">到達</th>
                        <th class="col-num">離脱率</th>
                        <th>示唆</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($group['steps'] as $step) : ?>
                    <tr>
                        <td><?php echo esc_html($step['label']); ?></td>
                        <td class="col-num"><?php echo esc_html(number_format((int) $step['count'])); ?></td>
                        <td class="col-num">
                            <?php
                            if ($step['leave_rate'] === null) {
                                echo '—';
                            } else {
                                $lr = (float) $step['leave_rate'];
                                echo esc_html(number_format($lr, 1) . '%');
                            }
                            ?>
                        </td>
                        <td>
                            <?php
                            if ($step['leave_rate'] !== null && (float) $step['leave_rate'] >= 40) {
                                echo '<span class="admin-analytics-badge admin-analytics-badge--warn">要改善</span>';
                            } else {
                                echo '<span class="admin-analytics-badge admin-analytics-badge--muted">—</span>';
                            }
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endforeach; ?>

    <section class="admin-analytics-section">
        <h2 class="admin-analytics-section__title">ページ別（直近30日・イベント）</h2>
        <?php if (empty($report['has_events'])) : ?>
        <p class="admin-analytics-note">イベントデータがまだありません。閲覧が蓄積されると表示されます。</p>
        <?php else : ?>
        <div style="overflow-x:auto;">
            <table class="admin-analytics-table">
                <thead>
                    <tr>
                        <th>ページ</th>
                        <th class="col-num">view</th>
                        <th class="col-num">アクション</th>
                        <th class="col-num">CV率</th>
                        <th class="col-num">leave率</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report['page_dropoffs'] as $row) : ?>
                    <tr>
                        <td><?php echo esc_html($row['label']); ?> <code><?php echo esc_html($row['page_key']); ?></code></td>
                        <td class="col-num"><?php echo (int) $row['views']; ?></td>
                        <td class="col-num"><?php echo (int) $row['actions']; ?></td>
                        <td class="col-num"><?php echo esc_html($row['cv_rate']); ?>%</td>
                        <td class="col-num"><?php echo esc_html($row['leave_rate']); ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
</div>
<?php get_footer(); ?>
