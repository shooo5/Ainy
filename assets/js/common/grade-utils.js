/**
 * 生年月日から学年を算出（PHP aidunite_calculate_grade_from_birthdate と同一ロジック）
 */
(function (global) {
  'use strict';

  /**
   * @param {string} birthDateStr YYYY-MM-DD
   * @returns {string}
   */
  function aiduniteCalculateGradeFromBirthdate(birthDateStr) {
    if (!birthDateStr) {
      return '';
    }

    var parts = birthDateStr.split('-');
    if (parts.length !== 3) {
      return '';
    }

    var birthYear = parseInt(parts[0], 10);
    var birthMonth = parseInt(parts[1], 10) - 1;
    var birthDay = parseInt(parts[2], 10);
    var birth = new Date(birthYear, birthMonth, birthDay);
    if (isNaN(birth.getTime())) {
      return '';
    }

    var today = new Date();
    var schoolYear = today.getFullYear();
    if (today.getMonth() + 1 < 4) {
      schoolYear--;
    }

    var april1 = new Date(schoolYear, 3, 1);
    var ageAtApril = schoolYear - birth.getFullYear();
    if (
      april1.getMonth() < birth.getMonth()
      || (april1.getMonth() === birth.getMonth() && april1.getDate() < birth.getDate())
    ) {
      ageAtApril--;
    }

    if (ageAtApril < 6) {
      return '未就学';
    }
    if (ageAtApril >= 6 && ageAtApril <= 11) {
      return '小学' + (ageAtApril - 5) + '年生';
    }
    if (ageAtApril >= 12 && ageAtApril <= 14) {
      return '中学' + (ageAtApril - 11) + '年生';
    }
    if (ageAtApril >= 15 && ageAtApril <= 17) {
      return '高校' + (ageAtApril - 14) + '年生';
    }

    return '卒業';
  }

  /**
   * @param {string} birthInputId
   * @param {string|Array<string>} gradeOutputIds
   */
  function aiduniteBindPlayerGradeAutoCalc(birthInputId, gradeOutputIds) {
    var birthInput = document.getElementById(birthInputId);
    if (!birthInput) {
      return;
    }

    var outputIds = Array.isArray(gradeOutputIds) ? gradeOutputIds : [gradeOutputIds];
    var outputs = outputIds
      .map(function (id) { return document.getElementById(id); })
      .filter(Boolean);

    if (!outputs.length) {
      return;
    }

    function updateGrade() {
      var grade = aiduniteCalculateGradeFromBirthdate(birthInput.value);
      outputs.forEach(function (el) {
        el.value = grade;
      });
    }

    birthInput.addEventListener('change', updateGrade);
    birthInput.addEventListener('input', updateGrade);
    if (birthInput.value) {
      updateGrade();
    }
  }

  global.aiduniteCalculateGradeFromBirthdate = aiduniteCalculateGradeFromBirthdate;
  global.aiduniteBindPlayerGradeAutoCalc = aiduniteBindPlayerGradeAutoCalc;
})(typeof window !== 'undefined' ? window : this);
