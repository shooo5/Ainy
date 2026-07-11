<?php
/**
 * 管理者データ一覧：セル表示の統一（Ainy UI ルールブック §2.4 / §2.7）
 *
 * 種別: text（一般文字）, date（日付・日程）, time（時間帯）, gender（性別）, venue（会場）, id, datetime
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 画面表示用日付（yy/mm/dd）。保存値 Y-m-d を想定。
 *
 * @param string $date
 * @return string
 */
function aidunite_admin_list_format_date_display($date) {
    $date = trim((string) $date);
    if ($date === '' || $date === '—') {
        return '—';
    }
    if (class_exists('AidUniteDateUtils')) {
        $formatted = AidUniteDateUtils::formatDate($date, AidUniteDateUtils::DATE_DISPLAY_SHORT);
        return $formatted !== '' ? $formatted : '—';
    }

    return $date;
}

/**
 * 時間帯表示（開始〜終了）
 *
 * @param string $start
 * @param string $end
 * @return string
 */
function aidunite_admin_list_format_time_range_display($start, $end = '') {
    $start = trim((string) $start);
    $end = trim((string) $end);
    if ($start === '' && $end === '') {
        return '—';
    }
    if ($start !== '' && $end !== '') {
        return $start . '〜' . $end;
    }

    return $start !== '' ? $start : $end;
}

/**
 * 性別ラベル（統一関数へ委譲）
 *
 * @param string $gender_raw male|female|both 等
 * @return string
 */
function aidunite_admin_list_format_gender_display($gender_raw) {
    $gender_raw = trim((string) $gender_raw);
    if ($gender_raw === '') {
        return '—';
    }
    if (function_exists('aidunite_get_gender_label')) {
        $label = aidunite_get_gender_label($gender_raw);
        if ($label !== '' && $label !== '不明') {
            return $label;
        }
    }
    $map = ['male' => '男子', 'female' => '女子', 'both' => '男女'];
    return $map[$gender_raw] ?? $gender_raw;
}

/**
 * スケジュールの会場メタ（登録時スナップショット優先）
 *
 * @param int $schedule_id
 * @return array{place:string, venue_name:string}
 */
function aidunite_admin_list_resolve_schedule_venue_meta($schedule_id) {
    $schedule_id = (int) $schedule_id;
    $place = '';
    $venue_name = '';

    if ($schedule_id <= 0 || get_post_type($schedule_id) !== 'schedule') {
        return ['place' => '', 'venue_name' => ''];
    }

    if (metadata_exists('post', $schedule_id, 'schedule_place_at_registration')) {
        $place = (string) get_post_meta($schedule_id, 'schedule_place_at_registration', true);
        $venue_name = (string) get_post_meta($schedule_id, 'venue_name_at_registration', true);
    } elseif (get_post_meta($schedule_id, 'pre_established_saved', true) === '1') {
        $place = (string) get_post_meta($schedule_id, 'pre_established_place', true);
        if ($place === '') {
            $place = (string) get_post_meta($schedule_id, 'pre_established_place_option', true);
        }
        $venue_name = (string) get_post_meta($schedule_id, 'venue_name', true);
    } else {
        if (function_exists('aidunite_schedule_get_display_bundle')) {
            $bundle = aidunite_schedule_get_display_bundle($schedule_id);
            $place = (string) ($bundle['place'] ?? '');
            $venue_name = (string) ($bundle['venue_name'] ?? '');
        } else {
            $place = function_exists('aidunite_schedule_read_place_raw')
                ? (string) aidunite_schedule_read_place_raw($schedule_id)
                : '';
            $venue_name = (string) get_post_meta($schedule_id, 'venue_name', true);
        }
    }

    return ['place' => $place, 'venue_name' => $venue_name];
}

/**
 * 会場条件のみ日本語表示（home/away/either 等。会場名は含めない）
 *
 * @param string $place schedule_place 相当
 * @return string
 */
function aidunite_admin_list_format_place_code_display($place) {
    $place = trim((string) $place);
    if ($place === '') {
        return '—';
    }

    $known_codes = ['home', 'away', 'either', 'both', 'neutral', 'tbd', 'undecided'];
    if (function_exists('aidunite_get_place_label') && in_array($place, $known_codes, true)) {
        $label = aidunite_get_place_label($place);
        if ($label !== '' && $label !== '不明') {
            return $label;
        }
    }

    if (function_exists('aidunite_jp_place')) {
        $jp = aidunite_jp_place($place);
        if ($jp !== '' && $jp !== '-') {
            return $jp;
        }
    }

    return $place;
}

/**
 * 会場名のみ表示
 *
 * @param string $venue_name
 * @return string
 */
function aidunite_admin_list_format_venue_name_display($venue_name) {
    $venue_name = trim((string) $venue_name);

    return $venue_name !== '' ? $venue_name : '—';
}

/**
 * 会場条件＋会場名（一覧用・列分離）
 *
 * @param int $schedule_id
 * @return array{place_label:string, venue_name:string}
 */
function aidunite_admin_list_get_schedule_venue_parts($schedule_id) {
    $meta = aidunite_admin_list_resolve_schedule_venue_meta($schedule_id);

    return [
        'place_label' => aidunite_admin_list_format_place_code_display($meta['place']),
        'venue_name' => aidunite_admin_list_format_venue_name_display($meta['venue_name']),
    ];
}

/**
 * @param string $type text|date|time|gender|venue|id|datetime
 * @param string $tag  td|th
 * @param string $extra_class
 * @return string
 */
function aidunite_admin_list_cell_open($type, $tag = 'td', $extra_class = '') {
    $type = sanitize_html_class((string) $type);
    $tag = $tag === 'th' ? 'th' : 'td';
    $classes = ['admin-cell', 'admin-cell--' . $type];
    if ($extra_class !== '') {
        $classes[] = $extra_class;
    }

    return '<' . $tag . ' class="' . esc_attr(implode(' ', $classes)) . '">';
}

/**
 * @param string $value
 * @return string
 */
function aidunite_admin_list_cell_value_html($value) {
    $value = (string) $value;
    if ($value === '' || $value === '—') {
        return '<span class="admin-cell__empty">—</span>';
    }

    return '<span class="admin-cell__value">' . esc_html($value) . '</span>';
}

/**
 * エスケープ済み HTML をセル内に出力（リンク等）
 *
 * @param string $html
 * @return string
 */
function aidunite_admin_list_cell_inner_html($html) {
    $plain = trim(wp_strip_all_tags((string) $html));
    if ($plain === '' || $plain === '—') {
        return '<span class="admin-cell__empty">—</span>';
    }

    return '<span class="admin-cell__value">' . $html . '</span>';
}

/**
 * 統一セル（テキストのみ）
 *
 * @param string $type
 * @param string $value
 * @param string $tag
 * @param string $extra_class
 * @return string
 */
function aidunite_admin_list_cell($type, $value, $tag = 'td', $extra_class = '') {
    return aidunite_admin_list_cell_open($type, $tag, $extra_class)
        . aidunite_admin_list_cell_value_html($value)
        . '</' . ($tag === 'th' ? 'th' : 'td') . '>';
}

/**
 * 統一セル（内部 HTML）
 *
 * @param string $type
 * @param string $inner_html 呼び出し側で esc_url / esc_html 済みであること
 * @param string $tag
 * @param string $extra_class
 * @return string
 */
function aidunite_admin_list_cell_html($type, $inner_html, $tag = 'td', $extra_class = '') {
    return aidunite_admin_list_cell_open($type, $tag, $extra_class)
        . aidunite_admin_list_cell_inner_html($inner_html)
        . '</' . ($tag === 'th' ? 'th' : 'td') . '>';
}

/**
 * echo 用ショートカット
 *
 * @param string $type
 * @param string $value
 * @param string $tag
 * @param string $extra_class
 */
function aidunite_admin_list_echo_cell($type, $value, $tag = 'td', $extra_class = '') {
    echo aidunite_admin_list_cell($type, $value, $tag, $extra_class);
}

/**
 * echo 用（内部 HTML）
 *
 * @param string $type
 * @param string $inner_html
 * @param string $tag
 * @param string $extra_class
 */
function aidunite_admin_list_echo_cell_html($type, $inner_html, $tag = 'td', $extra_class = '') {
    echo aidunite_admin_list_cell_html($type, $inner_html, $tag, $extra_class);
}
