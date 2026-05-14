# Checkout & Order Service

Laravel + MySQL service for the IAE Tugas 2 e-commerce checkout, payment, and order flow.

Repository naming format for submission:

```text
102022400268_Mochamad-Lutfie-Alfiansyah-Checkout-Order-Service
```

GitHub repository:

```text
https://github.com/IAE-2026/102022400268_Mochamad-Lutfie-Alfiansyah-Checkout-Order-Service
```

## Features

- Versioned REST API under `/api/v1`
- GraphQL order query at `/api/graphql`
- GraphQL Playground at `/graphql-playground`
- Swagger/OpenAPI page at `/api/documentation`
- API key protection with `X-IAE-KEY`
- Official IAE JSON response wrapper
- Independent MySQL database
- Dockerfile and Docker Compose setup
- Feature tests for the main endpoints

## Requirements

- Docker Desktop
- Composer and PHP 8.2+ for local development without Docker

## Quick Start With Docker

```bash
cp .env.example .env
docker compose up --build
```

The app will be available at:

- REST docs: `http://localhost:8002/api/documentation`
- GraphQL Playground: `http://localhost:8002/graphql-playground`
- API base URL: `http://localhost:8002/api/v1`

Default API key:

```text
X-IAE-KEY: 102022400268
```

Change it in `.env`:

```env
IAE_API_KEY=your_nim
```

## API Response Format

Success:

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

Error:

```json
{
  "status": "error",
  "message": "Resource not found",
  "errors": null
}
```

## Main Endpoints

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
- `POST /api/graphql`

## Example Checkout Request

```bash
curl -X POST http://localhost:8002/api/v1/checkouts \
  -H "Content-Type: application/json" \
  -H "X-IAE-KEY: 102022400268" \
  -d '{
    "user_id": 1,
    "shipping_address": "Jl. Telekomunikasi No. 1, Bandung",
    "payment_method": "bank_transfer",
    "items": [
      { "product_id": 10, "quantity": 2, "price": 150000 }
    ]
  }'
```

## Inter-Service Communication

This service never reads another service database directly. Configure HTTP service URLs in `.env`:

```env
CART_PROMO_SERVICE_URL=http://cart-promo-app:8000
PRODUCT_SERVICE_URL=http://product-stock-app:8000
```

Checkout creation accepts manual `items` for solo demos. If `items` is omitted and `cart_id` is provided, the service tries to fetch the cart from Cart & Promo service.

Product stock validation and deduction are opt-in:

```env
PRODUCT_STOCK_VALIDATION=true
PRODUCT_STOCK_DEDUCTION=true
```

## Local Development

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve --port=8002
```

## Tests

```bash
php artisan test
```

Tests use SQLite in memory and do not require Docker.

## Documentation Files

- REST API details: `docs/API.md`
- Machine-readable OpenAPI spec: `public/openapi.json`
- Prompting log: `PROMPT_LOG.md`
