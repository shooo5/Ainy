<?php
/**
 * デザインリファレンス §03 — スケジュール管理のフィードバック・表示仕様
 */
?>

<div class="design-part" id="ref-schedule-feedback">
    <h4>21-F：スケジュール管理 — フィードバックとカード表示</h4>
    <p class="part-description">
        スケジュール管理ページ（<code>page-schedule-management.php</code>）で使う通知・モーダル・カード時間表示の正本です。
        仮予定の確定フローでは<strong>複数の UI が短時間で連続</strong>するため、下表で役割を区別してください。
    </p>

    <table class="feedback-matrix">
        <thead>
            <tr>
                <th>ID</th>
                <th>操作</th>
                <th>UI 種別</th>
                <th>API / 要素</th>
                <th>表示文言（代表）</th>
                <th>状態</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>SC-1</strong></td>
                <td>クイックモーダルで新規保存</td>
                <td>B. 結果トースト</td>
                <td><code>AidUniteScheduleQuickModal.toast()</code> → <code>showToastNotification()</code></td>
                <td>タイトル「完了」／本文「登録しました」</td>
                <td>✅ 正規</td>
            </tr>
            <tr>
                <td><strong>SC-2</strong></td>
                <td>クイックモーダルで編集保存</td>
                <td>B. 結果トースト</td>
                <td>同上</td>
                <td>「保存しました」</td>
                <td>✅ 正規</td>
            </tr>
            <tr>
                <td><strong>SC-3</strong></td>
                <td>仮予定 →「確定する」押下<strong>前</strong></td>
                <td>E. 機能モーダル（入力・確認）</td>
                <td><code>showTentativeConfirmToast()</code> / <code>#tentative-confirm-toast</code></td>
                <td>見出し「仮スケジュールを確定」／時間・会場・メモ編集</td>
                <td>✅ 正規（確定専用）</td>
            </tr>
            <tr>
                <td><strong>SC-4</strong></td>
                <td>仮確定 API 成功<strong>直後</strong></td>
                <td>B. 結果トースト</td>
                <td><code>showToastNotification()</code></td>
                <td>タイトル「完了」／「スケジュールを確定しました」</td>
                <td>✅ 正規</td>
            </tr>
            <tr>
                <td><strong>SC-4L</strong></td>
                <td>（参考）中央ピル</td>
                <td>中央ピル（非採用）</td>
                <td><code>showCenterToast()</code> / <code>.center-toast</code></td>
                <td>「スケジュールを確定しました」（タイトルなし・黒ピル）</td>
                <td>⚠️ デザインリファレンスのみ（本番では未使用）</td>
            </tr>
            <tr>
                <td><strong>SC-5</strong></td>
                <td>確定後のカレンダー再取得</td>
                <td>ページ内オーバーレイ（読み込み）</td>
                <td><code>loadSchedules()</code> → <code>#calendar-loading-overlay</code></td>
                <td>「読み込み中...」（スピナー付き）</td>
                <td>✅ 正規（結果通知ではない）</td>
            </tr>
            <tr>
                <td><strong>SC-6</strong></td>
                <td>削除成功</td>
                <td>B. 結果トースト</td>
                <td><code>showToastNotification()</code></td>
                <td>「スケジュールを削除しました」</td>
                <td>✅ 正規</td>
            </tr>
            <tr>
                <td><strong>SC-7</strong></td>
                <td>カレンダー／リストの時間列</td>
                <td>カード内テキスト（表示仕様）</td>
                <td><code>formatScheduleTimeRange()</code> / <code>formatScheduleListTime()</code></td>
                <td>時間あり → <code>09:00〜11:00</code>／時間なし → <strong>空欄</strong>（「終日」は出さない）</td>
                <td>✅ 正規</td>
            </tr>
            <tr>
                <td><strong>SC-8</strong></td>
                <td>仮確定モーダル・登録フォーム内</td>
                <td>フォーム UI</td>
                <td>終日チェック <code>#toast-all-day</code> / クイックモーダル <code>.sqm-all-day</code></td>
                <td>「終日」ラベル（入力用。カードには反映しない）</td>
                <td>✅ 正規</td>
            </tr>
        </tbody>
    </table>

    <h5 class="feedback-demo-subtitle feedback-demo-subtitle--spaced">仮確定フロー（操作順）</h5>
    <p class="part-description">
        確定ボタンを押すと <strong>SC-3 モーダルが閉じる → SC-4 結果トースト → SC-5 読み込みオーバーレイ</strong> の順で出ます。
        「登録しました」は <strong>SC-1（新規保存時のみ）</strong> の文言で、確定 API 自体では出ません。
        中央ピル（SC-4L）は見た目の候補としてリファレンスに残していますが、本番は <code>showToastNotification</code> に統一しています。
    </p>

    <div class="feedback-pattern-grid feedback-pattern-grid--flow">
        <article class="feedback-pattern-card">
            <div class="feedback-pattern-card__head">
                <h5 class="feedback-pattern-card__title">SC-3 仮確定モーダル（静止プレビュー）</h5>
                <span class="feedback-status-badge feedback-status-badge--ok">✅ 機能モーダル</span>
            </div>
            <p class="feedback-pattern-card__desc">トーストではなく、確定前に時間・会場・メモを編集するダイアログ。クイックモーダルと同トンマナ（<code>.sqm-view-detail-*</code>）。</p>
            <div class="feedback-preview-stage" aria-hidden="true">
                <span class="feedback-preview-stage__label">プレビュー</span>
                <div class="feedback-preview-dim"></div>
                <div class="feedback-preview-confirm feedback-preview-confirm--wide">
                    <div class="feedback-preview-confirm__header">仮スケジュールを確定</div>
                    <div class="feedback-preview-confirm__body ref-schedule-tct-preview">
                        <div class="ref-schedule-tct-row"><span>日付</span><span>2026年6月4日</span></div>
                        <div class="ref-schedule-tct-row"><span>種別</span><span>練習</span></div>
                        <div class="ref-schedule-tct-divider"></div>
                        <div class="ref-schedule-tct-row"><span>時間</span><span>☑ 終日</span></div>
                        <div class="ref-schedule-tct-row"><span>会場</span><span>○○体育館</span></div>
                    </div>
                    <div class="feedback-preview-confirm__footer">
                        <span class="feedback-preview-confirm__btn feedback-preview-confirm__btn--primary">確定する</span>
                        <span class="feedback-preview-confirm__btn feedback-preview-confirm__btn--cancel">キャンセル</span>
                    </div>
                </div>
            </div>
        </article>

        <article class="feedback-pattern-card">
            <div class="feedback-pattern-card__head">
                <h5 class="feedback-pattern-card__title">SC-4L 中央ピル（参考・非採用）</h5>
                <span class="feedback-status-badge feedback-status-badge--warn">⚠️ リファレンスのみ</span>
            </div>
            <p class="feedback-pattern-card__desc">候補パターンとして保管。本番の仮確定成功は SC-4 の <code>showToastNotification</code> を使用。黒ピル・タイトルなし・約2秒で自動消去。</p>
            <div class="feedback-preview-stage" aria-hidden="true">
                <span class="feedback-preview-stage__label">プレビュー</span>
                <div class="feedback-preview-dim"></div>
                <div class="feedback-preview-center-pill">スケジュールを確定しました</div>
            </div>
        </article>

        <article class="feedback-pattern-card">
            <div class="feedback-pattern-card__head">
                <h5 class="feedback-pattern-card__title">SC-7 カード時間（終日なし）</h5>
                <span class="feedback-status-badge feedback-status-badge--ok">✅ 表示仕様</span>
            </div>
            <p class="feedback-pattern-card__desc">終日保存でもカード2行目に「終日」は出さない。時間未設定は行ごと空欄。</p>
            <div class="feedback-preview-stage" aria-hidden="true">
                <span class="feedback-preview-stage__label">プレビュー</span>
                <div class="ref-schedule-card-time-demo">
                    <div class="ref-schedule-card-time-demo__item">
                        <span class="ref-schedule-card-time-demo__label">時間あり</span>
                        <span>13:00〜14:00</span>
                    </div>
                    <div class="ref-schedule-card-time-demo__item">
                        <span class="ref-schedule-card-time-demo__label">終日（保存済み）</span>
                        <span class="ref-schedule-card-time-demo__empty">（表示なし）</span>
                    </div>
                </div>
            </div>
        </article>
    </div>

    <h5 class="feedback-demo-subtitle feedback-demo-subtitle--spaced">21-F-B：ライブデモ</h5>
    <div class="feedback-demo-actions ref-inline-wrap">
        <button type="button" class="btn btn-success" onclick="demoShowToast('success')">SC-1 登録トースト（完了／登録しました）</button>
        <button type="button" class="btn btn-success" onclick="if(typeof showToastNotification==='function'){showToastNotification('スケジュールを確定しました','success');}">SC-4 確定トースト（正規）</button>
        <button type="button" class="btn btn-primary" onclick="demoShowCenterToastSchedule()">SC-4L 中央ピル（参考）</button>
        <button type="button" class="btn btn-secondary" onclick="demoShowCalendarLoadingOverlay()">SC-5 読み込みオーバーレイ</button>
    </div>

    <div class="code-snippet">
        <div class="code-header">
            <span>スケジュール管理フィードバック（コード参照）</span>
            <button class="btn-copy" onclick="copyCode('schedule-feedback-code')">コピー</button>
        </div>
        <pre id="schedule-feedback-code"><code>// SC-1 新規保存（クイックモーダル）
AidUniteScheduleQuickModal.toast('登録しました', 'success');
// → showToastNotification(msg, type)  title: 完了

// SC-3 → SC-4 仮確定
showTentativeConfirmToast(scheduleId);
confirmTentativeSchedule(id, payload, closeToast);
// 成功時: showToastNotification('スケジュールを確定しました', 'success');
// 続けて: loadSchedules() → #calendar-loading-overlay

// SC-4L 中央ピル（参考・本番未使用）
// demoShowCenterToastSchedule() — デザインリファレンスのライブデモのみ

// SC-7 カード時間 — 終日は表示しない
// formatScheduleTimeRange / formatScheduleListTime
// start/end が空なら '' を返す（「終日」文字列は使わない）</code></pre>
    </div>
</div>

