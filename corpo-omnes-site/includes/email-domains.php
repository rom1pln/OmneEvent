<?php

const CORPO_EMAIL_DOMAINS_ETUDIANT = [
    'edu.ece.fr',
    'edu.esce.fr',
    'edu.heip.fr',
    'inseec-france.com',
    'supdepub.com',
];

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

function corpo_email_domain(string $email): string {
    $email = strtolower(trim($email));
    $pos   = strrpos($email, '@');
    if ($pos === false) return '';
    return substr($email, $pos + 1);
}

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

function corpo_email_allowed_domains(string $type): array {
    $list = $type === 'personnel'
        ? CORPO_EMAIL_DOMAINS_PERSONNEL
        : CORPO_EMAIL_DOMAINS_ETUDIANT;
    return array_map(fn($d) => '@' . $d, $list);
}
