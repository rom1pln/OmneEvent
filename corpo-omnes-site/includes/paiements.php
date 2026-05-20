<?php


require_once __DIR__ . '/env.php';
require_once __DIR__ . '/sumup.php';
require_once __DIR__ . '/stripe.php';

const PAIEMENT_PROVIDER_THRESHOLD_DEFAULT = 25.00;
const PAIEMENT_SUMUP_FEE_PCT_DEFAULT      = 2.5;
const PAIEMENT_STRIPE_FEE_PCT_DEFAULT     = 1.5;
const PAIEMENT_STRIPE_FEE_FIXED_DEFAULT   = 0.25;

function _paiement_env_float(string $key, float $default): float {
    $v = (string)corpo_env($key, '');
    if ($v === '') return $default;
    return (float)str_replace(',', '.', $v);
}

function paiement_threshold(): float          { return _paiement_env_float('PAYMENT_PROVIDER_THRESHOLD', PAIEMENT_PROVIDER_THRESHOLD_DEFAULT); }
function paiement_sumup_fee_pct(): float      { return _paiement_env_float('SUMUP_FEE_PERCENT',          PAIEMENT_SUMUP_FEE_PCT_DEFAULT); }
function paiement_stripe_fee_pct(): float     { return _paiement_env_float('STRIPE_FEE_PERCENT',         PAIEMENT_STRIPE_FEE_PCT_DEFAULT); }
function paiement_stripe_fee_fixed(): float   { return _paiement_env_float('STRIPE_FEE_FIXED',           PAIEMENT_STRIPE_FEE_FIXED_DEFAULT); }

/**
 * Décide quel provider utiliser selon le montant.
 * Convention : montant strictement ≤ seuil → SumUp, sinon Stripe.
 */
function paiement_provider_for(float $amount): string {
    return $amount <= paiement_threshold() ? 'sumup' : 'stripe';
}

/**
 * Calcule les frais et le net pour un montant donné.
 *
 * Retourne :
 *   provider     : 'sumup' | 'stripe'
 *   label        : 'SumUp' | 'Stripe'
 *   percent      : taux %
 *   fixed        : frais fixes en €
 *   frais        : montant des frais (arrondi 2 décimales)
 *   net          : montant - frais (frais à la charge de l'association)
 *   client_total : montant + frais (frais à la charge du client)
 *   threshold    : seuil SumUp/Stripe utilisé
 */
function paiement_calcule_frais(float $amount, ?string $provider = null): array {
    $provider = $provider ?: paiement_provider_for($amount);
    if ($provider === 'sumup') {
        $pct   = paiement_sumup_fee_pct();
        $fixed = 0.0;
    } else {
        $pct   = paiement_stripe_fee_pct();
        $fixed = paiement_stripe_fee_fixed();
    }
    $frais = round(($amount * $pct / 100) + $fixed, 2);
    if ($amount <= 0) $frais = 0.0;
    if ($frais < 0)   $frais = 0.0;
    return [
        'provider'     => $provider,
        'label'        => $provider === 'sumup' ? 'SumUp' : 'Stripe',
        'percent'      => $pct,
        'fixed'        => $fixed,
        'frais'        => $frais,
        'net'          => max(0.0, round($amount - $frais, 2)),
        'client_total' => round($amount + $frais, 2),
        'threshold'    => paiement_threshold(),
    ];
}

/**
 * Crée un checkout en utilisant le bon provider selon le montant.
 *
 * @return array{provider:string, checkout_id:string, redirect_url:string, raw:array}
 */
function paiement_create_checkout(float $amount, string $reference, string $email, string $description, string $returnUrl, ?string $forceProvider = null): array {
    $provider = $forceProvider ?: paiement_provider_for($amount);
    if ($provider === 'stripe') {
        $r = stripe_create_checkout($amount, $reference, $email, $description, $returnUrl);
        return [
            'provider'     => stripe_is_mock() ? 'mock_stripe' : 'stripe',
            'checkout_id'  => (string)($r['session_id'] ?? ''),
            'redirect_url' => (string)($r['redirect_url'] ?? ''),
            'raw'          => $r['raw'] ?? [],
        ];
    }
    $r = sumup_create_checkout($amount, $reference, $email, $description, $returnUrl);
    return [
        'provider'     => sumup_is_mock() ? 'mock' : 'sumup',
        'checkout_id'  => (string)($r['checkout_id'] ?? ''),
        'redirect_url' => (string)($r['redirect_url'] ?? ''),
        'raw'          => $r['raw'] ?? [],
    ];
}

/**
 * Récupère le statut d'un checkout selon le provider stocké en BDD.
 */
function paiement_get_status(string $provider, string $providerRef): string {
    $p = strtolower($provider);
    if (str_contains($p, 'stripe')) {
        return stripe_get_session_status($providerRef);
    }
    // 'sumup' / 'mock' (mock SumUp) / défaut
    return sumup_get_checkout_status($providerRef);
}

/**
 * True si le provider donné est en mode mock.
 */
function paiement_is_mock(string $provider): bool {
    $p = strtolower($provider);
    if (str_contains($p, 'stripe')) return stripe_is_mock();
    if (str_starts_with($p, 'mock')) return true;
    return sumup_is_mock();
}
