<?php
/**
 * 募集中の試合：1行（カード・グリッドレイアウト）
 *
 * @var array $args
 */

if (!defined('ABSPATH')) {
    exit;
}

$args = isset($args) && is_array($args) ? $args : [];
$row = isset($args['row']) && is_array($args['row']) ? $args['row'] : [];
$viewer_team_id = (int) ($args['viewer_team_id'] ?? 0);

$other = $row['other'] ?? null;
if (!$other instanceof WP_Post) {
    return;
}

$state = (string) ($row['state'] ?? '');
$best_my_id = (int) ($row['best_my_schedule_id'] ?? 0);
$other_id = (int) $other->ID;

$other_disp = function_exists('aidunite_schedule_get_market_list_display')
    ? aidunite_schedule_get_market_list_display($other_id)
    : [];
$other_full = function_exists('aidunite_schedule_get_display_bundle')
    ? aidunite_schedule_get_display_bundle($other_id)
    : [];

$other_team_id = (int) ($other_disp['team_id'] ?? (function_exists('aidunite_schedule_read_team_id')
    ? aidunite_schedule_read_team_id($other_id)
    : 0));
$other_team_bundle = $other_team_id && function_exists('aidunite_team_get_display_bundle')
    ? aidunite_team_get_display_bundle($other_team_id)
    : [];

$other_team_name = $other_team_id && function_exists('aidunite_get_team_name')
    ? aidunite_get_team_name($other_team_id)
    : ($other_team_id ? get_the_title($other_team_id) : '');
$other_team_name = $other_team_name ?: 'チーム名未設定';

$other_date = (string) ($other_disp['date'] ?? '');
$other_start = (string) ($other_disp['start_time'] ?? '');
$other_end = (string) ($other_disp['end_time'] ?? '');
$other_place = (string) ($other_disp['place'] ?? '');
$place_disp = function_exists('aidunite_jp_place') ? aidunite_jp_place($other_place) : ($other_place ?: '—');

$weekday_labels = ['日', '月', '火', '水', '木', '金', '土'];
$date_disp = $other_date
    ? date('n/j', strtotime($other_date)) . ' (' . $weekday_labels[(int) date('w', strtotime($other_date))] . ')'
    : '—';
$time_disp = trim(($other_start ?: '') . '–' . ($other_end ?: ''));
$time_disp = $time_disp !== '–' ? $time_disp : '—';

$no_preference_apply_link = home_url('/match-detail/') . '?schedule_id=' . $other_id . '#apply';
$status_data = $viewer_team_id ? aidunite_get_application_status($viewer_team_id, $best_my_id ?: 0, $other_team_id, $other_id) : [
    'text' => function_exists('aidunite_get_match_status_label') ? aidunite_get_match_status_label('not_applied', 'text') : '未申請',
    'class' => '',
    'code' => 'not_applied',
    'request_id' => 0,
    'is_requester' => false,
];
$detail_link = home_url('/match-detail/') . '?my_schedule_id=' . $best_my_id . '&schedule_id=' . $other_id;
if (!empty($status_data['request_id'])) {
    $detail_link .= '&match_request_id=' . (int) $status_data['request_id'];
}
$status_chat_link = !empty($status_data['request_id'])
    ? home_url('/chat?match_id=' . (int) $status_data['request_id'])
    : '';
$status_code = (string) ($status_data['code'] ?? '');
$is_not_applied = ($status_code === 'not_applied' || ($status_data['text'] ?? '') === '未申請');
$is_terminal = in_array($status_code, ['rejected', 'canceled', 'canceled_opponent', 'reconfirm_required', 'proposal_pending_accept', 'slots_full', 'gender_slots_full', 'gender_conflict', 'invalid_closed', 'venue_conflict', 'proposal_possible'], true)
    || in_array(($status_data['text'] ?? ''), ['拒否済み', 'キャンセル済み', '相手キャンセル'], true);
$outcome_blocks_reapply = in_array($status_code, ['slots_full', 'gender_slots_full'], true);
$is_established = ($status_code === 'established' || ($status_data['text'] ?? '') === '試合確定');
$market_reapply_hide = false;
if (!empty($best_my_id) && !empty($status_data['request_id']) && function_exists('aidunite_match_detail_reapply_cta')) {
    $mcta = aidunite_match_detail_reapply_cta((int) $other_id, (int) $status_data['request_id'], (int) $best_my_id);
    if (($mcta['variant'] ?? '') === 'ineligible') {
        $market_reapply_hide = true;
    }
}

$tier_labels = function_exists('aidunite_match_board_tier_labels') ? aidunite_match_board_tier_labels() : [];
if (isset($tier_labels[$state])) {
    $badge = (string) $tier_labels[$state]['label'];
    $state_class = (string) $tier_labels[$state]['class'];
} elseif ($state === 'green') {
    $badge = '成立可能';
    $state_class = 'green';
} elseif ($state === 'yellow') {
    $badge = '条件調整';
    $state_class = 'yellow';
} else {
    $badge = '希望未登録';
    $state_class = 'no_preference';
}

$team_link = $state_class === 'no_preference' ? $no_preference_apply_link : $detail_link;

$common_badges = [];
if ($viewer_team_id > 0 && $other_team_id > 0 && function_exists('aidunite_match_detail_common_points')) {
    $viewer_bundle = function_exists('aidunite_team_get_display_bundle') ? aidunite_team_get_display_bundle($viewer_team_id) : [];
    $my_sched = $best_my_id > 0 && function_exists('aidunite_schedule_get_display_bundle')
        ? aidunite_schedule_get_display_bundle($best_my_id)
        : [];
    $common_badges = aidunite_match_detail_common_points(
        $viewer_team_id,
        $other_team_id,
        is_array($viewer_bundle) ? $viewer_bundle : [],
        is_array($other_team_bundle) ? $other_team_bundle : [],
        [
            'my_schedule_data' => is_array($my_sched) ? $my_sched : [],
            'other_schedule_data' => is_array($other_full) ? $other_full : [],
        ]
    );
}
if ($best_my_id > 0 && function_exists('aidunite_market_row_state_from_schedules')) {
    $score_pack = aidunite_market_row_state_from_schedules($other_id, [$best_my_id], $viewer_team_id);
    $scores = is_array($score_pack['scores'] ?? null) ? $score_pack['scores'] : [];
    $t_score = (int) ($scores['T'] ?? 0);
    $v_score = (int) ($scores['V'] ?? 0);
    $a_score = (int) ($scores['A'] ?? 0);
    if ($t_score >= 80 && !in_array('時間帯一致', $common_badges, true)) {
        $common_badges[] = '時間帯一致';
    } elseif ($t_score >= 55 && !in_array('活動時間帯が近い', $common_badges, true)) {
        $common_badges[] = '活動時間帯が近い';
    }
    if ($v_score >= 30 && !in_array('会場条件OK', $common_badges, true)) {
        $common_badges[] = '会場条件OK';
    }
    if ($a_score >= 50 && !in_array('同じ活動エリア', $common_badges, true)) {
        $common_badges[] = '同じ活動エリア';
    } elseif ($a_score >= 25 && !in_array('活動エリア近い', $common_badges, true)) {
        $common_badges[] = '活動エリア近い';
    }
}
$common_badges = array_slice(array_values(array_unique($common_badges)), 0, 4);

$logo_url = trim((string) ($other_team_bundle['team_logo'] ?? ''));
$logo_has_url = $logo_url !== '' && function_exists('aidunite_team_logo_is_displayable')
    && aidunite_team_logo_is_displayable($logo_url);
$logo_style = '';
if ($logo_has_url && function_exists('aidunite_get_team_logo_crop')) {
    $logo_crop = aidunite_get_team_logo_crop($other_team_id);
    $logo_style = sprintf(
        'transform: translate(%d%%, %d%%) scale(%s);',
        (int) ($logo_crop['x'] ?? 0),
        (int) ($logo_crop['y'] ?? 0),
        max(0.5, (int) ($logo_crop['zoom'] ?? 100) / 100)
    );
}
$team_initial = function_exists('aidunite_match_detail_team_avatar_initial')
    ? aidunite_match_detail_team_avatar_initial($other_team_name)
    : (function_exists('mb_substr') ? mb_substr($other_team_name, 0, 1) : substr($other_team_name, 0, 1));
?>
<li class="market-axis-row market-axis-recruit-row" data-schedule-id="<?php echo (int) $other_id; ?>" data-state="<?php echo esc_attr($state_class); ?>"<?php
if (!empty($row['board_card_key'])) :
    ?> data-board-card-key="<?php echo esc_attr((string) $row['board_card_key']); ?>"<?php
    if (!empty($row['board_updated_at'])) :
        ?> data-board-updated-at="<?php echo esc_attr((string) $row['board_updated_at']); ?>"<?php
    endif;
    if (!empty($row['board_is_new'])) :
        ?> data-board-is-new="1"<?php
    endif;
endif;
?>>
    <div class="market-axis-recruit-row__datetime">
        <div class="market-axis-recruit-row__datetime-head">
            <span class="market-axis-recruit-row__date"><?php echo esc_html($date_disp); ?></span>
            <span class="market-axis-recruit-row__time"><?php echo esc_html($time_disp); ?></span>
            <span class="market-axis-badge market-axis-badge--<?php echo esc_attr($state_class); ?> market-axis-recruit-row__tier-badge"><?php
            if (function_exists('aidunite_match_board_tier_badge_icon_html')) {
                echo aidunite_match_board_tier_badge_icon_html($state_class); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            } elseif ($state_class === 'best' && function_exists('aidunite_render_theme_icon')) {
                echo aidunite_render_theme_icon('star', ['width' => '14', 'height' => '14'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
            ?><?php echo esc_html($badge); ?></span>
            <?php if (!empty($row['board_is_new']) && function_exists('aidunite_match_board_render_new_badge')) : ?>
                <div class="market-axis-row-new-badge" aria-hidden="false"><?php echo aidunite_match_board_render_new_badge(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="market-axis-recruit-row__divider market-axis-recruit-row__divider--datetime" aria-hidden="true"></div>

    <div class="market-axis-recruit-row__logo" aria-hidden="true">
        <a href="<?php echo esc_url($team_link); ?>" class="market-axis-recruit-row__logo-link" tabindex="-1">
            <?php if ($logo_has_url) : ?>
                <img
                    class="market-axis-recruit-row__logo-img"
                    src="<?php echo esc_url($logo_url); ?>"
                    alt=""
                    width="40"
                    height="40"
                    decoding="async"
                    <?php echo $logo_style !== '' ? 'style="' . esc_attr($logo_style) . '"' : ''; ?>
                >
            <?php else : ?>
                <span class="market-axis-recruit-row__logo-placeholder"><?php echo esc_html($team_initial); ?></span>
            <?php endif; ?>
        </a>
    </div>

    <div class="market-axis-recruit-row__team">
        <a href="<?php echo esc_url($team_link); ?>" class="market-axis-recruit-row__team-name" title="<?php echo esc_attr($other_team_name); ?>"><?php echo esc_html($other_team_name); ?></a>
        <p class="market-axis-recruit-row__venue">
            <span class="market-axis-recruit-row__venue-label">会場希望</span>
            <span class="market-axis-recruit-row__venue-sep" aria-hidden="true">｜</span>
            <span class="market-axis-recruit-row__venue-value"><?php echo esc_html($place_disp); ?></span>
        </p>
    </div>

    <div class="market-axis-recruit-row__divider market-axis-recruit-row__divider--team-common" aria-hidden="true"></div>

    <div class="market-axis-recruit-row__common">
        <?php if ($state_class === 'no_preference') : ?>
            <p class="market-axis-recruit-row__common-note">この日はスケジュールが空いてます。</p>
        <?php else : ?>
            <p class="market-axis-recruit-row__common-label">共通点</p>
            <?php if (!empty($common_badges)) : ?>
                <ul class="market-axis-recruit-row__common-badges" aria-label="共通点">
                    <?php foreach ($common_badges as $point) : ?>
                        <li><span class="market-axis-recruit-row__common-badge"><?php echo esc_html($point); ?></span></li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p class="market-axis-recruit-row__common-note">—</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="market-axis-recruit-row__actions">
        <div class="market-axis-recruit-row__cta">
            <?php if ($state_class === 'no_preference') : ?>
                <a href="<?php echo esc_url($no_preference_apply_link); ?>" class="match-btn match-btn--primary market-axis-recruit-row__apply-btn" data-analytics-target="match_apply" data-analytics-event="click">申請する</a>
            <?php elseif (in_array($state_class, ['best', 'yellow', 'green'], true)) : ?>
                <?php if ($is_not_applied && $best_my_id) : ?>
                    <a href="<?php echo esc_url($detail_link . '#apply'); ?>" class="match-btn match-btn--primary market-axis-recruit-row__apply-btn">申請する</a>
                <?php elseif ($is_terminal) : ?>
                    <?php if ($outcome_blocks_reapply && !empty($status_data['request_id'])) : ?>
                        <a href="<?php echo esc_url($detail_link); ?>" class="match-btn match-btn--secondary">詳細</a>
                        <?php if (!empty($status_data['is_requester'])) : ?>
                            <button type="button" class="match-btn match-btn--secondary au-open-cancel-modal market-axis-cancel-apply" data-request-id="<?php echo (int) $status_data['request_id']; ?>" data-team-name="<?php echo esc_attr($other_team_name); ?>" data-cancel-type="apply">申請をキャンセル</button>
                        <?php endif; ?>
                    <?php elseif (!$market_reapply_hide) : ?>
                        <a href="<?php echo esc_url($detail_link . '#apply'); ?>" class="match-btn match-btn--primary market-axis-recruit-row__apply-btn">申請する</a>
                    <?php else : ?>
                        <a href="<?php echo esc_url($detail_link); ?>" class="match-btn match-btn--secondary">詳細</a>
                    <?php endif; ?>
                <?php elseif ($is_established) : ?>
                    <span class="match-candidate-status-badge <?php echo esc_attr($status_data['class'] ?: 'badge-status'); ?>"><?php echo esc_html($status_data['text']); ?></span>
                    <?php if ($status_chat_link !== '') : ?>
                        <a href="<?php echo esc_url($status_chat_link); ?>" class="match-btn match-btn--secondary">チャット</a>
                    <?php endif; ?>
                    <a href="<?php echo esc_url($detail_link); ?>" class="match-btn match-btn--secondary">詳細</a>
                <?php else : ?>
                    <a href="<?php echo esc_url($detail_link); ?>" class="match-btn match-btn--secondary">申請内容を確認</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</li>
