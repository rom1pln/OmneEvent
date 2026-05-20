<?php
require_once 'includes/i18n.php';
$title = corpo_t('apr.meta_title');
$page  = 'apropos';
require_once 'includes/db.php';
require_once 'includes/associations-activity.php';
require_once 'includes/header.php';

$nbAssosSql = asso_has_mandat_columns($pdo)
    ? 'SELECT COUNT(*) FROM associations WHERE ' . asso_sql_active_condition()
    : 'SELECT COUNT(*) FROM associations';
$nbAssos       = (int)$pdo->query($nbAssosSql)->fetchColumn();
$nbEvents      = (int)$pdo->query("SELECT COUNT(*) FROM evenements WHERE statut='publie'")->fetchColumn();
$nbPartenaires = (int)$pdo->query("SELECT COUNT(*) FROM partenaires WHERE statut='publie'")->fetchColumn();
?>

<main>

  <section class="page-hero apropos-hero">
    <div class="container">
      <nav class="breadcrumb" aria-label="<?= htmlspecialchars(corpo_t('apr.breadcrumb_aria')) ?>">
        <a href="index.php"><?= htmlspecialchars(corpo_t('common.breadcrumb_home')) ?></a><span aria-hidden="true">›</span><span><?= htmlspecialchars(corpo_t('apr.crumb')) ?></span>
      </nav>
      <div class="apropos-hero-grid">
        <div>
          <span class="apropos-eyebrow"><?= htmlspecialchars(corpo_t('apr.eyebrow')) ?></span>
          <h1><?= htmlspecialchars(corpo_t('apr.hero_h1')) ?></h1>
          <p class="page-hero__sub">
            <?= corpo_t('apr.hero_sub') ?>
          </p>
          <div class="apropos-hero-cta">
            <a href="#equipe" class="btn btn--primary"><?= htmlspecialchars(corpo_t('apr.btn_team')) ?></a>
            <a href="#faq" class="btn btn--ghost"><?= htmlspecialchars(corpo_t('apr.btn_faq')) ?></a>
            <a href="guide-site.php" class="btn btn--ghost"><?= htmlspecialchars(corpo_t('apr.btn_guide')) ?></a>
          </div>
        </div>

        <div class="apropos-hero-stats">
          <div class="apropos-stat"><strong>5</strong><span><?= htmlspecialchars(corpo_t('apr.stat_schools')) ?></span></div>
          <div class="apropos-stat"><strong>2</strong><span><?= htmlspecialchars(corpo_t('apr.stat_campus')) ?></span></div>
          <div class="apropos-stat"><strong>6 000</strong><span><?= htmlspecialchars(corpo_t('apr.stat_students')) ?></span></div>
          <div class="apropos-stat"><strong><?= $nbAssos ?></strong><span><?= htmlspecialchars(corpo_t('apr.stat_structures')) ?></span></div>
        </div>
      </div>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <span class="section-label"><?= htmlspecialchars(corpo_t('apr.mission_label')) ?></span>
      <h2 class="section-title"><?= htmlspecialchars(corpo_t('apr.mission_title')) ?></h2>

      <div class="apropos-mission">
        <p class="apropos-mission__lead">
          <?= corpo_t('apr.mission_lead') ?>
        </p>

        <div class="apropos-pillars">
          <article class="pillar-card">
            <div class="pillar-card__icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 010 18M12 3a14 14 0 000 18"/>
              </svg>
            </div>
            <h3><?= htmlspecialchars(corpo_t('apr.pillar1_t')) ?></h3>
            <p><?= htmlspecialchars(corpo_t('apr.pillar1_p')) ?></p>
          </article>
          <article class="pillar-card">
            <div class="pillar-card__icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 12l3-3 3 3 3-6 3 6 3-3 3 3"/><path d="M3 18h18"/>
              </svg>
            </div>
            <h3><?= htmlspecialchars(corpo_t('apr.pillar2_t')) ?></h3>
            <p><?= htmlspecialchars(corpo_t('apr.pillar2_p')) ?></p>
          </article>
          <article class="pillar-card">
            <div class="pillar-card__icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M13 2L3 14h7l-1 8 10-12h-7l1-8z"/>
              </svg>
            </div>
            <h3><?= htmlspecialchars(corpo_t('apr.pillar3_t')) ?></h3>
            <p><?= htmlspecialchars(corpo_t('apr.pillar3_p')) ?></p>
          </article>
        </div>
      </div>
    </div>
  </section>

  <section id="equipe" class="section section--alt">
    <div class="container">
      <span class="section-label"><?= htmlspecialchars(corpo_t('apr.gov_label')) ?></span>
      <h2 class="section-title section-title--center"><?= htmlspecialchars(corpo_t('apr.team_title')) ?></h2>
      <p class="section-intro">
        <?= htmlspecialchars(corpo_t('apr.team_intro')) ?>
      </p>

            <div class="team-block">
        <p class="team-section-title"><span class="team-section-title__bar"></span><?= htmlspecialchars(corpo_t('apr.bureau')) ?></p>
        <div class="team-bureau">
          <article class="t-card">
            <div class="t-card__chip">Présidence</div>
            <div class="t-card__avatar">TT</div>
            <div class="t-card__name">Théo Tochon</div>
            <div class="t-card__role">Président de la Corpo</div>
          </article>
          <article class="t-card">
            <div class="t-card__chip">Présidence</div>
            <div class="t-card__avatar">RP</div>
            <div class="t-card__name">Romain Plane</div>
            <div class="t-card__role">Vice-Président &amp; Trésorier</div>
          </article>
          <article class="t-card">
            <div class="t-card__chip">Secrétariat</div>
            <div class="t-card__avatar">CB</div>
            <div class="t-card__name">Clara Bareau</div>
            <div class="t-card__role">Secrétaire Générale &amp; Vice-Trésorière</div>
          </article>
        </div>
      </div>

            <div class="team-block">
        <p class="team-section-title"><span class="team-section-title__bar"></span>Conseil d'Administration</p>
        <div class="team-ca">
          <article class="t-card t-card--ca t-card--tbd">
            <div class="t-card__chip">À pourvoir</div>
            <div class="t-card__avatar">?</div>
            <div class="t-card__name">Poste à confirmer</div>
            <div class="t-card__role">Président Omnès Sports</div>
          </article>
          <article class="t-card t-card--ca">
            <div class="t-card__chip">Resp.</div>
            <div class="t-card__avatar">MB</div>
            <div class="t-card__name">Marie Brègere</div>
            <div class="t-card__role">Associations</div>
          </article>
          <article class="t-card t-card--ca">
            <div class="t-card__chip">Resp.</div>
            <div class="t-card__avatar">WC</div>
            <div class="t-card__name">William Clin</div>
            <div class="t-card__role">Événementiel</div>
          </article>
          <article class="t-card t-card--ca">
            <div class="t-card__chip">Resp.</div>
            <div class="t-card__avatar">AS</div>
            <div class="t-card__name">Amandine Stempfel</div>
            <div class="t-card__role">RSE</div>
          </article>
          <article class="t-card t-card--ca">
            <div class="t-card__chip">Resp.</div>
            <div class="t-card__avatar">LM</div>
            <div class="t-card__name">Laura Marolho</div>
            <div class="t-card__role">Communication</div>
          </article>
          <article class="t-card t-card--ca">
            <div class="t-card__chip">Resp.</div>
            <div class="t-card__avatar">EL</div>
            <div class="t-card__name">Elyam Lalaouui</div>
            <div class="t-card__role">Partenariat</div>
          </article>
        </div>
      </div>

            <div class="team-block">
        <p class="team-section-title">
          <span class="team-section-title__bar"></span>
          Pôles ouverts au recrutement
          <span class="team-section-title__count">5 postes</span>
        </p>
        <div class="team-poles">
          <article class="pole-c pole-c--event">
            <div class="pole-c__head">
              <span class="pole-c__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
                </svg>
              </span>
              <span class="pole-c__badge">2 postes</span>
            </div>
            <h3>Événementiel</h3>
            <p>Organisation des grands événements inter-écoles aux côtés de William Clin.</p>
            <a href="mailto:corpoomnes@gmail.com?subject=Candidature%20p%C3%B4le%20%C3%89v%C3%A9nementiel" class="pole-c__cta">Candidater →</a>
          </article>
          <article class="pole-c pole-c--com">
            <div class="pole-c__head">
              <span class="pole-c__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
              </span>
              <span class="pole-c__badge">2 postes</span>
            </div>
            <h3>Communication</h3>
            <p>Réseaux sociaux, visuels et identité de la Corpo avec Laura Marolho.</p>
            <a href="mailto:corpoomnes@gmail.com?subject=Candidature%20p%C3%B4le%20Communication" class="pole-c__cta">Candidater →</a>
          </article>
          <article class="pole-c pole-c--part">
            <div class="pole-c__head">
              <span class="pole-c__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M16 4h2a2 2 0 0 1 2 2v14l-4-2-4 2-4-2-4 2V6a2 2 0 0 1 2-2h2"/>
                </svg>
              </span>
              <span class="pole-c__badge">1 poste</span>
            </div>
            <h3>Partenariat</h3>
            <p>Développement et suivi des partenaires locaux avec Elyam Lalaouui.</p>
            <a href="mailto:corpoomnes@gmail.com?subject=Candidature%20p%C3%B4le%20Partenariat" class="pole-c__cta">Candidater →</a>
          </article>
        </div>
        <p class="poles-note">
          Une question ? Écrivez-nous à
          <a href="mailto:corpoomnes@gmail.com" class="link-accent">corpoomnes@gmail.com</a>
        </p>
      </div>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <span class="section-label">Missions</span>
      <h2 class="section-title">Les rôles du bureau</h2>
      <p class="section-intro">Six fonctions complémentaires pour faire tourner la Corpo au quotidien.</p>

      <div class="role-grid">
        <article class="r-card r-card--01">
          <div class="r-card__head">
            <span class="r-card__num">01</span>
            <span class="r-card__icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M2 21h20M3 21V8l9-5 9 5v13M9 21v-7h6v7M9 12h.01M15 12h.01"/>
              </svg>
            </span>
          </div>
          <h3>Président</h3>
          <ul>
            <li>Représente la Corpo légalement</li>
            <li>Coordonne tous les pôles et le CA</li>
            <li>Prend les décisions stratégiques</li>
            <li>Interface avec le groupe Omnes</li>
          </ul>
        </article>

        <article class="r-card r-card--02">
          <div class="r-card__head">
            <span class="r-card__num">02</span>
            <span class="r-card__icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
              </svg>
            </span>
          </div>
          <h3>Vice-Président</h3>
          <ul>
            <li>Seconde le Président au quotidien</li>
            <li>Assure l'intérim en son absence</li>
            <li>Coordonne les pôles opérationnels</li>
            <li>Participe aux décisions stratégiques</li>
          </ul>
        </article>

        <article class="r-card r-card--03">
          <div class="r-card__head">
            <span class="r-card__num">03</span>
            <span class="r-card__icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
              </svg>
            </span>
          </div>
          <h3>Trésorier</h3>
          <ul>
            <li>Tient la comptabilité de l'asso</li>
            <li>Gère les subventions et le budget</li>
            <li>Valide les dépenses des pôles</li>
            <li>Présente les comptes en AG</li>
          </ul>
        </article>

        <article class="r-card r-card--04">
          <div class="r-card__head">
            <span class="r-card__num">04</span>
            <span class="r-card__icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="15" y2="17"/>
              </svg>
            </span>
          </div>
          <h3>Secrétaire Général</h3>
          <ul>
            <li>Rédige les PV et comptes-rendus</li>
            <li>Organise les CA et AG</li>
            <li>Archive les documents officiels</li>
            <li>Gère les adhésions</li>
          </ul>
        </article>

        <article class="r-card r-card--05">
          <div class="r-card__head">
            <span class="r-card__num">05</span>
            <span class="r-card__icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 10h18M9 21V10"/>
              </svg>
            </span>
          </div>
          <h3>Vice-Trésorier <small>(CA)</small></h3>
          <ul>
            <li>Assiste le trésorier dans ses missions</li>
            <li>Assure l'intérim en son absence</li>
            <li>Suit les dépenses des associations</li>
            <li>Vérifie la bonne tenue des comptes</li>
          </ul>
        </article>

        <article class="r-card r-card--06">
          <div class="r-card__head">
            <span class="r-card__num">06</span>
            <span class="r-card__icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6M18 9h1.5a2.5 2.5 0 0 0 0-5H18M4 22h16M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22M18 2H6v7a6 6 0 0 0 12 0V2z"/>
              </svg>
            </span>
          </div>
          <h3>Président Omnès Sports</h3>
          <ul>
            <li>Pilote le bureau des sports inter-écoles</li>
            <li>Coordonne les BDS des 5 écoles</li>
            <li>Planifie les tournois et challenges</li>
            <li>Gère les inscriptions sportives</li>
          </ul>
        </article>
      </div>
    </div>
  </section>

  <section class="section section--alt">
    <div class="container">
      <span class="section-label">Réseau</span>
      <h2 class="section-title section-title--center">BDE &amp; Fédérations</h2>
      <p class="section-intro">
        Chaque école possède son propre Bureau des Étudiants. La Corpo coordonne l'ensemble
        de ce réseau pour créer une dynamique commune entre les 6 000 étudiants Omnes Lyon.
      </p>

      <div class="bde-net">
        <article class="bde-school-card" style="--sc:#007179">
          <div class="bde-school-card__head">
            <span class="bde-school-card__tag">ECE</span>
            <span class="bde-school-card__campus">Citadelle</span>
          </div>
          <ul>
            <li><strong>BDE Ginfinity</strong><span>Bureau des Étudiants</span></li>
          </ul>
        </article>

        <article class="bde-school-card" style="--sc:#002D74">
          <div class="bde-school-card__head">
            <span class="bde-school-card__tag">ESCE</span>
            <span class="bde-school-card__campus">Citroën</span>
          </div>
          <ul>
            <li><strong>BDE Newolf</strong><span>Bureau des Étudiants</span></li>
          </ul>
        </article>

        <article class="bde-school-card" style="--sc:#E52521">
          <div class="bde-school-card__head">
            <span class="bde-school-card__tag">HEIP</span>
            <span class="bde-school-card__campus">Citadelle</span>
          </div>
          <ul>
            <li><strong>BDE Hyperion</strong><span>Bureau des Étudiants</span></li>
            <li><strong>EchoFed</strong><span>Fédération associative</span></li>
          </ul>
        </article>

        <article class="bde-school-card" style="--sc:#003DA5">
          <div class="bde-school-card__head">
            <span class="bde-school-card__tag">INSEEC</span>
            <span class="bde-school-card__campus">Citroën</span>
          </div>
          <ul>
            <li><strong>BDE In'Solute</strong><span>Grande École</span></li>
            <li><strong>BDE In'Stables</strong><span>BBA</span></li>
            <li><strong>BDE The Hangover</strong><span>Bachelor</span></li>
            <li><strong>BDE Paradise</strong><span>MSc</span></li>
          </ul>
        </article>

        <article class="bde-school-card" style="--sc:#FF5B05">
          <div class="bde-school-card__head">
            <span class="bde-school-card__tag">Sup de Pub</span>
            <span class="bde-school-card__campus">Citadelle</span>
          </div>
          <ul>
            <li><strong>BDE Shot</strong><span>Bureau des Étudiants</span></li>
          </ul>
        </article>

        <article class="bde-school-card bde-school-card--inter" style="--sc:#5D0282">
          <div class="bde-school-card__head">
            <span class="bde-school-card__tag">Inter-écoles</span>
            <span class="bde-school-card__campus">Tous campus</span>
          </div>
          <ul>
            <li><strong>OMNES Sport</strong><span>Bureau des Sports inter-écoles</span></li>
          </ul>
        </article>
      </div>
    </div>
  </section>

  <section id="faq" class="section">
    <div class="container">
      <span class="section-label">FAQ</span>
      <h2 class="section-title section-title--center">Questions fréquentes</h2>
      <p class="section-intro">Tout ce que vous vouliez savoir sur la Corpo sans jamais oser le demander.</p>

      <div class="faq-list">
        <div class="faq-item">
          <button class="faq-item__trigger" aria-expanded="false">
            Comment rejoindre le bureau de la Corpo ?
            <span class="faq-item__icon">+</span>
          </button>
          <div class="faq-item__body">
            <p>Les postes du bureau sont pourvus par élection chaque année en novembre-décembre. Suivez nos réseaux sociaux pour être informé des périodes de candidature. Des postes non-élus (chargés de mission, ambassadeurs) sont également disponibles tout au long de l'année.</p>
          </div>
        </div>
        <div class="faq-item">
          <button class="faq-item__trigger" aria-expanded="false">
            La Corpo est-elle ouverte à toutes les écoles ?
            <span class="faq-item__icon">+</span>
          </button>
          <div class="faq-item__body">
            <p>Oui, absolument. La Corpo Omnes Lyon représente les étudiants des 5 écoles : ECE, ESCE, HEIP, INSEEC et Sup de Pub, sur les deux campus lyonnais. Tous les étudiants du groupe Omnes sont automatiquement représentés par la Corpo.</p>
          </div>
        </div>
        <div class="faq-item">
          <button class="faq-item__trigger" aria-expanded="false">
            Comment bénéficier des offres partenaires ?
            <span class="faq-item__icon">+</span>
          </button>
          <div class="faq-item__body">
            <p>Rendez-vous sur la page <a href="partenaires.php">Partenaires</a> pour consulter la liste complète des offres. Présentez votre carte étudiante et le code promo associé directement en caisse.</p>
          </div>
        </div>
        <div class="faq-item">
          <button class="faq-item__trigger" aria-expanded="false">
            Comment proposer un événement ou un partenariat ?
            <span class="faq-item__icon">+</span>
          </button>
          <div class="faq-item__body">
            <p>Contactez-nous à <a href="mailto:corpoomnes@gmail.com">corpoomnes@gmail.com</a>. Pour les propositions de partenariat commercial, précisez la nature de l'offre et le public cible.</p>
          </div>
        </div>
        <div class="faq-item">
          <button class="faq-item__trigger" aria-expanded="false">
            Quelle est la différence entre la Corpo et un BDE ?
            <span class="faq-item__icon">+</span>
          </button>
          <div class="faq-item__body">
            <p>Chaque école a son propre BDE qui organise la vie associative de cette école. La Corpo est l'échelon supérieur : elle coordonne tous les BDE, organise des événements inter-écoles et négocie des partenariats pour les 6 000 étudiants Omnes Lyon.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="cta-section">
    <div class="container">
      <h2 class="cta-section__title">5 postes ouverts dans nos pôles</h2>
      <p class="cta-section__sub">Événementiel, Communication, Partenariat - rejoins l'équipe et contribue à la vie étudiante de 6 000 étudiants Omnes Lyon.</p>
      <div class="cta-section__actions">
        <a href="mailto:corpoomnes@gmail.com" class="btn btn--primary">Candidater par mail</a>
        <a href="https://instagram.com/copro_omnes" target="_blank" rel="noopener" class="btn btn--ghost">Suivre sur Instagram</a>
      </div>
    </div>
  </section>

</main>

<?php require_once 'includes/footer.php'; ?>
