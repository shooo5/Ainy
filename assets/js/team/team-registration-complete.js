(function () {
  'use strict';

  function scrollDetailsIntoView(details) {
    window.setTimeout(function () {
      details.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }, 80);
  }

  var legacyDetails = document.querySelector('.team-reg-complete-details');
  if (legacyDetails) {
    legacyDetails.addEventListener('toggle', function () {
      if (legacyDetails.open) {
        scrollDetailsIntoView(legacyDetails);
      }
    });
  }

  var pendingDetails = document.querySelectorAll('.team-reg-pending__details');
  if (!pendingDetails.length) {
    return;
  }

  pendingDetails.forEach(function (details) {
    details.addEventListener('toggle', function () {
      if (!details.open) {
        return;
      }

      pendingDetails.forEach(function (other) {
        if (other !== details) {
          other.open = false;
        }
      });

      scrollDetailsIntoView(details);
    });
  });
})();
