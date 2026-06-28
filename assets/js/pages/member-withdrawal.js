/**
 * 退会申請（page-member-withdrawal.php）
 */
(function () {
  'use strict';

  function init() {
    var form = document.getElementById('member-withdrawal-form');
    if (!form) return;

    var cfg = typeof aiduniteMemberWithdrawalPage !== 'undefined' ? aiduniteMemberWithdrawalPage : {};
    var submitButton = document.getElementById('submit-button');
    var loading = document.getElementById('loading');
    var errorMessage = document.getElementById('error-message');
    var successMessage = document.getElementById('success-message');
    var finalConfirmation = document.getElementById('final_confirmation');
    var repSection = document.getElementById('rep-withdrawal-choice-section');
    var repChoiceTransfer = document.getElementById('rep_choice_transfer');
    var repChoiceDissolve = document.getElementById('rep_choice_dissolve');
    var repTransferLinkWrap = document.getElementById('rep-transfer-link-wrap');
    var repChoiceRequiredNote = document.getElementById('rep-choice-required-note');
    var isRep = !!repSection;

    if (isRep) {
      function updateRepChoiceUI() {
        var dissolve = repChoiceDissolve && repChoiceDissolve.checked;
        if (repTransferLinkWrap) {
          repTransferLinkWrap.style.display = repChoiceTransfer && repChoiceTransfer.checked ? 'block' : 'none';
        }
        if (repChoiceRequiredNote) {
          repChoiceRequiredNote.style.display = repChoiceTransfer && repChoiceTransfer.checked ? 'block' : 'none';
        }
        if (submitButton) submitButton.disabled = !dissolve;
      }
      if (repChoiceTransfer) repChoiceTransfer.addEventListener('change', updateRepChoiceUI);
      if (repChoiceDissolve) repChoiceDissolve.addEventListener('change', updateRepChoiceUI);
      updateRepChoiceUI();
    }

    function validateFinalConfirmation() {
      if (!finalConfirmation) return;
      if (finalConfirmation.value !== '退会する') {
        finalConfirmation.setCustomValidity('「退会する」と正確に入力してください');
      } else {
        finalConfirmation.setCustomValidity('');
      }
    }

    if (finalConfirmation) finalConfirmation.addEventListener('input', validateFinalConfirmation);

    form.addEventListener('submit', function (e) {
      e.preventDefault();

      if (isRep && (!repChoiceDissolve || !repChoiceDissolve.checked)) {
        if (errorMessage) {
          errorMessage.textContent = '退会するには「チームを解散して退会する」を選択してください。代表者を譲る場合はチーム設定から行ってから退会申請してください。';
          errorMessage.style.display = 'block';
        }
        if (repChoiceRequiredNote) repChoiceRequiredNote.style.display = 'block';
        return;
      }

      if (!finalConfirmation || finalConfirmation.value !== '退会する') {
        if (errorMessage) {
          errorMessage.textContent = '最終確認の入力が正しくありません。';
          errorMessage.style.display = 'block';
        }
        return;
      }

      if (typeof aiduniteConfirm !== 'function') {
        form.submit();
        return;
      }

      aiduniteConfirm({
        message: '本当に退会申請を提出しますか？\nこの操作は取り消すことができません。',
        onConfirm: function () {
          var formData = new FormData(form);
          if (submitButton) submitButton.disabled = true;
          if (loading) loading.style.display = 'block';
          if (errorMessage) errorMessage.style.display = 'none';
          if (successMessage) successMessage.style.display = 'none';

          fetch(cfg.ajaxUrl || '', {
            method: 'POST',
            body: formData,
          })
            .then(function (response) {
              return response.json();
            })
            .then(function (data) {
              if (loading) loading.style.display = 'none';
              if (data.success) {
                if (successMessage) {
                  successMessage.textContent = data.data.message || '退会確認メールを送信しました。メールのリンクから退会を完了してください。';
                  successMessage.style.display = 'block';
                }
                form.reset();
                setTimeout(function () {
                  window.location.href = cfg.logoutUrl || '/';
                }, 10000);
              } else {
                if (errorMessage) {
                  errorMessage.textContent = (data.data && data.data.message) || '退会申請に失敗しました。';
                  errorMessage.style.display = 'block';
                }
                if (submitButton) submitButton.disabled = false;
              }
            })
            .catch(function (error) {
              if (loading) loading.style.display = 'none';
              if (errorMessage) {
                errorMessage.textContent = '通信エラーが発生しました。';
                errorMessage.style.display = 'block';
              }
              if (submitButton) submitButton.disabled = false;
              console.error('Error:', error);
            });
        },
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
