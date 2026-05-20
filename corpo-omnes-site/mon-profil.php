<?php
/**
 * mon-profil.php - Infos compte et mises à jour (connecté uniquement)
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php';

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = 'mon-profil.php';
    header('Location: admin/login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$flashOk = '';
$flashErr = '';

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) {
    header('Location: admin/logout.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $prenom = trim($_POST['prenom'] ?? '');
        $nom    = trim($_POST['nom'] ?? '');
        $emailPerso = trim($_POST['email_perso'] ?? '');
        $programme  = trim($_POST['programme'] ?? '');
        $promotion  = trim($_POST['promotion'] ?? '');

        if (mb_strlen($prenom) < 2) {
            $flashErr = corpo_t('profile.err_prenom');
        } elseif (mb_strlen($nom) < 2) {
            $flashErr = corpo_t('profile.err_nom');
        } elseif ($emailPerso !== '' && !filter_var($emailPerso, FILTER_VALIDATE_EMAIL)) {
            $flashErr = corpo_t('profile.err_email_perso');
        } else {
            $pdo->prepare(
                'UPDATE users SET prenom = ?, nom = ?, email_perso = ?, programme = ?, promotion = ? WHERE id = ?'
            )->execute([
                $prenom,
                $nom,
                $emailPerso === '' ? null : $emailPerso,
                $programme === '' ? null : $programme,
                $promotion === '' ? null : $promotion,
                $userId,
            ]);
            $_SESSION['user_prenom'] = $prenom;
            $_SESSION['user_nom']     = $nom;
            $flashOk = corpo_t('profile.flash_ok');
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
        }
    } elseif ($action === 'change_password') {
        $cur  = $_POST['current_password'] ?? '';
        $new  = $_POST['new_password'] ?? '';
        $conf = $_POST['new_password_confirm'] ?? '';

        if (!password_verify($cur, $user['password_hash'])) {
            $flashErr = corpo_t('profile.err_pwd_bad');
        } elseif (mb_strlen($new) < 8) {
            $flashErr = corpo_t('profile.err_pwd_short');
        } elseif ($new !== $conf) {
            $flashErr = corpo_t('profile.err_pwd_match');
        } else {
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([
                password_hash($new, PASSWORD_DEFAULT),
                $userId,
            ]);
            $flashOk = corpo_t('profile.flash_pwd');
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
        }
    }
}

$title = corpo_t('profile.meta_title');
$page  = 'mon-profil';
require_once __DIR__ . '/includes/header.php';
?>

<main>
  <section class="page-hero">
    <div class="container">
      <nav class="breadcrumb"><a href="index.php"><?= htmlspecialchars(corpo_t('common.breadcrumb_home')) ?></a><span>›</span><span><?= htmlspecialchars(corpo_t('profile.crumb')) ?></span></nav>
      <h1><?= htmlspecialchars(corpo_t('profile.h1')) ?></h1>
      <p class="page-hero__sub"><?= htmlspecialchars(corpo_t('profile.sub')) ?></p>
    </div>
  </section>

  <section class="container profil-layout">
    <?php if ($flashOk): ?>
      <div class="profil-flash profil-flash--ok"><?= htmlspecialchars($flashOk) ?></div>
    <?php endif; ?>
    <?php if ($flashErr): ?>
      <div class="profil-flash profil-flash--err"><?= htmlspecialchars($flashErr) ?></div>
    <?php endif; ?>

    <div class="profil-panel">
      <h2><?= htmlspecialchars(corpo_t('profile.sec_info')) ?></h2>
      <form method="post">
        <input type="hidden" name="action" value="update_profile">

        <div class="profil-field">
          <label for="profil-prenom"><?= htmlspecialchars(corpo_t('profile.lbl_prenom')) ?></label>
          <input type="text" id="profil-prenom" name="prenom" required maxlength="100"
                 value="<?= htmlspecialchars($user['prenom'] ?? '') ?>">
        </div>
        <div class="profil-field">
          <label for="profil-nom"><?= htmlspecialchars(corpo_t('profile.lbl_nom')) ?></label>
          <input type="text" id="profil-nom" name="nom" required maxlength="100"
                 value="<?= htmlspecialchars($user['nom'] ?? '') ?>">
        </div>
        <div class="profil-field">
          <label for="profil-email"><?= htmlspecialchars(corpo_t('profile.lbl_email')) ?></label>
          <input type="email" id="profil-email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" readonly>
          <p class="profil-field__hint"><?= htmlspecialchars(corpo_t('profile.hint_email')) ?></p>
        </div>
        <div class="profil-field">
          <label for="profil-email-perso"><?= htmlspecialchars(corpo_t('profile.lbl_email_perso')) ?></label>
          <input type="email" id="profil-email-perso" name="email_perso" maxlength="255"
                 value="<?= htmlspecialchars($user['email_perso'] ?? '') ?>"
                 placeholder="<?= htmlspecialchars(corpo_t('profile.ph_email_perso')) ?>">
        </div>
        <div class="profil-field">
          <label for="profil-username"><?= htmlspecialchars(corpo_t('profile.lbl_username')) ?></label>
          <input type="text" id="profil-username" value="<?= htmlspecialchars($user['username'] ?? '') ?>" readonly>
        </div>
        <?php if (!empty($user['ecole'])): ?>
        <div class="profil-field">
          <label for="profil-ecole"><?= htmlspecialchars(corpo_t('profile.lbl_school')) ?></label>
          <input type="text" id="profil-ecole" value="<?= htmlspecialchars($user['ecole']) ?>" readonly>
        </div>
        <?php endif; ?>
        <div class="profil-field">
          <label for="profil-programme"><?= htmlspecialchars(corpo_t('profile.lbl_programme')) ?></label>
          <input type="text" id="profil-programme" name="programme" maxlength="100"
                 value="<?= htmlspecialchars($user['programme'] ?? '') ?>"
                 placeholder="<?= htmlspecialchars(corpo_t('profile.ph_programme')) ?>">
        </div>
        <div class="profil-field">
          <label for="profil-promotion"><?= htmlspecialchars(corpo_t('profile.lbl_promo')) ?></label>
          <input type="text" id="profil-promotion" name="promotion" maxlength="20"
                 value="<?= htmlspecialchars($user['promotion'] ?? '') ?>"
                 placeholder="<?= htmlspecialchars(corpo_t('profile.ph_promo')) ?>">
        </div>

        <div class="profil-actions">
          <button type="submit" class="btn btn--primary"><?= htmlspecialchars(corpo_t('profile.btn_save')) ?></button>
        </div>
      </form>
    </div>

    <div class="profil-panel">
      <h2><?= htmlspecialchars(corpo_t('profile.sec_pwd')) ?></h2>
      <form method="post">
        <input type="hidden" name="action" value="change_password">

        <div class="profil-field">
          <label for="pwd-current"><?= htmlspecialchars(corpo_t('profile.pwd_current')) ?></label>
          <input type="password" id="pwd-current" name="current_password" required autocomplete="current-password">
        </div>
        <div class="profil-field">
          <label for="pwd-new"><?= htmlspecialchars(corpo_t('profile.pwd_new')) ?></label>
          <input type="password" id="pwd-new" name="new_password" required minlength="8" autocomplete="new-password">
        </div>
        <div class="profil-field">
          <label for="pwd-new2"><?= htmlspecialchars(corpo_t('profile.pwd_confirm')) ?></label>
          <input type="password" id="pwd-new2" name="new_password_confirm" required minlength="8" autocomplete="new-password">
        </div>

        <div class="profil-actions">
          <button type="submit" class="btn btn--primary"><?= htmlspecialchars(corpo_t('profile.btn_pwd')) ?></button>
        </div>
      </form>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
