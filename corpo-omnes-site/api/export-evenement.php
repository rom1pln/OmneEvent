<?php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/spreadsheet-export.php';

if (!isLoggedIn()) {
    http_response_code(401);
    exit('Authentification requise');
}

$id     = (int)($_GET['id'] ?? 0);
$type   = (string)($_GET['type'] ?? 'participants');
$format = (string)($_GET['format'] ?? 'xlsx');

if (!$id) {
    http_response_code(400);
    exit('Identifiant manquant');
}

$ev = $pdo->prepare('SELECT * FROM evenements WHERE id = ?');
$ev->execute([$id]);
$event = $ev->fetch(PDO::FETCH_ASSOC);
if (!$event) {
    http_response_code(404);
    exit('Événement introuvable');
}

$canExport = isAdminCorpo()
    || ($event['structure_type'] === 'asso' && in_array((int)$event['structure_id'], getManagedAssoIds($pdo), true))
    || ($event['structure_type'] === 'sport' && in_array((int)$event['structure_id'], getManagedSportIds($pdo), true));
if (!$canExport) {
    http_response_code(403);
    exit('Accès refusé');
}

$safeName = preg_replace('/[^a-zA-Z0-9_-]+/', '-', mb_strtolower((string)$event['titre'])) ?: 'evenement';
$basename = $type . '-' . $safeName . '-' . date('Ymd');

if ($type === 'demandes') {
    $headers = ['Date', 'Email', 'Prénom', 'Nom', 'École', 'Message', 'Statut'];
    $st = $pdo->prepare(
        'SELECT * FROM demandes_renseignement_evenement WHERE evenement_id = ? ORDER BY created_at DESC'
    );
    $st->execute([$id]);
    $rows = [];
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $rows[] = [
            $r['created_at'] ?? '',
            $r['email'] ?? '',
            $r['prenom'] ?? '',
            $r['nom'] ?? '',
            $r['ecole'] ?? '',
            $r['message'] ?? '',
            $r['statut'] ?? '',
        ];
    }
} else {
    $headers = [
        'ID inscription', 'Statut', 'Email', 'Prénom', 'Nom', 'École', 'Promotion',
        'Prix payé (€)', 'Paiement', 'Token QR', 'Scanné le', 'Inscrit le',
    ];
    $st = $pdo->prepare(
        "SELECT i.*, u.email AS u_email, u.nom AS u_nom, u.prenom AS u_prenom,
                u.ecole AS u_ecole, u.promotion AS u_promo
           FROM inscriptions_evenement i
           LEFT JOIN users u ON u.id = i.user_id
          WHERE i.evenement_id = ?
          ORDER BY FIELD(i.statut, 'confirme','en_attente','liste_attente','annule','refuse','rembourse'), i.id"
    );
    $st->execute([$id]);
    $rows = [];
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $rows[] = [
            $r['id'],
            $r['statut'] ?? '',
            $r['email'] ?: ($r['u_email'] ?? ''),
            $r['prenom'] ?: ($r['u_prenom'] ?? ''),
            $r['nom'] ?: ($r['u_nom'] ?? ''),
            $r['u_ecole'] ?? '',
            $r['u_promo'] ?? '',
            $r['prix_paye'] ?? '',
            $r['paiement_statut'] ?? '',
            $r['qr_token'] ?? '',
            $r['qr_scanned_at'] ?? '',
            $r['created_at'] ?? '',
        ];
    }
}

corpo_spreadsheet_send($basename, $headers, $rows, $format);
