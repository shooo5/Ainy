(function () {
  'use strict';

  var details = document.querySelector('.team-reg-complete-details');
  if (!details) {
    return;
  }

  details.addEventListener('toggle', function () {
    if (!details.open) {
      return;
    }
    window.setTimeout(function () {
      details.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }, 80);
  });
})();
