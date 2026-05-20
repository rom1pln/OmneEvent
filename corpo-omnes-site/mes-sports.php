<?php

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/i18n.php';

requireLogin();

$userId = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare(
    "SELECT sp.*, isc.statut AS insc_statut, isc.created_at AS inscrit_le
     FROM inscriptions_sport isc
     JOIN sports sp ON sp.id = isc.sport_id
     WHERE isc.user_id = ?
     ORDER BY isc.statut, sp.nom"
);
$stmt->execute([$userId]);
$mesSports = $stmt->fetchAll();

$sportIds = array_column($mesSports, 'id');

$entrainements = $resultats = [];
if (!empty($sportIds)) {
    $in = implode(',', array_fill(0, count($sportIds), '?'));
    $e = $pdo->prepare("SELECT se.*, sp.nom AS sport_nom, sp.couleur
                        FROM sport_entrainements se
                        JOIN sports sp ON sp.id = se.sport_id
                        WHERE se.sport_id IN ($in) ORDER BY sp.nom, se.id");
    $e->execute($sportIds); $entrainements = $e->fetchAll();

    $r = $pdo->prepare("SELECT sr.*, sp.nom AS sport_nom, sp.couleur
                        FROM sport_resultats sr
                        JOIN sports sp ON sp.id = sr.sport_id
                        WHERE sr.sport_id IN ($in) ORDER BY sr.date DESC LIMIT 10");
    $r->execute($sportIds); $resultats = $r->fetchAll();
}

$title = corpo_t('mes_sport.title');
$page  = 'sport';
require_once __DIR__ . '/includes/header.php';
?>

<main>
  <section class="page-hero">
    <div class="container">
      <nav class="breadcrumb"><a href="index.php"><?= htmlspecialchars(corpo_t('common.breadcrumb_home')) ?></a><span>›</span><span><?= htmlspecialchars(corpo_t('mes_sport.title')) ?></span></nav>
      <h1><?= htmlspecialchars(corpo_t('mes_sport.title')) ?></h1>
      <p class="page-hero__sub"><?= htmlspecialchars(corpo_t('mes_sport.sub')) ?></p>
      <a href="sport.php" class="btn btn--ghost btn--sm" style="margin-top:1rem"><?= htmlspecialchars(corpo_t('mes_sport.link_all')) ?></a>
    </div>
  </section>

  <div class="mes-page container" style="padding-top:var(--s8);padding-bottom:var(--s12)">

  <?php if (empty($mesSports)): ?>
    <div class="mes-empty">
      <p><?= htmlspecialchars(corpo_t('mes_sport.empty1')) ?></p>
      <a href="sport.php" class="btn-pill btn-primary"><?= htmlspecialchars(corpo_t('mes_sport.btn_explore')) ?></a>
    </div>
  <?php else: ?>

    <section class="mes-section">
      <h2><?= htmlspecialchars(corpo_t('mes_sport.title')) ?></h2>
      <div class="mes-cards-grid">
        <?php foreach ($mesSports as $sp): ?>
          <?php $color = $sp['couleur'] ?? '#5D0282'; ?>
          <a href="structure.php?sport=<?= urlencode($sp['slug']) ?>" class="mes-card"
             style="border-top:4px solid <?= htmlspecialchars($color) ?>">
            <div class="mes-card__logo" style="background:<?= htmlspecialchars($color) ?>22;color:<?= htmlspecialchars($color) ?>;font-size:1rem;font-weight:800;letter-spacing:-.02em">
              <?php if (!empty($sp['logo'])): ?>
                <img src="<?= htmlspecialchars($sp['logo']) ?>" alt="" style="width:100%;height:100%;object-fit:contain">
              <?php else: ?>
                <?= mb_strtoupper(mb_substr($sp['nom'], 0, 2)) ?>
              <?php endif; ?>
            </div>
            <div class="mes-card__body">
              <strong><?= htmlspecialchars($sp['nom']) ?></strong>
              <span><?= htmlspecialchars($sp['campus'] ?? corpo_t('mes_sport.campus_all')) ?></span>
              <?php if ($sp['insc_statut'] === 'confirme'): ?>
                <span class="badge-ok"><?= htmlspecialchars(corpo_t('mes_sport.badge_ok')) ?></span>
              <?php elseif ($sp['insc_statut'] === 'liste_attente'): ?>
                <span class="badge-wait"><?= htmlspecialchars(corpo_t('mes_sport.badge_wait')) ?></span>
              <?php endif; ?>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </section>

        <?php if (!empty($entrainements)): ?>
    <section class="mes-section">
      <h2><?= htmlspecialchars(corpo_t('mes_sport.planning_h2')) ?></h2>
      <div class="mes-training-list">
        <?php foreach ($entrainements as $entr): ?>
          <?php $color = $entr['couleur'] ?? '#5D0282'; ?>
          <div class="mes-training-item">
            <span class="mes-training-sport" style="background:<?= htmlspecialchars($color) ?>22;color:<?= htmlspecialchars($color) ?>"><?= htmlspecialchars($entr['sport_nom']) ?></span>
            <strong><?= htmlspecialchars($entr['jour']) ?></strong>
            <span><?= htmlspecialchars($entr['heure']) ?></span>
            <span class="lieu"><?= htmlspecialchars($entr['lieu']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

        <?php if (!empty($resultats)): ?>
    <section class="mes-section">
      <h2><?= htmlspecialchars(corpo_t('mes_sport.results_h2')) ?></h2>
      <div class="struct-results-list">
        <?php foreach ($resultats as $res): ?>
          <?php $win = $res['victoire']; $color = $res['couleur'] ?? '#5D0282'; ?>
          <div class="struct-result <?= $win === null ? 'draw' : ($win ? 'win' : 'loss') ?>">
            <span class="mes-training-sport" style="background:<?= htmlspecialchars($color) ?>22;color:<?= htmlspecialchars($color) ?>;font-size:.75rem;padding:2px 8px;border-radius:999px"><?= htmlspecialchars($res['sport_nom']) ?></span>
            <span class="struct-result__vs">vs <?= htmlspecialchars($res['adversaire']) ?></span>
            <span class="struct-result__score"><?= htmlspecialchars($res['score']) ?></span>
            <span class="struct-result__date"><?= date('d/m/Y', strtotime($res['date'])) ?></span>
            <span class="struct-result__badge">
              <?= $win === null ? 'Nul' : ($win ? 'Victoire' : 'Défaite') ?>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

  <?php endif; ?>

  </div></main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
