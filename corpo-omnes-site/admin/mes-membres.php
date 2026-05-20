<?php
// gestion des membres d'une structure - accessible aux admins de structure
// db.php doit être chargé AVANT admin-header.php (qui l'utilise dans le sidebar)
require_once __DIR__ . '/../includes/db.php';
$adminTitle = 'Membres des structures';
$adminPage  = 'mes-membres';
require_once __DIR__ . '/includes/admin-header.php';

$userId = (int)$_SESSION['user_id'];

// on liste les structures que l'user peut gérer
$mesStructures = [];
$seen = []; // pour éviter les doublons

$addStruct = function(string $type, int $id, string $nom = '', string $slug = '') use (&$mesStructures, &$seen) {
    $key = $type . ':' . $id;
    if (isset($seen[$key])) return;
    $seen[$key] = true;
    $mesStructures[] = ['type' => $type, 'id' => $id, 'nom' => $nom, 'slug' => $slug];
};

if (isAdminCorpo()) {
    // corpo voit tout
    $rows = $pdo->query("SELECT id, nom, slug, type FROM associations ORDER BY type, nom")->fetchAll();
    foreach ($rows as $r) {
        $assoType = strtolower((string)($r['type'] ?? ''));
        $intType  = ($assoType === 'bde') ? 'bde' : (($assoType === 'bds') ? 'bds' : 'asso');
        $addStruct($intType, (int)$r['id'], (string)$r['nom'], (string)($r['slug'] ?? ''));
    }
    $rows = $pdo->query("SELECT 'sport' AS type, id, nom, slug FROM sports ORDER BY nom")->fetchAll();
    foreach ($rows as $r) $addStruct('sport', (int)$r['id'], $r['nom'], $r['slug'] ?? '');
} else {
    // admin direct de sa structure
    $stmtDirect = $pdo->prepare(
        "SELECT sm.structure_type AS type, sm.structure_id AS id,
                COALESCE(a.nom, '') AS nom, COALESCE(a.slug, '') AS slug
         FROM structure_membres sm
         LEFT JOIN associations a ON sm.structure_type IN ('asso','bde','bds') AND a.id = sm.structure_id
         WHERE sm.user_id = ? AND sm.role_in_struct = 'admin' AND sm.statut = 'actif'
           AND sm.structure_type IN ('asso','bde','bds')
         ORDER BY nom"
    );
    $stmtDirect->execute([$userId]);
    foreach ($stmtDirect->fetchAll() as $r) {
        $addStruct((string)$r['type'], (int)$r['id'], (string)$r['nom'], (string)($r['slug'] ?? ''));
    }

    // assos rattachées (BDE → ses assos enfants)
    $assoIds = getManagedAssoIds($pdo);
    if (!empty($assoIds)) {
        $pl = implode(',', array_map('intval', $assoIds));
        $rows = $pdo->query("SELECT id, nom, slug, type FROM associations WHERE id IN ($pl) ORDER BY type, nom")->fetchAll();
        foreach ($rows as $r) {
            // tout ce qui n'est pas BDE/BDS reste "asso"
            $assoType = strtolower((string)($r['type'] ?? ''));
            $intType  = ($assoType === 'bde') ? 'bde' : (($assoType === 'bds') ? 'bds' : 'asso');
            $addStruct($intType, (int)$r['id'], (string)$r['nom'], (string)($r['slug'] ?? ''));
        }
    }

    // sports gérés
    $sportIds = getManagedSportIds($pdo);
    if (!empty($sportIds)) {
        $pl = implode(',', array_map('intval', $sportIds));
        $rows = $pdo->query("SELECT id, nom, slug FROM sports WHERE id IN ($pl) ORDER BY nom")->fetchAll();
        foreach ($rows as $r) {
            $addStruct('sport', (int)$r['id'], (string)$r['nom'], (string)($r['slug'] ?? ''));
        }
    }
}

// tri : BDE → BDS → Asso → Sport, puis alphabétique
usort($mesStructures, function ($a, $b) {
    $order = ['bde' => 0, 'bds' => 1, 'asso' => 2, 'sport' => 3];
    $ra = $order[$a['type']] ?? 9;
    $rb = $order[$b['type']] ?? 9;
    if ($ra !== $rb) return $ra <=> $rb;
    return strcmp((string)$a['nom'], (string)$b['nom']);
});

// structure choisie dans l'URL
$selType = $_GET['type'] ?? ($mesStructures[0]['type'] ?? 'asso');
$selId   = (int)($_GET['id'] ?? ($mesStructures[0]['id'] ?? 0));

// on vérifie que l'user a bien le droit de gérer cette structure
if ($selType === 'sport') {
    $canManage = isAdminCorpo() || canManageSport($selId, $pdo);
} else {
    $canManage = isAdminCorpo() || canManageAsso($selId, $pdo);
    if (!$canManage) {
        // Vérifie aussi admin direct BDE/BDS
        foreach ($mesStructures as $ms) {
            if ($ms['type'] === $selType && (int)$ms['id'] === $selId) { $canManage = true; break; }
        }
    }
}

if (!$canManage) {
    if (!empty($mesStructures)) {
        header('Location: mes-membres.php?type=' . urlencode($mesStructures[0]['type']) . '&id=' . $mesStructures[0]['id']);
    } else {
        header('Location: index.php');
    }
    exit;
}

// vérifie si la migration des colonnes resp_* a été appliquée
function mesm_has_resp_cols(PDO $pdo): bool {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        $st = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'structure_membres' AND COLUMN_NAME = 'resp_evenement'"
        );
        $st->execute();
        $cache = ((int)$st->fetchColumn()) > 0;
    } catch (Throwable $e) {
        $cache = false;
    }
    return $cache;
}
$smHasRespCols = mesm_has_resp_cols($pdo);

// traitement des actions POST
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    // Valider demande d'adhésion → adherent par défaut (non affiché publiquement).
    // L'admin pourra promouvoir en « membre » ou « bureau » depuis la liste.
    if ($act === 'valider_adhesion') {
        $demandeId = (int)$_POST['demande_id'];
        $pdo->prepare("UPDATE demandes_adhesion SET statut = 'accepte', traite_par = ? WHERE id = ?")->execute([$userId, $demandeId]);
        $dem = $pdo->prepare("SELECT * FROM demandes_adhesion WHERE id = ?");
        $dem->execute([$demandeId]);
        $d = $dem->fetch();
        if ($d) {
            $pdo->prepare("INSERT INTO structure_membres (user_id, structure_type, structure_id, role_in_struct, statut)
                           VALUES (?, ?, ?, 'adherent', 'actif')
                           ON DUPLICATE KEY UPDATE statut = 'actif'")->execute([$d['user_id'], $d['structure_type'], $d['structure_id']]);
        }
        $msg = 'Adhérent accepté.';
    }

    // Refuser demande
    if ($act === 'refuser_adhesion') {
        $demandeId = (int)$_POST['demande_id'];
        $pdo->prepare("UPDATE demandes_adhesion SET statut = 'refuse', traite_par = ? WHERE id = ?")->execute([$userId, $demandeId]);
        $msg = 'Demande refusée.';
    }

    // Changer rôle d'un membre (3 niveaux : adherent / membre / admin)
    if ($act === 'change_role') {
        $membreId = (int)$_POST['membre_id'];
        $newRole  = in_array($_POST['new_role'], ['admin','membre','adherent'], true) ? $_POST['new_role'] : 'adherent';
        $pdo->prepare("UPDATE structure_membres SET role_in_struct = ? WHERE id = ? AND structure_type = ? AND structure_id = ?")
            ->execute([$newRole, $membreId, $selType, $selId]);

        // Récupère l'user concerné (pour propager les changements de rôle global)
        $uId = $pdo->prepare("SELECT user_id FROM structure_membres WHERE id = ?");
        $uId->execute([$membreId]);
        $uIdVal = (int)$uId->fetchColumn();

        if ($newRole === 'admin') {
            // Promotion admin → 'membre_corpo' (accès panel) si l'user est encore 'user'
            if ($uIdVal) {
                $pdo->prepare("UPDATE users SET role = 'membre_corpo' WHERE id = ? AND role = 'user'")->execute([$uIdVal]);
            }
        } else {
            // Rétrogradation membre/adhérent : si l'user n'est plus admin nulle part
            // ET son rôle global est 'membre_corpo' (hérité), le ramener à 'user'
            // (sinon il garderait l'accès au panneau admin via isMembreCorpo()).
            if ($uIdVal) {
                syncGlobalRoleAfterStructChange($pdo, $uIdVal);
            }
        }
        $msg = 'Rôle mis à jour.';
    }

    // Retirer un membre
    if ($act === 'retirer') {
        $membreId = (int)$_POST['membre_id'];

        // Récupère l'user_id avant suppression pour la synchro post-retrait
        $uId = $pdo->prepare("SELECT user_id FROM structure_membres WHERE id = ?");
        $uId->execute([$membreId]);
        $uIdVal = (int)$uId->fetchColumn();

        $pdo->prepare("DELETE FROM structure_membres WHERE id = ? AND structure_type = ? AND structure_id = ?")
            ->execute([$membreId, $selType, $selId]);

        if ($uIdVal) {
            syncGlobalRoleAfterStructChange($pdo, $uIdVal);
        }
        $msg = 'Membre retiré.';
    }

    // Responsabilités fonctionnelles (cases cumulables)
    if ($act === 'save_all_resp' && $smHasRespCols) {
        $stmtIds = $pdo->prepare(
            "SELECT id FROM structure_membres WHERE structure_type = ? AND structure_id = ? AND statut = 'actif'"
        );
        $stmtIds->execute([$selType, $selId]);
        $validIds = array_map('intval', $stmtIds->fetchAll(PDO::FETCH_COLUMN));
        $upd        = $pdo->prepare(
            "UPDATE structure_membres SET resp_evenement = ?, resp_partenariat = ?, resp_communication = ?, resp_tresorerie = ?
              WHERE id = ? AND structure_type = ? AND structure_id = ?"
        );
        foreach ($validIds as $mid) {
            $re = !empty($_POST['re'][$mid]);
            $rp = !empty($_POST['rp'][$mid]);
            $rc = !empty($_POST['rc'][$mid]);
            $rt = !empty($_POST['rt'][$mid]);
            $upd->execute([$re ? 1 : 0, $rp ? 1 : 0, $rc ? 1 : 0, $rt ? 1 : 0, $mid, $selType, $selId]);
        }
        $msg = 'Responsabilités enregistrées.';
    }

    // Ajouter un membre par email (3 niveaux possibles)
    if ($act === 'ajouter') {
        $email   = trim($_POST['email'] ?? '');
        $roleAdd = in_array($_POST['role_add'], ['admin','membre','adherent'], true) ? $_POST['role_add'] : 'membre';
        $targetUser = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $targetUser->execute([$email, $email]);
        $tuid = $targetUser->fetchColumn();
        if ($tuid) {
            $pdo->prepare("INSERT INTO structure_membres (user_id, structure_type, structure_id, role_in_struct, statut)
                           VALUES (?, ?, ?, ?, 'actif')
                           ON DUPLICATE KEY UPDATE role_in_struct = VALUES(role_in_struct), statut = 'actif'")
                ->execute([$tuid, $selType, $selId, $roleAdd]);
            if ($roleAdd === 'admin') {
                $pdo->prepare("UPDATE users SET role = 'membre_corpo' WHERE id = ? AND role = 'user'")->execute([$tuid]);
            }
            $msg = ucfirst($roleAdd) . ' ajouté.';
        } else {
            $msg = '⚠️ Aucun utilisateur trouvé avec cet email / username.';
        }
    }

    // Valider inscription sport (liste d'attente)
    if ($act === 'valider_insc_sport') {
        $inscId = (int)$_POST['insc_id'];
        $pdo->prepare("UPDATE inscriptions_sport SET statut = 'confirme' WHERE id = ?")->execute([$inscId]);
        $pdo->prepare("UPDATE sports SET inscrits = inscrits + 1 WHERE id = (SELECT sport_id FROM inscriptions_sport WHERE id = ?)")->execute([$inscId]);
        $msg = 'Inscription sport confirmée.';
    }

    // Actualiser l'URL pour éviter double-submit
    header("Location: mes-membres.php?type=$selType&id=$selId&msg=" . urlencode($msg));
    exit;
}

if (isset($_GET['msg'])) $msg = htmlspecialchars($_GET['msg']);

// membres actuels, triés Bureau → Membre → Adhérent
$respSelect = $smHasRespCols
    ? ', sm.resp_evenement, sm.resp_partenariat, sm.resp_communication, sm.resp_tresorerie'
    : '';
$stmtMb = $pdo->prepare(
    "SELECT sm.id AS membre_id, sm.user_id, sm.role_in_struct, sm.statut, sm.created_at,
            u.username, u.nom, u.prenom, u.email, u.ecole
            $respSelect
     FROM structure_membres sm
     JOIN users u ON u.id = sm.user_id
     WHERE sm.structure_type = ? AND sm.structure_id = ? AND sm.statut = 'actif'
     ORDER BY FIELD(sm.role_in_struct, 'admin', 'membre', 'adherent'), u.username ASC"
);
$stmtMb->execute([$selType, $selId]);
$membres = $stmtMb->fetchAll();

// Comptes par rôle pour l'affichage
$nbBureau   = 0;
$nbMembres  = 0;
$nbAdherent = 0;
foreach ($membres as $mb) {
    if ($mb['role_in_struct'] === 'admin')          $nbBureau++;
    elseif ($mb['role_in_struct'] === 'membre')     $nbMembres++;
    elseif ($mb['role_in_struct'] === 'adherent')   $nbAdherent++;
}

// Demandes d'adhésion en attente
$stmtDem = $pdo->prepare(
    "SELECT da.id, da.user_id, da.message, da.created_at,
            u.username, u.nom, u.prenom, u.email
     FROM demandes_adhesion da
     JOIN users u ON u.id = da.user_id
     WHERE da.structure_type = ? AND da.structure_id = ? AND da.statut = 'en_attente'
     ORDER BY da.created_at ASC"
);
$stmtDem->execute([$selType, $selId]);
$demandes = $stmtDem->fetchAll();

// Inscriptions sport en attente (liste d'attente)
$inscriptionsAttente = [];
if ($selType === 'sport') {
    $stmtIA = $pdo->prepare(
        "SELECT is2.id AS insc_id, is2.user_id, is2.statut, is2.created_at,
                u.username, u.nom, u.prenom
         FROM inscriptions_sport is2
         JOIN users u ON u.id = is2.user_id
         WHERE is2.sport_id = ? AND is2.statut = 'liste_attente'
         ORDER BY is2.created_at ASC"
    );
    $stmtIA->execute([$selId]);
    $inscriptionsAttente = $stmtIA->fetchAll();
}

// Nom de la structure sélectionnée
$selNom = '';
foreach ($mesStructures as $ms) {
    if ($ms['type'] === $selType && (int)$ms['id'] === $selId) {
        $selNom = $ms['nom'];
        break;
    }
}
?>

<h1 class="admin-page-title">Membres de la structure</h1>

<?php if (empty($mesStructures)): ?>
  <div class="flash flash--warn">Aucune structure dans ton périmètre de gestion pour l'instant.</div>
<?php else: ?>
  <div class="flash flash--info">
    <strong>Périmètre de gestion</strong> - <?= count($mesStructures) ?> structure<?= count($mesStructures) > 1 ? 's' : '' ?>
    (BDE, BDS, associations et sports rattachés).
  </div>
<?php endif; ?>

<?php if ($msg): ?>
  <div class="flash flash--ok"><?= $msg ?></div>
<?php endif; ?>

<!-- Sélecteur de structure (filtres par type + liste) -->
<?php
  $typeLabels = ['bde' => 'BDE', 'bds' => 'BDS', 'asso' => 'Association', 'sport' => 'Sport'];
  $groups = ['bde' => [], 'bds' => [], 'asso' => [], 'sport' => []];
  foreach ($mesStructures as $ms) {
      $t = $ms['type'];
      if (!isset($groups[$t])) $groups[$t] = [];
      $groups[$t][] = $ms;
  }
  $totalStructs = count($mesStructures);
?>
<div class="admin-card" style="margin-bottom:var(--s6)">
  <div style="display:flex;flex-direction:column;gap:var(--s4)">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:var(--s4);flex-wrap:wrap">
      <h2 style="margin:0;font-size:1rem">Choisir une structure</h2>
      <div style="font-size:.75rem;color:var(--text-muted)"><?= $totalStructs ?> structure<?= $totalStructs > 1 ? 's' : '' ?> au total</div>
    </div>

    <!-- Onglets par type -->
    <div class="ms-tabs" role="tablist" style="display:flex;gap:var(--s2);flex-wrap:wrap">
      <?php
        $tabAll = ['all' => 'Tous'];
        foreach ($groups as $gType => $items) {
            if (!empty($items)) $tabAll[$gType] = $typeLabels[$gType] . ' (' . count($items) . ')';
        }
        // Onglet actif = type sélectionné, sinon "all"
        $activeTab = isset($_GET['tab']) ? $_GET['tab'] : $selType;
        if (!isset($tabAll[$activeTab])) $activeTab = 'all';
        foreach ($tabAll as $k => $label):
          $cls = ($k === $activeTab) ? 'ms-tab ms-tab--active' : 'ms-tab';
      ?>
        <button type="button" class="<?= $cls ?>" data-tab="<?= htmlspecialchars($k) ?>">
          <?= htmlspecialchars($label) ?>
        </button>
      <?php endforeach; ?>
    </div>

    <!-- Champ de recherche -->
    <input type="text" id="ms-search" class="admin-input" placeholder="Rechercher une structure…" autocomplete="off">

    <!-- Liste cliquable -->
    <div class="ms-list" id="ms-list" style="max-height:280px;overflow-y:auto;border:1px solid var(--border);border-radius:var(--r-md)">
      <?php foreach ($mesStructures as $ms):
          $isSel = ($ms['type'] === $selType && (int)$ms['id'] === $selId);
          $href  = 'mes-membres.php?type=' . urlencode($ms['type']) . '&id=' . (int)$ms['id'];
      ?>
        <a href="<?= $href ?>" class="ms-item <?= $isSel ? 'ms-item--active' : '' ?>"
           data-type="<?= htmlspecialchars($ms['type']) ?>"
           data-name="<?= htmlspecialchars(mb_strtolower($ms['nom'])) ?>">
          <span class="ms-item__badge ms-item__badge--<?= htmlspecialchars($ms['type']) ?>"><?= htmlspecialchars($typeLabels[$ms['type']] ?? $ms['type']) ?></span>
          <span class="ms-item__name"><?= htmlspecialchars($ms['nom']) ?></span>
          <?php if ($isSel): ?><span class="ms-item__dot" title="Structure active"></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
      <div class="ms-empty" style="display:none;padding:var(--s4);text-align:center;color:var(--text-muted);font-size:.8rem">
        Aucune structure ne correspond.
      </div>
    </div>
  </div>
</div>

<script>
(function() {
  const tabs = document.querySelectorAll('.ms-tabs .ms-tab');
  const items = document.querySelectorAll('#ms-list .ms-item');
  const empty = document.querySelector('#ms-list .ms-empty');
  const search = document.getElementById('ms-search');
  let activeTab = <?= json_encode($activeTab) ?>;

  function applyFilter() {
    const q = (search.value || '').trim().toLowerCase();
    let visible = 0;
    items.forEach(it => {
      const matchTab  = (activeTab === 'all') || (it.dataset.type === activeTab);
      const matchText = !q || it.dataset.name.indexOf(q) !== -1;
      const show = matchTab && matchText;
      it.style.display = show ? '' : 'none';
      if (show) visible++;
    });
    if (empty) empty.style.display = visible === 0 ? '' : 'none';
  }

  tabs.forEach(btn => {
    btn.addEventListener('click', () => {
      tabs.forEach(b => b.classList.remove('ms-tab--active'));
      btn.classList.add('ms-tab--active');
      activeTab = btn.dataset.tab;
      applyFilter();
    });
  });
  search.addEventListener('input', applyFilter);
  applyFilter();
})();
</script>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--s6)">

  <!-- Colonne gauche : membres actifs -->
  <div>
    <div class="admin-card">
      <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:var(--s3);margin-bottom:var(--s2)">
        <h2 style="margin:0">Membres actifs
          <span class="badge badge--ok" style="font-size:.7rem;font-weight:600;margin-left:.3rem"><?= count($membres) ?></span>
        </h2>
        <div style="display:flex;gap:var(--s2);flex-wrap:wrap">
          <a href="../api/export-membres.php?type=<?= urlencode($selType) ?>&id=<?= $selId ?>&format=xlsx" class="btn btn--ghost btn--sm">⤓ Exporter Excel</a>
          <a href="../api/export-membres.php?type=<?= urlencode($selType) ?>&id=<?= $selId ?>&format=csv" class="btn btn--ghost btn--sm">CSV</a>
        </div>
      </div>
      <p style="font-size:.78rem;color:var(--text-muted);margin:0 0 var(--s3)">
        <strong style="color:#c4b5fd">Bureau</strong> <?= $nbBureau ?>
        · <strong style="color:#7ce0b0">Membre</strong> <?= $nbMembres ?>
        · <strong style="color:var(--text-muted)">Adhérent</strong> <?= $nbAdherent ?>
        <br>
        <small>« Bureau » et « Membre » sont visibles sur la page publique. « Adhérent » ne l'est pas.</small>
      </p>
      <?php if (empty($membres)): ?>
        <p style="color:var(--text-muted);padding:var(--s4) 0">Aucun membre pour l'instant.</p>
      <?php else: ?>
        <table class="admin-table">
          <thead><tr><th>Nom</th><th>Email</th><th>Niveau</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($membres as $mb):
              $nom = trim(($mb['prenom'] ?? '') . ' ' . ($mb['nom'] ?? '')) ?: $mb['username'];
              $role = $mb['role_in_struct'];
              $badgeCls = $role === 'admin' ? 'badge--ok' : ($role === 'membre' ? 'badge--pending' : '');
              $badgeLabel = $role === 'admin' ? 'Bureau' : ($role === 'membre' ? 'Membre' : 'Adhérent');
            ?>
              <tr>
                <td>
                  <strong><?= htmlspecialchars($nom) ?></strong>
                  <?php if ($mb['ecole']): ?><br><small style="color:var(--text-muted)"><?= htmlspecialchars($mb['ecole']) ?></small><?php endif; ?>
                  <?php if ($role !== 'adherent'): ?>
                    <span class="badge <?= $badgeCls ?>" style="font-size:.6rem;margin-left:.4rem;vertical-align:middle"><?= $badgeLabel ?></span>
                  <?php endif; ?>
                </td>
                <td style="font-size:.78rem;color:var(--blue-light)"><?= htmlspecialchars($mb['email']) ?></td>
                <td>
                  <form method="post" class="admin-form change-role-form" style="margin:0;display:flex;gap:.3rem;align-items:center">
                    <input type="hidden" name="action"    value="change_role">
                    <input type="hidden" name="membre_id" value="<?= $mb['membre_id'] ?>">
                    <select name="new_role" data-initial="<?= htmlspecialchars($role) ?>" style="padding:.2rem .5rem;font-size:.75rem">
                      <option value="adherent" <?= $role === 'adherent' ? 'selected' : '' ?>>Adhérent</option>
                      <option value="membre"   <?= $role === 'membre'   ? 'selected' : '' ?>>Membre</option>
                      <option value="admin"    <?= $role === 'admin'    ? 'selected' : '' ?>>Bureau (admin)</option>
                    </select>
                    <button type="submit" class="btn btn--sm change-role-form__save"
                            disabled style="padding:.2rem .5rem;font-size:.72rem;opacity:.5;cursor:not-allowed">✓</button>
                  </form>
                </td>
                <td>
                  <form method="post" onsubmit="return confirm('Retirer ce membre ?')" style="margin:0">
                    <input type="hidden" name="action"    value="retirer">
                    <input type="hidden" name="membre_id" value="<?= $mb['membre_id'] ?>">
                    <button class="btn btn--sm btn--danger">Retirer</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <?php if ($smHasRespCols && !empty($membres)): ?>
        <div style="margin-top:var(--s6);padding-top:var(--s5);border-top:1px solid var(--border)">
          <h3 style="margin:0 0 var(--s2);font-size:.95rem">Responsabilités (cumulables)</h3>
          <p style="font-size:.75rem;color:var(--text-muted);margin:0 0 var(--s4)">
            Hors rôle « Bureau » : accès ciblé au panneau (événements, partenaires, actualités, compta).
            Le bureau de la structure conserve tous les droits.
          </p>
          <form method="post" class="admin-form">
            <input type="hidden" name="action" value="save_all_resp">
            <div style="overflow-x:auto">
              <table class="admin-table" style="font-size:.78rem">
                <thead>
                  <tr>
                    <th>Membre</th>
                    <th title="Événements">Évén.</th>
                    <th title="Partenariats">Part.</th>
                    <th title="Actualités">Comm.</th>
                    <th title="Comptabilité">Trés.</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($membres as $mb):
                    $mid = (int)$mb['membre_id'];
                    $nom = trim(($mb['prenom'] ?? '') . ' ' . ($mb['nom'] ?? '')) ?: $mb['username'];
                    $re  = !empty($mb['resp_evenement']);
                    $rp  = !empty($mb['resp_partenariat']);
                    $rc  = !empty($mb['resp_communication']);
                    $rt  = !empty($mb['resp_tresorerie']);
                    ?>
                    <tr>
                      <td><strong><?= htmlspecialchars($nom) ?></strong></td>
                      <td style="text-align:center">
                        <input type="hidden" name="re[<?= $mid ?>]" value="0">
                        <input type="checkbox" name="re[<?= $mid ?>]" value="1"<?= $re ? ' checked' : '' ?>>
                      </td>
                      <td style="text-align:center">
                        <input type="hidden" name="rp[<?= $mid ?>]" value="0">
                        <input type="checkbox" name="rp[<?= $mid ?>]" value="1"<?= $rp ? ' checked' : '' ?>>
                      </td>
                      <td style="text-align:center">
                        <input type="hidden" name="rc[<?= $mid ?>]" value="0">
                        <input type="checkbox" name="rc[<?= $mid ?>]" value="1"<?= $rc ? ' checked' : '' ?>>
                      </td>
                      <td style="text-align:center">
                        <input type="hidden" name="rt[<?= $mid ?>]" value="0">
                        <input type="checkbox" name="rt[<?= $mid ?>]" value="1"<?= $rt ? ' checked' : '' ?>>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <button type="submit" class="btn btn--primary btn--sm" style="margin-top:var(--s4)">Enregistrer les responsabilités</button>
          </form>
        </div>
      <?php elseif (!$smHasRespCols): ?>
        <p style="font-size:.72rem;color:var(--text-muted);margin-top:var(--s4)">
          Après migration de la base, les cases « responsabilités » (événements, partenariats, communication, trésorerie) apparaîtront ici.
        </p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Colonne droite : demandes + ajout manuel -->
  <div style="display:flex;flex-direction:column;gap:var(--s5)">

    <!-- Demandes en attente -->
    <?php if (!empty($demandes)): ?>
    <div class="admin-card">
      <h2>Demandes d'adhésion <span class="badge badge--pending" style="font-size:.7rem;font-weight:600;margin-left:.3rem"><?= count($demandes) ?></span></h2>
      <?php foreach ($demandes as $d): ?>
        <?php $dNom = trim(($d['prenom'] ?? '') . ' ' . ($d['nom'] ?? '')) ?: $d['username']; ?>
        <div style="padding:var(--s3) 0;border-bottom:1px solid var(--border)">
          <strong><?= htmlspecialchars($dNom) ?></strong>
          <span style="font-size:.75rem;color:var(--text-muted)"> · <?= htmlspecialchars($d['email']) ?></span>
          <?php if ($d['message']): ?>
            <p style="font-size:.8rem;margin:.4rem 0;color:var(--blue-light)">"<?= htmlspecialchars($d['message']) ?>"</p>
          <?php endif; ?>
          <div class="actions" style="margin-top:.5rem">
            <form method="post" style="margin:0">
              <input type="hidden" name="action"     value="valider_adhesion">
              <input type="hidden" name="demande_id" value="<?= $d['id'] ?>">
              <button class="btn btn--sm btn--success">Accepter</button>
            </form>
            <form method="post" style="margin:0">
              <input type="hidden" name="action"     value="refuser_adhesion">
              <input type="hidden" name="demande_id" value="<?= $d['id'] ?>">
              <button class="btn btn--sm btn--danger">Refuser</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Liste d'attente sport -->
    <?php if (!empty($inscriptionsAttente)): ?>
    <div class="admin-card">
      <h2>Liste d'attente sport <span class="badge badge--pending" style="font-size:.7rem;font-weight:600;margin-left:.3rem"><?= count($inscriptionsAttente) ?></span></h2>
      <?php foreach ($inscriptionsAttente as $ia): ?>
        <?php $iaNom = trim(($ia['prenom'] ?? '') . ' ' . ($ia['nom'] ?? '')) ?: $ia['username']; ?>
        <div style="padding:var(--s3) 0;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
          <span><?= htmlspecialchars($iaNom) ?></span>
          <form method="post" style="margin:0">
            <input type="hidden" name="action"  value="valider_insc_sport">
            <input type="hidden" name="insc_id" value="<?= $ia['insc_id'] ?>">
            <button class="btn btn--sm btn--success">Confirmer</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Ajouter un membre manuellement -->
    <div class="admin-card">
      <h2>Ajouter un membre</h2>
      <form method="post" class="admin-form">
        <input type="hidden" name="action" value="ajouter">
        <div class="form-row">
          <div class="form-col">
            <label>Email ou identifiant</label>
            <input type="text" name="email" required placeholder="prenom.nom@ecole.fr">
          </div>
          <div class="form-col" style="flex:0;min-width:180px">
            <label>Niveau</label>
            <select name="role_add">
              <option value="adherent">Adhérent (privé)</option>
              <option value="membre" selected>Membre (visible)</option>
              <option value="admin">Bureau / admin</option>
            </select>
          </div>
        </div>
        <button type="submit" class="btn btn--primary">Ajouter →</button>
      </form>
    </div>

  </div>
</div>

<script>
// Niveau membre dans une structure : on évite onchange=submit (déclenchements
// fantômes sur restauration BFCache / navigation clavier). Bouton Valider
// explicite, désactivé tant que la valeur n'a pas changé.
document.querySelectorAll('.change-role-form').forEach(form => {
  const sel = form.querySelector('select[name="new_role"]');
  const btn = form.querySelector('.change-role-form__save');
  if (!sel || !btn) return;
  const initial = sel.dataset.initial;
  const sync = () => {
    const changed = sel.value !== initial;
    btn.disabled = !changed;
    btn.style.opacity = changed ? '1' : '.5';
    btn.style.cursor  = changed ? 'pointer' : 'not-allowed';
  };
  sel.addEventListener('change', sync);
  form.addEventListener('submit', (e) => {
    if (sel.value === initial) { e.preventDefault(); }
  });
  sync();
});
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
