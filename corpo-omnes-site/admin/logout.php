<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/i18n.php';

corpo_clear_lang_cookie();

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $p['path']     ?? '/',
        'domain'   => $p['domain']   ?? '',
        'secure'   => $p['secure']   ?? false,
        'httponly' => $p['httponly'] ?? true,
        'samesite' => $p['samesite'] ?? 'Lax',
    ]);
}

session_destroy();

header('Location: login.php');
exit;
