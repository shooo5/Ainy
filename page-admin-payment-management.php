<?php
/*
Template Name: 管理用決済管理
 *
 * 使い方: 固定ページを新規作成し、スラッグを「admin-payment-management」に、
 * テンプレートで「管理用決済管理」を選択してください。
 */
require_once get_template_directory() . '/functions/common/auth-middleware.php';
$auth_result = AidUniteAuthMiddleware::require_admin(true);
if (!$auth_result->is_valid()) {
    return;
}

require_once get_stylesheet_directory() . '/functions/dashboard/ainy-dashboard-kpi.php';
require_once get_template_directory() . '/functions/payment/payment-config.php';

$payment_config = aidunite_get_payment_config();
$paid_count = function_exists('aidunite_dashboard_count_paid_users') ? aidunite_dashboard_count_paid_users() : 0;
$match_amount = (int) ($payment_config['match']['monthly_amount'] ?? 0);
$revenue_estimate = $paid_count * $match_amount;
$retention_ltv = function_exists('aidunite_dashboard_get_retention_ltv')
    ? aidunite_dashboard_get_retention_ltv($match_amount)
    : ['retention_months_avg' => 0, 'ltv_per_user' => 0, 'ltv_total' => 0, 'paid_count' => 0];

get_header();
?>

<div class="wrap page-admin-payment-management-wrap">
    <h1>決済管理</h1>
    <p style="color:var(--text-secondary); margin:0 0 var(--spacing-lg);">
        金額設定・プラン・Stripe を管理します。
        <a href="<?php echo esc_url(home_url('/admin-payment-list')); ?>">決済一覧</a>でユーザー別の決済状況を確認できます。
    </p>

    <?php
    set_query_var('aidunite_payment_management_paid_count', $paid_count);
    set_query_var('aidunite_payment_management_revenue_estimate', $revenue_estimate);
    set_query_var('aidunite_payment_management_retention_ltv', $retention_ltv);
    set_query_var('aidunite_payment_management_config', $payment_config);
    get_template_part('template-parts/admin/payment-management-panel');
    ?>
</div>

<?php get_footer(); ?>
