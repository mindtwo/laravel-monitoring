<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Laravel\Console;

use Illuminate\Console\Command;
use Mindtwo\Monitoring\Monitor;

final class PushCommand extends Command
{
    protected $signature = 'monitoring:push
        {--dry-run : Print the snapshot payload instead of sending it}
        {--compact : With --dry-run, print compact instead of pretty JSON}';

    protected $description = 'Build a monitoring snapshot and push it to the configured endpoint';

    public function handle(Monitor $monitor): int
    {
        if ($this->option('dry-run')) {
            $this->line($monitor->snapshot()->toJson($this->option('compact') ? 0 : JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $result = $monitor->push();

        if ($result->success) {
            $this->info(sprintf('Monitoring snapshot delivered (HTTP %s).', $result->statusCode ?? 'n/a'));

            return self::SUCCESS;
        }

        $this->error('Monitoring snapshot could not be delivered: '.($result->error ?? 'unknown error'));

        return self::FAILURE;
    }
}
