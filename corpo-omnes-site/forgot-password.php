<?php
// reset mot de passe - envoie un lien si l'email existe (même message dans tous les cas pour pas leaker)
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/mailer.php';

$title = 'Mot de passe oublié';
$page  = 'forgot-password';

$sent = false;
$err  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Adresse email invalide.';
    } else {
        try {
            $st = $pdo->prepare("SELECT id, email, prenom, nom FROM users WHERE email = ? LIMIT 1");
            $st->execute([$email]);
            $user = $st->fetch();
            if ($user) {
                // Invalide les anciens tokens non utilisés du même user
                $pdo->prepare(
                    "UPDATE password_resets SET used_at = NOW()
                     WHERE user_id = ? AND used_at IS NULL"
                )->execute([(int)$user['id']]);
                corpo_mail_send_password_reset($pdo, $user);
            } else {
                corpo_mail_log("[reset] demande pour email inconnu : $email");
            }
            $sent = true;
        } catch (Throwable $e) {
            $err = 'Erreur technique. Réessayez dans quelques minutes.';
            corpo_mail_log('[reset ERR] ' . $e->getMessage());
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<main class="register-wrap">
  <div class="register-card">
    <h1>Mot de passe oublié</h1>
    <p class="sub">Saisis l'adresse email de ton compte. Si elle est connue, tu recevras un lien pour choisir un nouveau mot de passe (valable 1 heure).</p>

    <?php if ($sent): ?>
      <div class="register-success" style="text-align:left;margin-top:var(--s4)">
        <strong>Mail envoyé !</strong>
        <p style="color:var(--text-muted);margin-top:.4rem">
          Si un compte correspond à cette adresse, tu vas recevoir un mail avec un lien
          pour choisir un nouveau mot de passe (valable 1 heure).
        </p>
        <p style="color:var(--text-muted);margin-top:.4rem">
          <strong>⏱ Le mail peut prendre 5 à 10 minutes à arriver.</strong>
          N'oublie pas de regarder dans tes <em>Spams / Indésirables</em>.
        </p>
        <p style="margin-top:var(--s4)">
          <a href="admin/login.php" class="link">← Retour à la connexion</a>
        </p>
      </div>
    <?php else: ?>
      <?php if ($err !== ''): ?>
        <div class="register-error"><p>⚠️ <?= htmlspecialchars($err) ?></p></div>
      <?php endif; ?>
      <form method="post" novalidate>
        <div class="field">
          <label>Email du compte <span class="req">*</span></label>
          <input type="email" name="email" required autofocus placeholder="prenom.nom@ecole.fr"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <button type="submit" class="btn btn--primary" style="width:100%;margin-top:var(--s4)">
          Envoyer le lien →
        </button>
        <p style="text-align:center;margin-top:1rem;font-size:.8rem;color:var(--text-muted)">
          <a href="admin/login.php" class="link">← Retour à la connexion</a>
        </p>
      </form>
    <?php endif; ?>
  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
