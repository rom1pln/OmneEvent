<?php
declare(strict_types=1);

function corpo_spreadsheet_xml(string $value): string
{
    $value = str_replace(["\0", "\r"], '', $value);
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function corpo_spreadsheet_col(int $colIndex): string
{
    $colIndex++;
    $letters = '';
    while ($colIndex > 0) {
        $colIndex--;
        $letters = chr(65 + ($colIndex % 26)) . $letters;
        $colIndex = intdiv($colIndex, 26);
    }
    return $letters;
}

function corpo_spreadsheet_cell_xml(int $rowNum, int $colIndex, mixed $value): string
{
    $ref = corpo_spreadsheet_col($colIndex) . $rowNum;
    if ($value === null || $value === '') {
        return '<c r="' . $ref . '"/>';
    }
    if (is_bool($value)) {
        $value = $value ? '1' : '0';
    }
    if (is_int($value) || is_float($value)) {
        return '<c r="' . $ref . '"><v>' . $value . '</v></c>';
    }
    $str = trim((string)$value);
    if ($str !== '' && is_numeric(str_replace([' ', ','], ['', '.'], $str))) {
        $norm = str_replace([' ', ','], ['', '.'], $str);
        if (preg_match('/^-?\d+(\.\d+)?$/', $norm)) {
            return '<c r="' . $ref . '"><v>' . corpo_spreadsheet_xml($norm) . '</v></c>';
        }
    }
    return '<c r="' . $ref . '" t="inlineStr"><is><t>' . corpo_spreadsheet_xml($str) . '</t></is></c>';
}

function corpo_spreadsheet_send_csv(string $basename, array $headers, array $rows): void
{
    $safe = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $basename) ?: 'export';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $safe . '.csv"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    $out = fopen('php://output', 'w');
    if ($out === false) {
        exit;
    }
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $headers, ';');
    foreach ($rows as $row) {
        fputcsv($out, array_map(static fn($v) => $v === null ? '' : (string)$v, $row), ';');
    }
    fclose($out);
    exit;
}

function corpo_spreadsheet_send_xlsx(string $basename, array $headers, array $rows): void
{
    if (!class_exists('ZipArchive')) {
        corpo_spreadsheet_send_csv($basename, $headers, $rows);
        return;
    }

    $sheetRows = '';
    $sheetRows .= '<row r="1">';
    foreach ($headers as $ci => $h) {
        $sheetRows .= corpo_spreadsheet_cell_xml(1, $ci, $h);
    }
    $sheetRows .= '</row>';

    $r = 2;
    foreach ($rows as $row) {
        $sheetRows .= '<row r="' . $r . '">';
        foreach ($headers as $ci => $_) {
            $sheetRows .= corpo_spreadsheet_cell_xml($r, $ci, $row[$ci] ?? '');
        }
        $sheetRows .= '</row>';
        $r++;
    }

    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetData>' . $sheetRows . '</sheetData></worksheet>';

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>';

    $relsRoot = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';

    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Export" sheetId="1" r:id="rId1"/></sheets></workbook>';

    $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';

    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="1"><font/></fonts><fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
        . '</styleSheet>';

    $tmp = tempnam(sys_get_temp_dir(), 'corpo_xlsx_');
    if ($tmp === false) {
        corpo_spreadsheet_send_csv($basename, $headers, $rows);
        return;
    }
    $zipPath = $tmp . '.xlsx';
    @unlink($tmp);

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        corpo_spreadsheet_send_csv($basename, $headers, $rows);
        return;
    }
    $zip->addFromString('[Content_Types].xml', $contentTypes);
    $zip->addFromString('_rels/.rels', $relsRoot);
    $zip->addFromString('xl/workbook.xml', $workbook);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zip->addFromString('xl/styles.xml', $styles);
    $zip->close();

    $safe = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $basename) ?: 'export';
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $safe . '.xlsx"');
    header('Content-Length: ' . (string)filesize($zipPath));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    readfile($zipPath);
    @unlink($zipPath);
    exit;
}

function corpo_spreadsheet_send(string $basename, array $headers, array $rows, string $format = 'xlsx'): void
{
    $format = strtolower($format);
    if ($format === 'csv') {
        corpo_spreadsheet_send_csv($basename, $headers, $rows);
        return;
    }
    corpo_spreadsheet_send_xlsx($basename, $headers, $rows);
}

function corpo_spreadsheet_role_label(string $role): string
{
    return match ($role) {
        'admin' => 'Bureau',
        'membre' => 'Membre',
        'adherent' => 'Adhérent',
        default => $role,
    };
}

function corpo_spreadsheet_tx_type_label(string $type): string
{
    return $type === 'recette' ? 'Recette' : ($type === 'depense' ? 'Dépense' : $type);
}
