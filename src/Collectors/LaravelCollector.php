<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Laravel\Collectors;

use Illuminate\Foundation\Application;
use Mindtwo\Monitoring\Collectors\AbstractCollector;
use Mindtwo\Monitoring\Contracts\TechnologyResolver;
use Mindtwo\Monitoring\Data\CollectionResult;
use Mindtwo\Monitoring\Technology\EndOfLifeTechnologyResolver;

/**
 * The Laravel framework itself as a first-class technology metric, so the
 * dashboard can match it against endoflife.date support windows.
 */
final class LaravelCollector extends AbstractCollector
{
    private TechnologyResolver $technologies;

    public function __construct(?TechnologyResolver $technologies = null)
    {
        $this->technologies = $technologies ?? EndOfLifeTechnologyResolver::default();
    }

    public function key(): string
    {
        return 'laravel';
    }

    public function collect(): CollectionResult
    {
        return CollectionResult::ok($this->key(), $this->technologyData(
            $this->technologies->resolve('laravel'),
            Application::VERSION
        ));
    }
}
