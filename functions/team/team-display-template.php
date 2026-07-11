<?php
/**
 * チーム情報表示用共通テンプレート
 * AidUnite統一仕様対応版
 */

/*--------------------------------------------------------------
  チーム情報表示テンプレート
--------------------------------------------------------------*/

/**
 * team_type を canonical に正規化
 *
 * @param mixed $raw
 * @return string
 */
function aidunite_team_type_to_canonical($raw) {
    $value = trim((string) $raw);
    if ($value === '') {
        return '';
    }
    if (function_exists('aidunite_normalize_team_org_type_value')) {
        $normalized = aidunite_normalize_team_org_type_value($value);
        if ($normalized !== '') {
            return $normalized;
        }
    }
    return strtolower($value);
}

/**
 * team_type の日本語表示ラベル
 *
 * @param mixed $raw
 * @return string
 */
function aidunite_team_type_label($raw) {
    $canonical = aidunite_team_type_to_canonical($raw);
    $labels = aidunite_team_type_options_for_select();
    return $labels[$canonical] ?? (string) $raw;
}

/**
 * フォーム用 team_type 選択肢（value => 日本語ラベル）
 *
 * @return array<string, string>
 */
function aidunite_team_type_options_for_select() {
    return [
        'school' => '学校',
        'club' => 'クラブ',
        'corporate' => '企業',
        'community' => '地域',
        'other' => 'その他',
    ];
}

/**
 * @param int|string $raw_or_team_id team_id または raw team_type
 */
function aidunite_team_type_is_school($raw_or_team_id) {
    if (is_numeric($raw_or_team_id)) {
        $team_id = (int) $raw_or_team_id;
        if (function_exists('aidunite_team_read_canonical_meta')) {
            $raw = (string) (aidunite_team_read_canonical_meta($team_id)['team_type'] ?? '');
        } elseif (function_exists('aidunite_team_get_display_bundle')) {
            $raw = (string) (aidunite_team_get_display_bundle($team_id)['team_type'] ?? '');
        } else {
            $raw = (string) get_post_meta($team_id, 'team_type', true);
        }
    } else {
        $raw = (string) $raw_or_team_id;
    }
    return aidunite_team_type_to_canonical($raw) === 'school';
}

/**
 * @param int|string $raw_or_team_id team_id または raw team_type
 */
function aidunite_team_type_is_club($raw_or_team_id) {
    if (is_numeric($raw_or_team_id)) {
        $team_id = (int) $raw_or_team_id;
        if (function_exists('aidunite_team_read_canonical_meta')) {
            $raw = (string) (aidunite_team_read_canonical_meta($team_id)['team_type'] ?? '');
        } elseif (function_exists('aidunite_team_get_display_bundle')) {
            $raw = (string) (aidunite_team_get_display_bundle($team_id)['team_type'] ?? '');
        } else {
            $raw = (string) get_post_meta($team_id, 'team_type', true);
        }
    } else {
        $raw = (string) $raw_or_team_id;
    }
    return aidunite_team_type_to_canonical($raw) === 'club';
}

/**
 * payment config キー（school / club）
 *
 * @param int|string $raw_or_team_id
 */
function aidunite_team_type_payment_config_key($raw_or_team_id) {
    return aidunite_team_type_is_club($raw_or_team_id) ? 'club' : 'school';
}

/**
 * チーム情報を表示場面に応じて表示する共通関数
 *
 * @param int $team_id チームID
 * @param string $display_mode 表示モード ('admin_approval', 'match_request', 'supporter_view')
 * @param array $additional_data 追加データ（マッチ条件など）
 * @return string HTML出力
 */
function aidunite_display_team_info($team_id, $display_mode = 'supporter_view', $additional_data = []) {
    if (!$team_id) {
        return '<p>⚠️ チーム情報が見つかりません。</p>';
    }

    $team_post = get_post($team_id);
    if (!$team_post || $team_post->post_type !== 'team') {
        return '<p>⚠️ 有効なチーム情報が見つかりません。</p>';
    }

    // チーム情報を取得
    $team_info = aidunite_get_team_display_data($team_id);

    // チーム情報が空の場合はエラーメッセージを返す
    if (empty($team_info)) {
        return '<p>⚠️ チーム情報の取得に失敗しました。</p>';
    }

    // 表示モードに応じてHTMLを生成
    try {
        switch ($display_mode) {
            case 'admin_approval':
                return aidunite_generate_admin_approval_view($team_info, $additional_data);
            case 'match_request':
                return aidunite_generate_match_request_view($team_info, $additional_data);
            case 'supporter_view':
                return aidunite_generate_supporter_view($team_info, $additional_data);
            default:
                return aidunite_generate_supporter_view($team_info, $additional_data);
        }
    } catch (Exception $e) {
        return '<p>⚠️ チーム情報の表示中にエラーが発生しました。</p>';
    }
}

/**
 * チーム表示用データを取得
 *
 * @param int $team_id チームID
 * @return array チーム情報配列
 */
function aidunite_get_team_display_data($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return [];
    }

    $team_post = get_post($team_id);
    if (!$team_post || $team_post->post_type !== 'team') {
        return [];
    }

    $bundle = function_exists('aidunite_team_get_display_bundle')
        ? aidunite_team_get_display_bundle($team_id)
        : [];

    if ($bundle === []) {
        return [];
    }

    $sport_type = (string) ($bundle['sport_type'] ?? '');
    $team_category = (string) ($bundle['team_category'] ?? '');
    $team_type_raw = (string) ($bundle['team_type'] ?? '');
    $team_gender_option = (string) ($bundle['team_gender_option'] ?? '');
    $region = (string) ($bundle['region'] ?? '');
    $team_description = (string) ($bundle['team_description'] ?? '');
    $team_achievements = (string) ($bundle['team_achievements'] ?? '');
    $team_logo = (string) ($bundle['team_logo'] ?? '');
    $registrant_name = (string) ($bundle['registrant_name'] ?? '');
    $contact_mail = (string) ($bundle['contact_mail'] ?? '');
    $contact_phone = (string) ($bundle['contact_phone'] ?? '');
    $team_website = (string) get_post_meta($team_id, 'team_website', true);
    $team_sns_url = (string) get_post_meta($team_id, 'team_sns_url', true);
    $team_name_kana = (string) ($bundle['team_name_kana'] ?? '');

    return [
        'team_id' => $team_id,
        'team_name' => (string) ($bundle['team_name'] ?? get_the_title($team_id)),
        'team_name_kana' => $team_name_kana !== '' ? $team_name_kana : '',
        'sport_type' => $sport_type !== '' ? $sport_type : '未設定',
        'team_category' => $team_category !== '' ? $team_category : '未設定',
        'team_type' => $team_type_raw !== '' ? aidunite_team_type_label($team_type_raw) : '未設定',
        'team_gender_option' => $team_gender_option !== '' ? $team_gender_option : '未設定',
        'region' => $region !== '' ? $region : '未設定',
        'team_description' => $team_description,
        'team_achievements' => $team_achievements,
        'team_logo' => $team_logo,
        'team_website' => $team_website !== '' ? $team_website : '',
        'team_sns_url' => $team_sns_url !== '' ? $team_sns_url : '',
        'registrant_name' => $registrant_name !== '' ? $registrant_name : '',
        'contact_mail' => $contact_mail !== '' ? $contact_mail : '',
        'contact_phone' => $contact_phone !== '' ? $contact_phone : '',
        'post_status' => get_post_status($team_id),
        'post_author' => get_post_field('post_author', $team_id),
    ];
}

/**
 * チーム管理（管理者）画面: team_status メタの正規化（未設定は active）
 */
function aidunite_team_management_normalize_team_status($raw) {
    $raw = (string) $raw;
    return $raw === '' ? 'active' : $raw;
}

/**
 * チーム管理（管理者）画面: team_status の表示ラベル
 */
function aidunite_team_management_get_team_status_label($status) {
    $status = aidunite_team_management_normalize_team_status($status);
    $labels = [
        'active' => '有効',
        'pending' => '承認待ち',
        'inactive' => '無効',
    ];

    return $labels[$status] ?? $status;
}

/**
 * チーム管理（管理者）画面: 投稿ステータスの表示ラベル
 */
function aidunite_team_management_get_post_status_label($post_status) {
    $labels = [
        'publish' => '公開',
        'pending' => '投稿承認待ち',
        'draft' => '下書き',
        'trash' => 'ゴミ箱',
    ];

    return $labels[$post_status] ?? ($post_status !== '' ? $post_status : '—');
}

/**
 * チーム管理（管理者）画面: フィルター選択肢（登録フォームと同一の正規値）
 *
 * @return array<string, array{label:string, options:array<string,string>}>
 */
function aidunite_team_management_get_filter_option_groups() {
    return [
        'status_filter' => [
            'label' => '状態',
            'options' => [
                '' => 'すべて',
                'active' => '有効',
                'pending' => '承認待ち',
                'inactive' => '無効',
            ],
        ],
        'type_filter' => [
            'label' => '種別',
            'options' => [
                '' => 'すべて',
                '学校' => '学校',
                'クラブ' => 'クラブ',
                '企業' => '企業',
                '地域' => '地域',
                'その他' => 'その他',
            ],
        ],
        'sport_filter' => [
            'label' => '種目',
            'options' => [
                '' => 'すべて',
                'バスケットボール' => 'バスケットボール',
                'サッカー' => 'サッカー',
                '野球' => '野球',
                'テニス' => 'テニス',
                'バレーボール' => 'バレーボール',
                'その他' => 'その他',
            ],
        ],
        'category_filter' => [
            'label' => '年代',
            'options' => [
                '' => 'すべて',
                '小学生' => '小学生',
                '中学生' => '中学生',
                '高校生' => '高校生',
                '大学生' => '大学生',
                '社会人' => '社会人',
                'シニア' => 'シニア',
            ],
        ],
        'gender_filter' => [
            'label' => '性別',
            'options' => [
                '' => 'すべて',
                'male' => '男子',
                'female' => '女子',
                'both' => '男子・女子可',
            ],
        ],
        'region_filter' => [
            'label' => '都道府県',
            'options' => function_exists('aidunite_team_activity_prefecture_filter_options')
                ? aidunite_team_activity_prefecture_filter_options()
                : ['' => 'すべて'],
        ],
    ];
}

/**
 * チーム管理（管理者）: チーム名フィルター用プルダウン（登録済みチーム一覧）
 *
 * @return array<string, string> team_id => 表示ラベル
 */
function aidunite_team_management_get_team_name_filter_options() {
    $teams = get_posts([
        'post_type' => 'team',
        'post_status' => ['publish', 'pending', 'draft'],
        'numberposts' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
    ]);

    $options = ['' => 'すべて'];
    foreach ($teams as $team) {
        if (function_exists('aidunite_team_looks_like_test_fixture') && aidunite_team_looks_like_test_fixture((int) $team->ID)) {
            continue;
        }
        $options[(string) $team->ID] = $team->post_title . '（ID:' . $team->ID . '）';
    }

    return $options;
}

/**
 * チーム管理（管理者）: 検出されたテスト用チームの注意バナー HTML
 *
 * @return string
 */
function aidunite_team_management_render_test_fixture_notice() {
    if (!function_exists('aidunite_discover_test_fixture_team_ids')) {
        return '';
    }

    $fixture_ids = aidunite_discover_test_fixture_team_ids();
    if ($fixture_ids === []) {
        return '';
    }

    $labels = [];
    foreach (array_slice($fixture_ids, 0, 5) as $fid) {
        $post = get_post($fid);
        $labels[] = $post ? $post->post_title . '（ID:' . $fid . '）' : ('ID:' . $fid);
    }
    $more = count($fixture_ids) > 5 ? ' 他 ' . (count($fixture_ids) - 5) . ' 件' : '';

    ob_start();
    ?>
    <div class="notice notice-warning aidunite-test-fixture-notice" style="margin:var(--spacing-base) 0; padding:var(--spacing-base); border-radius:var(--radius-base); border:1px solid var(--warning-color, #ffc107); background:rgba(255, 193, 7, 0.12);">
        <?php
        $deletable_ids = function_exists('aidunite_discover_test_fixture_team_ids_for_deletion')
            ? aidunite_discover_test_fixture_team_ids_for_deletion()
            : [];
        ?>
        <p style="margin:0 0 var(--spacing-sm) 0;">
            <strong>テスト用の可能性があるチームが <?php echo (int) count($fixture_ids); ?> 件あります。</strong>
            PHPUnit・E2E・手動検証などで作成されたデータが残っている可能性があります。
        </p>
        <p style="margin:0 0 var(--spacing-base) 0; font-size:var(--font-size-sm, 0.875rem); color:var(--text-secondary);">
            例: <?php echo esc_html(implode('、', $labels) . $more); ?>
        </p>
        <p style="margin:0 0 var(--spacing-base) 0; font-size:var(--font-size-sm, 0.875rem); color:var(--text-secondary);">
            一括削除できるのは<strong>テスト用マーク（aidunite_test_fixture）が付いた <?php echo (int) count($deletable_ids); ?> 件のみ</strong>です。名前が「テストチーム」の本番データは自動削除されません。個別にカードから削除してください。
        </p>
        <?php if (!empty($deletable_ids)) : ?>
        <form method="post" style="margin:0;" data-aidunite-confirm="テスト用マーク付きの <?php echo (int) count($deletable_ids); ?> 件のみ削除します。よろしいですか？" data-aidunite-confirm-label="削除する">
            <?php wp_nonce_field('delete_test_fixture_teams'); ?>
            <button type="submit" name="delete_test_fixture_teams" value="1" class="button button-warning">テスト用マーク付きチームを削除（<?php echo (int) count($deletable_ids); ?> 件）</button>
        </form>
        <?php endif; ?>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * チーム管理（管理者）画面: GET からフィルター値を取得（不正値は空に）
 *
 * @return array{team_filter:string,status_filter:string,type_filter:string,sport_filter:string,category_filter:string,gender_filter:string,region_filter:string}
 */
function aidunite_team_management_get_filters_from_request() {
    $groups = aidunite_team_management_get_filter_option_groups();
    $team_name_options = aidunite_team_management_get_team_name_filter_options();
    $team_filter_raw = isset($_GET['team_filter']) ? sanitize_text_field(wp_unslash($_GET['team_filter'])) : '';
    $filters = [
        'team_filter' => array_key_exists($team_filter_raw, $team_name_options) ? $team_filter_raw : '',
    ];

    foreach ($groups as $key => $group) {
        $raw = isset($_GET[$key]) ? sanitize_text_field(wp_unslash($_GET[$key])) : '';
        $filters[$key] = array_key_exists($raw, $group['options']) ? $raw : '';
    }

    return $filters;
}

/**
 * チーム管理（管理者）画面: フィルターが1件でも有効か
 *
 * @param array $filters aidunite_team_management_get_filters_from_request() の戻り値
 */
function aidunite_team_management_has_active_filters(array $filters) {
    if (($filters['team_filter'] ?? '') !== '') {
        return true;
    }
    foreach (aidunite_team_management_get_filter_option_groups() as $key => $group) {
        if (($filters[$key] ?? '') !== '') {
            return true;
        }
    }

    return false;
}

/**
 * チーム管理（管理者）画面: ページネーション等に載せるクエリ引数
 *
 * @param array $filters
 * @return array<string, string>
 */
function aidunite_team_management_filters_to_query_args(array $filters) {
    $args = [];
    if (($filters['team_filter'] ?? '') !== '') {
        $args['team_filter'] = $filters['team_filter'];
    }
    foreach (aidunite_team_management_get_filter_option_groups() as $key => $group) {
        if (($filters[$key] ?? '') !== '') {
            $args[$key] = $filters[$key];
        }
    }

    return $args;
}

/**
 * チーム管理（管理者）画面: チームがフィルター条件に一致するか
 *
 * @param int   $team_id
 * @param array $filters
 */
function aidunite_team_management_team_matches_filters($team_id, array $filters) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return false;
    }

    $team_filter = $filters['team_filter'] ?? '';
    if ($team_filter !== '' && (string) $team_id !== (string) (int) $team_filter) {
        return false;
    }

    $status_filter = $filters['status_filter'] ?? '';
    if ($status_filter !== '') {
        $team_status = aidunite_team_management_normalize_team_status(get_post_meta($team_id, 'team_status', true));
        if ($team_status !== $status_filter) {
            return false;
        }
    }

    $type_filter = $filters['type_filter'] ?? '';
    if ($type_filter !== '') {
        $team_type = aidunite_team_type_to_canonical((string) get_post_meta($team_id, 'team_type', true));
        $filter_type = aidunite_team_type_to_canonical($type_filter);
        if ($team_type !== $filter_type) {
            return false;
        }
    }

    $sport_filter = $filters['sport_filter'] ?? '';
    if ($sport_filter !== '' && (string) get_post_meta($team_id, 'sport_type', true) !== $sport_filter) {
        return false;
    }

    $category_filter = $filters['category_filter'] ?? '';
    if ($category_filter !== '' && (string) get_post_meta($team_id, 'team_category', true) !== $category_filter) {
        return false;
    }

    $gender_filter = $filters['gender_filter'] ?? '';
    if ($gender_filter !== '') {
        $gender_raw = (string) get_post_meta($team_id, 'team_gender_option', true);
        if (function_exists('aidunite_normalize_team_gender_option')) {
            $gender_raw = aidunite_normalize_team_gender_option($gender_raw);
        }
        if ($gender_raw !== $gender_filter) {
            return false;
        }
    }

    $region_filter = $filters['region_filter'] ?? '';
    if ($region_filter !== '') {
        $pref = '';
        if (function_exists('aidunite_get_team_activity_profile')) {
            $pref = (string) (aidunite_get_team_activity_profile($team_id)['activity_prefecture'] ?? '');
        }
        if ($pref === '') {
            $legacy_block = (string) get_post_meta($team_id, 'region', true);
            if ($legacy_block !== '' && function_exists('aidunite_team_activity_legacy_region_to_prefecture')) {
                $pref = aidunite_team_activity_legacy_region_to_prefecture($legacy_block);
            }
        }
        if ($pref !== $region_filter) {
            return false;
        }
    }

    return true;
}

/**
 * チーム管理（管理者）画面: 適用中フィルターの表示用ラベル一覧
 *
 * @param array $filters
 * @return array<string, string> ラベル名 => 表示値
 */
function aidunite_team_management_get_active_filter_labels(array $filters) {
    $active = [];
    $team_filter = $filters['team_filter'] ?? '';
    if ($team_filter !== '') {
        $team_options = aidunite_team_management_get_team_name_filter_options();
        $active['チーム名'] = $team_options[$team_filter] ?? ('ID ' . $team_filter);
    }

    $groups = aidunite_team_management_get_filter_option_groups();
    foreach ($groups as $key => $group) {
        $val = $filters[$key] ?? '';
        if ($val !== '' && isset($group['options'][$val])) {
            $active[$group['label']] = $group['options'][$val];
        }
    }

    return $active;
}

/**
 * チーム管理（管理者）: 代表者 user ID の表示名（ユーザー名 → 投稿 registrant_name）。
 *
 * @param int $leader_id
 * @param int $team_id_fallback
 * @return string
 */
function aidunite_team_management_get_leader_display_label($leader_id, $team_id_fallback = 0) {
    $leader_id = (int) $leader_id;
    if ($leader_id > 0) {
        $user = get_userdata($leader_id);
        if ($user && $user->display_name !== '') {
            return $user->display_name;
        }
    }
    $team_id_fallback = (int) $team_id_fallback;
    if ($team_id_fallback > 0) {
        $reg = (string) get_post_meta($team_id_fallback, 'registrant_name', true);
        if ($reg !== '') {
            return $reg;
        }
    }
    return $leader_id > 0 ? ('ユーザー #' . $leader_id) : '代表者未設定';
}

/**
 * チーム管理（管理者）: 代表者ごとのチーム件数（フィルター後の全件ベース）。
 *
 * @param WP_Post[] $teams
 * @return array<int, int> leader_user_id => count（0 は未設定）
 */
function aidunite_team_management_count_teams_by_leader(array $teams) {
    $counts = [];
    foreach ($teams as $team) {
        if (!$team instanceof WP_Post) {
            continue;
        }
        $leader_id = function_exists('aidunite_team_resolve_leader_user_id')
            ? aidunite_team_resolve_leader_user_id((int) $team->ID)
            : 0;
        if ($leader_id <= 0) {
            $leader_id = 0;
        }
        if (!isset($counts[$leader_id])) {
            $counts[$leader_id] = 0;
        }
        $counts[$leader_id]++;
    }
    return $counts;
}

/**
 * 代表者のメインチーム ID（primary_team_id → レガシー team_id → managed 先頭）。
 *
 * @param int $leader_id
 * @return int
 */
function aidunite_team_management_resolve_leader_primary_team_id($leader_id) {
    $leader_id = (int) $leader_id;
    if ($leader_id <= 0) {
        return 0;
    }
    $primary = (int) get_user_meta($leader_id, 'primary_team_id', true);
    if ($primary > 0) {
        return $primary;
    }
    $legacy = (int) get_user_meta($leader_id, 'team_id', true);
    if ($legacy > 0) {
        return $legacy;
    }
    if (function_exists('aidunite_read_user_managed_team_ids_meta')) {
        $managed = aidunite_read_user_managed_team_ids_meta($leader_id);
        if (!empty($managed)) {
            return (int) $managed[0];
        }
    }
    return 0;
}

/**
 * チーム管理（管理者）: 代表者 ID 昇順 → 同一代表内はメインチームを先頭（左端）→ 以降は新しい順。
 *
 * @param WP_Post[] $teams
 * @return WP_Post[]
 */
function aidunite_team_management_sort_teams_by_leader(array $teams) {
    usort($teams, static function ($a, $b) {
        $aid = $a instanceof WP_Post ? (int) $a->ID : 0;
        $bid = $b instanceof WP_Post ? (int) $b->ID : 0;
        $la = function_exists('aidunite_team_resolve_leader_user_id')
            ? aidunite_team_resolve_leader_user_id($aid)
            : 0;
        $lb = function_exists('aidunite_team_resolve_leader_user_id')
            ? aidunite_team_resolve_leader_user_id($bid)
            : 0;
        if ($la === 0 && $lb !== 0) {
            return 1;
        }
        if ($la !== 0 && $lb === 0) {
            return -1;
        }
        if ($la !== $lb) {
            return $la <=> $lb;
        }
        if ($la > 0 && function_exists('aidunite_team_management_resolve_leader_primary_team_id')) {
            $primary_tid = aidunite_team_management_resolve_leader_primary_team_id($la);
            if ($primary_tid > 0) {
                $a_primary = $aid === $primary_tid ? 0 : 1;
                $b_primary = $bid === $primary_tid ? 0 : 1;
                if ($a_primary !== $b_primary) {
                    return $a_primary <=> $b_primary;
                }
            }
        }
        $da = $a instanceof WP_Post ? strtotime($a->post_date) : 0;
        $db = $b instanceof WP_Post ? strtotime($b->post_date) : 0;
        if ($da !== $db) {
            return $db <=> $da;
        }
        return $bid <=> $aid;
    });
    return $teams;
}

/**
 * 現在ページの team 投稿を代表者グループに分割（表示順を維持）。
 *
 * @param WP_Post[] $teams_page
 * @return array<int, array{leader_id:int,leader_key:int,teams:WP_Post[]}>
 */
function aidunite_team_management_group_posts_by_leader(array $teams_page) {
    $groups = [];
    $order = [];
    foreach ($teams_page as $team) {
        if (!$team instanceof WP_Post) {
            continue;
        }
        $leader_id = function_exists('aidunite_team_resolve_leader_user_id')
            ? (int) aidunite_team_resolve_leader_user_id((int) $team->ID)
            : 0;
        $leader_key = $leader_id > 0 ? $leader_id : 0;
        if (!isset($groups[$leader_key])) {
            $groups[$leader_key] = [
                'leader_id' => $leader_id,
                'leader_key' => $leader_key,
                'teams' => [],
            ];
            $order[] = $leader_key;
        }
        $groups[$leader_key]['teams'][] = $team;
    }
    $out = [];
    foreach ($order as $leader_key) {
        $out[] = $groups[$leader_key];
    }
    return $out;
}

/**
 * チーム管理（管理者）画面: 所属ユーザー ID（`aidunite_get_team_affiliated_user_ids` のエイリアス）。
 *
 * @param int $team_id
 * @return int[]
 */
function aidunite_team_management_collect_member_user_ids($team_id) {
    return function_exists('aidunite_get_team_affiliated_user_ids')
        ? aidunite_get_team_affiliated_user_ids($team_id)
        : [];
}

/**
 * チーム管理（管理者）: フラッシュ通知を保存（POST-Redirect-GET 用）
 *
 * @param string $type success|warning|error
 * @param string $message
 */
function aidunite_team_management_set_flash_notice($type, $message) {
    $user_id = get_current_user_id();
    if ($user_id <= 0) {
        return;
    }
    set_transient(
        'aidunite_tml_notice_' . $user_id,
        [
            'type' => $type,
            'message' => (string) $message,
        ],
        120
    );
}

/**
 * @return array{type:string,message:string}|null
 */
function aidunite_team_management_consume_flash_notice() {
    $user_id = get_current_user_id();
    if ($user_id <= 0) {
        return null;
    }
    $key = 'aidunite_tml_notice_' . $user_id;
    $notice = get_transient($key);
    if ($notice) {
        delete_transient($key);
    }

    return is_array($notice) ? $notice : null;
}

/**
 * チーム管理（管理者）: 削除後リダイレクト先（フィルター・ページを維持）
 *
 * @param array<string, string> $filter_query_args
 * @return string
 */
function aidunite_team_management_get_redirect_url_after_action(array $filter_query_args = []) {
    $referer = wp_get_referer();
    $base = $referer ? $referer : get_permalink();
    $parsed = wp_parse_url($base);
    $path = isset($parsed['path']) ? $parsed['path'] : '';
    $permalinks = [trailingslashit(get_permalink())];
    if ($path !== '' && trailingslashit(home_url($path)) !== trailingslashit(get_permalink())) {
        $base = get_permalink();
    }

    return add_query_arg(array_filter($filter_query_args), $base);
}

/**
 * チームに紐づくスケジュール件数（一覧カード表示用・軽量カウント）
 *
 * @param int $team_id
 * @return int
 */
function aidunite_team_management_count_schedules($team_id) {
    $team_id = (int) $team_id;
    if ($team_id <= 0) {
        return 0;
    }

    $query = new WP_Query([
        'post_type' => 'schedule',
        'post_status' => ['publish', 'pending', 'draft', 'private'],
        'meta_key' => 'team_id',
        'meta_value' => (string) $team_id,
        'posts_per_page' => 1,
        'fields' => 'ids',
        'no_found_rows' => false,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    ]);

    return (int) $query->found_posts;
}

/**
 * チーム管理（管理者）画面: 所属ユーザーを aidunite_role 別に集計。
 * 総メンバー＝代表者＋選手＋保護者＋その他（ロール未設定・general 等）で一致させる。
 *
 * @param int $team_id
 * @return array{total:int,team_leader:int,player:int,parent:int,other:int,other_roles:array<string,int>}
 */
function aidunite_team_management_count_members($team_id) {
    $team_id = (int) $team_id;
    $counts = [
        'total' => 0,
        'team_leader' => 0,
        'player' => 0,
        'parent' => 0,
        'other' => 0,
        'other_roles' => [],
    ];
    if ($team_id <= 0) {
        return $counts;
    }

    $member_ids = aidunite_team_management_collect_member_user_ids($team_id);
    $leader_id = function_exists('aidunite_team_resolve_leader_user_id')
        ? aidunite_team_resolve_leader_user_id($team_id)
        : (int) get_post_meta($team_id, 'team_leader_id', true);

    $counts['total'] = count($member_ids);
    foreach ($member_ids as $user_id) {
        $user_id = (int) $user_id;
        if ($leader_id > 0 && $user_id === $leader_id) {
            $counts['team_leader']++;
            continue;
        }
        $role = (string) get_user_meta($user_id, 'aidunite_role', true);
        if ($role === 'team_leader') {
            $counts['team_leader']++;
        } elseif ($role === 'player') {
            $counts['player']++;
        } elseif ($role === 'parent') {
            $counts['parent']++;
        } else {
            $counts['other']++;
            $label = $role !== '' ? $role : '（ロール未設定）';
            if (!isset($counts['other_roles'][$label])) {
                $counts['other_roles'][$label] = 0;
            }
            $counts['other_roles'][$label]++;
        }
    }

    return $counts;
}

/**
 * チーム管理（管理者）画面: カード表示用ラベル一式（性別・日付・地域など統一定義）
 *
 * @param int $team_id
 * @return array<string, string>
 */
function aidunite_team_management_get_display_labels($team_id) {
    $team_id = (int) $team_id;
    $post = get_post($team_id);
    $info = aidunite_get_team_display_data($team_id);
    if (empty($info)) {
        return [];
    }

    $unset = '未設定';

    $gender_raw = (string) get_post_meta($team_id, 'team_gender_option', true);
    if (function_exists('aidunite_normalize_team_gender_option')) {
        $gender_raw = aidunite_normalize_team_gender_option($gender_raw);
    }
    if (function_exists('aidunite_team_public_profile_gender_label')) {
        $gender_label = aidunite_team_public_profile_gender_label($gender_raw);
    } elseif (function_exists('aidunite_get_gender_label')) {
        $gender_label = aidunite_get_gender_label($gender_raw);
    } else {
        $gender_label = $gender_raw;
    }
    if ($gender_label === '' || $gender_label === '不明') {
        $gender_label = $gender_raw !== '' ? $gender_raw : $unset;
    }

    $region = function_exists('aidunite_team_activity_display_label')
        ? aidunite_team_activity_display_label($team_id)
        : (string) get_post_meta($team_id, 'region', true);
    if ($region === '' || $region === '地域未設定') {
        $region = (string) get_post_meta($team_id, 'team_location', true);
    }

    $created_label = $unset;
    if ($post && !empty($post->post_date)) {
        $created_label = class_exists('AidUniteDateUtils')
            ? AidUniteDateUtils::formatDate($post->post_date, AidUniteDateUtils::DATE_DISPLAY_SHORT)
            : date_i18n('y/m/d', strtotime($post->post_date));
    }

    $team_status = aidunite_team_management_normalize_team_status(get_post_meta($team_id, 'team_status', true));

    $resolve_field = static function ($val) use ($unset) {
        $val = (string) $val;
        return ($val === '' || $val === $unset) ? $unset : $val;
    };

    return [
        'sport_type' => $resolve_field($info['sport_type'] ?? ''),
        'team_category' => $resolve_field($info['team_category'] ?? ''),
        'team_type' => $resolve_field($info['team_type'] ?? ''),
        'gender_label' => $gender_label,
        'region' => $region !== '' ? $region : $unset,
        'created_date' => $created_label,
        'team_status' => $team_status,
        'team_status_label' => aidunite_team_management_get_team_status_label($team_status),
        'post_status' => $post ? (string) $post->post_status : '',
        'post_status_label' => aidunite_team_management_get_post_status_label($post ? $post->post_status : ''),
    ];
}

/**
 * 管理者承認画面用の表示を生成（統一UI：カード形式）
 *
 * @param array $team_info チーム情報
 * @param array $additional_data 追加データ
 * @return string HTML
 */
function aidunite_generate_admin_approval_view($team_info, $additional_data = []) {
    $team_id = (int) ($team_info['team_id'] ?? 0);
    $context = function_exists('aidunite_get_team_application_review_context')
        ? aidunite_get_team_application_review_context($team_id)
        : [];

    if ($context === []) {
        $context = [
            'team_id'          => $team_id,
            'team_name'        => (string) ($team_info['team_name'] ?? ''),
            'post_status'      => (string) ($team_info['post_status'] ?? ''),
            'team_logo'        => (string) ($team_info['team_logo'] ?? ''),
            'logo_crop'        => ['x' => 0, 'y' => 0, 'zoom' => 100],
            'team_description' => (string) ($team_info['team_description'] ?? ''),
        ];
    }

    $rows = function_exists('aidunite_get_team_application_review_rows')
        ? aidunite_get_team_application_review_rows($context, [
            'include_submitted_at' => true,
            'include_contact'      => true,
        ])
        : [];

    $logo_url = (string) ($context['team_logo'] ?? '');
    $logo_crop = isset($context['logo_crop']) && is_array($context['logo_crop']) ? $context['logo_crop'] : ['x' => 0, 'y' => 0, 'zoom' => 100];
    $logo_style = sprintf(
        'transform: translate(%d%%, %d%%) scale(%s);',
        (int) ($logo_crop['x'] ?? 0),
        (int) ($logo_crop['y'] ?? 0),
        max(0.5, (int) ($logo_crop['zoom'] ?? 100) / 100)
    );

    $post_status = (string) ($context['post_status'] ?? $team_info['post_status'] ?? 'pending');
    $team_name = (string) ($context['team_name'] ?? $team_info['team_name'] ?? '');

    $html = '<article class="team-approval-card">';
    $html .= '<div class="team-approval-card__content">';

    $html .= '<div class="team-approval-card__header">';
    $html .= '<h3 class="team-approval-card__title">' . esc_html($team_name) . '</h3>';
    $html .= '<span class="team-approval-card__badge team-approval-card__badge--' . esc_attr($post_status) . '">';
    $html .= $post_status === 'pending' ? '承認待ち' : '承認済み';
    $html .= '</span>';
    $html .= '</div>';

    $html .= '<div class="team-approval-card__body">';

    if ($logo_url !== '') {
        $html .= '<div class="team-approval-card__logo-wrap">';
        $html .= '<div class="team-approval-card__logo-frame">';
        $html .= '<img class="team-approval-card__logo" src="' . esc_url($logo_url) . '" alt="' . esc_attr($team_name) . 'のロゴ" width="72" height="72" decoding="async" style="' . esc_attr($logo_style) . '">';
        $html .= '</div></div>';
    }

    $html .= '<div class="team-approval-card__grid">';
    foreach ($rows as $row) {
        if (($row['format'] ?? 'text') === 'multiline') {
            continue;
        }
        $html .= aidunite_generate_application_review_info_item($row);
    }
    $html .= '</div>';

    foreach ($rows as $row) {
        if (($row['format'] ?? 'text') !== 'multiline') {
            continue;
        }
        if ((string) ($row['value'] ?? '') === '' || (string) ($row['value'] ?? '') === '—') {
            continue;
        }
        $value_html = function_exists('aidunite_format_application_review_value_html')
            ? aidunite_format_application_review_value_html($row)
            : esc_html((string) ($row['value'] ?? '—'));

        $html .= '<div class="team-approval-card__desc">';
        $html .= '<span class="team-approval-card__label">' . esc_html((string) ($row['label'] ?? '')) . '</span>';
        $html .= '<p class="team-approval-card__value">' . $value_html . '</p>';
        $html .= '</div>';
    }

    $html .= '</div>';

    if ($post_status === 'pending') {
        $approve_url = wp_nonce_url(admin_url('admin-post.php?action=approve_team_creation&team_id=' . $team_id), 'approve_team_creation_' . $team_id);
        $approve_confirm_message = $team_name !== ''
            ? $team_name . 'の申請を承認しますか？'
            : 'チームの申請を承認しますか？';

        $html .= '<div class="team-approval-card__actions">';
        $html .= '<a href="' . esc_url($approve_url) . '" class="team-approval-card__btn team-approval-card__btn--approve" data-aidunite-confirm="' . esc_attr($approve_confirm_message) . '" data-aidunite-confirm-label="承認する" data-aidunite-confirm-variant="success" data-aidunite-confirm-message-align="center">承認する</a>';
        $html .= '</div>';

        $html .= '<form class="team-approval-card__revision" method="post" action="' . esc_url(admin_url('admin-post.php')) . '" data-aidunite-confirm="確認・修正依頼を送信しますか？" data-aidunite-confirm-label="送信する" data-aidunite-confirm-variant="primary">';
        $html .= wp_nonce_field('request_team_revision_' . $team_id, '_wpnonce', true, false);
        $html .= '<input type="hidden" name="action" value="request_team_revision">';
        $html .= '<input type="hidden" name="team_id" value="' . esc_attr((string) $team_id) . '">';
        $html .= '<label class="team-approval-card__revision-label" for="team-revision-message-' . esc_attr((string) $team_id) . '">確認・修正依頼内容</label>';
        $html .= '<textarea id="team-revision-message-' . esc_attr((string) $team_id) . '" class="team-approval-card__revision-input" name="revision_message" rows="4" required placeholder="例：チーム名の表記を確認してください。正式名称をご記入のうえ再申請をお願いします。"></textarea>';
        $html .= '<p class="team-approval-card__revision-note">申請者のマイページ・通知一覧・メールに表示されます。「却下」ではなく修正依頼として送信されます。</p>';
        $html .= '<button type="submit" class="team-approval-card__btn team-approval-card__btn--revision">修正依頼を送る</button>';
        $html .= '</form>';
    }

    $html .= '</div>';
    $html .= '</article>';

    return $html;
}

/**
 * マッチ申請画面用の表示を生成
 *
 * @param array $team_info チーム情報
 * @param array $additional_data マッチ条件などの追加データ
 * @return string HTML
 */
function aidunite_generate_match_request_view($team_info, $additional_data = []) {
    $html = '<div class="team-info-match-request">';

    // ヘッダー
    $html .= '<div class="team-info-header">';
    $html .= '<h3>' . esc_html($team_info['team_name']) . '</h3>';
    if ($team_info['team_logo']) {
        $html .= '<div class="team-logo">';
        $html .= '<img src="' . esc_url($team_info['team_logo']) . '" alt="チームロゴ" style="max-width: 150px; height: auto;">';
        $html .= '</div>';
    }
    $html .= '</div>';

    // 基本情報セクション
    $html .= '<div class="team-info-section">';
    $html .= '<h4>チーム基本情報</h4>';
    $html .= '<div class="info-grid">';
    $html .= aidunite_generate_info_item('競技種目', $team_info['sport_type']);
    $html .= aidunite_generate_info_item('年代カテゴリ', $team_info['team_category']);
    $html .= aidunite_generate_info_item('所属タイプ', $team_info['team_type']);
    $html .= aidunite_generate_info_item('性別', $team_info['team_gender_option']);
    $html .= aidunite_generate_info_item('地域', $team_info['region']);
    $html .= '</div>';
    $html .= '</div>';

    // チーム紹介
    if ($team_info['team_description']) {
        $html .= '<div class="team-info-section">';
        $html .= '<h4>チーム紹介</h4>';
        $html .= '<div class="team-description">' . nl2br(esc_html($team_info['team_description'])) . '</div>';
        $html .= '</div>';
    }

    // 実績
    if ($team_info['team_achievements']) {
        $html .= '<div class="team-info-section">';
        $html .= '<h4>実績</h4>';
        $html .= '<div class="team-achievements">' . nl2br(esc_html($team_info['team_achievements'])) . '</div>';
        $html .= '</div>';
    }

    // マッチ条件（追加データから）
    if (!empty($additional_data['match_conditions'])) {
        $html .= '<div class="team-info-section">';
        $html .= '<h4>マッチ条件</h4>';
        $html .= '<div class="match-conditions">';
        foreach ($additional_data['match_conditions'] as $condition) {
            $html .= '<div class="condition-item">';
            $html .= '<strong>' . esc_html($condition['label']) . ':</strong> ';
            $html .= esc_html($condition['value']);
            $html .= '</div>';
        }
        $html .= '</div>';
        $html .= '</div>';
    }

    $html .= '</div>';

    return $html;
}

/**
 * 支援者・一般ユーザー向けの表示を生成
 *
 * @param array $team_info チーム情報
 * @param array $additional_data 追加データ
 * @return string HTML
 */
function aidunite_generate_supporter_view($team_info, $additional_data = []) {
    $html = '<div class="team-info-supporter-view">';

    // ヘッダー
    $html .= '<div class="team-info-header">';
    $html .= '<h3>' . esc_html($team_info['team_name']) . '</h3>';
    if ($team_info['team_logo']) {
        $html .= '<div class="team-logo">';
        $html .= '<img src="' . esc_url($team_info['team_logo']) . '" alt="チームロゴ" style="max-width: 150px; height: auto;">';
        $html .= '</div>';
    }
    $html .= '</div>';

    // 基本情報セクション
    $html .= '<div class="team-info-section">';
    $html .= '<h4>チーム基本情報</h4>';
    $html .= '<div class="info-grid">';
    $html .= aidunite_generate_info_item('競技種目', $team_info['sport_type']);
    $html .= aidunite_generate_info_item('年代カテゴリ', $team_info['team_category']);
    $html .= aidunite_generate_info_item('所属タイプ', $team_info['team_type']);
    $html .= aidunite_generate_info_item('性別', $team_info['team_gender_option']);
    $html .= aidunite_generate_info_item('地域', $team_info['region']);
    $html .= '</div>';
    $html .= '</div>';

    // チーム紹介
    if ($team_info['team_description']) {
        $html .= '<div class="team-info-section">';
        $html .= '<h4>チーム紹介</h4>';
        $html .= '<div class="team-description">' . nl2br(esc_html($team_info['team_description'])) . '</div>';
        $html .= '</div>';
    }

    // 支援ボタン（追加データに応じて）
    if (!empty($additional_data['show_support_button'])) {
        $html .= '<div class="team-info-actions">';
        $html .= '<button class="btn btn-support" onclick="supportTeam(' . $team_info['team_id'] . ')">';
        $html .= '❤️ このチームを応援する';
        $html .= '</button>';
        $html .= '</div>';
    }

    $html .= '</div>';

    return $html;
}

/**
 * 情報項目を生成するヘルパー関数
 *
 * @param string $label ラベル
 * @param string $value 値
 * @return string HTML
 */
function aidunite_generate_info_item($label, $value) {
    if (empty($value)) {
        return '';
    }

    return '<div class="info-item">' .
           '<label>' . esc_html($label) . '</label>' .
           '<div class="info-value">' . esc_html($value) . '</div>' .
           '</div>';
}

/**
 * 申請内容レビュー行（承認カード用）
 *
 * @param array{label?:string, value?:string, format?:string} $row
 */
function aidunite_generate_application_review_info_item($row) {
    $label = (string) ($row['label'] ?? '');
    if ($label === '') {
        return '';
    }

    $value_html = function_exists('aidunite_format_application_review_value_html')
        ? aidunite_format_application_review_value_html($row)
        : esc_html((string) ($row['value'] ?? '—'));

    return '<div class="info-item">' .
           '<label>' . esc_html($label) . '</label>' .
           '<div class="info-value">' . $value_html . '</div>' .
           '</div>';
}

/**
 * チーム情報表示用のCSSを出力
 */
function aidunite_team_display_styles() {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $css = get_stylesheet_directory() . '/assets/css/components/team-display.css';
    if (is_readable($css)) {
        wp_enqueue_style(
            'aidunite-team-display',
            get_stylesheet_directory_uri() . '/assets/css/components/team-display.css',
            ['aidunite-style'],
            (string) filemtime($css)
        );
    }
}
/**
 * チーム支援用 JavaScript
 */
function aidunite_team_support_script() {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $js = get_stylesheet_directory() . '/assets/js/team/team-support.js';
    if (!is_readable($js)) {
        return;
    }
    wp_enqueue_script(
        'aidunite-team-support',
        get_stylesheet_directory_uri() . '/assets/js/team/team-support.js',
        ['aidunite-confirm-modal', 'aidunite-toast-notification'],
        (string) filemtime($js),
        true
    );
    wp_localize_script('aidunite-team-support', 'aiduniteTeamSupport', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('support_team_nonce'),
    ]);
}
