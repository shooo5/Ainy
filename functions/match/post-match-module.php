<?php
/**
 * 試合後アンケート画面：運営表示モジュール（PostMatchModule）
 * 仕様: match-request.md 第18章（実装アダプタ = CPT + 反応ログテーブル）
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/post-match-module-schema.php';

/** バナー表示用 WordPress 画像サイズ（登録名） */
define('AIDUNITE_POST_MATCH_MODULE_BANNER_SIZE', 'aidunite_pmm_banner');

/** 推奨アップロード（16:9） */
define('AIDUNITE_POST_MATCH_MODULE_BANNER_RECOMMENDED_WIDTH', 800);
define('AIDUNITE_POST_MATCH_MODULE_BANNER_RECOMMENDED_HEIGHT', 450);

/**
 * バナー用の中間サイズを登録（表示はこのサイズを優先）
 */
function aidunite_post_match_module_register_banner_image_size() {
    add_image_size(
        AIDUNITE_POST_MATCH_MODULE_BANNER_SIZE,
        AIDUNITE_POST_MATCH_MODULE_BANNER_RECOMMENDED_WIDTH,
        AIDUNITE_POST_MATCH_MODULE_BANNER_RECOMMENDED_HEIGHT,
        false
    );
}
add_action('after_setup_theme', 'aidunite_post_match_module_register_banner_image_size');

/**
 * @return string HTML（管理画面の案内文）
 */
function aidunite_post_match_module_banner_admin_help_html() {
    $w = (int) AIDUNITE_POST_MATCH_MODULE_BANNER_RECOMMENDED_WIDTH;
    $h = (int) AIDUNITE_POST_MATCH_MODULE_BANNER_RECOMMENDED_HEIGHT;
    return '<p class="admin-pmm-help">'
        . '<strong>推奨サイズ:</strong> ' . $w . '×' . $h . 'px 前後（16:9）。形式は JPG / PNG / WebP。1枚 500KB 以下目安。<br>'
        . '<strong>表示:</strong> PC ではカード内のバナー列（最大幅 約240px）、スマホではカード幅いっぱい。<br>'
        . '<strong>2通りの登録:</strong> ①「画像を選ぶ」（自サイトに保存） ②<strong>バナー画像URL（外部）</strong>（他社CDN・スポンサー配信画像）。両方ある場合は<strong>①を優先</strong>。<br>'
        . '<strong>外部URL:</strong> http / https の画像URL（例: <code>https://example.com/banner.jpg</code>）。http はプレビューや一部環境で表示されないことがあります。詳細文にURLを書いても画像にはなりません—必ず下の専用欄を使ってください。'
        . '</p>';
}

/**
 * 外部バナーURL（http / https）
 *
 * @param string $url
 * @return string
 */
function aidunite_post_match_module_sanitize_banner_external_url($url) {
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }
    $url = esc_url_raw($url);
    if ($url === '' || !wp_http_validate_url($url)) {
        return '';
    }
    $parsed = wp_parse_url($url);
    $scheme = isset($parsed['scheme']) ? strtolower((string) $parsed['scheme']) : '';
    if (!in_array($scheme, ['http', 'https'], true)) {
        return '';
    }
    return $url;
}

/**
 * 表示用バナーURL（メディア優先 → 外部URL）
 *
 * @param int    $attachment_id
 * @param string $external_url
 * @return string
 */
function aidunite_post_match_module_resolve_banner_url($attachment_id, $external_url = '') {
    $attachment_id = (int) $attachment_id;
    if ($attachment_id > 0) {
        $from_media = aidunite_post_match_module_banner_attachment_url($attachment_id);
        if ($from_media !== '') {
            return $from_media;
        }
    }
    return aidunite_post_match_module_sanitize_banner_external_url($external_url);
}

/**
 * @param int $attachment_id
 * @return string
 */
function aidunite_post_match_module_banner_attachment_url($attachment_id) {
    $attachment_id = (int) $attachment_id;
    if ($attachment_id <= 0) {
        return '';
    }
    $url = wp_get_attachment_image_url($attachment_id, AIDUNITE_POST_MATCH_MODULE_BANNER_SIZE);
    if (!$url) {
        $url = wp_get_attachment_image_url($attachment_id, 'large');
    }
    if (!$url) {
        $url = wp_get_attachment_image_url($attachment_id, 'medium');
    }
    if (!$url) {
        $url = wp_get_attachment_url($attachment_id);
    }
    return $url ? (string) $url : '';
}

/** @return string[] */
function aidunite_post_match_module_types() {
    return ['notice', 'campaign', 'sponsor'];
}

/** @return string[] */
function aidunite_post_match_module_slot_keys() {
    return ['after_match_feedback', 'after_match_feedback_answered'];
}

/**
 * @param string $type
 */
function aidunite_post_match_module_type_label($type) {
    $labels = [
        'notice'   => 'お知らせ',
        'campaign' => '大会・イベント',
        'sponsor'  => '支援・サービス',
    ];
    return $labels[$type] ?? $type;
}

/**
 * @param string $slot
 */
function aidunite_post_match_module_slot_label($slot) {
    $labels = [
        'after_match_feedback'          => '未回答時（フォーム下）',
        'after_match_feedback_answered' => '回答済み・送信後',
    ];
    return $labels[$slot] ?? $slot;
}

/**
 * @param int                  $post_id
 * @param array<string, mixed> $data
 */
function aidunite_post_match_module_apply_meta($post_id, array $data) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return;
    }

    $type = isset($data['module_type']) ? sanitize_key($data['module_type']) : 'notice';
    if (!in_array($type, aidunite_post_match_module_types(), true)) {
        $type = 'notice';
    }
    update_post_meta($post_id, 'module_type', $type);

    $slot = isset($data['slot_key']) ? sanitize_key($data['slot_key']) : 'after_match_feedback';
    if (!in_array($slot, aidunite_post_match_module_slot_keys(), true)) {
        $slot = 'after_match_feedback';
    }
    update_post_meta($post_id, 'slot_key', $slot);

    update_post_meta($post_id, 'is_active', !empty($data['is_active']) ? '1' : '0');
    update_post_meta($post_id, 'priority', isset($data['priority']) ? (int) $data['priority'] : 10);

    foreach (['start_at', 'end_at'] as $dt_key) {
        if (!empty($data[$dt_key])) {
            $val = sanitize_text_field((string) $data[$dt_key]);
            $ts = strtotime(str_replace('T', ' ', $val));
            update_post_meta($post_id, $dt_key, $ts ? wp_date('Y-m-d H:i:s', $ts) : '');
        } else {
            delete_post_meta($post_id, $dt_key);
        }
    }

    update_post_meta(
        $post_id,
        'description',
        isset($data['description']) ? sanitize_textarea_field((string) $data['description']) : ''
    );
    update_post_meta($post_id, 'banner_image_id', isset($data['banner_image_id']) ? (int) $data['banner_image_id'] : 0);
    $external = isset($data['banner_external_url'])
        ? aidunite_post_match_module_sanitize_banner_external_url((string) $data['banner_external_url'])
        : '';
    if ($external !== '') {
        update_post_meta($post_id, 'banner_external_url', $external);
    } else {
        delete_post_meta($post_id, 'banner_external_url');
    }
    update_post_meta(
        $post_id,
        'cta_label',
        isset($data['cta_label']) ? sanitize_text_field((string) $data['cta_label']) : ''
    );
    update_post_meta(
        $post_id,
        'cta_url',
        isset($data['cta_url']) ? esc_url_raw((string) $data['cta_url']) : ''
    );

    $target_sport = isset($data['target_sport']) ? sanitize_text_field((string) $data['target_sport']) : '';
    update_post_meta($post_id, 'target_sport', $target_sport);

    $target_region = isset($data['target_region']) ? sanitize_text_field((string) $data['target_region']) : '';
    update_post_meta($post_id, 'target_region', $target_region);
}

/**
 * CPT 登録（管理用永続化アダプタ）
 */
function aidunite_register_post_match_module_cpt() {
    register_post_type('post_match_module', [
        'labels' => [
            'name'          => '試合後モジュール',
            'singular_name' => '試合後モジュール',
            'add_new_item'  => 'モジュールを追加',
            'edit_item'     => 'モジュールを編集',
        ],
        'public'       => false,
        'show_ui'      => false,
        'show_in_menu' => false,
        'supports'     => ['title'],
        'capability_type' => 'post',
        'map_meta_cap'    => true,
    ]);
}
add_action('init', 'aidunite_register_post_match_module_cpt');

/**
 * @param int $post_id
 * @return array<string, mixed>
 */
function aidunite_post_match_module_get_meta($post_id) {
    $post_id = (int) $post_id;
    return [
        'id'              => $post_id,
        'type'            => (string) get_post_meta($post_id, 'module_type', true) ?: 'notice',
        'slot_key'        => (string) get_post_meta($post_id, 'slot_key', true) ?: 'after_match_feedback',
        'is_active'       => get_post_meta($post_id, 'is_active', true) !== '0',
        'start_at'        => (string) get_post_meta($post_id, 'start_at', true),
        'end_at'          => (string) get_post_meta($post_id, 'end_at', true),
        'priority'        => (int) get_post_meta($post_id, 'priority', true),
        'title'           => get_the_title($post_id),
        'description'     => (string) get_post_meta($post_id, 'description', true),
        'banner_image_id'     => (int) get_post_meta($post_id, 'banner_image_id', true),
        'banner_external_url' => (string) get_post_meta($post_id, 'banner_external_url', true),
        'cta_label'           => (string) get_post_meta($post_id, 'cta_label', true),
        'cta_url'         => (string) get_post_meta($post_id, 'cta_url', true),
        'target_sport'    => (string) get_post_meta($post_id, 'target_sport', true),
        'target_region'   => (string) get_post_meta($post_id, 'target_region', true),
    ];
}

/**
 * 表示条件（競技・地域）— 未設定は全チームに表示
 *
 * @param array<string, mixed> $module
 * @param array<string, mixed> $context
 */
/**
 * 競技ターゲット一致（未設定チームは除外しない）
 *
 * @param string $target_sport
 * @param string $team_sport
 */
function aidunite_post_match_module_sport_matches($target_sport, $team_sport) {
    $target_sport = sanitize_key($target_sport);
    $team_sport = trim((string) $team_sport);
    if ($target_sport === '' || $team_sport === '') {
        return true;
    }
    if ($target_sport === $team_sport) {
        return true;
    }
    if ($target_sport === 'basketball') {
        $aliases = ['basketball', 'バスケットボール', 'バスケ', 'バスケット'];
        return in_array($team_sport, $aliases, true)
            || stripos($team_sport, 'バスケ') !== false
            || stripos($team_sport, 'basket') !== false;
    }
    return $target_sport === sanitize_key($team_sport);
}

function aidunite_post_match_module_matches_team_target(array $module, array $context) {
    $team_id = (int) ($context['team_id'] ?? 0);
    if ($team_id <= 0) {
        return true;
    }

    $target_sport = (string) ($module['target_sport'] ?? '');
    if ($target_sport !== '') {
        $team_sport = (string) get_post_meta($team_id, 'sport_type', true);
        if ($team_sport !== '' && !aidunite_post_match_module_sport_matches($target_sport, $team_sport)) {
            return false;
        }
    }

    $target_region = (string) ($module['target_region'] ?? '');
    if ($target_region !== '' && function_exists('aidunite_get_team_activity_profile')) {
        $profile = aidunite_get_team_activity_profile($team_id);
        $pref = (string) ($profile['activity_prefecture'] ?? '');
        // チーム側の地域が未登録のときは除外しない（MVP: 取りこぼし防止）
        if ($pref !== '' && $pref !== $target_region) {
            return false;
        }
    }

    return true;
}

/**
 * 管理者向け: ユーザー画面で非表示になりうる理由
 *
 * @param array<string, mixed> $module
 * @return string[]
 */
function aidunite_post_match_module_visibility_warnings(array $module) {
    if (empty($module['is_active'])) {
        return ['表示OFF'];
    }
    $warnings = [];
    $now_ts = (int) current_time('timestamp');
    if (!aidunite_post_match_module_in_schedule($module, $now_ts)) {
        if (!empty($module['start_at'])) {
            $start = strtotime($module['start_at']);
            if ($start && $now_ts < $start) {
                $warnings[] = '表示開始前';
            }
        }
        if (!empty($module['end_at'])) {
            $end = strtotime($module['end_at']);
            if ($end && $now_ts > $end) {
                $warnings[] = '表示終了後';
            }
        }
        if ($warnings === []) {
            $warnings[] = '表示期間外';
        }
    }
    return $warnings;
}

/**
 * @param int $user_id
 * @param int $team_id
 * @param int $match_id
 * @return array{user_id:int,team_id:int,match_id:int,now_ts:int}
 */
function aidunite_build_post_match_module_context($user_id, $team_id, $match_id) {
    return [
        'user_id'  => (int) $user_id,
        'team_id'  => (int) $team_id,
        'match_id' => (int) $match_id,
        'now_ts'   => (int) current_time('timestamp'),
    ];
}

/**
 * モジュールが期間内か
 *
 * @param array<string, mixed> $module
 * @param int                  $now_ts
 */
function aidunite_post_match_module_in_schedule(array $module, $now_ts) {
    if (empty($module['is_active'])) {
        return false;
    }
    if (!empty($module['start_at'])) {
        $start = strtotime($module['start_at']);
        if ($start && $now_ts < $start) {
            return false;
        }
    }
    if (!empty($module['end_at'])) {
        $end = strtotime($module['end_at']);
        if ($end && $now_ts > $end) {
            return false;
        }
    }
    return true;
}

/**
 * @param array<string, mixed> $body
 * @return array<string, mixed>
 */
function aidunite_post_match_module_normalize_schedule_body(array $body) {
    $out = $body;
    if (!empty($body['no_period'])) {
        $out['start_at'] = '';
        $out['end_at'] = '';
        return $out;
    }
    foreach (['start_at', 'end_at'] as $key) {
        if (empty($body[$key])) {
            $out[$key] = '';
            continue;
        }
        $val = sanitize_text_field((string) $body[$key]);
        $ts = strtotime(str_replace('T', ' ', $val));
        $out[$key] = $ts ? wp_date('Y-m-d H:i:s', $ts) : '';
    }
    if (isset($body['is_active'])) {
        $out['is_active'] = !empty($body['is_active']);
    }
    return $out;
}

/**
 * @param array<string, mixed> $body
 * @param array<string, mixed> $module
 */
function aidunite_post_match_module_preview_schedule_note(array $body, array $module) {
    $now_ts = (int) current_time('timestamp');
    $is_active = !isset($body['is_active']) || !empty($body['is_active']);
    $in_schedule = aidunite_post_match_module_in_schedule([
        'is_active' => $is_active,
        'start_at'  => $body['start_at'] ?? '',
        'end_at'    => $body['end_at'] ?? '',
    ], $now_ts);

    if (!$is_active) {
        return '編集中モジュール: 非表示のためユーザー画面には出ません';
    }
    if (!$in_schedule) {
        return '編集中モジュール: 表示期間外のためユーザー画面には出ません';
    }
    return '編集中モジュール: ユーザー画面に表示されます';
}

/**
 * @param string               $slot_key
 * @param array<string, mixed> $context
 * @return array<int, array<string, mixed>>
 */
function aidunite_get_post_match_modules_for_slot($slot_key, array $context) {
    $slot_key = sanitize_key($slot_key);
    $now_ts = isset($context['now_ts']) ? (int) $context['now_ts'] : (int) current_time('timestamp');

    // Phase A: 未回答・回答済みのどちらでも同じ運営モジュールを出す（枠の取り違え防止）
    $slot_keys = array_values(array_unique([
        $slot_key,
        'after_match_feedback',
        'after_match_feedback_answered',
    ]));

    $posts = get_posts([
        'post_type'      => 'post_match_module',
        'post_status'    => 'publish',
        'posts_per_page' => 50,
        'orderby'        => 'meta_value_num',
        'meta_key'       => 'priority',
        'order'          => 'ASC',
        'meta_query'     => [
            [
                'key'     => 'slot_key',
                'value'   => $slot_keys,
                'compare' => 'IN',
            ],
        ],
    ]);

    $modules = [];
    $seen_ids = [];
    foreach ($posts as $post) {
        if (isset($seen_ids[$post->ID])) {
            continue;
        }
        $seen_ids[$post->ID] = true;
        $row = aidunite_post_match_module_get_meta($post->ID);
        if (!aidunite_post_match_module_in_schedule($row, $now_ts)) {
            continue;
        }
        if (!aidunite_post_match_module_matches_team_target($row, $context)) {
            continue;
        }
        $row['banner_url'] = aidunite_post_match_module_resolve_banner_url(
            (int) ($row['banner_image_id'] ?? 0),
            (string) ($row['banner_external_url'] ?? '')
        );
        $type = $row['type'];
        $labels = [
            'notice'   => 'お知らせ',
            'campaign' => '大会・イベント',
            'sponsor'  => '支援・サービス',
        ];
        $row['type_label'] = $labels[$type] ?? $type;
        $modules[] = $row;
    }

    usort($modules, static function ($a, $b) {
        return ((int) $a['priority']) <=> ((int) $b['priority']);
    });

    return $modules;
}

/**
 * 1件分のモジュールカード HTML
 *
 * @param array<string, mixed> $module id, type, type_label, title, description, banner_url, cta_label, cta_url
 * @param array<string, mixed> $options preview=true で CTA を無効化・data 属性付与
 * @return string
 */
function aidunite_render_post_match_module_card(array $module, array $options = []) {
    $is_preview = !empty($options['preview']);
    $id = (int) ($module['id'] ?? 0);
    $type = sanitize_key((string) ($module['type'] ?? 'notice'));
    if (!in_array($type, aidunite_post_match_module_types(), true)) {
        $type = 'notice';
    }
    $type_label = !empty($module['type_label'])
        ? (string) $module['type_label']
        : aidunite_post_match_module_type_label($type);
    $title = (string) ($module['title'] ?? '');
    $description = (string) ($module['description'] ?? '');
    $banner_url = (string) ($module['banner_url'] ?? '');
    $cta_url = !empty($module['cta_url']) ? (string) $module['cta_url'] : '';
    $cta_label = !empty($module['cta_label']) ? (string) $module['cta_label'] : '詳しく見る';

    $show_new_badge = ($type === 'campaign');
    ob_start();
    echo '<article class="mf-event-card post-match-module post-match-module--' . esc_attr($type) . '"';
    if ($id > 0) {
        echo ' data-module-id="' . esc_attr((string) $id) . '"';
    }
    if ($is_preview) {
        echo ' data-testid="post-match-module-preview-card"';
    } else {
        echo ' data-testid="post-match-module-card"';
    }
    echo '>';

    echo '<div class="mf-event-card__label">';
    echo esc_html($type_label);
    if ($show_new_badge) {
        echo ' <b class="mf-event-card__new">NEW</b>';
    }
    echo '</div>';

    echo '<div class="mf-event-card__content">';
    if ($banner_url !== '') {
        echo '<div class="mf-event-card__banner post-match-module__banner"><img src="' . esc_url($banner_url) . '" alt="" loading="lazy"></div>';
    }
    echo '<div class="mf-event-card__body">';
    if ($title !== '') {
        echo '<h3 class="post-match-module__title">' . esc_html($title) . '</h3>';
    } else {
        echo '<h3 class="post-match-module__title post-match-module__title--placeholder">（タイトル未入力）</h3>';
    }
    if ($description !== '') {
        echo '<p class="post-match-module__desc">' . nl2br(esc_html($description)) . '</p>';
    }
    if ($cta_url !== '') {
        if ($is_preview) {
            echo '<span class="mf-event-card__cta post-match-module__cta-link" tabindex="-1" aria-hidden="true">';
            echo esc_html($cta_label) . ' ↗';
            echo '</span>';
        } else {
            echo '<a href="' . esc_url($cta_url) . '" class="mf-event-card__cta post-match-module__cta-link" data-testid="post-match-module-cta" target="_blank" rel="noopener noreferrer">';
            echo esc_html($cta_label) . ' ↗';
            echo '</a>';
        }
    }
    echo '</div></div>';
    echo '</article>';
    return (string) ob_get_clean();
}

/**
 * プレビュー用モジュール配列を組み立て
 *
 * @param array<string, mixed> $data
 * @return array<string, mixed>
 */
function aidunite_post_match_module_build_preview_data(array $data) {
    $type = isset($data['module_type']) ? sanitize_key($data['module_type']) : 'notice';
    if (!in_array($type, aidunite_post_match_module_types(), true)) {
        $type = 'notice';
    }
    $banner_id = isset($data['banner_image_id']) ? (int) $data['banner_image_id'] : 0;
    $external = isset($data['banner_external_url']) ? (string) $data['banner_external_url'] : '';
    $banner_url = aidunite_post_match_module_resolve_banner_url($banner_id, $external);

    return [
        'id'          => isset($data['module_id']) ? (int) $data['module_id'] : 0,
        'type'        => $type,
        'type_label'  => aidunite_post_match_module_type_label($type),
        'title'       => isset($data['title']) ? sanitize_text_field($data['title']) : '',
        'description' => isset($data['description']) ? sanitize_textarea_field($data['description']) : '',
        'banner_url'  => $banner_url,
        'cta_label'   => isset($data['cta_label']) ? sanitize_text_field($data['cta_label']) : '',
        'cta_url'     => isset($data['cta_url']) ? esc_url_raw($data['cta_url']) : '',
    ];
}

/**
 * @param string               $slot_key
 * @param array<string, mixed> $context
 */
function aidunite_render_post_match_modules($slot_key, array $context) {
    $modules = aidunite_get_post_match_modules_for_slot($slot_key, $context);
    if ($modules === []) {
        return;
    }

    echo '<section class="mf-modules-section" data-testid="post-match-modules-section" aria-labelledby="mf-modules-heading">';
    echo '<h2 class="mf-modules-section__title" id="mf-modules-heading">チーム活動を応援する情報</h2>';
    echo '<div class="post-match-modules mf-modules-block" data-testid="post-match-modules" data-slot="' . esc_attr($slot_key) . '">';

    foreach ($modules as $module) {
        echo aidunite_render_post_match_module_card($module);
    }

    echo '</div></section>';
}

/**
 * @param int    $post_id
 * @param string $key
 */
function aidunite_post_match_module_datetime_local($post_id, $key) {
    $raw = (string) get_post_meta($post_id, $key, true);
    if ($raw === '') {
        return '';
    }
    $ts = strtotime($raw);
    if (!$ts) {
        return '';
    }
    return wp_date('Y-m-d\TH:i', $ts);
}

/**
 * フロント資産
 */
function aidunite_enqueue_post_match_module_assets() {
    if (!is_page_template('page-match-feedback.php')) {
        return;
    }

    $theme_uri = get_stylesheet_directory_uri();
    $ver = wp_get_theme()->get('Version') ?: '1.0.0';

    wp_enqueue_style(
        'aidunite-post-match-modules',
        $theme_uri . '/assets/css/components/post-match-module.css',
        [],
        $ver
    );

    wp_enqueue_script(
        'aidunite-post-match-modules',
        $theme_uri . '/assets/js/match/post-match-modules.js',
        [],
        $ver,
        true
    );

    wp_localize_script('aidunite-post-match-modules', 'aidunitePostMatchModulesConfig', [
        'restBase' => esc_url_raw(rest_url('aidunite/v1')),
        'nonce'    => wp_create_nonce('wp_rest'),
    ]);
}
add_action('wp_enqueue_scripts', 'aidunite_enqueue_post_match_module_assets');
