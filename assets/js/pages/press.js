/**
 * page-press.php
 */
(function () {
  'use strict';
  var cfg = typeof aidunitePage_press !== 'undefined' ? aidunitePage_press : {};

document.addEventListener('DOMContentLoaded', function() {
  // フィルター機能
  const filterBtns = document.querySelectorAll('.filter-btn');
  const pressItems = document.querySelectorAll('.press-item');

  filterBtns.forEach(btn => {
    btn.addEventListener('click', function() {
      const filter = this.getAttribute('data-filter');

      // アクティブボタンの切り替え
      filterBtns.forEach(b => b.classList.remove('active'));
      this.classList.add('active');

      // アイテムの表示/非表示
      pressItems.forEach(item => {
        if (filter === 'all' || item.getAttribute('data-category') === filter) {
          item.style.display = 'block';
        } else {
          item.style.display = 'none';
        }
      });
    });
  });

  // モーダル機能
  const modal = document.getElementById('pressModal');
  const modalContent = document.getElementById('modalContent');
  const closeBtn = document.querySelector('.close');
  const readMoreBtns = document.querySelectorAll('.read-more-btn');

  readMoreBtns.forEach(btn => {
    btn.addEventListener('click', function() {
      const pressItem = this.closest('.press-item');
      const title = pressItem.querySelector('.press-title').textContent;
      const meta = pressItem.querySelector('.press-meta').innerHTML;
      const content = pressItem.querySelector('.press-content').innerHTML;

      modalContent.innerHTML = `
        <h3>${title}</h3>
        <div class="press-meta">${meta}</div>
        <div class="press-content">${content}</div>
      `;

      modal.style.display = 'block';
    });
  });

  closeBtn.addEventListener('click', function() {
    modal.style.display = 'none';
  });

  window.addEventListener('click', function(event) {
    if (event.target === modal) {
      modal.style.display = 'none';
    }
  });
});
})();
