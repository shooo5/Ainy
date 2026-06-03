<?php
/**
 * デザインリファレンス §01 カラーパレット統一定義
 * 正本: assets/css/aidunite-style.css (:root), assets/css/layout/gender-theme-tokens.css
 */
?>
<p class="section-description">サイト共通色・状態色・男女チームテーマの CSS 変数一覧です。直接色指定（<code>#667eea</code> 等）は禁止し、必ず <code>var(--*)</code> を使用してください。</p>

<div class="design-part">
    <h4>サイト共通（:root）</h4>
    <div class="color-palette ref-showcase--tokens">
        <?php
        $site_colors = [
            ['--primary-color', 'Primary', 'ボタン（主要）・リンク・フォーカスリング'],
            ['--secondary-color', 'Secondary', 'グラデーション補助・装飾'],
            ['--accent-color', 'Accent', '強調アクセント（黄色）'],
            ['--success-color', 'Success', '成功トースト・アラート・badge-success'],
            ['--warning-color', 'Warning', '警告トースト・アラート・仮予定'],
            ['--danger-color', 'Danger', '削除ボタン・エラー・未読バッジ'],
            ['--info-color', 'Info', '情報トースト・アラート'],
            ['--text-primary', 'Text Primary', '本文・見出し'],
            ['--text-secondary', 'Text Secondary', '補足文・ラベル'],
            ['--bg-primary', 'Background', 'カード・モーダル背景'],
            ['--bg-secondary', 'Background Alt', 'ページ背景・セクション'],
            ['--border-light', 'Border', '区切り線・カード枠'],
        ];
        foreach ($site_colors as $item) :
            [$var, $label, $usage] = $item;
            ?>
            <div class="color-item">
                <div class="color-swatch ref-color-swatch" style="background: var(<?php echo esc_attr($var); ?>);"></div>
                <div class="color-info">
                    <div class="color-name"><?php echo esc_html($label); ?></div>
                    <div class="color-value"><code><?php echo esc_html($var); ?></code></div>
                    <div class="token-description"><?php echo esc_html($usage); ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="design-part">
    <h4>男子チーム（body[data-team-theme="boys"]）</h4>
    <p class="part-description">Webアプリ（ログイン後）のページヘッダー・ボトムナビ・スケジュール UI に適用。</p>
    <div class="ref-theme-preview" data-team-theme="boys">
        <div class="color-palette ref-showcase--tokens">
            <?php
            $boys = [
                ['--gender-boys-navy-900', 'Navy 900', 'ヒーロー背景'],
                ['--gender-boys-navy-800', 'Navy 800', 'ナビ・テーマ primary'],
                ['--gender-boys-accent', 'Accent Blue', 'アクセント・タブ・リンク'],
                ['--theme-accent', 'theme-accent', 'ボタン・フィルタ（男子時は青）'],
            ];
            foreach ($boys as $item) :
                [$var, $label, $usage] = $item;
                ?>
                <div class="color-item">
                    <div class="color-swatch ref-color-swatch" style="background: var(<?php echo esc_attr($var); ?>);"></div>
                    <div class="color-info">
                        <div class="color-name"><?php echo esc_html($label); ?></div>
                        <div class="color-value"><code><?php echo esc_html($var); ?></code></div>
                        <div class="token-description"><?php echo esc_html($usage); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="design-part">
    <h4>女子チーム（body[data-team-theme="girls"]）</h4>
    <p class="part-description">男子と同じ DOM 構造で、紫系グラデ・accent に切り替わります。</p>
    <div class="ref-theme-preview ref-theme-preview--girls" data-team-theme="girls">
        <div class="color-palette ref-showcase--tokens">
            <?php
            $girls = [
                ['--gender-girls-navy-900', 'Navy 900', 'ヒーロー背景'],
                ['--gender-girls-navy-700', 'Navy 700', 'ナビグラデ'],
                ['--gender-girls-accent', 'Accent Purple', 'アクセント'],
                ['--theme-accent', 'theme-accent', 'ボタン・タブ（女子時は紫）'],
            ];
            foreach ($girls as $item) :
                [$var, $label, $usage] = $item;
                ?>
                <div class="color-item">
                    <div class="color-swatch ref-color-swatch" style="background: var(<?php echo esc_attr($var); ?>);"></div>
                    <div class="color-info">
                        <div class="color-name"><?php echo esc_html($label); ?></div>
                        <div class="color-value"><code><?php echo esc_html($var); ?></code></div>
                        <div class="token-description"><?php echo esc_html($usage); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="design-part">
    <h4>Design Tokens（スペーシング・角丸・影）</h4>
    <p class="part-description">余白・角丸・影は次の4段階のみ。直接 <code>px</code> 指定は禁止です。</p>
    <table class="feedback-matrix ref-token-table">
        <thead>
            <tr><th>種別</th><th>変数</th><th>値</th><th>主な用途</th></tr>
        </thead>
        <tbody>
            <tr><td rowspan="6">Spacing</td><td><code>--spacing-xs</code></td><td>4px</td><td>アイコンとテキスト</td></tr>
            <tr><td><code>--spacing-sm</code></td><td>8px</td><td>要素内 gap</td></tr>
            <tr><td><code>--spacing-base</code></td><td>16px</td><td>標準 padding</td></tr>
            <tr><td><code>--spacing-md</code></td><td>24px</td><td>セクション間</td></tr>
            <tr><td><code>--spacing-lg</code></td><td>32px</td><td>大きなブロック間</td></tr>
            <tr><td><code>--spacing-xl</code></td><td>48px</td><td>ページ余白</td></tr>
            <tr><td rowspan="4">Radius</td><td><code>--radius-small</code></td><td>6px</td><td>バッジ・小要素</td></tr>
            <tr><td><code>--radius-base</code></td><td>8px</td><td>入力・デフォルト</td></tr>
            <tr><td><code>--radius-medium</code></td><td>12px</td><td>ボタン・カード</td></tr>
            <tr><td><code>--radius-large</code></td><td>16px</td><td>モーダル・トースト</td></tr>
            <tr><td rowspan="4">Shadow</td><td><code>--shadow-sm</code></td><td>軽い影</td><td>カード・ボタン</td></tr>
            <tr><td><code>--shadow-md</code></td><td>中程度</td><td>ホバー・モーダル</td></tr>
            <tr><td><code>--shadow-lg</code></td><td>強い影</td><td>ドロップダウン</td></tr>
            <tr><td><code>--shadow-xl</code></td><td>最大</td><td>トースト・フローティング</td></tr>
        </tbody>
    </table>
</div>
