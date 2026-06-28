<?php
/*
Template Name: チーム作成申請完了ページプレビュー
*/

// 管理画面からのアクセスのみ許可
if (!current_user_can('manage_options')) {
    wp_die('このページにアクセスする権限がありません。');
}

$preview_complete_css = get_stylesheet_directory() . '/assets/css/pages/registration-complete.css';
wp_enqueue_style(
    'registration-complete-style',
    get_stylesheet_directory_uri() . '/assets/css/pages/registration-complete.css',
    ['aidunite-style'],
    is_readable($preview_complete_css) ? (string) filemtime($preview_complete_css) : '1.0.0'
);

get_header();

// URLパラメータで直接プレビューを表示
$preview_type = isset($_GET['preview']) ? sanitize_text_field($_GET['preview']) : '';
$preview_page_id = '';

// 直接プレビューの場合
if ($preview_type && in_array($preview_type, ['pending', 'success', 'error'])) {
    // 直接プレビューページを表示
    $preview_page_id = "preview-{$preview_type}";
    ?>
    <?php
}
?>

<div class="team-registration-preview-container">
  <div class="team-registration-preview-header">
    <h1>チーム作成申請完了ページプレビュー</h1>
    <p>以下のボタンで各ページのプレビューを確認できます。「申請受付完了」は本番UIと同一です。承認完了・エラーは旧プレビュー用レイアウトです。</p>
  </div>

  <div class="team-registration-preview-buttons">
    <button class="preview-btn" data-preview="pending">
      <span class="preview-btn-icon">📋</span>
      申請受付完了ページ
    </button>
    <button class="preview-btn" data-preview="success">
      <span class="preview-btn-icon">🎊</span>
      申請承認完了ページ
    </button>
    <button class="preview-btn" data-preview="error">
      <span class="preview-btn-icon">⚠️</span>
      エラーページ
    </button>
  </div>

  <!-- プレビューエリア -->
  <div class="team-registration-preview-area">
    <div class="preview-placeholder">
      <div class="preview-placeholder-icon">👆</div>
      <h3>プレビューを表示</h3>
      <p>上記のボタンをクリックしてページを確認してください</p>
    </div>
  </div>
</div>

<!-- 申請受付完了ページ（本番と同一UI） -->
<div class="registration-complete-page team-registration-complete-page preview-page" id="preview-pending" style="display: none;">
  <div class="registration-complete-bg" aria-hidden="true">
    <div class="registration-complete-bg-gradient"></div>
    <div class="registration-complete-bg-wave registration-complete-bg-wave--1"></div>
    <div class="registration-complete-bg-wave registration-complete-bg-wave--2"></div>
  </div>
  <div class="registration-complete-container">
    <?php
    get_template_part(
        'team/registration-complete-content',
        null,
        ['mypage_url' => home_url('/mypage/')]
    );
    ?>
  </div>
</div>

<!-- 申請承認完了ページ -->
<div class="team-registration-complete-container preview-page" id="preview-success" style="display: none;">
  <!-- フローティング要素 -->
  <div class="team-registration-floating-elements">
    <div class="team-registration-floating-element">🎉</div>
    <div class="team-registration-floating-element">✨</div>
    <div class="team-registration-floating-element">🌟</div>
    <div class="team-registration-floating-element">🎊</div>
  </div>

  <div class="team-registration-complete-card">
    <!-- 成功アイコン -->
    <div class="team-registration-success-icon success">🎊</div>

    <!-- タイトル -->
    <h1 class="team-registration-complete-title">チーム作成申請が承認されました！</h1>

    <!-- 説明文 -->
    <div class="team-registration-complete-description">
      <p>おめでとうございます！<strong>チーム作成申請が承認されました！</strong></p>
      <p>これでAidUniteで新しいチームを運営していただけます。</p>
    </div>

    <!-- チーム情報セクション -->
    <div class="team-registration-team-section">
      <div class="team-registration-team-icon">🏆</div>
      <div class="team-registration-team-title">チーム情報</div>
      <div class="team-registration-team-info">
        <div class="team-info-item">
          <span class="team-info-label">チーム名：</span>
          <span class="team-info-value">サンプルチーム</span>
        </div>
        <div class="team-info-item">
          <span class="team-info-label">チームID：</span>
          <span class="team-info-value">TEAM-2024-001</span>
        </div>
        <div class="team-info-item">
          <span class="team-info-label">承認日：</span>
          <span class="team-info-value">2024年1月15日</span>
        </div>
      </div>
    </div>

    <!-- 次のステップ -->
    <div class="team-registration-next-steps">
      <div class="team-registration-next-steps-title">次のステップ</div>
      <div class="team-registration-next-steps-list">
        <div class="next-step-item">
          <span class="next-step-icon">👥</span>
          <span class="next-step-text">メンバーを招待する</span>
        </div>
        <div class="next-step-item">
          <span class="next-step-icon">📅</span>
          <span class="next-step-text">練習スケジュールを設定する</span>
        </div>
        <div class="next-step-item">
          <span class="next-step-icon">🏟️</span>
          <span class="next-step-text">試合を企画する</span>
        </div>
      </div>
    </div>

    <!-- アクションボタン -->
    <div class="team-registration-actions">
      <a href="<?php echo home_url('/mypage'); ?>" class="team-registration-btn team-registration-btn-success">
        <span class="team-registration-btn-icon">🏆</span>
        マイページへ
      </a>
      <a href="<?php echo home_url('/mypage'); ?>" class="team-registration-btn team-registration-btn-secondary">
        <span class="team-registration-btn-icon">👤</span>
        マイページへ
      </a>
    </div>
  </div>
</div>

<!-- エラーページ -->
<div class="team-registration-complete-container preview-page" id="preview-error" style="display: none;">
  <div class="team-registration-complete-card">
    <!-- エラーアイコン -->
    <div class="team-registration-error-icon">⚠️</div>

    <!-- タイトル -->
    <h1 class="team-registration-complete-title">申請処理でエラーが発生しました</h1>

    <!-- 説明文 -->
    <div class="team-registration-complete-description">
      <p>申し訳ございませんが、チーム作成申請の処理中にエラーが発生しました。</p>
      <p>しばらく時間をおいてから再度お試しください。</p>
    </div>

    <!-- エラー詳細 -->
    <div class="team-registration-error-details">
      <div class="team-registration-error-title">エラー詳細</div>
      <div class="team-registration-error-message">
        システムエラーが発生しました。お手数ですが、サポートまでお問い合わせください。
      </div>
    </div>

    <!-- アクションボタン -->
    <div class="team-registration-actions">
      <a href="<?php echo home_url('/team-registration'); ?>" class="team-registration-btn team-registration-btn-primary">
        <span class="team-registration-btn-icon">🔄</span>
        再度申請する
      </a>
      <a href="<?php echo home_url('/contact'); ?>" class="team-registration-btn team-registration-btn-secondary">
        <span class="team-registration-btn-icon">📞</span>
        サポートに問い合わせ
      </a>
    </div>
  </div>
</div>

<?php
$team_registration_preview_cfg = [];
if (!empty($preview_page_id)) {
    $team_registration_preview_cfg['previewPageId'] = $preview_page_id;
}
aidunite_page_asset_localize('team-registration-preview', $team_registration_preview_cfg);
get_footer();
?>
