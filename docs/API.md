# API Documentation

Base URL:

```text
http://localhost:8002
```

Required header for protected endpoints:

```text
X-IAE-KEY: 102022400268
Content-Type: application/json
Accept: application/json
```

## Checkout

### `GET /api/v1/checkouts`

Returns all checkouts with items.

### `POST /api/v1/checkouts`

Creates a checkout from manual items or a `cart_id`.

Request:

```json
{
  "user_id": 1,
  "cart_id": "cart-001",
  "shipping_address": "Jl. Telekomunikasi No. 1, Bandung",
  "payment_method": "bank_transfer",
  "items": [
    {
      "product_id": 10,
      "quantity": 2,
      "price": 150000
    }
  ]
}
```

### `GET /api/v1/checkouts/{id}`

Returns one checkout with items, payments, and order.

## Payment

### `GET /api/v1/payment/methods`

Returns supported payment methods:

- `bank_transfer`
- `e_wallet`
- `credit_card`
- `cod`

### `POST /api/v1/payments`

Creates a payment for a checkout.

```json
{
  "checkout_id": 1,
  "payment_method": "bank_transfer",
  "amount": 300000
}
```

### `GET /api/v1/payments/{id}`

Returns one payment with checkout and order data.

### `GET /api/v1/payments/{id}/status`

Returns compact payment status data.

### `POST /api/v1/payments/confirm`

Confirms a payment.

```json
{
  "payment_id": 1,
  "status": "confirmed"
}
```

## Order

### `GET /api/v1/orders`

Returns all orders with items and payment.

### `POST /api/v1/orders`

Creates an order directly for assignment rubric testing, or from a checkout that has a confirmed or paid payment.

Direct request:

```json
{
  "user_id": 1,
  "shipping_address": "Jl. Telekomunikasi No. 1, Bandung",
  "payment_method": "bank_transfer",
  "items": [
    {
      "product_id": 10,
      "quantity": 2,
      "price": 150000
    }
  ]
}
```

Checkout/payment request:

```json
{
  "checkout_id": 1,
  "payment_id": 1
}
```

### `GET /api/v1/orders/{id}`

Returns one order with items, payment, and checkout.

### `PUT /api/v1/orders/{id}/status`

Updates the order status.

```json
{
  "status": "processing"
}
```

Allowed statuses:

- `pending_payment`
- `paid`
- `processing`
- `shipped`
- `delivered`
- `completed`
- `cancelled`

## GraphQL

Endpoint:

```text
POST /graphql
POST /api/graphql
POST /api/v1/graphql
```

Example:

```graphql
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
```

Variables:

```json
{
  "id": 1
}
```

Interactive page:

```text
GET /graphql
GET /graphql-playground
```
