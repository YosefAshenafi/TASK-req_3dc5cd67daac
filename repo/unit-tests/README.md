# Unit Tests — Guide

> This directory is a **guide / catalog**, not an executable suite. Backend unit tests
> live under `backend/tests/Unit/` (Pest) and frontend unit tests live under
> `frontend/src/tests/unit/` (Vitest). The commands below invoke those real suites.

Pure function / single-class tests with no I/O — fastest feedback loop.

- **Backend tooling:** Pest (PHP)
- **Frontend tooling:** Vitest (TypeScript)

## Run

```bash
# Backend unit tests
docker compose --profile test run --rm test-runner php artisan test --testsuite=Unit

# Frontend unit tests
docker compose --profile dev run --rm frontend-dev npx vitest run
```

## Test files

```
Backend:
  backend/tests/Unit/EncryptedFieldCastTest.php
  backend/tests/Unit/MediaValidatorTest.php
  backend/tests/Unit/Gateway/RetryClassifierTest.php

Frontend:
  frontend/src/tests/unit/auth.store.test.ts
```
