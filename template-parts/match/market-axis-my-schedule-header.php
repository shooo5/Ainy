<?php
/**
 * 申請状況タブ：自分の募集スケジュールヘッダー（タブレット・スマホ 2行 / PC 1行 + 右上ケバブ）
 *
 * @var array $args
 */

if (!defined('ABSPATH')) {
    exit;
}

$args = isset($args) && is_array($args) ? $args : [];
$date_short = (string) ($args['date_short'] ?? '—');
$time_short = (string) ($args['time_short'] ?? '—');
$place_disp = (string) ($args['place_disp'] ?? '—');
$slots_disp = (string) ($args['slots_disp'] ?? '');
$is_full = !empty($args['is_full']);
$is_schedule_away = !empty($args['is_schedule_away']);

if ($is_full && !$is_schedule_away) {
    $slots_text = '満了';
    $slots_class = 'market-axis-my-schedule-header__slots-text market-axis-my-schedule-header__slots-text--full';
} elseif ($slots_disp !== '') {
    $slots_text = $slots_disp;
    $slots_class = 'market-axis-my-schedule-header__slots-text';
} else {
    $slots_text = '—';
    $slots_class = 'market-axis-my-schedule-header__slots-text market-axis-my-schedule-header__slots-text--muted';
}
?>
<div class="market-axis-my-schedule-header market-axis-my-schedule-header--layout-responsive">
    <div class="market-axis-my-schedule-header__content market-axis-my-schedule-header__content--stacked">
        <div class="market-axis-my-schedule-header__row market-axis-my-schedule-header__row--primary">
            <span class="market-axis-my-schedule-header__date"><?php echo esc_html($date_short); ?></span>
            <span class="market-axis-my-schedule-header__sep" aria-hidden="true">｜</span>
            <span class="market-axis-my-schedule-header__time"><?php echo esc_html($time_short); ?></span>
        </div>
        <div class="market-axis-my-schedule-header__row market-axis-my-schedule-header__row--secondary">
            <span class="market-axis-my-schedule-header__venue"><?php echo esc_html($place_disp); ?></span>
            <?php if (!$is_schedule_away) : ?>
                <span class="market-axis-my-schedule-header__sep" aria-hidden="true">｜</span>
                <span class="<?php echo esc_attr($slots_class); ?>"><?php echo esc_html($slots_text); ?></span>
            <?php endif; ?>
        </div>
    </div>
    <?php
    get_template_part('template-parts/match/market-axis-my-schedule-kebab', null, $args);
    ?>
</div>

