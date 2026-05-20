

(function () {
  'use strict';

  function parseI18n() {
    try {
      var el = document.getElementById('evt-i18n');
      if (el && el.textContent) {
        return JSON.parse(el.textContent);
      }
    } catch (e) {  }
    return {};
  }
  var L = parseI18n();
  function T(k, fallback) {
    return (L && L[k]) || fallback || k;
  }

  var cards     = Array.prototype.slice.call(document.querySelectorAll('.evt-filterable'));
  var emptyEl   = document.getElementById('evt-empty');
  var countEl   = document.getElementById('evt-list-count');
  var activeBox = document.getElementById('evt-active-filters');
  var searchIn  = document.getElementById('evt-search');
  var searchClr = document.getElementById('evt-search-clear');
  var sortSel   = document.getElementById('evt-sort');
  var resetBtn  = document.getElementById('evt-reset');

  if (!cards.length) return;

  var state = {
    search: '',
    type:   '',
    ecole:  '',
    campus: '',
    statut: '',
    preset: 'all',
    sort:   'date-asc'
  };

  var today = new Date(); today.setHours(0, 0, 0, 0);

  function startOfWeek() {
    var d = new Date(today);
    var dow = d.getDay() || 7;
    d.setDate(d.getDate() - (dow - 1));
    return d;
  }
  function endOfWeek()      { var d = startOfWeek(); d.setDate(d.getDate() + 6); return d; }
  function startOfWeekend() { var d = startOfWeek(); d.setDate(d.getDate() + 5); return d; }
  function endOfMonth()     { return new Date(today.getFullYear(), today.getMonth() + 1, 0); }

  function inRange(dStr, from, to) {
    var d = new Date(dStr);
    return (!from || d >= from) && (!to || d <= to);
  }
  function presetRange(p) {
    if (p === 'today')   return [today, today];
    if (p === 'week')    return [today, endOfWeek()];
    if (p === 'weekend') return [startOfWeekend(), endOfWeek()];
    if (p === 'month')   return [today, endOfMonth()];
    return [null, null];
  }

  function apply() {
    var visibleCount = 0;
    var range = presetRange(state.preset);
    var from = range[0], to = range[1];
    var visibleCards = [];

    cards.forEach(function (c) {

      if (c.classList.contains('evt-card--past')) {
        var okPastSearch = !state.search ||
          (c.dataset.evtSearch || '').indexOf(state.search) !== -1;
        var okPastType   = !state.type || (c.dataset.evtType === state.type);
        c.classList.toggle('is-hidden', !(okPastSearch && okPastType));
    return;
  }

      var txt    = (c.dataset.evtSearch || '');
      var type   = c.dataset.evtType   || '';
      var ecole  = c.dataset.evtEcole  || '';
      var campus = c.dataset.evtCampus || '';
      var statut = c.dataset.evtStatut || '';
      var date   = c.dataset.evtDate   || '';

      var ecoleArr  = ecole.split(',');
      var campusArr = campus.split(',');

      var okSearch = !state.search || txt.indexOf(state.search) !== -1;
      var okType   = !state.type   || type === state.type;
      var okEcole  = !state.ecole  ||
                     ecoleArr.indexOf(state.ecole) !== -1 ||
                     ecoleArr.indexOf('Tous')     !== -1 ||
                     ecoleArr.indexOf('Toutes')   !== -1;
      var okCampus = !state.campus ||
                     campusArr.indexOf(state.campus) !== -1 ||
                     campusArr.indexOf('Tous')       !== -1;
      var okStatut = !state.statut || statut === state.statut;
      var okDate   = !from || inRange(date, from, to);

      var ok = okSearch && okType && okEcole && okCampus && okStatut && okDate;
      c.classList.toggle('is-hidden', !ok);
      if (ok) {
        visibleCount++;
        visibleCards.push(c);
      }
    });

    sortCards(visibleCards);

    document.querySelectorAll('.evt-month-group').forEach(function (g) {
      var hasVisible = g.querySelectorAll('.evt-filterable:not(.is-hidden)').length > 0;
      g.style.display = hasVisible ? '' : 'none';
    });

    if (countEl) countEl.textContent = visibleCount;
    if (emptyEl) emptyEl.hidden = visibleCount > 0;

    renderActive();
  }

  function sortCards(visibleCards) {
    if (!visibleCards.length) return;
    var comparator;
    if (state.sort === 'date-desc') {
      comparator = function (a, b) { return (b.dataset.evtDate || '').localeCompare(a.dataset.evtDate || ''); };
    } else if (state.sort === 'popular') {
      comparator = function (a, b) { return (parseInt(b.dataset.evtPopularity, 10) || 0) - (parseInt(a.dataset.evtPopularity, 10) || 0); };
    } else {
      comparator = function (a, b) { return (a.dataset.evtDate || '').localeCompare(b.dataset.evtDate || ''); };
    }
    visibleCards.slice().sort(comparator).forEach(function (c) {
      if (c.parentElement) c.parentElement.appendChild(c);
    });
  }

  function renderActive() {
    var items = [];
    if (state.search) items.push({ k: 'search', label: '« ' + state.search + ' »' });
    if (state.type)   items.push({ k: 'type',   label: T('type', 'Type') + ' : ' + state.type });
    if (state.ecole)  items.push({ k: 'ecole',  label: T('school', 'École') + ' : ' + state.ecole });
    if (state.campus) items.push({ k: 'campus', label: T('campus', 'Campus') + ' : ' + state.campus });
    if (state.statut) items.push({ k: 'statut', label: T('status', 'Statut') + ' : ' + labelOfStatut(state.statut) });
    if (state.preset && state.preset !== 'all') items.push({ k: 'preset', label: labelOfPreset(state.preset) });

    if (!items.length) {
      activeBox.hidden = true; activeBox.innerHTML = '';
      return;
    }
    activeBox.hidden = false;
    activeBox.innerHTML = items.map(function (it) {
      return '<span class="evt-active-pill">' + escapeHtml(it.label) +
             '<button type="button" data-clear="' + it.k + '" aria-label="' + escapeHtml(T('remove', 'Retirer')) + '">✕</button></span>';
    }).join('') +
    '<button type="button" id="evt-clear-all" class="evt-reset-btn evt-reset-btn--sm">' + escapeHtml(T('clear_all', 'Tout effacer')) + '</button>';

    activeBox.querySelectorAll('button[data-clear]').forEach(function (b) {
      b.addEventListener('click', function () { clearKey(b.dataset.clear); });
    });
    var clearAll = document.getElementById('evt-clear-all');
    if (clearAll) clearAll.addEventListener('click', resetAll);
  }

  function labelOfPreset(p) {
    return ({
      today: T('preset_today', 'Aujourd\'hui'),
      week: T('preset_week', 'Cette semaine'),
      weekend: T('preset_weekend', 'Ce week-end'),
      month: T('preset_month', 'Ce mois-ci')
    })[p] || p;
  }
  function labelOfStatut(s) {
    return ({
      'inscription-ouverte': T('status_open', 'Inscription ouverte'),
      'complet': T('status_full', 'Complet')
    })[s] || s;
  }
  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
  }

  function clearKey(k) {
    if (k === 'search') { state.search = ''; if (searchIn) searchIn.value = ''; if (searchClr) searchClr.hidden = true; }
    if (k === 'type')   { state.type   = ''; resetChipGroup('type'); }
    if (k === 'ecole')  { state.ecole  = ''; resetChipGroup('ecole'); }
    if (k === 'campus') { state.campus = ''; resetChipGroup('campus'); }
    if (k === 'statut') { state.statut = ''; resetChipGroup('statut'); }
    if (k === 'preset') { state.preset = 'all'; resetPresets(); }
    apply();
  }
  function resetChipGroup(group) {
    document.querySelectorAll('.evt-chip[data-filter="' + group + '"]').forEach(function (c) {
      c.classList.toggle('active', c.dataset.value === '');
    });
  }
  function resetPresets() {
    document.querySelectorAll('.evt-preset').forEach(function (b) {
      b.classList.toggle('active', b.dataset.preset === 'all');
    });
  }
  function resetAll() {
    state.search = state.type = state.ecole = state.campus = state.statut = '';
    state.preset = 'all'; state.sort = 'date-asc';
    if (searchIn)  searchIn.value = '';
    if (searchClr) searchClr.hidden = true;
    if (sortSel)   sortSel.value = 'date-asc';
    resetChipGroup('type');
    resetChipGroup('ecole');
    resetChipGroup('campus');
    resetChipGroup('statut');
    resetPresets();
    apply();
  }

  if (searchIn) {
    searchIn.addEventListener('input', function () {
      state.search = searchIn.value.trim().toLowerCase();
      if (searchClr) searchClr.hidden = !state.search;
      apply();
    });
  }
  if (searchClr) searchClr.addEventListener('click', function () { clearKey('search'); });

  document.querySelectorAll('.evt-chip').forEach(function (chip) {
    chip.addEventListener('click', function () {
      var f = chip.dataset.filter;
      var v = chip.dataset.value;
      document.querySelectorAll('.evt-chip[data-filter="' + f + '"]').forEach(function (c) { c.classList.remove('active'); });
      chip.classList.add('active');
      state[f] = v;
      apply();
    });
  });

  document.querySelectorAll('.evt-preset').forEach(function (b) {
    b.addEventListener('click', function () {
      document.querySelectorAll('.evt-preset').forEach(function (c) { c.classList.remove('active'); });
      b.classList.add('active');
      state.preset = b.dataset.preset;
      apply();
    });
  });

  if (sortSel)  sortSel.addEventListener('change',  function () { state.sort = sortSel.value; apply(); });
  if (resetBtn) resetBtn.addEventListener('click', resetAll);

  // Bouton "Réinitialiser les filtres" dans l'empty state
  document.querySelectorAll('[data-evt-reset]').forEach(function (b) {
    b.addEventListener('click', resetAll);
  });

  var viewBtns = document.querySelectorAll('.evt-view-btn');
  viewBtns.forEach(function (b) {
    b.addEventListener('click', function () {
      var v = b.dataset.view;
      viewBtns.forEach(function (x) { x.classList.remove('active'); });
      b.classList.add('active');
      document.querySelectorAll('.evt-view').forEach(function (view) {
        view.classList.toggle('evt-view--active', view.id === 'evt-view-' + v);
      });
    });
  });

  // Hash → activer la vue calendrier directement
  if (location.hash === '#evt-view-calendar') {
    var calBtn = document.querySelector('.evt-view-btn[data-view="calendar"]');
    if (calBtn) calBtn.click();
  }

  apply();
})();
