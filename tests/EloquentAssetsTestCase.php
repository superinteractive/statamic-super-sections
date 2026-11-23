<?php

declare(strict_types=1);

namespace SuperInteractive\StatamicSuperSections\Tests;

use Statamic\Eloquent\Assets\AssetModel;
use Statamic\Eloquent\ServiceProvider as EloquentServiceProvider;

abstract class EloquentAssetsTestCase extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), EloquentServiceProvider::class];
    }

    protected function resolveApplicationConfiguration($app): void
    {
        parent::resolveApplicationConfiguration($app);

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('statamic.eloquent-driver.asset_containers.driver', 'file');
        $app['config']->set('statamic.eloquent-driver.assets.driver', 'eloquent');
        $app['config']->set('statamic.eloquent-driver.assets.model', AssetModel::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        (require __DIR__.'/../vendor/statamic/eloquent-driver/database/migrations/2024_03_07_100000_create_asset_table.php')->up();
    }
}
