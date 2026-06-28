/**
 * page-registration-preview.php
 */
(function () {
  'use strict';
  var cfg = typeof aidunitePage_registration_preview !== 'undefined' ? aidunitePage_registration_preview : {};

  function startAnimations() {
    const floatingElements = document.querySelectorAll('.registration-floating-element');
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

    const mailIcon = document.querySelector('.registration-email-icon');
    if (mailIcon) {
      setInterval(() => {
        mailIcon.style.transform = 'scale(1.1)';
        setTimeout(() => {
          mailIcon.style.transform = 'scale(1)';
        }, 200);
      }, 3000);
    }

    const successIcon = document.querySelector('.registration-success-icon.success');
    if (successIcon) {
      setInterval(() => {
        successIcon.style.transform = 'scale(1.2) rotate(10deg)';
        setTimeout(() => {
          successIcon.style.transform = 'scale(1) rotate(0deg)';
        }, 300);
      }, 2000);
    }

    const errorIcon = document.querySelector('.registration-error-icon');
    if (errorIcon) {
      setInterval(() => {
        errorIcon.style.transform = 'scale(1.1)';
        setTimeout(() => {
          errorIcon.style.transform = 'scale(1)';
        }, 200);
      }, 3000);
    }
  }

document.addEventListener('DOMContentLoaded', function() {
        const previewPageId = cfg.previewPageId || '';
        if (!previewPageId) {
            return;
        }
        const targetPage = document.getElementById(previewPageId);
        const previewArea = document.querySelector('.registration-preview-area');
        if (targetPage && previewArea) {
            targetPage.style.display = 'block';
            previewArea.innerHTML = '';
            previewArea.appendChild(targetPage);
            startAnimations();
        }
    });

document.addEventListener('DOMContentLoaded', function() {
  const previewButtons = document.querySelectorAll('.preview-btn');
  const previewArea = document.querySelector('.registration-preview-area');
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

        // アニメーションを開始
        startAnimations();
      }
    });
  });
});
})();
