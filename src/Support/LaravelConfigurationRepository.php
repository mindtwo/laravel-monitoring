<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Laravel\Support;

use Illuminate\Contracts\Config\Repository as ConfigContract;
use Mindtwo\Monitoring\Contracts\ConfigurationRepository;
use Mindtwo\Monitoring\Data\Credentials;
use Mindtwo\Monitoring\Transport\HttpTransport;

/**
 * Sources monitoring configuration from Laravel's config repository, which
 * already implements the required priority chain (config/monitoring.php
 * overrides → .env values → package defaults).
 */
final class LaravelConfigurationRepository implements ConfigurationRepository
{
    public function __construct(private ConfigContract $config) {}

    public function credentials(): Credentials
    {
        return new Credentials(
            trim((string) $this->config->get('monitoring.project_key', '')),
            trim((string) $this->config->get('monitoring.secret', ''))
        );
    }

    public function endpoint(): string
    {
        $endpoint = trim((string) $this->config->get('monitoring.endpoint', ''));

        return $endpoint !== '' ? $endpoint : HttpTransport::DEFAULT_ENDPOINT;
    }

    /**
     * @return array<int, string>
     */
    public function ipAllowList(): array
    {
        $list = $this->config->get('monitoring.ip_allow_list', []);

        if (is_string($list)) {
            $list = explode(',', $list);
        }

        if (! is_array($list)) {
            return [];
        }

        $entries = [];

        foreach ($list as $entry) {
            if (is_string($entry) && trim($entry) !== '') {
                $entries[] = trim($entry);
            }
        }

        return $entries;
    }

    /**
     * @param  mixed  $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return $this->config->get('monitoring.'.$key, $default);
    }
}
