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
# All backend tests
docker compose --profile test run --rm test-runner php artisan test

# Unit tests only
docker compose --profile test run --rm test-runner php artisan test --testsuite=Unit

# API (feature) tests only
docker compose --profile test run --rm test-runner php artisan test --testsuite=Api

# Single test file
docker compose --profile test run --rm test-runner php artisan test --filter=EncryptedFieldCastTest
```
