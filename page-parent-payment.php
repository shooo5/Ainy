<?php
/**
 * Template Name: 保護者月謝支払いページ
 */

$auth_result = AidUniteAuthMiddleware::require_auth(true);
if (!$auth_result->is_valid()) {
    return;
}

$user_id = (int) $auth_result->user_id;
$team_id = function_exists('aidunite_user_read_primary_team_id')
    ? (int) aidunite_user_read_primary_team_id($user_id)
    : 0;

$user_canonical = function_exists('aidunite_user_get_canonical_meta')
    ? aidunite_user_get_canonical_meta($user_id)
    : [];
$user_role = (string) ($user_canonical['aidunite_role'] ?? '');

$theme_key = ($team_id > 0 && function_exists('aidunite_get_team_ui_theme_key'))
    ? (string) aidunite_get_team_ui_theme_key($team_id)
    : '';
if (!in_array($theme_key, ['boys', 'girls'], true)) {
    $theme_key = '';
}

if (function_exists('aidunite_enqueue_payment_setup_shared_styles')) {
    aidunite_enqueue_payment_setup_shared_styles();
}

if (function_exists('aidunite_web_app_page_prepare_hero_shell_body_class')) {
    aidunite_web_app_page_prepare_hero_shell_body_class();
}

get_header();

$shell_subtitle = '月謝のお支払い方法の登録と履歴の確認ができます';
$shell_args = [
    'page_class' => 'page-parent-payment parent-payment-page',
    'title' => '月謝のお支払い',
    'subtitle' => $shell_subtitle,
    'back' => true,
    'back_url' => home_url('/mypage'),
    'active_nav' => 'none',
    'team_theme' => $theme_key,
];

$shell_mode = function_exists('aidunite_web_app_page_shell_begin')
    ? aidunite_web_app_page_shell_begin($shell_args, [
        'legacy_container_class' => 'page-container parent-payment-page',
        'legacy_back_url' => home_url('/mypage'),
        'legacy_back_label' => 'マイページに戻る',
    ])
    : 'legacy';

$close_shell = static function () use ($shell_mode) {
    if (function_exists('aidunite_web_app_page_shell_end')) {
        aidunite_web_app_page_shell_end($shell_mode);
    } else {
        echo '</div>';
    }
};

$vm = [
    'tuition_open' => false,
    'has_subscription' => false,
    'tuition_registration_pending' => false,
    'checkout_button_label' => '支払い方法を登録する',
];

if ($team_id <= 0) {
    echo '<div class="payment-setup-section payment-setup-alert" role="alert"><p>チームが見つかりません。</p></div>';
    $close_shell();
    get_footer();
    return;
}

if (!in_array($user_role, ['parent', 'player'], true)) {
    echo '<div class="payment-setup-section payment-setup-alert" role="alert"><p>このページは保護者・選手のみアクセス可能です。</p></div>';
    $close_shell();
    get_footer();
    return;
}

$team_display = function_exists('aidunite_team_get_display_bundle')
    ? aidunite_team_get_display_bundle($team_id)
    : [];
$team_name = (string) ($team_display['team_name'] ?? '');
if ($team_name === '') {
    $team_post = get_post($team_id);
    $team_name = $team_post ? (string) $team_post->post_title : '';
}

$tuition_display = function_exists('aidunite_payment_read_tuition_display')
    ? aidunite_payment_read_tuition_display($team_id)
    : [];
$tuition_open = function_exists('aidunite_payment_team_tuition_open_for_parents')
    ? aidunite_payment_team_tuition_open_for_parents($team_id)
    : !empty($tuition_display['team_tuition_enabled']);
$connect_block_reason = function_exists('aidunite_payment_read_team_tuition_connect_block_reason')
    ? aidunite_payment_read_team_tuition_connect_block_reason($team_id)
    : '';

if (isset($_GET['payment']) && sanitize_key((string) wp_unslash($_GET['payment'])) === 'success') {
    $session_id = isset($_GET['session_id'])
        ? sanitize_text_field((string) wp_unslash($_GET['session_id']))
        : '';
    if ($session_id !== '' && function_exists('aidunite_payment_sync_tuition_from_checkout_session')) {
        aidunite_payment_sync_tuition_from_checkout_session($user_id, $team_id, $session_id);
    } elseif (function_exists('aidunite_payment_sync_parent_tuition_subscription_from_stripe')) {
        aidunite_payment_sync_parent_tuition_subscription_from_stripe($user_id, $team_id);
    }
}

$has_subscription = function_exists('aidunite_payment_parent_has_tuition_subscription')
    ? aidunite_payment_parent_has_tuition_subscription($user_id, $team_id)
    : false;
$payment_flash = function_exists('aidunite_payment_read_flash_from_query')
    ? aidunite_payment_read_flash_from_query()
    : null;
$tuition_registration_pending = is_array($payment_flash)
    && ($payment_flash['type'] ?? '') === 'success'
    && !$has_subscription;

$vm = [
    'team_logo' => (string) ($team_display['team_logo'] ?? ''),
    'team_name' => $team_name,
    'monthly_fee' => (int) ($tuition_display['team_monthly_fee'] ?? 0),
    'tuition_open' => $tuition_open,
    'connect_block_reason' => $connect_block_reason,
    'has_subscription' => $has_subscription,
    'tuition_registration_pending' => $tuition_registration_pending,
    'checkout_button_label' => $tuition_registration_pending
        ? '登録中'
        : ($has_subscription ? '支払い方法を変更する' : '支払い方法を登録する'),
];
?>

<div class="payment-setup-content<?php echo $shell_mode !== 'legacy' ? ' payment-setup-content--shell' : ''; ?>">
    <?php
    get_template_part('template-parts/payment/parent-payment', 'content', [
        'vm' => $vm,
        'payment_flash' => $payment_flash,
    ]);
    ?>
</div>

<?php
$close_shell();

aidunite_page_asset_localize('parent-payment', [
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'teamId' => (int) $team_id,
    'paymentNonce' => wp_create_nonce('aidunite_payment_nonce'),
    'checkoutButtonLabel' => (string) ($vm['checkout_button_label'] ?? ''),
    'checkoutPending' => $tuition_registration_pending,
]);
get_footer();
