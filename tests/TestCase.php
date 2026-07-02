<?php

namespace MOE\Identifiers\Tests;

use MOE\Identifiers\IdentifiersServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            IdentifiersServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Alfabet Sqids deterministik untuk pengujian.
        $app['config']->set('moe-identifiers.public_id.driver', 'sqids');
        $app['config']->set('moe-identifiers.public_id.sqids.min_length', 8);
    }
}
