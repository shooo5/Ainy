<?php
/*
Template Name: 管理用決済一覧
 *
 * 使い方: 固定ページを新規作成し、スラッグを「admin-payment-list」に、
 * テンプレートで「管理用決済一覧」を選択してください。
 */
require_once get_template_directory() . '/functions/common/auth-middleware.php';
$auth_result = AidUniteAuthMiddleware::require_admin(true);
if (!$auth_result->is_valid()) {
    return;
}

require_once get_template_directory() . '/functions/payment/payment-config.php';
require_once get_template_directory() . '/functions/payment/admin-payment-list.php';
require_once get_template_directory() . '/functions/common/admin-list-display.php';

$active_tab = aidunite_admin_payment_list_normalize_tab(
    isset($_GET['apl_tab']) ? sanitize_key(wp_unslash($_GET['apl_tab'])) : 'teams'
);
$list_page_var = 'apl_paged';
$per_page = 25;

$contract_filter = isset($_GET['contract_filter']) ? sanitize_key(wp_unslash($_GET['contract_filter'])) : '';
$tuition_status_filter = isset($_GET['tuition_status_filter']) ? sanitize_key(wp_unslash($_GET['tuition_status_filter'])) : '';
$team_q = isset($_GET['team_q']) ? sanitize_text_field(wp_unslash($_GET['team_q'])) : '';

$allowed_contract_filters = ['', 'trial', 'paid', 'active', 'unpaid', 'cancelling', 'cancelled', 'inactive'];
if (!in_array($contract_filter, $allowed_contract_filters, true)) {
    $contract_filter = '';
}

$allowed_tuition_filters = ['', 'paid', 'pending_billing', 'overdue', 'failed', 'not_registered', 'disabled'];
if (!in_array($tuition_status_filter, $allowed_tuition_filters, true)) {
    $tuition_status_filter = '';
}

$total_count = 0;
$total_pages = 1;
$current_page = 1;
$offset = 0;
$teams_page = [];
$tuition_page = [];
$month_label = '';
$stripe_vm = [];

$all_team_ids = aidunite_admin_payment_list_collect_team_ids();
$all_tuition_rows_cached = aidunite_admin_payment_list_collect_tuition_rows();
$tab_counts = [
    'teams' => count($all_team_ids),
    'tuition' => count($all_tuition_rows_cached),
];

if ($active_tab === 'teams') {
    $all_team_rows = [];
    foreach ($all_team_ids as $team_id) {
        if (function_exists('aidunite_payment_sync_team_platform_subscription_from_stripe')
            && function_exists('aidunite_payment_read_team_stripe_subscription_id')
            && aidunite_payment_read_team_stripe_subscription_id($team_id) === '') {
            aidunite_payment_sync_team_platform_subscription_from_stripe($team_id, 0);
        }
        $row = aidunite_admin_payment_list_team_row_data($team_id);
        if ($contract_filter !== '' && (string) ($row['contract_filter_key'] ?? '') !== $contract_filter) {
            continue;
        }
        if ($team_q !== '' && mb_stripos((string) ($row['team_name'] ?? ''), $team_q) === false) {
            continue;
        }
        $all_team_rows[] = $row;
    }
    $total_count = count($all_team_rows);
    $total_pages = $total_count > 0 ? (int) ceil($total_count / $per_page) : 1;
    $requested_page = isset($_GET[$list_page_var]) ? (int) $_GET[$list_page_var] : 0;
    $current_page = $requested_page > 0 ? max(1, min($requested_page, $total_pages)) : 1;
    $offset = ($current_page - 1) * $per_page;
    $teams_page = array_slice($all_team_rows, $offset, $per_page);
} elseif ($active_tab === 'tuition') {
    $all_tuition_rows = [];
    foreach ($all_tuition_rows_cached as $row) {
        if ($tuition_status_filter !== '' && (string) ($row['month_status'] ?? '') !== $tuition_status_filter) {
            continue;
        }
        if ($team_q !== '' && mb_stripos((string) ($row['team_name'] ?? ''), $team_q) === false) {
            continue;
        }
        $all_tuition_rows[] = $row;
    }
    if ($all_tuition_rows !== [] && $month_label === '') {
        $month_label = (string) ($all_tuition_rows[0]['month_label'] ?? '');
    }
    if ($month_label === '' && function_exists('aidunite_payment_read_current_month_window')) {
        $window = aidunite_payment_read_current_month_window();
        $month_label = (string) ($window['label'] ?? '');
    }
    $total_count = count($all_tuition_rows);
    $total_pages = $total_count > 0 ? (int) ceil($total_count / $per_page) : 1;
    $requested_page = isset($_GET[$list_page_var]) ? (int) $_GET[$list_page_var] : 0;
    $current_page = $requested_page > 0 ? max(1, min($requested_page, $total_pages)) : 1;
    $offset = ($current_page - 1) * $per_page;
    $tuition_page = array_slice($all_tuition_rows, $offset, $per_page);
} else {
    $stripe_vm = aidunite_admin_payment_list_stripe_summary();
}

$pagination_query = array_filter([
    'apl_tab' => $active_tab,
    'contract_filter' => $active_tab === 'teams' ? $contract_filter : '',
    'tuition_status_filter' => $active_tab === 'tuition' ? $tuition_status_filter : '',
    'team_q' => $team_q !== '' ? $team_q : '',
]);
$pagination_base = add_query_arg($pagination_query, get_permalink());

$founding_slots_remaining = function_exists('aidunite_payment_read_founding_slots_remaining')
    ? (int) aidunite_payment_read_founding_slots_remaining()
    : 0;

get_header();
?>

<div class="wrap page-admin-payment-list-wrap">
    <h1>決済一覧（管理者用）</h1>
    <p class="admin-payment-list-lead">
        課金ストリーム別に確認できます。
        <strong>チーム（システム料）</strong>は代表者→Ainy本体 Stripe、
        <strong>保護者（月謝）</strong>は保護者→チーム Connect Stripe です。
        金額設定は <a href="<?php echo esc_url(home_url('/admin-payment-management')); ?>">決済管理</a> から行ってください。
        Founding Team 残枠: <strong><?php echo (int) $founding_slots_remaining; ?></strong> チーム
    </p>

    <nav class="admin-payment-list-tabs" aria-label="決済一覧タブ">
        <a href="<?php echo esc_url(aidunite_admin_payment_list_tab_url('teams')); ?>"
            class="admin-payment-list-tabs__tab<?php echo $active_tab === 'teams' ? ' is-active' : ''; ?>"
            <?php echo $active_tab === 'teams' ? 'aria-current="page"' : ''; ?>>
            チーム（システム料）
            <span class="admin-payment-list-tabs__count"><?php echo (int) $tab_counts['teams']; ?></span>
        </a>
        <a href="<?php echo esc_url(aidunite_admin_payment_list_tab_url('tuition')); ?>"
            class="admin-payment-list-tabs__tab<?php echo $active_tab === 'tuition' ? ' is-active' : ''; ?>"
            <?php echo $active_tab === 'tuition' ? 'aria-current="page"' : ''; ?>>
            保護者（月謝）
            <span class="admin-payment-list-tabs__count"><?php echo (int) $tab_counts['tuition']; ?></span>
        </a>
        <a href="<?php echo esc_url(aidunite_admin_payment_list_tab_url('stripe')); ?>"
            class="admin-payment-list-tabs__tab<?php echo $active_tab === 'stripe' ? ' is-active' : ''; ?>"
            <?php echo $active_tab === 'stripe' ? 'aria-current="page"' : ''; ?>>
            Stripe連携
        </a>
    </nav>

    <?php if ($active_tab !== 'stripe') : ?>
    <p class="admin-payment-list-result-count">
        全 <?php echo (int) $total_count; ?> 件
        <?php if ($total_pages > 1) : ?>
            （<?php echo (int) ($offset + 1); ?> - <?php echo (int) min($offset + $per_page, $total_count); ?> 件目）
        <?php endif; ?>
    </p>

    <?php if ($total_pages > 1) : ?>
    <nav class="admin-payment-list-pagination" aria-label="ページ送り">
        <?php
        echo paginate_links([
            'base' => $pagination_base . '%_%',
            'format' => '?' . $list_page_var . '=%#%',
            'current' => $current_page,
            'total' => $total_pages,
            'prev_text' => '&laquo; 前へ',
            'next_text' => '次へ &raquo;',
            'add_args' => $pagination_query,
        ]);
        ?>
    </nav>
    <?php endif; ?>
    <?php endif; ?>

    <?php
    if ($active_tab === 'teams') {
        get_template_part('template-parts/payment/admin-payment-list', 'teams', [
            'teams_page' => $teams_page,
            'offset' => $offset,
            'contract_filter' => $contract_filter,
            'team_q' => $team_q,
        ]);
    } elseif ($active_tab === 'tuition') {
        get_template_part('template-parts/payment/admin-payment-list', 'tuition', [
            'tuition_page' => $tuition_page,
            'offset' => $offset,
            'tuition_status_filter' => $tuition_status_filter,
            'team_q' => $team_q,
            'month_label' => $month_label,
        ]);
    } else {
        get_template_part('template-parts/payment/admin-payment-list', 'stripe', [
            'stripe_vm' => $stripe_vm,
        ]);
    }
    ?>

    <?php if ($active_tab !== 'stripe' && $total_pages > 1) : ?>
    <nav class="admin-payment-list-pagination admin-payment-list-pagination--bottom" aria-label="ページ送り（下）">
        <?php
        echo paginate_links([
            'base' => $pagination_base . '%_%',
            'format' => '?' . $list_page_var . '=%#%',
            'current' => $current_page,
            'total' => $total_pages,
            'prev_text' => '&laquo; 前へ',
            'next_text' => '次へ &raquo;',
            'add_args' => $pagination_query,
        ]);
        ?>
    </nav>
    <?php endif; ?>
</div>

<?php get_footer(); ?>
