<?php
/**
 * デザインリファレンス §03 ページ統一定義
 */
?>

<!-- パンくず・ページネーション（2カラム） -->
<div class="ref-showcase--2col ref-page-nav-grid">
    <div class="design-part ref-showcase-cell" id="ref-page-breadcrumb">
        <h4>パンくずリスト</h4>
        <p class="part-description">現在位置の階層表示。正規: <code>.breadcrumb</code>。</p>
        <nav class="breadcrumb" aria-label="パンくず（見本）">
            <a href="#">ホーム</a>
            <span class="breadcrumb-separator">/</span>
            <a href="#">カテゴリ</a>
            <span class="breadcrumb-separator">/</span>
            <span class="breadcrumb-current">現在のページ</span>
        </nav>
    </div>

    <div class="design-part ref-showcase-cell" id="ref-page-pagination">
        <h4>ページネーション</h4>
        <p class="part-description">一覧のページ切り替え。正規: <code>.pagination</code> + <code>.pagination-btn</code>（<code>pagination.css</code>）。</p>
        <nav class="pagination" aria-label="ページネーション（見本）">
            <button type="button" class="pagination-btn" disabled>« 前へ</button>
            <button type="button" class="pagination-btn active">1</button>
            <button type="button" class="pagination-btn">2</button>
            <button type="button" class="pagination-btn">3</button>
            <button type="button" class="pagination-btn">次へ »</button>
        </nav>
    </div>
</div>

<!-- カード -->
<div class="design-part" id="ref-page-card">
    <h4>カード</h4>
    <p class="part-description">情報ブロック。正規: <code>.card</code> + <code>.card-header</code> / <code>.card-body</code> / <code>.card-footer</code>（<code>card.css</code>）。</p>
    <div class="card ref-card-demo">
        <div class="card-header">
            <h5 class="card-title">カードタイトル</h5>
            <p class="card-subtitle">サブタイトル</p>
        </div>
        <div class="card-body">
            <p>カード本文。リスト2件以上のとき各要素をカードで包む（1件・0件は<a href="<?php echo esc_url(get_template_directory_uri() . '/docs/Redundant-Cards-Guidelines.md'); ?>" target="_blank" rel="noopener">余計なカードガイド</a>参照）。</p>
        </div>
        <div class="card-footer">
            <button type="button" class="btn btn-primary">アクション</button>
            <button type="button" class="btn btn-secondary">キャンセル</button>
        </div>
    </div>
</div>

<!-- フィードバック・モーダル（既存セクションをそのまま include） -->
<?php
$feedback_partial = get_template_directory() . '/includes/design-reference/section-feedback.php';
if (is_readable($feedback_partial)) {
    include $feedback_partial;
}
?>

<!-- アラート -->
<div class="design-part" id="ref-page-alert">
    <h4>アラート</h4>
    <p class="part-description">ページ内に<strong>固定表示</strong>するメッセージ（フィードバック系統 C）。消えるまで残り、ユーザーが読んでから操作を続けます。</p>
    <div class="ref-alert-usage ref-stack">
        <p class="part-description"><strong>使う場面</strong></p>
        <ul class="ref-usage-list">
            <li>フォーム送信<strong>前</strong>の注意・ルール（例：「試合申請は48時間以内に返答してください」）</li>
            <li>権限不足・メンテナンスなど、<strong>画面全体の文脈</strong>に関わる告知</li>
            <li>複数フィールドのバリデーション<strong>サマリー</strong>（上部にまとめて表示）</li>
            <li>ログイン直後の初回案内・未完了タスクの案内</li>
        </ul>
        <p class="part-description"><strong>使わない場面</strong> — 保存成功/失敗など操作結果の一時通知は <a href="#feedback-modal-guide">トースト</a>、確認は <a href="#feedback-modal-guide">確認モーダル</a>、1フィールドのエラーは <code>.form-error</code>。</p>
    </div>
    <div class="alert-examples ref-stack">
        <div class="alert alert-success"><strong>成功！</strong> 操作が正常に完了しました。</div>
        <div class="alert alert-warning"><strong>警告</strong> 注意が必要な情報があります。</div>
        <div class="alert alert-danger"><strong>エラー</strong> 問題が発生しました。</div>
        <div class="alert alert-info"><strong>情報</strong> 参考になる情報をお知らせします。</div>
    </div>
</div>

<!-- カレンダー -->
<div class="design-part" id="ref-page-calendar">
    <h4>カレンダー</h4>
    <p class="part-description">スケジュール共通 UI。正規: <code>schedule.css</code> + <code>schedule-calendar-common.php</code> / <code>schedule.js</code>。</p>
    <div class="calendar-demo calendar-demo--management">
        <div class="calendar-container">
            <div class="calendar-header">
                <button type="button" class="calendar-nav-btn calendar-nav-btn--month" onclick="demoCalendarPrev()" aria-label="前月">
                    <span class="calendar-nav-btn__chevron" aria-hidden="true">‹</span>
                </button>
                <h3 class="calendar-title" id="demo-calendar-title">2026年5月</h3>
                <button type="button" class="calendar-nav-btn calendar-nav-btn--month" onclick="demoCalendarNext()" aria-label="次月">
                    <span class="calendar-nav-btn__chevron" aria-hidden="true">›</span>
                </button>
            </div>
            <div class="calendar-weekdays">
                <div class="weekday weekday-sunday">日</div>
                <div class="weekday weekday-monday">月</div>
                <div class="weekday weekday-tuesday">火</div>
                <div class="weekday weekday-wednesday">水</div>
                <div class="weekday weekday-thursday">木</div>
                <div class="weekday weekday-friday">金</div>
                <div class="weekday weekday-saturday">土</div>
            </div>
            <div class="calendar-grid" id="demo-calendar-grid" role="grid" aria-label="スケジュールカレンダー（デモ）"></div>
        </div>
        <ul class="calendar-demo-legend" aria-label="凡例">
            <li><span class="calendar-demo-legend__swatch calendar-demo-legend__swatch--practice"></span>練習系</li>
            <li><span class="calendar-demo-legend__swatch calendar-demo-legend__swatch--match"></span>試合系</li>
            <li><span class="calendar-demo-legend__swatch calendar-demo-legend__swatch--event"></span>イベント系</li>
            <li><span class="calendar-demo-legend__swatch calendar-demo-legend__swatch--off"></span>休み</li>
        </ul>
    </div>
</div>
