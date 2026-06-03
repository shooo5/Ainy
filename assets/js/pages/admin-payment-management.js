(function () {
    document.addEventListener('DOMContentLoaded', function () {
        var cfg = typeof aidunitePaymentAdmin !== 'undefined' ? aidunitePaymentAdmin : {};
        var nonce = cfg.nonce || '';
        var ajaxUrl = cfg.ajaxUrl || '';

        document.querySelectorAll('.ainy-dashboard-tab-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var tabId = this.getAttribute('data-tab');
                document.querySelectorAll('.ainy-dashboard-tab-btn').forEach(function (b) {
                    b.classList.remove('is-active');
                });
                document.querySelectorAll('.ainy-dashboard-tab-content').forEach(function (c) {
                    c.classList.remove('is-active');
                });
                this.classList.add('is-active');
                var content = document.getElementById(tabId);
                if (content) {
                    content.classList.add('is-active');
                }
            });
        });

        var formAmount = document.getElementById('amount-settings-form');
        if (formAmount) {
            formAmount.addEventListener('submit', function (e) {
                e.preventDefault();
                var data = new FormData();
                data.append('action', 'aidunite_save_payment_config');
                data.append('board_amount', document.getElementById('board_amount').value);
                data.append('school_amount', document.getElementById('school_amount').value);
                data.append('personal_amount', document.getElementById('personal_amount').value);
                data.append('club_base_amount', document.getElementById('club_base_amount').value);
                data.append('nonce', nonce);
                fetch(ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
                    .then(function (r) {
                        return r.json();
                    })
                    .then(function (res) {
                        alert(res.success ? '設定を保存しました' : (res.data && res.data.message) || '保存に失敗しました');
                    })
                    .catch(function () {
                        alert('通信エラー');
                    });
            });
        }

        var formStripe = document.getElementById('stripe-settings-form');
        if (formStripe) {
            formStripe.addEventListener('submit', function (e) {
                e.preventDefault();
                var data = new FormData();
                data.append('action', 'aidunite_save_stripe_keys');
                data.append('publishable_key', document.getElementById('publishable_key').value);
                data.append('secret_key', document.getElementById('secret_key').value);
                data.append('webhook_secret', document.getElementById('webhook_secret').value);
                data.append('nonce', nonce);
                fetch(ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
                    .then(function (r) {
                        return r.json();
                    })
                    .then(function (res) {
                        alert(res.success ? 'Stripe設定を保存しました' : (res.data && res.data.message) || '保存に失敗しました');
                    })
                    .catch(function () {
                        alert('通信エラー');
                    });
            });
        }

        var genBtn = document.getElementById('generate-code-btn');
        if (genBtn) {
            genBtn.addEventListener('click', function () {
                var data = new FormData();
                data.append('action', 'aidunite_generate_registration_code');
                data.append('nonce', nonce);
                fetch(ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
                    .then(function (r) {
                        return r.json();
                    })
                    .then(function (res) {
                        if (res.success && res.data && res.data.code) {
                            document.getElementById('code-value').textContent = res.data.code;
                            document.getElementById('generated-code').style.display = 'block';
                        } else {
                            alert('コード生成に失敗しました');
                        }
                    })
                    .catch(function () {
                        alert('通信エラー');
                    });
            });
        }

        var copyBtn = document.getElementById('copy-code-btn');
        if (copyBtn) {
            copyBtn.addEventListener('click', function () {
                var code = document.getElementById('code-value').textContent;
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(code).then(function () {
                        alert('コピーしました: ' + code);
                    });
                } else {
                    alert('コード: ' + code);
                }
            });
        }

        var formPlanDisplayMode = document.getElementById('plan-display-mode-form');
        if (formPlanDisplayMode) {
            formPlanDisplayMode.addEventListener('submit', function (e) {
                e.preventDefault();
                var mode = document.querySelector('input[name="plan_display_mode"]:checked');
                if (!mode) {
                    return;
                }
                var data = new FormData();
                data.append('action', 'aidunite_save_plan_display_mode');
                data.append('plan_display_mode', mode.value);
                data.append('nonce', nonce);
                fetch(ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
                    .then(function (r) {
                        return r.json();
                    })
                    .then(function (res) {
                        alert(
                            res.success
                                ? (res.data && res.data.message) || '保存しました'
                                : (res.data && res.data.message) || '保存に失敗しました'
                        );
                    })
                    .catch(function () {
                        alert('通信エラー');
                    });
            });
        }
    });
})();
