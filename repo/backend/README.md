# SmartPark Backend — Laravel 13 / PHP 8.3

REST API for SmartPark Media Operations — on-premises parking media + device ingestion system.

See [`../CLAUDE.md`](../CLAUDE.md) for full technical details and architecture notes.

## Entry point

All REST routes: `backend/routes/api.php`

## Architecture

```
backend/
├── routes/api.php               # All REST routes
├── app/
│   ├── Http/
│   │   ├── Controllers/         # Thin controllers — business logic in Services/
│   │   └── Middleware/
│   │       └── RoleMiddleware.php
│   ├── Models/                  # 12 Eloquent models
│   ├── Services/                # Business logic (MediaValidator, etc.)
│   ├── Jobs/                    # Async: GenerateThumbnails, IndexAsset, etc.
│   ├── Casts/
│   │   └── EncryptedField.php   # AES field-level encryption
│   ├── Logging/
│   │   └── MaskSensitiveFields.php  # Monolog processor — redacts sensitive fields
│   └── Console/Commands/        # gateway:run, app:monitoring-sample, etc.
├── config/
│   └── smartpark.php            # Circuit breaker, media limits, latency thresholds
└── database/
    ├── migrations/
    ├── factories/
    └── seeders/
```

## Tests

```bash
# All backend tests (Unit + Feature)
docker compose --profile test run --rm test-runner php artisan test

# Unit tests only
docker compose --profile test run --rm test-runner php artisan test --testsuite=Unit

# Feature / API (HTTP contract) tests only — uses smartpark_test DB
docker compose --profile test run --rm test-runner php artisan test --testsuite=Feature

# Alias for feature tests (Api testsuite maps to tests/Feature/)
docker compose --profile test run --rm test-runner php artisan test --testsuite=Api

# Single test file
docker compose --profile test run --rm test-runner php artisan test --filter=EncryptedFieldCastTest
```

## Device Gateway

The gateway service reads `GATEWAY_TOKEN` from the environment (or Docker secrets) and sends it as `X-Gateway-Token` on every event POST to `/api/gateway/events`.

**Seeding / rotation:**
1. Generate a secret: `openssl rand -hex 32`
2. Set `GATEWAY_TOKEN=<secret>` in the gateway and backend service environments.
3. To rotate: update the value in both environments and restart the `gateway` service.

To inspect or requeue dead-lettered events:
```bash
docker compose --profile devices run --rm gateway php artisan gateway:dead-letter --list
docker compose --profile devices run --rm gateway php artisan gateway:dead-letter --requeue=<idempotency_key>
```
