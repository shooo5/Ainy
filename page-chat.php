<?php
/*
Template Name: チャット画面（統合版）
* エントリ: room_id, team_id, chat_id, match_id のいずれかでルームを特定
*/
$GLOBALS['aidunite_team_chat_page'] = true;

// 【A】FOUC防止：チャット用 critical CSS を head で先に出力（このテンプレート時のみ）
add_action('wp_head', function () {
    echo '<style id="aidunite-chat-critical-css">' . "\n";
    echo '/* ===== Chat Critical CSS (prevent FOUC) ===== */' . "\n";
    echo '.message { margin-bottom: 0.5em; display: flex; align-items: flex-start; gap: 6px; flex-direction: row; }' . "\n";
    echo '.message.own { flex-direction: row; justify-content: flex-end; }' . "\n";
    echo '.message-avatar { width: 36px; height: 36px; min-width: 36px; border-radius: 50%; background: var(--primary-color); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.85rem; }' . "\n";
    echo '.chat-messages .message .message-main { display: inline-flex; flex-direction: column; align-items: flex-start; max-width: min(40ch, 70%); }' . "\n";
    echo '.chat-messages .message.own .message-main { align-items: flex-end; }' . "\n";
    echo '.message-content { background: rgba(255,255,255,0.08); border-radius: var(--radius-medium); box-shadow: var(--shadow-sm); text-align: left; color: #e8ecf4; }' . "\n";
    echo '.chat-messages .message .message-main .message-content { padding: 8px 12px 10px; display: inline-block; width: fit-content; max-width: 100%; max-height: none; min-height: 0; overflow: visible; text-overflow: clip; text-align: left; }' . "\n";
    echo '.chat-messages .message .message-content::after { content: none; display: none; }' . "\n";
    echo '.chat-messages .message .message-body, .chat-messages .message .message-text { overflow: visible; max-height: none; -webkit-line-clamp: unset; display: block; color: #e8ecf4; }' . "\n";
    echo '.chat-page-shell .message-sender, .chat-page-shell .message-time { color: #94a3b8; }' . "\n";
    echo '.chat-page-shell .message-team { color: #c7d2fe; }' . "\n";
    echo '.message.own .message-content { background: rgba(99, 102, 241, 0.35); color: #f4f6ff; border: 1px solid rgba(129, 140, 248, 0.45); }' . "\n";
    echo '.chat-messages .message .message-avatar { display: none; }' . "\n";
    echo '</style>' . "\n";
}, 20);

get_header();

// 統一認証・権限チェック
$auth_result = AidUniteAuthMiddleware::require_team_membership(null, true);
if (!$auth_result->is_valid()) {
    return;
}

$user_id = $auth_result->user_id;
$user_team_id = $auth_result->team_id;

// 管理者でチームIDがない場合の処理（テスト用）
if (current_user_can('administrator') && !$user_team_id) {
    $teams = get_posts([
        'post_type' => 'team',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'fields' => 'ids'
    ]);
    if (!empty($teams)) {
        $user_team_id = $teams[0];
    }
}

list($user_role, $preview_mode) = aidunite_get_effective_user_role();

// データベーステーブル初期化
if (get_option('aidunite_messaging_db_version') !== '2.0.0') {
    require_once get_template_directory() . '/functions/messaging/database-schema.php';
    aidunite_create_messaging_tables();
}

$room_id_from_url = isset($_GET['room_id']) ? intval($_GET['room_id']) : 0;
$team_id_from_url = isset($_GET['team_id']) ? intval($_GET['team_id']) : 0;
$chat_id = isset($_GET['chat_id']) ? intval($_GET['chat_id']) : 0;
$match_id = isset($_GET['match_id']) ? intval($_GET['match_id']) : 0;

$team_id_int = $team_id_from_url ?: intval($user_team_id);
if ($team_id_from_url && (int) $team_id_from_url !== (int) $user_team_id) {
    error_log("⚠️ 警告: ユーザー({$user_id})は指定されたteam_id({$team_id_from_url})に所属していません。ユーザーのteam_id({$user_team_id})を使用します。");
    $team_id_int = intval($user_team_id);
}

$chat_room = null;
$room_type = null;
$chat_header_title = 'チャット';

if ($room_id_from_url) {
    if (function_exists('aidunite_resolve_canonical_chat_room_id')) {
        $canonical_room_id = (int) aidunite_resolve_canonical_chat_room_id($room_id_from_url);
        if ($canonical_room_id > 0 && $canonical_room_id !== $room_id_from_url) {
            wp_safe_redirect(home_url('/chat?room_id=' . $canonical_room_id));
            exit;
        }
        if ($canonical_room_id > 0) {
            $room_id_from_url = $canonical_room_id;
        }
    }
    $chat_room = aidunite_get_chat_room($room_id_from_url);
    if (!$chat_room) {
        wp_die('指定されたチャットルームが見つかりません。');
    }
    if (!aidunite_check_chat_permission($chat_room->id, $user_id)) {
        wp_die('このチャットルームへのアクセス権限がありません。');
    }
    $chat_id = $chat_room->id;
    $room_type = $chat_room->room_type;
    if (!empty($chat_room->team_id)) {
        $team_id_int = intval($chat_room->team_id);
    }
} elseif ($team_id_int && !$chat_id && !$match_id) {
    $chat_room = function_exists('aidunite_get_team_chat_room') ? aidunite_get_team_chat_room($team_id_int) : null;
    if (!$chat_room && function_exists('aidunite_create_team_chat')) {
        $chat_room_id = aidunite_create_team_chat($team_id_int);
        if (!is_wp_error($chat_room_id)) {
            $chat_room = aidunite_get_chat_room(intval($chat_room_id));
        }
    }
    if (!$chat_room) {
        wp_die('チャットルームの取得に失敗しました。管理者にお問い合わせください。');
    }
    $chat_id = $chat_room->id;
    $room_type = !empty($chat_room->room_type) ? $chat_room->room_type : 'team';
} elseif ($chat_id) {
    $chat_room = aidunite_get_chat_room($chat_id);
    if (!$chat_room) {
        wp_die('チャットルームが見つかりません。');
    }
    $room_type = $chat_room->room_type;
} elseif ($match_id) {
    // 3チーム以上の同一募集では match_id ごとに別ルームではなく schedule 共通ルームを優先する。
    if (function_exists('aidunite_get_game_chat_room_for_match_request')) {
        $chat_room = aidunite_get_game_chat_room_for_match_request($match_id);
    }
    $match_game_id = function_exists('aidunite_resolve_match_game_id_for_match_request')
        ? (int) aidunite_resolve_match_game_id_for_match_request($match_id)
        : 0;
    if ($match_game_id <= 0) {
        $to_schedule_id = (int) get_post_meta($match_id, 'to_schedule_id', true);
        $my_schedule_id = (int) get_post_meta($match_id, 'my_schedule_id', true);
        $match_game_id = $to_schedule_id ?: $my_schedule_id;
    }
    if (!$chat_room && $match_game_id > 0 && function_exists('aidunite_get_active_chat_room_for_match_game')) {
        $chat_room = aidunite_get_active_chat_room_for_match_game($match_game_id);
    }
    if (!$chat_room) {
        $chat_room = aidunite_get_match_chat_room($match_id);
    }
    if (!$chat_room && function_exists('aidunite_create_or_extend_match_chat')) {
        $from_team_id = (int) get_post_meta($match_id, 'from_team_id', true);
        $to_team_id = (int) get_post_meta($match_id, 'to_team_id', true);
        $match_date = '';
        if ($match_game_id > 0) {
            $match_date = (string) get_post_meta($match_game_id, 'schedule_date', true);
        }
        if ($from_team_id > 0 && $to_team_id > 0) {
            $room_id = aidunite_create_or_extend_match_chat($match_id, $from_team_id, $to_team_id, $match_game_id, $match_date);
            if (!is_wp_error($room_id) && (int) $room_id > 0) {
                $chat_room = aidunite_get_chat_room((int) $room_id);
            }
        }
    }
    if (!$chat_room) {
        wp_die('対戦チャットルームが見つかりません。ゲームがまだ決定していない可能性があります。');
    }
    $room_type = in_array((string) ($chat_room->room_type ?? ''), ['match', 'group'], true)
        ? (string) $chat_room->room_type
        : 'match';
    $chat_id = (int) $chat_room->id;
} else {
    wp_die('チャットルームID・チームID・マッチIDのいずれかを指定してください。');
}

if (empty($chat_id) || (int) $chat_id <= 0) {
    wp_die('チャットルームを特定できません。URLに room_id / team_id / chat_id / match_id のいずれかを指定するか、チャット一覧から再度入り直してください。');
}

// 権限チェック
if (!aidunite_check_chat_permission($chat_id, $user_id)) {
    // 管理者の場合は参加者テーブルに追加してアクセス許可
    if (current_user_can('administrator')) {
        global $wpdb;
        $participants_table = $wpdb->prefix . 'chat_participants';
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$participants_table} WHERE room_id = %d AND user_id = %d",
            $chat_id, $user_id
        ));
        if (!$existing) {
            $wpdb->insert($participants_table, [
                'room_id' => $chat_id,
                'user_id' => $user_id,
                'role' => 'admin'
            ], ['%d', '%d', '%s']);
        }
    } else {
        wp_die('このチャットルームへのアクセス権限がありません。');
    }
}

// ユーザーの参加者登録確認
$is_participant = aidunite_check_chat_permission($chat_id, $user_id);
if (!$is_participant) {
    global $wpdb;
    $wpdb->insert($wpdb->prefix . 'chat_participants', [
        'room_id' => $chat_id,
        'user_id' => $user_id,
        'role' => 'member'
    ], ['%d', '%d', '%s']);
}

// ヘッダー表示用の情報を取得（room_typeに応じて分岐）
if ($room_type === 'match') {
    $team_a_name = aidunite_get_team_name($chat_room->team_a_id);
    $team_b_name = aidunite_get_team_name($chat_room->team_b_id);
    $match_date_ts = !empty($chat_room->match_date) ? strtotime($chat_room->match_date) : false;
    $match_date = $match_date_ts ? date('m/d', $match_date_ts) : '';
    if ($match_date_ts) {
        $weekdays = ['日', '月', '火', '水', '木', '金', '土'];
        $chat_header_title = date('n/j', $match_date_ts) . '（' . $weekdays[(int) date('w', $match_date_ts)] . '）ゲーム';
    } else {
        $chat_header_title = 'ゲーム';
    }
} elseif ($room_type === 'team') {
    $team_name = aidunite_get_team_name($chat_room->team_id);
    $chat_header_title = "{$team_name} チームチャット";
} else {
    // direct, group, system などの場合
    $chat_header_title = $chat_room->name ?: 'チャット';
}
?>

<?php
$show_match_side = ($room_type === 'match' || $room_type === 'group');
?>
<div class="chat-page-shell">
    <div class="chat-layout">
        <div class="chat-main">
            <div class="chat-messages chat-messages--pattern-c" id="chatMessages">
                <header class="chat-header chat-header--pattern-c">
                    <button type="button" class="back-btn chat-header__back" onclick="history.back()" aria-label="戻る">
                        <span class="back-icon" aria-hidden="true">◁</span>
                    </button>
                    <h1 class="chat-header__title" id="chatHeaderTitle"><?php echo esc_html($chat_header_title); ?></h1>
                </header>

                <?php if ($show_match_side): ?>
                <button type="button" class="chat-mobile-info-toggle" id="chatMobileInfoToggle" aria-expanded="false" aria-controls="chatSidePanel">
                    試合情報を開く
                </button>
                <?php endif; ?>

                <div class="messages-container" id="messagesContainer" data-state="loading" aria-live="polite"></div>

                <button type="button" class="scroll-to-bottom-btn" id="scrollToBottomBtn" aria-label="最新のメッセージへ" title="最新へ" style="display: none;">↓</button>

                <div class="chat-input-area chat-input-area--pattern-c">
            <div class="input-controls-row" role="toolbar" aria-label="メッセージ入力補助">
                <button type="button" class="input-icon-btn" id="attachBtn" title="ファイルを添付する" aria-label="ファイル添付">
                    <?php echo aidunite_get_theme_icon_svg('attach_file', ['width' => '24', 'height' => '24', 'class' => 'input-icon-btn__icon']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </button>
                <button type="button" class="input-icon-btn" id="cameraBtn" title="カメラで写真を撮る" aria-label="写真撮影">
                    <?php echo aidunite_get_theme_icon_svg('photo_camera', ['width' => '24', 'height' => '24', 'class' => 'input-icon-btn__icon']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </button>
                <button type="button" class="input-icon-btn" id="imageBtn" title="イラスト・画像を選択する" aria-label="イラスト選択">
                    <?php echo aidunite_get_theme_icon_svg('image', ['width' => '24', 'height' => '24', 'class' => 'input-icon-btn__icon']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </button>
            </div>
            <div id="replyToIndicator" class="reply-to-indicator" role="status" aria-live="polite">
                <span class="reply-to-preview"></span>
                <button type="button" class="reply-to-cancel" id="replyToCancelBtn" aria-label="返信をキャンセル">×</button>
            </div>
            <div class="input-container">
                <textarea id="messageInput" class="message-input" placeholder="メッセージを入力..." rows="1"></textarea>
                <button class="send-btn" id="sendBtn" type="button" aria-label="送信" data-testid="team-chat-send">
                    <?php echo aidunite_get_theme_icon_svg('send', ['width' => '24', 'height' => '24', 'class' => 'send-icon']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </button>
            </div>
                </div>
            </div>
        </div>

        <?php if ($show_match_side): ?>
        <aside class="chat-side" id="chatSidePanel" aria-label="試合詳細・参加チーム">
            <div id="matchInfoPanel" class="match-info-panel chat-side-panel" role="region" aria-label="試合情報">
                <div class="chat-side-panel__header">
                    <h2 class="chat-side-panel__title">試合情報</h2>
                    <p class="match-info-purpose-note" id="matchInfoPurposeNote">このチャットは、この試合に関わるチーム向けの<strong>全体連絡用</strong>です。発言はチーム単位で表示されます。</p>
                </div>
                <div class="match-info-body chat-side-panel__body" id="matchInfoBody">
                    <dl class="chat-side-dl">
                        <div class="chat-side-dl__row">
                            <dt>日時</dt>
                            <dd><span id="matchInfoDate">-</span> <span id="matchInfoTime">-</span></dd>
                        </div>
                        <div class="chat-side-dl__row">
                            <dt>会場</dt>
                            <dd id="matchInfoVenue">-</dd>
                        </div>
                        <div class="chat-side-dl__row">
                            <dt>試合形式</dt>
                            <dd id="matchInfoGender">-</dd>
                        </div>
                    </dl>
                    <section class="chat-side-teams" aria-labelledby="chatSideTeamsTitle">
                        <h3 class="chat-side-teams__title" id="chatSideTeamsTitle">参加チーム</h3>
                        <ul class="chat-side-teams__list" id="matchInfoParticipantsList">
                            <li class="chat-side-teams__item" id="matchInfoParticipants">-</li>
                        </ul>
                    </section>
                    <p class="chat-side-memo" id="matchInfoMemo" hidden></p>
                </div>
            </div>
        </aside>
        <?php endif; ?>
    </div>
</div>

<!-- 評価モーダル（対戦チャットのみ） -->
<?php if ($room_type === 'match'): ?>
<div id="evaluationModal" class="evaluation-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>🏆 お礼と評価を投稿</h3>
            <button class="modal-close" id="evaluationModalClose">&times;</button>
        </div>
        <form id="evaluationForm" class="evaluation-form">
            <div class="evaluation-info">
                <p><strong>対戦相手:</strong> <?php echo esc_html($team_b_name); ?></p>
                <p><strong>試合日:</strong> <?php echo $match_date; ?> <?php echo $chat_room->match_time ? date('H:i', strtotime($chat_room->match_time)) : ''; ?></p>
            </div>

            <div class="form-group">
                <label>総合評価</label>
                <div class="rating-stars">
                    <input type="radio" id="star5" name="rating" value="5">
                    <label for="star5">★</label>
                    <input type="radio" id="star4" name="rating" value="4">
                    <label for="star4">★</label>
                    <input type="radio" id="star3" name="rating" value="3">
                    <label for="star3">★</label>
                    <input type="radio" id="star2" name="rating" value="2">
                    <label for="star2">★</label>
                    <input type="radio" id="star1" name="rating" value="1">
                    <label for="star1">★</label>
                </div>
            </div>

            <div class="form-group">
                <label for="feedback">お礼メッセージ</label>
                <textarea id="feedback" name="feedback" class="form-textarea" rows="4" placeholder="とても良い試合でした。また機会があれば..."></textarea>
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" id="wouldRematch" name="would_rematch">
                    <span class="checkmark"></span>
                    再試合希望
                </label>
            </div>

            <div class="form-actions">
                <button type="submit" class="submit-btn">📤 お礼と評価を送信</button>
                <button type="button" class="cancel-btn" id="evaluationCancelBtn">❌ キャンセル</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- ファイルアップロード -->
<input type="file" id="fileInput" style="display: none;" multiple>
<input type="file" id="imageInput" style="display: none;" accept="image/*">

<style>
.chat-container {
    max-width: 1200px;
    margin: 0 auto;
    min-height: 100vh;
    background: var(--bg-secondary);
    position: relative;
}

.chat-header {
    display: flex;
    align-items: center;
    padding: 15px 20px;
    background: white;
    border-bottom: 1px solid var(--border-light);
    box-shadow: var(--shadow-sm);
}

.back-btn {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 8px 12px;
    background: var(--text-secondary);
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.9rem;
    transition: background-color 0.3s ease;
}

.back-btn:hover {
    background: var(--text-secondary);
}

.chat-header h1 {
    flex: 1;
    margin: 0;
    font-size: 1.3rem;
    color: var(--text-primary);
    text-align: center;
}

.search-btn {
    padding: 8px 12px;
    background: var(--primary-color);
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.9rem;
    transition: background-color 0.3s ease;
}

.search-btn:hover {
    background: var(--primary-dark);
}

/* チャット画面：スクロールは吹き出しエリアのみ。チャットヘッダーは固定なし・フロー内 */
.chat-messages {
    height: 100vh;
    display: flex;
    flex-direction: column;
    background: var(--bg-secondary);
    position: relative;
    margin: 0;
    overflow: hidden;
}

.chat-messages .chat-header {
    flex-shrink: 0;
    background: var(--bg-primary);
    border-bottom: 1px solid var(--border-light);
    box-shadow: var(--shadow-sm);
}

/* スクロールはここだけ：吹き出しメッセージ内のみ */
.messages-container {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    padding: 0 0 120px;
    -webkit-overflow-scrolling: touch;
}

/* メッセージ：配置を詰める（吹き出し間・日付区切り・システムメッセージの余白を縮小） */
.chat-messages .message {
    margin-bottom: 0.5em;
}
/* 日付区切り（例: 2/24 火） */
.message-date-separator {
    display: flex;
    align-items: center;
    justify-content: center;
    margin: var(--spacing-xs) 0;
    padding: 0 var(--spacing-sm);
}
.message-date-separator__label {
    font-size: 0.75em;
    color: var(--text-secondary);
    padding: 4px 12px;
    background: var(--bg-secondary);
    border-radius: var(--radius-base);
}

/* 塊の後は1行開ける */
.message-continuation {
    margin-top: -2px;
}

/* 1行目: 送信者, 時刻 */
.message-meta {
    display: flex;
    align-items: baseline;
    flex-wrap: wrap;
    gap: 2px;
    font-size: var(--font-size-sm);
    margin: 0;
    padding: 0;
}

.chat-messages .message .message-body {
    margin: 0;
    padding: 0;
}

/* 送信時刻の行にアクション（👍👌🆗返信）を左寄せ、時刻は右 */
.message-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 4px;
    padding-top: 2px;
    gap: 8px;
}
.message-footer .message-time {
    flex-shrink: 0;
}

.chat-messages .message .message-text {
    margin: 0;
    line-height: 1.55;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    word-break: break-word;
}

.message-sender {
    font-weight: 600;
    color: var(--text-primary);
}

.message-meta-sep {
    color: var(--text-secondary);
    margin: 0 1px;
}

.message-time {
    font-size: 0.75em;
    color: var(--text-secondary);
}

.message-text {
    line-height: 1.6;
    word-wrap: break-word;
    color: var(--text-primary);
    font-size: var(--font-size-sm);
}

.message-image {
    max-width: 100%;
    border-radius: var(--radius-small);
    margin-top: 4px;
}

/* ================================ Chat reaction UI improve ================================ */
.chat-messages .message {
    display: flex;
    align-items: flex-start;
    gap: 8px;
}

.chat-messages .message.own {
    justify-content: flex-end;
}

.chat-messages .message .message-main {
    display: inline-flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 6px;
    max-width: min(40ch, 70%);
}

.chat-messages .message.own .message-main {
    align-items: flex-end;
}

.chat-messages .message .message-main .message-content {
    display: inline-block;
    width: fit-content;
    max-width: 100%;
    text-align: left;
    margin-bottom: 0;
}

.chat-messages .message .message-body,
.chat-messages .message .message-text {
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    word-break: break-word;
    line-height: 1.55;
}

/* message-actions は常時うっすら表示（気づきやすさ優先） */
.chat-messages .message .message-actions {
    display: flex;
    align-items: center;
    gap: 4px;
    opacity: 0.42;
    pointer-events: auto;
    transform: none;
    transition: opacity 0.15s ease, background 0.15s ease;
}

.chat-messages .message:hover .message-actions,
.chat-messages .message:focus-within .message-actions,
.chat-messages .message.show-actions .message-actions {
    opacity: 1;
}

.chat-messages .message .message-action-btn {
    border-radius: 999px;
    min-width: 30px;
    height: 30px;
    padding: 0 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.92);
    border: 1px solid rgba(0, 0, 0, 0.08);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
    cursor: pointer;
    font-size: 0.8rem;
    color: var(--text-primary);
}

.chat-messages .message .message-action-btn:hover {
    background: #fff;
    border-color: rgba(0, 0, 0, 0.16);
    color: var(--text-primary);
}

.chat-messages .message .message-action-btn:focus-visible {
    outline: 2px solid var(--primary-color);
    outline-offset: 2px;
}

.chat-messages .message .message-action-btn--active {
    color: var(--primary-color);
}

/* 返信スレッド：親吹き出しの直下に表示（Google Chat風） */
.message-thread-replies {
    margin-top: 4px;
    margin-bottom: 8px;
}

/* 「返信 ◯件」区切りライン（テキスト左・右に水平線） */
.message-thread-separator {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 10px 0 12px;
}

.message-thread-separator__label {
    flex-shrink: 0;
    font-size: 0.85rem;
    color: var(--text-secondary);
}

.message-thread-separator__line {
    flex: 1;
    height: 0;
    min-width: 0;
    border-top: 1px solid var(--border-light);
}

.message--reply .message-main {
    max-width: 85%;
}

/* リアクションは吹き出しと重ねない（gap で直下に配置） */
.chat-messages .message-reactions {
    margin-top: 0;
    padding-top: 0;
    position: static;
    display: inline-flex;
    flex-wrap: wrap;
    gap: 6px;
    max-width: 100%;
}

.chat-messages .message-reaction-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    font-size: 0.8rem;
    border-radius: 999px;
    background: #fff;
    border: 1px solid var(--border-light);
    box-shadow: var(--shadow-sm);
    cursor: pointer;
    user-select: none;
}

.chat-messages .message-reaction-pill:hover {
    background: var(--bg-secondary);
}

.chat-messages .message-reaction-pill--mine {
    border-color: var(--primary-color);
    color: var(--primary-color);
    background: rgba(0, 0, 0, 0.02);
}

.message-reaction-count {
    font-weight: 600;
}

/* メンション用（将来用） */
.mention {
    padding: 0 6px;
    border-radius: 999px;
    background: rgba(85, 140, 255, 0.15);
    color: #1f4bd8;
    font-weight: 600;
}

/* リアクションピッカー（＋で表示） */
.reaction-picker {
    position: fixed;
    z-index: 9999;
    background: #fff;
    border: 1px solid var(--border-light);
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
    padding: 8px;
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 6px;
}

.reaction-picker button {
    width: 34px;
    height: 34px;
    border: 0;
    background: transparent;
    border-radius: 10px;
    cursor: pointer;
    font-size: 1.2rem;
}

.reaction-picker button:hover {
    background: var(--bg-secondary);
}

@media (max-width: 768px) {
    .chat-messages .message .message-main {
        max-width: 92%;
    }
    .chat-messages .message .message-actions {
        opacity: 0.62;
    }
}

.chat-messages .message .message-content::after {
    content: none;
    display: none;
}

/* システムメッセージ：吹き出しにしない・中央寄せピル。余白詰め */
.system-message-wrapper {
    display: flex;
    justify-content: center;
    margin: var(--spacing-sm) 0;
}

.system-message-pill {
    max-width: 85%;
    padding: var(--spacing-sm) var(--spacing-base);
    background: var(--bg-secondary);
    border-radius: var(--radius-large);
    text-align: center;
    font-size: var(--font-size-sm);
    color: var(--text-primary);
    box-shadow: var(--shadow-sm);
}

.system-message-pill__title {
    font-weight: 600;
    color: var(--info-color);
    margin-bottom: 2px;
}

.system-message-pill__content {
    line-height: 1.5;
    white-space: pre-wrap;
}

.system-message-pill__time {
    display: block;
    margin-top: 4px;
    font-size: 0.8em;
    color: var(--text-secondary);
}

.loading-message, .no-messages {
    text-align: center;
    color: var(--text-secondary);
    padding: 40px;
}

/* 最新へボタン（LINE風：古い箇所にいる時だけ右下に表示） */
.scroll-to-bottom-btn {
    position: fixed;
    bottom: calc(165px + env(safe-area-inset-bottom, 0));
    right: 20px;
    z-index: 998;
    width: 44px;
    height: 44px;
    border-radius: var(--radius-small, 6px);
    border: 1px solid var(--border-light);
    background: var(--bg-primary);
    box-shadow: var(--shadow-md);
    font-size: 1.25rem;
    line-height: 1;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: opacity 0.2s ease, transform 0.2s ease;
}
.scroll-to-bottom-btn:hover {
    background: var(--bg-secondary);
    transform: scale(1.05);
}
.scroll-to-bottom-btn:focus-visible {
    outline: 2px solid var(--primary-color);
    outline-offset: 2px;
}

/* 入力エリア：画面下端に固定（スクロールしない） */
.chat-input-area {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 999;
    flex-shrink: 0;
    background: var(--bg-primary);
    border-top: 1px solid var(--border-light);
    padding: 15px 20px;
    padding-bottom: calc(15px + env(safe-area-inset-bottom, 0));
    box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.06);
}

/* 1行: アイコン｜アイコン｜アイコン｜定型文｜定型文｜定型文 */
.input-controls-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
}

.input-icon-btn {
    padding: 8px 12px;
    background: var(--bg-secondary);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-small);
    cursor: pointer;
    color: var(--text-secondary);
    transition: background-color 0.2s ease;
}

.input-icon-btn:hover,
.input-icon-btn:focus-visible {
    background: var(--border-light);
    outline: 2px solid var(--primary-color);
    outline-offset: 2px;
}

.input-shortcut-btn {
    padding: 4px 10px;
    font-size: 0.8rem;
    color: var(--text-secondary);
    background: var(--bg-secondary);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-small);
    cursor: pointer;
    transition: background-color 0.2s ease, color 0.2s ease;
}

.input-shortcut-btn:hover,
.input-shortcut-btn:focus-visible {
    background: var(--border-light);
    color: var(--text-primary);
    outline: 2px solid var(--primary-color);
    outline-offset: 2px;
}

/* 返信先インジケーター（Google Chat風：送信時に親メッセージの下に表示） */
.reply-to-indicator {
    display: none;
    align-items: center;
    gap: 8px;
    padding: 6px 10px;
    margin-bottom: 6px;
    background: var(--bg-secondary);
    border-radius: var(--radius-small);
    font-size: 0.85rem;
    color: var(--text-secondary);
}
.reply-to-indicator.is-visible {
    display: flex;
}
.reply-to-indicator .reply-to-preview {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.reply-to-indicator .reply-to-cancel {
    flex-shrink: 0;
    padding: 2px 8px;
    background: transparent;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    font-size: 1.1rem;
    line-height: 1;
    border-radius: var(--radius-small);
}
.reply-to-indicator .reply-to-cancel:hover,
.reply-to-indicator .reply-to-cancel:focus-visible {
    background: var(--border-light);
    color: var(--text-primary);
    outline: none;
}

.input-container {
    display: flex;
    align-items: flex-end;
    gap: 10px;
}

.message-input {
    flex: 1;
    padding: 10px 14px;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-medium);
    resize: none;
    font-family: inherit;
    font-size: 0.95rem;
    line-height: 1.4;
    min-height: 40px;
    max-height: 5.6em;
}

.message-input:focus {
    outline: none;
    border-color: var(--primary-color);
}

/* チャット送信ボタン：他CSS（.btn-primary等）のグラデーション干渉を防ぐため .chat-input-area でスコープ */
.chat-input-area .send-btn {
    flex-shrink: 0;
    padding: 10px 16px;
    min-height: 40px;
    background: var(--primary-color);
    background-image: none;
    color: white;
    border: none;
    border-radius: var(--radius-medium);
    cursor: pointer;
    font-size: 0.9rem;
    transition: background-color 0.2s ease;
}

.chat-input-area .send-btn:focus-visible {
    outline: 2px solid var(--primary-color);
    outline-offset: 2px;
}

.chat-input-area .send-btn:hover {
    background: var(--primary-dark);
    background-image: none;
}

.chat-input-area .send-btn:disabled,
.chat-input-area .send-btn.send-btn--loading {
    background: var(--text-secondary);
    background-image: none;
    cursor: not-allowed;
    opacity: 0.85;
}

.input-icon-btn__icon,
.send-icon {
    display: block;
    width: 24px;
    height: 24px;
    color: inherit;
}

/* 検索モーダル */
.search-modal, .evaluation-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.search-modal .modal-content, .evaluation-modal .modal-content {
    background: white;
    border-radius: 12px;
    width: 90%;
    max-width: 600px;
    max-height: 80vh;
    overflow: hidden;
}

.search-modal .modal-header, .evaluation-modal .modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: var(--bg-secondary);
    border-bottom: 1px solid var(--border-light);
}

.search-modal .modal-header h3, .evaluation-modal .modal-header h3 {
    margin: 0;
    font-size: 1.2rem;
    color: var(--text-primary);
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--text-secondary);
}

.search-form {
    display: flex;
    gap: 10px;
    padding: 20px;
    border-bottom: 1px solid var(--border-light);
}

.search-input {
    flex: 1;
    padding: 10px 12px;
    border: 1px solid var(--border-light);
    border-radius: 6px;
    font-size: 0.95rem;
}

.search-submit-btn {
    padding: 10px 20px;
    background: var(--primary-color);
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.95rem;
}

.search-results {
    max-height: 400px;
    overflow-y: auto;
    padding: 20px;
}

.search-result-item {
    padding: 15px;
    border-bottom: 1px solid var(--border-light);
    cursor: pointer;
    transition: background-color 0.3s ease;
}

.search-result-item:hover {
    background: var(--bg-secondary);
}

.search-result-sender {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 5px;
}

.search-result-text {
    color: var(--text-secondary);
    font-size: 0.9rem;
    line-height: 1.4;
}

.search-result-time {
    font-size: 0.8rem;
    color: var(--text-muted);
    margin-top: 5px;
}

/* 評価モーダル */
.evaluation-info {
    padding: 20px;
    background: var(--bg-secondary);
    border-bottom: 1px solid var(--border-light);
}

.evaluation-form {
    padding: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--text-primary);
}

.rating-stars {
    display: flex;
    gap: 5px;
    flex-direction: row-reverse;
    justify-content: flex-end;
}

.rating-stars input[type="radio"] {
    display: none;
}

.rating-stars label {
    font-size: 2rem;
    color: var(--border-color);
    cursor: pointer;
    transition: color 0.2s;
}

.rating-stars input[type="radio"]:checked ~ label,
.rating-stars label:hover,
.rating-stars label:hover ~ label {
    color: var(--warning-color);
}

.form-textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid var(--border-light);
    border-radius: 6px;
    font-family: inherit;
    font-size: 0.95rem;
    resize: vertical;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}

.form-actions {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}

.submit-btn, .cancel-btn {
    flex: 1;
    padding: 12px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.95rem;
    transition: background-color 0.3s ease;
}

.submit-btn {
    background: var(--primary-color);
    color: white;
}

.submit-btn:hover {
    background: var(--primary-dark);
}

.cancel-btn {
    background: var(--text-secondary);
    color: white;
}

.cancel-btn:hover {
    background: var(--text-secondary);
}

/* 試合情報パネル（統一定義・折りたたみ可）。ヘッダー下余白は .chat-messages の padding-top で確保済みのため margin-top なし */
.match-info-panel {
    flex-shrink: 0;
    background: white;
    border-bottom: 1px solid var(--border-light);
    box-shadow: var(--shadow-sm);
}

.match-info-header.match-info-toggle {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: var(--spacing-sm);
    padding: var(--spacing-sm) var(--spacing-base);
    cursor: pointer;
    user-select: none;
    border-bottom: 2px solid var(--primary-color);
}
.match-info-header.match-info-toggle:hover {
    background: var(--bg-secondary);
}
.match-info-heading-stack {
    flex: 1;
    min-width: 0;
}
.match-info-header h3 {
    margin: 0;
    font-size: var(--font-size-base);
    color: var(--text-primary);
    font-weight: 600;
}
.match-info-purpose-note {
    margin: var(--spacing-xs) 0 0;
    font-size: var(--font-size-sm);
    color: var(--text-secondary);
    line-height: 1.45;
    font-weight: 400;
}
.match-info-purpose-note strong {
    font-weight: 600;
    color: var(--text-primary);
}
.match-info-toggle-label {
    flex-shrink: 0;
    align-self: center;
    font-size: var(--font-size-sm);
    color: var(--text-secondary);
}

.match-info-body {
    overflow: hidden;
    transition: max-height 0.25s ease;
}
.match-info-panel.is-collapsed .match-info-body {
    max-height: 0;
}

.match-info-content {
    padding: var(--spacing-sm) 0 var(--spacing-base);
    display: flex;
    flex-direction: column;
    gap: var(--spacing-xs);
}

.match-info-row {
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
    font-size: var(--font-size-sm);
}
.match-info-sep {
    color: var(--text-secondary);
}
.match-info-value {
    color: var(--text-primary);
}
.match-info-row--label {
    flex-wrap: wrap;
}
.match-info-row--label .match-info-label-text {
    width: 100%;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 2px;
}
.match-info-row--label .match-info-value {
    width: 100%;
}

@media (max-width: 768px) {
    .chat-container {
        height: 100vh;
    }

    .chat-messages .chat-header {
        padding: 10px 15px;
    }

    .chat-header h1 {
        font-size: 1.1rem;
    }

    .messages-container {
        padding-bottom: calc(120px + env(safe-area-inset-bottom, 0px));
        -webkit-overflow-scrolling: touch;
    }

    .chat-messages .message .message-content {
        max-width: 92%;
    }
    .message-actions {
        flex-wrap: wrap;
    }

    .chat-input-area {
        padding: 10px 15px;
        padding-bottom: calc(10px + env(safe-area-inset-bottom, 0));
    }

    .scroll-to-bottom-btn {
        bottom: calc(155px + env(safe-area-inset-bottom, 0));
        right: 12px;
        width: 40px;
        height: 40px;
        font-size: 1.1rem;
    }

    .input-controls {
        flex-wrap: wrap;
    }

    .search-modal .modal-content, .evaluation-modal .modal-content {
        width: 95%;
        margin: 20px;
    }

    .form-actions {
        flex-direction: column;
    }

    .match-info-row {
        flex-wrap: wrap;
    }
}

/* Pattern C: messaging.css より後に読み込むため、レガシーインラインを上書き */
.chat-page-shell .chat-messages--pattern-c {
    height: calc(100vh - 32px);
    background: var(--chat-surface, #1a2238);
}
.chat-page-shell .chat-input-area--pattern-c {
    position: relative;
    left: auto;
    right: auto;
    bottom: auto;
    z-index: 10;
    background: var(--chat-surface, #1a2238);
    box-shadow: none;
}
.chat-page-shell .chat-header--pattern-c {
    background: var(--chat-surface-elevated, #222c48);
    border-bottom-color: var(--chat-border);
}
.chat-page-shell .match-info-panel.chat-side-panel {
    background: var(--chat-surface, #1a2238);
    border: 1px solid var(--chat-border);
    box-shadow: none;
}
.chat-page-shell .chat-side {
    display: block;
}
@media (max-width: 900px) {
    .chat-page-shell .chat-side:not(.is-mobile-open) {
        display: none;
    }
}

/* Pattern C: 濃紺背景では本文・メタ情報を白系に（レガシー --text-primary 上書き） */
.chat-page-shell,
.chat-page-shell .chat-messages--pattern-c {
    color: var(--chat-text, #e8ecf4);
}

.chat-page-shell .chat-messages--pattern-c .message-text,
.chat-page-shell .chat-messages--pattern-c .message-body,
.chat-page-shell .chat-messages--pattern-c .chat-messages .message .message-text,
.chat-page-shell .chat-messages--pattern-c .message:not(.own) .message-content,
.chat-page-shell .chat-messages--pattern-c .message:not(.own) .message-content .message-text {
    color: var(--chat-text, #e8ecf4);
}

.chat-page-shell .chat-messages--pattern-c .message-sender,
.chat-page-shell .chat-messages--pattern-c .message-team {
    color: color-mix(in srgb, var(--chat-accent, #6366f1) 75%, #fff);
}

.chat-page-shell .chat-messages--pattern-c .message-time,
.chat-page-shell .chat-messages--pattern-c .message-meta-sep,
.chat-page-shell .chat-messages--pattern-c .message-date-separator__label {
    color: var(--chat-text-muted, #94a3b8);
}

.chat-page-shell .chat-messages--pattern-c .message-date-separator__label {
    background: var(--chat-surface-elevated, #222c48);
}

.chat-page-shell .chat-messages--pattern-c .loading-message,
.chat-page-shell .chat-messages--pattern-c .no-messages {
    color: var(--chat-text-muted, #94a3b8);
}

.chat-page-shell .chat-messages--pattern-c .message.own .message-text,
.chat-page-shell .chat-messages--pattern-c .message.own .message-content,
.chat-page-shell .chat-messages--pattern-c .message.own .message-content .message-text {
    color: #f4f6ff;
}

.chat-page-shell .chat-messages--pattern-c .system-message-pill,
.chat-page-shell .chat-messages--pattern-c .system-message-pill__content,
.chat-page-shell .chat-messages--pattern-c .system-message-pill__title {
    color: var(--chat-text, #e8ecf4);
}

.chat-page-shell .chat-messages--pattern-c .system-message-pill__time {
    color: var(--chat-text-muted, #94a3b8);
}

.chat-page-shell .chat-messages--pattern-c .message-action-btn {
    color: var(--chat-text, #e8ecf4);
    background: var(--chat-surface-elevated, #222c48);
    border-color: var(--chat-border);
}

.chat-page-shell .chat-messages--pattern-c .message-reaction-pill {
    color: var(--chat-text, #e8ecf4);
    background: var(--chat-surface-elevated, #222c48);
    border-color: var(--chat-border);
}

.chat-page-shell .chat-messages--pattern-c .message-thread-separator__label {
    color: var(--chat-text-muted, #94a3b8);
}

.chat-page-shell .chat-header--pattern-c .chat-header__title {
    color: var(--chat-text, #e8ecf4);
}

.chat-page-shell .chat-side-panel,
.chat-page-shell .chat-side-panel .match-info-purpose-note,
.chat-page-shell .chat-side-dl__row dt,
.chat-page-shell .chat-side-dl__row dd,
.chat-page-shell .chat-side-teams__title,
.chat-page-shell .chat-side-teams__item {
    color: var(--chat-text, #e8ecf4);
}

.chat-page-shell .chat-side-dl__row dt {
    color: var(--chat-text-muted, #94a3b8);
}

.chat-page-shell .chat-mobile-info-toggle {
    color: var(--chat-text, #e8ecf4);
}

.chat-page-shell .scroll-to-bottom-btn {
    color: var(--chat-text, #e8ecf4);
    background: var(--chat-surface-elevated, #222c48);
    border-color: var(--chat-border);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const chatMessages = document.getElementById('chatMessages');
    const messagesContainer = chatMessages.querySelector('.messages-container');
    const messageInput = document.getElementById('messageInput');
    const sendBtn = document.getElementById('sendBtn');
    const attachBtn = document.getElementById('attachBtn');
    const cameraBtn = document.getElementById('cameraBtn');
    const imageBtn = document.getElementById('imageBtn');
    const fileInput = document.getElementById('fileInput');
    const imageInput = document.getElementById('imageInput');
    const replyToCancelBtn = document.getElementById('replyToCancelBtn');

    if (replyToCancelBtn) replyToCancelBtn.addEventListener('click', function() { clearReplyState(); });

    let messages = [];
    let currentRoomId = <?php echo (int) $chat_id; ?>;

    if (!currentRoomId && typeof window !== 'undefined' && window.location && window.location.search) {
        var params = new URLSearchParams(window.location.search);
        currentRoomId = parseInt(params.get('room_id') || params.get('chat_id') || '', 10) || 0;
    }

    const nonce = '<?php echo wp_create_nonce('wp_rest'); ?>';
    const userId = <?php echo get_current_user_id(); ?>;
    const roomType = '<?php echo $room_type; ?>';
    let eventSource = null;
    const ACTIVE_POLL_MS = 1000;
    const IDLE_POLL_MS = 10000;
    let pollTimer = null;
    let isPollingRequestInFlight = false;
    const chatIconsUrl = '<?php echo esc_js(aidunite_get_theme_icons_uri()); ?>';
    var replyToMessageId = null;
    var replyToMessagePreview = '';
    var lastMarkedReadMessageId = 0;

    function markRoomAsRead(messageId) {
        var messageIdInt = parseInt(messageId || 0, 10) || 0;
        if (!currentRoomId || messageIdInt <= 0) return Promise.resolve();
        if (messageIdInt <= lastMarkedReadMessageId) return Promise.resolve();

        return fetch('/wp-json/aidunite/v1/chats/' + currentRoomId + '/read', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce
            },
            body: JSON.stringify({ message_id: messageIdInt })
        }).then(function(response) {
            if (response.ok) {
                lastMarkedReadMessageId = messageIdInt;
                return;
            }
            return response.json().catch(function() { return {}; }).then(function(data) {
                var msg = (data && data.error) ? data.error : ('HTTP ' + response.status);
                throw new Error(msg);
            });
        }).catch(function(error) {
            console.warn('既読更新に失敗:', error);
        });
    }

    // メッセージ送信（返信時は parent_message_id を付与し、該当吹き出しの下に表示される）
    function sendMessage() {
        const content = messageInput.value.trim();
        if (!content) return;

        if (!currentRoomId && window.location && window.location.search) {
            var params = new URLSearchParams(window.location.search);
            currentRoomId = parseInt(params.get('room_id') || params.get('chat_id') || '', 10) || 0;
        }
        if (!currentRoomId) {
            alert('チャットルームの初期化に失敗しました。\n\n・URLに room_id / chat_id / team_id / match_id が含まれているか確認してください。\n・チャット一覧から再度ルームを開き直すか、ページを再読み込みしてください。');
            return;
        }

        sendBtn.disabled = true;
        sendBtn.classList.add('send-btn--loading');

        const messageData = {
            room_id: currentRoomId,
            message_type: 'text',
            content: content
        };
        if (replyToMessageId) {
            messageData.parent_message_id = parseInt(replyToMessageId, 10);
        }

        fetch('/wp-json/aidunite/v1/chats/' + currentRoomId + '/messages', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce
            },
            body: JSON.stringify(messageData)
        }).then(response => {
            if (response.ok) {
                messageInput.value = '';
                messageInput.style.height = 'auto';
                clearReplyState();
                loadMessages();
            } else {
                return response.json().catch(function() { return {}; }).then(function(data) {
                    var msg = (data && data.error) ? data.error : ('HTTP ' + response.status);
                    throw new Error(msg);
                });
            }
        }).catch(error => {
            console.error('送信エラー:', error);
            alert('メッセージの送信に失敗しました: ' + (error && error.message ? error.message : String(error)));
        }).finally(() => {
            sendBtn.disabled = false;
            sendBtn.classList.remove('send-btn--loading');
        });
    }

    // 吹き出しへのリアクション追加/削除（APIでトグル。新規メッセージは作らない）
    function sendReaction(messageId, reactionType) {
        if (!currentRoomId || !messageId) return;
        fetch('/wp-json/aidunite/v1/chats/' + currentRoomId + '/messages/' + messageId + '/reactions', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce
            },
            body: JSON.stringify({ reaction_type: reactionType })
        }).then(function(res) {
            if (res.ok) loadMessages();
        }).catch(function(err) {
            console.error('リアクションエラー:', err);
        });
    }

    function setReplyState(msgId, preview) {
        replyToMessageId = msgId;
        replyToMessagePreview = (preview || '').trim();
        if (replyToMessagePreview.length > 60) replyToMessagePreview = replyToMessagePreview.slice(0, 60) + '…';
        var el = document.getElementById('replyToIndicator');
        if (el) {
            el.classList.add('is-visible');
            var textEl = el.querySelector('.reply-to-preview');
            if (textEl) textEl.textContent = replyToMessagePreview ? '「' + replyToMessagePreview + '」へ返信' : '返信';
        }
        messageInput.focus();
    }

    function clearReplyState() {
        replyToMessageId = null;
        replyToMessagePreview = '';
        var el = document.getElementById('replyToIndicator');
        if (el) el.classList.remove('is-visible');
    }

    var reactionPickerEl = null;

    function closeReactionPicker() {
        if (reactionPickerEl) {
            reactionPickerEl.remove();
            reactionPickerEl = null;
        }
    }

    function closeAllMessageActions() {
        if (!chatMessages) return;
        chatMessages.querySelectorAll('.message.show-actions').forEach(function(el) { el.classList.remove('show-actions'); });
        closeReactionPicker();
    }

    function isMobile() {
        return window.matchMedia('(max-width: 768px)').matches;
    }

    function toggleReaction(messageId, reactionKey) {
        sendReaction(messageId, reactionKey);
    }

    function openReactionPicker(anchorEl, messageId) {
        closeReactionPicker();
        var emojis = [
            { key: 'thumb_up', label: '👍' },
            { key: 'favorite', label: '⭐' },
            { key: 'ok', label: '🆗' },
            { key: 'laugh', label: '😂' },
            { key: 'pray', label: '🙏' },
            { key: 'fire', label: '🔥' },
            { key: 'clap', label: '👏' },
            { key: 'heart', label: '❤️' },
            { key: 'wow', label: '😮' },
            { key: 'sad', label: '😢' },
            { key: 'angry', label: '😡' },
            { key: 'check', label: '✅' }
        ];
        var picker = document.createElement('div');
        picker.className = 'reaction-picker';
        picker.setAttribute('role', 'dialog');
        picker.setAttribute('aria-label', 'リアクションを選択');
        picker.innerHTML = emojis.map(function(e) { return '<button type="button" data-reaction="' + escapeHtml(e.key) + '" aria-label="' + escapeHtml(e.key) + '">' + e.label + '</button>'; }).join('');
        document.body.appendChild(picker);
        reactionPickerEl = picker;
        var rect = anchorEl.getBoundingClientRect();
        picker.style.top = (rect.bottom + 6) + 'px';
        picker.style.left = Math.min(rect.left, window.innerWidth - 260) + 'px';

        picker.addEventListener('click', function(ev) {
            var btn = ev.target.closest('button[data-reaction]');
            if (!btn) return;
            var reactionKey = btn.getAttribute('data-reaction');
            toggleReaction(messageId, reactionKey);
            closeReactionPicker();
        });
    }

    document.addEventListener('click', function(e) {
        var inMessage = e.target.closest('.chat-messages .message');
        var inPicker = e.target.closest('.reaction-picker');
        if (!inMessage && !inPicker) closeAllMessageActions();
    });

    messagesContainer.addEventListener('click', async function(e) {
        var msgEl = e.target.closest('.chat-messages .message');
        if (!msgEl || msgEl.classList.contains('system-message-wrapper')) return;

        var pill = e.target.closest('.message-reaction-pill');
        if (pill) {
            var messageId = msgEl.dataset && msgEl.dataset.messageId ? msgEl.dataset.messageId : msgEl.getAttribute('data-message-id');
            var reactionKey = pill.dataset && pill.dataset.reaction ? pill.dataset.reaction : pill.getAttribute('data-reaction');
            if (!messageId || !reactionKey) {
                console.warn('reaction data missing', { messageId: messageId, reactionKey: reactionKey });
                return;
            }
            try {
                var res = await fetch('/wp-json/aidunite/v1/chats/' + currentRoomId + '/messages/' + messageId + '/reactions', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': nonce
                    },
                    body: JSON.stringify({ reaction_type: reactionKey })
                });
                var data = res.ok ? await res.json().catch(function() { return {}; }) : await res.json().catch(function() { return {}; });
                if (!res.ok) {
                    console.warn('reaction api failed', data);
                    return;
                }
                await loadMessages();
            } catch (err) {
                console.error('reaction error', err);
            }
            return;
        }

        var addBtn = e.target.closest('.message-action-btn[data-action="add_reaction"]');
        if (addBtn) {
            var msgId = msgEl.getAttribute('data-message-id');
            if (!msgId) return;
            var anchor = msgEl.querySelector('.message-content') || addBtn;
            openReactionPicker(anchor, msgId);
            return;
        }

        if (isMobile()) {
            var tappedContent = e.target.closest('.message-content');
            var tappedButtonOrPill = e.target.closest('.message-action-btn') || e.target.closest('.message-reaction-pill');
            if (tappedContent && !tappedButtonOrPill) {
                var already = msgEl.classList.contains('show-actions');
                closeAllMessageActions();
                if (!already) msgEl.classList.add('show-actions');
                return;
            }
        }

        var btn = e.target.closest('.message-action-btn');
        if (!btn) return;
        var action = btn.getAttribute('data-action');
        var msgId = msgEl.getAttribute('data-message-id') || '';
        var msgContent = (msgEl.getAttribute('data-content') || '').replace(/&quot;/g, '"').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');
        if (action === 'thumb_up') {
            sendReaction(msgId, 'thumb_up');
        } else if (action === 'favorite') {
            sendReaction(msgId, 'favorite');
        } else if (action === 'ok') {
            sendReaction(msgId, 'ok');
        } else if (action === 'reply') {
            setReplyState(msgId, msgContent);
        }
    });

    // モバイル: タッチで click が発火しない/遅延することがあるため touchend でも同じ処理を行う
    function handleReactionTap(e) {
        var target = e.target;
        var msgEl = target.closest('.chat-messages .message');
        if (!msgEl || msgEl.classList.contains('system-message-wrapper')) return false;

        var pill = target.closest('.message-reaction-pill');
        if (pill) {
            var messageId = msgEl.dataset && msgEl.dataset.messageId ? msgEl.dataset.messageId : msgEl.getAttribute('data-message-id');
            var reactionKey = pill.dataset && pill.dataset.reaction ? pill.dataset.reaction : pill.getAttribute('data-reaction');
            if (!messageId || !reactionKey) return false;
            fetch('/wp-json/aidunite/v1/chats/' + currentRoomId + '/messages/' + messageId + '/reactions', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
                body: JSON.stringify({ reaction_type: reactionKey })
            }).then(function(res) {
                if (res.ok) loadMessages();
            }).catch(function(err) { console.error('reaction error', err); });
            return true;
        }

        var addBtn = target.closest('.message-action-btn[data-action="add_reaction"]');
        if (addBtn) {
            var msgId = msgEl.getAttribute('data-message-id');
            if (!msgId) return false;
            var anchor = msgEl.querySelector('.message-content') || addBtn;
            openReactionPicker(anchor, msgId);
            return true;
        }

        var btn = target.closest('.message-action-btn');
        if (!btn) return false;
        var action = btn.getAttribute('data-action');
        var msgId = msgEl.getAttribute('data-message-id') || '';
        var msgContent = (msgEl.getAttribute('data-content') || '').replace(/&quot;/g, '"').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');
        if (action === 'thumb_up') { sendReaction(msgId, 'thumb_up'); return true; }
        if (action === 'favorite') { sendReaction(msgId, 'favorite'); return true; }
        if (action === 'ok') { sendReaction(msgId, 'ok'); return true; }
        if (action === 'reply') { setReplyState(msgId, msgContent); return true; }
        return false;
    }

    messagesContainer.addEventListener('touchend', function(e) {
        if (!e.changedTouches || e.changedTouches.length === 0) return;
        var t = e.changedTouches[0];
        var el = document.elementFromPoint(t.clientX, t.clientY);
        if (!el) return;
        var msgEl = el.closest('.chat-messages .message');
        if (!msgEl || msgEl.classList.contains('system-message-wrapper')) return;
        var isButtonOrPill = el.closest('.message-action-btn') || el.closest('.message-reaction-pill');
        if (!isButtonOrPill) return;
        if (handleReactionTap({ target: el })) {
            e.preventDefault();
        }
    }, { passive: false });

    // メッセージ読み込み（suppressRender: true のときは描画せず配列だけ返す）
    function loadMessages(opts) {
        opts = opts || {};
        var suppressRender = opts.suppressRender === true;
        if (!currentRoomId) {
            if (!suppressRender) {
                messagesContainer.innerHTML = '<div class="loading-message">チャットルームの初期化に失敗しました</div>';
            }
            return Promise.resolve([]);
        }

        return fetch('/wp-json/aidunite/v1/chats/' + currentRoomId + '/messages', {
            headers: {
                'X-WP-Nonce': nonce
            }
        }).then(function(response) {
            if (!response.ok) throw new Error('メッセージの読み込みに失敗しました');
            return response.json();
        }).then(function(data) {
            var allMessages = data.messages || [];
            var list = allMessages.filter(function(message) {
                if (!message.room_id) return false;
                var messageRoomId = parseInt(message.room_id, 10);
                var currentRoomIdInt = parseInt(currentRoomId, 10);
                return messageRoomId === currentRoomIdInt;
            });

            var latestMessageId = 0;
            for (var i = 0; i < list.length; i++) {
                var currentMessageId = parseInt(list[i].id, 10) || 0;
                if (currentMessageId > latestMessageId) latestMessageId = currentMessageId;
            }
            if (latestMessageId > 0) {
                markRoomAsRead(latestMessageId);
            }

            if (suppressRender) {
                messages = list;
                return list;
            }
            var hadMessages = messages.length > 0;
            var existingKeys = {};
            messages.forEach(function(m) { existingKeys[getMessageKey(m)] = true; });
            var newMessages = list.filter(function(m) { return !existingKeys[getMessageKey(m)]; });
            if (!hadMessages && list.length > 0) {
                renderMessages(list);
                messages = list;
            } else if (newMessages.length > 0) {
                appendNewMessages(newMessages);
                messages = list;
            } else {
                messages = list;
                renderMessages(list); /* リアクション等の更新を即時反映（新着がなくても再描画） */
            }
            if (typeof updateScrollToBottomButtonVisibility === 'function') updateScrollToBottomButtonVisibility();
            return list;
        }).catch(function(error) {
            console.error('読み込みエラー:', error);
            if (!suppressRender) {
                messagesContainer.innerHTML = '<div class="loading-message">メッセージの読み込みに失敗しました</div>';
            }
            return [];
        });
    }

    // pending を一覧にマージ（重複排除。pending を先頭に）
    function mergePending(list, pending) {
        if (!pending) return list;

        var pendingKey = pending.id
            ? 'id:' + String(pending.id)
            : 'sys:' + (pending.message_type || 'system') + ':' + (pending.created_at || '') + ':' + (pending.content || pending.message || '');

        var exists = list.some(function(m) {
            var key = m.id
                ? 'id:' + String(m.id)
                : 'sys:' + (m.message_type || 'system') + ':' + (m.created_at || '') + ':' + (m.content || m.message || '');
            return key === pendingKey;
        });

        if (exists) return list;
        return [pending].concat(list);
    }

    // メッセージの一意キー（重複判定・新着判定用）
    function getMessageKey(msg) {
        if (msg.id) return 'id:' + String(msg.id);
        return 'sys:' + (msg.message_type || 'system') + ':' + (msg.created_at || '') + ':' + (msg.content || msg.message || '');
    }

    // 最下部付近にいるか（閾値px以内）
    function isNearBottom(threshold) {
        if (!messagesContainer) return false;
        threshold = threshold || 80;
        return messagesContainer.scrollTop + messagesContainer.clientHeight >= messagesContainer.scrollHeight - threshold;
    }

    // 新着メッセージをマージして全体を再描画（返信が親の直下に来るようツリーで表示）
    function appendNewMessages(newMessages) {
        if (!newMessages.length || !messagesContainer) return;
        var merged = messages.slice();
        var seen = {};
        merged.forEach(function(m) { seen[getMessageKey(m)] = true; });
        newMessages.forEach(function(m) {
            if (!seen[getMessageKey(m)]) {
                merged.push(m);
                seen[getMessageKey(m)] = true;
            }
        });
        merged.sort(function(a, b) { return new Date(a.created_at) - new Date(b.created_at); });
        renderMessages(merged);
        if (typeof updateScrollToBottomButtonVisibility === 'function') updateScrollToBottomButtonVisibility();
    }

    // 親メッセージと返信をグループ化（Google Chat風：返信は該当吹き出しの直下に表示）
    function buildMessageTree(list) {
        var roots = [];
        var repliesByParent = {};
        for (var i = 0; i < list.length; i++) {
            var m = list[i];
            var pid = m.parent_message_id ? parseInt(m.parent_message_id, 10) : null;
            if (!pid) {
                roots.push(m);
            } else {
                if (!repliesByParent[pid]) repliesByParent[pid] = [];
                repliesByParent[pid].push(m);
            }
        }
        roots.sort(function(a, b) { return new Date(a.created_at) - new Date(b.created_at); });
        Object.keys(repliesByParent).forEach(function(pid) {
            repliesByParent[pid].sort(function(a, b) { return new Date(a.created_at) - new Date(b.created_at); });
        });
        return { roots: roots, repliesByParent: repliesByParent };
    }

    // メッセージ表示（日付区切り・返信は親の直下にスレッド表示・リアクション表示）
    function renderMessages(msgs) {
        if (messagesContainer) messagesContainer.removeAttribute('data-state');
        var list = msgs !== undefined ? msgs : messages;
        if (list.length === 0) {
            messagesContainer.innerHTML = '<div class="no-messages">メッセージがありません</div>';
            return;
        }
        var tree = buildMessageTree(list);
        var html = [];
        var lastDateKey = null;
        var lastSenderId = null;
        for (var r = 0; r < tree.roots.length; r++) {
            var root = tree.roots[r];
            var isSystem = root.message_type === 'system' || (root.sender_name && root.sender_name === 'システム');
            var d = new Date(root.created_at);
            var dateKey = d.getFullYear() + '-' + (d.getMonth() + 1) + '-' + d.getDate();
            if (dateKey !== lastDateKey) {
                lastDateKey = dateKey;
                html.push(createDateSeparator(root.created_at));
            }
            var isContinuation = !isSystem && lastSenderId !== null && String(lastSenderId) === String(root.sender_id);
            if (!isSystem) lastSenderId = root.sender_id;
            html.push(createMessageElement(root, { isContinuation: isContinuation, isSystem: isSystem, isReply: false }));
            var replies = tree.repliesByParent[root.id] || [];
            if (replies.length > 0) {
                var replyCount = replies.length;
                var separatorLabel = '返信 ' + replyCount + '件';
                html.push('<div class="message-thread-replies">');
                html.push('<div class="message-thread-separator" role="separator" aria-label="' + escapeHtml(separatorLabel) + '"><span class="message-thread-separator__label">' + escapeHtml(separatorLabel) + '</span><span class="message-thread-separator__line" aria-hidden="true"></span></div>');
                for (var i = 0; i < replies.length; i++) {
                    var reply = replies[i];
                    var rCont = !isSystem && lastSenderId !== null && String(lastSenderId) === String(reply.sender_id);
                    if (!reply.message_type || reply.message_type !== 'system') lastSenderId = reply.sender_id;
                    html.push(createMessageElement(reply, { isContinuation: rCont, isSystem: false, isReply: true }));
                }
                html.push('</div>');
            }
        }
        messagesContainer.innerHTML = html.join('');
        if (msgs !== undefined) messages = list;
    }

    // モバイル：メッセージ長押しでアクション（👍👌🆗返信）表示
    (function setupLongPressActions() {
        var longPressTimer = null;
        var LONG_PRESS_MS = 500;

        function clearTimer() {
            if (longPressTimer) {
                clearTimeout(longPressTimer);
                longPressTimer = null;
            }
        }

        function removeShowActions() {
            [].forEach.call(document.querySelectorAll('.message.show-actions'), function(el) {
                el.classList.remove('show-actions');
            });
        }

        chatMessages.addEventListener('touchstart', function(e) {
            var msg = e.target.closest('.message:not(.system-message-wrapper)');
            if (!msg) return;
            clearTimer();
            longPressTimer = setTimeout(function() {
                longPressTimer = null;
                removeShowActions();
                msg.classList.add('show-actions');
                setTimeout(removeShowActions, 2500);
            }, LONG_PRESS_MS);
        }, { passive: true });

        chatMessages.addEventListener('touchend', clearTimer, { passive: true });
        chatMessages.addEventListener('touchcancel', clearTimer, { passive: true });
    })();

    // 日付区切りラベル（例: 2/24 火）
    function getDateSeparatorLabel(createdAt) {
        var d = new Date(createdAt);
        var w = ['日', '月', '火', '水', '木', '金', '土'][d.getDay()];
        return (d.getMonth() + 1) + '/' + d.getDate() + ' ' + w;
    }

    function createDateSeparator(createdAt) {
        var label = getDateSeparatorLabel(createdAt);
        return '<div class="message-date-separator" role="separator" aria-label="' + escapeHtml(label) + '">' +
            '<span class="message-date-separator__label">' + escapeHtml(label) + '</span></div>';
    }

    // 相対時刻（参考: 昨日 13:33 / 今日 10:00 / 25/01/15 13:33）
    function formatMessageTime(createdAt) {
        var d = new Date(createdAt);
        var now = new Date();
        var today = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime();
        var yesterday = today - 86400000;
        var t = d.getTime();
        var timeStr = d.toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit' });
        if (t >= today) return '今日 ' + timeStr;
        if (t >= yesterday && t < today) return '昨日 ' + timeStr;
        var y = String(d.getFullYear()).slice(-2);
        var m = String(d.getMonth() + 1).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        return y + '/' + m + '/' + day + ' ' + timeStr;
    }

    // メッセージ要素作成（会話の塊・時刻は吹き出し右下・リアクション表示・返信時は message--reply）
    function createMessageElement(message, opts) {
        opts = opts || {};
        var isContinuation = opts.isContinuation;
        var isSystem = opts.isSystem;
        var isReply = opts.isReply === true;

        if (isSystem) {
            return createSystemMessagePill(message);
        }

        var isOwn = message.sender_id == userId;
        var senderName = message.sender_name || '不明';
        var timeStr = formatMessageTime(message.created_at);
        var reactions = message.reactions || {};
        var myReactions = message.my_reactions || [];

        var body = '';
        if (message.message_type === 'image' && message.file_url) {
            body = '<img src="' + escapeHtml(message.file_url) + '" class="message-image" alt="画像">';
        } else {
            body = '<div class="message-text">' + escapeHtml(message.content || '') + '</div>';
        }

        var avatarHtml = '<div class="message-avatar">' + escapeHtml(senderName.charAt(0)) + '</div>';
        var msgId = message.id ? String(message.id) : '';
        var rawContent = message.content || '';
        var msgContent = rawContent.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

        var teamName = message.team_name || message.sender_team_name || '';
        var metaHtml = isContinuation ? '' : (
            '<div class="message-meta">' +
            (teamName ? '<span class="message-team">' + escapeHtml(teamName) + '</span>' : '') +
            '<span class="message-sender">' + escapeHtml(senderName) + '</span>' +
            '</div>'
        );

        var thumbUpActive = myReactions.indexOf('thumb_up') >= 0 ? ' message-action-btn--active' : '';
        var favoriteActive = myReactions.indexOf('favorite') >= 0 ? ' message-action-btn--active' : '';
        var okActive = myReactions.indexOf('ok') >= 0 ? ' message-action-btn--active' : '';

        var reactionLabels = { thumb_up: '👍', favorite: '⭐', ok: '🆗', laugh: '😂', pray: '🙏', fire: '🔥', clap: '👏', heart: '❤️', wow: '😮', sad: '😢', angry: '😡', check: '✅' };
        var reactionsHtml = '';
        for (var rKey in reactions) {
            if (!reactions[rKey]) continue;
            var isMine = myReactions.indexOf(rKey) >= 0;
            var label = reactionLabels[rKey] !== undefined ? reactionLabels[rKey] : rKey;
            reactionsHtml += '<button type="button" class="message-reaction-pill' + (isMine ? ' message-reaction-pill--mine' : '') + '" data-reaction="' + escapeHtml(rKey) + '" aria-label="' + escapeHtml(rKey) + '"><span class="message-reaction-icon">' + label + '</span><span class="message-reaction-count">' + reactions[rKey] + '</span></button>';
        }
        if (reactionsHtml) reactionsHtml = '<div class="message-reactions">' + reactionsHtml + '</div>';

        var replyClass = isReply ? ' message--reply' : '';
        return '<div class="message ' + (isOwn ? 'own' : '') + (isContinuation ? ' message-continuation' : '') + replyClass + '" data-message-id="' + escapeHtml(msgId) + '" data-content="' + msgContent + '">' +
            avatarHtml +
            '<div class="message-main">' +
            '<div class="message-content">' +
            metaHtml +
            '<div class="message-body">' + body + '</div>' +
            '<div class="message-footer">' +
            '<div class="message-actions">' +
            '<button type="button" class="message-action-btn' + thumbUpActive + '" data-action="thumb_up" aria-label="いいね">👍</button>' +
            '<button type="button" class="message-action-btn' + favoriteActive + '" data-action="favorite" aria-label="お気に入り">⭐</button>' +
            '<button type="button" class="message-action-btn' + okActive + '" data-action="ok" aria-label="了解">🆗</button>' +
            '<button type="button" class="message-action-btn" data-action="reply" aria-label="返信">返信</button>' +
            '<button type="button" class="message-action-btn" data-action="add_reaction" aria-label="リアクション追加">＋</button>' +
            '</div>' +
            '<span class="message-time">' + escapeHtml(timeStr) + '</span>' +
            '</div>' +
            '</div>' +
            reactionsHtml +
            '</div></div>';
    }

    // システムメッセージ：吹き出しにせず中央寄せピル
    function createSystemMessagePill(message) {
        var timeStr = formatMessageTime(message.created_at);
        var title = message.title ? ('<div class="system-message-pill__title">' + escapeHtml(message.title) + '</div>') : '';
        var content = '<div class="system-message-pill__content">' + escapeHtml(message.content || '') + '</div>';
        return '<div class="message system-message-wrapper" data-message-id="' + escapeHtml(String(message.id)) + '">' +
            '<div class="system-message-pill" role="status">' +
            title + content +
            '<span class="system-message-pill__time">' + escapeHtml(timeStr) + '</span>' +
            '</div></div>';
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // 最下部にスクロール（「最新へ」ボタン押下時のみ）
    function scrollToBottom() {
        if (!messagesContainer) return;
        function doScroll() {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
        requestAnimationFrame(function() {
            doScroll();
            requestAnimationFrame(doScroll);
        });
    }

    // 最新へボタン（LINE風）：古い箇所にいる時だけ表示し、押下で最下部へ
    const scrollToBottomBtn = document.getElementById('scrollToBottomBtn');
    function updateScrollToBottomButtonVisibility() {
        if (!scrollToBottomBtn || !messagesContainer) return;
        var show = !isNearBottom(120);
        scrollToBottomBtn.style.display = show ? 'flex' : 'none';
    }
    if (messagesContainer) {
        messagesContainer.addEventListener('scroll', updateScrollToBottomButtonVisibility);
    }
    if (scrollToBottomBtn) {
        scrollToBottomBtn.addEventListener('click', function() {
            scrollToBottom();
            updateScrollToBottomButtonVisibility();
        });
    }

    // 入力欄の高さ自動調整（1行→最大約4行）
    messageInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 90) + 'px';
    });

    // Enterキーで送信（Shift+Enterで改行）
    messageInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    // 送信ボタンクリック
    sendBtn.addEventListener('click', sendMessage);

    // ファイル添付
    attachBtn.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', handleFileUpload);

    // 画像選択
    imageBtn.addEventListener('click', () => imageInput.click());
    imageInput.addEventListener('change', handleImageUpload);

    // ファイルアップロード処理
    function handleFileUpload(e) {
        const files = e.target.files;
        if (files.length === 0) return;
        console.log('ファイルアップロード:', files);
    }

    // 画像アップロード処理
    function handleImageUpload(e) {
        const files = e.target.files;
        if (files.length === 0) return;
        console.log('画像アップロード:', files);
    }

    // 安全な可変ポーリング（表示中は短周期、非表示時は長周期）
    function getPollingIntervalMs() {
        return document.hidden ? IDLE_POLL_MS : ACTIVE_POLL_MS;
    }

    function runPollingTick() {
        if (!currentRoomId) {
            scheduleNextPolling();
            return;
        }
        if (isPollingRequestInFlight) {
            scheduleNextPolling();
            return;
        }

        isPollingRequestInFlight = true;
        Promise.resolve(loadMessages())
            .catch(function() {
                // loadMessages 内でエラーハンドリング済み
            })
            .finally(function() {
                isPollingRequestInFlight = false;
                scheduleNextPolling();
            });
    }

    function scheduleNextPolling(delayMs) {
        if (pollTimer) {
            clearTimeout(pollTimer);
            pollTimer = null;
        }
        var nextMs = typeof delayMs === 'number' ? delayMs : getPollingIntervalMs();
        pollTimer = setTimeout(runPollingTick, nextMs);
    }

    function startPolling() {
        scheduleNextPolling(getPollingIntervalMs());
    }

    // システム投稿としてメッセージを表示（中央寄せピル）
    function displayMessageAsSystemPost(message) {
        var timeStr = formatMessageTime(message.created_at);
        var title = message.title ? ('<div class="system-message-pill__title">' + escapeHtml(message.title) + '</div>') : '';
        var content = '<div class="system-message-pill__content">' + escapeHtml(message.content || '') + '</div>';
        var systemMessageElement = '<div class="message system-message-wrapper" data-message-id="' + escapeHtml(String(message.id)) + '">' +
            '<div class="system-message-pill" role="status">' +
            title + content +
            '<span class="system-message-pill__time">' + escapeHtml(timeStr) + '</span>' +
            '</div></div>';
        messagesContainer.insertAdjacentHTML('afterbegin', systemMessageElement);
    }

    // 時間フォーマット関数（統一ライブラリを使用）
    function formatTime(timestamp) {
        if (typeof AidUniteDateUtils !== 'undefined') {
            return AidUniteDateUtils.formatTimeFromTimestamp(timestamp);
        }
        // フォールバック（date-utils.jsが読み込まれていない場合）
        return new Date(timestamp).toLocaleTimeString('ja-JP', {
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    // チャットヘッダータイトルを更新する関数
    function updateChatHeaderTitle(message) {
        const headerTitle = document.getElementById('chatHeaderTitle');
        if (!headerTitle) return;

        let newTitle = '<?php echo esc_js($chat_header_title); ?>';

        if (message.category === 'match') {
            newTitle = '試合について';
        } else if (message.category === 'schedule') {
            newTitle = 'スケジュールについて';
        } else if (message.category === 'practice') {
            newTitle = '練習について';
        } else if (message.title) {
            const title = message.title.length > 20 ? message.title.substring(0, 20) + '...' : message.title;
            newTitle = title;
        }
        headerTitle.textContent = newTitle;
    }

    // 評価モーダル（対戦チャットのみ）
    <?php if ($room_type === 'match'): ?>
    const evaluationModal = document.getElementById('evaluationModal');
    const evaluationModalClose = document.getElementById('evaluationModalClose');
    const evaluationCancelBtn = document.getElementById('evaluationCancelBtn');
    const evaluationForm = document.getElementById('evaluationForm');

    if (evaluationModalClose) {
        evaluationModalClose.addEventListener('click', () => {
            evaluationModal.style.display = 'none';
        });
    }

    if (evaluationCancelBtn) {
        evaluationCancelBtn.addEventListener('click', () => {
            evaluationModal.style.display = 'none';
        });
    }

    if (evaluationForm) {
        evaluationForm.addEventListener('submit', function(e) {
            e.preventDefault();
            // 評価送信処理
            console.log('評価送信:', new FormData(evaluationForm));
        });
    }
    <?php endif; ?>

    // 試合情報パネルの読み込み（match/groupタイプのみ）
    <?php if ($room_type === 'match' || $room_type === 'group'): ?>
    function loadMatchInfo() {
        if (!currentRoomId) return;

        fetch('/wp-json/aidunite/v1/chats/' + currentRoomId + '/match-info', {
            headers: {
                'X-WP-Nonce': nonce
            }
        }).then(response => {
            if (!response.ok) {
                if (response.status === 404) {
                    // 試合情報がない場合はパネルを非表示
                    const panel = document.getElementById('matchInfoPanel');
                    if (panel) panel.style.display = 'none';
                    return;
                }
                throw new Error('試合情報の取得に失敗しました');
            }
            return response.json();
        }).then(data => {
            if (data && !data.error) {
                displayMatchInfo(data);
            }
        }).catch(error => {
            console.error('試合情報取得エラー:', error);
            // エラー時はパネルを非表示
            const panel = document.getElementById('matchInfoPanel');
            if (panel) panel.style.display = 'none';
        });
    }

    function displayMatchInfo(info) {
        const panel = document.getElementById('matchInfoPanel');
        if (!panel) return;

        // 日付（統一定義: Y年n月j日（曜）でAPIから返却）
        const dateElement = document.getElementById('matchInfoDate');
        if (dateElement) {
            dateElement.textContent = info.date_formatted || '-';
        }

        // 時間
        const timeElement = document.getElementById('matchInfoTime');
        if (timeElement) {
            timeElement.textContent = info.time_display || '-';
        }

        // 会場（統一定義でAPIから返却）
        const venueElement = document.getElementById('matchInfoVenue');
        if (venueElement) {
            venueElement.textContent = info.venue_name || '-';
        }

        // 性別（男子:1 / 女子:2 / 男子1、女子2）
        const genderElement = document.getElementById('matchInfoGender');
        if (genderElement) {
            genderElement.textContent = info.gender_display != null ? info.gender_display : '-';
        }

        const participantsElement = document.getElementById('matchInfoParticipants');
        const participantsList = document.getElementById('matchInfoParticipantsList');
        const namesText = (info.participant_names_display && info.participant_count > 0) ? info.participant_names_display : '—';
        if (participantsList && info.participant_teams && Array.isArray(info.participant_teams) && info.participant_teams.length > 0) {
            participantsList.innerHTML = info.participant_teams.map(function(t) {
                var label = (t.name || t.team_name || 'チーム') + (t.representative ? '（代表: ' + t.representative + '）' : '');
                return '<li class="chat-side-teams__item">' + escapeHtml(label) + '</li>';
            }).join('');
        } else if (participantsElement) {
            participantsElement.textContent = namesText;
        } else if (participantsList) {
            participantsList.innerHTML = '<li class="chat-side-teams__item">' + escapeHtml(namesText) + '</li>';
        }

        const memoEl = document.getElementById('matchInfoMemo');
        if (memoEl && info.memo) {
            memoEl.textContent = info.memo;
            memoEl.hidden = false;
        } else if (memoEl) {
            memoEl.hidden = true;
        }

        panel.style.display = '';
    }

    function initChatMobileInfoToggle() {
        var mobileBtn = document.getElementById('chatMobileInfoToggle');
        var side = document.getElementById('chatSidePanel');
        if (!mobileBtn || !side) return;
        mobileBtn.addEventListener('click', function() {
            var open = side.classList.toggle('is-mobile-open');
            mobileBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            mobileBtn.textContent = open ? '試合情報を閉じる' : '試合情報を開く';
        });
    }
    <?php endif; ?>

    // 初期化（async：pending をマージしてから1回だけ描画）
    (async function initChat() {
        var pendingRaw = sessionStorage.getItem('pendingMessage');
        var pending = pendingRaw ? JSON.parse(pendingRaw) : null;

        var list = await loadMessages({ suppressRender: true });
        var merged = mergePending(list, pending);

        renderMessages(merged);

        if (pending) {
            sessionStorage.removeItem('pendingMessage');
            updateChatHeaderTitle(pending);
        }

        startPolling();
    })();

    <?php if ($room_type === 'match' || $room_type === 'group'): ?>
    initChatMobileInfoToggle();
    loadMatchInfo();
    <?php endif; ?>

    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            // 復帰直後は最新を早めに同期
            scheduleNextPolling(0);
        } else {
            scheduleNextPolling(IDLE_POLL_MS);
        }
    });

    // ページ離脱時に接続/タイマーを閉じる
    window.addEventListener('beforeunload', function() {
        if (pollTimer) {
            clearTimeout(pollTimer);
            pollTimer = null;
        }
        if (eventSource) {
            eventSource.close();
        }
    });
});
</script>

<?php get_footer(); ?>
