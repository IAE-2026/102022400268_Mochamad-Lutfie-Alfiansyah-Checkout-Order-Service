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
