<?php
/**
 * Template Name: チーム公開プロフィール
 *
 * 固定ページに本テンプレートを割り当て、URL 例: /team-public/?team_id=123
 * 正本は team CPT のパーマリンク（single-team.php）。共有・プレビューはどちらでも可。
 *
 * @package AidUnite
 */

$team_id = isset($_GET['team_id']) ? (int) $_GET['team_id'] : 0;
if ($team_id <= 0 && isset($_GET['id'])) {
    $team_id = (int) $_GET['id'];
}

if ($team_id <= 0) {
    get_header();
    echo '<main class="team-public-profile" role="main"><p class="team-public-profile__empty">チーム ID が指定されていません（<code>?team_id=</code> を付けてください）。</p></main>';
    get_footer();
    return;
}

if (!function_exists('aidunite_team_public_profile_can_view')
    || !aidunite_team_public_profile_can_view($team_id)) {
    aidunite_team_public_profile_die_forbidden($team_id);
}

aidunite_team_public_profile_enqueue_assets();

$back_url = '';
if (
    is_user_logged_in()
    && function_exists('aidunite_team_public_profile_resolve_shell_meta')
) {
    $shell_meta = aidunite_team_public_profile_resolve_shell_meta($team_id);
    $back_url = (string) ($shell_meta['back_url'] ?? '');
} elseif (
    is_user_logged_in()
    && function_exists('aidunite_get_team_settings_page_url')
    && function_exists('aidunite_team_settings_user_has_team_leader_access')
    && aidunite_team_settings_user_has_team_leader_access((int) get_current_user_id(), $team_id)
) {
    $back_url = aidunite_get_team_settings_page_url();
}

get_header();

$team_public_shell_opened = function_exists('aidunite_team_public_profile_open_integrated_shell')
    && aidunite_team_public_profile_open_integrated_shell($team_id);

echo aidunite_render_team_public_profile($team_id, [
    'back_url' => $back_url,
    'integrated_shell' => $team_public_shell_opened,
]);

if ($team_public_shell_opened && function_exists('aidunite_web_app_page_shell_close')) {
    aidunite_web_app_page_shell_close();
}

get_footer();
