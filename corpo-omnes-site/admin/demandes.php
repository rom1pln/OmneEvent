<?php
$adminTitle = 'Demandes de partenariat';
$adminPage  = 'demandes';
require_once '../includes/db.php';
require_once 'includes/admin-header.php';

$flash = ''; $flashType = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'delete' && !empty($_POST['id'])) {
        $pdo->prepare("DELETE FROM demandes_partenariat WHERE id = ?")->execute([(int)$_POST['id']]);
        $flash = 'Demande supprimée.'; $flashType = 'err';
    }
    header('Location: demandes.php?flash=' . urlencode($flash) . '&type=' . $flashType);
    exit;
}

if (!empty($_GET['flash'])) { $flash = $_GET['flash']; $flashType = $_GET['type'] ?? 'ok'; }

// Détail d'une demande
$detail = null;
if (!empty($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM demandes_partenariat WHERE id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $detail = $stmt->fetch();
}

$demandes = $pdo->query("SELECT * FROM demandes_partenariat ORDER BY created_at DESC")->fetchAll();
?>

<h1 class="admin-page-title">Demandes de partenariat</h1>
<?php if ($flash): ?><div class="flash flash--<?= htmlspecialchars($flashType) ?>"><?= htmlspecialchars($flash) ?></div><?php endif; ?>

<?php if ($detail): ?>
<!-- Détail d'une demande -->
<div class="admin-card">
  <div style="display:flex;align-items:center;gap:var(--s4);margin-bottom:var(--s4)">
    <a href="demandes.php" class="btn btn--ghost btn--sm">← Retour à la liste</a>
    <h2 style="margin:0">Demande #<?= $detail['id'] ?></h2>
  </div>
  <dl style="display:grid;grid-template-columns:180px 1fr;gap:var(--s3) var(--s6);font-size:.85rem">
    <dt style="color:var(--text-muted);font-weight:700">Organisation</dt>    <dd><?= htmlspecialchars($detail['organisation']) ?></dd>
    <dt style="color:var(--text-muted);font-weight:700">Contact</dt>         <dd><?= htmlspecialchars($detail['nom_contact']) ?></dd>
    <dt style="color:var(--text-muted);font-weight:700">Email</dt>           <dd><a href="mailto:<?= htmlspecialchars($detail['email']) ?>"><?= htmlspecialchars($detail['email']) ?></a></dd>
    <dt style="color:var(--text-muted);font-weight:700">Téléphone</dt>       <dd><?= htmlspecialchars($detail['telephone'] ?: '-') ?></dd>
    <dt style="color:var(--text-muted);font-weight:700">Type d'offre</dt>    <dd><?= htmlspecialchars($detail['type_offre'] ?: '-') ?></dd>
    <dt style="color:var(--text-muted);font-weight:700">Date de réception</dt><dd><?= date('d/m/Y H:i', strtotime($detail['created_at'])) ?></dd>
    <dt style="color:var(--text-muted);font-weight:700;align-self:start">Message</dt>
    <dd style="background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:var(--r-md);padding:var(--s4);white-space:pre-wrap"><?= htmlspecialchars($detail['message']) ?></dd>
  </dl>
  <div style="margin-top:var(--s6);display:flex;gap:var(--s3)">
    <a href="mailto:<?= htmlspecialchars($detail['email']) ?>?subject=Re: Demande de partenariat Corpo Omnes Lyon" class="btn btn--primary">Répondre par email</a>
    <form method="post" onsubmit="return confirm('Supprimer définitivement cette demande ?')">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" value="<?= $detail['id'] ?>">
      <button type="submit" class="btn btn--danger">Supprimer</button>
    </form>
  </div>
</div>
<?php else: ?>

<!-- Liste -->
<div class="admin-card">
  <h2>Toutes les demandes (<?= count($demandes) ?>)</h2>
  <?php if (empty($demandes)): ?>
    <p style="color:var(--text-muted);font-size:.85rem">Aucune demande reçue pour le moment.</p>
  <?php else: ?>
    <table class="admin-table">
      <thead><tr><th>#</th><th>Organisation</th><th>Contact</th><th>Email</th><th>Type</th><th>Date</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($demandes as $d): ?>
        <tr>
          <td><?= $d['id'] ?></td>
          <td><strong><?= htmlspecialchars($d['organisation']) ?></strong></td>
          <td><?= htmlspecialchars($d['nom_contact']) ?></td>
          <td><a href="mailto:<?= htmlspecialchars($d['email']) ?>"><?= htmlspecialchars($d['email']) ?></a></td>
          <td><?= htmlspecialchars($d['type_offre'] ?: '-') ?></td>
          <td><?= date('d/m/Y H:i', strtotime($d['created_at'])) ?></td>
          <td class="actions">
            <a href="demandes.php?id=<?= $d['id'] ?>" class="btn btn--sm btn--ghost">Voir</a>
            <form method="post" style="display:inline" onsubmit="return confirm('Supprimer ?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $d['id'] ?>">
              <button type="submit" class="btn btn--sm btn--danger">✕</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once 'includes/admin-footer.php'; ?>
