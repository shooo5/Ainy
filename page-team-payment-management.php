<?php
/*
Template Name: チーム月謝管理
*/

$auth_result = AidUniteAuthMiddleware::require_team_leader(null, true);
if (!$auth_result->is_valid()) {
    return;
}

$user_id = (int) $auth_result->user_id;

$ctx = function_exists('aidunite_team_settings_resolve_context')
    ? aidunite_team_settings_resolve_context($user_id)
    : null;
if ($ctx instanceof WP_Error || !is_array($ctx)) {
    $error_message = $ctx instanceof WP_Error
        ? $ctx->get_error_message()
        : 'あなたの所属チームが見つかりません。';
    if (function_exists('aidunite_team_settings_redirect_denied')) {
        aidunite_team_settings_redirect_denied('context', $ctx instanceof WP_Error ? $ctx : new WP_Error('no_team', $error_message), $user_id);
    }
    wp_die(esc_html($error_message));
}

$team_id = (int) ($ctx['team_id'] ?? 0);
$team = $ctx['post'] ?? null;
if ($team_id <= 0 || !($team instanceof WP_Post)) {
    wp_die('チーム情報が見つかりません。');
}

$message = '';
$message_type = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aidunite_save_tuition_settings'])) {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'aidunite_save_tuition_settings_' . $team_id)) {
        $message = 'セキュリティチェックに失敗しました。ページを再読み込みしてください。';
        $message_type = 'error';
    } else {
        $enabled = isset($_POST['team_tuition_enabled']);
        $amount  = isset($_POST['team_monthly_fee']) ? (int) $_POST['team_monthly_fee'] : 0;

        if (aidunite_payment_write_team_tuition_settings((int) $team_id, $enabled, $amount)) {
            $message = '月謝設定を保存しました。';
            $message_type = 'success';
        } else {
            $message = '月謝設定の保存に失敗しました。';
            $message_type = 'error';
        }
    }
}

$tuition_display = function_exists('aidunite_payment_read_tuition_display')
    ? aidunite_payment_read_tuition_display((int) $team_id)
    : [];
$tuition_enabled = !empty($tuition_display['team_tuition_enabled']);
$monthly_fee     = (int) ($tuition_display['team_monthly_fee'] ?? 0);
$connect_acct    = (string) ($tuition_display['stripe_connect_account_id'] ?? '');
$fee_policy      = is_array($tuition_display['fee_policy'] ?? null) ? $tuition_display['fee_policy'] : [];
$team_fee_note   = (string) ($fee_policy['team_fee_note'] ?? '');

wp_enqueue_style(
    'aidunite-toggle-switch',
    get_stylesheet_directory_uri() . '/assets/css/components/toggle-switch.css',
    [],
    '1.0.1'
);

if (function_exists('aidunite_enqueue_payment_setup_shared_styles')) {
    aidunite_enqueue_payment_setup_shared_styles();
}

if (function_exists('aidunite_web_app_page_prepare_hero_shell_body_class')) {
    aidunite_web_app_page_prepare_hero_shell_body_class();
}

get_header();

$shell_args = [
    'page_class' => 'page-team-payment-management team-payment-management-container',
    'title' => 'チーム月謝管理',
    'subtitle' => 'チーム月謝の金額設定と、Stripe Connect連携を管理します。',
    'back' => true,
    'back_url' => home_url('/team-settings'),
    'active_nav' => 'none',
];

$shell_mode = function_exists('aidunite_web_app_page_shell_begin')
    ? aidunite_web_app_page_shell_begin($shell_args, [
        'legacy_container_class' => 'page-container team-payment-management-container',
        'legacy_back_url' => home_url('/team-settings'),
        'legacy_back_label' => 'チーム設定に戻る',
    ])
    : 'legacy';
?>

<div class="payment-setup-content<?php echo $shell_mode !== 'legacy' ? ' payment-setup-content--shell' : ''; ?>">
    <?php
    get_template_part('template-parts/payment/team-payment-management', 'content', [
        'message' => $message,
        'message_type' => $message_type,
        'team_id' => $team_id,
        'team' => $team,
        'tuition_enabled' => $tuition_enabled,
        'monthly_fee' => $monthly_fee,
        'connect_acct' => $connect_acct,
        'team_fee_note' => $team_fee_note,
    ]);
    ?>
</div>

<?php
if (function_exists('aidunite_web_app_page_shell_end')) {
    aidunite_web_app_page_shell_end($shell_mode);
} else {
    echo '</div>';
}

aidunite_page_asset_localize('team-payment-management', [
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'teamId' => (int) $team_id,
    'paymentNonce' => wp_create_nonce('aidunite_payment_nonce'),
]);
get_footer();
