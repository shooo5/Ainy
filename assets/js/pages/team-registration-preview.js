/**
 * page-team-registration-preview.php
 */
(function () {
  'use strict';
  var cfg = typeof aidunitePage_team_registration_preview !== 'undefined' ? aidunitePage_team_registration_preview : {};

document.addEventListener('DOMContentLoaded', function() {
        const previewPageId = cfg.previewPageId || '';
        if (!previewPageId) {
            return;
        }
        const targetPage = document.getElementById(previewPageId);
        const previewArea = document.querySelector('.team-registration-preview-area');
        if (targetPage && previewArea) {
            targetPage.style.display = 'block';
            previewArea.innerHTML = '';
            previewArea.appendChild(targetPage);
            if (typeof startTeamRegistrationAnimations === 'function') {
                startTeamRegistrationAnimations();
            }
        }
    });

document.addEventListener('DOMContentLoaded', function() {
  const previewButtons = document.querySelectorAll('.preview-btn');
  const previewArea = document.querySelector('.team-registration-preview-area');
  const previewPages = document.querySelectorAll('.preview-page');
  const placeholder = document.querySelector('.preview-placeholder');

  // プレビューボタンのクリックイベント
  previewButtons.forEach(button => {
    button.addEventListener('click', function() {
      const previewType = this.dataset.preview;

      // ボタンのアクティブ状態を更新
      previewButtons.forEach(btn => btn.classList.remove('active'));
      this.classList.add('active');

      // プレビューページを表示
      previewPages.forEach(page => {
        page.style.display = 'none';
      });

      const targetPage = document.getElementById(`preview-${previewType}`);
      if (targetPage) {
        targetPage.style.display = 'block';
        previewArea.innerHTML = '';
        previewArea.appendChild(targetPage);

        if (previewType !== 'pending' && typeof startTeamRegistrationAnimations === 'function') {
          startTeamRegistrationAnimations();
        }
      }
    });
  });

  // アニメーション開始関数
  function startTeamRegistrationAnimations() {
    // フローティング要素のアニメーション
    const floatingElements = document.querySelectorAll('.team-registration-floating-element');
    floatingElements.forEach((element, index) => {
      const speed = 0.5 + (index * 0.2);
      let position = 0;

      function animate() {
        position += speed * 0.01;
        element.style.transform = `translateY(${Math.sin(position) * 15}px) rotate(${position * 20}deg)`;
        requestAnimationFrame(animate);
      }
      animate();
    });

    // 確認アイコンの特別なアニメーション
    const confirmationIcon = document.querySelector('.team-registration-confirmation-icon');
    if (confirmationIcon) {
      setInterval(() => {
        confirmationIcon.style.transform = 'scale(1.1)';
        setTimeout(() => {
          confirmationIcon.style.transform = 'scale(1)';
        }, 200);
      }, 3000);
    }

    // 成功時の特別なアニメーション
    const successIcon = document.querySelector('.team-registration-success-icon.success');
    if (successIcon) {
      setInterval(() => {
        successIcon.style.transform = 'scale(1.2) rotate(10deg)';
        setTimeout(() => {
          successIcon.style.transform = 'scale(1) rotate(0deg)';
        }, 300);
      }, 2000);
    }

    // エラー時の特別なアニメーション
    const errorIcon = document.querySelector('.team-registration-error-icon');
    if (errorIcon) {
      setInterval(() => {
        errorIcon.style.transform = 'scale(1.1)';
        setTimeout(() => {
          errorIcon.style.transform = 'scale(1)';
        }, 200);
      }, 3000);
    }
  }
});
})();
