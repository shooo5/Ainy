<?php
/**
 * 試合の詳細：折りたたみセクション（活動情報・対戦実績・共通点）
 *
 * @package AidUnite
 */
if (!defined('ABSPATH')) {
    exit;
}

$other_team_id = isset($other_team_id) ? (int) $other_team_id : 0;
$other_team_data = isset($other_team_data) && is_array($other_team_data) ? $other_team_data : [];
$my_team_id = isset($my_team_id) ? (int) $my_team_id : 0;
$my_team_data = isset($my_team_data) && is_array($my_team_data) ? $my_team_data : [];
$venue_label = isset($match_detail_venue_label) ? (string) $match_detail_venue_label : '';
$common_context = isset($match_detail_common_context) && is_array($match_detail_common_context)
    ? $match_detail_common_context
    : [];

$activity_lines = function_exists('aidunite_match_detail_opponent_activity_lines')
    ? aidunite_match_detail_opponent_activity_lines($other_team_id, $other_team_data, $venue_label)
    : [];
$stats = ($other_team_id > 0 && function_exists('aidunite_match_detail_opponent_match_stats'))
    ? aidunite_match_detail_opponent_match_stats($other_team_id)
    : ['total' => 0, 'recent' => 0];
$achievements = trim((string) ($other_team_data['achievements'] ?? ''));
$common_points = function_exists('aidunite_match_detail_common_points')
    ? aidunite_match_detail_common_points($my_team_id, $other_team_id, $my_team_data, $other_team_data, $common_context)
    : [];

$has_activity = !empty($activity_lines);
$has_stats = !empty($stats['total']) || $achievements !== '';
$has_common = !empty($common_points);

if (!$has_activity && !$has_stats && !$has_common) {
    return;
}
?>
<div class="match-detail-accordions" aria-label="<?php echo esc_attr('相手チームの詳細情報'); ?>">
    <?php if ($has_activity) : ?>
    <details class="match-detail-accordion">
        <summary class="match-detail-accordion__summary">
            <span class="match-detail-accordion__icon" aria-hidden="true"><?php echo aidunite_render_theme_icon('calendar_month', ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
            <span class="match-detail-accordion__label"><?php echo esc_html('活動情報'); ?></span>
            <span class="match-detail-accordion__chev" aria-hidden="true"></span>
        </summary>
        <div class="match-detail-accordion__panel">
            <ul class="match-detail-opponent-list">
                <?php foreach ($activity_lines as $line) : ?>
                <li><?php echo esc_html($line); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </details>
    <?php endif; ?>

    <?php if ($has_stats) : ?>
    <details class="match-detail-accordion">
        <summary class="match-detail-accordion__summary">
            <span class="match-detail-accordion__icon" aria-hidden="true"><?php echo aidunite_render_theme_icon('trophy', ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
            <span class="match-detail-accordion__label"><?php echo esc_html('対戦実績'); ?></span>
            <span class="match-detail-accordion__chev" aria-hidden="true"></span>
        </summary>
        <div class="match-detail-accordion__panel">
            <?php if (!empty($stats['total'])) : ?>
            <p class="match-detail-opponent-stats__line"><strong><?php echo esc_html('成立試合'); ?></strong> <?php echo esc_html((string) (int) $stats['total']); ?><?php echo esc_html('件'); ?></p>
            <?php endif; ?>
            <?php if (!empty($stats['recent'])) : ?>
            <p class="match-detail-opponent-stats__line match-detail-opponent-stats__line--sub"><?php echo esc_html('最近1ヶ月で ' . (int) $stats['recent'] . '試合'); ?></p>
            <?php endif; ?>
            <?php if ($achievements !== '') : ?>
            <p class="match-detail-opponent-stats__note"><?php echo esc_html($achievements); ?></p>
            <?php endif; ?>
        </div>
    </details>
    <?php endif; ?>

    <?php if ($has_common) : ?>
    <details class="match-detail-accordion">
        <summary class="match-detail-accordion__summary">
            <span class="match-detail-accordion__icon" aria-hidden="true"><?php echo aidunite_render_theme_icon('heart_check', ['width' => '20', 'height' => '20'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
            <span class="match-detail-accordion__label"><?php echo esc_html('共通点'); ?></span>
            <span class="match-detail-accordion__chev" aria-hidden="true"></span>
        </summary>
        <div class="match-detail-accordion__panel">
            <div class="match-detail-opponent-chips" role="list">
                <?php foreach ($common_points as $point) : ?>
                <span class="badge match-detail-opponent-chip" role="listitem"><?php echo esc_html($point); ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </details>
    <?php endif; ?>
</div>
