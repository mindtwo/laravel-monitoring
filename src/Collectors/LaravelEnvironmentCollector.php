<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Laravel\Collectors;

use Illuminate\Contracts\Config\Repository as ConfigContract;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Foundation\CachesConfiguration;
use Illuminate\Contracts\Foundation\CachesRoutes;
use Mindtwo\Monitoring\Collectors\AbstractCollector;
use Mindtwo\Monitoring\Data\CollectionResult;

/**
 * Operational state of the Laravel application: debug flag, maintenance mode,
 * configured drivers and optimization caches — the settings worth alerting on
 * when they are wrong in production.
 */
final class LaravelEnvironmentCollector extends AbstractCollector
{
    public function __construct(
        private Application $app,
        private ConfigContract $config
    ) {}

    public function key(): string
    {
        return 'laravel_environment';
    }

    public function collect(): CollectionResult
    {
        return CollectionResult::ok($this->key(), [
            'environment' => (string) $this->app->environment(),
            'debug' => (bool) $this->config->get('app.debug'),
            'maintenance_mode' => $this->app->isDownForMaintenance(),
            'timezone' => (string) $this->config->get('app.timezone'),
            'locale' => (string) $this->config->get('app.locale'),
            'drivers' => [
                'cache' => $this->config->get('cache.default'),
                'queue' => $this->config->get('queue.default'),
                'session' => $this->config->get('session.driver'),
                'mail' => $this->config->get('mail.default'),
                'filesystem' => $this->config->get('filesystems.default'),
            ],
            'cached' => [
                'config' => $this->app instanceof CachesConfiguration ? $this->app->configurationIsCached() : null,
                'routes' => $this->app instanceof CachesRoutes ? $this->app->routesAreCached() : null,
            ],
        ]);
    }
}
