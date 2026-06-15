<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Laravel\Collectors;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Mindtwo\Monitoring\Collectors\AbstractCollector;
use Mindtwo\Monitoring\Contracts\TechnologyResolver;
use Mindtwo\Monitoring\Data\CollectionResult;
use Mindtwo\Monitoring\Support\DatabaseVersion;
use Mindtwo\Monitoring\Technology\EndOfLifeTechnologyResolver;
use PDO;
use Throwable;

/**
 * The live database server version taken from the actual application
 * connection — replacing the base package's CLI client detection, whose
 * client version can differ from the server's.
 */
final class ConnectedDatabaseCollector extends AbstractCollector
{
    private TechnologyResolver $technologies;

    public function __construct(
        private ConnectionResolverInterface $connections,
        ?TechnologyResolver $technologies = null,
        private ?string $connection = null
    ) {
        $this->technologies = $technologies ?? EndOfLifeTechnologyResolver::default();
    }

    public function key(): string
    {
        return 'database';
    }

    public function collect(): CollectionResult
    {
        try {
            $connection = $this->connections->connection($this->connection);
            $driver = $this->driverName($connection);
            $rawVersion = $this->serverVersion($connection);
        } catch (Throwable $exception) {
            return CollectionResult::failed($this->key(), 'Unable to inspect the database connection: '.$exception->getMessage());
        }

        [$identifier, $version] = DatabaseVersion::normalize($driver, $rawVersion);

        return CollectionResult::ok($this->key(), $this->technologyData(
            $this->technologies->resolve($identifier),
            $version,
            [
                'detected_via' => 'connection',
                'driver' => $driver,
                'connection' => $this->connection ?? 'default',
            ]
        ));
    }

    private function driverName(ConnectionInterface $connection): string
    {
        return method_exists($connection, 'getDriverName')
            ? (string) $connection->getDriverName()
            : 'unknown';
    }

    private function serverVersion(ConnectionInterface $connection): string
    {
        if (! method_exists($connection, 'getPdo')) {
            return '';
        }

        $pdo = $connection->getPdo();

        if (! $pdo instanceof PDO) {
            return '';
        }

        $version = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);

        return is_scalar($version) ? (string) $version : '';
    }
}
