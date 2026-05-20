<?php

require_once __DIR__ . '/env.php';

function stripe_is_mock(): bool {
    $key = (string)corpo_env('STRIPE_SECRET_KEY', '');
    return $key === '';
}

function stripe_mode(): string {
    return (string)corpo_env('STRIPE_MODE', 'test');
}

function stripe_create_checkout(float $amount, string $reference, string $email, string $description, string $returnUrl): array {
    if (stripe_is_mock()) {
        $fakeId = 'cs_mock_' . bin2hex(random_bytes(8));
        $sep    = str_contains($returnUrl, '?') ? '&' : '?';
        $redirect = $returnUrl . $sep . 'mock=1&stripe=1&session_id=' . urlencode($fakeId)
                  . '&ref=' . urlencode($reference);
        return [
            'session_id'   => $fakeId,
            'redirect_url' => $redirect,
            'raw'          => ['mock' => true, 'amount' => $amount, 'reference' => $reference],
        ];
    }

    $apiKey = (string)corpo_env('STRIPE_SECRET_KEY');
    $sep    = str_contains($returnUrl, '?') ? '&' : '?';
    $success = $returnUrl . $sep . 'stripe=1';
    $cancel  = $returnUrl . $sep . 'stripe=cancel';

    $cleanRef = preg_replace('/[^A-Za-z0-9_\-]/', '-', $reference);
    if (strlen($cleanRef) > 200) {
        $cleanRef = substr($cleanRef, 0, 200);
    }

    $params = [
        'mode'                                          => 'payment',
        'payment_method_types[0]'                       => 'card',
        'success_url'                                   => $success,
        'cancel_url'                                    => $cancel,
        'client_reference_id'                           => $cleanRef,
        'line_items[0][quantity]'                       => 1,
        'line_items[0][price_data][currency]'           => 'eur',
        'line_items[0][price_data][unit_amount]'        => (int)round($amount * 100),
        'line_items[0][price_data][product_data][name]' => substr($description, 0, 250),
    ];
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $params['customer_email'] = $email;
    }

    $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($params),
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $apiKey],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $resp     = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        throw new RuntimeException('Stripe : impossible de contacter l\'API (' . $curlErr . ').');
    }

    $data = json_decode($resp, true) ?: [];
    if ($httpCode !== 200 && $httpCode !== 201) {
        $msg = $data['error']['message']
            ?? $data['error_description']
            ?? substr((string)$resp, 0, 300);
        throw new RuntimeException('Stripe HTTP ' . $httpCode . ' - ' . $msg);
    }

    $url = $data['url'] ?? null;
    if (!$url) {
        throw new RuntimeException('Stripe : aucune URL de paiement retournée.');
    }
    return [
        'session_id'   => (string)($data['id'] ?? ''),
        'redirect_url' => (string)$url,
        'raw'          => $data,
    ];
}

function stripe_get_session_status(string $sessionId): string {
    if (stripe_is_mock() || str_starts_with($sessionId, 'cs_mock_')) {
        return 'paid';
    }
    $apiKey = (string)corpo_env('STRIPE_SECRET_KEY');
    $ch = curl_init('https://api.stripe.com/v1/checkout/sessions/' . urlencode($sessionId));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $apiKey],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($code !== 200) return 'unknown';

    $data          = json_decode((string)$resp, true) ?: [];
    $paymentStatus = strtolower((string)($data['payment_status'] ?? ''));
    $status        = strtolower((string)($data['status']         ?? ''));

    if ($paymentStatus === 'paid') return 'paid';
    if ($status === 'complete' && $paymentStatus !== 'unpaid') return 'paid';
    if (in_array($status, ['expired'], true)) return 'failed';
    if ($status === 'open') return 'pending';
    return 'unknown';
}
