---
name: castor
description: Castor task runner workflow for this repository. Use for Docker lifecycle, Symfony console operations, migrations, quality checks, and any infrastructure/project operation command. Triggers - castor, docker lifecycle, start stack, stop stack, run migrations, run tests in container, prod compose, local setup, task runner, replace make, list commands.
license: MIT
metadata:
  author: Symfony Web Template
  version: "1.0"
---

# Castor

This repository uses Castor as the single task runner for local and production-like operations.

## Rules

- Use `castor` commands instead of `make`.
- Keep using Dockerized PHP/Composer/console operations.
- Prefer `dev:*` tasks for local development.
- Use `prod:*` tasks only for production-like compose operations.

## Quick start

```bash
castor dev:setup
castor dev:bootstrap
castor dev:up
```

## Core command groups

- **Dev lifecycle:** `dev:up`, `dev:down`, `dev:restart`, `dev:ps`, `dev:logs`
- **Dev app ops:** `dev:console`, `dev:messenger-consume`
- **Quality:** `dev:test`, `dev:phpstan`, `dev:cs-fix`, `dev:quality`
- **Console examples:** `dev:console "doctrine:migrations:migrate --no-interaction"`, `dev:console "tailwind:build --watch"`
- **Prod-like lifecycle:** `prod:build`, `prod:up`, `prod:down`, `prod:restart`, `prod:ps`, `prod:logs`

## References

- **Project command guide:** [../../../docs/castor.md](../../../docs/castor.md)
- **Command catalog:** [references/commands.md](references/commands.md)
- **Operational workflow:** [references/workflow.md](references/workflow.md)
