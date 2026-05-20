<?php

declare(strict_types=1);

require_once __DIR__ . '/date-fr.php';

function asso_has_mandat_columns(PDO $pdo): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        $pdo->query('SELECT date_debut_mandat, date_fin_mandat FROM associations LIMIT 0');
        $cache = true;
    } catch (Throwable $e) {
        $cache = false;
    }
    return $cache;
}

function asso_normalize_mandat_date($raw): ?string
{
    return corpo_parse_date_input($raw);
}

function asso_is_active(?array $row, ?DateTimeImmutable $ref = null): bool
{
    if ($row === null) {
        return false;
    }
    if (!array_key_exists('date_debut_mandat', $row) && !array_key_exists('date_fin_mandat', $row)) {
        return true;
    }
    $ref = $ref ?? new DateTimeImmutable('today');

    $debut = asso_normalize_mandat_date($row['date_debut_mandat'] ?? null);
    $fin   = asso_normalize_mandat_date($row['date_fin_mandat'] ?? null);

    if ($debut !== null && $ref < new DateTimeImmutable($debut)) {
        return false;
    }
    if ($fin !== null && $ref > new DateTimeImmutable($fin)) {
        return false;
    }
    return true;
}

function asso_mandat_status_label(array $row): string
{
    if (asso_is_active($row)) {
        $debut = asso_normalize_mandat_date($row['date_debut_mandat'] ?? null);
        $fin   = asso_normalize_mandat_date($row['date_fin_mandat'] ?? null);
        if ($debut === null && $fin === null) {
            return 'active_life';
        }
        return 'active';
    }
    $debut = asso_normalize_mandat_date($row['date_debut_mandat'] ?? null);
    $ref   = new DateTimeImmutable('today');
    if ($debut !== null && $ref < new DateTimeImmutable($debut)) {
        return 'upcoming';
    }
    return 'inactive';
}

function asso_sql_active_condition(string $alias = ''): string
{
    $p = $alias !== '' ? rtrim($alias, '.') . '.' : '';
    return "(({$p}date_debut_mandat IS NULL OR {$p}date_debut_mandat <= CURDATE())
            AND ({$p}date_fin_mandat IS NULL OR {$p}date_fin_mandat >= CURDATE()))";
}

function asso_type_skips_parent_link(string $type): bool
{
    return in_array(trim($type), ['BDE', 'BDS', 'Corpo', 'Fédération'], true);
}

function asso_ecole_is_corpo_direct(string $ecole): bool
{
    $e = trim($ecole);
    if ($e === '' || strcasecmp($e, 'Toutes') === 0) {
        return true;
    }
    return stripos($e, 'omnes') !== false;
}

function asso_find_active_bde_for_ecole(PDO $pdo, string $ecole, ?int $exceptId = null): ?array
{
    $sql = "SELECT * FROM associations WHERE type = 'BDE' AND ecole = ?";
    $params = [$ecole];
    if ($exceptId) {
        $sql .= ' AND id != ?';
        $params[] = $exceptId;
    }
    $sql .= ' ORDER BY date_debut_mandat DESC, id DESC';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as $row) {
        if (asso_is_active($row)) {
            return $row;
        }
    }
    return null;
}

function asso_find_active_echofed(PDO $pdo): ?array
{
    $st = $pdo->query("SELECT * FROM associations WHERE slug = 'echofed' LIMIT 1");
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row && asso_is_active($row)) {
        return $row;
    }
    $st = $pdo->prepare(
        "SELECT * FROM associations WHERE ecole = 'HEIP' AND type = 'Fédération' ORDER BY id ASC"
    );
    $st->execute();
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $fed) {
        if (asso_is_active($fed)) {
            return $fed;
        }
    }
    return null;
}

function asso_resolve_parent_bde_id(PDO $pdo, string $ecole, string $type, ?int $selfId = null): ?int
{
    if (asso_type_skips_parent_link($type)) {
        return null;
    }
    if (asso_ecole_is_corpo_direct($ecole)) {
        return null;
    }
    if ($ecole === 'HEIP') {
        $fed = asso_find_active_echofed($pdo);
        return $fed ? (int)$fed['id'] : null;
    }
    $bde = asso_find_active_bde_for_ecole($pdo, $ecole, $selfId);
    return $bde ? (int)$bde['id'] : null;
}

function asso_describe_parent_attachment(PDO $pdo, string $ecole, string $type, ?int $selfId = null): array
{
    if (asso_type_skips_parent_link($type)) {
        return ['id' => null, 'label' => '- (structure autonome)', 'warn' => null];
    }
    if (asso_ecole_is_corpo_direct($ecole)) {
        return ['id' => null, 'label' => 'Corpo Omnes (école inter-écoles / Omnes)', 'warn' => null];
    }
    if ($ecole === 'HEIP') {
        $fed = asso_find_active_echofed($pdo);
        if ($fed) {
            return [
                'id'    => (int)$fed['id'],
                'label' => (string)$fed['nom'] . ' (fédération HEIP, mandat actif)',
                'warn'  => null,
            ];
        }
        return [
            'id'    => null,
            'label' => 'Corpo Omnes (EchoFed inactive ou absente)',
            'warn'  => 'Aucune fédération HEIP active : l’association sera rattachée à la Corpo.',
        ];
    }
    $bde = asso_find_active_bde_for_ecole($pdo, $ecole, $selfId);
    if ($bde) {
        return [
            'id'    => (int)$bde['id'],
            'label' => (string)$bde['nom'] . ' - ' . (string)$bde['ecole'] . ' (BDE mandat actif)',
            'warn'  => null,
        ];
    }
    return [
        'id'    => null,
        'label' => 'Corpo Omnes (aucun BDE actif pour ' . $ecole . ')',
        'warn'  => 'Aucun BDE en mandat actif pour cette école : rattachement direct à la Corpo.',
    ];
}

function asso_sync_parents_after_structure_change(
    PDO $pdo,
    string $type,
    string $ecole,
    ?string $oldEcole = null
): void {
    $ecole = trim($ecole);
    $oldEcole = $oldEcole !== null ? trim($oldEcole) : null;
    $sync = static function (string $e) use ($pdo): void {
        if ($e !== '' && strcasecmp($e, 'Toutes') !== 0) {
            asso_sync_parents_for_ecole($pdo, $e);
        }
    };
    if (strcasecmp($type, 'BDE') === 0) {
        $sync($ecole);
        return;
    }
    if (strcasecmp($type, 'Fédération') === 0
        && ($ecole === 'HEIP' || strcasecmp($oldEcole ?? '', 'HEIP') === 0)) {
        $sync('HEIP');
        return;
    }
    if ($oldEcole !== null && $oldEcole !== '' && strcasecmp($oldEcole, $ecole) !== 0) {
        $sync($oldEcole);
    }
    $sync($ecole);
}

function asso_sync_parents_for_ecole(PDO $pdo, string $ecole): int
{
    $st = $pdo->prepare(
        "SELECT id, type FROM associations
         WHERE ecole = ?
           AND type NOT IN ('BDE','BDS','Corpo','Fédération')"
    );
    $st->execute([$ecole]);
    $n = 0;
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $parentId = asso_resolve_parent_bde_id($pdo, $ecole, (string)$row['type'], (int)$row['id']);
        $pdo->prepare('UPDATE associations SET parent_bde_id = ? WHERE id = ?')
            ->execute([$parentId, (int)$row['id']]);
        $n++;
    }
    return $n;
}

function asso_parent_display_name(PDO $pdo, ?int $parentId): string
{
    if (!$parentId) {
        return 'Corpo Omnes';
    }
    $st = $pdo->prepare('SELECT nom, type, ecole FROM associations WHERE id = ? LIMIT 1');
    $st->execute([$parentId]);
    $p = $st->fetch(PDO::FETCH_ASSOC);
    if (!$p) {
        return '-';
    }
    return trim((string)$p['nom'] . ' (' . ($p['type'] ?? '') . ')');
}

function asso_format_mandat_period(array $row): string
{
    $debut = asso_normalize_mandat_date($row['date_debut_mandat'] ?? null);
    $fin   = asso_normalize_mandat_date($row['date_fin_mandat'] ?? null);
    if ($debut === null && $fin === null) {
        return '';
    }
    $fmt = static fn(?string $d) => $d ? (new DateTimeImmutable($d))->format('d/m/Y') : '-';
    if ($debut && $fin) {
        return $fmt($debut) . ' → ' . $fmt($fin);
    }
    if ($debut) {
        return 'depuis le ' . $fmt($debut);
    }
    return 'jusqu\'au ' . $fmt($fin);
}
