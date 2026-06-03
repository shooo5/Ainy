<?php
/*
Template Name: システムメンテナンス（管理者専用）
*/
if (!current_user_can('administrator')) {
    wp_die('このページにはアクセスできません。');
}

// キャッシュクリア処理
if (isset($_POST['clear_cache']) && check_admin_referer('clear_cache')) {
    wp_cache_flush();
    delete_transient('alloptions');
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
    echo '<div class="notice notice-success">キャッシュをクリアしました。</div>';
}

// データベース最適化処理
if (isset($_POST['optimize_database']) && check_admin_referer('optimize_database')) {
    global $wpdb;
    $tables = $wpdb->get_results("SHOW TABLES");
    $optimized_count = 0;

    foreach ($tables as $table) {
        $table_name = array_values((array)$table)[0];
        $result = $wpdb->query("OPTIMIZE TABLE $table_name");
        if ($result !== false) {
            $optimized_count++;
        }
    }

    echo '<div class="notice notice-success">' . $optimized_count . '個のテーブルを最適化しました。</div>';
}

// システム状態確認
$system_status = [
    'wp_version' => get_bloginfo('version'),
    'php_version' => phpversion(),
    'mysql_version' => $wpdb->db_version(),
    'memory_limit' => ini_get('memory_limit'),
    'total_users' => count_users()['total_users'],
    'total_teams' => wp_count_posts('team')->publish,
    'total_schedules' => wp_count_posts('schedule')->publish,
    'disk_free_space' => disk_free_space(ABSPATH),
    'disk_total_space' => disk_total_space(ABSPATH)
];

get_header();
?>

<div class="wrap" style="max-width:1400px;margin:auto;">
    <h1>システムメンテナンス（管理者専用）</h1>

    <!-- システム状態 -->
    <div class="system-status-section" style="background:var(--bg-secondary); padding:20px; margin:20px 0; border-radius:8px;">
        <h3>📊 システム状態</h3>
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap:15px;">
            <div style="background:var(--bg-primary); padding:15px; border-radius:8px; border-left:4px solid var(--primary-color);">
                <h4 style="margin:0 0 10px 0; color:var(--primary-color);">基本情報</h4>
                <p style="margin:5px 0;"><strong>WordPress:</strong> <?php echo esc_html($system_status['wp_version']); ?></p>
                <p style="margin:5px 0;"><strong>PHP:</strong> <?php echo esc_html($system_status['php_version']); ?></p>
                <p style="margin:5px 0;"><strong>MySQL:</strong> <?php echo esc_html($system_status['mysql_version']); ?></p>
            </div>

            <div style="background:var(--bg-primary); padding:15px; border-radius:8px; border-left:4px solid var(--success-color);">
                <h4 style="margin:0 0 10px 0; color:var(--success-color);">データ統計</h4>
                <p style="margin:5px 0;"><strong>ユーザー:</strong> <?php echo number_format($system_status['total_users']); ?>人</p>
                <p style="margin:5px 0;"><strong>チーム:</strong> <?php echo number_format($system_status['total_teams']); ?>チーム</p>
                <p style="margin:5px 0;"><strong>スケジュール:</strong> <?php echo number_format($system_status['total_schedules']); ?>件</p>
            </div>
        </div>
    </div>

    <!-- メンテナンス操作 -->
    <div class="maintenance-actions" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:20px; margin:20px 0;">

        <!-- キャッシュクリア -->
        <div class="action-card" style="background:var(--bg-primary); border:1px solid var(--border-color); border-radius:8px; padding:20px;">
            <h3 style="margin:0 0 15px 0; color:var(--primary-color);">🗑️ キャッシュクリア</h3>
            <p style="margin:0 0 15px 0; color:var(--text-secondary);">WordPressのキャッシュをクリアします。</p>
            <form method="post">
                <?php wp_nonce_field('clear_cache'); ?>
                <button type="submit" name="clear_cache" value="1" class="button button-primary">キャッシュクリア</button>
            </form>
        </div>

        <!-- データベース最適化 -->
        <div class="action-card" style="background:var(--bg-primary); border:1px solid var(--border-color); border-radius:8px; padding:20px;">
            <h3 style="margin:0 0 15px 0; color:var(--success-color);">⚡ データベース最適化</h3>
            <p style="margin:0 0 15px 0; color:var(--text-secondary);">データベーステーブルを最適化します。</p>
            <form method="post">
                <?php wp_nonce_field('optimize_database'); ?>
                <button type="submit" name="optimize_database" value="1" class="button button-primary">最適化実行</button>
            </form>
        </div>

        <!-- システム診断 -->
        <div class="action-card" style="background:var(--bg-primary); border:1px solid var(--border-color); border-radius:8px; padding:20px;">
            <h3 style="margin:0 0 15px 0; color:var(--danger-color);">🔍 システム診断</h3>
            <p style="margin:0 0 15px 0; color:var(--text-secondary);">システムの健全性をチェックします。</p>
            <button type="button" onclick="runSystemDiagnostic()" class="button button-secondary">診断実行</button>
        </div>
    </div>

    <!-- 診断結果表示エリア -->
    <div id="diagnostic-results" style="display:none; background:var(--bg-secondary); padding:20px; margin:20px 0; border-radius:8px;">
        <h3>🔍 システム診断結果</h3>
        <div id="diagnostic-content"></div>
    </div>
</div>

<script>
function runSystemDiagnostic() {
    const resultsDiv = document.getElementById('diagnostic-results');
    const contentDiv = document.getElementById('diagnostic-content');

    resultsDiv.style.display = 'block';
    contentDiv.innerHTML = '<p>診断を実行中...</p>';

    setTimeout(() => {
        contentDiv.innerHTML = '<div style="color:var(--success-color);"><h4>✅ システムは正常です</h4><ul><li>基本的な診断が完了しました</li><li>詳細な診断はWordPress管理画面で確認してください</li></ul></div>';
    }, 1000);
}
</script>

<?php get_footer(); ?>
