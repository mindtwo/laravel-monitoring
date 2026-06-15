<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Laravel\Http\Controllers;

use Illuminate\Contracts\Cache\Repository as CacheContract;
use Illuminate\Contracts\Config\Repository as ConfigContract;
use Illuminate\Http\JsonResponse;
use Mindtwo\Monitoring\Monitor;

/**
 * The pull endpoint: returns a fresh snapshot as JSON. Snapshots are cached
 * for a short window so polling dashboards don't trigger repeated (and
 * potentially expensive) audit runs.
 */
final class PullController
{
    public const CACHE_KEY = 'mindtwo-monitoring.snapshot';

    public function __invoke(Monitor $monitor, CacheContract $cache, ConfigContract $config): JsonResponse
    {
        $seconds = max(0, (int) $config->get('monitoring.route.cache_seconds', 300));

        /** @var array<string, mixed> $payload */
        $payload = $seconds > 0
            ? $cache->remember(self::CACHE_KEY, $seconds, static fn (): array => $monitor->snapshot()->toArray())
            : $monitor->snapshot()->toArray();

        return new JsonResponse($payload, 200, [
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
