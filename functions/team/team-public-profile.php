<?php
/**
 * Team public profile (simple public-facing page).
 *
 * Canonical URL: team CPT permalink (single-team.php).
 * Optional: fixed page with template page-team-public.php (?team_id=).
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once get_stylesheet_directory() . '/functions/team/team-display-template.php';

/**
 * @param int $team_id
 * @return string
 */
function aidunite_get_team_public_profile_url($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return home_url('/');
    }

    $pages = get_posts([
        'post_type' => 'page',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'meta_key' => '_wp_page_template',
        'meta_value' => 'page-team-public.php',
    ]);
    if (!empty($pages[0]) && $pages[0] instanceof WP_Post) {
        return (string) add_query_arg(['team_id' => $team_id], get_permalink($pages[0]->ID));
    }

    $url = get_permalink($team_id);
    return $url ? (string) $url : home_url('/');
}

/**
 * @param int      $team_id
 * @param int|null $user_id
 */
function aidunite_team_public_profile_can_view($team_id, $user_id = null) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return false;
    }
    $post = get_post($team_id);
    if (!$post || $post->post_type !== 'team') {
        return false;
    }

    if (current_user_can('administrator')) {
        return true;
    }

    $uid = $user_id !== null ? (int) $user_id : (int) get_current_user_id();
    if (
        $uid > 0
        && function_exists('aidunite_team_settings_user_has_team_leader_access')
        && aidunite_team_settings_user_has_team_leader_access($uid, $team_id)
    ) {
        return true;
    }

    if ($post->post_status !== 'publish') {
        return false;
    }

    $team_status = (string) get_post_meta($team_id, 'team_status', true);
    if ($team_status === 'pending') {
        return false;
    }
    if ($team_status !== '' && $team_status !== 'active') {
        return false;
    }

    return true;
}

/**
 * @param int $team_id
 * @param int $user_id
 */
function aidunite_team_public_profile_is_leader_preview($team_id, $user_id) {
    $team_id = (int) $team_id;
    $user_id = (int) $user_id;
    if ($team_id <= 0 || $user_id <= 0) {
        return false;
    }
    if (!function_exists('aidunite_team_settings_user_has_team_leader_access')) {
        return false;
    }
    if (!aidunite_team_settings_user_has_team_leader_access($user_id, $team_id)) {
        return false;
    }
    $post = get_post($team_id);
    if (!$post) {
        return false;
    }
    if ($post->post_status !== 'publish') {
        return true;
    }
    $team_status = (string) get_post_meta($team_id, 'team_status', true);
    return $team_status === 'pending' || ($team_status !== '' && $team_status !== 'active');
}

/**
 * @param string $raw
 */
function aidunite_team_public_profile_gender_label($raw) {
    if (function_exists('aidunite_mvp_gender_raw_is_both_legacy') && aidunite_mvp_gender_raw_is_both_legacy($raw)) {
        return '要設定（旧データ）';
    }
    if (function_exists('aidunite_team_gender_label')) {
        return aidunite_team_gender_label($raw);
    }
    $g = function_exists('aidunite_normalize_team_gender_option')
        ? aidunite_normalize_team_gender_option($raw)
        : strtolower(trim((string) $raw));
    if ($g === 'male') {
        return '男子';
    }
    if ($g === 'female') {
        return '女子';
    }
    return '—';
}

/**
 * @param int $team_id
 * @return array<string, mixed>
 */
function aidunite_team_public_profile_get_view_model($team_id) {
    $team_id = (int) $team_id;
    $base = function_exists('aidunite_get_team_display_data') ? aidunite_get_team_display_data($team_id) : [];
    if (empty($base)) {
        return [];
    }

    $unset = "\xe6\x9c\xaa\xe8\xa8\xad\xe5\xae\x9a";

    $team_place = (string) get_post_meta($team_id, 'team_place', true);
    if ($team_place === '') {
        $team_place = (string) get_post_meta($team_id, 'team_location', true);
    }

    $region = (string) ($base['region'] ?? '');
    if ($region === $unset) {
        $region = '';
    }

    $location_parts = array_filter([$region, $team_place], static function ($v) use ($unset) {
        return $v !== '' && $v !== $unset;
    });

    return array_merge($base, [
        'team_place' => $team_place,
        'location_display' => !empty($location_parts) ? implode(' / ', $location_parts) : '',
        'gender_label' => aidunite_team_public_profile_gender_label($base['team_gender_option'] ?? ''),
        'post_status' => get_post_status($team_id) ?: '',
        'team_status' => (string) get_post_meta($team_id, 'team_status', true),
        '_unset' => $unset,
    ]);
}

/**
 * UI strings (UTF-8 hex; avoids source encoding issues on Windows).
 *
 * @return array<string, string>
 */
function aidunite_team_public_profile_strings() {
    return [
        'empty' => "\xe3\x83\x81\xe3\x83\xbc\xe3\x83\xa0\xe6\x83\x85\xe5\xa0\xb1\xe3\x82\x92\xe8\xa1\xa8\xe7\xa4\xba\xe3\x81\xa7\xe3\x81\x8d\xe3\x81\xbe\xe3\x81\x9b\xe3\x82\x93\xe3\x80\x82",
        'banner_preview' => "\xe6\x89\xbf\xe8\xaa\x8d\xe5\x89\x8d\xe3\x83\xbb\xe9\x9d\x9e\xe5\x85\xac\xe9\x96\x8b\xe3\x81\xae\xe3\x83\x97\xe3\x83\xac\xe3\x83\x93\xe3\x83\xa5\xe3\x83\xbc\xe3\x81\xa7\xe3\x81\x99\xe3\x80\x82\xe4\xb8\x80\xe8\x88\xac\xe3\x81\xae\xe6\x96\xb9\xe3\x81\xab\xe3\x81\xaf\xe3\x81\xbe\xe3\x81\xa0\xe8\xa1\xa8\xe7\xa4\xba\xe3\x81\x95\xe3\x82\x8c\xe3\x81\xbe\xe3\x81\x9b\xe3\x82\x93\xe3\x80\x82",
        'banner_live' => "\xe5\x85\xac\xe9\x96\x8b\xe4\xb8\xad\xe3\x81\xae\xe3\x83\x97\xe3\x83\xad\xe3\x83\x95\xe3\x82\xa3\xe3\x83\xbc\xe3\x83\xab\xe3\x81\xa7\xe3\x81\x99\xe3\x80\x82\xe3\x81\x93\xe3\x81\xae URL \xe3\x82\x92\xe5\x85\xb1\xe6\x9c\x89\xe3\x81\x99\xe3\x82\x8b\xe3\x81\xa8\xe3\x80\x81\xe6\x9c\xaa\xe3\x83\xad\xe3\x82\xb0\xe3\x82\xa4\xe3\x83\xb3\xe3\x81\xae\xe6\x96\xb9\xe3\x82\x82\xe9\x96\xb2\xe8\xa6\xa7\xe3\x81\xa7\xe3\x81\x8d\xe3\x81\xbe\xe3\x81\x99\xe3\x80\x82",
        'basic' => "\xe5\x9f\xba\xe6\x9c\xac\xe6\x83\x85\xe5\xa0\xb1",
        'region' => "\xe6\xb4\xbb\xe5\x8b\x95\xe5\x9c\xb0\xe5\x9f\x9f",
        'category' => "\xe5\xb9\xb4\xe4\xbb\xa3\xe3\x82\xab\xe3\x83\x86\xe3\x82\xb4\xe3\x83\xaa",
        'category_short' => "\xe5\xb9\xb4\xe4\xbb\xa3",
        'type' => "\xe6\x89\x80\xe5\xb1\x9e\xe3\x82\xbf\xe3\x82\xa4\xe3\x83\x97",
        'gender' => "\xe6\x80\xa7\xe5\x88\xa5",
        'leader' => "\xe4\xbb\xa3\xe8\xa1\xa8\xe8\x80\x85",
        'card_location' => "\xe6\xb4\xbb\xe5\x8b\x95\xe5\xa0\xb4\xe6\x89\x80",
        'card_location_sub' => "\xe6\xb4\xbb\xe5\x8b\x95\xe5\x9c\xb0\xe5\x9f\x9f\xe3\x83\xbb\xe4\xbc\x9a\xe5\xa0\xb4",
        'card_class' => "\xe5\x88\x86\xe9\xa1\x9e\xe3\x83\xbb\xe5\xb1\x9e\xe6\x80\xa7",
        'card_class_sub' => "\xe3\x82\xab\xe3\x83\x86\xe3\x82\xb4\xe3\x83\xaa\xe3\x83\xbb\xe7\xa8\xae\xe5\x88\xa5\xe3\x83\xbb\xe6\x80\xa7\xe5\x88\xa5",
        'card_leader' => "\xe4\xbb\xa3\xe8\xa1\xa8\xe8\x80\x85",
        'card_leader_sub' => "\xe3\x83\x81\xe3\x83\xbc\xe3\x83\xa0\xe4\xbb\xa3\xe8\xa1\xa8\xe8\x80\x85\xe5\x90\x8d",
        'intro' => "\xe3\x83\x81\xe3\x83\xbc\xe3\x83\xa0\xe7\xb4\xb9\xe4\xbb\x8b",
        'intro_sub' => "\xe3\x83\x81\xe3\x83\xbc\xe3\x83\xa0\xe3\x81\xae\xe7\xb4\xb9\xe4\xbb\x8b\xe6\x96\x87",
        'back' => "\xe3\x83\x81\xe3\x83\xbc\xe3\x83\xa0\xe7\xae\xa1\xe7\x90\x86\xe3\x81\xab\xe6\x88\xbb\xe3\x82\x8b",
        'die_default' => "\xe3\x81\x93\xe3\x81\xae\xe3\x83\x81\xe3\x83\xbc\xe3\x83\xa0\xe3\x81\xae\xe5\x85\xac\xe9\x96\x8b\xe3\x83\x97\xe3\x83\xad\xe3\x83\x95\xe3\x82\xa3\xe3\x83\xbc\xe3\x83\xab\xe3\x81\xaf\xe3\x80\x81\xe7\x8f\xbe\xe5\x9c\xa8\xe8\xa1\xa8\xe7\xa4\xba\xe3\x81\xa7\xe3\x81\x8d\xe3\x81\xbe\xe3\x81\x9b\xe3\x82\x93\xe3\x80\x82",
        'die_pending' => "\xe3\x81\x93\xe3\x81\xae\xe3\x83\x81\xe3\x83\xbc\xe3\x83\xa0\xe3\x81\xaf\xe6\x89\xbf\xe8\xaa\x8d\xe5\xbe\x85\xe3\x81\xa1\xe3\x81\xae\xe3\x81\x9f\xe3\x82\x81\xe3\x80\x81\xe5\x85\xac\xe9\x96\x8b\xe3\x83\x97\xe3\x83\xad\xe3\x83\x95\xe3\x82\xa3\xe3\x83\xbc\xe3\x83\xab\xe3\x81\xaf\xe3\x81\xbe\xe3\x81\xa0\xe8\xa1\xa8\xe7\xa4\xba\xe3\x81\x95\xe3\x82\x8c\xe3\x81\xa6\xe3\x81\x84\xe3\x81\xbe\xe3\x81\x9b\xe3\x82\x93\xe3\x80\x82",
        'die_title' => "\xe3\x83\x81\xe3\x83\xbc\xe3\x83\xa0\xe5\x85\xac\xe9\x96\x8b\xe3\x83\x97\xe3\x83\xad\xe3\x83\x95\xe3\x82\xa3\xe3\x83\xbc\xe3\x83\xab",
    ];
}

function aidunite_team_public_profile_enqueue_assets() {
    $css = get_stylesheet_directory() . '/assets/css/components/team-public-profile.css';
    if (!is_readable($css)) {
        return;
    }
    $panel_css = get_stylesheet_directory() . '/assets/css/components/team-form-panel.css';
    if (is_readable($panel_css)) {
        wp_enqueue_style(
            'aidunite-team-form-panel',
            get_stylesheet_directory_uri() . '/assets/css/components/team-form-panel.css',
            ['form-style'],
            (string) filemtime($panel_css)
        );
    }
    wp_enqueue_style(
        'aidunite-team-public-profile',
        get_stylesheet_directory_uri() . '/assets/css/components/team-public-profile.css',
        ['form-style', 'aidunite-team-form-panel'],
        (string) filemtime($css)
    );
}

/**
 * チーム情報チップ用 SVG アイコンキー → basename
 *
 * @return array<string, string>
 */
function aidunite_team_info_chip_icon_basenames() {
    return [
        'location'  => 'stadium',
        'category'  => 'family_group',
        'type'      => 'build',
        'gender'    => 'group',
        'leader'    => 'person_check',
        'intro'     => 'stylus',
        'base_name' => 'id_card',
        'website'   => 'home',
        'sns'       => 'campaign',
    ];
}

/**
 * @param string $icon_key aidunite_team_info_chip_icon_basenames() のキー
 */
function aidunite_team_info_chip_icon_html($icon_key) {
    $map = aidunite_team_info_chip_icon_basenames();
    $basename = $map[$icon_key] ?? 'info';
    if (!function_exists('aidunite_render_theme_icon')) {
        return '';
    }
    return aidunite_render_theme_icon($basename, ['width' => '28', 'height' => '28']);
}

/**
 * チーム申請確認 JS 用チップ SVG マップ
 *
 * @return array<string, string>
 */
function aidunite_team_info_chip_icons_for_js() {
    $icons = [];
    foreach (array_keys(aidunite_team_info_chip_icon_basenames()) as $key) {
        $icons[$key] = aidunite_team_info_chip_icon_html($key);
    }
    return $icons;
}

/**
 * Basic info chip: icon + label + value.
 *
 * @param string $icon_key
 * @param string $label
 * @param string $value
 * @param bool   $solo
 */
function aidunite_team_public_profile_render_info_chip($icon_key, $label, $value, $solo = false) {
    $label = trim((string) $label);
    $value = trim((string) $value);
    if ($label === '' || $value === '') {
        return '';
    }
    $classes = 'team-info-chip';
    if ($solo) {
        $classes .= ' team-info-chip--solo';
    }
    ob_start();
    ?>
    <article class="<?php echo esc_attr($classes); ?>">
      <span class="team-info-chip__icon" aria-hidden="true"><?php echo aidunite_team_info_chip_icon_html($icon_key); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
      <span class="team-info-chip__label"><?php echo esc_html($label); ?></span>
      <span class="team-info-chip__value"><?php echo esc_html($value); ?></span>
    </article>
    <?php
    return (string) ob_get_clean();
}

/**
 * 紹介文チップ
 *
 * @param string $icon_key
 * @param string $label
 * @param string $text
 */
function aidunite_team_public_profile_render_intro_chip($icon_key, $label, $text) {
    $label = trim((string) $label);
    $text = trim((string) $text);
    if ($label === '' || $text === '') {
        return '';
    }

    ob_start();
    ?>
    <article class="team-info-chip team-info-chip--solo team-info-chip--intro">
      <span class="team-info-chip__icon" aria-hidden="true"><?php echo aidunite_team_info_chip_icon_html($icon_key); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
      <span class="team-info-chip__label"><?php echo esc_html($label); ?></span>
      <div class="team-info-chip__prose"><?php echo nl2br(esc_html($text)); ?></div>
    </article>
    <?php
    return (string) ob_get_clean();
}

/**
 * team ?????? CSS ??????single-team.php ???
 */
function aidunite_team_public_profile_register_assets() {
    if (
        is_singular('team')
        || is_page('team-public')
        || is_page_template('page-team-public.php')
    ) {
        aidunite_team_public_profile_enqueue_assets();
    }
}

add_action('wp_enqueue_scripts', 'aidunite_team_public_profile_register_assets', 20);

/**
 * 一体型シェル用メタ（タイトル・サブタイトル・戻り先）
 *
 * @param int $team_id
 * @return array{title: string, subtitle: string, back_url: string}
 */
function aidunite_team_public_profile_resolve_shell_meta($team_id) {
    $team_id = (int) $team_id;
    $vm = aidunite_team_public_profile_get_view_model($team_id);
    $team_name = !empty($vm['team_name']) ? (string) $vm['team_name'] : 'チーム';
    $user_id = (int) get_current_user_id();
    $back_url = '';
    $subtitle = "\xe5\x85\xac\xe9\x96\x8b\xe3\x83\x97\xe3\x83\xad\xe3\x83\x95\xe3\x82\xa3\xe3\x83\xbc\xe3\x83\xab";

    if (
        $user_id > 0
        && function_exists('aidunite_team_settings_user_has_team_leader_access')
        && aidunite_team_settings_user_has_team_leader_access($user_id, $team_id)
        && function_exists('aidunite_get_team_settings_page_url')
    ) {
        $back_url = aidunite_get_team_settings_page_url();
        if (function_exists('aidunite_team_public_profile_is_leader_preview')
            && aidunite_team_public_profile_is_leader_preview($team_id, $user_id)) {
            $subtitle = "\xe3\x83\x97\xe3\x83\xac\xe3\x83\x93\xe3\x83\xa5\xe3\x83\xbc\xef\xbc\x88\xe6\x89\xbf\xe8\xaa\x8d\xe5\x89\x8d\xe3\x83\xbb\xe9\x9d\x9e\xe5\x85\xac\xe9\x96\x8b\xef\xbc\x89";
        } else {
            $subtitle = "\xe5\x85\xb1\xe6\x9c\x89\xe7\x94\xa8\xe3\x83\x97\xe3\x83\xac\xe3\x83\x93\xe3\x83\xa5\xe3\x83\xbc";
        }
    } elseif ($user_id > 0) {
        $back_url = home_url('/match-board-own');
    }

    return [
        'title' => $team_name,
        'subtitle' => $subtitle,
        'back_url' => $back_url,
    ];
}

/**
 * ログイン済み Webアプリで一体型シェルを開く
 *
 * @param int $team_id
 * @return bool シェルを開いたか
 */
function aidunite_team_public_profile_open_integrated_shell($team_id) {
    if (
        !is_user_logged_in()
        || !function_exists('aidunite_should_use_web_app_integrated_ui')
        || !aidunite_should_use_web_app_integrated_ui()
        || !function_exists('aidunite_web_app_page_shell_open')
    ) {
        return false;
    }

    $meta = aidunite_team_public_profile_resolve_shell_meta((int) $team_id);
    $shell_args = [
        'page_class' => 'page-team-public',
        'title' => $meta['title'],
        'subtitle' => $meta['subtitle'],
        'hero_size' => 'sm',
    ];
    if ($meta['back_url'] !== '') {
        $shell_args['back_url'] = $meta['back_url'];
    }

    aidunite_web_app_page_shell_open($shell_args);

    return true;
}

/**
 * @param int   $team_id
 * @param array $args back_url, integrated_shell
 */
function aidunite_render_team_public_profile($team_id, array $args = []) {
    $team_id = (int) $team_id;
    $vm = aidunite_team_public_profile_get_view_model($team_id);
    $t = aidunite_team_public_profile_strings();
    if (empty($vm)) {
        return '<p class="team-public-profile__empty">' . esc_html($t['empty']) . '</p>';
    }

    $unset = (string) ($vm['_unset'] ?? "\xe6\x9c\xaa\xe8\xa8\xad\xe5\xae\x9a");
    $user_id = (int) get_current_user_id();
    $is_preview = $user_id > 0 && aidunite_team_public_profile_is_leader_preview($team_id, $user_id);
    $is_public = ($vm['post_status'] ?? '') === 'publish'
        && (($vm['team_status'] ?? '') === '' || ($vm['team_status'] ?? '') === 'active');
    $integrated_shell = !empty($args['integrated_shell']);
    $profile_tag = $integrated_shell ? 'div' : 'main';
    $title_tag = $integrated_shell ? 'h2' : 'h1';

    ob_start();
    ?>
    <<?php echo $profile_tag; ?> class="team-public-profile<?php echo $integrated_shell ? ' team-public-profile--integrated' : ''; ?>"<?php echo $integrated_shell ? '' : ' role="main"'; ?>>
      <div class="team-public-profile__inner">
        <?php if ($is_preview) : ?>
          <div class="team-public-profile__banner team-public-profile__banner--preview" role="status">
            <?php echo esc_html($t['banner_preview']); ?>
          </div>
        <?php elseif (
            $user_id > 0
            && function_exists('aidunite_team_settings_user_has_team_leader_access')
            && aidunite_team_settings_user_has_team_leader_access($user_id, $team_id)
            && $is_public
        ) : ?>
          <div class="team-public-profile__banner team-public-profile__banner--live" role="status">
            <?php echo esc_html($t['banner_live']); ?>
          </div>
        <?php endif; ?>

        <header class="team-public-profile__hero">
          <?php if (!empty($vm['team_logo']) && function_exists('aidunite_team_logo_is_displayable') && aidunite_team_logo_is_displayable($vm['team_logo'])) : ?>
            <img class="team-public-profile__logo" src="<?php echo esc_url($vm['team_logo']); ?>" alt="" width="120" height="120" loading="lazy" />
          <?php else : ?>
            <div class="team-public-profile__logo team-public-profile__logo--placeholder" aria-hidden="true"></div>
          <?php endif; ?>
          <div class="team-public-profile__hero-text">
            <<?php echo $title_tag; ?> class="team-public-profile__title"><?php echo esc_html($vm['team_name']); ?></<?php echo $title_tag; ?>>
            <?php if (!empty($vm['sport_type']) && $vm['sport_type'] !== $unset) : ?>
              <p class="team-public-profile__sport"><?php echo esc_html($vm['sport_type']); ?></p>
            <?php endif; ?>
          </div>
        </header>

        <?php
        $basic_chips = '';
        $chip_defs = [
            [
                'icon' => 'location',
                'label' => $t['card_location'],
                'value' => (string) ($vm['location_display'] ?? ''),
                'solo' => false,
            ],
            [
                'icon' => 'category',
                'label' => $t['category_short'],
                'value' => (string) ($vm['team_category'] ?? ''),
                'solo' => false,
            ],
            [
                'icon' => 'type',
                'label' => $t['type'],
                'value' => (string) ($vm['team_type'] ?? ''),
                'solo' => false,
            ],
            [
                'icon' => 'gender',
                'label' => $t['gender'],
                'value' => (string) ($vm['gender_label'] ?? ''),
                'solo' => false,
            ],
            [
                'icon' => 'leader',
                'label' => $t['leader'],
                'value' => (string) ($vm['registrant_name'] ?? ''),
                'solo' => true,
            ],
        ];
        foreach ($chip_defs as $chip) {
            if ($chip['value'] === '' || $chip['value'] === $unset) {
                continue;
            }
            $basic_chips .= aidunite_team_public_profile_render_info_chip(
                $chip['icon'],
                $chip['label'],
                $chip['value'],
                !empty($chip['solo'])
            );
        }
        ?>
        <?php if ($basic_chips !== '') : ?>
        <section class="team-public-profile__section team-public-profile__section--cards" aria-labelledby="team-public-basic-heading">
          <h2 id="team-public-basic-heading" class="team-public-profile__section-heading"><?php echo esc_html($t['basic']); ?></h2>
          <div class="team-info-chips" data-aidunite-ui="team-public-info-chips-v1">
            <?php echo $basic_chips; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
          </div>
        </section>
        <?php endif; ?>

        <?php
        $intro_chip = !empty($vm['team_description'])
            ? aidunite_team_public_profile_render_intro_chip(
                'intro',
                $t['intro'],
                (string) $vm['team_description']
            )
            : '';
        ?>
        <?php if ($intro_chip !== '') : ?>
          <div class="team-info-chips team-info-chips--intro">
            <?php echo $intro_chip; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
          </div>
        <?php endif; ?>
        <?php if (!empty($args['back_url']) && !$integrated_shell) : ?>
          <p class="team-public-profile__back">
            <a class="team-public-profile__back-link" href="<?php echo esc_url($args['back_url']); ?>"><?php echo esc_html($t['back']); ?></a>
          </p>
        <?php endif; ?>
      </div>
    </<?php echo $profile_tag; ?>>
    <?php
    return (string) ob_get_clean();
}

/**
 * @param int $team_id
 */
function aidunite_team_public_profile_die_forbidden($team_id = 0) {
    $team_id = (int) $team_id;
    $t = aidunite_team_public_profile_strings();
    $message = $t['die_default'];
    if ($team_id > 0) {
        $post = get_post($team_id);
        if ($post && $post->post_status === 'pending') {
            $message = $t['die_pending'];
        }
    }
    wp_die(
        esc_html($message),
        esc_html($t['die_title']),
        ['response' => 403, 'back_link' => true]
    );
}
