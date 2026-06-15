<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Laravel\Tests;

abstract class RouteDisabledTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('monitoring.route.enabled', false);
        $app['config']->set('monitoring.schedule.enabled', false);
    }
}
