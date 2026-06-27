<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Orange Money Web Payment API client.
 *
 * Implements the documented flow:
 *   1. OAuth 2.0 client-credentials token  (POST {token_path})
 *   2. Initiate web payment                (POST {webpay_path})  -> payment_url + pay_token + notif_token
 *   3. Check transaction status            (POST {status_path})  -> SUCCESS | PENDING | FAILED | EXPIRED
 *
 * Docs: https://developer.orange.com/apis/om-webpay
 */
class OrangeMoneyService
{
    private string $base;

    public function __construct()
    {
        $this->base = rtrim((string) config('streamwave.orange_money.base_url'), '/');
    }

    /**
     * Whether the real API is enabled and credentials are present.
     * When false, callers should fall back to the simulated flow.
     */
    public function apiEnabled(): bool
    {
        $om = config('streamwave.orange_money');

        return ($om['api_enabled'] ?? false)
            && ! empty($om['auth_header'])
            && ! empty($om['merchant_key']);
    }

    /**
     * Obtain (and cache) an OAuth access token.
     */
    public function token(): ?string
    {
        return Cache::remember('orange_money_token', now()->addMinutes(50), function () {
            $response = Http::asForm()
                ->withHeaders([
                    'Authorization' => 'Basic ' . config('streamwave.orange_money.auth_header'),
                    'Accept' => 'application/json',
                ])
                ->post($this->base . config('streamwave.orange_money.token_path'), [
                    'grant_type' => 'client_credentials',
                ]);

            if ($response->failed()) {
                Log::error('Orange Money token request failed', ['status' => $response->status(), 'body' => $response->body()]);

                return null;
            }

            return $response->json('access_token');
        });
    }

    /**
     * Initiate a web payment.
     *
     * @return array{payment_url:string, pay_token:string, notif_token:?string}|null
     */
    public function initiatePayment(array $order): ?array
    {
        $token = $this->token();
        if (! $token) {
            return null;
        }

        $payload = [
            'merchant_key' => config('streamwave.orange_money.merchant_key'),
            'currency' => config('streamwave.orange_money.currency'),
            'order_id' => $order['order_id'],
            'amount' => $order['amount'],
            'return_url' => $order['return_url'],
            'cancel_url' => $order['cancel_url'],
            'notif_url' => $order['notif_url'],
            'lang' => config('streamwave.orange_money.lang'),
            'reference' => $order['reference'] ?? config('app.name'),
        ];

        $response = Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->post($this->base . config('streamwave.orange_money.webpay_path'), $payload);

        if ($response->failed()) {
            Log::error('Orange Money web payment failed', ['status' => $response->status(), 'body' => $response->body()]);

            return null;
        }

        return [
            'payment_url' => $response->json('payment_url'),
            'pay_token' => $response->json('pay_token'),
            'notif_token' => $response->json('notif_token'),
        ];
    }

    /**
     * Check the status of a transaction.
     * Returns one of: INITIATED, PENDING, SUCCESS, FAILED, EXPIRED, or null on error.
     */
    public function checkStatus(string $orderId, $amount, string $payToken): ?string
    {
        $token = $this->token();
        if (! $token) {
            return null;
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->post($this->base . config('streamwave.orange_money.status_path'), [
                'order_id' => $orderId,
                'amount' => $amount,
                'pay_token' => $payToken,
            ]);

        if ($response->failed()) {
            Log::error('Orange Money status check failed', ['status' => $response->status(), 'body' => $response->body()]);

            return null;
        }

        return $response->json('status');
    }
}
