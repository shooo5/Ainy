<?php
/**
 * テーマ共通：CSS/JS の読込（functions.php から移設）
 */

if (!defined('ABSPATH')) {
  exit;
}

require_once get_stylesheet_directory() . '/functions/common/page-asset-loader.php';

function aidunite_enqueue_styles() {
  // 親テーマのCSSを除外（干渉を防ぐ）
  wp_dequeue_style('vantage-style');
  wp_dequeue_style('vantage-responsive');

  // メインCSSを読み込み（バージョン更新でキャッシュクリア）
  $aidunite_style_css = get_stylesheet_directory() . '/assets/css/aidunite-style.css';
  wp_enqueue_style(
    'aidunite-style',
    get_stylesheet_directory_uri() . '/assets/css/aidunite-style.css',
    array(),
    is_readable($aidunite_style_css) ? (string) filemtime($aidunite_style_css) : '1.1.0'
  );
  $site_shell_css = get_stylesheet_directory() . '/assets/css/layout/site-shell.css';
  if (is_readable($site_shell_css)) {
    wp_enqueue_style(
      'aidunite-site-shell',
      get_stylesheet_directory_uri() . '/assets/css/layout/site-shell.css',
      array('aidunite-style'),
      (string) filemtime($site_shell_css)
    );
  }
  $gender_theme_css = get_stylesheet_directory() . '/assets/css/layout/gender-theme-tokens.css';
  if (is_readable($gender_theme_css)) {
    wp_enqueue_style(
      'aidunite-gender-theme-tokens',
      get_stylesheet_directory_uri() . '/assets/css/layout/gender-theme-tokens.css',
      array('aidunite-style'),
      (string) filemtime($gender_theme_css)
    );
  }
  $team_theme_css = get_stylesheet_directory() . '/assets/css/layout/team-theme.css';
  if (is_readable($team_theme_css)) {
    wp_enqueue_style(
      'aidunite-team-theme',
      get_stylesheet_directory_uri() . '/assets/css/layout/team-theme.css',
      array('aidunite-style', 'aidunite-gender-theme-tokens'),
      (string) filemtime($team_theme_css)
    );
  }
  $web_app_ui_css = get_stylesheet_directory() . '/assets/css/layout/web-app-integrated-ui.css';
  if (is_readable($web_app_ui_css)) {
    wp_enqueue_style(
      'aidunite-web-app-integrated-ui',
      get_stylesheet_directory_uri() . '/assets/css/layout/web-app-integrated-ui.css',
      array('aidunite-style', 'aidunite-team-theme'),
      (string) filemtime($web_app_ui_css)
    );
  }
  wp_enqueue_style(
    'google-fonts',
    'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+JP:wght@300;400;500;700&display=swap',
    array(),
    null
  );
  wp_enqueue_style('material-symbols-outlined', 'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&icon_names=thumb_up', array(), null);

  // 共通コンポーネントCSSを読み込み
  wp_enqueue_style('tab-ui-style', get_stylesheet_directory_uri() . '/assets/css/components/tab-ui.css', array('aidunite-style'), '1.0.0');
  $ui_state_css = get_stylesheet_directory() . '/assets/css/components/ui-state.css';
  wp_enqueue_style(
    'aidunite-ui-state',
    get_stylesheet_directory_uri() . '/assets/css/components/ui-state.css',
    array('aidunite-style', 'tab-ui-style'),
    is_readable($ui_state_css) ? (string) filemtime($ui_state_css) : '1.0.0'
  );
  $button_css = get_stylesheet_directory() . '/assets/css/components/button.css';
  wp_enqueue_style(
    'button-style',
    get_stylesheet_directory_uri() . '/assets/css/components/button.css',
    array('aidunite-style'),
    is_readable($button_css) ? (string) filemtime($button_css) : '1.1.0'
  );
  $auth_button_css = get_stylesheet_directory() . '/assets/css/components/auth-button.css';
  if (is_readable($auth_button_css)) {
    wp_enqueue_style(
      'ainy-auth-button',
      get_stylesheet_directory_uri() . '/assets/css/components/auth-button.css',
      array('aidunite-style', 'button-style'),
      (string) filemtime($auth_button_css)
    );
  }
  wp_enqueue_style('card-style', get_stylesheet_directory_uri() . '/assets/css/components/card.css', array('aidunite-style'), '1.0.0');
  wp_enqueue_style('form-style', get_stylesheet_directory_uri() . '/assets/css/components/form.css', array('aidunite-style'), '1.0.0');
  wp_enqueue_style('loading-spinner-style', get_stylesheet_directory_uri() . '/assets/css/components/loading-spinner.css', array('aidunite-style'), '1.0.0');
  wp_enqueue_style('badge-style', get_stylesheet_directory_uri() . '/assets/css/components/badge.css', array('aidunite-style'), '1.0.1');
  wp_enqueue_style(
    'ainy-global-nav',
    get_stylesheet_directory_uri() . '/assets/css/layout/ainy-global-nav.css',
    array('aidunite-style', 'badge-style'),
    '1.0.0'
  );
  $ainy_header_css = get_stylesheet_directory() . '/assets/css/layout/ainy-header.css';
  if (is_readable($ainy_header_css)) {
    wp_enqueue_style(
      'ainy-header',
      get_stylesheet_directory_uri() . '/assets/css/layout/ainy-header.css',
      array('aidunite-style', 'ainy-global-nav', 'ainy-auth-button'),
      (string) filemtime($ainy_header_css)
    );
  }
  $ainy_header_shell_js = get_stylesheet_directory() . '/assets/js/common/ainy-header-shell.js';
  if (is_readable($ainy_header_shell_js)) {
    wp_enqueue_script(
      'ainy-header-shell',
      get_stylesheet_directory_uri() . '/assets/js/common/ainy-header-shell.js',
      array('aidunite-dom-utils'),
      (string) filemtime($ainy_header_shell_js),
      true
    );
  }
  wp_enqueue_style('tooltip-style', get_stylesheet_directory_uri() . '/assets/css/pages/tooltip.css', array('aidunite-style'), '1.0.0');

  // 共通JavaScriptを読み込み（getDocument 等は dom-utils に集約）
  wp_enqueue_script('aidunite-dom-utils', get_stylesheet_directory_uri() . '/assets/js/common/dom-utils.js', array(), '1.0.0', true);
  $theme_icons_css = get_stylesheet_directory() . '/assets/css/components/theme-icons.css';
  wp_enqueue_style(
    'aidunite-theme-icons',
    get_stylesheet_directory_uri() . '/assets/css/components/theme-icons.css',
    array('aidunite-style'),
    is_readable($theme_icons_css) ? (string) filemtime($theme_icons_css) : '1.0.0'
  );
  $theme_icons_js = get_stylesheet_directory() . '/assets/js/common/theme-icons.js';
  wp_enqueue_script(
    'aidunite-theme-icons',
    get_stylesheet_directory_uri() . '/assets/js/common/theme-icons.js',
    array('aidunite-dom-utils'),
    is_readable($theme_icons_js) ? (string) filemtime($theme_icons_js) : '1.0.0',
    true
  );
  if (function_exists('aidunite_build_theme_icon_js_manifest') && function_exists('aidunite_get_schedule_type_icon_map')) {
    wp_localize_script('aidunite-theme-icons', 'aiduniteThemeIcons', array(
      'svgMap'            => aidunite_build_theme_icon_js_manifest(),
      'scheduleTypeIcons' => aidunite_get_schedule_type_icon_map(),
    ));
  }
  wp_enqueue_style('aidunite-toast-notification', get_stylesheet_directory_uri() . '/assets/css/components/toast-notification.css', array('aidunite-style', 'aidunite-theme-icons'), '1.0.4');
  wp_enqueue_script('aidunite-toast-notification', get_stylesheet_directory_uri() . '/assets/js/common/toast-notification.js', array('aidunite-dom-utils', 'aidunite-theme-icons'), '1.0.6', true);
  wp_enqueue_style('aidunite-confirm-modal', get_stylesheet_directory_uri() . '/assets/css/components/confirm-modal.css', array('aidunite-style', 'button-style'), '1.0.0');
  wp_enqueue_script('aidunite-confirm-modal', get_stylesheet_directory_uri() . '/assets/js/common/confirm-modal.js', array('aidunite-dom-utils'), '1.0.0', true);
  $feedback_utils_js = get_stylesheet_directory() . '/assets/js/common/feedback-utils.js';
  wp_enqueue_script(
    'aidunite-feedback-utils',
    get_stylesheet_directory_uri() . '/assets/js/common/feedback-utils.js',
    array('aidunite-toast-notification', 'aidunite-confirm-modal'),
    is_readable($feedback_utils_js) ? (string) filemtime($feedback_utils_js) : '1.0.0',
    true
  );
  $loading_spinner_js = get_stylesheet_directory() . '/assets/js/common/loading-spinner-utils.js';
  wp_enqueue_script(
    'loading-spinner-utils',
    get_stylesheet_directory_uri() . '/assets/js/common/loading-spinner-utils.js',
    array('aidunite-dom-utils'),
    is_readable($loading_spinner_js) ? (string) filemtime($loading_spinner_js) : '1.0.1',
    true
  );
  $ui_state_helpers_js = get_stylesheet_directory() . '/assets/js/common/ui-state-helpers.js';
  wp_enqueue_script(
    'aidunite-ui-state-helpers',
    get_stylesheet_directory_uri() . '/assets/js/common/ui-state-helpers.js',
    array('loading-spinner-utils'),
    is_readable($ui_state_helpers_js) ? (string) filemtime($ui_state_helpers_js) : '1.0.0',
    true
  );
  $ui_tab_controller_js = get_stylesheet_directory() . '/assets/js/common/ui-tab-controller.js';
  wp_enqueue_script(
    'aidunite-ui-tab-controller',
    get_stylesheet_directory_uri() . '/assets/js/common/ui-tab-controller.js',
    array('aidunite-dom-utils'),
    is_readable($ui_tab_controller_js) ? (string) filemtime($ui_tab_controller_js) : '1.0.0',
    true
  );
  wp_enqueue_script('form-notifications', get_stylesheet_directory_uri() . '/assets/js/common/form-notifications.js', array('loading-spinner-utils', 'aidunite-toast-notification'), '1.0.0', true);
  wp_enqueue_script('tooltip-js', get_stylesheet_directory_uri() . '/assets/js/common/tooltip.js', array(), '1.0.0', true);
  wp_enqueue_script('email-halfwidth', get_stylesheet_directory_uri() . '/assets/js/common/email-halfwidth.js', array(), '1.0.0', true);
  wp_enqueue_script('aidunite-minute-select', get_stylesheet_directory_uri() . '/assets/js/common/minute-select.js', array(), '1.0.0', true);

  if (is_front_page()
    && empty($GLOBALS['aidunite_team_chat_page'])
    && (!function_exists('aidunite_is_web_app_page') || !aidunite_is_web_app_page())) {
    $ainy_footer_css = get_stylesheet_directory() . '/assets/css/layout/ainy-footer.css';
    if (is_readable($ainy_footer_css)) {
      wp_enqueue_style(
        'ainy-footer',
        get_stylesheet_directory_uri() . '/assets/css/layout/ainy-footer.css',
        array('aidunite-style'),
        (string) filemtime($ainy_footer_css)
      );
    }
  }

  if (is_page('login') || is_page_template('page-login.php')) {
    $login_css = get_stylesheet_directory() . '/assets/css/pages/login.css';
    if (is_readable($login_css)) {
      wp_enqueue_style(
        'login-style',
        get_stylesheet_directory_uri() . '/assets/css/pages/login.css',
        array('aidunite-style', 'button-style', 'form-style'),
        (string) filemtime($login_css)
      );
    }
    $login_js = get_stylesheet_directory() . '/assets/js/pages/login.js';
    if (is_readable($login_js)) {
      wp_enqueue_script(
        'aidunite-login-page',
        get_stylesheet_directory_uri() . '/assets/js/pages/login.js',
        array('aidunite-dom-utils'),
        (string) filemtime($login_js),
        true
      );
    }
  }

  // ページ専用CSSの条件分岐読み込み
  if (is_page('match-board-own') || is_page_template('page-match-board-own.php')) {
    $match_board_scroll_css = get_stylesheet_directory() . '/assets/css/pages/match-board-scroll-critical.css';
    if (is_readable($match_board_scroll_css)) {
      wp_enqueue_style(
        'aidunite-match-board-scroll-critical',
        get_stylesheet_directory_uri() . '/assets/css/pages/match-board-scroll-critical.css',
        array('aidunite-style'),
        (string) filemtime($match_board_scroll_css)
      );
    }
    wp_enqueue_style('match-board-style', get_stylesheet_directory_uri() . '/assets/css/pages/match-board.css', array('aidunite-style', 'badge-style'), '1.0.3');
    $match_board_page_js = get_stylesheet_directory() . '/assets/js/pages/match-board-page.js';
    if (is_readable($match_board_page_js)) {
      wp_enqueue_script(
        'aidunite-match-board-page',
        get_stylesheet_directory_uri() . '/assets/js/pages/match-board-page.js',
        array('aidunite-theme-icons'),
        (string) filemtime($match_board_page_js),
        true
      );
    }
  }

  if (function_exists('aidunite_enqueue_recruit_quick_modal_assets')) {
    aidunite_enqueue_recruit_quick_modal_assets();
  }

  if (is_page('schedule-management') || is_page('my-schedule') || is_page_template('page-schedule-management.php')) {
    $schedule_css = get_stylesheet_directory() . '/assets/css/pages/schedule.css';
    wp_enqueue_style('schedule-style', get_stylesheet_directory_uri() . '/assets/css/pages/schedule.css', array('aidunite-style'), is_readable($schedule_css) ? (string) filemtime($schedule_css) : '1.2.0', 'all');
    if (is_page('schedule-management') || is_page_template('page-schedule-management.php')) {
      if (function_exists('aidunite_enqueue_schedule_quick_modal_styles')) {
        aidunite_enqueue_schedule_quick_modal_styles();
      }
      $schedule_mgmt_css = get_stylesheet_directory() . '/assets/css/pages/schedule-management-page.css';
      if (is_readable($schedule_mgmt_css)) {
        wp_enqueue_style(
          'aidunite-schedule-management-page',
          get_stylesheet_directory_uri() . '/assets/css/pages/schedule-management-page.css',
          array('schedule-style', 'aidunite-schedule-quick-modal'),
          (string) filemtime($schedule_mgmt_css)
        );
      }
    }
    wp_enqueue_script('aidunite-date-utils', get_stylesheet_directory_uri() . '/assets/js/common/date-utils.js', array('aidunite-dom-utils'), '1.0.0', true);
    wp_enqueue_script('aidunite-schedule-utils', get_stylesheet_directory_uri() . '/assets/js/common/schedule-utils.js', array('aidunite-date-utils', 'aidunite-theme-icons'), '1.0.1', true);
    wp_enqueue_script('aidunite-ajax-utils', get_stylesheet_directory_uri() . '/assets/js/common/ajax-utils.js', array('aidunite-dom-utils', 'loading-spinner-utils', 'aidunite-toast-notification'), '1.0.0', true);
    wp_localize_script('aidunite-ajax-utils', 'aiduniteScheduleRest', [
      'root'  => rest_url(),
      'nonce' => wp_create_nonce('wp_rest'),
    ]);
    wp_enqueue_script('aidunite-schedule-loader', get_stylesheet_directory_uri() . '/assets/js/common/schedule-loader.js', array('aidunite-schedule-utils', 'aidunite-ajax-utils'), '1.0.1', true);
    wp_enqueue_script('aidunite-form-utils', get_stylesheet_directory_uri() . '/assets/js/common/form-utils.js', array('aidunite-dom-utils'), '1.0.0', true);
    wp_enqueue_script('aidunite-schedule-modal', get_stylesheet_directory_uri() . '/assets/js/common/schedule-modal.js', array('aidunite-schedule-utils', 'aidunite-ajax-utils', 'aidunite-toast-notification', 'aidunite-confirm-modal'), '1.0.2', true);
    $quick_modal_js = get_stylesheet_directory() . '/assets/js/common/schedule-quick-modal.js';
    if (is_readable($quick_modal_js)) {
      wp_enqueue_script(
        'aidunite-schedule-quick-modal',
        get_stylesheet_directory_uri() . '/assets/js/common/schedule-quick-modal.js',
        array('aidunite-schedule-modal', 'aidunite-date-utils', 'aidunite-confirm-modal'),
        (string) filemtime($quick_modal_js),
        true
      );
    }
  }

  if (is_page('attendance-report') || is_page_template('page-attendance-report.php')
    || is_page('attendance-management') || is_page_template('page-attendance-management.php')) {
    $attendance_css = get_stylesheet_directory() . '/assets/css/pages/attendance.css';
    if (is_readable($attendance_css)) {
      wp_enqueue_style(
        'aidunite-attendance-page',
        get_stylesheet_directory_uri() . '/assets/css/pages/attendance.css',
        array('aidunite-style', 'button-style'),
        (string) filemtime($attendance_css)
      );
    }
    if (is_page('attendance-report') || is_page_template('page-attendance-report.php')) {
      $attendance_js = get_stylesheet_directory() . '/assets/js/pages/attendance-report.js';
      if (is_readable($attendance_js)) {
        wp_enqueue_script(
          'aidunite-attendance-report',
          get_stylesheet_directory_uri() . '/assets/js/pages/attendance-report.js',
          array(),
          (string) filemtime($attendance_js),
          true
        );
      }
    }
  }

  if (is_page('schedule-edit') || is_page_template('page-schedule-edit.php')) {
    $schedule_edit_css = get_stylesheet_directory() . '/assets/css/pages/schedule.css';
    wp_enqueue_style(
      'schedule-style',
      get_stylesheet_directory_uri() . '/assets/css/pages/schedule.css',
      array('aidunite-style'),
      is_readable($schedule_edit_css) ? (string) filemtime($schedule_edit_css) : '1.2.0',
      'all'
    );
    wp_enqueue_script(
      'aidunite-ajax-utils',
      get_stylesheet_directory_uri() . '/assets/js/common/ajax-utils.js',
      array('aidunite-dom-utils', 'loading-spinner-utils', 'aidunite-toast-notification'),
      '1.0.1',
      true
    );
    wp_localize_script('aidunite-ajax-utils', 'aiduniteScheduleRest', [
      'root'  => rest_url(),
      'nonce' => wp_create_nonce('wp_rest'),
    ]);
    $schedule_edit_js = get_stylesheet_directory() . '/assets/js/schedule/schedule-edit.js';
    if (is_readable($schedule_edit_js)) {
      wp_enqueue_script(
        'aidunite-schedule-edit',
        get_stylesheet_directory_uri() . '/assets/js/schedule/schedule-edit.js',
        array('aidunite-ajax-utils', 'aidunite-minute-select', 'aidunite-theme-icons'),
        (string) filemtime($schedule_edit_js),
        true
      );
    }
    $renewal_css = get_stylesheet_directory() . '/assets/css/pages/schedule-edit-renewal.css';
    if (is_readable($renewal_css)) {
      wp_enqueue_style(
        'aidunite-schedule-edit-renewal',
        get_stylesheet_directory_uri() . '/assets/css/pages/schedule-edit-renewal.css',
        array('schedule-style'),
        (string) filemtime($renewal_css)
      );
    }
  }

  if (is_page('match-detail') || is_page_template('page-match-detail.php')) {
    wp_enqueue_style('match-board-style', get_stylesheet_directory_uri() . '/assets/css/pages/match-board.css', array('aidunite-style', 'badge-style'), '1.0.3');
    $match_detail_css = get_stylesheet_directory() . '/assets/css/pages/match-detail.css';
    wp_enqueue_style(
      'aidunite-match-detail',
      get_stylesheet_directory_uri() . '/assets/css/pages/match-detail.css',
      array('aidunite-style', 'match-board-style'),
      is_readable($match_detail_css) ? (string) filemtime($match_detail_css) : '1.0.1'
    );
    $match_detail_js = get_stylesheet_directory() . '/assets/js/pages/match-detail-page.js';
    if (is_readable($match_detail_js)) {
      wp_enqueue_script(
        'aidunite-match-detail-page',
        get_stylesheet_directory_uri() . '/assets/js/pages/match-detail-page.js',
        array('aidunite-theme-icons'),
        (string) filemtime($match_detail_js),
        true
      );
    }
  }

  if (is_page('payment-setup') || is_page_template('page-payment-setup.php')) {
    if (function_exists('aidunite_enqueue_payment_setup_shared_styles')) {
      aidunite_enqueue_payment_setup_shared_styles();
    }
    $payment_setup_js = get_stylesheet_directory() . '/assets/js/pages/payment-setup.js';
    if (is_readable($payment_setup_js)) {
      wp_enqueue_script(
        'aidunite-payment-setup',
        get_stylesheet_directory_uri() . '/assets/js/pages/payment-setup.js',
        array('jquery', 'aidunite-theme-icons', 'aidunite-confirm-modal'),
        (string) filemtime($payment_setup_js),
        true
      );
    }
  }

  if (is_page('payment-checkout') || is_page_template('page-payment-checkout.php')) {
    $payment_checkout_css = get_stylesheet_directory() . '/assets/css/pages/payment-checkout.css';
    if (is_readable($payment_checkout_css)) {
      wp_enqueue_style(
        'aidunite-payment-checkout',
        get_stylesheet_directory_uri() . '/assets/css/pages/payment-checkout.css',
        array('aidunite-style', 'button-style'),
        (string) filemtime($payment_checkout_css)
      );
    }

    wp_enqueue_script(
      'stripe-js',
      'https://js.stripe.com/v3/',
      array(),
      null,
      true
    );

    $payment_checkout_js = get_stylesheet_directory() . '/assets/js/pages/payment-checkout.js';
    if (is_readable($payment_checkout_js)) {
      wp_enqueue_script(
        'aidunite-payment-checkout',
        get_stylesheet_directory_uri() . '/assets/js/pages/payment-checkout.js',
        array('jquery', 'stripe-js'),
        (string) filemtime($payment_checkout_js),
        true
      );
    }
  }

  if (is_page('design-reference')) {
    $schedule_css_ref = get_stylesheet_directory() . '/assets/css/pages/schedule.css';
    wp_enqueue_style(
      'schedule-style',
      get_stylesheet_directory_uri() . '/assets/css/pages/schedule.css',
      array('aidunite-style'),
      is_readable($schedule_css_ref) ? (string) filemtime($schedule_css_ref) : '1.2.0',
      'all'
    );
    $design_ref_css = get_stylesheet_directory() . '/assets/css/pages/design-reference.css';
    wp_enqueue_style(
      'design-reference-style',
      get_stylesheet_directory_uri() . '/assets/css/pages/design-reference.css',
      array('aidunite-style', 'badge-style', 'schedule-style', 'aidunite-confirm-modal'),
      is_readable($design_ref_css) ? (string) filemtime($design_ref_css) : '1.2.0'
    );
    wp_enqueue_style(
      'aidunite-toggle-switch',
      get_stylesheet_directory_uri() . '/assets/css/components/toggle-switch.css',
      array('aidunite-style'),
      '1.0.1'
    );
  }

  if (is_page('profile-edit')) {
    wp_enqueue_style('profile-edit-style', get_stylesheet_directory_uri() . '/assets/css/pages/profile-edit.css', array('aidunite-style'), '1.0.0');
    wp_enqueue_script('aidunite-form-utils', get_stylesheet_directory_uri() . '/assets/js/common/form-utils.js', array('aidunite-dom-utils'), '1.0.0', true);
    wp_enqueue_script('profile-edit-js', get_stylesheet_directory_uri() . '/assets/js/pages/profile-edit.js', array('jquery', 'aidunite-form-utils', 'aidunite-toast-notification'), '1.0.0', true);
  }

  if (is_page('member-register') || is_page_template('page-member-register.php')) {
    $member_register_css = get_stylesheet_directory() . '/assets/css/pages/member-register.css';
    $member_register_js = get_stylesheet_directory() . '/assets/js/pages/member-register.js';
    wp_enqueue_style(
      'member-register-style',
      get_stylesheet_directory_uri() . '/assets/css/pages/member-register.css',
      array('aidunite-style', 'form-style'),
      is_readable($member_register_css) ? (string) filemtime($member_register_css) : '1.2.0'
    );
    wp_enqueue_script('aidunite-form-utils', get_stylesheet_directory_uri() . '/assets/js/common/form-utils.js', array('aidunite-dom-utils'), '1.0.0', true);
    wp_enqueue_script(
      'member-register-js',
      get_stylesheet_directory_uri() . '/assets/js/pages/member-register.js',
      array('aidunite-form-utils'),
      is_readable($member_register_js) ? (string) filemtime($member_register_js) : '1.0.0',
      true
    );
  }

  if (is_page('notification-settings') || is_page_template('page-notification-settings.php')) {
    wp_enqueue_style(
      'aidunite-toggle-switch',
      get_stylesheet_directory_uri() . '/assets/css/components/toggle-switch.css',
      array('aidunite-style'),
      '1.0.1'
    );
    $notif_settings_css = get_stylesheet_directory() . '/assets/css/pages/notification-settings.css';
    if (is_readable($notif_settings_css)) {
      wp_enqueue_style(
        'aidunite-notification-settings-page',
        get_stylesheet_directory_uri() . '/assets/css/pages/notification-settings.css',
        array('aidunite-style', 'aidunite-toggle-switch'),
        (string) filemtime($notif_settings_css)
      );
    }
    $notif_settings_js = get_stylesheet_directory() . '/assets/js/pages/notification-settings.js';
    if (is_readable($notif_settings_js)) {
      wp_enqueue_script(
        'aidunite-notification-settings',
        get_stylesheet_directory_uri() . '/assets/js/pages/notification-settings.js',
        array('aidunite-theme-icons'),
        (string) filemtime($notif_settings_js),
        true
      );
    }
  }

  if (is_page('mypage-favorite-teams') || is_page_template('page-mypage-favorite-teams.php')) {
    wp_enqueue_style('match-board-style', get_stylesheet_directory_uri() . '/assets/css/pages/match-board.css', array('aidunite-style', 'badge-style'), '1.0.3');
    $favorite_teams_css = get_stylesheet_directory() . '/assets/css/pages/mypage-favorite-teams.css';
    if (is_readable($favorite_teams_css)) {
      wp_enqueue_style(
        'aidunite-mypage-favorite-teams',
        get_stylesheet_directory_uri() . '/assets/css/pages/mypage-favorite-teams.css',
        array('aidunite-style', 'match-board-style'),
        (string) filemtime($favorite_teams_css)
      );
    }
    wp_enqueue_script(
      'aidunite-favorite-teams',
      get_stylesheet_directory_uri() . '/assets/js/pages/favorite-teams.js',
      array('aidunite-dom-utils'),
      '1.0.0',
      true
    );
    wp_localize_script('aidunite-favorite-teams', 'aidunite_favorite_teams', [
      'rest_url' => esc_url(rest_url()),
      'nonce'    => wp_create_nonce('wp_rest'),
    ]);
  }

  if (is_page('guardian-signup') || is_page_template('page-guardian-signup.php')) {
    $guardian_ui_css = get_stylesheet_directory() . '/assets/css/pages/guardian-parent-ui.css';
    $signup_css = get_stylesheet_directory() . '/assets/css/pages/guardian-signup.css';
    wp_enqueue_style(
      'guardian-parent-ui',
      get_stylesheet_directory_uri() . '/assets/css/pages/guardian-parent-ui.css',
      array('aidunite-style', 'aidunite-team-theme'),
      is_readable($guardian_ui_css) ? (string) filemtime($guardian_ui_css) : '1.0.1'
    );
    wp_enqueue_style(
      'guardian-signup-style',
      get_stylesheet_directory_uri() . '/assets/css/pages/guardian-signup.css',
      array('aidunite-style', 'form-style', 'guardian-parent-ui'),
      is_readable($signup_css) ? (string) filemtime($signup_css) : '1.0.1'
    );
    $guardian_signup_js = get_stylesheet_directory() . '/assets/js/pages/guardian-signup.js';
    if (is_readable($guardian_signup_js)) {
      wp_enqueue_script(
        'aidunite-guardian-signup',
        get_stylesheet_directory_uri() . '/assets/js/pages/guardian-signup.js',
        array(),
        (string) filemtime($guardian_signup_js),
        true
      );
    }
  }

  if (is_page('member-withdrawal') || is_page_template('page-member-withdrawal.php')) {
    $member_withdrawal_js = get_stylesheet_directory() . '/assets/js/pages/member-withdrawal.js';
    if (is_readable($member_withdrawal_js)) {
      wp_enqueue_script(
        'aidunite-member-withdrawal',
        get_stylesheet_directory_uri() . '/assets/js/pages/member-withdrawal.js',
        array('aidunite-confirm-modal', 'aidunite-toast-notification'),
        (string) filemtime($member_withdrawal_js),
        true
      );
    }
  }

  if (is_page('match-requests') || is_page_template('page-match-requests.php')) {
    $match_requests_css = get_stylesheet_directory() . '/assets/css/pages/match-requests.css';
    if (is_readable($match_requests_css)) {
      wp_enqueue_style(
        'aidunite-match-requests',
        get_stylesheet_directory_uri() . '/assets/css/pages/match-requests.css',
        array('aidunite-style'),
        (string) filemtime($match_requests_css)
      );
    }
    $match_requests_js = get_stylesheet_directory() . '/assets/js/pages/match-requests.js';
    if (is_readable($match_requests_js)) {
      wp_enqueue_script(
        'aidunite-match-requests',
        get_stylesheet_directory_uri() . '/assets/js/pages/match-requests.js',
        array('jquery', 'aidunite-toast-notification'),
        (string) filemtime($match_requests_js),
        true
      );
    }
  }

  if (is_page('match-history') || is_page_template('page-match-history.php')) {
    $match_history_css = get_stylesheet_directory() . '/assets/css/pages/match-history.css';
    if (is_readable($match_history_css)) {
      wp_enqueue_style(
        'aidunite-match-history',
        get_stylesheet_directory_uri() . '/assets/css/pages/match-history.css',
        array('aidunite-style', 'match-board-style'),
        (string) filemtime($match_history_css)
      );
    }
    $match_history_js = get_stylesheet_directory() . '/assets/js/pages/match-history.js';
    if (is_readable($match_history_js)) {
      wp_enqueue_script(
        'aidunite-match-history',
        get_stylesheet_directory_uri() . '/assets/js/pages/match-history.js',
        array('jquery', 'aidunite-theme-icons'),
        (string) filemtime($match_history_js),
        true
      );
    }
  }

  if (
    is_page('player-add')
    || is_page_template('page-player-add.php')
    || is_page('edit-player')
    || is_page_template('page-edit-player.php')
  ) {
    $player_add_css = get_stylesheet_directory() . '/assets/css/pages/player-add.css';
    wp_enqueue_style(
      'player-add-style',
      get_stylesheet_directory_uri() . '/assets/css/pages/player-add.css',
      array('aidunite-style', 'aidunite-team-theme', 'form-style'),
      is_readable($player_add_css) ? (string) filemtime($player_add_css) : '1.0.0'
    );
    $grade_utils_js = get_stylesheet_directory() . '/assets/js/common/grade-utils.js';
    wp_enqueue_script(
      'aidunite-grade-utils',
      get_stylesheet_directory_uri() . '/assets/js/common/grade-utils.js',
      array(),
      is_readable($grade_utils_js) ? (string) filemtime($grade_utils_js) : '1.0.0',
      true
    );
    $player_form_js = get_stylesheet_directory() . '/assets/js/pages/player-form-page.js';
    if (is_readable($player_form_js)) {
      wp_enqueue_script(
        'aidunite-player-form-page',
        get_stylesheet_directory_uri() . '/assets/js/pages/player-form-page.js',
        array('aidunite-grade-utils'),
        (string) filemtime($player_form_js),
        true
      );
    }
    $player_photo_js = get_stylesheet_directory() . '/assets/js/player/player-photo-upload.js';
    wp_enqueue_script(
      'aidunite-player-photo-upload',
      get_stylesheet_directory_uri() . '/assets/js/player/player-photo-upload.js',
      array(),
      is_readable($player_photo_js) ? (string) filemtime($player_photo_js) : '1.0.0',
      true
    );
    if (is_page('edit-player') || is_page_template('page-edit-player.php')) {
      $edit_player_css = get_stylesheet_directory() . '/assets/css/pages/edit-player.css';
      if (is_readable($edit_player_css)) {
        wp_enqueue_style(
          'aidunite-page-edit-player',
          get_stylesheet_directory_uri() . '/assets/css/pages/edit-player.css',
          array('player-add-style'),
          (string) filemtime($edit_player_css)
        );
      }
    }
  }

  if (is_page('invite-guardian') || is_page_template('page-invite-guardian.php')) {
    $guardian_ui_css = get_stylesheet_directory() . '/assets/css/pages/guardian-parent-ui.css';
    $invite_css = get_stylesheet_directory() . '/assets/css/pages/invite-guardian.css';
    wp_enqueue_style(
      'guardian-parent-ui',
      get_stylesheet_directory_uri() . '/assets/css/pages/guardian-parent-ui.css',
      array('aidunite-style', 'aidunite-team-theme'),
      is_readable($guardian_ui_css) ? (string) filemtime($guardian_ui_css) : '1.0.1'
    );
    wp_enqueue_style(
      'invite-guardian-style',
      get_stylesheet_directory_uri() . '/assets/css/pages/invite-guardian.css',
      array('aidunite-style', 'guardian-parent-ui'),
      is_readable($invite_css) ? (string) filemtime($invite_css) : '1.0.1'
    );
    $qrcode_vendor_js = get_stylesheet_directory() . '/assets/js/vendor/qrcode.min.js';
    wp_enqueue_script(
      'qrcode',
      get_stylesheet_directory_uri() . '/assets/js/vendor/qrcode.min.js',
      array(),
      is_readable($qrcode_vendor_js) ? (string) filemtime($qrcode_vendor_js) : '1.0.0',
      true
    );
    $invite_qr_js = get_stylesheet_directory() . '/assets/js/pages/invite-guardian-qr.js';
    wp_enqueue_script(
      'aidunite-invite-guardian-qr',
      get_stylesheet_directory_uri() . '/assets/js/pages/invite-guardian-qr.js',
      array('qrcode'),
      is_readable($invite_qr_js) ? (string) filemtime($invite_qr_js) : '1.0.0',
      true
    );
  }

  if (is_page('notifications') || is_page_template('page-notifications.php')) {
    wp_enqueue_style(
      'ainy-notifications-list',
      get_stylesheet_directory_uri() . '/assets/css/pages/notifications-list.css',
      array('aidunite-style', 'aidunite-team-theme', 'aidunite-web-app-integrated-ui'),
      '1.0.12'
    );
    wp_enqueue_script(
      'ainy-notifications-list',
      get_stylesheet_directory_uri() . '/assets/js/pages/notifications-list.js',
      array('jquery', 'aidunite-ui-state-helpers', 'aidunite-ui-tab-controller'),
      '1.0.8',
      true
    );
  }

  if (is_page('mypage') || is_page_template('page-mypage.php')) {
    $mypage_general_css = get_stylesheet_directory() . '/assets/css/pages/mypage-general-landing.css';
    wp_enqueue_style(
      'mypage-general-landing',
      get_stylesheet_directory_uri() . '/assets/css/pages/mypage-general-landing.css',
      array('aidunite-style'),
      file_exists($mypage_general_css) ? (string) filemtime($mypage_general_css) : '2.1.0'
    );

    $is_general_before_apply = function_exists('aidunite_is_general_mypage_screen')
      && function_exists('aidunite_mypage_general_state')
      && aidunite_is_general_mypage_screen()
      && aidunite_mypage_general_state() === 'before_apply';
    if ($is_general_before_apply) {
      $front_page_css = get_stylesheet_directory() . '/assets/css/pages/front-page.css';
      wp_enqueue_style(
        'aidunite-front-page',
        get_stylesheet_directory_uri() . '/assets/css/pages/front-page.css',
        array('aidunite-style', 'mypage-general-landing'),
        is_readable($front_page_css) ? (string) filemtime($front_page_css) : '1.1.0',
        'all'
      );
    }

    $is_general_application_review = function_exists('aidunite_is_general_mypage_screen')
      && function_exists('aidunite_mypage_general_state')
      && aidunite_is_general_mypage_screen()
      && in_array(aidunite_mypage_general_state(), array('pending', 'needs_revision'), true);
    if ($is_general_application_review && function_exists('aidunite_enqueue_application_review_assets')) {
      aidunite_enqueue_application_review_assets();
    }

    $is_general_pending = function_exists('aidunite_is_general_mypage_screen')
      && function_exists('aidunite_mypage_general_state')
      && aidunite_is_general_mypage_screen()
      && aidunite_mypage_general_state() === 'pending';
    if ($is_general_pending) {
      $mypage_joy_css = get_stylesheet_directory() . '/assets/css/pages/mypage-joy.css';
      if (is_readable($mypage_joy_css)) {
        wp_enqueue_style(
          'mypage-joy',
          get_stylesheet_directory_uri() . '/assets/css/pages/mypage-joy.css',
          array('aidunite-style', 'aidunite-team-theme', 'aidunite-web-app-integrated-ui', 'button-style'),
          (string) filemtime($mypage_joy_css)
        );
      }

      $pending_joy_css = get_stylesheet_directory() . '/assets/css/pages/mypage-general-pending-joy.css';
      if (is_readable($pending_joy_css)) {
        wp_enqueue_style(
          'mypage-general-pending-joy',
          get_stylesheet_directory_uri() . '/assets/css/pages/mypage-general-pending-joy.css',
          array('mypage-joy', 'team-registration-complete-style'),
          (string) filemtime($pending_joy_css)
        );
      }

      $team_complete_js = get_stylesheet_directory() . '/assets/js/team/team-registration-complete.js';
      if (is_readable($team_complete_js)) {
        wp_enqueue_script(
          'team-registration-complete',
          get_stylesheet_directory_uri() . '/assets/js/team/team-registration-complete.js',
          array(),
          (string) filemtime($team_complete_js),
          true
        );
      }
    }
  }

  if (is_page('ainy-dashboard') || is_page_template('page-ainy-dashboard.php')) {
    wp_enqueue_style(
      'ainy-dashboard-page',
      get_stylesheet_directory_uri() . '/assets/css/pages/ainy-dashboard.css',
      array('aidunite-style'),
      '1.0.2'
    );

    wp_enqueue_script(
      'chart-js',
      'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
      array(),
      '4.4.1',
      true
    );

    $charts_js = get_stylesheet_directory() . '/assets/js/admin/ainy-dashboard-charts.js';
    if (is_readable($charts_js)) {
      wp_enqueue_script(
        'ainy-dashboard-charts',
        get_stylesheet_directory_uri() . '/assets/js/admin/ainy-dashboard-charts.js',
        array('chart-js'),
        (string) filemtime($charts_js),
        true
      );

      if (function_exists('aidunite_dashboard_get_charts_payload')) {
        $dash_period = isset($_GET['period']) ? sanitize_text_field(wp_unslash($_GET['period'])) : '7d';
        if (!in_array($dash_period, ['today', '7d', '30d', 'month'], true)) {
          $dash_period = '7d';
        }
        wp_localize_script(
          'ainy-dashboard-charts',
          'ainyDashboardCharts',
          aidunite_dashboard_get_charts_payload($dash_period)
        );
      }
    }
  }

  if (is_page('team-settings') || is_page_template('page-team-settings.php')) {
    $team_settings_css = get_stylesheet_directory() . '/assets/css/components/team-settings-dashboard.css';
    if (is_readable($team_settings_css)) {
      wp_enqueue_style(
        'aidunite-team-settings-dashboard',
        get_stylesheet_directory_uri() . '/assets/css/components/team-settings-dashboard.css',
        array('aidunite-style', 'aidunite-team-theme', 'form-style'),
        (string) filemtime($team_settings_css)
      );
    }
    $team_reg_logo_css = get_stylesheet_directory() . '/assets/css/pages/team-registration.css';
    if (is_readable($team_reg_logo_css)) {
      wp_enqueue_style(
        'team-registration-logo-style',
        get_stylesheet_directory_uri() . '/assets/css/pages/team-registration.css',
        array('aidunite-team-settings-dashboard'),
        (string) filemtime($team_reg_logo_css)
      );
    }
    $team_logo_js = get_stylesheet_directory() . '/assets/js/team/team-logo-upload.js';
    if (is_readable($team_logo_js)) {
      wp_enqueue_script(
        'aidunite-team-logo-upload',
        get_stylesheet_directory_uri() . '/assets/js/team/team-logo-upload.js',
        array(),
        (string) filemtime($team_logo_js),
        true
      );
    }
    $team_settings_js = get_stylesheet_directory() . '/assets/js/pages/team-settings.js';
    if (is_readable($team_settings_js)) {
      wp_enqueue_script(
        'aidunite-team-settings',
        get_stylesheet_directory_uri() . '/assets/js/pages/team-settings.js',
        array('aidunite-team-logo-upload'),
        (string) filemtime($team_settings_js),
        true
      );
      $team_logo_upload_config = function_exists('aidunite_get_team_logo_upload_script_config')
        ? aidunite_get_team_logo_upload_script_config(
          'aidunite_team_settings_save',
          'aidunite_team_settings_nonce',
          'team_settings_logo_upload'
        )
        : array(
          'ajaxUrl' => admin_url('admin-ajax.php'),
          'nonce' => wp_create_nonce('aidunite_team_settings_save'),
          'nonceField' => 'aidunite_team_settings_nonce',
          'uploadAction' => 'team_settings_logo_upload',
          'logoMaxBytes' => 10 * 1024 * 1024,
          'logoMaxLabel' => '10MB',
        );
      wp_localize_script('aidunite-team-logo-upload', 'aiduniteTeamLogoUploadConfig', $team_logo_upload_config);
      wp_localize_script(
        'aidunite-team-settings',
        'aiduniteTeamSettings',
        $team_logo_upload_config
      );
    }
    $leader_transfer_js = get_stylesheet_directory() . '/assets/js/team/team-leader-transfer.js';
    if (is_readable($leader_transfer_js)) {
      wp_enqueue_script(
        'aidunite-team-leader-transfer',
        get_stylesheet_directory_uri() . '/assets/js/team/team-leader-transfer.js',
        array(),
        (string) filemtime($leader_transfer_js),
        true
      );
    }
  }

  if (is_page('team-public') || is_page_template('page-team-public.php') || is_singular('team')) {
    $team_panel_css = get_stylesheet_directory() . '/assets/css/components/team-form-panel.css';
    if (is_readable($team_panel_css)) {
      wp_enqueue_style(
        'aidunite-team-form-panel',
        get_stylesheet_directory_uri() . '/assets/css/components/team-form-panel.css',
        array('aidunite-style', 'form-style'),
        (string) filemtime($team_panel_css)
      );
    }
  }

  if (is_front_page()) {
    $front_page_css = get_stylesheet_directory() . '/assets/css/pages/front-page.css';
    wp_enqueue_style(
      'aidunite-front-page',
      get_stylesheet_directory_uri() . '/assets/css/pages/front-page.css',
      array('aidunite-style'),
      is_readable($front_page_css) ? (string) filemtime($front_page_css) : '1.1.0',
      'all'
    );
    wp_enqueue_script('front-page-js', get_stylesheet_directory_uri() . '/assets/js/pages/front-page.js', array('jquery'), '1.0.0', true);
  }

  $is_admin_list_page = (
    is_page('admin-user-list')
    || is_page('admin-schedule-list')
    || is_page('admin-payment-list')
    || is_page('team-management')
    || is_page('match-requests')
    || is_page('admin-match-feedback-list')
    || is_page_template('page-admin-user-list.php')
    || is_page_template('page-admin-schedule-list.php')
    || is_page_template('page-admin-payment-list.php')
    || is_page_template('page-admin-team-funnel.php')
    || is_page_template('page-admin-analytics-active.php')
    || is_page_template('page-admin-analytics-pv.php')
    || is_page_template('page-admin-analytics-churn.php')
    || is_page_template('page-admin-analytics-ai-report.php')
    || is_page_template('page-admin-payment-revenue.php')
    || is_page_template('page-team-management.php')
    || is_page_template('page-match-requests.php')
    || is_page_template('page-admin-match-feedback-list.php')
  );

  if (is_page('admin-match-feedback-list') || is_page_template('page-admin-match-feedback-list.php')) {
    wp_enqueue_style(
      'aidunite-admin-filters',
      get_stylesheet_directory_uri() . '/assets/css/pages/admin-filters.css',
      array('aidunite-style'),
      '1.0.0'
    );
  }

  $is_admin_analytics_page = (
    is_page_template('page-admin-team-funnel.php')
    || is_page_template('page-admin-analytics-active.php')
    || is_page_template('page-admin-analytics-pv.php')
    || is_page_template('page-admin-analytics-churn.php')
    || is_page_template('page-admin-analytics-ai-report.php')
    || is_page_template('page-admin-payment-revenue.php')
  );

  if ($is_admin_analytics_page) {
    $admin_analytics_css = get_stylesheet_directory() . '/assets/css/pages/admin-analytics.css';
    wp_enqueue_style(
      'aidunite-admin-analytics',
      get_stylesheet_directory_uri() . '/assets/css/pages/admin-analytics.css',
      array('aidunite-style'),
      is_readable($admin_analytics_css) ? (string) filemtime($admin_analytics_css) : '1.0.0'
    );
    if (is_page_template('page-admin-payment-revenue.php')) {
      $admin_payment_revenue_css = get_stylesheet_directory() . '/assets/css/pages/admin-payment-revenue.css';
      wp_enqueue_style(
        'aidunite-admin-payment-revenue',
        get_stylesheet_directory_uri() . '/assets/css/pages/admin-payment-revenue.css',
        array('aidunite-admin-analytics'),
        is_readable($admin_payment_revenue_css) ? (string) filemtime($admin_payment_revenue_css) : '1.0.0'
      );
    }
  }

  if ($is_admin_list_page) {
    wp_enqueue_style(
      'aidunite-admin-checkbox',
      get_stylesheet_directory_uri() . '/assets/css/components/admin-checkbox.css',
      array('aidunite-style'),
      '1.0.0'
    );
    $admin_list_table_css = get_stylesheet_directory() . '/assets/css/pages/admin-list-table.css';
    wp_enqueue_style(
      'aidunite-admin-list-table',
      get_stylesheet_directory_uri() . '/assets/css/pages/admin-list-table.css',
      array('aidunite-style', 'aidunite-admin-checkbox'),
      is_readable($admin_list_table_css) ? (string) filemtime($admin_list_table_css) : '1.0.0'
    );
  }

  $is_payment_admin_page = (
    is_page('admin-payment-management')
    || is_page('admin-payment-list')
    || is_page_template('page-admin-payment-management.php')
    || is_page_template('page-admin-payment-list.php')
  );
  if ($is_payment_admin_page) {
    if (is_page('admin-payment-management') || is_page_template('page-admin-payment-management.php')) {
      wp_enqueue_style('aidunite-dashboard', get_stylesheet_directory_uri() . '/assets/css/pages/ainy-dashboard.css', array('aidunite-style'), '1.0.0');
    }
    if (is_page('admin-payment-list') || is_page_template('page-admin-payment-list.php')) {
      wp_enqueue_style(
        'aidunite-admin-filters',
        get_stylesheet_directory_uri() . '/assets/css/pages/admin-filters.css',
        array('aidunite-style'),
        '1.0.0'
      );
      $admin_payment_list_css = get_stylesheet_directory() . '/assets/css/pages/admin-payment-list.css';
      wp_enqueue_style(
        'aidunite-admin-payment-list',
        get_stylesheet_directory_uri() . '/assets/css/pages/admin-payment-list.css',
        array('aidunite-style', 'aidunite-admin-list-table', 'aidunite-admin-filters'),
        is_readable($admin_payment_list_css) ? (string) filemtime($admin_payment_list_css) : '1.0.0'
      );
    }
    wp_enqueue_script(
      'aidunite-admin-payment-management',
      get_stylesheet_directory_uri() . '/assets/js/pages/admin-payment-management.js',
      array(),
      '1.0.0',
      true
    );
    wp_localize_script('aidunite-admin-payment-management', 'aidunitePaymentAdmin', [
      'ajaxUrl' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('aidunite_payment_admin'),
      'restUrl' => rest_url('aidunite/v1'),
      'restNonce' => wp_create_nonce('wp_rest'),
    ]);
  }

  if (
    is_page('admin-schedule-list')
    || is_page('team-management')
    || is_page_template('page-admin-schedule-list.php')
    || is_page_template('page-team-management.php')
  ) {
    wp_enqueue_style(
      'aidunite-admin-filters',
      get_stylesheet_directory_uri() . '/assets/css/pages/admin-filters.css',
      array('aidunite-style'),
      '1.0.0'
    );
  }

  if (is_page('admin-schedule-list') || is_page_template('page-admin-schedule-list.php')) {
    wp_enqueue_style(
      'aidunite-admin-schedule-list',
      get_stylesheet_directory_uri() . '/assets/css/pages/admin-schedule-list.css',
      array('aidunite-style', 'aidunite-admin-filters'),
      '1.0.0'
    );
  }

  if (is_page('team-management') || is_page_template('page-team-management.php')) {
    $team_management_css = get_stylesheet_directory() . '/assets/css/pages/team-management.css';
    wp_enqueue_style(
      'aidunite-team-management',
      get_stylesheet_directory_uri() . '/assets/css/pages/team-management.css',
      array('aidunite-style', 'aidunite-admin-filters', 'aidunite-site-shell'),
      is_readable($team_management_css) ? (string) filemtime($team_management_css) : '1.0.0'
    );
    $team_management_js = get_stylesheet_directory() . '/assets/js/pages/team-management.js';
    if (is_readable($team_management_js)) {
      wp_enqueue_script(
        'aidunite-team-management',
        get_stylesheet_directory_uri() . '/assets/js/pages/team-management.js',
        array('aidunite-confirm-modal', 'aidunite-toast-notification'),
        (string) filemtime($team_management_js),
        true
      );
    }
  }

  aidunite_enqueue_manifest_page_assets();
}

/**
 * wp-admin でも OS 通知禁止のためトースト・確認モーダルを読み込む
 */
function aidunite_enqueue_admin_feedback_assets($hook) {
  unset($hook);
  wp_enqueue_style('aidunite-toast-notification', get_stylesheet_directory_uri() . '/assets/css/components/toast-notification.css', array(), '1.0.4');
  wp_enqueue_style('aidunite-confirm-modal', get_stylesheet_directory_uri() . '/assets/css/components/confirm-modal.css', array(), '1.0.0');
  wp_enqueue_script('aidunite-dom-utils', get_stylesheet_directory_uri() . '/assets/js/common/dom-utils.js', array(), '1.0.0', true);
  wp_enqueue_script('aidunite-toast-notification', get_stylesheet_directory_uri() . '/assets/js/common/toast-notification.js', array('aidunite-dom-utils'), '1.0.6', true);
  wp_enqueue_script('aidunite-confirm-modal', get_stylesheet_directory_uri() . '/assets/js/common/confirm-modal.js', array('aidunite-dom-utils'), '1.0.0', true);
  $feedback_utils_js = get_stylesheet_directory() . '/assets/js/common/feedback-utils.js';
  wp_enqueue_script(
    'aidunite-feedback-utils',
    get_stylesheet_directory_uri() . '/assets/js/common/feedback-utils.js',
    array('aidunite-toast-notification', 'aidunite-confirm-modal'),
    is_readable($feedback_utils_js) ? (string) filemtime($feedback_utils_js) : '1.0.0',
    true
  );
}
add_action('admin_enqueue_scripts', 'aidunite_enqueue_admin_feedback_assets');
