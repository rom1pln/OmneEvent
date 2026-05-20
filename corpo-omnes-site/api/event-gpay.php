<?php
/**
 * api/event-gpay.php - Pass Google Wallet (événement)
 *
 * Nécessite un compte Google Wallet API + clé de service JSON.
 * Sans configuration, on renvoie 503 (évite un lien mort / erreur brute).
 */
declare(strict_types=1);

if (file_exists(__DIR__ . '/../includes/env.php')) {
    require_once __DIR__ . '/../includes/env.php';
}

$issuer = (string)(getenv('GOOGLE_WALLET_ISSUER_ID') ?: '');
$keyPath = (string)(getenv('GOOGLE_WALLET_SERVICE_KEY_PATH') ?: '');

if ($issuer === '' || $keyPath === '' || !is_readable($keyPath)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    exit(
        "Google Wallet n'est pas configuré sur ce serveur.\n\n"
        . "Configurer GOOGLE_WALLET_ISSUER_ID et GOOGLE_WALLET_SERVICE_KEY_PATH dans .env.\n"
        . "En attendant, utilise le QR code du billet ou « Imprimer / PDF »."
    );
}

http_response_code(501);
header('Content-Type: text/plain; charset=utf-8');
exit(
    "Google Wallet : les variables d'environnement sont présentes mais la génération de pass\n"
    . "n'est pas encore implémentée dans cette version.\n"
);
