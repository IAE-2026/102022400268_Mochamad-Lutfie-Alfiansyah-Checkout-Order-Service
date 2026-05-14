# Follow-Up Questions and Selected Answers

## Question Round 1

### Domain

Question:

~~~~text
I found the group process in the PDF as e-commerce product purchase. Is that the project domain we should plan around?
~~~~

Options:

~~~~text
E-commerce (Recommended) - Use the discovered product, cart, checkout/order, payment, promo, and review flow from the PDFs.
Different domain - You will provide a different business process and we will redesign the services around it.
~~~~

Selected answer:

~~~~text
E-commerce (Recommended)
~~~~

### My Service

Question:

~~~~text
Which individual service is your responsibility for this repository?
~~~~

Options:

~~~~text
Product & Stock - Own product catalog, search, stock read/update, and product GraphQL query.
Cart & Promo - Own cart items, promo list/apply, and cart or promo GraphQL query.
Checkout & Order - Own checkout, payments, orders, order status, and related integration calls.
~~~~

Selected answer:

~~~~text
Checkout & Order
~~~~

### Stack

Question:

~~~~text
What framework and database should your service use?
~~~~

Options:

~~~~text
Laravel + MySQL (Recommended) - Matches the assignment examples for Swagger and GraphQL, and is common for this class.
Express + PostgreSQL - Smaller JavaScript service with OpenAPI and GraphQL libraries.
FastAPI + PostgreSQL - Python API with automatic OpenAPI and simple tests, but GraphQL Playground needs extra setup.
~~~~

Selected answer:

~~~~text
Laravel + MySQL (Recommended)
~~~~

## Question Round 2

### Endpoints

Question:

~~~~text
Which Checkout & Order endpoint set should we implement for this assignment repo?
~~~~

Options:

~~~~text
Canonical v1 set (Recommended) - Use versioned endpoints for checkouts, payment methods/payments, orders, and order status, fixing the PDF’s unversioned/malformed paths.
Minimum 3 endpoints - Implement only collection, resource, and action endpoints required by the contract, plus GraphQL.
Full PDF literal - Implement every PDF-listed endpoint as closely as possible, including checkout, payment, and order flows.
~~~~

Selected answer:

~~~~text
Full PDF literal
~~~~

### Data Model

Question:

~~~~text
How detailed should the Checkout & Order database schema be?
~~~~

Options:

~~~~text
Practical assignment schema (Recommended) - Separate checkouts, checkout_items, payments, orders, and order_items with enough fields for validation and demos.
Minimal schema - Use fewer tables and store item snapshots more simply for a smaller project.
Production-like schema - Add addresses, invoices, status history, and richer payment/order audit fields.
~~~~

Selected answer:

~~~~text
Practical assignment schema (Recommended)
~~~~

### Integrations

Question:

~~~~text
How should Checkout & Order communicate with the other team services?
~~~~

Options:

~~~~text
HTTP clients + fallback (Recommended) - Call Product/Stock and Cart/Promo by configurable URLs when available, while allowing manual request data for solo testing.
HTTP clients required - Every checkout/order flow requires other services to be running and reachable.
No runtime calls - Only document external service contracts and keep this service standalone for assignment demos.
~~~~

Selected answer:

~~~~text
HTTP clients + fallback (Recommended)
~~~~

## Question Round 3

### Run Setup

Question:

~~~~text
How should the services be expected to run during team testing?
~~~~

Options:

~~~~text
One laptop network (Recommended) - All service containers join a shared Docker network and use service names/ports for HTTP calls.
Different laptops - Services call each other using LAN IP addresses configured in each .env file.
Solo demo only - Checkout & Order runs alone with fallback request payloads and documented external contracts.
~~~~

Selected answer:

~~~~text
One laptop network (Recommended)
~~~~

### Response

Question:

~~~~text
Which JSON response wrapper should Checkout & Order standardize on?
~~~~

Options:

~~~~text
Contract status wrapper (Recommended) - Use the PDF standard: status, message, data, and optional meta/errors.
Prompt success wrapper - Use success, message, data as shown in your prompt, even though it differs from the contract PDF.
Support both - Return status and success together for compatibility, at the cost of a less clean standard.
~~~~

Selected answer:

~~~~text
Contract status wrapper (Recommended)
~~~~

### Tests

Question:

~~~~text
What test coverage should the plan require?
~~~~

Options:

~~~~text
Main flow + auth (Recommended) - Feature tests for API key, validation, checkout/payment/order happy paths, 404s, and GraphQL query.
Minimal endpoint tests - Only test the minimum REST endpoints plus one authentication failure.
Broader integration tests - Include mocked HTTP calls to Product/Stock and Cart/Promo in addition to endpoint tests.
~~~~

Selected answer:

~~~~text
Main flow + auth (Recommended)
~~~~

## Question Round 4

### Repo ID

Question:

~~~~text
What repository/API-key identity should the plan use?
~~~~

Options:

~~~~text
Use placeholders (Recommended) - Plan with <NIM>_<Nama>-CheckoutOrder-Service and X-IAE-KEY=<NIM>, then replace during implementation.
I will provide exact - Choose Other and type your NIM plus name slug if you want the plan to include exact values.
~~~~

Selected answer:

~~~~text
Use placeholders (Recommended)
~~~~

### Ports

Question:

~~~~text
What Docker service names and ports should Checkout & Order use?
~~~~

Options:

~~~~text
Default 8002/33062 (Recommended) - Use checkout-order-app on host port 8002 and checkout-order-db MySQL on host port 33062.
Match team mapping - Choose Other and type the fixed app/db ports or service names if your group already assigned them.
~~~~

Selected answer:

~~~~text
Default 8002/33062 (Recommended)
~~~~

### Fields

Question:

~~~~text
Which data fields should the Checkout & Order resources use?
~~~~

Options:

~~~~text
Snapshot fields (Recommended) - Store checkout/order item snapshots with product_id, name, price, quantity, subtotal, totals, statuses, address, and payment fields.
PDF-only fields - Use only fields explicitly named in the business-process PDF and keep the schema smaller.
Team-defined fields - Choose Other and paste your exact required fields if your group already agreed on them.
~~~~

Selected answer:

~~~~text
PDF-only fields
~~~~
