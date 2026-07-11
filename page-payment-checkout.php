<?php
/**
 * Template Name: カード登録（Embedded Checkout）
 */

$auth_result = AidUniteAuthMiddleware::require_auth(true);
if (!$auth_result->is_valid()) {
    return;
}

$user_id = (int) $auth_result->user_id;
$team_id = function_exists('aidunite_user_read_primary_team_id')
    ? (int) aidunite_user_read_primary_team_id($user_id)
    : (int) ($auth_result->team_id ?? 0);

if ($team_id <= 0) {
    get_header();
    echo '<div class="page-container payment-checkout-page"><div class="payment-checkout-alert" role="alert"><p>チームが見つかりません。</p></div></div>';
    get_footer();
    return;
}

$vm = function_exists('aidunite_payment_read_checkout_page_model')
    ? aidunite_payment_read_checkout_page_model($team_id, $user_id)
    : [];

if (empty($vm['can_access_checkout'])) {
    wp_safe_redirect(home_url('/payment-setup'));
    exit;
}

$stripe_keys = function_exists('aidunite_get_stripe_keys') ? aidunite_get_stripe_keys() : [];
$publishable_key = (string) ($stripe_keys['publishable_key'] ?? '');

$shell_args = [
    'page_class' => 'page-payment-checkout payment-checkout-page',
    'title' => 'カード登録',
    'subtitle' => (string) ($vm['card_registration_note'] ?? '今すぐ課金はされません'),
    'back' => true,
    'back_url' => (string) ($vm['payment_setup_url'] ?? home_url('/payment-setup')),
    'active_nav' => 'none',
];

if (function_exists('aidunite_web_app_page_prepare_hero_shell_body_class')) {
    aidunite_web_app_page_prepare_hero_shell_body_class();
}

get_header();

$checkout_shell_mode = function_exists('aidunite_web_app_page_shell_begin')
    ? aidunite_web_app_page_shell_begin($shell_args, [
        'legacy_container_class' => 'page-container payment-checkout-page',
        'legacy_back_url' => (string) ($vm['payment_setup_url'] ?? home_url('/payment-setup')),
        'legacy_back_label' => '契約・お支払いに戻る',
    ])
    : 'legacy';
?>

<div class="payment-checkout-content<?php echo $checkout_shell_mode !== 'legacy' ? ' payment-checkout-content--shell' : ''; ?>">
    <div class="payment-checkout-layout">
        <?php
        get_template_part('template-parts/payment/checkout-plan', 'summary', [
            'vm' => $vm,
        ]);
        ?>

        <section class="payment-checkout-embedded-panel" aria-labelledby="payment-checkout-form-title">
            <h2 id="payment-checkout-form-title" class="payment-checkout-embedded-panel__title">お支払い方法の登録</h2>
            <p class="payment-checkout-embedded-panel__lead"><?php echo esc_html((string) ($vm['card_registration_note'] ?? '今すぐ課金はされません')); ?></p>

            <div id="payment-checkout-embedded" class="payment-checkout-embedded-mount" aria-live="polite">
                <p class="payment-checkout-loading">決済フォームを読み込み中...</p>
            </div>

            <div id="payment-checkout-error" class="payment-checkout-alert payment-checkout-alert--error" role="alert" hidden></div>
        </section>
    </div>
</div>

<?php
if (function_exists('aidunite_web_app_page_shell_end')) {
    aidunite_web_app_page_shell_end($checkout_shell_mode);
} elseif ($checkout_shell_mode === 'legacy') {
    echo '</div>';
}

wp_localize_script('aidunite-payment-checkout', 'aidunitePaymentCheckoutPage', [
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'paymentNonce' => wp_create_nonce('aidunite_payment_nonce'),
    'publishableKey' => $publishable_key,
    'paymentSetupUrl' => (string) ($vm['payment_setup_url'] ?? home_url('/payment-setup')),
    'uiMode' => 'embedded',
]);

get_footer();
