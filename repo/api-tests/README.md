# API Tests — Guide

> This directory is a **guide / catalog**, not an executable suite. The real Pest HTTP
> contract tests live at `backend/tests/Feature/`; running `php artisan test` from inside
> the `test-runner` container (or via `./run_tests.sh`) is what actually executes them.

- **Tooling:** Pest (PHP), runnable inside the `test-runner` Docker container.
- **Database:** Dedicated `smartpark_test` MySQL schema; refreshed per run via `RefreshDatabase`.

## Run

```bash
docker compose --profile test run --rm test-runner php artisan test --testsuite=Api
```

## Test files

```
backend/tests/Feature/
├── Auth/
│   ├── LoginTest.php
│   └── UserManagementTest.php
├── Contracts/
│   └── ApiContractTest.php
├── Devices/
│   ├── GatewayAuthTest.php
│   ├── IngestionDedupTest.php
│   └── ReplayAuditTest.php
├── Media/
│   ├── AssetDeleteReferencedTest.php
│   ├── AssetDurationTest.php
│   ├── AssetReplaceTest.php
│   └── AssetUploadTest.php
├── Monitoring/
│   └── DegradationFlagTest.php
├── Playlists/
│   ├── PlaylistCrudTest.php
│   └── ShareRedeemTest.php
├── Search/
│   └── SearchRankingTest.php
└── Security/
    └── CrossUserIsolationTest.php
```
