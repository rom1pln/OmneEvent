<?php
// comptabilité d'une structure - transactions, comptes, catégories
// Affichage des erreurs (temporaire - retire `display_errors` une fois stable en prod).
// Sur InfinityFree/42web.io, un 500 répété peut déclencher leur "surge protection" :
// si tu vois "ne peut pas traiter cette demande pour le moment", attends 1-2 min entre 2 tests.
@ini_set('display_errors', '1');
@ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// On installe un gestionnaire d'erreurs global avant tout require pour ne plus jamais avoir un écran blanc / 500 muet
set_exception_handler(function (Throwable $e) {
    while (ob_get_level() > 0) { @ob_end_clean(); }
    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><title>Erreur - Comptabilité</title>';
    echo '<style>body{font-family:system-ui,sans-serif;background:#120822;color:#f3eaff;padding:32px;max-width:920px;margin:auto}'
       . 'pre{background:#1d0f30;padding:16px;border-radius:8px;overflow:auto}.c{color:#ff8a8a}</style>';
    echo '<h1>Erreur côté serveur - admin/comptabilite.php</h1>';
    echo '<p class="c">' . htmlspecialchars(get_class($e) . ' : ' . $e->getMessage()) . '</p>';
    echo '<p><strong>Fichier :</strong> ' . htmlspecialchars($e->getFile()) . ' <strong>ligne</strong> ' . (int)$e->getLine() . '</p>';
    echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    echo '<p><a href="../admin/index.php" style="color:#c79bff">← Retour au panneau</a></p>';
    exit;
});

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/comptabilite.php';
require_once __DIR__ . '/../includes/notes-frais.php';
require_once __DIR__ . '/../includes/spreadsheet-export.php';

$adminTitle = 'Comptabilité';
$adminPage  = 'comptabilite';

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
requireBureau();
$userId = (int)($_SESSION['user_id'] ?? 0);

function dbHasTableCompta(PDO $pdo, string $table): bool {
    try {
        $st = $pdo->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1");
        $st->execute([$table]);
        return (bool)$st->fetchColumn();
    } catch (Throwable $e) { return false; }
}
$comptaReady = dbHasTableCompta($pdo, 'compta_transactions')
            && dbHasTableCompta($pdo, 'compta_comptes')
            && dbHasTableCompta($pdo, 'compta_categories');

// Si la migration n'a pas été appliquée → on charge le header et on stoppe ici proprement
if (!$comptaReady) {
    require_once __DIR__ . '/includes/admin-header.php';
    echo '<h1 class="admin-page-title">Comptabilité</h1>';
    echo '<div class="flash flash--warn" style="margin-bottom:var(--s4)">';
    echo '<strong>Tables comptabilité absentes.</strong> Avant d\'utiliser cette page, applique les migrations DB '
       . '(<code>tbl_compta_comptes</code>, <code>tbl_compta_categories</code>, <code>tbl_compta_transactions</code>).';
    echo '</div>';
    echo '<a href="migrate.php" class="btn btn--primary">→ Aller sur Migrations DB</a>';
    require_once __DIR__ . '/includes/admin-footer.php';
    exit;
}


// on construit la liste des structures accessibles
$mesStructures = [];
$seen = [];
$addStruct = function(string $type, int $id, string $nom = '', string $slug = '') use (&$mesStructures, &$seen) {
    $key = $type . ':' . $id;
    if (isset($seen[$key])) return;
    $seen[$key] = true;
    $mesStructures[] = ['type' => $type, 'id' => $id, 'nom' => $nom, 'slug' => $slug];
};

if (isAdminCorpo()) {
    $rows = $pdo->query("SELECT id, nom, slug, type FROM associations ORDER BY type, nom")->fetchAll();
    foreach ($rows as $r) {
        $assoType = strtolower((string)($r['type'] ?? ''));
        $intType  = ($assoType === 'bde') ? 'bde' : (($assoType === 'bds') ? 'bds' : 'asso');
        $addStruct($intType, (int)$r['id'], (string)$r['nom'], (string)($r['slug'] ?? ''));
    }
    $rows = $pdo->query("SELECT id, nom, slug FROM sports ORDER BY nom")->fetchAll();
    foreach ($rows as $r) $addStruct('sport', (int)$r['id'], $r['nom'], $r['slug'] ?? '');
} else {
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
    $assoIds = getManagedAssoIds($pdo);
    if (!empty($assoIds)) {
        $pl = implode(',', array_map('intval', $assoIds));
        $rows = $pdo->query("SELECT id, nom, slug, type FROM associations WHERE id IN ($pl) ORDER BY type, nom")->fetchAll();
        foreach ($rows as $r) {
            $assoType = strtolower((string)($r['type'] ?? ''));
            $intType  = ($assoType === 'bde') ? 'bde' : (($assoType === 'bds') ? 'bds' : 'asso');
            $addStruct($intType, (int)$r['id'], (string)$r['nom'], (string)($r['slug'] ?? ''));
        }
    }
    $sportIds = getManagedSportIds($pdo);
    if (!empty($sportIds)) {
        $pl = implode(',', array_map('intval', $sportIds));
        $rows = $pdo->query("SELECT id, nom, slug FROM sports WHERE id IN ($pl) ORDER BY nom")->fetchAll();
        foreach ($rows as $r) {
            $addStruct('sport', (int)$r['id'], (string)$r['nom'], (string)($r['slug'] ?? ''));
        }
    }
    foreach (getExplicitDelegatedStructures('tresorerie') as $d) {
        if ($d['type'] === 'sport') {
            $st1 = $pdo->prepare('SELECT id, nom, slug FROM sports WHERE id = ?');
            $st1->execute([$d['id']]);
            if ($r = $st1->fetch(PDO::FETCH_ASSOC)) {
                $addStruct('sport', (int)$r['id'], (string)$r['nom'], (string)($r['slug'] ?? ''));
            }
        } else {
            $st1 = $pdo->prepare('SELECT id, nom, slug, type FROM associations WHERE id = ?');
            $st1->execute([$d['id']]);
            if ($r = $st1->fetch(PDO::FETCH_ASSOC)) {
                $assoType = strtolower((string)($r['type'] ?? ''));
                $intType  = ($assoType === 'bde') ? 'bde' : (($assoType === 'bds') ? 'bds' : 'asso');
                $addStruct($intType, (int)$r['id'], (string)$r['nom'], (string)($r['slug'] ?? ''));
            }
        }
    }
}

usort($mesStructures, function ($a, $b) {
    $order = ['bde' => 0, 'bds' => 1, 'asso' => 2, 'sport' => 3];
    $ra = $order[$a['type']] ?? 9;
    $rb = $order[$b['type']] ?? 9;
    if ($ra !== $rb) return $ra <=> $rb;
    return strcmp((string)$a['nom'], (string)$b['nom']);
});

$selType = $_GET['type'] ?? ($mesStructures[0]['type'] ?? 'asso');
$selId   = (int)($_GET['id'] ?? ($mesStructures[0]['id'] ?? 0));

if ($selType === 'sport') {
    $canManage = isAdminCorpo() || canManageSport($selId, $pdo)
        || canManageStructureResource($pdo, 'sport', $selId, 'tresorerie');
} else {
    $canManage = isAdminCorpo() || canManageAsso($selId, $pdo)
        || canManageBDE($selId, $pdo) || canManageBDS($selId, $pdo)
        || canManageStructureResource($pdo, 'asso', $selId, 'tresorerie');
    if (!$canManage) {
        foreach ($mesStructures as $ms) {
            if ($ms['type'] === $selType && (int)$ms['id'] === $selId) { $canManage = true; break; }
        }
    }
}
if (!$canManage) {
    if (!empty($mesStructures)) {
        header('Location: comptabilite.php?type=' . urlencode($mesStructures[0]['type']) . '&id=' . $mesStructures[0]['id']);
    } else {
        header('Location: index.php');
    }
    exit;
}

// actions POST - après traitement on redirige toujours
$flash = '';
$flashType = 'ok';

function compta_redirect(string $type, int $id, string $tab = '', string $msg = '', string $kind = 'ok'): void {
    $u = "comptabilite.php?type=" . urlencode($type) . "&id=" . $id;
    if ($tab !== '')  $u .= "&tab=" . urlencode($tab);
    if ($msg !== '')  $u .= "&msg=" . urlencode($msg) . "&kind=" . urlencode($kind);
    header("Location: $u");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    // ajout ou modif d'une transaction
    if ($act === 'tx_save') {
        $txId        = (int)($_POST['tx_id'] ?? 0);
        $type        = in_array($_POST['type'] ?? '', ['recette','depense'], true) ? $_POST['type'] : 'depense';
        $montant     = max(0, (float)str_replace(',', '.', $_POST['montant'] ?? '0'));
        $dateOp      = $_POST['date_operation'] ?? date('Y-m-d');
        $libelle     = trim($_POST['libelle'] ?? '');
        $notes       = trim($_POST['notes'] ?? '');
        $reference   = trim($_POST['reference'] ?? '');
        $mode        = in_array($_POST['mode_paiement'] ?? '', ['especes','carte','virement','cheque','prelevement','autre'], true) ? $_POST['mode_paiement'] : 'virement';
        $compteId    = (int)($_POST['compte_id'] ?? 0) ?: null;
        $categorieId = (int)($_POST['categorie_id'] ?? 0) ?: null;
        $evtId       = (int)($_POST['evenement_id'] ?? 0) ?: null;

        if ($libelle === '' || $montant <= 0) {
            compta_redirect($selType, $selId, 'transactions', 'Libellé et montant > 0 requis.', 'err');
        }

        if ($txId > 0) {
            $stmt = $pdo->prepare(
                "UPDATE compta_transactions
                 SET type=?, montant=?, date_operation=?, libelle=?, notes=?, reference=?, mode_paiement=?,
                     compte_id=?, categorie_id=?, evenement_id=?
                 WHERE id=? AND structure_type=? AND structure_id=?"
            );
            $stmt->execute([$type, $montant, $dateOp, $libelle, $notes ?: null, $reference ?: null, $mode,
                            $compteId, $categorieId, $evtId, $txId, $selType, $selId]);
            compta_redirect($selType, $selId, 'transactions', 'Transaction mise à jour.');
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO compta_transactions
                 (structure_type, structure_id, compte_id, categorie_id, evenement_id,
                  type, montant, date_operation, libelle, notes, reference, mode_paiement, cree_par)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$selType, $selId, $compteId, $categorieId, $evtId,
                            $type, $montant, $dateOp, $libelle, $notes ?: null, $reference ?: null, $mode, $userId]);
            compta_redirect($selType, $selId, 'transactions', 'Transaction ajoutée.');
        }
    }

    if ($act === 'tx_delete') {
        $txId = (int)($_POST['tx_id'] ?? 0);
        $pdo->prepare("DELETE FROM compta_transactions WHERE id=? AND structure_type=? AND structure_id=?")
            ->execute([$txId, $selType, $selId]);
        compta_redirect($selType, $selId, 'transactions', 'Transaction supprimée.');
    }

    // gestion des comptes
    if ($act === 'compte_save') {
        $cid       = (int)($_POST['compte_id'] ?? 0);
        $nom       = trim($_POST['nom'] ?? '');
        $cType     = in_array($_POST['ctype'] ?? '', ['caisse','banque','autre'], true) ? $_POST['ctype'] : 'banque';
        $iban      = trim($_POST['iban'] ?? '');
        $soldeInit = (float)str_replace(',', '.', $_POST['solde_initial'] ?? '0');
        if ($nom === '') compta_redirect($selType, $selId, 'comptes', 'Le nom du compte est requis.', 'err');

        if ($cid > 0) {
            $pdo->prepare("UPDATE compta_comptes SET nom=?, type=?, iban=?, solde_initial=? WHERE id=? AND structure_type=? AND structure_id=?")
                ->execute([$nom, $cType, $iban ?: null, $soldeInit, $cid, $selType, $selId]);
            compta_redirect($selType, $selId, 'comptes', 'Compte mis à jour.');
        } else {
            $pdo->prepare("INSERT INTO compta_comptes (structure_type, structure_id, nom, type, iban, solde_initial) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([$selType, $selId, $nom, $cType, $iban ?: null, $soldeInit]);
            compta_redirect($selType, $selId, 'comptes', 'Compte créé.');
        }
    }
    if ($act === 'compte_archive') {
        $cid = (int)($_POST['compte_id'] ?? 0);
        $pdo->prepare("UPDATE compta_comptes SET archive = 1 - archive WHERE id=? AND structure_type=? AND structure_id=?")
            ->execute([$cid, $selType, $selId]);
        compta_redirect($selType, $selId, 'comptes', 'Statut du compte modifié.');
    }

    // gestion des catégories
    if ($act === 'cat_save') {
        $cid     = (int)($_POST['cat_id'] ?? 0);
        $nom     = trim($_POST['nom'] ?? '');
        $type    = in_array($_POST['cattype'] ?? '', ['recette','depense'], true) ? $_POST['cattype'] : 'depense';
        $couleur = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['couleur'] ?? '') ? $_POST['couleur'] : '#5D0282';
        if ($nom === '') compta_redirect($selType, $selId, 'categories', 'Le nom de catégorie est requis.', 'err');

        if ($cid > 0) {
            $pdo->prepare("UPDATE compta_categories SET nom=?, type=?, couleur=? WHERE id=? AND structure_type=? AND structure_id=?")
                ->execute([$nom, $type, $couleur, $cid, $selType, $selId]);
            compta_redirect($selType, $selId, 'categories', 'Catégorie mise à jour.');
        } else {
            $pdo->prepare("INSERT INTO compta_categories (structure_type, structure_id, nom, type, couleur) VALUES (?, ?, ?, ?, ?)")
                ->execute([$selType, $selId, $nom, $type, $couleur]);
            compta_redirect($selType, $selId, 'categories', 'Catégorie créée.');
        }
    }
    if ($act === 'cat_archive') {
        $cid = (int)($_POST['cat_id'] ?? 0);
        $pdo->prepare("UPDATE compta_categories SET archive = 1 - archive WHERE id=? AND structure_type=? AND structure_id=?")
            ->execute([$cid, $selType, $selId]);
        compta_redirect($selType, $selId, 'categories', 'Catégorie modifiée.');
    }

    // saisie rapide d'une transaction
    if ($act === 'tx_quick') {
        $type    = in_array($_POST['type'] ?? '', ['recette', 'depense'], true) ? $_POST['type'] : 'depense';
        $montant = max(0, (float)str_replace(',', '.', $_POST['montant'] ?? '0'));
        $libelle = trim($_POST['libelle'] ?? '');
        if ($libelle === '' || $montant <= 0) {
            compta_redirect($selType, $selId, 'transactions', 'Montant et libellé requis pour la saisie rapide.', 'err');
        }
        $compteId = compta_get_default_compte_id($pdo, $selType, $selId);
        $catNom   = $type === 'recette' ? 'Autres recettes' : 'Autres dépenses';
        $catId    = compta_find_category_id($pdo, $catNom, $type);
        $cols     = 'structure_type, structure_id, compte_id, categorie_id, type, montant, date_operation, libelle, mode_paiement, cree_par';
        $vals     = '?, ?, ?, ?, ?, ?, ?, ?, ?, ?';
        $params   = [$selType, $selId, $compteId, $catId, $type, $montant, date('Y-m-d'), $libelle, 'autre', $userId];
        if (compta_has_source_columns($pdo)) {
            $cols .= ', source_type';
            $vals .= ", 'manuel'";
        }
        $pdo->prepare("INSERT INTO compta_transactions ($cols) VALUES ($vals)")->execute($params);
        compta_redirect($selType, $selId, 'transactions', 'Écriture enregistrée.');
    }

    // import des paiements billetterie/boutique vers la compta
    if ($act === 'sync_billet') {
        $pid = (int)($_POST['paiement_id'] ?? 0);
        $r   = compta_import_billetterie($pdo, $pid, $userId);
        compta_redirect($selType, $selId, 'encaissements', $r['msg'] ?? ($r['ok'] ? 'OK' : 'Erreur'), $r['ok'] ? 'ok' : 'err');
    }
    if ($act === 'sync_boutique_ligne') {
        $lid = (int)($_POST['ligne_id'] ?? 0);
        $r   = compta_import_boutique_ligne($pdo, $lid, $userId);
        compta_redirect($selType, $selId, 'encaissements', $r['msg'] ?? ($r['ok'] ? 'OK' : 'Erreur'), $r['ok'] ? 'ok' : 'err');
    }
    if ($act === 'sync_all') {
        $batch = compta_import_all_pending($pdo, $selType, $selId, $userId);
        $msg   = $batch['imported'] . ' importée(s)';
        if ($batch['skipped'] > 0) {
            $msg .= ', ' . $batch['skipped'] . ' déjà présente(s)';
        }
        if (!empty($batch['errors'])) {
            $msg .= ' - ' . count($batch['errors']) . ' erreur(s)';
        }
        compta_redirect($selType, $selId, 'encaissements', $msg, empty($batch['errors']) ? 'ok' : 'err');
    }

    if ($act === 'nf_super_validate') {
        $noteId  = (int)($_POST['note_id'] ?? 0);
        $comment = trim((string)($_POST['commentaire'] ?? $_POST['commentaire_tresorier'] ?? ''));
        $r       = nf_super_validate_and_book($pdo, $noteId, $userId, $comment !== '' ? $comment : null);
        compta_redirect($selType, $selId, 'notes_frais', $r['msg'] ?? ($r['ok'] ? 'OK' : 'Erreur'), $r['ok'] ? 'ok' : 'err');
    }
    if ($act === 'nf_approve_bureau') {
        $noteId  = (int)($_POST['note_id'] ?? 0);
        $comment = trim((string)($_POST['commentaire_bureau'] ?? ''));
        $r       = nf_approve_bureau($pdo, $noteId, $userId, $comment !== '' ? $comment : null);
        compta_redirect($selType, $selId, 'notes_frais', $r['msg'] ?? ($r['ok'] ? 'OK' : 'Erreur'), $r['ok'] ? 'ok' : 'err');
    }
    if ($act === 'nf_approve_treso') {
        $noteId  = (int)($_POST['note_id'] ?? 0);
        $comment = trim((string)($_POST['commentaire_tresorier'] ?? ''));
        $r       = nf_approve_treso_and_book($pdo, $noteId, $userId, $comment !== '' ? $comment : null);
        compta_redirect($selType, $selId, 'notes_frais', $r['msg'] ?? ($r['ok'] ? 'OK' : 'Erreur'), $r['ok'] ? 'ok' : 'err');
    }
    if ($act === 'nf_approve') {
        $noteId  = (int)($_POST['note_id'] ?? 0);
        $comment = trim((string)($_POST['commentaire_tresorier'] ?? ''));
        $r       = nf_approve_treso_and_book($pdo, $noteId, $userId, $comment !== '' ? $comment : null);
        compta_redirect($selType, $selId, 'notes_frais', $r['msg'] ?? ($r['ok'] ? 'OK' : 'Erreur'), $r['ok'] ? 'ok' : 'err');
    }
    if ($act === 'nf_reject') {
        $noteId  = (int)($_POST['note_id'] ?? 0);
        $comment = trim((string)($_POST['commentaire_refus'] ?? $_POST['commentaire_tresorier'] ?? ''));
        $r       = nf_reject($pdo, $noteId, $userId, $comment);
        compta_redirect($selType, $selId, 'notes_frais', $r['msg'] ?? ($r['ok'] ? 'OK' : 'Erreur'), $r['ok'] ? 'ok' : 'err');
    }
}

if (isset($_GET['msg'])) {
    $flash = htmlspecialchars($_GET['msg']);
    $flashType = ($_GET['kind'] ?? 'ok') === 'err' ? 'err' : 'ok';
}

// filtres et chargement des données pour la vue
$activeTab = $_GET['tab'] ?? 'dashboard';
if (!in_array($activeTab, ['dashboard','encaissements','transactions','comptes','categories','notes_frais'], true)) {
    $activeTab = 'dashboard';
}

$comptaSourceReady = compta_has_source_columns($pdo);
$encaissementsSum  = $comptaSourceReady ? compta_encaissements_summary($pdo, $selType, $selId) : null;
$pendingBillet     = $comptaSourceReady ? compta_pending_billetterie($pdo, $selType, $selId) : [];
$pendingBoutique   = $comptaSourceReady ? compta_pending_boutique_lignes($pdo, $selType, $selId) : [];
$pendingTotal      = count($pendingBillet) + count($pendingBoutique);
$nfPendingCount    = nf_table_ready($pdo) ? nf_count_pending($pdo, $selType, $selId) : 0;

// Filtres période
$fDateFrom = $_GET['from'] ?? '';
$fDateTo   = $_GET['to']   ?? '';
if ($fDateFrom === '' && $fDateTo === '' && !empty($_GET['period'])) {
    match ((string)$_GET['period']) {
        'month' => [$fDateFrom = date('Y-m-01'), $fDateTo = date('Y-m-d')],
        '30d'   => [$fDateFrom = date('Y-m-d', strtotime('-30 days')), $fDateTo = date('Y-m-d')],
        'year'  => [$fDateFrom = date('Y-01-01'), $fDateTo = date('Y-m-d')],
        default => null,
    };
}
$fType     = $_GET['ftype']     ?? '';   // recette | depense | ''
$fCat      = (int)($_GET['fcat']     ?? 0);
$fCompte   = (int)($_GET['fcompte']  ?? 0);
$fEvt      = (int)($_GET['fevt']     ?? 0);
$fSearch   = trim($_GET['q'] ?? '');

// Comptes de la structure
$stmt = $pdo->prepare("SELECT * FROM compta_comptes WHERE structure_type=? AND structure_id=? ORDER BY archive ASC, nom ASC");
$stmt->execute([$selType, $selId]);
$comptes = $stmt->fetchAll();

// Catégories : globales (structure_type IS NULL) + spécifiques à la structure
$stmt = $pdo->prepare(
    "SELECT * FROM compta_categories
     WHERE archive = 0
       AND (
         structure_type IS NULL
         OR (structure_type = ? AND structure_id = ?)
       )
     ORDER BY type, nom"
);
$stmt->execute([$selType, $selId]);
$categories = $stmt->fetchAll();

// Catégories spécifiques (pour onglet "Gestion")
$stmt = $pdo->prepare("SELECT * FROM compta_categories WHERE structure_type=? AND structure_id=? ORDER BY archive, type, nom");
$stmt->execute([$selType, $selId]);
$categoriesStruct = $stmt->fetchAll();

// Événements rattachés à la structure
// → la table evenements.structure_type vaut 'asso' pour les assos/BDE/BDS
//   (puisqu'ils partagent la table `associations`). Pour les sports, c'est 'sport'.
$evtStructType = ($selType === 'sport') ? 'sport' : 'asso';
try {
    $stmt = $pdo->prepare(
        "SELECT id, titre, date FROM evenements
         WHERE structure_type = ? AND structure_id = ?
         ORDER BY date DESC LIMIT 300"
    );
    $stmt->execute([$evtStructType, $selId]);
    $evenementsStruct = $stmt->fetchAll();
} catch (Throwable $e) { $evenementsStruct = []; }

// Construire les filtres pour les requêtes transactions.
// On qualifie systématiquement avec l'alias `t.` car ces colonnes existent aussi
// dans `compta_comptes` et `compta_categories` (ambiguïté en JOIN).
$where      = "t.structure_type = ? AND t.structure_id = ?";
$whereNoT   = "structure_type = ? AND structure_id = ?"; // pour les requêtes sans JOIN
$params     = [$selType, $selId];
if ($fDateFrom !== '') { $where .= " AND t.date_operation >= ?"; $whereNoT .= " AND date_operation >= ?"; $params[] = $fDateFrom; }
if ($fDateTo   !== '') { $where .= " AND t.date_operation <= ?"; $whereNoT .= " AND date_operation <= ?"; $params[] = $fDateTo; }
if ($fType === 'recette' || $fType === 'depense') { $where .= " AND t.type = ?"; $whereNoT .= " AND type = ?"; $params[] = $fType; }
if ($fCat > 0)    { $where .= " AND t.categorie_id = ?"; $whereNoT .= " AND categorie_id = ?"; $params[] = $fCat; }
if ($fCompte > 0) { $where .= " AND t.compte_id = ?";    $whereNoT .= " AND compte_id = ?";   $params[] = $fCompte; }
if ($fEvt > 0)    { $where .= " AND t.evenement_id = ?"; $whereNoT .= " AND evenement_id = ?"; $params[] = $fEvt; }
if ($fSearch !== '') {
    $where    .= " AND (t.libelle LIKE ? OR t.reference LIKE ? OR t.notes LIKE ?)";
    $whereNoT .= " AND (libelle LIKE ? OR reference LIKE ? OR notes LIKE ?)";
    $like = '%' . $fSearch . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}

// Export Excel / CSV (transactions filtrées)
if (isset($_GET['export']) && in_array($_GET['export'], ['csv', 'xlsx'], true)) {
    $exportFormat = (string)$_GET['export'];
    $stmt = $pdo->prepare(
        "SELECT t.date_operation, t.type, t.montant, t.libelle, t.reference, t.mode_paiement,
                c.nom AS compte_nom, cat.nom AS cat_nom, e.titre AS evt_titre, u.username AS auteur,
                t.notes
         FROM compta_transactions t
         LEFT JOIN compta_comptes c    ON c.id = t.compte_id
         LEFT JOIN compta_categories cat ON cat.id = t.categorie_id
         LEFT JOIN evenements e        ON e.id = t.evenement_id
         LEFT JOIN users u             ON u.id = t.cree_par
         WHERE $where
         ORDER BY t.date_operation DESC, t.id DESC"
    );
    $stmt->execute($params);
    $dbRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $headers = ['Date', 'Type', 'Montant (€)', 'Libellé', 'Référence', 'Mode', 'Compte', 'Catégorie', 'Événement', 'Auteur', 'Notes'];
    $rows = [];
    foreach ($dbRows as $r) {
        $montant = ($r['type'] === 'depense' ? -1 : 1) * (float)$r['montant'];
        $rows[] = [
            $r['date_operation'],
            corpo_spreadsheet_tx_type_label((string)$r['type']),
            number_format($montant, 2, '.', ''),
            $r['libelle'],
            $r['reference'],
            $r['mode_paiement'],
            $r['compte_nom'],
            $r['cat_nom'],
            $r['evt_titre'],
            $r['auteur'],
            $r['notes'],
        ];
    }
    $basename = 'comptabilite_' . $selType . '_' . $selId . '_' . date('Ymd_His');
    corpo_spreadsheet_send($basename, $headers, $rows, $exportFormat);
}

// Transactions paginées
$page    = max(1, (int)($_GET['p'] ?? 1));
$perPage = 50;
$offset  = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM compta_transactions t WHERE $where");
$stmt->execute($params);
$txTotal = (int)$stmt->fetchColumn();
$pageMax = max(1, (int)ceil($txTotal / $perPage));

$joinBoutique = $comptaSourceReady
    ? ' LEFT JOIN boutique_commande_lignes bl ON t.source_type = \'boutique\' AND bl.id = t.source_id'
    : '';
$stmt = $pdo->prepare(
    "SELECT t.*,
            c.nom AS compte_nom, c.type AS compte_type,
            cat.nom AS cat_nom, cat.couleur AS cat_couleur,
            e.titre AS evt_titre, e.slug AS evt_slug,
            u.username AS auteur" . ($comptaSourceReady ? ', bl.commande_id AS boutique_commande_id' : '') . "
     FROM compta_transactions t
     LEFT JOIN compta_comptes c    ON c.id = t.compte_id
     LEFT JOIN compta_categories cat ON cat.id = t.categorie_id
     LEFT JOIN evenements e        ON e.id = t.evenement_id
     LEFT JOIN users u             ON u.id = t.cree_par
     $joinBoutique
     WHERE $where
     ORDER BY t.date_operation DESC, t.id DESC
     LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// KPIs : soldes par compte (utilise tous les txs, pas seulement filtrés)
$stmtKpi = $pdo->prepare(
    "SELECT compte_id,
            SUM(CASE WHEN type='recette' THEN montant ELSE 0 END) AS recettes,
            SUM(CASE WHEN type='depense' THEN montant ELSE 0 END) AS depenses
     FROM compta_transactions
     WHERE structure_type=? AND structure_id=?
     GROUP BY compte_id"
);
$stmtKpi->execute([$selType, $selId]);
$soldesParCompte = [];
foreach ($stmtKpi->fetchAll() as $r) $soldesParCompte[(int)$r['compte_id']] = $r;

$soldeTotal = 0.0;
$totalRecettes = 0.0;
$totalDepenses = 0.0;
foreach ($comptes as $c) {
    $cId = (int)$c['id'];
    $rec = (float)($soldesParCompte[$cId]['recettes'] ?? 0);
    $dep = (float)($soldesParCompte[$cId]['depenses'] ?? 0);
    $soldeTotal    += (float)$c['solde_initial'] + $rec - $dep;
    $totalRecettes += $rec;
    $totalDepenses += $dep;
}
$resultatNet = $totalRecettes - $totalDepenses;

// 30 derniers jours
$stmt = $pdo->prepare(
    "SELECT
       SUM(CASE WHEN type='recette' THEN montant ELSE 0 END) AS recettes,
       SUM(CASE WHEN type='depense' THEN montant ELSE 0 END) AS depenses
     FROM compta_transactions
     WHERE structure_type=? AND structure_id=? AND date_operation >= ?"
);
$stmt->execute([$selType, $selId, date('Y-m-d', strtotime('-30 days'))]);
$r30 = $stmt->fetch() ?: ['recettes' => 0, 'depenses' => 0];

// Camembert : répartition dépenses par catégorie (12 mois glissants)
$stmt = $pdo->prepare(
    "SELECT COALESCE(cat.nom,'(non classé)') AS nom,
            COALESCE(cat.couleur,'#5D0282') AS couleur,
            SUM(t.montant) AS total
     FROM compta_transactions t
     LEFT JOIN compta_categories cat ON cat.id = t.categorie_id
     WHERE t.structure_type=? AND t.structure_id=? AND t.type='depense' AND t.date_operation >= ?
     GROUP BY cat.id
     ORDER BY total DESC
     LIMIT 8"
);
$stmt->execute([$selType, $selId, date('Y-m-d', strtotime('-12 months'))]);
$depParCat = $stmt->fetchAll();
$depParCatTotal = array_sum(array_map(fn($r) => (float)$r['total'], $depParCat));

// Évolution mensuelle (12 derniers mois)
$stmt = $pdo->prepare(
    "SELECT DATE_FORMAT(date_operation, '%Y-%m') AS ym,
            SUM(CASE WHEN type='recette' THEN montant ELSE 0 END) AS recettes,
            SUM(CASE WHEN type='depense' THEN montant ELSE 0 END) AS depenses
     FROM compta_transactions
     WHERE structure_type=? AND structure_id=? AND date_operation >= ?
     GROUP BY ym ORDER BY ym ASC"
);
$stmt->execute([$selType, $selId, date('Y-m-01', strtotime('-11 months'))]);
$evolMois = $stmt->fetchAll();
// Préremplir les mois vides
$months = [];
for ($i = 11; $i >= 0; $i--) {
    $months[date('Y-m', strtotime("-$i months"))] = ['recettes' => 0, 'depenses' => 0];
}
foreach ($evolMois as $r) {
    if (isset($months[$r['ym']])) $months[$r['ym']] = ['recettes' => (float)$r['recettes'], 'depenses' => (float)$r['depenses']];
}
$maxMonth = 0;
foreach ($months as $m) { $maxMonth = max($maxMonth, $m['recettes'], $m['depenses']); }
$maxMonth = $maxMonth > 0 ? $maxMonth : 1;

// Nom de la structure
$selNom = '';
foreach ($mesStructures as $ms) {
    if ($ms['type'] === $selType && (int)$ms['id'] === $selId) { $selNom = $ms['nom']; break; }
}

// Helpers
function fmt_euro($v): string { return number_format((float)$v, 2, ',', ' ') . ' €'; }
$typeLabels = ['bde' => 'BDE', 'bds' => 'BDS', 'asso' => 'Association', 'sport' => 'Sport'];

// Pour pré-remplir le formulaire d'édition
$editTx = null;
if (!empty($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM compta_transactions WHERE id=? AND structure_type=? AND structure_id=?");
    $stmt->execute([(int)$_GET['edit'], $selType, $selId]);
    $editTx = $stmt->fetch() ?: null;
}

// rendu HTML
require_once __DIR__ . '/includes/admin-header.php';
?>

<h1 class="admin-page-title">
  Comptabilité
  <span style="font-size:.85rem;color:var(--text-muted);font-weight:400">- <?= htmlspecialchars($selNom ?: '-') ?></span>
</h1>

<?php if ($flash): ?>
  <div class="flash flash--<?= $flashType === 'err' ? 'err' : 'ok' ?>"><?= $flash ?></div>
<?php endif; ?>

<?php
// Sélecteur de structure (même composant que mes-membres.php)
$groups = ['bde' => [], 'bds' => [], 'asso' => [], 'sport' => []];
foreach ($mesStructures as $ms) { $groups[$ms['type']][] = $ms; }
$totalStructs = count($mesStructures);
?>
<?php if (empty($mesStructures)): ?>
  <div class="flash flash--warn">Aucune structure dans ton périmètre de gestion. Demande à un admin Corpo de t'attribuer un rôle Bureau.</div>
  <?php require_once 'includes/admin-footer.php'; return; ?>
<?php endif; ?>

<div class="admin-card" style="margin-bottom:var(--s6)">
  <div style="display:flex;flex-direction:column;gap:var(--s4)">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:var(--s4);flex-wrap:wrap">
      <h2 style="margin:0;font-size:1rem">Choisir une structure</h2>
      <div style="font-size:.75rem;color:var(--text-muted)"><?= $totalStructs ?> structure<?= $totalStructs > 1 ? 's' : '' ?> au total</div>
    </div>
    <div class="ms-tabs" style="display:flex;gap:var(--s2);flex-wrap:wrap">
      <?php
        $tabFilters = ['all' => 'Tous'];
        foreach ($groups as $gType => $items) { if (!empty($items)) $tabFilters[$gType] = $typeLabels[$gType] . ' (' . count($items) . ')'; }
        $activeFilter = isset($_GET['ftab']) ? $_GET['ftab'] : $selType;
        if (!isset($tabFilters[$activeFilter])) $activeFilter = 'all';
        foreach ($tabFilters as $k => $label):
          $cls = ($k === $activeFilter) ? 'ms-tab ms-tab--active' : 'ms-tab';
      ?>
        <button type="button" class="<?= $cls ?>" data-tab="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($label) ?></button>
      <?php endforeach; ?>
    </div>
    <input type="text" id="ms-search" class="admin-input" placeholder="Rechercher une structure…" autocomplete="off">
    <div class="ms-list" id="ms-list" style="max-height:240px;overflow-y:auto;border:1px solid var(--border);border-radius:var(--r-md)">
      <?php foreach ($mesStructures as $ms):
          $isSel = ($ms['type'] === $selType && (int)$ms['id'] === $selId);
          $href  = 'comptabilite.php?type=' . urlencode($ms['type']) . '&id=' . (int)$ms['id']; ?>
        <a href="<?= $href ?>" class="ms-item <?= $isSel ? 'ms-item--active' : '' ?>"
           data-type="<?= htmlspecialchars($ms['type']) ?>"
           data-name="<?= htmlspecialchars(mb_strtolower($ms['nom'])) ?>">
          <span class="ms-item__badge ms-item__badge--<?= htmlspecialchars($ms['type']) ?>"><?= htmlspecialchars($typeLabels[$ms['type']]) ?></span>
          <span class="ms-item__name"><?= htmlspecialchars($ms['nom']) ?></span>
          <?php if ($isSel): ?><span class="ms-item__dot"></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
      <div class="ms-empty" style="display:none;padding:var(--s4);text-align:center;color:var(--text-muted);font-size:.8rem">Aucune structure ne correspond.</div>
    </div>
  </div>
</div>

<!-- Sous-navigation -->
<div class="cpt-tabs">
  <?php
  $tabsNav = [
    'dashboard'     => ['Vue d\'ensemble', '📊'],
    'encaissements' => ['Encaissements',   '🔗', $pendingTotal],
    'notes_frais'   => ['Notes de frais',  '🧾', $nfPendingCount],
    'transactions'  => ['Transactions',    '📒'],
    'comptes'       => ['Comptes',         '🏦'],
    'categories'    => ['Catégories',      '🏷️'],
  ];
  foreach ($tabsNav as $k => $tabDef):
    [$label, $icon] = $tabDef;
    $badge = $tabDef[2] ?? 0;
    $href = 'comptabilite.php?type=' . urlencode($selType) . '&id=' . $selId . '&tab=' . $k;
    $cls  = ($activeTab === $k) ? 'cpt-tab cpt-tab--active' : 'cpt-tab';
  ?>
    <a href="<?= $href ?>" class="<?= $cls ?>">
      <span><?= $icon ?></span> <?= htmlspecialchars($label) ?>
      <?php if (in_array($k, ['encaissements', 'notes_frais'], true) && $badge > 0): ?>
        <span class="cpt-tab__badge"><?= (int)$badge ?></span>
      <?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>

<?php if ($activeTab === 'dashboard'): ?>

  <!-- KPIs -->
  <div class="cpt-kpis">
    <div class="cpt-kpi cpt-kpi--solde">
      <div class="cpt-kpi__label">Solde total</div>
      <div class="cpt-kpi__val"><?= fmt_euro($soldeTotal) ?></div>
      <div class="cpt-kpi__sub"><?= count($comptes) ?> compte<?= count($comptes) > 1 ? 's' : '' ?></div>
    </div>
    <div class="cpt-kpi cpt-kpi--recette">
      <div class="cpt-kpi__label">Recettes 30j</div>
      <div class="cpt-kpi__val">+<?= fmt_euro($r30['recettes']) ?></div>
      <div class="cpt-kpi__sub">Total : <?= fmt_euro($totalRecettes) ?></div>
    </div>
    <div class="cpt-kpi cpt-kpi--depense">
      <div class="cpt-kpi__label">Dépenses 30j</div>
      <div class="cpt-kpi__val">−<?= fmt_euro($r30['depenses']) ?></div>
      <div class="cpt-kpi__sub">Total : <?= fmt_euro($totalDepenses) ?></div>
    </div>
    <div class="cpt-kpi <?= $resultatNet >= 0 ? 'cpt-kpi--positif' : 'cpt-kpi--negatif' ?>">
      <div class="cpt-kpi__label">Résultat net</div>
      <div class="cpt-kpi__val"><?= ($resultatNet >= 0 ? '+' : '−') . fmt_euro(abs($resultatNet)) ?></div>
      <div class="cpt-kpi__sub">Recettes − dépenses (tout l'historique)</div>
    </div>
  </div>

  <?php if ($comptaSourceReady && $encaissementsSum): ?>
  <div class="admin-card cpt-sync-banner" style="margin-top:var(--s5)">
    <div class="cpt-sync-banner__head">
      <div>
        <h2 style="margin:0;font-size:1rem">Encaissements en ligne</h2>
        <p style="margin:.35rem 0 0;font-size:.82rem;color:var(--text-muted)">Billetterie et boutique - rapprochement avec le journal comptable</p>
      </div>
      <div style="display:flex;gap:var(--s2);flex-wrap:wrap">
        <?php if ($pendingTotal > 0): ?>
          <form method="post" style="margin:0">
            <input type="hidden" name="action" value="sync_all">
            <button type="submit" class="btn btn--primary btn--sm">Importer tout (<?= (int)$pendingTotal ?>)</button>
          </form>
        <?php endif; ?>
        <a href="?type=<?= urlencode($selType) ?>&id=<?= $selId ?>&tab=encaissements" class="btn btn--ghost btn--sm">Détail →</a>
      </div>
    </div>
    <div class="cpt-sync-grid">
      <div class="cpt-sync-card">
        <div class="cpt-sync-card__title">🎟 Billetterie</div>
        <div class="cpt-sync-card__row"><span>Encaissé en ligne</span><strong><?= fmt_euro($encaissementsSum['billetterie']['online']) ?></strong></div>
        <div class="cpt-sync-card__row"><span>En compta</span><strong style="color:#2ecc71"><?= fmt_euro($encaissementsSum['billetterie']['compta']) ?></strong></div>
        <?php if ($encaissementsSum['billetterie']['pending'] > 0): ?>
          <div class="cpt-sync-card__row cpt-sync-card__row--warn"><span>À importer</span><strong><?= fmt_euro($encaissementsSum['billetterie']['pending']) ?></strong></div>
        <?php endif; ?>
      </div>
      <div class="cpt-sync-card">
        <div class="cpt-sync-card__title">🛍 Boutique</div>
        <div class="cpt-sync-card__row"><span>Encaissé en ligne</span><strong><?= fmt_euro($encaissementsSum['boutique']['online']) ?></strong></div>
        <div class="cpt-sync-card__row"><span>En compta</span><strong style="color:#2ecc71"><?= fmt_euro($encaissementsSum['boutique']['compta']) ?></strong></div>
        <?php if ($encaissementsSum['boutique']['pending'] > 0): ?>
          <div class="cpt-sync-card__row cpt-sync-card__row--warn"><span>À importer</span><strong><?= fmt_euro($encaissementsSum['boutique']['pending']) ?></strong></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php elseif (!$comptaSourceReady): ?>
  <div class="flash flash--info" style="margin-top:var(--s5)">
    <strong>Lien billetterie / boutique :</strong> migration <code>compta_tx_source_link</code> -
    <a href="migrate.php">Migrations DB</a>.
  </div>
  <?php endif; ?>

  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(360px,1fr));gap:var(--s5);margin-top:var(--s5)">

    <!-- Évolution mensuelle -->
    <div class="admin-card">
      <h2 style="margin:0 0 var(--s4) 0;font-size:1rem">Évolution sur 12 mois</h2>
      <div class="cpt-bars">
        <?php foreach ($months as $ym => $m):
          $hR = ($m['recettes'] / $maxMonth) * 100;
          $hD = ($m['depenses'] / $maxMonth) * 100; ?>
          <div class="cpt-bar" title="<?= $ym ?>&#10;Recettes : <?= fmt_euro($m['recettes']) ?>&#10;Dépenses : <?= fmt_euro($m['depenses']) ?>">
            <div class="cpt-bar__col">
              <div class="cpt-bar__seg cpt-bar__seg--rec" style="height:<?= $hR ?>%"></div>
              <div class="cpt-bar__seg cpt-bar__seg--dep" style="height:<?= $hD ?>%"></div>
            </div>
            <div class="cpt-bar__lbl"><?= substr($ym, 5, 2) ?>/<?= substr($ym, 2, 2) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
      <div style="display:flex;gap:var(--s4);font-size:.75rem;color:var(--text-muted);margin-top:var(--s3)">
        <span><span style="display:inline-block;width:10px;height:10px;background:#2ecc71;border-radius:2px;margin-right:.3rem"></span>Recettes</span>
        <span><span style="display:inline-block;width:10px;height:10px;background:#e74c3c;border-radius:2px;margin-right:.3rem"></span>Dépenses</span>
      </div>
    </div>

    <!-- Répartition dépenses 12 mois -->
    <div class="admin-card">
      <h2 style="margin:0 0 var(--s4) 0;font-size:1rem">Dépenses par catégorie · 12 mois</h2>
      <?php if (empty($depParCat)): ?>
        <p style="color:var(--text-muted);font-size:.85rem">Aucune dépense enregistrée.</p>
      <?php else: ?>
        <div class="cpt-catbars">
          <?php foreach ($depParCat as $r):
            $pct = $depParCatTotal > 0 ? ((float)$r['total'] / $depParCatTotal * 100) : 0; ?>
            <div class="cpt-catbar">
              <div class="cpt-catbar__head">
                <span class="cpt-catbar__dot" style="background:<?= htmlspecialchars($r['couleur']) ?>"></span>
                <span class="cpt-catbar__name"><?= htmlspecialchars($r['nom']) ?></span>
                <span class="cpt-catbar__val"><?= fmt_euro($r['total']) ?></span>
              </div>
              <div class="cpt-catbar__rail"><div class="cpt-catbar__fill" style="width:<?= $pct ?>%;background:<?= htmlspecialchars($r['couleur']) ?>"></div></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Soldes par compte -->
    <div class="admin-card">
      <h2 style="margin:0 0 var(--s4) 0;font-size:1rem">Soldes par compte</h2>
      <?php if (empty($comptes)): ?>
        <p style="color:var(--text-muted);font-size:.85rem">Aucun compte. <a href="?type=<?= urlencode($selType) ?>&id=<?= $selId ?>&tab=comptes">Créer un compte →</a></p>
      <?php else: ?>
        <div class="cpt-compte-list">
          <?php foreach ($comptes as $c):
            $cId  = (int)$c['id'];
            $rec  = (float)($soldesParCompte[$cId]['recettes'] ?? 0);
            $dep  = (float)($soldesParCompte[$cId]['depenses'] ?? 0);
            $solde = (float)$c['solde_initial'] + $rec - $dep;
            $bal   = $solde >= 0 ? 'pos' : 'neg'; ?>
            <div class="cpt-compte-row <?= $c['archive'] ? 'cpt-compte-row--archived' : '' ?>">
              <div class="cpt-compte-row__icon">
                <?= $c['type'] === 'caisse' ? '💵' : ($c['type'] === 'banque' ? '🏦' : '💼') ?>
              </div>
              <div class="cpt-compte-row__body">
                <div class="cpt-compte-row__nom"><?= htmlspecialchars($c['nom']) ?></div>
                <div class="cpt-compte-row__sub"><?= htmlspecialchars(ucfirst($c['type'])) ?><?= $c['iban'] ? ' · ' . htmlspecialchars(substr($c['iban'], 0, 4)) . '…' . htmlspecialchars(substr($c['iban'], -4)) : '' ?></div>
              </div>
              <div class="cpt-compte-row__solde cpt-compte-row__solde--<?= $bal ?>"><?= fmt_euro($solde) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  </div>

<?php elseif ($activeTab === 'encaissements'): ?>

  <?php if (!$comptaSourceReady): ?>
    <div class="flash flash--warn">Applique la migration <code>compta_tx_source_link</code> dans <a href="migrate.php">Migrations DB</a>.</div>
  <?php else: ?>
    <div class="cpt-sync-actions">
      <?php if ($pendingTotal > 0): ?>
        <form method="post" style="margin:0">
          <input type="hidden" name="action" value="sync_all">
          <button type="submit" class="btn btn--primary">Importer tout (<?= (int)$pendingTotal ?>)</button>
        </form>
      <?php else: ?>
        <span class="flash flash--ok" style="margin:0">Tous les encaissements payés sont à jour en compta.</span>
      <?php endif; ?>
      <a href="boutique-commandes.php" class="btn btn--ghost btn--sm">Commandes boutique →</a>
      <a href="evenements.php" class="btn btn--ghost btn--sm">Événements / billetterie →</a>
    </div>

    <?php if ($encaissementsSum): ?>
    <div class="cpt-sync-grid" style="margin-bottom:var(--s5)">
      <div class="cpt-sync-card cpt-sync-card--wide">
        <div class="cpt-sync-card__title">🎟 Billetterie - synthèse</div>
        <div class="cpt-sync-card__row"><span>Total encaissé (paiements validés)</span><strong><?= fmt_euro($encaissementsSum['billetterie']['online']) ?></strong></div>
        <div class="cpt-sync-card__row"><span>Déjà au journal</span><strong style="color:#2ecc71"><?= fmt_euro($encaissementsSum['billetterie']['compta']) ?></strong></div>
        <div class="cpt-sync-card__row cpt-sync-card__row--warn"><span>Reste à importer</span><strong><?= fmt_euro($encaissementsSum['billetterie']['pending']) ?></strong></div>
      </div>
      <div class="cpt-sync-card cpt-sync-card--wide">
        <div class="cpt-sync-card__title">🛍 Boutique - synthèse</div>
        <div class="cpt-sync-card__row"><span>Total encaissé (commandes payées)</span><strong><?= fmt_euro($encaissementsSum['boutique']['online']) ?></strong></div>
        <div class="cpt-sync-card__row"><span>Déjà au journal</span><strong style="color:#2ecc71"><?= fmt_euro($encaissementsSum['boutique']['compta']) ?></strong></div>
        <div class="cpt-sync-card__row cpt-sync-card__row--warn"><span>Reste à importer</span><strong><?= fmt_euro($encaissementsSum['boutique']['pending']) ?></strong></div>
      </div>
    </div>
    <?php endif; ?>

    <div class="admin-card" style="padding:0;margin-bottom:var(--s5)">
      <h2 style="padding:var(--s4) var(--s4) 0;margin:0;font-size:1rem">Billetterie · à importer (<?= count($pendingBillet) ?>)</h2>
      <div style="overflow-x:auto">
        <table class="admin-table">
          <thead><tr><th>Date</th><th>Événement</th><th>Prestataire</th><th style="text-align:right">Montant</th><th></th></tr></thead>
          <tbody>
            <?php if (empty($pendingBillet)): ?>
              <tr><td colspan="5" style="text-align:center;padding:var(--s5);color:var(--text-muted)">Rien en attente.</td></tr>
            <?php else: foreach ($pendingBillet as $pb): ?>
              <tr>
                <td style="white-space:nowrap;font-size:.8rem"><?= date('d/m/Y', strtotime((string)($pb['updated_at'] ?? $pb['created_at']))) ?></td>
                <td>
                  <strong><?= htmlspecialchars((string)$pb['evt_titre']) ?></strong>
                  <br><a href="evenement.php?id=<?= (int)$pb['evenement_id'] ?>" class="btn btn--ghost btn--sm" style="margin-top:.25rem">Voir l'événement</a>
                </td>
                <td style="font-size:.78rem"><?= htmlspecialchars(strtoupper((string)$pb['provider'])) ?></td>
                <td style="text-align:right;font-weight:700;font-family:monospace;color:#2ecc71">+<?= fmt_euro($pb['montant']) ?></td>
                <td>
                  <form method="post" style="margin:0">
                    <input type="hidden" name="action" value="sync_billet">
                    <input type="hidden" name="paiement_id" value="<?= (int)$pb['id'] ?>">
                    <button type="submit" class="btn btn--primary btn--sm">Importer</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="admin-card" style="padding:0">
      <h2 style="padding:var(--s4) var(--s4) 0;margin:0;font-size:1rem">Boutique · lignes à importer (<?= count($pendingBoutique) ?>)</h2>
      <div style="overflow-x:auto">
        <table class="admin-table">
          <thead><tr><th>Date</th><th>Article</th><th>Commande</th><th style="text-align:right">Montant</th><th></th></tr></thead>
          <tbody>
            <?php if (empty($pendingBoutique)): ?>
              <tr><td colspan="5" style="text-align:center;padding:var(--s5);color:var(--text-muted)">Rien en attente.</td></tr>
            <?php else: foreach ($pendingBoutique as $pb): ?>
              <?php $mtLigne = (float)$pb['prix_unitaire'] * (int)$pb['quantite']; ?>
              <tr>
                <td style="white-space:nowrap;font-size:.8rem"><?= date('d/m/Y', strtotime((string)$pb['cmd_date'])) ?></td>
                <td><strong><?= htmlspecialchars((string)$pb['titre_snapshot']) ?></strong> ×<?= (int)$pb['quantite'] ?></td>
                <td style="font-size:.78rem">
                  #<?= (int)$pb['commande_id'] ?> · <?= htmlspecialchars((string)$pb['email']) ?>
                  <br><a href="boutique-commandes.php?id=<?= (int)$pb['commande_id'] ?>" class="btn btn--ghost btn--sm" style="margin-top:.25rem">Voir commande</a>
                </td>
                <td style="text-align:right;font-weight:700;font-family:monospace;color:#2ecc71">+<?= fmt_euro($mtLigne) ?></td>
                <td>
                  <form method="post" style="margin:0">
                    <input type="hidden" name="action" value="sync_boutique_ligne">
                    <input type="hidden" name="ligne_id" value="<?= (int)$pb['ligne_id'] ?>">
                    <button type="submit" class="btn btn--primary btn--sm">Importer</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

<?php elseif ($activeTab === 'transactions'): ?>

  <!-- Saisie rapide -->
  <form method="post" class="admin-card cpt-quick">
    <input type="hidden" name="action" value="tx_quick">
    <div class="cpt-quick__label">Saisie rapide</div>
    <div class="cpt-type-toggle cpt-quick__type">
      <label><input type="radio" name="type" value="depense" checked><span class="cpt-type-toggle__btn cpt-type-toggle__btn--dep">− Dépense</span></label>
      <label><input type="radio" name="type" value="recette"><span class="cpt-type-toggle__btn cpt-type-toggle__btn--rec">＋ Recette</span></label>
    </div>
    <input type="number" step="0.01" min="0.01" name="montant" required class="admin-input cpt-quick__amount" placeholder="Montant €" inputmode="decimal">
    <input type="text" name="libelle" required class="admin-input cpt-quick__libelle" placeholder="Libellé (ex : courses buvette)">
    <button type="submit" class="btn btn--primary">Enregistrer</button>
    <?php if ($pendingTotal > 0): ?>
      <a href="?type=<?= urlencode($selType) ?>&id=<?= $selId ?>&tab=encaissements" class="btn btn--ghost btn--sm cpt-quick__link"><?= (int)$pendingTotal ?> encaissement(s) à importer</a>
    <?php endif; ?>
  </form>

  <!-- Filtres -->
  <form method="get" class="admin-card cpt-filters">
    <input type="hidden" name="type" value="<?= htmlspecialchars($selType) ?>">
    <input type="hidden" name="id"   value="<?= $selId ?>">
    <input type="hidden" name="tab"  value="transactions">
    <div class="cpt-filters__grid">
      <div class="admin-field"><label>Du</label><input type="date" name="from" value="<?= htmlspecialchars($fDateFrom) ?>" class="admin-input"></div>
      <div class="admin-field"><label>Au</label><input type="date" name="to" value="<?= htmlspecialchars($fDateTo) ?>" class="admin-input"></div>
      <div class="admin-field"><label>Type</label>
        <select name="ftype" class="admin-input">
          <option value="">Tous</option>
          <option value="recette" <?= $fType === 'recette' ? 'selected' : '' ?>>Recettes</option>
          <option value="depense" <?= $fType === 'depense' ? 'selected' : '' ?>>Dépenses</option>
        </select>
      </div>
      <div class="admin-field"><label>Catégorie</label>
        <select name="fcat" class="admin-input">
          <option value="0">Toutes</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>" <?= $fCat === (int)$cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($cat['type'])) ?> · <?= htmlspecialchars($cat['nom']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="admin-field"><label>Compte</label>
        <select name="fcompte" class="admin-input">
          <option value="0">Tous</option>
          <?php foreach ($comptes as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $fCompte === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nom']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if (!empty($evenementsStruct)): ?>
        <div class="admin-field"><label>Événement</label>
          <select name="fevt" class="admin-input">
            <option value="0">Tous</option>
            <?php foreach ($evenementsStruct as $e): ?>
              <option value="<?= $e['id'] ?>" <?= $fEvt === (int)$e['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($e['titre']) ?><?= !empty($e['date']) ? ' (' . date('d/m/Y', strtotime($e['date'])) . ')' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
      <div class="admin-field"><label>Recherche</label><input type="text" name="q" value="<?= htmlspecialchars($fSearch) ?>" placeholder="Libellé, référence, notes…" class="admin-input"></div>
    </div>
    <div class="cpt-period-chips">
      <span class="evt-filter-label">Période :</span>
      <a class="evt-chip" href="?type=<?= urlencode($selType) ?>&id=<?= $selId ?>&tab=transactions&period=month">Ce mois</a>
      <a class="evt-chip" href="?type=<?= urlencode($selType) ?>&id=<?= $selId ?>&tab=transactions&period=30d">30 jours</a>
      <a class="evt-chip" href="?type=<?= urlencode($selType) ?>&id=<?= $selId ?>&tab=transactions&period=year">Cette année</a>
    </div>
    <div style="display:flex;gap:var(--s2);flex-wrap:wrap;margin-top:var(--s3)">
      <button type="submit" class="btn btn--primary btn--sm">Filtrer</button>
      <a href="?type=<?= urlencode($selType) ?>&id=<?= $selId ?>&tab=transactions" class="btn btn--ghost btn--sm">Réinitialiser</a>
      <a href="?type=<?= urlencode($selType) ?>&id=<?= $selId ?>&tab=transactions&export=xlsx&<?= http_build_query(['from'=>$fDateFrom,'to'=>$fDateTo,'ftype'=>$fType,'fcat'=>$fCat,'fcompte'=>$fCompte,'fevt'=>$fEvt,'q'=>$fSearch]) ?>" class="btn btn--ghost btn--sm">⤓ Exporter Excel</a>
      <a href="?type=<?= urlencode($selType) ?>&id=<?= $selId ?>&tab=transactions&export=csv&<?= http_build_query(['from'=>$fDateFrom,'to'=>$fDateTo,'ftype'=>$fType,'fcat'=>$fCat,'fcompte'=>$fCompte,'fevt'=>$fEvt,'q'=>$fSearch]) ?>" class="btn btn--ghost btn--sm">CSV</a>
      <button type="button" class="btn btn--success btn--sm" onclick="document.getElementById('cpt-tx-modal').classList.add('open');document.getElementById('tx-type').value='depense';document.getElementById('tx-id').value='';document.querySelector('#cpt-tx-modal .cpt-modal__title').textContent='Ajouter une dépense';" style="margin-left:auto">＋ Dépense</button>
      <button type="button" class="btn btn--success btn--sm" onclick="document.getElementById('cpt-tx-modal').classList.add('open');document.getElementById('tx-type').value='recette';document.getElementById('tx-id').value='';document.querySelector('#cpt-tx-modal .cpt-modal__title').textContent='Ajouter une recette';">＋ Recette</button>
    </div>
  </form>

  <!-- Tableau transactions -->
  <div class="admin-card" style="padding:0;overflow:hidden">
    <div style="overflow-x:auto">
      <table class="admin-table cpt-tx-table">
        <thead>
          <tr>
            <th>Date</th><th>Libellé</th><?php if ($comptaSourceReady): ?><th>Source</th><?php endif; ?><th>Catégorie</th><th>Compte</th><th>Événement</th><th style="text-align:right">Montant</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($transactions)): ?>
            <tr><td colspan="<?= $comptaSourceReady ? 8 : 7 ?>" style="text-align:center;padding:var(--s6);color:var(--text-muted)">Aucune transaction. Utilise la saisie rapide ou importe les encaissements en ligne.</td></tr>
          <?php else: foreach ($transactions as $tx): ?>
            <tr>
              <td style="white-space:nowrap;font-size:.8rem"><?= date('d/m/Y', strtotime($tx['date_operation'])) ?></td>
              <td>
                <div style="font-weight:600"><?= htmlspecialchars($tx['libelle']) ?></div>
                <?php if ($tx['reference']): ?><div style="font-size:.7rem;color:var(--text-muted)">Réf. <?= htmlspecialchars($tx['reference']) ?></div><?php endif; ?>
                <?php if ($tx['notes']): ?><div style="font-size:.7rem;color:var(--text-muted);max-width:320px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($tx['notes']) ?>"><?= htmlspecialchars($tx['notes']) ?></div><?php endif; ?>
                <?php if ($comptaSourceReady && !empty($tx['source_type']) && $tx['source_type'] !== 'manuel'): ?>
                  <span class="cpt-src-pill cpt-src-pill--<?= htmlspecialchars($tx['source_type']) ?>">
                    <?= $tx['source_type'] === 'billetterie' ? '🎟 Auto' : '🛍 Auto' ?>
                  </span>
                <?php endif; ?>
              </td>
              <?php if ($comptaSourceReady): ?>
              <td style="font-size:.72rem;white-space:nowrap">
                <?php if (($tx['source_type'] ?? '') === 'billetterie'): ?>
                  <a href="evenement.php?id=<?= (int)($tx['evenement_id'] ?? 0) ?>">Paiem. #<?= (int)($tx['source_id'] ?? 0) ?></a>
                <?php elseif (($tx['source_type'] ?? '') === 'boutique' && !empty($tx['boutique_commande_id'])): ?>
                  <a href="boutique-commandes.php?id=<?= (int)$tx['boutique_commande_id'] ?>">Cmd. #<?= (int)$tx['boutique_commande_id'] ?></a>
                <?php else: ?>
                  <span style="color:var(--text-muted)">Manuel</span>
                <?php endif; ?>
              </td>
              <?php endif; ?>
              <td>
                <?php if ($tx['cat_nom']): ?>
                  <span class="cpt-cat-pill" style="background:<?= htmlspecialchars($tx['cat_couleur']) ?>22;color:<?= htmlspecialchars($tx['cat_couleur']) ?>;border-color:<?= htmlspecialchars($tx['cat_couleur']) ?>55"><?= htmlspecialchars($tx['cat_nom']) ?></span>
                <?php else: ?>
                  <span style="color:var(--text-muted);font-size:.75rem">-</span>
                <?php endif; ?>
              </td>
              <td style="font-size:.78rem"><?= htmlspecialchars($tx['compte_nom'] ?? '-') ?></td>
              <td style="font-size:.78rem"><?= $tx['evt_titre'] ? '<a href="evenements.php" style="color:#fff">' . htmlspecialchars($tx['evt_titre']) . '</a>' : '-' ?></td>
              <td style="text-align:right;white-space:nowrap;font-weight:700;font-family:var(--font-mono,monospace);color:<?= $tx['type'] === 'recette' ? '#2ecc71' : '#e74c3c' ?>">
                <?= $tx['type'] === 'recette' ? '+' : '−' ?><?= fmt_euro($tx['montant']) ?>
              </td>
              <td style="white-space:nowrap">
                <button type="button" class="btn btn--ghost btn--sm" onclick='openEditTx(<?= json_encode($tx, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="Modifier">✎</button>
                <form method="post" style="display:inline" onsubmit="return confirm('Supprimer cette transaction ?');">
                  <input type="hidden" name="action" value="tx_delete">
                  <input type="hidden" name="tx_id" value="<?= $tx['id'] ?>">
                  <button class="btn btn--danger btn--sm" type="submit" title="Supprimer">🗑</button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($pageMax > 1): ?>
    <div style="display:flex;justify-content:center;gap:var(--s2);margin-top:var(--s4);flex-wrap:wrap">
      <?php
        $qs = $_GET; unset($qs['p']);
        for ($p = 1; $p <= $pageMax; $p++):
          $qs['p'] = $p;
          $cls = $p === $page ? 'btn btn--primary btn--sm' : 'btn btn--ghost btn--sm'; ?>
        <a class="<?= $cls ?>" href="?<?= http_build_query($qs) ?>"><?= $p ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>

  <!-- Modale Ajout / Édition -->
  <div id="cpt-tx-modal" class="cpt-modal">
    <div class="cpt-modal__backdrop" onclick="document.getElementById('cpt-tx-modal').classList.remove('open');"></div>
    <div class="cpt-modal__inner">
      <button type="button" class="cpt-modal__close" onclick="document.getElementById('cpt-tx-modal').classList.remove('open');">×</button>
      <h2 class="cpt-modal__title">Nouvelle transaction</h2>
      <form method="post" class="cpt-form">
        <input type="hidden" name="action" value="tx_save">
        <input type="hidden" name="tx_id" id="tx-id" value="">
        <div class="cpt-form__grid">
          <div class="admin-field">
            <label>Type</label>
            <div class="cpt-type-toggle">
              <label><input type="radio" name="type" value="depense" id="tx-type" checked><span class="cpt-type-toggle__btn cpt-type-toggle__btn--dep">− Dépense</span></label>
              <label><input type="radio" name="type" value="recette"><span class="cpt-type-toggle__btn cpt-type-toggle__btn--rec">＋ Recette</span></label>
            </div>
          </div>
          <div class="admin-field">
            <label>Montant (€) *</label>
            <input type="number" step="0.01" min="0.01" name="montant" id="tx-montant" required class="admin-input" placeholder="0,00">
          </div>
          <div class="admin-field">
            <label>Date *</label>
            <input type="date" name="date_operation" id="tx-date" value="<?= date('Y-m-d') ?>" required class="admin-input">
          </div>
          <div class="admin-field" style="grid-column:1/-1">
            <label>Libellé *</label>
            <input type="text" name="libelle" id="tx-libelle" required class="admin-input" placeholder="Ex : Achat boissons soirée d'intégration">
          </div>
          <div class="admin-field">
            <label>Compte</label>
            <select name="compte_id" id="tx-compte" class="admin-input">
              <option value="0">- Aucun -</option>
              <?php foreach ($comptes as $c): if ($c['archive']) continue; ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nom']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="admin-field">
            <label>Catégorie</label>
            <select name="categorie_id" id="tx-categorie" class="admin-input">
              <option value="0">- Non classée -</option>
              <?php
              $catRec = array_filter($categories, fn($c) => $c['type'] === 'recette');
              $catDep = array_filter($categories, fn($c) => $c['type'] === 'depense');
              ?>
              <optgroup label="Recettes">
                <?php foreach ($catRec as $c): ?>
                  <option value="<?= $c['id'] ?>" data-type="recette"><?= htmlspecialchars($c['nom']) ?></option>
                <?php endforeach; ?>
              </optgroup>
              <optgroup label="Dépenses">
                <?php foreach ($catDep as $c): ?>
                  <option value="<?= $c['id'] ?>" data-type="depense"><?= htmlspecialchars($c['nom']) ?></option>
                <?php endforeach; ?>
              </optgroup>
            </select>
          </div>
          <div class="admin-field">
            <label>Mode de paiement</label>
            <select name="mode_paiement" id="tx-mode" class="admin-input">
              <option value="virement">Virement</option>
              <option value="carte">Carte</option>
              <option value="especes">Espèces</option>
              <option value="cheque">Chèque</option>
              <option value="prelevement">Prélèvement</option>
              <option value="autre">Autre</option>
            </select>
          </div>
          <?php if (!empty($evenementsStruct)): ?>
            <div class="admin-field">
              <label>Lié à un événement (optionnel)</label>
              <select name="evenement_id" id="tx-evt" class="admin-input">
                <option value="0">- Aucun -</option>
                <?php foreach ($evenementsStruct as $e): ?>
                  <option value="<?= $e['id'] ?>">
                    <?= htmlspecialchars($e['titre']) ?><?= !empty($e['date']) ? ' (' . date('d/m/Y', strtotime($e['date'])) . ')' : '' ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endif; ?>
          <div class="admin-field">
            <label>Référence (n° facture, etc.)</label>
            <input type="text" name="reference" id="tx-ref" class="admin-input" placeholder="Optionnel">
          </div>
          <div class="admin-field" style="grid-column:1/-1">
            <label>Notes</label>
            <textarea name="notes" id="tx-notes" class="admin-input" rows="2" placeholder="Détails, contexte, justification…"></textarea>
          </div>
        </div>
        <div style="display:flex;gap:var(--s2);justify-content:flex-end;margin-top:var(--s4)">
          <button type="button" class="btn btn--ghost" onclick="document.getElementById('cpt-tx-modal').classList.remove('open');">Annuler</button>
          <button type="submit" class="btn btn--primary">Enregistrer</button>
        </div>
      </form>
    </div>
  </div>

<?php elseif ($activeTab === 'comptes'): ?>

  <div class="admin-card" style="margin-bottom:var(--s5)">
    <h2 style="margin:0 0 var(--s4) 0">Créer un compte</h2>
    <form method="post" class="cpt-form__grid">
      <input type="hidden" name="action" value="compte_save">
      <input type="hidden" name="compte_id" value="0">
      <div class="admin-field"><label>Nom *</label><input type="text" name="nom" required class="admin-input" placeholder="Ex : Compte courant Crédit Mutuel"></div>
      <div class="admin-field"><label>Type</label>
        <select name="ctype" class="admin-input">
          <option value="banque">Banque</option>
          <option value="caisse">Caisse / Espèces</option>
          <option value="autre">Autre</option>
        </select>
      </div>
      <div class="admin-field"><label>IBAN (optionnel)</label><input type="text" name="iban" class="admin-input" placeholder="FR76 ..."></div>
      <div class="admin-field"><label>Solde initial (€)</label><input type="number" step="0.01" name="solde_initial" value="0" class="admin-input"></div>
      <div style="grid-column:1/-1;display:flex;justify-content:flex-end"><button type="submit" class="btn btn--primary">Créer</button></div>
    </form>
  </div>

  <div class="admin-card" style="padding:0">
    <table class="admin-table">
      <thead><tr><th>Nom</th><th>Type</th><th>IBAN</th><th style="text-align:right">Solde initial</th><th style="text-align:right">Solde actuel</th><th>Statut</th><th></th></tr></thead>
      <tbody>
        <?php if (empty($comptes)): ?>
          <tr><td colspan="7" style="text-align:center;padding:var(--s5);color:var(--text-muted)">Aucun compte. Crée-en un ci-dessus.</td></tr>
        <?php else: foreach ($comptes as $c):
          $cId   = (int)$c['id'];
          $rec   = (float)($soldesParCompte[$cId]['recettes'] ?? 0);
          $dep   = (float)($soldesParCompte[$cId]['depenses'] ?? 0);
          $solde = (float)$c['solde_initial'] + $rec - $dep; ?>
          <tr style="<?= $c['archive'] ? 'opacity:.55' : '' ?>">
            <td><strong><?= htmlspecialchars($c['nom']) ?></strong></td>
            <td><?= htmlspecialchars(ucfirst($c['type'])) ?></td>
            <td style="font-family:monospace;font-size:.78rem"><?= htmlspecialchars($c['iban'] ?? '') ?></td>
            <td style="text-align:right;font-family:monospace"><?= fmt_euro($c['solde_initial']) ?></td>
            <td style="text-align:right;font-family:monospace;font-weight:700;color:<?= $solde >= 0 ? '#2ecc71' : '#e74c3c' ?>"><?= fmt_euro($solde) ?></td>
            <td><?= $c['archive'] ? '<span class="badge badge--warn">Archivé</span>' : '<span class="badge">Actif</span>' ?></td>
            <td>
              <form method="post" style="display:inline">
                <input type="hidden" name="action" value="compte_archive">
                <input type="hidden" name="compte_id" value="<?= $c['id'] ?>">
                <button type="submit" class="btn btn--ghost btn--sm"><?= $c['archive'] ? 'Désarchiver' : 'Archiver' ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

<?php elseif ($activeTab === 'categories'): ?>

  <div class="flash flash--info" style="margin-bottom:var(--s4)">
    Les catégories par défaut (héritées de la Corpo) sont disponibles pour toutes les structures. Tu peux créer des catégories propres à cette structure en complément.
  </div>

  <div class="admin-card" style="margin-bottom:var(--s5)">
    <h2 style="margin:0 0 var(--s4) 0">Ajouter une catégorie spécifique</h2>
    <form method="post" class="cpt-form__grid">
      <input type="hidden" name="action" value="cat_save">
      <input type="hidden" name="cat_id" value="0">
      <div class="admin-field"><label>Nom *</label><input type="text" name="nom" required class="admin-input" placeholder="Ex : Mécénat startup"></div>
      <div class="admin-field"><label>Type *</label>
        <select name="cattype" class="admin-input">
          <option value="depense">Dépense</option>
          <option value="recette">Recette</option>
        </select>
      </div>
      <div class="admin-field"><label>Couleur</label><input type="color" name="couleur" value="#5D0282" class="admin-input" style="height:42px;padding:.2rem"></div>
      <div style="grid-column:1/-1;display:flex;justify-content:flex-end"><button type="submit" class="btn btn--primary">Ajouter</button></div>
    </form>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--s4)">

    <div class="admin-card" style="padding:0">
      <h2 style="padding:var(--s4) var(--s4) 0;margin:0">Catégories de recettes</h2>
      <table class="admin-table"><thead><tr><th></th><th>Nom</th><th>Source</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($categories as $c): if ($c['type'] !== 'recette') continue; $isOwn = ((int)$c['structure_id'] === $selId && $c['structure_type'] === $selType); ?>
            <tr><td style="width:8px"><span class="cpt-catbar__dot" style="background:<?= htmlspecialchars($c['couleur']) ?>"></span></td>
                <td><?= htmlspecialchars($c['nom']) ?></td>
                <td style="font-size:.72rem;color:var(--text-muted)"><?= $isOwn ? 'Spécifique' : 'Modèle Corpo' ?></td>
                <td>
                  <?php if ($isOwn): ?>
                  <form method="post" style="display:inline">
                    <input type="hidden" name="action" value="cat_archive">
                    <input type="hidden" name="cat_id" value="<?= $c['id'] ?>">
                    <button type="submit" class="btn btn--ghost btn--sm">Archiver</button>
                  </form>
                  <?php endif; ?>
                </td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="admin-card" style="padding:0">
      <h2 style="padding:var(--s4) var(--s4) 0;margin:0">Catégories de dépenses</h2>
      <table class="admin-table"><thead><tr><th></th><th>Nom</th><th>Source</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($categories as $c): if ($c['type'] !== 'depense') continue; $isOwn = ((int)$c['structure_id'] === $selId && $c['structure_type'] === $selType); ?>
            <tr><td style="width:8px"><span class="cpt-catbar__dot" style="background:<?= htmlspecialchars($c['couleur']) ?>"></span></td>
                <td><?= htmlspecialchars($c['nom']) ?></td>
                <td style="font-size:.72rem;color:var(--text-muted)"><?= $isOwn ? 'Spécifique' : 'Modèle Corpo' ?></td>
                <td>
                  <?php if ($isOwn): ?>
                  <form method="post" style="display:inline">
                    <input type="hidden" name="action" value="cat_archive">
                    <input type="hidden" name="cat_id" value="<?= $c['id'] ?>">
                    <button type="submit" class="btn btn--ghost btn--sm">Archiver</button>
                  </form>
                  <?php endif; ?>
                </td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php elseif ($activeTab === 'notes_frais'): ?>

  <?php if (!nf_table_ready($pdo)): ?>
    <div class="admin-card">
      <p class="flash flash--warn">Table <code>compta_notes_frais</code> absente. Applique la migration <strong>tbl_compta_notes_frais</strong> dans <a href="migrate.php">Migrations DB</a>.</p>
    </div>
  <?php else: ?>
    <?php require __DIR__ . '/includes/compta-tab-notes-frais.php'; ?>
  <?php endif; ?>

<?php endif; ?>

<script>
// Sélecteur structures (filtres + recherche)
(function() {
  const tabs = document.querySelectorAll('.ms-tabs .ms-tab');
  const items = document.querySelectorAll('#ms-list .ms-item');
  const empty = document.querySelector('#ms-list .ms-empty');
  const search = document.getElementById('ms-search');
  if (!tabs.length) return;
  let activeTab = <?= json_encode($activeFilter ?? 'all') ?>;
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
  tabs.forEach(btn => btn.addEventListener('click', () => {
    tabs.forEach(b => b.classList.remove('ms-tab--active'));
    btn.classList.add('ms-tab--active');
    activeTab = btn.dataset.tab;
    applyFilter();
  }));
  search.addEventListener('input', applyFilter);
  applyFilter();
})();

// Modale Tx - édition pré-remplissage
function openEditTx(tx) {
  const m = document.getElementById('cpt-tx-modal');
  if (!m) return;
  document.getElementById('tx-id').value = tx.id;
  document.querySelector('#cpt-tx-modal .cpt-modal__title').textContent = 'Modifier la transaction';
  m.querySelectorAll('input[name="type"]').forEach(r => { r.checked = (r.value === tx.type); });
  document.getElementById('tx-montant').value   = tx.montant;
  document.getElementById('tx-date').value      = tx.date_operation;
  document.getElementById('tx-libelle').value   = tx.libelle || '';
  document.getElementById('tx-compte').value    = tx.compte_id || 0;
  document.getElementById('tx-categorie').value = tx.categorie_id || 0;
  document.getElementById('tx-mode').value      = tx.mode_paiement || 'virement';
  const evt = document.getElementById('tx-evt'); if (evt) evt.value = tx.evenement_id || 0;
  document.getElementById('tx-ref').value       = tx.reference || '';
  document.getElementById('tx-notes').value     = tx.notes || '';
  m.classList.add('open');
}
// Fermer modale avec Escape
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.cpt-modal.open').forEach(m => m.classList.remove('open'));
  }
});
</script>

<?php require_once 'includes/admin-footer.php'; ?>
