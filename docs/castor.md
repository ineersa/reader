# Castor command guide

This project uses [Castor](https://castor.jolicode.com/) as the only task runner.

## Install

```bash
curl "https://castor.jolicode.com/install" | bash
castor --version
```

## Task files

- Entry point: `castor.php`
- Shared helpers: `.castor/helpers.php`
- Development tasks: `.castor/dev.php` (`dev:*`)
- Production-like tasks: `.castor/prod.php` (`prod:*`)

## Common commands

### Development

- `castor dev:setup`
- `castor dev:bootstrap`
- `castor dev:up`
- `castor dev:down`
- `castor dev:restart`
- `castor dev:ps`
- `castor dev:logs`
- `castor dev:sh`

### App and tooling

- `castor dev:composer-install`
- `castor dev:composer "require vendor/package"`
- `castor dev:console "about"`
- `castor dev:messenger-consume`
- `castor dev:test`
- `castor dev:test-coverage`
- `castor dev:cs-fix`
- `castor dev:phpstan`
- `castor dev:quality`

Useful console examples:

- `castor dev:console "doctrine:migrations:migrate --no-interaction"`
- `castor dev:console "doctrine:migrations:status"`
- `castor dev:console "tailwind:build --watch"`
- `castor dev:console "asset-map:compile"`

### Production-like

- `castor prod:build`
- `castor prod:up`
- `castor prod:down`
- `castor prod:restart`
- `castor prod:ps`
- `castor prod:logs`
- `castor prod:console "about"`
- `castor prod:config`

Useful production console example:

- `castor prod:console "doctrine:migrations:migrate --no-interaction"`

## Discover tasks

```bash
castor list
castor list dev
castor list prod
```

## Notes

- Docker commands are executed through `docker compose`.
- Dev tasks use `compose.yaml` + `compose.override.yaml`.
- Prod-like tasks use `compose.yaml` + `compose.prod.yaml`.
- User-mapped container commands use `$(id -u):$(id -g)` to keep file ownership correct.
- `castor dev:up` and `castor dev:restart` stop conflicting running containers from other compose projects if they already bind the configured dev ports (`HTTP_PORT`, `HTTPS_PORT`, `MAILER_SMTP_PORT`, `MAILER_UI_PORT`).
- `castor dev:setup` and `castor dev:bootstrap` also stop conflicting running containers from other compose projects before doing their work.
- `castor dev:bootstrap` checks DNS resolution for `github.com` inside the PHP container before `tailwind:build` and fails fast with a Docker DNS hint when resolution is broken.
- `castor dev:bootstrap` removes invalid cached Tailwind binaries (for example zero-byte files after interrupted downloads) before triggering a fresh build.
