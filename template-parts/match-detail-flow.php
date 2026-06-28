<?php
/**
 * 試合の詳細：承認後の流れ（静的ガイド）
 */
if (!defined('ABSPATH')) {
    exit;
}

$match_detail_flow_mode = isset($match_detail_flow_mode) ? (string) $match_detail_flow_mode : 'apply';
$is_approval_flow = ($match_detail_flow_mode === 'approval');
?>
<section class="card match-detail-flow-card" aria-labelledby="match-detail-flow-title">
    <div class="card-body">
        <h2 id="match-detail-flow-title" class="match-detail-flow-card__title"><?php echo esc_html($is_approval_flow ? '承認後の流れ' : '申請の流れ'); ?></h2>
        <p class="match-detail-flow-card__lead">
            <?php
            echo esc_html(
                $is_approval_flow
                    ? '承認すると試合が成立し、チャットで詳細の調整ができるようになります。'
                    : '申請から試合当日までの流れです。'
            );
            ?>
        </p>
        <ol class="match-detail-flow-timeline" aria-label="<?php echo esc_attr($is_approval_flow ? '承認後の流れ' : '申請の流れ'); ?>">
            <?php if ($is_approval_flow) : ?>
            <li class="match-detail-flow-timeline__item">
                <span class="match-detail-flow-timeline__icon" aria-hidden="true"><?php echo aidunite_render_theme_icon('check_circle', ['width' => '22', 'height' => '22']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                <span class="match-detail-flow-timeline__label"><?php echo esc_html('承認'); ?></span>
            </li>
            <li class="match-detail-flow-timeline__item">
                <span class="match-detail-flow-timeline__icon" aria-hidden="true"><?php echo aidunite_render_theme_icon('trophy', ['width' => '22', 'height' => '22']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                <span class="match-detail-flow-timeline__label"><?php echo esc_html('試合成立'); ?></span>
            </li>
            <li class="match-detail-flow-timeline__item">
                <span class="match-detail-flow-timeline__icon" aria-hidden="true"><?php echo aidunite_render_theme_icon('chat', ['width' => '22', 'height' => '22']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                <span class="match-detail-flow-timeline__label"><?php echo esc_html('詳細調整'); ?></span>
            </li>
            <li class="match-detail-flow-timeline__item">
                <span class="match-detail-flow-timeline__icon" aria-hidden="true"><?php echo aidunite_render_theme_icon('calendar_month', ['width' => '22', 'height' => '22']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                <span class="match-detail-flow-timeline__label"><?php echo esc_html('試合当日'); ?></span>
            </li>
            <?php else : ?>
            <li class="match-detail-flow-timeline__item">
                <span class="match-detail-flow-timeline__icon" aria-hidden="true"><?php echo aidunite_render_theme_icon('send', ['width' => '22', 'height' => '22']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                <span class="match-detail-flow-timeline__label"><?php echo esc_html('申請'); ?></span>
            </li>
            <li class="match-detail-flow-timeline__item">
                <span class="match-detail-flow-timeline__icon" aria-hidden="true"><?php echo aidunite_render_theme_icon('person_check', ['width' => '22', 'height' => '22']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                <span class="match-detail-flow-timeline__label"><?php echo esc_html('相手の確認'); ?></span>
            </li>
            <li class="match-detail-flow-timeline__item">
                <span class="match-detail-flow-timeline__icon" aria-hidden="true"><?php echo aidunite_render_theme_icon('trophy', ['width' => '22', 'height' => '22']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                <span class="match-detail-flow-timeline__label"><?php echo esc_html('試合成立'); ?></span>
            </li>
            <li class="match-detail-flow-timeline__item">
                <span class="match-detail-flow-timeline__icon" aria-hidden="true"><?php echo aidunite_render_theme_icon('calendar_month', ['width' => '22', 'height' => '22']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                <span class="match-detail-flow-timeline__label"><?php echo esc_html('試合当日'); ?></span>
            </li>
            <?php endif; ?>
        </ol>
        <p class="match-detail-flow-note"><?php echo esc_html($is_approval_flow ? '承認後はチャットで会場・集合時間などを調整できます。' : '相手が確認するまでは、申請のキャンセルが可能です。'); ?></p>
    </div>
</section>
