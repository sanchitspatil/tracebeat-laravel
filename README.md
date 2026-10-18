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

## Mail content

The Nightwatch agent only reports a mail's subject and recipient counts. This package also sends the content of each outgoing mail (body, addresses, attachment list) to TraceBeat, so you can preview exactly what was sent, like Telescope's Mail tab. TraceBeat stores it only when the environment has **Store mail content** switched on, and only owners and admins can read it. Turn it off in the application with `TRACEBEAT_CAPTURE_MAIL=false`, or change what leaves the app:

```php
use TraceBeat\Laravel\Mail\MailCapture;

MailCapture::redactUsing(function (array $mail) {
    $mail['html'] = preg_replace('/token=[^"&]+/', 'token=***', $mail['html']);

    return $mail; // return null to skip a mail entirely
});
```

All `NIGHTWATCH_*` options (sampling, filtering, redaction) keep working. Existing Nightwatch installs can also keep `NIGHTWATCH_TOKEN` and `NIGHTWATCH_BASE_URL` and skip this package entirely.
