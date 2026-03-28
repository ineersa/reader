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
- **Creating or updating Castor task definitions** (`castor.php`, `.castor/*.php`) -- read and follow the `castor` skill first

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
- Never run Composer or PHP on the host for project operations.
- For browser verification, always use `playwright-cli` subagent.
- Prefer Mate tools for diagnostics/quality commands when available (`mate-tools` skill + wrapper scripts).

## Docker setup

- Runtime stack: FrankenPHP (PHP 8.5), Symfony worker mode, Mercure, SQLite.
- SQLite file path: `data/app` (`DATABASE_URL=sqlite:///%kernel.project_dir%/data/app`).
- Keep `data/.gitignore` as `*` and `!.gitignore`.
- Dev compose: `compose.yaml` + `compose.override.yaml`.
- Prod-like compose: `compose.yaml` + `compose.prod.yaml`.

## Castor flow

- First-time setup: `castor dev:setup`, then `castor dev:bootstrap` (and `castor dev:console "doctrine:migrations:migrate --no-interaction"` if Doctrine is used).
- Background workers: `castor dev:messenger-consume`.
- Local lifecycle: `castor dev:up`, `castor dev:down`, `castor dev:restart`, `castor dev:ps`.
- Prod-like lifecycle: `castor prod:up`, `castor prod:down`, `castor prod:restart`, `castor prod:ps`.

## Template placeholders

- Replace `{{PROJECT_PATH}}` in `.vscode/*.sh` wrappers.
- Replace `{{APP_DOMAIN}}` in `.env.prod.local.dist` for production setup.

## Suggested defaults for new features

- Start with Twig + UX (`stimulus`/`turbo`) before adding extra JS tooling.
- Keep pages server-rendered by default and prefer Turbo/Hotwire for navigation and partial updates.
- Add Live Components only for interactive stateful UI that cannot be handled cleanly with Turbo + Stimulus.
- Add at least one happy-path application test for each new route.
