<?php
/**
 * Template Name: 新規選手追加ページ
 */

$auth_result = AidUniteAuthMiddleware::require_auth(true);
if (!$auth_result->is_valid()) {
    return;
}

$current_user_id = get_current_user_id();
$submit_result = [];

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['player_nonce'])
) {
    $submit_result = aidunite_player_submit_registration(wp_unslash($_POST), $current_user_id, $_FILES);
    if (!empty($submit_result['redirect'])) {
        wp_safe_redirect($submit_result['redirect']);
        exit;
    }
}

$ctx = aidunite_player_get_registration_page_context($current_user_id, [
    'query' => $_GET,
    'post_defaults' => $_POST,
    'submit_result' => $submit_result,
]);

if (!empty($ctx['redirect'])) {
    wp_safe_redirect($ctx['redirect']);
    exit;
}

$d = is_array($ctx['form_defaults'] ?? null) ? $ctx['form_defaults'] : [];
$team = is_array($ctx['team'] ?? null) ? $ctx['team'] : [];
$is_minor_team = !empty($ctx['is_minor_team']);
$registered_by_parent = !empty($ctx['registered_by_parent']);
$page_title = $registered_by_parent ? 'お子様を登録' : '新しい選手を登録';
$page_subtitle = $registered_by_parent
    ? 'チームに参加するお子様の情報を登録します'
    : 'チームに新しい選手を追加します';
$submit_label = $registered_by_parent ? 'お子様を登録' : '選手を登録';
$theme_key = (string) ($ctx['theme_key'] ?? $team['theme_key'] ?? 'boys');
if (!in_array($theme_key, ['boys', 'girls'], true)) {
    $theme_key = 'boys';
}

get_header();

if (function_exists('aidunite_web_app_page_shell_open')) {
    aidunite_web_app_page_shell_open([
        'page_class' => 'page-player-add',
        'title' => $page_title,
        'subtitle' => $page_subtitle,
        'back_url' => (string) ($ctx['back_url'] ?? home_url('/team-members')),
    ]);
} else {
    echo '<div class="team-dashboard-container page-player-add"><div class="dashboard-header"><h1>' . esc_html($page_title) . '</h1></div>';
}
?>

<?php if (!empty($ctx['flash']['message'])) : ?>
  <div class="alert alert-<?php echo ($ctx['flash']['status'] ?? '') === 'success' ? 'success' : 'danger'; ?>" role="<?php echo ($ctx['flash']['status'] ?? '') === 'success' ? 'status' : 'alert'; ?>">
    <?php echo esc_html($ctx['flash']['message']); ?>
  </div>
<?php endif; ?>

<?php if (!empty($ctx['errors']) && is_array($ctx['errors'])) : ?>
  <div class="alert alert-danger" role="alert">
    <ul>
      <?php foreach ($ctx['errors'] as $error) : ?>
        <li><?php echo esc_html($error); ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<?php if (!empty($ctx['ok'])) : ?>
  <?php if (!empty($ctx['is_privileged_admin']) && empty($ctx['team_id'])) : ?>
  <div class="alert alert-warning" role="status">
    管理者閲覧モードです。チームが未指定のため登録はできません。<code>?team_id=</code> を付けてアクセスするか、チームを作成してください。
  </div>
  <?php endif; ?>

  <div class="form-section">
    <h3>所属チーム: <?php echo esc_html((string) ($team['team_name'] ?? '（未指定）')); ?></h3>
    <p>
      <span><?php echo esc_html((string) ($team['team_category'] ?? '')); ?>チーム</span>
      <?php if ($is_minor_team) : ?>
        <span>※ 未成年者向けの登録フォームです（保護者情報が必要）</span>
      <?php else : ?>
        <span>※ 成人向けの登録フォームです</span>
      <?php endif; ?>
    </p>
  </div>

  <form method="post" id="player-registration-form" class="player-registration-form" enctype="multipart/form-data"<?php echo (!empty($ctx['is_privileged_admin']) && empty($ctx['team_id'])) ? ' style="opacity:0.6;pointer-events:none;"' : ''; ?>>
    <?php wp_nonce_field('player_registration', 'player_nonce'); ?>
    <input type="hidden" name="team_id" value="<?php echo esc_attr((string) ($ctx['team_id'] ?? '')); ?>">

    <div class="player-form-flow" data-team-theme="<?php echo esc_attr($theme_key); ?>">

    <section class="player-form-block player-form-block--public" aria-labelledby="player-block-public-title">
      <div class="player-form-block__head">
        <h3 class="player-form-block__title" id="player-block-public-title">選手プロフィール公開情報</h3>
        <span class="player-form-block__badge">公開</span>
      </div>
      <p class="player-form-block__lead">他のユーザーにも表示される情報です。</p>

      <?php
      get_template_part('template-parts/player/player', 'photo-field', [
          'field_id' => 'player_photo_add',
          'initial_url' => '',
          'label' => '選手写真',
      ]);
      ?>

      <div class="form-row-2">
        <div class="form-group">
          <label for="player_nickname" class="player-form-label">ニックネーム <span class="player-form-required">必須</span></label>
          <input type="text" id="player_nickname" name="player_nickname" class="form-control" required
                 placeholder="例）たろう" value="<?php echo esc_attr((string) ($d['player_nickname'] ?? '')); ?>">
        </div>
        <div class="form-group">
          <label for="player_position">ポジション</label>
          <select id="player_position" name="player_position" class="form-control">
            <option value="">ポジションを選択</option>
            <?php
            $positions = ['PG' => 'PG（ポイントガード）', 'SG' => 'SG（シューティングガード）', 'SF' => 'SF（スモールフォワード）', 'PF' => 'PF（パワーフォワード）', 'C' => 'C（センター）'];
            foreach ($positions as $val => $label) :
                ?>
            <option value="<?php echo esc_attr($val); ?>" <?php selected((string) ($d['player_position'] ?? ''), $val); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-row-2">
        <div class="form-group">
          <label for="player_height">身長 (cm)</label>
          <input type="number" id="player_height" name="player_height" class="form-control" min="100" max="250" value="<?php echo esc_attr((string) ($d['player_height'] ?? '')); ?>">
        </div>
        <div class="form-group">
          <label for="player_club_team">所属クラブチーム名（任意）</label>
          <input type="text" id="player_club_team" name="player_club_team" class="form-control" value="<?php echo esc_attr((string) ($d['player_club_team'] ?? '')); ?>">
        </div>
      </div>
    </section>

    <section class="player-form-block player-form-block--private" aria-labelledby="player-block-private-title">
      <div class="player-form-block__head">
        <h3 class="player-form-block__title" id="player-block-private-title">管理者・保護者専用情報</h3>
        <span class="player-form-block__badge">限定</span>
      </div>
      <p class="player-form-block__lead">管理者と保護者のみが閲覧できる情報です。</p>

      <h4 class="player-form-block__subheading">選手</h4>

      <div class="player-form-fields">
        <div class="player-form-row-2">
          <div class="player-form-field">
            <label for="player_name_sei" class="player-form-label">選手名（性） <span class="player-form-required">必須</span></label>
            <div class="player-form-input-wrap player-form-input-wrap--plain">
              <input type="text" id="player_name_sei" name="player_name_sei" class="player-form-input" required autocomplete="family-name"
                     placeholder="例）山田" value="<?php echo esc_attr((string) ($d['player_name_sei'] ?? '')); ?>">
            </div>
          </div>
          <div class="player-form-field">
            <label for="player_name_mei" class="player-form-label">選手名（名） <span class="player-form-required">必須</span></label>
            <div class="player-form-input-wrap player-form-input-wrap--plain">
              <input type="text" id="player_name_mei" name="player_name_mei" class="player-form-input" required autocomplete="given-name"
                     placeholder="例）太郎" value="<?php echo esc_attr((string) ($d['player_name_mei'] ?? '')); ?>">
            </div>
          </div>
        </div>

        <div class="player-form-row-2">
          <div class="player-form-field">
            <label for="player_kana_sei" class="player-form-label">選手フリガナ（性） <span class="player-form-optional">任意</span></label>
            <div class="player-form-input-wrap player-form-input-wrap--plain">
              <input type="text" id="player_kana_sei" name="player_kana_sei" class="player-form-input"
                     placeholder="例）ヤマダ" value="<?php echo esc_attr((string) ($d['player_kana_sei'] ?? '')); ?>">
            </div>
          </div>
          <div class="player-form-field">
            <label for="player_kana_mei" class="player-form-label">選手フリガナ（名） <span class="player-form-optional">任意</span></label>
            <div class="player-form-input-wrap player-form-input-wrap--plain">
              <input type="text" id="player_kana_mei" name="player_kana_mei" class="player-form-input"
                     placeholder="例）タロウ" value="<?php echo esc_attr((string) ($d['player_kana_mei'] ?? '')); ?>">
            </div>
          </div>
        </div>

        <div class="player-form-row-2">
          <div class="player-form-field">
            <label for="player_birth_date" class="player-form-label">選手の生年月日 <span class="player-form-required">必須</span></label>
            <div class="player-form-input-wrap">
              <span class="player-form-input-icon"><?php echo aidunite_get_theme_icon_svg('today', ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
              <input type="date" id="player_birth_date" name="player_birth_date" class="player-form-input" required
                     value="<?php echo esc_attr((string) ($d['player_birth_date'] ?? '')); ?>">
            </div>
          </div>
          <div class="player-form-field">
            <label for="player_grade_display" class="player-form-label">選手の学年（自動計算）</label>
            <div class="player-form-input-wrap player-form-input-wrap--plain">
              <input type="text" id="player_grade_display" class="player-form-input" readonly
                     placeholder="生年月日を入力すると表示されます" value="<?php echo esc_attr((string) ($d['player_grade'] ?? '')); ?>">
              <input type="hidden" id="player_grade" name="player_grade" value="<?php echo esc_attr((string) ($d['player_grade'] ?? '')); ?>">
            </div>
            <p class="player-form-hint">生年月日から自動計算されます</p>
          </div>
        </div>

        <?php if (!$is_minor_team) : ?>
        <div class="player-form-field">
          <label for="player_email" class="player-form-label">選手メールアドレス <span class="player-form-optional">任意</span></label>
          <div class="player-form-input-wrap">
            <span class="player-form-input-icon"><?php echo aidunite_get_theme_icon_svg('mail', ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
            <input type="email" id="player_email" name="player_email" class="player-form-input" autocomplete="email"
                   placeholder="example@email.com" value="<?php echo esc_attr((string) ($d['player_email'] ?? '')); ?>">
          </div>
        </div>
        <?php endif; ?>
      </div>

      <?php if ($is_minor_team && !$registered_by_parent) : ?>
      <hr class="player-form-block__divider" aria-hidden="true">

      <h4 class="player-form-block__subheading">保護者</h4>
      <div class="player-form-fields">
        <div class="player-form-row-2">
          <div class="player-form-field">
            <label for="parent_name_sei" class="player-form-label">保護者名（性） <span class="player-form-optional">任意</span></label>
            <div class="player-form-input-wrap player-form-input-wrap--plain">
              <input type="text" id="parent_name_sei" name="parent_name_sei" class="player-form-input" autocomplete="family-name"
                     placeholder="例）山田" value="<?php echo esc_attr((string) ($d['parent_name_sei'] ?? '')); ?>">
            </div>
          </div>
          <div class="player-form-field">
            <label for="parent_name_mei" class="player-form-label">保護者名（名） <span class="player-form-optional">任意</span></label>
            <div class="player-form-input-wrap player-form-input-wrap--plain">
              <input type="text" id="parent_name_mei" name="parent_name_mei" class="player-form-input" autocomplete="given-name"
                     placeholder="例）花子" value="<?php echo esc_attr((string) ($d['parent_name_mei'] ?? '')); ?>">
            </div>
          </div>
        </div>

        <div class="player-form-row-2">
          <div class="player-form-field">
            <label for="parent_email" class="player-form-label">保護者メールアドレス <span class="player-form-required">必須</span></label>
            <div class="player-form-input-wrap">
              <span class="player-form-input-icon"><?php echo aidunite_get_theme_icon_svg('mail', ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
              <input type="email" id="parent_email" name="parent_email" class="player-form-input" required autocomplete="email"
                     placeholder="example@email.com" value="<?php echo esc_attr((string) ($d['parent_email'] ?? '')); ?>">
            </div>
            <p class="player-form-hint">登録完了の通知と保護者招待に使用されます</p>
          </div>
          <div class="player-form-field">
            <label for="parent_emergency_contact" class="player-form-label">緊急連絡先電話番号 <span class="player-form-optional">任意</span></label>
            <div class="player-form-input-wrap">
              <span class="player-form-input-icon"><?php echo aidunite_get_theme_icon_svg('sms', ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
              <input type="tel" id="parent_emergency_contact" name="parent_emergency_contact" class="player-form-input" autocomplete="tel"
                     placeholder="例）090-1234-5678" value="<?php echo esc_attr((string) ($d['parent_emergency_contact'] ?? '')); ?>">
            </div>
          </div>
        </div>
      </div>
      <?php else : ?>
      <hr class="player-form-block__divider" aria-hidden="true">

      <h4 class="player-form-block__subheading">緊急連絡先</h4>
      <div class="form-row-2">
        <div class="form-group">
          <label for="emergency_contact_name">緊急連絡先氏名</label>
          <input type="text" id="emergency_contact_name" name="emergency_contact_name" class="form-control" value="<?php echo esc_attr((string) ($d['emergency_contact_name'] ?? '')); ?>">
        </div>
        <div class="form-group">
          <label for="emergency_contact_relationship">続柄</label>
          <select id="emergency_contact_relationship" name="emergency_contact_relationship" class="form-control">
            <option value="">続柄を選択</option>
            <?php foreach (['家族', '友人', '同僚', 'その他'] as $rel) : ?>
            <option value="<?php echo esc_attr($rel); ?>" <?php selected((string) ($d['emergency_contact_relationship'] ?? ''), $rel); ?>><?php echo esc_html($rel); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label for="emergency_contact_phone">緊急連絡先電話番号</label>
        <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone" class="form-control" value="<?php echo esc_attr((string) ($d['emergency_contact_phone'] ?? '')); ?>">
      </div>
      <?php endif; ?>
    </section>

    </div>

    <button type="submit" class="btn btn-primary" id="player-submit-btn"><?php echo esc_html($submit_label); ?></button>
  </form>
<?php else : ?>
  <div class="alert alert-danger" role="alert">
    チーム情報が見つかりません。先にチームを登録してください。
  </div>
<?php endif; ?>

<?php
if (function_exists('aidunite_web_app_page_shell_close')) {
    aidunite_web_app_page_shell_close();
} else {
    echo '</div>';
}
?>

<?php get_footer(); ?>
