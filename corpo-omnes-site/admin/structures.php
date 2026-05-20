<?php
// structures - réservé au super admin
require_once '../includes/db.php';
$adminTitle = 'Structures';
$adminPage  = 'structures';
require_once 'includes/admin-header.php';

// Page réservée au Super Admin
if (!isSuperAdmin()) {
    echo '<div class="flash flash--err">Accès réservé au Super Administrateur.</div>';
    require_once 'includes/admin-footer.php';
    exit;
}

$flash = '';

// actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Créer une association / structure
    if ($action === 'create_asso') {
        $slug  = preg_replace('/[^a-z0-9]+/','-', strtolower(trim($_POST['slug'] ?? '')));
        $nom   = trim($_POST['nom'] ?? '');
        $ecole = trim($_POST['ecole'] ?? '');
        $type  = $_POST['type'] ?? 'Association';
        $campus = $_POST['campus'] ?? 'Tous';
        $desc  = trim($_POST['description'] ?? '');
        $color = $_POST['color'] ?? '#5D0282';
        if ($slug && $nom && $ecole) {
            try {
                $stmt = $pdo->prepare("INSERT INTO associations (slug, nom, ecole, type, campus, description, color) VALUES (?,?,?,?,?,?,?)");
                $stmt->execute([$slug, $nom, $ecole, $type, $campus, $desc, $color]);
                $flash = '<div class="flash flash--ok">Structure créée : <strong>' . htmlspecialchars($nom) . '</strong></div>';
            } catch (PDOException $e) {
                $flash = '<div class="flash flash--err">' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        } else {
            $flash = '<div class="flash flash--err">Slug, nom et école obligatoires.</div>';
        }

    } elseif ($action === 'delete_asso') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM associations WHERE id=?")->execute([$id]);
        $flash = '<div class="flash flash--ok">Structure supprimée.</div>';

    } elseif ($action === 'create_sport') {
        $slug = preg_replace('/[^a-z0-9]+/','-', strtolower(trim($_POST['slug'] ?? '')));
        $nom  = trim($_POST['nom'] ?? '');
        $icon = trim($_POST['icon'] ?? '');
        $couleur = $_POST['couleur'] ?? '#5D0282';
        $cat  = $_POST['categorie'] ?? 'club';
        $desc = trim($_POST['description'] ?? '');
        if ($slug && $nom) {
            try {
                $stmt = $pdo->prepare("INSERT INTO sports (slug,nom,icon,couleur,categorie,description) VALUES (?,?,?,?,?,?)");
                $stmt->execute([$slug, $nom, $icon, $couleur, $cat, $desc]);
                $flash = '<div class="flash flash--ok">Sport créé : <strong>' . htmlspecialchars($nom) . '</strong></div>';
            } catch (PDOException $e) {
                $flash = '<div class="flash flash--err">' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        } else {
            $flash = '<div class="flash flash--err">Slug et nom obligatoires.</div>';
        }

    } elseif ($action === 'delete_sport') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM sports WHERE id=?")->execute([$id]);
        $flash = '<div class="flash flash--ok">Sport supprimé.</div>';
    }
}

// lecture des structures
$assos  = $pdo->query("SELECT a.*, COUNT(sm.id) AS nb_membres FROM associations a LEFT JOIN structure_membres sm ON sm.structure_type='asso' AND sm.structure_id=a.id AND sm.statut='actif' GROUP BY a.id ORDER BY a.type, a.nom")->fetchAll();
$sports = $pdo->query("SELECT s.*, COUNT(sm.id) AS nb_membres FROM sports s LEFT JOIN structure_membres sm ON sm.structure_type='sport' AND sm.structure_id=s.id AND sm.statut='actif' GROUP BY s.id ORDER BY s.nom")->fetchAll();
?>

<h1 class="admin-page-title">Gestion des structures</h1>
<p style="color:var(--text-muted);margin-bottom:var(--s6)">Page réservée au Super Administrateur. Créez, modifiez et supprimez les structures (associations et sports).</p>
<?= $flash ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--s6)">

  <!-- ─ Associations ─ -->
  <div>
    <div class="admin-card">
      <h2>Créer une association / structure</h2>
      <form method="post" class="admin-form">
        <input type="hidden" name="action" value="create_asso">
        <div class="form-row">
          <div class="form-col">
            <label>Slug (URL)</label>
            <input type="text" name="slug" placeholder="bde-example" required>
          </div>
          <div class="form-col">
            <label>Nom</label>
            <input type="text" name="nom" placeholder="BDE Example" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-col">
            <label>École</label>
            <input type="text" name="ecole" placeholder="ECE, HEIP, ESCE…" required>
          </div>
          <div class="form-col">
            <label>Type</label>
            <select name="type">
              <option>BDE</option>
              <option>BDS</option>
              <option>Association</option>
              <option>Fédération</option>
              <option>Junior</option>
              <option>Corpo</option>
            </select>
          </div>
          <div class="form-col">
            <label>Campus</label>
            <select name="campus">
              <option>Tous</option>
              <option>Citadelle</option>
              <option>Citroën</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-col" style="flex:2">
            <label>Description</label>
            <textarea name="description" rows="3"></textarea>
          </div>
          <div class="form-col">
            <label>Couleur</label>
            <input type="text" name="color" placeholder="#5D0282" value="#5D0282">
          </div>
        </div>
        <button type="submit" class="btn btn--primary">Créer la structure</button>
      </form>
    </div>

    <div class="admin-card" style="padding:0;overflow:hidden">
      <table class="admin-table">
        <thead><tr><th>Nom</th><th>Type</th><th>École</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($assos as $a): ?>
            <tr>
              <td>
                <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?= htmlspecialchars($a['color']) ?>;margin-right:.4rem"></span>
                <strong><?= htmlspecialchars($a['nom']) ?></strong>
              </td>
              <td><span style="font-size:.75rem;color:var(--text-muted)"><?= htmlspecialchars($a['type']) ?></span></td>
              <td style="font-size:.78rem"><?= htmlspecialchars($a['ecole']) ?></td>
              <td>
                <form method="post" onsubmit="return confirm('Supprimer cette structure ? Cela supprimera aussi tous ses membres et contenus liés.')" style="display:inline">
                  <input type="hidden" name="action" value="delete_asso">
                  <input type="hidden" name="id" value="<?= $a['id'] ?>">
                  <button class="btn btn--sm btn--danger">🗑️</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ─ Sports ─ -->
  <div>
    <div class="admin-card">
      <h2>Créer un sport</h2>
      <form method="post" class="admin-form">
        <input type="hidden" name="action" value="create_sport">
        <div class="form-row">
          <div class="form-col">
            <label>Slug</label>
            <input type="text" name="slug" placeholder="natation" required>
          </div>
          <div class="form-col">
            <label>Nom</label>
            <input type="text" name="nom" placeholder="Natation" required>
          </div>
          <div class="form-col">
            <label>Icône</label>
            <input type="text" name="icon" placeholder="🏊" maxlength="4">
          </div>
        </div>
        <div class="form-row">
          <div class="form-col">
            <label>Catégorie</label>
            <select name="categorie">
              <option value="club">Club</option>
              <option value="individuel">Individuel</option>
            </select>
          </div>
          <div class="form-col">
            <label>Couleur</label>
            <input type="text" name="couleur" placeholder="#5D0282" value="#5D0282">
          </div>
        </div>
        <div class="form-row">
          <div class="form-col">
            <label>Description</label>
            <textarea name="description" rows="2"></textarea>
          </div>
        </div>
        <button type="submit" class="btn btn--primary">Créer le sport</button>
      </form>
    </div>

    <div class="admin-card" style="padding:0;overflow:hidden">
      <table class="admin-table">
        <thead><tr><th>Sport</th><th>Catégorie</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($sports as $s): ?>
            <tr>
              <td><?= $s['icon'] ?> <strong><?= htmlspecialchars($s['nom']) ?></strong></td>
              <td style="font-size:.78rem"><?= htmlspecialchars($s['categorie']) ?></td>
              <td>
                <form method="post" onsubmit="return confirm('Supprimer ce sport ?')" style="display:inline">
                  <input type="hidden" name="action" value="delete_sport">
                  <input type="hidden" name="id" value="<?= $s['id'] ?>">
                  <button class="btn btn--sm btn--danger">🗑️</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php require_once 'includes/admin-footer.php'; ?>
