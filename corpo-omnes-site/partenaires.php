<?php
require_once 'includes/i18n.php';
$title = corpo_t('pt.meta_title');
$page  = 'partenaires';
require_once 'includes/db.php';
require_once 'includes/header.php';

$partenaires = $pdo->query("SELECT * FROM partenaires WHERE statut='publie' ORDER BY nom ASC")->fetchAll();
$total       = count($partenaires);

// Couleurs par catégorie (pour le bandeau coloré des cartes)
$catColors = [
  'Sport'        => ['#22c55e','#16a34a'],
  'Restauration' => ['#f97316','#ea580c'],
  'Culture'      => ['#a855f7','#9333ea'],
  'Travail'      => ['#3b82f6','#2563eb'],
  'RSE'          => ['#10b981','#059669'],
  'default'      => ['#5D0282','#8B2FC9'],
];
?>

<main>

  <!-- ─ Hero ──────────────────────────────────────────────────────────────── -->
  <section class="page-hero">
    <div class="container">
      <nav class="breadcrumb"><a href="index.php"><?= htmlspecialchars(corpo_t('common.breadcrumb_home')) ?></a><span>›</span><span><?= htmlspecialchars(corpo_t('pt.crumb')) ?></span></nav>
      <h1><?= htmlspecialchars(corpo_t('pt.hero_title')) ?></h1>
      <p class="page-hero__sub"><?= corpo_t('pt.hero_sub') ?></p>

      <!-- Chiffres clés rapides -->
      <div class="pt-hero-stats">
        <div class="pt-hero-stat">
          <span class="pt-hero-stat__num"><?= $total ?></span>
          <span class="pt-hero-stat__label"><?= htmlspecialchars(corpo_t('pt.stat_active')) ?></span>
        </div>
        <div class="pt-hero-stat">
          <span class="pt-hero-stat__num">6 000</span>
          <span class="pt-hero-stat__label"><?= htmlspecialchars(corpo_t('pt.stat_students')) ?></span>
        </div>
        <div class="pt-hero-stat">
          <span class="pt-hero-stat__num">2</span>
          <span class="pt-hero-stat__label"><?= htmlspecialchars(corpo_t('pt.stat_campus')) ?></span>
        </div>
      </div>
    </div>
  </section>

  <!-- ─ Filtres + Grille ──────────────────────────────────────────────────── -->
  <section class="section">
    <div class="container">

      <!-- Barre de filtres sticky -->
      <div class="pt-filter-bar" id="pt-filters">
        <div class="pt-filter-left">
          <button class="pt-chip pt-chip--active" data-type=""><?= htmlspecialchars(corpo_t('pt.chip_all')) ?> <span class="pt-chip__count"><?= $total ?></span></button>
          <?php
          $cats = [];
          foreach ($partenaires as $p) {
              $t = $p['type'] ?: 'Autre';
              $cats[$t] = ($cats[$t] ?? 0) + 1;
          }
          foreach ($cats as $cat => $n):
          ?>
            <button class="pt-chip" data-type="<?= htmlspecialchars($cat) ?>">
              <?= htmlspecialchars($cat) ?> <span class="pt-chip__count"><?= $n ?></span>
            </button>
          <?php endforeach; ?>
        </div>

        <div class="pt-filter-right">
          <select id="pt-campus" class="pt-select">
            <option value=""><?= htmlspecialchars(corpo_t('pt.campus_all')) ?></option>
            <option value="Citadelle">Citadelle</option>
            <option value="Citroën">Citroën</option>
          </select>
          <div class="pt-search">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="search" id="pt-search" placeholder="<?= htmlspecialchars(corpo_t('pt.search_ph')) ?>">
          </div>
          <span id="pt-count" class="pt-filter-count"><?= $total ?> <?= htmlspecialchars(corpo_t('pt.results')) ?></span>
        </div>
      </div>

      <!-- Grille de cartes -->
      <div class="pt-grid" id="partner-grid">
        <?php foreach ($partenaires as $p):
          [$c1,$c2] = $catColors[$p['type']] ?? $catColors['default'];
          $initiale = mb_strtoupper(mb_substr($p['nom'], 0, 1));
        ?>
        <article class="pt-card"
                 data-type="<?= htmlspecialchars($p['type']) ?>"
                 data-campus="<?= htmlspecialchars($p['campus']) ?>"
                 data-nom="<?= htmlspecialchars(mb_strtolower($p['nom'])) ?>">

          <!-- Bandeau couleur catégorie -->
          <div class="pt-card__band" style="background:linear-gradient(135deg,<?= $c1 ?>,<?= $c2 ?>)">
            <span class="pt-card__cat-badge"><?= htmlspecialchars($p['type']) ?></span>
            <div class="pt-card__logo-wrap">
              <?php if ($p['logo'] && $p['logo'] !== 'images/partner-placeholder.png'): ?>
                <img src="<?= htmlspecialchars($p['logo']) ?>" alt="<?= htmlspecialchars($p['nom']) ?>" class="pt-card__logo-img">
              <?php else: ?>
                <span class="pt-card__logo-init"><?= $initiale ?></span>
              <?php endif; ?>
            </div>
          </div>

          <!-- Corps de la carte -->
          <div class="pt-card__body">
            <h3 class="pt-card__name"><?= htmlspecialchars($p['nom']) ?></h3>

            <?php if ($p['offre']): ?>
              <div class="pt-card__offer">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                <?= htmlspecialchars($p['offre']) ?>
              </div>
            <?php endif; ?>

            <?php if ($p['description']): ?>
              <p class="pt-card__desc"><?= htmlspecialchars(mb_substr($p['description'], 0, 100)) ?>…</p>
            <?php endif; ?>

            <div class="pt-card__foot">
              <span class="pt-card__campus">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                <?= htmlspecialchars($p['campus']) ?>
              </span>

              <?php if ($p['code']): ?>
                <span class="pt-card__code" onclick="copyCode(this, '<?= htmlspecialchars($p['code']) ?>')" title="Cliquer pour copier">
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                  <?= htmlspecialchars($p['code']) ?>
                </span>
              <?php endif; ?>
            </div>

            <?php if ($p['lien'] && $p['lien'] !== '#'): ?>
              <a href="<?= htmlspecialchars($p['lien']) ?>" class="pt-card__btn" target="_blank" rel="noopener">
                Voir l'offre
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/></svg>
              </a>
            <?php endif; ?>
          </div>
        </article>
        <?php endforeach; ?>
      </div>

      <p id="pt-empty" class="empty-state" hidden>Aucun partenaire ne correspond à votre recherche.</p>

    </div>
  </section>

  <!-- ─ RSE ───────────────────────────────────────────────────────────────── -->
  <section class="section section--alt">
    <div class="container">
      <span class="section-label">Engagement</span>
      <h2 class="section-title section-title--center">Responsabilité sociétale (RSE)</h2>
      <p class="section-intro">La Corpo s'engage pour une vie étudiante plus responsable, inclusive et solidaire.</p>
      <div class="grid grid--3">
        <article class="card" data-reveal>
          <span class="card__icon">♻️</span>
          <span class="card__eyebrow">Environnement</span>
          <h3 class="card__title">Réduire l'impact</h3>
          <p class="card__body">Gestes concrets pour réduire l'empreinte écologique des événements et du quotidien étudiant.</p>
          <ul class="card__list"><li>Tri et réduction des déchets</li><li>Gobelets réutilisables</li><li>Achats groupés éco-responsables</li></ul>
        </article>
        <article class="card" data-reveal>
          <span class="card__icon"></span>
          <span class="card__eyebrow">Prévention</span>
          <h3 class="card__title">Sensibilisation</h3>
          <p class="card__body">Actions d'éducation et de prévention pour une communauté plus informée et plus respectueuse.</p>
          <ul class="card__list"><li>Prévention santé et sécurité</li><li>Inclusion et diversité</li><li>Lutte contre les discriminations</li></ul>
        </article>
        <article class="card" data-reveal>
          <span class="card__icon">❤️</span>
          <span class="card__eyebrow">Solidarité</span>
          <h3 class="card__title">Engagement solidaire</h3>
          <p class="card__body">Initiatives concrètes pour agir au-delà du campus et contribuer à des causes qui comptent.</p>
          <ul class="card__list"><li>Collectes alimentaires et vestimentaires</li><li>Actions de bénévolat</li><li>Projets à impact social</li></ul>
        </article>
      </div>
    </div>
  </section>

  <!-- ─ Tableau récapitulatif ─────────────────────────────────────────────── -->
  <section class="section">
    <div class="container">
      <span class="section-label">Annuaire</span>
      <h2 class="section-title">Récapitulatif des offres</h2>
      <p class="lead">Présentez ce tableau à la caisse pour bénéficier des avantages partenaires.</p>
      <div class="table-wrap">
        <table class="partners-table">
          <thead>
            <tr>
              <th scope="col">Partenaire</th>
              <th scope="col">Catégorie</th>
              <th scope="col">Offre</th>
              <th scope="col">Code promo</th>
              <th scope="col">Campus</th>
              <th scope="col">Lien</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($partenaires as $p): ?>
            <tr>
              <td data-label="Partenaire"><strong><?= htmlspecialchars($p['nom']) ?></strong></td>
              <td data-label="Catégorie"><?= htmlspecialchars($p['type']) ?></td>
              <td data-label="Offre"><?= htmlspecialchars($p['offre']) ?></td>
              <td data-label="Code promo"><?= $p['code'] ? '<code>'.htmlspecialchars($p['code']).'</code>' : '-' ?></td>
              <td data-label="Campus"><?= htmlspecialchars($p['campus']) ?></td>
              <td data-label="Lien">
                <?php if ($p['lien'] && $p['lien'] !== '#'): ?>
                  <a href="<?= htmlspecialchars($p['lien']) ?>" target="_blank" rel="noopener">Voir →</a>
                <?php else: ?>-<?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <!-- ─ CTA ───────────────────────────────────────────────────────────────── -->
  <section class="cta-section">
    <div class="container">
      <h2 class="cta-section__title">Vous souhaitez devenir partenaire ?</h2>
      <p class="cta-section__sub">Rejoignez les partenaires de la Corpo Omnes Lyon et bénéficiez d'une visibilité auprès de 6 000 étudiants.</p>
      <div class="cta-section__actions">
        <a href="demande-partenariat.php" class="btn btn--primary btn--lg">Déposer une demande →</a>
        <a href="mailto:corpoomnes@gmail.com" class="btn btn--ghost btn--lg">Nous contacter</a>
      </div>
    </div>
  </section>

</main>

<!-- Toast copie code promo -->
<div id="copy-toast" style="
  position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;
  background:#22c55e;color:#fff;border-radius:999px;
  padding:.55rem 1.1rem;font-size:.82rem;font-weight:700;
  box-shadow:0 4px 20px rgba(0,0,0,.4);
  transform:translateY(100px);opacity:0;transition:all .3s cubic-bezier(.34,1.56,.64,1)
">Code copié !</div>

<?php
$extraScripts = ['js/partenaires.js'];
require_once 'includes/footer.php';
?>
