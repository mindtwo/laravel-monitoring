<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Laravel\Transport;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Client\Factory as HttpFactory;
use Mindtwo\Monitoring\Contracts\ConfigurationRepository;
use Mindtwo\Monitoring\Contracts\RequestSigner;
use Mindtwo\Monitoring\Contracts\Transport;
use Mindtwo\Monitoring\Data\Snapshot;
use Mindtwo\Monitoring\Data\TransportResult;
use Throwable;

/**
 * Push transport built on Laravel's HTTP client, so deliveries are visible to
 * Http::fake() in tests and benefit from the client's connection handling.
 * The signed JSON payload is sent byte-identical to what was signed.
 */
final class LaravelHttpTransport implements Transport
{
    public function __construct(
        private Container $container,
        private ConfigurationRepository $config,
        private RequestSigner $signer
    ) {}

    public function send(Snapshot $snapshot): TransportResult
    {
        $credentials = $this->config->credentials();

        if (! $credentials->isComplete()) {
            return TransportResult::failed(
                'Monitoring credentials are not configured. Set MONITORING_PROJECT_KEY and MONITORING_SECRET.'
            );
        }

        try {
            $payload = $snapshot->toJson();

            /** @var HttpFactory $http */
            $http = $this->container->make(HttpFactory::class);

            $response = $http
                ->withHeaders($this->signer->headers($payload, $credentials))
                ->withBody($payload, 'application/json')
                ->acceptJson()
                ->timeout(max(1, (int) $this->config->get('timeout', 15)))
                ->connectTimeout(5)
                ->post($this->config->endpoint());

            if ($response->successful()) {
                return TransportResult::delivered($response->status());
            }

            return TransportResult::failed(
                sprintf(
                    'The monitoring endpoint responded with HTTP %d%s.',
                    $response->status(),
                    ($summary = trim(mb_substr(strip_tags($response->body()), 0, 300))) !== '' ? ': '.$summary : ''
                ),
                $response->status()
            );
        } catch (Throwable $exception) {
            return TransportResult::failed($exception->getMessage());
        }
    }
}
