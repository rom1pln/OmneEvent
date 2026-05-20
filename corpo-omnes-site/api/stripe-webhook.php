<?php
/**
 * api/stripe-webhook.php
 *
 * Webhook Stripe : reçoit les notifications de paiement pour les checkouts > 25 €.
 * À configurer dans le dashboard Stripe : Developers → Webhooks → Add endpoint
 *   URL  : https://corpoomnes.42web.io/api/stripe-webhook.php
 *   Évts : checkout.session.completed, checkout.session.async_payment_succeeded,
 *          checkout.session.async_payment_failed, checkout.session.expired
 *
 * Comme pour le webhook SumUp, on re-vérifie systématiquement le statut via
 * l'API Stripe avant de créer les billets, donc même sans vérification de
 * signature, on n'est pas vulnérable à un faux "session.completed".
 *
 * Pour activer la vérification de signature : renseigne STRIPE_WEBHOOK_SECRET
 * dans .env (le secret du endpoint, commence par whsec_…).
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/billetterie.php';
require_once __DIR__ . '/../includes/paiements.php';
require_once __DIR__ . '/../includes/env.php';

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input') ?: '';

// (Optionnel) Vérification de signature Stripe - désactivée si pas de secret configuré.
$whSecret = (string)corpo_env('STRIPE_WEBHOOK_SECRET', '');
if ($whSecret !== '') {
    $sigHeader = (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');
    if ($sigHeader === '') {
        http_response_code(400);
        echo json_encode(['error' => 'missing signature']);
        exit;
    }
    // Format : t=timestamp,v1=signature[,v1=signature2]
    $parts = [];
    foreach (explode(',', $sigHeader) as $kv) {
        if (str_contains($kv, '=')) {
            [$k, $v] = explode('=', $kv, 2);
            $parts[trim($k)][] = trim($v);
        }
    }
    $ts = $parts['t'][0] ?? '';
    $sigs = $parts['v1'] ?? [];
    if ($ts === '' || empty($sigs)) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid signature header']);
        exit;
    }
    $payload  = $ts . '.' . $raw;
    $expected = hash_hmac('sha256', $payload, $whSecret);
    $ok = false;
    foreach ($sigs as $s) {
        if (hash_equals($expected, $s)) { $ok = true; break; }
    }
    if (!$ok) {
        http_response_code(400);
        echo json_encode(['error' => 'signature mismatch']);
        exit;
    }
}

$evt = json_decode($raw, true);
if (!is_array($evt)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid payload']);
    exit;
}

$type    = (string)($evt['type'] ?? '');
$session = $evt['data']['object'] ?? null;
if (!is_array($session) || empty($session['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'missing session']);
    exit;
}
$sessionId = (string)$session['id'];

// ── 1. Retrouve la transaction interne ────────────────────────
$tx = $pdo->prepare("SELECT * FROM paiement_transactions WHERE provider_ref = ? LIMIT 1");
$tx->execute([$sessionId]);
$transaction = $tx->fetch();
if (!$transaction) {
    require_once __DIR__ . '/../includes/boutique.php';
    try {
        if (boutique_webhook_process($pdo, $sessionId, 'stripe')) {
            echo json_encode(['ok' => true, 'note' => 'boutique order']);
            exit;
        }
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => 'boutique webhook', 'detail' => $e->getMessage()]);
        exit;
    }
    echo json_encode(['ok' => true, 'note' => 'unknown session, ignored']);
    exit;
}

// Idempotence
if (in_array($transaction['statut'], ['paye', 'echec', 'annule', 'rembourse'], true)) {
    echo json_encode(['ok' => true, 'note' => 'already processed', 'statut' => $transaction['statut']]);
    exit;
}

// ── 2. Re-vérifie via l'API Stripe (ne fait confiance qu'à Stripe) ──
$status = paiement_get_status((string)$transaction['provider'], $sessionId);
if ($status !== 'paid' && $status !== 'failed') {
    echo json_encode(['ok' => true, 'note' => 'status not final', 'status' => $status]);
    exit;
}

// ── 3. Échec ──────────────────────────────────────────────────
if ($status === 'failed') {
    $pdo->prepare("UPDATE paiement_transactions SET statut='echec' WHERE id=?")
        ->execute([$transaction['id']]);
    echo json_encode(['ok' => true, 'note' => 'marked failed']);
    exit;
}

// ── 4. Paiement OK → crée les billets si pas déjà fait ────────
$pdo->beginTransaction();
try {
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

    // Envoi mail des billets (PDF en pièce jointe) - hors transaction.
    if (!empty($createdIds)) {
        try { billet_send_mail_for_ids($pdo, $createdIds, (int)$transaction['id']); }
        catch (Throwable $e) { error_log('[stripe-webhook] mail err: ' . $e->getMessage()); }
    }

    require_once __DIR__ . '/../includes/comptabilite.php';
    compta_try_auto_import_billetterie($pdo, (int)$transaction['id']);

    echo json_encode([
        'ok'    => true,
        'note'  => 'tickets created',
        'count' => count($createdIds),
        'type'  => $type,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[stripe-webhook] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'internal error']);
}
