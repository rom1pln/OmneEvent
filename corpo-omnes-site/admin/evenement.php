<?php
$adminTitle = 'Événement';
$adminPage  = 'evenements';
require_once '../includes/db.php';
require_once '../includes/billetterie.php';
require_once '../includes/paiements.php';
require_once 'includes/admin-header.php';
requireBureau();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: evenements.php'); exit; }

$st = $pdo->prepare("SELECT * FROM evenements WHERE id=?");
$st->execute([$id]);
$ev = $st->fetch();
if (!$ev) {
    echo '<h1 class="admin-page-title">Événement introuvable</h1><p><a href="evenements.php" class="btn">← Retour</a></p>';
    require_once 'includes/admin-footer.php';
    exit;
}

// ── Contrôle d'accès : admin Corpo ou périmètre événements (bureau + resp. événements)
$evStType = (string)($ev['structure_type'] ?? 'asso');
$evStId   = (int)($ev['structure_id'] ?? 0);
$canManage = canManageEvenement($pdo, $ev);
if (!$canManage) {
    echo '<div class="flash flash--err">Tu n\'as pas accès à la gestion de cet événement.</div>';
    require_once 'includes/admin-footer.php';
    exit;
}

$flash = '';
$mode  = evt_normalize_mode($ev['mode_inscription'] ?? 'aucune');
$prix  = (float)($ev['prix'] ?? 0);
$places = (int)($ev['places'] ?? 0);

/* Capacités selon le mode */
$emitsTicket = evt_mode_emits_ticket($mode);    // billet + QR généré
$isPaid      = evt_mode_is_paid($mode);          // paiement SumUp
$hasDemandes = false;                            // legacy "demandes" - désormais désactivé

// ── Actions admin ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'cancel_billet' && !empty($_POST['inscription_id'])) {
        billet_cancel($pdo, (int)$_POST['inscription_id']);
        $flash = '<div class="flash flash--ok">Billet annulé. La liste d\'attente a été réajustée.</div>';
    }

    if ($act === 'promote_waitlist' && !empty($_POST['inscription_id'])) {
        $insId = (int)$_POST['inscription_id'];
        if (billet_promote_waitlist($pdo, $insId)) {
            $flash = '<div class="flash flash--ok">Participant promu — notification envoyée si l\'email est valide.</div>';
        } else {
            $flash = '<div class="flash flash--err">Promotion impossible (inscription introuvable ou déjà confirmée).</div>';
        }
    }

    if ($act === 'reset_scan' && !empty($_POST['inscription_id'])) {
        $pdo->prepare("UPDATE inscriptions_evenement SET qr_scanned_at=NULL, qr_scanned_by=NULL WHERE id=? AND evenement_id=?")
            ->execute([(int)$_POST['inscription_id'], $id]);
        $flash = '<div class="flash flash--ok">Scan réinitialisé.</div>';
    }

    if ($act === 'mark_scanned' && !empty($_POST['inscription_id'])) {
        $insId = (int)$_POST['inscription_id'];
        // Vérifie que le billet appartient bien à cet événement
        $check = $pdo->prepare("SELECT id FROM inscriptions_evenement WHERE id=? AND evenement_id=?");
        $check->execute([$insId, $id]);
        if (!$check->fetchColumn()) {
            $flash = '<div class="flash flash--err">Billet introuvable.</div>';
        } else {
            $res = billet_scan_mark($pdo, $insId, currentUserId() ?: 0);
            if ($res['ok'] ?? false) {
                $flash = '<div class="flash flash--ok">✓ Billet validé manuellement.</div>';
            } elseif ($res['already'] ?? false) {
                $flash = '<div class="flash flash--warn">⚠ Ce billet a déjà été validé.</div>';
            } else {
                $flash = '<div class="flash flash--err">' . htmlspecialchars($res['msg'] ?? 'Validation impossible') . '</div>';
            }
        }
    }

    if ($act === 'mark_traite' && !empty($_POST['demande_id'])) {
        $pdo->prepare("UPDATE demandes_renseignement_evenement SET statut='traite' WHERE id=? AND evenement_id=?")
            ->execute([(int)$_POST['demande_id'], $id]);
        $flash = '<div class="flash flash--ok">Demande marquée traitée.</div>';
    }

    // tarifs de l'événement
    // Détecte si la colonne frais_a_charge_client est présente (migration appliquée).
    $hasFraisClient = false;
    try {
        $c = $pdo->query("SHOW COLUMNS FROM evenement_tarifs LIKE 'frais_a_charge_client'");
        $hasFraisClient = $c && $c->fetchColumn() !== false;
    } catch (Throwable $e) {}

    if ($act === 'add_tarif') {
        $ecolesElig = $_POST['tarif_ecoles_eligibles'] ?? [];
        if (in_array('Tous', $ecolesElig, true) || empty($ecolesElig)) $ecolesElig = null;
        try {
            if ($hasFraisClient) {
                $pdo->prepare(
                    "INSERT INTO evenement_tarifs (evenement_id, nom, description, prix, places_max, ecoles_eligibles, reserve_membres, frais_a_charge_client, position, statut)
                     VALUES (?,?,?,?,?,?,?,?,?,'actif')"
                )->execute([
                    $id,
                    trim($_POST['tarif_nom'] ?? '') ?: 'Tarif',
                    trim($_POST['tarif_description'] ?? '') ?: null,
                    (float)($_POST['tarif_prix'] ?? 0),
                    (int)($_POST['tarif_places_max'] ?? 0) ?: null,
                    $ecolesElig ? json_encode($ecolesElig, JSON_UNESCAPED_UNICODE) : null,
                    isset($_POST['tarif_reserve_membres']) ? 1 : 0,
                    isset($_POST['tarif_frais_a_charge_client']) ? 1 : 0,
                    (int)($_POST['tarif_position'] ?? 0),
                ]);
            } else {
                $pdo->prepare(
                    "INSERT INTO evenement_tarifs (evenement_id, nom, description, prix, places_max, ecoles_eligibles, reserve_membres, position, statut)
                     VALUES (?,?,?,?,?,?,?,?,'actif')"
                )->execute([
                    $id,
                    trim($_POST['tarif_nom'] ?? '') ?: 'Tarif',
                    trim($_POST['tarif_description'] ?? '') ?: null,
                    (float)($_POST['tarif_prix'] ?? 0),
                    (int)($_POST['tarif_places_max'] ?? 0) ?: null,
                    $ecolesElig ? json_encode($ecolesElig, JSON_UNESCAPED_UNICODE) : null,
                    isset($_POST['tarif_reserve_membres']) ? 1 : 0,
                    (int)($_POST['tarif_position'] ?? 0),
                ]);
            }
            $flash = '<div class="flash flash--ok">Tarif ajouté.</div>';
        } catch (Throwable $e) {
            $flash = '<div class="flash flash--err">Impossible d\'ajouter le tarif : ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
    if ($act === 'update_tarif' && !empty($_POST['tarif_id'])) {
        $ecolesElig = $_POST['tarif_ecoles_eligibles'] ?? [];
        if (in_array('Tous', $ecolesElig, true) || empty($ecolesElig)) $ecolesElig = null;
        try {
            if ($hasFraisClient) {
                $pdo->prepare(
                    "UPDATE evenement_tarifs
                        SET nom=?, description=?, prix=?, places_max=?, ecoles_eligibles=?, reserve_membres=?, frais_a_charge_client=?, position=?, statut=?
                      WHERE id=? AND evenement_id=?"
                )->execute([
                    trim($_POST['tarif_nom'] ?? '') ?: 'Tarif',
                    trim($_POST['tarif_description'] ?? '') ?: null,
                    (float)($_POST['tarif_prix'] ?? 0),
                    (int)($_POST['tarif_places_max'] ?? 0) ?: null,
                    $ecolesElig ? json_encode($ecolesElig, JSON_UNESCAPED_UNICODE) : null,
                    isset($_POST['tarif_reserve_membres']) ? 1 : 0,
                    isset($_POST['tarif_frais_a_charge_client']) ? 1 : 0,
                    (int)($_POST['tarif_position'] ?? 0),
                    ($_POST['tarif_statut'] ?? 'actif') === 'inactif' ? 'inactif' : 'actif',
                    (int)$_POST['tarif_id'], $id,
                ]);
            } else {
                $pdo->prepare(
                    "UPDATE evenement_tarifs
                        SET nom=?, description=?, prix=?, places_max=?, ecoles_eligibles=?, reserve_membres=?, position=?, statut=?
                      WHERE id=? AND evenement_id=?"
                )->execute([
                    trim($_POST['tarif_nom'] ?? '') ?: 'Tarif',
                    trim($_POST['tarif_description'] ?? '') ?: null,
                    (float)($_POST['tarif_prix'] ?? 0),
                    (int)($_POST['tarif_places_max'] ?? 0) ?: null,
                    $ecolesElig ? json_encode($ecolesElig, JSON_UNESCAPED_UNICODE) : null,
                    isset($_POST['tarif_reserve_membres']) ? 1 : 0,
                    (int)($_POST['tarif_position'] ?? 0),
                    ($_POST['tarif_statut'] ?? 'actif') === 'inactif' ? 'inactif' : 'actif',
                    (int)$_POST['tarif_id'], $id,
                ]);
            }
            $flash = '<div class="flash flash--ok">Tarif mis à jour.</div>';
        } catch (Throwable $e) {
            $flash = '<div class="flash flash--err">' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
    if ($act === 'delete_tarif' && !empty($_POST['tarif_id'])) {
        $pdo->prepare("DELETE FROM evenement_tarifs WHERE id=? AND evenement_id=?")
            ->execute([(int)$_POST['tarif_id'], $id]);
        $flash = '<div class="flash flash--ok">Tarif supprimé.</div>';
    }

    // gestion des codes promo
    if ($act === 'add_promo') {
        try {
            $pdo->prepare(
                "INSERT INTO codes_promo (code, evenement_id, tarif_id, type, valeur, utilisations_max, expire_le, statut)
                 VALUES (?,?,?,?,?,?,?,'actif')"
            )->execute([
                strtoupper(trim($_POST['promo_code'] ?? '')) ?: 'CODE',
                $id,
                (int)($_POST['promo_tarif_id'] ?? 0) ?: null,
                ($_POST['promo_type'] ?? 'pourcentage') === 'fixe' ? 'fixe' : 'pourcentage',
                (float)($_POST['promo_valeur'] ?? 0),
                (int)($_POST['promo_utilisations_max'] ?? 0) ?: null,
                trim($_POST['promo_expire_le'] ?? '') ?: null,
            ]);
            $flash = '<div class="flash flash--ok">Code promo ajouté.</div>';
        } catch (Throwable $e) {
            $flash = '<div class="flash flash--err">' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
    if ($act === 'toggle_promo' && !empty($_POST['promo_id'])) {
        $pdo->prepare("UPDATE codes_promo SET statut = IF(statut='actif','inactif','actif') WHERE id=? AND evenement_id=?")
            ->execute([(int)$_POST['promo_id'], $id]);
        $flash = '<div class="flash flash--ok">Statut du code mis à jour.</div>';
    }
    if ($act === 'delete_promo' && !empty($_POST['promo_id'])) {
        $pdo->prepare("DELETE FROM codes_promo WHERE id=? AND evenement_id=?")
            ->execute([(int)$_POST['promo_id'], $id]);
        $flash = '<div class="flash flash--ok">Code promo supprimé.</div>';
    }
}

// ── Données ──────────────────────────────────────────────────
$inscrits = $pdo->prepare(
    "SELECT i.*, u.username, u.email AS u_email, u.ecole AS u_ecole, u.promotion AS u_promo
       FROM inscriptions_evenement i
       LEFT JOIN users u ON u.id = i.user_id
      WHERE i.evenement_id = ?
      ORDER BY
        FIELD(i.statut, 'confirme','en_attente','liste_attente','annule','refuse','rembourse'),
        i.waitlist_position ASC,
        i.id ASC"
);
$inscrits->execute([$id]);
$participants = $inscrits->fetchAll();

// Anciennes demandes "renseignement" - affichées s'il en reste (legacy)
$demandes = [];
try {
    $d = $pdo->prepare("SELECT * FROM demandes_renseignement_evenement WHERE evenement_id=? ORDER BY created_at DESC");
    $d->execute([$id]);
    $demandes = $d->fetchAll();
    $hasDemandes = !empty($demandes);
} catch (Throwable $e) { /* table peut ne pas exister */ }

$paiements = [];
if ($isPaid) {
    try {
        $t = $pdo->prepare("SELECT * FROM paiement_transactions WHERE evenement_id=? ORDER BY id DESC");
        $t->execute([$id]);
        $paiements = $t->fetchAll();
    } catch (Throwable $e) { /* table peut ne pas exister */ }
}

// chargement des tarifs & promos (billetterie seulement)
$tarifs = [];
$promos = [];
if ($isPaid) {
    try {
        $st = $pdo->prepare("SELECT * FROM evenement_tarifs WHERE evenement_id=? ORDER BY position ASC, id ASC");
        $st->execute([$id]);
        $tarifs = $st->fetchAll();
    } catch (Throwable $e) {}
    try {
        $st = $pdo->prepare("SELECT * FROM codes_promo WHERE evenement_id=? ORDER BY id DESC");
        $st->execute([$id]);
        $promos = $st->fetchAll();
    } catch (Throwable $e) {}
}
$ECOLES_ALL = ['ECE','ESCE','HEIP','INSEEC Bachelor','INSEEC BBA','INSEEC BTS','INSEEC GE','INSEEC MSc','Sup de Pub'];

// Stats
$nbConfirme = count(array_filter($participants, fn($p) => $p['statut'] === 'confirme'));
$nbAttente  = count(array_filter($participants, fn($p) => $p['statut'] === 'liste_attente'));
$nbScanned  = count(array_filter($participants, fn($p) => !empty($p['qr_scanned_at']) && $p['statut'] === 'confirme'));
$totalPaye  = array_sum(array_map(fn($p) => $p['paiement_statut'] === 'paye' ? (float)$p['prix_paye'] : 0, $participants));
$dispo = $places > 0 ? max(0, $places - $nbConfirme) : null;

$modeLabel = EVT_MODES_LABELS[$mode] ?? $mode;
?>

<div class="adm-evt-header">
  <a href="evenements.php" class="adm-evt-back">← Retour à la liste</a>
  <div class="adm-evt-title-row">
    <h1 class="admin-page-title" style="margin:0">
      <?= evt_icon_html($ev['icon'] ?? null, 'evt-emoji evt-emoji--sm') ?> <?= htmlspecialchars($ev['titre']) ?>
    </h1>
    <span class="badge badge--<?= $ev['statut']==='publie'?'ok':($ev['statut']==='en_attente'?'pending':'ko') ?>">
      <?= htmlspecialchars($ev['statut']) ?>
    </span>
  </div>
  <p class="adm-evt-subtitle">
    <strong><?= htmlspecialchars($ev['organisateur']) ?></strong>
    · <?= date('l j F Y', strtotime($ev['date'])) ?>
    <?php if (!empty($ev['heure'])): ?>· <?= htmlspecialchars($ev['heure']) ?><?php endif; ?>
    <?php if (!empty($ev['lieu'])): ?>· 📍 <?= htmlspecialchars($ev['lieu']) ?><?php endif; ?>
  </p>
</div>

<?= $flash ?>

<!-- ─── Statistiques ──────────────────────────────────── -->
<div class="adm-evt-stats">
  <div class="adm-evt-stat">
    <span class="adm-evt-stat__num"><?= $nbConfirme ?></span>
    <span class="adm-evt-stat__label">Confirmé<?= $nbConfirme > 1 ? 's' : '' ?></span>
  </div>
  <?php if ($places > 0): ?>
  <div class="adm-evt-stat">
    <span class="adm-evt-stat__num"><?= $dispo ?> / <?= $places ?></span>
    <span class="adm-evt-stat__label">Places dispo</span>
  </div>
  <?php endif; ?>
  <div class="adm-evt-stat">
    <span class="adm-evt-stat__num"><?= $nbAttente ?></span>
    <span class="adm-evt-stat__label">Liste d'attente</span>
  </div>
  <div class="adm-evt-stat">
    <span class="adm-evt-stat__num"><?= $nbScanned ?></span>
    <span class="adm-evt-stat__label">Billets scannés</span>
  </div>
  <?php if ($isPaid): ?>
  <div class="adm-evt-stat">
    <span class="adm-evt-stat__num"><?= number_format($totalPaye, 2, ',', ' ') ?> €</span>
    <span class="adm-evt-stat__label">Encaissé</span>
  </div>
  <?php endif; ?>
  <div class="adm-evt-stat">
    <span class="adm-evt-stat__num"><?= htmlspecialchars($modeLabel) ?></span>
    <span class="adm-evt-stat__label">Mode</span>
  </div>
</div>

<!-- onglets de navigation (participants / tarifs / promos / scanner) -->
<div class="adm-evt-tabs" role="tablist">
  <button type="button" class="adm-evt-tab is-active" data-tab="participants">Participants (<?= count($participants) ?>)</button>
  <?php if ($emitsTicket): ?>
    <button type="button" class="adm-evt-tab" data-tab="scanner">📷 Scanner QR</button>
  <?php endif; ?>
  <?php if ($isPaid): ?>
    <button type="button" class="adm-evt-tab" data-tab="tarifs">🎟 Tarifs (<?= count($tarifs) ?>)</button>
    <button type="button" class="adm-evt-tab" data-tab="codes">🏷 Codes promo (<?= count($promos) ?>)</button>
  <?php endif; ?>
  <?php if ($hasDemandes): ?>
    <button type="button" class="adm-evt-tab" data-tab="demandes">Demandes <small>(legacy)</small> (<?= count($demandes) ?>)</button>
  <?php endif; ?>
  <?php if (!empty($paiements)): ?>
    <button type="button" class="adm-evt-tab" data-tab="paiements">Paiements (<?= count($paiements) ?>)</button>
  <?php endif; ?>
  <button type="button" class="adm-evt-tab" data-tab="export">Export</button>
</div>

<!-- ─── Panel : Participants ─────────────────────────── -->
<section class="adm-evt-panel is-active" data-panel="participants">
  <?php if (!empty($participants) && $emitsTicket):
    $nbConfirmeTotal = $nbConfirme; // participants à scanner
    $pct = $nbConfirmeTotal > 0 ? round($nbScanned * 100 / $nbConfirmeTotal) : 0;
  ?>
    <div class="admin-card adm-scan-progress">
      <div class="adm-scan-progress__head">
        <div>
          <strong>📊 Progression des scans</strong>
          <span class="adm-scan-progress__counts">
            <strong><?= $nbScanned ?></strong> / <?= $nbConfirmeTotal ?> billet<?= $nbConfirmeTotal > 1 ? 's' : '' ?> scanné<?= $nbScanned > 1 ? 's' : '' ?>
            <span style="color:var(--text-muted)">·</span>
            <strong><?= max(0, $nbConfirmeTotal - $nbScanned) ?></strong> restant<?= ($nbConfirmeTotal - $nbScanned) > 1 ? 's' : '' ?>
          </span>
        </div>
        <span class="adm-scan-progress__pct"><?= $pct ?> %</span>
      </div>
      <div class="adm-scan-progress__bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $pct ?>">
        <div class="adm-scan-progress__fill" style="width:<?= $pct ?>%"></div>
      </div>
    </div>
  <?php endif; ?>

  <div class="admin-card adm-part-toolbar" style="margin-bottom:var(--s4)">
    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:var(--s3);width:100%">
      <strong style="font-size:.9rem">Export participants</strong>
      <a href="../api/export-evenement.php?id=<?= $id ?>&format=xlsx" class="btn btn--ghost btn--sm">⤓ Excel (.xlsx)</a>
      <a href="../api/export-evenement.php?id=<?= $id ?>&format=csv" class="btn btn--ghost btn--sm">CSV</a>
      <?php if ($hasDemandes): ?>
        <a href="../api/export-evenement.php?id=<?= $id ?>&type=demandes&format=xlsx" class="btn btn--ghost btn--sm">Demandes (legacy)</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($participants)): ?>
    <div class="admin-card adm-part-toolbar">
      <label class="adm-part-search">
        <span class="adm-part-search__icon" aria-hidden="true">🔎</span>
        <input type="search" id="participants-search" placeholder="Rechercher : nom, prénom, email, école…" aria-label="Rechercher un participant">
      </label>
      <div class="adm-part-filters">
        <label><input type="checkbox" id="filter-confirme" checked> Confirmés</label>
        <label><input type="checkbox" id="filter-attente" checked> En attente</label>
        <label><input type="checkbox" id="filter-annule"> Annulés / refusés</label>
        <?php if ($emitsTicket): ?>
          <label><input type="checkbox" id="filter-not-scanned"> Non scannés</label>
        <?php endif; ?>
      </div>
      <span class="adm-part-count" id="participants-count" style="margin-left:auto;color:var(--text-muted);font-size:.82rem"></span>
    </div>
  <?php endif; ?>

  <div class="admin-card" style="padding:0;overflow:hidden">
    <?php if (empty($participants)): ?>
      <p style="padding:var(--s6);text-align:center;color:var(--text-muted)">Aucun participant pour l'instant.</p>
    <?php else: ?>
      <table class="admin-table" id="participants-table">
        <thead>
          <tr>
            <th>#</th><th>Participant</th><th>Statut</th><th>Scan</th>
            <?php if ($isPaid): ?><th>Paiement</th><?php endif; ?>
            <th>Inscrit le</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($participants as $i => $p):
          $nomComplet = trim(($p['prenom'] ?? '') . ' ' . ($p['nom'] ?? ''));
          if (!$nomComplet && !empty($p['username'])) $nomComplet = $p['username'];
          $email = $p['email'] ?: ($p['u_email'] ?? '');
          $isScanned = !empty($p['qr_scanned_at']);
          $hasToken  = !empty($p['qr_token']);
          $searchBlob = mb_strtolower(trim(
              ($p['prenom']??'') . ' ' . ($p['nom']??'') . ' ' .
              ($p['username']??'') . ' ' . $email . ' ' .
              ($p['u_ecole']??'') . ' ' . ($p['u_promo']??'')
          ));
        ?>
          <tr class="participant-row <?= $isScanned ? 'is-scanned' : '' ?> <?= $hasToken && !$isScanned && $p['statut']==='confirme' ? 'is-pending-scan' : '' ?>"
              data-search="<?= htmlspecialchars($searchBlob) ?>"
              data-statut="<?= htmlspecialchars($p['statut']) ?>"
              data-scanned="<?= $isScanned ? '1' : '0' ?>"
              data-has-token="<?= $hasToken ? '1' : '0' ?>">
            <td data-label="#"><?= $i + 1 ?></td>
            <td data-label="Participant">
              <strong><?= htmlspecialchars($nomComplet ?: '- Invité -') ?></strong>
              <?php if ($email): ?><br><small style="color:var(--text-muted)"><?= htmlspecialchars($email) ?></small><?php endif; ?>
              <?php if (!empty($p['u_ecole'])): ?><br><small style="color:var(--text-muted)"><?= htmlspecialchars($p['u_ecole']) ?><?= $p['u_promo'] ? ' · '.htmlspecialchars($p['u_promo']) : '' ?></small><?php endif; ?>
            </td>
            <td data-label="Statut">
              <?php
                $statutClass = ['confirme'=>'ok','liste_attente'=>'pending','en_attente'=>'pending','annule'=>'ko','refuse'=>'ko','rembourse'=>'ko'][$p['statut']] ?? 'pending';
              ?>
              <span class="badge badge--<?= $statutClass ?>"><?= htmlspecialchars($p['statut']) ?></span>
              <?php if ($p['statut'] === 'liste_attente' && $p['waitlist_position']): ?>
                <br><small style="color:var(--text-muted)">#<?= (int)$p['waitlist_position'] ?> de la file</small>
              <?php endif; ?>
            </td>
            <td data-label="Scan">
              <?php if (!empty($p['qr_scanned_at'])): ?>
                <span class="scan-pill scan-pill--ok">✓ Scanné</span>
                <small style="display:block;font-size:.7rem;margin-top:3px;color:var(--text-muted)">le <?= date('d/m', strtotime($p['qr_scanned_at'])) ?> à <?= date('H:i', strtotime($p['qr_scanned_at'])) ?></small>
              <?php elseif ($p['qr_token'] && $p['statut']==='confirme'): ?>
                <span class="scan-pill scan-pill--pending">⏳ À scanner</span>
              <?php elseif ($p['qr_token']): ?>
                <span class="scan-pill scan-pill--muted">-</span>
              <?php else: ?>
                <span class="scan-pill scan-pill--muted">N/A</span>
              <?php endif; ?>
            </td>
            <?php if ($isPaid): ?>
            <td data-label="Paiement">
              <?php if (($p['paiement_statut'] ?? '') === 'paye'): ?>
                <span class="badge badge--ok"><?= number_format($p['prix_paye'], 2, ',', ' ') ?> €</span>
              <?php elseif (($p['paiement_statut'] ?? '') === 'en_attente'): ?>
                <span class="badge badge--pending">En attente</span>
              <?php elseif (($p['paiement_statut'] ?? '') === 'rembourse'): ?>
                <span class="badge badge--ko">Remboursé</span>
              <?php else: ?>
                <span style="color:var(--text-muted)">-</span>
              <?php endif; ?>
            </td>
            <?php endif; ?>
            <td data-label="Inscrit le" style="font-size:.78rem">
              <?= date('d/m/Y', strtotime($p['created_at'])) ?>
              <br><small style="color:var(--text-muted)"><?= date('H:i', strtotime($p['created_at'])) ?></small>
            </td>
            <td data-label="Actions">
              <div class="actions">
                <?php if ($hasToken && !$isScanned && $p['statut'] === 'confirme'): ?>
                  <form method="post" style="display:inline" title="Marquer ce billet comme validé (équivaut à un scan)">
                    <input type="hidden" name="action" value="mark_scanned">
                    <input type="hidden" name="inscription_id" value="<?= $p['id'] ?>">
                    <button class="btn btn--sm btn--success">✓ Valider</button>
                  </form>
                <?php endif; ?>
                <?php if ($p['statut'] === 'liste_attente'): ?>
                  <form method="post" style="display:inline">
                    <input type="hidden" name="action" value="promote_waitlist">
                    <input type="hidden" name="inscription_id" value="<?= $p['id'] ?>">
                    <button class="btn btn--sm btn--success" title="Promouvoir en confirmé">↑ Promouvoir</button>
                  </form>
                <?php endif; ?>
                <?php if ($isScanned): ?>
                  <form method="post" style="display:inline" onsubmit="return confirm('Réinitialiser le scan ?')">
                    <input type="hidden" name="action" value="reset_scan">
                    <input type="hidden" name="inscription_id" value="<?= $p['id'] ?>">
                    <button class="btn btn--sm" style="background:var(--surface);border-color:var(--border)">↺ Réinit. scan</button>
                  </form>
                <?php endif; ?>
                <?php if (!in_array($p['statut'], ['annule','refuse'])): ?>
                  <form method="post" style="display:inline" onsubmit="return confirm('Annuler ce billet ?')">
                    <input type="hidden" name="action" value="cancel_billet">
                    <input type="hidden" name="inscription_id" value="<?= $p['id'] ?>">
                    <button class="btn btn--sm btn--danger">Annuler</button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</section>

<!-- ─── Panel : Scanner (refonte design) ─────────────── -->
<?php if ($emitsTicket): ?>
<section class="adm-evt-panel scan-panel" data-panel="scanner" hidden>

  <!-- En-tête du scanner avec stats live -->
  <header class="scan-hero">
    <div class="scan-hero__left">
      <h2 class="scan-hero__title">
        <span class="scan-hero__icon" aria-hidden="true">📷</span>
        Contrôle d'entrée
      </h2>
      <p class="scan-hero__sub">Scanne le QR code du billet, ou saisis le code à la main.</p>
    </div>
    <div class="scan-hero__stats" aria-label="Progression des scans">
      <div class="scan-hero__stat scan-hero__stat--ok">
        <span class="scan-hero__stat-num" id="scan-stat-ok">0</span>
        <span class="scan-hero__stat-lbl">Scannés</span>
      </div>
      <div class="scan-hero__stat scan-hero__stat--err">
        <span class="scan-hero__stat-num" id="scan-stat-err">0</span>
        <span class="scan-hero__stat-lbl">Refusés</span>
      </div>
      <div class="scan-hero__stat">
        <span class="scan-hero__stat-num" id="scan-stat-total">0</span>
        <span class="scan-hero__stat-lbl">Total session</span>
      </div>
    </div>
  </header>

  <!-- Caméra + viseur stylé -->
  <div class="scan-preview" id="scan-preview">
    <div class="scan-preview__box">
      <span class="scan-preview__icon" aria-hidden="true">📷</span>
      <p>Le scan s’ouvre en <strong>plein écran</strong> pour viser les QR codes du billet.</p>
    </div>
    <div class="scan-stage__status scan-preview__status" id="scan-status">⏳ Initialisation…</div>
  </div>

  <div class="scan-fullscreen" id="scan-fullscreen" hidden aria-hidden="true">
    <header class="scan-fullscreen__bar">
      <button type="button" class="scan-fullscreen__back" id="scan-fullscreen-back" aria-label="Quitter le scan">← Retour</button>
      <span class="scan-fullscreen__title">Contrôle d’entrée</span>
      <div class="scan-fullscreen__stats" aria-label="Session">
        <span class="scan-fullscreen__stat scan-fullscreen__stat--ok">✓ <strong id="scan-fs-stat-ok">0</strong></span>
        <span class="scan-fullscreen__stat scan-fullscreen__stat--err">✗ <strong id="scan-fs-stat-err">0</strong></span>
      </div>
    </header>
    <div class="scan-fullscreen__body">
      <div class="scan-stage__frame scan-fullscreen__frame" id="scan-stage-frame">
        <div class="scanner-video" id="scanner-video"></div>

      <!-- Overlay de feedback plein écran -->
      <div class="scan-overlay" id="scan-overlay" aria-live="polite">
        <div class="scan-overlay__icon" id="scan-overlay-icon"></div>
        <div class="scan-overlay__title" id="scan-overlay-title"></div>
        <div class="scan-overlay__sub" id="scan-overlay-sub"></div>
      </div>

    </div>

      <p class="scan-fullscreen__hint" id="scan-fs-hint">Vise le QR code du billet</p>
    </div>
  </div>

  <!-- Boutons principaux (sticky bottom sur mobile) -->
  <div class="scan-actions">
    <button type="button" class="scan-btn scan-btn--start scan-btn--launch" id="scan-start">
      <span class="scan-btn__icon" aria-hidden="true">▶</span>
      <span class="scan-btn__label">Lancer le scan</span>
    </button>
    <button type="button" class="scan-btn scan-btn--stop" id="scan-stop" hidden tabindex="-1" aria-hidden="true">Arrêter</button>
    <div class="scan-camera-pick">
      <label for="scan-camera-select">📹 Caméra</label>
      <select id="scan-camera-select">
        <option value="">Par défaut</option>
      </select>
    </div>
  </div>

  <!-- Validation manuelle -->
  <div class="scan-manual-card">
    <div class="scan-manual-card__head">
      <span class="scan-manual-card__icon" aria-hidden="true">⌨️</span>
      <div>
        <h3 class="scan-manual-card__title">Validation manuelle</h3>
        <p class="scan-manual-card__sub">Saisis le code complet ou les <strong>8 premiers caractères</strong> du billet.</p>
      </div>
    </div>
    <form id="scan-manual-form" class="scan-manual-form">
      <input type="text" id="scan-manual-input"
             placeholder="A1B2C3D4"
             autocomplete="off"
             autocorrect="off"
             autocapitalize="characters"
             spellcheck="false">
      <button type="submit" class="scan-btn scan-btn--validate">
        <span class="scan-btn__icon" aria-hidden="true">✓</span>
        <span class="scan-btn__label">Valider</span>
      </button>
    </form>
  </div>

  <!-- Dernier résultat -->
  <div class="scanner-result" id="scanner-result" hidden></div>

  <!-- Historique des scans -->
  <div class="scan-log-card">
    <div class="scan-log-card__head">
      <h3>📜 Historique de la session</h3>
      <span class="scan-log-card__count" id="scan-log-count">0</span>
    </div>
    <ul id="scan-log" class="scan-log-card__list">
      <li class="scan-log-card__empty">Aucun scan pour le moment.</li>
    </ul>
  </div>

</section>
<?php endif; ?>

<!-- ─── Panel : Tarifs (billetterie) ──────────────────── -->
<?php if ($isPaid): ?>
<section class="adm-evt-panel" data-panel="tarifs" hidden>
  <div class="admin-card">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:var(--s3);margin-bottom:var(--s4)">
      <div>
        <h2 style="margin:0">🎟 Catégories de billets</h2>
        <p style="color:var(--text-muted);font-size:.85rem;margin:.3rem 0 0">
          Crée plusieurs tarifs (Standard, Étudiant, Early bird…). Si aucun tarif n'est défini, le prix de base de l'événement est utilisé.
        </p>
      </div>
      <button type="button" class="btn btn--primary" onclick="document.getElementById('tarif-form-add').hidden = !document.getElementById('tarif-form-add').hidden">
        + Ajouter un tarif
      </button>
    </div>

    <!-- Bandeau d'info : explique la relation prix de base / tarifs détaillés -->
    <div class="tarif-info-box" style="margin-bottom:var(--s4);padding:var(--s3) var(--s4);border-radius:var(--r-md);border:1px solid rgba(139,47,201,.35);background:rgba(93,2,130,.10);font-size:.85rem;line-height:1.5">
      <strong style="display:block;margin-bottom:4px;color:#c4b5fd">ℹ️ Comment ça marche</strong>
      <?php if (empty($tarifs)): ?>
        Aucun tarif détaillé n'est défini pour le moment. Les acheteurs paieront le
        <strong>prix de base</strong> renseigné dans <em>Modifier l'événement</em> :
        <strong><?= number_format($prix, 2, ',', ' ') ?> €</strong>.
        <a href="evenements.php#evt-<?= (int)$id ?>" style="color:#c4b5fd;text-decoration:underline">Modifier le prix de base</a>.
      <?php else: ?>
        <strong style="color:#fff">Les tarifs détaillés ci-dessous remplacent le prix de base</strong>
        (qui est actuellement de <?= number_format($prix, 2, ',', ' ') ?> €).
        L'acheteur choisit son tarif au moment de la commande.
        Pour rétablir un prix unique, supprime tous les tarifs détaillés.
      <?php endif; ?>
    </div>

    <!-- Form ajout -->
    <form method="post" class="admin-form tarif-form" id="tarif-form-add" hidden style="margin-bottom:var(--s5);padding:var(--s4);background:rgba(255,255,255,.02);border-radius:var(--r-md);border:1px solid var(--border)"
          data-fee-form>
      <input type="hidden" name="action" value="add_tarif">
      <h3 style="margin:0 0 var(--s3)">Nouveau tarif</h3>
      <div class="form-row">
        <div class="form-col" style="flex:2"><label>Nom *<input type="text" name="tarif_nom" required placeholder="Ex: Étudiant, VIP, Early bird"></label></div>
        <div class="form-col"><label>Prix (€)<input type="number" name="tarif_prix" min="0" step="0.01" value="0" data-fee-price></label></div>
        <div class="form-col"><label>Places max <small>(0 = illimité)</small><input type="number" name="tarif_places_max" min="0" value="0"></label></div>
        <div class="form-col"><label>Ordre<input type="number" name="tarif_position" value="0"></label></div>
      </div>
      <div class="form-row">
        <div class="form-col" style="flex:2"><label>Description <small>(optionnelle)</small><input type="text" name="tarif_description" placeholder="Visible sur la page publique"></label></div>
      </div>
      <div class="form-row">
        <div class="form-col">
          <label>Écoles éligibles <small>(décocher = toutes)</small></label>
          <div style="display:flex;flex-wrap:wrap;gap:.3rem .8rem;margin-top:.4rem">
            <?php foreach ($ECOLES_ALL as $e): ?>
              <label style="font-weight:400"><input type="checkbox" name="tarif_ecoles_eligibles[]" value="<?= htmlspecialchars($e) ?>"> <?= htmlspecialchars($e) ?></label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="form-col">
          <label style="display:flex;gap:.5rem;align-items:flex-start;cursor:pointer;margin-top:1.5rem">
            <input type="checkbox" name="tarif_reserve_membres" value="1">
            <span><strong>Réservé aux utilisateurs connectés</strong><br><small style="color:var(--text-muted)">Indispensable pour appliquer le filtre par école.</small></span>
          </label>
          <label style="display:flex;gap:.5rem;align-items:flex-start;cursor:pointer;margin-top:var(--s3)">
            <input type="checkbox" name="tarif_frais_a_charge_client" value="1" data-fee-passthrough>
            <span><strong>Frais à la charge du client</strong><br><small style="color:var(--text-muted)">Le client paie prix + frais. Sinon, frais déduits du net.</small></span>
          </label>
        </div>
      </div>

      <!-- Récap des frais (rendu live en JS) -->
      <div class="tarif-fee-recap" data-fee-recap aria-live="polite"></div>

      <button type="submit" class="btn btn--primary">Créer le tarif →</button>
      <button type="button" class="btn" onclick="document.getElementById('tarif-form-add').hidden = true" style="background:var(--surface);border-color:var(--border)">Annuler</button>
    </form>

    <?php if (empty($tarifs)): ?>
      <p style="text-align:center;padding:var(--s6);color:var(--text-muted)">Aucun tarif défini. Le prix de base de l'événement (<?= number_format($prix, 2, ',', ' ') ?> €) sera utilisé.</p>
    <?php else: ?>
      <table class="admin-table" style="margin-bottom:0">
        <thead><tr><th>#</th><th>Nom</th><th>Prix</th><th>Paiement &amp; net</th><th>Places</th><th>Éligibilité</th><th>Statut</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($tarifs as $t):
          $ecoles = $t['ecoles_eligibles'] ? (json_decode($t['ecoles_eligibles'], true) ?: []) : [];
          $vendus = 0;
          try {
            $st = $pdo->prepare("SELECT COUNT(*) FROM inscriptions_evenement WHERE tarif_id=? AND statut='confirme'");
            $st->execute([$t['id']]);
            $vendus = (int)$st->fetchColumn();
          } catch (Throwable $e) {}
          $fraisClient = (int)($t['frais_a_charge_client'] ?? 0) === 1;
          $tPrix       = (float)$t['prix'];
          $fee         = paiement_calcule_frais($tPrix);
        ?>
          <tr>
            <td><?= $t['position'] ?></td>
            <td>
              <strong><?= htmlspecialchars($t['nom']) ?></strong>
              <?php if ($t['description']): ?><br><small style="color:var(--text-muted)"><?= htmlspecialchars($t['description']) ?></small><?php endif; ?>
              <?php if ($t['reserve_membres']): ?><br><span class="tag" style="font-size:.65rem">🔒 Connexion</span><?php endif; ?>
            </td>
            <td><strong><?= number_format($tPrix, 2, ',', ' ') ?> €</strong></td>
            <td style="font-size:.78rem;line-height:1.45">
              <?php if ($tPrix > 0): ?>
                <span class="badge badge--<?= $fee['provider'] === 'sumup' ? 'ok' : 'pending' ?>" style="font-size:.6rem"><?= htmlspecialchars($fee['label']) ?></span>
                <br>Frais : <strong><?= number_format($fee['frais'], 2, ',', ' ') ?> €</strong>
                <small style="color:var(--text-muted)"> (<?= rtrim(rtrim(number_format($fee['percent'], 2, ',', ' '), '0'), ',') ?>%<?= $fee['fixed'] > 0 ? ' + ' . number_format($fee['fixed'], 2, ',', ' ') . ' €' : '' ?>)</small>
                <br>
                <?php if ($fraisClient): ?>
                  Client paie : <strong><?= number_format($fee['client_total'], 2, ',', ' ') ?> €</strong> · Net : <strong><?= number_format($tPrix, 2, ',', ' ') ?> €</strong>
                <?php else: ?>
                  Net perçu : <strong><?= number_format($fee['net'], 2, ',', ' ') ?> €</strong>
                <?php endif; ?>
              <?php else: ?>
                <span style="color:var(--text-muted)">Gratuit</span>
              <?php endif; ?>
            </td>
            <td><?= $t['places_max'] ? ($vendus . ' / ' . $t['places_max']) : ($vendus . ' (illimité)') ?></td>
            <td style="font-size:.78rem">
              <?= empty($ecoles) ? '<span style="color:var(--text-muted)">Toutes</span>' : htmlspecialchars(implode(', ', $ecoles)) ?>
            </td>
            <td><span class="badge badge--<?= $t['statut']==='actif'?'ok':'pending' ?>"><?= htmlspecialchars($t['statut']) ?></span></td>
            <td>
              <div class="actions">
                <button class="btn btn--sm" onclick="toggleTarifEdit(<?= $t['id'] ?>)" style="background:var(--surface);border-color:var(--border)">Modifier</button>
                <form method="post" style="display:inline" onsubmit="return confirm('Supprimer ce tarif ?')">
                  <input type="hidden" name="action" value="delete_tarif">
                  <input type="hidden" name="tarif_id" value="<?= $t['id'] ?>">
                  <button class="btn btn--sm btn--danger">Suppr.</button>
                </form>
              </div>
            </td>
          </tr>
          <tr id="tarif-edit-<?= $t['id'] ?>" hidden>
            <td colspan="8" style="background:rgba(255,255,255,.02);padding:var(--s4)">
              <form method="post" class="admin-form" data-fee-form>
                <input type="hidden" name="action" value="update_tarif">
                <input type="hidden" name="tarif_id" value="<?= $t['id'] ?>">
                <div class="form-row">
                  <div class="form-col" style="flex:2"><label>Nom<input type="text" name="tarif_nom" value="<?= htmlspecialchars($t['nom']) ?>" required></label></div>
                  <div class="form-col"><label>Prix (€)<input type="number" name="tarif_prix" min="0" step="0.01" value="<?= htmlspecialchars($t['prix']) ?>" data-fee-price></label></div>
                  <div class="form-col"><label>Places max<input type="number" name="tarif_places_max" min="0" value="<?= htmlspecialchars((string)($t['places_max'] ?: 0)) ?>"></label></div>
                  <div class="form-col"><label>Ordre<input type="number" name="tarif_position" value="<?= (int)$t['position'] ?>"></label></div>
                  <div class="form-col"><label>Statut
                    <select name="tarif_statut">
                      <option value="actif" <?= $t['statut']==='actif'?'selected':'' ?>>Actif</option>
                      <option value="inactif" <?= $t['statut']==='inactif'?'selected':'' ?>>Inactif</option>
                    </select>
                  </label></div>
                </div>
                <div class="form-row">
                  <div class="form-col" style="flex:2"><label>Description<input type="text" name="tarif_description" value="<?= htmlspecialchars($t['description'] ?? '') ?>"></label></div>
                </div>
                <div class="form-row">
                  <div class="form-col">
                    <label>Écoles éligibles</label>
                    <div style="display:flex;flex-wrap:wrap;gap:.3rem .8rem;margin-top:.4rem">
                      <?php foreach ($ECOLES_ALL as $e): ?>
                        <label style="font-weight:400"><input type="checkbox" name="tarif_ecoles_eligibles[]" value="<?= htmlspecialchars($e) ?>" <?= in_array($e, $ecoles, true)?'checked':'' ?>> <?= htmlspecialchars($e) ?></label>
                      <?php endforeach; ?>
                    </div>
                  </div>
                  <div class="form-col">
                    <label style="display:flex;gap:.5rem;align-items:flex-start;margin-top:1rem">
                      <input type="checkbox" name="tarif_reserve_membres" value="1" <?= $t['reserve_membres']?'checked':'' ?>>
                      <span>Réservé aux utilisateurs connectés</span>
                    </label>
                    <label style="display:flex;gap:.5rem;align-items:flex-start;margin-top:var(--s3)">
                      <input type="checkbox" name="tarif_frais_a_charge_client" value="1" <?= $fraisClient ? 'checked' : '' ?> data-fee-passthrough>
                      <span><strong>Frais à la charge du client</strong><br><small style="color:var(--text-muted)">Le client paie prix + frais.</small></span>
                    </label>
                  </div>
                </div>
                <div class="tarif-fee-recap" data-fee-recap aria-live="polite"></div>
                <button class="btn btn--primary">Enregistrer</button>
                <button type="button" class="btn" onclick="toggleTarifEdit(<?= $t['id'] ?>)" style="background:var(--surface);border-color:var(--border)">Annuler</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</section>

<!-- ─── Panel : Codes promo ───────────────────────────── -->
<section class="adm-evt-panel" data-panel="codes" hidden>
  <div class="admin-card">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:var(--s3);margin-bottom:var(--s4)">
      <div>
        <h2 style="margin:0">🏷 Codes promo</h2>
        <p style="color:var(--text-muted);font-size:.85rem;margin:.3rem 0 0">
          Crée des codes de réduction (pourcentage ou montant fixe). Les utilisateurs les saisiront lors du paiement.
        </p>
      </div>
      <button type="button" class="btn btn--primary" onclick="document.getElementById('promo-form-add').hidden = !document.getElementById('promo-form-add').hidden">
        + Ajouter un code
      </button>
    </div>

    <form method="post" class="admin-form" id="promo-form-add" hidden style="margin-bottom:var(--s5);padding:var(--s4);background:rgba(255,255,255,.02);border-radius:var(--r-md);border:1px solid var(--border)">
      <input type="hidden" name="action" value="add_promo">
      <h3 style="margin:0 0 var(--s3)">Nouveau code promo</h3>
      <div class="form-row">
        <div class="form-col" style="flex:2"><label>Code *<input type="text" name="promo_code" required placeholder="Ex: ETUDIANT20" style="text-transform:uppercase"></label></div>
        <div class="form-col"><label>Type
          <select name="promo_type">
            <option value="pourcentage">Pourcentage (%)</option>
            <option value="fixe">Montant fixe (€)</option>
          </select>
        </label></div>
        <div class="form-col"><label>Valeur *<input type="number" name="promo_valeur" min="0" step="0.01" required></label></div>
      </div>
      <div class="form-row">
        <div class="form-col"><label>Tarif spécifique <small>(opt.)</small>
          <select name="promo_tarif_id">
            <option value="">Tous les tarifs</option>
            <?php foreach ($tarifs as $t): ?>
              <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nom']) ?> (<?= number_format($t['prix'], 2, ',', ' ') ?> €)</option>
            <?php endforeach; ?>
          </select>
        </label></div>
        <div class="form-col"><label>Utilisations max <small>(0 = illimité)</small><input type="number" name="promo_utilisations_max" min="0" value="0"></label></div>
        <div class="form-col"><label>Expire le <small>(opt.)</small><input type="datetime-local" name="promo_expire_le"></label></div>
      </div>
      <button type="submit" class="btn btn--primary">Créer →</button>
      <button type="button" class="btn" onclick="document.getElementById('promo-form-add').hidden = true" style="background:var(--surface);border-color:var(--border)">Annuler</button>
    </form>

    <?php if (empty($promos)): ?>
      <p style="text-align:center;padding:var(--s6);color:var(--text-muted)">Aucun code promo. Crée-en un pour offrir des réductions.</p>
    <?php else: ?>
      <table class="admin-table" style="margin-bottom:0">
        <thead><tr><th>Code</th><th>Réduction</th><th>Tarif lié</th><th>Utilisations</th><th>Expire</th><th>Statut</th><th>Lien</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($promos as $p):
          $tNom = '- Tous -';
          if ($p['tarif_id']) {
            foreach ($tarifs as $t) if ((int)$t['id'] === (int)$p['tarif_id']) { $tNom = $t['nom']; break; }
          }
          $promoUrl = 'evenement.php?id=' . $id . '&promo=' . urlencode($p['code']);
        ?>
          <tr>
            <td><strong style="font-family:monospace;font-size:1rem"><?= htmlspecialchars($p['code']) ?></strong></td>
            <td>
              <?php if ($p['type'] === 'pourcentage'): ?>
                <strong>-<?= rtrim(rtrim(number_format($p['valeur'], 2, ',', ' '), '0'), ',') ?> %</strong>
              <?php else: ?>
                <strong>-<?= number_format($p['valeur'], 2, ',', ' ') ?> €</strong>
              <?php endif; ?>
            </td>
            <td style="font-size:.82rem"><?= htmlspecialchars($tNom) ?></td>
            <td><?= $p['utilisations_count'] ?><?= $p['utilisations_max'] ? ' / ' . $p['utilisations_max'] : '' ?></td>
            <td style="font-size:.78rem"><?= $p['expire_le'] ? date('d/m/Y H:i', strtotime($p['expire_le'])) : '<span style="color:var(--text-muted)">-</span>' ?></td>
            <td><span class="badge badge--<?= $p['statut']==='actif'?'ok':'pending' ?>"><?= htmlspecialchars($p['statut']) ?></span></td>
            <td>
              <button type="button" class="btn btn--sm" onclick="copyPromoUrl('<?= htmlspecialchars($promoUrl) ?>', this)" style="background:var(--surface);border-color:var(--border)" title="Copier le lien avec code pré-rempli">📋 Copier</button>
            </td>
            <td>
              <div class="actions">
                <form method="post" style="display:inline">
                  <input type="hidden" name="action" value="toggle_promo">
                  <input type="hidden" name="promo_id" value="<?= $p['id'] ?>">
                  <button class="btn btn--sm" style="background:var(--surface);border-color:var(--border)"><?= $p['statut']==='actif'?'Désactiver':'Activer' ?></button>
                </form>
                <form method="post" style="display:inline" onsubmit="return confirm('Supprimer ce code ?')">
                  <input type="hidden" name="action" value="delete_promo">
                  <input type="hidden" name="promo_id" value="<?= $p['id'] ?>">
                  <button class="btn btn--sm btn--danger">Suppr.</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<!-- ─── Panel : Demandes (legacy - anciens "renseignement par mail") ────────────────── -->
<?php if ($hasDemandes): ?>
<section class="adm-evt-panel" data-panel="demandes" hidden>
  <div class="admin-card" style="padding:0;overflow:hidden">
    <?php if (empty($demandes)): ?>
      <p style="padding:var(--s6);text-align:center;color:var(--text-muted)">Aucune demande reçue.</p>
    <?php else: ?>
      <table class="admin-table">
        <thead><tr><th>Date</th><th>Demandeur</th><th>École</th><th>Message</th><th>Statut</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($demandes as $d): ?>
          <tr>
            <td style="font-size:.78rem"><?= date('d/m/Y H:i', strtotime($d['created_at'])) ?></td>
            <td>
              <strong><?= htmlspecialchars(trim(($d['prenom']??'').' '.($d['nom']??''))) ?: '-' ?></strong>
              <br><a href="mailto:<?= htmlspecialchars($d['email']) ?>"><?= htmlspecialchars($d['email']) ?></a>
            </td>
            <td><?= htmlspecialchars($d['ecole'] ?? '-') ?></td>
            <td style="font-size:.82rem;max-width:380px"><?= nl2br(htmlspecialchars($d['message'] ?? '-')) ?></td>
            <td>
              <span class="badge badge--<?= $d['statut']==='traite'?'ok':'pending' ?>"><?= htmlspecialchars($d['statut']) ?></span>
            </td>
            <td>
              <?php if ($d['statut'] !== 'traite'): ?>
                <form method="post" style="display:inline">
                  <input type="hidden" name="action" value="mark_traite">
                  <input type="hidden" name="demande_id" value="<?= $d['id'] ?>">
                  <button class="btn btn--sm btn--success">Marquer traitée</button>
                </form>
              <?php endif; ?>
              <a href="mailto:<?= htmlspecialchars($d['email']) ?>?subject=Re:%20<?= rawurlencode($ev['titre']) ?>" class="btn btn--sm" style="background:var(--surface);border-color:var(--border)">Répondre</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<!-- ─── Panel : Paiements ────────────────────────────── -->
<?php if (!empty($paiements)): ?>
<section class="adm-evt-panel" data-panel="paiements" hidden>
  <div class="admin-card" style="padding:0;overflow:hidden">
    <table class="admin-table">
      <thead><tr><th>#</th><th>Date</th><th>Email</th><th>Montant</th><th>Provider</th><th>Statut</th><th>Réf</th></tr></thead>
      <tbody>
      <?php foreach ($paiements as $tx): ?>
        <tr>
          <td><?= $tx['id'] ?></td>
          <td style="font-size:.78rem"><?= date('d/m/Y H:i', strtotime($tx['created_at'])) ?></td>
          <td><?= htmlspecialchars($tx['email'] ?? '-') ?></td>
          <td><strong><?= number_format($tx['montant'], 2, ',', ' ') ?> €</strong></td>
          <td><span class="badge"><?= htmlspecialchars($tx['provider']) ?></span></td>
          <td><span class="badge badge--<?= $tx['statut']==='paye'?'ok':($tx['statut']==='echec'?'ko':'pending') ?>"><?= htmlspecialchars($tx['statut']) ?></span></td>
          <td style="font-size:.7rem;color:var(--text-muted)"><?= htmlspecialchars($tx['provider_ref'] ?? '-') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>

<!-- ─── Panel : Export ───────────────────────────────── -->
<section class="adm-evt-panel" data-panel="export" hidden>
  <div class="admin-card">
    <h2>Exporter les données</h2>
    <p style="color:var(--text-muted)">Télécharge la liste des participants au format Excel (.xlsx) ou CSV.</p>
    <div style="display:flex;flex-wrap:wrap;gap:var(--s2);margin-top:var(--s3)">
      <a href="../api/export-evenement.php?id=<?= $id ?>&format=xlsx" class="btn btn--primary">📥 Participants (Excel)</a>
      <a href="../api/export-evenement.php?id=<?= $id ?>&format=csv" class="btn btn--ghost btn--sm">CSV</a>
      <?php if ($hasDemandes): ?>
        <a href="../api/export-evenement.php?id=<?= $id ?>&type=demandes&format=xlsx" class="btn btn--ghost btn--sm">Demandes (legacy)</a>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- Charge la lib de scan QR : version locale (évite les blocages Tracking Prevention d'Edge) -->
<script src="../js/lib/html5-qrcode.min.js?v=<?= @filemtime(__DIR__ . '/../js/lib/html5-qrcode.min.js') ?>"
        onload="window.__qrLibLoaded = true; document.dispatchEvent(new Event('qr-lib-ready'));"
        onerror="console.error('[scan] Lib html5-qrcode introuvable à ../js/lib/html5-qrcode.min.js - recharge la page.');"></script>
<script>
// helpers UI pour les tarifs et promos
function toggleTarifEdit(id) {
  const row = document.getElementById('tarif-edit-' + id);
  if (row) row.hidden = !row.hidden;
}
function copyPromoUrl(path, btn) {
  const url = window.location.origin + window.location.pathname.replace(/admin\/.*$/, '') + path;
  navigator.clipboard.writeText(url).then(() => {
    const old = btn.textContent;
    btn.textContent = '✓ Copié !';
    setTimeout(() => { btn.textContent = old; }, 1500);
  });
}

// calcul live des frais : SumUp si <= 25€, Stripe sinon
(function () {
  const CONF = {
    threshold:   <?= json_encode(paiement_threshold()) ?>,
    sumupPct:    <?= json_encode(paiement_sumup_fee_pct()) ?>,
    stripePct:   <?= json_encode(paiement_stripe_fee_pct()) ?>,
    stripeFixed: <?= json_encode(paiement_stripe_fee_fixed()) ?>,
  };
  const fmt = (n) => (Math.round(n * 100) / 100).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const trimPct = (p) => {
    const s = (Math.round(p * 100) / 100).toString().replace('.', ',');
    return s;
  };

  function compute(price, passthrough) {
    if (!isFinite(price) || price <= 0) {
      return { gratuit: true };
    }
    const sumup = price <= CONF.threshold;
    const pct   = sumup ? CONF.sumupPct  : CONF.stripePct;
    const fixed = sumup ? 0              : CONF.stripeFixed;
    const frais = Math.round((price * pct / 100 + fixed) * 100) / 100;
    return {
      gratuit: false,
      provider:    sumup ? 'SumUp' : 'Stripe',
      providerKey: sumup ? 'sumup' : 'stripe',
      threshold:   CONF.threshold,
      pct, fixed, frais,
      net:          Math.max(0, Math.round((price - frais) * 100) / 100),
      clientTotal:  Math.round((price + frais) * 100) / 100,
      passthrough,
    };
  }

  function render(form) {
    const recap = form.querySelector('[data-fee-recap]');
    if (!recap) return;
    const price = parseFloat((form.querySelector('[data-fee-price]')?.value || '0').replace(',', '.'));
    const pass  = !!form.querySelector('[data-fee-passthrough]')?.checked;
    const r     = compute(price, pass);

    if (r.gratuit) {
      recap.className = 'tarif-fee-recap';
      recap.innerHTML = '<small style="color:var(--text-muted)">Billet gratuit - aucun frais de paiement.</small>';
      return;
    }
    const badge = '<span class="tarif-fee-badge tarif-fee-badge--' + r.providerKey + '">' + r.provider + '</span>';
    const formula = trimPct(r.pct) + '%' + (r.fixed > 0 ? ' + ' + fmt(r.fixed) + ' €' : '');
    const line1 = '<div><strong>Paiement :</strong> ' + badge
                + ' <small style="color:var(--text-muted)">(seuil ' + fmt(r.threshold) + ' € : ≤ = SumUp, &gt; = Stripe)</small></div>';
    const line2 = '<div><strong>Frais :</strong> ' + fmt(r.frais) + ' € '
                + '<small style="color:var(--text-muted)">(' + formula + ')</small></div>';
    const line3 = r.passthrough
      ? '<div>Client paie <strong>' + fmt(r.clientTotal) + ' €</strong> → Net perçu : <strong>' + fmt(price) + ' €</strong></div>'
      : '<div>Client paie <strong>' + fmt(price) + ' €</strong> → Net perçu : <strong>' + fmt(r.net) + ' €</strong></div>';
    recap.className = 'tarif-fee-recap tarif-fee-recap--' + r.providerKey;
    recap.innerHTML = line1 + line2 + line3;
  }

  function bind(form) {
    if (!form || form._feeBound) return;
    form._feeBound = true;
    form.addEventListener('input', (e) => {
      if (e.target.matches('[data-fee-price], [data-fee-passthrough]')) render(form);
    });
    form.addEventListener('change', (e) => {
      if (e.target.matches('[data-fee-price], [data-fee-passthrough]')) render(form);
    });
    render(form);
  }

  document.querySelectorAll('form[data-fee-form]').forEach(bind);
})();

// recherche et filtres dans la liste des participants
(function () {
  const search = document.getElementById('participants-search');
  if (!search) return;
  const rows = document.querySelectorAll('.participant-row');
  const fConf = document.getElementById('filter-confirme');
  const fAtt  = document.getElementById('filter-attente');
  const fAnn  = document.getElementById('filter-annule');
  const fNoScan = document.getElementById('filter-not-scanned');
  const counter = document.getElementById('participants-count');

  function apply() {
    const q = (search.value || '').toLowerCase().trim();
    const showConf  = fConf?.checked;
    const showAtt   = fAtt?.checked;
    const showAnn   = fAnn?.checked;
    const onlyNoScan = fNoScan?.checked;
    let visible = 0;
    rows.forEach(r => {
      const blob = r.dataset.search || '';
      const st   = r.dataset.statut || '';
      const scanned  = r.dataset.scanned === '1';
      const hasTok   = r.dataset.hasToken === '1';
      let ok = true;
      if (q && !blob.includes(q)) ok = false;
      const isConf = st === 'confirme';
      const isAtt  = st === 'en_attente' || st === 'liste_attente';
      const isAnn  = st === 'annule' || st === 'refuse' || st === 'rembourse';
      if (isConf && !showConf) ok = false;
      if (isAtt  && !showAtt)  ok = false;
      if (isAnn  && !showAnn)  ok = false;
      if (onlyNoScan && (!hasTok || scanned)) ok = false;
      r.style.display = ok ? '' : 'none';
      if (ok) visible++;
    });
    if (counter) counter.textContent = `${visible} affiché${visible > 1 ? 's' : ''} / ${rows.length} total`;
  }
  search.addEventListener('input', apply);
  [fConf, fAtt, fAnn, fNoScan].forEach(el => el && el.addEventListener('change', apply));
  apply();
})();

// gestion des onglets (participants / tarifs / scanner)
document.querySelectorAll('.adm-evt-tab').forEach(tab => {
  tab.addEventListener('click', () => {
    const target = tab.dataset.tab;
    document.querySelectorAll('.adm-evt-tab').forEach(t => t.classList.toggle('is-active', t === tab));
    document.querySelectorAll('.adm-evt-panel').forEach(p => {
      const active = p.dataset.panel === target;
      p.classList.toggle('is-active', active);
      p.hidden = !active;
    });
  });
});

// scanner QR (html5-qrcode)
(function () {
  const startBtn = document.getElementById('scan-start');
  if (!startBtn) return; // pas de scanner sur cet event

  const stopBtn  = document.getElementById('scan-stop');
  const select   = document.getElementById('scan-camera-select');
  const result   = document.getElementById('scanner-result');
  const log      = document.getElementById('scan-log');
  const manualForm = document.getElementById('scan-manual-form');
  const manualInp  = document.getElementById('scan-manual-input');
  const statusEl   = document.getElementById('scan-status');
  const scanFrame  = document.getElementById('scan-stage-frame');
  const fsEl       = document.getElementById('scan-fullscreen');
  const fsBack     = document.getElementById('scan-fullscreen-back');
  const fsStatOk   = document.getElementById('scan-fs-stat-ok');
  const fsStatErr  = document.getElementById('scan-fs-stat-err');
  const statOk     = document.getElementById('scan-stat-ok');
  const statErr    = document.getElementById('scan-stat-err');
  const statTotal  = document.getElementById('scan-stat-total');
  const logCountEl = document.getElementById('scan-log-count');

  const scanStats = { ok: 0, err: 0, total: 0 };
  let fsOpen = false;

  function setScanStats() {
    if (statOk) statOk.textContent = String(scanStats.ok);
    if (statErr) statErr.textContent = String(scanStats.err);
    if (statTotal) statTotal.textContent = String(scanStats.total);
    if (fsStatOk) fsStatOk.textContent = String(scanStats.ok);
    if (fsStatErr) fsStatErr.textContent = String(scanStats.err);
  }

  function openScanFullscreen() {
    if (!fsEl) return;
    fsEl.hidden = false;
    fsEl.setAttribute('aria-hidden', 'false');
    fsEl.classList.add('is-open');
    document.body.classList.add('scan-fs-lock');
    fsOpen = true;
    fsBack?.focus();
  }

  async function closeScanFullscreen() {
    if (!fsEl) return;
    if (qrInstance && qrInstance.isScanning) {
      try {
        await qrInstance.stop();
        qrInstance.clear();
      } catch (e) { console.warn('[scan] stop', e); }
    }
    fsEl.classList.remove('is-open');
    fsEl.hidden = true;
    fsEl.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('scan-fs-lock');
    fsOpen = false;
    startBtn.disabled = false;
    if (stopBtn) stopBtn.disabled = true;
    if (scanFrame) scanFrame.classList.remove('is-scanning');
    setStartLabel('Lancer le scan');
    setStatus('⏸ Scan arrêté — tu peux relancer');
  }

  function setStatus(txt, color) {
    if (!statusEl) return;
    statusEl.textContent = txt;
    statusEl.style.color = color || 'var(--text-muted)';
    statusEl.classList.remove('scan-stage__status--ok', 'scan-stage__status--warn', 'scan-stage__status--err');
    if (color && /#6ed18c|green/i.test(color)) statusEl.classList.add('scan-stage__status--ok');
    else if (color && /#ff8d8b|red/i.test(color)) statusEl.classList.add('scan-stage__status--err');
    else if (color && /#ff9500|orange/i.test(color)) statusEl.classList.add('scan-stage__status--warn');
  }
  function checkLibStatus() {
    if (window.Html5Qrcode) setStatus('✓ Lib prête', '#6ed18c');
    else setStatus('⏳ Lib en chargement…');
  }
  checkLibStatus();
  document.addEventListener('qr-lib-ready', checkLibStatus);
  setTimeout(() => { if (!window.Html5Qrcode) setStatus('✗ Lib introuvable (vérifie /js/lib/)', '#ff8d8b'); }, 5000);


  let qrInstance = null;
  let lastScanned = '';
  let lastScannedAt = 0;

  function showResult(payload, status, html) {
    result.hidden = false;
    result.className = 'scanner-result scanner-result--' + status;
    result.innerHTML = html;
  }

  // overlay plein écran affiché après scan (ok/erreur)
  const overlay      = document.getElementById('scan-overlay');
  const overlayIcon  = document.getElementById('scan-overlay-icon');
  const overlayTitle = document.getElementById('scan-overlay-title');
  const overlaySub   = document.getElementById('scan-overlay-sub');
  let   overlayTimer = null;

  function flashOverlay(status, title, sub) {
    if (!overlay) return;
    const cfg = {
      ok:   { cls: 'scan-overlay--ok',   icon: '✓' },
      warn: { cls: 'scan-overlay--warn', icon: '⚠' },
      err:  { cls: 'scan-overlay--err',  icon: '✗' },
    }[status] || { cls: 'scan-overlay--ok', icon: '?' };

    overlayIcon.textContent  = cfg.icon;
    overlayTitle.textContent = title || '';
    overlaySub.textContent   = sub || '';
    overlay.className = 'scan-overlay is-visible ' + cfg.cls;
    playBeep(status);

    clearTimeout(overlayTimer);
    overlayTimer = setTimeout(() => {
      overlay.classList.remove('is-visible');
    }, status === 'ok' ? 1800 : 2800);
  }

  // Petit bip via WebAudio (pas de fichier à charger)
  let audioCtx = null;
  function playBeep(status) {
    try {
      audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
      const freq = status === 'ok' ? 880 : (status === 'warn' ? 440 : 220);
      const osc = audioCtx.createOscillator();
      const gain = audioCtx.createGain();
      osc.connect(gain); gain.connect(audioCtx.destination);
      osc.frequency.value = freq;
      osc.type = 'sine';
      gain.gain.setValueAtTime(0.12, audioCtx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.18);
      osc.start();
      osc.stop(audioCtx.currentTime + 0.2);
      if (status === 'ok') {
        // Double bip pour validation OK
        setTimeout(() => {
          const o2 = audioCtx.createOscillator();
          const g2 = audioCtx.createGain();
          o2.connect(g2); g2.connect(audioCtx.destination);
          o2.frequency.value = 1318; o2.type = 'sine';
          g2.gain.setValueAtTime(0.12, audioCtx.currentTime);
          g2.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.18);
          o2.start(); o2.stop(audioCtx.currentTime + 0.2);
        }, 120);
      }
    } catch (e) { /* ignore : pas d'audio */ }
  }

  function addLog(html, ok = true) {
    const empty = log.querySelector('.scan-log-card__empty');
    if (empty) empty.remove();
    const li = document.createElement('li');
    li.className = 'scan-log__item' + (ok ? '' : ' is-err');
    const time = new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    li.innerHTML = `<span class="scan-log__time">${time}</span> ${html}`;
    log.prepend(li);
    scanStats.total++;
    if (ok) scanStats.ok++; else scanStats.err++;
    setScanStats();
    if (logCountEl) logCountEl.textContent = String(log.querySelectorAll('.scan-log__item').length);
  }

  async function handleScan(payload) {
    // Évite les doubles scans rapides du même code
    const now = Date.now();
    if (payload === lastScanned && (now - lastScannedAt) < 3000) return;
    lastScanned = payload; lastScannedAt = now;

    console.debug('[scan] QR détecté :', payload);

    try {
      const res = await fetch('../api/qr-validate.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          payload,
          evenement_id: '<?= $id ?>',
        }),
      });
      const text = await res.text();
      let data;
      try { data = JSON.parse(text); }
      catch (e) {
        console.error('[scan] Réponse API non-JSON :', text);
        addLog('✗ Erreur serveur : ' + escapeHtml(text.slice(0, 80)), false);
        return;
      }
      if (data.ok) {
        const name = (data.participant?.prenom || '') + ' ' + (data.participant?.nom || '');
        const niceName = name.trim() || data.participant?.email || '- Invité -';
        showResult(payload, 'ok', `
          <div class="scanner-result__big">✓ Validé</div>
          <div class="scanner-result__name">${escapeHtml(niceName)}</div>
          ${data.participant?.email ? `<div style="color:var(--text-muted);font-size:.78rem">${escapeHtml(data.participant.email)}</div>` : ''}
        `);
        addLog(`✓ <strong>${escapeHtml(niceName)}</strong> validé`);
        flashOverlay('ok', 'Validé', niceName);
      } else if (data.already) {
        showResult(payload, 'warn', `
          <div class="scanner-result__big">⚠ Déjà utilisé</div>
          <div class="scanner-result__name">${escapeHtml(data.msg)}</div>
        `);
        addLog(`⚠ Billet déjà utilisé`, false);
        flashOverlay('warn', 'Déjà utilisé', data.msg || '');
      } else {
        showResult(payload, 'err', `
          <div class="scanner-result__big">✗ Refusé</div>
          <div class="scanner-result__name">${escapeHtml(data.msg || 'Erreur')}</div>
        `);
        addLog(`✗ ${escapeHtml(data.msg || 'Erreur')}`, false);
        flashOverlay('err', 'Refusé', data.msg || '');
      }
    } catch (e) {
      addLog(`✗ Erreur réseau : ${escapeHtml(String(e))}`, false);
    }
  }

  function escapeHtml(s) { return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

  async function listCameras() {
    if (!window.Html5Qrcode) return;
    try {
      const devices = await Html5Qrcode.getCameras();
      select.innerHTML = '<option value="">- Auto -</option>' + devices.map(d => `<option value="${d.id}">${d.label || d.id}</option>`).join('');
    } catch (e) {
      console.warn('Camera enum failed', e);
    }
  }

  function waitForQrLib(maxMs = 6000) {
    return new Promise((resolve, reject) => {
      if (window.Html5Qrcode) return resolve();
      const t0 = Date.now();
      const onReady = () => { cleanup(); resolve(); };
      const iv = setInterval(() => {
        if (window.Html5Qrcode) { cleanup(); resolve(); }
        else if (Date.now() - t0 > maxMs) { cleanup(); reject(new Error('timeout')); }
      }, 150);
      function cleanup() { clearInterval(iv); document.removeEventListener('qr-lib-ready', onReady); }
      document.addEventListener('qr-lib-ready', onReady);
    });
  }

  const startLabel = startBtn.querySelector('.scan-btn__label');
  function setStartLabel(t) { if (startLabel) startLabel.textContent = t; else startBtn.textContent = t; }

  startBtn.addEventListener('click', async () => {
    startBtn.disabled = true;
    setStartLabel('Chargement…');
    try { await waitForQrLib(); }
    catch (e) {
      startBtn.disabled = false; setStartLabel('Lancer le scan');
      alert("Impossible de charger la bibliothèque de scan QR.\n\nVérifie :\n  • ta connexion internet (CDN bloqué ?),\n  • une extension type uBlock/AdBlocker,\n  • un pare-feu/réseau d'école.\n\nTu peux toujours valider manuellement via la saisie du code ou depuis la liste des participants.");
      return;
    }
    if (!qrInstance) qrInstance = new Html5Qrcode('scanner-video', /* verbose */ false);
    try {
      // qrbox adaptatif : 80 % de la plus petite dimension du flux vidéo
      const config = {
        fps: 25,
        // Cadre unique : même zone que la vidéo (évite la double zone lib + viseur)
        qrbox: (vw, vh) => ({ width: Math.max(1, vw - 4), height: Math.max(1, vh - 4) }),
        aspectRatio: 1.0,
        disableFlip: false,
        experimentalFeatures: { useBarCodeDetectorIfSupported: false },
      };
      const cam = select.value || { facingMode: 'environment' };
      setStartLabel('Démarrage caméra…');
      // Callback d'erreur de scan : on ignore "QR not found in frame" (très fréquent et normal)
      const onScanError = (errMsg) => {
        if (typeof errMsg === 'string' && !/NotFoundException|No.*MultiFormat|No QR code/i.test(errMsg)) {
          console.debug('[scan]', errMsg);
        }
      };
      await qrInstance.start(cam, config, handleScan, onScanError);
      startBtn.disabled = true;
      if (stopBtn) stopBtn.disabled = false;
      setStartLabel('Lancer le scan');
      if (scanFrame) scanFrame.classList.add('is-scanning');
      openScanFullscreen();
      setStatus('🟢 Scan actif — vise le QR', '#6ed18c');
    } catch (e) {
      await closeScanFullscreen();
      startBtn.disabled = false; setStartLabel('Lancer le scan');
      const msg = String(e && e.message || e);
      let hint = '';
      if (/not supported|secure|https/i.test(msg)) {
        hint = "\n\n👉 Cause probable : tu accèdes au site via HTTP. Les navigateurs n'autorisent la caméra qu'en HTTPS ou sur localhost.\n\nSolutions :\n  1. Utilise http://localhost au lieu de " + window.location.origin + "\n  2. chrome://flags/#unsafely-treat-insecure-origin-as-secure → ajouter " + window.location.origin + "\n  3. En attendant : saisis le code manuellement (8 caractères suffisent).";
      } else if (/permission|denied|NotAllowed/i.test(msg)) {
        hint = "\n\n👉 La permission caméra a été refusée. Clique sur l'icône cadenas / caméra dans la barre d'URL pour l'autoriser, puis recharge la page.";
      } else if (/NotFound|no.*camera/i.test(msg)) {
        hint = "\n\n👉 Aucune caméra détectée sur ce device.";
      }
      alert('Impossible de démarrer la caméra : ' + msg + hint);
    }
  });

  if (stopBtn) stopBtn.addEventListener('click', () => closeScanFullscreen());
  if (fsBack) fsBack.addEventListener('click', () => closeScanFullscreen());
  document.addEventListener('keydown', (e) => {
    if (fsOpen && e.key === 'Escape') {
      e.preventDefault();
      closeScanFullscreen();
    }
  });

  manualForm.addEventListener('submit', e => {
    e.preventDefault();
    const v = manualInp.value.trim().toUpperCase();
    if (v) { handleScan(v); manualInp.value = ''; }
  });

  // Liste les caméras quand la lib est prête (peut nécessiter une autorisation préalable)
  document.addEventListener('qr-lib-ready', () => setTimeout(listCameras, 200));
  if (window.Html5Qrcode) setTimeout(listCameras, 500);
})();
</script>

<?php require_once 'includes/admin-footer.php'; ?>
