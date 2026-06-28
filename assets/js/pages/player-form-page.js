/**
 * 選手登録・編集（page-player-add.php / page-edit-player.php）
 */
(function () {
  'use strict';

  function initGradeAutoCalc() {
    if (typeof aiduniteBindPlayerGradeAutoCalc !== 'function') {
      return;
    }
    var birthId = 'player_birth_date';
    var gradeTargets = ['player_grade_display', 'player_grade'];
    if (document.getElementById('player_grade') && !document.getElementById('player_grade_display')) {
      aiduniteBindPlayerGradeAutoCalc(birthId, 'player_grade');
      return;
    }
    aiduniteBindPlayerGradeAutoCalc(birthId, gradeTargets);
  }

  function clearToastQueryParams() {
    var params = new URLSearchParams(window.location.search);
    if (!params.get('toast') || !params.get('message') || !window.history || !window.history.replaceState) {
      return;
    }
    var url = new URL(window.location.href);
    url.searchParams.delete('toast');
    url.searchParams.delete('message');
    window.history.replaceState({}, '', url);
  }

  function init() {
    initGradeAutoCalc();
    clearToastQueryParams();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
