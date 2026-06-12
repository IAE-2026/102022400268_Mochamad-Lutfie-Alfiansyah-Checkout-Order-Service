<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use JsonException;

class OrderEventPublisher
{
    public function __construct(private readonly IaeCentralTokenClient $tokenClient)
    {
    }

    /**
     * @throws JsonException
     * @throws ConnectionException
     */
    public function publishOrderCreated(Order $order): void
    {
        if (! filter_var(config('services.rabbitmq.enabled'), FILTER_VALIDATE_BOOL)) {
            return;
        }

        $response = Http::timeout((int) config('services.central.timeout', 5))
            ->withToken($this->tokenClient->bearerToken())
            ->acceptJson()
            ->post((string) config('services.rabbitmq.publish_url'), [
                'exchange' => config('services.rabbitmq.exchange', 'iae.central.exchange'),
                'routing_key' => config('services.rabbitmq.routing_key', 'checkout.order.created'),
                'event' => 'checkout.order.created',
                'message' => $this->payload($order),
            ]);

        if (! $response->successful()) {
            throw new ConnectionException("IAE central message publisher returned HTTP {$response->status()}");
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Order $order): array
    {
        $order->loadMissing('items', 'payment', 'checkout');

        return [
            'event' => 'checkout.order.created',
            'service_name' => config('services.iae.service_name', 'Checkout-Order-Service'),
            'api_version' => config('services.iae.api_version', 'v1'),
            'occurred_at' => now()->toISOString(),
            'data' => [
                'order_id' => $order->id,
                'checkout_id' => $order->checkout_id,
                'payment_id' => $order->payment?->id,
                'user_id' => $order->user_id,
                'invoice_number' => $order->invoice_number,
                'total_amount' => (float) $order->total_amount,
                'status' => $order->status,
                'audit_receipt_number' => $order->audit_receipt_number,
                'items' => $order->items
                    ->map(fn ($item): array => [
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'price' => (float) $item->price,
                        'subtotal' => (float) $item->subtotal,
                    ])
                    ->values()
                    ->all(),
            ],
        ];
    }
}
