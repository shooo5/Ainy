<?php
/**
 * 通知設定統一管理システム
 * 全ての通知テンプレートと設定を一元管理
 *
 * 責務分離:
 * - 本ファイルのテンプレート（get_notification_templates）、priority、channels、delay 等は
 *   サーバー側・キュー・将来拡張のための内部設計であり、ユーザー向け画面には列挙しない。
 * - 一般ユーザー向けの編集 UI は page-notification-settings.php（メール ON/OFF のみ操作可能）。
 */

class AidUniteNotificationConfig {

    /**
     * 通知テンプレート設定
     */
    public static function get_notification_templates() {
        return [
            // マッチ関連通知
            'match_request' => [
                'title' => '【マッチ申請】{team_name} からマッチ申請が届きました',
                'message' => '{team_name} から練習試合の申請が届きました\n\n会場: {place}\n\n内容を確認し、承認または拒否を行ってください\n→ マイページ ＞ マッチ申請一覧',
                'priority' => 'high',
                'channels' => ['email', 'line', 'push'],
                'delay' => 0
            ],
            'match_established' => [
                'title' => '【マッチ成立】申請が承認されました！',
                'message' => '申請した練習試合が承認され、マッチが成立しました！\n\n会場: {place}\n\n詳細はマイページからご確認ください。',
                'priority' => 'high',
                'channels' => ['email', 'line', 'push'],
                'delay' => 0
            ],
            'match_rejected' => [
                'title' => '【マッチ結果】申請が拒否されました',
                'message' => '申請した練習試合が拒否されました\n\n内容をご確認ください。',
                'priority' => 'medium',
                'channels' => ['email', 'line'],
                'delay' => 0
            ],
            'high_match_found' => [
                'title' => '【高マッチ発見】新しいマッチ候補が見つかりました！',
                'message' => '新しいスケジュールが登録され、高マッチの候補が見つかりました！\n\nマッチ度: {match_level}\n会場: {place}\n\n詳細を確認して、マッチ申請を検討してください。\n→ マイページ ＞ おすすめ対戦',
                'priority' => 'high',
                'channels' => ['email', 'line', 'push'],
                'delay' => 0
            ],

            // システム通知
            'team_approval_request' => [
                'title' => '【承認依頼】チーム申請が届きました',
                'message' => '新しいチーム申請があります。\nチーム名: {team_name}\n申請者: {applicant_name}',
                'priority' => 'high',
                'channels' => ['email', 'line', 'push'],
                'delay' => 0
            ],
            'team_approval_completed' => [
                'title' => '【承認完了】チーム申請が承認されました',
                'message' => 'チーム申請が承認されました。\nチーム名: {team_name}',
                'priority' => 'medium',
                'channels' => ['email', 'line'],
                'delay' => 0
            ],
            'user_registration_completed' => [
                'title' => '【登録完了】AidUniteへご登録ありがとうございます',
                'message' => 'ご登録ありがとうございます。AidUniteのサービスをご利用いただけます。',
                'priority' => 'medium',
                'channels' => ['email'],
                'delay' => 0
            ],

            // 決済関連通知
            'payment_completed' => [
                'title' => '💰 月謝のお支払いが完了しました',
                'message' => '月謝の支払いが完了しました。\n金額: {amount}円\n支払い日: {date}',
                'priority' => 'high',
                'channels' => ['email', 'line'],
                'delay' => 0
            ],
            'payment_reminder' => [
                'title' => '💰 【ご案内】月謝のお支払い期限が近づいています',
                'message' => 'お支払い期限: {due_date} が近づいています。ご確認ください。',
                'priority' => 'high',
                'channels' => ['email', 'line', 'push'],
                'delay' => 0
            ],
            'receipt_issued' => [
                'title' => '📄 領収書が発行されました',
                'message' => '領収書が発行されました。\n領収書番号: {receipt_no}\n発行日: {date}',
                'priority' => 'low',
                'channels' => ['email'],
                'delay' => 300 // 5分後送信
            ],

            // 出席関連通知
            'attendance_registered' => [
                'title' => '出欠登録が完了しました',
                'message' => '出欠の登録が完了しました。\n対象イベント: {event_name}\n日時: {event_date}',
                'priority' => 'low',
                'channels' => ['email'],
                'delay' => 60 // 1分後送信
            ],
            'attendance_reminder' => [
                'title' => '【リマインド】出欠登録のご案内',
                'message' => '出欠登録のご案内です。\n対象イベント: {event_name}\n日時: {event_date}\n期限: {reminder_deadline}',
                'priority' => 'medium',
                'channels' => ['email', 'line'],
                'delay' => 0
            ],

        ];
    }

    /**
     * マッチ度に応じたメッセージテンプレート
     */
    public static function get_match_score_templates() {
        return [
            '☆（100%）' => '✨即マッチOK！条件ピッタリの対戦候補です！',
            '〇（80%）' => '⭕高確率で良マッチ！条件はほぼ一致しています！',
            '◇（60%）' => '🔶条件調整が必要ですが、マッチの可能性があります。',
            '△（40%以下）' => '🔺条件に差があります。要相談です。'
        ];
    }

    /**
     * 会場役割に応じたメッセージテンプレート
     */
    public static function get_place_role_templates() {
        return [
            'home' => '⚠️ 会場のご用意をお願いいたします。',
            'away' => '🏃 お時間までに会場へお越しください。'
        ];
    }

    /**
     * 通知チャンネル設定
     */
    public static function get_channel_config() {
        return [
            'email' => [
                'enabled' => true,
                'from_name' => 'AidUnite',
                'from_email' => get_option('admin_email'),
                'template' => 'default'
            ],
            'line' => [
                'enabled' => true,
                'api_url' => 'https://notify-api.line.me/api/notify',
                'max_length' => 1000
            ],
            'push' => [
                'enabled' => false, // 実装後にtrue
                'vapid_public_key' => get_option('aidunite_vapid_public_key', ''),
                'vapid_private_key' => get_option('aidunite_vapid_private_key', ''),
                'icon' => home_url('/assets/images/notification-icon.png'),
                'badge' => home_url('/assets/images/notification-badge.png')
            ]
        ];
    }

    /**
     * 優先度別設定
     */
    public static function get_priority_config() {
        return [
            'high' => [
                'immediate' => true,
                'retry_count' => 3,
                'retry_interval' => 300, // 5分
                'batch_size' => 1 // 即座に個別送信
            ],
            'medium' => [
                'immediate' => false,
                'batch_interval' => 900, // 15分ごと
                'retry_count' => 2,
                'retry_interval' => 1800, // 30分
                'batch_size' => 10
            ],
            'low' => [
                'immediate' => false,
                'batch_interval' => 3600, // 1時間ごと
                'retry_count' => 1,
                'retry_interval' => 7200, // 2時間
                'batch_size' => 50
            ]
        ];
    }

    /**
     * ユーザー通知設定のデフォルト値
     */
    public static function get_user_default_settings() {
        return [
            'email_notifications' => true,
            'line_notifications' => false,
            'push_notifications' => true,
            'notification_types' => [
                'match_request' => true,
                'match_established' => true,
                'match_rejected' => true,
                'payment_completed' => true,
                'payment_reminder' => true,
                'team_approval_request' => true,
                'attendance_reminder' => true,
                'receipt_issued' => false // デフォルトはオフ
            ],
            'quiet_hours' => [
                'enabled' => true,
                'start' => '22:00',
                'end' => '08:00'
            ],
            'batch_digest' => [
                'enabled' => false,
                'frequency' => 'daily', // daily, weekly
                'time' => '09:00'
            ]
        ];
    }

    /**
     * 多言語対応テンプレート取得
     */
    public static function get_localized_template($type, $locale = 'ja') {
        $templates = self::get_notification_templates();
        $lookup = (string) $type;
        if (function_exists('aidunite_normalize_notification_type_value')) {
            $canonical = aidunite_normalize_notification_type_value($lookup);
            if ($canonical !== '') {
                $lookup = $canonical;
            }
        }

        if (!isset($templates[$lookup])) {
            return null;
        }

        $template = $templates[$lookup];

        // 将来的な多言語対応のための構造
        if ($locale !== 'ja') {
            // 他言語のテンプレートをデータベースから取得
            $localized = get_option("aidunite_notification_templates_{$locale}", []);
            if (isset($localized[$lookup])) {
                $template = array_merge($template, $localized[$lookup]);
            }
        }

        return $template;
    }

    /**
     * テンプレート変数の置換
     */
    public static function replace_template_variables($template, $variables) {
        $message = $template['message'];
        $title = $template['title'];

        foreach ($variables as $key => $value) {
            $message = str_replace('{' . $key . '}', $value, $message);
            $title = str_replace('{' . $key . '}', $value, $title);
        }

        return [
            'title' => $title,
            'message' => $message
        ];
    }
}

/**
 * 設定管理用のヘルパー関数
 */
function aidunite_get_notification_template($type, $variables = []) {
    $template = AidUniteNotificationConfig::get_localized_template($type);

    if (!$template) {
        return null;
    }

    if (!empty($variables)) {
        return AidUniteNotificationConfig::replace_template_variables($template, $variables);
    }

    return $template;
}

function aidunite_get_user_notification_settings($user_id) {
    $settings = get_user_meta($user_id, 'notification_settings', true);

    if (empty($settings)) {
        $settings = AidUniteNotificationConfig::get_user_default_settings();
        update_user_meta($user_id, 'notification_settings', $settings);
    }

    // legacy notification_types キーを canonical へ寄せる（読取互換）
    if (is_array($settings['notification_types'] ?? null)) {
        $types = $settings['notification_types'];
        $legacy_map = [
            'match_accepted' => 'match_established',
            'team_approved' => 'team_approval_completed',
        ];
        foreach ($legacy_map as $legacy => $canonical) {
            if (array_key_exists($legacy, $types) && !array_key_exists($canonical, $types)) {
                $types[$canonical] = $types[$legacy];
            }
        }
        $settings['notification_types'] = $types;
    }

    return $settings;
}
