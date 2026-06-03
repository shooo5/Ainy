<?php
/**
 * オンボーディング・練習相手成立後モーダル（マイページ・試合一覧・マッチ詳細で共有）
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div
    id="mypage-onboarding-bot-chat-modal"
    class="mypage-onboarding-bot-modal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="mypage-onboarding-bot-chat-modal-title"
    hidden
>
    <div class="mypage-onboarding-bot-modal__backdrop" data-onboarding-bot-modal-close="later"></div>
    <div class="mypage-onboarding-bot-modal__panel card">
        <div class="card-body mypage-onboarding-bot-modal__body">
            <h2 class="mypage-onboarding-bot-modal__title" id="mypage-onboarding-bot-chat-modal-title">試合が成立しました</h2>
            <p class="mypage-onboarding-bot-modal__text">
                相手からの申請が来たら、今のように承認するだけで試合が成立します。<br>
                試合が成立すると相手チームとチャットができるようになります。<br>
                ぜひたくさんの試合募集を出して、子どもたちの経験の場を増やしましょう！
            </p>
            <div class="mypage-onboarding-bot-modal__actions">
                <button type="button" class="btn btn-secondary" id="mypage-onboarding-bot-chat-modal-later" data-onboarding-bot-modal-close="later">あとで</button>
                <a href="#" class="btn btn-secondary" id="mypage-onboarding-bot-chat-modal-chat" hidden>チャットへ</a>
                <button type="button" class="btn btn-primary" id="mypage-onboarding-bot-chat-modal-close">閉じる</button>
            </div>
        </div>
    </div>
</div>
