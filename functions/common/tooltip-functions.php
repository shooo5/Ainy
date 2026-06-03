<?php
/**
 * ツールチップ共通関数
 * 全ページで統一されたツールチップの文言と属性を管理
 */

/**
 * マッチ度のツールチップ文言を取得
 *
 * @param string $match_level マッチ度（ベストマッチ、高マッチ、中マッチ、低マッチ、条件不一致）
 * @return string ツールチップ文言
 */
function aidunite_get_match_score_tooltip($match_level) {
    $tooltips = [
        'ベストマッチ' => 'すべての条件が完全に一致しています',
        '高マッチ' => 'ほとんどの条件が一致しています',
        '中マッチ' => '一部の条件が一致しています',
        '低マッチ' => '条件が一部合わない可能性があります',
        '条件不一致' => '条件が合いません',
    ];

    return $tooltips[$match_level] ?? '';
}

/**
 * 申請ステータスのツールチップ文言を取得
 *
 * @param string $status ステータス（未申請、申請中、申請受付中、承認済み、試合確定、拒否済み、キャンセル済み、相手キャンセル）
 * @return string ツールチップ文言
 */
function aidunite_get_application_status_tooltip($status) {
    $tooltips = [
        'not_applied' => 'まだ申請していない状態です',
        '未申請' => 'まだ申請していない状態です',
        '申請中' => '申請を送信し、相手チームの承認を待っている状態です',
        '申請受付中' => '相手チームからの申請を受付中です',
        '承認済み' => '相手チームが申請を承認し、マッチが成立した状態です',
        '試合確定' => '試合が確定し、試合の準備を進めることができます',
        '拒否済み' => '相手チームが申請を拒否した状態です。再申請が可能です',
        'キャンセル済み' => '申請をキャンセルした状態です。再申請が可能です',
        '相手キャンセル' => '相手チームが申請をキャンセルした状態です',
    ];

    return $tooltips[$status] ?? '';
}

/**
 * 募集状況のツールチップ文言を取得
 *
 * @param string $status 募集状況（募集中、募集完了）
 * @return string ツールチップ文言
 */
function aidunite_get_recruitment_status_tooltip($status) {
    $tooltips = [
        '募集中' => '対戦相手を募集しています',
        '募集完了' => '定員に達し、募集を終了しました',
    ];

    return $tooltips[$status] ?? '';
}

/**
 * ツールチップ属性を生成
 *
 * @param string $tooltip_text ツールチップ文言
 * @param string $type ツールチップタイプ（'native' | 'custom'）
 * @return string HTML属性文字列
 */
function aidunite_get_tooltip_attributes($tooltip_text, $type = 'custom') {
    if (empty($tooltip_text)) {
        return '';
    }

    $escaped_text = esc_attr($tooltip_text);

    if ($type === 'native') {
        return 'title="' . $escaped_text . '" data-tooltip="' . $escaped_text . '"';
    } else {
        return 'data-tooltip="' . $escaped_text . '"';
    }
}

/**
 * ツールチップ付き要素のHTMLを生成
 *
 * @param string $content 要素の内容
 * @param string $tooltip_text ツールチップ文言
 * @param string $class 追加クラス
 * @param string $tag タグ名（'span' | 'div' | 'button'など）
 * @return string HTML文字列
 */
function aidunite_render_tooltip_element($content, $tooltip_text, $class = '', $tag = 'span') {
    $tooltip_attrs = aidunite_get_tooltip_attributes($tooltip_text);
    $class_attr = !empty($class) ? ' class="' . esc_attr($class) . ' tooltip-trigger"' : ' class="tooltip-trigger"';

    return '<' . $tag . $class_attr . ' ' . $tooltip_attrs . '>' . $content . '</' . $tag . '>';
}
