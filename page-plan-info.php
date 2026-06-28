<?php
/**
 * Template Name: プラン情報ページ
 */

if (function_exists('aidunite_web_app_page_prepare_hero_shell_body_class')) {
    aidunite_web_app_page_prepare_hero_shell_body_class();
}

get_header();

$vm = function_exists('aidunite_payment_read_plan_info_page_model')
    ? aidunite_payment_read_plan_info_page_model()
    : [];

$shell_subtitle = 'チームの成長ステージに合わせて選べるシンプルな料金体系';
$back_url = is_user_logged_in() ? home_url('/payment-setup') : home_url('/service');
$back_label = is_user_logged_in() ? '契約・お支払いに戻る' : 'サービス紹介に戻る';

$shell_args = [
    'page_class' => 'page-plan-info plan-info-page',
    'title' => '料金プラン',
    'subtitle' => $shell_subtitle,
    'back' => true,
    'back_url' => $back_url,
    'active_nav' => 'none',
];

if (function_exists('aidunite_enqueue_payment_setup_shared_styles')) {
    aidunite_enqueue_payment_setup_shared_styles();
}

$shell_mode = function_exists('aidunite_web_app_page_shell_begin')
    ? aidunite_web_app_page_shell_begin($shell_args, [
        'legacy_container_class' => 'team-dashboard-container page-plan-info plan-info-page',
        'legacy_back_url' => $back_url,
        'legacy_back_label' => $back_label,
    ])
    : 'legacy';
?>

<div class="plan-info-content payment-setup-content<?php echo $shell_mode !== 'legacy' ? ' payment-setup-content--shell plan-info-content--shell' : ''; ?>">
    <?php get_template_part('template-parts/plan/plan-info', 'content', ['vm' => $vm]); ?>
</div>

<?php
if (function_exists('aidunite_web_app_page_shell_end')) {
    aidunite_web_app_page_shell_end($shell_mode);
} else {
    echo '</div>';
}

get_footer();
