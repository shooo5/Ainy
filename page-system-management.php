<?php
/*
Template Name: システム管理（開発者専用）
*/

// 必要な関数ファイルを読み込み
require_once get_template_directory() . '/functions/user/user-functions.php';
require_once get_template_directory() . '/functions/payment/payment-config.php';
require_once get_template_directory() . '/functions/payment/payment-functions.php';
require_once get_template_directory() . '/functions/common/error-handler.php';

// 統一認証・権限チェック（管理者のみ）
require_once get_template_directory() . '/functions/common/auth-middleware.php';
$auth_result = AidUniteAuthMiddleware::require_admin(true);
if (!$auth_result->is_valid()) {
    get_header();
    echo '<div class="page-container">';
    echo '<div class="error-message">このページは開発者（administrator）のみアクセス可能です。</div>';
    echo '<a href="' . home_url('/mypage') . '" class="btn btn-primary">マイページに戻る</a>';
    echo '</div>';
    get_footer();
    return;
}

// システム情報・決済管理は Ainy ダッシュボードに統合済み。リダイレクト
wp_safe_redirect(home_url('/ainy-dashboard'));
exit;

$current_user = wp_get_current_user();
$user_id = $current_user->ID;

// 現在のロール情報を取得
list($user_role, $preview_mode) = aidunite_get_effective_user_role();
$aidunite_role = get_user_meta($user_id, 'aidunite_role', true);

// システム情報取得
$wp_version = get_bloginfo('version');
$php_version = phpversion();
$theme_name = wp_get_theme()->get('Name');
$active_plugins = get_option('active_plugins');
$total_users = count_users()['total_users'];
$total_posts = wp_count_posts()->publish;
$total_pages = wp_count_posts('page')->publish;

// ロール別ユーザー数統計
$role_stats = [];
$all_roles = ['team_leader', 'parent', 'player', 'supporter', 'administrator', 'public', 'match', 'general'];
foreach ($all_roles as $role) {
    $role_stats[$role] = 0;
}

$users = get_users();
foreach ($users as $user) {
    $user_aidunite_role = get_user_meta($user->ID, 'aidunite_role', true);
    if ($user_aidunite_role && isset($role_stats[$user_aidunite_role])) {
        $role_stats[$user_aidunite_role]++;
    } else {
        $role_stats['general']++;
    }
}
?>

<div class="system-management-container">
    <div class="dashboard-header">
        <h1>⚙️ システム管理（開発者専用）</h1>
        <p>AidUniteシステムの開発・テスト・管理を行うための統合ダッシュボードです</p>
    </div>

    <!-- システム情報セクション -->
    <section class="dashboard-section">
        <h2>📊 システム情報</h2>

        <div class="system-info-grid">
            <div class="info-card">
                <h3>🔧 技術情報</h3>
                <ul>
                    <li><strong>WordPress:</strong> <?php echo esc_html($wp_version); ?></li>
                    <li><strong>PHP:</strong> <?php echo esc_html($php_version); ?></li>
                    <li><strong>テーマ:</strong> <?php echo esc_html($theme_name); ?></li>
                    <li><strong>アクティブプラグイン:</strong> <?php echo count($active_plugins); ?>個</li>
                </ul>
            </div>

            <div class="info-card">
                <h3>📈 統計情報</h3>
                <ul>
                    <li><strong>総ユーザー数:</strong> <?php echo number_format($total_users); ?>人</li>
                    <li><strong>投稿数:</strong> <?php echo number_format($total_posts); ?>件</li>
                    <li><strong>固定ページ数:</strong> <?php echo number_format($total_pages); ?>件</li>
                    <li><strong>現在のプレビューモード:</strong> <?php echo $preview_mode ? esc_html($preview_mode) : 'なし'; ?></li>
                </ul>
            </div>
        </div>
    </section>

    <!-- ロール別統計セクション -->
    <section class="dashboard-section">
        <h2>👥 ロール別ユーザー統計</h2>

        <div class="role-stats-grid">
            <?php foreach ($role_stats as $role => $count): ?>
            <div class="role-stat-card">
                <div class="role-icon">
                    <?php
                    $icons = [
                        'team_leader' => '👑',
                        'parent' => '👨‍👩‍👧‍👦',
                        'player' => '🏃‍♂️',
                        'supporter' => '💝',
                        'administrator' => '⚙️',
                        'public' => '🌐',
                        'match' => '🤝',
                        'general' => '👤'
                    ];
                    echo $icons[$role] ?? '👤';
                    ?>
                </div>
                                 <div class="role-info">
                     <h4><?php
                     $roles = aidunite_get_user_roles();
                     echo esc_html($roles[$role] ?? $role);
                     ?></h4>
                     <p class="user-count"><?php echo number_format($count); ?>人</p>
                 </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ロールプレビューセクション -->
    <section class="dashboard-section">
        <h2>🔍 ロール別プレビューモード</h2>
        <p>各ロールのマイページ・機能をプレビューできます。URLに <code>?mode=ロール名</code> を追加してアクセスします。</p>

        <div class="preview-controls">
            <div class="preview-buttons-grid">
                <a href="<?php echo add_query_arg('mode', 'team_leader', home_url('/mypage')); ?>" class="preview-btn">
                    <span class="preview-icon">👑</span>
                    <span class="preview-label">チーム代表者</span>
                    <span class="preview-desc">マイページ・スケジュール管理</span>
                </a>

                <a href="<?php echo add_query_arg('mode', 'parent', home_url('/parent-dashboard')); ?>" class="preview-btn">
                    <span class="preview-icon">👨‍👩‍👧‍👦</span>
                    <span class="preview-label">保護者</span>
                    <span class="preview-desc">子供管理・出欠管理</span>
                </a>

                <a href="<?php echo add_query_arg('mode', 'player', home_url('/player-dashboard')); ?>" class="preview-btn">
                    <span class="preview-icon">🏃‍♂️</span>
                    <span class="preview-label">選手</span>
                    <span class="preview-desc">練習・試合参加</span>
                </a>

                <a href="<?php echo add_query_arg('mode', 'match', home_url('/match-board-own')); ?>" class="preview-btn">
                    <span class="preview-icon">🤝</span>
                    <span class="preview-label">マッチ担当</span>
                    <span class="preview-desc">試合調整・マッチング</span>
                </a>

                <a href="<?php echo add_query_arg('mode', 'public', home_url('/team-management')); ?>" class="preview-btn">
                    <span class="preview-icon">🌐</span>
                    <span class="preview-label">一般ユーザー</span>
                    <span class="preview-desc">チーム一覧・閲覧</span>
                </a>
            </div>

            <div class="preview-mode-info">
                <h4>🎯 プレビューモードの使い方</h4>
                <ol>
                    <li>上記のボタンをクリックして各ロールのページにアクセス</li>
                    <li>ページ上部に「プレビューモード」バナーが表示されます</li>
                    <li>バナーの「プレビューモード解除」で通常モードに戻ります</li>
                    <li>すべてのリンクに自動的にプレビューモードが適用されます</li>
                </ol>

                <?php if ($preview_mode): ?>
                <div class="current-preview-info">
                    <p><strong>現在のプレビューモード:</strong> <?php echo esc_html($preview_mode); ?></p>
                    <a href="<?php echo aidunite_remove_mode_from_current_url(); ?>" class="btn btn-warning">プレビューモード解除</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- 機能テストセクション -->
    <section class="dashboard-section">
        <h2>🧪 機能テスト・開発ツール</h2>

        <div class="test-tools-grid">
                            <div class="tool-card">
                    <h3>👥 ユーザー管理</h3>
                    <div class="tool-links">
                        <a href="<?php echo home_url('/admin-user-list'); ?>" class="tool-link">ユーザー一覧・ロール編集（拡張版）</a>
                    </div>
                </div>

                <div class="tool-card">
                    <h3>📅 スケジュール管理</h3>
                    <div class="tool-links">
                        <a href="<?php echo esc_url(home_url('/admin-schedule-list')); ?>" class="tool-link">スケジュール一覧（データ表）</a>
                        <a href="<?php echo esc_url(home_url('/schedule-management')); ?>" class="tool-link">スケジュール管理（カレンダー）</a>
                    </div>
                </div>

                <div class="tool-card">
                    <h3>🏟️ チーム管理</h3>
                    <div class="tool-links">
                        <a href="<?php echo home_url('/team-management'); ?>" class="tool-link">チーム管理（新規作成）</a>
                        <a href="<?php echo home_url('/team-management'); ?>" class="tool-link">チーム一覧</a>
                    </div>
                </div>

            <div class="tool-card">
                <h3>🏟️ マッチ管理</h3>
                <div class="tool-links">
                    <a href="<?php echo home_url('/match-management'); ?>" class="tool-link">マッチ全体管理</a>
                </div>
            </div>

            <div class="tool-card">
                <h3>💰 決済管理</h3>
                <div class="tool-links">
                    <a href="#payment-management" class="tool-link">決済設定</a>
                    <a href="#payment-status-list" class="tool-link">支払い状況一覧</a>
                </div>
            </div>

            <div class="tool-card">
                <h3>📅 スケジュール管理</h3>
                <div class="tool-links">
                    <a href="<?php echo home_url('/schedule-management'); ?>" class="tool-link">スケジュール管理</a>
                    <a href="<?php echo home_url('/schedule-list'); ?>" class="tool-link">スケジュール一覧</a>
                </div>
            </div>

                            <div class="tool-card">
                    <h3>🔧 システムツール</h3>
                    <div class="tool-links">
                        <a href="<?php echo home_url('/system-maintenance'); ?>" class="tool-link">システムメンテナンス（新規作成）</a>
                        <a href="<?php echo home_url('/match-log-view'); ?>" class="tool-link">マッチログ表示</a>
                        <a href="<?php echo admin_url('tools.php'); ?>" class="tool-link">WordPress管理ツール</a>
                    </div>
                </div>

            <div class="tool-card">
                <h3>📊 データ確認</h3>
                <div class="tool-links">
                    <a href="<?php echo home_url('/my-schedule'); ?>" class="tool-link">全チームスケジュール</a>
                    <a href="<?php echo home_url('/team-management'); ?>" class="tool-link">チーム一覧</a>
                </div>
            </div>
        </div>
    </section>

    <!-- クイックアクションセクション -->
    <section class="dashboard-section">
        <h2>⚡ クイックアクション</h2>

        <div class="quick-actions-grid">
            <a href="<?php echo admin_url(); ?>" class="quick-action-btn">
                <span class="action-icon">⚙️</span>
                <span class="action-label">WordPress管理画面</span>
            </a>

            <a href="<?php echo home_url('/mypage'); ?>" class="quick-action-btn">
                <span class="action-icon">🏠</span>
                <span class="action-label">通常マイページ</span>
            </a>

            <a href="<?php echo home_url('/front-page'); ?>" class="quick-action-btn">
                <span class="action-icon">🏠</span>
                <span class="action-label">フロントページ</span>
            </a>

            <a href="<?php echo home_url('/team-management'); ?>" class="quick-action-btn">
                <span class="action-icon">👥</span>
                <span class="action-label">チーム一覧</span>
            </a>
        </div>
    </section>

    <!-- 決済管理セクション -->
    <section class="dashboard-section" id="payment-management">
        <h2>💰 決済管理</h2>

        <div class="payment-management-tabs">
            <button class="tab-button active" data-tab="amount-settings">金額設定</button>
            <button class="tab-button" data-tab="plan-settings">プラン設定</button>
            <button class="tab-button" data-tab="registration-codes">専用コード管理</button>
            <button class="tab-button" data-tab="stripe-settings">Stripe設定</button>
        </div>

        <!-- 金額設定タブ -->
        <div class="tab-content active" id="amount-settings">
            <h3>金額設定</h3>
            <form id="amount-settings-form" class="payment-form">
                <div class="form-group">
                    <label>学校チーム（教育委員会契約）月額:</label>
                    <input type="number" name="board_amount" id="board_amount"
                           value="<?php echo esc_attr(aidunite_get_payment_config()['school']['board_amount']); ?>" min="0">
                    <span>円</span>
                </div>
                <div class="form-group">
                    <label>学校チーム（学校契約）月額:</label>
                    <input type="number" name="school_amount" id="school_amount"
                           value="<?php echo esc_attr(aidunite_get_payment_config()['school']['school_amount']); ?>" min="0">
                    <span>円</span>
                </div>
                <div class="form-group">
                    <label>学校チーム（個人契約）月額:</label>
                    <input type="number" name="personal_amount" id="personal_amount"
                           value="<?php echo esc_attr(aidunite_get_payment_config()['school']['personal_amount']); ?>" min="0">
                    <span>円</span>
                </div>
                <div class="form-group">
                    <label>クラブチーム（1人あたり）月額:</label>
                    <input type="number" name="club_base_amount" id="club_base_amount"
                           value="<?php echo esc_attr(aidunite_get_payment_config()['club']['base_amount']); ?>" min="0">
                    <span>円</span>
                </div>
                <button type="submit" class="btn btn-primary">保存</button>
            </form>
        </div>

        <!-- プラン設定タブ -->
        <div class="tab-content" id="plan-settings">
            <h3>プラン設定</h3>
            <div id="plans-list">
                <?php
                $config = aidunite_get_payment_config();
                $school_plans = $config['school']['plans'];
                foreach ($school_plans as $index => $plan):
                ?>
                <div class="plan-item" data-plan-index="<?php echo $index; ?>">
                    <h4><?php echo esc_html($plan['name']); ?></h4>
                    <p>ID: <?php echo esc_html($plan['id']); ?></p>
                    <p>説明: <?php echo esc_html($plan['description']); ?></p>
                    <p>トライアル: <?php echo esc_html($plan['trial_type'] === 'days' ? $plan['trial_value'] . '日' : $plan['trial_value'] . 'か月'); ?></p>
                    <?php if (!empty($plan['is_default'])): ?>
                    <span class="default-badge">デフォルト</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- 専用コード管理タブ -->
        <div class="tab-content" id="registration-codes">
            <h3>教育委員会専用コード管理</h3>
            <div class="code-generation">
                <button id="generate-code-btn" class="btn btn-primary">新しい専用コードを生成</button>
                <div id="generated-code" style="display:none; margin-top: 1rem; padding: 1rem; background: #f0f0f0; border-radius: 4px;">
                    <p><strong>生成されたコード:</strong> <span id="code-value"></span></p>
                    <button id="copy-code-btn" class="btn btn-secondary">コピー</button>
                </div>
            </div>
        </div>

        <!-- Stripe設定タブ -->
        <div class="tab-content" id="stripe-settings">
            <h3>Stripe API設定</h3>
            <form id="stripe-settings-form" class="payment-form">
                <div class="form-group">
                    <label>Publishable Key:</label>
                    <input type="text" name="publishable_key" id="publishable_key"
                           value="<?php echo esc_attr(get_option('aidunite_stripe_publishable_key', '')); ?>"
                           style="width: 100%; max-width: 500px;">
                </div>
                <div class="form-group">
                    <label>Secret Key:</label>
                    <input type="password" name="secret_key" id="secret_key"
                           value="<?php echo esc_attr(get_option('aidunite_stripe_secret_key', '')); ?>"
                           style="width: 100%; max-width: 500px;">
                </div>
                <div class="form-group">
                    <label>Webhook Secret:</label>
                    <input type="text" name="webhook_secret" id="webhook_secret"
                           value="<?php echo esc_attr(get_option('aidunite_stripe_webhook_secret', '')); ?>"
                           style="width: 100%; max-width: 500px;">
                </div>
                <button type="submit" class="btn btn-primary">保存</button>
            </form>
        </div>
    </section>
</div>

<style>
.system-management-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

/* dashboard-header は共通CSS（aidunite-style.css）を使用 */

.dashboard-section {
    margin-bottom: 40px;
    padding: 30px;
    background: white;
    border-radius: 15px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.dashboard-section h2 {
    margin: 0 0 20px 0;
    color: var(--text-primary);
    font-size: 1.8em;
    border-bottom: 3px solid var(--info-color);
    padding-bottom: 10px;
}

/* システム情報グリッド */
.system-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.info-card {
    padding: 20px;
    background: #f8f9fa;
    border-radius: 10px;
    border-left: 4px solid var(--info-color);
}

.info-card h3 {
    margin: 0 0 15px 0;
    color: var(--text-primary);
}

.info-card ul {
    margin: 0;
    padding-left: 20px;
}

.info-card li {
    margin-bottom: 8px;
    color: var(--text-secondary);
}

/* ロール統計グリッド */
.role-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.role-stat-card {
    display: flex;
    align-items: center;
    padding: 15px;
    background: linear-gradient(135deg, var(--info-color) 0%, var(--primary-color) 100%);
    color: white;
    border-radius: 10px;
    transition: transform 0.2s;
}

.role-stat-card:hover {
    transform: translateY(-2px);
}

.role-icon {
    font-size: 2em;
    margin-right: 15px;
}

.role-info h4 {
    margin: 0 0 5px 0;
    font-size: 1.1em;
}

.user-count {
    margin: 0;
    font-size: 1.5em;
    font-weight: bold;
}

/* プレビューボタングリッド */
.preview-buttons-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
    margin-bottom: 30px;
}

.preview-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 20px;
    background: linear-gradient(135deg, var(--secondary-color) 0%, var(--primary-color) 100%);
    color: white;
    text-decoration: none;
    border-radius: 10px;
    transition: all 0.3s;
}

.preview-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
    color: white;
}

.preview-icon {
    font-size: 2.5em;
    margin-bottom: 10px;
}

.preview-label {
    font-size: 1.2em;
    font-weight: bold;
    margin-bottom: 5px;
}

.preview-desc {
    font-size: 0.9em;
    opacity: 0.9;
    text-align: center;
}

.preview-mode-info {
    background: rgba(23, 162, 184, 0.1);
    padding: 20px;
    border-radius: 10px;
    border-left: 4px solid var(--info-color);
}

.preview-mode-info h4 {
    margin: 0 0 15px 0;
    color: var(--text-primary);
}

.preview-mode-info ol {
    margin: 0 0 20px 0;
    padding-left: 20px;
}

.preview-mode-info li {
    margin-bottom: 8px;
    color: var(--text-secondary);
}

.current-preview-info {
    background: rgba(255, 193, 7, 0.1);
    padding: 15px;
    border-radius: 8px;
    border: 1px solid var(--warning-color);
}

.current-preview-info p {
    margin: 0 0 10px 0;
    color: var(--warning-color);
}

/* テストツールグリッド */
.test-tools-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.tool-card {
    padding: 20px;
    background: #f8f9fa;
    border-radius: 10px;
    border: 1px solid #e9ecef;
}

.tool-card h3 {
    margin: 0 0 15px 0;
    color: var(--text-primary);
    border-bottom: 2px solid #3498db;
    padding-bottom: 8px;
}

.tool-links {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.tool-link {
    display: block;
    padding: 10px 15px;
    background: var(--info-color);
    color: white;
    text-decoration: none;
    border-radius: 5px;
    transition: background 0.2s;
}

.tool-link:hover {
    background: var(--primary-color);
    color: white;
}

/* クイックアクショングリッド */
.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.quick-action-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 20px;
    background: linear-gradient(135deg, #00b894 0%, #00a085 100%);
    color: white;
    text-decoration: none;
    border-radius: 10px;
    transition: all 0.3s;
}

.quick-action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
    color: white;
}

.action-icon {
    font-size: 2em;
    margin-bottom: 10px;
}

.action-label {
    font-weight: bold;
}

/* レスポンシブ対応 */
@media (max-width: 768px) {
    .system-management-container {
        padding: 10px;
    }

    /* dashboard-header は共通CSS（aidunite-style.css）のレスポンシブを使用 */

    .preview-buttons-grid {
        grid-template-columns: 1fr;
    }

    .test-tools-grid {
        grid-template-columns: 1fr;
    }

    .quick-actions-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* 決済管理スタイル */
.payment-management-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    border-bottom: 2px solid var(--border-light);
    flex-wrap: wrap;
}

.tab-button {
    padding: 10px 20px;
    background: #f0f0f0;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    font-size: 1rem;
    transition: all 0.3s;
}

.tab-button:hover {
    background: #e0e0e0;
}

.tab-button.active {
    background: white;
    border-bottom-color: #0073aa;
    font-weight: bold;
}

.tab-content {
    display: none;
    padding: 20px;
    background: white;
    border-radius: 8px;
    border: 1px solid #e0e0e0;
}

.tab-content.active {
    display: block;
}

.payment-form {
    max-width: 600px;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: bold;
}

.form-group input {
    width: 200px;
    padding: 0.5rem;
    border: 1px solid #ccc;
    border-radius: 4px;
}

.form-group span {
    margin-left: 0.5rem;
}

.plan-item {
    padding: 1rem;
    background: #f9f9f9;
    border-radius: 4px;
    margin-bottom: 1rem;
    position: relative;
}

.default-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    background: #0073aa;
    color: white;
    border-radius: 4px;
    font-size: 0.9rem;
    margin-top: 0.5rem;
}

.btn {
    padding: 0.5rem 1rem;
    background: #0073aa;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 1rem;
    transition: background 0.3s;
}

.btn:hover {
    background: #005a87;
}

.btn-secondary {
    background: #6c757d;
}

.btn-secondary:hover {
    background: #5a6268;
}
</style>

<script>
jQuery(document).ready(function($) {
    // タブ切り替え
    $('.tab-button').on('click', function() {
        const tabId = $(this).data('tab');
        $('.tab-button').removeClass('active');
        $('.tab-content').removeClass('active');
        $(this).addClass('active');
        $('#' + tabId).addClass('active');
    });

    // 金額設定保存
    $('#amount-settings-form').on('submit', function(e) {
        e.preventDefault();

        const data = {
            action: 'aidunite_save_payment_config',
            board_amount: $('#board_amount').val(),
            school_amount: $('#school_amount').val(),
            personal_amount: $('#personal_amount').val(),
            club_base_amount: $('#club_base_amount').val(),
            nonce: '<?php echo wp_create_nonce('aidunite_payment_admin'); ?>'
        };

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    if (typeof showToastNotification !== 'undefined') {
                        showToastNotification('設定を保存しました', 'success');
                    } else {
                        alert('設定を保存しました');
                    }
                } else {
                    if (typeof showToastNotification !== 'undefined') {
                        showToastNotification(response.data.message || '保存に失敗しました', 'error');
                    } else {
                        alert('エラー: ' + (response.data.message || '保存に失敗しました'));
                    }
                }
            },
            error: function() {
                if (typeof showToastNotification !== 'undefined') {
                    showToastNotification('通信エラーが発生しました', 'error');
                } else {
                    alert('通信エラーが発生しました');
                }
            }
        });
    });

    // Stripe設定保存
    $('#stripe-settings-form').on('submit', function(e) {
        e.preventDefault();

        const data = {
            action: 'aidunite_save_stripe_keys',
            publishable_key: $('#publishable_key').val(),
            secret_key: $('#secret_key').val(),
            webhook_secret: $('#webhook_secret').val(),
            nonce: '<?php echo wp_create_nonce('aidunite_payment_admin'); ?>'
        };

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    if (typeof showToastNotification !== 'undefined') {
                        showToastNotification('Stripe設定を保存しました', 'success');
                    } else {
                        alert('Stripe設定を保存しました');
                    }
                } else {
                    if (typeof showToastNotification !== 'undefined') {
                        showToastNotification(response.data.message || '保存に失敗しました', 'error');
                    } else {
                        alert('エラー: ' + (response.data.message || '保存に失敗しました'));
                    }
                }
            },
            error: function() {
                if (typeof showToastNotification !== 'undefined') {
                    showToastNotification('通信エラーが発生しました', 'error');
                } else {
                    alert('通信エラーが発生しました');
                }
            }
        });
    });

    // 専用コード生成
    $('#generate-code-btn').on('click', function() {
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'aidunite_generate_registration_code',
                nonce: '<?php echo wp_create_nonce('aidunite_payment_admin'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $('#code-value').text(response.data.code);
                    $('#generated-code').show();
                } else {
                    if (typeof showToastNotification !== 'undefined') {
                        showToastNotification(response.data.message || 'コード生成に失敗しました', 'error');
                    } else {
                        alert('エラー: ' + (response.data.message || 'コード生成に失敗しました'));
                    }
                }
            },
            error: function() {
                if (typeof showToastNotification !== 'undefined') {
                    showToastNotification('通信エラーが発生しました', 'error');
                } else {
                    alert('通信エラーが発生しました');
                }
            }
        });
    });

    // コードコピー
    $('#copy-code-btn').on('click', function() {
        const code = $('#code-value').text();
        navigator.clipboard.writeText(code).then(function() {
            if (typeof showToastNotification !== 'undefined') {
                showToastNotification('コードをコピーしました: ' + code, 'success');
            } else {
                alert('コードをコピーしました: ' + code);
            }
        });
    });
});
</script>

</style>

<?php get_footer(); ?>
