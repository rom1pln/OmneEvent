<?php
/**
 * reset-password.php — Nouveau mot de passe via lien reçu par mail.
 * Page autonome (db.php seulement) pour limiter les erreurs fatales sur hébergement mutualisé.
 */
$resetLog = static function (string $msg): void {
    $file = __DIR__ . '/logs/reset-password.log';
    $dir  = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    @file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
    error_log('[reset-password] ' . $msg);
};

$resetDebug = isset($_GET['debug']) && (string)$_GET['debug'] === '1';

register_shutdown_function(static function () use ($resetDebug, $resetLog): void {
    $e = error_get_last();
    if (!$e || !in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    $resetLog('Fatal: ' . $e['message'] . ' @ ' . $e['file'] . ':' . $e['line']);
    if (headers_sent()) {
        return;
    }
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Erreur</title></head><body style="font-family:system-ui,sans-serif;max-width:32rem;margin:3rem auto;padding:0 1rem">';
    echo '<h1 style="color:#b91c1c">Erreur serveur</h1>';
    echo '<p>La page de réinitialisation ne peut pas s’afficher. ';
    echo '<a href="forgot-password.php">Refaire une demande</a>.</p>';
    if ($resetDebug) {
        echo '<pre style="background:#111;color:#eee;padding:12px;overflow:auto;font-size:12px">';
        echo htmlspecialchars($e['message'] . "\n" . $e['file'] . ':' . $e['line'], ENT_QUOTES, 'UTF-8');
        echo '</pre>';
    }
    echo '</body></html>';
});

try {
    require_once __DIR__ . '/includes/db.php';
} catch (Throwable $e) {
    $resetLog('db.php: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Erreur</title></head><body style="font-family:system-ui,sans-serif;max-width:32rem;margin:3rem auto">';
    echo '<h1>Base de données</h1><p>Connexion impossible. Réessaie plus tard.</p>';
    if ($resetDebug) {
        echo '<pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';
    }
    echo '</body></html>';
    exit;
}

if (!isset($pdo) || !$pdo instanceof PDO) {
    $resetLog('PDO manquant après db.php');
    http_response_code(500);
    exit('Configuration base de données invalide.');
}

/** @return array<string,mixed>|null */
$lookupResetToken = static function (PDO $pdo, string $tokenHash): ?array {
    if (strlen($tokenHash) !== 64) {
        return null;
    }
    if (function_exists('corpo_password_reset_lookup')) {
        return corpo_password_reset_lookup($pdo, $tokenHash);
    }
    $st = $pdo->prepare(
        'SELECT pr.id AS reset_id, pr.user_id, pr.expires_at, pr.used_at,
                u.email, u.prenom, u.nom,
                (pr.expires_at <= NOW()) AS is_expired
         FROM password_resets pr
         INNER JOIN users u ON u.id = pr.user_id
         WHERE pr.token_hash = ?
         LIMIT 1'
    );
    $st->execute([$tokenHash]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
};

$tokenRaw = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$token    = function_exists('corpo_normalize_hex_token')
    ? corpo_normalize_hex_token($tokenRaw)
    : strtolower(preg_replace('/[^a-f0-9]/', '', $tokenRaw) ?? '');

$state = 'form';
$err   = '';
$user  = null;

$tableReady = function_exists('corpo_password_resets_table_ready')
    ? corpo_password_resets_table_ready($pdo)
    : true;

if (!$tableReady) {
    $state = 'setup';
} elseif ($token === '' || strlen($token) !== 64) {
    $state = 'invalid';
} else {
    try {
        $row = $lookupResetToken($pdo, hash('sha256', $token));

        if (!$row) {
            $state = 'invalid';
        } elseif (!empty($row['used_at'])) {
            $state = 'invalid';
        } elseif ((int)($row['is_expired'] ?? 0) === 1) {
            $state = 'expired';
        } else {
            $user = $row;
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
                $pwd  = (string)($_POST['password'] ?? '');
                $conf = (string)($_POST['password_confirm'] ?? '');
                if (strlen($pwd) < 8) {
                    $err = 'Mot de passe trop court (8 caractères min).';
                } elseif ($pwd !== $conf) {
                    $err = 'Les mots de passe ne correspondent pas.';
                } else {
                    $userId = (int)($row['user_id'] ?? 0);
                    $pdo->beginTransaction();
                    try {
                        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                            ->execute([password_hash($pwd, PASSWORD_DEFAULT), $userId]);
                        $pdo->prepare(
                            'UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL'
                        )->execute([$userId]);
                        $pdo->commit();
                        $state = 'ok';
                        $user  = null;
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        $resetLog('Update: ' . $e->getMessage());
                        $state = 'error';
                        $err   = $resetDebug ? $e->getMessage() : 'Mise à jour impossible.';
                    }
                }
            }
        }
    } catch (PDOException $e) {
        $resetLog('PDO: ' . $e->getMessage());
        $msg = $e->getMessage();
        $isMissingTable = (strpos($msg, 'password_resets') !== false)
            && (strpos($msg, "doesn't exist") !== false || strpos($msg, '1146') !== false);
        $isMissingCol = strpos($msg, 'Unknown column') !== false || strpos($msg, '1054') !== false;
        if ($isMissingTable || $isMissingCol) {
            $state = 'setup';
            $err   = 'Migration tbl_password_resets requise.';
        } else {
            $state = 'error';
            $err   = $resetDebug ? $msg : 'Erreur base de données.';
        }
    } catch (Throwable $e) {
        $resetLog('Err: ' . $e->getMessage());
        $state = 'error';
        $err   = $resetDebug ? $e->getMessage() : 'Erreur technique. Réessaie dans quelques minutes.';
    }
}

$pageTitle = 'Réinitialiser le mot de passe';
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
    <div class="login-card register-card" style="max-width:420px">
      <p style="text-align:center;margin:0 0 var(--s4)">
        <a href="index.php"><img src="images/logo-corpo-omnes.png" alt="Corpo Omnes" width="48" height="48"></a>
      </p>

      <?php if ($state === 'ok'): ?>
        <div style="text-align:center">
          <h1 style="margin:0 0 var(--s3)">Mot de passe mis à jour</h1>
          <p style="color:var(--text-muted);margin-bottom:var(--s5)">Tu peux te connecter avec ton nouveau mot de passe.</p>
          <a href="admin/login.php" class="btn btn--primary">Se connecter →</a>
        </div>

      <?php elseif ($state === 'setup'): ?>
        <h1>Configuration requise</h1>
        <p class="sub">La table <code>password_resets</code> est absente. Exécute la migration <strong>tbl_password_resets</strong> (admin → Migrations DB).</p>
        <?php if ($err !== ''): ?><p style="color:#b91c1c;font-size:.9rem"><?= htmlspecialchars($err) ?></p><?php endif; ?>
        <p style="margin-top:var(--s4)"><a href="forgot-password.php" class="link">← Retour</a></p>

      <?php elseif ($state === 'invalid'): ?>
        <h1>Lien invalide</h1>
        <p class="sub">Ce lien n’est plus valide ou a été tronqué par ton client mail.</p>
        <p style="margin-top:var(--s4)"><a href="forgot-password.php" class="btn btn--primary">Refaire une demande</a></p>

      <?php elseif ($state === 'expired'): ?>
        <h1>Lien expiré</h1>
        <p class="sub">Validité : 1 h. Demande un nouveau lien.</p>
        <p style="margin-top:var(--s4)"><a href="forgot-password.php" class="btn btn--primary">Refaire une demande</a></p>

      <?php elseif ($state === 'error'): ?>
        <h1 style="color:#ef4444">Erreur technique</h1>
        <p class="sub"><?= htmlspecialchars($err ?: 'Réessaie dans quelques minutes.') ?></p>
        <p style="margin-top:var(--s4)"><a href="forgot-password.php" class="btn btn--primary">Refaire une demande</a></p>

      <?php else: ?>
        <h1>Nouveau mot de passe</h1>
        <p class="sub">Compte : <strong><?= htmlspecialchars((string)($user['email'] ?? '')) ?></strong></p>
        <?php if ($err !== ''): ?>
          <div class="register-error" style="margin:var(--s3) 0"><p>⚠️ <?= htmlspecialchars($err) ?></p></div>
        <?php endif; ?>
        <form method="post" novalidate>
          <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
          <div class="field" style="margin-bottom:var(--s3)">
            <label>Nouveau mot de passe <span class="req">*</span></label>
            <input type="password" name="password" class="admin-input" required minlength="8" autocomplete="new-password" autofocus>
          </div>
          <div class="field" style="margin-bottom:var(--s4)">
            <label>Confirmer <span class="req">*</span></label>
            <input type="password" name="password_confirm" class="admin-input" required minlength="8" autocomplete="new-password">
          </div>
          <button type="submit" class="btn btn--primary" style="width:100%">Mettre à jour →</button>
        </form>
      <?php endif; ?>

      <p style="text-align:center;margin-top:var(--s4);font-size:.8rem">
        <a href="admin/login.php" class="link">← Connexion</a>
      </p>
    </div>
  </div>
</body>
</html>
