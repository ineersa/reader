# Castor workflow notes

## Daily development

1. Start stack: `castor dev:up`
2. Run one-off console commands: `castor dev:console "about"`
3. Run tests/quality: `castor dev:quality`
4. Stop stack: `castor dev:down`

## First-time setup

1. `castor dev:setup`
2. `castor dev:bootstrap`
3. Optional DB migration: `castor dev:console "doctrine:migrations:migrate --no-interaction"`
4. Optional worker: `castor dev:messenger-consume`

## Production-like local checks

1. `castor prod:build`
2. `castor prod:up`
3. `castor prod:console "doctrine:migrations:migrate --no-interaction"`
4. `castor prod:logs`
5. `castor prod:down`

## Discover tasks

- `castor list`
- `castor list dev`
- `castor list prod`
