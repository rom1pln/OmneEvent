<?php
$adminTitle = 'Tableau de bord';
$adminPage  = 'dashboard';
require_once '../includes/db.php';
require_once 'includes/admin-header.php';

$counts = [];
try {
    $counts['associations'] = $pdo->query("SELECT COUNT(*) FROM associations")->fetchColumn();
    $counts['evenements']   = $pdo->query("SELECT COUNT(*) FROM evenements WHERE statut='publie'")->fetchColumn();
    $counts['sports']       = $pdo->query("SELECT COUNT(*) FROM sports")->fetchColumn();
    $counts['partenaires']  = $pdo->query("SELECT COUNT(*) FROM partenaires WHERE statut='publie'")->fetchColumn();
    $counts['demandes_ext'] = $pdo->query("SELECT COUNT(*) FROM demandes_partenariat")->fetchColumn();

    if (isAdminCorpo()) {
        $counts['validation_pending'] = $pdo->query("SELECT COUNT(*) FROM demandes_validation WHERE statut='en_attente'")->fetchColumn();
        $counts['users']              = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $counts['users_attente']      = $pdo->query("SELECT COUNT(*) FROM users WHERE statut='en_attente'")->fetchColumn();
        $counts['actualites_pending'] = $pdo->query("SELECT COUNT(*) FROM actualites WHERE statut='en_attente' AND IFNULL(visibilite,'public')='public'")->fetchColumn();
        $counts['adhesions_pending']  = $pdo->query("SELECT COUNT(*) FROM demandes_adhesion WHERE statut='en_attente'")->fetchColumn();
    }
} catch (PDOException $e) {

}

if (isAdminPanelNotesFraisOnly()) {
    $tiles = [
        ['', 'Notes de frais', '→', 'notes-frais.php'],
    ];
} else {
$tiles = [
    ['', 'Associations',  $counts['associations'] ?? '?', 'associations.php'],
    ['', 'Événements',    $counts['evenements']   ?? '?', 'evenements.php'],
];
if (canAccessSportAdmin($pdo)) {
    $tiles[] = ['', 'Sports',  $counts['sports'] ?? '?', 'sports.php'];
}
$tiles[] = ['', 'Partenaires', $counts['partenaires'] ?? '?', 'partenaires.php'];

if (isAdminCorpo()) {
    $tiles[] = ['', 'À valider',              $counts['validation_pending'] ?? 0, 'validation.php'];
    $tiles[] = ['', 'Utilisateurs',           ($counts['users'] ?? 0) . ($counts['users_attente'] > 0 ? ' (+' . $counts['users_attente'] . ' attente)' : ''), 'users.php'];
    $tiles[] = ['', 'Actualités en attente',  $counts['actualites_pending'] ?? 0, 'actualites.php'];
    $tiles[] = ['', 'Demandes partenariat',   $counts['demandes_ext'] ?? 0, 'demandes.php'];
}
}

if (!isAdminPanelNotesFraisOnly() && isAdminPanelDelegationOnly()) {
    $tileLinks = array_column($tiles, 3);
    if (adminPanelDelegationAllows($pdo, 'comptabilite') && !in_array('comptabilite.php', $tileLinks, true)) {
        $tiles[] = ['', 'Comptabilité', '→', 'comptabilite.php'];
    }
    if (adminPanelDelegationAllows($pdo, 'actualites') && !in_array('actualites.php', $tileLinks, true)) {
        $tiles[] = ['', 'Actualités', '→', 'actualites.php'];
    }
}
?>

<h1 class="admin-page-title">
  Tableau de bord
  <?= roleBadge(currentRole()) ?>
</h1>

<?php if (!empty($_GET['denied'])): ?>
  <div class="flash flash--err">Tu n’as pas accès à cette page du panneau. Utilise le menu à gauche.</div>
<?php endif; ?>
<?php if (!empty($_GET['err'])): ?>
  <div class="flash flash--err"><?= htmlspecialchars($_GET['err']) ?></div>
<?php endif; ?>
<?php if (!empty($_GET['ok'])): ?>
  <div class="flash flash--ok"><?= htmlspecialchars($_GET['ok']) ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:var(--s4);margin-bottom:var(--s8)">
  <?php foreach ($tiles as [$icon, $label, $val, $link]):
    if (!adminPanelDelegationAllows($pdo, adminPanelPageKeyFromHref($link))) {
        continue;
    }
    ?>
    <a href="<?= $link ?>" style="
        background:var(--surface);border:1px solid var(--border);border-radius:var(--r-xl);
        padding:var(--s5);text-decoration:none;display:block;
        transition:border-color var(--ease);"
      onmouseover="this.style.borderColor='var(--purple)'"
      onmouseout="this.style.borderColor='var(--border)'">
      <div style="font-size:1.6rem;margin-bottom:var(--s2)"><?= $icon ?></div>
      <div style="font-size:1.4rem;font-weight:700;color:#fff"><?= $val ?></div>
      <div style="font-size:.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.08em"><?= $label ?></div>
    </a>
  <?php endforeach; ?>
</div>

<?php if (isAdminCorpo()): ?>
    <div class="admin-card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:var(--s4)">
      <h2 style="margin:0">Demandes de validation récentes</h2>
      <a href="validation.php" class="btn btn--ghost btn--sm">Voir tout →</a>
    </div>
    <?php
    $dvRecentes = $pdo->query(
        "SELECT dv.*, u.username AS demandeur
         FROM demandes_validation dv
         JOIN users u ON u.id = dv.user_id
         WHERE dv.statut = 'en_attente'
         ORDER BY dv.created_at DESC
         LIMIT 5"
    )->fetchAll();
    ?>
    <?php if (empty($dvRecentes)): ?>
      <p style="color:var(--text-muted);font-size:.85rem">Aucune demande en attente.</p>
    <?php else: ?>
      <table class="admin-table">
        <thead>
          <tr><th>Type</th><th>Demandé par</th><th>Structure</th><th>Date</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($dvRecentes as $dv):
            $pl = json_decode($dv['payload'], true);
            $titre = $pl['titre'] ?? $pl['nom'] ?? '-';
          ?>
            <tr>
              <td>
                <?= htmlspecialchars($dv['type']) ?><br>
                <span style="font-size:.72rem;color:var(--text-muted)"><?= htmlspecialchars($titre) ?></span>
              </td>
              <td style="font-size:.8rem"><?= htmlspecialchars($dv['demandeur']) ?></td>
              <td style="font-size:.78rem"><?= htmlspecialchars($dv['structure_type']) ?></td>
              <td style="font-size:.75rem;color:var(--text-muted)"><?= date('d/m/Y H:i', strtotime($dv['created_at'])) ?></td>
              <td><a href="validation.php" class="btn btn--sm btn--primary">Traiter</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

    <?php
  $adhTotal = (int)$pdo->query("SELECT COUNT(*) FROM demandes_adhesion WHERE statut='en_attente'")->fetchColumn();
  $adhRecentes = $pdo->query(
      "SELECT da.id, da.user_id, da.structure_type, da.structure_id, da.created_at,
              u.username AS demandeur,
              COALESCE(a.nom, s.nom) AS struct_nom
       FROM demandes_adhesion da
       JOIN users u ON u.id = da.user_id
       LEFT JOIN associations a  ON da.structure_type IN ('asso','bde','bds') AND a.id = da.structure_id
       LEFT JOIN sports       s  ON da.structure_type = 'sport' AND s.id = da.structure_id
       WHERE da.statut = 'en_attente'
       GROUP BY da.id
       ORDER BY da.created_at DESC
       LIMIT 5"
  )->fetchAll();
  if (!empty($adhRecentes)): ?>
    <div class="admin-card">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:var(--s4);gap:var(--s3);flex-wrap:wrap">
        <h2 style="margin:0">🙋 Demandes d'adhésion en attente
          <span style="background:#ef4444;color:#fff;border-radius:999px;padding:1px 8px;font-size:.7rem;margin-left:.4rem;vertical-align:middle"><?= $adhTotal ?></span>
        </h2>
        <?php if ($adhTotal > 5): ?>
          <a href="users.php" class="btn btn--ghost btn--sm">Voir tout →</a>
        <?php endif; ?>
      </div>
      <div style="max-height:340px;overflow-y:auto;border:1px solid var(--border);border-radius:var(--r-md)">
        <table class="admin-table" style="margin:0">
          <thead>
            <tr><th>Utilisateur</th><th>Structure</th><th>Date</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach ($adhRecentes as $adh): ?>
              <tr>
                <td><?= htmlspecialchars($adh['demandeur']) ?></td>
                <td style="font-size:.8rem"><?= htmlspecialchars($adh['struct_nom'] ?? '-') ?> <span style="color:var(--text-muted);font-size:.7rem">(<?= htmlspecialchars($adh['structure_type']) ?>)</span></td>
                <td style="font-size:.75rem;color:var(--text-muted)"><?= date('d/m/Y', strtotime($adh['created_at'])) ?></td>
                <td>
                  <form method="post" action="users.php" style="display:inline">
                    <input type="hidden" name="action" value="assigner_structure">
                    <input type="hidden" name="user_id" value="<?= $adh['user_id'] ?>">
                    <input type="hidden" name="structure_type" value="<?= $adh['structure_type'] ?>">
                    <input type="hidden" name="structure_id" value="<?= $adh['structure_id'] ?>">
                    <input type="hidden" name="role_in_struct" value="adherent">
                    <button class="btn btn--sm btn--success" type="submit" title="L'utilisateur sera ajouté comme adhérent.">Accepter</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

    <div class="admin-card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:var(--s4)">
      <h2 style="margin:0">Dernières demandes partenariat (externe)</h2>
      <a href="demandes.php" class="btn btn--ghost btn--sm">Voir tout →</a>
    </div>
    <?php
    $demExternes = $pdo->query("SELECT * FROM demandes_partenariat ORDER BY created_at DESC LIMIT 5")->fetchAll();
    ?>
    <?php if (empty($demExternes)): ?>
      <p style="color:var(--text-muted);font-size:.85rem">Aucune demande.</p>
    <?php else: ?>
      <table class="admin-table">
        <thead><tr><th>Entreprise</th><th>Contact</th><th>Email</th><th>Date</th></tr></thead>
        <tbody>
          <?php foreach ($demExternes as $d): ?>
            <tr>
              <td><?= htmlspecialchars($d['organisation']) ?></td>
              <td><?= htmlspecialchars($d['nom_contact']) ?></td>
              <td><a href="mailto:<?= htmlspecialchars($d['email']) ?>"><?= htmlspecialchars($d['email']) ?></a></td>
              <td style="font-size:.78rem;color:var(--text-muted)"><?= date('d/m/Y H:i', strtotime($d['created_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

<?php else: ?>
    <div class="flash flash--warn">
    Vous avez le rôle Bureau. Tout contenu que vous soumettez doit être validé par un Admin Corpo avant publication.
  </div>
<?php endif; ?>

<?php require_once 'includes/admin-footer.php'; ?>
