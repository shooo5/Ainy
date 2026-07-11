<?php
/**
 * 選手プロフィール写真アップロード
 *
 * @package AidUnite
 *
 * @var array<string, mixed> $args
 */

if (!defined('ABSPATH')) {
    exit;
}

$field_id = sanitize_key((string) ($args['field_id'] ?? 'player_photo'));
$file_input_id = $field_id . '_file';
$initial_url = (string) ($args['initial_url'] ?? '');
$label = (string) ($args['label'] ?? '選手写真');
$max_label = function_exists('aidunite_get_image_upload_max_label')
    ? aidunite_get_image_upload_max_label()
    : aidunite_player_read_photo_max_upload_label();
$hint = (string) ($args['hint'] ?? 'JPG / PNG / WebP（' . $max_label . 'まで）');
$has_initial = $initial_url !== '' && filter_var($initial_url, FILTER_VALIDATE_URL);
?>

<div class="player-photo-upload" data-player-photo-upload>
  <p class="player-photo-upload__label"><?php echo esc_html($label); ?> <span class="player-form-optional">任意</span></p>
  <div class="player-photo-upload__body">
    <input
      type="file"
      id="<?php echo esc_attr($file_input_id); ?>"
      name="player_photo_file"
      class="player-photo-upload__file"
      accept="image/jpeg,image/png,image/webp"
    >
    <input type="hidden" name="player_photo_remove" value="0" data-player-photo-remove-flag>
    <div class="player-photo-upload__circle<?php echo $has_initial ? ' has-image' : ''; ?>" data-player-photo-frame>
      <img
        src="<?php echo $has_initial ? esc_url($initial_url) : ''; ?>"
        alt=""
        class="player-photo-upload__preview"
        data-player-photo-preview
        width="96"
        height="96"
        <?php echo $has_initial ? '' : 'hidden'; ?>
      >
      <label
        for="<?php echo esc_attr($file_input_id); ?>"
        class="player-photo-upload__trigger"
        data-player-photo-trigger
        <?php echo $has_initial ? 'hidden' : ''; ?>
      >
        <?php echo aidunite_render_theme_icon('photo_camera', ['width' => '28', 'height' => '28'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <span class="player-photo-upload__trigger-text">写真を追加</span>
      </label>
    </div>
    <div class="player-photo-upload__actions">
      <label for="<?php echo esc_attr($file_input_id); ?>" class="player-photo-upload__change" data-player-photo-change <?php echo $has_initial ? '' : 'hidden'; ?>>画像を変更</label>
      <button type="button" class="player-photo-upload__remove" data-player-photo-remove <?php echo $has_initial ? '' : 'hidden'; ?>>写真を削除</button>
    </div>
  </div>
  <p class="player-photo-upload__hint"><?php echo esc_html($hint); ?></p>
</div>
