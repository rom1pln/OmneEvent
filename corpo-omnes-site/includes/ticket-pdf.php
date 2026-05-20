<?php

if (!class_exists('FPDF', false)) {
    if (!defined('FPDF_FONTPATH')) {
        define('FPDF_FONTPATH', __DIR__ . '/lib/fpdf-fonts/');
    }
    require_once __DIR__ . '/lib/fpdf.php';
}

if (!function_exists('corpo_ticket_pdf_utf8')) {

    function corpo_ticket_pdf_utf8(string $s): string {
        if ($s === '') return '';
        $out = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $s);
        return $out === false ? $s : $out;
    }
}

if (!function_exists('corpo_ticket_pdf_qr_png')) {

    function corpo_ticket_pdf_qr_png(string $payload, int $pixels = 600): ?string {
        if (!function_exists('imagecreate') || !function_exists('imagepng')) {
            return null;
        }
        if (!class_exists('QRCode', false)) {
            require_once __DIR__ . '/lib/qrcode.php';
        }
        try {
            $qr = QRCode::getMinimumQRCode($payload, QR_ERROR_CORRECT_LEVEL_H);
            $modules = $qr->getModuleCount();
            $margin  = 2;
            $total   = $modules + 2 * $margin;
            $cell    = max(1, (int)floor($pixels / $total));
            $side    = $cell * $total;

            $img = imagecreate($side, $side);
            $white = imagecolorallocate($img, 255, 255, 255);
            $black = imagecolorallocate($img, 0, 0, 0);
            imagefilledrectangle($img, 0, 0, $side, $side, $white);

            for ($r = 0; $r < $modules; $r++) {
                for ($c = 0; $c < $modules; $c++) {
                    if ($qr->isDark($r, $c)) {
                        $x = ($c + $margin) * $cell;
                        $y = ($r + $margin) * $cell;
                        imagefilledrectangle($img, $x, $y, $x + $cell - 1, $y + $cell - 1, $black);
                    }
                }
            }
            ob_start();
            imagepng($img);
            $data = ob_get_clean();
            imagedestroy($img);
            return $data !== '' ? $data : null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

function corpo_ticket_pdf_data(array $billet, array $event): ?string {
    if (!class_exists('FPDF', false)) return null;

    $bid   = (int)($billet['id'] ?? 0);
    $name  = trim((string)($billet['prenom'] ?? '') . ' ' . (string)($billet['nom'] ?? ''))
           ?: (string)($billet['email'] ?? '');
    $stat  = (string)($billet['statut'] ?? '');
    $statLabel = [
        'confirme'      => 'Confirme',
        'liste_attente' => "Liste d'attente",
        'en_attente'    => 'En attente',
    ][$stat] ?? $stat;

    try {
        $dateFmt = !empty($event['date']) ? (new DateTime($event['date']))->format('l j F Y') : '';
    } catch (Throwable $e) { $dateFmt = (string)($event['date'] ?? ''); }
    $titre  = (string)($event['titre'] ?? 'Evenement');
    $lieu   = (string)($event['lieu']  ?? '');
    $heure  = (string)($event['heure'] ?? '');
    $codeShort = !empty($billet['qr_token']) ? strtoupper(substr((string)$billet['qr_token'], 0, 8)) : '';

    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->SetMargins(0, 0, 0);
    $pdf->SetAutoPageBreak(false);
    $pdf->AddPage();
    $pdf->SetTitle('Billet ' . $bid, true);
    $pdf->SetAuthor('Corpo Omnes Lyon', true);

    $pdf->SetFillColor(93, 2, 130);
    $pdf->Rect(0, 0, 210, 32, 'F');
    $pdf->SetFillColor(139, 47, 201);
    $pdf->Rect(0, 32, 210, 2, 'F');
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Helvetica', 'B', 22);
    $pdf->SetXY(18, 8);
    $pdf->Cell(120, 10, corpo_ticket_pdf_utf8('CORPO OMNES'), 0, 2, 'L');
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetXY(18, 20);
    $pdf->Cell(120, 6, corpo_ticket_pdf_utf8('LYON  ·  BILLET ELECTRONIQUE'), 0, 0, 'L');
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->SetXY(150, 14);
    $pdf->Cell(48, 8, corpo_ticket_pdf_utf8('N° ' . $bid), 0, 0, 'R');

    $qrPngTmp = null;
    if (!empty($billet['qr_token'])) {

        if (!function_exists('billet_qr_payload')) {
            require_once __DIR__ . '/billetterie.php';
        }
        $qrData = function_exists('billet_qr_payload')
            ? billet_qr_payload((string)$billet['qr_token'])
            : (string)$billet['qr_token'];
        $png = corpo_ticket_pdf_qr_png($qrData, 600);
        if ($png !== null) {
            $qrPngTmp = tempnam(sys_get_temp_dir(), 'qr_') . '.png';
            file_put_contents($qrPngTmp, $png);

            $qrSize = 80;
            $qrX    = (210 - $qrSize) / 2;
            $pdf->Image($qrPngTmp, $qrX, 50, $qrSize, $qrSize, 'PNG');
        }
    }

    $pdf->SetTextColor(26, 0, 64);
    $pdf->SetFont('Helvetica', 'B', 18);
    $pdf->SetXY(0, 140);
    $pdf->Cell(210, 9, corpo_ticket_pdf_utf8($titre), 0, 0, 'C');

    $pdf->SetFont('Helvetica', '', 11);
    $pdf->SetTextColor(93, 2, 130);
    $pdf->SetXY(0, 150);
    $line = ucfirst($dateFmt) . ($heure !== '' ? '  -  ' . $heure : '');
    $pdf->Cell(210, 6, corpo_ticket_pdf_utf8($line), 0, 0, 'C');

    if ($lieu !== '') {
        $pdf->SetTextColor(70, 70, 70);
        $pdf->SetXY(0, 157);
        $pdf->Cell(210, 6, corpo_ticket_pdf_utf8($lieu), 0, 0, 'C');
    }

    $pdf->SetDrawColor(196, 181, 253);
    $pdf->SetLineWidth(0.3);
    $y = 170;
    for ($x = 18; $x < 192; $x += 4) {
        $pdf->Line($x, $y, $x + 2, $y);
    }

    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetTextColor(93, 2, 130);
    $pdf->SetXY(18, 178);
    $pdf->Cell(80, 5, corpo_ticket_pdf_utf8('PARTICIPANT'), 0, 0, 'L');
    $pdf->SetXY(110, 178);
    $pdf->Cell(80, 5, corpo_ticket_pdf_utf8('STATUT'), 0, 0, 'L');

    $pdf->SetFont('Helvetica', 'B', 13);
    $pdf->SetTextColor(26, 0, 64);
    $pdf->SetXY(18, 184);
    $pdf->Cell(80, 7, corpo_ticket_pdf_utf8($name ?: '-'), 0, 0, 'L');
    $pdf->SetXY(110, 184);
    $pdf->Cell(80, 7, corpo_ticket_pdf_utf8($statLabel ?: '-'), 0, 0, 'L');

    if (!empty($billet['email'])) {
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(120, 120, 120);
        $pdf->SetXY(18, 193);
        $pdf->Cell(180, 5, corpo_ticket_pdf_utf8((string)$billet['email']), 0, 0, 'L');
    }

    if ($codeShort !== '') {
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetTextColor(93, 2, 130);
        $pdf->SetXY(18, 205);
        $pdf->Cell(80, 5, corpo_ticket_pdf_utf8('CODE'), 0, 0, 'L');
        $pdf->SetFont('Courier', '', 11);
        $pdf->SetTextColor(26, 0, 64);
        $pdf->SetXY(18, 211);
        $pdf->Cell(180, 6, corpo_ticket_pdf_utf8($codeShort . '...'), 0, 0, 'L');
    }

    $pdf->SetDrawColor(232, 217, 245);
    $pdf->Line(18, 268, 192, 268);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetTextColor(93, 2, 130);
    $pdf->SetXY(0, 273);
    $pdf->Cell(210, 5, corpo_ticket_pdf_utf8('Presente ce billet (ou le QR ci-dessus) a l\'entree de l\'evenement.'), 0, 0, 'C');
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetTextColor(140, 140, 140);
    $pdf->SetXY(0, 280);
    $pdf->Cell(210, 5, corpo_ticket_pdf_utf8('Corpo Omnes Lyon  -  www.corpoomnes.fr  -  Billet electronique'), 0, 0, 'C');

    $out = $pdf->Output('S');

    if ($qrPngTmp && is_file($qrPngTmp)) {
        @unlink($qrPngTmp);
    }
    return $out;
}
