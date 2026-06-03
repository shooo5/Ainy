<?php
/**
 * Template Name: 保護者登録ページ
 */

// トークンとチームIDの取得
$token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
$team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : 0;

// トークン検証
$token_data = null;
if ($token) {
    $token_data = aidunite_verify_invite_token($token, $team_id);
}

// トークンが無効または期限切れの場合
if (!$token_data) {
    get_header();
    ?>
    <div class="team-dashboard-container" style="padding: 2rem; text-align: center;">
        <div class="mypage-card" style="max-width: 600px; margin: 0 auto;">
            <h1>無効な招待リンク</h1>
            <p>この招待リンクは無効または期限切れです。</p>
            <p>チーム代表者に新しい招待リンクを依頼してください。</p>
            <a href="<?php echo home_url('/'); ?>" class="btn btn-primary" style="margin-top: 1rem;">ホームに戻る</a>
        </div>
    </div>
    <?php
    get_footer();
    exit;
}

// チーム情報取得
$team_info = aidunite_get_team_info($team_id);
if (!$team_info) {
    wp_die('チーム情報が見つかりません。');
}

// フォーム送信処理
$registration_result = null;
$form_errors = [];

if ($_POST && isset($_POST['guardian_signup_nonce']) && wp_verify_nonce($_POST['guardian_signup_nonce'], 'guardian_signup')) {
    // 保護者情報を収集（メールは正規化: 全角→半角・小文字）
    $parent_data = [
        'parent_name_sei' => sanitize_text_field($_POST['parent_name_sei'] ?? ''),
        'parent_name_mei' => sanitize_text_field($_POST['parent_name_mei'] ?? ''),
        'parent_email' => function_exists('aidunite_normalize_email') ? aidunite_normalize_email($_POST['parent_email'] ?? '') : sanitize_email($_POST['parent_email'] ?? ''),
        'parent_phone' => sanitize_text_field($_POST['parent_phone'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'password_confirm' => $_POST['password_confirm'] ?? '',
    ];

    // バリデーション
    if (empty($parent_data['parent_name_sei']) || empty($parent_data['parent_name_mei'])) {
        $form_errors[] = '保護者名（姓・名）は必須です';
    }
    if (empty($parent_data['parent_email'])) {
        $form_errors[] = 'メールアドレスは必須です';
    }

    // 既存ユーザーチェック
    $existing_user = null;
    if (!empty($parent_data['parent_email']) && email_exists($parent_data['parent_email'])) {
        $existing_user = get_user_by('email', $parent_data['parent_email']);
    }

    // 既存ユーザーでない場合のみパスワードチェック
    if (!$existing_user) {
        if (empty($parent_data['password']) || strlen($parent_data['password']) < 8) {
            $form_errors[] = 'パスワードは8文字以上で入力してください';
        }
        if ($parent_data['password'] !== $parent_data['password_confirm']) {
            $form_errors[] = 'パスワードが一致しません';
        }
    }

    if (empty($form_errors)) {
        // 既存ユーザーの場合
        if ($existing_user) {
            $user_id = $existing_user->ID;

            // 保護者として設定
            aidunite_set_user_type($user_id, 'parent');

            // 複数チーム所属対応: 既存のチーム所属を確認
            $existing_teams = aidunite_get_user_teams($user_id);

            // team_id が存在するが team_memberships が空の場合、team_id から team_memberships を作成
            if (empty($existing_teams)) {
                $existing_team_id = get_user_meta($user_id, 'team_id', true);
                if ($existing_team_id) {
                    // 既存の team_id を team_memberships に変換
                    $existing_teams = [
                        $existing_team_id => [
                            'team_id' => $existing_team_id,
                            'role' => 'parent',
                            'joined_date' => current_time('mysql'),
                            'status' => 'active'
                        ]
                    ];
                    update_user_meta($user_id, 'team_memberships', $existing_teams);
                    // キャッシュをクリアして、次の処理で最新の値を取得できるようにする
                    clean_user_cache($user_id);
                }
            }

            // 新しいチームに追加（既存チームがある場合もない場合も統一処理）
            aidunite_add_user_to_multiple_teams($user_id, $team_id, 'parent');

            // 保護者メタデータを保存（共通メタ: 姓・名も first_name / last_name に同期）
            $parent_name = trim($parent_data['parent_name_sei'] . ' ' . $parent_data['parent_name_mei']);
            wp_update_user([
                'ID' => $user_id,
                'display_name' => $parent_name,
                'last_name' => $parent_data['parent_name_sei'],
                'first_name' => $parent_data['parent_name_mei'],
            ]);

            update_user_meta($user_id, 'parent_name_sei', $parent_data['parent_name_sei']);
            update_user_meta($user_id, 'parent_name_mei', $parent_data['parent_name_mei']);
            update_user_meta($user_id, 'parent_name', $parent_name);
            update_user_meta($user_id, 'parent_email', $parent_data['parent_email']);
            update_user_meta($user_id, 'family_id', (string) $user_id);
            if (!empty($parent_data['parent_phone'])) {
                update_user_meta($user_id, 'parent_phone', $parent_data['parent_phone']);
            }

            // 登録状態を本登録済みに設定（会員登録フローと同等）
            aidunite_update_user_registration_status_meta($user_id, 'accepted');
            update_user_meta($user_id, 'user_status', 0);
            update_user_meta($user_id, 'registration_date', current_time('mysql'));

            // トークンを削除（使用済み）
            $option_key = 'aidunite_invite_token_' . md5($token);
            delete_option($option_key);

            // キャッシュをクリアしてメタデータの更新を確実に反映
            clean_user_cache($user_id);
            wp_cache_flush();

            // ログイン状態に応じてリダイレクト
            if (is_user_logged_in() && get_current_user_id() == $user_id) {
                // 既にログイン済み
                wp_redirect(add_query_arg([
                    'registered' => '1',
                    'team_id' => $team_id
                ], home_url('/mypage')));
            } else {
                // ログインページにリダイレクト
                wp_redirect(add_query_arg([
                    'redirect_to' => add_query_arg([
                        'registered' => '1',
                        'team_id' => $team_id
                    ], home_url('/mypage'))
                ], wp_login_url()));
            }
            exit;
        }

        // 新規ユーザーの場合: 保護者アカウント作成
        $username = sanitize_user($parent_data['parent_email']);

        $user_id = wp_create_user($username, $parent_data['password'], $parent_data['parent_email']);

        if (is_wp_error($user_id)) {
            $form_errors[] = 'アカウント作成に失敗しました: ' . $user_id->get_error_message();
        } else {
            // ユーザー情報を更新（共通メタ: 姓・名も first_name / last_name に同期）
            $parent_name = trim($parent_data['parent_name_sei'] . ' ' . $parent_data['parent_name_mei']);
            wp_update_user([
                'ID' => $user_id,
                'display_name' => $parent_name,
                'last_name' => $parent_data['parent_name_sei'],
                'first_name' => $parent_data['parent_name_mei'],
            ]);

            // 保護者として設定
            aidunite_set_user_type($user_id, 'parent');

            aidunite_set_user_team($user_id, $team_id);

            // 保護者メタデータを保存
            update_user_meta($user_id, 'parent_name_sei', $parent_data['parent_name_sei']);
            update_user_meta($user_id, 'parent_name_mei', $parent_data['parent_name_mei']);
            update_user_meta($user_id, 'parent_name', $parent_name);
            update_user_meta($user_id, 'parent_email', $parent_data['parent_email']);
            update_user_meta($user_id, 'family_id', (string) $user_id);
            if (!empty($parent_data['parent_phone'])) {
                update_user_meta($user_id, 'parent_phone', $parent_data['parent_phone']);
            }

            // 登録状態を本登録済みに設定（会員登録フローと同等）
            aidunite_update_user_registration_status_meta($user_id, 'accepted');
            update_user_meta($user_id, 'user_status', 0);
            update_user_meta($user_id, 'registration_date', current_time('mysql'));

            // トークンを削除（使用済み）
            $option_key = 'aidunite_invite_token_' . md5($token);
            delete_option($option_key);

            // 自動ログイン
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id);

            // 成功メッセージとリダイレクト
            wp_redirect(add_query_arg([
                'registered' => '1',
                'team_id' => $team_id
            ], home_url('/mypage')));
            exit;
        }
    }
}

get_header();
?>

<style>
.guardian-signup-container {
    background: var(--bg-light);
    min-height: 100vh;
    padding: 2rem 0;
}

.guardian-signup-card {
    max-width: 600px;
    margin: 0 auto;
    background: white;
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
}

.guardian-signup-header {
    text-align: center;
    margin-bottom: 2rem;
}

.guardian-signup-header h1 {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}

.team-info-box {
    background: linear-gradient(135deg, rgba(23, 162, 184, 0.05) 0%, rgba(23, 162, 184, 0.1) 100%);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    border-left: 4px solid var(--primary-color);
}

.team-info-box h3 {
    margin: 0 0 0.5rem 0;
    font-size: 1.1rem;
    color: var(--text-primary);
}

.team-info-box p {
    margin: 0.25rem 0;
    color: var(--text-secondary);
}

.field-error {
    color: var(--danger-color);
    font-size: 0.875rem;
    margin-top: 0.25rem;
    display: block;
}

.field-error::before {
    content: none;
}
</style>

<div class="team-dashboard-container guardian-signup-container">
    <div class="guardian-signup-card">
        <div class="guardian-signup-header">
            <h1>保護者登録</h1>
            <p>チームへの参加を完了するために、保護者情報を登録してください</p>
        </div>

        <!-- チーム情報表示 -->
        <div class="team-info-box">
            <h3><?php echo aidunite_render_theme_icon('list_alt_add', ['width' => '22', 'height' => '22'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 参加チーム情報</h3>
            <p><strong>チーム名:</strong> <?php echo esc_html($team_info['team_name'] ?? '不明'); ?></p>
            <?php if (!empty($team_info['sport_type'])): ?>
            <p><strong>競技:</strong> <?php echo esc_html($team_info['sport_type']); ?></p>
            <?php endif; ?>
        </div>

        <!-- エラーメッセージ -->
        <?php if (!empty($form_errors)): ?>
        <div class="alert alert-danger">
            <h4><?php echo aidunite_render_theme_icon('brightness_alert', ['width' => '20', 'height' => '20'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 入力エラーがあります</h4>
            <ul>
                <?php foreach ($form_errors as $error): ?>
                <li><?php echo esc_html($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- 登録フォーム -->
        <form method="post" id="guardian-signup-form">
            <?php wp_nonce_field('guardian_signup', 'guardian_signup_nonce'); ?>
            <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">
            <input type="hidden" name="team_id" value="<?php echo esc_attr($team_id); ?>">

            <div class="form-section">
                <h4>保護者情報</h4>

                <div class="form-row-2">
                    <div class="form-group">
                        <label for="parent_name_sei">姓 <span class="required">*</span></label>
                        <input type="text" id="parent_name_sei" name="parent_name_sei" class="form-control" required aria-required="true" value="<?php echo esc_attr($_POST['parent_name_sei'] ?? ''); ?>">
                        <span class="field-error" id="error_parent_name_sei" style="display: none;"></span>
                    </div>

                    <div class="form-group">
                        <label for="parent_name_mei">名 <span class="required">*</span></label>
                        <input type="text" id="parent_name_mei" name="parent_name_mei" class="form-control" required aria-required="true" value="<?php echo esc_attr($_POST['parent_name_mei'] ?? ''); ?>">
                        <span class="field-error" id="error_parent_name_mei" style="display: none;"></span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="parent_email">メールアドレス <span class="required">*</span></label>
                    <input type="email" id="parent_email" name="parent_email" class="form-control" required aria-required="true" value="<?php echo esc_attr($_POST['parent_email'] ?? $token_data['email'] ?? ''); ?>">
                    <small class="form-text text-muted">ログインIDとして使用されます</small>
                    <span class="field-error" id="error_parent_email" style="display: none;"></span>
                </div>

                <div class="form-group">
                    <label for="parent_phone">電話番号（任意）</label>
                    <input type="tel" id="parent_phone" name="parent_phone" class="form-control" value="<?php echo esc_attr($_POST['parent_phone'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-section">
                <h4>パスワード設定</h4>

                <div class="form-group">
                    <label for="password">パスワード <span class="required">*</span></label>
                    <input type="password" id="password" name="password" class="form-control" required aria-required="true" minlength="8">
                    <small class="form-text text-muted">8文字以上で入力してください</small>
                    <span class="field-error" id="error_password" style="display: none;"></span>
                </div>

                <div class="form-group">
                    <label for="password_confirm">パスワード（確認） <span class="required">*</span></label>
                    <input type="password" id="password_confirm" name="password_confirm" class="form-control" required aria-required="true" minlength="8">
                    <span class="field-error" id="error_password_confirm" style="display: none;"></span>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" id="guardian-submit-btn" style="width: 100%; margin-top: 1.5rem;">
                <?php echo aidunite_render_theme_icon('celebration', ['width' => '20', 'height' => '20'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                登録を完了する
            </button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('guardian-signup-form');
    const password = document.getElementById('password');
    const passwordConfirm = document.getElementById('password_confirm');

    // パスワード一致チェック
    function validatePasswordMatch() {
        if (password.value && passwordConfirm.value) {
            if (password.value !== passwordConfirm.value) {
                showFieldError(passwordConfirm, 'パスワードが一致しません');
                return false;
            } else {
                clearFieldError(passwordConfirm);
            }
        }
        return true;
    }

    password.addEventListener('input', validatePasswordMatch);
    passwordConfirm.addEventListener('input', validatePasswordMatch);

    // フォーム送信前のバリデーション
    form.addEventListener('submit', function(e) {
        // すべてのエラーメッセージをクリア
        document.querySelectorAll('.field-error').forEach(err => {
            err.style.display = 'none';
            err.textContent = '';
        });
        document.querySelectorAll('.form-control.error').forEach(input => {
            input.classList.remove('error');
        });

        let hasError = false;

        // 必須項目チェック
        const nameSei = document.getElementById('parent_name_sei');
        const nameMei = document.getElementById('parent_name_mei');
        const email = document.getElementById('parent_email');

        if (!nameSei || !nameSei.value.trim()) {
            showFieldError(nameSei, '姓を入力してください');
            hasError = true;
        }
        if (!nameMei || !nameMei.value.trim()) {
            showFieldError(nameMei, '名を入力してください');
            hasError = true;
        }
        if (!email || !email.value.trim()) {
            showFieldError(email, 'メールアドレスを入力してください');
            hasError = true;
        }
        if (!password || !password.value.trim() || password.value.length < 8) {
            showFieldError(password, 'パスワードは8文字以上で入力してください');
            hasError = true;
        }
        if (!validatePasswordMatch()) {
            hasError = true;
        }

        if (hasError) {
            e.preventDefault();
            return false;
        }
    });
});

function showFieldError(input, message) {
    if (!input) return;
    input.classList.add('error');
    const errorSpan = input.parentElement.querySelector('.field-error');
    if (errorSpan) {
        errorSpan.textContent = message;
        errorSpan.style.display = 'block';
    }
}

function clearFieldError(input) {
    if (!input) return;
    input.classList.remove('error');
    const errorSpan = input.parentElement.querySelector('.field-error');
    if (errorSpan) {
        errorSpan.style.display = 'none';
    }
}
</script>

<?php get_footer(); ?>
