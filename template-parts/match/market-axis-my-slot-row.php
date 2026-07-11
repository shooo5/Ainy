<?php
/**
 * 申請状況タブ：枠別申請行（募集中カード風・2段 .market-axis-my-application-row）
 * 試合完了タブ：レガシー .market-axis-my-slot-row グリッド
 *
 * @var array $args
 */

if (!defined('ABSPATH')) {
    exit;
}

$args = isset($args) && is_array($args) ? $args : [];
$est = isset($args['est']) && is_array($args['est']) ? $args['est'] : null;
$my_schedule_id = (int) ($args['my_schedule_id'] ?? 0);
$viewer_team_id = (int) ($args['viewer_team_id'] ?? 0);
$detail_base = (string) ($args['detail_base'] ?? '');
$is_anchor_host_block = !empty($args['is_anchor_host_block']);
$show_my_tab_nudge = !empty($args['show_my_tab_nudge']);
$is_tail = !empty($args['is_tail']);
$is_muted = !empty($args['is_muted']);
$slot_label = (string) ($args['slot_label'] ?? '');
$tab_context = (string) ($args['tab_context'] ?? 'my');
$empty_message = (string) ($args['empty_message'] ?? '');
$empty_as_card = !empty($args['empty_as_card']);
$parent_date_short = (string) ($args['parent_date_short'] ?? '');
$parent_time_short = (string) ($args['parent_time_short'] ?? '');
$board_card_seen_map = isset($args['board_card_seen_map']) && is_array($args['board_card_seen_map'])
    ? $args['board_card_seen_map']
    : null;
$user_id = get_current_user_id();
if ($est && function_exists('aidunite_match_board_enrich_est_row')) {
    $est = aidunite_match_board_enrich_est_row($est, $viewer_team_id, (int) $user_id, $board_card_seen_map);
}

if ($tab_context === 'my' && !$empty_as_card) :
    $application_row_class = 'market-axis-row market-axis-my-slot-row market-axis-my-application-row';
    if ($is_tail) {
        $application_row_class .= ' market-axis-my-slot-row--tail';
    }
    if (!$est) {
        $application_row_class .= ' market-axis-my-application-row--empty';
    }
    ?>
<li class="<?php echo esc_attr($application_row_class); ?>"<?php
    if ($est && !empty($est['board_card_key'])) :
        ?> data-board-card-key="<?php echo esc_attr((string) $est['board_card_key']); ?>"<?php
        if (!empty($est['board_updated_at'])) :
            ?> data-board-updated-at="<?php echo esc_attr((string) $est['board_updated_at']); ?>"<?php
        endif;
        if (!empty($est['board_is_new'])) :
            ?> data-board-is-new="1"<?php
        endif;
    endif;
    ?>>
    <?php if ($est) :
        $established_detail_link = $detail_base . (int) ($est['other_schedule_id'] ?? 0);
        if (!empty($est['request_id'])) {
            $established_detail_link .= '&match_request_id=' . (int) $est['request_id'];
        }
        $team_name = (string) ($est['team_name'] ?? '—');
        $badge_label = (string) ($est['badge_label'] ?? ($est['status'] ?? '—'));
        $badge_variant = function_exists('aidunite_market_my_slot_badge_variant')
            ? aidunite_market_my_slot_badge_variant($badge_label, (string) ($est['status'] ?? ''))
            : 'default';
        $other_schedule_id = (int) ($est['other_schedule_id'] ?? 0);
        $other_team_id = 0;
        if ($other_schedule_id > 0 && function_exists('aidunite_schedule_read_team_id')) {
            $other_team_id = (int) aidunite_schedule_read_team_id($other_schedule_id);
        }
        $other_team_bundle = $other_team_id && function_exists('aidunite_team_get_display_bundle')
            ? aidunite_team_get_display_bundle($other_team_id)
            : [];
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
            ? aidunite_match_detail_team_avatar_initial($team_name)
            : (function_exists('mb_substr') ? mb_substr($team_name, 0, 1) : substr($team_name, 0, 1));

        $post_match = is_array($est['post_match_survey'] ?? null) ? $est['post_match_survey'] : [];
        $post_match_state = (string) ($post_match['state'] ?? '');
        ?>
    <div class="market-axis-my-application-row__main">
        <div class="market-axis-recruit-row__logo" aria-hidden="true">
            <a href="<?php echo esc_url($established_detail_link); ?>" class="market-axis-recruit-row__logo-link" tabindex="-1">
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
        <div class="market-axis-my-application-row__team">
            <a href="<?php echo esc_url($established_detail_link); ?>" class="market-axis-recruit-row__team-name" title="<?php echo esc_attr($team_name); ?>"><?php echo esc_html($team_name); ?></a>
        </div>
        <span class="market-axis-badge market-axis-badge--my-status market-axis-badge--my-status-<?php echo esc_attr($badge_variant); ?> market-axis-my-application-row__status"><?php echo esc_html($badge_label); ?></span>
        <div class="market-axis-my-application-row__new" aria-hidden="<?php echo !empty($est['board_is_new']) ? 'false' : 'true'; ?>">
            <?php if (!empty($est['board_is_new']) && function_exists('aidunite_match_board_render_new_badge')) : ?>
                <?php echo aidunite_match_board_render_new_badge(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="market-axis-my-application-row__actions">
        <div class="market-axis-recruit-row__cta">
            <?php if ($est['cta_type'] === 'cancel_apply') : ?>
                <a href="<?php echo esc_url($established_detail_link); ?>" class="match-btn match-btn--secondary">詳細</a>
                <button type="button" class="match-btn match-btn--secondary au-open-cancel-modal market-axis-cancel-apply" data-request-id="<?php echo (int) $est['request_id']; ?>" data-team-name="<?php echo esc_attr($team_name); ?>" data-cancel-type="apply">申請をキャンセル</button>
            <?php elseif ($est['cta_type'] === 'approve_reject') : ?>
                <a href="<?php echo esc_url($established_detail_link); ?>" class="match-btn match-btn--primary market-axis-recruit-row__apply-btn">詳細を確認</a>
            <?php elseif ($est['cta_type'] === 'cancel') : ?>
                <a href="<?php echo esc_url($established_detail_link); ?>" class="match-btn match-btn--secondary">詳細</a>
                <?php
                $chat_room = null;
                if (!empty($est['request_id']) && function_exists('aidunite_get_game_chat_room_for_match_request')) {
                    $chat_room = aidunite_get_game_chat_room_for_match_request((int) $est['request_id']);
                }
                if (!$chat_room && function_exists('aidunite_get_match_chat_room')) {
                    $chat_room = aidunite_get_match_chat_room($est['request_id']);
                }
                if (!$chat_room && function_exists('aidunite_get_schedule_chat_room')) {
                    $chat_room = aidunite_get_schedule_chat_room($my_schedule_id);
                }
                $chat_url = $chat_room ? home_url('/chat?room_id=' . (int) $chat_room->id) : home_url('/match-chat?match_id=' . (int) $est['request_id']);
                $suppress_bot_chat = !empty($est['request_id'])
                    && function_exists('aidunite_onboarding_bot_should_suppress_chat_cta')
                    && aidunite_onboarding_bot_should_suppress_chat_cta((int) $est['request_id']);
                if (!$suppress_bot_chat) :
                ?>
                <a href="<?php echo esc_url($chat_url); ?>" class="match-btn match-btn--secondary">チャット</a>
                <?php endif; ?>
                <?php if ($post_match_state === 'pending' && !empty($post_match['url'])) : ?>
                <a href="<?php echo esc_url($post_match['url']); ?>" class="match-btn match-btn--primary market-axis-recruit-row__apply-btn" data-testid="post-match-survey-cta"><?php echo esc_html($post_match['label'] ?? '試合後アンケート'); ?></a>
                <?php elseif ($post_match_state === 'answered') : ?>
                <span class="market-axis-my-slot-row__post-match-answered"><?php echo esc_html($post_match['label'] ?? 'アンケート回答済み'); ?></span>
                <?php endif; ?>
                <?php
                $is_host_pair_cancel_btn = $is_anchor_host_block && empty($est['is_requester']);
                $est_cancel_label = $is_host_pair_cancel_btn ? 'キャンセル' : '確定をキャンセル';
                ?>
                <button type="button" class="match-btn match-btn--secondary au-open-cancel-modal market-axis-established-cancel" data-request-id="<?php echo (int) $est['request_id']; ?>" data-team-name="<?php echo esc_attr($team_name); ?>" data-cancel-type="established" data-cancel-scope="pair"<?php echo $is_host_pair_cancel_btn ? ' data-host-pair-cancel="1"' : ''; ?>><?php echo esc_html($est_cancel_label); ?></button>
            <?php elseif ($est['cta_type'] === 'reapply') :
                $slot_reapply_label = '再申請する';
                $slot_reapply_hide = false;
                if (!empty($est['request_id']) && !empty($est['other_schedule_id']) && function_exists('aidunite_match_detail_reapply_cta')) {
                    $srcta = aidunite_match_detail_reapply_cta((int) $est['other_schedule_id'], (int) $est['request_id'], (int) $my_schedule_id);
                    if (($srcta['variant'] ?? '') === 'ineligible') {
                        $slot_reapply_hide = true;
                    } else {
                        $slot_reapply_label = $srcta['label'] ?: '再申請する';
                    }
                }
                if (!$slot_reapply_hide) : ?>
                <a href="<?php echo esc_url($established_detail_link . '#apply'); ?>" class="match-btn match-btn--primary market-axis-recruit-row__apply-btn"><?php echo esc_html($slot_reapply_label); ?></a>
                <?php endif; ?>
            <?php else : ?>
                <a href="<?php echo esc_url($established_detail_link); ?>" class="match-btn match-btn--secondary">詳細を見る</a>
            <?php endif; ?>
        </div>
    </div>
    <?php else : ?>
    <p class="market-axis-my-application-row__empty" role="status">まだ申請はありません。</p>
    <?php endif; ?>
</li>
    <?php
    return;
endif;

$row_class = 'market-axis-my-slot-row';
if ($tab_context === 'completed') {
    $row_class .= ' market-axis-my-slot-row--completed';
}
if ($is_muted && $tab_context === 'completed') {
    $row_class .= ' market-axis-my-slot-row--completed-muted';
}
if ($is_tail) {
    $row_class .= ' market-axis-my-slot-row--tail';
}
if (!$est) {
    $row_class .= ' market-axis-my-slot-row--empty';
}
if ($empty_as_card) {
    $row_class .= ' market-axis-my-slot-row--summary-card';
}
?>
<div class="<?php echo esc_attr($row_class); ?>"<?php
if ($est && !empty($est['board_card_key'])) :
    ?> data-board-card-key="<?php echo esc_attr((string) $est['board_card_key']); ?>"<?php
    if (!empty($est['board_updated_at'])) :
        ?> data-board-updated-at="<?php echo esc_attr((string) $est['board_updated_at']); ?>"<?php
    endif;
    if (!empty($est['board_is_new'])) :
        ?> data-board-is-new="1"<?php
    endif;
endif;
?>>
    <?php if ($est) :
        $established_detail_link = $detail_base . (int) ($est['other_schedule_id'] ?? 0);
        if (!empty($est['request_id'])) {
            $established_detail_link .= '&match_request_id=' . (int) $est['request_id'];
        }
        $team_name = (string) ($est['team_name'] ?? '—');
        $badge_label = (string) ($est['badge_label'] ?? ($est['status'] ?? '—'));
        $badge_variant = function_exists('aidunite_market_my_slot_badge_variant')
            ? aidunite_market_my_slot_badge_variant($badge_label, (string) ($est['status'] ?? ''))
            : 'default';
        if ($tab_context === 'completed' && function_exists('aidunite_match_board_completed_row_display_badge')) {
            $completed_badge = aidunite_match_board_completed_row_display_badge($est);
            $badge_label = (string) ($completed_badge['label'] ?? $badge_label);
            $badge_variant = (string) ($completed_badge['variant'] ?? $badge_variant);
        }
        $time_disp = (string) ($est['time_display'] ?? '—');
        $place_disp = (string) ($est['place_display'] ?? '—');
        $gender_disp = (string) ($est['gender_display'] ?? '—');
        $venue_line = trim($place_disp . ($gender_disp !== '—' ? '（' . $gender_disp . '）' : ''));

        $other_schedule_id = (int) ($est['other_schedule_id'] ?? 0);
        $other_team_id = 0;
        if ($other_schedule_id > 0 && function_exists('aidunite_schedule_read_team_id')) {
            $other_team_id = (int) aidunite_schedule_read_team_id($other_schedule_id);
        }
        $other_team_bundle = $other_team_id && function_exists('aidunite_team_get_display_bundle')
            ? aidunite_team_get_display_bundle($other_team_id)
            : [];
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
            ? aidunite_match_detail_team_avatar_initial($team_name)
            : (function_exists('mb_substr') ? mb_substr($team_name, 0, 1) : substr($team_name, 0, 1));

        $post_match = is_array($est['post_match_survey'] ?? null) ? $est['post_match_survey'] : [];
        $post_match_state = (string) ($post_match['state'] ?? '');
        ?>
        <div class="market-axis-my-slot-row__datetime">
            <div class="market-axis-my-slot-row__datetime-head">
                <span class="market-axis-my-slot-row__time"><?php echo esc_html($time_disp); ?></span>
                <span class="market-axis-badge market-axis-badge--my-status market-axis-badge--my-status-<?php echo esc_attr($badge_variant); ?> market-axis-my-slot-row__tier-badge"><?php echo esc_html($badge_label); ?></span>
                <?php if (!empty($est['board_is_new']) && function_exists('aidunite_match_board_render_new_badge')) : ?>
                    <div class="market-axis-row-new-badge" aria-hidden="false"><?php echo aidunite_match_board_render_new_badge(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="market-axis-my-slot-row__divider market-axis-my-slot-row__divider--datetime" aria-hidden="true"></div>

        <div class="market-axis-my-slot-row__logo" aria-hidden="true">
            <a href="<?php echo esc_url($established_detail_link); ?>" class="market-axis-my-slot-row__logo-link" tabindex="-1">
                <?php if ($logo_has_url) : ?>
                    <img
                        class="market-axis-my-slot-row__logo-img"
                        src="<?php echo esc_url($logo_url); ?>"
                        alt=""
                        width="40"
                        height="40"
                        decoding="async"
                        <?php echo $logo_style !== '' ? 'style="' . esc_attr($logo_style) . '"' : ''; ?>
                    >
                <?php else : ?>
                    <span class="market-axis-my-slot-row__logo-placeholder"><?php echo esc_html($team_initial); ?></span>
                <?php endif; ?>
            </a>
        </div>

        <div class="market-axis-my-slot-row__team">
            <a href="<?php echo esc_url($established_detail_link); ?>" class="market-axis-my-slot-row__team-name" title="<?php echo esc_attr($team_name); ?>"><?php echo esc_html($team_name); ?></a>
            <p class="market-axis-my-slot-row__venue">
                <span class="market-axis-my-slot-row__venue-label">会場</span>
                <span class="market-axis-my-slot-row__venue-sep" aria-hidden="true">｜</span>
                <span class="market-axis-my-slot-row__venue-value"><?php echo esc_html($venue_line); ?></span>
            </p>
        </div>

        <div class="market-axis-my-slot-row__footer">
        <?php if ($post_match_state === 'pending') : ?>
            <div class="market-axis-my-slot-row__info" role="region" aria-label="試合後アンケート">
                <?php if (function_exists('aidunite_render_theme_icon')) : ?>
                    <span class="market-axis-my-slot-row__info-icon" aria-hidden="true"><?php echo aidunite_render_theme_icon('info', ['width' => '16', 'height' => '16'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                <?php endif; ?>
                <p class="market-axis-my-slot-row__info-text">試合お疲れさまでした。アンケートへのご協力をお願いします。</p>
            </div>
        <?php endif; ?>
        <div class="market-axis-my-slot-row__actions">
            <div class="market-axis-my-slot-row__cta">
                <?php if ($tab_context === 'completed') : ?>
                    <a href="<?php echo esc_url($established_detail_link); ?>" class="match-btn match-btn--secondary">詳細</a>
                    <?php if ($post_match_state === 'pending' && !empty($post_match['url'])) : ?>
                    <a href="<?php echo esc_url($post_match['url']); ?>" class="match-btn match-btn--primary" data-testid="post-match-survey-cta"><?php echo esc_html($post_match['label'] ?? '試合後アンケート'); ?></a>
                    <?php elseif ($post_match_state === 'answered') : ?>
                    <span class="market-axis-my-slot-row__post-match-answered"><?php echo esc_html($post_match['label'] ?? 'アンケート回答済み'); ?></span>
                    <?php endif; ?>
                <?php elseif ($est['cta_type'] === 'cancel_apply') : ?>
                    <a href="<?php echo esc_url($established_detail_link); ?>" class="match-btn match-btn--secondary">詳細</a>
                    <button type="button" class="match-btn match-btn--secondary au-open-cancel-modal market-axis-cancel-apply" data-request-id="<?php echo (int) $est['request_id']; ?>" data-team-name="<?php echo esc_attr($team_name); ?>" data-cancel-type="apply">申請をキャンセル</button>
                <?php elseif ($est['cta_type'] === 'approve_reject') : ?>
                    <a href="<?php echo esc_url($established_detail_link); ?>" class="match-btn match-btn--primary market-axis-my-slot-row__apply-btn">詳細を確認</a>
                <?php elseif ($est['cta_type'] === 'cancel') : ?>
                    <a href="<?php echo esc_url($established_detail_link); ?>" class="match-btn match-btn--secondary">詳細</a>
                    <?php
                    $chat_room = null;
                    if (!empty($est['request_id']) && function_exists('aidunite_get_game_chat_room_for_match_request')) {
                        $chat_room = aidunite_get_game_chat_room_for_match_request((int) $est['request_id']);
                    }
                    if (!$chat_room) {
                        $est_mr_chat = !empty($est['request_id']) && function_exists('aidunite_match_request_get_canonical_meta')
                            ? aidunite_match_request_get_canonical_meta((int) $est['request_id'])
                            : [];
                        $chat_room_id_meta = (int) ($est_mr_chat['chat_room_id'] ?? 0);
                        if ($chat_room_id_meta > 0 && function_exists('aidunite_resolve_canonical_chat_room_id')) {
                            $chat_room_id_meta = (int) aidunite_resolve_canonical_chat_room_id($chat_room_id_meta);
                        }
                        if ($chat_room_id_meta > 0 && function_exists('aidunite_get_chat_room')) {
                            $chat_room = aidunite_get_chat_room($chat_room_id_meta);
                        }
                    }
                    if (!$chat_room && function_exists('aidunite_get_match_chat_room')) {
                        $chat_room = aidunite_get_match_chat_room($est['request_id']);
                    }
                    if (!$chat_room && function_exists('aidunite_get_schedule_chat_room')) {
                        $chat_room = aidunite_get_schedule_chat_room($my_schedule_id);
                    }
                    $chat_url = $chat_room ? home_url('/chat?room_id=' . (int) $chat_room->id) : home_url('/match-chat?match_id=' . (int) $est['request_id']);
                    $suppress_bot_chat = !empty($est['request_id'])
                        && function_exists('aidunite_onboarding_bot_should_suppress_chat_cta')
                        && aidunite_onboarding_bot_should_suppress_chat_cta((int) $est['request_id']);
                    if (!$suppress_bot_chat) :
                    ?>
                    <a href="<?php echo esc_url($chat_url); ?>" class="match-btn match-btn--secondary">チャット</a>
                    <?php endif; ?>
                    <?php if ($post_match_state === 'pending' && !empty($post_match['url'])) : ?>
                    <a href="<?php echo esc_url($post_match['url']); ?>" class="match-btn match-btn--primary" data-testid="post-match-survey-cta"><?php echo esc_html($post_match['label'] ?? '試合後アンケート'); ?></a>
                    <?php elseif ($post_match_state === 'answered') : ?>
                    <span class="market-axis-my-slot-row__post-match-answered"><?php echo esc_html($post_match['label'] ?? 'アンケート回答済み'); ?></span>
                    <?php endif; ?>
                    <?php
                    $is_host_pair_cancel_btn = $is_anchor_host_block && empty($est['is_requester']);
                    $est_cancel_label = $is_host_pair_cancel_btn ? 'キャンセル' : '確定をキャンセル';
                    ?>
                    <button type="button" class="match-btn match-btn--secondary au-open-cancel-modal market-axis-established-cancel" data-request-id="<?php echo (int) $est['request_id']; ?>" data-team-name="<?php echo esc_attr($team_name); ?>" data-cancel-type="established" data-cancel-scope="pair"<?php echo $is_host_pair_cancel_btn ? ' data-host-pair-cancel="1"' : ''; ?>><?php echo esc_html($est_cancel_label); ?></button>
                <?php elseif ($est['cta_type'] === 'reapply') :
                    $slot_reapply_label = '再申請する';
                    $slot_reapply_hide = false;
                    if (!empty($est['request_id']) && !empty($est['other_schedule_id']) && function_exists('aidunite_match_detail_reapply_cta')) {
                        $srcta = aidunite_match_detail_reapply_cta((int) $est['other_schedule_id'], (int) $est['request_id'], (int) $my_schedule_id);
                        if (($srcta['variant'] ?? '') === 'ineligible') {
                            $slot_reapply_hide = true;
                        } else {
                            $slot_reapply_label = $srcta['label'] ?: '再申請する';
                        }
                    }
                    if (!$slot_reapply_hide) : ?>
                    <a href="<?php echo esc_url($established_detail_link . '#apply'); ?>" class="match-btn match-btn--primary market-axis-my-slot-row__apply-btn"><?php echo esc_html($slot_reapply_label); ?></a>
                    <?php endif; ?>
                <?php else : ?>
                    <a href="<?php echo esc_url($established_detail_link); ?>" class="match-btn match-btn--secondary">詳細を見る</a>
                <?php endif; ?>
            </div>
        </div>
        </div>

        <div class="market-axis-my-slot-row__rule" aria-hidden="true"></div>
    <?php elseif ($empty_message !== '') : ?>
        <div class="market-axis-my-slot-row__summary" role="status">
            <span class="market-axis-badge market-axis-badge--my-status market-axis-badge--my-status-completed market-axis-my-slot-row__summary-badge">未成立</span>
            <p class="market-axis-my-slot-row__summary-text"><?php echo esc_html($empty_message); ?></p>
        </div>
    <?php else : ?>
        <div class="market-axis-my-slot-row__empty" role="status">
            <?php if ($slot_label !== '') : ?>
                <span class="market-axis-my-slot-row__empty-label"><?php echo esc_html($slot_label); ?></span>
            <?php endif; ?>
            <span class="market-axis-my-slot-row__empty-text"><?php echo !empty($show_my_tab_nudge) ? 'まだ申請はございません。届くとここに表示されます。' : 'まだ申請はございません。'; ?></span>
        </div>
    <?php endif; ?>
</div>
