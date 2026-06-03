<?php
/**
 * プロフィール編集機能用の関数群
 */

/**
 * アバター用の共通マークアップを生成（Design Tokens 使用・重複排除）
 *
 * @param int   $size   一辺のピクセル数
 * @param string $content 中身（絵文字など）
 * @return string HTML
 */
function _profile_avatar_markup($size, $content) {
    return sprintf(
        '<div style="width: %dpx; height: %dpx; border-radius: 50%%; background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); display: flex; align-items: center; justify-content: center; font-size: %dpx; color: var(--text-light); box-shadow: var(--shadow-md);">%s</div>',
        $size,
        $size,
        (int) ($size * 0.4),
        $content
    );
}

/**
 * ユーザーのアバターを取得
 */
function get_user_avatar($user_id = null, $size = 120) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    if (!$user_id) {
        return get_default_avatar($size);
    }

    $avatar_emoji = get_user_meta($user_id, 'user_avatar_emoji', true) ?: '👤';
    return _profile_avatar_markup($size, $avatar_emoji);
}

/**
 * デフォルトアバターを取得
 */
function get_default_avatar($size = 120) {
    return _profile_avatar_markup($size, '👤');
}

/**
 * ユーザーの表示名を取得
 */
function get_user_display_name($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    if (!$user_id) {
        return 'ゲスト';
    }

    $user = get_userdata($user_id);
    if (!$user) {
        return '不明なユーザー';
    }

    $display_name = isset($user->display_name) ? $user->display_name : '';
    if (empty($display_name)) {
        $display_name = isset($user->user_login) ? $user->user_login : '';
    }

    return $display_name;
}

/**
 * ユーザーの完全な名前を取得
 */
function get_user_full_name($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    if (!$user_id) {
        return '';
    }

    $user = get_userdata($user_id);
    if (!$user) {
        return '';
    }

    $first_name = isset($user->first_name) ? $user->first_name : '';
    $last_name = isset($user->last_name) ? $user->last_name : '';

    if (empty($first_name) && empty($last_name)) {
        $display = isset($user->display_name) ? $user->display_name : '';
        $login = isset($user->user_login) ? $user->user_login : '';
        return $display ?: $login;
    }

    return trim($last_name . ' ' . $first_name);
}

/**
 * ユーザーのプロフィール情報を取得
 */
function get_user_profile_data($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    if (!$user_id) {
        return array();
    }
    // D-1: 他 user_id のプロフィール読み取りは拒否（自分以外は返さない）
    if ((int) $user_id !== (int) get_current_user_id()) {
        return array();
    }

    $user = get_userdata($user_id);
    if (!$user) {
        return array();
    }

    return array(
        'user_id' => $user_id,
        'user_login' => isset($user->user_login) ? $user->user_login : '',
        'user_email' => isset($user->user_email) ? $user->user_email : '',
        'first_name' => isset($user->first_name) ? $user->first_name : '',
        'last_name' => isset($user->last_name) ? $user->last_name : '',
        'display_name' => isset($user->display_name) ? $user->display_name : '',
        'user_registered' => isset($user->user_registered) ? $user->user_registered : '',
        'phone' => get_user_meta($user_id, 'user_phone', true),
        'birth_date' => get_user_meta($user_id, 'user_birth_date', true),
        'gender' => get_user_meta($user_id, 'user_gender', true),
        'address' => get_user_meta($user_id, 'user_address', true),
        'bio' => get_user_meta($user_id, 'user_bio', true),
        'avatar_emoji' => get_user_meta($user_id, 'user_avatar_emoji', true) ?: '👤',
        'full_name' => get_user_full_name($user_id),
        'avatar_html' => get_user_avatar($user_id)
    );
}

/**
 * ユーザーのプロフィール情報を更新
 */
function update_user_profile($user_id, $profile_data) {
    if (!$user_id || !is_array($profile_data)) {
        return false;
    }
    // 他ユーザーのプロフィールは更新できない（Critical#6）
    if ((int) $user_id !== (int) get_current_user_id()) {
        return false;
    }
    // ユーザー情報を更新
    $user_data = array('ID' => $user_id);

    if (isset($profile_data['first_name'])) {
        $user_data['first_name'] = sanitize_text_field($profile_data['first_name']);
    }

    if (isset($profile_data['last_name'])) {
        $user_data['last_name'] = sanitize_text_field($profile_data['last_name']);
    }

    if (isset($profile_data['display_name'])) {
        $user_data['display_name'] = sanitize_text_field($profile_data['display_name']);
    }

    if (isset($profile_data['user_email'])) {
        $user_data['user_email'] = function_exists('aidunite_normalize_email') ? aidunite_normalize_email($profile_data['user_email']) : sanitize_email($profile_data['user_email']);
    }

    $result = wp_update_user($user_data);

    if (is_wp_error($result)) {
        return false;
    }

    // カスタムフィールドを更新
    $meta_fields = array(
        'user_phone' => 'phone',
        'user_birth_date' => 'birth_date',
        'user_gender' => 'gender',
        'user_address' => 'address',
        'user_bio' => 'bio',
        'user_avatar_emoji' => 'avatar_emoji'
    );

    foreach ($meta_fields as $meta_key => $data_key) {
        if (isset($profile_data[$data_key])) {
            $value = $profile_data[$data_key];

            switch ($meta_key) {
                case 'user_bio':
                    $value = sanitize_textarea_field($value);
                    break;
                case 'user_address':
                    $value = sanitize_textarea_field($value);
                    break;
                default:
                    $value = sanitize_text_field($value);
                    break;
            }

            update_user_meta($user_id, $meta_key, $value);
        }
    }

    return true;
}

/**
 * パスワードの強度をチェック
 */
function check_password_strength($password) {
    $score = 0;

    if (strlen($password) >= 8) $score++;
    if (preg_match('/[a-z]/', $password)) $score++;
    if (preg_match('/[A-Z]/', $password)) $score++;
    if (preg_match('/[0-9]/', $password)) $score++;
    if (preg_match('/[^A-Za-z0-9]/', $password)) $score++;

    if ($score < 2) return 'weak';
    if ($score < 4) return 'medium';
    return 'strong';
}

/**
 * メールアドレスの重複をチェック
 */
function is_email_available($email, $exclude_user_id = null) {
    $email_for_check = function_exists('aidunite_normalize_email') ? aidunite_normalize_email($email) : $email;
    $user = get_user_by('email', $email_for_check);

    if (!$user) {
        return true;
    }

    if ($exclude_user_id && $user->ID == $exclude_user_id) {
        return true;
    }

    return false;
}



/**
 * ユーザーの年齢を計算
 */
function calculate_user_age($birth_date) {
    if (empty($birth_date)) {
        return null;
    }

    $birth = new DateTime($birth_date);
    $today = new DateTime();
    $age = $today->diff($birth);

    return $age->y;
}

/**
 * 性別の表示名を取得
 */
function get_gender_display_name($gender) {
    $gender_names = array(
        'male' => '男性',
        'female' => '女性',
        'other' => 'その他',
        'prefer_not_to_say' => '回答しない'
    );

    return isset($gender_names[$gender]) ? $gender_names[$gender] : '';
}

/**
 * プロフィール編集ページのURLを取得
 */
function get_profile_edit_url() {
    $page = get_page_by_path('profile-edit');
    if ($page) {
        return get_permalink($page->ID);
    }

    // ページが存在しない場合は、現在のページのURLを返す
    return home_url('/profile-edit/');
}

/**
 * プロフィール編集ページへのリンクを生成
 */
function get_profile_edit_link($text = 'プロフィール編集', $class = '') {
    $url = get_profile_edit_url();
    $class_attr = $class ? ' class="' . esc_attr($class) . '"' : '';

    return sprintf('<a href="%s"%s>%s</a>', esc_url($url), $class_attr, esc_html($text));
}

/**
 * ユーザーのプロフィール完了度を計算
 */
function calculate_profile_completion($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    if (!$user_id) {
        return 0;
    }

    $profile_data = get_user_profile_data($user_id);
    $required_fields = array('first_name', 'last_name', 'display_name', 'user_email');
    $optional_fields = array('phone', 'birth_date', 'gender', 'address', 'bio');

    $completed_required = 0;
    $completed_optional = 0;

    // 必須フィールドの完了度をチェック
    foreach ($required_fields as $field) {
        if (!empty($profile_data[$field])) {
            $completed_required++;
        }
    }

    // オプションフィールドの完了度をチェック
    foreach ($optional_fields as $field) {
        if (!empty($profile_data[$field])) {
            $completed_optional++;
        }
    }

    // 完了度を計算（必須フィールドの重みを高くする）
    $required_weight = 0.7;
    $optional_weight = 0.3;

    $required_completion = $completed_required / count($required_fields);
    $optional_completion = $completed_optional / count($optional_fields);

    $total_completion = ($required_completion * $required_weight) + ($optional_completion * $optional_weight);

    return round($total_completion * 100);
}

/**
 * プロフィール完了度の表示用HTMLを生成
 */
function get_profile_completion_html($user_id = null) {
    $completion = calculate_profile_completion($user_id);

    $color_class = '';
    if ($completion >= 80) {
        $color_class = 'success';
    } elseif ($completion >= 60) {
        $color_class = 'warning';
    } else {
        $color_class = 'danger';
    }

    return sprintf(
        '<div class="profile-completion">
            <div class="progress">
                <div class="progress-bar bg-%s" style="width: %d%%"></div>
            </div>
            <small class="text-muted">プロフィール完了度: %d%%</small>
        </div>',
        $color_class,
        $completion,
        $completion
    );
}
