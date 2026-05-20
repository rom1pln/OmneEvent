<?php
// pont vers admin/login.php, stocke le ?next= en session pour rediriger après co
if (session_status() === PHP_SESSION_NONE) session_start();

$next = (string)($_GET['next'] ?? '');

// whitelist pour éviter l'open redirect (on accepte que les URLs locales)
if ($next !== '') {
    $valid = !preg_match('#^[a-z]+://#i', $next)         // pas https://...
          && strpos($next, '//') !== 0                    // pas //evil.com
          && strpos($next, "\n") === false                // pas d'injection
          && strpos($next, "\r") === false;
    if ($valid) {
        $_SESSION['redirect_after_login'] = $next;
    }
}

header('Location: admin/login.php');
exit;
