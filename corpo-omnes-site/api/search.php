<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/associations-activity.php';

$q = trim((string)($_GET['q'] ?? ''));
$limit = min(20, max(4, (int)($_GET['limit'] ?? 12)));

if (mb_strlen($q) < 2) {
    echo json_encode(['ok' => true, 'q' => $q, 'results' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$like = '%' . $q . '%';
$out  = [];

$push = static function (array &$out, string $type, string $title, string $url, string $meta = '', int $score = 0): void {
    $out[] = [
        'type'  => $type,
        'title' => $title,
        'url'   => $url,
        'meta'  => $meta,
        'score' => $score,
    ];
};

try {
    $st = $pdo->prepare(
        "SELECT id, titre, date, lieu, campus FROM evenements
         WHERE statut = 'publie' AND (titre LIKE ? OR description LIKE ? OR lieu LIKE ? OR organisateur LIKE ?)
         ORDER BY date DESC LIMIT 6"
    );
    $st->execute([$like, $like, $like, $like]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $meta = date('d/m/Y', strtotime((string)$r['date']));
        if (!empty($r['lieu'])) {
            $meta .= ' · ' . $r['lieu'];
        }
        $push($out, 'event', (string)$r['titre'], 'evenement.php?id=' . (int)$r['id'], $meta, 100);
    }
} catch (Throwable $e) {
}

try {
    $assoWhere = '(nom LIKE ? OR description LIKE ?)';
    if (asso_has_mandat_columns($pdo)) {
        $assoWhere .= ' AND ' . asso_sql_active_condition();
    }
    $st = $pdo->prepare(
        "SELECT id, nom, ecole, campus, slug FROM associations
         WHERE $assoWhere
         ORDER BY nom ASC LIMIT 5"
    );
    $st->execute([$like, $like]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $meta = trim(($r['ecole'] ?? '') . ' · ' . ($r['campus'] ?? ''), ' ·');
        $push($out, 'asso', (string)$r['nom'], 'structure.php?slug=' . urlencode((string)$r['slug']), $meta, 80);
    }
} catch (Throwable $e) {
}

try {
    $st = $pdo->prepare(
        "SELECT id, nom, slug FROM sports WHERE (nom LIKE ? OR description LIKE ?) ORDER BY nom LIMIT 4"
    );
    $st->execute([$like, $like]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $push($out, 'sport', (string)$r['nom'], 'sport-detail.php?slug=' . urlencode((string)$r['slug']), corpo_t('nav.sport'), 75);
    }
} catch (Throwable $e) {
}

try {
    $st = $pdo->prepare(
        "SELECT id, nom, type, campus FROM partenaires
         WHERE statut = 'publie' AND (nom LIKE ? OR description LIKE ? OR offre LIKE ?)
         ORDER BY nom LIMIT 4"
    );
    $st->execute([$like, $like, $like]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $push($out, 'partner', (string)$r['nom'], 'partenaires.php#' . (int)$r['id'], (string)($r['type'] ?? ''), 70);
    }
} catch (Throwable $e) {
}

try {
    $st = $pdo->prepare(
        "SELECT id, titre, prix FROM boutique_produits
         WHERE statut = 'publie' AND (titre LIKE ? OR description LIKE ? OR categorie LIKE ?)
         ORDER BY updated_at DESC LIMIT 4"
    );
    $st->execute([$like, $like, $like]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $push($out, 'shop', (string)$r['titre'], 'boutique.php', number_format((float)$r['prix'], 2, ',', ' ') . ' €', 65);
    }
} catch (Throwable $e) {
}

try {
    $st = $pdo->prepare(
        "SELECT id, titre, created_at FROM actualites
         WHERE statut = 'publie' AND IFNULL(visibilite,'public') = 'public'
           AND (titre LIKE ? OR contenu LIKE ?)
         ORDER BY created_at DESC LIMIT 4"
    );
    $st->execute([$like, $like]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $push($out, 'news', (string)$r['titre'], 'actualites.php#actu-' . (int)$r['id'], date('d/m/Y', strtotime((string)$r['created_at'])), 60);
    }
} catch (Throwable $e) {
}

usort($out, fn($a, $b) => ($b['score'] <=> $a['score']) ?: strcmp($a['title'], $b['title']));
$out = array_slice($out, 0, $limit);

$typeLabels = [
    'event'   => corpo_t('search.type_event'),
    'asso'    => corpo_t('search.type_asso'),
    'sport'   => corpo_t('search.type_sport'),
    'partner' => corpo_t('search.type_partner'),
    'shop'    => corpo_t('search.type_shop'),
    'news'    => corpo_t('search.type_news'),
];

foreach ($out as &$row) {
    $row['type_label'] = $typeLabels[$row['type']] ?? $row['type'];
}
unset($row);

echo json_encode(['ok' => true, 'q' => $q, 'results' => $out], JSON_UNESCAPED_UNICODE);
