<?php
/**
 * テーマ SVG アイコン registry（絵文字・Unicode 記号 → basename）
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * スケジュール種別 → SVG basename
 *
 * @return array<string, string>
 */
function aidunite_get_schedule_type_icon_map() {
    return [
        '公式試合'         => 'trophy',
        '練習試合'         => 'handshake',
        '練習試合（募集）' => 'handshake',
        '練習試合（仮）'   => 'handshake',
        '練習'             => 'exercise',
        '練習（仮）'       => 'exercise',
        '合同練習'         => 'group',
        '合同練習（募集）' => 'group',
        '合同練習（仮）'   => 'group',
        '合宿'             => 'camping',
        '合宿（仮）'       => 'camping',
        '遠征'             => 'flight_takeoff',
        '遠征（仮）'       => 'flight_takeoff',
        'イベント'         => 'celebration',
        'イベント（仮）'   => 'celebration',
        '休み'             => 'airline_seat_recline_extra',
        '休み（仮）'       => 'airline_seat_recline_extra',
        'ミーティング'     => 'list_alt_add',
        'ミーティング（仮）' => 'list_alt_add',
        '会議'             => 'list_alt_add',
        '会議（仮）'       => 'list_alt_add',
        '未定'             => 'question_mark',
        '未定（仮）'       => 'question_mark',
        'その他'           => 'calendar_month',
    ];
}

/**
 * @param string $type
 * @return string basename
 */
function aidunite_get_schedule_type_icon_basename($type) {
    $type = (string) $type;
    $map  = aidunite_get_schedule_type_icon_map();

    if ($type === '') {
        return 'calendar_month';
    }
    if (isset($map[$type])) {
        return $map[$type];
    }
    foreach ($map as $key => $basename) {
        if (strpos($type, $key) !== false || strpos($key, $type) !== false) {
            return $basename;
        }
    }
    return 'calendar_month';
}

/**
 * インライン SVG を span ラッパー付きで返す
 *
 * @param string $basename
 * @param array  $attrs
 * @param string $wrapper_class
 * @return string
 */
function aidunite_render_theme_icon($basename, $attrs = [], $wrapper_class = 'aidunite-icon') {
    $basename = sanitize_file_name((string) $basename);
    if ($basename === '') {
        return '';
    }

    $defaults = ['class' => 'aidunite-icon__svg'];
    if (!isset($attrs['width']) && !isset($attrs['height'])) {
        $defaults['width']  = '20';
        $defaults['height'] = '20';
    }
    $attrs = array_merge($defaults, $attrs);

    $svg = aidunite_get_theme_icon_svg($basename, $attrs);
    if ($svg === '') {
        return '';
    }

    $classes = trim('aidunite-icon aidunite-icon--' . sanitize_html_class($basename) . ' ' . (string) $wrapper_class);

    return '<span class="' . esc_attr($classes) . '" aria-hidden="true">' . $svg . '</span>';
}

/**
 * ダッシュボード等：basename または旧絵文字を SVG に解決して出力
 *
 * @param string $icon icon_svg basename
 * @param array  $attrs
 * @return string
 */
function aidunite_render_dashboard_icon($icon, $attrs = []) {
    $icon = (string) $icon;
    if ($icon === '') {
        return '';
    }
    if (preg_match('/^[a-z0-9_-]+$/i', $icon)) {
        return aidunite_render_theme_icon($icon, $attrs);
    }
    return '';
}

/**
 * JS 用に preload する SVG basename 一覧
 *
 * @return string[]
 */
function aidunite_get_theme_icon_js_basenames() {
    $names = array_values(aidunite_get_schedule_type_icon_map());
    $names = array_merge($names, [
        'check_circle', 'brightness_alert', 'info', 'close', 'menu', 'chevron_left', 'chevron_right',
        'arrow_forward', 'arrow_downward', 'arrow_upward', 'hourglass_empty', 'add', 'star', 'settings',
        'check', 'person_man', 'person_woman', 'visibility', 'visibility_lock', 'lock', 'delete_forever',
        'stylus', 'contact_mail', 'vs', 'hand_gesture', 'weight', 'currency_yen', 'redeem',
        'trending_up', 'trending_down', 'payments', 'save', 'start', 'mode_heat', 'robot_2', 'palette',
        'campaign', 'build', 'basketball', 'chat', 'notification_add', 'forward_to_inbox', 'mail',
        'bar_chart_4_bars', 'send', 'home', 'group', 'handshake', 'trophy', 'calendar_month', 'today', 'celebration',
        'list_alt_add', 'exercise', 'camping', 'flight_takeoff', 'airline_seat_recline_extra',
        'question_mark', 'stadium', 'directions_run', 'schedule', 'family_group', 'guardian-invite',
        'attach_file', 'sms', 'photo_camera', 'id_card', 'replay', 'sliders', 'lightbulb',
    ]);
    return array_values(array_unique(array_filter($names)));
}

/**
 * @param string[]|null $basenames
 * @return array<string, string> basename => inline SVG
 */
function aidunite_build_theme_icon_js_manifest($basenames = null) {
    if ($basenames === null) {
        $basenames = aidunite_get_theme_icon_js_basenames();
    }
    $manifest = [];
    foreach ($basenames as $name) {
        $name = sanitize_file_name((string) $name);
        if ($name === '') {
            continue;
        }
        $svg = aidunite_get_theme_icon_svg($name, ['class' => 'aidunite-icon__svg']);
        if ($svg !== '') {
            $manifest[$name] = $svg;
        }
    }
    return $manifest;
}

/**
 * トースト type → SVG basename
 *
 * @param string $type
 * @return string
 */
function aidunite_get_toast_icon_basename($type) {
    $map = [
        'success' => 'check_circle',
        'error'   => 'brightness_alert',
        'info'    => 'info',
        'warning' => 'brightness_alert',
    ];
    return $map[$type] ?? 'info';
}
