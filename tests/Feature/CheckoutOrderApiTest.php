<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
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
            'services.sso.enabled' => false,
            'services.legacy_audit.enabled' => false,
            'services.rabbitmq.enabled' => false,
            'services.central.bearer_token' => null,
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

    public function test_sso_jwt_is_mapped_to_local_role_for_critical_order_endpoint(): void
    {
        [$checkout, $payment] = $this->createConfirmedPayment();

        config([
            'services.sso.enabled' => true,
            'services.sso.algorithm' => 'HS256',
            'services.sso.jwt_secret' => 'sso-secret',
            'services.sso.role_claim' => 'role',
        ]);

        $this->postJson('/api/v1/orders', [
            'checkout_id' => $checkout['id'],
            'payment_id' => $payment['id'],
        ], $this->headers([
            'Authorization' => 'Bearer '.$this->jwt([
                'sub' => 'cloud-user-1',
                'email' => 'system@example.test',
                'name' => 'System Gateway',
                'role' => 'system',
                'exp' => now()->addMinutes(5)->timestamp,
            ]),
        ]))
            ->assertCreated()
            ->assertJsonPath('data.status', 'paid');

        $this->assertDatabaseHas('users', [
            'email' => 'system@example.test',
            'sso_provider' => 'cloud-dosen',
            'sso_subject' => 'cloud-user-1',
        ]);

        $this->assertDatabaseHas('roles', [
            'code' => 'system',
        ]);
    }

    public function test_sso_user_without_role_claim_defaults_to_customer_for_order_endpoint(): void
    {
        [$checkout, $payment] = $this->createConfirmedPayment();

        config([
            'services.sso.enabled' => true,
            'services.sso.algorithm' => 'HS256',
            'services.sso.jwt_secret' => 'sso-secret',
            'services.sso.role_claim' => 'role',
        ]);

        $this->postJson('/api/v1/orders', [
            'checkout_id' => $checkout['id'],
            'payment_id' => $payment['id'],
        ], $this->headers([
            'Authorization' => 'Bearer '.$this->jwt([
                'sub' => 'warga-40',
                'email' => 'warga40@ktp.iae.id',
                'name' => 'Warga 40',
                'exp' => now()->addMinutes(5)->timestamp,
            ]),
        ]))
            ->assertCreated()
            ->assertJsonPath('data.status', 'paid');

        $this->assertDatabaseHas('roles', [
            'code' => 'customer',
        ]);
    }

    public function test_order_creation_sends_legacy_soap_audit_and_stores_receipt_number(): void
    {
        [$checkout, $payment] = $this->createConfirmedPayment();

        config([
            'services.legacy_audit.enabled' => true,
            'services.legacy_audit.endpoint' => 'https://audit.example.test/soap',
            'services.central.bearer_token' => 'central-token',
            'services.iae.team_id' => 'TEAM-01',
            'services.legacy_audit.activity_name' => 'CheckoutOrderCreated',
        ]);

        Http::fake([
            'https://audit.example.test/soap' => Http::response(
                '<soap:Envelope><soap:Body><SubmitOrderAuditResponse><ReceiptNumber>RCP-ORDER-001</ReceiptNumber></SubmitOrderAuditResponse></soap:Body></soap:Envelope>',
                200,
            ),
        ]);

        $order = $this->postJson('/api/v1/orders', [
            'checkout_id' => $checkout['id'],
            'payment_id' => $payment['id'],
        ], $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.audit_receipt_number', 'RCP-ORDER-001')
            ->json('data');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://audit.example.test/soap'
            && $request->hasHeader('Authorization', 'Bearer central-token')
            && str_contains($request->body(), '<iae:AuditRequest>')
            && str_contains($request->body(), '<iae:TeamID>TEAM-01</iae:TeamID>')
            && str_contains($request->body(), '<iae:ActivityName>CheckoutOrderCreated</iae:ActivityName>')
            && str_contains($request->body(), '"transaction_type":"ORDER_CREATED"'));

        $this->assertDatabaseHas('orders', [
            'id' => $order['id'],
            'audit_receipt_number' => 'RCP-ORDER-001',
        ]);
    }

    public function test_order_creation_falls_back_to_request_bearer_token_when_central_api_key_is_missing(): void
    {
        [$checkout, $payment] = $this->createConfirmedPayment();

        config([
            'services.legacy_audit.enabled' => true,
            'services.legacy_audit.endpoint' => 'https://audit.example.test/soap',
            'services.central.bearer_token' => null,
            'services.central.api_key' => null,
            'services.iae.team_id' => 'TEAM-01',
            'services.legacy_audit.activity_name' => 'CheckoutOrderCreated',
        ]);

        Http::fake([
            'https://audit.example.test/soap' => Http::response(
                '<soap:Envelope><soap:Body><SubmitOrderAuditResponse><ReceiptNumber>RCP-REQUEST-TOKEN</ReceiptNumber></SubmitOrderAuditResponse></soap:Body></soap:Envelope>',
                200,
            ),
        ]);

        $this->postJson('/api/v1/orders', [
            'checkout_id' => $checkout['id'],
            'payment_id' => $payment['id'],
        ], $this->headers([
            'Authorization' => 'Bearer user-token-from-postman',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.audit_receipt_number', 'RCP-REQUEST-TOKEN');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://audit.example.test/soap'
            && $request->hasHeader('Authorization', 'Bearer user-token-from-postman'));

        Http::assertSentCount(1);
    }

    public function test_order_creation_uses_central_api_key_token_for_legacy_audit(): void
    {
        [$checkout, $payment] = $this->createConfirmedPayment();

        config([
            'services.legacy_audit.enabled' => true,
            'services.legacy_audit.endpoint' => 'https://audit.example.test/soap',
            'services.central.bearer_token' => null,
            'services.central.api_key' => 'KEY-MHS-343',
            'services.central.token_url' => 'https://iae.example.test/api/v1/auth/token',
            'services.iae.team_id' => 'TEAM-01',
            'services.legacy_audit.activity_name' => 'CheckoutOrderCreated',
        ]);

        Http::fake([
            'https://iae.example.test/api/v1/auth/token' => Http::response([
                'token' => 'machine-token',
            ], 200),
            'https://audit.example.test/soap' => Http::response(
                '<soap:Envelope><soap:Body><SubmitOrderAuditResponse><ReceiptNumber>RCP-MACHINE-TOKEN</ReceiptNumber></SubmitOrderAuditResponse></soap:Body></soap:Envelope>',
                200,
            ),
        ]);

        $this->postJson('/api/v1/orders', [
            'checkout_id' => $checkout['id'],
            'payment_id' => $payment['id'],
        ], $this->headers([
            'Authorization' => 'Bearer user-token-from-postman',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.audit_receipt_number', 'RCP-MACHINE-TOKEN');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://iae.example.test/api/v1/auth/token'
            && $request['api_key'] === 'KEY-MHS-343');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://audit.example.test/soap'
            && $request->hasHeader('Authorization', 'Bearer machine-token'));
    }

    public function test_order_creation_publishes_order_event_to_central_message_endpoint(): void
    {
        [$checkout, $payment] = $this->createConfirmedPayment();

        config([
            'services.rabbitmq.enabled' => true,
            'services.rabbitmq.publish_url' => 'https://iae.example.test/api/v1/messages/publish',
            'services.rabbitmq.exchange' => 'iae.central.exchange',
            'services.rabbitmq.routing_key' => 'checkout.order.created',
            'services.central.bearer_token' => 'central-token',
        ]);

        Http::fake([
            'https://iae.example.test/api/v1/messages/publish' => Http::response([
                'status' => 'success',
            ], 200),
        ]);

        $this->postJson('/api/v1/orders', [
            'checkout_id' => $checkout['id'],
            'payment_id' => $payment['id'],
        ], $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.status', 'paid');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://iae.example.test/api/v1/messages/publish'
            && $request->hasHeader('Authorization', 'Bearer central-token')
            && $request['exchange'] === 'iae.central.exchange'
            && $request['routing_key'] === 'checkout.order.created'
            && $request['event'] === 'checkout.order.created'
            && $request['message']['data']['checkout_id'] === $checkout['id']);
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
    private function headers(array $extra = []): array
    {
        return array_merge([
            'Accept' => 'application/json',
            'X-IAE-KEY' => 'test-key',
        ], $extra);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jwt(array $payload): string
    {
        $header = $this->base64UrlEncode(json_encode([
            'typ' => 'JWT',
            'alg' => 'HS256',
        ], JSON_THROW_ON_ERROR));

        $body = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', "{$header}.{$body}", 'sso-secret', true);

        return "{$header}.{$body}.".$this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
