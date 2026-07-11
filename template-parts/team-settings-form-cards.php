<?php
/**
 * チーム設定: 基本情報フォーム（selection-card トーンの team-form-panel）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
        <div class="team-settings-dash__form-sections team-form-panels team-form-panels--grid" data-aidunite-ui="team-settings-form-panels-v3">

          <article class="team-form-panel team-form-panel--full" aria-labelledby="team-form-card-basic-heading">
            <div class="card-header-row">
              <span class="card-icon" aria-hidden="true">📋</span>
              <div>
                <h3 class="card-title" id="team-form-card-basic-heading">チームの基本</h3>
              </div>
            </div>
            <div class="team-form-panel__body">
              <?php
              $team_name_kana = (string) ($team_name_kana ?? '');
              $team_logo_crop = function_exists('aidunite_get_team_logo_crop')
                  ? aidunite_get_team_logo_crop((int) $team_id)
                  : ['x' => 0, 'y' => 0, 'zoom' => 100];
              ?>
              <input type="hidden" id="team_logo" name="team_logo" value="<?php echo esc_attr($team_logo); ?>">
              <input type="hidden" id="team_logo_offset_x" name="team_logo_offset_x" value="<?php echo esc_attr((string) ($team_logo_crop['x'] ?? 0)); ?>">
              <input type="hidden" id="team_logo_offset_y" name="team_logo_offset_y" value="<?php echo esc_attr((string) ($team_logo_crop['y'] ?? 0)); ?>">
              <input type="hidden" id="team_logo_zoom" name="team_logo_zoom" value="<?php echo esc_attr((string) ($team_logo_crop['zoom'] ?? 100)); ?>">
              <?php
              get_template_part('template-parts/team/logo-upload-field', null, [
                  'variant' => 'settings',
                  'initial_url' => $team_logo,
                  'crop' => $team_logo_crop,
              ]);
              ?>
              <div class="form-grid form-grid-2">
                <div class="team-settings-dash__field">
                  <label for="team_name">チーム名 <span class="required" aria-hidden="true">*</span></label>
                  <input class="team-settings-dash__input" type="text" id="team_name" name="team_name" required value="<?php echo esc_attr($team_display_name); ?>" autocomplete="organization" />
                </div>
                <div class="team-settings-dash__field">
                  <label for="team_name_kana">フリガナ</label>
                  <input class="team-settings-dash__input" type="text" id="team_name_kana" name="team_name_kana" value="<?php echo esc_attr($team_name_kana); ?>" autocomplete="off" inputmode="katakana" placeholder="例：エイドユナイト" />
                </div>
                <div class="team-settings-dash__field">
                  <label for="sport_type">競技種目 <span class="required" aria-hidden="true">*</span></label>
                  <select class="team-settings-dash__select" id="sport_type" name="sport_type" required>
                    <option value="">選択してください</option>
                    <?php foreach ($sport_options as $val => $lab) : ?>
                      <option value="<?php echo esc_attr($val); ?>" <?php selected($sport_type, $val); ?>><?php echo esc_html($lab); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="team-settings-dash__field team-settings-dash__stack">
                <label for="team_description">チーム説明</label>
                <textarea class="team-settings-dash__textarea" id="team_description" name="team_description" rows="4"><?php echo esc_textarea($team_description_display); ?></textarea>
              </div>
            </div>
          </article>

          <article class="team-form-panel" aria-labelledby="team-form-card-class-heading">
            <div class="card-header-row">
              <span class="card-icon" aria-hidden="true"><?php echo aidunite_render_theme_icon('list_alt_add', ['width' => '24', 'height' => '24']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
              <div>
                <h3 class="card-title" id="team-form-card-class-heading">分類・属性</h3>
              </div>
            </div>
            <div class="team-form-panel__body">
              <div class="form-grid form-grid-2">
                <div class="team-settings-dash__field">
                  <label for="team_category">チームカテゴリ <span class="required" aria-hidden="true">*</span></label>
                  <select class="team-settings-dash__select" id="team_category" name="team_category" required>
                    <option value="">選択してください</option>
                    <?php foreach ($category_options as $opt) : ?>
                      <option value="<?php echo esc_attr($opt); ?>" <?php selected($team_category, $opt); ?>><?php echo esc_html($opt); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="team-settings-dash__field">
                  <label for="team_type">チーム種別 <span class="required" aria-hidden="true">*</span></label>
                  <select class="team-settings-dash__select" id="team_type" name="team_type" required>
                    <option value="">選択してください</option>
                    <?php foreach ($type_options as $opt_value => $opt_label) : ?>
                      <option value="<?php echo esc_attr($opt_value); ?>" <?php selected($team_type, $opt_value); ?>><?php echo esc_html($opt_label); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="team-settings-dash__field">
                  <label for="team_gender_option">性別 <span class="required" aria-hidden="true">*</span></label>
                  <select class="team-settings-dash__select" id="team_gender_option" name="team_gender_option" required>
                    <?php if (!empty($gender_is_both_legacy)) : ?>
                      <option value="" selected disabled>選択してください（要修正）</option>
                    <?php endif; ?>
                    <option value="male" <?php selected($team_gender_option, 'male'); ?>>男子</option>
                    <option value="female" <?php selected($team_gender_option, 'female'); ?>>女子</option>
                  </select>
                </div>
                <?php
                if (function_exists('aidunite_render_team_activity_fields')) {
                    aidunite_render_team_activity_fields((int) $team_id, [
                        'context' => 'team_settings',
                        'section' => 'prefecture',
                    ]);
                }
                ?>
              </div>
            </div>
          </article>

          <article class="team-form-panel" aria-labelledby="team-form-card-location-heading">
            <div class="card-header-row">
              <span class="card-icon" aria-hidden="true">📍</span>
              <div>
                <h3 class="card-title" id="team-form-card-location-heading">活動場所</h3>
              </div>
            </div>
            <div class="team-form-panel__body">
              <div class="form-grid form-grid-2">
                <?php
                if (function_exists('aidunite_render_team_activity_fields')) {
                    aidunite_render_team_activity_fields((int) $team_id, [
                        'context' => 'team_settings',
                        'section' => 'tokyo',
                    ]);
                }
                ?>
                <div class="team-settings-dash__field">
                  <label for="team_place">活動場所・会場（詳細）</label>
                  <input class="team-settings-dash__input" type="text" id="team_place" name="team_place" value="<?php echo esc_attr($team_place); ?>" placeholder="例：「〇〇市立体育館」" />
                </div>
              </div>
              <?php
              if (function_exists('aidunite_enqueue_team_activity_fields_script')) {
                  aidunite_enqueue_team_activity_fields_script();
              }
              ?>
            </div>
          </article>

          <article class="team-form-panel team-form-panel--full" aria-labelledby="team-form-card-contact-heading">
            <div class="card-header-row">
              <span class="card-icon" aria-hidden="true">📞</span>
              <div>
                <h3 class="card-title" id="team-form-card-contact-heading">代表者・連絡先</h3>
              </div>
            </div>
            <div class="team-form-panel__body">
              <div class="form-grid form-grid-2">
                <div class="team-settings-dash__field">
                  <label for="registrant_name">代表者名 <span class="required" aria-hidden="true">*</span></label>
                  <input class="team-settings-dash__input" type="text" id="registrant_name" name="registrant_name" required value="<?php echo esc_attr($registrant_name); ?>" autocomplete="name" />
                </div>
                <div class="team-settings-dash__field">
                  <label for="contact_mail">連絡先メール <span class="required" aria-hidden="true">*</span></label>
                  <input class="team-settings-dash__input" type="email" id="contact_mail" name="contact_mail" required value="<?php echo esc_attr($contact_mail); ?>" autocomplete="email" />
                </div>
              </div>
              <div class="team-settings-dash__field team-settings-dash__stack">
                <label for="contact_phone">連絡先電話番号</label>
                <input class="team-settings-dash__input" type="tel" id="contact_phone" name="contact_phone" value="<?php echo esc_attr($contact_phone); ?>" autocomplete="tel" />
              </div>
            </div>
          </article>

          <div class="team-settings-dash__form-actions">
            <button type="submit" name="update_team_settings" class="team-settings-dash__btn team-settings-dash__btn--primary">保存する</button>
          </div>

        </div>
