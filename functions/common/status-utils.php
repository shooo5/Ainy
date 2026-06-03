<?php
/**
 * 統一ステータス管理ユーティリティ
 * AidUnite Theme - Status Utils
 *
 * 状態管理（ステータス）を統一するためのユーティリティクラス
 */

if (!defined('ABSPATH')) {
    exit;
}

class AidUniteStatus {
    // マッチボードステータス
    const MATCH_BOARD_OPEN = 'open';
    const MATCH_BOARD_PENDING = 'pending';
    const MATCH_BOARD_ACCEPTED = 'accepted';
    const MATCH_BOARD_REJECTED = 'rejected';
    const MATCH_BOARD_CANCELED = 'canceled';
    const MATCH_BOARD_RESEND = 'resend';
    const MATCH_BOARD_CLOSED = 'closed';

    // マッチステータス
    const MATCH_PLANNED = 'planned';
    const MATCH_MATCHED = 'matched';
    const MATCH_CONFIRMED = 'confirmed';

    /**
     * ステータスを正規化
     *
     * @param string $status ステータス
     * @param string $type ステータスタイプ（'match_board', 'match'）
     * @return string 正規化されたステータス
     */
    public static function normalize($status, $type = 'match_board') {
        if (empty($status)) {
            return '';
        }

        $status = strtolower(trim($status));

        if ($type === 'match_board') {
            $allowed = [
                self::MATCH_BOARD_OPEN,
                self::MATCH_BOARD_PENDING,
                self::MATCH_BOARD_ACCEPTED,
                self::MATCH_BOARD_REJECTED,
                self::MATCH_BOARD_CANCELED,
                self::MATCH_BOARD_RESEND,
                self::MATCH_BOARD_CLOSED
            ];

            if (in_array($status, $allowed, true)) {
                return $status;
            }
        } elseif ($type === 'match') {
            $allowed = [
                self::MATCH_PLANNED,
                self::MATCH_MATCHED,
                self::MATCH_CONFIRMED
            ];

            if (in_array($status, $allowed, true)) {
                return $status;
            }
        }

        return $status; // 未知のステータスはそのまま返す
    }

    /**
     * ステータスのラベルを取得
     *
     * @param string $status ステータス
     * @param string $type ステータスタイプ
     * @return string ラベル
     */
    public static function getLabel($status, $type = 'match_board') {
        $labels = [];

        if ($type === 'match_board') {
            $labels = [
                self::MATCH_BOARD_OPEN => '公開中',
                self::MATCH_BOARD_PENDING => '承認待ち',
                self::MATCH_BOARD_ACCEPTED => '承認済み',
                self::MATCH_BOARD_REJECTED => '却下',
                self::MATCH_BOARD_CANCELED => 'キャンセル',
                self::MATCH_BOARD_RESEND => '再送信',
                self::MATCH_BOARD_CLOSED => '終了'
            ];
        } elseif ($type === 'match') {
            $labels = [
                self::MATCH_PLANNED => '予定',
                self::MATCH_MATCHED => 'マッチ済み',
                self::MATCH_CONFIRMED => '確定'
            ];
        }

        return $labels[$status] ?? $status;
    }

    /**
     * ステータス遷移が可能かチェック
     *
     * @param string $from_status 現在のステータス
     * @param string $to_status 遷移先のステータス
     * @param string $type ステータスタイプ
     * @return bool 遷移可能かどうか
     */
    public static function canTransition($from_status, $to_status, $type = 'match_board') {
        if ($type === 'match_board') {
            // マッチボードステータスの遷移ルール
            $transitions = [
                self::MATCH_BOARD_OPEN => [self::MATCH_BOARD_PENDING, self::MATCH_BOARD_CLOSED],
                self::MATCH_BOARD_PENDING => [self::MATCH_BOARD_ACCEPTED, self::MATCH_BOARD_REJECTED, self::MATCH_BOARD_CANCELED],
                self::MATCH_BOARD_ACCEPTED => [self::MATCH_BOARD_CLOSED],
                self::MATCH_BOARD_REJECTED => [self::MATCH_BOARD_RESEND],
                self::MATCH_BOARD_RESEND => [self::MATCH_BOARD_PENDING],
                self::MATCH_BOARD_CANCELED => [],
                self::MATCH_BOARD_CLOSED => []
            ];

            if (isset($transitions[$from_status])) {
                return in_array($to_status, $transitions[$from_status], true);
            }
        } elseif ($type === 'match') {
            // マッチステータスの遷移ルール
            $transitions = [
                self::MATCH_PLANNED => [self::MATCH_MATCHED],
                self::MATCH_MATCHED => [self::MATCH_CONFIRMED],
                self::MATCH_CONFIRMED => []
            ];

            if (isset($transitions[$from_status])) {
                return in_array($to_status, $transitions[$from_status], true);
            }
        }

        return false;
    }

    /**
     * ステータスのリストを取得
     *
     * @param string $type ステータスタイプ
     * @return array ステータスの配列
     */
    public static function getList($type = 'match_board') {
        if ($type === 'match_board') {
            return [
                self::MATCH_BOARD_OPEN,
                self::MATCH_BOARD_PENDING,
                self::MATCH_BOARD_ACCEPTED,
                self::MATCH_BOARD_REJECTED,
                self::MATCH_BOARD_CANCELED,
                self::MATCH_BOARD_RESEND,
                self::MATCH_BOARD_CLOSED
            ];
        } elseif ($type === 'match') {
            return [
                self::MATCH_PLANNED,
                self::MATCH_MATCHED,
                self::MATCH_CONFIRMED
            ];
        }

        return [];
    }
}
