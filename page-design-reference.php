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
        <a href="#feedback-spinner-toast">スピナー×トースト</a>
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
if (is_readable($ref_dir . 'demo-scripts.php')) {
    include $ref_dir . 'demo-scripts.php';
}
?>

<?php get_footer(); ?>
