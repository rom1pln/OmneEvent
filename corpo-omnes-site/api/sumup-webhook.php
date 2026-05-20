<?php
/**
 * api/sumup-webhook.php
 *
 * Webhook SumUp : reçoit les notifications de paiement.
 * À configurer dans le dashboard SumUp : Settings → Developers → Webhooks
 *   URL  : https://corpoomnes.42web.io/api/sumup-webhook.php
 *   Évts : checkout.paid, checkout.failed, checkout.canceled
 *
 * Avantage majeur : si la page de succès SumUp reste blanche / l'utilisateur
 * ferme l'onglet, le billet est quand même créé côté serveur dès que SumUp
 * confirme le paiement.
 *
 * Sécurité (à activer plus tard) :
 *   - Vérifier la signature `X-Sumup-Signature` avec le secret du webhook.
 *   - Ici, on se contente de re-vérifier le statut via l'API SumUp avant de
 *     créer les billets, donc même sans signature, on n'est pas vulnérable
 *     à un faux "checkout.paid" envoyé par un tiers.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/billetterie.php';
require_once __DIR__ . '/../includes/sumup.php';
require_once __DIR__ . '/../includes/env.php';

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input') ?: '';
$evt = json_decode($raw, true);

if (!is_array($evt) || empty($evt['id']) && empty($evt['checkout_id']) && empty($evt['data']['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid payload']);
    exit;
}

// SumUp envoie soit { "id": "...", "event_type": "...", "payload": {...} }
// soit { "event_type":"...", "data":{ "id":"...", "status":"PAID" } } selon la version.
// On tente d'extraire l'id du checkout.
$checkoutId = (string)(
    $evt['payload']['checkout_id']
    ?? $evt['data']['id']
    ?? $evt['checkout_id']
    ?? $evt['id']
    ?? ''
);
$eventType = (string)($evt['event_type'] ?? $evt['type'] ?? '');

if ($checkoutId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'missing checkout id']);
    exit;
}

// ── 1. Retrouve la transaction interne ────────────────────────
$tx = $pdo->prepare("SELECT * FROM paiement_transactions WHERE provider_ref = ? LIMIT 1");
$tx->execute([$checkoutId]);
$transaction = $tx->fetch();
if (!$transaction) {
    require_once __DIR__ . '/../includes/boutique.php';
    try {
        if (boutique_webhook_process($pdo, $checkoutId, 'sumup')) {
            echo json_encode(['ok' => true, 'note' => 'boutique order']);
            exit;
        }
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => 'boutique webhook', 'detail' => $e->getMessage()]);
        exit;
    }
    echo json_encode(['ok' => true, 'note' => 'unknown checkout, ignored']);
    exit;
}

// Déjà traitée → réponds 200 sans rien faire (idempotence)
if (in_array($transaction['statut'], ['paye', 'echec', 'annule', 'rembourse'], true)) {
    echo json_encode(['ok' => true, 'note' => 'already processed', 'statut' => $transaction['statut']]);
    exit;
}

// ── 2. Re-vérifie le statut via l'API SumUp (ne fait confiance qu'à SumUp) ──
$status = sumup_get_checkout_status($checkoutId);
if ($status !== 'paid' && $status !== 'failed') {
    // Statut pas encore définitif → on attend un autre webhook
    echo json_encode(['ok' => true, 'note' => 'status not final', 'status' => $status]);
    exit;
}

// ── 3. Échec → marque la transaction comme telle ──────────────
if ($status === 'failed') {
    $pdo->prepare("UPDATE paiement_transactions SET statut='echec' WHERE id=?")
        ->execute([$transaction['id']]);
    echo json_encode(['ok' => true, 'note' => 'marked failed']);
    exit;
}

// ── 4. Paiement OK → crée les billets si pas déjà fait ────────
$pdo->beginTransaction();
try {
    // Recheck dans la transaction pour éviter une double-création (concurrence webhook/callback client)
    $check = $pdo->prepare("SELECT statut FROM paiement_transactions WHERE id=? FOR UPDATE");
    $check->execute([$transaction['id']]);
    $curStatut = (string)$check->fetchColumn();
    if ($curStatut === 'paye') {
        $pdo->commit();
        echo json_encode(['ok' => true, 'note' => 'already paid (race)']);
        exit;
    }

    $pdo->prepare("UPDATE paiement_transactions SET statut='paye' WHERE id=?")
        ->execute([$transaction['id']]);

    $payload = json_decode((string)($transaction['payload'] ?? '{}'), true) ?: [];
    $codePromo = $payload['code_promo'] ?? null;
    $createdIds = billet_fulfill_from_transaction($pdo, $transaction, $payload);

    if ($codePromo && !empty($createdIds)) {
        $c = $pdo->prepare("SELECT id FROM codes_promo WHERE code=? AND (evenement_id=? OR evenement_id IS NULL) LIMIT 1");
        $c->execute([$codePromo, (int)$transaction['evenement_id']]);
        $cid = (int)$c->fetchColumn();
        if ($cid) code_promo_consume($pdo, $cid, count($createdIds));
    }

    if (!empty($createdIds)) {
        $pdo->prepare("UPDATE paiement_transactions SET inscription_id=? WHERE id=?")
            ->execute([$createdIds[0], (int)$transaction['id']]);
    }

    $pdo->commit();

    // Envoi du mail avec billets (PDF en pièce jointe) - hors transaction.
    // billet_send_mail_for_ids() gère le verrou anti-doublon via le payload de tx.
    if (!empty($createdIds)) {
        try { billet_send_mail_for_ids($pdo, $createdIds, (int)$transaction['id']); }
        catch (Throwable $e) { error_log('[sumup-webhook] mail err: ' . $e->getMessage()); }
    }

    require_once __DIR__ . '/../includes/comptabilite.php';
    compta_try_auto_import_billetterie($pdo, (int)$transaction['id']);

    echo json_encode([
        'ok' => true,
        'note' => 'tickets created',
        'count' => count($createdIds),
        'event_type' => $eventType,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[sumup-webhook] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'internal error']);
}
