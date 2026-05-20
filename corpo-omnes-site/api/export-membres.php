<?php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/spreadsheet-export.php';

if (!isLoggedIn()) {
    http_response_code(401);
    exit('Authentification requise');
}

$selType = (string)($_GET['type'] ?? '');
$selId   = (int)($_GET['id'] ?? 0);
$format  = (string)($_GET['format'] ?? 'xlsx');

if ($selId <= 0 || !in_array($selType, ['asso', 'bde', 'bds', 'sport'], true)) {
    http_response_code(400);
    exit('Paramètres invalides');
}

if ($selType === 'sport') {
    $canManage = isAdminCorpo() || canManageSport($selId, $pdo);
} else {
    $canManage = isAdminCorpo() || canManageAsso($selId, $pdo)
        || canManageBDE($selId, $pdo) || canManageBDS($selId, $pdo);
}
if (!$canManage) {
    http_response_code(403);
    exit('Accès refusé');
}

$structNom = '';
if ($selType === 'sport') {
    $st = $pdo->prepare('SELECT nom FROM sports WHERE id = ?');
} else {
    $st = $pdo->prepare('SELECT nom FROM associations WHERE id = ?');
}
$st->execute([$selId]);
$structNom = (string)($st->fetchColumn() ?: 'structure');

$hasResp = false;
try {
    $chk = $pdo->query(
        "SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'structure_membres' AND COLUMN_NAME = 'resp_evenement' LIMIT 1"
    );
    $hasResp = (bool)$chk->fetchColumn();
} catch (Throwable $e) {
    $hasResp = false;
}

$respSelect = $hasResp
    ? ', sm.resp_evenement, sm.resp_partenariat, sm.resp_communication, sm.resp_tresorerie'
    : '';

$stmt = $pdo->prepare(
    "SELECT sm.role_in_struct, sm.created_at,
            u.username, u.nom, u.prenom, u.email, u.ecole, u.promotion
            $respSelect
     FROM structure_membres sm
     JOIN users u ON u.id = sm.user_id
     WHERE sm.structure_type = ? AND sm.structure_id = ? AND sm.statut = 'actif'
     ORDER BY FIELD(sm.role_in_struct, 'admin', 'membre', 'adherent'), u.nom, u.prenom"
);
$stmt->execute([$selType, $selId]);
$membres = $stmt->fetchAll(PDO::FETCH_ASSOC);

$headers = ['Prénom', 'Nom', 'Identifiant', 'Email', 'École', 'Promotion', 'Niveau', 'Membre depuis'];
if ($hasResp) {
    $headers = array_merge($headers, ['Resp. événements', 'Resp. partenariats', 'Resp. communication', 'Resp. trésorerie']);
}

$rows = [];
foreach ($membres as $mb) {
    $row = [
        $mb['prenom'] ?? '',
        $mb['nom'] ?? '',
        $mb['username'] ?? '',
        $mb['email'] ?? '',
        $mb['ecole'] ?? '',
        $mb['promotion'] ?? '',
        corpo_spreadsheet_role_label((string)($mb['role_in_struct'] ?? '')),
        $mb['created_at'] ?? '',
    ];
    if ($hasResp) {
        $row[] = !empty($mb['resp_evenement']) ? 'Oui' : 'Non';
        $row[] = !empty($mb['resp_partenariat']) ? 'Oui' : 'Non';
        $row[] = !empty($mb['resp_communication']) ? 'Oui' : 'Non';
        $row[] = !empty($mb['resp_tresorerie']) ? 'Oui' : 'Non';
    }
    $rows[] = $row;
}

$safeName = preg_replace('/[^a-zA-Z0-9_-]+/', '-', mb_strtolower($structNom)) ?: 'membres';
$filename = 'membres-' . $safeName . '-' . date('Ymd');

corpo_spreadsheet_send($filename, $headers, $rows, $format);
