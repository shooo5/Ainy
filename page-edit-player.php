<?php
/**
 * Template Name: 選手編集ページ
 */

require_once get_template_directory() . '/functions/common/auth-middleware.php';
$auth_result = AidUniteAuthMiddleware::require_auth(true);
if (!$auth_result->is_valid()) {
    return;
}

$current_user_id = (int) $auth_result->user_id;
$user_info = aidunite_get_user_info($current_user_id);
$effective_role = $user_info['user_type'] ?? 'general';

$is_privileged_admin = function_exists('aidunite_user_is_privileged_admin')
    && aidunite_user_is_privileged_admin($current_user_id);

$player_id = intval($_GET['player_id'] ?? 0);
if (!$player_id) {
    wp_die('選手IDが指定されていません。');
}

// 選手の存在チェック（ユーザーメタベース）
$player_user = get_userdata($player_id);
if (!$player_user) {
    wp_die('選手が見つかりません。');
}

$player_canonical = aidunite_player_get_canonical_meta($player_id);
if ($player_canonical === []) {
    wp_die('選手が見つかりません。');
}

// チームID取得・所属確認
$player_team_id = (int) ($player_canonical['team_id'] ?? 0);
$edit_actor = function_exists('aidunite_team_members_resolve_actor')
    ? (string) aidunite_team_members_resolve_actor($current_user_id, $player_team_id)
    : '';

if ($is_privileged_admin) {
    $team_id = $player_team_id > 0
        ? $player_team_id
        : (int) aidunite_user_resolve_admin_team_id($current_user_id, $_GET);
} elseif ($edit_actor === 'parent') {
    $team_id = $player_team_id;
    if (!function_exists('aidunite_team_members_parent_can_manage_player')
        || !aidunite_team_members_parent_can_manage_player($current_user_id, $player_id, $team_id)) {
        wp_die('このお子様の情報を編集する権限がありません。');
    }
} elseif ($edit_actor === 'team_leader') {
    $team_id = (int) ($user_info['team_id'] ?? 0);
    if ($team_id <= 0) {
        $team_id = $player_team_id;
    }
    if ($player_team_id !== $team_id) {
        wp_die('この選手を編集する権限がありません。');
    }
} else {
    wp_die('この選手を編集する権限がありません。');
}

$edited_by_parent = ($edit_actor === 'parent');
$back_url = home_url('/team-members');

if ($team_id <= 0) {
    wp_die('閲覧可能なチームが見つかりません。');
}

// 選手のユーザータイプ確認
$player_user_type = (string) ($player_canonical['aidunite_role'] ?? '');
if ($player_user_type !== 'player') {
    wp_die('指定されたユーザーは選手ではありません。');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_player'])) {
    $edit_result = aidunite_player_submit_edit($player_id, $current_user_id, $team_id, $_FILES);
    if (!empty($edit_result['redirect'])) {
        wp_safe_redirect($edit_result['redirect']);
        exit;
    }
    if (!empty($edit_result['errors'])) {
        wp_die(
            esc_html(implode(' ', (array) $edit_result['errors'])),
            'エラー',
            ['response' => (int) ($edit_result['http_status'] ?? 400)]
        );
    }
}

$player_display = aidunite_player_get_edit_display($player_id);
$player_name_sei = (string) ($player_display['player_name_sei'] ?? '');
$player_name_mei = (string) ($player_display['player_name_mei'] ?? '');
$player_name = (string) ($player_display['player_name'] ?? '');
$player_kana_sei = (string) ($player_display['player_kana_sei'] ?? '');
$player_kana_mei = (string) ($player_display['player_kana_mei'] ?? '');
$player_name_kana = (string) ($player_display['player_name_kana'] ?? '');
$player_nickname = (string) ($player_display['player_nickname'] ?? '');
$player_birth_date = (string) ($player_display['player_birth_date'] ?? '');
$player_grade = (string) ($player_display['player_grade'] ?? '');
$player_position = (string) ($player_display['player_position'] ?? '');
$player_club_team = (string) ($player_display['player_club_team'] ?? '');
$player_email = (string) ($player_display['player_email'] ?? '');
$player_height = (string) ($player_display['player_height'] ?? '');
$player_weight = (string) ($player_display['player_weight'] ?? '');
$player_photo = (string) ($player_display['player_photo'] ?? '');

// 保護者情報（未成年チームの場合）
$team_display = aidunite_team_get_display_bundle((int) $team_id);
$team_category = (string) ($team_display['team_category'] ?? '');
$minor_categories = ['小学生', '中学生', '高校生'];
$is_minor_team = in_array($team_category, $minor_categories, true);

$parent_name_sei = '';
$parent_name_mei = '';
$parent_email = '';
$parent_emergency_contact = '';

if ($is_minor_team) {
    $parent_name_sei = (string) ($player_display['parent_name_sei'] ?? '');
    $parent_name_mei = (string) ($player_display['parent_name_mei'] ?? '');
    $parent_email = (string) ($player_display['parent_email'] ?? '');
    $parent_emergency_contact = (string) ($player_display['parent_emergency_contact'] ?? '');
}

$theme_key = function_exists('aidunite_get_team_ui_theme_key')
    ? (string) aidunite_get_team_ui_theme_key((int) $team_id)
    : 'boys';
if (!in_array($theme_key, ['boys', 'girls'], true)) {
    $theme_key = 'boys';
}

get_header();

$edit_page_title = $edited_by_parent ? 'お子様の情報を編集' : '選手情報を編集';
$edit_page_subtitle = $edited_by_parent ? 'お子様の登録情報を更新します' : '選手の情報を更新します';

if (function_exists('aidunite_web_app_page_shell_open')) {
    aidunite_web_app_page_shell_open([
        'page_class' => 'page-edit-player' . ($edited_by_parent ? ' page-edit-player--parent' : ''),
        'title' => $edit_page_title,
        'subtitle' => $edit_page_subtitle,
        'back_url' => $back_url,
    ]);
} else {
    echo '<div class="team-dashboard-container page-edit-player"><div class="dashboard-header"><h1>' . esc_html($edit_page_title) . '</h1><p>' . esc_html($edit_page_subtitle) . '</p></div>';
}
?>

      <form method="post" id="player-edit-form" class="player-edit-form" enctype="multipart/form-data">
        <?php wp_nonce_field('update_player_info', 'player_edit_nonce'); ?>

        <div class="player-form-flow" data-team-theme="<?php echo esc_attr($theme_key); ?>">

        <section class="player-form-block player-form-block--public" aria-labelledby="player-edit-block-public-title">
          <div class="player-form-block__head">
            <h3 class="player-form-block__title" id="player-edit-block-public-title">選手プロフィール公開情報</h3>
            <span class="player-form-block__badge">公開</span>
          </div>
          <p class="player-form-block__lead">他のユーザーにも表示される情報です。</p>

          <?php
          get_template_part('template-parts/player/player', 'photo-field', [
              'field_id' => 'player_photo_edit',
              'initial_url' => $player_photo,
              'label' => '選手写真',
          ]);
          ?>

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
              <label for="player_kana_sei">フリガナ（性）</label>
              <input type="text" id="player_kana_sei" name="player_kana_sei" class="form-control" value="<?php echo esc_attr($player_kana_sei); ?>" placeholder="フリガナ（性）を入力">
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
        </section>

        <?php if ($is_minor_team): ?>
        <section class="player-form-block player-form-block--private" aria-labelledby="player-edit-block-private-title">
          <div class="player-form-block__head">
            <h3 class="player-form-block__title" id="player-edit-block-private-title">管理者・保護者専用情報</h3>
            <span class="player-form-block__badge">限定</span>
          </div>
          <p class="player-form-block__lead">管理者と保護者のみが閲覧できる情報です。</p>

          <h4 class="player-form-block__subheading">保護者</h4>
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
        </section>
        <?php endif; ?>

        </div>

        <div style="text-align: center; margin-top: 2rem;">
          <button type="submit" name="update_player" class="btn btn-primary" style="padding: 1em 2em; font-size: 1.1em;">
            💾 保存する
          </button>
          <a href="<?php echo home_url('/team-members'); ?>" class="btn" style="background: var(--text-muted); color: white; padding: 1em 2em; margin-left: 1rem;">
            ← メンバー一覧に戻る
          </a>
        </div>
      </form>

<?php
if (function_exists('aidunite_web_app_page_shell_close')) {
    aidunite_web_app_page_shell_close();
} else {
    echo '</div>';
}
?>

<!-- <style>タグ削除済み：スタイルは aidunite-style.css へ統合 -->

<?php get_footer(); ?>
