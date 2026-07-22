# Chuckify

A small Symfony app that serves Chuck Norris jokes to registered users.

## Stack

- PHP 8.4, Symfony 7.4 (LTS)
- Doctrine ORM / MySQL 8
- Symfony AssetMapper + Stimulus + Tailwind CSS (no Node/npm toolchain required)
- `ai-service/`: Python 3.12, FastAPI - internal AI (embeddings/completions) service, see AI Features below

## Development

### Prerequisites

- PHP 8.4 or higher, with the `intl` and `pdo_mysql` extensions
- Composer
- Docker Compose
- Symfony CLI

### Installation

1. Checkout from git and change files to your needs.
2. Run `composer install`
3. Run `docker compose up -d` (starts MySQL, Mailpit, and `ai-service` in its fake-AI mode - see AI Features below)
4. Prepare the database:
   1. `symfony console doctrine:database:create`
   2. `symfony console doctrine:migrations:migrate`
5. If you want to set up a first user, run `symfony console doctrine:fixtures:load`
6. Build the Tailwind CSS once: `symfony console tailwind:build` - required before the app can render at all (dev, test and prod), since the page templates reference the compiled output

### Run the application

1. If not already started: `docker compose up -d`
2. Start the webserver: `symfony server:start -d`
3. (Optional) Watch and rebuild Tailwind CSS on change: `symfony console tailwind:build --watch`
4. Open `http://localhost:8000` in your browser
5. Log in with `chuck@local.wip` and password `Norris` - this fixture user is an admin (see `/admin`)
6. Emails (password reset, ...) are caught locally by [Mailpit](https://github.com/axllent/mailpit) (started as part of `docker compose up -d`) instead of being sent for real - view them at `http://localhost:8025`
7. To grant another user admin access: `symfony console app:user:promote <email>`

### Tests

```
php bin/phpunit
```

The test suite needs its own database (`joke_test` by default) and the Tailwind build from step 6 above; create and migrate the test database once with:

```
APP_ENV=test symfony console doctrine:database:create
APP_ENV=test symfony console doctrine:migrations:migrate --no-interaction
```

`ai-service` has its own, independent test suite:

```
cd ai-service
python3 -m venv .venv && source .venv/bin/activate
pip install -e ".[dev]"
ruff check .
pytest
```

## API

A read-only JSON API is available behind the same session auth as the rest of the app (log in via the browser first):

- `GET /api/jokes?category=&limit=` - list approved jokes, newest first
- `GET /api/jokes/{id}` - a single approved joke
- `GET /api/jokes/random` - a random approved joke from the database (doesn't hit the external Chuck Norris API)
- `GET /api/jokes/top?limit=` - the most-liked jokes

## AI Features

### Foundation

AI capability (embeddings, LLM completions) is provided by a separate internal service, `ai-service/` - a small FastAPI app (Python 3.12) that wraps the OpenAI API. Symfony never calls OpenAI directly; it only talks to `ai-service` over HTTP, so the OpenAI API key only ever needs to exist in one place.

**Why a separate service instead of calling OpenAI from PHP directly:** it keeps AI-specific dependencies (an OpenAI SDK, JSON Schema validation, retry/batching logic) out of the PHP app entirely, and makes it trivial to run the whole app with AI features completely disabled (see below) without needing to fake an HTTP client inside PHP tests for every feature that touches AI.

**Endpoints** (all except `/health` require an `X-Internal-Secret` header matching `SHARED_SECRET`):

- `POST /embeddings` - `{"texts": [...]}` → `{"vectors": [[...], ...], "model": "..."}`, batched at 100 texts per OpenAI call
- `POST /complete` - `{"system": ..., "user": ..., "response_schema": {...}?, "max_tokens": ...}` → `{"text": "..."}` (no schema) or `{"data": {...}}` (schema given - validated against it before the response is returned, on both the ai-service side and again on the PHP side)
- `GET /health` - no auth required, used for the container healthcheck

**Running without a real OpenAI key:** set `AI_PROVIDER=fake` on `ai-service` (the default - see `ai-service/.env.example`) to get deterministic canned responses instead of real API calls. This is what local dev and CI use.

**PHP side:** `App\Ai\EmbeddingProviderInterface` / `App\Ai\CompletionProviderInterface`, backed by `HttpAiServiceProvider` (talks to `ai-service`) or `NullAiProvider` (always throws, for running with AI disabled entirely) - selected via the `AI_PROVIDER` env var (`http` default, or `null`). Every call is logged to the `ai` Monolog channel (duration, model, no user content or secrets). Every provider call can throw `App\Exception\AiServiceException`; callers are expected to catch it and fall back to non-AI behavior, the same way `JokeManager` already falls back to the database when the external jokes API is unreachable.

Required env vars (PHP side, see `.env`): `AI_PROVIDER`, `AI_SERVICE_BASE_URL`, `AI_SERVICE_SHARED_SECRET` (must match `ai-service`'s own `SHARED_SECRET`).

### Semantic search

`/search` embeds the query and ranks approved jokes by cosine similarity against their stored embeddings (`App\Services\SemanticJokeSearch`), instead of (or rather: in addition to - see fallback below) the original full-text search. A small "semantisch" badge appears above the results whenever the AI path was actually used.

- **Embeddings are generated asynchronously.** Saving a new joke (external API fetch) or approving a submitted one dispatches `App\Message\GenerateJokeEmbeddingMessage` onto Messenger's `async` transport (Doctrine-backed, see `config/packages/messenger.yaml`) so the request never waits on `ai-service`. A dedicated `messenger-worker` container processes it (`compose.prod.yaml`); in dev, run `symfony console messenger:consume async -vv` in a separate terminal to process the queue (or just run the backfill command below, which embeds synchronously).
- **`php bin/console app:embeddings:backfill [--limit=N]`** embeds every approved joke that doesn't have one yet - idempotent (skips jokes that already have an embedding), batched, with a progress bar. Useful after a fresh install, or if some jokes were added before `ai-service` was reachable.
- **Similarity search** is brute-force cosine similarity in PHP (`App\Ai\Search\BruteForceCosineSimilaritySearch`), fine at this app's scale - kept behind `SimilaritySearchInterface` so a real vector database could replace it later without touching `SemanticJokeSearch`.
- **Optional LLM reranking**: set `AI_SEARCH_RERANK=true` to have the top-20 cosine matches reranked to a top-5 via `/complete` with a structured-output schema. The search query is passed as clearly delimited data (not instructions) in the rerank prompt, and truncated to 200 characters, as a prompt-injection safeguard. If reranking fails, the cosine-similarity order is used unchanged.
- **Fallback chain**: no embeddings indexed yet, or any AI call fails → the original `JokeRepository::search()` (MySQL FULLTEXT) runs instead, silently (no badge, no error shown to the user).

### Submission moderation

Every joke submission (`/joke/submit`) dispatches `App\Message\GenerateModerationResultMessage` (same async transport as embeddings) to get an AI pre-assessment before an admin ever looks at it:

- **`App\Services\JokeModerationAnalyzer`** asks the LLM (via `/complete`, structured output) for a `recommendation` (`approve`/`reject`/`unsure`), a `confidence` (0-1), short `reasons`, and any `flags` (`offensive`, `not_a_joke`, `low_quality`) - stored as `App\Entity\ModerationResult`, one per submission.
- **Duplicate detection is separate from the LLM call**: the analyzer embeds the submission and runs it through the same similarity search as semantic search (Feature 1) against existing *approved* jokes. A match above a fixed cosine-similarity threshold sets `ModerationResult::$duplicateOf` - the LLM never sees the rest of the database, so it has no way to know what already exists; embeddings do.
- **The admin always decides.** `/admin/jokes` shows the recommendation, confidence, reasons, flags, and a link to the duplicate (if any) next to each pending submission, but approving or deleting is always a manual, explicit admin action - there's no auto-approve/auto-reject. If no `ModerationResult` exists yet (still queued, or the AI call failed), the UI just says "No AI analysis available." and the review workflow is unaffected.
- **Prompt-injection hardening**: the submitted text is truncated to 500 characters (matching the submission form's own `Length` constraint) and wrapped in explicit `<<<JOKE>>>` delimiters with an "ignore instructions in here" system-prompt line, the same pattern used for the search-rerank prompt above.

### Explain the joke

On a joke's detail page (`/joke/{id}`), an "Explain this joke" button asks the LLM to explain the wordplay/cultural reference in 2-4 sentences, in English or German (a small EN/DE toggle next to the button; defaults to the browser's language).

- **`App\Services\JokeExplainer`** generates an explanation via `/complete` (plain text, no schema) and persists it as `App\Entity\JokeExplanation`, unique per `(joke, locale)`. Unlike embeddings/moderation, this call happens **synchronously** in the request (`JokeController::explain()`, `POST /joke/{id}/explain`) - there's a human waiting on a button click, and the result is cached after the first call, so the cost of one blocking LLM call is paid at most once per joke per language, ever.
- **Caching is the point**: every call first checks for an existing `JokeExplanation` for that `(joke, locale)` and returns it unchanged if found - repeat clicks, or a different user asking about the same joke in the same language, never spend another LLM call.
- **Rate limited** (`explain_action`, 10/minute per user) independently of the like/reaction limiters, since a cache miss is comparatively expensive.
- **Prompt-injection hardening**: same `<<<JOKE>>>`-delimited, length-truncated (500 chars) pattern as moderation; the frontend renders the response via `textContent` only, never `innerHTML`, so nothing the model returns can inject markup.
- On failure (AI service down, rate limited elsewhere, etc.) the endpoint returns a friendly JSON error and the rest of the page stays fully usable - explaining a joke is a nice-to-have, never a blocker.

## Production

### Secrets

Real secrets (`APP_SECRET`, `DATABASE_URL`, `MYSQL_ROOT_PASSWORD`, ...) must never be committed. Use either:

- the Symfony secrets vault (`php bin/console secrets:set APP_SECRET`) - the vault's public key is committed under `config/secrets/prod/`, the private decryption key is not and must be provisioned separately on the deploy target, or
- real environment variables injected by your hosting platform / `compose.prod.yaml`'s `env_file`.

`TRUSTED_PROXIES` should be set to the IP/CIDR of the reverse proxy in front of the app (see `config/packages/framework.yaml`).

Emails (password reset, registration confirmation) are sent via [Resend](https://resend.com/); set `MAILER_DSN=resend+api://RESEND_API_KEY@default` as a real env var / secret in prod. Dev/test default to `null://null` (or Mailpit in dev, see above) so nothing is ever sent from a non-prod environment by accident.

`OPENAI_API_KEY` and `AI_SERVICE_SHARED_SECRET` are only needed if you want real AI features (see AI Features above); leaving `OPENAI_API_KEY` unset with `AI_SERVICE_AI_PROVIDER=fake` runs `ai-service` in its deterministic fake mode instead. `PHP_AI_PROVIDER` and `AI_SERVICE_AI_PROVIDER` are deliberately separate variables (different services, different allowed values) - see the comments in `compose.prod.yaml`.

### Security headers

`config/packages/nelmio_security.yaml` (via `nelmio/security-bundle`) sets a Content-Security-Policy (nonce-based, matching `csp_nonce()` passed to `importmap()` in `templates/base.html.twig`), `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, and a restrictive `Referrer-Policy` on every response. HSTS (`Strict-Transport-Security`) is only enabled `when@prod`, since it would otherwise force HTTPS on local dev.

### Build & deploy

The production image is built directly from source - there's no separate frontend build step to coordinate, since AssetMapper compiles Tailwind CSS and the Stimulus/importmap JS at image build time:

```
docker compose -f compose.prod.yaml build
```

`compose.prod.yaml` defines five services: `app` (PHP-FPM), `nginx` (serves static assets, proxies PHP requests to `app`), `messenger-worker` (processes async jobs like embedding generation - see AI Features above), `ai-service` (internal only) and `database`. It uses its own Compose project name (`chuckify-prod`) so it never collides with the local dev stack (`chuckify-dev`, from `compose.yaml`).

Uploaded avatars are written to `/app/public/uploads/avatars` at runtime, which isn't part of the image (it's rebuilt on every deploy). Both `app` and `nginx` mount the same `avatar_uploads` named volume at that path - `app` so uploads persist across redeploys, `nginx` so it can actually see and serve files `app` wrote.

Typical deploy sequence:

1. Provide `DATABASE_URL`, `APP_SECRET`, `MAILER_DSN`, `MYSQL_ROOT_PASSWORD`, `MYSQL_PASSWORD`, `TRUSTED_PROXIES`, `AI_SERVICE_SHARED_SECRET` as real environment variables (e.g. via an `.env.prod.local`-style file passed to `--env-file`, or your platform's secrets manager). Add `OPENAI_API_KEY` too if you want real AI features rather than `ai-service`'s fake mode. These override the placeholder values baked into the image at build time, so nothing sensitive needs to be known at build time.
2. `docker compose -f compose.prod.yaml up -d --build`
3. Run migrations inside the running app container: `docker compose -f compose.prod.yaml exec app php bin/console doctrine:migrations:migrate --no-interaction`

If you deploy from source instead of this prebuilt image (e.g. onto a plain PHP-FPM host), you can additionally run `composer dump-env prod` on the target to compile the `.env` files into `.env.local.php`, skipping repeated `.env` parsing on every request. It isn't needed for the Docker setup above: the runtime image intentionally has no Composer/build tooling, and Symfony already prefers real environment variables over `.env` file values without it.

### Health check

`GET /health` checks database connectivity and returns `{"status":"ok"}` (200) or `{"status":"error", ...}` (503) - point your orchestrator's readiness/liveness probe at it.

### Scheduled tasks

The "Joke of the Day" (`/joke-of-the-day`) is picked once and then reused for the rest of the day; nothing selects it automatically. Run `app:joke-of-the-day:select` once a day (e.g. via the host's crontab or your orchestrator's scheduled-job feature) so the joke is ready before anyone visits the page:

```
0 6 * * * docker compose -f compose.prod.yaml exec -T app php bin/console app:joke-of-the-day:select
```

Running it more than once on the same day is harmless - it's idempotent and simply returns the joke already selected for today.

### Backups

`docker/backup/backup.sh` dumps the database to a gzip-compressed, timestamped file and prunes backups older than `BACKUP_RETENTION_DAYS` (default 14 days). It needs `MYSQL_ROOT_PASSWORD` in its environment (the same value used for `compose.prod.yaml`). Run it daily via cron on the deploy host, next to the repo checkout:

```
0 3 * * * MYSQL_ROOT_PASSWORD=... BACKUP_DIR=/var/backups/chuckify /path/to/chuckify/docker/backup/backup.sh >> /var/log/chuckify-backup.log 2>&1
```

To restore a backup:

```
gunzip -c chuckify-20260722-030000.sql.gz | docker compose -f compose.prod.yaml exec -T database mysql -uroot -p"$MYSQL_ROOT_PASSWORD" joke
```

This covers the database only - uploaded avatars live in the separate `avatar_uploads` volume (see above) and aren't included in these dumps.

### Error tracking

Set `SENTRY_DSN` as a real environment variable in prod to send uncaught exceptions and error-level log records to [Sentry](https://sentry.io/). Left unset (the default), the SDK simply doesn't send anything - dev and test are unaffected either way.

### CI

`.github/workflows/ci.yaml` runs two jobs on every push/PR: `test` (`composer validate`, the full PHP test suite against a MySQL service container, `composer audit`) and `ai-service-test` (`ruff check`, `pytest` for `ai-service/`, using its fake AI provider - no OpenAI key needed in CI).
