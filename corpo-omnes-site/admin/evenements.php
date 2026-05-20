<?php
$adminTitle = 'Événements';
$adminPage  = 'evenements';
require_once '../includes/db.php';
require_once '../includes/billetterie.php';
require_once '../includes/upload-logo.php';
require_once 'includes/admin-header.php';
requireBureau();

// listes de ref pour les selects
$ECOLES = ['ECE','ESCE','HEIP','INSEEC Bachelor','INSEEC BBA','INSEEC BTS','INSEEC GE','INSEEC MSc','Sup de Pub'];
$CAMPUS = ['Citroën','Citadelle'];
$EVT_TYPES = ['Corpo','BDE','Sport','RSE','Association'];
$MODES_INSCRIPTION = EVT_MODES;       // 6 modes (5 + externe)
$MODES_LABELS = EVT_MODES_LABELS;
$MODE_ICONS = [
    'aucune'                => '-',
    'email'                 => '✉',
    'connexion'             => '👤',
    'externe'               => '🔗',
    'billetterie_email'     => '💳',
    'billetterie_connexion' => '🎟',
];

// quelques fonctions utilitaires pour éviter de répéter du code
function organisateurFromStructure(PDO $pdo, string $type, ?int $id): string {
    if (!$id) return 'Corpo Omnes Lyon';
    if ($type === 'asso') {
        $s = $pdo->prepare("SELECT nom FROM associations WHERE id=?");
        $s->execute([$id]);
        return $s->fetchColumn() ?: 'Corpo Omnes Lyon';
    }
    if ($type === 'sport') {
        $s = $pdo->prepare("SELECT nom FROM sports WHERE id=?");
        $s->execute([$id]);
        return $s->fetchColumn() ?: 'Corpo Omnes Lyon';
    }
    return 'Corpo Omnes Lyon';
}

function buildCampusLegacy(array $campusInvites): string {
    $c = in_array('Citroën', $campusInvites);
    $d = in_array('Citadelle', $campusInvites);
    if ($c && $d) return 'Tous campus';
    if ($c) return 'Campus Citroën';
    if ($d) return 'Campus Citadelle';
    return 'Tous campus';
}

function parseInvites(array $post, string $key, array $all): array {
    if (isset($post['tout_' . $key])) return ['Tous'];
    $vals = array_filter($post[$key] ?? [], fn($v) => in_array($v, $all));
    return empty($vals) ? ['Tous'] : array_values($vals);
}

$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    global $ECOLES, $CAMPUS, $MODES_INSCRIPTION;
    $action = $_POST['action'] ?? '';

    // ajout d'un événement
    if ($action === 'add') {
        $titre       = trim($_POST['titre'] ?? '');
        $slug        = preg_replace('/[^a-z0-9]+/', '-', strtolower($titre)) . '-' . time();
        $structType  = $_POST['structure_type'] ?? 'corpo';
        $structId    = (int)($_POST['structure_id'] ?? 0) ?: null;
        // Les événements « Corpo (global) » sont rattachés à l'asso « Corpo OMNES »
        [$structType, $structId] = resolveCorpoStructure($pdo, $structType, $structId);
        $organisateur = organisateurFromStructure($pdo, $structType, $structId);

        $modeInsc    = evt_normalize_mode($_POST['mode_inscription'] ?? 'aucune');
        $lienBillet  = trim($_POST['lien_billetterie'] ?? '');
        $emailContact = trim($_POST['email_contact'] ?? '');
        $inscMsg     = trim($_POST['inscription_message'] ?? '');

        $ecolesInv   = parseInvites($_POST, 'ecoles_invitees', $ECOLES);
        $campusInv   = parseInvites($_POST, 'campus_invites',  $CAMPUS);
        $affichageTv = isset($_POST['affichage_tv']) ? 1 : 0;
        $campus      = buildCampusLegacy($campusInv);

        $prix       = evt_mode_is_paid($modeInsc) ? (float)($_POST['prix'] ?? 0) : 0;
        $maxBillets = max(1, min(20, (int)($_POST['max_billets_par_personne'] ?? 1)));
        $inscOuv    = corpo_datetime_from_input($_POST['inscriptions_ouvertes_le'] ?? '');
        $inscFer    = corpo_datetime_from_input($_POST['inscriptions_fermees_le'] ?? '');
        $ouvertExt  = isset($_POST['ouvert_externes']) ? 1 : 0;

        $canSubmitEvt = isAdminCorpo();
        if (!$canSubmitEvt) {
            if ($structType === 'sport' && $structId) {
                $canSubmitEvt = canManageStructureResource($pdo, 'sport', $structId, 'evenement');
            } elseif (($structType === 'asso' || $structType === 'corpo') && $structId) {
                $canSubmitEvt = canManageStructureResource($pdo, 'asso', $structId, 'evenement');
            } elseif ($structType === 'asso' && !$structId) {
                $canSubmitEvt = false;
            }
        }

        if (!$canSubmitEvt) {
            $flash = '<div class="flash flash--err">Tu n\'es pas autorisé à créer un événement pour cette structure.</div>';
        } elseif (isAdminCorpo()) {
            $banniere = evt_upload_banniere(null);
            $pdo->prepare(
                "INSERT INTO evenements
                   (slug, titre, date, date_fin, heure, heure_fin, lieu, campus,
                    organisateur, structure_type, structure_id, type, description,
                    mode_inscription, lien_billetterie, email_contact, inscription_message,
                    places, prix, max_billets_par_personne, inscriptions_ouvertes_le, inscriptions_fermees_le, ouvert_externes, icon, banniere,
                    ecoles_invitees, campus_invites, affichage_tv, statut, auteur_id)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'publie',?)"
            )->execute([
                $slug, $titre,
                $_POST['date'] ?? '', trim($_POST['date_fin'] ?? '') ?: null,
                trim($_POST['heure'] ?? ''), trim($_POST['heure_fin'] ?? '') ?: null,
                trim($_POST['lieu'] ?? ''), $campus, $organisateur,
                $structType, $structId,
                $_POST['type'] ?? 'Corpo',
                trim($_POST['description'] ?? ''),
                $modeInsc, $lienBillet ?: null, $emailContact ?: null, $inscMsg ?: null,
                (int)($_POST['places'] ?? 0),
                $prix, $maxBillets,
                $inscOuv ?: null, $inscFer ?: null, $ouvertExt,
                evt_normalize_icon($_POST['icon'] ?? null),
                $banniere,
                json_encode($ecolesInv, JSON_UNESCAPED_UNICODE),
                json_encode($campusInv, JSON_UNESCAPED_UNICODE),
                $affichageTv,
                currentUserId(),
            ]);
            $flash = '<div class="flash flash--ok">Événement publié. <a href="evenement.php?id=' . (int)$pdo->lastInsertId() . '">⚙ Configurer l\'inscription / la billetterie →</a></div>';
        } else {
            $payload = json_encode([
                'titre' => $titre, 'date' => $_POST['date'] ?? '',
                'date_fin' => trim($_POST['date_fin'] ?? '') ?: null,
                'heure' => trim($_POST['heure'] ?? ''),
                'heure_fin' => trim($_POST['heure_fin'] ?? '') ?: null,
                'lieu' => trim($_POST['lieu'] ?? ''),
                'organisateur' => $organisateur, 'type' => $_POST['type'] ?? '',
                'description' => trim($_POST['description'] ?? ''),
                'mode_inscription' => $modeInsc,
                'lien_billetterie' => $lienBillet,
                'email_contact'    => $emailContact,
                'inscription_message' => $inscMsg,
                'ecoles_invitees' => $ecolesInv, 'campus_invites' => $campusInv,
                'affichage_tv' => $affichageTv,
            ]);
            $pdo->prepare("INSERT INTO demandes_validation (user_id,type,structure_type,structure_id,payload) VALUES (?,?,?,?,?)")
                ->execute([currentUserId(), 'evenement', $structType, $structId, $payload]);
            $flash = '<div class="flash flash--warn">Soumis à validation Corpo.</div>';
        }
    }

    // modif d'un événement existant
    if ($action === 'update' && !empty($_POST['id'])) {
        $evtId = (int)$_POST['id'];
        $stEv  = $pdo->prepare('SELECT * FROM evenements WHERE id = ?');
        $stEv->execute([$evtId]);
        $rowEv = $stEv->fetch(PDO::FETCH_ASSOC);
        if (!$rowEv || !canManageEvenement($pdo, $rowEv)) {
            $flash = '<div class="flash flash--err">Tu n\'es pas autorisé à modifier cet événement.</div>';
        } else {
            if (isAdminCorpo()) {
                $structType  = $_POST['structure_type'] ?? 'corpo';
                $structId    = (int)($_POST['structure_id'] ?? 0) ?: null;
                [$structType, $structId] = resolveCorpoStructure($pdo, $structType, $structId);
            } else {
                $structType = (string)($rowEv['structure_type'] ?? 'asso');
                $structId   = (int)($rowEv['structure_id'] ?? 0) ?: null;
                [$structType, $structId] = resolveCorpoStructure($pdo, $structType, $structId);
            }
            $organisateur = organisateurFromStructure($pdo, $structType, $structId);

            $modeInsc    = evt_normalize_mode($_POST['mode_inscription'] ?? 'aucune');
            $lienBillet  = trim($_POST['lien_billetterie'] ?? '');
            $emailContact = trim($_POST['email_contact'] ?? '');
            $inscMsg     = trim($_POST['inscription_message'] ?? '');
            $prix        = evt_mode_is_paid($modeInsc) ? (float)($_POST['prix'] ?? 0) : 0;
            $maxBillets  = max(1, min(20, (int)($_POST['max_billets_par_personne'] ?? 1)));
            $inscOuv     = corpo_datetime_from_input($_POST['inscriptions_ouvertes_le'] ?? '');
            $inscFer     = corpo_datetime_from_input($_POST['inscriptions_fermees_le'] ?? '');
            $ouvertExt   = isset($_POST['ouvert_externes']) ? 1 : 0;

            $ecolesInv   = parseInvites($_POST, 'ecoles_invitees', $ECOLES);
            $campusInv   = parseInvites($_POST, 'campus_invites',  $CAMPUS);
            $affichageTv = isset($_POST['affichage_tv']) ? 1 : 0;
            $campus      = buildCampusLegacy($campusInv);
            $banniere    = evt_upload_banniere($rowEv['banniere'] ?? null);

            $payload = [
                'id' => $evtId,
                'titre' => trim($_POST['titre'] ?? ''),
                'date' => $_POST['date'] ?? '',
                'date_fin' => trim($_POST['date_fin'] ?? '') ?: null,
                'heure' => trim($_POST['heure'] ?? ''),
                'heure_fin' => trim($_POST['heure_fin'] ?? '') ?: null,
                'lieu' => trim($_POST['lieu'] ?? ''),
                'campus' => $campus,
                'organisateur' => $organisateur,
                'structure_type' => $structType,
                'structure_id' => $structId,
                'type' => $_POST['type'] ?? 'Corpo',
                'description' => trim($_POST['description'] ?? ''),
                'mode_inscription' => $modeInsc,
                'lien_billetterie' => $lienBillet,
                'email_contact' => $emailContact,
                'inscription_message' => $inscMsg,
                'places' => (int)($_POST['places'] ?? 0),
                'prix' => $prix,
                'max_billets_par_personne' => $maxBillets,
                'inscriptions_ouvertes_le' => $inscOuv ?: null,
                'inscriptions_fermees_le' => $inscFer ?: null,
                'ouvert_externes' => $ouvertExt,
                'icon' => evt_normalize_icon($_POST['icon'] ?? null),
                'banniere' => $banniere,
                'ecoles_invitees' => $ecolesInv,
                'campus_invites' => $campusInv,
                'affichage_tv' => $affichageTv,
            ];

            if (isAdminCorpo()) {
                evt_update_from_payload($pdo, $evtId, $payload);
                $flash = '<div class="flash flash--ok">Événement mis à jour.</div>';
            } else {
                $pdo->prepare(
                    "INSERT INTO demandes_validation (user_id, type, structure_type, structure_id, payload)
                     VALUES (?, 'evenement', ?, ?, ?)"
                )->execute([
                    currentUserId(),
                    $structType,
                    $structId,
                    json_encode($payload, JSON_UNESCAPED_UNICODE),
                ]);
                $flash = '<div class="flash flash--warn">Modification soumise à validation Corpo.</div>';
            }
        }
    }

    // suppression (réservé corpo)
    if ($action === 'delete' && isAdminCorpo()) {
        $pdo->prepare("DELETE FROM evenements WHERE id=?")->execute([(int)$_POST['id']]);
        $flash = '<div class="flash flash--ok">Événement supprimé.</div>';
    }

    // on bascule publie/en_attente
    if ($action === 'toggle_statut' && isAdminCorpo()) {
        $new = $_POST['statut'] === 'publie' ? 'en_attente' : 'publie';
        $pdo->prepare("UPDATE evenements SET statut=? WHERE id=?")->execute([$new, (int)$_POST['id']]);
        $flash = '<div class="flash flash--ok">Statut mis à jour.</div>';
    }
}

// on charge les events selon le périmètre de l'user
if (isAdminCorpo()) {
    $events = $pdo->query("SELECT * FROM evenements ORDER BY date DESC")->fetchAll();
} else {
    $assoIds  = getManagedAssoIds($pdo);
    $sportIds = getManagedSportIds($pdo);
    foreach (getExplicitDelegatedStructures('evenement') as $d) {
        if ($d['type'] === 'sport') {
            if (!in_array($d['id'], $sportIds, true)) {
                $sportIds[] = $d['id'];
            }
        } else {
            if (!in_array($d['id'], $assoIds, true)) {
                $assoIds[] = $d['id'];
            }
        }
    }
    foreach (getMemberships() as $m) {
        if (!_membershipHasResponsabilite($m, 'evenement')) {
            continue;
        }
        $mid = (int)($m['id'] ?? 0);
        if ($mid <= 0) {
            continue;
        }
        $mtype = (string)($m['type'] ?? '');
        if ($mtype === 'sport') {
            if (!in_array($mid, $sportIds, true)) {
                $sportIds[] = $mid;
            }
        } elseif (in_array($mtype, ['asso', 'bde', 'bds'], true)) {
            if (!in_array($mid, $assoIds, true)) {
                $assoIds[] = $mid;
            }
        }
    }
    $events   = [];
    if (!empty($assoIds)) {
        $ph   = implode(',', array_map('intval', $assoIds));
        $events = array_merge($events, $pdo->query("SELECT * FROM evenements WHERE structure_type='asso' AND structure_id IN ($ph) ORDER BY date DESC")->fetchAll());
    }
    if (!empty($sportIds)) {
        $ph   = implode(',', array_map('intval', $sportIds));
        $events = array_merge($events, $pdo->query("SELECT * FROM evenements WHERE structure_type='sport' AND structure_id IN ($ph) ORDER BY date DESC")->fetchAll());
    }
}

// structures dispo pour le formulaire de création
if (isAdminCorpo()) {
    $assos  = $pdo->query("SELECT id, nom, type, ecole FROM associations ORDER BY type, nom")->fetchAll();
    $sports = $pdo->query("SELECT id, nom FROM sports ORDER BY nom")->fetchAll();
} else {
    $mAssoIds  = getManagedAssoIds($pdo);
    $mSportIds = getManagedSportIds($pdo);
    $assos = $sports = [];
    if (!empty($mAssoIds)) {
        $ph  = implode(',', array_fill(0, count($mAssoIds), '?'));
        $stA = $pdo->prepare("SELECT id, nom, type, ecole FROM associations WHERE id IN ($ph) ORDER BY nom");
        $stA->execute($mAssoIds);
        $assos = $stA->fetchAll();
    }
    if (!empty($mSportIds)) {
        $ph  = implode(',', array_fill(0, count($mSportIds), '?'));
        $stS = $pdo->prepare("SELECT id, nom FROM sports WHERE id IN ($ph) ORDER BY nom");
        $stS->execute($mSportIds);
        $sports = $stS->fetchAll();
    }
    foreach (getExplicitDelegatedStructures('evenement') as $d) {
        if ($d['type'] === 'sport') {
            if (!in_array($d['id'], array_column($sports, 'id'), true)) {
                $st1 = $pdo->prepare('SELECT id, nom FROM sports WHERE id = ?');
                $st1->execute([$d['id']]);
                if ($row = $st1->fetch(PDO::FETCH_ASSOC)) {
                    $sports[] = $row;
                }
            }
        } else {
            if (!in_array($d['id'], array_column($assos, 'id'), true)) {
                $st1 = $pdo->prepare('SELECT id, nom, type, ecole FROM associations WHERE id = ?');
                $st1->execute([$d['id']]);
                if ($row = $st1->fetch(PDO::FETCH_ASSOC)) {
                    $assos[] = $row;
                }
            }
        }
    }
}

// calendrier scolaire pour afficher les dates d'exam/vacances en fond
$calEntries = $pdo->query("SELECT id, ecole, type, titre, date_debut, date_fin, promotions FROM calendrier_scolaire ORDER BY date_debut ASC")->fetchAll();
$CAL_TYPE_COLORS = [
    'vacances'            => '#3ECF8E',
    'examens'             => '#E52521',
    'rattrapages'         => '#FF9500',
    'rentree'             => '#007179',
    'evenement_academique'=> '#8B2FC9',
    'autre'               => '#888',
];
$CAL_TYPE_LABELS = [
    'vacances'            => 'Vacances',
    'examens'             => 'Examens',
    'rattrapages'         => 'Rattrapages',
    'rentree'             => 'Rentrée',
    'evenement_academique'=> 'Événement académique',
    'autre'               => 'Autre',
];

$jsCalEntries = array_map(function($e) use ($CAL_TYPE_COLORS, $CAL_TYPE_LABELS) {
    $promos = [];
    if (!empty($e['promotions'])) {
        $d = json_decode($e['promotions'], true);
        if (is_array($d)) $promos = $d;
    }
    return [
        'id'         => (int)$e['id'],
        'ecole'      => $e['ecole'],
        'type'       => $e['type'],
        'type_label' => $CAL_TYPE_LABELS[$e['type']] ?? $e['type'],
        'color'      => $CAL_TYPE_COLORS[$e['type']] ?? '#888',
        'titre'      => $e['titre'],
        'date_debut' => $e['date_debut'],
        'date_fin'   => $e['date_fin'] ?: $e['date_debut'],
        'promotions' => $promos,
    ];
}, $calEntries);

// format pour le JS du calendrier
$jsEvents = array_map(function($ev) {
    $ecoles = $ev['ecoles_invitees'] ? json_decode($ev['ecoles_invitees'], true) : ['Tous'];
    return [
        'id'          => (int)$ev['id'],
        'titre'       => $ev['titre'],
        'icon'        => $ev['icon'] ?? '',
        'date'        => $ev['date'],
        'date_fin'    => $ev['date_fin'] ?: $ev['date'],
        'heure'       => $ev['heure'] ?? '',
        'lieu'        => $ev['lieu'] ?? '',
        'organisateur'=> $ev['organisateur'] ?? '',
        'type'        => $ev['type'] ?? '',
        'ecoles'      => $ecoles,
        'statut'      => $ev['statut'] ?? 'publie',
    ];
}, $events);

// valeurs uniques pour les filtres
$organisateursDistincts = array_values(array_unique(array_filter(array_map(fn($e) => $e['organisateur'] ?? '', $events))));
sort($organisateursDistincts);
?>

<h1 class="admin-page-title">Événements</h1>
<?= $flash ?>

<?php if (!isAdminCorpo()): ?>
  <div class="flash flash--warn">Bureau / resp. événements : créations et modifications sont soumises à validation Corpo.</div>
<?php endif; ?>

<!-- ============ VUE D'ENSEMBLE - CALENDRIER + ÉVÉNEMENTS ============ -->
<div class="admin-card adcal-card">
  <div class="adcal-toolbar">
    <div class="adcal-nav">
      <button type="button" class="btn btn--sm" id="evt-prev" aria-label="Mois précédent">←</button>
      <button type="button" class="btn btn--sm" id="evt-today">Aujourd'hui</button>
      <button type="button" class="btn btn--sm" id="evt-next" aria-label="Mois suivant">→</button>
      <h2 class="adcal-title" id="evt-cal-title" aria-live="polite"></h2>
    </div>
    <div class="adcal-filters">
      <label class="adcal-filter">
        <span>École</span>
        <select id="evt-filter-ecole">
          <option value="">Toutes</option>
          <?php foreach ($ECOLES as $e): ?>
            <option value="<?= htmlspecialchars($e) ?>"><?= htmlspecialchars($e) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="adcal-filter">
        <span>Type</span>
        <select id="evt-filter-type">
          <option value="">Tous</option>
          <?php foreach ($EVT_TYPES as $t): ?>
            <option value="<?= $t ?>"><?= $t ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="adcal-filter">
        <span>Recherche</span>
        <input type="search" id="evt-filter-q" placeholder="titre, lieu, orga…"
               style="background:var(--surface);color:var(--text);border:1px solid var(--border);border-radius:var(--r-md);padding:.45rem .6rem;font-size:.85rem">
      </label>
      <button type="button" class="btn btn--sm" id="evt-reset" style="background:var(--surface);border-color:var(--border)">Réinitialiser</button>
    </div>
  </div>

  <div class="adcal-legend" aria-label="Légende">
    <span class="adcal-legend-item"><span class="adcal-dot" style="background:#8B2FC9"></span>Événement</span>
    <?php foreach ($CAL_TYPE_LABELS as $t => $lab): ?>
      <span class="adcal-legend-item">
        <span class="adcal-dot" style="background:<?= $CAL_TYPE_COLORS[$t] ?>"></span><?= htmlspecialchars($lab) ?>
      </span>
    <?php endforeach; ?>
    <span class="evt-cal-help" style="margin-left:auto;color:var(--text-muted);font-size:.75rem">
      💡 Clique sur un jour pour pré-remplir la date du formulaire ci-dessous.
    </span>
  </div>

  <div class="adcal-weekdays" aria-hidden="true">
    <div>Lun</div><div>Mar</div><div>Mer</div><div>Jeu</div><div>Ven</div><div>Sam</div><div>Dim</div>
  </div>
  <div class="adcal-grid" id="evt-cal-grid" role="grid" aria-labelledby="evt-cal-title"></div>
</div>

<!-- ============ FORMULAIRE AJOUT ============ -->
<div class="admin-card">
  <h2>Ajouter un événement</h2>
  <form method="post" class="admin-form" id="evt-add-form" enctype="multipart/form-data">
    <input type="hidden" name="action" value="add">

    <!-- Titre + icône + bannière -->
    <div class="form-row">
      <div class="form-col" style="flex:3"><label>Titre</label><input type="text" name="titre" required></div>
      <div class="form-col">
        <label>Icône (emoji)</label>
        <input type="text" name="icon" class="evt-emoji-input" value="🎉" maxlength="16" placeholder="🎉">
        <small style="color:var(--text-muted);font-size:.75rem">Un emoji affiché sur les cartes et la page publique.</small>
      </div>
    </div>
    <div class="form-row">
      <div class="form-col" style="flex:2">
        <label>Bannière <small style="color:var(--text-muted)">(JPG, PNG, WebP — max 5 Mo)</small></label>
        <input type="file" name="banniere_file" accept="image/jpeg,image/png,image/webp,image/gif">
      </div>
      <div class="form-col" style="flex:2">
        <label>ou URL de bannière</label>
        <input type="url" name="banniere_url" placeholder="https://…">
      </div>
    </div>

    <!-- Dates + heures + lieu -->
    <div class="form-row">
      <div class="form-col"><label>Date début</label><input type="date" name="date" id="evt-date" required></div>
      <div class="form-col"><label>Date fin <small style="color:var(--text-muted)">(opt.)</small></label><input type="date" name="date_fin" id="evt-date-fin"></div>
      <div class="form-col"><label>Heure début</label><input type="text" name="heure" placeholder="20h00"></div>
      <div class="form-col"><label>Heure fin <small style="color:var(--text-muted)">(opt.)</small></label><input type="text" name="heure_fin" placeholder="23h00"></div>
      <div class="form-col" style="flex:2"><label>Lieu</label><input type="text" name="lieu"></div>
    </div>

    <!-- Bandeau de contexte (rempli dynamiquement) -->
    <div class="evt-context" id="evt-context" hidden></div>

    <!-- Structure = organisateur + type -->
    <div class="form-row">
      <div class="form-col">
        <label>Type de structure</label>
        <select name="structure_type" id="evtTypeSelect" onchange="syncEvtStructList(this.value)">
          <?php if (isAdminCorpo()): ?>
            <option value="corpo">Corpo (global)</option>
          <?php endif; ?>
          <?php if (!empty($assos)):  ?><option value="asso" <?= !isAdminCorpo() ? 'selected':'' ?>>Association / BDE / BDS</option><?php endif; ?>
          <?php if (!empty($sports)): ?><option value="sport">Sport</option><?php endif; ?>
        </select>
      </div>
      <div class="form-col" style="flex:2">
        <label>Organisateur (structure concernée)</label>
        <select name="structure_id" id="evtStructList">
          <?php if (isAdminCorpo()): ?>
            <option value="0" data-type="corpo" data-label="Corpo Omnes Lyon">- Corpo Omnes Lyon -</option>
          <?php endif; ?>
          <?php foreach ($assos as $a): ?>
            <option value="<?= $a['id'] ?>" data-type="asso"
                    data-label="<?= htmlspecialchars($a['nom']) ?>"><?= htmlspecialchars($a['nom']) ?> (<?= htmlspecialchars($a['ecole']) ?>)</option>
          <?php endforeach; ?>
          <?php foreach ($sports as $s): ?>
            <option value="<?= $s['id'] ?>" data-type="sport" style="display:none"
                    data-label="<?= htmlspecialchars($s['nom']) ?>"><?= htmlspecialchars($s['nom']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-col">
        <label>Type d'événement</label>
        <select name="type">
          <?php foreach ($EVT_TYPES as $t): ?>
            <option><?= $t ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- Écoles invitées -->
    <div class="form-row">
      <div class="form-col" style="flex:2">
        <label>Écoles invitées</label>
        <div style="display:flex;flex-wrap:wrap;gap:.5rem .8rem;margin-top:.4rem">
          <label style="font-weight:600;color:var(--accent)">
            <input type="checkbox" name="tout_ecoles_invitees" id="tout_ecoles" onchange="toggleToutEcoles(this)"> Toutes
          </label>
          <?php foreach ($ECOLES as $e): ?>
            <label class="ecole-cb"><input type="checkbox" name="ecoles_invitees[]" value="<?= htmlspecialchars($e) ?>"> <?= htmlspecialchars($e) ?></label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="form-col">
        <label>Campus invités</label>
        <div style="display:flex;gap:.8rem;margin-top:.4rem">
          <?php foreach ($CAMPUS as $c): ?>
            <label><input type="checkbox" name="campus_invites[]" value="<?= htmlspecialchars($c) ?>" checked> <?= htmlspecialchars($c) ?></label>
          <?php endforeach; ?>
        </div>
        <div style="margin-top:1rem">
          <label><input type="checkbox" name="affichage_tv" checked> Afficher sur les écrans TV</label>
        </div>
      </div>
    </div>

    <!-- Description -->
    <div class="form-row">
      <div class="form-col"><label>Description</label><textarea name="description" rows="4"></textarea></div>
    </div>

    <!-- ============ INSCRIPTION & BILLETTERIE ============ -->
    <fieldset class="evt-insc-section">
      <legend>🎟 Inscription & Billetterie</legend>

      <div class="evt-insc-modes">
        <?php foreach ($MODES_INSCRIPTION as $m):
          $desc = [
            'aucune'                => "Pas d'inscription nécessaire. Juste l'affichage.",
            'email'                 => "Inscription gratuite par formulaire (nom, prénom, email). Billet QR émis.",
            'connexion'             => "Réservé aux membres connectés. Billet QR émis automatiquement.",
            'externe'               => "Renvoie vers une billetterie externe via un lien.",
            'billetterie_email'     => "Paiement SumUp + nom/prénom/email. Billet QR après paiement.",
            'billetterie_connexion' => "Paiement SumUp + connexion obligatoire. Billet QR lié au compte.",
          ][$m] ?? '';
        ?>
          <label class="evt-insc-mode-card">
            <input type="radio" name="mode_inscription" value="<?= $m ?>" <?= $m === 'aucune' ? 'checked' : '' ?>>
            <div class="evt-insc-mode-card__head">
              <span class="evt-insc-mode-card__icon"><?= $MODE_ICONS[$m] ?? '-' ?></span>
              <strong><?= htmlspecialchars($MODES_LABELS[$m]) ?></strong>
            </div>
            <small><?= $desc ?></small>
          </label>
        <?php endforeach; ?>
      </div>

      <!-- Options conditionnelles -->
      <div class="evt-insc-options">
        <div data-show-for="email,billetterie_email" style="display:none">
          <label>Email de réception (organisateur)
            <input type="email" name="email_contact" placeholder="contact@asso.fr">
          </label>
        </div>

        <div data-show-for="externe" style="display:none">
          <label>Lien billetterie externe
            <input type="url" name="lien_billetterie" placeholder="https://…">
          </label>
        </div>

        <div data-show-for="email,connexion,billetterie_email,billetterie_connexion" class="evt-insc-options__grid" style="display:none">
          <label>Places max <small>(0 = illimité)</small>
            <input type="number" name="places" value="0" min="0">
          </label>
          <label>Max billets / personne
            <input type="number" name="max_billets_par_personne" value="1" min="1" max="20">
          </label>
        </div>

        <div data-show-for="billetterie_email,billetterie_connexion" style="display:none">
          <label>Prix de base (€) <small style="color:var(--text-muted)">- utilisé si tu ne définis pas de tarifs détaillés</small>
            <input type="number" name="prix" value="0" min="0" step="0.01">
          </label>
          <p style="margin:.4rem 0 0;font-size:.78rem;color:var(--text-muted)">
            Pour créer plusieurs tarifs (Standard, Étudiant, Early bird…), tu pourras les ajouter
            après création depuis <em>Gérer l'événement → onglet Tarifs</em>.
          </p>
        </div>

        <div data-show-for="email,connexion,billetterie_email,billetterie_connexion" class="evt-insc-options__grid" style="display:none">
          <label>Ouverture des inscriptions
            <input type="datetime-local" name="inscriptions_ouvertes_le">
          </label>
          <label>Fermeture des inscriptions
            <input type="datetime-local" name="inscriptions_fermees_le">
          </label>
        </div>

        <div data-show-for="email,billetterie_email" style="display:none" class="evt-insc-toggle">
          <label class="evt-insc-toggle__label">
            <input type="checkbox" name="ouvert_externes" value="1" checked>
            <span>
              <strong>Ouvert aux personnes hors écoles invitées</strong>
              <small>Si décoché : seuls les étudiants des écoles cochées plus haut pourront s'inscrire (compte requis).</small>
            </span>
          </label>
        </div>

        <div data-show-for="connexion,billetterie_connexion" class="evt-insc-info" style="display:none">
          💡 Ce mode nécessite la connexion. L'inscription est <strong>automatiquement réservée aux écoles invitées</strong> sélectionnées plus haut.
        </div>

        <div data-show-for="email,connexion,externe,billetterie_email,billetterie_connexion" style="display:none">
          <label>Message d'information (affiché aux participants)
            <textarea name="inscription_message" rows="2" placeholder="Ex : merci de préciser votre promo."></textarea>
          </label>
        </div>

        <div data-show-for="billetterie_email,billetterie_connexion" class="evt-insc-info" style="display:none">
          🎟 Après création, tu pourras gérer plusieurs <strong>catégories de billets</strong> (Standard, Étudiant, Early bird…) et des <strong>codes promo</strong> depuis la page de l'événement.
        </div>
      </div>
    </fieldset>

    <button type="submit" class="btn btn--primary"><?= isAdminCorpo() ? 'Publier →' : 'Soumettre →' ?></button>
  </form>
</div>

<!-- ============ FILTRES LISTE ============ -->
<div class="admin-card evt-list-filters">
  <div class="evt-filters-bar">
    <label class="adcal-filter">
      <span>Recherche</span>
      <input type="search" id="list-q" placeholder="titre, lieu, orga…"
             style="background:var(--surface);color:var(--text);border:1px solid var(--border);border-radius:var(--r-md);padding:.45rem .6rem;font-size:.85rem">
    </label>
    <label class="adcal-filter">
      <span>Type</span>
      <select id="list-type">
        <option value="">Tous</option>
        <?php foreach ($EVT_TYPES as $t): ?><option value="<?= $t ?>"><?= $t ?></option><?php endforeach; ?>
      </select>
    </label>
    <label class="adcal-filter">
      <span>École</span>
      <select id="list-ecole">
        <option value="">Toutes</option>
        <?php foreach ($ECOLES as $e): ?><option value="<?= htmlspecialchars($e) ?>"><?= htmlspecialchars($e) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label class="adcal-filter">
      <span>Période</span>
      <select id="list-periode">
        <option value="upcoming">À venir + en cours</option>
        <option value="all">Tous</option>
        <option value="past">Passés</option>
      </select>
    </label>
    <label class="adcal-filter">
      <span>Statut</span>
      <select id="list-statut">
        <option value="">Tous</option>
        <option value="publie">Publié</option>
        <option value="en_attente">En attente</option>
        <option value="refuse">Refusé</option>
      </select>
    </label>
    <button type="button" class="btn btn--sm" id="list-reset" style="background:var(--surface);border-color:var(--border)">Réinitialiser</button>
  </div>
</div>

<!-- ============ LISTE ============ -->
<div class="admin-card" style="padding:0;overflow:hidden">
  <table class="admin-table" id="evt-list-table">
    <thead>
      <tr>
        <th>#</th><th>Titre</th><th>Date</th><th>Lieu</th>
        <th>Écoles</th><th>Inscr.</th><th>TV</th><th>Statut</th><th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($events)): ?>
        <tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:var(--s6)">Aucun événement.</td></tr>
      <?php endif; ?>
      <?php foreach ($events as $ev):
        $ecolesDecoded = $ev['ecoles_invitees'] ? json_decode($ev['ecoles_invitees'], true) : ['Tous'];
        $campusDecoded = $ev['campus_invites']  ? json_decode($ev['campus_invites'],  true) : ['Tous'];
        $mode = evt_normalize_mode($ev['mode_inscription'] ?? 'aucune');
        $modeLabel = $MODES_LABELS[$mode] ?? $mode;
      ?>
        <tr class="evt-row"
            data-date="<?= htmlspecialchars($ev['date']) ?>"
            data-date-fin="<?= htmlspecialchars($ev['date_fin'] ?: $ev['date']) ?>"
            data-type="<?= htmlspecialchars($ev['type'] ?? '') ?>"
            data-ecoles="<?= htmlspecialchars(implode('|', $ecolesDecoded)) ?>"
            data-statut="<?= htmlspecialchars($ev['statut']) ?>"
            data-search="<?= htmlspecialchars(mb_strtolower(($ev['titre'] ?? '').' '.($ev['lieu'] ?? '').' '.($ev['organisateur'] ?? ''))) ?>">
          <td data-label="#"><?= $ev['id'] ?></td>
          <td data-label="Titre">
            <strong><?= evt_icon_html($ev['icon'] ?? null, 'evt-emoji evt-emoji--sm') ?> <?= htmlspecialchars($ev['titre']) ?></strong>
            <br><small style="color:var(--text-muted)"><?= htmlspecialchars($ev['organisateur'] ?? '') ?></small>
          </td>
          <td data-label="Date">
            <?= date('d/m/Y', strtotime($ev['date'])) ?>
            <?php if (!empty($ev['heure'])): ?>
              <br><small><?= htmlspecialchars($ev['heure']) ?><?= !empty($ev['heure_fin']) ? ' → '.$ev['heure_fin'] : '' ?></small>
            <?php endif; ?>
          </td>
          <td data-label="Lieu" style="font-size:.78rem"><?= htmlspecialchars($ev['lieu'] ?? '') ?></td>
          <td data-label="Écoles" style="font-size:.75rem"><?= implode(', ', $ecolesDecoded) ?></td>
          <td data-label="Inscr.">
            <span class="evt-mode-badge evt-mode--<?= htmlspecialchars($mode) ?>" title="<?= htmlspecialchars($modeLabel) ?>">
              <?= $MODE_ICONS[$mode] ?? '-' ?>
            </span>
          </td>
          <td data-label="TV" style="text-align:center"><?= ($ev['affichage_tv'] ?? 1) ? '<span style="color:var(--green)">✓</span>' : '<span style="color:var(--text-muted)">–</span>' ?></td>
          <td data-label="Statut"><span class="badge <?= $ev['statut']==='publie'?'badge--ok':($ev['statut']==='en_attente'?'badge--pending':'badge--ko') ?>"><?= $ev['statut'] ?></span></td>
          <td data-label="Actions">
            <div class="actions">
              <a href="evenement.php?id=<?= $ev['id'] ?>" class="btn btn--sm btn--primary" title="Gérer l'événement (participants, scan, paiements)">⚙ Gérer</a>
              <?php
                // Lien vers la comptabilité filtrée par cet événement (si on connaît sa structure)
                $orgType = $ev['structure_type'] ?? '';
                $orgId   = (int)($ev['structure_id'] ?? 0);
                if ($orgType && $orgId && $orgType !== 'corpo'):
              ?>
                <a href="comptabilite.php?type=<?= urlencode($orgType) ?>&id=<?= $orgId ?>&tab=transactions&fevt=<?= (int)$ev['id'] ?>"
                   class="btn btn--sm" style="background:rgba(46,204,113,.18);border-color:#2ecc71;color:#2ecc71"
                   title="Voir la comptabilité de cet événement">💰 Compta</a>
              <?php endif; ?>
              <?php $canManageEv = canManageEvenement($pdo, $ev); ?>
              <?php if ($canManageEv): ?>
                <button class="btn btn--sm" onclick="toggleEdit('ev-<?= $ev['id'] ?>')"
                        style="background:var(--surface);border-color:var(--border)">Modifier</button>
              <?php endif; ?>
              <?php if (isAdminCorpo()): ?>
                <form method="post" style="display:inline">
                  <input type="hidden" name="action" value="toggle_statut">
                  <input type="hidden" name="id" value="<?= $ev['id'] ?>">
                  <input type="hidden" name="statut" value="<?= $ev['statut'] ?>">
                  <button class="btn btn--sm <?= $ev['statut']==='publie'?'btn--warn':'btn--success' ?>"><?= $ev['statut']==='publie'?'Dépublier':'Publier' ?></button>
                </form>
                <form method="post" onsubmit="return confirm('Supprimer ?')" style="display:inline">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $ev['id'] ?>">
                  <button class="btn btn--sm btn--danger">Suppr.</button>
                </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>

        <?php if (!empty($canManageEv)): ?>
        <!-- Édition inline -->
        <tr id="edit-ev-<?= $ev['id'] ?>" class="evt-edit-row" style="display:none">
          <td colspan="9" style="background:rgba(255,255,255,.02);padding:var(--s5)">
            <strong style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--blue-light)">
              Modifier - <?= htmlspecialchars($ev['titre']) ?>
            </strong>
            <form method="post" class="admin-form" style="margin-top:var(--s4)" enctype="multipart/form-data">
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="id" value="<?= $ev['id'] ?>">

              <div class="form-row">
                <div class="form-col" style="flex:3"><label>Titre</label><input type="text" name="titre" value="<?= htmlspecialchars($ev['titre']) ?>" required></div>
                <div class="form-col">
                  <label>Icône (emoji)</label>
                  <input type="text" name="icon" class="evt-emoji-input" value="<?= htmlspecialchars(evt_normalize_icon($ev['icon'] ?? null), ENT_QUOTES, 'UTF-8') ?>" maxlength="16" placeholder="🎉">
                </div>
              </div>
              <div class="form-row">
                <div class="form-col" style="flex:2">
                  <label>Bannière</label>
                  <?php $banPreview = evt_media_url($ev['banniere'] ?? null, '../'); ?>
                  <?php if ($banPreview): ?>
                    <img src="<?= htmlspecialchars($banPreview) ?>" alt="" style="display:block;max-width:220px;max-height:80px;object-fit:cover;border-radius:8px;margin-bottom:.5rem">
                  <?php endif; ?>
                  <input type="file" name="banniere_file" accept="image/jpeg,image/png,image/webp,image/gif">
                </div>
                <div class="form-col" style="flex:2">
                  <label>ou URL</label>
                  <input type="url" name="banniere_url" value="<?= !empty($ev['banniere']) && preg_match('#^https?://#i', (string)$ev['banniere']) ? htmlspecialchars($ev['banniere']) : '' ?>" placeholder="https://…">
                </div>
              </div>

              <div class="form-row">
                <div class="form-col"><label>Date début</label><input type="date" name="date" value="<?= $ev['date'] ?>" required></div>
                <div class="form-col"><label>Date fin</label><input type="date" name="date_fin" value="<?= $ev['date_fin'] ?? '' ?>"></div>
                <div class="form-col"><label>Heure début</label><input type="text" name="heure" value="<?= htmlspecialchars($ev['heure']??'') ?>"></div>
                <div class="form-col"><label>Heure fin</label><input type="text" name="heure_fin" value="<?= htmlspecialchars($ev['heure_fin']??'') ?>"></div>
                <div class="form-col" style="flex:2"><label>Lieu</label><input type="text" name="lieu" value="<?= htmlspecialchars($ev['lieu']??'') ?>"></div>
              </div>

              <div class="form-row">
                <div class="form-col">
                  <label>Type de structure</label>
                  <select name="structure_type" id="evtTypeSelect-<?= $ev['id'] ?>"
                          onchange="syncEvtStructListEdit(this.value, <?= $ev['id'] ?>)">
                    <option value="corpo" <?= ($ev['structure_type']??'')==='corpo'?'selected':'' ?>>Corpo (global)</option>
                    <?php if (!empty($assos)):  ?><option value="asso"  <?= ($ev['structure_type']??'')==='asso' ?'selected':'' ?>>Association / BDE / BDS</option><?php endif; ?>
                    <?php if (!empty($sports)): ?><option value="sport" <?= ($ev['structure_type']??'')==='sport'?'selected':'' ?>>Sport</option><?php endif; ?>
                  </select>
                </div>
                <div class="form-col" style="flex:2">
                  <label>Organisateur (structure)</label>
                  <select name="structure_id" id="evtStructList-<?= $ev['id'] ?>">
                    <option value="0" data-type="corpo">- Corpo Omnes Lyon -</option>
                    <?php foreach ($assos as $a): ?>
                      <option value="<?= $a['id'] ?>" data-type="asso"
                              <?= $ev['structure_id']==$a['id']?' selected':'' ?>><?= htmlspecialchars($a['nom']) ?> (<?= htmlspecialchars($a['ecole']) ?>)</option>
                    <?php endforeach; ?>
                    <?php foreach ($sports as $s): ?>
                      <option value="<?= $s['id'] ?>" data-type="sport" style="display:none"
                              <?= $ev['structure_id']==$s['id']?' selected':'' ?>><?= htmlspecialchars($s['nom']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-col">
                  <label>Type d'événement</label>
                  <select name="type">
                    <?php foreach ($EVT_TYPES as $t): ?>
                      <option<?= ($ev['type']??'')===$t?' selected':'' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <div class="form-row">
                <div class="form-col" style="flex:2">
                  <label>Écoles invitées</label>
                  <div style="display:flex;flex-wrap:wrap;gap:.5rem .8rem;margin-top:.4rem">
                    <?php $estTous = $ecolesDecoded === ['Tous']; ?>
                    <label style="font-weight:600;color:var(--accent)">
                      <input type="checkbox" name="tout_ecoles_invitees" id="tout_ecoles_<?= $ev['id'] ?>"
                             onchange="toggleToutEcolesEdit(this, <?= $ev['id'] ?>)"
                             <?= $estTous ? 'checked' : '' ?>> Toutes
                    </label>
                    <?php foreach ($ECOLES as $e): ?>
                      <label class="ecole-cb-<?= $ev['id'] ?>">
                        <input type="checkbox" name="ecoles_invitees[]" value="<?= htmlspecialchars($e) ?>"
                               <?= (!$estTous && in_array($e, $ecolesDecoded)) ? 'checked' : '' ?>
                               <?= $estTous ? 'disabled' : '' ?>>
                        <?= htmlspecialchars($e) ?>
                      </label>
                    <?php endforeach; ?>
                  </div>
                </div>
                <div class="form-col">
                  <label>Campus invités</label>
                  <div style="display:flex;gap:.8rem;margin-top:.4rem">
                    <?php foreach ($CAMPUS as $c): ?>
                      <label>
                        <input type="checkbox" name="campus_invites[]" value="<?= htmlspecialchars($c) ?>"
                               <?= in_array($c, $campusDecoded) || $campusDecoded === ['Tous'] ? 'checked' : '' ?>>
                        <?= htmlspecialchars($c) ?>
                      </label>
                    <?php endforeach; ?>
                  </div>
                  <div style="margin-top:1rem">
                    <label>
                      <input type="checkbox" name="affichage_tv" <?= ($ev['affichage_tv'] ?? 1) ? 'checked' : '' ?>>
                      Afficher sur les écrans TV
                    </label>
                  </div>
                </div>
              </div>

              <div class="form-row">
                <div class="form-col" style="flex:0;min-width:300px">
                  <label>Mode d'inscription</label>
                  <select name="mode_inscription" class="mode-insc-edit" data-evid="<?= $ev['id'] ?>" onchange="onModeInscChangeEdit(this)">
                    <?php foreach ($MODES_INSCRIPTION as $m): ?>
                      <option value="<?= $m ?>" <?= $mode===$m?'selected':'' ?>>
                        <?= htmlspecialchars($MODES_LABELS[$m]) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>

                  <div class="mode-wrap-edit email-wrap-edit" data-evid="<?= $ev['id'] ?>" data-show-for="email,billetterie_email"
                       style="margin-top:.5rem;<?= in_array($mode, ['email','billetterie_email'], true)?'':'display:none' ?>">
                    <label>Email de réception</label>
                    <input type="email" name="email_contact" value="<?= htmlspecialchars($ev['email_contact']??'') ?>" placeholder="contact@asso.fr">
                  </div>
                  <div class="mode-wrap-edit billet-wrap-edit" data-evid="<?= $ev['id'] ?>" data-show-for="externe"
                       style="margin-top:.5rem;<?= $mode==='externe'?'':'display:none' ?>">
                    <label>Lien billetterie externe</label>
                    <input type="url" name="lien_billetterie" value="<?= htmlspecialchars($ev['lien_billetterie']??'') ?>" placeholder="https://…">
                  </div>
                  <div class="mode-wrap-edit places-wrap-edit" data-evid="<?= $ev['id'] ?>" data-show-for="email,connexion,billetterie_email,billetterie_connexion"
                       style="margin-top:.5rem;<?= in_array($mode, ['email','connexion','billetterie_email','billetterie_connexion'], true)?'':'display:none' ?>">
                    <label>Places max</label>
                    <input type="number" name="places" value="<?= (int)($ev['places'] ?? 0) ?>" min="0">
                  </div>
                  <div class="mode-wrap-edit prix-wrap-edit" data-evid="<?= $ev['id'] ?>" data-show-for="billetterie_email,billetterie_connexion"
                       style="margin-top:.5rem;<?= in_array($mode, ['billetterie_email','billetterie_connexion'], true)?'':'display:none' ?>">
                    <label>Prix de base (€) <small style="color:var(--text-muted)">- ignoré si des tarifs détaillés sont définis</small></label>
                    <input type="number" name="prix" value="<?= htmlspecialchars((string)($ev['prix'] ?? 0)) ?>" min="0" step="0.01">
                    <small style="display:block;margin-top:4px;color:var(--text-muted);font-size:.78rem">
                      Pour des tarifs multiples (Étudiant, VIP…), va dans
                      <a href="evenement.php?id=<?= (int)$ev['id'] ?>#tarifs" style="color:var(--purple-light);text-decoration:underline">Gérer l'événement → onglet Tarifs</a>.
                    </small>
                    <label style="margin-top:.4rem">Max billets / personne</label>
                    <input type="number" name="max_billets_par_personne" value="<?= (int)($ev['max_billets_par_personne'] ?? 1) ?>" min="1" max="20">
                  </div>
                  <div class="mode-wrap-edit ouv-wrap-edit" data-evid="<?= $ev['id'] ?>" data-show-for="email,connexion,billetterie_email,billetterie_connexion"
                       style="margin-top:.5rem;<?= in_array($mode, ['email','connexion','billetterie_email','billetterie_connexion'], true)?'':'display:none' ?>">
                    <label>Ouverture des inscriptions</label>
                    <input type="datetime-local" name="inscriptions_ouvertes_le" value="<?= htmlspecialchars(corpo_datetime_to_input($ev['inscriptions_ouvertes_le'] ?? '')) ?>">
                    <label style="margin-top:.4rem">Fermeture des inscriptions</label>
                    <input type="datetime-local" name="inscriptions_fermees_le" value="<?= htmlspecialchars(corpo_datetime_to_input($ev['inscriptions_fermees_le'] ?? '')) ?>">
                  </div>
                  <div class="mode-wrap-edit ext-wrap-edit" data-evid="<?= $ev['id'] ?>" data-show-for="email,billetterie_email"
                       style="margin-top:.5rem;<?= in_array($mode, ['email','billetterie_email'], true)?'':'display:none' ?>">
                    <label style="display:flex;gap:.5rem;align-items:flex-start;cursor:pointer">
                      <input type="checkbox" name="ouvert_externes" value="1" <?= (int)($ev['ouvert_externes'] ?? 1) === 1 ? 'checked' : '' ?>>
                      <span><strong>Ouvert aux externes</strong><br><small style="color:var(--text-muted)">Si décoché : compte + école éligible obligatoires.</small></span>
                    </label>
                  </div>
                  <div class="mode-wrap-edit inscmsg-wrap-edit" data-evid="<?= $ev['id'] ?>" data-show-for="email,connexion,externe,billetterie_email,billetterie_connexion"
                       style="margin-top:.5rem;<?= $mode!=='aucune'?'':'display:none' ?>">
                    <label>Message d'information</label>
                    <textarea name="inscription_message" rows="2"><?= htmlspecialchars($ev['inscription_message']??'') ?></textarea>
                  </div>
                </div>
                <div class="form-col"><label>Description</label><textarea name="description" rows="6"><?= htmlspecialchars($ev['description']??'') ?></textarea></div>
              </div>

              <button type="submit" class="btn btn--primary"><?= isAdminCorpo() ? 'Enregistrer' : 'Soumettre à validation' ?></button>
              <button type="button" class="btn" onclick="toggleEdit('ev-<?= $ev['id'] ?>')"
                      style="background:var(--surface);border-color:var(--border)">Annuler</button>
            </form>
          </td>
        </tr>
        <?php endif; ?>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script id="evt-data" type="application/json"><?= json_encode([
    'events'  => $jsEvents,
    'cal'     => $jsCalEntries,
    'today'   => date('Y-m-d'),
    'months'  => ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'],
    'typeLabels' => $CAL_TYPE_LABELS,
], JSON_UNESCAPED_UNICODE) ?></script>

<script>
// select structures
const evtAssos  = <?= json_encode(array_map(fn($a) => ['id'=>$a['id'],'nom'=>$a['nom'],'ecole'=>$a['ecole']], $assos)) ?>;
const evtSports = <?= json_encode(array_map(fn($s) => ['id'=>$s['id'],'nom'=>$s['nom']], $sports)) ?>;

function syncEvtStructList(type) {
    const sel = document.getElementById('evtStructList');
    if (type !== 'corpo') {
        const list = type === 'sport' ? evtSports : evtAssos;
        sel.innerHTML = list.map(i =>
            `<option value="${i.id}" data-type="${type}" data-label="${i.nom}">${i.nom}${i.ecole ? ' (' + i.ecole + ')' : ''}</option>`
        ).join('');
    } else {
        sel.innerHTML = '<option value="0" data-type="corpo" data-label="Corpo Omnes Lyon">- Corpo Omnes Lyon -</option>';
    }
}
function syncEvtStructListEdit(type, evId) {
    const sel = document.getElementById('evtStructList-' + evId);
    if (type !== 'corpo') {
        const list = type === 'sport' ? evtSports : evtAssos;
        sel.innerHTML = list.map(i =>
            `<option value="${i.id}" data-type="${type}">${i.nom}${i.ecole ? ' (' + i.ecole + ')' : ''}</option>`
        ).join('');
    } else {
        sel.innerHTML = '<option value="0" data-type="corpo">- Corpo Omnes Lyon -</option>';
    }
}

// affiche/masque les champs selon le mode d'inscription
function onModeInscChange(modeValue) {
    const v = modeValue || (document.querySelector('input[name="mode_inscription"]:checked') || {}).value || 'aucune';
    document.querySelectorAll('#evt-add-form [data-show-for]').forEach(el => {
        const list = (el.dataset.showFor || '').split(',');
        el.style.display = list.includes(v) ? '' : 'none';
    });
    // Marque visuellement la carte sélectionnée
    document.querySelectorAll('#evt-add-form .evt-insc-mode-card').forEach(card => {
        const inp = card.querySelector('input[type="radio"]');
        card.classList.toggle('is-selected', inp && inp.checked);
    });
}
document.addEventListener('change', e => {
    if (e.target && e.target.name === 'mode_inscription') onModeInscChange(e.target.value);
});
function onModeInscChangeEdit(sel) {
    const v = sel.value;
    const id = sel.dataset.evid;
    document.querySelectorAll('.mode-wrap-edit[data-evid="'+id+'"]').forEach(el => {
        const list = (el.dataset.showFor || '').split(',');
        el.style.display = list.includes(v) ? '' : 'none';
    });
}

// coche/décoche toutes les écoles d'un coup
function toggleToutEcoles(cb) {
    document.querySelectorAll('.ecole-cb input').forEach(i => {
        i.disabled = cb.checked;
        if (cb.checked) i.checked = false;
    });
}
function toggleToutEcolesEdit(cb, evId) {
    document.querySelectorAll('.ecole-cb-' + evId + ' input').forEach(i => {
        i.disabled = cb.checked;
        if (cb.checked) i.checked = false;
    });
}

// ouvre/ferme le formulaire d'édition inline
function toggleEdit(id) {
    const row = document.getElementById('edit-' + id);
    if (!row) return;
    const isHidden = row.style.display === 'none';
    document.querySelectorAll('tr[id^="edit-"]').forEach(r => r.style.display = 'none');
    row.style.display = isHidden ? '' : 'none';
    if (isHidden) row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// mini calendrier pour aider à planifier les événements
(function () {
  const data = JSON.parse(document.getElementById('evt-data').textContent);
  const EVENTS = data.events;
  const CAL = data.cal;
  const MONTHS = data.months;
  const TODAY = new Date(data.today + 'T00:00:00');

  const grid    = document.getElementById('evt-cal-grid');
  const titleEl = document.getElementById('evt-cal-title');
  const fEcole  = document.getElementById('evt-filter-ecole');
  const fType   = document.getElementById('evt-filter-type');
  const fQ      = document.getElementById('evt-filter-q');
  const dateInp = document.getElementById('evt-date');
  const dateFinInp = document.getElementById('evt-date-fin');
  const ctx     = document.getElementById('evt-context');

  let cursor = new Date(TODAY.getFullYear(), TODAY.getMonth(), 1);

  function ymd(d) {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const j = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${j}`;
  }
  function parseYMD(s) { const [y, m, d] = s.split('-').map(Number); return new Date(y, m - 1, d); }
  function inRange(date, s, e) { const t = date.getTime(); return t >= parseYMD(s).getTime() && t <= parseYMD(e).getTime(); }

  function eventMatchesFilters(e) {
    if (fType.value && e.type !== fType.value) return false;
    if (fEcole.value && !(e.ecoles.includes('Tous') || e.ecoles.includes(fEcole.value))) return false;
    const q = (fQ.value || '').trim().toLowerCase();
    if (q && !((e.titre||'').toLowerCase().includes(q) || (e.lieu||'').toLowerCase().includes(q) || (e.organisateur||'').toLowerCase().includes(q))) return false;
    return true;
  }
  function calMatchesFilters(c) {
    if (fEcole.value && c.ecole !== fEcole.value && c.ecole !== 'Tous') return false;
    return true;
  }
  function eventsForDay(d) {
    const s = ymd(d);
    return EVENTS.filter(e => eventMatchesFilters(e) && s >= e.date && s <= e.date_fin);
  }
  function calForDay(d) {
    const s = ymd(d);
    return CAL.filter(c => calMatchesFilters(c) && s >= c.date_debut && s <= c.date_fin);
  }

  function render() {
    grid.innerHTML = '';
    const y = cursor.getFullYear(), m = cursor.getMonth();
    titleEl.textContent = `${MONTHS[m]} ${y}`;

    const first = new Date(y, m, 1);
    let startDow = (first.getDay() + 6) % 7;
    const daysInMonth = new Date(y, m + 1, 0).getDate();
    const daysInPrev  = new Date(y, m, 0).getDate();

    const cells = [];
    for (let i = 0; i < startDow; i++) cells.push({ date: new Date(y, m - 1, daysInPrev - startDow + 1 + i), other: true });
    for (let d = 1; d <= daysInMonth; d++) cells.push({ date: new Date(y, m, d), other: false });
    while (cells.length < 42) {
      const last = cells[cells.length - 1].date;
      cells.push({ date: new Date(last.getFullYear(), last.getMonth(), last.getDate() + 1), other: true });
    }

    cells.forEach(({ date, other }) => {
      const cell = document.createElement('div');
      cell.className = 'adcal-cell' + (other ? ' is-other' : '');
      if (ymd(date) === ymd(TODAY)) cell.classList.add('is-today');
      if (dateInp.value && ymd(date) === dateInp.value) cell.classList.add('is-selected');
      cell.dataset.date = ymd(date);

      const num = document.createElement('div');
      num.className = 'adcal-cell__num';
      num.textContent = date.getDate();
      cell.appendChild(num);

      const evs = eventsForDay(date);
      const cals = calForDay(date);

      const list = document.createElement('div');
      list.className = 'adcal-cell__events';

      // D'abord les périodes scolaires (en arrière-plan)
      cals.slice(0, 2).forEach(c => {
        const bar = document.createElement('div');
        const isStart = ymd(date) === c.date_debut;
        const isEnd   = ymd(date) === c.date_fin;
        const isMulti = c.date_debut !== c.date_fin;
        bar.className = 'adcal-event'
          + (isMulti ? ' is-multi' : '')
          + (isStart && isMulti ? ' is-start' : '')
          + (isEnd && isMulti ? ' is-end' : '');
        bar.style.setProperty('--c', c.color);
        bar.textContent = c.titre;
        bar.title = `${c.type_label} : ${c.titre} - ${c.ecole}`;
        list.appendChild(bar);
      });

      // Puis les événements (en couleur "événement" violet)
      evs.slice(0, 3).forEach(e => {
        const bar = document.createElement('div');
        bar.className = 'adcal-event evt-pill';
        bar.style.setProperty('--c', '#8B2FC9');
        bar.textContent = `${e.icon ? e.icon + ' ' : ''}${e.titre}`;
        bar.title = `${e.titre} - ${e.organisateur}${e.lieu ? ' @ ' + e.lieu : ''}`;
        list.appendChild(bar);
      });

      const total = cals.length + evs.length;
      const shown = Math.min(2, cals.length) + Math.min(3, evs.length);
      if (total > shown) {
        const more = document.createElement('div');
        more.className = 'adcal-event-more';
        more.textContent = `+${total - shown} autre${total - shown > 1 ? 's' : ''}`;
        list.appendChild(more);
      }

      cell.appendChild(list);
      cell.addEventListener('click', () => onDayClick(date));
      grid.appendChild(cell);
    });

    refreshContext();
  }

  function onDayClick(date) {
    dateInp.value = ymd(date);
    if (!dateFinInp.value || dateFinInp.value < dateInp.value) dateFinInp.value = '';
    refreshContext();
    // Re-render pour marquer la cellule sélectionnée
    grid.querySelectorAll('.adcal-cell').forEach(c => c.classList.remove('is-selected'));
    const target = grid.querySelector('.adcal-cell[data-date="' + dateInp.value + '"]');
    if (target) target.classList.add('is-selected');
    dateInp.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  /* Bandeau de contexte sous les dates */
  function refreshContext() {
    if (!dateInp.value) { ctx.hidden = true; ctx.innerHTML = ''; return; }
    const d = parseYMD(dateInp.value);
    const dFin = dateFinInp.value ? parseYMD(dateFinInp.value) : d;

    const sameDayEvts = [];
    const overlapCals = [];
    EVENTS.forEach(e => {
      if (parseYMD(e.date).getTime() <= dFin.getTime() && parseYMD(e.date_fin).getTime() >= d.getTime()) {
        sameDayEvts.push(e);
      }
    });
    CAL.forEach(c => {
      if (parseYMD(c.date_debut).getTime() <= dFin.getTime() && parseYMD(c.date_fin).getTime() >= d.getTime()) {
        overlapCals.push(c);
      }
    });

    const warnings = [];
    overlapCals.forEach(c => {
      if (c.type === 'examens') {
        warnings.push(`⚠ Cette période est marquée <strong>${c.type_label}</strong> (${c.ecole}) - “${c.titre}”. À éviter.`);
      }
    });

    let html = '';
    if (warnings.length) {
      html += '<div class="evt-context__warn">' + warnings.map(w => '<div>' + w + '</div>').join('') + '</div>';
    }

    if (overlapCals.length) {
      html += '<div class="evt-context__row"><strong>📚 Calendrier scolaire :</strong> '
        + overlapCals.map(c => `<span class="evt-context__chip" style="--c:${c.color}">${escapeHtml(c.type_label)} – ${escapeHtml(c.ecole)} : ${escapeHtml(c.titre)}</span>`).join(' ')
        + '</div>';
    } else {
      html += '<div class="evt-context__row"><span style="color:var(--text-muted)">📚 Aucune période scolaire identifiée à ces dates.</span></div>';
    }

    if (sameDayEvts.length) {
      html += '<div class="evt-context__row"><strong>📅 Autres événements sur cette période (' + sameDayEvts.length + ') :</strong>'
        + '<ul class="evt-context__list">'
        + sameDayEvts.map(e =>
            `<li><span style="opacity:.7">${escapeHtml(e.icon||'')}</span> <strong>${escapeHtml(e.titre)}</strong> <small style="color:var(--text-muted)">– ${escapeHtml(e.organisateur)}${e.lieu ? ' @ ' + escapeHtml(e.lieu) : ''} (${formatDateFR(e.date)})</small></li>`
          ).join('')
        + '</ul></div>';
    } else {
      html += '<div class="evt-context__row"><span style="color:var(--green)">✓ Aucun autre événement programmé sur cette période.</span></div>';
    }

    ctx.innerHTML = html;
    ctx.hidden = false;
  }

  function escapeHtml(s) { return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
  function formatDateFR(s) { const [y, m, d] = s.split('-'); return `${d}/${m}/${y}`; }

  document.getElementById('evt-prev').addEventListener('click', () => { cursor.setMonth(cursor.getMonth() - 1); render(); });
  document.getElementById('evt-next').addEventListener('click', () => { cursor.setMonth(cursor.getMonth() + 1); render(); });
  document.getElementById('evt-today').addEventListener('click', () => { cursor = new Date(TODAY.getFullYear(), TODAY.getMonth(), 1); render(); });
  document.getElementById('evt-reset').addEventListener('click', () => {
    fEcole.value = ''; fType.value = ''; fQ.value = '';
    render();
  });
  [fEcole, fType].forEach(s => s.addEventListener('change', render));
  fQ.addEventListener('input', render);
  dateInp.addEventListener('change', () => { render(); });
  dateFinInp.addEventListener('change', refreshContext);

  document.addEventListener('DOMContentLoaded', () => {
    syncEvtStructList(document.getElementById('evtTypeSelect').value);
    onModeInscChange();
  });
  render();
})();

// filtres du tableau
(function () {
  const q       = document.getElementById('list-q');
  const fType   = document.getElementById('list-type');
  const fEcole  = document.getElementById('list-ecole');
  const fPer    = document.getElementById('list-periode');
  const fStatut = document.getElementById('list-statut');
  const reset   = document.getElementById('list-reset');
  const today   = new Date(); today.setHours(0,0,0,0);
  const todayStr = (() => {
    const y = today.getFullYear();
    const m = String(today.getMonth() + 1).padStart(2, '0');
    const j = String(today.getDate()).padStart(2, '0');
    return `${y}-${m}-${j}`;
  })();

  function apply() {
    const ql = (q.value || '').trim().toLowerCase();
    document.querySelectorAll('.evt-row').forEach(row => {
      const d   = row.dataset.date;
      const dF  = row.dataset.dateFin || d;
      const t   = row.dataset.type || '';
      const es  = (row.dataset.ecoles || '').split('|');
      const st  = row.dataset.statut || '';
      const txt = row.dataset.search || '';

      let ok = true;
      if (ql && !txt.includes(ql)) ok = false;
      if (fType.value && t !== fType.value) ok = false;
      if (fEcole.value && !(es.includes('Tous') || es.includes(fEcole.value))) ok = false;
      if (fStatut.value && st !== fStatut.value) ok = false;
      if (fPer.value === 'upcoming' && dF < todayStr) ok = false;
      if (fPer.value === 'past' && dF >= todayStr) ok = false;

      row.style.display = ok ? '' : 'none';
      // Cache aussi la ligne d'édition correspondante si la principale est masquée
      const editRow = document.getElementById('edit-ev-' + row.querySelector('td')?.textContent?.trim());
      // (la ligne edit n'a pas de match, on n'y touche que si le toggle est ouvert)
    });
  }

  [q, fType, fEcole, fPer, fStatut].forEach(el => el.addEventListener('input', apply));
  reset.addEventListener('click', () => {
    q.value = ''; fType.value = ''; fEcole.value = ''; fPer.value = 'upcoming'; fStatut.value = '';
    apply();
  });
  apply();
})();
</script>

<?php require_once 'includes/admin-footer.php'; ?>
