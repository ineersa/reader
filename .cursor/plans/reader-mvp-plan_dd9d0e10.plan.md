---
name: reader-mvp-plan
overview: Turn the current Symfony starter into a Jina-style URL reader with a minimal dark UI, reusable fetch/parse service layer ported from `browser-mcp`, Turbo-powered result updates, raw `/r/{url}` output, caching, throttling, and focused application tests.
todos:
    - id: ui-shell
      content: Replace the starter homepage/base styles with a re-search-inspired Tailwind/Turbo shell, description block, loader/error area, raw toggle, copy button, and unchanged footer.
      status: completed
    - id: reader-services
      content: Port and simplify the browser-mcp reader stack into local Symfony services with canonicalization, fetch logic, HTML-to-markdown conversion, and 10-minute cache reuse.
      status: completed
    - id: routes-and-throttle
      content: Add homepage submit/result handling plus `/r/{url}` raw output, and wire a 10 requests / 10 minutes per-IP limiter with clear 429 handling.
      status: completed
    - id: tests
      content: Add deterministic application tests for homepage, raw route, successful output, validation failures, and rate limiting.
      status: completed
isProject: false
---

# Reader MVP Plan

## Architecture

- Rework the current homepage in `[/home/ineersa/projects/reader/templates/home/index.html.twig](/home/ineersa/projects/reader/templates/home/index.html.twig)` and `[/home/ineersa/projects/reader/templates/base.html.twig](/home/ineersa/projects/reader/templates/base.html.twig)` into a single-screen reader UI styled after `[/home/ineersa/projects/re-search/templates/home/index.html.twig](/home/ineersa/projects/re-search/templates/home/index.html.twig)` and `[/home/ineersa/projects/re-search/assets/styles/app.css](/home/ineersa/projects/re-search/assets/styles/app.css)`: same dark shell, typography, Tailwind feel, same footer, new title/description/favicon, one URL input, result area below.
- Use Turbo for the read interaction: keep `GET /` for the shell, add a submit endpoint that returns a matching `<turbo-frame>` for the result panel, and use a tiny Stimulus controller for client-only concerns such as loader state, raw/rendered toggle, and copy-to-clipboard. This follows the repo guidance to prefer Turbo + Stimulus before Live Components.
- Add a dedicated raw endpoint `GET /r/{url}` that returns `text/plain; charset=UTF-8` markdown directly from the shared reader service. Route requirements should allow the full URL path segment so `/r/https://test.url.com` works.

## Reader Service Layer

- Port the essential logic from `[/home/ineersa/mcp-servers/browser-mcp/src/Service/Reader/HttpReader.php](/home/ineersa/mcp-servers/browser-mcp/src/Service/Reader/HttpReader.php)`, `[/home/ineersa/mcp-servers/browser-mcp/src/Service/OpenService.php](/home/ineersa/mcp-servers/browser-mcp/src/Service/OpenService.php)`, `[/home/ineersa/mcp-servers/browser-mcp/src/Service/Utilities.php](/home/ineersa/mcp-servers/browser-mcp/src/Service/Utilities.php)`, and `[/home/ineersa/mcp-servers/browser-mcp/src/Service/Reader/Processors/PageProcessor.php](/home/ineersa/mcp-servers/browser-mcp/src/Service/Reader/Processors/PageProcessor.php)` into `reader`-local services under `src/Service/Reader/`.
- Keep the parts that matter for this app: URL canonicalization, HTTP client headers/timeouts/retries, GitHub blob/raw-file special handling, HTML-to-markdown conversion via `ineersa/html2markdown`, UTF-8 normalization, and cache-backed document retrieval.
- Use the `ineersa/html2markdown` LLM-optimized `Config` profile as the default converter setup: `bodyWidth: 0`, `unicodeSnob: true`, `inlineLinks: true`, `skipInternalLinks: true`, `wrapLinks: false`, `wrapListItems: false`, `wrapTables: false`, `padTables: false`, `useAutomaticLinks: true`, `backquoteCodeStyle: true`, `imagesToAlt: true`, `ulItemMark: '-'`, `emphasisMark: '*'`.
- Intentionally drop MCP-only formatting concerns from `OpenService` such as line windows and snippet anchoring; the app only needs a normalized markdown document object plus user-facing exceptions for invalid URL, upstream failure, and rate-limit cases.
- Store successful reads in Symfony cache for 10 minutes using `[/home/ineersa/projects/reader/config/packages/cache.yaml](/home/ineersa/projects/reader/config/packages/cache.yaml)`, with cache keys based on canonical URL so the webpage and raw route share the same cached result.

## HTTP/UI Flow

- Replace the placeholder `HomeController` in `[/home/ineersa/projects/reader/src/Controller/HomeController.php](/home/ineersa/projects/reader/src/Controller/HomeController.php)` with a small reader controller set: homepage render, Turbo result action, and raw markdown action.
- Add server-side URL validation before any fetch. Validation errors should render inline in the result/error area; upstream/network/parser failures should show a readable message; rate-limit failures should return `429` semantics and a clear retry message.
- Render parsed markdown below the input, plus a raw view toggle and copy button. The rendered view can use the same `.markdown-body` pattern already proven in `re-search`, while the raw view stays in a monospace `<pre>`.
- Keep the footer content aligned with `re-search`, but add a short explanatory block near the hero describing how the reader fetches a URL, extracts the main content, converts it into LLM-friendly markdown, and that it is based on [ineersa/html2markdown](https://github.com/ineersa/html2markdown).

## Config And Limits

- Update `[/home/ineersa/projects/reader/config/packages/rate_limiter.yaml](/home/ineersa/projects/reader/config/packages/rate_limiter.yaml)` and env defaults in `[/home/ineersa/projects/reader/.env](/home/ineersa/projects/reader/.env)` from the template values to a 10-requests-per-10-minutes sliding window keyed by client IP.
- Add any new service wiring in `[/home/ineersa/projects/reader/config/services.yaml](/home/ineersa/projects/reader/config/services.yaml)` and composer requirements in `[/home/ineersa/projects/reader/composer.json](/home/ineersa/projects/reader/composer.json)` for the markdown converter package if it is not already present.
- Replace the inline SVG favicon defined in `[/home/ineersa/projects/reader/templates/base.html.twig](/home/ineersa/projects/reader/templates/base.html.twig)` with app-specific branding in `public/` or a new inline icon.

## Tests

- Expand `[/home/ineersa/projects/reader/tests/Application/HomepageTest.php](/home/ineersa/projects/reader/tests/Application/HomepageTest.php)` into focused application tests for:
- homepage shell rendering and descriptive copy
- successful raw route response for `/r/https://test.url.com`
- successful main-page read flow and expected markdown output area
- invalid URL handling
- rate limiting after 10 requests within the window
- Keep tests deterministic by using a fake or mocked HTTP client / reader service rather than live network calls.
- After implementation, verify with the project’s required tooling path: Mate first for PHPUnit/PHPStan, then fix any lints on edited files.
