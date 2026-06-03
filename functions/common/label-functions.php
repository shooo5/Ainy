<?php
/**
 * ラベル取得共通関数
 * 性別・会場・マッチステータス・掲示板ステータスのラベルを統一的に取得
 */

/**
 * 統合ラベル取得関数
 *
 * @param string $type ラベルタイプ ('place' | 'gender' | 'match_status' | 'board_status')
 * @param string $value 内部値
 * @param string $format 出力形式 ('text' | 'html')
 * @return string ラベル（日本語）
 */
function aidunite_get_label($type, $value, $format = 'text') {
    $label = '';

    switch ($type) {
        case 'place':
            $label = aidunite_get_place_label($value);
            break;

        case 'gender':
            $label = aidunite_get_gender_label($value);
            break;

        case 'match_status':
            $label = aidunite_get_match_status_label($value, $format);
            break;

        case 'board_status':
            $label = aidunite_get_board_status_label($value);
            break;

        default:
            $label = '不明';
            break;
    }

    return $label;
}

/**
 * 会場ラベル取得
 *
 * @param string $value 会場内部値
 * @return string 会場ラベル
 */
function aidunite_get_place_label($value) {
    $place_labels = [
        'home' => 'ホーム',
        'away' => 'アウェイ',
        'neutral' => '中立',
        'both' => 'どちらでも可',  // 統一: bothで統一
        'either' => 'どちらでも可', // eitherもbothと同じ表記に統一
        'tbd' => '未定',
        'undecided' => '未定'
    ];

    return $place_labels[$value] ?? '不明';
}

/**
 * 性別ラベル取得
 *
 * @param string $value 性別内部値
 * @return string 性別ラベル
 */
function aidunite_get_gender_label($value) {
    if (function_exists('aidunite_mvp_gender_raw_is_both_legacy') && aidunite_mvp_gender_raw_is_both_legacy($value)) {
        return '要設定（旧データ）';
    }
    if (function_exists('aidunite_schedule_recruit_gender_label')) {
        $label = aidunite_schedule_recruit_gender_label($value);
        if ($label !== '—') {
            return $label;
        }
    }
    $gender_labels = [
        'male' => '男子',
        'female' => '女子',
    ];

    return $gender_labels[$value] ?? '不明';
}

/**
 * マッチ度スコア等向け：性別メタ値を日本語ラベルへ正規化（既に日本語ならそのまま）
 *
 * @param mixed $g schedule_gender / matching_gender_condition 等
 * @return string|mixed 空は ''、既知コードは日本語、その他は入力をそのまま返す
 */
if (!function_exists('aidunite_normalize_gender_for_match_score')) {
    function aidunite_normalize_gender_for_match_score($g) {
        if ($g === null || $g === '') {
            return '';
        }
        if (function_exists('aidunite_normalize_gender_canonical')) {
            $c = aidunite_normalize_gender_canonical(is_string($g) ? $g : '');
            if ($c === 'male') {
                return '男子';
            }
            if ($c === 'female') {
                return '女子';
            }
            if (function_exists('aidunite_mvp_gender_raw_is_both_legacy') && aidunite_mvp_gender_raw_is_both_legacy($g)) {
                return '';
            }
        }
        if (!is_string($g)) {
            return $g;
        }
        switch ($g) {
            case 'male':
                return '男子';
            case 'female':
                return '女子';
            case '男子':
            case '女子':
                return $g;
            default:
                return '';
        }
    }
}

/**
 * 後方互換: 旧ヘルパー名（各テンプレートのローカル定義を廃止するため）
 *
 * @param string $place 会場内部値
 * @return string
 */
if (!function_exists('aidunite_jp_place')) {
    function aidunite_jp_place($place) {
        $label = aidunite_get_place_label($place);
        return ($label === '不明') ? ($place ?: '-') : $label;
    }
}

/**
 * 後方互換: 旧ヘルパー名（各テンプレートのローカル定義を廃止するため）
 *
 * @param string $gender 性別内部値
 * @return string
 */
if (!function_exists('aidunite_jp_gender')) {
    function aidunite_jp_gender($gender) {
        $label = aidunite_get_gender_label($gender);
        return ($label === '不明') ? ($gender ?: '-') : $label;
    }
}

/**
 * マッチステータスラベル取得
 *
 * @param string $status ステータス値
 * @param string $format 出力形式 ('text' | 'html')
 * @return string ステータスラベル
 */
function aidunite_get_match_status_label($status, $format = 'text') {
    $canonical = $status;
    if (function_exists('aidunite_normalize_match_request_status')) {
        $canonical = aidunite_normalize_match_request_status((string) $status, '');
    }

    $status_labels = [
        'not_applied' => '未申請',
        '未申請' => '未申請',
        'pending' => '申請中',
        'publish' => '申請中',
        '申請中' => '申請中',
        'accepted' => '承認済み',
        'established' => '試合確定',
        'rejected' => '拒否',
        '拒否済み' => '拒否',
        'canceled' => 'キャンセル',
        'cancelled' => 'キャンセル',
        'resend' => '再申請',
    ];

    $label = $status_labels[$canonical] ?? ($status_labels[$status] ?? '不明');

    if ($format === 'html') {
        $status_colors = [
            'not_applied' => 'gray',
            '未申請' => 'gray',
            'pending' => 'blue',
            'publish' => 'blue',
            'accepted' => 'green',
            'established' => 'green',
            'rejected' => 'red',
            'canceled' => 'orange',
            'resend' => 'purple',
        ];

        $color = $status_colors[$canonical] ?? ($status_colors[$status] ?? 'gray');
        $label = "<span class='status-badge status-{$color}'>{$label}</span>";
    }

    return $label;
}

/**
 * 掲示板ステータスラベル取得
 *
 * @param string $status ステータス値
 * @param string $viewer_role 閲覧者ロール ('own' | 'other')
 * @return string 掲示板ステータスラベル
 */
function aidunite_get_board_status_label($status, $viewer_role = 'other') {
    $board_labels = [
        'available' => '募集中',
        'pending' => '申請中',
        'matched' => 'マッチ済み',
        'completed' => '完了',
        'canceled' => 'キャンセル'
    ];

    $label = $board_labels[$status] ?? '不明';

    // 自チームの場合は異なる表示
    if ($viewer_role === 'own') {
        $own_labels = [
            'available' => '公開中',
            'pending' => '申請受付中',
            'matched' => 'マッチ成立',
            'completed' => '試合完了',
            'canceled' => '取り消し'
        ];
        $label = $own_labels[$status] ?? $label;
    }

    return $label;
}
