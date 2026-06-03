<?php
require_once get_stylesheet_directory() . '/functions/team/team-functions.php';

/**
 * ユーザーロール定義
 */
function aidunite_get_user_roles() {
    return [
        'administrator' => '管理者 ⚙️',
        'team_leader'   => 'チーム代表者 🏠',
        'parent'        => '保護者 👨‍👩‍👧‍👦',
        'player'        => '選手 🏃‍♂️',
        'supporter'     => '支援者 💝',
        'general'       => '一般ユーザー 🧑',
        'public'        => '一般ユーザー 🌐',
        'match'         => 'マッチ担当 🤝'
    ];
}
/**
 * ユーザーIDから必要なユーザー情報を配列で返す（統一命名）
 */
function aidunite_get_user_info($user_id) {
    if (!$user_id) return [];

    $user = get_userdata($user_id);
    if (!$user) return [];

    // 必要なユーザーメタを取得
    $user_type = get_user_meta($user_id, 'aidunite_role', true);
    $team_id = function_exists('aidunite_get_current_team_id')
        ? (int) aidunite_get_current_team_id((int) $user_id)
        : (int) get_user_meta($user_id, 'team_id', true);

    return [
        'user_id'   => $user_id,
        'user_type' => $user_type ?: 'general',
        'team_id'   => $team_id ?: null,
        'user_name' => $user->display_name,
        'user_email'=> $user->user_email,
    ];
}

/**
 * 後方互換性のためのラッパー関数（段階的削除予定）
 * @deprecated 代わりに aidunite_get_user_info() を使用してください
 */
function tunageru_get_user_info($user_id) {
    return aidunite_get_user_info($user_id);
}

/**
 * ユーザータイプ取得（統一命名）
 */
function aidunite_get_user_type($user_id = null) {
    if (!$user_id) $user_id = get_current_user_id();
    $info = aidunite_get_user_info($user_id);
    return $info['user_type'] ?? 'general';
}

/**
 * 後方互換性のためのラッパー関数（段階的削除予定）
 * @deprecated 代わりに aidunite_get_user_type() を使用してください
 */
function tunageru_get_user_type($user_id = null) {
    return aidunite_get_user_type($user_id);
}
/**
 * チームホームテンプレート
 * AidUnite統一仕様対応版
 * ロール別表示切り替え機能付き
 */

/**
 * チーム代表者向けコンテンツ
 */
function aidunite_render_team_leader_content($team_info, $user_info) {
    ?>
    <div class="row">
        <!-- スケジュール管理 -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">📅 スケジュール管理</h5>
                </div>
                <div class="card-body">
                    <p>チームの練習・試合スケジュールを管理できます。</p>
                    <a href="<?php echo home_url('/schedule-management'); ?>" class="btn btn-primary">スケジュール管理</a>
                </div>
            </div>
        </div>

        <!-- 選手一覧 -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">👥 選手一覧</h5>
                </div>
                <div class="card-body">
                    <?php
                    $players = aidunite_get_team_members($team_info['team_id'], 'player');
                    $player_count = count($players);
                    ?>
                    <p>登録選手数: <?php echo $player_count; ?>名</p>
                    <a href="<?php echo home_url('/player-add'); ?>" class="btn btn-success">選手追加</a>
                    <a href="<?php echo home_url('/team-members'); ?>" class="btn btn-info">一覧表示</a>
                </div>
            </div>
        </div>

        <!-- 保護者一覧 -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">👨‍👩‍👧‍👦 保護者一覧</h5>
                </div>
                <div class="card-body">
                    <?php
                    $parents = aidunite_get_team_members($team_info['team_id'], 'parent');
                    $parent_count = count($parents);
                    ?>
                    <p>登録保護者数: <?php echo $parent_count; ?>名</p>
                    <a href="<?php echo home_url('/team-members'); ?>" class="btn btn-info">一覧表示</a>
                </div>
            </div>
        </div>

        <!-- 試合管理 -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">⚽ 試合管理</h5>
                </div>
                <div class="card-body">
                    <p>試合の申し込みや管理ができます。</p>
                    <a href="<?php echo home_url('/match-management'); ?>" class="btn btn-primary">試合管理</a>
                </div>
            </div>
        </div>

        <!-- 出欠確認 -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">✅ 出欠確認</h5>
                </div>
                <div class="card-body">
                    <p>選手の出欠状況を確認できます。</p>
                    <a href="<?php echo home_url('/attendance-management'); ?>" class="btn btn-success">出欠確認</a>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * 保護者向けコンテンツ
 */
function aidunite_render_parent_content($team_info, $user_info) {
    $parent_dashboard = aidunite_get_parent_dashboard_data($user_info['user_id']);
    ?>
    <div class="row">
        <!-- 子どもの予定 -->
        <div class="col-md-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">📅 子どもの予定</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($parent_dashboard['upcoming_schedules'])) : ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>日付</th>
                                        <th>時間</th>
                                        <th>種別</th>
                                        <th>場所</th>
                                        <th>出欠</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($parent_dashboard['upcoming_schedules'], 0, 10) as $schedule) : ?>
                                        <tr>
                                            <td><?php echo esc_html($schedule['schedule_date']); ?></td>
                                            <td><?php echo esc_html($schedule['schedule_start_time']); ?> - <?php echo esc_html($schedule['schedule_end_time']); ?></td>
                                            <td><?php echo esc_html($schedule['schedule_type']); ?></td>
                                            <td><?php echo esc_html($schedule['schedule_place']); ?></td>
                                            <td>
                                                <a href="<?php echo home_url('/attendance-response?schedule_id=' . $schedule['schedule_id'] . '&user_id=' . $user_info['user_id']); ?>" class="btn btn-sm btn-primary">回答</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else : ?>
                        <p class="text-muted">今月の予定はありません。</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 出欠連絡 -->
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">✅ 出欠連絡</h5>
                </div>
                <div class="card-body">
                    <p>子どもの出欠を連絡できます。</p>
                    <a href="<?php echo home_url('/attendance'); ?>" class="btn btn-success">出欠連絡</a>
                </div>
            </div>
        </div>

        <!-- チームからの連絡 -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">📢 チームからの連絡</h5>
                </div>
                <div class="card-body">
                    <p>チームからのお知らせを確認できます。</p>
                    <a href="<?php echo home_url('/team-notifications'); ?>" class="btn btn-info">連絡確認</a>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * 選手向けコンテンツ
 */
function aidunite_render_player_content($team_info, $user_info) {
    ?>
    <div class="row">
        <!-- マイスケジュール -->
        <div class="col-md-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">📅 マイスケジュール</h5>
                </div>
                <div class="card-body">
                    <?php
                    $current_month = date('Y-m');
                    $date_range = [
                        'start' => $current_month . '-01',
                        'end' => $current_month . '-31'
                    ];
                    $my_schedules = aidunite_get_child_schedules($user_info['user_id'], $date_range);
                    ?>
                    <?php if (!empty($my_schedules)) : ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>日付</th>
                                        <th>時間</th>
                                        <th>種別</th>
                                        <th>場所</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($my_schedules, 0, 10) as $schedule) : ?>
                                        <tr>
                                            <td><?php echo esc_html($schedule['schedule_date']); ?></td>
                                            <td><?php echo esc_html($schedule['schedule_start_time']); ?> - <?php echo esc_html($schedule['schedule_end_time']); ?></td>
                                            <td><?php echo esc_html($schedule['schedule_type']); ?></td>
                                            <td><?php echo esc_html($schedule['schedule_place']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else : ?>
                        <p class="text-muted">今月のスケジュールはありません。</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 試合履歴 -->
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">🏆 試合履歴</h5>
                </div>
                <div class="card-body">
                    <p>自分の試合履歴を確認できます。</p>
                    <a href="<?php echo home_url('/match-history'); ?>" class="btn btn-primary">試合履歴</a>
                </div>
            </div>
        </div>

        <!-- チーム情報 -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">🏠 チーム情報</h5>
                </div>
                <div class="card-body">
                    <p><strong>チーム名:</strong> <?php echo esc_html($team_info['team_name']); ?></p>
                    <p><strong>競技:</strong> <?php echo esc_html($team_info['sport_type']); ?></p>
                    <p><strong>地域:</strong> <?php echo esc_html($team_info['region']); ?></p>
                    <a href="<?php echo home_url('/team-detail?id=' . $team_info['team_id']); ?>" class="btn btn-info">詳細を見る</a>
                </div>
            </div>
        </div>

        <!-- 掲示板閲覧 -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">📢 掲示板</h5>
                </div>
                <div class="card-body">
                    <p>チームの掲示板を閲覧できます。</p>
                    <a href="<?php echo home_url('/team-board'); ?>" class="btn btn-secondary">掲示板</a>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * 支援者向けコンテンツ
 */
function aidunite_render_supporter_content($team_info, $user_info) {
    ?>
    <div class="row">
        <!-- 支援中チーム -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">🏠 支援中チーム</h5>
                </div>
                <div class="card-body">
                    <p><strong>チーム名:</strong> <?php echo esc_html($team_info['team_name']); ?></p>
                    <p><strong>競技:</strong> <?php echo esc_html($team_info['sport_type']); ?></p>
                    <p><strong>地域:</strong> <?php echo esc_html($team_info['region']); ?></p>
                    <a href="<?php echo home_url('/team-detail?id=' . $team_info['team_id']); ?>" class="btn btn-info">詳細を見る</a>
                </div>
            </div>
        </div>

        <!-- チーム紹介 -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">📖 チーム紹介</h5>
                </div>
                <div class="card-body">
                    <p><?php echo esc_html($team_info['team_description'] ?: 'チームの紹介文がありません。'); ?></p>
                    <a href="<?php echo home_url('/team-detail?id=' . $team_info['team_id']); ?>" class="btn btn-primary">詳細を見る</a>
                </div>
            </div>
        </div>

        <!-- メッセージ投稿 -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">💬 メッセージ投稿</h5>
                </div>
                <div class="card-body">
                    <p>チームに応援メッセージを送れます。</p>
                    <a href="<?php echo home_url('/send-message?team_id=' . $team_info['team_id']); ?>" class="btn btn-success">メッセージ送信</a>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * 管理者向けコンテンツ
 */
function aidunite_render_admin_content($team_info, $user_info) {
    ?>
    <div class="row">
        <!-- 全チーム状況確認 -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">⚙️ 全チーム状況確認</h5>
                </div>
                <div class="card-body">
                    <p>全チームの状況を確認できます。</p>
                    <a href="<?php echo admin_url('edit.php?post_type=team'); ?>" class="btn btn-primary">チーム一覧</a>
                    <a href="<?php echo admin_url('users.php'); ?>" class="btn btn-info">ユーザー一覧</a>
                </div>
            </div>
        </div>

        <!-- 管理操作リンク集 -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">🔧 管理操作</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="<?php echo home_url('/team-approval'); ?>" class="btn btn-warning">チーム承認</a>
                        <a href="<?php echo home_url('/match-management'); ?>" class="btn btn-success">マッチ管理</a>
                        <a href="<?php echo admin_url(); ?>" class="btn btn-secondary">管理画面</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- このチームの詳細情報 -->
        <div class="col-md-12 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">📊 このチームの詳細情報</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>基本情報</h6>
                            <p><strong>チームID:</strong> <?php echo esc_html($team_info['team_id']); ?></p>
                            <p><strong>代表者ID:</strong> <?php echo esc_html($team_info['leader_id']); ?></p>
                            <p><strong>登録日:</strong> <?php echo esc_html($team_info['created_date']); ?></p>
                            <p><strong>ステータス:</strong> <?php echo esc_html($team_info['post_status']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <h6>メンバー統計</h6>
                            <?php
                            $players = aidunite_get_team_members($team_info['team_id'], 'player');
                            $parents = aidunite_get_team_members($team_info['team_id'], 'parent');
                            $supporters = aidunite_get_team_members($team_info['team_id'], 'supporter');
                            ?>
                            <p><strong>選手数:</strong> <?php echo count($players); ?>名</p>
                            <p><strong>保護者数:</strong> <?php echo count($parents); ?>名</p>
                            <p><strong>支援者数:</strong> <?php echo count($supporters); ?>名</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * 一般ユーザー向けコンテンツ
 */
function aidunite_render_general_content($team_info, $user_info) {
    ?>
    <div class="row">
        <div class="col-md-12 mb-4">
            <div class="alert alert-info">
                <h5>👋 ようこそ！</h5>
                <p>このチームの情報を閲覧できます。チームに参加するには、チーム代表者にお問い合わせください。</p>
            </div>
        </div>

        <!-- チーム情報 -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">🏠 チーム情報</h5>
                </div>
                <div class="card-body">
                    <p><strong>チーム名:</strong> <?php echo esc_html($team_info['team_name']); ?></p>
                    <p><strong>競技:</strong> <?php echo esc_html($team_info['sport_type']); ?></p>
                    <p><strong>カテゴリ:</strong> <?php echo esc_html($team_info['team_category']); ?></p>
                    <p><strong>地域:</strong> <?php echo esc_html($team_info['region']); ?></p>
                    <p><strong>説明:</strong> <?php echo esc_html($team_info['team_description'] ?: '説明がありません。'); ?></p>
                </div>
            </div>
        </div>

        <!-- チーム実績 -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">🏆 チーム実績</h5>
                </div>
                <div class="card-body">
                    <p><?php echo esc_html($team_info['team_achievements'] ?: '実績情報がありません。'); ?></p>
                </div>
            </div>
        </div>

        <!-- 参加・支援 -->
        <div class="col-md-12 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">🤝 参加・支援</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>チームに参加</h6>
                            <p>選手や保護者としてチームに参加できます。</p>
                            <a href="<?php echo home_url('/team-apply?team_id=' . $team_info['team_id']); ?>" class="btn btn-primary">参加申請</a>
                        </div>
                        <div class="col-md-6">
                            <h6>チームを支援</h6>
                            <p>支援者としてチームを応援できます。</p>
                            <a href="<?php echo home_url('/support-team?team_id=' . $team_info['team_id']); ?>" class="btn btn-success">支援する</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
}

/**
 * お知らせの未読件数を取得（バッジ表示用）
 * 仕様: notification-spec.md — CPT の未読件数で表示
 *
 * 同一リクエスト内で header / footer / サイド等から複数回呼ばれるため static でキャッシュする。
 */
function aidunite_get_notification_count($user_id = null) {
    static $memo = [];

    if ($user_id === null) {
        $user_id = get_current_user_id();
    }
    $uid = (int) $user_id;
    if ($uid <= 0) {
        return 0;
    }
    if (array_key_exists($uid, $memo)) {
        return $memo[$uid];
    }
    if (function_exists('aidunite_notification_unread_count')) {
        $memo[$uid] = aidunite_notification_unread_count($uid);
    } else {
        $memo[$uid] = 0;
    }
    return $memo[$uid];
}

/**
 * マイページ用メニュー生成（統一命名）
 */
function aidunite_get_mypage_menu_v2($user_type) {
    // 強力なキャッシュクリア
    wp_cache_flush();
    wp_cache_delete('mypage_menu_' . $user_type, 'tunageru');
    delete_transient('mypage_menu_' . $user_type);

    // オブジェクトキャッシュもクリア
    if (function_exists('wp_cache_delete_group')) {
        wp_cache_delete_group('tunageru');
    }

    $menu = [];

    // 共通メニュー（全ロールでマイページ下部メニューに表示）
    $menu[] = [
        'title' => 'プロフィール編集',
        'url'   => home_url('/profile-edit'),
        'icon'  => '👤',
    ];
    $menu[] = [
        'title' => 'お知らせ',
        'url'   => home_url('/notifications'),
        'icon'  => '📬',
    ];
    $menu[] = [
        'title' => '通知設定',
        'url'   => home_url('/notification-settings'),
        'icon'  => '🔔',
    ];

    // ロール別メニュー
    switch ($user_type) {
        case 'team_leader':
            // チーム代表者：チーム運営に必要な全ての機能
            $menu[] = [
                'title' => 'コミュニケーションルーム',
                'url'   => home_url('/communication'),
                'icon'  => '💬',
                'description' => 'チーム内での連絡・コミュニケーション'
            ];
            $menu[] = [
                'title' => 'スケジュール登録',
                'url'   => home_url('/schedule-management'),
                'icon'  => '📅',
                'description' => '練習や試合のスケジュールを管理'
            ];
            $menu[] = [
                'title' => '試合一覧',
                'url'   => home_url('/match-board'),
                'icon'  => '🏟️',
                'description' => '試合の申し込み・管理'
            ];
            $menu[] = [
                'title' => 'お気に入りチーム',
                'url'   => home_url('/mypage-favorite-teams'),
                'icon'  => '⭐',
                'description' => 'お気に入りに登録したチーム一覧'
            ];
            $menu[] = [
                'title' => 'マッチ統計',
                'url'   => home_url('/match-analytics'),
                'icon'  => '📊',
                'description' => 'マッチ成立率の分析・統計'
            ];
            $menu[] = [
                'title' => '出欠一覧',
                'url'   => home_url('/attendance-management'),
                'icon'  => '✅',
                'description' => '練習・試合の出欠確認'
            ];
            $menu[] = [
                'title' => 'メンバー一覧',
                'url'   => home_url('/team-members'),
                'icon'  => '👥',
                'description' => 'チームメンバーの一覧・編集・管理',
                'slug' => 'member_list' // 並び替え用のスラッグ
            ];
            $menu[] = [
                'title' => '保護者招待・追加',
                'url'   => home_url('/invite-guardian'),
                'icon'  => '📩',
                'icon_svg' => 'guardian-invite',
                'description' => '保護者をメールで招待し、チームに参加してもらう',
                'slug' => 'invite_guardian'
            ];
            $menu[] = [
                'title' => 'チーム管理',
                'url'   => aidunite_get_team_settings_page_url(),
                'icon'  => '⚙️',
                'icon_svg' => 'settings',
                'description' => '操作中チームの基本情報・各種管理メニュー',
                'slug' => 'team_settings' // 並び替え用のスラッグ
            ];
            break;

        case 'parent':
            // 保護者：子供の活動管理とチーム情報確認
            $menu[] = [
                'title' => 'コミュニケーションルーム',
                'url'   => home_url('/communication'),
                'icon'  => '💬',
                'description' => 'チームからの連絡事項確認'
            ];
            $menu[] = [
                'title' => '子供の活動管理',
                'url'   => home_url('/player-add'),
                'icon'  => '👨‍👩‍👧‍👦',
                'description' => '子供の登録・管理'
            ];
            $menu[] = [
                'title' => 'スケジュール確認',
                'url'   => home_url('/schedule-list'),
                'icon'  => '📅',
                'description' => '練習・試合スケジュールの確認'
            ];
            $menu[] = [
                'title' => '出欠連絡',
                'url'   => home_url('/attendance-report'),
                'icon'  => '✅',
                'description' => '練習・試合の出欠連絡'
            ];
            break;

        case 'player':
            // 選手：自分の活動管理とチーム情報確認
            $menu[] = [
                'title' => 'コミュニケーションルーム',
                'url'   => home_url('/communication'),
                'icon'  => '💬',
                'description' => 'チームからの連絡事項確認'
            ];
            $menu[] = [
                'title' => 'スケジュール確認',
                'url'   => home_url('/schedule-list'),
                'icon'  => '📅',
                'description' => '練習・試合スケジュールの確認'
            ];
            $menu[] = [
                'title' => '出欠連絡',
                'url'   => home_url('/attendance-report'),
                'icon'  => '✅',
                'description' => '練習・試合の出欠連絡'
            ];
            $menu[] = [
                'title' => '個人記録',
                'url'   => home_url('/player-stats'),
                'icon'  => '📈',
                'description' => '個人の活動記録・統計'
            ];
            break;

        case 'supporter':
            // 支援者ロール（支援者関連ページは削除済み）
            break;

        case 'administrator':
            // 管理者：ダッシュボード・システム全体の管理
            $menu[] = [
                'title' => 'Ainyダッシュボード',
                'url'   => home_url('/ainy-dashboard'),
                'icon'  => '📊',
                'description' => 'KPI・やることラン・管理ショートカット'
            ];
            $menu[] = [
                'title' => 'システム管理',
                'url'   => home_url('/system-management'),
                'icon'  => '⚙️',
                'description' => 'システム全体の設定・管理'
            ];
            $menu[] = [
                'title' => 'WordPress管理画面',
                'url'   => admin_url(),
                'icon'  => '🔧',
                'description' => 'WordPressの管理画面'
            ];
            $menu[] = [
                'title' => 'ユーザー管理',
                'url'   => home_url('/user-management'),
                'icon'  => '👥',
                'description' => '全ユーザーの管理'
            ];
            $menu[] = [
                'title' => 'チーム管理',
                'url'   => home_url('/team-management'),
                'icon'  => '🏀',
                'description' => '全チームの管理'
            ];
            $menu[] = [
                'title' => 'システムログ',
                'url'   => home_url('/system-logs'),
                'icon'  => '📋',
                'description' => 'システムログの確認'
            ];
            break;

        default:
            // 一般ユーザー：チーム参加・作成
            $menu[] = [
                'title' => 'チーム作成申請',
                'url'   => home_url('/team-registration'),
                'icon'  => '🏀',
                'description' => '新しいチームの作成申請'
            ];
            $menu[] = [
                'title' => '利用ガイド',
                'url'   => home_url('/guide'),
                'icon'  => '📖',
                'description' => 'サービスの利用方法'
            ];
            $menu[] = [
                'title' => 'よくある質問',
                'url'   => home_url('/faq'),
                'icon'  => '❓',
                'description' => 'よくある質問と回答'
            ];
            break;
    }

    // 管理メニューの並び順を統一
    if (!function_exists('au_sort_mypage_menu')) {
        require_once get_template_directory() . '/functions/menu-order.php';
    }
    $menu = au_sort_mypage_menu($menu);

    return apply_filters('aidunite_mypage_menu_v2_items', $menu, $user_type);
}

/**
 * 後方互換性のためのラッパー関数（段階的削除予定）
 * @deprecated 代わりに aidunite_get_mypage_menu_v2() を使用してください
 */
function tunageru_get_mypage_menu_v2($user_type) {
    return aidunite_get_mypage_menu_v2($user_type);
}

/**
 * ユーザータイプに応じたアイコンを返す（統一命名）
 */
function aidunite_get_user_type_icon($user_type) {
    $icons = [
        'team_leader'   => '🏠',
        'parent'        => '👨‍👩‍👧‍👦',
        'player'        => '🏃‍♂️',
        'supporter'     => '💝',
        'administrator' => '⚙️',
        'general'       => '🧑',
    ];
    return $icons[$user_type] ?? '🧑';
}

/**
 * 後方互換性のためのラッパー関数（段階的削除予定）
 * @deprecated 代わりに aidunite_get_user_type_icon() を使用してください
 */
function tunageru_get_user_type_icon($user_type) {
    return aidunite_get_user_type_icon($user_type);
}

/**
 * ユーザータイプに応じた日本語ラベルを返す（統一命名）
 */
function aidunite_get_user_type_label($user_type) {
    $labels = [
        'team_leader'   => 'チーム代表者',
        'parent'        => '保護者',
        'player'        => '選手',
        'supporter'     => '支援者',
        'administrator' => '管理者',
        'general'       => '一般ユーザー',
    ];
    return $labels[$user_type] ?? '一般ユーザー';
}

/**
 * 後方互換性のためのラッパー関数（段階的削除予定）
 * @deprecated 代わりに aidunite_get_user_type_label() を使用してください
 */
function tunageru_get_user_type_label($user_type) {
    return aidunite_get_user_type_label($user_type);
}

/**
 * ユーザータイプごとのダッシュボードデータ取得（統一命名）
 */
function aidunite_get_dashboard_data($user_type) {
    $current_user_id = get_current_user_id();
    if (!$current_user_id) {
        return [];
    }

    $user_info = aidunite_get_user_info($current_user_id);
    if (empty($user_info)) {
        return [];
    }

    try {
        switch ($user_type) {
        case 'team_leader':
            $team_id = $user_info['team_id'] ?? 0;
            $team_info = null;
            $team_name = '未設定';

            if ($team_id) {
                $team_info = aidunite_get_team_info($team_id);
                if ($team_info && isset($team_info['team_name'])) {
                    $team_name = $team_info['team_name'];
                } else {
                    $team_title = get_the_title($team_id);
                    if ($team_title && $team_title !== '') {
                        $team_name = $team_title;
                    }
                }
            }

            return [
                'team_info' => [
                    'name' => $team_name,
                    'member_count' => aidunite_get_team_member_count($team_id),
                    'next_event' => aidunite_get_next_team_event($team_id),
                    'pending_requests' => aidunite_get_pending_requests_count($team_id),
                    'team_id' => $team_id
                ],
                'quick_stats' => [
                    'total_matches' => aidunite_get_total_matches($user_info['team_id'] ?? 0),
                    'upcoming_events' => aidunite_get_upcoming_events_count($user_info['team_id'] ?? 0),
                    'unpaid_fees' => aidunite_get_unpaid_fees_count($user_info['team_id'] ?? 0),
                    'pending_attendance' => aidunite_get_pending_attendance_count_team($user_info['team_id'] ?? 0)
                ],
                'calendar_events' => aidunite_get_team_calendar_events($user_info['team_id'] ?? 0),
                'recent_activities' => aidunite_get_team_recent_activities($user_info['team_id'] ?? 0),
                'match_status' => aidunite_get_team_match_status($user_info['team_id'] ?? 0)
            ];

        case 'parent':
            return [
                'children' => aidunite_get_parent_children_list($current_user_id),
                'upcoming_schedules' => aidunite_get_parent_upcoming_schedules($current_user_id),
                'recent_attendance' => aidunite_get_parent_recent_attendance($current_user_id),
                'team_info' => aidunite_get_parent_team_info($current_user_id)
            ];

        case 'player':
            return [
                'player_info' => aidunite_get_player_info($current_user_id),
                'team_info' => aidunite_get_player_team_info($current_user_id),
                'upcoming_schedules' => aidunite_get_player_upcoming_schedules($current_user_id),
                'recent_attendance' => aidunite_get_player_recent_attendance($current_user_id)
            ];

        case 'administrator':
            return [
                'system_info' => [
                    'total_users' => aidunite_get_total_users_count(),
                    'total_teams' => aidunite_get_total_teams_count(),
                    'active_sessions' => aidunite_get_active_sessions_count(),
                    'system_status' => aidunite_get_system_status()
                ],
                'quick_stats' => [
                    'new_registrations' => aidunite_get_new_registrations_count(),
                    'pending_approvals' => aidunite_get_pending_approvals_count(),
                    'system_alerts' => aidunite_get_system_alerts_count()
                ]
            ];

        default:
            return [
                'general_info' => [
                    'registration_date' => aidunite_get_user_registration_date($current_user_id)
                ]
                // 利用可能チーム、近隣チーム、人気スポーツ、おすすめチーム、利用ガイドは非表示
            ];
        }
    } catch (Exception $e) {
        error_log('aidunite_get_dashboard_data エラー: ' . $e->getMessage());
        return [];
    }
}
/**
 * ダッシュボードデータをHTMLで描画（統一命名）
 */
function aidunite_render_dashboard_content($user_type, $dashboard_data) {
    $html = '';

    switch ($user_type) {
        case 'team_leader':
            if (isset($dashboard_data['team_info'])) {
                $team_info = $dashboard_data['team_info'];
                $quick_stats = $dashboard_data['quick_stats'] ?? [];
                $calendar_events = $dashboard_data['calendar_events'] ?? [];
                $recent_activities = $dashboard_data['recent_activities'] ?? [];
                $match_status = $dashboard_data['match_status'] ?? [];

                // チーム情報サマリー
                $html .= '<div class="dashboard-stats-grid">';
                $html .= '<div class="stat-card">';
                $html .= '<div class="stat-icon">🏀</div>';
                $html .= '<div class="stat-content">';
                $html .= '<h4>チーム名</h4>';
                $html .= '<p>' . esc_html($team_info['name']) . '</p>';
                $html .= '</div>';
                $html .= '</div>';

                $html .= '<div class="stat-card">';
                $html .= '<div class="stat-icon">👥</div>';
                $html .= '<div class="stat-content">';
                $html .= '<h4>メンバー数</h4>';
                $html .= '<p>' . esc_html($team_info['member_count'] ?? 0) . '名</p>';
                $html .= '</div>';
                $html .= '</div>';

                $html .= '<div class="stat-card">';
                $html .= '<div class="stat-icon">📅</div>';
                $html .= '<div class="stat-content">';
                $html .= '<h4>次のイベント</h4>';
                $html .= '<p>' . esc_html($team_info['next_event'] ?? '予定なし') . '</p>';
                $html .= '</div>';
                $html .= '</div>';

                $html .= '<div class="stat-card">';
                $html .= '<div class="stat-icon">⚽</div>';
                $html .= '<div class="stat-content">';
                $html .= '<h4>総試合数</h4>';
                $html .= '<p>' . esc_html($quick_stats['total_matches'] ?? 0) . '試合</p>';
                $html .= '</div>';
                $html .= '</div>';

                $html .= '<div class="stat-card">';
                $html .= '<div class="stat-icon">📋</div>';
                $html .= '<div class="stat-content">';
                $html .= '<h4>今後のイベント</h4>';
                $html .= '<p>' . esc_html($quick_stats['upcoming_events'] ?? 0) . '件</p>';
                $html .= '</div>';
                $html .= '</div>';

                $html .= '<div class="stat-card">';
                $html .= '<div class="stat-icon">⏰</div>';
                $html .= '<div class="stat-content">';
                $html .= '<h4>出欠確認待ち</h4>';
                $html .= '<p>' . esc_html($quick_stats['pending_attendance'] ?? 0) . '件</p>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';

                // マッチ状況サマリー
                if (!empty($match_status)) {
                    $html .= '<div class="match-status-section">';
                    $html .= '<h3>🤝 マッチ状況</h3>';
                    $html .= '<div class="match-status-grid">';

                    $html .= '<div class="match-status-card">';
                    $html .= '<div class="match-status-icon">⏳</div>';
                    $html .= '<div class="match-status-content">';
                    $html .= '<h4>保留中の申請</h4>';
                    $html .= '<p>' . esc_html($match_status['pending_requests'] ?? 0) . '件</p>';
                    $html .= '</div>';
                    $html .= '</div>';

                    $html .= '<div class="match-status-card">';
                    $html .= '<div class="match-status-icon">✅</div>';
                    $html .= '<div class="match-status-content">';
                    $html .= '<h4>承認された試合</h4>';
                    $html .= '<p>' . esc_html($match_status['accepted_matches'] ?? 0) . '試合</p>';
                    $html .= '</div>';
                    $html .= '</div>';

                    $html .= '<div class="match-status-card">';
                    $html .= '<div class="match-status-icon">📅</div>';
                    $html .= '<div class="match-status-content">';
                    $html .= '<h4>今後の試合</h4>';
                    $html .= '<p>' . esc_html($match_status['upcoming_matches'] ?? 0) . '試合</p>';
                    $html .= '</div>';
                    $html .= '</div>';

                    $html .= '<div class="match-status-card">';
                    $html .= '<div class="match-status-icon">📊</div>';
                    $html .= '<div class="match-status-content">';
                    $html .= '<h4>最近の試合</h4>';
                    $html .= '<p>' . esc_html($match_status['recent_matches'] ?? 0) . '試合</p>';
                    $html .= '</div>';
                    $html .= '</div>';

                    $html .= '</div>';
                    $html .= '<div class="text-center mt-3">';
                    $html .= '<a href="' . esc_url(home_url('/match-management')) . '" class="btn btn-primary">マッチ管理</a>';
                    $html .= '<a href="' . esc_url(home_url('/my-matches')) . '" class="btn btn-secondary ml-2">マイマッチ</a>';
                    $html .= '</div>';
                    $html .= '</div>';
                }

                // 今月のスケジュール
                if (!empty($calendar_events)) {
                    $html .= '<div class="dashboard-calendar-section">';
                    $html .= '<h3>📅 今月のスケジュール</h3>';
                    $html .= '<div class="calendar-events">';

                    foreach ($calendar_events as $event) {
                        $date_obj = new DateTime($event['date']);
                        $formatted_date = $date_obj->format('m/d');
                        $time_info = '';

                        if (!empty($event['start_time']) && !empty($event['end_time'])) {
                            $time_info = $event['start_time'] . ' - ' . $event['end_time'];
                        } elseif (!empty($event['start_time'])) {
                            $time_info = $event['start_time'] . '開始';
                        }

                        $html .= '<div class="calendar-event">';
                        $html .= '<div class="event-date">' . $formatted_date . '</div>';
                        $html .= '<div class="event-info">';
                        $html .= '<h4>' . esc_html($event['title']) . '</h4>';
                        $html .= '<p>' . esc_html($event['type']) . ($time_info ? ' - ' . $time_info : '') . '</p>';
                        if (!empty($event['description'])) {
                            $html .= '<p class="event-description">' . esc_html($event['description']) . '</p>';
                        }
                        $html .= '</div>';
                        $html .= '</div>';
                    }

                    $html .= '</div>';
                    $html .= '<div class="text-center mt-3">';
                    $html .= '<a href="' . esc_url(home_url('/schedule-management')) . '" class="btn btn-primary">スケジュール管理</a>';
                    $html .= '</div>';
                    $html .= '</div>';
                } else {
                    $html .= '<div class="dashboard-calendar-section">';
                    $html .= '<h3>📅 今月のスケジュール</h3>';
                    $html .= '<div class="empty-state">';
                    $html .= '<p>今月のスケジュールはありません。</p>';
                    $html .= '<a href="' . esc_url(home_url('/schedule-management')) . '" class="btn btn-primary">スケジュールを作成</a>';
                    $html .= '</div>';
                    $html .= '</div>';
                }

                // 最近の活動
                if (!empty($recent_activities)) {
                    $html .= '<div class="recent-activities-section">';
                    $html .= '<h3>📊 最近の活動</h3>';
                    $html .= '<div class="activities-list">';

                    foreach ($recent_activities as $activity) {
                        $date_obj = new DateTime($activity['date']);
                        $time_ago = human_time_diff(strtotime($activity['date']), current_time('timestamp'));

                        $html .= '<div class="activity-item">';
                        $html .= '<div class="activity-icon">';
                        if ($activity['type'] === 'schedule') {
                            $html .= '📅';
                        } elseif ($activity['type'] === 'match') {
                            $html .= '⚽';
                        } elseif ($activity['type'] === 'match_request') {
                            $html .= '🤝';
                        } else {
                            $html .= '📝';
                        }
                        $html .= '</div>';
                        $html .= '<div class="activity-content">';
                        $html .= '<h4>' . esc_html($activity['title']) . '</h4>';
                        $html .= '<p>' . esc_html($activity['description']) . '</p>';
                        $html .= '<small class="activity-time">' . $time_ago . '前</small>';
                        $html .= '</div>';
                        $html .= '</div>';
                    }

                    $html .= '</div>';
                    $html .= '</div>';
                } else {
                    $html .= '<div class="recent-activities-section">';
                    $html .= '<h3>📊 最近の活動</h3>';
                    $html .= '<div class="empty-state">';
                    $html .= '<p>最近の活動はありません。</p>';
                    $html .= '</div>';
                    $html .= '</div>';
                }



            } else {
                $html .= '<div class="alert alert-info">';
                $html .= '<p>チーム情報が取得できませんでした。</p>';
                $html .= '</div>';
            }
            break;

        case 'parent':
            if (isset($dashboard_data['children'])) {
                $children = $dashboard_data['children'];
                $upcoming_schedules = $dashboard_data['upcoming_schedules'] ?? [];
                $recent_attendance = $dashboard_data['recent_attendance'] ?? [];

                // 子供の情報
                $html .= '<div class="dashboard-stats-grid">';
                foreach ($children as $child) {
                    $html .= '<div class="stat-card">';
                    $html .= '<div class="stat-icon">👶</div>';
                    $html .= '<div class="stat-content">';
                    $html .= '<h4>' . esc_html($child['name']) . '</h4>';
                    $html .= '<p>年齢: ' . esc_html($child['age']) . '歳</p>';
                    $html .= '</div>';
                    $html .= '</div>';
                }
                $html .= '</div>';

                // 今月の予定
                if (!empty($upcoming_schedules)) {
                    $html .= '<div class="dashboard-calendar-section">';
                    $html .= '<h3>📅 今月の予定</h3>';
                    $html .= '<div class="calendar-events">';

                    foreach ($upcoming_schedules as $schedule) {
                        $date_obj = new DateTime($schedule['schedule_date']);
                        $formatted_date = $date_obj->format('m/d');

                        $html .= '<div class="calendar-event">';
                        $html .= '<div class="event-date">' . $formatted_date . '</div>';
                        $html .= '<div class="event-info">';
                        $html .= '<h4>' . esc_html($schedule['title']) . '</h4>';
                        $html .= '<p>' . esc_html($schedule['type']) . ' - ' . esc_html($schedule['start_time']) . '開始</p>';
                        $html .= '</div>';
                        $html .= '</div>';
                    }

                    $html .= '</div>';
                    $html .= '</div>';
                }

                // 最近の出欠回答
                if (!empty($recent_attendance)) {
                    $html .= '<div class="recent-activities-section">';
                    $html .= '<h3>📊 最近の出欠回答</h3>';
                    $html .= '<div class="activities-list">';

                    foreach ($recent_attendance as $attendance) {
                        $html .= '<div class="activity-item">';
                        $html .= '<div class="activity-icon">✅</div>';
                        $html .= '<div class="activity-content">';
                        $html .= '<h4>' . esc_html($attendance['schedule_title']) . '</h4>';
                        $html .= '<p>' . esc_html($attendance['child_name']) . ' - ' . esc_html($attendance['status']) . '</p>';
                        $html .= '</div>';
                        $html .= '</div>';
                    }

                    $html .= '</div>';
                    $html .= '</div>';
                }
            }
            break;

        case 'player':
            if (isset($dashboard_data['player_info'])) {
                $player_info = $dashboard_data['player_info'];
                $upcoming_schedules = $dashboard_data['upcoming_schedules'] ?? [];
                $recent_attendance = $dashboard_data['recent_attendance'] ?? [];

                // 選手情報
                $html .= '<div class="dashboard-stats-grid">';
                $html .= '<div class="stat-card">';
                $html .= '<div class="stat-icon">👤</div>';
                $html .= '<div class="stat-content">';
                $html .= '<h4>選手名</h4>';
                $html .= '<p>' . esc_html($player_info['name']) . '</p>';
                $html .= '</div>';
                $html .= '</div>';

                if (!empty($player_info['age'])) {
                    $html .= '<div class="stat-card">';
                    $html .= '<div class="stat-icon">🎂</div>';
                    $html .= '<div class="stat-content">';
                    $html .= '<h4>年齢</h4>';
                    $html .= '<p>' . esc_html($player_info['age']) . '歳</p>';
                    $html .= '</div>';
                    $html .= '</div>';
                }

                if (!empty($player_info['position'])) {
                    $html .= '<div class="stat-card">';
                    $html .= '<div class="stat-icon">⚽</div>';
                    $html .= '<div class="stat-content">';
                    $html .= '<h4>ポジション</h4>';
                    $html .= '<p>' . esc_html($player_info['position']) . '</p>';
                    $html .= '</div>';
                    $html .= '</div>';
                }
                $html .= '</div>';

                // 今月の予定
                if (!empty($upcoming_schedules)) {
                    $html .= '<div class="dashboard-calendar-section">';
                    $html .= '<h3>📅 今月の予定</h3>';
                    $html .= '<div class="calendar-events">';

                    foreach ($upcoming_schedules as $schedule) {
                        $date_obj = new DateTime($schedule['schedule_date']);
                        $formatted_date = $date_obj->format('m/d');

                        $html .= '<div class="calendar-event">';
                        $html .= '<div class="event-date">' . $formatted_date . '</div>';
                        $html .= '<div class="event-info">';
                        $html .= '<h4>' . esc_html($schedule['title']) . '</h4>';
                        $html .= '<p>' . esc_html($schedule['type']) . ' - ' . esc_html($schedule['start_time']) . '開始</p>';
                        $html .= '</div>';
                        $html .= '</div>';
                    }

                    $html .= '</div>';
                    $html .= '</div>';
                }
            }
            break;

        case 'administrator':
            if (isset($dashboard_data['system_info'])) {
                $system_info = $dashboard_data['system_info'];
                $quick_stats = $dashboard_data['quick_stats'] ?? [];

                $html .= '<div class="dashboard-stats-grid">';
                $html .= '<div class="stat-card">';
                $html .= '<div class="stat-icon">👥</div>';
                $html .= '<div class="stat-content">';
                $html .= '<h4>総ユーザー数</h4>';
                $html .= '<p>' . esc_html($system_info['total_users']) . '名</p>';
                $html .= '</div>';
                $html .= '</div>';

                $html .= '<div class="stat-card">';
                $html .= '<div class="stat-icon">🏀</div>';
                $html .= '<div class="stat-content">';
                $html .= '<h4>総チーム数</h4>';
                $html .= '<p>' . esc_html($system_info['total_teams']) . 'チーム</p>';
                $html .= '</div>';
                $html .= '</div>';

                $html .= '<div class="stat-card">';
                $html .= '<div class="stat-icon">🖥️</div>';
                $html .= '<div class="stat-content">';
                $html .= '<h4>システム状況</h4>';
                $html .= '<p>' . esc_html($system_info['system_status']) . '</p>';
                $html .= '</div>';
                $html .= '</div>';

                $html .= '<div class="stat-card">';
                $html .= '<div class="stat-icon">📊</div>';
                $html .= '<div class="stat-content">';
                $html .= '<h4>新規登録</h4>';
                $html .= '<p>' . esc_html($quick_stats['new_registrations'] ?? 0) . '件</p>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
            }
            break;

        default:
            if (isset($dashboard_data['general_info'])) {
                $general_info = $dashboard_data['general_info'];
                $quick_stats = $dashboard_data['quick_stats'] ?? [];
                $recommended_teams = $dashboard_data['recommended_teams'] ?? [];
                $usage_guide = $dashboard_data['usage_guide'] ?? [];

                $html .= '<div class="dashboard-stats-grid">';
                $html .= '<div class="stat-card">';
                $html .= '<div class="stat-icon">📅</div>';
                $html .= '<div class="stat-content">';
                $html .= '<h4>登録日</h4>';
                $html .= '<p>' . esc_html($general_info['registration_date']) . '</p>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';

                // 利用ガイドとおすすめチームは非表示
                // 利用可能チーム、近隣チーム、人気スポーツも非表示
            }
            break;
    }

    return $html;
}

/**
 * チームメンバー数を取得（affiliated ユーザー総数。管理者内訳と同じ基準）
 */
function aidunite_get_team_member_count($team_id) {
    if (!$team_id) {
        return 0;
    }
    if (function_exists('aidunite_get_team_affiliated_user_ids')) {
        return count(aidunite_get_team_affiliated_user_ids($team_id));
    }
    return 0;
}

/**
 * チームの次のイベントを取得（実際のデータから）（統一命名）
 */
function aidunite_get_next_team_event($team_id) {
    if (!$team_id) return '予定なし';

    $current_date = date('Y-m-d');

    // 今後のスケジュールを取得
    $upcoming_schedules = get_posts([
        'post_type' => 'schedule',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'meta_query' => [
            [
                'key' => 'team_id',
                'value' => $team_id,
                'compare' => '='
            ],
            [
                'key' => 'schedule_date',
                'value' => $current_date,
                'compare' => '>=',
                'type' => 'DATE'
            ]
        ],
        'orderby' => 'meta_value',
        'meta_key' => 'schedule_date',
        'order' => 'ASC'
    ]);

    if (!empty($upcoming_schedules)) {
        $schedule = $upcoming_schedules[0];
        $schedule_date = get_post_meta($schedule->ID, 'schedule_date', true);
        $schedule_type = get_post_meta($schedule->ID, 'schedule_type', true);
        $start_time = get_post_meta($schedule->ID, 'start_time', true);

        $date_obj = new DateTime($schedule_date);
        $formatted_date = $date_obj->format('m/d');
        $schedule_type = $schedule_type ?: '練習';

        if ($start_time) {
            return $schedule_type . ' - ' . $formatted_date . ' ' . $start_time;
        } else {
            return $schedule_type . ' - ' . $formatted_date;
        }
    }

    return '予定なし';
}

/**
 * 保留中のリクエスト数を取得（実際のデータから）（統一命名）
 */
function aidunite_get_pending_requests_count($team_id) {
    if (!$team_id) return 0;

    // チーム参加申請の数を取得
    $pending_requests = get_posts([
        'post_type' => 'team_application',
        'post_status' => 'pending',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'team_id',
                'value' => $team_id,
                'compare' => '='
            ]
        ]
    ]);

    return count($pending_requests);
}

/**
 * 総試合数を取得（実際のデータから）（統一命名）
 */
function aidunite_get_total_matches($team_id) {
    if (!$team_id) return 0;

    // チームの試合数を取得
    $matches = get_posts([
        'post_type' => 'multi_match',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'team_id',
                'value' => $team_id,
                'compare' => '='
            ]
        ]
    ]);

    return count($matches);
}

/**
 * 今後のイベント数を取得（実際のデータから）（統一命名）
 */
function aidunite_get_upcoming_events_count($team_id) {
    if (!$team_id) return 0;

    $current_date = date('Y-m-d');

    // 今後のスケジュール数を取得
    $upcoming_schedules = get_posts([
        'post_type' => 'schedule',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'team_id',
                'value' => $team_id,
                'compare' => '='
            ],
            [
                'key' => 'schedule_date',
                'value' => $current_date,
                'compare' => '>=',
                'type' => 'DATE'
            ]
        ]
    ]);

    return count($upcoming_schedules);
}

/**
 * 未払い料金数を取得（実際のデータから）（統一命名）
 */
function aidunite_get_unpaid_fees_count($team_id) {
    if (!$team_id) return 0;

    // 未払い料金の数を取得（実装は料金システムに依存）
    // 現在は仮の実装
    return 0;
}

/**
 * 出欠確認待ち数を取得（実際のデータから）（統一命名）
 */
function aidunite_get_pending_attendance_count_team($team_id) {
    if (!$team_id) return 0;

    // 今月のスケジュールで出欠未回答の数を取得
    $current_month = date('Y-m');
    $date_range = [
        'start' => $current_month . '-01',
        'end' => $current_month . '-31'
    ];

    $schedules = get_posts([
        'post_type' => 'schedule',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'team_id',
                'value' => $team_id,
                'compare' => '='
            ],
            [
                'key' => 'schedule_date',
                'value' => [$date_range['start'], $date_range['end']],
                'compare' => 'BETWEEN',
                'type' => 'DATE'
            ]
        ]
    ]);

    $pending_count = 0;
    foreach ($schedules as $schedule) {
        // 各スケジュールに対する出欠回答状況を確認
        $schedule_id = $schedule->ID;
        $team_members = aidunite_get_team_members($team_id, 'player');

        foreach ($team_members as $member) {
            // $memberはWP_Userオブジェクトなので、->IDでアクセス
            $user_id = is_object($member) ? $member->ID : (is_array($member) ? ($member['user_id'] ?? $member['ID'] ?? 0) : 0);
            if ($user_id) {
                $attendance_status = get_post_meta($schedule_id, 'attendance_' . $user_id, true);
                if (empty($attendance_status)) {
                    $pending_count++;
                }
            }
        }
    }

    return $pending_count;
}

/**
 * チームのカレンダーイベントを取得（統一命名）
 */
function aidunite_get_team_calendar_events($team_id) {
    if (!$team_id) return [];

    $current_month = date('Y-m');
    $date_range = [
        'start' => $current_month . '-01',
        'end' => $current_month . '-31'
    ];

    // スケジュール投稿から今月のイベントを取得
    $schedules = get_posts([
        'post_type' => 'schedule',
        'post_status' => 'publish',
        'posts_per_page' => 10,
        'meta_query' => [
            [
                'key' => 'team_id',
                'value' => $team_id,
                'compare' => '='
            ],
            [
                'key' => 'schedule_date',
                'value' => [$date_range['start'], $date_range['end']],
                'compare' => 'BETWEEN',
                'type' => 'DATE'
            ]
        ],
        'orderby' => 'meta_value',
        'meta_key' => 'schedule_date',
        'order' => 'ASC'
    ]);

    $events = [];
    foreach ($schedules as $schedule) {
        $schedule_date = get_post_meta($schedule->ID, 'schedule_date', true);
        $schedule_type = get_post_meta($schedule->ID, 'schedule_type', true);
        $start_time = get_post_meta($schedule->ID, 'start_time', true);
        $end_time = get_post_meta($schedule->ID, 'end_time', true);

        if ($schedule_date) {
            $events[] = [
                'id' => $schedule->ID,
                'title' => $schedule->post_title,
                'date' => $schedule_date,
                'type' => $schedule_type ?: '練習',
                'start_time' => $start_time,
                'end_time' => $end_time,
                'description' => wp_trim_words($schedule->post_content, 20)
            ];
        }
    }

    return $events;
}

/**
 * チームの最近の活動を取得（統一命名）
 */
function aidunite_get_team_recent_activities($team_id) {
    if (!$team_id) return [];

    $activities = [];

    // 最近のスケジュール作成
    $recent_schedules = get_posts([
        'post_type' => 'schedule',
        'post_status' => 'publish',
        'posts_per_page' => 5,
        'meta_query' => [
            [
                'key' => 'team_id',
                'value' => $team_id,
                'compare' => '='
            ]
        ],
        'orderby' => 'date',
        'order' => 'DESC'
    ]);

    foreach ($recent_schedules as $schedule) {
        $schedule_date = get_post_meta($schedule->ID, 'schedule_date', true);
        $activities[] = [
            'type' => 'schedule',
            'title' => 'スケジュール作成: ' . $schedule->post_title,
            'date' => $schedule->post_date,
            'schedule_date' => $schedule_date,
            'description' => '新しいスケジュールが作成されました'
        ];
    }

    // 最近の試合結果
    $recent_matches = get_posts([
        'post_type' => 'multi_match',
        'post_status' => 'publish',
        'posts_per_page' => 5,
        'meta_query' => [
            [
                'key' => 'team_id',
                'value' => $team_id,
                'compare' => '='
            ]
        ],
        'orderby' => 'date',
        'order' => 'DESC'
    ]);

    foreach ($recent_matches as $match) {
        $match_date = get_post_meta($match->ID, 'match_date', true);
        $activities[] = [
            'type' => 'match',
            'title' => '試合完了: ' . $match->post_title,
            'date' => $match->post_date,
            'match_date' => $match_date,
            'description' => '試合が完了しました'
        ];
    }

    // 最近のマッチ申請
    $recent_match_requests = get_posts([
        'post_type' => 'match_request',
        'post_status' => 'publish',
        'posts_per_page' => 5,
        'meta_query' => [
            [
                'key' => 'team_id',
                'value' => $team_id,
                'compare' => '='
            ]
        ],
        'orderby' => 'date',
        'order' => 'DESC'
    ]);

    foreach ($recent_match_requests as $request) {
        $request_status = get_post_meta($request->ID, 'status', true);
        $activities[] = [
            'type' => 'match_request',
            'title' => 'マッチ申請: ' . $request->post_title,
            'date' => $request->post_date,
            'description' => 'マッチ申請が' . ($request_status === 'accepted' ? '承認されました' : ($request_status === 'established' ? '試合が確定しました' : ($request_status === 'pending' ? '保留中です' : '拒否されました')))
        ];
    }

    // 日付でソート
    usort($activities, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });

    return array_slice($activities, 0, 10);
}

/**
 * 子供のカレンダーイベントを取得（統一命名）
 */
function aidunite_get_children_calendar_events($parent_id) {
    if (!$parent_id) return [];

    // 実際の実装ではデータベースから取得
    return [
        [
            'date' => '1月15日',
            'title' => '平日練習',
            'child_name' => '田中太郎',
            'time' => '19:00-21:00'
        ],
        [
            'date' => '1月18日',
            'title' => '週末練習',
            'child_name' => '田中花子',
            'time' => '14:00-17:00'
        ]
    ];
}

/**
 * 保護者向けチーム通知を取得（統一命名）
 */
function aidunite_get_team_notifications_for_parent($parent_id) {
    if (!$parent_id) return [];

    // 実際の実装ではデータベースから取得
    return [
        [
            'icon' => '📢',
            'title' => '練習時間変更のお知らせ',
            'message' => '明日の練習時間が19:30-21:30に変更になりました',
            'date' => '1時間前',
            'unread' => true
        ],
        [
            'icon' => '🏆',
            'title' => '試合結果のお知らせ',
            'message' => '先日の試合結果が更新されました',
            'date' => '2時間前',
            'unread' => false
        ]
    ];
}

/**
 * 選手のカレンダーイベントを取得（統一命名）
 */
function aidunite_get_player_calendar_events($player_id) {
    if (!$player_id) return [];

    // 実際の実装ではデータベースから取得
    return [
        [
            'date' => '1月15日',
            'title' => '平日練習',
            'time' => '19:00-21:00',
            'location' => '体育館A'
        ],
        [
            'date' => '1月18日',
            'title' => '週末練習',
            'time' => '14:00-17:00',
            'location' => '体育館B'
        ]
    ];
}

/**
 * 選手向けチームメッセージを取得（統一命名）
 */
function aidunite_get_team_messages_for_player($player_id) {
    if (!$player_id) return [];

    // 実際の実装ではデータベースから取得
    return [
        [
            'icon' => '📢',
            'title' => '練習時間変更のお知らせ',
            'message' => '明日の練習時間が19:30-21:30に変更になりました',
            'date' => '1時間前',
            'unread' => true
        ],
        [
            'icon' => '🏆',
            'title' => '試合結果のお知らせ',
            'message' => '先日の試合結果が更新されました',
            'date' => '2時間前',
            'unread' => false
        ]
    ];
}

/**
 * 選手の個人記録を取得（統一命名）
 */
function aidunite_get_player_personal_stats($player_id) {
    if (!$player_id) return [];

    // 実際の実装ではデータベースから取得
    return [
        [
            'label' => '総試合数',
            'value' => '15試合'
        ],
        [
            'label' => '得点数',
            'value' => '45点'
        ],
        [
            'label' => 'アシスト数',
            'value' => '12回'
        ],
        [
            'label' => '出席率',
            'value' => '95%'
        ]
    ];
}

/**
 * 選手の総試合数を取得（統一命名）
 */
function aidunite_get_player_total_matches($player_id) {
    if (!$player_id) return 0;

    // 実際の実装ではデータベースから取得
    return rand(10, 30);
}

/**
 * 選手の次の予定数を取得（統一命名）
 */
function aidunite_get_player_next_events_count($player_id) {
    if (!$player_id) return 0;

    // 実際の実装ではデータベースから取得
    return rand(1, 5);
}

/**
 * 支援者の支援チーム数を取得（統一命名）
 */
function aidunite_get_supported_teams_count($supporter_id) {
    if (!$supporter_id) return 0;

    // 実際の実装ではデータベースから取得
    return rand(1, 5);
}

/**
 * 支援者の総支援額を取得（統一命名）
 */
function aidunite_get_total_support_amount($supporter_id) {
    if (!$supporter_id) return 0;

    // 実際の実装ではデータベースから取得
    return rand(50000, 200000);
}

/**
 * 支援者の最近の支援情報を取得（統一命名）
 */
function aidunite_get_recent_support_info($supporter_id) {
    if (!$supporter_id) return [];

    // 実際の実装ではデータベースから取得
    return [
        'next_event' => '1月20日の支援イベント',
        'last_support' => '2024年1月15日',
        'last_amount' => 10000
    ];
}

/**
 * 支援者の月間支援額を取得（統一命名）
 */
function aidunite_get_monthly_support_amount($supporter_id) {
    if (!$supporter_id) return 0;

    // 実際の実装ではデータベースから取得
    return rand(10000, 50000);
}

/**
 * 支援者の支援頻度を取得（統一命名）
 */
function aidunite_get_support_frequency($supporter_id) {
    if (!$supporter_id) return 0;

    // 実際の実装ではデータベースから取得
    return rand(1, 10);
}

/**
 * 支援チームの活動数を取得（統一命名）
 */
function aidunite_get_supported_teams_activities($supporter_id) {
    if (!$supporter_id) return 0;

    // 実際の実装ではデータベースから取得
    return rand(5, 20);
}

/**
 * 支援チーム一覧を取得（統一命名）
 */
function aidunite_get_supported_teams_list($supporter_id) {
    if (!$supporter_id) return [];

    // 実際の実装ではデータベースから取得
    return [
        [
            'name' => 'サンプルチームA',
            'sport_type' => 'バスケットボール',
            'support_amount' => 50000
        ],
        [
            'name' => 'サンプルチームB',
            'sport_type' => 'サッカー',
            'support_amount' => 30000
        ],
        [
            'name' => 'サンプルチームC',
            'sport_type' => '野球',
            'support_amount' => 40000
        ]
    ];
}

/**
 * 支援履歴を取得（統一命名）
 */
function aidunite_get_support_history($supporter_id) {
    if (!$supporter_id) return [];

    // 実際の実装ではデータベースから取得
    return [
        [
            'icon' => '💝',
            'team_name' => 'サンプルチームA',
            'amount' => 10000,
            'type' => '練習用具支援',
            'date' => '2024年1月15日'
        ],
        [
            'icon' => '💝',
            'team_name' => 'サンプルチームB',
            'amount' => 15000,
            'type' => '遠征費用支援',
            'date' => '2024年1月10日'
        ],
        [
            'icon' => '💝',
            'team_name' => 'サンプルチームC',
            'amount' => 8000,
            'type' => 'ユニフォーム支援',
            'date' => '2024年1月5日'
        ]
    ];
}

/**
 * おすすめチームを取得（統一命名）
 */
function aidunite_get_recommended_teams() {
    // 実際の実装ではデータベースから取得
    return [
        [
            'name' => 'おすすめチームA',
            'sport_type' => 'バスケットボール',
            'location' => '東京都',
            'detail_url' => home_url('/team-detail/1')
        ],
        [
            'name' => 'おすすめチームB',
            'sport_type' => 'サッカー',
            'location' => '神奈川県',
            'detail_url' => home_url('/team-detail/2')
        ],
        [
            'name' => 'おすすめチームC',
            'sport_type' => '野球',
            'location' => '埼玉県',
            'detail_url' => home_url('/team-detail/3')
        ]
    ];
}

/**
 * 利用ガイドのステップを取得（統一命名）
 */
function aidunite_get_usage_guide_steps() {
    return [
        [
            'title' => 'プロフィールを完成させよう',
            'description' => 'プロフィール情報を入力して、チームに参加しやすくしましょう',
            'action_url' => home_url('/profile-edit'),
            'action_text' => 'プロフィール編集'
        ],
        [
            'title' => 'チームを探そう',
            'description' => '興味のあるスポーツのチームを探して、参加を申し込みましょう',
            'action_url' => home_url('/team-registration'),
            'action_text' => 'チーム作成'
        ],
        [
            'title' => 'チームを作成しよう',
            'description' => '新しいチームを作成して、仲間を集めましょう',
            'action_url' => home_url('/team-registration'),
            'action_text' => 'チーム作成'
        ]
    ];
}

/**
 * 利用可能なチーム数を取得（統一命名）
 */
function aidunite_get_available_teams_count() {
    // 実際の実装ではデータベースから取得
    return rand(50, 200);
}

/**
 * 近隣チーム数を取得（統一命名）
 */
function aidunite_get_nearby_teams_count() {
    // 実際の実装ではデータベースから取得
    return rand(5, 20);
}

/**
 * 人気スポーツを取得（統一命名）
 */
function aidunite_get_popular_sports() {
    // 実際の実装ではデータベースから取得
    $sports = ['バスケットボール', 'サッカー', '野球', 'テニス', 'バレーボール'];
    return $sports[array_rand($sports)];
}

/**
 * 最近の活動数を取得（統一命名）
 */
function aidunite_get_recent_activities_count() {
    // 実際の実装ではデータベースから取得
    return rand(10, 50);
}

/**
 * ユーザーの登録日を取得（統一命名）
 */
function aidunite_get_user_registration_date($user_id) {
    if (!$user_id) return '不明';

    $user = get_userdata($user_id);
    if (!$user) return '不明';

    return date('Y年m月d日', strtotime($user->user_registered));
}

/**
 * ユーザーの最終ログイン日を取得（統一命名）
 */
function aidunite_get_user_last_login($user_id) {
    if (!$user_id) return '不明';

    $last_login = get_user_meta($user_id, 'last_login', true);
    if (!$last_login) return '不明';

    return date('Y年m月d日', strtotime($last_login));
}

/**
 * 総ユーザー数を取得（統一命名）
 */
function aidunite_get_total_users_count() {
    // 実際の実装ではデータベースから取得
    return rand(500, 2000);
}

/**
 * 総チーム数を取得（統一命名）
 */
function aidunite_get_total_teams_count() {
    // 実際の実装ではデータベースから取得
    return rand(100, 500);
}

/**
 * アクティブセッション数を取得（統一命名）
 */
function aidunite_get_active_sessions_count() {
    // 実際の実装ではデータベースから取得
    return rand(50, 200);
}

/**
 * システム状況を取得（統一命名）
 */
function aidunite_get_system_status() {
    // 実際の実装ではデータベースから取得
    return '正常';
}

/**
 * 新規登録数を取得（統一命名）
 */
function aidunite_get_new_registrations_count() {
    // 実際の実装ではデータベースから取得
    return rand(10, 50);
}

/**
 * 承認待ち数を取得（統一命名）
 */
function aidunite_get_pending_approvals_count() {
    // 実際の実装ではデータベースから取得
    return rand(5, 20);
}

/**
 * システムアラート数を取得（統一命名）
 */
function aidunite_get_system_alerts_count() {
    // 実際の実装ではデータベースから取得
    return rand(0, 5);
}

/**
 * チームのマッチ状況を取得（統一命名）
 */
function aidunite_get_team_match_status($team_id) {
    if (!$team_id) return [];

    $match_status = [
        'pending_requests' => 0,
        'accepted_matches' => 0,
        'upcoming_matches' => 0,
        'recent_matches' => 0
    ];

    // 保留中のマッチ申請
    $pending_requests = get_posts([
        'post_type' => 'match_request',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'team_id',
                'value' => $team_id,
                'compare' => '='
            ],
            [
                'key' => 'status',
                'value' => 'pending',
                'compare' => '='
            ]
        ]
    ]);
    $match_status['pending_requests'] = count($pending_requests);

    // 承認された試合
    $accepted_matches = get_posts([
        'post_type' => 'multi_match',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'team_id',
                'value' => $team_id,
                'compare' => '='
            ],
            [
                'key' => 'result_status',
                'value' => ['scheduled', 'in_progress'],
                'compare' => 'IN'
            ]
        ]
    ]);
    $match_status['accepted_matches'] = count($accepted_matches);

    // 今後の試合
    $current_date = date('Y-m-d');
    $upcoming_matches = get_posts([
        'post_type' => 'multi_match',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'team_id',
                'value' => $team_id,
                'compare' => '='
            ],
            [
                'key' => 'match_date',
                'value' => $current_date,
                'compare' => '>=',
                'type' => 'DATE'
            ]
        ]
    ]);
    $match_status['upcoming_matches'] = count($upcoming_matches);

    // 最近の試合（過去30日）
    $thirty_days_ago = date('Y-m-d', strtotime('-30 days'));
    $recent_matches = get_posts([
        'post_type' => 'multi_match',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'team_id',
                'value' => $team_id,
                'compare' => '='
            ],
            [
                'key' => 'match_date',
                'value' => [$thirty_days_ago, $current_date],
                'compare' => 'BETWEEN',
                'type' => 'DATE'
            ]
        ]
    ]);
    $match_status['recent_matches'] = count($recent_matches);

    return $match_status;
}

/**
 * 後方互換性のためのラッパー関数（段階的削除予定）
 * @deprecated 代わりに aidunite_get_team_match_status() を使用してください
 */
function tunageru_get_team_match_status($team_id) {
    return aidunite_get_team_match_status($team_id);
}

/**
 * 保護者の今後のスケジュールを取得（統一命名）
 */
function aidunite_get_parent_upcoming_schedules($user_id) {
    if (!$user_id) return [];

    $children = aidunite_get_parent_children_list($user_id);
    if (empty($children)) return [];

    $schedules = [];
    $current_date = date('Y-m-d');

    foreach ($children as $child) {
        $child_uid = (int) $child['ID'];
        $child_tid_list = function_exists('aidunite_get_managed_team_ids')
            ? aidunite_get_managed_team_ids($child_uid)
            : [];
        if (empty($child_tid_list)) {
            $c = (int) get_user_meta($child_uid, 'team_id', true);
            if ($c > 0) {
                $child_tid_list = [$c];
            }
        }
        if (empty($child_tid_list)) {
            continue;
        }

        $team_meta = count($child_tid_list) === 1
            ? [
                'key' => 'team_id',
                'value' => (int) $child_tid_list[0],
                'compare' => '=',
            ]
            : [
                'key' => 'team_id',
                'value' => array_values(array_map('intval', $child_tid_list)),
                'compare' => 'IN',
            ];

        $child_schedules = get_posts([
            'post_type' => 'schedule',
            'post_status' => 'publish',
            'posts_per_page' => 5,
            'meta_query' => [
                $team_meta,
                [
                    'key' => 'schedule_date',
                    'value' => $current_date,
                    'compare' => '>=',
                    'type' => 'DATE'
                ]
            ],
            'orderby' => 'meta_value',
            'meta_key' => 'schedule_date',
            'order' => 'ASC'
        ]);

        foreach ($child_schedules as $schedule) {
            $schedule_date = get_post_meta($schedule->ID, 'schedule_date', true);
            $schedule_time = get_post_meta($schedule->ID, 'schedule_time', true);
            $schedule_type = get_post_meta($schedule->ID, 'schedule_type', true);

            $schedules[] = [
                'id' => $schedule->ID,
                'title' => $schedule->post_title,
                'date' => $schedule_date,
                'time' => $schedule_time,
                'type' => $schedule_type,
                'child_name' => $child['display_name']
            ];
        }
    }

    // 日付順でソート
    usort($schedules, function($a, $b) {
        return strtotime($a['date']) - strtotime($b['date']);
    });

    return array_slice($schedules, 0, 5); // 最新5件
}

/**
 * 後方互換性のためのラッパー関数（段階的削除予定）
 * @deprecated 代わりに aidunite_get_parent_upcoming_schedules() を使用してください
 */
function tunageru_get_parent_upcoming_schedules($user_id) {
    return aidunite_get_parent_upcoming_schedules($user_id);
}

/**
 * プレイヤーのチーム情報を取得（統一命名）
 */
function aidunite_get_player_team_info($user_id) {
    if (!$user_id) return [];

    $team_id = function_exists('aidunite_get_current_team_id')
        ? (int) aidunite_get_current_team_id((int) $user_id)
        : (int) get_user_meta($user_id, 'team_id', true);
    if (!$team_id) return [];

    $team_post = get_post($team_id);
    if (!$team_post || $team_post->post_type !== 'team') {
        return [];
    }

    $team_info = [
        'id' => $team_id,
        'name' => $team_post->post_title,
        'description' => $team_post->post_content,
        'sport_type' => get_post_meta($team_id, 'sport_type', true),
        'age_group' => get_post_meta($team_id, 'age_group', true),
        'location' => get_post_meta($team_id, 'location', true),
        'member_count' => aidunite_get_team_member_count($team_id),
        'join_date' => get_user_meta($user_id, 'team_join_date', true)
    ];

    return $team_info;
}

/**
 * プレイヤーの今後のスケジュールを取得（統一命名）
 */
function aidunite_get_player_upcoming_schedules($user_id) {
    if (!$user_id) return [];

    $team_clause = function_exists('aidunite_schedule_team_meta_query_for_user')
        ? aidunite_schedule_team_meta_query_for_user((int) $user_id)
        : null;
    if ($team_clause === null) {
        return [];
    }

    $current_date = date('Y-m-d');

    $schedules = get_posts([
        'post_type' => 'schedule',
        'post_status' => 'publish',
        'posts_per_page' => 5,
        'meta_query' => [
            $team_clause,
            [
                'key' => 'schedule_date',
                'value' => $current_date,
                'compare' => '>=',
                'type' => 'DATE'
            ]
        ],
        'orderby' => 'meta_value',
        'meta_key' => 'schedule_date',
        'order' => 'ASC'
    ]);

    $formatted_schedules = [];
    foreach ($schedules as $schedule) {
        $schedule_date = get_post_meta($schedule->ID, 'schedule_date', true);
        $schedule_time = get_post_meta($schedule->ID, 'schedule_time', true);
        $schedule_type = get_post_meta($schedule->ID, 'schedule_type', true);

        $formatted_schedules[] = [
            'id' => $schedule->ID,
            'title' => $schedule->post_title,
            'date' => $schedule_date,
            'time' => $schedule_time,
            'type' => $schedule_type
        ];
    }

    return $formatted_schedules;
}

/**
 * プレイヤーの最近の出欠記録を取得（統一命名）
 */
function aidunite_get_player_recent_attendance($user_id) {
    if (!$user_id) return [];

    $uid = (int) $user_id;
    $has_team = (function_exists('aidunite_get_managed_team_ids') && !empty(aidunite_get_managed_team_ids($uid)))
        || (int) get_user_meta($uid, 'team_id', true) > 0;
    if (!$has_team) {
        return [];
    }

    $current_date = date('Y-m-d');
    $thirty_days_ago = date('Y-m-d', strtotime('-30 days'));

    $attendances = get_posts([
        'post_type' => 'attendance',
        'post_status' => 'publish',
        'posts_per_page' => 5,
        'meta_query' => [
            [
                'key' => 'player_id',
                'value' => $user_id,
                'compare' => '='
            ],
            [
                'key' => 'schedule_date',
                'value' => [$thirty_days_ago, $current_date],
                'compare' => 'BETWEEN',
                'type' => 'DATE'
            ]
        ],
        'orderby' => 'meta_value',
        'meta_key' => 'schedule_date',
        'order' => 'DESC'
    ]);

    $formatted_attendances = [];
    foreach ($attendances as $attendance) {
        $schedule_date = get_post_meta($attendance->ID, 'schedule_date', true);
        $attendance_status = get_post_meta($attendance->ID, 'attendance_status', true);
        $schedule_title = get_post_meta($attendance->ID, 'schedule_title', true);

        $formatted_attendances[] = [
            'id' => $attendance->ID,
            'date' => $schedule_date,
            'status' => $attendance_status,
            'schedule_title' => $schedule_title
        ];
    }

    return $formatted_attendances;
}

/**
 * 保護者の最近の出欠記録を取得（統一命名）
 */
function aidunite_get_parent_recent_attendance($user_id) {
    if (!$user_id) return [];

    $children = aidunite_get_parent_children_list($user_id);
    if (empty($children)) return [];

    $attendances = [];
    $current_date = date('Y-m-d');
    $thirty_days_ago = date('Y-m-d', strtotime('-30 days'));

    foreach ($children as $child) {
        $child_uid = (int) $child['ID'];
        $has_team = (function_exists('aidunite_get_managed_team_ids') && !empty(aidunite_get_managed_team_ids($child_uid)))
            || (int) get_user_meta($child_uid, 'team_id', true) > 0;
        if (!$has_team) {
            continue;
        }

        $child_attendances = get_posts([
            'post_type' => 'attendance',
            'post_status' => 'publish',
            'posts_per_page' => 5,
            'meta_query' => [
                [
                    'key' => 'player_id',
                    'value' => $child['ID'],
                    'compare' => '='
                ],
                [
                    'key' => 'schedule_date',
                    'value' => [$thirty_days_ago, $current_date],
                    'compare' => 'BETWEEN',
                    'type' => 'DATE'
                ]
            ],
            'orderby' => 'meta_value',
            'meta_key' => 'schedule_date',
            'order' => 'DESC'
        ]);

        foreach ($child_attendances as $attendance) {
            $schedule_date = get_post_meta($attendance->ID, 'schedule_date', true);
            $attendance_status = get_post_meta($attendance->ID, 'attendance_status', true);
            $schedule_title = get_post_meta($attendance->ID, 'schedule_title', true);

            $attendances[] = [
                'id' => $attendance->ID,
                'date' => $schedule_date,
                'status' => $attendance_status,
                'schedule_title' => $schedule_title,
                'child_name' => $child['display_name']
            ];
        }
    }

    // 日付順でソート
    usort($attendances, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });

    return array_slice($attendances, 0, 5); // 最新5件
}

/**
 * 保護者のチーム情報を取得（統一命名）
 */
function aidunite_get_parent_team_info($user_id) {
    if (!$user_id) return [];

    $children = aidunite_get_parent_children_list($user_id);
    if (empty($children)) return [];

    $team_info = [];
    $seen_team_ids = [];

    foreach ($children as $child) {
        $child_uid = (int) $child['ID'];
        $team_ids = function_exists('aidunite_get_managed_team_ids') ? aidunite_get_managed_team_ids($child_uid) : [];
        if (empty($team_ids)) {
            $c = (int) get_user_meta($child_uid, 'team_id', true);
            if ($c > 0) {
                $team_ids = [$c];
            }
        }
        foreach ($team_ids as $child_team_id) {
            $child_team_id = (int) $child_team_id;
            if ($child_team_id <= 0 || isset($seen_team_ids[$child_team_id])) {
                continue;
            }

            $team_post = get_post($child_team_id);
            if (!$team_post || $team_post->post_type !== 'team') {
                continue;
            }

            $seen_team_ids[$child_team_id] = true;
            $team_info[] = [
                'id' => $child_team_id,
                'name' => $team_post->post_title,
                'description' => $team_post->post_content,
                'sport_type' => get_post_meta($child_team_id, 'sport_type', true),
                'age_group' => get_post_meta($child_team_id, 'age_group', true),
                'location' => get_post_meta($child_team_id, 'location', true),
                'member_count' => aidunite_get_team_member_count($child_team_id),
                'child_name' => $child['display_name']
            ];
        }
    }

    return $team_info;
}



?>
