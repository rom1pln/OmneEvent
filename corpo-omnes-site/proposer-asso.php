<?php

$title = 'Proposer une association';
$page  = 'proposer-asso';
require_once 'includes/db.php';
require_once 'includes/auth.php';

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = 'proposer-asso.php';
    header('Location: admin/login.php');
    exit;
}

$errors  = [];
$success = false;
$post    = $_POST;

$ecoles    = ['ECE','ESCE','HEIP','INSEEC Bachelor','INSEEC BBA','INSEEC BTS','INSEEC GE','INSEEC MSc','Sup de Pub','Toutes (inter-écoles)'];
$types     = ['Association','BDE','BDS','Fédération','Junior Entreprise','Autre'];
$campuses  = ['Citroën','Citadelle','Les deux'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomAsso    = trim($post['nom_asso']     ?? '');
    $typeAsso   = trim($post['type_asso']    ?? '');
    $ecoleAsso  = trim($post['ecole_asso']   ?? '');
    $campusAsso = trim($post['campus_asso']  ?? '');
    $description= trim($post['description']  ?? '');
    $motivation = trim($post['motivation']   ?? '');
    $contactNom = trim($post['contact_nom']  ?? '');
    $contactMail= trim($post['contact_mail'] ?? '');

    if (mb_strlen($nomAsso) < 2)       $errors[] = 'Le nom de l\'association est requis (2 caractères min).';
    if (!in_array($typeAsso, $types))   $errors[] = 'Type de structure invalide.';
    if (!in_array($ecoleAsso, $ecoles)) $errors[] = 'École invalide.';
    if (!in_array($campusAsso, $campuses)) $errors[] = 'Campus invalide.';
    if (mb_strlen($description) < 20)  $errors[] = 'La description doit faire au moins 20 caractères.';
    if (mb_strlen($motivation) < 20)   $errors[] = 'La motivation doit faire au moins 20 caractères.';
    if ($contactMail && !filter_var($contactMail, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Email de contact invalide.';

    if (empty($errors)) {
        try {
            $userId = (int)($_SESSION['user_id'] ?? 0);

            $payload = json_encode([
                'nom'         => $nomAsso,
                'type'        => $typeAsso,
                'ecole'       => $ecoleAsso,
                'campus'      => $campusAsso,
                'description' => $description,
                'motivation'  => $motivation,
                'contact_nom' => $contactNom,
                'contact_mail'=> $contactMail,
            ], JSON_UNESCAPED_UNICODE);

            $stmt = $pdo->prepare(
                "INSERT INTO demandes_validation
                   (user_id, type, structure_type, statut, payload, created_at)
                 VALUES (?, 'nouvelle_asso', 'asso', 'en_attente', ?, NOW())"
            );
            $stmt->execute([$userId, $payload]);
            $success = true;
        } catch (PDOException $e) {
            $errors[] = 'Erreur technique. Réessayez plus tard.';
        }
    }
}

require_once 'includes/header.php';
?>

<main>
  <section class="page-hero">
    <div class="container">
      <nav class="breadcrumb">
        <a href="index.php">Accueil</a><span>›</span>
        <a href="associations.php">Associations</a><span>›</span>
        <span>Proposer une association</span>
      </nav>
      <h1>Proposer une association</h1>
      <p class="page-hero__sub">
        Tu as un projet associatif ? Soumets-le à la Corpo Omnes Lyon pour validation.
      </p>
    </div>
  </section>

  <section class="section">
    <div class="container" style="max-width:680px">

      <?php if ($success): ?>

        <div class="pa-success">
          <div class="pa-success__icon" aria-hidden="true">✅</div>
          <h2>Proposition envoyée !</h2>
          <p>Ton projet a bien été transmis à la Corpo Omnes Lyon. Tu recevras une réponse prochainement.</p>
          <div style="display:flex;gap:var(--s3);justify-content:center;margin-top:var(--s6)">
            <a href="associations.php" class="btn btn--secondary">Voir les associations</a>
            <a href="index.php" class="btn btn--primary">Retour à l'accueil</a>
          </div>
        </div>

      <?php else: ?>

        <?php if (!empty($errors)): ?>
          <div class="pa-errors">
            <?php foreach ($errors as $e): ?>
              <p>⚠️ <?= htmlspecialchars($e) ?></p>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div class="pa-card">
          <form method="post" novalidate>

            <p class="pa-section-label">L'association</p>

            <div class="pa-field">
              <label for="nom_asso">Nom de l'association <span class="pa-req">*</span></label>
              <input type="text" id="nom_asso" name="nom_asso"
                     value="<?= htmlspecialchars($post['nom_asso'] ?? '') ?>"
                     placeholder="ex : Club Photo ECE, BDE ESCE…" required>
            </div>

            <div class="pa-row">
              <div class="pa-field">
                <label for="type_asso">Type de structure <span class="pa-req">*</span></label>
                <select id="type_asso" name="type_asso" required>
                  <option value="">- Choisir -</option>
                  <?php foreach ($types as $t): ?>
                    <option value="<?= $t ?>"<?= ($post['type_asso'] ?? '') === $t ? ' selected' : '' ?>><?= $t ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="pa-field">
                <label for="ecole_asso">École concernée <span class="pa-req">*</span></label>
                <select id="ecole_asso" name="ecole_asso" required>
                  <option value="">- Choisir -</option>
                  <?php foreach ($ecoles as $ec): ?>
                    <option value="<?= $ec ?>"<?= ($post['ecole_asso'] ?? '') === $ec ? ' selected' : '' ?>><?= $ec ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="pa-field" style="max-width:240px">
              <label for="campus_asso">Campus <span class="pa-req">*</span></label>
              <select id="campus_asso" name="campus_asso" required>
                <option value="">- Choisir -</option>
                <?php foreach ($campuses as $c): ?>
                  <option value="<?= $c ?>"<?= ($post['campus_asso'] ?? '') === $c ? ' selected' : '' ?>><?= $c ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="pa-field">
              <label for="description">Description du projet <span class="pa-req">*</span></label>
              <p class="pa-hint">Que fait cette association ? Quel est son rôle, ses activités ?</p>
              <textarea id="description" name="description" rows="4" required
                        placeholder="Décris l'association en quelques phrases…"><?= htmlspecialchars($post['description'] ?? '') ?></textarea>
            </div>

            <div class="pa-field">
              <label for="motivation">Motivation & justification <span class="pa-req">*</span></label>
              <p class="pa-hint">Pourquoi cette association est-elle utile aux étudiants ? Qu'est-ce qui manque actuellement ?</p>
              <textarea id="motivation" name="motivation" rows="4" required
                        placeholder="Explique pourquoi ce projet mérite d'exister…"><?= htmlspecialchars($post['motivation'] ?? '') ?></textarea>
            </div>

            <p class="pa-section-label" style="margin-top:var(--s6)">Porteur du projet</p>

            <div class="pa-row">
              <div class="pa-field">
                <label for="contact_nom">Nom complet</label>
                <input type="text" id="contact_nom" name="contact_nom"
                       value="<?= htmlspecialchars($post['contact_nom'] ?? '') ?>"
                       placeholder="Prénom Nom">
              </div>
              <div class="pa-field">
                <label for="contact_mail">Email de contact</label>
                <input type="email" id="contact_mail" name="contact_mail"
                       value="<?= htmlspecialchars($post['contact_mail'] ?? '') ?>"
                       placeholder="prenom.nom@ecole.fr">
              </div>
            </div>

            <button type="submit" class="btn btn--primary" style="width:100%;margin-top:var(--s6)">
              Soumettre la proposition →
            </button>

            <p style="text-align:center;margin-top:var(--s4);font-size:.78rem;color:var(--text-muted)">
              Ta proposition sera examinée par la Corpo Omnes Lyon et tu seras contacté(e) par email.
            </p>
          </form>
        </div>

      <?php endif; ?>

    </div>
  </section>
</main>

<style>

.pa-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--r-xl);
  padding: var(--s8);
}

.pa-success {
  background: var(--surface);
  border: 1px solid rgba(34,197,94,.3);
  border-radius: var(--r-xl);
  padding: var(--s10) var(--s8);
  text-align: center;
}
.pa-success__icon { font-size: 2.5rem; margin-bottom: var(--s4); }
.pa-success h2 { font-size: 1.4rem; font-weight: 700; margin-bottom: var(--s3); }
.pa-success p { color: var(--text-muted); }

.pa-errors {
  background: rgba(239,68,68,.12);
  border: 1px solid rgba(239,68,68,.3);
  border-radius: var(--r-md);
  padding: var(--s4) var(--s5);
  margin-bottom: var(--s5);
  font-size: .85rem;
  color: #fca5a5;
}
.pa-errors p { margin: .2rem 0; }

.pa-section-label {
  font-size: .68rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: .1em; color: var(--blue-light);
  margin-bottom: var(--s4);
}

.pa-field { margin-bottom: var(--s5); }
.pa-field label {
  display: block;
  font-size: .75rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: .07em; color: var(--blue-light);
  margin-bottom: var(--s2);
}
.pa-hint {
  font-size: .75rem; color: var(--text-muted);
  margin-bottom: var(--s2);
}
.pa-field input[type=text],
.pa-field input[type=email],
.pa-field select,
.pa-field textarea {
  width: 100%;
  background: rgba(255,255,255,.04);
  border: 1px solid var(--border);
  border-radius: var(--r-md);
  padding: .6rem var(--s4);
  color: #fff; font-size: .9rem; font-family: inherit;
  outline: none;
  transition: border-color var(--ease);
  box-sizing: border-box;
}
.pa-field input:focus,
.pa-field select:focus,
.pa-field textarea:focus { border-color: var(--purple); }
.pa-field textarea { resize: vertical; min-height: 90px; }
.pa-field select option,
.pa-field select optgroup { background: #0D001F; color: #fff; }

.pa-row {
  display: flex;
  gap: var(--s4);
  flex-wrap: wrap;
}
.pa-row .pa-field { flex: 1; min-width: 180px; }

.pa-req { color: #ef4444; margin-left: 2px; }
</style>

<?php require_once 'includes/footer.php'; ?>
