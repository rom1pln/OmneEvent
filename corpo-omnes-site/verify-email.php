<?php
/**
 * verify-email.php — Activation de compte via lien reçu par mail.
 * Page autonome (db.php seulement) pour limiter les erreurs fatales sur hébergement mutualisé.
 */
$verifyLog = static function (string $msg): void {
    $file = __DIR__ . '/logs/verify-email.log';
    $dir  = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    @file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
    error_log('[verify-email] ' . $msg);
};

$verifyDebug = isset($_GET['debug']) && (string)$_GET['debug'] === '1';

register_shutdown_function(static function () use ($verifyDebug, $verifyLog): void {
    $e = error_get_last();
    if (!$e || !in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    $verifyLog('Fatal: ' . $e['message'] . ' @ ' . $e['file'] . ':' . $e['line']);
    if (headers_sent()) {
        return;
    }
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Erreur</title></head><body style="font-family:system-ui,sans-serif;max-width:32rem;margin:3rem auto;padding:0 1rem">';
    echo '<h1 style="color:#b91c1c">Erreur serveur</h1>';
    echo '<p>La page d’activation ne peut pas s’afficher. ';
    echo '<a href="register.php">Créer un compte</a> ou réessaie plus tard.</p>';
    if ($verifyDebug) {
        echo '<pre style="background:#111;color:#eee;padding:12px;overflow:auto;font-size:12px">';
        echo htmlspecialchars($e['message'] . "\n" . $e['file'] . ':' . $e['line'], ENT_QUOTES, 'UTF-8');
        echo '</pre>';
    }
    echo '</body></html>';
});

try {
    require_once __DIR__ . '/includes/db.php';
} catch (Throwable $e) {
    $verifyLog('db.php: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Erreur</title></head><body style="font-family:system-ui,sans-serif;max-width:32rem;margin:3rem auto">';
    echo '<h1>Base de données</h1><p>Connexion impossible. Réessaie plus tard.</p>';
    if ($verifyDebug) {
        echo '<pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';
    }
    echo '</body></html>';
    exit;
}

if (!isset($pdo) || !$pdo instanceof PDO) {
    $verifyLog('PDO manquant après db.php');
    http_response_code(500);
    exit('Configuration base de données invalide.');
}

/** @return array<string,mixed>|null */
$lookupVerificationToken = static function (PDO $pdo, string $tokenHash): ?array {
    if (strlen($tokenHash) !== 64) {
        return null;
    }
    if (function_exists('corpo_email_verification_lookup')) {
        return corpo_email_verification_lookup($pdo, $tokenHash);
    }
    $st = $pdo->prepare(
        'SELECT ev.id AS verification_id, ev.user_id, ev.expires_at, ev.used_at,
                u.statut, u.email_verified_at, u.email, u.prenom,
                (ev.expires_at <= NOW()) AS is_expired
         FROM email_verifications ev
         INNER JOIN users u ON u.id = ev.user_id
         WHERE ev.token_hash = ?
         LIMIT 1'
    );
    $st->execute([$tokenHash]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
};

$tokenRaw = (string)($_GET['token'] ?? '');
$token    = function_exists('corpo_normalize_hex_token')
    ? corpo_normalize_hex_token($tokenRaw)
    : strtolower(preg_replace('/[^a-f0-9]/', '', $tokenRaw) ?? '');

$state = 'invalid';
$err   = '';

$tableReady = function_exists('corpo_email_verifications_table_ready')
    ? corpo_email_verifications_table_ready($pdo)
    : true;

if (!$tableReady) {
    $state = 'setup';
} elseif ($token === '' || strlen($token) !== 64) {
    $state = 'invalid';
} else {
    try {
        $row = $lookupVerificationToken($pdo, hash('sha256', $token));

        if (!$row) {
            $state = 'invalid';
        } elseif (!empty($row['used_at']) || !empty($row['email_verified_at'])) {
            $state = 'already';
        } elseif ((int)($row['is_expired'] ?? 0) === 1) {
            $state = 'expired';
        } else {
            $pdo->beginTransaction();
            try {
                $pdo->prepare(
                    "UPDATE users SET statut = 'actif', email_verified_at = NOW() WHERE id = ?"
                )->execute([(int)$row['user_id']]);
                $pdo->prepare(
                    'UPDATE email_verifications SET used_at = NOW() WHERE id = ?'
                )->execute([(int)$row['verification_id']]);
                $pdo->commit();
                $state = 'ok';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $verifyLog('Update: ' . $e->getMessage());
                $state = 'error';
                $err   = $verifyDebug ? $e->getMessage() : 'Activation impossible.';
            }
        }
    } catch (PDOException $e) {
        $verifyLog('PDO: ' . $e->getMessage());
        $msg = $e->getMessage();
        $isMissingTable = (strpos($msg, 'email_verifications') !== false)
            && (strpos($msg, "doesn't exist") !== false || strpos($msg, '1146') !== false);
        $isMissingCol = strpos($msg, 'Unknown column') !== false
            || strpos($msg, '1054') !== false
            || strpos($msg, 'email_verified_at') !== false;
        if ($isMissingTable || $isMissingCol) {
            $state = 'setup';
            $err   = 'Migration email_verifications / email_verified_at requise.';
        } else {
            $state = 'error';
            $err   = $verifyDebug ? $msg : 'Erreur base de données.';
        }
    } catch (Throwable $e) {
        $verifyLog('Err: ' . $e->getMessage());
        $state = 'error';
        $err   = $verifyDebug ? $e->getMessage() : 'Erreur technique. Réessaie dans quelques minutes.';
    }
}

$pageTitle = 'Confirmation de l\'email';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> — Corpo Omnes Lyon</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body class="login-page-body">
  <div class="login-wrap">
    <div class="login-card register-card verify-card<?= $state === 'ok' ? ' register-success' : '' ?>" style="max-width:420px">

      <p style="text-align:center;margin:0 0 var(--s4)">
        <a href="index.php"><img src="images/logo-corpo-omnes.png" alt="Corpo Omnes" width="48" height="48"></a>
      </p>

      <?php if ($state === 'ok'): ?>
        <div class="check" aria-hidden="true"></div>
        <h1 class="verify-card__title">Compte activé !</h1>
        <p class="verify-card__text">
          Ton adresse email est confirmée. Tu peux maintenant te connecter.
        </p>
        <a href="admin/login.php" class="btn btn--primary btn--sm verify-card__cta">Se connecter →</a>

      <?php elseif ($state === 'already'): ?>
        <h1 class="verify-card__title">Compte déjà activé</h1>
        <p class="verify-card__text">
          Ce lien a déjà été utilisé. Tu peux te connecter directement.
        </p>
        <a href="admin/login.php" class="btn btn--primary btn--sm verify-card__cta">Se connecter →</a>

      <?php elseif ($state === 'expired'): ?>
        <h1 class="verify-card__title">Lien expiré</h1>
        <p class="verify-card__text">
          Le lien d'activation a expiré (validité : 24 h). Recrée un compte ou contacte
          la Corpo pour qu'on te renvoie un nouveau lien.
        </p>
        <a href="register.php" class="btn btn--primary btn--sm verify-card__cta">Créer un compte</a>

      <?php elseif ($state === 'setup'): ?>
        <h1>Configuration requise</h1>
        <p class="verify-card__text">
          La table <code>email_verifications</code> ou la colonne <code>email_verified_at</code> est absente.
          Exécute les migrations <strong>tbl_email_verifications</strong> et <strong>users_email_verified_at</strong>
          (admin → Migrations DB).
        </p>
        <?php if ($err !== ''): ?><p style="color:#b91c1c;font-size:.9rem"><?= htmlspecialchars($err) ?></p><?php endif; ?>

      <?php elseif ($state === 'error'): ?>
        <h1 class="verify-card__title" style="color:#ef4444">Erreur technique</h1>
        <p class="verify-card__text">
          <?= htmlspecialchars($err ?: 'Une erreur est survenue. Réessaie dans quelques minutes.') ?>
        </p>
        <a href="index.php" class="btn btn--sm verify-card__cta">Retour à l'accueil</a>

      <?php else: ?>
        <h1 class="verify-card__title">Lien invalide</h1>
        <p class="verify-card__text">
          Le lien que tu as ouvert n'est pas reconnu. Vérifie qu'il est bien complet
          (parfois les mails coupent les URL).
        </p>
        <a href="register.php" class="btn btn--primary btn--sm verify-card__cta">Créer un compte</a>
      <?php endif; ?>

      <p style="text-align:center;margin-top:var(--s4);font-size:.8rem">
        <a href="admin/login.php" class="link">← Connexion</a>
      </p>
    </div>
  </div>
</body>
</html>
