<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/date-fr.php';
require_once __DIR__ . '/../includes/notes-frais.php';

$adminTitle = 'Notes de frais';
$adminPage  = 'notes-frais';

requireBureau();
requireAdminPanelDelegationRoute($pdo, 'notes-frais');

$userId  = (int)($_SESSION['user_id'] ?? 0);
$flashOk = '';
$flashErr = '';

if (!nf_table_ready($pdo)) {
    require_once __DIR__ . '/includes/admin-header.php';
    echo '<h1 class="admin-page-title">Notes de frais</h1>';
    echo '<div class="flash flash--warn">Migration <code>tbl_compta_notes_frais</code> requise.</div>';
    require_once __DIR__ . '/includes/admin-footer.php';
    exit;
}

$canSubmit         = nf_can_submit_any($pdo, $userId);
$canValidateBureau = nf_is_bureau_validator_any($pdo, $userId);
$canSuperValidate  = nf_is_super_validator();

if (!nf_can_access_admin_notes_page($pdo, $userId)) {
    require_once __DIR__ . '/includes/admin-header.php';
    echo '<h1 class="admin-page-title">Notes de frais</h1>';
    echo '<div class="flash flash--warn">Réservé aux membres du bureau (hors adhérents).</div>';
    require_once __DIR__ . '/includes/admin-footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAct = (string)($_POST['action'] ?? '');
    if ($postAct === 'nf_super_validate' && $canSuperValidate) {
        $r = nf_super_validate_and_book($pdo, (int)($_POST['note_id'] ?? 0), $userId, trim((string)($_POST['commentaire'] ?? '')) ?: null);
        $flashOk  = $r['ok'] ? ($r['msg'] ?? '') : '';
        $flashErr = $r['ok'] ? '' : ($r['msg'] ?? 'Erreur');
    } elseif ($postAct === 'nf_approve_bureau') {
        $r = nf_approve_bureau($pdo, (int)($_POST['note_id'] ?? 0), $userId, trim((string)($_POST['commentaire_bureau'] ?? '')) ?: null);
        $flashOk  = $r['ok'] ? ($r['msg'] ?? '') : '';
        $flashErr = $r['ok'] ? '' : ($r['msg'] ?? 'Erreur');
    } elseif ($postAct === 'submit_nf' && $canSubmit) {
        $selType = (string)($_POST['structure_type'] ?? '');
        $selId   = (int)($_POST['structure_id'] ?? 0);
        $up = nf_upload_justificatif_pdf($selType, $selId);
        if (!$up['ok']) {
            $flashErr = $up['msg'] ?? 'Erreur upload PDF.';
        } else {
            $r = nf_create_request(
                $pdo,
                $userId,
                $selType,
                $selId,
                (float)str_replace([',', ' '], ['.', ''], (string)($_POST['montant'] ?? '0')),
                trim((string)($_POST['date_depense'] ?? '')),
                trim((string)($_POST['libelle'] ?? '')),
                (string)$up['path'],
                trim((string)($_POST['commentaire_membre'] ?? ''))
            );
            $flashOk  = $r['ok'] ? ($r['msg'] ?? '') : '';
            $flashErr = $r['ok'] ? '' : ($r['msg'] ?? 'Erreur');
        }
    }
}

$pendingBureau   = (!$canSuperValidate && nf_dual_validation_ready($pdo))
    ? nf_list_pending_bureau_for_validator($pdo, $userId) : [];
$pendingSuper    = $canSuperValidate ? nf_list_pending_for_super_admin($pdo) : [];
$memberships   = $canSubmit ? nf_memberships_for_submit($pdo, $userId) : [];
$selType       = (string)($_GET['structure_type'] ?? ($memberships[0]['type'] ?? ''));
$selId         = (int)($_GET['structure_id'] ?? ($memberships[0]['id'] ?? 0));
$validSel      = false;
foreach ($memberships as $m) {
    if ($m['type'] === $selType && (int)$m['id'] === $selId) {
        $validSel = true;
        break;
    }
}
if (!$validSel && !empty($memberships)) {
    $selType  = $memberships[0]['type'];
    $selId    = (int)$memberships[0]['id'];
    $validSel = true;
}

$myNotes = $canSubmit
    ? ($validSel ? nf_list_for_user($pdo, $userId, $selType, $selId) : nf_list_for_user($pdo, $userId))
    : [];

require_once __DIR__ . '/includes/admin-header.php';
?>

<h1 class="admin-page-title">Notes de frais</h1>
<p style="margin:0 0 var(--s5);font-size:.85rem;color:var(--text-muted);max-width:52rem">
  <?php if ($canSuperValidate): ?>
    En tant que <strong>super administrateur</strong>, tu peux valider et comptabiliser une note en une seule action (sans double validation).
  <?php else: ?>
    Double validation : un membre du <strong>bureau</strong> (autre que le demandeur), puis un membre <strong>trésorerie</strong> distinct.
    La compta est mise à jour après la validation trésorerie.
  <?php endif; ?>
</p>

<?php if ($flashOk): ?><div class="flash flash--ok"><?= htmlspecialchars($flashOk) ?></div><?php endif; ?>
<?php if ($flashErr): ?><div class="flash flash--err"><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>

<?php if (!empty($pendingSuper)): ?>
<div class="admin-card" style="margin-bottom:var(--s5)">
  <h2 style="margin:0 0 var(--s4);font-size:1rem">À valider (super admin)</h2>
  <div style="overflow-x:auto">
    <table class="admin-table">
      <thead>
        <tr><th>Structure</th><th>Membre</th><th>Libellé</th><th>Montant</th><th>Statut</th><th>PDF</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($pendingSuper as $ps): ?>
        <tr>
          <td data-label="Structure"><?= htmlspecialchars($ps['structure_nom'] ?? $ps['structure_type']) ?></td>
          <td data-label="Membre"><?= htmlspecialchars(trim(($ps['prenom'] ?? '') . ' ' . ($ps['nom'] ?? '')) ?: $ps['email']) ?></td>
          <td data-label="Libellé"><?= htmlspecialchars($ps['libelle']) ?></td>
          <td data-label="Montant"><?= number_format((float)$ps['montant'], 2, ',', ' ') ?> €</td>
          <td data-label="Statut"><?= htmlspecialchars(nf_statut_label((string)$ps['statut'])) ?></td>
          <td data-label="PDF"><a href="api/note-frais-pdf.php?id=<?= (int)$ps['id'] ?>" target="_blank" rel="noopener">PDF</a></td>
          <td data-label="Action">
            <form method="post">
              <input type="hidden" name="action" value="nf_super_validate">
              <input type="hidden" name="note_id" value="<?= (int)$ps['id'] ?>">
              <button type="submit" class="btn btn--sm btn--primary" onclick="return confirm('Valider et enregistrer en compta ?')">Valider (complet)</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($pendingBureau)): ?>
<div class="admin-card" style="margin-bottom:var(--s5)">
  <h2 style="margin:0 0 var(--s4);font-size:1rem">À valider (bureau)</h2>
  <div style="overflow-x:auto">
    <table class="admin-table">
      <thead>
        <tr><th>Structure</th><th>Membre</th><th>Libellé</th><th>Montant</th><th>PDF</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($pendingBureau as $pb): ?>
        <tr>
          <td data-label="Structure"><?= htmlspecialchars($pb['structure_nom'] ?? $pb['structure_type']) ?></td>
          <td data-label="Membre"><?= htmlspecialchars(trim(($pb['prenom'] ?? '') . ' ' . ($pb['nom'] ?? '')) ?: $pb['email']) ?></td>
          <td data-label="Libellé"><?= htmlspecialchars($pb['libelle']) ?></td>
          <td data-label="Montant"><?= number_format((float)$pb['montant'], 2, ',', ' ') ?> €</td>
          <td data-label="PDF"><a href="api/note-frais-pdf.php?id=<?= (int)$pb['id'] ?>" target="_blank" rel="noopener">PDF</a></td>
          <td data-label="Action">
            <form method="post">
              <input type="hidden" name="action" value="nf_approve_bureau">
              <input type="hidden" name="note_id" value="<?= (int)$pb['id'] ?>">
              <button type="submit" class="btn btn--sm btn--primary" onclick="return confirm('Valider (bureau) ?')">Valider bureau</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if ($canSubmit): ?>
<div class="admin-card" style="margin-bottom:var(--s5)">
  <h2 style="margin:0 0 var(--s4);font-size:1rem">Nouvelle demande</h2>
  <form method="post" enctype="multipart/form-data" class="admin-form">
    <input type="hidden" name="action" value="submit_nf">
    <div class="form-row">
      <div class="form-col" style="flex:1 1 100%">
        <label>Structure</label>
        <select name="structure_type" id="nf-struct-type" class="admin-input" required onchange="nfSyncStructSelect()">
          <?php foreach ($memberships as $m): ?>
            <option value="<?= htmlspecialchars($m['type']) ?>" data-id="<?= (int)$m['id'] ?>"
              <?= ($m['type'] === $selType && (int)$m['id'] === $selId) ? 'selected' : '' ?>>
              <?= htmlspecialchars($m['nom']) ?> (<?= htmlspecialchars(strtoupper($m['type'])) ?>)
            </option>
          <?php endforeach; ?>
        </select>
        <input type="hidden" name="structure_id" id="nf-struct-id" value="<?= (int)$selId ?>">
      </div>
    </div>
    <div class="form-row">
      <div class="form-col">
        <label>Montant (€)</label>
        <input type="text" name="montant" class="admin-input" inputmode="decimal" required>
      </div>
      <div class="form-col">
        <label>Date de la dépense</label>
        <?= corpo_render_date_input('date_depense', date('Y-m-d')) ?>
      </div>
    </div>
    <div class="form-row">
      <div class="form-col" style="flex:1 1 100%">
        <label>Libellé</label>
        <input type="text" name="libelle" class="admin-input" maxlength="200" required>
      </div>
    </div>
    <div class="form-row">
      <div class="form-col" style="flex:1 1 100%">
        <label>Justificatif PDF</label>
        <input type="file" name="justificatif_pdf" accept="application/pdf,.pdf" required class="admin-input">
      </div>
    </div>
    <button type="submit" class="btn btn--primary">Envoyer la demande</button>
  </form>
</div>

<div class="admin-card">
  <h2 style="margin:0 0 var(--s4);font-size:1rem">Mes demandes</h2>
  <?php if (empty($myNotes)): ?>
    <p style="color:var(--text-muted)">Aucune demande.</p>
  <?php else: ?>
    <table class="admin-table">
      <thead><tr><th>Date</th><th>Structure</th><th>Libellé</th><th>Montant</th><th>Statut</th><th>PDF</th></tr></thead>
      <tbody>
        <?php foreach ($myNotes as $n): ?>
        <tr>
          <td data-label="Date"><?= htmlspecialchars((new DateTimeImmutable($n['date_depense']))->format('d/m/Y')) ?></td>
          <td data-label="Structure"><?= htmlspecialchars($n['structure_nom'] ?? '') ?></td>
          <td data-label="Libellé"><?= htmlspecialchars($n['libelle']) ?></td>
          <td data-label="Montant"><?= number_format((float)$n['montant'], 2, ',', ' ') ?> €</td>
          <td data-label="Statut"><?= htmlspecialchars(nf_statut_label((string)$n['statut'])) ?></td>
          <td data-label="PDF"><a href="api/note-frais-pdf.php?id=<?= (int)$n['id'] ?>" target="_blank" rel="noopener">PDF</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php endif; ?>

<script>
function nfSyncStructSelect() {
  var sel = document.getElementById('nf-struct-type');
  var hid = document.getElementById('nf-struct-id');
  if (!sel || !hid) return;
  hid.value = sel.options[sel.selectedIndex].getAttribute('data-id') || '';
}
document.addEventListener('DOMContentLoaded', nfSyncStructSelect);
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
