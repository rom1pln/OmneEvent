<?php

require_once '../includes/db.php';

$comptes = [
    'superadmin' => 'superadmin2026',
    'admincorpo' => 'admin2026',
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
    .ok  { background: #ecfdf5; border: 1px solid #6ee7b7; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
    .err { background: #fef2f2; border: 1px solid #fca5a5; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
    table { border-collapse: collapse; width: 100%; margin-top: .5rem; }
    td, th { border: 1px solid #ddd; padding: .4rem .6rem; text-align: left; }
    th { background: #f3f4f6; }
    .warn { background: #fffbeb; border: 1px solid #fcd34d; padding: 1rem; border-radius: 8px; margin-top: 1.5rem; }
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
