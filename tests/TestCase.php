<?php

declare(strict_types=1);

namespace SuperInteractive\StatamicSuperSections\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Statamic\Providers\StatamicServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            StatamicServiceProvider::class,
        ];
    }
}
