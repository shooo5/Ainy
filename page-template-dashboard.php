<?php
/**
 * Template Name: ダッシュボード型ページテンプレート
 *
 * ダッシュボード型ページテンプレート
 *
 * 使用方法:
 * 1. このファイルをincludeする
 * 2. 以下の変数を設定する:
 *    - $page_title: ページタイトル
 *    - $page_description: ページの説明
 *    - $page_icon: ページアイコン（SVG basename、例: bar_chart_4_bars）
 *    - $tabs: タブ配列 [['id' => 'tab1', 'label' => 'タブ1', 'icon' => 'list_alt_add']]
 *    - $tab_contents: タブコンテンツ配列
 *    - $required_role: 必要な権限（オプション）
 *    - $preview_mode: プレビューモード（オプション）
 */

// ページスラッグを自動取得
$page_slug = get_post_field('post_name', get_post()) ?: 'dashboard';

// 統一認証・権限チェック
if (isset($required_role)) {
    // 特定のロールが必要な場合
    $auth_result = AidUniteAuthMiddleware::require([
        'roles' => [$required_role, 'administrator'],
        'redirect' => true,
    ]);
} else {
    // ログインのみ必要な場合
    $auth_result = AidUniteAuthMiddleware::require_auth(true);
}

if (!$auth_result->is_valid()) {
    // リダイレクトは自動で実行される
    return;
}

// ユーザー情報を取得
list($user_role, $preview_mode) = aidunite_get_effective_user_role();

// デフォルト値の設定
$page_title = $page_title ?? 'ダッシュボード';
$page_description = $page_description ?? 'ダッシュボードページです';
$page_icon = $page_icon ?? 'bar_chart_4_bars';
$tabs = $tabs ?? [];
$tab_contents = $tab_contents ?? [];
$preview_mode = $preview_mode ?? false;

get_header();

$use_integrated_ui = function_exists('aidunite_should_use_web_app_integrated_ui')
    && aidunite_should_use_web_app_integrated_ui()
    && function_exists('aidunite_web_app_page_shell_open');

$integrated_hero_args = isset($web_app_hero_args) && is_array($web_app_hero_args) ? $web_app_hero_args : [];

if ($preview_mode) {
    aidunite_preview_mode_banner($preview_mode);
}

if ($use_integrated_ui) {
    $shell_args = [
        'page_class' => 'page-' . $page_slug,
        'title' => $page_title,
        'subtitle' => $page_description,
    ];
    if ($integrated_hero_args !== []) {
        $shell_args['hero'] = $integrated_hero_args;
    }
    aidunite_web_app_page_shell_open($shell_args);
} else {
    echo '<div class="team-dashboard-container page-' . esc_attr($page_slug) . '">';
    if (trim((string) $page_title) !== '' || trim((string) $page_description) !== '') {
        echo '<div class="dashboard-header">';
        if (trim((string) $page_title) !== '') {
            echo '<h1>' . esc_html($page_title) . '</h1>';
        }
        if (trim((string) $page_description) !== '') {
            echo '<p>' . esc_html($page_description) . '</p>';
        }
        echo '</div>';
    }
}
?>

  <?php if (!empty($tabs)): ?>
    <!-- タブナビゲーション -->
    <div class="tab-navigation">
      <?php foreach ($tabs as $index => $tab): ?>
        <button class="tab-btn <?php echo $index === 0 ? 'active' : ''; ?>"
                data-tab="<?php echo esc_attr($tab['id']); ?>">
          <?php echo aidunite_render_dashboard_icon($tab['icon']); ?> <?php echo esc_html($tab['label']); ?>
        </button>
      <?php endforeach; ?>
    </div>

    <!-- タブコンテンツ -->
    <?php foreach ($tab_contents as $index => $content): ?>
      <section class="dashboard-section tab-content <?php echo $index === 0 ? 'active' : ''; ?>"
               id="<?php echo esc_attr($content['id']); ?>">
        <h2><?php echo aidunite_render_dashboard_icon($content['icon']); ?> <?php echo esc_html($content['title']); ?></h2>
        <div class="main-content-area">
          <?php echo $content['content']; ?>
        </div>
      </section>
    <?php endforeach; ?>
  <?php else: ?>
    <!-- タブなしの場合は直接コンテンツを表示 -->
    <section class="dashboard-section">
      <div class="main-content-area">
        <?php echo $main_content ?? '<div class="empty-state"><div class="empty-icon">' . aidunite_render_theme_icon('list_alt_add', ['width' => '40', 'height' => '40']) . '</div><h3>コンテンツが設定されていません</h3><p>ページコンテンツを設定してください。</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
      </div>
    </section>
  <?php endif; ?>

  <?php if (isset($dashboard_footer)): ?>
    <!-- ダッシュボードフッター -->
    <div class="dashboard-footer">
      <?php echo $dashboard_footer; ?>
    </div>
  <?php endif; ?>

<?php
if ($use_integrated_ui && function_exists('aidunite_web_app_page_shell_close')) {
    aidunite_web_app_page_shell_close();
} else {
    echo '</div>';
}
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // タブ切り替え機能
  const tabBtns = document.querySelectorAll('.tab-btn');
  const tabContents = document.querySelectorAll('.tab-content');

  tabBtns.forEach(btn => {
    btn.addEventListener('click', function() {
      const targetTab = this.getAttribute('data-tab');

      // タブボタンのアクティブ状態を切り替え
      tabBtns.forEach(b => b.classList.remove('active'));
      this.classList.add('active');

      // タブコンテンツの表示を切り替え
      tabContents.forEach(content => {
        content.classList.remove('active');
        if (content.id === targetTab) {
          content.classList.add('active');
        }
      });
    });
  });
});
</script>

<?php get_footer(); ?>
