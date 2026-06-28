/**
 * functions/post-types/player.php — 生年月日から年齢自動計算
 */
(function () {
  'use strict';
  var birth = document.getElementById('birth_date');
  var ageField = document.getElementById('age');
  if (!birth || !ageField) {
    return;
  }
  birth.addEventListener('change', function () {
    var birthDate = new Date(this.value);
    if (Number.isNaN(birthDate.getTime())) {
      return;
    }
    var today = new Date();
    var age = today.getFullYear() - birthDate.getFullYear();
    var monthDiff = today.getMonth() - birthDate.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
      age -= 1;
    }
    ageField.value = age;
  });
})();
