<?php

$adminTitle = 'Sports';
$adminPage  = 'sports';
require_once '../includes/db.php';
require_once '../includes/upload-logo.php';
require_once 'includes/admin-header.php';

if (!canAccessSportAdmin($pdo)) {
    header('Location: index.php?err=' . urlencode('Accès Sports réservé aux clubs/sports, BDS, BDE et fédérations.'));
    exit;
}

$userId = (int)$_SESSION['user_id'];
$flash  = '';

$allBds = $pdo->query(
    "SELECT id, nom, ecole FROM associations WHERE type='BDS' ORDER BY nom"
)->fetchAll();

$managedIds = getManagedSportIds($pdo);
$isCorpoAdmin = isAdminCorpo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $bdsId = (int)($_POST['parent_bds_id'] ?? 0);
        if (!$bdsId) {
            $flash = '<div class="flash flash--err">Tu dois rattacher le sport à un BDS.</div>';
        } elseif (!$isCorpoAdmin && !canCreateSportUnderBds($pdo, $bdsId)) {
            $flash = '<div class="flash flash--err">Tu n\'as pas les droits pour rattacher un sport à ce BDS (BDS, BDE de la même école ou fédération concernée).</div>';
        } else {
            $nomSport = trim($_POST['nom'] ?? '');
            $autoSlug = preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nomSport))) . '-' . substr(time(), -5);
            $logoAdd  = uploadLogo('sports', 'logo_file', 'logo_url');
            $payload = [
                'action'          => 'add_sport',
                'slug'            => $autoSlug,
                'nom'             => $nomSport,
                'icon'            => trim($_POST['icon']             ?? ''),
                'couleur'         => trim($_POST['couleur']          ?? '#5D0282'),
                'categorie'       => trim($_POST['categorie']        ?? 'club'),
                'description'     => trim($_POST['description']      ?? ''),
                'campus'          => trim($_POST['campus']           ?? 'Tous'),
                'places'          => (int)($_POST['places']          ?? 0),
                'lien_acces'      => trim($_POST['lien_acces']       ?? '') ?: null,
                'infra_partenaire'=> trim($_POST['infra_partenaire'] ?? '') ?: null,
                'parent_bds_id'   => $bdsId,
                'logo'            => $logoAdd,
            ];

            try {
                if ($isCorpoAdmin) {

                    $pdo->prepare(
                        "INSERT INTO sports (slug,nom,icon,couleur,categorie,description,campus,places,lien_acces,infra_partenaire,parent_bds_id,logo)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
                    )->execute([
                        $payload['slug'], $payload['nom'], $payload['icon'],
                        $payload['couleur'], $payload['categorie'], $payload['description'],
                        $payload['campus'], $payload['places'],
                        $payload['lien_acces'], $payload['infra_partenaire'], $bdsId,
                        $payload['logo'],
                    ]);
                    $flash = '<div class="flash flash--ok">Sport ajouté.</div>';
                } else {
                    $pdo->prepare(
                        "INSERT INTO demandes_validation (user_id, type, structure_type, structure_id, payload)
                         VALUES (?, 'sport', 'bds', ?, ?)"
                    )->execute([$userId, $bdsId, json_encode($payload, JSON_UNESCAPED_UNICODE)]);
                    $flash = '<div class="flash flash--ok">Demande envoyée - en attente de validation par la Corpo.</div>';
                }
            } catch (Throwable $e) {
                $flash = '<div class="flash flash--err">Erreur SQL à l\'ajout : '
                       . htmlspecialchars($e->getMessage())
                       . '<br><small>→ ouvre <a href="migrate.php" style="color:inherit;text-decoration:underline">admin/migrate.php</a> pour appliquer les migrations manquantes.</small></div>';
            }
        }
    }

    if ($action === 'update' && !empty($_POST['id'])) {
        $sportId = (int)$_POST['id'];
        $bdsId   = (int)($_POST['parent_bds_id'] ?? 0);

        if (!$isCorpoAdmin && !canManageSport($sportId, $pdo)) {
            $flash = '<div class="flash flash--err">Tu n\'as pas les droits sur ce sport.</div>';
        } elseif (!$bdsId) {
            $flash = '<div class="flash flash--err">Rattache le sport à un BDS.</div>';
        } elseif (!$isCorpoAdmin && !canCreateSportUnderBds($pdo, $bdsId)) {
            $flash = '<div class="flash flash--err">Tu n\'as pas les droits pour rattacher ce sport à ce BDS.</div>';
        } else {

            $prevSport = $pdo->prepare("SELECT slug, logo FROM sports WHERE id=?");
            $prevSport->execute([$sportId]);
            $prevSportRow = $prevSport->fetch();
            $logoUpd  = uploadLogo('sports', 'logo_file', 'logo_url', $prevSportRow['logo'] ?? null);
            $payload = [
                'action'          => 'update_sport',
                'id'              => $sportId,
                'slug'            => $prevSportRow['slug'] ?? '',
                'nom'             => trim($_POST['nom']              ?? ''),
                'icon'            => trim($_POST['icon']             ?? ''),
                'couleur'         => trim($_POST['couleur']          ?? '#5D0282'),
                'categorie'       => trim($_POST['categorie']        ?? 'club'),
                'description'     => trim($_POST['description']      ?? ''),
                'campus'          => trim($_POST['campus']           ?? 'Tous'),
                'places'          => (int)($_POST['places']          ?? 0),
                'lien_acces'      => trim($_POST['lien_acces']       ?? '') ?: null,
                'infra_partenaire'=> trim($_POST['infra_partenaire'] ?? '') ?: null,
                'parent_bds_id'   => $bdsId,
                'logo'            => $logoUpd,
            ];

            try {
                if ($isCorpoAdmin) {
                    $pdo->prepare(
                        "UPDATE sports SET slug=?,nom=?,icon=?,couleur=?,categorie=?,description=?,campus=?,places=?,lien_acces=?,infra_partenaire=?,parent_bds_id=?,logo=? WHERE id=?"
                    )->execute([
                        $payload['slug'], $payload['nom'], $payload['icon'],
                        $payload['couleur'], $payload['categorie'], $payload['description'],
                        $payload['campus'], $payload['places'],
                        $payload['lien_acces'], $payload['infra_partenaire'], $bdsId,
                        $payload['logo'], $sportId,
                    ]);
                    $flash = '<div class="flash flash--ok">Sport mis à jour.</div>';
                } else {
                    $pdo->prepare(
                        "INSERT INTO demandes_validation (user_id, type, structure_type, structure_id, payload)
                         VALUES (?, 'sport', 'sport', ?, ?)"
                    )->execute([$userId, $sportId, json_encode($payload, JSON_UNESCAPED_UNICODE)]);
                    $flash = '<div class="flash flash--ok">Demande de modification envoyée à la Corpo.</div>';
                }
            } catch (Throwable $e) {
                $flash = '<div class="flash flash--err">Erreur SQL à la modification : '
                       . htmlspecialchars($e->getMessage())
                       . '<br><small>→ ouvre <a href="migrate.php" style="color:inherit;text-decoration:underline">admin/migrate.php</a> pour appliquer les migrations manquantes.</small></div>';
            }
        }
    }

    if ($action === 'delete' && !empty($_POST['id'])) {

        if (!$isCorpoAdmin) {
            $flash = '<div class="flash flash--err">Seule la Corpo peut supprimer un sport.</div>';
        } else {
            $pdo->prepare("DELETE FROM sports WHERE id=?")->execute([(int)$_POST['id']]);
            $flash = '<div class="flash flash--ok">Sport supprimé.</div>';
        }
    }

    if ($action === 'add_score') {
        $sportId = (int)($_POST['sport_id'] ?? 0);
        if (!$isCorpoAdmin && !canManageSport($sportId, $pdo)) {
            $flash = '<div class="flash flash--err">Tu ne gères pas ce sport.</div>';
        } else {
            $pdo->prepare(
                "INSERT INTO sport_resultats (sport_id, adversaire, score, date, victoire) VALUES (?,?,?,?,?)"
            )->execute([
                $sportId,
                trim($_POST['adversaire'] ?? ''),
                trim($_POST['score']      ?? ''),
                trim($_POST['date']       ?? date('Y-m-d')),
                ($_POST['victoire'] ?? '') === '' ? null : (int)$_POST['victoire'],
            ]);
            $flash = '<div class="flash flash--ok">Résultat enregistré.</div>';
        }
    }

    if ($action === 'update_score' && !empty($_POST['id'])) {
        $scoreId = (int)$_POST['id'];
        $sportId = (int)($_POST['sport_id'] ?? 0);
        if (!$isCorpoAdmin && !canManageSport($sportId, $pdo)) {
            $flash = '<div class="flash flash--err">Tu ne gères pas ce sport.</div>';
        } else {
            $pdo->prepare(
                "UPDATE sport_resultats SET sport_id=?, adversaire=?, score=?, date=?, victoire=? WHERE id=?"
            )->execute([
                $sportId,
                trim($_POST['adversaire'] ?? ''),
                trim($_POST['score']      ?? ''),
                trim($_POST['date']       ?? date('Y-m-d')),
                ($_POST['victoire'] ?? '') === '' ? null : (int)$_POST['victoire'],
                $scoreId,
            ]);
            $flash = '<div class="flash flash--ok">Résultat mis à jour.</div>';
        }
    }

    if ($action === 'delete_score' && !empty($_POST['id'])) {
        $scoreId = (int)$_POST['id'];

        $row = $pdo->prepare("SELECT sport_id FROM sport_resultats WHERE id=?");
        $row->execute([$scoreId]);
        $sportId = (int)($row->fetchColumn() ?: 0);
        if (!$isCorpoAdmin && !canManageSport($sportId, $pdo)) {
            $flash = '<div class="flash flash--err">Tu ne gères pas ce sport.</div>';
        } else {
            $pdo->prepare("DELETE FROM sport_resultats WHERE id=?")->execute([$scoreId]);
            $flash = '<div class="flash flash--ok">Résultat supprimé.</div>';
        }
    }
}

if ($isCorpoAdmin || empty($managedIds)) {
    $sports = $pdo->query(
        "SELECT s.*, a.nom AS bds_nom FROM sports s
         LEFT JOIN associations a ON a.id = s.parent_bds_id
         ORDER BY s.categorie, s.nom"
    )->fetchAll();
} else {
    $pl = implode(',', array_map('intval', $managedIds));
    $sports = $pdo->query(
        "SELECT s.*, a.nom AS bds_nom FROM sports s
         LEFT JOIN associations a ON a.id = s.parent_bds_id
         WHERE s.id IN ($pl)
         ORDER BY s.categorie, s.nom"
    )->fetchAll();
}

$clubs  = array_values(array_filter($sports, fn($s) => $s['categorie'] === 'club'));
$libres = array_values(array_filter($sports, fn($s) => $s['categorie'] === 'individuel'));

if ($isCorpoAdmin || empty($managedIds)) {
    $scores = $pdo->query(
        "SELECT r.*, s.nom AS sport_nom FROM sport_resultats r
         JOIN sports s ON r.sport_id = s.id ORDER BY r.date DESC"
    )->fetchAll();
} else {
    $pl = implode(',', array_map('intval', $managedIds));
    $scores = $pdo->query(
        "SELECT r.*, s.nom AS sport_nom FROM sport_resultats r
         JOIN sports s ON r.sport_id = s.id
         WHERE r.sport_id IN ($pl)
         ORDER BY r.date DESC"
    )->fetchAll();
}

if ($isCorpoAdmin) {
    $bdsDispos = $allBds;
} else {

    $bdsDispos = [];
    foreach (getMemberships() as $m) {
        if ($m['type'] === 'bds' && $m['role'] === 'admin') {
            $bdsDispos[] = ['id' => $m['id'], 'nom' => $m['nom'] ?? '?', 'ecole' => ''];
        }
    }
    if (empty($bdsDispos)) $bdsDispos = $allBds;
}

$clubsPourScores = $clubs;

$tab = $_GET['tab'] ?? 'sports';
?>

<h1 class="admin-page-title">Gestion des sports</h1>

<?php if (!$isCorpoAdmin): ?>
  <div class="flash flash--info">
    Toute création ou modification de sport est soumise à <strong>validation Corpo</strong>. Seuls les scores sont en accès direct.
  </div>
  <div class="flash flash--scope">
    <strong>Périmètre de gestion</strong> - tu vois uniquement les sports dont tu as la responsabilité (admin sport, BDS, BDE, OMNES Sport ou Fédération).
  </div>
<?php endif; ?>

<?= $flash ?>

<div style="display:flex;gap:var(--s2);margin-bottom:var(--s6);border-bottom:1px solid var(--border)">
  <?php foreach ([
    'sports' => 'Sports clubs',
    'libres' => '🆓 Accès libre',
    'scores' => 'Scores',
  ] as $key => $label): ?>
    <a href="?tab=<?= $key ?>"
       style="padding:var(--s3) var(--s5);border-bottom:2px solid <?= $tab===$key?'var(--purple)':'transparent' ?>;
              color:<?= $tab===$key?'#fff':'var(--text-muted)' ?>;font-weight:<?= $tab===$key?'700':'400' ?>;
              text-decoration:none;font-size:.85rem;transition:color var(--ease)">
      <?= $label ?>
    </a>
  <?php endforeach; ?>
</div>

<?php  if ($tab === 'sports'): ?>

<div class="admin-card">
  <h2>Proposer un sport club</h2>
  <?php if (!$isCorpoAdmin): ?>
    <div class="flash flash--warn" style="margin-top:0">
      Ta demande sera transmise à la Corpo pour validation avant publication.
    </div>
  <?php endif; ?>
  <form method="post" enctype="multipart/form-data" class="admin-form">
    <input type="hidden" name="action" value="add">
    <div class="form-row">
      <div class="form-col"><label>Nom <span style="color:#ef4444">*</span></label><input type="text" name="nom" placeholder="Volleyball" required></div>
      <div class="form-col"><label>Icône (emoji)</label><input type="text" name="icon" value="" placeholder="🏐" maxlength="4"></div>
      <div class="form-col"><label>Couleur</label><input type="color" name="couleur" value="#5D0282" style="height:38px;padding:2px 4px"></div>
    </div>
    <div class="form-row">
      <div class="form-col">
        <label>BDS responsable <span style="color:#ef4444">*</span></label>
        <select name="parent_bds_id" required>
          <option value="">- Choisir un BDS -</option>
          <?php foreach ($bdsDispos as $b): ?>
            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['nom']) ?><?= $b['ecole'] ? ' ('.$b['ecole'].')' : '' ?></option>
          <?php endforeach; ?>
        </select>
        <small style="color:var(--text-muted);font-size:.7rem">Tout sport doit appartenir à un BDS.</small>
      </div>
      <div class="form-col"><label>Campus</label>
        <select name="campus"><option>Tous</option><option>Citroën</option><option>Citadelle</option></select>
      </div>
      <div class="form-col"><label>Places</label><input type="number" name="places" value="20" min="0"></div>
    </div>
    <div class="form-row"><div class="form-col"><label>Description</label><textarea name="description" rows="2"></textarea></div></div>
    <div class="form-row">
      <div class="form-col">
        <label>Logo</label>
        <input type="file" name="logo_file" accept="image elseif ($tab === 'libres'): ?>

<div class="admin-card">
  <h2>Proposer un sport en accès libre</h2>
  <?php if (!$isCorpoAdmin): ?>
    <div class="flash flash--warn" style="margin-top:0">
      Les sports en accès libre passent également par validation Corpo.
    </div>
  <?php endif; ?>
  <form method="post" enctype="multipart/form-data" class="admin-form">
    <input type="hidden" name="action" value="add">
    <input type="hidden" name="categorie" value="individuel">
    <div class="form-row">
      <div class="form-col"><label>Nom</label><input type="text" name="nom" placeholder="Ping-Pong" required></div>
      <div class="form-col"><label>Icône (emoji)</label><input type="text" name="icon" value="🏓" maxlength="4"></div>
      <div class="form-col"><label>Couleur</label><input type="color" name="couleur" value="
    </div>
    <div class="form-row">
      <div class="form-col">
        <label>BDS responsable <span style="color:#ef4444">*</span></label>
        <select name="parent_bds_id" required>
          <option value="">- Choisir un BDS -</option>
          <?php foreach ($bdsDispos as $b): ?>
            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['nom']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-col"><label>Campus</label>
        <select name="campus"><option>Tous</option><option>Citroën</option><option>Citadelle</option></select>
      </div>
      <div class="form-col"><label>Places (0 = illimité)</label><input type="number" name="places" value="0" min="0"></div>
    </div>
    <div class="form-row"><div class="form-col"><label>Description / Modalités d'accès</label><textarea name="description" rows="2"></textarea></div></div>
    <div class="form-row">
      <div class="form-col"><label>Lien WhatsApp / Accès</label><input type="url" name="lien_acces" placeholder="https://chat.whatsapp.com/…"></div>
      <div class="form-col"><label>Infrastructure partenaire</label><input type="text" name="infra_partenaire" placeholder="Urban Gym Lyon…"></div>
    </div>
    <div class="form-row">
      <div class="form-col">
        <label>Logo</label>
        <input type="file" name="logo_file" accept="image elseif ($tab === 'scores'): ?>

<div class="admin-card">
  <div class="flash flash--ok" style="margin-top:0">
    Les scores sont enregistrés <strong>directement</strong>, sans validation Corpo.
    Seuls les sports dans ton périmètre de gestion sont accessibles.
  </div>

  <?php if (empty($clubsPourScores)): ?>
    <p style="color:var(--text-muted)">Aucun sport dans ton périmètre pour entrer des scores.</p>
  <?php else: ?>
  <h2>Ajouter un résultat</h2>
  <form method="post" class="admin-form">
    <input type="hidden" name="action" value="add_score">
    <div class="form-row">
      <div class="form-col"><label>Sport</label>
        <select name="sport_id" required>
          <?php foreach ($clubsPourScores as $s): ?>
            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['nom']) ?> (<?= htmlspecialchars($s['bds_nom'] ?? '?') ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-col"><label>Adversaire</label><input type="text" name="adversaire" placeholder="INSA Lyon" required></div>
      <div class="form-col"><label>Score</label><input type="text" name="score" placeholder="72 – 58"></div>
      <div class="form-col"><label>Date</label><input type="date" name="date" value="<?= date('Y-m-d') ?>" required></div>
      <div class="form-col"><label>Résultat</label>
        <select name="victoire">
          <option value="">- Nul / N/A</option>
          <option value="1">Victoire</option>
          <option value="0">Défaite</option>
        </select>
      </div>
    </div>
    <button type="submit" class="btn btn--primary">Enregistrer →</button>
  </form>
  <?php endif; ?>
</div>

<div class="admin-card" style="padding:0;overflow:hidden">
  <div style="padding:var(--s5) var(--s6);border-bottom:1px solid var(--border)">
    <strong>Résultats sportifs (<?= count($scores) ?>)</strong>
  </div>
  <?php if (empty($scores)): ?>
    <p style="padding:var(--s6);color:var(--text-muted)">Aucun résultat enregistré.</p>
  <?php else: ?>
    <table class="admin-table">
      <thead><tr><th>Sport</th><th>Adversaire</th><th>Score</th><th>Date</th><th>Résultat</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($scores as $r):
          $canEditScore = $isCorpoAdmin || canManageSport((int)$r['sport_id'], $pdo);
        ?>
          <tr>
            <td><?= htmlspecialchars($r['sport_nom']) ?></td>
            <td><?= htmlspecialchars($r['adversaire']) ?></td>
            <td><strong><?= htmlspecialchars($r['score']) ?></strong></td>
            <td><?= date('d/m/Y', strtotime($r['date'])) ?></td>
            <td>
              <?php if ($r['victoire'] === null): ?>
                <span style="color:var(--text-muted)">Nul</span>
              <?php elseif ($r['victoire']): ?>
                <span style="color:#22c55e;font-weight:700">Victoire</span>
              <?php else: ?>
                <span style="color:#ef4444">Défaite</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($canEditScore): ?>
              <div class="actions">
                <button class="btn btn--sm" onclick="toggleEdit('sc-<?= $r['id'] ?>')"
                        style="background:var(--surface);border-color:var(--border)">✏️</button>
                <form method="post" onsubmit="return confirm('Supprimer ?')" style="display:inline">
                  <input type="hidden" name="action" value="delete_score">
                  <input type="hidden" name="id" value="<?= $r['id'] ?>">
                  <button class="btn btn--sm btn--danger">🗑️</button>
                </form>
              </div>
              <?php endif; ?>
            </td>
          </tr>
          <?php if ($canEditScore): ?>
          <tr id="edit-sc-<?= $r['id'] ?>" style="display:none">
            <td colspan="6" style="background:rgba(255,255,255,.02);padding:var(--s5)">
              <form method="post" class="admin-form">
                <input type="hidden" name="action" value="update_score">
                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                <div class="form-row">
                  <div class="form-col"><label>Sport</label>
                    <select name="sport_id">
                      <?php foreach ($clubsPourScores as $s): ?>
                        <option value="<?= $s['id'] ?>"<?= $s['id']==$r['sport_id']?' selected':'' ?>><?= htmlspecialchars($s['nom']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="form-col"><label>Adversaire</label><input type="text" name="adversaire" value="<?= htmlspecialchars($r['adversaire']) ?>" required></div>
                  <div class="form-col"><label>Score</label><input type="text" name="score" value="<?= htmlspecialchars($r['score']) ?>"></div>
                  <div class="form-col"><label>Date</label><input type="date" name="date" value="<?= $r['date'] ?>"></div>
                  <div class="form-col"><label>Résultat</label>
                    <select name="victoire">
                      <option value=""<?= $r['victoire']===null?' selected':'' ?>>Nul / N/A</option>
                      <option value="1"<?= $r['victoire']==='1'?' selected':'' ?>>Victoire</option>
                      <option value="0"<?= $r['victoire']==='0'?' selected':'' ?>>Défaite</option>
                    </select>
                  </div>
                </div>
                <button type="submit" class="btn btn--primary">💾 Enregistrer</button>
                <button type="button" class="btn" onclick="toggleEdit('sc-<?= $r['id'] ?>')"
                        style="background:var(--surface);border-color:var(--border)">Annuler</button>
              </form>
            </td>
          </tr>
          <?php endif; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php endif; ?>

<script>
function toggleEdit(id) {
  const row = document.getElementById('edit-' + id);
  if (!row) return;
  const isHidden = row.style.display === 'none';
  document.querySelectorAll('tr[id^="edit-"]').forEach(r => r.style.display = 'none');
  row.style.display = isHidden ? '' : 'none';
  if (isHidden) row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
</script>

<?php require_once 'includes/admin-footer.php'; ?>
