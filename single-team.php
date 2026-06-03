<?php
/**
 * team CPT single: public profile page.
 *
 * @package AidUnite
 */

global $post;

$team_id = 0;
if ($post instanceof WP_Post && $post->post_type === 'team') {
    $team_id = (int) $post->ID;
}
if ($team_id <= 0) {
    $team_id = (int) get_queried_object_id();
}
if ($team_id <= 0) {
    $team_id = (int) get_the_ID();
}

if (!function_exists('aidunite_team_public_profile_can_view')) {
    wp_die('Team public profile is not available.', 'Error', ['response' => 500]);
}

if (!aidunite_team_public_profile_can_view($team_id)) {
    aidunite_team_public_profile_die_forbidden($team_id);
}

if (function_exists('aidunite_team_public_profile_enqueue_assets')) {
    aidunite_team_public_profile_enqueue_assets();
}

$back_url = '';
if (function_exists('aidunite_team_public_profile_resolve_shell_meta')) {
    $shell_meta = aidunite_team_public_profile_resolve_shell_meta($team_id);
    $back_url = (string) ($shell_meta['back_url'] ?? '');
} elseif (
    is_user_logged_in()
    && function_exists('aidunite_team_settings_user_has_team_leader_access')
    && aidunite_team_settings_user_has_team_leader_access((int) get_current_user_id(), $team_id)
    && function_exists('aidunite_get_team_settings_page_url')
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
