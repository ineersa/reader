# Castor commands

## Development

- `castor dev:init`
- `castor dev:setup`
- `castor dev:bootstrap`
- `castor dev:build`
- `castor dev:up`
- `castor dev:down`
- `castor dev:stop`
- `castor dev:restart`
- `castor dev:ps`
- `castor dev:logs`
- `castor dev:logs-php`
- `castor dev:logs-mailer`
- `castor dev:pull`
- `castor dev:prune`
- `castor dev:sh`
- `castor dev:root-sh`

## Symfony / Composer / DB

- `castor dev:composer-install`
- `castor dev:composer-update`
- `castor dev:composer "require vendor/package"`
- `castor dev:console "about"`
- `castor dev:messenger-consume`
- `castor dev:messenger-clear`

## Quality / Assets

- `castor dev:test`
- `castor dev:test-coverage`
- `castor dev:cs-fix`
- `castor dev:phpstan`
- `castor dev:quality`
- `castor dev:check`
- `castor dev:config`

## Useful console commands

- `castor dev:console "doctrine:migrations:migrate --no-interaction"`
- `castor dev:console "doctrine:migrations:status"`
- `castor dev:console "tailwind:build --watch"`
- `castor dev:console "asset-map:compile"`

## Production-like

- `castor prod:build`
- `castor prod:up`
- `castor prod:down`
- `castor prod:stop`
- `castor prod:restart`
- `castor prod:ps`
- `castor prod:logs`
- `castor prod:console "about"`
- `castor prod:config`

## Useful production console commands

- `castor prod:console "doctrine:migrations:migrate --no-interaction"`
