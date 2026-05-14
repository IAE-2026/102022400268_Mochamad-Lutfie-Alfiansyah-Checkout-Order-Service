<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutOrderApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.iae.api_key' => 'test-key',
            'services.iae.service_name' => 'Checkout-Order-Service',
            'services.iae.api_version' => 'v1',
            'services.integrations.validate_stock' => false,
            'services.integrations.deduct_stock' => false,
        ]);
    }

    public function test_api_key_is_required(): void
    {
        $this->getJson('/api/v1/checkouts')
            ->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'message' => 'Missing or invalid X-IAE-KEY header',
                'errors' => null,
            ]);
    }

    public function test_checkout_can_be_created_listed_and_retrieved(): void
    {
        $checkout = $this->createCheckout();

        $this->assertSame('success', $checkout['status']);
        $this->assertSame('Checkout created successfully', $checkout['message']);
        $this->assertSame('Checkout-Order-Service', $checkout['meta']['service_name']);
        $this->assertSame('v1', $checkout['meta']['api_version']);
        $this->assertSame(1, $checkout['data']['user_id']);
        $this->assertCount(1, $checkout['data']['items']);

        $this->getJson('/api/v1/checkouts', $this->headers())
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/checkouts/'.$checkout['data']['id'], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.id', $checkout['data']['id'])
            ->assertJsonPath('data.items.0.product_id', 10);
    }

    public function test_payment_flow_works(): void
    {
        $checkout = $this->createCheckout()['data'];

        $this->getJson('/api/v1/payment/methods', $this->headers())
            ->assertOk()
            ->assertJsonPath('data.0.code', 'bank_transfer');

        $payment = $this->postJson('/api/v1/payments', [
            'checkout_id' => $checkout['id'],
            'payment_method' => 'bank_transfer',
        ], $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->json('data');

        $this->getJson('/api/v1/payments/'.$payment['id'], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.id', $payment['id']);

        $this->getJson('/api/v1/payments/'.$payment['id'].'/status', $this->headers())
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');

        $this->postJson('/api/v1/payments/confirm', [
            'payment_id' => $payment['id'],
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');
    }

    public function test_order_can_be_created_and_status_updated_after_payment_confirmation(): void
    {
        [$checkout, $payment] = $this->createConfirmedPayment();

        $order = $this->postJson('/api/v1/orders', [
            'checkout_id' => $checkout['id'],
            'payment_id' => $payment['id'],
        ], $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.items.0.product_id', 10)
            ->json('data');

        $this->getJson('/api/v1/orders/'.$order['id'], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.id', $order['id'])
            ->assertJsonPath('data.payment.id', $payment['id']);

        $this->putJson('/api/v1/orders/'.$order['id'].'/status', [
            'status' => 'processing',
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.status', 'processing');
    }

    public function test_validation_errors_and_missing_resources_use_contract_wrapper(): void
    {
        $this->postJson('/api/v1/checkouts', [
            'user_id' => 1,
        ], $this->headers())
            ->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonStructure(['status', 'message', 'errors']);

        $this->getJson('/api/v1/orders/999', $this->headers())
            ->assertNotFound()
            ->assertJson([
                'status' => 'error',
                'message' => 'Resource not found',
                'errors' => null,
            ]);
    }

    public function test_graphql_order_query_returns_selected_fields(): void
    {
        [$checkout, $payment] = $this->createConfirmedPayment();

        $order = $this->postJson('/api/v1/orders', [
            'checkout_id' => $checkout['id'],
            'payment_id' => $payment['id'],
        ], $this->headers())->json('data');

        $this->postJson('/api/graphql', [
            'query' => <<<'GRAPHQL'
query Order($id: ID!) {
  order(id: $id) {
    id
    invoice_number
    status
    total_amount
    items {
      product_id
      quantity
      price
      subtotal
    }
  }
}
GRAPHQL,
            'variables' => [
                'id' => $order['id'],
            ],
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.order.id', (string) $order['id'])
            ->assertJsonPath('data.order.status', 'paid')
            ->assertJsonPath('data.order.items.0.product_id', 10);
    }

    /**
     * @return array<string, mixed>
     */
    private function createCheckout(): array
    {
        return $this->postJson('/api/v1/checkouts', [
            'user_id' => 1,
            'shipping_address' => 'Jl. Telekomunikasi No. 1, Bandung',
            'payment_method' => 'bank_transfer',
            'items' => [
                [
                    'product_id' => 10,
                    'quantity' => 2,
                    'price' => 150000,
                ],
            ],
        ], $this->headers())
            ->assertCreated()
            ->json();
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function createConfirmedPayment(): array
    {
        $checkout = $this->createCheckout()['data'];

        $payment = $this->postJson('/api/v1/payments', [
            'checkout_id' => $checkout['id'],
            'payment_method' => 'bank_transfer',
        ], $this->headers())->json('data');

        $payment = $this->postJson('/api/v1/payments/confirm', [
            'payment_id' => $payment['id'],
        ], $this->headers())->json('data');

        return [$checkout, $payment];
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'Accept' => 'application/json',
            'X-IAE-KEY' => 'test-key',
        ];
    }
}
