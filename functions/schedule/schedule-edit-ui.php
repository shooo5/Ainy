<?php
/**
 * スケジュール登録 UI リニューアル用ヘルパー
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * スケジュール登録ウィザード：STEP定義（ステッパー・見出し共通）
 *
 * @return array<int, array{title: string, desc: string, hint: string}>
 */
function aidunite_get_schedule_edit_wizard_step_definitions() {
    return [
        [
            'title' => '登録する目的を選びましょう。',
            'desc'  => '予定の目的によって、その後の入力内容が変わります。',
            'hint'  => '迷ったときは、「試合を募集する」から始めてみましょう。',
        ],
        [
            'title' => 'いつ試合をしたいですか？',
            'desc'  => '日付と時間帯を選ぶだけです。',
            'hint'  => 'あとから変更することもできます。',
        ],
        [
            'title' => '試合条件を決めましょう。',
            'desc'  => '試合の条件を設定します。',
            'hint'  => '迷ったら「練習試合」「どちらでも可」で進めて大丈夫です。',
        ],
        [
            'title' => '入力内容の確認。',
            'desc'  => '以下の内容で試合募集を公開します。',
            'hint'  => '募集内容はあとから変更することもできます。<br>問題なければ公開しましょう。',
        ],
    ];
}

/**
 * ページヒーロー用ウィザードステッパー HTML
 */
function aidunite_render_schedule_edit_wizard_stepper_html() {
    ob_start();
    get_template_part('template-parts/schedule-edit', 'wizard-stepper', [
        'steps' => aidunite_get_schedule_edit_wizard_step_definitions(),
    ]);
    return (string) ob_get_clean();
}

/**
 * ウィザード見出しブロック（STEP定義から出力・本文エリア用：タイトルなし・説明文のみ）
 *
 * @param int $step_index 1〜4
 */
function aidunite_schedule_edit_wizard_heading_for_step($step_index) {
    $steps = aidunite_get_schedule_edit_wizard_step_definitions();
    $index = (int) $step_index - 1;
    if (!isset($steps[$index]) || !is_array($steps[$index])) {
        return;
    }
    $step = $steps[$index];
    aidunite_schedule_edit_wizard_heading([
        'title' => '',
        'desc'  => isset($step['desc']) ? (string) $step['desc'] : '',
        'hint'  => isset($step['hint']) ? (string) $step['hint'] : '',
    ]);
}

/**
 * ウィザード見出しブロック
 *
 * @param array<string, string> $args title, desc, hint
 */
function aidunite_schedule_edit_wizard_heading(array $args) {
    $title = isset($args['title']) ? (string) $args['title'] : '';
    $desc  = isset($args['desc']) ? (string) $args['desc'] : '';
    $hint  = isset($args['hint']) ? (string) $args['hint'] : '';
    if ($title === '' && $desc === '' && $hint === '') {
        return;
    }
    ?>
    <div class="schedule-wizard-heading<?php echo $title === '' ? ' schedule-wizard-heading--desc-only' : ''; ?>">
        <?php if ($title !== '') : ?>
            <h3 class="schedule-wizard-heading__title"><?php echo esc_html($title); ?></h3>
        <?php endif; ?>
        <?php if ($desc !== '') : ?>
            <p class="schedule-wizard-heading__desc"><?php echo esc_html($desc); ?></p>
        <?php endif; ?>
        <?php if ($hint !== '') : ?>
            <p class="schedule-wizard-heading__hint"><?php echo esc_html($hint); ?></p>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * STEP2：開始／終了の1行スロット（icon・ラベル・時▼・：・分▼）
 *
 * @param string $prefix start|end
 * @param int    $default_hour
 * @param string $default_minute
 * @param string $label_id
 * @param string $label_text
 */
function aidunite_schedule_edit_render_time_picker_field($prefix, $default_hour, $default_minute, $label_id, $label_text) {
    $prefix = $prefix === 'end' ? 'end' : 'start';
    $hour   = max(0, min(23, (int) $default_hour));
    $minute = ((int) $default_minute) >= 30 ? '30' : '00';
    $hour_id   = $prefix . '_hour';
    $minute_id = $prefix . '_minute';
    ?>
    <div class="schedule-time-slot schedule-time-slot--<?php echo esc_attr($prefix); ?> time-input-group" role="group" aria-labelledby="<?php echo esc_attr($label_id); ?>">
        <span class="schedule-time-slot__icon" aria-hidden="true">
            <?php echo aidunite_render_theme_icon('schedule', ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </span>
        <span class="schedule-time-slot__label time-label" id="<?php echo esc_attr($label_id); ?>"><?php echo esc_html($label_text); ?></span>
        <div class="schedule-time-slot__fields time-inputs">
            <select name="<?php echo esc_attr($hour_id); ?>" id="<?php echo esc_attr($hour_id); ?>" class="time-select time-select--hour" required aria-labelledby="<?php echo esc_attr($label_id); ?>" aria-label="<?php echo esc_attr($label_text); ?>（時）">
                <?php for ($i = 0; $i < 24; $i++) : ?>
                    <option value="<?php echo (int) $i; ?>" <?php selected($i, $hour); ?>>
                        <?php echo esc_html(str_pad((string) $i, 2, '0', STR_PAD_LEFT)); ?>
                    </option>
                <?php endfor; ?>
            </select>
            <span class="time-separator" aria-hidden="true">:</span>
            <select name="<?php echo esc_attr($minute_id); ?>" id="<?php echo esc_attr($minute_id); ?>" class="time-select time-select--minute" required aria-labelledby="<?php echo esc_attr($label_id); ?>" aria-label="<?php echo esc_attr($label_text); ?>（分）">
                <option value="00" <?php selected('00', $minute); ?>>00</option>
                <option value="30" <?php selected('30', $minute); ?>>30</option>
            </select>
        </div>
    </div>
    <?php
}

/**
 * 薄青・薄黄の説明バー
 *
 * @param string $text
 * @param string $variant blue|yellow|green
 * @param string $icon_svg
 */

function aidunite_schedule_edit_info_bar($text, $variant = 'blue', $icon_svg = 'info') {
    $variant = in_array($variant, ['blue', 'yellow', 'green'], true) ? $variant : 'blue';
    ?>
    <div class="schedule-wizard-info-bar schedule-wizard-info-bar--<?php echo esc_attr($variant); ?>" role="note">
        <span class="schedule-wizard-info-bar__icon" aria-hidden="true">
            <?php echo aidunite_render_theme_icon($icon_svg, ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </span>
        <p class="schedule-wizard-info-bar__text"><?php echo esc_html($text); ?></p>
    </div>
    <?php
}
