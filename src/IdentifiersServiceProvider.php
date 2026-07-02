<?php

namespace MOE\Identifiers;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\ServiceProvider;
use MOE\Identifiers\Support\NumberFormatter;
use MOE\Identifiers\Support\PublicIdCodec;
use MOE\Identifiers\Support\SequenceManager;

class IdentifiersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/moe-identifiers.php',
            'moe-identifiers'
        );

        $this->app->singleton(PublicIdCodec::class, function ($app) {
            return new PublicIdCodec($app->make(Config::class));
        });

        $this->app->singleton(SequenceManager::class, function ($app) {
            return new SequenceManager(
                $app->make(ConnectionInterface::class),
                $app->make(Config::class),
            );
        });

        $this->app->singleton(NumberFormatter::class, function ($app) {
            return new NumberFormatter(
                $app->make(Config::class),
                $app->make(SequenceManager::class),
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/moe-identifiers.php' => config_path('moe-identifiers.php'),
            ], 'moe-identifiers-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'moe-identifiers-migrations');
        }

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
