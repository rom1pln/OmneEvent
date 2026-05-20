<?php

require_once '../includes/db.php';
$adminTitle = 'Actualités';
$adminPage  = 'actualites';
require_once 'includes/admin-header.php';
requireBureau();

$flash = '';

function corpo_actu_has_visibilite(PDO $pdo): bool {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        $st = $pdo->prepare(
            "SELECT 1 FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'actualites' AND COLUMN_NAME = 'visibilite' LIMIT 1"
        );
        $st->execute();
        $cache = (bool)$st->fetchColumn();
    } catch (Throwable $e) {
        $cache = false;
    }
    return $cache;
}

function actu_user_can_manage(PDO $pdo, array $a): bool {
    if (isAdminCorpo()) {
        return true;
    }
    $st  = (string)($a['structure_type'] ?? '');
    $sid = (int)($a['structure_id'] ?? 0);
    if ($sid <= 0) {
        return false;
    }
    if ($st === 'sport') {
        return canManageStructureResource($pdo, 'sport', $sid, 'communication');
    }
    if ($st === 'asso') {
        return canManageStructureResource($pdo, 'asso', $sid, 'communication');
    }
    return false;
}

$periAssoIds  = isAdminCorpo() ? null : getManagedAssoIds($pdo);
$periSportIds = isAdminCorpo() ? null : getManagedSportIds($pdo);
if (!isAdminCorpo()) {
    foreach (getExplicitDelegatedStructures('communication') as $d) {
        if ($d['type'] === 'sport') {
            if (is_array($periSportIds) && !in_array($d['id'], $periSportIds, true)) {
                $periSportIds[] = $d['id'];
            }
        } else {
            if (is_array($periAssoIds) && !in_array($d['id'], $periAssoIds, true)) {
                $periAssoIds[] = $d['id'];
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $titre      = trim($_POST['titre'] ?? '');
        $contenu    = trim($_POST['contenu'] ?? '');
        $structType = $_POST['structure_type'] ?? 'corpo';
        $structId   = (int)($_POST['structure_id'] ?? 0) ?: null;
        $hasVis     = corpo_actu_has_visibilite($pdo);
        $visibilite = ($hasVis && (($_POST['visibilite'] ?? 'public') === 'membres')) ? 'membres' : 'public';

        $okScope = isAdminCorpo();
        if (!$okScope) {
            if ($structType === 'asso' && $structId) {
                $okScope = canManageStructureResource($pdo, 'asso', (int)$structId, 'communication');
            }
            if ($structType === 'sport' && $structId) {
                $okScope = canManageStructureResource($pdo, 'sport', (int)$structId, 'communication');
            }
        }
        if ($structType === 'corpo' && !isAdminCorpo()) {
            $okScope = false;
        }
        if ($visibilite === 'membres' && ($structType === 'corpo' || !$structId)) {
            $okScope = false;
        }

        if ($titre === '' || $contenu === '') {
            $flash = '<div class="flash flash--err">Titre et contenu obligatoires.</div>';
        } elseif (!$okScope) {
            $flash = '<div class="flash flash--err">Cette structure n\'est pas dans ton périmètre de gestion.</div>';
        } else {
            $membresOnly = ($visibilite === 'membres');

            if ($membresOnly && !isAdminCorpo()) {
                if ($hasVis) {
                    $stmt = $pdo->prepare(
                        "INSERT INTO actualites (titre, contenu, structure_type, structure_id, auteur_id, statut, visibilite)
                         VALUES (?,?,?,?,?,?,?)"
                    );
                    $stmt->execute([$titre, $contenu, $structType, $structId, currentUserId(), 'publie', 'membres']);
                } else {
                    $stmt = $pdo->prepare(
                        "INSERT INTO actualites (titre, contenu, structure_type, structure_id, auteur_id, statut) VALUES (?,?,?,?,?,?)"
                    );
                    $stmt->execute([$titre, $contenu, $structType, $structId, currentUserId(), 'publie']);
                }
                $flash = '<div class="flash flash--ok">Actualité réservée aux membres publiée (sans validation Corpo).</div>';
            } elseif (!isAdminCorpo()) {
                if ($hasVis) {
                    $stmt = $pdo->prepare(
                        "INSERT INTO actualites (titre, contenu, structure_type, structure_id, auteur_id, statut, visibilite)
                         VALUES (?,?,?,?,?,?,?)"
                    );
                    $stmt->execute([$titre, $contenu, $structType, $structId, currentUserId(), 'en_attente', 'public']);
                } else {
                    $stmt = $pdo->prepare(
                        "INSERT INTO actualites (titre, contenu, structure_type, structure_id, auteur_id, statut) VALUES (?,?,?,?,?,?)"
                    );
                    $stmt->execute([$titre, $contenu, $structType, $structId, currentUserId(), 'en_attente']);
                }
                $actuId = (int)$pdo->lastInsertId();
                $dv     = $pdo->prepare(
                    "INSERT INTO demandes_validation (user_id, type, structure_type, structure_id, payload) VALUES (?,?,?,?,?)"
                );
                $dv->execute([
                    currentUserId(),
                    'actualite',
                    $structType,
                    $structId,
                    json_encode(['titre' => $titre, 'contenu' => $contenu, 'actualite_id' => $actuId]),
                ]);
                $flash = '<div class="flash flash--warn">Actualité soumise à validation Corpo.</div>';
            } else {
                $statut = 'publie';
                if ($hasVis) {
                    $stmt = $pdo->prepare(
                        "INSERT INTO actualites (titre, contenu, structure_type, structure_id, auteur_id, statut, visibilite)
                         VALUES (?,?,?,?,?,?,?)"
                    );
                    $stmt->execute([$titre, $contenu, $structType, $structId, currentUserId(), $statut, $visibilite]);
                } else {
                    $stmt = $pdo->prepare(
                        "INSERT INTO actualites (titre, contenu, structure_type, structure_id, auteur_id, statut) VALUES (?,?,?,?,?,?)"
                    );
                    $stmt->execute([$titre, $contenu, $structType, $structId, currentUserId(), $statut]);
                }
                $flash = '<div class="flash flash--ok">Actualité publiée.</div>';
            }
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $flash = '<div class="flash flash--err">Actualité invalide.</div>';
        } else {
            $stRow = $pdo->prepare('SELECT * FROM actualites WHERE id=? LIMIT 1');
            $stRow->execute([$id]);
            $row = $stRow->fetch(PDO::FETCH_ASSOC);
            if (!$row || !actu_user_can_manage($pdo, $row)) {
                $flash = '<div class="flash flash--err">Action non autorisée.</div>';
            } else {
                $titre   = trim($_POST['titre'] ?? '');
                $contenu = trim($_POST['contenu'] ?? '');
                $statut  = $_POST['statut'] ?? 'en_attente';
                if (!isAdminCorpo()) {
                    $statut = (string)($row['statut'] ?? 'en_attente');
                }
                $hasVis = corpo_actu_has_visibilite($pdo);
                if (!isAdminCorpo()) {
                    $vis = $hasVis ? (string)($row['visibilite'] ?? 'public') : 'public';
                } else {
                    $vis = $hasVis && (($_POST['visibilite'] ?? '') === 'membres') ? 'membres' : 'public';
                }
                if ($titre && $contenu) {
                    if ($hasVis) {
                        $pdo->prepare('UPDATE actualites SET titre=?, contenu=?, statut=?, visibilite=? WHERE id=?')
                            ->execute([$titre, $contenu, $statut, $vis, $id]);
                    } else {
                        $pdo->prepare('UPDATE actualites SET titre=?, contenu=?, statut=? WHERE id=?')
                            ->execute([$titre, $contenu, $statut, $id]);
                    }
                    $flash = '<div class="flash flash--ok">Actualité mise à jour.</div>';
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $flash = '<div class="flash flash--err">Actualité invalide.</div>';
        } else {
            $stRow = $pdo->prepare('SELECT * FROM actualites WHERE id=? LIMIT 1');
            $stRow->execute([$id]);
            $row = $stRow->fetch(PDO::FETCH_ASSOC);
            if (!$row || !actu_user_can_manage($pdo, $row)) {
                $flash = '<div class="flash flash--err">Action non autorisée.</div>';
            } else {
                $pdo->prepare('DELETE FROM actualites WHERE id=?')->execute([$id]);
                $flash = '<div class="flash flash--ok">Actualité supprimée.</div>';
            }
        }
    }
}

if (isAdminCorpo()) {
    $actus = $pdo->query("SELECT a.*, u.username AS auteur FROM actualites a JOIN users u ON u.id=a.auteur_id ORDER BY a.created_at DESC")->fetchAll();
} else {
    $assoIds  = is_array($periAssoIds) ? $periAssoIds : [];
    $sportIds = is_array($periSportIds) ? $periSportIds : [];
    $actus    = [];
    if (!empty($assoIds)) {
        $ph   = implode(',', array_map('intval', $assoIds));
        $rows = $pdo->query("SELECT a.*, u.username AS auteur FROM actualites a JOIN users u ON u.id=a.auteur_id WHERE a.structure_type='asso' AND a.structure_id IN ($ph) ORDER BY a.created_at DESC")->fetchAll();
        $actus = array_merge($actus, $rows);
    }
    if (!empty($sportIds)) {
        $ph   = implode(',', array_map('intval', $sportIds));
        $rows = $pdo->query("SELECT a.*, u.username AS auteur FROM actualites a JOIN users u ON u.id=a.auteur_id WHERE a.structure_type='sport' AND a.structure_id IN ($ph) ORDER BY a.created_at DESC")->fetchAll();
        $actus = array_merge($actus, $rows);
    }

    $own = $pdo->prepare("SELECT a.*, u.username AS auteur FROM actualites a JOIN users u ON u.id=a.auteur_id WHERE a.auteur_id=? ORDER BY a.created_at DESC");
    $own->execute([currentUserId()]);
    $existIds = array_column($actus, 'id');
    foreach ($own->fetchAll() as $row) {
        if (!in_array($row['id'], $existIds)) $actus[] = $row;
    }
}

if (isAdminCorpo()) {
    $assos  = $pdo->query("SELECT id, nom, type FROM associations ORDER BY type, nom")->fetchAll();
    $sports = $pdo->query("SELECT id, nom FROM sports ORDER BY nom")->fetchAll();
} else {
    $mAssoIds  = is_array($periAssoIds) ? $periAssoIds : [];
    $mSportIds = is_array($periSportIds) ? $periSportIds : [];
    $assos = $sports = [];
    if (!empty($mAssoIds)) {
        $ph   = implode(',', array_fill(0, count($mAssoIds), '?'));
        $stA  = $pdo->prepare("SELECT id, nom, type FROM associations WHERE id IN ($ph) ORDER BY nom");
        $stA->execute($mAssoIds);
        $assos = $stA->fetchAll();
    }
    if (!empty($mSportIds)) {
        $ph   = implode(',', array_fill(0, count($mSportIds), '?'));
        $stS  = $pdo->prepare("SELECT id, nom FROM sports WHERE id IN ($ph) ORDER BY nom");
        $stS->execute($mSportIds);
        $sports = $stS->fetchAll();
    }
}
$hasVisCol = corpo_actu_has_visibilite($pdo);
?>

<h1 class="admin-page-title">Actualités</h1>

<?php if (!isAdminCorpo()): ?>
  <div class="flash flash--info">
    Les actualités <strong>publiques</strong> (site) sont soumises à validation Corpo.
    <?php if ($hasVisCol): ?>
      Les actualités <strong>réservées aux membres</strong> de la structure sont publiées tout de suite, sans validation Corpo.
    <?php endif; ?>
  </div>
  <?php
    $nbAssoPeri  = is_array($periAssoIds)  ? count($periAssoIds)  : 0;
    $nbSportPeri = is_array($periSportIds) ? count($periSportIds) : 0;
  ?>
  <div class="flash flash--scope">
    <strong>Périmètre de gestion</strong> - vous pouvez publier des actualités pour
    <?= $nbAssoPeri ?> association<?= $nbAssoPeri > 1 ? 's' : '' ?>
    et <?= $nbSportPeri ?> sport<?= $nbSportPeri > 1 ? 's' : '' ?> rattaché<?= $nbSportPeri > 1 ? 's' : '' ?>.
    La liste affichée ci-dessous est filtrée selon votre périmètre.
  </div>
<?php endif; ?>

<?= $flash ?>

<div class="admin-card">
  <h2>Publier une actualité</h2>
  <form method="post" class="admin-form">
    <input type="hidden" name="action" value="create">
    <div class="form-row">
      <div class="form-col" style="flex:2">
        <label>Titre</label>
        <input type="text" name="titre" placeholder="Titre de l'actualité" required>
      </div>
      <div class="form-col">
        <label>Type</label>
        <select name="structure_type" id="actTypeSelect" onchange="syncActuStructList(this.value)">
          <?php if (isAdminCorpo()): ?><option value="corpo">Corpo (général)</option><?php endif; ?>
          <?php if (!empty($assos)):  ?><option value="asso"  <?= !isAdminCorpo() ? 'selected':'' ?>>Asso / BDE / BDS</option><?php endif; ?>
          <?php if (!empty($sports)): ?><option value="sport">Sport</option><?php endif; ?>
        </select>
      </div>
      <div class="form-col">
        <label>Structure</label>
        <select name="structure_id" id="actStructList">
          <?php if (isAdminCorpo()): ?><option value="0">- Corpo Omnes -</option><?php endif; ?>
          <?php foreach ($assos as $a): ?>
            <option value="<?= $a['id'] ?>" data-type="asso"><?= htmlspecialchars($a['nom']) ?><?= !empty($a['type'])?' ('.$a['type'].')':'' ?></option>
          <?php endforeach; ?>
          <?php foreach ($sports as $s): ?>
            <option value="<?= $s['id'] ?>" data-type="sport" style="display:none"><?= htmlspecialchars($s['nom']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <script>
        function syncActuVisAndSubmitLabel() {
          const type = document.getElementById('actTypeSelect').value;
          const visRow = document.getElementById('actuVisRow');
          const visSel = document.getElementById('actuVisSelect');
          const btn = document.getElementById('actuSubmitBtn');
          if (visRow) {
            if (type === 'corpo') {
              visRow.style.display = 'none';
              if (visSel) visSel.value = 'public';
            } else {
              visRow.style.display = '';
            }
          }
          if (!btn) return;
          const isAdmin = <?= json_encode((bool)isAdminCorpo()) ?>;
          const membres = visSel && visSel.value === 'membres';
          if (isAdmin) btn.textContent = 'Publier';
          else if (membres) btn.textContent = 'Publier (membres uniquement)';
          else btn.textContent = 'Soumettre à validation';
        }
        function syncActuStructList(type) {
          const sel = document.getElementById('actStructList');
          [...sel.options].forEach(opt => {
            const t = opt.dataset.type || 'corpo';
            opt.style.display = (type === 'corpo') ? (opt.value === '0' ? '' : 'none')
                              : (t === type || opt.value === '0') ? '' : 'none';
          });
          const first = [...sel.options].find(o => o.style.display !== 'none');
          if (first) sel.value = first.value;
          syncActuVisAndSubmitLabel();
        }
        document.addEventListener('DOMContentLoaded', () => {
          syncActuStructList(document.getElementById('actTypeSelect').value);
          const visSel = document.getElementById('actuVisSelect');
          if (visSel) visSel.addEventListener('change', syncActuVisAndSubmitLabel);
        });
      </script>
    </div>
    <div class="form-row">
      <div class="form-col">
        <label>Contenu</label>
        <textarea name="contenu" rows="5" placeholder="Rédigez votre actualité..." required></textarea>
      </div>
    </div>
    <?php if ($hasVisCol): ?>
    <div class="form-row" id="actuVisRow">
      <div class="form-col">
        <label>Visibilité</label>
        <select name="visibilite" id="actuVisSelect">
          <option value="public">Publique (visible sur le site après validation)</option>
          <option value="membres">Réservée aux membres / adhérents (sans validation Corpo)</option>
        </select>
      </div>
    </div>
    <?php endif; ?>
    <button type="submit" class="btn btn--primary" id="actuSubmitBtn">
      <?= isAdminCorpo() ? 'Publier' : 'Soumettre à validation' ?>
    </button>
  </form>
</div>

<div class="admin-card" style="padding:0;overflow:hidden">
  <?php if (empty($actus)): ?>
    <p style="padding:var(--s8);text-align:center;color:var(--text-muted)">Aucune actualité.</p>
  <?php else: ?>
    <table class="admin-table">
      <thead>
        <tr>
          <th>Titre</th>
          <th>Structure</th>
          <th>Auteur</th>
          <th>Statut</th>
          <?php if ($hasVisCol): ?><th>Visibilité</th><?php endif; ?>
          <th>Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($actus as $a): ?>
          <?php
            $canManageActu = actu_user_can_manage($pdo, $a);
            $vrow          = $hasVisCol ? (string)($a['visibilite'] ?? 'public') : 'public';
            $actuTableColspan = $hasVisCol ? 7 : 6;
          ?>
          <tr>
            <td><strong><?= htmlspecialchars($a['titre']) ?></strong></td>
            <td style="font-size:.78rem">
              <?= htmlspecialchars($a['structure_type']) ?>
              <?php if ($a['structure_id']): ?>
            </td>
            <td style="font-size:.78rem"><?= htmlspecialchars($a['auteur']) ?></td>
            <td>
              <span class="badge <?= $a['statut']==='publie' ? 'badge--ok' : ($a['statut']==='en_attente' ? 'badge--pending' : 'badge--ko') ?>">
                <?= htmlspecialchars($a['statut']) ?>
              </span>
            </td>
            <?php if ($hasVisCol): ?>
            <td>
              <span class="badge <?= $vrow === 'membres' ? 'badge--pending' : 'badge--ok' ?>" style="font-size:.72rem">
                <?= $vrow === 'membres' ? 'Membres' : 'Public' ?>
              </span>
            </td>
            <?php endif; ?>
            <td style="font-size:.75rem;color:var(--text-muted)"><?= date('d/m/Y', strtotime($a['created_at'])) ?></td>
            <td>
              <?php if ($canManageActu): ?>
                <div class="actions">
                  <button type="button" class="btn btn--sm" onclick="toggleEdit('actu-<?= $a['id'] ?>')"
                          style="background:var(--surface);border-color:var(--border)">✏️</button>
                  <form method="post" onsubmit="return confirm('Supprimer ?')" style="display:inline">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $a['id'] ?>">
                    <button type="submit" class="btn btn--sm btn--danger">🗑️</button>
                  </form>
                </div>
              <?php else: ?>
                <span style="color:var(--text-muted);font-size:.75rem">-</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php if ($canManageActu): ?>
            <tr id="edit-actu-<?= $a['id'] ?>" style="display:none">
              <td colspan="<?= (int)$actuTableColspan ?>" style="background:rgba(255,255,255,.02);padding:var(--s5)">
                <strong style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--blue-light)">
                  Modifier - <?= htmlspecialchars($a['titre']) ?>
                </strong>
                <form method="post" class="admin-form" style="margin-top:var(--s4)">
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="id" value="<?= $a['id'] ?>">
                  <div class="form-row">
                    <div class="form-col"><label>Titre</label><input type="text" name="titre" value="<?= htmlspecialchars($a['titre']) ?>" required></div>
                    <?php if (isAdminCorpo()): ?>
                    <div class="form-col" style="flex:0;min-width:160px">
                      <label>Statut</label>
                      <select name="statut">
                        <option value="publie"<?= $a['statut']==='publie'?' selected':'' ?>>Publié</option>
                        <option value="en_attente"<?= $a['statut']==='en_attente'?' selected':'' ?>>En attente</option>
                        <option value="refuse"<?= $a['statut']==='refuse'?' selected':'' ?>>Refusé</option>
                      </select>
                    </div>
                    <?php endif; ?>
                    <?php if ($hasVisCol && isAdminCorpo()): ?>
                    <div class="form-col" style="flex:0;min-width:200px">
                      <label>Visibilité</label>
                      <select name="visibilite">
                        <option value="public"<?= $vrow === 'public' ? ' selected' : '' ?>>Publique</option>
                        <option value="membres"<?= $vrow === 'membres' ? ' selected' : '' ?>>Membres</option>
                      </select>
                    </div>
                    <?php endif; ?>
                  </div>
                  <div class="form-row">
                    <div class="form-col"><label>Contenu</label><textarea name="contenu" rows="5" required><?= htmlspecialchars($a['contenu']) ?></textarea></div>
                  </div>
                  <button type="submit" class="btn btn--primary">💾 Enregistrer</button>
                  <button type="button" class="btn" onclick="toggleEdit('actu-<?= $a['id'] ?>')"
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

<script>
function toggleEdit(id) {
  const row = document.getElementById('edit-' + id);
  const isHidden = row.style.display === 'none';
  document.querySelectorAll('tr[id^="edit-"]').forEach(r => r.style.display = 'none');
  row.style.display = isHidden ? '' : 'none';
  if (isHidden) row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
const actAssos  = <?= json_encode(array_map(fn($a)=>['id'=>$a['id'],'nom'=>$a['nom']], $assos)) ?>;
const actSports = <?= json_encode(array_map(fn($s)=>['id'=>$s['id'],'nom'=>$s['nom']], $sports)) ?>;

function updateActuStructs() {
  const type   = document.getElementById('actTypeSelect').value;
  const select = document.getElementById('actStructList');
  if (type === 'corpo') {
    select.innerHTML = '<option value="0">- Général -</option>';
    return;
  }
  const list = type === 'sport' ? actSports : actAssos;
  select.innerHTML = list.map(i => `<option value="${i.id}">${i.nom}</option>`).join('');
}
</script>

<?php require_once 'includes/admin-footer.php'; ?>
