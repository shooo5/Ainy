<?php
/**
 * Template Name: チーム月謝徴収状況
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
if ($team_id <= 0) {
    wp_die('チーム情報が見つかりません。');
}

$vm = function_exists('aidunite_payment_read_team_tuition_collections_page_model')
    ? aidunite_payment_read_team_tuition_collections_page_model($team_id)
    : [];

if (function_exists('aidunite_enqueue_payment_setup_shared_styles')) {
    aidunite_enqueue_payment_setup_shared_styles();
}

if (function_exists('aidunite_web_app_page_prepare_hero_shell_body_class')) {
    aidunite_web_app_page_prepare_hero_shell_body_class();
}

get_header();

$shell_args = [
    'page_class' => 'page-team-tuition-collections team-tuition-collections-page',
    'title' => '月謝の徴収状況',
    'subtitle' => '保護者ごとの今月の支払い状況と、直近の入金履歴を確認できます。',
    'back' => true,
    'back_url' => home_url('/team-payment-management'),
    'active_nav' => 'none',
];

$shell_mode = function_exists('aidunite_web_app_page_shell_begin')
    ? aidunite_web_app_page_shell_begin($shell_args, [
        'legacy_container_class' => 'page-container team-tuition-collections-page',
        'legacy_back_url' => home_url('/team-payment-management'),
        'legacy_back_label' => 'チーム月謝管理に戻る',
    ])
    : 'legacy';
?>

<div class="payment-setup-content team-tuition-collections-content<?php echo $shell_mode !== 'legacy' ? ' payment-setup-content--shell' : ''; ?>">
    <?php
    get_template_part('template-parts/payment/team-tuition-collections', 'content', [
        'vm' => $vm,
    ]);
    ?>
</div>

<?php
if (function_exists('aidunite_web_app_page_shell_end')) {
    aidunite_web_app_page_shell_end($shell_mode);
} else {
    echo '</div>';
}

if (function_exists('aidunite_page_asset_localize')) {
    aidunite_page_asset_localize('team-tuition-collections', [
        'defaultFilter' => (string) ($vm['default_filter'] ?? 'action_needed'),
        'monthLabel' => (string) ($vm['month_label'] ?? ''),
        'followupMessages' => is_array($vm['followup_messages'] ?? null) ? $vm['followup_messages'] : [],
        'copySuccessLabel' => '案内文をコピーしました',
        'copyErrorLabel' => 'コピーに失敗しました。手動で選択してコピーしてください。',
    ]);
}

get_footer();
