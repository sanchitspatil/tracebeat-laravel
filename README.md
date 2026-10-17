# TraceBeat for Laravel

Connects a Laravel application to your own [TraceBeat](https://github.com/) server. It is a thin layer over the official `laravel/nightwatch` package: the same sensors and agent, pointed at your server.

## Install

```bash
composer require tracebeat/laravel
php artisan tracebeat:install --url=https://tracebeat.example.com --token=YOUR_ENVIRONMENT_TOKEN
php artisan tracebeat:status
php artisan tracebeat:agent
```

Keep `tracebeat:agent` running under Supervisor, systemd or a Forge daemon.

## Commands

| Command | What it does |
| --- | --- |
| `tracebeat:install` | Publishes `config/nightwatch.php` (reads `TRACEBEAT_TOKEN`) and writes `TRACEBEAT_URL` and `TRACEBEAT_TOKEN` to `.env` |
| `tracebeat:agent` | Runs the Nightwatch agent against your TraceBeat server |
| `tracebeat:status` | Authenticates against `/api/agent-auth` and explains any failure |
| `tracebeat:deploy` | Sends deploy metadata so charts can mark deploys |

All `NIGHTWATCH_*` options (sampling, filtering, redaction) keep working. Existing Nightwatch installs can also keep `NIGHTWATCH_TOKEN` and `NIGHTWATCH_BASE_URL` and skip this package entirely.
