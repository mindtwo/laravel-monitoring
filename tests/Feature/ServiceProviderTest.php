<?php

declare(strict_types=1);

use Mindtwo\Monitoring\Contracts\Collector;
use Mindtwo\Monitoring\Contracts\ConfigurationRepository;
use Mindtwo\Monitoring\Contracts\Transport;
use Mindtwo\Monitoring\Data\CollectionResult;
use Mindtwo\Monitoring\Laravel\Facades\Monitoring;
use Mindtwo\Monitoring\Laravel\Support\LaravelConfigurationRepository;
use Mindtwo\Monitoring\Laravel\Transport\LaravelHttpTransport;
use Mindtwo\Monitoring\Monitor;

test('the monitor is a singleton with the configured collectors', function () {
    $monitor = app(Monitor::class);

    expect($monitor)->toBe(app(Monitor::class))
        ->and(array_keys($monitor->collectors()))->toBe(['laravel', 'laravel_environment', 'database']);
});

test('contracts are bound to their laravel implementations', function () {
    expect(app(ConfigurationRepository::class))->toBeInstanceOf(LaravelConfigurationRepository::class)
        ->and(app(Transport::class))->toBeInstanceOf(LaravelHttpTransport::class);
});

test('package config is merged with sensible defaults', function () {
    expect(config('monitoring.endpoint'))->toBe('https://monitoring.mindtwo.com/api/monitoring')
        ->and(config('monitoring.enabled'))->toBeTrue()
        ->and(config('monitoring.route.path'))->toBe('api/m2-monitoring')
        ->and(config('monitoring.schedule.cron'))->toBe('0 3 * * *');
});

test('custom collectors from the config are resolved through the container', function () {
    $custom = new class implements Collector
    {
        public function key(): string
        {
            return 'acme_custom';
        }

        public function supported(): bool
        {
            return true;
        }

        public function collect(): CollectionResult
        {
            return CollectionResult::ok($this->key(), ['answer' => 42]);
        }
    };

    app()->bind(get_class($custom), fn () => $custom);
    config()->set('monitoring.collectors', [get_class($custom)]);

    $snapshot = app(Monitor::class)->snapshot();

    expect($snapshot->result('acme_custom')?->data['answer'])->toBe(42);
});

test('the facade proxies to the monitor', function () {
    Monitoring::addCustomData('deployment', fn (): array => ['region' => 'eu-central-1']);

    $snapshot = Monitoring::snapshot();

    expect($snapshot->customData())->toBe(['deployment' => ['region' => 'eu-central-1']])
        ->and(Monitoring::has('laravel'))->toBeTrue();
});

test('the snapshot factory honors the monitoring environment override', function () {
    config()->set('monitoring.environment', 'edge');

    expect(app(Monitor::class)->snapshot()->environment)->toBe('edge');
});

test('the config file is publishable', function () {
    $target = config_path('monitoring.php');

    if (file_exists($target)) {
        unlink($target);
    }

    $this->artisan('vendor:publish', ['--tag' => 'monitoring-config'])->assertExitCode(0);

    expect(file_exists($target))->toBeTrue();

    unlink($target);
});
