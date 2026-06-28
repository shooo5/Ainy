<?php
/**
 * Template Name: 契約・お支払いページ
 * 契約状況の確認・プラン変更・お支払い方法の管理
 */

if (function_exists('aidunite_web_app_page_prepare_hero_shell_body_class')) {
    aidunite_web_app_page_prepare_hero_shell_body_class();
}

get_header();

$auth_result = AidUniteAuthMiddleware::require_auth(true);
if (!$auth_result->is_valid()) {
    return;
}

$user_id = (int) $auth_result->user_id;
$team_id = function_exists('aidunite_user_read_primary_team_id')
    ? (int) aidunite_user_read_primary_team_id($user_id)
    : (int) $auth_result->team_id;

$shell_subtitle = '契約状況の確認、プラン変更、お支払い方法の管理ができます';
$shell_args = [
    'page_class' => 'page-payment-setup payment-setup-page',
    'title' => '契約・お支払い',
    'subtitle' => $shell_subtitle,
    'back' => true,
    'back_url' => home_url('/team-settings'),
    'active_nav' => 'none',
];

$payment_setup_shell_mode = function_exists('aidunite_web_app_page_shell_begin')
    ? aidunite_web_app_page_shell_begin($shell_args, [
        'legacy_container_class' => 'page-container payment-setup-page',
        'legacy_back_url' => home_url('/team-settings'),
        'legacy_back_label' => 'チーム設定に戻る',
    ])
    : 'legacy';

if (empty($team_id)) {
    echo '<div class="payment-setup-section payment-setup-alert" role="alert"><p>チームが見つかりません。</p></div>';
    if (function_exists('aidunite_web_app_page_shell_end')) {
        aidunite_web_app_page_shell_end($payment_setup_shell_mode);
    } else {
        echo '</div>';
    }
    get_footer();
    return;
}

if (
    function_exists('aidunite_payment_exit_snooze_first_match_prompt_payment_setup')
    && function_exists('aidunite_get_effective_user_role')
) {
    list($setup_role,) = aidunite_get_effective_user_role($user_id);
    if ($setup_role === 'team_leader') {
        aidunite_payment_exit_snooze_first_match_prompt_payment_setup($team_id);
    }
}

if (
    isset($_GET['upgrade']) && $_GET['upgrade'] === 'club'
    && function_exists('aidunite_payment_persist_upgrade_to_club')
    && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? '')), 'aidunite_upgrade_club_' . $team_id)
) {
    aidunite_payment_persist_upgrade_to_club($team_id);
}

if (function_exists('aidunite_payment_migrate_team_legacy_meta')) {
    aidunite_payment_migrate_team_legacy_meta($team_id);
}

if (function_exists('aidunite_payment_maybe_sync_platform_subscription_for_setup_page')) {
    aidunite_payment_maybe_sync_platform_subscription_for_setup_page($user_id, $team_id);
}

$setup_payload = function_exists('aidunite_payment_read_setup_display')
    ? aidunite_payment_read_setup_display($team_id, $user_id)
    : [];

$vm = function_exists('aidunite_payment_read_setup_page_model')
    ? aidunite_payment_read_setup_page_model($team_id, $user_id, $setup_payload)
    : [];

$payment_flash = function_exists('aidunite_payment_read_flash_from_query')
    ? aidunite_payment_read_flash_from_query()
    : null;

$leader_transfer_checkout = is_array($setup_payload['leader_transfer_checkout'] ?? null)
    ? $setup_payload['leader_transfer_checkout']
    : null;
if (
    is_array($leader_transfer_checkout)
    && ($leader_transfer_checkout['checkout_deadline'] ?? '') !== ''
    && function_exists('aidunite_payment_format_date_display')
) {
    $leader_transfer_checkout['checkout_deadline'] = aidunite_payment_format_date_display(
        (string) $leader_transfer_checkout['checkout_deadline']
    );
}

$club_plan_required_banner = isset($_GET['reason'])
    && $_GET['reason'] === 'club_plan_required'
    && empty($vm['has_club_plan']);
?>

<div class="payment-setup-content<?php echo $payment_setup_shell_mode !== 'legacy' ? ' payment-setup-content--shell' : ''; ?>">
    <?php
    get_template_part('template-parts/payment/setup-page', 'content', [
        'vm' => $vm,
        'payment_flash' => $payment_flash,
        'leader_transfer_checkout' => $leader_transfer_checkout,
        'club_plan_required_banner' => $club_plan_required_banner,
    ]);
    ?>
</div>

<?php
if (function_exists('aidunite_web_app_page_shell_end')) {
    aidunite_web_app_page_shell_end($payment_setup_shell_mode);
} elseif ($payment_setup_shell_mode === 'legacy') {
    echo '</div>';
}

wp_localize_script('aidunite-payment-setup', 'aidunitePaymentSetupPage', [
    'features' => [
        'planChange' => true,
    ],
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'planSelectionNonce' => wp_create_nonce('aidunite_plan_selection_nonce'),
    'paymentNonce' => wp_create_nonce('aidunite_payment_nonce'),
    'restNonce' => wp_create_nonce('wp_rest'),
    'restBase' => rest_url('aidunite/v1/'),
    'teamId' => $team_id,
    'currentProductPlan' => (string) ($vm['product_plan'] ?? 'match'),
    'planInfoUrl' => (string) ($vm['plan_info_url'] ?? home_url('/plan-info')),
    'checkoutPageUrl' => home_url('/payment-checkout'),
]);

get_footer();
