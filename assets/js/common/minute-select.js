/**
 * 時間セレクトの分を10分刻み（00, 10, 20, 30, 40, 50）に統一
 * select.minute-select に適用
 */
document.addEventListener('DOMContentLoaded', function () {
  const replaceMinuteOptions = (select) => {
    select.innerHTML = '';
    ['00', '10', '20', '30', '40', '50'].forEach(min => {
      const option = document.createElement('option');
      option.value = min;
      option.textContent = min;
      select.appendChild(option);
    });
  };
  document.querySelectorAll('select.minute-select').forEach(select => {
    replaceMinuteOptions(select);
  });
});
