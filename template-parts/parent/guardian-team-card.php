<?php
/**
 * 保護者招待・登録：チーム情報カード
 *
 * @package AidUnite
 *
 * @var array<string, mixed> $args
 */

if (!defined('ABSPATH')) {
    exit;
}

$team = is_array($args['team'] ?? null) ? $args['team'] : [];
$heading = (string) ($args['heading'] ?? '招待先チーム');
$facts_layout = (string) ($args['facts_layout'] ?? 'stack');
if (!in_array($facts_layout, ['stack', 'inline'], true)) {
    $facts_layout = 'stack';
}
$team_name = (string) ($team['team_name'] ?? '');
if ($team_name === '') {
    return;
}

$sport_type = trim((string) ($team['sport_type'] ?? ''));
$prefecture = trim((string) ($team['activity_prefecture'] ?? ''));
$activity_area = trim((string) ($team['activity_area'] ?? ''));
$team_category = trim((string) ($team['team_category'] ?? ''));
$gender_label = trim((string) ($team['gender_label'] ?? ''));
$team_type_label = trim((string) ($team['team_type_label'] ?? ''));

$location_parts = [];
if ($prefecture !== '') {
    $location_parts[] = $prefecture;
    if ($prefecture === '東京都' && $activity_area !== '') {
        $location_parts[] = $activity_area;
    }
} else {
    $region = trim((string) ($team['region'] ?? ''));
    if ($region !== '' && $region !== '地域未設定') {
        $location_parts[] = $region;
    }
}

$join_display = static function (array $parts) {
    return implode('｜', array_values(array_filter($parts, static function ($part) {
        return trim((string) $part) !== '';
    })));
};
$location_line = $join_display($location_parts);

$fact_rows = [
    ['label' => '年代', 'value' => $team_category],
    ['label' => '都道府県', 'value' => $location_line],
    ['label' => '性別', 'value' => $gender_label],
    ['label' => '種別', 'value' => $team_type_label],
];
$has_facts = false;
foreach ($fact_rows as $row) {
    if (trim((string) ($row['value'] ?? '')) !== '') {
        $has_facts = true;
        break;
    }
}
?>
<section class="guardian-flow-card guardian-flow-card--team<?php echo $facts_layout === 'inline' ? ' guardian-team-card--facts-inline' : ''; ?>" aria-labelledby="guardian-team-card-heading">
    <h2 class="guardian-flow-card__title guardian-flow-card__title--accent" id="guardian-team-card-heading">
        <?php echo esc_html($heading); ?>
    </h2>

    <div class="guardian-team-card__body">
        <div class="guardian-team-card__hero">
            <?php if (!empty($team['logo_url'])) : ?>
            <img class="guardian-team-card__logo" src="<?php echo esc_url($team['logo_url']); ?>" alt="" width="80" height="80" loading="lazy">
            <?php else : ?>
            <div class="guardian-team-card__logo guardian-team-card__logo--placeholder" aria-hidden="true">
                <?php
                if (function_exists('aidunite_render_theme_icon')) {
                    echo aidunite_render_theme_icon('groups', ['width' => '40', 'height' => '40']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }
                ?>
            </div>
            <?php endif; ?>
            <div class="guardian-team-card__title-block">
                <p class="guardian-team-card__name"><?php echo esc_html($team_name); ?></p>
                <?php if ($sport_type !== '') : ?>
                <p class="guardian-team-card__sport">（<?php echo esc_html($sport_type); ?>）</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($has_facts) : ?>
        <dl class="guardian-team-card__facts<?php echo $facts_layout === 'inline' ? ' guardian-team-card__facts--inline' : ''; ?>">
            <?php foreach ($fact_rows as $row) : ?>
                <?php if (trim((string) ($row['value'] ?? '')) === '') {
                    continue;
                } ?>
            <div class="guardian-team-card__fact">
                <dt class="guardian-team-card__fact-label"><?php echo esc_html((string) $row['label']); ?></dt>
                <?php if ($facts_layout === 'inline') : ?>
                <span class="guardian-team-card__fact-sep" aria-hidden="true">｜</span>
                <?php endif; ?>
                <dd class="guardian-team-card__fact-value"><?php echo esc_html((string) $row['value']); ?></dd>
            </div>
            <?php endforeach; ?>
        </dl>
        <?php endif; ?>
    </div>
</section>
