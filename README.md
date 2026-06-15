# mindtwo/laravel-monitoring

[![Tests](https://github.com/mindtwo/laravel-monitoring/actions/workflows/tests.yml/badge.svg)](https://github.com/mindtwo/laravel-monitoring/actions/workflows/tests.yml)
[![Larastan Level 8](https://img.shields.io/badge/Larastan-level%208-brightgreen)](phpstan.neon.dist)
[![PHP 8.1+](https://img.shields.io/badge/php-%5E8.1-blue)](composer.json)
[![Laravel 10–13](https://img.shields.io/badge/laravel-10%20%7C%2011%20%7C%2012%20%7C%2013-red)](composer.json)
[![License: MIT](https://img.shields.io/badge/license-MIT-lightgrey)](LICENSE.md)

Laravel plugin of the mindtwo monitoring suite. On top of
[`mindtwo/base-monitoring`](https://github.com/mindtwo/base-monitoring) — which collects OS,
web server, database, Node.js, system stats, Composer/npm packages, security audits, licenses
and git status — this package adds:

- **Laravel collectors** — framework version, operational environment (debug flag, maintenance
  mode, drivers, optimization caches) and the **live database server version** from your actual
  connection.
- **Push** — a scheduled `monitoring:push` run delivering signed snapshots to the central
  endpoint.
- **Pull** — a throttled, HMAC-authenticated `GET /api/m2-monitoring` endpoint with optional
  IP allow-listing and short-lived snapshot caching.
- **DX tooling** — `monitoring:show`, `monitoring:push --dry-run`, `monitoring:collectors`,
  `php artisan about` integration and a facade for custom data.

## Installation

```bash
composer require mindtwo/laravel-monitoring
```

Set your credentials (issued by the monitoring dashboard) in `.env`:

```dotenv
MONITORING_PROJECT_KEY=prj_live_8f3a…
MONITORING_SECRET=base64-encoded-shared-secret
```

That's it. The service provider auto-registers, the pull endpoint goes live at
`/api/m2-monitoring`, and a push is scheduled daily at 03:00 — provided your
[scheduler](https://laravel.com/docs/scheduling) is running.

Publish the config when you need to customize more:

```bash
php artisan vendor:publish --tag=monitoring-config
```

## Configuration

Everything is configurable via `.env` without publishing:

| Variable | Default | Purpose |
| --- | --- | --- |
| `MONITORING_ENABLED` | `true` | Master switch for route + schedule |
| `MONITORING_PROJECT_KEY` | – | Project key from the dashboard |
| `MONITORING_SECRET` | – | Shared secret (never transmitted) |
| `MONITORING_ENDPOINT` | `https://monitoring.mindtwo.com/api/monitoring` | Push target |
| `MONITORING_ENVIRONMENT` | `app()->environment()` | Reported environment |
| `MONITORING_TIMEOUT` | `15` | Push timeout (seconds) |
| `MONITORING_ROUTE_ENABLED` | `true` | Expose the pull endpoint |
| `MONITORING_ROUTE_PATH` | `api/m2-monitoring` | Pull endpoint path |
| `MONITORING_ROUTE_CACHE` | `300` | Pull snapshot cache (seconds, `0` disables) |
| `MONITORING_RATE_LIMIT` | `10` | Pull requests per minute per IP |
| `MONITORING_SIGNATURE_TOLERANCE` | `300` | Signature timestamp window (seconds) |
| `MONITORING_IP_ALLOW_LIST` | – | Comma-separated IPs / CIDR ranges |
| `MONITORING_SCHEDULE_ENABLED` | `true` | Register the scheduled push |
| `MONITORING_SCHEDULE_CRON` | `0 3 * * *` | Push cron expression |
| `MONITORING_SCHEDULE_TIMEZONE` | app timezone | Cron timezone |

## Artisan commands

```bash
php artisan monitoring:show              # human-readable snapshot table
php artisan monitoring:show --json       # the full payload
php artisan monitoring:push --dry-run    # print what would be sent
php artisan monitoring:push              # build + deliver a snapshot now
php artisan monitoring:collectors        # registered collectors + support status
```

`monitoring:show` example:

```text
+---------------------+--------+----------------------------+
| Metric              | Status | Details                    |
+---------------------+--------+----------------------------+
| os                  | ok     | macos 14.4.1               |
| php                 | ok     | php 8.3.2                  |
| database            | ok     | mysql 8.0.36               |
| laravel             | ok     | laravel 11.9.0             |
| composer_packages   | ok     | 73 packages                |
| composer_audit      | ok     |                            |
| nginx               | unsup… |                            |
+---------------------+--------+----------------------------+
```

## The pull endpoint

`GET /api/m2-monitoring` returns the current snapshot as JSON. Every request must be signed:

```text
X-Monitoring-Key:       <project key>
X-Monitoring-Timestamp: <unix timestamp>
X-Monitoring-Signature: hex( hmac_sha256( "<timestamp>.<raw request body>", secret ) )
```

The body of a pull GET is empty, so the signed string is simply `"<timestamp>."`. Example:

```bash
TIMESTAMP=$(date +%s)
SIGNATURE=$(printf '%s.' "$TIMESTAMP" | openssl dgst -sha256 -hmac "$MONITORING_SECRET" -hex | sed 's/^.* //')

curl https://example.com/api/m2-monitoring \
  -H "X-Monitoring-Key: $MONITORING_PROJECT_KEY" \
  -H "X-Monitoring-Timestamp: $TIMESTAMP" \
  -H "X-Monitoring-Signature: $SIGNATURE"
```

Defense layers, in request order:

1. **Rate limiting** — 10 requests/minute per IP by default (`throttle:monitoring`).
2. **IP allow-list** *(optional)* — plain IPs and CIDR ranges, IPv4 + IPv6. Behind a load
   balancer, configure [trusted proxies](https://laravel.com/docs/requests#configuring-trusted-proxies)
   so the client IP resolves correctly.
3. **Signature verification** — constant-time HMAC comparison plus a timestamp tolerance window
   against replays. Unconfigured credentials answer `503` and never expose data.
4. **Snapshot caching** — responses are cached for 5 minutes by default, keeping repeated polls
   from re-running expensive audit collectors.

## Customizing

### Choose or add collectors

The `collectors` config array is the single source of truth — reorder, remove, or add entries.
Every class is resolved through the container:

```php
// config/monitoring.php
'collectors' => [
    // ...defaults...
    \App\Monitoring\HorizonCollector::class,
],
```

```php
namespace App\Monitoring;

use Mindtwo\Monitoring\Collectors\AbstractCollector;
use Mindtwo\Monitoring\Data\CollectionResult;

final class HorizonCollector extends AbstractCollector
{
    public function key(): string
    {
        return 'horizon';
    }

    public function collect(): CollectionResult
    {
        return CollectionResult::ok($this->key(), [
            'status' => 'running',
        ]);
    }
}
```

### Attach custom data

```php
use Mindtwo\Monitoring\Laravel\Facades\Monitoring;

Monitoring::addCustomData('deployment', fn () => [
    'region' => config('app.region'),
    'release' => exec_free_release_id(),
]);
```

Closures are evaluated lazily and fault-isolated — a throwing provider records an error under
its key instead of breaking the snapshot.

### Replace the transport

Bind your own `Mindtwo\Monitoring\Contracts\Transport` implementation to ship snapshots
anywhere else (S3, a queue, a different protocol):

```php
$this->app->singleton(\Mindtwo\Monitoring\Contracts\Transport::class, MyTransport::class);
```

## How pushing works

`monitoring:push` is registered on the scheduler (daily at 03:00 by default) with
`withoutOverlapping()`, `onOneServer()` and `runInBackground()`. The command builds a snapshot
— every collector individually fault-isolated — signs the JSON payload and POSTs it through
Laravel's HTTP client, so `Http::fake()` sees it in your tests.

Delivery failures exit non-zero with a clear message; they never throw.

## Testing your integration

```php
use Illuminate\Support\Facades\Http;

Http::fake(['monitoring.mindtwo.com/*' => Http::response('', 200)]);

$this->artisan('monitoring:push')->assertExitCode(0);

Http::assertSent(fn ($request) => $request->hasHeader('X-Monitoring-Signature'));
```

## Development

```bash
composer install
composer check    # pint --test + larastan (level 8) + pest
```

## Security

If you discover a security issue, please email [info@mindtwo.de](mailto:info@mindtwo.de)
instead of opening a public issue.

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
