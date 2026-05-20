

(function () {
  'use strict';

  var grid        = document.getElementById('asso-grid');
  var emptyMsg    = document.getElementById('asso-empty');
  var countEl     = document.getElementById('asso-count');
  var searchInput = document.getElementById('asso-search');
  var searchClear = document.getElementById('search-clear');

  if (!grid) return;

  var state = { search: '', ecole: '', type: '', campus: '', sort: 'alpha', showInactive: false };
  var showInactiveCb = document.getElementById('asso-show-inactive');

  var cards = Array.from(grid.querySelectorAll('.asso-card-link'));

  function isCardActive(card) {
    if (card.getAttribute('data-inactive') === '1') return false;
    if (card.getAttribute('data-active') === '0') return false;
    return card.getAttribute('data-active') === '1';
  }

  function setCardShown(card, shown) {
    card.hidden = !shown;
    card.classList.toggle('asso-card-link--filtered', !shown);
  }

  function apply() {
    var q = state.search;
    var visible = [];

    cards.forEach(function (card) {
      var nom    = (card.dataset.nom    || '').toLowerCase();
      var desc   = (card.dataset.desc   || '').toLowerCase();
      var type   = card.dataset.type   || '';
      var ecole  = card.dataset.ecole  || '';
      var campus = card.dataset.campus || '';

      var okSearch = !q || nom.indexOf(q) !== -1 || desc.indexOf(q) !== -1;

      var okEcole = !state.ecole
                 || ecole === state.ecole
                 || ecole === 'Toutes';

      var okType = !state.type || type === state.type;

      var okCampus = !state.campus
                   || campus === state.campus
                   || campus === 'Tous';

      var okActive = state.showInactive ? true : isCardActive(card);

      var show = okSearch && okEcole && okType && okCampus && okActive;

      if (show) {
        setCardShown(card, true);
        visible.push(card);
      } else {
        setCardShown(card, false);
      }
    });

    if (state.sort === 'type') {
      var typeOrder = ['Corpo', 'BDE', 'BDS', 'Fédération', 'Association', 'Junior'];
      visible.sort(function (a, b) {
        var ia = typeOrder.indexOf(a.dataset.type);
        var ib = typeOrder.indexOf(b.dataset.type);
        var diff = (ia < 0 ? 99 : ia) - (ib < 0 ? 99 : ib);
        if (diff !== 0) return diff;
        return (a.dataset.nom || '').localeCompare(b.dataset.nom || '', 'fr');
      });
    } else {
      visible.sort(function (a, b) {
        return (a.dataset.nom || '').localeCompare(b.dataset.nom || '', 'fr');
      });
    }

    visible.forEach(function (c) { grid.appendChild(c); });

    var n = visible.length;
    if (countEl)  countEl.textContent = n;
    if (emptyMsg) emptyMsg.hidden = (n > 0);
  }

  if (searchInput) {
    searchInput.addEventListener('input', function () {
      state.search = searchInput.value.trim().toLowerCase();
      if (searchClear) searchClear.hidden = !state.search;
      apply();
    });
  }
  if (searchClear) {
    searchClear.addEventListener('click', function () {
      searchInput.value = '';
      state.search = '';
      searchClear.hidden = true;
      searchInput.focus();
      apply();
    });
  }

  function activateChip(clickedChip, selector) {
    document.querySelectorAll(selector).forEach(function (c) {
      c.classList.remove('active');
    });
    clickedChip.classList.add('active');
  }

  document.querySelectorAll('.filter-chip[data-ecole]').forEach(function (chip) {
    chip.addEventListener('click', function () {
      activateChip(chip, '.filter-chip[data-ecole]');
      state.ecole = chip.dataset.ecole;
      apply();
    });
  });

  document.querySelectorAll('.filter-chip[data-type]').forEach(function (chip) {
    chip.addEventListener('click', function () {
      activateChip(chip, '.filter-chip[data-type]');
      state.type = chip.dataset.type;
      apply();
    });
  });

  if (showInactiveCb) {
    showInactiveCb.addEventListener('change', function () {
      state.showInactive = showInactiveCb.checked;
      apply();
    });
  }

  var campusSel = document.getElementById('filter-campus');
  if (campusSel) {
    campusSel.addEventListener('change', function () {
      state.campus = campusSel.value;
      apply();
    });
  }

  var sortSel = document.getElementById('filter-sort');
  if (sortSel) {
    sortSel.addEventListener('change', function () {
      state.sort = sortSel.value;
      apply();
    });
  }

  var resetBtn = document.getElementById('filter-reset');
  if (resetBtn) {
    resetBtn.addEventListener('click', function () {

      state.search = '';
      state.ecole  = '';
      state.type   = '';
      state.campus = '';
      state.sort   = 'alpha';
      state.showInactive = false;

      if (searchInput) searchInput.value = '';
      if (searchClear) searchClear.hidden = true;
      if (campusSel)   campusSel.value   = '';
      if (sortSel)     sortSel.value     = 'alpha';
      if (showInactiveCb) showInactiveCb.checked = false;

      var ecoleChips = document.querySelectorAll('.filter-chip[data-ecole]');
      var typeChips  = document.querySelectorAll('.filter-chip[data-type]');
      ecoleChips.forEach(function (c, i) { c.classList.toggle('active', i === 0); });
      typeChips.forEach(function  (c, i) { c.classList.toggle('active', i === 0); });

      apply();
    });
  }

  apply();

})();
