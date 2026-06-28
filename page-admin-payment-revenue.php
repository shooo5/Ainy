<?php
/*
Template Name: 管理用売上・見込み
 *
 * 使い方: 固定ページを新規作成し、スラッグを「admin-payment-revenue」に、
 * テンプレートで「管理用売上・見込み」を選択してください。
 */
require_once get_template_directory() . '/functions/common/auth-middleware.php';
$auth_result = AidUniteAuthMiddleware::require_admin(true);
if (!$auth_result->is_valid()) {
    return;
}

require_once get_template_directory() . '/functions/payment/payment-config.php';
require_once get_template_directory() . '/functions/payment/admin-payment-metrics.php';
require_once get_template_directory() . '/functions/dashboard/admin-payment-revenue.php';

$force_refresh = isset($_GET['refresh']) && sanitize_key(wp_unslash($_GET['refresh'])) === '1';
$report = function_exists('aidunite_admin_revenue_build_report')
    ? aidunite_admin_revenue_build_report(['force_refresh' => $force_refresh])
    : [];

$current = (array) ($report['current_month'] ?? []);
$stripe = (array) ($report['stripe'] ?? []);
$system_mrr = (array) ($report['system_mrr'] ?? []);
$tuition_mrr = (array) ($report['tuition_mrr'] ?? []);
$monthly_rows = (array) ($report['monthly_rows'] ?? []);
$base_url = get_permalink();
$refresh_url = add_query_arg('refresh', '1', $base_url);

get_header();
?>
<div class="admin-analytics-wrap page-admin-payment-revenue">
    <?php
    if (function_exists('aidunite_analytics_render_admin_nav')) {
        aidunite_analytics_render_admin_nav('revenue');
    }
    if (function_exists('aidunite_analytics_render_page_header')) {
        aidunite_analytics_render_page_header(
            '売上・見込み',
            'システム料は Stripe 実績と WP メタの MRR 見込み、月謝は Connect 実績（WP 履歴）とサブスク見込みを表示します。トライアル中のチームは見込みに含めません。法人契約も月額計算は個人契約と同じです（契約形態は表示のみ）。'
        );
    }
    ?>

    <?php if (!$stripe['available'] && ($stripe['error'] ?? '') !== '') : ?>
    <p class="admin-analytics-note admin-payment-revenue__stripe-warning" role="status">
        Stripe 実績: <?php echo esc_html((string) $stripe['error']); ?>（WP ベースの見込みのみ表示）
    </p>
    <?php endif; ?>

    <section class="admin-analytics-section">
        <div class="admin-payment-revenue__toolbar">
            <p class="admin-analytics-section__desc">
                更新: <?php echo esc_html((string) ($report['generated_at'] ?? '')); ?>
                <?php if (!empty($stripe['available'])) : ?>
                · Stripe 請求 <?php echo (int) ($stripe['invoice_count'] ?? 0); ?> 件を集計
                <?php endif; ?>
            </p>
            <a class="button" href="<?php echo esc_url($refresh_url); ?>">Stripe 実績を再取得</a>
        </div>

        <h2 class="admin-analytics-section__title">今月サマリ（<?php echo esc_html((string) ($current['label'] ?? '')); ?>）</h2>
        <div class="admin-analytics-kpi-grid admin-payment-revenue__kpi-grid">
            <?php
            if (function_exists('aidunite_analytics_render_kpi_card')) {
                aidunite_analytics_render_kpi_card(
                    'システム料（実績）',
                    aidunite_admin_revenue_format_yen((int) ($current['system_actual'] ?? 0)),
                    'Stripe paid Invoice'
                );
                aidunite_analytics_render_kpi_card(
                    '月謝（実績・総額）',
                    aidunite_admin_revenue_format_yen((int) ($current['tuition_gross'] ?? 0)),
                    'Connect 決済履歴'
                );
                aidunite_analytics_render_kpi_card(
                    '月謝（Ainy手数料）',
                    aidunite_admin_revenue_format_yen((int) ($current['tuition_ainy_fee'] ?? 0)),
                    '実績ベース'
                );
                aidunite_analytics_render_kpi_card(
                    'MRR見込・システム料',
                    aidunite_admin_revenue_format_yen((int) ($system_mrr['total'] ?? 0)),
                    (int) ($system_mrr['team_count'] ?? 0) . ' チーム（トライアル除外）'
                );
                aidunite_analytics_render_kpi_card(
                    'MRR見込・月謝手数料',
                    aidunite_admin_revenue_format_yen((int) ($tuition_mrr['total_ainy_fee'] ?? 0)),
                    (int) ($tuition_mrr['subscriber_count'] ?? 0) . ' 保護者サブスク'
                );
                aidunite_analytics_render_kpi_card(
                    'MRR見込・合計',
                    aidunite_admin_revenue_format_yen((int) ($report['mrr_total'] ?? 0)),
                    'システム料＋月謝手数料'
                );
            }
            ?>
        </div>
    </section>

    <section class="admin-analytics-section">
        <h2 class="admin-analytics-section__title">月次推移（直近12ヶ月）</h2>
        <div class="admin-payment-revenue__table-wrap">
            <table class="admin-analytics-table admin-payment-revenue__table">
                <thead>
                    <tr>
                        <th>月</th>
                        <th class="col-num">システム料実績</th>
                        <th class="col-num">月謝実績（総額）</th>
                        <th class="col-num">月謝 Ainy手数料</th>
                        <th class="col-num">実績合計</th>
                        <th class="col-num">月謝件数</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($monthly_rows === []) : ?>
                    <tr><td colspan="6">データなし</td></tr>
                    <?php else : ?>
                    <?php foreach ($monthly_rows as $row) : ?>
                    <tr<?php echo !empty($row['is_current']) ? ' class="is-current-month"' : ''; ?>>
                        <td><?php echo esc_html((string) ($row['label'] ?? '')); ?></td>
                        <td class="col-num"><?php echo esc_html(aidunite_admin_revenue_format_yen((int) ($row['system_actual'] ?? 0))); ?></td>
                        <td class="col-num"><?php echo esc_html(aidunite_admin_revenue_format_yen((int) ($row['tuition_gross'] ?? 0))); ?></td>
                        <td class="col-num"><?php echo esc_html(aidunite_admin_revenue_format_yen((int) ($row['tuition_ainy_fee'] ?? 0))); ?></td>
                        <td class="col-num"><?php echo esc_html(aidunite_admin_revenue_format_yen((int) ($row['actual_total'] ?? 0))); ?></td>
                        <td class="col-num"><?php echo (int) ($row['tuition_paid_count'] ?? 0); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <p class="admin-analytics-note">MRR 見込みは当月時点のスナップショットです（上表の過去月には反映しません）。</p>
    </section>

    <section class="admin-analytics-section">
        <h2 class="admin-analytics-section__title">MRR 内訳：システム料（<?php echo (int) ($system_mrr['team_count'] ?? 0); ?> チーム）</h2>
        <div class="admin-payment-revenue__table-wrap">
            <table class="admin-analytics-table">
                <thead>
                    <tr>
                        <th>チーム</th>
                        <th>プラン</th>
                        <th>契約状態</th>
                        <th>契約形態</th>
                        <th class="col-num">月額見込</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($system_mrr['rows'])) : ?>
                    <tr><td colspan="5">対象チームなし（トライアル中・未課金は除外）</td></tr>
                    <?php else : ?>
                    <?php foreach ((array) $system_mrr['rows'] as $row) : ?>
                    <tr>
                        <td>
                            <a href="<?php echo esc_url(home_url('/admin-payment-list?apl_tab=teams&team_q=' . rawurlencode((string) ($row['team_name'] ?? '')))); ?>">
                                <?php echo esc_html((string) ($row['team_name'] ?? '')); ?>
                            </a>
                        </td>
                        <td><?php echo esc_html(strtoupper((string) ($row['product_plan'] ?? 'match'))); ?></td>
                        <td><?php echo esc_html((string) ($row['contract_label'] ?? '')); ?></td>
                        <td><?php echo esc_html((string) ($row['contract_mode_label'] ?? '')); ?></td>
                        <td class="col-num"><?php echo esc_html(aidunite_admin_revenue_format_yen((int) ($row['monthly_fee'] ?? 0))); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="admin-analytics-section">
        <h2 class="admin-analytics-section__title">MRR 内訳：月謝サブスク（<?php echo (int) ($tuition_mrr['subscriber_count'] ?? 0); ?> 件）</h2>
        <div class="admin-payment-revenue__table-wrap">
            <table class="admin-analytics-table">
                <thead>
                    <tr>
                        <th>チーム</th>
                        <th>保護者</th>
                        <th>当月状態</th>
                        <th class="col-num">月謝</th>
                        <th class="col-num">Ainy手数料見込</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tuition_mrr['rows'])) : ?>
                    <tr><td colspan="5">サブスク登録済みの保護者なし</td></tr>
                    <?php else : ?>
                    <?php foreach ((array) $tuition_mrr['rows'] as $row) : ?>
                    <tr>
                        <td>
                            <a href="<?php echo esc_url(home_url('/admin-payment-list?apl_tab=tuition&team_q=' . rawurlencode((string) ($row['team_name'] ?? '')))); ?>">
                                <?php echo esc_html((string) ($row['team_name'] ?? '')); ?>
                            </a>
                        </td>
                        <td><?php echo esc_html((string) ($row['parent_name'] ?? '')); ?></td>
                        <td><?php echo esc_html((string) ($row['month_status_label'] ?? '')); ?></td>
                        <td class="col-num"><?php echo esc_html(aidunite_admin_revenue_format_yen((int) ($row['monthly_fee'] ?? 0))); ?></td>
                        <td class="col-num"><?php echo esc_html(aidunite_admin_revenue_format_yen((int) ($row['ainy_fee'] ?? 0))); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <p class="admin-analytics-note">
            <a href="<?php echo esc_url(home_url('/admin-payment-list?apl_tab=tuition')); ?>">決済一覧（保護者タブ）</a>
            ·
            <a href="<?php echo esc_url(home_url('/admin-payment-management')); ?>">決済管理</a>
        </p>
    </section>
</div>
<?php get_footer(); ?>
