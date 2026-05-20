// Overlay de recherche globale (déclenché depuis le header)
(function () {
  const overlay = document.getElementById('global-search');
  const input = document.getElementById('global-search-input');
  const results = document.getElementById('global-search-results');
  const openBtns = document.querySelectorAll('[data-global-search-open]');
  const closeBtns = document.querySelectorAll('[data-global-search-close]');
  if (!overlay || !input || !results) return;

  let timer = null;
  let lastQ = '';

  function openSearch() {
    overlay.classList.add('is-open');
    overlay.setAttribute('aria-hidden', 'false');
    document.body.classList.add('global-search-open');
    setTimeout(() => input.focus(), 80);
  }

  function closeSearch() {
    overlay.classList.remove('is-open');
    overlay.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('global-search-open');
  }

  function esc(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  function renderEmpty(msg) {
    results.innerHTML = '<p class="global-search__empty">' + esc(msg) + '</p>';
  }

  function renderLoading() {
    results.innerHTML = '<p class="global-search__empty">' + esc(overlay.dataset.msgLoading || '…') + '</p>';
  }

  function renderResults(items) {
    if (!items.length) {
      renderEmpty(overlay.dataset.msgEmpty || '');
      return;
    }
    const html = items.map((r) => {
      const url = r.url.indexOf('http') === 0 ? r.url : r.url;
      return '<a class="global-search__hit" href="' + esc(url) + '">' +
        '<span class="global-search__hit-type">' + esc(r.type_label || r.type) + '</span>' +
        '<span class="global-search__hit-title">' + esc(r.title) + '</span>' +
        (r.meta ? '<span class="global-search__hit-meta">' + esc(r.meta) + '</span>' : '') +
        '</a>';
    }).join('');
    results.innerHTML = html;
  }

  function fetchResults(q) {
    renderLoading();
    const base = overlay.dataset.apiBase || 'api/search.php';
    fetch(base + '?q=' + encodeURIComponent(q) + '&limit=12', { headers: { Accept: 'application/json' } })
      .then((res) => res.json())
      .then((data) => {
        if (data.q !== q) return;
        renderResults(data.results || []);
      })
      .catch(() => renderEmpty(overlay.dataset.msgErr || 'Erreur'));
  }

  function onInput() {
    const q = input.value.trim();
    if (q === lastQ) return;
    lastQ = q;
    clearTimeout(timer);
    if (q.length < 2) {
      renderEmpty(overlay.dataset.msgHint || '');
      return;
    }
    timer = setTimeout(() => fetchResults(q), 220);
  }

  openBtns.forEach((btn) => btn.addEventListener('click', (e) => {
    e.preventDefault();
    openSearch();
  }));

  closeBtns.forEach((btn) => btn.addEventListener('click', closeSearch));

  overlay.addEventListener('click', (e) => {
    if (e.target === overlay || e.target.classList.contains('global-search__backdrop')) {
      closeSearch();
    }
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && overlay.classList.contains('is-open')) {
      closeSearch();
    }
    if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
      e.preventDefault();
      if (overlay.classList.contains('is-open')) closeSearch();
      else openSearch();
    }
  });

  input.addEventListener('input', onInput);
  renderEmpty(overlay.dataset.msgHint || '');
})();
