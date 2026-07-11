<?php
/*
Template Name: チャット画面（統合版）
* エントリ: room_id, team_id, chat_id, match_id のいずれかでルームを特定
*/
$GLOBALS['aidunite_team_chat_page'] = true;

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
        $mr_chat = function_exists('aidunite_match_request_get_canonical_meta')
            ? aidunite_match_request_get_canonical_meta((int) $match_id)
            : [];
        $to_schedule_id = (int) ($mr_chat['to_schedule_id'] ?? 0);
        $my_schedule_id = (int) ($mr_chat['my_schedule_id'] ?? 0);
        $match_game_id = $to_schedule_id ?: $my_schedule_id;
    }
    if (!$chat_room && $match_game_id > 0 && function_exists('aidunite_get_active_chat_room_for_match_game')) {
        $chat_room = aidunite_get_active_chat_room_for_match_game($match_game_id);
    }
    if (!$chat_room) {
        $chat_room = aidunite_get_match_chat_room($match_id);
    }
    if (!$chat_room && function_exists('aidunite_create_or_extend_match_chat')) {
        $mr_chat = isset($mr_chat) && is_array($mr_chat)
            ? $mr_chat
            : (function_exists('aidunite_match_request_get_canonical_meta')
                ? aidunite_match_request_get_canonical_meta((int) $match_id)
                : []);
        $from_team_id = (int) ($mr_chat['from_team_id'] ?? 0);
        $to_team_id = (int) ($mr_chat['to_team_id'] ?? 0);
        if ($to_team_id < 1) {
            $to_team_id = (int) ($mr_chat['other_team_id'] ?? 0);
        }
        $match_date = '';
        if ($match_game_id > 0) {
            $match_date = function_exists('aidunite_schedule_read_normalized_date')
                ? aidunite_schedule_read_normalized_date((int) $match_game_id)
                : '';
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
    // 管理者の場合は参加者テーブルに追加してアクセス許可（プレビューモード時はロール制限を優先）
    if (current_user_can('administrator') && !$preview_mode) {
        if (function_exists('aidunite_chat_persist_ensure_participant')) {
            aidunite_chat_persist_ensure_participant($chat_id, $user_id, 'admin');
        }
    } else {
        wp_die('このチャットルームへのアクセス権限がありません。');
    }
}

// ユーザーの参加者登録確認
$is_participant = aidunite_check_chat_permission($chat_id, $user_id);
if (!$is_participant) {
    if (function_exists('aidunite_chat_persist_ensure_participant')) {
        aidunite_chat_persist_ensure_participant($chat_id, $user_id, 'member');
    }
}

// 画面表示時点で既読を最新まで同期（JS 失敗時も未読バッジが残らないようにする）
if (function_exists('aidunite_sync_chat_read_status_to_latest')) {
    aidunite_sync_chat_read_status_to_latest((int) $chat_id, (int) $user_id);
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



<?php
wp_localize_script('aidunite-chat-page', 'aiduniteChatPage', [
    'chatId' => (int) $chat_id,
    'roomType' => (string) $room_type,
    'userId' => (int) $user_id,
    'restNonce' => wp_create_nonce('wp_rest'),
    'restUrl' => rest_url('aidunite/v1/'),
    'chatIconsUrl' => function_exists('aidunite_get_theme_icons_uri') ? aidunite_get_theme_icons_uri() : '',
    'defaultHeaderTitle' => (string) $chat_header_title,
]);
get_footer();
?>
