<?php
/*====================================================================
  マッチ関連通知機能統合：match-notifications.php
====================================================================*/

/**
 * マッチ申請通知を送信（統合版）
 */
function send_match_request_notification($match_request_id, $target_schedule_id, $from_team_id) {
    try {
        // 申請先チームのIDを取得
        $to_team_id = get_post_meta($target_schedule_id, 'team_id', true);
        if (!$to_team_id) {
            error_log("❌ 通知送信失敗：申請先チームIDが取得できません schedule_id={$target_schedule_id}");
            return false;
        }

        // 申請元チーム名を取得
        $from_team_name = get_the_title($from_team_id);
        if (!$from_team_name) {
            $from_team_name = "チームID: {$from_team_id}";
        }

        // スケジュール情報を取得
        $schedule_date = get_post_meta($target_schedule_id, 'schedule_date', true);
        // Phase 2: 統一メタキーを優先、後方互換性のために旧キーもフォールバック
        $schedule_place = get_post_meta($target_schedule_id, 'schedule_place', true);
        if (empty($schedule_place)) {
            $schedule_place = get_post_meta($target_schedule_id, 'schedule_place_option', true);
        }

        // 申請先チームの代表者を取得
        $team_leaders = get_team_leaders($to_team_id);

        if (empty($team_leaders)) {
            error_log("❌ 通知送信失敗：申請先チームの代表者が見つかりません team_id={$to_team_id}");
            return false;
        }

        // 各代表者に通知を送信
        $success_count = 0;
        foreach ($team_leaders as $leader_id) {
            $notification_data = [
                'user_id' => $leader_id,
                'type' => 'match_request',
                'title' => '【マッチ申請】' . $from_team_name . ' からマッチ申請が届きました',
                'message' => $from_team_name . ' から練習試合の申請が届きました' . "\n\n" .
                            '会場: ' . $schedule_place . "\n\n" .
                            '内容を確認し、承認または拒否を行ってください' . "\n" .
                            '→ マイページ ＞ マッチ申請一覧',
                'data' => [
                    'match_request_id' => $match_request_id,
                    'from_team_id' => $from_team_id,
                    'to_team_id' => $to_team_id,
                    'schedule_id' => $target_schedule_id,
                    'schedule_date' => $schedule_date
                ]
            ];

            $result = aidunite_create_notification($notification_data);
            if ($result) {
                $success_count++;
            }
        }

        error_log("📨 マッチ申請通知送信完了：成功={$success_count}/" . count($team_leaders) . " team_id={$to_team_id}");
        return $success_count > 0;

    } catch (Exception $e) {
        error_log("❌ マッチ申請通知送信エラー：" . $e->getMessage());
        return false;
    }
}

/**
 * 申請受信通知を送信（統合版）
 */
function send_match_request_received_notification($request_id, $target_team_id, $from_team_id) {
    if (function_exists('aidunite_onboarding_bot_maybe_suppress_notification')
        && aidunite_onboarding_bot_maybe_suppress_notification((int) $request_id)) {
        return false;
    }

    // 申請受信チームのリーダーを取得
    $team_leaders = get_team_leaders($target_team_id);

    if (empty($team_leaders)) {
        return false;
    }

    // 申請元チーム名を取得
    $from_team_name = get_the_title($from_team_id);
    if (empty($from_team_name)) {
        $from_team_name = "チームID: {$from_team_id}";
    }

    $to_schedule_id = get_post_meta($request_id, 'to_schedule_id', true);

    // 各リーダーに通知を送信
    $success_count = 0;
    foreach ($team_leaders as $leader_id) {
        $notification_data = [
            'user_id' => $leader_id,
            'type' => 'match_request_received',
            'title' => '新しいマッチ申請が届きました',
            'message' => "{$from_team_name}からマッチ申請が届きました。",
            'priority' => 'high',
            'channels' => ['email', 'push'],
            'meta' => [
                'request_id' => $request_id,
                'from_team_id' => $from_team_id,
                'to_schedule_id' => $to_schedule_id
            ]
        ];

        // 通知を作成
        if (function_exists('aidunite_create_notification')) {
            $result = aidunite_create_notification($notification_data);
            if ($result) {
                $success_count++;
            }
        }
    }

    error_log("📨 申請受信通知送信完了：成功={$success_count}/" . count($team_leaders) . " team_id={$target_team_id}");
    return $success_count > 0;
}

/**
 * マッチ承認通知を送信（統合版）
 */
function send_match_approval_notification($request_id) {
    if (function_exists('aidunite_onboarding_bot_maybe_suppress_notification')
        && aidunite_onboarding_bot_maybe_suppress_notification((int) $request_id)) {
        return false;
    }

    try {
        $from_team_id = get_post_meta($request_id, 'from_team_id', true);
        $to_schedule_id = get_post_meta($request_id, 'to_schedule_id', true);

        if (!$from_team_id || !$to_schedule_id) {
            return false;
        }

        // 申請元チームの代表者を取得
        $team_leaders = get_team_leaders($from_team_id);

        if (empty($team_leaders)) {
            return false;
        }

        // スケジュール情報を取得
        $schedule_date = get_post_meta($to_schedule_id, 'schedule_date', true);
        $schedule_place = get_post_meta($to_schedule_id, 'schedule_place_option', true) ?:
                         get_post_meta($to_schedule_id, 'schedule_place', true);

        $to_team_name = get_the_title(get_post_meta($to_schedule_id, 'team_id', true));

        // 各代表者に通知を送信
        $success_count = 0;
        foreach ($team_leaders as $leader_id) {
            $notification_data = [
                'user_id' => $leader_id,
                'type' => 'match_established',
                'title' => '【マッチ成立】申請が承認されました！',
                'message' => '申請した練習試合が承認され、マッチが成立しました！' . "\n\n" .
                            '会場: ' . $schedule_place . "\n" .
                            '対戦相手: ' . $to_team_name . "\n\n" .
                            '詳細はマイページからご確認ください。',
                'data' => [
                    'match_request_id' => $request_id,
                    'from_team_id' => $from_team_id,
                    'to_team_id' => get_post_meta($to_schedule_id, 'team_id', true),
                    'schedule_id' => $to_schedule_id,
                    'schedule_date' => $schedule_date
                ]
            ];

            $result = aidunite_create_notification($notification_data);
            if ($result) {
                $success_count++;
            }
        }

        error_log("📨 マッチ承認通知送信完了：成功={$success_count}/" . count($team_leaders) . " team_id={$from_team_id}");
        return $success_count > 0;

    } catch (Exception $e) {
        error_log("❌ マッチ承認通知送信エラー：" . $e->getMessage());
        return false;
    }
}

/**
 * 試合終了後のアンケート依頼通知（両チーム代表者）
 */
function send_match_feedback_survey_notifications($match_request_id) {
    try {
        $match_request_id = (int) $match_request_id;
        if ($match_request_id <= 0) {
            return false;
        }
        if (get_post_meta($match_request_id, 'feedback_survey_notification_sent', true)) {
            return false;
        }

        $from_team_id = (int) get_post_meta($match_request_id, 'from_team_id', true);
        $to_team_id = (int) get_post_meta($match_request_id, 'to_team_id', true);
        if ($to_team_id <= 0) {
            $to_schedule_id = (int) get_post_meta($match_request_id, 'to_schedule_id', true);
            if ($to_schedule_id > 0) {
                $to_team_id = (int) get_post_meta($to_schedule_id, 'team_id', true);
            }
        }
        if ($from_team_id <= 0 || $to_team_id <= 0) {
            return false;
        }

        $from_team_name = get_the_title($from_team_id);
        $to_team_name = get_the_title($to_team_id);
        $survey_url = function_exists('aidunite_get_match_feedback_survey_url')
            ? aidunite_get_match_feedback_survey_url($match_request_id)
            : home_url('/match-feedback/?match_id=' . $match_request_id);

        $message = '試合お疲れさまでした。' . "\n\n"
            . $from_team_name . ' vs ' . $to_team_name . ' の試合について、'
            . 'アンケートへのご協力をお願いします。' . "\n\n"
            . '通知一覧または対戦チャットからも回答できます。';

        $team_ids = array_unique([$from_team_id, $to_team_id]);
        $success_count = 0;
        foreach ($team_ids as $team_id) {
            $leaders = get_team_leaders((int) $team_id);
            if (empty($leaders)) {
                continue;
            }
            foreach ($leaders as $leader_id) {
                $notification_data = [
                    'user_id'  => (int) $leader_id,
                    'type'     => 'match_feedback_survey',
                    'title'    => '【試合完了】アンケートのお願い',
                    'message'  => $message,
                    'link_url' => $survey_url,
                    'data'     => [
                        'match_request_id' => $match_request_id,
                        'request_id'       => $match_request_id,
                    ],
                ];
                if (aidunite_create_notification($notification_data)) {
                    $success_count++;
                }
            }
        }

        if ($success_count > 0) {
            update_post_meta($match_request_id, 'feedback_survey_notification_sent', true);
            update_post_meta($match_request_id, 'feedback_survey_notification_sent_at', current_time('mysql'));
        }

        return $success_count > 0;
    } catch (Exception $e) {
        error_log('❌ 試合後アンケート通知送信エラー: ' . $e->getMessage());

        return false;
    }
}

/**
 * マッチ拒否通知を送信（統合版）
 */
function send_match_rejection_notification($request_id) {
    try {
        $from_team_id = get_post_meta($request_id, 'from_team_id', true);
        $to_schedule_id = get_post_meta($request_id, 'to_schedule_id', true);

        if (!$from_team_id || !$to_schedule_id) {
            return false;
        }

        // 申請元チームの代表者を取得
        $team_leaders = get_team_leaders($from_team_id);

        if (empty($team_leaders)) {
            return false;
        }

        $schedule_date = get_post_meta($to_schedule_id, 'schedule_date', true);
        $to_team_name = get_the_title(get_post_meta($to_schedule_id, 'team_id', true));

        // 各代表者に通知を送信
        $success_count = 0;
        foreach ($team_leaders as $leader_id) {
            $notification_data = [
                'user_id' => $leader_id,
                'type' => 'match_rejected',
                'title' => '【マッチ結果】申請が拒否されました',
                'message' => '申請した練習試合が拒否されました' . "\n\n" .
                            '対戦相手: ' . $to_team_name . "\n\n" .
                            '内容をご確認ください。',
                'data' => [
                    'match_request_id' => $request_id,
                    'from_team_id' => $from_team_id,
                    'to_team_id' => get_post_meta($to_schedule_id, 'team_id', true),
                    'schedule_id' => $to_schedule_id,
                    'schedule_date' => $schedule_date
                ]
            ];

            $result = aidunite_create_notification($notification_data);
            if ($result) {
                $success_count++;
            }
        }

        error_log("📨 マッチ拒否通知送信完了：成功={$success_count}/" . count($team_leaders) . " team_id={$from_team_id}");
        return $success_count > 0;

    } catch (Exception $e) {
        error_log("❌ マッチ拒否通知送信エラー：" . $e->getMessage());
        return false;
    }
}

/**
 * マッチキャンセル通知を送信（統合版）
 */
function send_match_cancellation_notification($request_id) {
    $from_team_id = get_post_meta($request_id, 'from_team_id', true);
    $to_schedule_id = get_post_meta($request_id, 'to_schedule_id', true);
    $to_team_id = get_post_meta($to_schedule_id, 'team_id', true);

    if (!$from_team_id || !$to_team_id) {
        return false;
    }

    $from_team_name = get_the_title($from_team_id);
    $schedule_date = get_post_meta($to_schedule_id, 'schedule_date', true);

    // 申請先チームのリーダーを取得
    $team_leaders = get_team_leaders($to_team_id);

    $success_count = 0;
    try {
        if (!empty($team_leaders)) {
            foreach ($team_leaders as $leader_id) {
                $notification_data = [
                    'user_id' => $leader_id,
                    'type' => 'match_canceled',
                    'title' => '【マッチ結果】申請がキャンセルされました',
                    'message' => '申請された練習試合がキャンセルされました' . "\n\n" .
                                '申請チーム: ' . $from_team_name . "\n\n" .
                                '内容をご確認ください。',
                    'data' => [
                        'match_request_id' => $request_id,
                        'from_team_id' => $from_team_id,
                        'to_team_id' => $to_team_id,
                        'schedule_id' => $to_schedule_id,
                        'schedule_date' => $schedule_date
                    ]
                ];

                $result = aidunite_create_notification($notification_data);
                if ($result) {
                    $success_count++;
                }
            }

            error_log("📨 マッチキャンセル通知送信完了：成功={$success_count}/" . count($team_leaders) . " team_id={$to_team_id}");
            return $success_count > 0;
        }

    } catch (Exception $e) {
        error_log("❌ マッチキャンセル通知送信エラー：" . $e->getMessage());
        return false;
    }
}

/**
 * 同一申請元・同一募集への重複 MR が、別件の試合確定により自動キャンセルされたときの通知。
 * 申請元チーム代表者と募集側（主催）チーム代表者へ送る（同一ユーザーは1通に集約）。
 *
 * @param int $canceled_request_id 自動キャンセルした MR 投稿ID
 * @param int $winner_request_id   試合確定した MR 投稿ID
 * @return bool 1件以上送信できれば true
 */
function send_match_request_superseded_notifications($canceled_request_id, $winner_request_id) {
    try {
        $canceled_request_id = (int) $canceled_request_id;
        $winner_request_id   = (int) $winner_request_id;
        if ($canceled_request_id <= 0 || $winner_request_id <= 0) {
            return false;
        }

        $from_team_id   = (int) get_post_meta($canceled_request_id, 'from_team_id', true);
        $to_schedule_id = (int) get_post_meta($canceled_request_id, 'to_schedule_id', true);
        if ($from_team_id <= 0 || $to_schedule_id <= 0) {
            return false;
        }

        $host_team_id = (int) get_post_meta($to_schedule_id, 'team_id', true);
        $from_team_name = get_the_title($from_team_id);
        if ($from_team_name === '') {
            $from_team_name = 'チームID: ' . $from_team_id;
        }

        $applicant_leaders = function_exists('get_team_leaders') ? get_team_leaders($from_team_id) : [];
        $host_leaders      = ($host_team_id > 0 && function_exists('get_team_leaders')) ? get_team_leaders($host_team_id) : [];

        $title_applicant = '【マッチ結果】申請が自動キャンセルされました';
        $msg_applicant   = '別の申請が試合確定したため、この申請は自動キャンセルされました。' . "\n\n"
            . 'マッチ詳細で状態をご確認ください。';

        $title_host = '【マッチ結果】重複申請が自動整理されました';
        $msg_host   = $from_team_name . ' から同一募集への重複申請のうち、試合確定に伴い未確定分を自動キャンセルしました。' . "\n\n"
            . 'マッチ詳細でご確認ください。';

        $sent = [];
        $success = 0;

        foreach ($applicant_leaders as $uid) {
            $uid = (int) $uid;
            if ($uid <= 0 || isset($sent[$uid])) {
                continue;
            }
            $sent[$uid] = true;
            if (!function_exists('aidunite_create_notification')) {
                continue;
            }
            if (aidunite_create_notification([
                'user_id' => $uid,
                'type'    => 'match_request_superseded',
                'title'   => $title_applicant,
                'message' => $msg_applicant,
                'data'    => [
                    'match_request_id'        => $canceled_request_id,
                    'superseded_by_request_id' => $winner_request_id,
                    'from_team_id'            => $from_team_id,
                    'to_schedule_id'          => $to_schedule_id,
                ],
            ])) {
                $success++;
            }
        }

        foreach ($host_leaders as $uid) {
            $uid = (int) $uid;
            if ($uid <= 0 || isset($sent[$uid])) {
                continue;
            }
            $sent[$uid] = true;
            if (!function_exists('aidunite_create_notification')) {
                continue;
            }
            if (aidunite_create_notification([
                'user_id' => $uid,
                'type'    => 'match_request_superseded',
                'title'   => $title_host,
                'message' => $msg_host,
                'data'    => [
                    'match_request_id'        => $canceled_request_id,
                    'superseded_by_request_id' => $winner_request_id,
                    'from_team_id'            => $from_team_id,
                    'to_schedule_id'          => $to_schedule_id,
                ],
            ])) {
                $success++;
            }
        }

        error_log('📨 match_request_superseded 送信: success=' . $success . ' canceled_id=' . $canceled_request_id . ' winner_id=' . $winner_request_id);
        return $success > 0;
    } catch (Exception $e) {
        error_log('❌ match_request_superseded 通知エラー: ' . $e->getMessage());
        return false;
    }
}

/**
 * 逆方向MRが「別方向の成立」により自動キャンセルされた通知を申請元代表者へ送信。
 *
 * @param int $canceled_request_id 自動キャンセルされた MR
 * @param int $winner_request_id   成立した反対方向 MR
 * @return bool
 */
function send_match_request_mirror_established_notification($canceled_request_id, $winner_request_id) {
    $canceled_request_id = (int) $canceled_request_id;
    $winner_request_id = (int) $winner_request_id;
    if ($canceled_request_id <= 0 || $winner_request_id <= 0) {
        return false;
    }

    $from_team_id = (int) get_post_meta($canceled_request_id, 'from_team_id', true);
    $to_schedule_id = (int) get_post_meta($canceled_request_id, 'to_schedule_id', true);
    if ($from_team_id <= 0 || $to_schedule_id <= 0) {
        return false;
    }

    $leaders = function_exists('get_team_leaders') ? get_team_leaders($from_team_id) : [];
    if (empty($leaders)) {
        return false;
    }

    $success = 0;
    foreach ($leaders as $uid) {
        $uid = (int) $uid;
        if ($uid <= 0) {
            continue;
        }
        if (!function_exists('aidunite_create_notification')) {
            continue;
        }
        $ok = aidunite_create_notification([
            'user_id' => $uid,
            'type'    => 'match_request_superseded',
            'title'   => '【マッチ結果】申請が自動キャンセルされました',
            'message' => '別方向の申請が成立したため、この申請は自動キャンセルされました。' . "\n\n"
                . 'マッチ詳細で状態をご確認ください。',
            'data'    => [
                'match_request_id'         => $canceled_request_id,
                'superseded_by_request_id' => $winner_request_id,
                'from_team_id'             => $from_team_id,
                'to_schedule_id'           => $to_schedule_id,
            ],
        ]);
        if ($ok) {
            $success++;
        }
    }

    error_log('📨 mirror_established 通知: success=' . $success . ' canceled_id=' . $canceled_request_id . ' winner_id=' . $winner_request_id);
    return $success > 0;
}

/**
 * 募集条件変更により再確認が必要になった MR の申請元代表者へ通知する。
 *
 * @param int    $request_id
 * @param int    $winner_request_id
 * @param string $reason
 * @return bool
 */
function send_match_request_reconfirm_required_notification($request_id, $winner_request_id, $reason = 'schedule_condition_changed') {
    $request_id = (int) $request_id;
    $winner_request_id = (int) $winner_request_id;
    if ($request_id <= 0) {
        return false;
    }
    $from_team_id = (int) get_post_meta($request_id, 'from_team_id', true);
    if ($from_team_id <= 0 || !function_exists('get_team_leaders')) {
        return false;
    }
    $leaders = get_team_leaders($from_team_id);
    if (empty($leaders)) {
        return false;
    }
    $to_schedule_id = (int) get_post_meta($request_id, 'to_schedule_id', true);

    $message = '募集条件が変更されたため、申請内容の再確認が必要です。現在の条件で再申請をご検討ください。' . "\n\n"
        . 'マッチ詳細で変更内容をご確認ください。';

    $success = 0;
    foreach ($leaders as $uid) {
        $uid = (int) $uid;
        if ($uid <= 0 || !function_exists('aidunite_create_notification')) {
            continue;
        }
        $ok = aidunite_create_notification([
            'user_id' => $uid,
            'type'    => 'match_request_reconfirm_required',
            'title'   => '【マッチ申請】募集条件変更のため再確認が必要です',
            'message' => $message,
            'data'    => [
                'match_request_id' => $request_id,
                'winner_request_id' => $winner_request_id,
                'reconfirm_reason' => (string) $reason,
                'to_schedule_id' => $to_schedule_id,
            ],
        ]);
        if ($ok) {
            $success++;
        }
    }
    error_log('📨 reconfirm_required 通知: success=' . $success . ' request_id=' . $request_id . ' winner=' . $winner_request_id);
    return $success > 0;
}

/**
 * 募集側が再提示したことを申請側へ通知する。
 */
function send_match_request_reconfirm_proposed_notification($request_id, $proposal_team_id = 0) {
    $request_id = (int) $request_id;
    $proposal_team_id = (int) $proposal_team_id;
    if ($request_id <= 0 || !function_exists('get_team_leaders') || !function_exists('aidunite_create_notification')) {
        return false;
    }
    $from_team_id = (int) get_post_meta($request_id, 'from_team_id', true);
    if ($from_team_id <= 0) {
        return false;
    }
    $leaders = get_team_leaders($from_team_id);
    if (empty($leaders)) {
        return false;
    }
    $message = '募集側から最新条件の提案が届きました。承諾すると承認可能状態に戻ります。';
    $success = 0;
    foreach ($leaders as $uid) {
        $ok = aidunite_create_notification([
            'user_id' => (int) $uid,
            'type'    => 'match_request_reconfirm_proposed',
            'title'   => '【マッチ申請】最新条件の提案が届きました',
            'message' => $message,
            'data'    => [
                'match_request_id' => $request_id,
                'proposal_by_team_id' => $proposal_team_id,
            ],
        ]);
        if ($ok) {
            $success++;
        }
    }
    return $success > 0;
}

/**
 * 申請側が再提示を承諾したことを募集側へ通知する。
 */
function send_match_request_reconfirm_accepted_notification($request_id, $accepted_team_id = 0) {
    $request_id = (int) $request_id;
    $accepted_team_id = (int) $accepted_team_id;
    if ($request_id <= 0 || !function_exists('get_team_leaders') || !function_exists('aidunite_create_notification')) {
        return false;
    }
    $to_schedule_id = (int) get_post_meta($request_id, 'to_schedule_id', true);
    $to_team_id = (int) get_post_meta($to_schedule_id, 'team_id', true);
    if ($to_team_id <= 0) {
        return false;
    }
    $leaders = get_team_leaders($to_team_id);
    if (empty($leaders)) {
        return false;
    }
    $message = '相手チームが最新条件の提案を承諾しました。承認操作へ進めます。';
    $success = 0;
    foreach ($leaders as $uid) {
        $ok = aidunite_create_notification([
            'user_id' => (int) $uid,
            'type'    => 'match_request_reconfirm_accepted',
            'title'   => '【マッチ申請】相手チームが提案を承諾しました',
            'message' => $message,
            'data'    => [
                'match_request_id' => $request_id,
                'accepted_by_team_id' => $accepted_team_id,
            ],
        ]);
        if ($ok) {
            $success++;
        }
    }
    return $success > 0;
}

/**
 * 主催によるゲーム全体解散通知（仕様: match_game_dissolved）
 * MR 単位の match_canceled は送らず、参加していた全チームの代表者へ1タイプで通知する。
 *
 * @param int               $match_game_id anchor schedule ID（ゲームの箱）
 * @param WP_Post[]         $canceled_posts       いま解散で canceled にした match_request 投稿（事前収集）
 * @param int               $actor_team_id        実行した主催チーム ID
 * @return bool 1件以上送信できれば true
 */
function send_match_game_dissolved_notification($match_game_id, array $canceled_posts, $actor_team_id) {
    $match_game_id = (int) $match_game_id;
    $actor_team_id = (int) $actor_team_id;
    if ($match_game_id <= 0 || $canceled_posts === []) {
        return false;
    }

    if (function_exists('aidunite_resolve_schedule_owner_team_id')) {
        $host_team_id = (int) aidunite_resolve_schedule_owner_team_id($match_game_id);
    } else {
        $host_team_id = (int) get_post_meta($match_game_id, 'team_id', true);
        if ($host_team_id <= 0) {
            $author_id = (int) get_post_field('post_author', $match_game_id);
            if ($author_id > 0) {
                $host_team_id = (int) get_user_meta($author_id, 'team_id', true);
            }
        }
    }
    $host_name = $host_team_id ? get_the_title($host_team_id) : '主催チーム';

    $team_ids = [];
    if ($host_team_id > 0) {
        $team_ids[$host_team_id] = true;
    }
    foreach ($canceled_posts as $p) {
        if (!$p instanceof WP_Post) {
            continue;
        }
        $fid = (int) get_post_meta($p->ID, 'from_team_id', true);
        $tid = (int) get_post_meta($p->ID, 'to_team_id', true);
        if ($fid > 0) {
            $team_ids[$fid] = true;
        }
        if ($tid > 0) {
            $team_ids[$tid] = true;
        }
    }

    $mr_ids        = array_map(
        static function ($post) {
            return $post instanceof WP_Post ? (int) $post->ID : 0;
        },
        $canceled_posts
    );
    $mr_ids        = array_values(array_filter(array_unique($mr_ids)));

    $title = '【試合】主催によりゲームが解散されました';
    $body  = $host_name . '（主催）により、この募集に紐づく試合（ゲーム）が解散されました。' . "\n\n"
        . '再びマッチングする場合は、マッチ掲示板やスケジュールから募集・申請を行ってください。';

    $link_url = home_url('/match-board-own');

    $leader_sent = [];
    $success     = 0;
    foreach (array_keys($team_ids) as $team_id) {
        $team_id = (int) $team_id;
        if ($team_id <= 0) {
            continue;
        }
        $leaders = function_exists('get_team_leaders') ? get_team_leaders($team_id) : [];
        foreach ($leaders as $leader_id) {
            $leader_id = (int) $leader_id;
            if ($leader_id <= 0 || isset($leader_sent[$leader_id])) {
                continue;
            }
            $leader_sent[$leader_id] = true;

            if (!function_exists('aidunite_notification_send')) {
                continue;
            }
            $related_for_notice = !empty($mr_ids) ? (int) $mr_ids[0] : 0;
            $result = aidunite_notification_send($leader_id, 'match_game_dissolved', [
                'title'       => $title,
                'message'     => $body,
                'related_id'  => $related_for_notice,
                'link_url'    => $link_url,
            ]);
            if (!empty($result['success'])) {
                $success++;
            }
        }
    }

    error_log('📨 match_game_dissolved 送信完了: success=' . $success . ' match_game_id=' . $match_game_id . ' mr_ids=' . implode(',', $mr_ids));
    return $success > 0;
}

/**
 * 主催以外の参加ペアのみキャンセル（共有ゲーム継続）時の通知（仕様: match_participant_withdrawn）
 *
 * @param int $request_id    キャンセルした match_request ID
 * @param int $actor_team_id 操作したチーム ID
 * @return bool 1件以上送信できれば true
 */
function send_match_participant_withdrawn_notification($request_id, $actor_team_id) {
    $request_id    = (int) $request_id;
    $actor_team_id = (int) $actor_team_id;
    if ($request_id <= 0 || !function_exists('aidunite_notification_send')) {
        return false;
    }

    $match_game_id = function_exists('aidunite_resolve_match_game_id_for_match_request')
        ? (int) aidunite_resolve_match_game_id_for_match_request($request_id)
        : (int) get_post_meta($request_id, 'to_schedule_id', true);
    if ($match_game_id <= 0 || $match_game_id === 9999) {
        return false;
    }

    if (
        function_exists('aidunite_count_game_established_match_requests')
        && aidunite_count_game_established_match_requests($match_game_id, $request_id) <= 0
    ) {
        return false;
    }

    $canceled_team_id = function_exists('aidunite_resolve_withdrawn_team_id_for_canceled_match_request')
        ? (int) aidunite_resolve_withdrawn_team_id_for_canceled_match_request($request_id, $actor_team_id)
        : (int) get_post_meta($request_id, 'from_team_id', true);
    if ($canceled_team_id <= 0) {
        $canceled_team_id = (int) get_post_meta($request_id, 'canceled_by_team_id', true);
    }
    $canceled_name = $canceled_team_id > 0
        ? (function_exists('aidunite_get_team_name') ? aidunite_get_team_name($canceled_team_id) : get_the_title($canceled_team_id))
        : '';
    if ($canceled_name === '') {
        $canceled_name = '相手チーム';
    }

    if (function_exists('aidunite_resolve_schedule_owner_team_id')) {
        $host_team_id = (int) aidunite_resolve_schedule_owner_team_id($match_game_id);
    } else {
        $host_team_id = (int) get_post_meta($match_game_id, 'team_id', true);
    }

    $team_ids = [];
    if ($host_team_id > 0) {
        $team_ids[$host_team_id] = true;
    }
    if (function_exists('aidunite_get_game_match_requests')) {
        foreach (aidunite_get_game_match_requests($match_game_id) as $req) {
            $st = function_exists('aidunite_normalize_match_request_status')
                ? aidunite_normalize_match_request_status((string) get_post_meta($req->ID, 'status', true), isset($req->post_status) ? (string) $req->post_status : '')
                : (string) get_post_meta($req->ID, 'status', true);
            if ($st !== 'established' || (int) $req->ID === $request_id) {
                continue;
            }
            $fid = (int) get_post_meta($req->ID, 'from_team_id', true);
            $tid = (int) get_post_meta($req->ID, 'to_team_id', true);
            if ($fid > 0) {
                $team_ids[$fid] = true;
            }
            if ($tid > 0) {
                $team_ids[$tid] = true;
            }
        }
    }
    if ($canceled_team_id > 0) {
        $team_ids[$canceled_team_id] = true;
    }

    $link_url = home_url('/match-board-own');
    if (function_exists('aidunite_get_active_chat_room_for_match_game')) {
        $room = aidunite_get_active_chat_room_for_match_game($match_game_id);
        if ($room && !empty($room->id)) {
            $link_url = home_url('/chat?room_id=' . (int) $room->id);
        }
    }

    $remaining_count = function_exists('aidunite_count_game_established_match_requests')
        ? (int) aidunite_count_game_established_match_requests($match_game_id, 0)
        : 0;

    $leader_sent = [];
    $success     = 0;
    foreach (array_keys($team_ids) as $team_id) {
        $team_id = (int) $team_id;
        if ($team_id <= 0) {
            continue;
        }
        $leaders = function_exists('get_team_leaders') ? get_team_leaders($team_id) : [];
        foreach ($leaders as $leader_id) {
            $leader_id = (int) $leader_id;
            if ($leader_id <= 0 || isset($leader_sent[$leader_id])) {
                continue;
            }
            $leader_sent[$leader_id] = true;

            if ($team_id === $canceled_team_id) {
                $title = '【試合】試合確定をキャンセルしました';
                $body  = 'あなたのチームは試合確定をキャンセルしました。' . "\n"
                    . '他チームとのゲームは継続中です（現在 ' . max(0, $remaining_count) . ' 件の成立が残っています）。';
            } else {
                $title = '【試合】' . $canceled_name . 'の試合キャンセルがありました';
                $body  = $canceled_name . 'チームの試合キャンセルがありました。' . "\n"
                    . 'ゲームは継続中です（現在 ' . max(0, $remaining_count) . ' 件の成立が残っています）。' . "\n\n"
                    . '共有チャットでご確認ください。';
            }

            $result = aidunite_notification_send($leader_id, 'match_participant_withdrawn', [
                'title'      => $title,
                'message'    => $body,
                'related_id' => $recruit_sid,
                'link_url'   => $link_url,
            ]);
            if (!empty($result['success'])) {
                $success++;
            }
        }
    }

    error_log('📨 match_participant_withdrawn 送信完了: success=' . $success . ' request_id=' . $request_id . ' canceled_team=' . $canceled_team_id);
    return $success > 0;
}

/**
 * キャンセル通知の振り分け（共有ゲーム継続 vs 単一ペア）
 *
 * @param int $request_id
 * @param int $actor_team_id
 * @return bool
 */
function aidunite_notify_match_request_canceled($request_id, $actor_team_id) {
    $request_id    = (int) $request_id;
    $actor_team_id = (int) $actor_team_id;
    if ($request_id <= 0) {
        return false;
    }

    $was_established = (get_post_meta($request_id, 'established_at', true) !== '');
    $match_game_id     = function_exists('aidunite_resolve_match_game_id_for_match_request')
        ? (int) aidunite_resolve_match_game_id_for_match_request($request_id)
        : (int) get_post_meta($request_id, 'to_schedule_id', true);
    $other_established = ($match_game_id > 0 && $match_game_id !== 9999 && function_exists('aidunite_count_game_established_match_requests'))
        ? (int) aidunite_count_game_established_match_requests($match_game_id, $request_id)
        : 0;

    if ($was_established && $other_established > 0 && function_exists('send_match_participant_withdrawn_notification')) {
        return send_match_participant_withdrawn_notification($request_id, $actor_team_id);
    }

    if (function_exists('send_match_cancellation_notification')) {
        return send_match_cancellation_notification($request_id);
    }

    return false;
}

/**
 * REST update-match-status などからの通知フック。
 * 承認→試合確定の通知は aidunite_after_match_established に集約（二重送信防止）。
 */
if (!function_exists('aidunite_handle_match_status_change')) {
    function aidunite_handle_match_status_change($match_id, $action, $user_id = 0, $new_status = '', $extra = '') {
        $match_id = (int) $match_id;
        if ($match_id <= 0) {
            return;
        }
        $action = (string) $action;
        if ($action === 'approve') {
            return;
        }
        if ($action === 'reject' && function_exists('send_match_rejection_notification')) {
            send_match_rejection_notification($match_id);
        }
    }
}
