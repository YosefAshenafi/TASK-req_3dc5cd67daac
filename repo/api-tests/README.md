# API Tests

HTTP-level contract tests that exercise every endpoint declared in `../docs/api-specs.md`.

- **Tooling:** Pest (PHP) for controller + integration tests, runnable inside the `test-runner` container.
- **Scope:** auth, users, media, favorites, playlists (incl. share/redeem), recommendations, device events, replay, monitoring.
- **Database:** uses a dedicated `smartpark_test` MySQL schema; refreshed between runs via `RefreshDatabase` trait.
- **Run:**
  ```bash
  docker compose --profile test run --rm test-runner \
    php artisan test --testsuite=Api
  ```

## Layout (created during Phase 2 onward)

```
api-tests/
├── Auth/
│   └── LoginTest.php
├── Media/
│   ├── AssetUploadTest.php
│   └── AssetDeleteReferencedTest.php
├── Playlists/
│   ├── ShareRedeemTest.php
│   └── PlaylistCrudTest.php
├── Search/
│   └── SearchRankingTest.php
├── Devices/
│   ├── IngestionDedupTest.php
│   ├── OutOfOrderTest.php
│   └── ReplayAuditTest.php
└── Monitoring/
    └── DegradationFlagTest.php
```

Each test is a black-box HTTP call against the booted Laravel app with factories for seed data. See the **Exit Criteria** checklist in `../PLAN.md` for the tests that gate each phase.
