<?php
/** Redirection vers l’API admin (accès panneau requis). */
$id = (int)($_GET['id'] ?? 0);
$target = '../admin/api/note-frais-pdf.php' . ($id > 0 ? '?id=' . $id : '');
header('Location: ' . $target, true, 302);
exit;
