# AGENTS.md

Online auction monorepo. Two top-level packages, no shared tooling at root:

- `frontend/` — Next.js (App Router), TypeScript strict, Tailwind. Runs locally, **not** in Docker.
- `backend/` — Laravel + all Docker infrastructure (PostgreSQL, Redis, Horizon). REST API under `/api`,
  Filament admin at `/admin`, Sanctum auth, Redis for cache/sessions/queues.

Domain: `User` (role `user|admin`, status `pending|approved|rejected|banned`) -> `Auction` -> `Bid`.
Money is stored as **integers in kopecks** — never floats.

## Commands

Backend always runs inside the Docker container, executed from `backend/`:

```bash
docker compose exec backend php artisan migrate --seed
docker compose exec backend php artisan test                  # PHPUnit (single test: --filter=TestName)
docker compose exec backend php artisan test --filter=Name
```

- All `docker compose` commands are run from `backend/`; the Laravel service is named `backend`.
- Until containers exist, run composer via `docker run --rm -v "$PWD":/app composer ...`.
- PHPUnit only — no Pest. Never run local `./vendor/bin/phpunit`; everything goes through the container.
- Frontend: `npm run lint` must pass. No `any` types.
- API address in frontend comes only from `NEXT_PUBLIC_API_URL`.

## Backend conventions (strict)

- Validation only via Form Requests; responses only via API Resources; authorization via Policies/Gates.
- Mass-assignment protection via `$fillable`. `role` and `status` are **never** in `$fillable`.
- DB schema changes only via migrations. Seeders must leave a usable database after `migrate --seed`.
- Filament admin at `/admin`: access restricted to `role=admin` via `canAccessPanel`; `/horizon` admin-only.
- Queues only via Redis + Horizon; emails and notifications must be sent through the queue.

## Secrets & infra

- Never read, print, or commit `.env`. Only `.env.example` with placeholder values. No secrets in code
  or in `docker-compose.yml` — reference `${VAR}` resolved from `backend/.env`.
- Postgres and Redis ports are **not** published to the host.

## Git / workflow

- Never run `git commit` or `git push`. Never use `git add .`.
- No nested `.git` inside `backend/` or `frontend/` — delete them if generators create one.
- Before changing multiple files, show a plan first.
- After each task, briefly list the changes and how to verify them.
