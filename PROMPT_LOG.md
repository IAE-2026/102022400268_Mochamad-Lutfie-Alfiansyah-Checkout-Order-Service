# Prompt Log

This repository includes the requested word-for-word user-visible chat records in Markdown format.

- Full visible transcript: [docs/CHAT_HISTORY_VERBATIM.md](docs/CHAT_HISTORY_VERBATIM.md)
- Follow-up questions and selected answers: [docs/FOLLOW_UP_QUESTIONS.md](docs/FOLLOW_UP_QUESTIONS.md)
- Implementation plan: [docs/IMPLEMENTATION_PLAN.md](docs/IMPLEMENTATION_PLAN.md)

Hidden system/developer instructions, internal tool calls, and command outputs are not part of the user-visible chat transcript.

## Tugas 3 Progress Log

- User requested a Mermaid sequence diagram for the most critical Checkout Order endpoint.
- Critical endpoint selected: `POST /api/v1/orders`.
- User requested classification of diagram elements into boundary, controller, and entity.
- User requested implementation of Tugas 3 based on the existing Laravel service.
- Implemented SSO JWT role mapping, SOAP XML audit client, RabbitMQ event publisher, and related documentation/tests.
- User provided URL dan Akun Tugas IAE PDF; implementation was adjusted to `https://iae-sso.virtualfri.id`, JWKS RS256 verification, SOAP `iae:AuditRequest`, and central message publish endpoint.
