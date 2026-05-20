<?php

require_once __DIR__ . '/env.php';

function sumup_is_mock(): bool {
    $key = corpo_env('SUMUP_API_KEY', '');
    return !$key;
}

function sumup_mode(): string {
    return (string) corpo_env('SUMUP_MODE', 'test');
}

function sumup_create_checkout(float $amount, string $reference, string $email, string $description, string $returnUrl): array {
    if (sumup_is_mock()) {
        $fakeId = 'mock_' . bin2hex(random_bytes(8));

        $redirect = $returnUrl . (str_contains($returnUrl, '?') ? '&' : '?')
                  . 'mock=1&checkout_id=' . urlencode($fakeId) . '&ref=' . urlencode($reference);
        return [
            'checkout_id' => $fakeId,
            'redirect_url' => $redirect,
            'raw' => ['mock' => true, 'amount' => $amount, 'reference' => $reference],
        ];
    }

    $apiKey   = (string)corpo_env('SUMUP_API_KEY');
    $merchant = (string)corpo_env('SUMUP_MERCHANT_CODE', 'M4RC8MDH');

    $reference = preg_replace('/[^A-Za-z0-9_\-]/', '-', $reference);
    if (strlen($reference) > 90) {
        $reference = substr($reference, 0, 90);
    }

    $payload = [
        'checkout_reference' => $reference,
        'amount'             => round($amount, 2),
        'currency'           => 'EUR',
        'merchant_code'      => $merchant,
        'description'        => $description,

        'redirect_url'       => $returnUrl,
        'hosted_checkout'    => ['enabled' => true],
    ];
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $payload['personal_details'] = ['email' => $email];
    }

    $ch = curl_init('https://api.sumup.com/v0.1/checkouts');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $resp     = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        throw new RuntimeException('SumUp : impossible de contacter l\'API (' . $curlErr . ').');
    }

    $data = json_decode($resp, true) ?: [];
    if ($httpCode !== 201 && $httpCode !== 200) {

        $msg = $data['error_message']
            ?? $data['message']
            ?? (isset($data['errors'][0]['message']) ? $data['errors'][0]['message'] : null)
            ?? substr((string)$resp, 0, 300);
        throw new RuntimeException('SumUp HTTP ' . $httpCode . ' - ' . $msg);
    }

    $redirect = $data['hosted_checkout_url'] ?? null;
    if (!$redirect) {

        throw new RuntimeException(
            "SumUp : l'API n'a pas renvoyé d'URL de paiement (hosted_checkout_url manquant). "
            . "Vérifie que ta clé API a bien accès au compte marchand " . $merchant . "."
        );
    }
    return [
        'checkout_id'  => $data['id'] ?? '',
        'redirect_url' => $redirect,
        'raw'          => $data,
    ];
}

function sumup_get_checkout_status(string $checkoutId): string {
    if (sumup_is_mock() || str_starts_with($checkoutId, 'mock_')) {

        return 'paid';
    }

    $apiKey = corpo_env('SUMUP_API_KEY');
    $ch = curl_init('https://api.sumup.com/v0.1/checkouts/' . urlencode($checkoutId));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($code !== 200) return 'unknown';

    $data = json_decode($resp, true) ?: [];
    $s = strtolower($data['status'] ?? '');
    if (in_array($s, ['paid','successful','success'], true)) return 'paid';
    if (in_array($s, ['failed','error','canceled','cancelled'], true)) return 'failed';
    if (in_array($s, ['pending'], true)) return 'pending';
    return 'unknown';
}
