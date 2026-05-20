<?php
// génère un .ics pour un événement (compatible Apple/Google/Outlook)
// GET ?id=<event_id>

define('CORPO_API_PLAIN', true);

if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

require_once __DIR__ . '/../includes/db.php';

/**
 * @return never
 */
function ics_fail(int $code, string $message): void
{
    if (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo $message;
    exit;
}

try {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        ics_fail(400, 'Missing id.');
    }

    $st = $pdo->prepare("SELECT * FROM evenements WHERE id=? AND statut='publie'");
    $st->execute([$id]);
    $ev = $st->fetch();
    if (!$ev) {
        ics_fail(404, 'Event not found.');
    }

    function ics_escape(string $s): string
    {
        return str_replace(["\\", "\n", "\r", ',', ';'], ["\\\\", "\\n", '', "\\,", "\\;"], $s);
    }

    function ics_fold(string $line): string
    {
        $out = '';
        $len = strlen($line);
        $i = 0;
        while ($i < $len) {
            $chunk = substr($line, $i, $i === 0 ? 75 : 74);
            $out .= ($i === 0 ? '' : ' ') . $chunk . "\r\n";
            $i += $i === 0 ? 75 : 74;
        }
        return $out;
    }

    /** "20h00", "20:00", "20h" → "20:00:00" */
    function ics_normalize_heure(?string $heure): string
    {
        $h = trim((string)$heure);
        if ($h === '') {
            return '00:00:00';
        }
        if (preg_match('/^(\d{1,2})h(\d{2})?$/i', $h, $m)) {
            $hh = (int)$m[1];
            $mm = isset($m[2]) && $m[2] !== '' ? (int)$m[2] : 0;
            return sprintf('%02d:%02d:00', $hh, $mm);
        }
        if (preg_match('/^(\d{1,2}):(\d{2})/', $h, $m)) {
            return sprintf('%02d:%02d:00', (int)$m[1], (int)$m[2]);
        }
        $ts = strtotime($h);
        if ($ts !== false) {
            return date('H:i:s', $ts);
        }
        return '00:00:00';
    }

    function ics_datetime(string $date, ?string $heure, string $tzName = 'Europe/Paris'): string
    {
        $tz = new DateTimeZone($tzName);
        $dt = new DateTime($date . ' ' . ics_normalize_heure($heure), $tz);
        $dt->setTimezone(new DateTimeZone('UTC'));
        return $dt->format('Ymd\THis\Z');
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/api/event-ics.php'));
    $siteRoot = preg_replace('#/api$#', '', $scriptDir) ?: '';
    $url = $scheme . '://' . $host . rtrim($siteRoot, '/') . '/evenement.php?id=' . $id;

    $start = ics_datetime($ev['date'], $ev['heure'] ?? null);
    $endDate = !empty($ev['date_fin']) ? $ev['date_fin'] : $ev['date'];
    $endHour = $ev['heure_fin'] ?? null;
    if ($endHour === null || $endHour === '') {
        if (!empty($ev['heure'])) {
            $tz = new DateTimeZone('Europe/Paris');
            $dtStart = new DateTime($ev['date'] . ' ' . ics_normalize_heure($ev['heure']), $tz);
            $dtStart->modify('+2 hours');
            $endHour = $dtStart->format('H:i');
        } else {
            $endHour = '23:59';
        }
    }
    $end = ics_datetime($endDate, $endHour);

    $uidHost = preg_replace('/[^a-z0-9.\-]/i', '', $host) ?: 'corpoomnes.local';
    $uid = 'evt-' . $id . '@' . $uidHost;
    $dtstamp = gmdate('Ymd\THis\Z');

    $summary = ics_escape((string)$ev['titre']);
    $descParts = [];
    if (!empty($ev['description'])) {
        $descParts[] = (string)$ev['description'];
    }
    $descParts[] = 'Organisé par : ' . ($ev['organisateur'] ?? '');
    $descParts[] = 'Plus d\'infos : ' . $url;
    $description = ics_escape(implode("\n\n", $descParts));
    $location = ics_escape(trim(($ev['lieu'] ?? '') . ($ev['campus'] ? ' - ' . $ev['campus'] : '')));

    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Corpo Omnes//Events//FR',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'BEGIN:VEVENT',
        'UID:' . $uid,
        'DTSTAMP:' . $dtstamp,
        'DTSTART:' . $start,
        'DTEND:' . $end,
        'SUMMARY:' . $summary,
        'DESCRIPTION:' . $description,
        'LOCATION:' . $location,
        'URL:' . $url,
        'STATUS:CONFIRMED',
        'TRANSP:OPAQUE',
        'BEGIN:VALARM',
        'TRIGGER:-PT1H',
        'ACTION:DISPLAY',
        'DESCRIPTION:' . $summary,
        'END:VALARM',
        'END:VEVENT',
        'END:VCALENDAR',
    ];

    $body = '';
    foreach ($lines as $l) {
        $body .= ics_fold($l);
    }

    $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower((string)$ev['titre']));
    $slug = trim($slug, '-') ?: 'evenement';
    $filename = 'corpo-omnes-' . $slug . '.ics';

    if (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: text/calendar; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Transfer-Encoding: binary');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');

    echo $body;
    exit;
} catch (Throwable $e) {
    error_log('[event-ics] ' . $e->getMessage());
    ics_fail(500, 'ICS generation failed.');
}
