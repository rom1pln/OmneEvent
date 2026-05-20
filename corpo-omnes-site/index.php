<?php
require_once 'includes/i18n.php';
$title = corpo_t('index.meta_title');
$page  = 'index';
require_once 'includes/db.php';
require_once 'includes/header.php';

// données pour la homepage
$nbBde       = $pdo->query("SELECT COUNT(*) FROM associations WHERE type='BDE'")->fetchColumn();
$nbAssos     = $pdo->query("SELECT COUNT(*) FROM associations")->fetchColumn();
$nbPartenaires = $pdo->query("SELECT COUNT(*) FROM partenaires WHERE statut='publie'")->fetchColumn();
$nbSports    = $pdo->query("SELECT COUNT(*) FROM sports")->fetchColumn();

// Prochain événement à venir
$nextEvent   = $pdo->query(
    "SELECT * FROM evenements WHERE statut='publie' AND date >= CURDATE() ORDER BY date ASC LIMIT 1"
)->fetch();

// 3 événements après (ou tous si peu)
$upcomingEvts = $pdo->query(
    "SELECT * FROM evenements WHERE statut='publie' AND date >= CURDATE() ORDER BY date ASC LIMIT 4"
)->fetchAll();

// Sports clubs
$sports = $pdo->query("SELECT * FROM sports WHERE categorie='club' ORDER BY id LIMIT 4")->fetchAll();

// Derniers résultats sportifs
$resultats = $pdo->query(
    "SELECT r.*, s.nom AS sport_nom, s.icon AS sport_icon, s.couleur
     FROM sport_resultats r JOIN sports s ON s.id = r.sport_id
     ORDER BY r.date DESC LIMIT 3"
)->fetchAll();

// Quelques partenaires mis en avant
$featuredPt = $pdo->query(
    "SELECT * FROM partenaires WHERE statut='publie' ORDER BY id LIMIT 4"
)->fetchAll();

// Dernières actualités publiées
$actus = $pdo->query(
    "SELECT * FROM actualites WHERE statut='publie' AND IFNULL(visibilite,'public')='public' ORDER BY created_at DESC LIMIT 3"
)->fetchAll();

$monthNames = corpo_month_names_full();
?>

<main>

  <!-- HERO -->
  <section class="hero" aria-labelledby="hero-title">
    <div class="hero__content container">
      <span class="hero__eyebrow"><?= htmlspecialchars(corpo_t('index.hero_eyebrow')) ?></span>
      <h1 class="hero__title" id="hero-title"><?= htmlspecialchars(corpo_t('index.hero_title')) ?></h1>
      <div class="hero__cycle" aria-hidden="true">
        <span class="hero__word"><?= htmlspecialchars(corpo_t('index.hero_w1')) ?></span>
        <span class="hero__word"><?= htmlspecialchars(corpo_t('index.hero_w2')) ?></span>
        <span class="hero__word"><?= htmlspecialchars(corpo_t('index.hero_w3')) ?></span>
        <span class="hero__word"><?= htmlspecialchars(corpo_t('index.hero_w4')) ?></span>
      </div>
      <p class="hero__desc"><?= corpo_t('index.hero_desc') ?></p>
      <div class="hero__actions">
        <a href="associations.php" class="btn btn--primary"><?= htmlspecialchars(corpo_t('index.btn_join_asso')) ?></a>
        <a href="evenements.php"   class="btn btn--ghost"><?= htmlspecialchars(corpo_t('index.btn_events')) ?></a>
      </div>
    </div>
  </section>

  <!-- STATS -->
  <div class="stats-band" role="region" aria-label="<?= htmlspecialchars(corpo_t('index.stats_aria')) ?>">
    <div class="container">
      <div class="stat-item"><span class="stat-item__value">6 000+</span><span class="stat-item__label"><?= htmlspecialchars(corpo_t('index.stat_students')) ?></span></div>
      <div class="stat-item"><span class="stat-item__value">5</span><span class="stat-item__label"><?= htmlspecialchars(corpo_t('index.stat_schools')) ?></span></div>
      <div class="stat-item"><span class="stat-item__value">2</span><span class="stat-item__label"><?= htmlspecialchars(corpo_t('index.stat_campus')) ?></span></div>
      <div class="stat-item"><span class="stat-item__value"><?= $nbBde ?></span><span class="stat-item__label"><?= htmlspecialchars(corpo_t('index.stat_bde')) ?></span></div>
      <div class="stat-item"><span class="stat-item__value"><?= $nbAssos ?></span><span class="stat-item__label"><?= htmlspecialchars(corpo_t('index.stat_structures')) ?></span></div>
      <div class="stat-item"><span class="stat-item__value"><?= $nbPartenaires ?></span><span class="stat-item__label"><?= htmlspecialchars(corpo_t('index.stat_partners')) ?></span></div>
      <div class="stat-item"><span class="stat-item__value"><?= $nbSports ?></span><span class="stat-item__label"><?= htmlspecialchars(corpo_t('index.stat_sports')) ?></span></div>
    </div>
  </div>

  <!-- prochain événement -->
  <?php if ($nextEvent): $evtDt = new DateTime($nextEvent['date']); ?>
  <section class="section home-evt-section">
    <div class="container">
      <div class="home-evt-header">
        <div>
          <span class="section-label"><?= htmlspecialchars(corpo_t('index.next_evt_label')) ?></span>
          <h2 class="section-title"><?= htmlspecialchars(corpo_t('index.next_evt_title')) ?></h2>
        </div>
        <a href="evenements.php" class="btn btn--ghost btn--sm"><?= htmlspecialchars(corpo_t('index.next_evt_all')) ?></a>
      </div>

      <div class="home-evt-feature">
        <!-- Grande carte événement -->
        <article class="home-evt-main">
          <div class="home-evt-main__date">
            <span class="home-evt-main__day"><?= $evtDt->format('d') ?></span>
            <span class="home-evt-main__month"><?= $monthNames[(int)$evtDt->format('n')] ?></span>
            <span class="home-evt-main__year"><?= $evtDt->format('Y') ?></span>
          </div>
          <div class="home-evt-main__body">
            <span class="home-evt-main__eyebrow">
              <?= htmlspecialchars($nextEvent['organisateur'] ?? 'Corpo Omnes Lyon') ?>
            </span>
            <h3 class="home-evt-main__title">
              <?= htmlspecialchars($nextEvent['titre']) ?>
            </h3>
            <?php if ($nextEvent['description']): ?>
              <p class="home-evt-main__desc"><?= htmlspecialchars(mb_substr($nextEvent['description'], 0, 160)) ?>…</p>
            <?php endif; ?>
            <div class="home-evt-main__meta">
              <?php if ($nextEvent['heure']): ?><span><?= htmlspecialchars($nextEvent['heure']) ?></span><?php endif; ?>
              <?php if ($nextEvent['lieu']):  ?><span><?= htmlspecialchars($nextEvent['lieu']) ?></span><?php endif; ?>
              <?php if ($nextEvent['campus']): ?><span><?= htmlspecialchars($nextEvent['campus']) ?></span><?php endif; ?>
              <?php if ($nextEvent['places']): ?><span><?= sprintf(corpo_t('index.evt_places'), (int)$nextEvent['places']) ?></span><?php endif; ?>
            </div>
            <?php
              require_once 'includes/billetterie.php';
              $modeInsc = evt_normalize_mode($nextEvent['mode_inscription'] ?? 'aucune');
              if ($modeInsc === 'externe' && !empty($nextEvent['lien_billetterie'])): ?>
              <a href="<?= htmlspecialchars($nextEvent['lien_billetterie']) ?>" target="_blank"
                 class="btn btn--primary btn--sm" style="margin-top:var(--s4)"><?= htmlspecialchars(corpo_t('index.btn_ticketing')) ?></a>
            <?php elseif (in_array($modeInsc, ['email','connexion','billetterie_email','billetterie_connexion'], true)): ?>
              <a href="evenement.php?id=<?= $nextEvent['id'] ?>"
                 class="btn btn--primary btn--sm" style="margin-top:var(--s4)">
                <?= htmlspecialchars(evt_mode_is_paid($modeInsc) ? corpo_t('index.btn_buy_ticket') : corpo_t('index.btn_register_evt')) ?>
              </a>
            <?php endif; ?>
          </div>
        </article>

        <!-- Liste des events suivants -->
        <?php if (count($upcomingEvts) > 1): ?>
        <div class="home-evt-list">
          <?php foreach (array_slice($upcomingEvts, 1) as $ev):
            $dt = new DateTime($ev['date']);
          ?>
            <a href="evenements.php?m=<?= $dt->format('n') ?>&y=<?= $dt->format('Y') ?>" class="home-evt-item">
              <div class="home-evt-item__date">
                <span><?= $dt->format('d') ?></span>
                <span><?= corpo_month_abbr((int)$dt->format('n')) ?></span>
              </div>
              <div class="home-evt-item__info">
                <strong><?= htmlspecialchars($ev['titre']) ?></strong>
                <span><?= htmlspecialchars($ev['lieu'] ?? '') ?></span>
              </div>
            </a>
          <?php endforeach; ?>
          <a href="evenements.php" class="home-evt-item home-evt-item--more">
            <?= htmlspecialchars(corpo_t('index.next_evt_more')) ?>
          </a>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- sports -->
  <section class="section section--alt home-sports-section">
    <div class="container">
      <div class="home-section-header">
        <div>
          <span class="section-label"><?= htmlspecialchars(corpo_t('index.sports_label')) ?></span>
          <h2 class="section-title"><?= htmlspecialchars(corpo_t('index.sports_title')) ?></h2>
        </div>
        <a href="sport.php" class="btn btn--ghost btn--sm"><?= htmlspecialchars(corpo_t('index.sports_all')) ?></a>
      </div>

      <div class="home-sports-grid">
        <?php foreach ($sports as $s):
          $pct  = $s['places'] > 0 ? round($s['inscrits']/$s['places']*100) : 0;
          $dispo = $s['places'] - $s['inscrits'];
        ?>
          <a href="structure.php?sport=<?= htmlspecialchars($s['slug']) ?>" class="home-sport-card"
             style="--sc:<?= htmlspecialchars($s['couleur']) ?>">
            <div class="home-sport-card__icon">
              <?php if (!empty($s['logo'])): ?>
                <img src="<?= htmlspecialchars($s['logo']) ?>" alt="" style="width:100%;height:100%;object-fit:contain">
              <?php else: ?>
                <?= mb_strtoupper(mb_substr($s['nom'], 0, 2)) ?>
              <?php endif; ?>
            </div>
            <div class="home-sport-card__body">
              <h3 class="home-sport-card__name"><?= htmlspecialchars($s['nom']) ?></h3>
              <span class="home-sport-card__campus"><?= htmlspecialchars($s['campus']) ?></span>
              <div class="home-sport-card__bar">
                <div class="home-sport-card__fill" style="width:<?= $pct ?>%"></div>
              </div>
              <span class="home-sport-card__places">
                <?= $dispo > 0 ? sprintf(corpo_t('sport.places_avail'), $dispo) : corpo_t('common.full') ?>
              </span>
            </div>
          </a>
        <?php endforeach; ?>
      </div>

      <!-- Derniers résultats en petite bande -->
      <?php if (!empty($resultats)): ?>
        <div class="home-results-strip">
          <span class="home-results-strip__label"><?= htmlspecialchars(corpo_t('index.results_label')) ?></span>
          <?php foreach ($resultats as $r):
            $cls = $r['victoire'] === null ? 'draw' : ($r['victoire'] ? 'win' : 'loss');
          ?>
            <div class="home-result-pill home-result-pill--<?= $cls ?>">
              <span class="home-result-pill__sport"><?= htmlspecialchars($r['sport_nom']) ?></span>
              <span><?= htmlspecialchars($r['score']) ?></span>
              <span style="opacity:.6"><?= htmlspecialchars(corpo_t('common.vs')) ?> <?= htmlspecialchars($r['adversaire']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- campus -->
  <section class="section" aria-labelledby="campus-title">
    <div class="container">
      <span class="section-label"><?= htmlspecialchars(corpo_t('index.infra_label')) ?></span>
      <h2 class="section-title" id="campus-title"><?= htmlspecialchars(corpo_t('index.infra_title')) ?></h2>
      <p class="lead"><?= htmlspecialchars(corpo_t('index.infra_lead')) ?></p>
      <div class="grid grid--2">
        <article class="campus-card" data-reveal>
          <img src="images/campus-citroen.webp" alt="<?= htmlspecialchars(corpo_t('index.campus_alt_citroen')) ?>" class="campus-card__img">
          <div class="campus-card__body">
            <span class="campus-card__year"><?= htmlspecialchars(corpo_t('index.campus_year_citroen')) ?></span>
            <h3 class="campus-card__title"><?= htmlspecialchars(corpo_t('index.campus_title_citroen')) ?></h3>
            <div class="campus-card__schools">
              <span class="tag tag--esce">ESCE</span>
              <span class="tag tag--inseec">INSEEC</span>
            </div>
            <p class="campus-card__info"><?= corpo_t('index.campus_info_citroen') ?></p>
          </div>
        </article>
        <article class="campus-card" data-reveal>
          <img src="images/campus-citadelle.webp" alt="<?= htmlspecialchars(corpo_t('index.campus_alt_citadelle')) ?>" class="campus-card__img">
          <div class="campus-card__body">
            <span class="campus-card__year"><?= htmlspecialchars(corpo_t('index.campus_year_citadelle')) ?></span>
            <h3 class="campus-card__title"><?= htmlspecialchars(corpo_t('index.campus_title_citadelle')) ?></h3>
            <div class="campus-card__schools">
              <span class="tag tag--ece">ECE</span>
              <span class="tag tag--heip">HEIP</span>
              <span class="tag tag--sup">Sup de Pub</span>
            </div>
            <p class="campus-card__info"><?= corpo_t('index.campus_info_citadelle') ?></p>
          </div>
        </article>
      </div>
    </div>
  </section>

  <!-- partenaires -->
  <?php if (!empty($featuredPt)): ?>
  <section class="section home-partners-section">
    <div class="container">
      <div class="home-section-header">
        <div>
          <span class="section-label"><?= htmlspecialchars(corpo_t('index.partners_label')) ?></span>
          <h2 class="section-title"><?= htmlspecialchars(corpo_t('index.partners_title')) ?></h2>
        </div>
        <a href="partenaires.php" class="btn btn--ghost btn--sm"><?= htmlspecialchars(corpo_t('index.partners_all')) ?></a>
      </div>

      <div class="home-partners-row">
        <?php
        $ptColors = [
          'Sport'=>'#22c55e','Restauration'=>'#f97316',
          'Culture'=>'#a855f7','Travail'=>'#3b82f6','RSE'=>'#10b981',
        ];
        foreach ($featuredPt as $p):
          $c = $ptColors[$p['type']] ?? '#5D0282';
          $init = mb_strtoupper(mb_substr($p['nom'],0,1));
        ?>
          <div class="home-partner-card">
            <div class="home-partner-card__logo" style="border-color:<?= $c ?>22;background:<?= $c ?>18">
              <?php if ($p['logo'] && $p['logo'] !== 'images/partner-placeholder.png'): ?>
                <img src="<?= htmlspecialchars($p['logo']) ?>" alt="<?= htmlspecialchars($p['nom']) ?>">
              <?php else: ?>
                <span style="color:<?= $c ?>;font-weight:800;font-size:1.2rem"><?= $init ?></span>
              <?php endif; ?>
            </div>
            <div class="home-partner-card__body">
              <strong class="home-partner-card__name"><?= htmlspecialchars($p['nom']) ?></strong>
              <span class="home-partner-card__offer"><?= htmlspecialchars($p['offre']) ?></span>
              <?php if ($p['code']): ?>
                <code class="home-partner-card__code"><?= htmlspecialchars($p['code']) ?></code>
              <?php endif; ?>
            </div>
            <span class="home-partner-card__badge" style="background:<?= $c ?>22;color:<?= $c ?>">
              <?= htmlspecialchars($p['type']) ?>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
      <p style="text-align:center;margin-top:var(--s6)">
        <a href="demande-partenariat.php" class="btn btn--ghost btn--sm"><?= htmlspecialchars(corpo_t('index.become_partner')) ?></a>
      </p>
    </div>
  </section>
  <?php endif; ?>

  <!-- actus -->
  <?php if (!empty($actus)): ?>
  <section class="section section--alt home-actus-section">
    <div class="container">
      <div class="home-section-header">
        <div>
          <span class="section-label"><?= htmlspecialchars(corpo_t('index.news_label')) ?></span>
          <h2 class="section-title"><?= htmlspecialchars(corpo_t('index.news_title')) ?></h2>
        </div>
      </div>
      <div class="home-actus-grid">
        <?php foreach ($actus as $actu): ?>
          <article class="home-actu-card">
            <div class="home-actu-card__meta">
              <span class="home-actu-card__tag"><?= htmlspecialchars(ucfirst($actu['structure_type'])) ?></span>
              <span class="home-actu-card__date"><?= date('d/m/Y', strtotime($actu['created_at'])) ?></span>
            </div>
            <h3 class="home-actu-card__title"><?= htmlspecialchars($actu['titre']) ?></h3>
            <p class="home-actu-card__body"><?= htmlspecialchars(mb_substr($actu['contenu'], 0, 120)) ?>…</p>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- écoles -->
  <section class="section <?= empty($actus) ? 'section--alt' : '' ?>" aria-labelledby="ecoles-title">
    <div class="container">
      <span class="section-label"><?= htmlspecialchars(corpo_t('index.community_label')) ?></span>
      <h2 class="section-title section-title--center" id="ecoles-title"><?= htmlspecialchars(corpo_t('index.community_title')) ?></h2>
      <p class="section-intro"><?= htmlspecialchars(corpo_t('index.community_intro')) ?></p>
      <div class="grid grid--5">
        <article class="school-card school-card--ece"    data-reveal><h3 class="school-card__name">ECE</h3><p class="school-card__type"><?= htmlspecialchars(corpo_t('index.school_ece_type')) ?></p><p class="school-card__campus">Citadelle</p></article>
        <article class="school-card school-card--esce"   data-reveal><h3 class="school-card__name">ESCE</h3><p class="school-card__type"><?= htmlspecialchars(corpo_t('index.school_esce_type')) ?></p><p class="school-card__campus">Citroën</p></article>
        <article class="school-card school-card--heip"   data-reveal><h3 class="school-card__name">HEIP</h3><p class="school-card__type"><?= htmlspecialchars(corpo_t('index.school_heip_type')) ?></p><p class="school-card__campus">Citadelle</p></article>
        <article class="school-card school-card--inseec" data-reveal><h3 class="school-card__name">INSEEC</h3><p class="school-card__type"><?= htmlspecialchars(corpo_t('index.school_inseec_type')) ?></p><p class="school-card__campus">Citroën</p></article>
        <article class="school-card school-card--sup"    data-reveal><h3 class="school-card__name">Sup&nbsp;de&nbsp;Pub</h3><p class="school-card__type"><?= htmlspecialchars(corpo_t('index.school_sup_type')) ?></p><p class="school-card__campus">Citadelle</p></article>
      </div>
    </div>
  </section>

  <!-- CTA final -->
  <section class="cta-section">
    <div class="container">
      <h2 class="cta-section__title"><?= htmlspecialchars(corpo_t('index.cta_title')) ?></h2>
      <p class="cta-section__sub"><?= htmlspecialchars(corpo_t('index.cta_sub')) ?></p>
      <div class="cta-section__actions">
        <a href="register.php"     class="btn btn--primary btn--lg"><?= htmlspecialchars(corpo_t('index.cta_register')) ?></a>
        <a href="associations.php" class="btn btn--ghost btn--lg"><?= htmlspecialchars(corpo_t('index.cta_explore')) ?></a>
      </div>
    </div>
  </section>

  <!-- Lightbox (gardé pour compatibilité) -->
  <div id="lightbox" class="lightbox" role="dialog" aria-modal="true" aria-label="<?= htmlspecialchars(corpo_t('index.lightbox')) ?>" hidden>
    <button class="lightbox__close" aria-label="<?= htmlspecialchars(corpo_t('index.lightbox_close')) ?>">&times;</button>
    <img src="" alt="" class="lightbox__img">
  </div>

</main>

<?php require_once 'includes/footer.php'; ?>
