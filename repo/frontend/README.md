# SmartPark SPA — Vue 3 + TypeScript

On-premises parking media operations single-page application built with Vue 3, TypeScript, Vite, Pinia, Vue Router, and Tailwind CSS 4.

See [`../CLAUDE.md`](../CLAUDE.md) for full technical details.

## Dev server

```bash
npm run dev
```

Runs on port 5173 with hot module replacement. Or via Docker:

```bash
docker compose --profile dev up -d
```

## Build

```bash
npm run build
```

Output in `dist/`. Nginx serves the built SPA at http://localhost:8090.

## Tests

```bash
# Unit tests (Vitest)
npx vitest run

# E2E tests (Playwright)
npx playwright test
```

## Key directories

| Path | Purpose |
|------|---------|
| `src/views/` | One component per route |
| `src/services/api.ts` | All HTTP calls (Axios/fetch wrapper) |
| `src/stores/` | Pinia stores: auth, player, ui |
| `src/types/api.ts` | TypeScript DTOs mirroring API responses |
| `src/components/` | Shared: AssetTile, ShareDialog, RedeemDialog, AppLayout |
| `e2e/` | Playwright E2E specs |

## Role-to-route mapping

| Path | Allowed roles |
|------|---------------|
| `/login` | public |
| `/search`, `/library`, `/favorites`, `/playlists`, `/now-playing` | all authenticated |
| `/admin/*` | admin |
| `/devices/*` | admin, technician |
