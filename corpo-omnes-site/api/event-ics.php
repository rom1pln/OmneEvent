<?php

require_once __DIR__ . '/../includes/db.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { http_response_code(400); exit('Missing id.'); }

$st = $pdo->prepare("SELECT * FROM evenements WHERE id=? AND statut='publie'");
$st->execute([$id]);
$ev = $st->fetch();
if (!$ev) { http_response_code(404); exit('Event not found.'); }

function ics_escape(string $s): string {

    $s = str_replace(["\\", "\n", "\r", ',', ';'], ["\\\\", "\\n", '', "\\,", "\\;"], $s);
    return $s;
}
function ics_fold(string $line): string {

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
function ics_datetime(string $date, ?string $heure): string {

    $tz = new DateTimeZone('Europe/Paris');
    $dt = new DateTime($date . ' ' . ($heure ?: '00:00'), $tz);
    $dt->setTimezone(new DateTimeZone('UTC'));
    return $dt->format('Ymd\THis\Z');
}

$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base     = $scheme . '://' . $host;
$url      = $base . '/evenement.php?id=' . $id;

$start = ics_datetime($ev['date'], $ev['heure'] ?? null);
$endDate = $ev['date_fin'] ?: $ev['date'];
$endHour = $ev['heure_fin'] ?: (
    !empty($ev['heure'])
        ? date('H:i', strtotime($ev['heure'] . ' +2 hours'))
        : '23:59'
);
$end = ics_datetime($endDate, $endHour);

$uid = 'evt-' . $id . '@' . preg_replace('/[^a-z0-9.\-]/i', '', $host);
$dtstamp = gmdate('Ymd\THis\Z');

$summary = ics_escape($ev['titre']);
$descParts = [];
if (!empty($ev['description'])) $descParts[] = $ev['description'];
$descParts[] = 'Organisé par : ' . ($ev['organisateur'] ?? '');
$descParts[] = 'Plus d\'infos : ' . $url;
$description = ics_escape(implode("\n\n", $descParts));
$location = ics_escape(($ev['lieu'] ?? '') . ($ev['campus'] ? ' - ' . $ev['campus'] : ''));

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
    'DTEND:'   . $end,
    'SUMMARY:' . $summary,
    'DESCRIPTION:' . $description,
    'LOCATION:' . $location,
    'URL;VALUE=URI:' . $url,
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
foreach ($lines as $l) $body .= ics_fold($l);

$filename = 'event-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($ev['titre'])) . '.ics';
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: public, max-age=300');
echo $body;
