<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/db.php';

$title   = corpo_t('dp.meta_title');
$page    = 'partenaires';
$errors  = [];
$success = isset($_GET['sent']) && $_GET['sent'] === '1';
$post    = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_contact  = trim($post['nom_contact'] ?? '');
    $email        = trim($post['email'] ?? '');
    $organisation = trim($post['organisation'] ?? '');
    $telephone    = trim($post['telephone'] ?? '');
    $type_offre   = trim($post['type_offre'] ?? '');
    $message      = trim($post['message'] ?? '');

    if ($nom_contact === '') {
        $errors[] = corpo_t('dp.err_name');
    }
    if ($email === '') {
        $errors[] = corpo_t('dp.err_email');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = corpo_t('dp.err_email_invalid');
    }
    if ($organisation === '') {
        $errors[] = corpo_t('dp.err_org');
    }
    if ($type_offre === '') {
        $errors[] = corpo_t('dp.err_type');
    }
    if ($message === '') {
        $errors[] = corpo_t('dp.err_message');
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            "INSERT INTO demandes_partenariat (nom_contact, email, organisation, telephone, type_offre, message)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$nom_contact, $email, $organisation, $telephone, $type_offre, $message]);
        header('Location: demande-partenariat.php?sent=1');
        exit;
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<main>
  <section class="page-hero">
    <div class="container">
      <nav class="breadcrumb" aria-label="<?= htmlspecialchars(corpo_t('pt.breadcrumb_aria')) ?>">
        <a href="index.php"><?= htmlspecialchars(corpo_t('common.breadcrumb_home')) ?></a>
        <span aria-hidden="true">›</span>
        <a href="partenaires.php"><?= htmlspecialchars(corpo_t('pt.crumb')) ?></a>
        <span aria-hidden="true">›</span>
        <span><?= htmlspecialchars(corpo_t('dp.crumb')) ?></span>
      </nav>
      <span class="section-label"><?= htmlspecialchars(corpo_t('dp.eyebrow')) ?></span>
      <h1><?= htmlspecialchars(corpo_t('dp.hero_h1')) ?></h1>
      <p class="page-hero__sub"><?= htmlspecialchars(corpo_t('dp.hero_sub')) ?></p>
    </div>
  </section>

  <section class="section section--alt">
    <div class="container dp-layout">
      <div class="dp-stats">
        <div class="dp-stat"><span class="dp-stat__num">6 000</span><span class="dp-stat__label"><?= htmlspecialchars(corpo_t('pt.stat_students')) ?></span></div>
        <div class="dp-stat"><span class="dp-stat__num">31+</span><span class="dp-stat__label"><?= htmlspecialchars(corpo_t('dp.stat_assos')) ?></span></div>
        <div class="dp-stat"><span class="dp-stat__num">2</span><span class="dp-stat__label"><?= htmlspecialchars(corpo_t('pt.stat_campus')) ?></span></div>
      </div>

      <div class="dp-grid">
        <div class="pa-card dp-form-wrap">
          <h2 class="dp-form-title"><?= htmlspecialchars(corpo_t('dp.form_title')) ?></h2>
          <p class="dp-form-lead"><?= htmlspecialchars(corpo_t('dp.form_lead')) ?></p>

          <?php if ($success): ?>
            <div class="pa-success">
              <div class="pa-success__icon" aria-hidden="true">✓</div>
              <h3><?= htmlspecialchars(corpo_t('dp.success_title')) ?></h3>
              <p><?= htmlspecialchars(corpo_t('dp.success_text')) ?></p>
              <div class="dp-success-actions">
                <a href="partenaires.php" class="btn btn--ghost"><?= htmlspecialchars(corpo_t('pt.crumb')) ?></a>
                <a href="index.php" class="btn btn--primary"><?= htmlspecialchars(corpo_t('common.breadcrumb_home')) ?></a>
              </div>
            </div>
          <?php else: ?>

            <?php if (!empty($errors)): ?>
              <div class="pa-errors">
                <?php foreach ($errors as $err): ?>
                  <p><?= htmlspecialchars($err) ?></p>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <form method="post" class="dp-form" novalidate>
              <p class="pa-section-label"><?= htmlspecialchars(corpo_t('dp.sec_org')) ?></p>

              <div class="pa-row">
                <div class="pa-field">
                  <label for="organisation"><?= htmlspecialchars(corpo_t('dp.label_org')) ?> <span class="pa-req">*</span></label>
                  <input type="text" id="organisation" name="organisation" required
                         value="<?= htmlspecialchars($post['organisation'] ?? '') ?>"
                         placeholder="<?= htmlspecialchars(corpo_t('dp.ph_org')) ?>">
                </div>
                <div class="pa-field">
                  <label for="type_offre"><?= htmlspecialchars(corpo_t('dp.label_type')) ?> <span class="pa-req">*</span></label>
                  <select id="type_offre" name="type_offre" required>
                    <option value=""><?= htmlspecialchars(corpo_t('dp.ph_type')) ?></option>
                    <option value="remise"<?= ($post['type_offre'] ?? '') === 'remise' ? ' selected' : '' ?>><?= htmlspecialchars(corpo_t('dp.type_remise')) ?></option>
                    <option value="evenement"<?= ($post['type_offre'] ?? '') === 'evenement' ? ' selected' : '' ?>><?= htmlspecialchars(corpo_t('dp.type_event')) ?></option>
                    <option value="conference"<?= ($post['type_offre'] ?? '') === 'conference' ? ' selected' : '' ?>><?= htmlspecialchars(corpo_t('dp.type_talk')) ?></option>
                    <option value="recrutement"<?= ($post['type_offre'] ?? '') === 'recrutement' ? ' selected' : '' ?>><?= htmlspecialchars(corpo_t('dp.type_jobs')) ?></option>
                    <option value="autre"<?= ($post['type_offre'] ?? '') === 'autre' ? ' selected' : '' ?>><?= htmlspecialchars(corpo_t('dp.type_other')) ?></option>
                  </select>
                </div>
              </div>

              <p class="pa-section-label"><?= htmlspecialchars(corpo_t('dp.sec_contact')) ?></p>

              <div class="pa-row">
                <div class="pa-field">
                  <label for="nom_contact"><?= htmlspecialchars(corpo_t('dp.label_name')) ?> <span class="pa-req">*</span></label>
                  <input type="text" id="nom_contact" name="nom_contact" required
                         value="<?= htmlspecialchars($post['nom_contact'] ?? '') ?>"
                         placeholder="<?= htmlspecialchars(corpo_t('dp.ph_name')) ?>">
                </div>
                <div class="pa-field">
                  <label for="email"><?= htmlspecialchars(corpo_t('dp.label_email')) ?> <span class="pa-req">*</span></label>
                  <input type="email" id="email" name="email" required
                         value="<?= htmlspecialchars($post['email'] ?? '') ?>"
                         placeholder="<?= htmlspecialchars(corpo_t('dp.ph_email')) ?>">
                </div>
              </div>

              <div class="pa-field" style="max-width:280px">
                <label for="telephone"><?= htmlspecialchars(corpo_t('dp.label_phone')) ?></label>
                <input type="tel" id="telephone" name="telephone"
                       value="<?= htmlspecialchars($post['telephone'] ?? '') ?>"
                       placeholder="06 00 00 00 00">
              </div>

              <div class="pa-field">
                <label for="message"><?= htmlspecialchars(corpo_t('dp.label_offer')) ?> <span class="pa-req">*</span></label>
                <p class="pa-hint"><?= htmlspecialchars(corpo_t('dp.hint_offer')) ?></p>
                <textarea id="message" name="message" rows="5" required
                          placeholder="<?= htmlspecialchars(corpo_t('dp.ph_offer')) ?>"><?= htmlspecialchars($post['message'] ?? '') ?></textarea>
              </div>

              <label class="dp-consent">
                <input type="checkbox" name="consent" required value="1">
                <span><?= htmlspecialchars(corpo_t('dp.consent')) ?></span>
              </label>

              <button type="submit" class="btn btn--primary" style="width:100%"><?= htmlspecialchars(corpo_t('dp.submit')) ?></button>
            </form>
          <?php endif; ?>
        </div>

        <aside class="dp-aside">
          <h2 class="dp-aside__title"><?= htmlspecialchars(corpo_t('dp.why_title')) ?></h2>
          <ul class="dp-benefits">
            <li>
              <strong><?= htmlspecialchars(corpo_t('dp.b1_t')) ?></strong>
              <span><?= htmlspecialchars(corpo_t('dp.b1_p')) ?></span>
            </li>
            <li>
              <strong><?= htmlspecialchars(corpo_t('dp.b2_t')) ?></strong>
              <span><?= htmlspecialchars(corpo_t('dp.b2_p')) ?></span>
            </li>
            <li>
              <strong><?= htmlspecialchars(corpo_t('dp.b3_t')) ?></strong>
              <span><?= htmlspecialchars(corpo_t('dp.b3_p')) ?></span>
            </li>
          </ul>
          <div class="dp-contact-card">
            <p class="dp-contact-card__label"><?= htmlspecialchars(corpo_t('dp.contact_label')) ?></p>
            <p class="dp-contact-card__name">Elyam Lalaouui</p>
            <p class="dp-contact-card__role"><?= htmlspecialchars(corpo_t('dp.contact_role')) ?></p>
            <a href="mailto:corpoomnes@gmail.com">corpoomnes@gmail.com</a>
          </div>
          <a href="partenaires.php" class="btn btn--ghost" style="width:100%;text-align:center;margin-top:var(--s4)">← <?= htmlspecialchars(corpo_t('pt.back_list')) ?></a>
        </aside>
      </div>
    </div>
  </section>
</main>

<style>
.pa-card, .pa-success, .pa-errors, .pa-field input, .pa-field select, .pa-field textarea,
.pa-section-label, .pa-hint, .pa-req { /* styles partagés proposer-asso */ }
.pa-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--r-xl);
  padding: var(--s8);
}
.pa-success {
  background: rgba(39,174,96,.1);
  border: 1px solid rgba(39,174,96,.35);
  border-radius: var(--r-xl);
  padding: var(--s8);
  text-align: center;
}
.pa-success__icon { font-size: 2rem; color: #2ecc71; margin-bottom: var(--s3); }
.pa-success h3 { font-size: 1.25rem; margin-bottom: var(--s3); }
.pa-success p { color: var(--text-muted); font-size: .9rem; }
.pa-errors {
  background: rgba(239,68,68,.12);
  border: 1px solid rgba(239,68,68,.3);
  border-radius: var(--r-md);
  padding: var(--s4);
  margin-bottom: var(--s5);
  font-size: .85rem;
  color: #fca5a5;
}
.pa-section-label {
  font-size: .68rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: .1em; color: var(--blue-light);
  margin: var(--s6) 0 var(--s4);
}
.pa-section-label:first-of-type { margin-top: 0; }
.pa-field { margin-bottom: var(--s5); }
.pa-field label {
  display: block; font-size: .75rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: .07em;
  color: var(--blue-light); margin-bottom: var(--s2);
}
.pa-hint { font-size: .75rem; color: var(--text-muted); margin-bottom: var(--s2); }
.pa-field input, .pa-field select, .pa-field textarea {
  width: 100%; background: rgba(255,255,255,.04);
  border: 1px solid var(--border); border-radius: var(--r-md);
  padding: .65rem var(--s4); color: #fff; font-size: .9rem;
  font-family: inherit; box-sizing: border-box;
}
.pa-field input:focus, .pa-field select:focus, .pa-field textarea:focus {
  border-color: var(--purple); outline: none;
}
.pa-field textarea { resize: vertical; min-height: 120px; }
.pa-field select option { background: #0D001F; }
.pa-row { display: flex; gap: var(--s4); flex-wrap: wrap; }
.pa-row .pa-field { flex: 1; min-width: 200px; }
.pa-req { color: #ef4444; }

.dp-layout { max-width: 1100px; margin: 0 auto; }
.dp-stats {
  display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--s4);
  margin-bottom: var(--s8);
}
.dp-stat {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--r-lg); padding: var(--s5); text-align: center;
}
.dp-stat__num { display: block; font-size: 1.8rem; font-weight: 800; color: var(--purple-light); }
.dp-stat__label { font-size: .72rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: .06em; }
.dp-grid { display: grid; grid-template-columns: 1.15fr .85fr; gap: var(--s8); align-items: start; }
.dp-form-title { font-size: 1.35rem; margin: 0 0 var(--s2); }
.dp-form-lead { font-size: .88rem; color: var(--text-muted); margin: 0 0 var(--s6); }
.dp-consent {
  display: flex; gap: var(--s3); align-items: flex-start;
  font-size: .78rem; color: var(--text-muted); margin: var(--s5) 0;
  cursor: pointer;
}
.dp-consent input { accent-color: var(--purple); margin-top: .2rem; }
.dp-aside__title { font-size: 1.1rem; margin: 0 0 var(--s5); }
.dp-benefits { list-style: none; padding: 0; margin: 0 0 var(--s6); display: flex; flex-direction: column; gap: var(--s4); }
.dp-benefits li {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--r-lg); padding: var(--s4) var(--s5);
}
.dp-benefits strong { display: block; font-size: .88rem; margin-bottom: .25rem; }
.dp-benefits span { font-size: .8rem; color: var(--text-muted); line-height: 1.5; }
.dp-contact-card {
  background: rgba(93,2,130,.15); border: 1px solid rgba(139,47,201,.35);
  border-radius: var(--r-lg); padding: var(--s5);
}
.dp-contact-card__label { font-size: .65rem; text-transform: uppercase; letter-spacing: .08em; color: var(--purple-light); margin: 0 0 var(--s2); }
.dp-contact-card__name { font-weight: 700; margin: 0; }
.dp-contact-card__role { font-size: .78rem; color: var(--text-muted); margin: 0 0 var(--s3); }
.dp-contact-card a { font-size: .85rem; color: var(--blue-light); }
.dp-success-actions { display: flex; gap: var(--s3); justify-content: center; flex-wrap: wrap; margin-top: var(--s5); }
@media (max-width: 900px) {
  .dp-grid { grid-template-columns: 1fr; }
  .dp-stats { grid-template-columns: 1fr; }
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
