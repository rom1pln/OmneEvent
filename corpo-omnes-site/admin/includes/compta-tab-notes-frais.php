<?php

$nfPending = nf_count_pending($pdo, $selType, $selId);
$nfFilter  = $_GET['nf_statut'] ?? '';
$nfFilterAllowed = ['soumise', 'approuvee_bureau', 'remboursee', 'refusee', ''];
if ($nfFilter !== '' && !in_array($nfFilter, $nfFilterAllowed, true)) {
    $nfFilter = '';
}
$nfList = nf_list_for_structure($pdo, $selType, $selId, $nfFilter !== '' ? $nfFilter : null);
$canManageTreso = nf_can_manage($pdo, $userId, $selType, $selId);
$nfDual = nf_dual_validation_ready($pdo);
?>

<div class="admin-card">
  <div class="cpt-sync-banner__head" style="display:flex;justify-content:space-between;align-items:flex-start;gap:var(--s4);flex-wrap:wrap;margin-bottom:var(--s4)">
    <div>
      <h2 style="margin:0;font-size:1.05rem">Notes de frais</h2>
      <p style="margin:.35rem 0 0;font-size:.82rem;color:var(--text-muted)">
        <?php if ($nfDual): ?>
          Double validation : un <strong>membre du bureau</strong> de la structure (hors demandeur), puis un <strong>responsable trésorerie</strong> distinct (ou admin de structure) — ensuite écriture comptable.
          <?php if (function_exists('isSuperAdmin') && isSuperAdmin()): ?>
            En tant que super admin, utilise « Valider (complet) » pour valider seul.
          <?php endif; ?>
        <?php else: ?>
          Applique la migration <code>nf_dual_validation_cols</code> pour activer la double validation.
        <?php endif; ?>
      </p>
    </div>
    <?php if ($nfPending > 0): ?>
      <span class="badge badge--pending"><?= (int)$nfPending ?> en attente</span>
    <?php endif; ?>
  </div>

  <div style="display:flex;gap:var(--s2);flex-wrap:wrap;margin-bottom:var(--s4)">
    <?php
    $nfFilters = [
        ''                  => 'Toutes',
        'soumise'           => 'Attente bureau',
        'approuvee_bureau'  => 'Attente tréso',
        'remboursee'        => 'Remboursées',
        'refusee'           => 'Refusées',
    ];
    foreach ($nfFilters as $k => $lab):
        $href = 'comptabilite.php?type=' . urlencode($selType) . '&id=' . $selId . '&tab=notes_frais' . ($k !== '' ? '&nf_statut=' . urlencode($k) : '');
        $cls = ($nfFilter === $k) ? 'btn btn--sm btn--primary' : 'btn btn--sm btn--ghost';
    ?>
      <a href="<?= htmlspecialchars($href) ?>" class="<?= $cls ?>"><?= htmlspecialchars($lab) ?></a>
    <?php endforeach; ?>
    <?php if (nf_can_submit($pdo, $userId, $selType, $selId)): ?>
      <a href="notes-frais.php?structure_type=<?= urlencode($selType) ?>&structure_id=<?= (int)$selId ?>" class="btn btn--sm btn--ghost">Déposer une note</a>
    <?php endif; ?>
  </div>

  <?php if (empty($nfList)): ?>
    <p style="color:var(--text-muted);font-size:.9rem">Aucune demande pour cette structure.</p>
  <?php else: ?>
    <div style="overflow-x:auto">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Membre</th>
            <th>Libellé</th>
            <th>Montant</th>
            <th>Statut</th>
            <?php if ($nfDual): ?><th>Validations</th><?php endif; ?>
            <th>PDF</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($nfList as $n):
            $membre = trim(($n['prenom'] ?? '') . ' ' . ($n['nom'] ?? ''));
            $st = (string)($n['statut'] ?? '');
            $canSuper  = nf_can_super_validate_note($pdo, $userId, $n);
            $canBureau = nf_can_approve_bureau_note($pdo, $userId, $n);
            $canTreso  = !$canSuper && nf_can_approve_treso_note($pdo, $userId, $n);
            $canReject = nf_can_reject_note($pdo, $userId, $n);
            $showActions = $canSuper || $canBureau || $canTreso || $canReject;
          ?>
          <tr>
            <td data-label="Date"><?= htmlspecialchars((new DateTimeImmutable($n['date_depense']))->format('d/m/Y')) ?></td>
            <td data-label="Membre">
              <strong><?= htmlspecialchars($membre ?: $n['email']) ?></strong>
              <br><small style="color:var(--text-muted)"><?= htmlspecialchars($n['email'] ?? '') ?></small>
            </td>
            <td data-label="Libellé"><?= htmlspecialchars($n['libelle']) ?></td>
            <td data-label="Montant"><?= number_format((float)$n['montant'], 2, ',', ' ') ?> €</td>
            <td data-label="Statut"><span class="badge"><?= htmlspecialchars(nf_statut_label($st)) ?></span></td>
            <?php if ($nfDual): ?>
            <td data-label="Validations" style="font-size:.75rem;color:var(--text-muted)">
              <?php foreach (nf_validation_lines($n) as $line): ?>
                <div><?= htmlspecialchars($line) ?></div><br>
              <?php endforeach; ?>
              <?php if (empty($n['valide_bureau_par']) && empty($n['valide_treso_par'])): ?>—<?php endif; ?>
            </td>
            <?php endif; ?>
            <td data-label="PDF">
              <a href="api/note-frais-pdf.php?id=<?= (int)$n['id'] ?>" target="_blank" rel="noopener">PDF</a>
              <?php if (!empty($n['compta_transaction_id'])): ?>
                <br><a href="?type=<?= urlencode($selType) ?>&id=<?= $selId ?>&tab=transactions&q=%23<?= (int)$n['compta_transaction_id'] ?>" class="btn btn--ghost btn--sm" style="margin-top:.25rem;font-size:.7rem">Écriture</a>
              <?php endif; ?>
            </td>
            <td data-label="Actions">
              <?php if (!$showActions): ?>
                <span style="color:var(--text-muted);font-size:.78rem">—</span>
              <?php else: ?>
                <?php if ($canSuper): ?>
                  <form method="post" style="margin:0 0 var(--s2)">
                    <input type="hidden" name="action" value="nf_super_validate">
                    <input type="hidden" name="note_id" value="<?= (int)$n['id'] ?>">
                    <button type="submit" class="btn btn--sm btn--primary" style="width:100%"
                            onclick="return confirm('Valider et comptabiliser (super admin) ?')">Valider (complet)</button>
                  </form>
                <?php endif; ?>
                <?php if ($canBureau): ?>
                  <form method="post" style="margin:0 0 var(--s2)">
                    <input type="hidden" name="action" value="nf_approve_bureau">
                    <input type="hidden" name="note_id" value="<?= (int)$n['id'] ?>">
                    <button type="submit" class="btn btn--sm btn--primary" style="width:100%"
                            onclick="return confirm('Valider en tant que membre du bureau ?')">Valider (bureau)</button>
                  </form>
                <?php endif; ?>
                <?php if ($canTreso): ?>
                  <form method="post" style="margin:0 0 var(--s2)">
                    <input type="hidden" name="action" value="nf_approve_treso">
                    <input type="hidden" name="note_id" value="<?= (int)$n['id'] ?>">
                    <button type="submit" class="btn btn--sm btn--primary" style="width:100%"
                            onclick="return confirm('Valider en trésorerie et créer l\'écriture comptable ?')">Valider (trésorerie)</button>
                  </form>
                <?php endif; ?>
                <?php if ($canReject): ?>
                  <details>
                    <summary class="btn btn--sm btn--danger" style="cursor:pointer;list-style:none;width:100%">Refuser</summary>
                    <form method="post" style="margin-top:var(--s2)">
                      <input type="hidden" name="action" value="nf_reject">
                      <input type="hidden" name="note_id" value="<?= (int)$n['id'] ?>">
                      <textarea name="commentaire_refus" class="admin-input" rows="2" required placeholder="Motif du refus…"></textarea>
                      <button type="submit" class="btn btn--sm btn--danger" style="margin-top:var(--s2);width:100%">Confirmer le refus</button>
                    </form>
                  </details>
                <?php endif; ?>
              <?php endif; ?>
            </td>
          </tr>
          <?php if (!empty($n['commentaire_membre']) || !empty($n['commentaire_bureau']) || !empty($n['commentaire_tresorier'])): ?>
          <tr>
            <td colspan="<?= $nfDual ? 8 : 7 ?>" style="font-size:.78rem;color:var(--text-muted);background:rgba(255,255,255,.02)">
              <?php if (!empty($n['commentaire_membre'])): ?>Membre : <?= htmlspecialchars($n['commentaire_membre']) ?><?php endif; ?>
              <?php if (!empty($n['commentaire_bureau'])): ?><?= !empty($n['commentaire_membre']) ? ' — ' : '' ?>Bureau : <?= htmlspecialchars($n['commentaire_bureau']) ?><?php endif; ?>
              <?php if (!empty($n['commentaire_tresorier']) && $st === 'refusee'): ?><?= (!empty($n['commentaire_membre']) || !empty($n['commentaire_bureau'])) ? ' — ' : '' ?>Refus : <?= htmlspecialchars($n['commentaire_tresorier']) ?><?php endif; ?>
            </td>
          </tr>
          <?php endif; ?>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
