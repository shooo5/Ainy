<?php
/**
 * Template Name: チーム設定
 *
 * チーム管理ダッシュボード（操作中 team ＝正規メタ編集＋管理メニュー）
 */

$page_title = '';
$page_description = '';
$page_icon = 'settings';
$required_role = 'team_leader';

$auth_result = AidUniteAuthMiddleware::require_team_leader(null, false);
$current_user_id = (int) ($auth_result->user_id ?? get_current_user_id());
if (!$auth_result->is_valid()) {
    aidunite_team_settings_redirect_denied('auth', $auth_result, $current_user_id);
}

$ctx = aidunite_team_settings_resolve_context($current_user_id);
if (is_wp_error($ctx)) {
    aidunite_team_settings_redirect_denied('context', $ctx, $current_user_id);
}

$team_id = (int) $ctx['team_id'];
$team_post = $ctx['post'];
$is_admin_team_edit = current_user_can('administrator')
    && (int) aidunite_team_settings_resolve_requested_team_id() === $team_id;

$save_success = false;
$save_error = '';
if (isset($_POST['update_team_settings'])) {
    if (
        !isset($_POST['aidunite_team_settings_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['aidunite_team_settings_nonce'])), 'aidunite_team_settings_save')
    ) {
        $save_error = 'セキュリティ検証に失敗しました。再度お試しください。';
    } else {
        $res = aidunite_team_settings_save_from_post($team_id, $current_user_id);
        if (is_wp_error($res)) {
            $save_error = $res->get_error_message();
        } else {
            $save_success = true;
            $team_post = get_post($team_id);
        }
    }
}

$team_settings = function_exists('aidunite_team_get_settings_display')
    ? aidunite_team_get_settings_display($team_id)
    : [];
$team_display_name = (string) ($team_settings['team_name'] ?? $team_post->post_title);
$team_name_kana = (string) ($team_settings['team_name_kana'] ?? '');
$team_logo_crop = function_exists('aidunite_get_team_logo_crop')
    ? aidunite_get_team_logo_crop($team_id)
    : ['x' => 0, 'y' => 0, 'zoom' => 100];
$team_description_display = (string) ($team_settings['team_description'] ?? $team_post->post_content);
$sport_type = (string) ($team_settings['sport_type'] ?? '');
$team_category = (string) ($team_settings['team_category'] ?? '');
$team_type = (string) ($team_settings['team_type'] ?? '');
if (function_exists('aidunite_team_type_to_canonical')) {
    $team_type = aidunite_team_type_to_canonical($team_type);
}
$team_gender_option = (string) ($team_settings['team_gender_option'] ?? '');
$gender_is_both_legacy = in_array($team_gender_option, ['both', 'mixed'], true);
$region = (string) ($team_settings['region'] ?? '');
$team_place = (string) ($team_settings['team_place'] ?? '');
$team_logo = (string) ($team_settings['team_logo'] ?? '');
$registrant_name = (string) ($team_settings['registrant_name'] ?? '');
$contact_mail = (string) ($team_settings['contact_mail'] ?? '');
$contact_phone = (string) ($team_settings['contact_phone'] ?? '');

$team_status_raw = (string) ($team_settings['team_status'] ?? '');
$team_status_raw = function_exists('aidunite_team_management_normalize_team_status')
    ? aidunite_team_management_normalize_team_status($team_status_raw)
    : ($team_status_raw === '' ? 'active' : $team_status_raw);
$team_status_display = function_exists('aidunite_team_management_get_team_status_label')
    ? aidunite_team_management_get_team_status_label($team_status_raw)
    : $team_status_raw;
$post_status_raw = get_post_status($team_id);
$public_status_label = function_exists('aidunite_team_management_get_post_status_label')
    ? aidunite_team_management_get_post_status_label($post_status_raw)
    : ($post_status_raw === 'publish' ? '公開' : (string) $post_status_raw);

$menu_items = aidunite_team_settings_dashboard_menu_items($team_id, $current_user_id);
$managed_team_count = function_exists('aidunite_get_managed_team_ids')
    ? count(aidunite_get_managed_team_ids($current_user_id))
    : 0;
$team_public_profile_url = function_exists('aidunite_get_team_public_profile_url')
    ? aidunite_get_team_public_profile_url($team_id)
    : get_permalink($team_id);
$team_gender_label = function_exists('aidunite_team_public_profile_gender_label')
    ? aidunite_team_public_profile_gender_label($team_gender_option)
    : ($team_gender_option === 'female' ? '女子' : ($team_gender_option === 'male' ? '男子' : '—'));
$team_category_display = $team_category !== '' ? $team_category : '—';
$registered_date_display = '';
if (!empty($team_post->post_date)) {
    $registered_date_display = class_exists('AidUniteDateUtils')
        ? AidUniteDateUtils::formatDate($team_post->post_date, AidUniteDateUtils::DATE_DISPLAY_SHORT)
        : '';
}
if ($registered_date_display === '' && !empty($team_post->post_date)) {
    $registered_date_display = date_i18n('y/m/d', strtotime($team_post->post_date));
}

$sport_options = [
    'バスケットボール' => 'バスケットボール',
    'サッカー' => 'サッカー',
    '野球' => '野球',
    'テニス' => 'テニス',
    'バレーボール' => 'バレーボール',
    'その他' => 'その他',
];
$category_options = ['小学生', '中学生', '高校生', '大学生', '社会人', 'シニア'];
$type_options = function_exists('aidunite_team_type_options_for_select')
    ? aidunite_team_type_options_for_select()
    : [
        'school' => '学校',
        'club' => 'クラブ',
        'corporate' => '企業',
        'community' => '地域',
        'other' => 'その他',
    ];

$team_summary_compact = function_exists('aidunite_should_use_web_app_integrated_ui')
    && aidunite_should_use_web_app_integrated_ui();

$team_settings_hero_body = '';
if ($team_summary_compact && function_exists('aidunite_render_team_settings_hero_body')) {
    ob_start();
    aidunite_render_team_settings_hero_body([
        'team_display_name' => $team_display_name,
        'team_logo' => $team_logo,
        'team_gender_label' => $team_gender_label,
        'team_category_display' => $team_category_display,
        'region' => $region,
        'public_status_label' => $public_status_label,
        'team_status_display' => $team_status_display,
        'team_status_raw' => $team_status_raw,
        'user_id' => $current_user_id,
        'team_public_profile_url' => $team_public_profile_url,
    ]);
    $team_settings_hero_body = ob_get_clean();
}

$web_app_hero_args = [
    'size'       => 'md',
    'title'      => 'チーム設定',
    'back'       => true,
    'active_nav' => 'none',
    'hero_body'  => $team_settings_hero_body,
    'actions'    => [
        [
            'type' => 'link',
            'href' => '#team-form',
            'icon_svg' => 'stylus',
            'aria' => '基本情報を編集',
        ],
    ],
];

ob_start();
?>

<div class="team-settings-dash page-team-settings" role="main">
  <div class="team-settings-dash__inner">
    <?php if ($save_success) : ?>
      <div class="team-settings-dash__alert team-settings-dash__alert--success" role="status">
        チーム情報を保存しました。
      </div>
    <?php elseif ($save_error !== '') : ?>
      <div class="team-settings-dash__alert" role="alert">
        <?php echo esc_html($save_error); ?>
      </div>
    <?php endif; ?>

    <?php if ($is_admin_team_edit) : ?>
      <div class="team-settings-dash__alert team-settings-dash__alert--info" role="status">
        管理者としてチーム ID <?php echo (int) $team_id; ?> を編集しています。
        <a href="<?php echo esc_url(home_url('/team-management')); ?>">チーム管理一覧へ戻る</a>
      </div>
    <?php elseif (function_exists('aidunite_render_team_settings_operating_team_hint')) : ?>
      <?php aidunite_render_team_settings_operating_team_hint($current_user_id); ?>
    <?php endif; ?>

    <section class="team-settings-dash__section" id="team-menu" aria-labelledby="team-menu-heading">
      <h2 class="team-settings-dash__section-title" id="team-menu-heading">チーム管理メニュー</h2>
      <div class="team-settings-dash__menu-grid">
        <?php foreach ($menu_items as $item) : ?>
          <?php
            $menu_card_class = 'team-settings-dash__menu-card card';
            if (!empty($item['attention'])) {
                $menu_card_class .= ' team-settings-dash__menu-card--attention';
            }
            $menu_url = (string) ($item['url'] ?? '');
            if ($menu_url === '' && (string) ($item['slug'] ?? '') === 'invite-guardian'
                && function_exists('aidunite_get_invite_guardian_page_url')) {
                $menu_url = aidunite_get_invite_guardian_page_url();
            }
            ?>
          <a class="<?php echo esc_attr($menu_card_class); ?>" href="<?php echo esc_url($menu_url !== '' ? $menu_url : '#'); ?>">
            <span class="team-settings-dash__menu-icon" aria-hidden="true"><?php echo aidunite_render_theme_icon($item['icon'], ['width' => '24', 'height' => '24']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
            <span class="team-settings-dash__menu-title"><?php echo esc_html((string) ($item['title'] ?? '')); ?></span>
            <span class="team-settings-dash__menu-desc"><?php echo esc_html($item['description']); ?></span>
            <span class="team-settings-dash__menu-meta">
              <?php if (!empty($item['badge'])) : ?>
                <span class="team-settings-dash__badge"><?php echo esc_html($item['badge']); ?></span>
              <?php endif; ?>
              <span class="team-settings-dash__menu-arrow" aria-hidden="true"><?php echo aidunite_render_theme_icon('arrow_forward', ['width' => '18', 'height' => '18']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
    </section>

    <?php if (!$team_summary_compact) : ?>
    <section
      class="team-settings-dash__section"
      id="team-summary"
      aria-labelledby="team-summary-heading"
    >
      <div class="team-settings-dash__section-head">
        <h1 class="team-settings-dash__section-title team-settings-dash__section-title--in-head" id="team-summary-heading">現在のチーム</h1>
      </div>
      <div class="team-settings-dash__summary">
        <div class="team-settings-dash__summary-hero">
          <?php if ($team_logo !== '' && function_exists('aidunite_team_logo_is_displayable') && aidunite_team_logo_is_displayable($team_logo)) : ?>
            <img class="team-settings-dash__logo" src="<?php echo esc_url($team_logo); ?>" alt="" width="88" height="88" loading="lazy" />
          <?php else : ?>
            <div class="team-settings-dash__logo team-settings-dash__logo--placeholder" aria-hidden="true"><?php echo aidunite_render_theme_icon('stadium', ['width' => '40', 'height' => '40']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
          <?php endif; ?>
          <div class="team-settings-dash__summary-name-block">
            <span class="team-settings-dash__summary-name-label">チーム名</span>
            <span class="team-settings-dash__summary-name-value"><?php echo esc_html($team_display_name); ?></span>
          </div>
        </div>

        <dl class="team-settings-dash__summary-grid">
          <div class="team-settings-dash__summary-cell">
            <dt class="team-settings-dash__summary-label">性別</dt>
            <dd class="team-settings-dash__summary-value"><?php echo esc_html($team_gender_label !== '' ? $team_gender_label : '—'); ?></dd>
          </div>
          <div class="team-settings-dash__summary-cell">
            <dt class="team-settings-dash__summary-label">カテゴリ</dt>
            <dd class="team-settings-dash__summary-value"><?php echo esc_html($team_category_display); ?></dd>
          </div>
          <div class="team-settings-dash__summary-cell">
            <dt class="team-settings-dash__summary-label">活動地域</dt>
            <dd class="team-settings-dash__summary-value"><?php echo esc_html($region !== '' ? $region : '—'); ?></dd>
          </div>
          <div class="team-settings-dash__summary-cell">
            <dt class="team-settings-dash__summary-label">代表者名</dt>
            <dd class="team-settings-dash__summary-value"><?php echo esc_html($registrant_name !== '' ? $registrant_name : '—'); ?></dd>
          </div>
          <div class="team-settings-dash__summary-cell">
            <dt class="team-settings-dash__summary-label">登録日</dt>
            <dd class="team-settings-dash__summary-value"><?php echo esc_html($registered_date_display !== '' ? $registered_date_display : '—'); ?></dd>
          </div>
          <div class="team-settings-dash__summary-cell">
            <dt class="team-settings-dash__summary-label">公開ステータス</dt>
            <dd class="team-settings-dash__summary-value team-settings-dash__summary-value--badges">
              <span class="team-settings-dash__badge"><?php echo esc_html($public_status_label); ?></span>
              <?php if ($team_status_raw !== '' && $team_status_raw !== 'active') : ?>
                <span class="team-settings-dash__badge"><?php echo esc_html($team_status_display); ?></span>
              <?php endif; ?>
            </dd>
          </div>
        </dl>

        <?php if (function_exists('aidunite_render_team_settings_summary_actions')) : ?>
        <div class="team-settings-dash__public-block">
          <?php aidunite_render_team_settings_summary_actions($current_user_id, $team_public_profile_url); ?>
        </div>
        <?php endif; ?>
      </div>
    </section>
    <?php endif; ?>

    <section class="team-settings-dash__section" id="team-form" aria-labelledby="team-form-heading">
      <h2 class="team-settings-dash__section-title" id="team-form-heading">基本情報の編集</h2>
      <form class="team-settings-dash__form" method="post" action="<?php echo esc_url(function_exists('aidunite_get_team_settings_edit_url') ? aidunite_get_team_settings_edit_url($team_id) : ''); ?>" enctype="multipart/form-data">
        <input type="hidden" name="team_id" value="<?php echo esc_attr((string) $team_id); ?>">
        <?php wp_nonce_field('aidunite_team_settings_save', 'aidunite_team_settings_nonce'); ?>
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

        <div class="team-settings-dash__form-grid team-settings-dash__form-grid--name-kana">
          <div class="team-settings-dash__field">
            <label for="team_name">チーム名 <span class="required" aria-hidden="true">*</span></label>
            <input class="team-settings-dash__input" type="text" id="team_name" name="team_name" required value="<?php echo esc_attr($team_display_name); ?>" autocomplete="organization" />
          </div>
          <div class="team-settings-dash__field">
            <label for="team_name_kana">フリガナ</label>
            <input class="team-settings-dash__input" type="text" id="team_name_kana" name="team_name_kana" value="<?php echo esc_attr($team_name_kana); ?>" autocomplete="off" inputmode="katakana" placeholder="例：エイドユナイト" />
          </div>
        </div>

        <div class="team-settings-dash__form-grid">
          <div class="team-settings-dash__field team-settings-dash__field--span-all">
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

        <div class="team-settings-dash__form-grid team-settings-dash__form-grid--2 team-settings-dash__stack">
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
        </div>

        <div class="team-settings-dash__form-grid team-settings-dash__form-grid--2 team-settings-dash__stack">
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

        <?php
        if (function_exists('aidunite_render_team_activity_fields')) {
            aidunite_render_team_activity_fields((int) $team_id, [
                'context' => 'team_settings',
                'section' => 'tokyo',
            ]);
        }
        if (function_exists('aidunite_enqueue_team_activity_fields_script')) {
            aidunite_enqueue_team_activity_fields_script();
        }
        ?>

        <div class="team-settings-dash__field team-settings-dash__stack">
          <label for="team_place">活動場所・会場（詳細）</label>
          <input class="team-settings-dash__input" type="text" id="team_place" name="team_place" value="<?php echo esc_attr($team_place); ?>" placeholder="例：〇〇市立体育館" />
        </div>

        <div class="team-settings-dash__form-grid team-settings-dash__form-grid--2 team-settings-dash__stack">
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

        <div class="team-settings-dash__actions team-settings-dash__stack--lg">
          <button type="submit" name="update_team_settings" class="team-settings-dash__btn team-settings-dash__btn--primary">保存する</button>
        </div>
      </form>
    </section>

    <?php
    $leader_transfer_candidates = function_exists('aidunite_team_get_leader_transfer_candidates')
        ? aidunite_team_get_leader_transfer_candidates($team_id, $current_user_id)
        : [];
    $leader_transfer_pending = function_exists('aidunite_team_read_leader_transfer_pending')
        ? aidunite_team_read_leader_transfer_pending($team_id)
        : null;
    $leader_transfer_days = function_exists('aidunite_payment_get_leader_transfer_checkout_days')
        ? aidunite_payment_get_leader_transfer_checkout_days()
        : 14;
    if (!empty($leader_transfer_candidates) || !empty($leader_transfer_pending)) :
    ?>
    <section class="team-settings-dash__section" id="team-leader-transfer" aria-labelledby="team-leader-transfer-heading">
      <h2 class="team-settings-dash__section-title" id="team-leader-transfer-heading">代表者の譲渡</h2>
      <div class="team-settings-dash__help">
        <p>退会前に代表者を別のメンバーへ譲渡できます。チームに月額契約がある場合、<strong>同時引き継ぎ</strong>（譲渡先が支払い設定済み）か、譲渡先が<strong><?php echo (int) $leader_transfer_days; ?>日以内に Checkout</strong>する必要があります。</p>
        <?php if (!empty($leader_transfer_pending)) : ?>
        <p class="team-settings-dash__notice">譲渡手続き中です。新代表の Checkout 期限: <?php echo esc_html((string) ($leader_transfer_pending['checkout_deadline'] ?? '')); ?></p>
        <?php else : ?>
        <div class="team-settings-dash__form-grid team-settings-dash__stack">
          <div class="team-settings-dash__field">
            <label for="leader-transfer-target">譲渡先メンバー</label>
            <select class="team-settings-dash__select" id="leader-transfer-target">
              <option value="">選択してください</option>
              <?php foreach ($leader_transfer_candidates as $candidate) : ?>
              <option value="<?php echo esc_attr((string) $candidate['user_id']); ?>">
                <?php echo esc_html($candidate['display_name']); ?>（<?php echo esc_html($candidate['role']); ?>）
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="team-settings-dash__field">
            <label><input type="checkbox" id="leader-transfer-simultaneous" value="1"> 同時引き継ぎ（譲渡先が支払い設定済みの場合）</label>
          </div>
          <div class="team-settings-dash__field">
            <button type="button" class="team-settings-dash__btn team-settings-dash__btn--outline" id="leader-transfer-submit">代表者を譲渡する</button>
            <p id="leader-transfer-message" class="team-settings-dash__form-note" style="display:none;"></p>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </section>
    <?php
    if (wp_script_is('aidunite-team-leader-transfer', 'enqueued')) {
        wp_localize_script('aidunite-team-leader-transfer', 'aiduniteTeamLeaderTransfer', [
            'teamId' => (int) $team_id,
            'restNonce' => wp_create_nonce('wp_rest'),
            'checkoutDeadlineDays' => (int) $leader_transfer_days,
            'restUrl' => rest_url('aidunite/v1/team-leader/transfer'),
        ]);
    }
    ?>
    <?php endif; ?>

    <?php if (!$team_summary_compact) : ?>
    <section class="team-settings-dash__section" id="team-help" aria-labelledby="team-help-heading">
      <h2 class="team-settings-dash__section-title" id="team-help-heading">補助案内</h2>
      <div class="team-settings-dash__help">
        <p>この画面は<strong>操作中のチーム</strong>に対して動作します。複数チームの代表者は、画面上部の<strong>「操作中」</strong>からチームを切り替えてください。</p>
      </div>
    </section>
    <?php endif; ?>
  </div>
</div>

<?php
$main_content = ob_get_clean();
// 一体型 UI: ainy-webapp-content 直下に配置（dashboard-section ラッパー不要）
$dashboard_direct_content = true;
// 認証・代表者チェックは本ファイル先頭で完了。テンプレートの require_role 二重チェックで
// team_leader_id のみ一致し aidunite_role が未設定の代表者が弾かれるのを防ぐ。
unset($required_role);
include get_template_directory() . '/page-template-dashboard.php';
