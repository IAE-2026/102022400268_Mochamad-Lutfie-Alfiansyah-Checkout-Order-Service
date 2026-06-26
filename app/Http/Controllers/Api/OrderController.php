<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Checkout;
use App\Models\Order;
use App\Models\Payment;
use App\Services\ProductStockClient;
use App\Support\ApiResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(): JsonResponse
    {
        $orders = Order::with('items', 'payment')->latest()->get();

        return ApiResponse::success('Orders retrieved successfully', $orders);
    }

    public function store(Request $request, ProductStockClient $productStockClient): JsonResponse
    {
        if (! $request->filled('checkout_id')) {
            return $this->storeDirectOrder($request);
        }

        $validated = $request->validate([
            'checkout_id' => ['required', 'integer', 'exists:checkouts,id'],
            'payment_id' => ['nullable', 'integer', 'exists:payments,id'],
        ]);

        $checkout = Checkout::with('items')->findOrFail($validated['checkout_id']);
        $payment = $this->resolvePayment($checkout, $validated['payment_id'] ?? null);

        if (! $payment || ! in_array($payment->status, ['confirmed', 'paid'], true)) {
            return ApiResponse::error('Checkout requires a confirmed payment before order creation', null, 409);
        }

        if ($checkout->order()->exists()) {
            return ApiResponse::error('Order already exists for this checkout', null, 409);
        }

        try {
            $productStockClient->deductStock($checkout->items->toArray());
        } catch (ConnectionException $exception) {
            return ApiResponse::error($exception->getMessage(), null, 502);
        }

        $order = DB::transaction(function () use ($checkout, $payment): Order {
            $order = Order::create([
                'checkout_id' => $checkout->id,
                'user_id' => $checkout->user_id,
                'invoice_number' => $this->invoiceNumber(),
                'total_amount' => $checkout->total_amount,
                'status' => 'paid',
            ]);

            foreach ($checkout->items as $item) {
                $order->items()->create([
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'subtotal' => $item->subtotal,
                ]);
            }

            $payment->update(['order_id' => $order->id]);
            $checkout->update(['status' => 'converted_to_order']);

            return $order->load('items', 'payment');
        });

        return ApiResponse::success('Order created successfully', $order, 201);
    }

    private function storeDirectOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'min:1'],
            'shipping_address' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['nullable', 'string', Rule::in(['bank_transfer', 'e_wallet', 'credit_card', 'cod'])],
            'items' => ['nullable', 'array'],
            'items.*.product_id' => ['required_with:items', 'integer', 'min:1'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.price' => ['required_with:items', 'numeric', 'min:0'],
        ]);

        $items = $validated['items'] ?? [
            [
                'product_id' => 1,
                'quantity' => 1,
                'price' => 100000,
            ],
        ];

        $order = DB::transaction(function () use ($validated, $items): Order {
            $totalAmount = collect($items)->sum(fn (array $item): float => round((float) $item['price'] * (int) $item['quantity'], 2));
            $paymentMethod = $validated['payment_method'] ?? 'bank_transfer';

            $checkout = Checkout::create([
                'user_id' => $validated['user_id'] ?? 1,
                'cart_id' => null,
                'shipping_address' => $validated['shipping_address'] ?? 'Default shipping address',
                'payment_method' => $paymentMethod,
                'total_amount' => $totalAmount,
                'status' => 'converted_to_order',
            ]);

            foreach ($items as $item) {
                $checkout->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'subtotal' => round((float) $item['price'] * (int) $item['quantity'], 2),
                ]);
            }

            $order = Order::create([
                'checkout_id' => $checkout->id,
                'user_id' => $checkout->user_id,
                'invoice_number' => $this->invoiceNumber(),
                'total_amount' => $totalAmount,
                'status' => 'paid',
            ]);

            foreach ($items as $item) {
                $order->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'subtotal' => round((float) $item['price'] * (int) $item['quantity'], 2),
                ]);
            }

            Payment::create([
                'checkout_id' => $checkout->id,
                'order_id' => $order->id,
                'payment_method' => $paymentMethod,
                'amount' => $totalAmount,
                'status' => 'confirmed',
                'confirmed_at' => now(),
            ]);

            return $order->load('items', 'payment', 'checkout');
        });

        return ApiResponse::success('Order created successfully', $order, 201);
    }

    public function show(Order $order): JsonResponse
    {
        return ApiResponse::success('Order retrieved successfully', $order->load('items', 'payment', 'checkout'));
    }

    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in([
                'pending_payment',
                'paid',
                'processing',
                'shipped',
                'delivered',
                'completed',
                'cancelled',
            ])],
        ]);

        $order->update(['status' => $validated['status']]);

        return ApiResponse::success('Order status updated successfully', $order->fresh()->load('items', 'payment'));
    }

    private function resolvePayment(Checkout $checkout, ?int $paymentId): ?Payment
    {
        if ($paymentId) {
            return Payment::where('checkout_id', $checkout->id)->find($paymentId);
        }

        return $checkout->payments()
            ->whereIn('status', ['confirmed', 'paid'])
            ->latest()
            ->first();
    }

    private function invoiceNumber(): string
    {
        return 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
    }
}
