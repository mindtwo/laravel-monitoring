<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;

test('the push command is scheduled with the configured cron expression', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event): bool => str_contains((string) $event->command, 'monitoring:push'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('0 3 * * *')
        ->and($events->first()->onOneServer)->toBeTrue()
        ->and($events->first()->withoutOverlapping)->toBeTrue();
});
