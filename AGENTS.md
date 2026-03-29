# symfony-web-template

This repository is a reusable Symfony UX template.

## Which tool to use

- **Pure JS behavior, no server round-trip** -- use the `stimulus` skill
- **Navigation, partial page updates** -- use the `turbo` skill
- **Reusable static UI component** -- use the `twig-component` skill
- **Reactive component that re-renders on user input** -- use the `live-component` skill
- **Not sure which one fits** -- use the `symfony-ux` skill
- **Browser automation / UI testing** -- use Task tool with `subagent_type: "playwright-cli"`
- **Infrastructure / Docker / project operations** -- use Castor tasks (`castor ...`) and the `castor` skill
- **Diagnostics / quality / Composer / PHPStan / PHPUnit operations** -- always load `mate-tools` and use `mate/mate-tool-call.sh` first
- **Creating or updating Castor task definitions** (`castor.php`, `.castor/*.php`) -- read and follow the `castor` skill first

## Operations hierarchy (strict)

For project operations, always use this order:

1. **Mate tools first** (if an equivalent Mate command exists)
2. **Castor task second** (`castor ...`) if Mate is not available
3. **Raw command last** (`docker compose ...`, etc.) only when neither Mate nor Castor provides the operation

Never jump directly to raw Docker/CLI commands when the same action exists in Mate or Castor.

Examples:

- Composer install/update/require -> Mate first: `mate/mate-tool-call.sh composer-install '{}'`, `mate/mate-tool-call.sh composer-update '{"mode":"summary"}'`, `mate/mate-tool-call.sh composer-require '{"package":"vendor/package","version":"^x.y"}'`; fallback: `castor dev:composer-install` / `castor dev:composer "..."`
- PHPUnit / PHPStan -> Mate first: `mate/mate-tool-call.sh phpunit-run-suite '{"mode":"summary"}'`, `mate/mate-tool-call.sh phpstan-analyse '{"mode":"summary"}'`; fallback: `castor dev:test` / `castor dev:phpstan`
- PHP CS Fixer -> use `castor dev:cs-fix` (no Mate equivalent required by default)
- Docker lifecycle -> `castor dev:*` / `castor prod:*` (not raw `docker compose up/down` unless no task exists)

## Key rules

- Always render `{{ attributes }}` on LiveComponent root elements.
- Prefer HTML Twig component syntax (`<twig:Alert />`).
- Use `data-model="debounce(300)|field"` for text fields in LiveComponents.
- Stimulus controllers must clean up listeners/observers in `disconnect()`.
- Turbo Frame IDs must match between page and response.
- Use Turbo Streams for multi-region updates; Frames for single-region updates.
- Prefer injecting `ClockInterface` for time-sensitive logic instead of calling system time directly.
- Prefer Tailwind utility classes over adding custom CSS rules.
- Add custom CSS only when utilities are not enough, and keep it in `assets/styles/app.css`.
- Keep tests deterministic: prefer static assertions and fixed inputs (avoid time/random/network dependent assertions).
- Use `WebTestCase` for HTTP behavior and assert response status + key page content.
- For infrastructure operations, use Castor tasks (`castor ...`); when adding or changing those tasks, follow the `castor` skill.
- Enforce command selection hierarchy: Mate -> Castor -> raw commands (raw only as fallback).
- For Composer/PHPStan/PHPUnit, always load `mate-tools` and use `mate/mate-tool-call.sh <tool-name> '<json-input>'`.
- Never call `docker compose exec ... vendor/bin/mate` directly.
- Never run Composer or PHP on the host for project operations.
- For browser verification, always use `playwright-cli` subagent.
- Prefer Mate tools for diagnostics/quality commands when available (`mate-tools` skill + wrapper scripts).

## Docker setup

- Runtime stack: FrankenPHP (PHP 8.5), Symfony worker mode.
- Keep `data/.gitignore` as `*` and `!.gitignore`.
- Dev compose: `compose.yaml` + `compose.override.yaml`.
- Prod-like compose: `compose.yaml` + `compose.prod.yaml`.

## Castor flow

- First-time setup: `castor dev:setup`, then `castor dev:bootstrap`.
- Local lifecycle: `castor dev:up`, `castor dev:down`, `castor dev:restart`, `castor dev:ps`.
- Prod-like lifecycle: `castor prod:up`, `castor prod:down`, `castor prod:restart`, `castor prod:ps`.

## Template placeholders

- Replace `/home/ineersa/projects/reader` in `.vscode/*.sh` wrappers.
- Replace `reader.ineersa.com` in `.env.prod.local.dist` for production setup.

## Suggested defaults for new features

- Start with Twig + UX (`stimulus`/`turbo`) before adding extra JS tooling.
- Keep pages server-rendered by default and prefer Turbo/Hotwire for navigation and partial updates.
- Add Live Components only for interactive stateful UI that cannot be handled cleanly with Turbo + Stimulus.
- Add at least one happy-path application test for each new route.
