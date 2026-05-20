<?php

declare(strict_types=1);

require_once __DIR__ . '/date-fr.php';

function nf_table_ready(PDO $pdo): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        $pdo->query('SELECT 1 FROM compta_notes_frais LIMIT 0');
        $cache = true;
    } catch (Throwable $e) {
        $cache = false;
    }
    return $cache;
}

function nf_source_type_ready(PDO $pdo): bool
{
    if (!function_exists('compta_has_source_columns') || !compta_has_source_columns($pdo)) {
        return false;
    }
    try {
        $st = $pdo->query(
            "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'compta_transactions'
               AND COLUMN_NAME = 'source_type'
             LIMIT 1"
        );
        $col = (string)$st->fetchColumn();
        return str_contains($col, 'note_frais');
    } catch (Throwable $e) {
        return false;
    }
}

function nf_user_is_bureau_member(PDO $pdo, int $userId, string $structType, int $structId): bool
{
    if ($userId <= 0 || $structId <= 0) {
        return false;
    }
    $st = $pdo->prepare(
        "SELECT 1 FROM structure_membres
         WHERE user_id = ? AND structure_type = ? AND structure_id = ?
           AND statut = 'actif' AND role_in_struct IN ('admin', 'membre')
         LIMIT 1"
    );
    $st->execute([$userId, $structType, $structId]);
    return (bool)$st->fetchColumn();
}

function nf_can_submit(PDO $pdo, int $userId, string $structType, int $structId): bool
{
    return nf_user_is_bureau_member($pdo, $userId, $structType, $structId);
}

function nf_can_submit_any(PDO $pdo, int $userId): bool
{
    return nf_memberships_for_submit($pdo, $userId) !== [];
}

function nf_can_access_admin_notes_page(PDO $pdo, int $userId): bool
{
    if ($userId <= 0 || !nf_table_ready($pdo)) {
        return false;
    }
    if (function_exists('isSuperAdmin') && isSuperAdmin()) {
        return true;
    }
    if (nf_can_submit_any($pdo, $userId)) {
        return true;
    }
    return nf_is_bureau_validator_any($pdo, $userId);
}

function nf_is_super_validator(): bool
{
    return function_exists('isSuperAdmin') && isSuperAdmin();
}

function nf_can_manage(PDO $pdo, int $userId, string $structType, int $structId): bool
{
    if ($userId <= 0 || $structId <= 0 || !function_exists('memberHasStructureResponsabilite')) {
        return false;
    }
    if (nf_is_super_validator()) {
        return false;
    }
    if (memberHasStructureResponsabilite($structType, $structId, 'tresorerie')) {
        return true;
    }
    if ($structType === 'asso') {
        return memberHasStructureResponsabilite('bde', $structId, 'tresorerie')
            || memberHasStructureResponsabilite('bds', $structId, 'tresorerie');
    }
    return false;
}

function nf_dual_validation_ready(PDO $pdo): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        $st = $pdo->query(
            "SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'compta_notes_frais'
               AND COLUMN_NAME = 'valide_bureau_par'
             LIMIT 1"
        );
        $cache = (bool)$st->fetchColumn();
    } catch (Throwable $e) {
        $cache = false;
    }
    return $cache;
}

function nf_is_bureau_validator_any(PDO $pdo, int $userId): bool
{
    foreach (getMemberships() as $m) {
        $type = (string)($m['type'] ?? '');
        $id   = (int)($m['id'] ?? 0);
        if ($id > 0 && nf_user_is_bureau_member($pdo, $userId, $type, $id)) {
            return true;
        }
    }
    return false;
}

function nf_user_display(array $row, string $prefix = ''): string
{
    $p = $prefix !== '' ? $prefix . '_' : '';
    $name = trim((string)($row[$p . 'prenom'] ?? '') . ' ' . (string)($row[$p . 'nom'] ?? ''));
    return $name !== '' ? $name : (string)($row[$p . 'email'] ?? '');
}

function nf_can_view(PDO $pdo, int $userId, array $note): bool
{
    if ($userId <= 0) {
        return false;
    }
    if (nf_is_super_validator()) {
        return true;
    }
    if ((int)($note['user_id'] ?? 0) === $userId) {
        return true;
    }
    $structType = (string)($note['structure_type'] ?? '');
    $structId   = (int)($note['structure_id'] ?? 0);
    if (nf_can_manage($pdo, $userId, $structType, $structId)) {
        return true;
    }
    return nf_user_is_bureau_member($pdo, $userId, $structType, $structId);
}

function nf_can_approve_bureau_note(PDO $pdo, int $userId, array $note): bool
{
    if ($userId <= 0) {
        return false;
    }
    if (nf_is_super_validator()) {
        return false;
    }
    if (!nf_dual_validation_ready($pdo)) {
        return false;
    }
    $structType = (string)($note['structure_type'] ?? '');
    $structId   = (int)($note['structure_id'] ?? 0);
    if (($note['statut'] ?? '') !== 'soumise') {
        return false;
    }
    if ((int)($note['user_id'] ?? 0) === $userId) {
        return false;
    }
    return nf_user_is_bureau_member($pdo, $userId, $structType, $structId);
}

function nf_can_approve_treso_note(PDO $pdo, int $userId, array $note): bool
{
    if ($userId <= 0) {
        return false;
    }
    $structType = (string)($note['structure_type'] ?? '');
    $structId   = (int)($note['structure_id'] ?? 0);
    $st         = (string)($note['statut'] ?? '');

    if (nf_is_super_validator()) {
        return false;
    }

    if (!nf_dual_validation_ready($pdo)) {
        return false;
    }
    if ($st !== 'approuvee_bureau') {
        return false;
    }
    if ((int)($note['user_id'] ?? 0) === $userId) {
        return false;
    }
    $bureauId = (int)($note['valide_bureau_par'] ?? 0);
    if ($bureauId > 0 && $bureauId === $userId) {
        return false;
    }
    return nf_can_manage($pdo, $userId, $structType, $structId);
}

function nf_can_super_validate_note(PDO $pdo, int $userId, array $note): bool
{
    if (!nf_is_super_validator() || $userId <= 0 || !nf_dual_validation_ready($pdo)) {
        return false;
    }
    $st = (string)($note['statut'] ?? '');
    return in_array($st, ['soumise', 'approuvee_bureau'], true) && empty($note['compta_transaction_id']);
}

function nf_can_reject_note(PDO $pdo, int $userId, array $note): bool
{
    $st = (string)($note['statut'] ?? '');
    if (!in_array($st, ['soumise', 'approuvee_bureau'], true)) {
        return false;
    }
    if (nf_is_super_validator()) {
        return true;
    }
    $structType = (string)($note['structure_type'] ?? '');
    $structId   = (int)($note['structure_id'] ?? 0);
    if ($st === 'soumise') {
        return nf_can_approve_bureau_note($pdo, $userId, $note)
            || nf_can_manage($pdo, $userId, $structType, $structId);
    }
    return nf_can_manage($pdo, $userId, $structType, $structId);
}

function nf_memberships_for_submit(PDO $pdo, int $userId): array
{
    $out = [];
    foreach (getMemberships() as $m) {
        $type = (string)($m['type'] ?? '');
        $id   = (int)($m['id'] ?? 0);
        $role = (string)($m['role'] ?? '');
        if ($id <= 0 || !in_array($type, ['asso', 'bde', 'bds', 'sport'], true)) {
            continue;
        }
        if ($role === 'adherent' || !nf_can_submit($pdo, $userId, $type, $id)) {
            continue;
        }
        $nom = (string)($m['nom'] ?? '');
        if ($nom === '' && in_array($type, ['asso', 'bde', 'bds'], true)) {
            $st = $pdo->prepare('SELECT nom FROM associations WHERE id = ? LIMIT 1');
            $st->execute([$id]);
            $nom = (string)$st->fetchColumn();
        }
        if ($nom === '' && $type === 'sport') {
            $st = $pdo->prepare('SELECT nom FROM sports WHERE id = ? LIMIT 1');
            $st->execute([$id]);
            $nom = (string)$st->fetchColumn();
        }
        $out[] = ['type' => $type, 'id' => $id, 'nom' => $nom ?: ('Structure #' . $id)];
    }
    usort($out, fn($a, $b) => strcmp($a['nom'], $b['nom']));
    return $out;
}

function nf_statut_label(string $statut): string
{
    return match ($statut) {
        'soumise'           => 'En attente bureau',
        'approuvee_bureau'  => 'En attente trésorerie',
        'approuvee'         => 'En attente trésorerie',
        'refusee'           => 'Refusée',
        'remboursee'        => 'Remboursée (compta)',
        default             => $statut,
    };
}

function nf_validation_lines(array $note): array
{
    $lines = [];
    if (!empty($note['valide_bureau_par'])) {
        $lines[] = 'Bureau : ' . nf_user_display($note, 'bureau')
            . (!empty($note['valide_bureau_le']) ? ' (' . date('d/m/Y H:i', strtotime((string)$note['valide_bureau_le'])) . ')' : '');
    }
    if (!empty($note['valide_treso_par'])) {
        $lines[] = 'Trésorerie : ' . nf_user_display($note, 'treso')
            . (!empty($note['valide_treso_le']) ? ' (' . date('d/m/Y H:i', strtotime((string)$note['valide_treso_le'])) . ')' : '');
    }
    return $lines;
}

function nf_upload_justificatif_pdf(string $structType, int $structId, string $fileField = 'justificatif_pdf'): array
{
    if (empty($_FILES[$fileField]['tmp_name']) || ($_FILES[$fileField]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'msg' => 'Le justificatif PDF est obligatoire.'];
    }
    $size = (int)($_FILES[$fileField]['size'] ?? 0);
    if ($size <= 0 || $size > 10 * 1024 * 1024) {
        return ['ok' => false, 'msg' => 'PDF trop volumineux (max. 10 Mo).'];
    }
    $ext = strtolower(pathinfo((string)($_FILES[$fileField]['name'] ?? ''), PATHINFO_EXTENSION));
    if ($ext !== 'pdf') {
        return ['ok' => false, 'msg' => 'Seuls les fichiers PDF sont acceptés.'];
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = $finfo ? (string)finfo_file($finfo, $_FILES[$fileField]['tmp_name']) : '';
    if ($finfo) {
        finfo_close($finfo);
    }
    if ($mime !== '' && $mime !== 'application/pdf') {
        return ['ok' => false, 'msg' => 'Le fichier doit être un PDF valide.'];
    }

    $dir = __DIR__ . '/../images/justificatifs/' . preg_replace('/[^a-z]/', '', $structType) . '/' . (int)$structId . '/';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        return ['ok' => false, 'msg' => 'Impossible de créer le dossier de stockage.'];
    }
    $fname = 'nf-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.pdf';
    if (!move_uploaded_file($_FILES[$fileField]['tmp_name'], $dir . $fname)) {
        return ['ok' => false, 'msg' => 'Échec de l\'enregistrement du PDF.'];
    }
    return ['ok' => true, 'path' => 'images/justificatifs/' . preg_replace('/[^a-z]/', '', $structType) . '/' . (int)$structId . '/' . $fname];
}

function nf_create_request(
    PDO $pdo,
    int $userId,
    string $structType,
    int $structId,
    float $montant,
    string $dateDepense,
    string $libelle,
    string $pdfPath,
    ?string $commentaire = null
): array {
    if (!nf_table_ready($pdo)) {
        return ['ok' => false, 'msg' => 'Table notes de frais absente — exécute la migration.'];
    }
    if (!nf_can_submit($pdo, $userId, $structType, $structId)) {
        return ['ok' => false, 'msg' => 'Réservé aux membres du bureau (hors adhérents) de cette structure.'];
    }
    $libelle = trim($libelle);
    if ($libelle === '' || $montant <= 0) {
        return ['ok' => false, 'msg' => 'Libellé et montant strictement positif requis.'];
    }
    $dateIso = corpo_parse_date_input($dateDepense);
    if ($dateIso === null) {
        return ['ok' => false, 'msg' => 'Date de dépense invalide (jj/mm/aaaa).'];
    }
    if ($pdfPath === '') {
        return ['ok' => false, 'msg' => 'Justificatif PDF requis.'];
    }

    $pdo->prepare(
        "INSERT INTO compta_notes_frais
           (structure_type, structure_id, user_id, montant, date_depense, libelle,
            justificatif_pdf, commentaire_membre, statut)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'soumise')"
    )->execute([
        $structType,
        $structId,
        $userId,
        round($montant, 2),
        $dateIso,
        $libelle,
        $pdfPath,
        $commentaire !== null && trim($commentaire) !== '' ? trim($commentaire) : null,
    ]);

    return ['ok' => true, 'id' => (int)$pdo->lastInsertId(), 'msg' => 'Demande envoyée — validation bureau puis trésorerie requises.'];
}

function nf_get(PDO $pdo, int $noteId): ?array
{
    if (!nf_table_ready($pdo)) {
        return null;
    }
    $joins = 'JOIN users u ON u.id = nf.user_id';
    if (nf_dual_validation_ready($pdo)) {
        $joins .= ' LEFT JOIN users ub ON ub.id = nf.valide_bureau_par
                    LEFT JOIN users ut ON ut.id = nf.valide_treso_par';
    }
    $cols = 'nf.*, u.prenom, u.nom, u.email';
    if (nf_dual_validation_ready($pdo)) {
        $cols .= ', ub.prenom AS bureau_prenom, ub.nom AS bureau_nom, ub.email AS bureau_email,
                  ut.prenom AS treso_prenom, ut.nom AS treso_nom, ut.email AS treso_email';
    }
    $st = $pdo->prepare("SELECT $cols FROM compta_notes_frais nf $joins WHERE nf.id = ? LIMIT 1");
    $st->execute([$noteId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function nf_list_for_user(PDO $pdo, int $userId, ?string $structType = null, ?int $structId = null): array
{
    if (!nf_table_ready($pdo)) {
        return [];
    }
    $sql = 'SELECT nf.*, a.nom AS structure_nom FROM compta_notes_frais nf
            LEFT JOIN associations a ON nf.structure_type IN (\'asso\',\'bde\',\'bds\') AND a.id = nf.structure_id
            WHERE nf.user_id = ?';
    $params = [$userId];
    if ($structType !== null && $structId !== null && $structId > 0) {
        $sql .= ' AND nf.structure_type = ? AND nf.structure_id = ?';
        $params[] = $structType;
        $params[] = $structId;
    }
    $sql .= ' ORDER BY nf.created_at DESC';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function nf_list_for_structure(PDO $pdo, string $structType, int $structId, ?string $statutFilter = null): array
{
    if (!nf_table_ready($pdo)) {
        return [];
    }
    $joins = 'JOIN users u ON u.id = nf.user_id';
    $cols  = 'nf.*, u.prenom, u.nom, u.email';
    if (nf_dual_validation_ready($pdo)) {
        $joins .= ' LEFT JOIN users ub ON ub.id = nf.valide_bureau_par
                     LEFT JOIN users ut ON ut.id = nf.valide_treso_par';
        $cols .= ', ub.prenom AS bureau_prenom, ub.nom AS bureau_nom, ub.email AS bureau_email,
                  ut.prenom AS treso_prenom, ut.nom AS treso_nom, ut.email AS treso_email';
    }
    $sql = "SELECT $cols FROM compta_notes_frais nf $joins
            WHERE nf.structure_type = ? AND nf.structure_id = ?";
    $params = [$structType, $structId];
    if ($statutFilter !== null && $statutFilter !== '') {
        $sql .= ' AND nf.statut = ?';
        $params[] = $statutFilter;
    }
    $sql .= ' ORDER BY FIELD(nf.statut, \'soumise\', \'approuvee_bureau\', \'approuvee\', \'remboursee\', \'refusee\'), nf.created_at DESC';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function nf_count_pending(PDO $pdo, string $structType, int $structId): int
{
    if (!nf_table_ready($pdo)) {
        return 0;
    }
    if (nf_dual_validation_ready($pdo)) {
        $st = $pdo->prepare(
            "SELECT COUNT(*) FROM compta_notes_frais
             WHERE structure_type = ? AND structure_id = ?
               AND statut IN ('soumise', 'approuvee_bureau')"
        );
    } else {
        $st = $pdo->prepare(
            "SELECT COUNT(*) FROM compta_notes_frais
             WHERE structure_type = ? AND structure_id = ? AND statut = 'soumise'"
        );
    }
    $st->execute([$structType, $structId]);
    return (int)$st->fetchColumn();
}

function nf_list_pending_bureau_for_validator(PDO $pdo, int $validatorId): array
{
    if (!nf_table_ready($pdo) || !nf_dual_validation_ready($pdo)) {
        return [];
    }
    $out = [];
    $seen = [];
    foreach (getMemberships() as $m) {
        $type = (string)($m['type'] ?? '');
        $id   = (int)($m['id'] ?? 0);
        if ($id <= 0 || !in_array($type, ['asso', 'bde', 'bds', 'sport'], true)) {
            continue;
        }
        if (!nf_user_is_bureau_member($pdo, $validatorId, $type, $id)) {
            continue;
        }
        $key = $type . ':' . $id;
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $st = $pdo->prepare(
            "SELECT nf.*, u.prenom, u.nom, u.email,
                    COALESCE(a.nom, '') AS structure_nom
             FROM compta_notes_frais nf
             JOIN users u ON u.id = nf.user_id
             LEFT JOIN associations a ON nf.structure_type IN ('asso','bde','bds') AND a.id = nf.structure_id
             WHERE nf.structure_type = ? AND nf.structure_id = ?
               AND nf.statut = 'soumise' AND nf.user_id <> ?
             ORDER BY nf.created_at ASC"
        );
        $st->execute([$type, $id, $validatorId]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[] = $row;
        }
    }
    return $out;
}

function nf_list_pending_for_super_admin(PDO $pdo): array
{
    if (!nf_table_ready($pdo) || !nf_is_super_validator()) {
        return [];
    }
    $st = $pdo->query(
        "SELECT nf.*, u.prenom, u.nom, u.email,
                COALESCE(a.nom, sp.nom, CONCAT(UPPER(nf.structure_type), ' #', nf.structure_id)) AS structure_nom
         FROM compta_notes_frais nf
         JOIN users u ON u.id = nf.user_id
         LEFT JOIN associations a ON nf.structure_type IN ('asso','bde','bds') AND a.id = nf.structure_id
         LEFT JOIN sports sp ON nf.structure_type = 'sport' AND sp.id = nf.structure_id
         WHERE nf.statut IN ('soumise', 'approuvee_bureau')
           AND (nf.compta_transaction_id IS NULL OR nf.compta_transaction_id = 0)
         ORDER BY FIELD(nf.statut, 'soumise', 'approuvee_bureau'), nf.created_at ASC"
    );
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function nf_super_validate_and_book(PDO $pdo, int $noteId, int $adminId, ?string $comment = null): array
{
    if (!nf_is_super_validator()) {
        return ['ok' => false, 'msg' => 'Réservé au super administrateur.'];
    }
    $note = nf_get($pdo, $noteId);
    if (!$note) {
        return ['ok' => false, 'msg' => 'Demande introuvable.'];
    }
    if (!nf_can_super_validate_note($pdo, $adminId, $note)) {
        return ['ok' => false, 'msg' => 'Validation impossible pour cette demande.'];
    }
    if (($note['statut'] ?? '') === 'soumise' && nf_dual_validation_ready($pdo)) {
        $pdo->prepare(
            "UPDATE compta_notes_frais
             SET statut = 'approuvee_bureau', valide_bureau_par = ?, valide_bureau_le = NOW(),
                 commentaire_bureau = COALESCE(?, commentaire_bureau)
             WHERE id = ? AND statut = 'soumise'"
        )->execute([
            $adminId,
            $comment !== null && trim($comment) !== '' ? trim($comment) : null,
            $noteId,
        ]);
    }
    return nf_approve_treso_and_book($pdo, $noteId, $adminId, $comment);
}

function nf_approve_bureau(PDO $pdo, int $noteId, int $validatorId, ?string $comment = null): array
{
    $note = nf_get($pdo, $noteId);
    if (!$note) {
        return ['ok' => false, 'msg' => 'Demande introuvable.'];
    }
    if (!nf_dual_validation_ready($pdo)) {
        return ['ok' => false, 'msg' => 'Migration double validation absente — exécute nf_dual_validation_cols.'];
    }
    if (!nf_can_approve_bureau_note($pdo, $validatorId, $note)) {
        return ['ok' => false, 'msg' => 'Validation bureau impossible (droits, statut ou tu es le demandeur).'];
    }

    $pdo->prepare(
        "UPDATE compta_notes_frais
         SET statut = 'approuvee_bureau', valide_bureau_par = ?, valide_bureau_le = NOW(),
             commentaire_bureau = ?
         WHERE id = ? AND statut = 'soumise'"
    )->execute([
        $validatorId,
        $comment !== null && trim($comment) !== '' ? trim($comment) : null,
        $noteId,
    ]);

    return ['ok' => true, 'msg' => 'Validée par le bureau — en attente de la trésorerie (autre personne).'];
}

function nf_approve_treso_and_book(PDO $pdo, int $noteId, int $treasurerId, ?string $comment = null): array
{
    require_once __DIR__ . '/comptabilite.php';

    $note = nf_get($pdo, $noteId);
    if (!$note) {
        return ['ok' => false, 'msg' => 'Demande introuvable.'];
    }
    if (!nf_dual_validation_ready($pdo)) {
        return nf_approve_and_book_legacy($pdo, $noteId, $treasurerId, $comment);
    }
    if (!nf_can_approve_treso_note($pdo, $treasurerId, $note)) {
        return ['ok' => false, 'msg' => 'Validation trésorerie impossible : droits requis, statut incorrect, ou même personne que le validateur bureau.'];
    }
    if (!empty($note['compta_transaction_id'])) {
        return ['ok' => false, 'msg' => 'Déjà liée à une écriture comptable.'];
    }

    $structType = (string)$note['structure_type'];
    $structId   = (int)$note['structure_id'];
    $montant    = (float)$note['montant'];
    $libelle    = 'Note de frais — ' . trim((string)$note['libelle']);
    $dateOp     = (string)$note['date_depense'];
    $pdf        = (string)($note['justificatif_pdf'] ?? '');

    $catId    = compta_find_category_id($pdo, 'Notes de frais', 'depense')
        ?? compta_find_category_id($pdo, 'Autres dépenses', 'depense');
    $compteId = compta_get_default_compte_id($pdo, $structType, $structId);

    $pdo->beginTransaction();
    try {
        $cols = 'structure_type, structure_id, compte_id, categorie_id, type, montant, date_operation, libelle, notes, mode_paiement, justificatif, cree_par';
        $vals = '?, ?, ?, ?, \'depense\', ?, ?, ?, ?, \'virement\', ?, ?';
        $params = [
            $structType,
            $structId,
            $compteId,
            $catId,
            $montant,
            $dateOp,
            mb_substr($libelle, 0, 200),
            'Demande #' . $noteId . ' — ' . trim((string)($note['prenom'] ?? '') . ' ' . ($note['nom'] ?? '')),
            $pdf ?: null,
            $treasurerId,
        ];
        if (nf_source_type_ready($pdo)) {
            $cols .= ', source_type, source_id';
            $vals .= ", 'note_frais', ?";
            $params[] = $noteId;
        }
        $pdo->prepare("INSERT INTO compta_transactions ($cols) VALUES ($vals)")->execute($params);
        $txId = (int)$pdo->lastInsertId();

        $upd = $pdo->prepare(
            "UPDATE compta_notes_frais
             SET statut = 'remboursee', valide_treso_par = ?, valide_treso_le = NOW(),
                 traite_par = ?, traite_le = NOW(),
                 commentaire_tresorier = ?, compta_transaction_id = ?
             WHERE id = ? AND statut = 'approuvee_bureau'"
        );
        $upd->execute([
            $treasurerId,
            $treasurerId,
            $comment !== null && trim($comment) !== '' ? trim($comment) : null,
            $txId,
            $noteId,
        ]);
        if ($upd->rowCount() < 1) {
            throw new RuntimeException('La note n\'est pas en attente de validation trésorerie.');
        }

        $pdo->commit();
        return ['ok' => true, 'tx_id' => $txId, 'msg' => 'Validée par la trésorerie et enregistrée en compta.'];
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['ok' => false, 'msg' => 'Erreur : ' . $e->getMessage()];
    }
}

function nf_approve_and_book_legacy(PDO $pdo, int $noteId, int $treasurerId, ?string $comment = null): array
{
    require_once __DIR__ . '/comptabilite.php';

    $note = nf_get($pdo, $noteId);
    if (!$note) {
        return ['ok' => false, 'msg' => 'Demande introuvable.'];
    }
    $structType = (string)$note['structure_type'];
    $structId   = (int)$note['structure_id'];
    if (!nf_can_manage($pdo, $treasurerId, $structType, $structId)) {
        return ['ok' => false, 'msg' => 'Droits trésorerie requis.'];
    }
    if (($note['statut'] ?? '') !== 'soumise') {
        return ['ok' => false, 'msg' => 'Cette demande n\'est plus en attente.'];
    }
    if (!empty($note['compta_transaction_id'])) {
        return ['ok' => false, 'msg' => 'Déjà liée à une écriture comptable.'];
    }

    $montant = (float)$note['montant'];
    $libelle = 'Note de frais — ' . trim((string)$note['libelle']);
    $dateOp  = (string)$note['date_depense'];
    $pdf     = (string)($note['justificatif_pdf'] ?? '');

    $catId    = compta_find_category_id($pdo, 'Notes de frais', 'depense')
        ?? compta_find_category_id($pdo, 'Autres dépenses', 'depense');
    $compteId = compta_get_default_compte_id($pdo, $structType, $structId);

    $pdo->beginTransaction();
    try {
        $cols = 'structure_type, structure_id, compte_id, categorie_id, type, montant, date_operation, libelle, notes, mode_paiement, justificatif, cree_par';
        $vals = '?, ?, ?, ?, \'depense\', ?, ?, ?, ?, \'virement\', ?, ?';
        $params = [
            $structType, $structId, $compteId, $catId, $montant, $dateOp,
            mb_substr($libelle, 0, 200),
            'Demande #' . $noteId . ' — ' . trim((string)($note['prenom'] ?? '') . ' ' . ($note['nom'] ?? '')),
            $pdf ?: null, $treasurerId,
        ];
        if (nf_source_type_ready($pdo)) {
            $cols .= ', source_type, source_id';
            $vals .= ", 'note_frais', ?";
            $params[] = $noteId;
        }
        $pdo->prepare("INSERT INTO compta_transactions ($cols) VALUES ($vals)")->execute($params);
        $txId = (int)$pdo->lastInsertId();

        $pdo->prepare(
            "UPDATE compta_notes_frais
             SET statut = 'remboursee', traite_par = ?, traite_le = NOW(),
                 commentaire_tresorier = ?, compta_transaction_id = ?
             WHERE id = ? AND statut = 'soumise'"
        )->execute([
            $treasurerId,
            $comment !== null && trim($comment) !== '' ? trim($comment) : null,
            $txId,
            $noteId,
        ]);

        $pdo->commit();
        return ['ok' => true, 'tx_id' => $txId, 'msg' => 'Note approuvée et enregistrée en compta.'];
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['ok' => false, 'msg' => 'Erreur : ' . $e->getMessage()];
    }
}

function nf_approve_and_book(PDO $pdo, int $noteId, int $treasurerId, ?string $comment = null): array
{
    return nf_approve_treso_and_book($pdo, $noteId, $treasurerId, $comment);
}

function nf_reject(PDO $pdo, int $noteId, int $actorId, string $comment): array
{
    $note = nf_get($pdo, $noteId);
    if (!$note) {
        return ['ok' => false, 'msg' => 'Demande introuvable.'];
    }
    if (!nf_can_reject_note($pdo, $actorId, $note)) {
        return ['ok' => false, 'msg' => 'Refus non autorisé pour cette demande.'];
    }
    $comment = trim($comment);
    if ($comment === '') {
        return ['ok' => false, 'msg' => 'Un motif de refus est requis.'];
    }

    $st = (string)($note['statut'] ?? '');
    if (nf_dual_validation_ready($pdo) && $st === 'soumise' && nf_can_approve_bureau_note($pdo, $actorId, $note)) {
        $pdo->prepare(
            "UPDATE compta_notes_frais
             SET statut = 'refusee', valide_bureau_par = NULL, valide_bureau_le = NULL,
                 commentaire_bureau = ?, traite_par = ?, traite_le = NOW(), commentaire_tresorier = ?
             WHERE id = ? AND statut = 'soumise'"
        )->execute([$comment, $actorId, $comment, $noteId]);
    } else {
        $allowed = nf_dual_validation_ready($pdo)
            ? "statut IN ('soumise', 'approuvee_bureau')"
            : "statut = 'soumise'";
        $pdo->prepare(
            "UPDATE compta_notes_frais
             SET statut = 'refusee', traite_par = ?, traite_le = NOW(), commentaire_tresorier = ?
             WHERE id = ? AND $allowed"
        )->execute([$actorId, $comment, $noteId]);
    }

    return ['ok' => true, 'msg' => 'Demande refusée.'];
}
