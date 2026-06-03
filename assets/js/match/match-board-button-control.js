/**
 * Match Board Button Control
 * 申請の進捗タブのアクションボタン制御
 */

// getDocument は js/common/dom-utils.js で定義。未読込時はフォールバック
if (typeof getDocument === 'undefined') {
    function getDocument() {
        if (typeof global !== 'undefined' && global.document) return global.document;
        return typeof document !== 'undefined' ? document : null;
    }
}

function getLocation() {
    // テスト環境ではglobal.locationを優先的に使用
    // JSDOMがglobal.locationを設定している場合でも、テストで設定したモックを優先する
    if (typeof global !== 'undefined' && global.location) {
        // テスト環境でモックが設定されている場合は、それを優先
        // jest.fn()でモックされている場合は、それが優先される
        return global.location;
    }
    // ブラウザ環境では通常のlocationを使用
    return typeof location !== 'undefined' ? location : null;
}

// 3日前警告の日数設定
const CANCEL_WARNING_DAYS = 3;

/**
 * 日付文字列をDateオブジェクトに変換
 */
function parseDate(dateStr) {
    if (!dateStr) return null;
    // YYYY-MM-DD形式を想定
    const parts = dateStr.split('-');
    if (parts.length !== 3) return null;
    return new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
}

/**
 * 2つの日付の差（日数）を計算
 */
function getDaysDifference(date1, date2) {
    if (!date1 || !date2) return null;
    const diffTime = Math.abs(date2 - date1);
    return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
}

/**
 * 3日前警告ダイアログを表示
 */
function showCancelWarning(callback) {
    const message = `試合日まで${CANCEL_WARNING_DAYS}日以内のキャンセルは相手チームに迷惑をかける可能性があります。\n\n本当にキャンセルしますか？`;

    if (confirm(message)) {
        callback();
    }
}

/**
 * ステータス更新AJAX
 */
function updateApplicationStatus(index, newStatus, dateStr) {
    const $ = (typeof global !== 'undefined' && global.jQuery) ? global.jQuery : (typeof jQuery !== 'undefined' ? jQuery : null);
    if (!$) {
        console.error('jQuery is not available');
        return;
    }

    const data = {
        action: 'au_update_match_request_status',
        request_id: index,
        index: index,
        status: newStatus,
        security: $('#au_match_nonce').val()
    };

    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: data,
        beforeSend: function() {
            // ローディング表示
            $('.au-actions button[data-index="' + index + '"]').prop('disabled', true);
        },
        success: function(response) {
            if (response.success) {
                // ページリロードで最新状態を反映
                const loc = getLocation();
                if (typeof loc !== 'undefined' && loc.reload) {
                    loc.reload();
                }
            } else {
                if (typeof showToastNotification !== 'undefined') {
                    showToastNotification(response.data?.message || '不明なエラーが発生しました', 'error');
                } else {
                    alert('エラー: ' + (response.data?.message || '不明なエラーが発生しました'));
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', xhr.responseText);
            if (typeof showToastNotification !== 'undefined') {
                showToastNotification('通信エラーが発生しました。ページを再読み込みしてください。', 'error');
            } else {
                alert('通信エラーが発生しました。ページを再読み込みしてください。');
            }
        },
        complete: function() {
            // ローディング解除
            $('.au-actions button[data-index="' + index + '"]').prop('disabled', false);
        }
    });
}

/**
 * キャンセルボタンクリック処理
 */
function handleCancelClick(index, dateStr) {
    const matchDate = parseDate(dateStr);
    const today = new Date();
    const daysDiff = getDaysDifference(today, matchDate);

    // 3日前以内の場合は警告表示
    if (daysDiff !== null && daysDiff <= CANCEL_WARNING_DAYS) {
        showCancelWarning(function() {
            updateApplicationStatus(index, 'canceled', dateStr);
        });
    } else {
        // 通常のキャンセル処理
        if (confirm('本当にキャンセルしますか？')) {
            updateApplicationStatus(index, 'canceled', dateStr);
        }
    }
}

/**
 * 承認ボタンクリック処理
 */
function handleApproveClick(index) {
    if (confirm('この申請を承認しますか？')) {
        updateApplicationStatus(index, 'accepted');
    }
}

/**
 * 拒否ボタンクリック処理
 */
function handleRejectClick(index) {
    if (confirm('この申請を拒否しますか？')) {
        updateApplicationStatus(index, 'rejected');
    }
}

/**
 * イベントリスナー設定
 */
function initEventListeners() {
    const $ = (typeof global !== 'undefined' && global.jQuery) ? global.jQuery : (typeof jQuery !== 'undefined' ? jQuery : null);
    if (!$) {
        console.error('jQuery is not available');
        return;
    }

    const doc = getDocument();
    // キャンセルボタン
    $(doc).on('click', '.au-actions button[data-status="キャンセル"]', function(e) {
        e.preventDefault();
        const index = $(this).data('index');
        const date = $(this).data('date');
        handleCancelClick(index, date);
    });

    // 承認ボタン
    $(doc).on('click', '.au-actions button[data-status="承認待ち"]', function(e) {
        e.preventDefault();
        const index = $(this).data('index');
        handleApproveClick(index);
    });

    // 拒否ボタン
    $(doc).on('click', '.au-actions button[data-status="拒否された"]', function(e) {
        e.preventDefault();
        const index = $(this).data('index');
        handleRejectClick(index);
    });
}

/**
 * 初期化
 */
function init() {
    initEventListeners();
}

// テスト用にグローバルに公開（モジュール読み込み時に確実に実行される）
if (typeof global !== 'undefined') {
    global.parseDate = parseDate;
    global.getDaysDifference = getDaysDifference;
    global.showCancelWarning = showCancelWarning;
    global.updateApplicationStatus = updateApplicationStatus;
    global.handleCancelClick = handleCancelClick;
    global.handleApproveClick = handleApproveClick;
    global.handleRejectClick = handleRejectClick;
    global.initEventListeners = initEventListeners;
    global.init = init;
    global.CANCEL_WARNING_DAYS = CANCEL_WARNING_DAYS;
}

// DOM読み込み完了後に初期化
// テスト環境ではglobal.jQueryを優先的に使用
const testJQuery = (typeof global !== 'undefined' && global.jQuery) ? global.jQuery : (typeof jQuery !== 'undefined' ? jQuery : null);
if (testJQuery) {
    const docForReady = getDocument();
    testJQuery(docForReady).ready(function() {
        init();
    });
}
