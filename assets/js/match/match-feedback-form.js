/**
 * 試合後振り返り — 星評価・文字数・送信
 */
(function ($) {
  'use strict';

  var l10n = window.aiduniteMatchFeedbackL10n || {};
  var starHints = l10n.starHints || {
    1: '改善の余地あり',
    2: 'いまいち',
    3: 'ふつう',
    4: '良かった',
    5: 'とても良かった',
  };

  function starValueFromLabel($label) {
    var v = parseInt($label.data('star-value'), 10);
    if (!Number.isNaN(v)) {
      return v;
    }
    var forId = $label.attr('for');
    if (!forId) {
      return NaN;
    }
    return parseInt($('#' + forId).val(), 10);
  }

  function applyLit($group, upToValue, className) {
    className = className || 'is-lit';
    $group.find('.star-rating__label').each(function () {
      var $label = $(this);
      var value = starValueFromLabel($label);
      $label.toggleClass(className, !Number.isNaN(upToValue) && !Number.isNaN(value) && value <= upToValue);
    });
  }

  function updateStarHint($group) {
    var name = $group.data('star-rating');
    var $hint = $('[data-star-hint-for="' + name + '"]');
    if (!$hint.length) {
      return;
    }
    var checked = parseInt($group.find('.star-rating__input:checked').val(), 10);
    if (Number.isNaN(checked) || !starHints[checked]) {
      $hint.text('');
      return;
    }
    $hint.text(starHints[checked]);
  }

  function syncStarGroup($group) {
    var checked = parseInt($group.find('.star-rating__input:checked').val(), 10);
    $group.find('.star-rating__label').removeClass('is-lit is-hover-lit');
    if (!checked || Number.isNaN(checked)) {
      $group.removeClass('is-selected');
      updateStarHint($group);
      return;
    }
    $group.addClass('is-selected');
    applyLit($group, checked, 'is-lit');
    updateStarHint($group);
  }

  function initStarRatings(context) {
    $(context || document)
      .find('[data-star-rating]')
      .each(function () {
        var $group = $(this);
        if ($group.data('starRatingBound')) {
          return;
        }
        $group.data('starRatingBound', true);
        syncStarGroup($group);
        $group.find('.star-rating__input').on('change', function () {
          syncStarGroup($group);
        });
        $group.find('.star-rating__label').on('mouseenter', function () {
          var hoverValue = starValueFromLabel($(this));
          if (Number.isNaN(hoverValue)) {
            return;
          }
          $group.find('.star-rating__label').removeClass('is-hover-lit');
          applyLit($group, hoverValue, 'is-hover-lit');
        });
        $group.on('mouseleave', function () {
          $group.find('.star-rating__label').removeClass('is-hover-lit');
          syncStarGroup($group);
        });
      });
  }

  function initCharCount(context) {
    $(context || document)
      .find('[data-char-count]')
      .each(function () {
        var $ta = $(this);
        var targetId = $ta.data('char-count');
        var $counter = $('#' + targetId);
        if (!$counter.length) {
          return;
        }
        var max = parseInt($ta.attr('maxlength'), 10) || 300;
        var sync = function () {
          var len = ($ta.val() || '').length;
          $counter.text(len + '/' + max);
        };
        sync();
        $ta.on('input', sync);
      });
  }

  function initFormSubmit() {
    var $form = $('#match-feedback-form');
    if (!$form.length || $form.data('mfSubmitBound')) {
      return;
    }
    $form.data('mfSubmitBound', true);

    $form.on('submit', function (e) {
      e.preventDefault();
      if (!l10n.restUrl) {
        return;
      }

      var formData = {
        match_id: $form.find('input[name="match_id"]').val(),
        team_id: $form.find('input[name="team_id"]').val(),
        satisfaction: $form.find('input[name="satisfaction"]:checked').val(),
        satisfaction_reasons: $form.find('input[name="satisfaction_reasons[]"]:checked')
          .map(function () {
            return $(this).val();
          })
          .get(),
        opponent_rating: $form.find('input[name="opponent_rating"]:checked').val(),
        opponent_reasons: [],
        venue_rating: $form.find('input[name="venue_rating"]:checked').val() || '',
        venue_improvement: $form.find('textarea[name="venue_improvement"]').val(),
        rematch_interest: $form.find('input[name="rematch_interest"]:checked').val(),
        rematch_reason: $form.find('textarea[name="rematch_reason"]').val(),
        comment: $form.find('textarea[name="comment"]').val(),
      };

      var $btn = $form.find('[data-testid="survey-submit-button"]');
      $btn.prop('disabled', true);

      $.ajax({
        url: l10n.restUrl,
        method: 'POST',
        data: formData,
        beforeSend: function (xhr) {
          if (l10n.restNonce) {
            xhr.setRequestHeader('X-WP-Nonce', l10n.restNonce);
          }
        },
      })
        .done(function (response) {
          if (!response || !response.success) {
            aiduniteToast('送信に失敗しました: ' + (response && response.message ? response.message : 'エラー'), 'error');
            $btn.prop('disabled', false);
            return;
          }
          var redirect = $form.data('redirect-success');
          var opponentTeamId = $form.data('opponent-team-id');
          var addFav = $form.find('input[name="add_to_favorites"]:checked').length > 0;
          var favUrl = l10n.favoriteRestUrl || '';

          function goRedirect() {
            if (redirect) {
              window.location.href = redirect;
            }
          }

          if (addFav && opponentTeamId && favUrl && l10n.restNonce) {
            $.ajax({
              url: favUrl,
              method: 'POST',
              contentType: 'application/json',
              data: JSON.stringify({ team_id: parseInt(opponentTeamId, 10) }),
              beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', l10n.restNonce);
              },
            }).always(goRedirect);
          } else {
            goRedirect();
          }
        })
        .fail(function () {
          aiduniteToast('送信に失敗しました。ページを再読み込みして再度お試しください。', 'error');
          $btn.prop('disabled', false);
        });
    });
  }

  function initAll(context) {
    initStarRatings(context);
    initCharCount(context);
    initFormSubmit();
  }

  $(function () {
    initAll(document);
  });

  window.aiduniteInitMatchFeedbackStarRatings = initAll;
})(jQuery);
