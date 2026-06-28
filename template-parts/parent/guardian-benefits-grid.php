<?php
/**
 * 保護者招待・登録：保護者ができること
 *
 * @package AidUnite
 *
 * @var array<string, mixed> $args
 */

if (!defined('ABSPATH')) {
    exit;
}

$variant = (string) ($args['variant'] ?? 'invite');
$title = $variant === 'signup'
    ? 'このチームの保護者になると…'
    : '保護者ができること';
?>
<section class="guardian-flow-card guardian-flow-card--benefits guardian-flow-card--benefits-<?php echo esc_attr($variant); ?>" aria-labelledby="guardian-benefits-heading">
    <h2 class="guardian-flow-card__title" id="guardian-benefits-heading"><?php echo esc_html($title); ?></h2>
    <ul class="guardian-benefits-grid">
        <li class="guardian-benefits-item">
            <span class="guardian-benefits-item__icon" aria-hidden="true">
                <?php echo function_exists('aidunite_render_theme_icon') ? aidunite_render_theme_icon('calendar_month', ['width' => '22', 'height' => '22']) : '📅'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </span>
            <span class="guardian-benefits-item__title">スケジュール確認</span>
            <span class="guardian-benefits-item__text">練習・試合の予定をいつでも確認</span>
        </li>
        <li class="guardian-benefits-item">
            <span class="guardian-benefits-item__icon" aria-hidden="true">
                <?php echo function_exists('aidunite_render_theme_icon') ? aidunite_render_theme_icon('check_circle', ['width' => '22', 'height' => '22']) : '✓'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </span>
            <span class="guardian-benefits-item__title">出欠回答</span>
            <span class="guardian-benefits-item__text">参加可否をスマホから回答</span>
        </li>
        <li class="guardian-benefits-item">
            <span class="guardian-benefits-item__icon" aria-hidden="true">
                <?php echo function_exists('aidunite_render_theme_icon') ? aidunite_render_theme_icon('campaign', ['width' => '22', 'height' => '22']) : '📢'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </span>
            <span class="guardian-benefits-item__title">チーム連絡確認</span>
            <span class="guardian-benefits-item__text">代表者からの連絡を見逃さない</span>
        </li>
        <li class="guardian-benefits-item">
            <span class="guardian-benefits-item__icon" aria-hidden="true">
                <?php echo function_exists('aidunite_render_theme_icon') ? aidunite_render_theme_icon('emoji_events', ['width' => '22', 'height' => '22']) : '🏆'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </span>
            <span class="guardian-benefits-item__title">試合情報確認</span>
            <span class="guardian-benefits-item__text">対戦相手や会場などを把握</span>
        </li>
    </ul>
</section>
