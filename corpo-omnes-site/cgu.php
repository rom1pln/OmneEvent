<?php
require_once 'includes/i18n.php';

$legalKey     = 'legal.cgu';
$legalPage    = 'cgu';
$legalUpdated = '11/05/2026';

$lang = corpo_current_lang();

if ($lang === 'en') {
    $legalToc = [
        ['id' => 'sec-purpose',  'label' => 'Purpose'],
        ['id' => 'sec-access',   'label' => 'Access'],
        ['id' => 'sec-account',  'label' => 'Account & login'],
        ['id' => 'sec-conduct',  'label' => 'Acceptable use'],
        ['id' => 'sec-content',  'label' => 'User-generated content'],
        ['id' => 'sec-moderation','label' => 'Moderation'],
        ['id' => 'sec-suspension','label' => 'Suspension & deletion'],
        ['id' => 'sec-availab',  'label' => 'Availability'],
        ['id' => 'sec-changes',  'label' => 'Changes'],
        ['id' => 'sec-law',      'label' => 'Applicable law'],
    ];
    $legalContent = <<<HTML
<section id="sec-purpose">
  <h2>1. Purpose</h2>
  <p>
    These Terms of Use ("ToU") govern access to and use of the Corpo Omnes Lyon website and
    its services. By using the site, you agree to abide by these terms.
  </p>
</section>

<section id="sec-access">
  <h2>2. Access</h2>
  <p>
    Most of the website is freely accessible. Some areas (admin panel, "my…" pages) require an
    account and are reserved for active Omnes Education students or for those holding a
    specific role.
  </p>
</section>

<section id="sec-account">
  <h2>3. Account &amp; login</h2>
  <ul>
    <li>You must provide accurate information and keep your password confidential.</li>
    <li>You are responsible for all activity carried out under your account.</li>
    <li>Sharing your account with a third party is forbidden.</li>
  </ul>
</section>

<section id="sec-conduct">
  <h2>4. Acceptable use</h2>
  <p>The following are strictly forbidden on the website:</p>
  <ul>
    <li>publishing illegal, defamatory, racist, sexist, hateful or harassing content;</li>
    <li>impersonating someone else;</li>
    <li>compromising the security of the website (intrusion attempt, denial of service, etc.);</li>
    <li>commercial use without prior authorisation.</li>
  </ul>
</section>

<section id="sec-content">
  <h2>5. User-generated content</h2>
  <p>
    You remain the author of the content you publish (news, events, comments). By publishing it,
    you grant Corpo Omnes Lyon a non-exclusive right to use it in connection with the operation
    of the website and student communication.
  </p>
</section>

<section id="sec-moderation">
  <h2>6. Moderation</h2>
  <p>
    Some content is subject to a priori validation by an administrator. We reserve the right to
    remove any content that does not comply with these ToU.
  </p>
</section>

<section id="sec-suspension">
  <h2>7. Suspension &amp; deletion</h2>
  <p>
    Any breach of these ToU may result in the suspension or deletion of the account, without
    prejudice to legal action.
  </p>
</section>

<section id="sec-availab">
  <h2>8. Availability</h2>
  <p>
    The website is provided "as is". Despite our best efforts, we cannot guarantee uninterrupted
    availability. Outages may occur for maintenance.
  </p>
</section>

<section id="sec-changes">
  <h2>9. Changes</h2>
  <p>
    These ToU may be updated. The current version is the one published on the website on the
    "last updated" date displayed above.
  </p>
</section>

<section id="sec-law">
  <h2>10. Applicable law</h2>
  <p>
    These ToU are governed by French law. Any dispute relating to their interpretation or
    execution will fall under the exclusive jurisdiction of the French courts.
  </p>
</section>
HTML;
} else {
    $legalToc = [
        ['id' => 'sec-objet',        'label' => 'Objet'],
        ['id' => 'sec-acces',        'label' => 'Accès au site'],
        ['id' => 'sec-compte',       'label' => 'Compte & connexion'],
        ['id' => 'sec-conduite',     'label' => 'Usage acceptable'],
        ['id' => 'sec-contenu',      'label' => 'Contenus publiés'],
        ['id' => 'sec-moderation',   'label' => 'Modération'],
        ['id' => 'sec-suspension',   'label' => 'Suspension & suppression'],
        ['id' => 'sec-dispo',        'label' => 'Disponibilité'],
        ['id' => 'sec-evolutions',   'label' => 'Évolutions'],
        ['id' => 'sec-droit',        'label' => 'Droit applicable'],
    ];
    $legalContent = <<<HTML
<section id="sec-objet">
  <h2>1. Objet</h2>
  <p>
    Les présentes Conditions Générales d'Utilisation (« CGU ») régissent l'accès et l'utilisation
    du site Corpo Omnes Lyon et de ses services. En utilisant le site, vous acceptez de
    respecter ces conditions.
  </p>
</section>

<section id="sec-acces">
  <h2>2. Accès au site</h2>
  <p>
    La majeure partie du site est en accès libre. Certaines zones (panneau admin, pages
    « mes… ») nécessitent un compte et sont réservées aux étudiants Omnes Education actifs ou
    aux titulaires d'un rôle spécifique.
  </p>
</section>

<section id="sec-compte">
  <h2>3. Compte &amp; connexion</h2>
  <ul>
    <li>Vous devez fournir des informations exactes et garder votre mot de passe confidentiel.</li>
    <li>Vous êtes responsable de toute activité réalisée depuis votre compte.</li>
    <li>Le partage de compte avec un tiers est interdit.</li>
  </ul>
</section>

<section id="sec-conduite">
  <h2>4. Usage acceptable</h2>
  <p>Sont strictement interdits sur le site :</p>
  <ul>
    <li>la publication de contenus illicites, diffamatoires, racistes, sexistes, haineux ou harcelants ;</li>
    <li>l'usurpation d'identité ;</li>
    <li>toute action portant atteinte à la sécurité du site (tentative d'intrusion, déni de service, etc.) ;</li>
    <li>l'usage commercial sans autorisation préalable.</li>
  </ul>
</section>

<section id="sec-contenu">
  <h2>5. Contenus publiés par les utilisateurs</h2>
  <p>
    Vous restez auteur des contenus que vous publiez (actualités, événements, commentaires).
    En les publiant, vous accordez à Corpo Omnes Lyon un droit non exclusif d'utilisation dans
    le cadre du fonctionnement du site et de la communication étudiante.
  </p>
</section>

<section id="sec-moderation">
  <h2>6. Modération</h2>
  <p>
    Certains contenus sont soumis à une validation a priori par un administrateur. Nous nous
    réservons le droit de retirer tout contenu non conforme aux présentes CGU.
  </p>
</section>

<section id="sec-suspension">
  <h2>7. Suspension &amp; suppression</h2>
  <p>
    Tout manquement aux présentes CGU peut entraîner la suspension ou la suppression du compte,
    sans préjudice des éventuelles actions en justice.
  </p>
</section>

<section id="sec-dispo">
  <h2>8. Disponibilité</h2>
  <p>
    Le site est fourni « en l'état ». Malgré tous nos efforts, nous ne garantissons pas une
    disponibilité ininterrompue. Des coupures peuvent intervenir pour maintenance.
  </p>
</section>

<section id="sec-evolutions">
  <h2>9. Évolutions</h2>
  <p>
    Les présentes CGU peuvent être mises à jour. La version applicable est celle publiée sur le
    site à la date de « dernière mise à jour » indiquée ci-dessus.
  </p>
</section>

<section id="sec-droit">
  <h2>10. Droit applicable</h2>
  <p>
    Les présentes CGU sont régies par le droit français. Tout litige relatif à leur
    interprétation ou à leur exécution relèvera de la compétence exclusive des tribunaux
    français.
  </p>
</section>
HTML;
}

$legalRelated = [
    ['href' => 'mentions-legales.php',          'label' => corpo_t('legal.mentions.meta_title')],
    ['href' => 'politique-confidentialite.php', 'label' => corpo_t('legal.confid.meta_title')],
    ['href' => 'cgv.php',                       'label' => corpo_t('legal.cgv.meta_title')],
];

require_once 'includes/legal-layout.php';
