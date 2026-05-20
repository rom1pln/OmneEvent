<?php
declare(strict_types=1);
/**
 * set-lang.php - Change la langue (cookie) puis revient sur la page demandée.
 * Usage : set-lang.php?lang=fr&redirect=/corpo-omnes-site/index.php
 */

require_once __DIR__ . '/includes/i18n.php';

$lang = (string)($_GET['lang'] ?? '');
if (in_array($lang, CORPO_LANG_ALLOWED, true)) {
    corpo_set_lang_cookie($lang);
}

/* Redirection sécurisée : on n'autorise que des chemins relatifs internes
   (pas d'URL externe) pour éviter une redirection ouverte. */
$target = (string)($_GET['redirect'] ?? '');
if ($target === ''
    || !preg_match('#^/[A-Za-z0-9_./?=&%-]*$#', $target)
    || str_contains($target, '..')) {
    $target = '/';
}

header('Location: ' . $target);
exit;
