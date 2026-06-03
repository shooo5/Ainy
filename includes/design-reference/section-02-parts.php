<?php
/**
 * デザインリファレンス §02 パーツ統一定義
 */
?>

<!-- ボタン -->
<div class="design-part" id="ref-part-button">
    <h4>ボタン</h4>
    <p class="part-description">正規クラス: <code>.btn</code> + modifier（<code>button.css</code>）。最小高さ 44px。</p>

    <h5 class="feedback-demo-subtitle">プライマリーボタン（.btn-primary）</h5>
    <p class="part-description">画面の<strong>主操作</strong>（送信・保存・申請など）に使用。</p>
    <div class="button-examples ref-inline-wrap ref-btn-size-demo">
        <div class="ref-size-sample">
            <button type="button" class="btn btn-primary btn-sm">小</button>
            <span class="ref-size-label">.btn-sm — 高36px</span>
        </div>
        <div class="ref-size-sample">
            <button type="button" class="btn btn-primary">中（標準）</button>
            <span class="ref-size-label">標準 — 高44px</span>
        </div>
        <div class="ref-size-sample">
            <button type="button" class="btn btn-primary btn-lg">大</button>
            <span class="ref-size-label">.btn-lg — 高52px</span>
        </div>
    </div>

    <h5 class="feedback-demo-subtitle feedback-demo-subtitle--spaced">プライマリーの状態</h5>
    <p class="part-description">各列が1つの状態です。<strong>Hover</strong>＝背景が<strong>約12%暗く</strong>なり、少し浮いて影が強くなる。<strong>Active</strong>＝クリックを押し込んでいる瞬間（沈んで内側の影）。</p>
    <div class="ref-state-compare ref-inline-wrap" aria-label="プライマリーボタン状態見本">
        <div class="ref-state-card">
            <span class="ref-state-label">Default</span>
            <button type="button" class="btn btn-primary">送信</button>
            <span class="ref-state-hint">通常</span>
        </div>
        <div class="ref-state-card">
            <span class="ref-state-label">Hover</span>
            <button type="button" class="btn btn-primary state-hover" tabindex="-1" aria-hidden="true">送信</button>
            <span class="ref-state-hint">背景が暗く</span>
        </div>
        <div class="ref-state-card">
            <span class="ref-state-label">Focus</span>
            <button type="button" class="btn btn-primary state-focus" tabindex="-1" aria-hidden="true">送信</button>
            <span class="ref-state-hint">青リング</span>
        </div>
        <div class="ref-state-card">
            <span class="ref-state-label">Active</span>
            <button type="button" class="btn btn-primary state-active" tabindex="-1" aria-hidden="true">送信</button>
            <span class="ref-state-hint">押下・沈む</span>
        </div>
        <div class="ref-state-card">
            <span class="ref-state-label">Disabled</span>
            <button type="button" class="btn btn-primary" disabled>送信</button>
            <span class="ref-state-hint">操作不可</span>
        </div>
    </div>

    <h5 class="feedback-demo-subtitle feedback-demo-subtitle--spaced">セカンダリーボタン（.btn-secondary / .btn-outline）</h5>
    <p class="part-description"><strong>副操作</strong>（キャンセル・戻る）に使用。主操作より視覚的優先度を下げる。</p>
    <div class="button-examples ref-inline-wrap">
        <button type="button" class="btn btn-secondary">キャンセル</button>
        <button type="button" class="btn btn-outline">アウトライン</button>
        <button type="button" class="btn btn-danger">削除（.btn-danger）</button>
    </div>

    <h5 class="feedback-demo-subtitle feedback-demo-subtitle--spaced">セカンダリーの状態</h5>
    <p class="part-description"><strong>Active の違い</strong>：プライマリーはグラデが暗く沈む／セカンダリーはグレー背景がさらに濃くなる。副操作なので Hover 時の浮き上がりは控えめです。</p>
    <div class="ref-state-compare ref-inline-wrap" aria-label="セカンダリーボタン状態見本">
        <div class="ref-state-card">
            <span class="ref-state-label">Default</span>
            <button type="button" class="btn btn-secondary">キャンセル</button>
            <span class="ref-state-hint">グレー背景</span>
        </div>
        <div class="ref-state-card">
            <span class="ref-state-label">Hover</span>
            <button type="button" class="btn btn-secondary state-hover" tabindex="-1" aria-hidden="true">キャンセル</button>
            <span class="ref-state-hint">背景が濃く</span>
        </div>
        <div class="ref-state-card">
            <span class="ref-state-label">Active</span>
            <button type="button" class="btn btn-secondary state-active" tabindex="-1" aria-hidden="true">キャンセル</button>
            <span class="ref-state-hint">押下・沈む</span>
        </div>
        <div class="ref-state-card">
            <span class="ref-state-label">Disabled</span>
            <button type="button" class="btn btn-secondary" disabled>キャンセル</button>
            <span class="ref-state-hint">半透明</span>
        </div>
    </div>

    <h5 class="feedback-demo-subtitle feedback-demo-subtitle--spaced">Primary vs Secondary — Active 比較</h5>
    <div class="ref-state-compare ref-inline-wrap ref-state-compare--pair" aria-label="Active 状態比較">
        <div class="ref-state-card">
            <span class="ref-state-label">Primary Active</span>
            <button type="button" class="btn btn-primary state-active" tabindex="-1" aria-hidden="true">送信</button>
            <span class="ref-state-hint">グラデが暗く・内側の影</span>
        </div>
        <div class="ref-state-card">
            <span class="ref-state-label">Secondary Active</span>
            <button type="button" class="btn btn-secondary state-active" tabindex="-1" aria-hidden="true">キャンセル</button>
            <span class="ref-state-hint">枠線付きグレーが濃く</span>
        </div>
    </div>
</div>

<!-- チェックボックス・ラジオ -->
<div class="design-part" id="ref-part-form-controls">
    <h4>チェックボックス・ラジオボタン</h4>
    <p class="part-description">正規: <code>.form-check</code> + <code>.form-check-input</code>（<code>form.css</code>）。</p>
    <div class="ref-showcase--2col">
        <div class="ref-form-control-group">
            <h5 class="feedback-demo-subtitle">チェックボックス</h5>
            <div class="ref-inline-wrap ref-form-checks-row">
                <div class="form-check"><input type="checkbox" class="form-check-input" id="ref-chk-1" checked><label class="form-check-label" for="ref-chk-1">選択済み</label></div>
                <div class="form-check"><input type="checkbox" class="form-check-input" id="ref-chk-2"><label class="form-check-label" for="ref-chk-2">未選択</label></div>
                <div class="form-check"><input type="checkbox" class="form-check-input" id="ref-chk-3" disabled><label class="form-check-label" for="ref-chk-3">無効</label></div>
            </div>
        </div>
        <div class="ref-form-control-group">
            <h5 class="feedback-demo-subtitle">ラジオボタン</h5>
            <div class="ref-inline-wrap ref-form-checks-row">
                <div class="form-check"><input type="radio" class="form-check-input" name="ref-radio" id="ref-rad-1" checked><label class="form-check-label" for="ref-rad-1">選択済み</label></div>
                <div class="form-check"><input type="radio" class="form-check-input" name="ref-radio" id="ref-rad-2"><label class="form-check-label" for="ref-rad-2">未選択</label></div>
                <div class="form-check"><input type="radio" class="form-check-input" name="ref-radio-dis" id="ref-rad-3" disabled><label class="form-check-label" for="ref-rad-3">無効</label></div>
            </div>
        </div>
    </div>
</div>

<!-- トグル -->
<div class="design-part" id="ref-part-toggle">
    <h4>トグルスイッチ</h4>
    <p class="part-description">ON/OFF の切り替え。正規: <code>.toggle-switch</code>（<code>toggle-switch.css</code>）。</p>
    <div class="ref-inline ref-toggle-row">
        <label class="toggle-switch">
            <input type="checkbox" checked aria-label="通知を受け取る">
            <span class="toggle-slider"></span>
        </label>
        <span class="toggle-label">通知を受け取る（ON）</span>
        <label class="toggle-switch">
            <input type="checkbox" aria-label="通知を受け取る OFF">
            <span class="toggle-slider"></span>
        </label>
        <span class="toggle-label">OFF</span>
    </div>
</div>

<!-- タブ -->
<div class="design-part" id="ref-part-tabs">
    <h4>タブ</h4>
    <p class="part-description">正規: <code>.tab-nav</code> + <code>.tab-btn</code> + パネル（本ページデモは <code>.ref-tab-panel</code>、実装は <code>.tab-content</code>）。</p>
    <div class="tab-demo ref-tab-demo">
        <div class="tab-nav ref-tab-nav">
            <button type="button" class="tab-btn active" onclick="demoTabSwitch(this, 'ref-tab-panel-1')">タブ1</button>
            <button type="button" class="tab-btn" onclick="demoTabSwitch(this, 'ref-tab-panel-2')">タブ2</button>
            <button type="button" class="tab-btn" onclick="demoTabSwitch(this, 'ref-tab-panel-3')">タブ3</button>
        </div>
        <div class="ref-tab-panel active" id="ref-tab-panel-1" role="tabpanel">タブ1のコンテンツ — 一覧の切り替え例です。</div>
        <div class="ref-tab-panel" id="ref-tab-panel-2" role="tabpanel">タブ2のコンテンツ — 別カテゴリの情報を表示します。</div>
        <div class="ref-tab-panel" id="ref-tab-panel-3" role="tabpanel">タブ3のコンテンツ — 設定や補足情報向けです。</div>
    </div>
</div>

<!-- ツールチップ -->
<div class="design-part" id="ref-part-tooltip">
    <h4>ツールチップ</h4>
    <p class="part-description">正規: <code>.tooltip-trigger</code> + <code>data-tooltip</code>（<code>tooltip.css</code> / <code>tooltip.js</code>）。</p>
    <div class="tooltip-examples ref-inline-wrap">
        <button type="button" class="btn btn-primary tooltip-trigger" data-tooltip="基本ツールチップ">ホバーで表示</button>
        <span class="tooltip-trigger tooltip-bottom" <?php echo aidunite_get_tooltip_attributes('下に表示'); ?>>下</span>
        <span class="tooltip-trigger tooltip-left" <?php echo aidunite_get_tooltip_attributes('左に表示'); ?>>左</span>
        <span class="tooltip-trigger tooltip-right" <?php echo aidunite_get_tooltip_attributes('右に表示'); ?>>右</span>
    </div>
</div>

<?php include __DIR__ . '/section-02-icons.php'; ?>

<!-- ステータス -->
<div class="design-part" id="ref-part-status">
    <h4>ステータス</h4>
    <p class="part-description">マッチ申請・募集などの<strong>状態ラベル</strong>。正規: <code>badge.css</code> の status 系クラス。ホバーで説明ツールチップ。</p>
    <div class="tooltip-badge-group">
        <?php
        $statuses = [
            '未申請' => 'badge',
            '申請中' => 'badge badge-status',
            '申請受付中' => 'badge badge-status',
            '承認済み' => 'badge badge-approved',
            '試合確定' => 'badge badge-established',
            '拒否済み' => 'badge badge-rejected',
            'キャンセル済み' => 'badge badge-canceled',
        ];
        foreach ($statuses as $status => $class) {
            $tooltip_attrs = aidunite_get_tooltip_attributes(aidunite_get_application_status_tooltip($status));
            echo '<span class="' . esc_attr($class) . ' tooltip-trigger" ' . $tooltip_attrs . '>' . esc_html($status) . '</span>';
        }
        ?>
    </div>
</div>

<!-- ピル -->
<div class="design-part" id="ref-part-pills">
    <h4>ピル</h4>
    <p class="part-description">角丸の<strong>ピル型 UI</strong>。用途ごとにクラスが異なります（混同しないこと）。<code>.match-score-badge</code>（マッチ度ピル）は<strong>旧仕様・本番未使用</strong> — 掲示板の段階表示は <code>board_tier</code> + ステータス系バッジを使用。</p>

    <h5 class="feedback-demo-subtitle">① 選択ピル — マッチ詳細</h5>
    <p class="part-description">会場・性別・同日予定の<strong>選択</strong>。正規: <code>.match-select-pills</code> + <code>.match-pill</code>（<code>match-detail.css</code> / <code>docs/spec/match-request.md</code>）。申請中は <code>disabled</code> で固定。</p>
    <div class="ref-pill-demo ref-pill-demo--select">
        <div class="match-select-row">
            <span class="match-select-label">会場</span>
            <div class="match-select-pills" role="group" aria-label="会場（見本）">
                <button type="button" class="match-pill match-pill-place selected" onclick="demoMatchPillSelect(this)">ホーム</button>
                <button type="button" class="match-pill match-pill-place" onclick="demoMatchPillSelect(this)">アウェイ</button>
                <button type="button" class="match-pill match-pill-place" disabled aria-disabled="true">固定（申請中）</button>
            </div>
        </div>
        <div class="match-select-row">
            <span class="match-select-label">性別</span>
            <div class="match-select-pills" role="group" aria-label="性別（見本）">
                <button type="button" class="match-pill match-pill-gender" onclick="demoMatchPillSelect(this)">男子</button>
                <button type="button" class="match-pill match-pill-gender selected" onclick="demoMatchPillSelect(this)">女子</button>
                <button type="button" class="match-pill match-pill-gender" onclick="demoMatchPillSelect(this)">男子・女子可</button>
            </div>
        </div>
    </div>

    <h5 class="feedback-demo-subtitle feedback-demo-subtitle--spaced">② ピルタブ — 試合掲示板</h5>
    <p class="part-description">一覧のタブ切替。正規: <code>.market-axis-pill-tabs</code> + <code>.market-axis-pill-tab</code>（<code>match-board.css</code>）。件数バッジは付けない（仕様）。</p>
    <div class="ref-pill-demo ref-pill-demo--board-tab">
        <nav class="market-axis-pill-tabs" role="tablist" aria-label="掲示板タブ（見本）">
            <button type="button" class="market-axis-pill-tab active" role="tab" aria-selected="true" onclick="demoMarketPillTabSwitch(this)">募集中の試合</button>
            <button type="button" class="market-axis-pill-tab" role="tab" aria-selected="false" onclick="demoMarketPillTabSwitch(this)">申請状況</button>
        </nav>
    </div>

    <h5 class="feedback-demo-subtitle feedback-demo-subtitle--spaced">③ 表示ピル — 掲示板メタ</h5>
    <p class="part-description">行内の短いラベル（選択不可）。正規: <code>.match-pill</code> on <code>.page-match-board-own</code>（角丸 999px・表示専用）。</p>
    <div class="ref-pill-demo ref-pill-demo--board-meta ref-inline-wrap">
        <span class="match-pill">練習試合</span>
        <span class="match-pill match-pill--muted">終了</span>
        <span class="match-pill">イベント</span>
    </div>

    <h5 class="feedback-demo-subtitle feedback-demo-subtitle--spaced">④ リアクションピル — チャット</h5>
    <p class="part-description">メッセージへのリアクション集計。正規: <code>.message-reaction-pill</code>（<code>page-chat.php</code> 内スタイル）。</p>
    <div class="ref-pill-demo ref-pill-demo--reaction ref-inline-wrap">
        <button type="button" class="message-reaction-pill" aria-label="いいね 3件"><span class="message-reaction-icon">👍</span><span class="message-reaction-count">3</span></button>
        <button type="button" class="message-reaction-pill message-reaction-pill--mine" aria-label="了解 1件（自分）"><span class="message-reaction-icon">✅</span><span class="message-reaction-count">1</span></button>
    </div>

    <h5 class="feedback-demo-subtitle feedback-demo-subtitle--spaced">⑤ システムメッセージピル — チャット</h5>
    <p class="part-description">参加通知など、中央寄せのシステム告知。正規: <code>.system-message-pill</code>。</p>
    <div class="ref-pill-demo ref-pill-demo--system">
        <div class="system-message-wrapper">
            <div class="system-message-pill" role="status">
                <div class="system-message-pill__title">試合が成立しました</div>
                <div class="system-message-pill__content">5月20日 14:00 — 〇〇中学校</div>
                <span class="system-message-pill__time">10:32</span>
            </div>
        </div>
    </div>

    <h5 class="feedback-demo-subtitle feedback-demo-subtitle--spaced">⑥ 完了ピル — チーム登録</h5>
    <p class="part-description">登録完了ヒーロー上の成功ラベル。正規: <code>.team-reg-complete-pill</code>（<code>team-registration-complete.css</code>）。</p>
    <div class="ref-pill-demo ref-pill-demo--reg">
        <p class="team-reg-complete-pill"><span class="team-reg-complete-pill__icon" aria-hidden="true">✓</span>登録が完了しました</p>
    </div>
</div>

<!-- バッジ -->
<div class="design-part" id="ref-part-badge">
    <h4>バッジ</h4>
    <p class="part-description">汎用バッジと未読件数。正規: <code>badge.css</code>。</p>
    <div class="badge-examples ref-inline-wrap">
        <span class="badge badge-primary">新着</span>
        <span class="badge badge-secondary">進行中</span>
        <span class="badge badge-success">完了</span>
        <span class="badge badge-warning">保留</span>
        <span class="badge badge-danger">緊急</span>
    </div>
    <h5 class="feedback-demo-subtitle feedback-demo-subtitle--spaced">未読バッジ</h5>
    <p class="part-description">件数表示（<code>.count-badge--overlay</code>）と、未読があることだけ示す<strong>点のみ</strong>（<code>.ainy-notification-dot</code>・ヘッダーお知らせ等）の2パターン。</p>
    <div class="unread-badge-showcase ref-inline-wrap">
        <span class="ref-icon-wrap ref-icon-wrap--dot" aria-hidden="true">
            <span class="ref-icon">🔔</span>
            <span class="ainy-notification-dot" aria-hidden="true"></span>
        </span>
        <span class="ref-size-label ref-size-label--inline">点のみ（未読あり）</span>
        <span class="ref-icon-wrap" aria-hidden="true"><span class="ref-icon">🔔</span><span class="count-badge count-badge--overlay">3</span></span>
        <span class="ref-size-label ref-size-label--inline">件数（赤）</span>
        <span class="ref-tab-like">要対応<span class="count-badge count-badge--unread count-badge--overlay">2</span></span>
        <span class="unread-count-badge">未読: 2件</span>
    </div>
</div>

<!-- メニュー -->
<div class="design-part" id="ref-part-menu">
    <h4>メニュー（ドロップダウン）</h4>
    <p class="part-description">選択肢の展開。ページ固有実装時も <code>.dropdown-menu</code> / <code>.dropdown-item</code> パターンに合わせる。</p>
    <div class="dropdown-demo">
        <button type="button" class="btn btn-primary dropdown-toggle" onclick="demoDropdownToggle(this)">メニューを開く ▼</button>
        <div class="dropdown-menu">
            <a href="#" class="dropdown-item" onclick="return false;">オプション1</a>
            <a href="#" class="dropdown-item" onclick="return false;">オプション2</a>
            <div class="dropdown-divider"></div>
            <a href="#" class="dropdown-item" onclick="return false;">オプション3</a>
        </div>
    </div>
</div>

<!-- ローディング -->
<div class="design-part" id="ref-part-loading">
    <h4>ローディングスピナー</h4>
    <p class="part-description">正規 API: <code>showLoadingSpinner()</code> / <code>LoadingSpinnerManager</code>（<code>loading-spinner.css</code>）。トーストとの併用フローは <a href="#feedback-spinner-toast">21-E：スピナー × トースト</a> を参照。</p>
    <div class="ref-loading-row ref-inline-wrap">
        <div class="spinner-item"><div class="spinner spinner-sm"></div><div class="spinner-label">Small</div></div>
        <div class="spinner-item"><div class="spinner spinner-md spinner-primary"></div><div class="spinner-label">Medium</div></div>
        <div class="spinner-item"><div class="spinner spinner-lg"></div><div class="spinner-label">Large</div></div>
        <button type="button" class="btn btn-primary" id="fullscreen-spinner-btn" onclick="demoShowFullscreenSpinner()">フルスクリーンスピナー</button>
        <button type="button" class="btn btn-success" id="demo-button-spinner" onclick="demoButtonSpinner(this)"><span class="button-text">ボタン内スピナー</span></button>
    </div>
</div>

<!-- プログレスバー -->
<div class="design-part" id="ref-part-progress">
    <h4>プログレスバー</h4>
    <p class="part-description">進捗表示。正規: <code>.progress-bar</code> + <code>.progress-fill</code>。</p>
    <div class="ref-stack">
        <div class="ref-inline">
            <div class="progress-bar"><div class="progress-fill ref-progress-25"></div></div>
            <span class="progress-label">25%</span>
        </div>
        <div class="ref-inline">
            <div class="progress-bar"><div class="progress-fill progress-success ref-progress-50"></div></div>
            <span class="progress-label">50%</span>
        </div>
        <div class="ref-inline">
            <div class="progress-bar"><div class="progress-fill progress-warning ref-progress-75"></div></div>
            <span class="progress-label">75%</span>
        </div>
    </div>
</div>
