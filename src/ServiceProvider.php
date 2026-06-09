<?php

declare(strict_types=1);

namespace SuperInteractive\StatamicSuperSections;

use Illuminate\Support\Facades\Blade;
use Statamic\Providers\AddonServiceProvider;

final class ServiceProvider extends AddonServiceProvider
{
    public function bootAddon()
    {
        Blade::componentNamespace(
            'SuperInteractive\\StatamicSuperSections\\View\\Components',
            'super',
        );
    }
}
