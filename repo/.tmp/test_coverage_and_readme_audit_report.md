# Test Coverage Audit

## Scope, Method, and Project Type
- Method: static inspection only (no test execution, no runtime boot).
- Project type declaration found: `fullstack` in `README.md:3`.
- Endpoint scope audited: API routes declared in `backend/routes/api.php` (resolved with Laravel API prefix `/api`), including nested middleware groups and `Route::apiResource` expansions.

## Backend Endpoint Inventory
Source: `backend/routes/api.php:21-109`.

1. `GET /api/health`
2. `POST /api/auth/login`
3. `POST /api/gateway/events`
4. `POST /api/auth/logout`
5. `GET /api/auth/me`
6. `GET /api/search`
7. `GET /api/assets`
8. `GET /api/assets/{id}`
9. `POST /api/assets/{id}/play`
10. `GET /api/favorites`
11. `PUT /api/favorites/{asset_id}`
12. `DELETE /api/favorites/{asset_id}`
13. `POST /api/playlists/redeem`
14. `DELETE /api/playlists/shares/{id}`
15. `GET /api/playlists`
16. `POST /api/playlists`
17. `GET /api/playlists/{id}`
18. `PUT /api/playlists/{id}`
19. `PATCH /api/playlists/{id}`
20. `DELETE /api/playlists/{id}`
21. `POST /api/playlists/{id}/share`
22. `POST /api/playlists/{id}/items`
23. `DELETE /api/playlists/{id}/items/{itemId}`
24. `PUT /api/playlists/{id}/items/order`
25. `GET /api/history`
26. `GET /api/history/sessions`
27. `GET /api/now-playing`
28. `GET /api/recommendations`
29. `POST /api/assets`
30. `DELETE /api/assets/{id}`
31. `POST /api/admin/assets/{id}/replace`
32. `GET /api/users`
33. `POST /api/users`
34. `GET /api/users/{id}`
35. `PUT /api/users/{id}`
36. `PATCH /api/users/{id}`
37. `DELETE /api/users/{id}`
38. `PATCH /api/users/{id}/freeze`
39. `PATCH /api/users/{id}/unfreeze`
40. `PATCH /api/users/{id}/blacklist`
41. `GET /api/monitoring/status`
42. `POST /api/monitoring/feature-flags/{flag}/reset`
43. `POST /api/devices/events`
44. `GET /api/devices`
45. `GET /api/devices/{id}`
46. `GET /api/devices/{id}/events`
47. `GET /api/devices/{id}/replay/audits`
48. `POST /api/devices/{id}/replay`

## API Test Mapping Table
| Endpoint | Covered | Test Type | Test Files | Evidence |
|---|---|---|---|---|
| `GET /api/health` | Yes | true no-mock HTTP | `backend/tests/Feature/HealthTest.php` | `HealthTest.php:4` |
| `POST /api/auth/login` | Yes | true no-mock HTTP | `backend/tests/Feature/Auth/LoginTest.php` | `LoginTest.php:16` |
| `POST /api/gateway/events` | Yes | true no-mock HTTP | `backend/tests/Feature/Devices/GatewayAuthTest.php` | `GatewayAuthTest.php:22` |
| `POST /api/auth/logout` | Yes | true no-mock HTTP | `backend/tests/Feature/Auth/LoginTest.php` | `LoginTest.php:100` |
| `GET /api/auth/me` | Yes | true no-mock HTTP | `backend/tests/Feature/Auth/LoginTest.php` | `LoginTest.php:86` |
| `GET /api/search` | Yes | true no-mock HTTP | `backend/tests/Feature/Search/SearchRankingTest.php` | `SearchRankingTest.php:18` |
| `GET /api/assets` | Yes | true no-mock HTTP | `backend/tests/Feature/Security/UnpublishedAssetAccessTest.php` | `UnpublishedAssetAccessTest.php:104` |
| `GET /api/assets/{id}` | Yes | true no-mock HTTP | `backend/tests/Feature/Media/AssetVisibilityTest.php` | `AssetVisibilityTest.php:12` |
| `POST /api/assets/{id}/play` | Yes | true no-mock HTTP | `backend/tests/Feature/History/SessionHistoryTest.php` | `SessionHistoryTest.php:13` |
| `GET /api/favorites` | Yes | true no-mock HTTP | `backend/tests/Feature/Security/CrossUserIsolationTest.php` | `CrossUserIsolationTest.php:18` |
| `PUT /api/favorites/{asset_id}` | Yes | true no-mock HTTP | `backend/tests/Feature/Security/UnpublishedAssetAccessTest.php` | `UnpublishedAssetAccessTest.php:19` |
| `DELETE /api/favorites/{asset_id}` | Yes | true no-mock HTTP | `backend/tests/Feature/Security/CrossUserIsolationTest.php` | `CrossUserIsolationTest.php:78` |
| `POST /api/playlists/redeem` | Yes | true no-mock HTTP | `backend/tests/Feature/Playlists/ShareRedeemTest.php` | `ShareRedeemTest.php:60` |
| `DELETE /api/playlists/shares/{id}` | Yes | true no-mock HTTP | `backend/tests/Feature/Playlists/ShareRedeemTest.php` | `ShareRedeemTest.php:145` |
| `GET /api/playlists` | Yes | true no-mock HTTP | `backend/tests/Feature/Playlists/PlaylistCrudTest.php` | `PlaylistCrudTest.php:26` |
| `POST /api/playlists` | Yes | true no-mock HTTP | `backend/tests/Feature/Playlists/PlaylistCrudTest.php` | `PlaylistCrudTest.php:12` |
| `GET /api/playlists/{id}` | Yes | true no-mock HTTP | `backend/tests/Feature/Playlists/PlaylistCrudTest.php` | `PlaylistCrudTest.php:43` |
| `PUT /api/playlists/{id}` | Yes | true no-mock HTTP | `backend/tests/Feature/Playlists/PlaylistCrudTest.php` | `PlaylistCrudTest.php:55` |
| `PATCH /api/playlists/{id}` | Yes | true no-mock HTTP | `backend/tests/Feature/Playlists/PlaylistCrudTest.php` | `PlaylistCrudTest.php:65` |
| `DELETE /api/playlists/{id}` | Yes | true no-mock HTTP | `backend/tests/Feature/Playlists/PlaylistCrudTest.php` | `PlaylistCrudTest.php:75` |
| `POST /api/playlists/{id}/share` | Yes | true no-mock HTTP | `backend/tests/Feature/Playlists/ShareRedeemTest.php` | `ShareRedeemTest.php:20` |
| `POST /api/playlists/{id}/items` | Yes | true no-mock HTTP | `backend/tests/Feature/Playlists/PlaylistCrudTest.php` | `PlaylistCrudTest.php:97` |
| `DELETE /api/playlists/{id}/items/{itemId}` | Yes | true no-mock HTTP | `backend/tests/Feature/Playlists/PlaylistCrudTest.php` | `PlaylistCrudTest.php:115` |
| `PUT /api/playlists/{id}/items/order` | Yes | true no-mock HTTP | `backend/tests/Feature/Playlists/PlaylistCrudTest.php` | `PlaylistCrudTest.php:131` |
| `GET /api/history` | Yes | true no-mock HTTP | `backend/tests/Feature/History/SessionHistoryTest.php` | `SessionHistoryTest.php:39` |
| `GET /api/history/sessions` | Yes | true no-mock HTTP | `backend/tests/Feature/History/SessionHistoryTest.php` | `SessionHistoryTest.php:79` |
| `GET /api/now-playing` | Yes | true no-mock HTTP | `backend/tests/Feature/History/SessionHistoryTest.php` | `SessionHistoryTest.php:146` |
| `GET /api/recommendations` | Yes | true no-mock HTTP | `backend/tests/Feature/Search/RecommendationEndpointTest.php` | `RecommendationEndpointTest.php:35` |
| `POST /api/assets` | Yes | true no-mock HTTP (also mocked variants exist) | `backend/tests/Feature/Media/AssetNoMockHttpCoverageTest.php` | `AssetNoMockHttpCoverageTest.php:21` |
| `DELETE /api/assets/{id}` | Yes | true no-mock HTTP (also mocked variants exist) | `backend/tests/Feature/Media/AssetNoMockHttpCoverageTest.php` | `AssetNoMockHttpCoverageTest.php:46` |
| `POST /api/admin/assets/{id}/replace` | Yes | true no-mock HTTP (also mocked variants exist) | `backend/tests/Feature/Media/AssetNoMockHttpCoverageTest.php` | `AssetNoMockHttpCoverageTest.php:62` |
| `GET /api/users` | Yes | true no-mock HTTP | `backend/tests/Feature/Auth/LoginTest.php` | `LoginTest.php:118` |
| `POST /api/users` | Yes | true no-mock HTTP | `backend/tests/Feature/Auth/UserManagementTest.php` | `UserManagementTest.php:9` |
| `GET /api/users/{id}` | Yes | true no-mock HTTP | `backend/tests/Feature/Auth/UserShowUpdateTest.php` | `UserShowUpdateTest.php:10` |
| `PUT /api/users/{id}` | Yes | true no-mock HTTP | `backend/tests/Feature/Auth/UserShowUpdateTest.php` | `UserShowUpdateTest.php:21` |
| `PATCH /api/users/{id}` | Yes | true no-mock HTTP | `backend/tests/Feature/Auth/UserShowUpdateTest.php` | `UserShowUpdateTest.php:35` |
| `DELETE /api/users/{id}` | Yes | true no-mock HTTP | `backend/tests/Feature/Auth/UserManagementTest.php` | `UserManagementTest.php:70` |
| `PATCH /api/users/{id}/freeze` | Yes | true no-mock HTTP | `backend/tests/Feature/Auth/UserManagementTest.php` | `UserManagementTest.php:25` |
| `PATCH /api/users/{id}/unfreeze` | Yes | true no-mock HTTP | `backend/tests/Feature/Auth/UserManagementTest.php` | `UserManagementTest.php:44` |
| `PATCH /api/users/{id}/blacklist` | Yes | true no-mock HTTP | `backend/tests/Feature/Auth/UserManagementTest.php` | `UserManagementTest.php:56` |
| `GET /api/monitoring/status` | Yes | true no-mock HTTP | `backend/tests/Feature/Monitoring/DegradationFlagTest.php` | `DegradationFlagTest.php:17` |
| `POST /api/monitoring/feature-flags/{flag}/reset` | Yes | true no-mock HTTP | `backend/tests/Feature/Monitoring/DegradationFlagTest.php` | `DegradationFlagTest.php:83` |
| `POST /api/devices/events` | Yes | true no-mock HTTP | `backend/tests/Feature/Devices/IngestionDedupTest.php` | `IngestionDedupTest.php:33` |
| `GET /api/devices` | Yes | true no-mock HTTP | `backend/tests/Feature/Devices/IngestionDedupTest.php` | `IngestionDedupTest.php:113` |
| `GET /api/devices/{id}` | Yes | true no-mock HTTP | `backend/tests/Feature/Devices/IngestionDedupTest.php` | `IngestionDedupTest.php:160` |
| `GET /api/devices/{id}/events` | Yes | true no-mock HTTP | `backend/tests/Feature/Devices/EventsListingTest.php` | `EventsListingTest.php:42` |
| `GET /api/devices/{id}/replay/audits` | Yes | true no-mock HTTP | `backend/tests/Feature/Devices/ReplayAuditTest.php` | `ReplayAuditTest.php:47` |
| `POST /api/devices/{id}/replay` | Yes | true no-mock HTTP | `backend/tests/Feature/Devices/ReplayAuditTest.php` | `ReplayAuditTest.php:15` |

## API Test Classification
1. True No-Mock HTTP
- Primary evidence: Feature tests under `backend/tests/Feature/**` use `getJson/postJson/...` against `/api/...` and bootstrap `Tests\TestCase` with real app routing (`backend/tests/Pest.php:6-8`).
- Additional live FE↔BE API evidence: `frontend/e2e/devices/live-device-events.spec.ts:7`, `:31` (`request.post` to real API URL).

2. HTTP with Mocking
- Backend HTTP tests with fakes/overrides:
- `backend/tests/Feature/Media/AssetUploadTest.php:10-13` (`Storage::fake`, `Queue::fake`)
- `backend/tests/Feature/Media/AssetDurationTest.php:11-13` (`Storage::fake`, `Queue::fake`)
- `backend/tests/Feature/Media/AssetDeleteReferencedTest.php:10-13` (`Storage::fake`, `Queue::fake`)
- `backend/tests/Feature/Media/AssetReplaceTest.php:14-18` (`Storage::fake`, `Queue::fake`)
- `backend/tests/Feature/Media/AssetReplaceTest.php:29-31` (`app()->bind(MediaProbe::class, ...)` DI override)
- `backend/tests/Feature/Contracts/ApiContractTest.php:16-18` (`Storage::fake`, `Queue::fake`)
- Frontend Playwright API interception tests:
- `frontend/e2e/devices/replay-with-audit.spec.ts:1-5`, `:24`, `:34`, `:42`, `:50`, `:95`
- `frontend/e2e/library/recommended-degradation.spec.ts:25`

3. Non-HTTP (unit/integration without HTTP)
- Backend: `backend/tests/Unit/**` (e.g., `MediaValidatorTest.php`, `EncryptedFieldCastTest.php`).
- Frontend: `frontend/src/tests/unit/auth.store.test.ts`, `frontend/src/tests/unit/player.store.test.ts`.

## Mock Detection Findings
- `Storage::fake` and `Queue::fake` used in backend HTTP tests (`AssetUploadTest.php`, `AssetDurationTest.php`, `AssetDeleteReferencedTest.php`, `AssetReplaceTest.php`, `ApiContractTest.php`).
- DI/service override detected: `app()->bind(MediaProbe::class, ...)` in `AssetReplaceTest.php:29-31`.
- Frontend unit tests mock API layer with `vi.mock('@/services/api', ...)` in:
- `frontend/src/tests/unit/auth.store.test.ts:6`
- `frontend/src/tests/unit/player.store.test.ts:6`
- Frontend E2E/API interception with `page.route(...)` in multiple specs (e.g., `replay-with-audit.spec.ts:24+`).

## Coverage Summary
- Total API endpoints: **48**
- Endpoints with HTTP tests: **48**
- Endpoints with true no-mock HTTP evidence: **48**
- HTTP coverage: **100.00%**
- True API coverage: **100.00%**

## Unit Test Summary

### Backend Unit Tests
- Test files:
- `backend/tests/Unit/EncryptedFieldCastTest.php`
- `backend/tests/Unit/MediaValidatorTest.php`
- `backend/tests/Unit/Gateway/RetryClassifierTest.php`
- `backend/tests/Unit/Logging/MaskSensitiveFieldsTest.php`
- Modules covered:
- Repositories/models/casts: `EncryptedField`, `User` encrypted persistence (`EncryptedFieldCastTest.php`)
- Services: `MediaValidator` (`MediaValidatorTest.php`)
- Gateway retry classification logic (`RetryClassifierTest.php`)
- Logging sanitizer: `MaskSensitiveFields` (`MaskSensitiveFieldsTest.php`)
- Important backend modules not unit-tested (by direct unit tests):
- Controllers: `AuthController`, `UserController`, `AssetController`, `PlaylistController`, `DeviceController`, `MonitoringController`, `SearchController`, `FavoriteController`, `PlayHistoryController`, `RecommendationController`
- Middleware: `RoleMiddleware`, `EnforceAccountStatus`, `GatewayTokenMiddleware`, `RecordApiMetrics`
- Services: `Monitoring\MetricsRecorder`, `MediaProbe`

### Frontend Unit Tests (STRICT)
- Frontend test files:
- `frontend/src/tests/unit/auth.store.test.ts`
- `frontend/src/tests/unit/player.store.test.ts`
- Frameworks/tools detected:
- Vitest import evidence: `auth.store.test.ts:1`, `player.store.test.ts:1`
- Pinia store testing: `auth.store.test.ts:2-3`, `player.store.test.ts:2-3`
- Tests import actual frontend modules:
- `@/stores/auth` (`auth.store.test.ts:3`)
- `@/stores/player` (`player.store.test.ts:3`)
- Components/modules covered:
- `src/stores/auth.ts`
- `src/stores/player.ts`
- Important frontend components/modules not unit-tested:
- Views: `src/views/**` (e.g., `SearchView.vue`, `PlaylistsView.vue`, `admin/*`, `devices/*`)
- Components: `AssetTile.vue`, `ShareDialog.vue`, `RedeemDialog.vue`, `AddToPlaylistDialog.vue`
- Router and route guards: `src/router/index.ts` (not directly unit-tested)
- API client: `src/services/api.ts` (mocked in unit tests, not directly tested)
- `src/stores/ui.ts`
- Mandatory verdict: **Frontend unit tests: PRESENT**
- Strict fullstack adequacy verdict: **CRITICAL GAP** (frontend unit tests exist but are narrow/store-only; no component rendering tests).

### Cross-Layer Observation
- Testing is backend-heavy. Backend HTTP coverage is broad; frontend unit coverage is thin and does not cover components/views.

## API Observability Check
- Strong examples (endpoint + input + response assertions clear):
- `backend/tests/Feature/Auth/LoginTest.php:16-23`
- `backend/tests/Feature/Playlists/PlaylistCrudTest.php:12-16`
- `backend/tests/Feature/Monitoring/DegradationFlagTest.php:83-85`
- Weak areas:
- Several tests assert status only or minimal payload shape (e.g., `CrossUserIsolationTest.php:102`, `PlaylistCrudTest.php:75`, parts of Playwright UI-first specs with mocked API routes).

## Test Quality & Sufficiency
- Success/failure paths: broadly covered across auth, playlists, assets, devices, monitoring, security.
- Edge/validation cases: present (rate limiting, invalid MIME, oversize files, role restrictions, account status, dedup/out-of-order/too-old).
- Integration boundaries: mixed quality due use of fakes in media/contract tests; however true no-mock HTTP evidence also exists.
- Assertion depth: mixed; many robust assertions, some shallow status-only assertions remain.
- `run_tests.sh` check:
- Docker-based suites confirmed (`run_tests.sh:101`, `:120`, `:138`, `:150`).
- No required local package-manager installation step in the script.

## End-to-End Expectations (Fullstack)
- Real FE↔BE flow exists at least in one spec: `frontend/e2e/devices/live-device-events.spec.ts:16-53`.
- Large portion of frontend E2E suite relies on API mocking/interception (`page.route`), so full end-to-end backend coverage from browser layer is partial.

## Tests Check
- `api-tests/`, `unit-tests/`, `e2e-tests/` are guides only (non-executable); real tests live under `backend/tests/` and `frontend/src/tests/` / `frontend/e2e/`.
- Evidence: `api-tests/README.md:3-5`, `unit-tests/README.md:3-5`.

## Test Coverage Score (0-100)
**82/100**

## Score Rationale
- + High endpoint-level API coverage with direct HTTP route hits.
- + Strong backend negative-case and security coverage.
- - Heavy mock/fake usage in subsets of HTTP and browser tests.
- - Frontend unit depth is insufficient for fullstack quality bar (critical gap).
- - Some tests are assertion-light.

## Key Gaps
1. **CRITICAL**: Frontend unit suite does not cover UI components/views; it is store-focused only.
2. **HIGH**: Many browser E2E specs mock backend routes, reducing true FE↔BE confidence.
3. **MEDIUM**: Some HTTP tests validate only status with limited response contract assertions.

## Confidence & Assumptions
- Confidence: **High** for route inventory and static mapping from route declarations to test call sites.
- Assumptions:
- Laravel API prefix `/api` applies via `withRouting(api: ...)` and is consistent with test paths.
- Audit scope treats API endpoints in `routes/api.php` as primary coverage target.

---

# README Audit

## README Location
- Found at required location: `README.md`.

## Hard Gates

### Formatting
- PASS: Structured markdown, clear sectioning and commands (`README.md:1-208`).

### Startup Instructions (backend/fullstack)
- PASS: Explicit required command present: `docker-compose up` (`README.md:16-18`).

### Access Method
- PASS: URL and port provided: `http://localhost:8090` (`README.md:31-33`).

### Verification Method
- PASS: API verification with `curl` and UI verification flow provided (`README.md:37-55`).

### Environment Rules (Docker-contained, no runtime install/manual DB setup)
- PASS: README states Docker-contained runtime (`README.md:8`, `:141-142`).
- No required `npm install` / `pip install` / `apt-get` / manual DB setup instructions detected.

### Demo Credentials (auth exists)
- PASS: Credentials include usernames, password, and all roles (`README.md:68-73`).

## Engineering Quality
- Tech stack clarity: strong (`README.md:7-10`).
- Architecture reference: present (`README.md:9-10`).
- Testing instructions: strong and dockerized (`README.md:101-160`).
- Security/roles: demo-role accounts and environment caveats documented (`README.md:57-76`).
- Workflow clarity: strong quick-start and selective test commands.

## High Priority Issues
- None.

## Medium Priority Issues
- `README.md:165` uses `npx vitest` in optional interactive command; this can be interpreted as runtime package resolution behavior if dependencies are missing. Not a hard-gate violation, but stricter wording could avoid ambiguity.

## Low Priority Issues
- Root README references architecture details externally (`CLAUDE.md`, `PLAN.md`) rather than summarizing key runtime architecture inline.

## Hard Gate Failures
- None.

## README Verdict
**PASS**

---

## Final Combined Verdicts
- **Test Coverage Audit Verdict:** PARTIAL PASS (strong backend API coverage, but critical frontend unit-test depth gap).
- **README Audit Verdict:** PASS.
