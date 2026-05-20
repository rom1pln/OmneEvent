<?php
require_once 'includes/i18n.php';

$legalKey     = 'legal.cookies';
$legalPage    = 'politique-cookies';
$legalUpdated = '11/05/2026';

$lang = corpo_current_lang();

if ($lang === 'en') {
    $legalToc = [
        ['id' => 'sec-what',     'label' => 'What is a cookie?'],
        ['id' => 'sec-cats',     'label' => 'Categories used'],
        ['id' => 'sec-list',     'label' => 'List of cookies'],
        ['id' => 'sec-manage',   'label' => 'Manage your choices'],
        ['id' => 'sec-browser',  'label' => 'Browser settings'],
        ['id' => 'sec-update',   'label' => 'Policy updates'],
    ];
    $legalContent = <<<HTML
<section id="sec-what">
  <h2>1. What is a cookie?</h2>
  <p>
    A cookie is a small text file deposited on your device when you visit a website. It allows the
    website to recognise you across pages, store preferences (such as language) or measure
    audience.
  </p>
</section>

<section id="sec-cats">
  <h2>2. Categories used on this site</h2>
  <ul>
    <li><strong>Essential</strong> - required for the website to work (session, login, security, language). Always active.</li>
    <li><strong>Preferences</strong> - remember your display choices (filters, views, sorting).</li>
    <li><strong>Analytics</strong> - anonymous statistics to improve the website.</li>
    <li><strong>Marketing &amp; partners</strong> - content for our partners. No advertising third parties enabled by default.</li>
  </ul>
</section>

<section id="sec-list">
  <h2>3. List of cookies</h2>
  <table class="legal-table">
    <thead><tr><th>Name</th><th>Purpose</th><th>Category</th><th>Retention</th></tr></thead>
    <tbody>
      <tr><td><code>PHPSESSID</code></td><td>User session identifier</td><td>Essential</td><td>End of session</td></tr>
      <tr><td><code>corpo_lang</code></td><td>Display language (FR / EN)</td><td>Essential</td><td>1 year</td></tr>
      <tr><td><code>corpo_consent</code></td><td>Cookie preferences</td><td>Essential</td><td>6 months</td></tr>
      <tr><td><code>corpo_pref_*</code></td><td>UI preferences (filters, sort)</td><td>Preferences</td><td>6 months</td></tr>
      <tr><td><code>_ga</code> / <code>_ga_*</code></td><td>Anonymous traffic measurement</td><td>Analytics</td><td>13 months</td></tr>
    </tbody>
  </table>
</section>

<section id="sec-manage">
  <h2>4. Manage your choices</h2>
  <p>
    You can change your cookie preferences at any time by clicking the button below or the
    "Cookies" link in the footer.
  </p>
  <p>
    <button type="button" class="btn btn--primary btn--sm" data-cookie-pref>Open cookie preferences</button>
  </p>
</section>

<section id="sec-browser">
  <h2>5. Browser settings</h2>
  <p>
    You can also configure your browser to refuse or delete cookies. Disabling essential cookies
    may break some features of the website.
  </p>
</section>

<section id="sec-update">
  <h2>6. Policy updates</h2>
  <p>
    This policy may be updated to reflect changes in our services or applicable regulations. The
    "last updated" date above is the effective version.
  </p>
</section>
HTML;
} else {
    $legalToc = [
        ['id' => 'sec-quoi',         'label' => "Qu'est-ce qu'un cookie ?"],
        ['id' => 'sec-categories',   'label' => 'Catégories utilisées'],
        ['id' => 'sec-liste',        'label' => 'Liste des cookies'],
        ['id' => 'sec-gerer',        'label' => 'Gérer vos choix'],
        ['id' => 'sec-navigateur',   'label' => 'Paramètres du navigateur'],
        ['id' => 'sec-mises-a-jour', 'label' => 'Mises à jour'],
    ];
    $legalContent = <<<HTML
<section id="sec-quoi">
  <h2>1. Qu'est-ce qu'un cookie ?</h2>
  <p>
    Un cookie est un petit fichier texte déposé sur votre appareil lors de la visite d'un site
    internet. Il permet au site de vous reconnaître entre les pages, de mémoriser vos
    préférences (par exemple la langue) ou de mesurer l'audience.
  </p>
</section>

<section id="sec-categories">
  <h2>2. Catégories utilisées sur le site</h2>
  <ul>
    <li><strong>Essentiels</strong> - nécessaires au fonctionnement (session, connexion, sécurité, langue). Toujours actifs.</li>
    <li><strong>Préférences</strong> - mémorisent vos choix d'affichage (filtres, vues, tri).</li>
    <li><strong>Mesure d'audience</strong> - statistiques anonymes pour améliorer le site.</li>
    <li><strong>Marketing &amp; partenaires</strong> - contenus de nos partenaires. Aucun service publicitaire tiers n'est activé par défaut.</li>
  </ul>
</section>

<section id="sec-liste">
  <h2>3. Liste des cookies</h2>
  <table class="legal-table">
    <thead><tr><th>Nom</th><th>Finalité</th><th>Catégorie</th><th>Durée</th></tr></thead>
    <tbody>
      <tr><td><code>PHPSESSID</code></td><td>Identifiant de session utilisateur</td><td>Essentiel</td><td>Fin de session</td></tr>
      <tr><td><code>corpo_lang</code></td><td>Langue d'affichage (FR / EN)</td><td>Essentiel</td><td>1 an</td></tr>
      <tr><td><code>corpo_consent</code></td><td>Préférences cookies</td><td>Essentiel</td><td>6 mois</td></tr>
      <tr><td><code>corpo_pref_*</code></td><td>Préférences d'interface (filtres, tri)</td><td>Préférences</td><td>6 mois</td></tr>
      <tr><td><code>_ga</code> / <code>_ga_*</code></td><td>Mesure d'audience anonyme</td><td>Audience</td><td>13 mois</td></tr>
    </tbody>
  </table>
</section>

<section id="sec-gerer">
  <h2>4. Gérer vos choix</h2>
  <p>
    Vous pouvez modifier vos préférences cookies à tout moment en cliquant sur le bouton
    ci-dessous ou sur le lien « Cookies » dans le pied de page.
  </p>
  <p>
    <button type="button" class="btn btn--primary btn--sm" data-cookie-pref>Ouvrir mes préférences cookies</button>
  </p>
</section>

<section id="sec-navigateur">
  <h2>5. Paramètres du navigateur</h2>
  <p>
    Vous pouvez également configurer votre navigateur pour refuser ou supprimer les cookies. La
    désactivation des cookies essentiels peut empêcher certaines fonctionnalités du site.
  </p>
</section>

<section id="sec-mises-a-jour">
  <h2>6. Mises à jour</h2>
  <p>
    Cette politique peut être mise à jour pour refléter l'évolution de nos services ou de la
    réglementation. La date de « dernière mise à jour » indiquée ci-dessus correspond à la
    version en vigueur.
  </p>
</section>
HTML;
}

$legalRelated = [
    ['href' => 'mentions-legales.php',          'label' => corpo_t('legal.mentions.meta_title')],
    ['href' => 'politique-confidentialite.php', 'label' => corpo_t('legal.confid.meta_title')],
    ['href' => 'cgu.php',                       'label' => corpo_t('legal.cgu.meta_title')],
];

require_once 'includes/legal-layout.php';
