<?php
/**
 * 固定ページ自動作成（スケジュール・決済・コミュニケーション・通知設定）
 * functions.php から移設
 */
if (!defined('ABSPATH')) { exit; }
/*--------------------------------------------------------------
  旧 schedule-list 固定ページは廃止（/schedule-management へリダイレクト）
--------------------------------------------------------------*/

function create_payment_pages() {
    // 支払い設定ページ
    $payment_setup_page = get_page_by_path('payment-setup');
    if (!$payment_setup_page) {
        $page_id = wp_insert_post(array(
            'post_title'    => '【決済】支払い設定ページ',
            'post_name'     => 'payment-setup',
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_content'  => '',
        ));
        if ($page_id && !is_wp_error($page_id)) {
            update_post_meta($page_id, '_wp_page_template', 'page-payment-setup.php');
        }
    } else {
        // 既存ページのタイトルを更新
        wp_update_post(array(
            'ID' => $payment_setup_page->ID,
            'post_title' => '【決済】支払い設定ページ',
        ));
    }

    $payment_checkout_page = get_page_by_path('payment-checkout');
    if (!$payment_checkout_page) {
        $checkout_page_id = wp_insert_post(array(
            'post_title'   => '【決済】カード登録',
            'post_name'    => 'payment-checkout',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '',
        ));
        if ($checkout_page_id && !is_wp_error($checkout_page_id)) {
            update_post_meta($checkout_page_id, '_wp_page_template', 'page-payment-checkout.php');
        }
    } else {
        wp_update_post(array(
            'ID' => $payment_checkout_page->ID,
            'post_title' => '【決済】カード登録',
        ));
        $checkout_template = get_post_meta($payment_checkout_page->ID, '_wp_page_template', true);
        if ($checkout_template !== 'page-payment-checkout.php') {
            update_post_meta($payment_checkout_page->ID, '_wp_page_template', 'page-payment-checkout.php');
        }
    }

    // 支払い必要ページ
    $payment_required_page = get_page_by_path('payment-required');
    if (!$payment_required_page) {
        $page_id = wp_insert_post(array(
            'post_title'    => '【決済】支払い必要ページ',
            'post_name'     => 'payment-required',
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_content'  => '',
        ));
        if ($page_id && !is_wp_error($page_id)) {
            update_post_meta($page_id, '_wp_page_template', 'page-payment-required.php');
        }
    } else {
        // 既存ページのタイトルを更新
        wp_update_post(array(
            'ID' => $payment_required_page->ID,
            'post_title' => '【決済】支払い必要ページ',
        ));
    }

    // プラン選択ページ
    $plan_selection_page = get_page_by_path('plan-selection');
    if (!$plan_selection_page) {
        $page_id = wp_insert_post(array(
            'post_title'    => '【決済】プラン選択ページ',
            'post_name'     => 'plan-selection',
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_content'  => '',
        ));
        if ($page_id && !is_wp_error($page_id)) {
            update_post_meta($page_id, '_wp_page_template', 'page-payment-setup.php');
        }
    } else {
        // 既存ページのタイトルを更新
        wp_update_post(array(
            'ID' => $plan_selection_page->ID,
            'post_title' => '【決済】プラン選択ページ',
        ));
        $current_template = get_post_meta($plan_selection_page->ID, '_wp_page_template', true);
        if ($current_template === 'default' || $current_template === '') {
            update_post_meta($plan_selection_page->ID, '_wp_page_template', 'page-payment-setup.php');
        }
    }

    // 保護者月謝支払いページ
    $parent_payment_page = get_page_by_path('parent-payment');
    if (!$parent_payment_page) {
        $page_id = wp_insert_post(array(
            'post_title'    => '【決済】保護者月謝支払いページ',
            'post_name'     => 'parent-payment',
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_content'  => '',
        ));
        if ($page_id && !is_wp_error($page_id)) {
            update_post_meta($page_id, '_wp_page_template', 'page-parent-payment.php');
        }
    } else {
        // 既存ページのタイトルを更新
        wp_update_post(array(
            'ID' => $parent_payment_page->ID,
            'post_title' => '【決済】保護者月謝支払いページ',
        ));
    }

    // プラン情報ページ
    $plan_info_page = get_page_by_path('plan-info');
    if (!$plan_info_page) {
        $page_id = wp_insert_post(array(
            'post_title'    => '【決済】プラン情報ページ',
            'post_name'     => 'plan-info',
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_content'  => '',
        ));
        if ($page_id && !is_wp_error($page_id)) {
            update_post_meta($page_id, '_wp_page_template', 'page-plan-info.php');
        }
    } else {
        // 既存ページのタイトルを更新
        wp_update_post(array(
            'ID' => $plan_info_page->ID,
            'post_title' => '【決済】プラン情報ページ',
        ));
    }

    // チーム月謝管理ページ
    $team_payment_page = get_page_by_path('team-payment-management');
    if (!$team_payment_page) {
        $page_id = wp_insert_post(array(
            'post_title'    => '【決済】チーム月謝管理ページ',
            'post_name'     => 'team-payment-management',
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_content'  => '',
        ));
        if ($page_id && !is_wp_error($page_id)) {
            update_post_meta($page_id, '_wp_page_template', 'page-team-payment-management.php');
        }
    } else {
        // 既存ページのタイトルを更新
        wp_update_post(array(
            'ID' => $team_payment_page->ID,
            'post_title' => '【決済】チーム月謝管理ページ',
        ));
    }

    // チーム月謝徴収状況ページ
    $team_tuition_collections_page = get_page_by_path('team-tuition-collections');
    if (!$team_tuition_collections_page) {
        $page_id = wp_insert_post(array(
            'post_title'    => '【決済】チーム月謝徴収状況ページ',
            'post_name'     => 'team-tuition-collections',
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_content'  => '',
        ));
        if ($page_id && !is_wp_error($page_id)) {
            update_post_meta($page_id, '_wp_page_template', 'page-team-tuition-collections.php');
        }
    } else {
        wp_update_post(array(
            'ID' => $team_tuition_collections_page->ID,
            'post_title' => '【決済】チーム月謝徴収状況ページ',
        ));
    }

    aidunite_ensure_admin_payment_management_page();
    aidunite_ensure_admin_payment_list_page();

    // 保護者登録ページ
    $guardian_signup_page = get_page_by_path('guardian-signup');
    if (!$guardian_signup_page) {
        $page_id = wp_insert_post(array(
            'post_title'    => '保護者登録ページ',
            'post_name'     => 'guardian-signup',
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_content'  => '',
        ));
        if ($page_id && !is_wp_error($page_id)) {
            update_post_meta($page_id, '_wp_page_template', 'page-guardian-signup.php');
        }
    } else {
        // 既存ページのテンプレートを確認・更新
        $current_template = get_post_meta($guardian_signup_page->ID, '_wp_page_template', true);
        if ($current_template !== 'page-guardian-signup.php') {
            update_post_meta($guardian_signup_page->ID, '_wp_page_template', 'page-guardian-signup.php');
        }
    }

    // 保護者仮登録完了ページ
    $guardian_pending_page = get_page_by_path('guardian-registration-pending');
    if (!$guardian_pending_page) {
        $page_id = wp_insert_post(array(
            'post_title'    => '保護者仮登録完了',
            'post_name'     => 'guardian-registration-pending',
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_content'  => '',
        ));
        if ($page_id && !is_wp_error($page_id)) {
            update_post_meta($page_id, '_wp_page_template', 'page-guardian-registration-pending.php');
        }
    } else {
        $current_template = get_post_meta($guardian_pending_page->ID, '_wp_page_template', true);
        if ($current_template !== 'page-guardian-registration-pending.php') {
            update_post_meta($guardian_pending_page->ID, '_wp_page_template', 'page-guardian-registration-pending.php');
        }
    }

    // 保護者招待ページ
    $invite_guardian_page = get_page_by_path('invite-guardian');
    if (!$invite_guardian_page) {
        $page_id = wp_insert_post(array(
            'post_title'    => '保護者招待フォーム',
            'post_name'     => 'invite-guardian',
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_content'  => '',
        ));
        if ($page_id && !is_wp_error($page_id)) {
            update_post_meta($page_id, '_wp_page_template', 'page-invite-guardian.php');
        }
    } else {
        $current_template = get_post_meta($invite_guardian_page->ID, '_wp_page_template', true);
        if ($current_template !== 'page-invite-guardian.php') {
            update_post_meta($invite_guardian_page->ID, '_wp_page_template', 'page-invite-guardian.php');
        }
    }
}

// テーマアクティベーション時にページを作成
add_action('after_switch_theme', 'create_payment_pages');

// 管理画面からも手動で作成可能にする
add_action('admin_init', 'create_payment_pages');
add_action('init', 'create_payment_pages', 20);

/**
 * コミュニケーション関連の固定ページを作成・更新
 */
function create_communication_pages() {
    // コミュニケーションメイン画面
    $communication_main_page = get_page_by_path('communication-main');
    if (!$communication_main_page) {
        $page_id = wp_insert_post(array(
            'post_title'    => 'コミュニケーションメイン画面',
            'post_name'     => 'communication-main',
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_content'  => '',
        ));
        if ($page_id && !is_wp_error($page_id)) {
            if (file_exists(get_template_directory() . '/page-communication-main.php')) {
                update_post_meta($page_id, '_wp_page_template', 'page-communication-main.php');
            }
        }
    } else {
        // 既存ページのテンプレートを確認・更新
        $current_template = get_post_meta($communication_main_page->ID, '_wp_page_template', true);
        if ($current_template !== 'page-communication-main.php') {
            update_post_meta($communication_main_page->ID, '_wp_page_template', 'page-communication-main.php');
        }
    }

    // チャット画面（統合版）
    $chat_page = get_page_by_path('chat');
    if (!$chat_page) {
        $page_id = wp_insert_post(array(
            'post_title'    => 'チャット画面（統合版）',
            'post_name'     => 'chat',
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_content'  => '',
        ));
        if ($page_id && !is_wp_error($page_id)) {
            if (file_exists(get_template_directory() . '/page-chat.php')) {
                update_post_meta($page_id, '_wp_page_template', 'page-chat.php');
            }
        }
    } else {
        // 既存ページのテンプレートを確認・更新
        $current_template = get_post_meta($chat_page->ID, '_wp_page_template', true);
        if ($current_template !== 'page-chat.php') {
            update_post_meta($chat_page->ID, '_wp_page_template', 'page-chat.php');
        }
    }

    // 後方互換性: /communication も /communication-main にリダイレクト
    $communication_page = get_page_by_path('communication');
    if (!$communication_page) {
        // /communication ページが存在しない場合は作成（リダイレクト用）
        $page_id = wp_insert_post(array(
            'post_title'    => 'コミュニケーション',
            'post_name'     => 'communication',
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_content'  => '',
        ));
        if ($page_id && !is_wp_error($page_id)) {
            // リダイレクト用のショートコードまたはテンプレートを設定
            update_post_meta($page_id, '_wp_page_template', 'page-communication-main.php');
        }
    }

    // チャット管理ページ（管理者専用）
    $chat_management_page = get_page_by_path('chat-management');
    if (!$chat_management_page) {
        $page_id = wp_insert_post(array(
            'post_title'    => 'チャット管理',
            'post_name'     => 'chat-management',
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_content'  => '',
        ));
        if ($page_id && !is_wp_error($page_id)) {
            if (file_exists(get_template_directory() . '/page-chat-management.php')) {
                update_post_meta($page_id, '_wp_page_template', 'page-chat-management.php');
            }
        }
    } else {
        $current_template = get_post_meta($chat_management_page->ID, '_wp_page_template', true);
        if ($current_template !== 'page-chat-management.php') {
            update_post_meta($chat_management_page->ID, '_wp_page_template', 'page-chat-management.php');
        }
    }

    aidunite_ensure_admin_post_match_modules_page();
    aidunite_ensure_admin_match_feedback_list_page();
}

/**
 * 試合後モジュール管理ページ（/admin-post-match-modules）を確保
 */
function aidunite_ensure_admin_post_match_modules_page() {
    $pmm_page = get_page_by_path('admin-post-match-modules');
    if (!$pmm_page) {
        $page_id = wp_insert_post([
            'post_title'   => '試合後モジュール',
            'post_name'    => 'admin-post-match-modules',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '',
        ]);
        if ($page_id && !is_wp_error($page_id)) {
            if (file_exists(get_template_directory() . '/page-admin-post-match-modules.php')) {
                update_post_meta($page_id, '_wp_page_template', 'page-admin-post-match-modules.php');
            }
        }
    } else {
        $current_template = get_post_meta($pmm_page->ID, '_wp_page_template', true);
        if ($current_template !== 'page-admin-post-match-modules.php') {
            update_post_meta($pmm_page->ID, '_wp_page_template', 'page-admin-post-match-modules.php');
        }
    }
}

add_action('init', 'aidunite_ensure_admin_post_match_modules_page', 25);

/**
 * 試合後アンケート一覧（データ管理 /admin-match-feedback-list）を確保
 */
function aidunite_ensure_admin_match_feedback_list_page() {
    $page = get_page_by_path('admin-match-feedback-list');
    if (!$page) {
        $page_id = wp_insert_post([
            'post_title'   => '試合後のアンケート一覧',
            'post_name'    => 'admin-match-feedback-list',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '',
        ]);
        if ($page_id && !is_wp_error($page_id)) {
            if (file_exists(get_template_directory() . '/page-admin-match-feedback-list.php')) {
                update_post_meta($page_id, '_wp_page_template', 'page-admin-match-feedback-list.php');
            }
        }
    } else {
        $current_template = get_post_meta($page->ID, '_wp_page_template', true);
        if ($current_template !== 'page-admin-match-feedback-list.php') {
            update_post_meta($page->ID, '_wp_page_template', 'page-admin-match-feedback-list.php');
        }
    }
}

add_action('init', 'aidunite_ensure_admin_match_feedback_list_page', 25);

// テーマアクティベーション時にページを作成
add_action('after_switch_theme', 'create_communication_pages');

// 管理画面からも手動で作成可能にする
add_action('admin_init', 'create_communication_pages');

/*--------------------------------------------------------------
  通知設定ページ（ドメイン通知のチャンネル設定）の自動作成
  ダッシュボード等は home_url('/notification-settings') を参照する
--------------------------------------------------------------*/
function create_notification_settings_page() {
    $page = get_page_by_path('notification-settings');
    if (!$page) {
        $page_id = wp_insert_post(array(
            'post_title'    => '通知設定',
            'post_name'     => 'notification-settings',
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_content'  => '',
        ));
        if ($page_id && !is_wp_error($page_id)) {
            if (file_exists(get_template_directory() . '/page-notification-settings.php')) {
                update_post_meta($page_id, '_wp_page_template', 'page-notification-settings.php');
            }
        }
    } else {
        $current_template = get_post_meta($page->ID, '_wp_page_template', true);
        if ($current_template !== 'page-notification-settings.php'
            && file_exists(get_template_directory() . '/page-notification-settings.php')) {
            update_post_meta($page->ID, '_wp_page_template', 'page-notification-settings.php');
        }
    }
}

add_action('after_switch_theme', 'create_notification_settings_page');
add_action('admin_init', 'create_notification_settings_page');

/*--------------------------------------------------------------
  システム管理（/system-management）— Ainy ダッシュボードへリダイレクト用
  旧ブックマーク互換。テンプレ: page-system-management.php
--------------------------------------------------------------*/
function aidunite_ensure_system_management_page() {
    $page = get_page_by_path('system-management');
    $template = 'page-system-management.php';
    $template_path = get_template_directory() . '/' . $template;

    if (!$page) {
        $page_id = wp_insert_post([
            'post_title'   => 'システム管理（リダイレクト）',
            'post_name'    => 'system-management',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '',
        ]);
        if ($page_id && !is_wp_error($page_id) && is_readable($template_path)) {
            update_post_meta($page_id, '_wp_page_template', $template);
        }
        return;
    }

    $current_template = (string) get_post_meta($page->ID, '_wp_page_template', true);
    if ($current_template !== $template && is_readable($template_path)) {
        update_post_meta($page->ID, '_wp_page_template', $template);
    }
}

add_action('after_switch_theme', 'aidunite_ensure_system_management_page');
add_action('admin_init', 'aidunite_ensure_system_management_page');
add_action('init', 'aidunite_ensure_system_management_page', 25);

/**
 * 大会・イベント公開 LP 固定ページ
 */
function aidunite_ensure_competition_event_public_page() {
    $page = get_page_by_path('competition-event');
    $template = 'page-competition-event.php';
    $template_path = get_template_directory() . '/' . $template;

    if (!$page) {
        $page_id = wp_insert_post([
            'post_title'   => '大会・イベント',
            'post_name'    => 'competition-event',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '',
        ]);
        if ($page_id && !is_wp_error($page_id) && is_readable($template_path)) {
            update_post_meta($page_id, '_wp_page_template', $template);
        }

        return;
    }

    $current_template = (string) get_post_meta($page->ID, '_wp_page_template', true);
    if ($current_template !== $template && is_readable($template_path)) {
        update_post_meta($page->ID, '_wp_page_template', $template);
    }
}

add_action('after_switch_theme', 'aidunite_ensure_competition_event_public_page');
add_action('admin_init', 'aidunite_ensure_competition_event_public_page');
add_action('init', 'aidunite_ensure_competition_event_public_page', 25);

/**
 * 管理者用決済管理ページ（/admin-payment-management）を確保
 */
function aidunite_ensure_admin_payment_management_page() {
    $page = get_page_by_path('admin-payment-management');
    $template = 'page-admin-payment-management.php';
    $template_path = get_template_directory() . '/' . $template;

    if (!$page) {
        $page_id = wp_insert_post([
            'post_title'   => '決済管理',
            'post_name'    => 'admin-payment-management',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '',
        ]);
        if ($page_id && !is_wp_error($page_id) && is_readable($template_path)) {
            update_post_meta($page_id, '_wp_page_template', $template);
        }

        return;
    }

    $current_template = (string) get_post_meta($page->ID, '_wp_page_template', true);
    if ($current_template !== $template && is_readable($template_path)) {
        update_post_meta($page->ID, '_wp_page_template', $template);
    }
}

/**
 * 管理者用決済一覧ページ（/admin-payment-list）を確保
 */
function aidunite_ensure_admin_payment_list_page() {
    $page = get_page_by_path('admin-payment-list');
    $template = 'page-admin-payment-list.php';
    $template_path = get_template_directory() . '/' . $template;

    if (!$page) {
        $page_id = wp_insert_post([
            'post_title'   => '決済一覧',
            'post_name'    => 'admin-payment-list',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '',
        ]);
        if ($page_id && !is_wp_error($page_id) && is_readable($template_path)) {
            update_post_meta($page_id, '_wp_page_template', $template);
        }

        return;
    }

    $current_template = (string) get_post_meta($page->ID, '_wp_page_template', true);
    if ($current_template !== $template && is_readable($template_path)) {
        update_post_meta($page->ID, '_wp_page_template', $template);
    }
}

add_action('after_switch_theme', 'aidunite_ensure_admin_payment_management_page');
add_action('admin_init', 'aidunite_ensure_admin_payment_management_page');
add_action('init', 'aidunite_ensure_admin_payment_management_page', 25);

add_action('after_switch_theme', 'aidunite_ensure_admin_payment_list_page');
add_action('admin_init', 'aidunite_ensure_admin_payment_list_page');
add_action('init', 'aidunite_ensure_admin_payment_list_page', 25);

/**
 * 管理者用売上・見込みページ（/admin-payment-revenue）を確保
 */
function aidunite_ensure_admin_payment_revenue_page() {
    $page = get_page_by_path('admin-payment-revenue');
    $template = 'page-admin-payment-revenue.php';
    $template_path = get_template_directory() . '/' . $template;

    if (!$page) {
        $page_id = wp_insert_post([
            'post_title'   => '売上・見込み',
            'post_name'    => 'admin-payment-revenue',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '',
        ]);
        if ($page_id && !is_wp_error($page_id) && is_readable($template_path)) {
            update_post_meta($page_id, '_wp_page_template', $template);
        }

        return;
    }

    $current_template = (string) get_post_meta($page->ID, '_wp_page_template', true);
    if ($current_template !== $template && is_readable($template_path)) {
        update_post_meta($page->ID, '_wp_page_template', $template);
    }
}

add_action('after_switch_theme', 'aidunite_ensure_admin_payment_revenue_page');
add_action('admin_init', 'aidunite_ensure_admin_payment_revenue_page');
add_action('init', 'aidunite_ensure_admin_payment_revenue_page', 25);
