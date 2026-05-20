<?php
declare(strict_types=1);
// page de connexion - gestion des tentatives, CSRF et langue

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/i18n.php';

// changement de langue via cookie avant de rediriger
if (isset($_GET['lang'])) {
    $lng = (string)$_GET['lang'];
    if (in_array($lng, CORPO_LANG_ALLOWED, true)) {
        corpo_set_lang_cookie($lng);
    }
    header('Location: login.php');
    exit;
}

if (isLoggedIn()) {
    if (hasAdminPanelAccess()) {
        header('Location: index.php');
    } else {
        header('Location: ../index.php');
    }
    exit;
}

$lang = corpo_current_lang();
$L    = corpo_login_strings($lang);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $error = $L['csrf_invalid'];
    } else {
        $identifier = trim((string)($_POST['email'] ?? ''));
        $password   = (string)($_POST['password'] ?? '');

        if ($identifier === '' || $password === '') {
            $error = $L['empty_fields'];
        } else {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? OR username = ? LIMIT 1');
            $stmt->execute([$identifier, $identifier]);
            $user = $stmt->fetch();

            $passwordOk = $user && password_verify($password, $user['password_hash']);

            if ($passwordOk && ($user['statut'] ?? '') === 'actif') {
                unset($_SESSION['login_fail_count']);
                csrf_rotate();
                session_regenerate_id(true);
                $_SESSION['user_id']         = (int)$user['id'];
                $_SESSION['user_role']       = $user['role'];
                $_SESSION['admin_username']  = $user['email'];
                $_SESSION['user_prenom']     = $user['prenom'] ?? '';
                $_SESSION['user_nom']        = $user['nom'] ?? '';
                $_SESSION['user_login']      = $user['username'] ?? '';

                loadMemberships((int)$user['id'], $pdo);
                $_SESSION['admin_logged_in'] = hasAdminPanelAccess();

                if (isset($_SESSION['redirect_after_login'])) {
                    $redirect = (string)$_SESSION['redirect_after_login'];
                    unset($_SESSION['redirect_after_login']);
                    // Les liens publics (evenement.php, etc.) sont relatifs à la racine du site ;
                    // depuis admin/, il faut remonter d'un cran sinon le navigateur résout admin/evenement.php.
                    if ($redirect !== ''
                        && !preg_match('#^[a-z][a-z0-9+.-]*://#i', $redirect)
                        && ($redirect[0] ?? '') !== '/'
                        && strpos($redirect, '//') !== 0
                        && strncmp($redirect, '../', 3) !== 0
                        && strncmp($redirect, 'admin/', 6) !== 0
                    ) {
                        $redirect = '../' . ltrim($redirect, './');
                    }
                } elseif ($_SESSION['admin_logged_in']) {
                    $redirect = 'index.php';
                } else {
                    $redirect = '../index.php';
                }
                header('Location: ' . $redirect);
                exit;
            }

            if ($passwordOk && ($user['statut'] ?? '') !== 'actif') {
                $statutUser = (string)($user['statut'] ?? '');
                if ($statutUser === 'en_attente') {
                    $error = 'Ton compte n\'est pas encore validé. Clique sur le lien envoyé par mail '
                           . 'pour l\'activer. Le mail peut mettre 5 à 10 minutes à arriver - '
                           . 'pense aussi à vérifier ton dossier Spam / Indésirables.';
                } else {
                    $error = $L['inactive'];
                }
            } else {
                $_SESSION['login_fail_count'] = (int)($_SESSION['login_fail_count'] ?? 0) + 1;
                $n = (int)$_SESSION['login_fail_count'];

                if ($n >= 3) {
                    sleep(5);
                    $_SESSION['login_fail_count'] = 0;
                    $error = $L['blocked_wait'];
                } else {
                    $error = $L['bad_creds'];
                }
            }
        }
    }
}

$failCount = (int)($_SESSION['login_fail_count'] ?? 0);

?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($L['html_title']) ?></title>
  <link rel="stylesheet" href="../css/style.css">
</head>
<body class="login-page-body">
  <div class="login-wrap">
    <div class="login-card">
      <div class="login-lang-bar" role="navigation" aria-label="<?= htmlspecialchars($L['lang_label']) ?>">
        <span class="login-lang-bar__label"><?= htmlspecialchars($L['lang_label']) ?> :</span>
        <a href="login.php?lang=fr" class="login-lang-bar__link<?= $lang === 'fr' ? ' login-lang-bar__link--active' : '' ?>"><?= htmlspecialchars($L['lang_fr']) ?></a>
        <span class="login-lang-bar__sep">|</span>
        <a href="login.php?lang=en" class="login-lang-bar__link<?= $lang === 'en' ? ' login-lang-bar__link--active' : '' ?>"><?= htmlspecialchars($L['lang_en']) ?></a>
      </div>

      <div class="login-card__logo">
        <img src="../images/logo-corpo-omnes.png" alt="">
        <div>
          <div class="login-card__brand">Corpo Omnes Lyon</div>
          <div class="login-card__brand-sub"><?= htmlspecialchars($L['brand_sub']) ?></div>
        </div>
      </div>

      <h1 class="login-card__title"><?= htmlspecialchars($L['title']) ?></h1>
      <p class="login-card__sub"><?= htmlspecialchars($L['subtitle']) ?></p>

      <?php if ($error !== ''): ?>
        <div class="login-error" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if ($failCount > 0 && $error !== $L['blocked_wait']): ?>
        <div class="login-info"><?= htmlspecialchars(sprintf($L['attempts_line'], $failCount)) ?></div>
      <?php endif; ?>

      <form method="post" action="login.php" autocomplete="on">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

        <div class="form-group">
          <label for="email"><?= htmlspecialchars($L['email_label']) ?></label>
          <input type="text" id="email" name="email"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                 autocomplete="username" required autofocus placeholder="<?= htmlspecialchars($L['email_ph']) ?>">
        </div>
        <div class="form-group">
          <label for="password"><?= htmlspecialchars($L['password_label']) ?></label>
          <input type="password" id="password" name="password" autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn btn--primary login-submit"><?= htmlspecialchars($L['submit']) ?></button>
      </form>

      <p class="login-back" style="margin-top:1rem">
        <a href="../forgot-password.php">Mot de passe oublié ?</a>
      </p>
      <p class="login-back">
        <a href="../index.php"><?= htmlspecialchars($L['back_site']) ?></a>
      </p>
    </div>
  </div>
</body>
</html>
