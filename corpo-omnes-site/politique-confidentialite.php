<?php
require_once 'includes/i18n.php';

$legalKey     = 'legal.confid';
$legalPage    = 'politique-confidentialite';
$legalUpdated = '11/05/2026';

$lang = corpo_current_lang();

if ($lang === 'en') {
    $legalToc = [
        ['id' => 'sec-intro',     'label' => 'Introduction'],
        ['id' => 'sec-data',      'label' => 'Data we collect'],
        ['id' => 'sec-purposes',  'label' => 'Purposes & legal basis'],
        ['id' => 'sec-retention', 'label' => 'Retention periods'],
        ['id' => 'sec-recipients','label' => 'Recipients & subcontractors'],
        ['id' => 'sec-rights',    'label' => 'Your rights'],
        ['id' => 'sec-security',  'label' => 'Security'],
        ['id' => 'sec-transfers', 'label' => 'International transfers'],
        ['id' => 'sec-contact',   'label' => 'Contact the DPO'],
    ];
    $legalContent = <<<HTML
<section id="sec-intro">
  <h2>1. Introduction</h2>
  <p>
    Corpo Omnes Lyon ("we", "us") is committed to protecting the privacy of its members and visitors.
    This policy explains what personal data we collect, why, how long we keep it and how you can
    exercise your rights under the GDPR and the French Data Protection Act.
  </p>
</section>

<section id="sec-data">
  <h2>2. Data we collect</h2>
  <ul>
    <li><strong>Account data:</strong> first and last name, school email, school, promotion, role.</li>
    <li><strong>Activity data:</strong> registrations to events / sports / associations, news subscriptions.</li>
    <li><strong>Connection data:</strong> session identifiers, IP (anonymised), browser type.</li>
    <li><strong>Preferences:</strong> language, display choices, cookie consent.</li>
  </ul>
</section>

<section id="sec-purposes">
  <h2>3. Purposes &amp; legal basis</h2>
  <table class="legal-table">
    <thead><tr><th>Purpose</th><th>Legal basis</th></tr></thead>
    <tbody>
      <tr><td>Account creation and authentication</td><td>Performance of the contract / membership</td></tr>
      <tr><td>Event &amp; sports registration management</td><td>Performance of the contract</td></tr>
      <tr><td>Communication about student life</td><td>Legitimate interest</td></tr>
      <tr><td>Audience measurement (anonymous)</td><td>Consent</td></tr>
      <tr><td>Security &amp; fraud prevention</td><td>Legitimate interest</td></tr>
    </tbody>
  </table>
</section>

<section id="sec-retention">
  <h2>4. Retention periods</h2>
  <ul>
    <li>Active account data: throughout the membership, then up to 3 years.</li>
    <li>Connection logs: 12 months.</li>
    <li>Cookies: see <a href="politique-cookies.php">cookie policy</a>.</li>
  </ul>
</section>

<section id="sec-recipients">
  <h2>5. Recipients &amp; subcontractors</h2>
  <p>
    Your data is processed by the Corpo Omnes Lyon team and the technical service providers
    necessary to operate the website (hosting, backups). It is never sold or transferred to
    advertising partners.
  </p>
</section>

<section id="sec-rights">
  <h2>6. Your rights</h2>
  <p>You may at any time:</p>
  <ul>
    <li>access the data we hold about you;</li>
    <li>request a rectification or deletion;</li>
    <li>request a restriction or object to processing;</li>
    <li>withdraw consent (analytics cookies, marketing);</li>
    <li>request portability of your data;</li>
    <li>lodge a complaint with the CNIL (<a href="https://www.cnil.fr" target="_blank" rel="noopener">cnil.fr</a>).</li>
  </ul>
</section>

<section id="sec-security">
  <h2>7. Security</h2>
  <p>
    Passwords are stored as hashes using modern algorithms. Connections are made via HTTPS and
    access to sensitive data is restricted to authorised administrators.
  </p>
</section>

<section id="sec-transfers">
  <h2>8. International transfers</h2>
  <p>
    Data is hosted in the European Union. Any transfer outside the EU would be subject to
    appropriate guarantees (Standard Contractual Clauses).
  </p>
</section>

<section id="sec-contact">
  <h2>9. Contact the DPO</h2>
  <p>
    For any question or to exercise your rights, contact us at
    <a href="mailto:corpoomnes@gmail.com">corpoomnes@gmail.com</a> with the subject
    "GDPR".
  </p>
</section>
HTML;
} else {
    $legalToc = [
        ['id' => 'sec-intro',         'label' => 'Introduction'],
        ['id' => 'sec-donnees',       'label' => 'Données collectées'],
        ['id' => 'sec-finalites',     'label' => 'Finalités & bases légales'],
        ['id' => 'sec-conservation',  'label' => 'Durées de conservation'],
        ['id' => 'sec-destinataires', 'label' => 'Destinataires & sous-traitants'],
        ['id' => 'sec-droits',        'label' => 'Vos droits'],
        ['id' => 'sec-securite',      'label' => 'Sécurité'],
        ['id' => 'sec-transferts',    'label' => 'Transferts hors UE'],
        ['id' => 'sec-contact',       'label' => 'Contacter le DPO'],
    ];
    $legalContent = <<<HTML
<section id="sec-intro">
  <h2>1. Introduction</h2>
  <p>
    Corpo Omnes Lyon (« nous ») s'engage à protéger la vie privée de ses membres et des visiteurs
    du site. La présente politique explique quelles données personnelles nous collectons,
    pourquoi, combien de temps elles sont conservées et comment vous pouvez exercer vos droits
    issus du RGPD et de la loi Informatique et Libertés.
  </p>
</section>

<section id="sec-donnees">
  <h2>2. Données collectées</h2>
  <ul>
    <li><strong>Données de compte :</strong> nom, prénom, email scolaire, école, promotion, rôle.</li>
    <li><strong>Données d'activité :</strong> inscriptions à des événements / sports / associations, abonnements aux actualités.</li>
    <li><strong>Données de connexion :</strong> identifiants de session, adresse IP (anonymisée), type de navigateur.</li>
    <li><strong>Préférences :</strong> langue, choix d'affichage, consentement aux cookies.</li>
  </ul>
</section>

<section id="sec-finalites">
  <h2>3. Finalités &amp; bases légales</h2>
  <table class="legal-table">
    <thead><tr><th>Finalité</th><th>Base légale</th></tr></thead>
    <tbody>
      <tr><td>Création et authentification du compte</td><td>Exécution du contrat / adhésion</td></tr>
      <tr><td>Gestion des inscriptions événements &amp; sports</td><td>Exécution du contrat</td></tr>
      <tr><td>Communication sur la vie étudiante</td><td>Intérêt légitime</td></tr>
      <tr><td>Mesure d'audience anonyme</td><td>Consentement</td></tr>
      <tr><td>Sécurité &amp; prévention de la fraude</td><td>Intérêt légitime</td></tr>
    </tbody>
  </table>
</section>

<section id="sec-conservation">
  <h2>4. Durées de conservation</h2>
  <ul>
    <li>Données de compte actif : pendant toute la durée de l'adhésion, puis jusqu'à 3 ans.</li>
    <li>Logs de connexion : 12 mois.</li>
    <li>Cookies : voir la <a href="politique-cookies.php">politique de cookies</a>.</li>
  </ul>
</section>

<section id="sec-destinataires">
  <h2>5. Destinataires &amp; sous-traitants</h2>
  <p>
    Vos données sont traitées par l'équipe Corpo Omnes Lyon et par les prestataires techniques
    nécessaires à l'exploitation du site (hébergement, sauvegardes). Elles ne sont jamais
    vendues ni transmises à des partenaires publicitaires.
  </p>
</section>

<section id="sec-droits">
  <h2>6. Vos droits</h2>
  <p>Vous pouvez à tout moment :</p>
  <ul>
    <li>accéder aux données que nous détenons sur vous ;</li>
    <li>demander leur rectification ou leur suppression ;</li>
    <li>demander la limitation ou vous opposer au traitement ;</li>
    <li>retirer votre consentement (cookies analytics, marketing) ;</li>
    <li>demander la portabilité de vos données ;</li>
    <li>introduire une réclamation auprès de la CNIL (<a href="https://www.cnil.fr" target="_blank" rel="noopener">cnil.fr</a>).</li>
  </ul>
</section>

<section id="sec-securite">
  <h2>7. Sécurité</h2>
  <p>
    Les mots de passe sont stockés sous forme de hash avec des algorithmes modernes.
    Les connexions s'effectuent via HTTPS et l'accès aux données sensibles est limité aux
    administrateurs habilités.
  </p>
</section>

<section id="sec-transferts">
  <h2>8. Transferts hors UE</h2>
  <p>
    Les données sont hébergées dans l'Union européenne. Tout transfert hors UE ferait l'objet de
    garanties appropriées (Clauses Contractuelles Types).
  </p>
</section>

<section id="sec-contact">
  <h2>9. Contacter le DPO</h2>
  <p>
    Pour toute question ou pour exercer vos droits, écrivez-nous à
    <a href="mailto:corpoomnes@gmail.com">corpoomnes@gmail.com</a> avec l'objet
    « RGPD ».
  </p>
</section>
HTML;
}

$legalRelated = [
    ['href' => 'mentions-legales.php',  'label' => corpo_t('legal.mentions.meta_title')],
    ['href' => 'politique-cookies.php', 'label' => corpo_t('legal.cookies.meta_title')],
    ['href' => 'cgu.php',               'label' => corpo_t('legal.cgu.meta_title')],
];

require_once 'includes/legal-layout.php';
