<?php
$page = 'sport';
require_once 'includes/db.php';

$slug = trim($_GET['s'] ?? '');
if ($slug === '') {
    header('Location: sport.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM sports WHERE slug = ?");
$stmt->execute([$slug]);
$sport = $stmt->fetch();

if (!$sport) {
    header('Location: sport.php');
    exit;
}

$title = $sport['nom'];
require_once 'includes/header.php';

$referents    = $pdo->prepare("SELECT * FROM sport_referents    WHERE sport_id = ? ORDER BY id");
$entrainements= $pdo->prepare("SELECT * FROM sport_entrainements WHERE sport_id = ? ORDER BY id");
$evenements   = $pdo->prepare("SELECT * FROM sport_evenements   WHERE sport_id = ? ORDER BY date");
$resultats    = $pdo->prepare("SELECT * FROM sport_resultats    WHERE sport_id = ? ORDER BY date DESC");

foreach ([$referents, $entrainements, $evenements, $resultats] as $s) {
    $s->execute([$sport['id']]);
}
$refs  = $referents->fetchAll();
$ents  = $entrainements->fetchAll();
$evts  = $evenements->fetchAll();
$ress  = $resultats->fetchAll();

$dispo = $sport['places'] - $sport['inscrits'];
$pct   = $sport['places'] > 0 ? round(($sport['inscrits'] / $sport['places']) * 100) : 0;
?>

  <main id="sport-main">

        <section class="page-hero" style="--sport-color: <?= htmlspecialchars($sport['couleur']) ?>">
      <div class="container">
        <nav class="breadcrumb" aria-label="Fil d'Ariane">
          <a href="index.php">Accueil</a><span aria-hidden="true">›</span>
          <a href="sport.php">Sport</a><span aria-hidden="true">›</span>
          <span><?= htmlspecialchars($sport['nom']) ?></span>
        </nav>
        <h1><?= htmlspecialchars($sport['icon']) ?> <?= htmlspecialchars($sport['nom']) ?></h1>
        <p class="page-hero__sub"><?= htmlspecialchars($sport['description']) ?></p>
      </div>
    </section>

    <div class="container sport-detail__layout">

            <div class="sport-detail__main">

                <section class="section-card" aria-labelledby="ent-title">
          <h2 class="section-card__title" id="ent-title">Entraînements</h2>
          <?php if (empty($ents)): ?>
            <p class="empty-state">Aucun entraînement renseigné pour le moment.</p>
          <?php else: ?>
            <ul class="training-list">
              <?php foreach ($ents as $e): ?>
              <li class="training-item">
                <strong class="training-item__day"><?= htmlspecialchars($e['jour']) ?></strong>
                <span class="training-item__hour"><?= htmlspecialchars($e['heure']) ?></span>
                <span class="training-item__place"><?= htmlspecialchars($e['lieu']) ?></span>
              </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </section>

                <section class="section-card" aria-labelledby="evt-title">
          <h2 class="section-card__title" id="evt-title">Prochains événements</h2>
          <?php if (empty($evts)): ?>
            <p class="empty-state">Aucun événement programmé.</p>
          <?php else: ?>
            <ul class="event-list-mini">
              <?php foreach ($evts as $ev): ?>
              <li class="event-mini">
                <span class="event-mini__date"><?= date('d/m/Y', strtotime($ev['date'])) ?></span>
                <div>
                  <strong><?= htmlspecialchars($ev['titre']) ?></strong>
                  <span class="event-mini__lieu"><?= htmlspecialchars($ev['lieu']) ?></span>
                </div>
              </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </section>

                <section class="section-card" aria-labelledby="res-title">
          <h2 class="section-card__title" id="res-title">⚡ Résultats récents</h2>
          <?php if (empty($ress)): ?>
            <p class="empty-state">Aucun résultat renseigné.</p>
          <?php else: ?>
            <div class="results-mini">
              <?php foreach ($ress as $r):
                $vic = $r['victoire'];
                $cls = $vic === null ? 'draw' : ($vic ? 'win' : 'loss');
                $label = $vic === null ? 'Nul' : ($vic ? 'V' : 'D');
              ?>
              <div class="result-mini result-mini--<?= $cls ?>">
                <span class="result-mini__label"><?= $label ?></span>
                <div>
                  <strong><?= htmlspecialchars($r['score']) ?></strong>
                  vs <?= htmlspecialchars($r['adversaire']) ?>
                </div>
                <span class="result-mini__date"><?= date('d/m/Y', strtotime($r['date'])) ?></span>
              </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>

      </div>
            <aside class="sport-detail__aside">

                <div class="aside-card">
          <h3 class="aside-card__title">Inscription</h3>
          <div class="sport-hub-card__places">
            <div class="sport-hub-card__bar-track">
              <div class="sport-hub-card__bar-fill" style="width: <?= $pct ?>%"></div>
            </div>
            <span><?= $sport['inscrits'] ?> / <?= $sport['places'] ?> inscrits</span>
          </div>
          <?php if ($dispo > 0): ?>
            <p class="aside-card__dispo"><?= $dispo ?> place<?= $dispo > 1 ? 's' : '' ?> disponible<?= $dispo > 1 ? 's' : '' ?></p>
            <a href="mailto:<?= htmlspecialchars($refs[0]['email'] ?? 'sport.omnes.lyon@gmail.com') ?>?subject=Inscription <?= rawurlencode($sport['nom']) ?>"
               class="btn btn--primary" style="width:100%;text-align:center">M'inscrire</a>
          <?php else: ?>
            <p class="aside-card__full">Complet pour cette saison</p>
          <?php endif; ?>
          <p class="aside-card__campus">Campus : <?= htmlspecialchars($sport['campus']) ?></p>
        </div>

                <?php if (!empty($refs)): ?>
        <div class="aside-card">
          <h3 class="aside-card__title">Référents</h3>
          <?php foreach ($refs as $ref): ?>
          <div class="referent-item">
            <div class="referent-item__avatar"><?= htmlspecialchars($ref['initiales']) ?></div>
            <div>
              <strong><?= htmlspecialchars($ref['nom']) ?></strong>
              <span class="referent-item__role"><?= htmlspecialchars($ref['role']) ?></span>
              <a href="mailto:<?= htmlspecialchars($ref['email']) ?>" class="referent-item__mail">
                <?= htmlspecialchars($ref['email']) ?>
              </a>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <a href="sport.php" class="btn btn--ghost" style="width:100%;text-align:center">← Retour aux sports</a>

      </aside>

    </div>
  </main>

<?php require_once 'includes/footer.php'; ?>
