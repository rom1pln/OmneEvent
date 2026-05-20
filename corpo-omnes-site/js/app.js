// Utilitaires partagés (nav, formatage dates, animations)

// Nav glass on scroll
(function () {
  const nav = document.querySelector('.nav');
  if (!nav) return;
  const toggle = () => nav.classList.toggle('scrolled', window.scrollY > 40);
  toggle();
  window.addEventListener('scroll', toggle, { passive: true });
})();

// Hamburger menu - géré par jQuery dans js/jquery-features.js

// Marque le lien actif dans la nav
(function () {
  const links = document.querySelectorAll('.nav__link');
  // La classe active est déjà gérée côté PHP (header.php),
  // ce bloc sert de fallback pour les anciennes pages .html
  const current = location.pathname.split('/').pop() || 'index.php';
  links.forEach(link => {
    const href = link.getAttribute('href') || '';
    if (href === current || (current === '' && (href === 'index.php' || href === 'index.html'))) {
      link.classList.add('active');
    }
  });
})();

// Utilitaires
function formatDate(dateStr) {
  const d = new Date(dateStr);
  return d.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
}

function formatDateShort(dateStr) {
  const d = new Date(dateStr);
  return d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
}

// Animation d'entrée au scroll (Intersection Observer)
(function () {
  const els = document.querySelectorAll('[data-reveal]');
  if (!els.length) return;
  const obs = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('revealed');
        obs.unobserve(entry.target);
      }
    });
  }, { threshold: 0.12 });
  els.forEach(el => obs.observe(el));
})();
