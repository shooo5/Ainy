<?php
/**
 * チームロゴアップロード（作成申請・チーム設定共通）
 *
 * @package AidUnite
 *
 * @var array $args {
 *   @type string $variant registration|settings
 *   @type string $initial_url
 *   @type array  $crop {x,y,zoom}
 * }
 */

if (!defined('ABSPATH')) {
    exit;
}

$variant = isset($args['variant']) && $args['variant'] === 'settings' ? 'settings' : 'registration';
$gender_key = isset($args['gender_key']) && in_array($args['gender_key'], ['male', 'female'], true)
    ? (string) $args['gender_key']
    : '';
$initial_url = isset($args['initial_url']) ? (string) $args['initial_url'] : '';
$crop = isset($args['crop']) && is_array($args['crop']) ? $args['crop'] : ['x' => 0, 'y' => 0, 'zoom' => 100];
$crop_x = (int) ($crop['x'] ?? 0);
$crop_y = (int) ($crop['y'] ?? 0);
$crop_zoom = (int) ($crop['zoom'] ?? 100);
if ($crop_zoom < 50) {
    $crop_zoom = 100;
}

$logo_max_label = function_exists('aidunite_get_team_logo_max_upload_label')
    ? aidunite_get_team_logo_max_upload_label()
    : '10MB';

$has_initial = $initial_url !== '' && filter_var($initial_url, FILTER_VALIDATE_URL);

if ($variant === 'settings') {
    $file_id = 'team_settings_logo_file';
    $frame_id = 'team-settings-logo-frame';
    $media_id = 'team-settings-logo-media';
    $preview_id = 'team-settings-logo-preview';
    $placeholder_id = 'team-settings-logo-placeholder';
    $trigger_id = 'team-settings-logo-trigger';
    $remove_id = 'team-settings-logo-remove';
    $adjust_id = 'team-settings-logo-adjust';
    $zoom_range_id = 'team-settings-logo-zoom-range';
    $change_id = 'team-settings-logo-change';
    $wrap_class = 'team-settings-dash__logo-upload';
} elseif ($gender_key !== '') {
    $variant    = 'registration_' . $gender_key;
    $file_id    = 'team_' . $gender_key . '_logo_file';
    $frame_id   = 'team-' . $gender_key . '-logo-frame';
    $media_id   = 'team-' . $gender_key . '-logo-media';
    $preview_id = 'team-' . $gender_key . '-logo-preview';
    $placeholder_id = 'team-' . $gender_key . '-logo-placeholder';
    $trigger_id = 'team-' . $gender_key . '-logo-trigger';
    $remove_id  = 'team-' . $gender_key . '-logo-remove';
    $adjust_id  = 'team-' . $gender_key . '-logo-adjust';
    $zoom_range_id = 'team-' . $gender_key . '-logo-zoom-range';
    $change_id  = 'team-' . $gender_key . '-logo-change';
    $wrap_class = '';
} else {
    $file_id = 'team_logo_file';
    $frame_id = 'team-logo-frame';
    $media_id = 'team-logo-media';
    $preview_id = 'team-logo-preview';
    $placeholder_id = 'team-logo-placeholder';
    $trigger_id = 'team-logo-trigger';
    $remove_id = 'team-logo-remove';
    $adjust_id = 'team-logo-adjust';
    $zoom_range_id = 'team-logo-zoom-range';
    $change_id = 'team-logo-change';
    $wrap_class = '';
}
?>
<div class="team-reg-logo-upload<?php echo $wrap_class !== '' ? ' ' . esc_attr($wrap_class) : ''; ?>" data-team-logo-upload data-variant="<?php echo esc_attr($variant); ?>">
  <input type="file" id="<?php echo esc_attr($file_id); ?>" class="team-reg-logo-upload__file" accept="image/jpeg,image/png,image/webp" hidden>
  <div class="team-reg-logo-upload__circle<?php echo $has_initial ? ' has-image' : ''; ?>" id="<?php echo esc_attr($frame_id); ?>">
    <div class="team-reg-logo-upload__media" id="<?php echo esc_attr($media_id); ?>"<?php echo $has_initial ? '' : ' hidden'; ?>>
      <img
        class="team-reg-logo-upload__preview"
        id="<?php echo esc_attr($preview_id); ?>"
        alt=""
        draggable="false"
        <?php if ($has_initial) : ?>
          src="<?php echo esc_url($initial_url); ?>"
        <?php endif; ?>
      >
    </div>
    <button type="button" class="team-reg-logo-upload__trigger" id="<?php echo esc_attr($trigger_id); ?>" aria-label="チームロゴを追加"<?php echo $has_initial ? ' hidden' : ''; ?>>
      <span class="team-reg-logo-upload__placeholder" id="<?php echo esc_attr($placeholder_id); ?>"<?php echo $has_initial ? ' hidden' : ''; ?>>
        <svg class="team-reg-logo-upload__icon" width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z" stroke="currentColor" stroke-width="1.5"/>
          <path d="M5 8.5h1.2l1-1.6a2 2 0 0 1 1.7-.9h6.2a2 2 0 0 1 1.7.9l1 1.6H19a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
        </svg>
        <span class="team-reg-logo-upload__text">ロゴを追加</span>
      </span>
    </button>
  </div>
  <button type="button" class="team-reg-logo-upload__remove" id="<?php echo esc_attr($remove_id); ?>"<?php echo $has_initial ? '' : ' hidden'; ?> aria-label="ロゴを削除">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    </svg>
  </button>
  <div class="team-reg-logo-upload__adjust" id="<?php echo esc_attr($adjust_id); ?>"<?php echo $has_initial ? '' : ' hidden'; ?>>
    <p class="team-reg-logo-upload__drag-hint">ドラッグで位置、スライダーで縮小・拡大（50〜250%）</p>
    <label class="team-reg-logo-upload__zoom" for="<?php echo esc_attr($zoom_range_id); ?>">
      <span class="team-reg-logo-upload__zoom-label">表示サイズ</span>
      <input
        type="range"
        id="<?php echo esc_attr($zoom_range_id); ?>"
        class="team-reg-logo-upload__zoom-range"
        min="50"
        max="250"
        value="<?php echo esc_attr((string) $crop_zoom); ?>"
        step="5"
        aria-valuemin="50"
        aria-valuemax="250"
        aria-valuenow="<?php echo esc_attr((string) $crop_zoom); ?>"
      >
    </label>
    <button type="button" class="team-reg-logo-upload__change-btn" id="<?php echo esc_attr($change_id); ?>">画像を変更</button>
  </div>
  <p class="team-reg-logo-upload__hint" data-team-logo-hint>JPG / PNG / WebP（<?php echo esc_html($logo_max_label); ?>まで）</p>
</div>
