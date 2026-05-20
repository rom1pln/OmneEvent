<?php
$adminTitle = 'Utilisateurs';
$adminPage  = 'users';
require_once '../includes/db.php';
require_once 'includes/admin-header.php';
requireAdmin();

$flash = '';

function userTargetRole(PDO $pdo, int $userId): ?string {
    if ($userId <= 0) return null;
    $st = $pdo->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
    $st->execute([$userId]);
    $r = $st->fetchColumn();
    return $r ? (string)$r : null;
}

function canActOnUser(PDO $pdo, int $targetUserId): bool {
    if (isSuperAdmin()) return true;
    $role = userTargetRole($pdo, $targetUserId);
    return $role !== null && $role !== 'super_admin';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email']    ?? '');
        $role     = $_POST['role']          ?? 'user';
        $mdp      = $_POST['password']      ?? '';
        $nom      = trim($_POST['nom']      ?? '');
        $prenom   = trim($_POST['prenom']   ?? '');

        $rolesAuthorised = isSuperAdmin()
            ? ['user','membre_corpo','admin_corpo','super_admin']
            : ['user','membre_corpo','admin_corpo'];

        if (!in_array($role, $rolesAuthorised, true)) {
            $flash = '<div class="flash flash--err">Rôle non autorisé.</div>';
        } elseif ($username === '' || $email === '' || $mdp === '') {
            $flash = '<div class="flash flash--err">Identifiant, email et mot de passe sont obligatoires.</div>';
        } else {
            try {
                $hash = password_hash($mdp, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare(
                    "INSERT INTO users (username, email, password_hash, nom, prenom, role, statut)
                     VALUES (?,?,?,?,?,?,'actif')"
                );
                $stmt->execute([$username, $email, $hash, $nom ?: null, $prenom ?: null, $role]);
                $flash = '<div class="flash flash--ok">Utilisateur <strong>'.htmlspecialchars($username).'</strong> créé.</div>';
            } catch (PDOException $e) {
                $flash = '<div class="flash flash--err">'.htmlspecialchars($e->getMessage()).'</div>';
            }
        }

    } elseif ($action === 'statut') {
        $userId    = (int)($_POST['user_id'] ?? 0);
        $newStatut = $_POST['statut'] ?? 'actif';
        if ($userId === currentUserId()) {
            $flash = '<div class="flash flash--err">Impossible de modifier votre propre compte.</div>';
        } elseif (!canActOnUser($pdo, $userId)) {
            $flash = '<div class="flash flash--err">Action interdite sur un Super Administrateur.</div>';
        } else {
            $pdo->prepare("UPDATE users SET statut=? WHERE id=?")->execute([$newStatut, $userId]);
            $flash = '<div class="flash flash--ok">Statut mis à jour.</div>';
        }

    } elseif ($action === 'role' && isSuperAdmin()) {
        $userId  = (int)($_POST['user_id'] ?? 0);
        $newRole = $_POST['role'] ?? 'user';
        $valid   = ['user','membre_corpo','admin_corpo','super_admin'];
        if ($userId === currentUserId()) {
            $flash = '<div class="flash flash--err">Impossible de modifier votre propre rôle.</div>';
        } elseif (!in_array($newRole, $valid, true)) {
            $flash = '<div class="flash flash--err">Rôle invalide.</div>';
        } else {
            $pdo->prepare("UPDATE users SET role=? WHERE id=?")->execute([$newRole, $userId]);
            $flash = '<div class="flash flash--ok">Rôle global mis à jour.</div>';
        }

    } elseif ($action === 'assigner_structure') {
        $userId       = (int)($_POST['user_id']      ?? 0);
        $structType   = $_POST['structure_type']     ?? '';
        $structId     = (int)($_POST['structure_id'] ?? 0);
        $roleInStruct = in_array($_POST['role_in_struct'] ?? '', ['admin','membre','adherent'], true)
                        ? $_POST['role_in_struct'] : 'membre';

        $allowedTypes = ['bde','asso','bds','sport'];
        if (!canActOnUser($pdo, $userId)) {
            $flash = '<div class="flash flash--err">Action interdite sur un Super Administrateur.</div>';
        } elseif ($userId && in_array($structType, $allowedTypes, true) && $structId) {
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO structure_membres
                       (user_id, structure_type, structure_id, role_in_struct, statut, invited_by)
                     VALUES (?,?,?,?,'actif',?)
                     ON DUPLICATE KEY UPDATE role_in_struct=?, statut='actif'"
                );
                $stmt->execute([$userId, $structType, $structId, $roleInStruct, currentUserId(), $roleInStruct]);

                if ($roleInStruct === 'admin') {

                    $pdo->prepare(
                        "UPDATE users SET role='membre_corpo' WHERE id=? AND role='user'"
                    )->execute([$userId]);
                } else {

                    syncGlobalRoleAfterStructChange($pdo, $userId);
                }
                $flash = '<div class="flash flash--ok">Affectation enregistrée.</div>';
            } catch (PDOException $e) {
                $flash = '<div class="flash flash--err">'.htmlspecialchars($e->getMessage()).'</div>';
            }
        }

    } elseif ($action === 'retirer_structure') {
        $membreId = (int)($_POST['membre_id'] ?? 0);
        if ($membreId) {

            $uStmt = $pdo->prepare("SELECT user_id FROM structure_membres WHERE id = ? LIMIT 1");
            $uStmt->execute([$membreId]);
            $targetUserId = (int)$uStmt->fetchColumn();

            if (!canActOnUser($pdo, $targetUserId)) {
                $flash = '<div class="flash flash--err">Action interdite sur un Super Administrateur.</div>';
            } else {
                $pdo->prepare("DELETE FROM structure_membres WHERE id=?")->execute([$membreId]);

                if ($targetUserId > 0) {
                    syncGlobalRoleAfterStructChange($pdo, $targetUserId);
                }
                $flash = '<div class="flash flash--ok">Affectation retirée.</div>';
            }
        }

    } elseif ($action === 'delete' && isSuperAdmin()) {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId === currentUserId()) {
            $flash = '<div class="flash flash--err">Impossible de supprimer votre propre compte.</div>';
        } else {
            $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$userId]);
            $flash = '<div class="flash flash--ok">Utilisateur supprimé.</div>';
        }
    }
}

$users = $pdo->query("SELECT * FROM users ORDER BY role, username")->fetchAll();

$bdes   = $pdo->query("SELECT id,nom,ecole FROM associations WHERE type='BDE'   ORDER BY nom")->fetchAll();
$bds_   = $pdo->query("SELECT id,nom,ecole FROM associations WHERE type='BDS'   ORDER BY nom")->fetchAll();
$assos  = $pdo->query("SELECT id,nom,ecole FROM associations WHERE type NOT IN ('BDE','BDS','Corpo') ORDER BY nom")->fetchAll();
$sports = $pdo->query("SELECT id,nom FROM sports ORDER BY nom")->fetchAll();

$roleGlobalOptions = [
    'user'        => 'Étudiant',
    'membre_corpo'=> 'Membre Corpo',
    'admin_corpo' => 'Admin Corpo',
];
if (isSuperAdmin()) $roleGlobalOptions['super_admin'] = 'Super Admin';
?>

<h1 class="admin-page-title">Utilisateurs</h1>
<?= $flash ?>

<div class="admin-card">
  <h2>Créer un compte manuellement</h2>
  <p style="font-size:.8rem;color:var(--text-muted);margin-bottom:var(--s4)">
    Les étudiants peuvent aussi s'inscrire eux-mêmes depuis <a href="../register.php" target="_blank" style="color:var(--blue-light)">la page d'inscription</a>. Leurs comptes sont validés automatiquement.
  </p>
  <form method="post" class="admin-form">
    <input type="hidden" name="action" value="create">
    <div class="form-row">
      <div class="form-col"><label>Prénom</label><input type="text" name="prenom" placeholder="Marie"></div>
      <div class="form-col"><label>Nom</label><input type="text" name="nom" placeholder="Dupont"></div>
      <div class="form-col"><label>Identifiant <span style="color:#ef4444">*</span></label><input type="text" name="username" placeholder="marie.dupont" required></div>
    </div>
    <div class="form-row">
      <div class="form-col"><label>Email <span style="color:#ef4444">*</span></label><input type="email" name="email" placeholder="marie@ecole.fr" required></div>
      <div class="form-col"><label>Mot de passe temporaire <span style="color:#ef4444">*</span></label><input type="password" name="password" required></div>
      <div class="form-col"><label>Rôle global</label>
        <select name="role">
          <?php foreach ($roleGlobalOptions as $val => $lbl): ?>
            <option value="<?= $val ?>"><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <button type="submit" class="btn btn--primary">Créer →</button>
  </form>
</div>

<div class="admin-card" style="padding:0;overflow:hidden">
  <div style="padding:var(--s5) var(--s6);border-bottom:1px solid var(--border);display:flex;align-items:center;gap:var(--s4)">
    <strong>Tous les utilisateurs (<?= count($users) ?>)</strong>
    <input type="search" id="user-search" placeholder="Filtrer…"
           style="margin-left:auto;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:var(--r-md);
                  color:#fff;padding:.3rem var(--s4);font-size:.8rem;outline:none;width:200px">
  </div>
  <table class="admin-table" id="users-table">
    <thead>
      <tr>
        <th>Étudiant</th>
        <th>Email</th>
        <th>École / Promo</th>
        <th>Rôle global</th>
        <th>Statut</th>
        <th>Structures</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u):

        $stmtS = $pdo->prepare(
          "SELECT sm.id AS membre_id, sm.structure_type, sm.structure_id, sm.role_in_struct, sm.statut,
                  COALESCE(a.nom, sp.nom) AS struct_nom
           FROM structure_membres sm
           LEFT JOIN associations a  ON sm.structure_type IN ('asso','bde','bds') AND a.id  = sm.structure_id
           LEFT JOIN sports       sp ON sm.structure_type = 'sport'               AND sp.id = sm.structure_id
           WHERE sm.user_id = ? ORDER BY sm.structure_type, struct_nom"
        );
        $stmtS->execute([$u['id']]);
        $structs = $stmtS->fetchAll();
        $nomComplet = trim(($u['prenom'] ?? '').' '.($u['nom'] ?? ''));
      ?>
        <tr data-search="<?= htmlspecialchars(mb_strtolower($u['username'].' '.$u['email'].' '.($u['nom']??'').' '.($u['prenom']??''))) ?>">
          <td>
            <strong><?= htmlspecialchars($u['username']) ?></strong>
            <?php if ($nomComplet): ?>
              <br><small style="color:var(--text-muted)"><?= htmlspecialchars($nomComplet) ?></small>
            <?php endif; ?>
            <?php if ($u['id'] === currentUserId()): ?>
              <span style="font-size:.62rem;color:var(--purple-light)"> (vous)</span>
            <?php endif; ?>
          </td>
          <td style="font-size:.78rem;color:var(--blue-light)"><?= htmlspecialchars($u['email']) ?></td>
          <td style="font-size:.75rem">
            <?php if ($u['ecole']): ?><strong><?= htmlspecialchars($u['ecole']) ?></strong><?php endif; ?>
            <?php if ($u['programme']): ?><br><?= htmlspecialchars($u['programme']) ?><?php endif; ?>
            <?php if ($u['promotion']): ?><br><span style="color:var(--text-muted)"><?= htmlspecialchars($u['promotion']) ?></span><?php endif; ?>
          </td>
          <td><?= roleBadge($u['role']) ?></td>
          <td>
            <span class="badge badge--<?= $u['statut']==='actif'?'ok':($u['statut']==='en_attente'?'pending':'ko') ?>">
              <?= $u['statut'] ?>
            </span>
          </td>
                    <td style="font-size:.73rem;max-width:200px">
            <?php if (empty($structs)): ?>
              <span style="color:var(--text-muted)">-</span>
            <?php else: ?>
              <?php foreach ($structs as $sm): ?>
                <div style="display:flex;align-items:center;gap:4px;margin-bottom:3px">
                  <span style="font-size:.65rem;background:rgba(255,255,255,.07);border-radius:3px;padding:0 4px;text-transform:uppercase">
                    <?= htmlspecialchars($sm['structure_type']) ?>
                  </span>
                  <span><?= htmlspecialchars($sm['struct_nom'] ?? '?') ?></span>
                  <?= structRoleBadge($sm['role_in_struct']) ?>
                  <?php if ($u['id'] !== currentUserId() && ($u['role'] !== 'super_admin' || isSuperAdmin())): ?>
                    <form method="post" style="display:inline;margin:0"
                          onsubmit="return confirm('Retirer de cette structure ?')">
                      <input type="hidden" name="action" value="retirer_structure">
                      <input type="hidden" name="membre_id" value="<?= $sm['membre_id'] ?>">
                      <button class="btn btn--sm btn--danger" style="padding:0 4px;font-size:.6rem;line-height:1.6">✕</button>
                    </form>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </td>
                    <td>
            <?php $isProtected = ($u['role'] === 'super_admin') && !isSuperAdmin(); ?>
            <div class="actions" style="flex-wrap:wrap;gap:4px">
              <?php if ($u['id'] !== currentUserId() && !$isProtected): ?>

                                <form method="post" style="display:inline">
                  <input type="hidden" name="action" value="statut">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <?php if ($u['statut'] === 'actif'): ?>
                    <input type="hidden" name="statut" value="suspendu">
                    <button class="btn btn--sm btn--warn">Suspendre</button>
                  <?php elseif ($u['statut'] === 'suspendu'): ?>
                    <input type="hidden" name="statut" value="actif">
                    <button class="btn btn--sm btn--success">Réactiver</button>
                  <?php else: ?>
                    <input type="hidden" name="statut" value="actif">
                    <button class="btn btn--sm btn--success">Valider</button>
                  <?php endif; ?>
                </form>

                                <button class="btn btn--sm" onclick="toggleAssign(<?= $u['id'] ?>)"
                        style="background:var(--surface);border-color:var(--border)">
                  Affecter
                </button>

                <?php if (isSuperAdmin()): ?>
                                    <form method="post" class="role-global-form"
                        style="display:inline-flex;gap:.3rem;align-items:center" title="Changer le rôle global">
                    <input type="hidden" name="action" value="role">
                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    <select name="role" data-initial="<?= htmlspecialchars($u['role']) ?>"
                            style="background:var(--surface);border:1px solid var(--border);color:var(--blue-light);
                                   border-radius:var(--r-md);padding:.2rem .5rem;font-size:.72rem;cursor:pointer">
                      <?php foreach ($roleGlobalOptions as $v => $l): ?>
                        <option value="<?= $v ?>"<?= $u['role']===$v?' selected':'' ?>><?= $l ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn--sm role-global-form__save"
                            disabled
                            style="padding:.2rem .5rem;font-size:.72rem;opacity:.5;cursor:not-allowed">
                      ✓
                    </button>
                  </form>
                                    <form method="post" style="display:inline"
                        onsubmit="return confirm('Supprimer définitivement cet utilisateur ?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    <button class="btn btn--sm btn--danger">🗑️</button>
                  </form>
                <?php endif; ?>
              <?php elseif ($isProtected): ?>
                <span style="font-size:.7rem;color:var(--text-muted)">Protégé (Super Admin)</span>
              <?php endif; ?>
            </div>

                        <div id="assign-<?= $u['id'] ?>" style="display:none;margin-top:var(--s3)">
              <form method="post" class="admin-form"
                    style="background:rgba(255,255,255,.03);border:1px solid var(--border);
                           border-radius:var(--r-md);padding:var(--s4)">
                <input type="hidden" name="action" value="assigner_structure">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <p style="font-size:.7rem;color:var(--blue-light);font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:var(--s3)">
                  Affecter à une structure
                </p>
                <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-end">
                  <div style="min-width:120px">
                    <label>Type de structure</label>
                    <select name="structure_type" id="stype-<?= $u['id'] ?>"
                            onchange="updateStructList(<?= $u['id'] ?>)">
                      <option value="bde">BDE</option>
                      <option value="asso">Association</option>
                      <option value="bds">BDS</option>
                      <option value="sport">Sport</option>
                    </select>
                  </div>
                  <div style="flex:1;min-width:160px">
                    <label>Structure</label>
                    <select name="structure_id" id="slist-<?= $u['id'] ?>">
                      <?php foreach ($bdes as $b): ?>
                        <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['nom']) ?> (<?= htmlspecialchars($b['ecole']) ?>)</option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div style="min-width:110px">
                    <label>Rôle dans la structure</label>
                    <select name="role_in_struct">
                      <option value="adherent">Adhérent (privé)</option>
                      <option value="membre" selected>Membre (visible)</option>
                      <option value="admin">Bureau / admin</option>
                    </select>
                  </div>
                  <div style="display:flex;gap:.4rem;align-items:flex-end;padding-bottom:2px">
                    <button type="submit" class="btn btn--sm btn--primary">Affecter</button>
                    <button type="button" class="btn btn--sm" onclick="toggleAssign(<?= $u['id'] ?>)"
                            style="background:var(--surface);border-color:var(--border)">✕</button>
                  </div>
                </div>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script>

const structData = {
  bde:   <?= json_encode(array_map(fn($b) => ['id'=>$b['id'],'nom'=>$b['nom'].' ('.$b['ecole'].')'], $bdes))   ?>,
  asso:  <?= json_encode(array_map(fn($a) => ['id'=>$a['id'],'nom'=>$a['nom'].' ('.$a['ecole'].')'], $assos))  ?>,
  bds:   <?= json_encode(array_map(fn($b) => ['id'=>$b['id'],'nom'=>$b['nom'].' ('.$b['ecole'].')'], $bds_))   ?>,
  sport: <?= json_encode(array_map(fn($s) => ['id'=>$s['id'],'nom'=>$s['nom']], $sports))                      ?>,
};

function toggleAssign(id) {
  const el = document.getElementById('assign-' + id);
  const open = el.style.display === 'none';

  document.querySelectorAll('[id^="assign-"]').forEach(p => p.style.display = 'none');
  if (open) el.style.display = '';
}

function updateStructList(userId) {
  const type   = document.getElementById('stype-' + userId).value;
  const select = document.getElementById('slist-' + userId);
  const list   = structData[type] || [];
  select.innerHTML = list.length
    ? list.map(i => `<option value="${i.id}">${i.nom}</option>`).join('')
    : '<option value="0">- Aucune structure de ce type -</option>';
}

document.getElementById('user-search').addEventListener('input', function() {
  const q = this.value.toLowerCase().trim();
  document.querySelectorAll('#users-table tbody tr[data-search]').forEach(tr => {
    tr.hidden = q && !tr.dataset.search.includes(q);
  });
});

document.querySelectorAll('.role-global-form').forEach(form => {
  const sel = form.querySelector('select[name="role"]');
  const btn = form.querySelector('.role-global-form__save');
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
    if (sel.value === initial) { e.preventDefault(); return; }
    if (!confirm('Confirmer le changement de rôle global pour cet utilisateur ?')) {
      e.preventDefault();
    }
  });
  sync();
});
</script>

<?php require_once 'includes/admin-footer.php'; ?>
