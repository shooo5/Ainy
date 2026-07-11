<?php
/**
 * 統一日付・時間ユーティリティ
 * AidUnite Theme - Date Utils
 *
 * タイムゾーン、日付フォーマット、日付比較を統一するためのユーティリティクラス
 */

if (!defined('ABSPATH')) {
    exit;
}

class AidUniteDateUtils {
    const TIMEZONE = 'Asia/Tokyo';
    const DATE_FORMAT = 'Y-m-d';
    const DATETIME_FORMAT = 'Y-m-d H:i:s';
    const TIME_FORMAT = 'H:i';
    /** 画面表示用：yy/mm/dd（統一定義・Ainy-UI-Unified-Rules） */
    const DATE_DISPLAY_SHORT = 'y/m/d';

    /**
     * 現在の日時を取得（タイムゾーン対応）
     *
     * @param string $format 日時フォーマット
     * @return string フォーマットされた日時
     */
    public static function now($format = self::DATETIME_FORMAT) {
        $timezone = new DateTimeZone(self::TIMEZONE);
        $datetime = new DateTime('now', $timezone);
        return $datetime->format($format);
    }

    /**
     * 日付をフォーマット
     *
     * @param string|int $date 日付（文字列またはタイムスタンプ）
     * @param string $format フォーマット
     * @return string フォーマットされた日付
     */
    public static function formatDate($date, $format = self::DATE_FORMAT) {
        if (empty($date)) {
            return '';
        }

        $timezone = new DateTimeZone(self::TIMEZONE);

        if (is_numeric($date)) {
            $datetime = new DateTime('@' . $date, $timezone);
        } else {
            $datetime = new DateTime($date, $timezone);
        }

        return $datetime->format($format);
    }

    /**
     * 日付を画面表示用にフォーマット（統一定義：yy/mm/dd（曜） Ainy-UI-Unified-Rules）
     *
     * @param string|int $date 日付（Y-m-d またはタイムスタンプ）
     * @return string 例: "25/03/01（土）"
     */
    public static function formatDateForDisplay($date) {
        if (empty($date)) {
            return '';
        }
        $timezone = new DateTimeZone(self::TIMEZONE);
        if (is_numeric($date)) {
            $datetime = new DateTime('@' . $date, $timezone);
        } else {
            $datetime = new DateTime($date, $timezone);
        }
        $weekdays = ['日', '月', '火', '水', '木', '金', '土'];
        $w = $weekdays[(int) $datetime->format('w')];
        return $datetime->format(self::DATE_DISPLAY_SHORT) . '（' . $w . '）';
    }

    /**
     * 時間をフォーマット
     *
     * @param string $time 時間（HH:MM形式）
     * @param string $format フォーマット
     * @return string フォーマットされた時間
     */
    public static function formatTime($time, $format = self::TIME_FORMAT) {
        if (empty($time)) {
            return '';
        }

        $timezone = new DateTimeZone(self::TIMEZONE);
        $datetime = new DateTime($time, $timezone);
        return $datetime->format($format);
    }

    /**
     * 日付を比較
     *
     * @param string $date1 日付1
     * @param string $date2 日付2
     * @return int -1: date1 < date2, 0: date1 == date2, 1: date1 > date2
     */
    public static function compareDate($date1, $date2) {
        $timezone = new DateTimeZone(self::TIMEZONE);
        $dt1 = new DateTime($date1, $timezone);
        $dt2 = new DateTime($date2, $timezone);

        if ($dt1 < $dt2) return -1;
        if ($dt1 > $dt2) return 1;
        return 0;
    }

    /**
     * 日付が有効かチェック
     *
     * @param string $date 日付
     * @param string $format フォーマット
     * @return bool
     */
    public static function isValidDate($date, $format = self::DATE_FORMAT) {
        if (empty($date)) {
            return false;
        }

        $timezone = new DateTimeZone(self::TIMEZONE);
        $datetime = DateTime::createFromFormat($format, $date, $timezone);
        return $datetime && $datetime->format($format) === $date;
    }

    /**
     * 時間が有効かチェック
     *
     * @param string $time 時間（HH:MM形式）
     * @return bool
     */
    public static function isValidTime($time) {
        if (empty($time)) {
            return false;
        }

        return preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time) === 1;
    }

    /**
     * 日付に日数を加算
     *
     * @param string $date 日付
     * @param int $days 加算する日数（負の値で減算）
     * @param string $format フォーマット
     * @return string 計算後の日付
     */
    public static function addDays($date, $days, $format = self::DATE_FORMAT) {
        if (empty($date)) {
            return '';
        }

        $timezone = new DateTimeZone(self::TIMEZONE);
        $datetime = new DateTime($date, $timezone);
        $datetime->modify("+{$days} days");
        return $datetime->format($format);
    }

    /**
     * 相対時間を取得（例: "3日前", "2時間後"）
     *
     * @param string $date 日付
     * @return string 相対時間
     */
    public static function getRelativeTime($date) {
        if (empty($date)) {
            return '';
        }

        $timezone = new DateTimeZone(self::TIMEZONE);
        $datetime = new DateTime($date, $timezone);
        $now = new DateTime('now', $timezone);
        $diff = $now->diff($datetime);

        if ($diff->days > 0) {
            return $diff->days . '日前';
        } elseif ($diff->h > 0) {
            return $diff->h . '時間前';
        } elseif ($diff->i > 0) {
            return $diff->i . '分前';
        } else {
            return 'たった今';
        }
    }

    /**
     * タイムゾーンを変換
     *
     * @param string $date 日付
     * @param string $from_timezone 元のタイムゾーン
     * @param string $to_timezone 変換先のタイムゾーン
     * @param string $format フォーマット
     * @return string 変換後の日付
     */
    public static function convertTimezone($date, $from_timezone, $to_timezone, $format = self::DATETIME_FORMAT) {
        if (empty($date)) {
            return '';
        }

        $from_tz = new DateTimeZone($from_timezone);
        $to_tz = new DateTimeZone($to_timezone);
        $datetime = new DateTime($date, $from_tz);
        $datetime->setTimezone($to_tz);
        return $datetime->format($format);
    }
}

/**
 * 通知本文の日程表示（統一定義 yy/mm/dd（曜）。時間は含めない）
 *
 * @param string|null $date Y-m-d 等
 * @return string 空のときは ''
 */
function aidunite_format_notification_date($date) {
    if ($date === null || $date === '') {
        return '';
    }
    if (class_exists('AidUniteDateUtils')) {
        return AidUniteDateUtils::formatDateForDisplay($date);
    }
    return (string) $date;
}

/**
 * 通知本文用「対象日程」1行（未設定時は em dash）
 *
 * @param string|null $date
 * @return string
 */
function aidunite_notification_schedule_date_line($date) {
    $formatted = aidunite_format_notification_date($date);
    return '対象日程: ' . ($formatted !== '' ? $formatted : '—');
}

/**
 * チャット等 API 用: MySQL 日時（GMT として保存）をサイト TZ の ISO8601 に変換
 *
 * TIMESTAMP / current_time('mysql') 経由の DB 値は UTC として解釈し、
 * フロントの new Date() が正しいローカル時刻を表示できるようにする。
 *
 * @param string|null $mysql_datetime Y-m-d H:i:s
 * @return string ISO8601（例: 2026-06-03T22:53:00+09:00）空入力時は ''
 */
function aidunite_format_chat_created_at_for_client($mysql_datetime) {
    $mysql_datetime = trim((string) $mysql_datetime);
    if ($mysql_datetime === '') {
        return '';
    }
    if (function_exists('mysql2date') && function_exists('wp_date')) {
        $ts = mysql2date('U', $mysql_datetime, true);
        if ($ts) {
            return wp_date('c', (int) $ts);
        }
    }
    if (class_exists('AidUniteDateUtils')) {
        $converted = AidUniteDateUtils::convertTimezone(
            $mysql_datetime,
            'UTC',
            AidUniteDateUtils::TIMEZONE,
            'Y-m-d\TH:i:sP'
        );
        if ($converted !== '') {
            return $converted;
        }
    }

    return $mysql_datetime;
}
