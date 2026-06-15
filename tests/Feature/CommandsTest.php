<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

test('monitoring:push --dry-run prints the payload without sending', function () {
    Http::fake();

    $this->artisan('monitoring:push', ['--dry-run' => true])
        ->expectsOutputToContain('"schema_version": "1.0"')
        ->assertExitCode(0);

    Http::assertNothingSent();
});

test('monitoring:push --dry-run --compact prints compact JSON', function () {
    $this->artisan('monitoring:push', ['--dry-run' => true, '--compact' => true])
        ->expectsOutputToContain('"schema_version":"1.0"')
        ->assertExitCode(0);
});

test('monitoring:push delivers and reports success', function () {
    Http::fake(['*' => Http::response('', 200)]);

    $this->artisan('monitoring:push')
        ->expectsOutputToContain('delivered (HTTP 200)')
        ->assertExitCode(0);
});

test('monitoring:push fails loudly on delivery errors', function () {
    Http::fake(['*' => Http::response('', 500)]);

    $this->artisan('monitoring:push')
        ->expectsOutputToContain('could not be delivered')
        ->assertExitCode(1);
});

test('monitoring:push fails with guidance when credentials are missing', function () {
    config()->set('monitoring.secret', null);

    Http::fake();

    $this->artisan('monitoring:push')
        ->expectsOutputToContain('MONITORING_PROJECT_KEY')
        ->assertExitCode(1);

    Http::assertNothingSent();
});

test('monitoring:show renders a table of metrics', function () {
    expect(Artisan::call('monitoring:show'))->toBe(0);

    $output = Artisan::output();

    expect($output)->toContain('laravel')
        ->toContain('database')
        ->toContain('sqlite')
        ->toContain('Collected at');
});

test('monitoring:show --json outputs the full snapshot', function () {
    expect(Artisan::call('monitoring:show', ['--json' => true]))->toBe(0);

    $payload = json_decode(Artisan::output(), true);

    expect($payload)->toBeArray()
        ->and($payload['schema_version'])->toBe('1.0')
        ->and($payload['metrics'])->toHaveKeys(['laravel', 'laravel_environment', 'database']);
});

test('monitoring:collectors lists registrations and support', function () {
    expect(Artisan::call('monitoring:collectors'))->toBe(0);

    expect(Artisan::output())
        ->toContain('laravel_environment')
        ->toContain('ConnectedDatabaseCollector');
});
