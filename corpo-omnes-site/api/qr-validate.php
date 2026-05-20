<?php
// valide un QR code de billet
// POST { payload, evenement_id } → JSON { ok, msg, already?, participant? }

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/billetterie.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Non authentifié']); exit;
}

$payload = trim((string)($_POST['payload'] ?? ''));
$evtId   = (int)($_POST['evenement_id'] ?? 0);
if (!$payload) {
    echo json_encode(['ok'=>false,'msg'=>'Aucune donnée fournie']); exit;
}

// Vérifie que l'admin a le droit de scanner cet event
$ev = $pdo->prepare("SELECT * FROM evenements WHERE id=?");
$ev->execute([$evtId]);
$event = $ev->fetch();
if (!$event) { echo json_encode(['ok'=>false,'msg'=>'Événement introuvable']); exit; }

$canScan = isAdminCorpo()
    || ($event['structure_type'] === 'asso'  && in_array((int)$event['structure_id'], getManagedAssoIds($pdo), true))
    || ($event['structure_type'] === 'sport' && in_array((int)$event['structure_id'], getManagedSportIds($pdo), true));
if (!$canScan) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'msg'=>'Pas les droits sur cet événement']); exit;
}

// Recherche du billet (préfixe limité à cet event pour réduire l'ambiguïté)
$look = billet_scan_lookup($pdo, $payload, $evtId);
if (!$look['ok']) {
    echo json_encode(['ok'=>false,'msg'=>$look['msg']]); exit;
}
$ins = $look['inscription'];

if ((int)$ins['evenement_id'] !== $evtId) {
    echo json_encode([
        'ok'  => false,
        'msg' => 'Ce billet appartient à un autre événement : "' . ($ins['titre'] ?? '') . '"',
    ]); exit;
}

// Marquage du scan
$res = billet_scan_mark($pdo, (int)$ins['id'], (int)currentUserId());
$participant = $res['inscription'] ?: $ins;

echo json_encode([
    'ok'       => (bool)$res['ok'],
    'already'  => (bool)($res['already'] ?? false),
    'msg'      => $res['msg'],
    'participant' => [
        'id'      => (int)$participant['id'],
        'nom'     => $participant['nom'] ?? '',
        'prenom'  => $participant['prenom'] ?? '',
        'email'   => $participant['email'] ?? '',
        'statut'  => $participant['statut'],
    ],
]);
