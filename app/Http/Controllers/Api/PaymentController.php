<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Checkout;
use App\Models\Payment;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function methods(): JsonResponse
    {
        return ApiResponse::success('Payment methods retrieved successfully', [
            ['code' => 'bank_transfer', 'name' => 'Bank Transfer'],
            ['code' => 'e_wallet', 'name' => 'E-Wallet'],
            ['code' => 'credit_card', 'name' => 'Credit Card'],
            ['code' => 'cod', 'name' => 'Cash on Delivery'],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'checkout_id' => ['required', 'integer', 'exists:checkouts,id'],
            'payment_method' => ['required', 'string', Rule::in(['bank_transfer', 'e_wallet', 'credit_card', 'cod'])],
            'amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $checkout = Checkout::findOrFail($validated['checkout_id']);

        $payment = Payment::create([
            'checkout_id' => $checkout->id,
            'payment_method' => $validated['payment_method'],
            'amount' => $validated['amount'] ?? $checkout->total_amount,
            'status' => 'pending',
        ])->load('checkout');

        return ApiResponse::success('Payment created successfully', $payment, 201);
    }

    public function show(Payment $payment): JsonResponse
    {
        return ApiResponse::success('Payment retrieved successfully', $payment->load('checkout', 'order'));
    }

    public function status(Payment $payment): JsonResponse
    {
        return ApiResponse::success('Payment status retrieved successfully', [
            'payment_id' => $payment->id,
            'checkout_id' => $payment->checkout_id,
            'order_id' => $payment->order_id,
            'status' => $payment->status,
            'confirmed_at' => $payment->confirmed_at,
        ]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payment_id' => ['required', 'integer', 'exists:payments,id'],
            'status' => ['nullable', 'string', Rule::in(['confirmed', 'paid', 'failed'])],
        ]);

        $payment = Payment::with('checkout')->findOrFail($validated['payment_id']);

        if ($payment->status === 'failed') {
            return ApiResponse::error('Failed payments cannot be confirmed', null, 409);
        }

        $status = $validated['status'] ?? 'confirmed';
        $payment->update([
            'status' => $status,
            'confirmed_at' => in_array($status, ['confirmed', 'paid'], true) ? now() : null,
        ]);

        if (in_array($status, ['confirmed', 'paid'], true)) {
            $payment->checkout->update(['status' => 'confirmed']);
        }

        return ApiResponse::success('Payment confirmed successfully', $payment->fresh()->load('checkout', 'order'));
    }
}
