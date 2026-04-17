# End-to-End Tests

Browser-level tests that drive the deployed SPA against the real Laravel API, MySQL, and Redis inside Docker. Each scenario maps to a user story from the prompt.

- **Tooling:** Playwright (TypeScript) with Chromium (kiosk profile) and desktop profile.
- **Run:**
  ```bash
  docker compose --profile test up -d nginx backend queue-worker scheduler mysql redis
  docker compose --profile test run --rm e2e-runner
  ```

## Scenarios (one spec file each)

```
e2e-tests/
├── auth/
│   └── login-by-role.spec.ts          # each role lands on its view
├── library/
│   ├── search-filter-sort.spec.ts      # tags + duration + recency + sort
│   └── recommended-degradation.spec.ts # breaker trips → badge appears
├── favorites-and-playlists/
│   ├── favorite-and-unfavorite.spec.ts
│   ├── playlist-build-edit.spec.ts
│   └── share-redeem-on-second-kiosk.spec.ts
├── now-playing/
│   └── recent-plays-and-reasons.spec.ts
├── admin/
│   ├── user-freeze-blacklist.spec.ts
│   ├── upload-validation.spec.ts       # magic-byte + size caps
│   ├── delete-referenced-asset.spec.ts
│   └── monitoring-dashboard.spec.ts
└── devices/
    ├── duplicate-and-out-of-order.spec.ts
    ├── buffered-retransmission.spec.ts
    └── replay-with-audit.spec.ts
```

## Kiosk profile

A Playwright project `smartpark-kiosk` boots Chromium with a tablet viewport (1024×1366) and `hasTouch: true` so tap-target regressions are caught automatically.
