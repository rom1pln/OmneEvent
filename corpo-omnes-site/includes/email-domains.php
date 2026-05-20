<?php
// domaines email autorisés selon le profil (étudiant ou personnel)
// si tu veux en ajouter, modifie juste les constantes ci-dessous
const CORPO_EMAIL_DOMAINS_ETUDIANT = [
    'edu.ece.fr',
    'edu.esce.fr',
    'edu.heip.fr',
    'inseec-france.com',
    'supdepub.com',
];

// domaines pour le personnel (profs, admin, etc.)
const CORPO_EMAIL_DOMAINS_PERSONNEL = [
    'omneseducation.com',
    'omnesintervenant.com',
    'prof.omneseducation.com',
    'ece.fr',
    'esce.fr',
    'heip.fr',
    'inseec.com',
    'supdepub.com',
];

// extrait le domaine depuis un email
function corpo_email_domain(string $email): string {
    $email = strtolower(trim($email));
    $pos   = strrpos($email, '@');
    if ($pos === false) return '';
    return substr($email, $pos + 1);
}

// on vérifie que le domaine est bien dans la liste pour le type
function corpo_email_is_valid_for_type(string $email, string $type): bool {
    $domain = corpo_email_domain($email);
    if ($domain === '') return false;
    $list = $type === 'personnel'
        ? CORPO_EMAIL_DOMAINS_PERSONNEL
        : CORPO_EMAIL_DOMAINS_ETUDIANT;
    foreach ($list as $allowed) {
        if ($domain === strtolower($allowed)) return true;
    }
    return false;
}

// liste des domaines avec @ pour les afficher dans le formulaire
function corpo_email_allowed_domains(string $type): array {
    $list = $type === 'personnel'
        ? CORPO_EMAIL_DOMAINS_PERSONNEL
        : CORPO_EMAIL_DOMAINS_ETUDIANT;
    return array_map(fn($d) => '@' . $d, $list);
}
