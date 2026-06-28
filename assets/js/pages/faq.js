/**
 * page-faq.php — アコーディオン（Phase 4: キーボード・aria-expanded）
 */
(function () {
  'use strict';

  function setExpanded(question, expanded) {
    question.classList.toggle('active', expanded);
    question.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    var answer = question.nextElementSibling;
    if (answer && answer.classList.contains('faq-answer')) {
      answer.classList.toggle('active', expanded);
      answer.hidden = !expanded;
    }
  }

  function toggleFaq(element) {
    if (!element || !element.classList.contains('faq-question')) {
      return;
    }
    var isActive = element.classList.contains('active');

    document.querySelectorAll('.faq-question.active').forEach(function (item) {
      if (item !== element) {
        setExpanded(item, false);
      }
    });

    setExpanded(element, !isActive);
  }

  window.toggleFaq = toggleFaq;

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.faq-question').forEach(function (el) {
      el.setAttribute('role', 'button');
      el.setAttribute('tabindex', '0');
      el.setAttribute('aria-expanded', el.classList.contains('active') ? 'true' : 'false');
      var answer = el.nextElementSibling;
      if (answer && answer.classList.contains('faq-answer')) {
        answer.hidden = !el.classList.contains('active');
      }
      el.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          toggleFaq(el);
        }
      });
    });
  });
})();
