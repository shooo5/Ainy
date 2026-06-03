/**
 * 試合招待確認ページ用JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        const form = $('#match-invite-approval-form');
        const rejectBtn = $('#reject-btn');
        const resultMessage = $('#result-message');

        // 承認フォーム送信
        form.on('submit', function(e) {
            e.preventDefault();

            const token = $('input[name="token"]').val();
            const schoolName = $('#school_name').val().trim();
            const approverName = $('#approver_name').val().trim();

            // バリデーション
            if (!schoolName) {
                showMessage('学校名またはクラブ名を入力してください', 'error');
                return;
            }

            if (!approverName) {
                showMessage('お名前を入力してください', 'error');
                return;
            }

            // 送信ボタンを無効化
            const submitBtn = form.find('button[type="submit"]');
            submitBtn.prop('disabled', true).text('処理中...');

            // REST API呼び出し
            const root = typeof matchInviteSettings !== 'undefined' ? matchInviteSettings.root : '/wp-json/';
            const nonce = typeof matchInviteSettings !== 'undefined' ? matchInviteSettings.nonce : '';

            $.ajax({
                url: root + 'aidunite/v1/match-invite-approve',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': nonce
                },
                contentType: 'application/json',
                data: JSON.stringify({
                    token: token,
                    school_name: schoolName,
                    approver_name: approverName
                }),
                success: function(response) {
                    if (response.success) {
                        showMessage('承認が完了しました。ありがとうございます！', 'success');
                        form.hide();
                        // 3秒後にホームにリダイレクト
                        setTimeout(function() {
                            window.location.href = '/';
                        }, 3000);
                    } else {
                        showMessage(response.message || '承認処理に失敗しました', 'error');
                        submitBtn.prop('disabled', false).text('承認');
                    }
                },
                error: function(xhr, status, error) {
                    let errorMsg = '承認処理に失敗しました';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    showMessage(errorMsg, 'error');
                    submitBtn.prop('disabled', false).text('承認');
                }
            });
        });

        // 拒否ボタンクリック
        rejectBtn.on('click', function() {
            if (!confirm('この試合招待を拒否しますか？')) {
                return;
            }

            const token = $('input[name="token"]').val();
            const btn = $(this);
            btn.prop('disabled', true).text('処理中...');

            // REST API呼び出し
            const root = typeof matchInviteSettings !== 'undefined' ? matchInviteSettings.root : '/wp-json/';
            const nonce = typeof matchInviteSettings !== 'undefined' ? matchInviteSettings.nonce : '';

            $.ajax({
                url: root + 'aidunite/v1/match-invite-reject',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': nonce
                },
                contentType: 'application/json',
                data: JSON.stringify({
                    token: token
                }),
                success: function(response) {
                    if (response.success) {
                        showMessage('拒否が完了しました', 'success');
                        form.hide();
                        // 3秒後にホームにリダイレクト
                        setTimeout(function() {
                            window.location.href = '/';
                        }, 3000);
                    } else {
                        showMessage(response.message || '拒否処理に失敗しました', 'error');
                        btn.prop('disabled', false).text('拒否');
                    }
                },
                error: function(xhr, status, error) {
                    let errorMsg = '拒否処理に失敗しました';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    showMessage(errorMsg, 'error');
                    btn.prop('disabled', false).text('拒否');
                }
            });
        });

        // メッセージ表示関数
        function showMessage(message, type) {
            resultMessage
                .removeClass('success error')
                .addClass(type)
                .text(message)
                .fadeIn();

            // エラーの場合は5秒後に非表示
            if (type === 'error') {
                setTimeout(function() {
                    resultMessage.fadeOut();
                }, 5000);
            }
        }
    });
})(jQuery);
