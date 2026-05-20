<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/i18n.php';

corpo_clear_lang_cookie();
corpo_destroy_session();

header('Location: login.php');
exit;
