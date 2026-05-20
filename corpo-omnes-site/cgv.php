<?php
require_once 'includes/i18n.php';

$legalKey     = 'legal.cgv';
$legalPage    = 'cgv';
$legalUpdated = '11/05/2026';

$lang = corpo_current_lang();

if ($lang === 'en') {
    $legalToc = [
        ['id' => 'sec-scope',     'label' => 'Scope'],
        ['id' => 'sec-products',  'label' => 'Products & services'],
        ['id' => 'sec-prices',    'label' => 'Prices'],
        ['id' => 'sec-orders',    'label' => 'Orders'],
        ['id' => 'sec-payment',   'label' => 'Payment'],
        ['id' => 'sec-delivery',  'label' => 'Delivery / Access'],
        ['id' => 'sec-withdraw',  'label' => 'Right of withdrawal'],
        ['id' => 'sec-refund',    'label' => 'Refunds & cancellations'],
        ['id' => 'sec-liability', 'label' => 'Liability'],
        ['id' => 'sec-law',       'label' => 'Applicable law'],
    ];
    $legalContent = <<<HTML
<section id="sec-scope">
  <h2>1. Scope</h2>
  <p>
    These Terms and Conditions of Sale ("T&amp;Cs") apply to all paid services offered by
    Corpo Omnes Lyon: event ticketing, sports memberships, partner offers, sale of association
    merchandise.
  </p>
</section>

<section id="sec-products">
  <h2>2. Products &amp; services</h2>
  <p>
    Each event or product is described on its dedicated page, with the date, location, price and
    any applicable conditions. Photographs are not contractual.
  </p>
</section>

<section id="sec-prices">
  <h2>3. Prices</h2>
  <p>
    Prices are indicated in euros, including all taxes. They may vary depending on whether the
    user is a member of an Omnes Education school or external. Prices may evolve and the
    applicable one is the price displayed at the time of the order.
  </p>
</section>

<section id="sec-orders">
  <h2>4. Orders</h2>
  <p>
    The order is confirmed once payment has been received. A receipt is sent by email. Some
    events have limited capacity and orders are accepted in order of payment.
  </p>
</section>

<section id="sec-payment">
  <h2>5. Payment</h2>
  <p>
    Payment is made by bank card via a secure provider (HelloAsso, Stripe…). Corpo Omnes Lyon
    never stores card data on its servers.
  </p>
</section>

<section id="sec-delivery">
  <h2>6. Delivery / Access</h2>
  <p>
    For events: access is granted upon presentation of the electronic ticket received by email
    and a valid student card. For merchandise: pick-up at the indicated point on campus.
  </p>
</section>

<section id="sec-withdraw">
  <h2>7. Right of withdrawal</h2>
  <p>
    In accordance with article L. 221-28 of the French Consumer Code, the right of withdrawal
    does not apply to leisure services provided on a specific date or period. It does apply to
    physical merchandise (14 days).
  </p>
</section>

<section id="sec-refund">
  <h2>8. Refunds &amp; cancellations</h2>
  <p>
    If an event is cancelled by the organiser, refunds are made within 30 days. In case of
    cancellation by the user, conditions are specified on the event page.
  </p>
</section>

<section id="sec-liability">
  <h2>9. Liability</h2>
  <p>
    Corpo Omnes Lyon is bound by an obligation of means and not of result. We cannot be held
    responsible for damages caused by misuse or external causes (transport, weather, etc.).
  </p>
</section>

<section id="sec-law">
  <h2>10. Applicable law</h2>
  <p>
    These T&amp;Cs are governed by French law. Any dispute will be subject to mediation and, if
    necessary, to the competent French courts.
  </p>
</section>
HTML;
} else {
    $legalToc = [
        ['id' => 'sec-objet',         'label' => 'Objet'],
        ['id' => 'sec-prestations',   'label' => 'Produits & prestations'],
        ['id' => 'sec-prix',          'label' => 'Prix'],
        ['id' => 'sec-commande',      'label' => 'Commande'],
        ['id' => 'sec-paiement',      'label' => 'Paiement'],
        ['id' => 'sec-livraison',     'label' => 'Livraison / Accès'],
        ['id' => 'sec-retractation',  'label' => 'Droit de rétractation'],
        ['id' => 'sec-remboursement', 'label' => 'Remboursements & annulations'],
        ['id' => 'sec-responsab',     'label' => 'Responsabilité'],
        ['id' => 'sec-droit',         'label' => 'Droit applicable'],
    ];
    $legalContent = <<<HTML
<section id="sec-objet">
  <h2>1. Objet</h2>
  <p>
    Les présentes Conditions Générales de Vente (« CGV ») s'appliquent à l'ensemble des
    prestations payantes proposées par Corpo Omnes Lyon : billetteries d'événements,
    cotisations sportives, offres partenaires, vente de produits associatifs.
  </p>
</section>

<section id="sec-prestations">
  <h2>2. Produits &amp; prestations</h2>
  <p>
    Chaque événement ou produit est présenté sur sa fiche dédiée, avec date, lieu, tarif et
    éventuelles conditions applicables. Les photographies sont non contractuelles.
  </p>
</section>

<section id="sec-prix">
  <h2>3. Prix</h2>
  <p>
    Les prix sont indiqués en euros, toutes taxes comprises. Ils peuvent varier selon que
    l'utilisateur est membre d'une école Omnes Education ou externe. Les prix peuvent évoluer ;
    le prix applicable est celui affiché au moment de la commande.
  </p>
</section>

<section id="sec-commande">
  <h2>4. Commande</h2>
  <p>
    La commande est confirmée dès réception du paiement. Un reçu est envoyé par email. Certains
    événements ont des places limitées : les commandes sont acceptées dans l'ordre des
    paiements.
  </p>
</section>

<section id="sec-paiement">
  <h2>5. Paiement</h2>
  <p>
    Le paiement s'effectue par carte bancaire via un prestataire sécurisé (HelloAsso, Stripe…).
    Corpo Omnes Lyon ne stocke jamais les données de carte sur ses serveurs.
  </p>
</section>

<section id="sec-livraison">
  <h2>6. Livraison / Accès</h2>
  <p>
    Pour les événements : l'accès est conditionné à la présentation du billet électronique reçu
    par email et d'une carte étudiante valide. Pour les produits physiques : retrait sur le
    point indiqué sur le campus.
  </p>
</section>

<section id="sec-retractation">
  <h2>7. Droit de rétractation</h2>
  <p>
    Conformément à l'article L. 221-28 du Code de la consommation, le droit de rétractation ne
    s'applique pas aux services de loisirs fournis à une date ou à une période déterminée. Il
    s'applique en revanche aux produits physiques (délai de 14 jours).
  </p>
</section>

<section id="sec-remboursement">
  <h2>8. Remboursements &amp; annulations</h2>
  <p>
    En cas d'annulation d'un événement par l'organisateur, le remboursement intervient sous
    30 jours. En cas d'annulation par l'utilisateur, les conditions sont précisées sur la fiche
    de l'événement concerné.
  </p>
</section>

<section id="sec-responsab">
  <h2>9. Responsabilité</h2>
  <p>
    Corpo Omnes Lyon est tenue à une obligation de moyens et non de résultat. Notre
    responsabilité ne saurait être engagée pour des dommages liés à un usage non conforme ou
    à des causes extérieures (transports, météo, etc.).
  </p>
</section>

<section id="sec-droit">
  <h2>10. Droit applicable</h2>
  <p>
    Les présentes CGV sont régies par le droit français. Tout litige fera l'objet d'une
    tentative de médiation préalable et, à défaut, sera soumis aux juridictions françaises
    compétentes.
  </p>
</section>
HTML;
}

$legalRelated = [
    ['href' => 'mentions-legales.php',          'label' => corpo_t('legal.mentions.meta_title')],
    ['href' => 'cgu.php',                       'label' => corpo_t('legal.cgu.meta_title')],
    ['href' => 'politique-confidentialite.php', 'label' => corpo_t('legal.confid.meta_title')],
];

require_once 'includes/legal-layout.php';
