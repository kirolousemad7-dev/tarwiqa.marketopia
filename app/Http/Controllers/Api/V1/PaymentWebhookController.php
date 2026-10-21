<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Services\WalletServiceInterface;
use App\Http\Controllers\Controller;
use App\Services\PaymentPaymobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentPaymobService $paymob, WalletServiceInterface $wallets): JsonResponse
    {
        $payload = $request->input('obj');

        if (! is_array($payload)) {
            return response()->json(['status' => 'failed'], 400);
        }

        if (! $paymob->verifyHmac($payload, $request->query('hmac'))) {
            Log::warning('Paymob webhook rejected', ['reason' => 'invalid_signature']);

            return response()->json(['status' => 'failed'], 401);
        }

        $reference = data_get($payload, 'order.merchant_order_id') ?: data_get($payload, 'order.id');

        if (! is_scalar($reference) || (string) $reference === '') {
            return response()->json(['status' => 'failed'], 422);
        }

        $amountCents = data_get($payload, 'amount_cents');
        $cents = is_numeric($amountCents) ? (int) $amountCents : -1;
        $result = $wallets->settlePaymobDeposit((string) $reference, $this->paymentSucceeded(data_get($payload, 'success')), $cents);

        Log::info('Paymob webhook settled', ['reference' => (string) $reference, 'status' => $result['status']]);

        return response()->json(['status' => $result['status']], $result['http']);
    }

    private function paymentSucceeded(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['true', '1', 'yes'], true);
        }

        return false;
    }
}
