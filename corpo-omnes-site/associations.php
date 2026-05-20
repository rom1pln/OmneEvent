<?php
require_once 'includes/i18n.php';
$title = corpo_t('asso.meta_title');
$page  = 'associations';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/associations-activity.php';
require_once 'includes/header.php';

$mandatReady = asso_has_mandat_columns($pdo);
$assos = $pdo->query("SELECT * FROM associations ORDER BY type, nom ASC")->fetchAll();
$totalActive = 0;
$totalInactive = 0;
foreach ($assos as &$aRow) {
    $aRow['_active'] = !$mandatReady || asso_is_active($aRow);
    if ($aRow['_active']) {
        $totalActive++;
    } else {
        $totalInactive++;
    }
}
unset($aRow);
$total = $totalActive;

// Valeurs de filtre disponibles
// Ordre souhaité : écoles non-INSEEC en alpha, puis les 5 programmes INSEEC regroupés
$ecolesRaw = array_values(array_unique(array_filter(
    array_column($assos, 'ecole'),
    fn($e) => $e && $e !== 'Toutes'
)));

$ecoleOrder = ['ECE','ESCE','HEIP','Sup de Pub','INSEEC Bachelor','INSEEC BBA','INSEEC BTS','INSEEC GE','INSEEC MSc'];
usort($ecolesRaw, function($a, $b) use ($ecoleOrder) {
    $ia = array_search($a, $ecoleOrder);
    $ib = array_search($b, $ecoleOrder);
    $ia = ($ia === false) ? 99 : $ia;
    $ib = ($ib === false) ? 99 : $ib;
    return $ia <=> $ib;
});
$ecolesDispos = $ecolesRaw;

// Types distincts pour les chips (on mappe en "thèmes")
$typesDispos = array_values(array_unique(array_column($assos, 'type')));
$typeOrder   = ['Corpo','BDE','BDS','Association','Fédération','Junior'];
usort($typesDispos, fn($a,$b) =>
    (array_search($a,$typeOrder) ?? 99) <=> (array_search($b,$typeOrder) ?? 99)
);

$assosJson = json_encode($assos, JSON_UNESCAPED_UNICODE);
?>

<main>
  <section class="page-hero">
    <div class="container">
      <nav class="breadcrumb"><a href="index.php"><?= htmlspecialchars(corpo_t('common.breadcrumb_home')) ?></a><span>›</span><span><?= htmlspecialchars(corpo_t('asso.crumb')) ?></span></nav>
      <h1><?= htmlspecialchars(corpo_t('asso.hero_title')) ?></h1>
      <p class="page-hero__sub">
        <span id="asso-count"><?= $total ?></span> <?= corpo_t('asso.hero_sub') ?>
      </p>
      <?php if (isLoggedIn()): ?>
        <a href="proposer-asso.php" class="btn btn--secondary" style="margin-top:var(--s4);display:inline-block">
          <?= htmlspecialchars(corpo_t('asso.propose')) ?>
        </a>
      <?php endif; ?>
    </div>
  </section>

  <section class="section">
    <div class="container">

      <!-- filtres -->
      <div class="asso-filters">

        <!-- Recherche par nom -->
        <div class="asso-search-wrap">
          <svg class="asso-search-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
            <circle cx="8.5" cy="8.5" r="5.5" stroke="currentColor" stroke-width="1.6"/>
            <path d="M13 13l3.5 3.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
          </svg>
          <input type="search" id="asso-search" class="asso-search-input"
                 placeholder="<?= htmlspecialchars(corpo_t('asso.search_ph')) ?>" autocomplete="off"
                 aria-label="<?= htmlspecialchars(corpo_t('asso.search_aria')) ?>">
          <button class="asso-search-clear" id="search-clear" aria-label="<?= htmlspecialchars(corpo_t('asso.search_clear')) ?>" hidden>✕</button>
        </div>

        <!-- Filtre École -->
        <div class="filter-section">
          <span class="filter-label"><?= htmlspecialchars(corpo_t('asso.filter_school')) ?></span>
          <div class="filter-chips" role="group" aria-label="<?= htmlspecialchars(corpo_t('asso.filter_school')) ?>">
            <button class="filter-chip active" data-ecole=""><?= htmlspecialchars(corpo_t('asso.chip_all_school')) ?></button>
            <?php foreach ($ecolesDispos as $e): ?>
              <button class="filter-chip" data-ecole="<?= htmlspecialchars($e) ?>">
                <?= htmlspecialchars($e) ?>
              </button>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Filtre Thème / Type de structure -->
        <div class="filter-section">
          <span class="filter-label"><?= htmlspecialchars(corpo_t('asso.filter_structure')) ?></span>
          <div class="filter-chips" role="group" aria-label="<?= htmlspecialchars(corpo_t('asso.filter_structure')) ?>">
            <button class="filter-chip active" data-type=""><?= htmlspecialchars(corpo_t('asso.chip_all_type')) ?></button>
            <?php foreach ($typesDispos as $t): ?>
              <button class="filter-chip" data-type="<?= htmlspecialchars($t) ?>">
                <?= htmlspecialchars($t) ?>
              </button>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Ligne basse : campus + tri + reset -->
        <div class="filter-row">
          <select id="filter-campus" class="filter-select" aria-label="<?= htmlspecialchars(corpo_t('asso.aria_campus')) ?>">
            <option value=""><?= htmlspecialchars(corpo_t('asso.all_campus')) ?></option>
            <option value="Citadelle"><?= htmlspecialchars(corpo_t('asso.campus_opt_citadelle')) ?></option>
            <option value="Citroën"><?= htmlspecialchars(corpo_t('asso.campus_opt_citroen')) ?></option>
          </select>
          <select id="filter-sort" class="filter-select" aria-label="<?= htmlspecialchars(corpo_t('asso.aria_sort')) ?>">
            <option value="alpha"><?= htmlspecialchars(corpo_t('asso.sort_alpha')) ?></option>
            <option value="type"><?= htmlspecialchars(corpo_t('asso.sort_type')) ?></option>
          </select>
          <button id="filter-reset" class="btn btn--sm filter-reset-btn" aria-label="<?= htmlspecialchars(corpo_t('asso.aria_reset')) ?>">
            <?= htmlspecialchars(corpo_t('asso.reset')) ?>
          </button>
        </div>
        <?php if ($mandatReady && $totalInactive > 0): ?>
        <label class="asso-show-inactive">
          <input type="checkbox" id="asso-show-inactive" value="1">
          <span><?= htmlspecialchars(corpo_t('asso.show_inactive')) ?></span>
          <small>(<?= (int)$totalInactive ?>)</small>
        </label>
        <?php endif; ?>
      </div>

      <!-- grille des assos -->
      <div class="grid grid--3" id="asso-grid" role="list" aria-live="polite">
        <?php foreach ($assos as $a): ?>
          <a href="structure.php?slug=<?= urlencode($a['slug']) ?>"
             class="asso-card-link"
             data-type="<?= htmlspecialchars($a['type']) ?>"
             data-ecole="<?= htmlspecialchars($a['ecole']) ?>"
             data-campus="<?= htmlspecialchars($a['campus']) ?>"
             data-nom="<?= htmlspecialchars(mb_strtolower($a['nom'])) ?>"
             data-desc="<?= htmlspecialchars(mb_strtolower($a['description'] ?? '')) ?>"
             data-slug="<?= htmlspecialchars($a['slug']) ?>"
             data-active="<?= !empty($a['_active']) ? '1' : '0' ?>"
             data-inactive="<?= empty($a['_active']) ? '1' : '0' ?>"
             role="listitem">
            <article class="asso-card<?= empty($a['_active']) ? ' asso-card--inactive' : '' ?>" style="--asso-color:<?= htmlspecialchars($a['color']) ?>">
              <div class="asso-card__header">
                <?php if (!empty($a['logo'])): ?>
                  <img src="<?= htmlspecialchars($a['logo']) ?>"
                       alt="Logo <?= htmlspecialchars($a['nom']) ?>"
                       class="asso-card__logo">
                <?php else: ?>
                  <div class="asso-card__initials"
                       style="background:<?= htmlspecialchars($a['color']) ?>">
                    <?= htmlspecialchars(mb_substr($a['nom'], 0, 2)) ?>
                  </div>
                <?php endif; ?>
                <div>
                  <h3 class="asso-card__name"><?= htmlspecialchars($a['nom']) ?></h3>
                  <span class="asso-card__school"><?= htmlspecialchars($a['ecole']) ?></span>
                </div>
              </div>
              <?php if (!empty($a['description'])): ?>
                <p class="asso-card__desc">
                  <?= htmlspecialchars(mb_substr($a['description'], 0, 110)) ?>…
                </p>
              <?php endif; ?>
              <div class="asso-card__footer">
                <span class="tag"><?= htmlspecialchars($a['type']) ?></span>
                <?php if (empty($a['_active'])): ?>
                  <span class="tag asso-card__status asso-card__status--inactive"><?= htmlspecialchars(corpo_t('asso.badge_inactive')) ?></span>
                <?php endif; ?>
                <span class="asso-card__campus"><?= htmlspecialchars($a['campus']) ?></span>
              </div>
            </article>
          </a>
        <?php endforeach; ?>
      </div>

      <p id="asso-empty" class="empty-state" hidden>
        Aucune association ne correspond à votre recherche.
        <button onclick="document.getElementById('filter-reset').click()"
                style="display:block;margin:.8rem auto 0;font-size:.8rem;color:var(--purple-light);background:none;border:none;cursor:pointer;text-decoration:underline">
          Réinitialiser les filtres
        </button>
      </p>

    </div>
  </section>

</main>

<script src="js/associations.js?v=8" defer></script>

<?php
$partenairesAsso = $pdo->query(
    "SELECT p.*, a.nom AS asso_nom, a.slug AS asso_slug
     FROM partenaires p
     LEFT JOIN associations a ON p.structure_type IN ('asso','bde','bds') AND a.id = p.structure_id
     WHERE p.statut = 'publie' AND p.structure_type IN ('asso','bde','bds')
     ORDER BY a.nom, p.nom LIMIT 30"
)->fetchAll();
?>
<?php if (!empty($partenairesAsso)): ?>
<section class="section" style="border-top:1px solid var(--border);padding-top:var(--s10)">
  <div class="container">
    <h2 class="section-title" style="font-size:1.1rem;margin-bottom:var(--s5)">Partenaires des associations</h2>
    <div style="display:flex;flex-wrap:wrap;gap:var(--s3)">
      <?php foreach ($partenairesAsso as $pt): ?>
        <a href="structure.php?slug=<?= urlencode($pt['asso_slug'] ?? '') ?>"
           title="Partenaire de <?= htmlspecialchars($pt['asso_nom'] ?? '?') ?>"
           class="partner-pill"
           style="display:flex;align-items:center;gap:.5rem;background:var(--surface);border:1px solid var(--border);border-radius:var(--r-md);padding:.4rem .9rem;text-decoration:none;color:var(--text);font-size:.78rem;transition:border-color var(--ease)">
          <?php if (!empty($pt['logo'])): ?>
            <img src="<?= htmlspecialchars($pt['logo']) ?>" alt="" style="height:20px;width:auto;object-fit:contain">
          <?php endif; ?>
          <span><?= htmlspecialchars($pt['nom']) ?></span>
          <span style="font-size:.65rem;color:var(--text-muted)"><?= htmlspecialchars($pt['asso_nom'] ?? '') ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
