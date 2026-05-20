/**
 * Champs .input-date-fr : saisie jj/mm/aaaa (masque léger + validation au blur).
 */
(function () {
  'use strict';

  function formatDigits(digits) {
    if (digits.length <= 2) return digits;
    if (digits.length <= 4) return digits.slice(0, 2) + '/' + digits.slice(2);
    return digits.slice(0, 2) + '/' + digits.slice(2, 4) + '/' + digits.slice(4, 8);
  }

  function isValidFrDate(str) {
    var m = /^(\d{1,2})\/(\d{1,2})\/(\d{4})$/.exec(str);
    if (!m) return false;
    var d = parseInt(m[1], 10);
    var mo = parseInt(m[2], 10);
    var y = parseInt(m[3], 10);
    if (y < 1900 || y > 2100 || mo < 1 || mo > 12 || d < 1 || d > 31) return false;
    var dt = new Date(y, mo - 1, d);
    return dt.getFullYear() === y && dt.getMonth() === mo - 1 && dt.getDate() === d;
  }

  function bind(input) {
    if (input.dataset.dateFrBound) return;
    input.dataset.dateFrBound = '1';

    input.addEventListener('input', function () {
      var digits = input.value.replace(/\D/g, '').slice(0, 8);
      var next = formatDigits(digits);
      if (input.value !== next) input.value = next;
      input.classList.remove('input-date-fr--invalid');
    });

    input.addEventListener('blur', function () {
      var v = input.value.trim();
      if (v === '') {
        input.classList.remove('input-date-fr--invalid');
        return;
      }
      input.classList.toggle('input-date-fr--invalid', !isValidFrDate(v));
    });
  }

  function init() {
    document.querySelectorAll('.input-date-fr').forEach(bind);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
