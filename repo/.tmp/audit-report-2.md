# SmartPark Delivery Acceptance & Project Architecture Audit (Static-Only)

## 1. Verdict
- Overall conclusion: **Partial Pass**

## 2. Scope and Static Verification Boundary
- What was reviewed:
  - Repository docs/config: `README.md`, `backend/README.md`, `frontend/README.md`, `CLAUDE.md`, compose/docker files, env examples.
  - Backend architecture/code: routes, middleware, controllers, models, migrations, services, jobs, console commands, logging config.
  - Frontend architecture/code: router, stores, API client, key views/components for Search/Now Playing/Playlists/Admin/Devices.
  - Test assets: backend Pest unit/feature tests, frontend Vitest + Playwright specs/config.
- What was not reviewed:
  - Runtime behavior under real network latency, DB load, queue throughput, browser rendering behavior, container health.
  - External integration behavior beyond static code paths.
- What was intentionally not executed:
  - No project startup, no Docker runtime, no tests, no API calls, no browser automation.
- Claims requiring manual verification:
  - Real p95 latency behavior and auto-degradation timing under load.
  - Real gateway retransmission/backoff behavior over prolonged outages.
  - Actual kiosk/desktop UX quality and responsiveness in deployed environment.

## 3. Repository / Requirement Mapping Summary
- Prompt core goal mapped: local-first SmartPark media operations with role-based UI + Laravel APIs + MySQL, plus offline-first device ingestion and admin/device operations.
- Core flows mapped:
  - Username/password auth and role routing (`backend/app/Http/Controllers/Auth/AuthController.php:19`, `frontend/src/router/index.ts:109`).
  - Library/search/favorites/playlists/share-code/redeem/now-playing (`backend/routes/api.php:48`, `frontend/src/views/SearchView.vue:12`, `frontend/src/components/ShareDialog.vue:77`, `frontend/src/components/RedeemDialog.vue:36`).
  - Admin account controls/monitoring/upload review (`backend/routes/api.php:82`, `frontend/src/views/admin/AdminUsersView.vue:73`, `frontend/src/views/admin/AdminMonitoringView.vue:83`, `frontend/src/views/admin/AdminUploadsView.vue:24`).
  - Device ingestion/dedup/out-of-order/replay/audit/gateway buffering (`backend/app/Http/Controllers/DeviceController.php:28`, `backend/app/Console/Commands/GatewayRun.php:135`, `frontend/src/views/devices/DeviceDetailView.vue:183`).
- Major constraints mapped:
  - Media allowlist/size/fingerprint/sniff (`backend/app/Services/MediaValidator.php:11`).
  - Async jobs + feature-flag degradation (`backend/app/Jobs/GenerateThumbnails.php:19`, `backend/app/Console/Commands/MonitoringSample.php:45`).
  - Encryption/log masking/30-day purge (`backend/app/Models/User.php:52`, `backend/app/Logging/MaskSensitiveFields.php:11`, `backend/app/Jobs/PurgeSoftDeletedUsers.php:22`).

## 4. Section-by-section Review

### 1. Hard Gates
#### 1.1 Documentation and static verifiability
- Conclusion: **Pass**
- Rationale: Startup/config/test instructions and route/module entrypoints are documented and statically align with code structure.
- Evidence: `README.md:12`, `README.md:76`, `backend/README.md:9`, `frontend/README.md:27`, `backend/routes/api.php:21`, `docker-compose.yml:45`.
- Manual verification note: Runtime setup success is not statically proven.

#### 1.2 Material deviation from Prompt
- Conclusion: **Partial Pass**
- Rationale: Implementation is strongly aligned overall, but there are material reliability gaps affecting core ingestion semantics.
- Evidence: Core alignment in `backend/routes/api.php:42`, `frontend/src/views/SearchView.vue:14`; reliability gap in `backend/app/Jobs/ReconcileDeviceEvents.php:42` and `backend/app/Http/Controllers/DeviceController.php:184`.
- Manual verification note: End-to-end behavior of out-of-order recovery requires runtime validation.

### 2. Delivery Completeness
#### 2.1 Coverage of explicit core requirements
- Conclusion: **Partial Pass**
- Rationale: Most explicit features exist (auth, RBAC, search/filter/sort, playlists share/redeem, admin tools, device ingest, media validation, monitoring), but key ingestion correctness and frontend null-safety defects remain.
- Evidence: Feature presence in `backend/routes/api.php:48`, `backend/app/Services/MediaValidator.php:58`, `frontend/src/views/devices/DeviceDetailView.vue:139`; defects in `backend/app/Jobs/ReconcileDeviceEvents.php:42`, `frontend/src/views/FavoritesView.vue:79`.

#### 2.2 End-to-end 0→1 deliverable vs partial demo
- Conclusion: **Pass**
- Rationale: Full multi-module project structure with backend/frontend/tests/docs is present; not a single-file demo.
- Evidence: `README.md:153`, `backend/`, `frontend/`, `backend/tests/Feature/Auth/LoginTest.php:13`, `frontend/e2e/auth/login-by-role.spec.ts:33`.
- Manual verification note: Runtime E2E validity still needs manual run.

### 3. Engineering and Architecture Quality
#### 3.1 Structure and decomposition
- Conclusion: **Pass**
- Rationale: Backend and frontend decomposition is reasonable for scope (controllers/services/jobs/middleware; views/stores/api client/router).
- Evidence: `backend/README.md:13`, `backend/app/Http/Controllers`, `backend/app/Jobs`, `frontend/src/router/index.ts:4`, `frontend/src/services/api.ts:187`.

#### 3.2 Maintainability and extensibility
- Conclusion: **Partial Pass**
- Rationale: Generally maintainable patterns, but some brittle logic paths and mismatch-prone test/documentation patterns reduce extensibility confidence.
- Evidence: Brittle reconciliation logic `backend/app/Jobs/ReconcileDeviceEvents.php:42`; frontend null assumption `frontend/src/views/FavoritesView.vue:79`; test/docs mismatch `CLAUDE.md:65` vs `frontend/playwright.config.ts:18`.

### 4. Engineering Details and Professionalism
#### 4.1 Error handling, logging, validation, API design
- Conclusion: **Partial Pass**
- Rationale: Strong validation/logging presence overall, but critical edge-case defects exist in ingestion reconciliation and gateway buffer handling.
- Evidence: Validation/logging present `backend/app/Services/MediaValidator.php:58`, `backend/config/logging.php:55`; defects `backend/app/Console/Commands/GatewayRun.php:113`, `backend/app/Jobs/ReconcileDeviceEvents.php:42`.

#### 4.2 Product-grade vs demo-grade
- Conclusion: **Partial Pass**
- Rationale: Core implementation appears product-oriented, but frontend E2E suite contains many mocked/smoke assertions that do not strongly validate product-critical flows.
- Evidence: Mock-only notes `frontend/e2e/devices/replay-with-audit.spec.ts:1`, trivial assertions `frontend/e2e/library/search-filter-sort.spec.ts:70`, `frontend/e2e/admin/user-freeze-blacklist.spec.ts:53`.

### 5. Prompt Understanding and Requirement Fit
#### 5.1 Business goal and implicit constraints fit
- Conclusion: **Partial Pass**
- Rationale: Business scenario is well understood and mostly implemented, but high-impact correctness gaps conflict with offline-first ingestion reliability intent.
- Evidence: Intent fit `backend/app/Http/Controllers/DeviceController.php:22`, `frontend/src/views/SearchView.vue:43`; reliability gap `backend/app/Jobs/ReconcileDeviceEvents.php:42` and buffer drop `backend/app/Console/Commands/GatewayRun.php:113`.

### 6. Aesthetics (frontend-only/full-stack)
#### 6.1 Visual/interaction quality fit
- Conclusion: **Cannot Confirm Statistically**
- Rationale: UI code indicates responsive layouts and interaction states, but visual rendering quality and usability cannot be proven without runtime/browser verification.
- Evidence: Responsive/interactions in `frontend/src/layouts/AppLayout.vue:74`, `frontend/src/views/SearchView.vue:110`, `frontend/src/views/devices/DeviceDetailView.vue:165`.
- Manual verification note: Validate on kiosk-tablet and desktop breakpoints in browser.

## 5. Issues / Suggestions (Severity-Rated)

### High
1. **Severity:** High
   - **Title:** Out-of-order reconciliation can fail to advance monotonic counter after missing events arrive
   - **Conclusion:** Fail
   - **Evidence:** `backend/app/Jobs/ReconcileDeviceEvents.php:42`, `backend/app/Jobs/ReconcileDeviceEvents.php:52`, `backend/app/Http/Controllers/DeviceController.php:184`, `backend/app/Http/Controllers/DeviceController.php:199`
   - **Impact:** Device sequence can remain stale after gap closure, violating prompt requirement to correct out-of-order sequences via monotonic counters.
   - **Minimum actionable fix:** Rework reconciliation to advance from current `devices.last_sequence_no` through contiguous accepted events and trigger reconciliation also when in-order events close known gaps.

2. **Severity:** High
   - **Title:** Favorites UI assumes non-null `asset` though backend explicitly returns null in valid cases
   - **Conclusion:** Fail
   - **Evidence:** Backend null contract `backend/app/Http/Controllers/FavoriteController.php:30`; frontend non-null assertion `frontend/src/views/FavoritesView.vue:79`; required prop usage `frontend/src/components/AssetTile.vue:10`
   - **Impact:** Runtime rendering failure risk for soft-deleted/missing assets in favorites, breaking a core user flow.
   - **Minimum actionable fix:** Guard null assets in favorites UI before rendering `AssetTile` and show placeholder rows for `asset == null`.

3. **Severity:** High
   - **Title:** Gateway buffer-at-capacity logic silently drops oldest queued events
   - **Conclusion:** Fail
   - **Evidence:** `backend/app/Console/Commands/GatewayRun.php:112`, `backend/app/Console/Commands/GatewayRun.php:115`
   - **Impact:** Potential data loss during prolonged outage despite offline buffering requirement; event history integrity compromised.
   - **Minimum actionable fix:** Replace silent eviction with explicit rejection/dead-letter strategy and operator-visible alerting when capacity reached.

4. **Severity:** High
   - **Title:** Frontend E2E coverage is heavily mocked/trivial and may miss severe regressions
   - **Conclusion:** Partial Fail
   - **Evidence:** Mock-only notes `frontend/e2e/devices/buffered-retransmission.spec.ts:1`, `frontend/e2e/devices/replay-with-audit.spec.ts:1`; trivial pass assertions `frontend/e2e/library/search-filter-sort.spec.ts:70`, `frontend/e2e/admin/user-freeze-blacklist.spec.ts:53`, `frontend/e2e/playlists/share-redeem-on-second-kiosk.spec.ts:90`
   - **Impact:** CI can pass while real integration defects remain undetected in critical role/device/admin flows.
   - **Minimum actionable fix:** Convert key mocked scenarios into live API-backed tests and replace `expect(true).toBe(true)` with behavior assertions tied to API/UI outcomes.

### Medium
5. **Severity:** Medium
   - **Title:** Recommendation hit-rate degradation logic is not a rolling-window metric
   - **Conclusion:** Partial Fail
   - **Evidence:** Global counters incremented `backend/app/Services/Monitoring/MetricsRecorder.php:115`, `backend/app/Services/Monitoring/MetricsRecorder.php:125`; breaker decision uses totals `backend/app/Console/Commands/MonitoringSample.php:30`
   - **Impact:** Degradation may be sticky or unrepresentative of current conditions, diverging from prompt intent.
   - **Minimum actionable fix:** Track recommendation requests/hits in a timestamped rolling window (e.g., Redis ZSET) analogous to latency/error samples.

6. **Severity:** Medium
   - **Title:** Latency sample recording can overwrite same-second identical values
   - **Conclusion:** Partial Fail
   - **Evidence:** `backend/app/Services/Monitoring/MetricsRecorder.php:20`
   - **Impact:** Under-sampling can skew p95 and degrade/recover decisions.
   - **Minimum actionable fix:** Use unique member IDs per latency sample (as done for request samples).

7. **Severity:** Medium
   - **Title:** Documentation claims kiosk+desktop Playwright profiles, config defines only one project
   - **Conclusion:** Fail (documentation consistency)
   - **Evidence:** Claimed profiles `CLAUDE.md:65`, `e2e-tests/README.md:7`; actual config `frontend/playwright.config.ts:18`
   - **Impact:** Reviewers/operators can overestimate scenario coverage.
   - **Minimum actionable fix:** Either implement explicit kiosk/desktop Playwright projects or correct docs to match actual single-project setup.

8. **Severity:** Medium
   - **Title:** Login rate-limit keyed only by username increases account-lockout abuse risk
   - **Conclusion:** Partial Fail (security hardening)
   - **Evidence:** `backend/app/Http/Controllers/Auth/AuthController.php:27`
   - **Impact:** Attackers can repeatedly lock targeted accounts without source-IP segmentation.
   - **Minimum actionable fix:** Include source/IP dimension and/or hybrid user+IP throttling strategy with lockout telemetry.

## 6. Security Review Summary
- **Authentication entry points:** **Pass**
  - Evidence: `POST /api/auth/login` validation + hash check in `backend/app/Http/Controllers/Auth/AuthController.php:21`; token issuance `:80`.
- **Route-level authorization:** **Pass**
  - Evidence: role middleware on admin/device groups `backend/routes/api.php:82`, `backend/routes/api.php:102`; role enforcement `backend/app/Http/Middleware/RoleMiddleware.php:32`.
- **Object-level authorization:** **Partial Pass**
  - Evidence: owner scoping in playlists `backend/app/Http/Controllers/PlaylistController.php:25`, `:122`, `:271`; cross-user tests `backend/tests/Feature/Security/CrossUserIsolationTest.php:10`.
  - Gap: frontend null-asset handling can break user data views (`frontend/src/views/FavoritesView.vue:79`).
- **Function-level authorization:** **Pass**
  - Evidence: admin-only user/monitoring/asset management routes in `backend/routes/api.php:84`..`:97`.
- **Tenant/user data isolation:** **Partial Pass**
  - Evidence: user-scoped favorites/history/playlists (`backend/app/Http/Controllers/FavoriteController.php:23`, `backend/app/Http/Controllers/PlayHistoryController.php:64`); tests `backend/tests/Feature/Security/CrossUserIsolationTest.php:25`.
  - Gap: no multi-tenant model in scope; single-site assumption.
- **Admin/internal/debug endpoint protection:** **Pass**
  - Evidence: monitoring and user management under admin role group `backend/routes/api.php:82`; gateway machine-auth token middleware `backend/routes/api.php:28`, `backend/app/Http/Middleware/GatewayTokenMiddleware.php:15`.

## 7. Tests and Logging Review
- **Unit tests:** **Partial Pass**
  - Exists for encryption, media validation, log masking, retry classifier.
  - Evidence: `backend/tests/Unit/EncryptedFieldCastTest.php:6`, `backend/tests/Unit/MediaValidatorTest.php:6`, `backend/tests/Unit/Logging/MaskSensitiveFieldsTest.php:18`, `frontend/src/tests/unit/auth.store.test.ts:39`.
  - Gap: missing unit tests around reconciliation algorithm correctness.

- **API / integration tests:** **Partial Pass**
  - Strong backend feature coverage for auth/media/playlists/devices/security.
  - Evidence: `backend/tests/Feature/Devices/DedupWindowAndGapTest.php:18`, `backend/tests/Feature/Search/SearchRankingTest.php:27`, `backend/tests/Feature/Security/AccountStatusTest.php:9`.
  - Gap: no test catches stale `last_sequence_no` after late gap fill.

- **Logging categories / observability:** **Pass**
  - API metrics recorder + monitoring endpoint + masking processor present.
  - Evidence: `backend/app/Http/Middleware/RecordApiMetrics.php:31`, `backend/app/Http/Controllers/MonitoringController.php:29`, `backend/config/logging.php:58`.

- **Sensitive-data leakage risk in logs / responses:** **Partial Pass**
  - Positive: sensitive key redaction implemented and tested.
  - Evidence: `backend/app/Logging/MaskSensitiveFields.php:11`, `backend/tests/Unit/Logging/MaskSensitiveFieldsTest.php:23`.
  - Residual risk: not all response payloads are centrally scrubbed; relies on per-endpoint response shaping.

## 8. Test Coverage Assessment (Static Audit)

### 8.1 Test Overview
- Unit tests exist: backend Pest unit + frontend Vitest.
  - Evidence: `backend/phpunit.xml:8`, `frontend/package.json:10`.
- API/integration tests exist: backend Pest Feature suites.
  - Evidence: `backend/phpunit.xml:11`, `backend/tests/Feature/...`.
- E2E tests exist: Playwright specs under `frontend/e2e`.
  - Evidence: `frontend/playwright.config.ts:5`, `frontend/e2e/auth/login-by-role.spec.ts:33`.
- Test entry points documented:
  - `README.md:81`, `README.md:120`, `README.md:128`, `README.md:134`, `run_tests.sh:97`.

### 8.2 Coverage Mapping Table
| Requirement / Risk Point | Mapped Test Case(s) | Key Assertion / Fixture / Mock | Coverage Assessment | Gap | Minimum Test Addition |
|---|---|---|---|---|---|
| Username/password login + token | `backend/tests/Feature/Auth/LoginTest.php:13` | token + user fields asserted `:21`..`:23` | sufficient | None major | Add token expiry/rotation behavior tests |
| 401/429 auth failures | `backend/tests/Feature/Auth/LoginTest.php:26`, `backend/tests/Feature/Security/RateLimitTest.php:29` | invalid creds + retry_after assertions | sufficient | No IP-dimension abuse test | Add user+IP throttle behavior tests |
| Admin RBAC on admin routes | `backend/tests/Feature/Auth/LoginTest.php:107`, `backend/tests/Feature/Auth/UserManagementTest.php:77` | 403 for non-admin, 200 for admin | sufficient | None major | Add negative tests for each admin sub-route |
| Playlist object ownership isolation | `backend/tests/Feature/Playlists/PlaylistCrudTest.php:71`, `backend/tests/Feature/Security/CrossUserIsolationTest.php:54` | 404/403 on non-owner operations | sufficient | None major | Add share-revoke cross-role permutations |
| Device dedup within 7-day window | `backend/tests/Feature/Devices/DedupWindowAndGapTest.php:18`, `:55` | accepted after 7 days; duplicate within window | basically covered | No concurrency race test | Add concurrent duplicate submissions test |
| Out-of-order/gap correction monotonic update | `backend/tests/Feature/Devices/DedupWindowAndGapTest.php:81`, `:109` | out_of_order classification assertions | insufficient | No assertion that late missing event advances counter past pending out-of-order event | Add test for seq 100, 102(out_of_order), then 101(in-order) => `last_sequence_no==102` |
| Upload allowlist/size/fingerprint/magic sniff | `backend/tests/Feature/Media/AssetUploadTest.php:61`, `:80`; `backend/tests/Unit/MediaValidatorTest.php:6` | 422 reason_code checks | sufficient | No randomized malformed corpus test | Add table-driven MIME spoof cases |
| Asset delete blocked when referenced | `backend/tests/Feature/Media/AssetDeleteReferencedTest.php:24` | 409 + reference_count | sufficient | None major | Add replacement-then-delete flow assertion |
| Recommendation degrade fallback | `backend/tests/Feature/Search/SearchRankingTest.php:66`; `backend/tests/Feature/Search/RecommendationEndpointTest.php:9` | degraded header + fallback=most_played | basically covered | No rolling-window timing test | Add metric-window boundary tests for breaker |
| Frontend critical user flows (real integration) | `frontend/e2e/...` | many specs mock API or use non-assertive checks | insufficient | High false-confidence risk | Convert top flows (login/search/share/redeem/device audit) to non-mocked API-backed assertions |

### 8.3 Security Coverage Audit
- **Authentication:** basically covered by backend feature tests (`backend/tests/Feature/Auth/LoginTest.php:13`).
- **Route authorization:** covered for key admin/non-admin paths (`backend/tests/Feature/Auth/LoginTest.php:107`).
- **Object-level authorization:** covered for playlists/favorites/history (`backend/tests/Feature/Security/CrossUserIsolationTest.php:10`).
- **Tenant/data isolation:** single-tenant assumptions; user-level isolation covered, multi-tenant not applicable.
- **Admin/internal protection:** covered for monitoring/admin and gateway token path (`backend/tests/Feature/Monitoring/DegradationFlagTest.php:101`, `backend/tests/Feature/Devices/GatewayAuthTest.php:18`).
- Residual risk: severe ingestion reconciliation defects can remain despite passing suite.

### 8.4 Final Coverage Judgment
- **Final Coverage Judgment:** **Partial Pass**
- Covered major risks:
  - Auth happy path/failures, core RBAC, many media/device/search contracts.
- Uncovered/high-risk gaps:
  - Reconciliation correctness after gap closure is not asserted.
  - Frontend E2E is often mocked/trivial, so severe integration defects can pass undetected.

## 9. Final Notes
- This assessment is strictly static; runtime correctness claims were not made.
- Most required product areas are implemented, but high-severity reliability and test-confidence gaps should be addressed before delivery acceptance.
