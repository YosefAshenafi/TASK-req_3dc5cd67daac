# API Tests

HTTP-level contract tests that exercise every SmartPark endpoint.

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
├── Media/
│   ├── AssetUploadTest.php
│   └── AssetDeleteReferencedTest.php
├── Playlists/
│   ├── PlaylistCrudTest.php
│   └── ShareRedeemTest.php
├── Search/
│   └── SearchRankingTest.php
├── Devices/
│   └── IngestionDedupTest.php
└── Monitoring/
    └── DegradationFlagTest.php
```
