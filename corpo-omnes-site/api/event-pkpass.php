<?php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/billetterie.php';
if (file_exists(__DIR__ . '/../includes/env.php')) require_once __DIR__ . '/../includes/env.php';

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Extension PHP 'zip' indisponible - impossible de générer le pkpass.\nActive ext-zip dans php.ini.");
}

$certPathCheck = getenv('APPLE_WALLET_CERT_PATH');
$wwdrPathCheck = getenv('APPLE_WALLET_WWDR_PATH');
if (!$certPathCheck || !@file_exists($certPathCheck) || !$wwdrPathCheck || !@file_exists($wwdrPathCheck) || !function_exists('openssl_pkcs12_read')) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    exit(
        "Apple Wallet n'est pas configuré sur ce serveur.\n\n" .
        "Pour activer cette fonctionnalité, l'administrateur doit :\n" .
        "  1. S'inscrire au Apple Developer Program (99 EUR/an)\n" .
        "  2. Créer un Pass Type ID + certificat\n" .
        "  3. Configurer .env avec APPLE_WALLET_CERT_PATH, APPLE_WALLET_WWDR_PATH, etc.\n\n" .
        "En attendant, utilise le bouton 'Imprimer / PDF' ou ajoute l'évenement à ton agenda."
    );
}

$insId = (int)($_GET['ins'] ?? 0);
$tok   = (string)($_GET['t'] ?? '');
if (!$insId || $tok === '') { http_response_code(400); exit('Paramètres manquants.'); }

$st = $pdo->prepare(
  "SELECT i.*, e.titre, e.date, e.heure, e.heure_fin, e.date_fin, e.lieu, e.campus, e.organisateur, e.icon
     FROM inscriptions_evenement i
     JOIN evenements e ON e.id = i.evenement_id
    WHERE i.id = ? AND i.qr_token = ?
    LIMIT 1"
);
$st->execute([$insId, $tok]);
$row = $st->fetch();
if (!$row) { http_response_code(404); exit('Billet introuvable.'); }

$tz       = new DateTimeZone('Europe/Paris');
$dtStart  = new DateTime($row['date'] . ' ' . ($row['heure'] ?: '00:00'), $tz);
$dtEnd    = new DateTime(($row['date_fin'] ?: $row['date']) . ' ' . ($row['heure_fin'] ?: ($row['heure'] ? date('H:i', strtotime($row['heure'] . ' +2 hours')) : '23:59')), $tz);
$relevantDate = $dtStart->format('c');

$name = trim(($row['prenom'] ?? '') . ' ' . ($row['nom'] ?? '')) ?: ($row['email'] ?? '');

$passTypeId = getenv('APPLE_WALLET_PASS_TYPE_ID') ?: 'pass.fr.corpoomnes.event';
$teamId     = getenv('APPLE_WALLET_TEAM_ID')     ?: 'TEAMID00000';
$orgName    = 'Corpo Omnes Lyon';
$serial     = 'evt-' . (int)$row['evenement_id'] . '-ins-' . (int)$row['id'];

$pass = [
    'formatVersion'    => 1,
    'passTypeIdentifier' => $passTypeId,
    'teamIdentifier'   => $teamId,
    'organizationName' => $orgName,
    'serialNumber'     => $serial,
    'description'      => 'Billet - ' . $row['titre'],
    'logoText'         => 'Corpo Omnes',
    'foregroundColor'  => 'rgb(255, 255, 255)',
    'backgroundColor'  => 'rgb(93, 2, 130)',
    'labelColor'       => 'rgb(255, 255, 255)',
    'relevantDate'     => $relevantDate,
    'eventTicket'      => [
        'primaryFields'   => [[
            'key'   => 'event',
            'label' => 'ÉVÉNEMENT',
            'value' => $row['titre'],
        ]],
        'secondaryFields' => [[
            'key'   => 'date',
            'label' => 'DATE',
            'value' => $dtStart->format('d/m/Y'),
            'dateStyle' => 'PKDateStyleMedium',
        ], [
            'key'     => 'time',
            'label'   => 'HEURE',
            'value'   => $row['heure'] ?: '-',
        ]],
        'auxiliaryFields' => [[
            'key'   => 'location',
            'label' => 'LIEU',
            'value' => $row['lieu'] ?: '-',
        ], [
            'key'   => 'name',
            'label' => 'TITULAIRE',
            'value' => $name,
        ]],
        'backFields'      => [[
            'key'   => 'organisateur',
            'label' => 'Organisateur',
            'value' => $row['organisateur'] ?: $orgName,
        ], [
            'key'   => 'ticket-id',
            'label' => 'Référence',
            'value' => substr($row['qr_token'] ?? '', 0, 12),
        ], [
            'key'   => 'help',
            'label' => 'Aide',
            'value' => 'Présente le QR à l\'entrée. Conserve ce billet jusqu\'à la fin de l\'événement.',
        ]],
    ],
    'barcodes' => [[
        'message'         => billet_qr_payload($row['qr_token']),
        'format'          => 'PKBarcodeFormatQR',
        'messageEncoding' => 'iso-8859-1',
        'altText'         => substr($row['qr_token'], 0, 8),
    ]],
];

$passJson = json_encode($pass, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

$pngBlank29  = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAB0AAAAdCAYAAABWk2cPAAAAGklEQVR42mNkYGD4z0AEYBxVSF+FjKMKBwAALPgB/RZobgAAAAAASUVORK5CYII=');
$pngBlank58  = base64_decode('iVBORw0KGgoAAAANSUhEUgAAADoAAAA6CAYAAADhu0ooAAAAH0lEQVR42u3BAQ0AAADCoPdPbQ43oAAAAAAAAAAAAOA1HuQAATQTr7gAAAAASUVORK5CYII=');

$files = [
    'pass.json'    => $passJson,
    'icon.png'     => $pngBlank29,
    'icon@2x.png'  => $pngBlank58,
    'logo.png'     => $pngBlank29,
    'logo@2x.png'  => $pngBlank58,
];

$manifest = [];
foreach ($files as $name => $content) {
    $manifest[$name] = sha1($content);
}
$manifestJson = json_encode($manifest, JSON_PRETTY_PRINT);
$files['manifest.json'] = $manifestJson;

$certPath = getenv('APPLE_WALLET_CERT_PATH');
$certPass = getenv('APPLE_WALLET_CERT_PASS') ?: '';
$wwdrPath = getenv('APPLE_WALLET_WWDR_PATH');

$signature = null;
if ($certPath && file_exists($certPath) && $wwdrPath && file_exists($wwdrPath) && function_exists('openssl_pkcs7_sign')) {

    $tmpManifest = tempnam(sys_get_temp_dir(), 'manifest_');
    $tmpSig      = tempnam(sys_get_temp_dir(), 'sig_');
    file_put_contents($tmpManifest, $manifestJson);
    $p12 = file_get_contents($certPath);
    $cert = [];
    if (openssl_pkcs12_read($p12, $cert, $certPass)) {
        $ok = openssl_pkcs7_sign(
            $tmpManifest, $tmpSig,
            $cert['cert'], [$cert['pkey'], $certPass],
            [], PKCS7_BINARY | PKCS7_DETACHED,
            $wwdrPath
        );
        if ($ok) {
            $raw = file_get_contents($tmpSig);

            $raw = preg_replace('/-----[A-Z ]+-----/', '', $raw);
            $raw = preg_replace('/\s+/', '', $raw);
            $signature = base64_decode($raw);
        }
    }
    @unlink($tmpManifest); @unlink($tmpSig);
}
if ($signature !== null) $files['signature'] = $signature;

$tmpZip = tempnam(sys_get_temp_dir(), 'pkpass_');
$zip = new ZipArchive();
if ($zip->open($tmpZip, ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    exit('Impossible de créer le pkpass.');
}
foreach ($files as $name => $content) {
    $zip->addFromString($name, $content);
}
$zip->close();

$body = file_get_contents($tmpZip);
@unlink($tmpZip);

header('Content-Type: application/vnd.apple.pkpass');
header('Content-Disposition: attachment; filename="billet-' . $serial . '.pkpass"');
header('Content-Length: ' . strlen($body));
header('Cache-Control: no-store');
echo $body;
