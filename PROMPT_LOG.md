# AI Prompt Log

This file records the AI-assisted implementation history required by the assignment.

## 2026-05-14

### User Request

Implement an IAE Tugas 2 service-based assignment project for the Checkout & Order service using Laravel, MySQL, Docker, REST, GraphQL, Swagger/OpenAPI, API-key security, migrations, and tests.

### Planning Decisions

- Domain: e-commerce purchase flow.
- Service responsibility: Checkout & Order.
- Stack: Laravel + MySQL.
- API key header: `X-IAE-KEY: 102022400268`.
- Response wrapper: official Standard Integration Contract format with `status`, `message`, `data`, `meta`, and `errors`.
- Docker app port: `8002`.
- Docker MySQL host port: `33062`.
- Shared Docker network: `iae-network`.

### Implementation Notes

- Created a standalone Laravel repository scaffold.
- Added REST endpoints under `/api/v1`.
- Added GraphQL order query at `/api/graphql`.
- Added OpenAPI JSON and Swagger UI route.
- Added Dockerfile, Docker Compose, `.env.example`, docs, migrations, models, and feature tests.
