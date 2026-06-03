<?php
/*
Template Name: 管理用アクティブデータ一覧
 */
require_once get_template_directory() . '/functions/common/auth-middleware.php';
$auth_result = AidUniteAuthMiddleware::require_admin(true);
if (!$auth_result->is_valid()) {
    return;
}

$segment_filter = isset($_GET['segment']) ? sanitize_key(wp_unslash($_GET['segment'])) : '';
$report = aidunite_analytics_get_active_teams_report($segment_filter, 150);
$summary = $report['summary'] ?? [];
$labels = aidunite_analytics_team_segment_labels();
$base_url = get_permalink();

get_header();
?>
<div class="admin-analytics-wrap">
    <?php aidunite_analytics_render_admin_nav('active'); ?>
    <?php aidunite_analytics_render_page_header(
        'アクティブデータ一覧',
        'チームの活動区分と明細です。最終活動は schedule / マッチ申請から判定しています。'
    ); ?>

    <section class="admin-analytics-section">
        <h2 class="admin-analytics-section__title">区分サマリ（公開チーム <?php echo (int) ($report['total_publish_teams'] ?? 0); ?>）</h2>
        <div class="admin-analytics-kpi-grid">
            <?php foreach ($labels as $key => $label) : ?>
            <?php
            $count = (int) ($summary[$key] ?? 0);
            $url = add_query_arg('segment', $key, $base_url);
            ?>
            <a href="<?php echo esc_url($url); ?>" class="admin-analytics-kpi-card" style="text-decoration:none;color:inherit;">
                <span class="admin-analytics-kpi-card__label"><?php echo esc_html($label); ?></span>
                <span class="admin-analytics-kpi-card__value"><?php echo esc_html(number_format($count)); ?></span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php if ($segment_filter !== '') : ?>
        <p><a href="<?php echo esc_url($base_url); ?>">フィルタ解除</a> — 表示中: <?php echo esc_html($labels[$segment_filter] ?? $segment_filter); ?></p>
        <?php endif; ?>
    </section>

    <section class="admin-analytics-section">
        <h2 class="admin-analytics-section__title">チーム明細</h2>
        <div style="overflow-x:auto;">
            <table class="admin-analytics-table">
                <thead>
                    <tr>
                        <th class="col-no">No</th>
                        <th>チーム名</th>
                        <th>ID</th>
                        <th>区分</th>
                        <th>最終活動</th>
                        <th>募集</th>
                        <th>申請</th>
                        <th>成立</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($report['teams'])) : ?>
                    <tr><td colspan="8">該当なし</td></tr>
                    <?php else : ?>
                    <?php foreach ($report['teams'] as $i => $row) : ?>
                    <?php $f = $row['flags'] ?? []; ?>
                    <tr>
                        <td class="col-no"><?php echo (int) ($i + 1); ?></td>
                        <td><?php echo esc_html($row['team_name']); ?></td>
                        <td><?php echo (int) $row['team_id']; ?></td>
                        <td>
                            <?php
                            $seg = $row['segment'] ?? '';
                            $badge = $seg === 'high_value' ? 'high' : ($seg === 'dormant_risk' ? 'warn' : 'muted');
                            ?>
                            <span class="admin-analytics-badge admin-analytics-badge--<?php echo esc_attr($badge); ?>">
                                <?php echo esc_html($row['segment_label']); ?>
                            </span>
                        </td>
                        <td><?php echo $row['last_activity_at'] ? esc_html(substr($row['last_activity_at'], 0, 10)) : '—'; ?></td>
                        <td><?php echo !empty($f['has_recruit']) ? '○' : '—'; ?></td>
                        <td><?php echo !empty($f['has_application']) ? '○' : '—'; ?></td>
                        <td><?php echo !empty($f['has_established']) ? '○' . (int) ($f['established_count'] ?? 0) : '—'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php get_footer(); ?>
