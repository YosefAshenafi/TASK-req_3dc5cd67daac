# Test Coverage Audit

## Scope & Method
- Audit mode: static inspection only (no code/test/script execution).
- Inspected for this audit: `repo/backend/routes/api.php`, `repo/backend/tests/**`, `repo/frontend/src/tests/unit/**`, `repo/frontend/e2e/**`, `repo/run_tests.sh`, `repo/README.md`.
- Project type: `fullstack` (declared in `repo/README.md:3`).

## Backend Endpoint Inventory
Resolved from `repo/backend/routes/api.php` with `/api` prefix:

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

Route evidence: `repo/backend/routes/api.php:22-114` (`apiResource` expansions included for playlists/users).

## API Test Mapping Table
| Endpoint | Covered | Test type | Test files | Evidence |
|---|---|---|---|---|
| `GET /api/health` | yes | true no-mock HTTP | `backend/tests/Feature/HealthTest.php` | `test('health endpoint returns ok'...)` |
| `POST /api/auth/login` | yes | true no-mock HTTP | `Feature/Auth/LoginTest.php`, `Feature/Security/RateLimitTest.php` | `test('login success returns token and user'...)`, `test('login rate limit applies...'...)` |
| `GET /api/settings` | yes | true no-mock HTTP | `Feature/Settings/SettingsEndpointTest.php` | `test('GET /api/settings returns defaults...'...)` |
| `POST /api/gateway/events` | yes | true no-mock HTTP | `Feature/Devices/GatewayAuthTest.php` | `test('POST /api/gateway/events with correct token returns 201...'...)` |
| `POST /api/auth/logout` | yes | true no-mock HTTP | `Feature/Auth/LoginTest.php`, `Feature/Security/AccountStatusTest.php` | `test('logout returns 204'...)`, `test('frozen user can still log out cleanly'...)` |
| `GET /api/auth/me` | yes | true no-mock HTTP | `Feature/Auth/LoginTest.php`, `Feature/Security/AccountStatusTest.php` | `test('authenticated user can get me'...)` |
| `GET /api/search` | yes | true no-mock HTTP | `Feature/Search/SearchRankingTest.php`, `Feature/Security/AccountStatusTest.php` | `test('search returns results matching query'...)` |
| `GET /api/assets` | yes | true no-mock HTTP | `Feature/Security/UnpublishedAssetAccessTest.php` | `test('non-admin asset list only returns ready assets'...)` |
| `GET /api/assets/:id` | yes | true no-mock HTTP | `Feature/Media/AssetUploadTest.php`, `Feature/Media/AssetVisibilityTest.php` | `test('get asset returns detail'...)`, `test('non-admin user cannot read asset in processing status'...)` |
| `POST /api/assets/:id/play` | yes | true no-mock HTTP | `Feature/History/SessionHistoryTest.php`, `Feature/Contracts/ApiContractTest.php` | `test('POST /assets/{id}/play accepts...'...)` |
| `GET /api/favorites` | yes | true no-mock HTTP | `Feature/Security/CrossUserIsolationTest.php`, `Feature/Security/UnpublishedAssetAccessTest.php` | `test('user A cannot see user B favorites...'...)` |
| `PUT /api/favorites/:asset_id` | yes | true no-mock HTTP | `Feature/Security/UnpublishedAssetAccessTest.php` | `test('regular user cannot favorite an asset whose status is processing'...)` |
| `DELETE /api/favorites/:asset_id` | yes | true no-mock HTTP | `Feature/Security/CrossUserIsolationTest.php` | `test('user can remove a favorite'...)` |
| `POST /api/playlists/redeem` | yes | true no-mock HTTP | `Feature/Playlists/ShareRedeemTest.php` | `test('recipient can redeem share code...'...)` |
| `DELETE /api/playlists/shares/:id` | yes | true no-mock HTTP | `Feature/Playlists/ShareRedeemTest.php`, `Feature/Security/CrossUserIsolationTest.php` | `test('user can revoke a share'...)` |
| `GET /api/playlists` | yes | true no-mock HTTP | `Feature/Playlists/PlaylistCrudTest.php`, `Feature/Contracts/ApiContractTest.php` | `test('user can list their playlists'...)` |
| `POST /api/playlists` | yes | true no-mock HTTP | `Feature/Playlists/PlaylistCrudTest.php` | `test('user can create a playlist'...)` |
| `GET /api/playlists/:id` | yes | true no-mock HTTP | `Feature/Playlists/PlaylistCrudTest.php` | `test('user can get a playlist with items'...)` |
| `PUT /api/playlists/:id` | yes | true no-mock HTTP | `Feature/Playlists/PlaylistCrudTest.php` | `test('user can update a playlist via PUT'...)` |
| `PATCH /api/playlists/:id` | yes | true no-mock HTTP | `Feature/Playlists/PlaylistCrudTest.php` | `test('user can rename a playlist'...)` |
| `DELETE /api/playlists/:id` | yes | true no-mock HTTP | `Feature/Playlists/PlaylistCrudTest.php` | `test('user can delete their playlist'...)` |
| `POST /api/playlists/:id/share` | yes | true no-mock HTTP | `Feature/Playlists/ShareRedeemTest.php`, `Feature/Security/RateLimitTest.php` | `test('user can generate a share code'...)` |
| `POST /api/playlists/:id/items` | yes | true no-mock HTTP | `Feature/Playlists/PlaylistCrudTest.php`, `Feature/Security/UnpublishedAssetAccessTest.php` | `test('POST /playlists/{id}/items adds an item...'...)` |
| `DELETE /api/playlists/:id/items/:itemId` | yes | true no-mock HTTP | `Feature/Playlists/PlaylistCrudTest.php` | `test('DELETE /playlists/{id}/items/{itemId} removes item...'...)` |
| `PUT /api/playlists/:id/items/order` | yes | true no-mock HTTP | `Feature/Playlists/PlaylistCrudTest.php`, `Feature/Contracts/ApiContractTest.php` | `test('PUT /playlists/{id}/items/order reorders...'...)` |
| `GET /api/history` | yes | true no-mock HTTP | `Feature/History/SessionHistoryTest.php`, `Feature/Security/CrossUserIsolationTest.php` | `test('GET /history returns session_id...'...)` |
| `GET /api/history/sessions` | yes | true no-mock HTTP | `Feature/History/SessionHistoryTest.php` | `test('GET /history/sessions groups plays by session_id'...)` |
| `GET /api/now-playing` | yes | true no-mock HTTP | `Feature/History/SessionHistoryTest.php` | `test('GET /now-playing exposes session_id...'...)` |
| `GET /api/recommendations` | yes | true no-mock HTTP | `Feature/Search/RecommendationEndpointTest.php` | `test('GET /recommendations returns ...'...)` |
| `POST /api/assets` | yes | true no-mock HTTP + HTTP with mocking | `Feature/Media/AssetNoMockHttpCoverageTest.php`, `Feature/Media/AssetUploadTest.php`, `Feature/Media/AssetDurationTest.php` | no-mock test name explicitly states "without test doubles"; mock evidence in other files via `Storage::fake`, `Queue::fake` |
| `DELETE /api/assets/:id` | yes | true no-mock HTTP + HTTP with mocking | `Feature/Media/AssetNoMockHttpCoverageTest.php`, `Feature/Media/AssetDeleteReferencedTest.php` | no-mock delete test + `Storage::fake/Queue::fake` in delete file |
| `POST /api/admin/assets/:id/replace` | yes | true no-mock HTTP + HTTP with mocking | `Feature/Media/AssetNoMockHttpCoverageTest.php`, `Feature/Media/AssetReplaceTest.php` | no-mock replace test + fake storage/queue in replace file |
| `GET /api/users` | yes | true no-mock HTTP | `Feature/Auth/LoginTest.php` | `test('regular user cannot access admin endpoints'...)` and `test('admin can access admin endpoints'...)` |
| `POST /api/users` | yes | true no-mock HTTP | `Feature/Auth/UserManagementTest.php` | `test('admin can create a user'...)` |
| `GET /api/users/:id` | yes | true no-mock HTTP | `Feature/Auth/UserShowUpdateTest.php` | `test('admin can get a user by id'...)` |
| `PUT /api/users/:id` | yes | true no-mock HTTP | `Feature/Auth/UserShowUpdateTest.php` | `test('admin can update a user via PUT'...)` |
| `PATCH /api/users/:id` | yes | true no-mock HTTP | `Feature/Auth/UserShowUpdateTest.php` | `test('admin can update a user via PATCH'...)` |
| `DELETE /api/users/:id` | yes | true no-mock HTTP | `Feature/Auth/UserManagementTest.php` | `test('admin can soft-delete a user'...)` |
| `PATCH /api/users/:id/freeze` | yes | true no-mock HTTP | `Feature/Auth/UserManagementTest.php`, `Feature/Contracts/ApiContractTest.php` | `test('admin can freeze a user'...)` |
| `PATCH /api/users/:id/unfreeze` | yes | true no-mock HTTP | `Feature/Auth/UserManagementTest.php`, `Feature/Contracts/ApiContractTest.php` | `test('admin can unfreeze a user'...)` |
| `PATCH /api/users/:id/blacklist` | yes | true no-mock HTTP | `Feature/Auth/UserManagementTest.php`, `Feature/Contracts/ApiContractTest.php` | `test('admin can blacklist a user'...)` |
| `GET /api/monitoring/status` | yes | true no-mock HTTP | `Feature/Monitoring/DegradationFlagTest.php` | `test('monitoring status returns expected structure'...)` |
| `POST /api/monitoring/feature-flags/:flag/reset` | yes | true no-mock HTTP | `Feature/Monitoring/DegradationFlagTest.php` | `test('admin can reset the recommended flag...'...)` |
| `PUT /api/settings` | yes | true no-mock HTTP | `Feature/Settings/SettingsEndpointTest.php` | `test('PUT /api/settings persists partial updates...'...)` |
| `POST /api/devices/events` | yes | true no-mock HTTP | `Feature/Devices/IngestionDedupTest.php`, `Feature/Devices/DedupWindowAndGapTest.php`, `Feature/Devices/AuditPersistenceTest.php` | `test('accepted event returns 201...'...)` |
| `GET /api/devices` | yes | true no-mock HTTP | `Feature/Devices/IngestionDedupTest.php` | `test('device roster is accessible to technician'...)` |
| `GET /api/devices/:id` | yes | true no-mock HTTP | `Feature/Devices/IngestionDedupTest.php` | `test('GET /devices/{id} returns device detail...'...)` |
| `GET /api/devices/:id/events` | yes | true no-mock HTTP | `Feature/Devices/IngestionDedupTest.php`, `Feature/Devices/EventsListingTest.php`, `Feature/Devices/AuditPersistenceTest.php` | `test('device events endpoint filters by status'...)` |
| `GET /api/devices/:id/replay/audits` | yes | true no-mock HTTP | `Feature/Devices/ReplayAuditTest.php` | `test('technician can list replay audits...'...)` |
| `POST /api/devices/:id/replay` | yes | true no-mock HTTP | `Feature/Devices/ReplayAuditTest.php`, `Feature/Contracts/ApiContractTest.php` | `test('replay creates an audit record...'...)` |

## API Test Classification
1. True No-Mock HTTP
- Most backend feature tests in `repo/backend/tests/Feature/**` use Laravel HTTP test client without DI overrides or mocked controllers/services.
- Explicit no-mock evidence: `repo/backend/tests/Feature/Media/AssetNoMockHttpCoverageTest.php` test names declare "through real HTTP stack without test doubles".

2. HTTP with Mocking
- `repo/backend/tests/Feature/Media/AssetUploadTest.php` (`Storage::fake('local')`, `Storage::fake('public')`, `Queue::fake()` at lines 10-12).
- `repo/backend/tests/Feature/Media/AssetReplaceTest.php` (`Storage::fake`, `Queue::fake()` at lines 15-17).
- `repo/backend/tests/Feature/Media/AssetDeleteReferencedTest.php` (`Storage::fake`, `Queue::fake()` at lines 11-12).
- `repo/backend/tests/Feature/Media/AssetDurationTest.php` (`Storage::fake`, `Queue::fake()` at lines 11-13).
- `repo/backend/tests/Feature/Contracts/ApiContractTest.php` (`Storage::fake`, `Queue::fake()` at lines 16-18).

3. Non-HTTP (unit/integration without HTTP)
- Backend unit suite: `repo/backend/tests/Unit/**` (console commands, casts, services, logging, jobs, models, gateway classifier).
- Frontend unit suite: `repo/frontend/src/tests/unit/**` (Vitest + Vue Test Utils + Pinia store/module/component tests).

## Mock Detection
- Backend HTTP-level fakes detected:
  - Filesystem fake: `Storage::fake(...)` in media/contract feature tests above.
  - Queue fake: `Queue::fake()` in media/contract feature tests above.
- Backend unit mocking:
  - Service mock in unit command tests: `repo/backend/tests/Unit/Console/MonitoringSampleCommandTest.php:14-16,36-39,55-57,69-71,89-91` (`$this->mock(MetricsRecorder::class)` + `shouldReceive(...)`).
- Frontend unit mocking:
  - `vi.mock(...)` in `repo/frontend/src/tests/unit/auth.store.test.ts:6`, `player.store.test.ts:6`, `settings.store.test.ts:5`, `use-unsaved-guard.composable.test.ts:8`, `asset-tile.component.test.ts:15,22,30`.

## Coverage Summary
- Total backend API endpoints: **50**.
- Endpoints with HTTP tests: **50**.
- Endpoints with at least one true no-mock HTTP test: **50**.
- HTTP coverage: **100%**.
- True API coverage: **100%**.

Calculation basis: endpoint inventory from `repo/backend/routes/api.php`; request evidence from `repo/backend/tests/Feature/**` via explicit `getJson/postJson/putJson/patchJson/deleteJson` calls.

## Unit Test Summary
### Backend Unit Tests
- Test files: `repo/backend/tests/Unit/**` (8 top-level unit files + subdirs Console/Gateway/Jobs/Logging/Models/Services).
- Modules covered:
  - Services: `MediaValidator`, `MetricsRecorder`.
  - Jobs: `PurgeSoftDeletedUsers`, `GenerateRecommendationCandidates`, `IndexAsset`, `MediaScanRequested`, `GenerateThumbnails`, `ReconcileDeviceEvents`.
  - Logging: `MaskSensitiveFields`.
  - Casts/models: `EncryptedField`, model relations.
  - Console/gateway behavior: monitoring sample command, dead-letter command, retry classifier.
- Newly added backend unit tests for prior gaps:
  - Middleware: `repo/backend/tests/Unit/Middleware/RoleMiddlewareTest.php`, `EnforceAccountStatusMiddlewareTest.php`, `GatewayTokenMiddlewareTest.php`, `RecordApiMetricsMiddlewareTest.php`.
  - Service: `repo/backend/tests/Unit/MediaProbeTest.php`.
- Important backend modules still not unit-tested (file-level evidence by absence in `tests/Unit`):
  - Controllers in `repo/backend/app/Http/Controllers/**` (covered primarily via feature HTTP tests, not unit tests).
  - Role and status behavior is unit-tested at middleware class level, but controller-level branch permutations remain primarily feature-tested.

### Frontend Unit Tests (STRICT REQUIREMENT)
- Frontend test files detected:
  - `repo/frontend/src/tests/unit/ui.store.test.ts`
  - `repo/frontend/src/tests/unit/auth.store.test.ts`
  - `repo/frontend/src/tests/unit/player.store.test.ts`
  - `repo/frontend/src/tests/unit/use-unsaved-guard.composable.test.ts`
  - `repo/frontend/src/tests/unit/router.helpers.test.ts`
  - `repo/frontend/src/tests/unit/settings.store.test.ts`
  - `repo/frontend/src/tests/unit/api.service.test.ts`
  - `repo/frontend/src/tests/unit/asset-tile.component.test.ts`
  - `repo/frontend/src/tests/unit/app-layout.component.test.ts`
- Frameworks/tools detected:
  - Vitest imports in unit files (`import ... from 'vitest'` across unit suite).
  - Vue Test Utils component rendering (`mount` in `asset-tile.component.test.ts:1,62` and composable mount in `use-unsaved-guard.composable.test.ts`).
  - Pinia store testing (`createPinia`, `setActivePinia` in store tests).
- Components/modules covered:
  - Component: `@/components/AssetTile.vue` (`asset-tile.component.test.ts:3`).
  - Layout: `@/layouts/AppLayout.vue` (`app-layout.component.test.ts:5`) including role nav rendering, logout redirect, notification dismissal.
  - Stores: `@/stores/auth`, `@/stores/player`, `@/stores/settings`, `@/stores/ui`.
  - Composable: `@/composables/useUnsavedGuard`.
  - Router helper: `@/router/roleHome`.
  - API service module: `@/services/api`.
- Important frontend components/modules NOT unit-tested:
  - Major views under `repo/frontend/src/views/**` (e.g., `LoginView.vue`, `LibraryView.vue`, `PlaylistsView.vue`, admin/device views).
  - Dialog-heavy UI (`src/components/ShareDialog.vue`, `RedeemDialog.vue`, `AddToPlaylistDialog.vue`) still lacks direct unit test files.

**Mandatory Verdict:** **Frontend unit tests: PRESENT**.

### Cross-Layer Observation
- Backend has broader API/feature surface coverage than frontend unit component/view coverage.
- Frontend is not untested (unit + E2E exist), but unit depth is concentrated in stores/composables with limited direct view-level coverage.

## API Observability Check
- Strong observability in most API tests:
  - Explicit method+path and payloads (`postJson('/api/...', [...])` etc.)
  - Explicit status/body assertions in many tests (e.g., monitoring/search/contracts/session tests).
- Weak spots:
  - Some authorization tests assert mostly status-only outcomes (e.g., role/forbidden checks in `Feature/Auth/LoginTest.php`, `Feature/Auth/UserShowUpdateTest.php`, parts of `Feature/Security/CrossUserIsolationTest.php`) with less response-structure verification.

## Tests Check
- Success paths: broadly covered across auth, media, playlists, devices, search, monitoring.
- Failure/negative paths: present (invalid MIME/size, authz failures, rate limits, unknown flags/codes, duplicate/out-of-order/too-old events).
- Edge cases: present (cursor pagination, dedup windows, session buckets, degraded recommendation mode).
- Validation/auth/permissions: substantial coverage across feature tests.
- Integration boundaries: present (device ingestion + replay audit + monitoring metrics) but some media tests use faked storage/queue.
- Assertion depth: generally meaningful; a minority of status-only checks remain.
- `run_tests.sh` check: Docker-based execution confirmed (`docker compose ...` commands; no required host package installs) in `repo/run_tests.sh` header and commands.

## Test Coverage Score (0-100)
**91 / 100**

## Score Rationale
- + High endpoint coverage (50/50) with explicit HTTP-path evidence.
- + True no-mock HTTP evidence exists for all endpoint groups.
- + Strong negative-path and permissions testing.
- + Added dedicated backend unit tests for middleware and `MediaProbe`.
- + Added frontend layout-level unit test coverage (`AppLayout.vue`) to reduce backend-heavy imbalance.
- - Presence of HTTP tests with mocked storage/queue for media/contract subsets.
- - Frontend unit suite is still thin across major route views and playlist/share dialogs.

## Key Gaps
1. Media feature tests rely on `Storage::fake`/`Queue::fake` in several files; keep these, but add additional non-faked assertions for critical media execution paths beyond the dedicated no-mock file.
2. Expand frontend unit tests to high-value route views/dialog flows (auth/login UX states, playlist share/redeem UI states, admin screens).

## Confidence & Assumptions
- Confidence: **high** for endpoint inventory and route-to-test mapping.
- Assumptions:
  - Laravel API prefix `/api` is active (consistent with test URLs and Laravel conventions).
  - `apiResource` expanded to REST methods (`index/store/show/update/destroy`) and update supports both PUT and PATCH.
  - Local backend test execution was not fully re-verified in this shell because project Pest bootstrap enforces DB migration and local MySQL host was unavailable (`php_network_getaddresses ... host mysql`); frontend new unit file executed successfully with Vitest.

## Test Coverage Verdict
**PASS WITH GAPS** (coverage breadth is strong; quality gaps remain around mocked HTTP subsets and frontend view-level unit depth).

---

# README Audit

## README Location
- Required file `repo/README.md`: **present**.

## Hard Gate Evaluation
1. Formatting/readability: **PASS**.
- Evidence: structured markdown sections and tables (`repo/README.md:1-208`).

2. Startup instruction (fullstack must include `docker-compose up`): **PASS**.
- Evidence: `repo/README.md:16-18` includes exact `docker-compose up`.

3. Access method (URL + port required for backend/web): **PASS**.
- Evidence: `http://localhost:8090` at `repo/README.md:31-33` and usage notes at `:52-54`.

4. Verification method: **PASS**.
- Evidence: API verification via `curl` health/login and expected outputs (`repo/README.md:41-55`), plus SPA verification flow.

5. Environment rules (no runtime host installs/manual DB setup): **PASS**.
- Evidence: explicit Docker-contained workflow (`repo/README.md:8`, `:109`, `:141-160`); no `npm install`, `pip install`, `apt-get`, or manual DB bootstrap instructions found in README.

6. Demo credentials with auth and all roles: **PASS**.
- Auth exists (routes include login/logout/me and role-protected endpoints in `repo/backend/routes/api.php:23,41,47,84,107`).
- Credentials provided for Admin, Regular user, Technician in table (`repo/README.md:68-73`) with password declaration.

## Engineering Quality
- Tech stack clarity: strong (`repo/README.md:7-10`).
- Architecture reference: present via `CLAUDE.md` link (`repo/README.md:9`).
- Testing instructions: strong (`repo/README.md:101-166`).
- Security/roles clarity: good (credentials + role table + auth workflow notes).
- Workflows/presentation: strong and actionable.

## High Priority Issues
- None.

## Medium Priority Issues
- README includes host DB credentials for local access (`repo/README.md:78-87`); acceptable for local/dev, but could be misapplied if copied to non-local environments without stronger warning language.

## Low Priority Issues
- Quick start uses `docker-compose up`, while most later commands use `docker compose ...`; both valid, but style inconsistency may confuse inexperienced users.

## Hard Gate Failures
- None.

## README Verdict (PASS / PARTIAL PASS / FAIL)
**PASS**

## README Final Verdict
**PASS**
