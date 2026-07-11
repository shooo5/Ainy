<?php
/**
 * チーム申請完了画面・申請内容確認・承認ページ共通の表示データ
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 性別表示ラベル（申請確認・承認画面共通）
 *
 * @param mixed $raw
 */
function aidunite_format_team_application_gender_label($raw) {
    $raw = is_string($raw) ? trim($raw) : '';
    if ($raw === '' || $raw === '未設定') {
        return '';
    }

    if (function_exists('aidunite_team_public_profile_gender_label')) {
        $label = aidunite_team_public_profile_gender_label($raw);
        if ($label !== '' && $label !== '不明') {
            return $label;
        }
    }

    if (function_exists('aidunite_get_gender_label')) {
        $label = aidunite_get_gender_label($raw);
        if ($label !== '' && $label !== '不明') {
            return $label;
        }
    }

    return $raw;
}

/**
 * 申請内容レビュー用コンテキスト（登録 wizard STEP3・承認ページ・アコーディオン共通）
 *
 * @param int $team_id
 * @return array<string, mixed>
 */
function aidunite_get_team_application_review_context($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return [];
    }

    if (!function_exists('aidunite_get_team_display_data')) {
        require_once get_stylesheet_directory() . '/functions/team/team-display-template.php';
    }

    $data = aidunite_get_team_display_data($team_id);
    if ($data === []) {
        return [];
    }

    $team_name = function_exists('aidunite_get_team_name')
        ? (string) aidunite_get_team_name($team_id)
        : (string) ($data['team_name'] ?? '');

    if ($team_name === '') {
        $team_name = (string) ($data['team_name'] ?? '');
    }

    $logo_crop = function_exists('aidunite_get_team_logo_crop')
        ? aidunite_get_team_logo_crop($team_id)
        : ['x' => 0, 'y' => 0, 'zoom' => 100];

    $submitted = get_post_time('Y年n月j日 H:i', false, $team_id);
    if ($submitted === false || $submitted === '') {
        $submitted = get_the_date('Y年n月j日 H:i', $team_id);
    }

    $gender_raw = (string) ($data['team_gender_option'] ?? '');

    return [
        'team_id'            => $team_id,
        'team_name'          => $team_name,
        'team_name_kana'     => (string) get_post_meta($team_id, 'team_name_kana', true),
        'sport_type'         => (string) ($data['sport_type'] ?? ''),
        'team_category'      => (string) ($data['team_category'] ?? ''),
        'team_type'          => function_exists('aidunite_team_type_label')
            ? aidunite_team_type_label((string) ($data['team_type'] ?? ''))
            : (string) ($data['team_type'] ?? ''),
        'team_gender_option' => $gender_raw,
        'team_gender_label'  => aidunite_format_team_application_gender_label($gender_raw),
        'region'             => (string) ($data['region'] ?? ''),
        'team_description'   => (string) ($data['team_description'] ?? ''),
        'team_logo'          => (string) ($data['team_logo'] ?? ''),
        'logo_crop'          => $logo_crop,
        'team_website'       => (string) ($data['team_website'] ?? ''),
        'team_sns_url'       => (string) ($data['team_sns_url'] ?? ''),
        'registrant_name'    => (string) ($data['registrant_name'] ?? ''),
        'contact_mail'       => (string) ($data['contact_mail'] ?? ''),
        'contact_phone'      => (string) ($data['contact_phone'] ?? ''),
        'submitted_at'       => (string) $submitted,
        'post_status'        => (string) ($data['post_status'] ?? ''),
    ];
}

/**
 * 申請内容フィールド定義（表示行・カード共通）
 *
 * @param array<string, bool> $options include_submitted_at, include_contact
 * @return array<int, array<string, string>>
 */
function aidunite_get_team_application_review_field_defs($options = []) {
    $include_submitted = !isset($options['include_submitted_at']) || !empty($options['include_submitted_at']);
    $include_contact = !empty($options['include_contact']);

    $defs = [
        ['key' => 'team_name', 'label' => 'チーム', 'format' => 'text', 'layout' => 'grid'],
        ['key' => 'team_name_kana', 'label' => 'フリガナ', 'format' => 'text', 'layout' => 'grid'],
        ['key' => 'sport_type', 'label' => 'スポーツ', 'format' => 'text', 'layout' => 'grid'],
        ['key' => 'team_category', 'label' => '年代', 'format' => 'text', 'layout' => 'grid'],
        ['key' => 'team_gender_option', 'alt_key' => 'team_gender_label', 'label' => '性別', 'format' => 'text', 'layout' => 'grid'],
        ['key' => 'team_type', 'label' => '所属', 'format' => 'text', 'layout' => 'grid'],
        ['key' => 'region', 'label' => '都道府県', 'format' => 'text', 'layout' => 'grid'],
        ['key' => 'team_website', 'label' => '公式サイト', 'format' => 'url', 'layout' => 'solo'],
        ['key' => 'team_sns_url', 'label' => 'SNS', 'format' => 'url', 'layout' => 'solo'],
        ['key' => 'team_description', 'label' => '紹介文', 'format' => 'multiline', 'layout' => 'intro'],
    ];

    if ($include_submitted) {
        $defs[] = ['key' => 'submitted_at', 'label' => '申請日時', 'format' => 'text', 'layout' => 'solo'];
    }

    if ($include_contact) {
        $defs[] = ['key' => 'registrant_name', 'label' => '代表者', 'format' => 'text', 'layout' => 'solo'];
        $defs[] = ['key' => 'contact_mail', 'label' => 'メール', 'format' => 'text', 'layout' => 'solo'];
        $defs[] = ['key' => 'contact_phone', 'label' => '電話', 'format' => 'text', 'layout' => 'solo'];
    }

    return $defs;
}

/**
 * 申請内容フィールドの表示値
 *
 * @param array<string, mixed> $context
 * @param array<string, string> $def
 */
function aidunite_resolve_application_review_field_value($context, $def) {
    $unset = '—';

    if (!empty($def['alt_key'])) {
        $alt = trim((string) ($context[$def['alt_key']] ?? ''));
        if ($alt !== '' && $alt !== '未設定') {
            return $alt;
        }
    }

    $key = (string) ($def['key'] ?? '');
    if ($key === '') {
        return $unset;
    }

    $raw = trim((string) ($context[$key] ?? ''));
    if ($raw === '' || $raw === '未設定') {
        return $unset;
    }

    return $raw;
}

/**
 * 申請内容の表示行（登録確認 STEP3 と同一ラベル順）
 *
 * @param array<string, mixed> $context
 * @param array<string, bool>  $options include_submitted_at, include_contact
 * @return array<int, array{label:string, value:string, format:string}>
 */
function aidunite_get_team_application_review_rows($context, $options = []) {
    $rows = [];

    foreach (aidunite_get_team_application_review_field_defs($options) as $def) {
        $rows[] = [
            'label'  => (string) $def['label'],
            'value'  => aidunite_resolve_application_review_field_value($context, $def),
            'format' => (string) $def['format'],
        ];
    }

    return $rows;
}

/**
 * 申請内容行の HTML 値
 *
 * @param array{label?:string, value?:string, format?:string} $row
 */
function aidunite_format_application_review_value_html($row) {
    $value = (string) ($row['value'] ?? '');
    $format = (string) ($row['format'] ?? 'text');

    if ($value === '' || $value === '—') {
        return '—';
    }

    if ($format === 'url') {
        $url = esc_url($value);
        if ($url === '') {
            return '—';
        }

        return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . esc_html($value) . '</a>';
    }

    if ($format === 'multiline') {
        return nl2br(esc_html($value));
    }

    return esc_html($value);
}

/**
 * 申請内容確認：info chip（アイコンなし）
 *
 * @param string $label
 * @param string $value
 * @param string $format text|url
 * @param bool   $solo
 */
function aidunite_render_application_review_info_chip($label, $value, $format = 'text', $solo = false) {
    $label = trim((string) $label);
    if ($label === '') {
        return '';
    }

    $display = trim((string) $value);
    if ($display === '') {
        $display = '—';
    }

    $value_html = aidunite_format_application_review_value_html([
        'value'  => $display,
        'format' => $format,
    ]);

    $classes = 'team-info-chip';
    if ($solo) {
        $classes .= ' team-info-chip--solo';
    }

    ob_start();
    ?>
    <article class="<?php echo esc_attr($classes); ?>">
      <span class="team-info-chip__label"><?php echo esc_html($label); ?></span>
      <span class="team-info-chip__value"><?php echo $value_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
    </article>
    <?php
    return (string) ob_get_clean();
}

/**
 * 申請内容確認：紹介文 chip（アイコンなし）
 *
 * @param string $label
 * @param string $text
 */
function aidunite_render_application_review_intro_chip($label, $text) {
    $label = trim((string) $label);
    $text = trim((string) $text);
    if ($label === '') {
        return '';
    }

    if ($text === '') {
        $text = '—';
    }

    ob_start();
    ?>
    <article class="team-info-chip team-info-chip--solo team-info-chip--intro">
      <span class="team-info-chip__label"><?php echo esc_html($label); ?></span>
      <div class="team-info-chip__prose"><?php echo nl2br(esc_html($text)); ?></div>
    </article>
    <?php
    return (string) ob_get_clean();
}

/**
 * 申請内容確認：カードグリッド（横2カラム + 1カラム項目）
 *
 * @param array<string, mixed> $context
 * @param array<string, bool>  $options include_submitted_at, include_contact
 */
function aidunite_render_application_review_card_layout($context, $options = []) {
    $chips = [];

    foreach (aidunite_get_team_application_review_field_defs($options) as $def) {
        $value = aidunite_resolve_application_review_field_value($context, $def);
        $layout = (string) ($def['layout'] ?? 'grid');

        if ($layout === 'intro') {
            $chips[] = aidunite_render_application_review_intro_chip((string) $def['label'], $value);
            continue;
        }

        $chips[] = aidunite_render_application_review_info_chip(
            (string) $def['label'],
            $value,
            (string) $def['format'],
            $layout === 'solo'
        );
    }

    $chips = array_values(array_filter($chips, static function ($chip) {
        return $chip !== '';
    }));

    if ($chips === []) {
        return '';
    }

    ob_start();
    ?>
    <div class="team-reg-complete-details__chips" data-aidunite-ui="application-review-chips-v1">
      <?php echo implode('', $chips); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * 申請内容確認 UI 用アセット
 */
function aidunite_enqueue_application_review_assets() {
    static $enqueued = false;
    if ($enqueued) {
        return;
    }
    $enqueued = true;

    $team_complete_css = get_stylesheet_directory() . '/assets/css/pages/team-registration-complete.css';
    if (is_readable($team_complete_css)) {
        wp_enqueue_style(
            'team-registration-complete-style',
            get_stylesheet_directory_uri() . '/assets/css/pages/team-registration-complete.css',
            ['aidunite-style'],
            (string) filemtime($team_complete_css)
        );
    }
}

/**
 * 完了画面用コンテキスト
 *
 * @return array<string, mixed>
 */
function aidunite_get_team_registration_complete_context() {
    $defaults = [
        'team_id'            => 0,
        'team_name'          => 'あなたのチーム',
        'team_name_kana'     => '',
        'sport_type'         => '',
        'team_category'      => '',
        'team_type'          => '',
        'team_gender_option' => '',
        'team_gender_label'  => '',
        'region'             => '',
        'team_description'   => '',
        'team_website'       => '',
        'team_sns_url'       => '',
        'team_logo'          => '',
        'logo_crop'          => ['x' => 0, 'y' => 0, 'zoom' => 100],
        'submitted_at'       => '',
        'user_email'         => '',
    ];

    $user_id = get_current_user_id();
    if ($user_id <= 0) {
        return $defaults;
    }

    $user = wp_get_current_user();
    $defaults['user_email'] = (string) $user->user_email;

    $paired_get = isset($_GET['paired_team_id']) ? (int) $_GET['paired_team_id'] : 0;
    $team_id    = isset($_GET['team_id']) ? (int) $_GET['team_id'] : 0;
    $pending    = (int) get_user_meta($user_id, 'pending_team_id', true);

    if ($team_id <= 0) {
        $team_id = $pending;
    }

    $team_ids = [];
    if ($team_id > 0) {
        $team_ids[] = $team_id;
    }
    if ($paired_get > 0 && !in_array($paired_get, $team_ids, true)) {
        $team_ids[] = $paired_get;
    }
    if (function_exists('aidunite_get_user_pending_application_team_ids')) {
        foreach (aidunite_get_user_pending_application_team_ids($user_id) as $pid) {
            $pid = (int) $pid;
            if ($pid > 0 && !in_array($pid, $team_ids, true)) {
                $team_ids[] = $pid;
            }
        }
    }

    if ($team_ids === []) {
        return $defaults;
    }

    $teams_ctx = [];
    foreach ($team_ids as $tid) {
        $leader_id = (int) get_post_meta($tid, 'team_leader_id', true);
        $post      = get_post($tid);
        $is_pending_author = $post
            && $post->post_type === 'team'
            && $post->post_status === 'pending'
            && (int) $post->post_author === $user_id;
        $can_view = $pending === $tid
            || $is_pending_author
            || $leader_id === $user_id
            || current_user_can('manage_options');
        if (!$can_view) {
            continue;
        }
        $ctx = aidunite_get_team_application_review_context($tid);
        if ($ctx !== []) {
            $teams_ctx[] = $ctx;
        }
    }

    if ($teams_ctx === []) {
        return $defaults;
    }

    $primary = $teams_ctx[0];

    return array_merge($defaults, $primary, [
        'user_email' => (string) $defaults['user_email'],
        'teams'      => $teams_ctx,
    ]);
}

/**
 * 申請日（Y/m/d）
 *
 * @param array<string, mixed> $team_ctx
 */
function aidunite_format_team_application_date_short($team_ctx) {
    $team_id = (int) ($team_ctx['team_id'] ?? 0);
    if ($team_id > 0) {
        $formatted = get_the_date('Y/m/d', $team_id);
        if (is_string($formatted) && $formatted !== '') {
            return $formatted;
        }
    }

    return wp_date('Y/m/d');
}

/**
 * @param array<string, mixed> $team_ctx
 */
function aidunite_team_application_pending_gender_slug($team_ctx) {
    $raw = (string) ($team_ctx['team_gender_option'] ?? '');
    if (function_exists('aidunite_normalize_team_gender_option')) {
        $raw = (string) aidunite_normalize_team_gender_option($raw);
    }

    if ($raw === 'male') {
        return 'male';
    }
    if ($raw === 'female') {
        return 'female';
    }

    return '';
}

/**
 * @param array<string, mixed> $team_ctx
 */
function aidunite_render_team_application_pending_avatar($team_ctx) {
    $gender = aidunite_team_application_pending_gender_slug($team_ctx);
    $logo = (string) ($team_ctx['team_logo'] ?? '');
    $mod = $gender !== '' ? ' team-reg-pending__avatar--' . $gender : '';

    ob_start();
    ?>
    <span class="team-reg-pending__avatar<?php echo esc_attr($mod); ?>" aria-hidden="true">
      <?php if ($logo !== '' && function_exists('aidunite_team_logo_is_displayable') && aidunite_team_logo_is_displayable($logo)) : ?>
        <img class="team-reg-pending__avatar-img" src="<?php echo esc_url($logo); ?>" alt="" width="40" height="40" loading="lazy" />
      <?php else : ?>
        <span class="team-reg-pending__avatar-icon"><?php echo aidunite_get_theme_icon_svg('group', ['width' => '22', 'height' => '22']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
      <?php endif; ?>
    </span>
    <?php
    return (string) ob_get_clean();
}

/**
 * 申請中ステータスバッジ
 */
function aidunite_render_team_application_pending_status_badge() {
    ob_start();
    ?>
    <span class="team-reg-pending__status">
      <span class="team-reg-pending__status-icon" aria-hidden="true"><?php echo aidunite_get_theme_icon_svg('hourglass_empty', ['width' => '16', 'height' => '16']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
      確認中
    </span>
    <?php
    return (string) ob_get_clean();
}

/**
 * 申請中チームの性別ラベル
 *
 * @param array<string, mixed> $team_ctx
 */
function aidunite_get_team_application_pending_gender_label($team_ctx) {
    $gender_label = (string) ($team_ctx['team_gender_label'] ?? '');
    if ($gender_label === '' && function_exists('aidunite_format_team_application_gender_label')) {
        $gender_label = aidunite_format_team_application_gender_label($team_ctx['team_gender_option'] ?? '');
    }

    return $gender_label;
}

/**
 * 申請内容確認パネル本体（ロゴ + チップグリッド）
 *
 * @param array<string, mixed> $context
 * @param array<string, bool>  $options
 */
function aidunite_render_team_application_review_panel($context, $options = []) {
    $context = is_array($context) ? $context : [];
    $team_name = (string) ($context['team_name'] ?? '');
    $logo_url  = (string) ($context['team_logo'] ?? '');
    $logo_crop = isset($context['logo_crop']) && is_array($context['logo_crop'])
        ? $context['logo_crop']
        : ['x' => 0, 'y' => 0, 'zoom' => 100];
    $logo_style = sprintf(
        'transform: translate(%d%%, %d%%) scale(%s);',
        (int) ($logo_crop['x'] ?? 0),
        (int) ($logo_crop['y'] ?? 0),
        max(0.5, (int) ($logo_crop['zoom'] ?? 100) / 100)
    );
    $has_logo = $logo_url !== '';

    ob_start();
    ?>
    <div class="team-reg-complete-details__body<?php echo $has_logo ? '' : ' team-reg-complete-details__body--no-logo'; ?>">
      <aside class="team-reg-complete-details__logo-side" aria-label="チームロゴ">
        <?php if ($has_logo) : ?>
          <div class="team-reg-complete-details__logo-frame">
            <img
              class="team-reg-complete-details__logo"
              src="<?php echo esc_url($logo_url); ?>"
              alt="<?php echo esc_attr($team_name); ?>のロゴ"
              width="168"
              height="168"
              decoding="async"
              style="<?php echo esc_attr($logo_style); ?>"
            >
          </div>
        <?php else : ?>
          <div class="team-reg-complete-details__logo-frame team-reg-complete-details__logo-frame--placeholder" aria-hidden="true"></div>
        <?php endif; ?>
      </aside>

      <div class="team-reg-complete-details__content">
        <?php
        if (function_exists('aidunite_render_application_review_card_layout')) {
            echo aidunite_render_application_review_card_layout($context, array_merge([
                'include_submitted_at' => true,
                'include_contact'      => false,
            ], $options)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        ?>
      </div>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * 申請中チームカード（2行レイアウト・押下で申請内容を展開）
 *
 * @param array<string, mixed> $team_ctx
 */
function aidunite_render_team_registration_pending_card($team_ctx) {
    $gender_slug  = aidunite_team_application_pending_gender_slug($team_ctx);
    $gender_label = aidunite_get_team_application_pending_gender_label($team_ctx);
    $gender_mod   = $gender_slug !== '' ? ' team-reg-pending__details--' . $gender_slug : '';
    $team_name    = (string) ($team_ctx['team_name'] ?? '');

    ob_start();
    ?>
    <li class="team-reg-pending__item">
      <details class="team-reg-pending__details<?php echo esc_attr($gender_mod); ?>">
        <summary class="team-reg-pending__card">
          <span class="team-reg-pending__card-logo">
            <?php echo aidunite_render_team_application_pending_avatar($team_ctx); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
          </span>
          <p class="team-reg-pending__card-name"><?php echo esc_html($team_name); ?></p>
          <div class="team-reg-pending__card-status"><?php echo aidunite_render_team_application_pending_status_badge(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
          <p class="team-reg-pending__card-meta">
            <?php echo esc_html($gender_label !== '' ? $gender_label : '—'); ?>
            <span class="team-reg-pending__card-meta-sep" aria-hidden="true">|</span>
            <?php echo esc_html(aidunite_format_team_application_date_short($team_ctx)); ?>
          </p>
          <span class="team-reg-pending__card-chev" aria-hidden="true"></span>
          <span class="screen-reader-text"><?php echo esc_html($team_name); ?>の申請内容を表示</span>
        </summary>
        <div class="team-reg-pending__card-panel">
          <?php echo aidunite_render_team_application_review_panel($team_ctx); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
      </details>
    </li>
    <?php
    return (string) ob_get_clean();
}

/**
 * 申請中チーム一覧
 *
 * @param array<int, array<string, mixed>> $teams
 */
function aidunite_render_team_registration_pending_section(array $teams) {
    $teams = array_values(array_filter($teams, static function ($row) {
        return is_array($row) && !empty($row['team_name']);
    }));

    if ($teams === []) {
        return '';
    }

    $count = count($teams);

    ob_start();
    ?>
    <section class="team-reg-pending" aria-labelledby="team-reg-pending-heading">
      <h2 id="team-reg-pending-heading" class="team-reg-pending__heading">申請中のチーム（<?php echo (int) $count; ?>件）</h2>

      <ul class="team-reg-pending__cards">
        <?php foreach ($teams as $team_ctx) : ?>
          <?php echo aidunite_render_team_registration_pending_card($team_ctx); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php endforeach; ?>
      </ul>
    </section>
    <?php
    return (string) ob_get_clean();
}

/**
 * 申請内容確認アコーディオン（完了画面・承認待ちマイページ共通）
 *
 * @param array<string, mixed> $context
 * @param bool                 $open
 */
function aidunite_render_registration_application_details($context = [], $open = false) {
    $part = get_stylesheet_directory() . '/template-parts/team/registration-application-details.php';
    if (!is_readable($part)) {
        return;
    }

    load_template($part, false, [
        'context' => is_array($context) ? $context : [],
        'open'    => (bool) $open,
    ]);
}
