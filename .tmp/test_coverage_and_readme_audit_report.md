# Test Coverage Audit

## Scope and Method
- Static inspection only (no execution).
- Audit scope limited to: `repo/backend/routes/api.php`, `repo/backend/bootstrap/app.php`, `repo/backend/tests/**`, `repo/frontend/tests/**`, `repo/tests/e2e/tests/**`, `repo/run_tests.sh`, `repo/README.md`.
- Project type declaration found in README top section: **Fullstack Web Application** (`repo/README.md`).

## Backend Endpoint Inventory
Resolved from `repo/backend/bootstrap/app.php` (`apiPrefix: 'api'`) and `repo/backend/routes/api.php`.

1. `GET /api/health`
2. `POST /api/auth/login`
3. `POST /api/auth/logout`
4. `GET /api/auth/me`
5. `GET /api/assets`
6. `POST /api/assets`
7. `GET /api/assets/{asset}`
8. `DELETE /api/assets/{asset}`
9. `GET /api/favorites`
10. `POST /api/favorites`
11. `DELETE /api/favorites/{favorite}`
12. `GET /api/playlists`
13. `POST /api/playlists`
14. `POST /api/playlists/redeem`
15. `GET /api/playlists/{playlist}`
16. `PATCH /api/playlists/{playlist}`
17. `DELETE /api/playlists/{playlist}`
18. `POST /api/playlists/{playlist}/items`
19. `DELETE /api/playlists/{playlist}/items/{item}`
20. `GET /api/play-history`
21. `POST /api/play-history`
22. `GET /api/recommendations`
23. `GET /api/admin/users`
24. `PATCH /api/admin/users/{user}/freeze`
25. `PATCH /api/admin/users/{user}/blacklist`
26. `DELETE /api/admin/users/{user}`
27. `GET /api/admin/assets`
28. `PATCH /api/admin/assets/{asset}/approve`
29. `PATCH /api/admin/assets/{asset}/reject`
30. `GET /api/admin/dashboard`
31. `GET /api/admin/monitoring`
32. `POST /api/admin/device-replays`
33. `GET /api/technician/devices`
34. `GET /api/technician/events`
35. `POST /api/device/events`

## API Test Mapping Table
| Endpoint | Covered | Test type | Test files | Evidence |
|---|---|---|---|---|
| `GET /api/health` | yes | true no-mock HTTP | `repo/backend/tests/Feature/HealthTest.php` | `test_health_endpoint_returns_ok` |
| `POST /api/auth/login` | yes | true no-mock HTTP | `repo/backend/tests/Feature/AuthTest.php` | `test_login_with_valid_credentials` |
| `POST /api/auth/logout` | yes | true no-mock HTTP | `repo/backend/tests/Feature/AuthTest.php` | `test_logout_invalidates_session` |
| `GET /api/auth/me` | yes | true no-mock HTTP | `repo/backend/tests/Feature/AuthTest.php` | `test_me_returns_authenticated_user` |
| `GET /api/assets` | yes | true no-mock HTTP | `repo/backend/tests/Feature/RecommendationTest.php`, `repo/backend/tests/Feature/AssetNoMockHttpTest.php` | `test_tag_filter_api_returns_matching_assets`; `test_owner_delete_removes_asset_from_list` |
| `POST /api/assets` | yes | true no-mock HTTP | `repo/backend/tests/Feature/AssetNoMockHttpTest.php` | `test_upload_mp3_creates_db_record_and_returns_201` |
| `GET /api/assets/{asset}` | yes | true no-mock HTTP | `repo/backend/tests/Feature/AssetVisibilityTest.php` | `test_pending_asset_visible_to_owner` |
| `DELETE /api/assets/{asset}` | yes | true no-mock HTTP | `repo/backend/tests/Feature/AssetNoMockHttpTest.php` | `test_owner_can_delete_own_asset` |
| `GET /api/favorites` | yes | true no-mock HTTP | `repo/backend/tests/Feature/FavoriteTest.php` | `test_favorites_list_returns_only_owner_favorites` |
| `POST /api/favorites` | yes | true no-mock HTTP | `repo/backend/tests/Feature/FavoriteTest.php` | `test_add_favorite_success_returns_201` |
| `DELETE /api/favorites/{favorite}` | yes | true no-mock HTTP | `repo/backend/tests/Feature/FavoriteTest.php` | `test_delete_favorite_owner_succeeds` |
| `GET /api/playlists` | yes | true no-mock HTTP | `repo/backend/tests/Feature/PlaylistShareCodeTest.php` | `test_share_code_present_in_playlist_index` |
| `POST /api/playlists` | yes | true no-mock HTTP | `repo/backend/tests/Feature/PlaylistTest.php` | `test_create_playlist` |
| `POST /api/playlists/redeem` | yes | true no-mock HTTP | `repo/backend/tests/Feature/PlaylistShareCodeTest.php` | `test_redeem_valid_share_code_returns_playlist` |
| `GET /api/playlists/{playlist}` | yes | true no-mock HTTP | `repo/backend/tests/Feature/PlaylistTest.php` | `test_show_playlist_forbidden_for_other_user` |
| `PATCH /api/playlists/{playlist}` | yes | true no-mock HTTP | `repo/backend/tests/Feature/PlaylistTest.php` | `test_update_playlist_success` |
| `DELETE /api/playlists/{playlist}` | yes | true no-mock HTTP | `repo/backend/tests/Feature/PlaylistTest.php` | `test_delete_playlist_forbidden_for_other_user` |
| `POST /api/playlists/{playlist}/items` | yes | true no-mock HTTP | `repo/backend/tests/Feature/PlaylistTest.php` | `test_add_item_to_playlist` |
| `DELETE /api/playlists/{playlist}/items/{item}` | yes | true no-mock HTTP | `repo/backend/tests/Feature/PlaylistTest.php` | `test_remove_item_from_playlist_success` |
| `GET /api/play-history` | yes | true no-mock HTTP | `repo/backend/tests/Feature/PlayHistoryNoMockHttpTest.php` | `test_list_returns_only_authenticated_users_entries` |
| `POST /api/play-history` | yes | true no-mock HTTP | `repo/backend/tests/Feature/PlayHistoryNoMockHttpTest.php` | `test_store_creates_db_entry_and_returns_201` |
| `GET /api/recommendations` | yes | true no-mock HTTP | `repo/backend/tests/Feature/RecommendationTest.php` | `test_recommendations_return_expected_structure` |
| `GET /api/admin/users` | yes | true no-mock HTTP | `repo/backend/tests/Feature/AdminTest.php` | `test_admin_users_list_accessible_by_admin` |
| `PATCH /api/admin/users/{user}/freeze` | yes | true no-mock HTTP | `repo/backend/tests/Feature/AdminTest.php` | `test_admin_freeze_user` |
| `PATCH /api/admin/users/{user}/blacklist` | yes | true no-mock HTTP | `repo/backend/tests/Feature/AdminTest.php` | `test_admin_blacklist_user` |
| `DELETE /api/admin/users/{user}` | yes | true no-mock HTTP | `repo/backend/tests/Feature/AdminTest.php` | `test_admin_delete_user_soft_deletes` |
| `GET /api/admin/assets` | yes | true no-mock HTTP | `repo/backend/tests/Feature/AdminAssetTest.php` | `test_admin_asset_list_returns_200_for_admin` |
| `PATCH /api/admin/assets/{asset}/approve` | yes | true no-mock HTTP | `repo/backend/tests/Feature/AdminAssetTest.php` | `test_admin_approve_sets_status_to_approved` |
| `PATCH /api/admin/assets/{asset}/reject` | yes | true no-mock HTTP | `repo/backend/tests/Feature/AdminAssetTest.php` | `test_admin_reject_sets_status_to_rejected` |
| `GET /api/admin/dashboard` | yes | true no-mock HTTP | `repo/backend/tests/Feature/AdminTest.php` | `test_admin_dashboard_accessible_by_admin` |
| `GET /api/admin/monitoring` | yes | true no-mock HTTP | `repo/backend/tests/Feature/MonitoringTest.php` | `test_monitoring_returns_full_structure` |
| `POST /api/admin/device-replays` | yes | true no-mock HTTP | `repo/backend/tests/Feature/DeviceEventTest.php` | `test_admin_can_create_replay_audit` |
| `GET /api/technician/devices` | yes | true no-mock HTTP | `repo/backend/tests/Feature/TechnicianDevicesTest.php` | `test_devices_returns_200_for_technician` |
| `GET /api/technician/events` | yes | true no-mock HTTP | `repo/backend/tests/Feature/TechnicianDevicesTest.php` | `test_events_returns_200_for_technician` |
| `POST /api/device/events` | yes | true no-mock HTTP | `repo/backend/tests/Feature/DeviceEventTest.php` | `test_ingest_creates_event_with_received_status` |

## API Test Classification
1. True No-Mock HTTP
- `repo/backend/tests/Feature/HealthTest.php`
- `repo/backend/tests/Feature/AuthTest.php`
- `repo/backend/tests/Feature/FavoriteTest.php`
- `repo/backend/tests/Feature/PlaylistTest.php`
- `repo/backend/tests/Feature/PlaylistShareCodeTest.php`
- `repo/backend/tests/Feature/RecommendationTest.php`
- `repo/backend/tests/Feature/AdminTest.php`
- `repo/backend/tests/Feature/AdminAssetTest.php`
- `repo/backend/tests/Feature/MonitoringTest.php`
- `repo/backend/tests/Feature/TechnicianDevicesTest.php`
- `repo/backend/tests/Feature/DeviceEventTest.php`
- `repo/backend/tests/Feature/AssetVisibilityTest.php`
- `repo/backend/tests/Feature/AssetNoMockHttpTest.php`
- `repo/backend/tests/Feature/PlayHistoryNoMockHttpTest.php`

2. HTTP with Mocking
- `repo/backend/tests/Feature/AssetTest.php` (uses `Storage::fake('local')`, `Queue::fake()` in `setUp()`)
- `repo/backend/tests/Feature/PlayHistoryTest.php` (uses `Queue::fake()` in `setUp()`)

3. Non-HTTP (unit/integration without HTTP)
- Backend unit: `repo/backend/tests/Unit/*.php`
- Frontend unit: `repo/frontend/tests/*.test.js`
- E2E browser tests: `repo/tests/e2e/tests/*.spec.js`

## Mock Detection
- Backend HTTP with mocking:
  - `Storage::fake('local')` at `repo/backend/tests/Feature/AssetTest.php:31`.
  - `Queue::fake()` at `repo/backend/tests/Feature/AssetTest.php:32`.
  - `Queue::fake()` at `repo/backend/tests/Feature/PlayHistoryTest.php:27`.
- Frontend unit mocking (expected for unit isolation):
  - `vi.mock('@/stores/auth', ...)` at `repo/frontend/tests/router.test.js:8`.
  - `vi.mock('@/api/axios', ...)` in multiple files (e.g., `repo/frontend/tests/LoginView.test.js:6`, `repo/frontend/tests/searchStore.test.js:6`).
  - Component-level mocks in views tests (e.g., `repo/frontend/tests/PlaylistsView.test.js:17`, `:21`).

## Coverage Summary
- Total endpoints: **35**
- Endpoints with HTTP tests: **35**
- Endpoints with TRUE no-mock HTTP tests: **35**
- HTTP coverage: **100.00%**
- True API coverage: **100.00%**

## Unit Test Summary
### Backend Unit Tests
- Files:
  - `repo/backend/tests/Unit/DeviceApiKeyAuthTest.php`
  - `repo/backend/tests/Unit/StructuredLoggerTest.php`
  - `repo/backend/tests/Unit/RequireRoleMiddlewareTest.php`
  - `repo/backend/tests/Unit/ScanHookServiceTest.php`
  - `repo/backend/tests/Unit/MimeValidationServiceTest.php`
  - `repo/backend/tests/Unit/DegradationServiceTest.php`
- Modules covered:
  - Auth/guards/middleware: `DeviceApiKeyAuth`, `RequireRole`, `StructuredLogger`
  - Services: `ScanHookService`, `MimeValidationService`, `DegradationService`
- Important backend modules not directly unit-tested:
  - Controllers under `repo/backend/app/Http/Controllers/**` (primarily feature/API-tested)
  - Jobs under `repo/backend/app/Jobs/**` (behavior covered indirectly)
  - No explicit repository abstraction layer detected.

### Frontend Unit Tests (STRICT REQUIREMENT)
- Frontend unit test files present:
  - Existing: `repo/frontend/tests/LoginView.test.js`, `AssetCard.test.js`, `HistoryView.test.js`, `FavoritesView.test.js`, `PlaylistsView.test.js`, `PlaylistDetailView.test.js`, `NowPlayingPanel.test.js`, `AdminDashboardView.test.js`, `AdminAssetsView.test.js`, `AdminMonitoringView.test.js`, `AdminUsersView.test.js`, `LibraryViewTags.test.js`, `TechnicianConsoleView.test.js`
  - Newly added direct-module tests: `repo/frontend/tests/searchStore.test.js`, `router.test.js`, `StatCard.test.js`, `CreatePlaylistModal.test.js`, `RedeemCodeModal.test.js`
- Framework/tool evidence:
  - Vitest imports (`describe`, `it`, `expect`, `vi`) across frontend tests.
  - Vue Test Utils `mount(...)` usage in component tests.
- Components/modules covered:
  - Views: login/library/favorites/history/playlists/admin/technician view suite.
  - Components: `AssetCard`, `NowPlayingPanel`, `StatCard`, `CreatePlaylistModal`, `RedeemCodeModal`.
  - Core modules: `router/index.js`, `stores/search.js`.
- Important frontend components/modules not tested (direct evidence absent):
  - `repo/frontend/src/stores/nowPlaying.js`
  - `repo/frontend/src/stores/auth.js`
  - `repo/frontend/src/stores/playlist.js`
  - `repo/frontend/src/App.vue`
- **Frontend unit tests: PRESENT**

### Cross-Layer Observation
- Backend API tests, frontend unit tests, and Playwright E2E tests are all present.
- Testing is balanced across layers; frontend is no longer a weakly-covered side.

## API Observability Check
- Strong observability in most backend API tests: explicit method/path, payload/query, and status/content assertions.
- Remaining weak spots:
  - Some authorization-path checks are still status-only in feature files (e.g., `repo/backend/tests/Feature/AdminAssetTest.php` unauthorized/forbidden tests).
  - E2E tests focus on UI behavior and do not expose raw API response contracts (acceptable for E2E type, but weaker for API observability).

## Tests Check
- Success paths: broadly covered across auth, assets, playlists, favorites, admin, technician, device ingestion, recommendations.
- Failure paths and validation: broadly covered with 401/403/404/409/422 branches.
- Edge cases: present (share code collisions, replay dedupe, monitoring/degradation assertions).
- Integration boundaries:
  - True no-mock HTTP coverage now exists for all endpoints.
  - Mocked HTTP suites still exist in parallel (`AssetTest`, `PlayHistoryTest`) but do not negate no-mock endpoint evidence.
- `run_tests.sh`:
  - Docker-based orchestration (`docker compose`, `docker run`) is present, satisfying containerized execution intent.
  - No host-level package manager commands required in script.

## Test Coverage Score (0-100)
**93/100**

## Score Rationale
- + 35/35 endpoints have HTTP tests.
- + 35/35 endpoints have explicit true no-mock HTTP coverage.
- + Strong backend unit and frontend unit breadth with direct module/component tests.
- + E2E suite exists for FE↔BE flows.
- - Some tests still rely on status-only assertions in specific branches, reducing assertion depth in parts of suite.
- - Mock-heavy duplicate HTTP suites remain and can mask confidence if treated alone.

## Key Gaps
1. Strengthen status-only API assertions in selected authorization/error tests (`repo/backend/tests/Feature/AdminAssetTest.php`, portions of `AdminTest.php`).
2. Add direct unit tests for remaining core frontend stores (`auth`, `playlist`, `nowPlaying`) to reduce indirect-only coverage.

## Confidence & Assumptions
- Confidence: **High** for endpoint inventory and endpoint-to-test mapping.
- Confidence: **Medium-High** for execution-path no-mock classification based on static code evidence.
- Assumption: API routes are exclusively declared in `repo/backend/routes/api.php` as wired by `repo/backend/bootstrap/app.php`.

## Test Coverage Verdict
**PASS**

---

# README Audit

## README Location
- Found at required path: `repo/README.md`.

## High Priority Issues
1. README includes manual DB setup steps (`docker compose exec backend php artisan db:seed --force`) as required startup/use step (`Seed demo accounts (required on first run)` and verification precondition). This violates strict “no manual DB setup” rule.

## Medium Priority Issues
1. None with hard operational impact beyond strict gate failure.

## Low Priority Issues
1. Mixed `docker-compose` and `docker compose` command style; functionally valid but less consistent.

## Hard Gate Failures
1. **Environment Rules (STRICT): FAIL**
- Manual DB setup is explicitly required in multiple sections of `repo/README.md`:
  - “Seed demo accounts (required on first run)”
  - “After starting the application and seeding ... verify key flows”
  - “Run ... db:seed --force to create the following accounts”

## README Verdict (PASS / PARTIAL PASS / FAIL)
**FAIL**

## Engineering Quality
- Tech stack clarity: strong.
- Architecture explanation: adequate and readable.
- Testing instructions: present and structured.
- Security/roles and credentials: clearly documented.
- Workflow guidance: clear sequence.
- Presentation quality: clean markdown, good sectional organization.

## README Audit Verdict
**FAIL**
