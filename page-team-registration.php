<?php
/*
Template Name: 新規チーム登録ページ
*/

if (!is_user_logged_in()) {
    wp_safe_redirect(wp_login_url(get_permalink()));
    exit;
}

if (function_exists('aidunite_user_has_pending_team_application')
    && aidunite_user_has_pending_team_application(get_current_user_id())) {
    wp_safe_redirect(home_url('/mypage/'));
    exit;
}

$current_user = wp_get_current_user();
$rep_phone = (string) get_user_meta($current_user->ID, 'phone', true);
if ($rep_phone === '') {
    $rep_phone = (string) get_user_meta($current_user->ID, 'user_phone', true);
}

$team_reg_css = get_stylesheet_directory() . '/assets/css/pages/team-registration.css';
$team_reg_js = get_stylesheet_directory() . '/assets/js/team/team-registration-wizard.js';

wp_enqueue_style(
    'team-registration-style',
    get_stylesheet_directory_uri() . '/assets/css/pages/team-registration.css',
    ['aidunite-style'],
    is_readable($team_reg_css) ? (string) filemtime($team_reg_css) : '1.0.0'
);

$team_logo_js = get_stylesheet_directory() . '/assets/js/team/team-logo-upload.js';

wp_enqueue_script(
    'aidunite-team-logo-upload',
    get_stylesheet_directory_uri() . '/assets/js/team/team-logo-upload.js',
    [],
    is_readable($team_logo_js) ? (string) filemtime($team_logo_js) : '1.0.0',
    true
);

wp_enqueue_script(
    'team-registration-wizard',
    get_stylesheet_directory_uri() . '/assets/js/team/team-registration-wizard.js',
    ['aidunite-team-logo-upload'],
    is_readable($team_reg_js) ? (string) filemtime($team_reg_js) : '1.0.0',
    true
);

$terms_url = home_url('/terms/');
$privacy_url = home_url('/privacy/');

$team_logo_upload_config = function_exists('aidunite_get_team_logo_upload_script_config')
    ? aidunite_get_team_logo_upload_script_config('save_team_metabox', 'team_metabox_nonce', 'team_registration_logo_upload')
    : [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('save_team_metabox'),
        'nonceField' => 'team_metabox_nonce',
        'uploadAction' => 'team_registration_logo_upload',
        'logoMaxBytes' => 10 * 1024 * 1024,
        'logoMaxLabel' => '10MB',
    ];

wp_localize_script('aidunite-team-logo-upload', 'aiduniteTeamLogoUploadConfig', $team_logo_upload_config);

wp_localize_script('team-registration-wizard', 'aiduniteTeamRegistration', [
    'ajaxUrl' => $team_logo_upload_config['ajaxUrl'],
    'nonce' => $team_logo_upload_config['nonce'],
    'mypageUrl' => home_url('/mypage/'),
    'completeUrl' => home_url('/team-registration-complete/'),
    'termsUrl' => $terms_url,
    'privacyUrl' => $privacy_url,
    'logoMaxBytes' => $team_logo_upload_config['logoMaxBytes'],
    'logoMaxLabel' => $team_logo_upload_config['logoMaxLabel'],
    'currentUser' => [
        'display_name' => $current_user->display_name,
        'user_email' => $current_user->user_email,
    ],
]);

if (function_exists('aidunite_enqueue_team_activity_fields_script')) {
    aidunite_enqueue_team_activity_fields_script();
}

get_header();

$mypage_url = home_url('/mypage/');
?>

<div class="team-registration-page" data-gender-theme="">
  <div class="team-reg-bg" aria-hidden="true">
    <div class="team-reg-bg-gradient"></div>
  </div>

  <div class="team-reg-container">
    <header class="team-reg-hero">
      <h1 class="team-reg-title">チーム作成</h1>
      <p class="team-reg-note">男子・女子は<strong>別チーム</strong>として登録されます。男女両方は<strong>1回の申請</strong>でまとめて送れます。</p>
    </header>

    <ol class="team-reg-progress" aria-label="申請の進捗" data-team-reg-progress>
      <li class="team-reg-progress__item is-active" data-progress-slot="0">
        <span class="team-reg-progress__dot" aria-hidden="true"></span>
        <span class="team-reg-progress__label" data-progress-label>申請種別・共通</span>
      </li>
      <li class="team-reg-progress__connector" aria-hidden="true"></li>
      <li class="team-reg-progress__item" data-progress-slot="1">
        <span class="team-reg-progress__dot" aria-hidden="true"></span>
        <span class="team-reg-progress__label" data-progress-label>活動・連絡</span>
      </li>
      <li class="team-reg-progress__connector" aria-hidden="true"></li>
      <li class="team-reg-progress__item" data-progress-slot="2">
        <span class="team-reg-progress__dot" aria-hidden="true"></span>
        <span class="team-reg-progress__label" data-progress-label>チーム詳細</span>
      </li>
      <li class="team-reg-progress__connector" aria-hidden="true" data-progress-connector-extra></li>
      <li class="team-reg-progress__item" data-progress-slot="3" hidden>
        <span class="team-reg-progress__dot" aria-hidden="true"></span>
        <span class="team-reg-progress__label" data-progress-label>女子チーム</span>
      </li>
      <li class="team-reg-progress__connector" aria-hidden="true"></li>
      <li class="team-reg-progress__item" data-progress-slot="4">
        <span class="team-reg-progress__dot" aria-hidden="true"></span>
        <span class="team-reg-progress__label" data-progress-label>確認・申請</span>
      </li>
    </ol>

    <div class="team-reg-card">
      <form method="post" id="team-registration-form" class="team-reg-form" novalidate enctype="multipart/form-data">
        <?php wp_nonce_field('save_team_metabox', 'team_metabox_nonce'); ?>

        <input type="hidden" name="registration_scope" id="registration_scope" value="">
        <input type="hidden" name="team_gender_option" id="team_gender_option" value="">
        <input type="hidden" name="representative_name" value="<?php echo esc_attr($current_user->display_name); ?>">
        <input type="hidden" name="representative_email" value="<?php echo esc_attr($current_user->user_email); ?>">
        <input type="hidden" name="representative_phone" value="<?php echo esc_attr($rep_phone); ?>">

        <input type="hidden" id="team_logo" name="team_logo" value="">
        <input type="hidden" id="team_logo_offset_x" name="team_logo_offset_x" value="0">
        <input type="hidden" id="team_logo_offset_y" name="team_logo_offset_y" value="0">
        <input type="hidden" id="team_logo_zoom" name="team_logo_zoom" value="100">

        <input type="hidden" id="team_male_logo" name="team_male_logo" value="">
        <input type="hidden" id="team_male_logo_offset_x" name="team_male_logo_offset_x" value="0">
        <input type="hidden" id="team_male_logo_offset_y" name="team_male_logo_offset_y" value="0">
        <input type="hidden" id="team_male_logo_zoom" name="team_male_logo_zoom" value="100">

        <input type="hidden" id="team_female_logo" name="team_female_logo" value="">
        <input type="hidden" id="team_female_logo_offset_x" name="team_female_logo_offset_x" value="0">
        <input type="hidden" id="team_female_logo_offset_y" name="team_female_logo_offset_y" value="0">
        <input type="hidden" id="team_female_logo_zoom" name="team_female_logo_zoom" value="100">

        <!-- STEP 1: 申請種別・共通 -->
        <section class="team-reg-step is-active" data-step="1" aria-labelledby="team-reg-step-1-title">
          <div class="team-reg-step-head team-reg-step-head--compact">
            <h2 id="team-reg-step-1-title" class="team-reg-step-title">どのチームを登録しますか？</h2>
          </div>

          <fieldset class="team-reg-scope" data-team-reg-scope>
            <legend class="team-reg-label">申請するチーム <span class="team-reg-required" aria-hidden="true">*</span></legend>
            <div class="team-reg-scope__options">
              <label class="team-reg-scope__option">
                <input type="radio" name="registration_scope_radio" value="male" class="team-reg-scope__input" required>
                <span class="team-reg-scope__card">男子チーム</span>
              </label>
              <label class="team-reg-scope__option">
                <input type="radio" name="registration_scope_radio" value="female" class="team-reg-scope__input">
                <span class="team-reg-scope__card">女子チーム</span>
              </label>
              <label class="team-reg-scope__option">
                <input type="radio" name="registration_scope_radio" value="both" class="team-reg-scope__input">
                <span class="team-reg-scope__card">男女両方</span>
              </label>
            </div>
          </fieldset>

          <div class="team-reg-step-head team-reg-step-head--compact">
            <h3 class="team-reg-step-subtitle">共通情報</h3>
          </div>

          <div class="team-reg-fields-grid">
            <div class="team-reg-fields-grid__cell">
              <div class="team-reg-field">
                <label for="team_name_base" class="team-reg-label">学校名・クラブ名（ベース） <span class="team-reg-required" aria-hidden="true">*</span></label>
                <input type="text" id="team_name_base" name="team_name_base" class="team-reg-input" required autocomplete="organization" placeholder="例：○○中学校">
                <p class="team-reg-field-hint" data-team-name-hint>男子・女子は別チーム名で登録されます（「 男子」「 女子」を付与）。</p>
              </div>
            </div>
            <div class="team-reg-fields-grid__cell">
              <div class="team-reg-field">
                <label for="team_name_kana" class="team-reg-label">フリガナ</label>
                <input type="text" id="team_name_kana" name="team_name_kana" class="team-reg-input" autocomplete="off" inputmode="katakana" placeholder="例：マルマルチュウガッコウ">
              </div>
            </div>
          </div>

          <div class="team-reg-field">
            <label for="sport_type" class="team-reg-label">スポーツ <span class="team-reg-required" aria-hidden="true">*</span></label>
            <select id="sport_type" name="sport_type" class="team-reg-input team-reg-select" required>
              <option value="">選択してください</option>
              <option value="バスケットボール">バスケットボール</option>
              <option value="サッカー">サッカー</option>
              <option value="野球">野球</option>
              <option value="テニス">テニス</option>
              <option value="バレーボール">バレーボール</option>
              <option value="その他">その他</option>
            </select>
          </div>

          <div class="team-reg-field">
            <label for="team_category" class="team-reg-label">年代 <span class="team-reg-required" aria-hidden="true">*</span></label>
            <select id="team_category" name="team_category" class="team-reg-input team-reg-select" required>
              <option value="">選択してください</option>
              <option value="小学生">小学生</option>
              <option value="中学生">中学生</option>
              <option value="高校生">高校生</option>
              <option value="大学生">大学生</option>
              <option value="社会人">社会人</option>
              <option value="シニア">シニア</option>
            </select>
          </div>

          <div class="team-reg-field">
            <label for="team_type" class="team-reg-label">所属 <span class="team-reg-required" aria-hidden="true">*</span></label>
            <select id="team_type" name="team_type" class="team-reg-input team-reg-select" required>
              <option value="">選択してください</option>
              <?php
              $team_type_options = function_exists('aidunite_team_type_options_for_select')
                  ? aidunite_team_type_options_for_select()
                  : [
                      'school' => '学校',
                      'club' => 'クラブ',
                      'corporate' => '企業',
                      'community' => '地域',
                      'other' => 'その他',
                  ];
              foreach ($team_type_options as $opt_value => $opt_label) :
                  ?>
              <option value="<?php echo esc_attr($opt_value); ?>"><?php echo esc_html($opt_label); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="team-reg-actions">
            <a href="<?php echo esc_url($mypage_url); ?>" class="team-reg-btn team-reg-btn--ghost">戻る</a>
            <button type="button" class="team-reg-btn team-reg-btn--primary" data-action="next">次へ</button>
          </div>
        </section>

        <!-- STEP 2: 活動・紹介（共通） -->
        <section class="team-reg-step" data-step="2" aria-labelledby="team-reg-step-2-title" hidden>
          <div class="team-reg-step-head team-reg-step-head--compact">
            <h2 id="team-reg-step-2-title" class="team-reg-step-title">活動・連絡先</h2>
          </div>

          <div class="team-reg-fields-grid">
            <div class="team-reg-fields-grid__cell">
              <?php
              if (function_exists('aidunite_render_team_activity_fields')) {
                  aidunite_render_team_activity_fields(0, ['context' => 'team_registration', 'section' => 'prefecture']);
              }
              ?>
            </div>
            <div class="team-reg-fields-grid__cell team-reg-fields-grid__cell--tokyo-type" id="team-reg-tokyo-type-cell" style="display: none;">
              <?php
              if (function_exists('aidunite_render_team_activity_fields')) {
                  aidunite_render_team_activity_fields(0, ['context' => 'team_registration', 'section' => 'tokyo_type']);
              }
              ?>
            </div>
          </div>

          <div class="team-reg-fields-grid team-reg-fields-grid--tokyo-area" id="aidunite-team-activity-tokyo-wrap" style="display: none;">
            <div class="team-reg-fields-grid__cell team-reg-fields-grid__cell--tokyo-area">
              <?php
              if (function_exists('aidunite_render_team_activity_fields')) {
                  aidunite_render_team_activity_fields(0, ['context' => 'team_registration', 'section' => 'tokyo_ward']);
                  aidunite_render_team_activity_fields(0, ['context' => 'team_registration', 'section' => 'tokyo_city']);
              }
              ?>
            </div>
            <div class="team-reg-fields-grid__cell team-reg-fields-grid__cell--tokyo-spacer" aria-hidden="true"></div>
          </div>

          <div class="team-reg-fields-grid">
            <div class="team-reg-fields-grid__cell">
              <div class="team-reg-field">
                <label for="team_sns_url" class="team-reg-label">サイト</label>
                <input type="url" id="team_sns_url" name="team_sns_url" class="team-reg-input" placeholder="https://instagram.com/..." inputmode="url">
              </div>
            </div>
            <div class="team-reg-fields-grid__cell">
              <div class="team-reg-field">
                <label for="team_website" class="team-reg-label">公式サイト</label>
                <input type="url" id="team_website" name="team_website" class="team-reg-input" placeholder="https://example.com" inputmode="url">
              </div>
            </div>
          </div>

          <div class="team-reg-actions">
            <button type="button" class="team-reg-btn team-reg-btn--ghost" data-action="prev">戻る</button>
            <button type="button" class="team-reg-btn team-reg-btn--primary" data-action="next">次へ</button>
          </div>
        </section>

        <!-- STEP 3: 男子 -->
        <section class="team-reg-step" data-step="3" data-gender-panel="male" aria-labelledby="team-reg-step-3-title" hidden>
          <div class="team-reg-step-head team-reg-step-head--compact">
            <h2 id="team-reg-step-3-title" class="team-reg-step-title">男子チーム情報</h2>
          </div>

          <?php
          get_template_part('template-parts/team/logo-upload-field', null, [
              'variant'    => 'registration',
              'gender_key' => 'male',
          ]);
          ?>

          <div class="team-reg-field">
            <label for="team_male_name" class="team-reg-label">チーム名 <span class="team-reg-required" aria-hidden="true">*</span></label>
            <input type="text" id="team_male_name" name="team_male_name" class="team-reg-input" data-auto-team-name="male">
          </div>

          <div class="team-reg-field team-reg-field--full">
            <label for="team_male_description" class="team-reg-label">紹介文</label>
            <textarea id="team_male_description" name="team_male_description" class="team-reg-input team-reg-textarea team-reg-textarea--compact" rows="3" placeholder="男子チームの活動内容や特徴"></textarea>
          </div>

          <div class="team-reg-actions">
            <button type="button" class="team-reg-btn team-reg-btn--ghost" data-action="prev">戻る</button>
            <button type="button" class="team-reg-btn team-reg-btn--primary" data-action="next">次へ</button>
          </div>
        </section>

        <!-- STEP 4: 女子 -->
        <section class="team-reg-step" data-step="4" data-gender-panel="female" aria-labelledby="team-reg-step-4-title" hidden>
          <div class="team-reg-step-head team-reg-step-head--compact">
            <h2 id="team-reg-step-4-title" class="team-reg-step-title">女子チーム情報</h2>
          </div>

          <?php
          get_template_part('template-parts/team/logo-upload-field', null, [
              'variant'    => 'registration',
              'gender_key' => 'female',
          ]);
          ?>

          <div class="team-reg-field">
            <label for="team_female_name" class="team-reg-label">チーム名 <span class="team-reg-required" aria-hidden="true">*</span></label>
            <input type="text" id="team_female_name" name="team_female_name" class="team-reg-input" data-auto-team-name="female">
          </div>

          <div class="team-reg-field team-reg-field--full">
            <label for="team_female_description" class="team-reg-label">紹介文</label>
            <textarea id="team_female_description" name="team_female_description" class="team-reg-input team-reg-textarea team-reg-textarea--compact" rows="3" placeholder="女子チームの活動内容や特徴"></textarea>
          </div>

          <div class="team-reg-actions">
            <button type="button" class="team-reg-btn team-reg-btn--ghost" data-action="prev">戻る</button>
            <button type="button" class="team-reg-btn team-reg-btn--primary" data-action="next">次へ</button>
          </div>
        </section>

        <!-- STEP 5: 確認 -->
        <section class="team-reg-step" data-step="5" data-confirm-step aria-labelledby="team-reg-step-5-title" hidden>
          <div class="team-reg-step-head team-reg-step-head--compact">
            <h2 id="team-reg-step-5-title" class="team-reg-step-title">確認・申請</h2>
          </div>

          <div id="team-reg-summary-root" class="team-reg-summary-root"></div>

          <div class="team-reg-submit-wrap">
            <button type="submit" class="team-reg-btn team-reg-btn--submit" id="team-reg-submit">
              チーム登録を申請する
            </button>
            <p class="team-reg-submit-note">承認後すぐに、<br>練習試合募集やスケジュール共有を始められます。</p>
          </div>

          <div class="team-reg-actions team-reg-actions--confirm">
            <button type="button" class="team-reg-btn team-reg-btn--ghost" data-action="prev">戻る</button>
          </div>
        </section>
      </form>
    </div>
  </div>
</div>

<?php get_footer(); ?>
