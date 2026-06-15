<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;
use Mindtwo\Monitoring\Laravel\Tests\RouteDisabledTestCase;

uses(RouteDisabledTestCase::class);

test('no pull route is registered when disabled', function () {
    expect(Route::has('monitoring.pull'))->toBeFalse();

    $this->withHeaders(signedHeaders())->get('/api/m2-monitoring')->assertNotFound();
});

test('no scheduled push is registered when disabled', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event): bool => str_contains((string) $event->command, 'monitoring:push'));

    expect($events)->toBeEmpty();
});
