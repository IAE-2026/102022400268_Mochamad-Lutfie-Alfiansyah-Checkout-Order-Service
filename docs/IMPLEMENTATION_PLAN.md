# Implementation Plan

Verbatim implementation plan produced before code was written:

~~~~text
<proposed_plan>
# Checkout & Order Service Plan

## Summary
Build a separate Laravel + MySQL repository for the e-commerce **Checkout & Order** service. It will run independently with Docker, expose versioned REST APIs plus one GraphQL query, use `X-IAE-KEY` authentication, and follow the official IAE response wrapper:

```json
{
  "status": "success",
  "message": "Data retrieved successfully",
  "data": {},
  "meta": {
    "service_name": "Checkout-Order-Service",
    "api_version": "v1"
  }
}
```

Repository name default: `<NIM>_<Nama>-CheckoutOrder-Service`  
API key default: `X-IAE-KEY=<NIM>`

## Key Changes
- Create a fresh Laravel service repo, not a monorepo.
- Add required files: `Dockerfile`, `docker-compose.yml`, `README.md`, `.env.example`, API docs, migrations, tests, and `PROMPT_LOG.md`.
- Docker defaults:
  - App service: `checkout-order-app`
  - MySQL service: `checkout-order-db`
  - Host app port: `8002`
  - Host MySQL port: `33062`
  - Shared Docker network: `iae-network`
- Use configurable HTTP integrations:
  - `PRODUCT_SERVICE_URL=http://product-stock-app:8000`
  - `CART_PROMO_SERVICE_URL=http://cart-promo-app:8000`
  - External API keys via env variables
- Support solo testing by allowing checkout item payloads directly when other services are unavailable.

## API Contract
Implement all Checkout & Order actions from the PDF, normalized to `/api/v1`.

REST endpoints:
- `GET /api/v1/checkouts`
- `POST /api/v1/checkouts`
- `GET /api/v1/checkouts/{id}`
- `GET /api/v1/payment/methods`
- `POST /api/v1/payments`
- `GET /api/v1/payments/{id}`
- `GET /api/v1/payments/{id}/status`
- `POST /api/v1/payments/confirm`
- `GET /api/v1/orders`
- `POST /api/v1/orders`
- `GET /api/v1/orders/{id}`
- `PUT /api/v1/orders/{id}/status`

GraphQL:
- Add one query equivalent to `GET /api/v1/orders/{id}`:
  - `order(id: ID!): Order`
- Provide GraphQL Playground access for testing, with API key header support.

Swagger/OpenAPI:
- Expose interactive REST documentation at Laravel Swagger route, for example `/api/documentation`.
- Document `X-IAE-KEY` as the required auth header.

## Database Schema
Use a practical schema, but keep fields close to the PDF process.

Tables:
- `checkouts`: `id`, `user_id`, `cart_id`, `shipping_address`, `payment_method`, `total_amount`, `status`, timestamps
- `checkout_items`: `id`, `checkout_id`, `product_id`, `quantity`, `price`, `subtotal`, timestamps
- `payments`: `id`, `checkout_id`, `order_id`, `payment_method`, `amount`, `status`, `confirmed_at`, timestamps
- `orders`: `id`, `checkout_id`, `user_id`, `invoice_number`, `total_amount`, `status`, timestamps
- `order_items`: `id`, `order_id`, `product_id`, `quantity`, `price`, `subtotal`, timestamps

Default statuses:
- Checkout: `draft`, `confirmed`, `converted_to_order`
- Payment: `pending`, `paid`, `failed`, `confirmed`
- Order: `pending_payment`, `paid`, `processing`, `shipped`, `delivered`, `completed`, `cancelled`

## Inter-Service Flow
- Checkout creation may fetch cart data from Cart & Promo service, or accept manual `items` in request body for solo testing.
- Checkout/order flow may validate and reduce product stock through Product & Stock service over HTTP.
- No service may read another service’s database directly.
- All outgoing service calls use JSON, UTF-8, and `X-IAE-KEY`.

## Error Handling
Use the official error wrapper:

```json
{
  "status": "error",
  "message": "Resource not found",
  "errors": null
}
```

Status codes:
- `200` for successful reads/updates
- `201` for created checkout, payment, or order
- `401` for missing/invalid `X-IAE-KEY`
- `404` for missing resources
- `422` for validation errors
- `409` for invalid payment/order status transitions
- `502` only when strict external-service integration is required and unavailable

## Test Plan
Add Laravel feature tests for:
- API key required on protected endpoints
- Response wrapper format for success and error responses
- Create checkout with manual items
- List/get checkout
- Get payment methods
- Create payment, get payment, get payment status, confirm payment
- Create order from confirmed checkout/payment
- Get order and update order status
- 404 resource behavior
- Validation error behavior
- GraphQL `order(id)` query with selected fields

## Assumptions
- Domain is e-commerce purchase flow from the PDFs.
- Your service responsibility is Checkout & Order.
- Stack is Laravel + MySQL.
- The official Standard Integration Contract overrides the prompt’s `success: true` example.
- Exact NIM/name will replace `<NIM>` and `<Nama>` during implementation.
</proposed_plan>
~~~~
