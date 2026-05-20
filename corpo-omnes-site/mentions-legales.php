<?php
require_once 'includes/i18n.php';

$legalKey     = 'legal.mentions';
$legalPage    = 'mentions-legales';
$legalUpdated = '11/05/2026';

$lang = corpo_current_lang();

if ($lang === 'en') {
    $legalToc = [
        ['id' => 'sec-publisher',  'label' => 'Publisher'],
        ['id' => 'sec-director',   'label' => 'Publication director'],
        ['id' => 'sec-host',       'label' => 'Hosting'],
        ['id' => 'sec-ip',         'label' => 'Intellectual property'],
        ['id' => 'sec-liability',  'label' => 'Liability'],
        ['id' => 'sec-credits',    'label' => 'Credits'],
        ['id' => 'sec-law',        'label' => 'Applicable law'],
    ];
    $legalContent = <<<HTML
<section id="sec-publisher">
  <h2>1. Publisher</h2>
  <p>
    This website is published by <strong>Corpo Omnes Lyon</strong>, a non-profit student association
    (French "association loi 1901") representing the students of the Omnes Education group in Lyon
    (ECE, ESCE, HEIP, INSEEC, Sup de Pub).
  </p>
  <ul>
    <li><strong>Registered office:</strong> 25 rue de l'Université, 69007 Lyon, France</li>
    <li><strong>Email:</strong> <a href="mailto:corpoomnes@gmail.com">corpoomnes@gmail.com</a></li>
    <li><strong>Instagram:</strong> <a href="https://instagram.com/copro_omnes" target="_blank" rel="noopener">@copro_omnes</a></li>
  </ul>
</section>

<section id="sec-director">
  <h2>2. Publication director</h2>
  <p>
    The publication director is the President of Corpo Omnes Lyon in office. The director can be
    contacted via the email address mentioned above.
  </p>
</section>

<section id="sec-host">
  <h2>3. Hosting</h2>
  <p>
    The site is hosted on infrastructure provided by the Omnes Education group and its technical
    partners. Contact information can be requested at the publisher's email above.
  </p>
</section>

<section id="sec-ip">
  <h2>4. Intellectual property</h2>
  <p>
    All content (texts, photographs, logos, graphics, source code) available on this website is
    protected by copyright and intellectual property rights. Any reproduction, total or partial,
    requires prior written authorisation from Corpo Omnes Lyon, except for the logos of partner
    schools and associations which remain the property of their respective owners.
  </p>
</section>

<section id="sec-liability">
  <h2>5. Liability</h2>
  <p>
    Corpo Omnes Lyon makes every effort to provide accurate and up-to-date information but cannot
    be held liable for errors, omissions or unavailability of the website. Hyperlinks to external
    websites are provided for convenience and we are not responsible for their content.
  </p>
</section>

<section id="sec-credits">
  <h2>6. Credits</h2>
  <p>
    Design and development: Corpo Omnes Lyon's tech team, in collaboration with student volunteers.
    Fonts and icons used under their respective open-source licences.
  </p>
</section>

<section id="sec-law">
  <h2>7. Applicable law</h2>
  <p>
    This website and its terms of use are subject to French law. Any dispute relating to its use
    will fall under the exclusive jurisdiction of the French courts.
  </p>
</section>
HTML;
} else {
    $legalToc = [
        ['id' => 'sec-editeur',     'label' => "Éditeur du site"],
        ['id' => 'sec-directeur',   'label' => 'Directeur de la publication'],
        ['id' => 'sec-hebergement', 'label' => 'Hébergement'],
        ['id' => 'sec-propriete',   'label' => 'Propriété intellectuelle'],
        ['id' => 'sec-responsab',   'label' => 'Responsabilité'],
        ['id' => 'sec-credits',     'label' => 'Crédits'],
        ['id' => 'sec-droit',       'label' => 'Droit applicable'],
    ];
    $legalContent = <<<HTML
<section id="sec-editeur">
  <h2>1. Éditeur du site</h2>
  <p>
    Le présent site est édité par <strong>Corpo Omnes Lyon</strong>, association loi 1901
    représentant les étudiants du groupe Omnes Education sur le campus de Lyon (ECE, ESCE, HEIP,
    INSEEC, Sup de Pub).
  </p>
  <ul>
    <li><strong>Siège social :</strong> 25 rue de l'Université, 69007 Lyon, France</li>
    <li><strong>Email :</strong> <a href="mailto:corpoomnes@gmail.com">corpoomnes@gmail.com</a></li>
    <li><strong>Instagram :</strong> <a href="https://instagram.com/copro_omnes" target="_blank" rel="noopener">@copro_omnes</a></li>
  </ul>
</section>

<section id="sec-directeur">
  <h2>2. Directeur de la publication</h2>
  <p>
    Le directeur de la publication est le ou la Président·e en exercice de Corpo Omnes Lyon. Il
    ou elle peut être joint·e à l'adresse email indiquée ci-dessus.
  </p>
</section>

<section id="sec-hebergement">
  <h2>3. Hébergement</h2>
  <p>
    Le site est hébergé sur l'infrastructure technique mise à disposition par le groupe
    Omnes Education et ses partenaires techniques. Les coordonnées complètes de l'hébergeur sont
    communicables sur demande à l'adresse de l'éditeur indiquée ci-dessus.
  </p>
</section>

<section id="sec-propriete">
  <h2>4. Propriété intellectuelle</h2>
  <p>
    L'ensemble du contenu présent sur le site (textes, photographies, logos, graphismes, code
    source) est protégé par les droits d'auteur et de propriété intellectuelle. Toute
    reproduction, totale ou partielle, est soumise à autorisation écrite préalable de Corpo Omnes
    Lyon, à l'exception des logos des écoles et associations partenaires, qui restent la
    propriété de leurs titulaires respectifs.
  </p>
</section>

<section id="sec-responsab">
  <h2>5. Responsabilité</h2>
  <p>
    Corpo Omnes Lyon met tout en œuvre pour fournir des informations exactes et à jour, mais ne
    saurait être tenue responsable des erreurs, omissions ou indisponibilités du site. Les liens
    hypertextes vers des sites tiers sont fournis à titre informatif ; nous ne pouvons garantir
    le contenu de ces sites.
  </p>
</section>

<section id="sec-credits">
  <h2>6. Crédits</h2>
  <p>
    Conception et développement : équipe technique de Corpo Omnes Lyon, en collaboration avec
    les étudiants bénévoles. Polices et icônes utilisées sous leurs licences libres respectives.
  </p>
</section>

<section id="sec-droit">
  <h2>7. Droit applicable</h2>
  <p>
    Le site et ses conditions d'utilisation sont régis par le droit français. Tout litige relatif
    à leur utilisation relèvera de la compétence exclusive des tribunaux français.
  </p>
</section>
HTML;
}

$legalRelated = [
    ['href' => 'politique-confidentialite.php', 'label' => corpo_t('legal.confid.meta_title')],
    ['href' => 'politique-cookies.php',         'label' => corpo_t('legal.cookies.meta_title')],
    ['href' => 'cgu.php',                       'label' => corpo_t('legal.cgu.meta_title')],
];

require_once 'includes/legal-layout.php';
