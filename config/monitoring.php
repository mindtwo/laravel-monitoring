<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Collectors\ApacheCollector;
use Mindtwo\Monitoring\Collectors\CaddyCollector;
use Mindtwo\Monitoring\Collectors\ComposerAuditCollector;
use Mindtwo\Monitoring\Collectors\ComposerLicensesCollector;
use Mindtwo\Monitoring\Collectors\ComposerPackagesCollector;
use Mindtwo\Monitoring\Collectors\GitStatusCollector;
use Mindtwo\Monitoring\Collectors\NginxCollector;
use Mindtwo\Monitoring\Collectors\NodeCollector;
use Mindtwo\Monitoring\Collectors\NpmAuditCollector;
use Mindtwo\Monitoring\Collectors\NpmPackagesCollector;
use Mindtwo\Monitoring\Collectors\OsCollector;
use Mindtwo\Monitoring\Collectors\PhpCollector;
use Mindtwo\Monitoring\Collectors\RedisCollector;
use Mindtwo\Monitoring\Collectors\SystemStatsCollector;
use Mindtwo\Monitoring\Laravel\Collectors\ConnectedDatabaseCollector;
use Mindtwo\Monitoring\Laravel\Collectors\LaravelCollector;
use Mindtwo\Monitoring\Laravel\Collectors\LaravelEnvironmentCollector;

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    |
    | When disabled, no pull route is registered and no scheduled push runs.
    | The artisan commands stay available for local inspection.
    |
    */

    'enabled' => (bool) env('MONITORING_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Credentials & endpoint
    |--------------------------------------------------------------------------
    |
    | Project key and secret are issued by the central monitoring dashboard.
    | The secret is never transmitted — payloads are HMAC-signed with it.
    |
    */

    'project_key' => env('MONITORING_PROJECT_KEY'),

    'secret' => env('MONITORING_SECRET'),

    'endpoint' => env('MONITORING_ENDPOINT', 'https://monitoring.mindtwo.com/api/monitoring'),

    /*
    |--------------------------------------------------------------------------
    | Snapshot context
    |--------------------------------------------------------------------------
    |
    | The reported environment defaults to the application environment. The
    | project root is where lockfiles, git metadata etc. are looked up and
    | defaults to base_path().
    |
    */

    'environment' => env('MONITORING_ENVIRONMENT'),

    'project_root' => null,

    /*
    |--------------------------------------------------------------------------
    | Push transport
    |--------------------------------------------------------------------------
    */

    'timeout' => (int) env('MONITORING_TIMEOUT', 15),

    /*
    |--------------------------------------------------------------------------
    | Collectors
    |--------------------------------------------------------------------------
    |
    | Every entry is resolved through the container, so custom collectors with
    | their own dependencies work out of the box — implement the Collector
    | contract and add your class here. The Laravel-specific
    | ConnectedDatabaseCollector intentionally replaces the CLI-based base
    | "database" detection with the live connection's server version.
    |
    */

    'collectors' => [
        OsCollector::class,
        PhpCollector::class,
        ConnectedDatabaseCollector::class,
        NginxCollector::class,
        ApacheCollector::class,
        CaddyCollector::class,
        RedisCollector::class,
        NodeCollector::class,
        SystemStatsCollector::class,
        ComposerPackagesCollector::class,
        NpmPackagesCollector::class,
        ComposerAuditCollector::class,
        ComposerLicensesCollector::class,
        NpmAuditCollector::class,
        GitStatusCollector::class,
        LaravelCollector::class,
        LaravelEnvironmentCollector::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pull endpoint
    |--------------------------------------------------------------------------
    |
    | A GET endpoint returning a fresh snapshot as JSON. Requests must be
    | signed with the project key + secret (see the README for the signature
    | spec) and pass the optional IP allow-list. Snapshots are cached briefly
    | to keep repeated polling cheap.
    |
    */

    'route' => [
        'enabled' => (bool) env('MONITORING_ROUTE_ENABLED', true),
        'path' => env('MONITORING_ROUTE_PATH', 'api/m2-monitoring'),
        'name' => 'monitoring.pull',
        'middleware' => ['throttle:monitoring'],
        'signature_tolerance' => (int) env('MONITORING_SIGNATURE_TOLERANCE', 300),
        'cache_seconds' => (int) env('MONITORING_ROUTE_CACHE', 300),
        'rate_limit_per_minute' => (int) env('MONITORING_RATE_LIMIT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | IP allow-list
    |--------------------------------------------------------------------------
    |
    | Optional. Plain IPs and CIDR ranges (IPv4/IPv6), as an array or a
    | comma-separated string. An empty list allows every IP — authentication
    | is always enforced via the signature either way.
    |
    */

    'ip_allow_list' => env('MONITORING_IP_ALLOW_LIST', []),

    /*
    |--------------------------------------------------------------------------
    | Scheduled push
    |--------------------------------------------------------------------------
    |
    | Registers `monitoring:push` on the scheduler. Requires your scheduler to
    | run (schedule:work / cron). Disable to wire the push yourself.
    |
    */

    'schedule' => [
        'enabled' => (bool) env('MONITORING_SCHEDULE_ENABLED', true),
        'cron' => env('MONITORING_SCHEDULE_CRON', '0 3 * * *'),
        'timezone' => env('MONITORING_SCHEDULE_TIMEZONE'),
    ],

];
