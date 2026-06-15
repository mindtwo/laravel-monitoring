# Changelog

All notable changes to `mindtwo/laravel-monitoring` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 1.0.0 - 2026-06-12

Initial release.

### Added

- Auto-discovered service provider wiring the full base-monitoring collector catalog through
  the container, configurable via `config/monitoring.php`.
- Laravel collectors: framework version (`laravel`), operational environment
  (`laravel_environment`: debug, maintenance mode, drivers, optimization caches) and the live
  database server version (`database`, replacing CLI client detection; MariaDB-behind-mysql
  aware).
- Scheduled push: `monitoring:push` registered on the scheduler (daily 03:00 default,
  `withoutOverlapping`, `onOneServer`, `runInBackground`), delivering signed snapshots through
  Laravel's HTTP client (`Http::fake()` compatible).
- Signed pull endpoint `GET /api/m2-monitoring` with HMAC verification, replay protection,
  per-IP rate limiting, optional IP/CIDR allow-list and short-lived snapshot caching.
- Artisan tooling: `monitoring:show [--json]`, `monitoring:push [--dry-run] [--compact]`,
  `monitoring:collectors`, plus `php artisan about` integration.
- `Monitoring` facade for registering collectors and lazy custom data at runtime.
- Full `.env` configuration surface (`MONITORING_*`) with secure defaults.
