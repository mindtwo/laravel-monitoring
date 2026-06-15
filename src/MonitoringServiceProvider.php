<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Laravel;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Config\Repository as ConfigContract;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Mindtwo\Monitoring\Collectors\DefaultCollectors;
use Mindtwo\Monitoring\Contracts\Collector;
use Mindtwo\Monitoring\Contracts\ConfigurationRepository;
use Mindtwo\Monitoring\Contracts\ProcessRunner;
use Mindtwo\Monitoring\Contracts\RequestSigner;
use Mindtwo\Monitoring\Contracts\SignatureVerifier;
use Mindtwo\Monitoring\Contracts\TechnologyResolver;
use Mindtwo\Monitoring\Contracts\Transport;
use Mindtwo\Monitoring\Data\Source;
use Mindtwo\Monitoring\Laravel\Console\CollectorsCommand;
use Mindtwo\Monitoring\Laravel\Console\PushCommand;
use Mindtwo\Monitoring\Laravel\Console\ShowCommand;
use Mindtwo\Monitoring\Laravel\Http\Controllers\PullController;
use Mindtwo\Monitoring\Laravel\Http\Middleware\EnsureIpIsAllowed;
use Mindtwo\Monitoring\Laravel\Http\Middleware\VerifySignature;
use Mindtwo\Monitoring\Laravel\Support\LaravelConfigurationRepository;
use Mindtwo\Monitoring\Laravel\Transport\LaravelHttpTransport;
use Mindtwo\Monitoring\Monitor;
use Mindtwo\Monitoring\Process\ExecutableFinder;
use Mindtwo\Monitoring\Process\ProcessRunnerFactory;
use Mindtwo\Monitoring\SnapshotBuilder;
use Mindtwo\Monitoring\SnapshotFactory;
use Mindtwo\Monitoring\Technology\EndOfLifeTechnologyResolver;

final class MonitoringServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/monitoring.php', 'monitoring');

        $this->app->singleton(ProcessRunner::class, static fn (): ProcessRunner => ProcessRunnerFactory::make());
        $this->app->singleton(ExecutableFinder::class, static fn (): ExecutableFinder => new ExecutableFinder);
        $this->app->singleton(TechnologyResolver::class, static fn (): TechnologyResolver => EndOfLifeTechnologyResolver::default());
        $this->app->singleton(ConfigurationRepository::class, LaravelConfigurationRepository::class);
        $this->app->singleton(RequestSigner::class, static fn (): RequestSigner => new \Mindtwo\Monitoring\Transport\HmacRequestSigner);

        $this->app->singleton(SignatureVerifier::class, static function (Application $app): SignatureVerifier {
            /** @var ConfigContract $config */
            $config = $app->make(ConfigContract::class);

            return new \Mindtwo\Monitoring\Transport\HmacSignatureVerifier(
                max(0, (int) $config->get('monitoring.route.signature_tolerance', 300))
            );
        });

        // Shared singleton so Http::fake() in tests targets the same factory
        // instance the transport resolves at send time.
        $this->app->singletonIf(HttpFactory::class);

        $this->app->singleton(Transport::class, static fn (Application $app): Transport => new LaravelHttpTransport(
            $app,
            $app->make(ConfigurationRepository::class),
            $app->make(RequestSigner::class)
        ));

        $this->app->singleton(SnapshotFactory::class, static function (Application $app): SnapshotFactory {
            /** @var ConfigContract $config */
            $config = $app->make(ConfigContract::class);

            /** @var ConfigurationRepository $repository */
            $repository = $app->make(ConfigurationRepository::class);

            $projectKey = $repository->credentials()->projectKey;
            $environment = (string) ($config->get('monitoring.environment') ?? $app->environment());

            return new SnapshotFactory(
                Source::plugin(Source::TYPE_LARAVEL, 'mindtwo/laravel-monitoring'),
                $environment,
                $projectKey !== '' ? $projectKey : null
            );
        });

        $this->app->singleton(SnapshotBuilder::class, static fn (Application $app): SnapshotBuilder => new SnapshotBuilder(
            $app->make(SnapshotFactory::class)
        ));

        $this->app->singleton(Monitor::class, function (Application $app): Monitor {
            $monitor = new Monitor($app->make(SnapshotBuilder::class), $app->make(Transport::class));

            return $monitor->replace(...$this->resolveCollectors($app));
        });
    }

    public function boot(): void
    {
        $this->bootPublishing();
        $this->bootCommands();
        $this->bootRoute();
        $this->bootRateLimiter();
        $this->bootSchedule();
        $this->bootAboutCommand();
    }

    /**
     * Builds the collector list from config. Base collectors are constructed
     * with shared dependencies and the configured project root; unknown
     * classes (custom collectors) are resolved through the container.
     *
     * @return array<int, Collector>
     */
    private function resolveCollectors(Application $app): array
    {
        /** @var ConfigContract $config */
        $config = $app->make(ConfigContract::class);

        $projectRoot = $config->get('monitoring.project_root');
        $projectRoot = is_string($projectRoot) && $projectRoot !== '' ? $projectRoot : base_path();

        $defaults = [];

        foreach (DefaultCollectors::make(
            $app->make(ProcessRunner::class),
            $projectRoot,
            $app->make(TechnologyResolver::class),
            $app->make(ExecutableFinder::class)
        ) as $collector) {
            $defaults[get_class($collector)] = $collector;
        }

        $collectors = [];

        /** @var array<int, class-string<Collector>> $configured */
        $configured = (array) $config->get('monitoring.collectors', []);

        foreach ($configured as $class) {
            $collectors[] = $defaults[$class] ?? $app->make($class);
        }

        return $collectors;
    }

    private function bootPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/monitoring.php' => config_path('monitoring.php'),
        ], 'monitoring-config');
    }

    private function bootCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            PushCommand::class,
            ShowCommand::class,
            CollectorsCommand::class,
        ]);
    }

    private function bootRoute(): void
    {
        /** @var ConfigContract $config */
        $config = $this->app->make(ConfigContract::class);

        if (! $config->get('monitoring.enabled', true) || ! $config->get('monitoring.route.enabled', true)) {
            return;
        }

        $path = trim((string) $config->get('monitoring.route.path', 'api/m2-monitoring'), '/');

        if ($path === '') {
            return;
        }

        /** @var array<int, string> $middleware */
        $middleware = (array) $config->get('monitoring.route.middleware', []);

        Route::get($path, PullController::class)
            ->middleware(array_merge([EnsureIpIsAllowed::class, VerifySignature::class], $middleware))
            ->name((string) $config->get('monitoring.route.name', 'monitoring.pull'));
    }

    private function bootRateLimiter(): void
    {
        RateLimiter::for('monitoring', static function (Request $request): Limit {
            $perMinute = (int) config('monitoring.route.rate_limit_per_minute', 10);

            return Limit::perMinute(max(1, $perMinute))->by('mindtwo-monitoring|'.$request->ip());
        });
    }

    private function bootSchedule(): void
    {
        /** @var ConfigContract $config */
        $config = $this->app->make(ConfigContract::class);

        if (! $config->get('monitoring.enabled', true) || ! $config->get('monitoring.schedule.enabled', true)) {
            return;
        }

        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule) use ($config): void {
            $event = $schedule->command('monitoring:push')
                ->cron((string) $config->get('monitoring.schedule.cron', '0 3 * * *'))
                ->withoutOverlapping()
                ->onOneServer()
                ->runInBackground();

            $timezone = $config->get('monitoring.schedule.timezone');

            if (is_string($timezone) && $timezone !== '') {
                $event->timezone($timezone);
            }
        });
    }

    private function bootAboutCommand(): void
    {
        if (! class_exists(AboutCommand::class)) {
            return;
        }

        AboutCommand::add('mindtwo Monitoring', static fn (): array => [
            'Enabled' => config('monitoring.enabled') ? 'yes' : 'no',
            'Endpoint' => (string) config('monitoring.endpoint'),
            'Pull Route' => config('monitoring.route.enabled')
                ? '/'.trim((string) config('monitoring.route.path'), '/')
                : 'disabled',
            'Scheduled Push' => config('monitoring.schedule.enabled')
                ? (string) config('monitoring.schedule.cron')
                : 'disabled',
        ]);
    }
}
