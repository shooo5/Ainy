<?php
/*
Template Name: プロフィール編集
*/

get_header();

// 統一認証・権限チェック
require_once get_template_directory() . '/functions/common/auth-middleware.php';
$auth_result = AidUniteAuthMiddleware::require_auth(false);
if (!$auth_result->is_valid()) {
    echo '<div class="profile-edit-wrapper">
            <div class="profile-edit-container">
                <div class="profile-edit-card">
                    <div class="profile-message error">
                        <p>' . esc_html($auth_result->error ?: 'ログインが必要です') . '</p>
                        <a href="' . esc_url($auth_result->redirect_url ?: wp_login_url()) . '" class="profile-save-btn">ログインする</a>
                    </div>
                </div>
            </div>
          </div>';
    get_footer();
    exit;
}

$current_user = wp_get_current_user();
$update_message = '';
$message_type = '';

// リダイレクト後のメッセージ表示（POST-Redirect-GETパターン）
// $_SERVER['QUERY_STRING']から直接パースする方法も試す
parse_str($_SERVER['QUERY_STRING'] ?? '', $query_params);

$updated = isset($_GET['updated']) ? $_GET['updated'] : (isset($query_params['updated']) ? $query_params['updated'] : '');
$error = isset($_GET['error']) ? $_GET['error'] : (isset($query_params['error']) ? $query_params['error'] : '');

if ($updated == '1') {
    $update_message = 'プロフィールを更新しました';
    $message_type = 'success';
} elseif (!empty($error)) {
    $error_messages = [
        'email_exists' => 'このメールアドレスは既に使用されています',
        'security' => 'セキュリティエラーが発生しました。',
        'update_failed' => '更新に失敗しました。',
        'password_mismatch' => 'パスワードが一致しません。',
        'password_short' => 'パスワードは8文字以上で入力してください。'
    ];
    $update_message = $error_messages[$error] ?? 'エラーが発生しました。';
    $message_type = 'error';
}

$profile_display = aidunite_user_get_profile_display((int) $current_user->ID);
$user_phone = (string) ($profile_display['user_phone'] ?? '');
$user_birth_date = (string) ($profile_display['user_birth_date'] ?? '');
$user_gender = (string) ($profile_display['user_gender'] ?? '');
$user_address = (string) ($profile_display['user_address'] ?? '');
$user_bio = (string) ($profile_display['user_bio'] ?? '');
$user_avatar_type = (string) ($profile_display['user_avatar_type'] ?? 'emoji');
$user_avatar_emoji = (string) ($profile_display['user_avatar_emoji'] ?? '👤');

// アバター用の絵文字オプション
$avatar_emojis = ['👤', '😊', '😎', '🤖', '🐱', '🐶', '🦁', '🐯', '🐸', '🐙', '🌟', '💎', '🎮', '⚽', '🏀', '🎾', '🎯', '🎨', '🎭', '🎪'];

?>

<?php
if (function_exists('aidunite_web_app_page_shell_open')) {
    aidunite_web_app_page_shell_open([
        'page_class' => 'page-profile-edit profile-edit-wrapper',
        'title' => 'プロフィール編集',
        'subtitle' => 'あなたの情報を更新しましょう',
        'back_url' => home_url('/mypage'),
    ]);
} else {
    echo '<div class="profile-edit-wrapper">';
}
?>
    <div class="profile-edit-container">
        <div class="profile-edit-card">

            <!-- メッセージ表示 -->
            <?php if ($update_message): ?>
                <div class="profile-message <?php echo $message_type; ?>">
                    <?php echo $update_message; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(home_url('/profile-edit')); ?>" enctype="multipart/form-data" id="profileEditForm">
                <?php wp_nonce_field('update_profile_info', 'profile_edit_nonce'); ?>

                <!-- アバター設定セクション -->
                <div class="profile-avatar-section">
                    <h3 class="profile-section-title"><?php echo aidunite_render_theme_icon('image', ['width' => '22', 'height' => '22'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> アバター設定</h3>

                    <div class="profile-avatar-preview" id="avatarPreview">
                        <span id="avatarEmoji"><?php echo $user_avatar_emoji; ?></span>
                    </div>

                    <div class="profile-avatar-options">
                        <?php foreach ($avatar_emojis as $emoji): ?>
                            <div class="profile-avatar-option <?php echo ($user_avatar_emoji === $emoji) ? 'selected' : ''; ?>"
                                 data-emoji="<?php echo $emoji; ?>">
                                <?php echo $emoji; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <input type="hidden" name="user_avatar_type" value="emoji">
                    <input type="hidden" name="user_avatar_emoji" value="<?php echo $user_avatar_emoji; ?>" id="selectedEmoji">
                </div>

                <!-- 基本情報セクション -->
                <div class="profile-form-section">
                    <h3 class="profile-section-title"><?php echo aidunite_render_theme_icon('person_man', ['width' => '22', 'height' => '22'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 基本情報</h3>

                    <div class="profile-form-row">
                        <div class="profile-form-group">
                            <label for="last_name" class="profile-form-label">
                                姓 <span class="profile-required">*</span>
                            </label>
                            <input type="text" id="last_name" name="last_name"
                                   value="<?php echo esc_attr($current_user->last_name); ?>"
                                   class="profile-form-input" required>
                        </div>

                        <div class="profile-form-group">
                            <label for="first_name" class="profile-form-label">
                                名 <span class="profile-required">*</span>
                            </label>
                            <input type="text" id="first_name" name="first_name"
                                   value="<?php echo esc_attr($current_user->first_name); ?>"
                                   class="profile-form-input" required>
                        </div>
                    </div>

                    <div class="profile-form-group">
                        <label for="display_name" class="profile-form-label">
                            表示名 <span class="profile-required">*</span>
                        </label>
                        <input type="text" id="display_name" name="display_name"
                               value="<?php echo esc_attr($current_user->display_name); ?>"
                               class="profile-form-input" required>
                        <div class="profile-help-text">他のユーザーに表示される名前です</div>
                    </div>

                    <div class="profile-form-group">
                        <label for="user_email" class="profile-form-label">
                            メールアドレス <span class="profile-required">*</span>
                        </label>
                        <input type="email" id="user_email" name="user_email"
                               value="<?php echo esc_attr($current_user->user_email); ?>"
                               class="profile-form-input" required>
                    </div>

                    <div class="profile-form-row">
                        <div class="profile-form-group">
                            <label for="user_phone" class="profile-form-label">電話番号</label>
                            <input type="tel" id="user_phone" name="user_phone"
                                   value="<?php echo esc_attr($user_phone); ?>"
                                   class="profile-form-input" placeholder="090-1234-5678">
                        </div>

                        <div class="profile-form-group">
                            <label for="user_birth_date" class="profile-form-label">生年月日</label>
                            <input type="date" id="user_birth_date" name="user_birth_date"
                                   value="<?php echo esc_attr($user_birth_date); ?>"
                                   class="profile-form-input">
                        </div>
                    </div>

                    <div class="profile-form-group">
                        <label for="user_gender" class="profile-form-label">性別</label>
                        <select id="user_gender" name="user_gender" class="profile-form-select">
                            <option value="">選択してください</option>
                            <option value="male" <?php selected($user_gender, 'male'); ?>>男性</option>
                            <option value="female" <?php selected($user_gender, 'female'); ?>>女性</option>
                            <option value="other" <?php selected($user_gender, 'other'); ?>>その他</option>
                            <option value="prefer_not_to_say" <?php selected($user_gender, 'prefer_not_to_say'); ?>>回答しない</option>
                        </select>
                    </div>

                    <div class="profile-form-group">
                        <label for="user_address" class="profile-form-label">住所</label>
                        <textarea id="user_address" name="user_address"
                                  class="profile-form-textarea"
                                  placeholder="都道府県 市区町村 番地"><?php echo esc_textarea($user_address); ?></textarea>
                    </div>

                    <div class="profile-form-group">
                        <label for="user_bio" class="profile-form-label">自己紹介</label>
                        <textarea id="user_bio" name="user_bio"
                                  class="profile-form-textarea"
                                  placeholder="あなたについて教えてください..."><?php echo esc_textarea($user_bio); ?></textarea>
                        <div class="profile-help-text">最大500文字まで</div>
                    </div>
                </div>

                <!-- セキュリティセクション -->
                <div class="profile-form-section">
                    <h3 class="profile-section-title"><?php echo aidunite_render_theme_icon('lock', ['width' => '22', 'height' => '22'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> セキュリティ設定</h3>

                    <div class="profile-form-group">
                        <label for="new_password" class="profile-form-label">新しいパスワード</label>
                        <input type="password" id="new_password" name="new_password"
                               class="profile-form-input" minlength="8">
                        <div class="profile-help-text">8文字以上で入力してください（変更しない場合は空欄のまま）</div>
                    </div>

                    <div class="profile-form-group">
                        <label for="confirm_password" class="profile-form-label">新しいパスワード（確認）</label>
                        <input type="password" id="confirm_password" name="confirm_password"
                               class="profile-form-input" minlength="8">
                    </div>
                </div>

                <!-- アクションボタン -->
                <div class="profile-form-actions">
                    <button type="submit" name="update_profile" class="profile-save-btn">
                        <?php echo aidunite_render_theme_icon('save', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        プロフィールを保存
                    </button>
                    <a href="<?php echo get_permalink(get_page_by_path('mypage')); ?>" class="profile-cancel-btn">
                        <?php echo aidunite_render_theme_icon('close', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        キャンセル
                    </a>
                </div>
            </form>
        </div>
    </div>

<?php
if (function_exists('aidunite_web_app_page_shell_close')) {
    aidunite_web_app_page_shell_close();
} else {
    echo '</div>';
}
?>

<?php
get_footer();
?>
