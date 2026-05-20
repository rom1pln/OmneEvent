<?php

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/billetterie.php';
require_once __DIR__ . '/includes/associations-activity.php';
require_once __DIR__ . '/includes/i18n.php';

$isSport  = isset($_GET['sport']);
$slug     = $_GET['slug'] ?? ($_GET['sport'] ?? '');

if (!$slug) {
    header('Location: associations.php');
    exit;
}

$struct   = null;
$type     = 'asso';

if ($isSport) {
    $stmt = $pdo->prepare("SELECT * FROM sports WHERE slug = ?");
    $stmt->execute([$slug]);
    $struct = $stmt->fetch();
    $type   = 'sport';
} else {
    $stmt = $pdo->prepare("SELECT * FROM associations WHERE slug = ?");
    $stmt->execute([$slug]);
    $struct = $stmt->fetch();
    if ($struct) {
        $rawType = strtolower($struct['type']);
        if ($rawType === 'bde') $type = 'bde';
        elseif ($rawType === 'bds') $type = 'bds';
        else $type = 'asso';
    }
}

if (!$struct) {
    header('Location: associations.php');
    exit;
}

$structId   = (int)$struct['id'];
$structNom  = $struct['nom'];
$color      = $struct['couleur'] ?? $struct['color'] ?? '#5D0282';
$assoInactiveBanner = !$isSport && !asso_is_active($struct);
$assoInactivePeriod = $assoInactiveBanner ? asso_format_mandat_period($struct) : '';

$userId        = isLoggedIn() ? (int)$_SESSION['user_id'] : 0;
$isMembre      = $userId ? isMembreOf($type, $structId) : false;
$isAdmin       = $userId ? isAdminOf($type, $structId)  : false;

$userEcole = '';
if ($userId) {
    $stE = $pdo->prepare("SELECT ecole FROM users WHERE id = ?");
    $stE->execute([$userId]);
    $userEcole = trim((string)($stE->fetchColumn() ?: ''));
}

$ecolesEligibles = [];
if (!$isSport && !empty($struct['ecoles_eligibles'])) {
    $decoded = json_decode((string)$struct['ecoles_eligibles'], true);
    if (is_array($decoded)) $ecolesEligibles = array_values(array_filter(array_map('strval', $decoded)));
}
$peutDemander = empty($ecolesEligibles) || ($userEcole && in_array($userEcole, $ecolesEligibles, true));

$flashMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userId) {
    $action = $_POST['action'] ?? '';
    $motiv  = trim($_POST['message'] ?? '');

    if ($action === 'rejoindre' && !$isSport && !empty($ecolesEligibles) && (!$userEcole || !in_array($userEcole, $ecolesEligibles, true))) {

        $flashMsg = "Cette structure n'accepte les demandes que des écoles suivantes : " . implode(', ', $ecolesEligibles) . '.';
        $action = '';
    }

    if ($action === 'rejoindre') {

        $isLibre = $isSport && ($struct['categorie'] ?? '') === 'individuel';

        if ($isLibre) {

            $pdo->prepare("INSERT INTO inscriptions_sport (user_id, sport_id, statut) VALUES (?,?,'confirme')
                           ON DUPLICATE KEY UPDATE statut = 'confirme'")->execute([$userId, $structId]);
            $pdo->prepare("INSERT INTO structure_membres (user_id, structure_type, structure_id, role_in_struct, statut)
                           VALUES (?, 'sport', ?, 'adherent', 'actif')
                           ON DUPLICATE KEY UPDATE statut = 'actif'")->execute([$userId, $structId]);
            $flashMsg = 'Accès libre activé !';
        } else {

            $stType = $isSport ? 'sport' : $type;
            $stId   = $structId;

            $chkExist = $pdo->prepare(
                "SELECT id FROM demandes_adhesion WHERE user_id=? AND structure_type=? AND structure_id=? AND statut='en_attente'"
            );
            $chkExist->execute([$userId, $stType, $stId]);
            if ($chkExist->fetchColumn()) {
                $flashMsg = 'Tu as déjà une demande en cours.';
            } else {
                $pdo->prepare(
                    "INSERT INTO demandes_adhesion (user_id, structure_type, structure_id, message, statut)
                     VALUES (?,?,?,?,'en_attente')"
                )->execute([$userId, $stType, $stId, $motiv]);
                $flashMsg = 'Demande envoyée - en attente de validation par l\'admin.';
            }
        }
    }

    if ($action === 'quitter') {
        if ($isSport) {
            $pdo->prepare("DELETE FROM inscriptions_sport WHERE user_id=? AND sport_id=?")->execute([$userId, $structId]);
            $pdo->prepare("UPDATE sports SET inscrits = GREATEST(0, inscrits-1) WHERE id=?")->execute([$structId]);
        }
        $pdo->prepare("DELETE FROM structure_membres WHERE user_id=? AND structure_type=? AND structure_id=?")->execute([$userId, $type, $structId]);

        syncGlobalRoleAfterStructChange($pdo, $userId);
        $flashMsg = 'Tu as quitté cette structure.';
        $isMembre = false;
    }

    if ($action === 'retirer_demande') {
        $stType = $isSport ? 'sport' : $type;
        $pdo->prepare(
            "DELETE FROM demandes_adhesion WHERE user_id=? AND structure_type=? AND structure_id=? AND statut='en_attente'"
        )->execute([$userId, $stType, $structId]);
        $flashMsg = 'Ta demande a été retirée.';
    }

    if ($isSport) {
        $rf = $pdo->prepare("SELECT * FROM sports WHERE id=?"); $rf->execute([$structId]);
        $struct = $rf->fetch() ?: $struct;
    }
}

$demandeEnAttente = false;
if ($userId && !$isMembre) {
    $chk = $pdo->prepare("SELECT statut FROM demandes_adhesion WHERE user_id = ? AND structure_type = ? AND structure_id = ? ORDER BY created_at DESC LIMIT 1");
    $chk->execute([$userId, $type, $structId]);
    $row = $chk->fetch();
    $demandeEnAttente = ($row && $row['statut'] === 'en_attente');
}

$inscriptionSport = null;
if ($userId && $isSport) {
    $si = $pdo->prepare("SELECT statut FROM inscriptions_sport WHERE user_id = ? AND sport_id = ?");
    $si->execute([$userId, $structId]);
    $inscriptionSport = $si->fetchColumn();
}

$actuStType = $type === 'sport' ? 'sport' : 'asso';
$membrePourActus = $isMembre || $isAdmin;
$actus           = $pdo->prepare(
    "SELECT * FROM actualites WHERE structure_type = ? AND structure_id = ? AND statut = 'publie'
       AND (IFNULL(visibilite,'public') = 'public' OR (IFNULL(visibilite,'public') = 'membres' AND ? = 1))
     ORDER BY created_at DESC LIMIT 5"
);
$actus->execute([$actuStType, $structId, $membrePourActus ? 1 : 0]);
$actus = $actus->fetchAll();

$evtStType = $type === 'sport' ? 'sport' : 'asso';
$membrePourEvts = ($isMembre || $isAdmin) ? 1 : 0;
if (corpo_evt_has_visibilite_column($pdo)) {
    $events = $pdo->prepare(
        "SELECT * FROM evenements WHERE structure_type = ? AND structure_id = ? AND statut = 'publie'
           AND (IFNULL(visibilite,'public') = 'public' OR (IFNULL(visibilite,'public') = 'membres' AND ? = 1))
         ORDER BY date ASC"
    );
    $events->execute([$evtStType, $structId, $membrePourEvts]);
    $events = $events->fetchAll();
} else {
    $events = $pdo->prepare(
        "SELECT * FROM evenements WHERE structure_type = ? AND structure_id = ? AND statut = 'publie' ORDER BY date ASC"
    );
    $events->execute([$evtStType, $structId]);
    $events = $events->fetchAll();
}

$partenaires = $pdo->prepare("SELECT * FROM partenaires WHERE structure_type = ? AND structure_id = ? AND statut = 'publie' ORDER BY nom");
$partenaires->execute([$type === 'sport' ? 'sport' : 'asso', $structId]);
$partenaires = $partenaires->fetchAll();

$membres = $pdo->prepare(
    "SELECT u.username, u.nom, u.prenom, u.ecole, sm.role_in_struct
       FROM structure_membres sm
       JOIN users u ON u.id = sm.user_id
      WHERE sm.structure_type = ?
        AND sm.structure_id   = ?
        AND sm.statut         = 'actif'
        AND sm.role_in_struct IN ('admin', 'membre')
      ORDER BY FIELD(sm.role_in_struct, 'admin', 'membre'), u.username ASC"
);
$membres->execute([$type, $structId]);
$membres = $membres->fetchAll();

$referents = $entrainements = $resultats = [];
if ($isSport) {
    $referents = $pdo->prepare("SELECT * FROM sport_referents WHERE sport_id = ?")->execute([$structId]) ? [] : [];
    $r = $pdo->prepare("SELECT * FROM sport_referents WHERE sport_id = ?");
    $r->execute([$structId]); $referents = $r->fetchAll();
    $r2 = $pdo->prepare("SELECT * FROM sport_entrainements WHERE sport_id = ? ORDER BY id");
    $r2->execute([$structId]); $entrainements = $r2->fetchAll();
    $r3 = $pdo->prepare("SELECT * FROM sport_resultats WHERE sport_id = ? ORDER BY date DESC LIMIT 5");
    $r3->execute([$structId]); $resultats = $r3->fetchAll();
}

$sousStructures = [];
if ($type === 'bde') {
    $ss = $pdo->prepare("SELECT * FROM associations WHERE parent_bde_id = ? ORDER BY nom");
    $ss->execute([$structId]); $sousStructures = $ss->fetchAll();
} elseif ($type === 'bds') {
    $ss = $pdo->prepare("SELECT * FROM sports WHERE parent_bds_id = ? ORDER BY nom");
    $ss->execute([$structId]); $sousStructures = $ss->fetchAll();
}

$title = htmlspecialchars($structNom) . ' - Corpo Omnes Lyon';
$page  = $isSport ? 'sport' : 'associations';
require_once __DIR__ . '/includes/header.php';
?>

<style>
  .struct-accent     { color: <?= htmlspecialchars($color) ?>; }
  .struct-badge      { background: <?= htmlspecialchars($color) ?>22; color: <?= htmlspecialchars($color) ?>; border: 1px solid <?= htmlspecialchars($color) ?>44; }
  .struct-btn-main   { background: <?= htmlspecialchars($color) ?>; color: #fff; }
  .struct-btn-main:hover { filter: brightness(1.12); }
  .struct-section h2::before { background: <?= htmlspecialchars($color) ?>; }
  :root { --struct-accent: <?= htmlspecialchars($color) ?>; }
  .page-hero { border-bottom-color: <?= htmlspecialchars($color) ?>44; }
</style>

<main>
    <section class="page-hero">
    <div class="container">
      <nav class="breadcrumb">
        <a href="index.php">Accueil</a><span>›</span>
        <a href="<?= $isSport ? 'sport.php' : 'associations.php' ?>"><?= $isSport ? 'Sports' : 'Associations' ?></a>
        <span>›</span><span><?= htmlspecialchars($structNom) ?></span>
      </nav>

      <?php if ($assoInactiveBanner): ?>
      <div class="flash flash--warn asso-inactive-banner" style="margin-bottom:var(--s4)">
        <?= htmlspecialchars(corpo_t('asso.inactive_banner')) ?>
        <?php if ($assoInactivePeriod !== ''): ?>
          <span style="display:block;margin-top:.35rem;font-size:.85rem;opacity:.9"><?= htmlspecialchars($assoInactivePeriod) ?></span>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <div style="display:flex;align-items:center;gap:1.5rem;margin-bottom:1rem;flex-wrap:wrap">
        <?php if (!empty($struct['logo'])): ?>
          <img src="<?= htmlspecialchars($struct['logo']) ?>" alt="" style="width:60px;height:60px;border-radius:12px;object-fit:contain;background:rgba(255,255,255,.08);padding:4px">
        <?php else: ?>
          <div style="width:60px;height:60px;border-radius:12px;background:<?= htmlspecialchars($color) ?>;display:flex;align-items:center;justify-content:center;font-size:1.3rem;font-weight:800;color:#fff;flex-shrink:0;letter-spacing:-.02em">
            <?= mb_strtoupper(mb_substr($structNom, 0, 2)) ?>
          </div>
        <?php endif; ?>
        <div>
          <div style="display:flex;flex-wrap:wrap;gap:.4rem;margin-bottom:.5rem">
            <span class="struct-badge" style="font-size:.68rem;padding:.15rem .6rem;border-radius:999px">
              <?= htmlspecialchars($struct['type'] ?? ($isSport ? 'Sport' : 'Association')) ?>
            </span>
            <?php if (!empty($struct['campus'])): ?>
              <span class="struct-badge" style="font-size:.68rem;padding:.15rem .6rem;border-radius:999px"><?= htmlspecialchars($struct['campus']) ?></span>
            <?php endif; ?>
            <?php if (!empty($struct['ecole'])): ?>
              <span class="struct-badge" style="font-size:.68rem;padding:.15rem .6rem;border-radius:999px"><?= htmlspecialchars($struct['ecole']) ?></span>
            <?php endif; ?>
          </div>
          <h1 style="margin:0;line-height:1.1"><?= htmlspecialchars($structNom) ?></h1>
        </div>
      </div>

      <p class="page-hero__sub"><?= htmlspecialchars(mb_substr($struct['description'] ?? '', 0, 200)) ?></p>

            <div style="display:flex;flex-wrap:wrap;gap:2rem;margin:1.2rem 0">
        <?php if ($isSport): ?>
          <?php if ((int)($struct['places'] ?? 0) > 0): ?>
            <div><strong style="font-size:1.4rem"><?= max(0, (int)$struct['places'] - (int)($struct['inscrits'] ?? 0)) ?></strong><br><span style="font-size:.72rem;color:var(--text-muted);text-transform:uppercase">places dispo</span></div>
          <?php endif; ?>
        <?php endif; ?>
        <div><strong style="font-size:1.4rem"><?= count($events) ?></strong><br><span style="font-size:.72rem;color:var(--text-muted);text-transform:uppercase">événements</span></div>
        <div><strong style="font-size:1.4rem"><?= count($actus) ?></strong><br><span style="font-size:.72rem;color:var(--text-muted);text-transform:uppercase">actus</span></div>
        <?php if (!empty($partenaires)): ?>
          <div><strong style="font-size:1.4rem"><?= count($partenaires) ?></strong><br><span style="font-size:.72rem;color:var(--text-muted);text-transform:uppercase">partenaires</span></div>
        <?php endif; ?>
      </div>

            <?php if ($flashMsg): ?>
        <div style="padding:.6rem 1.2rem;background:rgba(93,2,130,.15);border:1px solid rgba(93,2,130,.4);border-radius:8px;font-size:.85rem;color:#c084fc;margin-bottom:1rem"><?= htmlspecialchars($flashMsg) ?></div>
      <?php endif; ?>

            <?php
        $isLibre = $isSport && ($struct['categorie'] ?? '') === 'individuel';
        $places  = (int)($struct['places'] ?? 0);
        $inscrits= (int)($struct['inscrits'] ?? 0);
        $complet = $places > 0 && $inscrits >= $places;
      ?>
      <div style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-start">
        <?php if (!$userId): ?>
          <a href="admin/login.php" class="btn btn--primary">Se connecter pour rejoindre</a>
        <?php elseif ($isMembre || $inscriptionSport === 'confirme'): ?>
          <form method="post" onsubmit="return confirm('Quitter cette structure ?')">
            <input type="hidden" name="action" value="quitter">
            <button class="btn" style="border-color:#e74c3c;color:#e74c3c;background:transparent">Quitter</button>
          </form>
          <span class="tag" style="background:rgba(39,174,96,.15);color:#2ecc71;border-color:rgba(39,174,96,.3)"><?= $isSport ? 'Inscrit' : 'Membre' ?></span>
        <?php elseif ($demandeEnAttente || $inscriptionSport === 'liste_attente'): ?>
          <div style="display:flex;flex-direction:column;gap:.4rem;align-items:flex-start">
            <span class="tag" style="background:rgba(230,126,34,.15);color:#e67e22;border-color:rgba(230,126,34,.3)">Demande en cours de validation</span>
            <?php if ($demandeEnAttente): ?>
              <form method="post" onsubmit="return confirm('Retirer ta demande d\'adhésion ?')">
                <input type="hidden" name="action" value="retirer_demande">
                <button class="btn btn--sm" style="background:transparent;border-color:rgba(231,76,60,.5);color:#e74c3c">Retirer ma demande</button>
              </form>
            <?php endif; ?>
          </div>
        <?php elseif ($isLibre): ?>
          <form method="post">
            <input type="hidden" name="action" value="rejoindre">
            <button class="btn btn--primary">Accéder au sport</button>
          </form>
        <?php elseif (!$isSport && !$peutDemander): ?>
          <div style="display:flex;flex-direction:column;gap:.35rem;max-width:420px">
            <span class="tag" style="background:rgba(231,76,60,.12);color:#ff8a8a;border-color:rgba(231,76,60,.3)">Adhésion réservée</span>
            <small style="color:var(--text-muted)">
              Cette structure n'accepte les demandes que des écoles :
              <strong style="color:#fff"><?= htmlspecialchars(implode(', ', $ecolesEligibles)) ?></strong>.
              <?php if (!$userEcole): ?><br>Renseigne ton école dans ton profil pour vérifier ton éligibilité.<?php endif; ?>
            </small>
          </div>
        <?php else: ?>
          <form method="post" id="join-form" style="display:flex;flex-direction:column;gap:.5rem;max-width:360px">
            <input type="hidden" name="action" value="rejoindre">
            <textarea name="message" rows="2" class="struct-join-msg"
              placeholder="<?= $isSport ? 'Niveau, motivation, expérience…' : 'Message de motivation (optionnel)' ?>"></textarea>
            <button class="btn btn--primary" style="align-self:flex-start">
              <?= $complet ? 'Liste d\'attente' : 'Demander à rejoindre' ?>
            </button>
            <?php if ($isSport && $places > 0): ?>
              <small style="color:var(--text-muted)"><?= $complet ? 'Complet - liste d\'attente' : "$inscrits / $places places" ?></small>
            <?php endif; ?>
            <?php if (!$isSport && !empty($ecolesEligibles)): ?>
              <small style="color:var(--text-muted);font-size:.72rem">
                ✓ Ouvert aux écoles : <?= htmlspecialchars(implode(', ', $ecolesEligibles)) ?>
              </small>
            <?php endif; ?>
          </form>
        <?php endif; ?>
        <?php if (!empty($struct['instagram'])): ?>
          <a href="https://instagram.com/<?= htmlspecialchars($struct['instagram']) ?>" target="_blank" class="btn">📸 Instagram</a>
        <?php endif; ?>
        <?php if (!empty($struct['contact'])): ?>
          <a href="mailto:<?= htmlspecialchars($struct['contact']) ?>" class="btn">✉️ Contact</a>
        <?php endif; ?>
        <?php if (!empty($struct['lien_acces'])): ?>
          <a href="<?= htmlspecialchars($struct['lien_acces']) ?>" target="_blank" class="btn btn--primary">Rejoindre le groupe</a>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <div class="struct-body container">

        <?php if (!empty($sousStructures)): ?>
    <section class="struct-section">
      <h2><?= $type === 'bde' ? 'Associations rattachées' : 'Sports rattachés' ?></h2>
      <div class="struct-sub-grid">
        <?php foreach ($sousStructures as $ss): ?>
          <?php $ssColor = $ss['couleur'] ?? $ss['color'] ?? '#5D0282'; ?>
          <a href="<?= $type === 'bde' ? 'structure.php?slug=' . urlencode($ss['slug']) : 'structure.php?sport=' . urlencode($ss['slug']) ?>"
             class="struct-sub-card" style="border-top:3px solid <?= htmlspecialchars($ssColor) ?>">
            <strong><?= htmlspecialchars($ss['nom']) ?></strong>
            <?php if ($type === 'bds'): ?>
              <span><?= (int)($ss['inscrits'] ?? 0) ?> inscrits</span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

        <?php if ($isSport && !empty($entrainements)): ?>
    <section class="struct-section">
      <h2>⏱ Entraînements</h2>
      <div class="struct-training-grid">
        <?php foreach ($entrainements as $e): ?>
          <div class="struct-training-card">
            <strong><?= htmlspecialchars($e['jour']) ?></strong>
            <span><?= htmlspecialchars($e['heure']) ?></span>
            <span class="struct-training-lieu"><?= htmlspecialchars($e['lieu']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

        <?php if ($isSport && !empty($resultats)): ?>
    <section class="struct-section">
      <h2>Derniers résultats</h2>
      <div class="struct-results-list">
        <?php foreach ($resultats as $res): ?>
          <?php $win = $res['victoire']; ?>
          <div class="struct-result <?= $win === null ? 'draw' : ($win ? 'win' : 'loss') ?>">
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

        <section class="struct-section">
      <h2>Événements</h2>
      <?php if (empty($events)): ?>
        <p class="struct-empty">Aucun événement à venir pour l'instant.</p>
      <?php else: ?>
        <div class="struct-events-grid">
          <?php foreach ($events as $ev): ?>
            <?php $past = strtotime($ev['date']) < time(); ?>
            <div class="struct-event-card <?= $past ? 'past' : '' ?>">
              <div class="struct-event-icon"><?= $ev['icon'] ? evt_render_icon($ev['icon']) : '' ?></div>
              <div class="struct-event-body">
                <strong><?= htmlspecialchars($ev['titre']) ?></strong>
                <?php if (evt_normalize_visibilite($ev['visibilite'] ?? 'public') === 'membres'): ?>
                  <span class="tag" style="font-size:.62rem;margin-left:4px">🔒 Membres</span>
                <?php endif; ?>
                <?php if ((int)($ev['inscription_membres'] ?? 0) === 1): ?>
                  <span class="tag" style="font-size:.62rem;margin-left:4px">🎫 Inscr. membres</span>
                <?php endif; ?>
                <span><?= date('d/m/Y', strtotime($ev['date'])) ?><?= $ev['heure'] ? ' · ' . htmlspecialchars($ev['heure']) : '' ?></span>
                <?php if ($ev['lieu']): ?>
                  <span class="struct-event-lieu"><?= htmlspecialchars($ev['lieu']) ?></span>
                <?php endif; ?>
                <?php if (!$past):
                  $evMode = evt_normalize_mode($ev['mode_inscription'] ?? 'aucune');
                ?>
                  <?php if ($evMode === 'externe' && $ev['lien_billetterie']): ?>
                    <a href="<?= htmlspecialchars($ev['lien_billetterie']) ?>" target="_blank" class="struct-btn-main btn-sm">Billetterie</a>
                  <?php elseif (in_array($evMode, ['email','connexion','billetterie_email','billetterie_connexion'], true)): ?>
                    <a href="evenement.php?id=<?= $ev['id'] ?>" class="struct-btn-main btn-sm">
                      <?= evt_mode_is_paid($evMode) ? 'Acheter' : "S'inscrire" ?>
                    </a>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

        <section class="struct-section">
      <h2>Actualités</h2>
      <?php if (empty($actus)): ?>
        <p class="struct-empty">Pas encore d'actualités publiées.</p>
      <?php else: ?>
        <div class="struct-actus-grid">
          <?php foreach ($actus as $a): ?>
            <article class="struct-actu-card">
              <h3><?= htmlspecialchars($a['titre']) ?></h3>
              <p><?= htmlspecialchars(mb_substr(strip_tags($a['contenu']), 0, 200)) ?>…</p>
              <time><?= date('d/m/Y', strtotime($a['created_at'])) ?></time>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

        <?php if (!empty($partenaires)): ?>
    <section class="struct-section">
      <h2>Partenaires</h2>
      <div class="struct-partners-grid">
        <?php foreach ($partenaires as $p): ?>
          <div class="struct-partner-card">
            <strong><?= htmlspecialchars($p['nom']) ?></strong>
            <?php if ($p['offre']): ?>
              <span class="struct-partner-offre">🎁 <?= htmlspecialchars($p['offre']) ?></span>
            <?php endif; ?>
            <?php if ($p['code']): ?>
              <code><?= htmlspecialchars($p['code']) ?></code>
            <?php endif; ?>
            <?php if ($p['lien'] && $p['lien'] !== '#'): ?>
              <a href="<?= htmlspecialchars($p['lien']) ?>" target="_blank" class="btn-sm">Voir →</a>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

        <?php if ($isSport && !empty($referents)): ?>
    <section class="struct-section">
      <h2>Référents</h2>
      <div class="struct-members-grid">
        <?php foreach ($referents as $ref): ?>
          <div class="struct-member-card">
            <div class="struct-member-avatar" style="background:<?= htmlspecialchars($color) ?>"><?= htmlspecialchars($ref['initiales'] ?? '??') ?></div>
            <strong><?= htmlspecialchars($ref['nom']) ?></strong>
            <span><?= htmlspecialchars($ref['role']) ?></span>
            <?php if ($ref['email']): ?>
              <a href="mailto:<?= htmlspecialchars($ref['email']) ?>">✉️</a>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php elseif (!empty($membres)):
      // Sépare le bureau (admins) des membres "actifs" pour deux blocs distincts
      $bureau = array_values(array_filter($membres, fn($m) => $m['role_in_struct'] === 'admin'));
      $eqipe  = array_values(array_filter($membres, fn($m) => $m['role_in_struct'] === 'membre'));
    ?>
    <?php if (!empty($bureau)): ?>
    <section class="struct-section">
      <h2>Le bureau</h2>
      <div class="struct-members-grid">
        <?php foreach ($bureau as $mb):
          $displayName = trim(($mb['prenom'] ?? '') . ' ' . ($mb['nom'] ?? '')) ?: $mb['username'];
        ?>
          <div class="struct-member-card">
            <div class="struct-member-avatar" style="background:<?= htmlspecialchars($color) ?>"><?= mb_strtoupper(mb_substr($displayName, 0, 2)) ?></div>
            <strong><?= htmlspecialchars($displayName) ?></strong>
            <span class="struct-badge" style="font-size:.7rem">Bureau</span>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>
    <?php if (!empty($eqipe)): ?>
    <section class="struct-section">
      <h2>L'équipe</h2>
      <div class="struct-members-grid">
        <?php foreach ($eqipe as $mb):
          $displayName = trim(($mb['prenom'] ?? '') . ' ' . ($mb['nom'] ?? '')) ?: $mb['username'];
        ?>
          <div class="struct-member-card">
            <div class="struct-member-avatar" style="background:<?= htmlspecialchars($color) ?>"><?= mb_strtoupper(mb_substr($displayName, 0, 2)) ?></div>
            <strong><?= htmlspecialchars($displayName) ?></strong>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>
    <?php endif; ?>

  </div></main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
