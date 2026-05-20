<?php

require_once __DIR__ . '/../includes/lib/qrcode.php';

$payload = (string)($_GET['p'] ?? '');
$size    = max(120, min(600, (int)($_GET['s'] ?? 280)));

if ($payload === '') {
    http_response_code(400);
    exit('Missing payload.');
}

header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=3600, immutable');

try {

    $qr = QRCode::getMinimumQRCode($payload, QR_ERROR_CORRECT_LEVEL_H);
    $modules = $qr->getModuleCount();

    $margin = 2;
    $total  = $modules + 2 * $margin;
    $cell   = $size / $total;

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size
       . '" viewBox="0 0 ' . $total . ' ' . $total . '" shape-rendering="crispEdges">';
    echo '<rect width="100%" height="100%" fill="#ffffff"/>';
    echo '<g fill="#000000">';
    for ($r = 0; $r < $modules; $r++) {
        for ($c = 0; $c < $modules; $c++) {
            if ($qr->isDark($r, $c)) {
                echo '<rect x="' . ($c + $margin) . '" y="' . ($r + $margin) . '" width="1" height="1"/>';
            }
        }
    }
    echo '</g></svg>';
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'QR generation error: ' . $e->getMessage();
}
