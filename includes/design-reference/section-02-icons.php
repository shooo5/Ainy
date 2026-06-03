<?php
/**
 * デザインリファレンス §02 SVG アイコン一覧（自動スキャン）
 */

$icon_groups = function_exists('aidunite_get_theme_svg_icon_inventory')
    ? aidunite_get_theme_svg_icon_inventory()
    : [];

$total_icons = 0;
foreach ($icon_groups as $group) {
    $total_icons += count($group['items'] ?? []);
}
?>

<!-- SVGアイコン -->
<div class="design-part" id="ref-part-svg-icons">
    <h4>SVGアイコン</h4>
    <p class="part-description">
        テーマ内の SVG アイコン一覧（<strong><?php echo (int) $total_icons; ?> 件</strong>）。
        <code>assets/images/icons/</code> 配下にファイルを追加すると、次回表示時に<strong>自動で一覧へ反映</strong>されます。
        正本ガイド: <code>docs/icon-usage-guide.md</code> / ヘルパー: <code>functions/common/icon-helpers.php</code>。
        <strong>一覧表は形状確認のためすべて黒表示</strong>（各ページでは CSS で色を指定）。
    </p>

    <div class="ref-icon-usage ref-stack">
        <p class="part-description"><strong>使い分け</strong></p>
        <ul class="ref-usage-list">
            <li><strong>ファイル SVG</strong> — <code>aidunite_get_inline_icon_svg('home')</code> または <code>aidunite_get_theme_icon_svg('home')</code>（<code>currentColor</code>）</li>
            <li><strong>置き場所</strong> — <code>assets/images/icons/*.svg</code>（サブフォルダなし）</li>
            <li><strong>会員登録</strong> — 他画面と同様 <code>aidunite_get_theme_icon_svg('mail')</code> 等（<code>assets/images/icons/</code>）</li>
            <li><strong>Material Symbols</strong> — マッチ度 thumb_up 等（フォント。別系統）</li>
        </ul>
    </div>

    <?php foreach ($icon_groups as $group) : ?>
        <?php
        $items = $group['items'] ?? [];
        if (!$items) {
            continue;
        }
        ?>
        <h5 class="feedback-demo-subtitle feedback-demo-subtitle--spaced" id="ref-svg-icons-<?php echo esc_attr($group['slug']); ?>">
            <?php echo esc_html($group['label']); ?>
            <span class="ref-icon-group-count">（<?php echo count($items); ?>）</span>
        </h5>
        <?php if (!empty($group['description'])) : ?>
            <p class="part-description"><?php echo wp_kses_post($group['description']); ?></p>
        <?php endif; ?>

        <div class="ref-icon-grid" role="list" aria-label="<?php echo esc_attr($group['label']); ?>">
            <?php foreach ($items as $item) : ?>
                <?php
                $preview = function_exists('aidunite_render_design_ref_icon_preview')
                    ? aidunite_render_design_ref_icon_preview($item)
                    : '';
                if ($preview === '') {
                    continue;
                }
                $helper_label = $item['helper'] ?? '';
                if ($helper_label === 'img') {
                    $helper_label = 'img（本番は CSS 着色）';
                }
                ?>
                <figure class="ref-icon-card" role="listitem" title="<?php echo esc_attr($item['name']); ?>">
                    <div class="ref-icon-card__preview">
                        <?php echo $preview; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized SVG/img from theme files ?>
                    </div>
                    <figcaption class="ref-icon-card__meta">
                        <code class="ref-icon-card__name"><?php echo esc_html($item['name']); ?></code>
                        <?php if (!empty($item['relative'])) : ?>
                            <span class="ref-icon-card__path"><?php echo esc_html($item['relative']); ?></span>
                        <?php else : ?>
                            <span class="ref-icon-card__path"><?php echo esc_html($helper_label); ?></span>
                        <?php endif; ?>
                    </figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <p class="part-note ref-icon-auto-note">
        追加方法: <code>assets/images/icons/</code> に <code>.svg</code> を置くだけ（黒1色 DL 推奨 → CSS で着色）。
        PHP 内蔵アイコンを増やす場合は <code>aidunite_svg_icon_inventory_groups</code> フィルター、または <code>icon-helpers.php</code> の inventory 配列を更新してください。
    </p>

    <h5 class="feedback-demo-subtitle feedback-demo-subtitle--spaced" id="ref-svg-icon-color-demo">1ファイル + CSS で色分け</h5>
    <p class="part-description">
        <code>*_999999.svg</code> / <code>*_FFFFFF.svg</code> など色違いコピーは削除済み。
        各アイコンは <code>assets/images/icons/*.svg</code> 1枚 + <code>aidunite_get_theme_icon_svg()</code> + 親の <code>color</code> で表示します。
    </p>
    <div class="ref-icon-color-demo ref-inline-wrap" aria-label="home アイコン色見本">
        <div class="ref-icon-color-demo__item ref-icon-color-demo__item--muted">
            <?php echo aidunite_get_chat_icon_svg('home', ['width' => '32', 'height' => '32', 'class' => 'ref-icon-preview__svg']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <span class="ref-icon-color-demo__label">非アクティブ相当<br><code>#999</code></span>
        </div>
        <div class="ref-icon-color-demo__item ref-icon-color-demo__item--on-dark">
            <?php echo aidunite_get_chat_icon_svg('home', ['width' => '32', 'height' => '32', 'class' => 'ref-icon-preview__svg']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <span class="ref-icon-color-demo__label">アクティブ相当<br><code>#fff</code></span>
        </div>
        <div class="ref-icon-color-demo__item ref-icon-color-demo__item--brand">
            <?php echo aidunite_get_chat_icon_svg('home', ['width' => '32', 'height' => '32', 'class' => 'ref-icon-preview__svg']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <span class="ref-icon-color-demo__label">マッチ詳細等<br><code>var(--primary-color)</code></span>
        </div>
    </div>
</div>
