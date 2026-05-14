<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Checkout;
use App\Services\CartPromoClient;
use App\Services\ProductStockClient;
use App\Support\ApiResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CheckoutController extends Controller
{
    public function index(): JsonResponse
    {
        $checkouts = Checkout::with('items')->latest()->get();

        return ApiResponse::success('Checkouts retrieved successfully', $checkouts);
    }

    public function store(Request $request, CartPromoClient $cartPromoClient, ProductStockClient $productStockClient): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'min:1'],
            'cart_id' => ['nullable', 'string', 'max:100'],
            'shipping_address' => ['required', 'string', 'max:1000'],
            'payment_method' => ['required', 'string', Rule::in($this->paymentMethods())],
            'items' => ['nullable', 'array', 'min:1'],
            'items.*.product_id' => ['required_with:items', 'integer', 'min:1'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.price' => ['required_with:items', 'numeric', 'min:0'],
        ]);

        try {
            $items = $this->resolveItems($validated, $cartPromoClient);
            $productStockClient->validateStock($items);
        } catch (ConnectionException $exception) {
            return ApiResponse::error($exception->getMessage(), null, 502);
        }

        $checkout = DB::transaction(function () use ($validated, $items): Checkout {
            $totalAmount = collect($items)->sum(fn (array $item): float => $this->subtotal($item));

            $checkout = Checkout::create([
                'user_id' => $validated['user_id'],
                'cart_id' => $validated['cart_id'] ?? null,
                'shipping_address' => $validated['shipping_address'],
                'payment_method' => $validated['payment_method'],
                'total_amount' => $totalAmount,
                'status' => 'draft',
            ]);

            foreach ($items as $item) {
                $checkout->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'subtotal' => $this->subtotal($item),
                ]);
            }

            return $checkout->load('items');
        });

        return ApiResponse::success('Checkout created successfully', $checkout, 201);
    }

    public function show(Checkout $checkout): JsonResponse
    {
        return ApiResponse::success('Checkout retrieved successfully', $checkout->load('items', 'payments', 'order'));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveItems(array $validated, CartPromoClient $cartPromoClient): array
    {
        if (! empty($validated['items'])) {
            return $validated['items'];
        }

        if (! empty($validated['cart_id'])) {
            return $cartPromoClient->fetchCartItems($validated['cart_id']);
        }

        throw new ConnectionException('Provide checkout items or a cart_id that can be fetched from Cart & Promo service');
    }

    /**
     * @return array<int, string>
     */
    private function paymentMethods(): array
    {
        return ['bank_transfer', 'e_wallet', 'credit_card', 'cod'];
    }

    /**
     * @param array<string, mixed> $item
     */
    private function subtotal(array $item): float
    {
        return round((float) $item['price'] * (int) $item['quantity'], 2);
    }
}
