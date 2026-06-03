<?php
/**
 * マッチ詳細：申請の流れ（静的ガイド）
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="card match-detail-flow-card" aria-labelledby="match-detail-flow-title">
    <div class="card-header">
        <h2 id="match-detail-flow-title" class="card-title">申請の流れ</h2>
    </div>
    <div class="card-body">
        <ol class="match-detail-flow-steps">
            <li class="match-detail-flow-steps__item">
                <span class="match-detail-flow-steps__num" aria-hidden="true">1</span>
                <span class="match-detail-flow-steps__text">
                    <strong>この条件で申請</strong>
                    <span class="match-detail-flow-steps__desc">選択した条件を相手チームへ送信します。</span>
                </span>
            </li>
            <li class="match-detail-flow-steps__item">
                <span class="match-detail-flow-steps__num" aria-hidden="true">2</span>
                <span class="match-detail-flow-steps__text">
                    <strong>相手の確認</strong>
                    <span class="match-detail-flow-steps__desc">相手チームが申請内容を確認します。</span>
                </span>
            </li>
            <li class="match-detail-flow-steps__item">
                <span class="match-detail-flow-steps__num" aria-hidden="true">3</span>
                <span class="match-detail-flow-steps__text">
                    <strong>マッチ成立</strong>
                    <span class="match-detail-flow-steps__desc">承認されると試合が成立し、チャットで調整できます。</span>
                </span>
            </li>
            <li class="match-detail-flow-steps__item">
                <span class="match-detail-flow-steps__num" aria-hidden="true">4</span>
                <span class="match-detail-flow-steps__text">
                    <strong>詳細調整</strong>
                    <span class="match-detail-flow-steps__desc">会場・集合時間などをチャットで最終調整します。</span>
                </span>
            </li>
        </ol>
        <p class="match-detail-flow-note">相手が確認するまでは、申請のキャンセルが可能です。</p>
    </div>
</section>
