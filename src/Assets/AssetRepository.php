<?php

declare(strict_types=1);

namespace SuperInteractive\StatamicSuperSections\Assets;

use League\Flysystem\PathTraversalDetected;
use Statamic\Contracts\Assets\Asset;
use Statamic\Eloquent\Assets\AssetRepository as EloquentAssetRepository;
use Statamic\Fieldtypes\Sets;

/**
 * Serves set preview images straight from their disk, so they need no database rows.
 */
final class AssetRepository extends EloquentAssetRepository
{
    public function findById($id): ?Asset
    {
        [$container, $path] = explode('::', $id);

        if ($container !== (Sets::previewImageConfig()['container'] ?? null)) {
            return parent::findById($id);
        }

        try {
            $asset = $this->make()->container($container)->path($path);

            return $asset->disk()->exists($path) ? $asset : null;
        } catch (PathTraversalDetected) {
            return null;
        }
    }
}
