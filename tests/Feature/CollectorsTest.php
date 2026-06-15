<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Mindtwo\Monitoring\Laravel\Collectors\ConnectedDatabaseCollector;
use Mindtwo\Monitoring\Laravel\Collectors\LaravelCollector;
use Mindtwo\Monitoring\Laravel\Collectors\LaravelEnvironmentCollector;

test('the laravel collector reports the framework version as a known technology', function () {
    $result = app(LaravelCollector::class)->collect();

    expect($result->status)->toBe('ok')
        ->and($result->data)->toBe(['technology' => 'laravel', 'version' => Application::VERSION]);
});

test('the environment collector reports operational state', function () {
    $result = app(LaravelEnvironmentCollector::class)->collect();

    expect($result->status)->toBe('ok')
        ->and($result->data['environment'])->toBe('testing')
        ->and($result->data['debug'])->toBeBool()
        ->and($result->data['maintenance_mode'])->toBeFalse()
        ->and($result->data['drivers'])->toHaveKeys(['cache', 'queue', 'session', 'mail', 'filesystem'])
        ->and($result->data['cached'])->toHaveKeys(['config', 'routes']);
});

test('the connected database collector reads the live sqlite version', function () {
    $result = app(ConnectedDatabaseCollector::class)->collect();

    expect($result->status)->toBe('ok')
        ->and($result->data['technology'])->toBe('sqlite')
        ->and($result->data['version'])->toMatch('/^\d+\.\d+/')
        ->and($result->data['detected_via'])->toBe('connection')
        ->and($result->data['driver'])->toBe('sqlite');
});

test('a broken database connection fails the metric without aborting the snapshot', function () {
    config()->set('database.default', 'broken');
    config()->set('database.connections.broken', [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 1,
        'database' => 'missing',
        'username' => 'nobody',
        'password' => 'wrong',
    ]);

    $snapshot = app(Mindtwo\Monitoring\Monitor::class)->snapshot();

    expect($snapshot->result('database')?->status)->toBe('failed')
        ->and($snapshot->result('laravel')?->status)->toBe('ok');
});
