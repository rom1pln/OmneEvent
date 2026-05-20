<?php
/*
 * admin/setup-password.php
 * ============================================================
 * OUTIL DE CONFIGURATION INITIALE - À SUPPRIMER APRÈS UTILISATION
 *
 * URL : http://localhost/corpo-omnes-site/admin/setup-password.php
 *
 * Ce script configure les mots de passes des comptes par défaut.
 * Supprimez ce fichier une fois terminé.
 * ============================================================
 */

require_once '../includes/db.php';

// Mots de passe à configurer - changez-les avant d'exécuter !
$comptes = [
    'superadmin' => 'superadmin2026',  // ← Super Admin
    'admincorpo' => 'admin2026',       // ← Admin Corpo
];

$ok    = [];
$erreurs = [];

foreach ($comptes as $username => $mdp) {
    $hash = password_hash($mdp, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = ?");
    $stmt->execute([$hash, $username]);
    if ($stmt->rowCount() > 0) {
        $ok[] = ['user' => $username, 'mdp' => $mdp];
    } else {
        $erreurs[] = $username;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Setup - Corpo Omnes</title>
  <style>
    body { font-family: sans-serif; max-width: 600px; margin: 3rem auto; padding: 1rem; }
    .ok  { background:#d1fae5; border:1px solid #6ee7b7; border-radius:8px; padding:1rem; margin-bottom:1rem; }
    .err { background:#fee2e2; border:1px solid #fca5a5; border-radius:8px; padding:1rem; margin-bottom:1rem; }
    table { border-collapse: collapse; width: 100%; margin-top: .5rem; }
    td, th { border: 1px solid #ccc; padding: .4rem .7rem; }
    th { background:#f3f4f6; }
    .warn { background:#fef3c7; border:1px solid #fcd34d; border-radius:8px; padding:1rem; margin-top:1rem; }
  </style>
</head>
<body>
  <h2>Configuration initiale - Corpo Omnes</h2>

  <?php if ($ok): ?>
    <div class="ok">
      <strong>Comptes configurés avec succès :</strong>
      <table>
        <tr><th>Identifiant</th><th>Mot de passe</th><th>Rôle</th></tr>
        <?php foreach ($ok as $c): ?>
          <tr>
            <td><?= htmlspecialchars($c['user']) ?></td>
            <td><?= htmlspecialchars($c['mdp']) ?></td>
            <td><?= $c['user'] === 'superadmin' ? 'Super Administrateur' : 'Admin Corpo' ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  <?php endif; ?>

  <?php if ($erreurs): ?>
    <div class="err">
      <strong>Comptes introuvables en base :</strong>
      <?= implode(', ', array_map('htmlspecialchars', $erreurs)) ?><br>
      Vérifiez que database.sql a bien été importé dans phpMyAdmin.
    </div>
  <?php endif; ?>

  <div class="warn">
    ⚠️ <strong>Supprimez ce fichier immédiatement après configuration !</strong><br>
    <code>corpo-omnes-site/admin/setup-password.php</code>
  </div>

  <p style="margin-top:1.5rem">
    <a href="login.php">→ Aller à la page de connexion</a>
  </p>
</body>
</html>
