<?php

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/paiements.php';
require_once __DIR__ . '/mailer.php';

const BOUTIQUE_CART_KEY = 'boutique_cart';

function boutique_db_ready(PDO $pdo): bool {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        $st = $pdo->prepare(
            "SELECT 1 FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'boutique_produits' LIMIT 1"
        );
        $st->execute();
        $cache = (bool)$st->fetchColumn();
    } catch (Throwable $e) {
        $cache = false;
    }
    return $cache;
}

function boutique_cart_get(): array {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return [];
    }
    $c = $_SESSION[BOUTIQUE_CART_KEY] ?? [];
    if (!is_array($c)) {
        return [];
    }
    $out = [];
    foreach ($c as $row) {
        $id = (int)($row['id'] ?? 0);
        $q  = max(1, (int)($row['q'] ?? 1));
        if ($id > 0) {
            $out[] = ['id' => $id, 'q' => $q];
        }
    }
    return $out;
}

function boutique_cart_set(array $items): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION[BOUTIQUE_CART_KEY] = array_values($items);
    }
}

function boutique_cart_add(int $produitId, int $qty = 1): void {
    $qty = max(1, min(99, $qty));
    $cart = boutique_cart_get();
    $found = false;
    foreach ($cart as &$row) {
        if ($row['id'] === $produitId) {
            $row['q'] = min(99, $row['q'] + $qty);
            $found    = true;
            break;
        }
    }
    unset($row);
    if (!$found) {
        $cart[] = ['id' => $produitId, 'q' => $qty];
    }
    boutique_cart_set($cart);
}

function boutique_cart_remove(int $produitId): void {
    $cart = array_values(array_filter(boutique_cart_get(), fn($r) => (int)$r['id'] !== $produitId));
    boutique_cart_set($cart);
}

function boutique_cart_clear(): void {
    boutique_cart_set([]);
}

function boutique_cart_validate(PDO $pdo, array $cart): array {
    if (empty($cart)) {
        return ['ok' => false, 'msg' => 'Panier vide.'];
    }
    $ids = array_unique(array_map(fn($r) => (int)$r['id'], $cart));
    if (empty($ids)) {
        return ['ok' => false, 'msg' => 'Panier invalide.'];
    }
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare(
        "SELECT p.*, a.nom AS asso_nom, a.ecole AS asso_ecole
         FROM boutique_produits p
         JOIN associations a ON a.id = p.structure_id
         WHERE p.id IN ($ph) AND p.statut = 'publie'"
    );
    $st->execute($ids);
    $byId = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $byId[(int)$p['id']] = $p;
    }
    $lignes = [];
    foreach ($cart as $row) {
        $pid = (int)$row['id'];
        $q   = (int)$row['q'];
        if (!isset($byId[$pid])) {
            return ['ok' => false, 'msg' => 'Un article du panier n’est plus disponible.'];
        }
        $p = $byId[$pid];
        if ((int)$p['stock'] < $q) {
            return ['ok' => false, 'msg' => 'Stock insuffisant pour « ' . htmlspecialchars((string)$p['titre']) . ' ».'];
        }
        $lignes[] = [
            'produit_id'             => $pid,
            'structure_type'         => (string)$p['structure_type'],
            'structure_id'           => (int)$p['structure_id'],
            'titre'                  => (string)$p['titre'],
            'prix_unitaire'          => (float)$p['prix'],
            'quantite'               => $q,
            'frais_a_charge_client'  => (int)($p['frais_a_charge_client'] ?? 0) === 1,
        ];
    }
    return ['ok' => true, 'lignes' => $lignes];
}

function boutique_order_public_url(int $commandeId): string {
    return corpo_mail_app_url('boutique.php?order=' . $commandeId);
}

function boutique_compute_payment_amounts(array $lignes): array {
    $subtotal = 0.0;
    foreach ($lignes as $ln) {
        $subtotal += (float)$ln['prix_unitaire'] * (int)$ln['quantite'];
    }
    $subtotal = round($subtotal, 2);
    $fraisAuClient = false;
    foreach ($lignes as $ln) {
        if (!empty($ln['frais_a_charge_client'])) {
            $fraisAuClient = true;
            break;
        }
    }
    $feeInfo = paiement_calcule_frais($subtotal);
    $providerBase = (string)$feeInfo['provider'];
    $montant = $fraisAuClient ? (float)$feeInfo['client_total'] : $subtotal;
    $montant = round($montant, 2);
    if (!$fraisAuClient) {
        $feeInfo = paiement_calcule_frais($montant, $providerBase);
    } else {
        $feeInfo = paiement_calcule_frais($montant);
    }
    $provider = (string)$feeInfo['provider'];
    return [
        'subtotal'         => $subtotal,
        'montant_facture'  => $montant,
        'frais_au_client'  => $fraisAuClient,
        'fee_info'         => $feeInfo,
        'provider'         => $provider,
    ];
}

function boutique_create_checkout(PDO $pdo, array $lignes, string $email, string $nom, string $prenom, ?int $userId): array {
    $email = trim($email);
    $nom   = trim($nom);
    $prenom = trim($prenom);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'msg' => 'Email invalide.'];
    }
    if ($nom === '' || $prenom === '') {
        return ['ok' => false, 'msg' => 'Merci de renseigner ton nom et ton prénom.'];
    }

    $cartRecheck = [];
    foreach ($lignes as $ln) {
        $cartRecheck[] = ['id' => (int)$ln['produit_id'], 'q' => (int)$ln['quantite']];
    }
    $v = boutique_cart_validate($pdo, $cartRecheck);
    if (empty($v['ok']) || empty($v['lignes'])) {
        return ['ok' => false, 'msg' => (string)($v['msg'] ?? 'Panier invalide.')];
    }
    $lignes = $v['lignes'];

    $pay = boutique_compute_payment_amounts($lignes);
    $montant = $pay['montant_facture'];
    $payloadMeta = [
        'subtotal'                => $pay['subtotal'],
        'frais_a_charge_client'   => $pay['frais_au_client'],
        'fee_info'                => $pay['fee_info'],
        'lignes'                  => $lignes,
    ];

    $pdo->beginTransaction();
    $orderId = 0;
    try {
        $pdo->prepare(
            "INSERT INTO boutique_commandes
              (email, nom, prenom, user_id, montant_total, provider, provider_ref, statut, payload)
             VALUES (?,?,?,?,?,?,?,?,?)"
        )->execute([
            $email,
            $nom,
            $prenom,
            $userId ?: null,
            $montant,
            '',
            null,
            'init',
            json_encode($payloadMeta, JSON_UNESCAPED_UNICODE),
        ]);
        $orderId = (int)$pdo->lastInsertId();
        $insL = $pdo->prepare(
            "INSERT INTO boutique_commande_lignes
              (commande_id, produit_id, structure_type, structure_id, titre_snapshot, prix_unitaire, quantite)
             VALUES (?,?,?,?,?,?,?)"
        );
        foreach ($lignes as $ln) {
            $insL->execute([
                $orderId,
                (int)$ln['produit_id'],
                (string)$ln['structure_type'],
                (int)$ln['structure_id'],
                (string)$ln['titre'],
                (float)$ln['prix_unitaire'],
                (int)$ln['quantite'],
            ]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'msg' => 'Erreur enregistrement commande.'];
    }

    if ($montant <= 0) {
        $pdo->prepare("UPDATE boutique_commandes SET provider = 'free', statut = 'init' WHERE id = ?")->execute([$orderId]);
        try {
            boutique_finalize_order_paid($pdo, $orderId);
        } catch (Throwable $e) {
            return ['ok' => false, 'msg' => $e->getMessage()];
        }
        boutique_cart_clear();
        return ['ok' => true, 'redirect' => boutique_order_public_url($orderId), 'order_id' => $orderId];
    }

    $reference = 'bout' . $orderId;
    $returnUrl = boutique_order_public_url($orderId);

    try {
        $checkout = paiement_create_checkout(
            $montant,
            $reference,
            $email,
            'Boutique Corpo Omnes - commande n°' . $orderId,
            $returnUrl,
            $pay['frais_au_client'] ? (string)$pay['fee_info']['provider'] : null
        );
        $checkoutId = trim((string)($checkout['checkout_id'] ?? ''));
        $redirectUrl = trim((string)($checkout['redirect_url'] ?? ''));
        if ($checkoutId === '' || $redirectUrl === '') {
            $pdo->prepare("UPDATE boutique_commandes SET statut='echec' WHERE id=?")->execute([$orderId]);
            return ['ok' => false, 'msg' => 'Réponse prestataire de paiement incomplète (référence ou URL manquante).'];
        }
        $pdo->prepare(
            "UPDATE boutique_commandes SET provider=?, provider_ref=?, statut='en_attente' WHERE id=?"
        )->execute([$checkout['provider'], $checkoutId, $orderId]);
    } catch (Throwable $e) {
        $pdo->prepare("UPDATE boutique_commandes SET statut='echec' WHERE id=?")->execute([$orderId]);
        return ['ok' => false, 'msg' => 'Échec création paiement : ' . $e->getMessage()];
    }

    boutique_cart_clear();
    return ['ok' => true, 'redirect' => $redirectUrl, 'order_id' => $orderId];
}

function boutique_catalog_list(PDO $pdo, array $filters): array {

    $where = ["p.statut = 'publie'"];
    $params = [];
    if (!empty($filters['ecole'])) {
        $where[] = 'a.ecole = ?';
        $params[] = $filters['ecole'];
    }
    if (!empty($filters['asso'])) {
        $where[] = 'a.id = ?';
        $params[] = (int)$filters['asso'];
    }
    if (isset($filters['pmin']) && $filters['pmin'] !== '' && is_numeric($filters['pmin'])) {
        $where[] = 'p.prix >= ?';
        $params[] = (float)$filters['pmin'];
    }
    if (isset($filters['pmax']) && $filters['pmax'] !== '' && is_numeric($filters['pmax'])) {
        $where[] = 'p.prix <= ?';
        $params[] = (float)$filters['pmax'];
    }
    if (!empty($filters['taille'])) {
        $where[] = 'p.taille = ?';
        $params[] = $filters['taille'];
    }
    if (!empty($filters['categorie'])) {
        $where[] = 'p.categorie = ?';
        $params[] = $filters['categorie'];
    }
    if (!empty($filters['q'])) {
        $where[] = '(p.titre LIKE ? OR p.description LIKE ?)';
        $q = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $filters['q']) . '%';
        $params[] = $q;
        $params[] = $q;
    }
    $tri = $filters['tri'] ?? 'recent';
    $orderBy = match ($tri) {
        'prix_asc'  => 'p.prix ASC, p.id DESC',
        'prix_desc' => 'p.prix DESC, p.id DESC',
        'nom'       => 'p.titre ASC',
        default     => 'p.id DESC',
    };
    $sql = 'SELECT p.*, a.nom AS asso_nom, a.slug AS asso_slug, a.ecole AS asso_ecole
            FROM boutique_produits p
            JOIN associations a ON a.id = p.structure_id
            WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $orderBy;
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function boutique_catalog_distinct_ecoles(PDO $pdo): array {
    $st = $pdo->query(
        "SELECT DISTINCT a.ecole FROM boutique_produits p
         JOIN associations a ON a.id = p.structure_id
         WHERE p.statut = 'publie' AND a.ecole IS NOT NULL AND a.ecole <> ''
         ORDER BY a.ecole"
    );
    return $st ? array_values(array_filter($st->fetchAll(PDO::FETCH_COLUMN) ?: [])) : [];
}

function boutique_catalog_assos_for_ecole(PDO $pdo, ?string $ecole): array {
    $sql = "SELECT DISTINCT a.id, a.nom FROM boutique_produits p
            JOIN associations a ON a.id = p.structure_id
            WHERE p.statut = 'publie'";
    $params = [];
    if ($ecole !== null && $ecole !== '') {
        $sql .= ' AND a.ecole = ?';
        $params[] = $ecole;
    }
    $sql .= ' ORDER BY a.nom';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function boutique_catalog_distinct_tailles(PDO $pdo): array {
    $st = $pdo->query(
        "SELECT DISTINCT p.taille FROM boutique_produits p
         WHERE p.statut = 'publie' AND p.taille IS NOT NULL AND p.taille <> ''
         ORDER BY p.taille"
    );
    return $st ? array_values(array_filter($st->fetchAll(PDO::FETCH_COLUMN) ?: [])) : [];
}

function boutique_catalog_distinct_categories(PDO $pdo): array {
    $st = $pdo->query(
        "SELECT DISTINCT p.categorie FROM boutique_produits p
         WHERE p.statut = 'publie' AND p.categorie IS NOT NULL AND p.categorie <> ''
         ORDER BY p.categorie"
    );
    return $st ? array_values(array_filter($st->fetchAll(PDO::FETCH_COLUMN) ?: [])) : [];
}

function boutique_finalize_order_paid(PDO $pdo, int $commandeId): void {
    $pdo->beginTransaction();
    try {
        $c = $pdo->prepare("SELECT * FROM boutique_commandes WHERE id = ? FOR UPDATE");
        $c->execute([$commandeId]);
        $cmd = $c->fetch(PDO::FETCH_ASSOC);
        if (!$cmd) {
            $pdo->rollBack();
            return;
        }
        if (($cmd['statut'] ?? '') === 'paye') {
            $pdo->commit();
            return;
        }

        $lignes = $pdo->prepare('SELECT * FROM boutique_commande_lignes WHERE commande_id = ?');
        $lignes->execute([$commandeId]);
        $rows = $lignes->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $ln) {
            $pid = (int)$ln['produit_id'];
            $q   = (int)$ln['quantite'];
            $u   = $pdo->prepare('UPDATE boutique_produits SET stock = stock - ? WHERE id = ? AND stock >= ?');
            $u->execute([$q, $pid, $q]);
            if ($u->rowCount() === 0) {
                throw new RuntimeException('Stock insuffisant pour produit #' . $pid);
            }
        }

        $pdo->prepare("UPDATE boutique_commandes SET statut = 'paye', updated_at = CURRENT_TIMESTAMP WHERE id = ?")
            ->execute([$commandeId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    $c2 = $pdo->prepare('SELECT * FROM boutique_commandes WHERE id = ?');
    $c2->execute([$commandeId]);
    $cmd = $c2->fetch(PDO::FETCH_ASSOC);
    if (!$cmd) {
        return;
    }
    $payload = json_decode((string)($cmd['payload'] ?? '{}'), true) ?: [];
    if (!empty($payload['mail_sent'])) {
        return;
    }

    $lignes = $pdo->prepare('SELECT * FROM boutique_commande_lignes WHERE commande_id = ? ORDER BY id');
    $lignes->execute([$commandeId]);
    $rows = $lignes->fetchAll(PDO::FETCH_ASSOC);

    $linesHtml = '<ul style="margin:0;padding-left:20px">';
    foreach ($rows as $ln) {
        $linesHtml .= '<li>' . htmlspecialchars((string)$ln['titre_snapshot'])
            . ' × ' . (int)$ln['quantite'] . ' - '
            . number_format((float)$ln['prix_unitaire'] * (int)$ln['quantite'], 2, ',', ' ') . ' €</li>';
    }
    $linesHtml .= '</ul>';

    $email = (string)($cmd['email'] ?? '');
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $subject = 'Corpo Omnes - Commande boutique n°' . $commandeId;
    $body    = corpo_mail_layout(
        'Commande confirmée',
        '<p>Bonjour <strong>' . htmlspecialchars(trim(($cmd['prenom'] ?? '') . ' ' . ($cmd['nom'] ?? ''))) . '</strong>,</p>'
        . '<p>Ta commande <strong>n°' . $commandeId . '</strong> a bien été enregistrée (paiement reçu).</p>'
        . '<h3 style="margin:24px 0 8px;font-size:16px">Détail</h3>' . $linesHtml
        . '<p style="margin-top:20px"><strong>Total :</strong> '
        . number_format((float)$cmd['montant_total'], 2, ',', ' ') . ' €</p>',
        corpo_mail_app_url('boutique.php?order=' . $commandeId),
        'Voir ma commande'
    );

    try {
        corpo_mail_send($email, $subject, $body);
    } catch (Throwable $e) {
        corpo_mail_log('[boutique] mail commande ' . $commandeId . ' : ' . $e->getMessage());
    }

    $payload['mail_sent'] = true;
    $pdo->prepare('UPDATE boutique_commandes SET payload = ? WHERE id = ?')
        ->execute([json_encode($payload, JSON_UNESCAPED_UNICODE), $commandeId]);

    require_once __DIR__ . '/comptabilite.php';
    compta_try_auto_import_boutique_commande($pdo, $commandeId);
}

function boutique_webhook_process(PDO $pdo, string $providerRef, string $providerHint): bool {
    $st = $pdo->prepare('SELECT * FROM boutique_commandes WHERE provider_ref = ? LIMIT 1');
    $st->execute([$providerRef]);
    $cmd = $st->fetch(PDO::FETCH_ASSOC);
    if (!$cmd) {
        return false;
    }

    if (in_array($cmd['statut'], ['paye', 'echec', 'annule'], true)) {
        return true;
    }

    $prov = (string)($cmd['provider'] ?? 'sumup');
    $status = paiement_get_status($prov, $providerRef);
    if ($status !== 'paid' && $status !== 'failed') {
        return true;
    }

    if ($status === 'failed') {
        $pdo->prepare("UPDATE boutique_commandes SET statut = 'echec', updated_at = CURRENT_TIMESTAMP WHERE id = ?")
            ->execute([(int)$cmd['id']]);
        return true;
    }

    try {
        boutique_finalize_order_paid($pdo, (int)$cmd['id']);
    } catch (Throwable $e) {
        error_log('[boutique-webhook] finalize ' . $e->getMessage());
        throw $e;
    }

    return true;
}

function boutique_poll_order_payment(PDO $pdo, int $commandeId, bool $forceMockPaid): array {
    $st = $pdo->prepare('SELECT * FROM boutique_commandes WHERE id = ? LIMIT 1');
    $st->execute([$commandeId]);
    $cmd = $st->fetch(PDO::FETCH_ASSOC);
    if (!$cmd) {
        return ['state' => 'missing'];
    }
    if (($cmd['statut'] ?? '') === 'paye') {
        return ['state' => 'paid'];
    }
    if (($cmd['statut'] ?? '') === 'echec') {
        return ['state' => 'failed'];
    }
    if (($cmd['statut'] ?? '') === 'annule') {
        return ['state' => 'cancelled'];
    }

    $ref = (string)($cmd['provider_ref'] ?? '');
    if ($ref === '') {
        return ['state' => 'pending'];
    }

    $prov = (string)($cmd['provider'] ?? 'sumup');
    $status = paiement_get_status($prov, $ref);
    if ($forceMockPaid && paiement_is_mock($prov)) {
        $status = 'paid';
    }
    if ($status === 'pending' || $status === 'unknown') {
        usleep(600000);
        $status = paiement_get_status($prov, $ref);
        if ($forceMockPaid && paiement_is_mock($prov)) {
            $status = 'paid';
        }
    }

    if ($status === 'paid') {
        try {
            boutique_finalize_order_paid($pdo, $commandeId);
        } catch (Throwable $e) {
            return ['state' => 'error', 'msg' => $e->getMessage()];
        }
        return ['state' => 'paid'];
    }
    if ($status === 'failed') {
        $pdo->prepare("UPDATE boutique_commandes SET statut = 'echec' WHERE id = ?")->execute([$commandeId]);
        return ['state' => 'failed'];
    }
    return ['state' => 'pending'];
}

function boutique_orders_list_for_user(PDO $pdo, int $userId): array {
    if (!boutique_db_ready($pdo)) {
        return [];
    }
    $st = $pdo->prepare(
        "SELECT c.*,
                (SELECT COUNT(*) FROM boutique_commande_lignes l WHERE l.commande_id = c.id) AS nb_lignes
         FROM boutique_commandes c
         WHERE c.user_id = ?
            OR (c.user_id IS NULL AND c.email = (SELECT u.email FROM users u WHERE u.id = ? LIMIT 1))
         ORDER BY c.created_at DESC"
    );
    $st->execute([$userId, $userId]);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

const BOUTIQUE_COMMANDE_STATUTS = ['init', 'en_attente', 'paye', 'echec', 'annule'];

function boutique_orders_list_for_admin(PDO $pdo, bool $isCorpo, array $allowedAssoIds, int $filterAssoId = 0): array {
    if (!boutique_db_ready($pdo)) {
        return [];
    }
    $limit = (int)300;
    $subNb = '(SELECT COUNT(*) FROM boutique_commande_lignes l0 WHERE l0.commande_id = c.id)';
    $subAs = '(SELECT GROUP_CONCAT(DISTINCT CONCAT(IFNULL(ax.nom,\'-\'),\' (#\',lx.structure_id,\')\') ORDER BY ax.nom SEPARATOR \' · \')
                FROM boutique_commande_lignes lx
                LEFT JOIN associations ax ON ax.id = lx.structure_id
                WHERE lx.commande_id = c.id)';

    $w     = [];
    $param = [];

    if ($isCorpo) {
        $from = "FROM boutique_commandes c";
        if ($filterAssoId > 0) {
            $w[]    = 'EXISTS (SELECT 1 FROM boutique_commande_lignes lf WHERE lf.commande_id = c.id AND lf.structure_id = ?)';
            $param[] = $filterAssoId;
        }
    } else {
        if ($allowedAssoIds === []) {
            return [];
        }
        $ids = array_values(array_unique(array_map('intval', $allowedAssoIds)));
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $from = "FROM boutique_commandes c
                 INNER JOIN boutique_commande_lignes l ON l.commande_id = c.id";
        $w[]   = "l.structure_id IN ($ph)";
        $param = array_merge($param, $ids);
        if ($filterAssoId > 0) {
            $w[]     = 'l.structure_id = ?';
            $param[] = $filterAssoId;
        }
    }

    $whereSql = $w === [] ? '1=1' : implode(' AND ', $w);
    $sql      = "SELECT DISTINCT c.*, $subNb AS nb_lignes, $subAs AS assos_resume
                 $from
                 WHERE $whereSql
                 ORDER BY c.created_at DESC
                 LIMIT $limit";
    $st = $pdo->prepare($sql);
    $st->execute($param);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function boutique_order_admin_set_statut(PDO $pdo, int $orderId, string $statut, bool $isCorpo, array $allowedAssoIds): array {
    if (!boutique_db_ready($pdo) || $orderId <= 0) {
        return ['ok' => false, 'msg' => 'Commande invalide.'];
    }
    if (!in_array($statut, BOUTIQUE_COMMANDE_STATUTS, true)) {
        return ['ok' => false, 'msg' => 'Statut invalide.'];
    }
    if (boutique_order_admin_detail($pdo, $orderId, $isCorpo, $allowedAssoIds) === null) {
        return ['ok' => false, 'msg' => 'Commande introuvable ou accès refusé.'];
    }
    $pdo->prepare(
        "UPDATE boutique_commandes SET statut = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?"
    )->execute([$statut, $orderId]);
    return ['ok' => true];
}

function boutique_order_admin_detail(PDO $pdo, int $orderId, bool $isCorpo, array $allowedAssoIds): ?array {
    if (!boutique_db_ready($pdo) || $orderId <= 0) {
        return null;
    }
    $st = $pdo->prepare('SELECT * FROM boutique_commandes WHERE id = ? LIMIT 1');
    $st->execute([$orderId]);
    $cmd = $st->fetch(PDO::FETCH_ASSOC);
    if (!$cmd) {
        return null;
    }
    $stL = $pdo->prepare('SELECT * FROM boutique_commande_lignes WHERE commande_id = ? ORDER BY id');
    $stL->execute([$orderId]);
    $allLines = $stL->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if ($allLines === []) {
        if (!$isCorpo && $allowedAssoIds === []) {
            return null;
        }
        return ['commande' => $cmd, 'lignes' => [], 'lignes_autres_structures' => 0];
    }
    if ($isCorpo) {
        return ['commande' => $cmd, 'lignes' => $allLines, 'lignes_autres_structures' => 0];
    }
    $allowed = array_fill_keys(array_map('intval', $allowedAssoIds), true);
    $visible  = [];
    $hidden   = 0;
    foreach ($allLines as $ln) {
        $sid = (int)($ln['structure_id'] ?? 0);
        if (isset($allowed[$sid])) {
            $visible[] = $ln;
        } else {
            $hidden++;
        }
    }
    if ($visible === [] && $hidden > 0) {
        return null;
    }
    return ['commande' => $cmd, 'lignes' => $visible, 'lignes_autres_structures' => $hidden];
}
