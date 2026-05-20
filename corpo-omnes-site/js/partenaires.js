// Filtres partenaires + copie code promo
(function () {
  const grid  = document.getElementById('partner-grid');
  const empty = document.getElementById('pt-empty');
  const count = document.getElementById('pt-count');
  if (!grid) return;

  let activeType   = '';
  let activeCampus = '';
  let activeSearch = '';

  function applyFilters() {
    const cards = [...grid.querySelectorAll('.pt-card')];
    let n = 0;

    cards.forEach(card => {
      const type   = card.dataset.type   || '';
      const campus = card.dataset.campus || '';
      const nom    = card.dataset.nom    || '';

      const okType   = !activeType   || type === activeType;
      const okCampus = !activeCampus || campus === activeCampus || campus === 'Tous';
      const okSearch = !activeSearch || nom.includes(activeSearch);

      const show = okType && okCampus && okSearch;
      card.hidden = !show;
      if (show) n++;
    });

    if (count) {
      const tpl = count.dataset.tpl || '%d résultats';
      count.textContent = tpl.replace('%d', String(n));
    }
    if (empty) empty.hidden = n > 0;
  }

  document.querySelectorAll('.pt-chip[data-type]').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.pt-chip[data-type]').forEach(b => b.classList.remove('pt-chip--active'));
      btn.classList.add('pt-chip--active');
      activeType = btn.dataset.type;
      applyFilters();
    });
  });

  const sel = document.getElementById('pt-campus');
  if (sel) sel.addEventListener('change', e => { activeCampus = e.target.value; applyFilters(); });

  const srch = document.getElementById('pt-search');
  if (srch) srch.addEventListener('input', e => { activeSearch = e.target.value.toLowerCase().trim(); applyFilters(); });
})();

// copie le code promo dans le presse-papiers et affiche un toast
window.copyCode = function(el, code) {
  navigator.clipboard.writeText(code).then(() => {
    const toast = document.getElementById('copy-toast');
    if (!toast) return;
    toast.style.transform = 'translateY(0)';
    toast.style.opacity   = '1';
    setTimeout(() => {
      toast.style.transform = 'translateY(100px)';
      toast.style.opacity   = '0';
    }, 2200);
  });
};
