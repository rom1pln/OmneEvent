<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/i18n.php';

$lang = (string)($_GET['lang'] ?? '');
if (in_array($lang, CORPO_LANG_ALLOWED, true)) {
    corpo_set_lang_cookie($lang);
}

$target = (string)($_GET['redirect'] ?? '');
if ($target === ''
    || !preg_match('#^/[A-Za-z0-9_./?=&%-]*$#', $target)
    || str_contains($target, '..')) {
    $target = '/';
}

header('Location: ' . $target);
exit;
