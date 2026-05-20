<?php
$adminTitle = 'Partenaires';
$adminPage  = 'partenaires';
require_once '../includes/db.php';
require_once '../includes/upload-logo.php';
require_once 'includes/admin-header.php';
requireBureau();

$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $nom        = trim($_POST['nom'] ?? '');
        $structType = $_POST['structure_type'] ?? 'asso';
        $structId   = (int)($_POST['structure_id'] ?? 0) ?: null;

        $authorized = isAdminCorpo();
        if (!$authorized && $structId) {
            if ($structType === 'sport') {
                $authorized = canManageStructureResource($pdo, 'sport', $structId, 'partenariat');
            } elseif (in_array($structType, ['asso', 'bde', 'bds'], true)) {
                $authorized = canManageStructureResource($pdo, 'asso', $structId, 'partenariat');
            }
        }
        if (!$authorized && !$structId && $structType === 'corpo') {
            $authorized = false;
        }

        if ($nom === '') {
            $flash = '<div class="flash flash--err">Le nom est obligatoire.</div>';
        } elseif (!$authorized) {
            $flash = '<div class="flash flash--err">Tu n\'es pas autorisé à ajouter un partenaire pour cette structure.</div>';
        } elseif (isAdminCorpo()) {
            $logoAdd = uploadLogo('partenaires', 'logo_file', 'logo_url') ?? 'images/partner-placeholder.png';
            $pdo->prepare("INSERT INTO partenaires (nom,type,logo,offre,code,campus,lien,description,structure_type,structure_id,statut,auteur_id) VALUES (?,?,?,?,?,?,?,?,?,?,'publie',?)")
                ->execute([$nom,$_POST['type']??'',$logoAdd,trim($_POST['offre']??''),trim($_POST['code']??'')?:null,$_POST['campus']??'Tous',trim($_POST['lien']??'#'),trim($_POST['description']??''),$structType,$structId,currentUserId()]);
            $flash = '<div class="flash flash--ok">Partenaire publié.</div>';
        } else {
            $payload = json_encode(['nom'=>$nom,'type'=>$_POST['type']??'','offre'=>trim($_POST['offre']??''),'code'=>trim($_POST['code']??''),'campus'=>$_POST['campus']??'Tous','lien'=>trim($_POST['lien']??'#'),'description'=>trim($_POST['description']??''),'structure_type'=>$structType,'structure_id'=>$structId]);
            $pdo->prepare("INSERT INTO demandes_validation (user_id,type,structure_type,structure_id,payload) VALUES (?,?,?,?,?)")
                ->execute([currentUserId(),'partenaire',$structType,$structId,$payload]);
            $flash = '<div class="flash flash--warn">Soumis à validation Corpo.</div>';
        }
    }

    if ($action === 'update' && !empty($_POST['id'])) {
        $pid = (int)$_POST['id'];
        $pt  = $pdo->prepare('SELECT * FROM partenaires WHERE id = ?');
        $pt->execute([$pid]);
        $rowPt = $pt->fetch(PDO::FETCH_ASSOC);
        $canUpd = isAdminCorpo();
        if (!$canUpd && $rowPt) {
            $st = (string)($rowPt['structure_type'] ?? '');
            $sid = (int)($rowPt['structure_id'] ?? 0);
            if ($sid > 0 && $st === 'sport') {
                $canUpd = canManageStructureResource($pdo, 'sport', $sid, 'partenariat');
            } elseif ($sid > 0 && in_array($st, ['asso', 'bde', 'bds'], true)) {
                $canUpd = canManageStructureResource($pdo, 'asso', $sid, 'partenariat');
            }
        }
        if (!$canUpd || !$rowPt) {
            $flash = '<div class="flash flash--err">Action non autorisée.</div>';
        } else {
        $prevPt = $pdo->prepare("SELECT logo FROM partenaires WHERE id=?");
        $prevPt->execute([$pid]);
        $prevLogo = $prevPt->fetchColumn() ?: 'images/partner-placeholder.png';
        $logoUpd  = uploadLogo('partenaires', 'logo_file', 'logo_url', $prevLogo) ?? $prevLogo;
        $pdo->prepare("UPDATE partenaires SET nom=?,type=?,logo=?,offre=?,code=?,campus=?,lien=?,description=? WHERE id=?")
            ->execute([
                trim($_POST['nom']         ?? ''),
                $_POST['type']             ?? '',
                $logoUpd,
                trim($_POST['offre']       ?? ''),
                trim($_POST['code']        ?? '') ?: null,
                $_POST['campus']           ?? 'Tous',
                trim($_POST['lien']        ?? '#'),
                trim($_POST['description'] ?? ''),
                $pid,
            ]);
        $flash = '<div class="flash flash--ok">Partenaire mis à jour.</div>';
        }
    }

    if ($action === 'delete' && !empty($_POST['id'])) {
        $pid = (int)$_POST['id'];
        $pt  = $pdo->prepare('SELECT * FROM partenaires WHERE id = ?');
        $pt->execute([$pid]);
        $rowPt = $pt->fetch(PDO::FETCH_ASSOC);
        $canDel = isAdminCorpo();
        if (!$canDel && $rowPt) {
            $st = (string)($rowPt['structure_type'] ?? '');
            $sid = (int)($rowPt['structure_id'] ?? 0);
            if ($sid > 0 && $st === 'sport') {
                $canDel = canManageStructureResource($pdo, 'sport', $sid, 'partenariat');
            } elseif ($sid > 0 && in_array($st, ['asso', 'bde', 'bds'], true)) {
                $canDel = canManageStructureResource($pdo, 'asso', $sid, 'partenariat');
            }
        }
        if ($canDel && $rowPt) {
            $pdo->prepare("DELETE FROM partenaires WHERE id=?")->execute([$pid]);
            $flash = '<div class="flash flash--ok">Partenaire supprimé.</div>';
        } elseif (!$canDel) {
            $flash = '<div class="flash flash--err">Action non autorisée.</div>';
        }
    }

    if ($action === 'toggle_statut' && !empty($_POST['id'])) {
        $pid = (int)$_POST['id'];
        $pt  = $pdo->prepare('SELECT * FROM partenaires WHERE id = ?');
        $pt->execute([$pid]);
        $rowPt = $pt->fetch(PDO::FETCH_ASSOC);
        $canTg = isAdminCorpo();
        if (!$canTg && $rowPt) {
            $st = (string)($rowPt['structure_type'] ?? '');
            $sid = (int)($rowPt['structure_id'] ?? 0);
            if ($sid > 0 && $st === 'sport') {
                $canTg = canManageStructureResource($pdo, 'sport', $sid, 'partenariat');
            } elseif ($sid > 0 && in_array($st, ['asso', 'bde', 'bds'], true)) {
                $canTg = canManageStructureResource($pdo, 'asso', $sid, 'partenariat');
            }
        }
        if ($canTg && $rowPt) {
            $new = $_POST['statut'] === 'publie' ? 'en_attente' : 'publie';
            $pdo->prepare("UPDATE partenaires SET statut=? WHERE id=?")->execute([$new, $pid]);
            $flash = '<div class="flash flash--ok">Statut mis à jour.</div>';
        } elseif (!$canTg) {
            $flash = '<div class="flash flash--err">Action non autorisée.</div>';
        }
    }
}

if (isAdminCorpo()) {
    $partners = $pdo->query("SELECT * FROM partenaires ORDER BY statut, type, nom")->fetchAll();
} else {
    $assoIds  = getManagedAssoIds($pdo);
    $sportIds = getManagedSportIds($pdo);
    foreach (getExplicitDelegatedStructures('partenariat') as $d) {
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
    $partners = [];
    if (!empty($assoIds)) {
        $ph   = implode(',', array_map('intval', $assoIds));
        $rows = $pdo->query("SELECT * FROM partenaires WHERE structure_type='asso' AND structure_id IN ($ph) ORDER BY nom")->fetchAll();
        $partners = array_merge($partners, $rows);
    }
    if (!empty($sportIds)) {
        $ph   = implode(',', array_map('intval', $sportIds));
        $rows = $pdo->query("SELECT * FROM partenaires WHERE structure_type='sport' AND structure_id IN ($ph) ORDER BY nom")->fetchAll();
        $partners = array_merge($partners, $rows);
    }
}

function partenaire_user_can_manage(PDO $pdo, array $p): bool {
    if (isAdminCorpo()) {
        return true;
    }
    $st  = (string)($p['structure_type'] ?? '');
    $sid = (int)($p['structure_id'] ?? 0);
    if ($sid <= 0) {
        return false;
    }
    if ($st === 'sport') {
        return canManageStructureResource($pdo, 'sport', $sid, 'partenariat');
    }
    if (in_array($st, ['asso', 'bde', 'bds'], true)) {
        return canManageStructureResource($pdo, 'asso', $sid, 'partenariat');
    }
    return false;
}

if (isAdminCorpo()) {
    $assos  = $pdo->query("SELECT id, nom, type FROM associations ORDER BY type, nom")->fetchAll();
    $sports = $pdo->query("SELECT id, nom FROM sports ORDER BY nom")->fetchAll();
} else {
    $assoIds  = getManagedAssoIds($pdo);
    $sportIds = getManagedSportIds($pdo);

    $assos = $sports = [];
    if (!empty($assoIds)) {
        $ph    = implode(',', array_fill(0, count($assoIds), '?'));
        $stA   = $pdo->prepare("SELECT id, nom, type FROM associations WHERE id IN ($ph) ORDER BY nom");
        $stA->execute($assoIds);
        $assos = $stA->fetchAll();
    }
    if (!empty($sportIds)) {
        $ph    = implode(',', array_fill(0, count($sportIds), '?'));
        $stS   = $pdo->prepare("SELECT id, nom FROM sports WHERE id IN ($ph) ORDER BY nom");
        $stS->execute($sportIds);
        $sports = $stS->fetchAll();
    }
    foreach (getExplicitDelegatedStructures('partenariat') as $d) {
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
                $st1 = $pdo->prepare('SELECT id, nom, type FROM associations WHERE id = ?');
                $st1->execute([$d['id']]);
                if ($row = $st1->fetch(PDO::FETCH_ASSOC)) {
                    $assos[] = $row;
                }
            }
        }
    }
}

$types  = ['Sport','Restauration','Culture','Travail','RSE','Autre'];
$campus = ['Tous','Citroën','Citadelle'];
?>

<h1 class="admin-page-title">Partenaires</h1>
<?= $flash ?>

<?php if (!isAdminCorpo()): ?>
  <div class="flash flash--warn">Bureau : vos partenaires passent par validation Corpo.</div>
<?php endif; ?>

<div class="admin-card">
  <h2>Ajouter un partenaire</h2>
  <form method="post" enctype="multipart/form-data" class="admin-form">
    <input type="hidden" name="action" value="add">
    <div class="form-row">
      <div class="form-col" style="flex:2"><label>Nom</label><input type="text" name="nom" required></div>
      <div class="form-col"><label>Type</label>
        <select name="type"><?php foreach ($types as $t): ?><option><?= $t ?></option><?php endforeach; ?></select>
      </div>
      <div class="form-col"><label>Campus</label>
        <select name="campus"><?php foreach ($campus as $c): ?><option><?= $c ?></option><?php endforeach; ?></select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-col">
        <label>Logo</label>
        <input type="file" name="logo_file" accept="image/*" style="font-size:.8rem">
        <input type="text" name="logo_url" placeholder="Ou URL https://…" style="margin-top:var(--s2);font-size:.78rem">
      </div>
    </div>
    <div class="form-row">
      <div class="form-col"><label>Offre</label><input type="text" name="offre" placeholder="−20% sur l'abonnement"></div>
      <div class="form-col"><label>Code promo</label><input type="text" name="code" placeholder="OMNES20"></div>
      <div class="form-col"><label>Lien</label><input type="url" name="lien" value="#"></div>
    </div>
    <div class="form-row">
      <div class="form-col" style="flex:2">
        <label>Structure concernée</label>
        <select name="structure_type" id="ptTypeSelect" onchange="syncPtStructList(this.value)">
          <?php if (isAdminCorpo()): ?>
            <option value="corpo">Corpo (global)</option>
          <?php endif; ?>
          <?php if (!empty($assos)): ?>
            <option value="asso" selected>Association / BDE / BDS</option>
          <?php endif; ?>
          <?php if (!empty($sports)): ?>
            <option value="sport">Sport</option>
          <?php endif; ?>
        </select>
      </div>
      <div class="form-col" style="flex:3">
        <label>Choisir la structure</label>
        <select name="structure_id" id="ptStructList">
          <?php if (isAdminCorpo()): ?>
            <option value="0">- Corpo Omnes (global) -</option>
          <?php endif; ?>
          <?php foreach ($assos as $a): ?>
            <option value="<?= $a['id'] ?>" data-type="asso"><?= htmlspecialchars($a['nom']) ?><?= !empty($a['type']) ? ' ('.$a['type'].')' : '' ?></option>
          <?php endforeach; ?>
          <?php foreach ($sports as $s): ?>
            <option value="<?= $s['id'] ?>" data-type="sport" style="display:none"><?= htmlspecialchars($s['nom']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <script>
      function syncPtStructList(type) {
        const sel = document.getElementById('ptStructList');
        [...sel.options].forEach(opt => {
          const t = opt.dataset.type || 'corpo';
          if (type === 'corpo') {
            opt.style.display = opt.value === '0' ? '' : 'none';
          } else {
            opt.style.display = (t === type || opt.value === '0') ? '' : 'none';
          }
        });

        const first = [...sel.options].find(o => o.style.display !== 'none');
        if (first) sel.value = first.value;
      }

      document.addEventListener('DOMContentLoaded', () => syncPtStructList(document.getElementById('ptTypeSelect').value));
    </script>
    <div class="form-row">
      <div class="form-col"><label>Description</label><textarea name="description" rows="2"></textarea></div>
    </div>
    <button type="submit" class="btn btn--primary"><?= isAdminCorpo() ? 'Publier →' : 'Soumettre →' ?></button>
  </form>
</div>

<div class="admin-card" style="padding:0;overflow:hidden">
  <table class="admin-table">
    <thead><tr><th>
    <tbody>
      <?php if (empty($partners)): ?>
        <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:var(--s6)">Aucun partenaire.</td></tr>
      <?php endif; ?>
      <?php foreach ($partners as $p): ?>
        <tr>
          <td><?= $p['id'] ?></td>
          <td><strong><?= htmlspecialchars($p['nom']) ?></strong></td>
          <td style="font-size:.78rem"><?= htmlspecialchars($p['type']) ?></td>
          <td style="font-size:.78rem">
            <?= htmlspecialchars($p['offre']?:'-') ?>
            <?php if ($p['code']): ?><br><code><?= htmlspecialchars($p['code']) ?></code><?php endif; ?>
          </td>
          <td style="font-size:.75rem"><?= htmlspecialchars($p['structure_type']) ?><?= $p['structure_id']?" #".$p['structure_id']:'' ?></td>
          <td><span class="badge <?= $p['statut']==='publie'?'badge--ok':($p['statut']==='en_attente'?'badge--pending':'badge--ko') ?>"><?= $p['statut'] ?></span></td>
          <td>
            <div class="actions">
              <?php if (partenaire_user_can_manage($pdo, $p)): ?>
                <button class="btn btn--sm" onclick="toggleEdit('pt-<?= $p['id'] ?>')"
                        style="background:var(--surface);border-color:var(--border)">✏️</button>
                <?php if (isAdminCorpo()): ?>
                <form method="post" style="display:inline">
                  <input type="hidden" name="action" value="toggle_statut">
                  <input type="hidden" name="id" value="<?= $p['id'] ?>">
                  <input type="hidden" name="statut" value="<?= $p['statut'] ?>">
                  <button class="btn btn--sm <?= $p['statut']==='publie'?'btn--warn':'btn--success' ?>"><?= $p['statut']==='publie'?'Dépublier':'Publier' ?></button>
                </form>
                <?php endif; ?>
                <form method="post" onsubmit="return confirm('Supprimer ?')" style="display:inline">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $p['id'] ?>">
                  <button class="btn btn--sm btn--danger">🗑️</button>
                </form>
              <?php else: ?>
                <span style="font-size:.75rem;color:var(--text-muted)">Lecture seule</span>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php if (partenaire_user_can_manage($pdo, $p)): ?>
          <tr id="edit-pt-<?= $p['id'] ?>" style="display:none">
            <td colspan="7" style="background:rgba(255,255,255,.02);padding:var(--s5)">
              <strong style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--blue-light)">
                Modifier - <?= htmlspecialchars($p['nom']) ?>
              </strong>
              <form method="post" enctype="multipart/form-data" class="admin-form" style="margin-top:var(--s4)">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                <div class="form-row">
                  <div class="form-col" style="flex:2"><label>Nom</label><input type="text" name="nom" value="<?= htmlspecialchars($p['nom']) ?>" required></div>
                  <div class="form-col"><label>Type</label>
                    <select name="type"><?php foreach ($types as $t): ?><option<?= $p['type']===$t?' selected':'' ?>><?= $t ?></option><?php endforeach; ?></select>
                  </div>
                  <div class="form-col"><label>Campus</label>
                    <select name="campus"><?php foreach ($campus as $c): ?><option<?= $p['campus']===$c?' selected':'' ?>><?= $c ?></option><?php endforeach; ?></select>
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-col"><label>Offre</label><input type="text" name="offre" value="<?= htmlspecialchars($p['offre']??'') ?>"></div>
                  <div class="form-col"><label>Code promo</label><input type="text" name="code" value="<?= htmlspecialchars($p['code']??'') ?>"></div>
                  <div class="form-col"><label>Lien</label><input type="url" name="lien" value="<?= htmlspecialchars($p['lien']??'#') ?>"></div>
                </div>
                <div class="form-row">
                  <div class="form-col">
                    <label>Logo</label>
                    <?php if (!empty($p['logo']) && $p['logo'] !== 'images/partner-placeholder.png'): ?>
                      <div style="display:flex;align-items:center;gap:var(--s3);margin-bottom:var(--s2)">
                        <img src="../<?= htmlspecialchars($p['logo']) ?>" alt="" style="height:30px;object-fit:contain;border-radius:4px;border:1px solid var(--border)">
                        <span style="font-size:.7rem;color:var(--text-muted)">Logo actuel</span>
                      </div>
                    <?php endif; ?>
                    <input type="file" name="logo_file" accept="image/*" style="font-size:.8rem">
                    <input type="text" name="logo_url" value="<?= htmlspecialchars($p['logo']??'') ?>" placeholder="Ou URL https://…" style="margin-top:var(--s2);font-size:.78rem">
                  </div>
                  <div class="form-col"><label>Description</label><textarea name="description" rows="2"><?= htmlspecialchars($p['description']??'') ?></textarea></div>
                </div>
                <button type="submit" class="btn btn--primary">💾 Enregistrer</button>
                <button type="button" class="btn" onclick="toggleEdit('pt-<?= $p['id'] ?>')"
                        style="background:var(--surface);border-color:var(--border)">Annuler</button>
              </form>
            </td>
          </tr>
        <?php endif; ?>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script>
function toggleEdit(id) {
  const row = document.getElementById('edit-' + id);
  const isHidden = row.style.display === 'none';
  document.querySelectorAll('tr[id^="edit-"]').forEach(r => r.style.display = 'none');
  row.style.display = isHidden ? '' : 'none';
  if (isHidden) row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
const ptAssos  = <?= json_encode(array_map(fn($a)=>['id'=>$a['id'],'nom'=>$a['nom']], $assos)) ?>;
const ptSports = <?= json_encode(array_map(fn($s)=>['id'=>$s['id'],'nom'=>$s['nom']], $sports)) ?>;
function updatePtStructs(sel) {
  const select = document.getElementById('ptStructList');
  if (sel.value === 'corpo') { select.innerHTML = '<option value="0">- Corpo Omnes -</option>'; return; }
  const list = sel.value === 'sport' ? ptSports : ptAssos;
  select.innerHTML = list.map(i => `<option value="${i.id}">${i.nom}</option>`).join('');
}
</script>

<?php require_once 'includes/admin-footer.php'; ?>
