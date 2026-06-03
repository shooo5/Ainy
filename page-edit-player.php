<?php
/**
 * Template Name: 選手編集ページ
 */

// 統一認証・権限チェック（チーム代表者のみ）
require_once get_template_directory() . '/functions/common/auth-middleware.php';
$auth_result = AidUniteAuthMiddleware::require([
    'roles' => ['team_leader', 'administrator'],
    'redirect' => true,
]);
if (!$auth_result->is_valid()) {
    // リダイレクトは自動で実行される
    return;
}

$current_user_id = $auth_result->user_id;
$user_info = aidunite_get_user_info($current_user_id);
$effective_role = $user_info['user_type'] ?? 'general';

// チームID取得
$team_id = $user_info['team_id'] ?? 0;
if (!$team_id) {
    wp_die('チーム情報が見つかりません。');
}

$player_id = intval($_GET['player_id'] ?? 0);
if (!$player_id) {
    wp_die('選手IDが指定されていません。');
}

// 選手の存在チェック（ユーザーメタベース）
$player_user = get_userdata($player_id);
if (!$player_user) {
    wp_die('選手が見つかりません。');
}

// 自分のチームの選手か確認
$player_team_id = get_user_meta($player_id, 'team_id', true);
if ($player_team_id != $team_id) {
    wp_die('この選手を編集する権限がありません。');
}

// 選手のユーザータイプ確認
$player_user_type = get_user_meta($player_id, 'aidunite_role', true) ?: get_user_meta($player_id, 'user_type', true);
if ($player_user_type !== 'player') {
    wp_die('指定されたユーザーは選手ではありません。');
}

// フォーム送信処理
$update_success = false;
if ($_POST && isset($_POST['update_player'])) {
    // CSRF対策（統一版）
    $nonce_result = AidUniteAuthMiddleware::verify_nonce('player_edit_nonce', 'update_player_info');
    if (!is_wp_error($nonce_result)) {
        // 選手名（性・名）
        $player_name_sei = sanitize_text_field($_POST['player_name_sei'] ?? '');
        $player_name_mei = sanitize_text_field($_POST['player_name_mei'] ?? '');
        $player_name = trim($player_name_sei . ' ' . $player_name_mei);

        // フリガナ（性・名）
        $player_kana_sei = sanitize_text_field($_POST['player_kana_sei'] ?? '');
        $player_kana_mei = sanitize_text_field($_POST['player_kana_mei'] ?? '');
        $player_name_kana = trim($player_kana_sei . ' ' . $player_kana_mei);

        // ユーザーメタを更新
        $meta_fields = [
            'player_name' => $player_name,
            'player_name_sei' => $player_name_sei,
            'player_name_mei' => $player_name_mei,
            'player_name_kana' => $player_name_kana,
            'player_kana_sei' => $player_kana_sei,
            'player_kana_mei' => $player_kana_mei,
            'player_nickname' => sanitize_text_field($_POST['player_nickname'] ?? ''),
            'player_birth_date' => sanitize_text_field($_POST['player_birth_date'] ?? ''),
            'player_grade' => sanitize_text_field($_POST['player_grade'] ?? ''),
            'player_position' => sanitize_text_field($_POST['player_position'] ?? ''),
            'player_club_team' => sanitize_text_field($_POST['player_club_team'] ?? ''),
            'player_email' => function_exists('aidunite_normalize_email') ? aidunite_normalize_email($_POST['player_email'] ?? '') : sanitize_email($_POST['player_email'] ?? ''),
            'player_height' => sanitize_text_field($_POST['player_height'] ?? ''),
            'player_weight' => sanitize_text_field($_POST['player_weight'] ?? ''),
        ];

        foreach ($meta_fields as $key => $value) {
            update_user_meta($player_id, $key, $value);
        }

        // 学年自動計算（生年月日から）
        if (!empty($meta_fields['player_birth_date']) && empty($meta_fields['player_grade'])) {
            require_once(get_template_directory() . '/functions/parent/parent-functions.php');
            if (function_exists('aidunite_calculate_grade_from_birthdate')) {
                $grade = aidunite_calculate_grade_from_birthdate($meta_fields['player_birth_date']);
                update_user_meta($player_id, 'player_grade', $grade);
            }
        }

        // 保護者情報（未成年チームの場合）
        $team_category = get_post_meta($team_id, 'team_category', true);
        $minor_categories = ['小学生', '中学生', '高校生'];
        $is_minor_team = in_array($team_category, $minor_categories);

        if ($is_minor_team) {
            $parent_name_sei = sanitize_text_field($_POST['parent_name_sei'] ?? '');
            $parent_name_mei = sanitize_text_field($_POST['parent_name_mei'] ?? '');
            $parent_name = trim($parent_name_sei . ' ' . $parent_name_mei);

            $parent_meta_fields = [
                'parent_name' => $parent_name,
                'parent_name_sei' => $parent_name_sei,
                'parent_name_mei' => $parent_name_mei,
                'parent_email' => function_exists('aidunite_normalize_email') ? aidunite_normalize_email($_POST['parent_email'] ?? '') : sanitize_email($_POST['parent_email'] ?? ''),
                'parent_emergency_contact' => sanitize_text_field($_POST['parent_emergency_contact'] ?? ''),
            ];

            foreach ($parent_meta_fields as $key => $value) {
                update_user_meta($player_id, $key, $value);
            }
        }

        $update_success = true;
        // リダイレクト（トースト通知はJavaScriptで表示）
        wp_redirect(home_url('/team-members?updated=1&player_id=' . $player_id));
        exit;
    } else {
        // CSRF検証失敗
        wp_die('セキュリティチェックに失敗しました。', 'エラー', ['response' => 403]);
    }
}

// 現在の選手情報を取得（ユーザーメタベース）
$player_name_sei = get_user_meta($player_id, 'player_name_sei', true);
$player_name_mei = get_user_meta($player_id, 'player_name_mei', true);
$player_name = get_user_meta($player_id, 'player_name', true);
if (empty($player_name_sei) && empty($player_name_mei) && !empty($player_name)) {
    // 後方互換性: player_nameから分割を試みる
    $name_parts = explode(' ', $player_name, 2);
    $player_name_sei = $name_parts[0] ?? '';
    $player_name_mei = $name_parts[1] ?? '';
}

$player_kana_sei = get_user_meta($player_id, 'player_kana_sei', true);
$player_kana_mei = get_user_meta($player_id, 'player_kana_mei', true);
$player_name_kana = get_user_meta($player_id, 'player_name_kana', true);
if (empty($player_kana_sei) && empty($player_kana_mei) && !empty($player_name_kana)) {
    $kana_parts = explode(' ', $player_name_kana, 2);
    $player_kana_sei = $kana_parts[0] ?? '';
    $player_kana_mei = $kana_parts[1] ?? '';
}

$player_nickname = get_user_meta($player_id, 'player_nickname', true);
$player_birth_date = get_user_meta($player_id, 'player_birth_date', true);
$player_grade = get_user_meta($player_id, 'player_grade', true);
$player_position = get_user_meta($player_id, 'player_position', true);
$player_club_team = get_user_meta($player_id, 'player_club_team', true);
$player_email = get_user_meta($player_id, 'player_email', true);
$player_height = get_user_meta($player_id, 'player_height', true);
// 後方互換性のため、旧キーも確認
if (empty($player_height)) {
    $player_height = get_user_meta($player_id, 'height', true);
}
$player_weight = get_user_meta($player_id, 'player_weight', true);
// 後方互換性のため、旧キーも確認
if (empty($player_weight)) {
    $player_weight = get_user_meta($player_id, 'weight', true);
}

// 保護者情報（未成年チームの場合）
$team_category = get_post_meta($team_id, 'team_category', true);
$minor_categories = ['小学生', '中学生', '高校生'];
$is_minor_team = in_array($team_category, $minor_categories);

$parent_name_sei = '';
$parent_name_mei = '';
$parent_email = '';
$parent_emergency_contact = '';

if ($is_minor_team) {
    $parent_name_sei = get_user_meta($player_id, 'parent_name_sei', true);
    $parent_name_mei = get_user_meta($player_id, 'parent_name_mei', true);
    $parent_name = get_user_meta($player_id, 'parent_name', true);
    if (empty($parent_name_sei) && empty($parent_name_mei) && !empty($parent_name)) {
        $parent_parts = explode(' ', $parent_name, 2);
        $parent_name_sei = $parent_parts[0] ?? '';
        $parent_name_mei = $parent_parts[1] ?? '';
    }
    $parent_email = get_user_meta($player_id, 'parent_email', true);
    $parent_emergency_contact = get_user_meta($player_id, 'parent_emergency_contact', true);
}

get_header();

if (function_exists('aidunite_web_app_page_shell_open')) {
    aidunite_web_app_page_shell_open([
        'page_class' => 'page-edit-player',
        'title' => '選手情報を編集',
        'subtitle' => '選手の情報を更新します',
        'back_url' => home_url('/team-members'),
    ]);
} else {
    echo '<div class="team-dashboard-container page-edit-player"><div class="dashboard-header"><h1>選手情報を編集</h1><p>選手の情報を更新します</p></div>';
}
?>

  <section class="dashboard-section dashboard-section--flush">
    <div class="main-content-area">
      <form method="post" id="player-edit-form" class="player-edit-form">
        <?php wp_nonce_field('update_player_info', 'player_edit_nonce'); ?>

        <!-- 選手プロフィール公開情報 -->
        <div class="form-section">
          <h3>📋 選手プロフィール公開情報</h3>

          <div class="form-row-2">
            <div class="form-group">
              <label for="player_name_sei">性 <span class="required">*</span></label>
              <input type="text" id="player_name_sei" name="player_name_sei" class="form-control" required value="<?php echo esc_attr($player_name_sei); ?>" placeholder="姓を入力">
            </div>
            <div class="form-group">
              <label for="player_name_mei">名 <span class="required">*</span></label>
              <input type="text" id="player_name_mei" name="player_name_mei" class="form-control" required value="<?php echo esc_attr($player_name_mei); ?>" placeholder="名を入力">
            </div>
          </div>

          <div class="form-row-2">
            <div class="form-group">
              <label for="player_kana_sei">フリガナ（姓）</label>
              <input type="text" id="player_kana_sei" name="player_kana_sei" class="form-control" value="<?php echo esc_attr($player_kana_sei); ?>" placeholder="フリガナ（姓）を入力">
            </div>
            <div class="form-group">
              <label for="player_kana_mei">フリガナ（名）</label>
              <input type="text" id="player_kana_mei" name="player_kana_mei" class="form-control" value="<?php echo esc_attr($player_kana_mei); ?>" placeholder="フリガナ（名）を入力">
            </div>
          </div>

          <div class="form-row-2">
            <div class="form-group">
              <label for="player_nickname">ニックネーム</label>
              <input type="text" id="player_nickname" name="player_nickname" class="form-control" value="<?php echo esc_attr($player_nickname); ?>" placeholder="ニックネームを入力">
            </div>
            <div class="form-group">
              <label for="player_birth_date">生年月日</label>
              <input type="date" id="player_birth_date" name="player_birth_date" class="form-control" value="<?php echo esc_attr($player_birth_date); ?>">
            </div>
          </div>

          <div class="form-row-2">
            <div class="form-group">
              <label for="player_grade">学年</label>
              <input type="text" id="player_grade" name="player_grade" class="form-control" value="<?php echo esc_attr($player_grade); ?>" placeholder="学年（自動計算）" readonly>
              <small class="form-text text-muted">生年月日から自動計算されます</small>
            </div>
            <div class="form-group">
              <label for="player_position">ポジション</label>
              <input type="text" id="player_position" name="player_position" class="form-control" value="<?php echo esc_attr($player_position); ?>" placeholder="ポジションを入力">
            </div>
          </div>

          <div class="form-row-2">
            <div class="form-group">
              <label for="player_club_team">所属クラブチーム名（任意）</label>
              <input type="text" id="player_club_team" name="player_club_team" class="form-control" value="<?php echo esc_attr($player_club_team); ?>" placeholder="小学生で所属していたクラブチームがあれば">
            </div>
            <div class="form-group">
              <label for="player_email">選手本人のメールアドレス（任意）</label>
              <input type="email" id="player_email" name="player_email" class="form-control" value="<?php echo esc_attr($player_email); ?>" placeholder="選手本人のメールアドレス">
            </div>
          </div>

          <div class="form-row-2">
            <div class="form-group">
              <label for="player_height">身長 (cm)</label>
              <input type="number" id="player_height" name="player_height" class="form-control" value="<?php echo esc_attr($player_height); ?>" placeholder="身長を入力" min="100" max="250">
            </div>
            <div class="form-group">
              <label for="player_weight">体重 (kg)</label>
              <input type="number" id="player_weight" name="player_weight" class="form-control" value="<?php echo esc_attr($player_weight); ?>" placeholder="体重を入力" min="20" max="200" step="0.1">
            </div>
          </div>
        </div>

        <?php if ($is_minor_team): ?>
        <!-- 管理者・保護者専用情報（未成年チーム） -->
        <div class="form-section">
          <h3>🔒 管理者・保護者専用情報</h3>

          <h4>所属選手</h4>
          <p class="text-muted">選手の基本情報</p>

          <h4>保護者</h4>
          <div class="form-row-2">
            <div class="form-group">
              <label for="parent_name_sei">保護者名（性）</label>
              <input type="text" id="parent_name_sei" name="parent_name_sei" class="form-control" value="<?php echo esc_attr($parent_name_sei); ?>" placeholder="保護者名（性）">
            </div>
            <div class="form-group">
              <label for="parent_name_mei">保護者名（名）</label>
              <input type="text" id="parent_name_mei" name="parent_name_mei" class="form-control" value="<?php echo esc_attr($parent_name_mei); ?>" placeholder="保護者名（名）">
            </div>
          </div>

          <div class="form-row-2">
            <div class="form-group">
              <label for="parent_email">保護者メールアドレス</label>
              <input type="email" id="parent_email" name="parent_email" class="form-control" value="<?php echo esc_attr($parent_email); ?>" placeholder="保護者のメールアドレス">
            </div>
            <div class="form-group">
              <label for="parent_emergency_contact">緊急連絡先電話番号（任意）</label>
              <input type="tel" id="parent_emergency_contact" name="parent_emergency_contact" class="form-control" value="<?php echo esc_attr($parent_emergency_contact); ?>" placeholder="緊急連絡先電話番号">
            </div>
          </div>
        </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 2rem;">
          <button type="submit" name="update_player" class="btn btn-primary" style="padding: 1em 2em; font-size: 1.1em;">
            💾 保存する
          </button>
          <a href="<?php echo home_url('/team-members'); ?>" class="btn" style="background: var(--text-muted); color: white; padding: 1em 2em; margin-left: 1rem;">
            ← メンバー一覧に戻る
          </a>
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

<style>
.page-edit-player {
  background: var(--bg-light);
  min-height: 100vh;
  padding: 2rem 0;
}

.player-edit-form {
  background: white;
  padding: 2rem;
  border-radius: 12px;
  box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.form-section {
  margin-bottom: 2rem;
  padding-bottom: 2rem;
  border-bottom: 1px solid #e0e0e0;
}

.form-section:last-child {
  border-bottom: none;
}

.form-row-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
  margin-bottom: 1rem;
}

.form-group {
  margin-bottom: 1rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 600;
  color: var(--text-primary);
}

.form-group .form-control {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid var(--border-color);
  border-radius: 6px;
  font-size: 1rem;
}

.form-group .form-control:focus {
  outline: none;
  border-color: #667eea;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-group .form-text {
  font-size: 0.875rem;
  color: var(--text-secondary);
  margin-top: 0.25rem;
}

.required {
  color: var(--danger-color);
}

@media (max-width: 768px) {
  .form-row-2 {
    grid-template-columns: 1fr;
  }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 学年自動計算
    const birthDateInput = document.getElementById('player_birth_date');
    const gradeInput = document.getElementById('player_grade');

    if (birthDateInput && gradeInput) {
        birthDateInput.addEventListener('change', function() {
            const birthDate = new Date(this.value);
            if (!isNaN(birthDate.getTime())) {
                const today = new Date();
                const age = today.getFullYear() - birthDate.getFullYear();
                const monthDiff = today.getMonth() - birthDate.getMonth();

                let schoolAge = age;
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                    schoolAge = age - 1;
                }

                // 4月1日基準で学年計算
                const currentMonth = today.getMonth() + 1;
                if (currentMonth >= 4) {
                    schoolAge = schoolAge - 6;
                } else {
                    schoolAge = schoolAge - 7;
                }

                if (schoolAge < 1) {
                    gradeInput.value = '未就学';
                } else if (schoolAge > 12) {
                    gradeInput.value = '卒業';
                } else {
                    gradeInput.value = schoolAge + '年生';
                }
            }
        });
    }

    // フリガナ自動変換（任意）
    const playerNameSei = document.getElementById('player_name_sei');
    const playerNameMei = document.getElementById('player_name_mei');
    const playerKanaSei = document.getElementById('player_kana_sei');
    const playerKanaMei = document.getElementById('player_kana_mei');

    // フリガナ自動変換は実装しない（手動入力のみ）
});
</script>

<!-- <style>タグ削除済み：スタイルは aidunite-style.css へ統合 -->

<?php get_footer(); ?>
