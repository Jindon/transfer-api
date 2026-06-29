# Transfer API

A secure, production-grade REST API for transferring funds between accounts. Built with PHP 8.5, Symfony 8.1, MySQL 8.0, and Redis 7.

## Architecture

Modules under `src/` follow a per-feature DDD layout: `Domain` → `Application` → `Infrastructure` → `Presentation`.

```
src/
├── Account/       # Account entity, balance mutations
├── Transfer/      # Core transfer flow (command → handler → DB transaction)
├── Idempotency/   # Key reservation and Redis replay cache
├── Ledger/        # Double-entry bookkeeping for completed transfers
├── Security/      # API key authenticator
└── Shared/        # Money value object, Currency enum
```

### Transfer flow

`POST /api/transfers` accepts an `X-Api-Key` and `Idempotency-Key` header:

1. Controller maps the request to a `TransferCommand` with a SHA-256 body hash.
2. Handler checks the Redis replay cache - on a hit, returns the cached result without touching the DB.
3. On a cache miss, the handler reserves the idempotency key via a unique-constraint INSERT.
4. `TransactionRunner` locks source and destination accounts in a deterministic order (by ID) to prevent deadlocks, debits/credits balances, writes double-entry ledger rows, and marks the transfer `COMPLETED`. Retries up to 3× on deadlock/lock-timeout with exponential backoff + jitter.
5. On success, the result is written to Redis (24 h TTL).
6. On failure, the transfer is marked `FAILED` and the idempotency key is released.

### Key design decisions

- **Money is integer cents.** `Money::make(int $amount, string $currencyCode)` — never floats.
- **Idempotency** uses a two-phase approach: reserve a DB row (unique key constraint), then cache the result in Redis. Repeat requests short-circuit at the cache layer.
- **Deadlock prevention** via consistent account locking order (`getOrderedLockForUpdate` sorts IDs ascending before acquiring pessimistic write locks).
- **Error responses** follow RFC 7807 (`application/problem+json`).

---

## Setup

### With Docker (recommended)

**Requirements:** Docker with Compose plugin.

```bash
git clone <repo-url> && cd transfer-api

# Start all services (app on :8000, MySQL on :3306, Redis on :6379)
docker compose up -d
```

The entrypoint automatically runs migrations and seeds four accounts on first start. The API is ready at `http://localhost:8000`.

To tail logs:

```bash
docker compose logs -f app
```

To stop:

```bash
docker compose down
```

### Without Docker

**Requirements:** PHP 8.5, Composer, MySQL 8.0, Redis 7.

```bash
git clone <repo-url> && cd transfer-api

composer install

# Copy and edit environment variables
cp .env .env.local
```

Edit `.env.local` and set:

```dotenv
DATABASE_URL="mysql://user:password@127.0.0.1:3306/transfer_app?serverVersion=8.0&charset=utf8mb4"
REDIS_URL=redis://localhost:6379
MESSENGER_TRANSPORT_DSN=redis://localhost:6379/transfer_events
API_KEY=your_api_key_here
APP_SECRET=your_app_secret_here
```

```bash
# Run migrations
./bin/console doctrine:migrations:migrate

# Seed four accounts for testing (Custom seeder)
./bin/console app:seed:accounts

# Start the development server
symfony serve
```

---

## API

All endpoints require the `X-Api-Key` header.

### Accounts

#### List accounts

```
GET /api/accounts
```

```bash
curl http://localhost:8000/api/accounts \
  -H "X-Api-Key: super_secure_api_key"
```

```json
[
  {
    "uuid": "0196b1a2-...",
    "currency": "EUR",
    "balance": "EUR 1000.00",
    "createdAt": "2026-06-28T10:00:00+00:00",
    "updatedAt": null
  }
]
```

#### Get account

```
GET /api/accounts/{uuid}
```

```bash
curl http://localhost:8000/api/accounts/0196b1a2-... \
  -H "X-Api-Key: super_secure_api_key"
```

### Transfers

#### Create transfer

```
POST /api/transfers
```

Requires an additional `Idempotency-Key` header. Repeat requests with the same key return the original response without re-processing.

```bash
curl -X POST http://localhost:8000/api/transfers \
  -H "X-Api-Key: super_secure_api_key" \
  -H "Idempotency-Key: unique-request-id-1" \
  -H "Content-Type: application/json" \
  -d '{
    "sourceAccountUuid": "0196b1a2-...",
    "destinationAccountUuid": "0196b1a3-...",
    "amount": 100000,
    "currency": "EUR"
  }'
```

> `amount` is in the smallest currency unit (cents). `100000` = EUR 1000.00.

```json
{
  "uuid": "0196b1a4-...",
  "status": "COMPLETED",
  "sourceAccountUuid": "0196b1a2-...",
  "destinationAccountUuid": "0196b1a3-...",
  "amount": "EUR 1000.00",
  "reference": null,
  "createdAt": "2026-06-28T10:01:00+00:00",
  "completedAt": "2026-06-28T10:01:00+00:00"
}
```

#### Get transfer

```
GET /api/transfers/{uuid}
```

```bash
curl http://localhost:8000/api/transfers/0196b1a4-... \
  -H "X-Api-Key: super_secure_api_key"
```

### Error responses

All errors follow [RFC 7807](https://datatracker.ietf.org/doc/html/rfc7807) (`application/problem+json`):

| Scenario | Status | `type`                             |
|---|---|------------------------------------|
| Missing `X-Api-Key` | 401 | `unauthorized`                     |
| Missing `Idempotency-Key` | 400 | `missing-idempotency-key-header`   |
| Validation failure | 422 | `default symfony's validation type` |
| Insufficient funds | 422 | `insufficient-funds`               |
| Account not found | 404 | `not-found`                        |
| Transfer not found | 404 | `not-found`                        |
| Duplicate idempotency key (in-flight) | 409 | `transfer-conflict`                |
| Idempotency key reused with different payload | 409 | `request-hash-mismatch`            |
| Transfer already processed | 409 | `already-processed`                |

---

## Testing

### Test environment setup

Tests run against a dedicated database. Create it and build the schema before running the suite for the first time:

```bash
./bin/console doctrine:database:create --env=test
./bin/console doctrine:schema:create --env=test
```

The test environment is configured in `.env.test`. By default it connects as `root` with no password on `127.0.0.1:3306`. Override any value locally in `.env.test.local` (not committed):

```dotenv
# .env.test.local — only needed if your local MySQL differs from the defaults
DB_USER=root
DB_PASSWORD=your_password
DB_NAME=transfer_api
REDIS_URL=redis://localhost:6379
```

Redis must also be running - it is used by the idempotency replay cache and the Messenger transport. The default test configuration expects it at `redis://localhost:6379`.

If you are using Docker, the database and Redis containers are already exposed on their default ports (MySQL `:3306`, Redis `:6379`) via `compose.override.yaml`, so no extra setup is needed — just run `docker compose up -d` first.

### Running tests

```bash
# Run all tests
./bin/phpunit

# Single file
./bin/phpunit tests/Transfer/Unit/Domain/TransferTest.php

# Single method
./bin/phpunit --filter testMethodName tests/Path/To/Test.php
```

### Test layers

| Layer | What it covers | DB? |
|---|---|---|
| `Unit` | Pure domain logic (Money, Transfer, Account) | No |
| `Integration` | Repository queries, request validation | Yes |
| `Functional` | Full HTTP stack via `WebTestCase` | Yes |

Integration and functional tests are wrapped in a transaction that rolls back automatically after each test (DAMA Doctrine Test Bundle), so no manual cleanup is needed between runs.

---

## Development

```bash
# Code style check / fix
composer cs-check
composer cs-fix

# Static analysis (PHPStan level 4)
./vendor/bin/phpstan analyse
```

---

## What was not implemented

- **Account management endpoints** - accounts are seeded via a console command only. A production system would need `POST /api/accounts` for creation and `DELETE` or a status field for deactivation.
- **Transfer listing** - no `GET /api/transfers` to list transfers, filtered by account or status. Useful for reconciliation and support tooling.
- **Pagination** - the `GET /api/accounts` endpoint returns all rows. A cursor- or offset-based pagination strategy is needed before this scales.
- **Multi-currency transfers** — only EUR is supported at this time. To support additional currencies, `Currency::SUPPORTED_CURRENCIES` would be extended and cross-currency transfers would require FX conversion: a scheduled console command would poll an external rate provider and persist the latest rates into an `exchange_rates` table. The `TransactionRunner` would look up the applicable rate at execution time, convert the debit amount, and record both the original and converted amounts in the ledger rows so the rate used is permanently auditable.

- **Transfer fees** — no fee is deducted from transfers. A production system would define a fee structure (flat, percentage, or tiered) and apply it during the transaction: the fee amount would be debited from the source account alongside the principal, credited to a designated fee account, and recorded as its own ledger entries so fee revenue is fully auditable.

- **Transfer reversal** - no mechanism to reverse or refund a completed transfer.

- **Rate limiting** - no per-key or per-IP throttling on any endpoint.

- **Per-user authentication** - the single shared API key is suitable for a service-to-service context but not for multi-tenant use. A real system would need OAuth2 / JWT with scoped credentials. I have kept it simple for this task.

- **OpenAPI specification** - the API contract lives only in this README. A machine-readable spec (e.g. via NelmioApiDocBundle) would enable client generation and contract testing.

- **Observability** - the application logs via Monolog but does not yet expose telemetry in a way that external systems can reliably consume.

- **Concurrency / load tests** — the deadlock-prevention and retry logic is covered by unit tests but has not been exercised under concurrent load. May be using a dedicated load-testing tool (k6, Gatling, or Locust) with a scenario that hammers the same pair of accounts simultaneously.

---

## Time invested

~20 hours

## AI tools used

[Claude Code](https://claude.ai/code) (Claude Sonnet 4.6) was used throughout development for exploring solutions, generating tests and code review.
[ChatGPT](https://chatgpt.com/) was also used as search tool
