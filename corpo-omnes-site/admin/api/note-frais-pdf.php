<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/notes-frais.php';

if (!isLoggedIn() || !hasAdminPanelAccess()) {
    http_response_code(403);
    exit('Accès refusé.');
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0 || !nf_table_ready($pdo)) {
    http_response_code(404);
    exit('Introuvable.');
}

$note = nf_get($pdo, $id);
$userId = (int)$_SESSION['user_id'];
if (!$note || !nf_can_view($pdo, $userId, $note)) {
    http_response_code(403);
    exit('Accès refusé.');
}

$rel = (string)($note['justificatif_pdf'] ?? '');
$path = realpath(__DIR__ . '/../../' . ltrim($rel, '/'));
$base = realpath(__DIR__ . '/../../images/justificatifs');
if (!$path || !$base || !str_starts_with($path, $base) || !is_file($path)) {
    http_response_code(404);
    exit('Fichier introuvable.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="justificatif-note-' . $id . '.pdf"');
header('Content-Length: ' . (string)filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
