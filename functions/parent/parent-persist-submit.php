<?php
/**
 * 保護者招待・登録 POST オーケストレーション
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 代表者がチームの保護者承認を操作できるか
 *
 * @param int $leader_user_id
 * @param int $team_id
 * @return bool
 */
function aidunite_parent_submit_leader_can_manage_team($leader_user_id, $team_id) {
    $leader_user_id = (int) $leader_user_id;
    $team_id = (int) $team_id;
    if ($leader_user_id <= 0 || $team_id <= 0) {
        return false;
    }

    if (user_can($leader_user_id, 'administrator')) {
        return true;
    }

    if (function_exists('aidunite_user_is_privileged_admin') && aidunite_user_is_privileged_admin($leader_user_id)) {
        return true;
    }

    if (!function_exists('aidunite_team_resolve_leader_user_id')) {
        require_once get_stylesheet_directory() . '/functions/team/team-persist-read.php';
    }
    $resolved_leader = (int) aidunite_team_resolve_leader_user_id($team_id);

    return $resolved_leader > 0 && $resolved_leader === $leader_user_id;
}

/**
 * @param array<string, mixed> $team
 * @param string               $invite_url
 * @param string               $invite_email
 * @return bool
 */
function aidunite_parent_submit_send_invite_mail(array $team, $invite_url, $invite_email, array $context = []) {
    $invite_email = (string) $invite_email;
    if ($invite_email === '') {
        return false;
    }

    $player_name = trim((string) ($context['player_name'] ?? ''));
    $intro = $player_name !== ''
        ? "お子様「{$player_name}」がチームに登録されました。\n保護者アカウントの作成をお願いします。\n\n"
        : '';

    $subject = $player_name !== ''
        ? '【AidUnite】お子様の登録完了・保護者アカウントのご案内'
        : '【AidUnite】チーム参加のご案内（保護者向け）';

    $message = "{$intro}チーム名：{$team['team_name']}
競技：{$team['sport_type']}
地域：{$team['region']}

以下のURLより保護者として登録し、チームに参加できます：
{$invite_url}

※このリンクは72時間有効です。
※このメールアドレスでの登録のみ有効です。
※登録後、お子様の予定・出欠などをマイページからご確認いただけます。

AidUnite運営チーム
";

    $headers = ['Content-Type: text/plain; charset=UTF-8'];

    return (bool) wp_mail($invite_email, $subject, $message, $headers);
}

/**
 * 既に保護者アカウントがある場合：選手登録のお知らせ（パスワードなし）
 *
 * @param string               $parent_email
 * @param array<string, mixed> $team
 * @param array<string, mixed> $player_data
 * @return bool
 */
function aidunite_parent_submit_send_child_registered_notice_mail($parent_email, array $team, array $player_data) {
    $parent_email = function_exists('aidunite_normalize_email')
        ? aidunite_normalize_email((string) $parent_email)
        : sanitize_email((string) $parent_email);

    if ($parent_email === '') {
        return false;
    }

    $player_name = (string) ($player_data['player_name'] ?? $player_data['nickname'] ?? 'お子様');
    $mypage_url = home_url('/mypage/');

    $subject = '【AidUnite】お子様のチーム登録完了';
    $message = "
保護者 様

お子様「{$player_name}」がチーム「{$team['team_name']}」に登録されました。
今後、練習・試合のスケジュールやチームからの連絡は、保護者のマイページからご確認いただけます。

▶ マイページ：{$mypage_url}

AidUnite運営チーム
";

    $headers = ['Content-Type: text/plain; charset=UTF-8'];

    return (bool) wp_mail($parent_email, $subject, $message, $headers);
}

/**
 * 保護者サインアップ完了通知
 *
 * @param int                  $parent_user_id
 * @param int                  $team_id
 * @param array<string, mixed> $link_result
 * @return bool
 */
function aidunite_parent_submit_send_guardian_signup_complete_mail($parent_user_id, $team_id, array $link_result = []) {
    $parent_user_id = (int) $parent_user_id;
    $team_id = (int) $team_id;
    if ($parent_user_id <= 0) {
        return false;
    }

    $user = get_userdata($parent_user_id);
    if (!$user || $user->user_email === '') {
        return false;
    }

    $team = aidunite_parent_read_team_payload($team_id);
    $linked_count = (int) ($link_result['linked_count'] ?? 0);
    $mypage_url = home_url('/mypage/');

    $child_note = $linked_count > 0
        ? "お子様 {$linked_count} 名との紐づけが完了しました。\n"
        : '';

    $subject = '【AidUnite】保護者登録が完了しました';
    $message = "
{$user->display_name} 様

チーム「{$team['team_name']}」への保護者登録が完了しました。
{$child_note}
▶ マイページ：{$mypage_url}

AidUnite運営チーム
";

    $headers = ['Content-Type: text/plain; charset=UTF-8'];

    return (bool) wp_mail($user->user_email, $subject, $message, $headers);
}

/**
 * 未成年選手登録後：保護者招待または既存保護者への即紐づけ
 *
 * @param int                  $team_id
 * @param int                  $team_leader_id
 * @param int                  $player_user_id
 * @param array<string, mixed> $player_data
 * @param array<string, mixed> $parent_data
 * @return array<string, mixed>
 */
function aidunite_parent_submit_after_minor_player_registered($team_id, $team_leader_id, $player_user_id, array $player_data, array $parent_data) {
    $team_id = (int) $team_id;
    $team_leader_id = (int) $team_leader_id;
    $player_user_id = (int) $player_user_id;

    $parent_email = function_exists('aidunite_normalize_email')
        ? aidunite_normalize_email((string) ($parent_data['parent_email'] ?? ''))
        : sanitize_email((string) ($parent_data['parent_email'] ?? ''));

    if ($team_id <= 0 || $parent_email === '') {
        return [
            'invite_sent' => false,
            'linked_existing' => false,
            'parent_user_id' => 0,
            'message' => '',
        ];
    }

    $team = aidunite_parent_read_team_payload($team_id);
    $existing_parent = get_user_by('email', $parent_email);

    if ($existing_parent instanceof WP_User) {
        $parent_user_id = (int) $existing_parent->ID;
        $current_type = function_exists('aidunite_get_user_type')
            ? (string) aidunite_get_user_type($parent_user_id)
            : '';

        if ($current_type === '' || $current_type === 'general') {
            aidunite_set_user_type($parent_user_id, 'parent');
        }

        if (function_exists('aidunite_add_user_to_multiple_teams')) {
            aidunite_add_user_to_multiple_teams($parent_user_id, $team_id, 'parent', 'active');
        }

        if (function_exists('aidunite_link_parent_and_player')) {
            aidunite_link_parent_and_player($parent_user_id, $player_user_id);
        }

        $sent = aidunite_parent_submit_send_child_registered_notice_mail($parent_email, $team, $player_data);

        return [
            'invite_sent' => false,
            'linked_existing' => true,
            'parent_user_id' => $parent_user_id,
            'notice_sent' => $sent,
            'message' => '選手の登録が完了しました。登録済みの保護者アカウントと紐づけました。',
        ];
    }

    $stored = aidunite_parent_persist_invite_token([
        'team_id' => $team_id,
        'invite_email' => $parent_email,
        'inviter_user_id' => $team_leader_id,
        'hours_valid' => 72,
    ]);

    if (!is_array($stored) || empty($stored['token'])) {
        return [
            'invite_sent' => false,
            'linked_existing' => false,
            'parent_user_id' => 0,
            'message' => '選手は登録されましたが、保護者招待メールの送信に失敗しました。保護者招待ページから再送してください。',
        ];
    }

    $invite_url = home_url(
        '/guardian-signup/?token=' . rawurlencode((string) $stored['token']) . '&team_id=' . $team_id
    );

    $sent = aidunite_parent_submit_send_invite_mail($team, $invite_url, $parent_email, [
        'player_name' => (string) ($player_data['player_name'] ?? $player_data['nickname'] ?? ''),
    ]);

    if (!$sent) {
        aidunite_parent_consume_invite_token((string) $stored['token']);
        return [
            'invite_sent' => false,
            'linked_existing' => false,
            'parent_user_id' => 0,
            'message' => '選手は登録されましたが、保護者招待メールの送信に失敗しました。保護者招待ページから再送してください。',
        ];
    }

    return [
        'invite_sent' => true,
        'linked_existing' => false,
        'parent_user_id' => 0,
        'message' => '選手の登録が完了しました。保護者への招待メールを送信しました。',
    ];
}

/**
 * QR 申請時に代表者へ通知
 *
 * @param int $team_id
 * @param int $parent_user_id
 */
function aidunite_parent_submit_notify_join_request($team_id, $parent_user_id) {
    $team_id = (int) $team_id;
    $parent_user_id = (int) $parent_user_id;
    if ($team_id <= 0 || $parent_user_id <= 0 || !function_exists('aidunite_notify_user')) {
        return;
    }

    if (!function_exists('aidunite_team_resolve_leader_user_id')) {
        require_once get_stylesheet_directory() . '/functions/team/team-persist-read.php';
    }
    $leader_id = (int) aidunite_team_resolve_leader_user_id($team_id);
    if ($leader_id <= 0) {
        return;
    }

    $parent = get_userdata($parent_user_id);
    $team = aidunite_parent_read_team_payload($team_id);
    $parent_name = $parent ? (string) $parent->display_name : '保護者';

    aidunite_notify_user(
        $leader_id,
        '保護者の参加申請',
        ($team['team_name'] ?? 'チーム') . ' に ' . $parent_name . ' さんから参加申請がありました。承認をお願いします。',
        'parent_join_request',
        $parent_user_id
    );
}

/**
 * 承認時に保護者へ通知
 *
 * @param int $team_id
 * @param int $parent_user_id
 */
function aidunite_parent_submit_notify_parent_approved($team_id, $parent_user_id) {
    $team_id = (int) $team_id;
    $parent_user_id = (int) $parent_user_id;
    if ($team_id <= 0 || $parent_user_id <= 0 || !function_exists('aidunite_notify_user')) {
        return;
    }

    $team = aidunite_parent_read_team_payload($team_id);
    aidunite_notify_user(
        $parent_user_id,
        'チーム参加が承認されました',
        ($team['team_name'] ?? 'チーム') . ' への参加が承認されました。マイページからご利用いただけます。',
        'parent_approved',
        $team_id
    );
}

/**
 * チーム参加承認メール（QR 承認後）
 *
 * @param int $team_id
 * @param int $parent_user_id
 * @return bool
 */
function aidunite_parent_submit_send_parent_approved_mail($team_id, $parent_user_id) {
    $team_id = (int) $team_id;
    $parent_user_id = (int) $parent_user_id;
    if ($team_id <= 0 || $parent_user_id <= 0) {
        return false;
    }

    $user = get_userdata($parent_user_id);
    if (!$user || $user->user_email === '') {
        return false;
    }

    $team = aidunite_parent_read_team_payload($team_id);
    $mypage_url = home_url('/mypage/');
    $team_name = (string) ($team['team_name'] ?? 'チーム');

    $subject = '【AidUnite】チーム参加が承認されました';
    $message = "
{$user->display_name} 様

チーム「{$team_name}」への参加申請が代表者により承認されました。
Ainy 会員として、チームの保護者機能をご利用いただけます。

▶ マイページ：{$mypage_url}

AidUnite運営チーム
";

    $headers = ['Content-Type: text/plain; charset=UTF-8'];

    if (function_exists('aidunite_send_mail')) {
        $result = aidunite_send_mail($user->user_email, $subject, $message, $headers, 'parent_approved');
        return !empty($result['ok']);
    }

    return (bool) wp_mail($user->user_email, $subject, $message, $headers);
}

/**
 * QR: メール確認後・代表者承認待ちの案内メール
 *
 * @param int $parent_user_id
 * @param int $team_id
 * @return bool
 */
function aidunite_parent_submit_send_email_confirmed_pending_approval_mail($parent_user_id, $team_id) {
    $parent_user_id = (int) $parent_user_id;
    $team_id = (int) $team_id;
    if ($parent_user_id <= 0 || $team_id <= 0) {
        return false;
    }

    $user = get_userdata($parent_user_id);
    if (!$user || $user->user_email === '') {
        return false;
    }

    $team = aidunite_parent_read_team_payload($team_id);
    $login_url = home_url('/login/');
    $team_name = (string) ($team['team_name'] ?? 'チーム');

    $subject = '【AidUnite】メール確認完了・参加承認待ち';
    $message = "
{$user->display_name} 様

Ainy 会員登録（メール確認）が完了しました。
チーム「{$team_name}」への参加申請は、代表者の承認待ちです。
承認されるとマイページからスケジュール・連絡・出欠などをご利用いただけます。

▶ ログイン：{$login_url}

AidUnite運営チーム
";

    $headers = ['Content-Type: text/plain; charset=UTF-8'];

    if (function_exists('aidunite_send_mail')) {
        $result = aidunite_send_mail($user->user_email, $subject, $message, $headers, 'guardian_email_confirmed');
        return !empty($result['ok']);
    }

    return (bool) wp_mail($user->user_email, $subject, $message, $headers);
}

/**
 * 仮登録完了画面 URL
 *
 * @param string $invite_type email|qr
 * @param bool   $mail_sent
 * @return string
 */
function aidunite_parent_submit_provisional_redirect_url($invite_type, $mail_sent = true) {
    $args = [
        'pending' => '1',
        'invite_type' => aidunite_parent_normalize_invite_type($invite_type),
    ];
    if (!$mail_sent) {
        $args['mail_failed'] = '1';
    }

    return add_query_arg($args, home_url('/guardian-registration-pending/'));
}

/**
 * 保護者の parent 所属 team_id を1件取得
 *
 * @param int $user_id
 * @return int
 */
function aidunite_parent_submit_read_primary_parent_team_id($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0 || !function_exists('aidunite_user_read_team_memberships')) {
        return 0;
    }

    $memberships = aidunite_user_read_team_memberships($user_id);
    if (!is_array($memberships)) {
        return 0;
    }

    foreach ($memberships as $team_id => $membership) {
        if (!is_array($membership)) {
            continue;
        }
        if ((string) ($membership['role'] ?? '') === 'parent') {
            return (int) $team_id;
        }
    }

    return 0;
}

/**
 * 本登録完了後: 保護者招待経路の後処理
 *
 * @param int $user_id
 */
function aidunite_parent_on_registration_accepted($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return;
    }

    $reg_ctx = function_exists('aidunite_user_read_registration_context')
        ? aidunite_user_read_registration_context($user_id)
        : [];
    if (empty($reg_ctx['is_guardian_invite'])) {
        return;
    }

    $registration_source = (string) ($reg_ctx['registration_source'] ?? '');
    $invite_type = aidunite_parent_normalize_invite_type(
        (string) ($reg_ctx['guardian_invite_type'] ?? '')
    );
    if (strpos($registration_source, '_qr') !== false) {
        $invite_type = 'qr';
    } elseif (strpos($registration_source, '_email') !== false) {
        $invite_type = 'email';
    }

    $team_id = aidunite_parent_submit_read_primary_parent_team_id($user_id);
    if ($team_id <= 0) {
        return;
    }

    if ($invite_type === 'email') {
        if (function_exists('aidunite_user_persist_update_team_membership_status')) {
            aidunite_user_persist_update_team_membership_status($user_id, $team_id, 'active');
        }

        $parent_email = function_exists('aidunite_normalize_email')
            ? aidunite_normalize_email((string) get_userdata($user_id)->user_email)
            : sanitize_email((string) get_userdata($user_id)->user_email);

        $link_result = ['linked_count' => 0, 'player_ids' => []];
        if ($parent_email !== '' && function_exists('aidunite_parent_link_guardian_to_team_players')) {
            $link_result = aidunite_parent_link_guardian_to_team_players($user_id, $team_id, $parent_email);
        }

        aidunite_parent_submit_send_guardian_signup_complete_mail($user_id, $team_id, $link_result);
        clean_user_cache($user_id);
        return;
    }

    aidunite_parent_submit_notify_join_request($team_id, $user_id);
    aidunite_parent_submit_send_email_confirmed_pending_approval_mail($user_id, $team_id);
    clean_user_cache($user_id);
}

/**
 * 既存ユーザーの legacy team_id → team_memberships 移行
 *
 * @param int $user_id
 */
function aidunite_parent_submit_migrate_legacy_team_memberships($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0 || !function_exists('aidunite_get_user_teams')) {
        return;
    }

    $existing_teams = aidunite_get_user_teams($user_id);
    if (!empty($existing_teams)) {
        return;
    }

    $existing_team_id = function_exists('aidunite_user_read_legacy_team_id')
        ? (int) aidunite_user_read_legacy_team_id($user_id)
        : 0;

    if ($existing_team_id <= 0) {
        return;
    }

    $memberships = [
        $existing_team_id => [
            'team_id' => $existing_team_id,
            'role' => 'parent',
            'joined_date' => current_time('mysql'),
            'status' => 'active',
        ],
    ];

    if (function_exists('aidunite_user_persist_team_memberships')) {
        aidunite_user_persist_team_memberships($user_id, $memberships);
        clean_user_cache($user_id);
    }
}

/**
 * サインアップ後の共通ユーザー処理
 *
 * @param int                  $user_id
 * @param int                  $team_id
 * @param array<string, mixed> $payload
 * @param array<string, mixed> $stored
 * @param array<string, mixed> $opts defer_activation_actions, registration_status
 * @return array{membership_status:string,redirect:string,link_result:array}
 */
function aidunite_parent_submit_finalize_guardian_user($user_id, $team_id, array $payload, array $stored, array $opts = []) {
    $user_id = (int) $user_id;
    $team_id = (int) $team_id;
    $defer_activation = !empty($opts['defer_activation_actions']);
    $registration_status = (string) ($opts['registration_status'] ?? 'accepted');

    $invite_type = aidunite_parent_normalize_invite_type($stored['invite_type'] ?? 'email');
    $membership_status = $invite_type === 'qr' ? 'pending' : 'active';
    if ($defer_activation) {
        $membership_status = 'pending';
    }

    $profile_payload = $payload;
    $profile_payload['invite_type'] = $invite_type;
    $profile_payload['registration_source'] = $invite_type === 'qr'
        ? 'guardian_invite_qr'
        : 'guardian_invite_email';

    $parent_name = trim($payload['parent_name_sei'] . ' ' . $payload['parent_name_mei']);
    wp_update_user([
        'ID' => $user_id,
        'display_name' => $parent_name,
        'last_name' => $payload['parent_name_sei'],
        'first_name' => $payload['parent_name_mei'],
    ]);

    aidunite_set_user_type($user_id, 'parent');
    aidunite_parent_submit_migrate_legacy_team_memberships($user_id);
    aidunite_add_user_to_multiple_teams($user_id, $team_id, 'parent', $membership_status);

    aidunite_parent_persist_guardian_signup_profile($user_id, $profile_payload, [
        'registration_status' => $registration_status,
    ]);

    if ($invite_type === 'email') {
        aidunite_parent_consume_invite_token((string) ($stored['token'] ?? $payload['token'] ?? ''));
    } else {
        aidunite_parent_increment_invite_token_use((string) ($stored['token'] ?? ''), $team_id);
        if (!$defer_activation) {
            aidunite_parent_submit_notify_join_request($team_id, $user_id);
        }
    }

    $parent_email = function_exists('aidunite_normalize_email')
        ? aidunite_normalize_email((string) ($payload['parent_email'] ?? ''))
        : sanitize_email((string) ($payload['parent_email'] ?? ''));

    $link_result = ['linked_count' => 0, 'player_ids' => []];
    if (!$defer_activation && $membership_status === 'active' && $parent_email !== '' && function_exists('aidunite_parent_link_guardian_to_team_players')) {
        $link_result = aidunite_parent_link_guardian_to_team_players($user_id, $team_id, $parent_email);
    }

    if (!$defer_activation && $membership_status === 'active') {
        aidunite_parent_submit_send_guardian_signup_complete_mail($user_id, $team_id, $link_result);
    }

    clean_user_cache($user_id);
    wp_cache_flush();

    $redirect_args = [
        'registered' => '1',
        'team_id' => $team_id,
    ];
    if ($membership_status === 'pending') {
        $redirect_args['pending'] = '1';
    }
    if (!empty($link_result['linked_count'])) {
        $redirect_args['linked'] = (string) (int) $link_result['linked_count'];
    }

    return [
        'membership_status' => $membership_status,
        'redirect' => add_query_arg($redirect_args, home_url('/mypage')),
        'link_result' => $link_result,
    ];
}

/**
 * @param array<string, mixed> $raw
 * @param int                  $current_user_id
 * @return array<string, mixed>
 */
function aidunite_parent_submit_invite(array $raw, $current_user_id) {
    $current_user_id = (int) $current_user_id;
    if ($current_user_id <= 0) {
        return [
            'ok' => false,
            'redirect' => wp_login_url(home_url('/invite-guardian')),
            'errors' => [],
            'flash' => ['status' => 'auth_required', 'message' => ''],
        ];
    }

    $payload = function_exists('aidunite_normalize_guardian_invite_payload')
        ? aidunite_normalize_guardian_invite_payload($raw)
        : $raw;

    $team_id = function_exists('aidunite_get_current_team_id')
        ? (int) aidunite_get_current_team_id($current_user_id)
        : 0;

    if ($team_id <= 0) {
        return [
            'ok' => false,
            'redirect' => home_url('/invite-guardian/?error=no_team'),
            'errors' => [],
            'flash' => ['status' => 'no_team', 'message' => ''],
        ];
    }

    if (!aidunite_parent_submit_leader_can_manage_team($current_user_id, $team_id)) {
        return [
            'ok' => false,
            'redirect' => home_url('/mypage'),
            'errors' => ['保護者招待はチーム代表者のみ実行できます。'],
            'flash' => ['status' => 'forbidden', 'message' => ''],
        ];
    }

    if (
        function_exists('aidunite_activation_is_page_locked')
        && aidunite_activation_is_page_locked('invite-guardian', $team_id)
    ) {
        $message = function_exists('aidunite_activation_lock_message')
            ? (string) aidunite_activation_lock_message($team_id)
            : '初回の試合が成立すると利用できます';

        return [
            'ok' => false,
            'redirect' => '',
            'errors' => [$message],
            'flash' => ['status' => 'locked', 'message' => $message],
        ];
    }

    $invite_email = (string) ($payload['invite_email'] ?? '');
    if ($invite_email === '') {
        return [
            'ok' => false,
            'redirect' => '',
            'errors' => ['保護者のメールアドレスを入力してください。'],
            'flash' => ['status' => 'validation', 'message' => ''],
            'form_defaults' => ['invite_email' => ''],
        ];
    }

    $stored = aidunite_parent_persist_invite_token([
        'team_id' => $team_id,
        'invite_email' => $invite_email,
        'invite_type' => 'email',
        'inviter_user_id' => $current_user_id,
        'hours_valid' => 72,
    ]);

    if (!is_array($stored) || empty($stored['token'])) {
        return [
            'ok' => false,
            'redirect' => home_url('/invite-guardian/?error=send_failed'),
            'errors' => [],
            'flash' => ['status' => 'send_failed', 'message' => ''],
        ];
    }

    $team = aidunite_parent_read_team_payload($team_id);
    $invite_url = home_url(
        '/guardian-signup/?token=' . rawurlencode((string) $stored['token']) . '&team_id=' . $team_id
    );

    $sent = aidunite_parent_submit_send_invite_mail($team, $invite_url, $invite_email);

    if (!$sent) {
        aidunite_parent_consume_invite_token((string) $stored['token']);
        return [
            'ok' => false,
            'redirect' => home_url('/invite-guardian/?error=send_failed'),
            'errors' => [],
            'flash' => ['status' => 'send_failed', 'message' => ''],
        ];
    }

    return [
        'ok' => true,
        'redirect' => home_url('/invite-guardian/?sent=1'),
        'errors' => [],
        'flash' => ['status' => 'sent', 'message' => '保護者に招待メールを送信しました。'],
    ];
}

function aidunite_parent_submit_send_activation_email_for_guardian($user_id, array $payload, $token) {
    if (!function_exists('aidunite_send_activation_email')) {
        require_once get_stylesheet_directory() . '/functions/member/register-functions.php';
    }

    if (!function_exists('aidunite_send_activation_email')) {
        return ['ok' => false, 'error' => 'mail_utils_missing'];
    }

    return aidunite_send_activation_email($user_id, [
        'last_name' => (string) ($payload['parent_name_sei'] ?? ''),
        'first_name' => (string) ($payload['parent_name_mei'] ?? ''),
        'user_email' => (string) ($payload['parent_email'] ?? ''),
    ], (string) $token);
}

/**
 * 仮登録未完了の保護者 signup を再送信
 *
 * @param int                  $user_id
 * @param int                  $team_id
 * @param array<string, mixed> $payload
 * @param array<string, mixed> $stored
 * @return array<string, mixed>
 */
function aidunite_parent_refresh_pending_guardian_signup($user_id, $team_id, array $payload, array $stored) {
    $user_id = (int) $user_id;
    $team_id = (int) $team_id;
    $invite_type = aidunite_parent_normalize_invite_type($stored['invite_type'] ?? 'email');

    $parent_name = trim($payload['parent_name_sei'] . ' ' . $payload['parent_name_mei']);
    wp_update_user([
        'ID' => $user_id,
        'user_pass' => (string) ($payload['password'] ?? ''),
        'display_name' => $parent_name,
        'last_name' => $payload['parent_name_sei'],
        'first_name' => $payload['parent_name_mei'],
    ]);

    aidunite_parent_submit_finalize_guardian_user($user_id, $team_id, $payload, $stored, [
        'defer_activation_actions' => true,
        'registration_status' => 'pending',
    ]);

    $token = function_exists('aidunite_user_persist_provisional_registration_token')
        ? aidunite_user_persist_provisional_registration_token($user_id)
        : bin2hex(random_bytes(16));

    $mail_result = aidunite_parent_submit_send_activation_email_for_guardian($user_id, $payload, $token);

    return [
        'ok' => true,
        'redirect' => aidunite_parent_submit_provisional_redirect_url($invite_type, !empty($mail_result['ok'])),
        'errors' => [],
        'flash' => ['status' => 'pending_activation', 'message' => ''],
        'membership_status' => 'pending',
        'provisional' => true,
    ];
}

/**
 * 新規保護者アカウント: 仮登録 + 確認メール
 *
 * @param int                  $user_id
 * @param int                  $team_id
 * @param array<string, mixed> $payload
 * @param array<string, mixed> $stored
 * @param string               $invite_type
 * @return array<string, mixed>
 */
function aidunite_parent_submit_provisional_guardian_signup($user_id, $team_id, array $payload, array $stored, $invite_type) {
    $user_id = (int) $user_id;
    $team_id = (int) $team_id;

    aidunite_parent_submit_finalize_guardian_user($user_id, $team_id, $payload, $stored, [
        'defer_activation_actions' => true,
        'registration_status' => 'pending',
    ]);

    $token = function_exists('aidunite_user_persist_provisional_registration_token')
        ? aidunite_user_persist_provisional_registration_token($user_id)
        : bin2hex(random_bytes(16));

    $mail_result = aidunite_parent_submit_send_activation_email_for_guardian($user_id, $payload, $token);

    return [
        'ok' => true,
        'redirect' => aidunite_parent_submit_provisional_redirect_url($invite_type, !empty($mail_result['ok'])),
        'errors' => [],
        'flash' => ['status' => 'pending_activation', 'message' => ''],
        'next_action' => 'confirm_email',
        'membership_status' => 'pending',
        'provisional' => true,
    ];
}

/**
 * @param array<string, mixed> $raw
 * @return array<string, mixed>
 */
function aidunite_parent_submit_guardian_signup(array $raw) {
    $payload = function_exists('aidunite_normalize_parent_signup_payload')
        ? aidunite_normalize_parent_signup_payload($raw)
        : $raw;

    $token = (string) ($payload['token'] ?? '');
    $team_id = (int) ($payload['team_id'] ?? 0);
    $errors = [];

    $stored = aidunite_parent_read_invite_token($token, $team_id > 0 ? $team_id : null);
    if (!is_array($stored)) {
        return [
            'ok' => false,
            'redirect' => '',
            'errors' => ['この招待リンクは無効または期限切れです。'],
            'flash' => ['status' => 'invalid_token', 'message' => ''],
            'form_repersist' => $payload,
        ];
    }

    $team_id = (int) ($stored['team_id'] ?? $team_id);
    $invite_type = aidunite_parent_normalize_invite_type($stored['invite_type'] ?? 'email');

    if (empty($payload['parent_name_sei']) || empty($payload['parent_name_mei'])) {
        $errors[] = '保護者名（姓・名）は必須です';
    }
    if (empty($payload['parent_kana_sei']) || empty($payload['parent_kana_mei'])) {
        $errors[] = 'フリガナ（姓・名）は必須です';
    }
    if (empty($payload['parent_email'])) {
        $errors[] = 'メールアドレスは必須です';
    }

    if ($invite_type === 'email') {
        $expected_email = function_exists('aidunite_normalize_email')
            ? aidunite_normalize_email((string) ($stored['invite_email'] ?? ''))
            : sanitize_email((string) ($stored['invite_email'] ?? ''));
        $actual_email = (string) ($payload['parent_email'] ?? '');
        if ($expected_email !== '' && $expected_email !== $actual_email) {
            $errors[] = 'この招待は別のメールアドレス向けです。招待メールに記載のアドレスで登録してください。';
        }
    }

    $existing_user = null;
    $needs_new_account = true;
    if (!empty($payload['parent_email']) && email_exists($payload['parent_email'])) {
        $existing_user = get_user_by('email', $payload['parent_email']);
        $needs_new_account = !($existing_user instanceof WP_User);
    }

    if ($needs_new_account) {
        if (empty($payload['agree_terms'])) {
            $errors[] = '利用規約およびプライバシーポリシーに同意してください';
        }
        if (empty($payload['password']) || strlen((string) $payload['password']) < 8) {
            $errors[] = 'パスワードは8文字以上で入力してください';
        }
        if ($payload['password'] !== ($payload['password_confirm'] ?? '')) {
            $errors[] = 'パスワードが一致しません';
        }
    } elseif ($existing_user instanceof WP_User) {
        $pending_registration = (string) get_user_meta((int) $existing_user->ID, 'registration_status', true);
        if ($pending_registration === 'pending') {
            if (empty($payload['agree_terms'])) {
                $errors[] = '利用規約およびプライバシーポリシーに同意してください';
            }
            if (empty($payload['password']) || strlen((string) $payload['password']) < 8) {
                $errors[] = 'パスワードは8文字以上で入力してください';
            }
            if ($payload['password'] !== ($payload['password_confirm'] ?? '')) {
                $errors[] = 'パスワードが一致しません';
            }
        }
    }

    if (!empty($errors)) {
        return [
            'ok' => false,
            'redirect' => '',
            'errors' => $errors,
            'flash' => ['status' => 'validation', 'message' => ''],
            'form_repersist' => $payload,
            'token' => $token,
            'team_id' => $team_id,
        ];
    }

    if ($existing_user instanceof WP_User) {
        $reg_ctx = function_exists('aidunite_user_read_registration_context')
            ? aidunite_user_read_registration_context((int) $existing_user->ID)
            : [];
        if (($reg_ctx['registration_status'] ?? '') === 'pending' && empty($reg_ctx['registration_is_accepted'])) {
            return aidunite_parent_refresh_pending_guardian_signup((int) $existing_user->ID, $team_id, $payload, $stored);
        }
    }

    $membership_user_id = $existing_user ? (int) $existing_user->ID : 0;
    if ($membership_user_id > 0 && function_exists('aidunite_parent_read_user_membership_status')) {
        $current_membership_status = aidunite_parent_read_user_membership_status($membership_user_id, $team_id);
        if ($current_membership_status === 'active') {
            return [
                'ok' => false,
                'redirect' => '',
                'errors' => ['すでにこのチームに参加しています。マイページからご利用ください。'],
                'flash' => ['status' => 'already_member', 'message' => ''],
                'form_repersist' => $payload,
                'token' => $token,
                'team_id' => $team_id,
            ];
        }
        if ($current_membership_status === 'pending') {
            return [
                'ok' => false,
                'redirect' => add_query_arg(['pending' => '1'], home_url('/mypage')),
                'errors' => ['すでに参加申請中です。チーム代表者の承認をお待ちください。'],
                'flash' => ['status' => 'already_pending', 'message' => ''],
                'form_repersist' => $payload,
                'token' => $token,
                'team_id' => $team_id,
            ];
        }
    }

    if ($existing_user instanceof WP_User) {
        $user_id = (int) $existing_user->ID;
        $final = aidunite_parent_submit_finalize_guardian_user($user_id, $team_id, $payload, $stored);

        if (is_user_logged_in() && get_current_user_id() === $user_id) {
            return [
                'ok' => true,
                'redirect' => $final['redirect'],
                'errors' => [],
                'flash' => [
                    'status' => $final['membership_status'] === 'pending' ? 'pending' : 'registered',
                    'message' => '',
                ],
                'next_action' => 'link_player',
                'membership_status' => $final['membership_status'],
            ];
        }

        return [
            'ok' => true,
            'redirect' => add_query_arg(['redirect_to' => $final['redirect']], wp_login_url()),
            'errors' => [],
            'flash' => [
                'status' => $final['membership_status'] === 'pending' ? 'pending' : 'registered',
                'message' => '',
            ],
            'next_action' => 'link_player',
            'membership_status' => $final['membership_status'],
        ];
    }

    $username = sanitize_user($payload['parent_email']);
    $user_id = wp_create_user($username, (string) $payload['password'], $payload['parent_email']);

    if (is_wp_error($user_id)) {
        return [
            'ok' => false,
            'redirect' => '',
            'errors' => ['アカウント作成に失敗しました: ' . $user_id->get_error_message()],
            'flash' => ['status' => 'create_failed', 'message' => ''],
            'form_repersist' => $payload,
            'token' => $token,
            'team_id' => $team_id,
        ];
    }

    return aidunite_parent_submit_provisional_guardian_signup(
        (int) $user_id,
        $team_id,
        $payload,
        $stored,
        $invite_type
    );
}

/**
 * @param array<string, mixed> $raw
 * @param int                  $current_user_id
 * @return array<string, mixed>
 */
function aidunite_parent_submit_approve_parent(array $raw, $current_user_id) {
    $current_user_id = (int) $current_user_id;
    $team_id = (int) ($raw['team_id'] ?? 0);
    $parent_user_id = (int) ($raw['parent_user_id'] ?? $raw['parent_id'] ?? 0);

    if (!aidunite_parent_submit_leader_can_manage_team($current_user_id, $team_id)) {
        return [
            'ok' => false,
            'errors' => ['この操作を行う権限がありません。'],
            'flash' => ['status' => 'forbidden', 'message' => ''],
        ];
    }

    if ($parent_user_id <= 0) {
        return [
            'ok' => false,
            'errors' => ['保護者が指定されていません。'],
            'flash' => ['status' => 'validation', 'message' => ''],
        ];
    }

    $status = aidunite_parent_read_user_membership_status($parent_user_id, $team_id);
    if ($status !== 'pending') {
        return [
            'ok' => false,
            'errors' => ['承認待ちの保護者ではありません。'],
            'flash' => ['status' => 'not_pending', 'message' => ''],
        ];
    }

    if (!aidunite_parent_user_registration_is_accepted($parent_user_id)) {
        return [
            'ok' => false,
            'errors' => ['保護者のメール確認（本登録）が完了していません。'],
            'flash' => ['status' => 'registration_pending', 'message' => ''],
        ];
    }

    if (!function_exists('aidunite_user_persist_update_team_membership_status')
        || !aidunite_user_persist_update_team_membership_status($parent_user_id, $team_id, 'active')) {
        return [
            'ok' => false,
            'errors' => ['承認処理に失敗しました。'],
            'flash' => ['status' => 'persist_failed', 'message' => ''],
        ];
    }

    $parent_profile = function_exists('aidunite_parent_read_guardian_profile_fields')
        ? aidunite_parent_read_guardian_profile_fields($parent_user_id)
        : [];
    aidunite_parent_persist_approval_log_entry($team_id, [
        'action' => 'approved',
        'parent_user_id' => $parent_user_id,
        'actor_user_id' => $current_user_id,
        'invite_type' => (string) ($parent_profile['guardian_invite_type'] ?? ''),
    ]);

    aidunite_parent_submit_notify_parent_approved($team_id, $parent_user_id);
    aidunite_parent_submit_send_parent_approved_mail($team_id, $parent_user_id);

    $parent_user = get_userdata($parent_user_id);
    if ($parent_user && function_exists('aidunite_parent_link_guardian_to_team_players')) {
        aidunite_parent_link_guardian_to_team_players($parent_user_id, $team_id, (string) $parent_user->user_email);
    }

    clean_user_cache($parent_user_id);

    return [
        'ok' => true,
        'redirect' => home_url('/team-members?list_tab=parents&parent_tab=pending&approved=1&team_id=' . $team_id),
        'errors' => [],
        'flash' => ['status' => 'approved', 'message' => '保護者を承認しました。'],
    ];
}

/**
 * @param array<string, mixed> $raw
 * @param int                  $current_user_id
 * @return array<string, mixed>
 */
function aidunite_parent_submit_reject_parent(array $raw, $current_user_id) {
    $current_user_id = (int) $current_user_id;
    $team_id = (int) ($raw['team_id'] ?? 0);
    $parent_user_id = (int) ($raw['parent_user_id'] ?? $raw['parent_id'] ?? 0);

    if (!aidunite_parent_submit_leader_can_manage_team($current_user_id, $team_id)) {
        return [
            'ok' => false,
            'errors' => ['この操作を行う権限がありません。'],
            'flash' => ['status' => 'forbidden', 'message' => ''],
        ];
    }

    if ($parent_user_id <= 0) {
        return [
            'ok' => false,
            'errors' => ['保護者が指定されていません。'],
            'flash' => ['status' => 'validation', 'message' => ''],
        ];
    }

    $status = aidunite_parent_read_user_membership_status($parent_user_id, $team_id);
    if ($status !== 'pending') {
        return [
            'ok' => false,
            'errors' => ['承認待ちの保護者ではありません。'],
            'flash' => ['status' => 'not_pending', 'message' => ''],
        ];
    }

    if (!function_exists('aidunite_user_persist_remove_team_membership')
        || !aidunite_user_persist_remove_team_membership($parent_user_id, $team_id)) {
        return [
            'ok' => false,
            'errors' => ['却下処理に失敗しました。'],
            'flash' => ['status' => 'persist_failed', 'message' => ''],
        ];
    }

    $parent_profile = function_exists('aidunite_parent_read_guardian_profile_fields')
        ? aidunite_parent_read_guardian_profile_fields($parent_user_id)
        : [];
    aidunite_parent_persist_approval_log_entry($team_id, [
        'action' => 'rejected',
        'parent_user_id' => $parent_user_id,
        'actor_user_id' => $current_user_id,
        'invite_type' => (string) ($parent_profile['guardian_invite_type'] ?? ''),
    ]);

    clean_user_cache($parent_user_id);

    return [
        'ok' => true,
        'redirect' => home_url('/team-members?list_tab=parents&parent_tab=pending&rejected=1&team_id=' . $team_id),
        'errors' => [],
        'flash' => ['status' => 'rejected', 'message' => '参加申請を却下しました。'],
    ];
}

add_action('aidunite_registration_accepted', 'aidunite_parent_on_registration_accepted', 10, 1);
