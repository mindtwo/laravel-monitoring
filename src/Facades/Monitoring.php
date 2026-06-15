<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Mindtwo\Monitoring\Monitor;

/**
 * @method static Monitor register(\Mindtwo\Monitoring\Contracts\Collector ...$collectors)
 * @method static Monitor replace(\Mindtwo\Monitoring\Contracts\Collector ...$collectors)
 * @method static Monitor forget(string $key)
 * @method static bool has(string $key)
 * @method static array<string, \Mindtwo\Monitoring\Contracts\Collector> collectors()
 * @method static Monitor addCustomData(string $key, mixed $value)
 * @method static \Mindtwo\Monitoring\Data\Snapshot snapshot()
 * @method static \Mindtwo\Monitoring\Data\TransportResult push(?\Mindtwo\Monitoring\Contracts\Transport $transport = null)
 *
 * @see Monitor
 */
final class Monitoring extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Monitor::class;
    }
}
