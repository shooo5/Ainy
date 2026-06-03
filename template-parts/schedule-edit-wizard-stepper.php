<?php
/**
 * スケジュール登録ウィザード：ページヒーロー内ステップ表示
 *
 * ヒーロー：STEP番号 + タイトルのみ（説明文は本文エリアへ）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

$defaults = [
    'steps' => function_exists('aidunite_get_schedule_edit_wizard_step_definitions')
        ? aidunite_get_schedule_edit_wizard_step_definitions()
        : [],
];
$args = wp_parse_args($args ?? [], $defaults);
$steps = is_array($args['steps']) ? $args['steps'] : $defaults['steps'];
?>

<nav class="schedule-edit-wizard-stepper" id="schedule-edit-wizard-stepper" aria-label="登録ステップ">
    <ol class="schedule-edit-wizard-stepper__list">
        <?php foreach ($steps as $index => $step) : ?>
            <?php
            if (!is_array($step)) {
                continue;
            }
            $step_num = $index + 1;
            $title = isset($step['title']) ? (string) $step['title'] : '';
            ?>
            <li class="step-item schedule-edit-wizard-stepper__item<?php echo $step_num === 1 ? ' active' : ''; ?>" data-step="<?php echo (int) $step_num; ?>" aria-label="<?php echo esc_attr($title); ?>">
                <span class="schedule-edit-wizard-stepper__num" aria-hidden="true"><?php echo (int) $step_num; ?></span>
                <?php if ($title !== '') : ?>
                    <span class="schedule-edit-wizard-stepper__label"><?php echo esc_html($title); ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
