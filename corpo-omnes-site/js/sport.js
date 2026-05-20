// Page sport

// format de date court genre "15 mars 2026"
// jsp si c'est le bon format mais ca marche
const moisCourt = ['jan.','fév.','mars','avr.','mai','juin','juil.','août','sept.','oct.','nov.','déc.'];

function formatDate(iso) {
  const d = new Date(iso);
  return `${d.getDate()} ${moisCourt[d.getMonth()]} ${d.getFullYear()}`;
}

// --- page hub (liste des sports) ---

function initHub() {
  const hubGrid     = document.getElementById('sport-hub-grid');
  const resultsGrid = document.getElementById('results-grid');
  if (!hubGrid) return;

  // on filtre uniquement les clubs (pas les sports indiv qui arrivent plus tard)
  const sports = getSports().filter(s => s.categorie === 'club');

  sports.forEach(s => {
    const dispo = s.places - s.inscrits;
    const pct   = Math.round((s.inscrits / s.places) * 100);

    const card = document.createElement('article');
    card.className = 'sport-hub-card';
    card.style.setProperty('--sport-color', s.couleur);

    // structure horizontale : bloc couleur gauche + contenu droite
    card.innerHTML = `
      <div class="sport-hub-card__left" aria-hidden="true">${s.icon}</div>
      <div class="sport-hub-card__right">
        <div class="sport-hub-card__header">
          <h3 class="sport-hub-card__name">${s.nom}</h3>
          <span class="sport-hub-card__badge">${s.campus}</span>
        </div>
        <p class="sport-hub-card__desc">${s.description}</p>
        <ul class="sport-hub-card__schedule">
          ${s.entrainements.map(e =>
            `<li><strong>${e.jour}</strong> · ${e.heure}</li>`
          ).join('')}
        </ul>
        <div class="sport-hub-card__footer">
          <div class="sport-hub-card__places">
            <div class="sport-hub-card__bar-track">
              <div class="sport-hub-card__bar-fill" style="width:${pct}%"></div>
            </div>
            <span>${dispo > 0 ? `${dispo} place${dispo > 1 ? 's' : ''} dispo.` : 'Complet'}</span>
          </div>
          <a href="sport-detail.html?s=${s.slug}" class="btn btn--primary btn--sm">Voir →</a>
        </div>
      </div>
    `;

    hubGrid.appendChild(card);
  });

  // ── Résultats (dernier match uniquement par sport) ─────
  // TODO: afficher tout l'historique si on clique sur "voir plus" ou un truc comme ca
  if (!resultsGrid) return;

  sports.forEach(s => {
    if (!s.resultats || !s.resultats.length) return;
    const last = s.resultats[0];

    const item = document.createElement('div');
    item.className = 'result-item';
    item.style.setProperty('--sport-color', s.couleur);

    const victoire = last.victoire === true   ? 'result-item--win'
                   : last.victoire === false  ? 'result-item--loss'
                   :                           'result-item--draw';
    item.classList.add(victoire);

    const label = last.victoire === true   ? 'Victoire'
                : last.victoire === false  ? 'Défaite'
                :                           'Nul';

    item.innerHTML = `
      <div class="result-item__sport">
        <span class="result-item__icon">${s.icon}</span>
        <span class="result-item__name">${s.nom}</span>
      </div>
      <div class="result-item__match">
        <span class="result-item__vs">vs ${last.adversaire}</span>
        <span class="result-item__score">${last.score}</span>
      </div>
      <div class="result-item__meta">
        <span class="result-item__label">${label}</span>
        <span class="result-item__date">${formatDate(last.date)}</span>
      </div>
    `;

    resultsGrid.appendChild(item);
  });
}

// --- page détail d'un sport (sport-detail.html) ---
// chargée dynamiquement via ?s=basket etc.

function initDetail() {
  const main = document.getElementById('sport-main');
  if (!main) return;

  const params = new URLSearchParams(window.location.search);
  const slug   = params.get('s');
  const sport  = getSports().find(s => s.slug === slug);

  if (!sport) {
    main.innerHTML = `
      <section class="section">
        <div class="container" style="text-align:center">
          <p style="color:var(--text-muted);margin-bottom:var(--s6)">Sport introuvable.</p>
          <a href="sport.html" class="btn btn--primary">Retour aux sports</a>
        </div>
      </section>`;
    return;
  }

  document.title = `${sport.nom} - Omnes Sport`;

  const dispo = sport.places - sport.inscrits;

  main.innerHTML = `
    <!-- HERO -->
    <section class="page-hero" style="--accent:${sport.couleur}">
      <div class="container">
        <nav class="breadcrumb" aria-label="Fil d'Ariane">
          <a href="index.html">Accueil</a>
          <span aria-hidden="true">›</span>
          <a href="sport.html">Sport</a>
          <span aria-hidden="true">›</span>
          <span>${sport.nom}</span>
        </nav>
        <h1><span aria-hidden="true">${sport.icon}</span> ${sport.nom}</h1>
        <p class="page-hero__sub">${sport.description}</p>
      </div>
    </section>

    <!-- CONTENU -->
    <section class="section">
      <div class="container">
        <div class="sport-detail-layout">

          <!-- Colonne principale -->
          <div class="sport-detail-main">

            <!-- Entraînements -->
            <div class="sport-detail-block">
              <h2 class="sport-detail-block__title">📅 Entraînements</h2>
              <ul class="schedule-list">
                ${sport.entrainements.map(e => `
                  <li class="schedule-item">
                    <span class="schedule-item__jour">${e.jour}</span>
                    <div class="schedule-item__info">
                      <span class="schedule-item__heure">${e.heure}</span>
                      <span class="schedule-item__lieu">📍 ${e.lieu}</span>
                    </div>
                  </li>`).join('')}
              </ul>
            </div>

            <!-- Événements -->
            <div class="sport-detail-block">
              <h2 class="sport-detail-block__title">🏆 Événements à venir</h2>
              ${sport.evenements.length ? `
                <ul class="schedule-list">
                  ${sport.evenements.map(e => `
                    <li class="schedule-item">
                      <span class="schedule-item__jour">${formatDate(e.date)}</span>
                      <div class="schedule-item__info">
                        <span class="schedule-item__heure">${e.titre}</span>
                        <span class="schedule-item__lieu">📍 ${e.lieu}</span>
                      </div>
                    </li>`).join('')}
                </ul>` :
                `<p style="color:var(--text-muted);font-size:.9rem">Aucun événement prévu pour le moment.</p>`
              }
            </div>

            <!-- Résultats -->
            <div class="sport-detail-block">
              <h2 class="sport-detail-block__title">📊 Résultats récents</h2>
              <div class="results-list">
                ${sport.resultats.map(r => {
                  const cls   = r.victoire === true  ? 'result-row--win'
                              : r.victoire === false ? 'result-row--loss'
                              :                       'result-row--draw';
                  const label = r.victoire === true  ? 'V'
                              : r.victoire === false ? 'D'
                              :                       'N';
                  return `
                    <div class="result-row ${cls}">
                      <span class="result-row__label">${label}</span>
                      <span class="result-row__vs">vs ${r.adversaire}</span>
                      <span class="result-row__score">${r.score}</span>
                      <span class="result-row__date">${formatDate(r.date)}</span>
                    </div>`;
                }).join('')}
              </div>
            </div>

          </div>

          <!-- Colonne latérale -->
          <aside class="sport-detail-aside">

            <!-- Inscription -->
            <div class="sport-aside-card sport-aside-card--cta" style="--sport-color:${sport.couleur}">
              <h3 class="sport-aside-card__title">Rejoindre l'équipe</h3>
              <p class="sport-aside-card__sub">
                ${dispo > 0
                  ? `${dispo} place${dispo > 1 ? 's' : ''} disponible${dispo > 1 ? 's' : ''} sur ${sport.places}`
                  : 'Équipe complète - liste d\'attente disponible'}
              </p>
              <div class="sport-aside-card__bar">
                <div style="width:${Math.round((sport.inscrits/sport.places)*100)}%"></div>
              </div>
              <a href="#" class="btn btn--primary" style="width:100%;text-align:center;margin-top:var(--s4)"
                aria-disabled="true" title="Disponible à la rentrée 2026">
                S'inscrire sur la plateforme
              </a>
            </div>

            <!-- Référents -->
            <div class="sport-aside-card">
              <h3 class="sport-aside-card__title">Contact</h3>
              ${sport.referents.map(r => `
                <div class="referent-item">
                  <div class="referent-item__avatar">${r.initiales}</div>
                  <div class="referent-item__info">
                    <strong>${r.nom}</strong>
                    <span>${r.role}</span>
                  </div>
                </div>
                <a href="mailto:${r.email}" class="referent-item__email">${r.email}</a>
              `).join('')}
            </div>

            <!-- Retour -->
            <a href="sport.html" class="btn btn--ghost" style="width:100%;text-align:center">
              ← Tous les sports
            </a>

          </aside>

        </div>
      </div>
    </section>
  `;
}

// detecte sur quel page on est pour appeler la bonne fonction
// (y'a surement une meilleure facon de faire mais ca marche)

if (document.getElementById('sport-hub-grid')) {
  initHub();
} else if (document.getElementById('sport-main')) {
  initDetail();
}
