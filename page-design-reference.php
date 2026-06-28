<?php
/**
 * Template Name: デザインリファレンス
 *
 * Ainy デザインシステム — スタイルガイド
 * スタイル: assets/css/pages/design-reference.css
 * 部分テンプレート: includes/design-reference/
 */

get_header();

$ref_dir = get_template_directory() . '/includes/design-reference/';
?>

<div class="team-dashboard-container page-design-reference">
    <div class="dashboard-header">
        <h1>🎨 Ainy デザインシステム</h1>
        <p>統一ルールの実物確認ページ。正本は <code>docs/Ainy-UI-all-Unified-Rules.md</code> および各 include 内のコンポーネント CSS。</p>
    </div>

    <nav class="ref-toc" aria-label="デザインリファレンス目次">
        <a href="#ref-chapter-colors">① カラーパレット</a>
        <a href="#ref-chapter-parts">② パーツ</a>
        <a href="#ref-part-svg-icons">SVGアイコン</a>
        <a href="#ref-chapter-pages">③ ページ</a>
        <a href="#feedback-modal-guide">フィードバック・モーダル</a>
        <a href="#ref-first-match-billing-modal">初試合成立モーダル</a>
        <a href="#feedback-spinner-toast">スピナー×トースト</a>
        <a href="#ref-schedule-feedback">スケジュール通知</a>
    </nav>

    <section class="dashboard-section">
        <div class="main-content-area">

            <div class="design-section ref-chapter" id="ref-chapter-colors">
                <h2 class="section-title">① カラーパレット統一定義</h2>
                <?php include $ref_dir . 'section-01-colors.php'; ?>
            </div>

            <div class="design-section ref-chapter" id="ref-chapter-parts">
                <h2 class="section-title">② パーツ統一定義</h2>
                <?php include $ref_dir . 'section-02-parts.php'; ?>
            </div>

            <div class="design-section ref-chapter" id="ref-chapter-pages">
                <h2 class="section-title">③ ページ統一定義</h2>
                <?php include $ref_dir . 'section-03-pages.php'; ?>
            </div>

        </div>
    </section>
</div>

<?php
$ref_demo_js = get_stylesheet_directory() . '/assets/js/pages/design-reference-demo.js';
$first_match_css = get_stylesheet_directory() . '/assets/css/components/payment-first-match-prompt.css';
if (is_readable($first_match_css)) {
    wp_enqueue_style(
        'aidunite-payment-first-match-prompt',
        get_stylesheet_directory_uri() . '/assets/css/components/payment-first-match-prompt.css',
        ['aidunite-style'],
        (string) filemtime($first_match_css)
    );
}
$first_match_js = get_stylesheet_directory() . '/assets/js/payment/payment-first-match-prompt.js';
if (is_readable($first_match_js)) {
    wp_enqueue_script(
        'aidunite-payment-first-match-prompt',
        get_stylesheet_directory_uri() . '/assets/js/payment/payment-first-match-prompt.js',
        [],
        (string) filemtime($first_match_js),
        true
    );
    $demo_prompt = function_exists('aidunite_payment_exit_get_first_match_billing_prompt_demo_payload')
        ? aidunite_payment_exit_get_first_match_billing_prompt_demo_payload()
        : [];
    wp_localize_script('aidunite-payment-first-match-prompt', 'aidunitePaymentFirstMatch', [
        'previewMode' => '1',
        'previewPrompt' => $demo_prompt,
        'heroImageUrl' => function_exists('aidunite_payment_exit_get_first_match_hero_image_url')
            ? aidunite_payment_exit_get_first_match_hero_image_url()
            : '',
    ]);
}
if (is_readable($ref_demo_js)) {
    wp_enqueue_script(
        'aidunite-design-reference-demo',
        get_stylesheet_directory_uri() . '/assets/js/pages/design-reference-demo.js',
        ['aidunite-toast-notification', 'aidunite-confirm-modal', 'loading-spinner-utils', 'aidunite-payment-first-match-prompt'],
        (string) filemtime($ref_demo_js),
        true
    );
    wp_localize_script('aidunite-design-reference-demo', 'aiduniteDesignReferenceDemo', [
        'pushIcon' => get_site_icon_url(192) ?: '',
    ]);
}
$ref_schedule_fb_css = get_stylesheet_directory() . '/assets/css/pages/design-reference-schedule-feedback.css';
if (is_readable($ref_schedule_fb_css)) {
    wp_enqueue_style(
        'aidunite-design-reference-schedule-feedback',
        get_stylesheet_directory_uri() . '/assets/css/pages/design-reference-schedule-feedback.css',
        ['aidunite-style'],
        (string) filemtime($ref_schedule_fb_css)
    );
}
?>

<?php get_footer(); ?>
