<?php
/**
 * 共通関数
 */

require_once __DIR__ . '/app-navigation.php';
require_once __DIR__ . '/mypage-ui.php';

/*--------------------------------------------------------------
  メールアドレス正規化（全角→半角・trim・小文字）
  保存・照合の両方で使用する。入力時の半角強制はJSで実施。
--------------------------------------------------------------*/
if (!function_exists('aidunite_normalize_email')) {
function aidunite_normalize_email($email) {
    if (!is_string($email) || $email === '') {
        return sanitize_email($email);
    }
    // 全角英数字を半角に（mb_convert_kana の 'a' は英数字を半角に）
    $email = mb_convert_kana($email, 'a', 'UTF-8');
    // よくある全角記号を半角に
    $full_to_half = [
        '＠' => '@', '．' => '.', '－' => '-', '＿' => '_', '＋' => '+',
        '　' => ' '
    ];
    $email = str_replace(array_keys($full_to_half), array_values($full_to_half), $email);
    $email = trim($email);
    $email = strtolower($email);
    return sanitize_email($email);
}
}

/*--------------------------------------------------------------
  カスタムログイン処理
--------------------------------------------------------------*/
if (!function_exists('aidunite_normalize_login_display_error')) {
    /**
     * ログインエラー表示用（URL クエリに残った旧 status 表記を除去）
     */
    function aidunite_normalize_login_display_error($message) {
        $message = trim((string) $message);
        if ($message === '') {
            return '';
        }

        $message = str_replace(["\r\n", "\r"], "\n", $message);
        $message = preg_replace('/（status:\s*[^）]*）/u', '', $message);
        $message = preg_replace('/\(status:\s*[^)]*\)/i', '', $message);
        $message = preg_replace('/（user_status:\s*[^）]*）/u', '', $message);
        $message = preg_replace('/\(user_status:\s*[^)]*\)/i', '', $message);
        $message = preg_replace('/[ \t]+/u', ' ', $message);
        $message = trim($message);

        if (str_contains($message, '本登録が完了していません') && str_contains($message, 'メール認証')) {
            return "本登録が完了していません。\nメール認証を完了してください。";
        }
        if (str_contains($message, 'アカウントが無効化') && str_contains($message, '運営')) {
            return "アカウントが無効化されています。\n運営までご連絡ください。";
        }

        return $message;
    }
}

if (!function_exists('aidunite_custom_login')) {
function aidunite_custom_login($username, $password, $remember = false) {
    $username = aidunite_normalize_email($username);
    error_log('🔐 [aidunite_custom_login] ログイン試行開始 - username: ' . $username);
    $user_obj = null;

    // 1. メールアドレス形式の場合
    if (is_email($username)) {
        $user_obj = get_user_by('email', $username);
        if ($user_obj) {
            error_log('✅ [aidunite_custom_login] メールアドレスでユーザー発見 - ID: ' . $user_obj->ID . ', user_login: ' . $user_obj->user_login);
            $username = $user_obj->user_login;
        } else {
            error_log('❌ [aidunite_custom_login] メールアドレスでユーザーが見つかりません: ' . $username);
        }
    }

    // 2. user_loginで検索（ユーザー名）
    if (!$user_obj) {
        $user_obj = get_user_by('login', $username);
        if ($user_obj) {
            error_log('✅ [aidunite_custom_login] user_loginでユーザー発見 - ID: ' . $user_obj->ID);
        } else {
            error_log('❌ [aidunite_custom_login] user_loginでユーザーが見つかりません: ' . $username);
        }
    }

    // 3. ニックネーム（display_name）で検索
    if (!$user_obj) {
        $user_obj = aidunite_get_user_by_nickname($username);
        if ($user_obj) {
            error_log('✅ [aidunite_custom_login] ニックネームでユーザー発見 - ID: ' . $user_obj->ID . ', user_login: ' . $user_obj->user_login);
            $username = $user_obj->user_login;
        } else {
            error_log('❌ [aidunite_custom_login] ニックネームでユーザーが見つかりません: ' . $username);
        }
    }

    // ユーザー取得
    if (!$user_obj) {
        error_log('❌ [aidunite_custom_login] ログイン失敗: ユーザーが見つかりません - ' . $username);
        return [
            'success' => false,
            'message' => 'メールアドレスまたはパスワードが間違っています。'
        ];
    }

    if (function_exists('aidunite_admin_security_block_frontend_admin_login')) {
        $admin_block = aidunite_admin_security_block_frontend_admin_login($user_obj);
        if ($admin_block instanceof WP_Error) {
            return [
                'success' => false,
                'message' => $admin_block->get_error_message(),
            ];
        }
    }

    // ユーザー認証
    error_log('🔑 [aidunite_custom_login] パスワード認証開始 - user_login: ' . $username . ', user_id: ' . $user_obj->ID);
    $creds = [
        'user_login'    => $username,
        'user_password' => $password,
        'remember'      => $remember,
    ];
    $user = wp_signon($creds, is_ssl());

    if (is_wp_error($user)) {
        error_log('❌ [aidunite_custom_login] パスワード認証エラー - user_login: ' . $username . ' - ' . $user->get_error_message());
        if (
            function_exists('aidunite_admin_security_record_login_failure')
            && function_exists('aidunite_admin_security_is_privileged_user')
            && aidunite_admin_security_is_privileged_user($user_obj)
        ) {
            aidunite_admin_security_record_login_failure((int) $user_obj->ID);
        }
        return [
            'success' => false,
            'message' => 'メールアドレスまたはパスワードが間違っています。'
        ];
    }

    error_log('✅ [aidunite_custom_login] ユーザー認証成功 - ID: ' . $user->ID . ', user_login: ' . $user->user_login);

    // 本登録状態チェック
    $registration_status = get_user_meta($user->ID, 'registration_status', true);
    $user_status = get_user_meta($user->ID, 'user_status', true);
    error_log('📋 [aidunite_custom_login] メタ情報確認 - registration_status: "' . $registration_status . '", user_status: "' . $user_status . '"');

    if ($registration_status !== 'accepted' && $registration_status !== 'active') {
        error_log('❌ [aidunite_custom_login] 本登録未完了 - registration_status: "' . $registration_status . '"');
        return [
            'success' => false,
            'message' => "本登録が完了していません。\nメール認証を完了してください。"
        ];
    }
    if ($user_status !== '' && $user_status !== '0') {
        error_log('❌ [aidunite_custom_login] アカウント無効化 - user_status: "' . $user_status . '"');
        return [
            'success' => false,
            'message' => "アカウントが無効化されています。\n運営までご連絡ください。"
        ];
    }

    error_log('✅ [aidunite_custom_login] メタ情報チェック通過');

    // ログイン成功
    error_log('🎉 [aidunite_custom_login] ログイン成功 - ID: ' . $user->ID);
    wp_set_current_user($user->ID);
    if ($remember) {
        wp_set_auth_cookie($user->ID, true);
    } else {
        wp_set_auth_cookie($user->ID);
    }

    // リダイレクト先を決定
    $redirect_url = aidunite_get_login_redirect_url($user);
    error_log('🔗 [aidunite_custom_login] リダイレクト先: ' . $redirect_url);

    return [
        'success' => true,
        'user_id' => $user->ID,
        'redirect_url' => $redirect_url
    ];
}
}

/*--------------------------------------------------------------
  ニックネーム（display_name）でユーザー検索
--------------------------------------------------------------*/
function aidunite_get_user_by_nickname($nickname) {
    if (empty($nickname)) {
        return false;
    }

    $users = get_users([
        'meta_key' => 'nickname',
        'meta_value' => $nickname,
        'number' => 1
    ]);

    if (!empty($users)) {
        return $users[0];
    }

    // display_nameでも検索
    $users = get_users([
        'search' => $nickname,
        'search_columns' => ['display_name'],
        'number' => 1
    ]);

    return !empty($users) ? $users[0] : false;
}

/*--------------------------------------------------------------
  ニックネーム重複チェックと自動調整
--------------------------------------------------------------*/
function aidunite_generate_unique_nickname($base_nickname, $exclude_user_id = null) {
    if (empty($base_nickname)) {
        return '';
    }

    $nickname = sanitize_text_field($base_nickname);
    $counter = 1;
    $original_nickname = $nickname;

    // 重複チェック
    while (aidunite_is_nickname_taken($nickname, $exclude_user_id)) {
        $nickname = $original_nickname . '_' . $counter;
        $counter++;

        // 無限ループ防止
        if ($counter > 999) {
            $nickname = $original_nickname . '_' . time();
            break;
        }
    }

    return $nickname;
}

function aidunite_is_nickname_taken($nickname, $exclude_user_id = null) {
    if (empty($nickname)) {
        return false;
    }

    // nicknameメタフィールドで検索
    $args = [
        'meta_key' => 'nickname',
        'meta_value' => $nickname,
        'number' => 1,
        'fields' => 'ID'
    ];

    if ($exclude_user_id) {
        $args['exclude'] = [$exclude_user_id];
    }

    $users = get_users($args);
    if (!empty($users)) {
        return true;
    }

    // display_nameでも検索
    $args = [
        'search' => $nickname,
        'search_columns' => ['display_name'],
        'number' => 1,
        'fields' => 'ID'
    ];

    if ($exclude_user_id) {
        $args['exclude'] = [$exclude_user_id];
    }

    $users = get_users($args);
    return !empty($users);
}

/*--------------------------------------------------------------
  子供アカウント登録（ニックネーム重複対応強化版）
--------------------------------------------------------------*/
function aidunite_register_child_with_nickname($parent_id, $child_data) {
    if (!$parent_id || !$child_data) {
        return ['success' => false, 'message' => '必要な情報が不足しています'];
    }

    $team_id = function_exists('aidunite_get_current_team_id')
        ? (int) aidunite_get_current_team_id((int) $parent_id)
        : (int) get_user_meta($parent_id, 'team_id', true);

    // ニックネーム重複チェックと調整
    $base_nickname = $child_data['child_name'] ?? $child_data['nickname'] ?? '';
    $unique_nickname = aidunite_generate_unique_nickname($base_nickname);

    // ユーザー名生成
    $username = aidunite_generate_child_username($child_data['child_name'], $team_id);

    // メールアドレス設定（任意）
    $email = !empty($child_data['child_email'])
        ? sanitize_email($child_data['child_email'])
        : 'child_' . time() . '@temp.aidunite.local';

    $password = wp_generate_password(12, false);
    $child_user_id = wp_create_user($username, $password, $email);

    if (is_wp_error($child_user_id)) {
        return ['success' => false, 'message' => 'アカウント作成に失敗しました: ' . $child_user_id->get_error_message()];
    }

    // ユーザー情報更新
    wp_update_user([
        'ID' => $child_user_id,
        'display_name' => $child_data['child_name'],
        'nickname' => $unique_nickname,
        'first_name' => $child_data['child_name']
    ]);

    // 選手設定
    aidunite_set_user_type($child_user_id, 'player');
    aidunite_set_minor_status($child_user_id, true);
    if ($team_id) {
        aidunite_set_user_team($child_user_id, $team_id);
    }

    // 保護者と連携
    aidunite_link_parent_and_player($parent_id, $child_user_id);

    return [
        'success' => true,
        'user_id' => $child_user_id,
        'username' => $username,
        'nickname' => $unique_nickname,
        'password' => $password,
        'login_methods' => [
            'メールアドレス' => $email,
            'ユーザー名' => $username,
            'ニックネーム' => $unique_nickname
        ]
    ];
}

/*--------------------------------------------------------------
  ログイン後のリダイレクト先を決定
--------------------------------------------------------------*/
/**
 * ログイン後のリダイレクトURLを取得（統一命名）
 */
function aidunite_get_login_redirect_url($user) {
    if (!isset($user->roles)) {
        return home_url('/mypage');
    }

    if ($user instanceof WP_User && function_exists('aidunite_get_post_login_url_for_user')) {
        return aidunite_get_post_login_url_for_user($user);
    }

    $role = $user->roles[0];
    switch ($role) {
        case 'administrator':
            return admin_url();
        case 'author':
            return home_url('/mypage?login=success');
        case 'subscriber':
            return home_url('/mypage?login=success');
        default:
            return home_url('/mypage');
    }
}

/*--------------------------------------------------------------
  ログインフォームのHTML生成
--------------------------------------------------------------*/
/**
 * ログインフォームのHTMLを生成（統一命名）
 */
function aidunite_get_login_form_html($error_message = '') {
    $error_message = function_exists('aidunite_normalize_login_display_error')
        ? aidunite_normalize_login_display_error($error_message)
        : trim((string) $error_message);
    $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

    ob_start();
    ?>
    <form method="post" action="<?php echo esc_url($current_url); ?>" class="login-form">
        <?php wp_nonce_field('aidunite_login_nonce', 'aidunite_login_nonce'); ?>

        <?php if ($error_message): ?>
            <div class="login-error-message" role="alert">
                <span class="login-error-icon"><?php echo aidunite_get_theme_icon_svg('brightness_alert', ['width' => '22', 'height' => '22']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                <span class="login-error-message__text"><?php echo nl2br(esc_html($error_message), false); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
            </div>
        <?php endif; ?>

        <div class="login-form-group">
            <label for="user_email" class="login-form-label">メールアドレス</label>
            <div class="login-input-wrap">
                <span class="login-input-icon"><?php echo aidunite_get_theme_icon_svg('mail', ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                <input type="email" name="user_email" id="user_email" class="login-form-input" data-testid="login-email" placeholder="example@email.com" required autocomplete="email">
            </div>
            <p class="login-form-hint">ご登録のメールアドレスでログインしてください</p>
        </div>

        <div class="login-form-group">
            <label for="user_pass" class="login-form-label">パスワード</label>
            <div class="login-input-wrap login-input-wrap--password">
                <span class="login-input-icon"><?php echo aidunite_get_theme_icon_svg('lock', ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                <input type="password" name="user_pass" id="user_pass" class="login-form-input" data-testid="login-password" placeholder="8文字以上" required autocomplete="current-password">
                <button type="button" class="login-password-toggle" data-target="user_pass" aria-label="パスワードを表示" aria-pressed="false">
                    <span class="login-toggle-icon login-toggle-icon--show"><?php echo aidunite_get_theme_icon_svg('visibility_lock', ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    <span class="login-toggle-icon login-toggle-icon--hide" hidden><?php echo aidunite_get_theme_icon_svg('visibility', ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                </button>
            </div>
        </div>

        <div class="login-checkbox-group">
            <input type="checkbox" name="rememberme" id="rememberme" value="1">
            <label for="rememberme" class="login-checkbox-label">
                <span class="login-checkbox-custom"></span>
                ログイン状態を保持する
            </label>
        </div>

        <button type="submit" name="aidunite_login_submit" value="1" class="login-submit-btn">
            ログイン
        </button>
    </form>

    <div class="login-footer">
    <p>アカウントをお持ちでない方は、<br><a href="<?php echo esc_url(home_url('/member-register')); ?>">新規登録</a>してください。</p>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * team_memberships / managed_team_ids から表示ロールを補完（meta が general のとき）
 *
 * @param int $user_id
 * @return string team_leader|parent|player|'' 
 */
function aidunite_resolve_role_from_team_affiliation($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return '';
    }

    if (function_exists('aidunite_get_managed_team_ids')) {
        $managed = aidunite_get_managed_team_ids($user_id);
        if (!empty($managed)) {
            return 'team_leader';
        }
    }

    if (!function_exists('aidunite_user_read_team_memberships')) {
        return '';
    }

    $memberships = aidunite_user_read_team_memberships($user_id);
    if ($memberships === []) {
        return '';
    }

    $team_id = function_exists('aidunite_get_current_team_id')
        ? (int) aidunite_get_current_team_id($user_id)
        : 0;

    $resolve_membership_role = static function ($membership) {
        if (!is_array($membership)) {
            return '';
        }
        $status = (string) ($membership['status'] ?? 'active');
        if ($status !== '' && $status !== 'active') {
            return '';
        }
        $role = (string) ($membership['role'] ?? '');
        return in_array($role, ['team_leader', 'parent', 'player'], true) ? $role : '';
    };

    if ($team_id > 0) {
        $current_membership = $memberships[$team_id] ?? null;
        if ($current_membership === null) {
            foreach ($memberships as $membership) {
                if (is_array($membership) && (int) ($membership['team_id'] ?? 0) === $team_id) {
                    $current_membership = $membership;
                    break;
                }
            }
        }
        $current_role = $resolve_membership_role($current_membership);
        if ($current_role !== '') {
            return $current_role;
        }
    }

    $has_player = false;
    foreach ($memberships as $membership) {
        $role = $resolve_membership_role($membership);
        if ($role === 'team_leader') {
            return 'team_leader';
        }
        if ($role === 'parent') {
            return 'parent';
        }
        if ($role === 'player') {
            $has_player = true;
        }
    }

    return $has_player ? 'player' : '';
}

/**
 * 管理者なら ?mode=xxx / cookie を優先してロールを"そのまま適用"
 * 返り値: [ $effective_role, $preview_mode ]  ※$preview_modeは true/false
 */
function aidunite_get_effective_user_role() {
    $current_user_id = get_current_user_id();
    $wp_user = wp_get_current_user();

    // 既存メタ
    $aidunite_role = get_user_meta($current_user_id, 'aidunite_role', true);
    $user_type_meta = get_user_meta($current_user_id, 'user_type', true);
    $allowed = ['team_leader','parent','player','supporter','match','public','general','administrator','developer'];

    // デフォルト（一般ユーザー相当）
    $role = (in_array($aidunite_role, $allowed, true)) ? $aidunite_role : 'general';
    $preview = false;

    // --- 管理者の簡易プレビュー（= impersonate） ---
    // 統一認証・権限チェック（管理者のみ）
    require_once get_template_directory() . '/functions/common/auth-middleware.php';
    $admin_result = AidUniteAuthMiddleware::require_admin(false);
    if ( $admin_result->is_valid() ) {
        // 1) URL ?mode=xxx が来たら最優先＆Cookieに保存（2時間）
        if ( isset($_GET['mode']) && in_array($_GET['mode'], $allowed, true) ) {
            // 管理者の場合はnonce検証をスキップ（簡単にプレビューモードを切り替えられるように）
            $role = sanitize_text_field($_GET['mode']);
            $preview = true;
            // 2h
            setcookie('aid_preview_mode', $role, time() + 2 * HOUR_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);
            $_COOKIE['aid_preview_mode'] = $role; // 同一リクエストでの参照用
        }
        // 2) URLがなくてもCookieがあれば継続
        elseif ( !empty($_COOKIE['aid_preview_mode']) && in_array($_COOKIE['aid_preview_mode'], $allowed, true) ) {
            $role = sanitize_text_field($_COOKIE['aid_preview_mode']);
            $preview = true;
        }

        // 解除: ?mode=off または ?mode=
        if ( isset($_GET['mode']) && ($_GET['mode'] === 'off' || $_GET['mode'] === '') ) {
            // 管理者の場合はnonce検証をスキップ
            setcookie('aid_preview_mode', '', time() - 3600, COOKIEPATH ?: '/', COOKIE_DOMAIN);
            unset($_COOKIE['aid_preview_mode']);
            $preview = false;
            // 管理者自身の通常ロールに戻す
            $role = (in_array($aidunite_role, $allowed, true)) ? $aidunite_role : 'general';
        }
    }

    if (!$preview) {
        if (in_array($role, ['general', 'public', ''], true)) {
            $affiliation_role = aidunite_resolve_role_from_team_affiliation($current_user_id);
            if ($affiliation_role !== '') {
                $role = $affiliation_role;
            } elseif (
                $user_type_meta !== ''
                && in_array($user_type_meta, $allowed, true)
                && !in_array($user_type_meta, ['general', 'public'], true)
            ) {
                $role = $user_type_meta;
            }
        }
    }

    return [$role, $preview];
}

/**
 * modeパラメータを自動付加するURL生成ヘルパー
 * 外部サイトやmode不要なリンクには付与しない
 * @param string $url
 * @param bool $force_add デフォルトfalse。trueなら強制付加
 * @return string
 */
function aidunite_add_mode_to_url($url, $force_add = false) {
    list($user_role, $preview_mode) = aidunite_get_effective_user_role();
    if ($preview_mode) {
        $parsed_url = parse_url($url);
        // 外部サイト判定
        $site_host = parse_url(home_url(), PHP_URL_HOST);
        if (isset($parsed_url['host']) && $parsed_url['host'] !== $site_host && !$force_add) {
            return $url; // 外部サイトは付与しない
        }
        // アンカーリンクやjavascript:等も除外
        if (isset($parsed_url['scheme']) && in_array($parsed_url['scheme'], ['mailto', 'tel', 'javascript'])) {
            return $url;
        }
        // すでにmodeが付与されている場合は上書き
        $query = [];
        if (isset($parsed_url['query'])) {
            parse_str($parsed_url['query'], $query);
        }
        $query['mode'] = $preview_mode;
        $scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
        $host     = isset($parsed_url['host']) ? $parsed_url['host'] : '';
        $port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
        $path     = isset($parsed_url['path']) ? $parsed_url['path'] : '';
        $querystr = http_build_query($query);
        $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
        return $scheme . $host . $port . $path . '?' . $querystr . $fragment;
    }
    return $url;
}



/**
 * mode解除リンク生成
 * 現在のURLからmodeパラメータを除去
 * @return string
 */
function aidunite_remove_mode_from_current_url() {
    return esc_url(remove_query_arg('mode'));
}

/**
 * ユーザータイプを設定
 * @param int $user_id ユーザーID
 * @param string $user_type ユーザータイプ（team_leader, parent, player, supporter, match, public, general, administrator）
 * @return bool
 */
if (!function_exists('aidunite_set_user_type')) {
function aidunite_set_user_type($user_id, $user_type) {
    if (!$user_id || !$user_type) {
        return false;
    }

    $allowed_types = ['team_leader', 'parent', 'player', 'supporter', 'match', 'public', 'general', 'administrator'];
    if (!in_array($user_type, $allowed_types, true)) {
        return false;
    }

    if (function_exists('aidunite_user_write_role_meta')) {
        return aidunite_user_write_role_meta($user_id, $user_type) !== '';
    }

    update_user_meta($user_id, 'aidunite_role', $user_type);
    update_user_meta($user_id, 'user_type', $user_type);

    return true;
}
}

/**
 * ユーザーがチーム代表者かどうかをチェック
 * @param int $user_id ユーザーID
 * @return bool
 */
if (!function_exists('aidunite_is_team_leader')) {
function aidunite_is_team_leader($user_id) {
    if (!$user_id) {
        return false;
    }

    $user_type = get_user_meta($user_id, 'aidunite_role', true);
    if (empty($user_type)) {
        // 後方互換性のため user_type もチェック
        $user_type = get_user_meta($user_id, 'user_type', true);
    }

    return $user_type === 'team_leader';
}
}

/**
 * ユーザーにチームを設定
 * @param int $user_id ユーザーID
 * @param int $team_id チームID
 * @return bool
 */
if (!function_exists('aidunite_set_user_team')) {
function aidunite_set_user_team($user_id, $team_id) {
    if (!$user_id || !$team_id) {
        return false;
    }

    update_user_meta($user_id, 'team_id', $team_id);
    return true;
}
}

/**
 * 未成年ステータスを設定
 * @param int $user_id ユーザーID
 * @param bool $is_minor 未成年かどうか
 * @return bool
 */
if (!function_exists('aidunite_set_minor_status')) {
function aidunite_set_minor_status($user_id, $is_minor) {
    if (!$user_id) {
        return false;
    }

    update_user_meta($user_id, 'is_minor', $is_minor ? '1' : '0');
    return true;
}
}

/**
 * 選手から保護者を取得
 * @param int $player_id 選手のユーザーID
 * @return WP_User|null 保護者のユーザーオブジェクト、見つからない場合はnull
 */
if (!function_exists('aidunite_get_player_parent')) {
function aidunite_get_player_parent($player_id) {
    if (!$player_id) return null;

    // 方法0: linked_parent_id（親子紐付けの正本）
    $linked_parent_id = function_exists('aidunite_player_read_linked_parent_id')
        ? aidunite_player_read_linked_parent_id($player_id)
        : (int) get_user_meta($player_id, 'linked_parent_id', true);
    if ($linked_parent_id > 0) {
        $parent = get_userdata($linked_parent_id);
        if ($parent) {
            $parent_role = get_user_meta($linked_parent_id, 'aidunite_role', true) ?: get_user_meta($linked_parent_id, 'user_type', true);
            if ($parent_role === 'parent' || $parent_role === '' || $parent_role === 'general') {
                return $parent;
            }
        }
    }

    // 方法1: ユーザーメタにparent_idが保存されている場合
    $parent_id = get_user_meta($player_id, 'parent_id', true);
    if ($parent_id) {
        $parent = get_userdata($parent_id);
        if ($parent) {
            $parent_role = get_user_meta($parent_id, 'aidunite_role', true) ?: get_user_meta($parent_id, 'user_type', true);
            if ($parent_role === 'parent') {
                return $parent;
            }
        }
    }

    // 方法2: parent_emailから保護者を検索
    $parent_email = get_user_meta($player_id, 'parent_email', true);
    if ($parent_email) {
        $parent_user = get_user_by('email', $parent_email);
        if ($parent_user) {
            $parent_role = get_user_meta($parent_user->ID, 'aidunite_role', true) ?: get_user_meta($parent_user->ID, 'user_type', true);
            if ($parent_role === 'parent') {
                return $parent_user;
            }
        }
    }

    return null;
}
}

/**
 * システム開発者のプレビューモードかどうかをチェック
 * @return bool
 */
function aidunite_is_developer_preview_mode() {
    // システム開発者のプレビューモードでのみ実行可能
    if (isset($_GET['mode']) && $_GET['mode'] === 'developer') {
        return true;
    }

    // プレビューモードの確認
    list($user_role, $preview_mode) = aidunite_get_effective_user_role();
    return $preview_mode === 'developer';
}

/**
 * 確認モードバナー表示
 * @param string|null $preview_mode aidunite_get_effective_user_role() の第2戻り値
 */
function aidunite_enqueue_preview_mode_banner_assets($base_url = '') {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $css = get_stylesheet_directory() . '/assets/css/components/preview-mode-banner.css';
    if (is_readable($css)) {
        wp_enqueue_style(
            'aidunite-preview-mode-banner',
            get_stylesheet_directory_uri() . '/assets/css/components/preview-mode-banner.css',
            ['aidunite-style'],
            (string) filemtime($css)
        );
    }
    $js = get_stylesheet_directory() . '/assets/js/common/preview-mode-banner.js';
    if (is_readable($js)) {
        wp_enqueue_script(
            'aidunite-preview-mode-banner',
            get_stylesheet_directory_uri() . '/assets/js/common/preview-mode-banner.js',
            [],
            (string) filemtime($js),
            true
        );
        if ($base_url !== '') {
            wp_localize_script('aidunite-preview-mode-banner', 'aidunitePreviewModeBanner', [
                'baseUrl' => $base_url,
            ]);
        }
    }
}

function aidunite_preview_mode_banner($preview_mode) {
    // 統一認証・権限チェック（管理者のみ）
    require_once get_template_directory() . '/functions/common/auth-middleware.php';
    $admin_result = AidUniteAuthMiddleware::require_admin(false);
    if ($admin_result->is_valid()) {
        $allowed_roles = ['team_leader', 'parent', 'player', 'supporter', 'match', 'public', 'general', 'administrator', 'developer'];
        $role_labels = [
            'team_leader'   => '👑 チーム代表者',
            'parent'        => '👨‍👩‍👧‍👦 保護者',
            'player'        => '🏃‍♂️ 選手',
            'supporter'     => '💝 支援者',
            'match'         => '🤝 マッチ担当',
            'public'        => '🌐 一般ユーザー（公開）',
            'general'       => '🧑 一般ユーザー',
            'administrator' => '⚙️ 管理者',
            'developer'     => '👨‍💻 システム開発者',
        ];

        $current_url = (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $base_url = remove_query_arg('mode', $current_url);
        // 解除時は ?mode=off 必須（PHP側で Cookie を削除するため）
        $off_url = add_query_arg('mode', 'off', $base_url);

        list($base_role, $current_preview_mode) = aidunite_get_effective_user_role();
        $base_role_label = $role_labels[$base_role] ?? $base_role;
        $current_label = $preview_mode ? ($role_labels[$preview_mode] ?? $preview_mode) : '';

        aidunite_enqueue_preview_mode_banner_assets($base_url);
        echo '<div class="preview-mode-banner" id="preview-mode-banner" role="region" aria-label="プレビューモード">';
        echo '<div class="preview-mode-banner__header" id="preview-mode-banner-toggle" tabindex="0" role="button" aria-expanded="true" aria-controls="preview-mode-banner-body">';
        echo '<span aria-hidden="true">🔍 プレビューモード</span>';
        if ($current_label) {
            echo ' <span class="preview-mode-banner__badge">' . esc_html($current_label) . '</span>';
        }
        echo ' <span class="preview-mode-banner__toggle" id="preview-mode-banner-toggle-label">▼ 折りたたむ</span>';
        echo '</div>';
        echo '<div class="preview-mode-banner__body" id="preview-mode-banner-body">';
        echo '<div class="preview-mode-banner__inner">';
        echo '<div class="preview-mode-banner__meta">';
        echo '基本ロール: <span class="preview-mode-banner__role-pill">' . esc_html($base_role_label) . '</span>';
        echo '</div>';

        if ($preview_mode) {
            echo '<div class="preview-mode-banner__current">';
            echo '現在 <strong>' . esc_html($role_labels[$preview_mode] ?? $preview_mode) . '</strong> としてプレビュー中';
            echo '</div>';
        }

        echo '<form class="preview-mode-banner__form" id="preview-mode-form">';
        echo '<select id="preview-mode-select" name="mode" class="preview-mode-banner__select">';
        echo '<option value="">▼ ロールを選択</option>';
        foreach ($allowed_roles as $role) {
            $selected = ($preview_mode === $role) ? 'selected' : '';
            $label = $role_labels[$role] ?? $role;
            $escaped_role = esc_attr($role);
            $escaped_label = esc_html($label);
            echo "<option value=\"{$escaped_role}\" {$selected}>{$escaped_label}</option>";
        }
        echo '</select>';
        echo '</form>';

        if ($preview_mode) {
            echo ' <a href="' . esc_url($off_url) . '" class="preview-mode-banner__off-link">プレビューモード解除</a>';
            echo '<div class="preview-mode-banner__hint">';
            echo '⚠️ プレビューモード解除後は基本ロール（' . esc_html($base_role_label) . '）に戻ります';
            echo '</div>';
        }

        echo '<div class="preview-mode-banner__hint">';
        echo '💡 各ロールの機能をテストできます。すべてのリンクに自動的にプレビューモードが適用されます。';
        echo '</div>';
        echo '</div></div></div>';
    } else if ($preview_mode) {
        // 管理者以外は従来通り（解除時も ?mode=off で Cookie を消す）。折りたたみ対応
        $current_url = (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $off_url = add_query_arg('mode', 'off', remove_query_arg('mode', $current_url));
        aidunite_enqueue_preview_mode_banner_assets();
        echo '<div class="preview-mode-banner preview-mode-banner--simple" id="preview-mode-banner" role="region" aria-label="プレビューモード">';
        echo '<div class="preview-mode-banner__header preview-mode-banner__header--gradient" id="preview-mode-banner-toggle" tabindex="0" role="button" aria-expanded="true" aria-controls="preview-mode-banner-body">';
        echo '<span>🔍 プレビューモード</span> <span class="preview-mode-banner__badge">' . esc_html($preview_mode) . '</span> でプレビュー中';
        echo ' <span class="preview-mode-banner__toggle" id="preview-mode-banner-toggle-label">▼ 折りたたむ</span>';
        echo '</div>';
        echo '<div class="preview-mode-banner__body" id="preview-mode-banner-body">';
        echo '<div class="preview-mode-banner__inner preview-mode-banner__inner--gradient">';
        echo ' <a href="' . esc_url($off_url) . '" class="preview-mode-banner__off-link">プレビューモード解除</a>';
        echo '</div></div></div>';
    }
}

/*--------------------------------------------------------------
  スポーツ種目別試合結果フォーマット定義
--------------------------------------------------------------*/
function aidunite_get_sport_result_format($sport_type) {
    $formats = [
        'サッカー' => [
            'name' => 'サッカー',
            'icon' => '⚽',
            'score_fields' => [
                'our_score' => ['label' => '自チーム得点', 'type' => 'number', 'min' => 0, 'max' => 99],
                'opponent_score' => ['label' => '相手得点', 'type' => 'number', 'min' => 0, 'max' => 99]
            ],
            'player_stats' => [
                'goals' => ['label' => '得点', 'type' => 'number', 'min' => 0, 'max' => 10],
                'assists' => ['label' => 'アシスト', 'type' => 'number', 'min' => 0, 'max' => 10],
                'yellow_cards' => ['label' => 'イエローカード', 'type' => 'number', 'min' => 0, 'max' => 2],
                'red_cards' => ['label' => 'レッドカード', 'type' => 'number', 'min' => 0, 'max' => 1],
                'saves' => ['label' => 'セーブ（GK）', 'type' => 'number', 'min' => 0, 'max' => 50]
            ],
            'match_duration' => '90分',
            'periods' => ['前半', '後半']
        ],
        'バスケットボール' => [
            'name' => 'バスケットボール',
            'icon' => '🏀',
            'score_fields' => [
                'our_score' => ['label' => '自チーム得点', 'type' => 'number', 'min' => 0, 'max' => 200],
                'opponent_score' => ['label' => '相手得点', 'type' => 'number', 'min' => 0, 'max' => 200]
            ],
            'player_stats' => [
                'points' => ['label' => '得点', 'type' => 'number', 'min' => 0, 'max' => 100],
                'rebounds' => ['label' => 'リバウンド', 'type' => 'number', 'min' => 0, 'max' => 30],
                'assists' => ['label' => 'アシスト', 'type' => 'number', 'min' => 0, 'max' => 20],
                'steals' => ['label' => 'スティール', 'type' => 'number', 'min' => 0, 'max' => 10],
                'blocks' => ['label' => 'ブロック', 'type' => 'number', 'min' => 0, 'max' => 10],
                'fouls' => ['label' => 'ファウル', 'type' => 'number', 'min' => 0, 'max' => 5]
            ],
            'match_duration' => '40分（4Q制）',
            'periods' => ['第1Q', '第2Q', '第3Q', '第4Q']
        ],
        '野球' => [
            'name' => '野球',
            'icon' => '⚾',
            'score_fields' => [
                'our_score' => ['label' => '自チーム得点', 'type' => 'number', 'min' => 0, 'max' => 50],
                'opponent_score' => ['label' => '相手得点', 'type' => 'number', 'min' => 0, 'max' => 50]
            ],
            'player_stats' => [
                'hits' => ['label' => '安打', 'type' => 'number', 'min' => 0, 'max' => 10],
                'runs' => ['label' => '得点', 'type' => 'number', 'min' => 0, 'max' => 10],
                'rbis' => ['label' => '打点', 'type' => 'number', 'min' => 0, 'max' => 10],
                'stolen_bases' => ['label' => '盗塁', 'type' => 'number', 'min' => 0, 'max' => 5],
                'strikeouts' => ['label' => '三振', 'type' => 'number', 'min' => 0, 'max' => 10],
                'walks' => ['label' => '四球', 'type' => 'number', 'min' => 0, 'max' => 5]
            ],
            'match_duration' => '9回制',
            'periods' => ['1回', '2回', '3回', '4回', '5回', '6回', '7回', '8回', '9回']
        ],
        'テニス' => [
            'name' => 'テニス',
            'icon' => '🎾',
            'score_fields' => [
                'our_sets' => ['label' => '自チームセット', 'type' => 'number', 'min' => 0, 'max' => 5],
                'opponent_sets' => ['label' => '相手セット', 'type' => 'number', 'min' => 0, 'max' => 5]
            ],
            'player_stats' => [
                'aces' => ['label' => 'エース', 'type' => 'number', 'min' => 0, 'max' => 20],
                'double_faults' => ['label' => 'ダブルフォルト', 'type' => 'number', 'min' => 0, 'max' => 10],
                'winners' => ['label' => 'ウィナー', 'type' => 'number', 'min' => 0, 'max' => 30],
                'unforced_errors' => ['label' => 'アンフォーストエラー', 'type' => 'number', 'min' => 0, 'max' => 20],
                'break_points' => ['label' => 'ブレークポイント', 'type' => 'number', 'min' => 0, 'max' => 10]
            ],
            'match_duration' => '3セット制',
            'periods' => ['1セット', '2セット', '3セット']
        ],
        'バレーボール' => [
            'name' => 'バレーボール',
            'icon' => '🏐',
            'score_fields' => [
                'our_sets' => ['label' => '自チームセット', 'type' => 'number', 'min' => 0, 'max' => 5],
                'opponent_sets' => ['label' => '相手セット', 'type' => 'number', 'min' => 0, 'max' => 5]
            ],
            'player_stats' => [
                'points' => ['label' => '得点', 'type' => 'number', 'min' => 0, 'max' => 50],
                'spikes' => ['label' => 'スパイク', 'type' => 'number', 'min' => 0, 'max' => 30],
                'blocks' => ['label' => 'ブロック', 'type' => 'number', 'min' => 0, 'max' => 10],
                'serves' => ['label' => 'サーブ', 'type' => 'number', 'min' => 0, 'max' => 20],
                'receives' => ['label' => 'レシーブ', 'type' => 'number', 'min' => 0, 'max' => 30],
                'sets' => ['label' => 'セット', 'type' => 'number', 'min' => 0, 'max' => 20]
            ],
            'match_duration' => '5セット制',
            'periods' => ['1セット', '2セット', '3セット', '4セット', '5セット']
        ],
        'その他' => [
            'name' => 'その他',
            'icon' => '🏆',
            'score_fields' => [
                'our_score' => ['label' => '自チーム得点', 'type' => 'number', 'min' => 0, 'max' => 999],
                'opponent_score' => ['label' => '相手得点', 'type' => 'number', 'min' => 0, 'max' => 999]
            ],
            'player_stats' => [
                'goals' => ['label' => '得点', 'type' => 'number', 'min' => 0, 'max' => 50],
                'assists' => ['label' => 'アシスト', 'type' => 'number', 'min' => 0, 'max' => 20],
                'yellow_cards' => ['label' => 'イエローカード', 'type' => 'number', 'min' => 0, 'max' => 5],
                'red_cards' => ['label' => 'レッドカード', 'type' => 'number', 'min' => 0, 'max' => 2]
            ],
            'match_duration' => '試合時間',
            'periods' => ['前半', '後半']
        ]
    ];

    return isset($formats[$sport_type]) ? $formats[$sport_type] : $formats['その他'];
}

/*--------------------------------------------------------------
  スポーツ種目別試合結果フォーマットHTML生成
--------------------------------------------------------------*/
function aidunite_generate_sport_result_form($sport_type, $team_id) {
    $format = aidunite_get_sport_result_format($sport_type);
    $html = '';

    // スコア入力フィールド
    $html .= '<div class="score-section">';
    $html .= '<h3>' . $format['icon'] . ' スコア</h3>';
    $html .= '<div class="score-inputs">';

    foreach ($format['score_fields'] as $field_name => $field_config) {
        $html .= '<div class="score-group">';
        $html .= '<label for="' . $field_name . '">' . $field_config['label'] . ' *</label>';
        $html .= '<input type="' . $field_config['type'] . '" id="' . $field_name . '" name="' . $field_name . '" required class="form-input score-input" min="' . $field_config['min'] . '" max="' . $field_config['max'] . '">';
        $html .= '</div>';

        if ($field_name !== array_key_last($format['score_fields'])) {
            $html .= '<div class="score-separator">-</div>';
        }
    }

    $html .= '</div>';
    $html .= '<div class="result-display" id="resultDisplay">';
    $html .= '<div class="result-text" id="resultText">結果がここに表示されます</div>';
    $html .= '</div>';
    $html .= '</div>';

    // 選手別記録セクション
    $html .= '<div class="player-stats-section">';
    $html .= '<h3>👥 選手別記録</h3>';
    $html .= '<p class="section-description">主要な選手の記録を入力できます（任意）</p>';
    $html .= '<div id="playerStatsContainer">';
    $html .= '<div class="player-stat-item">';
    $html .= '<div class="player-stat-header">';
    $html .= '<input type="text" name="player_names[]" class="form-input player-name" placeholder="選手名">';
    $html .= '<button type="button" class="remove-player-btn" onclick="removePlayerStat(this)">🗑️</button>';
    $html .= '</div>';
    $html .= '<div class="player-stat-inputs">';

    foreach ($format['player_stats'] as $stat_name => $stat_config) {
        $html .= '<div class="stat-input">';
        $html .= '<label>' . $stat_config['label'] . '</label>';
        $html .= '<input type="' . $stat_config['type'] . '" name="player_' . $stat_name . '[]" class="form-input stat-input-field" min="' . $stat_config['min'] . '" max="' . $stat_config['max'] . '">';
        $html .= '</div>';
    }

    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<button type="button" class="add-player-btn" onclick="addPlayerStat(\'' . $sport_type . '\')">';
    $html .= '<span>➕</span> ' . $format['name'] . '選手を追加';
    $html .= '</button>';
    $html .= '</div>';

    return $html;
}

/*--------------------------------------------------------------
  スポーツ種目別選手記録追加HTML生成
--------------------------------------------------------------*/
function aidunite_generate_player_stat_html($sport_type) {
    $format = aidunite_get_sport_result_format($sport_type);
    $html = '';

    $html .= '<div class="player-stat-header">';
    $html .= '<input type="text" name="player_names[]" class="form-input player-name" placeholder="選手名">';
    $html .= '<button type="button" class="remove-player-btn" onclick="removePlayerStat(this)">🗑️</button>';
    $html .= '</div>';
    $html .= '<div class="player-stat-inputs">';

    foreach ($format['player_stats'] as $stat_name => $stat_config) {
        $html .= '<div class="stat-input">';
        $html .= '<label>' . $stat_config['label'] . '</label>';
        $html .= '<input type="' . $stat_config['type'] . '" name="player_' . $stat_name . '[]" class="form-input stat-input-field" min="' . $stat_config['min'] . '" max="' . $stat_config['max'] . '">';
        $html .= '</div>';
    }

    $html .= '</div>';

    return $html;
}

/*--------------------------------------------------------------
  スケジュール関連REST APIエンドポイント登録
--------------------------------------------------------------*/
function aidunite_register_schedule_rest_routes() {
    // 期間指定でスケジュール取得（期間指定なしでも取得可能）
    register_rest_route('aidunite/v1', '/get-user-schedules', [
        'methods' => 'GET',
        'callback' => 'aidunite_rest_get_user_schedules',
        'permission_callback' => function() {
            require_once get_template_directory() . '/functions/common/auth-middleware.php';
            $auth_result = AidUniteAuthMiddleware::require_auth(false);
            return $auth_result->is_valid();
        },
        'args' => [
            'start' => [
                'required' => false,
                'validate_callback' => function($param) {
                    return empty($param) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $param);
                }
            ],
            'end' => [
                'required' => false,
                'validate_callback' => function($param) {
                    return empty($param) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $param);
                }
            ],
            'scope' => [
                'required' => false,
                'validate_callback' => function($param) {
                    return empty($param) || in_array(strtolower((string) $param), ['operating', 'managed'], true);
                }
            ],
            'team_id' => [
                'required' => false,
                'validate_callback' => function($param) {
                    return $param === '' || $param === null || (is_numeric($param) && (int) $param >= 0);
                }
            ],
        ]
    ]);

    // 指定日のスケジュール取得
    register_rest_route('aidunite/v1', '/get-schedules-by-date', [
        'methods' => 'POST',
        'callback' => 'aidunite_rest_get_schedules_by_date',
        'permission_callback' => function() {
            require_once get_template_directory() . '/functions/common/auth-middleware.php';
            $auth_result = AidUniteAuthMiddleware::require_auth(false);
            return $auth_result->is_valid();
        },
        'args' => [
            'date' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $param);
                }
            ]
        ]
    ]);

    // 要対応アイテム取得（MYPAGE-REDESIGN-PROPOSAL.md に基づく実装）
    register_rest_route('aidunite/v1', '/get-action-required-items', [
        'methods' => 'GET',
        'callback' => 'aidunite_rest_get_action_required_items',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);

}
add_action('rest_api_init', 'aidunite_register_schedule_rest_routes');

/**
 * REST API: 期間指定でスケジュール取得（期間指定なしでも取得可能）
 */
function aidunite_rest_get_user_schedules($request) {
    require_once get_template_directory() . '/functions/common/auth-middleware.php';
    $auth_result = AidUniteAuthMiddleware::rest_require($request, []);
    if (is_wp_error($auth_result)) {
        return $auth_result;
    }

    $start_date = $request->get_param('start');
    $end_date = $request->get_param('end');
    $user_id = $auth_result->user_id;
    $fetch_args = [];
    $scope = $request->get_param('scope');
    if ($scope !== null && $scope !== '') {
        $fetch_args['scope'] = strtolower((string) $scope);
    }
    $team_id = $request->get_param('team_id');
    if ($team_id !== null && $team_id !== '' && (int) $team_id > 0) {
        $fetch_args['team_id'] = (int) $team_id;
    }

    // 期間指定がない場合は全期間を取得
    if (empty($start_date) || empty($end_date)) {
        $schedules = aidunite_get_all_user_schedules($user_id, $fetch_args);
    } else {
        $schedules = aidunite_get_user_schedules_by_date_range($start_date, $end_date, $user_id, $fetch_args);
    }

    $dissolution_markers = [];
    if (function_exists('aidunite_payment_exit_collect_dissolution_markers_for_user')
        && !empty($start_date) && !empty($end_date)) {
        $dissolution_markers = aidunite_payment_exit_collect_dissolution_markers_for_user(
            $user_id,
            (string) $start_date,
            (string) $end_date,
            $fetch_args
        );
    }

    return rest_ensure_response([
        'success' => true,
        'data' => $schedules,
        'count' => count($schedules),
        'dissolution_markers' => $dissolution_markers,
    ]);
}

/**
 * REST API: 指定日のスケジュール取得
 */
function aidunite_rest_get_schedules_by_date($request) {
    require_once get_template_directory() . '/functions/common/auth-middleware.php';
    $auth_result = AidUniteAuthMiddleware::rest_require($request, []);
    if (is_wp_error($auth_result)) {
        return $auth_result;
    }

    $date = $request->get_param('date');
    $user_id = $auth_result->user_id;

    $schedules = aidunite_get_schedules_by_date($date, $user_id);

    return rest_ensure_response([
        'success' => true,
        'data' => $schedules,
        'count' => count($schedules)
    ]);
}

/**
 * ロールごとに「やること」へ出してよい要対応タイプか
 *
 * @param string $type
 * @param string $effective_role
 * @param int    $user_id
 */
function aidunite_action_required_item_allowed_for_role($type, $effective_role, $user_id = 0) {
    $type = (string) $type;
    $effective_role = (string) $effective_role;
    $user_id = (int) $user_id;

    if (in_array($effective_role, ['team_leader', 'administrator'], true)) {
        return true;
    }

    if ($effective_role === 'parent') {
        return in_array($type, ['attendance', 'payment', 'tuition_payment'], true);
    }

    if ($effective_role === 'player') {
        if ($type === 'payment'
            && $user_id > 0
            && function_exists('aidunite_user_is_high_school_grade_or_below')
            && aidunite_user_is_high_school_grade_or_below($user_id)) {
            return false;
        }

        return in_array($type, ['attendance', 'payment', 'tuition_payment'], true);
    }

    return false;
}

/**
 * 要対応アイテムを取得（REST・マイページ初期表示の両方で利用）
 * Inbox型通知システム用。ログイン済みユーザーの要対応リストを返す。
 *
 * @return array 要対応アイテムの配列（期限切れ除外・緊急度・ソート済み）
 */
function aidunite_get_action_required_items_for_display() {
    $user_id = get_current_user_id();
    $team_scope = function_exists('aidunite_get_managed_team_ids') ? aidunite_get_managed_team_ids($user_id) : [];
    if (empty($team_scope)) {
        $legacy = (int) get_user_meta($user_id, 'team_id', true);
        if ($legacy > 0) {
            $team_scope = [$legacy];
        }
    }
    list($effective_role, $preview_mode) = aidunite_get_effective_user_role();

    $action_items = [];

    // 0. 保護者承認待ち（チーム代表者のみ）
    if ($effective_role === 'team_leader' && !empty($team_scope) && function_exists('aidunite_parent_read_pending_approval_count')) {
        foreach ($team_scope as $scope_tid) {
            $scope_tid = (int) $scope_tid;
            if ($scope_tid <= 0) {
                continue;
            }
            $pending_count = aidunite_parent_read_pending_approval_count($scope_tid);
            if ($pending_count <= 0) {
                continue;
            }
            $team_label = '';
            if (function_exists('aidunite_team_get_display_bundle')) {
                $bundle = aidunite_team_get_display_bundle($scope_tid);
                $team_label = (string) ($bundle['team_name'] ?? '');
            }
            $action_items[] = [
                'type' => 'parent_approval',
                'id' => $scope_tid,
                'title' => '保護者の承認待ち',
                'description' => ($team_label !== '' ? $team_label . ' — ' : '') . $pending_count . '件の参加申請があります',
                'deadline' => null,
                'link_url' => home_url('/team-members?list_tab=parents&parent_tab=pending&team_id=' . $scope_tid),
                'actions' => [
                    ['label' => '承認する', 'action' => 'open', 'type' => 'primary'],
                ],
            ];
        }
    }

    // 0b. 大会・イベント招待への回答（チーム代表者のみ）
    if ($effective_role === 'team_leader' && !empty($team_scope) && function_exists('aidunite_competition_read_action_required_items')) {
        $competition_items = aidunite_competition_read_action_required_items($user_id, $team_scope, $effective_role);
        if (!empty($competition_items)) {
            $action_items = array_merge($action_items, $competition_items);
        }
    }

    // 0c. ファネル離脱向けオンボーディング / reuse（代表者）
    if ($effective_role === 'team_leader' && !empty($team_scope) && function_exists('aidunite_funnel_read_leader_action_items')) {
        $funnel_items = aidunite_funnel_read_leader_action_items($user_id, $team_scope);
        if ($funnel_items !== []) {
            $action_items = array_merge($funnel_items, $action_items);
        }
    }

    // 1. マッチ申請への回答が必要なアイテム（チーム責任者のみ）
    if ($effective_role === 'team_leader' && !empty($team_scope)) {
        foreach ($team_scope as $scope_tid) {
            $scope_tid = (int) $scope_tid;
            if ($scope_tid > 0
                && function_exists('aidunite_activation_is_mission_ui')
                && aidunite_activation_is_mission_ui($scope_tid)
                && function_exists('aidunite_onboarding_bot_ensure_for_team')) {
                aidunite_onboarding_bot_ensure_for_team($scope_tid);
            }
        }

        $match_requests = get_posts([
            'post_type' => 'match_request',
            'post_status' => 'publish',
            'posts_per_page' => 10,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => [
                [
                    'key' => 'status',
                    'value' => ['publish', 'pending', ''],
                    'compare' => 'IN'
                ]
            ]
        ]);

        foreach ($match_requests as $match_req) {
            $to_schedule_id = get_post_meta($match_req->ID, 'to_schedule_id', true);
            if (!$to_schedule_id) continue;

            $schedule_author_id = get_post_field('post_author', $to_schedule_id);
            $schedule_team_id = function_exists('aidunite_resolve_schedule_owner_team_id')
                ? (int) aidunite_resolve_schedule_owner_team_id((int) $to_schedule_id)
                : (function_exists('aidunite_schedule_read_team_id')
                    ? (int) aidunite_schedule_read_team_id((int) $to_schedule_id)
                    : 0);
            if ($schedule_team_id <= 0 && $schedule_author_id) {
                $schedule_team_id = function_exists('aidunite_get_current_team_id')
                    ? (int) aidunite_get_current_team_id((int) $schedule_author_id)
                    : (int) get_user_meta((int) $schedule_author_id, 'team_id', true);
            }

            // 自分のチーム（managed）への申請のみ
            if (in_array($schedule_team_id, array_map('intval', $team_scope), true)) {
                $from_team_id = get_post_meta($match_req->ID, 'from_team_id', true);
                $from_team = $from_team_id ? get_post($from_team_id) : null;
                $match_date = get_post_meta($match_req->ID, 'match_date', true);
                if (!$match_date && function_exists('aidunite_schedule_read_normalized_date')) {
                    $match_date = aidunite_schedule_read_normalized_date((int) $to_schedule_id);
                }
                $place = function_exists('aidunite_schedule_read_place_raw')
                    ? aidunite_schedule_read_place_raw((int) $to_schedule_id)
                    : '';

                // マッチ申請の返答期限（キャンセルポリシー §17.7 整合: 試合3日前23:59）
                $deadline = null;
                if ($match_date && function_exists('aidunite_match_policy_get_response_deadline')) {
                    $deadline = aidunite_match_policy_get_response_deadline($match_date);
                } elseif ($match_date) {
                    $deadline = date('Y-m-d 23:59:59', strtotime($match_date . ' -3 days'));
                }

                $board_url = home_url('/match-board-own');
                $action_items[] = [
                    'type' => 'match_request',
                    'id' => $match_req->ID,
                    'title' => 'マッチ申請への回答',
                    'description' => 'vs ' . ($from_team_id && function_exists('aidunite_get_team_name')
                        ? aidunite_get_team_name((int) $from_team_id)
                        : ($from_team ? $from_team->post_title : '相手チーム')),
                    'date' => $match_date,
                    'place' => $place,
                    'deadline' => $deadline,
                    'link_url' => $board_url,
                    'actions' => [
                        ['label' => '承認する', 'action' => 'approve', 'type' => 'primary'],
                        ['label' => '拒否する', 'action' => 'reject', 'type' => 'secondary']
                    ]
                ];
            }
        }
    }

    // 2. 出欠回答が必要なスケジュール
    $today = date('Y-m-d');
    $next_week = date('Y-m-d', strtotime('+7 days'));

    $schedules = function_exists('aidunite_attendance_read_schedules')
        ? aidunite_attendance_read_schedules([
            'date_between' => [$today, $next_week],
            'posts_per_page' => 20,
            'order' => 'ASC',
        ])
        : [];

    $team_scope_int = array_map('intval', $team_scope);

    foreach ($schedules as $schedule) {
        $schedule_team_id = function_exists('aidunite_schedule_read_team_id')
            ? (int) aidunite_schedule_read_team_id((int) $schedule->ID)
            : 0;

        // 管理中チームのスケジュールのみ
        if (!empty($team_scope_int) && !in_array($schedule_team_id, $team_scope_int, true)) {
            continue;
        }

        $attendance_data = function_exists('aidunite_attendance_read_data_map')
            ? aidunite_attendance_read_data_map((int) $schedule->ID)
            : [];
        $has_response = false;

        if (is_array($attendance_data)) {
            if ($effective_role === 'parent') {
                $children_ids = get_user_meta($user_id, 'player_id', false);
                if (empty($children_ids) && function_exists('aidunite_get_parent_children')) {
                    foreach (aidunite_get_parent_children($user_id) as $child) {
                        $cid = (int) ($child['user_id'] ?? 0);
                        if ($cid > 0) {
                            $children_ids[] = $cid;
                        }
                    }
                }
                if (empty($children_ids) && !empty($team_scope_int)) {
                    foreach ($team_scope_int as $scope_tid) {
                        if (function_exists('aidunite_attendance_get_target_member_ids_for_team')) {
                            foreach (aidunite_attendance_get_target_member_ids_for_team((int) $scope_tid) as $pid) {
                                if (function_exists('aidunite_get_player_parent') && (int) aidunite_get_player_parent((int) $pid) === (int) $user_id) {
                                    $children_ids[] = (int) $pid;
                                }
                            }
                        }
                    }
                }
                foreach (array_unique(array_map('intval', (array) $children_ids)) as $child_id) {
                    if ($child_id > 0 && isset($attendance_data[$child_id])) {
                        $row_status = $attendance_data[$child_id]['status'] ?? '';
                        if (!function_exists('aidunite_attendance_status_is_unanswered') || !aidunite_attendance_status_is_unanswered($row_status)) {
                            $has_response = true;
                            break;
                        }
                    }
                }
            } elseif (isset($attendance_data[$user_id])) {
                $has_response = true;
            }
        }

        if (!$has_response) {
                $sch_disp = function_exists('aidunite_schedule_get_display_bundle')
                    ? aidunite_schedule_get_display_bundle((int) $schedule->ID)
                    : [];
                $schedule_date = (string) ($sch_disp['date'] ?? (function_exists('aidunite_schedule_read_normalized_date')
                    ? aidunite_schedule_read_normalized_date((int) $schedule->ID)
                    : ''));
                $start_time = (string) ($sch_disp['start_time'] ?? '');
                $end_time = (string) ($sch_disp['end_time'] ?? '');
                $place = (string) ($sch_disp['place'] ?? (function_exists('aidunite_schedule_read_place_raw')
                    ? aidunite_schedule_read_place_raw((int) $schedule->ID)
                    : ''));
                $type = (string) ($sch_disp['schedule_type'] ?? '');

                $deadline = function_exists('aidunite_attendance_policy_get_response_deadline')
                    ? aidunite_attendance_policy_get_response_deadline($schedule_date)
                    : ($schedule_date ? date('Y-m-d 18:00:00', strtotime($schedule_date . ' -1 day')) : null);

                $action_items[] = [
                    'type' => 'attendance',
                    'id' => $schedule->ID,
                    'title' => '出欠回答が必要',
                    'description' => ($type ?: 'スケジュール') . ($schedule_date ? ' - ' . date('m/d', strtotime($schedule_date)) : ''),
                    'date' => $schedule_date,
                    'time' => ($start_time && $end_time) ? "{$start_time} - {$end_time}" : null,
                    'place' => $place,
                    'deadline' => $deadline,
                    'link_url' => function_exists('aidunite_attendance_report_url')
                        ? aidunite_attendance_report_url((int) $schedule->ID)
                        : home_url('/attendance-report'),
                    'actions' => [
                        ['label' => '参加', 'action' => 'attending', 'type' => 'primary'],
                        ['label' => '不参加', 'action' => 'not_attending', 'type' => 'secondary'],
                        ['label' => '要相談', 'action' => 'maybe', 'type' => 'secondary']
                    ]
                ];
        }
    }

    // 3. 試合後アンケート（未回答・試合終了済み／代表者のみ）
    if ($effective_role === 'team_leader' && !empty($team_scope_int) && function_exists('aidunite_resolve_post_match_survey_cta')) {
        foreach ($team_scope_int as $survey_team_id) {
            if ($survey_team_id <= 0) {
                continue;
            }
            if (function_exists('aidunite_process_pending_post_match_survey_flows_for_team')) {
                aidunite_process_pending_post_match_survey_flows_for_team($survey_team_id, 5);
            }
            $survey_requests = get_posts([
                'post_type'      => 'match_request',
                'post_status'    => 'publish',
                'posts_per_page' => 8,
                'fields'         => 'ids',
                'orderby'        => 'ID',
                'order'          => 'DESC',
                'meta_query'     => [
                    'relation' => 'AND',
                    [
                        'key'     => 'status',
                        'value'   => ['established', '試合確定'],
                        'compare' => 'IN',
                    ],
                    [
                        'relation' => 'OR',
                        ['key' => 'from_team_id', 'value' => (string) $survey_team_id, 'compare' => '='],
                        ['key' => 'to_team_id', 'value' => (string) $survey_team_id, 'compare' => '='],
                    ],
                ],
            ]);
            foreach ($survey_requests as $mr_id) {
                $cta = aidunite_resolve_post_match_survey_cta((int) $mr_id, $survey_team_id);
                if (empty($cta['state']) || $cta['state'] !== 'pending') {
                    continue;
                }
                $match_date = '';
                $to_sched = (int) get_post_meta($mr_id, 'to_schedule_id', true);
                if ($to_sched && function_exists('aidunite_schedule_read_normalized_date')) {
                    $match_date = (string) aidunite_schedule_read_normalized_date($to_sched);
                }
                $action_items[] = [
                    'type'        => 'match_feedback',
                    'id'          => (int) $mr_id,
                    'title'       => '試合後アンケート',
                    'description' => $match_date ? ('試合日 ' . date('n/j', strtotime($match_date))) : 'ご回答をお願いします',
                    'date'        => $match_date,
                    'deadline'    => null,
                    'link_url'    => $cta['url'] ?? '',
                    'actions'     => [
                        ['label' => '回答する', 'action' => 'open', 'type' => 'primary'],
                    ],
                ];
            }
        }
    }

    // 3b. 保護者：月謝カード未登録（Club・Connect 有効チームのみ）
    if ($effective_role === 'parent'
        && function_exists('aidunite_payment_read_parent_tuition_action_required_items')) {
        $tuition_items = aidunite_payment_read_parent_tuition_action_required_items($user_id);
        if (!empty($tuition_items)) {
            $action_items = array_merge($action_items, $tuition_items);
        }
    }

    // 4. 決済・支払い（導線再有効化まで要対応に出さない）
    $skip_player_payment_todo = (
        $effective_role === 'player'
        && function_exists('aidunite_user_is_high_school_grade_or_below')
        && aidunite_user_is_high_school_grade_or_below($user_id)
    );
    if (
        !$skip_player_payment_todo
        && function_exists('aidunite_payment_user_flows_enabled')
        && aidunite_payment_user_flows_enabled()
        && function_exists('aidunite_is_payment_required')
        && aidunite_is_payment_required($user_id)
    ) {
        $pay_url = home_url('/payment-setup');
        if ($effective_role === 'parent') {
            $pay_url = home_url('/parent-payment');
        }
        $action_items[] = [
            'type'        => 'payment',
            'id'          => $user_id,
            'title'       => $effective_role === 'parent' ? '会費のお支払い' : 'お支払いの確認',
            'description' => 'お支払い手続きが必要です',
            'deadline'    => null,
            'link_url'    => $pay_url,
            'actions'     => [
                ['label' => '確認する', 'action' => 'open', 'type' => 'primary'],
            ],
        ];
    }

    // 期限切れタスクの自動キャンセル処理（表示時に判定）
    $now = time();
    $filtered_items = [];
    $expired_items = [];

    foreach ($action_items as $item) {
        $deadline = $item['deadline'] ?? null;

        if ($deadline) {
            $deadline_ts = strtotime($deadline);

            // 期限切れ判定（同日締切は23:59まで期限内として扱う）
            if ($deadline_ts < $now) {
                // 期限切れタスクは自動キャンセル（ステータス更新）
                // マッチ申請の場合
                if ($item['type'] === 'match_request') {
                    aidunite_update_match_request_status_meta((int) $item['id'], 'canceled');
                    update_post_meta($item['id'], 'cancel_reason', '期限切れのため自動キャンセル');
                }
                // 履歴用に保存（P1対応用）
                $expired_items[] = $item;
                continue; // 通常表示から除外
            }
        }

        // 期限切れでないタスクのみ追加
        $filtered_items[] = $item;
    }

    $filtered_items = array_values(array_filter(
        $filtered_items,
        static function ($item) use ($effective_role, $user_id) {
            return aidunite_action_required_item_allowed_for_role($item['type'] ?? '', $effective_role, $user_id);
        }
    ));

    // 緊急度判定（URGENT/SOON/NORMAL）— キャンセルポリシー match-cancel-policy.php 準拠
    $now = time();
    foreach ($filtered_items as &$item) {
        $deadline = $item['deadline'] ?? null;
        $match_date = ($item['type'] ?? '') === 'match_request' ? (string) ($item['date'] ?? '') : '';

        if ($item['type'] === 'match_request' && function_exists('aidunite_match_policy_compute_action_urgency')) {
            $urgency = aidunite_match_policy_compute_action_urgency($match_date, $deadline);
            $item['urgency_level'] = $urgency['urgency_level'];
            $item['is_urgent'] = !empty($urgency['is_urgent']);
            if (isset($urgency['cancel_tier'])) {
                $item['cancel_tier'] = $urgency['cancel_tier'];
            }
            if (isset($urgency['days_until_match'])) {
                $item['days_until_match'] = $urgency['days_until_match'];
            }
            if (isset($urgency['remaining_hours'])) {
                $item['remaining_hours'] = $urgency['remaining_hours'];
            }
            if (isset($urgency['remaining_days'])) {
                $item['remaining_days'] = $urgency['remaining_days'];
            }
            continue;
        }

        if ($deadline) {
            $deadline_ts = strtotime($deadline);
            $seconds_until_deadline = $deadline_ts - $now;
            $hours_until_deadline = $seconds_until_deadline / 3600;
            $days_until_deadline = $seconds_until_deadline / (24 * 3600);

            // URGENT: 残り24時間以内 or 締切当日
            if ($hours_until_deadline <= 24 || $days_until_deadline < 1) {
                $item['urgency_level'] = 'URGENT';
                $item['is_urgent'] = true;
            }
            // SOON: 残り3日以内
            elseif ($days_until_deadline <= 3) {
                $item['urgency_level'] = 'SOON';
                $item['is_urgent'] = false;
            }
            // NORMAL: それ以外
            else {
                $item['urgency_level'] = 'NORMAL';
                $item['is_urgent'] = false;
            }

            // 残り時間の情報を追加（表示用）
            if ($hours_until_deadline <= 24) {
                $item['remaining_hours'] = max(0, floor($hours_until_deadline));
            } else {
                $item['remaining_days'] = max(0, floor($days_until_deadline));
            }
        } else {
            // deadlineなし
            $item['urgency_level'] = 'NONE';
            $item['is_urgent'] = false;
        }
    }
    unset($item);

    // 期限順にソート（昇順）
    usort($filtered_items, function($a, $b) {
        $priority_map = [
            'tuition_payment' => 0,
            'attendance' => 1,
            'payment' => 2,
        ];
        $deadline_a = $a['deadline'] ?? null;
        $deadline_b = $b['deadline'] ?? null;

        if (!$deadline_a && !$deadline_b) {
            $priority_a = $priority_map[$a['type'] ?? ''] ?? 3;
            $priority_b = $priority_map[$b['type'] ?? ''] ?? 3;
            if ($priority_a !== $priority_b) {
                return $priority_a - $priority_b;
            }
            return 0;
        }
        if (!$deadline_a) return 1;
        if (!$deadline_b) return -1;

        $ts_a = strtotime($deadline_a);
        $ts_b = strtotime($deadline_b);
        if ($ts_a !== $ts_b) {
            return $ts_a - $ts_b;
        }
        $priority_a = $priority_map[$a['type'] ?? ''] ?? 3;
        $priority_b = $priority_map[$b['type'] ?? ''] ?? 3;
        return $priority_a - $priority_b;
    });

    return $filtered_items;
}

/**
 * REST API: 要対応アイテム取得（MYPAGE-REDESIGN-PROPOSAL.md に基づく実装）
 * Inbox型通知システム用
 */
function aidunite_rest_get_action_required_items($request) {
    require_once get_template_directory() . '/functions/common/auth-middleware.php';
    $auth_result = AidUniteAuthMiddleware::rest_require($request, []);
    if (is_wp_error($auth_result)) {
        return $auth_result;
    }

    $filtered_items = aidunite_get_action_required_items_for_display();

    return rest_ensure_response([
        'success' => true,
        'items' => $filtered_items,
        'count' => count($filtered_items)
    ]);
}

/**
 * チーム UI テーマキー（男子=boys / 女子=girls）。操作中チームの team_gender_option を参照。
 *
 * @param int|null $team_id null のとき aidunite_get_current_team_id()
 * @return string 'boys'|'girls'
 */
function aidunite_get_team_ui_theme_key($team_id = null) {
    $team_id = $team_id !== null ? (int) $team_id : 0;
    if ($team_id <= 0 && function_exists('aidunite_get_current_team_id')) {
        $team_id = (int) aidunite_get_current_team_id();
    }
    if ($team_id <= 0) {
        return 'boys';
    }

    $raw = (string) get_post_meta($team_id, 'team_gender_option', true);
    $gender = function_exists('aidunite_normalize_team_gender_option')
        ? aidunite_normalize_team_gender_option($raw)
        : (function_exists('aidunite_normalize_gender_canonical') ? aidunite_normalize_gender_canonical($raw) : '');

    return $gender === 'female' ? 'girls' : 'boys';
}

/**
 * body に data-team-theme を付与するか（ログイン済み Webアプリページ）
 */
function aidunite_should_apply_team_ui_theme() {
    if (!is_user_logged_in()) {
        return false;
    }
    if (!function_exists('aidunite_is_web_app_page')) {
        return false;
    }

    return aidunite_is_web_app_page();
}
