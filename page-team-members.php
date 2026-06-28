<?php
/**
 * Template Name: チームメンバー一覧
 */

$auth_result = AidUniteAuthMiddleware::require_auth(true);
if (!$auth_result->is_valid()) {
    return;
}

$current_user_id = (int) $auth_result->user_id;
$page_ctx = aidunite_team_members_get_page_context($current_user_id, $_GET);

if (!empty($page_ctx['redirect'])) {
    wp_safe_redirect($page_ctx['redirect']);
    exit;
}

if (empty($page_ctx['ok'])) {
    wp_die(esc_html((string) ($page_ctx['error_message'] ?? 'このページを表示できません。')));
}

$team_id = (int) ($page_ctx['team_id'] ?? 0);
$is_parent_view = !empty($page_ctx['is_parent_view']);
$is_leader_view = !$is_parent_view;
$team_name = (string) ($page_ctx['team_name'] ?? 'チーム');
$page_title_shell = $is_parent_view
    ? 'お子さまの一覧'
    : ((string) ($page_ctx['list_tab'] ?? 'members') === 'parents' ? '保護者一覧' : 'メンバー一覧');

$approval_message = '';

if ($is_leader_view) {
    if (isset($_POST['approve_parent']) && isset($_POST['parent_user_id']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['approve_parent_nonce'] ?? '')), 'approve_parent_' . (int) $_POST['parent_user_id'])) {
        $approve_result = aidunite_parent_submit_approve_parent(wp_unslash($_POST), $current_user_id);
        if (!empty($approve_result['redirect'])) {
            wp_safe_redirect($approve_result['redirect']);
            exit;
        }
        if (!empty($approve_result['errors'])) {
            $approval_message = '<div class="notice notice-error"><p>' . esc_html(implode(' ', $approve_result['errors'])) . '</p></div>';
        }
    }

    if (isset($_POST['reject_parent']) && isset($_POST['parent_user_id']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['reject_parent_nonce'] ?? '')), 'reject_parent_' . (int) $_POST['parent_user_id'])) {
        $reject_result = aidunite_parent_submit_reject_parent(wp_unslash($_POST), $current_user_id);
        if (!empty($reject_result['redirect'])) {
            wp_safe_redirect($reject_result['redirect']);
            exit;
        }
        if (!empty($reject_result['errors'])) {
            $approval_message = '<div class="notice notice-error"><p>' . esc_html(implode(' ', $reject_result['errors'])) . '</p></div>';
        }
    }

    if (isset($_GET['approved']) && (string) $_GET['approved'] === '1') {
        $approval_message = '<div class="notice notice-success"><p>保護者を承認しました。</p></div>';
    } elseif (isset($_GET['rejected']) && (string) $_GET['rejected'] === '1') {
        $approval_message = '<div class="notice notice-success"><p>参加申請を却下しました。</p></div>';
    }
}

$pending_approval_ctx = $is_leader_view
    ? aidunite_parent_get_pending_approval_context($team_id, $current_user_id)
    : ['pending_count' => 0, 'pending_parents' => []];

$team_members_css = get_stylesheet_directory() . '/assets/css/pages/team-members.css';
wp_enqueue_style(
    'team-members-page',
    get_stylesheet_directory_uri() . '/assets/css/pages/team-members.css',
    ['aidunite-style', 'button-style', 'card-style', 'form-style'],
    is_readable($team_members_css) ? (string) filemtime($team_members_css) : '1.0.0'
);

$team_members_js = get_stylesheet_directory() . '/assets/js/pages/team-members.js';
wp_enqueue_script(
    'team-members-page',
    get_stylesheet_directory_uri() . '/assets/js/pages/team-members.js',
    ['aidunite-toast-notification', 'aidunite-confirm-modal'],
    is_readable($team_members_js) ? (string) filemtime($team_members_js) : '1.0.0',
    true
);
wp_localize_script(
    'team-members-page',
    'aiduniteTeamMembers',
    [
        'ajaxUrl' => admin_url('admin-ajax.php'),
    ]
);

if (function_exists('aidunite_web_app_page_prepare_hero_shell_body_class')) {
    aidunite_web_app_page_prepare_hero_shell_body_class();
}

get_header();

$team_members_shell_mode = function_exists('aidunite_web_app_page_shell_begin')
    ? aidunite_web_app_page_shell_begin([
        'page_class' => 'page-team-members' . ($is_parent_view ? ' page-team-members--parent' : ' page-team-members--leader'),
        'title' => $page_title_shell,
        'subtitle' => '所属チーム: ' . $team_name,
        'back' => true,
        'back_url' => $is_parent_view ? home_url('/mypage') : home_url('/team-settings'),
        'active_nav' => 'none',
        'actions' => [],
    ], [
        'legacy_container_class' => 'team-dashboard-container page-team-members',
        'legacy_back_url' => $is_parent_view ? home_url('/mypage') : home_url('/team-settings'),
        'legacy_back_label' => $is_parent_view ? 'マイページに戻る' : 'チーム設定に戻る',
    ])
    : 'legacy';

if ($team_members_shell_mode === 'legacy' && !function_exists('aidunite_web_app_page_shell_begin')) {
    echo '<div class="team-dashboard-container page-team-members">';
}
?>

<div class="team-members-container">
    <?php if ($approval_message !== '') : ?>
        <?php echo $approval_message; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    <?php endif; ?>

    <?php
    get_template_part('template-parts/team/team-members', 'board', [
        'ctx' => $page_ctx,
        'pending_approval_ctx' => $pending_approval_ctx,
    ]);
    ?>
</div>

<?php
if (function_exists('aidunite_web_app_page_shell_end')) {
    aidunite_web_app_page_shell_end($team_members_shell_mode);
} elseif ($team_members_shell_mode === 'legacy') {
    echo '</div>';
}
?>

<?php get_footer(); ?>
