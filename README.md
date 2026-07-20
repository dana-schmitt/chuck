# Chuckify

A small Symfony app that serves Chuck Norris jokes to registered users.

## Stack

- PHP 8.4, Symfony 7.4 (LTS)
- Doctrine ORM / MySQL 8
- Symfony AssetMapper + Stimulus + Tailwind CSS (no Node/npm toolchain required)

## Development

### Prerequisites

- PHP 8.4 or higher, with the `intl` and `pdo_mysql` extensions
- Composer
- Docker Compose
- Symfony CLI

### Installation

1. Checkout from git and change files to your needs.
2. Run `composer install`
3. Run `docker compose up -d` (starts the MySQL container)
4. Prepare the database:
   1. `symfony console doctrine:database:create`
   2. `symfony console doctrine:migrations:migrate`
5. If you want to set up a first user, run `symfony console doctrine:fixtures:load`

### Run the application

1. If not already started: `docker compose up -d`
2. Start the webserver: `symfony server:start -d`
3. (Optional) Watch and rebuild Tailwind CSS on change: `symfony console tailwind:build --watch`
4. Open `http://localhost:8000` in your browser
5. Log in with `chuck@local.wip` and password `Norris`

### Tests

```
php bin/phpunit
```

The test suite needs its own database (`joke_test` by default); create and migrate it once with:

```
APP_ENV=test symfony console doctrine:database:create
APP_ENV=test symfony console doctrine:migrations:migrate --no-interaction
```

## Production

### Secrets

Real secrets (`APP_SECRET`, `DATABASE_URL`, `MYSQL_ROOT_PASSWORD`, ...) must never be committed. Use either:

- the Symfony secrets vault (`php bin/console secrets:set APP_SECRET`) - the vault's public key is committed under `config/secrets/prod/`, the private decryption key is not and must be provisioned separately on the deploy target, or
- real environment variables injected by your hosting platform / `compose.prod.yaml`'s `env_file`.

`TRUSTED_PROXIES` should be set to the IP/CIDR of the reverse proxy in front of the app (see `config/packages/framework.yaml`).

### Build & deploy

The production image is built directly from source - there's no separate frontend build step to coordinate, since AssetMapper compiles Tailwind CSS and the Stimulus/importmap JS at image build time:

```
docker compose -f compose.prod.yaml build
```

`compose.prod.yaml` defines three services: `app` (PHP-FPM), `nginx` (serves static assets, proxies PHP requests to `app`) and `database`. It uses its own Compose project name (`chuckify-prod`) so it never collides with the local dev stack (`chuckify-dev`, from `compose.yaml`).

Typical deploy sequence:

1. Provide `DATABASE_URL`, `APP_SECRET`, `MYSQL_ROOT_PASSWORD`, `MYSQL_PASSWORD`, `TRUSTED_PROXIES` as real environment variables (e.g. via an `.env.prod.local`-style file passed to `--env-file`, or your platform's secrets manager). These override the placeholder values baked into the image at build time, so nothing sensitive needs to be known at build time.
2. `docker compose -f compose.prod.yaml up -d --build`
3. Run migrations inside the running app container: `docker compose -f compose.prod.yaml exec app php bin/console doctrine:migrations:migrate --no-interaction`

If you deploy from source instead of this prebuilt image (e.g. onto a plain PHP-FPM host), you can additionally run `composer dump-env prod` on the target to compile the `.env` files into `.env.local.php`, skipping repeated `.env` parsing on every request. It isn't needed for the Docker setup above: the runtime image intentionally has no Composer/build tooling, and Symfony already prefers real environment variables over `.env` file values without it.

### Health check

`GET /health` checks database connectivity and returns `{"status":"ok"}` (200) or `{"status":"error", ...}` (503) - point your orchestrator's readiness/liveness probe at it.

### CI

`.github/workflows/ci.yaml` runs on every push/PR: `composer validate`, the full test suite against a MySQL service container, and `composer audit`.
