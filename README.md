# Symfony Web Template

Reusable Symfony 8 template with Docker (FrankenPHP), Castor tasks, Cursor/OpenCode setup, and production-friendly defaults.

## Included in this template

- Docker runtime: FrankenPHP + Mercure + SQLite-ready setup.
- Compose files: `compose.yaml`, `compose.override.yaml`, `compose.prod.yaml`.
- Developer workflow: Castor task runner (`castor.php` + `.castor/`), VS Code wrappers, PHPStan/CS Fixer defaults.
- Agent setup: `.cursor/skills`, `.opencode/skills`, `.opencode/agents`, `AGENTS.md`.
- Environment templates: `.env`, `.env.dev`, `.env.test`, `.env.prod`, `.env.prod.local.dist`.
- Generic docs: local setup, Mercure notes, and deployment guide.

## Install Castor

Install Castor once on your machine:

```bash
curl "https://castor.jolicode.com/install" | bash
```

Then verify:

```bash
castor --version
castor list
```

## Quick start

```bash
castor dev:setup
castor dev:bootstrap
```

`castor dev:bootstrap` does not run database migrations.

If your project uses Doctrine migrations:

```bash
castor dev:console "doctrine:migrations:migrate --no-interaction"
```

In another terminal:

```bash
castor dev:messenger-consume
```

Open `http://localhost:8080`.

## Daily run

```bash
castor dev:up
```

Useful lifecycle commands:

- `castor dev:down`
- `castor dev:restart`
- `castor dev:ps`
- `castor dev:logs`

## Production-like local run

```bash
castor prod:up
```

Useful commands:

- `castor prod:down`
- `castor prod:restart`
- `castor prod:ps`
- `castor prod:logs`
- `castor prod:console "doctrine:migrations:migrate --no-interaction"`

## Command reference

- `docs/castor.md`

## Placeholder replacement checklist

This template intentionally uses placeholders in some files.

- `{{PROJECT_PATH}}`: absolute local project path used by `.vscode/*.sh` wrappers.
- `{{APP_DOMAIN}}`: production domain example in `.env.prod.local.dist`.

Suggested replacement command:

```bash
rg -l '\{\{PROJECT_PATH\}\}|\{\{APP_DOMAIN\}\}' . | xargs sed -i "s#{{PROJECT_PATH}}#/absolute/path/to/your/project#g; s#{{APP_DOMAIN}}#app.example.com#g"
```

## Important files

- `AGENTS.md`
- `docs/setup.md`
- `docs/server-deployment.md`
- `docs/mercure.md`
