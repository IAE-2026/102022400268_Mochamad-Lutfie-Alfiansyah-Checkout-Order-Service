<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class ProductStockClient
{
    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function validateStock(array $items): void
    {
        if (! filter_var(config('services.integrations.validate_stock'), FILTER_VALIDATE_BOOL)) {
            return;
        }

        foreach ($items as $item) {
            $productId = $item['product_id'];
            $quantity = (int) $item['quantity'];

            $response = $this->client()->get($this->baseUrl()."/api/v1/products/{$productId}/stock");

            if (! $response->successful()) {
                throw new ConnectionException("Product service returned HTTP {$response->status()}");
            }

            $data = $response->json('data') ?? $response->json();
            $stock = (int) ($data['stock'] ?? $data['quantity'] ?? 0);

            if ($stock < $quantity) {
                throw new ConnectionException("Insufficient stock for product {$productId}");
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function deductStock(array $items): void
    {
        if (! filter_var(config('services.integrations.deduct_stock'), FILTER_VALIDATE_BOOL)) {
            return;
        }

        foreach ($items as $item) {
            $response = $this->client()->put($this->baseUrl().'/api/v1/products/stock/update', [
                'product_id' => $item['product_id'],
                'quantity' => (int) $item['quantity'],
                'operation' => 'decrease',
            ]);

            if (! $response->successful()) {
                throw new ConnectionException("Product service returned HTTP {$response->status()}");
            }
        }
    }

    private function client()
    {
        return Http::timeout((int) config('services.integrations.timeout', 5))
            ->withHeaders([
                'Accept' => 'application/json',
                'X-IAE-KEY' => config('services.integrations.product_api_key'),
            ]);
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.integrations.product_url'), '/');
    }
}
