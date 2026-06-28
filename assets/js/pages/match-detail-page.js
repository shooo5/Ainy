/**
 * マッチ詳細（page-match-detail.php）
 * aiduniteMatchDetailPage は wp_localize_script で注入
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var cfg = typeof aiduniteMatchDetailPage !== 'undefined' ? aiduniteMatchDetailPage : {};

    // 確認: 時間選択UIは start-time-select-detail / end-time-select-detail の1系統のみ
    const startTimeSelectDetail = document.getElementById('start-time-select-detail');
    const endTimeSelectDetail = document.getElementById('end-time-select-detail');
    const selectedTimeRangeDetail = document.getElementById('selected-time-range-detail');

    // 時間選択UIの処理
    if (startTimeSelectDetail && endTimeSelectDetail) {
        const myStart = (cfg.mySchedule && cfg.mySchedule.start) || '';
        const myEnd = (cfg.mySchedule && cfg.mySchedule.end) || '';
        const otherStart = (cfg.otherSchedule && cfg.otherSchedule.start) || '';
        const otherEnd = (cfg.otherSchedule && cfg.otherSchedule.end) || '';

        // 時間を分に変換
        function timeToMinutes(timeStr) {
            if (!timeStr) return NaN;
            const parts = String(timeStr).split(':').map(Number);
            const hours = isFinite(parts[0]) ? parts[0] : NaN;
            const minutes = isFinite(parts[1]) ? parts[1] : 0;
            return hours * 60 + minutes;
        }

        function minutesToTime(minutes) {
            const hours = Math.floor(minutes / 60);
            const mins = minutes % 60;
            return String(hours).padStart(2, '0') + ':' + String(mins).padStart(2, '0');
        }

        const myStartMin = timeToMinutes(myStart);
        const myEndMin = timeToMinutes(myEnd);
        const otherStartMin = timeToMinutes(otherStart);
        const otherEndMin = timeToMinutes(otherEnd);

        // 利用可能な時間範囲を計算（双方の重複範囲：積集合＝最大開始〜最小終了）
        const overlapStart = Math.max(myStartMin, otherStartMin);
        const overlapEnd = Math.min(myEndMin, otherEndMin);

        // 30分単位で時間オプションを生成
        function generateTimeOptions(startMin, endMin) {
            const options = [];
            if (isFinite(startMin) && isFinite(endMin) && startMin < endMin) {
                for (let minutes = startMin; minutes <= endMin; minutes += 30) {
                    const timeStr = minutesToTime(minutes);
                    options.push(`<option value="${timeStr}">${timeStr}</option>`);
                }
            }
            return options.join('');
        }

        // 開始・終了セレクトを生成（重複範囲内のみ選択可能）
        const startOpts = generateTimeOptions(overlapStart, overlapEnd - 30);
        const endOpts = generateTimeOptions(overlapStart + 30, overlapEnd);
        if (startOpts && endOpts) {
            startTimeSelectDetail.innerHTML = '<option value="">選択してください</option>' + startOpts;
            endTimeSelectDetail.innerHTML = '<option value="">選択してください</option>' + endOpts;
            // デフォルトは重複範囲の開始〜終了
            const defStart = minutesToTime(overlapStart);
            const defEnd = minutesToTime(overlapEnd);
            if ([...startTimeSelectDetail.options].some(o => o.value === defStart)) startTimeSelectDetail.value = defStart;
            if ([...endTimeSelectDetail.options].some(o => o.value === defEnd)) endTimeSelectDetail.value = defEnd;
            if (selectedTimeRangeDetail) selectedTimeRangeDetail.textContent = `${defStart} ～ ${defEnd}`;
        } else {
            if (startTimeSelectDetail) startTimeSelectDetail.closest('div')?.classList.add('hidden');
            if (endTimeSelectDetail) endTimeSelectDetail.closest('div')?.classList.add('hidden');
            if (selectedTimeRangeDetail) selectedTimeRangeDetail.textContent = '選択可能時間なし（時間が重なっていません）';
        }

        // 時間選択の更新処理
        function updateTimeRangeDetail() {
            const startTime = startTimeSelectDetail.value;
            const endTime = endTimeSelectDetail.value;

            if (startTime && endTime) {
                const startMin = timeToMinutes(startTime);
                const endMin = timeToMinutes(endTime);

                if (startMin < endMin) {
                    if (selectedTimeRangeDetail) selectedTimeRangeDetail.textContent = `${startTime} ～ ${endTime}`;
                } else {
                    if (selectedTimeRangeDetail) selectedTimeRangeDetail.textContent = '終了時間は開始時間より後にしてください';
                }
            } else {
                if (selectedTimeRangeDetail) selectedTimeRangeDetail.textContent = '時間を選択してください';
            }
        }

        // 開始時間変更時の処理（重複範囲内に制限）
        startTimeSelectDetail.addEventListener('change', function() {
            const startTime = this.value;
            const prevEnd = endTimeSelectDetail.value;
            if (startTime) {
                const startMin = timeToMinutes(startTime);
                endTimeSelectDetail.innerHTML = '<option value="">選択してください</option>' +
                    generateTimeOptions(startMin + 30, overlapEnd);
                const prevEndMin = timeToMinutes(prevEnd);
                const minAllowedEnd = startMin + 30;
                if (isFinite(prevEndMin) && prevEndMin >= minAllowedEnd && prevEndMin <= overlapEnd) {
                    if ([...endTimeSelectDetail.options].some(o => o.value === prevEnd)) endTimeSelectDetail.value = prevEnd;
                } else {
                    const adjustedEnd = minutesToTime(Math.min(Math.max(minAllowedEnd, overlapStart + 30), overlapEnd));
                    if ([...endTimeSelectDetail.options].some(o => o.value === adjustedEnd)) endTimeSelectDetail.value = adjustedEnd;
                }
            }
            updateTimeRangeDetail();
        });

        // 終了時間変更時の処理（重複範囲内に制限）
        endTimeSelectDetail.addEventListener('change', function() {
            const endTime = this.value;
            const prevStart = startTimeSelectDetail.value;
            if (endTime) {
                const endMin = timeToMinutes(endTime);
                startTimeSelectDetail.innerHTML = '<option value="">選択してください</option>' +
                    generateTimeOptions(overlapStart, endMin - 30);
                const prevStartMin = timeToMinutes(prevStart);
                const maxAllowedStart = endMin - 30;
                if (isFinite(prevStartMin) && prevStartMin >= overlapStart && prevStartMin <= maxAllowedStart) {
                    if ([...startTimeSelectDetail.options].some(o => o.value === prevStart)) startTimeSelectDetail.value = prevStart;
                } else {
                    const adjustedStart = minutesToTime(Math.min(Math.max(overlapStart, maxAllowedStart), maxAllowedStart));
                    if ([...startTimeSelectDetail.options].some(o => o.value === adjustedStart)) startTimeSelectDetail.value = adjustedStart;
                }
            }
            updateTimeRangeDetail();
        });
    }

    const applyButton = document.getElementById('applyButton');
    const cancelButton = document.getElementById('cancelButton');
    const fullLoader = document.getElementById('fullScreenLoader');
    const loaderText = document.getElementById('loaderText');
    const aiduniteWpRestNonce = cfg.restNonce || '';
    const aiduniteMatchRequestUrl = cfg.matchRequestUrl || '';

    // エラー表示関数（アラート廃止）
    function showErrorAnimation(message) {
        console.error('Error:', message);
        const cleanMessage = String(message).replace(/^❌\s*/, '');
        if (typeof showToastNotification !== 'undefined') {
            showToastNotification(cleanMessage, 'error');
        }
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    }

    function updateApplyButton() {
        const needsPlaceAdjustment = !!cfg.needsPlaceAdjustment;
        const needsGenderAdjustment = !!cfg.needsGenderAdjustment;

        const placeSelected = !needsPlaceAdjustment || document.querySelector('[data-type="place"].selected') !== null;
        const genderSelect = document.querySelector('[name="gender_choice"]');
        const genderSelected = !needsGenderAdjustment || document.querySelector('[data-type="gender"].selected') !== null || (genderSelect && genderSelect.value);
        const canApply = placeSelected && genderSelected;

        if (applyButton) {
            applyButton.disabled = !canApply;
            applyButton.classList.toggle('is-muted', !canApply);
        }
    }

    const genderLabels = { male: '男子', female: '女子', both: '男子・女子可' };
    const placeLabels = { home: 'ホーム', away: 'アウェイ', either: 'どちらでも可', both: 'どちらでも可' };
    document.querySelectorAll('.match-pill').forEach(pill => {
        pill.addEventListener('click', function() {
            if (this.disabled) return;
            const type = this.dataset.type;
            const value = this.dataset.value;
            document.querySelectorAll(`.match-pill[data-type="${type}"]`).forEach(p => p.classList.remove('selected'));
            this.classList.add('selected');
            if (type === 'gender') {
                const summaryEl = document.getElementById('apply-summary-gender');
                if (summaryEl && genderLabels[value]) summaryEl.textContent = genderLabels[value];
                const summaryElOther = document.getElementById('apply-summary-gender-other');
                if (summaryElOther && genderLabels[value]) summaryElOther.textContent = genderLabels[value];
            }
            if (type === 'place') {
                const summaryPlace = document.getElementById('apply-summary-place');
                if (summaryPlace && placeLabels[value]) summaryPlace.textContent = placeLabels[value];
                const summaryPlaceOther = document.getElementById('apply-summary-place-other');
                if (summaryPlaceOther && placeLabels[value]) summaryPlaceOther.textContent = placeLabels[value];
            }
            if (typeof updateApplyButton === 'function') updateApplyButton();
        });
    });

    // 申請ボタンクリック → ローディングスピナー表示 → 申請送信
    if (applyButton) {
        applyButton.addEventListener('click', function() {
            if (this.disabled) return;

            const placeSel = document.querySelector('[data-type="place"].selected');
            const genderSel = document.querySelector('[data-type="gender"].selected');
            const genderChoiceSelect = document.querySelector('[name="gender_choice"]');
            const genderValue = (genderChoiceSelect && genderChoiceSelect.value) ? genderChoiceSelect.value : (genderSel ? genderSel.dataset.value : cfg.resolvedGender || 'both');
            let placeValue = placeSel ? placeSel.dataset.value : '';
            if (!placeValue) {
                placeValue = cfg.resolvedPlace || 'home';
            }
            if (placeValue === 'both') {
                placeValue = 'either';
            }
            const activeStartSelect = document.getElementById('start-time-select-detail');
            const activeEndSelect = document.getElementById('end-time-select-detail');

            // 時間選択の検証（時間調整が必要な場合のみ。ワイヤー：🟡では重なり時間使用のためselectなし）
            if (activeStartSelect && activeEndSelect) {
                const startTime = activeStartSelect.value;
                const endTime = activeEndSelect.value;

                if (!startTime || !endTime) {
                    showErrorAnimation('開始時間と終了時間を選択してください。');
                    return;
                }

                if (startTime >= endTime) {
                    showErrorAnimation('終了時間は開始時間より後にしてください。');
                    return;
                }
            }

            // 時間データの取得（ワイヤー：🟡では重なり時間を表示のみ→ここで重なり時間を使用）
            let selectedStartTime = '';
            let selectedEndTime = '';

            const timeDisplayOnly = document.querySelector('.match-detail-time-display-only');
            if (timeDisplayOnly && timeDisplayOnly.dataset.overlapStart && timeDisplayOnly.dataset.overlapEnd) {
                selectedStartTime = timeDisplayOnly.dataset.overlapStart;
                selectedEndTime = timeDisplayOnly.dataset.overlapEnd;
            } else if (activeStartSelect && activeEndSelect && activeStartSelect.value && activeEndSelect.value) {
                selectedStartTime = activeStartSelect.value;
                selectedEndTime = activeEndSelect.value;
            } else {
                const myScheduleStart = (cfg.mySchedule && cfg.mySchedule.start) || '';
                const myScheduleEnd = (cfg.mySchedule && cfg.mySchedule.end) || '';
                const otherScheduleStart = (cfg.otherSchedule && cfg.otherSchedule.start) || '';
                const otherScheduleEnd = (cfg.otherSchedule && cfg.otherSchedule.end) || '';
                if (myScheduleStart && myScheduleEnd && otherScheduleStart && otherScheduleEnd) {
                    const overlapStart = myScheduleStart > otherScheduleStart ? myScheduleStart : otherScheduleStart;
                    const overlapEnd = myScheduleEnd < otherScheduleEnd ? myScheduleEnd : otherScheduleEnd;
                    if (overlapStart < overlapEnd) {
                        selectedStartTime = overlapStart;
                        selectedEndTime = overlapEnd;
                    }
                }
                if (!selectedStartTime || !selectedEndTime) {
                    selectedStartTime = myScheduleStart || otherScheduleStart || '';
                    selectedEndTime = myScheduleEnd || otherScheduleEnd || '';
                }

            }

            // 時間データの検証
            if (!selectedStartTime || !selectedEndTime) {
                showErrorAnimation('時間データが取得できませんでした。ページを再読み込みしてください。');
                this.disabled = false;
                this.innerHTML = this.originalHTML || '申請する';
                if (fullLoader) fullLoader.style.display = 'none';
                return;
            }

            const matchRequestBody = {
                my_schedule_id: parseInt(String(cfg.myScheduleId || 0), 10) || 0,
                other_schedule_id: parseInt(String(cfg.otherScheduleId || 0), 10) || 0,
                selected_start_time: selectedStartTime,
                selected_end_time: selectedEndTime,
                selected_place: placeValue,
                selected_gender: genderValue
            };

            // ボタンを無効化してローディングスピナーを表示（統一されたローディングスピナーを使用）
            this.disabled = true;
            const originalHTML = this.innerHTML;
            this.innerHTML = '<span class="button-loading-spinner"></span><span>申請中...</span>';
            this.originalHTML = originalHTML;

            // フルスクリーンローダーを表示（スケジュール登録と同じ仕様）
            if (fullLoader && loaderText) {
                loaderText.textContent = '申請中...';
                fullLoader.style.display = 'flex';
            }

            fetch(aiduniteMatchRequestUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': aiduniteWpRestNonce
                },
                body: JSON.stringify(matchRequestBody)
            })
              .then(async r=>{
                  let data = null; let text = '';
                  try { text = await r.text(); data = JSON.parse(text); } catch(e) { /* not json */ }

                  if (r.ok && data && data.success) {
                      // 下書き削除/自動保存停止は、実装が存在する場合のみ実行
                      if (typeof deleteDraft === 'function') {
                          deleteDraft();
                      }
                      if (typeof stopAutoSave === 'function') {
                          stopAutoSave();
                      }

                      // 1秒後にローディングスピナーを非表示（スケジュール登録と同じ仕様）
                      setTimeout(() => {
                          if (fullLoader) {
                              fullLoader.style.display = 'none';
                          }

                          // 完了メッセージを表示
                          const completionMessage = document.getElementById('completion-message');
                          if (completionMessage) {
                              completionMessage.style.display = 'flex';
                          }

                          // 2.5秒後にリダイレクト（データベース反映を待つため1.5秒待機）
                          setTimeout(() => {
                              const url = (cfg.matchBoardUrl || '') + '#progress-view';
                              const timestamp = Date.now();
                              window.location.replace(`${url}?refresh=${timestamp}`);
                          }, 1500);
                      }, 1000);
                  } else {
                      console.error('match-request REST failed:', r.status, text || data);
                      // エラーメッセージを表示
                      const errorMessage = (data && data.data && data.data.message) || (data && data.message) || '申請に失敗しました';
                      showErrorAnimation(errorMessage);
                      // ローディングスピナーを非表示
                      if (fullLoader) {
                          fullLoader.style.display = 'none';
                      }
                      // ボタンを再有効化
                      this.disabled = false;
                      this.innerHTML = this.originalHTML || '申請する';
                      this.style.background = '';
                  }
              })
              .catch(()=>{
                  // ローディングスピナーを非表示
                  if (fullLoader) {
                      fullLoader.style.display = 'none';
                  }

                  aiduniteToast('申請に失敗しました。時間をおいてお試しください', 'error');
                  // ボタンを再有効化
                  this.disabled = false;
                  this.innerHTML = this.originalHTML || '申請する';
                  this.style.background = '';
              });
        });
    }

    // 相手のみモード：申請するボタン
    const applyButtonOtherOnly = document.getElementById('applyButtonOtherOnly');
    if (applyButtonOtherOnly) {
        applyButtonOtherOnly.addEventListener('click', function() {
            if (this.disabled) return;
            const form = document.getElementById('applyFormOtherOnly');
            const schedPill = form ? form.querySelector('.match-pill[data-type="my_schedule"].selected') : null;
            const scheduleHidden = form ? form.querySelector('input[name="my_schedule_id"]') : null;
            const myScheduleId = schedPill ? String(schedPill.dataset.value || '') : (scheduleHidden ? scheduleHidden.value : '');
            const otherScheduleId = this.dataset.otherScheduleId || '';
            const otherTeamId = this.dataset.otherTeamId || '';
            if (myScheduleId === '' || !otherScheduleId || !otherTeamId) {
                aiduniteToast('申請に必要な情報がありません。', 'error');
                return;
            }
            this.disabled = true;
            const originalHTML = this.innerHTML;
            this.innerHTML = '<span class="button-loading-spinner"></span>申請中...';
            this.originalHTML = originalHTML;
            const fullLoader = document.getElementById('fullScreenLoader');
            const loaderText = document.getElementById('loaderText');
            if (fullLoader && loaderText) { loaderText.textContent = '申請中...'; fullLoader.style.display = 'flex'; }

            const placePill = form ? form.querySelector('.match-pill[data-type="place"].selected') : null;
            const placeHidden = form ? form.querySelector('input[name="selected_place"]') : null;
            var selectedPlace = placePill ? placePill.dataset.value : (placeHidden ? placeHidden.value : (cfg.otherSchedule && cfg.otherSchedule.place) || 'either');
            var myPlace = 'either';
            if (schedPill) {
                myPlace = schedPill.getAttribute('data-place') || schedPill.dataset.place || 'either';
            } else if (scheduleHidden) {
                myPlace = scheduleHidden.getAttribute('data-my-place') || 'either';
            }
            // both/either を正規化し、会場確定を「自分優先（自分未定なら相手）」で統一
            selectedPlace = (selectedPlace === 'both') ? 'either' : selectedPlace;
            myPlace = (myPlace === 'both') ? 'either' : myPlace;
            if (myPlace === 'home' || myPlace === 'away') {
                selectedPlace = myPlace;
            } else if (!selectedPlace || selectedPlace === 'either') {
                selectedPlace = 'home';
            }
            if (!selectedPlace || selectedPlace === 'both') {
                selectedPlace = 'home';
            }
            const genderPill = form ? form.querySelector('.match-pill[data-type="gender"].selected') : null;
            const genderHidden = form ? form.querySelector('input[name="selected_gender"]') : null;
            var selectedGender = genderPill ? genderPill.dataset.value : (genderHidden ? genderHidden.value : (cfg.otherSchedule && cfg.otherSchedule.gender) || 'both');
            const matchRequestBodyOther = {
                my_schedule_id: parseInt(myScheduleId, 10) || 0,
                other_schedule_id: parseInt(otherScheduleId, 10) || 0,
                other_team_id: parseInt(otherTeamId, 10) || 0,
                selected_start_time: (cfg.otherSchedule && cfg.otherSchedule.start) || '',
                selected_end_time: (cfg.otherSchedule && cfg.otherSchedule.end) || '',
                selected_place: selectedPlace,
                selected_gender: selectedGender
            };

            fetch(aiduniteMatchRequestUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': aiduniteWpRestNonce
                },
                body: JSON.stringify(matchRequestBodyOther)
            })
                .then(function(r) { return r.text().then(function(t) { try { return { ok: r.ok, data: JSON.parse(t) }; } catch(e) { return { ok: r.ok, data: null, raw: t }; } }); })
                .then(function(res) {
                    if (res.ok && res.data && res.data.success) {
                        // 比較モードと同じ：1秒後にローダー非表示 → 完了メッセージ表示 → 1.5秒後にリダイレクト
                        setTimeout(function() {
                            if (fullLoader) fullLoader.style.display = 'none';
                            var completionMessage = document.getElementById('completion-message');
                            if (completionMessage) completionMessage.style.display = 'flex';
                            setTimeout(function() {
                                var url = (cfg.matchBoardUrl || '') + '#progress-view';
                                window.location.replace(url + '?refresh=' + Date.now());
                            }, 1500);
                        }, 1000);
                    } else {
                        if (fullLoader) fullLoader.style.display = 'none';
                        var msg = (res.data && res.data.data && res.data.data.message) || (res.data && res.data.message) || '申請に失敗しました';
                        aiduniteToast(msg, 'error');
                        applyButtonOtherOnly.disabled = false;
                        applyButtonOtherOnly.innerHTML = applyButtonOtherOnly.originalHTML || originalHTML;
                    }
                })
                .catch(function() {
                    if (fullLoader) fullLoader.style.display = 'none';
                    aiduniteToast('申請に失敗しました。時間をおいてお試しください', 'error');
                    applyButtonOtherOnly.disabled = false;
                    applyButtonOtherOnly.innerHTML = applyButtonOtherOnly.originalHTML || originalHTML;
                });
        });
    }

    // キャンセルボタンの処理
    if (cancelButton) {
        cancelButton.addEventListener('click', function() {
            if (this.disabled) return;

            // ボタンを無効化してローディング表示
            this.disabled = true;
            const originalHTML = this.innerHTML;
            this.innerHTML = '<span class="button-loading-spinner"></span>キャンセル中...';

            // フルスクリーンローダーを表示
            if (fullLoader && loaderText) {
                loaderText.textContent = 'キャンセル中';
                fullLoader.style.display = 'flex';
            }

            // わくわく感を演出するテキスト変更
            const loadingTexts = ['キャンセル中...', '処理中...', '送信中...', '完了間近...'];
            let textIndex = 0;
            const textInterval = setInterval(() => {
                textIndex = (textIndex + 1) % loadingTexts.length;
                this.innerHTML = '<span class="button-loading-spinner"></span>' + loadingTexts[textIndex];
                if (loaderText) {
                    loaderText.textContent = loadingTexts[textIndex];
                }
            }, 800);

            // テキスト変更を停止するためのタイマーIDと元のHTMLを保存
            this.textInterval = textInterval;
            this.originalHTML = originalHTML;

            // ステータス更新APIに統一（旧キャンセル専用APIは使わない）
            const requestId = this.getAttribute('data-request-id');
            if (!requestId) {
                if (this.textInterval) {
                    clearInterval(this.textInterval);
                }
                if (fullLoader) fullLoader.style.display = 'none';
                showErrorAnimation('キャンセル対象の申請IDを取得できませんでした。ページを再読み込みしてください。');
                this.disabled = false;
                this.innerHTML = this.originalHTML || 'キャンセル';
                this.style.background = '';
                return;
            }
            const payload = new FormData();
            payload.append('action','au_update_match_request_status');
            payload.append('security',cfg.matchNonce || '');
            payload.append('request_id', String(requestId));
            payload.append('status', 'canceled');

            fetch(cfg.ajaxUrl || '', { method:'POST', body: payload })
              .then(async r=>{
                  let data = null; let text = '';
                  try { text = await r.text(); data = JSON.parse(text); } catch(e) { /* not json */ }

                  if (r.ok && data && data.success) {
                      // テキスト変更を停止
                      if (this.textInterval) {
                          clearInterval(this.textInterval);
                      }

                      // 成功時：わくわく感を演出するため少し待ってから完了メッセージを表示
                      setTimeout(() => {
                          this.innerHTML = (typeof AidUniteThemeIcons !== 'undefined' ? AidUniteThemeIcons.html('check_circle', 18) + ' ' : '') + 'キャンセル完了しました！';
                          this.style.background = 'var(--success-color)'; if (loaderText) loaderText.textContent = '完了！';

                          setTimeout(() => {
                              if (fullLoader) fullLoader.style.display = 'none'; window.location.reload();
                          }, 2000);
                      }, 2000);
                  } else {
                      // テキスト変更を停止
                      if (this.textInterval) {
                          clearInterval(this.textInterval);
                      }

                      console.error('Cancel failed:', r.status, text || data);
                      showErrorAnimation('キャンセルに失敗しました。（' + ((data&&data.data&&data.data.message) || (data&&data.message) || r.status) + '）');
                      // ボタンを再有効化
                      this.disabled = false;
                      this.innerHTML = this.originalHTML || 'キャンセル';
                      this.style.background = '';
                  }
              })
              .catch(()=>{
                  // テキスト変更を停止
                  if (this.textInterval) {
                      clearInterval(this.textInterval);
                  }

                  showErrorAnimation('キャンセルに失敗しました。時間をおいてお試しください');
                  // ボタンを再有効化
                  this.disabled = false;
                  this.innerHTML = this.originalHTML || 'キャンセル';
                  this.style.background = '';
              });
        });
    }



    // 承認ボタンの処理
    const approveButton = document.getElementById('approveButton');
    if (approveButton) {
        approveButton.addEventListener('click', function() {
            if (this.disabled) return;
            const requestId = (this.getAttribute('data-request-id') || '').trim();
            if (!requestId) {
                showErrorAnimation('承認対象の申請IDを取得できませんでした。ページを再読み込みしてください。');
                return;
            }

            // ボタンを無効化してローディング表示
            this.disabled = true;
            this.dataset.originalHtml = this.innerHTML;
            this.innerHTML = '<span style="display:inline-block; width:18px; height:18px; border:3px solid rgba(255,255,255,0.3); border-top:3px solid #fff; border-radius:50%; animation:spin 0.8s linear infinite; margin-right:10px;"></span>承認中...';

            // フルスクリーンローダーを表示
            if (fullLoader && loaderText) {
                loaderText.textContent = '承認中';
                fullLoader.style.display = 'flex';
            }

            // テキスト変更
            const loadingTexts = ['承認中...', '処理中...', '送信中...', '完了間近...'];
            let textIndex = 0;
            const textInterval = setInterval(() => {
                textIndex = (textIndex + 1) % loadingTexts.length;
                this.innerHTML = '<span style="display:inline-block; width:18px; height:18px; border:3px solid rgba(255,255,255,0.3); border-top:3px solid #fff; border-radius:50%; animation:spin 0.8s linear infinite; margin-right:10px;"></span>' + loadingTexts[textIndex];
                if (loaderText) {
                    loaderText.textContent = loadingTexts[textIndex];
                }
            }, 800);

            // テキスト変更を停止するためのタイマーIDを保存
            this.textInterval = textInterval;

                updateMatchRequestStatus(requestId, 'accepted', this);
        });
    }

    // 拒否ボタンの処理
    const rejectButton = document.getElementById('rejectButton');
    if (rejectButton) {
        rejectButton.addEventListener('click', function() {
            if (this.disabled) return;
            const requestId = (this.getAttribute('data-request-id') || '').trim();
            if (!requestId) {
                showErrorAnimation('拒否対象の申請IDを取得できませんでした。ページを再読み込みしてください。');
                return;
            }

            // ボタンを無効化してローディング表示
            this.disabled = true;
            this.dataset.originalHtml = this.innerHTML;
            this.innerHTML = '<span style="display:inline-block; width:18px; height:18px; border:3px solid rgba(255,255,255,0.3); border-top:3px solid #fff; border-radius:50%; animation:spin 0.8s linear infinite; margin-right:10px;"></span>拒否中...';

            // フルスクリーンローダーを表示
            if (fullLoader && loaderText) {
                loaderText.textContent = '拒否中';
                fullLoader.style.display = 'flex';
            }

            // テキスト変更
            const loadingTexts = ['拒否中...', '処理中...', '送信中...', '完了間近...'];
            let textIndex = 0;
            const textInterval = setInterval(() => {
                textIndex = (textIndex + 1) % loadingTexts.length;
                this.innerHTML = '<span style="display:inline-block; width:18px; height:18px; border:3px solid rgba(255,255,255,0.3); border-top:3px solid #fff; border-radius:50%; animation:spin 0.8s linear infinite; margin-right:10px;"></span>' + loadingTexts[textIndex];
                if (loaderText) {
                    loaderText.textContent = loadingTexts[textIndex];
                }
            }, 800);

            // テキスト変更を停止するためのタイマーIDを保存
            this.textInterval = textInterval;

                updateMatchRequestStatus(requestId, 'rejected', this);
        });
    }

    // 再申請ボタンの処理
    const reapplyButton = document.getElementById('reapplyButton');
    if (reapplyButton) {
        reapplyButton.addEventListener('click', function() {
            if (this.disabled) return;

            // ボタンを無効化してローディング表示（統一されたクラスを使用）
            this.disabled = true;
            const originalHTML = this.innerHTML;
            this.innerHTML = '<span class="button-loading-spinner"></span><span>再申請中...</span>';
            this.originalHTML = originalHTML;

            // フルスクリーンローダーを表示（スケジュール登録と同じ仕様）
            if (fullLoader && loaderText) {
                loaderText.textContent = '再申請中...';
                fullLoader.style.display = 'flex';
            }

            // 再申請: 重なり時間・会場・性別をフォーム／既定値から取得
            const timeDisplayOnly = document.querySelector('.match-detail-time-display-only');
            const activeStartSelect = document.getElementById('start-time-select-detail');
            const activeEndSelect = document.getElementById('end-time-select-detail');
            const placeSel = document.querySelector('[data-type="place"].selected');
            const genderSel = document.querySelector('[data-type="gender"].selected');
            const genderChoiceSelect = document.querySelector('[name="gender_choice"]');
            const reapplyBtnEl = document.getElementById('reapplyButton');
            const defaultPlaceFromBtn = (reapplyBtnEl && reapplyBtnEl.dataset.defaultPlace) ? reapplyBtnEl.dataset.defaultPlace : '';
            const defaultGenderFromBtn = (reapplyBtnEl && reapplyBtnEl.dataset.defaultGender) ? reapplyBtnEl.dataset.defaultGender : '';
            const reapplyGenderValue = (genderChoiceSelect && genderChoiceSelect.value) ? genderChoiceSelect.value : (genderSel ? genderSel.dataset.value : (defaultGenderFromBtn || cfg.resolvedGender || 'both'));

            let selectedStartTime = '';
            let selectedEndTime = '';
            if (timeDisplayOnly && timeDisplayOnly.dataset.overlapStart && timeDisplayOnly.dataset.overlapEnd) {
                selectedStartTime = timeDisplayOnly.dataset.overlapStart;
                selectedEndTime = timeDisplayOnly.dataset.overlapEnd;
            } else if (activeStartSelect && activeEndSelect) {
                selectedStartTime = activeStartSelect.value;
                selectedEndTime = activeEndSelect.value;
            }
            let placeValue = placeSel ? placeSel.dataset.value : '';
            if (!placeValue) {
                placeValue = defaultPlaceFromBtn || cfg.resolvedPlace || 'home';
            }

            if (activeStartSelect && activeEndSelect) {
                const startTime = activeStartSelect.value;
                const endTime = activeEndSelect.value;
                if (startTime && endTime) {
                    if (startTime >= endTime) {
                        showErrorAnimation('終了時間は開始時間より後にしてください。');
                        this.disabled = false;
                        this.innerHTML = this.originalHTML || '再申請する';
                        this.style.background = '';
                        return;
                    }
                }
            }
            // 申請ボタンと同じフォールバック（再申請でも重なり時間または既存時間を採用）
            if (!selectedStartTime || !selectedEndTime) {
                const myScheduleStart = (cfg.mySchedule && cfg.mySchedule.start) || '';
                const myScheduleEnd = (cfg.mySchedule && cfg.mySchedule.end) || '';
                const otherScheduleStart = (cfg.otherSchedule && cfg.otherSchedule.start) || '';
                const otherScheduleEnd = (cfg.otherSchedule && cfg.otherSchedule.end) || '';
                if (myScheduleStart && myScheduleEnd && otherScheduleStart && otherScheduleEnd) {
                    const overlapStart = myScheduleStart > otherScheduleStart ? myScheduleStart : otherScheduleStart;
                    const overlapEnd = myScheduleEnd < otherScheduleEnd ? myScheduleEnd : otherScheduleEnd;
                    if (overlapStart < overlapEnd) {
                        selectedStartTime = overlapStart;
                        selectedEndTime = overlapEnd;
                    }
                }
                if (!selectedStartTime || !selectedEndTime) {
                    selectedStartTime = myScheduleStart || otherScheduleStart || '';
                    selectedEndTime = myScheduleEnd || otherScheduleEnd || '';
                }
            }
            if (!selectedStartTime || !selectedEndTime) {
                showErrorAnimation('時間データが取得できませんでした。');
                this.disabled = false;
                this.innerHTML = this.originalHTML || '再申請する';
                this.style.background = '';
                return;
            }

            const matchRequestBodyReapply = {
                my_schedule_id: parseInt(String(cfg.myScheduleId || 0), 10) || 0,
                other_schedule_id: parseInt(String(cfg.otherScheduleId || 0), 10) || 0,
                match_request_id: parseInt(String(cfg.matchRequestId || 0), 10) || 0,
                selected_start_time: selectedStartTime,
                selected_end_time: selectedEndTime,
                selected_place: placeValue,
                selected_gender: reapplyGenderValue
            };

            fetch(aiduniteMatchRequestUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': aiduniteWpRestNonce
                },
                body: JSON.stringify(matchRequestBodyReapply)
            })
            .then(async response => {
                let data = null;
                let text = '';
                try {
                    text = await response.text();
                    data = JSON.parse(text);
                } catch(e) {
                    console.error('JSON parse error:', e);
                }

                if (response.ok && data && data.success) {
                    // 1秒後にローディングスピナーを非表示（スケジュール登録と同じ仕様）
                    setTimeout(() => {
                        if (fullLoader) {
                            fullLoader.style.display = 'none';
                        }

                        // 完了メッセージを表示
                        const completionMessage = document.getElementById('completion-message');
                        if (completionMessage) {
                            completionMessage.style.display = 'flex';
                        }

                        // 2.5秒後にリダイレクト（データベース反映を待つため1.5秒待機）
                        setTimeout(() => {
                            const url = (cfg.matchBoardUrl || '') + '#progress-view';
                            const timestamp = Date.now();
                            window.location.replace(`${url}?refresh=${timestamp}`);
                        }, 1500);
                    }, 1000);
                } else {
                    console.error('Reapply failed:', response.status, text || data);
                    // エラーメッセージを表示
                    const errorMessage = (data && data.data && data.data.message) || (data && data.message) || '再申請に失敗しました';
                    showErrorAnimation(errorMessage);
                    // ローディングスピナーを非表示
                    if (fullLoader) {
                        fullLoader.style.display = 'none';
                    }
                    // ボタンを再有効化
                    this.disabled = false;
                    this.innerHTML = this.originalHTML || '再申請する';
                    this.style.background = '';
                }
            })
            .catch(error => {
                console.error('Reapply error:', error);
                // ローディングスピナーを非表示
                if (fullLoader) {
                    fullLoader.style.display = 'none';
                }

                showErrorAnimation('再申請に失敗しました。時間をおいてお試しください');
                // ボタンを再有効化
                this.disabled = false;
                this.innerHTML = this.originalHTML || '再申請する';
                this.style.background = '';
            });
        });
    }

    function callMatchAjaxAction(actionName, requestId, actionButton, loadingText) {
        if (!requestId) {
            showErrorAnimation('申請IDを取得できませんでした。ページを再読み込みしてください。');
            return;
        }
        actionButton.disabled = true;
        actionButton.dataset.originalHtml = actionButton.innerHTML;
        actionButton.innerHTML = '<span class="button-loading-spinner"></span><span>' + loadingText + '</span>';
        if (fullLoader && loaderText) {
            loaderText.textContent = loadingText;
            fullLoader.style.display = 'flex';
        }
        const payload = new FormData();
        payload.append('action', actionName);
        payload.append('security', cfg.matchNonce || '');
        payload.append('request_id', String(requestId));
        fetch(cfg.ajaxUrl || '', {
            method: 'POST',
            body: payload
        })
        .then(async (response) => {
            let json = null;
            try {
                json = await response.json();
            } catch (e) {}
            if (json && json.success) {
                setTimeout(function() { location.reload(); }, 500);
                return;
            }
            const err = (json && json.data && (json.data.message || json.data)) || (json && json.message) || '処理に失敗しました';
            showErrorAnimation(String(err));
            if (fullLoader) fullLoader.style.display = 'none';
            actionButton.disabled = false;
            actionButton.innerHTML = actionButton.dataset.originalHtml || '実行';
        })
        .catch(() => {
            showErrorAnimation('処理に失敗しました');
            if (fullLoader) fullLoader.style.display = 'none';
            actionButton.disabled = false;
            actionButton.innerHTML = actionButton.dataset.originalHtml || '実行';
        });
    }

    const proposalButton = document.getElementById('proposalButton');
    if (proposalButton) {
        proposalButton.addEventListener('click', function() {
            const requestId = (this.getAttribute('data-request-id') || '').trim();
            callMatchAjaxAction('au_propose_reconfirm_conditions', requestId, this, '提案中...');
        });
    }

    const acceptProposalButton = document.getElementById('acceptProposalButton');
    if (acceptProposalButton) {
        acceptProposalButton.addEventListener('click', function() {
            const requestId = (this.getAttribute('data-request-id') || '').trim();
            callMatchAjaxAction('au_accept_reconfirm_proposal', requestId, this, '承諾中...');
        });
    }

    function showChatRedirectModal() {
        return new Promise(function(resolve) {
            const modal = document.getElementById('chat-redirect-modal');
            const goBtn = document.getElementById('chat-redirect-go-btn');
            const stayBtn = document.getElementById('chat-redirect-stay-btn');

            if (!modal || !goBtn || !stayBtn) {
                resolve(false);
                return;
            }

            const close = function(goToChat) {
                modal.style.display = 'none';
                modal.setAttribute('aria-hidden', 'true');
                goBtn.removeEventListener('click', onGo);
                stayBtn.removeEventListener('click', onStay);
                modal.removeEventListener('click', onBackdrop);
                resolve(goToChat);
            };
            const onGo = function() { close(true); };
            const onStay = function() { close(false); };
            const onBackdrop = function(e) {
                if (e.target === modal) {
                    close(false);
                }
            };

            goBtn.addEventListener('click', onGo);
            stayBtn.addEventListener('click', onStay);
            modal.addEventListener('click', onBackdrop);
            modal.style.display = 'flex';
            modal.setAttribute('aria-hidden', 'false');
        });
    }

    function updateMatchRequestStatus(requestId, status, actionButton = null) {
        const requestKey = String(requestId) + ':' + String(status);
        window.__aiduniteMatchStatusInFlight = window.__aiduniteMatchStatusInFlight || {};
        if (window.__aiduniteMatchStatusInFlight[requestKey]) {
            return;
        }
        window.__aiduniteMatchStatusInFlight[requestKey] = true;

        const restoreActionButton = () => {
            if (!actionButton) return;
            if (actionButton.textInterval) {
                clearInterval(actionButton.textInterval);
                actionButton.textInterval = null;
            }
            actionButton.disabled = false;
            if (actionButton.dataset && actionButton.dataset.originalHtml) {
                actionButton.innerHTML = actionButton.dataset.originalHtml;
            }
        };

        // 実際の処理実行
        const payload = new FormData();
        payload.append('action', 'au_update_match_request_status');
        payload.append('security', cfg.matchNonce || '');
        payload.append('request_id', requestId);
        payload.append('status', status);

        fetch(cfg.ajaxUrl || '', {
            method: 'POST',
            body: payload
        })
        .then(r => r.json())
        .then(async json => {
            if (json && json.success) {
                const data = json.data || {};
                const isAccepted = status === 'accepted';

                if (isAccepted && data.show_onboarding_bot_chat_modal) {
                    if (typeof showToastNotification !== 'undefined') {
                        showToastNotification('試合が成立しました。', 'success');
                    }
                    if (typeof window.aiduniteShowOnboardingBotChatModal === 'function') {
                        window.aiduniteShowOnboardingBotChatModal(data.chat_url || '');
                        return;
                    }
                }

                const chatRedirectUrl = (typeof data.redirect_url === 'string') ? data.redirect_url : '';

                if (isAccepted && chatRedirectUrl) {
                    if (typeof showToastNotification !== 'undefined' && !window.__aiduniteApprovalToastShown) {
                        window.__aiduniteApprovalToastShown = true;
                        showToastNotification('試合成立おめでとうございます。専用チャットへ移動できます。', 'success');
                    }
                    const goToChat = await showChatRedirectModal();
                    if (goToChat) {
                        window.location.href = chatRedirectUrl;
                        return;
                    }
                }

                setTimeout(function() { location.reload(); }, 800);
            } else {
                window.__aiduniteMatchStatusInFlight[requestKey] = false;
                if (fullLoader) fullLoader.style.display = 'none';
                restoreActionButton();
                const err = (json && json.data && (json.data.message || json.data)) || (json && json.message) || '更新に失敗しました';
                showErrorAnimation(String(err));
            }
        })
        .catch(() => {
            window.__aiduniteMatchStatusInFlight[requestKey] = false;
            if (fullLoader) fullLoader.style.display = 'none';
            restoreActionButton();
            showErrorAnimation('更新に失敗しました');
        });
    }

    updateApplyButton();

    // チェックリストの保存機能
    const checklistItems = document.querySelectorAll('.checklist-item');
    checklistItems.forEach(item => {
        item.addEventListener('change', function() {
            const itemId = this.getAttribute('data-item-id');
            const checklistKey = this.getAttribute('data-checklist-key');
            const isChecked = this.checked;

            // LocalStorageに保存
            let checklistData = JSON.parse(localStorage.getItem(checklistKey) || '{}');
            checklistData[itemId] = isChecked;
            localStorage.setItem(checklistKey, JSON.stringify(checklistData));

            // サーバーにも保存（オプション）
            fetch(cfg.ajaxUrl || '', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'aidunite_save_checklist',
                    checklist_key: checklistKey,
                    item_id: itemId,
                    checked: isChecked ? '1' : '0',
                    security: cfg.checklistNonce || ''
                })
            }).catch(error => {
                console.error('チェックリストの保存に失敗しました:', error);
            });
        });
    });

    var compareToggle = document.getElementById('matchDetailCompareToggle');
    var comparePanel = document.getElementById('matchDetailComparePanel');
    if (compareToggle && comparePanel) {
        compareToggle.addEventListener('click', function () {
            var expanded = compareToggle.getAttribute('aria-expanded') === 'true';
            compareToggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            comparePanel.hidden = expanded;
        });
    }
  });
})();

