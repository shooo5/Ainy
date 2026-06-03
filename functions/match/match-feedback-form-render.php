<?php
/**
 * マッチアンケートフォーム — 試合後振り返り体験 UI（本番・管理プレビュー共通）
 */

if (!defined('ABSPATH')) {
    exit;
}

/** @return array<int, string> */
function aidunite_match_feedback_star_hint_labels() {
    return [
        1 => '改善の余地あり',
        2 => 'いまいち',
        3 => 'ふつう',
        4 => '良かった',
        5 => 'とても良かった',
    ];
}

/**
 * @param array<string, mixed> $args
 */
function aidunite_match_feedback_form_normalize_args(array $args) {
    return wp_parse_args($args, [
        'mode'                    => 'live',
        'match_id'                => 0,
        'team_id'                 => 0,
        'opponent_team_id'        => 0,
        'opponent_team_name'      => '相手チーム',
        'current_user_id'         => 0,
        'schedule_date'           => '',
        'schedule_start'          => '',
        'schedule_end'            => '',
        'venue_display'           => '',
        'favorite_checked'        => false,
        'show_favorite'           => true,
        'show_post_match_modules' => false,
        'post_match_slot'         => 'after_match_feedback',
        'post_match_context'      => null,
        'only_sections'           => null,
    ]);
}

function aidunite_match_feedback_form_enqueue_assets() {
    $theme_uri = get_stylesheet_directory_uri();
    $theme_dir = get_stylesheet_directory();
    $css_form = $theme_dir . '/assets/css/components/match-feedback-form.css';
    $css_exp = $theme_dir . '/assets/css/pages/match-feedback-experience.css';
    $ver_form = file_exists($css_form) ? (string) filemtime($css_form) : '1';
    $ver_exp = file_exists($css_exp) ? (string) filemtime($css_exp) : '1';

    wp_enqueue_style('aidunite-match-feedback-form', $theme_uri . '/assets/css/components/match-feedback-form.css', [], $ver_form);
    wp_enqueue_style('aidunite-match-feedback-experience', $theme_uri . '/assets/css/pages/match-feedback-experience.css', ['aidunite-match-feedback-form'], $ver_exp);

    $js_path = $theme_dir . '/assets/js/match/match-feedback-form.js';
    if (file_exists($js_path)) {
        wp_enqueue_script(
            'aidunite-match-feedback-form',
            $theme_uri . '/assets/js/match/match-feedback-form.js',
            ['jquery'],
            (string) filemtime($js_path),
            true
        );
        wp_localize_script('aidunite-match-feedback-form', 'aiduniteMatchFeedbackL10n', [
            'starHints'        => aidunite_match_feedback_star_hint_labels(),
            'restUrl'          => esc_url_raw(rest_url('aidunite/v1/match-feedback')),
            'favoriteRestUrl'  => esc_url_raw(rest_url('aidunite/v1/favorite-teams')),
            'restNonce'        => wp_create_nonce('wp_rest'),
        ]);
    }
}

/**
 * @param string[]|null $only
 * @param string        $section
 */
function aidunite_match_feedback_should_render_section($only, $section) {
    if ($only === null || !is_array($only) || $only === []) {
        return true;
    }

    return in_array($section, $only, true);
}

/**
 * @param string               $name
 * @param array<string, mixed> $args
 */
function aidunite_render_match_feedback_star_rating($name, array $args = []) {
    $name = sanitize_key($name);
    $required = !empty($args['required']);
    $disabled = !empty($args['disabled']);
    $id_prefix = preg_replace('/[^a-z0-9_\-]/', '-', $name);

    ob_start();
    ?>
    <div class="star-rating mf-star-rating-wrap" data-star-rating="<?php echo esc_attr($name); ?>" role="radiogroup" aria-label="<?php echo esc_attr((string) ($args['aria_label'] ?? $name)); ?>">
        <?php for ($i = 5; $i >= 1; $i--) : ?>
            <input type="radio" class="star-rating__input" name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($id_prefix . '-' . $i); ?>" value="<?php echo (int) $i; ?>"<?php echo $required ? ' required' : ''; ?><?php echo $disabled ? ' disabled' : ''; ?>>
            <label for="<?php echo esc_attr($id_prefix . '-' . $i); ?>" class="star-rating__label" data-star-value="<?php echo (int) $i; ?>" title="<?php echo esc_attr($i . '点'); ?>">
                <span class="star-rating__glyph" aria-hidden="true"></span>
                <span class="screen-reader-text"><?php echo esc_html((string) $i); ?>点</span>
            </label>
        <?php endfor; ?>
    </div>
    <p class="mf-star-hint mf-center-text" data-star-hint-for="<?php echo esc_attr($name); ?>" aria-live="polite"></p>
    <?php
    return ob_get_clean();
}

/**
 * @param string $name
 * @param array<string, string> $items
 * @param bool   $disabled
 */
function aidunite_match_feedback_chip_items() {
    return [
        'time'       => ['icon_svg' => 'schedule', 'label' => '時間の使い方'],
        'venue'      => ['icon_svg' => 'stadium', 'label' => '会場・環境'],
        'opponent'   => ['icon_svg' => 'handshake', 'label' => '相手チームの対応'],
        'atmosphere' => ['icon_svg' => 'group', 'label' => '雰囲気・マナー'],
    ];
}

/**
 * @param string $name
 * @param bool   $disabled
 */
function aidunite_render_match_feedback_chips($name, array $items = [], $disabled = false) {
    if ($items === []) {
        $items = aidunite_match_feedback_chip_items();
    }
    $dis = $disabled ? ' disabled' : '';
    ob_start();
    echo '<div class="mf-choice-grid" data-chip-group="' . esc_attr($name) . '">';
    foreach ($items as $value => $item) {
        if (!is_array($item)) {
            $item = ['icon' => '', 'label' => (string) $item];
        }
        $label = (string) ($item['label'] ?? '');
        if ($label === '' && !empty($item['lines']) && is_array($item['lines'])) {
            $label = implode('', $item['lines']);
        }
        echo '<label class="mf-choice">';
        echo '<input type="checkbox" name="' . esc_attr($name) . '[]" value="' . esc_attr((string) $value) . '"' . $dis . '>';
        echo '<span class="mf-choice__body">';
        if (!empty($item['icon_svg']) && function_exists('aidunite_render_theme_icon')) {
            echo aidunite_render_theme_icon((string) $item['icon_svg'], ['width' => '22', 'height' => '22'], 'mf-choice__icon'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } elseif (!empty($item['icon'])) {
            echo '<span class="mf-choice__icon" aria-hidden="true">' . esc_html((string) $item['icon']) . '</span>';
        }
        echo '<span class="mf-choice__label">' . esc_html($label) . '</span>';
        echo '</span></label>';
    }
    echo '</div>';
    return ob_get_clean();
}

/**
 * @param bool $disabled
 * @param bool $required
 */
function aidunite_render_match_feedback_mood_picker($disabled = false, $required = true) {
    $moods = [
        5 => ['emoji' => '😊', 'label' => 'とても良い'],
        4 => ['emoji' => '😃', 'label' => '良い'],
        3 => ['emoji' => '😐', 'label' => 'ふつう'],
        2 => ['emoji' => '😟', 'label' => 'あまり良くない'],
        1 => ['emoji' => '😔', 'label' => '良くない'],
    ];
    $req = $required && !$disabled ? ' required' : '';
    $dis = $disabled ? ' disabled' : '';
    ob_start();
    echo '<div class="mf-face-grid" role="radiogroup" aria-label="相手チームの印象">';
    foreach ($moods as $val => $m) {
        echo '<label class="mf-face">';
        echo '<input type="radio" name="opponent_rating" value="' . (int) $val . '"' . $req . $dis . '>';
        echo '<span class="mf-face__body"><span class="mf-face__emoji" aria-hidden="true">' . esc_html($m['emoji']) . '</span>';
        echo '<span class="mf-face__label">' . esc_html($m['label']) . '</span></span>';
        echo '</label>';
    }
    echo '</div>';
    return ob_get_clean();
}

/**
 * @param int    $num
 * @param string $title
 * @param string $inner
 * @param bool   $optional
 */
function aidunite_match_feedback_circled_num($num) {
    $map = [1 => '①', 2 => '②', 3 => '③', 4 => '④', 5 => '⑤', 6 => '⑥', 7 => '⑦', 8 => '⑧', 9 => '⑨'];
    return $map[(int) $num] ?? (string) $num;
}

/**
 * @param int    $num
 * @param string $title
 * @param string $inner
 * @param bool   $optional
 * @param string $optional_note 例: 複数選択OK
 */
function aidunite_render_match_feedback_question_card($num, $title, $inner, $optional = false, $optional_note = '') {
    ob_start();
    ?>
    <section class="mf-q-card" data-mf-question="<?php echo (int) $num; ?>">
        <h2 class="mf-q-card__title">
            <span class="mf-q-card__circled" aria-hidden="true"><?php echo esc_html(aidunite_match_feedback_circled_num($num)); ?></span>
            <?php echo esc_html($title); ?>
            <?php if ($optional) : ?>
                <small class="mf-q-card__optional">任意</small>
            <?php elseif ($optional_note !== '') : ?>
                <small class="mf-q-card__optional"><?php echo esc_html($optional_note); ?></small>
            <?php endif; ?>
        </h2>
        <div class="mf-q-card__body"><?php echo $inner; ?></div>
    </section>
    <?php
    return ob_get_clean();
}

/**
 * @param array<string, mixed> $args
 */
function aidunite_render_match_feedback_match_summary(array $args) {
    $args = aidunite_match_feedback_form_normalize_args($args);
    $date_disp = '—';
    if (!empty($args['schedule_date'])) {
        $ts = strtotime((string) $args['schedule_date']);
        $week = ['日', '月', '火', '水', '木', '金', '土'];
        $date_disp = $ts ? wp_date('n月j日（' . $week[(int) wp_date('w', $ts)] . '）', $ts) : '—';
    }
    $time_disp = ($args['schedule_start'] && $args['schedule_end'])
        ? esc_html($args['schedule_start'] . '〜' . $args['schedule_end'])
        : '—';
    $info_cols = !empty($args['venue_display']) ? 4 : 3;
    ob_start();
    ?>
    <div class="mf-match-info" role="group" aria-label="試合情報">
        <h3 class="mf-match-info__heading">試合情報</h3>
        <div class="mf-info-grid mf-info-grid--cols-<?php echo (int) $info_cols; ?>">
            <div class="mf-info-card"><?php echo aidunite_render_theme_icon('calendar_month', ['width' => '20', 'height' => '20'], 'mf-info-card__icon'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><strong><?php echo esc_html($date_disp); ?></strong></div>
            <div class="mf-info-card"><?php echo aidunite_render_theme_icon('schedule', ['width' => '20', 'height' => '20'], 'mf-info-card__icon'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><strong><?php echo $time_disp; ?></strong></div>
            <div class="mf-info-card mf-info-card--opponent"><?php echo aidunite_render_theme_icon('vs', ['width' => '20', 'height' => '20'], 'mf-info-card__icon'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><strong><?php echo esc_html((string) $args['opponent_team_name']); ?></strong></div>
            <?php if (!empty($args['venue_display'])) : ?>
            <div class="mf-info-card"><?php echo aidunite_render_theme_icon('stadium', ['width' => '20', 'height' => '20'], 'mf-info-card__icon'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><strong><?php echo esc_html((string) $args['venue_display']); ?></strong></div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * @param array<string, mixed> $args
 */
function aidunite_render_match_feedback_survey_fields(array $args) {
    $args = aidunite_match_feedback_form_normalize_args($args);
    $is_preview = ($args['mode'] === 'preview');
    $disabled_attr = $is_preview ? ' disabled' : '';
    $only = isset($args['only_sections']) && is_array($args['only_sections']) ? $args['only_sections'] : null;

    $show_satisfaction = aidunite_match_feedback_should_render_section($only, 'satisfaction');
    $show_opponent = aidunite_match_feedback_should_render_section($only, 'opponent');
    // 会場の評価は MVP 対象外（試合情報の会場表示・「良かった点」チップの会場は残す）
    $show_venue = false;
    $show_rematch = aidunite_match_feedback_should_render_section($only, 'rematch');
    $show_comment = aidunite_match_feedback_should_render_section($only, 'comment');
    $show_favorite = aidunite_match_feedback_should_render_section($only, 'favorite')
        && !empty($args['show_favorite'])
        && (int) $args['opponent_team_id'] > 0
        && (int) $args['opponent_team_id'] !== (int) $args['team_id'];

    $q = 0;
    ob_start();
    ?>
    <div class="feedback-form-fields mf-questions<?php echo $is_preview ? ' feedback-form-fields--preview' : ''; ?>" data-testid="match-feedback-form-fields">
        <?php if (!$is_preview) : ?>
            <input type="hidden" name="match_id" value="<?php echo esc_attr((string) (int) $args['match_id']); ?>">
            <input type="hidden" name="team_id" value="<?php echo esc_attr((string) (int) $args['team_id']); ?>">
        <?php endif; ?>

        <?php
        if ($show_satisfaction) {
            $q++;
            $inner = aidunite_render_match_feedback_star_rating('satisfaction', [
                'required'   => !$is_preview,
                'disabled'   => $is_preview,
                'aria_label' => '試合の満足度',
            ]);
            echo aidunite_render_match_feedback_question_card($q, 'この試合、どうでしたか？', $inner, false);

            $q++;
            $inner = aidunite_render_match_feedback_chips('satisfaction_reasons', [], $is_preview);
            echo aidunite_render_match_feedback_question_card($q, '特に良かった点は？', $inner, false, '複数選択OK');
        }

        if ($show_opponent) {
            $q++;
            $inner = aidunite_render_match_feedback_mood_picker($is_preview, !$is_preview);
            echo aidunite_render_match_feedback_question_card($q, '相手チームはどうでしたか？', $inner, false);
        }

        if ($show_rematch) {
            $q++;
            ob_start();
            ?>
            <div class="mf-rematch-group" role="radiogroup" aria-label="また試合したいですか">
                <label class="mf-rematch-card mf-again">
                    <input type="radio" name="rematch_interest" value="yes" data-testid="rematch-interest-yes"<?php echo $is_preview ? ' disabled checked' : ' required'; ?>>
                    <span class="mf-rematch-card__body">
                        <?php echo aidunite_render_theme_icon('hand_gesture', ['width' => '24', 'height' => '24'], 'mf-rematch-card__icon'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <span class="mf-rematch-card__label">また試合したい</span>
                    </span>
                </label>
                <label class="mf-rematch-card mf-again">
                    <input type="radio" name="rematch_interest" value="no" data-testid="rematch-interest-no"<?php echo $disabled_attr; ?><?php echo $is_preview ? '' : ' required'; ?>>
                    <span class="mf-rematch-card__body">
                        <?php echo aidunite_render_theme_icon('weight', ['width' => '24', 'height' => '24'], 'mf-rematch-card__icon'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <span class="mf-rematch-card__label">今回のみ</span>
                    </span>
                </label>
            </div>
            <label class="mf-field-label" for="rematch_reason">理由 <small>任意</small></label>
            <textarea id="rematch_reason" name="rematch_reason" rows="3" class="form-control mf-textarea" maxlength="200" placeholder="理由があれば教えてください" data-char-count="mf-rematch-count"<?php echo $disabled_attr; ?>></textarea>
            <p class="mf-char-count" id="mf-rematch-count" aria-live="polite">0/200</p>
            <?php
            echo aidunite_render_match_feedback_question_card($q, 'また試合したいですか？', ob_get_clean(), false);
        }

        if ($show_comment) {
            $q++;
            ob_start();
            ?>
            <textarea id="mf_comment" name="comment" rows="4" class="form-control mf-textarea" maxlength="300" placeholder="自由にご記入ください" data-char-count="mf-comment-count"<?php echo $disabled_attr; ?>></textarea>
            <p class="mf-char-count" id="mf-comment-count" aria-live="polite">0/300</p>
            <?php
            echo aidunite_render_match_feedback_question_card($q, 'コメント', ob_get_clean(), true);
        }

        if ($show_favorite) {
            $q++;
            ob_start();
            ?>
            <p class="mf-favorite-lead">お気に入りに追加すると、マイページからいつでも確認できます。</p>
            <label class="mf-favorite-row">
                <span class="mf-favorite-row__label">♡ お気に入りに追加する</span>
                <input type="checkbox" class="mf-favorite-row__input" name="add_to_favorites" value="1"<?php echo !empty($args['favorite_checked']) ? ' checked' : ''; ?><?php echo $disabled_attr; ?>>
            </label>
            <?php
            echo aidunite_render_match_feedback_question_card($q, 'この相手チームをお気に入りに追加しますか？', ob_get_clean(), false);
        }

        if (!empty($args['show_post_match_modules']) && !empty($args['post_match_context']) && function_exists('aidunite_render_post_match_modules')) {
            aidunite_render_post_match_modules((string) $args['post_match_slot'], $args['post_match_context']);
        }
        ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * @param string               $section
 * @param array<string, mixed> $args
 */
function aidunite_render_match_feedback_preview_section($section, array $args = []) {
    $base = array_merge(aidunite_match_feedback_form_normalize_args($args), ['mode' => 'preview']);
    $map = [
        'evaluation' => ['satisfaction', 'opponent', 'comment'],
        'favorite'   => ['favorite'],
        'rematch'    => ['rematch'],
    ];
    if (!isset($map[$section])) {
        return '';
    }
    return '<div class="match-feedback-flow-preview__form-surface feedback-form mf-questions-preview">'
        . aidunite_render_match_feedback_survey_fields(array_merge($base, ['only_sections' => $map[$section]]))
        . '</div>';
}

add_action('wp_enqueue_scripts', static function () {
    if (!is_page_template('page-match-feedback.php')) {
        return;
    }
    aidunite_match_feedback_form_enqueue_assets();
    $pmm_css = get_stylesheet_directory() . '/assets/css/components/post-match-module.css';
    if (file_exists($pmm_css)) {
        wp_enqueue_style(
            'aidunite-post-match-modules',
            get_stylesheet_directory_uri() . '/assets/css/components/post-match-module.css',
            [],
            (string) filemtime($pmm_css)
        );
    }
}, 20);
