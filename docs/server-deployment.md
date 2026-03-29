# Server deployment (FrankenPHP, Docker, TLS, domain)

This guide assumes `compose.yaml` + `compose.prod.yaml`.

## Default mode (recommended)

By default, app HTTP is published to loopback only:

- `127.0.0.1:${APP_HTTP_PORT:-8080}:80`

Use host nginx (or another reverse proxy) for public TLS on 443.

## One-time setup

1. Copy prod local env file:

   ```bash
   cp .env.prod.local.dist .env.prod.local
   ```

2. Build and start:

   ```bash
   castor prod:build
   castor prod:up
   ```

3. Configure nginx to proxy `http://127.0.0.1:${APP_HTTP_PORT:-8080}`.

## Dedicated host mode (Caddy public 80/443)

If no other service owns host 80/443, set in `.env`:

```bash
APP_HTTP_BIND=0.0.0.0
APP_HTTP_PORT=80
```

Then publish host 443 with a local compose override.

## Runtime notes

- `compose.prod.yaml` loads `.env.prod.local` with `env_file`.
- Symfony environment is baked by `composer dump-env prod` during image build.
