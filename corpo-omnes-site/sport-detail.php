<?php
declare(strict_types=1);

$page = 'sport';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/sports.php';
require_once __DIR__ . '/includes/i18n.php';

$slug = trim($_GET['s'] ?? '');
if ($slug === '') {
    header('Location: sport.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM sports WHERE slug = ?');
$stmt->execute([$slug]);
$sport = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sport) {
    header('Location: sport.php');
    exit;
}

$sport = sport_attach_capacity($pdo, $sport);
$sportId = (int)$sport['id'];
$userId  = isLoggedIn() ? (int)$_SESSION['user_id'] : 0;
$flashMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userId) {
    $action = $_POST['action'] ?? '';
    $motiv  = trim($_POST['message'] ?? '');

    if ($action === 'rejoindre') {
        $isLibre = ($sport['categorie'] ?? '') === 'individuel';
        if ($isLibre) {
            sport_register_free_access($pdo, $userId, $sportId);
            $flashMsg = 'Accès libre activé !';
        } else {
            $chk = $pdo->prepare(
                "SELECT id FROM demandes_adhesion WHERE user_id=? AND structure_type='sport' AND structure_id=? AND statut='en_attente'"
            );
            $chk->execute([$userId, $sportId]);
            if ($chk->fetchColumn()) {
                $flashMsg = corpo_t('sport.flash_pending');
            } else {
                $pdo->prepare(
                    "INSERT INTO demandes_adhesion (user_id, structure_type, structure_id, message, statut)
                     VALUES (?, 'sport', ?, ?, 'en_attente')"
                )->execute([$userId, $sportId, $motiv]);
                $flashMsg = corpo_t('sport.flash_sent');
            }
        }
    }

    if ($action === 'quitter') {
        sport_leave_member($pdo, $userId, $sportId);
        syncGlobalRoleAfterStructChange($pdo, $userId);
        $flashMsg = corpo_t('sport.flash_left');
    }

    if ($action === 'retirer_demande') {
        $pdo->prepare(
            "DELETE FROM demandes_adhesion WHERE user_id=? AND structure_type='sport' AND structure_id=? AND statut='en_attente'"
        )->execute([$userId, $sportId]);
        $flashMsg = 'Demande retirée.';
    }

    $stmt->execute([$slug]);
    $sport = sport_attach_capacity($pdo, $stmt->fetch(PDO::FETCH_ASSOC) ?: $sport);
}

$inscriptionStatut = null;
$demandeEnAttente  = false;
if ($userId) {
    $si = $pdo->prepare('SELECT statut FROM inscriptions_sport WHERE user_id = ? AND sport_id = ?');
    $si->execute([$userId, $sportId]);
    $inscriptionStatut = $si->fetchColumn() ?: null;

    $chk = $pdo->prepare(
        "SELECT 1 FROM demandes_adhesion WHERE user_id = ? AND structure_type = 'sport' AND structure_id = ? AND statut = 'en_attente'"
    );
    $chk->execute([$userId, $sportId]);
    $demandeEnAttente = (bool)$chk->fetchColumn();
}

$isMembre = $userId ? isMembreOf('sport', $sportId) : false;
$isLibre  = ($sport['categorie'] ?? '') === 'individuel';
$places   = (int)($sport['places'] ?? 0);
$complet  = (bool)($sport['_complet'] ?? false);
$dispo    = $sport['_places_dispo'];
$pct      = $sport['_pct'];

$title = $sport['nom'];
require_once __DIR__ . '/includes/header.php';

$referents     = $pdo->prepare('SELECT * FROM sport_referents WHERE sport_id = ? ORDER BY id');
$entrainements = $pdo->prepare('SELECT * FROM sport_entrainements WHERE sport_id = ? ORDER BY id');
$evenements    = $pdo->prepare('SELECT * FROM sport_evenements WHERE sport_id = ? ORDER BY date');
$resultats     = $pdo->prepare('SELECT * FROM sport_resultats WHERE sport_id = ? ORDER BY date DESC');

foreach ([$referents, $entrainements, $evenements, $resultats] as $s) {
    $s->execute([$sportId]);
}
$refs  = $referents->fetchAll();
$ents  = $entrainements->fetchAll();
$evts  = $evenements->fetchAll();
$ress  = $resultats->fetchAll();
?>

  <main id="sport-main">

    <section class="page-hero" style="--sport-color: <?= htmlspecialchars($sport['couleur']) ?>">
      <div class="container">
        <nav class="breadcrumb" aria-label="<?= htmlspecialchars(corpo_t('sport.breadcrumb_aria')) ?>">
          <a href="index.php"><?= htmlspecialchars(corpo_t('common.breadcrumb_home')) ?></a><span aria-hidden="true">›</span>
          <a href="sport.php"><?= htmlspecialchars(corpo_t('sport.crumb')) ?></a><span aria-hidden="true">›</span>
          <span><?= htmlspecialchars($sport['nom']) ?></span>
        </nav>
        <h1><?= htmlspecialchars($sport['icon'] ?? '') ?> <?= htmlspecialchars($sport['nom']) ?></h1>
        <p class="page-hero__sub"><?= htmlspecialchars($sport['description'] ?? '') ?></p>
        <?php if ($flashMsg): ?>
          <div style="margin-top:1rem;padding:.6rem 1rem;background:rgba(39,174,96,.15);border:1px solid rgba(39,174,96,.4);border-radius:var(--r-md);font-size:.85rem;color:#2ecc71"><?= htmlspecialchars($flashMsg) ?></div>
        <?php endif; ?>
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
                <span class="training-item__place"><?= htmlspecialchars($e['lieu'] ?? '') ?></span>
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
                  <span class="event-mini__lieu"><?= htmlspecialchars($ev['lieu'] ?? '') ?></span>
                </div>
              </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </section>

        <section class="section-card" aria-labelledby="res-title">
          <h2 class="section-card__title" id="res-title">Résultats récents</h2>
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
          <?php if ($places > 0): ?>
          <div class="sport-hub-card__places">
            <div class="sport-hub-card__bar-track">
              <div class="sport-hub-card__bar-fill" style="width: <?= $pct ?>%"></div>
            </div>
            <span><?= (int)$sport['inscrits'] ?> / <?= $places ?> inscrits</span>
          </div>
          <?php if ($dispo !== null && $dispo > 0): ?>
            <p class="aside-card__dispo"><?= htmlspecialchars(sprintf(corpo_t('sport.places_avail'), $dispo)) ?></p>
          <?php elseif ($places > 0): ?>
            <p class="aside-card__full"><?= htmlspecialchars(corpo_t('common.full')) ?> — <?= htmlspecialchars(corpo_t('sport.waitlist')) ?></p>
          <?php endif; ?>
          <?php else: ?>
            <p class="aside-card__dispo"><?= (int)$sport['inscrits'] ?> inscrit<?= $sport['inscrits'] > 1 ? 's' : '' ?> (places illimitées)</p>
          <?php endif; ?>

          <?php if (!$userId): ?>
            <a href="login.php?redirect=<?= rawurlencode('sport-detail.php?s=' . $slug) ?>" class="btn btn--primary" style="width:100%;text-align:center;margin-top:.75rem"><?= htmlspecialchars(corpo_t('sport.btn_join')) ?></a>
          <?php elseif ($isMembre || $inscriptionStatut === 'confirme'): ?>
            <form method="post" style="margin-top:.75rem" onsubmit="return confirm('Quitter ce sport ?')">
              <input type="hidden" name="action" value="quitter">
              <button type="submit" class="btn" style="width:100%;border-color:#e74c3c;color:#e74c3c;background:transparent"><?= htmlspecialchars(corpo_t('sport.btn_leave')) ?></button>
            </form>
            <p style="font-size:.75rem;color:#27ae60;margin-top:.5rem"><?= htmlspecialchars(corpo_t('sport.status_member')) ?></p>
          <?php elseif ($inscriptionStatut === 'liste_attente' || $demandeEnAttente): ?>
            <p style="font-size:.85rem;color:#e67e22;margin-top:.75rem"><?= htmlspecialchars(corpo_t('sport.status_pending')) ?></p>
            <?php if ($demandeEnAttente): ?>
            <form method="post" style="margin-top:.5rem">
              <input type="hidden" name="action" value="retirer_demande">
              <button type="submit" class="btn btn--sm" style="width:100%">Retirer ma demande</button>
            </form>
            <?php endif; ?>
          <?php elseif ($isLibre): ?>
            <form method="post" style="margin-top:.75rem">
              <input type="hidden" name="action" value="rejoindre">
              <button type="submit" class="btn btn--primary" style="width:100%">Accéder au sport</button>
            </form>
          <?php else: ?>
            <form method="post" style="margin-top:.75rem;display:flex;flex-direction:column;gap:.5rem">
              <input type="hidden" name="action" value="rejoindre">
              <textarea name="message" rows="2" placeholder="Niveau, motivation…" style="width:100%;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:6px;padding:.5rem;color:#fff;font-size:.8rem"></textarea>
              <button type="submit" class="btn btn--primary" style="width:100%">
                <?= $complet ? htmlspecialchars(corpo_t('sport.waitlist')) : htmlspecialchars(corpo_t('sport.btn_send_request')) ?>
              </button>
            </form>
          <?php endif; ?>
          <p class="aside-card__campus" style="margin-top:.75rem">Campus : <?= htmlspecialchars($sport['campus'] ?? '') ?></p>
        </div>

        <?php if (!empty($refs)): ?>
        <div class="aside-card">
          <h3 class="aside-card__title">Référents</h3>
          <?php foreach ($refs as $ref): ?>
          <div class="referent-item">
            <div class="referent-item__avatar"><?= htmlspecialchars($ref['initiales'] ?? '') ?></div>
            <div>
              <strong><?= htmlspecialchars($ref['nom'] ?? '') ?></strong>
              <span class="referent-item__role"><?= htmlspecialchars($ref['role'] ?? '') ?></span>
              <?php if (!empty($ref['email'])): ?>
              <a href="mailto:<?= htmlspecialchars($ref['email']) ?>" class="referent-item__mail"><?= htmlspecialchars($ref['email']) ?></a>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <a href="structure.php?sport=<?= htmlspecialchars($slug) ?>" class="btn btn--ghost" style="width:100%;text-align:center;margin-bottom:.5rem">Page structure</a>
        <a href="sport.php" class="btn btn--ghost" style="width:100%;text-align:center">← Retour aux sports</a>

      </aside>

    </div>

  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
