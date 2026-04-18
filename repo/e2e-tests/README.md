# End-to-End Tests — Guide

> This directory is a **guide / catalog**, not an executable suite. The actual Playwright
> specs live at `frontend/e2e/`; running the `e2e-runner` Docker Compose service below is
> what actually executes them.

Browser-level tests using Playwright (TypeScript) with Chromium in two profiles:
- **kiosk** — 1024×1366 tablet viewport, touch enabled
- **desktop** — standard Chromium viewport

## Run

```bash
# Ensure backend is running first
docker compose up -d mysql redis backend nginx queue-worker scheduler

# Run E2E suite
docker compose --profile test run --rm e2e-runner
```

## Spec files

```
frontend/e2e/
├── auth/
│   └── login-by-role.spec.ts
├── library/
│   ├── search-filter-sort.spec.ts
│   ├── favorite-and-unfavorite.spec.ts
│   └── recommended-degradation.spec.ts
├── playlists/
│   ├── playlist-build-edit.spec.ts
│   └── share-redeem-on-second-kiosk.spec.ts
├── admin/
│   ├── user-freeze-blacklist.spec.ts
│   ├── upload-validation.spec.ts
│   ├── delete-referenced-asset.spec.ts
│   └── monitoring-dashboard.spec.ts
└── devices/
    ├── duplicate-and-out-of-order.spec.ts
    ├── buffered-retransmission.spec.ts   ← uses mocked API routes (not full E2E)
    └── replay-with-audit.spec.ts         ← uses mocked API routes (not full E2E)
```

Note: `buffered-retransmission.spec.ts` and `replay-with-audit.spec.ts` use Playwright route mocks for device API calls. They are component-level browser tests, not full end-to-end tests against the live backend.
