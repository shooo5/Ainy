<?php
/*
Template Name: 管理用成立ファネル一覧
 */
require_once get_template_directory() . '/functions/common/auth-middleware.php';
$auth_result = AidUniteAuthMiddleware::require_admin(true);
if (!$auth_result->is_valid()) {
    return;
}

$funnel = aidunite_analytics_get_team_funnel_summary();
$reference = $funnel['reference'] ?? [];
$groups = $funnel['groups'] ?? [];

$drill_step = isset($_GET['step']) ? sanitize_key(wp_unslash($_GET['step'])) : '';
$drill_teams = $drill_step !== '' ? aidunite_analytics_get_team_ids_for_funnel_step($drill_step, 200) : [];
$base_url = get_permalink();

get_header();
?>
<div class="admin-analytics-wrap page-admin-team-funnel-wrap">
    <?php aidunite_analytics_render_admin_nav('funnel'); ?>
    <?php aidunite_analytics_render_page_header(
        '成立ファネル一覧',
        'チーム単位の到達状況です。①オンボーディングと②マッチングに分けて表示します（プロフィール完了は含みません）。'
    ); ?>

    <section class="admin-analytics-section">
        <h2 class="admin-analytics-section__title">参考指標（ファネル外）</h2>
        <div class="admin-analytics-kpi-grid">
            <?php aidunite_analytics_render_kpi_card('代表者アカウント', number_format((int) ($reference['user_accounts'] ?? 0))); ?>
            <?php aidunite_analytics_render_kpi_card('承認済みチーム', number_format((int) ($reference['teams_active'] ?? 0))); ?>
            <?php aidunite_analytics_render_kpi_card('成立経験チーム', number_format((int) ($reference['teams_established'] ?? 0))); ?>
            <?php aidunite_analytics_render_kpi_card('高価値に近いチーム', number_format((int) ($reference['teams_high_value'] ?? 0)), '募集・申請・成立'); ?>
        </div>
    </section>

    <?php foreach ($groups as $group) : ?>
    <section class="admin-analytics-section">
        <h2 class="admin-analytics-section__title"><?php echo esc_html($group['title']); ?></h2>
        <p class="admin-analytics-section__desc"><?php echo esc_html($group['description']); ?></p>
        <div style="overflow-x:auto;">
            <table class="admin-analytics-table">
                <thead>
                    <tr>
                        <th class="col-no">No</th>
                        <th>段階</th>
                        <th class="col-num">到達チーム数</th>
                        <th class="col-num">前段階比</th>
                        <th class="col-num">離脱率</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($group['steps'] as $i => $step) : ?>
                    <tr>
                        <td class="col-no"><?php echo (int) ($i + 1); ?></td>
                        <td><?php echo esc_html($step['label']); ?></td>
                        <td class="col-num"><strong><?php echo esc_html(number_format((int) $step['count'])); ?></strong></td>
                        <td class="col-num">
                            <?php
                            if ($step['step_rate'] === null) {
                                echo !empty($step['rate_note']) ? esc_html($step['rate_note']) : '—';
                            } else {
                                echo esc_html(number_format((float) $step['step_rate'], 1) . '%');
                            }
                            ?>
                        </td>
                        <td class="col-num">
                            <?php echo $step['leave_rate'] === null ? '—' : esc_html(number_format((float) $step['leave_rate'], 1) . '%'); ?>
                        </td>
                        <td><a href="<?php echo esc_url(add_query_arg('step', $step['id'], $base_url)); ?>">チーム一覧</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endforeach; ?>

    <?php if ($drill_step !== '') : ?>
    <?php
    $step_label = $drill_step;
    foreach ($funnel['steps'] ?? [] as $s) {
        if ($s['id'] === $drill_step) {
            $step_label = $s['label'];
            break;
        }
    }
    ?>
    <section class="admin-analytics-section">
        <h2 class="admin-analytics-section__title">「<?php echo esc_html($step_label); ?>」のチーム一覧</h2>
        <p class="admin-analytics-section__desc">
            最大200件。
            <a href="<?php echo esc_url(remove_query_arg('step', $base_url)); ?>">ファネルに戻る</a>
        </p>
        <div style="overflow-x:auto;">
            <table class="admin-analytics-table">
                <thead>
                    <tr>
                        <th class="col-no">No</th>
                        <th>チーム名</th>
                        <th>チームID</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($drill_teams)) : ?>
                    <tr><td colspan="4">該当なし</td></tr>
                    <?php else : ?>
                    <?php foreach ($drill_teams as $idx => $team_id) : ?>
                    <?php
                    $team_id = (int) $team_id;
                    $team_display = function_exists('aidunite_team_get_display_bundle')
                        ? aidunite_team_get_display_bundle($team_id)
                        : [];
                    $team_name = (string) ($team_display['team_name'] ?? '');
                    if ($team_name === '') {
                        $p = get_post($team_id);
                        $team_name = $p ? $p->post_title : ('#' . $team_id);
                    }
                    ?>
                    <tr>
                        <td class="col-no"><?php echo (int) ($idx + 1); ?></td>
                        <td><?php echo esc_html($team_name); ?></td>
                        <td><?php echo (int) $team_id; ?></td>
                        <td><a href="<?php echo esc_url(home_url('/team-management')); ?>">チーム管理</a></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>
</div>
<?php get_footer(); ?>
