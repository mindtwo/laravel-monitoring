<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Laravel\Console;

use Illuminate\Console\Command;
use Mindtwo\Monitoring\Data\CollectionResult;
use Mindtwo\Monitoring\Enums\Status;
use Mindtwo\Monitoring\Monitor;

final class ShowCommand extends Command
{
    protected $signature = 'monitoring:show {--json : Output the full snapshot as JSON}';

    protected $description = 'Collect and display the monitoring snapshot for this application';

    public function handle(Monitor $monitor): int
    {
        $snapshot = $monitor->snapshot();

        if ($this->option('json')) {
            $this->line($snapshot->toJson(JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($snapshot->results() as $key => $result) {
            $rows[] = [$key, $this->decorateStatus($result->status), $this->summarize($result)];
        }

        $this->table(['Metric', 'Status', 'Details'], $rows);
        $this->line(sprintf(
            '  Collected at %s for environment <comment>%s</comment>. Use <comment>--json</comment> for the full payload.',
            $snapshot->collectedAt,
            $snapshot->environment
        ));

        return self::SUCCESS;
    }

    private function decorateStatus(string $status): string
    {
        return match ($status) {
            Status::OK => '<info>ok</info>',
            Status::WARNING => '<comment>warning</comment>',
            Status::FAILED => '<error>failed</error>',
            default => $status,
        };
    }

    private function summarize(CollectionResult $result): string
    {
        if ($result->error !== null) {
            return $this->truncate($result->error);
        }

        if (isset($result->data['technology'])) {
            $version = $result->data['version'] ?? null;

            return trim((string) $result->data['technology'].' '.(is_scalar($version) ? (string) $version : ''));
        }

        if (isset($result->data['count'])) {
            return $result->data['count'].' packages';
        }

        if ($result->data === []) {
            return '';
        }

        return $this->truncate((string) json_encode($result->data, JSON_UNESCAPED_SLASHES));
    }

    private function truncate(string $value): string
    {
        return mb_strlen($value) > 70 ? mb_substr($value, 0, 70).'…' : $value;
    }
}
