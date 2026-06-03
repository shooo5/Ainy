<?php
/**
 * マイページ v2 UI ヘルパー（パターンC：タイトルバー型セクション）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * マイページ v2 アイコン（assets/images/icons/chat/）
 */
function aidunite_get_mypage_icon_base_url() {
    return get_stylesheet_directory_uri() . '/assets/images/icons/chat/';
}

/**
 * セクションタイトルバー用アイコン名
 *
 * @param string $section todo|today-schedules|event|upcoming
 */
function aidunite_get_mypage_section_icon_name($section) {
    $map = [
        'todo'            => 'check_circle',
        'today-schedules' => 'today',
        'event'           => 'trophy',
        'upcoming'        => 'schedule',
    ];

    return isset($map[$section]) ? $map[$section] : 'check_circle';
}

/**
 * タブ用アイコン名
 *
 * @param string $section todo|today-schedules|event|upcoming
 */
function aidunite_get_mypage_tab_icon_name($section) {
    return aidunite_get_mypage_section_icon_name($section);
}

/**
 * クイックアクション用アイコン名
 *
 * @param string $key schedule|chat|match
 */
function aidunite_get_mypage_quick_icon_name($key) {
    $map = [
        'schedule' => 'calendar_month',
        'chat'     => 'chat',
        'match'    => 'trophy',
    ];

    return isset($map[$key]) ? $map[$key] : '';
}

/**
 * @deprecated 3.0 use aidunite_get_mypage_section_icon_name()
 */
function aidunite_get_mypage_section_icon_file($section) {
    return aidunite_get_mypage_section_icon_name($section) . '.svg';
}

/**
 * @deprecated 3.0 use aidunite_get_mypage_tab_icon_name()
 */
function aidunite_get_mypage_tab_icon_file($section) {
    return aidunite_get_mypage_tab_icon_name($section) . '.svg';
}

/**
 * @deprecated 3.0 use aidunite_get_mypage_quick_icon_name()
 */
function aidunite_get_mypage_quick_icon_file($key) {
    $name = aidunite_get_mypage_quick_icon_name($key);

    return $name !== '' ? $name . '.svg' : '';
}

/**
 * マイページ v2 タブ定義
 *
 * @return array<int, array{key:string, scroll:string, label:string}>
 */
function aidunite_get_mypage_tabs_config() {
    return [
        [
            'key'    => 'today-schedules',
            'scroll' => '#mypage-section-today-schedules',
            'label'  => '本日の予定',
        ],
        [
            'key'    => 'todo',
            'scroll' => '#mypage-section-todo',
            'label'  => 'やること',
        ],
        [
            'key'    => 'event',
            'scroll' => '#mypage-section-event',
            'label'  => '大会',
        ],
        [
            'key'    => 'upcoming',
            'scroll' => '#mypage-section-upcoming',
            'label'  => '直近',
        ],
    ];
}

/**
 * マイページ v2 タブナビを出力
 *
 * @param string $icon_base 未使用（後方互換）
 */
function aidunite_render_mypage_tabs($icon_base = '') {
    $tabs = aidunite_get_mypage_tabs_config();
    if ($tabs === []) {
        return;
    }
    ?>
    <nav class="mypage-v2-tabs" role="tablist" aria-label="マイページタブ">
        <?php foreach ($tabs as $tab_index => $tab) :
            $tab_key    = (string) ($tab['key'] ?? '');
            $tab_label  = (string) ($tab['label'] ?? '');
            $tab_scroll = (string) ($tab['scroll'] ?? '');
            $tab_icon   = aidunite_get_mypage_tab_icon_name($tab_key);
            $is_active  = $tab_index === 0;
            ?>
        <button type="button"
            class="mypage-v2-tab<?php echo $is_active ? ' active' : ''; ?>"
            role="tab"
            aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
            data-tab="<?php echo esc_attr($tab_key); ?>"
            data-scroll="<?php echo esc_attr($tab_scroll); ?>">
            <span class="mypage-v2-tab__icon" aria-hidden="true"><?php echo aidunite_get_chat_icon_svg($tab_icon, ['width' => '18', 'height' => '18']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
            <span><?php echo esc_html($tab_label); ?></span>
        </button>
        <?php endforeach; ?>
    </nav>
    <?php
}

/**
 * パターンC：アイコン付きタイトルバーを出力
 *
 * @param array<string, mixed> $args {
 *   @type string $section   セクションキー（todo|today-schedules|event|upcoming）
 *   @type string $title    見出し
 *   @type string $subtitle 補足（任意）
 *   @type string $heading_id h3 id（任意）
 *   @type string $link_url 「すべて見る」URL（空なら非表示）
 *   @type string $link_label リンク文言（既定: すべて見る）
 *   @type bool   $priority  やること等の強調バー
 * }
 */
function aidunite_render_mypage_section_bar(array $args = []) {
    $args = wp_parse_args($args, [
        'section'     => '',
        'title'       => '',
        'subtitle'    => '',
        'heading_id'  => '',
        'link_url'    => '',
        'link_label'  => 'すべて見る',
        'priority'    => false,
    ]);

    $section = (string) $args['section'];
    $icon_name = aidunite_get_mypage_section_icon_name($section);

    $bar_class = 'mypage-v2-section-bar';
    if ($section !== '') {
        $bar_class .= ' mypage-v2-section-bar--' . sanitize_html_class($section);
    }
    if (!empty($args['priority'])) {
        $bar_class .= ' mypage-v2-section-bar--priority';
    }

    $heading_id = (string) $args['heading_id'];
    $heading_attr = $heading_id !== '' ? ' id="' . esc_attr($heading_id) . '"' : '';
    ?>
    <div class="<?php echo esc_attr($bar_class); ?>">
        <div class="mypage-v2-section-bar__main">
            <span class="mypage-v2-section-bar__icon" aria-hidden="true">
                <?php echo aidunite_get_chat_icon_svg($icon_name, ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </span>
            <h3 class="mypage-v2-section-bar__title"<?php echo $heading_attr; ?>>
                <?php echo esc_html((string) $args['title']); ?>
                <?php if ((string) $args['subtitle'] !== '') : ?>
                    <span class="mypage-v2-section-bar__subtitle"><?php echo esc_html((string) $args['subtitle']); ?></span>
                <?php endif; ?>
            </h3>
        </div>
        <?php if ((string) $args['link_url'] !== '') : ?>
            <a href="<?php echo esc_url((string) $args['link_url']); ?>" class="mypage-v2-section-bar__link">
                <?php echo esc_html((string) $args['link_label']); ?>
            </a>
        <?php endif; ?>
    </div>
    <?php
}
