<?php
/**
 * オンボーディング・練習相手成立後モーダル（マイページ・試合一覧・マッチ詳細で共有）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

$onboarding_bot_modal_gender_theme = '';
if (function_exists('aidunite_match_board_resolve_viewer_team_id')) {
    $modal_team_id = aidunite_match_board_resolve_viewer_team_id(get_current_user_id());
    if ($modal_team_id > 0 && function_exists('aidunite_get_team_ui_theme_key')) {
        $theme_key = aidunite_get_team_ui_theme_key($modal_team_id);
        if ($theme_key === 'girls') {
            $onboarding_bot_modal_gender_theme = 'female';
        } elseif ($theme_key === 'boys') {
            $onboarding_bot_modal_gender_theme = 'male';
        }
    }
}
?>
<div
    id="mypage-onboarding-bot-chat-modal"
    class="mypage-onboarding-bot-modal mypage-onboarding-bot-modal--established match-board-onboarding-modal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="mypage-onboarding-bot-chat-modal-title"
    <?php if ($onboarding_bot_modal_gender_theme !== '') : ?>
    data-gender-theme="<?php echo esc_attr($onboarding_bot_modal_gender_theme); ?>"
    <?php endif; ?>
    hidden
>
    <div class="mypage-onboarding-bot-modal__backdrop" data-onboarding-bot-modal-close="close"></div>
    <div class="mypage-onboarding-bot-modal__panel card">
        <div class="card-body mypage-onboarding-bot-modal__body">
            <header class="match-board-onboarding-modal__head">
                <div class="match-board-onboarding-modal__head-title-wrap">
                    <div class="match-board-onboarding-modal__head-main">
                        <span class="match-board-onboarding-modal__icon" aria-hidden="true">
                            <?php echo aidunite_render_theme_icon('check_circle', ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </span>
                        <h2 class="match-board-onboarding-modal__title" id="mypage-onboarding-bot-chat-modal-title"><?php echo esc_html('試合が成立しました'); ?></h2>
                    </div>
                    <span class="match-board-onboarding-modal__head-accent" aria-hidden="true"></span>
                </div>
            </header>
            <p class="mypage-onboarding-bot-modal__text">
                相手からの申請が来たら、今のように承認するだけで試合が成立します。<br>
                試合が成立すると相手チームとチャットができるようになります。<br>
                ぜひたくさんの試合募集を出して、子どもたちの経験の場を増やしましょう！
            </p>
            <div class="mypage-onboarding-bot-modal__actions">
                <button type="button" class="btn btn-secondary mypage-onboarding-bot-modal__btn--inactive" id="mypage-onboarding-bot-chat-modal-later" disabled aria-disabled="true"><?php echo esc_html('あとで'); ?></button>
                <button type="button" class="btn btn-secondary mypage-onboarding-bot-modal__btn--inactive" id="mypage-onboarding-bot-chat-modal-chat" disabled aria-disabled="true"><?php echo esc_html('チャットへ'); ?></button>
                <button type="button" class="btn btn-primary" id="mypage-onboarding-bot-chat-modal-close"><?php echo esc_html('閉じる'); ?></button>
            </div>
        </div>
    </div>
</div>
