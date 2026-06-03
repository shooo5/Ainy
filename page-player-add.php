<?php
/**
 * Template Name: 新規選手追加ページ
 */

// 統一認証・権限チェック
$auth_result = AidUniteAuthMiddleware::require_auth(true);
if (!$auth_result->is_valid()) {
    // リダイレクトは自動で実行される
    return;
}

// チーム所属チェック
$current_user = wp_get_current_user();

$team_post = get_posts(array(
  'post_type' => 'team',
  'author'    => $current_user->ID,
  'post_status' => array('publish', 'pending', 'draft'),
  'numberposts' => 1,
));

if (empty($team_post)) {
    // team_idメタからも検索を試みる
    $team_id_from_meta = get_user_meta($current_user->ID, 'team_id', true);
    if ($team_id_from_meta) {
        $team_post_from_meta = get_posts(array(
            'post_type' => 'team',
            'p' => $team_id_from_meta,
            'post_status' => array('publish', 'pending', 'draft'),
            'numberposts' => 1,
        ));
        if (!empty($team_post_from_meta)) {
            $team_post = $team_post_from_meta;
        }
    }
}

if (!$team_post) {
    wp_redirect(home_url('/team-registration'));
    exit;
}

$team_id = $team_post[0]->ID;
$team_name = get_post_meta($team_id, 'team_name', true) ?: $team_post[0]->post_title;
$team_category = get_post_meta($team_id, 'team_category', true);

// チームカテゴリーによる未成年/成人判定
$minor_categories = ['小学生', '中学生', '高校生'];
$is_minor_team = in_array($team_category, $minor_categories);

// フォーム送信処理
$registration_result = null;
$form_errors = [];

if ($_POST && isset($_POST['player_nonce']) && wp_verify_nonce($_POST['player_nonce'], 'player_registration')) {
    $registration_mode = sanitize_text_field($_POST['registration_mode'] ?? 'single'); // single | bulk

    /**
     * 単発登録用の1件分処理
     */
    $register_single_player = function($raw_player, $raw_parent) use ($current_user, $is_minor_team, &$form_errors) {
        // 性・名を結合して氏名を作成
        $player_name_sei = sanitize_text_field($raw_player['player_name_sei'] ?? '');
        $player_name_mei = sanitize_text_field($raw_player['player_name_mei'] ?? '');
        $player_name = trim($player_name_sei . ' ' . $player_name_mei);

        // フリガナも結合
        $player_kana_sei = sanitize_text_field($raw_player['player_kana_sei'] ?? '');
        $player_kana_mei = sanitize_text_field($raw_player['player_kana_mei'] ?? '');
        $player_kana = trim($player_kana_sei . ' ' . $player_kana_mei);

        // 既存のplayer_name, player_kanaも確認（まとめ登録などで使用される可能性）
        if (empty($player_name) && !empty($raw_player['player_name'])) {
            $player_name = sanitize_text_field($raw_player['player_name']);
        }
        if (empty($player_kana) && !empty($raw_player['player_kana'])) {
            $player_kana = sanitize_text_field($raw_player['player_kana']);
        }

        // 選手データ収集（最低限＋任意）
        $player_data = [
            'player_name'      => $player_name,
            'player_name_kana' => $player_kana,
            'player_name_sei'  => $player_name_sei,
            'player_name_mei'  => $player_name_mei,
            'player_kana_sei'  => $player_kana_sei,
            'player_kana_mei'  => $player_kana_mei,
            'birth_date'       => sanitize_text_field($raw_player['player_birth_date'] ?? ''),
            'grade'            => sanitize_text_field($raw_player['player_grade'] ?? ''),
            'position'         => sanitize_text_field($raw_player['player_position'] ?? ''),
            'nickname'         => sanitize_text_field($raw_player['player_nickname'] ?? ''),
            'club_team'        => sanitize_text_field($raw_player['player_club_team'] ?? ''),
            'notes'            => sanitize_textarea_field($raw_player['player_notes'] ?? ''),
            'player_email'     => function_exists('aidunite_normalize_email') ? aidunite_normalize_email($raw_player['player_email'] ?? '') : sanitize_email($raw_player['player_email'] ?? ''),
            'height'           => sanitize_text_field($raw_player['player_height'] ?? ''),
            'weight'           => sanitize_text_field($raw_player['player_weight'] ?? ''),
            // パスワードは常に自動生成
            'password_option'  => 'auto',
            'custom_password'  => '',
        ];

        // 保護者 / 緊急連絡先データ収集
        if ($is_minor_team) {
            // 保護者名を結合
            $parent_name_sei = sanitize_text_field($raw_parent['parent_name_sei'] ?? '');
            $parent_name_mei = sanitize_text_field($raw_parent['parent_name_mei'] ?? '');
            $parent_name = trim($parent_name_sei . ' ' . $parent_name_mei);

            // 未成年チーム: 保護者メールを主とし、深い個人情報は持たない
            $parent_data = [
                'parent_name'             => $parent_name,
                'parent_name_sei'         => $parent_name_sei,
                'parent_name_mei'         => $parent_name_mei,
                'parent_email'            => function_exists('aidunite_normalize_email') ? aidunite_normalize_email($raw_parent['parent_email'] ?? '') : sanitize_email($raw_parent['parent_email'] ?? ''),
                'parent_emergency_contact' => sanitize_text_field($raw_parent['parent_emergency_contact'] ?? ''),
                'create_account'          => false,
            ];
        } else {
            // 成人チーム: 緊急連絡先情報のみ
            $parent_data = [
                'emergency_contact_name'         => '',
                'emergency_contact_relationship' => '',
                'emergency_contact_phone'        => sanitize_text_field($raw_parent['emergency_contact_phone'] ?? ''),
                'emergency_contact_email'        => '',
                'create_account'                 => false,
            ];
        }

        // バリデーション（最低限）
        if (empty($player_data['player_name']) || (empty($player_name_sei) || empty($player_name_mei))) {
            $form_errors[] = '選手名（姓・名）は必須です';
        }
        if (empty($player_data['birth_date'])) {
            $form_errors[] = '生年月日は必須です';
        }
        if ($is_minor_team && empty($parent_data['parent_email'])) {
            $form_errors[] = '未成年チームでは保護者メールアドレスは必須です';
        }

        if (!empty($form_errors)) {
            return null;
        }

        // エラーがない場合は登録処理実行
        if ($is_minor_team) {
            return aidunite_team_leader_register_player_with_parent($current_user->ID, $player_data, $parent_data);
        } else {
            return aidunite_team_leader_register_adult_player($current_user->ID, $player_data, $parent_data);
        }
    };

    if ($registration_mode === 'bulk') {
        // まとめ登録モード
        $bulk_players = $_POST['bulk_players'] ?? [];
        $success_count = 0;

        if (!is_array($bulk_players) || empty($bulk_players)) {
            $form_errors[] = 'まとめ登録する選手情報が入力されていません。';
        } else {
            foreach ($bulk_players as $index => $row) {
                // 空行（全フィールド空）はスキップ
                $name   = trim($row['player_name'] ?? '');
                $birth  = trim($row['player_birth_date'] ?? '');
                $p_email = trim($row['parent_email'] ?? '');

                if ($name === '' && $birth === '' && $p_email === '') {
                    continue;
                }

                $single_errors_before = count($form_errors);
                $result = $register_single_player(
                    [
                        'player_name_sei'   => $row['player_name_sei'] ?? '',
                        'player_name_mei'  => $row['player_name_mei'] ?? '',
                        'player_kana_sei'  => $row['player_kana_sei'] ?? '',
                        'player_kana_mei'  => $row['player_kana_mei'] ?? '',
                        'player_name'      => $row['player_name'] ?? '', // フォールバック用
                        'player_kana'      => $row['player_kana'] ?? '', // フォールバック用
                        'player_birth_date' => $row['player_birth_date'] ?? '',
                        'player_nickname'   => $row['player_nickname'] ?? '',
                        'player_position'   => $row['player_position'] ?? '',
                        'player_club_team'  => $row['player_club_team'] ?? '',
                    ],
                    [
                        'parent_name_sei'          => $row['parent_name_sei'] ?? '',
                        'parent_name_mei'         => $row['parent_name_mei'] ?? '',
                        'parent_email'             => $row['parent_email'] ?? '',
                        'parent_emergency_contact' => $row['parent_emergency_contact'] ?? '',
                        'emergency_contact_phone'  => $row['emergency_contact_phone'] ?? '',
                    ]
                );

                if (count($form_errors) > $single_errors_before) {
                    $form_errors[] = sprintf('行 %d の登録に失敗しました。上記のエラー内容を確認してください。', $index + 1);
                    break;
                }

                if ($result && !empty($result['success'])) {
                    $success_count++;
                } else {
                    $form_errors[] = sprintf('行 %d の登録に失敗しました。', $index + 1);
                    break;
                }
            }
        }

        if (empty($form_errors) && $success_count > 0) {
            // 成功時：トースト通知用のパラメータを付与してリダイレクト
            wp_redirect(add_query_arg([
                'toast' => 'success',
                'message' => urlencode($success_count . '名の選手を登録しました。'),
            ], home_url('/player-add')));
            exit;
        } elseif (!empty($form_errors)) {
            // エラー時：トースト通知用のパラメータを付与してリダイレクト
            $error_message = !empty($form_errors) ? urlencode(implode(' ', $form_errors)) : urlencode('登録に失敗しました。');
            wp_redirect(add_query_arg([
                'toast' => 'error',
                'message' => $error_message,
            ], home_url('/player-add')));
            exit;
        }
    } else {
        // 単発登録モード
        $registration_result = $register_single_player($_POST, $_POST);

        if ($registration_result && !empty($registration_result['success'])) {
            // メタデータの更新を確実にするため、キャッシュをクリア
            wp_cache_flush();
            if (!empty($registration_result['player_id'])) {
                clean_user_cache($registration_result['player_id']);
            }
            if (!empty($registration_result['parent_id'])) {
                clean_user_cache($registration_result['parent_id']);
            }

            // 成功時：トースト通知用のパラメータを付与してリダイレクト
            wp_redirect(add_query_arg([
                'toast' => 'success',
                'message' => urlencode('選手の登録が完了しました。保護者への招待メールが送信されました。'),
            ], home_url('/player-add')));
            exit;
        } elseif ($registration_result && !empty($registration_result['message'])) {
            // エラー時：トースト通知用のパラメータを付与してリダイレクト
            wp_redirect(add_query_arg([
                'toast' => 'error',
                'message' => urlencode($registration_result['message']),
            ], home_url('/player-add')));
            exit;
        }
    }
}

get_header();
?>

<style>
/* 選手追加ページ専用スタイル */
.page-player-add {
  background: var(--bg-light);
}

.form-section {
  margin-bottom: 2rem;
  padding: 1.5rem;
  border-radius: 8px;
  background: var(--bg-secondary);
  border-left: 4px solid var(--primary-color);
}

.form-section h4 {
  margin: 0 0 0.5rem 0;
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--text-primary);
}

/* 公開プロフィールセクション */
.form-section:nth-of-type(1) {
  border-left-color: var(--success-color);
  background: linear-gradient(135deg, rgba(40, 167, 69, 0.05) 0%, rgba(40, 167, 69, 0.1) 100%);
}

/* 管理者・保護者専用セクション */
.form-section:nth-of-type(2) {
  border-left-color: var(--warning-color);
  background: linear-gradient(135deg, rgba(255, 193, 7, 0.05) 0%, rgba(255, 193, 7, 0.1) 100%);
  position: relative;
}

/* 保護者情報セクション */
.form-section:nth-of-type(3) {
  border-left-color: var(--info-color);
  background: linear-gradient(135deg, rgba(23, 162, 184, 0.05) 0%, rgba(23, 162, 184, 0.1) 100%);
}

/* プライバシー同意セクション */
.privacy-consent-section {
  border-left-color: var(--info-color);
  background: linear-gradient(135deg, rgba(23, 162, 184, 0.05) 0%, rgba(23, 162, 184, 0.1) 100%);
  margin-top: 2rem;
  padding: 1.5rem;
  border-radius: 8px;
  border-left: 4px solid var(--info-color);
}

/* 学年自動計算フィールド */
#player_grade_display {
  background-color: var(--border-light);
  color: var(--text-secondary);
  cursor: not-allowed;
}

#player_grade_display:focus {
  box-shadow: none;
  border-color: var(--border-color);
}

/* 必須フィールドの強調 */
.form-group label .required {
  color: var(--danger-color);
  font-weight: bold;
}

/* トースト通知スタイル（画面中央表示） */
.player-registration-toast {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 10000;
  animation: fadeIn 0.3s ease-out;
  font-family: inherit;
}

.player-registration-toast-card {
  background: white;
  padding: 2.5rem 3rem;
  border-radius: 20px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
  text-align: center;
  max-width: 500px;
  animation: slideIn 0.5s ease-out;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
}

.player-registration-toast-icon {
  font-size: 3.5rem;
  line-height: 1;
}

.player-registration-toast-message {
  flex: 1;
}

.player-registration-toast .toast-title {
  font-size: 1.3rem;
  font-weight: 600;
  color: var(--text-primary);
  margin-bottom: 0.75rem;
  line-height: 1.4;
}

.player-registration-toast .toast-content {
  font-size: 1.1rem;
  line-height: 1.8;
  color: var(--text-secondary);
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateY(-20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (max-width: 768px) {
  .player-registration-toast-card {
    padding: 2rem 1.5rem;
    max-width: 90%;
  }

  .player-registration-toast-icon {
    font-size: 3rem;
  }

  .player-registration-toast .toast-title {
    font-size: 1.2rem;
  }

  .player-registration-toast .toast-content {
    font-size: 1rem;
  }
}

/* プライバシー同意チェックボックス */
.checkbox-group {
  margin: 1rem 0;
  display: block;
  flex-direction: unset;
  gap: unset;
}

/* デバッグ用：余計な要素を非表示 */
.checkbox-group > *:not(input[type="checkbox"]):not(label) {
  display: none;
}

/* チェックボックスを非表示にして、カスタムチェックボックスのみ表示 */
.checkbox-group input[type="checkbox"] {
  display: none;
}

.checkbox-label {
  display: flex;
  align-items: flex-start;
  cursor: pointer;
  font-size: 0.95rem;
  line-height: 1.4;
  gap: 0.75rem;
}

.checkbox-custom {
  width: 20px;
  height: 20px;
  border: 2px solid var(--primary-color);
  border-radius: 4px;
  margin-right: 12px;
  margin-top: 2px;
  position: relative;
  background: white;
  flex-shrink: 0;
}

.checkbox-custom::after {
  content: '✓';
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  color: white;
  font-size: 12px;
  font-weight: bold;
  opacity: 0;
  transition: opacity 0.2s ease;
}

input[type="checkbox"]:checked + .checkbox-label .checkbox-custom {
  background: var(--primary-color);
  border-color: var(--primary-color);
}

input[type="checkbox"]:checked + .checkbox-label .checkbox-custom::after {
  opacity: 1;
}

.checkbox-text {
  flex: 1;
}

.terms-link {
  color: var(--primary-color);
  text-decoration: underline;
  font-weight: 500;
}

.terms-link:hover {
  color: var(--primary-dark);
  text-decoration: none;
}

/* 送信ボタンのスタイル */
#player-submit-btn {
  background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
  border: none;
  padding: 12px 30px;
  font-size: 1.1rem;
  font-weight: 600;
  border-radius: 8px;
  box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
  transition: all 0.3s ease;
  margin-top: 1rem;
}

#player-submit-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

#player-submit-btn:disabled {
  background: var(--text-muted);
  transform: none;
  box-shadow: none;
  cursor: not-allowed;
}

/* 情報アイコンとテキスト */
.text-muted {
  color: var(--text-muted);
  font-size: 0.9rem;
  margin-bottom: 1rem;
}

/* レスポンシブ対応 */
@media (max-width: 768px) {
  .form-section {
    padding: 1rem;
    margin-bottom: 1.5rem;
  }

  .form-section h4 {
    font-size: 1rem;
  }

  .checkbox-label {
    font-size: 0.9rem;
  }

  #player-submit-btn {
    width: 100%;
    padding: 15px;
  }
}

@media (max-width: 480px) {
  .form-section {
    padding: 0.8rem;
  }

  .checkbox-custom {
    width: 18px;
    height: 18px;
    margin-right: 8px;
  }

  .checkbox-text {
    font-size: 0.85rem;
  }
}

/* アニメーション効果 */
.form-section {
  animation: fadeInUp 0.6s ease-out;
}

.form-section:nth-child(1) { animation-delay: 0.1s; }
.form-section:nth-child(2) { animation-delay: 0.2s; }
.form-section:nth-child(3) { animation-delay: 0.3s; }

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* フォーカス時のエフェクト */
.form-control:focus {
  border-color: var(--primary-color);
  box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

/* プルダウンの⋁⋁⋁表示問題を修正 */
select.form-control {
  appearance: none;
  -webkit-appearance: none;
  -moz-appearance: none;
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
  background-position: right 0.75rem center;
  background-repeat: no-repeat;
  background-size: 1.5em 1.5em;
  padding-right: 2.5rem;
}

select.form-control:focus {
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236a5af9' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
}

/* ブラウザのデフォルト矢印を完全に非表示 */
select.form-control::-ms-expand {
  display: none;
}

/* エラー状態 */
.form-control.error {
  border-color: var(--danger-color);
  box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
}

/* インラインエラーメッセージ */
.field-error {
  color: var(--danger-color);
  font-size: 0.875rem;
  margin-top: 0.25rem;
  display: block;
}

.field-error::before {
  content: none;
}

/* まとめ登録テーブル */
.bulk-registration-table-container {
  overflow-x: auto;
  margin: 1.5rem 0;
}

.bulk-registration-table {
  width: 100%;
  border-collapse: collapse;
  background: white;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.bulk-registration-table thead {
  background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
  color: white;
}

.bulk-registration-table th {
  padding: 1rem 0.75rem;
  text-align: left;
  font-weight: 600;
  font-size: 0.875rem;
  white-space: nowrap;
}

.bulk-registration-table td {
  padding: 0.75rem;
  border-bottom: 1px solid var(--border-light);
  vertical-align: middle;
}

.bulk-registration-table tbody tr:hover {
  background: var(--bg-secondary);
}

.bulk-registration-table input,
.bulk-registration-table select {
  width: 100%;
  padding: 0.5rem;
  border: 1px solid var(--border-color);
  border-radius: 4px;
  font-size: 0.875rem;
}

.bulk-registration-table input.error,
.bulk-registration-table select.error {
  border-color: var(--danger-color);
}

.bulk-registration-table .row-actions {
  white-space: nowrap;
}

.bulk-registration-table .btn-remove-row {
  padding: 0.25rem 0.5rem;
  background: var(--danger-color);
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 0.75rem;
}

.bulk-registration-table .btn-remove-row:hover {
  background: var(--danger-color);
}

.bulk-registration-actions {
  margin-top: 1rem;
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
}

.btn-secondary {
  background: var(--text-muted);
  color: white;
  padding: 0.75rem 1.5rem;
  border-radius: 8px;
  border: none;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
}

.btn-secondary:hover {
  background: var(--text-secondary);
  transform: translateY(-2px);
}

@media (max-width: 768px) {
  .bulk-registration-table {
    font-size: 0.75rem;
  }

  .bulk-registration-table th,
  .bulk-registration-table td {
    padding: 0.5rem 0.25rem;
  }

  .bulk-registration-table input,
  .bulk-registration-table select {
    font-size: 0.75rem;
    padding: 0.375rem;
  }
}

/* 成功状態 */
.form-control.success {
  border-color: var(--success-color);
  box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
}

/* エラーメッセージスタイル */
.alert {
  padding: 1rem 1.5rem;
  margin-bottom: 1.5rem;
  border-radius: 8px;
  border-left: 4px solid;
}

.alert-danger {
  background: linear-gradient(135deg, rgba(220, 53, 69, 0.05) 0%, rgba(220, 53, 69, 0.1) 100%);
  border-left-color: var(--danger-color);
  color: var(--danger-color);
}

.alert h4 {
  margin: 0 0 0.5rem 0;
  font-size: 1rem;
  font-weight: 600;
}

.alert ul {
  margin: 0;
  padding-left: 1.5rem;
}

.alert li {
  margin-bottom: 0.25rem;
}

/* ログイン情報セクション */
.login-info-section {
  margin-top: 1.5rem;
  padding: 1rem;
  background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--border-light) 100%);
  border-radius: 8px;
  border: 1px solid var(--border-color);
}

.login-info-section h5 {
  margin: 0 0 1rem 0;
  font-size: 1rem;
  font-weight: 600;
  color: var(--text-primary);
}

.form-text {
  font-size: 0.875rem;
  color: var(--text-secondary);
  margin-top: 0.25rem;
}

/* 保護者アカウント作成オプション */
.parent-account-option {
  margin-bottom: 1.5rem;
  padding: 1rem;
  background: linear-gradient(135deg, rgba(23, 162, 184, 0.05) 0%, rgba(23, 162, 184, 0.1) 100%);
  border-radius: 8px;
  border: 1px solid var(--info-color);
}

.parent-account-option label {
  display: flex;
  align-items: center;
  font-weight: 600;
  margin-bottom: 0.5rem;
}

.parent-account-option input[type="checkbox"] {
  margin-right: 0.5rem;
  transform: scale(1.2);
}

/* 保護者ログイン情報セクション */
.parent-login-section {
  margin-top: 1.5rem;
  padding: 1rem;
  background: linear-gradient(135deg, rgba(255, 193, 7, 0.05) 0%, rgba(255, 193, 7, 0.1) 100%);
  border-radius: 8px;
  border: 1px solid #ffeaa7;
}

.parent-login-section h5 {
  margin: 0 0 1rem 0;
  font-size: 1rem;
  font-weight: 600;
  color: var(--text-primary);
}

/* チームカテゴリー表示 */
.team-category-info {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-top: 0.5rem;
}

.category-badge {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  background: var(--primary-color);
  color: white;
  border-radius: 20px;
  font-size: 0.875rem;
  font-weight: 600;
}

.category-note {
  font-size: 0.875rem;
  color: var(--text-secondary);
  font-style: italic;
}

@media (max-width: 768px) {
  .team-category-info {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
  }
}

/* ステップ形式（ウィザード） */
.registration-steps {
    display: flex;
    justify-content: space-between;
    margin-bottom: 2rem;
    padding: 1rem;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.step-indicator {
    flex: 1;
    text-align: center;
    position: relative;
    opacity: 0.5;
    transition: opacity 0.3s ease;
}

.step-indicator.active {
    opacity: 1;
}

.step-indicator.completed {
    opacity: 0.8;
}

.step-number {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #e9ecef;
    color: #6c757d;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    margin: 0 auto 0.5rem;
    transition: all 0.3s ease;
}

.step-indicator.active .step-number {
    background: var(--primary-color);
    color: white;
}

.step-indicator.completed .step-number {
    background: var(--success-color);
    color: white;
}

.step-label {
    font-size: 0.875rem;
    color: var(--text-secondary);
    font-weight: 500;
}

.step-indicator.active .step-label {
    color: var(--text-primary);
    font-weight: 600;
}

.form-step {
    animation: fadeIn 0.3s ease-out;
}

.step-navigation {
    display: flex;
    justify-content: space-between;
    margin-top: 2rem;
    gap: 1rem;
}

.confirmation-summary {
    background: var(--bg-secondary);
    border-radius: 8px;
    padding: 1.5rem;
}

.confirmation-summary-item {
    display: flex;
    justify-content: space-between;
    padding: 0.75rem 0;
    border-bottom: 1px solid #dee2e6;
}

.confirmation-summary-item:last-child {
    border-bottom: none;
}

.confirmation-summary-label {
    font-weight: 600;
    color: var(--text-primary);
}

.confirmation-summary-value {
    color: var(--text-secondary);
}

@media (max-width: 768px) {
    .registration-steps {
        flex-direction: column;
        gap: 1rem;
    }

    .step-indicator {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .step-number {
        margin: 0;
    }
}
</style>

<?php
if (function_exists('aidunite_web_app_page_shell_open')) {
    aidunite_web_app_page_shell_open([
        'page_class' => 'page-player-add',
        'title' => '新しい選手を登録',
        'subtitle' => 'チームに新しい選手を追加します',
        'back_url' => home_url('/team-members'),
    ]);
} else {
    echo '<div class="team-dashboard-container page-player-add"><div class="dashboard-header"><h1>新しい選手を登録</h1><p>チームに新しい選手を追加して、より強力なチームを作りましょう！</p></div>';
}
?>

  <section class="dashboard-section dashboard-section--flush">
    <h2><?php echo aidunite_render_theme_icon('list_alt_add', ['width' => '24', 'height' => '24'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 選手情報入力</h2>
    <div class="main-content-area">

      <!-- エラーメッセージ表示（フォーム送信前のバリデーションエラー用） -->
      <?php if (!empty($form_errors)) : ?>
      <div class="alert alert-danger">
        <h4><?php echo aidunite_render_theme_icon('brightness_alert', ['width' => '20', 'height' => '20'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 入力エラーがあります</h4>
        <ul>
          <?php foreach ($form_errors as $error) : ?>
          <li><?php echo esc_html($error); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <div class="form-section">
        <h3>所属チーム: <?php echo esc_html($team_name); ?></h3>
        <p class="team-category-info">
          <span class="category-badge"><?php echo esc_html($team_category); ?>チーム</span>
          <?php if ($is_minor_team): ?>
            <span class="category-note">※ 未成年者向けの登録フォームです（保護者情報が必要）</span>
          <?php else: ?>
            <span class="category-note">※ 成人向けの登録フォームです（本人登録）</span>
          <?php endif; ?>
        </p>
      </div>

      <form id="player-registration-form" method="post">
        <?php wp_nonce_field('player_registration', 'player_nonce'); ?>
        <input type="hidden" name="team_id" value="<?php echo esc_attr($team_id); ?>">
        <input type="hidden" name="registration_mode" id="registration_mode" value="single">

        <!-- 登録モード切り替え -->
        <div class="form-section">
          <h4>登録モード</h4>
          <div class="form-row-2">
            <div class="form-group">
              <label>
                <input type="radio" name="registration_mode_selector" value="single" checked onchange="switchRegistrationMode('single')">
                単発で1人ずつ登録
              </label>
            </div>
            <div class="form-group">
              <label style="opacity: 0.5; cursor: not-allowed;">
                <input type="radio" name="registration_mode_selector" value="bulk" disabled>
                まとめて複数人を登録（準備中）
              </label>
            </div>
          </div>
          <p class="text-muted" style="margin-top: 0.5rem;">
            ※ まとめて複数人を登録する機能は現在準備中です。今は「単発で1人ずつ登録」のみご利用いただけます。
          </p>
        </div>

        <!-- 単発登録フォーム -->
        <div id="single-registration-section">
        <!-- ステップインジケーター -->
        <div class="registration-steps" id="registration-steps" style="display: none;">
            <div class="step-indicator active" data-step="1">
                <div class="step-number">1</div>
                <div class="step-label">基本情報</div>
            </div>
            <div class="step-indicator" data-step="2">
                <div class="step-number">2</div>
                <div class="step-label">詳細情報</div>
            </div>
            <div class="step-indicator" data-step="3">
                <div class="step-number">3</div>
                <div class="step-label">確認</div>
            </div>
        </div>

        <!-- ステップ1: 基本情報 -->
        <div class="form-step active" data-step="1" role="tabpanel" aria-labelledby="step-1-tab">
        <!-- 選手プロフィール公開情報 -->
        <div class="form-section">
          <h4><?php echo aidunite_render_theme_icon('notification_add', ['width' => '20', 'height' => '20'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 選手プロフィール公開情報</h4>
          <p class="text-muted">これらの情報は他のユーザーにも表示されます</p>

          <div class="form-row-2">
            <div class="form-group">
              <label for="player_nickname">ニックネーム <span class="required">*</span></label>
              <input type="text" id="player_nickname" name="player_nickname" class="form-control" required placeholder="表示用のニックネームを入力">
              <small class="form-text text-muted">※ログイン用ユーザー名は自動生成されます</small>
            </div>

            <div class="form-group">
              <label for="player_position">ポジション</label>
              <select id="player_position" name="player_position" class="form-control">
                <option value="">ポジションを選択</option>
                <option value="PG">PG（ポイントガード）</option>
                <option value="SG">SG（シューティングガード）</option>
                <option value="SF">SF（スモールフォワード）</option>
                <option value="PF">PF（パワーフォワード）</option>
                <option value="C">C（センター）</option>
              </select>
            </div>
          </div>

          <div class="form-row-2">
            <div class="form-group">
              <label for="player_height">身長 (cm)</label>
              <input type="number" id="player_height" name="player_height" class="form-control" min="100" max="250" placeholder="身長を入力">
            </div>

            <div class="form-group">
              <label for="player_experience">バスケットボール経験年数</label>
              <select id="player_experience" name="player_experience" class="form-control">
                <option value="">経験年数を選択</option>
                <option value="0">初心者（0年）</option>
                <option value="1">1年未満</option>
                <option value="2">1-2年</option>
                <option value="3">2-3年</option>
                <option value="4">3-4年</option>
                <option value="5">4-5年</option>
                <option value="6">5年以上</option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label for="player_club_team">所属クラブチーム名（任意）</label>
            <input type="text" id="player_club_team" name="player_club_team" class="form-control" placeholder="小学生で所属していたクラブチームがあれば入力">
            <small class="form-text text-muted">例：○○バスケットボールクラブ</small>
          </div>
        </div>

        <!-- 管理者・保護者専用情報 -->
        <div class="form-section">
          <h4>管理者・保護者専用情報</h4>
          <p class="text-muted">これらの情報は管理者と保護者のみが閲覧できます</p>

          <!-- 所属選手 -->
          <h5 style="margin-top: 1.5rem; margin-bottom: 1rem; font-size: 1rem; font-weight: 600; color: var(--text-primary);">所属選手</h5>

          <div class="form-row-2">
            <div class="form-group">
              <label for="player_name_sei">性 <span class="required">*</span></label>
              <input type="text" id="player_name_sei" name="player_name_sei" class="form-control" required placeholder="姓を入力" aria-required="true">
              <span class="field-error" id="error_player_name_sei" style="display: none;"></span>
            </div>

            <div class="form-group">
              <label for="player_name_mei">名 <span class="required">*</span></label>
              <input type="text" id="player_name_mei" name="player_name_mei" class="form-control" required placeholder="名を入力" aria-required="true">
              <span class="field-error" id="error_player_name_mei" style="display: none;"></span>
            </div>
          </div>

          <div class="form-row-2">
            <div class="form-group">
              <label for="player_kana_sei">フリガナ（姓）</label>
              <input type="text" id="player_kana_sei" name="player_kana_sei" class="form-control" placeholder="フリガナ（姓）を入力">
            </div>

            <div class="form-group">
              <label for="player_kana_mei">フリガナ（名）</label>
              <input type="text" id="player_kana_mei" name="player_kana_mei" class="form-control" placeholder="フリガナ（名）を入力">
            </div>
          </div>

          <input type="hidden" id="player_name" name="player_name">
          <input type="hidden" id="player_kana" name="player_kana">

          <div class="form-row-2">
            <div class="form-group">
              <label for="player_birth_date">生年月日 <span class="required">*</span></label>
              <input type="date" id="player_birth_date" name="player_birth_date" class="form-control" required aria-required="true">
              <span class="field-error" id="error_player_birth_date" style="display: none;"></span>
            </div>

            <div class="form-group">
              <label for="player_grade_display">学年（自動計算）</label>
              <input type="text" id="player_grade_display" class="form-control" readonly placeholder="生年月日を入力すると自動で表示されます" aria-label="学年（自動計算）">
              <input type="hidden" id="player_grade" name="player_grade">
            </div>
          </div>

          <div class="form-group">
            <label for="player_email">選手メールアドレス（任意）</label>
            <input type="email" id="player_email" name="player_email" class="form-control" placeholder="必要に応じて選手本人のメールアドレスを入力">
          </div>

          <?php if ($is_minor_team): ?>
          <!-- 区切り線 -->
          <hr style="margin: 2rem 0; border: none; border-top: 2px solid #dee2e6;">

          <!-- 保護者 -->
          <h5 style="margin-top: 0; margin-bottom: 1rem; font-size: 1rem; font-weight: 600; color: var(--text-primary);">保護者</h5>

          <div class="form-row-2">
            <div class="form-group">
              <label for="parent_name_sei">性</label>
              <input type="text" id="parent_name_sei" name="parent_name_sei" class="form-control" placeholder="保護者の姓を入力">
            </div>

            <div class="form-group">
              <label for="parent_name_mei">名</label>
              <input type="text" id="parent_name_mei" name="parent_name_mei" class="form-control" placeholder="保護者の名を入力">
            </div>
          </div>

          <div class="form-row-2">
            <div class="form-group">
              <label for="parent_email">保護者メールアドレス <span class="required">*</span></label>
              <input type="email" id="parent_email" name="parent_email" class="form-control" required placeholder="保護者のメールアドレスを入力" aria-required="true">
              <small class="form-text text-muted">保護者への招待メール送信に使用します</small>
              <span class="field-error" id="error_parent_email" style="display: none;"></span>
            </div>
            <div class="form-group">
              <label for="parent_emergency_contact">緊急連絡先電話番号（任意）</label>
              <input type="tel" id="parent_emergency_contact" name="parent_emergency_contact" class="form-control" placeholder="保護者の緊急連絡先（必要な場合のみ）">
            </div>
          </div>

          <input type="hidden" id="parent_name" name="parent_name">
          <?php endif; ?>
        </div>

        <?php if (!$is_minor_team): ?>
        <!-- 成人チーム用緊急連絡先セクション -->
        <div class="form-section">
          <h4><?php echo aidunite_render_theme_icon('contact_mail', ['width' => '20', 'height' => '20'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 緊急連絡先情報</h4>
          <p class="text-muted">緊急時の連絡先を入力してください</p>

          <div class="form-row-2">
            <div class="form-group">
              <label for="emergency_contact_name">緊急連絡先氏名</label>
              <input type="text" id="emergency_contact_name" name="emergency_contact_name" class="form-control" placeholder="緊急連絡先の氏名を入力">
            </div>

            <div class="form-group">
              <label for="emergency_contact_relationship">続柄</label>
              <select id="emergency_contact_relationship" name="emergency_contact_relationship" class="form-control">
                <option value="">続柄を選択</option>
                <option value="家族">家族</option>
                <option value="友人">友人</option>
                <option value="同僚">同僚</option>
                <option value="その他">その他</option>
              </select>
            </div>
          </div>

                      <div class="form-row-2">
                        <div class="form-group">
                          <label for="emergency_contact_phone">緊急連絡先電話番号</label>
                          <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone" class="form-control" placeholder="緊急連絡先の電話番号">
                        </div>
                      </div>
        </div>
        <?php endif; ?>

        <div class="form-group">
          <label for="player_notes">備考</label>
          <textarea id="player_notes" name="player_notes" class="form-control" rows="4" placeholder="その他の情報があれば入力してください"></textarea>
        </div>
        </div>
        <!-- ステップ2のナビゲーション -->
        <div class="step-navigation">
            <button type="button" class="btn btn-secondary" id="prev-step-2" onclick="goToStep(1)">← 戻る</button>
            <button type="button" class="btn btn-primary" id="next-step-2" onclick="goToStep(3)">次へ →</button>
        </div>
        </div>

        <!-- ステップ3: 確認 -->
        <div class="form-step" data-step="3" role="tabpanel" aria-labelledby="step-3-tab" style="display: none;">
        <div class="form-section">
            <h4><?php echo aidunite_render_theme_icon('list_alt_add', ['width' => '20', 'height' => '20'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 入力内容の確認</h4>
            <div class="confirmation-summary" id="confirmation-summary">
                <!-- JavaScriptで動的に生成 -->
            </div>
        </div>

        <!-- プライバシー同意 -->
        <div class="privacy-consent-section">
          <div class="checkbox-group">
            <input type="checkbox" id="privacy_consent" name="privacy_consent" required aria-required="true">
            <label for="privacy_consent" class="checkbox-label">
              <span class="checkbox-custom"></span>
              <span class="checkbox-text">
                登録することで、<a href="/privacy-policy" target="_blank" class="terms-link">プライバシーポリシー</a>と利用目的に同意します。
                <span class="required">*</span>
              </span>
            </label>
          </div>
        </div>

        <!-- ステップ3のナビゲーション -->
        <div class="step-navigation">
            <button type="button" class="btn btn-secondary" id="prev-step-3" onclick="goToStep(2)">← 戻る</button>
            <button type="submit" class="btn btn-primary" id="player-submit-btn">
              <?php echo aidunite_render_theme_icon('basketball', ['width' => '20', 'height' => '20'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
              選手を登録
            </button>
        </div>
        </div>
      </form>
        <!-- プライバシー同意 -->
        <div class="privacy-consent-section">
          <div class="checkbox-group">
            <input type="checkbox" id="privacy_consent" name="privacy_consent" required>
            <label for="privacy_consent" class="checkbox-label">
              <span class="checkbox-custom"></span>
              <span class="checkbox-text">
                登録することで、<a href="/privacy-policy" target="_blank" class="terms-link">プライバシーポリシー</a>と利用目的に同意します。
                <span class="required">*</span>
              </span>
            </label>
          </div>
        </div>

        <button type="submit" class="btn btn-primary" id="player-submit-btn">
          <?php echo aidunite_render_theme_icon('basketball', ['width' => '20', 'height' => '20'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
          選手を登録
        </button>
      </div>
      <!-- まとめ登録フォーム -->
      <div id="bulk-registration-section" style="display: none;">
        <div class="form-section">
          <h4><?php echo aidunite_render_theme_icon('list_alt_add', ['width' => '20', 'height' => '20'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> まとめて複数人を登録</h4>
          <p class="text-muted">テーブル形式で複数の選手情報を一度に入力できます。空行は自動的にスキップされます。</p>

          <div class="bulk-registration-table-container">
            <table id="bulk-registration-table" class="bulk-registration-table">
              <thead>
                <tr>
                  <th>選手名（姓）<span class="required">*</span></th>
                  <th>選手名（名）<span class="required">*</span></th>
                  <th>フリガナ（姓）</th>
                  <th>フリガナ（名）</th>
                  <th>生年月日<span class="required">*</span></th>
                  <th>ニックネーム</th>
                  <th>ポジション</th>
                  <?php if ($is_minor_team): ?>
                  <th>保護者メール<span class="required">*</span></th>
                  <?php endif; ?>
                  <th>操作</th>
                </tr>
              </thead>
              <tbody id="bulk-registration-tbody">
                <!-- 行はJavaScriptで動的に追加 -->
              </tbody>
            </table>

            <div class="bulk-registration-actions">
              <button type="button" class="btn btn-secondary" id="add-bulk-row-btn" onclick="addBulkRow()">
                <?php echo aidunite_render_theme_icon('add', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 行を追加
              </button>
              <button type="button" class="btn btn-secondary" id="remove-all-rows-btn" onclick="removeAllBulkRows()">
                <?php echo aidunite_render_theme_icon('delete_forever', ['width' => '18', 'height' => '18'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> すべて削除
              </button>
            </div>
          </div>

          <!-- プライバシー同意 -->
          <div class="privacy-consent-section" style="margin-top: 2rem;">
            <div class="checkbox-group">
              <input type="checkbox" id="privacy_consent_bulk" name="privacy_consent_bulk" required>
              <label for="privacy_consent_bulk" class="checkbox-label">
                <span class="checkbox-custom"></span>
                <span class="checkbox-text">
                  登録することで、<a href="/privacy-policy" target="_blank" class="terms-link">プライバシーポリシー</a>と利用目的に同意します。
                  <span class="required">*</span>
                </span>
              </label>
            </div>
          </div>

          <button type="submit" class="btn btn-primary" id="bulk-submit-btn">
            <?php echo aidunite_render_theme_icon('basketball', ['width' => '20', 'height' => '20'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            まとめて登録
          </button>
        </div>
      </form>
    </div>
  </section>

<?php
if (function_exists('aidunite_web_app_page_shell_close')) {
    aidunite_web_app_page_shell_close();
} else {
    echo '</div>';
}
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('player-registration-form');
    const submitBtn = document.getElementById('player-submit-btn');
    const birthDateInput = document.getElementById('player_birth_date');
    const gradeDisplay = document.getElementById('player_grade_display');
    const gradeHidden = document.getElementById('player_grade');
    const privacyConsent = document.getElementById('privacy_consent');

    // 学年自動計算関数
    function calculateGrade(birthDate) {
        if (!birthDate) return '';

        const today = new Date();
        const birth = new Date(birthDate);
        const age = today.getFullYear() - birth.getFullYear();
        const monthDiff = today.getMonth() - birth.getMonth();

        // 学年計算（4月1日基準）
        let schoolAge = age;
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
            schoolAge = age - 1;
        }

        // 学年判定
        if (schoolAge < 6) {
            return '未就学';
        } else if (schoolAge <= 12) {
            const grade = schoolAge - 5;
            return `小学${grade}年生`;
        } else if (schoolAge <= 15) {
            const grade = schoolAge - 12;
            return `中学${grade}年生`;
        } else if (schoolAge <= 18) {
            const grade = schoolAge - 15;
            return `高校${grade}年生`;
        } else if (schoolAge <= 22) {
            return '大学生';
        } else {
            return '社会人';
        }
    }

    // 生年月日変更時の学年自動計算
    birthDateInput.addEventListener('change', function() {
        const grade = calculateGrade(this.value);
        gradeDisplay.value = grade;
        gradeHidden.value = grade;
    });

    // 性・名を結合してhiddenフィールドに設定（選手）
    const playerNameSei = document.getElementById('player_name_sei');
    const playerNameMei = document.getElementById('player_name_mei');
    const playerNameHidden = document.getElementById('player_name');

    function updatePlayerName() {
        const sei = playerNameSei ? playerNameSei.value.trim() : '';
        const mei = playerNameMei ? playerNameMei.value.trim() : '';
        if (playerNameHidden) {
            playerNameHidden.value = (sei + ' ' + mei).trim();
        }
    }

    if (playerNameSei) playerNameSei.addEventListener('input', updatePlayerName);
    if (playerNameMei) playerNameMei.addEventListener('input', updatePlayerName);

    // 性・名を結合してhiddenフィールドに設定（保護者）
    const parentNameSei = document.getElementById('parent_name_sei');
    const parentNameMei = document.getElementById('parent_name_mei');
    const parentNameHidden = document.getElementById('parent_name');

    function updateParentName() {
        const sei = parentNameSei ? parentNameSei.value.trim() : '';
        const mei = parentNameMei ? parentNameMei.value.trim() : '';
        if (parentNameHidden) {
            parentNameHidden.value = (sei + ' ' + mei).trim();
        }
    }

    if (parentNameSei) parentNameSei.addEventListener('input', updateParentName);
    if (parentNameMei) parentNameMei.addEventListener('input', updateParentName);

    // フリガナ自動変換機能（wanakana.js使用）
    // wanakana.jsライブラリを読み込む
    const wanakanaScript = document.createElement('script');
    wanakanaScript.src = 'https://cdn.jsdelivr.net/npm/wanakana@4.0.2/umd/wanakana.min.js';
    wanakanaScript.onload = function() {
        initKanaConversion();
    };
    document.head.appendChild(wanakanaScript);

    function initKanaConversion() {
        const playerKanaSei = document.getElementById('player_kana_sei');
        const playerKanaMei = document.getElementById('player_kana_mei');
        const playerKanaHidden = document.getElementById('player_kana');

        if (typeof wanakana === 'undefined') {
            console.warn('wanakana.jsが読み込まれていません。簡易変換を使用します。');
            initSimpleKanaConversion();
            return;
        }

        // wanakana.jsを使用した高精度なフリガナ変換
        function convertToKatakana(text) {
            if (!text) return '';
            try {
                // 漢字・ひらがなをカタカナに変換
                return wanakana.toKatakana(text);
            } catch (e) {
                console.warn('wanakana変換エラー:', e);
                return text;
            }
        }

        // 性の入力時にフリガナ（姓）を自動変換
        if (playerNameSei && playerKanaSei) {
            let isManualEdit = false;

            playerKanaSei.addEventListener('input', function() {
                isManualEdit = true;
            });

            playerNameSei.addEventListener('blur', function() {
                if (!isManualEdit && !playerKanaSei.value.trim()) {
                    const converted = convertToKatakana(this.value);
                    if (converted && converted !== this.value) {
                        playerKanaSei.value = converted;
                        updatePlayerKana();
                    }
                }
                isManualEdit = false;
            });
        }

        // 名の入力時にフリガナ（名）を自動変換
        if (playerNameMei && playerKanaMei) {
            let isManualEdit = false;

            playerKanaMei.addEventListener('input', function() {
                isManualEdit = true;
            });

            playerNameMei.addEventListener('blur', function() {
                if (!isManualEdit && !playerKanaMei.value.trim()) {
                    const converted = convertToKatakana(this.value);
                    if (converted && converted !== this.value) {
                        playerKanaMei.value = converted;
                        updatePlayerKana();
                    }
                }
                isManualEdit = false;
            });
        }

        // フリガナを結合してhiddenフィールドに設定
        function updatePlayerKana() {
            const kanaSei = playerKanaSei ? playerKanaSei.value.trim() : '';
            const kanaMei = playerKanaMei ? playerKanaMei.value.trim() : '';
            if (playerKanaHidden) {
                playerKanaHidden.value = (kanaSei + ' ' + kanaMei).trim();
            }
        }

        if (playerKanaSei) playerKanaSei.addEventListener('input', updatePlayerKana);
        if (playerKanaMei) playerKanaMei.addEventListener('input', updatePlayerKana);
    }

    // 簡易変換（フォールバック）
    function initSimpleKanaConversion() {
        const playerKanaSei = document.getElementById('player_kana_sei');
        const playerKanaMei = document.getElementById('player_kana_mei');
        const playerKanaHidden = document.getElementById('player_kana');

        function convertToKatakana(text) {
            if (!text) return '';
            return text
                .replace(/[あ-ん]/g, function(match) {
                    return String.fromCharCode(match.charCodeAt(0) + 0x60);
                })
                .replace(/[ア-ン]/g, function(match) {
                    return match;
                });
        }

        if (playerNameSei && playerKanaSei) {
            let isManualEdit = false;
            playerKanaSei.addEventListener('input', function() {
                isManualEdit = true;
            });
            playerNameSei.addEventListener('blur', function() {
                if (!isManualEdit && !playerKanaSei.value.trim()) {
                    const converted = convertToKatakana(this.value);
                    if (converted) {
                        playerKanaSei.value = converted;
                        updatePlayerKana();
                    }
                }
                isManualEdit = false;
            });
        }

        if (playerNameMei && playerKanaMei) {
            let isManualEdit = false;
            playerKanaMei.addEventListener('input', function() {
                isManualEdit = true;
            });
            playerNameMei.addEventListener('blur', function() {
                if (!isManualEdit && !playerKanaMei.value.trim()) {
                    const converted = convertToKatakana(this.value);
                    if (converted) {
                        playerKanaMei.value = converted;
                        updatePlayerKana();
                    }
                }
                isManualEdit = false;
            });
        }

        function updatePlayerKana() {
            const kanaSei = playerKanaSei ? playerKanaSei.value.trim() : '';
            const kanaMei = playerKanaMei ? playerKanaMei.value.trim() : '';
            if (playerKanaHidden) {
                playerKanaHidden.value = (kanaSei + ' ' + kanaMei).trim();
            }
        }

        if (playerKanaSei) playerKanaSei.addEventListener('input', updatePlayerKana);
        if (playerKanaMei) playerKanaMei.addEventListener('input', updatePlayerKana);
    }


    // 単発登録フォームのバリデーション
    if (form) {
        form.addEventListener('submit', function(e) {
            const mode = document.getElementById('registration_mode').value;

            if (mode === 'single') {
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
                const nameSei = document.getElementById('player_name_sei');
                const nameMei = document.getElementById('player_name_mei');
                const birthDate = document.getElementById('player_birth_date');
                const parentEmail = document.getElementById('parent_email');

                if (!nameSei || !nameSei.value.trim()) {
                    showFieldError(nameSei, '選手名（姓）を入力してください');
                    hasError = true;
                }
                if (!nameMei || !nameMei.value.trim()) {
                    showFieldError(nameMei, '選手名（名）を入力してください');
                    hasError = true;
                }
                if (!birthDate || !birthDate.value.trim()) {
                    showFieldError(birthDate, '生年月日を入力してください');
                    hasError = true;
                }
                if (<?php echo $is_minor_team ? 'true' : 'false'; ?> && (!parentEmail || !parentEmail.value.trim())) {
                    showFieldError(parentEmail, '保護者メールアドレスを入力してください');
                    hasError = true;
                }

                if (hasError) {
                    e.preventDefault();
                    showToast('入力エラーがあります。各フィールドを確認してください。', 'error');
                    return false;
                }
            }
        });
    }

    // トースト通知の表示
    const urlParams = new URLSearchParams(window.location.search);
    const toastType = urlParams.get('toast');
    const toastMessage = urlParams.get('message');

    if (toastType && toastMessage) {
        showToast(decodeURIComponent(toastMessage), toastType);

        // URLからパラメータを削除（ブラウザ履歴に残さない）
        if (window.history && window.history.replaceState) {
            const url = new URL(window.location);
            url.searchParams.delete('toast');
            url.searchParams.delete('message');
            window.history.replaceState({}, '', url);
        }

        // 成功時はリダイレクトしない（同じページに留まる）
        // 複数の選手を続けて登録できるようにする
        // if (toastType === 'success') {
        //     setTimeout(() => {
        //         window.location.href = '<?php echo home_url('/mypage'); ?>';
        //     }, 2000);
        // }
    }
});

function getToastIconHtml(type) {
    const iconMap = { success: 'check_circle', error: 'brightness_alert', info: 'info' };
    if (typeof AidUniteThemeIcons !== 'undefined') {
        return AidUniteThemeIcons.html(iconMap[type] || 'info', 28);
    }
    return '';
}

// トースト通知を表示する関数（画面中央表示）
function showToast(message, type = 'info') {
    // 既存のトーストがあれば削除
    const existingToast = document.querySelector('.player-registration-toast');
    if (existingToast) {
        existingToast.remove();
    }

    const toast = document.createElement('div');
    toast.className = 'player-registration-toast';
    toast.setAttribute('data-type', type);

    const icon = getToastIconHtml(type);
    const title = type === 'success' ? '選手登録が完了しました！' : type === 'error' ? '登録に失敗しました' : 'お知らせ';

    toast.innerHTML = `
        <div class="player-registration-toast-card">
            <div class="player-registration-toast-icon">${icon}</div>
            <div class="player-registration-toast-message">
                <div class="toast-title">${title}</div>
                <div class="toast-content">${escapeHtml(message)}</div>
            </div>
        </div>
    `;

    document.body.appendChild(toast);

    // 成功時は2秒後にリダイレクト、エラー時は5秒後に自動削除
    const autoHideDelay = type === 'success' ? 2000 : 5000;
    setTimeout(() => {
        if (toast.parentElement) {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-20px)';
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.remove();
                }
            }, 300);
        }
    }, autoHideDelay);
}

// HTMLエスケープ関数
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// 登録モード切り替え
function switchRegistrationMode(mode) {
    const hiddenMode = document.getElementById('registration_mode');
    const singleSection = document.getElementById('single-registration-section');
    const bulkSection = document.getElementById('bulk-registration-section');
    const submitBtn = document.getElementById('player-submit-btn');
    const bulkSubmitBtn = document.getElementById('bulk-submit-btn');

    if (hiddenMode) {
        hiddenMode.value = mode;
    }

    if (mode === 'bulk') {
        if (singleSection) singleSection.style.display = 'none';
        if (bulkSection) bulkSection.style.display = 'block';
        if (submitBtn) submitBtn.style.display = 'none';
        if (bulkSubmitBtn) bulkSubmitBtn.style.display = 'block';
        // 初期行を追加
        if (document.getElementById('bulk-registration-tbody').children.length === 0) {
            addBulkRow();
        }
    } else {
        if (singleSection) singleSection.style.display = 'block';
        if (bulkSection) bulkSection.style.display = 'none';
        if (submitBtn) submitBtn.style.display = 'block';
        if (bulkSubmitBtn) bulkSubmitBtn.style.display = 'none';
    }
}

// まとめ登録用の行を追加
let bulkRowIndex = 0;
function addBulkRow() {
    const tbody = document.getElementById('bulk-registration-tbody');
    const row = document.createElement('tr');
    row.dataset.rowIndex = bulkRowIndex++;

    const isMinorTeam = <?php echo $is_minor_team ? 'true' : 'false'; ?>;

    row.innerHTML = `
        <td>
            <input type="text" name="bulk_players[${row.dataset.rowIndex}][player_name_sei]" class="form-control bulk-input" placeholder="姓">
            <span class="field-error" style="display: none;"></span>
        </td>
        <td>
            <input type="text" name="bulk_players[${row.dataset.rowIndex}][player_name_mei]" class="form-control bulk-input" placeholder="名">
            <span class="field-error" style="display: none;"></span>
        </td>
        <td>
            <input type="text" name="bulk_players[${row.dataset.rowIndex}][player_kana_sei]" class="form-control bulk-input" placeholder="フリガナ（姓）">
        </td>
        <td>
            <input type="text" name="bulk_players[${row.dataset.rowIndex}][player_kana_mei]" class="form-control bulk-input" placeholder="フリガナ（名）">
        </td>
        <td>
            <input type="date" name="bulk_players[${row.dataset.rowIndex}][player_birth_date]" class="form-control bulk-input bulk-birth-date" placeholder="生年月日">
            <span class="field-error" style="display: none;"></span>
        </td>
        <td>
            <input type="text" name="bulk_players[${row.dataset.rowIndex}][player_nickname]" class="form-control bulk-input" placeholder="ニックネーム">
        </td>
        <td>
            <select name="bulk_players[${row.dataset.rowIndex}][player_position]" class="form-control bulk-input">
                <option value="">選択</option>
                <option value="PG">PG</option>
                <option value="SG">SG</option>
                <option value="SF">SF</option>
                <option value="PF">PF</option>
                <option value="C">C</option>
            </select>
        </td>
        ${isMinorTeam ? `
        <td>
            <input type="email" name="bulk_players[${row.dataset.rowIndex}][parent_email]" class="form-control bulk-input" placeholder="保護者メール">
            <span class="field-error" style="display: none;"></span>
        </td>
        ` : ''}
        <td class="row-actions">
            <button type="button" class="btn-remove-row" onclick="removeBulkRow(this)" aria-label="行を削除">${typeof AidUniteThemeIcons !== 'undefined' ? AidUniteThemeIcons.html('delete_forever', 18) : ''}</button>
        </td>
    `;

    tbody.appendChild(row);

    // 生年月日変更時の学年自動計算
    const birthDateInput = row.querySelector('.bulk-birth-date');
    if (birthDateInput) {
        birthDateInput.addEventListener('change', function() {
            // まとめ登録では学年表示は省略（必要に応じて追加可能）
        });
    }
}

// まとめ登録用の行を削除
function removeBulkRow(btn) {
    const row = btn.closest('tr');
    if (row) {
        row.remove();
    }
}

// すべての行を削除
function removeAllBulkRows() {
    if (confirm('すべての行を削除しますか？')) {
        const tbody = document.getElementById('bulk-registration-tbody');
        tbody.innerHTML = '';
        bulkRowIndex = 0;
    }
}

// フォーム送信前のバリデーション（まとめ登録用）
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('player-registration-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const mode = document.getElementById('registration_mode').value;

            if (mode === 'bulk') {
                // まとめ登録モードのバリデーション
                const tbody = document.getElementById('bulk-registration-tbody');
                const rows = tbody.querySelectorAll('tr');
                let hasError = false;

                // すべてのエラーメッセージをクリア
                tbody.querySelectorAll('.field-error').forEach(err => {
                    err.style.display = 'none';
                    err.textContent = '';
                });
                tbody.querySelectorAll('.error').forEach(input => {
                    input.classList.remove('error');
                });

                rows.forEach((row, index) => {
                    const nameSei = row.querySelector('input[name*="[player_name_sei]"]');
                    const nameMei = row.querySelector('input[name*="[player_name_mei]"]');
                    const birthDate = row.querySelector('input[name*="[player_birth_date]"]');
                    const parentEmail = row.querySelector('input[name*="[parent_email]"]');

                    // 空行チェック
                    const isEmpty = (!nameSei || !nameSei.value.trim()) &&
                                   (!nameMei || !nameMei.value.trim()) &&
                                   (!birthDate || !birthDate.value.trim()) &&
                                   (!parentEmail || !parentEmail.value.trim());

                    if (isEmpty) {
                        return; // 空行はスキップ
                    }

                    // 必須項目チェック
                    if (!nameSei || !nameSei.value.trim()) {
                        showFieldError(nameSei, '選手名（姓）は必須です');
                        hasError = true;
                    }
                    if (!nameMei || !nameMei.value.trim()) {
                        showFieldError(nameMei, '選手名（名）は必須です');
                        hasError = true;
                    }
                    if (!birthDate || !birthDate.value.trim()) {
                        showFieldError(birthDate, '生年月日は必須です');
                        hasError = true;
                    }
                    if (<?php echo $is_minor_team ? 'true' : 'false'; ?> && (!parentEmail || !parentEmail.value.trim())) {
                        showFieldError(parentEmail, '保護者メールアドレスは必須です');
                        hasError = true;
                    }
                });

                if (hasError) {
                    e.preventDefault();
                    showToast('入力エラーがあります。各フィールドを確認してください。', 'error');
                    return false;
                }

                // プライバシー同意チェック
                const privacyConsent = document.getElementById('privacy_consent_bulk');
                if (!privacyConsent || !privacyConsent.checked) {
                    e.preventDefault();
                    showToast('プライバシーポリシーへの同意が必要です。', 'error');
                    return false;
                }
            } else {
                // 単発登録モードのバリデーション（既存の処理）
                const privacyConsent = document.getElementById('privacy_consent');
                if (!privacyConsent || !privacyConsent.checked) {
                    e.preventDefault();
                    showToast('プライバシーポリシーへの同意が必要です。', 'error');
                    return false;
                }
            }
        });
    }
});

// フィールドエラーを表示
function showFieldError(input, message) {
    if (!input) return;

    input.classList.add('error');
    input.setAttribute('aria-invalid', 'true');
    const errorSpan = input.parentElement.querySelector('.field-error');
    if (errorSpan) {
        errorSpan.textContent = message;
        errorSpan.style.display = 'block';
        errorSpan.setAttribute('role', 'alert');
    }
}

// ステップ管理
let currentStep = 1;
const totalSteps = 3;

function goToStep(step) {
    if (step < 1 || step > totalSteps) return;

    // 現在のステップを非表示
    const currentStepElement = document.querySelector(`.form-step[data-step="${currentStep}"]`);
    if (currentStepElement) {
        currentStepElement.style.display = 'none';
        currentStepElement.classList.remove('active');
    }

    // 新しいステップを表示
    const newStepElement = document.querySelector(`.form-step[data-step="${step}"]`);
    if (newStepElement) {
        newStepElement.style.display = 'block';
        newStepElement.classList.add('active');
    }

    // ステップインジケーターを更新
    document.querySelectorAll('.step-indicator').forEach((indicator, index) => {
        const stepNum = index + 1;
        indicator.classList.remove('active', 'completed');
        if (stepNum === step) {
            indicator.classList.add('active');
        } else if (stepNum < step) {
            indicator.classList.add('completed');
        }
    });

    currentStep = step;

    // 確認画面の場合はサマリーを更新
    if (step === 3) {
        updateConfirmationSummary();
    }
}

// 確認画面のサマリーを更新
function updateConfirmationSummary() {
    const summary = document.getElementById('confirmation-summary');
    if (!summary) return;

    const data = {
        'ニックネーム': document.getElementById('player_nickname')?.value || '-',
        'ポジション': document.getElementById('player_position')?.value || '-',
        '身長': document.getElementById('player_height')?.value ? document.getElementById('player_height').value + 'cm' : '-',
        '選手名（姓）': document.getElementById('player_name_sei')?.value || '-',
        '選手名（名）': document.getElementById('player_name_mei')?.value || '-',
        '生年月日': document.getElementById('player_birth_date')?.value || '-',
        '学年': document.getElementById('player_grade_display')?.value || '-',
    };

    <?php if ($is_minor_team): ?>
    data['保護者メール'] = document.getElementById('parent_email')?.value || '-';
    <?php endif; ?>

    let html = '';
    for (const [label, value] of Object.entries(data)) {
        if (value !== '-') {
            html += `
                <div class="confirmation-summary-item">
                    <span class="confirmation-summary-label">${label}:</span>
                    <span class="confirmation-summary-value">${escapeHtml(value)}</span>
                </div>
            `;
        }
    }

    summary.innerHTML = html || '<p>入力内容がありません。</p>';
}

// 入力候補機能（過去の登録データから候補を表示）
function initAutocomplete() {
    // ニックネームの候補
    const nicknameInput = document.getElementById('player_nickname');
    if (nicknameInput) {
        // 過去の登録データから候補を取得（AJAXで実装可能）
        // ここでは簡易的な実装として、datalistを使用
        const datalist = document.createElement('datalist');
        datalist.id = 'nickname-suggestions';
        // 実際の実装では、AJAXで過去の登録データを取得して候補を追加
        nicknameInput.setAttribute('list', 'nickname-suggestions');
        document.body.appendChild(datalist);
    }

    // ポジションの候補は既にselectで実装済み
}

// アクセシビリティの強化
function enhanceAccessibility() {
    // 必須項目にaria-requiredを追加
    document.querySelectorAll('.form-control[required]').forEach(input => {
        input.setAttribute('aria-required', 'true');
    });

    // エラーメッセージにrole="alert"を追加
    document.querySelectorAll('.field-error').forEach(error => {
        error.setAttribute('role', 'alert');
    });

    // フォームセクションにaria-labelを追加
    document.querySelectorAll('.form-section').forEach((section, index) => {
        const heading = section.querySelector('h4');
        if (heading) {
            section.setAttribute('aria-labelledby', `section-heading-${index}`);
            heading.id = `section-heading-${index}`;
        }
    });
}

// 初期化
document.addEventListener('DOMContentLoaded', function() {
    // ステップ形式を有効化（オプション）
    const enableWizard = false; // デフォルトは無効（既存のUIを維持）

    if (enableWizard) {
        const stepsIndicator = document.getElementById('registration-steps');
        if (stepsIndicator) {
            stepsIndicator.style.display = 'flex';
        }
        goToStep(1);
    }

    // 入力候補機能を初期化
    initAutocomplete();

    // アクセシビリティを強化
    enhanceAccessibility();
});
</script>

<?php get_footer(); ?>
