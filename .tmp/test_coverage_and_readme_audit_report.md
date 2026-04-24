# Test Coverage Audit

## Scope and Method
- Mode: static inspection only (no execution).
- Inspected areas: `repo/backend/routes/api.php`, `repo/backend/tests/**`, `repo/frontend/src/tests/**`, `repo/frontend/e2e/**`, `repo/README.md`, `repo/run_tests.sh`, minimal config files.

## Project Type Detection
- Declared type in README: **fullstack** (`repo/README.md`: `Project type: fullstack`).
- Inference result: not needed.

## Backend Endpoint Inventory
Resolved with Laravel API prefix `/api` from `repo/backend/bootstrap/app.php` + `repo/backend/routes/api.php`.

1. `GET /api/health`
2. `POST /api/auth/login`
3. `GET /api/settings`
4. `POST /api/gateway/events`
5. `POST /api/auth/logout`
6. `GET /api/auth/me`
7. `GET /api/search`
8. `GET /api/assets`
9. `GET /api/assets/{id}`
10. `POST /api/assets/{id}/play`
11. `GET /api/favorites`
12. `PUT /api/favorites/{asset_id}`
13. `DELETE /api/favorites/{asset_id}`
14. `POST /api/playlists/redeem`
15. `DELETE /api/playlists/shares/{id}`
16. `GET /api/playlists`
17. `POST /api/playlists`
18. `GET /api/playlists/{id}`
19. `PUT /api/playlists/{id}`
20. `PATCH /api/playlists/{id}`
21. `DELETE /api/playlists/{id}`
22. `POST /api/playlists/{id}/share`
23. `POST /api/playlists/{id}/items`
24. `DELETE /api/playlists/{id}/items/{itemId}`
25. `PUT /api/playlists/{id}/items/order`
26. `GET /api/history`
27. `GET /api/history/sessions`
28. `GET /api/now-playing`
29. `GET /api/recommendations`
30. `POST /api/assets`
31. `DELETE /api/assets/{id}`
32. `POST /api/admin/assets/{id}/replace`
33. `GET /api/users`
34. `POST /api/users`
35. `GET /api/users/{id}`
36. `PUT /api/users/{id}`
37. `PATCH /api/users/{id}`
38. `DELETE /api/users/{id}`
39. `PATCH /api/users/{id}/freeze`
40. `PATCH /api/users/{id}/unfreeze`
41. `PATCH /api/users/{id}/blacklist`
42. `GET /api/monitoring/status`
43. `POST /api/monitoring/feature-flags/{flag}/reset`
44. `PUT /api/settings`
45. `POST /api/devices/events`
46. `GET /api/devices`
47. `GET /api/devices/{id}`
48. `GET /api/devices/{id}/events`
49. `GET /api/devices/{id}/replay/audits`
50. `POST /api/devices/{id}/replay`

## API Test Mapping Table

| Endpoint | Covered | Test type | Test files | Evidence |
|---|---|---|---|---|
| `GET /api/health` | yes | true no-mock HTTP | `tests/Feature/HealthTest.php` | `HealthTest.php:3` `test('health endpoint returns ok'...)` |
| `POST /api/auth/login` | yes | true no-mock HTTP | `tests/Feature/Auth/LoginTest.php`, `tests/Feature/Security/RateLimitTest.php` | `LoginTest.php:17`, `RateLimitTest.php:29` |
| `GET /api/settings` | yes | true no-mock HTTP | `tests/Feature/Settings/SettingsEndpointTest.php` | `SettingsEndpointTest.php:6` |
| `POST /api/gateway/events` | yes | true no-mock HTTP | `tests/Feature/Devices/GatewayAuthTest.php` | `GatewayAuthTest.php:18` |
| `POST /api/auth/logout` | yes | true no-mock HTTP | `tests/Feature/Auth/LoginTest.php`, `tests/Feature/Security/AccountStatusTest.php` | `LoginTest.php:169`, `AccountStatusTest.php:52` |
| `GET /api/auth/me` | yes | true no-mock HTTP | `tests/Feature/Auth/LoginTest.php`, `tests/Feature/Security/AccountStatusTest.php` | `LoginTest.php:147`, `AccountStatusTest.php:13` |
| `GET /api/search` | yes | true no-mock HTTP | `tests/Feature/Search/SearchRankingTest.php`, `tests/Feature/Media/AssetDurationTest.php` | `SearchRankingTest.php:8` |
| `GET /api/assets` | yes | true no-mock HTTP | `tests/Feature/Security/UnpublishedAssetAccessTest.php` | `UnpublishedAssetAccessTest.php:96` |
| `GET /api/assets/{id}` | yes | HTTP with mocking | `tests/Feature/Media/AssetUploadTest.php`, `tests/Feature/Media/AssetVisibilityTest.php`, `tests/Feature/Contracts/ApiContractTest.php` | `AssetUploadTest.php:337`; storage provider faked in `AssetUploadTest.php:19` (`Storage::fake`) |
| `POST /api/assets/{id}/play` | yes | true no-mock HTTP | `tests/Feature/History/SessionHistoryTest.php`, `tests/Feature/Contracts/ApiContractTest.php` | `SessionHistoryTest.php:7` |
| `GET /api/favorites` | yes | true no-mock HTTP | `tests/Feature/Security/CrossUserIsolationTest.php`, `tests/Feature/Security/UnpublishedAssetAccessTest.php` | `CrossUserIsolationTest.php:10` |
| `PUT /api/favorites/{asset_id}` | yes | true no-mock HTTP | `tests/Feature/Security/UnpublishedAssetAccessTest.php` | `UnpublishedAssetAccessTest.php:14` |
| `DELETE /api/favorites/{asset_id}` | yes | true no-mock HTTP | `tests/Feature/Security/CrossUserIsolationTest.php` | `CrossUserIsolationTest.php:71` |
| `POST /api/playlists/redeem` | yes | true no-mock HTTP | `tests/Feature/Playlists/ShareRedeemTest.php` | `ShareRedeemTest.php:45` |
| `DELETE /api/playlists/shares/{id}` | yes | true no-mock HTTP | `tests/Feature/Playlists/ShareRedeemTest.php`, `tests/Feature/Security/CrossUserIsolationTest.php` | `ShareRedeemTest.php:136` |
| `GET /api/playlists` | yes | true no-mock HTTP | `tests/Feature/Playlists/PlaylistCrudTest.php`, `tests/Feature/Contracts/ApiContractTest.php` | `PlaylistCrudTest.php:20` |
| `POST /api/playlists` | yes | true no-mock HTTP | `tests/Feature/Playlists/PlaylistCrudTest.php` | `PlaylistCrudTest.php:8` |
| `GET /api/playlists/{id}` | yes | true no-mock HTTP | `tests/Feature/Playlists/PlaylistCrudTest.php` | `PlaylistCrudTest.php:31` |
| `PUT /api/playlists/{id}` | yes | true no-mock HTTP | `tests/Feature/Playlists/PlaylistCrudTest.php` | `PlaylistCrudTest.php:50` |
| `PATCH /api/playlists/{id}` | yes | true no-mock HTTP | `tests/Feature/Playlists/PlaylistCrudTest.php` | `PlaylistCrudTest.php:60` |
| `DELETE /api/playlists/{id}` | yes | true no-mock HTTP | `tests/Feature/Playlists/PlaylistCrudTest.php` | `PlaylistCrudTest.php:70` |
| `POST /api/playlists/{id}/share` | yes | true no-mock HTTP | `tests/Feature/Playlists/ShareRedeemTest.php`, `tests/Feature/Security/RateLimitTest.php` | `ShareRedeemTest.php:15` |
| `POST /api/playlists/{id}/items` | yes | true no-mock HTTP | `tests/Feature/Playlists/PlaylistCrudTest.php`, `tests/Feature/Security/UnpublishedAssetAccessTest.php` | `PlaylistCrudTest.php:91` |
| `DELETE /api/playlists/{id}/items/{itemId}` | yes | true no-mock HTTP | `tests/Feature/Playlists/PlaylistCrudTest.php` | `PlaylistCrudTest.php:108` |
| `PUT /api/playlists/{id}/items/order` | yes | true no-mock HTTP | `tests/Feature/Playlists/PlaylistCrudTest.php`, `tests/Feature/Contracts/ApiContractTest.php` | `PlaylistCrudTest.php:121` |
| `GET /api/history` | yes | true no-mock HTTP | `tests/Feature/History/SessionHistoryTest.php`, `tests/Feature/Security/CrossUserIsolationTest.php` | `SessionHistoryTest.php:26` |
| `GET /api/history/sessions` | yes | true no-mock HTTP | `tests/Feature/History/SessionHistoryTest.php` | `SessionHistoryTest.php:48` |
| `GET /api/now-playing` | yes | true no-mock HTTP | `tests/Feature/History/SessionHistoryTest.php` | `SessionHistoryTest.php:134` |
| `GET /api/recommendations` | yes | true no-mock HTTP | `tests/Feature/Search/RecommendationEndpointTest.php` | `RecommendationEndpointTest.php:9` |
| `POST /api/assets` | yes | true no-mock HTTP + HTTP with mocking | `tests/Feature/Media/AssetNoMockHttpCoverageTest.php`, `tests/Feature/Media/AssetUploadTest.php`, `tests/Feature/Contracts/ApiContractTest.php` | `AssetNoMockHttpCoverageTest.php:17`; mocked variant uses `Storage::fake` in `AssetUploadTest.php:19` |
| `DELETE /api/assets/{id}` | yes | true no-mock HTTP + HTTP with mocking | `tests/Feature/Media/AssetNoMockHttpCoverageTest.php`, `tests/Feature/Media/AssetDeleteReferencedTest.php` | `AssetNoMockHttpCoverageTest.php:38`; mocked variant `AssetDeleteReferencedTest.php:15` |
| `POST /api/admin/assets/{id}/replace` | yes | true no-mock HTTP + HTTP with mocking | `tests/Feature/Media/AssetNoMockHttpCoverageTest.php`, `tests/Feature/Media/AssetReplaceTest.php` | `AssetNoMockHttpCoverageTest.php:53`; mocked variant `AssetReplaceTest.php:17` |
| `GET /api/users` | yes | true no-mock HTTP | `tests/Feature/Auth/LoginTest.php` | `LoginTest.php:204` |
| `POST /api/users` | yes | true no-mock HTTP | `tests/Feature/Auth/UserManagementTest.php` | `UserManagementTest.php:5` |
| `GET /api/users/{id}` | yes | true no-mock HTTP | `tests/Feature/Auth/UserShowUpdateTest.php` | `UserShowUpdateTest.php:5` |
| `PUT /api/users/{id}` | yes | true no-mock HTTP | `tests/Feature/Auth/UserShowUpdateTest.php` | `UserShowUpdateTest.php:16` |
| `PATCH /api/users/{id}` | yes | true no-mock HTTP | `tests/Feature/Auth/UserShowUpdateTest.php` | `UserShowUpdateTest.php:30` |
| `DELETE /api/users/{id}` | yes | true no-mock HTTP | `tests/Feature/Auth/UserManagementTest.php` | `UserManagementTest.php:65` |
| `PATCH /api/users/{id}/freeze` | yes | true no-mock HTTP | `tests/Feature/Auth/UserManagementTest.php`, `tests/Feature/Contracts/ApiContractTest.php` | `UserManagementTest.php:20` |
| `PATCH /api/users/{id}/unfreeze` | yes | true no-mock HTTP | `tests/Feature/Auth/UserManagementTest.php`, `tests/Feature/Contracts/ApiContractTest.php` | `UserManagementTest.php:39` |
| `PATCH /api/users/{id}/blacklist` | yes | true no-mock HTTP | `tests/Feature/Auth/UserManagementTest.php`, `tests/Feature/Contracts/ApiContractTest.php` | `UserManagementTest.php:51` |
| `GET /api/monitoring/status` | yes | true no-mock HTTP | `tests/Feature/Monitoring/DegradationFlagTest.php` | `DegradationFlagTest.php:8` |
| `POST /api/monitoring/feature-flags/{flag}/reset` | yes | true no-mock HTTP | `tests/Feature/Monitoring/DegradationFlagTest.php` | `DegradationFlagTest.php:71` |
| `PUT /api/settings` | yes | true no-mock HTTP | `tests/Feature/Settings/SettingsEndpointTest.php` | `SettingsEndpointTest.php:30` |
| `POST /api/devices/events` | yes | true no-mock HTTP | `tests/Feature/Devices/IngestionDedupTest.php`, `tests/Feature/Devices/AuditPersistenceTest.php` | `IngestionDedupTest.php:27` |
| `GET /api/devices` | yes | true no-mock HTTP | `tests/Feature/Devices/IngestionDedupTest.php` | `IngestionDedupTest.php:110` |
| `GET /api/devices/{id}` | yes | true no-mock HTTP | `tests/Feature/Devices/IngestionDedupTest.php` | `IngestionDedupTest.php:155` |
| `GET /api/devices/{id}/events` | yes | true no-mock HTTP | `tests/Feature/Devices/IngestionDedupTest.php`, `tests/Feature/Devices/EventsListingTest.php` | `EventsListingTest.php:7` |
| `GET /api/devices/{id}/replay/audits` | yes | true no-mock HTTP | `tests/Feature/Devices/ReplayAuditTest.php` | `ReplayAuditTest.php:32` |
| `POST /api/devices/{id}/replay` | yes | true no-mock HTTP | `tests/Feature/Devices/ReplayAuditTest.php`, `tests/Feature/Contracts/ApiContractTest.php` | `ReplayAuditTest.php:9` |

## API Test Classification

### 1) True No-Mock HTTP
Evidence pattern: HTTP calls via `$this->getJson/postJson/...` and no route-path mocking in file.
- Representative files: `tests/Feature/Auth/*.php`, `tests/Feature/Devices/*.php`, `tests/Feature/Playlists/*.php`, `tests/Feature/Search/*.php`, `tests/Feature/Settings/SettingsEndpointTest.php`, `tests/Feature/History/SessionHistoryTest.php`, `tests/Feature/Security/AccountStatusTest.php`, `tests/Feature/Security/CrossUserIsolationTest.php`, `tests/Feature/Security/RateLimitTest.php`, `tests/Feature/Security/UnpublishedAssetAccessTest.php`, `tests/Feature/Media/AssetNoMockHttpCoverageTest.php`.

### 2) HTTP with Mocking
- `tests/Feature/Media/AssetUploadTest.php` (`Storage::fake('local')`, `Storage::fake('public')` at lines 19-20)
- `tests/Feature/Media/AssetDeleteReferencedTest.php` (`Storage::fake('local')` at line 15)
- `tests/Feature/Media/AssetReplaceTest.php` (`Storage::fake('local')`, `Storage::fake('public')` at lines 17-18)
- `tests/Feature/Media/AssetDurationTest.php` (`Storage::fake('local')`, `Storage::fake('public')` at lines 15-16)
- `tests/Feature/Contracts/ApiContractTest.php` (`Storage::fake('local')`, `Storage::fake('public')` at lines 17-18)

### 3) Non-HTTP (unit/integration without HTTP)
- `tests/Feature/Security/SeederNoPasswordLeakTest.php` (direct `DatabaseSeeder()->run()`, no HTTP request).
- `tests/Feature/Monitoring/DegradationFlagTest.php`:
  - `test('MetricsRecorder computes error rate from recorded status codes'...)` (direct service/cache usage)
  - `test('circuit breaker trips when MetricsRecorder p95 exceeds threshold'...)` (direct command invocation)

## Mock Detection
- Mock/provider override in API-adjacent feature tests:
  - `Storage::fake('local'/'public')` in files listed above (media/contract feature tests).
- Unit-test mocks (non-API classification but relevant over-mocking visibility):
  - `tests/Unit/Console/MonitoringSampleCommandTest.php` uses `$this->mock(MetricsRecorder::class)` and `shouldReceive(...)`.
- Frontend unit tests intentionally use `vi.mock(...)` broadly (e.g., `frontend/src/tests/unit/login.view.test.ts`, `asset-tile.component.test.ts`, `player.store.test.ts`).

## Coverage Summary
- Total endpoints: **50**
- Endpoints with HTTP tests: **50**
- Endpoints with true no-mock HTTP tests: **49**
- HTTP coverage: **100.00%**
- True API coverage: **98.00%**

Rationale for 49/50 true no-mock: `GET /api/assets/{id}` evidence currently comes from files that fake storage provider (`Storage::fake(...)`).

## Unit Test Summary

### Backend Unit Tests
- Unit test files present:
  - `tests/Unit/Console/*`, `tests/Unit/Middleware/*`, `tests/Unit/Services/*`, `tests/Unit/Jobs/*`, `tests/Unit/Logging/*`, `tests/Unit/Providers/*`, `tests/Unit/Models/*`, plus `EncryptedFieldCastTest.php`, `MediaProbeTest.php`, `MediaValidatorTest.php`, `Gateway/RetryClassifierTest.php`.
- Modules covered:
  - Controllers: indirectly via feature HTTP tests; no dedicated controller unit tests found.
  - Services: `MediaProbe`, `MediaValidator`, `Monitoring/MetricsRecorder` covered.
  - Repositories: no repository layer detected under `app/`.
  - Auth/guards/middleware: `RoleMiddleware`, `EnforceAccountStatus`, `GatewayTokenMiddleware`, `RecordApiMetrics` covered.
- Important backend modules not unit-tested directly:
  - HTTP controllers under `app/Http/Controllers/*.php` (covered by feature tests but not unit-isolated).
  - `AuthController` edge branches are primarily feature-tested, not unit-tested.

### Frontend Unit Tests (STRICT)
- Frontend test files: **present** under `frontend/src/tests/unit/*.test.ts` (28 files).
- Frameworks/tools detected:
  - `vitest` (`frontend/package.json`, `vitest.config.ts`)
  - `@vue/test-utils` (`frontend/package.json` + imports in test files)
  - `jsdom` test environment (`frontend/vitest.config.ts`)
- Components/modules covered (evidence from imports in test files):
  - Views: `LoginView`, `LibraryView`, `SearchView`, `PlaylistsView`, `PlaylistDetailView`, `FavoritesView`, `NowPlayingView`, `ForbiddenView`, admin views, device views.
  - Components: `AssetTile`, `AddToPlaylistDialog`, `ShareDialog`, `RedeemDialog`.
  - Stores/composables/services/router helpers: `auth`, `player`, `settings`, `ui`, `useUnsavedGuard`, API service layer, router orchestration.
- Important frontend modules not clearly unit-tested directly:
  - Bootstrap shell files `frontend/src/main.ts`, `frontend/src/App.vue` (no direct unit spec found).

**Frontend unit tests: PRESENT**

Cross-layer observation:
- Backend and frontend both have substantial automated tests.
- Balance is acceptable; not backend-only.

## Tests Check
- Observability (endpoint + input + response assertions): **mostly strong**.
  - Strong examples: `Auth/LoginTest.php`, `Playlists/PlaylistCrudTest.php`, `Devices/ReplayAuditTest.php` with body and JSON shape assertions.
  - Weak spots: some tests assert mainly status code in loops, e.g. `Security/AccountStatusTest.php` endpoint arrays at lines ~74 and ~92.
- Sufficiency dimensions:
  - Success paths: covered broadly.
  - Failure/validation: covered broadly (401/403/404/409/422/429 patterns present).
  - Edge cases: present (rate limits, replay/audit, duration filters, ownership/isolation).
  - Auth/permissions: strongly covered.
  - Integration boundaries: medium-strong; some media-path tests rely on `Storage::fake`.
  - Assertions depth: generally meaningful, with a minority of status-only checks.
- `run_tests.sh` check:
  - Docker-based execution confirmed (`docker compose ...` throughout `repo/run_tests.sh`).
  - No host runtime/package install requirement in the script itself.

## End-to-End Expectations (Fullstack)
- Fullstack expectation: FE↔BE E2E should exist.
- Evidence:
  - Playwright specs present: `frontend/e2e/**/*.spec.ts`.
  - Frontend Vite proxy targets backend API (`frontend/vite.config.ts`, `/api` -> `http://localhost:8090` by default).
- Static limitation: execution not performed, so runtime fidelity cannot be confirmed in this audit.

## Test Coverage Score (0-100)
**91/100**

## Score Rationale
- + Full endpoint HTTP coverage (50/50).
- + Broad negative-path and permission testing.
- + Strong frontend unit presence for fullstack requirement.
- - One endpoint (`GET /api/assets/{id}`) lacks true no-mock evidence due storage provider fakes in observed tests.
- - Some weak observability (status-only assertions).

## Key Gaps
1. `GET /api/assets/{id}` true no-mock HTTP evidence is missing (`Storage::fake` used in current covering tests).
2. Several authorization tests rely on status-only assertions instead of response contract validation.
3. Controller-specific unit isolation is limited (coverage is mostly via feature tests).

## Confidence & Assumptions
- Confidence: **high** for route-to-test mapping and README hard-gate checks.
- Assumptions:
  - `Route::apiResource` expands to standard Laravel API methods including both `PUT` and `PATCH` update routes.
  - `/api` prefix is applied via Laravel routing bootstrap (`withRouting(api: ...)`).

**Test Coverage Verdict: PARTIAL PASS** (high coverage with one strict true-no-mock gap).

---

# README Audit

## Target File
- Located at `repo/README.md`: **present**.

## Hard Gate Evaluation

### Formatting
- PASS: clear Markdown hierarchy, tables, command blocks, and structured sections.

### Startup Instructions (fullstack requirement)
- PASS: includes exact `docker-compose up` command under “Quick start — single command”.

### Access Method
- PASS: explicit URL and port provided (`http://localhost:8090`).

### Verification Method
- PASS: includes concrete verification flow:
  - `curl http://localhost:8090/api/health`
  - login curl example
  - SPA navigation steps

### Environment Rules (strict)
- PASS: README states Docker-contained runtime and commands are Docker-based.
- No direct `npm install`, `pip install`, `apt-get`, or manual DB setup instructions found in root README.

### Demo Credentials (auth exists)
- PASS: credentials include username + password + all listed roles:
  - admin / user / technician table present.

## Engineering Quality
- Tech stack clarity: strong.
- Architecture explanation: acceptable (links to `CLAUDE.md` for deeper architecture).
- Testing instructions: strong, includes all suites and selective modes.
- Security/roles explanation: adequate via credentials/role table and auth behavior notes.
- Workflow clarity: strong (quick start, verification, tests, phase gate, directory layout).
- Presentation quality: high.

## High Priority Issues
- None.

## Medium Priority Issues
- `README.md` references expected executable frontend unit tests at `frontend/src/tests/unit/`; this matches current structure, so no active mismatch. No medium-severity compliance issues identified.

## Low Priority Issues
- Root README is comprehensive but long; quickstart-critical sections could be condensed for faster operator onboarding.

## Hard Gate Failures
- None.

## README Verdict
**PASS**

---

## Final Combined Verdicts
- **Test Coverage Audit:** PARTIAL PASS
- **README Audit:** PASS
