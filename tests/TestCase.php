<?php

declare(strict_types=1);

namespace SuperInteractive\StatamicSuperSections\Tests;

use Statamic\Testing\AddonTestCase;
use SuperInteractive\StatamicSuperSections\ServiceProvider;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;
}
