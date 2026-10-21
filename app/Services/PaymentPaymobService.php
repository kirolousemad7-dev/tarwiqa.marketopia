<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaymentPaymobService
{
    private ?string $baseUrl;

    private ?string $hmacSecret;

    private ?string $secretKey;

    private ?string $publicKey;

    private int $expiration;

    private int $integrationId;

    private ?string $callbackUrl;

    private ?string $redirectUrl;

    private const HMAC_FIELDS = [
        'amount_cents', 'created_at', 'currency', 'error_occured', 'has_parent_transaction',
        'id', 'integration_id', 'is_3d_secure', 'is_auth', 'is_capture', 'is_refunded',
        'is_standalone_payment', 'is_voided', 'order.id', 'owner', 'pending',
        'source_data.pan', 'source_data.sub_type', 'source_data.type', 'success',
    ];

    public function __construct()
    {
        $this->baseUrl = $this->stringConfig('paymob.base_url');
        $this->publicKey = $this->stringConfig('paymob.public_key');
        $this->secretKey = $this->stringConfig('paymob.secret_key');
        $this->hmacSecret = $this->stringConfig('paymob.hmac_secret');
        $this->expiration = max(1, (int) config('paymob.expiration_seconds', 30));
        $this->integrationId = (int) config('paymob.integration_id');
        $this->callbackUrl = $this->stringConfig('paymob.callback_url');
        $this->redirectUrl = $this->stringConfig('paymob.redirect_url');
    }

    public function makeOrder(string $reference, int $amount, string $description): array
    {
        $this->ensureConfigured();

        $response = $this->request()->post('/v1/intention', [
            'amount' => $amount,
            'currency' => 'EGP',
            'payment_methods' => [$this->integrationId],
            'items' => [
                [
                    'name' => $description,
                    'amount' => $amount,
                    'description' => $description,
                    'quantity' => 1,
                ],
            ],
            'billing_data' => $this->billingData(),
            'special_reference' => $reference,
            'notification_url' => $this->callbackUrl,
            'redirection_url' => $this->redirectUrl,
        ])->throw()->json();

        if (! is_array($response) || empty($response['id']) || empty($response['client_secret'])) {
            throw new RuntimeException('Paymob returned an invalid payment intention response.');
        }

        return [
            'reference' => $response['special_reference'],
            'checkout_url' => $this->checkoutUrl($response['client_secret']),
        ];
    }

    public function checkoutUrl(string $clientSecret): string
    {
        $this->ensureConfigured();

        return rtrim((string) $this->baseUrl, '/').'/unifiedcheckout/?'.http_build_query([
            'publicKey' => $this->publicKey,
            'clientSecret' => $clientSecret,
        ]);
    }

    public function verifyHmac(array $payload, mixed $receivedHmac): bool
    {
        if (blank($this->hmacSecret) || ! is_string($receivedHmac) || $receivedHmac === '') {
            return false;
        }

        $calculated = hash_hmac('sha512', $this->canonicalPayload($payload), $this->hmacSecret);

        if (strlen($calculated) !== strlen($receivedHmac)) {
            return false;
        }

        return hash_equals($calculated, strtolower($receivedHmac));
    }

    public function signatureFor(array $payload): string
    {
        if (blank($this->hmacSecret)) {
            throw new RuntimeException('Paymob is not configured.');
        }

        return hash_hmac('sha512', $this->canonicalPayload($payload), $this->hmacSecret);
    }

    private function request(): PendingRequest
    {
        $this->ensureConfigured();

        return Http::baseUrl((string) $this->baseUrl)
            ->withToken((string) $this->secretKey)
            ->acceptJson()
            ->asJson()
            ->timeout($this->expiration)
            ->retry(2, 250);
    }

    private function ensureConfigured(): void
    {
        if (blank($this->baseUrl) || blank($this->secretKey) || blank($this->publicKey) || blank($this->hmacSecret) || $this->integrationId < 1 || blank($this->callbackUrl) || blank($this->redirectUrl)) {
            throw new RuntimeException('Paymob is not configured.');
        }
    }

    private function canonicalPayload(array $payload): string
    {
        return collect(self::HMAC_FIELDS)
            ->map(fn (string $field): string => $this->stringify(Arr::get($payload, $field)))
            ->implode('');
    }

    private function stringify(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    private function stringConfig(string $key): ?string
    {
        $value = config($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function billingData(): array
    {
        return [
            'first_name' => 'ala',
            'last_name' => 'zain',
            'phone_number' => '+92345xxxxxxxx',
            'email' => 'ali@gmail.com',
            'street' => 'N/A',
            'city' => 'N/A',
            'country' => 'N/A',
            'state' => 'N/A',
            'building' => 'N/A',
            'floor' => 'N/A',
            'apartment' => 'N/A',
        ];
    }
}
