<?php

declare(strict_types=1);

namespace Kami\Cocktail\Metrics;

use Throwable;
use Prometheus\Storage\Redis;
use Prometheus\CollectorRegistry;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Redis as LaravelRedis;
use Kami\Cocktail\Http\Middleware\TracksRequestMetric;
use Kami\Cocktail\Http\Middleware\TrackSQLQueriesMetric;

class MetricsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (config('bar-assistant.metrics.enabled') === false) {
            return;
        }

        if (config('bar-assistant.metrics.enabled') === true && config('cache.default') !== 'redis') {
            Log::warning('Metrics are enabled, but the cache driver is not set to redis. Metrics will not be available.');
            return;
        }

        $this->app->scoped(CollectorRegistry::class, function () {
            return new CollectorRegistry(Redis::fromExistingConnection(LaravelRedis::connection()->client()));
        });
    }

    public function boot(): void
    {
        if (config('bar-assistant.metrics.enabled') === false) {
            return;
        }

        if (config('cache.default') !== 'redis') {
            return;
        }

        // $this->app[Kernel::class]->pushMiddleware(TrackSQLQueriesMetric::class);
        $this->app[Kernel::class]->pushMiddleware(TracksRequestMetric::class);

        $metrics = collect([
            TotalBars::class,
            TotalActiveUsers::class,
        ]);

        $registry = $this->app->make(CollectorRegistry::class);

        $metrics->each(function (string $metricClassname) use ($registry) {
            try {
                $metric = new $metricClassname($registry);
                if (is_callable($metric)) {
                    $metric();
                }
            } catch (Throwable $e) {
                Log::error('Unable to register metric: ' . $metricClassname . '. Error: ' . $e->getMessage());
            }
        });
    }
}
