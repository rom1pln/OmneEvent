<?php

require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/i18n.php';
require_once 'includes/mailer.php';
require_once 'includes/email-domains.php';
$page  = 'register';
$title = corpo_t('reg.meta_title');

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$errors  = [];
$success = false;
$post    = $_POST;

$ecoles      = ['ECE','ESCE','HEIP','INSEEC Bachelor','INSEEC BBA','INSEEC BTS','INSEEC GE','INSEEC MSc','Sup de Pub','Autre'];
$ecolesPerso = ['ECE','ESCE','HEIP','INSEEC Bachelor','INSEEC BBA','INSEEC BTS','INSEEC GE','INSEEC MSc','Sup de Pub','Omnes (Administration)','Autre'];
$campuses    = ['Citroën','Citadelle','Les deux'];
$fonctions   = ['Responsable vie étudiante','Chargé(e) de relations écoles','Enseignant(e)','Administration','Direction','Autre'];

$userType = $post['user_type'] ?? 'etudiant';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom    = trim($post['nom']    ?? '');
    $prenom = trim($post['prenom'] ?? '');
    $email  = trim($post['email']  ?? '');
    $mdp        = $post['password']         ?? '';
    $mdpConfirm = $post['password_confirm'] ?? '';

    if (mb_strlen($nom)    < 2) $errors[] = 'Le nom est requis (2 caractères min).';
    if (mb_strlen($prenom) < 2) $errors[] = 'Le prénom est requis.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email invalide.';
    } elseif (!corpo_email_is_valid_for_type($email, $userType)) {

        $allowed = implode(', ', corpo_email_allowed_domains($userType));
        $errors[] = $userType === 'personnel'
            ? "Cet email n'est pas reconnu comme un email du personnel Omnes. Domaines acceptés : $allowed."
            : "Cet email n'est pas reconnu comme un email étudiant Omnes. Utilise ton adresse école (domaines acceptés : $allowed).";
    }
    if (mb_strlen($mdp) < 8)   $errors[] = 'Mot de passe trop court (8 caractères min).';
    if ($mdp !== $mdpConfirm)  $errors[] = 'Les mots de passe ne correspondent pas.';

    $username = strtolower(preg_replace('/[^a-zA-Z0-9._-]/', '', explode('@', $email)[0]));
    if (mb_strlen($username) < 2) $username = strtolower($prenom . '.' . $nom);

    if ($userType === 'etudiant') {
        $ecole      = trim($post['ecole']      ?? '');
        $promotion  = trim($post['promotion']  ?? '');
        $emailPerso = trim($post['email_perso'] ?? '');
        $structType = $post['structure_type']   ?? '';
        $structId   = (int)($post['structure_id'] ?? 0);
        $message    = trim($post['message']    ?? '');

        if (!in_array($ecole, $ecoles)) $errors[] = 'École invalide.';
        if ($emailPerso && !filter_var($emailPerso, FILTER_VALIDATE_EMAIL))
            $errors[] = 'Email personnel invalide.';

        if (empty($errors)) {
            try {
                $hash = password_hash($mdp, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare(
                    "INSERT INTO users
                       (username,email,email_perso,password_hash,nom,prenom,ecole,promotion,role,statut)
                     VALUES (?,?,?,?,?,?,?,?,'user','en_attente')"
                );
                $stmt->execute([
                    $username, $email,
                    $emailPerso ?: null,
                    $hash,
                    $nom, $prenom,
                    $ecole, $promotion ?: null,
                ]);
                $userId = (int)$pdo->lastInsertId();

                if ($structType && $structId > 0) {
                    $pdo->prepare(
                        "INSERT INTO demandes_adhesion (user_id,structure_type,structure_id,message) VALUES (?,?,?,?)"
                    )->execute([$userId, $structType, $structId, $message]);
                }
                corpo_mail_send_verification($pdo, [
                    'id'     => $userId, 'email' => $email,
                    'prenom' => $prenom, 'nom'   => $nom,
                ]);
                $success = true;
            } catch (PDOException $e) {
                $errors[] = str_contains($e->getMessage(), 'Duplicate')
                    ? 'Cet email est déjà utilisé.'
                    : 'Erreur technique. Réessayez plus tard.';
            }
        }

    } else {
        $campus   = trim($post['campus']   ?? '');
        $fonction = trim($post['fonction'] ?? '');
        $ecoleP   = trim($post['ecole_perso'] ?? '');

        $ecoleStored = $ecoleP === 'Omnes (Administration)' ? 'Groupe Omnes' : ($ecoleP ?: 'Groupe Omnes');

        if (empty($errors)) {
            try {
                $hash = password_hash($mdp, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare(
                    "INSERT INTO users
                       (username,email,password_hash,nom,prenom,ecole,promotion,role,statut)
                     VALUES (?,?,?,?,?,?,?,'user','en_attente')"
                );
                $stmt->execute([
                    $username, $email, $hash,
                    $nom, $prenom,
                    $ecoleStored,
                    $fonction ?: null,
                ]);
                $userId = (int)$pdo->lastInsertId();
                corpo_mail_send_verification($pdo, [
                    'id'     => $userId, 'email' => $email,
                    'prenom' => $prenom, 'nom'   => $nom,
                ]);
                $success = true;
            } catch (PDOException $e) {
                $errors[] = str_contains($e->getMessage(), 'Duplicate')
                    ? 'Cet email est déjà utilisé.'
                    : 'Erreur technique. Réessayez plus tard.';
            }
        }
    }
}

require_once 'includes/header.php';
?>

<main class="register-wrap">

<?php if ($success): ?>
  <div class="register-success">
    <div class="check" aria-hidden="true"></div>
    <h2 style="font-size:1.2rem;font-weight:700;margin-bottom:.5rem">Compte créé !</h2>
    <p style="color:var(--text-muted);margin-bottom:1.2rem">
      Un mail de confirmation vient d'être envoyé à <strong><?= htmlspecialchars($post['email'] ?? '') ?></strong>.
      Clique sur le lien dans le mail pour activer ton compte (lien valable 24 h).
      <br><br>
      <strong>⏱ Le mail peut prendre 5 à 10 minutes à arriver.</strong>
      Pense aussi à vérifier ton dossier <em>Spam / Indésirables</em>.
      <?php if (!empty($post['structure_type'])): ?>
        <br><br>Ta demande d'adhésion a été transmise au responsable de la structure.
      <?php endif; ?>
    </p>
    <a href="admin/login.php" class="btn btn--primary">J'ai activé mon compte, me connecter →</a>
  </div>

<?php else: ?>

  <div class="register-tabs">
    <button class="register-tab<?= $userType !== 'personnel' ? ' register-tab--active' : '' ?>"
            onclick="switchTab('etudiant')" type="button">
      Étudiant
    </button>
    <button class="register-tab<?= $userType === 'personnel' ? ' register-tab--active' : '' ?>"
            onclick="switchTab('personnel')" type="button">
      Personnel du groupe
    </button>
  </div>

  <div class="register-card" id="form-etudiant"
       style="<?= $userType === 'personnel' ? 'display:none' : '' ?>">
    <h1>Créer un compte</h1>
    <p class="sub">Rejoins la plateforme Corpo Omnes Lyon pour accéder aux services et structures étudiantes.</p>

    <?php if (!empty($errors) && $userType !== 'personnel'): ?>
      <div class="register-error">
        <?php foreach ($errors as $e): ?><p>⚠️ <?= htmlspecialchars($e) ?></p><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="post" novalidate>
      <input type="hidden" name="user_type" value="etudiant">

            <p class="section-label" style="margin-bottom:var(--s4)">Identité</p>

      <div class="field field-row">
        <div>
          <label>Prénom <span class="req">*</span></label>
          <input type="text" name="prenom"
                 value="<?= $userType !== 'personnel' ? htmlspecialchars($post['prenom'] ?? '') : '' ?>"
                 placeholder="Marie" required autofocus>
        </div>
        <div>
          <label>Nom <span class="req">*</span></label>
          <input type="text" name="nom"
                 value="<?= $userType !== 'personnel' ? htmlspecialchars($post['nom'] ?? '') : '' ?>"
                 placeholder="Dupont" required>
        </div>
      </div>

      <div class="field">
        <label>École <span class="req">*</span></label>
        <select name="ecole" required>
          <option value="">- Choisir -</option>
          <?php foreach ($ecoles as $ec): ?>
            <option value="<?= $ec ?>"<?= ($post['ecole'] ?? '') === $ec && $userType !== 'personnel' ? ' selected' : '' ?>><?= $ec ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field" style="max-width:200px">
        <label>Promotion / Année</label>
        <input type="text" name="promotion"
               value="<?= $userType !== 'personnel' ? htmlspecialchars($post['promotion'] ?? '') : '' ?>"
               placeholder="ex: B3 2027">
      </div>

      <hr class="divider">

            <p class="section-label" style="margin-bottom:var(--s4)">Contact</p>

      <div class="field">
        <label>Email école <span class="req">*</span></label>
        <p class="section-hint" style="margin-bottom:.4rem">
          Utilisé comme identifiant de connexion. Doit être ton email <strong>étudiant Omnes</strong> :
          <span style="color:var(--purple)"><?= htmlspecialchars(implode(', ', corpo_email_allowed_domains('etudiant'))) ?></span>.
        </p>
        <input type="email" name="email"
               value="<?= $userType !== 'personnel' ? htmlspecialchars($post['email'] ?? '') : '' ?>"
               placeholder="prenom.nom@edu.ece.fr" required>
      </div>

      <div class="field">
        <label>Email personnel <span style="font-weight:400;color:var(--text-muted)">(optionnel)</span></label>
        <input type="email" name="email_perso"
               value="<?= $userType !== 'personnel' ? htmlspecialchars($post['email_perso'] ?? '') : '' ?>"
               placeholder="prenom@gmail.com">
      </div>

      <hr class="divider">

            <p class="section-label" style="margin-bottom:var(--s4)">Mot de passe</p>

      <div class="field field-row">
        <div>
          <label>Mot de passe <span class="req">*</span></label>
          <input type="password" name="password" placeholder="8 caractères min" required>
        </div>
        <div>
          <label>Confirmer <span class="req">*</span></label>
          <input type="password" name="password_confirm" placeholder="Retaper" required>
        </div>
      </div>

      <button type="submit" class="btn btn--primary" style="width:100%;margin-top:var(--s5)">
        Créer mon compte →
      </button>

      <p style="text-align:center;margin-top:1rem;font-size:.8rem;color:var(--text-muted)">
        Déjà un compte ? <a href="admin/login.php" class="link">Se connecter</a>
      </p>
    </form>
  </div>

  <div class="register-card" id="form-personnel"
       style="<?= $userType !== 'personnel' ? 'display:none' : '' ?>">
    <h1>Créer un compte</h1>
    <p class="sub">Accès réservé au personnel administratif et enseignant du Groupe Omnes.</p>

    <?php if (!empty($errors) && $userType === 'personnel'): ?>
      <div class="register-error">
        <?php foreach ($errors as $e): ?><p>⚠️ <?= htmlspecialchars($e) ?></p><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="post" novalidate>
      <input type="hidden" name="user_type" value="personnel">

            <p class="section-label" style="margin-bottom:var(--s4)">Identité</p>

      <div class="field field-row">
        <div>
          <label>Prénom <span class="req">*</span></label>
          <input type="text" name="prenom"
                 value="<?= $userType === 'personnel' ? htmlspecialchars($post['prenom'] ?? '') : '' ?>"
                 placeholder="Marie" required>
        </div>
        <div>
          <label>Nom <span class="req">*</span></label>
          <input type="text" name="nom"
                 value="<?= $userType === 'personnel' ? htmlspecialchars($post['nom'] ?? '') : '' ?>"
                 placeholder="Dupont" required>
        </div>
      </div>

      <div class="field field-row">
        <div>
          <label>École / Structure <span class="req">*</span></label>
          <select name="ecole_perso" required>
            <option value="">- Choisir -</option>
            <?php foreach ($ecolesPerso as $ep): ?>
              <option value="<?= htmlspecialchars($ep) ?>"<?= ($post['ecole_perso'] ?? '') === $ep ? ' selected' : '' ?>><?= htmlspecialchars($ep) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Fonction / Poste</label>
          <select name="fonction">
            <option value="">- Choisir -</option>
            <?php foreach ($fonctions as $f): ?>
              <option value="<?= htmlspecialchars($f) ?>"<?= ($post['fonction'] ?? '') === $f ? ' selected' : '' ?>><?= $f ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="field" style="max-width:200px">
        <label>Campus</label>
        <select name="campus">
          <option value="">- Choisir -</option>
          <?php foreach ($campuses as $c): ?>
            <option value="<?= $c ?>"<?= ($post['campus'] ?? '') === $c ? ' selected' : '' ?>><?= $c ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <hr class="divider">

            <p class="section-label" style="margin-bottom:var(--s4)">Contact professionnel</p>

      <div class="field">
        <label>Email professionnel <span class="req">*</span></label>
        <p class="section-hint" style="margin-bottom:.4rem">
          Utilisé comme identifiant de connexion. Doit être ton email <strong>professionnel Omnes</strong> :
          <span style="color:var(--purple)"><?= htmlspecialchars(implode(', ', corpo_email_allowed_domains('personnel'))) ?></span>.
        </p>
        <input type="email" name="email"
               value="<?= $userType === 'personnel' ? htmlspecialchars($post['email'] ?? '') : '' ?>"
               placeholder="prenom.nom@omneseducation.com" required>
      </div>

      <hr class="divider">

            <p class="section-label" style="margin-bottom:var(--s4)">Mot de passe</p>

      <div class="field field-row">
        <div>
          <label>Mot de passe <span class="req">*</span></label>
          <input type="password" name="password" placeholder="8 caractères min" required>
        </div>
        <div>
          <label>Confirmer <span class="req">*</span></label>
          <input type="password" name="password_confirm" placeholder="Retaper" required>
        </div>
      </div>

      <button type="submit" class="btn btn--primary" style="width:100%;margin-top:var(--s5)">
        Créer mon compte →
      </button>

      <p style="text-align:center;margin-top:1rem;font-size:.8rem;color:var(--text-muted)">
        Déjà un compte ? <a href="admin/login.php" class="link">Se connecter</a>
      </p>
    </form>
  </div>

<?php endif; ?>
</main>

<style>

.register-tabs {
  display: flex;
  justify-content: center;
  gap: var(--s2);
  margin: var(--s8) auto var(--s4);
  max-width: 500px;
}
.register-tab {
  flex: 1;
  padding: .6rem var(--s5);
  border-radius: var(--r-md);
  font-size: .85rem;
  font-weight: 600;
  cursor: pointer;
  border: 1px solid var(--border);
  background: var(--surface);
  color: var(--text-muted);
  transition: all var(--ease);
}
.register-tab--active,
.register-tab:hover {
  background: var(--purple);
  border-color: var(--purple);
  color: #fff;
}

.req { color: #ef4444; margin-left: 2px; }

.register-card select option,
.register-card select optgroup { background: #0D001F; color: #fff; }
</style>

<script>

function switchTab(type) {
  document.getElementById('form-etudiant').style.display  = type === 'etudiant'  ? '' : 'none';
  document.getElementById('form-personnel').style.display = type === 'personnel' ? '' : 'none';
  document.querySelectorAll('.register-tab').forEach((btn, i) => {
    btn.classList.toggle('register-tab--active', (i === 0) === (type === 'etudiant'));
  });
}
</script>

<?php require_once 'includes/footer.php'; ?>
