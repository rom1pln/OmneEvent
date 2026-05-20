<?php

if (session_status() === PHP_SESSION_NONE) session_start();

$next = (string)($_GET['next'] ?? '');

if ($next !== '') {
    $valid = !preg_match('#^[a-z]+://#i', $next)
          && strpos($next, '//') !== 0
          && strpos($next, "\n") === false
          && strpos($next, "\r") === false;
    if ($valid) {
        $_SESSION['redirect_after_login'] = $next;
    }
}

header('Location: admin/login.php');
exit;
