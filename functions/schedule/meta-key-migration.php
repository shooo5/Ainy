<?php
/**
 * メタキー統一移行スクリプト
 * Phase 4: 既存データの移行
 *
 * matching_gender_condition → schedule_gender
 * schedule_place_option → schedule_place
 * male_teams / female_teams → male_slots / female_slots
 * schedule_note → schedule_quick_memo
 *
 * 既に統一キーが存在する場合はスキップ（上書きしない）
 * 新規保存は schedule-persist.php が正本キーのみ書き込み（Phase 4）
 */

// 直接実行を防ぐ
if (!defined('ABSPATH')) {
    exit;
}

/**
 * メタキー統一移行を実行
 *
 * @return array 移行結果
 */
function aidunite_migrate_meta_keys() {
    global $wpdb;

    error_log("[META-UNIFY Phase4] Starting meta key migration");

    $results = [
        'gender_migrated' => 0,
        'gender_skipped' => 0,
        'gender_errors' => 0,
        'place_migrated' => 0,
        'place_skipped' => 0,
        'place_errors' => 0,
        'male_slots_migrated' => 0,
        'male_slots_skipped' => 0,
        'male_slots_errors' => 0,
        'female_slots_migrated' => 0,
        'female_slots_skipped' => 0,
        'female_slots_errors' => 0,
        'memo_migrated' => 0,
        'memo_skipped' => 0,
        'memo_errors' => 0,
        'total_schedules' => 0,
    ];

    // すべてのスケジュールを取得
    $schedules = get_posts([
        'post_type' => 'schedule',
        'post_status' => 'any',
        'posts_per_page' => -1,
        'fields' => 'ids'
    ]);

    $results['total_schedules'] = count($schedules);
    error_log("[META-UNIFY Phase4] Found {$results['total_schedules']} schedules to process");

    foreach ($schedules as $schedule_id) {
        // 性別条件の移行
        $old_gender = get_post_meta($schedule_id, 'matching_gender_condition', true);
        $new_gender = get_post_meta($schedule_id, 'schedule_gender', true);

        if (!empty($old_gender)) {
            if (empty($new_gender)) {
                // 統一キーが存在しない場合、コピー
                $result = update_post_meta($schedule_id, 'schedule_gender', $old_gender);
                if ($result !== false) {
                    $results['gender_migrated']++;
                    error_log("[META-UNIFY Phase4] Migrated gender for schedule_id={$schedule_id}: {$old_gender}");
                } else {
                    $results['gender_errors']++;
                    error_log("[META-UNIFY Phase4] ERROR: Failed to migrate gender for schedule_id={$schedule_id}");
                }
            } else {
                // 既に統一キーが存在する場合、スキップ
                $results['gender_skipped']++;
                error_log("[META-UNIFY Phase4] Skipped gender migration for schedule_id={$schedule_id} (already exists: {$new_gender})");
            }
        }

        // 会場条件の移行
        $old_place = get_post_meta($schedule_id, 'schedule_place_option', true);
        $new_place = get_post_meta($schedule_id, 'schedule_place', true);

        if (!empty($old_place)) {
            if (empty($new_place)) {
                // 統一キーが存在しない場合、コピー
                $result = update_post_meta($schedule_id, 'schedule_place', $old_place);
                if ($result !== false) {
                    $results['place_migrated']++;
                    error_log("[META-UNIFY Phase4] Migrated place for schedule_id={$schedule_id}: {$old_place}");
                } else {
                    $results['place_errors']++;
                    error_log("[META-UNIFY Phase4] ERROR: Failed to migrate place for schedule_id={$schedule_id}");
                }
            } else {
                // 既に統一キーが存在する場合、スキップ
                $results['place_skipped']++;
                error_log("[META-UNIFY Phase4] Skipped place migration for schedule_id={$schedule_id} (already exists: {$new_place})");
            }
        }

        $old_male = get_post_meta($schedule_id, 'male_teams', true);
        $new_male = get_post_meta($schedule_id, 'male_slots', true);
        if ($old_male !== '' && $old_male !== false) {
            if ($new_male === '' || $new_male === false) {
                $result = update_post_meta($schedule_id, 'male_slots', (int) $old_male);
                if ($result !== false) {
                    $results['male_slots_migrated']++;
                } else {
                    $results['male_slots_errors']++;
                }
            } else {
                $results['male_slots_skipped']++;
            }
        }

        $old_female = get_post_meta($schedule_id, 'female_teams', true);
        $new_female = get_post_meta($schedule_id, 'female_slots', true);
        if ($old_female !== '' && $old_female !== false) {
            if ($new_female === '' || $new_female === false) {
                $result = update_post_meta($schedule_id, 'female_slots', (int) $old_female);
                if ($result !== false) {
                    $results['female_slots_migrated']++;
                } else {
                    $results['female_slots_errors']++;
                }
            } else {
                $results['female_slots_skipped']++;
            }
        }

        $old_memo = get_post_meta($schedule_id, 'schedule_note', true);
        $new_memo = get_post_meta($schedule_id, 'schedule_quick_memo', true);
        if ($old_memo !== '' && $old_memo !== false) {
            if ($new_memo === '' || $new_memo === false) {
                $result = update_post_meta($schedule_id, 'schedule_quick_memo', $old_memo);
                if ($result !== false) {
                    $results['memo_migrated']++;
                } else {
                    $results['memo_errors']++;
                }
            } else {
                $results['memo_skipped']++;
            }
        }
    }

    error_log("[META-UNIFY Phase4] Migration completed. Results: " . json_encode($results));

    return $results;
}

/**
 * 移行実行（管理者のみ）
 */
function aidunite_run_meta_key_migration() {
    // 統一認証・権限チェック（管理者のみ）
    require_once get_template_directory() . '/functions/common/auth-middleware.php';
    $auth_result = AidUniteAuthMiddleware::require_admin(false);
    if (!$auth_result->is_valid()) {
        wp_die($auth_result->error ?: '権限がありません');
    }

    // CSRF対策（統一版）
    $nonce_result = AidUniteAuthMiddleware::verify_nonce('_wpnonce', 'aidunite_meta_key_migration');
    if (is_wp_error($nonce_result)) {
        wp_die($nonce_result->get_error_message(), 'エラー', ['response' => 403]);
    }

    // 移行実行
    $results = aidunite_migrate_meta_keys();

    // 結果をオプションに保存
    update_option('aidunite_meta_key_migration_results', $results);
    update_option('aidunite_meta_key_migration_date', current_time('mysql'));

    // リダイレクト
    wp_redirect(add_query_arg([
        'page' => 'aidunite-meta-key-migration',
        'migrated' => '1'
    ], admin_url('tools.php')));
    exit;
}

// 管理画面で移行を実行するためのアクションフック
add_action('admin_post_aidunite_migrate_meta_keys', 'aidunite_run_meta_key_migration');

/**
 * 管理画面に移行ページを追加
 */
function aidunite_add_meta_key_migration_page() {
    add_management_page(
        'メタキー統一移行',
        'メタキー統一移行',
        'manage_options',
        'aidunite-meta-key-migration',
        'aidunite_render_meta_key_migration_page'
    );
}
add_action('admin_menu', 'aidunite_add_meta_key_migration_page');

/**
 * 移行ページの表示
 */
function aidunite_render_meta_key_migration_page() {
    // 移行結果を取得
    $results = get_option('aidunite_meta_key_migration_results', null);
    $migration_date = get_option('aidunite_meta_key_migration_date', null);
    $migrated = isset($_GET['migrated']) && $_GET['migrated'] === '1';

    ?>
    <div class="wrap">
        <h1>メタキー統一移行</h1>
        <p>既存のメタキーを統一キーに移行します。</p>
        <ul>
            <li><strong>matching_gender_condition</strong> → <strong>schedule_gender</strong></li>
            <li><strong>schedule_place_option</strong> → <strong>schedule_place</strong></li>
            <li><strong>male_teams</strong> / <strong>female_teams</strong> → <strong>male_slots</strong> / <strong>female_slots</strong></li>
            <li><strong>schedule_note</strong> → <strong>schedule_quick_memo</strong></li>
        </ul>
        <p><strong>Phase 4 書き込み:</strong> 新規・更新の保存は正本キーのみ（旧キーは読取フォールバックのみ）。緊急時のみ <code>AIDUNITE_SCHEDULE_LEGACY_META_WRITES</code> を true に定義。</p>
        <p><strong>注意:</strong> 既に統一キーが存在する場合はスキップされます（上書きしません）。</p>

        <?php if ($migrated && $results): ?>
            <div class="notice notice-success is-dismissible">
                <h3>移行完了</h3>
                <p>移行日時: <?php echo esc_html($migration_date); ?></p>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>項目</th>
                            <th>結果</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>処理対象スケジュール数</td>
                            <td><?php echo esc_html($results['total_schedules']); ?>件</td>
                        </tr>
                        <tr>
                            <td>性別条件: 移行完了</td>
                            <td><?php echo esc_html($results['gender_migrated']); ?>件</td>
                        </tr>
                        <tr>
                            <td>性別条件: スキップ（既に存在）</td>
                            <td><?php echo esc_html($results['gender_skipped']); ?>件</td>
                        </tr>
                        <tr>
                            <td>性別条件: エラー</td>
                            <td><?php echo esc_html($results['gender_errors']); ?>件</td>
                        </tr>
                        <tr>
                            <td>会場条件: 移行完了</td>
                            <td><?php echo esc_html($results['place_migrated']); ?>件</td>
                        </tr>
                        <tr>
                            <td>会場条件: スキップ（既に存在）</td>
                            <td><?php echo esc_html($results['place_skipped']); ?>件</td>
                        </tr>
                        <tr>
                            <td>会場条件: エラー</td>
                            <td><?php echo esc_html($results['place_errors']); ?>件</td>
                        </tr>
                        <tr>
                            <td>男子枠: 移行完了</td>
                            <td><?php echo esc_html($results['male_slots_migrated'] ?? 0); ?>件</td>
                        </tr>
                        <tr>
                            <td>女子枠: 移行完了</td>
                            <td><?php echo esc_html($results['female_slots_migrated'] ?? 0); ?>件</td>
                        </tr>
                        <tr>
                            <td>メモ: 移行完了</td>
                            <td><?php echo esc_html($results['memo_migrated'] ?? 0); ?>件</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <?php wp_nonce_field('aidunite_meta_key_migration'); ?>
            <input type="hidden" name="action" value="aidunite_migrate_meta_keys">
            <p>
                <input type="submit" class="button button-primary" value="移行を実行" onclick="return confirm('移行を実行しますか？既存の統一キーは上書きされません。');">
            </p>
        </form>

        <hr>

        <h2>移行前の確認</h2>
        <p>以下のクエリで移行対象のデータを確認できます：</p>
        <pre style="background: #f5f5f5; padding: 10px; border: 1px solid #ddd;">
SELECT post_id, meta_key, meta_value
FROM wp_postmeta
WHERE meta_key IN ('matching_gender_condition', 'schedule_place_option')
AND post_id IN (
    SELECT ID FROM wp_posts WHERE post_type = 'schedule'
)
ORDER BY post_id, meta_key;
        </pre>

        <?php aidunite_render_meta_key_cleanup_section(); ?>
    </div>
    <?php
}

/**
 * Phase 5: 旧メタキーの削除（クリーンアップ）
 *
 * @return array 削除結果
 */
function aidunite_cleanup_old_meta_keys() {
    global $wpdb;

    error_log("[META-UNIFY Phase5] Starting old meta key cleanup");

    $results = [
        'gender_deleted' => 0,
        'gender_errors' => 0,
        'place_deleted' => 0,
        'place_errors' => 0,
        'male_teams_deleted' => 0,
        'male_teams_errors' => 0,
        'female_teams_deleted' => 0,
        'female_teams_errors' => 0,
        'memo_deleted' => 0,
        'memo_errors' => 0,
        'total_schedules' => 0,
    ];

    // すべてのスケジュールを取得
    $schedules = get_posts([
        'post_type' => 'schedule',
        'post_status' => 'any',
        'posts_per_page' => -1,
        'fields' => 'ids'
    ]);

    $results['total_schedules'] = count($schedules);
    error_log("[META-UNIFY Phase5] Found {$results['total_schedules']} schedules to process");

    foreach ($schedules as $schedule_id) {
        // 統一キーの存在確認
        $new_gender = get_post_meta($schedule_id, 'schedule_gender', true);
        $new_place = get_post_meta($schedule_id, 'schedule_place', true);

        // 旧キーの存在確認
        $old_gender = get_post_meta($schedule_id, 'matching_gender_condition', true);
        $old_place = get_post_meta($schedule_id, 'schedule_place_option', true);

        // 性別条件の削除（統一キーが存在する場合のみ）
        if (!empty($old_gender) && !empty($new_gender)) {
            $result = delete_post_meta($schedule_id, 'matching_gender_condition');
            if ($result) {
                $results['gender_deleted']++;
                error_log("[META-UNIFY Phase5] Deleted matching_gender_condition for schedule_id={$schedule_id} (unified key exists: {$new_gender})");
            } else {
                $results['gender_errors']++;
                error_log("[META-UNIFY Phase5] ERROR: Failed to delete matching_gender_condition for schedule_id={$schedule_id}");
            }
        } elseif (!empty($old_gender) && empty($new_gender)) {
            // 統一キーが存在しない場合はスキップ（安全のため）
            error_log("[META-UNIFY Phase5] Skipped deletion of matching_gender_condition for schedule_id={$schedule_id} (unified key does not exist)");
        }

        // 会場条件の削除（統一キーが存在する場合のみ）
        if (!empty($old_place) && !empty($new_place)) {
            $result = delete_post_meta($schedule_id, 'schedule_place_option');
            if ($result) {
                $results['place_deleted']++;
                error_log("[META-UNIFY Phase5] Deleted schedule_place_option for schedule_id={$schedule_id} (unified key exists: {$new_place})");
            } else {
                $results['place_errors']++;
                error_log("[META-UNIFY Phase5] ERROR: Failed to delete schedule_place_option for schedule_id={$schedule_id}");
            }
        } elseif (!empty($old_place) && empty($new_place)) {
            // 統一キーが存在しない場合はスキップ（安全のため）
            error_log("[META-UNIFY Phase5] Skipped deletion of schedule_place_option for schedule_id={$schedule_id} (unified key does not exist)");
        }

        $old_male_teams = get_post_meta($schedule_id, 'male_teams', true);
        $new_male_slots = get_post_meta($schedule_id, 'male_slots', true);
        if ($old_male_teams !== '' && $old_male_teams !== false && $new_male_slots !== '' && $new_male_slots !== false) {
            if (delete_post_meta($schedule_id, 'male_teams')) {
                $results['male_teams_deleted']++;
            } else {
                $results['male_teams_errors']++;
            }
        }

        $old_female_teams = get_post_meta($schedule_id, 'female_teams', true);
        $new_female_slots = get_post_meta($schedule_id, 'female_slots', true);
        if ($old_female_teams !== '' && $old_female_teams !== false && $new_female_slots !== '' && $new_female_slots !== false) {
            if (delete_post_meta($schedule_id, 'female_teams')) {
                $results['female_teams_deleted']++;
            } else {
                $results['female_teams_errors']++;
            }
        }

        $old_memo = get_post_meta($schedule_id, 'schedule_note', true);
        $new_memo = get_post_meta($schedule_id, 'schedule_quick_memo', true);
        if ($old_memo !== '' && $old_memo !== false && $new_memo !== '' && $new_memo !== false) {
            if (delete_post_meta($schedule_id, 'schedule_note')) {
                $results['memo_deleted']++;
            } else {
                $results['memo_errors']++;
            }
        }
    }

    error_log("[META-UNIFY Phase5] Cleanup completed. Results: " . json_encode($results));

    return $results;
}

/**
 * クリーンアップ実行（管理者のみ）
 */
function aidunite_run_meta_key_cleanup() {
    // 統一認証・権限チェック（管理者のみ）
    require_once get_template_directory() . '/functions/common/auth-middleware.php';
    $auth_result = AidUniteAuthMiddleware::require_admin(false);
    if (!$auth_result->is_valid()) {
        wp_die($auth_result->error ?: '権限がありません');
    }

    // CSRF対策（統一版）
    $nonce_result = AidUniteAuthMiddleware::verify_nonce('_wpnonce', 'aidunite_meta_key_cleanup');
    if (is_wp_error($nonce_result)) {
        wp_die($nonce_result->get_error_message(), 'エラー', ['response' => 403]);
    }

    // クリーンアップ実行
    $results = aidunite_cleanup_old_meta_keys();

    // 結果をオプションに保存
    update_option('aidunite_meta_key_cleanup_results', $results);
    update_option('aidunite_meta_key_cleanup_date', current_time('mysql'));

    // リダイレクト
    wp_redirect(add_query_arg([
        'page' => 'aidunite-meta-key-migration',
        'cleaned' => '1'
    ], admin_url('tools.php')));
    exit;
}

// 管理画面でクリーンアップを実行するためのアクションフック
add_action('admin_post_aidunite_cleanup_meta_keys', 'aidunite_run_meta_key_cleanup');

/**
 * 移行ページにクリーンアップセクションを追加
 */
function aidunite_render_meta_key_cleanup_section() {
    // クリーンアップ結果を取得
    $cleanup_results = get_option('aidunite_meta_key_cleanup_results', null);
    $cleanup_date = get_option('aidunite_meta_key_cleanup_date', null);
    $cleaned = isset($_GET['cleaned']) && $_GET['cleaned'] === '1';

    ?>
    <hr style="margin: 30px 0;">
    <h2>Phase 5: 旧メタキーの削除（クリーンアップ）</h2>
    <p>統一キーが存在するスケジュールから、旧メタキーを削除します。</p>
    <ul>
        <li><strong>matching_gender_condition</strong> → 削除（統一キーが存在する場合のみ）</li>
        <li><strong>schedule_place_option</strong> → 削除（統一キーが存在する場合のみ）</li>
        <li><strong>male_teams</strong> / <strong>female_teams</strong> → 削除（<strong>male_slots</strong> / <strong>female_slots</strong> がある場合のみ）</li>
        <li><strong>schedule_note</strong> → 削除（<strong>schedule_quick_memo</strong> がある場合のみ）</li>
    </ul>
    <p><strong>注意:</strong> 統一キーが存在しない場合はスキップされます（安全のため）。</p>

    <?php if ($cleaned && $cleanup_results): ?>
        <div class="notice notice-success is-dismissible">
            <h3>クリーンアップ完了</h3>
            <p>クリーンアップ日時: <?php echo esc_html($cleanup_date); ?></p>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>項目</th>
                        <th>結果</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>処理対象スケジュール数</td>
                        <td><?php echo esc_html($cleanup_results['total_schedules']); ?>件</td>
                    </tr>
                    <tr>
                        <td>性別条件: 削除完了</td>
                        <td><?php echo esc_html($cleanup_results['gender_deleted']); ?>件</td>
                    </tr>
                    <tr>
                        <td>性別条件: エラー</td>
                        <td><?php echo esc_html($cleanup_results['gender_errors']); ?>件</td>
                    </tr>
                    <tr>
                        <td>会場条件: 削除完了</td>
                        <td><?php echo esc_html($cleanup_results['place_deleted']); ?>件</td>
                    </tr>
                    <tr>
                        <td>会場条件: エラー</td>
                        <td><?php echo esc_html($cleanup_results['place_errors']); ?>件</td>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
        <?php wp_nonce_field('aidunite_meta_key_cleanup'); ?>
        <input type="hidden" name="action" value="aidunite_cleanup_meta_keys">
        <p>
            <input type="submit" class="button button-primary" value="クリーンアップを実行" onclick="return confirm('旧メタキーを削除しますか？統一キーが存在しない場合はスキップされます。');">
        </p>
    </form>
    <?php
}


/**
 * WP-CLIコマンド（オプション）
 */
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('aidunite migrate-meta-keys', function($args, $assoc_args) {
        WP_CLI::line('メタキー統一移行を開始します...');

        $results = aidunite_migrate_meta_keys();

        WP_CLI::success("移行完了:");
        WP_CLI::line("  処理対象スケジュール数: {$results['total_schedules']}件");
        WP_CLI::line("  性別条件: 移行 {$results['gender_migrated']}件, スキップ {$results['gender_skipped']}件, エラー {$results['gender_errors']}件");
        WP_CLI::line("  会場条件: 移行 {$results['place_migrated']}件, スキップ {$results['place_skipped']}件, エラー {$results['place_errors']}件");
        WP_CLI::line("  男子枠: 移行 " . ($results['male_slots_migrated'] ?? 0) . "件");
        WP_CLI::line("  女子枠: 移行 " . ($results['female_slots_migrated'] ?? 0) . "件");
        WP_CLI::line("  メモ: 移行 " . ($results['memo_migrated'] ?? 0) . "件");
    });

    WP_CLI::add_command('aidunite cleanup-meta-keys', function($args, $assoc_args) {
        WP_CLI::line('旧メタキーのクリーンアップを開始します...');

        $results = aidunite_cleanup_old_meta_keys();

        WP_CLI::success("クリーンアップ完了:");
        WP_CLI::line("  処理対象スケジュール数: {$results['total_schedules']}件");
        WP_CLI::line("  性別条件: 削除 {$results['gender_deleted']}件, エラー {$results['gender_errors']}件");
        WP_CLI::line("  会場条件: 削除 {$results['place_deleted']}件, エラー {$results['place_errors']}件");
        WP_CLI::line("  male_teams: 削除 " . ($results['male_teams_deleted'] ?? 0) . "件");
        WP_CLI::line("  female_teams: 削除 " . ($results['female_teams_deleted'] ?? 0) . "件");
        WP_CLI::line("  schedule_note: 削除 " . ($results['memo_deleted'] ?? 0) . "件");
    });
}
