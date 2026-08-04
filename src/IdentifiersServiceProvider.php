<?php

declare(strict_types=1);

namespace Moe\Identifiers;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\ServiceProvider;
use Moe\Identifiers\Support\NumberFormatter;
use Moe\Identifiers\Support\PublicIdCodec;
use Moe\Identifiers\Support\SequenceManager;

class IdentifiersServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
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

    /**
     * @return void
     */
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
