<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/billetterie.php';

const CAMPUS_ECOLES = [
    'Citroen'   => ['ESCE', 'INSEEC Bachelor', 'INSEEC BBA', 'INSEEC BTS', 'INSEEC GE', 'INSEEC MSc'],
    'Citadelle' => ['ECE', 'HEIP', 'Sup de Pub'],
];

const CAMPUS_INVITE_LABEL = [
    'Citroen'   => 'Citroën',
    'Citadelle' => 'Citadelle',
];

const TV_INSEEC_PROGRAMS = [
    'INSEEC Bachelor',
    'INSEEC BBA',
    'INSEEC BTS',
    'INSEEC GE',
    'INSEEC MSc',
];

function tv_fold(string $s): string
{
    $s = mb_strtolower(trim($s));
    return str_replace(['é', 'è', 'ê', 'ë', 'ù', 'û'], ['e', 'e', 'e', 'e', 'u', 'u'], $s);
}

function tv_normalize_ecole(?string $raw): ?string
{
    if ($raw === null || trim($raw) === '') {
        return null;
    }
    $folded = tv_fold($raw);
    $aliases = [
        'ece'        => 'ECE',
        'esce'       => 'ESCE',
        'heip'       => 'HEIP',
        'inseec'     => 'INSEEC',
        'sup de pub' => 'Sup de Pub',
        'supdepub'   => 'Sup de Pub',
        'sup-de-pub' => 'Sup de Pub',
    ];
    if (isset($aliases[$folded])) {
        return $aliases[$folded];
    }
    if ($folded === 'inseec' || str_starts_with($folded, 'inseec ')) {
        return 'INSEEC';
    }
    foreach (array_merge(['ECE', 'ESCE', 'HEIP', 'Sup de Pub'], TV_INSEEC_PROGRAMS) as $canonical) {
        if (tv_fold($canonical) === $folded) {
            return str_starts_with($canonical, 'INSEEC') ? 'INSEEC' : $canonical;
        }
    }
    return null;
}

function tv_normalize_hex_color(?string $raw): ?string
{
    $c = trim((string)$raw);
    if ($c === '') {
        return null;
    }
    if ($c[0] !== '#') {
        $c = '#' . $c;
    }
    if (preg_match('/^#[0-9A-Fa-f]{6}$/', $c)) {
        return $c;
    }
    if (preg_match('/^#([0-9A-Fa-f])([0-9A-Fa-f])([0-9A-Fa-f])$/', $c, $m)) {
        return '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
    }
    return null;
}

function tv_ecole_sql_filter(string $canonical, array &$params): string
{
    if ($canonical === 'INSEEC') {
        $parts = [];
        foreach (TV_INSEEC_PROGRAMS as $i => $prog) {
            $key = ':inseec' . $i;
            $parts[] = 'JSON_CONTAINS(e.ecoles_invitees, JSON_QUOTE(' . $key . '))';
            $params[$key] = $prog;
        }
        return '(' . implode(' OR ', $parts) . ' OR JSON_CONTAINS(e.ecoles_invitees, \'"Tous"\'))';
    }
    $params[':ecole'] = $canonical;
    return '(JSON_CONTAINS(e.ecoles_invitees, JSON_QUOTE(:ecole)) OR JSON_CONTAINS(e.ecoles_invitees, \'"Tous"\'))';
}

function tv_normalize_campus_key(?string $raw): ?string
{
    if ($raw === null || trim($raw) === '') {
        return null;
    }
    $s = trim($raw);
    $lower = strtolower(str_replace(['ë', 'é', 'è'], ['e', 'e', 'e'], $s));
    if ($lower === 'citroen') {
        return 'Citroen';
    }
    if ($lower === 'citadelle') {
        return 'Citadelle';
    }
    return isset(CAMPUS_ECOLES[$s]) ? $s : null;
}

function tv_spotlight_tier_norm(string $structureType, ?string $assoType): float {
    $st = strtolower($structureType);
    if ($st === 'corpo') {
        return 1.0;
    }
    if ($st === 'sport') {
        return 0.75;
    }
    if ($st === 'asso') {
        $raw = trim((string)$assoType);
        if ($raw === '') {
            return 0.25;
        }
        if (preg_match('/\b(BDE|BDS)\b/ui', $raw)) {
            return 0.5;
        }
        if (preg_match('/\b(fédération|federation|echofed|echo[\s-]*fed)\b/ui', $raw)) {
            return 0.5;
        }
        return 0.25;
    }
    return 0.25;
}

function tv_spotlight_days_until(string $dateYmd, DateTimeImmutable $today): int {
    try {
        $d = new DateTimeImmutable($dateYmd . ' 00:00:00');
    } catch (Throwable $e) {
        return 30;
    }
    if ($d < $today) {
        return 30;
    }
    return min(30, (int)$today->diff($d)->days);
}

function tv_spotlight_score_row(array $row, DateTimeImmutable $today): float {
    $tier = tv_spotlight_tier_norm((string)($row['structure_type'] ?? 'asso'), $row['asso_type'] ?? null);
    $days  = tv_spotlight_days_until((string)($row['date'] ?? ''), $today);
    $timeW = (30 - $days) / 30.0;
    return 0.55 * $tier + 0.45 * $timeW;
}

$spotlight = isset($_GET['mode']) && strtolower((string)$_GET['mode']) === 'spotlight';

$baseWhere = ["e.statut = 'publie'", "e.affichage_tv = 1"];
if (corpo_evt_has_visibilite_column($pdo)) {
    $baseWhere[] = "IFNULL(e.visibilite,'public') = 'public'";
}
$params    = [];
$filterSql = [];

$ecoleRaw      = trim((string)($_GET['ecole'] ?? ''));
$ecoleCanon    = $ecoleRaw !== '' ? tv_normalize_ecole($ecoleRaw) : null;
$campusKey     = tv_normalize_campus_key($_GET['campus'] ?? null);

if ($ecoleCanon !== null) {
    $filterSql[] = tv_ecole_sql_filter($ecoleCanon, $params);
} elseif ($ecoleRaw !== '') {

    $filterSql[] = '1 = 0';
} elseif ($campusKey !== null) {
    $ecoles = CAMPUS_ECOLES[$campusKey];
    $parts  = [];
    foreach ($ecoles as $i => $e) {
        $parts[]         = "JSON_CONTAINS(e.ecoles_invitees, JSON_QUOTE(:ce$i))";
        $params[":ce$i"] = $e;
    }
    $ecoleMatch = '(' . implode(' OR ', $parts) . " OR JSON_CONTAINS(e.ecoles_invitees, '\"Tous\"'))";

    $campusLbl = CAMPUS_INVITE_LABEL[$campusKey];
    $params[':campusLbl'] = $campusLbl;
    $campusMatch = "(
        e.campus_invites IS NULL
        OR e.campus_invites = ''
        OR e.campus_invites = '[]'
        OR JSON_CONTAINS(e.campus_invites, JSON_QUOTE(:campusLbl))
        OR JSON_CONTAINS(e.campus_invites, '\"Tous\"')
    )";

    $filterSql[] = "($ecoleMatch AND $campusMatch)";
}

$runQuery = static function (array $dateConstraints) use ($pdo, $baseWhere, $filterSql, $params): array {
    $where = array_merge($baseWhere, $dateConstraints, $filterSql);
    $sql   = "SELECT e.*, a.ecole AS organisateur_ecole, a.type AS asso_type, a.nom AS structure_nom,
                     COALESCE(
                       CASE WHEN e.structure_type IN ('asso', 'corpo') AND e.structure_id IS NOT NULL THEN a.color END,
                       CASE WHEN e.structure_type = 'sport' AND e.structure_id IS NOT NULL THEN s.couleur END,
                       (SELECT a2.color FROM associations a2
                         WHERE LOWER(TRIM(a2.nom)) = LOWER(TRIM(e.organisateur))
                         LIMIT 1)
                     ) AS organisateur_color
              FROM evenements e
              LEFT JOIN associations a
                ON e.structure_id = a.id AND e.structure_type IN ('asso', 'corpo')
              LEFT JOIN sports s ON e.structure_type = 'sport' AND e.structure_id = s.id
              WHERE " . implode(' AND ', $where) . "
              ORDER BY e.date ASC, e.heure ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
};

$rows = [];
try {
    if ($spotlight) {
        $rows = $runQuery(['e.date >= CURDATE()', 'e.date <= DATE_ADD(CURDATE(), INTERVAL 1 MONTH)']);
        if ($rows === []) {
            $rows = $runQuery(['e.date >= CURDATE()']);
        }
    } else {
        $rows = $runQuery([]);
    }

    $today = new DateTimeImmutable('today');

    if ($spotlight) {
        usort($rows, static function (array $a, array $b) use ($today): int {
            $sa = tv_spotlight_score_row($a, $today);
            $sb = tv_spotlight_score_row($b, $today);
            if (abs($sa - $sb) < 1e-9) {
                $cmp = strcmp((string)($a['date'] ?? ''), (string)($b['date'] ?? ''));
                return $cmp !== 0 ? $cmp : ((int)($a['id'] ?? 0) - (int)($b['id'] ?? 0));
            }
            return $sb <=> $sa;
        });
    }

    $events = array_map(static function (array $ev) use ($spotlight, $today): array {
        $ecolesJ = $ev['ecoles_invitees'] ?? null;
        $campusJ = $ev['campus_invites'] ?? null;
        $ecoles = is_string($ecolesJ) ? (json_decode($ecolesJ, true) ?: ['Tous']) : (is_array($ecolesJ) ? $ecolesJ : ['Tous']);
        $campus = is_string($campusJ) ? (json_decode($campusJ, true) ?: ['Tous']) : (is_array($campusJ) ? $campusJ : ['Tous']);

        $out = [
            'id'                 => (int)$ev['id'],
            'slug'               => $ev['slug'],
            'titre'              => $ev['titre'],
            'date'               => $ev['date'],
            'date_fin'           => $ev['date_fin'] ?? null,
            'heure'              => $ev['heure'] ?? '-',
            'heure_fin'          => $ev['heure_fin'] ?? null,
            'lieu'               => $ev['lieu'] ?? '-',
            'campus'             => $ev['campus'] ?? 'Tous campus',
            'organisateur'       => $ev['organisateur'] ?? '',
            'organisateur_ecole' => $ev['organisateur_ecole'] ?? null,
            'organisateur_color' => tv_normalize_hex_color($ev['organisateur_color'] ?? null),
            'structure_nom'      => $ev['structure_nom'] ?? null,
            'type'               => $ev['type'] ?? 'Corpo',
            'structure_type'     => $ev['structure_type'] ?? 'asso',
            'asso_type'          => $ev['asso_type'] ?? null,
            'description'        => $ev['description'] ?? '',
            'inscriptions'       => ($ev['mode_inscription'] ?? '') !== 'aucune',
            'lien_billetterie'   => $ev['lien_billetterie'] ?? null,
            'places'             => (int)($ev['places'] ?? 0),
            'inscrits'           => (int)($ev['inscrits'] ?? 0),
            'icon'               => $ev['icon'] ?? '',
            'ecoles_invitees'    => $ecoles,
            'campus_invites'     => $campus,
        ];
        if ($spotlight) {
            $out['tv_sort_score'] = tv_spotlight_score_row($ev, $today);
        }
        return $out;
    }, $rows);

    echo json_encode($events, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur base de données']);
}
