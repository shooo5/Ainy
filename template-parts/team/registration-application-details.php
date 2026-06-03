<?php
/**
 * チーム申請内容の確認（アコーディオン・完了画面・承認待ちマイページ共通）
 *
 * @var array $context  aidunite_get_team_application_review_context と同一キー
 * @var bool  $open     初期表示で開くか
 */

if (!defined('ABSPATH')) {
    exit;
}

$context = isset($args['context']) && is_array($args['context']) ? $args['context'] : [];
$open = !empty($args['open']);

$team_name = (string) ($context['team_name'] ?? '');
$accordion_title = (string) ($context['accordion_title'] ?? '');
if ($accordion_title === '') {
    $accordion_title = '申請内容の確認';
}
$icon_base = aidunite_get_theme_icons_uri();

$logo_url = (string) ($context['team_logo'] ?? '');
$logo_crop = isset($context['logo_crop']) && is_array($context['logo_crop']) ? $context['logo_crop'] : ['x' => 0, 'y' => 0, 'zoom' => 100];
$logo_style = sprintf(
    'transform: translate(%d%%, %d%%) scale(%s);',
    (int) ($logo_crop['x'] ?? 0),
    (int) ($logo_crop['y'] ?? 0),
    max(0.5, (int) ($logo_crop['zoom'] ?? 100) / 100)
);
$has_logo = $logo_url !== '';
?>

<details class="team-reg-complete-details"<?php echo $open ? ' open' : ''; ?>>
  <summary class="team-reg-complete-details__summary">
    <span class="team-reg-complete-details__icon" aria-hidden="true">
      <img src="<?php echo esc_url($icon_base . 'list_alt_add.svg'); ?>" alt="" width="28" height="28" decoding="async">
    </span>
    <span class="team-reg-complete-details__label"><?php echo esc_html($accordion_title); ?></span>
    <span class="team-reg-complete-details__chev" aria-hidden="true"></span>
  </summary>
  <div class="team-reg-complete-details__panel">
    <div class="team-reg-complete-details__body<?php echo $has_logo ? '' : ' team-reg-complete-details__body--no-logo'; ?>">
      <aside class="team-reg-complete-details__logo-side" aria-label="チームロゴ">
        <?php if ($has_logo) : ?>
          <div class="team-reg-complete-details__logo-frame">
            <img
              class="team-reg-complete-details__logo"
              src="<?php echo esc_url($logo_url); ?>"
              alt="<?php echo esc_attr($team_name); ?>のロゴ"
              width="168"
              height="168"
              decoding="async"
              style="<?php echo esc_attr($logo_style); ?>"
            >
          </div>
        <?php else : ?>
          <div class="team-reg-complete-details__logo-frame team-reg-complete-details__logo-frame--placeholder" aria-hidden="true"></div>
        <?php endif; ?>
      </aside>

      <div class="team-reg-complete-details__content">
        <?php
        if (function_exists('aidunite_render_application_review_card_layout')) {
            echo aidunite_render_application_review_card_layout($context, [
                'include_submitted_at' => true,
                'include_contact'      => false,
            ]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        ?>
      </div>
    </div>
  </div>
</details>
