<?php
declare(strict_types=1);

$adminTitle = 'Commandes boutique';
$adminPage  = 'boutique-commandes';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/admin-header.php';

require_once __DIR__ . '/../includes/boutique.php';
require_once __DIR__ . '/../includes/comptabilite.php';

if (!boutique_db_ready($pdo)) {
    echo '<div class="flash flash--warn">Les tables boutique ne sont pas installées. Exécute la migration <code>tbl_boutique</code> depuis <a href="migrate.php">migrate.php</a>.</div>';
    require_once __DIR__ . '/includes/admin-footer.php';
    exit;
}

if (isAdminCorpo()) {
    $assos = $pdo->query("SELECT id, nom, type, ecole FROM associations ORDER BY ecole, nom")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $mAssoIds = getManagedAssoIds($pdo);
    $assos     = [];
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
$isCorpo       = isAdminCorpo();

$filterAssoId = isset($_GET['asso']) ? max(0, (int)$_GET['asso']) : 0;
if ($filterAssoId > 0 && !in_array($filterAssoId, $allowedAssoIds, true)) {
    $filterAssoId = 0;
}

$flashErr = '';
$flashOk  = isset($_GET['saved']);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_statut') {
    $cid      = (int)($_POST['commande_id'] ?? 0);
    $st       = trim((string)($_POST['statut'] ?? ''));
    $keepAsso = max(0, (int)($_POST['asso'] ?? 0));
    if ($keepAsso > 0 && !in_array($keepAsso, $allowedAssoIds, true)) {
        $keepAsso = 0;
    }
    $r = boutique_order_admin_set_statut($pdo, $cid, $st, $isCorpo, $allowedAssoIds);
    if ($r['ok']) {
        $qAsso = $keepAsso > 0 ? '&asso=' . $keepAsso : '';
        $next  = (($_POST['redirect'] ?? '') === 'detail')
            ? 'boutique-commandes.php?id=' . $cid . '&saved=1' . $qAsso
            : 'boutique-commandes.php?saved=1' . $qAsso;
        header('Location: ' . $next);
        exit;
    }
    $flashErr = $r['msg'] ?? 'Erreur.';
}

$detailId = isset($_GET['id']) ? max(0, (int)$_GET['id']) : 0;
$detail   = $detailId > 0 ? boutique_order_admin_detail($pdo, $detailId, $isCorpo, $allowedAssoIds) : null;

$qsAsso = $filterAssoId > 0 ? '&asso=' . $filterAssoId : '';

function boutique_cmd_statut_admin(string $s): string {
    return match ($s) {
        'paye'       => 'Payée',
        'en_attente' => 'En attente paiement',
        'init'       => 'Initiée',
        'echec'      => 'Échec',
        'annule'     => 'Annulée',
        default      => $s,
    };
}

$orders = (!$detail || $detailId === 0)
    ? boutique_orders_list_for_admin($pdo, $isCorpo, $allowedAssoIds, $filterAssoId)
    : [];
?>

<h1 class="admin-page-title">Commandes boutique</h1>

<?php if ($flashOk): ?>
  <div class="flash flash--ok">Statut enregistré.</div>
<?php endif; ?>
<?php if ($flashErr !== ''): ?>
  <div class="flash flash--err"><?= htmlspecialchars($flashErr) ?></div>
<?php endif; ?>

<p style="font-size:.85rem;color:var(--text-muted);margin:calc(var(--s6)*-1) 0 var(--s5)">
  <a href="boutique.php">← Retour aux produits</a>
  &nbsp;·&nbsp;
  <a href="../boutique.php" target="_blank" rel="noopener">Catalogue public</a>
</p>

<?php if ($detail): ?>
  <?php
  $c   = $detail['commande'];
  $lns = $detail['lignes'];
  $hid = (int)($detail['lignes_autres_structures'] ?? 0);
  $st0 = (string)($c['statut'] ?? 'init');
  ?>
  <div class="admin-card">
    <h2>Commande
    <p style="font-size:.85rem;color:var(--text-muted);margin-bottom:var(--s4)">
      <?= htmlspecialchars(boutique_cmd_statut_admin($st0)) ?>
      · <?= htmlspecialchars((string)($c['created_at'] ?? '')) ?>
      · <?= number_format((float)($c['montant_total'] ?? 0), 2, ',', ' ') ?> €
      · <?= htmlspecialchars((string)($c['provider'] ?? '')) ?>
      <?php if (($c['provider_ref'] ?? '') !== '' && ($c['provider_ref'] ?? '') !== null): ?>
        <span style="word-break:break-all"> · ref <?= htmlspecialchars((string)$c['provider_ref']) ?></span>
      <?php endif; ?>
    </p>

    <div style="margin-bottom:var(--s5);padding:var(--s5);background:rgba(255,255,255,.02);border:1px solid var(--border);border-radius:var(--r-md)">
      <h3 style="font-size:.95rem;margin-bottom:var(--s3)">Modifier le statut</h3>
      <form method="post" class="admin-form" style="display:flex;flex-wrap:wrap;gap:var(--s3);align-items:flex-end">
        <input type="hidden" name="action" value="set_statut">
        <input type="hidden" name="commande_id" value="<?= (int)$c['id'] ?>">
        <input type="hidden" name="asso" value="<?= (int)$filterAssoId ?>">
        <input type="hidden" name="redirect" value="detail">
        <div class="form-col">
          <label for="statut-cmd">Statut</label>
          <select name="statut" id="statut-cmd" style="min-width:14rem">
            <?php foreach (BOUTIQUE_COMMANDE_STATUTS as $sv): ?>
              <option value="<?= htmlspecialchars($sv) ?>"<?= $st0 === $sv ? ' selected' : '' ?>><?= htmlspecialchars(boutique_cmd_statut_admin($sv)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn--primary">Enregistrer</button>
      </form>
      <p style="font-size:.72rem;color:var(--text-muted);margin:var(--s3) 0 0">Usage manuel (ex. annulation, litige). Les paiements en ligne mettent aussi à jour ce statut automatiquement.</p>
    </div>

    <?php if ($hid > 0): ?>
      <div class="flash flash--info" style="margin-bottom:var(--s4)">
        Cette commande contient aussi <strong><?= $hid ?></strong> ligne(s) pour d’autres structures (non affichées).
      </div>
    <?php endif; ?>
    <?php
      $comptaHref = '';
      if (($c['statut'] ?? '') === 'paye' && !empty($lns) && compta_has_source_columns($pdo)) {
          $ln0 = $lns[0];
          $comptaHref = 'comptabilite.php?type=' . urlencode((string)($ln0['structure_type'] ?? 'asso'))
              . '&id=' . (int)($ln0['structure_id'] ?? 0) . '&tab=encaissements';
      }
    ?>
    <?php if ($comptaHref !== ''): ?>
      <p style="margin-bottom:var(--s4)">
        <a href="<?= htmlspecialchars($comptaHref) ?>" class="btn btn--ghost btn--sm">💰 Voir / importer en compta</a>
      </p>
    <?php endif; ?>
    <p style="margin-bottom:var(--s4)">
      <strong><?= htmlspecialchars((string)($c['prenom'] ?? '')) ?> <?= htmlspecialchars((string)($c['nom'] ?? '')) ?></strong><br>
      <span style="font-size:.88rem;color:var(--text-muted)"><?= htmlspecialchars((string)($c['email'] ?? '')) ?></span>
      <?php if (!empty($c['user_id'])): ?>
        <span style="font-size:.82rem;color:var(--text-muted)"> · user
      <?php endif; ?>
    </p>
    <div style="overflow-x:auto">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Produit</th>
            <th>Structure</th>
            <th>Prix u.</th>
            <th>Qté</th>
            <th>Sous-total</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($lns as $ln): ?>
          <tr>
            <td><?= htmlspecialchars((string)($ln['titre_snapshot'] ?? '')) ?> <span style="opacity:.6">(
            <td><?= htmlspecialchars((string)($ln['structure_type'] ?? '')) ?>
            <td><?= number_format((float)($ln['prix_unitaire'] ?? 0), 2, ',', ' ') ?> €</td>
            <td><?= (int)($ln['quantite'] ?? 0) ?></td>
            <td><?= number_format((float)($ln['prix_unitaire'] ?? 0) * (int)($ln['quantite'] ?? 0), 2, ',', ' ') ?> €</td>
          </tr>
        <?php endforeach; ?>
        <?php if ($lns === []): ?>
          <tr><td colspan="5" style="color:var(--text-muted)">Aucune ligne.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <p style="margin-top:var(--s5)">
      <a class="btn btn--sm" href="boutique-commandes.php<?= $filterAssoId > 0 ? '?asso=' . (int)$filterAssoId : '' ?>">← Liste des commandes</a>
      <a class="btn btn--sm" href="../boutique.php?order=<?= (int)$c['id'] ?>" target="_blank" rel="noopener">Aperçu côté site</a>
    </p>
  </div>
<?php elseif ($detailId > 0): ?>
  <div class="flash flash--err">Commande introuvable ou hors de ton périmètre.</div>
  <p><a class="btn" href="boutique-commandes.php<?= $filterAssoId > 0 ? '?asso=' . (int)$filterAssoId : '' ?>">← Liste</a></p>
<?php endif; ?>

<?php if (!$detail || $detailId === 0): ?>
<div class="admin-card">
  <h2>Liste (<?= count($orders) ?> affichée(s), max. 300)</h2>

  <form method="get" class="admin-form" style="display:flex;flex-wrap:wrap;gap:var(--s4);align-items:flex-end;margin-bottom:var(--s5)">
    <div class="form-col">
      <label for="filtre-asso">Filtrer par association</label>
      <select name="asso" id="filtre-asso" onchange="this.form.submit()">
        <option value="">Toutes les associations</option>
        <?php foreach ($assos as $a): ?>
          <option value="<?= (int)$a['id'] ?>"<?= $filterAssoId === (int)$a['id'] ? ' selected' : '' ?>>
            <?= htmlspecialchars((string)($a['nom'] ?? '')) ?> (<?= htmlspecialchars((string)($a['ecole'] ?? '')) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <noscript><button type="submit" class="btn btn--sm">Appliquer</button></noscript>
  </form>

  <?php if (!$orders): ?>
    <p style="color:var(--text-muted)">Aucune commande pour ce filtre ou ton périmètre.</p>
  <?php else: ?>
    <div style="overflow-x:auto">
      <table class="admin-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Date</th>
            <th>Associations</th>
            <th>Client</th>
            <th>Email</th>
            <th>Montant</th>
            <th>Statut</th>
            <th>Lignes</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($orders as $c):
            $cid = (int)$c['id'];
            $stR = (string)($c['statut'] ?? 'init');
            $resume = (string)($c['assos_resume'] ?? '');
            if (strlen($resume) > 120) {
                $resume = substr($resume, 0, 117) . '…';
            }
            ?>
          <tr>
            <td><?= $cid ?></td>
            <td><?= htmlspecialchars((string)($c['created_at'] ?? '')) ?></td>
            <td style="max-width:14rem;font-size:.78rem;line-height:1.35"><?= htmlspecialchars($resume !== '' ? $resume : '-') ?></td>
            <td><?= htmlspecialchars(trim((string)($c['prenom'] ?? '') . ' ' . (string)($c['nom'] ?? ''))) ?></td>
            <td><?= htmlspecialchars((string)($c['email'] ?? '')) ?></td>
            <td><?= number_format((float)($c['montant_total'] ?? 0), 2, ',', ' ') ?> €</td>
            <td>
              <form method="post" style="display:flex;flex-wrap:wrap;gap:.35rem;align-items:center">
                <input type="hidden" name="action" value="set_statut">
                <input type="hidden" name="commande_id" value="<?= $cid ?>">
                <input type="hidden" name="asso" value="<?= (int)$filterAssoId ?>">
                <input type="hidden" name="redirect" value="list">
                <select name="statut" class="admin-form" style="font-size:.75rem;padding:.25rem .4rem;min-width:9rem">
                  <?php foreach (BOUTIQUE_COMMANDE_STATUTS as $sv): ?>
                    <option value="<?= htmlspecialchars($sv) ?>"<?= $stR === $sv ? ' selected' : '' ?>><?= htmlspecialchars(boutique_cmd_statut_admin($sv)) ?></option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn--sm" title="Enregistrer le statut">OK</button>
              </form>
            </td>
            <td><?= (int)($c['nb_lignes'] ?? 0) ?></td>
            <td class="actions">
              <a class="btn btn--sm" href="boutique-commandes.php?id=<?= $cid ?><?= htmlspecialchars($qsAsso) ?>">Détails</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
