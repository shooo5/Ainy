(function () {
    document.addEventListener('DOMContentLoaded', function () {
        var cfg = typeof aidunitePaymentAdmin !== 'undefined' ? aidunitePaymentAdmin : {};
        var nonce = cfg.nonce || '';
        var ajaxUrl = cfg.ajaxUrl || '';
        var restUrl = (cfg.restUrl || '').replace(/\/$/, '');
        var restNonce = cfg.restNonce || '';

        function assignFoundingTeam(teamId, onSuccess) {
            var id = parseInt(teamId, 10);
            if (!id || !restUrl || !restNonce) {
                aiduniteToast('設定が不足しています', 'error');
                return;
            }
            fetch(restUrl + '/teams/' + id + '/payment-plan/founding', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-WP-Nonce': restNonce,
                    'Content-Type': 'application/json',
                },
            })
                .then(function (r) {
                    return r.json().then(function (body) {
                        return { ok: r.ok, body: body };
                    });
                })
                .then(function (res) {
                    if (res.ok && res.body && res.body.success) {
                        aiduniteToast((res.body.message) || 'Founding Team に割り当てました', 'success');
                        if (typeof onSuccess === 'function') {
                            onSuccess(res.body);
                        } else {
                            window.location.reload();
                        }
                        return;
                    }
                    var msg =
                        (res.body && (res.body.message || (res.body.data && res.body.data.message))) ||
                        '割当に失敗しました';
                    aiduniteToast(msg, 'error');
                })
                .catch(function () {
                    aiduniteToast('通信エラー', 'error');
                });
        }

        function updateFoundingSlotsRemaining(payload) {
            var el = document.getElementById('founding-slots-remaining');
            if (!el || !payload || !payload.founding) {
                return;
            }
            if (typeof payload.founding.slots_remaining !== 'undefined') {
                el.textContent = String(payload.founding.slots_remaining);
            }
        }

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
                var matchAmount = document.getElementById('match_monthly_amount').value;
                var data = new FormData();
                data.append('action', 'aidunite_save_payment_config');
                data.append('match_monthly_amount', matchAmount);
                data.append('personal_amount', matchAmount);
                data.append('corporate_amount', matchAmount);
                data.append('club_per_player_amount', document.getElementById('club_per_player_amount').value);
                data.append('club_minimum_addon', document.getElementById('club_minimum_addon').value);
                data.append('founding_max_slots', document.getElementById('founding_max_slots').value);
                data.append('founding_discount_percent', document.getElementById('founding_discount_percent').value);
                data.append('multi_team_discount_percent', document.getElementById('multi_team_discount_percent').value);
                data.append('nonce', nonce);
                fetch(ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
                    .then(function (r) {
                        return r.json();
                    })
                    .then(function (res) {
                        aiduniteToast(
                            res.success ? '設定を保存しました' : (res.data && res.data.message) || '保存に失敗しました',
                            res.success ? 'success' : 'error'
                        );
                    })
                    .catch(function () {
                        aiduniteToast('通信エラー', 'error');
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
                        aiduniteToast(
                            res.success ? 'Stripe設定を保存しました' : (res.data && res.data.message) || '保存に失敗しました',
                            res.success ? 'success' : 'error'
                        );
                    })
                    .catch(function () {
                        aiduniteToast('通信エラー', 'error');
                    });
            });
        }

        var foundingAssignBtn = document.getElementById('founding-assign-btn');
        if (foundingAssignBtn) {
            foundingAssignBtn.addEventListener('click', function () {
                var input = document.getElementById('founding-assign-team-id');
                var teamId = input ? input.value : '';
                if (!teamId) {
                    aiduniteToast('チームIDを入力してください', 'error');
                    return;
                }
                if (!window.confirm('チームID ' + teamId + ' を Founding Team に割り当てますか？')) {
                    return;
                }
                assignFoundingTeam(teamId, function (body) {
                    updateFoundingSlotsRemaining(body.payload || {});
                    if (input) {
                        input.value = '';
                    }
                });
            });
        }

        document.querySelectorAll('.aidunite-assign-founding-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var teamId = this.getAttribute('data-team-id');
                if (!teamId) {
                    return;
                }
                if (!window.confirm('チームID ' + teamId + ' を Founding Team に割り当てますか？')) {
                    return;
                }
                assignFoundingTeam(teamId);
            });
        });

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
                        aiduniteToast(
                            res.success
                                ? (res.data && res.data.message) || '保存しました'
                                : (res.data && res.data.message) || '保存に失敗しました',
                            res.success ? 'success' : 'error'
                        );
                    })
                    .catch(function () {
                        aiduniteToast('通信エラー', 'error');
                    });
            });
        }
    });
})();
