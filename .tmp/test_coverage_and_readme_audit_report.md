# Test Coverage Audit

## Scope and Mode
- Audit mode: static inspection only (no code/test execution).
- Sources inspected: `repo/backend/routes/api.php`, `repo/backend/bootstrap/app.php`, `repo/backend/tests/**`, `repo/frontend/src/tests/unit/**`, `repo/frontend/e2e/**`, `repo/run_tests.sh`, `repo/README.md`.
- Project type declaration: `fullstack` (from `repo/README.md:3`).

## Backend Endpoint Inventory
Resolved API prefix from Laravel routing configuration (`repo/backend/bootstrap/app.php:8-10`) and route declarations (`repo/backend/routes/api.php`).

1. `GET /api/health`
2. `POST /api/auth/login`
3. `GET /api/settings`
4. `POST /api/gateway/events`
5. `POST /api/auth/logout`
6. `GET /api/auth/me`
7. `GET /api/search`
8. `GET /api/assets`
9. `GET /api/assets/:id`
10. `POST /api/assets/:id/play`
11. `GET /api/favorites`
12. `PUT /api/favorites/:asset_id`
13. `DELETE /api/favorites/:asset_id`
14. `POST /api/playlists/redeem`
15. `DELETE /api/playlists/shares/:id`
16. `GET /api/playlists`
17. `POST /api/playlists`
18. `GET /api/playlists/:id`
19. `PUT /api/playlists/:id`
20. `PATCH /api/playlists/:id`
21. `DELETE /api/playlists/:id`
22. `POST /api/playlists/:id/share`
23. `POST /api/playlists/:id/items`
24. `DELETE /api/playlists/:id/items/:itemId`
25. `PUT /api/playlists/:id/items/order`
26. `GET /api/history`
27. `GET /api/history/sessions`
28. `GET /api/now-playing`
29. `GET /api/recommendations`
30. `POST /api/assets`
31. `DELETE /api/assets/:id`
32. `POST /api/admin/assets/:id/replace`
33. `GET /api/users`
34. `POST /api/users`
35. `GET /api/users/:id`
36. `PUT /api/users/:id`
37. `PATCH /api/users/:id`
38. `DELETE /api/users/:id`
39. `PATCH /api/users/:id/freeze`
40. `PATCH /api/users/:id/unfreeze`
41. `PATCH /api/users/:id/blacklist`
42. `GET /api/monitoring/status`
43. `POST /api/monitoring/feature-flags/:flag/reset`
44. `PUT /api/settings`
45. `POST /api/devices/events`
46. `GET /api/devices`
47. `GET /api/devices/:id`
48. `GET /api/devices/:id/events`
49. `GET /api/devices/:id/replay/audits`
50. `POST /api/devices/:id/replay`

## API Test Mapping Table
| Endpoint | Covered | Test type | Test files | Evidence |
|---|---|---|---|---|
| `GET /api/health` | yes | true no-mock HTTP | `backend/tests/Feature/HealthTest.php` | `HealthTest.php:4` |
| `POST /api/auth/login` | yes | true no-mock HTTP | `backend/tests/Feature/Auth/LoginTest.php` | `LoginTest.php:16` |
| `GET /api/settings` | yes | true no-mock HTTP | `backend/tests/Feature/Settings/SettingsEndpointTest.php` | `SettingsEndpointTest.php:7` |
| `POST /api/gateway/events` | yes | true no-mock HTTP | `backend/tests/Feature/Devices/GatewayAuthTest.php` | `GatewayAuthTest.php:22` |
| `POST /api/auth/logout` | yes | true no-mock HTTP | `backend/tests/Feature/Auth/LoginTest.php` | `LoginTest.php:100` |
| `GET /api/auth/me` | yes | true no-mock HTTP | `backend/tests/Feature/Auth/LoginTest.php` | `LoginTest.php:86` |
| `GET /api/search` | yes | true no-mock HTTP | `backend/tests/Feature/Search/SearchRankingTest.php` | `SearchRankingTest.php:18` |
| `GET /api/assets` | yes | true no-mock HTTP | `backend/tests/Feature/Security/UnpublishedAssetAccessTest.php` | `UnpublishedAssetAccessTest.php:104` |
| `GET /api/assets/:id` | yes | true no-mock HTTP | `backend/tests/Feature/Media/AssetVisibilityTest.php` | `AssetVisibilityTest.php:12` |
| `POST /api/assets/:id/play` | yes | true no-mock HTTP | `backend/tests/Feature/History/SessionHistoryTest.php` | `SessionHistoryTest.php:13` |
| `GET /api/favorites` | yes | true no-mock HTTP | `backend/tests/Feature/Security/CrossUserIsolationTest.php` | `CrossUserIsolationTest.php:18` |
| `PUT /api/favorites/:asset_id` | yes | true no-mock HTTP | `backend/tests/Feature/Security/UnpublishedAssetAccessTest.php` | `UnpublishedAssetAccessTest.php:19` |
| `DELETE /api/favorites/:asset_id` | yes | true no-mock HTTP | `backend/tests/Feature/Security/CrossUserIsolationTest.php` | `CrossUserIsolationTest.php:78` |
| `POST /api/playlists/redeem` | yes | true no-mock HTTP | `backend/tests/Feature/Playlists/ShareRedeemTest.php` | `ShareRedeemTest.php:60` |
| `DELETE /api/playlists/shares/:id` | yes | true no-mock HTTP | `backend/tests/Feature/Playlists/ShareRedeemTest.php` | `ShareRedeemTest.php:145` |
| `GET /api/playlists` | yes | true no-mock HTTP | `backend/tests/Feature/Playlists/PlaylistCrudTest.php` | `PlaylistCrudTest.php:26` |
| `POST /api/playlists` | yes | true no-mock HTTP | `backend/tests/Feature/Playlists/PlaylistCrudTest.php` | `PlaylistCrudTest.php:12` |
| `GET /api/playlists/:id` | yes | true no-mock HTTP | `backend/tests/Feature/Playlists/PlaylistCrudTest.php` | `PlaylistCrudTest.php:43` |
| `PUT /api/playlists/:id` | yes | true no-mock HTTP | `backend/tests/Feature/Playlists/PlaylistCrudTest.php` | `PlaylistCrudTest.php:55` |
| `PATCH /api/playlists/:id` | yes | true no-mock HTTP | `backend/tests/Feature/Playlists/PlaylistCrudTest.php` | `PlaylistCrudTest.php:65` |
| `DELETE /api/playlists/:id` | yes | true no-mock HTTP | `backend/tests/Feature/Playlists/PlaylistCrudTest.php` | `PlaylistCrudTest.php:75` |
| `POST /api/playlists/:id/share` | yes | true no-mock HTTP | `backend/tests/Feature/Playlists/ShareRedeemTest.php` | `ShareRedeemTest.php:20` |
| `POST /api/playlists/:id/items` | yes | true no-mock HTTP | `backend/tests/Feature/Playlists/PlaylistCrudTest.php` | `PlaylistCrudTest.php:97` |
| `DELETE /api/playlists/:id/items/:itemId` | yes | true no-mock HTTP | `backend/tests/Feature/Playlists/PlaylistCrudTest.php` | `PlaylistCrudTest.php:115` |
| `PUT /api/playlists/:id/items/order` | yes | true no-mock HTTP | `backend/tests/Feature/Playlists/PlaylistCrudTest.php` | `PlaylistCrudTest.php:131` |
| `GET /api/history` | yes | true no-mock HTTP | `backend/tests/Feature/History/SessionHistoryTest.php` | `SessionHistoryTest.php:39` |
| `GET /api/history/sessions` | yes | true no-mock HTTP | `backend/tests/Feature/History/SessionHistoryTest.php` | `SessionHistoryTest.php:79` |
| `GET /api/now-playing` | yes | true no-mock HTTP | `backend/tests/Feature/History/SessionHistoryTest.php` | `SessionHistoryTest.php:146` |
| `GET /api/recommendations` | yes | true no-mock HTTP | `backend/tests/Feature/Search/RecommendationEndpointTest.php` | `RecommendationEndpointTest.php:35` |
| `POST /api/assets` | yes | true no-mock HTTP + HTTP with mocking | `backend/tests/Feature/Media/AssetNoMockHttpCoverageTest.php`, `backend/tests/Feature/Media/AssetUploadTest.php` | `AssetNoMockHttpCoverageTest.php:21`; `AssetUploadTest.php:46` |
| `DELETE /api/assets/:id` | yes | true no-mock HTTP + HTTP with mocking | `backend/tests/Feature/Media/AssetNoMockHttpCoverageTest.php`, `backend/tests/Feature/Media/AssetDeleteReferencedTest.php` | `AssetNoMockHttpCoverageTest.php:46`; `AssetDeleteReferencedTest.php:20` |
| `POST /api/admin/assets/:id/replace` | yes | true no-mock HTTP + HTTP with mocking | `backend/tests/Feature/Media/AssetNoMockHttpCoverageTest.php`, `backend/tests/Feature/Media/AssetReplaceTest.php` | `AssetNoMockHttpCoverageTest.php:62`; `AssetReplaceTest.php:48` |
| `GET /api/users` | yes | true no-mock HTTP | `backend/tests/Feature/Auth/LoginTest.php` | `LoginTest.php:111` |
| `POST /api/users` | yes | true no-mock HTTP | `backend/tests/Feature/Auth/UserManagementTest.php` | `UserManagementTest.php:9` |
| `GET /api/users/:id` | yes | true no-mock HTTP | `backend/tests/Feature/Auth/UserShowUpdateTest.php` | `UserShowUpdateTest.php:10` |
| `PUT /api/users/:id` | yes | true no-mock HTTP | `backend/tests/Feature/Auth/UserShowUpdateTest.php` | `UserShowUpdateTest.php:21` |
| `PATCH /api/users/:id` | yes | true no-mock HTTP | `backend/tests/Feature/Auth/UserShowUpdateTest.php` | `UserShowUpdateTest.php:35` |
| `DELETE /api/users/:id` | yes | true no-mock HTTP | `backend/tests/Feature/Auth/UserManagementTest.php` | `UserManagementTest.php:70` |
| `PATCH /api/users/:id/freeze` | yes | true no-mock HTTP | `backend/tests/Feature/Auth/UserManagementTest.php` | `UserManagementTest.php:25` |
| `PATCH /api/users/:id/unfreeze` | yes | true no-mock HTTP | `backend/tests/Feature/Auth/UserManagementTest.php` | `UserManagementTest.php:44` |
| `PATCH /api/users/:id/blacklist` | yes | true no-mock HTTP | `backend/tests/Feature/Auth/UserManagementTest.php` | `UserManagementTest.php:56` |
| `GET /api/monitoring/status` | yes | true no-mock HTTP | `backend/tests/Feature/Monitoring/DegradationFlagTest.php` | `DegradationFlagTest.php:17` |
| `POST /api/monitoring/feature-flags/:flag/reset` | yes | true no-mock HTTP | `backend/tests/Feature/Monitoring/DegradationFlagTest.php` | `DegradationFlagTest.php:83` |
| `PUT /api/settings` | yes | true no-mock HTTP | `backend/tests/Feature/Settings/SettingsEndpointTest.php` | `SettingsEndpointTest.php:41` |
| `POST /api/devices/events` | yes | true no-mock HTTP | `backend/tests/Feature/Devices/IngestionDedupTest.php` | `IngestionDedupTest.php:33` |
| `GET /api/devices` | yes | true no-mock HTTP | `backend/tests/Feature/Devices/IngestionDedupTest.php` | `IngestionDedupTest.php:113` |
| `GET /api/devices/:id` | yes | true no-mock HTTP | `backend/tests/Feature/Devices/IngestionDedupTest.php` | `IngestionDedupTest.php:160` |
| `GET /api/devices/:id/events` | yes | true no-mock HTTP | `backend/tests/Feature/Devices/EventsListingTest.php` | `EventsListingTest.php:42` |
| `GET /api/devices/:id/replay/audits` | yes | true no-mock HTTP | `backend/tests/Feature/Devices/ReplayAuditTest.php` | `ReplayAuditTest.php:47` |
| `POST /api/devices/:id/replay` | yes | true no-mock HTTP | `backend/tests/Feature/Devices/ReplayAuditTest.php` | `ReplayAuditTest.php:15` |

## API Test Classification
1. True No-Mock HTTP
- Evidence: most feature tests directly call HTTP endpoints with `getJson/postJson/...` and no `Queue::fake`, DI override, or service stubs in the file.
- Representative files: `backend/tests/Feature/Auth/LoginTest.php`, `backend/tests/Feature/Playlists/PlaylistCrudTest.php`, `backend/tests/Feature/Devices/IngestionDedupTest.php`, `backend/tests/Feature/Monitoring/DegradationFlagTest.php`.

2. HTTP with Mocking
- `backend/tests/Feature/Media/AssetUploadTest.php` (`Queue::fake` at line 21).
- `backend/tests/Feature/Media/AssetDurationTest.php` (`Queue::fake` at line 13; `app()->bind(MediaProbe::class, ...)` at line 22).
- `backend/tests/Feature/Media/AssetReplaceTest.php` (`Queue::fake` at line 19; `app()->bind(MediaProbe::class, ...)` at line 31).
- `backend/tests/Feature/Media/AssetDeleteReferencedTest.php` (`Queue::fake` at line 12).
- `backend/tests/Feature/Contracts/ApiContractTest.php` (`Queue::fake` at line 18).

3. Non-HTTP (unit/integration without HTTP)
- Entire `backend/tests/Unit/**` set (14 files), e.g. middleware/service/model/command tests.

## Mock Detection
- `Queue::fake` in feature tests:
  - `backend/tests/Feature/Media/AssetUploadTest.php:21`
  - `backend/tests/Feature/Media/AssetDurationTest.php:13`
  - `backend/tests/Feature/Media/AssetReplaceTest.php:19`
  - `backend/tests/Feature/Media/AssetDeleteReferencedTest.php:12`
  - `backend/tests/Feature/Contracts/ApiContractTest.php:18`
- DI service override/stub in feature tests:
  - `backend/tests/Feature/Media/AssetDurationTest.php:22` (`MediaProbe` binding override)
  - `backend/tests/Feature/Media/AssetReplaceTest.php:31` (`MediaProbe` binding override)
- Unit-level mocking:
  - `backend/tests/Unit/Console/MonitoringSampleCommandTest.php:14-16` (`$this->mock(MetricsRecorder::class)` + `shouldReceive`)

## Coverage Summary
- Total endpoints: `50`
- Endpoints with HTTP tests: `50`
- Endpoints with true no-mock HTTP coverage: `50`
- HTTP coverage: `100%`
- True API coverage: `100%`

## Unit Test Summary
### Backend Unit Tests
- Files: `14` under `backend/tests/Unit/**`.
- Covered backend module types:
  - Middleware: `RoleMiddleware`, `EnforceAccountStatus`, `GatewayTokenMiddleware`, `RecordApiMetrics`
  - Services/Casts/Utility: `MediaValidator`, `MediaProbe`, `MetricsRecorder`, `EncryptedField`
  - Models/Jobs/Logging/Console commands: model relationships, jobs, sensitive-field logging mask, gateway dead-letter/monitoring commands
- Important backend modules not directly unit-tested (feature-tested only or not explicitly isolated):
  - Controllers in `backend/app/Http/Controllers/**` (no direct unit tests)
  - `RecommendationController` and `SearchController` internal ranking logic isolation (validated by feature tests, not isolated unit tests)
  - `App\Providers\AppServiceProvider` boot behavior

### Frontend Unit Tests (STRICT)
- Frontend test files detected: `23` files under `frontend/src/tests/unit/**`.
- Framework/tooling evidence:
  - `vitest` config in `frontend/vitest.config.ts`
  - `@vue/test-utils` mounting actual Vue components (e.g., `login.view.test.ts`, `library.view.test.ts`)
- Components/modules covered (direct imports in tests):
  - Views: `LoginView`, `LibraryView`, `SearchView`, `FavoritesView`, `NowPlayingView`, `PlaylistsView`, `PlaylistDetailView`, `DevicesView`, `DeviceDetailView`, `AdminUsersView`
  - Components: `AssetTile`, `AddToPlaylistDialog`, `ShareDialog`, `RedeemDialog`, `AppLayout`
  - Stores/services/router/composable: `auth`, `player`, `settings`, `ui`, `api` service, router helpers/orchestration, `useUnsavedGuard`
- Important frontend modules not explicitly unit-tested:
  - `frontend/src/views/admin/AdminView.vue`
  - `frontend/src/views/admin/AdminSettingsView.vue`
  - `frontend/src/views/admin/AdminMonitoringView.vue`
  - `frontend/src/views/admin/AdminUploadsView.vue`
  - `frontend/src/views/ForbiddenView.vue`
- Mandatory verdict: **Frontend unit tests: PRESENT**

### Cross-Layer Observation
- Backend and frontend both have substantial automated tests (backend feature+unit, frontend unit+Playwright E2E).
- Testing is not backend-only; coverage is comparatively balanced.

## Tests Check
- Observability quality:
  - Strong in many contract/feature tests with explicit request payload and response shape assertions (e.g., `backend/tests/Feature/Contracts/ApiContractTest.php`, `backend/tests/Feature/Monitoring/DegradationFlagTest.php`).
  - Weak in some authorization/status checks where only status code is asserted, with limited response-body assertion (e.g., `backend/tests/Feature/Auth/LoginTest.php:111`, `backend/tests/Feature/Security/AccountStatusTest.php:54-56`).
- Sufficiency dimensions:
  - Success paths: present across all major domains.
  - Failure/validation: present (422/401/403/404/409/423, rate limit, unknown flag, missing fields).
  - Auth/permissions: present (`role`/account-status scenarios, admin vs user vs technician).
  - Edge cases: present in devices dedup/replay and media validation.
  - Integration boundaries: partially compromised in media endpoints due queue/service fakes in multiple feature suites.
- `run_tests.sh` check:
  - Docker-based execution confirmed (`docker compose` for all suites) from `repo/run_tests.sh`.
  - No local dependency requirement in this script.

## Test Coverage Score (0-100)
`86/100`

## Score Rationale
- `+` Endpoint-to-HTTP mapping is complete (50/50).
- `+` True no-mock HTTP evidence exists for each endpoint.
- `+` Frontend unit tests are present and broad for fullstack requirements.
- `-` Multiple feature suites use `Queue::fake` and DI override on core media flows, reducing execution-path realism.
- `-` Some endpoint tests are assertion-light (status-only), reducing observability and contract confidence.
- `-` Several important frontend admin views remain untested at unit level.

## Key Gaps
1. Media feature suites rely on fakes/stubs (`Queue::fake`, `MediaProbe` DI override), which weakens real-path confidence for asset-processing behavior.
2. Assertion depth is uneven on some auth/permission endpoints (status-only checks).
3. Missing frontend unit tests for key admin views and forbidden state view.

## Confidence & Assumptions
- Confidence: **High** for endpoint inventory and method/path mapping, **Medium-High** for qualitative sufficiency scoring.
- Assumptions:
  - Endpoint inventory scope is API routes in `routes/api.php` with Laravel `/api` prefix.
  - Laravel framework health endpoint `/up` was treated as framework-provided route, not part of app API inventory.

## Test Coverage Verdict
- **PASS (strict static audit), with notable quality gaps on mock reliance and uneven assertion depth.**

---

# README Audit

## High Priority Issues
- None.

## Medium Priority Issues
- README relies on external docs (`CLAUDE.md`) for architecture depth instead of containing a concise in-file architecture section (`repo/README.md:9`).

## Low Priority Issues
- Uses both `docker-compose` and `docker compose` forms; technically valid, but mixed style can cause minor confusion (`repo/README.md:17`, `repo/README.md:21`).

## Hard Gate Failures
- None.

Hard-gate checks (all passed):
- README exists at required path: `repo/README.md`.
- Project type declared near top: `fullstack` (`repo/README.md:3`).
- Startup instruction includes `docker-compose up` (`repo/README.md:17`).
- Access method includes URL + port (`http://localhost:8090`) (`repo/README.md:36`).
- Verification method includes concrete API + UI flow (`curl` login and UI login/browse steps) (`repo/README.md:42-55`).
- Environment rules are Docker-contained; no required host `npm install`/`pip install`/manual DB setup instructions.
- Demo credentials provided with username/password and all roles (`repo/README.md:65-72`).

## Engineering Quality
- Tech stack clarity: strong.
- Architecture explanation: acceptable, but partially delegated to `CLAUDE.md`.
- Testing instructions: strong (`./run_tests.sh` and per-suite dockerized commands).
- Security/roles clarity: present (admin/user/technician credentials and role-aware login verification guidance).
- Workflow clarity: strong quick-start + selective tests + directory map.
- Presentation quality: good markdown structure.

## README Verdict (PASS / PARTIAL PASS / FAIL)
- **PASS**

## README Final Verdict
- **PASS**
