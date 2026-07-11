<?php
/*
Template Name: 登録完了ページプレビュー
*/

// 管理画面からのアクセスのみ許可
if (!current_user_can('manage_options')) {
    wp_die('このページにアクセスする権限がありません。');
}

// 専用CSSファイルの読み込み
wp_enqueue_style('registration-complete-style', get_stylesheet_directory_uri() . '/assets/css/pages/registration-complete.css');

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

<div class="registration-preview-container">
  <div class="registration-preview-header">
    <h1>登録完了ページプレビュー</h1>
    <p>以下のボタンで各ページのプレビューを確認できます</p>
  </div>

  <div class="registration-preview-buttons">
    <button class="preview-btn" data-preview="pending">
      <span class="preview-btn-icon">📧</span>
      仮登録完了ページ
    </button>
    <button class="preview-btn" data-preview="success">
      <span class="preview-btn-icon">🎊</span>
      本登録完了ページ
    </button>
    <button class="preview-btn" data-preview="error">
      <span class="preview-btn-icon">⚠️</span>
      エラーページ
    </button>
  </div>

  <!-- プレビューエリア -->
  <div class="registration-preview-area">
    <div class="preview-placeholder">
      <div class="preview-placeholder-icon">👆</div>
      <h3>プレビューを表示</h3>
      <p>上記のボタンをクリックしてページを確認してください</p>
    </div>
  </div>
</div>

<!-- 仮登録完了ページ -->
<div class="registration-complete-container preview-page" id="preview-pending" style="display: none;">
  <!-- フローティング要素 -->
  <div class="registration-floating-elements">
    <div class="registration-floating-element">🎉</div>
    <div class="registration-floating-element">✨</div>
    <div class="registration-floating-element">🌟</div>
    <div class="registration-floating-element">🎊</div>
  </div>

  <div class="registration-complete-card">
    <!-- 成功アイコン -->
    <div class="registration-success-icon pending">📧</div>

    <!-- タイトル -->
    <h1 class="registration-complete-title">仮登録を受け付けました！</h1>

    <!-- 説明文 -->
    <div class="registration-complete-description">
      <p>ご登録いただき、<strong>ありがとうございます！</strong></p>
      <p>AidUniteの素晴らしい世界への第一歩を踏み出していただきました。</p>
    </div>

    <!-- メール確認セクション -->
    <div class="registration-email-section">
      <div class="registration-email-icon">📬</div>
      <div class="registration-email-title">本登録用メールを送信しました</div>
      <div class="registration-email-text">
        ご入力いただいたメールアドレス宛に本登録用の確認メールを送信しました。<br>
        <strong>メール内のリンクをクリック</strong>して本登録を完了してください。
      </div>
    </div>

    <!-- 注意事項 -->
    <div class="registration-notice">
      <span class="registration-notice-icon">💡</span>
      <strong>メールが届かない場合</strong>は、迷惑メールフォルダもご確認ください。
    </div>

    <!-- アクションボタン -->
    <div class="registration-actions">
      <a href="<?php echo esc_url(home_url('/login/')); ?>" class="registration-btn registration-btn-primary">
        <span class="registration-btn-icon">🚀</span>
        ログイン画面へ
      </a>
      <a href="<?php echo esc_url(home_url('/')); ?>" class="registration-btn registration-btn-secondary">
        <span class="registration-btn-icon">🏠</span>
        ホームへ戻る
      </a>
    </div>
  </div>
</div>

<!-- 本登録完了ページ -->
<div class="registration-complete-container preview-page" id="preview-success" style="display: none;">
  <!-- フローティング要素 -->
  <div class="registration-floating-elements">
    <div class="registration-floating-element">🎉</div>
    <div class="registration-floating-element">✨</div>
    <div class="registration-floating-element">🌟</div>
    <div class="registration-floating-element">🎊</div>
    <div class="registration-floating-element">🎈</div>
    <div class="registration-floating-element">🎆</div>
  </div>

  <div class="registration-complete-card">
    <!-- 成功アイコン -->
    <div class="registration-success-icon success">🎊</div>

    <!-- タイトル -->
    <h1 class="registration-complete-title">本登録が完了しました！</h1>

    <!-- 説明文 -->
    <div class="registration-complete-description">
      <p><strong>おめでとうございます！</strong></p>
      <p>AidUniteの正式メンバーとして登録が完了しました。</p>
      <p>これで全ての機能をご利用いただけます。</p>
    </div>

    <!-- 成功セクション -->
    <div class="registration-email-section">
      <div class="registration-email-icon">✅</div>
      <div class="registration-email-title">登録完了</div>
      <div class="registration-email-text">
        アカウントが正常に有効化されました。<br>
        <strong>ログインしてサービスをご利用ください。</strong>
      </div>
    </div>

    <!-- 次のステップ -->
    <div class="registration-notice">
      <span class="registration-notice-icon">🚀</span>
      <strong>次のステップ</strong>：ログインしてマイページから各種設定を行ってください。
    </div>

    <!-- アクションボタン -->
    <div class="registration-actions">
      <a href="<?php echo esc_url(home_url('/login/')); ?>" class="registration-btn registration-btn-primary">
        <span class="registration-btn-icon">🔑</span>
        ログインする
      </a>
      <a href="<?php echo esc_url(home_url('/')); ?>" class="registration-btn registration-btn-secondary">
        <span class="registration-btn-icon">🏠</span>
        ホームへ戻る
      </a>
    </div>
  </div>
</div>

<!-- エラーページ -->
<div class="registration-complete-container preview-page" id="preview-error" style="display: none;">
  <div class="registration-complete-card">
    <div class="registration-error">
      <div class="registration-error-icon">⚠️</div>
      <h1 class="registration-complete-title">無効なリンクです</h1>
      <div class="registration-complete-description">
        <p>リンクが間違っているか、<strong>有効期限が切れています</strong>。</p>
        <p>新しい登録をお試しください。</p>
      </div>

      <div class="registration-actions">
        <a href="<?php echo esc_url(home_url('/member-register/')); ?>" class="registration-btn registration-btn-primary">
          <span class="registration-btn-icon">🔄</span>
          再登録する
        </a>
        <a href="<?php echo esc_url(home_url('/')); ?>" class="registration-btn registration-btn-secondary">
          <span class="registration-btn-icon">🏠</span>
          ホームへ戻る
        </a>
      </div>
    </div>
  </div>
</div>

<?php
$registration_preview_cfg = [];
if (!empty($preview_page_id)) {
    $registration_preview_cfg['previewPageId'] = $preview_page_id;
}
aidunite_page_asset_localize('registration-preview', $registration_preview_cfg);
get_footer();
?>
