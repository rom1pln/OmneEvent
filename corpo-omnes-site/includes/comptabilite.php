<?php

declare(strict_types=1);

function compta_has_source_columns(PDO $pdo): bool {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        $st = $pdo->prepare(
            "SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'compta_transactions'
               AND COLUMN_NAME = 'source_type'
             LIMIT 1"
        );
        $st->execute();
        $cache = (bool)$st->fetchColumn();
    } catch (Throwable $e) {
        $cache = false;
    }
    return $cache;
}

function compta_assoc_to_structure(PDO $pdo, int $assoId): ?array {
    $st = $pdo->prepare('SELECT id, type FROM associations WHERE id = ? LIMIT 1');
    $st->execute([$assoId]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if (!$r) {
        return null;
    }
    $t = strtolower((string)($r['type'] ?? 'asso'));
    $type = match ($t) {
        'bde' => 'bde',
        'bds' => 'bds',
        default => 'asso',
    };
    return ['type' => $type, 'id' => (int)$r['id']];
}

function compta_structure_from_event(PDO $pdo, int $eventId): ?array {
    $st = $pdo->prepare('SELECT structure_type, structure_id FROM evenements WHERE id = ? LIMIT 1');
    $st->execute([$eventId]);
    $ev = $st->fetch(PDO::FETCH_ASSOC);
    if (!$ev || !(int)($ev['structure_id'] ?? 0)) {
        return null;
    }
    $stType = (string)($ev['structure_type'] ?? '');
    $stId   = (int)$ev['structure_id'];
    if ($stType === 'sport') {
        return ['type' => 'sport', 'id' => $stId];
    }
    if ($stType === 'asso') {
        return compta_assoc_to_structure($pdo, $stId);
    }
    return null;
}

function compta_structure_matches(string $selType, int $selId, string $structType, int $structId): bool {
    return $selType === $structType && $selId === $structId;
}

function compta_provider_to_mode(string $provider): string {
    return match (strtolower($provider)) {
        'stripe', 'sumup', 'mock_stripe' => 'carte',
        'manuel', 'free', 'mock' => 'autre',
        default => 'carte',
    };
}

function compta_find_category_id(PDO $pdo, string $nom, string $type = 'recette'): ?int {
    $st = $pdo->prepare(
        "SELECT id FROM compta_categories
         WHERE nom = ? AND type = ? AND archive = 0
         ORDER BY structure_type IS NULL DESC
         LIMIT 1"
    );
    $st->execute([$nom, $type]);
    $id = $st->fetchColumn();
    return $id ? (int)$id : null;
}

function compta_get_default_compte_id(PDO $pdo, string $structType, int $structId): ?int {
    $st = $pdo->prepare(
        "SELECT id FROM compta_comptes
         WHERE structure_type = ? AND structure_id = ? AND archive = 0
         ORDER BY type = 'banque' DESC, id ASC
         LIMIT 1"
    );
    $st->execute([$structType, $structId]);
    $id = $st->fetchColumn();
    return $id ? (int)$id : null;
}

function compta_tx_exists_for_source(PDO $pdo, string $sourceType, int $sourceId): bool {
    if (!compta_has_source_columns($pdo)) {
        return false;
    }
    $st = $pdo->prepare(
        'SELECT 1 FROM compta_transactions WHERE source_type = ? AND source_id = ? LIMIT 1'
    );
    $st->execute([$sourceType, $sourceId]);
    return (bool)$st->fetchColumn();
}

function compta_import_billetterie(PDO $pdo, int $paiementId, ?int $userId = null): array {
    if (!compta_has_source_columns($pdo)) {
        return ['ok' => false, 'msg' => 'Applique la migration « compta_tx_source_link » dans Migrations DB.'];
    }
    if (compta_tx_exists_for_source($pdo, 'billetterie', $paiementId)) {
        return ['ok' => true, 'skipped' => true, 'msg' => 'Déjà enregistré en compta.'];
    }

    $st = $pdo->prepare(
        "SELECT pt.*, e.titre AS evt_titre, e.date AS evt_date
         FROM paiement_transactions pt
         JOIN evenements e ON e.id = pt.evenement_id
         WHERE pt.id = ? AND pt.statut = 'paye'
         LIMIT 1"
    );
    $st->execute([$paiementId]);
    $pt = $st->fetch(PDO::FETCH_ASSOC);
    if (!$pt) {
        return ['ok' => false, 'msg' => 'Paiement introuvable ou non validé.'];
    }

    $struct = compta_structure_from_event($pdo, (int)$pt['evenement_id']);
    if (!$struct) {
        return ['ok' => false, 'msg' => 'Événement Corpo sans structure comptable.'];
    }

    $montant = (float)$pt['montant'];
    if ($montant <= 0) {
        return ['ok' => false, 'msg' => 'Montant nul.'];
    }

    $catId    = compta_find_category_id($pdo, 'Billetterie', 'recette');
    $compteId = compta_get_default_compte_id($pdo, $struct['type'], $struct['id']);
    $dateOp   = date('Y-m-d', strtotime((string)($pt['updated_at'] ?? $pt['created_at'] ?? 'now')));
    $libelle  = 'Billetterie - ' . (string)$pt['evt_titre'];
    $ref      = $pt['provider_ref'] ? (string)$pt['provider_ref'] : ('PAY-' . $paiementId);
    $notes    = 'Import auto · ' . strtoupper((string)$pt['provider']) . ' · événement #' . (int)$pt['evenement_id'];

    $ins = $pdo->prepare(
        "INSERT INTO compta_transactions
         (structure_type, structure_id, compte_id, categorie_id, evenement_id,
          source_type, source_id, type, montant, date_operation, libelle, notes, reference,
          mode_paiement, cree_par)
         VALUES (?, ?, ?, ?, ?, 'billetterie', ?, 'recette', ?, ?, ?, ?, ?, ?, ?)"
    );
    $ins->execute([
        $struct['type'],
        $struct['id'],
        $compteId,
        $catId,
        (int)$pt['evenement_id'],
        $paiementId,
        $montant,
        $dateOp,
        $libelle,
        $notes,
        $ref,
        compta_provider_to_mode((string)$pt['provider']),
        $userId,
    ]);

    return ['ok' => true, 'tx_id' => (int)$pdo->lastInsertId(), 'msg' => 'Recette billetterie importée.'];
}

function compta_import_boutique_ligne(PDO $pdo, int $ligneId, ?int $userId = null): array {
    if (!compta_has_source_columns($pdo)) {
        return ['ok' => false, 'msg' => 'Migration source compta manquante.'];
    }
    if (compta_tx_exists_for_source($pdo, 'boutique', $ligneId)) {
        return ['ok' => true, 'skipped' => true, 'msg' => 'Ligne déjà importée.'];
    }

    $st = $pdo->prepare(
        "SELECT l.*, c.statut AS cmd_statut, c.provider, c.provider_ref, c.email, c.created_at AS cmd_date
         FROM boutique_commande_lignes l
         JOIN boutique_commandes c ON c.id = l.commande_id
         WHERE l.id = ?
         LIMIT 1"
    );
    $st->execute([$ligneId]);
    $ln = $st->fetch(PDO::FETCH_ASSOC);
    if (!$ln || ($ln['cmd_statut'] ?? '') !== 'paye') {
        return ['ok' => false, 'msg' => 'Commande non payée ou ligne introuvable.'];
    }

    $structType = (string)$ln['structure_type'];
    $structId   = (int)$ln['structure_id'];
    $montant    = round((float)$ln['prix_unitaire'] * (int)$ln['quantite'], 2);
    if ($montant <= 0) {
        return ['ok' => false, 'msg' => 'Montant nul.'];
    }

    $catId = compta_find_category_id($pdo, 'Boutique', 'recette')
        ?? compta_find_category_id($pdo, 'Autres recettes', 'recette');
    $compteId = compta_get_default_compte_id($pdo, $structType, $structId);
    $dateOp   = date('Y-m-d', strtotime((string)($ln['cmd_date'] ?? 'now')));
    $libelle  = 'Boutique - ' . (string)$ln['titre_snapshot'] . ' ×' . (int)$ln['quantite'];
    $ref      = $ln['provider_ref'] ? (string)$ln['provider_ref'] : ('CMD-' . (int)$ln['commande_id']);
    $notes    = 'Import auto · commande #' . (int)$ln['commande_id'] . ' · ' . (string)$ln['email'];

    $ins = $pdo->prepare(
        "INSERT INTO compta_transactions
         (structure_type, structure_id, compte_id, categorie_id, evenement_id,
          source_type, source_id, type, montant, date_operation, libelle, notes, reference,
          mode_paiement, cree_par)
         VALUES (?, ?, ?, ?, NULL, 'boutique', ?, 'recette', ?, ?, ?, ?, ?, ?, ?)"
    );
    $ins->execute([
        $structType,
        $structId,
        $compteId,
        $catId,
        $ligneId,
        $montant,
        $dateOp,
        $libelle,
        $notes,
        $ref,
        compta_provider_to_mode((string)$ln['provider']),
        $userId,
    ]);

    return ['ok' => true, 'tx_id' => (int)$pdo->lastInsertId(), 'msg' => 'Vente boutique importée.'];
}

function compta_import_all_pending(PDO $pdo, string $structType, int $structId, ?int $userId = null): array {
    $out = ['imported' => 0, 'skipped' => 0, 'errors' => []];
    foreach (compta_pending_billetterie($pdo, $structType, $structId) as $row) {
        $r = compta_import_billetterie($pdo, (int)$row['id'], $userId);
        if (!empty($r['skipped'])) {
            $out['skipped']++;
        } elseif ($r['ok']) {
            $out['imported']++;
        } else {
            $out['errors'][] = '#' . $row['id'] . ' : ' . ($r['msg'] ?? 'Erreur');
        }
    }
    foreach (compta_pending_boutique_lignes($pdo, $structType, $structId) as $row) {
        $r = compta_import_boutique_ligne($pdo, (int)$row['ligne_id'], $userId);
        if (!empty($r['skipped'])) {
            $out['skipped']++;
        } elseif ($r['ok']) {
            $out['imported']++;
        } else {
            $out['errors'][] = 'Ligne #' . $row['ligne_id'] . ' : ' . ($r['msg'] ?? 'Erreur');
        }
    }
    return $out;
}

function compta_try_auto_import_billetterie(PDO $pdo, int $paiementId): void {
    try {
        compta_import_billetterie($pdo, $paiementId, null);
    } catch (Throwable $e) {
        error_log('[compta] auto billetterie #' . $paiementId . ' : ' . $e->getMessage());
    }
}

function compta_try_auto_import_boutique_commande(PDO $pdo, int $commandeId): void {
    try {
        $st = $pdo->prepare('SELECT id FROM boutique_commande_lignes WHERE commande_id = ?');
        $st->execute([$commandeId]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $lid) {
            compta_import_boutique_ligne($pdo, (int)$lid, null);
        }
    } catch (Throwable $e) {
        error_log('[compta] auto boutique cmd #' . $commandeId . ' : ' . $e->getMessage());
    }
}

function compta_pending_billetterie(PDO $pdo, string $structType, int $structId): array {
    if (!compta_has_source_columns($pdo)) {
        return [];
    }
    $evtFilter = ($structType === 'sport')
        ? 'e.structure_type = \'sport\' AND e.structure_id = ?'
        : 'e.structure_type = \'asso\' AND e.structure_id = ?';

    $sql = "SELECT pt.id, pt.montant, pt.provider, pt.provider_ref, pt.created_at, pt.updated_at,
                   e.id AS evenement_id, e.titre AS evt_titre, e.date AS evt_date
            FROM paiement_transactions pt
            JOIN evenements e ON e.id = pt.evenement_id
            WHERE pt.statut = 'paye'
              AND $evtFilter
              AND NOT EXISTS (
                SELECT 1 FROM compta_transactions ct
                WHERE ct.source_type = 'billetterie' AND ct.source_id = pt.id
              )
            ORDER BY pt.updated_at DESC
            LIMIT 200";
    $st = $pdo->prepare($sql);
    $st->execute([$structId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function compta_pending_boutique_lignes(PDO $pdo, string $structType, int $structId): array {
    if (!compta_has_source_columns($pdo)) {
        return [];
    }
    $st = $pdo->prepare(
        "SELECT l.id AS ligne_id, l.commande_id, l.titre_snapshot, l.prix_unitaire, l.quantite,
                c.email, c.provider, c.provider_ref, c.created_at AS cmd_date
         FROM boutique_commande_lignes l
         JOIN boutique_commandes c ON c.id = l.commande_id
         WHERE c.statut = 'paye'
           AND l.structure_type = ? AND l.structure_id = ?
           AND NOT EXISTS (
             SELECT 1 FROM compta_transactions ct
             WHERE ct.source_type = 'boutique' AND ct.source_id = l.id
           )
         ORDER BY c.created_at DESC
         LIMIT 200"
    );
    $st->execute([$structType, $structId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function compta_encaissements_summary(PDO $pdo, string $structType, int $structId): array {
    $sum = [
        'billetterie' => ['online' => 0.0, 'compta' => 0.0, 'pending' => 0.0],
        'boutique'    => ['online' => 0.0, 'compta' => 0.0, 'pending' => 0.0],
    ];

    $evtFilter = ($structType === 'sport')
        ? 'e.structure_type = \'sport\' AND e.structure_id = ?'
        : 'e.structure_type = \'asso\' AND e.structure_id = ?';

    try {
        $st = $pdo->prepare(
            "SELECT COALESCE(SUM(pt.montant), 0)
             FROM paiement_transactions pt
             JOIN evenements e ON e.id = pt.evenement_id
             WHERE pt.statut = 'paye' AND $evtFilter"
        );
        $st->execute([$structId]);
        $sum['billetterie']['online'] = (float)$st->fetchColumn();
    } catch (Throwable $e) {
    }

    if (compta_has_source_columns($pdo)) {
        foreach (compta_pending_billetterie($pdo, $structType, $structId) as $p) {
            $sum['billetterie']['pending'] += (float)$p['montant'];
        }
        $st = $pdo->prepare(
            "SELECT COALESCE(SUM(montant), 0) FROM compta_transactions
             WHERE structure_type = ? AND structure_id = ? AND type = 'recette' AND source_type = 'billetterie'"
        );
        $st->execute([$structType, $structId]);
        $sum['billetterie']['compta'] = (float)$st->fetchColumn();
    }

    try {
        $st = $pdo->prepare(
            "SELECT COALESCE(SUM(l.prix_unitaire * l.quantite), 0)
             FROM boutique_commande_lignes l
             JOIN boutique_commandes c ON c.id = l.commande_id
             WHERE c.statut = 'paye' AND l.structure_type = ? AND l.structure_id = ?"
        );
        $st->execute([$structType, $structId]);
        $sum['boutique']['online'] = (float)$st->fetchColumn();
    } catch (Throwable $e) {
    }

    if (compta_has_source_columns($pdo)) {
        foreach (compta_pending_boutique_lignes($pdo, $structType, $structId) as $p) {
            $sum['boutique']['pending'] += (float)$p['prix_unitaire'] * (int)$p['quantite'];
        }
        $st = $pdo->prepare(
            "SELECT COALESCE(SUM(montant), 0) FROM compta_transactions
             WHERE structure_type = ? AND structure_id = ? AND type = 'recette' AND source_type = 'boutique'"
        );
        $st->execute([$structType, $structId]);
        $sum['boutique']['compta'] = (float)$st->fetchColumn();
    }

    return $sum;
}
