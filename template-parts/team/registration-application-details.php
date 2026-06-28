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

$accordion_title = (string) ($context['accordion_title'] ?? '');
if ($accordion_title === '') {
    $accordion_title = '申請内容の確認';
}
$icon_base = aidunite_get_theme_icons_uri();
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
    <?php
    if (function_exists('aidunite_render_team_application_review_panel')) {
        echo aidunite_render_team_application_review_panel($context); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
    ?>
  </div>
</details>
