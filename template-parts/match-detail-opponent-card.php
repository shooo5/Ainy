<?php
/**
 * 試合の詳細：相手チームカード（メインカラム）
 *
 * @package AidUnite
 */
if (!defined('ABSPATH')) {
    exit;
}

$other_team_id = isset($other_team_id) ? (int) $other_team_id : 0;
$other_team_data = isset($other_team_data) && is_array($other_team_data) ? $other_team_data : [];
$application_status = isset($application_status) ? (string) $application_status : '';
$match_detail_venue_hint_theme = isset($match_detail_venue_hint_theme) ? (string) $match_detail_venue_hint_theme : 'boys';

$team_name = (string) ($other_team_data['name'] ?? '—');
$team_logo = (string) ($other_team_data['logo'] ?? '');
$profile_url = ($other_team_id > 0 && function_exists('aidunite_get_team_public_profile_url'))
    ? aidunite_get_team_public_profile_url($other_team_id)
    : '';
$status_label = function_exists('aidunite_match_detail_application_status_label')
    ? aidunite_match_detail_application_status_label($application_status)
    : '未申請';
?>
<section class="card match-detail-opponent-card match-detail-opponent-card--<?php echo esc_attr($match_detail_venue_hint_theme); ?>" aria-label="<?php echo esc_attr('相手チーム'); ?>">
    <div class="card-body">
        <div class="match-detail-opponent-card__head">
            <?php if ($team_logo !== '') : ?>
            <img class="match-detail-opponent-card__avatar" src="<?php echo esc_url($team_logo); ?>" alt="" width="48" height="48" loading="lazy" decoding="async">
            <?php else : ?>
            <span class="match-detail-opponent-card__avatar match-detail-opponent-card__avatar--initial" aria-hidden="true"><?php echo esc_html(function_exists('aidunite_match_detail_team_avatar_initial') ? aidunite_match_detail_team_avatar_initial($team_name) : '?'); ?></span>
            <?php endif; ?>
            <div class="match-detail-opponent-card__identity">
                <div class="match-detail-opponent-card__title-row">
                    <p class="match-detail-opponent-card__name"><?php echo esc_html($team_name); ?></p>
                    <?php if ($status_label !== '') : ?>
                    <span class="match-detail-opponent-card__status"><?php echo esc_html($status_label); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php if ($profile_url !== '') : ?>
        <div class="match-detail-opponent-card__foot">
            <a class="match-detail-opponent-card__profile-link" href="<?php echo esc_url($profile_url); ?>">
                <?php echo esc_html('チーム情報を見る'); ?>
                <?php echo aidunite_render_theme_icon('chevron_right', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </a>
        </div>
        <?php endif; ?>
    </div>
</section>
