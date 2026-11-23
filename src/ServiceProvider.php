<?php

declare(strict_types=1);

namespace SuperInteractive\StatamicSuperSections;

use Composer\InstalledVersions;
use Composer\Semver\VersionParser;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Statamic\Contracts\Assets\Asset;
use Statamic\Contracts\Assets\AssetRepository as AssetRepositoryContract;
use Statamic\Eloquent\Assets\AssetRepository as EloquentAssetRepository;
use Statamic\Fieldtypes\Sets;
use Statamic\Providers\AddonServiceProvider;
use Statamic\Statamic;

final class ServiceProvider extends AddonServiceProvider
{
    public function bootAddon(): void
    {
        Blade::componentNamespace(
            'SuperInteractive\\StatamicSuperSections\\View\\Components',
            'super',
        );

        if (! InstalledVersions::satisfies(new VersionParser, 'statamic/cms', '^6.0')) {
            return;
        }

        if (config('filesystems.disks.set_previews') === null) {
            config()->set('filesystems.disks.set_previews', [
                'driver' => 'local',
                'root' => resource_path('set-previews'),
                'throw' => false,
            ]);
        }

        config()->set('statamic.assets.set_preview_images', ['container' => 'set_previews', 'folder' => null]);

        if (config('statamic.eloquent-driver.assets.driver') === 'eloquent' && class_exists(EloquentAssetRepository::class)) {
            Statamic::repository(AssetRepositoryContract::class, Assets\AssetRepository::class);
        }

        Gate::before(fn ($user, string $ability, array $arguments): ?bool => $ability === 'view'
            && ($arguments[0] ?? null) instanceof Asset
            && $arguments[0]->containerHandle() === (Sets::previewImageConfig()['container'] ?? null)
            ? true
            : null);
    }
}
