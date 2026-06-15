<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Laravel\Console;

use Illuminate\Console\Command;
use Mindtwo\Monitoring\Monitor;
use Throwable;

final class CollectorsCommand extends Command
{
    protected $signature = 'monitoring:collectors';

    protected $description = 'List the registered monitoring collectors and whether they are supported here';

    public function handle(Monitor $monitor): int
    {
        $rows = [];

        foreach ($monitor->collectors() as $key => $collector) {
            try {
                $supported = $collector->supported() ? '<info>yes</info>' : '<comment>no</comment>';
            } catch (Throwable $exception) {
                $supported = '<error>error</error>';
            }

            $rows[] = [$key, get_class($collector), $supported];
        }

        $this->table(['Key', 'Collector', 'Supported'], $rows);

        return self::SUCCESS;
    }
}
