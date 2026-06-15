<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Laravel\Tests;

use Mindtwo\Monitoring\Laravel\Collectors\ConnectedDatabaseCollector;
use Mindtwo\Monitoring\Laravel\Collectors\LaravelCollector;
use Mindtwo\Monitoring\Laravel\Collectors\LaravelEnvironmentCollector;
use Mindtwo\Monitoring\Laravel\MonitoringServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [MonitoringServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('monitoring.project_key', 'prj_test');
        $app['config']->set('monitoring.secret', 'test-secret');

        // Deterministic endpoint tests: no snapshot caching unless a test opts in.
        $app['config']->set('monitoring.route.cache_seconds', 0);

        // A fast, process-free collector set; collector-specific behavior is
        // tested directly against the collector classes.
        $app['config']->set('monitoring.collectors', [
            LaravelCollector::class,
            LaravelEnvironmentCollector::class,
            ConnectedDatabaseCollector::class,
        ]);

        $app['config']->set('database.default', 'testing');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('queue.default', 'sync');
    }
}
