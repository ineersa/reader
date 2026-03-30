# Reader

Reader is a Symfony app that turns public web pages into clean markdown for LLM and automation workflows.

It provides a browser UI and a simple raw endpoint you can call from scripts.

## What It Does

- Fetches a public URL and extracts readable markdown.
- Supports direct raw output for tool integrations.
- Uses caching to avoid repeated expensive fetches.
- Applies validation, SSRF protection, and per-IP rate limiting.
- Result is returned as HTML (UI) or plain text markdown (raw endpoint).

## Demo app

Demo application is hosted here - [https://reader.ineersa.com](https://reader.ineersa.com)

## Endpoints

- `GET /`
    - Web UI for entering a URL and viewing markdown.

- `GET /read?url=<absolute-url>`
    - UI-oriented fetch endpoint.
    - Returns rendered page/frame content.

- `GET /r/{url}`
    - Raw markdown endpoint.
    - Response content type: `text/plain; charset=UTF-8`.
    - Best for scripts, bots, and tool integrations.

Examples:

```bash
curl "https://reader.ineersa.com/r/https://symfony.com/doc/current/ai/index.html"
```

## Security And Limits

- URL protocols limited to `http` and `https`.
- Internal/private network targets are blocked by Symfony `NoPrivateNetworkHttpClient`.
- Redirects are supported and checked by the protected HTTP client.
- Rate limiter is configured by `APP_SUBMIT_RATE_LIMIT` (10-minute sliding window).

## Local Quick Start

Install Castor once:

```bash
curl "https://castor.jolicode.com/install" | bash
```

Run app:

```bash
castor dev:setup
castor dev:bootstrap
castor dev:up
```

Open `http://localhost:${HTTP_PORT:-8080}`.

## Documentation

- Local setup: [docs/setup.md](docs/setup.md)
- Server deployment and TLS: [docs/server-deployment.md](docs/server-deployment.md)
- Castor task reference: [docs/castor.md](docs/castor.md)
