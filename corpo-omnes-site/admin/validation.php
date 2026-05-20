<?php

require_once '../includes/db.php';
$adminTitle = 'Validation des demandes';
$adminPage  = 'validation';
require_once 'includes/admin-header.php';
requireAdmin();

$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['demande_id'])) {
    $demandeId = (int)$_POST['demande_id'];
    $action    = $_POST['action'];
    $message   = trim($_POST['message_refus'] ?? '');

    if ($action === 'valider') {

        $d = $pdo->prepare("SELECT * FROM demandes_validation WHERE id = ?");
        $d->execute([$demandeId]);
        $demande = $d->fetch();

        if ($demande) {
            $payload = json_decode($demande['payload'], true);
            try {

                switch ($demande['type']) {
                    case 'evenement':
                        require_once __DIR__ . '/../includes/billetterie.php';
                        evt_apply_validation_demande($pdo, $demande, is_array($payload) ? $payload : []);
                        break;

                    case 'partenaire':
                        if (!empty($payload['id'])) {
                            $stmt = $pdo->prepare("UPDATE partenaires SET nom=?, type=?, offre=?, code=?, campus=?, lien=?, description=?, statut='publie' WHERE id=?");
                            $stmt->execute([$payload['nom'], $payload['type'], $payload['offre']??'', $payload['code']??'', $payload['campus']??'Tous', $payload['lien']??'#', $payload['description']??'', $payload['id']]);
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO partenaires (nom,type,offre,code,campus,lien,description,structure_type,structure_id,statut,auteur_id) VALUES (?,?,?,?,?,?,?,?,?,'publie',?)");
                            $stmt->execute([$payload['nom'], $payload['type']??'', $payload['offre']??'', $payload['code']??'', $payload['campus']??'Tous', $payload['lien']??'#', $payload['description']??'', $demande['structure_type'], $demande['structure_id'], $demande['user_id']]);
                        }
                        break;

                    case 'actualite':

                        $stmt = $pdo->prepare("UPDATE actualites SET statut='publie' WHERE id=?");
                        $stmt->execute([$payload['actualite_id'] ?? 0]);
                        break;

                    case 'nouvelle_asso':

                        break;
                }

                $upd = $pdo->prepare("UPDATE demandes_validation SET statut='valide', validated_by=?, validated_at=NOW() WHERE id=?");
                $upd->execute([currentUserId(), $demandeId]);
                $flash = '<div class="flash flash--ok">Demande validée et contenu publié.</div>';
            } catch (PDOException $e) {
                $flash = '<div class="flash flash--err">Erreur lors de l\'application : ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }

    } elseif ($action === 'refuser') {
        $upd = $pdo->prepare("UPDATE demandes_validation SET statut='refuse', validated_by=?, validated_at=NOW(), message_refus=? WHERE id=?");
        $upd->execute([currentUserId(), $message, $demandeId]);
        $flash = '<div class="flash flash--warn">⚠️ Demande refusée.</div>';
    }
}

$filtre   = $_GET['statut'] ?? 'en_attente';
$filtreOk = in_array($filtre, ['en_attente','valide','refuse','tout']) ? $filtre : 'en_attente';

$whereClause = $filtreOk === 'tout' ? '' : "WHERE dv.statut = ?";
$params      = $filtreOk === 'tout' ? [] : [$filtreOk];

$sql = "SELECT dv.*, u.username AS demandeur,
               v.username AS validateur
        FROM demandes_validation dv
        JOIN users u ON u.id = dv.user_id
        LEFT JOIN users v ON v.id = dv.validated_by
        $whereClause
        ORDER BY dv.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$demandes = $stmt->fetchAll();

$counts = $pdo->query("SELECT statut, COUNT(*) c FROM demandes_validation GROUP BY statut")->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<h1 class="admin-page-title">File de validation</h1>
<?= $flash ?>

<div style="display:flex;gap:.5rem;margin-bottom:var(--s6);flex-wrap:wrap">
  <?php
  $tabs = [
    'en_attente' => ['En attente', 'badge--pending'],
    'valide'     => ['Validées',   'badge--ok'],
    'refuse'     => ['Refusées',   'badge--ko'],
    'tout'       => ['Toutes',         ''],
  ];
  foreach ($tabs as $key => [$label, $badgeCls]): ?>
    <a href="?statut=<?= $key ?>"
       class="btn <?= $filtreOk === $key ? 'btn--primary' : '' ?>"
       style="<?= $filtreOk === $key ? '' : 'background:var(--surface);border-color:var(--border)' ?>">
      <?= $label ?>
      <?php if (isset($counts[$key]) && $counts[$key] > 0): ?>
        <span class="badge <?= $badgeCls ?>" style="margin-left:.3rem"><?= $counts[$key] ?></span>
      <?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>

<div class="admin-card" style="padding:0;overflow:hidden">
  <?php if (empty($demandes)): ?>
    <p style="padding:var(--s8);text-align:center;color:var(--text-muted)">
      Aucune demande <?= $filtreOk !== 'tout' ? '"' . htmlspecialchars($filtreOk) . '"' : '' ?>.
    </p>
  <?php else: ?>
    <table class="admin-table">
      <thead>
        <tr>
          <th>
          <th>Type</th>
          <th>Structure</th>
          <th>Demandé par</th>
          <th>Date</th>
          <th>Statut</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($demandes as $d):
          $payload = json_decode($d['payload'], true);
          $titre   = $payload['titre'] ?? $payload['nom'] ?? '-';
          $typeLabels = [
            'evenement'        => 'Événement',
            'partenaire'       => 'Partenaire',
            'offre_partenaire' => 'Offre partenaire',
            'actualite'        => 'Actualité',
            'contenu'          => 'Contenu',
            'nouvelle_asso'    => '🆕 Proposition asso',
          ];
        ?>
          <tr>
            <td><?= $d['id'] ?></td>
            <td>
              <?= $typeLabels[$d['type']] ?? $d['type'] ?><br>
              <span style="color:var(--text-muted);font-size:.72rem"><?= htmlspecialchars($titre) ?></span>
            </td>
            <td>
              <span style="font-size:.8rem"><?= htmlspecialchars($d['structure_type']) ?></span>
              <?php if ($d['structure_id']): ?>
                <br><span style="color:var(--text-muted);font-size:.7rem">id <?= $d['structure_id'] ?></span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($d['demandeur']) ?></td>
            <td style="font-size:.78rem;color:var(--text-muted)">
              <?= date('d/m/Y H:i', strtotime($d['created_at'])) ?>
            </td>
            <td>
              <?php
                $badgeMap = ['en_attente' => 'badge--pending', 'valide' => 'badge--ok', 'refuse' => 'badge--ko'];
                $badgeLabel = ['en_attente' => 'En attente', 'valide' => 'Validé', 'refuse' => 'Refusé'];
              ?>
              <span class="badge <?= $badgeMap[$d['statut']] ?? '' ?>">
                <?= $badgeLabel[$d['statut']] ?? $d['statut'] ?>
              </span>
              <?php if ($d['validateur']): ?>
                <br><span style="font-size:.68rem;color:var(--text-muted)">par <?= htmlspecialchars($d['validateur']) ?></span>
              <?php endif; ?>
              <?php if ($d['message_refus']): ?>
                <br><span style="font-size:.68rem;color:#fca5a5" title="<?= htmlspecialchars($d['message_refus']) ?>">⚠️ Motif disponible</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="actions">
                                <button class="btn btn--sm"
                        onclick="toggleDetail(<?= $d['id'] ?>)"
                        style="background:var(--surface);border-color:var(--border)">
                  👁️ Détail
                </button>

                <?php if ($d['statut'] === 'en_attente'): ?>
                                    <form method="post" style="display:inline" onsubmit="return confirm('Valider et publier cette demande ?')">
                    <input type="hidden" name="demande_id" value="<?= $d['id'] ?>">
                    <input type="hidden" name="action" value="valider">
                    <button type="submit" class="btn btn--sm btn--success">Valider</button>
                  </form>
                                    <button class="btn btn--sm btn--danger"
                          onclick="showRefus(<?= $d['id'] ?>)">
                    Refuser
                  </button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
                    <tr id="detail-<?= $d['id'] ?>" style="display:none">
            <td colspan="7" style="background:rgba(255,255,255,.02);padding:var(--s4)">
              <?php if ($d['type'] === 'nouvelle_asso' && $payload): ?>
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--s4);font-size:.82rem">
                  <div>
                    <p style="color:var(--blue-light);font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.2rem">Nom proposé</p>
                    <p><?= htmlspecialchars($payload['nom'] ?? '-') ?></p>
                  </div>
                  <div>
                    <p style="color:var(--blue-light);font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.2rem">Type · École · Campus</p>
                    <p><?= htmlspecialchars($payload['type'] ?? '-') ?> · <?= htmlspecialchars($payload['ecole'] ?? '-') ?> · <?= htmlspecialchars($payload['campus'] ?? '-') ?></p>
                  </div>
                  <div style="grid-column:1/-1">
                    <p style="color:var(--blue-light);font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.2rem">Description</p>
                    <p style="color:var(--text-muted)"><?= nl2br(htmlspecialchars($payload['description'] ?? '-')) ?></p>
                  </div>
                  <div style="grid-column:1/-1">
                    <p style="color:var(--blue-light);font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.2rem">Motivation</p>
                    <p style="color:var(--text-muted)"><?= nl2br(htmlspecialchars($payload['motivation'] ?? '-')) ?></p>
                  </div>
                  <?php if (!empty($payload['contact_nom']) || !empty($payload['contact_mail'])): ?>
                  <div>
                    <p style="color:var(--blue-light);font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.2rem">Porteur du projet</p>
                    <p><?= htmlspecialchars($payload['contact_nom'] ?? '') ?></p>
                    <?php if (!empty($payload['contact_mail'])): ?>
                      <a href="mailto:<?= htmlspecialchars($payload['contact_mail']) ?>" style="color:var(--purple-light);font-size:.78rem"><?= htmlspecialchars($payload['contact_mail']) ?></a>
                    <?php endif; ?>
                  </div>
                  <?php endif; ?>
                  <div style="grid-column:1/-1">
                    <a href="../admin/associations.php?action=add&nom=<?= urlencode($payload['nom'] ?? '') ?>&ecole=<?= urlencode($payload['ecole'] ?? '') ?>"
                       class="btn btn--sm btn--success" target="_blank">
                      ➕ Créer l'association depuis ce formulaire
                    </a>
                  </div>
                </div>
              <?php else: ?>
                <strong style="font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;color:var(--blue-light)">
                  Payload JSON
                </strong>
                <pre style="margin:.5rem 0 0;font-size:.75rem;color:var(--text-muted);white-space:pre-wrap;word-break:break-all"><?= htmlspecialchars(json_encode($payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>
              <?php endif; ?>

              <?php if ($d['statut'] === 'en_attente'): ?>
                                <div id="refus-<?= $d['id'] ?>" style="display:none;margin-top:var(--s4)">
                  <form method="post">
                    <input type="hidden" name="demande_id" value="<?= $d['id'] ?>">
                    <input type="hidden" name="action" value="refuser">
                    <label style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--blue-light)">
                      Motif du refus (optionnel)
                    </label>
                    <textarea name="message_refus" rows="3"
                              style="width:100%;background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:var(--r-md);padding:.5rem;color:#fff;font-size:.82rem;box-sizing:border-box;margin-top:.3rem"
                              placeholder="Expliquez pourquoi la demande est refusée..."></textarea>
                    <div style="margin-top:.5rem;display:flex;gap:.5rem">
                      <button type="submit" class="btn btn--sm btn--danger">Confirmer le refus</button>
                      <button type="button" class="btn btn--sm" onclick="hideRefus(<?= $d['id'] ?>)"
                              style="background:var(--surface);border-color:var(--border)">Annuler</button>
                    </div>
                  </form>
                </div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<script>
function toggleDetail(id) {
  const row = document.getElementById('detail-' + id);
  row.style.display = row.style.display === 'none' ? '' : 'none';
}
function showRefus(id) {
  document.getElementById('detail-' + id).style.display = '';
  document.getElementById('refus-' + id).style.display = '';
}
function hideRefus(id) {
  document.getElementById('refus-' + id).style.display = 'none';
}
</script>

<?php require_once 'includes/admin-footer.php'; ?>
