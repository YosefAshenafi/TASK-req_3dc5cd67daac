# Unit Tests

Pure function / single-class tests with no I/O. These are the fastest feedback loop and are run on every save during development.

- **Tooling:** Pest (PHP) for backend, Vitest for frontend TypeScript.
- **Scope:**
  - **Backend:** value objects, policies, validators (media MIME sniffing, fingerprint checks), recommendation scoring, dedup logic, feature-flag circuit breaker, encryption cast.
  - **Frontend:** Pinia store actions, composable logic (`useSearchFilters`, `usePlayerQueue`), formatting utilities, TypeScript DTO guards.
- **Run:**
  ```bash
  docker compose --profile test run --rm test-runner \
    php artisan test --testsuite=Unit

  docker compose --profile dev run --rm frontend-dev \
    npx vitest run
  ```

## Layout

```
unit-tests/
├── backend/
│   ├── MediaValidatorTest.php
│   ├── DeviceEventDeduplicatorTest.php
│   ├── RecommendationScorerTest.php
│   ├── CircuitBreakerTest.php
│   └── EncryptedFieldCastTest.php
└── frontend/
    ├── stores/authStore.spec.ts
    ├── stores/playlistStore.spec.ts
    ├── composables/useSearchFilters.spec.ts
    └── utils/formatDuration.spec.ts
```
