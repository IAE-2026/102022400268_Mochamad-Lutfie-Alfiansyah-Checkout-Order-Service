<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class CartPromoClient
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchCartItems(string $cartId): array
    {
        $response = Http::timeout((int) config('services.integrations.timeout', 5))
            ->withHeaders([
                'Accept' => 'application/json',
                'X-IAE-KEY' => config('services.integrations.cart_promo_api_key'),
            ])
            ->get(rtrim(config('services.integrations.cart_promo_url'), '/')."/api/v1/carts/{$cartId}");

        if (! $response->successful()) {
            throw new ConnectionException("Cart service returned HTTP {$response->status()}");
        }

        $payload = $response->json();
        $data = $payload['data'] ?? $payload;

        return $data['items'] ?? $data['cart_items'] ?? (array) $data;
    }
}
