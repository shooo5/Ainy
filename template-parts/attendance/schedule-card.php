<?php
/**
 * 出欠スケジュールカード（report / management 共通）
 *
 * @var array $args {
 *   @type array  $schedule  aidunite_attendance_build_schedule_view_model の結果
 *   @type string $mode      report|manage
 *   @type bool   $highlight
 * }
 */

if (!defined('ABSPATH')) {
    exit;
}

$schedule = is_array($args['schedule'] ?? null) ? $args['schedule'] : [];
$mode = (string) ($args['mode'] ?? 'report');
$highlight = !empty($args['highlight']);
$schedule_id = (int) ($schedule['schedule_id'] ?? $schedule['id'] ?? 0);
if ($schedule_id <= 0) {
    return;
}

$ui_key = (string) ($schedule['ui_key'] ?? 'unanswered');
$is_unanswered = ($ui_key === 'unanswered');
$summary = is_array($schedule['summary'] ?? null) ? $schedule['summary'] : [];
$members = is_array($schedule['members'] ?? null) ? $schedule['members'] : [];
$total = (int) ($summary['total'] ?? 0);
$attending = (int) ($summary['attending'] ?? 0);
$not_attending = (int) ($summary['not_attending'] ?? 0);
$maybe = (int) ($summary['maybe'] ?? 0);
$answered = (int) ($summary['answered'] ?? 0);

$status_labels = [
    'unanswered' => '未回答',
    'attending' => '参加',
    'not_attending' => '不参加',
    'maybe' => '未定',
];
$your_badge = $status_labels[$ui_key] ?? '未回答';

$card_class = 'attendance-card attendance-card--' . $mode;
if ($highlight) {
    $card_class .= ' attendance-card--highlight';
}

$deadline_ts = !empty($schedule['deadline_at'])
    ? strtotime((string) $schedule['deadline_at'])
    : 0;
if ($deadline_ts <= 0 && !empty($schedule['date']) && function_exists('aidunite_attendance_policy_get_response_deadline')) {
    $deadline_ts = strtotime(aidunite_attendance_policy_get_response_deadline((string) $schedule['date']));
}
$deadline_urgent = $deadline_ts > 0 && $deadline_ts <= strtotime('+1 day');

$att_pct = $total > 0 ? round(($attending / $total) * 100) : 0;
$abs_pct = $total > 0 ? round(($not_attending / $total) * 100) : 0;
$maybe_pct = $total > 0 ? round(($maybe / $total) * 100) : 0;
$pend_pct = max(0, 100 - $att_pct - $abs_pct - $maybe_pct);

$show_form = ($mode === 'report') && !empty($schedule['response_open']);
$status_field = 'attendance_status_' . $schedule_id;
$choice_present_id = 'att_choice_present_' . $schedule_id;
$choice_absent_id = 'att_choice_absent_' . $schedule_id;
$choice_maybe_id = 'att_choice_maybe_' . $schedule_id;
$response_closed_message = trim((string) ($schedule['response_closed_message'] ?? ''));
$display_members = array_slice($members, 0, 5);
$has_more_members = count($members) > 5;
$respondent_name = trim((string) ($schedule['respondent_name'] ?? ''));
$your_section_title = $respondent_name !== '' ? $respondent_name . 'の出欠' : 'あなたの出欠';
?>

<article class="<?php echo esc_attr($card_class); ?>" id="attendance-schedule-<?php echo esc_attr($schedule_id); ?>">
    <header class="attendance-card__event">
        <div class="attendance-card__event-main">
            <div class="attendance-card__event-icon" aria-hidden="true">
                <?php echo aidunite_render_theme_icon('calendar_month', ['width' => '28', 'height' => '28']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
            <div class="attendance-card__event-body">
                <h3 class="attendance-card__title"><?php echo esc_html($schedule['title'] ?? 'スケジュール'); ?></h3>
                <?php if (!empty($schedule['datetime_label'])) : ?>
                    <p class="attendance-card__datetime"><?php echo esc_html($schedule['datetime_label']); ?></p>
                <?php endif; ?>
                <?php if (!empty($schedule['place'])) : ?>
                    <p class="attendance-card__place">
                        <?php echo aidunite_render_theme_icon('stadium', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php echo esc_html($schedule['place']); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <?php if (!empty($schedule['deadline_label'])) : ?>
            <div class="attendance-card__deadline<?php echo $deadline_urgent ? ' attendance-card__deadline--urgent' : ''; ?>">
                <span class="attendance-card__deadline-label">回答期限</span>
                <span class="attendance-card__deadline-value"><?php echo esc_html($schedule['deadline_label']); ?>まで</span>
            </div>
        <?php endif; ?>
    </header>

    <div class="attendance-card__body">
        <?php if ($mode === 'report') : ?>
            <section class="attendance-card__your">
                <div class="attendance-card__your-head">
                    <h4 class="attendance-card__section-title"><?php echo esc_html($your_section_title); ?></h4>
                    <span class="attendance-card__status-badge attendance-card__status-badge--<?php echo esc_attr($ui_key); ?>">
                        <?php echo esc_html($your_badge); ?>
                    </span>
                </div>

                <?php if ($show_form) : ?>
                <form method="post" action="" class="attendance-form" data-schedule-id="<?php echo esc_attr($schedule_id); ?>" autocomplete="off">
                    <?php wp_nonce_field('submit_attendance', 'attendance_nonce'); ?>
                    <input type="hidden" name="schedule_id" value="<?php echo esc_attr($schedule_id); ?>">

                    <div class="attendance-choice-group attendance-choice-group--triple" role="radiogroup" aria-label="<?php echo esc_attr($your_section_title); ?>">
                        <div class="attendance-choice attendance-choice--present">
                            <input type="radio"
                                   class="attendance-choice__input"
                                   id="<?php echo esc_attr($choice_present_id); ?>"
                                   name="<?php echo esc_attr($status_field); ?>"
                                   value="attending"
                                   <?php checked($schedule['status'] ?? '', 'attending'); ?>
                                   <?php echo $is_unanswered ? 'required' : ''; ?>>
                            <label class="attendance-choice__tile" for="<?php echo esc_attr($choice_present_id); ?>">
                                <span class="attendance-choice__icon" aria-hidden="true"><?php echo aidunite_render_theme_icon('check_circle', ['width' => '28', 'height' => '28']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                <span class="attendance-choice__label">参加</span>
                            </label>
                        </div>
                        <div class="attendance-choice attendance-choice--absent">
                            <input type="radio"
                                   class="attendance-choice__input"
                                   id="<?php echo esc_attr($choice_absent_id); ?>"
                                   name="<?php echo esc_attr($status_field); ?>"
                                   value="not_attending"
                                   <?php checked($schedule['status'] ?? '', 'not_attending'); ?>
                                   <?php echo $is_unanswered ? 'required' : ''; ?>>
                            <label class="attendance-choice__tile" for="<?php echo esc_attr($choice_absent_id); ?>">
                                <span class="attendance-choice__icon" aria-hidden="true"><?php echo aidunite_render_theme_icon('close', ['width' => '28', 'height' => '28']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                <span class="attendance-choice__label">不参加</span>
                            </label>
                        </div>
                        <div class="attendance-choice attendance-choice--maybe">
                            <input type="radio"
                                   class="attendance-choice__input"
                                   id="<?php echo esc_attr($choice_maybe_id); ?>"
                                   name="<?php echo esc_attr($status_field); ?>"
                                   value="maybe"
                                   <?php checked($schedule['status'] ?? '', 'maybe'); ?>
                                   <?php echo $is_unanswered ? 'required' : ''; ?>>
                            <label class="attendance-choice__tile" for="<?php echo esc_attr($choice_maybe_id); ?>">
                                <span class="attendance-choice__icon" aria-hidden="true"><?php echo aidunite_render_theme_icon('question_mark', ['width' => '28', 'height' => '28']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                <span class="attendance-choice__label">未定</span>
                            </label>
                        </div>
                    </div>

                    <div class="attendance-card__comment">
                        <label for="comment_<?php echo esc_attr($schedule_id); ?>" class="attendance-card__comment-label">コメント（任意）</label>
                        <textarea id="comment_<?php echo esc_attr($schedule_id); ?>"
                                  name="comment_<?php echo esc_attr($schedule_id); ?>"
                                  class="attendance-card__comment-input"
                                  maxlength="200"
                                  rows="3"
                                  placeholder="例：午前中は予定があります"><?php echo esc_textarea($schedule['note'] ?? ''); ?></textarea>
                        <div class="attendance-card__comment-counter" data-max="200">
                            <span class="attendance-card__comment-count"><?php echo esc_html(mb_strlen((string) ($schedule['note'] ?? ''))); ?></span>/200
                        </div>
                    </div>

                    <div class="attendance-card__submit-wrap">
                        <button type="submit" name="submit_attendance" class="attendance-card__submit btn btn-primary">
                            <?php echo aidunite_render_theme_icon('send', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            送信する
                        </button>
                    </div>
                </form>
                <?php elseif ($response_closed_message !== '') : ?>
                    <p class="attendance-card__closed-notice"><?php echo esc_html($response_closed_message); ?></p>
                    <?php if (!empty($schedule['note'])) : ?>
                        <p class="attendance-card__member-note"><?php echo esc_html($schedule['note']); ?></p>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($total > 0) : ?>
            <section class="attendance-card__summary">
                <div class="attendance-card__summary-head">
                    <h4 class="attendance-card__section-title">回答状況（<?php echo esc_html((string) $answered); ?>/<?php echo esc_html((string) $total); ?>人）</h4>
                </div>

                <div class="attendance-card__bar" role="img" aria-label="参加<?php echo esc_attr((string) $att_pct); ?>%、不参加<?php echo esc_attr((string) $abs_pct); ?>%、未定<?php echo esc_attr((string) $maybe_pct); ?>%">
                    <?php if ($att_pct > 0) : ?><span class="attendance-card__bar-seg attendance-card__bar-seg--present" style="width:<?php echo esc_attr((string) $att_pct); ?>%"></span><?php endif; ?>
                    <?php if ($abs_pct > 0) : ?><span class="attendance-card__bar-seg attendance-card__bar-seg--absent" style="width:<?php echo esc_attr((string) $abs_pct); ?>%"></span><?php endif; ?>
                    <?php if ($maybe_pct > 0) : ?><span class="attendance-card__bar-seg attendance-card__bar-seg--maybe" style="width:<?php echo esc_attr((string) $maybe_pct); ?>%"></span><?php endif; ?>
                    <?php if ($pend_pct > 0) : ?><span class="attendance-card__bar-seg attendance-card__bar-seg--pending" style="width:<?php echo esc_attr((string) $pend_pct); ?>%"></span><?php endif; ?>
                </div>

                <ul class="attendance-card__stats">
                    <li><span class="attendance-card__stats-dot attendance-card__stats-dot--present"></span>参加 <?php echo esc_html((string) $attending); ?>人 (<?php echo esc_html((string) $att_pct); ?>%)</li>
                    <li><span class="attendance-card__stats-dot attendance-card__stats-dot--absent"></span>不参加 <?php echo esc_html((string) $not_attending); ?>人 (<?php echo esc_html((string) $abs_pct); ?>%)</li>
                    <li><span class="attendance-card__stats-dot attendance-card__stats-dot--maybe"></span>未定 <?php echo esc_html((string) $maybe); ?>人 (<?php echo esc_html((string) $maybe_pct); ?>%)</li>
                </ul>

                <?php if ($members !== [] && $mode !== 'manage') : ?>
                    <ul class="attendance-card__members">
                        <?php foreach ($display_members as $member) : ?>
                            <?php
                            $m_key = (string) ($member['ui_key'] ?? 'unanswered');
                            $m_label = $status_labels[$m_key] ?? '未回答';
                            ?>
                            <li class="attendance-card__member">
                                <div class="attendance-card__member-avatar" aria-hidden="true">
                                    <?php aidunite_attendance_render_member_avatar($member); ?>
                                </div>
                                <div class="attendance-card__member-body">
                                    <div class="attendance-card__member-head">
                                        <span class="attendance-card__member-name">
                                            <?php echo esc_html($member['display_name'] ?? ''); ?>
                                            <?php if (!empty($member['is_self'])) : ?><span class="attendance-card__member-you">（あなた）</span><?php endif; ?>
                                        </span>
                                        <span class="attendance-card__member-badge attendance-card__member-badge--<?php echo esc_attr($m_key); ?>">
                                            <?php echo esc_html($m_label); ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($member['note'])) : ?>
                                        <p class="attendance-card__member-note"><?php echo esc_html($member['note']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($member['response_date'])) : ?>
                                        <time class="attendance-card__member-time"><?php echo esc_html($member['response_date']); ?></time>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ($has_more_members) : ?>
                        <p class="attendance-card__members-more">他 <?php echo esc_html((string) (count($members) - 5)); ?> 人が回答済み</p>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($mode === 'manage' && $members !== []) : ?>
            <section class="attendance-card__members-full">
                <h4 class="attendance-card__section-title">メンバー別出欠状況</h4>
                <ul class="attendance-card__members">
                    <?php foreach ($members as $member) : ?>
                        <?php
                        $m_key = (string) ($member['ui_key'] ?? 'unanswered');
                        $m_label = $status_labels[$m_key] ?? '未回答';
                        ?>
                        <li class="attendance-card__member">
                            <div class="attendance-card__member-avatar" aria-hidden="true">
                                <?php aidunite_attendance_render_member_avatar($member); ?>
                            </div>
                            <div class="attendance-card__member-body">
                                <div class="attendance-card__member-head">
                                    <span class="attendance-card__member-name"><?php echo esc_html($member['display_name'] ?? ''); ?></span>
                                    <span class="attendance-card__member-badge attendance-card__member-badge--<?php echo esc_attr($m_key); ?>">
                                        <?php echo esc_html($m_label); ?>
                                    </span>
                                </div>
                                <?php if (!empty($member['note'])) : ?>
                                    <p class="attendance-card__member-note"><?php echo esc_html($member['note']); ?></p>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>
    </div>
</article>
