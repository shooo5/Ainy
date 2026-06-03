<?php
/*
Template Name: コミュニケーションメイン画面
*/

get_header();

// 統一認証・権限チェック
$auth_result = AidUniteAuthMiddleware::require_team_membership(null, true);
if (!$auth_result->is_valid()) {
    // リダイレクトは自動で実行される
    return;
}

$user_id = $auth_result->user_id;
// 操作中チーム（ヘッダー切替と同期）。タイムライン API に渡し男子/女子の混在を防ぐ
$team_id = function_exists('aidunite_get_current_team_id')
    ? (int) aidunite_get_current_team_id((int) $user_id)
    : (int) $auth_result->team_id;
if ($team_id <= 0) {
    $team_id = (int) $auth_result->team_id;
}

// 管理者でチームIDがない場合の処理
if (current_user_can('administrator') && !$team_id) {
    // 管理者の場合は最初に見つかったチームを使用（テスト用）
    $teams = get_posts([
        'post_type' => 'team',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'fields' => 'ids'
    ]);
    if (!empty($teams)) {
        $team_id = $teams[0];
    }
}

// サンプルメッセージの作成（チームIDがある場合のみ）
if ($team_id) {
    aidunite_create_sample_messages($team_id);
}

list($user_role, $preview_mode) = aidunite_get_effective_user_role();

// コミュニケーションメイン用JS（team_id を渡す）
wp_enqueue_script(
    'aidunite-communication-main',
    get_template_directory_uri() . '/assets/js/pages/communication-main.js',
    array('jquery'),
    '1.5',
    true
);
wp_localize_script('aidunite-communication-main', 'aidunite_communication_main', array(
    'team_id' => $team_id ? (int) $team_id : 0,
));

if (function_exists('aidunite_web_app_page_shell_open')) {
    aidunite_web_app_page_shell_open([
        'page_class' => 'page-communication-main',
        'title' => 'コミュニケーション',
        'subtitle' => 'チームの連絡・チャット',
        'content_class' => 'ainy-webapp-content--communication',
    ]);
} else {
    echo '<div class="communication-main-container"><div class="communication-header"><h1>コミュニケーション</h1><p>チームの連絡・チャット</p></div>';
}
?>

    <div class="communication-content communication-content--pattern-c">
        <div class="communication-layout">
            <div class="communication-layout__main">
                <div class="timeline-section">
                    <div class="communication-summary" aria-label="要対応件数">
                        <div class="summary-item" id="summaryUnread" data-action="action-required" role="button" tabindex="0">
                            <span class="summary-label">要対応</span>
                            <span id="unreadCount" class="summary-count">0</span>
                            <span class="summary-unit">件</span>
                        </div>
                    </div>

                    <div class="timeline-header">
                        <div class="timeline-header-actions">
                            <div class="timeline-filters" role="tablist" aria-label="コミュニケーション種別">
                                <button class="filter-btn active" data-filter="all" type="button" role="tab" aria-selected="true" aria-label="すべて">
                                    <span class="filter-btn-label">すべて</span>
                                </button>
                                <button class="filter-btn" data-filter="chat" type="button" role="tab" aria-selected="false" aria-label="チャット">
                                    <span class="filter-btn-badge-wrap"><span class="filter-btn-label">チャット</span></span>
                                    <span class="tab-unread-badge count-badge count-badge--unread count-badge--overlay" data-filter="chat" aria-hidden="true"></span>
                                </button>
                                <button class="filter-btn" data-filter="board" type="button" role="tab" aria-selected="false" aria-label="お知らせ">
                                    <span class="filter-btn-badge-wrap"><span class="filter-btn-label">お知らせ</span></span>
                                    <span class="tab-unread-badge count-badge count-badge--unread count-badge--overlay" data-filter="board" aria-hidden="true"></span>
                                </button>
                                <button class="filter-btn" data-filter="action-required" type="button" role="tab" aria-selected="false" aria-label="要対応">
                                    <span class="filter-btn-badge-wrap"><span class="filter-btn-label">要対応</span></span>
                                    <span class="tab-unread-badge count-badge count-badge--unread count-badge--overlay" data-filter="action-required" aria-hidden="true"></span>
                                </button>
                                <button class="filter-btn" data-filter="completed" type="button" role="tab" aria-selected="false" aria-label="完了済み">
                                    <span class="filter-btn-badge-wrap"><span class="filter-btn-label">完了済み</span></span>
                                    <span class="tab-unread-badge count-badge count-badge--overlay tab-unread-badge--count" data-filter="completed" aria-hidden="true"></span>
                                </button>
                                <button class="filter-btn" data-filter="deleted" type="button" role="tab" aria-selected="false" aria-label="削除済み">
                                    <span class="filter-btn-badge-wrap"><span class="filter-btn-label">削除済み</span></span>
                                    <span class="tab-unread-badge count-badge count-badge--overlay tab-unread-badge--count" data-filter="deleted" aria-hidden="true"></span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div id="timelineList" class="timeline-list timeline-list--pattern-c">
                        <div class="loading-message">タイムラインを読み込み中...</div>
                    </div>
                </div>
            </div>

            <aside class="communication-layout__aside" aria-label="補助情報">
                <section class="communication-aside-card" id="actionRequiredAside" aria-labelledby="actionRequiredAsideTitle">
                    <h2 class="communication-aside-card__title" id="actionRequiredAsideTitle">要対応のチャット</h2>
                    <div id="actionRequiredAsideList" class="communication-aside-list">
                        <p class="communication-aside-empty">読み込み中…</p>
                    </div>
                </section>
                <section class="communication-aside-card communication-aside-card--shortcuts" aria-label="ショートカット">
                    <h2 class="communication-aside-card__title">ショートカット</h2>
                    <ul class="communication-shortcut-list">
                        <li><a class="communication-shortcut-link" href="<?php echo esc_url(home_url('/notifications/')); ?>">お知らせ一覧</a></li>
                        <li><button type="button" class="communication-shortcut-link communication-shortcut-link--btn" id="shortcutStartChat">新しいチャット</button></li>
                        <li><button type="button" class="communication-shortcut-link communication-shortcut-link--btn" id="shortcutPostBoard">お知らせを投稿</button></li>
                    </ul>
                </section>
            </aside>
        </div>
    </div>

<?php
if (function_exists('aidunite_web_app_page_shell_close')) {
    aidunite_web_app_page_shell_close();
} else {
    echo '</div>';
}
?>

<!-- 右下固定 FAB＋メニュー（お知らせを投稿 / チャットを始める） -->
<div class="new-message-btn-fixed-wrap" aria-hidden="false">
    <div class="fab-menu" id="fabMenu" aria-hidden="true">
        <button type="button" class="fab-menu-item" id="fabMenuPostBoard" aria-label="お知らせを投稿">
            <span class="fab-menu-item-icon" aria-hidden="true"><?php echo aidunite_render_theme_icon('list_alt_add', ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
            <span class="fab-menu-item-label">お知らせを投稿</span>
        </button>
        <button type="button" class="fab-menu-item" id="fabMenuStartChat" aria-label="チャットを始める">
            <span class="fab-menu-item-icon" aria-hidden="true"><?php echo aidunite_render_theme_icon('chat', ['width' => '20', 'height' => '20']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
            <span class="fab-menu-item-label">チャットを始める</span>
        </button>
    </div>
    <button type="button" class="new-message-btn new-message-btn--fab" id="newMessageBtn" title="メニューを開く" aria-label="メニューを開く" aria-expanded="false" aria-controls="fabMenu">
        <span class="new-message-btn-icon" aria-hidden="true"><?php echo aidunite_render_theme_icon('add', ['width' => '24', 'height' => '24']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
    </button>
</div>

<!-- チャットを始めるモーダル（参加者選択→ルーム作成→遷移） -->
<div id="startChatModal" class="message-modal start-chat-modal" style="display: none;" aria-labelledby="startChatModalTitle" aria-modal="true">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="startChatModalTitle">チャットを始める</h3>
            <button type="button" class="modal-close" id="startChatModalClose" aria-label="閉じる">&times;</button>
        </div>
        <div class="start-chat-modal-body">
            <p class="start-chat-modal-description">チャットする相手を選んでください（1人でダイレクト、2人以上でグループ）</p>
            <div class="start-chat-members-wrap">
                <div id="startChatMemberList" class="start-chat-member-list" role="listbox" aria-label="チームメンバー">
                    <div class="start-chat-loading" id="startChatMemberLoading">読み込み中...</div>
                </div>
            </div>
            <div class="start-chat-title-wrap" id="startChatTitleWrap" style="display: none;">
                <label for="startChatRoomTitle">グループ名（任意）</label>
                <input type="text" id="startChatRoomTitle" class="form-input" placeholder="例: 練習メンバー" maxlength="100">
            </div>
            <div class="form-actions start-chat-actions">
                <button type="button" class="post-btn post-btn--primary" id="startChatSubmitBtn" disabled>作成してチャットを開く</button>
                <button type="button" class="cancel-btn cancel-btn--tertiary" id="startChatCancelBtn">キャンセル</button>
            </div>
        </div>
    </div>
</div>

<!-- 新規メッセージ投稿モーダル（お知らせ投稿用） -->
<div id="messageModal" class="message-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php echo aidunite_render_theme_icon('chat', ['width' => '22', 'height' => '22']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> お知らせを投稿</h3>
            <button class="modal-close" id="modalClose" aria-label="閉じる">&times;</button>
        </div>

        <form id="messageForm" class="message-form">
            <div class="form-group form-group--primary">
                <label for="messageTitle">タイトル <span class="required-mark">*</span></label>
                <input type="text" id="messageTitle" name="title" required class="form-input" placeholder="メッセージのタイトル">
            </div>

            <div class="form-group form-group--primary">
                <label for="messageContent">内容 <span class="required-mark">*</span></label>
                <textarea id="messageContent" name="content" required class="form-textarea" rows="5" placeholder="メッセージの内容を入力してください"></textarea>
            </div>

            <div class="form-supplementary" aria-label="補助設定">
                <div class="form-row">
                    <div class="form-group">
                        <label for="messageCategory">カテゴリ</label>
                        <select id="messageCategory" name="category" class="form-select">
                            <option value="general">その他</option>
                            <option value="schedule">スケジュール</option>
                            <option value="practice">練習</option>
                            <option value="match">試合</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="messagePriority">重要度</label>
                        <select id="messagePriority" name="priority" class="form-select">
                            <option value="normal">通常</option>
                            <option value="important">重要</option>
                            <option value="urgent">緊急</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="post-btn post-btn--primary" id="postBtn">
                    <span class="post-icon" aria-hidden="true"><?php echo aidunite_render_theme_icon('send', ['width' => '18', 'height' => '18']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    <span class="post-text">投稿</span>
                </button>
                <button type="button" class="draft-btn draft-btn--secondary" id="draftBtn">
                    <span class="draft-icon" aria-hidden="true"><?php echo aidunite_render_theme_icon('save', ['width' => '18', 'height' => '18']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    <span class="draft-text">下書き保存</span>
                </button>
                <button type="button" class="cancel-btn cancel-btn--tertiary" id="cancelBtn">
                    <span class="cancel-text">キャンセル</span>
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// FAB を body 直下に移動（親の transform で fixed が効かなくなるのを防ぐ）
?>
<script>
(function() {
    var wrap = document.querySelector('.new-message-btn-fixed-wrap');
    if (wrap && document.body) document.body.appendChild(wrap);
})();
</script>
<?php get_footer(); ?>
