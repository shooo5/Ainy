<?php
/**
 * Template Name: スケジュール編集
 *
 * スケジュール登録・編集
 * ダッシュボードテンプレート構造を採用したUI/UX改善版
 */

// 統一認証・チーム所属チェック（代表・保護者・選手はチーム所属であればアクセス可＝プライベート予定登録のため）
$auth_result = AidUniteAuthMiddleware::require_auth(true);
if (!$auth_result->is_valid()) {
    return;
}

$user_id = $auth_result->user_id;
$team_id = function_exists('aidunite_resolve_user_team_id_for_schedule_ops')
    ? (int) aidunite_resolve_user_team_id_for_schedule_ops((int) $user_id)
    : aidunite_user_read_primary_team_id((int) $user_id);
if (!$team_id) {
    wp_safe_redirect(home_url('/schedule-management'));
    exit;
}

$team_edit_bundle = function_exists('aidunite_team_get_display_bundle')
    ? aidunite_team_get_display_bundle((int) $team_id)
    : [];

$team_gender_canonical = (string) ($team_edit_bundle['team_gender_option'] ?? '');

/** 新規・対戦マッチ希望時の性別カード初期値（team_gender_option → male/female/both）。編集時は既存メタを優先 */
$default_gender_condition_from_team = '';
if ($team_id && function_exists('aidunite_team_gender_option_to_schedule_gender_condition')) {
    $default_gender_condition_from_team = aidunite_team_gender_option_to_schedule_gender_condition(
        (string) ($team_edit_bundle['team_gender_option_raw'] ?? $team_gender_canonical)
    );
}

$user_role = function_exists('aidunite_get_user_role') ? aidunite_get_user_role($user_id) : '';
$is_team_leader = ($user_role === 'team_leader');
$is_admin = current_user_can('manage_options');

$schedule_edit_trial_simplified = function_exists('aidunite_schedule_edit_is_trial_simplified')
    && aidunite_schedule_edit_is_trial_simplified($user_id);

// 編集モードの処理
$edit_post_id = 0;
if (isset($_GET['post_id']) && !empty($_GET['post_id'])) {
    $edit_post_id = intval($_GET['post_id']);
}

/** 編集時の schedule メタ（表示・権限判定。書込は persist 正本） */
$edit_schedule_meta = [];
if ($edit_post_id > 0 && function_exists('aidunite_schedule_get_canonical_meta')) {
    $edit_schedule_meta = aidunite_schedule_get_canonical_meta($edit_post_id);
}

// 編集時：プライベート予定は本人のみ、チーム予定は代表のみ編集可
if ($edit_post_id > 0) {
    $edit_post = get_post($edit_post_id);
    if (!$edit_post || $edit_post->post_type !== 'schedule') {
        wp_safe_redirect(home_url('/schedule-management'));
        exit;
    }
    $edit_is_personal = !empty($edit_schedule_meta)
        ? (($edit_schedule_meta['is_personal'] ?? '0') === '1')
        : false;
    if ($edit_is_personal) {
        if ((int) $edit_post->post_author !== (int) $user_id) {
            wp_safe_redirect(home_url('/schedule-management'));
            exit;
        }
    } else {
        if (!$is_team_leader && !$is_admin) {
            wp_safe_redirect(home_url('/schedule-management'));
            exit;
        }
    }
}

// 日付パラメータの処理
$preset_date = '';
if (isset($_GET['date']) && !empty($_GET['date'])) {
    $preset_date = sanitize_text_field($_GET['date']);
    // 日付の妥当性チェック
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $preset_date)) {
        $preset_date = '';
    }
}

// 編集モードの場合は既存スケジュールから日付を取得
if ($edit_post_id > 0 && empty($preset_date)) {
    $existing_date = !empty($edit_schedule_meta['date'])
        ? (string) $edit_schedule_meta['date']
        : (function_exists('aidunite_schedule_read_normalized_date')
            ? (string) aidunite_schedule_read_normalized_date($edit_post_id)
            : '');
    if ($existing_date !== '') {
        $preset_date = $existing_date;
    }
}

// デフォルト値を設定（現在の日付）
if (empty($preset_date)) {
    $preset_date = date('Y-m-d');
}

// POST処理（解析・バリデーション: schedule-persist-submit.php → persist 正本）
if ($_POST && isset($_POST['schedule_nonce']) && wp_verify_nonce($_POST['schedule_nonce'], 'aidunite_schedule_nonce')) {
    $team_id = function_exists('aidunite_resolve_user_team_id_for_schedule_ops')
        ? (int) aidunite_resolve_user_team_id_for_schedule_ops((int) $user_id)
        : aidunite_user_read_primary_team_id((int) $user_id);

    $parsed = function_exists('aidunite_schedule_parse_wizard_post')
        ? aidunite_schedule_parse_wizard_post($_POST, [
            'user_id' => $user_id,
            'team_id' => $team_id,
            'trial_simplified' => $schedule_edit_trial_simplified,
            'is_team_leader' => $is_team_leader,
        ])
        : [];

    $errors = function_exists('aidunite_schedule_validate_wizard_submission')
        ? aidunite_schedule_validate_wizard_submission($parsed, $team_id, [
            'edit_post_id' => (int) ($parsed['post_id'] ?? 0),
        ])
        : ['フォーム処理が利用できません'];

    if (empty($errors)) {
        try {
            $intent = (string) ($parsed['intent'] ?? '');
            $post_id_from_form = (int) ($parsed['post_id'] ?? 0);
            $success_count = 0;
            $dates_to_process = [];

            if ($post_id_from_form > 0) {
                $edit_post = get_post($post_id_from_form);
                $edit_meta_row = function_exists('aidunite_schedule_get_canonical_meta')
                    ? aidunite_schedule_get_canonical_meta($post_id_from_form)
                    : [];
                $is_personal_edit = $edit_post
                    ? (($edit_meta_row['is_personal'] ?? '0') === '1')
                    : false;
                $can_edit = $edit_post && $edit_post->post_type === 'schedule'
                    && ((int) $edit_post->post_author === (int) $user_id
                        || (!$is_personal_edit && ($is_team_leader || $is_admin)));

                if ($can_edit) {
                    $date = (string) ($parsed['start_date'] ?? '');
                    $edit_blocked_by_match = false;

                    if (function_exists('aidunite_can_update_schedule')) {
                        $params_for_gate = [
                            'date' => $date,
                            'start_time' => (string) ($parsed['start_time'] ?? ''),
                            'end_time' => (string) ($parsed['end_time'] ?? ''),
                            'type' => (string) ($parsed['schedule_type'] ?? ''),
                            'place' => (string) ($parsed['venue_condition'] ?? ''),
                            'schedule_place' => (string) ($parsed['venue_condition'] ?? ''),
                            'schedule_gender' => (string) ($parsed['gender_condition'] ?? ''),
                            'intent' => $intent,
                            'matching' => ($intent === 'recruit') ? 1 : 0,
                        ];
                        $edit_gate = aidunite_can_update_schedule($post_id_from_form, $params_for_gate);
                        if (is_wp_error($edit_gate)) {
                            $errors[] = $edit_gate->get_error_message();
                            $edit_blocked_by_match = true;
                        }
                    }

                    if (!$edit_blocked_by_match) {
                        $persist_data = aidunite_schedule_persist_payload_from_wizard($parsed, $date, $user_id, $team_id);
                        $update_result = aidunite_schedule_update_published_post($post_id_from_form, $persist_data);
                        if (is_wp_error($update_result)) {
                            $errors[] = $update_result->get_error_message();
                        } else {
                            $success_count = 1;
                        }
                    }
                } else {
                    $errors[] = '編集対象のスケジュールが見つからないか、編集権限がありません。';
                }
            } else {
                $dates_to_process = (array) ($parsed['dates_to_process'] ?? []);
                foreach ($dates_to_process as $date) {
                    $persist_data = aidunite_schedule_persist_payload_from_wizard($parsed, $date, $user_id, $team_id);
                    $created_id = aidunite_schedule_create_published_post($persist_data);
                    if (is_wp_error($created_id)) {
                        $errors[] = $created_id->get_error_message();
                        continue;
                    }

                    if ($intent === 'recruit' && function_exists('aidunite_schedule_finalize_new_recruit')) {
                        aidunite_schedule_finalize_new_recruit((int) $created_id, $user_id, $team_id);
                    }

                    $success_count++;
                }
            }

            if ($success_count > 0) {
                if (
                    function_exists('aidunite_schedule_edit_should_redirect_to_mypage')
                    && aidunite_schedule_edit_should_redirect_to_mypage($team_id, $intent)
                ) {
                    wp_safe_redirect(add_query_arg('activation', 'stage2', home_url('/mypage/')));
                    exit;
                }

                $management_url = add_query_arg('refresh', '1', home_url('/schedule-management'));
                wp_redirect($management_url);
                exit;
            }

            if (empty($errors)) {
                $errors[] = 'スケジュールの保存に失敗しました';
            }
        } catch (Throwable $e) {
            $errors[] = 'スケジュールの保存中にエラーが発生しました: ' . $e->getMessage();
        }
    }
}

// ダッシュボードテンプレート用の変数設定
$page_title = 'スケジュール登録';
$page_description = 'スケジュール登録や編集ができます。';
$page_icon = ''; // アイコンなし
$required_role = 'team_leader';

// 4ステップウィザード（UX改善: 1)目的 → 2)日程時間 → 3)種別/条件 → 4)確認/登録）
$tabs = [
    ['id' => 'step1', 'label' => 'Step 1: 目的', 'icon_svg' => 'campaign'],
    ['id' => 'step2', 'label' => 'Step 2: 日程・時間', 'icon_svg' => 'calendar_month'],
    ['id' => 'step3', 'label' => 'Step 3: 種別・条件', 'icon_svg' => 'list_alt_add'],
    ['id' => 'step4', 'label' => 'Step 4: 確認・登録', 'icon_svg' => 'check_circle'],
];

// 各ステップのコンテンツ
$schedule_edit_wizard_steps = function_exists('aidunite_get_schedule_edit_wizard_step_definitions')
    ? aidunite_get_schedule_edit_wizard_step_definitions()
    : [];
$tab_contents = [
    [
        'id' => 'step1',
        'icon_svg' => 'campaign',
        'title' => $schedule_edit_wizard_steps[0]['title'] ?? '登録する目的を選びましょう',
        'content' => get_step1_content()
    ],
    [
        'id' => 'step2',
        'icon_svg' => 'calendar_month',
        'title' => $schedule_edit_wizard_steps[1]['title'] ?? 'いつ試合をしたいですか？',
        'content' => get_step3_content()
    ],
    [
        'id' => 'step3',
        'icon_svg' => 'list_alt_add',
        'title' => $schedule_edit_wizard_steps[2]['title'] ?? '試合条件を決めましょう。',
        'content' => get_step2_content()
    ],
    [
        'id' => 'step4',
        'icon_svg' => 'check_circle',
        'title' => $schedule_edit_wizard_steps[3]['title'] ?? '入力内容の確認。',
        'content' => get_step4_content()
    ]
];

// ステップ1のコンテンツ生成（カード形式）
function get_step1_content() {
    global $is_team_leader, $edit_post_id, $edit_schedule_meta, $schedule_edit_trial_simplified;
    ob_start();
    $edit_is_personal = ($edit_post_id > 0)
        ? (!empty($edit_schedule_meta)
            ? (($edit_schedule_meta['is_personal'] ?? '0') === '1')
            : false)
        : false;
    $trial            = $schedule_edit_trial_simplified;
    $team_vis_checked = (!$trial && !($edit_post_id > 0 && $edit_is_personal)) ? 'checked' : '';
    $personal_vis_checked = (!$trial && $edit_post_id > 0 && $edit_is_personal) ? 'checked' : '';
    ?>
    <div class="step-content schedule-wizard-step schedule-wizard-step--1<?php echo $trial ? ' step-content--trial-simplified' : ''; ?>">
        <?php aidunite_schedule_edit_wizard_heading_for_step(1); ?>

        <?php if ($is_team_leader) : ?>
        <div class="schedule-wizard-section schedule-visibility-selection" id="schedule_visibility_section">
            <div class="card-selection card-selection--2col" role="group" aria-label="予定の種類">
                <label class="selection-card selection-card--visibility<?php echo $trial ? ' is-locked' : ''; ?>">
                    <input type="radio" name="schedule_visibility" value="team" <?php echo esc_attr($team_vis_checked); ?>>
                    <span class="card-header-row">
                        <span class="card-icon"><?php echo aidunite_render_theme_icon('group', ['width' => '24', 'height' => '24']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                        <span class="card-title">チームの予定</span>
                    </span>
                    <div class="card-description">チーム全体で共有する予定を登録します</div>
                </label>
                <label class="selection-card selection-card--visibility<?php echo $trial ? ' selection-card--trial-hidden' : ''; ?>"<?php echo $trial ? ' hidden' : ''; ?>>
                    <input type="radio" name="schedule_visibility" value="personal" <?php echo esc_attr($personal_vis_checked); ?>>
                    <span class="card-header-row">
                        <span class="card-icon"><?php echo aidunite_render_theme_icon('lock', ['width' => '24', 'height' => '24']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                        <span class="card-title">プライベートの予定</span>
                    </span>
                    <div class="card-description">自分だけが使う予定を登録します（非公開）</div>
                </label>
            </div>
        </div>
        <?php else : ?>
        <input type="hidden" name="schedule_visibility" value="personal">
        <?php endif; ?>

        <div class="schedule-wizard-section intent-selection" id="purpose_section">
            <div class="card-selection card-selection--3col">
                <div class="selection-card" data-value="recruit" role="button" tabindex="0">
                    <div class="card-header-row">
                        <div class="card-icon"><?php echo aidunite_render_theme_icon('handshake', ['width' => '24', 'height' => '24']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                        <div class="card-title">試合を募集する <span class="schedule-wizard-badge schedule-wizard-badge--recommended">おすすめ</span></div>
                    </div>
                    <div class="card-description">条件に合うチームへ表示され、<br>申請・チャットでやり取りできます。</div>
                </div>
                <div class="selection-card<?php echo $trial ? ' selection-card--trial-hidden' : ''; ?>" data-value="confirmed"<?php echo $trial ? ' hidden' : ''; ?>>
                    <div class="card-header-row">
                        <div class="card-icon"><?php echo aidunite_render_theme_icon('check_circle', ['width' => '24', 'height' => '24']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                        <div class="card-title">確定の予定を登録</div>
                    </div>
                    <div class="card-description">確定した練習や試合を登録します。</div>
                </div>
                <div class="selection-card<?php echo $trial ? ' selection-card--trial-hidden' : ''; ?>" data-value="tentative"<?php echo $trial ? ' hidden' : ''; ?>>
                    <div class="card-header-row">
                        <div class="card-icon"><?php echo aidunite_render_theme_icon('calendar_month', ['width' => '24', 'height' => '24']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                        <div class="card-title">日程を仮押さえ</div>
                    </div>
                    <div class="card-description">未確定の日程を仮で登録します。<br>あとから内容を変更して確定できます。</div>
                </div>
            </div>
            <input type="hidden" name="intent" id="intent" required>
        </div>

        <div id="intent-error" class="error-message" style="display: none;">
            <div class="error-icon"><?php echo aidunite_render_theme_icon('brightness_alert', ['width' => '48', 'height' => '48'], 'aidunite-icon--warning'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <div class="error-text">目的を選択してください。</div>
        </div>

        <?php aidunite_schedule_edit_info_bar('登録した予定は、カレンダー・試合の募集などと連携されます。', 'blue', 'info'); ?>

        <div class="unified-navigation">
            <div class="nav-cta-group">
                <button type="button" id="nav-next" class="btn btn-primary" onclick="goToNextStep()" data-testid="schedule-nav-next-1">
                    <span class="btn__label">次へ</span><?php echo aidunite_render_theme_icon('chevron_right', ['width' => '20', 'height' => '20'], 'btn-nav-chevron'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </button>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// ステップ2のコンテンツ生成（種別選択＝UI上はStep3・種別・条件）
function get_step2_content() {
    global $is_team_leader, $edit_post_id, $edit_schedule_meta, $schedule_edit_trial_simplified, $team_id;
    $step3_venue_title   = '② 会場条件';
    $step3_gender_title  = '③ 性別条件';
    $step3_recruit_title = '④ 募集チーム数';
    $step3_memo_title    = '⑤ メモ';
    ob_start();
    ?>
    <div class="step-content schedule-wizard-step schedule-wizard-step--3<?php echo $schedule_edit_trial_simplified ? ' step-content--trial-simplified' : ''; ?>">
        <?php aidunite_schedule_edit_wizard_heading_for_step(3); ?>

        <div class="schedule-wizard-section">
            <div class="schedule-wizard-section__head">
                <h4 class="schedule-wizard-section__title">① 試合の種類</h4>
                <span class="schedule-wizard-badge schedule-wizard-badge--required">必須</span>
            </div>
            <div class="schedule-type-selection">
                <div class="card-selection card-selection--2col" id="schedule_type_cards"></div>
                <input type="hidden" name="schedule_type" id="schedule_type" required>
            </div>
            <div id="schedule-type-error" class="error-message" style="display: none;">
                <div class="error-icon"><?php echo aidunite_render_theme_icon('brightness_alert', ['width' => '48', 'height' => '48'], 'aidunite-icon--warning'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                <div class="error-text">種別を選択してください。</div>
            </div>
        </div>

        <div id="match_conditions" class="match-conditions" style="display:none;">
            <div class="schedule-wizard-section match-conditions__venue">
                <div class="schedule-wizard-section__head">
                    <h4 class="schedule-wizard-section__title"><?php echo esc_html( $step3_venue_title ); ?></h4>
                    <span class="schedule-wizard-badge schedule-wizard-badge--required">必須</span>
                </div>
                <div class="form-help" id="venue-condition-help" style="display: none;" role="status">
                    ※ 複数チーム募集の場合、会場は自チーム主催（ホーム）に固定されます。
                </div>
                <div class="card-selection card-selection--3col">
                    <div class="selection-card" data-value="both">
                        <div class="card-header-row">
                            <div class="card-icon"><?php echo aidunite_render_theme_icon('stadium', ['width' => '24', 'height' => '24']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                            <div class="card-title">どちらでも可 <span class="schedule-wizard-badge schedule-wizard-badge--recommended">おすすめ</span></div>
                        </div>
                        <div class="card-description">相手チームと相談して決めます。</div>
                    </div>
                    <div class="selection-card" data-value="home">
                        <div class="card-header-row">
                            <div class="card-icon"><?php echo aidunite_render_theme_icon('home', ['width' => '24', 'height' => '24']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                            <div class="card-title">ホーム（主催）</div>
                        </div>
                        <div class="card-description">自分たちの会場で開催します。</div>
                    </div>
                    <div class="selection-card" data-value="away">
                        <div class="card-header-row">
                            <div class="card-icon"><?php echo aidunite_render_theme_icon('flight_takeoff', ['width' => '24', 'height' => '24']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                            <div class="card-title">アウェイ</div>
                        </div>
                        <div class="card-description">相手チームの会場で開催します。</div>
                    </div>
                    <div class="selection-card" data-value="undecided" style="display:none;">
                        <div class="card-header-row">
                            <div class="card-icon"><?php echo aidunite_render_theme_icon('question_mark', ['width' => '24', 'height' => '24']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                            <div class="card-title">未定</div>
                        </div>
                        <div class="card-description">会場は未定</div>
                    </div>
                </div>
                <?php aidunite_schedule_edit_info_bar('「どちらでも可」にすると、より多くのチームへ表示されます。', 'blue', 'info'); ?>
                <input type="hidden" name="venue_condition" id="venue_condition">

                <div id="venue-name-section" style="display: none; margin-top: var(--spacing-sm);">
                    <label for="venue_name" class="schedule-wizard-section__title">会場名</label>
                    <input type="text" name="venue_name" id="venue_name" list="venue-names" class="form-control" placeholder="会場名を入力してください">
                    <datalist id="venue-names"></datalist>
                </div>
            </div>

            <div class="schedule-wizard-section match-conditions__gender">
                <div class="schedule-wizard-section__head">
                    <h4 class="schedule-wizard-section__title"><?php echo esc_html( $step3_gender_title ); ?></h4>
                    <span class="schedule-wizard-badge schedule-wizard-badge--required">必須</span>
                </div>
                <div id="gender-restrict-notice" class="schedule-wizard-info-bar schedule-wizard-info-bar--blue" style="display: none;" role="status"></div>
                <div class="card-selection card-selection--2col">
                    <div class="selection-card" data-value="male">
                        <div class="card-header-row">
                            <div class="card-icon"><?php echo aidunite_render_theme_icon('person_man', ['width' => '24', 'height' => '24']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                            <div class="card-title">男子</div>
                        </div>
                        <div class="card-description gender-description">男子チームとの対戦</div>
                    </div>
                    <div class="selection-card" data-value="female">
                        <div class="card-header-row">
                            <div class="card-icon"><?php echo aidunite_render_theme_icon('person_woman', ['width' => '24', 'height' => '24']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                            <div class="card-title">女子</div>
                        </div>
                        <div class="card-description gender-description">女子チームとの対戦</div>
                    </div>
                </div>
                <?php aidunite_schedule_edit_info_bar('登録されている性別が自動で選択されます。', 'blue', 'info'); ?>
                <input type="hidden" name="gender_condition" id="gender_condition">
            </div>

            <div class="schedule-wizard-section" id="team-count-section" style="display: none;">
                <div class="schedule-wizard-section__head">
                    <h4 class="schedule-wizard-section__title"><?php echo esc_html( $step3_recruit_title ); ?></h4>
                    <span class="schedule-wizard-badge schedule-wizard-badge--required">必須</span>
                </div>
                <div class="team-count-settings">
                    <div class="team-count-item">
                        <label class="team-count-label" for="male_teams">
                            <span class="team-icon"><?php echo aidunite_render_theme_icon('person_man', ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                            男子チーム
                        </label>
                        <div class="team-count-control">
                            <input type="range" name="male_teams" id="male_teams" min="0" max="5" value="1" class="team-slider" aria-describedby="total_teams_value">
                            <span class="team-count-value" id="male_teams_value">1チーム</span>
                        </div>
                    </div>
                    <div class="team-count-item">
                        <label class="team-count-label" for="female_teams">
                            <span class="team-icon"><?php echo aidunite_render_theme_icon('person_woman', ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                            女子チーム
                        </label>
                        <div class="team-count-control">
                            <input type="range" name="female_teams" id="female_teams" min="0" max="5" value="0" class="team-slider">
                            <span class="team-count-value" id="female_teams_value">0チーム</span>
                        </div>
                    </div>
                    <div class="total-teams">
                        募集チーム数: <span id="total_teams_value">1</span>チーム
                    </div>
                </div>
            </div>
        </div>

        <div class="schedule-wizard-section">
            <div class="schedule-wizard-section__head">
                <h4 class="schedule-wizard-section__title"><?php echo esc_html( $step3_memo_title ); ?></h4>
                <span class="schedule-wizard-badge schedule-wizard-badge--optional">任意</span>
            </div>
            <textarea name="schedule_quick_memo" id="schedule_quick_memo" class="schedule-memo-textarea" maxlength="200" placeholder="例：&#10;体育館の使用時間は各チーム負担 など" rows="4"></textarea>
            <div class="schedule-memo-counter"><span id="schedule_memo_count">0</span>/200</div>
        </div>

        <?php if (!$schedule_edit_trial_simplified) : ?>
        <div class="schedule-wizard-section form-section--attendance">
            <div class="schedule-wizard-section__head">
                <h4 class="schedule-wizard-section__title">⑥ 出欠確認</h4>
            </div>
            <label class="checkbox-label attendance-checkbox-label">
                <input type="checkbox" name="attendance_required" id="attendance_required" class="attendance-checkbox-input" value="1"
                    <?php
                    if ($edit_post_id > 0) {
                        $current_attendance_required = !empty($edit_schedule_meta)
                            ? ($edit_schedule_meta['attendance_required'] ?? '0')
                            : '0';
                        checked($current_attendance_required, '1');
                    }
                    ?>>
                <span class="attendance-checkbox-custom" aria-hidden="true"></span>
                <span class="attendance-checkbox-text">出欠確認が必要</span>
            </label>
            <?php aidunite_schedule_edit_info_bar('チェックすると、保護者・選手に出欠確認通知を送信します。', 'blue', 'info'); ?>
        </div>
        <?php endif; ?>

        <div class="unified-navigation">
            <button type="button" id="nav-prev-3" class="btn btn-ghost" onclick="goToPreviousStep()">
                <?php echo aidunite_render_theme_icon('chevron_left', ['width' => '20', 'height' => '20'], 'btn-nav-chevron'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span class="btn__label">戻る</span>
            </button>
            <button type="button" id="nav-next-3" class="btn btn-primary" onclick="goToNextStep()" data-testid="schedule-nav-next-3">
                <span class="btn__label">次へ</span><?php echo aidunite_render_theme_icon('chevron_right', ['width' => '20', 'height' => '20'], 'btn-nav-chevron'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// ステップ3のコンテンツ生成（日程・時間＝UI上はStep2）
function get_step3_content() {
    global $is_team_leader, $preset_date;
    ob_start();
    ?>
    <div class="step-content schedule-wizard-step schedule-wizard-step--2">
        <?php aidunite_schedule_edit_wizard_heading_for_step(2); ?>

        <div class="schedule-date-step">
            <div class="schedule-date-tabs" role="tablist" aria-label="日時の選び方">
                <button type="button" class="schedule-date-tab is-active" id="schedule-date-tab-calendar" role="tab" aria-selected="true" aria-controls="schedule-date-panel-calendar" data-date-mode="calendar">
                    <span class="schedule-date-tab__title">日付から選ぶ</span>
                    <span class="schedule-date-tab__desc">カレンダーから希望日を選択</span>
                </button>
                <button type="button" class="schedule-date-tab" id="schedule-date-tab-repeat" role="tab" aria-selected="false" aria-controls="schedule-date-panel-repeat" data-date-mode="repeat">
                    <span class="schedule-date-tab__title">繰り返しで選ぶ（任意）</span>
                    <span class="schedule-date-tab__desc">毎週の曜日や複数日をまとめて選択</span>
                </button>
            </div>

            <div id="schedule-date-panel-calendar" class="schedule-date-panel" role="tabpanel" aria-labelledby="schedule-date-tab-calendar">
                <div class="calendar-container">
                    <div class="calendar-header">
                        <button type="button" class="calendar-nav-btn calendar-nav-btn--month" id="schedule-edit-calendar-prev-btn" aria-label="前月">
                            <span class="calendar-nav-btn__chevron" aria-hidden="true"><?php echo aidunite_render_theme_icon('chevron_left', ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                        </button>
                        <div class="current-month calendar-title" id="current_month">2025年8月</div>
                        <button type="button" class="calendar-nav-btn calendar-nav-btn--month" id="schedule-edit-calendar-next-btn" aria-label="次月">
                            <span class="calendar-nav-btn__chevron" aria-hidden="true"><?php echo aidunite_render_theme_icon('chevron_right', ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                        </button>
                    </div>
                    <div class="calendar-grid" id="calendar_grid"></div>
                </div>
            </div>

            <div id="schedule-date-panel-repeat" class="schedule-date-panel" role="tabpanel" aria-labelledby="schedule-date-tab-repeat" hidden>
                <div class="weekday-selection-simple" id="weekday_selection_simple">
                    <div class="weekday-selection-header">
                        <span class="weekday-selection-label">繰り返す曜日を選択してください（当月のみ）</span>
                        <button type="button" class="btn btn-small" id="clear-weekday-selection">クリア</button>
                    </div>
                    <div class="weekday-buttons">
                        <button type="button" class="weekday-btn" data-weekday="0">日</button>
                        <button type="button" class="weekday-btn" data-weekday="1">月</button>
                        <button type="button" class="weekday-btn" data-weekday="2">火</button>
                        <button type="button" class="weekday-btn" data-weekday="3">水</button>
                        <button type="button" class="weekday-btn" data-weekday="4">木</button>
                        <button type="button" class="weekday-btn" data-weekday="5">金</button>
                        <button type="button" class="weekday-btn" data-weekday="6">土</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="selected-dates-display selected-dates-display--pill-chips" id="selected_dates_display" style="display: none;">
            <div class="selected-dates-info">
                <span class="selected-dates-label">選択済み</span>
                <div class="selected-dates-chips" id="selected_dates_text"></div>
            </div>
        </div>

        <input type="hidden" name="start_date" id="start_date" value="<?php echo isset($_GET['date']) && $preset_date !== '' ? esc_attr($preset_date) : ''; ?>" required>
        <input type="hidden" name="end_date" id="end_date">
        <input type="hidden" name="all_confirmed_dates" id="all_confirmed_dates">

        <div class="schedule-time-step time-settings schedule-wizard-section" role="group" aria-labelledby="schedule-time-range-title">
            <div class="schedule-time-range__head">
                <h4 class="schedule-time-range__title" id="schedule-time-range-title">時間帯を選択してください</h4>
                <p class="schedule-time-range__desc">開始時間と終了時間を選びましょう</p>
            </div>
            <div class="time-input-container schedule-time-range__row schedule-time-range__row--inline">
                <?php
                if (function_exists('aidunite_schedule_edit_render_time_picker_field')) {
                    aidunite_schedule_edit_render_time_picker_field('start', 13, '00', 'schedule-time-start-label', '開始時間');
                    aidunite_schedule_edit_render_time_picker_field('end', 14, '00', 'schedule-time-end-label', '終了時間');
                }
                ?>
            </div>
        </div>

        <div class="unified-navigation">
            <button type="button" id="nav-prev-2" class="btn btn-ghost" onclick="goToPreviousStep()">
                <?php echo aidunite_render_theme_icon('chevron_left', ['width' => '20', 'height' => '20'], 'btn-nav-chevron'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span class="btn__label">戻る</span>
            </button>
            <button type="button" id="nav-next-2" class="btn btn-primary" onclick="goToNextStep()" data-testid="schedule-nav-next-2">
                <span class="btn__label">次へ</span><?php echo aidunite_render_theme_icon('chevron_right', ['width' => '20', 'height' => '20'], 'btn-nav-chevron'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// ステップ4のコンテンツ生成（確認・登録）
function get_step4_content() {
    global $is_team_leader;
    ob_start();
    ?>
    <div class="step-content schedule-wizard-step schedule-wizard-step--4">
        <?php aidunite_schedule_edit_wizard_heading_for_step(4); ?>
        <?php aidunite_schedule_edit_info_bar('この内容は、条件に合うチームへ公開されます。', 'blue', 'info'); ?>

        <div class="schedule-confirm-table" id="schedule-confirm-table">
            <?php if ($is_team_leader) : ?>
            <div class="schedule-confirm-table__row">
                <div class="schedule-confirm-table__cell">
                    <span class="schedule-confirm-table__label">予定</span>
                    <span class="schedule-confirm-table__value" id="confirm_visibility">-</span>
                </div>
                <div class="schedule-confirm-table__cell">
                    <span class="schedule-confirm-table__label">目的</span>
                    <span class="schedule-confirm-table__value" id="confirm_intent">-</span>
                </div>
                <div class="schedule-confirm-table__cell">
                    <span class="schedule-confirm-table__label">日付</span>
                    <span class="schedule-confirm-table__value confirmation-value--date-list" id="confirm_start_date" data-type="date">-</span>
                </div>
            </div>
            <div class="schedule-confirm-table__row">
                <div class="schedule-confirm-table__cell">
                    <span class="schedule-confirm-table__label">時間</span>
                    <span class="schedule-confirm-table__value" id="confirm_time" data-type="time">-</span>
                </div>
                <div class="schedule-confirm-table__cell">
                    <span class="schedule-confirm-table__label">種別</span>
                    <span class="schedule-confirm-table__value" id="confirm_schedule_type">-</span>
                </div>
                <div class="schedule-confirm-table__cell">
                    <span class="schedule-confirm-table__label">性別</span>
                    <span class="schedule-confirm-table__value" id="confirm_gender">-</span>
                </div>
            </div>
            <div class="schedule-confirm-table__row">
                <div class="schedule-confirm-table__cell schedule-confirm-table__cell--full">
                    <span class="schedule-confirm-table__label">会場</span>
                    <span class="schedule-confirm-table__value" id="confirm_venue_condition_and_name">-</span>
                </div>
            </div>
            <div class="schedule-confirm-table__row" id="confirm_team_count_row" style="display:none;">
                <div class="schedule-confirm-table__cell">
                    <span class="schedule-confirm-table__label">募集数</span>
                    <span class="schedule-confirm-table__value" id="confirm_team_count">-</span>
                </div>
                <div class="schedule-confirm-table__cell">
                    <span class="schedule-confirm-table__label">メモ</span>
                    <span class="schedule-confirm-table__value" id="confirm_quick_memo">-</span>
                </div>
            </div>
            <div class="schedule-confirm-table__row" id="confirm_memo_row_leader">
                <div class="schedule-confirm-table__cell schedule-confirm-table__cell--full">
                    <span class="schedule-confirm-table__label">メモ</span>
                    <span class="schedule-confirm-table__value" id="confirm_quick_memo_single">-</span>
                </div>
            </div>
            <?php else : ?>
            <div class="schedule-confirm-table__row">
                <div class="schedule-confirm-table__cell">
                    <span class="schedule-confirm-table__label">目的</span>
                    <span class="schedule-confirm-table__value" id="confirm_intent">-</span>
                </div>
                <div class="schedule-confirm-table__cell">
                    <span class="schedule-confirm-table__label">日付</span>
                    <span class="schedule-confirm-table__value confirmation-value--date-list" id="confirm_start_date" data-type="date">-</span>
                </div>
                <div class="schedule-confirm-table__cell">
                    <span class="schedule-confirm-table__label">時間</span>
                    <span class="schedule-confirm-table__value" id="confirm_time" data-type="time">-</span>
                </div>
            </div>
            <div class="schedule-confirm-table__row">
                <div class="schedule-confirm-table__cell">
                    <span class="schedule-confirm-table__label">種別</span>
                    <span class="schedule-confirm-table__value" id="confirm_schedule_type">-</span>
                </div>
                <div class="schedule-confirm-table__cell">
                    <span class="schedule-confirm-table__label">性別</span>
                    <span class="schedule-confirm-table__value" id="confirm_gender">-</span>
                </div>
                <div class="schedule-confirm-table__cell">
                    <span class="schedule-confirm-table__label">会場</span>
                    <span class="schedule-confirm-table__value" id="confirm_venue_condition_and_name">-</span>
                </div>
            </div>
            <div class="schedule-confirm-table__row" id="confirm_team_count_row" style="display:none;">
                <div class="schedule-confirm-table__cell">
                    <span class="schedule-confirm-table__label">募集数</span>
                    <span class="schedule-confirm-table__value" id="confirm_team_count">-</span>
                </div>
                <div class="schedule-confirm-table__cell">
                    <span class="schedule-confirm-table__label">メモ</span>
                    <span class="schedule-confirm-table__value" id="confirm_quick_memo">-</span>
                </div>
            </div>
            <div class="schedule-confirm-table__row" id="confirm_memo_row">
                <div class="schedule-confirm-table__cell schedule-confirm-table__cell--full">
                    <span class="schedule-confirm-table__label">メモ</span>
                    <span class="schedule-confirm-table__value" id="confirm_quick_memo_single">-</span>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="schedule-wizard-info-bar schedule-wizard-info-bar--yellow">
            <span class="schedule-wizard-info-bar__icon" aria-hidden="true"><?php echo aidunite_render_theme_icon('lightbulb', ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
            <div>
                <p class="schedule-wizard-info-bar__text" style="font-weight: 600; margin-bottom: var(--spacing-xs);">登録する前にチェック</p>
                <ul class="schedule-confirm-checklist">
                    <li class="schedule-confirm-checklist__item">日程・時間は正しいですか？</li>
                    <li class="schedule-confirm-checklist__item">募集条件は適切ですか？</li>
                </ul>
            </div>
        </div>

        <div class="unified-navigation">
            <button type="button" id="nav-prev-4" class="btn btn-ghost" onclick="goToPreviousStep()">
                <?php echo aidunite_render_theme_icon('chevron_left', ['width' => '20', 'height' => '20'], 'btn-nav-chevron'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span class="btn__label">戻る</span>
            </button>
            <button type="submit" id="nav-submit-4" class="btn btn-primary" form="scheduleForm" data-testid="schedule-create-submit">
                試合を募集する
            </button>
        </div>

        <div id="loading-spinner" class="loading-spinner" style="display: none;">
            <div class="spinner-container">
                <div class="spinner"></div>
                <div class="spinner-text">登録中...</div>
            </div>
        </div>

        <div id="completion-message" class="completion-message" style="display: none;">
            <div class="completion-card">
                <div class="completion-icon"><?php echo aidunite_render_theme_icon('check_circle', ['width' => '48', 'height' => '48'], 'aidunite-icon--success'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                <div class="completion-title">募集を公開しました</div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}


$schedule_edit_title = $edit_post_id > 0 ? '予定を編集' : '試合を組む';
$schedule_edit_subtitle = $edit_post_id > 0 ? '予定の内容を更新します' : '';
$schedule_edit_wizard_stepper_html = function_exists('aidunite_render_schedule_edit_wizard_stepper_html')
    ? aidunite_render_schedule_edit_wizard_stepper_html()
    : '';

get_header();

$schedule_edit_shell_opened = false;
if (function_exists('aidunite_web_app_page_shell_open')) {
    aidunite_web_app_page_shell_open([
        'page_class' => 'page-schedule-edit page-schedule-edit--renewal',
        'title' => $schedule_edit_title,
        'subtitle' => $schedule_edit_subtitle,
        'back_url' => home_url('/schedule-management'),
        'content_class' => 'ainy-webapp-content--schedule-edit',
        'active_nav' => 'schedule',
        'hero' => [
            'title' => $schedule_edit_title,
            'subtitle' => $schedule_edit_subtitle,
            'back_url' => home_url('/schedule-management'),
            'active_nav' => 'schedule',
            'hero_body' => $schedule_edit_wizard_stepper_html,
        ],
    ]);
    $schedule_edit_shell_opened = true;
} else {
    echo '<div class="team-dashboard-container page-schedule-edit page-schedule-edit--renewal" data-testid="schedule-edit-page">';
}
?>
    <?php if (!empty($errors)): ?>
        <div class="error-message">
            <div class="error-icon"><?php echo aidunite_render_theme_icon('close', ['width' => '48', 'height' => '48'], 'aidunite-icon--danger'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <div class="error-content">
                <h3>入力エラーがあります</h3>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo esc_html($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <!-- 3ステップウィザード -->
    <div class="wizard-container page-schedule-edit--renewal<?php echo $schedule_edit_trial_simplified ? ' wizard-container--trial-simplified' : ''; ?>" data-testid="schedule-edit-page"<?php echo $schedule_edit_trial_simplified ? ' data-trial-simplified="1"' : ''; ?>>


        <!-- フォーム -->
        <form method="post" id="scheduleForm" class="wizard-form">
            <?php wp_nonce_field('aidunite_schedule_nonce', 'schedule_nonce'); ?>
            <?php if ( $edit_post_id > 0 ) : ?>
            <input type="hidden" name="post_id" value="<?php echo esc_attr( $edit_post_id ); ?>">
            <?php endif; ?>

            <!-- タブナビゲーション（フロントエンド非表示） -->
            <div class="tab-navigation" data-aidunite-tabs="skip" style="display: none;">
                <?php foreach ($tabs as $index => $tab): ?>
                    <button type="button" class="tab-btn <?php echo $index === 0 ? 'active' : ''; ?>"
                            data-tab="<?php echo esc_attr($tab['id']); ?>">
                        <?php echo aidunite_render_theme_icon($tab['icon_svg'], ['width' => '18', 'height' => '18'], 'aidunite-icon--inline'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html($tab['label']); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- タブコンテンツ -->
            <?php foreach ($tab_contents as $index => $content): ?>
                <section class="tab-content <?php echo $index === 0 ? 'active' : ''; ?>"
                         id="<?php echo esc_attr($content['id']); ?>">
                    <h2><?php echo esc_html($content['title']); ?></h2>
                        <?php echo $content['content']; ?>

                </section>
            <?php endforeach; ?>
        </form>
    </div>

<?php
if ($schedule_edit_shell_opened && function_exists('aidunite_web_app_page_shell_close')) {
    aidunite_web_app_page_shell_close();
} else {
    echo '</div>';
}
?>

    <?php
    // チームのホーム名と会場名履歴を取得（ページ冒頭で解決した操作中 team_id を使用）
    $home_venue_name = (string) ($team_edit_bundle['home_venue_name'] ?? '');
    $venue_name_history = $team_edit_bundle['venue_name_history'] ?? [];
    if (!is_array($venue_name_history)) {
        $venue_name_history = [];
    }

    $schedule_edit_activation_redirect = (
        function_exists('aidunite_schedule_edit_should_redirect_to_mypage')
        && aidunite_schedule_edit_should_redirect_to_mypage((int) $team_id, 'recruit')
    ) ? add_query_arg('activation', 'stage2', home_url('/mypage/')) : '';

    wp_localize_script('aidunite-schedule-edit', 'aiduniteScheduleEditPage', [
        'teamVenueData' => [
            'homeVenueName' => $home_venue_name,
            'venueHistory' => $venue_name_history,
        ],
        'aidunite' => [
            'presetDate' => $preset_date,
            'editPostId' => $edit_post_id > 0 ? $edit_post_id : null,
            'scheduleManagementUrl' => home_url('/schedule-management'),
            'defaultGenderConditionFromTeam' => $default_gender_condition_from_team,
            'teamGenderCanonical' => $team_gender_canonical,
            'recruitGenderLabels' => ['male' => '男子', 'female' => '女子'],
            'venueConditionLabels' => [
                'home' => 'ホーム',
                'away' => 'アウェイ',
                'either' => 'どちらでも可',
                'both' => 'どちらでも可',
                'undecided' => '未定',
            ],
            'trialSimplified' => (bool) $schedule_edit_trial_simplified,
            'trialRecruitGender' => function_exists('aidunite_activation_recruit_gender_for_team')
                ? aidunite_activation_recruit_gender_for_team((int) $team_id)
                : 'male',
            'activationRedirectUrl' => $schedule_edit_activation_redirect,
        ],
    ]);
    ?>

<!-- トースト・schedule.css は enqueue.php。以下はカレンダー用インラインスタイルのみ -->
<?php
require_once get_template_directory() . '/functions/schedule/schedule-calendar-common.php';
?>

<?php get_footer(); ?>
