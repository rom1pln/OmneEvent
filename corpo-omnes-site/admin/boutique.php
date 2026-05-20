<?php
declare(strict_types=1);

$adminTitle = 'Boutique';
$adminPage  = 'boutique';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/upload-logo.php';
require_once __DIR__ . '/includes/admin-header.php';
requireBureau();

require_once __DIR__ . '/../includes/boutique.php';

$flash = '';

if (!boutique_db_ready($pdo)) {
    echo '<div class="flash flash--warn">Les tables boutique ne sont pas installées. Exécute la migration <code>tbl_boutique</code> depuis <a href="migrate.php">migrate.php</a>.</div>';
    require_once __DIR__ . '/includes/admin-footer.php';
    exit;
}

// structures gérées (même logique que pour les events)
if (isAdminCorpo()) {
    $assos = $pdo->query("SELECT id, nom, type, ecole FROM associations ORDER BY ecole, nom")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $mAssoIds = getManagedAssoIds($pdo);
    $assos    = [];
    if (!empty($mAssoIds)) {
        $ph  = implode(',', array_fill(0, count($mAssoIds), '?'));
        $stA = $pdo->prepare("SELECT id, nom, type, ecole FROM associations WHERE id IN ($ph) ORDER BY nom");
        $stA->execute($mAssoIds);
        $assos = $stA->fetchAll(PDO::FETCH_ASSOC);
    }
    foreach (getExplicitDelegatedStructures('evenement') as $d) {
        if ($d['type'] === 'sport') {
            continue;
        }
        if (!in_array($d['id'], array_column($assos, 'id'), true)) {
            $st1 = $pdo->prepare('SELECT id, nom, type, ecole FROM associations WHERE id = ?');
            $st1->execute([$d['id']]);
            if ($row = $st1->fetch(PDO::FETCH_ASSOC)) {
                $assos[] = $row;
            }
        }
    }
}

$allowedAssoIds = array_map('intval', array_column($assos, 'id'));

function boutique_admin_can_product(PDO $pdo, array $row): bool {
    if (isAdminCorpo()) {
        return true;
    }
    $sid = (int)($row['structure_id'] ?? 0);
    return canManageStructureResource($pdo, 'asso', $sid, 'evenement');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $titre       = trim($_POST['titre'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $prix        = round((float)str_replace(',', '.', (string)($_POST['prix'] ?? '0')), 2);
        $stock       = max(0, (int)($_POST['stock'] ?? 1));
        $taille      = trim($_POST['taille'] ?? '') ?: null;
        $categorie   = trim($_POST['categorie'] ?? '') ?: null;
        $statut      = in_array($_POST['statut'] ?? '', ['brouillon', 'publie', 'archive'], true)
            ? $_POST['statut'] : 'publie';
        $structId = (int)($_POST['structure_id'] ?? 0);
        $fraisCli = isset($_POST['frais_a_charge_client']) ? 1 : 0;

        $ok = isAdminCorpo() || canManageStructureResource($pdo, 'asso', $structId, 'evenement');
        if (!$ok || !in_array($structId, $allowedAssoIds, true)) {
            $flash = '<div class="flash flash--err">Tu n\'es pas autorisé à créer un produit pour cette structure.</div>';
        } elseif ($titre === '') {
            $flash = '<div class="flash flash--err">Le titre est obligatoire.</div>';
        } else {
            $img = uploadLogo('boutique', 'image_file', 'image_url', null) ?? null;
            $pdo->prepare(
                "INSERT INTO boutique_produits
                  (structure_type, structure_id, titre, description, categorie, taille, image, prix, stock, frais_a_charge_client, statut)
                 VALUES ('asso',?,?,?,?,?,?,?,?,?,?)"
            )->execute([$structId, $titre, $description ?: null, $categorie, $taille, $img, $prix, $stock, $fraisCli, $statut]);
            $flash = '<div class="flash flash--ok">Produit créé.</div>';
        }
    }

    if ($action === 'update' && !empty($_POST['id'])) {
        $id = (int)$_POST['id'];
        $st = $pdo->prepare('SELECT * FROM boutique_produits WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row || !boutique_admin_can_product($pdo, $row)) {
            $flash = '<div class="flash flash--err">Action non autorisée.</div>';
        } else {
            $titre       = trim($_POST['titre'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $prix        = round((float)str_replace(',', '.', (string)($_POST['prix'] ?? '0')), 2);
            $stock       = max(0, (int)($_POST['stock'] ?? 0));
            $taille      = trim($_POST['taille'] ?? '') ?: null;
            $categorie   = trim($_POST['categorie'] ?? '') ?: null;
            $statut      = in_array($_POST['statut'] ?? '', ['brouillon', 'publie', 'archive'], true)
                ? $_POST['statut'] : 'publie';
            $structId = (int)($_POST['structure_id'] ?? 0);
            $fraisCli = isset($_POST['frais_a_charge_client']) ? 1 : 0;

            $canStruct = isAdminCorpo() || canManageStructureResource($pdo, 'asso', $structId, 'evenement');
            if (!$canStruct || !in_array($structId, $allowedAssoIds, true)) {
                $flash = '<div class="flash flash--err">Structure invalide ou interdite.</div>';
            } elseif ($titre === '') {
                $flash = '<div class="flash flash--err">Le titre est obligatoire.</div>';
            } else {
                $prevImg = (string)($row['image'] ?? '');
                $img     = uploadLogo('boutique', 'image_file', 'image_url', $prevImg !== '' ? $prevImg : null) ?? $prevImg;
                $pdo->prepare(
                    "UPDATE boutique_produits SET structure_id=?, titre=?, description=?, categorie=?, taille=?, image=?, prix=?, stock=?, frais_a_charge_client=?, statut=?
                     WHERE id=?"
                )->execute([$structId, $titre, $description ?: null, $categorie, $taille, $img ?: null, $prix, $stock, $fraisCli, $statut, $id]);
                $flash = '<div class="flash flash--ok">Produit mis à jour.</div>';
            }
        }
    }

    if ($action === 'delete' && !empty($_POST['id'])) {
        $id = (int)$_POST['id'];
        $st = $pdo->prepare('SELECT * FROM boutique_produits WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row || !boutique_admin_can_product($pdo, $row)) {
            $flash = '<div class="flash flash--err">Suppression non autorisée.</div>';
        } else {
            $pdo->prepare('DELETE FROM boutique_produits WHERE id = ?')->execute([$id]);
            $flash = '<div class="flash flash--ok">Produit supprimé.</div>';
        }
    }
}

$sql = "SELECT p.*, a.nom AS asso_nom, a.ecole
        FROM boutique_produits p
        JOIN associations a ON a.id = p.structure_id
        ORDER BY p.id DESC";
if (!isAdminCorpo() && !empty($allowedAssoIds)) {
    $ph = implode(',', array_fill(0, count($allowedAssoIds), '?'));
    $sql = "SELECT p.*, a.nom AS asso_nom, a.ecole
            FROM boutique_produits p
            JOIN associations a ON a.id = p.structure_id
            WHERE p.structure_id IN ($ph)
            ORDER BY p.id DESC";
    $stList = $pdo->prepare($sql);
    $stList->execute($allowedAssoIds);
    $produits = $stList->fetchAll(PDO::FETCH_ASSOC);
} elseif (!isAdminCorpo()) {
    $produits = [];
} else {
    $produits = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

$edit = null;
if (!empty($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    if ($eid > 0) {
        $st = $pdo->prepare('SELECT * FROM boutique_produits WHERE id = ?');
        $st->execute([$eid]);
        $edit = $st->fetch(PDO::FETCH_ASSOC);
        if ($edit && !boutique_admin_can_product($pdo, $edit)) {
            $edit = null;
        }
    }
}
?>
<h1 class="admin-page-title">Boutique</h1>
<?= $flash ?>

<div class="admin-card">
  <h2><?= $edit ? 'Modifier un produit' : 'Nouveau produit' ?></h2>
  <?php if (empty($assos)): ?>
    <p style="color:var(--text-muted)">Aucune structure associée à ton compte - tu ne peux pas créer de produit.</p>
  <?php else: ?>
  <form method="post" enctype="multipart/form-data" class="admin-form">
    <input type="hidden" name="action" value="<?= $edit ? 'update' : 'add' ?>">
    <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>

    <div class="form-row">
      <div class="form-col">
        <label>Association vendeuse *</label>
        <select name="structure_id" required>
          <?php foreach ($assos as $a): ?>
            <option value="<?= (int)$a['id'] ?>"
              <?= (int)($edit['structure_id'] ?? 0) === (int)$a['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($a['ecole'] . ' - ' . $a['nom']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-col">
        <label>Statut</label>
        <select name="statut">
          <?php foreach (['brouillon' => 'Brouillon', 'publie' => 'Publié', 'archive' => 'Archivé'] as $k => $lab): ?>
            <option value="<?= $k ?>" <?= ($edit['statut'] ?? 'publie') === $k ? 'selected' : '' ?>><?= $lab ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-row">
      <div class="form-col" style="flex:2">
        <label>Titre *</label>
        <input type="text" name="titre" required maxlength="200" value="<?= htmlspecialchars((string)($edit['titre'] ?? '')) ?>">
      </div>
      <div class="form-col">
        <label>Prix (€) *</label>
        <input type="text" name="prix" required inputmode="decimal" value="<?= htmlspecialchars((string)($edit['prix'] ?? '0')) ?>">
      </div>
      <div class="form-col">
        <label>Stock</label>
        <input type="number" name="stock" min="0" value="<?= (int)($edit['stock'] ?? 1) ?>">
      </div>
    </div>

    <div class="form-row">
      <div class="form-col">
        <label>Catégorie (optionnel)</label>
        <input type="text" name="categorie" maxlength="80" placeholder="Goodies, Textile…" value="<?= htmlspecialchars((string)($edit['categorie'] ?? '')) ?>">
      </div>
      <div class="form-col">
        <label>Taille (optionnel)</label>
        <input type="text" name="taille" maxlength="40" placeholder="S, M, L…" value="<?= htmlspecialchars((string)($edit['taille'] ?? '')) ?>">
      </div>
    </div>

    <label>Description</label>
    <textarea name="description" rows="4"><?= htmlspecialchars((string)($edit['description'] ?? '')) ?></textarea>

    <div class="form-row" style="margin-top:var(--s4)">
      <div class="form-col">
        <label>Image (fichier ≤ 2 Mo ou URL)</label>
        <input type="file" name="image_file" accept="image/*">
        <input type="url" name="image_url" placeholder="https://…" style="margin-top:8px" value="<?= (!empty($edit['image']) && str_starts_with((string)$edit['image'], 'http')) ? htmlspecialchars((string)$edit['image']) : '' ?>">
        <?php if (!empty($edit['image'])): ?>
          <p style="font-size:.75rem;color:var(--text-muted);margin-top:6px">Actuelle : <?= htmlspecialchars((string)$edit['image']) ?></p>
        <?php endif; ?>
      </div>
      <div class="form-col" style="display:flex;align-items:flex-end">
        <label style="display:flex;gap:8px;align-items:center;cursor:pointer">
          <input type="checkbox" name="frais_a_charge_client" value="1" <?= !empty($edit['frais_a_charge_client']) ? 'checked' : '' ?>>
          Refacturer les frais SumUp / Stripe à l’acheteur (comme billetterie « frais au client »)
        </label>
      </div>
    </div>

    <div style="margin-top:var(--s5)">
      <button type="submit" class="btn btn--primary"><?= $edit ? 'Enregistrer' : 'Créer le produit' ?></button>
      <?php if ($edit): ?>
        <a href="boutique.php" class="btn">Annuler l’édition</a>
      <?php endif; ?>
    </div>
  </form>
  <?php endif; ?>
</div>

<div class="admin-card">
  <h2 style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:var(--s3)">
    <span>Produits</span>
    <a href="boutique-commandes.php" class="btn btn--sm">Commandes boutique</a>
  </h2>
  <?php if (!$produits): ?>
    <p style="color:var(--text-muted)">Aucun produit pour ton périmètre.</p>
  <?php else: ?>
    <div style="overflow-x:auto">
      <table class="admin-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Asso</th>
            <th>Titre</th>
            <th>Prix</th>
            <th>Stock</th>
            <th>Statut</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($produits as $p): ?>
          <tr>
            <td><?= (int)$p['id'] ?></td>
            <td><?= htmlspecialchars((string)$p['asso_nom']) ?></td>
            <td><?= htmlspecialchars((string)$p['titre']) ?></td>
            <td><?= number_format((float)$p['prix'], 2, ',', ' ') ?> €</td>
            <td><?= (int)$p['stock'] ?></td>
            <td><?= htmlspecialchars((string)$p['statut']) ?></td>
            <td class="actions">
              <?php if (boutique_admin_can_product($pdo, $p)): ?>
                <a class="btn btn--sm" href="boutique.php?edit=<?= (int)$p['id'] ?>">Modifier</a>
                <form method="post" style="display:inline" onsubmit="return confirm('Supprimer ce produit ?');">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                  <button type="submit" class="btn btn--sm btn--danger">Supprimer</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<p style="font-size:.8rem;color:var(--text-muted);margin-top:var(--s6)">
  Catalogue public : <a href="../boutique.php" target="_blank" rel="noopener">boutique.php</a> - les paiements utilisent les mêmes règles SumUp / Stripe que la billetterie.
</p>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
